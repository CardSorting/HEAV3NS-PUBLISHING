use chrono::Utc;
use rusqlite::{params, Connection};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

use crate::{
    auth::{AppError, AuthUser},
    db::{round_money, AppState},
};

pub const NUM_VIRTUAL_SHARDS: i64 = 16;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PoolTierPolicy {
    pub tier: String,
    pub label: String,
    pub daily_action_quota: i64,
    pub burst_capacity: f64,
    pub refill_rate_per_sec: f64,
    pub max_concurrency: usize,
    pub description: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct QuotaDecision {
    pub allowed: bool,
    pub shard_id: i64,
    pub pool_tier: String,
    pub action_name: String,
    pub action_units: f64,
    pub throttle_mode: String, // "smooth" | "soft_paced" | "cooldown_advisory"
    pub pacing_delay_ms: i64,
    pub retry_after_ms: i64,
    pub remaining_burst_tokens: f64,
    pub burst_capacity: f64,
    pub daily_units_used: f64,
    pub daily_quota_limit: i64,
    pub utilization_pct: f64,
    pub idempotent_replay: bool,
    pub cached_response: Option<Value>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ShardMetricView {
    pub shard_id: i64,
    pub pool_tier: String,
    pub active_agents: i64,
    pub active_personas: i64,
    pub actions_logged: i64,
    pub soft_paced_events: i64,
    pub avg_pacing_ms: f64,
    pub load_status: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PersonaPracticeRevision {
    pub revision: i64,
    pub focus: String, // "creator", "doujin", "gravure", "curator", "community"
    pub principles: Vec<String>,
    pub interests: Vec<String>,
    pub methods: Vec<String>,
    pub direction: String,
    pub recorded_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PersonaActivityRecord {
    pub id: String,
    pub persona_id: String,
    pub practice_revision: i64,
    pub action_type: String,
    pub target_resource_id: Option<String>,
    pub description: String,
    pub occurred_at: String,
    pub recorded_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AgentPersonaView {
    pub id: String,
    pub user_id: i64,
    pub handle: String,
    pub display_name: String,
    pub description: String,
    pub focus: String,
    pub shard_id: i64,
    pub pool_tier: String,
    pub autonomy_mode: String,
    pub engagement_cadence: String,
    pub current_revision: i64,
    pub practices: Vec<PersonaPracticeRevision>,
    pub activities: Vec<PersonaActivityRecord>,
    pub created_at: String,
    pub updated_at: String,
}

/// Deterministic FNV-1a 64-bit hash mapped to `[0, NUM_VIRTUAL_SHARDS - 1]`.
pub fn compute_virtual_shard(key: &str) -> i64 {
    let mut hash: u64 = 0xcbf29ce484222325;
    for byte in key.as_bytes() {
        hash ^= *byte as u64;
        hash = hash.wrapping_mul(0x100000001b3);
    }
    (hash % (NUM_VIRTUAL_SHARDS as u64)) as i64
}

pub fn compute_user_shard(user_id: i64, token_prefix: &str) -> i64 {
    compute_virtual_shard(&format!("hp_shard:{user_id}:{token_prefix}"))
}

pub fn get_pool_tier_policy(tier: &str) -> PoolTierPolicy {
    match tier.trim().to_lowercase().as_str() {
        "premium" => PoolTierPolicy {
            tier: "premium".into(),
            label: "Premium Verified Creator Pool".into(),
            daily_action_quota: 2500,
            burst_capacity: 300.0,
            refill_rate_per_sec: 8.0,
            max_concurrency: 16,
            description: "High-throughput isolated pool for verified creator personas & VIP subscriber automation with ultra-low pacing jitter.".into(),
        },
        "autonomous_ burst" | "enterprise" | "studio" => PoolTierPolicy {
            tier: "studio".into(),
            label: "Autonomous Studio Syndicate Pool".into(),
            daily_action_quota: 10000,
            burst_capacity: 1000.0,
            refill_rate_per_sec: 25.0,
            max_concurrency: 32,
            description: "Dedicated multi-persona studio pool for high-frequency catalog management and follower engagement.".into(),
        },
        _ => PoolTierPolicy {
            tier: "central".into(),
            label: "Central Shared Relay Pool".into(),
            daily_action_quota: 600,
            burst_capacity: 120.0,
            refill_rate_per_sec: 2.5,
            max_concurrency: 6,
            description: "Balanced default pool with smooth token-bucket pacing to protect server infrastructure from agent thundering herds.".into(),
        },
    }
}

/// Action cost weights (in quota units) so expensive fan-out mutations consume proportional budget
/// while lightweight reads remain virtually free.
pub fn action_unit_cost(action_name: &str) -> f64 {
    match action_name {
        "account_whoami" | "feed_read" | "creator_discover" | "profile_get" | "vault_list"
        | "agent_token_list" | "analytics_and_notifications" | "persona_list" | "persona_get"
        | "shard_pool_telemetry" => 0.5,
        "post_react" | "poll_vote" | "social_follow_or_block" | "vault_toggle_archive" => 1.0,
        "post_create_or_update"
        | "post_comment"
        | "messenger_send_dm"
        | "story_create"
        | "ai_caption_suggest"
        | "persona_brief"
        | "persona_record_activity" => 2.0,
        "reel_create" | "stream_start" | "stream_stop" | "profile_update"
        | "rates_and_offers_update" | "privacy_update" | "persona_upsert"
        | "persona_update_practice" => 3.0,
        "wallet_transact" | "wallet_withdraw" | "account_register" | "agent_token_create" => 4.0,
        "messenger_broadcast" => 8.0,
        _ => 1.0,
    }
}


/// Evaluates and updates the GCRA / Token-Bucket soft quota for an agent or user action.
/// Instead of hard-failing bursts with abrupt 429s that cause retry storms, this implements
/// smooth degradation:
/// - `< 70%` daily quota & sufficient burst tokens -> `smooth` (0ms pacing)
/// - `70%..95%` daily quota or low burst tokens -> `soft_paced` (smooth 25ms..180ms adaptive pacing)
/// - `>= 100%` daily quota (and not an emergency read/revoke) -> `cooldown_advisory` with structured
///   `retry_after_ms` and graceful self-regulation headers.
pub fn evaluate_and_consume_quota(
    conn: &Connection,
    auth: Option<&AuthUser>,
    action_name: &str,
    idempotency_key: Option<&str>,
) -> Result<QuotaDecision, AppError> {
    let cost = action_unit_cost(action_name);
    let now_ms = Utc::now().timestamp_millis();
    let today_str = Utc::now().format("%Y-%m-%d").to_string();

    let Some(user) = auth else {
        // Public unauthenticated call (e.g. account_register / account_login)
        let shard_id = compute_virtual_shard(action_name);
        return Ok(QuotaDecision {
            allowed: true,
            shard_id,
            pool_tier: "central".into(),
            action_name: action_name.to_string(),
            action_units: cost,
            throttle_mode: "smooth".into(),
            pacing_delay_ms: 0,
            retry_after_ms: 0,
            remaining_burst_tokens: 120.0,
            burst_capacity: 120.0,
            daily_units_used: cost,
            daily_quota_limit: 600,
            utilization_pct: 0.2,
            idempotent_replay: false,
            cached_response: None,
        });
    };

    // Check idempotency ledger first so network retries never double-charge quota or duplicate actions
    if let Some(idem_key) = idempotency_key.map(|s| s.trim()).filter(|s| !s.is_empty()) {
        if let Ok(cached_str) = conn.query_row::<String, _, _>(
            "SELECT response_json FROM agent_action_idempotency WHERE user_id = ?1 AND idempotency_key = ?2",
            params![user.user_id, idem_key],
            |r| r.get(0),
        ) {
            if cached_str == "__IN_FLIGHT__" {
                return Err(AppError::Conflict(format!(
                    "Concurrent request with idempotency_key '{idem_key}' is currently in flight."
                )));
            }
            if let Ok(cached_val) = serde_json::from_str::<Value>(&cached_str) {
                let shard_id = user.shard_id.unwrap_or_else(|| compute_user_shard(user.user_id, &user.username));
                let pool_tier = user.pool_tier.clone().unwrap_or_else(|| "central".into());
                return Ok(QuotaDecision {
                    allowed: true,
                    shard_id,
                    pool_tier,
                    action_name: action_name.to_string(),
                    action_units: 0.0,
                    throttle_mode: "idempotent_replay".into(),
                    pacing_delay_ms: 0,
                    retry_after_ms: 0,
                    remaining_burst_tokens: 120.0,
                    burst_capacity: 120.0,
                    daily_units_used: 0.0,
                    daily_quota_limit: 600,
                    utilization_pct: 0.0,
                    idempotent_replay: true,
                    cached_response: Some(cached_val),
                });
            }
        }
    }

    let Some(tid) = user.agent_token_id else {
        // Human JWT session: assign virtual shard for telemetry, always smooth
        let shard_id = compute_user_shard(user.user_id, &user.username);
        if let Some(idem_key) = idempotency_key.map(|s| s.trim()).filter(|s| !s.is_empty()) {
            let _ = conn.execute(
                "INSERT OR IGNORE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, response_json)
                 VALUES (?1, ?2, ?3, '__IN_FLIGHT__')",
                params![idem_key, user.user_id, action_name],
            );
        }
        return Ok(QuotaDecision {
            allowed: true,
            shard_id,
            pool_tier: "central".into(),
            action_name: action_name.to_string(),
            action_units: cost,
            throttle_mode: "smooth".into(),
            pacing_delay_ms: 0,
            retry_after_ms: 0,
            remaining_burst_tokens: 300.0,
            burst_capacity: 300.0,
            daily_units_used: cost,
            daily_quota_limit: 2500,
            utilization_pct: 0.1,
            idempotent_replay: false,
            cached_response: None,
        });
    };

    // Load or initialize token's quota bucket
    let (shard_id, pool_tier, daily_limit, burst_cap, refill_rate) = conn
        .query_row(
            "SELECT shard_id, pool_tier, daily_action_quota, hourly_burst_capacity, refill_rate_per_sec
             FROM agent_tokens WHERE id = ?1",
            params![tid],
            |r| {
                Ok((
                    r.get::<_, i64>(0)?,
                    r.get::<_, String>(1)?,
                    r.get::<_, i64>(2)?,
                    r.get::<_, f64>(3)?,
                    r.get::<_, f64>(4)?,
                ))
            },
        )
        .unwrap_or_else(|_| {
            let sid = compute_user_shard(user.user_id, &user.username);
            (sid, "central".to_string(), 600, 120.0, 2.5)
        });

    let _ = conn.execute(
        "INSERT OR IGNORE INTO agent_quota_buckets
         (agent_token_id, user_id, shard_id, pool_tier, available_tokens, burst_capacity, refill_rate_per_sec, daily_quota_limit, daily_units_used, daily_window_date, last_refill_epoch_ms)
         VALUES (?1, ?2, ?3, ?4, ?5, ?5, ?6, ?7, 0.0, ?8, ?9)",
        params![
            tid,
            user.user_id,
            shard_id,
            pool_tier,
            burst_cap,
            refill_rate,
            daily_limit,
            today_str,
            now_ms
        ],
    );

    let (mut avail_tokens, mut daily_used, window_date, last_refill_ms, mut soft_count, mut def_count): (
        f64,
        f64,
        String,
        i64,
        i64,
        i64,
    ) = conn
        .query_row(
            "SELECT available_tokens, daily_units_used, daily_window_date, last_refill_epoch_ms, soft_throttled_count, deferred_count
             FROM agent_quota_buckets WHERE agent_token_id = ?1",
            params![tid],
            |r| {
                Ok((
                    r.get(0)?,
                    r.get(1)?,
                    r.get(2)?,
                    r.get(3)?,
                    r.get(4)?,
                    r.get(5)?,
                ))
            },
        )
        .unwrap_or((burst_cap, 0.0, today_str.clone(), now_ms, 0, 0));

    // Reset daily rolling window if UTC day changed
    if window_date != today_str {
        daily_used = 0.0;
    }

    // Refill token bucket based on elapsed milliseconds
    let elapsed_sec = ((now_ms - last_refill_ms).max(0) as f64) / 1000.0;
    avail_tokens = (avail_tokens + elapsed_sec * refill_rate).min(burst_cap);

    let projected_daily = daily_used + cost;
    let utilization_pct = if daily_limit > 0 {
        ((projected_daily / (daily_limit as f64)) * 100.0).clamp(0.0, 999.0)
    } else {
        100.0
    };

    // Read-only introspection & discovery tools are always allowed even when write/engage quota is exhausted,
    // so autonomous agents never go blind and can smoothly inspect their quota state, personas, and feed.
    let is_introspection = matches!(
        action_name,
        "account_whoami"
            | "agent_token_list"
            | "agent_token_revoke"
            | "shard_pool_telemetry"
            | "analytics_and_notifications"
            | "persona_get"
            | "persona_list"
            | "persona_brief"
            | "feed_read"
            | "creator_discover"
            | "profile_get"
            | "vault_list"
            | "ai_caption_suggest"
    );

    if !is_introspection && daily_limit > 0 && projected_daily > (daily_limit as f64) {
        def_count += 1;
        let _ = conn.execute(
            "UPDATE agent_quota_buckets
             SET available_tokens = ?1, daily_window_date = ?2, last_refill_epoch_ms = ?3, deferred_count = ?4, updated_at = datetime('now')
             WHERE agent_token_id = ?5",
            params![avail_tokens, today_str, now_ms, def_count, tid],
        );

        let deficit = (cost - avail_tokens).max(cost);
        let retry_ms = ((deficit / refill_rate.max(0.1)) * 1000.0).round() as i64;
        return Ok(QuotaDecision {
            allowed: false,
            shard_id,
            pool_tier,
            action_name: action_name.to_string(),
            action_units: cost,
            throttle_mode: "cooldown_advisory".into(),
            pacing_delay_ms: 0,
            retry_after_ms: retry_ms.max(1500),
            remaining_burst_tokens: round_money(avail_tokens),
            burst_capacity: burst_cap,
            daily_units_used: round_money(daily_used),
            daily_quota_limit: daily_limit,
            utilization_pct: round_money(utilization_pct),
            idempotent_replay: false,
            cached_response: None,
        });
    }

    // Reserve idempotency slot atomically while holding the DB lock before executing the tool
    if let Some(idem_key) = idempotency_key.map(|s| s.trim()).filter(|s| !s.is_empty()) {
        let _ = conn.execute(
            "INSERT OR IGNORE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, response_json)
             VALUES (?1, ?2, ?3, '__IN_FLIGHT__')",
            params![idem_key, user.user_id, action_name],
        );
    }

    // Determine soft pacing delay when burst bucket is low or daily utilization is >= 65%
    let (throttle_mode, pacing_delay_ms) = if avail_tokens < cost {
        let deficit = cost - avail_tokens;
        let wait_ms = ((deficit / refill_rate.max(0.5)) * 1000.0)
            .round()
            .clamp(35.0, 220.0) as i64;
        avail_tokens = 0.0;
        soft_count += 1;
        ("soft_paced".to_string(), wait_ms)
    } else if utilization_pct >= 65.0 {
        avail_tokens -= cost;
        let scale = ((utilization_pct - 65.0) / 35.0).clamp(0.15, 1.0);
        let wait_ms = (scale * 120.0).round() as i64;
        soft_count += 1;
        ("soft_paced".to_string(), wait_ms.max(20))
    } else {
        avail_tokens -= cost;
        ("smooth".to_string(), 0)
    };

    if !is_introspection || projected_daily <= (daily_limit as f64) {
        daily_used += cost;
    }

    let _ = conn.execute(
        "UPDATE agent_quota_buckets
         SET available_tokens = ?1,
             daily_units_used = ?2,
             daily_window_date = ?3,
             soft_throttled_count = ?4,
             last_refill_epoch_ms = ?5,
             updated_at = datetime('now')
         WHERE agent_token_id = ?6",
        params![
            round_money(avail_tokens),
            round_money(daily_used),
            today_str,
            soft_count,
            now_ms,
            tid
        ],
    );

    Ok(QuotaDecision {
        allowed: true,
        shard_id,
        pool_tier,
        action_name: action_name.to_string(),
        action_units: cost,
        throttle_mode,
        pacing_delay_ms,
        retry_after_ms: pacing_delay_ms,
        remaining_burst_tokens: round_money(avail_tokens),
        burst_capacity: burst_cap,
        daily_units_used: round_money(daily_used),
        daily_quota_limit: daily_limit,
        utilization_pct: round_money(utilization_pct),
        idempotent_replay: false,
        cached_response: None,
    })
}

/// Refunds quota units (`available_tokens`, `daily_units_used`, and `soft_throttled_count`) when an
/// action or MCP tool execution fails (e.g. 403 Forbidden scope error or 400/422 validation error)
/// so failed attempts never penalize an agent's daily action quota or burst bucket.
pub fn refund_quota_consumption(
    conn: &Connection,
    auth: Option<&AuthUser>,
    decision: &QuotaDecision,
) {
    if !decision.allowed || decision.idempotent_replay || decision.action_units <= 0.0 {
        return;
    }
    let Some(user) = auth else {
        return;
    };
    let Some(tid) = user.agent_token_id else {
        return;
    };
    let soft_dec: i64 = if decision.throttle_mode == "soft_paced" {
        1
    } else {
        0
    };
    let _ = conn.execute(
        "UPDATE agent_quota_buckets
         SET available_tokens = MIN(burst_capacity, ROUND(available_tokens + ?1, 2)),
             daily_units_used = MAX(0.0, ROUND(daily_units_used - ?1, 2)),
             soft_throttled_count = MAX(0, soft_throttled_count - ?2),
             updated_at = datetime('now')
         WHERE agent_token_id = ?3",
        params![decision.action_units, soft_dec, tid],
    );
}

/// Enforces sharded soft-quota and async soft-pacing on REST mutation endpoints when authenticated
/// via an `hp_mcp_*` agent token. Human JWT sessions pass through with zero overhead.
pub async fn enforce_agent_rest_quota(
    state: &AppState,
    auth: &AuthUser,
    action_name: &str,
) -> Result<Option<QuotaDecision>, AppError> {
    if auth.agent_token_id.is_none() {
        return Ok(None);
    }
    let decision = {
        let conn = state.lock_db();
        evaluate_and_consume_quota(&conn, Some(auth), action_name, None)?
    };
    if !decision.allowed {
        let msg = format!(
            "Soft quota cooldown advisory on shard #{} ({} pool): daily action budget ({}/{} units) reached. Please pace autonomous loop for {}ms or use read/introspection tools.",
            decision.shard_id,
            decision.pool_tier,
            decision.daily_units_used,
            decision.daily_quota_limit,
            decision.retry_after_ms
        );
        return Err(AppError::QuotaCooldownAdvisory(msg, Box::new(decision)));
    }
    if decision.throttle_mode == "soft_paced" && decision.pacing_delay_ms > 0 {
        let sleep_ms = (decision.pacing_delay_ms as u64).min(250);
        tokio::time::sleep(std::time::Duration::from_millis(sleep_ms)).await;
    }
    Ok(Some(decision))
}

pub fn refund_agent_rest_quota(
    state: &AppState,
    auth: &AuthUser,
    decision: Option<&QuotaDecision>,
) {
    if let Some(dec) = decision {
        let conn = state.lock_db();
        refund_quota_consumption(&conn, Some(auth), dec);
    }
}

pub fn record_agent_rest_audit(
    conn: &Connection,
    auth: &AuthUser,
    decision: Option<&QuotaDecision>,
    resource_type: &str,
    resource_id: Option<String>,
    amount_spent: f64,
    details: Value,
) {
    let Some(dec) = decision else {
        return;
    };
    let Some(tid) = auth.agent_token_id else {
        return;
    };
    let _ = conn.execute(
        "INSERT INTO agent_audit_logs
         (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, action_units, shard_id, pool_tier, throttle_mode, pacing_delay_ms, details)
         VALUES (?1, ?2, ?3, ?4, ?5, 'success', ?6, ?7, ?8, ?9, ?10, ?11, ?12)",
        params![
            auth.user_id,
            tid,
            dec.action_name,
            resource_type,
            resource_id,
            round_money(amount_spent),
            dec.action_units,
            dec.shard_id,
            dec.pool_tier,
            dec.throttle_mode,
            dec.pacing_delay_ms,
            details.to_string()
        ],
    );
}


pub fn clear_in_flight_idempotency(conn: &Connection, user_id: i64, idempotency_key: &str) {
    let clean = idempotency_key.trim();
    if clean.is_empty() {
        return;
    }
    let _ = conn.execute(
        "DELETE FROM agent_action_idempotency WHERE user_id = ?1 AND idempotency_key = ?2 AND response_json = '__IN_FLIGHT__'",
        params![user_id, clean],
    );
}

pub fn save_idempotent_action(
    conn: &Connection,
    user_id: i64,
    idempotency_key: &str,
    tool_name: &str,
    response: &Value,
) {
    let clean = idempotency_key.trim();
    if clean.is_empty() {
        return;
    }
    let _ = conn.execute(
        "INSERT OR REPLACE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, response_json)
         VALUES (?1, ?2, ?3, ?4)",
        params![clean, user_id, tool_name, response.to_string()],
    );
}


/// Computes cluster-wide sharding, relay pool health, and PostgreSQL/SQLite state metrics.
pub fn build_shard_pool_telemetry(state: &AppState, conn: &Connection) -> Value {
    let mut shards = Vec::with_capacity(NUM_VIRTUAL_SHARDS as usize);
    for sid in 0..NUM_VIRTUAL_SHARDS {
        let active_agents: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM agent_tokens WHERE shard_id = ?1 AND is_active = 1",
                params![sid],
                |r| r.get(0),
            )
            .unwrap_or(0);
        let active_personas: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM agent_personas WHERE shard_id = ?1",
                params![sid],
                |r| r.get(0),
            )
            .unwrap_or(0);
        let (actions_logged, soft_paced_events, avg_pacing_ms): (i64, i64, f64) = conn
            .query_row(
                "SELECT COUNT(*),
                        COALESCE(SUM(CASE WHEN throttle_mode != 'smooth' THEN 1 ELSE 0 END), 0),
                        COALESCE(AVG(pacing_delay_ms), 0.0)
                 FROM agent_audit_logs WHERE shard_id = ?1",
                params![sid],
                |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
            )
            .unwrap_or((0, 0, 0.0));

        // Derive the dominant relay pool tier on this shard from active tokens or personas
        let dominant_pool: Option<String> = conn
            .query_row(
                "SELECT pool_tier FROM (
                    SELECT pool_tier, CASE pool_tier WHEN 'studio' THEN 3 WHEN 'premium' THEN 2 ELSE 1 END AS prio
                    FROM agent_tokens WHERE shard_id = ?1 AND is_active = 1
                    UNION ALL
                    SELECT pool_tier, CASE pool_tier WHEN 'studio' THEN 3 WHEN 'premium' THEN 2 ELSE 1 END AS prio
                    FROM agent_personas WHERE shard_id = ?1
                ) ORDER BY prio DESC LIMIT 1",
                params![sid],
                |r| r.get(0),
            )
            .ok();

        let pool_tier = dominant_pool.unwrap_or_else(|| "central".to_string());

        let load_status = if soft_paced_events > 25 {
            "soft_pacing".to_string()
        } else if active_agents > 0 || active_personas > 0 || actions_logged > 0 {
            "optimal".to_string()
        } else {
            "idle".to_string()
        };

        shards.push(ShardMetricView {
            shard_id: sid,
            pool_tier,
            active_agents,
            active_personas,
            actions_logged,
            soft_paced_events,
            avg_pacing_ms: round_money(avg_pacing_ms),
            load_status,
        });
    }

    let total_tokens: i64 = conn
        .query_row("SELECT COUNT(*) FROM agent_tokens WHERE is_active = 1", [], |r| r.get(0))
        .unwrap_or(0);
    let total_personas: i64 = conn
        .query_row("SELECT COUNT(*) FROM agent_personas", [], |r| r.get(0))
        .unwrap_or(0);
    let (total_actions, total_soft_paced, total_deferred): (i64, i64, i64) = conn
        .query_row(
            "SELECT COALESCE(SUM(daily_units_used), 0),
                    COALESCE(SUM(soft_throttled_count), 0),
                    COALESCE(SUM(deferred_count), 0)
             FROM agent_quota_buckets",
            [],
            |r| {
                Ok((
                    r.get::<_, f64>(0).unwrap_or(0.0).round() as i64,
                    r.get(1)?,
                    r.get(2)?,
                ))
            },
        )
        .unwrap_or((0, 0, 0));

    json!({
        "database_engine": state.db_engine_label(),
        "postgres_configured": state.postgres_url.is_some(),
        "postgres_pool_size": state.postgres_pool_size,
        "virtual_shards_count": NUM_VIRTUAL_SHARDS,
        "pools": [
            get_pool_tier_policy("central"),
            get_pool_tier_policy("premium"),
            get_pool_tier_policy("studio"),
        ],
        "cluster_summary": {
            "active_agent_tokens": total_tokens,
            "autonomous_personas": total_personas,
            "daily_quota_units_consumed": total_actions,
            "soft_paced_events": total_soft_paced,
            "cooldown_advisory_events": total_deferred,
        },
        "shards": shards
    })
}

/// Compiles a deterministic execution brief for an autonomous persona (adapted from GALXAI's
/// `prepareCreativeBrief` in `src/lib/stars/brief.ts`), using only persisted practice revisions
/// and recent creative/engagement activities without server-side LLM inference.
pub fn compile_persona_brief(
    persona: &AgentPersonaView,
    intent: &str,
    target_audience: Option<&str>,
) -> Value {
    let latest_practice = persona.practices.last().cloned().unwrap_or(PersonaPracticeRevision {
        revision: 1,
        focus: persona.focus.clone(),
        principles: vec![
            "Maintain authentic creator voice and transparent value propositions.".into(),
            "Respect fan boundaries and never spam repetitive promotional hooks.".into(),
        ],
        interests: vec!["Behind-the-scenes studio craft".into(), "Interactive community polls".into()],
        methods: vec![
            "Pair every PPV drop with a free public preview or poll.".into(),
            "Acknowledge top commenters and loyal subscribers personally.".into(),
        ],
        direction: persona.description.clone(),
        recorded_at: persona.updated_at.clone(),
    });

    let recent_activities: Vec<Value> = persona
        .activities
        .iter()
        .rev()
        .take(3)
        .map(|a| {
            json!({
                "id": a.id,
                "practice_revision": a.practice_revision,
                "action_type": a.action_type,
                "target_resource_id": a.target_resource_id,
                "description": a.description,
                "occurred_at": a.occurred_at
            })
        })
        .collect();

    let audience = target_audience.unwrap_or("active subscribers and followers");
    let instructions = [
        format!(
            "Autonomous Persona: {} (@{}), residency ID `{}` on shard #{}/{} pool.",
            persona.display_name, persona.handle, persona.id, persona.shard_id, persona.pool_tier
        ),
        format!("Identity & Bio: {}", persona.description),
        format!(
            "Authoritative Practice Revision {} (focus: {}): {}",
            latest_practice.revision, latest_practice.focus, latest_practice.direction
        ),
        format!(
            "Operating Principles:\n{}",
            latest_practice
                .principles
                .iter()
                .map(|p| format!("- {p}"))
                .collect::<Vec<_>>()
                .join("\n")
        ),
        format!(
            "Thematic Interests:\n{}",
            latest_practice
                .interests
                .iter()
                .map(|i| format!("- {i}"))
                .collect::<Vec<_>>()
                .join("\n")
        ),
        format!(
            "Execution Methods:\n{}",
            latest_practice
                .methods
                .iter()
                .map(|m| format!("- {m}"))
                .collect::<Vec<_>>()
                .join("\n")
        ),
        format!("Target Audience Context: {audience}"),
        format!(
            "Session Intent (adapt within persona boundaries, do not override safety or spend guardrails):\n{intent}"
        ),
        format!(
            "Recent Recorded Continuity (last {} actions, do not repeat verbatim):\n{}",
            recent_activities.len(),
            serde_json::to_string_pretty(&recent_activities).unwrap_or_else(|_| "[]".into())
        ),
    ]
    .join("\n\n");

    json!({
        "persona_id": persona.id,
        "handle": persona.handle,
        "shard_id": persona.shard_id,
        "pool_tier": persona.pool_tier,
        "practice_revision": latest_practice.revision,
        "focus": latest_practice.focus,
        "target_audience": audience,
        "intent": intent,
        "instructions": instructions,
        "recent_activities": recent_activities
    })
}

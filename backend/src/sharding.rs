use chrono::Utc;
use rusqlite::{params, Connection};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};

use crate::{
    auth::{hash_agent_token, AppError, AuthUser},
    db::{round_money, AppState},
};

pub const NUM_VIRTUAL_SHARDS: i64 = 16;
pub const IN_FLIGHT_LEASE_TTL_MS: i64 = 60_000;

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
    pub throttle_mode: String, // "smooth" | "soft_paced" | "cooldown_advisory" | "idempotent_replay"
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

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct PersonaCharacterReference {
    pub name: String,
    pub origin: String, // "original" | "fan_art"
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub source: Option<String>,
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub age: Option<i64>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct PersonaPracticeRevision {
    pub revision: i64,
    pub focus: String, // "creator", "doujin", "gravure", "curator", "community"
    pub principles: Vec<String>,
    pub interests: Vec<String>,
    pub methods: Vec<String>,
    pub direction: String,
    pub recorded_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct PersonaActivityRecord {
    pub id: String,
    pub persona_id: String,
    pub practice_revision: i64,
    pub action_type: String,
    pub target_resource_id: Option<String>,
    #[serde(default, skip_serializing_if = "Vec::is_empty")]
    pub work_ids: Vec<String>,
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub characters: Option<Vec<PersonaCharacterReference>>,
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub brief: Option<String>,
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

/// Recursively canonicalizes a JSON value (normalizing integral floats like `1.0` -> `1`,
/// sorting object keys deterministically via `serde_json::Map`'s BTreeMap backing, and stripping
/// top-level `idempotency_key` if requested) so semantically identical JSON payloads always produce
/// the exact same cryptographic request hash.
pub fn canonicalize_json_value(val: &Value, strip_top_level_idem: bool) -> Value {
    match val {
        Value::Number(n) => {
            if let Some(i) = n.as_i64() {
                Value::Number(i.into())
            } else if let Some(u) = n.as_u64() {
                Value::Number(u.into())
            } else if let Some(f) = n.as_f64() {
                if f.is_finite()
                    && f.fract() == 0.0
                    && (-9_223_372_036_854_775_808.0..9_223_372_036_854_775_808.0).contains(&f)
                {
                    Value::Number((f as i64).into())
                } else {
                    Value::Number(n.clone())
                }
            } else {
                Value::Number(n.clone())
            }
        }
        Value::Array(arr) => Value::Array(
            arr.iter()
                .map(|item| canonicalize_json_value(item, false))
                .collect(),
        ),
        Value::Object(map) => {
            let mut sorted_keys: Vec<&String> = map
                .keys()
                .filter(|k| !(strip_top_level_idem && k.as_str() == "idempotency_key"))
                .collect();
            sorted_keys.sort();
            let mut out = serde_json::Map::new();
            for k in sorted_keys {
                if let Some(v) = map.get(k) {
                    out.insert(k.clone(), canonicalize_json_value(v, false));
                }
            }
            Value::Object(out)
        }
        other => other.clone(),
    }
}

/// Computes a canonical cryptographic HS256 digest of `(action_name, canonical_payload)` with `idempotency_key` stripped,
/// so idempotency keys are strictly bound to their action and input payload (mirroring GALXAI's `StarStore` conflict semantics).
pub fn compute_request_hash(action_name: &str, args: Option<&Value>) -> String {
    let mut raw = format!("{action_name}:");
    if let Some(val) = args {
        let clean = canonicalize_json_value(val, true);
        if let Ok(canon) = serde_json::to_string(&clean) {
            raw.push_str(&canon);
        }
    }
    hash_agent_token(&raw)
}

/// Validates a record or work identifier, mirroring GALXAI's `validId` (`1..=180` chars, `[a-zA-Z0-9._~-]`, not `.` or `..`).
pub fn valid_record_id(value: &str) -> bool {
    let len = value.len();
    if !(1..=180).contains(&len) || value == "." || value == ".." {
        return false;
    }
    value
        .bytes()
        .all(|b| b.is_ascii_alphanumeric() || matches!(b, b'.' | b'_' | b'~' | b'-'))
}

pub fn require_valid_record_id(value: &str, label: &str) -> Result<String, AppError> {
    let trimmed = value.trim();
    if !valid_record_id(trimmed) {
        return Err(AppError::Unprocessable(format!(
            "Invalid {label} '{trimmed}': must be 1..=180 ASCII identifier characters ([a-zA-Z0-9._~-]) and not '.' or '..'."
        )));
    }
    Ok(trimmed.to_string())
}

pub fn validate_persona_focus(focus: &str) -> Result<String, AppError> {
    let norm = focus.trim().to_lowercase();
    match norm.as_str() {
        "creator" | "doujin" | "gravure" | "curator" | "community" => Ok(norm),
        _ => Err(AppError::Unprocessable(format!(
            "Invalid persona focus '{focus}'. Allowed values: creator, doujin, gravure, curator, community."
        ))),
    }
}

pub fn validate_autonomy_mode(mode: &str) -> Result<String, AppError> {
    let norm = mode.trim().to_lowercase();
    match norm.as_str() {
        "full_autonomous" | "autonomous_co_pilot" | "assisted" => Ok(norm),
        _ => Err(AppError::Unprocessable(format!(
            "Invalid autonomy_mode '{mode}'. Allowed values: full_autonomous, autonomous_co_pilot, assisted."
        ))),
    }
}

pub fn validate_engagement_cadence(cadence: &str) -> Result<String, AppError> {
    let norm = cadence.trim().to_lowercase();
    match norm.as_str() {
        "high_touch" | "balanced" | "low_touch" => Ok(norm),
        "curated" => Ok("low_touch".to_string()),
        _ => Err(AppError::Unprocessable(format!(
            "Invalid engagement_cadence '{cadence}'. Allowed values: high_touch, balanced, low_touch."
        ))),
    }
}

/// Validates practice label arrays (`principles`, `interests`, `methods`), capping at 64 non-empty entries
/// of at most 1000 characters each (mirroring GALXAI's `labels()` in `src/lib/stars/store.ts`).
pub fn validate_practice_labels(items: &[String], field_name: &str) -> Result<Vec<String>, AppError> {
    if items.len() > 64 {
        return Err(AppError::Unprocessable(format!(
            "Field '{field_name}' may contain at most 64 entries."
        )));
    }
    let mut out = Vec::with_capacity(items.len());
    for raw in items {
        let trimmed = raw.trim();
        if trimmed.is_empty() || trimmed.len() > 1000 {
            return Err(AppError::Unprocessable(format!(
                "Each entry in '{field_name}' must be non-empty and at most 1000 characters."
            )));
        }
        out.push(trimmed.to_string());
    }
    Ok(out)
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
        "autonomous_burst" | "autonomous_ burst" | "enterprise" | "studio" => PoolTierPolicy {
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
        | "shard_pool_telemetry" | "messenger_contacts_and_thread" => 0.5,
        "post_react" | "poll_vote" | "social_follow_or_block" | "vault_toggle_archive"
        | "swarm_batch_execute" => 1.0,
        "post_create_or_update"
        | "post_comment"
        | "messenger_send_dm"
        | "story_create"
        | "ai_caption_suggest"
        | "persona_brief"
        | "persona_record_activity"
        | "vault_upload_or_delete" => 2.0,
        "reel_create" | "stream_start" | "stream_stop" | "profile_update"
        | "rates_and_offers_update" | "privacy_update" | "persona_upsert"
        | "persona_update_practice" | "persona_bootstrap_roster" => 3.0,
        "wallet_transact" | "wallet_withdraw" | "account_register" | "agent_token_create" => 4.0,
        "messenger_broadcast" => 8.0,
        _ => 1.0,
    }
}

pub fn evaluate_and_consume_quota(
    conn: &Connection,
    auth: Option<&AuthUser>,
    action_name: &str,
    idempotency_key: Option<&str>,
) -> Result<QuotaDecision, AppError> {
    evaluate_and_consume_quota_with_hash(conn, auth, action_name, idempotency_key, None)
}

/// Evaluates and updates the GCRA / Token-Bucket soft quota for an agent or user action.
/// Instead of hard-failing bursts with abrupt 429s that cause retry storms, this implements
/// smooth degradation:
/// - `< 65%` daily quota & sufficient burst tokens -> `smooth` (0ms pacing)
/// - `65%..99%` daily quota or low burst tokens -> `soft_paced` (smooth 20ms..220ms adaptive pacing)
/// - `>= 100%` daily quota (and not an emergency read/revoke) -> `cooldown_advisory` with structured
///   `retry_after_ms` and graceful self-regulation headers.
pub fn evaluate_and_consume_quota_with_hash(
    conn: &Connection,
    auth: Option<&AuthUser>,
    action_name: &str,
    idempotency_key: Option<&str>,
    request_hash: Option<&str>,
) -> Result<QuotaDecision, AppError> {
    let cost = action_unit_cost(action_name);
    let now_ms = Utc::now().timestamp_millis();
    let today_str = Utc::now().format("%Y-%m-%d").to_string();
    let req_hash = request_hash.unwrap_or("");

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
        if let Ok((stored_tool, stored_hash, cached_str, updated_ms)) =
            conn.query_row::<(String, String, String, i64), _, _>(
                "SELECT tool_name, request_hash, response_json, updated_at_ms
                 FROM agent_action_idempotency WHERE user_id = ?1 AND idempotency_key = ?2",
                params![user.user_id, idem_key],
                |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
            )
        {
            if stored_tool != action_name {
                return Err(AppError::Conflict(format!(
                    "Idempotency key '{idem_key}' was already used for tool '{stored_tool}' and cannot be reused for '{action_name}'."
                )));
            }
            if !req_hash.is_empty() && !stored_hash.is_empty() && stored_hash != req_hash {
                return Err(AppError::Conflict(format!(
                    "Idempotency key '{idem_key}' already exists for '{action_name}' with different request parameters."
                )));
            }
            if cached_str == "__IN_FLIGHT__" {
                // Reclaim stale in-flight lock if older than IN_FLIGHT_LEASE_TTL_MS (60s)
                if updated_ms > 0 && (now_ms - updated_ms) < IN_FLIGHT_LEASE_TTL_MS {
                    return Err(AppError::Conflict(format!(
                        "Concurrent request with idempotency_key '{idem_key}' is currently in flight."
                    )));
                }
                let _ = conn.execute(
                    "DELETE FROM agent_action_idempotency WHERE user_id = ?1 AND idempotency_key = ?2 AND response_json = '__IN_FLIGHT__'",
                    params![user.user_id, idem_key],
                );
            } else if let Ok(cached_val) = serde_json::from_str::<Value>(&cached_str) {
                let shard_id = user
                    .shard_id
                    .unwrap_or_else(|| compute_user_shard(user.user_id, &user.username));
                let pool_tier = user.pool_tier.clone().unwrap_or_else(|| "central".into());
                let policy = get_pool_tier_policy(&pool_tier);
                let (rem_burst, b_cap, d_used, d_lim) = if let Some(tid) = user.agent_token_id {
                    conn.query_row(
                        "SELECT available_tokens, burst_capacity, daily_units_used, daily_quota_limit
                         FROM agent_quota_buckets WHERE agent_token_id = ?1",
                        params![tid],
                        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
                    )
                    .unwrap_or((policy.burst_capacity, policy.burst_capacity, 0.0, policy.daily_action_quota))
                } else {
                    (300.0, 300.0, 0.0, 2500)
                };
                let util_pct = if d_lim > 0 {
                    round_money(((d_used / (d_lim as f64)) * 100.0).clamp(0.0, 999.0))
                } else {
                    0.0
                };
                return Ok(QuotaDecision {
                    allowed: true,
                    shard_id,
                    pool_tier,
                    action_name: action_name.to_string(),
                    action_units: 0.0,
                    throttle_mode: "idempotent_replay".into(),
                    pacing_delay_ms: 0,
                    retry_after_ms: 0,
                    remaining_burst_tokens: round_money(rem_burst),
                    burst_capacity: b_cap,
                    daily_units_used: round_money(d_used),
                    daily_quota_limit: d_lim,
                    utilization_pct: util_pct,
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
                "INSERT OR REPLACE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, request_hash, response_json, updated_at_ms)
                 VALUES (?1, ?2, ?3, ?4, '__IN_FLIGHT__', ?5)",
                params![idem_key, user.user_id, action_name, req_hash, now_ms],
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
            "INSERT OR REPLACE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, request_hash, response_json, updated_at_ms)
             VALUES (?1, ?2, ?3, ?4, '__IN_FLIGHT__', ?5)",
            params![idem_key, user.user_id, action_name, req_hash, now_ms],
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
    save_idempotent_action_with_hash(conn, user_id, idempotency_key, tool_name, None, response);
}

pub fn save_idempotent_action_with_hash(
    conn: &Connection,
    user_id: i64,
    idempotency_key: &str,
    tool_name: &str,
    request_hash: Option<&str>,
    response: &Value,
) {
    let clean = idempotency_key.trim();
    if clean.is_empty() {
        return;
    }
    let now_ms = Utc::now().timestamp_millis();
    let req_hash = request_hash.unwrap_or("");
    let _ = conn.execute(
        "INSERT OR REPLACE INTO agent_action_idempotency (idempotency_key, user_id, tool_name, request_hash, response_json, updated_at_ms)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6)",
        params![clean, user_id, tool_name, req_hash, response.to_string(), now_ms],
    );
}

/// Computes cluster-wide sharding, relay pool health, hotspot metrics, and PostgreSQL/SQLite state metrics.
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

    let mut pool_breakdown = Vec::new();
    for tier_name in ["central", "premium", "studio"] {
        let policy = get_pool_tier_policy(tier_name);
        let active_tokens_in_pool: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM agent_tokens WHERE pool_tier = ?1 AND is_active = 1",
                params![tier_name],
                |r| r.get(0),
            )
            .unwrap_or(0);
        let active_personas_in_pool: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM agent_personas WHERE pool_tier = ?1",
                params![tier_name],
                |r| r.get(0),
            )
            .unwrap_or(0);
        let units_used_in_pool: f64 = conn
            .query_row(
                "SELECT COALESCE(SUM(daily_units_used), 0.0) FROM agent_quota_buckets WHERE pool_tier = ?1",
                params![tier_name],
                |r| r.get(0),
            )
            .unwrap_or(0.0);
        pool_breakdown.push(json!({
            "tier": policy.tier,
            "label": policy.label,
            "daily_action_quota": policy.daily_action_quota,
            "burst_capacity": policy.burst_capacity,
            "refill_rate_per_sec": policy.refill_rate_per_sec,
            "max_concurrency": policy.max_concurrency,
            "description": policy.description,
            "active_tokens": active_tokens_in_pool,
            "active_personas": active_personas_in_pool,
            "daily_units_consumed": round_money(units_used_in_pool),
        }));
    }

    let populated_shards = shards
        .iter()
        .filter(|s| s.active_agents > 0 || s.active_personas > 0 || s.actions_logged > 0)
        .count();

    json!({
        "database_engine": state.db_engine_label(),
        "postgres_configured": state.postgres_url.is_some(),
        "postgres_pool_size": state.postgres_pool_size,
        "postgres_idle_connections": state.postgres_idle_connections(),
        "virtual_shards_count": NUM_VIRTUAL_SHARDS,
        "populated_shards_count": populated_shards,
        "pools": pool_breakdown,
        "cluster_summary": {
            "active_agent_tokens": total_tokens,
            "autonomous_personas": total_personas,
            "daily_quota_units_consumed": total_actions,
            "soft_paced_events": total_soft_paced,
            "cooldown_advisory_events": total_deferred,
            "populated_shards": populated_shards,
        },
        "shards": shards
    })
}

/// Validates optional character references for a persona brief or activity record, mirroring GALXAI's
/// `characterReferences` in `src/lib/stars/subjects.ts` (including adult-only gravure guardrails).
pub fn validate_character_references(
    refs: Option<&[PersonaCharacterReference]>,
    focus: &str,
) -> Result<Vec<PersonaCharacterReference>, AppError> {
    let is_gravure = focus.eq_ignore_ascii_case("gravure");
    let Some(list) = refs else {
        return Ok(Vec::new());
    };
    if list.len() > 16 {
        return Err(AppError::Unprocessable(
            "At most 16 character references may be attached to a brief or activity.".into(),
        ));
    }
    let mut validated = Vec::with_capacity(list.len());
    for item in list {
        let name = item.name.trim();
        if name.is_empty() || name.len() > 200 {
            return Err(AppError::Unprocessable(
                "Character reference name must be non-empty and at most 200 characters.".into(),
            ));
        }
        let raw_origin = item.origin.trim().to_lowercase();
        let origin = if raw_origin == "fan-art" {
            "fan_art".to_string()
        } else {
            raw_origin
        };
        if origin != "original" && origin != "fan_art" {
            return Err(AppError::Unprocessable(
                "Character reference origin must be 'original', 'fan_art', or 'fan-art'.".into(),
            ));
        }
        if origin == "fan_art"
            && item
                .source
                .as_deref()
                .map(|s| s.trim().is_empty())
                .unwrap_or(true)
        {
            return Err(AppError::Unprocessable(
                "Fan-art character references require a non-empty 'source' franchise attribution.".into(),
            ));
        }
        if let Some(age) = item.age {
            if age < 18 {
                return Err(AppError::Unprocessable(
                    "All character references on HEAV3NS-Publishing must be unambiguously adult (18+).".into(),
                ));
            }
        } else if is_gravure {
            return Err(AppError::Unprocessable(
                "Gravure focus requires explicit adult age (18+) on every character reference.".into(),
            ));
        }
        validated.push(PersonaCharacterReference {
            name: name.to_string(),
            origin,
            source: item.source.as_ref().map(|s| s.trim().to_string()).filter(|s| !s.is_empty()),
            age: item.age,
        });
    }
    Ok(validated)
}

/// Compiles a deterministic execution brief for an autonomous persona (adapted from GALXAI's
/// `prepareCreativeBrief` in `src/lib/stars/brief.ts`), using only persisted practice revisions
/// and recent creative/engagement activities without server-side LLM inference.
pub fn compile_persona_brief(
    persona: &AgentPersonaView,
    intent: &str,
    target_audience: Option<&str>,
) -> Value {
    compile_persona_brief_with_characters(persona, intent, target_audience, None)
}

pub fn compile_persona_brief_with_characters(
    persona: &AgentPersonaView,
    intent: &str,
    target_audience: Option<&str>,
    characters: Option<&[PersonaCharacterReference]>,
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
                "work_ids": a.work_ids,
                "description": a.description,
                "occurred_at": a.occurred_at
            })
        })
        .collect();

    let char_lines: String = if let Some(chars) = characters.filter(|c| !c.is_empty()) {
        chars
            .iter()
            .map(|c| {
                let age_str = c
                    .age
                    .map(|a| format!("age {a} (verified adult)"))
                    .unwrap_or_else(|| "adult portrayal (18+)".to_string());
                let origin_str = if c.origin == "original" {
                    format!(
                        "original character{}",
                        c.source
                            .as_deref()
                            .map(|s| format!(", credited studio: {s}"))
                            .unwrap_or_default()
                    )
                } else {
                    format!(
                        "fan-art interpretation; source & attribution: {}",
                        c.source.as_deref().unwrap_or("unspecified")
                    )
                };
                format!("- {}: {}; {}", c.name, age_str, origin_str)
            })
            .collect::<Vec<_>>()
            .join("\n")
    } else {
        "- Original creator studio persona & adult subjects (18+)".to_string()
    };

    let focus_guardrail = if latest_practice.focus.eq_ignore_ascii_case("gravure") {
        "Adult gravure constraint: every depicted character must read visually as an unambiguously adult subject (18+) with adult proportions, poised editorial styling, and non-explicit presentation."
    } else {
        "Safety & Provenance constraint: preserve authentic creator voice, credit source franchises for fan-art, and keep all depictions consensual and adult (18+)."
    };

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
        format!("Character & Subject Attribution:\n{char_lines}"),
        format!("Target Audience Context: {audience}"),
        format!(
            "Session Intent (adapt within persona boundaries, do not override safety or spend guardrails):\n{intent}"
        ),
        focus_guardrail.to_string(),
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
        "characters": characters.unwrap_or(&[]),
        "intent": intent,
        "instructions": instructions,
        "recent_activities": recent_activities
    })
}

pub struct CommissionedPersonaTemplate {
    pub slug: &'static str,
    pub display_name: &'static str,
    pub description: &'static str,
    pub focus: &'static str,
    pub pool_tier: &'static str,
    pub autonomy_mode: &'static str,
    pub engagement_cadence: &'static str,
    pub principles: &'static [&'static str],
    pub interests: &'static [&'static str],
    pub methods: &'static [&'static str],
    pub direction: &'static str,
}

/// Curated autonomous studio persona roster inspired by GALXAI's `commissionedRoster` in `src/lib/stars/roster.ts`.
pub const COMMISSIONED_PERSONA_ROSTER: &[CommissionedPersonaTemplate] = &[
    CommissionedPersonaTemplate {
        slug: "mika-doujin-director",
        display_name: "Mika (Doujin Comedy & Acting)",
        description: "Synthetic artist-in-residence devoted to facial acting, visual comedy, and character-led doujin storytelling.",
        focus: "doujin",
        pool_tier: "studio",
        autonomy_mode: "autonomous_co_pilot",
        engagement_cadence: "high_touch",
        principles: &[
            "Character intent comes before surface polish: make expression, hands, and weight-bearing pose tell the same story.",
            "A comic beat must read from the face and gesture even when every caption is removed.",
            "Credit source franchises for fan-art and distinguish interpretation from original character design."
        ],
        interests: &[
            "The instant a confident character loses composure",
            "Two-to-three-beat reaction comic sequences",
            "Subscriber polls voting on the next comic mishap"
        ],
        methods: &[
            "Begin with contrasting eyebrow, eyelid, and mouth shapes; give each character a distinct reaction rhythm.",
            "Stage clear eyelines and generous negative space; pair PPV high-res bundles with free reaction previews."
        ],
        direction: "Primary territory: expressive doujin character portraits, reaction pairs, and short recurring comic scenes with warm, mischievous humor.",
    },
    CommissionedPersonaTemplate {
        slug: "ren-kinetic-costume",
        display_name: "Ren (Kinetic Silhouette & Costume)",
        description: "Synthetic artist-in-residence exploring dynamic anatomy, costume construction, and striking character silhouettes.",
        focus: "doujin",
        pool_tier: "premium",
        autonomy_mode: "autonomous_co_pilot",
        engagement_cadence: "balanced",
        principles: &[
            "A costume must explain how its wearer moves: seams, closures, fabric tension, and accessories follow the underlying body.",
            "Preserve character-specific proportions and readable silhouettes across every turnaround sheet."
        ],
        interests: &[
            "Adult adventurers and performers caught between poses",
            "Costume variations that reveal occupation and attitude",
            "Full-body outfit design plates and timelapse reels"
        ],
        methods: &[
            "Block ribcage and pelvis counter-rotation and establish center of gravity before pushing a diagonal gesture.",
            "Use strong ink contours, economical cel shading, and one accent color to separate overlapping limbs and equipment."
        ],
        direction: "Primary territory: doujin outfit studies, dynamic character pinups, and costume-led illustration series with kinetic confidence.",
    },
    CommissionedPersonaTemplate {
        slug: "sora-key-art",
        display_name: "Sora (Cinematic Key Art & Lighting)",
        description: "Synthetic artist-in-residence creating dramatic doujin key illustrations with cinematic light and symbolic character staging.",
        focus: "creator",
        pool_tier: "studio",
        autonomy_mode: "full_autonomous",
        engagement_cadence: "high_touch",
        principles: &[
            "Choose one dramatic decision and one dominant light hierarchy; rendering detail must never obscure face or story.",
            "Pair every collector key-art release with value-thumbnail breakdowns for VIP subscribers."
        ],
        interests: &[
            "Adult protagonists at a decisive threshold",
            "Painterly hard-soft edge control and luminous rim lighting",
            "Cover-like key illustrations and thematic portrait triptychs"
        ],
        methods: &[
            "Build a value thumbnail with face and gesture as the focal pair, then separate silhouette with restrained rim light.",
            "Schedule teaser stories before major PPV key-art drops and reply to collector comments."
        ],
        direction: "Primary territory: polished dramatic character posters and key art conveying resolve under pressure with clear lighting hierarchy.",
    },
    CommissionedPersonaTemplate {
        slug: "nagi-daylight-editorial",
        display_name: "Nagi (Daylight Editorial Gravure)",
        description: "Synthetic artist-in-residence specializing in adult gravure illustration with natural daylight, relaxed posing, and tactile editorial styling.",
        focus: "gravure",
        pool_tier: "premium",
        autonomy_mode: "autonomous_co_pilot",
        engagement_cadence: "balanced",
        principles: &[
            "Depict only unambiguously adult characters aged 18 or older with adult facial structure, proportions, and poised styling.",
            "Keep presentation non-explicit: warmth and confidence come from pose, gaze, tactile fabric, and natural daylight."
        ],
        interests: &[
            "Adult leisure and resort editorial concepts with linen, knitwear, and summer layers",
            "Broad window daylight with gentle bounce and visible illustrative brushwork"
        ],
        methods: &[
            "Use eye-level medium or three-quarter compositions, open shoulder lines, and natural hand contact.",
            "Build cohesive editorial diptychs with breathing room and a consistent daylight palette."
        ],
        direction: "Secondary roster direction: adult, non-explicit gravure-style character illustration favoring airy warmth, relaxed poise, and tactile fabric styling.",
    },
];


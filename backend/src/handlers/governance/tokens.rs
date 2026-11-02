use axum::{
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    Json,
};
use chrono::Utc;
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::{round_money, AppState},
    handlers::governance::personas::list_user_agent_personas,
    models::*,
};

pub fn list_user_agent_tokens(
    conn: &rusqlite::Connection,
    user_id: i64,
) -> Result<Vec<AgentTokenView>, rusqlite::Error> {
    let today_str = Utc::now().format("%Y-%m-%d").to_string();
    let mut stmt = conn.prepare(
        "SELECT id, user_id, name, token_prefix, scopes, max_spend_per_tx, daily_spend_limit,
                pool_tier, shard_id, daily_action_quota, hourly_burst_capacity, refill_rate_per_sec,
                is_active, last_used_at, expires_at, created_at
         FROM agent_tokens WHERE user_id = ?1 ORDER BY id DESC",
    )?;
    let rows = stmt
        .query_map(params![user_id], |r| {
            let tid: i64 = r.get(0)?;
            let scopes_raw: String = r.get(4)?;
            let scopes: Vec<String> = serde_json::from_str(&scopes_raw).unwrap_or_default();
            let spent_today: f64 = conn
                .query_row(
                    "SELECT COALESCE(SUM(amount_spent), 0.0) FROM agent_audit_logs
                     WHERE agent_token_id = ?1 AND status = 'success' AND created_at >= datetime('now', '-24 hours')",
                    params![tid],
                    |sr| sr.get(0),
                )
                .unwrap_or(0.0);
            let rounded_spent = round_money(spent_today);
            let pool_tier: String = r.get(7)?;
            let shard_id: i64 = r.get(8)?;
            let daily_action_quota: i64 = r.get(9)?;
            let burst_capacity: f64 = r.get(10)?;
            let refill_rate_per_sec: f64 = r.get(11)?;

            let (avail_tokens, daily_units_used, window_date): (f64, f64, String) = conn
                .query_row(
                    "SELECT available_tokens, daily_units_used, daily_window_date
                     FROM agent_quota_buckets WHERE agent_token_id = ?1",
                    params![tid],
                    |qr| Ok((qr.get(0)?, qr.get(1)?, qr.get(2)?)),
                )
                .unwrap_or((burst_capacity, 0.0, today_str.clone()));

            let effective_daily_used = if window_date == today_str {
                round_money(daily_units_used)
            } else {
                0.0
            };

            let utilization_pct = if daily_action_quota > 0 {
                (effective_daily_used / (daily_action_quota as f64)) * 100.0
            } else {
                0.0
            };
            let throttle_mode = if utilization_pct >= 100.0 {
                "cooldown_advisory".to_string()
            } else if utilization_pct >= 75.0 || avail_tokens < 5.0 {
                "soft_paced".to_string()
            } else {
                "smooth".to_string()
            };

            Ok(AgentTokenView {
                id: tid,
                user_id: r.get(1)?,
                name: r.get(2)?,
                token_prefix: r.get(3)?,
                scopes,
                max_spend_per_tx: r.get(5)?,
                daily_spend_limit: r.get(6)?,
                spent_today: rounded_spent,
                spent_last_24h: rounded_spent,
                shard_id,
                pool_tier,
                daily_action_quota,
                daily_units_used: effective_daily_used,
                available_burst_tokens: round_money(avail_tokens),
                burst_capacity,
                refill_rate_per_sec,
                throttle_mode,
                is_active: r.get::<_, i64>(12)? != 0,
                last_used_at: r.get(13)?,
                expires_at: r.get(14)?,
                created_at: r.get(15)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();
    Ok(rows)
}

pub fn list_user_agent_audit_logs(
    conn: &rusqlite::Connection,
    user_id: i64,
    limit: i64,
) -> Result<Vec<AgentAuditLogView>, rusqlite::Error> {
    let mut stmt = conn.prepare(
        "SELECT l.id, l.user_id, l.agent_token_id, t.name, l.tool_name, l.resource_type, l.resource_id,
                l.status, l.amount_spent, l.action_units, l.shard_id, l.pool_tier, l.throttle_mode,
                l.pacing_delay_ms, l.details, l.created_at
         FROM agent_audit_logs l
         LEFT JOIN agent_tokens t ON l.agent_token_id = t.id
         WHERE l.user_id = ?1
         ORDER BY l.id DESC LIMIT ?2",
    )?;
    let rows = stmt
        .query_map(params![user_id, limit], |r| {
            Ok(AgentAuditLogView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                agent_token_id: r.get(2)?,
                agent_name: r.get(3)?,
                tool_name: r.get(4)?,
                resource_type: r.get(5)?,
                resource_id: r.get(6)?,
                status: r.get(7)?,
                amount_spent: r.get(8)?,
                action_units: r.get(9)?,
                shard_id: r.get(10)?,
                pool_tier: r.get(11)?,
                throttle_mode: r.get(12)?,
                pacing_delay_ms: r.get(13)?,
                details: r.get(14)?,
                created_at: r.get(15)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();
    Ok(rows)
}

#[derive(Deserialize)]
pub struct CreateAgentTokenPayload {
    pub name: String,
    pub scopes: Option<Vec<String>>,
    pub max_spend_per_tx: Option<f64>,
    pub daily_spend_limit: Option<f64>,
    pub pool_tier: Option<String>,
    pub daily_action_quota: Option<i64>,
    pub expires_in_days: Option<i64>,
}

pub async fn create_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateAgentTokenPayload>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() {
        auth.require_scope("admin")?;
    }
    let name = payload.name.trim();
    if name.is_empty() {
        return Err(AppError::Unprocessable("Agent key name is required.".into()));
    }
    let scopes = payload.scopes.unwrap_or_else(|| {
        vec![
            "read".into(),
            "write".into(),
            "engage".into(),
            "monetize".into(),
            "messages".into(),
            "wallet".into(),
        ]
    });
    let scopes_json = serde_json::to_string(&scopes).unwrap_or_else(|_| "[]".into());
    let max_tx = round_money(payload.max_spend_per_tx.unwrap_or(50.0).max(0.0));
    let daily_lim = round_money(payload.daily_spend_limit.unwrap_or(200.0).max(0.0));

    let policy = crate::sharding::get_pool_tier_policy(payload.pool_tier.as_deref().unwrap_or("central"));
    let daily_action_quota = payload
        .daily_action_quota
        .filter(|&q| q > 0)
        .unwrap_or(policy.daily_action_quota);

    let raw_secret = format!(
        "hp_mcp_{}_{}",
        auth.user_id,
        Uuid::new_v4().simple()
    );
    let prefix = format!("{}...", &raw_secret[..14.min(raw_secret.len())]);
    let token_hash = crate::auth::hash_agent_token(&raw_secret);
    let shard_id = crate::sharding::compute_user_shard(auth.user_id, &prefix);

    let conn = state.lock_db();
    let expires_at: Option<String> = payload.expires_in_days.and_then(|days| {
        if days > 0 {
            conn.query_row(
                "SELECT datetime('now', ?1)",
                params![format!("+{days} days")],
                |r| r.get(0),
            )
            .ok()
        } else {
            None
        }
    });

    conn.execute(
        "INSERT INTO agent_tokens
         (user_id, name, token_prefix, token_hash, scopes, max_spend_per_tx, daily_spend_limit,
          pool_tier, shard_id, daily_action_quota, hourly_burst_capacity, refill_rate_per_sec, is_active, expires_at)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, 1, ?13)",
        params![
            auth.user_id,
            name,
            prefix,
            token_hash,
            scopes_json,
            max_tx,
            daily_lim,
            policy.tier,
            shard_id,
            daily_action_quota,
            policy.burst_capacity,
            policy.refill_rate_per_sec,
            expires_at
        ],
    )?;
    let tid = conn.last_insert_rowid();

    let today_str = Utc::now().format("%Y-%m-%d").to_string();
    let now_ms = Utc::now().timestamp_millis();
    let _ = conn.execute(
        "INSERT OR REPLACE INTO agent_quota_buckets
         (agent_token_id, user_id, shard_id, pool_tier, available_tokens, burst_capacity, refill_rate_per_sec, daily_quota_limit, daily_units_used, daily_window_date, last_refill_epoch_ms)
         VALUES (?1, ?2, ?3, ?4, ?5, ?5, ?6, ?7, 0.0, ?8, ?9)",
        params![
            tid,
            auth.user_id,
            shard_id,
            policy.tier,
            policy.burst_capacity,
            policy.refill_rate_per_sec,
            daily_action_quota,
            today_str,
            now_ms
        ],
    );

    let _ = conn.execute(
        "INSERT INTO agent_audit_logs
         (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, action_units, shard_id, pool_tier, throttle_mode, pacing_delay_ms, details)
         VALUES (?1, ?2, 'agent_token.create', 'agent_token', ?3, 'success', 0.0, 4.0, ?4, ?5, 'smooth', 0, ?6)",
        params![
            auth.user_id,
            tid,
            tid.to_string(),
            shard_id,
            policy.tier,
            json!({
                "name": name,
                "scopes": scopes,
                "pool_tier": policy.tier,
                "shard_id": shard_id,
                "daily_action_quota": daily_action_quota,
                "max_spend_per_tx": max_tx,
                "daily_spend_limit": daily_lim
            }).to_string()
        ],
    );

    let tokens = list_user_agent_tokens(&conn, auth.user_id)?;
    drop(conn);

    // Mirror agent token & bucket creation to PostgreSQL pool when active
    state.with_postgres(|pg| {
        let _ = pg.execute(
            "INSERT INTO agent_tokens
             (id, user_id, name, token_prefix, token_hash, scopes, max_spend_per_tx, daily_spend_limit,
              pool_tier, shard_id, daily_action_quota, hourly_burst_capacity, refill_rate_per_sec, is_active, expires_at)
             VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, 1, $14)
             ON CONFLICT (token_hash) DO NOTHING",
            &[
                &tid,
                &auth.user_id,
                &name,
                &prefix,
                &token_hash,
                &scopes_json,
                &max_tx,
                &daily_lim,
                &policy.tier,
                &shard_id,
                &daily_action_quota,
                &policy.burst_capacity,
                &policy.refill_rate_per_sec,
                &expires_at,
            ],
        );
        Ok(())
    });

    Ok((
        StatusCode::CREATED,
        Json(json!({
            "success": true,
            "message": "Autonomous MCP Agent Token generated and assigned to virtual shard & relay pool. Copy it now — it will not be shown in full again.",
            "agent_token": raw_secret,
            "token_id": tid,
            "shard_id": shard_id,
            "pool_tier": policy.tier,
            "daily_action_quota": daily_action_quota,
            "agent_tokens": tokens
        })),
    ))
}

pub async fn get_agent_tokens(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let tokens = list_user_agent_tokens(&conn, auth.user_id)?;
    let audit_logs = list_user_agent_audit_logs(&conn, auth.user_id, 100)?;
    let personas = list_user_agent_personas(&conn, Some(auth.user_id))?;
    let shard_telemetry = crate::sharding::build_shard_pool_telemetry(&state, &conn);
    Ok(Json(json!({
        "success": true,
        "agent_tokens": tokens,
        "agent_audit_logs": audit_logs,
        "agent_personas": personas,
        "shard_telemetry": shard_telemetry
    })))
}

pub async fn revoke_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(token_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() && auth.agent_token_id != Some(token_id) {
        auth.require_scope("admin")?;
    }
    let conn = state.lock_db();
    let updated = conn.execute(
        "UPDATE agent_tokens SET is_active = 0 WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![token_id, auth.user_id, auth.role_id],
    )?;
    if updated == 0 {
        return Err(AppError::NotFound("Agent token not found.".into()));
    }
    let _ = conn.execute(
        "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, details)
         VALUES (?1, ?2, 'agent_token.revoke', 'agent_token', ?3, 'success', 0.0, '{}')",
        params![auth.user_id, token_id, token_id.to_string()],
    );
    Ok(Json(json!({
        "success": true,
        "message": "Agent token revoked."
    })))
}

pub async fn delete_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(token_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() && auth.agent_token_id != Some(token_id) {
        auth.require_scope("admin")?;
    }
    let conn = state.lock_db();
    let deleted = conn.execute(
        "DELETE FROM agent_tokens WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![token_id, auth.user_id, auth.role_id],
    )?;
    if deleted == 0 {
        return Err(AppError::NotFound("Agent token not found.".into()));
    }
    Ok(Json(json!({
        "success": true,
        "message": "Agent token deleted."
    })))
}


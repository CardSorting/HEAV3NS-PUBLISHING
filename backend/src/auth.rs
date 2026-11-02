use axum::{
    async_trait,
    extract::{FromRef, FromRequestParts},
    http::{header, request::Parts, StatusCode},
    response::{IntoResponse, Response},
    Json,
};
use jsonwebtoken::{decode, encode, DecodingKey, EncodingKey, Header, Validation};
use rusqlite::params;
use serde::{Deserialize, Serialize};
use serde_json::json;

use crate::db::AppState;

const JWT_SECRET: &[u8] = b"justfans-super-secret-jwt-key-2026-rust-edition";

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct Claims {
    pub sub: i64,
    pub username: String,
    pub role_id: i64,
    pub exp: usize,
}

pub fn create_jwt(user_id: i64, username: &str, role_id: i64) -> Result<String, AppError> {
    let expiration = chrono::Utc::now()
        .checked_add_signed(chrono::Duration::days(30))
        .expect("valid timestamp")
        .timestamp() as usize;

    let claims = Claims {
        sub: user_id,
        username: username.to_string(),
        role_id,
        exp: expiration,
    };

    encode(
        &Header::default(),
        &claims,
        &EncodingKey::from_secret(JWT_SECRET),
    )
    .map_err(|_| AppError::Internal("Failed to generate token".into()))
}

pub fn decode_jwt(token: &str) -> Result<Claims, AppError> {
    decode::<Claims>(
        token,
        &DecodingKey::from_secret(JWT_SECRET),
        &Validation::default(),
    )
    .map(|data| data.claims)
    .map_err(|_| AppError::Unauthorized("Invalid or expired token".into()))
}

/// Deterministic cryptographic digest for `hp_mcp_*` agent tokens using HS256 signature
pub fn hash_agent_token(raw_token: &str) -> String {
    #[derive(Serialize)]
    struct TokenPayload<'a> {
        tok: &'a str,
    }
    let jwt = encode(
        &Header::default(),
        &TokenPayload { tok: raw_token },
        &EncodingKey::from_secret(JWT_SECRET),
    )
    .unwrap_or_default();
    jwt.split('.').nth(2).unwrap_or(&jwt).to_string()
}

#[derive(Debug, Clone)]
pub struct AuthUser {
    pub user_id: i64,
    pub username: String,
    pub role_id: i64,
    pub agent_token_id: Option<i64>,
    pub agent_scopes: Option<Vec<String>>,
    pub max_spend_per_tx: Option<f64>,
    pub daily_spend_limit: Option<f64>,
    pub shard_id: Option<i64>,
    pub pool_tier: Option<String>,
}

impl AuthUser {
    pub fn require_scope(&self, scope: &str) -> Result<(), AppError> {
        if let Some(ref scopes) = self.agent_scopes {
            if !scopes.iter().any(|s| s == scope || s == "*" || s == "admin") {
                return Err(AppError::Forbidden(format!(
                    "Agent token lacks required scope '{scope}'"
                )));
            }
        }
        Ok(())
    }
}

#[derive(Debug, Clone)]
pub struct OptionalAuthUser(pub Option<AuthUser>);

fn extract_token_from_parts(parts: &Parts) -> Option<String> {
    if let Some(mcp_header) = parts.headers.get("x-agent-token") {
        if let Ok(mcp_str) = mcp_header.to_str() {
            let trimmed = mcp_str.trim();
            if !trimmed.is_empty() {
                return Some(trimmed.to_string());
            }
        }
    }
    if let Some(auth_header) = parts.headers.get(header::AUTHORIZATION) {
        if let Ok(auth_str) = auth_header.to_str() {
            if let Some(token) = auth_str.strip_prefix("Bearer ") {
                if !token.trim().is_empty() {
                    return Some(token.trim().to_string());
                }
            }
        }
    }
    if let Some(cookie_header) = parts.headers.get(header::COOKIE) {
        if let Ok(cookie_str) = cookie_header.to_str() {
            for part in cookie_str.split(';') {
                let part = part.trim();
                if let Some(val) = part.strip_prefix("justfans_token=") {
                    if !val.trim().is_empty() {
                        return Some(val.trim().to_string());
                    }
                }
            }
        }
    }
    None
}

pub fn resolve_token(state: &AppState, token: &str) -> Result<AuthUser, AppError> {
    if token.starts_with("hp_mcp_") {
        let thash = hash_agent_token(token);
        let conn = state.lock_db();
        let row = conn
            .query_row(
                "SELECT t.id, t.user_id, u.username, u.role_id, t.scopes, t.max_spend_per_tx, t.daily_spend_limit, t.is_active, t.expires_at, t.shard_id, t.pool_tier
                 FROM agent_tokens t
                 JOIN users u ON t.user_id = u.id
                 WHERE t.token_hash = ?1",
                params![thash],
                |r| {
                    Ok((
                        r.get::<_, i64>(0)?,
                        r.get::<_, i64>(1)?,
                        r.get::<_, String>(2)?,
                        r.get::<_, i64>(3)?,
                        r.get::<_, String>(4)?,
                        r.get::<_, f64>(5)?,
                        r.get::<_, f64>(6)?,
                        r.get::<_, i64>(7)? != 0,
                        r.get::<_, Option<String>>(8)?,
                        r.get::<_, i64>(9).unwrap_or(0),
                        r.get::<_, String>(10).unwrap_or_else(|_| "central".into()),
                    ))
                },
            )
            .map_err(|_| AppError::Unauthorized("Invalid or revoked MCP agent token".into()))?;

        let (tid, uid, username, role_id, scopes_raw, max_tx, daily_lim, is_active, expires_at, shard_id, pool_tier) = row;
        if !is_active {
            return Err(AppError::Unauthorized("MCP agent token has been revoked".into()));
        }
        if let Some(exp) = expires_at {
            let expired: bool = conn
                .query_row("SELECT ?1 < datetime('now')", params![exp], |r| r.get(0))
                .unwrap_or(false);
            if expired {
                return Err(AppError::Unauthorized("MCP agent token has expired".into()));
            }
        }
        let _ = conn.execute(
            "UPDATE agent_tokens SET last_used_at = datetime('now') WHERE id = ?1",
            params![tid],
        );
        let scopes: Vec<String> = serde_json::from_str(&scopes_raw).unwrap_or_default();
        return Ok(AuthUser {
            user_id: uid,
            username,
            role_id,
            agent_token_id: Some(tid),
            agent_scopes: Some(scopes),
            max_spend_per_tx: Some(max_tx),
            daily_spend_limit: Some(daily_lim),
            shard_id: Some(shard_id),
            pool_tier: Some(pool_tier),
        });
    }

    let claims = decode_jwt(token)?;
    let shard_id = crate::sharding::compute_user_shard(claims.sub, &claims.username);
    Ok(AuthUser {
        user_id: claims.sub,
        username: claims.username,
        role_id: claims.role_id,
        agent_token_id: None,
        agent_scopes: None,
        max_spend_per_tx: None,
        daily_spend_limit: None,
        shard_id: Some(shard_id),
        pool_tier: Some("central".into()),
    })
}

#[async_trait]
impl<S> FromRequestParts<S> for AuthUser
where
    AppState: FromRef<S>,
    S: Send + Sync,
{
    type Rejection = AppError;

    async fn from_request_parts(parts: &mut Parts, state: &S) -> Result<Self, Self::Rejection> {
        let token = extract_token_from_parts(parts)
            .ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
        let app_state = AppState::from_ref(state);
        resolve_token(&app_state, &token)
    }
}

#[async_trait]
impl<S> FromRequestParts<S> for OptionalAuthUser
where
    AppState: FromRef<S>,
    S: Send + Sync,
{
    type Rejection = AppError;

    async fn from_request_parts(parts: &mut Parts, state: &S) -> Result<Self, Self::Rejection> {
        if let Some(token) = extract_token_from_parts(parts) {
            let app_state = AppState::from_ref(state);
            if let Ok(user) = resolve_token(&app_state, &token) {
                return Ok(OptionalAuthUser(Some(user)));
            }
        }
        Ok(OptionalAuthUser(None))
    }
}

#[derive(Debug)]
pub enum AppError {
    BadRequest(String),
    Unauthorized(String),
    Forbidden(String),
    NotFound(String),
    Conflict(String),
    Unprocessable(String),
    QuotaCooldownAdvisory(String, Box<crate::sharding::QuotaDecision>),
    Internal(String),
}

impl From<rusqlite::Error> for AppError {
    fn from(err: rusqlite::Error) -> Self {
        match err {
            rusqlite::Error::QueryReturnedNoRows => AppError::NotFound("Resource not found".into()),
            _ => AppError::Internal(format!("Database error: {err}")),
        }
    }
}

impl IntoResponse for AppError {
    fn into_response(self) -> Response {
        match self {
            AppError::QuotaCooldownAdvisory(message, decision) => {
                let retry_sec = ((decision.retry_after_ms as f64) / 1000.0).ceil().max(1.0) as i64;
                (
                    StatusCode::TOO_MANY_REQUESTS,
                    [
                        ("x-heav3ns-shard", decision.shard_id.to_string()),
                        ("x-heav3ns-pool", decision.pool_tier.clone()),
                        ("x-ratelimit-throttle-mode", decision.throttle_mode.clone()),
                        ("x-ratelimit-pacing-ms", decision.pacing_delay_ms.to_string()),
                        ("retry-after", retry_sec.to_string()),
                    ],
                    Json(json!({
                        "success": false,
                        "throttled": true,
                        "status": "cooldown_advisory",
                        "message": message,
                        "error": message,
                        "retry_after_ms": decision.retry_after_ms,
                        "_quota_governance": decision,
                        "_heav3ns_governance": decision,
                    })),
                )
                    .into_response()
            }
            other => {
                let (status, message) = match other {
                    AppError::BadRequest(msg) => (StatusCode::BAD_REQUEST, msg),
                    AppError::Unauthorized(msg) => (StatusCode::UNAUTHORIZED, msg),
                    AppError::Forbidden(msg) => (StatusCode::FORBIDDEN, msg),
                    AppError::NotFound(msg) => (StatusCode::NOT_FOUND, msg),
                    AppError::Conflict(msg) => (StatusCode::CONFLICT, msg),
                    AppError::Unprocessable(msg) => (StatusCode::UNPROCESSABLE_ENTITY, msg),
                    AppError::Internal(msg) => (StatusCode::INTERNAL_SERVER_ERROR, msg),
                    AppError::QuotaCooldownAdvisory(_, _) => unreachable!(),
                };

                (
                    status,
                    Json(json!({
                        "success": false,
                        "message": message,
                        "error": message,
                    })),
                )
                    .into_response()
            }
        }
    }
}



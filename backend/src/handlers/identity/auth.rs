use axum::{
    extract::State,
    http::{header, StatusCode},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{create_jwt, AppError, AuthUser},
    db::{get_user_public_profile, AppState},
};

#[derive(Deserialize)]
pub struct RegisterPayload {
    pub name: String,
    pub username: Option<String>,
    pub email: String,
    pub password: String,
    pub referral_code: Option<String>,
}

pub fn normalize_db_datetime(input: Option<String>) -> Option<String> {
    let raw = input?.trim().replace('T', " ");
    if raw.is_empty() {
        return None;
    }
    if raw.len() == 10 {
        Some(format!("{raw} 00:00:00"))
    } else if raw.len() == 16 {
        Some(format!("{raw}:00"))
    } else {
        Some(raw)
    }
}

pub async fn register(
    State(state): State<AppState>,
    Json(payload): Json<RegisterPayload>,
) -> Result<impl IntoResponse, AppError> {
    let name = payload.name.trim();
    let email = payload.email.trim().to_lowercase();
    let password = payload.password.trim();

    if name.is_empty() || email.is_empty() || password.len() < 6 {
        return Err(AppError::Unprocessable(
            "Name, valid email, and password (min 6 chars) are required.".into(),
        ));
    }

    let raw_username = payload
        .username
        .unwrap_or_else(|| {
            email
                .split('@')
                .next()
                .unwrap_or("user")
                .chars()
                .filter(|c| c.is_alphanumeric() || *c == '_')
                .collect()
        })
        .trim()
        .to_lowercase();

    let username = if raw_username.is_empty() {
        format!("user_{}", Uuid::new_v4().simple().to_string()[..6].to_lowercase())
    } else {
        raw_username
    };

    let mut conn = state.lock_db();

    let exists: i64 = conn.query_row(
        "SELECT COUNT(*) FROM users WHERE email = ?1 OR username = ?2",
        params![email, username],
        |r| r.get(0),
    )?;
    if exists > 0 {
        return Err(AppError::Unprocessable(
            "An account with that email or username already exists.".into(),
        ));
    }

    let password_hash = bcrypt::hash(password, 4)
        .map_err(|_| AppError::Internal("Password hashing error".into()))?;
    let referral_code = format!(
        "{}{}",
        username.chars().take(4).collect::<String>().to_uppercase(),
        &Uuid::new_v4().simple().to_string()[..4].to_uppercase()
    );

    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
    tx.execute(
        "INSERT INTO users (name, username, email, password_hash, role_id, email_verified_at, referral_code)
         VALUES (?1, ?2, ?3, ?4, 2, datetime('now'), ?5)",
        params![name, username, email, password_hash, referral_code],
    )?;
    let user_id = tx.last_insert_rowid();

    tx.execute(
        "INSERT INTO wallets (user_id, total) VALUES (?1, 100.0)",
        params![user_id],
    )?;

    if let Some(ref_code) = payload.referral_code.as_deref() {
        let clean_ref = ref_code.trim();
        if !clean_ref.is_empty() {
            if let Ok(referrer_id) = tx.query_row::<i64, _, _>(
                "SELECT id FROM users WHERE UPPER(referral_code) = UPPER(?1) AND id != ?2",
                params![clean_ref, user_id],
                |r| r.get(0),
            ) {
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + 5.0, 2), updated_at = datetime('now') WHERE user_id = ?1",
                    params![referrer_id],
                )?;
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + 5.0, 2), updated_at = datetime('now') WHERE user_id = ?1",
                    params![user_id],
                )?;
                let _ = tx.execute(
                    "INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, bonus_amount)
                     VALUES (?1, ?2, ?3, 5.0)",
                    params![referrer_id, user_id, clean_ref.to_uppercase()],
                );
                let _ = tx.execute(
                    "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
                     VALUES (?1, ?2, ?3, 'tip', ?4)",
                    params![
                        Uuid::new_v4().to_string(),
                        user_id,
                        referrer_id,
                        format!("@{} joined using your referral link! You earned a $5.00 bonus.", username)
                    ],
                );
            }
        }
    }

    for (lname, ltype) in [
        ("Followers", "followers"),
        ("Following", "following"),
        ("Blocked", "blocked"),
    ] {
        tx.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, ?2, ?3)",
            params![user_id, lname, ltype],
        )?;
    }
    tx.commit()?;

    let token = create_jwt(user_id, &username, 2)?;
    let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;

    let cookie = format!(
        "justfans_token={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=2592000"
    );

    Ok((
        StatusCode::CREATED,
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "token": token,
            "user": profile
        })),
    ))
}

#[derive(Deserialize)]
pub struct LoginPayload {
    pub email: String,
    pub password: String,
}

pub async fn login(
    State(state): State<AppState>,
    Json(payload): Json<LoginPayload>,
) -> Result<impl IntoResponse, AppError> {
    let identifier = payload.email.trim();
    let password = payload.password.trim();

    if identifier.is_empty() || password.is_empty() {
        return Err(AppError::BadRequest("Email/username and password are required.".into()));
    }

    let conn = state.lock_db();

    let user_row: Result<(i64, String, String, i64), rusqlite::Error> = conn.query_row(
        "SELECT id, username, password_hash, role_id FROM users WHERE email = ?1 OR username = ?1",
        params![identifier],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
    );

    let (user_id, username, password_hash, role_id) = match user_row {
        Ok(row) => row,
        Err(_) => {
            return Err(AppError::Unauthorized(
                "These credentials do not match our records.".into(),
            ))
        }
    };

    let valid = bcrypt::verify(password, &password_hash).unwrap_or(false);
    if !valid {
        return Err(AppError::Unauthorized(
            "These credentials do not match our records.".into(),
        ));
    }

    let token = create_jwt(user_id, &username, role_id)?;
    let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;

    let cookie = format!(
        "justfans_token={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=2592000"
    );

    Ok((
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "token": token,
            "user": profile
        })),
    ))
}

pub async fn logout() -> impl IntoResponse {
    let cookie = "justfans_token=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0";
    (
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "message": "Logged out"
        })),
    )
}

pub async fn get_me(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    let unread_notifications: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM notifications WHERE to_user_id = ?1 AND is_read = 0",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let unread_messages: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM user_messages WHERE receiver_id = ?1 AND is_seen = 0",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let active_streams_count: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM streams WHERE status = 'in-progress' AND is_public = 1",
            [],
            |r| r.get(0),
        )
        .unwrap_or(0);

    Ok(Json(json!({
        "success": true,
        "user": profile,
        "unread_notifications": unread_notifications,
        "unread_messages": unread_messages,
        "active_streams_count": active_streams_count
    })))
}


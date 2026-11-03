use axum::{
    extract::State,
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};

use crate::{
    auth::{AppError, AuthUser},
    db::{get_user_public_profile, round_money, AppState},
    handlers::governance::{list_user_agent_audit_logs, list_user_agent_tokens},
    handlers::identity::normalize_db_datetime,
    models::*,
};

pub async fn get_settings_data(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    // Active & past subscriptions
    let mut sub_stmt = conn.prepare(
        "SELECT s.id, s.sender_user_id, s.recipient_user_id,
                c.name, c.username, c.avatar,
                f.name, f.username, f.avatar,
                s.amount, s.status, s.expires_at, s.created_at
         FROM subscriptions s
         JOIN users c ON s.recipient_user_id = c.id
         JOIN users f ON s.sender_user_id = f.id
         WHERE s.sender_user_id = ?1 OR s.recipient_user_id = ?1
         ORDER BY s.id DESC",
    )?;
    let subscriptions: Vec<SubscriptionView> = sub_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(SubscriptionView {
                id: r.get(0)?,
                sender_user_id: r.get(1)?,
                recipient_user_id: r.get(2)?,
                creator_name: r.get(3)?,
                creator_username: r.get(4)?,
                creator_avatar: r.get(5)?,
                subscriber_name: r.get(6)?,
                subscriber_username: r.get(7)?,
                subscriber_avatar: r.get(8)?,
                amount: r.get(9)?,
                status: r.get(10)?,
                expires_at: r.get(11)?,
                created_at: r.get(12)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Transactions
    let mut tx_stmt = conn.prepare(
        "SELECT t.id, t.sender_user_id, t.recipient_user_id, u1.name, u2.name,
                t.tx_type, t.status, t.amount, t.currency, t.payment_provider, t.post_id, t.invoice_id, t.created_at
         FROM transactions t
         JOIN users u1 ON t.sender_user_id = u1.id
         LEFT JOIN users u2 ON t.recipient_user_id = u2.id
         WHERE t.sender_user_id = ?1 OR t.recipient_user_id = ?1
         ORDER BY t.id DESC LIMIT 50",
    )?;
    let transactions: Vec<TransactionView> = tx_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(TransactionView {
                id: r.get(0)?,
                sender_user_id: r.get(1)?,
                recipient_user_id: r.get(2)?,
                sender_name: r.get(3)?,
                recipient_name: r.get(4)?,
                tx_type: r.get(5)?,
                status: r.get(6)?,
                amount: r.get(7)?,
                currency: r.get(8)?,
                payment_provider: r.get(9)?,
                post_id: r.get(10)?,
                invoice_id: r.get(11)?,
                created_at: r.get(12)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Withdrawals
    let mut w_stmt = conn.prepare(
        "SELECT w.id, w.user_id, u.name, u.username, w.amount, w.status, w.payment_method, w.payment_identifier, w.message, w.created_at
         FROM withdrawals w JOIN users u ON w.user_id = u.id
         WHERE w.user_id = ?1 ORDER BY w.id DESC",
    )?;
    let withdrawals: Vec<WithdrawalView> = w_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(WithdrawalView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                amount: r.get(4)?,
                status: r.get(5)?,
                payment_method: r.get(6)?,
                payment_identifier: r.get(7)?,
                message: r.get(8)?,
                created_at: r.get(9)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    let verification: Option<Value> = conn
        .query_row(
            "SELECT id, status, files, rejection_reason, created_at FROM user_verifies WHERE user_id = ?1",
            params![auth.user_id],
            |r| {
                let files_raw: String = r.get(2)?;
                let files_parsed: Value = serde_json::from_str(&files_raw).unwrap_or_else(|_| json!([]));
                Ok(json!({
                    "id": r.get::<_, i64>(0)?,
                    "status": r.get::<_, String>(1)?,
                    "files": files_parsed,
                    "rejection_reason": r.get::<_, Option<String>>(3)?,
                    "created_at": r.get::<_, String>(4)?,
                }))
            },
        )
        .ok();

    let billing: Option<Value> = conn
        .query_row(
            "SELECT first_name, last_name, billing_address, city, country, state, postcode FROM users WHERE id = ?1",
            params![auth.user_id],
            |r| {
                Ok(json!({
                    "first_name": r.get::<_, Option<String>>(0)?.unwrap_or_default(),
                    "last_name": r.get::<_, Option<String>>(1)?.unwrap_or_default(),
                    "billing_address": r.get::<_, Option<String>>(2)?.unwrap_or_default(),
                    "city": r.get::<_, Option<String>>(3)?.unwrap_or_default(),
                    "country": r.get::<_, Option<String>>(4)?.unwrap_or_default(),
                    "state": r.get::<_, Option<String>>(5)?.unwrap_or_default(),
                    "postcode": r.get::<_, Option<String>>(6)?.unwrap_or_default(),
                }))
            },
        )
        .ok();

    let mut ref_stmt = conn.prepare(
        "SELECT r.id, r.referred_user_id, u.name, u.username, u.avatar, r.referral_code, r.bonus_amount, r.created_at
         FROM referrals r
         JOIN users u ON r.referred_user_id = u.id
         WHERE r.referrer_user_id = ?1
         ORDER BY r.id DESC",
    )?;
    let referrals: Vec<Value> = ref_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "used_by_user_id": r.get::<_, i64>(1)?,
                "used_by_name": r.get::<_, String>(2)?,
                "used_by_username": r.get::<_, String>(3)?,
                "used_by_avatar": r.get::<_, String>(4)?,
                "referral_code": r.get::<_, String>(5)?,
                "bonus_amount": r.get::<_, f64>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    let agent_tokens = list_user_agent_tokens(&conn, auth.user_id)?;
    let agent_audit_logs = list_user_agent_audit_logs(&conn, auth.user_id, 50)?;

    Ok(Json(json!({
        "success": true,
        "profile": profile,
        "subscriptions": subscriptions,
        "transactions": transactions,
        "withdrawals": withdrawals,
        "verification": verification,
        "billing": billing,
        "referrals": referrals,
        "agent_tokens": agent_tokens,
        "agent_audit_logs": agent_audit_logs
    })))
}

#[derive(Deserialize)]
pub struct SubmitVerifyPayload {
    pub files: Vec<String>,
}

pub async fn submit_verification_request(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SubmitVerifyPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "profile_update").await?;
    let res: Result<Value, AppError> = (|| {
        let clean_files: Vec<String> = payload
            .files
            .into_iter()
            .map(|f| f.trim().to_string())
            .filter(|f| !f.is_empty())
            .collect();
        if clean_files.is_empty() {
            return Err(AppError::Unprocessable(
                "Please upload or provide at least one ID document file.".into(),
            ));
        }
        let files_json = serde_json::to_string(&clean_files).unwrap_or_else(|_| "[]".into());
        let conn = state.lock_db();
        conn.execute(
            "INSERT INTO user_verifies (user_id, status, files, rejection_reason)
             VALUES (?1, 'pending', ?2, NULL)
             ON CONFLICT(user_id) DO UPDATE SET status = 'pending', files = excluded.files, rejection_reason = NULL",
            params![auth.user_id, files_json],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user_verify",
            Some(auth.user_id.to_string()),
            0.0,
            json!({ "action": "submit_verification_request", "files_count": clean_files.len() }),
        );

        Ok(json!({
            "success": true,
            "message": "Identity verification documents submitted for review."
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct UpdateProfilePayload {
    pub name: String,
    pub username: String,
    pub bio: Option<String>,
    pub location: Option<String>,
    pub website: Option<String>,
    pub birthdate: Option<String>,
    pub gender_pronoun: Option<String>,
    pub avatar: Option<String>,
    pub cover: Option<String>,
}

pub async fn update_profile_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateProfilePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "profile_update").await?;
    let res: Result<Value, AppError> = (|| {
        let name = payload.name.trim();
        let username = payload
            .username
            .trim()
            .chars()
            .filter(|c| c.is_alphanumeric() || *c == '_' || *c == '-')
            .collect::<String>()
            .to_lowercase();

        if name.is_empty() || username.is_empty() {
            return Err(AppError::Unprocessable("Name and valid username are required.".into()));
        }

        let conn = state.lock_db();
        let existing: i64 = conn.query_row(
            "SELECT COUNT(*) FROM users WHERE username = ?1 AND id != ?2",
            params![username, auth.user_id],
            |r| r.get(0),
        )?;
        if existing > 0 {
            return Err(AppError::Unprocessable("Username is already taken by another user.".into()));
        }

        conn.execute(
            "UPDATE users SET
                name = ?1,
                username = ?2,
                bio = ?3,
                location = ?4,
                website = ?5,
                birthdate = ?6,
                gender_pronoun = ?7,
                avatar = COALESCE(?8, avatar),
                cover = COALESCE(?9, cover)
             WHERE id = ?10",
            params![
                name,
                username,
                payload.bio.unwrap_or_default().trim(),
                payload.location.unwrap_or_default().trim(),
                payload.website.unwrap_or_default().trim(),
                payload.birthdate,
                payload.gender_pronoun,
                payload.avatar,
                payload.cover,
                auth.user_id
            ],
        )?;

        let updated = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user",
            Some(auth.user_id.to_string()),
            0.0,
            json!({ "action": "update_profile_settings", "username": username }),
        );
        Ok(json!({
            "success": true,
            "message": "Profile settings saved.",
            "user": updated
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct UpdateRatesPayload {
    pub paid_profile: bool,
    pub open_profile: bool,
    pub profile_access_price: f64,
    pub profile_access_price_3_months: f64,
    pub profile_access_price_6_months: f64,
    pub profile_access_price_12_months: f64,
    pub offer_discount_percent: Option<i64>,
    pub offer_expires_at: Option<String>,
}

pub async fn update_rates_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateRatesPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("monetize")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "rates_and_offers_update").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let p1 = round_money(payload.profile_access_price.max(0.0));
        let p3 = round_money(payload.profile_access_price_3_months.max(0.0));
        let p6 = round_money(payload.profile_access_price_6_months.max(0.0));
        let p12 = round_money(payload.profile_access_price_12_months.max(0.0));

        let (old1, old3, old6, old12): (f64, f64, f64, f64) = conn
            .query_row(
                "SELECT profile_access_price, profile_access_price_3_months, profile_access_price_6_months, profile_access_price_12_months
                 FROM users WHERE id = ?1",
                params![auth.user_id],
                |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
            )
            .unwrap_or((p1, p3, p6, p12));

        conn.execute(
            "UPDATE users SET
                paid_profile = ?1,
                open_profile = ?2,
                profile_access_price = ?3,
                profile_access_price_3_months = ?4,
                profile_access_price_6_months = ?5,
                profile_access_price_12_months = ?6
             WHERE id = ?7",
            params![
                if payload.paid_profile { 1 } else { 0 },
                if payload.open_profile { 1 } else { 0 },
                p1,
                p3,
                p6,
                p12,
                auth.user_id
            ],
        )?;

        if let Some(exp) = normalize_db_datetime(payload.offer_expires_at) {
            let disc = payload.offer_discount_percent.unwrap_or(20).clamp(5, 90);
            conn.execute(
                "INSERT INTO creator_offers (user_id, old_profile_access_price, old_profile_access_price_3_months, old_profile_access_price_6_months, old_profile_access_price_12_months, discount_percent, expires_at)
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7)
                 ON CONFLICT(user_id) DO UPDATE SET
                    old_profile_access_price = excluded.old_profile_access_price,
                    old_profile_access_price_3_months = excluded.old_profile_access_price_3_months,
                    old_profile_access_price_6_months = excluded.old_profile_access_price_6_months,
                    old_profile_access_price_12_months = excluded.old_profile_access_price_12_months,
                    discount_percent = excluded.discount_percent,
                    expires_at = excluded.expires_at",
                params![auth.user_id, old1.max(p1), old3.max(p3), old6.max(p6), old12.max(p12), disc, exp],
            )?;
        }

        let updated = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user_rates",
            Some(auth.user_id.to_string()),
            0.0,
            json!({ "action": "update_rates_settings", "price_1m": p1, "price_3m": p3 }),
        );
        Ok(json!({
            "success": true,
            "message": "Subscription rates updated.",
            "user": updated
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct UpdateAccountPayload {
    pub email: Option<String>,
    pub new_password: Option<String>,
    pub first_name: Option<String>,
    pub last_name: Option<String>,
    pub billing_address: Option<String>,
    pub city: Option<String>,
    pub country: Option<String>,
    pub state: Option<String>,
    pub postcode: Option<String>,
}

pub async fn update_account_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateAccountPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "profile_update").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        if let Some(email) = payload.email {
            let clean_email = email.trim().to_lowercase();
            if !clean_email.is_empty() {
                let exists: i64 = conn.query_row(
                    "SELECT COUNT(*) FROM users WHERE email = ?1 AND id != ?2",
                    params![clean_email, auth.user_id],
                    |r| r.get(0),
                )?;
                if exists > 0 {
                    return Err(AppError::Unprocessable("Email is already registered to another account.".into()));
                }
                conn.execute(
                    "UPDATE users SET email = ?1 WHERE id = ?2",
                    params![clean_email, auth.user_id],
                )?;
            }
        }
        if let Some(pw) = payload.new_password {
            if !pw.trim().is_empty() {
                if pw.trim().len() < 6 {
                    return Err(AppError::Unprocessable("New password must be at least 6 characters.".into()));
                }
                let hash = bcrypt::hash(pw.trim(), 4)
                    .map_err(|_| AppError::Internal("Hash error".into()))?;
                conn.execute(
                    "UPDATE users SET password_hash = ?1 WHERE id = ?2",
                    params![hash, auth.user_id],
                )?;
            }
        }
        if payload.first_name.is_some()
            || payload.last_name.is_some()
            || payload.billing_address.is_some()
            || payload.city.is_some()
            || payload.country.is_some()
            || payload.state.is_some()
            || payload.postcode.is_some()
        {
            conn.execute(
                "UPDATE users SET
                    first_name = COALESCE(?1, first_name),
                    last_name = COALESCE(?2, last_name),
                    billing_address = COALESCE(?3, billing_address),
                    city = COALESCE(?4, city),
                    country = COALESCE(?5, country),
                    state = COALESCE(?6, state),
                    postcode = COALESCE(?7, postcode)
                 WHERE id = ?8",
                params![
                    payload.first_name.map(|s| s.trim().to_string()),
                    payload.last_name.map(|s| s.trim().to_string()),
                    payload.billing_address.map(|s| s.trim().to_string()),
                    payload.city.map(|s| s.trim().to_string()),
                    payload.country.map(|s| s.trim().to_string()),
                    payload.state.map(|s| s.trim().to_string()),
                    payload.postcode.map(|s| s.trim().to_string()),
                    auth.user_id
                ],
            )?;
        }
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user_account",
            Some(auth.user_id.to_string()),
            0.0,
            json!({ "action": "update_account_settings" }),
        );
        Ok(json!({
            "success": true,
            "message": "Account security & billing settings updated."
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct UpdatePrivacyPayload {
    pub public_profile: bool,
    pub enable_2fa: bool,
    pub enable_geoblocking: bool,
}

pub async fn update_privacy_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdatePrivacyPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "privacy_update").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        conn.execute(
            "UPDATE users SET public_profile = ?1, enable_2fa = ?2, enable_geoblocking = ?3 WHERE id = ?4",
            params![
                if payload.public_profile { 1 } else { 0 },
                if payload.enable_2fa { 1 } else { 0 },
                if payload.enable_geoblocking { 1 } else { 0 },
                auth.user_id
            ],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user_privacy",
            Some(auth.user_id.to_string()),
            0.0,
            json!({ "action": "update_privacy_settings", "public_profile": payload.public_profile }),
        );
        Ok(json!({
            "success": true,
            "message": "Privacy preferences saved."
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


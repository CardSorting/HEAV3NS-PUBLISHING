use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};

use crate::{
    auth::{AppError, AuthUser},
    db::{get_user_public_profile, round_money, AppState},
    models::*,
};

pub async fn get_public_page(
    State(state): State<AppState>,
    Path(slug): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let page = conn.query_row(
        "SELECT id, slug, title, short_title, content, updated_at FROM public_pages WHERE slug = ?1",
        params![slug],
        |r| {
            Ok(PublicPageView {
                id: r.get(0)?,
                slug: r.get(1)?,
                title: r.get(2)?,
                short_title: r.get(3)?,
                content: r.get(4)?,
                updated_at: r.get(5)?,
            })
        },
    )?;

    Ok(Json(json!({
        "success": true,
        "page": page
    })))
}

#[derive(Deserialize)]
pub struct ContactPayload {
    pub email: String,
    pub subject: String,
    pub message: String,
}

pub async fn send_contact_message(
    State(state): State<AppState>,
    Json(payload): Json<ContactPayload>,
) -> Result<impl IntoResponse, AppError> {
    if payload.email.trim().is_empty() || payload.message.trim().is_empty() {
        return Err(AppError::Unprocessable("Email and message are required.".into()));
    }
    let conn = state.lock_db();
    conn.execute(
        "INSERT INTO contact_messages (email, subject, message) VALUES (?1, ?2, ?3)",
        params![
            payload.email.trim(),
            payload.subject.trim(),
            payload.message.trim()
        ],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Your message has been sent! Our support team will get back to you shortly."
    })))
}

#[derive(Deserialize)]
pub struct ReportPayload {
    pub reported_user_id: i64,
    pub post_id: Option<i64>,
    pub report_type: String,
    pub details: Option<String>,
}

pub async fn submit_report(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<ReportPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let conn = state.lock_db();
    conn.execute(
        "INSERT INTO user_reports (from_user_id, reported_user_id, post_id, report_type, details)
         VALUES (?1, ?2, ?3, ?4, ?5)",
        params![
            auth.user_id,
            payload.reported_user_id,
            payload.post_id,
            payload.report_type,
            payload.details.unwrap_or_default()
        ],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Report submitted to moderation team."
    })))
}

#[derive(Deserialize)]
pub struct AiSuggestionPayload {
    pub topic: Option<String>,
}

pub async fn generate_ai_suggestion(
    auth: AuthUser,
    Json(payload): Json<AiSuggestionPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let topic = payload
        .topic
        .unwrap_or_else(|| "exclusive creator update".into());
    let suggestion = format!(
        "✨ Just dropped a brand new {topic} set! Unlock or subscribe now for full HD behind-the-scenes access and let me know your favorite shot in the comments 💬🔥 #exclusive #creator"
    );
    Ok(Json(json!({
        "success": true,
        "suggestion": suggestion
    })))
}

pub async fn get_admin_dashboard(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();

    let total_users: i64 = conn.query_row("SELECT COUNT(*) FROM users", [], |r| r.get(0))?;
    let total_posts: i64 = conn.query_row("SELECT COUNT(*) FROM posts", [], |r| r.get(0))?;
    let total_subscriptions: i64 =
        conn.query_row("SELECT COUNT(*) FROM subscriptions", [], |r| r.get(0))?;
    let total_revenue: f64 = conn.query_row(
        "SELECT COALESCE(SUM(amount), 0.0) FROM transactions WHERE status = 'approved'",
        [],
        |r| r.get(0),
    )?;

    let mut u_stmt = conn.prepare("SELECT id FROM users ORDER BY id ASC")?;
    let uids: Vec<i64> = u_stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    let mut users = Vec::new();
    for uid in uids {
        if let Ok(u) = get_user_public_profile(&conn, uid, Some(auth.user_id)) {
            users.push(u);
        }
    }

    let mut w_stmt = conn.prepare(
        "SELECT w.id, w.user_id, u.name, u.username, w.amount, w.status, w.payment_method, w.payment_identifier, w.message, w.created_at
         FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.id DESC",
    )?;
    let withdrawals: Vec<WithdrawalView> = w_stmt
        .query_map([], |r| {
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

    let mut r_stmt = conn.prepare(
        "SELECT r.id, u1.username, u2.username, r.post_id, r.report_type, r.details, r.status, r.created_at
         FROM user_reports r
         JOIN users u1 ON r.from_user_id = u1.id
         JOIN users u2 ON r.reported_user_id = u2.id
         ORDER BY r.id DESC",
    )?;
    let reports: Vec<Value> = r_stmt
        .query_map([], |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "reporter": r.get::<_, String>(1)?,
                "reported": r.get::<_, String>(2)?,
                "post_id": r.get::<_, Option<i64>>(3)?,
                "report_type": r.get::<_, String>(4)?,
                "details": r.get::<_, String>(5)?,
                "status": r.get::<_, String>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    let mut v_stmt = conn.prepare(
        "SELECT v.id, v.user_id, u.name, u.username, v.status, v.files, v.rejection_reason, v.created_at
         FROM user_verifies v
         JOIN users u ON v.user_id = u.id
         ORDER BY v.id DESC",
    )?;
    let verifications: Vec<Value> = v_stmt
        .query_map([], |r| {
            let files_raw: String = r.get(5)?;
            let files_parsed: Value = serde_json::from_str(&files_raw).unwrap_or_else(|_| json!([]));
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "user_id": r.get::<_, i64>(1)?,
                "user_name": r.get::<_, String>(2)?,
                "user_username": r.get::<_, String>(3)?,
                "status": r.get::<_, String>(4)?,
                "files": files_parsed,
                "rejection_reason": r.get::<_, Option<String>>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "metrics": {
            "total_users": total_users,
            "total_posts": total_posts,
            "total_subscriptions": total_subscriptions,
            "total_revenue": round_money(total_revenue),
        },
        "users": users,
        "withdrawals": withdrawals,
        "reports": reports,
        "verifications": verifications
    })))
}

#[derive(Deserialize)]
pub struct AdminWithdrawalAction {
    pub action: String, // "approve" or "reject"
}

pub async fn handle_admin_withdrawal(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(withdrawal_id): Path<i64>,
    Json(payload): Json<AdminWithdrawalAction>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let mut conn = state.lock_db();
    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
    let (uid, amount, current_status): (i64, f64, String) = tx.query_row(
        "SELECT user_id, amount, status FROM withdrawals WHERE id = ?1",
        params![withdrawal_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
    )?;

    if current_status != "requested" {
        return Err(AppError::BadRequest("Withdrawal already processed.".into()));
    }

    if payload.action == "approve" {
        tx.execute(
            "UPDATE withdrawals SET status = 'approved' WHERE id = ?1",
            params![withdrawal_id],
        )?;
    } else {
        tx.execute(
            "UPDATE withdrawals SET status = 'rejected' WHERE id = ?1",
            params![withdrawal_id],
        )?;
        // Refund wallet
        tx.execute(
            "UPDATE wallets SET total = ROUND(total + ?1, 2) WHERE user_id = ?2",
            params![round_money(amount), uid],
        )?;
    }
    tx.commit()?;

    Ok(Json(json!({
        "success": true,
        "status": if payload.action == "approve" { "approved" } else { "rejected" }
    })))
}

#[derive(Deserialize)]
pub struct AdminReportAction {
    pub action: String, // "resolve" or "dismiss"
}

pub async fn handle_admin_report(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(report_id): Path<i64>,
    Json(payload): Json<AdminReportAction>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();
    let new_status = if payload.action == "dismiss" {
        "dismissed"
    } else {
        "resolved"
    };
    conn.execute(
        "UPDATE user_reports SET status = ?1 WHERE id = ?2",
        params![new_status, report_id],
    )?;

    Ok(Json(json!({
        "success": true,
        "status": new_status
    })))
}

#[derive(Deserialize)]
pub struct AdminVerifyAction {
    pub action: String, // "approve" or "reject"
    pub rejection_reason: Option<String>,
}

pub async fn handle_admin_verification(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(verify_id): Path<i64>,
    Json(payload): Json<AdminVerifyAction>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();
    let uid: i64 = conn.query_row(
        "SELECT user_id FROM user_verifies WHERE id = ?1",
        params![verify_id],
        |r| r.get(0),
    )?;

    if payload.action == "approve" {
        conn.execute(
            "UPDATE user_verifies SET status = 'verified', rejection_reason = NULL WHERE id = ?1",
            params![verify_id],
        )?;
        conn.execute(
            "UPDATE users SET identity_verified_at = datetime('now') WHERE id = ?1",
            params![uid],
        )?;
    } else {
        conn.execute(
            "UPDATE user_verifies SET status = 'rejected', rejection_reason = ?1 WHERE id = ?2",
            params![payload.rejection_reason, verify_id],
        )?;
        conn.execute(
            "UPDATE users SET identity_verified_at = NULL WHERE id = ?1",
            params![uid],
        )?;
    }

    Ok(Json(json!({
        "success": true,
        "status": if payload.action == "approve" { "verified" } else { "rejected" }
    })))
}


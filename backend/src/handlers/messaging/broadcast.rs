use axum::{
    extract::State,
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::{is_blocked_between, round_money, AppState},
};

#[derive(Deserialize)]
pub struct BroadcastMessagePayload {
    pub list_id: Option<i64>,
    pub recipient_ids: Option<Vec<i64>>,
    #[serde(alias = "audience")]
    pub target: Option<String>, // "subscribers", "followers", "all", "list"
    pub message: String,
    pub price: Option<f64>,
    pub attachments: Option<Vec<String>>,
}

pub async fn broadcast_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<BroadcastMessagePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let msg = payload.message.trim();
    let attachment_inputs = payload.attachments.clone().unwrap_or_default();
    if msg.is_empty() && attachment_inputs.is_empty() {
        return Err(AppError::Unprocessable(
            "Broadcast message must contain text or at least one attachment.".into(),
        ));
    }
    let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
    let conn = state.lock_db();

    let mut targets: Vec<i64> = Vec::new();
    if let Some(ref ids) = payload.recipient_ids {
        for &id in ids {
            if id != auth.user_id && !targets.contains(&id) {
                targets.push(id);
            }
        }
    }
    if let Some(lid) = payload.list_id {
        let mut stmt = conn.prepare(
            "SELECT ulm.user_id FROM user_list_members ulm
             JOIN user_lists ul ON ulm.list_id = ul.id
             WHERE ul.id = ?1 AND ul.user_id = ?2",
        )?;
        let rows: Vec<i64> = stmt
            .query_map(params![lid, auth.user_id], |r| r.get(0))?
            .filter_map(Result::ok)
            .collect();
        for id in rows {
            if id != auth.user_id && !targets.contains(&id) {
                targets.push(id);
            }
        }
    }
    if targets.is_empty() {
        let target_mode = payload.target.as_deref().unwrap_or("subscribers");
        if target_mode == "followers" || target_mode == "all" {
            let mut stmt = conn.prepare(
                "SELECT ulm.user_id FROM user_list_members ulm
                 JOIN user_lists ul ON ulm.list_id = ul.id
                 WHERE ul.user_id = ?1 AND ul.list_type = 'followers'",
            )?;
            let rows: Vec<i64> = stmt
                .query_map(params![auth.user_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            for id in rows {
                if id != auth.user_id && !targets.contains(&id) {
                    targets.push(id);
                }
            }
        }
        if target_mode != "followers" {
            let mut stmt = conn.prepare(
                "SELECT DISTINCT sender_user_id FROM subscriptions
                 WHERE recipient_user_id = ?1 AND status = 'completed' AND expires_at >= datetime('now')",
            )?;
            let rows: Vec<i64> = stmt
                .query_map(params![auth.user_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            for id in rows {
                if id != auth.user_id && !targets.contains(&id) {
                    targets.push(id);
                }
            }
        }
    }

    let final_msg = if msg.is_empty() {
        "📎 Sent broadcast media attachment".to_string()
    } else {
        msg.to_string()
    };

    let mut sent_ids = Vec::new();
    for receiver_id in targets {
        if is_blocked_between(&conn, auth.user_id, receiver_id) {
            continue;
        }
        conn.execute(
            "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
            params![auth.user_id, receiver_id, final_msg, price],
        )?;
        let mid = conn.last_insert_rowid();
        for item in &attachment_inputs {
            let new_att_id = Uuid::new_v4().to_string();
            let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                "video"
            } else {
                "image"
            };
            let _ = conn.execute(
                "INSERT INTO attachments (id, user_id, message_id, filename, file_url, file_type, file_size)
                 VALUES (?1, ?2, ?3, 'broadcast-media', ?4, ?5, 1024)",
                params![new_att_id, auth.user_id, mid, item, ftype],
            );
        }
        let _ = conn.execute(
            "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
             VALUES (?1, ?2, ?3, 'message', ?4)",
            params![
                Uuid::new_v4().to_string(),
                auth.user_id,
                receiver_id,
                format!("@{} sent a broadcast message", auth.username)
            ],
        );
        sent_ids.push(mid);
    }

    Ok(Json(json!({
        "success": true,
        "sent_count": sent_ids.len(),
        "message_ids": sent_ids,
        "message": format!("Broadcast delivered to {} recipient(s).", sent_ids.len())
    })))
}


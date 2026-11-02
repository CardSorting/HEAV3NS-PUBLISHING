use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::{get_user_public_profile, is_blocked_between, round_money, AppState},
    models::*,
};

pub async fn get_messenger_contacts(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();
    let mut stmt = conn.prepare(
        "SELECT DISTINCT CASE WHEN sender_id = ?1 THEN receiver_id ELSE sender_id END AS contact_id
         FROM user_messages
         WHERE sender_id = ?1 OR receiver_id = ?1",
    )?;
    let mut contact_ids: Vec<i64> = stmt
        .query_map(params![auth.user_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    // Also include other creators if contact list is small so user can easily message anyone
    let mut all_u = conn.prepare("SELECT id FROM users WHERE id != ?1 ORDER BY id ASC")?;
    let all_ids: Vec<i64> = all_u
        .query_map(params![auth.user_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    for id in all_ids {
        if !contact_ids.contains(&id) {
            contact_ids.push(id);
        }
    }

    let mut contacts = Vec::new();
    for cid in contact_ids {
        if is_blocked_between(&conn, auth.user_id, cid) {
            continue;
        }
        if let Ok(u) = get_user_public_profile(&conn, cid, Some(auth.user_id)) {
            let (last_msg, last_at): (String, String) = conn
                .query_row(
                    "SELECT message, created_at FROM user_messages
                     WHERE (sender_id = ?1 AND receiver_id = ?2) OR (sender_id = ?2 AND receiver_id = ?1)
                     ORDER BY id DESC LIMIT 1",
                    params![auth.user_id, cid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )
                .unwrap_or_else(|_| ("Start a conversation".into(), "".into()));

            let unread_count: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM user_messages WHERE sender_id = ?1 AND receiver_id = ?2 AND is_seen = 0",
                    params![cid, auth.user_id],
                    |r| r.get(0),
                )
                .unwrap_or(0);

            contacts.push(ConversationContact {
                user_id: cid,
                name: u.name,
                username: u.username,
                avatar: u.avatar,
                is_verified: u.is_verified,
                last_message: last_msg,
                last_message_at: last_at,
                unread_count,
            });
        }
    }

    Ok(Json(json!({
        "success": true,
        "contacts": contacts
    })))
}

fn load_message_attachments(
    conn: &rusqlite::Connection,
    message_id: i64,
    is_unlocked: bool,
) -> Vec<Attachment> {
    let Ok(mut att_stmt) = conn.prepare(
        "SELECT id, user_id, post_id, message_id, filename, file_url, file_type, file_size, is_vault, is_archived, created_at
         FROM attachments WHERE message_id = ?1 ORDER BY created_at ASC",
    ) else {
        return Vec::new();
    };
    att_stmt
        .query_map(params![message_id], |arow| {
            let raw_url: String = arow.get(5)?;
            Ok(Attachment {
                id: arow.get(0)?,
                user_id: arow.get(1)?,
                post_id: arow.get(2)?,
                message_id: arow.get(3)?,
                filename: arow.get(4)?,
                file_url: if is_unlocked {
                    raw_url
                } else {
                    "/img/post-locked.svg".to_string()
                },
                file_type: arow.get(6)?,
                file_size: arow.get(7)?,
                is_vault: arow.get::<_, i64>(8)? != 0,
                is_archived: arow.get::<_, i64>(9)? != 0,
                created_at: arow.get(10)?,
            })
        })
        .map(|rows| rows.filter_map(Result::ok).collect())
        .unwrap_or_default()
}

pub async fn get_messages_with_user(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(other_user_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();

    if is_blocked_between(&conn, auth.user_id, other_user_id) {
        return Err(AppError::Forbidden(
            "Conversation unavailable because one of you has blocked the other.".into(),
        ));
    }

    // Mark messages from other_user_id as seen
    conn.execute(
        "UPDATE user_messages SET is_seen = 1 WHERE sender_id = ?1 AND receiver_id = ?2",
        params![other_user_id, auth.user_id],
    )?;

    let mut stmt = conn.prepare(
        "SELECT m.id, m.sender_id, m.receiver_id, u.name, u.username, u.avatar,
                m.message, m.price, m.is_seen, m.is_pinned, m.created_at
         FROM user_messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ?1 AND m.receiver_id = ?2)
            OR (m.sender_id = ?2 AND m.receiver_id = ?1)
         ORDER BY m.id ASC",
    )?;

    let messages: Vec<MessageView> = stmt
        .query_map(params![auth.user_id, other_user_id], |r| {
            let mid: i64 = r.get(0)?;
            let sender_id: i64 = r.get(1)?;
            let price: f64 = r.get(7)?;

            let is_unlocked = if sender_id == auth.user_id || price <= 0.0 {
                true
            } else {
                conn.query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'",
                    params![auth.user_id, mid],
                    |tr| tr.get::<_, i64>(0),
                )
                .unwrap_or(0)
                    > 0
            };

            let raw_msg: String = r.get(6)?;
            let display_msg = if is_unlocked {
                raw_msg
            } else {
                "🔒 Locked Pay-Per-View Message — Unlock to view".into()
            };

            let attachments = load_message_attachments(&conn, mid, is_unlocked);

            Ok(MessageView {
                id: mid,
                sender_id,
                receiver_id: r.get(2)?,
                sender_name: r.get(3)?,
                sender_username: r.get(4)?,
                sender_avatar: r.get(5)?,
                message: display_msg,
                price,
                is_unlocked,
                is_seen: r.get::<_, i64>(8)? != 0,
                is_pinned: r.get::<_, i64>(9)? != 0,
                attachments,
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    let contact_profile = get_user_public_profile(&conn, other_user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "contact": contact_profile,
        "messages": messages
    })))
}

#[derive(Deserialize)]
pub struct SendMessagePayload {
    pub receiver_id: i64,
    pub message: String,
    pub price: Option<f64>,
    pub attachments: Option<Vec<String>>,
}

pub async fn send_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SendMessagePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "messenger_send_dm").await?;
    let res: Result<Value, AppError> = (|| {
        if payload.receiver_id == auth.user_id {
            return Err(AppError::Unprocessable("Cannot send a message to yourself.".into()));
        }
        let msg = payload.message.trim();
        let attachment_inputs = payload.attachments.clone().unwrap_or_default();
        if msg.is_empty() && attachment_inputs.is_empty() {
            return Err(AppError::Unprocessable(
                "Message must contain text or at least one attachment.".into(),
            ));
        }
        let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
        let conn = state.lock_db();

        if is_blocked_between(&conn, auth.user_id, payload.receiver_id) {
            return Err(AppError::Forbidden(
                "Cannot send messages to this user due to block settings.".into(),
            ));
        }

        let final_msg = if msg.is_empty() {
            "📎 Sent media attachment".to_string()
        } else {
            msg.to_string()
        };

        conn.execute(
            "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
            params![auth.user_id, payload.receiver_id, final_msg, price],
        )?;
        let mid = conn.last_insert_rowid();

        for item in attachment_inputs {
            let updated = conn.execute(
                "UPDATE attachments SET message_id = ?1 WHERE (id = ?2 OR file_url = ?2) AND user_id = ?3",
                params![mid, item, auth.user_id],
            )?;
            if updated == 0 && (item.starts_with('/') || item.starts_with("http")) {
                let new_att_id = Uuid::new_v4().to_string();
                let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                    "video"
                } else {
                    "image"
                };
                conn.execute(
                    "INSERT INTO attachments (id, user_id, message_id, filename, file_url, file_type, file_size)
                     VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1024)",
                    params![new_att_id, auth.user_id, mid, "message-media", item, ftype],
                )?;
            }
        }

        let _ = conn.execute(
            "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
             VALUES (?1, ?2, ?3, 'message', ?4)",
            params![
                Uuid::new_v4().to_string(),
                auth.user_id,
                payload.receiver_id,
                format!("@{} sent you a direct message", auth.username)
            ],
        );

        let sender = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;
        let attachments = load_message_attachments(&conn, mid, true);
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "message",
            Some(mid.to_string()),
            0.0,
            json!({ "receiver_id": payload.receiver_id, "message_id": mid, "price": price }),
        );

        Ok(json!({
            "success": true,
            "message": MessageView {
                id: mid,
                sender_id: auth.user_id,
                receiver_id: payload.receiver_id,
                sender_name: sender.name,
                sender_username: sender.username,
                sender_avatar: sender.avatar,
                message: final_msg,
                price,
                is_unlocked: true,
                is_seen: false,
                is_pinned: false,
                attachments,
                created_at: "Just now".into(),
            }
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn toggle_pin_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(message_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "messenger_send_dm").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let (sender_id, receiver_id, current_pin): (i64, i64, i64) = conn.query_row(
            "SELECT sender_id, receiver_id, is_pinned FROM user_messages WHERE id = ?1",
            params![message_id],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
        )?;
        if sender_id != auth.user_id && receiver_id != auth.user_id {
            return Err(AppError::Forbidden("Not a participant in this conversation.".into()));
        }
        let next_pin = if current_pin == 0 { 1 } else { 0 };
        conn.execute(
            "UPDATE user_messages SET is_pinned = ?1 WHERE id = ?2",
            params![next_pin, message_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "message",
            Some(message_id.to_string()),
            0.0,
            json!({ "message_id": message_id, "is_pinned": next_pin != 0 }),
        );
        Ok(json!({
            "success": true,
            "is_pinned": next_pin != 0
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(message_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "messenger_send_dm").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let sender_id: i64 = conn.query_row(
            "SELECT sender_id FROM user_messages WHERE id = ?1",
            params![message_id],
            |r| r.get(0),
        )?;
        if sender_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Only the sender can delete this message.".into()));
        }
        let was_purchased: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM transactions WHERE message_id = ?1 AND tx_type = 'message-unlock' AND status = 'approved'",
                params![message_id],
                |r| r.get(0),
            )
            .unwrap_or(0);
        if was_purchased > 0 {
            return Err(AppError::Unprocessable(
                "Cannot delete a Pay-Per-View message that has already been purchased by the recipient.".into(),
            ));
        }
        conn.execute("UPDATE attachments SET message_id = NULL WHERE message_id = ?1", params![message_id])?;
        conn.execute(
            "DELETE FROM user_messages WHERE id = ?1",
            params![message_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "message",
            Some(message_id.to_string()),
            0.0,
            json!({ "deleted_message_id": message_id }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


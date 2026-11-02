use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser, OptionalAuthUser},
    db::{get_user_public_profile, is_blocked_between, round_money, AppState},
    models::*,
};

pub fn compute_stream_unlocked(
    conn: &rusqlite::Connection,
    stream_id: i64,
    owner_id: i64,
    price: f64,
    requires_subscription: bool,
    viewer_id: Option<i64>,
) -> bool {
    let is_owner_or_admin = if let Some(vid) = viewer_id {
        if vid == owner_id {
            true
        } else {
            let role: i64 = conn
                .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
                .unwrap_or(2);
            role == 1
        }
    } else {
        false
    };
    if is_owner_or_admin {
        return true;
    }

    let sub_ok = if !requires_subscription {
        true
    } else if let Some(vid) = viewer_id {
        conn.query_row(
            "SELECT COUNT(*) FROM subscriptions WHERE sender_user_id = ?1 AND recipient_user_id = ?2 AND status = 'completed' AND expires_at >= datetime('now')",
            params![vid, owner_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    };

    let price_ok = if price <= 0.0 {
        true
    } else if let Some(vid) = viewer_id {
        conn.query_row(
            "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND stream_id = ?2 AND tx_type = 'stream-unlock' AND status = 'approved'",
            params![vid, stream_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    };

    sub_ok && price_ok
}

pub async fn get_streams(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let viewer_is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

    let mut stmt = conn.prepare(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.name, s.slug, s.poster, s.price, s.requires_subscription, s.is_public, s.status, s.rtmp_server, s.rtmp_key, s.hls_link, s.viewer_count, s.created_at
         FROM streams s
         JOIN users u ON s.user_id = u.id
         ORDER BY CASE WHEN s.status = 'in-progress' THEN 0 ELSE 1 END, s.id DESC",
    )?;
    let streams: Vec<StreamView> = stmt
        .query_map([], |r| {
            let sid: i64 = r.get(0)?;
            let ver: Option<String> = r.get(5)?;
            let owner_id: i64 = r.get(1)?;
            let price: f64 = r.get(9)?;
            let req_sub: bool = r.get::<_, i64>(10)? != 0;
            let is_owner_or_admin = viewer_id == Some(owner_id) || viewer_is_admin;
            let is_unlocked = compute_stream_unlocked(&conn, sid, owner_id, price, req_sub, viewer_id);
            Ok(StreamView {
                id: sid,
                user_id: owner_id,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                name: r.get(6)?,
                slug: r.get(7)?,
                poster: r.get(8)?,
                price,
                requires_subscription: req_sub,
                is_public: r.get::<_, i64>(11)? != 0,
                status: r.get(12)?,
                rtmp_server: if is_owner_or_admin { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner_or_admin { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|sv| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, sv.user_id)
            } else {
                true
            }
        })
        .collect();

    Ok(Json(json!({
        "success": true,
        "streams": streams
    })))
}

#[derive(Deserialize)]
pub struct SaveStreamPayload {
    pub id: Option<i64>,
    pub name: String,
    pub price: Option<f64>,
    pub requires_subscription: Option<bool>,
    pub is_public: Option<bool>,
}

pub async fn save_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SaveStreamPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let name = payload.name.trim();
    if name.is_empty() {
        return Err(AppError::Unprocessable("Stream title is required.".into()));
    }
    let slug = name
        .to_lowercase()
        .chars()
        .map(|c| if c.is_alphanumeric() { c } else { '-' })
        .collect::<String>();
    let rtmp_key = format!("sk_live_{}_{}", auth.username, &Uuid::new_v4().simple().to_string()[..8]);
    let conn = state.lock_db();

    conn.execute(
        "INSERT INTO streams (user_id, name, slug, price, requires_subscription, is_public, status, rtmp_key)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, 'in-progress', ?7)",
        params![
            auth.user_id,
            name,
            slug,
            round_money(payload.price.unwrap_or(0.0).max(0.0)),
            if payload.requires_subscription.unwrap_or(false) { 1 } else { 0 },
            if payload.is_public.unwrap_or(true) { 1 } else { 0 },
            rtmp_key
        ],
    )?;

    Ok(Json(json!({
        "success": true,
        "stream_id": conn.last_insert_rowid()
    })))
}

pub async fn stop_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    conn.execute(
        "UPDATE streams SET status = 'ended' WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![stream_id, auth.user_id, auth.role_id],
    )?;
    Ok(Json(json!({ "success": true })))
}

pub async fn delete_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM streams WHERE id = ?1",
        params![stream_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this stream.".into()));
    }
    conn.execute("DELETE FROM streams WHERE id = ?1", params![stream_id])?;
    Ok(Json(json!({ "success": true })))
}

pub async fn get_stream_detail(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let viewer_is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

    let stream = conn.query_row(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.name, s.slug, s.poster, s.price, s.requires_subscription, s.is_public, s.status, s.rtmp_server, s.rtmp_key, s.hls_link, s.viewer_count, s.created_at
         FROM streams s JOIN users u ON s.user_id = u.id WHERE s.id = ?1",
        params![stream_id],
        |r| {
            let sid: i64 = r.get(0)?;
            let ver: Option<String> = r.get(5)?;
            let owner_id: i64 = r.get(1)?;
            let price: f64 = r.get(9)?;
            let req_sub: bool = r.get::<_, i64>(10)? != 0;
            let is_owner_or_admin = viewer_id == Some(owner_id) || viewer_is_admin;
            let is_unlocked = compute_stream_unlocked(&conn, sid, owner_id, price, req_sub, viewer_id);
            Ok(StreamView {
                id: sid,
                user_id: owner_id,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                name: r.get(6)?,
                slug: r.get(7)?,
                poster: r.get(8)?,
                price,
                requires_subscription: req_sub,
                is_public: r.get::<_, i64>(11)? != 0,
                status: r.get(12)?,
                rtmp_server: if is_owner_or_admin { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner_or_admin { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        },
    )?;

    if let Some(vid) = viewer_id {
        if is_blocked_between(&conn, vid, stream.user_id) {
            return Err(AppError::Forbidden("Stream unavailable due to block settings.".into()));
        }
    }

    let messages: Vec<StreamMessageView> = if !stream.is_unlocked {
        Vec::new()
    } else {
        let mut m_stmt = conn.prepare(
            "SELECT sm.id, sm.stream_id, sm.user_id, u.name, u.username, u.avatar, sm.message, sm.created_at
             FROM stream_messages sm JOIN users u ON sm.user_id = u.id
             WHERE sm.stream_id = ?1 ORDER BY sm.id ASC",
        )?;
        let rows = m_stmt
            .query_map(params![stream_id], |r| {
                Ok(StreamMessageView {
                    id: r.get(0)?,
                    stream_id: r.get(1)?,
                    user_id: r.get(2)?,
                    user_name: r.get(3)?,
                    user_username: r.get(4)?,
                    user_avatar: r.get(5)?,
                    message: r.get(6)?,
                    created_at: r.get(7)?,
                })
            })?
            .filter_map(Result::ok)
            .collect();
        rows
    };

    Ok(Json(json!({
        "success": true,
        "stream": stream,
        "messages": messages
    })))
}

#[derive(Deserialize)]
pub struct StreamCommentPayload {
    pub stream_id: i64,
    pub message: String,
}

pub async fn add_stream_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<StreamCommentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Message cannot be empty.".into()));
    }
    let conn = state.lock_db();
    let (owner_id, price, req_sub): (i64, f64, bool) = conn.query_row(
        "SELECT user_id, price, requires_subscription FROM streams WHERE id = ?1",
        params![payload.stream_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get::<_, i64>(2)? != 0)),
    )?;
    if is_blocked_between(&conn, auth.user_id, owner_id) {
        return Err(AppError::Forbidden("Cannot chat in this stream.".into()));
    }
    if !compute_stream_unlocked(&conn, payload.stream_id, owner_id, price, req_sub, Some(auth.user_id)) {
        return Err(AppError::Forbidden("Unlock this live stream to participate in chat.".into()));
    }

    conn.execute(
        "INSERT INTO stream_messages (stream_id, user_id, message) VALUES (?1, ?2, ?3)",
        params![payload.stream_id, auth.user_id, msg],
    )?;
    let id = conn.last_insert_rowid();
    let user = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "comment": StreamMessageView {
            id,
            stream_id: payload.stream_id,
            user_id: auth.user_id,
            user_name: user.name,
            user_username: user.username,
            user_avatar: user.avatar,
            message: msg.to_string(),
            created_at: "Just now".into()
        }
    })))
}


use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;

use crate::{
    auth::{AppError, AuthUser, OptionalAuthUser},
    db::{is_blocked_between, AppState},
    models::*,
};

pub async fn get_stories(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let mut stmt = conn.prepare(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.media_url, s.media_type, s.caption, s.bg_preset, s.is_highlight, s.views_count, s.expires_at, s.created_at
         FROM stories s
         JOIN users u ON s.user_id = u.id
         WHERE s.expires_at >= datetime('now') OR s.is_highlight = 1
         ORDER BY s.id DESC",
    )?;
    let stories: Vec<StoryView> = stmt
        .query_map([], |r| {
            let ver: Option<String> = r.get(5)?;
            Ok(StoryView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                media_url: r.get(6)?,
                media_type: r.get(7)?,
                caption: r.get(8)?,
                bg_preset: r.get(9)?,
                is_highlight: r.get::<_, i64>(10)? != 0,
                views_count: r.get(11)?,
                expires_at: r.get(12)?,
                created_at: r.get(13)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|st| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, st.user_id)
            } else {
                true
            }
        })
        .collect();

    Ok(Json(json!({
        "success": true,
        "stories": stories
    })))
}

#[derive(Deserialize)]
pub struct CreateStoryPayload {
    pub caption: String,
    pub media_url: Option<String>,
    pub media_type: Option<String>,
    pub bg_preset: Option<String>,
    pub is_highlight: Option<bool>,
}

pub async fn create_story(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateStoryPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "story_create").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
        let conn = state.lock_db();
        let murl = payload.media_url.unwrap_or_default().trim().to_string();
        let mtype = payload.media_type.unwrap_or_else(|| {
            if murl.ends_with(".mp4") || murl.ends_with(".webm") {
                "video".into()
            } else if murl.is_empty() {
                "text".into()
            } else {
                "image".into()
            }
        });
        let preset = payload
            .bg_preset
            .unwrap_or_else(|| "gradient-purple".into());
        let highlight = if payload.is_highlight.unwrap_or(false) {
            1
        } else {
            0
        };
        conn.execute(
            "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, expires_at)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, datetime('now', '+24 hours'))",
            params![auth.user_id, murl, mtype, payload.caption.trim(), preset, highlight],
        )?;
        let sid = conn.last_insert_rowid();
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "story",
            Some(sid.to_string()),
            0.0,
            json!({ "action": "create_story", "is_highlight": highlight != 0 }),
        );
        Ok(json!({
            "success": true,
            "story_id": sid
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn increment_story_view(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    if let Some(a) = auth {
        let _ = conn.execute(
            "INSERT INTO story_views (story_id, viewer_user_id) VALUES (?1, ?2) ON CONFLICT(story_id, viewer_user_id) DO NOTHING",
            params![story_id, a.user_id],
        );
    }
    conn.execute(
        "UPDATE stories SET views_count = views_count + 1 WHERE id = ?1",
        params![story_id],
    )?;
    let views_count: i64 = conn
        .query_row("SELECT views_count FROM stories WHERE id = ?1", params![story_id], |r| r.get(0))
        .unwrap_or(0);
    Ok(Json(json!({
        "success": true,
        "views_count": views_count
    })))
}

pub async fn toggle_story_highlight(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "story_create").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
        let conn = state.lock_db();
        let (owner_id, current_hl): (i64, i64) = conn.query_row(
            "SELECT user_id, is_highlight FROM stories WHERE id = ?1",
            params![story_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )?;
        if owner_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Only the story owner can toggle highlight.".into()));
        }
        let next_hl = if current_hl == 0 { 1 } else { 0 };
        conn.execute(
            "UPDATE stories SET is_highlight = ?1 WHERE id = ?2",
            params![next_hl, story_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "story",
            Some(story_id.to_string()),
            0.0,
            json!({ "action": "toggle_highlight", "is_highlight": next_hl != 0 }),
        );
        Ok(json!({
            "success": true,
            "is_highlight": next_hl != 0
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_story(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "story_create").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
        let conn = state.lock_db();
        let owner_id: i64 = conn.query_row(
            "SELECT user_id FROM stories WHERE id = ?1",
            params![story_id],
            |r| r.get(0),
        )?;
        if owner_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Not authorized to delete this story.".into()));
        }
        conn.execute("DELETE FROM stories WHERE id = ?1", params![story_id])?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "story",
            Some(story_id.to_string()),
            0.0,
            json!({ "action": "delete_story" }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


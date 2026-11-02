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
    db::{get_user_public_profile, is_blocked_between, round_money, AppState},
    models::*,
};

pub fn compute_reel_unlocked(
    conn: &rusqlite::Connection,
    reel_id: i64,
    owner_id: i64,
    price: f64,
    viewer_id: Option<i64>,
) -> bool {
    if price <= 0.0 {
        return true;
    }
    if let Some(vid) = viewer_id {
        if vid == owner_id {
            return true;
        }
        let role: i64 = conn
            .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
            .unwrap_or(2);
        if role == 1 {
            return true;
        }
        conn.query_row(
            "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND reel_id = ?2 AND tx_type = 'reel-unlock' AND status = 'approved'",
            params![vid, reel_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    }
}

pub async fn get_reels(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let mut stmt = conn.prepare(
        "SELECT r.id, r.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                r.caption, r.video_url, r.cover_url, r.price, r.views_count, r.created_at
         FROM reels r
         JOIN users u ON r.user_id = u.id
         ORDER BY r.id DESC",
    )?;
    let reels: Vec<ReelView> = stmt
        .query_map([], |r| {
            let rid: i64 = r.get(0)?;
            let owner_id: i64 = r.get(1)?;
            let ver: Option<String> = r.get(5)?;
            let price: f64 = r.get(9)?;
            let is_unlocked = compute_reel_unlocked(&conn, rid, owner_id, price, viewer_id);
            let likes_count: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM reactions WHERE reel_id = ?1",
                    params![rid],
                    |row| row.get(0),
                )
                .unwrap_or(0);
            let comments_count: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM reel_comments WHERE reel_id = ?1",
                    params![rid],
                    |row| row.get(0),
                )
                .unwrap_or(0);
            let user_liked = viewer_id
                .map(|vid| {
                    conn.query_row(
                        "SELECT COUNT(*) FROM reactions WHERE reel_id = ?1 AND user_id = ?2",
                        params![rid, vid],
                        |row| row.get::<_, i64>(0),
                    )
                    .unwrap_or(0)
                        > 0
                })
                .unwrap_or(false);
            let user_bookmarked = viewer_id
                .map(|vid| {
                    conn.query_row(
                        "SELECT COUNT(*) FROM user_bookmarks WHERE reel_id = ?1 AND user_id = ?2",
                        params![rid, vid],
                        |row| row.get::<_, i64>(0),
                    )
                    .unwrap_or(0)
                        > 0
                })
                .unwrap_or(false);
            let raw_video: String = r.get(7)?;

            Ok(ReelView {
                id: rid,
                user_id: owner_id,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                caption: r.get(6)?,
                video_url: if is_unlocked { raw_video } else { String::new() },
                cover_url: r.get(8)?,
                price,
                is_unlocked,
                views_count: r.get(10)?,
                likes_count,
                comments_count,
                user_liked,
                user_bookmarked,
                created_at: r.get(11)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|rv| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, rv.user_id)
            } else {
                true
            }
        })
        .collect();

    Ok(Json(json!({
        "success": true,
        "reels": reels
    })))
}

#[derive(Deserialize)]
pub struct CreateReelPayload {
    pub caption: String,
    #[serde(alias = "video_path")]
    pub video_url: Option<String>,
    #[serde(alias = "cover_path")]
    pub cover_url: Option<String>,
    pub price: Option<f64>,
}

pub async fn create_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateReelPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let vurl = payload
        .video_url
        .filter(|s| !s.trim().is_empty())
        .unwrap_or_else(|| "/img/video-loading-spinner.mp4".into());
    let curl = payload
        .cover_url
        .filter(|s| !s.trim().is_empty())
        .unwrap_or_else(|| "/img/home-scene-1.svg".into());
    let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
    conn.execute(
        "INSERT INTO reels (user_id, caption, video_url, cover_url, price) VALUES (?1, ?2, ?3, ?4, ?5)",
        params![auth.user_id, payload.caption.trim(), vurl, curl, price],
    )?;
    Ok(Json(json!({
        "success": true,
        "reel_id": conn.last_insert_rowid()
    })))
}

pub async fn increment_reel_view(
    State(state): State<AppState>,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    conn.execute(
        "UPDATE reels SET views_count = views_count + 1 WHERE id = ?1",
        params![reel_id],
    )?;
    let views_count: i64 = conn
        .query_row("SELECT views_count FROM reels WHERE id = ?1", params![reel_id], |r| r.get(0))
        .unwrap_or(0);
    Ok(Json(json!({
        "success": true,
        "views_count": views_count
    })))
}

pub async fn delete_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM reels WHERE id = ?1",
        params![reel_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this reel.".into()));
    }
    conn.execute("DELETE FROM reels WHERE id = ?1", params![reel_id])?;
    Ok(Json(json!({ "success": true })))
}

pub async fn get_reel_comments(
    State(state): State<AppState>,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let mut stmt = conn.prepare(
        "SELECT rc.id, rc.reel_id, rc.user_id, u.name, u.username, u.avatar, u.identity_verified_at, rc.message, rc.created_at
         FROM reel_comments rc
         JOIN users u ON rc.user_id = u.id
         WHERE rc.reel_id = ?1
         ORDER BY rc.id ASC",
    )?;
    let comments: Vec<ReelCommentView> = stmt
        .query_map(params![reel_id], |r| {
            let ver: Option<String> = r.get(6)?;
            Ok(ReelCommentView {
                id: r.get(0)?,
                reel_id: r.get(1)?,
                user_id: r.get(2)?,
                user_name: r.get(3)?,
                user_username: r.get(4)?,
                user_avatar: r.get(5)?,
                user_verified: ver.is_some(),
                message: r.get(7)?,
                created_at: r.get(8)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "comments": comments
    })))
}

#[derive(Deserialize)]
pub struct AddReelCommentPayload {
    pub reel_id: i64,
    pub message: String,
}

pub async fn add_reel_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<AddReelCommentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
    }
    let conn = state.lock_db();
    let (owner_id, price): (i64, f64) = conn.query_row(
        "SELECT user_id, price FROM reels WHERE id = ?1",
        params![payload.reel_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if is_blocked_between(&conn, auth.user_id, owner_id) {
        return Err(AppError::Forbidden("Cannot comment on this creator's reel.".into()));
    }
    if !compute_reel_unlocked(&conn, payload.reel_id, owner_id, price, Some(auth.user_id)) {
        return Err(AppError::Forbidden("Unlock this reel before commenting.".into()));
    }

    conn.execute(
        "INSERT INTO reel_comments (reel_id, user_id, message) VALUES (?1, ?2, ?3)",
        params![payload.reel_id, auth.user_id, msg],
    )?;
    let cid = conn.last_insert_rowid();
    let user = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "comment": ReelCommentView {
            id: cid,
            reel_id: payload.reel_id,
            user_id: auth.user_id,
            user_name: user.name,
            user_username: user.username,
            user_avatar: user.avatar,
            user_verified: user.is_verified,
            message: msg.to_string(),
            created_at: "Just now".into(),
        }
    })))
}

pub async fn delete_reel_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let conn = state.lock_db();
    let (comment_user_id, reel_owner_id): (i64, i64) = conn.query_row(
        "SELECT rc.user_id, r.user_id FROM reel_comments rc JOIN reels r ON rc.reel_id = r.id WHERE rc.id = ?1",
        params![comment_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if comment_user_id != auth.user_id && reel_owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this comment.".into()));
    }
    conn.execute("DELETE FROM reel_comments WHERE id = ?1", params![comment_id])?;
    Ok(Json(json!({ "success": true })))
}


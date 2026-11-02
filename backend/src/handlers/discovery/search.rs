use axum::{
    extract::{Query, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;

use crate::{
    auth::{AppError, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, is_blocked_between, AppState},
    handlers::media::compute_stream_unlocked,
    models::*,
};

#[derive(Deserialize)]
pub struct SearchQuery {
    pub query: Option<String>,
    pub filter: Option<String>, // "top", "latest", "people", "photos", "videos", "live"
}

pub async fn search_handler(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Query(q): Query<SearchQuery>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let term = q.query.unwrap_or_default().trim().to_lowercase();
    let filter = q.filter.unwrap_or_else(|| "top".into());
    let like_pattern = format!("%{term}%");

    // Search Users (only public profiles)
    let mut u_stmt = conn.prepare(
        "SELECT id FROM users
         WHERE public_profile = 1 AND (LOWER(name) LIKE ?1 OR LOWER(username) LIKE ?1 OR LOWER(bio) LIKE ?1)
         ORDER BY id ASC LIMIT 30",
    )?;
    let uids: Vec<i64> = u_stmt
        .query_map(params![like_pattern], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    let mut users = Vec::new();
    for uid in uids {
        if let Some(vid) = viewer_id {
            if is_blocked_between(&conn, vid, uid) {
                continue;
            }
        }
        if let Ok(p) = get_user_public_profile(&conn, uid, viewer_id) {
            users.push(p);
        }
    }

    // Search Posts
    let mut p_stmt = conn.prepare(
        "SELECT p.id FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE p.status = 1
           AND (p.release_date IS NULL OR p.release_date <= datetime('now'))
           AND (p.expire_date IS NULL OR p.expire_date >= datetime('now'))
           AND (LOWER(p.text) LIKE ?1 OR LOWER(u.name) LIKE ?1 OR LOWER(u.username) LIKE ?1)
         ORDER BY p.id DESC LIMIT 30",
    )?;
    let pids: Vec<i64> = p_stmt
        .query_map(params![like_pattern], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    let mut posts = Vec::new();
    for pid in pids {
        if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
            if let Some(vid) = viewer_id {
                if is_blocked_between(&conn, vid, pv.user_id) {
                    continue;
                }
            }
            if filter == "photos" && pv.attachments.is_empty() {
                continue;
            }
            if filter == "videos" && !pv.attachments.iter().any(|a| a.file_type == "video") {
                continue;
            }
            posts.push(pv);
        }
    }

    // Search Streams
    let mut s_stmt = conn.prepare(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.name, s.slug, s.poster, s.price, s.requires_subscription, s.is_public, s.status, s.hls_link, s.viewer_count, s.created_at
         FROM streams s
         JOIN users u ON s.user_id = u.id
         WHERE LOWER(s.name) LIKE ?1 OR LOWER(u.name) LIKE ?1
         ORDER BY s.id DESC",
    )?;
    let streams: Vec<StreamView> = s_stmt
        .query_map(params![like_pattern], |r| {
            let sid: i64 = r.get(0)?;
            let owner_id: i64 = r.get(1)?;
            let ver: Option<String> = r.get(5)?;
            let price: f64 = r.get(9)?;
            let req_sub: bool = r.get::<_, i64>(10)? != 0;
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
                rtmp_server: String::new(),
                rtmp_key: String::new(),
                hls_link: if is_unlocked { r.get(13)? } else { String::new() },
                viewer_count: r.get(14)?,
                is_unlocked,
                created_at: r.get(15)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "filter": filter,
        "users": users,
        "posts": posts,
        "streams": streams
    })))
}


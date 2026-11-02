use axum::{
    extract::{Path, Query, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;

use crate::{
    auth::{AppError, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, is_blocked_between, AppState},
    handlers::media::{compute_reel_unlocked, compute_stream_unlocked},
    models::*,
};

#[derive(Deserialize)]
pub struct ProfileQuery {
    pub filter: Option<String>,
}

pub async fn get_profile(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(username): Path<String>,
    Query(query): Query<ProfileQuery>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

    let target_id: i64 = conn
        .query_row(
            "SELECT id FROM users WHERE username = ?1",
            params![username.trim()],
            |r| r.get(0),
        )
        .map_err(|_| AppError::NotFound(format!("Profile @{username} not found")))?;

    if let Some(vid) = viewer_id {
        if !is_admin && is_blocked_between(&conn, vid, target_id) {
            return Err(AppError::Forbidden(
                "This profile is unavailable due to block settings.".into(),
            ));
        }
    }

    let profile = get_user_public_profile(&conn, target_id, viewer_id)?;

    if !profile.public_profile && viewer_id.is_none() {
        return Err(AppError::Forbidden(
            "This profile is private. Please sign in to view.".into(),
        ));
    }

    let is_owner_or_admin = viewer_id == Some(target_id) || is_admin;
    let post_sql = if is_owner_or_admin {
        "SELECT id FROM posts WHERE user_id = ?1 ORDER BY is_pinned DESC, id DESC"
    } else {
        "SELECT id FROM posts WHERE user_id = ?1 AND status = 1 AND (release_date IS NULL OR release_date <= datetime('now')) AND (expire_date IS NULL OR expire_date >= datetime('now')) ORDER BY is_pinned DESC, id DESC"
    };

    let mut stmt = conn.prepare(post_sql)?;
    let post_ids: Vec<i64> = stmt
        .query_map(params![target_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let filter = query.filter.as_deref().unwrap_or("");
    let mut posts = Vec::new();
    for pid in post_ids {
        if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
            if (filter == "media" || filter == "photos") && pv.attachments.is_empty() {
                continue;
            }
            if filter == "videos" && !pv.attachments.iter().any(|a| a.file_type == "video") {
                continue;
            }
            posts.push(pv);
        }
    }

    // Recent media attachments (only from unlocked posts)
    let mut recent_media = Vec::new();
    for p in &posts {
        if p.is_unlocked {
            for att in &p.attachments {
                recent_media.push(att.clone());
            }
        }
    }

    // Active streams for this user
    let mut s_stmt = conn.prepare(
        "SELECT id, user_id, name, slug, poster, price, requires_subscription, is_public, status, rtmp_server, rtmp_key, hls_link, viewer_count, created_at
         FROM streams WHERE user_id = ?1 ORDER BY id DESC",
    )?;
    let streams: Vec<StreamView> = s_stmt
        .query_map(params![target_id], |r| {
            let sid: i64 = r.get(0)?;
            let price: f64 = r.get(5)?;
            let req_sub: bool = r.get::<_, i64>(6)? != 0;
            let is_unlocked = compute_stream_unlocked(&conn, sid, target_id, price, req_sub, viewer_id);
            Ok(StreamView {
                id: sid,
                user_id: r.get(1)?,
                user_name: profile.name.clone(),
                user_username: profile.username.clone(),
                user_avatar: profile.avatar.clone(),
                user_verified: profile.is_verified,
                name: r.get(2)?,
                slug: r.get(3)?,
                poster: r.get(4)?,
                price,
                requires_subscription: req_sub,
                is_public: r.get::<_, i64>(7)? != 0,
                status: r.get(8)?,
                rtmp_server: if is_owner_or_admin { r.get(9)? } else { String::new() },
                rtmp_key: if is_owner_or_admin { r.get(10)? } else { String::new() },
                hls_link: if is_unlocked { r.get(11)? } else { String::new() },
                viewer_count: r.get(12)?,
                is_unlocked,
                created_at: r.get(13)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Reels for this creator
    let mut r_stmt = conn.prepare(
        "SELECT id, user_id, caption, video_url, cover_url, price, views_count, created_at
         FROM reels WHERE user_id = ?1 ORDER BY id DESC",
    )?;
    let reels: Vec<ReelView> = r_stmt
        .query_map(params![target_id], |r| {
            let rid: i64 = r.get(0)?;
            let price: f64 = r.get(5)?;
            let is_unlocked = compute_reel_unlocked(&conn, rid, target_id, price, viewer_id);
            let likes_count: i64 = conn
                .query_row("SELECT COUNT(*) FROM reactions WHERE reel_id = ?1", params![rid], |row| row.get(0))
                .unwrap_or(0);
            let comments_count: i64 = conn
                .query_row("SELECT COUNT(*) FROM reel_comments WHERE reel_id = ?1", params![rid], |row| row.get(0))
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
            let raw_video: String = r.get(3)?;
            Ok(ReelView {
                id: rid,
                user_id: r.get(1)?,
                user_name: profile.name.clone(),
                user_username: profile.username.clone(),
                user_avatar: profile.avatar.clone(),
                user_verified: profile.is_verified,
                caption: r.get(2)?,
                video_url: if is_unlocked { raw_video } else { String::new() },
                cover_url: r.get(4)?,
                price,
                is_unlocked,
                views_count: r.get(6)?,
                likes_count,
                comments_count,
                user_liked,
                user_bookmarked,
                created_at: r.get(7)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Stories & Highlights for this creator
    let mut st_stmt = conn.prepare(
        "SELECT id, user_id, media_url, media_type, caption, bg_preset, is_highlight, views_count, expires_at, created_at
         FROM stories WHERE user_id = ?1 AND (expires_at >= datetime('now') OR is_highlight = 1)
         ORDER BY is_highlight DESC, id DESC",
    )?;
    let stories: Vec<StoryView> = st_stmt
        .query_map(params![target_id], |r| {
            Ok(StoryView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: profile.name.clone(),
                user_username: profile.username.clone(),
                user_avatar: profile.avatar.clone(),
                user_verified: profile.is_verified,
                media_url: r.get(2)?,
                media_type: r.get(3)?,
                caption: r.get(4)?,
                bg_preset: r.get(5)?,
                is_highlight: r.get::<_, i64>(6)? != 0,
                views_count: r.get(7)?,
                expires_at: r.get(8)?,
                created_at: r.get(9)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "profile": profile,
        "posts": posts,
        "recent_media": recent_media,
        "streams": streams,
        "reels": reels,
        "stories": stories
    })))
}


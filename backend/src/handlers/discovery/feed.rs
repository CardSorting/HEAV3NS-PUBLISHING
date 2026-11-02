use axum::{
    extract::{Query, State},
    response::IntoResponse,
    Json,
};
use serde::Deserialize;
use serde_json::json;

use crate::{
    auth::{AppError, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, is_blocked_between, AppState},
    models::*,
};

#[derive(Deserialize)]
pub struct FeedQuery {
    pub page: Option<i64>,
    pub filter: Option<String>,
}

pub async fn get_feed(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Query(query): Query<FeedQuery>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let page = query.page.unwrap_or(1).max(1);
    let per_page = 20;
    let offset = (page - 1) * per_page;
    let filter = query.filter.as_deref().unwrap_or("all").trim().to_lowercase();

    let mut stmt = conn.prepare(
        "SELECT id FROM posts
         WHERE status = 1
           AND (release_date IS NULL OR release_date <= datetime('now'))
           AND (expire_date IS NULL OR expire_date >= datetime('now'))
         ORDER BY is_pinned DESC, id DESC",
    )?;
    let post_ids: Vec<i64> = stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let mut filtered_posts = Vec::new();
    for pid in post_ids {
        if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
            if let Some(vid) = viewer_id {
                if is_blocked_between(&conn, vid, pv.user_id) {
                    continue;
                }
            }
            if filter == "media" && pv.attachments.is_empty() {
                continue;
            }
            if filter == "photos" && !pv.attachments.iter().any(|a| a.file_type == "image") {
                continue;
            }
            if filter == "videos" && !pv.attachments.iter().any(|a| a.file_type == "video") {
                continue;
            }
            if filter == "subscribed" && !pv.is_subbed {
                continue;
            }
            filtered_posts.push(pv);
        }
    }

    let total_matching = filtered_posts.len() as i64;
    let posts: Vec<PostView> = filtered_posts
        .into_iter()
        .skip(offset as usize)
        .take(per_page as usize)
        .collect();

    let suggestions = build_suggestions(&conn, viewer_id, false)?;

    Ok(Json(json!({
        "success": true,
        "filter": filter,
        "posts": posts,
        "suggestions": suggestions,
        "page": page,
        "has_more": (offset + (posts.len() as i64)) < total_matching
    })))
}

fn build_suggestions(
    conn: &rusqlite::Connection,
    viewer_id: Option<i64>,
    free_only: bool,
) -> Result<Vec<UserPublicProfile>, rusqlite::Error> {
    let sql = if free_only {
        "SELECT id FROM users WHERE public_profile = 1 AND (paid_profile = 0 OR open_profile = 1 OR profile_access_price <= 0) ORDER BY id ASC LIMIT 6"
    } else {
        "SELECT id FROM users WHERE public_profile = 1 ORDER BY id ASC LIMIT 6"
    };
    let mut stmt = conn.prepare(sql)?;
    let ids: Vec<i64> = stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let mut list = Vec::new();
    for uid in ids {
        if Some(uid) == viewer_id {
            continue;
        }
        if let Some(vid) = viewer_id {
            if is_blocked_between(conn, vid, uid) {
                continue;
            }
        }
        if let Ok(p) = get_user_public_profile(conn, uid, viewer_id) {
            list.push(p);
        }
    }
    Ok(list)
}

#[derive(Deserialize)]
pub struct SuggestionsFilterPayload {
    pub free_only: Option<bool>,
}

pub async fn filter_suggestions(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Json(payload): Json<Option<SuggestionsFilterPayload>>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let free_only = payload.and_then(|p| p.free_only).unwrap_or(false);
    let suggestions = build_suggestions(&conn, viewer_id, free_only)?;
    Ok(Json(json!({
        "success": true,
        "suggestions": suggestions
    })))
}


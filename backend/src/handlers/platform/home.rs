use axum::{
    extract::State,
    response::IntoResponse,
    Json,
};
use serde_json::json;

use crate::{
    auth::{AppError, OptionalAuthUser},
    db::{get_user_public_profile, is_blocked_between, AppState},
};

pub async fn health_check() -> impl IntoResponse {
    Json(json!({
        "status": "ok",
        "service": "heav3ns-publishing-rust-backend",
        "version": "2.0.0"
    }))
}

pub async fn get_home_data(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let mut stmt = conn.prepare("SELECT id FROM users ORDER BY id ASC LIMIT 9")?;
    let ids: Vec<i64> = stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let mut featured_members = Vec::new();
    for uid in ids {
        if let Some(vid) = viewer_id {
            if is_blocked_between(&conn, vid, uid) {
                continue;
            }
        }
        if let Ok(profile) = get_user_public_profile(&conn, uid, viewer_id) {
            featured_members.push(profile);
        }
    }

    Ok(Json(json!({
        "success": true,
        "site": {
            "name": "HEAV3NS-Publishing",
            "slogan": "Premium Content Creators SaaS Platform",
            "description": "Launch a premium content platform that feels polished from day one, with subscriptions, direct engagement, discovery, and growth tools."
        },
        "featured_members": featured_members
    })))
}

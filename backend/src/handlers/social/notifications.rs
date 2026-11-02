use axum::{
    extract::{Query, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;

use crate::{
    auth::{AppError, AuthUser},
    db::AppState,
    models::*,
};

#[derive(Deserialize)]
pub struct NotificationsQuery {
    pub filter: Option<String>,
}

pub async fn get_notifications(
    State(state): State<AppState>,
    auth: AuthUser,
    Query(q): Query<NotificationsQuery>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let filter = q.filter.unwrap_or_default();

    // Mark all as read when fetched
    conn.execute(
        "UPDATE notifications SET is_read = 1 WHERE to_user_id = ?1",
        params![auth.user_id],
    )?;

    let mut stmt = conn.prepare(
        "SELECT n.id, n.from_user_id, u.name, u.username, u.avatar,
                n.to_user_id, n.notification_type, n.post_id, n.message, n.is_read, n.created_at
         FROM notifications n
         LEFT JOIN users u ON n.from_user_id = u.id
         WHERE n.to_user_id = ?1
         ORDER BY n.created_at DESC LIMIT 50",
    )?;

    let items: Vec<NotificationView> = stmt
        .query_map(params![auth.user_id], |r| {
            Ok(NotificationView {
                id: r.get(0)?,
                from_user_id: r.get(1)?,
                from_user_name: r.get(2)?,
                from_user_username: r.get(3)?,
                from_user_avatar: r.get(4)?,
                to_user_id: r.get(5)?,
                notification_type: r.get(6)?,
                post_id: r.get(7)?,
                message: r.get(8)?,
                is_read: r.get::<_, i64>(9)? != 0,
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|n| filter.is_empty() || filter == "all" || n.notification_type == filter)
        .collect();

    Ok(Json(json!({
        "success": true,
        "notifications": items
    })))
}


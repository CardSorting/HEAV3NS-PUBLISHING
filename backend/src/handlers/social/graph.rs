use axum::{
    extract::State,
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};

use crate::{
    auth::{AppError, AuthUser},
    db::{is_blocked_between, AppState},
};

#[derive(Deserialize)]
pub struct FollowPayload {
    pub target_user_id: i64,
}

pub async fn toggle_follow(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<FollowPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        if payload.target_user_id == auth.user_id {
            return Err(AppError::Unprocessable("Cannot follow yourself.".into()));
        }
        let conn = state.lock_db();
        if is_blocked_between(&conn, auth.user_id, payload.target_user_id) {
            return Err(AppError::Forbidden("Cannot follow a blocked user.".into()));
        }

        let following_list_id: i64 = conn.query_row(
            "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = 'following'",
            params![auth.user_id],
            |r| r.get(0),
        )?;

        let existing: Option<i64> = conn
            .query_row(
                "SELECT id FROM user_list_members WHERE list_id = ?1 AND user_id = ?2",
                params![following_list_id, payload.target_user_id],
                |r| r.get(0),
            )
            .ok();

        let is_following = if let Some(mid) = existing {
            conn.execute("DELETE FROM user_list_members WHERE id = ?1", params![mid])?;
            let _ = conn.execute(
                "DELETE FROM user_list_members WHERE user_id = ?1 AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?2 AND list_type = 'followers')",
                params![auth.user_id, payload.target_user_id],
            );
            false
        } else {
            conn.execute(
                "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                params![following_list_id, payload.target_user_id],
            )?;
            if let Ok(target_followers_id) = conn.query_row::<i64, _, _>(
                "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = 'followers'",
                params![payload.target_user_id],
                |r| r.get(0),
            ) {
                let _ = conn.execute(
                    "INSERT OR IGNORE INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                    params![target_followers_id, auth.user_id],
                );
            }
            true
        };
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user",
            Some(payload.target_user_id.to_string()),
            0.0,
            json!({ "target_user_id": payload.target_user_id, "is_following": is_following }),
        );

        Ok(json!({
            "success": true,
            "is_following": is_following
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn toggle_block_user(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<FollowPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        if payload.target_user_id == auth.user_id {
            return Err(AppError::Unprocessable("Cannot block yourself.".into()));
        }
        let conn = state.lock_db();
        let blocked_list_id: i64 = conn.query_row(
            "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = 'blocked'",
            params![auth.user_id],
            |r| r.get(0),
        )?;

        let existing: Option<i64> = conn
            .query_row(
                "SELECT id FROM user_list_members WHERE list_id = ?1 AND user_id = ?2",
                params![blocked_list_id, payload.target_user_id],
                |r| r.get(0),
            )
            .ok();

        let is_blocked = if let Some(mid) = existing {
            conn.execute("DELETE FROM user_list_members WHERE id = ?1", params![mid])?;
            false
        } else {
            conn.execute(
                "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                params![blocked_list_id, payload.target_user_id],
            )?;
            let _ = conn.execute(
                "DELETE FROM user_list_members WHERE user_id = ?1 AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?2 AND list_type IN ('following', 'followers'))",
                params![payload.target_user_id, auth.user_id],
            );
            let _ = conn.execute(
                "DELETE FROM user_list_members WHERE user_id = ?1 AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?2 AND list_type IN ('following', 'followers'))",
                params![auth.user_id, payload.target_user_id],
            );
            true
        };
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "user",
            Some(payload.target_user_id.to_string()),
            0.0,
            json!({ "target_user_id": payload.target_user_id, "is_blocked": is_blocked }),
        );

        Ok(json!({
            "success": true,
            "is_blocked": is_blocked
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


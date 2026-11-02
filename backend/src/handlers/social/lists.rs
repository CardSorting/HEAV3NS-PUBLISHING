use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};

use crate::{
    auth::{AppError, AuthUser},
    db::{build_post_view, get_user_public_profile, AppState},
    handlers::media::compute_reel_unlocked,
    models::*,
};

pub async fn get_bookmarks(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let mut stmt = conn.prepare(
        "SELECT post_id FROM user_bookmarks WHERE user_id = ?1 AND post_id IS NOT NULL ORDER BY id DESC",
    )?;
    let pids: Vec<i64> = stmt
        .query_map(params![auth.user_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let mut posts = Vec::new();
    for pid in pids {
        if let Ok(pv) = build_post_view(&conn, pid, Some(auth.user_id)) {
            posts.push(pv);
        }
    }

    let mut r_stmt = conn.prepare(
        "SELECT r.id, r.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                r.caption, r.video_url, r.cover_url, r.price, r.views_count, r.created_at
         FROM user_bookmarks ub
         JOIN reels r ON ub.reel_id = r.id
         JOIN users u ON r.user_id = u.id
         WHERE ub.user_id = ?1 AND ub.reel_id IS NOT NULL
         ORDER BY ub.id DESC",
    )?;
    let reels: Vec<ReelView> = r_stmt
        .query_map(params![auth.user_id], |r| {
            let rid: i64 = r.get(0)?;
            let owner_id: i64 = r.get(1)?;
            let ver: Option<String> = r.get(5)?;
            let price: f64 = r.get(9)?;
            let is_unlocked = compute_reel_unlocked(&conn, rid, owner_id, price, Some(auth.user_id));
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
            let user_liked = conn
                .query_row(
                    "SELECT COUNT(*) FROM reactions WHERE reel_id = ?1 AND user_id = ?2",
                    params![rid, auth.user_id],
                    |row| row.get::<_, i64>(0),
                )
                .unwrap_or(0)
                > 0;
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
                user_bookmarked: true,
                created_at: r.get(11)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "posts": posts,
        "reels": reels
    })))
}

pub async fn get_lists(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let mut stmt = conn.prepare(
        "SELECT id, user_id, name, list_type, created_at FROM user_lists WHERE user_id = ?1 ORDER BY id ASC",
    )?;
    let lists: Vec<UserListView> = stmt
        .query_map(params![auth.user_id], |r| {
            let lid: i64 = r.get(0)?;
            let mut m_stmt = conn
                .prepare("SELECT user_id FROM user_list_members WHERE list_id = ?1")
                .unwrap();
            let member_ids: Vec<i64> = m_stmt
                .query_map(params![lid], |mr| mr.get(0))
                .unwrap()
                .filter_map(Result::ok)
                .collect();
            let mut members = Vec::new();
            for mid in &member_ids {
                if let Ok(mp) = get_user_public_profile(&conn, *mid, Some(auth.user_id)) {
                    members.push(mp);
                }
            }
            Ok(UserListView {
                id: lid,
                user_id: r.get(1)?,
                name: r.get(2)?,
                list_type: r.get(3)?,
                members_count: members.len() as i64,
                members,
                created_at: r.get(4)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "lists": lists
    })))
}

pub async fn get_list_detail(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let list = conn.query_row(
        "SELECT id, user_id, name, list_type, created_at FROM user_lists WHERE id = ?1 AND user_id = ?2",
        params![list_id, auth.user_id],
        |r| {
            let lid: i64 = r.get(0)?;
            let mut m_stmt = conn
                .prepare("SELECT user_id FROM user_list_members WHERE list_id = ?1")
                .unwrap();
            let member_ids: Vec<i64> = m_stmt
                .query_map(params![lid], |mr| mr.get(0))
                .unwrap()
                .filter_map(Result::ok)
                .collect();
            let mut members = Vec::new();
            for mid in &member_ids {
                if let Ok(mp) = get_user_public_profile(&conn, *mid, Some(auth.user_id)) {
                    members.push(mp);
                }
            }
            Ok(UserListView {
                id: lid,
                user_id: r.get(1)?,
                name: r.get(2)?,
                list_type: r.get(3)?,
                members_count: members.len() as i64,
                members,
                created_at: r.get(4)?,
            })
        },
    )?;

    Ok(Json(json!({
        "success": true,
        "list": list
    })))
}

#[derive(Deserialize)]
pub struct SaveListPayload {
    pub id: Option<i64>,
    pub name: String,
}

pub async fn save_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SaveListPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        let name = payload.name.trim();
        if name.is_empty() {
            return Err(AppError::Unprocessable("List name is required.".into()));
        }
        let conn = state.lock_db();
        if let Some(existing_id) = payload.id {
            let updated = conn.execute(
                "UPDATE user_lists SET name = ?1 WHERE id = ?2 AND user_id = ?3 AND list_type = 'custom'",
                params![name, existing_id, auth.user_id],
            )?;
            if updated == 0 {
                return Err(AppError::Forbidden("Cannot rename this list.".into()));
            }
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "list",
                Some(existing_id.to_string()),
                0.0,
                json!({ "list_id": existing_id, "name": name }),
            );
            return Ok(json!({
                "success": true,
                "list_id": existing_id
            }));
        }
        conn.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, ?2, 'custom')",
            params![auth.user_id, name],
        )?;
        let lid = conn.last_insert_rowid();
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "list",
            Some(lid.to_string()),
            0.0,
            json!({ "list_id": lid, "name": name }),
        );
        Ok(json!({
            "success": true,
            "list_id": lid
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn rename_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
    Json(payload): Json<SaveListPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        let name = payload.name.trim();
        if name.is_empty() {
            return Err(AppError::Unprocessable("List name is required.".into()));
        }
        let conn = state.lock_db();
        let updated = conn.execute(
            "UPDATE user_lists SET name = ?1 WHERE id = ?2 AND user_id = ?3 AND list_type = 'custom'",
            params![name, list_id, auth.user_id],
        )?;
        if updated == 0 {
            return Err(AppError::Forbidden("Only custom lists can be renamed.".into()));
        }
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "list",
            Some(list_id.to_string()),
            0.0,
            json!({ "list_id": list_id, "name": name }),
        );
        Ok(json!({
            "success": true,
            "name": name
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn clear_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let (owner_id, list_type): (i64, String) = conn.query_row(
            "SELECT user_id, list_type FROM user_lists WHERE id = ?1",
            params![list_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )?;
        if owner_id != auth.user_id {
            return Err(AppError::Forbidden("Not your list.".into()));
        }
        if list_type == "following" {
            let mut stmt = conn.prepare("SELECT user_id FROM user_list_members WHERE list_id = ?1")?;
            let target_ids: Vec<i64> = stmt
                .query_map(params![list_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            for tid in target_ids {
                let _ = conn.execute(
                    "DELETE FROM user_list_members WHERE user_id = ?1 AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?2 AND list_type = 'followers')",
                    params![auth.user_id, tid],
                );
            }
        }
        conn.execute("DELETE FROM user_list_members WHERE list_id = ?1", params![list_id])?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "list",
            Some(list_id.to_string()),
            0.0,
            json!({ "cleared_list_id": list_id }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        conn.execute(
            "DELETE FROM user_lists WHERE id = ?1 AND user_id = ?2 AND list_type = 'custom'",
            params![list_id, auth.user_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "list",
            Some(list_id.to_string()),
            0.0,
            json!({ "deleted_list_id": list_id }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct ListMemberPayload {
    pub list_id: i64,
    pub member_user_id: i64,
}

pub async fn toggle_list_member(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<ListMemberPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "social_follow_or_block").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let (owner, list_type): (i64, String) = conn.query_row(
            "SELECT user_id, list_type FROM user_lists WHERE id = ?1",
            params![payload.list_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )?;
        if owner != auth.user_id {
            return Err(AppError::Forbidden("Not your list.".into()));
        }

        let existing: Option<i64> = conn
            .query_row(
                "SELECT id FROM user_list_members WHERE list_id = ?1 AND user_id = ?2",
                params![payload.list_id, payload.member_user_id],
                |r| r.get(0),
            )
            .ok();

        let added = if let Some(mid) = existing {
            conn.execute("DELETE FROM user_list_members WHERE id = ?1", params![mid])?;
            if list_type == "following" {
                let _ = conn.execute(
                    "DELETE FROM user_list_members WHERE user_id = ?1 AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?2 AND list_type = 'followers')",
                    params![auth.user_id, payload.member_user_id],
                );
            }
            false
        } else {
            conn.execute(
                "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                params![payload.list_id, payload.member_user_id],
            )?;
            if list_type == "following" {
                if let Ok(target_followers_id) = conn.query_row::<i64, _, _>(
                    "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = 'followers'",
                    params![payload.member_user_id],
                    |r| r.get(0),
                ) {
                    let _ = conn.execute(
                        "INSERT OR IGNORE INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                        params![target_followers_id, auth.user_id],
                    );
                }
            }
            true
        };
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "list",
            Some(payload.list_id.to_string()),
            0.0,
            json!({ "list_id": payload.list_id, "member_user_id": payload.member_user_id, "added": added }),
        );

        Ok(json!({
            "success": true,
            "added": added
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


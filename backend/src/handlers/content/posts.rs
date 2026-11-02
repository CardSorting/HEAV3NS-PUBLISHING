use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, is_blocked_between, round_money, AppState},
    handlers::identity::normalize_db_datetime,
};

pub async fn get_single_post(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(post_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

    let (author_id, is_published): (i64, bool) = conn.query_row(
        "SELECT user_id,
                (status = 1
                 AND (release_date IS NULL OR release_date <= datetime('now'))
                 AND (expire_date IS NULL OR expire_date >= datetime('now')))
         FROM posts WHERE id = ?1",
        params![post_id],
        |r| Ok((r.get(0)?, r.get::<_, i64>(1)? != 0)),
    )?;

    if let Some(vid) = viewer_id {
        if !is_admin && is_blocked_between(&conn, vid, author_id) {
            return Err(AppError::Forbidden("You cannot view this creator's posts.".into()));
        }
    }

    if !is_published && viewer_id != Some(author_id) && !is_admin {
        return Err(AppError::NotFound("Post is not available or has expired.".into()));
    }

    let post = build_post_view(&conn, post_id, viewer_id)?;
    let author = get_user_public_profile(&conn, post.user_id, viewer_id)?;

    Ok(Json(json!({
        "success": true,
        "post": post,
        "author": author
    })))
}

#[derive(Deserialize)]
pub struct SavePostPayload {
    pub id: Option<i64>,
    pub text: String,
    pub price: Option<f64>,
    pub is_pinned: Option<bool>,
    pub release_date: Option<String>,
    pub expire_date: Option<String>,
    pub attachments: Option<Vec<String>>, // attachment IDs or URLs
    pub poll_answers: Option<Vec<String>>,
}

pub async fn save_post(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SavePostPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_create_or_update").await?;
    let res: Result<Value, AppError> = (|| {
        let text = payload.text.trim();
        let attachment_inputs = payload.attachments.clone().unwrap_or_default();

        if text.is_empty() && attachment_inputs.is_empty() {
            return Err(AppError::Unprocessable(
                "Post must contain text or at least one media attachment.".into(),
            ));
        }

        let price = round_money(
            payload
                .price
                .filter(|v| v.is_finite())
                .unwrap_or(0.0)
                .max(0.0),
        );
        let is_pinned = if payload.is_pinned.unwrap_or(false) { 1 } else { 0 };
        let release_date = normalize_db_datetime(payload.release_date);
        let expire_date = normalize_db_datetime(payload.expire_date);
        let conn = state.lock_db();

        let post_id = if let Some(existing_id) = payload.id {
            let owner_id: i64 = conn.query_row(
                "SELECT user_id FROM posts WHERE id = ?1",
                params![existing_id],
                |r| r.get(0),
            )?;
            if owner_id != auth.user_id && auth.role_id != 1 {
                return Err(AppError::Forbidden("You do not own this post.".into()));
            }
            conn.execute(
                "UPDATE posts SET text = ?1, price = ?2, is_pinned = ?3, release_date = ?4, expire_date = ?5 WHERE id = ?6",
                params![
                    text,
                    price,
                    is_pinned,
                    release_date,
                    expire_date,
                    existing_id
                ],
            )?;
            existing_id
        } else {
            conn.execute(
                "INSERT INTO posts (user_id, text, price, status, is_pinned, release_date, expire_date)
                 VALUES (?1, ?2, ?3, 1, ?4, ?5, ?6)",
                params![
                    auth.user_id,
                    text,
                    price,
                    is_pinned,
                    release_date,
                    expire_date
                ],
            )?;
            conn.last_insert_rowid()
        };

        // Link or create attachments
        if payload.id.is_some() && payload.attachments.is_some() {
            conn.execute(
                "UPDATE attachments SET post_id = NULL WHERE post_id = ?1",
                params![post_id],
            )?;
        }

        for item in attachment_inputs {
            let updated = conn.execute(
                "UPDATE attachments SET post_id = ?1 WHERE (id = ?2 OR file_url = ?2) AND user_id = ?3",
                params![post_id, item, auth.user_id],
            )?;
            if updated == 0 && (item.starts_with('/') || item.starts_with("http")) {
                let new_att_id = Uuid::new_v4().to_string();
                let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                    "video"
                } else {
                    "image"
                };
                conn.execute(
                    "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
                     VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1024)",
                    params![new_att_id, auth.user_id, post_id, "attachment", item, ftype],
                )?;
            }
        }

        // Create or update Poll if poll_answers provided
        if let Some(answers) = payload.poll_answers {
            let clean_answers: Vec<String> = answers
                .into_iter()
                .map(|a| a.trim().to_string())
                .filter(|a| !a.is_empty())
                .collect();
            if clean_answers.len() >= 2 {
                let existing_poll: Option<(i64, Vec<String>)> = conn
                    .query_row(
                        "SELECT id FROM polls WHERE post_id = ?1",
                        params![post_id],
                        |r| r.get::<_, i64>(0),
                    )
                    .ok()
                    .map(|pid| {
                        let mut stmt = conn
                            .prepare("SELECT answer FROM poll_answers WHERE poll_id = ?1 ORDER BY id ASC")
                            .unwrap();
                        let cur: Vec<String> = stmt
                            .query_map(params![pid], |r| r.get(0))
                            .unwrap()
                            .filter_map(Result::ok)
                            .collect();
                        (pid, cur)
                    });

                let needs_recreate =
                    !matches!(existing_poll, Some((_, ref cur)) if *cur == clean_answers);

                if needs_recreate {
                    conn.execute("DELETE FROM polls WHERE post_id = ?1", params![post_id])?;
                    conn.execute(
                        "INSERT INTO polls (user_id, post_id) VALUES (?1, ?2)",
                        params![auth.user_id, post_id],
                    )?;
                    let poll_id = conn.last_insert_rowid();
                    for ans in clean_answers {
                        conn.execute(
                            "INSERT INTO poll_answers (poll_id, answer) VALUES (?1, ?2)",
                            params![poll_id, ans],
                        )?;
                    }
                }
            }
        }

        let post_view = build_post_view(&conn, post_id, Some(auth.user_id))?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "post",
            Some(post_id.to_string()),
            0.0,
            json!({ "post_id": post_id, "price": price }),
        );

        let mut resp = json!({
            "success": true,
            "message": "Post saved successfully.",
            "post": post_view
        });
        if let Some(ref dec) = quota_dec {
            resp["_quota_governance"] = serde_json::to_value(dec).unwrap_or_default();
        }
        Ok(resp)
    })();

    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_post(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(post_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_create_or_update").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let owner_id: i64 = conn.query_row(
            "SELECT user_id FROM posts WHERE id = ?1",
            params![post_id],
            |r| r.get(0),
        )?;
        if owner_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Not authorized to delete this post.".into()));
        }
        conn.execute("DELETE FROM posts WHERE id = ?1", params![post_id])?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "post",
            Some(post_id.to_string()),
            0.0,
            json!({ "deleted_post_id": post_id }),
        );
        Ok(json!({
            "success": true,
            "message": "Post deleted."
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct PinPostPayload {
    pub post_id: i64,
}

pub async fn toggle_pin_post(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<PinPostPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_create_or_update").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let (owner_id, current_pin): (i64, i64) = conn.query_row(
            "SELECT user_id, is_pinned FROM posts WHERE id = ?1",
            params![payload.post_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )?;
        if owner_id != auth.user_id {
            return Err(AppError::Forbidden("Only the author can pin this post.".into()));
        }
        let next_pin = if current_pin == 0 { 1 } else { 0 };
        conn.execute(
            "UPDATE posts SET is_pinned = ?1 WHERE id = ?2",
            params![next_pin, payload.post_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "post",
            Some(payload.post_id.to_string()),
            0.0,
            json!({ "post_id": payload.post_id, "is_pinned": next_pin != 0 }),
        );
        Ok(json!({
            "success": true,
            "is_pinned": next_pin != 0
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


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
    auth::{AppError, AuthUser},
    db::{build_post_view, is_blocked_between, AppState},
    models::*,
};

#[derive(Deserialize)]
pub struct ReactPayload {
    pub post_id: Option<i64>,
    pub comment_id: Option<i64>,
    pub reel_id: Option<i64>,
}

pub async fn toggle_reaction(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<ReactPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_react").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();

        if let Some(pid) = payload.post_id {
            let author_id: i64 = conn.query_row(
                "SELECT user_id FROM posts WHERE id = ?1",
                params![pid],
                |r| r.get(0),
            )?;
            if is_blocked_between(&conn, auth.user_id, author_id) {
                return Err(AppError::Forbidden("Cannot react to this creator's post.".into()));
            }
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM reactions WHERE post_id = ?1 AND user_id = ?2",
                    params![pid, auth.user_id],
                    |r| r.get(0),
                )
                .ok();

            let liked = if let Some(rid) = existing {
                conn.execute("DELETE FROM reactions WHERE id = ?1", params![rid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO reactions (user_id, reaction_type, post_id) VALUES (?1, 'like', ?2)",
                    params![auth.user_id, pid],
                )?;
                if author_id != auth.user_id {
                    let _ = conn.execute(
                        "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message)
                         VALUES (?1, ?2, ?3, 'like', ?4, ?5)",
                        params![
                            Uuid::new_v4().to_string(),
                            auth.user_id,
                            author_id,
                            pid,
                            format!("@{} liked your post", auth.username)
                        ],
                    );
                }
                true
            };

            let likes_count: i64 = conn.query_row(
                "SELECT COUNT(*) FROM reactions WHERE post_id = ?1",
                params![pid],
                |r| r.get(0),
            )?;
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "post",
                Some(pid.to_string()),
                0.0,
                json!({ "post_id": pid, "liked": liked }),
            );

            return Ok(json!({
                "success": true,
                "liked": liked,
                "likes_count": likes_count
            }));
        }

        if let Some(cid) = payload.comment_id {
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM reactions WHERE post_comment_id = ?1 AND user_id = ?2",
                    params![cid, auth.user_id],
                    |r| r.get(0),
                )
                .ok();
            let liked = if let Some(rid) = existing {
                conn.execute("DELETE FROM reactions WHERE id = ?1", params![rid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO reactions (user_id, reaction_type, post_comment_id) VALUES (?1, 'like', ?2)",
                    params![auth.user_id, cid],
                )?;
                true
            };
            let likes_count: i64 = conn.query_row(
                "SELECT COUNT(*) FROM reactions WHERE post_comment_id = ?1",
                params![cid],
                |r| r.get(0),
            )?;
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "comment",
                Some(cid.to_string()),
                0.0,
                json!({ "comment_id": cid, "liked": liked }),
            );
            return Ok(json!({
                "success": true,
                "liked": liked,
                "likes_count": likes_count
            }));
        }

        if let Some(rid_target) = payload.reel_id {
            let owner_id: i64 = conn.query_row(
                "SELECT user_id FROM reels WHERE id = ?1",
                params![rid_target],
                |r| r.get(0),
            )?;
            if is_blocked_between(&conn, auth.user_id, owner_id) {
                return Err(AppError::Forbidden("Cannot react to this creator's reel.".into()));
            }
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM reactions WHERE reel_id = ?1 AND user_id = ?2",
                    params![rid_target, auth.user_id],
                    |r| r.get(0),
                )
                .ok();
            let liked = if let Some(rid) = existing {
                conn.execute("DELETE FROM reactions WHERE id = ?1", params![rid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO reactions (user_id, reaction_type, reel_id) VALUES (?1, 'like', ?2)",
                    params![auth.user_id, rid_target],
                )?;
                true
            };
            let likes_count: i64 = conn.query_row(
                "SELECT COUNT(*) FROM reactions WHERE reel_id = ?1",
                params![rid_target],
                |r| r.get(0),
            )?;
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "reel",
                Some(rid_target.to_string()),
                0.0,
                json!({ "reel_id": rid_target, "liked": liked }),
            );
            return Ok(json!({
                "success": true,
                "liked": liked,
                "likes_count": likes_count
            }));
        }

        Err(AppError::BadRequest("post_id, comment_id, or reel_id required".into()))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct BookmarkPayload {
    pub post_id: Option<i64>,
    pub reel_id: Option<i64>,
}

pub async fn toggle_bookmark(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<BookmarkPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_react").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();

        if let Some(pid) = payload.post_id {
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM user_bookmarks WHERE post_id = ?1 AND user_id = ?2",
                    params![pid, auth.user_id],
                    |r| r.get(0),
                )
                .ok();

            let bookmarked = if let Some(bid) = existing {
                conn.execute("DELETE FROM user_bookmarks WHERE id = ?1", params![bid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO user_bookmarks (user_id, post_id) VALUES (?1, ?2)",
                    params![auth.user_id, pid],
                )?;
                true
            };
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "bookmark",
                Some(pid.to_string()),
                0.0,
                json!({ "post_id": pid, "bookmarked": bookmarked }),
            );

            return Ok(json!({
                "success": true,
                "bookmarked": bookmarked
            }));
        }

        if let Some(rid) = payload.reel_id {
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM user_bookmarks WHERE reel_id = ?1 AND user_id = ?2",
                    params![rid, auth.user_id],
                    |r| r.get(0),
                )
                .ok();

            let bookmarked = if let Some(bid) = existing {
                conn.execute("DELETE FROM user_bookmarks WHERE id = ?1", params![bid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO user_bookmarks (user_id, reel_id) VALUES (?1, ?2)",
                    params![auth.user_id, rid],
                )?;
                true
            };
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "bookmark",
                Some(rid.to_string()),
                0.0,
                json!({ "reel_id": rid, "bookmarked": bookmarked }),
            );

            return Ok(json!({
                "success": true,
                "bookmarked": bookmarked
            }));
        }

        Err(AppError::BadRequest("post_id or reel_id required".into()))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct AddCommentPayload {
    pub post_id: i64,
    pub message: String,
}

pub async fn add_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<AddCommentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_comment").await?;
    let res: Result<Value, AppError> = (|| {
        let msg = payload.message.trim();
        if msg.is_empty() {
            return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
        }
        let conn = state.lock_db();
        let post_view = build_post_view(&conn, payload.post_id, Some(auth.user_id))?;

        if is_blocked_between(&conn, auth.user_id, post_view.user_id) {
            return Err(AppError::Forbidden("You cannot comment on this creator's posts.".into()));
        }
        if !post_view.is_unlocked {
            return Err(AppError::Forbidden(
                "You must subscribe or unlock this post before commenting.".into(),
            ));
        }

        let author_id = post_view.user_id;

        conn.execute(
            "INSERT INTO post_comments (post_id, user_id, message) VALUES (?1, ?2, ?3)",
            params![payload.post_id, auth.user_id, msg],
        )?;
        let cid = conn.last_insert_rowid();

        if author_id != auth.user_id {
            let _ = conn.execute(
                "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message)
                 VALUES (?1, ?2, ?3, 'comment', ?4, ?5)",
                params![
                    Uuid::new_v4().to_string(),
                    auth.user_id,
                    author_id,
                    payload.post_id,
                    format!("@{} commented on your post", auth.username)
                ],
            );
        }

        let comment = conn.query_row(
            "SELECT c.id, c.post_id, c.user_id, u.name, u.username, u.avatar, u.identity_verified_at, c.message, c.created_at
             FROM post_comments c JOIN users u ON c.user_id = u.id WHERE c.id = ?1",
            params![cid],
            |crow| {
                let verified: Option<String> = crow.get(6)?;
                Ok(PostCommentView {
                    id: crow.get(0)?,
                    post_id: crow.get(1)?,
                    user_id: crow.get(2)?,
                    user_name: crow.get(3)?,
                    user_username: crow.get(4)?,
                    user_avatar: crow.get(5)?,
                    user_verified: verified.is_some(),
                    message: crow.get(7)?,
                    likes_count: 0,
                    user_liked: false,
                    created_at: crow.get(8)?,
                })
            },
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "comment",
            Some(cid.to_string()),
            0.0,
            json!({ "post_id": payload.post_id, "comment_id": cid }),
        );

        Ok(json!({
            "success": true,
            "comment": comment
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct EditCommentPayload {
    pub message: String,
}

pub async fn edit_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
    Json(payload): Json<EditCommentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_comment").await?;
    let res: Result<Value, AppError> = (|| {
        let msg = payload.message.trim();
        if msg.is_empty() {
            return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
        }
        let conn = state.lock_db();
        let owner_id: i64 = conn.query_row(
            "SELECT user_id FROM post_comments WHERE id = ?1",
            params![comment_id],
            |r| r.get(0),
        )?;
        if owner_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Cannot edit another user's comment.".into()));
        }
        conn.execute(
            "UPDATE post_comments SET message = ?1 WHERE id = ?2",
            params![msg, comment_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "comment",
            Some(comment_id.to_string()),
            0.0,
            json!({ "comment_id": comment_id }),
        );
        Ok(json!({
            "success": true,
            "message": msg
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "post_comment").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let (owner_id, post_author_id): (i64, i64) = conn.query_row(
            "SELECT c.user_id, p.user_id FROM post_comments c JOIN posts p ON c.post_id = p.id WHERE c.id = ?1",
            params![comment_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )?;
        if owner_id != auth.user_id && post_author_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden("Cannot delete another user's comment.".into()));
        }
        conn.execute("DELETE FROM post_comments WHERE id = ?1", params![comment_id])?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "comment",
            Some(comment_id.to_string()),
            0.0,
            json!({ "deleted_comment_id": comment_id }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct PollVotePayload {
    pub poll_id: i64,
    pub answer_id: i64,
}

pub async fn vote_poll(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<PollVotePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "poll_vote").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        let post_id: i64 = conn.query_row(
            "SELECT post_id FROM polls WHERE id = ?1",
            params![payload.poll_id],
            |r| r.get(0),
        )?;

        let post_view = build_post_view(&conn, post_id, Some(auth.user_id))?;
        if is_blocked_between(&conn, auth.user_id, post_view.user_id) {
            return Err(AppError::Forbidden("You cannot vote on this creator's poll.".into()));
        }
        if !post_view.is_unlocked {
            return Err(AppError::Forbidden("Unlock this post to vote in the poll.".into()));
        }

        let valid_answer: i64 = conn.query_row(
            "SELECT COUNT(*) FROM poll_answers WHERE id = ?1 AND poll_id = ?2",
            params![payload.answer_id, payload.poll_id],
            |r| r.get(0),
        )?;
        if valid_answer == 0 {
            return Err(AppError::Unprocessable("Invalid poll answer for this poll.".into()));
        }

        conn.execute(
            "INSERT INTO poll_user_answers (poll_id, user_id, answer_id) VALUES (?1, ?2, ?3)
             ON CONFLICT(poll_id, user_id) DO UPDATE SET answer_id = excluded.answer_id",
            params![payload.poll_id, auth.user_id, payload.answer_id],
        )?;

        let post = build_post_view(&conn, post_id, Some(auth.user_id))?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "poll",
            Some(payload.poll_id.to_string()),
            0.0,
            json!({ "poll_id": payload.poll_id, "answer_id": payload.answer_id }),
        );
        Ok(json!({
            "success": true,
            "poll": post.poll
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


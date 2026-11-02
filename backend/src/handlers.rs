use axum::{
    extract::{Multipart, Path, Query, State},
    http::{header, StatusCode},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};
use uuid::Uuid;

use crate::{
    auth::{create_jwt, AppError, AuthUser, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, AppState},
    models::*,
};

// ============================================================================
// Health & Public Home
// ============================================================================

pub async fn health_check() -> impl IntoResponse {
    Json(json!({
        "status": "ok",
        "service": "justfans-rust-backend",
        "version": "1.0.0"
    }))
}

pub async fn get_home_data(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let mut stmt = conn.prepare("SELECT id FROM users ORDER BY id ASC LIMIT 9")?;
    let ids: Vec<i64> = stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    let mut featured_members = Vec::new();
    for uid in ids {
        if let Ok(profile) = get_user_public_profile(&conn, uid, viewer_id) {
            featured_members.push(profile);
        }
    }

    Ok(Json(json!({
        "success": true,
        "site": {
            "name": "JustFans",
            "slogan": "Premium Content Creators SaaS Platform",
            "description": "Launch a premium content platform that feels polished from day one, with subscriptions, direct engagement, discovery, and growth tools."
        },
        "featured_members": featured_members
    })))
}

// ============================================================================
// Authentication
// ============================================================================

#[derive(Deserialize)]
pub struct RegisterPayload {
    pub name: String,
    pub username: Option<String>,
    pub email: String,
    pub password: String,
}

pub async fn register(
    State(state): State<AppState>,
    Json(payload): Json<RegisterPayload>,
) -> Result<impl IntoResponse, AppError> {
    let name = payload.name.trim();
    let email = payload.email.trim().to_lowercase();
    let password = payload.password.trim();

    if name.is_empty() || email.is_empty() || password.len() < 6 {
        return Err(AppError::Unprocessable(
            "Name, valid email, and password (min 6 chars) are required.".into(),
        ));
    }

    let raw_username = payload
        .username
        .unwrap_or_else(|| {
            email
                .split('@')
                .next()
                .unwrap_or("user")
                .chars()
                .filter(|c| c.is_alphanumeric() || *c == '_')
                .collect()
        })
        .trim()
        .to_lowercase();

    let username = if raw_username.is_empty() {
        format!("user_{}", Uuid::new_v4().simple().to_string()[..6].to_lowercase())
    } else {
        raw_username
    };

    let conn = state.db.lock().unwrap();

    let exists: i64 = conn.query_row(
        "SELECT COUNT(*) FROM users WHERE email = ?1 OR username = ?2",
        params![email, username],
        |r| r.get(0),
    )?;
    if exists > 0 {
        return Err(AppError::Unprocessable(
            "An account with that email or username already exists.".into(),
        ));
    }

    let password_hash = bcrypt::hash(password, 4)
        .map_err(|_| AppError::Internal("Password hashing error".into()))?;
    let referral_code = format!(
        "{}{}",
        username.chars().take(4).collect::<String>().to_uppercase(),
        &Uuid::new_v4().simple().to_string()[..4].to_uppercase()
    );

    conn.execute(
        "INSERT INTO users (name, username, email, password_hash, role_id, email_verified_at, referral_code)
         VALUES (?1, ?2, ?3, ?4, 2, datetime('now'), ?5)",
        params![name, username, email, password_hash, referral_code],
    )?;
    let user_id = conn.last_insert_rowid();

    conn.execute(
        "INSERT INTO wallets (user_id, total) VALUES (?1, 100.0)",
        params![user_id],
    )?;

    for (lname, ltype) in [
        ("Followers", "followers"),
        ("Following", "following"),
        ("Blocked", "blocked"),
    ] {
        conn.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, ?2, ?3)",
            params![user_id, lname, ltype],
        )?;
    }

    let token = create_jwt(user_id, &username, 2)?;
    let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;

    let cookie = format!(
        "justfans_token={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=2592000"
    );

    Ok((
        StatusCode::CREATED,
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "token": token,
            "user": profile
        })),
    ))
}

#[derive(Deserialize)]
pub struct LoginPayload {
    pub email: String,
    pub password: String,
}

pub async fn login(
    State(state): State<AppState>,
    Json(payload): Json<LoginPayload>,
) -> Result<impl IntoResponse, AppError> {
    let identifier = payload.email.trim();
    let password = payload.password.trim();

    if identifier.is_empty() || password.is_empty() {
        return Err(AppError::BadRequest("Email/username and password are required.".into()));
    }

    let conn = state.db.lock().unwrap();

    let user_row: Result<(i64, String, String, i64), rusqlite::Error> = conn.query_row(
        "SELECT id, username, password_hash, role_id FROM users WHERE email = ?1 OR username = ?1",
        params![identifier],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
    );

    let (user_id, username, password_hash, role_id) = match user_row {
        Ok(row) => row,
        Err(_) => {
            return Err(AppError::Unauthorized(
                "These credentials do not match our records.".into(),
            ))
        }
    };

    let valid = bcrypt::verify(password, &password_hash).unwrap_or(false);
    if !valid {
        return Err(AppError::Unauthorized(
            "These credentials do not match our records.".into(),
        ));
    }

    let token = create_jwt(user_id, &username, role_id)?;
    let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;

    let cookie = format!(
        "justfans_token={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age=2592000"
    );

    Ok((
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "token": token,
            "user": profile
        })),
    ))
}

pub async fn logout() -> impl IntoResponse {
    let cookie = "justfans_token=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0";
    (
        [(header::SET_COOKIE, cookie)],
        Json(json!({
            "success": true,
            "message": "Logged out"
        })),
    )
}

pub async fn get_me(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    let unread_notifications: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM notifications WHERE to_user_id = ?1 AND is_read = 0",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let unread_messages: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM user_messages WHERE receiver_id = ?1 AND is_seen = 0",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    Ok(Json(json!({
        "success": true,
        "user": profile,
        "unread_notifications": unread_notifications,
        "unread_messages": unread_messages
    })))
}

// ============================================================================
// Feed & Suggestions
// ============================================================================

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
    let conn = state.db.lock().unwrap();
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
        "SELECT id FROM users WHERE (paid_profile = 0 OR open_profile = 1 OR profile_access_price <= 0) ORDER BY id ASC LIMIT 6"
    } else {
        "SELECT id FROM users ORDER BY id ASC LIMIT 6"
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
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let free_only = payload.and_then(|p| p.free_only).unwrap_or(false);
    let suggestions = build_suggestions(&conn, viewer_id, free_only)?;
    Ok(Json(json!({
        "success": true,
        "suggestions": suggestions
    })))
}

// ============================================================================
// Posts CRUD, Reactions, Comments, Bookmarks, Polls
// ============================================================================

pub async fn get_single_post(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(post_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
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
    let text = payload.text.trim();
    let attachment_inputs = payload.attachments.clone().unwrap_or_default();

    if text.is_empty() && attachment_inputs.is_empty() {
        return Err(AppError::Unprocessable(
            "Post must contain text or at least one media attachment.".into(),
        ));
    }

    let price = payload.price.unwrap_or(0.0).max(0.0);
    let is_pinned = if payload.is_pinned.unwrap_or(false) { 1 } else { 0 };
    let conn = state.db.lock().unwrap();

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
                payload.release_date,
                payload.expire_date,
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
                payload.release_date,
                payload.expire_date
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

    let post_view = build_post_view(&conn, post_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "message": "Post saved successfully.",
        "post": post_view
    })))
}

pub async fn delete_post(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(post_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM posts WHERE id = ?1",
        params![post_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this post.".into()));
    }
    conn.execute("DELETE FROM posts WHERE id = ?1", params![post_id])?;
    Ok(Json(json!({
        "success": true,
        "message": "Post deleted."
    })))
}

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
    let conn = state.db.lock().unwrap();

    if let Some(pid) = payload.post_id {
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
            // Notify post owner
            if let Ok(author_id) = conn.query_row::<i64, _, _>(
                "SELECT user_id FROM posts WHERE id = ?1",
                params![pid],
                |r| r.get(0),
            ) {
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
            }
            true
        };

        let likes_count: i64 = conn.query_row(
            "SELECT COUNT(*) FROM reactions WHERE post_id = ?1",
            params![pid],
            |r| r.get(0),
        )?;

        return Ok(Json(json!({
            "success": true,
            "liked": liked,
            "likes_count": likes_count
        })));
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
        return Ok(Json(json!({
            "success": true,
            "liked": liked,
            "likes_count": likes_count
        })));
    }

    if let Some(rid_target) = payload.reel_id {
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
        return Ok(Json(json!({
            "success": true,
            "liked": liked,
            "likes_count": likes_count
        })));
    }

    Err(AppError::BadRequest("post_id, comment_id, or reel_id required".into()))
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
    let conn = state.db.lock().unwrap();

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

        return Ok(Json(json!({
            "success": true,
            "bookmarked": bookmarked
        })));
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

        return Ok(Json(json!({
            "success": true,
            "bookmarked": bookmarked
        })));
    }

    Err(AppError::BadRequest("post_id or reel_id required".into()))
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
    let conn = state.db.lock().unwrap();
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
    Ok(Json(json!({
        "success": true,
        "is_pinned": next_pin != 0
    })))
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
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
    }
    let conn = state.db.lock().unwrap();
    let author_id: i64 = conn.query_row(
        "SELECT user_id FROM posts WHERE id = ?1",
        params![payload.post_id],
        |r| r.get(0),
    )?;

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

    Ok(Json(json!({
        "success": true,
        "comment": comment
    })))
}

pub async fn delete_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM post_comments WHERE id = ?1",
        params![comment_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Cannot delete another user's comment.".into()));
    }
    conn.execute("DELETE FROM post_comments WHERE id = ?1", params![comment_id])?;
    Ok(Json(json!({ "success": true })))
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
    let conn = state.db.lock().unwrap();
    let post_id: i64 = conn.query_row(
        "SELECT post_id FROM polls WHERE id = ?1",
        params![payload.poll_id],
        |r| r.get(0),
    )?;

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
    Ok(Json(json!({
        "success": true,
        "poll": post.poll
    })))
}

// ============================================================================
// Profiles & Search / Explore
// ============================================================================

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
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let target_id: i64 = conn
        .query_row(
            "SELECT id FROM users WHERE username = ?1",
            params![username.trim()],
            |r| r.get(0),
        )
        .map_err(|_| AppError::NotFound(format!("Profile @{username} not found")))?;

    let profile = get_user_public_profile(&conn, target_id, viewer_id)?;

    let mut stmt = conn.prepare(
        "SELECT id FROM posts WHERE user_id = ?1 AND status = 1 ORDER BY is_pinned DESC, id DESC",
    )?;
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
                rtmp_server: r.get(9)?,
                rtmp_key: String::new(),
                hls_link: if is_unlocked { r.get(11)? } else { String::new() },
                viewer_count: r.get(12)?,
                is_unlocked,
                created_at: r.get(13)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "profile": profile,
        "posts": posts,
        "recent_media": recent_media,
        "streams": streams
    })))
}

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
    let conn = state.db.lock().unwrap();
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
        if let Ok(p) = get_user_public_profile(&conn, uid, viewer_id) {
            users.push(p);
        }
    }

    // Search Posts
    let mut p_stmt = conn.prepare(
        "SELECT p.id FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE p.status = 1 AND (LOWER(p.text) LIKE ?1 OR LOWER(u.name) LIKE ?1 OR LOWER(u.username) LIKE ?1)
         ORDER BY p.id DESC LIMIT 30",
    )?;
    let pids: Vec<i64> = p_stmt
        .query_map(params![like_pattern], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    let mut posts = Vec::new();
    for pid in pids {
        if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
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

// ============================================================================
// Bookmarks & Lists
// ============================================================================

pub async fn get_bookmarks(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
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
            let ver: Option<String> = r.get(5)?;
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

            Ok(ReelView {
                id: rid,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                caption: r.get(6)?,
                video_url: r.get(7)?,
                cover_url: r.get(8)?,
                price: r.get(9)?,
                is_unlocked: true,
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
    let conn = state.db.lock().unwrap();
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
    let conn = state.db.lock().unwrap();
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
    pub name: String,
}

pub async fn save_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SaveListPayload>,
) -> Result<impl IntoResponse, AppError> {
    let name = payload.name.trim();
    if name.is_empty() {
        return Err(AppError::Unprocessable("List name is required.".into()));
    }
    let conn = state.db.lock().unwrap();
    conn.execute(
        "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, ?2, 'custom')",
        params![auth.user_id, name],
    )?;
    let lid = conn.last_insert_rowid();
    Ok(Json(json!({
        "success": true,
        "list_id": lid
    })))
}

pub async fn delete_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "DELETE FROM user_lists WHERE id = ?1 AND user_id = ?2 AND list_type = 'custom'",
        params![list_id, auth.user_id],
    )?;
    Ok(Json(json!({ "success": true })))
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
    let conn = state.db.lock().unwrap();
    let owner: i64 = conn.query_row(
        "SELECT user_id FROM user_lists WHERE id = ?1",
        params![payload.list_id],
        |r| r.get(0),
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
        false
    } else {
        conn.execute(
            "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
            params![payload.list_id, payload.member_user_id],
        )?;
        true
    };

    Ok(Json(json!({
        "success": true,
        "added": added
    })))
}

#[derive(Deserialize)]
pub struct FollowPayload {
    pub target_user_id: i64,
}

pub async fn toggle_follow(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<FollowPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
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
        false
    } else {
        conn.execute(
            "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
            params![following_list_id, payload.target_user_id],
        )?;
        true
    };

    Ok(Json(json!({
        "success": true,
        "is_following": is_following
    })))
}

// ============================================================================
// Messenger
// ============================================================================

pub async fn get_messenger_contacts(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let mut stmt = conn.prepare(
        "SELECT DISTINCT CASE WHEN sender_id = ?1 THEN receiver_id ELSE sender_id END AS contact_id
         FROM user_messages
         WHERE sender_id = ?1 OR receiver_id = ?1",
    )?;
    let mut contact_ids: Vec<i64> = stmt
        .query_map(params![auth.user_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();

    // Also include other creators if contact list is small so user can easily message anyone
    let mut all_u = conn.prepare("SELECT id FROM users WHERE id != ?1 ORDER BY id ASC")?;
    let all_ids: Vec<i64> = all_u
        .query_map(params![auth.user_id], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    for id in all_ids {
        if !contact_ids.contains(&id) {
            contact_ids.push(id);
        }
    }

    let mut contacts = Vec::new();
    for cid in contact_ids {
        if let Ok(u) = get_user_public_profile(&conn, cid, Some(auth.user_id)) {
            let (last_msg, last_at): (String, String) = conn
                .query_row(
                    "SELECT message, created_at FROM user_messages
                     WHERE (sender_id = ?1 AND receiver_id = ?2) OR (sender_id = ?2 AND receiver_id = ?1)
                     ORDER BY id DESC LIMIT 1",
                    params![auth.user_id, cid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )
                .unwrap_or_else(|_| ("Start a conversation".into(), "".into()));

            let unread_count: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM user_messages WHERE sender_id = ?1 AND receiver_id = ?2 AND is_seen = 0",
                    params![cid, auth.user_id],
                    |r| r.get(0),
                )
                .unwrap_or(0);

            contacts.push(ConversationContact {
                user_id: cid,
                name: u.name,
                username: u.username,
                avatar: u.avatar,
                is_verified: u.is_verified,
                last_message: last_msg,
                last_message_at: last_at,
                unread_count,
            });
        }
    }

    Ok(Json(json!({
        "success": true,
        "contacts": contacts
    })))
}

pub async fn get_messages_with_user(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(other_user_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();

    // Mark messages from other_user_id as seen
    conn.execute(
        "UPDATE user_messages SET is_seen = 1 WHERE sender_id = ?1 AND receiver_id = ?2",
        params![other_user_id, auth.user_id],
    )?;

    let mut stmt = conn.prepare(
        "SELECT m.id, m.sender_id, m.receiver_id, u.name, u.username, u.avatar,
                m.message, m.price, m.is_seen, m.is_pinned, m.created_at
         FROM user_messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id = ?1 AND m.receiver_id = ?2)
            OR (m.sender_id = ?2 AND m.receiver_id = ?1)
         ORDER BY m.id ASC",
    )?;

    let messages: Vec<MessageView> = stmt
        .query_map(params![auth.user_id, other_user_id], |r| {
            let mid: i64 = r.get(0)?;
            let sender_id: i64 = r.get(1)?;
            let price: f64 = r.get(7)?;

            let is_unlocked = if sender_id == auth.user_id || price <= 0.0 {
                true
            } else {
                conn.query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'",
                    params![auth.user_id, mid],
                    |tr| tr.get::<_, i64>(0),
                )
                .unwrap_or(0)
                    > 0
            };

            let raw_msg: String = r.get(6)?;
            let display_msg = if is_unlocked {
                raw_msg
            } else {
                "🔒 Locked Pay-Per-View Message — Unlock to view".into()
            };

            Ok(MessageView {
                id: mid,
                sender_id,
                receiver_id: r.get(2)?,
                sender_name: r.get(3)?,
                sender_username: r.get(4)?,
                sender_avatar: r.get(5)?,
                message: display_msg,
                price,
                is_unlocked,
                is_seen: r.get::<_, i64>(8)? != 0,
                is_pinned: r.get::<_, i64>(9)? != 0,
                attachments: Vec::new(),
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    let contact_profile = get_user_public_profile(&conn, other_user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "contact": contact_profile,
        "messages": messages
    })))
}

#[derive(Deserialize)]
pub struct SendMessagePayload {
    pub receiver_id: i64,
    pub message: String,
    pub price: Option<f64>,
}

pub async fn send_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SendMessagePayload>,
) -> Result<impl IntoResponse, AppError> {
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Message cannot be empty.".into()));
    }
    let price = payload.price.unwrap_or(0.0).max(0.0);
    let conn = state.db.lock().unwrap();

    conn.execute(
        "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
        params![auth.user_id, payload.receiver_id, msg, price],
    )?;
    let mid = conn.last_insert_rowid();

    let _ = conn.execute(
        "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
         VALUES (?1, ?2, ?3, 'message', ?4)",
        params![
            Uuid::new_v4().to_string(),
            auth.user_id,
            payload.receiver_id,
            format!("@{} sent you a direct message", auth.username)
        ],
    );

    let sender = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "message": MessageView {
            id: mid,
            sender_id: auth.user_id,
            receiver_id: payload.receiver_id,
            sender_name: sender.name,
            sender_username: sender.username,
            sender_avatar: sender.avatar,
            message: msg.to_string(),
            price,
            is_unlocked: true,
            is_seen: false,
            is_pinned: false,
            attachments: Vec::new(),
            created_at: "Just now".into(),
        }
    })))
}

pub async fn delete_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(message_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "DELETE FROM user_messages WHERE id = ?1 AND sender_id = ?2",
        params![message_id, auth.user_id],
    )?;
    Ok(Json(json!({ "success": true })))
}

// ============================================================================
// Notifications
// ============================================================================

#[derive(Deserialize)]
pub struct NotificationsQuery {
    pub filter: Option<String>,
}

pub async fn get_notifications(
    State(state): State<AppState>,
    auth: AuthUser,
    Query(q): Query<NotificationsQuery>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
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

// ============================================================================
// Payments, Subscriptions, Tips, PPV Unlocks, Wallet & Withdrawals
// ============================================================================

#[derive(Deserialize)]
pub struct InitiatePaymentPayload {
    pub payment_type: String, // "deposit", "subscription", "tip", "post-unlock", "message-unlock", "stream-unlock"
    pub recipient_user_id: Option<i64>,
    pub post_id: Option<i64>,
    pub message_id: Option<i64>,
    pub stream_id: Option<i64>,
    pub amount: Option<f64>,
    pub provider: Option<String>, // "credit", "stripe", "paypal"
    pub months: Option<i64>,
}

pub async fn initiate_payment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<InitiatePaymentPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let provider = payload.provider.unwrap_or_else(|| "credit".into());
    let ptype = payload.payment_type.trim();

    if ptype == "deposit" {
        let amt = payload.amount.unwrap_or(0.0);
        if amt <= 0.0 {
            return Err(AppError::Unprocessable("Deposit amount must be positive.".into()));
        }
        conn.execute(
            "UPDATE wallets SET total = total + ?1, updated_at = datetime('now') WHERE user_id = ?2",
            params![amt, auth.user_id],
        )?;
        conn.execute(
            "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider)
             VALUES (?1, ?1, 'deposit', 'approved', ?2, ?3)",
            params![auth.user_id, amt, provider],
        )?;
        let new_balance: f64 = conn.query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )?;
        return Ok(Json(json!({
            "success": true,
            "message": format!("${:.2} deposited into your wallet!", amt),
            "wallet_balance": new_balance
        })));
    }

    // Determine price and recipient
    let (amount, recipient_id) = match ptype {
        "subscription" => {
            let rec_id = payload
                .recipient_user_id
                .ok_or_else(|| AppError::BadRequest("recipient_user_id required".into()))?;
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("Cannot subscribe to yourself.".into()));
            }
            let months = payload.months.unwrap_or(1);
            let price: f64 = match months {
                3 => conn.query_row(
                    "SELECT profile_access_price_3_months * 3 FROM users WHERE id = ?1",
                    params![rec_id],
                    |r| r.get(0),
                )?,
                6 => conn.query_row(
                    "SELECT profile_access_price_6_months * 6 FROM users WHERE id = ?1",
                    params![rec_id],
                    |r| r.get(0),
                )?,
                12 => conn.query_row(
                    "SELECT profile_access_price_12_months * 12 FROM users WHERE id = ?1",
                    params![rec_id],
                    |r| r.get(0),
                )?,
                _ => conn.query_row(
                    "SELECT profile_access_price FROM users WHERE id = ?1",
                    params![rec_id],
                    |r| r.get(0),
                )?,
            };
            (price.max(0.0), rec_id)
        }
        "post-unlock" => {
            let pid = payload
                .post_id
                .ok_or_else(|| AppError::BadRequest("post_id required".into()))?;
            let (rec_id, price): (i64, f64) = conn.query_row(
                "SELECT user_id, price FROM posts WHERE id = ?1",
                params![pid],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )?;
            let already_unlocked: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND post_id = ?2 AND tx_type = 'post-unlock' AND status = 'approved'",
                    params![auth.user_id, pid],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            if already_unlocked > 0 {
                return Err(AppError::Unprocessable("Post is already unlocked.".into()));
            }
            (price, rec_id)
        }
        "message-unlock" => {
            let mid = payload
                .message_id
                .ok_or_else(|| AppError::BadRequest("message_id required".into()))?;
            let (rec_id, price): (i64, f64) = conn.query_row(
                "SELECT sender_id, price FROM user_messages WHERE id = ?1",
                params![mid],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )?;
            let already_unlocked: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'",
                    params![auth.user_id, mid],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            if already_unlocked > 0 {
                return Err(AppError::Unprocessable("Message is already unlocked.".into()));
            }
            (price, rec_id)
        }
        "stream-unlock" => {
            let sid = payload
                .stream_id
                .ok_or_else(|| AppError::BadRequest("stream_id required".into()))?;
            let (rec_id, price): (i64, f64) = conn.query_row(
                "SELECT user_id, price FROM streams WHERE id = ?1",
                params![sid],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )?;
            let already_unlocked: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND stream_id = ?2 AND tx_type = 'stream-unlock' AND status = 'approved'",
                    params![auth.user_id, sid],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            if already_unlocked > 0 {
                return Err(AppError::Unprocessable("Stream is already unlocked.".into()));
            }
            (price, rec_id)
        }
        "tip" => {
            let amt = payload.amount.unwrap_or(5.0);
            if amt <= 0.0 || !amt.is_finite() {
                return Err(AppError::Unprocessable("Tip amount must be greater than 0.".into()));
            }
            let rec_id = if let Some(r) = payload.recipient_user_id {
                r
            } else if let Some(pid) = payload.post_id {
                conn.query_row("SELECT user_id FROM posts WHERE id = ?1", params![pid], |r| {
                    r.get(0)
                })?
            } else {
                return Err(AppError::BadRequest("recipient_user_id or post_id required".into()));
            };
            (amt, rec_id)
        }
        _ => {
            return Err(AppError::BadRequest(format!(
                "Unsupported payment_type: {ptype}"
            )))
        }
    };

    // Deduct from wallet if using credit
    if provider == "credit" && amount > 0.0 {
        let sender_balance: f64 = conn
            .query_row(
                "SELECT total FROM wallets WHERE user_id = ?1",
                params![auth.user_id],
                |r| r.get(0),
            )
            .unwrap_or(0.0);
        if sender_balance < amount {
            return Err(AppError::Unprocessable(format!(
                "Insufficient wallet balance (${sender_balance:.2} available, ${amount:.2} required). Please deposit funds or choose Stripe/PayPal."
            )));
        }
        conn.execute(
            "UPDATE wallets SET total = total - ?1, updated_at = datetime('now') WHERE user_id = ?2",
            params![amount, auth.user_id],
        )?;
    }

    // Credit recipient wallet (minus 15% platform fee)
    if amount > 0.0 {
        let creator_share = (amount * 0.85 * 100.0).round() / 100.0;
        conn.execute(
            "UPDATE wallets SET total = total + ?1, updated_at = datetime('now') WHERE user_id = ?2",
            params![creator_share, recipient_id],
        )?;
    }

    // Record transaction with generated invoice_id
    let next_invoice_id: i64 = conn
        .query_row(
            "SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions",
            [],
            |r| r.get(0),
        )
        .unwrap_or(1001);

    conn.execute(
        "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, post_id, message_id, stream_id, invoice_id)
         VALUES (?1, ?2, ?3, 'approved', ?4, ?5, ?6, ?7, ?8, ?9)",
        params![
            auth.user_id,
            recipient_id,
            ptype,
            amount,
            provider,
            payload.post_id,
            payload.message_id,
            payload.stream_id,
            next_invoice_id
        ],
    )?;

    if ptype == "subscription" {
        let days = payload.months.unwrap_or(1) * 30;
        conn.execute(
            "INSERT INTO subscriptions (sender_user_id, recipient_user_id, amount, provider, status, expires_at)
             VALUES (?1, ?2, ?3, ?4, 'completed', datetime('now', ?5))",
            params![
                auth.user_id,
                recipient_id,
                amount,
                provider,
                format!("+{days} days")
            ],
        )?;
    }

    // Send notification to creator
    if recipient_id != auth.user_id {
        let notif_msg = match ptype {
            "subscription" => format!("@{} subscribed to your profile for ${amount:.2}", auth.username),
            "tip" => format!("@{} sent you a ${amount:.2} tip!", auth.username),
            "post-unlock" => format!("@{} unlocked your PPV post for ${amount:.2}", auth.username),
            "message-unlock" => format!("@{} unlocked your PPV message for ${amount:.2}", auth.username),
            _ => format!("@{} sent a ${amount:.2} payment", auth.username),
        };
        let _ = conn.execute(
            "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6)",
            params![
                Uuid::new_v4().to_string(),
                auth.user_id,
                recipient_id,
                ptype,
                payload.post_id,
                notif_msg
            ],
        );
    }

    let updated_balance: f64 = conn
        .query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    Ok(Json(json!({
        "success": true,
        "message": "Payment processed successfully!",
        "wallet_balance": updated_balance
    })))
}

pub async fn cancel_subscription(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(sub_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "UPDATE subscriptions SET status = 'canceled' WHERE id = ?1 AND (sender_user_id = ?2 OR recipient_user_id = ?2)",
        params![sub_id, auth.user_id],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Subscription canceled."
    })))
}

#[derive(Deserialize)]
pub struct WithdrawalRequestPayload {
    pub amount: f64,
    pub payment_method: Option<String>,
    pub payment_identifier: Option<String>,
    pub message: Option<String>,
}

pub async fn request_withdrawal(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<WithdrawalRequestPayload>,
) -> Result<impl IntoResponse, AppError> {
    if payload.amount < 20.0 {
        return Err(AppError::Unprocessable(
            "Minimum withdrawal amount is $20.00.".into(),
        ));
    }
    let conn = state.db.lock().unwrap();
    let balance: f64 = conn
        .query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    if balance < payload.amount {
        return Err(AppError::Unprocessable(
            "Requested amount exceeds available wallet balance.".into(),
        ));
    }

    conn.execute(
        "UPDATE wallets SET total = total - ?1, updated_at = datetime('now') WHERE user_id = ?2",
        params![payload.amount, auth.user_id],
    )?;

    conn.execute(
        "INSERT INTO withdrawals (user_id, amount, status, payment_method, payment_identifier, message)
         VALUES (?1, ?2, 'requested', ?3, ?4, ?5)",
        params![
            auth.user_id,
            payload.amount,
            payload.payment_method.unwrap_or_else(|| "Bank Transfer".into()),
            payload.payment_identifier.unwrap_or_default(),
            payload.message.unwrap_or_default()
        ],
    )?;

    let new_balance: f64 = conn.query_row(
        "SELECT total FROM wallets WHERE user_id = ?1",
        params![auth.user_id],
        |r| r.get(0),
    )?;

    Ok(Json(json!({
        "success": true,
        "message": "Withdrawal request submitted for review.",
        "wallet_balance": new_balance
    })))
}

// ============================================================================
// Settings (Profile, Account, Rates, Wallet, Payments, Subscriptions, Privacy, Verify)
// ============================================================================

pub async fn get_settings_data(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    // Active & past subscriptions
    let mut sub_stmt = conn.prepare(
        "SELECT s.id, s.sender_user_id, s.recipient_user_id,
                c.name, c.username, c.avatar,
                f.name, f.username, f.avatar,
                s.amount, s.status, s.expires_at, s.created_at
         FROM subscriptions s
         JOIN users c ON s.recipient_user_id = c.id
         JOIN users f ON s.sender_user_id = f.id
         WHERE s.sender_user_id = ?1 OR s.recipient_user_id = ?1
         ORDER BY s.id DESC",
    )?;
    let subscriptions: Vec<SubscriptionView> = sub_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(SubscriptionView {
                id: r.get(0)?,
                sender_user_id: r.get(1)?,
                recipient_user_id: r.get(2)?,
                creator_name: r.get(3)?,
                creator_username: r.get(4)?,
                creator_avatar: r.get(5)?,
                subscriber_name: r.get(6)?,
                subscriber_username: r.get(7)?,
                subscriber_avatar: r.get(8)?,
                amount: r.get(9)?,
                status: r.get(10)?,
                expires_at: r.get(11)?,
                created_at: r.get(12)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Transactions
    let mut tx_stmt = conn.prepare(
        "SELECT t.id, t.sender_user_id, t.recipient_user_id, u1.name, u2.name,
                t.tx_type, t.status, t.amount, t.currency, t.payment_provider, t.post_id, t.invoice_id, t.created_at
         FROM transactions t
         JOIN users u1 ON t.sender_user_id = u1.id
         LEFT JOIN users u2 ON t.recipient_user_id = u2.id
         WHERE t.sender_user_id = ?1 OR t.recipient_user_id = ?1
         ORDER BY t.id DESC LIMIT 50",
    )?;
    let transactions: Vec<TransactionView> = tx_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(TransactionView {
                id: r.get(0)?,
                sender_user_id: r.get(1)?,
                recipient_user_id: r.get(2)?,
                sender_name: r.get(3)?,
                recipient_name: r.get(4)?,
                tx_type: r.get(5)?,
                status: r.get(6)?,
                amount: r.get(7)?,
                currency: r.get(8)?,
                payment_provider: r.get(9)?,
                post_id: r.get(10)?,
                invoice_id: r.get(11)?,
                created_at: r.get(12)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    // Withdrawals
    let mut w_stmt = conn.prepare(
        "SELECT w.id, w.user_id, u.name, u.username, w.amount, w.status, w.payment_method, w.payment_identifier, w.message, w.created_at
         FROM withdrawals w JOIN users u ON w.user_id = u.id
         WHERE w.user_id = ?1 ORDER BY w.id DESC",
    )?;
    let withdrawals: Vec<WithdrawalView> = w_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(WithdrawalView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                amount: r.get(4)?,
                status: r.get(5)?,
                payment_method: r.get(6)?,
                payment_identifier: r.get(7)?,
                message: r.get(8)?,
                created_at: r.get(9)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    let verification: Option<Value> = conn
        .query_row(
            "SELECT id, status, files, rejection_reason, created_at FROM user_verifies WHERE user_id = ?1",
            params![auth.user_id],
            |r| {
                let files_raw: String = r.get(2)?;
                let files_parsed: Value = serde_json::from_str(&files_raw).unwrap_or_else(|_| json!([]));
                Ok(json!({
                    "id": r.get::<_, i64>(0)?,
                    "status": r.get::<_, String>(1)?,
                    "files": files_parsed,
                    "rejection_reason": r.get::<_, Option<String>>(3)?,
                    "created_at": r.get::<_, String>(4)?,
                }))
            },
        )
        .ok();

    Ok(Json(json!({
        "success": true,
        "profile": profile,
        "subscriptions": subscriptions,
        "transactions": transactions,
        "withdrawals": withdrawals,
        "verification": verification
    })))
}

#[derive(Deserialize)]
pub struct SubmitVerifyPayload {
    pub files: Vec<String>,
}

pub async fn submit_verification_request(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SubmitVerifyPayload>,
) -> Result<impl IntoResponse, AppError> {
    let clean_files: Vec<String> = payload
        .files
        .into_iter()
        .map(|f| f.trim().to_string())
        .filter(|f| !f.is_empty())
        .collect();
    if clean_files.is_empty() {
        return Err(AppError::Unprocessable(
            "Please upload or provide at least one ID document file.".into(),
        ));
    }
    let files_json = serde_json::to_string(&clean_files).unwrap_or_else(|_| "[]".into());
    let conn = state.db.lock().unwrap();
    conn.execute(
        "INSERT INTO user_verifies (user_id, status, files, rejection_reason)
         VALUES (?1, 'pending', ?2, NULL)
         ON CONFLICT(user_id) DO UPDATE SET status = 'pending', files = excluded.files, rejection_reason = NULL",
        params![auth.user_id, files_json],
    )?;

    Ok(Json(json!({
        "success": true,
        "message": "Identity verification documents submitted for review."
    })))
}

#[derive(Deserialize)]
pub struct UpdateProfilePayload {
    pub name: String,
    pub username: String,
    pub bio: Option<String>,
    pub location: Option<String>,
    pub website: Option<String>,
    pub birthdate: Option<String>,
    pub gender_pronoun: Option<String>,
    pub avatar: Option<String>,
    pub cover: Option<String>,
}

pub async fn update_profile_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateProfilePayload>,
) -> Result<impl IntoResponse, AppError> {
    let name = payload.name.trim();
    let username = payload
        .username
        .trim()
        .chars()
        .filter(|c| c.is_alphanumeric() || *c == '_' || *c == '-')
        .collect::<String>()
        .to_lowercase();

    if name.is_empty() || username.is_empty() {
        return Err(AppError::Unprocessable("Name and valid username are required.".into()));
    }

    let conn = state.db.lock().unwrap();
    let existing: i64 = conn.query_row(
        "SELECT COUNT(*) FROM users WHERE username = ?1 AND id != ?2",
        params![username, auth.user_id],
        |r| r.get(0),
    )?;
    if existing > 0 {
        return Err(AppError::Unprocessable("Username is already taken by another user.".into()));
    }

    conn.execute(
        "UPDATE users SET
            name = ?1,
            username = ?2,
            bio = ?3,
            location = ?4,
            website = ?5,
            birthdate = ?6,
            gender_pronoun = ?7,
            avatar = COALESCE(?8, avatar),
            cover = COALESCE(?9, cover)
         WHERE id = ?10",
        params![
            name,
            username,
            payload.bio.unwrap_or_default().trim(),
            payload.location.unwrap_or_default().trim(),
            payload.website.unwrap_or_default().trim(),
            payload.birthdate,
            payload.gender_pronoun,
            payload.avatar,
            payload.cover,
            auth.user_id
        ],
    )?;

    let updated = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;
    Ok(Json(json!({
        "success": true,
        "message": "Profile settings saved.",
        "user": updated
    })))
}

#[derive(Deserialize)]
pub struct UpdateRatesPayload {
    pub paid_profile: bool,
    pub open_profile: bool,
    pub profile_access_price: f64,
    pub profile_access_price_3_months: f64,
    pub profile_access_price_6_months: f64,
    pub profile_access_price_12_months: f64,
}

pub async fn update_rates_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateRatesPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "UPDATE users SET
            paid_profile = ?1,
            open_profile = ?2,
            profile_access_price = ?3,
            profile_access_price_3_months = ?4,
            profile_access_price_6_months = ?5,
            profile_access_price_12_months = ?6
         WHERE id = ?7",
        params![
            if payload.paid_profile { 1 } else { 0 },
            if payload.open_profile { 1 } else { 0 },
            payload.profile_access_price.max(0.0),
            payload.profile_access_price_3_months.max(0.0),
            payload.profile_access_price_6_months.max(0.0),
            payload.profile_access_price_12_months.max(0.0),
            auth.user_id
        ],
    )?;
    let updated = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;
    Ok(Json(json!({
        "success": true,
        "message": "Subscription rates updated.",
        "user": updated
    })))
}

#[derive(Deserialize)]
pub struct UpdateAccountPayload {
    pub email: Option<String>,
    pub new_password: Option<String>,
}

pub async fn update_account_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateAccountPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    if let Some(email) = payload.email {
        let clean_email = email.trim().to_lowercase();
        if !clean_email.is_empty() {
            let exists: i64 = conn.query_row(
                "SELECT COUNT(*) FROM users WHERE email = ?1 AND id != ?2",
                params![clean_email, auth.user_id],
                |r| r.get(0),
            )?;
            if exists > 0 {
                return Err(AppError::Unprocessable("Email is already registered to another account.".into()));
            }
            conn.execute(
                "UPDATE users SET email = ?1 WHERE id = ?2",
                params![clean_email, auth.user_id],
            )?;
        }
    }
    if let Some(pw) = payload.new_password {
        if !pw.trim().is_empty() {
            if pw.trim().len() < 6 {
                return Err(AppError::Unprocessable("New password must be at least 6 characters.".into()));
            }
            let hash = bcrypt::hash(pw.trim(), 4)
                .map_err(|_| AppError::Internal("Hash error".into()))?;
            conn.execute(
                "UPDATE users SET password_hash = ?1 WHERE id = ?2",
                params![hash, auth.user_id],
            )?;
        }
    }
    Ok(Json(json!({
        "success": true,
        "message": "Account security settings updated."
    })))
}

#[derive(Deserialize)]
pub struct UpdatePrivacyPayload {
    pub public_profile: bool,
    pub enable_2fa: bool,
    pub enable_geoblocking: bool,
}

pub async fn update_privacy_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdatePrivacyPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "UPDATE users SET public_profile = ?1, enable_2fa = ?2, enable_geoblocking = ?3 WHERE id = ?4",
        params![
            if payload.public_profile { 1 } else { 0 },
            if payload.enable_2fa { 1 } else { 0 },
            if payload.enable_geoblocking { 1 } else { 0 },
            auth.user_id
        ],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Privacy preferences saved."
    })))
}

// ============================================================================
// Media Vault & File Upload
// ============================================================================

pub async fn get_vault_items(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let mut stmt = conn.prepare(
        "SELECT id, user_id, post_id, message_id, filename, file_url, file_type, file_size, is_vault, is_archived, created_at
         FROM attachments WHERE user_id = ?1 ORDER BY created_at DESC",
    )?;
    let items: Vec<Attachment> = stmt
        .query_map(params![auth.user_id], |r| {
            Ok(Attachment {
                id: r.get(0)?,
                user_id: r.get(1)?,
                post_id: r.get(2)?,
                message_id: r.get(3)?,
                filename: r.get(4)?,
                file_url: r.get(5)?,
                file_type: r.get(6)?,
                file_size: r.get(7)?,
                is_vault: r.get::<_, i64>(8)? != 0,
                is_archived: r.get::<_, i64>(9)? != 0,
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "items": items
    })))
}

pub async fn upload_attachment(
    State(state): State<AppState>,
    auth: AuthUser,
    mut multipart: Multipart,
) -> Result<impl IntoResponse, AppError> {
    let mut saved_attachments = Vec::new();

    while let Some(field) = multipart
        .next_field()
        .await
        .map_err(|e| AppError::BadRequest(format!("Multipart error: {e}")))?
    {
        let orig_name = field.file_name().unwrap_or("upload.bin").to_string();
        let content_type = field.content_type().unwrap_or("image/jpeg").to_string();
        let data = field
            .bytes()
            .await
            .map_err(|e| AppError::BadRequest(format!("Failed reading upload: {e}")))?;

        if data.is_empty() {
            continue;
        }

        let raw_ext = std::path::Path::new(&orig_name)
            .extension()
            .and_then(|e| e.to_str())
            .unwrap_or("jpg");
        let clean_ext: String = raw_ext
            .chars()
            .filter(|c| c.is_ascii_alphanumeric())
            .take(8)
            .collect::<String>()
            .to_lowercase();
        let ext = if clean_ext.is_empty() {
            "jpg".to_string()
        } else {
            clean_ext
        };

        let att_id = Uuid::new_v4().to_string();
        let stored_filename = format!("{att_id}.{ext}");
        let full_path = format!("{}/{}", state.uploads_dir, stored_filename);

        std::fs::write(&full_path, &data)
            .map_err(|e| AppError::Internal(format!("Failed saving file: {e}")))?;

        let file_type = if content_type.starts_with("video") || ext == "mp4" || ext == "webm" {
            "video"
        } else if content_type.starts_with("audio") || ext == "mp3" || ext == "wav" {
            "audio"
        } else {
            "image"
        };

        let file_url = format!("/uploads/{stored_filename}");
        let file_size = data.len() as i64;

        let conn = state.db.lock().unwrap();
        conn.execute(
            "INSERT INTO attachments (id, user_id, filename, file_url, file_type, file_size, is_vault)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1)",
            params![att_id, auth.user_id, orig_name, file_url, file_type, file_size],
        )?;

        saved_attachments.push(Attachment {
            id: att_id,
            user_id: auth.user_id,
            post_id: None,
            message_id: None,
            filename: orig_name,
            file_url,
            file_type: file_type.to_string(),
            file_size,
            is_vault: true,
            is_archived: false,
            created_at: "Just now".into(),
        });
    }

    Ok(Json(json!({
        "success": true,
        "attachments": saved_attachments
    })))
}

pub async fn delete_vault_item(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(att_id): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "DELETE FROM attachments WHERE id = ?1 AND user_id = ?2",
        params![att_id, auth.user_id],
    )?;
    Ok(Json(json!({ "success": true })))
}

// ============================================================================
// Reels, Stories & Live Streams
// ============================================================================

pub async fn get_reels(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
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
            let ver: Option<String> = r.get(5)?;
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

            Ok(ReelView {
                id: rid,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                caption: r.get(6)?,
                video_url: r.get(7)?,
                cover_url: r.get(8)?,
                price: r.get(9)?,
                is_unlocked: true,
                views_count: r.get(10)?,
                likes_count,
                comments_count,
                user_liked,
                user_bookmarked,
                created_at: r.get(11)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "reels": reels
    })))
}

#[derive(Deserialize)]
pub struct CreateReelPayload {
    pub caption: String,
    pub video_url: Option<String>,
    pub cover_url: Option<String>,
}

pub async fn create_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateReelPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let vurl = payload
        .video_url
        .unwrap_or_else(|| "/img/video-loading-spinner.mp4".into());
    let curl = payload
        .cover_url
        .unwrap_or_else(|| "/img/home-scene-1.svg".into());
    conn.execute(
        "INSERT INTO reels (user_id, caption, video_url, cover_url) VALUES (?1, ?2, ?3, ?4)",
        params![auth.user_id, payload.caption.trim(), vurl, curl],
    )?;
    Ok(Json(json!({
        "success": true,
        "reel_id": conn.last_insert_rowid()
    })))
}

pub async fn delete_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
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
    let conn = state.db.lock().unwrap();
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
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
    }
    let conn = state.db.lock().unwrap();
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

pub async fn get_stories(
    State(state): State<AppState>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let mut stmt = conn.prepare(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.media_url, s.media_type, s.caption, s.bg_preset, s.is_highlight, s.views_count, s.expires_at, s.created_at
         FROM stories s
         JOIN users u ON s.user_id = u.id
         WHERE s.expires_at >= datetime('now') OR s.is_highlight = 1
         ORDER BY s.id DESC",
    )?;
    let stories: Vec<StoryView> = stmt
        .query_map([], |r| {
            let ver: Option<String> = r.get(5)?;
            Ok(StoryView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                user_avatar: r.get(4)?,
                user_verified: ver.is_some(),
                media_url: r.get(6)?,
                media_type: r.get(7)?,
                caption: r.get(8)?,
                bg_preset: r.get(9)?,
                is_highlight: r.get::<_, i64>(10)? != 0,
                views_count: r.get(11)?,
                expires_at: r.get(12)?,
                created_at: r.get(13)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "stories": stories
    })))
}

#[derive(Deserialize)]
pub struct CreateStoryPayload {
    pub caption: String,
    pub media_url: Option<String>,
    pub bg_preset: Option<String>,
    pub is_highlight: Option<bool>,
}

pub async fn create_story(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateStoryPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let murl = payload
        .media_url
        .unwrap_or_else(|| "/img/home-scene-1.svg".into());
    let preset = payload
        .bg_preset
        .unwrap_or_else(|| "gradient-purple".into());
    let highlight = if payload.is_highlight.unwrap_or(false) {
        1
    } else {
        0
    };
    conn.execute(
        "INSERT INTO stories (user_id, media_url, caption, bg_preset, is_highlight, expires_at)
         VALUES (?1, ?2, ?3, ?4, ?5, datetime('now', '+24 hours'))",
        params![auth.user_id, murl, payload.caption.trim(), preset, highlight],
    )?;
    Ok(Json(json!({
        "success": true,
        "story_id": conn.last_insert_rowid()
    })))
}

pub fn compute_stream_unlocked(
    conn: &rusqlite::Connection,
    stream_id: i64,
    owner_id: i64,
    price: f64,
    requires_subscription: bool,
    viewer_id: Option<i64>,
) -> bool {
    let is_owner_or_admin = if let Some(vid) = viewer_id {
        if vid == owner_id {
            true
        } else {
            let role: i64 = conn
                .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
                .unwrap_or(2);
            role == 1
        }
    } else {
        false
    };
    if is_owner_or_admin {
        return true;
    }

    let sub_ok = if !requires_subscription {
        true
    } else if let Some(vid) = viewer_id {
        conn.query_row(
            "SELECT COUNT(*) FROM subscriptions WHERE sender_user_id = ?1 AND recipient_user_id = ?2 AND status = 'completed' AND expires_at >= datetime('now')",
            params![vid, owner_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    };

    let price_ok = if price <= 0.0 {
        true
    } else if let Some(vid) = viewer_id {
        conn.query_row(
            "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND stream_id = ?2 AND tx_type = 'stream-unlock' AND status = 'approved'",
            params![vid, stream_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    };

    sub_ok && price_ok
}

pub async fn get_streams(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let mut stmt = conn.prepare(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.name, s.slug, s.poster, s.price, s.requires_subscription, s.is_public, s.status, s.rtmp_server, s.rtmp_key, s.hls_link, s.viewer_count, s.created_at
         FROM streams s
         JOIN users u ON s.user_id = u.id
         ORDER BY CASE WHEN s.status = 'in-progress' THEN 0 ELSE 1 END, s.id DESC",
    )?;
    let streams: Vec<StreamView> = stmt
        .query_map([], |r| {
            let sid: i64 = r.get(0)?;
            let ver: Option<String> = r.get(5)?;
            let owner_id: i64 = r.get(1)?;
            let price: f64 = r.get(9)?;
            let req_sub: bool = r.get::<_, i64>(10)? != 0;
            let is_owner = viewer_id == Some(owner_id);
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
                rtmp_server: if is_owner { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "streams": streams
    })))
}

#[derive(Deserialize)]
pub struct SaveStreamPayload {
    pub id: Option<i64>,
    pub name: String,
    pub price: Option<f64>,
    pub requires_subscription: Option<bool>,
    pub is_public: Option<bool>,
}

pub async fn save_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SaveStreamPayload>,
) -> Result<impl IntoResponse, AppError> {
    let name = payload.name.trim();
    if name.is_empty() {
        return Err(AppError::Unprocessable("Stream title is required.".into()));
    }
    let slug = name
        .to_lowercase()
        .chars()
        .map(|c| if c.is_alphanumeric() { c } else { '-' })
        .collect::<String>();
    let rtmp_key = format!("sk_live_{}_{}", auth.username, &Uuid::new_v4().simple().to_string()[..8]);
    let conn = state.db.lock().unwrap();

    conn.execute(
        "INSERT INTO streams (user_id, name, slug, price, requires_subscription, is_public, status, rtmp_key)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, 'in-progress', ?7)",
        params![
            auth.user_id,
            name,
            slug,
            payload.price.unwrap_or(0.0),
            if payload.requires_subscription.unwrap_or(false) { 1 } else { 0 },
            if payload.is_public.unwrap_or(true) { 1 } else { 0 },
            rtmp_key
        ],
    )?;

    Ok(Json(json!({
        "success": true,
        "stream_id": conn.last_insert_rowid()
    })))
}

pub async fn stop_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "UPDATE streams SET status = 'ended' WHERE id = ?1 AND user_id = ?2",
        params![stream_id, auth.user_id],
    )?;
    Ok(Json(json!({ "success": true })))
}

pub async fn get_stream_detail(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let viewer_id = auth.as_ref().map(|a| a.user_id);

    let stream = conn.query_row(
        "SELECT s.id, s.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                s.name, s.slug, s.poster, s.price, s.requires_subscription, s.is_public, s.status, s.rtmp_server, s.rtmp_key, s.hls_link, s.viewer_count, s.created_at
         FROM streams s JOIN users u ON s.user_id = u.id WHERE s.id = ?1",
        params![stream_id],
        |r| {
            let sid: i64 = r.get(0)?;
            let ver: Option<String> = r.get(5)?;
            let owner_id: i64 = r.get(1)?;
            let price: f64 = r.get(9)?;
            let req_sub: bool = r.get::<_, i64>(10)? != 0;
            let is_owner = viewer_id == Some(owner_id);
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
                rtmp_server: if is_owner { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        },
    )?;

    let mut m_stmt = conn.prepare(
        "SELECT sm.id, sm.stream_id, sm.user_id, u.name, u.username, u.avatar, sm.message, sm.created_at
         FROM stream_messages sm JOIN users u ON sm.user_id = u.id
         WHERE sm.stream_id = ?1 ORDER BY sm.id ASC",
    )?;
    let messages: Vec<StreamMessageView> = m_stmt
        .query_map(params![stream_id], |r| {
            Ok(StreamMessageView {
                id: r.get(0)?,
                stream_id: r.get(1)?,
                user_id: r.get(2)?,
                user_name: r.get(3)?,
                user_username: r.get(4)?,
                user_avatar: r.get(5)?,
                message: r.get(6)?,
                created_at: r.get(7)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "stream": stream,
        "messages": messages
    })))
}

#[derive(Deserialize)]
pub struct StreamCommentPayload {
    pub stream_id: i64,
    pub message: String,
}

pub async fn add_stream_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<StreamCommentPayload>,
) -> Result<impl IntoResponse, AppError> {
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Message cannot be empty.".into()));
    }
    let conn = state.db.lock().unwrap();
    conn.execute(
        "INSERT INTO stream_messages (stream_id, user_id, message) VALUES (?1, ?2, ?3)",
        params![payload.stream_id, auth.user_id, msg],
    )?;
    let id = conn.last_insert_rowid();
    let user = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    Ok(Json(json!({
        "success": true,
        "comment": StreamMessageView {
            id,
            stream_id: payload.stream_id,
            user_id: auth.user_id,
            user_name: user.name,
            user_username: user.username,
            user_avatar: user.avatar,
            message: msg.to_string(),
            created_at: "Just now".into()
        }
    })))
}

// ============================================================================
// Invoices, Public Pages, Contact, Reports, AI Suggestions & Admin Panel
// ============================================================================

pub async fn get_invoice_detail(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(invoice_or_tx_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let tx = conn.query_row(
        "SELECT t.id, t.sender_user_id, t.recipient_user_id, u1.name, u1.email, u2.name,
                t.tx_type, t.status, t.amount, t.currency, t.payment_provider, t.post_id, t.invoice_id, t.created_at
         FROM transactions t
         JOIN users u1 ON t.sender_user_id = u1.id
         LEFT JOIN users u2 ON t.recipient_user_id = u2.id
         WHERE (t.invoice_id = ?1 OR t.id = ?1)
           AND (t.sender_user_id = ?2 OR t.recipient_user_id = ?2 OR ?3 = 1)
         ORDER BY t.id DESC LIMIT 1",
        params![invoice_or_tx_id, auth.user_id, auth.role_id],
        |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "sender_user_id": r.get::<_, i64>(1)?,
                "recipient_user_id": r.get::<_, Option<i64>>(2)?,
                "sender_name": r.get::<_, String>(3)?,
                "sender_email": r.get::<_, String>(4)?,
                "recipient_name": r.get::<_, Option<String>>(5)?,
                "tx_type": r.get::<_, String>(6)?,
                "status": r.get::<_, String>(7)?,
                "amount": r.get::<_, f64>(8)?,
                "currency": r.get::<_, String>(9)?,
                "payment_provider": r.get::<_, String>(10)?,
                "post_id": r.get::<_, Option<i64>>(11)?,
                "invoice_id": r.get::<_, Option<i64>>(12)?.unwrap_or(invoice_or_tx_id),
                "created_at": r.get::<_, String>(13)?,
            }))
        },
    )?;

    Ok(Json(json!({
        "success": true,
        "invoice": tx
    })))
}

pub async fn get_public_page(
    State(state): State<AppState>,
    Path(slug): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    let page = conn.query_row(
        "SELECT id, slug, title, short_title, content, updated_at FROM public_pages WHERE slug = ?1",
        params![slug],
        |r| {
            Ok(PublicPageView {
                id: r.get(0)?,
                slug: r.get(1)?,
                title: r.get(2)?,
                short_title: r.get(3)?,
                content: r.get(4)?,
                updated_at: r.get(5)?,
            })
        },
    )?;

    Ok(Json(json!({
        "success": true,
        "page": page
    })))
}

#[derive(Deserialize)]
pub struct ContactPayload {
    pub email: String,
    pub subject: String,
    pub message: String,
}

pub async fn send_contact_message(
    State(state): State<AppState>,
    Json(payload): Json<ContactPayload>,
) -> Result<impl IntoResponse, AppError> {
    if payload.email.trim().is_empty() || payload.message.trim().is_empty() {
        return Err(AppError::Unprocessable("Email and message are required.".into()));
    }
    let conn = state.db.lock().unwrap();
    conn.execute(
        "INSERT INTO contact_messages (email, subject, message) VALUES (?1, ?2, ?3)",
        params![
            payload.email.trim(),
            payload.subject.trim(),
            payload.message.trim()
        ],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Your message has been sent! Our support team will get back to you shortly."
    })))
}

#[derive(Deserialize)]
pub struct ReportPayload {
    pub reported_user_id: i64,
    pub post_id: Option<i64>,
    pub report_type: String,
    pub details: Option<String>,
}

pub async fn submit_report(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<ReportPayload>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.db.lock().unwrap();
    conn.execute(
        "INSERT INTO user_reports (from_user_id, reported_user_id, post_id, report_type, details)
         VALUES (?1, ?2, ?3, ?4, ?5)",
        params![
            auth.user_id,
            payload.reported_user_id,
            payload.post_id,
            payload.report_type,
            payload.details.unwrap_or_default()
        ],
    )?;
    Ok(Json(json!({
        "success": true,
        "message": "Report submitted to moderation team."
    })))
}

#[derive(Deserialize)]
pub struct AiSuggestionPayload {
    pub topic: Option<String>,
}

pub async fn generate_ai_suggestion(
    _auth: AuthUser,
    Json(payload): Json<AiSuggestionPayload>,
) -> Result<impl IntoResponse, AppError> {
    let topic = payload
        .topic
        .unwrap_or_else(|| "exclusive creator update".into());
    let suggestion = format!(
        "✨ Just dropped a brand new {topic} set! Unlock or subscribe now for full HD behind-the-scenes access and let me know your favorite shot in the comments 💬🔥 #exclusive #creator"
    );
    Ok(Json(json!({
        "success": true,
        "suggestion": suggestion
    })))
}

pub async fn get_admin_dashboard(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.db.lock().unwrap();

    let total_users: i64 = conn.query_row("SELECT COUNT(*) FROM users", [], |r| r.get(0))?;
    let total_posts: i64 = conn.query_row("SELECT COUNT(*) FROM posts", [], |r| r.get(0))?;
    let total_subscriptions: i64 =
        conn.query_row("SELECT COUNT(*) FROM subscriptions", [], |r| r.get(0))?;
    let total_revenue: f64 = conn.query_row(
        "SELECT COALESCE(SUM(amount), 0.0) FROM transactions WHERE status = 'approved'",
        [],
        |r| r.get(0),
    )?;

    let mut u_stmt = conn.prepare("SELECT id FROM users ORDER BY id ASC")?;
    let uids: Vec<i64> = u_stmt
        .query_map([], |r| r.get(0))?
        .filter_map(Result::ok)
        .collect();
    let mut users = Vec::new();
    for uid in uids {
        if let Ok(u) = get_user_public_profile(&conn, uid, Some(auth.user_id)) {
            users.push(u);
        }
    }

    let mut w_stmt = conn.prepare(
        "SELECT w.id, w.user_id, u.name, u.username, w.amount, w.status, w.payment_method, w.payment_identifier, w.message, w.created_at
         FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.id DESC",
    )?;
    let withdrawals: Vec<WithdrawalView> = w_stmt
        .query_map([], |r| {
            Ok(WithdrawalView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                user_name: r.get(2)?,
                user_username: r.get(3)?,
                amount: r.get(4)?,
                status: r.get(5)?,
                payment_method: r.get(6)?,
                payment_identifier: r.get(7)?,
                message: r.get(8)?,
                created_at: r.get(9)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();

    let mut r_stmt = conn.prepare(
        "SELECT r.id, u1.username, u2.username, r.post_id, r.report_type, r.details, r.status, r.created_at
         FROM user_reports r
         JOIN users u1 ON r.from_user_id = u1.id
         JOIN users u2 ON r.reported_user_id = u2.id
         ORDER BY r.id DESC",
    )?;
    let reports: Vec<Value> = r_stmt
        .query_map([], |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "reporter": r.get::<_, String>(1)?,
                "reported": r.get::<_, String>(2)?,
                "post_id": r.get::<_, Option<i64>>(3)?,
                "report_type": r.get::<_, String>(4)?,
                "details": r.get::<_, String>(5)?,
                "status": r.get::<_, String>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    let mut v_stmt = conn.prepare(
        "SELECT v.id, v.user_id, u.name, u.username, v.status, v.files, v.rejection_reason, v.created_at
         FROM user_verifies v
         JOIN users u ON v.user_id = u.id
         ORDER BY v.id DESC",
    )?;
    let verifications: Vec<Value> = v_stmt
        .query_map([], |r| {
            let files_raw: String = r.get(5)?;
            let files_parsed: Value = serde_json::from_str(&files_raw).unwrap_or_else(|_| json!([]));
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "user_id": r.get::<_, i64>(1)?,
                "user_name": r.get::<_, String>(2)?,
                "user_username": r.get::<_, String>(3)?,
                "status": r.get::<_, String>(4)?,
                "files": files_parsed,
                "rejection_reason": r.get::<_, Option<String>>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    Ok(Json(json!({
        "success": true,
        "metrics": {
            "total_users": total_users,
            "total_posts": total_posts,
            "total_subscriptions": total_subscriptions,
            "total_revenue": total_revenue,
        },
        "users": users,
        "withdrawals": withdrawals,
        "reports": reports,
        "verifications": verifications
    })))
}

#[derive(Deserialize)]
pub struct AdminWithdrawalAction {
    pub action: String, // "approve" or "reject"
}

pub async fn handle_admin_withdrawal(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(withdrawal_id): Path<i64>,
    Json(payload): Json<AdminWithdrawalAction>,
) -> Result<impl IntoResponse, AppError> {
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.db.lock().unwrap();
    let (uid, amount, current_status): (i64, f64, String) = conn.query_row(
        "SELECT user_id, amount, status FROM withdrawals WHERE id = ?1",
        params![withdrawal_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
    )?;

    if current_status != "requested" {
        return Err(AppError::BadRequest("Withdrawal already processed.".into()));
    }

    if payload.action == "approve" {
        conn.execute(
            "UPDATE withdrawals SET status = 'approved' WHERE id = ?1",
            params![withdrawal_id],
        )?;
    } else {
        conn.execute(
            "UPDATE withdrawals SET status = 'rejected' WHERE id = ?1",
            params![withdrawal_id],
        )?;
        // Refund wallet
        conn.execute(
            "UPDATE wallets SET total = total + ?1 WHERE user_id = ?2",
            params![amount, uid],
        )?;
    }

    Ok(Json(json!({
        "success": true,
        "status": if payload.action == "approve" { "approved" } else { "rejected" }
    })))
}

#[derive(Deserialize)]
pub struct AdminReportAction {
    pub action: String, // "resolve" or "dismiss"
}

pub async fn handle_admin_report(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(report_id): Path<i64>,
    Json(payload): Json<AdminReportAction>,
) -> Result<impl IntoResponse, AppError> {
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.db.lock().unwrap();
    let new_status = if payload.action == "dismiss" {
        "dismissed"
    } else {
        "resolved"
    };
    conn.execute(
        "UPDATE user_reports SET status = ?1 WHERE id = ?2",
        params![new_status, report_id],
    )?;

    Ok(Json(json!({
        "success": true,
        "status": new_status
    })))
}

#[derive(Deserialize)]
pub struct AdminVerifyAction {
    pub action: String, // "approve" or "reject"
    pub rejection_reason: Option<String>,
}

pub async fn handle_admin_verification(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(verify_id): Path<i64>,
    Json(payload): Json<AdminVerifyAction>,
) -> Result<impl IntoResponse, AppError> {
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.db.lock().unwrap();
    let uid: i64 = conn.query_row(
        "SELECT user_id FROM user_verifies WHERE id = ?1",
        params![verify_id],
        |r| r.get(0),
    )?;

    if payload.action == "approve" {
        conn.execute(
            "UPDATE user_verifies SET status = 'verified', rejection_reason = NULL WHERE id = ?1",
            params![verify_id],
        )?;
        conn.execute(
            "UPDATE users SET identity_verified_at = datetime('now') WHERE id = ?1",
            params![uid],
        )?;
    } else {
        conn.execute(
            "UPDATE user_verifies SET status = 'rejected', rejection_reason = ?1 WHERE id = ?2",
            params![payload.rejection_reason, verify_id],
        )?;
        conn.execute(
            "UPDATE users SET identity_verified_at = NULL WHERE id = ?1",
            params![uid],
        )?;
    }

    Ok(Json(json!({
        "success": true,
        "status": if payload.action == "approve" { "verified" } else { "rejected" }
    })))
}


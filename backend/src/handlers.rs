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
    db::{build_post_view, get_user_public_profile, is_blocked_between, round_money, AppState},
    models::*,
};

// ============================================================================
// Health & Public Home
// ============================================================================

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

// ============================================================================
// Authentication
// ============================================================================

#[derive(Deserialize)]
pub struct RegisterPayload {
    pub name: String,
    pub username: Option<String>,
    pub email: String,
    pub password: String,
    pub referral_code: Option<String>,
}

pub fn normalize_db_datetime(input: Option<String>) -> Option<String> {
    let raw = input?.trim().replace('T', " ");
    if raw.is_empty() {
        return None;
    }
    if raw.len() == 10 {
        Some(format!("{raw} 00:00:00"))
    } else if raw.len() == 16 {
        Some(format!("{raw}:00"))
    } else {
        Some(raw)
    }
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

    let mut conn = state.lock_db();

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

    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
    tx.execute(
        "INSERT INTO users (name, username, email, password_hash, role_id, email_verified_at, referral_code)
         VALUES (?1, ?2, ?3, ?4, 2, datetime('now'), ?5)",
        params![name, username, email, password_hash, referral_code],
    )?;
    let user_id = tx.last_insert_rowid();

    tx.execute(
        "INSERT INTO wallets (user_id, total) VALUES (?1, 100.0)",
        params![user_id],
    )?;

    if let Some(ref_code) = payload.referral_code.as_deref() {
        let clean_ref = ref_code.trim();
        if !clean_ref.is_empty() {
            if let Ok(referrer_id) = tx.query_row::<i64, _, _>(
                "SELECT id FROM users WHERE UPPER(referral_code) = UPPER(?1) AND id != ?2",
                params![clean_ref, user_id],
                |r| r.get(0),
            ) {
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + 5.0, 2), updated_at = datetime('now') WHERE user_id = ?1",
                    params![referrer_id],
                )?;
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + 5.0, 2), updated_at = datetime('now') WHERE user_id = ?1",
                    params![user_id],
                )?;
                let _ = tx.execute(
                    "INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, bonus_amount)
                     VALUES (?1, ?2, ?3, 5.0)",
                    params![referrer_id, user_id, clean_ref.to_uppercase()],
                );
                let _ = tx.execute(
                    "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
                     VALUES (?1, ?2, ?3, 'tip', ?4)",
                    params![
                        Uuid::new_v4().to_string(),
                        user_id,
                        referrer_id,
                        format!("@{} joined using your referral link! You earned a $5.00 bonus.", username)
                    ],
                );
            }
        }
    }

    for (lname, ltype) in [
        ("Followers", "followers"),
        ("Following", "following"),
        ("Blocked", "blocked"),
    ] {
        tx.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, ?2, ?3)",
            params![user_id, lname, ltype],
        )?;
    }
    tx.commit()?;

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

    let conn = state.lock_db();

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
    let conn = state.lock_db();
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

    let active_streams_count: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM streams WHERE status = 'in-progress' AND is_public = 1",
            [],
            |r| r.get(0),
        )
        .unwrap_or(0);

    Ok(Json(json!({
        "success": true,
        "user": profile,
        "unread_notifications": unread_notifications,
        "unread_messages": unread_messages,
        "active_streams_count": active_streams_count
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

// ============================================================================
// Posts CRUD, Reactions, Comments, Bookmarks, Polls
// ============================================================================

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
            // Preserve existing poll and votes if answers are identical when editing
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
    auth.require_scope("write")?;
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
    auth.require_scope("engage")?;
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
            // Notify post owner
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
    auth.require_scope("engage")?;
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
    auth.require_scope("write")?;
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
    auth.require_scope("engage")?;
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

    Ok(Json(json!({
        "success": true,
        "comment": comment
    })))
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
    Ok(Json(json!({
        "success": true,
        "message": msg
    })))
}

pub async fn delete_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
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
    auth.require_scope("engage")?;
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

pub fn compute_reel_unlocked(
    conn: &rusqlite::Connection,
    reel_id: i64,
    owner_id: i64,
    price: f64,
    viewer_id: Option<i64>,
) -> bool {
    if price <= 0.0 {
        return true;
    }
    if let Some(vid) = viewer_id {
        if vid == owner_id {
            return true;
        }
        let role: i64 = conn
            .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
            .unwrap_or(2);
        if role == 1 {
            return true;
        }
        conn.query_row(
            "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND reel_id = ?2 AND tx_type = 'reel-unlock' AND status = 'approved'",
            params![vid, reel_id],
            |r| r.get::<_, i64>(0),
        )
        .unwrap_or(0)
            > 0
    } else {
        false
    }
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

// ============================================================================
// Bookmarks & Lists
// ============================================================================

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
        return Ok(Json(json!({
            "success": true,
            "list_id": existing_id
        })));
    }
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

pub async fn rename_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
    Json(payload): Json<SaveListPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
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
    Ok(Json(json!({
        "success": true,
        "name": name
    })))
}

pub async fn clear_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
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
        // Also remove auth.user_id from targets' followers lists
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
    Ok(Json(json!({ "success": true })))
}

pub async fn delete_list(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(list_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let conn = state.lock_db();
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
    auth.require_scope("engage")?;
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
    auth.require_scope("engage")?;
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

    Ok(Json(json!({
        "success": true,
        "is_following": is_following
    })))
}

pub async fn toggle_block_user(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<FollowPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
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
        // Also remove mutual follow relationships when blocking
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

    Ok(Json(json!({
        "success": true,
        "is_blocked": is_blocked
    })))
}

// ============================================================================
// Messenger
// ============================================================================

pub async fn get_messenger_contacts(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();
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
        if is_blocked_between(&conn, auth.user_id, cid) {
            continue;
        }
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

fn load_message_attachments(
    conn: &rusqlite::Connection,
    message_id: i64,
    is_unlocked: bool,
) -> Vec<Attachment> {
    let Ok(mut att_stmt) = conn.prepare(
        "SELECT id, user_id, post_id, message_id, filename, file_url, file_type, file_size, is_vault, is_archived, created_at
         FROM attachments WHERE message_id = ?1 ORDER BY created_at ASC",
    ) else {
        return Vec::new();
    };
    att_stmt
        .query_map(params![message_id], |arow| {
            let raw_url: String = arow.get(5)?;
            Ok(Attachment {
                id: arow.get(0)?,
                user_id: arow.get(1)?,
                post_id: arow.get(2)?,
                message_id: arow.get(3)?,
                filename: arow.get(4)?,
                file_url: if is_unlocked {
                    raw_url
                } else {
                    "/img/post-locked.svg".to_string()
                },
                file_type: arow.get(6)?,
                file_size: arow.get(7)?,
                is_vault: arow.get::<_, i64>(8)? != 0,
                is_archived: arow.get::<_, i64>(9)? != 0,
                created_at: arow.get(10)?,
            })
        })
        .map(|rows| rows.filter_map(Result::ok).collect())
        .unwrap_or_default()
}

pub async fn get_messages_with_user(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(other_user_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();

    if is_blocked_between(&conn, auth.user_id, other_user_id) {
        return Err(AppError::Forbidden(
            "Conversation unavailable because one of you has blocked the other.".into(),
        ));
    }

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

            let attachments = load_message_attachments(&conn, mid, is_unlocked);

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
                attachments,
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
    pub attachments: Option<Vec<String>>,
}

pub async fn send_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SendMessagePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    if payload.receiver_id == auth.user_id {
        return Err(AppError::Unprocessable("Cannot send a message to yourself.".into()));
    }
    let msg = payload.message.trim();
    let attachment_inputs = payload.attachments.clone().unwrap_or_default();
    if msg.is_empty() && attachment_inputs.is_empty() {
        return Err(AppError::Unprocessable(
            "Message must contain text or at least one attachment.".into(),
        ));
    }
    let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
    let conn = state.lock_db();

    if is_blocked_between(&conn, auth.user_id, payload.receiver_id) {
        return Err(AppError::Forbidden(
            "Cannot send messages to this user due to block settings.".into(),
        ));
    }

    let final_msg = if msg.is_empty() {
        "📎 Sent media attachment".to_string()
    } else {
        msg.to_string()
    };

    conn.execute(
        "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
        params![auth.user_id, payload.receiver_id, final_msg, price],
    )?;
    let mid = conn.last_insert_rowid();

    for item in attachment_inputs {
        let updated = conn.execute(
            "UPDATE attachments SET message_id = ?1 WHERE (id = ?2 OR file_url = ?2) AND user_id = ?3",
            params![mid, item, auth.user_id],
        )?;
        if updated == 0 && (item.starts_with('/') || item.starts_with("http")) {
            let new_att_id = Uuid::new_v4().to_string();
            let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                "video"
            } else {
                "image"
            };
            conn.execute(
                "INSERT INTO attachments (id, user_id, message_id, filename, file_url, file_type, file_size)
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1024)",
                params![new_att_id, auth.user_id, mid, "message-media", item, ftype],
            )?;
        }
    }

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
    let attachments = load_message_attachments(&conn, mid, true);

    Ok(Json(json!({
        "success": true,
        "message": MessageView {
            id: mid,
            sender_id: auth.user_id,
            receiver_id: payload.receiver_id,
            sender_name: sender.name,
            sender_username: sender.username,
            sender_avatar: sender.avatar,
            message: final_msg,
            price,
            is_unlocked: true,
            is_seen: false,
            is_pinned: false,
            attachments,
            created_at: "Just now".into(),
        }
    })))
}

pub async fn toggle_pin_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(message_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();
    let (sender_id, receiver_id, current_pin): (i64, i64, i64) = conn.query_row(
        "SELECT sender_id, receiver_id, is_pinned FROM user_messages WHERE id = ?1",
        params![message_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
    )?;
    if sender_id != auth.user_id && receiver_id != auth.user_id {
        return Err(AppError::Forbidden("Not a participant in this conversation.".into()));
    }
    let next_pin = if current_pin == 0 { 1 } else { 0 };
    conn.execute(
        "UPDATE user_messages SET is_pinned = ?1 WHERE id = ?2",
        params![next_pin, message_id],
    )?;
    Ok(Json(json!({
        "success": true,
        "is_pinned": next_pin != 0
    })))
}

pub async fn delete_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(message_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let conn = state.lock_db();
    let sender_id: i64 = conn.query_row(
        "SELECT sender_id FROM user_messages WHERE id = ?1",
        params![message_id],
        |r| r.get(0),
    )?;
    if sender_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Only the sender can delete this message.".into()));
    }
    let was_purchased: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM transactions WHERE message_id = ?1 AND tx_type = 'message-unlock' AND status = 'approved'",
            params![message_id],
            |r| r.get(0),
        )
        .unwrap_or(0);
    if was_purchased > 0 {
        return Err(AppError::Unprocessable(
            "Cannot delete a Pay-Per-View message that has already been purchased by the recipient.".into(),
        ));
    }
    conn.execute("UPDATE attachments SET message_id = NULL WHERE message_id = ?1", params![message_id])?;
    conn.execute(
        "DELETE FROM user_messages WHERE id = ?1",
        params![message_id],
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

// ============================================================================
// Payments, Subscriptions, Tips, PPV Unlocks, Wallet & Withdrawals
// ============================================================================

#[derive(Deserialize)]
pub struct InitiatePaymentPayload {
    pub payment_type: String, // "deposit", "subscription", "tip", "post-unlock", "message-unlock", "stream-unlock", "reel-unlock"
    pub recipient_user_id: Option<i64>,
    pub post_id: Option<i64>,
    pub message_id: Option<i64>,
    pub stream_id: Option<i64>,
    pub reel_id: Option<i64>,
    pub amount: Option<f64>,
    pub provider: Option<String>, // "credit", "stripe", "paypal"
    pub months: Option<i64>,
}

pub async fn initiate_payment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<InitiatePaymentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("wallet")?;
    let mut conn = state.lock_db();
    let raw_provider = payload
        .provider
        .unwrap_or_else(|| "credit".into())
        .trim()
        .to_lowercase();
    let provider = match raw_provider.as_str() {
        "wallet" | "credit" | "" => "credit".to_string(),
        "stripe" | "paypal" | "crypto" | "ccbill" => raw_provider,
        other => {
            return Err(AppError::BadRequest(format!(
                "Unsupported payment provider: {other}"
            )))
        }
    };
    let ptype = payload.payment_type.trim();

    if ptype == "deposit" {
        let amt = round_money(payload.amount.unwrap_or(0.0));
        if amt <= 0.0 || !amt.is_finite() {
            return Err(AppError::Unprocessable("Deposit amount must be positive.".into()));
        }
        let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
        let next_inv: i64 = tx
            .query_row(
                "SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions",
                [],
                |r| r.get(0),
            )
            .unwrap_or(1001);
        tx.execute(
            "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
            params![amt, auth.user_id],
        )?;
        tx.execute(
            "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, invoice_id)
             VALUES (?1, ?1, 'deposit', 'approved', ?2, ?3, ?4)",
            params![auth.user_id, amt, provider, next_inv],
        )?;
        let new_balance: f64 = tx.query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )?;
        tx.commit()?;
        return Ok(Json(json!({
            "success": true,
            "message": format!("${:.2} deposited into your wallet!", amt),
            "wallet_balance": round_money(new_balance)
        })));
    }

    // Determine price and recipient
    let (raw_amount, recipient_id) = match ptype {
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
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("You already own this post.".into()));
            }
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
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("You sent this message.".into()));
            }
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
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("You own this stream.".into()));
            }
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
        "reel-unlock" => {
            let rid = payload
                .reel_id
                .ok_or_else(|| AppError::BadRequest("reel_id required".into()))?;
            let (rec_id, price): (i64, f64) = conn.query_row(
                "SELECT user_id, price FROM reels WHERE id = ?1",
                params![rid],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )?;
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("You own this reel.".into()));
            }
            let already_unlocked: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND reel_id = ?2 AND tx_type = 'reel-unlock' AND status = 'approved'",
                    params![auth.user_id, rid],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            if already_unlocked > 0 {
                return Err(AppError::Unprocessable("Reel is already unlocked.".into()));
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
            if rec_id == auth.user_id {
                return Err(AppError::Unprocessable("Cannot tip yourself.".into()));
            }
            (amt, rec_id)
        }
        _ => {
            return Err(AppError::BadRequest(format!(
                "Unsupported payment_type: {ptype}"
            )))
        }
    };

    if is_blocked_between(&conn, auth.user_id, recipient_id) {
        return Err(AppError::Forbidden("Transaction unavailable due to block settings.".into()));
    }

    let amount = round_money(raw_amount);

    // Enforce Autonomous Agent spend guardrails if authenticated via MCP Agent Token
    if let Some(tid) = auth.agent_token_id {
        if let Some(max_tx) = auth.max_spend_per_tx {
            if amount > max_tx {
                return Err(AppError::Unprocessable(format!(
                    "Autonomous agent payment (${amount:.2}) exceeds per-transaction spend cap (${max_tx:.2})."
                )));
            }
        }
        if let Some(daily_lim) = auth.daily_spend_limit {
            let spent_today: f64 = conn
                .query_row(
                    "SELECT COALESCE(SUM(amount_spent), 0.0) FROM agent_audit_logs
                     WHERE agent_token_id = ?1 AND status = 'success' AND created_at >= datetime('now', '-24 hours')",
                    params![tid],
                    |r| r.get(0),
                )
                .unwrap_or(0.0);
            if spent_today + amount > daily_lim {
                return Err(AppError::Unprocessable(format!(
                    "Autonomous agent payment (${amount:.2}) would exceed 24h daily spend cap (${daily_lim:.2}; ${spent_today:.2} already spent)."
                )));
            }
        }
    }

    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;

    // Deduct from wallet if using credit
    if provider == "credit" && amount > 0.0 {
        let sender_balance: f64 = tx
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
        tx.execute(
            "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
            params![amount, auth.user_id],
        )?;
    }

    // Credit recipient wallet (minus 15% platform fee)
    if amount > 0.0 {
        let creator_share = round_money(amount * 0.85);
        tx.execute(
            "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
            params![creator_share, recipient_id],
        )?;
    }

    // Record transaction with generated invoice_id
    let next_invoice_id: i64 = tx
        .query_row(
            "SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions",
            [],
            |r| r.get(0),
        )
        .unwrap_or(1001);

    tx.execute(
        "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, post_id, message_id, stream_id, reel_id, invoice_id)
         VALUES (?1, ?2, ?3, 'approved', ?4, ?5, ?6, ?7, ?8, ?9, ?10)",
        params![
            auth.user_id,
            recipient_id,
            ptype,
            amount,
            provider,
            payload.post_id,
            payload.message_id,
            payload.stream_id,
            payload.reel_id,
            next_invoice_id
        ],
    )?;

    if ptype == "subscription" {
        let days = payload.months.unwrap_or(1) * 30;
        tx.execute(
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

    if let Some(tid) = auth.agent_token_id {
        let _ = tx.execute(
            "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, details)
             VALUES (?1, ?2, ?3, 'payment', ?4, 'success', ?5, ?6)",
            params![
                auth.user_id,
                tid,
                format!("payment.{ptype}"),
                next_invoice_id.to_string(),
                amount,
                json!({ "payment_type": ptype, "recipient_user_id": recipient_id, "amount": amount }).to_string()
            ],
        );
    }

    // Send notification to creator
    if recipient_id != auth.user_id {
        let notif_msg = match ptype {
            "subscription" => format!("@{} subscribed to your profile for ${amount:.2}", auth.username),
            "tip" => format!("@{} sent you a ${amount:.2} tip!", auth.username),
            "post-unlock" => format!("@{} unlocked your PPV post for ${amount:.2}", auth.username),
            "message-unlock" => format!("@{} unlocked your PPV message for ${amount:.2}", auth.username),
            "reel-unlock" => format!("@{} unlocked your Reel for ${amount:.2}", auth.username),
            _ => format!("@{} sent a ${amount:.2} payment", auth.username),
        };
        let _ = tx.execute(
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

    let updated_balance: f64 = tx
        .query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    tx.commit()?;

    Ok(Json(json!({
        "success": true,
        "message": "Payment processed successfully!",
        "invoice_id": next_invoice_id,
        "amount": amount,
        "wallet_balance": round_money(updated_balance)
    })))
}

pub async fn cancel_subscription(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(sub_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("wallet")?;
    let conn = state.lock_db();
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
    auth.require_scope("wallet")?;
    let amount = round_money(payload.amount);
    if amount < 20.0 || !amount.is_finite() {
        return Err(AppError::Unprocessable(
            "Minimum withdrawal amount is $20.00.".into(),
        ));
    }
    let mut conn = state.lock_db();
    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
    let balance: f64 = tx
        .query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    if balance < amount {
        return Err(AppError::Unprocessable(
            "Requested amount exceeds available wallet balance.".into(),
        ));
    }

    tx.execute(
        "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
        params![amount, auth.user_id],
    )?;

    tx.execute(
        "INSERT INTO withdrawals (user_id, amount, status, payment_method, payment_identifier, message)
         VALUES (?1, ?2, 'requested', ?3, ?4, ?5)",
        params![
            auth.user_id,
            amount,
            payload.payment_method.unwrap_or_else(|| "Bank Transfer".into()),
            payload.payment_identifier.unwrap_or_default(),
            payload.message.unwrap_or_default()
        ],
    )?;

    let new_balance: f64 = tx.query_row(
        "SELECT total FROM wallets WHERE user_id = ?1",
        params![auth.user_id],
        |r| r.get(0),
    )?;
    tx.commit()?;

    Ok(Json(json!({
        "success": true,
        "message": "Withdrawal request submitted for review.",
        "wallet_balance": round_money(new_balance)
    })))
}

// ============================================================================
// Settings (Profile, Account, Rates, Wallet, Payments, Subscriptions, Privacy, Verify)
// ============================================================================

pub async fn get_settings_data(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
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

    let billing: Option<Value> = conn
        .query_row(
            "SELECT first_name, last_name, billing_address, city, country, state, postcode FROM users WHERE id = ?1",
            params![auth.user_id],
            |r| {
                Ok(json!({
                    "first_name": r.get::<_, Option<String>>(0)?.unwrap_or_default(),
                    "last_name": r.get::<_, Option<String>>(1)?.unwrap_or_default(),
                    "billing_address": r.get::<_, Option<String>>(2)?.unwrap_or_default(),
                    "city": r.get::<_, Option<String>>(3)?.unwrap_or_default(),
                    "country": r.get::<_, Option<String>>(4)?.unwrap_or_default(),
                    "state": r.get::<_, Option<String>>(5)?.unwrap_or_default(),
                    "postcode": r.get::<_, Option<String>>(6)?.unwrap_or_default(),
                }))
            },
        )
        .ok();

    let mut ref_stmt = conn.prepare(
        "SELECT r.id, r.referred_user_id, u.name, u.username, u.avatar, r.referral_code, r.bonus_amount, r.created_at
         FROM referrals r
         JOIN users u ON r.referred_user_id = u.id
         WHERE r.referrer_user_id = ?1
         ORDER BY r.id DESC",
    )?;
    let referrals: Vec<Value> = ref_stmt
        .query_map(params![auth.user_id], |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "used_by_user_id": r.get::<_, i64>(1)?,
                "used_by_name": r.get::<_, String>(2)?,
                "used_by_username": r.get::<_, String>(3)?,
                "used_by_avatar": r.get::<_, String>(4)?,
                "referral_code": r.get::<_, String>(5)?,
                "bonus_amount": r.get::<_, f64>(6)?,
                "created_at": r.get::<_, String>(7)?,
            }))
        })?
        .filter_map(Result::ok)
        .collect();

    let agent_tokens = list_user_agent_tokens(&conn, auth.user_id)?;
    let agent_audit_logs = list_user_agent_audit_logs(&conn, auth.user_id, 50)?;

    Ok(Json(json!({
        "success": true,
        "profile": profile,
        "subscriptions": subscriptions,
        "transactions": transactions,
        "withdrawals": withdrawals,
        "verification": verification,
        "billing": billing,
        "referrals": referrals,
        "agent_tokens": agent_tokens,
        "agent_audit_logs": agent_audit_logs
    })))
}

pub fn list_user_agent_tokens(
    conn: &rusqlite::Connection,
    user_id: i64,
) -> Result<Vec<AgentTokenView>, rusqlite::Error> {
    let mut stmt = conn.prepare(
        "SELECT id, user_id, name, token_prefix, scopes, max_spend_per_tx, daily_spend_limit, is_active, last_used_at, expires_at, created_at
         FROM agent_tokens WHERE user_id = ?1 ORDER BY id DESC",
    )?;
    let rows = stmt
        .query_map(params![user_id], |r| {
            let tid: i64 = r.get(0)?;
            let scopes_raw: String = r.get(4)?;
            let scopes: Vec<String> = serde_json::from_str(&scopes_raw).unwrap_or_default();
            let spent_today: f64 = conn
                .query_row(
                    "SELECT COALESCE(SUM(amount_spent), 0.0) FROM agent_audit_logs
                     WHERE agent_token_id = ?1 AND status = 'success' AND created_at >= datetime('now', '-24 hours')",
                    params![tid],
                    |sr| sr.get(0),
                )
                .unwrap_or(0.0);
            let rounded_spent = round_money(spent_today);
            Ok(AgentTokenView {
                id: tid,
                user_id: r.get(1)?,
                name: r.get(2)?,
                token_prefix: r.get(3)?,
                scopes,
                max_spend_per_tx: r.get(5)?,
                daily_spend_limit: r.get(6)?,
                spent_today: rounded_spent,
                spent_last_24h: rounded_spent,
                is_active: r.get::<_, i64>(7)? != 0,
                last_used_at: r.get(8)?,
                expires_at: r.get(9)?,
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();
    Ok(rows)
}

pub fn list_user_agent_audit_logs(
    conn: &rusqlite::Connection,
    user_id: i64,
    limit: i64,
) -> Result<Vec<AgentAuditLogView>, rusqlite::Error> {
    let mut stmt = conn.prepare(
        "SELECT l.id, l.user_id, l.agent_token_id, t.name, l.tool_name, l.resource_type, l.resource_id, l.status, l.amount_spent, l.details, l.created_at
         FROM agent_audit_logs l
         LEFT JOIN agent_tokens t ON l.agent_token_id = t.id
         WHERE l.user_id = ?1
         ORDER BY l.id DESC LIMIT ?2",
    )?;
    let rows = stmt
        .query_map(params![user_id, limit], |r| {
            Ok(AgentAuditLogView {
                id: r.get(0)?,
                user_id: r.get(1)?,
                agent_token_id: r.get(2)?,
                agent_name: r.get(3)?,
                tool_name: r.get(4)?,
                resource_type: r.get(5)?,
                resource_id: r.get(6)?,
                status: r.get(7)?,
                amount_spent: r.get(8)?,
                details: r.get(9)?,
                created_at: r.get(10)?,
            })
        })?
        .filter_map(Result::ok)
        .collect();
    Ok(rows)
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
    auth.require_scope("write")?;
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
    let conn = state.lock_db();
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
    auth.require_scope("write")?;
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

    let conn = state.lock_db();
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
    pub offer_discount_percent: Option<i64>,
    pub offer_expires_at: Option<String>,
}

pub async fn update_rates_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateRatesPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("monetize")?;
    let conn = state.lock_db();
    let p1 = round_money(payload.profile_access_price.max(0.0));
    let p3 = round_money(payload.profile_access_price_3_months.max(0.0));
    let p6 = round_money(payload.profile_access_price_6_months.max(0.0));
    let p12 = round_money(payload.profile_access_price_12_months.max(0.0));

    let (old1, old3, old6, old12): (f64, f64, f64, f64) = conn
        .query_row(
            "SELECT profile_access_price, profile_access_price_3_months, profile_access_price_6_months, profile_access_price_12_months
             FROM users WHERE id = ?1",
            params![auth.user_id],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
        )
        .unwrap_or((p1, p3, p6, p12));

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
            p1,
            p3,
            p6,
            p12,
            auth.user_id
        ],
    )?;

    if let Some(exp) = normalize_db_datetime(payload.offer_expires_at) {
        let disc = payload.offer_discount_percent.unwrap_or(20).clamp(5, 90);
        conn.execute(
            "INSERT INTO creator_offers (user_id, old_profile_access_price, old_profile_access_price_3_months, old_profile_access_price_6_months, old_profile_access_price_12_months, discount_percent, expires_at)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7)
             ON CONFLICT(user_id) DO UPDATE SET
                old_profile_access_price = excluded.old_profile_access_price,
                old_profile_access_price_3_months = excluded.old_profile_access_price_3_months,
                old_profile_access_price_6_months = excluded.old_profile_access_price_6_months,
                old_profile_access_price_12_months = excluded.old_profile_access_price_12_months,
                discount_percent = excluded.discount_percent,
                expires_at = excluded.expires_at",
            params![auth.user_id, old1.max(p1), old3.max(p3), old6.max(p6), old12.max(p12), disc, exp],
        )?;
    }

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
    pub first_name: Option<String>,
    pub last_name: Option<String>,
    pub billing_address: Option<String>,
    pub city: Option<String>,
    pub country: Option<String>,
    pub state: Option<String>,
    pub postcode: Option<String>,
}

pub async fn update_account_settings(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpdateAccountPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("admin")?;
    let conn = state.lock_db();
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
    if payload.first_name.is_some()
        || payload.last_name.is_some()
        || payload.billing_address.is_some()
        || payload.city.is_some()
        || payload.country.is_some()
        || payload.state.is_some()
        || payload.postcode.is_some()
    {
        conn.execute(
            "UPDATE users SET
                first_name = COALESCE(?1, first_name),
                last_name = COALESCE(?2, last_name),
                billing_address = COALESCE(?3, billing_address),
                city = COALESCE(?4, city),
                country = COALESCE(?5, country),
                state = COALESCE(?6, state),
                postcode = COALESCE(?7, postcode)
             WHERE id = ?8",
            params![
                payload.first_name.map(|s| s.trim().to_string()),
                payload.last_name.map(|s| s.trim().to_string()),
                payload.billing_address.map(|s| s.trim().to_string()),
                payload.city.map(|s| s.trim().to_string()),
                payload.country.map(|s| s.trim().to_string()),
                payload.state.map(|s| s.trim().to_string()),
                payload.postcode.map(|s| s.trim().to_string()),
                auth.user_id
            ],
        )?;
    }
    Ok(Json(json!({
        "success": true,
        "message": "Account security & billing settings updated."
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
    auth.require_scope("write")?;
    let conn = state.lock_db();
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
    auth.require_scope("read")?;
    let conn = state.lock_db();
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
    auth.require_scope("write")?;
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

        let conn = state.lock_db();
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

pub async fn toggle_archive_vault_item(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(att_id): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let (owner_id, current_arch): (i64, i64) = conn.query_row(
        "SELECT user_id, is_archived FROM attachments WHERE id = ?1",
        params![att_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not your vault item.".into()));
    }
    let next_arch = if current_arch == 0 { 1 } else { 0 };
    conn.execute(
        "UPDATE attachments SET is_archived = ?1 WHERE id = ?2",
        params![next_arch, att_id],
    )?;
    Ok(Json(json!({
        "success": true,
        "is_archived": next_arch != 0
    })))
}

pub async fn delete_vault_item(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(att_id): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let (owner_id, post_id, message_id): (i64, Option<i64>, Option<i64>) = conn.query_row(
        "SELECT user_id, post_id, message_id FROM attachments WHERE id = ?1",
        params![att_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not your vault item.".into()));
    }
    if post_id.is_some() || message_id.is_some() {
        return Err(AppError::Unprocessable(
            "This file is currently attached to a published post or message. Archive it instead or remove the post first.".into(),
        ));
    }
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
    let conn = state.lock_db();
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
            let owner_id: i64 = r.get(1)?;
            let ver: Option<String> = r.get(5)?;
            let price: f64 = r.get(9)?;
            let is_unlocked = compute_reel_unlocked(&conn, rid, owner_id, price, viewer_id);
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
                user_bookmarked,
                created_at: r.get(11)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|rv| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, rv.user_id)
            } else {
                true
            }
        })
        .collect();

    Ok(Json(json!({
        "success": true,
        "reels": reels
    })))
}

#[derive(Deserialize)]
pub struct CreateReelPayload {
    pub caption: String,
    #[serde(alias = "video_path")]
    pub video_url: Option<String>,
    #[serde(alias = "cover_path")]
    pub cover_url: Option<String>,
    pub price: Option<f64>,
}

pub async fn create_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateReelPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let vurl = payload
        .video_url
        .filter(|s| !s.trim().is_empty())
        .unwrap_or_else(|| "/img/video-loading-spinner.mp4".into());
    let curl = payload
        .cover_url
        .filter(|s| !s.trim().is_empty())
        .unwrap_or_else(|| "/img/home-scene-1.svg".into());
    let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
    conn.execute(
        "INSERT INTO reels (user_id, caption, video_url, cover_url, price) VALUES (?1, ?2, ?3, ?4, ?5)",
        params![auth.user_id, payload.caption.trim(), vurl, curl, price],
    )?;
    Ok(Json(json!({
        "success": true,
        "reel_id": conn.last_insert_rowid()
    })))
}

pub async fn increment_reel_view(
    State(state): State<AppState>,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    conn.execute(
        "UPDATE reels SET views_count = views_count + 1 WHERE id = ?1",
        params![reel_id],
    )?;
    let views_count: i64 = conn
        .query_row("SELECT views_count FROM reels WHERE id = ?1", params![reel_id], |r| r.get(0))
        .unwrap_or(0);
    Ok(Json(json!({
        "success": true,
        "views_count": views_count
    })))
}

pub async fn delete_reel(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(reel_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
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
    let conn = state.lock_db();
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
    auth.require_scope("engage")?;
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Comment cannot be empty.".into()));
    }
    let conn = state.lock_db();
    let (owner_id, price): (i64, f64) = conn.query_row(
        "SELECT user_id, price FROM reels WHERE id = ?1",
        params![payload.reel_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if is_blocked_between(&conn, auth.user_id, owner_id) {
        return Err(AppError::Forbidden("Cannot comment on this creator's reel.".into()));
    }
    if !compute_reel_unlocked(&conn, payload.reel_id, owner_id, price, Some(auth.user_id)) {
        return Err(AppError::Forbidden("Unlock this reel before commenting.".into()));
    }

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

pub async fn delete_reel_comment(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(comment_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let conn = state.lock_db();
    let (comment_user_id, reel_owner_id): (i64, i64) = conn.query_row(
        "SELECT rc.user_id, r.user_id FROM reel_comments rc JOIN reels r ON rc.reel_id = r.id WHERE rc.id = ?1",
        params![comment_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if comment_user_id != auth.user_id && reel_owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this comment.".into()));
    }
    conn.execute("DELETE FROM reel_comments WHERE id = ?1", params![comment_id])?;
    Ok(Json(json!({ "success": true })))
}

pub async fn get_stories(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
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
        .filter(|st| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, st.user_id)
            } else {
                true
            }
        })
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
    pub media_type: Option<String>,
    pub bg_preset: Option<String>,
    pub is_highlight: Option<bool>,
}

pub async fn create_story(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateStoryPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let murl = payload.media_url.unwrap_or_default().trim().to_string();
    let mtype = payload.media_type.unwrap_or_else(|| {
        if murl.ends_with(".mp4") || murl.ends_with(".webm") {
            "video".into()
        } else if murl.is_empty() {
            "text".into()
        } else {
            "image".into()
        }
    });
    let preset = payload
        .bg_preset
        .unwrap_or_else(|| "gradient-purple".into());
    let highlight = if payload.is_highlight.unwrap_or(false) {
        1
    } else {
        0
    };
    conn.execute(
        "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, expires_at)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, datetime('now', '+24 hours'))",
        params![auth.user_id, murl, mtype, payload.caption.trim(), preset, highlight],
    )?;
    Ok(Json(json!({
        "success": true,
        "story_id": conn.last_insert_rowid()
    })))
}

pub async fn increment_story_view(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    if let Some(a) = auth {
        let _ = conn.execute(
            "INSERT INTO story_views (story_id, viewer_user_id) VALUES (?1, ?2) ON CONFLICT(story_id, viewer_user_id) DO NOTHING",
            params![story_id, a.user_id],
        );
    }
    conn.execute(
        "UPDATE stories SET views_count = views_count + 1 WHERE id = ?1",
        params![story_id],
    )?;
    let views_count: i64 = conn
        .query_row("SELECT views_count FROM stories WHERE id = ?1", params![story_id], |r| r.get(0))
        .unwrap_or(0);
    Ok(Json(json!({
        "success": true,
        "views_count": views_count
    })))
}

pub async fn toggle_story_highlight(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let (owner_id, current_hl): (i64, i64) = conn.query_row(
        "SELECT user_id, is_highlight FROM stories WHERE id = ?1",
        params![story_id],
        |r| Ok((r.get(0)?, r.get(1)?)),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Only the story owner can toggle highlight.".into()));
    }
    let next_hl = if current_hl == 0 { 1 } else { 0 };
    conn.execute(
        "UPDATE stories SET is_highlight = ?1 WHERE id = ?2",
        params![next_hl, story_id],
    )?;
    Ok(Json(json!({
        "success": true,
        "is_highlight": next_hl != 0
    })))
}

pub async fn delete_story(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(story_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM stories WHERE id = ?1",
        params![story_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this story.".into()));
    }
    conn.execute("DELETE FROM stories WHERE id = ?1", params![story_id])?;
    Ok(Json(json!({ "success": true })))
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
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let viewer_is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

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
            let is_owner_or_admin = viewer_id == Some(owner_id) || viewer_is_admin;
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
                rtmp_server: if is_owner_or_admin { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner_or_admin { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        })?
        .filter_map(Result::ok)
        .filter(|sv| {
            if let Some(vid) = viewer_id {
                !is_blocked_between(&conn, vid, sv.user_id)
            } else {
                true
            }
        })
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
    auth.require_scope("write")?;
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
    let conn = state.lock_db();

    conn.execute(
        "INSERT INTO streams (user_id, name, slug, price, requires_subscription, is_public, status, rtmp_key)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, 'in-progress', ?7)",
        params![
            auth.user_id,
            name,
            slug,
            round_money(payload.price.unwrap_or(0.0).max(0.0)),
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
    auth.require_scope("write")?;
    let conn = state.lock_db();
    conn.execute(
        "UPDATE streams SET status = 'ended' WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![stream_id, auth.user_id, auth.role_id],
    )?;
    Ok(Json(json!({ "success": true })))
}

pub async fn delete_stream(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let conn = state.lock_db();
    let owner_id: i64 = conn.query_row(
        "SELECT user_id FROM streams WHERE id = ?1",
        params![stream_id],
        |r| r.get(0),
    )?;
    if owner_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden("Not authorized to delete this stream.".into()));
    }
    conn.execute("DELETE FROM streams WHERE id = ?1", params![stream_id])?;
    Ok(Json(json!({ "success": true })))
}

pub async fn get_stream_detail(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    Path(stream_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let viewer_id = auth.as_ref().map(|a| a.user_id);
    let viewer_is_admin = auth.as_ref().map(|a| a.role_id == 1).unwrap_or(false);

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
            let is_owner_or_admin = viewer_id == Some(owner_id) || viewer_is_admin;
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
                rtmp_server: if is_owner_or_admin { r.get(13)? } else { String::new() },
                rtmp_key: if is_owner_or_admin { r.get(14)? } else { String::new() },
                hls_link: if is_unlocked { r.get(15)? } else { String::new() },
                viewer_count: r.get(16)?,
                is_unlocked,
                created_at: r.get(17)?,
            })
        },
    )?;

    if let Some(vid) = viewer_id {
        if is_blocked_between(&conn, vid, stream.user_id) {
            return Err(AppError::Forbidden("Stream unavailable due to block settings.".into()));
        }
    }

    let messages: Vec<StreamMessageView> = if !stream.is_unlocked {
        Vec::new()
    } else {
        let mut m_stmt = conn.prepare(
            "SELECT sm.id, sm.stream_id, sm.user_id, u.name, u.username, u.avatar, sm.message, sm.created_at
             FROM stream_messages sm JOIN users u ON sm.user_id = u.id
             WHERE sm.stream_id = ?1 ORDER BY sm.id ASC",
        )?;
        let rows = m_stmt
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
        rows
    };

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
    auth.require_scope("engage")?;
    let msg = payload.message.trim();
    if msg.is_empty() {
        return Err(AppError::Unprocessable("Message cannot be empty.".into()));
    }
    let conn = state.lock_db();
    let (owner_id, price, req_sub): (i64, f64, bool) = conn.query_row(
        "SELECT user_id, price, requires_subscription FROM streams WHERE id = ?1",
        params![payload.stream_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get::<_, i64>(2)? != 0)),
    )?;
    if is_blocked_between(&conn, auth.user_id, owner_id) {
        return Err(AppError::Forbidden("Cannot chat in this stream.".into()));
    }
    if !compute_stream_unlocked(&conn, payload.stream_id, owner_id, price, req_sub, Some(auth.user_id)) {
        return Err(AppError::Forbidden("Unlock this live stream to participate in chat.".into()));
    }

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
    auth.require_scope("read")?;
    let conn = state.lock_db();
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
    let conn = state.lock_db();
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
    let conn = state.lock_db();
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
    auth.require_scope("engage")?;
    let conn = state.lock_db();
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
    auth: AuthUser,
    Json(payload): Json<AiSuggestionPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
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
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();

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
            "total_revenue": round_money(total_revenue),
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
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let mut conn = state.lock_db();
    let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
    let (uid, amount, current_status): (i64, f64, String) = tx.query_row(
        "SELECT user_id, amount, status FROM withdrawals WHERE id = ?1",
        params![withdrawal_id],
        |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
    )?;

    if current_status != "requested" {
        return Err(AppError::BadRequest("Withdrawal already processed.".into()));
    }

    if payload.action == "approve" {
        tx.execute(
            "UPDATE withdrawals SET status = 'approved' WHERE id = ?1",
            params![withdrawal_id],
        )?;
    } else {
        tx.execute(
            "UPDATE withdrawals SET status = 'rejected' WHERE id = ?1",
            params![withdrawal_id],
        )?;
        // Refund wallet
        tx.execute(
            "UPDATE wallets SET total = ROUND(total + ?1, 2) WHERE user_id = ?2",
            params![round_money(amount), uid],
        )?;
    }
    tx.commit()?;

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
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();
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
    auth.require_scope("admin")?;
    if auth.role_id != 1 {
        return Err(AppError::Forbidden("Admin privileges required.".into()));
    }
    let conn = state.lock_db();
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

// ============================================================================
// Broadcast Messaging, Creator Analytics & Agent Token Management
// ============================================================================

#[derive(Deserialize)]
pub struct BroadcastMessagePayload {
    pub list_id: Option<i64>,
    pub recipient_ids: Option<Vec<i64>>,
    #[serde(alias = "audience")]
    pub target: Option<String>, // "subscribers", "followers", "all", "list"
    pub message: String,
    pub price: Option<f64>,
    pub attachments: Option<Vec<String>>,
}

pub async fn broadcast_message(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<BroadcastMessagePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("messages")?;
    let msg = payload.message.trim();
    let attachment_inputs = payload.attachments.clone().unwrap_or_default();
    if msg.is_empty() && attachment_inputs.is_empty() {
        return Err(AppError::Unprocessable(
            "Broadcast message must contain text or at least one attachment.".into(),
        ));
    }
    let price = round_money(payload.price.unwrap_or(0.0).max(0.0));
    let conn = state.lock_db();

    let mut targets: Vec<i64> = Vec::new();
    if let Some(ref ids) = payload.recipient_ids {
        for &id in ids {
            if id != auth.user_id && !targets.contains(&id) {
                targets.push(id);
            }
        }
    }
    if let Some(lid) = payload.list_id {
        let mut stmt = conn.prepare(
            "SELECT ulm.user_id FROM user_list_members ulm
             JOIN user_lists ul ON ulm.list_id = ul.id
             WHERE ul.id = ?1 AND ul.user_id = ?2",
        )?;
        let rows: Vec<i64> = stmt
            .query_map(params![lid, auth.user_id], |r| r.get(0))?
            .filter_map(Result::ok)
            .collect();
        for id in rows {
            if id != auth.user_id && !targets.contains(&id) {
                targets.push(id);
            }
        }
    }
    if targets.is_empty() {
        let target_mode = payload.target.as_deref().unwrap_or("subscribers");
        if target_mode == "followers" || target_mode == "all" {
            let mut stmt = conn.prepare(
                "SELECT ulm.user_id FROM user_list_members ulm
                 JOIN user_lists ul ON ulm.list_id = ul.id
                 WHERE ul.user_id = ?1 AND ul.list_type = 'followers'",
            )?;
            let rows: Vec<i64> = stmt
                .query_map(params![auth.user_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            for id in rows {
                if id != auth.user_id && !targets.contains(&id) {
                    targets.push(id);
                }
            }
        }
        if target_mode != "followers" {
            let mut stmt = conn.prepare(
                "SELECT DISTINCT sender_user_id FROM subscriptions
                 WHERE recipient_user_id = ?1 AND status = 'completed' AND expires_at >= datetime('now')",
            )?;
            let rows: Vec<i64> = stmt
                .query_map(params![auth.user_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            for id in rows {
                if id != auth.user_id && !targets.contains(&id) {
                    targets.push(id);
                }
            }
        }
    }

    let final_msg = if msg.is_empty() {
        "📎 Sent broadcast media attachment".to_string()
    } else {
        msg.to_string()
    };

    let mut sent_ids = Vec::new();
    for receiver_id in targets {
        if is_blocked_between(&conn, auth.user_id, receiver_id) {
            continue;
        }
        conn.execute(
            "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
            params![auth.user_id, receiver_id, final_msg, price],
        )?;
        let mid = conn.last_insert_rowid();
        for item in &attachment_inputs {
            let new_att_id = Uuid::new_v4().to_string();
            let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                "video"
            } else {
                "image"
            };
            let _ = conn.execute(
                "INSERT INTO attachments (id, user_id, message_id, filename, file_url, file_type, file_size)
                 VALUES (?1, ?2, ?3, 'broadcast-media', ?4, ?5, 1024)",
                params![new_att_id, auth.user_id, mid, item, ftype],
            );
        }
        let _ = conn.execute(
            "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, message)
             VALUES (?1, ?2, ?3, 'message', ?4)",
            params![
                Uuid::new_v4().to_string(),
                auth.user_id,
                receiver_id,
                format!("@{} sent a broadcast message", auth.username)
            ],
        );
        sent_ids.push(mid);
    }

    Ok(Json(json!({
        "success": true,
        "sent_count": sent_ids.len(),
        "message_ids": sent_ids,
        "message": format!("Broadcast delivered to {} recipient(s).", sent_ids.len())
    })))
}

#[derive(Deserialize)]
pub struct CreateAgentTokenPayload {
    pub name: String,
    pub scopes: Option<Vec<String>>,
    pub max_spend_per_tx: Option<f64>,
    pub daily_spend_limit: Option<f64>,
    pub expires_in_days: Option<i64>,
}

pub async fn create_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<CreateAgentTokenPayload>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() {
        auth.require_scope("admin")?;
    }
    let name = payload.name.trim();
    if name.is_empty() {
        return Err(AppError::Unprocessable("Agent key name is required.".into()));
    }
    let scopes = payload.scopes.unwrap_or_else(|| {
        vec![
            "read".into(),
            "write".into(),
            "engage".into(),
            "monetize".into(),
            "messages".into(),
            "wallet".into(),
        ]
    });
    let scopes_json = serde_json::to_string(&scopes).unwrap_or_else(|_| "[]".into());
    let max_tx = round_money(payload.max_spend_per_tx.unwrap_or(50.0).max(0.0));
    let daily_lim = round_money(payload.daily_spend_limit.unwrap_or(200.0).max(0.0));

    let raw_secret = format!(
        "hp_mcp_{}_{}",
        auth.user_id,
        Uuid::new_v4().simple()
    );
    let prefix = format!("{}...", &raw_secret[..14.min(raw_secret.len())]);
    let token_hash = crate::auth::hash_agent_token(&raw_secret);

    let conn = state.lock_db();
    let expires_at: Option<String> = payload.expires_in_days.and_then(|days| {
        if days > 0 {
            conn.query_row(
                "SELECT datetime('now', ?1)",
                params![format!("+{days} days")],
                |r| r.get(0),
            )
            .ok()
        } else {
            None
        }
    });

    conn.execute(
        "INSERT INTO agent_tokens (user_id, name, token_prefix, token_hash, scopes, max_spend_per_tx, daily_spend_limit, is_active, expires_at)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, 1, ?8)",
        params![
            auth.user_id,
            name,
            prefix,
            token_hash,
            scopes_json,
            max_tx,
            daily_lim,
            expires_at
        ],
    )?;
    let tid = conn.last_insert_rowid();

    let _ = conn.execute(
        "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, details)
         VALUES (?1, ?2, 'agent_token.create', 'agent_token', ?3, 'success', 0.0, ?4)",
        params![
            auth.user_id,
            tid,
            tid.to_string(),
            json!({ "name": name, "scopes": scopes, "max_spend_per_tx": max_tx, "daily_spend_limit": daily_lim }).to_string()
        ],
    );

    let tokens = list_user_agent_tokens(&conn, auth.user_id)?;

    Ok((
        StatusCode::CREATED,
        Json(json!({
            "success": true,
            "message": "Autonomous MCP Agent Token generated. Copy it now — it will not be shown in full again.",
            "agent_token": raw_secret,
            "token_id": tid,
            "agent_tokens": tokens
        })),
    ))
}

pub async fn get_agent_tokens(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let tokens = list_user_agent_tokens(&conn, auth.user_id)?;
    let audit_logs = list_user_agent_audit_logs(&conn, auth.user_id, 100)?;
    Ok(Json(json!({
        "success": true,
        "agent_tokens": tokens,
        "agent_audit_logs": audit_logs
    })))
}

pub async fn revoke_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(token_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() && auth.agent_token_id != Some(token_id) {
        auth.require_scope("admin")?;
    }
    let conn = state.lock_db();
    let updated = conn.execute(
        "UPDATE agent_tokens SET is_active = 0 WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![token_id, auth.user_id, auth.role_id],
    )?;
    if updated == 0 {
        return Err(AppError::NotFound("Agent token not found.".into()));
    }
    let _ = conn.execute(
        "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, details)
         VALUES (?1, ?2, 'agent_token.revoke', 'agent_token', ?3, 'success', 0.0, '{}')",
        params![auth.user_id, token_id, token_id.to_string()],
    );
    Ok(Json(json!({
        "success": true,
        "message": "Agent token revoked."
    })))
}

pub async fn delete_agent_token(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(token_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    if auth.agent_token_id.is_some() && auth.agent_token_id != Some(token_id) {
        auth.require_scope("admin")?;
    }
    let conn = state.lock_db();
    let deleted = conn.execute(
        "DELETE FROM agent_tokens WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
        params![token_id, auth.user_id, auth.role_id],
    )?;
    if deleted == 0 {
        return Err(AppError::NotFound("Agent token not found.".into()));
    }
    Ok(Json(json!({
        "success": true,
        "message": "Agent token deleted."
    })))
}

pub async fn get_creator_analytics(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    let (gross_earnings, tx_count): (f64, i64) = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0), COUNT(*) FROM transactions
             WHERE recipient_user_id = ?1 AND sender_user_id != ?1 AND status = 'approved'",
            params![auth.user_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )
        .unwrap_or((0.0, 0));

    let net_earnings = round_money(gross_earnings * 0.85);

    let sub_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type IN ('subscription', 'one-month-subscription') AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let ppv_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type IN ('post-unlock', 'message-unlock', 'reel-unlock', 'stream-unlock') AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let tips_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type = 'tip' AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let total_likes: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM reactions r JOIN posts p ON r.post_id = p.id WHERE p.user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_comments: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM post_comments c JOIN posts p ON c.post_id = p.id WHERE p.user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_reel_views: i64 = conn
        .query_row(
            "SELECT COALESCE(SUM(views_count), 0) FROM reels WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_story_views: i64 = conn
        .query_row(
            "SELECT COALESCE(SUM(views_count), 0) FROM stories WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let agent_actions_count: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM agent_audit_logs WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    Ok(Json(json!({
        "success": true,
        "analytics": {
            "user_id": auth.user_id,
            "username": profile.username,
            "wallet_balance": profile.wallet_balance,
            "active_subscribers": profile.fans_count,
            "followers_count": profile.followers_count,
            "posts_count": profile.posts_count,
            "gross_earnings": round_money(gross_earnings),
            "net_earnings_85_pct": net_earnings,
            "transactions_count": tx_count,
            "revenue_breakdown": {
                "subscriptions": round_money(sub_revenue),
                "ppv_unlocks": round_money(ppv_revenue),
                "tips": round_money(tips_revenue),
            },
            "engagement": {
                "post_likes": total_likes,
                "post_comments": total_comments,
                "reel_views": total_reel_views,
                "story_views": total_story_views,
                "autonomous_agent_actions": agent_actions_count
            }
        }
    })))
}



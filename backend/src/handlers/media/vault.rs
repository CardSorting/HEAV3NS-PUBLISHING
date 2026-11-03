use axum::{
    extract::{Multipart, Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::AppState,
    models::*,
};

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
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "vault_upload_or_delete").await?;
    let mut saved_attachments = Vec::new();

    while let Some(field) = match multipart.next_field().await {
        Ok(f) => f,
        Err(e) => {
            crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
            return Err(AppError::BadRequest(format!("Multipart error: {e}")));
        }
    } {
        let orig_name = field.file_name().unwrap_or("upload.bin").to_string();
        let content_type = field.content_type().unwrap_or("image/jpeg").to_string();
        let data = match field.bytes().await {
            Ok(b) => b,
            Err(e) => {
                crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
                return Err(AppError::BadRequest(format!("Failed reading upload: {e}")));
            }
        };

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

        if let Err(e) = std::fs::write(&full_path, &data) {
            crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
            return Err(AppError::Internal(format!("Failed saving file: {e}")));
        }

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
        if let Err(e) = conn.execute(
            "INSERT INTO attachments (id, user_id, filename, file_url, file_type, file_size, is_vault)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1)",
            params![att_id, auth.user_id, orig_name, file_url, file_type, file_size],
        ) {
            drop(conn);
            crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
            return Err(e.into());
        }

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

    {
        let conn = state.lock_db();
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "vault",
            saved_attachments.first().map(|a| a.id.clone()),
            0.0,
            json!({ "action": "upload_attachment", "count": saved_attachments.len() }),
        );
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
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "vault_toggle_archive").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
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
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "vault",
            Some(att_id),
            0.0,
            json!({ "action": "toggle_archive", "is_archived": next_arch != 0 }),
        );
        Ok(json!({
            "success": true,
            "is_archived": next_arch != 0
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn delete_vault_item(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(att_id): Path<String>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "vault_upload_or_delete").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
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
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "vault",
            Some(att_id),
            0.0,
            json!({ "action": "delete_vault_item" }),
        );
        Ok(json!({ "success": true }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}


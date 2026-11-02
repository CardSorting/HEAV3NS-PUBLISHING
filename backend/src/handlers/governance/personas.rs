use axum::{
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    Json,
};
use chrono::Utc;
use rusqlite::params;
use serde::Deserialize;
use serde_json::json;
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::AppState,
};

pub fn parse_persona_row(
    row: &rusqlite::Row<'_>,
) -> Result<crate::sharding::AgentPersonaView, rusqlite::Error> {
    let practices_raw: String = row.get(11)?;
    let activities_raw: String = row.get(12)?;
    let practices: Vec<crate::sharding::PersonaPracticeRevision> =
        serde_json::from_str(&practices_raw).unwrap_or_default();
    let activities: Vec<crate::sharding::PersonaActivityRecord> =
        serde_json::from_str(&activities_raw).unwrap_or_default();
    Ok(crate::sharding::AgentPersonaView {
        id: row.get(0)?,
        user_id: row.get(1)?,
        handle: row.get(2)?,
        display_name: row.get(3)?,
        description: row.get(4)?,
        focus: row.get(5)?,
        shard_id: row.get(6)?,
        pool_tier: row.get(7)?,
        autonomy_mode: row.get(8)?,
        engagement_cadence: row.get(9)?,
        current_revision: row.get(10)?,
        practices,
        activities,
        created_at: row.get(13)?,
        updated_at: row.get(14)?,
    })
}

pub fn list_user_agent_personas(
    conn: &rusqlite::Connection,
    user_id: Option<i64>,
) -> Result<Vec<crate::sharding::AgentPersonaView>, rusqlite::Error> {
    if let Some(uid) = user_id {
        let mut stmt = conn.prepare(
            "SELECT id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
                    autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json,
                    created_at, updated_at
             FROM agent_personas WHERE user_id = ?1 ORDER BY updated_at DESC",
        )?;
        let rows = stmt
            .query_map(params![uid], parse_persona_row)?
            .filter_map(Result::ok)
            .collect();
        Ok(rows)
    } else {
        let mut stmt = conn.prepare(
            "SELECT id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
                    autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json,
                    created_at, updated_at
             FROM agent_personas ORDER BY updated_at DESC",
        )?;
        let rows = stmt
            .query_map([], parse_persona_row)?
            .filter_map(Result::ok)
            .collect();
        Ok(rows)
    }
}

pub fn get_persona_by_id_or_handle(
    conn: &rusqlite::Connection,
    identifier: &str,
) -> Result<crate::sharding::AgentPersonaView, AppError> {
    let clean = identifier.trim().trim_start_matches('@');
    conn.query_row(
        "SELECT id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
                autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json,
                created_at, updated_at
         FROM agent_personas WHERE id = ?1 OR lower(handle) = lower(?1)",
        params![clean],
        parse_persona_row,
    )
    .map_err(|_| AppError::NotFound(format!("Autonomous persona '{clean}' not found.")))
}

pub async fn get_agent_shards_telemetry(
    State(state): State<AppState>,
    auth: Option<AuthUser>,
) -> Result<impl IntoResponse, AppError> {
    let conn = state.lock_db();
    let telemetry = crate::sharding::build_shard_pool_telemetry(&state, &conn);
    let user_personas = if let Some(u) = auth {
        list_user_agent_personas(&conn, Some(u.user_id)).unwrap_or_default()
    } else {
        Vec::new()
    };
    Ok(Json(json!({
        "success": true,
        "telemetry": telemetry,
        "personas": user_personas
    })))
}

pub async fn get_agent_personas(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let personas = list_user_agent_personas(&conn, Some(auth.user_id))?;
    Ok(Json(json!({
        "success": true,
        "personas": personas
    })))
}

#[derive(Deserialize)]
pub struct UpsertPersonaPayload {
    pub handle: String,
    pub display_name: String,
    pub description: Option<String>,
    pub focus: Option<String>,
    pub pool_tier: Option<String>,
    pub autonomy_mode: Option<String>,
    pub engagement_cadence: Option<String>,
    pub principles: Option<Vec<String>>,
    pub interests: Option<Vec<String>>,
    pub methods: Option<Vec<String>>,
    pub direction: Option<String>,
}

pub async fn upsert_agent_persona(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<UpsertPersonaPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let handle = payload
        .handle
        .trim()
        .trim_start_matches('@')
        .to_lowercase()
        .replace(' ', "-");
    if handle.len() < 3 {
        return Err(AppError::Unprocessable(
            "Persona handle must be at least 3 characters.".into(),
        ));
    }
    let display_name = payload.display_name.trim();
    if display_name.is_empty() {
        return Err(AppError::Unprocessable(
            "Persona display_name is required.".into(),
        ));
    }

    let description = payload.description.unwrap_or_default();
    let focus = payload.focus.unwrap_or_else(|| "creator".into());
    let policy = crate::sharding::get_pool_tier_policy(payload.pool_tier.as_deref().unwrap_or("central"));
    let autonomy_mode = payload
        .autonomy_mode
        .unwrap_or_else(|| "autonomous_co_pilot".into());
    let engagement_cadence = payload
        .engagement_cadence
        .unwrap_or_else(|| "balanced".into());
    let shard_id = crate::sharding::compute_virtual_shard(&format!("persona:{}:{handle}", auth.user_id));
    let now_iso = Utc::now().to_rfc3339();

    let conn = state.lock_db();
    let existing = get_persona_by_id_or_handle(&conn, &handle).ok();

    let persona_id = if let Some(ref ex) = existing {
        if ex.user_id != auth.user_id && auth.role_id != 1 {
            return Err(AppError::Forbidden(
                "This persona handle belongs to another creator.".into(),
            ));
        }
        conn.execute(
            "UPDATE agent_personas
             SET display_name = ?1, description = ?2, focus = ?3, pool_tier = ?4,
                 autonomy_mode = ?5, engagement_cadence = ?6, updated_at = datetime('now')
             WHERE id = ?7",
            params![
                display_name,
                description,
                focus,
                policy.tier,
                autonomy_mode,
                engagement_cadence,
                ex.id
            ],
        )?;
        ex.id.clone()
    } else {
        let pid = format!("persona-{}", &Uuid::new_v4().simple().to_string()[..12]);
        let initial_practice = crate::sharding::PersonaPracticeRevision {
            revision: 1,
            focus: focus.clone(),
            principles: payload.principles.unwrap_or_else(|| {
                vec![
                    "Maintain authentic creator voice and transparent value propositions.".into(),
                    "Engage warmly with subscribers while respecting token-bucket pacing.".into(),
                ]
            }),
            interests: payload.interests.unwrap_or_else(|| {
                vec![
                    "Exclusive studio drops".into(),
                    "Interactive subscriber polls".into(),
                ]
            }),
            methods: payload.methods.unwrap_or_else(|| {
                vec![
                    "Pair Pay-Per-View releases with free public previews.".into(),
                    "Acknowledge top comments and loyal supporters.".into(),
                ]
            }),
            direction: payload.direction.unwrap_or_else(|| {
                if description.is_empty() {
                    format!("Operate autonomous persona @{handle} with high creative craft.")
                } else {
                    description.clone()
                }
            }),
            recorded_at: now_iso,
        };
        let practices_json =
            serde_json::to_string(&vec![initial_practice]).unwrap_or_else(|_| "[]".into());
        conn.execute(
            "INSERT INTO agent_personas
             (id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
              autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, 1, ?11, '[]')",
            params![
                pid,
                auth.user_id,
                handle,
                display_name,
                description,
                focus,
                shard_id,
                policy.tier,
                autonomy_mode,
                engagement_cadence,
                practices_json
            ],
        )?;
        pid
    };

    let persona = get_persona_by_id_or_handle(&conn, &persona_id)?;
    let personas = list_user_agent_personas(&conn, Some(auth.user_id))?;
    drop(conn);
    state.sync_persona_to_postgres(&persona);

    Ok((
        StatusCode::CREATED,
        Json(json!({
            "success": true,
            "message": "Autonomous persona saved to shard residency.",
            "persona": persona,
            "personas": personas
        })),
    ))
}

#[derive(Deserialize)]
pub struct UpdatePersonaPracticePayload {
    pub expected_revision: Option<i64>,
    pub focus: Option<String>,
    pub principles: Option<Vec<String>>,
    pub interests: Option<Vec<String>>,
    pub methods: Option<Vec<String>>,
    pub direction: String,
}

pub async fn update_persona_practice(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(persona_id): Path<String>,
    Json(payload): Json<UpdatePersonaPracticePayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let direction = payload.direction.trim();
    if direction.is_empty() {
        return Err(AppError::Unprocessable(
            "Practice revision direction is required.".into(),
        ));
    }

    let conn = state.lock_db();
    let mut persona = get_persona_by_id_or_handle(&conn, &persona_id)?;
    if persona.user_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden(
            "You do not have permission to evolve this persona's practice.".into(),
        ));
    }

    if let Some(exp) = payload.expected_revision {
        if exp != persona.current_revision {
            return Err(AppError::Conflict(format!(
                "Optimistic concurrency conflict: expected revision {exp}, but current revision is {}.",
                persona.current_revision
            )));
        }
    }

    let next_rev = persona.current_revision + 1;
    let prev = persona.practices.last().cloned();
    let focus = payload
        .focus
        .or_else(|| prev.as_ref().map(|p| p.focus.clone()))
        .unwrap_or_else(|| persona.focus.clone());
    let principles = payload
        .principles
        .or_else(|| prev.as_ref().map(|p| p.principles.clone()))
        .unwrap_or_default();
    let interests = payload
        .interests
        .or_else(|| prev.as_ref().map(|p| p.interests.clone()))
        .unwrap_or_default();
    let methods = payload
        .methods
        .or_else(|| prev.as_ref().map(|p| p.methods.clone()))
        .unwrap_or_default();

    let new_rev = crate::sharding::PersonaPracticeRevision {
        revision: next_rev,
        focus: focus.clone(),
        principles,
        interests,
        methods,
        direction: direction.to_string(),
        recorded_at: Utc::now().to_rfc3339(),
    };
    persona.practices.push(new_rev.clone());
    let practices_json =
        serde_json::to_string(&persona.practices).unwrap_or_else(|_| "[]".into());

    conn.execute(
        "UPDATE agent_personas
         SET focus = ?1, current_revision = ?2, practices_json = ?3, updated_at = datetime('now')
         WHERE id = ?4",
        params![focus, next_rev, practices_json, persona.id],
    )?;

    let updated = get_persona_by_id_or_handle(&conn, &persona.id)?;
    drop(conn);
    state.sync_persona_to_postgres(&updated);

    Ok(Json(json!({
        "success": true,
        "message": format!("Appended practice revision #{next_rev}."),
        "practice": new_rev,
        "persona": updated
    })))
}

#[derive(Deserialize)]
pub struct CompileBriefPayload {
    pub intent: String,
    pub target_audience: Option<String>,
}

pub async fn compile_persona_brief_handler(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(persona_id): Path<String>,
    Json(payload): Json<CompileBriefPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let intent = payload.intent.trim();
    if intent.is_empty() {
        return Err(AppError::Unprocessable(
            "Execution brief intent cannot be empty.".into(),
        ));
    }
    let conn = state.lock_db();
    let persona = get_persona_by_id_or_handle(&conn, &persona_id)?;
    let brief = crate::sharding::compile_persona_brief(
        &persona,
        intent,
        payload.target_audience.as_deref(),
    );
    Ok(Json(json!({
        "success": true,
        "brief": brief
    })))
}

#[derive(Deserialize)]
pub struct RecordPersonaActivityPayload {
    pub action_type: String,
    pub target_resource_id: Option<String>,
    pub description: String,
    pub practice_revision: Option<i64>,
}

pub async fn record_persona_activity(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(persona_id): Path<String>,
    Json(payload): Json<RecordPersonaActivityPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("engage")?;
    let desc = payload.description.trim();
    if desc.is_empty() {
        return Err(AppError::Unprocessable(
            "Activity description is required.".into(),
        ));
    }
    let conn = state.lock_db();
    let mut persona = get_persona_by_id_or_handle(&conn, &persona_id)?;
    if persona.user_id != auth.user_id && auth.role_id != 1 {
        return Err(AppError::Forbidden(
            "You do not have permission to record activity on this persona.".into(),
        ));
    }

    let rev = payload
        .practice_revision
        .unwrap_or(persona.current_revision);
    if rev < 1 || rev > persona.current_revision {
        return Err(AppError::Unprocessable(format!(
            "Invalid practice_revision {rev} (valid range: 1..={}).",
            persona.current_revision
        )));
    }

    let now_iso = Utc::now().to_rfc3339();
    let act = crate::sharding::PersonaActivityRecord {
        id: format!("act-{}", &Uuid::new_v4().simple().to_string()[..12]),
        persona_id: persona.id.clone(),
        practice_revision: rev,
        action_type: payload.action_type.trim().to_string(),
        target_resource_id: payload.target_resource_id,
        description: desc.to_string(),
        occurred_at: now_iso.clone(),
        recorded_at: now_iso,
    };

    persona.activities.push(act.clone());
    let activities_json =
        serde_json::to_string(&persona.activities).unwrap_or_else(|_| "[]".into());
    conn.execute(
        "UPDATE agent_personas SET activities_json = ?1, updated_at = datetime('now') WHERE id = ?2",
        params![activities_json, persona.id],
    )?;

    let updated = get_persona_by_id_or_handle(&conn, &persona.id)?;
    drop(conn);
    state.sync_persona_to_postgres(&updated);

    Ok((
        StatusCode::CREATED,
        Json(json!({
            "success": true,
            "activity": act,
            "persona": updated
        })),
    ))
}


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
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "persona_upsert").await?;
    let res: Result<(StatusCode, serde_json::Value), AppError> = (|| {
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
        let focus = crate::sharding::validate_persona_focus(
            payload.focus.as_deref().unwrap_or("creator"),
        )?;
        let policy =
            crate::sharding::get_pool_tier_policy(payload.pool_tier.as_deref().unwrap_or("central"));
        let autonomy_mode = crate::sharding::validate_autonomy_mode(
            payload
                .autonomy_mode
                .as_deref()
                .unwrap_or("autonomous_co_pilot"),
        )?;
        let engagement_cadence = crate::sharding::validate_engagement_cadence(
            payload
                .engagement_cadence
                .as_deref()
                .unwrap_or("balanced"),
        )?;
        let shard_id =
            crate::sharding::compute_virtual_shard(&format!("persona:{}:{handle}", auth.user_id));
        let now_iso = Utc::now().to_rfc3339();

        let conn = state.lock_db();
        let existing = get_persona_by_id_or_handle(&conn, &handle).ok();

        let persona_id = if let Some(ref ex) = existing {
            if ex.user_id != auth.user_id && auth.role_id != 1 {
                return Err(AppError::Forbidden(
                    "This persona handle belongs to another creator.".into(),
                ));
            }
            if let Some(ref p) = payload.principles {
                crate::sharding::validate_practice_labels(p, "principles")?;
            }
            if let Some(ref i) = payload.interests {
                crate::sharding::validate_practice_labels(i, "interests")?;
            }
            if let Some(ref m) = payload.methods {
                crate::sharding::validate_practice_labels(m, "methods")?;
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
            let principles = match payload.principles {
                Some(ref p) => crate::sharding::validate_practice_labels(p, "principles")?,
                None => vec![
                    "Maintain authentic creator voice and transparent value propositions.".into(),
                    "Engage warmly with subscribers while respecting token-bucket pacing.".into(),
                ],
            };
            let interests = match payload.interests {
                Some(ref i) => crate::sharding::validate_practice_labels(i, "interests")?,
                None => vec![
                    "Exclusive studio drops".into(),
                    "Interactive subscriber polls".into(),
                ],
            };
            let methods = match payload.methods {
                Some(ref m) => crate::sharding::validate_practice_labels(m, "methods")?,
                None => vec![
                    "Pair Pay-Per-View releases with free public previews.".into(),
                    "Acknowledge top comments and loyal supporters.".into(),
                ],
            };
            let initial_practice = crate::sharding::PersonaPracticeRevision {
                revision: 1,
                focus: focus.clone(),
                principles,
                interests,
                methods,
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
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "persona",
            Some(persona.id.clone()),
            0.0,
            json!({ "handle": persona.handle, "shard_id": persona.shard_id, "pool_tier": persona.pool_tier }),
        );
        drop(conn);
        state.sync_persona_to_postgres(&persona);

        Ok((
            StatusCode::CREATED,
            json!({
                "success": true,
                "message": "Autonomous persona saved to shard residency.",
                "persona": persona,
                "personas": personas
            }),
        ))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(|(code, val)| (code, Json(val)))
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
    let quota_dec =
        crate::sharding::enforce_agent_rest_quota(&state, &auth, "persona_update_practice").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
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

        let prev = persona.practices.last().cloned();
        let focus = match payload.focus.as_deref() {
            Some(f) => crate::sharding::validate_persona_focus(f)?,
            None => prev
                .as_ref()
                .map(|p| p.focus.clone())
                .unwrap_or_else(|| persona.focus.clone()),
        };
        let principles = match payload.principles {
            Some(ref p) => crate::sharding::validate_practice_labels(p, "principles")?,
            None => prev.as_ref().map(|p| p.principles.clone()).unwrap_or_default(),
        };
        let interests = match payload.interests {
            Some(ref i) => crate::sharding::validate_practice_labels(i, "interests")?,
            None => prev.as_ref().map(|p| p.interests.clone()).unwrap_or_default(),
        };
        let methods = match payload.methods {
            Some(ref m) => crate::sharding::validate_practice_labels(m, "methods")?,
            None => prev.as_ref().map(|p| p.methods.clone()).unwrap_or_default(),
        };

        if let Some(exp) = payload.expected_revision {
            // GALXAI StarStore.setPractice exact replay semantics:
            // If expected_revision == current_revision - 1 and the practice matches the latest revision,
            // return idempotently instead of throwing 409 after a lost network response.
            if exp == persona.current_revision - 1 {
                if let Some(ref last) = prev {
                    if last.focus == focus
                        && last.principles == principles
                        && last.interests == interests
                        && last.methods == methods
                        && last.direction == direction
                    {
                        return Ok(json!({
                            "success": true,
                            "idempotent_replay": true,
                            "message": format!("Replayed practice revision #{}.", last.revision),
                            "practice": last,
                            "persona": persona
                        }));
                    }
                }
            }
            if exp != persona.current_revision {
                return Err(AppError::Conflict(format!(
                    "Optimistic concurrency conflict: expected revision {exp}, but current revision is {}.",
                    persona.current_revision
                )));
            }
        }

        let next_rev = persona.current_revision + 1;
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
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "persona",
            Some(persona.id.clone()),
            0.0,
            json!({ "revision": next_rev, "focus": focus }),
        );
        drop(conn);
        state.sync_persona_to_postgres(&updated);

        Ok(json!({
            "success": true,
            "message": format!("Appended practice revision #{next_rev}."),
            "practice": new_rev,
            "persona": updated
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct CompileBriefPayload {
    pub intent: String,
    pub target_audience: Option<String>,
    pub characters: Option<Vec<crate::sharding::PersonaCharacterReference>>,
}

pub async fn compile_persona_brief_handler(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(persona_id): Path<String>,
    Json(payload): Json<CompileBriefPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "persona_brief").await?;
    let res: Result<serde_json::Value, AppError> = (|| {
        let intent = payload.intent.trim();
        if intent.is_empty() || intent.len() > 4000 {
            return Err(AppError::Unprocessable(
                "Execution brief intent must be between 1 and 4000 characters.".into(),
            ));
        }
        let conn = state.lock_db();
        let persona = get_persona_by_id_or_handle(&conn, &persona_id)?;
        let active_focus = persona
            .practices
            .last()
            .map(|p| p.focus.as_str())
            .unwrap_or(&persona.focus);
        let validated_chars = crate::sharding::validate_character_references(
            payload.characters.as_deref(),
            active_focus,
        )?;
        let brief = crate::sharding::compile_persona_brief_with_characters(
            &persona,
            intent,
            payload.target_audience.as_deref(),
            if validated_chars.is_empty() {
                None
            } else {
                Some(&validated_chars)
            },
        );
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "persona_brief",
            Some(persona.id),
            0.0,
            json!({ "intent": intent }),
        );
        Ok(json!({
            "success": true,
            "brief": brief
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct RecordPersonaActivityPayload {
    pub id: Option<String>,
    pub action_type: String,
    pub target_resource_id: Option<String>,
    pub work_ids: Option<Vec<String>>,
    pub characters: Option<Vec<crate::sharding::PersonaCharacterReference>>,
    pub brief: Option<String>,
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
    let quota_dec =
        crate::sharding::enforce_agent_rest_quota(&state, &auth, "persona_record_activity").await?;
    let res: Result<(StatusCode, serde_json::Value), AppError> = (|| {
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

        let rev_focus = persona
            .practices
            .get((rev - 1) as usize)
            .map(|p| p.focus.as_str())
            .unwrap_or(&persona.focus);
        // In GALXAI `StarStore.addActivity`, if `characters` are provided on a gravure activity, or if the
        // caller passed `characters: []` explicitly on a gravure activity, enforce non-empty adult references.
        if rev_focus.eq_ignore_ascii_case("gravure") {
            if let Some(ref raw_list) = payload.characters {
                if raw_list.is_empty() {
                    return Err(AppError::Unprocessable(
                        "Gravure activity requires at least one explicitly adult (18+) character reference when characters are specified.".into(),
                    ));
                }
            }
        }
        let validated_chars = crate::sharding::validate_character_references(
            payload.characters.as_deref(),
            rev_focus,
        )?;
        let characters_opt = if validated_chars.is_empty() {
            None
        } else {
            Some(validated_chars)
        };

        let mut work_ids = Vec::new();
        for raw_wid in payload.work_ids.unwrap_or_default() {
            let trimmed = raw_wid.trim();
            if !trimmed.is_empty() {
                let valid = crate::sharding::require_valid_record_id(trimmed, "work_id")?;
                work_ids.push(valid);
            }
        }
        if work_ids.len() > 64 {
            return Err(AppError::Unprocessable(
                "At most 64 work_ids may be linked to a single activity.".into(),
            ));
        }
        let mut seen_works = std::collections::HashSet::new();
        for wid in &work_ids {
            if !seen_works.insert(wid.clone()) {
                return Err(AppError::Unprocessable(format!(
                    "Duplicate work_id '{wid}' in activity payload."
                )));
            }
        }

        let act_id = match payload.id.as_ref().map(|s| s.trim()).filter(|s| !s.is_empty()) {
            Some(custom_id) => crate::sharding::require_valid_record_id(custom_id, "activity id")?,
            None => format!("act-{}", &Uuid::new_v4().simple().to_string()[..12]),
        };
        let action_type_clean = payload.action_type.trim().to_string();
        let target_res_clean = match payload
            .target_resource_id
            .as_ref()
            .map(|s| s.trim())
            .filter(|s| !s.is_empty())
        {
            Some(tr) => Some(crate::sharding::require_valid_record_id(tr, "target_resource_id")?),
            None => None,
        };
        if let Some(ref tr) = target_res_clean {
            if !work_ids.contains(tr) {
                work_ids.push(tr.clone());
            }
        }
        let compiled_brief = crate::sharding::compile_persona_brief_with_characters(
            &persona,
            desc,
            None,
            characters_opt.as_deref(),
        );
        let brief_clean = payload
            .brief
            .as_ref()
            .map(|s| s.trim().to_string())
            .filter(|s| !s.is_empty())
            .or_else(|| {
                compiled_brief
                    .get("instructions")
                    .and_then(|v| v.as_str())
                    .map(String::from)
            });

        // GALXAI StarStore.addActivity replay & global conflict check across all personas:
        let all_personas = list_user_agent_personas(&conn, None).unwrap_or_default();
        for p in &all_personas {
            if let Some(prev_act) = p.activities.iter().find(|a| a.id == act_id).cloned() {
                if p.id == persona.id
                    && prev_act.practice_revision == rev
                    && prev_act.action_type == action_type_clean
                    && prev_act.target_resource_id == target_res_clean
                    && prev_act.work_ids == work_ids
                    && prev_act.description == desc
                    && prev_act.characters == characters_opt
                    && (payload.brief.is_none() || prev_act.brief == brief_clean)
                {
                    return Ok((
                        StatusCode::OK,
                        json!({
                            "success": true,
                            "idempotent_replay": true,
                            "activity": prev_act,
                            "persona": persona
                        }),
                    ));
                }
                return Err(AppError::Conflict(format!(
                    "Activity ID '{act_id}' already exists on persona '@{}' with different content.",
                    p.handle
                )));
            }
            if p.id != persona.id {
                for entry in &p.activities {
                    for wid in &work_ids {
                        if entry.work_ids.contains(wid) {
                            return Err(AppError::Conflict(format!(
                                "Referenced work_id '{wid}' is already attributed to persona '@{}'.",
                                p.handle
                            )));
                        }
                    }
                }
            }
        }

        let now_iso = Utc::now().to_rfc3339();
        let act = crate::sharding::PersonaActivityRecord {
            id: act_id.clone(),
            persona_id: persona.id.clone(),
            practice_revision: rev,
            action_type: action_type_clean.clone(),
            target_resource_id: target_res_clean,
            work_ids,
            characters: characters_opt,
            brief: brief_clean,
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
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "persona_activity",
            Some(act_id),
            0.0,
            json!({ "persona_id": persona.id, "action_type": action_type_clean }),
        );
        drop(conn);
        state.sync_persona_to_postgres(&updated);

        Ok((
            StatusCode::CREATED,
            json!({
                "success": true,
                "activity": act,
                "persona": updated
            }),
        ))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(|(code, val)| (code, Json(val)))
}

#[derive(Deserialize, Default)]
pub struct BootstrapRosterPayload {
    pub pool_tier_override: Option<String>,
}

/// Non-destructively installs the GALXAI-inspired commissioned persona roster for the authenticated creator
/// (`POST /api/agent/personas/roster`). Existing personas and evolved practices are preserved untouched.
pub fn bootstrap_commissioned_roster_for_user(
    state: &AppState,
    user_id: i64,
    username: &str,
    pool_override: Option<&str>,
) -> Result<serde_json::Value, AppError> {
    let conn = state.lock_db();
    let before = list_user_agent_personas(&conn, Some(user_id))?;
    let existing_before = before.len();
    let now_iso = Utc::now().to_rfc3339();
    let clean_user = username
        .chars()
        .filter(|c| c.is_alphanumeric() || *c == '-')
        .collect::<String>()
        .to_lowercase();

    let mut changes = Vec::new();
    let mut synced_personas = Vec::new();

    for tpl in crate::sharding::COMMISSIONED_PERSONA_ROSTER {
        let handle = format!("{clean_user}-{}", tpl.slug);
        let pid = format!("persona-{user_id}-{}", tpl.slug);
        let existing = before
            .iter()
            .find(|p| p.id == pid || p.handle.eq_ignore_ascii_case(&handle));

        if let Some(ex) = existing {
            if !ex.practices.is_empty() {
                changes.push(json!({
                    "id": ex.id,
                    "handle": ex.handle,
                    "shard_id": ex.shard_id,
                    "pool_tier": ex.pool_tier,
                    "action": "preserved existing practice"
                }));
                continue;
            }
        }

        let tier_str = pool_override.unwrap_or(tpl.pool_tier);
        let policy = crate::sharding::get_pool_tier_policy(tier_str);
        let shard_id = crate::sharding::compute_virtual_shard(&format!("persona:{user_id}:{handle}"));
        let rev = crate::sharding::PersonaPracticeRevision {
            revision: 1,
            focus: tpl.focus.to_string(),
            principles: tpl.principles.iter().map(|s| s.to_string()).collect(),
            interests: tpl.interests.iter().map(|s| s.to_string()).collect(),
            methods: tpl.methods.iter().map(|s| s.to_string()).collect(),
            direction: tpl.direction.to_string(),
            recorded_at: now_iso.clone(),
        };
        let practices_json = serde_json::to_string(&vec![rev]).unwrap_or_else(|_| "[]".into());

        conn.execute(
            "INSERT OR IGNORE INTO agent_personas
             (id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
              autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, 1, ?11, '[]')",
            params![
                pid,
                user_id,
                handle,
                tpl.display_name,
                tpl.description,
                tpl.focus,
                shard_id,
                policy.tier,
                tpl.autonomy_mode,
                tpl.engagement_cadence,
                practices_json
            ],
        )?;

        if let Ok(p) = get_persona_by_id_or_handle(&conn, &pid) {
            synced_personas.push(p);
        }
        changes.push(json!({
            "id": pid,
            "handle": handle,
            "shard_id": shard_id,
            "pool_tier": policy.tier,
            "action": if existing.is_some() { "initialized practice" } else { "created identity and practice" }
        }));
    }

    let personas = list_user_agent_personas(&conn, Some(user_id))?;
    drop(conn);
    for p in &synced_personas {
        state.sync_persona_to_postgres(p);
    }

    Ok(json!({
        "success": true,
        "existing_before": existing_before,
        "installed_count": synced_personas.len(),
        "changes": changes,
        "personas": personas
    }))
}

pub async fn bootstrap_persona_roster_handler(
    State(state): State<AppState>,
    auth: AuthUser,
    payload: Option<Json<BootstrapRosterPayload>>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("write")?;
    let quota_dec =
        crate::sharding::enforce_agent_rest_quota(&state, &auth, "persona_bootstrap_roster").await?;
    let override_tier = payload
        .as_ref()
        .and_then(|p| p.pool_tier_override.as_deref());
    let res = bootstrap_commissioned_roster_for_user(&state, auth.user_id, &auth.username, override_tier);
    match res {
        Ok(val) => {
            let conn = state.lock_db();
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "persona_roster",
                None,
                0.0,
                json!({ "installed_count": val.get("installed_count") }),
            );
            Ok(Json(val))
        }
        Err(e) => {
            crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
            Err(e)
        }
    }
}

#[derive(Deserialize)]
pub struct SwarmBatchStep {
    #[serde(alias = "tool_name")]
    pub tool: String,
    #[serde(default)]
    pub arguments: serde_json::Value,
    pub idempotency_key: Option<String>,
}

#[derive(Deserialize)]
pub struct SwarmBatchPayload {
    #[serde(alias = "actions")]
    pub steps: Vec<SwarmBatchStep>,
    pub stop_on_error: Option<bool>,
}

/// POST /api/agent/swarm/batch — Executes a multi-step sequence of autonomous agent actions in a single
/// orchestrated pipeline with per-step GCRA token-bucket governance, shard routing, and idempotency.
pub async fn execute_swarm_batch_handler(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<SwarmBatchPayload>,
) -> Result<impl IntoResponse, AppError> {
    if payload.steps.is_empty() || payload.steps.len() > 25 {
        return Err(AppError::Unprocessable(
            "Swarm batch must contain between 1 and 25 action steps.".into(),
        ));
    }
    let batch_quota_dec =
        crate::sharding::enforce_agent_rest_quota(&state, &auth, "swarm_batch_execute").await?;

    let total_steps = payload.steps.len();
    let stop_on_err = payload.stop_on_error.unwrap_or(true);
    let mut results = Vec::with_capacity(total_steps);
    let mut succeeded = 0usize;
    let mut failed = 0usize;
    let mut total_pacing_ms = 0i64;

    for (idx, step) in payload.steps.into_iter().enumerate() {
        let tool_name = step.tool.trim().to_string();
        if tool_name.is_empty() || tool_name == "swarm_batch_execute" {
            crate::sharding::refund_agent_rest_quota(&state, &auth, batch_quota_dec.as_ref());
            return Err(AppError::BadRequest(
                "Empty or nested swarm_batch_execute calls are not allowed.".into(),
            ));
        }
        let mut args = if step.arguments.is_object() {
            step.arguments
        } else {
            json!({})
        };
        if let Some(ref ikey) = step.idempotency_key {
            if let Some(obj) = args.as_object_mut() {
                obj.entry("idempotency_key".to_string())
                    .or_insert_with(|| serde_json::Value::String(ikey.clone()));
            }
        }

        match crate::mcp::execute_mcp_tool(&state, Some(&auth), &tool_name, &args) {
            Ok(val) => {
                let gov = val.get("_quota_governance").cloned().unwrap_or_else(|| json!({}));
                let step_shard = gov.get("shard_id").and_then(|v| v.as_i64()).unwrap_or(0);
                let step_pool = gov
                    .get("pool_tier")
                    .and_then(|v| v.as_str())
                    .unwrap_or("central")
                    .to_string();
                let step_mode = gov
                    .get("throttle_mode")
                    .and_then(|v| v.as_str())
                    .unwrap_or("smooth")
                    .to_string();
                let pacing = gov
                    .get("pacing_delay_ms")
                    .and_then(|v| v.as_i64())
                    .unwrap_or(0);
                total_pacing_ms += pacing;
                let throttled = val
                    .get("throttled")
                    .and_then(|v| v.as_bool())
                    .unwrap_or(false);
                if throttled {
                    failed += 1;
                    results.push(json!({
                        "index": idx,
                        "step_index": idx,
                        "tool": tool_name,
                        "tool_name": tool_name,
                        "success": false,
                        "throttled": true,
                        "shard_id": step_shard,
                        "pool_tier": step_pool,
                        "throttle_mode": step_mode,
                        "pacing_delay_ms": pacing,
                        "_quota_governance": gov,
                        "result": val
                    }));
                    if stop_on_err {
                        break;
                    }
                } else {
                    succeeded += 1;
                    results.push(json!({
                        "index": idx,
                        "step_index": idx,
                        "tool": tool_name,
                        "tool_name": tool_name,
                        "success": true,
                        "throttled": false,
                        "shard_id": step_shard,
                        "pool_tier": step_pool,
                        "throttle_mode": step_mode,
                        "pacing_delay_ms": pacing,
                        "_quota_governance": gov,
                        "result": val
                    }));
                }
            }
            Err(err) => {
                failed += 1;
                let msg = match &err {
                    AppError::BadRequest(m)
                    | AppError::Unauthorized(m)
                    | AppError::Forbidden(m)
                    | AppError::NotFound(m)
                    | AppError::Conflict(m)
                    | AppError::Unprocessable(m)
                    | AppError::QuotaCooldownAdvisory(m, _)
                    | AppError::Internal(m) => m.clone(),
                };
                results.push(json!({
                    "index": idx,
                    "step_index": idx,
                    "tool": tool_name,
                    "tool_name": tool_name,
                    "success": false,
                    "error": msg
                }));
                if stop_on_err {
                    break;
                }
            }
        }
    }

    if total_pacing_ms > 0 {
        tokio::time::sleep(std::time::Duration::from_millis(
            (total_pacing_ms as u64).min(250),
        ))
        .await;
    }

    let telemetry = {
        let conn = state.lock_db();
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            batch_quota_dec.as_ref(),
            "swarm_batch",
            None,
            0.0,
            json!({
                "total_steps": total_steps,
                "executed": results.len(),
                "succeeded": succeeded,
                "failed": failed
            }),
        );
        crate::sharding::build_shard_pool_telemetry(&state, &conn)
    };

    let shard_hdr = batch_quota_dec
        .as_ref()
        .map(|d| d.shard_id)
        .or(auth.shard_id)
        .unwrap_or(0)
        .to_string();
    let pool_hdr = batch_quota_dec
        .as_ref()
        .map(|d| d.pool_tier.clone())
        .or_else(|| auth.pool_tier.clone())
        .unwrap_or_else(|| "central".into());
    let mode_hdr = batch_quota_dec
        .as_ref()
        .map(|d| d.throttle_mode.clone())
        .unwrap_or_else(|| "smooth".into());
    let pacing_hdr = total_pacing_ms.to_string();

    Ok((
        [
            ("x-heav3ns-shard", shard_hdr),
            ("x-heav3ns-pool", pool_hdr),
            ("x-ratelimit-throttle-mode", mode_hdr),
            ("x-ratelimit-pacing-ms", pacing_hdr),
        ],
        Json(json!({
            "success": failed == 0,
            "total_steps": total_steps,
            "batch_size": total_steps,
            "executed": results.len(),
            "succeeded": succeeded,
            "succeeded_count": succeeded,
            "failed": failed,
            "failed_count": failed,
            "total_pacing_delay_ms": total_pacing_ms,
            "results": results,
            "telemetry_snapshot": {
                "shards": telemetry["shards"],
                "virtual_shards_count": telemetry["virtual_shards_count"],
                "cluster_summary": telemetry["cluster_summary"]
            }
        })),
    ))
}


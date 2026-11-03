use axum::{
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};
use uuid::Uuid;

use crate::{
    auth::{create_jwt, hash_agent_token, AppError, AuthUser, OptionalAuthUser},
    db::{build_post_view, get_user_public_profile, is_blocked_between, round_money, AppState},
    handlers::{
        list_user_agent_audit_logs, list_user_agent_tokens, normalize_db_datetime,
    },
    models::*,
};

#[derive(Deserialize)]
pub struct JsonRpcRequest {
    pub jsonrpc: Option<String>,
    pub id: Option<Value>,
    pub method: String,
    pub params: Option<Value>,
}

/// Returns the complete MCP Tool Catalog (30+ tools) for autonomous account creation and operation.
pub fn get_mcp_tools_catalog() -> Vec<Value> {
    vec![
        // 1. Autonomous Account Registration & Authentication
        json!({
            "name": "account_register",
            "category": "Identity & Auth",
            "scope": "public",
            "description": "Autonomously register a new creator or fan account, provision a $100.00 starter wallet, and optionally issue an immediate MCP Agent Token (hp_mcp_*) for ongoing autonomous operation.",
            "inputSchema": {
                "type": "object",
                "required": ["name", "email", "password"],
                "properties": {
                    "name": { "type": "string", "description": "Display name" },
                    "username": { "type": "string", "description": "Unique handle (optional, derived from email if omitted)" },
                    "email": { "type": "string", "description": "Account email address" },
                    "password": { "type": "string", "description": "Account password (min 6 chars)" },
                    "referral_code": { "type": "string", "description": "Optional creator referral code for +$5.00 signup bonus" },
                    "issue_agent_token": { "type": "boolean", "description": "If true (default), automatically provisions an hp_mcp_* agent token" },
                    "agent_name": { "type": "string", "description": "Name for the provisioned MCP agent token" }
                }
            }
        }),
        json!({
            "name": "account_login",
            "category": "Identity & Auth",
            "scope": "public",
            "description": "Authenticate an existing account with email/username and password, returning a JWT and optional scoped MCP Agent Token.",
            "inputSchema": {
                "type": "object",
                "required": ["email", "password"],
                "properties": {
                    "email": { "type": "string", "description": "Email or username" },
                    "password": { "type": "string", "description": "Account password" },
                    "issue_agent_token": { "type": "boolean", "description": "If true, creates and returns a fresh hp_mcp_* token" },
                    "agent_name": { "type": "string", "description": "Label for the issued agent token" }
                }
            }
        }),
        json!({
            "name": "account_whoami",
            "category": "Identity & Auth",
            "scope": "read",
            "description": "Inspect the currently authenticated account profile, wallet balance, unread notifications, unread messages, and active agent token scopes/guardrails.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        json!({
            "name": "agent_token_create",
            "category": "Identity & Auth",
            "scope": "admin",
            "description": "Provision a new scoped MCP Agent Token (hp_mcp_*) with granular scopes, relay pool tier assignment, and per-tx / 24h spend caps.",
            "inputSchema": {
                "type": "object",
                "required": ["name"],
                "properties": {
                    "name": { "type": "string", "description": "Agent key name (e.g., 'Claude Autonomous Studio Agent')" },
                    "scopes": { "type": "array", "items": { "type": "string" }, "description": "Scopes: read, write, engage, monetize, messages, wallet, admin" },
                    "max_spend_per_tx": { "type": "number", "description": "Maximum USD spend per single transaction (default 50.0)" },
                    "daily_spend_limit": { "type": "number", "description": "Maximum USD spend per rolling 24h window (default 200.0)" },
                    "pool_tier": { "type": "string", "description": "Relay pool tier: central, premium, or studio (default central)" },
                    "daily_action_quota": { "type": "integer", "description": "Optional custom daily soft action quota override" }
                }
            }
        }),
        json!({
            "name": "agent_token_list",
            "category": "Identity & Auth",
            "scope": "read",
            "description": "List all MCP Agent Tokens and recent autonomous audit log entries for the account.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        json!({
            "name": "agent_token_revoke",
            "category": "Identity & Auth",
            "scope": "admin",
            "description": "Immediately revoke an MCP Agent Token by ID.",
            "inputSchema": {
                "type": "object",
                "required": ["token_id"],
                "properties": { "token_id": { "type": "integer" } }
            }
        }),
        // 2. Profile, Creator Rates, Promotional Offers & KYC
        json!({
            "name": "profile_update",
            "category": "Profile & Rates",
            "scope": "write",
            "description": "Update public creator profile metadata (display name, username, bio, location, website, pronouns, birthdate, avatar, cover).",
            "inputSchema": {
                "type": "object",
                "required": ["name", "username"],
                "properties": {
                    "name": { "type": "string" },
                    "username": { "type": "string" },
                    "bio": { "type": "string" },
                    "location": { "type": "string" },
                    "website": { "type": "string" },
                    "gender_pronoun": { "type": "string" },
                    "birthdate": { "type": "string" },
                    "avatar": { "type": "string" },
                    "cover": { "type": "string" }
                }
            }
        }),
        json!({
            "name": "rates_and_offers_update",
            "category": "Profile & Rates",
            "scope": "monetize",
            "description": "Configure creator subscription rates (1m, 3m, 6m, 12m), paid/open profile mode, and optional promotional discount campaign.",
            "inputSchema": {
                "type": "object",
                "required": ["paid_profile", "open_profile", "profile_access_price"],
                "properties": {
                    "paid_profile": { "type": "boolean" },
                    "open_profile": { "type": "boolean" },
                    "profile_access_price": { "type": "number" },
                    "profile_access_price_3_months": { "type": "number" },
                    "profile_access_price_6_months": { "type": "number" },
                    "profile_access_price_12_months": { "type": "number" },
                    "offer_discount_percent": { "type": "integer", "description": "Promotional discount percentage (5-90)" },
                    "offer_expires_at": { "type": "string", "description": "ISO datetime when promo expires" }
                }
            }
        }),
        json!({
            "name": "privacy_update",
            "category": "Profile & Rates",
            "scope": "write",
            "description": "Update account privacy flags (public_profile, enable_2fa, enable_geoblocking).",
            "inputSchema": {
                "type": "object",
                "required": ["public_profile", "enable_2fa", "enable_geoblocking"],
                "properties": {
                    "public_profile": { "type": "boolean" },
                    "enable_2fa": { "type": "boolean" },
                    "enable_geoblocking": { "type": "boolean" }
                }
            }
        }),
        json!({
            "name": "kyc_verification_submit",
            "category": "Profile & Rates",
            "scope": "write",
            "description": "Submit creator identity verification document URLs for KYC review.",
            "inputSchema": {
                "type": "object",
                "required": ["files"],
                "properties": {
                    "files": { "type": "array", "items": { "type": "string" } }
                }
            }
        }),
        // 3. Content Publishing (Posts, Polls, Reels, Stories, Streams, AI Copywriting)
        json!({
            "name": "post_create_or_update",
            "category": "Publishing",
            "scope": "write",
            "description": "Create or edit a post with optional PPV price, pinned flag, scheduled release_date, expire_date, media attachments, and interactive poll options.",
            "inputSchema": {
                "type": "object",
                "required": ["text"],
                "properties": {
                    "id": { "type": "integer", "description": "Optional post ID to update" },
                    "text": { "type": "string" },
                    "price": { "type": "number", "description": "PPV unlock price ($0 for free/subscriber post)" },
                    "is_pinned": { "type": "boolean" },
                    "release_date": { "type": "string", "description": "Optional schedule date (YYYY-MM-DD HH:MM:SS)" },
                    "expire_date": { "type": "string", "description": "Optional expiration date" },
                    "attachments": { "type": "array", "items": { "type": "string" } },
                    "poll_answers": { "type": "array", "items": { "type": "string" } }
                }
            }
        }),
        json!({
            "name": "post_delete",
            "category": "Publishing",
            "scope": "write",
            "description": "Delete a post owned by the authenticated user.",
            "inputSchema": {
                "type": "object",
                "required": ["post_id"],
                "properties": { "post_id": { "type": "integer" } }
            }
        }),
        json!({
            "name": "reel_create",
            "category": "Publishing",
            "scope": "write",
            "description": "Publish a vertical short-form video Reel with optional PPV unlock price.",
            "inputSchema": {
                "type": "object",
                "required": ["caption"],
                "properties": {
                    "caption": { "type": "string" },
                    "video_url": { "type": "string" },
                    "cover_url": { "type": "string" },
                    "price": { "type": "number" }
                }
            }
        }),
        json!({
            "name": "story_create",
            "category": "Publishing",
            "scope": "write",
            "description": "Publish a 24h ephemeral Story or permanent Profile Highlight.",
            "inputSchema": {
                "type": "object",
                "required": ["caption"],
                "properties": {
                    "caption": { "type": "string" },
                    "media_url": { "type": "string" },
                    "bg_preset": { "type": "string" },
                    "is_highlight": { "type": "boolean" }
                }
            }
        }),
        json!({
            "name": "stream_start",
            "category": "Publishing",
            "scope": "write",
            "description": "Start an HLS Live Stream and provision RTMP ingest credentials.",
            "inputSchema": {
                "type": "object",
                "required": ["name"],
                "properties": {
                    "name": { "type": "string" },
                    "price": { "type": "number" },
                    "requires_subscription": { "type": "boolean" },
                    "is_public": { "type": "boolean" }
                }
            }
        }),
        json!({
            "name": "stream_stop",
            "category": "Publishing",
            "scope": "write",
            "description": "End an active live stream.",
            "inputSchema": {
                "type": "object",
                "required": ["stream_id"],
                "properties": { "stream_id": { "type": "integer" } }
            }
        }),
        json!({
            "name": "ai_caption_suggest",
            "category": "Publishing",
            "scope": "read",
            "description": "Generate high-converting creator post/reel copy for a given topic.",
            "inputSchema": {
                "type": "object",
                "properties": { "topic": { "type": "string" } }
            }
        }),
        // 4. Media Vault Management
        json!({
            "name": "vault_list",
            "category": "Media Vault",
            "scope": "read",
            "description": "List all media files in the user's Media Vault.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        json!({
            "name": "vault_register_asset",
            "category": "Media Vault",
            "scope": "write",
            "description": "Register a media asset URL in the user's Media Vault for attachment to posts or messages.",
            "inputSchema": {
                "type": "object",
                "required": ["file_url"],
                "properties": {
                    "filename": { "type": "string" },
                    "file_url": { "type": "string" },
                    "file_type": { "type": "string", "description": "image, video, or audio" }
                }
            }
        }),
        json!({
            "name": "vault_toggle_archive",
            "category": "Media Vault",
            "scope": "write",
            "description": "Archive or unarchive a Media Vault attachment.",
            "inputSchema": {
                "type": "object",
                "required": ["attachment_id"],
                "properties": { "attachment_id": { "type": "string" } }
            }
        }),
        // 5. Discovery, Feed & Social Engagement
        json!({
            "name": "feed_read",
            "category": "Discovery & Engagement",
            "scope": "read",
            "description": "Read the personalized feed with filter ('all', 'subscribed', 'media', 'photos', 'videos').",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "page": { "type": "integer" },
                    "filter": { "type": "string" }
                }
            }
        }),
        json!({
            "name": "creator_discover",
            "category": "Discovery & Engagement",
            "scope": "read",
            "description": "Search creators, posts, and live streams across the platform.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "query": { "type": "string" },
                    "filter": { "type": "string" }
                }
            }
        }),
        json!({
            "name": "profile_get",
            "category": "Discovery & Engagement",
            "scope": "read",
            "description": "Fetch a creator's public profile, posts, reels, stories, streams, and active promotional offer.",
            "inputSchema": {
                "type": "object",
                "required": ["username"],
                "properties": { "username": { "type": "string" } }
            }
        }),
        json!({
            "name": "post_react",
            "category": "Discovery & Engagement",
            "scope": "engage",
            "description": "Toggle a like reaction on a post, comment, or reel.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "post_id": { "type": "integer" },
                    "comment_id": { "type": "integer" },
                    "reel_id": { "type": "integer" }
                }
            }
        }),
        json!({
            "name": "post_comment",
            "category": "Discovery & Engagement",
            "scope": "engage",
            "description": "Add a comment to an unlocked post.",
            "inputSchema": {
                "type": "object",
                "required": ["post_id", "message"],
                "properties": {
                    "post_id": { "type": "integer" },
                    "message": { "type": "string" }
                }
            }
        }),
        json!({
            "name": "poll_vote",
            "category": "Discovery & Engagement",
            "scope": "engage",
            "description": "Vote in a post poll.",
            "inputSchema": {
                "type": "object",
                "required": ["poll_id", "answer_id"],
                "properties": {
                    "poll_id": { "type": "integer" },
                    "answer_id": { "type": "integer" }
                }
            }
        }),
        json!({
            "name": "social_follow_or_block",
            "category": "Discovery & Engagement",
            "scope": "engage",
            "description": "Follow/unfollow or block/unblock a target user.",
            "inputSchema": {
                "type": "object",
                "required": ["target_user_id", "action"],
                "properties": {
                    "target_user_id": { "type": "integer" },
                    "action": { "type": "string", "description": "'follow' or 'block'" }
                }
            }
        }),
        // 6. Direct & Broadcast Messaging
        json!({
            "name": "messenger_contacts_and_thread",
            "category": "Messaging",
            "scope": "messages",
            "description": "List messenger contacts or fetch conversation messages with a specific user_id.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "other_user_id": { "type": "integer", "description": "If provided, loads conversation thread with this user" }
                }
            }
        }),
        json!({
            "name": "messenger_send_dm",
            "category": "Messaging",
            "scope": "messages",
            "description": "Send a 1-on-1 direct message or paid PPV locked message.",
            "inputSchema": {
                "type": "object",
                "required": ["receiver_id", "message"],
                "properties": {
                    "receiver_id": { "type": "integer" },
                    "message": { "type": "string" },
                    "price": { "type": "number" },
                    "attachments": { "type": "array", "items": { "type": "string" } }
                }
            }
        }),
        json!({
            "name": "messenger_broadcast",
            "category": "Messaging",
            "scope": "messages",
            "description": "Send a mass broadcast message (free or PPV) to all active subscribers, followers, or a custom list.",
            "inputSchema": {
                "type": "object",
                "required": ["message"],
                "properties": {
                    "target": { "type": "string", "description": "'subscribers' or 'followers'" },
                    "list_id": { "type": "integer" },
                    "recipient_ids": { "type": "array", "items": { "type": "integer" } },
                    "message": { "type": "string" },
                    "price": { "type": "number" },
                    "attachments": { "type": "array", "items": { "type": "string" } }
                }
            }
        }),
        // 7. Wallet, Subscriptions, PPV Unlocks, Tips & Withdrawals
        json!({
            "name": "wallet_transact",
            "category": "Wallet & Monetization",
            "scope": "wallet",
            "description": "Execute a financial transaction (deposit, subscription, tip, post-unlock, message-unlock, reel-unlock, stream-unlock) with automatic spend-cap enforcement.",
            "inputSchema": {
                "type": "object",
                "required": ["payment_type"],
                "properties": {
                    "payment_type": { "type": "string", "description": "deposit, subscription, tip, post-unlock, message-unlock, reel-unlock, stream-unlock" },
                    "recipient_user_id": { "type": "integer" },
                    "post_id": { "type": "integer" },
                    "message_id": { "type": "integer" },
                    "reel_id": { "type": "integer" },
                    "stream_id": { "type": "integer" },
                    "amount": { "type": "number" },
                    "months": { "type": "integer", "description": "1, 3, 6, or 12 for subscription" },
                    "provider": { "type": "string", "description": "wallet/credit, stripe, paypal, crypto" }
                }
            }
        }),
        json!({
            "name": "wallet_withdraw",
            "category": "Wallet & Monetization",
            "scope": "wallet",
            "description": "Request a creator payout withdrawal (min $20.00) with immediate wallet escrow.",
            "inputSchema": {
                "type": "object",
                "required": ["amount"],
                "properties": {
                    "amount": { "type": "number" },
                    "payment_method": { "type": "string" },
                    "payment_identifier": { "type": "string" }
                }
            }
        }),
        // 8. Analytics & Notifications
        json!({
            "name": "analytics_and_notifications",
            "category": "Telemetry & Analytics",
            "scope": "read",
            "description": "Fetch comprehensive creator revenue breakdown (85/15 net split, subscriptions, PPV, tips), engagement metrics, notifications, and autonomous agent audit logs.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        // 9. Autonomous Persona Studio, Sharding & Soft Quota Governance (No-Inference)
        json!({
            "name": "shard_pool_telemetry",
            "category": "Shards & Quotas",
            "scope": "read",
            "description": "Inspect the 16 virtual shards, relay pool tiers (central, premium, studio), PostgreSQL cluster status, and soft token-bucket pacing metrics.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        json!({
            "name": "persona_upsert",
            "category": "Autonomous Personas",
            "scope": "write",
            "description": "Create or update an autonomous creator/fan persona residency assigned to a deterministic shard and relay pool tier.",
            "inputSchema": {
                "type": "object",
                "required": ["handle", "display_name"],
                "properties": {
                    "handle": { "type": "string", "description": "Unique persona handle (e.g. 'aria-neon-architect')" },
                    "display_name": { "type": "string", "description": "Display name" },
                    "description": { "type": "string", "description": "Persona identity bio" },
                    "focus": { "type": "string", "description": "creator, curator, community, doujin, or gravure" },
                    "pool_tier": { "type": "string", "description": "central, premium, or studio" },
                    "autonomy_mode": { "type": "string", "description": "full_autonomous or autonomous_co_pilot" },
                    "engagement_cadence": { "type": "string", "description": "high_touch, balanced, or curated" },
                    "principles": { "type": "array", "items": { "type": "string" } },
                    "interests": { "type": "array", "items": { "type": "string" } },
                    "methods": { "type": "array", "items": { "type": "string" } },
                    "direction": { "type": "string" }
                }
            }
        }),
        json!({
            "name": "persona_get",
            "category": "Autonomous Personas",
            "scope": "read",
            "description": "Fetch an autonomous persona by ID or handle (or list all personas for the account if omitted), including numbered practice revisions and recorded activities.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "persona_id": { "type": "string", "description": "Optional persona ID or handle" }
                }
            }
        }),
        json!({
            "name": "persona_update_practice",
            "category": "Autonomous Personas",
            "scope": "write",
            "description": "Append a new numbered practice revision to an autonomous persona with optimistic concurrency checking (expected_revision).",
            "inputSchema": {
                "type": "object",
                "required": ["persona_id", "direction"],
                "properties": {
                    "persona_id": { "type": "string", "description": "Persona ID or handle" },
                    "expected_revision": { "type": "integer", "description": "Optional current revision for optimistic concurrency check" },
                    "focus": { "type": "string" },
                    "principles": { "type": "array", "items": { "type": "string" } },
                    "interests": { "type": "array", "items": { "type": "string" } },
                    "methods": { "type": "array", "items": { "type": "string" } },
                    "direction": { "type": "string", "description": "Updated creative & engagement direction" }
                }
            }
        }),
        json!({
            "name": "persona_brief",
            "category": "Autonomous Personas",
            "scope": "read",
            "description": "Deterministically compile a no-inference autonomous execution brief from the persona's latest practice revision, recent activity continuity, and optional adult character references.",
            "inputSchema": {
                "type": "object",
                "required": ["persona_id", "intent"],
                "properties": {
                    "persona_id": { "type": "string", "description": "Persona ID or handle" },
                    "intent": { "type": "string", "description": "Session goal or content drop objective" },
                    "target_audience": { "type": "string", "description": "Optional audience segment" },
                    "characters": {
                        "type": "array",
                        "description": "Optional adult (18+) character / model references for brief compilation",
                        "items": {
                            "type": "object",
                            "required": ["name"],
                            "properties": {
                                "name": { "type": "string" },
                                "role": { "type": "string" },
                                "visual_notes": { "type": "string" }
                            }
                        }
                    }
                }
            }
        }),
        json!({
            "name": "persona_record_activity",
            "category": "Autonomous Personas",
            "scope": "engage",
            "description": "Append an immutable activity record with work attribution and compiled brief continuity to an autonomous persona's ledger.",
            "inputSchema": {
                "type": "object",
                "required": ["persona_id", "action_type", "description"],
                "properties": {
                    "id": { "type": "string", "description": "Optional deterministic activity ID for exact replay idempotency" },
                    "persona_id": { "type": "string", "description": "Persona ID or handle" },
                    "action_type": { "type": "string", "description": "e.g. post_create, comment_reply, broadcast_drop" },
                    "target_resource_id": { "type": "string" },
                    "work_ids": { "type": "array", "items": { "type": "string" }, "description": "Attributed post/reel/story/vault IDs (enforces cross-persona uniqueness)" },
                    "characters": {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "required": ["name"],
                            "properties": {
                                "name": { "type": "string" },
                                "role": { "type": "string" },
                                "visual_notes": { "type": "string" }
                            }
                        }
                    },
                    "description": { "type": "string" },
                    "practice_revision": { "type": "integer" }
                }
            }
        }),
        json!({
            "name": "persona_bootstrap_roster",
            "category": "Autonomous Personas",
            "scope": "write",
            "description": "Non-destructively install the curated 4-persona Commissioned Studio Roster (Mika, Ren, Sora, Nagi) while preserving any evolved revisions on existing personas.",
            "inputSchema": { "type": "object", "properties": {} }
        }),
        json!({
            "name": "swarm_batch_execute",
            "category": "Shards & Quotas",
            "scope": "write",
            "description": "Execute a multi-step autonomous swarm action batch with per-step shard routing, soft-quota telemetry, and optional stop_on_error control.",
            "inputSchema": {
                "type": "object",
                "required": ["actions"],
                "properties": {
                    "stop_on_error": { "type": "boolean", "description": "Halt remaining batch steps if a step fails or enters cooldown advisory" },
                    "actions": {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "required": ["tool_name"],
                            "properties": {
                                "tool_name": { "type": "string" },
                                "arguments": { "type": "object" },
                                "idempotency_key": { "type": "string" }
                            }
                        }
                    }
                }
            }
        }),
    ]
}

pub fn get_mcp_resources_catalog() -> Vec<Value> {
    vec![
        json!({
            "uri": "heav3ns://account/profile",
            "name": "Authenticated Account Profile & Wallet",
            "description": "Current user profile, subscription pricing, active promotional offer, and wallet balance.",
            "mimeType": "application/json"
        }),
        json!({
            "uri": "heav3ns://account/analytics",
            "name": "Creator Revenue & Engagement Analytics",
            "description": "Real-time 85/15 creator earnings breakdown, subscriber counts, and engagement telemetry.",
            "mimeType": "application/json"
        }),
        json!({
            "uri": "heav3ns://account/audit-logs",
            "name": "Autonomous Agent Audit Trail",
            "description": "Immutable log of all MCP tool calls, shard routing, soft quota pacing, and wallet spend executed by agents.",
            "mimeType": "application/json"
        }),
        json!({
            "uri": "heav3ns://account/personas",
            "name": "Autonomous Personas & Practice Revisions",
            "description": "Registered autonomous personas, numbered practice revisions, and continuity logs.",
            "mimeType": "application/json"
        }),
        json!({
            "uri": "heav3ns://platform/shards",
            "name": "16-Shard & Relay Pool Telemetry",
            "description": "Cluster-wide virtual shard load, central/premium/studio pool policies, and PostgreSQL status.",
            "mimeType": "application/json"
        }),
        json!({
            "uri": "heav3ns://platform/featured-creators",
            "name": "Featured Platform Creators",
            "description": "Public directory of featured creators and subscription offers.",
            "mimeType": "application/json"
        }),
    ]
}

pub fn get_mcp_prompts_catalog() -> Vec<Value> {
    vec![
        json!({
            "name": "autonomous_creator_onboarding",
            "description": "End-to-end workflow for an AI agent to register a creator account, configure bio & subscription tiers, launch a promotional offer, and publish a pinned welcome post with a poll.",
            "arguments": [
                { "name": "persona_name", "description": "Creator display name", "required": true },
                { "name": "niche", "description": "Content niche (e.g. digital art, fitness, music)", "required": true }
            ]
        }),
        json!({
            "name": "autonomous_daily_growth_and_engagement",
            "description": "Daily autonomous loop: check analytics & notifications, publish a scheduled post or PPV reel, reply to fan comments, and broadcast a VIP offer to subscribers.",
            "arguments": [
                { "name": "daily_budget_usd", "description": "Max spend budget for the session", "required": false }
            ]
        }),
        json!({
            "name": "autonomous_fan_curator",
            "description": "Discover creators in a target niche, subscribe or unlock top PPV posts within spend guardrails, and vote on creator polls.",
            "arguments": [
                { "name": "interest_query", "description": "Search topic or creator handle", "required": true }
            ]
        }),
    ]
}

/// GET /api/mcp/manifest — Public discovery manifest for MCP clients & autonomous agents
pub async fn get_mcp_manifest() -> impl IntoResponse {
    Json(json!({
        "success": true,
        "protocol": "model-context-protocol",
        "protocolVersion": "2024-11-05",
        "serverInfo": {
            "name": "heav3ns-publishing-mcp-server",
            "version": "2.4.0",
            "vendor": "HEAV3NS-Publishing",
            "description": "Model Context Protocol (MCP) Server for autonomous AI agents to create personas, operate creator & fan accounts, and self-regulate via 16-shard token-bucket soft quotas without server-side LLM inference."
        },
        "endpoints": {
            "jsonrpc": "/api/mcp",
            "manifest": "/api/mcp/manifest",
            "rest_tool_call": "/api/mcp/tools/:tool_name",
            "agent_tokens": "/api/agent/tokens",
            "agent_shards": "/api/agent/shards",
            "agent_personas": "/api/agent/personas",
            "creator_analytics": "/api/analytics/overview"
        },
        "authentication": {
            "headers": [
                "Authorization: Bearer <hp_mcp_token_or_jwt>",
                "X-Agent-Token: <hp_mcp_token>",
                "X-Idempotency-Key: <optional_uuid>"
            ],
            "self_provisioning_tools": ["account_register", "account_login"],
            "scopes": ["read", "write", "engage", "monetize", "messages", "wallet", "admin"],
            "pool_tiers": ["central", "premium", "studio"]
        },
        "tools": get_mcp_tools_catalog(),
        "resources": get_mcp_resources_catalog(),
        "prompts": get_mcp_prompts_catalog()
    }))
}

/// POST /api/mcp/tools/:tool_name — Direct REST bridge for calling any MCP tool
pub async fn call_mcp_tool_rest(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    headers: axum::http::HeaderMap,
    Path(tool_name): Path<String>,
    Json(args): Json<Option<Value>>,
) -> Result<impl IntoResponse, AppError> {
    let mut arguments = args.unwrap_or_else(|| json!({}));
    if let Some(hdr_idem) = headers
        .get("x-idempotency-key")
        .and_then(|v| v.to_str().ok())
        .map(|s| s.trim())
        .filter(|s| !s.is_empty())
    {
        if let Some(obj) = arguments.as_object_mut() {
            obj.entry("idempotency_key".to_string())
                .or_insert_with(|| Value::String(hdr_idem.to_string()));
        }
    }
    let result = execute_mcp_tool(&state, auth.as_ref(), &tool_name, &arguments)?;
    let shard_hdr = result
        .get("_quota_governance")
        .and_then(|q| q.get("shard_id"))
        .and_then(|v| v.as_i64())
        .unwrap_or(0)
        .to_string();
    let pool_hdr = result
        .get("_quota_governance")
        .and_then(|q| q.get("pool_tier"))
        .and_then(|v| v.as_str())
        .unwrap_or("central")
        .to_string();
    let mode_hdr = result
        .get("_quota_governance")
        .and_then(|q| q.get("throttle_mode"))
        .and_then(|v| v.as_str())
        .unwrap_or("smooth")
        .to_string();
    let pacing_ms_val = result
        .get("_quota_governance")
        .and_then(|q| q.get("pacing_delay_ms"))
        .and_then(|v| v.as_i64())
        .unwrap_or(0);
    let pacing_hdr = pacing_ms_val.to_string();

    if mode_hdr == "soft_paced" && pacing_ms_val > 0 {
        tokio::time::sleep(std::time::Duration::from_millis(
            (pacing_ms_val as u64).min(250),
        ))
        .await;
    }

    Ok((
        [
            ("x-heav3ns-shard", shard_hdr),
            ("x-heav3ns-pool", pool_hdr),
            ("x-ratelimit-throttle-mode", mode_hdr),
            ("x-ratelimit-pacing-ms", pacing_hdr),
        ],
        Json(json!({
            "success": true,
            "tool": tool_name,
            "result": result
        })),
    ))
}

/// POST /api/mcp — Standard JSON-RPC 2.0 Model Context Protocol endpoint
pub async fn handle_mcp_rpc(
    State(state): State<AppState>,
    OptionalAuthUser(auth): OptionalAuthUser,
    headers: axum::http::HeaderMap,
    Json(req): Json<JsonRpcRequest>,
) -> impl IntoResponse {
    let rpc_id = req.id.clone().unwrap_or(Value::Null);
    let params = req.params.unwrap_or_else(|| json!({}));
    let header_idem = headers
        .get("x-idempotency-key")
        .and_then(|v| v.to_str().ok())
        .map(|s| s.trim().to_string())
        .filter(|s| !s.is_empty());

    let mut shard_hdr = auth
        .as_ref()
        .and_then(|u| u.shard_id)
        .unwrap_or(0)
        .to_string();
    let mut pool_hdr = auth
        .as_ref()
        .and_then(|u| u.pool_tier.clone())
        .unwrap_or_else(|| "central".to_string());
    let mut mode_hdr = "smooth".to_string();
    let mut pacing_hdr = "0".to_string();

    let outcome: Result<Value, AppError> = match req.method.as_str() {
        "initialize" => Ok(json!({
            "protocolVersion": "2024-11-05",
            "capabilities": {
                "tools": { "listChanged": false },
                "resources": { "subscribe": false, "listChanged": false },
                "prompts": { "listChanged": false }
            },
            "serverInfo": {
                "name": "heav3ns-publishing-mcp-server",
                "version": "2.4.0"
            }
        })),
        "notifications/initialized" | "ping" => Ok(json!({ "status": "ok" })),
        "tools/list" => Ok(json!({
            "tools": get_mcp_tools_catalog()
        })),
        "resources/list" => Ok(json!({
            "resources": get_mcp_resources_catalog()
        })),
        "resources/read" => {
            let uri = params
                .get("uri")
                .and_then(|v| v.as_str())
                .unwrap_or("heav3ns://account/profile");
            read_mcp_resource(&state, auth.as_ref(), uri).map(|contents| {
                json!({
                    "contents": [{
                        "uri": uri,
                        "mimeType": "application/json",
                        "text": serde_json::to_string_pretty(&contents).unwrap_or_default()
                    }]
                })
            })
        }
        "prompts/list" => Ok(json!({
            "prompts": get_mcp_prompts_catalog()
        })),
        "prompts/get" => {
            let name = params
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or("autonomous_creator_onboarding");
            let args = params.get("arguments").cloned().unwrap_or_else(|| json!({}));
            Ok(build_mcp_prompt(name, &args))
        }
        "tools/call" => {
            let tool_name = params
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or_default();
            let mut arguments = params
                .get("arguments")
                .cloned()
                .unwrap_or_else(|| json!({}));
            if let Some(ref hid) = header_idem {
                if let Some(obj) = arguments.as_object_mut() {
                    obj.entry("idempotency_key".to_string())
                        .or_insert_with(|| Value::String(hid.clone()));
                }
            }
            match execute_mcp_tool(&state, auth.as_ref(), tool_name, &arguments) {
                Ok(val) => {
                    if let Some(gov) = val.get("_quota_governance") {
                        if let Some(sid) = gov.get("shard_id").and_then(|v| v.as_i64()) {
                            shard_hdr = sid.to_string();
                        }
                        if let Some(pt) = gov.get("pool_tier").and_then(|v| v.as_str()) {
                            pool_hdr = pt.to_string();
                        }
                        if let Some(tm) = gov.get("throttle_mode").and_then(|v| v.as_str()) {
                            mode_hdr = tm.to_string();
                        }
                        if let Some(pm) = gov.get("pacing_delay_ms").and_then(|v| v.as_i64()) {
                            pacing_hdr = pm.to_string();
                            if mode_hdr == "soft_paced" && pm > 0 {
                                tokio::time::sleep(std::time::Duration::from_millis(
                                    (pm as u64).min(250),
                                ))
                                .await;
                            }
                        }
                    }
                    Ok(json!({
                        "content": [{
                            "type": "text",
                            "text": serde_json::to_string_pretty(&val).unwrap_or_default()
                        }],
                        "structuredContent": val,
                        "isError": false
                    }))
                }
                Err(err) => {
                    if let AppError::QuotaCooldownAdvisory(_, ref dec) = err {
                        shard_hdr = dec.shard_id.to_string();
                        pool_hdr = dec.pool_tier.clone();
                        mode_hdr = dec.throttle_mode.clone();
                        pacing_hdr = dec.retry_after_ms.to_string();
                    }
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
                    Ok(json!({
                        "content": [{
                            "type": "text",
                            "text": format!("Error executing tool '{tool_name}': {msg}")
                        }],
                        "structuredContent": {
                            "success": false,
                            "error": msg
                        },
                        "isError": true
                    }))
                }
            }
        }
        other => Err(AppError::BadRequest(format!(
            "Unsupported MCP JSON-RPC method: {other}"
        ))),
    };

    let resp_headers = [
        ("x-heav3ns-shard", shard_hdr),
        ("x-heav3ns-pool", pool_hdr),
        ("x-ratelimit-throttle-mode", mode_hdr),
        ("x-ratelimit-pacing-ms", pacing_hdr),
    ];

    match outcome {
        Ok(result) => (
            StatusCode::OK,
            resp_headers,
            Json(json!({
                "jsonrpc": "2.0",
                "id": rpc_id,
                "result": result
            })),
        ),
        Err(err) => {
            let (code, msg) = match err {
                AppError::Unauthorized(m) => (-32001, m),
                AppError::Forbidden(m) => (-32003, m),
                AppError::NotFound(m) => (-32004, m),
                AppError::Conflict(m) => (-32009, m),
                AppError::Unprocessable(m) => (-32602, m),
                AppError::QuotaCooldownAdvisory(m, _) => (-32029, m),
                AppError::BadRequest(m) => (-32601, m),
                AppError::Internal(m) => (-32603, m),
            };
            (
                StatusCode::OK,
                resp_headers,
                Json(json!({
                    "jsonrpc": "2.0",
                    "id": rpc_id,
                    "error": {
                        "code": code,
                        "message": msg
                    }
                })),
            )
        }
    }
}

fn read_mcp_resource(
    state: &AppState,
    auth: Option<&AuthUser>,
    uri: &str,
) -> Result<Value, AppError> {
    let conn = state.lock_db();
    match uri {
        "heav3ns://platform/featured-creators" => {
            let mut stmt = conn.prepare("SELECT id FROM users WHERE public_profile = 1 ORDER BY id ASC LIMIT 9")?;
            let ids: Vec<i64> = stmt
                .query_map([], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            let viewer_id = auth.map(|a| a.user_id);
            let mut profiles = Vec::new();
            for id in ids {
                if let Ok(p) = get_user_public_profile(&conn, id, viewer_id) {
                    profiles.push(p);
                }
            }
            Ok(json!({ "featured_creators": profiles }))
        }
        "heav3ns://account/profile" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let profile = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            Ok(json!({ "profile": profile }))
        }
        "heav3ns://account/audit-logs" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let logs = list_user_agent_audit_logs(&conn, user.user_id, 50)?;
            Ok(json!({ "audit_logs": logs }))
        }
        "heav3ns://account/personas" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let personas = crate::handlers::list_user_agent_personas(&conn, Some(user.user_id))?;
            Ok(json!({ "personas": personas }))
        }
        "heav3ns://platform/shards" => {
            let telemetry = crate::sharding::build_shard_pool_telemetry(state, &conn);
            Ok(json!({ "shard_telemetry": telemetry }))
        }
        "heav3ns://account/analytics" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            drop(conn);
            execute_mcp_tool(state, auth, "analytics_and_notifications", &json!({}))
        }
        other => Err(AppError::NotFound(format!("Unknown MCP resource URI: {other}"))),
    }
}

fn build_mcp_prompt(name: &str, args: &Value) -> Value {
    let persona = args
        .get("persona_name")
        .and_then(|v| v.as_str())
        .unwrap_or("Nova Synth");
    let niche = args
        .get("niche")
        .or_else(|| args.get("interest_query"))
        .and_then(|v| v.as_str())
        .unwrap_or("digital art & behind-the-scenes studio production");

    let text = match name {
        "autonomous_creator_onboarding" => format!(
            "You are operating an autonomous creator account for {persona} in the {niche} niche on HEAV3NS-Publishing.\n\
             Step 1: Call `account_register` (or `account_whoami` if already authenticated) to provision your account and `hp_mcp_*` agent token.\n\
             Step 2: Call `persona_upsert` to establish your autonomous persona residency on a dedicated virtual shard & relay pool.\n\
             Step 3: Call `profile_update` and `rates_and_offers_update` to configure subscription bundles.\n\
             Step 4: Call `post_create_or_update` to publish a pinned welcome post with an interactive fan poll."
        ),
        "autonomous_daily_growth_and_engagement" => {
            "Execute the daily autonomous creator growth loop:\n\
             1. Call `analytics_and_notifications` and `shard_pool_telemetry` to inspect revenue (85/15 split), soft quota tokens, and unread comments.\n\
             2. Call `persona_brief` to compile a deterministic execution brief from your latest practice revision.\n\
             3. Call `post_create_or_update` (or `reel_create`) to publish fresh content and `persona_record_activity` to log continuity."
                .to_string()
        }
        _ => format!(
            "Execute autonomous fan curation for '{niche}':\n\
             1. Call `creator_discover` with query='{niche}'.\n\
             2. Inspect creator profiles with `profile_get`.\n\
             3. Use `wallet_transact` within your configured spend guardrails to subscribe or unlock PPV posts, and engage via `post_react`, `post_comment`, and `poll_vote`."
        ),
    };

    json!({
        "description": format!("MCP workflow prompt: {name}"),
        "messages": [{
            "role": "user",
            "content": {
                "type": "text",
                "text": text
            }
        }]
    })
}

fn record_audit(
    conn: &rusqlite::Connection,
    auth: &AuthUser,
    tool_name: &str,
    resource_type: &str,
    resource_id: Option<String>,
    amount_spent: f64,
    details: Value,
) {
    let shard_id = auth
        .shard_id
        .unwrap_or_else(|| crate::sharding::compute_user_shard(auth.user_id, &auth.username));
    let pool_tier = auth
        .pool_tier
        .clone()
        .unwrap_or_else(|| "central".to_string());
    let units = crate::sharding::action_unit_cost(tool_name);
    let _ = conn.execute(
        "INSERT INTO agent_audit_logs
         (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, action_units, shard_id, pool_tier, throttle_mode, pacing_delay_ms, details)
         VALUES (?1, ?2, ?3, ?4, ?5, 'success', ?6, ?7, ?8, ?9, 'smooth', 0, ?10)",
        params![
            auth.user_id,
            auth.agent_token_id,
            tool_name,
            resource_type,
            resource_id,
            round_money(amount_spent),
            units,
            shard_id,
            pool_tier,
            details.to_string()
        ],
    );
}

struct ProvisionAgentTokenSpec<'a> {
    user_id: i64,
    agent_name: &'a str,
    scopes: &'a [String],
    max_tx: f64,
    daily_lim: f64,
    pool_tier: Option<&'a str>,
    daily_action_quota: Option<i64>,
}

fn provision_agent_token_internal(
    conn: &rusqlite::Connection,
    spec: &ProvisionAgentTokenSpec<'_>,
) -> Result<(i64, String, i64, String, i64), AppError> {
    let user_id = spec.user_id;
    let raw_secret = format!("hp_mcp_{}_{}", user_id, Uuid::new_v4().simple());
    let prefix = format!("{}...", &raw_secret[..14.min(raw_secret.len())]);
    let token_hash = hash_agent_token(&raw_secret);
    let scopes_json = serde_json::to_string(spec.scopes).unwrap_or_else(|_| "[]".into());
    let shard_id = crate::sharding::compute_user_shard(user_id, &raw_secret);
    let policy = crate::sharding::get_pool_tier_policy(spec.pool_tier.unwrap_or("central"));
    let action_quota = spec
        .daily_action_quota
        .filter(|&q| q >= 20)
        .unwrap_or(policy.daily_action_quota);

    conn.execute(
        "INSERT INTO agent_tokens
         (user_id, name, token_prefix, token_hash, scopes, max_spend_per_tx, daily_spend_limit,
          pool_tier, shard_id, daily_action_quota, hourly_burst_capacity, refill_rate_per_sec, is_active)
         VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, 1)",
        params![
            user_id,
            spec.agent_name,
            prefix,
            token_hash,
            scopes_json,
            spec.max_tx,
            spec.daily_lim,
            policy.tier,
            shard_id,
            action_quota,
            policy.burst_capacity,
            policy.refill_rate_per_sec
        ],
    )?;
    let tid = conn.last_insert_rowid();
    let today_str = chrono::Utc::now().format("%Y-%m-%d").to_string();
    let now_ms = chrono::Utc::now().timestamp_millis();
    let _ = conn.execute(
        "INSERT OR REPLACE INTO agent_quota_buckets
         (agent_token_id, user_id, shard_id, pool_tier, available_tokens, burst_capacity, refill_rate_per_sec, daily_quota_limit, daily_units_used, daily_window_date, last_refill_epoch_ms)
         VALUES (?1, ?2, ?3, ?4, ?5, ?5, ?6, ?7, 0.0, ?8, ?9)",
        params![
            tid,
            user_id,
            shard_id,
            policy.tier,
            policy.burst_capacity,
            policy.refill_rate_per_sec,
            action_quota,
            today_str,
            now_ms
        ],
    );
    Ok((tid, raw_secret, shard_id, policy.tier, action_quota))
}

pub fn execute_mcp_tool(
    state: &AppState,
    auth: Option<&AuthUser>,
    tool_name: &str,
    args: &Value,
) -> Result<Value, AppError> {
    let idem_key = args
        .get("idempotency_key")
        .and_then(|v| v.as_str())
        .map(|s| s.trim().to_string())
        .filter(|s| !s.is_empty());
    let req_hash = crate::sharding::compute_request_hash(tool_name, Some(args));

    let (decision, prev_max_audit_id) = {
        let conn = state.lock_db();
        let prev_max: i64 = if let Some(user) = auth {
            conn.query_row(
                "SELECT COALESCE(MAX(id), 0) FROM agent_audit_logs WHERE user_id = ?1",
                params![user.user_id],
                |r| r.get(0),
            )
            .unwrap_or(0)
        } else {
            0
        };
        let dec = crate::sharding::evaluate_and_consume_quota_with_hash(
            &conn,
            auth,
            tool_name,
            idem_key.as_deref(),
            Some(&req_hash),
        )?;
        (dec, prev_max)
    };

    if decision.idempotent_replay {
        if let Some(mut cached) = decision.cached_response.clone() {
            if let Some(obj) = cached.as_object_mut() {
                let gov = serde_json::to_value(&decision).unwrap_or_else(|_| json!({}));
                obj.insert("_quota_governance".to_string(), gov.clone());
                obj.insert("_heav3ns_governance".to_string(), gov);
            }
            return Ok(cached);
        }
    }

    if !decision.allowed {
        return Ok(json!({
            "success": false,
            "throttled": true,
            "status": "cooldown_advisory",
            "message": format!(
                "Soft quota cooldown advisory on shard #{} ({} pool): daily action budget ({}/{} units) reached. Please pace autonomous loop for {}ms or use read/introspection tools.",
                decision.shard_id,
                decision.pool_tier,
                decision.daily_units_used,
                decision.daily_quota_limit,
                decision.retry_after_ms
            ),
            "_quota_governance": decision,
            "_heav3ns_governance": decision
        }));
    }

    let mut raw_res = match execute_mcp_tool_inner(state, auth, tool_name, args) {
        Ok(res) => res,
        Err(err) => {
            let conn = state.lock_db();
            crate::sharding::refund_quota_consumption(&conn, auth, &decision);
            if let (Some(user), Some(ref key)) = (auth, &idem_key) {
                crate::sharding::clear_in_flight_idempotency(&conn, user.user_id, key);
            }
            return Err(err);
        }
    };

    if let Some(obj) = raw_res.as_object_mut() {
        let gov = serde_json::to_value(&decision).unwrap_or_else(|_| json!({}));
        obj.insert("_quota_governance".to_string(), gov.clone());
        obj.insert("_heav3ns_governance".to_string(), gov);
    }

    if let (Some(user), Some(ref key)) = (auth, &idem_key) {
        let conn = state.lock_db();
        crate::sharding::save_idempotent_action_with_hash(
            &conn,
            user.user_id,
            key,
            tool_name,
            Some(&req_hash),
            &raw_res,
        );
    }

    // Update newly inserted audit log entry (or insert one if tool did not call record_audit)
    // without ever clobbering a prior tool's audit log row.
    if let Some(user) = auth {
        let conn = state.lock_db();
        let updated_rows = conn
            .execute(
                "UPDATE agent_audit_logs
                 SET shard_id = ?1, pool_tier = ?2, action_units = ?3, throttle_mode = ?4, pacing_delay_ms = ?5, idempotency_key = ?6
                 WHERE id > ?7 AND user_id = ?8 AND tool_name = ?9",
                params![
                    decision.shard_id,
                    decision.pool_tier,
                    decision.action_units,
                    decision.throttle_mode,
                    decision.pacing_delay_ms,
                    idem_key,
                    prev_max_audit_id,
                    user.user_id,
                    tool_name
                ],
            )
            .unwrap_or(0);

        if updated_rows == 0 && user.agent_token_id.is_some() {
            let _ = conn.execute(
                "INSERT INTO agent_audit_logs
                 (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent,
                  action_units, shard_id, pool_tier, throttle_mode, pacing_delay_ms, idempotency_key, details)
                 VALUES (?1, ?2, ?3, 'mcp_tool', NULL, 'success', 0.0, ?4, ?5, ?6, ?7, ?8, ?9, '{}')",
                params![
                    user.user_id,
                    user.agent_token_id,
                    tool_name,
                    decision.action_units,
                    decision.shard_id,
                    decision.pool_tier,
                    decision.throttle_mode,
                    decision.pacing_delay_ms,
                    idem_key,
                ],
            );
        }
    }

    Ok(raw_res)
}

fn execute_mcp_tool_inner(
    state: &AppState,
    auth: Option<&AuthUser>,
    tool_name: &str,
    args: &Value,
) -> Result<Value, AppError> {
    match tool_name {
        "account_register" => {
            let name = args
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let email = args
                .get("email")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim()
                .to_lowercase();
            let password = args
                .get("password")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();

            if name.is_empty() || email.is_empty() || password.len() < 6 {
                return Err(AppError::Unprocessable(
                    "name, valid email, and password (min 6 chars) are required.".into(),
                ));
            }

            let raw_username = args
                .get("username")
                .and_then(|v| v.as_str())
                .map(|s| s.trim().to_lowercase())
                .filter(|s| !s.is_empty())
                .unwrap_or_else(|| {
                    email
                        .split('@')
                        .next()
                        .unwrap_or("agent")
                        .chars()
                        .filter(|c| c.is_alphanumeric() || *c == '_')
                        .collect()
                });
            let username = if raw_username.is_empty() {
                format!("agent_{}", &Uuid::new_v4().simple().to_string()[..6])
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
                .map_err(|_| AppError::Internal("Password hash error".into()))?;
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

            if let Some(ref_code) = args.get("referral_code").and_then(|v| v.as_str()) {
                let clean_ref = ref_code.trim();
                if !clean_ref.is_empty() {
                    if let Ok(referrer_id) = tx.query_row::<i64, _, _>(
                        "SELECT id FROM users WHERE UPPER(referral_code) = UPPER(?1) AND id != ?2",
                        params![clean_ref, user_id],
                        |r| r.get(0),
                    ) {
                        tx.execute(
                            "UPDATE wallets SET total = ROUND(total + 5.0, 2) WHERE user_id = ?1",
                            params![referrer_id],
                        )?;
                        tx.execute(
                            "UPDATE wallets SET total = ROUND(total + 5.0, 2) WHERE user_id = ?1",
                            params![user_id],
                        )?;
                        let _ = tx.execute(
                            "INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, bonus_amount)
                             VALUES (?1, ?2, ?3, 5.0)",
                            params![referrer_id, user_id, clean_ref.to_uppercase()],
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

            let jwt_token = create_jwt(user_id, &username, 2)?;
            let issue_agent = args
                .get("issue_agent_token")
                .and_then(|v| v.as_bool())
                .unwrap_or(true);
            let mut agent_token_secret: Option<String> = None;
            if issue_agent {
                let agent_name = args
                    .get("agent_name")
                    .and_then(|v| v.as_str())
                    .unwrap_or("Primary Autonomous Agent");
                let max_tx = round_money(
                    args.get("max_spend_per_tx")
                        .and_then(|v| v.as_f64())
                        .unwrap_or(50.0)
                        .max(0.0),
                );
                let daily_lim = round_money(
                    args.get("daily_spend_limit")
                        .and_then(|v| v.as_f64())
                        .unwrap_or(200.0)
                        .max(0.0),
                );
                let scopes = vec![
                    "read".into(),
                    "write".into(),
                    "engage".into(),
                    "monetize".into(),
                    "messages".into(),
                    "wallet".into(),
                ];
                let (tid, secret, _, _, _) = provision_agent_token_internal(
                    &conn,
                    &ProvisionAgentTokenSpec {
                        user_id,
                        agent_name,
                        scopes: &scopes,
                        max_tx,
                        daily_lim,
                        pool_tier: args.get("pool_tier").and_then(|v| v.as_str()),
                        daily_action_quota: args.get("daily_action_quota").and_then(|v| v.as_i64()),
                    },
                )?;
                let _ = conn.execute(
                    "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, details)
                     VALUES (?1, ?2, 'account_register', 'user', ?3, 'success', 0.0, ?4)",
                    params![
                        user_id,
                        tid,
                        user_id.to_string(),
                        json!({ "username": username, "agent_name": agent_name }).to_string()
                    ],
                );
                agent_token_secret = Some(secret);
            }

            let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;
            Ok(json!({
                "success": true,
                "user": profile,
                "jwt_token": jwt_token,
                "agent_token": agent_token_secret
            }))
        }

        "account_login" => {
            let identifier = args
                .get("email")
                .or_else(|| args.get("username"))
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let password = args
                .get("password")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let conn = state.lock_db();
            let (user_id, username, password_hash, role_id): (i64, String, String, i64) = conn
                .query_row(
                    "SELECT id, username, password_hash, role_id FROM users WHERE email = ?1 OR username = ?1",
                    params![identifier],
                    |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?, r.get(3)?)),
                )
                .map_err(|_| AppError::Unauthorized("Invalid credentials".into()))?;

            if !bcrypt::verify(password, &password_hash).unwrap_or(false) {
                return Err(AppError::Unauthorized("Invalid credentials".into()));
            }
            let jwt_token = create_jwt(user_id, &username, role_id)?;
            let mut agent_token_secret: Option<String> = None;
            if args
                .get("issue_agent_token")
                .and_then(|v| v.as_bool())
                .unwrap_or(false)
            {
                let agent_name = args
                    .get("agent_name")
                    .and_then(|v| v.as_str())
                    .unwrap_or("Autonomous Session Agent");
                let max_tx = round_money(
                    args.get("max_spend_per_tx")
                        .and_then(|v| v.as_f64())
                        .unwrap_or(50.0)
                        .max(0.0),
                );
                let daily_lim = round_money(
                    args.get("daily_spend_limit")
                        .and_then(|v| v.as_f64())
                        .unwrap_or(200.0)
                        .max(0.0),
                );
                let scopes = vec![
                    "read".into(),
                    "write".into(),
                    "engage".into(),
                    "monetize".into(),
                    "messages".into(),
                    "wallet".into(),
                ];
                let (_, secret, _, _, _) = provision_agent_token_internal(
                    &conn,
                    &ProvisionAgentTokenSpec {
                        user_id,
                        agent_name,
                        scopes: &scopes,
                        max_tx,
                        daily_lim,
                        pool_tier: args.get("pool_tier").and_then(|v| v.as_str()),
                        daily_action_quota: args.get("daily_action_quota").and_then(|v| v.as_i64()),
                    },
                )?;
                agent_token_secret = Some(secret);
            }
            let profile = get_user_public_profile(&conn, user_id, Some(user_id))?;
            Ok(json!({
                "success": true,
                "user": profile,
                "jwt_token": jwt_token,
                "agent_token": agent_token_secret
            }))
        }

        "account_whoami" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let conn = state.lock_db();
            let profile = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            let unread_notifications: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM notifications WHERE to_user_id = ?1 AND is_read = 0",
                    params![user.user_id],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            let unread_messages: i64 = conn
                .query_row(
                    "SELECT COUNT(*) FROM user_messages WHERE receiver_id = ?1 AND is_seen = 0",
                    params![user.user_id],
                    |r| r.get(0),
                )
                .unwrap_or(0);
            record_audit(&conn, user, "account_whoami", "user", Some(user.user_id.to_string()), 0.0, json!({}));
            Ok(json!({
                "authenticated": true,
                "user": profile,
                "unread_notifications": unread_notifications,
                "unread_messages": unread_messages,
                "auth_mode": if user.agent_token_id.is_some() { "mcp_agent_token" } else { "jwt" },
                "agent_token_id": user.agent_token_id,
                "shard_id": user.shard_id,
                "pool_tier": user.pool_tier,
                "scopes": user.agent_scopes,
                "max_spend_per_tx": user.max_spend_per_tx,
                "daily_spend_limit": user.daily_spend_limit
            }))
        }

        "agent_token_create" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            if user.agent_token_id.is_some() {
                user.require_scope("admin")?;
            }
            let name = args
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or("Autonomous Agent")
                .trim();
            if name.is_empty() {
                return Err(AppError::Unprocessable("Agent token name required".into()));
            }
            let scopes: Vec<String> = args
                .get("scopes")
                .and_then(|v| v.as_array())
                .map(|arr| {
                    arr.iter()
                        .filter_map(|x| x.as_str().map(|s| s.to_string()))
                        .collect()
                })
                .unwrap_or_else(|| {
                    vec![
                        "read".into(),
                        "write".into(),
                        "engage".into(),
                        "monetize".into(),
                        "messages".into(),
                        "wallet".into(),
                    ]
                });
            let max_tx = round_money(
                args.get("max_spend_per_tx")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(50.0)
                    .max(0.0),
            );
            let daily_lim = round_money(
                args.get("daily_spend_limit")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(200.0)
                    .max(0.0),
            );
            let pool_tier = args.get("pool_tier").and_then(|v| v.as_str());
            let daily_action_quota = args.get("daily_action_quota").and_then(|v| v.as_i64());
            let conn = state.lock_db();
            let (tid, secret, shard_id, assigned_pool_tier, assigned_quota) =
                provision_agent_token_internal(
                    &conn,
                    &ProvisionAgentTokenSpec {
                        user_id: user.user_id,
                        agent_name: name,
                        scopes: &scopes,
                        max_tx,
                        daily_lim,
                        pool_tier,
                        daily_action_quota,
                    },
                )?;
            record_audit(
                &conn,
                user,
                "agent_token_create",
                "agent_token",
                Some(tid.to_string()),
                0.0,
                json!({
                    "name": name,
                    "scopes": scopes,
                    "shard_id": shard_id,
                    "pool_tier": assigned_pool_tier,
                    "daily_action_quota": assigned_quota
                }),
            );
            Ok(json!({
                "token_id": tid,
                "agent_token": secret,
                "scopes": scopes,
                "shard_id": shard_id,
                "pool_tier": assigned_pool_tier,
                "daily_action_quota": assigned_quota,
                "max_spend_per_tx": max_tx,
                "daily_spend_limit": daily_lim
            }))
        }

        "agent_token_list" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let conn = state.lock_db();
            let tokens = list_user_agent_tokens(&conn, user.user_id)?;
            let logs = list_user_agent_audit_logs(&conn, user.user_id, 50)?;
            Ok(json!({
                "agent_tokens": tokens,
                "agent_audit_logs": logs
            }))
        }

        "agent_token_revoke" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            if user.agent_token_id.is_some() {
                user.require_scope("admin")?;
            }
            let tid = args
                .get("token_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("token_id required".into()))?;
            let conn = state.lock_db();
            let updated = conn.execute(
                "UPDATE agent_tokens SET is_active = 0 WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
                params![tid, user.user_id, user.role_id],
            )?;
            if updated == 0 {
                return Err(AppError::NotFound("Agent token not found".into()));
            }
            record_audit(&conn, user, "agent_token_revoke", "agent_token", Some(tid.to_string()), 0.0, json!({}));
            Ok(json!({ "revoked": true, "token_id": tid }))
        }

        "profile_update" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let conn = state.lock_db();
            let current = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            let name = args
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or(&current.name)
                .trim();
            let username = args
                .get("username")
                .and_then(|v| v.as_str())
                .unwrap_or(&current.username)
                .trim()
                .chars()
                .filter(|c| c.is_alphanumeric() || *c == '_' || *c == '-')
                .collect::<String>()
                .to_lowercase();
            if name.is_empty() || username.is_empty() {
                return Err(AppError::Unprocessable("Name and valid username are required".into()));
            }
            let taken: i64 = conn.query_row(
                "SELECT COUNT(*) FROM users WHERE username = ?1 AND id != ?2",
                params![username, user.user_id],
                |r| r.get(0),
            )?;
            if taken > 0 {
                return Err(AppError::Unprocessable("Username already taken".into()));
            }
            let bio = args.get("bio").and_then(|v| v.as_str()).unwrap_or(&current.bio);
            let location = args
                .get("location")
                .and_then(|v| v.as_str())
                .unwrap_or(&current.location);
            let website = args
                .get("website")
                .and_then(|v| v.as_str())
                .unwrap_or(&current.website);
            let pronoun = args
                .get("gender_pronoun")
                .and_then(|v| v.as_str())
                .or(current.gender_pronoun.as_deref());
            let birthdate = args
                .get("birthdate")
                .and_then(|v| v.as_str())
                .or(current.birthdate.as_deref());
            let avatar = args.get("avatar").and_then(|v| v.as_str());
            let cover = args.get("cover").and_then(|v| v.as_str());

            conn.execute(
                "UPDATE users SET
                    name = ?1, username = ?2, bio = ?3, location = ?4, website = ?5,
                    birthdate = ?6, gender_pronoun = ?7,
                    avatar = COALESCE(?8, avatar), cover = COALESCE(?9, cover)
                 WHERE id = ?10",
                params![
                    name,
                    username,
                    bio.trim(),
                    location.trim(),
                    website.trim(),
                    birthdate,
                    pronoun,
                    avatar,
                    cover,
                    user.user_id
                ],
            )?;
            record_audit(
                &conn,
                user,
                "profile_update",
                "user",
                Some(user.user_id.to_string()),
                0.0,
                json!({ "name": name, "username": username }),
            );
            let updated = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            Ok(json!({ "user": updated }))
        }

        "rates_and_offers_update" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("monetize")?;
            let conn = state.lock_db();
            let cur = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            let paid_profile = args
                .get("paid_profile")
                .and_then(|v| v.as_bool())
                .unwrap_or(cur.paid_profile);
            let open_profile = args
                .get("open_profile")
                .and_then(|v| v.as_bool())
                .unwrap_or(cur.open_profile);
            let p1 = round_money(
                args.get("profile_access_price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(cur.profile_access_price)
                    .max(0.0),
            );
            let p3 = round_money(
                args.get("profile_access_price_3_months")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(cur.profile_access_price_3_months)
                    .max(0.0),
            );
            let p6 = round_money(
                args.get("profile_access_price_6_months")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(cur.profile_access_price_6_months)
                    .max(0.0),
            );
            let p12 = round_money(
                args.get("profile_access_price_12_months")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(cur.profile_access_price_12_months)
                    .max(0.0),
            );

            conn.execute(
                "UPDATE users SET
                    paid_profile = ?1, open_profile = ?2,
                    profile_access_price = ?3, profile_access_price_3_months = ?4,
                    profile_access_price_6_months = ?5, profile_access_price_12_months = ?6
                 WHERE id = ?7",
                params![
                    if paid_profile { 1 } else { 0 },
                    if open_profile { 1 } else { 0 },
                    p1,
                    p3,
                    p6,
                    p12,
                    user.user_id
                ],
            )?;

            if let Some(exp) = normalize_db_datetime(
                args.get("offer_expires_at")
                    .and_then(|v| v.as_str())
                    .map(|s| s.to_string()),
            ) {
                let disc = args
                    .get("offer_discount_percent")
                    .and_then(|v| v.as_i64())
                    .unwrap_or(25)
                    .clamp(5, 90);
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
                    params![
                        user.user_id,
                        cur.profile_access_price.max(p1),
                        cur.profile_access_price_3_months.max(p3),
                        cur.profile_access_price_6_months.max(p6),
                        cur.profile_access_price_12_months.max(p12),
                        disc,
                        exp
                    ],
                )?;
            }

            record_audit(
                &conn,
                user,
                "rates_and_offers_update",
                "user",
                Some(user.user_id.to_string()),
                0.0,
                json!({ "price_1m": p1, "paid_profile": paid_profile }),
            );
            let updated = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            Ok(json!({ "user": updated }))
        }

        "privacy_update" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let conn = state.lock_db();
            let pub_prof = args
                .get("public_profile")
                .and_then(|v| v.as_bool())
                .unwrap_or(true);
            let two_fa = args
                .get("enable_2fa")
                .and_then(|v| v.as_bool())
                .unwrap_or(false);
            let geo = args
                .get("enable_geoblocking")
                .and_then(|v| v.as_bool())
                .unwrap_or(false);
            conn.execute(
                "UPDATE users SET public_profile = ?1, enable_2fa = ?2, enable_geoblocking = ?3 WHERE id = ?4",
                params![
                    if pub_prof { 1 } else { 0 },
                    if two_fa { 1 } else { 0 },
                    if geo { 1 } else { 0 },
                    user.user_id
                ],
            )?;
            record_audit(&conn, user, "privacy_update", "user", Some(user.user_id.to_string()), 0.0, args.clone());
            Ok(json!({ "updated": true }))
        }

        "kyc_verification_submit" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let files: Vec<String> = args
                .get("files")
                .and_then(|v| v.as_array())
                .map(|arr| {
                    arr.iter()
                        .filter_map(|x| x.as_str().map(|s| s.trim().to_string()))
                        .filter(|s| !s.is_empty())
                        .collect()
                })
                .unwrap_or_default();
            if files.is_empty() {
                return Err(AppError::Unprocessable("At least one document file URL required".into()));
            }
            let conn = state.lock_db();
            conn.execute(
                "INSERT INTO user_verifies (user_id, status, files, rejection_reason)
                 VALUES (?1, 'pending', ?2, NULL)
                 ON CONFLICT(user_id) DO UPDATE SET status = 'pending', files = excluded.files, rejection_reason = NULL",
                params![user.user_id, serde_json::to_string(&files).unwrap_or_default()],
            )?;
            record_audit(&conn, user, "kyc_verification_submit", "verification", Some(user.user_id.to_string()), 0.0, json!({ "files": files }));
            Ok(json!({ "status": "pending", "files": files }))
        }

        "post_create_or_update" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let text = args
                .get("text")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let attachments: Vec<String> = args
                .get("attachments")
                .and_then(|v| v.as_array())
                .map(|arr| {
                    arr.iter()
                        .filter_map(|x| x.as_str().map(|s| s.to_string()))
                        .collect()
                })
                .unwrap_or_default();
            if text.is_empty() && attachments.is_empty() {
                return Err(AppError::Unprocessable(
                    "Post must contain text or at least one attachment.".into(),
                ));
            }
            let price = round_money(
                args.get("price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(0.0)
                    .max(0.0),
            );
            let is_pinned = if args.get("is_pinned").and_then(|v| v.as_bool()).unwrap_or(false) {
                1
            } else {
                0
            };
            let release_date = normalize_db_datetime(
                args.get("release_date")
                    .and_then(|v| v.as_str())
                    .map(|s| s.to_string()),
            );
            let expire_date = normalize_db_datetime(
                args.get("expire_date")
                    .and_then(|v| v.as_str())
                    .map(|s| s.to_string()),
            );
            let conn = state.lock_db();
            let post_id = if let Some(existing_id) = args.get("id").and_then(|v| v.as_i64()) {
                let owner_id: i64 = conn.query_row(
                    "SELECT user_id FROM posts WHERE id = ?1",
                    params![existing_id],
                    |r| r.get(0),
                )?;
                if owner_id != user.user_id && user.role_id != 1 {
                    return Err(AppError::Forbidden("You do not own this post".into()));
                }
                conn.execute(
                    "UPDATE posts SET text = ?1, price = ?2, is_pinned = ?3, release_date = ?4, expire_date = ?5 WHERE id = ?6",
                    params![text, price, is_pinned, release_date, expire_date, existing_id],
                )?;
                existing_id
            } else {
                conn.execute(
                    "INSERT INTO posts (user_id, text, price, status, is_pinned, release_date, expire_date)
                     VALUES (?1, ?2, ?3, 1, ?4, ?5, ?6)",
                    params![user.user_id, text, price, is_pinned, release_date, expire_date],
                )?;
                conn.last_insert_rowid()
            };

            for item in attachments {
                let updated = conn.execute(
                    "UPDATE attachments SET post_id = ?1 WHERE (id = ?2 OR file_url = ?2) AND user_id = ?3",
                    params![post_id, item, user.user_id],
                )?;
                if updated == 0 && (item.starts_with('/') || item.starts_with("http")) {
                    let ftype = if item.ends_with(".mp4") || item.ends_with(".webm") {
                        "video"
                    } else {
                        "image"
                    };
                    conn.execute(
                        "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
                         VALUES (?1, ?2, ?3, 'agent-media', ?4, ?5, 1024)",
                        params![Uuid::new_v4().to_string(), user.user_id, post_id, item, ftype],
                    )?;
                }
            }

            if let Some(poll_arr) = args.get("poll_answers").and_then(|v| v.as_array()) {
                let clean: Vec<String> = poll_arr
                    .iter()
                    .filter_map(|x| x.as_str().map(|s| s.trim().to_string()))
                    .filter(|s| !s.is_empty())
                    .collect();
                if clean.len() >= 2 {
                    conn.execute("DELETE FROM polls WHERE post_id = ?1", params![post_id])?;
                    conn.execute(
                        "INSERT INTO polls (user_id, post_id) VALUES (?1, ?2)",
                        params![user.user_id, post_id],
                    )?;
                    let poll_id = conn.last_insert_rowid();
                    for ans in clean {
                        conn.execute(
                            "INSERT INTO poll_answers (poll_id, answer) VALUES (?1, ?2)",
                            params![poll_id, ans],
                        )?;
                    }
                }
            }

            record_audit(
                &conn,
                user,
                "post_create_or_update",
                "post",
                Some(post_id.to_string()),
                0.0,
                json!({ "post_id": post_id, "price": price }),
            );
            let pv = build_post_view(&conn, post_id, Some(user.user_id))?;
            Ok(json!({ "post": pv }))
        }

        "post_delete" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let pid = args
                .get("post_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("post_id required".into()))?;
            let conn = state.lock_db();
            let owner_id: i64 = conn.query_row(
                "SELECT user_id FROM posts WHERE id = ?1",
                params![pid],
                |r| r.get(0),
            )?;
            if owner_id != user.user_id && user.role_id != 1 {
                return Err(AppError::Forbidden("Not authorized to delete this post".into()));
            }
            conn.execute("DELETE FROM posts WHERE id = ?1", params![pid])?;
            record_audit(&conn, user, "post_delete", "post", Some(pid.to_string()), 0.0, json!({}));
            Ok(json!({ "deleted": true, "post_id": pid }))
        }

        "reel_create" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let caption = args
                .get("caption")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let video_url = args
                .get("video_url")
                .and_then(|v| v.as_str())
                .filter(|s| !s.trim().is_empty())
                .unwrap_or("/img/video-loading-spinner.mp4");
            let cover_url = args
                .get("cover_url")
                .and_then(|v| v.as_str())
                .filter(|s| !s.trim().is_empty())
                .unwrap_or("/img/home-scene-1.svg");
            let price = round_money(
                args.get("price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(0.0)
                    .max(0.0),
            );
            let conn = state.lock_db();
            conn.execute(
                "INSERT INTO reels (user_id, caption, video_url, cover_url, price) VALUES (?1, ?2, ?3, ?4, ?5)",
                params![user.user_id, caption, video_url, cover_url, price],
            )?;
            let rid = conn.last_insert_rowid();
            record_audit(
                &conn,
                user,
                "reel_create",
                "reel",
                Some(rid.to_string()),
                0.0,
                json!({ "reel_id": rid, "price": price }),
            );
            Ok(json!({ "reel_id": rid, "price": price }))
        }

        "story_create" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let caption = args
                .get("caption")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let media_url = args
                .get("media_url")
                .and_then(|v| v.as_str())
                .unwrap_or("");
            let bg_preset = args
                .get("bg_preset")
                .and_then(|v| v.as_str())
                .unwrap_or("gradient-purple");
            let is_highlight = if args
                .get("is_highlight")
                .and_then(|v| v.as_bool())
                .unwrap_or(false)
            {
                1
            } else {
                0
            };
            let mtype = if media_url.ends_with(".mp4") {
                "video"
            } else if media_url.is_empty() {
                "text"
            } else {
                "image"
            };
            let conn = state.lock_db();
            conn.execute(
                "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, expires_at)
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, datetime('now', '+24 hours'))",
                params![user.user_id, media_url, mtype, caption, bg_preset, is_highlight],
            )?;
            let sid = conn.last_insert_rowid();
            record_audit(&conn, user, "story_create", "story", Some(sid.to_string()), 0.0, json!({ "story_id": sid }));
            Ok(json!({ "story_id": sid }))
        }

        "stream_start" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let name = args
                .get("name")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if name.is_empty() {
                return Err(AppError::Unprocessable("Stream name required".into()));
            }
            let slug: String = name
                .to_lowercase()
                .chars()
                .map(|c| if c.is_alphanumeric() { c } else { '-' })
                .collect();
            let rtmp_key = format!(
                "sk_live_{}_{}",
                user.username,
                &Uuid::new_v4().simple().to_string()[..8]
            );
            let price = round_money(
                args.get("price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(0.0)
                    .max(0.0),
            );
            let req_sub = if args
                .get("requires_subscription")
                .and_then(|v| v.as_bool())
                .unwrap_or(false)
            {
                1
            } else {
                0
            };
            let is_pub = if args
                .get("is_public")
                .and_then(|v| v.as_bool())
                .unwrap_or(true)
            {
                1
            } else {
                0
            };
            let conn = state.lock_db();
            conn.execute(
                "INSERT INTO streams (user_id, name, slug, price, requires_subscription, is_public, status, rtmp_key)
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, 'in-progress', ?7)",
                params![user.user_id, name, slug, price, req_sub, is_pub, rtmp_key],
            )?;
            let stream_id = conn.last_insert_rowid();
            record_audit(
                &conn,
                user,
                "stream_start",
                "stream",
                Some(stream_id.to_string()),
                0.0,
                json!({ "stream_id": stream_id, "name": name }),
            );
            Ok(json!({
                "stream_id": stream_id,
                "rtmp_server": "rtmp://live.justfans.local/app",
                "rtmp_key": rtmp_key,
                "status": "in-progress"
            }))
        }

        "stream_stop" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let sid = args
                .get("stream_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("stream_id required".into()))?;
            let conn = state.lock_db();
            conn.execute(
                "UPDATE streams SET status = 'ended' WHERE id = ?1 AND (user_id = ?2 OR ?3 = 1)",
                params![sid, user.user_id, user.role_id],
            )?;
            record_audit(&conn, user, "stream_stop", "stream", Some(sid.to_string()), 0.0, json!({}));
            Ok(json!({ "stream_id": sid, "status": "ended" }))
        }

        "ai_caption_suggest" => {
            let topic = args
                .get("topic")
                .and_then(|v| v.as_str())
                .unwrap_or("exclusive studio drop");
            Ok(json!({
                "suggestion": format!("✨ Just dropped a brand new {topic} set! Unlock or subscribe now for full HD behind-the-scenes access and let me know your favorite shot in the comments 💬🔥 #exclusive #creator")
            }))
        }

        "vault_list" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let conn = state.lock_db();
            let mut stmt = conn.prepare(
                "SELECT id, user_id, post_id, message_id, filename, file_url, file_type, file_size, is_vault, is_archived, created_at
                 FROM attachments WHERE user_id = ?1 ORDER BY created_at DESC",
            )?;
            let items: Vec<Attachment> = stmt
                .query_map(params![user.user_id], |r| {
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
            Ok(json!({ "items": items }))
        }

        "vault_register_asset" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let file_url = args
                .get("file_url")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if file_url.is_empty() {
                return Err(AppError::Unprocessable("file_url required".into()));
            }
            let filename = args
                .get("filename")
                .and_then(|v| v.as_str())
                .unwrap_or("vault-asset.jpg");
            let file_type = args
                .get("file_type")
                .and_then(|v| v.as_str())
                .unwrap_or("image");
            let att_id = Uuid::new_v4().to_string();
            let conn = state.lock_db();
            conn.execute(
                "INSERT INTO attachments (id, user_id, filename, file_url, file_type, file_size, is_vault)
                 VALUES (?1, ?2, ?3, ?4, ?5, 4096, 1)",
                params![att_id, user.user_id, filename, file_url, file_type],
            )?;
            record_audit(&conn, user, "vault_register_asset", "attachment", Some(att_id.clone()), 0.0, json!({ "file_url": file_url }));
            Ok(json!({ "attachment_id": att_id, "file_url": file_url }))
        }

        "vault_toggle_archive" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let att_id = args
                .get("attachment_id")
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("attachment_id required".into()))?;
            let conn = state.lock_db();
            let (owner_id, cur): (i64, i64) = conn.query_row(
                "SELECT user_id, is_archived FROM attachments WHERE id = ?1",
                params![att_id],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )?;
            if owner_id != user.user_id && user.role_id != 1 {
                return Err(AppError::Forbidden("Not your vault attachment".into()));
            }
            let next = if cur == 0 { 1 } else { 0 };
            conn.execute(
                "UPDATE attachments SET is_archived = ?1 WHERE id = ?2",
                params![next, att_id],
            )?;
            Ok(json!({ "attachment_id": att_id, "is_archived": next != 0 }))
        }

        "feed_read" => {
            if let Some(u) = auth {
                u.require_scope("read")?;
            }
            let viewer_id = auth.map(|a| a.user_id);
            let page = args.get("page").and_then(|v| v.as_i64()).unwrap_or(1).max(1);
            let offset = (page - 1) * 20;
            let filter = args
                .get("filter")
                .and_then(|v| v.as_str())
                .unwrap_or("all")
                .trim()
                .to_lowercase();
            let conn = state.lock_db();
            let pids: Vec<i64> = match (filter.as_str(), viewer_id) {
                ("subscribed", Some(vid)) => {
                    let mut stmt = conn.prepare(
                        "SELECT id FROM posts
                         WHERE status = 1
                           AND (release_date IS NULL OR release_date <= datetime('now'))
                           AND (expire_date IS NULL OR expire_date >= datetime('now'))
                           AND (
                               user_id = ?1
                               OR user_id IN (SELECT recipient_user_id FROM subscriptions WHERE sender_user_id = ?1 AND status = 'completed' AND expires_at >= datetime('now'))
                               OR user_id IN (SELECT ulm.user_id FROM user_list_members ulm JOIN user_lists ul ON ulm.list_id = ul.id WHERE ul.user_id = ?1 AND ul.list_type = 'following')
                           )
                         ORDER BY is_pinned DESC, id DESC LIMIT 20 OFFSET ?2",
                    )?;
                    let rows = stmt
                        .query_map(params![vid, offset], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    rows
                }
                ("media", _) => {
                    let mut stmt = conn.prepare(
                        "SELECT id FROM posts
                         WHERE status = 1
                           AND (release_date IS NULL OR release_date <= datetime('now'))
                           AND (expire_date IS NULL OR expire_date >= datetime('now'))
                           AND EXISTS (SELECT 1 FROM attachments a WHERE a.post_id = posts.id)
                         ORDER BY is_pinned DESC, id DESC LIMIT 20 OFFSET ?1",
                    )?;
                    let rows = stmt
                        .query_map(params![offset], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    rows
                }
                ("photos", _) => {
                    let mut stmt = conn.prepare(
                        "SELECT id FROM posts
                         WHERE status = 1
                           AND (release_date IS NULL OR release_date <= datetime('now'))
                           AND (expire_date IS NULL OR expire_date >= datetime('now'))
                           AND EXISTS (SELECT 1 FROM attachments a WHERE a.post_id = posts.id AND a.file_type IN ('image', 'jpg', 'jpeg', 'png', 'webp', 'gif'))
                         ORDER BY is_pinned DESC, id DESC LIMIT 20 OFFSET ?1",
                    )?;
                    let rows = stmt
                        .query_map(params![offset], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    rows
                }
                ("videos", _) => {
                    let mut stmt = conn.prepare(
                        "SELECT id FROM posts
                         WHERE status = 1
                           AND (release_date IS NULL OR release_date <= datetime('now'))
                           AND (expire_date IS NULL OR expire_date >= datetime('now'))
                           AND EXISTS (SELECT 1 FROM attachments a WHERE a.post_id = posts.id AND a.file_type IN ('video', 'mp4', 'mov', 'webm'))
                         ORDER BY is_pinned DESC, id DESC LIMIT 20 OFFSET ?1",
                    )?;
                    let rows = stmt
                        .query_map(params![offset], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    rows
                }
                _ => {
                    let mut stmt = conn.prepare(
                        "SELECT id FROM posts
                         WHERE status = 1
                           AND (release_date IS NULL OR release_date <= datetime('now'))
                           AND (expire_date IS NULL OR expire_date >= datetime('now'))
                         ORDER BY is_pinned DESC, id DESC LIMIT 20 OFFSET ?1",
                    )?;
                    let rows = stmt
                        .query_map(params![offset], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    rows
                }
            };
            let mut posts = Vec::new();
            for pid in pids {
                if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
                    if let Some(vid) = viewer_id {
                        if is_blocked_between(&conn, vid, pv.user_id) {
                            continue;
                        }
                    }
                    posts.push(pv);
                }
            }
            Ok(json!({ "page": page, "filter": filter, "posts": posts }))
        }

        "creator_discover" => {
            if let Some(u) = auth {
                u.require_scope("read")?;
            }
            let viewer_id = auth.map(|a| a.user_id);
            let q = args
                .get("query")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim()
                .to_lowercase();
            let pattern = format!("%{q}%");
            let conn = state.lock_db();
            let mut stmt = conn.prepare(
                "SELECT id FROM users WHERE public_profile = 1 AND (LOWER(name) LIKE ?1 OR LOWER(username) LIKE ?1 OR LOWER(bio) LIKE ?1) ORDER BY id ASC LIMIT 20",
            )?;
            let uids: Vec<i64> = stmt
                .query_map(params![pattern], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            let mut creators = Vec::new();
            for uid in uids {
                if let Ok(p) = get_user_public_profile(&conn, uid, viewer_id) {
                    creators.push(p);
                }
            }
            Ok(json!({ "creators": creators }))
        }

        "profile_get" => {
            if let Some(u) = auth {
                u.require_scope("read")?;
            }
            let viewer_id = auth.map(|a| a.user_id);
            let username = args
                .get("username")
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("username required".into()))?;
            let conn = state.lock_db();
            let target_id: i64 = conn
                .query_row(
                    "SELECT id FROM users WHERE username = ?1",
                    params![username.trim()],
                    |r| r.get(0),
                )
                .map_err(|_| AppError::NotFound("Profile not found".into()))?;
            let profile = get_user_public_profile(&conn, target_id, viewer_id)?;
            let mut stmt = conn.prepare(
                "SELECT id FROM posts WHERE user_id = ?1 AND status = 1
                 AND (release_date IS NULL OR release_date <= datetime('now'))
                 AND (expire_date IS NULL OR expire_date >= datetime('now'))
                 ORDER BY is_pinned DESC, id DESC LIMIT 20",
            )?;
            let pids: Vec<i64> = stmt
                .query_map(params![target_id], |r| r.get(0))?
                .filter_map(Result::ok)
                .collect();
            let mut posts = Vec::new();
            for pid in pids {
                if let Ok(pv) = build_post_view(&conn, pid, viewer_id) {
                    posts.push(pv);
                }
            }
            Ok(json!({ "profile": profile, "posts": posts }))
        }

        "post_react" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("engage")?;
            let conn = state.lock_db();
            if let Some(pid) = args.get("post_id").and_then(|v| v.as_i64()) {
                let author_id: i64 = conn.query_row(
                    "SELECT user_id FROM posts WHERE id = ?1",
                    params![pid],
                    |r| r.get(0),
                )?;
                if is_blocked_between(&conn, user.user_id, author_id) {
                    return Err(AppError::Forbidden("Blocked".into()));
                }
                let existing: Option<i64> = conn
                    .query_row(
                        "SELECT id FROM reactions WHERE post_id = ?1 AND user_id = ?2",
                        params![pid, user.user_id],
                        |r| r.get(0),
                    )
                    .ok();
                let liked = if let Some(rid) = existing {
                    conn.execute("DELETE FROM reactions WHERE id = ?1", params![rid])?;
                    false
                } else {
                    conn.execute(
                        "INSERT INTO reactions (user_id, reaction_type, post_id) VALUES (?1, 'like', ?2)",
                        params![user.user_id, pid],
                    )?;
                    true
                };
                record_audit(&conn, user, "post_react", "post", Some(pid.to_string()), 0.0, json!({ "liked": liked }));
                Ok(json!({ "post_id": pid, "liked": liked }))
            } else if let Some(cid) = args.get("comment_id").and_then(|v| v.as_i64()) {
                let author_id: i64 = conn.query_row(
                    "SELECT user_id FROM post_comments WHERE id = ?1",
                    params![cid],
                    |r| r.get(0),
                )?;
                if is_blocked_between(&conn, user.user_id, author_id) {
                    return Err(AppError::Forbidden("Blocked".into()));
                }
                let existing: Option<i64> = conn
                    .query_row(
                        "SELECT id FROM reactions WHERE post_comment_id = ?1 AND user_id = ?2",
                        params![cid, user.user_id],
                        |r| r.get(0),
                    )
                    .ok();
                let liked = if let Some(rid) = existing {
                    conn.execute("DELETE FROM reactions WHERE id = ?1", params![rid])?;
                    false
                } else {
                    conn.execute(
                        "INSERT INTO reactions (user_id, reaction_type, post_comment_id) VALUES (?1, 'like', ?2)",
                        params![user.user_id, cid],
                    )?;
                    true
                };
                record_audit(&conn, user, "post_react", "comment", Some(cid.to_string()), 0.0, json!({ "liked": liked }));
                Ok(json!({ "comment_id": cid, "liked": liked }))
            } else if let Some(rid) = args.get("reel_id").and_then(|v| v.as_i64()) {
                let author_id: i64 = conn.query_row(
                    "SELECT user_id FROM reels WHERE id = ?1",
                    params![rid],
                    |r| r.get(0),
                )?;
                if is_blocked_between(&conn, user.user_id, author_id) {
                    return Err(AppError::Forbidden("Blocked".into()));
                }
                let existing: Option<i64> = conn
                    .query_row(
                        "SELECT id FROM reactions WHERE reel_id = ?1 AND user_id = ?2",
                        params![rid, user.user_id],
                        |r| r.get(0),
                    )
                    .ok();
                let liked = if let Some(rx_id) = existing {
                    conn.execute("DELETE FROM reactions WHERE id = ?1", params![rx_id])?;
                    false
                } else {
                    conn.execute(
                        "INSERT INTO reactions (user_id, reaction_type, reel_id) VALUES (?1, 'like', ?2)",
                        params![user.user_id, rid],
                    )?;
                    true
                };
                record_audit(&conn, user, "post_react", "reel", Some(rid.to_string()), 0.0, json!({ "liked": liked }));
                Ok(json!({ "reel_id": rid, "liked": liked }))
            } else {
                Err(AppError::BadRequest("post_id, comment_id, or reel_id required".into()))
            }
        }

        "post_comment" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("engage")?;
            let pid = args
                .get("post_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("post_id required".into()))?;
            let message = args
                .get("message")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if message.is_empty() {
                return Err(AppError::Unprocessable("Comment cannot be empty".into()));
            }
            let conn = state.lock_db();
            let pv = build_post_view(&conn, pid, Some(user.user_id))?;
            if is_blocked_between(&conn, user.user_id, pv.user_id) || !pv.is_unlocked {
                return Err(AppError::Forbidden("Post is locked or blocked".into()));
            }
            conn.execute(
                "INSERT INTO post_comments (post_id, user_id, message) VALUES (?1, ?2, ?3)",
                params![pid, user.user_id, message],
            )?;
            let cid = conn.last_insert_rowid();
            record_audit(&conn, user, "post_comment", "comment", Some(cid.to_string()), 0.0, json!({ "post_id": pid }));
            Ok(json!({ "comment_id": cid, "post_id": pid, "message": message }))
        }

        "poll_vote" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("engage")?;
            let poll_id = args
                .get("poll_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("poll_id required".into()))?;
            let answer_id = args
                .get("answer_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("answer_id required".into()))?;
            let conn = state.lock_db();
            let post_id: i64 = conn.query_row(
                "SELECT post_id FROM polls WHERE id = ?1",
                params![poll_id],
                |r| r.get(0),
            )?;
            let pv = build_post_view(&conn, post_id, Some(user.user_id))?;
            if !pv.is_unlocked {
                return Err(AppError::Forbidden("Unlock post to vote".into()));
            }
            let valid: i64 = conn.query_row(
                "SELECT COUNT(*) FROM poll_answers WHERE id = ?1 AND poll_id = ?2",
                params![answer_id, poll_id],
                |r| r.get(0),
            )?;
            if valid == 0 {
                return Err(AppError::Unprocessable("Invalid answer_id for poll".into()));
            }
            conn.execute(
                "INSERT INTO poll_user_answers (poll_id, user_id, answer_id) VALUES (?1, ?2, ?3)
                 ON CONFLICT(poll_id, user_id) DO UPDATE SET answer_id = excluded.answer_id",
                params![poll_id, user.user_id, answer_id],
            )?;
            record_audit(&conn, user, "poll_vote", "poll", Some(poll_id.to_string()), 0.0, json!({ "answer_id": answer_id }));
            let updated_pv = build_post_view(&conn, post_id, Some(user.user_id))?;
            Ok(json!({ "poll": updated_pv.poll }))
        }

        "social_follow_or_block" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("engage")?;
            let target_id = args
                .get("target_user_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("target_user_id required".into()))?;
            let action = args
                .get("action")
                .and_then(|v| v.as_str())
                .unwrap_or("follow");
            if target_id == user.user_id {
                return Err(AppError::Unprocessable("Cannot target yourself".into()));
            }
            let conn = state.lock_db();
            let list_type = if action == "block" { "blocked" } else { "following" };
            let lid: i64 = conn.query_row(
                "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = ?2",
                params![user.user_id, list_type],
                |r| r.get(0),
            )?;
            let existing: Option<i64> = conn
                .query_row(
                    "SELECT id FROM user_list_members WHERE list_id = ?1 AND user_id = ?2",
                    params![lid, target_id],
                    |r| r.get(0),
                )
                .ok();
            let active = if let Some(mid) = existing {
                conn.execute("DELETE FROM user_list_members WHERE id = ?1", params![mid])?;
                false
            } else {
                conn.execute(
                    "INSERT INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                    params![lid, target_id],
                )?;
                if list_type == "following" {
                    if let Ok(fl_id) = conn.query_row::<i64, _, _>(
                        "SELECT id FROM user_lists WHERE user_id = ?1 AND list_type = 'followers'",
                        params![target_id],
                        |r| r.get(0),
                    ) {
                        let _ = conn.execute(
                            "INSERT OR IGNORE INTO user_list_members (list_id, user_id) VALUES (?1, ?2)",
                            params![fl_id, user.user_id],
                        );
                    }
                }
                true
            };
            record_audit(&conn, user, "social_follow_or_block", "user", Some(target_id.to_string()), 0.0, json!({ "action": action, "active": active }));
            Ok(json!({ "target_user_id": target_id, "action": action, "active": active }))
        }

        "messenger_contacts_and_thread" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("messages")?;
            let conn = state.lock_db();
            if let Some(other_id) = args.get("other_user_id").and_then(|v| v.as_i64()) {
                let mut stmt = conn.prepare(
                    "SELECT m.id, m.sender_id, m.receiver_id, u.name, u.username, u.avatar,
                            m.message, m.price, m.is_seen, m.is_pinned, m.created_at
                     FROM user_messages m JOIN users u ON m.sender_id = u.id
                     WHERE (m.sender_id = ?1 AND m.receiver_id = ?2)
                        OR (m.sender_id = ?2 AND m.receiver_id = ?1)
                     ORDER BY m.id ASC",
                )?;
                let msgs: Vec<Value> = stmt
                    .query_map(params![user.user_id, other_id], |r| {
                        let mid: i64 = r.get(0)?;
                        let sender_id: i64 = r.get(1)?;
                        let price: f64 = r.get(7)?;
                        let is_unlocked = sender_id == user.user_id
                            || price <= 0.0
                            || conn
                                .query_row(
                                    "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'",
                                    params![user.user_id, mid],
                                    |tr| tr.get::<_, i64>(0),
                                )
                                .unwrap_or(0)
                                > 0;
                        let raw_msg: String = r.get(6)?;
                        Ok(json!({
                            "id": mid,
                            "sender_id": sender_id,
                            "receiver_id": r.get::<_, i64>(2)?,
                            "sender_username": r.get::<_, String>(4)?,
                            "message": if is_unlocked { raw_msg } else { "🔒 Locked PPV Message".into() },
                            "price": price,
                            "is_unlocked": is_unlocked,
                            "created_at": r.get::<_, String>(10)?
                        }))
                    })?
                    .filter_map(Result::ok)
                    .collect();
                return Ok(json!({ "other_user_id": other_id, "messages": msgs }));
            }

            let mut stmt = conn.prepare("SELECT id, name, username FROM users WHERE id != ?1 ORDER BY id ASC")?;
            let contacts: Vec<Value> = stmt
                .query_map(params![user.user_id], |r| {
                    Ok(json!({
                        "user_id": r.get::<_, i64>(0)?,
                        "name": r.get::<_, String>(1)?,
                        "username": r.get::<_, String>(2)?,
                    }))
                })?
                .filter_map(Result::ok)
                .collect();
            Ok(json!({ "contacts": contacts }))
        }

        "messenger_send_dm" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("messages")?;
            let receiver_id = args
                .get("receiver_id")
                .and_then(|v| v.as_i64())
                .ok_or_else(|| AppError::BadRequest("receiver_id required".into()))?;
            let msg = args
                .get("message")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if msg.is_empty() {
                return Err(AppError::Unprocessable("Message text required".into()));
            }
            if receiver_id == user.user_id {
                return Err(AppError::Unprocessable("Cannot message yourself".into()));
            }
            let price = round_money(
                args.get("price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(0.0)
                    .max(0.0),
            );
            let conn = state.lock_db();
            if is_blocked_between(&conn, user.user_id, receiver_id) {
                return Err(AppError::Forbidden("Blocked".into()));
            }
            conn.execute(
                "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
                params![user.user_id, receiver_id, msg, price],
            )?;
            let mid = conn.last_insert_rowid();
            record_audit(
                &conn,
                user,
                "messenger_send_dm",
                "message",
                Some(mid.to_string()),
                0.0,
                json!({ "receiver_id": receiver_id, "price": price }),
            );
            Ok(json!({ "message_id": mid, "receiver_id": receiver_id, "price": price }))
        }

        "messenger_broadcast" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("messages")?;
            let msg = args
                .get("message")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            let attachments = args
                .get("attachments")
                .and_then(|v| v.as_array())
                .cloned()
                .unwrap_or_default();
            if msg.is_empty() && attachments.is_empty() {
                return Err(AppError::Unprocessable("Message text or attachment required".into()));
            }
            let price = round_money(
                args.get("price")
                    .and_then(|v| v.as_f64())
                    .unwrap_or(0.0)
                    .max(0.0),
            );
            let target = args
                .get("target")
                .or_else(|| args.get("audience"))
                .and_then(|v| v.as_str())
                .unwrap_or("subscribers")
                .trim()
                .to_lowercase();
            let conn = state.lock_db();
            let mut targets: Vec<i64> = Vec::new();
            if let Some(arr) = args.get("recipient_ids").and_then(|v| v.as_array()) {
                for v in arr {
                    if let Some(id) = v.as_i64() {
                        if id != user.user_id && !targets.contains(&id) {
                            targets.push(id);
                        }
                    }
                }
            }
            if targets.is_empty() {
                if let Some(lid) = args.get("list_id").and_then(|v| v.as_i64()) {
                    let mut stmt = conn.prepare(
                        "SELECT ulm.user_id FROM user_list_members ulm
                         JOIN user_lists ul ON ulm.list_id = ul.id
                         WHERE ul.id = ?1 AND ul.user_id = ?2",
                    )?;
                    let rows: Vec<i64> = stmt
                        .query_map(params![lid, user.user_id], |r| r.get(0))?
                        .filter_map(Result::ok)
                        .collect();
                    for id in rows {
                        if id != user.user_id && !targets.contains(&id) {
                            targets.push(id);
                        }
                    }
                } else {
                    if target == "subscribers" || target == "all" {
                        let mut stmt = conn.prepare(
                            "SELECT DISTINCT sender_user_id FROM subscriptions
                             WHERE recipient_user_id = ?1 AND status = 'completed' AND expires_at >= datetime('now')",
                        )?;
                        let rows: Vec<i64> = stmt
                            .query_map(params![user.user_id], |r| r.get(0))?
                            .filter_map(Result::ok)
                            .collect();
                        for id in rows {
                            if id != user.user_id && !targets.contains(&id) {
                                targets.push(id);
                            }
                        }
                    }
                    if target == "followers" || target == "all" {
                        let mut f_stmt = conn.prepare(
                            "SELECT DISTINCT ul.user_id FROM user_list_members ulm
                             JOIN user_lists ul ON ulm.list_id = ul.id
                             WHERE ulm.user_id = ?1 AND ul.list_type = 'following'
                             UNION
                             SELECT DISTINCT ulm.user_id FROM user_list_members ulm
                             JOIN user_lists ul ON ulm.list_id = ul.id
                             WHERE ul.user_id = ?1 AND ul.list_type = 'followers'",
                        )?;
                        let rows: Vec<i64> = f_stmt
                            .query_map(params![user.user_id], |r| r.get(0))?
                            .filter_map(Result::ok)
                            .collect();
                        for id in rows {
                            if id != user.user_id && !targets.contains(&id) {
                                targets.push(id);
                            }
                        }
                    }
                }
            }
            let mut sent_ids = Vec::new();
            for rid in targets {
                if !is_blocked_between(&conn, user.user_id, rid) {
                    conn.execute(
                        "INSERT INTO user_messages (sender_id, receiver_id, message, price) VALUES (?1, ?2, ?3, ?4)",
                        params![user.user_id, rid, msg, price],
                    )?;
                    let mid = conn.last_insert_rowid();
                    for att in &attachments {
                        let (fname, furl, ftype) = if let Some(url_str) = att.as_str() {
                            ("broadcast-media", url_str, "image")
                        } else {
                            let f = att.get("filename").and_then(|v| v.as_str()).unwrap_or("");
                            let u = att
                                .get("file_url")
                                .or_else(|| att.get("thumbnail"))
                                .and_then(|v| v.as_str())
                                .unwrap_or(f);
                            let t = att.get("file_type").and_then(|v| v.as_str()).unwrap_or("image");
                            (f, u, t)
                        };
                        if !fname.is_empty() && !furl.is_empty() {
                            let _ = conn.execute(
                                "INSERT INTO attachments (id, user_id, message_id, filename, file_url, file_type, file_size)
                                 VALUES (?1, ?2, ?3, ?4, ?5, ?6, 1024)",
                                params![
                                    format!("att-bcast-{mid}-{}", sent_ids.len()),
                                    user.user_id,
                                    mid,
                                    fname,
                                    furl,
                                    ftype
                                ],
                            );
                        }
                    }
                    sent_ids.push(mid);
                }
            }
            record_audit(
                &conn,
                user,
                "messenger_broadcast",
                "broadcast",
                None,
                0.0,
                json!({ "target": target, "sent_count": sent_ids.len(), "price": price }),
            );
            Ok(json!({ "target": target, "sent_count": sent_ids.len(), "message_ids": sent_ids }))
        }

        "wallet_transact" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("wallet")?;
            let ptype = args
                .get("payment_type")
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("payment_type required".into()))?
                .trim();
            let raw_provider = args
                .get("provider")
                .and_then(|v| v.as_str())
                .unwrap_or("credit")
                .trim()
                .to_lowercase();
            let provider = match raw_provider.as_str() {
                "wallet" | "credit" | "" => "credit".to_string(),
                "stripe" | "paypal" | "crypto" | "ccbill" => raw_provider,
                other => return Err(AppError::BadRequest(format!("Unsupported provider: {other}"))),
            };

            let mut conn = state.lock_db();
            if ptype == "deposit" {
                let amt = round_money(args.get("amount").and_then(|v| v.as_f64()).unwrap_or(0.0));
                if amt <= 0.0 || !amt.is_finite() {
                    return Err(AppError::Unprocessable("Deposit amount must be positive".into()));
                }
                let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
                let next_inv: i64 = tx
                    .query_row("SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions", [], |r| r.get(0))
                    .unwrap_or(1001);
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                    params![amt, user.user_id],
                )?;
                tx.execute(
                    "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, invoice_id)
                     VALUES (?1, ?1, 'deposit', 'approved', ?2, ?3, ?4)",
                    params![user.user_id, amt, provider, next_inv],
                )?;
                let new_bal: f64 = tx.query_row(
                    "SELECT total FROM wallets WHERE user_id = ?1",
                    params![user.user_id],
                    |r| r.get(0),
                )?;
                tx.commit()?;
                record_audit(&conn, user, "wallet_transact", "deposit", Some(next_inv.to_string()), 0.0, json!({ "amount": amt }));
                return Ok(json!({
                    "payment_type": "deposit",
                    "amount": amt,
                    "invoice_id": next_inv,
                    "wallet_balance": round_money(new_bal)
                }));
            }

            let post_id = args.get("post_id").and_then(|v| v.as_i64());
            let message_id = args.get("message_id").and_then(|v| v.as_i64());
            let reel_id = args.get("reel_id").and_then(|v| v.as_i64());
            let stream_id = args.get("stream_id").and_then(|v| v.as_i64());
            let months = args.get("months").and_then(|v| v.as_i64()).unwrap_or(1);

            let (raw_amt, recipient_id) = match ptype {
                "subscription" => {
                    let rec = args
                        .get("recipient_user_id")
                        .and_then(|v| v.as_i64())
                        .ok_or_else(|| AppError::BadRequest("recipient_user_id required".into()))?;
                    if rec == user.user_id {
                        return Err(AppError::Unprocessable("Cannot subscribe to yourself".into()));
                    }
                    let price: f64 = match months {
                        3 => conn.query_row("SELECT profile_access_price_3_months * 3 FROM users WHERE id = ?1", params![rec], |r| r.get(0))?,
                        6 => conn.query_row("SELECT profile_access_price_6_months * 6 FROM users WHERE id = ?1", params![rec], |r| r.get(0))?,
                        12 => conn.query_row("SELECT profile_access_price_12_months * 12 FROM users WHERE id = ?1", params![rec], |r| r.get(0))?,
                        _ => conn.query_row("SELECT profile_access_price FROM users WHERE id = ?1", params![rec], |r| r.get(0))?,
                    };
                    (price.max(0.0), rec)
                }
                "post-unlock" => {
                    let pid = post_id.ok_or_else(|| AppError::BadRequest("post_id required".into()))?;
                    let (rec, price): (i64, f64) = conn.query_row("SELECT user_id, price FROM posts WHERE id = ?1", params![pid], |r| Ok((r.get(0)?, r.get(1)?)))?;
                    let dup: i64 = conn.query_row("SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND post_id = ?2 AND tx_type = 'post-unlock' AND status = 'approved'", params![user.user_id, pid], |r| r.get(0)).unwrap_or(0);
                    if dup > 0 {
                        return Err(AppError::Unprocessable("Post already unlocked".into()));
                    }
                    (price, rec)
                }
                "message-unlock" => {
                    let mid = message_id.ok_or_else(|| AppError::BadRequest("message_id required".into()))?;
                    let (rec, price): (i64, f64) = conn.query_row("SELECT sender_id, price FROM user_messages WHERE id = ?1", params![mid], |r| Ok((r.get(0)?, r.get(1)?)))?;
                    let dup: i64 = conn.query_row("SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'", params![user.user_id, mid], |r| r.get(0)).unwrap_or(0);
                    if dup > 0 {
                        return Err(AppError::Unprocessable("Message already unlocked".into()));
                    }
                    (price, rec)
                }
                "reel-unlock" => {
                    let rid = reel_id.ok_or_else(|| AppError::BadRequest("reel_id required".into()))?;
                    let (rec, price): (i64, f64) = conn.query_row("SELECT user_id, price FROM reels WHERE id = ?1", params![rid], |r| Ok((r.get(0)?, r.get(1)?)))?;
                    let dup: i64 = conn.query_row("SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND reel_id = ?2 AND tx_type = 'reel-unlock' AND status = 'approved'", params![user.user_id, rid], |r| r.get(0)).unwrap_or(0);
                    if dup > 0 {
                        return Err(AppError::Unprocessable("Reel already unlocked".into()));
                    }
                    (price, rec)
                }
                "stream-unlock" => {
                    let sid = stream_id.ok_or_else(|| AppError::BadRequest("stream_id required".into()))?;
                    let (rec, price): (i64, f64) = conn.query_row("SELECT user_id, price FROM streams WHERE id = ?1", params![sid], |r| Ok((r.get(0)?, r.get(1)?)))?;
                    let dup: i64 = conn.query_row("SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND stream_id = ?2 AND tx_type = 'stream-unlock' AND status = 'approved'", params![user.user_id, sid], |r| r.get(0)).unwrap_or(0);
                    if dup > 0 {
                        return Err(AppError::Unprocessable("Stream already unlocked".into()));
                    }
                    (price, rec)
                }
                "tip" => {
                    let amt = args.get("amount").and_then(|v| v.as_f64()).unwrap_or(5.0);
                    if amt <= 0.0 || !amt.is_finite() {
                        return Err(AppError::Unprocessable("Tip must be > 0".into()));
                    }
                    let rec = args
                        .get("recipient_user_id")
                        .and_then(|v| v.as_i64())
                        .ok_or_else(|| AppError::BadRequest("recipient_user_id required".into()))?;
                    (amt, rec)
                }
                other => return Err(AppError::BadRequest(format!("Unsupported payment_type: {other}"))),
            };

            if recipient_id == user.user_id {
                return Err(AppError::Unprocessable("Cannot transact with yourself.".into()));
            }
            if is_blocked_between(&conn, user.user_id, recipient_id) {
                return Err(AppError::Forbidden("Transaction blocked due to privacy restrictions.".into()));
            }

            let amount = round_money(raw_amt);

            if let Some(tid) = user.agent_token_id {
                if let Some(max_tx) = user.max_spend_per_tx {
                    if amount > max_tx {
                        return Err(AppError::Unprocessable(format!(
                            "Autonomous agent payment (${amount:.2}) exceeds per-transaction spend cap (${max_tx:.2})."
                        )));
                    }
                }
                if let Some(daily_lim) = user.daily_spend_limit {
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
                            "Autonomous agent payment (${amount:.2}) would exceed 24h daily spend cap (${daily_lim:.2})."
                        )));
                    }
                }
            }

            let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
            if provider == "credit" && amount > 0.0 {
                let bal: f64 = tx
                    .query_row("SELECT total FROM wallets WHERE user_id = ?1", params![user.user_id], |r| r.get(0))
                    .unwrap_or(0.0);
                if bal < amount {
                    return Err(AppError::Unprocessable("Insufficient wallet balance".into()));
                }
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                    params![amount, user.user_id],
                )?;
            }
            if amount > 0.0 {
                let creator_share = round_money(amount * 0.85);
                tx.execute(
                    "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                    params![creator_share, recipient_id],
                )?;
            }
            let next_inv: i64 = tx
                .query_row("SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions", [], |r| r.get(0))
                .unwrap_or(1001);
            tx.execute(
                "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, post_id, message_id, stream_id, reel_id, invoice_id)
                 VALUES (?1, ?2, ?3, 'approved', ?4, ?5, ?6, ?7, ?8, ?9, ?10)",
                params![user.user_id, recipient_id, ptype, amount, provider, post_id, message_id, stream_id, reel_id, next_inv],
            )?;
            if ptype == "subscription" {
                let days = months * 30;
                tx.execute(
                    "INSERT INTO subscriptions (sender_user_id, recipient_user_id, amount, provider, status, expires_at)
                     VALUES (?1, ?2, ?3, ?4, 'completed', datetime('now', ?5))",
                    params![user.user_id, recipient_id, amount, provider, format!("+{days} days")],
                )?;
            }
            let updated_bal: f64 = tx
                .query_row("SELECT total FROM wallets WHERE user_id = ?1", params![user.user_id], |r| r.get(0))
                .unwrap_or(0.0);
            tx.commit()?;

            record_audit(
                &conn,
                user,
                "wallet_transact",
                "payment",
                Some(next_inv.to_string()),
                amount,
                json!({ "payment_type": ptype, "recipient_user_id": recipient_id, "amount": amount }),
            );
            Ok(json!({
                "payment_type": ptype,
                "amount": amount,
                "invoice_id": next_inv,
                "wallet_balance": round_money(updated_bal)
            }))
        }

        "wallet_withdraw" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("wallet")?;
            let amount = round_money(args.get("amount").and_then(|v| v.as_f64()).unwrap_or(0.0));
            if amount < 20.0 || !amount.is_finite() {
                return Err(AppError::Unprocessable("Minimum withdrawal amount is $20.00".into()));
            }
            let method = args
                .get("payment_method")
                .and_then(|v| v.as_str())
                .unwrap_or("Bank Transfer");
            let ident = args
                .get("payment_identifier")
                .and_then(|v| v.as_str())
                .unwrap_or("");
            let mut conn = state.lock_db();
            let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
            let bal: f64 = tx
                .query_row("SELECT total FROM wallets WHERE user_id = ?1", params![user.user_id], |r| r.get(0))
                .unwrap_or(0.0);
            if bal < amount {
                return Err(AppError::Unprocessable("Insufficient wallet balance".into()));
            }
            tx.execute(
                "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                params![amount, user.user_id],
            )?;
            tx.execute(
                "INSERT INTO withdrawals (user_id, amount, status, payment_method, payment_identifier)
                 VALUES (?1, ?2, 'requested', ?3, ?4)",
                params![user.user_id, amount, method, ident],
            )?;
            let wid = tx.last_insert_rowid();
            let new_bal: f64 = tx.query_row("SELECT total FROM wallets WHERE user_id = ?1", params![user.user_id], |r| r.get(0))?;
            tx.commit()?;
            record_audit(&conn, user, "wallet_withdraw", "withdrawal", Some(wid.to_string()), 0.0, json!({ "amount": amount }));
            Ok(json!({
                "withdrawal_id": wid,
                "amount": amount,
                "status": "requested",
                "wallet_balance": round_money(new_bal)
            }))
        }

        "analytics_and_notifications" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let conn = state.lock_db();
            let profile = get_user_public_profile(&conn, user.user_id, Some(user.user_id))?;
            let (gross_earnings, tx_count): (f64, i64) = conn
                .query_row(
                    "SELECT COALESCE(SUM(amount), 0.0), COUNT(*) FROM transactions
                     WHERE recipient_user_id = ?1 AND sender_user_id != ?1 AND status = 'approved'",
                    params![user.user_id],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )
                .unwrap_or((0.0, 0));
            let audit_logs = list_user_agent_audit_logs(&conn, user.user_id, 25)?;
            let mut n_stmt = conn.prepare(
                "SELECT id, notification_type, message, is_read, created_at
                 FROM notifications WHERE to_user_id = ?1 ORDER BY created_at DESC LIMIT 20",
            )?;
            let notifications: Vec<Value> = n_stmt
                .query_map(params![user.user_id], |r| {
                    Ok(json!({
                        "id": r.get::<_, String>(0)?,
                        "type": r.get::<_, String>(1)?,
                        "message": r.get::<_, String>(2)?,
                        "is_read": r.get::<_, i64>(3)? != 0,
                        "created_at": r.get::<_, String>(4)?,
                    }))
                })?
                .filter_map(Result::ok)
                .collect();
            Ok(json!({
                "profile": profile,
                "gross_earnings": round_money(gross_earnings),
                "net_earnings_85_pct": round_money(gross_earnings * 0.85),
                "transactions_count": tx_count,
                "notifications": notifications,
                "agent_audit_logs": audit_logs
            }))
        }

        "shard_pool_telemetry" => {
            let conn = state.lock_db();
            let telemetry = crate::sharding::build_shard_pool_telemetry(state, &conn);
            if let Some(user) = auth {
                record_audit(&conn, user, "shard_pool_telemetry", "shards", None, 0.0, json!({}));
            }
            Ok(json!({
                "telemetry": telemetry
            }))
        }

        "persona_get" | "persona_list" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let conn = state.lock_db();
            if let Some(pid) = args
                .get("persona_id")
                .or_else(|| args.get("handle"))
                .and_then(|v| v.as_str())
                .filter(|s| !s.trim().is_empty())
            {
                let persona = crate::handlers::get_persona_by_id_or_handle(&conn, pid)?;
                Ok(json!({ "persona": persona }))
            } else {
                let personas = crate::handlers::list_user_agent_personas(&conn, Some(user.user_id))?;
                Ok(json!({ "personas": personas }))
            }
        }

        "persona_upsert" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let handle = args
                .get("handle")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim()
                .trim_start_matches('@')
                .to_lowercase()
                .replace(' ', "-");
            let display_name = args
                .get("display_name")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if handle.len() < 3 || display_name.is_empty() {
                return Err(AppError::Unprocessable(
                    "handle (min 3 chars) and display_name are required.".into(),
                ));
            }
            let description = args
                .get("description")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .to_string();
            let focus = crate::sharding::validate_persona_focus(
                args.get("focus")
                    .and_then(|v| v.as_str())
                    .unwrap_or("creator"),
            )?;
            let policy = crate::sharding::get_pool_tier_policy(
                args.get("pool_tier")
                    .and_then(|v| v.as_str())
                    .unwrap_or("central"),
            );
            let autonomy_mode = crate::sharding::validate_autonomy_mode(
                args.get("autonomy_mode")
                    .and_then(|v| v.as_str())
                    .unwrap_or("autonomous_co_pilot"),
            )?;
            let engagement_cadence = crate::sharding::validate_engagement_cadence(
                args.get("engagement_cadence")
                    .and_then(|v| v.as_str())
                    .unwrap_or("balanced"),
            )?;
            let shard_id = crate::sharding::compute_virtual_shard(&format!("persona:{}:{handle}", user.user_id));

            let raw_principles: Option<Vec<String>> = args
                .get("principles")
                .and_then(|v| v.as_array())
                .map(|arr| arr.iter().filter_map(|x| x.as_str().map(String::from)).collect());
            let raw_interests: Option<Vec<String>> = args
                .get("interests")
                .and_then(|v| v.as_array())
                .map(|arr| arr.iter().filter_map(|x| x.as_str().map(String::from)).collect());
            let raw_methods: Option<Vec<String>> = args
                .get("methods")
                .and_then(|v| v.as_array())
                .map(|arr| arr.iter().filter_map(|x| x.as_str().map(String::from)).collect());

            let conn = state.lock_db();
            let existing = crate::handlers::get_persona_by_id_or_handle(&conn, &handle).ok();
            let pid = if let Some(ex) = existing {
                if ex.user_id != user.user_id && user.role_id != 1 {
                    return Err(AppError::Forbidden("Persona handle owned by another user.".into()));
                }
                if let Some(ref p) = raw_principles {
                    crate::sharding::validate_practice_labels(p, "principles")?;
                }
                if let Some(ref i) = raw_interests {
                    crate::sharding::validate_practice_labels(i, "interests")?;
                }
                if let Some(ref m) = raw_methods {
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
                ex.id
            } else {
                let new_id = format!("persona-{}", &Uuid::new_v4().simple().to_string()[..12]);
                let principles = match raw_principles {
                    Some(ref p) => crate::sharding::validate_practice_labels(p, "principles")?,
                    None => vec!["Maintain authentic creator voice.".into()],
                };
                let interests = match raw_interests {
                    Some(ref i) => crate::sharding::validate_practice_labels(i, "interests")?,
                    None => vec!["Behind-the-scenes studio craft".into()],
                };
                let methods = match raw_methods {
                    Some(ref m) => crate::sharding::validate_practice_labels(m, "methods")?,
                    None => vec!["Pair PPV drops with free community polls.".into()],
                };
                let direction = args
                    .get("direction")
                    .and_then(|v| v.as_str())
                    .map(String::from)
                    .unwrap_or_else(|| description.clone());

                let rev = crate::sharding::PersonaPracticeRevision {
                    revision: 1,
                    focus: focus.clone(),
                    principles,
                    interests,
                    methods,
                    direction,
                    recorded_at: chrono::Utc::now().to_rfc3339(),
                };
                let p_json = serde_json::to_string(&vec![rev]).unwrap_or_else(|_| "[]".into());
                conn.execute(
                    "INSERT INTO agent_personas
                     (id, user_id, handle, display_name, description, focus, shard_id, pool_tier,
                      autonomy_mode, engagement_cadence, current_revision, practices_json, activities_json)
                     VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, 1, ?11, '[]')",
                    params![
                        new_id,
                        user.user_id,
                        handle,
                        display_name,
                        description,
                        focus,
                        shard_id,
                        policy.tier,
                        autonomy_mode,
                        engagement_cadence,
                        p_json
                    ],
                )?;
                new_id
            };
            let persona = crate::handlers::get_persona_by_id_or_handle(&conn, &pid)?;
            record_audit(
                &conn,
                user,
                "persona_upsert",
                "persona",
                Some(pid),
                0.0,
                json!({ "handle": persona.handle, "shard_id": persona.shard_id, "pool_tier": persona.pool_tier }),
            );
            drop(conn);
            state.sync_persona_to_postgres(&persona);
            Ok(json!({ "persona": persona }))
        }

        "persona_update_practice" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let pid = args
                .get("persona_id")
                .or_else(|| args.get("handle"))
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("persona_id required".into()))?;
            let direction = args
                .get("direction")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if direction.is_empty() {
                return Err(AppError::Unprocessable("direction required".into()));
            }
            let conn = state.lock_db();
            let mut persona = crate::handlers::get_persona_by_id_or_handle(&conn, pid)?;
            if persona.user_id != user.user_id && user.role_id != 1 {
                return Err(AppError::Forbidden("Forbidden".into()));
            }
            let prev = persona.practices.last().cloned();
            let focus = match args.get("focus").and_then(|v| v.as_str()) {
                Some(f) => crate::sharding::validate_persona_focus(f)?,
                None => prev
                    .as_ref()
                    .map(|p| p.focus.clone())
                    .unwrap_or_else(|| persona.focus.clone()),
            };
            let principles: Vec<String> = match args.get("principles").and_then(|v| v.as_array()) {
                Some(a) => {
                    let raw: Vec<String> = a.iter().filter_map(|x| x.as_str().map(String::from)).collect();
                    crate::sharding::validate_practice_labels(&raw, "principles")?
                }
                None => prev.as_ref().map(|p| p.principles.clone()).unwrap_or_default(),
            };
            let interests: Vec<String> = match args.get("interests").and_then(|v| v.as_array()) {
                Some(a) => {
                    let raw: Vec<String> = a.iter().filter_map(|x| x.as_str().map(String::from)).collect();
                    crate::sharding::validate_practice_labels(&raw, "interests")?
                }
                None => prev.as_ref().map(|p| p.interests.clone()).unwrap_or_default(),
            };
            let methods: Vec<String> = match args.get("methods").and_then(|v| v.as_array()) {
                Some(a) => {
                    let raw: Vec<String> = a.iter().filter_map(|x| x.as_str().map(String::from)).collect();
                    crate::sharding::validate_practice_labels(&raw, "methods")?
                }
                None => prev.as_ref().map(|p| p.methods.clone()).unwrap_or_default(),
            };

            if let Some(exp) = args.get("expected_revision").and_then(|v| v.as_i64()) {
                // GALXAI exact replay idempotency: if expected_revision == current_revision - 1
                // and the latest practice content matches, return idempotent replay.
                if exp == persona.current_revision - 1 {
                    if let Some(ref latest) = prev {
                        if latest.focus == focus
                            && latest.principles == principles
                            && latest.interests == interests
                            && latest.methods == methods
                            && latest.direction == direction
                        {
                            return Ok(json!({
                                "practice": latest,
                                "persona": persona,
                                "idempotent_replay": true
                            }));
                        }
                    }
                }
                if exp != persona.current_revision {
                    return Err(AppError::Conflict(format!(
                        "Expected revision {exp}, found {}",
                        persona.current_revision
                    )));
                }
            }
            let next_rev = persona.current_revision + 1;
            let rev_obj = crate::sharding::PersonaPracticeRevision {
                revision: next_rev,
                focus: focus.clone(),
                principles,
                interests,
                methods,
                direction: direction.to_string(),
                recorded_at: chrono::Utc::now().to_rfc3339(),
            };
            persona.practices.push(rev_obj.clone());
            let p_json = serde_json::to_string(&persona.practices).unwrap_or_else(|_| "[]".into());
            conn.execute(
                "UPDATE agent_personas SET focus = ?1, current_revision = ?2, practices_json = ?3, updated_at = datetime('now') WHERE id = ?4",
                params![focus, next_rev, p_json, persona.id],
            )?;
            let updated = crate::handlers::get_persona_by_id_or_handle(&conn, &persona.id)?;
            record_audit(
                &conn,
                user,
                "persona_update_practice",
                "persona",
                Some(persona.id),
                0.0,
                json!({ "revision": next_rev }),
            );
            drop(conn);
            state.sync_persona_to_postgres(&updated);
            Ok(json!({ "practice": rev_obj, "persona": updated }))
        }

        "persona_brief" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("read")?;
            let pid = args
                .get("persona_id")
                .or_else(|| args.get("handle"))
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("persona_id required".into()))?;
            let intent = args
                .get("intent")
                .and_then(|v| v.as_str())
                .unwrap_or("Engage with subscribers and publish high-craft studio updates")
                .trim();
            let audience = args.get("target_audience").and_then(|v| v.as_str());
            let raw_chars: Option<Vec<crate::sharding::PersonaCharacterReference>> =
                match args.get("characters") {
                    Some(v) if !v.is_null() => Some(serde_json::from_value(v.clone()).map_err(
                        |e| AppError::Unprocessable(format!("Invalid characters array: {e}")),
                    )?),
                    _ => None,
                };
            let conn = state.lock_db();
            let persona = crate::handlers::get_persona_by_id_or_handle(&conn, pid)?;
            let active_focus = persona
                .practices
                .last()
                .map(|p| p.focus.as_str())
                .unwrap_or(&persona.focus);
            let characters = crate::sharding::validate_character_references(
                raw_chars.as_deref(),
                active_focus,
            )?;
            let brief = crate::sharding::compile_persona_brief_with_characters(
                &persona,
                intent,
                audience,
                Some(&characters),
            );
            record_audit(
                &conn,
                user,
                "persona_brief",
                "persona",
                Some(persona.id),
                0.0,
                json!({ "intent": intent, "characters_count": characters.len() }),
            );
            Ok(json!({ "brief": brief }))
        }

        "persona_record_activity" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("engage")?;
            let pid = args
                .get("persona_id")
                .or_else(|| args.get("handle"))
                .and_then(|v| v.as_str())
                .ok_or_else(|| AppError::BadRequest("persona_id required".into()))?;
            let action_type = args
                .get("action_type")
                .and_then(|v| v.as_str())
                .unwrap_or("engagement")
                .trim();
            let description = args
                .get("description")
                .and_then(|v| v.as_str())
                .unwrap_or("")
                .trim();
            if description.is_empty() {
                return Err(AppError::Unprocessable("description required".into()));
            }
            let raw_chars: Option<Vec<crate::sharding::PersonaCharacterReference>> =
                match args.get("characters") {
                    Some(v) if !v.is_null() => Some(serde_json::from_value(v.clone()).map_err(
                        |e| AppError::Unprocessable(format!("Invalid characters array: {e}")),
                    )?),
                    _ => None,
                };

            let mut work_ids: Vec<String> = Vec::new();
            if let Some(arr) = args.get("work_ids").and_then(|v| v.as_array()) {
                for x in arr {
                    if let Some(s) = x.as_str() {
                        let trimmed = s.trim();
                        if !trimmed.is_empty() {
                            let valid = crate::sharding::require_valid_record_id(trimmed, "work_id")?;
                            work_ids.push(valid);
                        }
                    }
                }
            }
            let target_res_id = match args
                .get("target_resource_id")
                .and_then(|v| v.as_str())
                .map(|s| s.trim())
                .filter(|s| !s.is_empty())
            {
                Some(tr) => Some(crate::sharding::require_valid_record_id(
                    tr,
                    "target_resource_id",
                )?),
                None => None,
            };
            if let Some(ref target_id) = target_res_id {
                if !work_ids.contains(target_id) {
                    work_ids.push(target_id.clone());
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

            let conn = state.lock_db();
            let mut persona = crate::handlers::get_persona_by_id_or_handle(&conn, pid)?;
            if persona.user_id != user.user_id && user.role_id != 1 {
                return Err(AppError::Forbidden("Forbidden".into()));
            }
            let rev = args
                .get("practice_revision")
                .and_then(|v| v.as_i64())
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
            if rev_focus.eq_ignore_ascii_case("gravure") {
                if let Some(ref list) = raw_chars {
                    if list.is_empty() {
                        return Err(AppError::Unprocessable(
                            "Gravure activity requires at least one explicitly adult (18+) character reference when characters are specified.".into(),
                        ));
                    }
                }
            }
            let characters = crate::sharding::validate_character_references(
                raw_chars.as_deref(),
                rev_focus,
            )?;
            let characters_opt = if characters.is_empty() {
                None
            } else {
                Some(characters.clone())
            };

            let act_id = match args
                .get("id")
                .and_then(|v| v.as_str())
                .map(|s| s.trim())
                .filter(|s| !s.is_empty())
            {
                Some(custom_id) => crate::sharding::require_valid_record_id(custom_id, "activity id")?,
                None => format!("act-{}", &Uuid::new_v4().simple().to_string()[..12]),
            };

            let compiled_brief = crate::sharding::compile_persona_brief_with_characters(
                &persona,
                description,
                None,
                Some(&characters),
            );
            let brief_text = args
                .get("brief")
                .and_then(|v| v.as_str())
                .map(|s| s.trim().to_string())
                .filter(|s| !s.is_empty())
                .or_else(|| {
                    compiled_brief
                        .get("instructions")
                        .and_then(|v| v.as_str())
                        .map(String::from)
                });

            // Check exact replay idempotency or conflicting activity ID across all personas
            let all_personas = crate::handlers::list_user_agent_personas(&conn, None)?;
            for p in &all_personas {
                if let Some(existing_act) = p.activities.iter().find(|a| a.id == act_id) {
                    if p.id == persona.id
                        && existing_act.practice_revision == rev
                        && existing_act.action_type == action_type
                        && existing_act.target_resource_id == target_res_id
                        && existing_act.description == description
                        && existing_act.work_ids == work_ids
                        && existing_act.characters == characters_opt
                        && existing_act.brief == brief_text
                    {
                        return Ok(json!({
                            "activity": existing_act,
                            "persona": persona,
                            "idempotent_replay": true
                        }));
                    }
                    return Err(AppError::Conflict(format!(
                        "Activity '{act_id}' already exists with different content."
                    )));
                }
                if p.id != persona.id {
                    for wid in &work_ids {
                        if p.activities.iter().any(|a| a.work_ids.contains(wid)) {
                            return Err(AppError::Conflict(format!(
                                "Work '{wid}' is already attributed to persona @{}.",
                                p.handle
                            )));
                        }
                    }
                }
            }

            let now_iso = chrono::Utc::now().to_rfc3339();
            let act = crate::sharding::PersonaActivityRecord {
                id: act_id,
                persona_id: persona.id.clone(),
                practice_revision: rev,
                action_type: action_type.to_string(),
                target_resource_id: target_res_id,
                work_ids,
                characters: characters_opt,
                brief: brief_text,
                description: description.to_string(),
                occurred_at: now_iso.clone(),
                recorded_at: now_iso,
            };
            persona.activities.push(act.clone());
            let a_json = serde_json::to_string(&persona.activities).unwrap_or_else(|_| "[]".into());
            conn.execute(
                "UPDATE agent_personas SET activities_json = ?1, updated_at = datetime('now') WHERE id = ?2",
                params![a_json, persona.id],
            )?;
            let updated = crate::handlers::get_persona_by_id_or_handle(&conn, &persona.id)?;
            record_audit(
                &conn,
                user,
                "persona_record_activity",
                "persona",
                Some(persona.id),
                0.0,
                json!({ "activity_id": act.id, "action_type": action_type }),
            );
            drop(conn);
            state.sync_persona_to_postgres(&updated);
            Ok(json!({ "activity": act, "persona": updated }))
        }

        "persona_bootstrap_roster" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            user.require_scope("write")?;
            let pool_override = args.get("pool_tier_override").and_then(|v| v.as_str());
            let res = crate::handlers::bootstrap_commissioned_roster_for_user(
                state,
                user.user_id,
                &user.username,
                pool_override,
            )?;
            {
                let conn = state.lock_db();
                record_audit(
                    &conn,
                    user,
                    "persona_bootstrap_roster",
                    "persona_roster",
                    None,
                    0.0,
                    json!({
                        "installed_count": res["installed_count"],
                        "existing_before": res["existing_before"]
                    }),
                );
            }
            Ok(res)
        }

        "swarm_batch_execute" => {
            let user = auth.ok_or_else(|| AppError::Unauthorized("Authentication required".into()))?;
            let actions = args
                .get("actions")
                .or_else(|| args.get("steps"))
                .and_then(|v| v.as_array())
                .ok_or_else(|| AppError::Unprocessable("actions array is required.".into()))?;
            if actions.is_empty() || actions.len() > 25 {
                return Err(AppError::Unprocessable(
                    "actions must contain between 1 and 25 steps.".into(),
                ));
            }
            let stop_on_error = args
                .get("stop_on_error")
                .and_then(|v| v.as_bool())
                .unwrap_or(true);

            let mut results = Vec::with_capacity(actions.len());
            let mut succeeded = 0usize;
            let mut failed = 0usize;

            for (idx, step) in actions.iter().enumerate() {
                let step_tool = step
                    .get("tool_name")
                    .or_else(|| step.get("tool"))
                    .and_then(|v| v.as_str())
                    .unwrap_or("")
                    .trim();
                if step_tool.is_empty() || step_tool == "swarm_batch_execute" {
                    failed += 1;
                    results.push(json!({
                        "index": idx,
                        "step_index": idx,
                        "tool": step_tool,
                        "tool_name": step_tool,
                        "success": false,
                        "error": "Invalid or recursive tool_name in swarm_batch_execute"
                    }));
                    if stop_on_error {
                        break;
                    }
                    continue;
                }
                let mut step_args = step
                    .get("arguments")
                    .cloned()
                    .unwrap_or_else(|| json!({}));
                if !step_args.is_object() {
                    step_args = json!({});
                }
                if let Some(idem) = step
                    .get("idempotency_key")
                    .and_then(|v| v.as_str())
                    .filter(|s| !s.trim().is_empty())
                {
                    if let Some(obj) = step_args.as_object_mut() {
                        obj.insert("idempotency_key".to_string(), json!(idem.trim()));
                    }
                }
                match execute_mcp_tool(state, Some(user), step_tool, &step_args) {
                    Ok(res) => {
                        let throttled = res
                            .get("throttled")
                            .and_then(|v| v.as_bool())
                            .unwrap_or(false);
                        let step_gov = res.get("_quota_governance").cloned().unwrap_or(Value::Null);
                        let step_shard = step_gov.get("shard_id").cloned().unwrap_or(Value::Null);
                        let step_pool = step_gov.get("pool_tier").cloned().unwrap_or(Value::Null);
                        let step_mode = step_gov.get("throttle_mode").cloned().unwrap_or(Value::Null);
                        let step_pacing = step_gov.get("pacing_delay_ms").cloned().unwrap_or(json!(0));
                        if throttled {
                            failed += 1;
                        } else {
                            succeeded += 1;
                        }
                        results.push(json!({
                            "index": idx,
                            "step_index": idx,
                            "tool": step_tool,
                            "tool_name": step_tool,
                            "success": !throttled,
                            "throttled": throttled,
                            "shard_id": step_shard,
                            "pool_tier": step_pool,
                            "throttle_mode": step_mode,
                            "pacing_delay_ms": step_pacing,
                            "_quota_governance": step_gov,
                            "result": res
                        }));
                        if throttled && stop_on_error {
                            break;
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
                            "tool": step_tool,
                            "tool_name": step_tool,
                            "success": false,
                            "error": msg
                        }));
                        if stop_on_error {
                            break;
                        }
                    }
                }
            }

            let telemetry = {
                let conn = state.lock_db();
                crate::sharding::build_shard_pool_telemetry(state, &conn)
            };
            Ok(json!({
                "success": failed == 0,
                "total_steps": actions.len(),
                "batch_size": actions.len(),
                "executed": results.len(),
                "succeeded": succeeded,
                "succeeded_count": succeeded,
                "failed": failed,
                "failed_count": failed,
                "results": results,
                "telemetry_snapshot": {
                    "shards": telemetry["shards"],
                    "virtual_shards_count": telemetry["virtual_shards_count"],
                    "cluster_summary": telemetry["cluster_summary"]
                }
            }))
        }

        other => Err(AppError::NotFound(format!("Unknown MCP tool: {other}"))),
    }
}

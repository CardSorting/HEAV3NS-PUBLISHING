# REST & MCP API Reference

All API endpoints are prefixed with `/api` on the Rust backend (`http://127.0.0.1:8080/api`) and proxied by Next.js 16 (`http://127.0.0.1:3000/api`).

As documented in **[ADR-008](../adr/008-ddd-bounded-context-handlers-refactoring.md)**, every route in [`backend/src/main.rs`](../../backend/src/main.rs) is bound explicitly via `handlers::<domain>::*` across **9 Domain-Driven Design (DDD) bounded-context directories** under [`backend/src/handlers/`](../../backend/src/handlers/).

## Authentication Modes
- **Public**: No authentication required.
- **Optional Auth (`OptionalAuthUser`)**: Accepts `Authorization: Bearer <token>`, `X-Agent-Token: hp_mcp_*`, or `justfans_token` cookie if present to personalize `is_unlocked`, `is_subscribed`, `user_liked`, and `user_bookmarked` flags.
- **Protected (`AuthUser`)**: Requires a valid JWT or scoped `hp_mcp_*` agent token (`401 Unauthorized` otherwise).
- **Admin (`role_id == 1`)**: Requires a valid JWT belonging to a Platform Admin (`403 Forbidden` otherwise).

---

## 1. Identity Bounded Context (`handlers::identity::*`)
**Modules:** [`backend/src/handlers/identity/auth.rs`](../../backend/src/handlers/identity/auth.rs) · [`backend/src/handlers/identity/settings.rs`](../../backend/src/handlers/identity/settings.rs)

| Method | Endpoint | Handler (`handlers::identity::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `POST` | `/api/auth/register` | `auth::register` | Public | Creates user, `$100.00` wallet, default lists (`Followers`, `Following`, `Blocked`), sets cookie & returns `{ token, user }`. |
| `POST` | `/api/auth/login` | `auth::login` | Public | Authenticates by email or username + password, sets cookie & returns `{ token, user }`. |
| `POST` | `/api/auth/logout` | `auth::logout` | Public | Clears `justfans_token` cookie (`Max-Age=0`). |
| `GET` | `/api/auth/me` | `auth::get_me` | Protected | Returns current `user` profile, `unread_notifications`, and `unread_messages`. |
| `GET` | `/api/settings` | `settings::get_settings_data` | Protected | Returns `{ profile, subscriptions, transactions, withdrawals, verification, billing, referrals, agent_tokens, agent_audit_logs }`. |
| `POST` | `/api/settings/profile` | `settings::update_profile_settings` | Protected | Updates profile (`name`, `username`, `bio`, `location`, `website`, `birthdate`, `gender_pronoun`, `avatar`, `cover`). |
| `POST` | `/api/settings/rates` | `settings::update_rates_settings` | Protected | Updates creator subscription pricing, promotional offers (`creator_offers`), & `paid_profile` / `open_profile` flags. |
| `POST` | `/api/settings/account` | `settings::update_account_settings` | Protected | Updates email address, billing details, and/or password. |
| `POST` | `/api/settings/privacy` | `settings::update_privacy_settings` | Protected | Updates `public_profile`, `enable_2fa`, and `enable_geoblocking`. |
| `POST` | `/api/settings/verify` | `settings::submit_verification_request` | Protected | Submits ID verification document URLs. Body: `{ files: string[] }`. |

---

## 2. Discovery Bounded Context (`handlers::discovery::*`)
**Modules:** [`backend/src/handlers/discovery/feed.rs`](../../backend/src/handlers/discovery/feed.rs) · [`backend/src/handlers/discovery/profiles.rs`](../../backend/src/handlers/discovery/profiles.rs) · [`backend/src/handlers/discovery/search.rs`](../../backend/src/handlers/discovery/search.rs)

| Method | Endpoint | Handler (`handlers::discovery::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/feed?page=1&filter=all` | `feed::get_feed` | Optional | Paginated feed (`20` per page). Supports category/media filters (`all`, `subscribed`, `media`, `photos`, `videos`, `audio`, `polls`) and search query. |
| `POST` | `/api/suggestions/filter` | `feed::filter_suggestions` | Optional | Body: `{ "free_only": true }`. Returns suggested creators. |
| `GET` | `/api/profile/:username?filter=` | `profiles::get_profile` | Optional | Returns `{ profile, posts, recent_media, streams }`. `recent_media` includes only unlocked attachments. |
| `GET` | `/api/search?query=&filter=top` | `search::search_handler` | Optional | Searches public users, posts, and streams (`top`, `latest`, `people`, `photos`, `videos`, `live`). |

---

## 3. Content Bounded Context (`handlers::content::*`)
**Modules:** [`backend/src/handlers/content/posts.rs`](../../backend/src/handlers/content/posts.rs) · [`backend/src/handlers/content/engagement.rs`](../../backend/src/handlers/content/engagement.rs)

| Method | Endpoint | Handler (`handlers::content::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/posts/:post_id` | `posts::get_single_post` | Optional | Returns `{ post, author }` with paywall rules applied. |
| `POST` | `/api/posts/save` | `posts::save_post` | Protected | Creates or updates a post. Body: `{ id?, text, price?, is_pinned?, release_date?, expire_date?, attachments?, poll_answers? }`. |
| `DELETE` | `/api/posts/:post_id` | `posts::delete_post` | Protected | Deletes a post (owner or Admin only). |
| `POST` | `/api/posts/pin` | `posts::toggle_pin_post` | Protected | Pins/unpins a post on the creator's profile. Body: `{ post_id }`. |
| `POST` | `/api/posts/react` | `engagement::toggle_reaction` | Protected | Toggles a like on a post, comment, or reel. Body: `{ post_id?, comment_id?, reel_id? }`. |
| `POST` | `/api/posts/bookmark` | `engagement::toggle_bookmark` | Protected | Toggles a bookmark on a post or reel. Body: `{ post_id?, reel_id? }`. |
| `POST` | `/api/posts/comments` | `engagement::add_comment` | Protected | Adds a comment to a post. Body: `{ post_id, message }`. |
| `PUT` / `POST` | `/api/posts/comments/:comment_id` | `engagement::edit_comment` | Protected | Updates an existing post comment (author or Admin). |
| `DELETE` | `/api/posts/comments/:comment_id` | `engagement::delete_comment` | Protected | Deletes a post comment (comment author or Admin). |
| `POST` | `/api/posts/polls/vote` | `engagement::vote_poll` | Protected | Casts or updates a poll vote. Body: `{ poll_id, answer_id }`. Validates `answer_id` belongs to `poll_id`. |

---

## 4. Social Bounded Context (`handlers::social::*`)
**Modules:** [`backend/src/handlers/social/lists.rs`](../../backend/src/handlers/social/lists.rs) · [`backend/src/handlers/social/graph.rs`](../../backend/src/handlers/social/graph.rs) · [`backend/src/handlers/social/notifications.rs`](../../backend/src/handlers/social/notifications.rs)

| Method | Endpoint | Handler (`handlers::social::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/bookmarks` | `lists::get_bookmarks` | Protected | Returns `{ posts, reels, total }` bookmarked by the current user. |
| `GET` | `/api/lists` | `lists::get_lists` | Protected | Lists all user lists (`Followers`, `Following`, `Blocked`, and `custom` lists) with member profiles. |
| `POST` | `/api/lists` | `lists::save_list` | Protected | Creates a new custom list. Body: `{ name }`. |
| `GET` | `/api/lists/:list_id` | `lists::get_list_detail` | Protected | Returns details and members of a specific list. |
| `PUT` | `/api/lists/:list_id` | `lists::rename_list` | Protected | Renames a custom user list. |
| `POST` | `/api/lists/:list_id/clear` | `lists::clear_list` | Protected | Removes all members from a user list. |
| `DELETE` | `/api/lists/:list_id` | `lists::delete_list` | Protected | Deletes a custom list. |
| `POST` | `/api/lists/members` | `lists::toggle_list_member` | Protected | Toggles a user's membership in a list. Body: `{ list_id, member_user_id }`. |
| `POST` | `/api/lists/follow` | `graph::toggle_follow` | Protected | Toggles following a creator. Body: `{ target_user_id }`. |
| `POST` | `/api/lists/block` | `graph::toggle_block_user` | Protected | Toggles blocking a user (`Blocked` list + bidirectional block enforcement). |
| `GET` | `/api/notifications?filter=all` | `notifications::get_notifications` | Protected | Marks notifications as read and returns up to 50 notifications filtered by type (`all`, `like`, `comment`, `subscription`, `tip`, `message`). |

---

## 5. Messaging Bounded Context (`handlers::messaging::*`)
**Modules:** [`backend/src/handlers/messaging/dm.rs`](../../backend/src/handlers/messaging/dm.rs) · [`backend/src/handlers/messaging/broadcast.rs`](../../backend/src/handlers/messaging/broadcast.rs)

| Method | Endpoint | Handler (`handlers::messaging::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/messenger/contacts` | `dm::get_messenger_contacts` | Protected | Returns conversation contacts with last message preview and unread counts. |
| `GET` | `/api/messenger/messages/:user_id` | `dm::get_messages_with_user` | Protected | Marks incoming messages from `:user_id` as seen and returns conversation thread (masking locked PPV messages). |
| `POST` | `/api/messenger/send` | `dm::send_message` | Protected | Sends a direct message (or paid PPV message). Body: `{ receiver_id, message, price?, attachment_ids? }`. |
| `POST` | `/api/messenger/pin/:message_id` | `dm::toggle_pin_message` | Protected | Pins or unpins a message within a conversation. |
| `DELETE` | `/api/messenger/message/:message_id` | `dm::delete_message` | Protected | Deletes a sent message (sender or Admin). |
| `POST` | `/api/messenger/broadcast` | `broadcast::broadcast_message` | Protected | Sends a mass broadcast DM (with optional PPV price) to followers, subscribers, or custom list members. |

---

## 6. Monetization Bounded Context (`handlers::monetization::*`)
**Modules:** [`backend/src/handlers/monetization/ledger.rs`](../../backend/src/handlers/monetization/ledger.rs) · [`backend/src/handlers/monetization/analytics.rs`](../../backend/src/handlers/monetization/analytics.rs)

| Method | Endpoint | Handler (`handlers::monetization::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/analytics/overview` | `analytics::get_creator_analytics` | Protected | Returns creator revenue breakdown (85/15 split), subscriber telemetry, and autonomous agent spend metrics. |
| `POST` | `/api/payments/initiate` | `ledger::initiate_payment` | Protected | Processes payments (`deposit`, `subscription`, `post-unlock`, `reel-unlock`, `message-unlock`, `stream-unlock`, `tip`) inside `BEGIN IMMEDIATE` transactions. Credits 85% to creator wallet. |
| `GET` | `/api/invoices/:id` | `ledger::get_invoice_detail` | Protected | Returns invoice details by `invoice_id` or `transaction.id` (accessible only to sender, recipient, or Admin). |
| `POST` | `/api/subscriptions/:sub_id/cancel` | `ledger::cancel_subscription` | Protected | Cancels an active subscription (`status = 'canceled'`). |
| `POST` | `/api/withdrawals/request` | `ledger::request_withdrawal` | Protected | Requests a creator payout (min `$20.00`). Deducts from wallet immediately inside `BEGIN IMMEDIATE`. |

---

## 7. Media Bounded Context (`handlers::media::*`)
**Modules:** [`backend/src/handlers/media/vault.rs`](../../backend/src/handlers/media/vault.rs) · [`backend/src/handlers/media/reels.rs`](../../backend/src/handlers/media/reels.rs) · [`backend/src/handlers/media/stories.rs`](../../backend/src/handlers/media/stories.rs) · [`backend/src/handlers/media/streams.rs`](../../backend/src/handlers/media/streams.rs)

| Method | Endpoint | Handler (`handlers::media::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/vault` | `vault::get_vault_items` | Protected | Lists all uploaded Media Vault items for the current user. |
| `POST` | `/api/attachment/upload` | `vault::upload_attachment` | Protected | `multipart/form-data` upload (up to 50MB). Sanitizes file extension, writes to `/uploads`, and records in `attachments`. |
| `POST` | `/api/vault/:att_id/archive` | `vault::toggle_archive_vault_item` | Protected | Toggles archive status (`is_archived`) on a Media Vault item. |
| `DELETE` | `/api/vault/:att_id` | `vault::delete_vault_item` | Protected | Deletes a Media Vault attachment. |
| `GET` | `/api/reels` | `reels::get_reels` | Optional | Lists vertical video Reels with PPV paywall redaction (`compute_reel_unlocked`), likes, comments count, and bookmark status. |
| `POST` | `/api/reels` | `reels::create_reel` | Protected | Publishes a new Reel. Body: `{ caption, video_url?, cover_url?, price? }`. |
| `DELETE` | `/api/reels/:reel_id` | `reels::delete_reel` | Protected | Deletes a Reel (owner or Admin). |
| `POST` | `/api/reels/:reel_id/view` | `reels::increment_reel_view` | Optional | Increments the view counter on a Reel. |
| `GET` | `/api/reels/:reel_id/comments` | `reels::get_reel_comments` | Public | Lists comments on a Reel. |
| `POST` | `/api/reels/comments` | `reels::add_reel_comment` | Protected | Adds a comment to a Reel. Body: `{ reel_id, message }`. |
| `DELETE` | `/api/reels/comments/:comment_id` | `reels::delete_reel_comment` | Protected | Deletes a Reel comment (author or Admin). |
| `GET` | `/api/stories` | `stories::get_stories` | Public | Lists active 24h stories and pinned highlights. |
| `POST` | `/api/stories` | `stories::create_story` | Protected | Creates a 24h story. Body: `{ caption, media_url?, bg_preset?, is_highlight? }`. |
| `DELETE` | `/api/stories/:story_id` | `stories::delete_story` | Protected | Deletes a story (owner or Admin). |
| `POST` | `/api/stories/:story_id/view` | `stories::increment_story_view` | Optional | Records a story view (`story_views` + `views_count`). |
| `POST` | `/api/stories/:story_id/pin` | `stories::toggle_story_highlight` | Protected | Pins/unpins a story as a permanent profile Highlight (`is_highlight`). |
| `GET` | `/api/streams` | `streams::get_streams` | Optional | Lists active and ended streams with paywall status (`compute_stream_unlocked`). |
| `POST` | `/api/streams` | `streams::save_stream` | Protected | Starts or updates a live stream and generates an RTMP key. Body: `{ name, price?, requires_subscription?, is_public? }`. |
| `GET` | `/api/streams/:stream_id` | `streams::get_stream_detail` | Optional | Returns `{ stream, messages }` with HLS and RTMP key protection. |
| `DELETE` | `/api/streams/:stream_id` | `streams::delete_stream` | Protected | Deletes a stream (owner or Admin). |
| `POST` | `/api/streams/:stream_id/stop` | `streams::stop_stream` | Protected | Ends an active stream (`status = 'ended'`). |
| `POST` | `/api/streams/comment` | `streams::add_stream_comment` | Protected | Posts a message in live stream chat. Body: `{ stream_id, message }`. |

---

## 8. Platform Bounded Context (`handlers::platform::*`)
**Modules:** [`backend/src/handlers/platform/home.rs`](../../backend/src/handlers/platform/home.rs) · [`backend/src/handlers/platform/admin.rs`](../../backend/src/handlers/platform/admin.rs)

| Method | Endpoint | Handler (`handlers::platform::*`) | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/health` | `home::health_check` | Public | Returns `{ "status": "ok", "service": "heav3ns-publishing-rust-backend", "version": "2.0.0" }`. |
| `GET` | `/api/home` | `home::get_home_data` | Optional | Returns site metadata and featured creator profiles. |
| `GET` | `/api/pages/:slug` | `admin::get_public_page` | Public | Returns public page HTML content (`terms-and-conditions`, `privacy`, `help`). |
| `POST` | `/api/contact` | `admin::send_contact_message` | Public | Submits a support contact message. Body: `{ email, subject, message }`. |
| `POST` | `/api/report` | `admin::submit_report` | Protected | Submits a moderation report. Body: `{ reported_user_id, post_id?, report_type, details? }`. |
| `POST` | `/api/ai/suggest` | `admin::generate_ai_suggestion` | Protected | Generates an AI creator caption suggestion. Body: `{ topic? }`. |
| `GET` | `/api/admin/dashboard` | `admin::get_admin_dashboard` | Admin | Returns platform metrics, users, withdrawals, reports, and pending ID verifications. |
| `POST` | `/api/admin/withdrawals/:id` | `admin::handle_admin_withdrawal` | Admin | Approves or rejects a payout withdrawal (`action: "approve" | "reject"`). Rejecting refunds the creator's wallet inside `BEGIN IMMEDIATE`. |
| `POST` | `/api/admin/verifications/:id` | `admin::handle_admin_verification` | Admin | Approves or rejects a creator ID verification (`action: "approve" | "reject"`). Approving sets `users.identity_verified_at`. |
| `POST` | `/api/admin/reports/:id` | `admin::handle_admin_report` | Admin | Resolves or dismisses a moderation report (`action: "resolve" | "dismiss"`). |

---

## 9. Governance Bounded Context & MCP Protocol (`handlers::governance::*` & `mcp::*`)
**Modules:** [`backend/src/handlers/governance/tokens.rs`](../../backend/src/handlers/governance/tokens.rs) · [`backend/src/handlers/governance/personas.rs`](../../backend/src/handlers/governance/personas.rs) · [`backend/src/mcp.rs`](../../backend/src/mcp.rs)

| Method | Endpoint | Handler | Auth | Description |
| --- | --- | --- | --- | --- |
| `GET` | `/api/agent/tokens` | `governance::get_agent_tokens` | Protected | Lists scoped `hp_mcp_*` agent tokens (with shard, pool tier, and live token-bucket quota metrics) and recent audit logs. |
| `POST` | `/api/agent/tokens` | `governance::create_agent_token` | Protected | Mints a new `hp_mcp_*` agent token assigned to a deterministic virtual shard (`0..15`) and relay pool tier (`central`, `premium`, `studio`). |
| `POST` | `/api/agent/tokens/:id/revoke` | `governance::revoke_agent_token` | Protected | Immediately revokes an agent token (`is_active = 0`). |
| `DELETE` | `/api/agent/tokens/:id` | `governance::delete_agent_token` | Protected | Permanently deletes an agent token record. |
| `GET` | `/api/agent/shards` | `governance::get_agent_shards_telemetry` | Optional | Returns 16-shard load distribution, 3 relay pool tier policies, and PostgreSQL cluster pool health telemetry. |
| `GET` | `/api/agent/personas` | `governance::get_agent_personas` | Optional | Lists No-Inference Autonomous Personas (with practice revision history and recent activities). |
| `POST` | `/api/agent/personas` | `governance::upsert_agent_persona` | Protected | Creates or updates an autonomous persona assigned to a virtual shard and relay pool tier. |
| `POST` | `/api/agent/personas/:id/practice` | `governance::update_persona_practice` | Protected | Appends a numbered practice revision with optimistic concurrency control (`expected_revision` -> `409 Conflict` on mismatch). |
| `POST` | `/api/agent/personas/:id/brief` | `governance::compile_persona_brief_handler` | Protected | Compiles a zero-inference Markdown execution brief (`compile_persona_brief`) for external autonomous loops. |
| `POST` | `/api/agent/personas/:id/activities` | `governance::record_persona_activity` | Protected | Records an immutable follower/content activity entry tied to a valid `practice_revision`. |
| `GET` | `/api/mcp` & `/api/mcp/manifest` | `mcp::get_mcp_manifest` | Public | Returns MCP protocol version (`2024-11-05`), server info (`2.4.0`), 32 tools, 6 resources, and 3 prompt workflows. |
| `POST` | `/api/mcp` | `mcp::handle_mcp_rpc` | Optional / `X-Agent-Token` | JSON-RPC 2.0 MCP endpoint (`initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`). Supports `X-Idempotency-Key` header. |
| `POST` | `/api/mcp/tools/:tool_name` | `mcp::call_mcp_tool_rest` | Optional / `X-Agent-Token` | Direct REST bridge for any MCP tool; emits `X-Heav3ns-Shard`, `X-Heav3ns-Pool`, `X-RateLimit-Throttle-Mode`, and `X-RateLimit-Pacing-Ms` headers. |

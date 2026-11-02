# ADR-008: Domain-Driven Design (DDD) Bounded-Context Modularization of Backend Route Handlers

- **Status:** Accepted & Implemented
- **Date:** 2026-11-02
- **Deciders:** HEAV3NS-Publishing Core Architecture Group
- **Code References:**
  - [`backend/src/handlers.rs`](../../backend/src/handlers.rs)
  - [`backend/src/handlers/`](../../backend/src/handlers/)
  - [`backend/src/main.rs`](../../backend/src/main.rs)

---

## 1. Context & Problem Statement

As **HEAV3NS-Publishing** evolved to support 75+ REST endpoints, 6 creator monetization streams, short-form Reels, 24-hour Stories, HLS Live Streaming, a 16-shard / 3-pool GCRA soft quota engine, No-Inference Autonomous Personas, and a full Model Context Protocol (MCP 2024-11-05) server, [`backend/src/handlers.rs`](../../backend/src/handlers.rs) grew into a **6,500+ line monolithic file**.

While the monolith was convenient during initial prototyping, it introduced several engineering bottlenecks:
1. **High Cognitive & Context Window Overhead**: Both human contributors and autonomous AI coding agents had to navigate thousands of lines of unrelated domain logic when auditing or modifying a single feature (e.g., editing a poll vote handler alongside financial ledger transactions).
2. **Blurred Domain Boundaries**: Authentication, social graph mutations, media vault uploads, and ACID wallet settlements lived in a single flat namespace without explicit module boundaries.
3. **Merge Contention**: Concurrent work across discovery feeds, governance tokens, and monetization ledgers frequently touched the same file.

---

## 2. Decision

We decomposed the 6,500+ line [`backend/src/handlers.rs`](../../backend/src/handlers.rs) monolith into **9 Domain-Driven Design (DDD) bounded-context directories** under [`backend/src/handlers/`](../../backend/src/handlers/), comprising **22 focused sub-domain modules**:

```text
backend/src/
├── handlers.rs                        # Top-level DDD facade (pub mod + pub use re-exports)
└── handlers/
    ├── content/                       # Bounded Context 1: Publishing & Interactive Engagement
    │   ├── mod.rs
    │   ├── posts.rs                   # get_single_post, save_post, delete_post, toggle_pin_post
    │   └── engagement.rs              # toggle_reaction, toggle_bookmark, add/edit/delete_comment, vote_poll
    ├── discovery/                     # Bounded Context 2: Feed, Creator Profiles & Explore Search
    │   ├── mod.rs
    │   ├── feed.rs                    # get_feed, filter_suggestions
    │   ├── profiles.rs                # get_profile
    │   └── search.rs                  # search_handler
    ├── governance/                    # Bounded Context 3: Agent Tokens, Shards & Autonomous Personas
    │   ├── mod.rs
    │   ├── tokens.rs                  # create/get/revoke/delete_agent_token, list_user_agent_tokens/audit_logs
    │   └── personas.rs                # get_agent_shards_telemetry, get/upsert_agent_personas, update_persona_practice, compile_persona_brief_handler, record_persona_activity
    ├── identity/                      # Bounded Context 4: Authentication, Account & Creator Settings
    │   ├── mod.rs
    │   ├── auth.rs                    # register, login, logout, get_me, normalize_db_datetime
    │   └── settings.rs                # get_settings_data, update_profile/rates/account/privacy_settings, submit_verification_request
    ├── media/                         # Bounded Context 5: Vault, Reels, Stories & Live Streams
    │   ├── mod.rs
    │   ├── vault.rs                   # get_vault_items, upload_attachment, toggle_archive_vault_item, delete_vault_item
    │   ├── reels.rs                   # get_reels, create_reel, delete_reel, increment_reel_view, get/add/delete_reel_comment, compute_reel_unlocked
    │   ├── stories.rs                 # get_stories, create_story, delete_story, increment_story_view, toggle_story_highlight
    │   └── streams.rs                 # get_streams, save_stream, stop_stream, delete_stream, get_stream_detail, add_stream_comment, compute_stream_unlocked
    ├── messaging/                     # Bounded Context 6: 1-on-1 Direct Messenger & Mass Broadcasts
    │   ├── mod.rs
    │   ├── dm.rs                      # get_messenger_contacts, get_messages_with_user, send_message, toggle_pin_message, delete_message
    │   └── broadcast.rs               # broadcast_message
    ├── monetization/                  # Bounded Context 7: ACID Financial Ledger, Payouts & Analytics
    │   ├── mod.rs
    │   ├── ledger.rs                  # initiate_payment, cancel_subscription, request_withdrawal, get_invoice_detail
    │   └── analytics.rs               # get_creator_analytics
    ├── platform/                      # Bounded Context 8: Health, Home Discovery, Public CMS & Admin Moderation
    │   ├── mod.rs
    │   ├── home.rs                    # health_check, get_home_data
    │   └── admin.rs                   # get_public_page, send_contact_message, submit_report, generate_ai_suggestion, get_admin_dashboard, handle_admin_withdrawal/report/verification
    └── social/                        # Bounded Context 9: Custom Lists, Follow/Block Graph & Notifications
        ├── mod.rs
        ├── lists.rs                   # get_bookmarks, get_lists, get_list_detail, save_list, rename_list, clear_list, delete_list, toggle_list_member
        ├── graph.rs                   # toggle_follow, toggle_block_user
        └── notifications.rs           # get_notifications
```

### Key Architectural Conventions
1. **Clean Bounded-Context Facades (`mod.rs`)**: Each of the 9 domain directories contains a concise `mod.rs` that declares its sub-modules and re-exports their public handlers (`pub use <submodule>::*;`).
2. **Explicit Domain-Qualified Route Binding in `main.rs`**: The Axum router in [`backend/src/main.rs`](../../backend/src/main.rs) binds every endpoint using explicit domain paths (`handlers::platform::*`, `handlers::identity::*`, `handlers::discovery::*`, `handlers::content::*`, `handlers::social::*`, `handlers::messaging::*`, `handlers::monetization::*`, `handlers::governance::*`, `handlers::media::*`), making the domain ownership of every HTTP route immediately clear at the router level.
3. **Backward-Compatible Top-Level Facade (`handlers.rs`)**: [`backend/src/handlers.rs`](../../backend/src/handlers.rs) is now a 20-line facade that declares all 9 domain modules and re-exports them (`pub use <domain>::*;`), preserving compatibility with internal callers such as [`backend/src/mcp.rs`](../../backend/src/mcp.rs).

---

## 3. Consequences

### Positive
- **High-Cohesion, Low-Coupling Modules**: Security auditors can inspect [`backend/src/handlers/monetization/ledger.rs`](../../backend/src/handlers/monetization/ledger.rs) in isolation to verify `BEGIN IMMEDIATE` ACID payment invariants without wading through UI or social graph code.
- **Agent-Friendly Context Windows**: Every sub-domain file is sized between ~50 and ~600 lines, allowing AI coding agents and human reviewers to load an entire bounded context cleanly.
- **Zero Behavioural Regression**: All 11 integration test suites in [`backend/src/main.rs`](../../backend/src/main.rs) and strict `cargo clippy --all-targets -- -D warnings` pass with zero warnings.


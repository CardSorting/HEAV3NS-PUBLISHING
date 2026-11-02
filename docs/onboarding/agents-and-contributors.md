# Onboarding Guide: AI Coding Agents & Open-Source Contributors

This guide is optimized for **autonomous AI coding agents** and **human software engineers** working inside the **HEAV3NS-Publishing** repository. Follow this context map to navigate the codebase, understand strict invariants, and verify changes with zero regressions.

---

## 1. High-Signal Context Map (Read Order)

When starting a coding, debugging, or feature task, inspect files in this exact order:

1. **[`docs/knowledge-base/overview.md`](../knowledge-base/overview.md)** — Domain vocabulary, the 6 non-negotiable business invariants, and the full DDD codebase map.
2. **[`backend/src/models.rs`](../../backend/src/models.rs)** — Canonical Rust `serde` data structures serialized across the API boundary.
3. **[`backend/src/db.rs`](../../backend/src/db.rs)** & **[`backend/migrations/001_heav3ns_publishing_postgres.sql`](../../backend/migrations/001_heav3ns_publishing_postgres.sql)** — 28-table PostgreSQL & SQLite schema (`init_db`, `init_postgres_db`), demo seed data (`seed_db`), paywall evaluation (`is_post_unlocked`), and redacted view builders (`get_user_public_profile`, `build_post_view`).
4. **[`backend/src/auth.rs`](../../backend/src/auth.rs)** & **[`backend/src/sharding.rs`](../../backend/src/sharding.rs)** — Dual-channel JWT + scoped `hp_mcp_*` agent token authentication, HMAC-SHA256 token hashing (`hash_agent_token`), capability scope enforcement (`require_scope`), 16-shard consistent hashing (`0..15`), 3 relay pool tiers (`central`, `premium`, `studio`), and GCRA soft token-bucket quota pacing.
5. **[`backend/src/mcp.rs`](../../backend/src/mcp.rs)** — Model Context Protocol (MCP 2024-11-05) JSON-RPC 2.0 server (`POST /api/mcp`), manifest (`GET /api/mcp/manifest`), and 32 autonomous agent tools.
6. **[`backend/src/handlers.rs`](../../backend/src/handlers.rs)** & **[`backend/src/handlers/`](../../backend/src/handlers/)** ([ADR-008](../adr/008-ddd-bounded-context-handlers-refactoring.md)) — All 75+ Axum route handlers organized into **9 DDD bounded-context directories** (each with a `mod.rs` facade re-exporting focused sub-domain files):
   - [`handlers/content/{posts,engagement}.rs`](../../backend/src/handlers/content/) — Post CRUD, pin toggles, likes, bookmarks, comments, and poll voting.
   - [`handlers/discovery/{feed,profiles,search}.rs`](../../backend/src/handlers/discovery/) — Category-filtered feed, creator suggestions, public profiles, and Explore search.
   - [`handlers/governance/{tokens,personas}.rs`](../../backend/src/handlers/governance/) — Scoped `hp_mcp_*` agent tokens, 16-shard telemetry, and No-Inference Autonomous Persona Studio.
   - [`handlers/identity/{auth,settings}.rs`](../../backend/src/handlers/identity/) — Registration, login, logout, `/auth/me`, settings tabs, and KYC verification submission.
   - [`handlers/media/{vault,reels,stories,streams}.rs`](../../backend/src/handlers/media/) — Media Vault uploads/archiving, PPV Reels, 24h Stories/Highlights, and RTMP/HLS Live Streams.
   - [`handlers/messaging/{dm,broadcast}.rs`](../../backend/src/handlers/messaging/) — 1-on-1 Direct Messenger, PPV message unlocks, and mass subscriber/follower Broadcast DMs.
   - [`handlers/monetization/{ledger,analytics}.rs`](../../backend/src/handlers/monetization/) — `BEGIN IMMEDIATE` ACID payment ledger (`initiate_payment`), 85/15 creator split, withdrawals, invoices, and creator analytics.
   - [`handlers/platform/{home,admin}.rs`](../../backend/src/handlers/platform/) — Health check, home discovery, public CMS pages, AI caption assistant, moderation reports, and Admin Dashboard.
   - [`handlers/social/{lists,graph,notifications}.rs`](../../backend/src/handlers/social/) — Saved bookmarks, custom user lists, follow/block graph, and activity notifications.
7. **[`backend/src/main.rs`](../../backend/src/main.rs)** — Explicit domain-qualified route registration (`handlers::<domain>::*` in `build_app`) and the end-to-end integration test suites (`mod tests`).
8. **[`frontend/src/lib/api.ts`](../../frontend/src/lib/api.ts)** — TypeScript interfaces and `apiFetch` client helpers consumed by the Next.js 16 App Router pages.
9. **[`frontend/src/app/my/agents/page.tsx`](../../frontend/src/app/my/agents/page.tsx)** & **[`frontend/src/components/AppShell.tsx`](../../frontend/src/components/AppShell.tsx)** — Autonomous Persona Studio, 16-Shard Telemetry & MCP Control Center UI and global `⌘K` Command Palette.

---

## 2. Critical Rules for Code Modifications

> [!IMPORTANT]
> Never violate any of the following six engineering rules when modifying `backend/` or `frontend/`:

1. **Database Transactions**:
   - Any multi-step financial or user-provisioning mutation (`register`, `initiate_payment`, `request_withdrawal`, `handle_admin_withdrawal`, `wallet_transact`, `wallet_withdraw`) **must** use `conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)` (never `DEFERRED` or bare `savepoint()`).
2. **Paywall & PII Redaction**:
   - Never add a field to `PostView`, `ReelView`, `StreamView`, `MessageView`, or `UserPublicProfile` without verifying how it behaves when `!is_unlocked` or `!is_owner_or_admin`.
3. **Agent Scopes & Spend Guardrails**:
   - Any state-mutating or financial endpoint/MCP tool must enforce `auth.require_scope(...)` and, for wallet spends, validate `max_spend_per_tx` and the 24-hour rolling `daily_spend_limit` against `agent_audit_logs`.
4. **Monetary Rounding**:
   - Always wrap floating-point currency math in `round_money(val)` (`(val * 100.0).round() / 100.0`) before persisting to `wallets` or `transactions`.
5. **Date/Time Normalization**:
   - When accepting HTML `datetime-local` inputs (`YYYY-MM-DDTHH:MM`), normalize them via `normalize_db_datetime` (`YYYY-MM-DD HH:MM:SS`) so SQLite `datetime('now')` comparisons remain lexicographically sound.
6. **Next.js 16 App Router Conventions**:
   - Client components using `useSearchParams()` must wrap the hook consumer in a `<Suspense>` boundary so Next.js 16 static page generation (`npm run build`) succeeds cleanly.

---

## 3. Mandatory Verification Loop

Before completing any task or opening a pull request, run all three verification commands and confirm **zero errors and zero warnings**:

```bash
# 1. Run all Rust backend integration, sharding, MCP & security test suites
cd backend && cargo test

# 2. Run strict Clippy static analysis (warnings treated as errors)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Run Next.js 16.4.3 + TypeScript production build (26 routes)
cd frontend && npm run build
```

See **[`CONTRIBUTING.md`](../../CONTRIBUTING.md)** for commit message conventions and PR checklists.


# Onboarding Guide: AI Coding Agents & Open-Source Contributors

This guide is optimized for **autonomous AI coding agents** and **human software engineers** working inside the **HEAV3NS-Publishing** repository. Follow this context map to navigate the codebase, understand strict invariants, and verify changes with zero regressions.

---

## 1. High-Signal Context Map (Read Order)

When starting a coding, debugging, or feature task, inspect files in this exact order:

1. **[`docs/knowledge-base/overview.md`](../knowledge-base/overview.md)** — Domain vocabulary and the 6 non-negotiable business invariants.
2. **[`backend/src/models.rs`](../../backend/src/models.rs)** — Canonical Rust `serde` data structures serialized across the API boundary.
3. **[`backend/src/db.rs`](../../backend/src/db.rs)** — 24-table SQLite schema (`init_db`), demo seed data (`seed_db`), paywall evaluation (`is_post_unlocked`), and redacted view builders (`get_user_public_profile`, `build_post_view`).
4. **[`backend/src/auth.rs`](../../backend/src/auth.rs)** — Dual-channel JWT + scoped `hp_mcp_*` agent token authentication, HMAC-SHA256 token hashing (`hash_agent_token`), and capability scope enforcement (`require_scope`).
5. **[`backend/src/mcp.rs`](../../backend/src/mcp.rs)** — Model Context Protocol (MCP 2024-11-05) JSON-RPC 2.0 server (`POST /api/mcp`), manifest (`GET /api/mcp/manifest`), and 26 autonomous agent tools.
6. **[`backend/src/handlers.rs`](../../backend/src/handlers.rs)** — All 70+ Axum route handlers, spend guardrails, and `BEGIN IMMEDIATE` transaction blocks.
7. **[`backend/src/main.rs`](../../backend/src/main.rs)** — Route registration (`build_app`) and the 9 end-to-end integration test suites (`mod tests`).
8. **[`frontend/src/lib/api.ts`](../../frontend/src/lib/api.ts)** — TypeScript interfaces and `apiFetch` client helpers consumed by the Next.js 16 App Router pages.
9. **[`frontend/src/app/my/agents/page.tsx`](../../frontend/src/app/my/agents/page.tsx)** & **[`frontend/src/components/AppShell.tsx`](../../frontend/src/components/AppShell.tsx)** — Autonomous AI Agents & MCP Control Center UI and global `⌘K` Command Palette.

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
# 1. Run all 9 Rust backend integration & MCP test suites (< 0.15s execution)
cd backend && cargo test

# 2. Run strict Clippy static analysis (warnings treated as errors)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Run Next.js 16.4.3 + TypeScript production build (26 routes)
cd frontend && npm run build
```

See **[`CONTRIBUTING.md`](../../CONTRIBUTING.md)** for commit message conventions and PR checklists.


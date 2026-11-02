# Onboarding Guide: AI Coding Agents & Open-Source Contributors

This guide is optimized for **autonomous AI coding agents** and **human software engineers** working inside the **HEAV3NS-Publishing** repository. Follow this context map to navigate the codebase, understand strict invariants, and verify changes with zero regressions.

---

## 1. High-Signal Context Map (Read Order)

When starting a coding, debugging, or feature task, inspect files in this exact order:

1. **[`docs/knowledge-base/overview.md`](../knowledge-base/overview.md)** — Domain vocabulary and the 6 non-negotiable business invariants.
2. **[`backend/src/models.rs`](../../backend/src/models.rs)** — Canonical Rust `serde` data structures serialized across the API boundary.
3. **[`backend/src/db.rs`](../../backend/src/db.rs)** — 23-table SQLite schema (`init_db`), demo seed data (`seed_db`), paywall evaluation (`is_post_unlocked`), and redacted view builders (`get_user_public_profile`, `build_post_view`).
4. **[`backend/src/handlers.rs`](../../backend/src/handlers.rs)** — All 60+ Axum route handlers and `BEGIN IMMEDIATE` transaction blocks.
5. **[`backend/src/main.rs`](../../backend/src/main.rs)** — Route registration (`build_app`) and the 8 end-to-end integration test suites (`mod tests`).
6. **[`frontend/src/lib/api.ts`](../../frontend/src/lib/api.ts)** — TypeScript interfaces and `apiFetch` client helpers consumed by the Next.js 16 App Router pages.
7. **[`frontend/src/components/AppShell.tsx`](../../frontend/src/components/AppShell.tsx)** — Global navigation shell, sticky header breadcrumbs, mobile bottom bar, and `⌘K` Command Palette.

---

## 2. Critical Rules for Code Modifications

> [!IMPORTANT]
> Never violate any of the following five engineering rules when modifying `backend/` or `frontend/`:

1. **Database Transactions**:
   - Any multi-step financial or user-provisioning mutation (`register`, `initiate_payment`, `request_withdrawal`, `handle_admin_withdrawal`) **must** use `conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)` (never `DEFERRED` or bare `savepoint()`).
2. **Paywall & PII Redaction**:
   - Never add a field to `PostView`, `ReelView`, `StreamView`, `MessageView`, or `UserPublicProfile` without verifying how it behaves when `!is_unlocked` or `!is_owner_or_admin`.
3. **Monetary Rounding**:
   - Always wrap floating-point currency math in `round_money(val)` (`(val * 100.0).round() / 100.0`) before persisting to `wallets` or `transactions`.
4. **Date/Time Normalization**:
   - When accepting HTML `datetime-local` inputs (`YYYY-MM-DDTHH:MM`), normalize them via `normalize_db_datetime` (`YYYY-MM-DD HH:MM:SS`) so SQLite `datetime('now')` comparisons remain lexicographically sound.
5. **Next.js 16 App Router Conventions**:
   - Client components using `useSearchParams()` must wrap the hook consumer in a `<Suspense>` boundary so Next.js 16 static page generation (`npm run build`) succeeds cleanly.

---

## 3. Mandatory Verification Loop

Before completing any task or opening a pull request, run all three verification commands and confirm **zero errors and zero warnings**:

```bash
# 1. Run all 8 Rust backend integration test suites (< 0.15s execution)
cd backend && cargo test

# 2. Run strict Clippy static analysis (warnings treated as errors)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Run Next.js 16.4.3 + TypeScript production build (25 routes)
cd frontend && npm run build
```

See **[`CONTRIBUTING.md`](../../CONTRIBUTING.md)** for commit message conventions and PR checklists.

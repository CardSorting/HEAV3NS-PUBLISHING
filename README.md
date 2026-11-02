# JustFans — Rust (Axum) & Next.js 14 Creator Platform

JustFans is a full-stack premium creator monetization platform converted from Laravel/jQuery to a **Rust (Axum + SQLite)** backend (`backend/`) and a **Next.js 14 (App Router + TypeScript)** frontend (`frontend/`).

---

## Architecture Overview

| Layer | Technology Stack | Directory | Default Port |
| --- | --- | --- | --- |
| **Backend API** | Rust 2021, Axum 0.7, rusqlite (bundled SQLite), Argon2, JWT (`jsonwebtoken`), Tower HTTP | `backend/` | `http://127.0.0.1:8080` |
| **Frontend Web** | Next.js 14 (App Router), React 18, TypeScript, Bootstrap 5 (Light & Dark themes) | `frontend/` | `http://127.0.0.1:3000` |

### Key Features
- **Authentication & Security**: Argon2id password hashing, Bearer/Cookie JWT authentication, role-based admin access (`role_id == 1`), 2FA/privacy/geoblocking settings, and strict public profile field masking (`email`, `wallet_balance`, `birthdate`, `enable_2fa`, and `enable_geoblocking` are hidden from non-owners).
- **Creator Feed & Paywall Protection**:
  - Subscription-only and Pay-Per-View (PPV) locked posts mask post text, poll options, and media attachment URLs until unlocked via subscription or wallet payment.
  - Feed filtering by `all`, `subscribed`, `media`, `photos`, and `videos`.
  - Interactive polls, post/comment reactions, comment creation/deletion, bookmarks, and post/user reporting.
- **Short-Form Video Reels & 24h Stories**:
  - Vertical video Reels with `<video>` playback, likes, slide-over comments drawer, bookmarking, and creator deletion.
  - Ephemeral 24h Creator Stories tray and lightbox viewer.
- **Live Streams**: Creator streaming directory, HLS stream player, real-time stream chat, and PPV stream ticket unlocks.
- **Direct Messenger**: 1-on-1 creator messaging, paid PPV message unlocks, tips, user blocking/unblocking, and message deletion.
- **Wallet, Subscriptions & Payouts**: Instant wallet deposits (Stripe/PayPal/Crypto simulation), 1/3/6/12-month creator subscriptions, tips, printable tax invoices, and creator withdrawal requests.
- **Custom Lists & Moderation**:
  - Custom user lists (`Following`, `Blocked`, plus custom lists) with member management.
  - Creator Identity Verification document upload and Admin Moderation Dashboard (approving/rejecting withdrawals and ID verifications, resolving/dismissing reports).

---

## Quick Start

### 1. Start Both Services Together
From the repository root:
```bash
./start-dev.sh
```
`start-dev.sh` launches the Rust backend on `http://127.0.0.1:8080` and the Next.js frontend on `http://127.0.0.1:3000`, and traps `SIGINT`/`SIGTERM` to cleanly stop both processes on exit.

### 2. Run Backend & Frontend Separately

**Rust Backend (`backend/`)**:
```bash
cd backend
PORT=8080 DATABASE_URL=justfans.db cargo run
```
On startup, the backend automatically initializes the SQLite schema and seeds demo creators, posts, polls, stories, reels, streams, messages, and transactions.

**Next.js Frontend (`frontend/`)**:
```bash
cd frontend
npm install
BACKEND_URL="http://127.0.0.1:8080" npm run dev
```
The Next.js configuration (`frontend/next.config.mjs`) proxies `/api/*` and `/uploads/*` requests to `BACKEND_URL`.

---

## Demo Accounts

All seeded demo accounts use the password: **`password123`**

| Role | Name | Username | Email |
| --- | --- | --- | --- |
| **Admin & Verified Creator** | Aria Vance | `@ariavance` | `aria@justfans.local` |
| **Verified Creator** | Marcus Sterling | `@marcusfit` | `marcus@justfans.local` |
| **Verified Creator (Free/Open Profile)** | Elena Rostova | `@elenamusic` | `elena@justfans.local` |
| **Fan / Subscriber** | Alex Rivera | `@alexrivera` | `alex@justfans.local` |

---

## Testing & Verification

### Backend Tests & Linting
Run the integration test suite and Clippy lint checks:
```bash
cd backend
cargo clippy --all-targets -- -D warnings
cargo test
```

### Frontend Production Build
Verify TypeScript types and Next.js static/dynamic route compilation:
```bash
cd frontend
npm run build
```

---

## Documentation & Engineering Knowledge Base

Comprehensive architecture documentation, domain knowledge base, API specifications, Architecture Decision Records (ADRs), Operational Playbooks, and Engineering Post-Mortems are located in [`docs/`](./docs/README.md):

- **Documentation Hub**: [`docs/README.md`](./docs/README.md)
- **Knowledge Base & Domain Reference**: [`docs/knowledge-base/overview.md`](./docs/knowledge-base/overview.md)
- **Architecture & Schema**:
  - [System Architecture & Data Flows](./docs/architecture/system-architecture.md)
  - [Database Schema & ER Diagram (21 Tables)](./docs/architecture/database-schema.md)
- **REST API Reference**: [Complete Endpoint Reference (`45+` Routes)](./docs/api/api-reference.md)
- **Architecture Decision Records (ADRs)**:
  - [ADR-001: Migration from Legacy PHP/Laravel to Rust (Axum) & Next.js 14](./docs/adr/001-rust-axum-and-nextjs-migration.md)
  - [ADR-002: Embedded SQLite with WAL Mode and Mutex-Synchronized State](./docs/adr/002-sqlite-wal-and-state-management.md)
  - [ADR-003: Dual-Channel Stateless JWT Authentication & Optional Extractor Pattern](./docs/adr/003-dual-channel-jwt-authentication.md)
  - [ADR-004: Server-Side Paywall Enforcement & Privacy Redaction at the Serialization Boundary](./docs/adr/004-server-side-paywall-and-privacy-masking.md)
  - [ADR-005: Unified Payment Ledger, Authoritative Pricing & 85/15 Creator Revenue Split](./docs/adr/005-unified-payment-ledger-and-creator-split.md)
- **Operational Playbooks**:
  - [Playbook 001: Local Development, Startup & Full-Stack Verification](./docs/playbooks/001-local-development-and-startup.md)
  - [Playbook 002: Database Backup, Restore, Inspection & Clean Re-Seeding](./docs/playbooks/002-database-backup-restore-and-seeding.md)
  - [Playbook 003: Payment, Wallet & Creator Payout Reconciliation](./docs/playbooks/003-payment-and-payout-reconciliation.md)
  - [Playbook 004: Content Moderation & Creator Identity Verification](./docs/playbooks/004-moderation-and-creator-verification.md)
  - [Playbook 005: Security Incident Response & Paywall Audit](./docs/playbooks/005-security-incident-and-paywall-audit.md)
- **Engineering Post-Mortems**:
  - [PM-001: Subscription Price Spoofing & Duplicate PPV Unlock Charges](./docs/post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md)
  - [PM-002: Locked Post Cleartext Metadata, VIP Stream HLS & Public Profile PII Exposure](./docs/post-mortems/002-locked-post-and-stream-paywall-data-leak.md)
  - [PM-003: Cross-Poll Vote Corruption & Vault Attachment Row Duplication](./docs/post-mortems/003-poll-vote-corruption-and-attachment-duplication.md)
  - [PM-004: Frontend-Backend Contract Mismatches & Pre-Filter Feed Pagination](./docs/post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)


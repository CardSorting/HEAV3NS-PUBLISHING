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

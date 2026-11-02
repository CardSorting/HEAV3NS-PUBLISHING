# Knowledge Base & Domain Reference

This document captures the institutional knowledge, domain vocabulary, business invariants, and codebase map for **HEAV3NS-Publishing**.

---

## 1. Domain Vocabulary & Core Entities

| Concept | Definition & Implementation Details |
| --- | --- |
| **Creator / Member (`users`)** | Every account is stored in `users`. Users with `role_id = 1` are Platform Administrators; users with `role_id = 2` are standard Creators/Fans. Any user can publish content, configure subscription rates (`paid_profile`, `open_profile`, `profile_access_price`), or subscribe to other creators. |
| **Verified Creator (`identity_verified_at`)** | A user whose government ID submission (`user_verifies` table) has been approved by an Admin (`POST /api/admin/verifications/:id`). Displays a ✅ badge across the UI. |
| **Paid vs. Open Profile** | - **Paid Profile (`paid_profile = 1, open_profile = 0`)**: All standard posts require an active subscription to unlock.<br>- **Open Profile (`open_profile = 1` or `paid_profile = 0`)**: Standard posts (`price == 0`) are visible to everyone for free, while Pay-Per-View (`price > 0`) posts still require an individual unlock purchase. |
| **Pay-Per-View (PPV)** | Individual content items (`posts`, `user_messages`, or `streams`) with `price > 0.0`. Unlocking creates an `'approved'` record in `transactions` with `tx_type` set to `'post-unlock'`, `'message-unlock'`, or `'stream-unlock'`. |
| **Wallet (`wallets`)** | Each user has a 1:1 row in `wallets` tracking their USD credit balance (`total`). New accounts receive a `$100.00` welcome balance upon registration. |
| **Creator Revenue Split (85 / 15)** | Whenever a fan pays for a subscription, PPV unlock, or tip, **85%** of `amount` (`(amount * 0.85 * 100.0).round() / 100.0`) is credited immediately to the recipient creator's wallet, and **15%** is retained as the platform fee. |
| **Media Vault (`attachments`)** | Uploaded files (`/api/attachment/upload`) are stored on disk under `backend/uploads/<uuid>.<ext>` and indexed in `attachments` with `is_vault = 1`. Creators can attach existing Vault media to new or edited posts without re-uploading. |
| **Reels (`reels`, `reel_comments`)** | Short-form vertical videos with `<video>` playback, view counts, likes (`reactions.reel_id`), comments (`reel_comments`), and bookmarks (`user_bookmarks.reel_id`). |
| **Stories (`stories`)** | Ephemeral 24-hour visual updates (`expires_at = datetime('now', '+24 hours')`). Stories marked with `is_highlight = 1` remain visible permanently past expiration. |
| **Live Streams (`streams`, `stream_messages`)** | Creator broadcasts with an RTMP ingest key (`rtmp_key`, visible only to the stream owner), HLS playback URL (`hls_link`, hidden when locked), optional subscription requirement (`requires_subscription`), optional ticket price (`price`), and real-time chat (`stream_messages`). |

---

## 2. Core Business Invariants (Must Never Be Violated)

1. **Server-Authoritative Pricing**:
   - The backend **never** trusts client-supplied `amount` values for `subscription`, `post-unlock`, `message-unlock`, or `stream-unlock` payments. Prices are always queried directly from `users`, `posts`, `user_messages`, or `streams` in SQLite.
   - Only `deposit` and `tip` accept a client-supplied `amount`, which must be finite and strictly `> 0.0`.
2. **Idempotent / Non-Duplicate Unlocks**:
   - Before charging for `post-unlock`, `message-unlock`, or `stream-unlock`, the server queries `transactions` for an existing `'approved'` unlock by `sender_user_id`. Duplicate unlock requests are rejected with `422 Unprocessable Entity`.
3. **No Self-Subscription**:
   - Users cannot subscribe to their own profile (`rec_id == auth.user_id` returns `422 Unprocessable Entity`).
4. **Paywall Redaction at the Serialization Boundary**:
   - When `PostView.is_unlocked == false`:
     - `post.text` is replaced with `"🔒 Exclusive locked content — Subscribe or unlock to view this post."`
     - `post.poll` is set to `None` (`null` in JSON).
     - Every attachment's `file_url` is replaced with `"/img/post-locked.svg"`.
   - When `MessageView.is_unlocked == false`:
     - `message.message` is replaced with `"🔒 Locked Pay-Per-View Message — Unlock to view"`.
   - When `StreamView.is_unlocked == false`:
     - `stream.hls_link` is replaced with `""`.
   - Stream `rtmp_server` and `rtmp_key` are only populated when `viewer_id == Some(stream.user_id)`.
5. **PII & Financial Field Redaction on Public Profiles**:
   - `get_user_public_profile(conn, target_user_id, viewer_id)` checks whether `viewer_id` is the profile owner (`Some(uid) == viewer_id`) or a Platform Admin (`role_id == 1`).
   - If neither is true, `email`, `birthdate`, `wallet_balance` (`0.0`), `enable_2fa` (`false`), and `enable_geoblocking` (`false`) are masked before returning `UserPublicProfile`.
6. **Withdrawal Minimum & Refund on Rejection**:
   - Creator withdrawal requests (`/api/withdrawals/request`) require a minimum of `$20.00` and sufficient wallet balance.
   - Balance is deducted immediately when the withdrawal is requested (`status = 'requested'`). If an Admin rejects the withdrawal (`/api/admin/withdrawals/:id` with `action: "reject"`), the full amount is refunded to the creator's `wallets.total`.

---

## 3. Codebase Map

```text
HEAV3NS-PUBLISHING/
├── backend/                        # Rust (Axum 0.7 + SQLite) API Server
│   ├── Cargo.toml                  # Rust crate dependencies
│   └── src/
│       ├── main.rs                 # App router, CORS, static file mounts, 7 integration tests
│       ├── auth.rs                 # JWT encode/decode, AuthUser & OptionalAuthUser extractors, AppError
│       ├── db.rs                   # 21-table SQLite schema, seed_db(), get_user_public_profile(), build_post_view()
│       ├── handlers.rs             # All 45+ HTTP route handlers & business logic
│       └── models.rs               # Serde request/response DTO structs
├── frontend/                       # Next.js 14 (App Router + TypeScript) Web Client
│   ├── next.config.mjs             # Proxies /api/* and /uploads/* to Rust backend
│   ├── package.json                # Next.js 14, React 18, TypeScript dependencies
│   ├── public/                     # Static assets (Bootstrap 5 theme CSS, SVG illustrations, fonts)
│   └── src/
│       ├── lib/api.ts              # Typed apiFetch() wrapper, JWT storage helpers, TypeScript interfaces
│       ├── context/AuthContext.tsx # Global user session, wallet balance, unread counts, dark mode state
│       ├── components/
│       │   ├── AppShell.tsx        # Left navigation sidebar, account mini-card, theme toggle
│       │   ├── PostCard.tsx        # Post rendering, paywall CTAs, polls, comments, tips, report modal
│       │   └── PostComposerForm.tsx# Post create/edit, file upload, Vault picker, AI caption assistant
│       └── app/                    # 21 Next.js App Router pages
│           ├── page.tsx            # Public landing page
│           ├── login/page.tsx      # Login + 1-click demo account switcher
│           ├── register/page.tsx   # Account registration ($100 welcome credit)
│           ├── feed/page.tsx       # Main feed, 24h stories tray & lightbox, category filters, suggestions
│           ├── search/page.tsx     # Explore & search (Top, Latest, People, Photos, Videos, Live)
│           ├── [username]/page.tsx # Creator profile, subscription bundles, custom list modal, report modal
│           ├── posts/              # Create (/posts/create), Edit (/posts/edit/[post_id]), Single Post
│           ├── reels/              # Vertical video Reels feed (/reels) & creation (/reels/create)
│           ├── stories/create/     # 24h Story & Highlight creator
│           ├── stream/             # Live stream room & chat (/stream/[streamID]/[slug])
│           ├── invoices/[id]/      # Printable transaction invoice view
│           ├── admin/page.tsx      # Admin dashboard (metrics, withdrawals, ID verifications, reports)
│           ├── contact/page.tsx    # Support contact form
│           ├── pages/[slug]/       # Public CMS pages (terms-and-conditions, privacy, help)
│           └── my/                 # Authenticated user workspace
│               ├── messenger/      # Direct messaging & PPV message unlocks
│               ├── notifications/  # Activity notifications by type
│               ├── bookmarks/      # Saved posts & saved reels
│               ├── lists/          # Custom user lists & member management
│               ├── vault/          # Reusable media vault manager
│               ├── streams/        # Creator broadcast studio
│               └── settings/       # 9-tab settings (Profile, Rates, Wallet, Subscriptions, Payments, Verify, Account, Privacy, Referrals)
├── docs/                           # Engineering Documentation, Knowledge Base, ADRs, Playbooks, Post-Mortems
└── start-dev.sh                    # Unified development launcher with SIGINT/SIGTERM cleanup trap
```

---

## 4. Seeded Demo Accounts & Test Data

When `backend/src/db.rs` initializes an empty database, `seed_db()` populates 4 accounts (all with password `password123`):

| User ID | Name | Username | Email | Role | Profile Type | Initial Wallet |
| --- | --- | --- | --- | --- | --- | --- |
| `1` | Aria Vance | `@ariavance` | `aria@justfans.local` | Admin (`1`) | Paid (`$14.99/mo`) | `$1,450.50` |
| `2` | Marcus Sterling | `@marcusfit` | `marcus@justfans.local` | User (`2`) | Paid (`$9.99/mo`) | `$820.00` |
| `3` | Elena Rostova | `@elenamusic` | `elena@justfans.local` | User (`2`) | Free / Open (`$0.00`) | `$540.75` |
| `4` | Alex Rivera | `@alexrivera` | `alex@justfans.local` | User (`2`) | Free / Open (`$0.00`) | `$250.00` |

### Seeded Relationships & Content
- **Subscriptions**: Alex (`#4`) is subscribed to Aria (`#1`); Aria (`#1`) is subscribed to Marcus (`#2`).
- **Posts**:
  - Post `#1` (Aria): Free to Aria's subscribers, pinned, includes an interactive 3-option Poll (`poll_id = 1`).
  - Post `#2` (Aria): `$19.99` PPV post (locked for Alex until unlocked via `/api/payments/initiate`).
  - Post `#3` (Marcus): Standard subscriber post (`$0.00` PPV, requires subscription to Marcus).
  - Post `#4` (Elena): Open profile post (unlocked for all viewers, including guests).
- **Live Streams**:
  - Stream `#1` (Aria): Public free live stream (`price = 0.0, requires_subscription = 0`).
  - Stream `#2` (Marcus): VIP live stream (`price = 5.0, requires_subscription = 1`).
- **Invoices / Transactions**:
  - Invoice `#1001`: `$14.99` subscription (`stripe`)
  - Invoice `#1002`: `$25.00` tip (`credit`)
  - Invoice `#1003`: `$100.00` wallet deposit (`stripe`)


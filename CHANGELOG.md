# Changelog

All notable changes to **HEAV3NS-Publishing** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.0] - 2026-11-02

### Added
- **Next.js 16.4.3 & React 19.3.1 Upgrade**: Upgraded `frontend/` to Next.js 16 (Turbopack) and React 19 with full TypeScript 5.6 compatibility across all 25 App Router routes.
- **Global Command Palette (`⌘K` / `Ctrl+K`)**: Added an interactive keyboard-driven Command Palette in [`AppShell.tsx`](./frontend/src/components/AppShell.tsx) for instant route jumping, theme toggling, quick-creation actions, and direct Explore search (`/search?query=...`).
- **Promotional Creator Offers (`creator_offers`)**: Added creator discount campaigns with strikethrough original pricing (`old_profile_access_price*`) and promotional expiration dates.
- **Scheduled & Expiring Posts (`release_date` & `expire_date`)**: Added post scheduling and auto-expiration controls in [`PostComposerForm.tsx`](./frontend/src/components/PostComposerForm.tsx) and server-side visibility filtering in [`backend/src/db.rs`](./backend/src/db.rs).
- **Pay-Per-View (PPV) Reels & Media Vault Video Picker**: Added `reel-unlock` payment support, locked reel paywall overlays, and existing Media Vault video selection when creating Reels.
- **Story Highlights & Story View Analytics (`story_views`)**: Added permanent creator profile Story Highlights (`POST /api/stories/:id/highlight`) and viewer tracking (`POST /api/stories/:id/view`).
- **Referral System & Signup Bonuses (`referrals`)**: Added referral code generation, signup bonus distribution (`$5.00` referrer credit / `$2.50` referee welcome bonus), and referral history tracking in `/my/settings?tab=referrals`.
- **Mass Broadcast Messaging & Media Vault Archiving**: Added multi-recipient/fan-list broadcast messaging in `/my/messenger` and vault file archiving (`POST /api/vault/:id/archive`).
- **Engineering Knowledge Base & Open-Source Governance**: Added [`docs/`](./docs/README.md) (Knowledge Base, System Architecture, 23-Table Database Schema, REST API Reference, 5 ADRs, 5 Operational Playbooks, 4 Post-Mortems) and open-source governance files (`LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `SUPPORT.md`, and `.github/` templates & CI workflow).

### Changed
- **Repository Rebranding**: Rebranded project and package manifests from `JustFans` to **HEAV3NS-Publishing** (`heav3ns-publishing-backend` and `heav3ns-publishing-frontend`).
- **Immediate SQLite Transactions**: Upgraded [`register`](./backend/src/handlers.rs), [`initiate_payment`](./backend/src/handlers.rs), [`request_withdrawal`](./backend/src/handlers.rs), and [`handle_admin_withdrawal`](./backend/src/handlers.rs) to execute inside `rusqlite::TransactionBehavior::Immediate` (`BEGIN IMMEDIATE`) transactions.

### Fixed
- **Payment Provider Normalization**: Normalized `"wallet"` and `"credit"` payment providers in [`initiate_payment`](./backend/src/handlers.rs) and rejected unknown providers with `400 Bad Request`.
- **Locked Post Comment Redaction**: Masked `post.comments` (`[]`) on locked PPV and subscription-only posts in [`build_post_view`](./backend/src/db.rs).
- **Public Profile Billing PII Masking**: Masked `first_name`, `last_name`, `billing_address`, `city`, `country`, `state`, and `postcode` on [`UserPublicProfile`](./backend/src/models.rs) for non-owners.
- **Poll Vote Preservation on Post Edits**: Preserved existing `polls` and `poll_user_answers` votes when editing a post caption/price if poll options remain unchanged.
- **Bidirectional User Block Enforcement**: Enforced `is_blocked_between` across reactions, comments, live streams, profiles, and direct messaging.

---

## [1.0.0] - 2026-11-01

### Added
- **Full-Stack Rust & Next.js Conversion**: Migrated the legacy PHP/Laravel + jQuery monolith into a decoupled **Rust (Axum 0.7 + SQLite WAL)** backend (`backend/`) and **Next.js (App Router + TypeScript)** frontend (`frontend/`).
- **Core Creator Monetization Suite**:
  - 1/3/6/12-month creator subscriptions, PPV post unlocks, PPV message unlocks, PPV live stream unlocks, and creator tips with an 85% creator / 15% platform revenue split.
  - Creator wallet ledger, printable tax invoices (`/invoices/[id]`), and withdrawal escrow/refund workflows.
  - Short-form video Reels (`/reels`), 24-hour ephemeral Stories, HLS Live Streaming (`/my/streams`), 1-on-1 Direct Messenger (`/my/messenger`), Custom Lists (`/my/lists`), Media Vault (`/my/vault`), and Admin Moderation Dashboard (`/admin`).
- **Security & Regression Fixes (PM-001 through PM-004)**:
  - Fixed subscription price spoofing and duplicate PPV charges ([PM-001](./docs/post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md)).
  - Fixed locked post cleartext metadata, VIP stream HLS URLs, and public profile PII exposure ([PM-002](./docs/post-mortems/002-locked-post-and-stream-paywall-data-leak.md)).
  - Fixed cross-poll vote corruption and vault attachment row duplication ([PM-003](./docs/post-mortems/003-poll-vote-corruption-and-attachment-duplication.md)).
  - Fixed frontend-backend contract mismatches and category-filtered feed pagination ([PM-004](./docs/post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)).


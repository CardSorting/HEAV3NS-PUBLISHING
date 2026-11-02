# Onboarding Guide: Security Researchers & Systems Auditors

Welcome to the **HEAV3NS-Publishing** security and systems research portal. This guide provides researchers, cryptographers, and systems auditors with direct pointers to our threat model, formal invariants, verification harnesses, and engineering post-mortems.

---

## 1. Foundational Research Papers & Architecture Specs

Start with our three primary technical documents:
1. **[Technical Whitepaper (`PAPER-003`)](../papers/003-technical-whitepaper.md)** — Formal specification of the entitlement evaluation equations, serialization-boundary redaction projections, and `BEGIN IMMEDIATE` ACID financial state machine.
2. **[Philosophy Paper (`PAPER-001`)](../papers/001-philosophy-paper.md)** — Why security is enforced as a serialization-boundary invariant rather than a presentation concern.
3. **[System Architecture & Data Flow](../architecture/system-architecture.md)** & **[Database Schema (23 Tables)](../architecture/database-schema.md)**.

---

## 2. Six Core Security & Financial Invariants to Audit

Every audit of **HEAV3NS-Publishing** should verify these six invariants:

| Invariant ID | Specification | Primary Implementation Location |
| :--- | :--- | :--- |
| **INV-1: Server-Side Post & Media Redaction** | When `is_post_unlocked == false`, the JSON payload must return `text: ""`, `poll: null`, `comments: []`, and `attachments[].path: "/img/locked-media.svg"`. Locked attachments must also be excluded from `/api/profile/:username` `recent_media`. | [`backend/src/db.rs`](../../backend/src/db.rs) (`is_post_unlocked`, `build_post_view`) |
| **INV-2: Server-Side Reel, Message & Stream Redaction** | Locked PPV reels must return `video_url: ""`; locked PPV messages must redact `message` and `attachments`; locked VIP streams must return `hls_link: null`, and `rtmp_key` must only be returned to the stream owner. | [`backend/src/handlers.rs`](../../backend/src/handlers.rs) (`get_reels`, `get_messages`, `get_streams`) |
| **INV-3: Public Profile PII & Billing Redaction** | `GET /api/profile/:username` and embedded `UserPublicProfile` objects must return `None` for `email`, `birthdate`, `first_name`, `last_name`, `billing_address`, `city`, `country`, `state`, `postcode`, and `0.0` for `wallet_balance` unless `viewer_id == user.id` or viewer is Admin. | [`backend/src/db.rs`](../../backend/src/db.rs) (`get_user_public_profile`) |
| **INV-4: Authoritative Pricing & Duplicate Unlock Guard** | `POST /api/payments/initiate` must ignore client-supplied `amount` for subscriptions and PPV unlocks (querying SQLite instead) and reject duplicate `post-unlock`, `reel-unlock`, `message-unlock`, and `stream-unlock` purchases. | [`backend/src/handlers.rs`](../../backend/src/handlers.rs) (`initiate_payment`) |
| **INV-5: Atomic `BEGIN IMMEDIATE` Ledger & 85/15 Split** | All multi-step wallet mutations execute inside `rusqlite::TransactionBehavior::Immediate`, crediting `round_money(amount * 0.85)` to the creator and deducting `100%` from the buyer when `provider == "credit" | "wallet"`. | [`backend/src/handlers.rs`](../../backend/src/handlers.rs) (`initiate_payment`, `request_withdrawal`, `handle_admin_withdrawal`) |
| **INV-6: Bidirectional Block Enforcement** | If user $A$ blocks user $B$ (`is_blocked_between(A, B) == true`), neither user can view the other's profile, posts, streams, reactions, comments, or send direct messages. | [`backend/src/db.rs`](../../backend/src/db.rs) (`is_blocked_between`) |

---

## 3. Running the Automated Security & Adversarial Test Harness

You can execute all 8 in-memory integration test suites—including dedicated financial spoofing, paywall redaction, and edge-case tests—in under 1 second:

```bash
cd backend
cargo test -- --nocapture
```

To run specific security suites:
```bash
# Test subscription price spoofing, duplicate PPV unlocks, and withdrawal refunds
cargo test test_security_and_regression_fixes -- --nocapture

# Test PII masking, locked post/stream redaction, and moderation permissions
cargo test test_privacy_paywall_reels_and_moderation -- --nocapture

# Test promotional offers, scheduled posts, wallet provider normalization, and blocking
cargo test test_deep_hardening_and_parity_features -- --nocapture
```

---

## 4. Historical Vulnerability Post-Mortems

Review how previous vulnerabilities were discovered, analyzed, patched, and regression-tested:
- **[PM-001: Subscription Price Spoofing & Duplicate PPV Unlock Charges](../post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md)**
- **[PM-002: Locked Post Cleartext Metadata, VIP Stream HLS & Public Profile PII Exposure](../post-mortems/002-locked-post-and-stream-paywall-data-leak.md)**
- **[PM-003: Cross-Poll Vote Corruption & Vault Attachment Row Duplication](../post-mortems/003-poll-vote-corruption-and-attachment-duplication.md)**
- **[PM-004: Frontend-Backend Contract Mismatches & Pre-Filter Feed Pagination](../post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)**

---

## 5. Coordinated Vulnerability Disclosure

If you discover a security vulnerability or invariant bypass, please follow the private reporting instructions in **[`SECURITY.md`](../../SECURITY.md)**.

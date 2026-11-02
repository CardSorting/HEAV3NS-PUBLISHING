# ADR-004: Server-Side Paywall Enforcement and Privacy Redaction at the Serialization Boundary

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

In creator monetization platforms, a common critical vulnerability occurs when the backend returns the full database record (including post text, media URLs, poll details, or live stream HLS `.m3u8` URLs) and relies on the frontend UI to render a "Locked / Subscribe to View" overlay. Any user inspecting browser network traffic (DevTools) can trivially bypass a client-side paywall.

Similarly, returning a unified `User` model on public profile endpoints (`GET /api/profile/:username`) risks leaking private PII (`email`, `birthdate`) and financial data (`wallet_balance`) to anonymous scrapers.

## Decision

We established a strict architectural invariant: **All paywall masking and PII redaction must occur on the server inside the view-builder functions in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs) and the DDD bounded-context handlers in [`backend/src/handlers/`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/) before JSON serialization.**

Specifically:
1. **Post Paywall Redaction (`build_post_view` in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs))**:
   - `is_post_unlocked(conn, viewer_id, post_user_id, post_id, price)` checks whether the viewer is the creator, an active subscriber (for `$0.00` posts), or has an approved `"post-unlock"` transaction (for PPV posts where `price > 0.0`).
   - When `is_unlocked == false`:
     - `PostView.text` is overwritten with `"🔒 Exclusive locked content — Subscribe or unlock to view this post."` (or `""`).
     - `PostView.poll` is set to `None` and `PostView.comments` is set to `[]`.
     - Every `AttachmentView.file_url` is overwritten with `"/img/post-locked.svg"` (or `"/img/locked-media.svg"`).
2. **Profile Media Tab Filtering (`get_profile` in [`backend/src/handlers/discovery/profiles.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/discovery/profiles.rs))**:
   - The `recent_media` array on `/api/profile/:username` filters each candidate post through `is_post_unlocked` and excludes any attachment belonging to a locked post.
3. **Live Stream & Reel Redaction (`compute_stream_unlocked` in [`backend/src/handlers/media/streams.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/streams.rs) & `compute_reel_unlocked` in [`backend/src/handlers/media/reels.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/reels.rs))**:
   - For VIP streams (`price > 0.0` or `requires_subscription == true`), unauthorized viewers receive `is_unlocked: false` and `hls_link: ""`.
   - For PPV reels (`price > 0.0`), unauthorized viewers receive `is_unlocked: false` and `video_url: ""`.
4. **Public User Profile Sanitization (`get_user_public_profile` in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs))**:
   - Unless `viewer_id == Some(user.id)` or the viewer is a Platform Admin, the returned `UserPublicProfile` struct masks `email`, `birthdate`, billing address fields, and sets `wallet_balance: 0.0`.

## Consequences

### Positive
- **Cryptographic/Network-Level Paywall Security**: Inspecting the raw HTTP response payload for a locked post or stream reveals zero premium content, text, or media URLs.
- **GDPR / Privacy Compliance**: Public profile endpoints cannot be harvested for creator email addresses, birthdates, or wallet balances.

### Negative / Trade-offs
- **Slightly Higher Query Cost per Feed Page**: Computing `is_unlocked` checks subscription and transaction tables per post, though with SQLite running in-process in WAL mode, these point lookups take microseconds.


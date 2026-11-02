# ADR-004: Server-Side Paywall Enforcement and Privacy Redaction at the Serialization Boundary

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

In creator monetization platforms, a common critical vulnerability occurs when the backend returns the full database record (including post text, media URLs, poll details, or live stream HLS `.m3u8` URLs) and relies on the frontend UI to render a "Locked / Subscribe to View" overlay. Any user inspecting browser network traffic (DevTools) can trivially bypass a client-side paywall.

Similarly, returning a unified `User` model on public profile endpoints (`GET /api/profile/:username`) risks leaking private PII (`email`, `birthdate`) and financial data (`wallet_balance`) to anonymous scrapers.

## Decision

We established a strict architectural invariant: **All paywall masking and PII redaction must occur on the server inside the view-builder functions in [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs) before JSON serialization.**

Specifically:
1. **Post Paywall Redaction (`build_post_view`)**:
   - `compute_post_unlocked(conn, viewer_id, post_user_id, post_id, price, is_pinned)` checks whether the viewer is the creator, an active subscriber (for `$0.00` posts), or has an approved `"post-unlock"` transaction (for PPV posts where `price > 0.0`).
   - When `is_unlocked == false`:
     - `PostView.text` is overwritten with `""` (empty string).
     - `PostView.poll` is set to `None`.
     - Every `AttachmentView.file_url` is overwritten with `"/img/locked-media.svg"` and `has_blurred_preview` is set to `true`.
2. **Profile Media Tab Filtering (`get_profile`)**:
   - The `recent_media` array on `/api/profile/:username` filters each candidate post through `compute_post_unlocked` and excludes any attachment belonging to a locked post.
3. **Live Stream HLS Redaction (`compute_stream_unlocked`)**:
   - For VIP streams (`price > 0.0` or `requires_subscription == true`), unauthorized viewers receive `is_unlocked: false` and `hls_url: Some("/img/locked-stream.m3u8")`.
4. **Public User Profile Sanitization (`get_user_public_profile`)**:
   - Unless `viewer_id == user.id`, the returned `UserPublic` struct sets `email: None`, `birthdate: None`, and `wallet_balance: 0.0`.

## Consequences

### Positive
- **Cryptographic/Network-Level Paywall Security**: Inspecting the raw HTTP response payload for a locked post or stream reveals zero premium content, text, or media URLs.
- **GDPR / Privacy Compliance**: Public profile endpoints cannot be harvested for creator email addresses, birthdates, or wallet balances.

### Negative / Trade-offs
- **Slightly Higher Query Cost per Feed Page**: Computing `is_unlocked` checks subscription and transaction tables per post, though with SQLite running in-process in WAL mode, these point lookups take microseconds.


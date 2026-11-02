# Post-Mortem 002: Locked Post Cleartext Metadata, VIP Stream HLS & Public Profile PII Exposure

- **Incident ID**: PM-002
- **Severity**: High (SEV-2 — Paywall Bypass & Privacy Data Leak)
- **Status**: Resolved & Regression-Tested
- **Date Discovered**: 2026-11-01 (During Phase 4 Security & Privacy Audit)
- **Affected Component**: [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs) (`build_post_view`, `get_profile`, `get_streams`, `get_user_public_profile`)

---

## 1. Executive Summary

During a comprehensive audit of API responses returned to unauthenticated and non-subscribed users, four data-exposure vulnerabilities were uncovered across the serialization boundary:
1. **Locked Post Text & Poll Leak**: While `build_post_view` replaced `attachments[].file_url` with `"/img/locked-media.svg"` when `is_unlocked == false`, it still serialized `post.text` and `post.poll` in cleartext. Premium text-only posts or captions on PPV posts were readable via browser DevTools.
2. **Profile Media Tab Leak**: `get_profile` (`GET /api/profile/:username`) populated `recent_media` by querying `attachments` joined with `posts WHERE user_id = ?1` without filtering each post through `compute_post_unlocked`. Raw media URLs from locked posts appeared in the profile's `recent_media` array.
3. **VIP Stream HLS URL Leak**: `get_streams` (`GET /api/streams`) hardcoded `is_unlocked: true` on `StreamView` and always returned the cleartext `hls_url`, even when `price > 0.0` or `requires_subscription == true`.
4. **Public Profile PII Exposure**: `get_user_public_profile` returned `email`, `birthdate`, and `wallet_balance` in `UserPublic` for any queried user, not just when `viewer_id == user.id`.

---

## 2. Root Cause Analysis

1. **Incomplete Redaction in `build_post_view`**: The initial implementation focused on masking media attachment URLs (`file_url`) and assumed the frontend blur overlay would hide post captions and polls.
2. **Secondary Query Bypassing View Builder**: `get_profile` constructed `recent_media` using a standalone SQL query against `attachments` rather than reusing the `is_unlocked` post filter already computed for the profile's `posts` feed.
3. **Missing `compute_stream_unlocked` Helper**: Live streams had a `stream-unlock` payment type in `initiate_payment`, but `get_streams` lacked the corresponding entitlement check helper (`compute_stream_unlocked`).
4. **Shared Struct Reuse (`UserPublic`)**: `get_user_public_profile` was called by both `get_me` (where the authenticated user needs their own `email` and `wallet_balance`) and public profile views (`get_profile`, post author embeds), without a `viewer_id` parameter to distinguish self-lookup from third-party lookup.

---

## 3. Resolution & Code Changes

1. **Full Post Redaction (`build_post_view`)**:
   - When `!is_unlocked`, `PostView` now sets `text: String::new()` and `poll: None` in addition to replacing attachment URLs with `"/img/locked-media.svg"`.
2. **Entitlement-Filtered `recent_media` (`get_profile`)**:
   - `get_profile` now evaluates `compute_post_unlocked` for every post before including its attachments in `recent_media`.
3. **Stream Paywall Helper (`compute_stream_unlocked`)**:
   - Added `compute_stream_unlocked(conn, viewer_id, creator_id, stream_id, price, requires_subscription)` and updated `get_streams`, `create_stream`, and `update_stream` to mask `hls_url` to `Some("/img/locked-stream.m3u8")` when `is_unlocked == false`.
4. **Viewer-Aware `get_user_public_profile(conn, user_id, viewer_id)`**:
   - Added `viewer_id: Option<i64>` to `get_user_public_profile`. Whenever `viewer_id != Some(user_id)`, `email` and `birthdate` are set to `None` and `wallet_balance` is set to `0.0`.

---

## 4. Prevention & Regression Tests

Added [`test_privacy_paywall_reels_and_moderation`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) to verify:
- Public profile responses for other users have `email == null`, `birthdate == null`, and `wallet_balance == 0.0`.
- Locked PPV posts return `text == ""`, `poll == null`, and `attachments[].file_url == "/img/locked-media.svg"`.
- Locked post attachments do not appear in `/api/profile/:username` `recent_media`.
- Paid/VIP streams return `is_unlocked == false` and `hls_url == "/img/locked-stream.m3u8"` until unlocked.


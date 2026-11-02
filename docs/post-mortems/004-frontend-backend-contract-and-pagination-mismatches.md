# Post-Mortem 004: Frontend-Backend Contract Mismatches & Pre-Filter Feed Pagination

- **Incident ID**: PM-004
- **Severity**: Medium (SEV-3 — Functional Regressions)
- **Status**: Resolved & Regression-Tested
- **Date Discovered**: 2026-11-01 (During Full-Stack Integration Audit)
- **Affected Components**:
  - [`frontend/src/app/my/settings/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/my/settings/page.tsx)
  - [`frontend/src/app/admin/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/admin/page.tsx)
  - [`frontend/src/app/my/bookmarks/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/my/bookmarks/page.tsx)
  - [`backend/src/handlers/discovery/feed.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/discovery/feed.rs) (`get_feed`), [`backend/src/handlers/social/lists.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/social/lists.rs) (`get_bookmarks`), [`backend/src/handlers/content/engagement.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/content/engagement.rs) (`toggle_reaction`, `toggle_bookmark`), & [`backend/src/handlers/media/reels.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/reels.rs) (`add_reel_comment`) (re-exported via [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs))

---

## 1. Executive Summary

During end-to-end integration walkthroughs between the Next.js frontend and the Rust Axum backend, four contract and pagination discrepancies were identified and fixed:
1. **`POST /api/settings/verify` Payload Type Mismatch (`422 Unprocessable Entity`)**: The Rust struct `SubmitVerifyPayload` expected `files: Vec<String>`, whereas `frontend/src/app/my/settings/page.tsx` submitted `files: verifyFileUrl` (a bare `string`), causing Axum's JSON extractor to reject KYC verification submissions with `422`.
2. **Admin Moderation Report Field Mismatch**: `GET /api/admin/dashboard` serialized the reporting and reported usernames as `reporter` and `reported`, while `frontend/src/app/admin/page.tsx` attempted to render `r.from_user_id` and `r.user_id`, resulting in blank reporter/reported columns in the Admin Moderation table.
3. **Missing Bookmarked Reels in `/my/bookmarks`**: While users could bookmark short-form video reels (`POST /api/posts/bookmark` with `reel_id`), `GET /api/bookmarks` only queried bookmarked posts (`post_id IS NOT NULL`) and omitted bookmarked reels (`reel_id IS NOT NULL`), and `frontend/src/app/my/bookmarks/page.tsx` had no tab or grid to display saved reels.
4. **Pre-Filter SQL `LIMIT/OFFSET` in `get_feed`**: `GET /api/feed` applied `LIMIT ?1 OFFSET ?2` in SQL *before* filtering posts in Rust for media categories (`media_type = image | video | audio`) or search keywords. Requesting page 1 with `filter=video` could return fewer than `per_page` items (or 0 items) if the top 10 newest database rows happened to be text-only posts.

---

## 2. Root Cause Analysis

1. **Inconsistent Client Call in `my/settings/page.tsx`**: Although [`frontend/src/lib/api.ts`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/lib/api.ts) defined `api.settings.verify(files: string[])`, `my/settings/page.tsx` bypassed the helper with a raw `fetch('/api/settings/verify', ...)` passing a single string.
2. **Untyped `any` in Admin Reports Table**: `admin/page.tsx` typed the report list as `any[]` and guessed the raw SQLite column names (`from_user_id`, `user_id`) instead of the joined alias names (`reporter`, `reported`) returned by `get_admin_dashboard`.
3. **Incomplete Reel Parity**: Reels were added as a modern feature alongside posts, and while `user_bookmarks` had a `reel_id` column, `get_bookmarks` only hydrated `posts` and `toggle_reaction`/`toggle_bookmark`/`add_reel_comment` did not return `404 Not Found` if a non-existent `reel_id` was passed.
4. **Hybrid SQL + In-Memory Filtering**: Because post attachment media types live in a one-to-many child table (`attachments`), `get_feed` hydrated `PostView` in Rust before checking `media_type`, which interacted poorly with SQL-level `LIMIT/OFFSET`.

---

## 3. Resolution & Code Changes

1. **Fixed KYC Verification Submission**:
   - Updated [`frontend/src/app/my/settings/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/my/settings/page.tsx) to send `files: [verifyFileUrl]` (`string[]`), matching `SubmitVerifyPayload { files: Vec<String> }`.
2. **Aligned Admin Reports UI**:
   - Updated [`frontend/src/app/admin/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/admin/page.tsx) to display `r.reporter` and `r.reported` (with fallback to `r.from_user_id` / `r.user_id`).
3. **Full Bookmarked Reels Support & Reel Existence Checks**:
   - Updated `get_bookmarks` in [`backend/src/handlers/social/lists.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/social/lists.rs) to return `{"posts": [...], "reels": [...], "total": ...}`.
   - Updated [`frontend/src/app/my/bookmarks/page.tsx`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/app/my/bookmarks/page.tsx) to render both **Saved Posts** and **Saved Reels**.
   - Added existence checks (`SELECT COUNT(*) FROM reels WHERE id = ?1`) to [`backend/src/handlers/content/engagement.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/content/engagement.rs) (`toggle_reaction`, `toggle_bookmark`) and [`backend/src/handlers/media/reels.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/reels.rs) (`add_reel_comment`) so invalid reel IDs properly return `404 Not Found`.
4. **Post-Filter Pagination in `get_feed`**:
   - Updated `get_feed` in [`backend/src/handlers/discovery/feed.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/discovery/feed.rs) to filter candidate posts by category (`image`, `video`, `audio`, `polls`) and search query first, and then apply `.skip(offset).take(per_page)` on the filtered vector with an accurate `total` count.

---

## 4. Prevention & Regression Tests

- Extended [`test_privacy_paywall_reels_and_moderation`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) to test:
  - `POST /api/reels/99999/like` returns `404 Not Found`.
  - Bookmarking a reel and calling `GET /api/bookmarks` returns the reel inside the `reels` array.
  - Category-filtered feed pagination (`GET /api/feed?media_type=image&page=1&per_page=2`) returns accurate filtered items and `total` counts.
- Verified zero TypeScript or Next.js build errors via `npm run build`.


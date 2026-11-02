# Post-Mortem 003: Cross-Poll Vote Corruption & Vault Attachment Row Duplication

- **Incident ID**: PM-003
- **Severity**: Medium (SEV-3 — Data Integrity)
- **Status**: Resolved & Regression-Tested
- **Date Discovered**: 2026-11-01 (During Backend Handler Code Review)
- **Affected Component**: [`backend/src/handlers/content/engagement.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/content/engagement.rs) (`vote_poll`) & [`backend/src/handlers/content/posts.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/content/posts.rs) (`save_post`) (re-exported via [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs))

---

## 1. Executive Summary

Two relational data-integrity bugs were identified in interactive post features:
1. **Cross-Poll Vote Corruption**: In `vote_poll` (`POST /api/polls/:id/vote`), the handler accepted a `poll_id` in the URL path and an `answer_id` in the JSON body (`{"answer_id": 42}`), but did not verify that `poll_answers.id == answer_id` actually belonged to `poll_answers.poll_id == poll_id`. A malformed or malicious request could record a row in `user_poll_answers` linking `poll_id = 1` with an `answer_id` from `poll_id = 2`, corrupting vote tallies and percentage calculations.
2. **Vault Attachment Row Duplication on Post Create/Edit**: When a creator attached an existing file from their Media Vault (`/my/vault`) or uploaded a file via `/api/upload` and then submitted `POST /api/posts` (`save_post`), the frontend passed the attachment's `file_url` (e.g., `"/uploads/uuid.jpg"`) in `attachment_ids`. However, `save_post` checked `SELECT COUNT(*) FROM attachments WHERE id = ?1` and updated `WHERE id = ?2` without matching `OR file_url = ?2`. As a result, `save_post` failed to match the existing vault `attachments` row and inserted a duplicate `attachments` record every time a post was saved or edited.

---

## 2. Root Cause Analysis

### Issue 1: Missing Parent-Child Ownership Check Before Vote Upsert
`user_poll_answers` has foreign keys to both `polls(id)` and `poll_answers(id)`. However, standard SQLite foreign key constraints only verify that `poll_id` exists in `polls` and `answer_id` exists in `poll_answers` independently; they do not enforce that `poll_answers.poll_id == user_poll_answers.poll_id` without a composite foreign key or an application-level check.

### Issue 2: Dual Identifier Format in `attachment_ids`
The `CreatePostRequest.attachment_ids` vector accepts strings that may be either an attachment UUID (`attachments.id`) or an uploaded path (`attachments.file_url`). The `UPDATE attachments SET post_id = ?1 WHERE id = ?2` statement only matched the primary key `id`, leaving any attachment referenced by `file_url` unlinked and falling through to the `INSERT INTO attachments` fallback.

---

## 3. Resolution & Code Changes

1. **Strict Poll-Answer Ownership Validation (`vote_poll`)**:
   - Added a pre-check in `vote_poll`:
     ```rust
     let belongs_to_poll: bool = conn
         .query_row(
             "SELECT COUNT(*) FROM poll_answers WHERE id = ?1 AND poll_id = ?2",
             params![payload.answer_id, poll_id],
             |r| r.get::<_, i64>(0),
         )
         .unwrap_or(0) > 0;
     if !belongs_to_poll {
         return Err((
             StatusCode::BAD_REQUEST,
             Json(serde_json::json!({"error": "Invalid answer_id for this poll"})),
         ));
     }
     ```
2. **Dual `id` / `file_url` Attachment Linking (`save_post`)**:
   - Updated `save_post` to match both `id` and `file_url` when linking existing uploaded/vault attachments to a post:
     ```sql
     UPDATE attachments
     SET post_id = ?1
     WHERE (id = ?2 OR file_url = ?2) AND user_id = ?3
     ```
   - Only inserts a new row into `attachments` if `rows_affected == 0`.

---

## 4. Prevention & Regression Tests

- Verified in [`test_privacy_paywall_reels_and_moderation`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) that voting on a poll with an `answer_id` belonging to another poll (or non-existent `answer_id`) returns `400 Bad Request` (`"Invalid answer_id for this poll"`).
- Verified in [`test_posts_feed_comments_reactions_bookmarks`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) that creating and editing posts with existing vault attachments links the existing row without duplicating vault items.


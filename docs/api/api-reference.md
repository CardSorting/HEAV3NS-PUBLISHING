# REST API Reference

All API endpoints are prefixed with `/api` on the Rust backend (`http://127.0.0.1:8080/api`) and proxied by Next.js (`http://127.0.0.1:3000/api`).

## Authentication Modes
- **Public**: No authentication required.
- **Optional Auth**: Accepts `Authorization: Bearer <token>` or `justfans_token` cookie if present to personalize `is_unlocked`, `is_subscribed`, `user_liked`, and `user_bookmarked` flags.
- **Protected (`AuthUser`)**: Requires a valid JWT (`401 Unauthorized` otherwise).
- **Admin (`role_id == 1`)**: Requires a valid JWT belonging to an Admin (`403 Forbidden` otherwise).

---

## 1. Health, Home & Authentication

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/health` | Public | Returns `{ "status": "ok", "service": "justfans-rust-backend", "version": "1.0.0" }`. |
| `GET` | `/api/home` | Optional | Returns site metadata and up to 9 featured creator profiles. |
| `POST` | `/api/auth/register` | Public | Creates user, `$100.00` wallet, default lists (`Followers`, `Following`, `Blocked`), sets cookie & returns `{ token, user }`. |
| `POST` | `/api/auth/login` | Public | Authenticates by email or username + password, sets cookie & returns `{ token, user }`. |
| `POST` | `/api/auth/logout` | Public | Clears `justfans_token` cookie (`Max-Age=0`). |
| `GET` | `/api/auth/me` | Protected | Returns current `user` profile, `unread_notifications`, and `unread_messages`. |

---

## 2. Feed, Posts, Reactions, Comments & Polls

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/feed?page=1&filter=all` | Optional | Paginated feed (`20` per page). Supports `filter`: `all`, `subscribed`, `media`, `photos`, `videos`. |
| `POST` | `/api/suggestions/filter` | Optional | Body: `{ "free_only": true }`. Returns suggested creators. |
| `GET` | `/api/posts/:post_id` | Optional | Returns `{ post, author }` with paywall rules applied. |
| `POST` | `/api/posts/save` | Protected | Creates or updates a post. Body: `{ id?, text, price?, is_pinned?, attachments?, poll_answers? }`. |
| `DELETE` | `/api/posts/:post_id` | Protected | Deletes a post (owner or Admin only). |
| `POST` | `/api/posts/react` | Protected | Toggles a like on a post, comment, or reel. Body: `{ post_id?, comment_id?, reel_id? }`. |
| `POST` | `/api/posts/bookmark` | Protected | Toggles a bookmark on a post or reel. Body: `{ post_id?, reel_id? }`. |
| `POST` | `/api/posts/pin` | Protected | Pins/unpins a post on the creator's profile. Body: `{ post_id }`. |
| `POST` | `/api/posts/comments` | Protected | Adds a comment to a post. Body: `{ post_id, message }`. |
| `DELETE` | `/api/posts/comments/:comment_id` | Protected | Deletes a post comment (comment author or Admin). |
| `POST` | `/api/posts/polls/vote` | Protected | Casts or updates a poll vote. Body: `{ poll_id, answer_id }`. Validates `answer_id` belongs to `poll_id`. |

---

## 3. Profiles, Search, Bookmarks & Custom Lists

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/profile/:username?filter=` | Optional | Returns `{ profile, posts, recent_media, streams }`. `recent_media` includes only unlocked attachments. |
| `GET` | `/api/search?query=&filter=top` | Optional | Searches public users, posts, and streams (`top`, `latest`, `people`, `photos`, `videos`, `live`). |
| `GET` | `/api/bookmarks` | Protected | Returns `{ posts, reels }` bookmarked by the current user. |
| `GET` | `/api/lists` | Protected | Lists all user lists (`Followers`, `Following`, `Blocked`, and `custom` lists) with member profiles. |
| `POST` | `/api/lists` | Protected | Creates a new custom list. Body: `{ name }`. |
| `GET` | `/api/lists/:list_id` | Protected | Returns details and members of a specific list. |
| `DELETE` | `/api/lists/:list_id` | Protected | Deletes a custom list. |
| `POST` | `/api/lists/members` | Protected | Toggles a user's membership in a list. Body: `{ list_id, member_user_id }`. |
| `POST` | `/api/lists/follow` | Protected | Toggles following a creator. Body: `{ target_user_id }`. |

---

## 4. Direct Messenger & Notifications

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/messenger/contacts` | Protected | Returns conversation contacts with last message preview and unread counts. |
| `GET` | `/api/messenger/messages/:user_id` | Protected | Marks incoming messages from `:user_id` as seen and returns conversation thread (masking locked PPV messages). |
| `POST` | `/api/messenger/send` | Protected | Sends a direct message (or paid PPV message). Body: `{ receiver_id, message, price? }`. |
| `DELETE` | `/api/messenger/message/:message_id` | Protected | Deletes a sent message (sender or Admin). |
| `GET` | `/api/notifications?filter=all` | Protected | Marks notifications as read and returns up to 50 notifications filtered by type (`all`, `like`, `comment`, `subscription`, `tip`, `message`). |

---

## 5. Payments, Subscriptions, Invoices & Withdrawals

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `POST` | `/api/payments/initiate` | Protected | Processes payments (`deposit`, `subscription`, `post-unlock`, `message-unlock`, `stream-unlock`, `tip`). Auto-assigns sequential `invoice_id` and credits 85% to creator wallet. |
| `GET` | `/api/invoices/:id` | Protected | Returns invoice details by `invoice_id` or `transaction.id` (accessible only to sender, recipient, or Admin). |
| `POST` | `/api/subscriptions/:sub_id/cancel` | Protected | Cancels an active subscription (`status = 'canceled'`). |
| `POST` | `/api/withdrawals/request` | Protected | Requests a creator payout (min `$20.00`). Deducts from wallet immediately. |

---

## 6. Settings, Verification & Media Vault

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/settings` | Protected | Returns `{ profile, subscriptions, transactions, withdrawals, verification }`. |
| `POST` | `/api/settings/profile` | Protected | Updates profile (`name`, `username`, `bio`, `location`, `website`, `birthdate`, `gender_pronoun`, `avatar`, `cover`). |
| `POST` | `/api/settings/rates` | Protected | Updates creator subscription pricing & `paid_profile` / `open_profile` flags. |
| `POST` | `/api/settings/account` | Protected | Updates email address and/or password (validating uniqueness and min length). |
| `POST` | `/api/settings/privacy` | Protected | Updates `public_profile`, `enable_2fa`, and `enable_geoblocking`. |
| `POST` | `/api/settings/verify` | Protected | Submits ID verification document URLs. Body: `{ files: string[] }`. |
| `GET` | `/api/vault` | Protected | Lists all uploaded Media Vault items for the current user. |
| `DELETE` | `/api/vault/:att_id` | Protected | Deletes a Media Vault attachment. |
| `POST` | `/api/attachment/upload` | Protected | `multipart/form-data` upload (up to 50MB). Sanitizes file extension, writes to `/uploads`, and records in `attachments`. |

---

## 7. Reels, Stories & Live Streams

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/reels` | Optional | Lists vertical video Reels with likes, comments count, and bookmark status. |
| `POST` | `/api/reels` | Protected | Publishes a new Reel. Body: `{ caption, video_url?, cover_url? }`. |
| `DELETE` | `/api/reels/:reel_id` | Protected | Deletes a Reel (owner or Admin). |
| `GET` | `/api/reels/:reel_id/comments` | Public | Lists comments on a Reel. |
| `POST` | `/api/reels/comments` | Protected | Adds a comment to a Reel. Body: `{ reel_id, message }`. |
| `GET` | `/api/stories` | Public | Lists active 24h stories and pinned highlights. |
| `POST` | `/api/stories` | Protected | Creates a 24h story. Body: `{ caption, media_url?, bg_preset?, is_highlight? }`. |
| `GET` | `/api/streams` | Optional | Lists active and ended streams with paywall status. |
| `POST` | `/api/streams` | Protected | Starts a live stream and generates an RTMP key. Body: `{ name, price?, requires_subscription?, is_public? }`. |
| `GET` | `/api/streams/:stream_id` | Optional | Returns `{ stream, messages }`. |
| `POST` | `/api/streams/:stream_id/stop` | Protected | Ends an active stream (`status = 'ended'`). |
| `POST` | `/api/streams/comment` | Protected | Posts a message in live stream chat. Body: `{ stream_id, message }`. |

---

## 8. Public Pages, Contact, Reports, AI & Admin Panel

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/pages/:slug` | Public | Returns public page HTML content (`terms-and-conditions`, `privacy`, `help`). |
| `POST` | `/api/contact` | Public | Submits a support contact message. Body: `{ email, subject, message }`. |
| `POST` | `/api/report` | Protected | Submits a moderation report. Body: `{ reported_user_id, post_id?, report_type, details? }`. |
| `POST` | `/api/ai/suggest` | Protected | Generates an AI creator caption suggestion. Body: `{ topic? }`. |
| `GET` | `/api/admin/dashboard` | Admin | Returns platform metrics, users, withdrawals, reports, and pending ID verifications. |
| `POST` | `/api/admin/withdrawals/:id` | Admin | Approves or rejects a payout withdrawal (`action: "approve" | "reject"`). Rejecting refunds the creator's wallet. |
| `POST` | `/api/admin/verifications/:id` | Admin | Approves or rejects a creator ID verification (`action: "approve" | "reject"`). Approving sets `users.identity_verified_at`. |
| `POST` | `/api/admin/reports/:id` | Admin | Resolves or dismisses a moderation report (`action: "resolve" | "dismiss"`). |


# Playbook 004: Content Moderation & Creator Identity Verification

## Objective
Process creator KYC identity verification submissions (`user_verifies`) and triage user-submitted abuse/safety reports (`user_reports`).

---

## Part 1: Creator KYC Identity Verification

Creators submit government ID documentation via `/settings` (**Verify** tab), which sends `POST /api/settings/verify` with `{"files": ["..."]}` (`Vec<String>`) and creates/updates a row in `user_verifies` with `status = 'pending'`.

### Step 1: Inspect Pending Verifications
```sql
SELECT uv.id, uv.user_id, u.username, u.email, uv.files, uv.status, uv.created_at
FROM user_verifies uv
JOIN users u ON u.id = uv.user_id
WHERE uv.status = 'pending'
ORDER BY uv.created_at ASC;
```

### Step 2: Approve or Reject via Admin Dashboard or API
- **Approve**: Sets `user_verifies.status = 'verified'` AND updates `users.identity_verified_at = CURRENT_TIMESTAMP`, displaying the verified badge across the creator's profile, posts, reels, and streams.
- **Reject**: Sets `user_verifies.status = 'rejected'` and records `rejection_reason`.

```bash
# Approve verification ID 1
curl -s -X POST http://127.0.0.1:8080/api/admin/verifications/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "verified"}' | jq .

# Reject verification ID 1
curl -s -X POST http://127.0.0.1:8080/api/admin/verifications/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "rejected", "reason": "ID document was blurry or expired"}' | jq .
```

---

## Part 2: User & Content Moderation (`user_reports`)

Users can report profiles, posts, messages, or streams via `POST /api/reports`. Each report contains:
- `from_user_id` (serialized as `reporter` in `GET /api/admin/reports`)
- `user_id` (serialized as `reported`)
- `post_id`, `message_id`, or `stream_id` (optional target resource)
- `type` (e.g., `"I don't like this post"`, `"Spam"`, `"Copyright"`, `"Harassment"`)
- `details` (free-text context)
- `status` (`"received"`, `"seen"`, `"solved"`)

### Step 1: Review Open Reports
Navigate to `/admin` -> **Moderation Reports**, or query via API:
```bash
curl -s http://127.0.0.1:8080/api/admin/reports \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
```

### Step 2: Take Enforcement Action
If a reported post violates platform policy, remove it using the admin token (admins bypass the creator ownership check in `delete_post`):
```bash
curl -s -X DELETE http://127.0.0.1:8080/api/posts/$OFFENDING_POST_ID \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
```

### Step 3: Resolve the Report
Mark the report as `"solved"` or `"seen"`:
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/reports/$REPORT_ID \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "solved"}' | jq .
```

# Playbook 004: Content Moderation & Creator Identity Verification

## Objective
Process creator KYC identity verification submissions (`user_verifies`) and triage user-submitted abuse/safety reports (`user_reports`) via the DDD handlers in [`backend/src/handlers/identity/settings.rs`](../../backend/src/handlers/identity/settings.rs), [`backend/src/handlers/platform/admin.rs`](../../backend/src/handlers/platform/admin.rs), and [`backend/src/handlers/content/posts.rs`](../../backend/src/handlers/content/posts.rs).

---

## Part 1: Creator KYC Identity Verification

Creators submit government ID documentation via `/my/settings?tab=verify` (**Verify** tab), which sends `POST /api/settings/verify` (`handlers::identity::submit_verification_request`) with `{"files": ["..."]}` (`Vec<String>`) and creates/updates a row in `user_verifies` with `status = 'pending'`.

### Step 1: Inspect Pending Verifications
```sql
SELECT uv.id, uv.user_id, u.username, u.email, uv.files, uv.status, uv.created_at
FROM user_verifies uv
JOIN users u ON u.id = uv.user_id
WHERE uv.status = 'pending'
ORDER BY uv.created_at ASC;
```

### Step 2: Approve or Reject via Admin Dashboard or API
- **Approve (`action: "approve"`)**: Sets `user_verifies.status = 'verified'` AND updates `users.identity_verified_at = datetime('now')` in [`handle_admin_verification`](../../backend/src/handlers/platform/admin.rs), displaying the verified badge across the creator's profile, posts, reels, and streams.
- **Reject (`action: "reject"`)**: Sets `user_verifies.status = 'rejected'`, clears `identity_verified_at = NULL`, and records `rejection_reason`.

```bash
# Approve verification ID 1
curl -s -X POST http://127.0.0.1:8080/api/admin/verifications/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "approve"}' | jq .

# Reject verification ID 1
curl -s -X POST http://127.0.0.1:8080/api/admin/verifications/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "reject", "rejection_reason": "ID document was blurry or expired"}' | jq .
```

---

## Part 2: User & Content Moderation (`user_reports`)

Users can report profiles or posts via `POST /api/report` (`handlers::platform::submit_report`). Each report contains:
- `from_user_id` (serialized as `reporter` in `GET /api/admin/dashboard`)
- `reported_user_id` (serialized as `reported`)
- `post_id` (optional target post)
- `report_type` (e.g., `"Inappropriate content"`, `"Spam"`, `"Copyright"`, `"Harassment"`)
- `details` (free-text context)
- `status` (`"received"`, `"resolved"`, `"dismissed"`)

### Step 1: Review Open Reports
Navigate to `/admin` -> **Moderation Reports**, or query via API (`handlers::platform::get_admin_dashboard`):
```bash
curl -s http://127.0.0.1:8080/api/admin/dashboard \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .reports
```

### Step 2: Take Enforcement Action
If a reported post violates platform policy, remove it using the admin token (admins bypass the creator ownership check in `handlers::content::delete_post` in [`backend/src/handlers/content/posts.rs`](../../backend/src/handlers/content/posts.rs)):
```bash
curl -s -X DELETE http://127.0.0.1:8080/api/posts/$OFFENDING_POST_ID \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
```

### Step 3: Resolve or Dismiss the Report
Mark the report as `"resolved"` (`action: "resolve"`) or `"dismissed"` (`action: "dismiss"`) via `handlers::platform::handle_admin_report`:
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/reports/$REPORT_ID \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "resolve"}' | jq .
```



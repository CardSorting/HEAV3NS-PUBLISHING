# Playbook 005: Security Incident Response & Paywall Audit

## Objective
Respond to suspected credential compromise, rotate JWT signing keys, and run automated verification probes to confirm that locked paywall content and private user PII are not leaking via API endpoints.

---

## 1. Rotating `JWT_SECRET` (Invalidate All Active Sessions)

Because JWT authentication in [`backend/src/auth.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/auth.rs) is stateless, rotating the `JWT_SECRET` environment variable immediately invalidates every issued token across all users.

```bash
# Generate a new 256-bit cryptographically random secret
NEW_SECRET=$(openssl rand -hex 32)

# Restart the backend with the rotated secret
JWT_SECRET="$NEW_SECRET" cargo run --release
```

---

## 2. Automated Paywall & Privacy Verification Suite

If a regression is suspected in paywall masking or PII redaction, run the dedicated security and privacy integration tests in [`backend/src/main.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs):

```bash
cd backend
cargo test test_financial_security_and_withdrawals -- --nocapture
cargo test test_privacy_paywall_reels_and_moderation -- --nocapture
```

What these two suites verify:
1. **Subscription Price Spoofing Blocked**: Submitting `amount: 0.0` or `amount: -50.0` on a subscription payment is rejected (`400 Bad Request`) or overridden with the database-authoritative creator rate.
2. **Duplicate PPV Unlocks Blocked**: Attempting to purchase `post-unlock`, `message-unlock`, or `stream-unlock` a second time returns `400 Bad Request` (`already unlocked`) without debiting the fan's wallet.
3. **Locked Post Redaction**: Fetching a locked PPV post as an unsubscribed/unpaid viewer returns `is_unlocked: false`, `text: ""`, `poll: null`, and `attachments[].file_url == "/img/locked-media.svg"`.
4. **Locked Profile Media Tab Redaction**: Fetching `/api/profile/:username` excludes attachments belonging to locked posts from `recent_media`.
5. **VIP Stream HLS Redaction**: Fetching `/api/streams` as an unauthorized user masks VIP stream URLs to `"/img/locked-stream.m3u8"` with `is_unlocked: false`.
6. **Public Profile PII Redaction**: Fetching `/api/profile/:username` for another user returns `email: null`, `birthdate: null`, and `wallet_balance: 0.0`.

---

## 3. Live Endpoint Smoke Audit (cURL)

Run these commands against a running instance (`http://127.0.0.1:8080`) to verify anonymous paywall behavior:

```bash
# 1. Verify anonymous request to public profile redacts email & wallet_balance
curl -s http://127.0.0.1:8080/api/profile/alex_creates | jq '{username: .user.username, email: .user.email, wallet: .user.wallet_balance}'

# Expected:
# {
#   "username": "alex_creates",
#   "email": null,
#   "wallet": 0.0
# }

# 2. Verify anonymous request to a PPV post (Post #2, $15.00) redacts text and attachment URLs
curl -s http://127.0.0.1:8080/api/posts/2 | jq '{id: .post.id, is_unlocked: .post.is_unlocked, text: .post.text, files: [.post.attachments[].file_url]}'

# Expected:
# {
#   "id": 2,
#   "is_unlocked": false,
#   "text": "",
#   "files": ["/img/locked-media.svg"]
# }
```


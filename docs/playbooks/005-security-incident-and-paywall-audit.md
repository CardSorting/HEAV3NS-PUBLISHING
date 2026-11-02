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

If a regression is suspected in paywall masking or PII redaction across [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs) or [`backend/src/handlers/`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/), run the dedicated security and privacy integration tests in [`backend/src/main.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs):

```bash
cd backend
cargo test test_security_and_regression_fixes -- --nocapture
cargo test test_privacy_paywall_reels_and_moderation -- --nocapture
cargo test test_deep_hardening_and_parity_features -- --nocapture
```

What these suites verify:
1. **Subscription Price Spoofing Blocked ([`handlers/monetization/ledger.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/monetization/ledger.rs))**: Submitting `amount: 0.0` or `amount: -50.0` on a subscription payment is overridden with the database-authoritative creator rate.
2. **Duplicate PPV Unlocks Blocked ([`handlers/monetization/ledger.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/monetization/ledger.rs))**: Attempting to purchase `post-unlock`, `reel-unlock`, `message-unlock`, or `stream-unlock` a second time returns `422 Unprocessable Entity` (`already unlocked`) without debiting the fan's wallet.
3. **Locked Post Redaction ([`db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs))**: Fetching a locked PPV post as an unsubscribed/unpaid viewer returns `is_unlocked: false`, locked placeholder text, `poll: null`, `comments: []`, and `attachments[].file_url == "/img/post-locked.svg"`.
4. **Locked Profile Media Tab Redaction ([`handlers/discovery/profiles.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/discovery/profiles.rs))**: Fetching `/api/profile/:username` excludes attachments belonging to locked posts from `recent_media`.
5. **VIP Stream HLS & PPV Reel Redaction ([`handlers/media/streams.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/streams.rs) & [`handlers/media/reels.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/reels.rs))**: Fetching `/api/streams` or `/api/reels` as an unauthorized user masks VIP stream HLS links and PPV reel video URLs with `is_unlocked: false`.
6. **Public Profile PII Redaction ([`db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs))**: Fetching `/api/profile/:username` for another user returns `email: null`, `birthdate: null`, and `wallet_balance: 0.0`.

---

## 3. Live Endpoint Smoke Audit (cURL)

Run these commands against a running instance (`http://127.0.0.1:8080`) to verify anonymous paywall behavior:

```bash
# 1. Verify anonymous request to public profile redacts email & wallet_balance
curl -s http://127.0.0.1:8080/api/profile/ariavance | jq '{username: .profile.username, email: .profile.email, wallet: .profile.wallet_balance}'

# Expected:
# {
#   "username": "ariavance",
#   "email": null,
#   "wallet": 0.0
# }

# 2. Verify anonymous request to a PPV post (Post #2, $19.99) redacts text and attachment URLs
curl -s http://127.0.0.1:8080/api/posts/2 | jq '{id: .post.id, is_unlocked: .post.is_unlocked, text: .post.text, files: [.post.attachments[].file_url]}'

# Expected:
# {
#   "id": 2,
#   "is_unlocked": false,
#   "text": "🔒 Exclusive locked content — Subscribe or unlock to view this post.",
#   "files": ["/img/post-locked.svg"]
# }
```



# Post-Mortem 001: Subscription Price Spoofing & Duplicate PPV Unlock Charges

- **Incident ID**: PM-001
- **Severity**: Critical (SEV-1 — Financial Integrity)
- **Status**: Resolved & Regression-Tested
- **Date Discovered**: 2026-11-01 (During Phase 4 Verification of Rust/Next.js Migration)
- **Affected Component**: [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs) (`initiate_payment`, `admin_handle_withdrawal`)

---

## 1. Executive Summary

During adversarial security auditing of the unified payment endpoint (`POST /api/payments/initiate`), two high-severity financial logic flaws were identified:
1. **Subscription Price Spoofing**: When initiating a `"subscription"` transaction, the handler prioritized the optional `payload.amount` sent by the client and only queried the creator's database subscription price (`profile_access_price*`) when `payload.amount` was omitted (`None`). A malicious client sending `{"transaction_type": "subscription", "recipient_user_id": 1, "amount": 0.0}` could obtain an active creator subscription for `$0.00` (or even pass a negative number).
2. **Duplicate PPV Unlock Charges**: When initiating `"post-unlock"`, `"message-unlock"`, or `"stream-unlock"` transactions, the handler did not check whether the fan had already unlocked the target item, nor did it verify that the item's database price matched. Fans could be charged multiple times for the same PPV post, message, or stream.
3. **Unrefunded Rejected Withdrawals**: In `admin_handle_withdrawal` (`POST /api/admin/withdrawals/:id`), rejecting a creator's withdrawal request updated `withdrawals.status = 'rejected'` without refunding the escrowed `amount` back to `wallets.total`.

---

## 2. Root Cause Analysis

### Flaw 1: Client-Trusted `payload.amount` Fallback
In the initial draft of `initiate_payment`, amount resolution was written as:
```rust
// VULNERABLE PATTERN (Before Fix)
let amount = payload.amount.unwrap_or_else(|| {
    lookup_creator_subscription_price(&conn, recipient_id, &sub_type)
});
```
Because the frontend UI conveniently passed `amount` in the JSON body for display symmetry with tips and deposits, `payload.amount` was `Some(...)`, bypassing the database price lookup entirely.

### Flaw 2: Missing Idempotency / Prior-Entitlement Query on PPV Unlocks
Unlike subscriptions (which updated an existing row or inserted a renewal), PPV unlocks simply inserted a new row into `transactions` and deducted the wallet balance without querying `SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND post_id = ?2 AND type = 'post-unlock' AND status = 'approved'`.

### Flaw 3: Asymmetric Escrow Accounting on Withdrawals
`request_withdrawal` immediately deducted `amount` from `wallets.total` to prevent double-withdrawal requests, assuming most withdrawals would be `"approved"`. However, the `"rejected"` branch in `admin_handle_withdrawal` only updated the `withdrawals` table status and forgot to reverse the wallet debit.

---

## 3. Resolution & Code Changes

All three issues were remediated in [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs):

1. **Authoritative Server-Side Pricing**:
   - `"subscription"` ignores `payload.amount` completely and always queries `profile_access_price`, `profile_access_price_3_months`, `profile_access_price_6_months`, or `profile_access_price_12_months` from `users`.
   - `"post-unlock"`, `"message-unlock"`, and `"stream-unlock"` query the authoritative `price` and `user_id` (creator) directly from `posts`, `user_messages`, and `streams`.
   - All transaction types enforce `if amount <= 0.0 { return Err((StatusCode::BAD_REQUEST, ...)); }`.
2. **Pre-Flight Duplicate Unlock Guard**:
   - Before debiting the wallet, `"post-unlock"`, `"message-unlock"`, and `"stream-unlock"` check `transactions` for any prior `status = 'approved'` unlock by `auth.user_id` and return `400 Bad Request` (`"Post/Message/Stream already unlocked"`).
3. **Atomic Rejection Refund**:
   - In `admin_handle_withdrawal`, transitioning a non-rejected withdrawal to `"rejected"` executes:
     ```sql
     UPDATE wallets SET total = total + ?1, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?2
     ```

---

## 4. Prevention & Regression Tests

Added [`test_financial_security_and_withdrawals`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) to the automated `cargo test` suite, which explicitly asserts:
- Negative and zero deposits/tips return `400 Bad Request`.
- Submitting a spoofed `$0.01` or `$0.00` `amount` on a subscription still charges the creator's true database price (`$9.99`).
- Unlocking an already-unlocked PPV post returns `400 Bad Request` and leaves wallet balances untouched.
- Rejecting a `$50.00` withdrawal restores the exact `$50.00` to the creator's wallet.


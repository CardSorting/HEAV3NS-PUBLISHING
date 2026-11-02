# ADR-005: Unified Payment Ledger, Authoritative Pricing, and 85/15 Creator Revenue Split

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

HEAV3NS-PUBLISHING supports six distinct financial flows:
1. Wallet deposit (`deposit`)
2. Recurring creator subscription (`subscription`)
3. Pay-Per-View post unlock (`post-unlock`)
4. Pay-Per-View private message unlock (`message-unlock`)
5. Pay-Per-View live stream unlock (`stream-unlock`)
6. Voluntary creator tips (`tip`, `post-tip`, `stream-tip`, `chat-tip`)

Plus creator payout withdrawals (`/api/wallet/withdraw` and `/api/admin/withdrawals/:id`).

If each flow maintained its own payment endpoint and custom accounting logic, the risk of pricing manipulation (e.g., a client submitting a modified subscription price), duplicate PPV charges, or inconsistent platform fee splits would be high.

## Decision

We centralized all fan-to-creator and wallet deposit transactions into a single handler, [`initiate_payment`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs) (`POST /api/payments/initiate`), governed by four strict rules:

1. **Authoritative Server-Side Pricing**:
   - For `"subscription"`, client-supplied `amount` is **ignored**; the price is queried directly from `users.profile_access_price` / `profile_access_price_3_months` / `profile_access_price_6_months` / `profile_access_price_12_months` and must be `> 0.0`.
   - For `"post-unlock"`, `"message-unlock"`, and `"stream-unlock"`, the price and recipient `creator_id` are queried directly from `posts.price`, `user_messages.price`, and `streams.price`.
   - For `"deposit"` and `"tip"`, the client-supplied `amount` must be strictly `> 0.0`.
2. **Idempotent / Duplicate-Purchase Prevention**:
   - Before charging a user for `"post-unlock"`, `"message-unlock"`, or `"stream-unlock"`, the handler queries `transactions` for an existing `status = 'approved'` record matching the user and target resource (`post_id`, `user_message_id`, or `stream_id`). If found, it rejects the request with `400 Bad Request` (`"Post/Message/Stream already unlocked"`).
3. **Deterministic 85% / 15% Revenue Split**:
   - For all creator-directed payments (`recipient_id != sender_id` and `tx_type != "deposit"`), the creator's `wallets.total` is immediately credited with `amount * 0.85`, while the 15% platform fee is retained implicitly in the transaction ledger.
4. **Immediate Escrow Deduction & Rejection Refund on Withdrawals**:
   - Requesting a withdrawal (`POST /api/wallet/withdraw`, minimum `$20.00`) immediately deducts `amount` from `wallets.total` so those funds cannot be spent twice while `status = 'requested'`.
   - If an administrator transitions the withdrawal to `status = 'rejected'` (`POST /api/admin/withdrawals/:id`), the exact `amount` is atomically refunded to `wallets.total`.

## Consequences

### Positive
- **Single Audit Trail**: Every movement of funds is recorded in `transactions` and `withdrawals` with consistent status codes and provider metadata.
- **Immunity to Client Price Tampering**: Even if a malicious client tampers with the JSON body of `/api/payments/initiate`, they cannot purchase a subscription or PPV item below its database price.
- **Zero Double-Spend on Payouts**: Deducting wallet balance on withdrawal request prevents a user from queuing multiple concurrent withdrawal requests exceeding their balance.

### Negative / Trade-offs
- **Centralized Handler Complexity**: `initiate_payment` handles branching validation for 6 transaction types in a single function, requiring comprehensive integration test coverage (provided in `test_financial_security_and_withdrawals`).

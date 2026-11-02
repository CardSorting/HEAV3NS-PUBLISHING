# Playbook 003: Payment, Wallet & Creator Payout Reconciliation

## Objective
Audit wallet balances, verify the 85%/15% creator revenue split across transactions in [`backend/src/handlers/monetization/ledger.rs`](../../backend/src/handlers/monetization/ledger.rs) (`initiate_payment`, `request_withdrawal`), process pending creator withdrawal requests in [`backend/src/handlers/platform/admin.rs`](../../backend/src/handlers/platform/admin.rs) (`handle_admin_withdrawal`), and reconcile discrepancies.

---

## 1. Ledger Invariants Reference

1. **Fan Deposits (`tx_type = 'deposit'`)**:
   - `sender_user_id == recipient_user_id`.
   - Credits `100%` of `amount` to `wallets.total`.
2. **Creator Monetization (`subscription`, `post-unlock`, `reel-unlock`, `message-unlock`, `stream-unlock`, `tip`)**:
   - If `payment_provider == 'credit'`, deducts `100%` of `amount` from `sender_user_id`'s wallet.
   - Credits `85%` (`round_money(amount * 0.85)`) to `recipient_user_id`'s wallet.
   - Retains `15%` (`amount * 0.15`) as platform revenue.
3. **Creator Withdrawals (`withdrawals` table — `POST /api/withdrawals/request` in `handlers::monetization::request_withdrawal`)**:
   - Minimum withdrawal: `$20.00`.
   - On creation (`status = 'requested'`): `100%` of `amount` is immediately deducted from `wallets.total` inside a `BEGIN IMMEDIATE` transaction.
   - On approval (`status = 'approved'`): Payout is marked settled; wallet balance remains unchanged (already deducted).
   - On rejection (`status = 'rejected'`): `100%` of `amount` is atomically refunded to `wallets.total` inside `BEGIN IMMEDIATE`.

---

## 2. Auditing Pending Creator Withdrawals

### Via Admin UI
1. Log in as an administrator (`aria@justfans.local`) and navigate to `/admin`.
2. Open the **Withdrawals** tab.
3. Click **Approve** (after disbursing funds via Stripe Connect / bank transfer / PayPal) or **Reject** (to automatically refund the creator's wallet).

### Via API / CLI
List all withdrawals via the Admin Dashboard endpoint (`handlers::platform::get_admin_dashboard`):
```bash
curl -s http://127.0.0.1:8080/api/admin/dashboard \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .withdrawals
```

Approve a withdrawal (`id = 1` via `handlers::platform::handle_admin_withdrawal`):
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/withdrawals/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "approve"}' | jq .
```

Reject a withdrawal and trigger automatic wallet refund (`id = 2`):
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/withdrawals/2 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action": "reject"}' | jq .
```

---

## 3. SQL Ledger Reconciliation Queries

Run these queries via `sqlite3 -header -column backend/justfans.sqlite` to audit platform revenue and creator earnings:

### Platform Gross Volume (GMV) & 15% Platform Fee Summary
```sql
SELECT
  tx_type,
  COUNT(*) AS tx_count,
  ROUND(SUM(amount), 2) AS gross_volume_usd,
  ROUND(SUM(CASE WHEN tx_type != 'deposit' THEN amount * 0.85 ELSE 0 END), 2) AS creator_net_usd,
  ROUND(SUM(CASE WHEN tx_type != 'deposit' THEN amount * 0.15 ELSE 0 END), 2) AS platform_fee_usd
FROM transactions
WHERE status = 'approved'
GROUP BY tx_type;
```

### Detect Duplicate Approved PPV Unlocks (Should Always Return 0 Rows)
```sql
SELECT sender_user_id, tx_type, post_id, reel_id, message_id, stream_id, COUNT(*) AS unlock_count
FROM transactions
WHERE status = 'approved'
  AND tx_type IN ('post-unlock', 'reel-unlock', 'message-unlock', 'stream-unlock')
GROUP BY sender_user_id, tx_type, post_id, reel_id, message_id, stream_id
HAVING COUNT(*) > 1;
```



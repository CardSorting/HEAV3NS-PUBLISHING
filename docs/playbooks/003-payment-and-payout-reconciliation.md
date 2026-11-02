# Playbook 003: Payment, Wallet & Creator Payout Reconciliation

## Objective
Audit wallet balances, verify the 85%/15% creator revenue split across transactions, process pending creator withdrawal requests, and reconcile discrepancies.

---

## 1. Ledger Invariants Reference

1. **Fan Deposits (`type = 'deposit'`)**:
   - `sender_user_id == recipient_user_id`.
   - Credits `100%` of `amount` to `wallets.total`.
2. **Creator Monetization (`subscription`, `post-unlock`, `message-unlock`, `stream-unlock`, `tip`, `post-tip`, `stream-tip`, `chat-tip`)**:
   - If `payment_provider == 'credit'`, deducts `100%` of `amount` from `sender_user_id`'s wallet.
   - Credits `85%` (`amount * 0.85`) to `recipient_user_id`'s wallet.
   - Retains `15%` (`amount * 0.15`) as platform revenue.
3. **Creator Withdrawals (`withdrawals` table)**:
   - Minimum withdrawal: `$20.00`.
   - On creation (`status = 'requested'`): `100%` of `amount` is immediately deducted from `wallets.total`.
   - On approval (`status = 'approved'`): Payout is marked settled (`processed = 1`); wallet balance remains unchanged (already deducted).
   - On rejection (`status = 'rejected'`): `100%` of `amount` is refunded to `wallets.total`.

---

## 2. Auditing Pending Creator Withdrawals

### Via Admin UI
1. Log in as an administrator (`admin@justfans.test`) and navigate to `/admin`.
2. Open the **Withdrawals** tab.
3. Click **Approve** (after disbursing funds via Stripe Connect / bank transfer / PayPal) or **Reject** (to automatically refund the creator's wallet).

### Via API / CLI
List all withdrawals:
```bash
curl -s http://127.0.0.1:8080/api/admin/withdrawals \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
```

Approve a withdrawal (`id = 1`):
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/withdrawals/1 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "approved"}' | jq .
```

Reject a withdrawal and trigger automatic wallet refund (`id = 2`):
```bash
curl -s -X POST http://127.0.0.1:8080/api/admin/withdrawals/2 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "rejected"}' | jq .
```

---

## 3. SQL Ledger Reconciliation Queries

Run these queries via `sqlite3 -header -column backend/justfans.db` to audit platform revenue and creator earnings:

### Platform Gross Volume (GMV) & 15% Platform Fee Summary
```sql
SELECT
  type,
  COUNT(*) AS tx_count,
  ROUND(SUM(amount), 2) AS gross_volume_usd,
  ROUND(SUM(CASE WHEN type != 'deposit' THEN amount * 0.85 ELSE 0 END), 2) AS creator_net_usd,
  ROUND(SUM(CASE WHEN type != 'deposit' THEN amount * 0.15 ELSE 0 END), 2) AS platform_fee_usd
FROM transactions
WHERE status = 'approved'
GROUP BY type;
```

### Detect Duplicate Approved PPV Unlocks (Should Always Return 0 Rows)
```sql
SELECT sender_user_id, type, post_id, user_message_id, stream_id, COUNT(*) AS unlock_count
FROM transactions
WHERE status = 'approved'
  AND type IN ('post-unlock', 'message-unlock', 'stream-unlock')
GROUP BY sender_user_id, type, post_id, user_message_id, stream_id
HAVING COUNT(*) > 1;
```


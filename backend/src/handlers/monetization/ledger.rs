use axum::{
    extract::{Path, State},
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde::Deserialize;
use serde_json::{json, Value};
use uuid::Uuid;

use crate::{
    auth::{AppError, AuthUser},
    db::{is_blocked_between, round_money, AppState},
};

#[derive(Deserialize)]
pub struct InitiatePaymentPayload {
    pub payment_type: String, // "deposit", "subscription", "tip", "post-unlock", "message-unlock", "stream-unlock", "reel-unlock"
    pub recipient_user_id: Option<i64>,
    pub post_id: Option<i64>,
    pub message_id: Option<i64>,
    pub stream_id: Option<i64>,
    pub reel_id: Option<i64>,
    pub amount: Option<f64>,
    pub provider: Option<String>, // "credit", "stripe", "paypal"
    pub months: Option<i64>,
}

pub async fn initiate_payment(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<InitiatePaymentPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("wallet")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "wallet_transact").await?;
    let res: Result<Value, AppError> = (|| {
        let mut conn = state.lock_db();
        let raw_provider = payload
            .provider
            .unwrap_or_else(|| "credit".into())
            .trim()
            .to_lowercase();
        let provider = match raw_provider.as_str() {
            "wallet" | "credit" | "" => "credit".to_string(),
            "stripe" | "paypal" | "crypto" | "ccbill" => raw_provider,
            other => {
                return Err(AppError::BadRequest(format!(
                    "Unsupported payment provider: {other}"
                )))
            }
        };
        let ptype = payload.payment_type.trim();

        if ptype == "deposit" {
            let amt = round_money(payload.amount.unwrap_or(0.0));
            if amt <= 0.0 || !amt.is_finite() {
                return Err(AppError::Unprocessable("Deposit amount must be positive.".into()));
            }
            let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
            let next_inv: i64 = tx
                .query_row(
                    "SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions",
                    [],
                    |r| r.get(0),
                )
                .unwrap_or(1001);
            tx.execute(
                "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                params![amt, auth.user_id],
            )?;
            tx.execute(
                "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, invoice_id)
                 VALUES (?1, ?1, 'deposit', 'approved', ?2, ?3, ?4)",
                params![auth.user_id, amt, provider, next_inv],
            )?;
            let new_balance: f64 = tx.query_row(
                "SELECT total FROM wallets WHERE user_id = ?1",
                params![auth.user_id],
                |r| r.get(0),
            )?;
            tx.commit()?;
            crate::sharding::record_agent_rest_audit(
                &conn,
                &auth,
                quota_dec.as_ref(),
                "payment",
                Some(next_inv.to_string()),
                0.0,
                json!({ "payment_type": "deposit", "amount": amt }),
            );
            return Ok(json!({
                "success": true,
                "message": format!("${:.2} deposited into your wallet!", amt),
                "wallet_balance": round_money(new_balance)
            }));
        }

        // Determine price and recipient
        let (raw_amount, recipient_id) = match ptype {
            "subscription" => {
                let rec_id = payload
                    .recipient_user_id
                    .ok_or_else(|| AppError::BadRequest("recipient_user_id required".into()))?;
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("Cannot subscribe to yourself.".into()));
                }
                let months = payload.months.unwrap_or(1);
                let price: f64 = match months {
                    3 => conn.query_row(
                        "SELECT profile_access_price_3_months * 3 FROM users WHERE id = ?1",
                        params![rec_id],
                        |r| r.get(0),
                    )?,
                    6 => conn.query_row(
                        "SELECT profile_access_price_6_months * 6 FROM users WHERE id = ?1",
                        params![rec_id],
                        |r| r.get(0),
                    )?,
                    12 => conn.query_row(
                        "SELECT profile_access_price_12_months * 12 FROM users WHERE id = ?1",
                        params![rec_id],
                        |r| r.get(0),
                    )?,
                    _ => conn.query_row(
                        "SELECT profile_access_price FROM users WHERE id = ?1",
                        params![rec_id],
                        |r| r.get(0),
                    )?,
                };
                (price.max(0.0), rec_id)
            }
            "post-unlock" => {
                let pid = payload
                    .post_id
                    .ok_or_else(|| AppError::BadRequest("post_id required".into()))?;
                let (rec_id, price): (i64, f64) = conn.query_row(
                    "SELECT user_id, price FROM posts WHERE id = ?1",
                    params![pid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )?;
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("You already own this post.".into()));
                }
                let already_unlocked: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND post_id = ?2 AND tx_type = 'post-unlock' AND status = 'approved'",
                        params![auth.user_id, pid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);
                if already_unlocked > 0 {
                    return Err(AppError::Unprocessable("Post is already unlocked.".into()));
                }
                (price, rec_id)
            }
            "message-unlock" => {
                let mid = payload
                    .message_id
                    .ok_or_else(|| AppError::BadRequest("message_id required".into()))?;
                let (rec_id, price): (i64, f64) = conn.query_row(
                    "SELECT sender_id, price FROM user_messages WHERE id = ?1",
                    params![mid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )?;
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("You sent this message.".into()));
                }
                let already_unlocked: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND message_id = ?2 AND tx_type = 'message-unlock' AND status = 'approved'",
                        params![auth.user_id, mid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);
                if already_unlocked > 0 {
                    return Err(AppError::Unprocessable("Message is already unlocked.".into()));
                }
                (price, rec_id)
            }
            "stream-unlock" => {
                let sid = payload
                    .stream_id
                    .ok_or_else(|| AppError::BadRequest("stream_id required".into()))?;
                let (rec_id, price): (i64, f64) = conn.query_row(
                    "SELECT user_id, price FROM streams WHERE id = ?1",
                    params![sid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )?;
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("You own this stream.".into()));
                }
                let already_unlocked: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND stream_id = ?2 AND tx_type = 'stream-unlock' AND status = 'approved'",
                        params![auth.user_id, sid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);
                if already_unlocked > 0 {
                    return Err(AppError::Unprocessable("Stream is already unlocked.".into()));
                }
                (price, rec_id)
            }
            "reel-unlock" => {
                let rid = payload
                    .reel_id
                    .ok_or_else(|| AppError::BadRequest("reel_id required".into()))?;
                let (rec_id, price): (i64, f64) = conn.query_row(
                    "SELECT user_id, price FROM reels WHERE id = ?1",
                    params![rid],
                    |r| Ok((r.get(0)?, r.get(1)?)),
                )?;
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("You own this reel.".into()));
                }
                let already_unlocked: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND reel_id = ?2 AND tx_type = 'reel-unlock' AND status = 'approved'",
                        params![auth.user_id, rid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);
                if already_unlocked > 0 {
                    return Err(AppError::Unprocessable("Reel is already unlocked.".into()));
                }
                (price, rec_id)
            }
            "tip" => {
                let amt = payload.amount.unwrap_or(5.0);
                if amt <= 0.0 || !amt.is_finite() {
                    return Err(AppError::Unprocessable("Tip amount must be greater than 0.".into()));
                }
                let rec_id = if let Some(r) = payload.recipient_user_id {
                    r
                } else if let Some(pid) = payload.post_id {
                    conn.query_row("SELECT user_id FROM posts WHERE id = ?1", params![pid], |r| {
                        r.get(0)
                    })?
                } else {
                    return Err(AppError::BadRequest("recipient_user_id or post_id required".into()));
                };
                if rec_id == auth.user_id {
                    return Err(AppError::Unprocessable("Cannot tip yourself.".into()));
                }
                (amt, rec_id)
            }
            _ => {
                return Err(AppError::BadRequest(format!(
                    "Unsupported payment_type: {ptype}"
                )))
            }
        };

        if is_blocked_between(&conn, auth.user_id, recipient_id) {
            return Err(AppError::Forbidden("Transaction unavailable due to block settings.".into()));
        }

        let amount = round_money(raw_amount);

        // Enforce Autonomous Agent spend guardrails if authenticated via MCP Agent Token
        if let Some(tid) = auth.agent_token_id {
            if let Some(max_tx) = auth.max_spend_per_tx {
                if amount > max_tx {
                    return Err(AppError::Unprocessable(format!(
                        "Autonomous agent payment (${amount:.2}) exceeds per-transaction spend cap (${max_tx:.2})."
                    )));
                }
            }
            if let Some(daily_lim) = auth.daily_spend_limit {
                let spent_today: f64 = conn
                    .query_row(
                        "SELECT COALESCE(SUM(amount_spent), 0.0) FROM agent_audit_logs
                         WHERE agent_token_id = ?1 AND status = 'success' AND created_at >= datetime('now', '-24 hours')",
                        params![tid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0.0);
                if spent_today + amount > daily_lim {
                    return Err(AppError::Unprocessable(format!(
                        "Autonomous agent payment (${amount:.2}) would exceed 24h daily spend cap (${daily_lim:.2}; ${spent_today:.2} already spent)."
                    )));
                }
            }
        }

        let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;

        // Deduct from wallet if using credit
        if provider == "credit" && amount > 0.0 {
            let sender_balance: f64 = tx
                .query_row(
                    "SELECT total FROM wallets WHERE user_id = ?1",
                    params![auth.user_id],
                    |r| r.get(0),
                )
                .unwrap_or(0.0);
            if sender_balance < amount {
                return Err(AppError::Unprocessable(format!(
                    "Insufficient wallet balance (${sender_balance:.2} available, ${amount:.2} required). Please deposit funds or choose Stripe/PayPal."
                )));
            }
            tx.execute(
                "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                params![amount, auth.user_id],
            )?;
        }

        // Credit recipient wallet (minus 15% platform fee)
        if amount > 0.0 {
            let creator_share = round_money(amount * 0.85);
            tx.execute(
                "UPDATE wallets SET total = ROUND(total + ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
                params![creator_share, recipient_id],
            )?;
        }

        // Record transaction with generated invoice_id
        let next_invoice_id: i64 = tx
            .query_row(
                "SELECT COALESCE(MAX(invoice_id), 1000) + 1 FROM transactions",
                [],
                |r| r.get(0),
            )
            .unwrap_or(1001);

        tx.execute(
            "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, post_id, message_id, stream_id, reel_id, invoice_id)
             VALUES (?1, ?2, ?3, 'approved', ?4, ?5, ?6, ?7, ?8, ?9, ?10)",
            params![
                auth.user_id,
                recipient_id,
                ptype,
                amount,
                provider,
                payload.post_id,
                payload.message_id,
                payload.stream_id,
                payload.reel_id,
                next_invoice_id
            ],
        )?;

        if ptype == "subscription" {
            let days = payload.months.unwrap_or(1) * 30;
            tx.execute(
                "INSERT INTO subscriptions (sender_user_id, recipient_user_id, amount, provider, status, expires_at)
                 VALUES (?1, ?2, ?3, ?4, 'completed', datetime('now', ?5))",
                params![
                    auth.user_id,
                    recipient_id,
                    amount,
                    provider,
                    format!("+{days} days")
                ],
            )?;
        }

        if let Some(tid) = auth.agent_token_id {
            let shard_id = quota_dec.as_ref().map(|d| d.shard_id).unwrap_or(0);
            let pool_tier = quota_dec
                .as_ref()
                .map(|d| d.pool_tier.clone())
                .unwrap_or_else(|| "central".into());
            let action_units = quota_dec.as_ref().map(|d| d.action_units).unwrap_or(4.0);
            let throttle_mode = quota_dec
                .as_ref()
                .map(|d| d.throttle_mode.clone())
                .unwrap_or_else(|| "smooth".into());
            let pacing_ms = quota_dec.as_ref().map(|d| d.pacing_delay_ms).unwrap_or(0);
            let _ = tx.execute(
                "INSERT INTO agent_audit_logs (user_id, agent_token_id, tool_name, resource_type, resource_id, status, amount_spent, action_units, shard_id, pool_tier, throttle_mode, pacing_delay_ms, details)
                 VALUES (?1, ?2, ?3, 'payment', ?4, 'success', ?5, ?6, ?7, ?8, ?9, ?10, ?11)",
                params![
                    auth.user_id,
                    tid,
                    format!("payment.{ptype}"),
                    next_invoice_id.to_string(),
                    amount,
                    action_units,
                    shard_id,
                    pool_tier,
                    throttle_mode,
                    pacing_ms,
                    json!({ "payment_type": ptype, "recipient_user_id": recipient_id, "amount": amount }).to_string()
                ],
            );
        }

        // Send notification to creator
        if recipient_id != auth.user_id {
            let notif_msg = match ptype {
                "subscription" => format!("@{} subscribed to your profile for ${amount:.2}", auth.username),
                "tip" => format!("@{} sent you a ${amount:.2} tip!", auth.username),
                "post-unlock" => format!("@{} unlocked your PPV post for ${amount:.2}", auth.username),
                "message-unlock" => format!("@{} unlocked your PPV message for ${amount:.2}", auth.username),
                "reel-unlock" => format!("@{} unlocked your Reel for ${amount:.2}", auth.username),
                _ => format!("@{} sent a ${amount:.2} payment", auth.username),
            };
            let _ = tx.execute(
                "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message)
                 VALUES (?1, ?2, ?3, ?4, ?5, ?6)",
                params![
                    Uuid::new_v4().to_string(),
                    auth.user_id,
                    recipient_id,
                    ptype,
                    payload.post_id,
                    notif_msg
                ],
            );
        }

        let updated_balance: f64 = tx
            .query_row(
                "SELECT total FROM wallets WHERE user_id = ?1",
                params![auth.user_id],
                |r| r.get(0),
            )
            .unwrap_or(0.0);

        tx.commit()?;

        Ok(json!({
            "success": true,
            "message": "Payment processed successfully!",
            "invoice_id": next_invoice_id,
            "amount": amount,
            "wallet_balance": round_money(updated_balance)
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn cancel_subscription(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(sub_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("wallet")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "wallet_transact").await?;
    let res: Result<Value, AppError> = (|| {
        let conn = state.lock_db();
        conn.execute(
            "UPDATE subscriptions SET status = 'canceled' WHERE id = ?1 AND (sender_user_id = ?2 OR recipient_user_id = ?2)",
            params![sub_id, auth.user_id],
        )?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "subscription",
            Some(sub_id.to_string()),
            0.0,
            json!({ "canceled_subscription_id": sub_id }),
        );
        Ok(json!({
            "success": true,
            "message": "Subscription canceled."
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

#[derive(Deserialize)]
pub struct WithdrawalRequestPayload {
    pub amount: f64,
    pub payment_method: Option<String>,
    pub payment_identifier: Option<String>,
    pub message: Option<String>,
}

pub async fn request_withdrawal(
    State(state): State<AppState>,
    auth: AuthUser,
    Json(payload): Json<WithdrawalRequestPayload>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("wallet")?;
    let quota_dec = crate::sharding::enforce_agent_rest_quota(&state, &auth, "wallet_withdraw").await?;
    let res: Result<Value, AppError> = (|| {
        let amount = round_money(payload.amount);
        if amount < 20.0 || !amount.is_finite() {
            return Err(AppError::Unprocessable(
                "Minimum withdrawal amount is $20.00.".into(),
            ));
        }
        let mut conn = state.lock_db();
        let tx = conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
        let balance: f64 = tx
            .query_row(
                "SELECT total FROM wallets WHERE user_id = ?1",
                params![auth.user_id],
                |r| r.get(0),
            )
            .unwrap_or(0.0);

        if balance < amount {
            return Err(AppError::Unprocessable(
                "Requested amount exceeds available wallet balance.".into(),
            ));
        }

        tx.execute(
            "UPDATE wallets SET total = ROUND(total - ?1, 2), updated_at = datetime('now') WHERE user_id = ?2",
            params![amount, auth.user_id],
        )?;

        tx.execute(
            "INSERT INTO withdrawals (user_id, amount, status, payment_method, payment_identifier, message)
             VALUES (?1, ?2, 'requested', ?3, ?4, ?5)",
            params![
                auth.user_id,
                amount,
                payload.payment_method.unwrap_or_else(|| "Bank Transfer".into()),
                payload.payment_identifier.unwrap_or_default(),
                payload.message.unwrap_or_default()
            ],
        )?;
        let wid = tx.last_insert_rowid();

        let new_balance: f64 = tx.query_row(
            "SELECT total FROM wallets WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )?;
        tx.commit()?;
        crate::sharding::record_agent_rest_audit(
            &conn,
            &auth,
            quota_dec.as_ref(),
            "withdrawal",
            Some(wid.to_string()),
            0.0,
            json!({ "withdrawal_id": wid, "amount": amount }),
        );

        Ok(json!({
            "success": true,
            "message": "Withdrawal request submitted for review.",
            "wallet_balance": round_money(new_balance)
        }))
    })();
    if res.is_err() {
        crate::sharding::refund_agent_rest_quota(&state, &auth, quota_dec.as_ref());
    }
    res.map(Json)
}

pub async fn get_invoice_detail(
    State(state): State<AppState>,
    auth: AuthUser,
    Path(invoice_or_tx_id): Path<i64>,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let tx = conn.query_row(
        "SELECT t.id, t.sender_user_id, t.recipient_user_id, u1.name, u1.email, u2.name,
                t.tx_type, t.status, t.amount, t.currency, t.payment_provider, t.post_id, t.invoice_id, t.created_at
         FROM transactions t
         JOIN users u1 ON t.sender_user_id = u1.id
         LEFT JOIN users u2 ON t.recipient_user_id = u2.id
         WHERE (t.invoice_id = ?1 OR t.id = ?1)
           AND (t.sender_user_id = ?2 OR t.recipient_user_id = ?2 OR ?3 = 1)
         ORDER BY t.id DESC LIMIT 1",
        params![invoice_or_tx_id, auth.user_id, auth.role_id],
        |r| {
            Ok(json!({
                "id": r.get::<_, i64>(0)?,
                "sender_user_id": r.get::<_, i64>(1)?,
                "recipient_user_id": r.get::<_, Option<i64>>(2)?,
                "sender_name": r.get::<_, String>(3)?,
                "sender_email": r.get::<_, String>(4)?,
                "recipient_name": r.get::<_, Option<String>>(5)?,
                "tx_type": r.get::<_, String>(6)?,
                "status": r.get::<_, String>(7)?,
                "amount": r.get::<_, f64>(8)?,
                "currency": r.get::<_, String>(9)?,
                "payment_provider": r.get::<_, String>(10)?,
                "post_id": r.get::<_, Option<i64>>(11)?,
                "invoice_id": r.get::<_, Option<i64>>(12)?.unwrap_or(invoice_or_tx_id),
                "created_at": r.get::<_, String>(13)?,
            }))
        },
    )?;

    Ok(Json(json!({
        "success": true,
        "invoice": tx
    })))
}


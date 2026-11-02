use axum::{
    extract::State,
    response::IntoResponse,
    Json,
};
use rusqlite::params;
use serde_json::json;

use crate::{
    auth::{AppError, AuthUser},
    db::{get_user_public_profile, round_money, AppState},
};

pub async fn get_creator_analytics(
    State(state): State<AppState>,
    auth: AuthUser,
) -> Result<impl IntoResponse, AppError> {
    auth.require_scope("read")?;
    let conn = state.lock_db();
    let profile = get_user_public_profile(&conn, auth.user_id, Some(auth.user_id))?;

    let (gross_earnings, tx_count): (f64, i64) = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0), COUNT(*) FROM transactions
             WHERE recipient_user_id = ?1 AND sender_user_id != ?1 AND status = 'approved'",
            params![auth.user_id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )
        .unwrap_or((0.0, 0));

    let net_earnings = round_money(gross_earnings * 0.85);

    let sub_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type IN ('subscription', 'one-month-subscription') AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let ppv_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type IN ('post-unlock', 'message-unlock', 'reel-unlock', 'stream-unlock') AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let tips_revenue: f64 = conn
        .query_row(
            "SELECT COALESCE(SUM(amount), 0.0) FROM transactions
             WHERE recipient_user_id = ?1 AND tx_type = 'tip' AND status = 'approved'",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0.0);

    let total_likes: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM reactions r JOIN posts p ON r.post_id = p.id WHERE p.user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_comments: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM post_comments c JOIN posts p ON c.post_id = p.id WHERE p.user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_reel_views: i64 = conn
        .query_row(
            "SELECT COALESCE(SUM(views_count), 0) FROM reels WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let total_story_views: i64 = conn
        .query_row(
            "SELECT COALESCE(SUM(views_count), 0) FROM stories WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    let agent_actions_count: i64 = conn
        .query_row(
            "SELECT COUNT(*) FROM agent_audit_logs WHERE user_id = ?1",
            params![auth.user_id],
            |r| r.get(0),
        )
        .unwrap_or(0);

    Ok(Json(json!({
        "success": true,
        "analytics": {
            "user_id": auth.user_id,
            "username": profile.username,
            "wallet_balance": profile.wallet_balance,
            "active_subscribers": profile.fans_count,
            "followers_count": profile.followers_count,
            "posts_count": profile.posts_count,
            "gross_earnings": round_money(gross_earnings),
            "net_earnings_85_pct": net_earnings,
            "transactions_count": tx_count,
            "revenue_breakdown": {
                "subscriptions": round_money(sub_revenue),
                "ppv_unlocks": round_money(ppv_revenue),
                "tips": round_money(tips_revenue),
            },
            "engagement": {
                "post_likes": total_likes,
                "post_comments": total_comments,
                "reel_views": total_reel_views,
                "story_views": total_story_views,
                "autonomous_agent_actions": agent_actions_count
            }
        }
    })))
}


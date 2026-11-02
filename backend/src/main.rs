pub mod auth;
pub mod db;
pub mod handlers;
pub mod models;

use axum::{
    extract::DefaultBodyLimit,
    http::{header, Method},
    routing::{delete, get, post},
    Router,
};
use std::sync::{Arc, Mutex};
use tower_http::{
    cors::{AllowOrigin, CorsLayer},
    services::ServeDir,
};

use crate::db::{init_db, AppState};

pub fn build_app(state: AppState) -> Router {
    let cors = CorsLayer::new()
        .allow_origin(AllowOrigin::mirror_request())
        .allow_methods([
            Method::GET,
            Method::POST,
            Method::PUT,
            Method::DELETE,
            Method::OPTIONS,
        ])
        .allow_headers([
            header::CONTENT_TYPE,
            header::AUTHORIZATION,
            header::ACCEPT,
            header::COOKIE,
        ])
        .allow_credentials(true);

    let api_router = Router::new()
        // Health & Home
        .route("/health", get(handlers::health_check))
        .route("/home", get(handlers::get_home_data))
        // Auth
        .route("/auth/register", post(handlers::register))
        .route("/auth/login", post(handlers::login))
        .route("/auth/logout", post(handlers::logout))
        .route("/auth/me", get(handlers::get_me))
        // Feed & Suggestions
        .route("/feed", get(handlers::get_feed))
        .route("/suggestions/filter", post(handlers::filter_suggestions))
        // Posts
        .route("/posts/save", post(handlers::save_post))
        .route("/posts/react", post(handlers::toggle_reaction))
        .route("/posts/bookmark", post(handlers::toggle_bookmark))
        .route("/posts/pin", post(handlers::toggle_pin_post))
        .route("/posts/comments", post(handlers::add_comment))
        .route("/posts/comments/:comment_id", delete(handlers::delete_comment))
        .route("/posts/polls/vote", post(handlers::vote_poll))
        .route("/posts/:post_id", get(handlers::get_single_post).delete(handlers::delete_post))
        // Profiles & Search
        .route("/profile/:username", get(handlers::get_profile))
        .route("/search", get(handlers::search_handler))
        // Bookmarks & Lists
        .route("/bookmarks", get(handlers::get_bookmarks))
        .route("/lists", get(handlers::get_lists).post(handlers::save_list))
        .route("/lists/members", post(handlers::toggle_list_member))
        .route("/lists/follow", post(handlers::toggle_follow))
        .route("/lists/:list_id", get(handlers::get_list_detail).delete(handlers::delete_list))
        // Messenger
        .route("/messenger/contacts", get(handlers::get_messenger_contacts))
        .route("/messenger/messages/:user_id", get(handlers::get_messages_with_user))
        .route("/messenger/send", post(handlers::send_message))
        .route("/messenger/message/:message_id", delete(handlers::delete_message))
        // Notifications
        .route("/notifications", get(handlers::get_notifications))
        // Payments, Subscriptions, Invoices & Withdrawals
        .route("/payments/initiate", post(handlers::initiate_payment))
        .route("/invoices/:id", get(handlers::get_invoice_detail))
        .route("/subscriptions/:sub_id/cancel", post(handlers::cancel_subscription))
        .route("/withdrawals/request", post(handlers::request_withdrawal))
        // Settings
        .route("/settings", get(handlers::get_settings_data))
        .route("/settings/profile", post(handlers::update_profile_settings))
        .route("/settings/rates", post(handlers::update_rates_settings))
        .route("/settings/account", post(handlers::update_account_settings))
        .route("/settings/privacy", post(handlers::update_privacy_settings))
        .route("/settings/verify", post(handlers::submit_verification_request))
        // Media Vault & Uploads
        .route("/vault", get(handlers::get_vault_items))
        .route("/vault/:att_id", delete(handlers::delete_vault_item))
        .route("/attachment/upload", post(handlers::upload_attachment))
        // Reels, Stories & Streams
        .route("/reels", get(handlers::get_reels).post(handlers::create_reel))
        .route("/reels/comments", post(handlers::add_reel_comment))
        .route("/reels/:reel_id", delete(handlers::delete_reel))
        .route("/reels/:reel_id/comments", get(handlers::get_reel_comments))
        .route("/stories", get(handlers::get_stories).post(handlers::create_story))
        .route("/streams", get(handlers::get_streams).post(handlers::save_stream))
        .route("/streams/comment", post(handlers::add_stream_comment))
        .route("/streams/:stream_id", get(handlers::get_stream_detail))
        .route("/streams/:stream_id/stop", post(handlers::stop_stream))
        // Public Pages, Contact, Reports, AI & Admin
        .route("/pages/:slug", get(handlers::get_public_page))
        .route("/contact", post(handlers::send_contact_message))
        .route("/report", post(handlers::submit_report))
        .route("/ai/suggest", post(handlers::generate_ai_suggestion))
        .route("/admin/dashboard", get(handlers::get_admin_dashboard))
        .route("/admin/withdrawals/:withdrawal_id", post(handlers::handle_admin_withdrawal))
        .route("/admin/reports/:report_id", post(handlers::handle_admin_report))
        .route("/admin/verifications/:verify_id", post(handlers::handle_admin_verification));

    Router::new()
        .nest("/api", api_router)
        .nest_service("/uploads", ServeDir::new(&state.uploads_dir))
        .nest_service("/img", ServeDir::new(format!("{}/img", state.public_dir)))
        .nest_service("/fonts", ServeDir::new(format!("{}/fonts", state.public_dir)))
        .layer(DefaultBodyLimit::max(50 * 1024 * 1024))
        .layer(cors)
        .with_state(state)
}

#[tokio::main]
async fn main() {
    tracing_subscriber::fmt()
        .with_env_filter(
            std::env::var("RUST_LOG").unwrap_or_else(|_| "justfans_backend=info,tower_http=info".into()),
        )
        .init();

    let db_path = std::env::var("DATABASE_PATH").unwrap_or_else(|_| "justfans.sqlite".into());
    let uploads_dir = std::env::var("UPLOADS_DIR").unwrap_or_else(|_| "./uploads".into());
    let public_dir = std::env::var("PUBLIC_DIR").unwrap_or_else(|_| "../public".into());
    let port: u16 = std::env::var("PORT")
        .ok()
        .and_then(|p| p.parse().ok())
        .unwrap_or(8080);

    std::fs::create_dir_all(&uploads_dir).expect("Failed to create uploads directory");

    let conn = init_db(&db_path).expect("Failed to initialize SQLite database");
    let state = AppState {
        db: Arc::new(Mutex::new(conn)),
        uploads_dir,
        public_dir,
    };

    let app = build_app(state);
    let addr = std::net::SocketAddr::from(([0, 0, 0, 0], port));
    tracing::info!("JustFans Rust Backend listening on http://{}", addr);

    let listener = tokio::net::TcpListener::bind(addr).await.unwrap();
    axum::serve(listener, app).await.unwrap();
}

#[cfg(test)]
mod tests {
    use super::*;
    use axum::{
        body::Body,
        http::{Request, StatusCode},
    };
    use tower::ServiceExt;

    fn setup_test_app() -> Router {
        let conn = init_db(":memory:").expect("in-memory db init");
        let tmp_dir = std::env::temp_dir().join("justfans_test_uploads");
        let _ = std::fs::create_dir_all(&tmp_dir);
        let state = AppState {
            db: Arc::new(Mutex::new(conn)),
            uploads_dir: tmp_dir.to_string_lossy().to_string(),
            public_dir: "../public".to_string(),
        };
        build_app(state)
    }

    async fn body_json(resp: axum::response::Response) -> serde_json::Value {
        let bytes = axum::body::to_bytes(resp.into_body(), usize::MAX)
            .await
            .unwrap();
        serde_json::from_slice(&bytes).unwrap()
    }

    #[tokio::test]
    async fn test_health_and_home_seeded() {
        let app = setup_test_app();
        let res = app
            .clone()
            .oneshot(Request::builder().uri("/api/health").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(res.status(), StatusCode::OK);
        let json = body_json(res).await;
        assert_eq!(json["status"], "ok");

        let home_res = app
            .oneshot(Request::builder().uri("/api/home").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(home_res.status(), StatusCode::OK);
        let home_json = body_json(home_res).await;
        assert_eq!(home_json["success"], true);
        assert!(home_json["featured_members"].as_array().unwrap().len() >= 4);
    }

    #[tokio::test]
    async fn test_register_login_and_me() {
        let app = setup_test_app();

        // Register a new user
        let reg_req = Request::builder()
            .method("POST")
            .uri("/api/auth/register")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "name": "Test Creator",
                    "username": "testcreator",
                    "email": "test@creator.io",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();

        let reg_res = app.clone().oneshot(reg_req).await.unwrap();
        assert_eq!(reg_res.status(), StatusCode::CREATED);
        let reg_json = body_json(reg_res).await;
        let token = reg_json["token"].as_str().unwrap().to_string();
        assert_eq!(reg_json["user"]["username"], "testcreator");

        // Duplicate registration should fail with 422
        let dup_req = Request::builder()
            .method("POST")
            .uri("/api/auth/register")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "name": "Test Creator",
                    "username": "testcreator",
                    "email": "test@creator.io",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();
        let dup_res = app.clone().oneshot(dup_req).await.unwrap();
        assert_eq!(dup_res.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // Fetch /api/auth/me with token
        let me_req = Request::builder()
            .uri("/api/auth/me")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())
            .unwrap();
        let me_res = app.clone().oneshot(me_req).await.unwrap();
        assert_eq!(me_res.status(), StatusCode::OK);
        let me_json = body_json(me_res).await;
        assert_eq!(me_json["user"]["email"], "test@creator.io");
    }

    #[tokio::test]
    async fn test_posts_ppv_unlock_and_interactions() {
        let app = setup_test_app();

        // Login as Alex (user 4)
        let login_req = Request::builder()
            .method("POST")
            .uri("/api/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "email": "alex@justfans.local",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();
        let login_res = app.clone().oneshot(login_req).await.unwrap();
        assert_eq!(login_res.status(), StatusCode::OK);
        let login_json = body_json(login_res).await;
        let token = login_json["token"].as_str().unwrap().to_string();

        // Post 2 is a $19.99 PPV post by Aria. Check that it starts locked for Alex
        let p2_req = Request::builder()
            .uri("/api/posts/2")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())
            .unwrap();
        let p2_res = app.clone().oneshot(p2_req).await.unwrap();
        let p2_json = body_json(p2_res).await;
        assert_eq!(p2_json["post"]["is_unlocked"], false);
        assert!(p2_json["post"]["text"].as_str().unwrap().contains("Exclusive locked content"));

        // Unlock Post 2 via /api/payments/initiate
        let unlock_req = Request::builder()
            .method("POST")
            .uri("/api/payments/initiate")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "payment_type": "post-unlock",
                    "post_id": 2,
                    "provider": "credit"
                })
                .to_string(),
            ))
            .unwrap();
        let unlock_res = app.clone().oneshot(unlock_req).await.unwrap();
        assert_eq!(unlock_res.status(), StatusCode::OK);

        // Verify Post 2 is now unlocked for Alex
        let p2_after_req = Request::builder()
            .uri("/api/posts/2")
            .header("authorization", format!("Bearer {token}"))
            .body(Body::empty())
            .unwrap();
        let p2_after_res = app.clone().oneshot(p2_after_req).await.unwrap();
        let p2_after_json = body_json(p2_after_res).await;
        assert_eq!(p2_after_json["post"]["is_unlocked"], true);
        assert!(p2_after_json["post"]["text"].as_str().unwrap().contains("EXCLUSIVE PPV SET"));

        // Like Post 2 & add a comment
        let react_req = Request::builder()
            .method("POST")
            .uri("/api/posts/react")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(serde_json::json!({ "post_id": 2 }).to_string()))
            .unwrap();
        let react_res = app.clone().oneshot(react_req).await.unwrap();
        let react_json = body_json(react_res).await;
        assert_eq!(react_json["liked"], true);

        let cmt_req = Request::builder()
            .method("POST")
            .uri("/api/posts/comments")
            .header("authorization", format!("Bearer {token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "post_id": 2,
                    "message": "Worth every penny!"
                })
                .to_string(),
            ))
            .unwrap();
        let cmt_res = app.clone().oneshot(cmt_req).await.unwrap();
        assert_eq!(cmt_res.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_messenger_and_admin_permissions() {
        let app = setup_test_app();

        // Login as Aria (Admin, role_id = 1)
        let login_req = Request::builder()
            .method("POST")
            .uri("/api/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "email": "aria@justfans.local",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();
        let login_json = body_json(app.clone().oneshot(login_req).await.unwrap()).await;
        let aria_token = login_json["token"].as_str().unwrap().to_string();

        // Send a PPV message to user 4
        let msg_req = Request::builder()
            .method("POST")
            .uri("/api/messenger/send")
            .header("authorization", format!("Bearer {aria_token}"))
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "receiver_id": 4,
                    "message": "Exclusive private sketch!",
                    "price": 10.0
                })
                .to_string(),
            ))
            .unwrap();
        let msg_res = app.clone().oneshot(msg_req).await.unwrap();
        assert_eq!(msg_res.status(), StatusCode::OK);

        // Access Admin Dashboard
        let admin_req = Request::builder()
            .uri("/api/admin/dashboard")
            .header("authorization", format!("Bearer {aria_token}"))
            .body(Body::empty())
            .unwrap();
        let admin_res = app.clone().oneshot(admin_req).await.unwrap();
        assert_eq!(admin_res.status(), StatusCode::OK);
        let admin_json = body_json(admin_res).await;
        assert!(admin_json["metrics"]["total_users"].as_i64().unwrap() >= 4);
    }

    #[tokio::test]
    async fn test_edge_cases_and_error_paths() {
        let app = setup_test_app();

        // 1. Unauthorized access to protected endpoint (/api/auth/me without token) -> 401
        let unauth_res = app
            .clone()
            .oneshot(Request::builder().uri("/api/auth/me").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(unauth_res.status(), StatusCode::UNAUTHORIZED);

        // 2. Non-existent profile -> 404
        let nf_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/profile/does_not_exist_99999")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(nf_res.status(), StatusCode::NOT_FOUND);

        // 3. Login as non-admin (Alex, role_id = 2) and attempt admin access -> 403
        let login_req = Request::builder()
            .method("POST")
            .uri("/api/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "email": "alex@justfans.local",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();
        let login_json = body_json(app.clone().oneshot(login_req).await.unwrap()).await;
        let alex_token = login_json["token"].as_str().unwrap().to_string();

        let forbidden_admin = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/admin/dashboard")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(forbidden_admin.status(), StatusCode::FORBIDDEN);

        // 4. Empty post text and no attachments -> 422
        let empty_post = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/save")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "text": "   ",
                            "attachments": []
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(empty_post.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 5. Insufficient wallet balance for tip -> 422
        let huge_tip = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "payment_type": "tip",
                            "recipient_user_id": 1,
                            "amount": 999999.0,
                            "provider": "credit"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(huge_tip.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 6. Withdrawal below $20 minimum -> 422
        let small_withdraw = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/withdrawals/request")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "amount": 5.0
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(small_withdraw.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 7. Bookmarks, Lists, Reels, Stories, Streams & Public Pages
        let bm_toggle = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/bookmark")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(serde_json::json!({ "post_id": 1 }).to_string()))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(bm_toggle.status(), StatusCode::OK);

        let bm_list = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/bookmarks")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        let bm_json = body_json(bm_list).await;
        assert_eq!(bm_json["posts"].as_array().unwrap().len(), 1);

        let page_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/pages/help")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(page_res.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_security_and_regression_fixes() {
        let app = setup_test_app();

        // Login as Alex (user 4, balance $250.00)
        let login_req = Request::builder()
            .method("POST")
            .uri("/api/auth/login")
            .header("content-type", "application/json")
            .body(Body::from(
                serde_json::json!({
                    "email": "alex@justfans.local",
                    "password": "password123"
                })
                .to_string(),
            ))
            .unwrap();
        let login_json = body_json(app.clone().oneshot(login_req).await.unwrap()).await;
        let alex_token = login_json["token"].as_str().unwrap().to_string();

        // 1. Subscription price tampering: attempt to subscribe to Marcus (user 2, $9.99/mo) with amount: 0.0
        let spoof_sub = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "payment_type": "subscription",
                            "recipient_user_id": 2,
                            "months": 1,
                            "amount": 0.0,
                            "provider": "credit"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(spoof_sub.status(), StatusCode::OK);
        let spoof_json = body_json(spoof_sub).await;
        // Balance must have decreased by 9.99 (from 250.00 to 240.01), NOT 0.0
        let new_bal = spoof_json["wallet_balance"].as_f64().unwrap();
        assert!((new_bal - 240.01).abs() < 0.01, "Expected 240.01, got {new_bal}");

        // 2. Duplicate PPV post unlock rejection
        let unlock1 = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "payment_type": "post-unlock",
                            "post_id": 2,
                            "provider": "credit"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(unlock1.status(), StatusCode::OK);

        let unlock2 = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "payment_type": "post-unlock",
                            "post_id": 2,
                            "provider": "credit"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(unlock2.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 3. Poll cross-poll answer validation: voting with non-existent answer_id 9999 -> 422
        let bad_vote = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/polls/vote")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "poll_id": 1,
                            "answer_id": 9999
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(bad_vote.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 4. Stream paywall & stream-unlock: Stream 2 costs $5.00 and requires subscription (which Alex now has from step 1)
        let stream_before = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/streams/2")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        let sb_json = body_json(stream_before).await;
        assert_eq!(sb_json["stream"]["is_unlocked"], false);

        let stream_unlock = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "payment_type": "stream-unlock",
                            "stream_id": 2,
                            "provider": "credit"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(stream_unlock.status(), StatusCode::OK);

        let stream_after = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/streams/2")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        let sa_json = body_json(stream_after).await;
        assert_eq!(sa_json["stream"]["is_unlocked"], true);

        // 5. Invoice detail endpoint returns real transaction data
        let inv_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/invoices/1002")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(inv_res.status(), StatusCode::OK);
        let inv_json = body_json(inv_res).await;
        assert_eq!(inv_json["invoice"]["amount"], 25.0);
        assert_eq!(inv_json["invoice"]["tx_type"], "tip");

        // 6. Duplicate username in profile settings -> 422 (not 500)
        let dup_username = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/settings/profile")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "name": "Alex Rivera",
                            "username": "ariavance"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(dup_username.status(), StatusCode::UNPROCESSABLE_ENTITY);
    }

    #[tokio::test]
    async fn test_privacy_paywall_reels_and_moderation() {
        let app = setup_test_app();

        // 1. Public profile privacy: unauthenticated request to /api/profile/ariavance must mask email & wallet_balance
        let pub_prof_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/profile/ariavance")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(pub_prof_res.status(), StatusCode::OK);
        let pub_prof_json = body_json(pub_prof_res).await;
        assert_eq!(pub_prof_json["profile"]["email"], "");
        assert_eq!(pub_prof_json["profile"]["wallet_balance"], 0.0);
        // Unauthenticated viewer is not subscribed to Aria, so posts 1 and 2 are locked, and recent_media must be empty
        assert_eq!(pub_prof_json["recent_media"].as_array().unwrap().len(), 0);
        // Post 1 has a poll, but since viewer is unauthenticated and Aria is paid, poll must be null and text masked
        assert!(pub_prof_json["posts"][0]["poll"].is_null());
        assert!(pub_prof_json["posts"][0]["text"]
            .as_str()
            .unwrap()
            .contains("Exclusive locked content"));

        // 2. Feed filter parameter: ?filter=videos should return 0 posts (all seeded attachments are images)
        let feed_vid_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/feed?filter=videos")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        let feed_vid_json = body_json(feed_vid_res).await;
        assert_eq!(feed_vid_json["posts"].as_array().unwrap().len(), 0);

        let feed_photos_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/feed?filter=photos")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        let feed_photos_json = body_json(feed_photos_res).await;
        assert!(feed_photos_json["posts"].as_array().unwrap().len() >= 4);

        // 3. Login as Alex (user 4) & test Reels comments, bookmarking, and verification submission
        let alex_login = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/auth/login")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "email": "alex@justfans.local",
                                "password": "password123"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let alex_token = alex_login["token"].as_str().unwrap().to_string();

        // Add comment to Reel 1
        let reel_cmt_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/reels/comments")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "reel_id": 1,
                            "message": "Awesome timelapse reel!"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(reel_cmt_res.status(), StatusCode::OK);

        let get_reel_cmts = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/reels/1/comments")
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(get_reel_cmts["comments"].as_array().unwrap().len(), 1);

        // Bookmark Reel 1
        let reel_bm_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/posts/bookmark")
                        .header("authorization", format!("Bearer {alex_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(serde_json::json!({ "reel_id": 1 }).to_string()))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(reel_bm_res["bookmarked"], true);

        let get_bm_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/bookmarks")
                        .header("authorization", format!("Bearer {alex_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(get_bm_res["reels"].as_array().unwrap().len(), 1);
        assert_eq!(get_bm_res["reels"][0]["id"], 1);

        // Submit verification request & report
        let verify_req = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/settings/verify")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "files": ["/uploads/passport-demo.jpg"]
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(verify_req.status(), StatusCode::OK);

        let report_req = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/report")
                    .header("authorization", format!("Bearer {alex_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "reported_user_id": 2,
                            "post_id": 3,
                            "report_type": "Spam or misleading",
                            "details": "Test report details"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(report_req.status(), StatusCode::OK);

        // 4. Login as Admin (Aria) and approve verification & resolve report
        let aria_login = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/auth/login")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "email": "aria@justfans.local",
                                "password": "password123"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let aria_token = aria_login["token"].as_str().unwrap().to_string();

        let admin_dash = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/admin/dashboard")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(admin_dash["verifications"].as_array().unwrap().len(), 1);
        assert_eq!(admin_dash["reports"].as_array().unwrap().len(), 1);
        assert_eq!(admin_dash["reports"][0]["reporter"], "alexrivera");
        assert_eq!(admin_dash["reports"][0]["reported"], "marcusfit");

        let approve_verify = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/admin/verifications/1")
                    .header("authorization", format!("Bearer {aria_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(serde_json::json!({ "action": "approve" }).to_string()))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(approve_verify.status(), StatusCode::OK);

        let resolve_report = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/admin/reports/1")
                    .header("authorization", format!("Bearer {aria_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(serde_json::json!({ "action": "resolve" }).to_string()))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(resolve_report.status(), StatusCode::OK);

        // Verify Alex is now verified
        let alex_prof = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/profile/alexrivera")
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(alex_prof["profile"]["is_verified"], true);
    }
}

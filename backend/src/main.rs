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
        .route(
            "/posts/comments/:comment_id",
            delete(handlers::delete_comment)
                .put(handlers::edit_comment)
                .post(handlers::edit_comment),
        )
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
        .route("/lists/block", post(handlers::toggle_block_user))
        .route(
            "/lists/:list_id",
            get(handlers::get_list_detail)
                .put(handlers::rename_list)
                .delete(handlers::delete_list),
        )
        .route("/lists/:list_id/clear", post(handlers::clear_list))
        // Messenger
        .route("/messenger/contacts", get(handlers::get_messenger_contacts))
        .route("/messenger/messages/:user_id", get(handlers::get_messages_with_user))
        .route("/messenger/send", post(handlers::send_message))
        .route("/messenger/pin/:message_id", post(handlers::toggle_pin_message))
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
        .route("/vault/:att_id/archive", post(handlers::toggle_archive_vault_item))
        .route("/attachment/upload", post(handlers::upload_attachment))
        // Reels, Stories & Streams
        .route("/reels", get(handlers::get_reels).post(handlers::create_reel))
        .route("/reels/comments", post(handlers::add_reel_comment))
        .route("/reels/comments/:comment_id", delete(handlers::delete_reel_comment))
        .route("/reels/:reel_id", delete(handlers::delete_reel))
        .route("/reels/:reel_id/view", post(handlers::increment_reel_view))
        .route("/reels/:reel_id/comments", get(handlers::get_reel_comments))
        .route("/stories", get(handlers::get_stories).post(handlers::create_story))
        .route("/stories/:story_id", delete(handlers::delete_story))
        .route("/stories/:story_id/view", post(handlers::increment_story_view))
        .route("/stories/:story_id/pin", post(handlers::toggle_story_highlight))
        .route("/streams", get(handlers::get_streams).post(handlers::save_stream))
        .route("/streams/comment", post(handlers::add_stream_comment))
        .route(
            "/streams/:stream_id",
            get(handlers::get_stream_detail).delete(handlers::delete_stream),
        )
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
    let public_dir = std::env::var("PUBLIC_DIR").unwrap_or_else(|_| "../frontend/public".into());
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
    tracing::info!("HEAV3NS-Publishing Rust Backend listening on http://{}", addr);

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
            public_dir: "../frontend/public".to_string(),
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

    #[tokio::test]
    async fn test_deep_hardening_and_parity_features() {
        let app = setup_test_app();

        // 1. Referral code registration bonus (+$5.00 to referrer Aria `ARIA2026`, $100.00 default + $5.00 = $105.00 to new user)
        let reg_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/auth/register")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "name": "Chloe Fan",
                                "username": "chloefan",
                                "email": "chloe@justfans.local",
                                "password": "password123",
                                "referral_code": "ARIA2026"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let chloe_token = reg_res["token"].as_str().unwrap().to_string();
        let chloe_id = reg_res["user"]["id"].as_i64().unwrap();
        assert_eq!(reg_res["user"]["wallet_balance"], 105.0);

        // Login as Aria (user 1) & Alex (user 4)
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

        // 2. Locked post comment & poll vote enforcement:
        // Post 2 is Aria's PPV post ($14.99). First Aria posts a comment on her own Post 2.
        let _ = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/comments")
                    .header("authorization", format!("Bearer {aria_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "post_id": 2,
                            "message": "VIP comment inside locked post"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();

        // Chloe has not unlocked Post 2, so fetching Post 2 must mask its comments (`comments == []`)
        let locked_post_view = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/posts/2")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(locked_post_view["post"]["is_unlocked"], false);
        assert_eq!(
            locked_post_view["post"]["comments"]
                .as_array()
                .unwrap()
                .len(),
            0,
            "Locked post must not leak comments to non-purchasers"
        );

        // And commenting on locked Post 2 must return 403 Forbidden.
        let locked_cmt = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/comments")
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "post_id": 2,
                            "message": "Trying to comment on locked post"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(locked_cmt.status(), StatusCode::FORBIDDEN);

        // Comment on unlocked post 4 (Elena's open_profile post) & then edit comment
        let cmt_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/posts/comments")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "post_id": 4,
                                "message": "Initial comment text"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let cmt_id = cmt_res["comment"]["id"].as_i64().unwrap();

        let edit_cmt_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("PUT")
                        .uri(format!("/api/posts/comments/{cmt_id}"))
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "message": "Edited comment text!"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(edit_cmt_res["message"], "Edited comment text!");

        // 3. Follower list sync & custom list rename/clear
        let follow_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/lists/follow")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({ "target_user_id": 1 }).to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(follow_res["is_following"], true);

        // Check Aria's lists — her "Followers" list should include Chloe
        let aria_lists = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/lists")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let followers_list = aria_lists["lists"]
            .as_array()
            .unwrap()
            .iter()
            .find(|l| l["list_type"] == "followers")
            .unwrap();
        assert!(followers_list["members_count"].as_i64().unwrap() >= 2);

        // Create custom list, add member, rename, and clear
        let new_list = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/lists")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({ "name": "Top Creators" }).to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let custom_list_id = new_list["list_id"].as_i64().unwrap();

        let _ = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/lists/members")
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "list_id": custom_list_id,
                            "member_user_id": 1
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();

        let rename_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("PUT")
                        .uri(format!("/api/lists/{custom_list_id}"))
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({ "name": "VIP Creators" }).to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(rename_res["name"], "VIP Creators");

        let clear_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/lists/{custom_list_id}/clear"))
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(clear_res.status(), StatusCode::OK);

        // 4. Blocking enforcement: Aria blocks Chloe -> Chloe cannot view Aria's profile or DM her
        let block_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/lists/block")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({ "target_user_id": chloe_id }).to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(block_res["is_blocked"], true);

        let blocked_prof = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/profile/ariavance")
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(blocked_prof.status(), StatusCode::FORBIDDEN);

        let blocked_dm = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/messenger/send")
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "receiver_id": 1,
                            "message": "Hello while blocked"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(blocked_dm.status(), StatusCode::FORBIDDEN);

        // Unblock Chloe
        let unblock_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/lists/block")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({ "target_user_id": chloe_id }).to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(unblock_res["is_blocked"], false);

        // 5. Messenger pinning & purchased PPV message deletion protection
        let ppv_msg = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/messenger/send")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "receiver_id": chloe_id,
                                "message": "Exclusive PPV photo set",
                                "price": 5.0
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let ppv_msg_id = ppv_msg["message"]["id"].as_i64().unwrap();

        let pin_msg_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri(format!("/api/messenger/pin/{ppv_msg_id}"))
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(pin_msg_res["is_pinned"], true);

        // Chloe unlocks the PPV message using provider: "wallet" -> must deduct $5.00 from Chloe's $105.00 balance!
        let unlock_msg = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/payments/initiate")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "payment_type": "message-unlock",
                                "provider": "wallet",
                                "amount": 999.0, // server must ignore client amount and charge 5.00
                                "recipient_user_id": 1,
                                "message_id": ppv_msg_id
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(unlock_msg["success"], true);
        assert_eq!(
            unlock_msg["wallet_balance"], 100.0,
            "provider: 'wallet' must actually deduct $5.00 from Chloe's wallet balance"
        );

        // Aria attempts to delete the already-purchased PPV message -> must fail with 422
        let del_purchased_msg = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("DELETE")
                    .uri(format!("/api/messenger/message/{ppv_msg_id}"))
                    .header("authorization", format!("Bearer {aria_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(del_purchased_msg.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 6. Media Vault archive & in-use attachment deletion protection
        let archive_att = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/vault/att-seed-1/archive")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(archive_att["is_archived"], true);

        // Attempting to delete att-seed-1 (attached to Post 1) without removing post must return 422
        let del_in_use_att = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("DELETE")
                    .uri("/api/vault/att-seed-1")
                    .header("authorization", format!("Bearer {aria_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(del_in_use_att.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 7. Paid Reel creation, paywall masking, unlock payment & view increment
        let paid_reel = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/reels")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "caption": "Premium Backstage Reel",
                                "video_path": "/img/hero-video.mp4",
                                "price": 4.5
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let paid_reel_id = paid_reel["reel_id"].as_i64().unwrap();

        // Chloe fetches reels -> paid reel should be locked with empty video_url
        let chloe_reels = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/reels")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let locked_reel = chloe_reels["reels"]
            .as_array()
            .unwrap()
            .iter()
            .find(|r| r["id"] == paid_reel_id)
            .unwrap();
        assert_eq!(locked_reel["is_unlocked"], false);
        assert_eq!(locked_reel["video_url"], "");

        // Unlock paid reel via wallet -> balance drops from $100.00 to $95.50
        let unlock_reel = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/payments/initiate")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "payment_type": "reel-unlock",
                                "provider": "wallet",
                                "amount": 4.5,
                                "recipient_user_id": 1,
                                "reel_id": paid_reel_id
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(unlock_reel["success"], true);
        assert_eq!(unlock_reel["wallet_balance"], 95.5);

        // Verify unlocked reel now returns custom video_path "/img/hero-video.mp4"
        let unlocked_reels = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/reels")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let after_unlock_reel = unlocked_reels["reels"]
            .as_array()
            .unwrap()
            .iter()
            .find(|r| r["id"] == paid_reel_id)
            .unwrap();
        assert_eq!(after_unlock_reel["is_unlocked"], true);
        assert_eq!(after_unlock_reel["video_url"], "/img/hero-video.mp4");

        // View increment on reel
        let reel_view_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri(format!("/api/reels/{paid_reel_id}/view"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(reel_view_res["views_count"], 1);

        // 8. Story view increment & highlight pin (story 1 starts with is_highlight = 1, so toggling turns it off then back on)
        let story_unpin = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/stories/1/pin")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(story_unpin["is_highlight"], false);

        let story_pin = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/stories/1/pin")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(story_pin["is_highlight"], true);

        let story_view = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/stories/1/view")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert!(story_view["views_count"].as_i64().unwrap() >= 1);

        // 9. Locked stream chat protection: Stream 2 requires subscription + $5 PPV
        let locked_stream_cmt = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/streams/comment")
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "stream_id": 2,
                            "message": "Should fail while stream is locked"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(locked_stream_cmt.status(), StatusCode::FORBIDDEN);

        // 10. Editing a post with an active poll preserves existing poll votes when options are unchanged,
        // and scheduled posts with datetime-local format ("YYYY-MM-DDTHH:MM") are hidden from non-owners.
        let edit_post1 = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/posts/save")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "id": 1,
                                "text": "Updated caption for Post 1 without wiping poll votes!",
                                "price": 0.0,
                                "attachments": ["att-seed-1"],
                                "poll_answers": [
                                    "Cyberpunk Tokyo Rooftop",
                                    "Ethereal Forest Fantasy",
                                    "Behind-the-Scenes Vlog"
                                ]
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(
            edit_post1["post"]["poll"]["total_votes"], 1,
            "Editing post caption must preserve existing poll votes when options are unchanged"
        );

        // Create a future scheduled post using HTML datetime-local format ("2099-12-31T23:59")
        let future_post = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/posts/save")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "text": "Future scheduled drop",
                                "price": 0.0,
                                "release_date": "2099-12-31T23:59"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let future_post_id = future_post["post"]["id"].as_i64().unwrap();
        assert_eq!(future_post["post"]["release_date"], "2099-12-31 23:59:00");

        // Non-owner fetching future scheduled post gets 404
        let non_owner_future = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri(format!("/api/posts/{future_post_id}"))
                    .header("authorization", format!("Bearer {chloe_token}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(non_owner_future.status(), StatusCode::NOT_FOUND);

        // Verify Aria's settings endpoint includes Chloe in referrals list and masks billing PII on public profile
        let aria_settings = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/settings")
                        .header("authorization", format!("Bearer {aria_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(
            aria_settings["referrals"].as_array().unwrap().len(),
            1,
            "Referrals table must record referral usage and return it in /api/settings"
        );
        assert_eq!(
            aria_settings["referrals"][0]["used_by_username"],
            "chloefan"
        );
        assert_eq!(
            aria_settings["profile"]["billing_address"],
            "742 Evergreen Terrace, Suite 4B"
        );

        let public_aria = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/profile/ariavance")
                        .header("authorization", format!("Bearer {chloe_token}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert!(
            public_aria["creator"]["billing_address"].is_null(),
            "Public profile must mask billing_address PII from non-owners"
        );
    }
}

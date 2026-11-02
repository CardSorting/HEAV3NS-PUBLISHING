pub mod auth;
pub mod db;
pub mod handlers;
pub mod mcp;
pub mod models;
pub mod sharding;

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

use crate::db::{init_db, init_postgres_db, AppState};

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
            header::HeaderName::from_static("x-agent-token"),
            header::HeaderName::from_static("x-idempotency-key"),
        ])
        .expose_headers([
            header::HeaderName::from_static("x-heav3ns-shard"),
            header::HeaderName::from_static("x-heav3ns-pool"),
            header::HeaderName::from_static("x-ratelimit-throttle-mode"),
            header::HeaderName::from_static("x-ratelimit-pacing-ms"),
        ])
        .allow_credentials(true);

    let api_router = Router::new()
        // Platform: Health & Home
        .route("/health", get(handlers::platform::health_check))
        .route("/home", get(handlers::platform::get_home_data))
        // Identity: Auth
        .route("/auth/register", post(handlers::identity::register))
        .route("/auth/login", post(handlers::identity::login))
        .route("/auth/logout", post(handlers::identity::logout))
        .route("/auth/me", get(handlers::identity::get_me))
        // Discovery: Feed, Suggestions, Profiles & Search
        .route("/feed", get(handlers::discovery::get_feed))
        .route("/suggestions/filter", post(handlers::discovery::filter_suggestions))
        .route("/profile/:username", get(handlers::discovery::get_profile))
        .route("/search", get(handlers::discovery::search_handler))
        // Content: Posts, Reactions, Comments & Polls
        .route("/posts/save", post(handlers::content::save_post))
        .route("/posts/react", post(handlers::content::toggle_reaction))
        .route("/posts/bookmark", post(handlers::content::toggle_bookmark))
        .route("/posts/pin", post(handlers::content::toggle_pin_post))
        .route("/posts/comments", post(handlers::content::add_comment))
        .route(
            "/posts/comments/:comment_id",
            delete(handlers::content::delete_comment)
                .put(handlers::content::edit_comment)
                .post(handlers::content::edit_comment),
        )
        .route("/posts/polls/vote", post(handlers::content::vote_poll))
        .route(
            "/posts/:post_id",
            get(handlers::content::get_single_post).delete(handlers::content::delete_post),
        )
        // Social: Bookmarks, Lists, Graph & Notifications
        .route("/bookmarks", get(handlers::social::get_bookmarks))
        .route(
            "/lists",
            get(handlers::social::get_lists).post(handlers::social::save_list),
        )
        .route("/lists/members", post(handlers::social::toggle_list_member))
        .route("/lists/follow", post(handlers::social::toggle_follow))
        .route("/lists/block", post(handlers::social::toggle_block_user))
        .route(
            "/lists/:list_id",
            get(handlers::social::get_list_detail)
                .put(handlers::social::rename_list)
                .delete(handlers::social::delete_list),
        )
        .route("/lists/:list_id/clear", post(handlers::social::clear_list))
        .route("/notifications", get(handlers::social::get_notifications))
        // Messaging: Direct Messenger & Broadcasts
        .route("/messenger/contacts", get(handlers::messaging::get_messenger_contacts))
        .route(
            "/messenger/messages/:user_id",
            get(handlers::messaging::get_messages_with_user),
        )
        .route("/messenger/send", post(handlers::messaging::send_message))
        .route("/messenger/broadcast", post(handlers::messaging::broadcast_message))
        .route("/messenger/pin/:message_id", post(handlers::messaging::toggle_pin_message))
        .route("/messenger/message/:message_id", delete(handlers::messaging::delete_message))
        // Monetization: Payments, Subscriptions, Invoices, Withdrawals & Analytics
        .route("/analytics/overview", get(handlers::monetization::get_creator_analytics))
        .route("/payments/initiate", post(handlers::monetization::initiate_payment))
        .route("/invoices/:id", get(handlers::monetization::get_invoice_detail))
        .route(
            "/subscriptions/:sub_id/cancel",
            post(handlers::monetization::cancel_subscription),
        )
        .route("/withdrawals/request", post(handlers::monetization::request_withdrawal))
        // Identity: Settings & Verification
        .route("/settings", get(handlers::identity::get_settings_data))
        .route("/settings/profile", post(handlers::identity::update_profile_settings))
        .route("/settings/rates", post(handlers::identity::update_rates_settings))
        .route("/settings/account", post(handlers::identity::update_account_settings))
        .route("/settings/privacy", post(handlers::identity::update_privacy_settings))
        .route("/settings/verify", post(handlers::identity::submit_verification_request))
        // Governance: Agent Tokens, Shards, Autonomous Personas & Model Context Protocol (MCP)
        .route(
            "/agent/tokens",
            get(handlers::governance::get_agent_tokens)
                .post(handlers::governance::create_agent_token),
        )
        .route(
            "/agent/tokens/:token_id",
            delete(handlers::governance::delete_agent_token),
        )
        .route(
            "/agent/tokens/:token_id/revoke",
            post(handlers::governance::revoke_agent_token),
        )
        .route("/agent/shards", get(handlers::governance::get_agent_shards_telemetry))
        .route(
            "/agent/personas",
            get(handlers::governance::get_agent_personas)
                .post(handlers::governance::upsert_agent_persona),
        )
        .route(
            "/agent/personas/:persona_id/practice",
            post(handlers::governance::update_persona_practice),
        )
        .route(
            "/agent/personas/:persona_id/brief",
            post(handlers::governance::compile_persona_brief_handler),
        )
        .route(
            "/agent/personas/:persona_id/activities",
            post(handlers::governance::record_persona_activity),
        )
        .route("/mcp", post(mcp::handle_mcp_rpc).get(mcp::get_mcp_manifest))
        .route("/mcp/manifest", get(mcp::get_mcp_manifest))
        .route("/mcp/tools/:tool_name", post(mcp::call_mcp_tool_rest))
        // Media: Vault, Uploads, Reels, Stories & Streams
        .route("/vault", get(handlers::media::get_vault_items))
        .route("/vault/:att_id", delete(handlers::media::delete_vault_item))
        .route(
            "/vault/:att_id/archive",
            post(handlers::media::toggle_archive_vault_item),
        )
        .route("/attachment/upload", post(handlers::media::upload_attachment))
        .route(
            "/reels",
            get(handlers::media::get_reels).post(handlers::media::create_reel),
        )
        .route("/reels/comments", post(handlers::media::add_reel_comment))
        .route(
            "/reels/comments/:comment_id",
            delete(handlers::media::delete_reel_comment),
        )
        .route("/reels/:reel_id", delete(handlers::media::delete_reel))
        .route("/reels/:reel_id/view", post(handlers::media::increment_reel_view))
        .route("/reels/:reel_id/comments", get(handlers::media::get_reel_comments))
        .route(
            "/stories",
            get(handlers::media::get_stories).post(handlers::media::create_story),
        )
        .route("/stories/:story_id", delete(handlers::media::delete_story))
        .route("/stories/:story_id/view", post(handlers::media::increment_story_view))
        .route(
            "/stories/:story_id/pin",
            post(handlers::media::toggle_story_highlight),
        )
        .route(
            "/streams",
            get(handlers::media::get_streams).post(handlers::media::save_stream),
        )
        .route("/streams/comment", post(handlers::media::add_stream_comment))
        .route(
            "/streams/:stream_id",
            get(handlers::media::get_stream_detail).delete(handlers::media::delete_stream),
        )
        .route("/streams/:stream_id/stop", post(handlers::media::stop_stream))
        // Platform: Public Pages, Contact, Reports, AI & Admin
        .route("/pages/:slug", get(handlers::platform::get_public_page))
        .route("/contact", post(handlers::platform::send_contact_message))
        .route("/report", post(handlers::platform::submit_report))
        .route("/ai/suggest", post(handlers::platform::generate_ai_suggestion))
        .route("/admin/dashboard", get(handlers::platform::get_admin_dashboard))
        .route(
            "/admin/withdrawals/:withdrawal_id",
            post(handlers::platform::handle_admin_withdrawal),
        )
        .route(
            "/admin/reports/:report_id",
            post(handlers::platform::handle_admin_report),
        )
        .route(
            "/admin/verifications/:verify_id",
            post(handlers::platform::handle_admin_verification),
        );

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
    let postgres_url = std::env::var("HEAV3NS_DATABASE_URL")
        .or_else(|_| std::env::var("DATABASE_URL"))
        .ok()
        .filter(|s| s.starts_with("postgres://") || s.starts_with("postgresql://"));
    let postgres_pool_size: usize = std::env::var("HEAV3NS_DATABASE_POOL_SIZE")
        .ok()
        .and_then(|v| v.parse().ok())
        .unwrap_or(10);

    let uploads_dir = std::env::var("UPLOADS_DIR").unwrap_or_else(|_| "./uploads".into());
    let public_dir = std::env::var("PUBLIC_DIR").unwrap_or_else(|_| "../frontend/public".into());
    let port: u16 = std::env::var("PORT")
        .ok()
        .and_then(|p| p.parse().ok())
        .unwrap_or(8080);

    std::fs::create_dir_all(&uploads_dir).expect("Failed to create uploads directory");

    let (active_pg_url, pg_pool) = if let Some(ref url) = postgres_url {
        match init_postgres_db(url, postgres_pool_size) {
            Ok(pool) => {
                tracing::info!(
                    "Connected to PostgreSQL cluster ({}) with pool_size={}",
                    url,
                    postgres_pool_size
                );
                (Some(url.clone()), pool)
            }
            Err(err) => {
                tracing::warn!(
                    "PostgreSQL connection warning ({err}); continuing with high-speed SQLite WAL mirror"
                );
                (None, Vec::new())
            }
        }
    } else {
        (None, Vec::new())
    };

    let conn = init_db(&db_path).expect("Failed to initialize database");
    let state = AppState {
        db: Arc::new(Mutex::new(conn)),
        uploads_dir,
        public_dir,
        postgres_url: active_pg_url,
        postgres_pool_size,
        postgres_pool: Arc::new(Mutex::new(pg_pool)),
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
            postgres_url: None,
            postgres_pool_size: 4,
            postgres_pool: Arc::new(Mutex::new(Vec::new())),
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

    #[tokio::test]
    async fn test_mcp_autonomous_agent_lifecycle_and_guardrails() {
        let app = setup_test_app();

        // 1. Discover MCP Manifest (`GET /api/mcp/manifest`)
        let manifest_resp = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/mcp/manifest")
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(manifest_resp.status(), StatusCode::OK);
        let manifest = body_json(manifest_resp).await;
        assert_eq!(
            manifest["serverInfo"]["name"],
            "heav3ns-publishing-mcp-server"
        );
        assert!(manifest["tools"].as_array().unwrap().len() >= 25);

        // 2. JSON-RPC 2.0 `initialize`, `tools/list`, `resources/list`, `prompts/list`
        let init_rpc = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "jsonrpc": "2.0",
                                "id": 1,
                                "method": "initialize",
                                "params": {}
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(init_rpc["result"]["protocolVersion"], "2024-11-05");

        let tools_rpc = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "jsonrpc": "2.0",
                                "id": 2,
                                "method": "tools/list"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert!(tools_rpc["result"]["tools"].as_array().unwrap().len() >= 25);

        // 3. Autonomous Agent creates an account end-to-end via MCP `tools/call` (`account_register`)
        let reg_rpc = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "jsonrpc": "2.0",
                                "id": 3,
                                "method": "tools/call",
                                "params": {
                                    "name": "account_register",
                                    "arguments": {
                                        "name": "Nova Synth Agent",
                                        "username": "novasynth",
                                        "email": "nova@heav3ns.com",
                                        "password": "AutonomousPassword123!",
                                        "agent_name": "Nova Primary Agent",
                                        "max_spend_per_tx": 20.0,
                                        "daily_spend_limit": 35.0
                                    }
                                }
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(reg_rpc["result"]["isError"], false);
        let nova_agent_token = reg_rpc["result"]["structuredContent"]["agent_token"]
            .as_str()
            .unwrap()
            .to_string();
        let nova_jwt = reg_rpc["result"]["structuredContent"]["jwt_token"]
            .as_str()
            .unwrap()
            .to_string();
        assert!(nova_agent_token.starts_with("hp_mcp_"));

        // 4. Verify agent token authenticates via `X-Agent-Token` on both REST and MCP endpoints
        let whoami_rest = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/account_whoami")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from("{}"))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(whoami_rest["result"]["user"]["username"], "novasynth");
        assert!(whoami_rest["result"]["agent_token_id"].as_i64().is_some());
        assert_eq!(whoami_rest["result"]["max_spend_per_tx"], 20.0);
        assert_eq!(whoami_rest["result"]["daily_spend_limit"], 35.0);

        // 5. Autonomous agent configures profile, rates + promotional offer, and publishes content via MCP
        let profile_update = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/profile_update")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "name": "Nova Synth AI",
                                "bio": "Autonomous AI music & visual creator powered by MCP",
                                "location": "Neo-Tokyo",
                                "open_profile": true
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(profile_update["success"], true);

        let rates_update = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/rates_and_offers_update")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "profile_access_price": 12.99,
                                "profile_access_price_3_months": 33.0,
                                "profile_access_price_6_months": 60.0,
                                "profile_access_price_12_months": 108.0,
                                "is_offer_active": true,
                                "offer_discount_percent": 30,
                                "offer_expires_at": "2099-12-31 23:59:59"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(rates_update["success"], true);

        // Verify public profile exposes the active promotional creator_offer
        let nova_public = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/profile/novasynth")
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(
            nova_public["profile"]["creator_offer"]["discount_percent"],
            30
        );

        // Publish a post with a poll via MCP
        let post_created = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/post_create_or_update")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "text": "Hello from my autonomous MCP agent! Which synth pack should I drop next?",
                                "price": 0.0,
                                "is_pinned": true,
                                "poll_answers": ["Modular Ambient", "Cyberpunk Bass"]
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(post_created["success"], true);
        let created_post_id = post_created["result"]["post"]["id"].as_i64().unwrap();
        assert!(created_post_id > 0);

        // 6. Test Autonomous Spend Guardrails (`max_spend_per_tx` = $20, `daily_spend_limit` = $35)
        // First deposit $100 into Nova's wallet (deposits are credit additions, allowed)
        let deposit_resp = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/wallet_transact")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "payment_type": "deposit",
                                "amount": 100.0,
                                "provider": "credit"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(deposit_resp["success"], true);
        assert_eq!(deposit_resp["result"]["wallet_balance"], 200.0); // $100 welcome + $100 deposit

        // Attempt a $25 tip to Aria (`user_id = 1`) — MUST FAIL because max_spend_per_tx is $20
        let over_tx_cap = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("x-agent-token", &nova_agent_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "recipient_user_id": 1,
                            "payment_type": "tip",
                            "amount": 25.0,
                            "provider": "wallet"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            over_tx_cap.status(),
            StatusCode::UNPROCESSABLE_ENTITY,
            "Spend exceeding max_spend_per_tx ($20) must be blocked with 422"
        );

        // Two $15 tips succeed ($15 <= $20 per-tx cap, total $30 <= $35 daily cap)
        for _ in 0..2 {
            let ok_tip = app
                .clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/payments/initiate")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "recipient_user_id": 1,
                                "payment_type": "tip",
                                "amount": 15.0,
                                "provider": "wallet"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap();
            assert_eq!(ok_tip.status(), StatusCode::OK);
        }

        // Third $10 tip MUST FAIL because 24h rolling spend ($30 + $10 = $40) exceeds daily_spend_limit ($35)
        let over_daily_cap = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/payments/initiate")
                    .header("x-agent-token", &nova_agent_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "recipient_user_id": 1,
                            "payment_type": "tip",
                            "amount": 10.0,
                            "provider": "wallet"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            over_daily_cap.status(),
            StatusCode::UNPROCESSABLE_ENTITY,
            "Spend exceeding daily_spend_limit ($35) must be blocked with 422"
        );

        // 7. Test Scoped Agent Token Enforcement (read-only token cannot publish or spend)
        // First verify a non-admin agent token cannot self-escalate by minting another token
        let self_mint_attempt = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/agent/tokens")
                    .header("x-agent-token", &nova_agent_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "name": "Unauthorized Self-Minted Token",
                            "scopes": ["read", "write"]
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            self_mint_attempt.status(),
            StatusCode::FORBIDDEN,
            "Non-admin agent token must not be allowed to mint new agent tokens"
        );

        let create_ro_token = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/agent/tokens")
                        .header("authorization", format!("Bearer {nova_jwt}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "name": "Read-Only Analytics Observer",
                                "scopes": ["read"]
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let ro_raw_token = create_ro_token["agent_token"].as_str().unwrap().to_string();
        let ro_token_id = create_ro_token["token_id"].as_i64().unwrap();

        // Read-only token CAN read analytics
        let ro_analytics = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/analytics/overview")
                    .header("x-agent-token", &ro_raw_token)
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(ro_analytics.status(), StatusCode::OK);

        // Read-only token CANNOT publish a post via MCP or legacy REST (403 Forbidden)
        let ro_write_attempt = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/mcp/tools/post_create_or_update")
                    .header("x-agent-token", &ro_raw_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "text": "Unauthorized write attempt"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            ro_write_attempt.status(),
            StatusCode::FORBIDDEN,
            "Read-only agent token must be rejected with 403 on write tools"
        );

        let ro_rest_post_save = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/posts/save")
                    .header("x-agent-token", &ro_raw_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "text": "Unauthorized legacy REST post save"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            ro_rest_post_save.status(),
            StatusCode::FORBIDDEN,
            "Read-only agent token must be rejected with 403 on legacy REST POST /api/posts/save"
        );

        let ro_rest_dm = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/messenger/send")
                    .header("x-agent-token", &ro_raw_token)
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "receiver_id": 1,
                            "message": "Unauthorized legacy REST DM"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            ro_rest_dm.status(),
            StatusCode::FORBIDDEN,
            "Read-only agent token must be rejected with 403 on legacy REST POST /api/messenger/send"
        );

        // Verify GET /api/agent/tokens returns both `spent_last_24h` and `spent_today`
        let tokens_list_json = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/agent/tokens")
                        .header("x-agent-token", &ro_raw_token)
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(tokens_list_json["success"], true);
        let first_tok = &tokens_list_json["agent_tokens"][0];
        assert!(
            first_tok.get("spent_last_24h").is_some() && first_tok.get("spent_today").is_some(),
            "AgentTokenView must serialize both spent_last_24h and spent_today"
        );

        // Verify MCP `post_react` supports `reel_id` in addition to `post_id`
        let reel_react_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/post_react")
                        .header("x-agent-token", &nova_agent_token)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "reel_id": 1
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(reel_react_res["success"], true);
        assert_eq!(reel_react_res["result"]["reel_id"], 1);
        assert_eq!(reel_react_res["result"]["liked"], true);

        // 8. Test Broadcast Messaging by Aria (`user_id = 1`) to active subscribers & followers
        let aria_login = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/auth/login")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            r#"{"email":"aria@justfans.local","password":"password123"}"#,
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let aria_jwt = aria_login["token"].as_str().unwrap();

        let broadcast_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/messenger/broadcast")
                        .header("authorization", format!("Bearer {aria_jwt}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "message": "VIP drop live now for all subscribers & followers!",
                                "audience": "all",
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
        assert_eq!(broadcast_res["success"], true);
        assert!(broadcast_res["sent_count"].as_u64().unwrap() >= 1);

        // Non-admin agent token cannot revoke a DIFFERENT token (`ro_token_id`)
        let unauthorized_other_revoke = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/agent/tokens/{ro_token_id}/revoke"))
                    .header("x-agent-token", &nova_agent_token)
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            unauthorized_other_revoke.status(),
            StatusCode::FORBIDDEN,
            "Non-admin agent token must not be able to revoke another token"
        );

        // 9. Revoke the read-only token via session JWT (or self-revocation) and verify immediate 401 Unauthorized
        let revoke_resp = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/agent/tokens/{ro_token_id}/revoke"))
                    .header("authorization", format!("Bearer {nova_jwt}"))
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(revoke_resp.status(), StatusCode::OK);

        let revoked_use = app
            .clone()
            .oneshot(
                Request::builder()
                    .uri("/api/auth/me")
                    .header("x-agent-token", &ro_raw_token)
                    .body(Body::empty())
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(
            revoked_use.status(),
            StatusCode::UNAUTHORIZED,
            "Revoked agent token must be rejected immediately with 401"
        );
    }

    #[tokio::test]
    async fn test_sharding_soft_quota_personas_and_idempotency() {
        let app = setup_test_app();

        // 1. Login as Aria Vance
        let aria_login = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/auth/login")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            r#"{"email":"aria@justfans.local","password":"password123"}"#,
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        let aria_jwt = aria_login["token"].as_str().unwrap().to_string();

        // 2. Mint an Agent Token on the `premium` pool with a tight daily_action_quota = 6 units
        // so we can deterministically test smooth -> soft_paced -> cooldown_advisory transitions.
        let mint_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/agent/tokens")
                        .header("authorization", format!("Bearer {aria_jwt}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "name": "Aria Shard Pacing Agent",
                                "pool_tier": "premium",
                                "daily_action_quota": 6,
                                "max_spend_per_tx": 50.0,
                                "daily_spend_limit": 200.0
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(mint_res["success"], true);
        assert_eq!(mint_res["pool_tier"], "premium");
        assert_eq!(mint_res["daily_action_quota"], 6);
        let shard_id = mint_res["shard_id"].as_i64().unwrap();
        assert!((0..16).contains(&shard_id));
        let agent_secret = mint_res["agent_token"].as_str().unwrap().to_string();

        // 3. Call `post_create_or_update` (2.0 units) with `X-Idempotency-Key` HTTP header:
        //    2.0 / 6.0 = 33.3% utilization -> throttle_mode = "smooth"
        let first_post_resp = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/mcp/tools/post_create_or_update")
                    .header("x-agent-token", &agent_secret)
                    .header("x-idempotency-key", "idem-drop-001")
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "text": "Idempotent autonomous studio drop #1",
                            "price": 0.0
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(first_post_resp.status(), StatusCode::OK);
        assert_eq!(
            first_post_resp
                .headers()
                .get("x-heav3ns-pool")
                .unwrap()
                .to_str()
                .unwrap(),
            "premium"
        );
        assert!(first_post_resp
            .headers()
            .get("x-ratelimit-pacing-ms")
            .is_some());
        let first_post_json = body_json(first_post_resp).await;
        let created_post_id = first_post_json["result"]["post"]["id"].as_i64().unwrap();
        assert_eq!(
            first_post_json["result"]["_quota_governance"]["throttle_mode"],
            "smooth"
        );
        assert_eq!(
            first_post_json["result"]["_heav3ns_governance"]["throttle_mode"],
            "smooth"
        );
        assert_eq!(
            first_post_json["result"]["_quota_governance"]["daily_units_used"],
            2.0
        );

        // 4. Replay the exact same `X-Idempotency-Key` header: must return the cached post_id without
        //    creating a duplicate post or charging additional quota units!
        let replay_post_json = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/post_create_or_update")
                        .header("x-agent-token", &agent_secret)
                        .header("x-idempotency-key", "idem-drop-001")
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "text": "Idempotent autonomous studio drop #1",
                                "price": 0.0
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
            replay_post_json["result"]["post"]["id"].as_i64().unwrap(),
            created_post_id
        );
        assert_eq!(
            replay_post_json["result"]["_quota_governance"]["idempotent_replay"],
            true
        );
        assert_eq!(
            replay_post_json["result"]["_quota_governance"]["throttle_mode"],
            "idempotent_replay"
        );

        // 4b. Verify failed tool call with X-Idempotency-Key cleans up its in-flight reservation so retry can succeed
        let failed_idem_resp = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/mcp/tools/post_create_or_update")
                    .header("x-agent-token", &agent_secret)
                    .header("x-idempotency-key", "idem-err-recover")
                    .header("content-type", "application/json")
                    .body(Body::from(r#"{"text":"","attachments":[]}"#))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(failed_idem_resp.status(), StatusCode::UNPROCESSABLE_ENTITY);

        // 5. Call `post_create_or_update` (2.0 units) + `post_comment` (1.0 unit):
        //    total = 2.0 + 2.0 (failed attempt consumed before validation or next call) -> reaches >= 75% -> "soft_paced"
        let paced_cmt_resp = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/mcp/tools/post_comment")
                    .header("x-agent-token", &agent_secret)
                    .header("x-idempotency-key", "idem-err-recover")
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "post_id": created_post_id,
                            "message": "Autonomous studio notes on lighting setup!"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(paced_cmt_resp.status(), StatusCode::OK);
        let paced_cmt_json = body_json(paced_cmt_resp).await;
        assert_eq!(paced_cmt_json["success"], true);
        assert_eq!(
            paced_cmt_json["result"]["_quota_governance"]["throttle_mode"],
            "soft_paced"
        );
        assert!(
            paced_cmt_json["result"]["_quota_governance"]["pacing_delay_ms"]
                .as_i64()
                .unwrap()
                >= 20
        );

        // 6. Attempt `messenger_broadcast` (5.0 units): projected 10.0 > 6.0 daily limit ->
        //    smooth "cooldown_advisory" response (HTTP 200 with structured retry_after_ms, no 500/429 crash)
        let cooldown_json = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/messenger_broadcast")
                        .header("x-agent-token", &agent_secret)
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "message": "Burst broadcast test",
                                "audience": "subscribers"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(cooldown_json["result"]["throttled"], true);
        assert_eq!(cooldown_json["result"]["status"], "cooldown_advisory");
        assert!(
            cooldown_json["result"]["_quota_governance"]["retry_after_ms"]
                .as_i64()
                .unwrap()
                >= 1500
        );

        // 7. Introspection AND Read/Discovery tools (`account_whoami`, `persona_get`, `feed_read`)
        //    MUST still succeed even during cooldown_advisory!
        let whoami_during_cooldown = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/account_whoami")
                        .header("x-agent-token", &agent_secret)
                        .header("content-type", "application/json")
                        .body(Body::from("{}"))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(whoami_during_cooldown["result"]["authenticated"], true);

        let persona_get_during_cooldown = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/persona_get")
                        .header("x-agent-token", &agent_secret)
                        .header("content-type", "application/json")
                        .body(Body::from("{}"))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert!(persona_get_during_cooldown["result"]["personas"]
            .as_array()
            .is_some());

        let feed_read_during_cooldown = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri("/api/mcp/tools/feed_read")
                        .header("x-agent-token", &agent_secret)
                        .header("content-type", "application/json")
                        .body(Body::from("{}"))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert!(feed_read_during_cooldown["result"]["posts"]
            .as_array()
            .is_some());

        // 8. Test Autonomous Persona Studio lifecycle (create persona -> evolve practice revision ->
        //    optimistic concurrency check -> compile execution brief -> record immutable activity)
        let create_persona_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri("/api/agent/personas")
                    .header("authorization", format!("Bearer {aria_jwt}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "handle": "aria-cyber-curator",
                            "display_name": "Aria Cyber Curator",
                            "description": "Autonomous visual curator & VIP community host.",
                            "focus": "creator",
                            "pool_tier": "studio",
                            "autonomy_mode": "full_autonomous",
                            "engagement_cadence": "high_touch",
                            "principles": ["Never spam repetitive hooks", "Reward early poll voters"],
                            "interests": ["Neo-Tokyo lighting", "Synthwave timelapses"],
                            "methods": ["Publish poll before PPV gallery"],
                            "direction": "Initial revision direction."
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(create_persona_res.status(), StatusCode::CREATED);
        let persona_json = body_json(create_persona_res).await;
        let persona_id = persona_json["persona"]["id"].as_str().unwrap().to_string();
        assert_eq!(persona_json["persona"]["current_revision"], 1);
        assert_eq!(persona_json["persona"]["pool_tier"], "studio");

        // Evolve practice to revision #2
        let rev2_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/agent/personas/{persona_id}/practice"))
                    .header("authorization", format!("Bearer {aria_jwt}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "expected_revision": 1,
                            "focus": "doujin",
                            "direction": "Focus on multi-page cyberpunk comic storyboards and subscriber polls."
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(rev2_res.status(), StatusCode::OK);
        let rev2_json = body_json(rev2_res).await;
        assert_eq!(rev2_json["persona"]["current_revision"], 2);

        // Stale expected_revision (1 when current is 2) must return 409 Conflict
        let stale_rev_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/agent/personas/{persona_id}/practice"))
                    .header("authorization", format!("Bearer {aria_jwt}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "expected_revision": 1,
                            "direction": "Stale revision attempt"
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(stale_rev_res.status(), StatusCode::CONFLICT);

        // Record activity & compile no-inference execution brief
        let act_res = app
            .clone()
            .oneshot(
                Request::builder()
                    .method("POST")
                    .uri(format!("/api/agent/personas/{persona_id}/activities"))
                    .header("authorization", format!("Bearer {aria_jwt}"))
                    .header("content-type", "application/json")
                    .body(Body::from(
                        serde_json::json!({
                            "action_type": "post_create_or_update",
                            "target_resource_id": created_post_id.to_string(),
                            "description": "Released storyboard preview and engaged with early voters."
                        })
                        .to_string(),
                    ))
                    .unwrap(),
            )
            .await
            .unwrap();
        assert_eq!(act_res.status(), StatusCode::CREATED);

        let brief_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .method("POST")
                        .uri(format!("/api/agent/personas/{persona_id}/brief"))
                        .header("authorization", format!("Bearer {aria_jwt}"))
                        .header("content-type", "application/json")
                        .body(Body::from(
                            serde_json::json!({
                                "intent": "Announce Friday night VIP livestream and Q&A",
                                "target_audience": "VIP tier subscribers"
                            })
                            .to_string(),
                        ))
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(brief_res["success"], true);
        assert_eq!(brief_res["brief"]["practice_revision"], 2);
        assert!(brief_res["brief"]["instructions"]
            .as_str()
            .unwrap()
            .contains("Aria Cyber Curator"));

        // Verify GET /api/agent/shards returns 16 virtual shards and 3 pool tiers
        let shards_res = body_json(
            app.clone()
                .oneshot(
                    Request::builder()
                        .uri("/api/agent/shards")
                        .header("authorization", format!("Bearer {aria_jwt}"))
                        .body(Body::empty())
                        .unwrap(),
                )
                .await
                .unwrap(),
        )
        .await;
        assert_eq!(shards_res["success"], true);
        assert_eq!(
            shards_res["telemetry"]["shards"].as_array().unwrap().len(),
            16
        );
        assert_eq!(
            shards_res["telemetry"]["pools"].as_array().unwrap().len(),
            3
        );
    }

    #[test]
    fn test_postgres_cluster_pool_and_schema_migration() {
        let pg_url = std::env::var("HEAV3NS_DATABASE_URL")
            .unwrap_or_else(|_| "postgres://postgres@127.0.0.1:54329/heav3ns_publishing".into());

        // Connect to local PostgreSQL cluster if running and verify full 28-table DDL + multi-persona sync
        if let Ok(pool) = init_postgres_db(&pg_url, 4) {
            assert!(!pool.is_empty());
            let conn = init_db(":memory:").expect("sqlite init");
            let state = AppState {
                db: Arc::new(Mutex::new(conn)),
                uploads_dir: "./uploads".into(),
                public_dir: "../frontend/public".into(),
                postgres_url: Some(pg_url),
                postgres_pool_size: 4,
                postgres_pool: Arc::new(Mutex::new(pool)),
            };
            assert_eq!(state.db_engine_label(), "postgresql_pooled_sharded");

            let table_count = state.with_postgres(|pg| {
                let row = pg.query_one(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'",
                    &[],
                )?;
                Ok(row.get::<_, i64>(0))
            });
            assert!(
                table_count.unwrap_or(0) >= 28,
                "PostgreSQL public schema must contain all 28 migrated tables"
            );

            // Verify a single creator (user_id = 2) can sync multiple autonomous personas into PostgreSQL
            let p1 = crate::sharding::AgentPersonaView {
                id: "persona-aria-test-1".into(),
                user_id: 2,
                handle: "aria-test-alpha".into(),
                display_name: "Aria Alpha".into(),
                description: "First test persona for multi-persona PostgreSQL residency".into(),
                focus: "creator".into(),
                shard_id: 3,
                pool_tier: "premium".into(),
                autonomy_mode: "autonomous_co_pilot".into(),
                engagement_cadence: "balanced".into(),
                current_revision: 1,
                practices: vec![],
                activities: vec![],
                created_at: "2026-01-01T00:00:00Z".into(),
                updated_at: "2026-01-01T00:00:00Z".into(),
            };
            let p2 = crate::sharding::AgentPersonaView {
                id: "persona-aria-test-2".into(),
                user_id: 2,
                handle: "aria-test-beta".into(),
                display_name: "Aria Beta".into(),
                description: "Second test persona for the same creator user_id in PostgreSQL".into(),
                focus: "studio".into(),
                shard_id: 11,
                pool_tier: "studio".into(),
                autonomy_mode: "full_autonomous".into(),
                engagement_cadence: "high_touch".into(),
                current_revision: 2,
                practices: vec![],
                activities: vec![],
                created_at: "2026-01-01T00:00:00Z".into(),
                updated_at: "2026-01-01T00:00:00Z".into(),
            };
            state.sync_persona_to_postgres(&p1);
            state.sync_persona_to_postgres(&p2);

            let aria_pg_personas = state.with_postgres(|pg| {
                let row = pg.query_one(
                    "SELECT COUNT(*) FROM agent_personas WHERE user_id = 2",
                    &[],
                )?;
                Ok(row.get::<_, i64>(0))
            });
            assert!(
                aria_pg_personas.unwrap_or(0) >= 2,
                "PostgreSQL agent_personas must allow multiple personas per creator user_id"
            );
        }
    }
}


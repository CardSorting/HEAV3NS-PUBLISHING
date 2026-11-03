-- ============================================================================
-- HEAV3NS-Publishing Authoritative PostgreSQL Schema & Sharded Agent Governance
-- Migration: 001_heav3ns_publishing_postgres.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role_id BIGINT NOT NULL DEFAULT 2,
    bio TEXT NOT NULL DEFAULT '',
    location TEXT NOT NULL DEFAULT '',
    website TEXT NOT NULL DEFAULT '',
    avatar TEXT NOT NULL DEFAULT '/img/default-avatar.jpg',
    cover TEXT NOT NULL DEFAULT '/img/default-cover.png',
    birthdate TEXT,
    gender_id BIGINT DEFAULT 1,
    gender_pronoun TEXT DEFAULT 'they/them',
    profile_access_price DOUBLE PRECISION NOT NULL DEFAULT 15.0,
    profile_access_price_3_months DOUBLE PRECISION NOT NULL DEFAULT 12.0,
    profile_access_price_6_months DOUBLE PRECISION NOT NULL DEFAULT 10.0,
    profile_access_price_12_months DOUBLE PRECISION NOT NULL DEFAULT 8.0,
    public_profile BIGINT NOT NULL DEFAULT 1,
    paid_profile BIGINT NOT NULL DEFAULT 1,
    open_profile BIGINT NOT NULL DEFAULT 0,
    enable_2fa BIGINT NOT NULL DEFAULT 0,
    enable_geoblocking BIGINT NOT NULL DEFAULT 0,
    identity_verified_at TEXT,
    email_verified_at TEXT,
    billing_address TEXT,
    first_name TEXT,
    last_name TEXT,
    city TEXT,
    country TEXT,
    state TEXT,
    postcode TEXT,
    referral_code TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS wallets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    total DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    updated_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS posts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    text TEXT NOT NULL DEFAULT '',
    price DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    status BIGINT NOT NULL DEFAULT 1,
    is_pinned BIGINT NOT NULL DEFAULT 0,
    release_date TEXT,
    expire_date TEXT,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS attachments (
    id TEXT PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    post_id BIGINT REFERENCES posts(id) ON DELETE SET NULL,
    message_id BIGINT,
    filename TEXT NOT NULL,
    file_url TEXT NOT NULL,
    file_type TEXT NOT NULL DEFAULT 'image',
    file_size BIGINT NOT NULL DEFAULT 0,
    is_vault BIGINT NOT NULL DEFAULT 1,
    is_archived BIGINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS post_comments (
    id BIGSERIAL PRIMARY KEY,
    post_id BIGINT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS reactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reaction_type TEXT NOT NULL DEFAULT 'like',
    post_id BIGINT REFERENCES posts(id) ON DELETE CASCADE,
    post_comment_id BIGINT REFERENCES post_comments(id) ON DELETE CASCADE,
    reel_id BIGINT,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS polls (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    post_id BIGINT NOT NULL UNIQUE REFERENCES posts(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS poll_answers (
    id BIGSERIAL PRIMARY KEY,
    poll_id BIGINT NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    answer TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS poll_user_answers (
    id BIGSERIAL PRIMARY KEY,
    poll_id BIGINT NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    answer_id BIGINT NOT NULL REFERENCES poll_answers(id) ON DELETE CASCADE,
    UNIQUE(poll_id, user_id)
);

CREATE TABLE IF NOT EXISTS reels (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    caption TEXT NOT NULL DEFAULT '',
    video_url TEXT NOT NULL,
    cover_url TEXT NOT NULL DEFAULT '/img/default-cover.png',
    price DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    views_count BIGINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS user_bookmarks (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    post_id BIGINT REFERENCES posts(id) ON DELETE CASCADE,
    reel_id BIGINT REFERENCES reels(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')),
    UNIQUE(user_id, post_id),
    UNIQUE(user_id, reel_id)
);

CREATE TABLE IF NOT EXISTS user_lists (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    list_type TEXT NOT NULL DEFAULT 'custom',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS user_list_members (
    id BIGSERIAL PRIMARY KEY,
    list_id BIGINT NOT NULL REFERENCES user_lists(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')),
    UNIQUE(list_id, user_id)
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGSERIAL PRIMARY KEY,
    sender_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipient_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount DOUBLE PRECISION NOT NULL DEFAULT 15.0,
    provider TEXT NOT NULL DEFAULT 'credit',
    status TEXT NOT NULL DEFAULT 'completed',
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS transactions (
    id BIGSERIAL PRIMARY KEY,
    sender_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipient_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    tx_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'approved',
    amount DOUBLE PRECISION NOT NULL,
    currency TEXT NOT NULL DEFAULT 'USD',
    payment_provider TEXT NOT NULL DEFAULT 'credit',
    post_id BIGINT REFERENCES posts(id) ON DELETE SET NULL,
    message_id BIGINT,
    stream_id BIGINT,
    reel_id BIGINT,
    invoice_id BIGINT,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS withdrawals (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount DOUBLE PRECISION NOT NULL,
    status TEXT NOT NULL DEFAULT 'requested',
    payment_method TEXT NOT NULL DEFAULT 'Bank Transfer',
    payment_identifier TEXT NOT NULL DEFAULT '',
    message TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS user_messages (
    id BIGSERIAL PRIMARY KEY,
    sender_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    price DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    is_seen BIGINT NOT NULL DEFAULT 0,
    is_pinned BIGINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS notifications (
    id TEXT PRIMARY KEY,
    from_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    to_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    notification_type TEXT NOT NULL,
    post_id BIGINT REFERENCES posts(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read BIGINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS reel_comments (
    id BIGSERIAL PRIMARY KEY,
    reel_id BIGINT NOT NULL REFERENCES reels(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS stories (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    media_url TEXT NOT NULL DEFAULT '',
    media_type TEXT NOT NULL DEFAULT 'image',
    caption TEXT NOT NULL DEFAULT '',
    bg_preset TEXT NOT NULL DEFAULT 'gradient-purple',
    is_highlight BIGINT NOT NULL DEFAULT 0,
    views_count BIGINT NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS streams (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    poster TEXT NOT NULL DEFAULT '/img/live-stream-cover.svg',
    price DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    requires_subscription BIGINT NOT NULL DEFAULT 0,
    is_public BIGINT NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'in-progress',
    rtmp_server TEXT NOT NULL DEFAULT 'rtmp://live.justfans.local/app',
    rtmp_key TEXT NOT NULL,
    hls_link TEXT NOT NULL DEFAULT '/img/video-loading-spinner.mp4',
    viewer_count BIGINT NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS stream_messages (
    id BIGSERIAL PRIMARY KEY,
    stream_id BIGINT NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS public_pages (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    short_title TEXT NOT NULL,
    content TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGSERIAL PRIMARY KEY,
    email TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS user_reports (
    id BIGSERIAL PRIMARY KEY,
    from_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reported_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    post_id BIGINT REFERENCES posts(id) ON DELETE SET NULL,
    report_type TEXT NOT NULL DEFAULT 'Inappropriate content',
    details TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'received',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS user_verifies (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending',
    files TEXT NOT NULL DEFAULT '[]',
    rejection_reason TEXT,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS referrals (
    id BIGSERIAL PRIMARY KEY,
    referrer_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referred_user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    referral_code TEXT NOT NULL,
    bonus_amount DOUBLE PRECISION NOT NULL DEFAULT 5.0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS story_views (
    id BIGSERIAL PRIMARY KEY,
    story_id BIGINT NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    viewer_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')),
    UNIQUE(story_id, viewer_user_id)
);

CREATE TABLE IF NOT EXISTS creator_offers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    old_profile_access_price DOUBLE PRECISION NOT NULL DEFAULT 15.0,
    old_profile_access_price_3_months DOUBLE PRECISION NOT NULL DEFAULT 12.0,
    old_profile_access_price_6_months DOUBLE PRECISION NOT NULL DEFAULT 10.0,
    old_profile_access_price_12_months DOUBLE PRECISION NOT NULL DEFAULT 8.0,
    discount_percent BIGINT NOT NULL DEFAULT 25,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

-- ============================================================================
-- Sharded Agent Tokens, Relay Pools, Soft Action Quotas & Autonomous Personas
-- ============================================================================

CREATE TABLE IF NOT EXISTS agent_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    token_prefix TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    scopes TEXT NOT NULL DEFAULT '["read","write","engage","monetize","messages","wallet"]',
    max_spend_per_tx DOUBLE PRECISION NOT NULL DEFAULT 50.0,
    daily_spend_limit DOUBLE PRECISION NOT NULL DEFAULT 200.0,
    pool_tier TEXT NOT NULL DEFAULT 'central',
    shard_id BIGINT NOT NULL DEFAULT 0,
    daily_action_quota BIGINT NOT NULL DEFAULT 600,
    hourly_burst_capacity DOUBLE PRECISION NOT NULL DEFAULT 120.0,
    refill_rate_per_sec DOUBLE PRECISION NOT NULL DEFAULT 2.5,
    is_active BIGINT NOT NULL DEFAULT 1,
    last_used_at TEXT,
    expires_at TEXT,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS agent_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    agent_token_id BIGINT REFERENCES agent_tokens(id) ON DELETE SET NULL,
    tool_name TEXT NOT NULL,
    resource_type TEXT NOT NULL DEFAULT 'account',
    resource_id TEXT,
    status TEXT NOT NULL DEFAULT 'success',
    amount_spent DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    action_units DOUBLE PRECISION NOT NULL DEFAULT 1.0,
    shard_id BIGINT NOT NULL DEFAULT 0,
    pool_tier TEXT NOT NULL DEFAULT 'central',
    throttle_mode TEXT NOT NULL DEFAULT 'smooth',
    pacing_delay_ms BIGINT NOT NULL DEFAULT 0,
    idempotency_key TEXT,
    details TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS agent_quota_buckets (
    agent_token_id BIGINT PRIMARY KEY REFERENCES agent_tokens(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    shard_id BIGINT NOT NULL DEFAULT 0,
    pool_tier TEXT NOT NULL DEFAULT 'central',
    available_tokens DOUBLE PRECISION NOT NULL DEFAULT 120.0,
    burst_capacity DOUBLE PRECISION NOT NULL DEFAULT 120.0,
    refill_rate_per_sec DOUBLE PRECISION NOT NULL DEFAULT 2.5,
    daily_quota_limit BIGINT NOT NULL DEFAULT 600,
    daily_units_used DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    daily_window_date TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD')),
    soft_throttled_count BIGINT NOT NULL DEFAULT 0,
    deferred_count BIGINT NOT NULL DEFAULT 0,
    last_refill_epoch_ms BIGINT NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

CREATE TABLE IF NOT EXISTS agent_action_idempotency (
    idempotency_key TEXT NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tool_name TEXT NOT NULL,
    request_hash TEXT NOT NULL DEFAULT '',
    response_json TEXT NOT NULL,
    updated_at_ms BIGINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')),
    PRIMARY KEY (user_id, idempotency_key)
);

ALTER TABLE agent_action_idempotency ADD COLUMN IF NOT EXISTS request_hash TEXT NOT NULL DEFAULT '';
ALTER TABLE agent_action_idempotency ADD COLUMN IF NOT EXISTS updated_at_ms BIGINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS agent_personas (
    id TEXT PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    handle TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    focus TEXT NOT NULL DEFAULT 'creator',
    shard_id BIGINT NOT NULL DEFAULT 0,
    pool_tier TEXT NOT NULL DEFAULT 'central',
    autonomy_mode TEXT NOT NULL DEFAULT 'autonomous_co_pilot',
    engagement_cadence TEXT NOT NULL DEFAULT 'balanced',
    current_revision BIGINT NOT NULL DEFAULT 1,
    practices_json TEXT NOT NULL DEFAULT '[]',
    activities_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')),
    updated_at TEXT NOT NULL DEFAULT (to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS'))
);

ALTER TABLE agent_personas DROP CONSTRAINT IF EXISTS agent_personas_user_id_key;

CREATE INDEX IF NOT EXISTS idx_agent_audit_token_created ON agent_audit_logs (agent_token_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_agent_audit_shard ON agent_audit_logs (shard_id, pool_tier);
CREATE INDEX IF NOT EXISTS idx_posts_user_status ON posts (user_id, status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_sender_recipient ON transactions (sender_user_id, recipient_user_id, status);


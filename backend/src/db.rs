use rusqlite::{params, Connection};
use std::sync::{Arc, Mutex};

use crate::models::{Attachment, PollAnswerView, PollView, PostCommentView, PostView, UserPublicProfile};

#[derive(Clone)]
pub struct AppState {
    pub db: Arc<Mutex<Connection>>,
    pub uploads_dir: String,
    pub public_dir: String,
}

pub fn init_db(db_path: &str) -> Result<Connection, rusqlite::Error> {
    let conn = if db_path == ":memory:" {
        Connection::open_in_memory()?
    } else {
        Connection::open(db_path)?
    };

    conn.execute_batch("PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;")?;

    conn.execute_batch(
        r#"
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            role_id INTEGER NOT NULL DEFAULT 2,
            bio TEXT NOT NULL DEFAULT '',
            location TEXT NOT NULL DEFAULT '',
            website TEXT NOT NULL DEFAULT '',
            avatar TEXT NOT NULL DEFAULT '/img/default-avatar.jpg',
            cover TEXT NOT NULL DEFAULT '/img/default-cover.png',
            birthdate TEXT,
            gender_id INTEGER DEFAULT 1,
            gender_pronoun TEXT DEFAULT 'they/them',
            profile_access_price REAL NOT NULL DEFAULT 15.0,
            profile_access_price_3_months REAL NOT NULL DEFAULT 12.0,
            profile_access_price_6_months REAL NOT NULL DEFAULT 10.0,
            profile_access_price_12_months REAL NOT NULL DEFAULT 8.0,
            public_profile INTEGER NOT NULL DEFAULT 1,
            paid_profile INTEGER NOT NULL DEFAULT 1,
            open_profile INTEGER NOT NULL DEFAULT 0,
            enable_2fa INTEGER NOT NULL DEFAULT 0,
            enable_geoblocking INTEGER NOT NULL DEFAULT 0,
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
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS wallets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            total REAL NOT NULL DEFAULT 0.0,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            text TEXT NOT NULL DEFAULT '',
            price REAL NOT NULL DEFAULT 0.0,
            status INTEGER NOT NULL DEFAULT 1,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            release_date TEXT,
            expire_date TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS attachments (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            post_id INTEGER REFERENCES posts(id) ON DELETE SET NULL,
            message_id INTEGER,
            filename TEXT NOT NULL,
            file_url TEXT NOT NULL,
            file_type TEXT NOT NULL DEFAULT 'image',
            file_size INTEGER NOT NULL DEFAULT 0,
            is_vault INTEGER NOT NULL DEFAULT 1,
            is_archived INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS post_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS reactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            reaction_type TEXT NOT NULL DEFAULT 'like',
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            post_comment_id INTEGER REFERENCES post_comments(id) ON DELETE CASCADE,
            reel_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS polls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            post_id INTEGER NOT NULL UNIQUE REFERENCES posts(id) ON DELETE CASCADE,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS poll_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
            answer TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS poll_user_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            answer_id INTEGER NOT NULL REFERENCES poll_answers(id) ON DELETE CASCADE,
            UNIQUE(poll_id, user_id)
        );

        CREATE TABLE IF NOT EXISTS user_bookmarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            reel_id INTEGER REFERENCES reels(id) ON DELETE CASCADE,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(user_id, post_id),
            UNIQUE(user_id, reel_id)
        );

        CREATE TABLE IF NOT EXISTS user_lists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            list_type TEXT NOT NULL DEFAULT 'custom',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_list_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            list_id INTEGER NOT NULL REFERENCES user_lists(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(list_id, user_id)
        );

        CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            recipient_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            amount REAL NOT NULL DEFAULT 15.0,
            provider TEXT NOT NULL DEFAULT 'credit',
            status TEXT NOT NULL DEFAULT 'completed',
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            recipient_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            tx_type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'approved',
            amount REAL NOT NULL,
            currency TEXT NOT NULL DEFAULT 'USD',
            payment_provider TEXT NOT NULL DEFAULT 'credit',
            post_id INTEGER REFERENCES posts(id) ON DELETE SET NULL,
            message_id INTEGER,
            stream_id INTEGER,
            invoice_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS withdrawals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            amount REAL NOT NULL,
            status TEXT NOT NULL DEFAULT 'requested',
            payment_method TEXT NOT NULL DEFAULT 'Bank Transfer',
            payment_identifier TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            price REAL NOT NULL DEFAULT 0.0,
            is_seen INTEGER NOT NULL DEFAULT 0,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id TEXT PRIMARY KEY,
            from_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            notification_type TEXT NOT NULL,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS reels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            caption TEXT NOT NULL DEFAULT '',
            video_url TEXT NOT NULL,
            cover_url TEXT NOT NULL DEFAULT '/img/default-cover.png',
            price REAL NOT NULL DEFAULT 0.0,
            views_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS reel_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reel_id INTEGER NOT NULL REFERENCES reels(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS stories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            media_url TEXT NOT NULL DEFAULT '',
            media_type TEXT NOT NULL DEFAULT 'image',
            caption TEXT NOT NULL DEFAULT '',
            bg_preset TEXT NOT NULL DEFAULT 'gradient-purple',
            is_highlight INTEGER NOT NULL DEFAULT 0,
            views_count INTEGER NOT NULL DEFAULT 0,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS streams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            poster TEXT NOT NULL DEFAULT '/img/live-stream-cover.svg',
            price REAL NOT NULL DEFAULT 0.0,
            requires_subscription INTEGER NOT NULL DEFAULT 0,
            is_public INTEGER NOT NULL DEFAULT 1,
            status TEXT NOT NULL DEFAULT 'in-progress',
            rtmp_server TEXT NOT NULL DEFAULT 'rtmp://live.justfans.local/app',
            rtmp_key TEXT NOT NULL,
            hls_link TEXT NOT NULL DEFAULT '/img/video-loading-spinner.mp4',
            viewer_count INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS stream_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            stream_id INTEGER NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS public_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            short_title TEXT NOT NULL,
            content TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS contact_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            reported_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            post_id INTEGER REFERENCES posts(id) ON DELETE SET NULL,
            report_type TEXT NOT NULL DEFAULT 'Inappropriate content',
            details TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'received',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_verifies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT 'pending',
            files TEXT NOT NULL DEFAULT '[]',
            rejection_reason TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        "#,
    )?;

    seed_db(&conn)?;

    Ok(conn)
}

fn seed_db(conn: &Connection) -> Result<(), rusqlite::Error> {
    let user_count: i64 = conn.query_row("SELECT COUNT(*) FROM users", [], |row| row.get(0))?;
    if user_count > 0 {
        return Ok(());
    }

    // Default password for all demo accounts: "password123"
    let default_hash = bcrypt::hash("password123", 4).unwrap_or_else(|_| "$2b$04$invalid".to_string());

    // 1. Admin & Creator: Aria Vance
    conn.execute(
        "INSERT INTO users (id, name, username, email, password_hash, role_id, bio, location, website, avatar, cover, profile_access_price, profile_access_price_3_months, profile_access_price_6_months, profile_access_price_12_months, public_profile, paid_profile, open_profile, identity_verified_at, email_verified_at, referral_code, country)
         VALUES (1, 'Aria Vance', 'ariavance', 'aria@justfans.local', ?1, 1, 'Digital artist, cosplayer & exclusive visual storyteller. New high-res photo sets & behind-the-scenes videos every Tuesday & Friday ✨', 'Los Angeles, CA', 'https://ariavance.art', '/img/default-avatar.jpg', '/img/default-cover.png', 14.99, 11.99, 9.99, 7.99, 1, 1, 0, datetime('now'), datetime('now'), 'ARIA2026', 'United States')",
        params![default_hash],
    )?;

    // 2. Creator: Marcus Sterling
    conn.execute(
        "INSERT INTO users (id, name, username, email, password_hash, role_id, bio, location, website, avatar, cover, profile_access_price, profile_access_price_3_months, profile_access_price_6_months, profile_access_price_12_months, public_profile, paid_profile, open_profile, identity_verified_at, email_verified_at, referral_code, country)
         VALUES (2, 'Marcus Sterling', 'marcusfit', 'marcus@justfans.local', ?1, 2, 'Pro strength coach, nutrition protocols & daily live workout streams 💪 Subscribe for custom training plans and Q&A.', 'Miami, FL', 'https://marcusfit.io', '/img/default-avatar.jpg', '/img/default-cover.png', 9.99, 8.49, 6.99, 5.99, 1, 1, 0, datetime('now'), datetime('now'), 'MARCUSFIT', 'United States')",
        params![default_hash],
    )?;

    // 3. Creator (Open/Free profile with PPV): Elena Rostova
    conn.execute(
        "INSERT INTO users (id, name, username, email, password_hash, role_id, bio, location, website, avatar, cover, profile_access_price, profile_access_price_3_months, profile_access_price_6_months, profile_access_price_12_months, public_profile, paid_profile, open_profile, identity_verified_at, email_verified_at, referral_code, country)
         VALUES (3, 'Elena Rostova', 'elenamusic', 'elena@justfans.local', ?1, 2, 'Indie synth-pop producer & vocalist 🎹 Free profile! Unlock unreleased stems, studio sessions, and acoustic covers.', 'Berlin, Germany', 'https://elenasynth.de', '/img/default-avatar.jpg', '/img/default-cover.png', 0.0, 0.0, 0.0, 0.0, 1, 0, 1, datetime('now'), datetime('now'), 'ELENA88', 'Germany')",
        params![default_hash],
    )?;

    // 4. Fan / Demo Subscriber: Alex Rivera
    conn.execute(
        "INSERT INTO users (id, name, username, email, password_hash, role_id, bio, location, website, avatar, cover, profile_access_price, public_profile, paid_profile, open_profile, email_verified_at, referral_code, country)
         VALUES (4, 'Alex Rivera', 'alexrivera', 'alex@justfans.local', ?1, 2, 'Supporting independent creators, music lovers & digital art collectors.', 'New York, NY', '', '/img/default-avatar.jpg', '/img/default-cover.png', 0.0, 1, 0, 1, datetime('now'), 'ALEXFAN', 'United States')",
        params![default_hash],
    )?;

    // Wallets
    conn.execute("INSERT INTO wallets (user_id, total) VALUES (1, 1450.50)", [])?;
    conn.execute("INSERT INTO wallets (user_id, total) VALUES (2, 820.00)", [])?;
    conn.execute("INSERT INTO wallets (user_id, total) VALUES (3, 540.75)", [])?;
    conn.execute("INSERT INTO wallets (user_id, total) VALUES (4, 250.00)", [])?;

    // Default Lists for each user
    for uid in 1..=4 {
        conn.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, 'Followers', 'followers')",
            params![uid],
        )?;
        conn.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, 'Following', 'following')",
            params![uid],
        )?;
        conn.execute(
            "INSERT INTO user_lists (user_id, name, list_type) VALUES (?1, 'Blocked', 'blocked')",
            params![uid],
        )?;
    }

    // Make Aria & Alex follow each other and subscribed to Aria
    conn.execute(
        "INSERT INTO subscriptions (sender_user_id, recipient_user_id, amount, provider, status, expires_at)
         VALUES (4, 1, 14.99, 'credit', 'completed', datetime('now', '+30 days'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO subscriptions (sender_user_id, recipient_user_id, amount, provider, status, expires_at)
         VALUES (1, 2, 9.99, 'credit', 'completed', datetime('now', '+25 days'))",
        [],
    )?;

    // Add following list entries
    let aria_following_list: i64 = conn.query_row(
        "SELECT id FROM user_lists WHERE user_id = 1 AND list_type = 'following'",
        [],
        |r| r.get(0),
    )?;
    conn.execute(
        "INSERT OR IGNORE INTO user_list_members (list_id, user_id) VALUES (?1, 2), (?1, 3)",
        params![aria_following_list],
    )?;

    let alex_following_list: i64 = conn.query_row(
        "SELECT id FROM user_lists WHERE user_id = 4 AND list_type = 'following'",
        [],
        |r| r.get(0),
    )?;
    conn.execute(
        "INSERT OR IGNORE INTO user_list_members (list_id, user_id) VALUES (?1, 1), (?1, 3)",
        params![alex_following_list],
    )?;

    // Seed Posts
    conn.execute(
        "INSERT INTO posts (id, user_id, text, price, status, is_pinned, created_at)
         VALUES (1, 1, 'Welcome to my official creator page! 🎨✨ Here is a sneak peek at our Cyberpunk Neon studio shoot. Let me know which concept we should shoot next in the poll below! #digitalart #cyberpunk #exclusive', 0.0, 1, 1, datetime('now', '-2 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
         VALUES ('att-seed-1', 1, 1, 'studio-concept.svg', '/img/home-scene-1.svg', 'image', 10981)",
        [],
    )?;

    // Poll on Post 1
    conn.execute("INSERT INTO polls (id, user_id, post_id) VALUES (1, 1, 1)", [])?;
    conn.execute("INSERT INTO poll_answers (id, poll_id, answer) VALUES (1, 1, 'Cyberpunk Tokyo Rooftop')", [])?;
    conn.execute("INSERT INTO poll_answers (id, poll_id, answer) VALUES (2, 1, 'Ethereal Forest Fantasy')", [])?;
    conn.execute("INSERT INTO poll_answers (id, poll_id, answer) VALUES (3, 1, 'Behind-the-Scenes Vlog')", [])?;
    conn.execute("INSERT INTO poll_user_answers (poll_id, user_id, answer_id) VALUES (1, 4, 1)", [])?;

    // Post 2: PPV Post by Aria
    conn.execute(
        "INSERT INTO posts (id, user_id, text, price, status, is_pinned, created_at)
         VALUES (2, 1, '🔥 EXCLUSIVE PPV SET: 4K Unedited Collector''s Gallery + Procreate Timelapse Video. Unlock this post to view the full high-resolution pack!', 19.99, 1, 0, datetime('now', '-5 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
         VALUES ('att-seed-2', 1, 2, 'exclusive-pack.svg', '/img/home-scene-3.svg', 'image', 10663)",
        [],
    )?;

    // Post 3: Marcus Fitness Post
    conn.execute(
        "INSERT INTO posts (id, user_id, text, price, status, is_pinned, created_at)
         VALUES (3, 2, 'Upper Body Hypertrophy & Core Conditioning breakdown 💪 Drop your questions in the comments and I will review form check videos tonight! #fitness #strength #coaching', 0.0, 1, 1, datetime('now', '-1 day'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
         VALUES ('att-seed-3', 2, 3, 'training-session.svg', '/img/home-scene-2.svg', 'image', 12956)",
        [],
    )?;

    // Post 4: Elena Music Free Post
    conn.execute(
        "INSERT INTO posts (id, user_id, text, price, status, is_pinned, created_at)
         VALUES (4, 3, 'Just finished mastering our new midnight synthwave track ''Neon Horizon'' 🎹🎧 Listen to the preview clip and tell me what you think! #synthwave #indiemusic #producer', 0.0, 1, 0, datetime('now', '-3 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO attachments (id, user_id, post_id, filename, file_url, file_type, file_size)
         VALUES ('att-seed-4', 3, 4, 'synth-session.svg', '/img/home-creators.svg', 'image', 24144)",
        [],
    )?;

    // Seed Post Comments & Likes
    conn.execute(
        "INSERT INTO post_comments (post_id, user_id, message, created_at)
         VALUES (1, 4, 'The lighting in this set is unreal! Definitely voting for Cyberpunk Tokyo Rooftop 🔥', datetime('now', '-1 hour'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO post_comments (post_id, user_id, message, created_at)
         VALUES (1, 3, 'Love the color palette Aria! So inspiring.', datetime('now', '-45 minutes'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO reactions (user_id, reaction_type, post_id) VALUES (4, 'like', 1), (3, 'like', 1), (2, 'like', 1), (1, 'like', 3), (4, 'like', 4)",
        [],
    )?;

    // Seed Messages
    conn.execute(
        "INSERT INTO user_messages (sender_id, receiver_id, message, price, is_seen, created_at)
         VALUES (4, 1, 'Hey Aria! Just subscribed to your page, huge fan of your artwork!', 0.0, 1, datetime('now', '-3 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO user_messages (sender_id, receiver_id, message, price, is_seen, created_at)
         VALUES (1, 4, 'Thank you so much for the support Alex! 💜 Let me know if you want any custom commissions!', 0.0, 0, datetime('now', '-2 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO user_messages (sender_id, receiver_id, message, price, is_seen, created_at)
         VALUES (3, 1, 'Hey Aria, would love to collaborate on cover art for my upcoming EP!', 0.0, 0, datetime('now', '-1 hour'))",
        [],
    )?;

    // Seed Notifications
    conn.execute(
        "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message, is_read, created_at)
         VALUES ('notif-1', 4, 1, 'subscription', NULL, 'Alex Rivera subscribed to your profile for $14.99', 0, datetime('now', '-3 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message, is_read, created_at)
         VALUES ('notif-2', 4, 1, 'comment', 1, 'Alex Rivera commented on your post', 0, datetime('now', '-1 hour'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO notifications (id, from_user_id, to_user_id, notification_type, post_id, message, is_read, created_at)
         VALUES ('notif-3', 3, 1, 'like', 1, 'Elena Rostova liked your post', 1, datetime('now', '-45 minutes'))",
        [],
    )?;

    // Seed Reels
    conn.execute(
        "INSERT INTO reels (id, user_id, caption, video_url, cover_url, price, views_count, created_at)
         VALUES (1, 1, 'Neon lights & late night digital painting timelapse ✨ #reels #cyberpunk', '/img/video-loading-spinner.mp4', '/img/home-scene-1.svg', 0.0, 342, datetime('now', '-4 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO reels (id, user_id, caption, video_url, cover_url, price, views_count, created_at)
         VALUES (2, 2, '60-Second Explosive Kettlebell Complex 🔥 Try this finisher!', '/img/video-loading-spinner.mp4', '/img/home-scene-2.svg', 0.0, 518, datetime('now', '-8 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO reels (id, user_id, caption, video_url, cover_url, price, views_count, created_at)
         VALUES (3, 3, 'Live analog synth jam in Berlin studio 🎹✨', '/img/video-loading-spinner.mp4', '/img/home-scene-3.svg', 0.0, 289, datetime('now', '-12 hours'))",
        [],
    )?;

    // Seed Stories
    conn.execute(
        "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, views_count, expires_at)
         VALUES (1, '/img/home-scene-1.svg', 'image', 'New photoshoot dropping at 8PM EST! ✨', 'gradient-purple', 1, 84, datetime('now', '+20 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, views_count, expires_at)
         VALUES (2, '/img/home-scene-2.svg', 'image', 'Morning cardio done. Live Q&A stream starting soon! 💪', 'gradient-blue', 0, 52, datetime('now', '+18 hours'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO stories (user_id, media_url, media_type, caption, bg_preset, is_highlight, views_count, expires_at)
         VALUES (3, '/img/home-scene-3.svg', 'image', 'Mixing vocals all night in Berlin 🎧', 'gradient-pink', 1, 67, datetime('now', '+22 hours'))",
        [],
    )?;

    // Seed Live Streams
    conn.execute(
        "INSERT INTO streams (id, user_id, name, slug, poster, price, requires_subscription, is_public, status, rtmp_key, viewer_count, created_at)
         VALUES (1, 1, 'Live Digital Painting & Subscribers Hangout 🎨', 'live-digital-painting-hangout', '/img/live-stream-cover.svg', 0.0, 0, 1, 'in-progress', 'sk_live_aria_998877', 47, datetime('now', '-30 minutes'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO streams (id, user_id, name, slug, poster, price, requires_subscription, is_public, status, rtmp_key, viewer_count, created_at)
         VALUES (2, 2, 'VIP Form Check & Strength Programming Masterclass', 'vip-strength-masterclass', '/img/live-stream-cover.svg', 5.0, 1, 1, 'in-progress', 'sk_live_marcus_112233', 29, datetime('now', '-15 minutes'))",
        [],
    )?;
    conn.execute(
        "INSERT INTO stream_messages (stream_id, user_id, message, created_at)
         VALUES (1, 4, 'Love how the background details are coming together!', datetime('now', '-10 minutes')),
                (1, 3, 'The brush texture looks awesome Aria!', datetime('now', '-5 minutes'))",
        [],
    )?;

    // Seed Transactions
    conn.execute(
        "INSERT INTO transactions (sender_user_id, recipient_user_id, tx_type, status, amount, currency, payment_provider, invoice_id, created_at)
         VALUES (4, 1, 'one-month-subscription', 'approved', 14.99, 'USD', 'stripe', 1001, datetime('now', '-3 hours')),
                (4, 1, 'tip', 'approved', 25.00, 'USD', 'credit', 1002, datetime('now', '-2 hours')),
                (4, 4, 'deposit', 'approved', 100.00, 'USD', 'stripe', 1003, datetime('now', '-1 day'))",
        [],
    )?;

    // Seed Public Pages
    conn.execute(
        "INSERT INTO public_pages (slug, title, short_title, content)
         VALUES
         ('terms-and-conditions', 'Terms and Conditions', 'Terms of Use', '<h3>1. Platform Terms of Service</h3><p>Welcome to JustFans. By accessing our platform, you agree to comply with creator monetization standards, age verification policies (18+), and intellectual property guidelines.</p><h3>2. Subscriptions & Creator Monetization</h3><p>Creators retain 85% of all subscription, pay-per-view (PPV), and tip earnings. Subscriptions renew automatically unless cancelled prior to the billing date.</p>'),
         ('privacy', 'Privacy Policy', 'Privacy Policy', '<h3>1. Data Protection & Privacy</h3><p>We take your privacy seriously. All personal data, payment records, and private messages are encrypted and never sold to third parties.</p><h3>2. Cookie & Session Security</h3><p>We use strictly necessary authentication tokens and optional analytics preferences that you control.</p>'),
         ('help', 'Help & Creator FAQ', 'Help Center', '<h3>How do I start earning as a creator?</h3><p>Go to Settings → Rates to set your monthly subscription bundle prices, or create Pay-Per-View (PPV) posts and locked messages directly from the composer.</p><h3>How do payouts work?</h3><p>Navigate to Settings → Wallet → Withdraw to request an instant payout via Bank Transfer, Stripe Connect, or PayPal.</p>')",
        [],
    )?;

    // Seed Settings
    conn.execute(
        "INSERT INTO settings (key, value) VALUES
         ('site.name', 'JustFans'),
         ('site.slogan', 'Premium Content Creators SaaS Platform'),
         ('site.description', 'Launch a premium content platform with subscriptions, PPV posts, direct messenger, reels, stories, and live streaming.'),
         ('feed.min_post_description', '0'),
         ('payments.withdrawal_min_amount', '20'),
         ('payments.platform_fee_percentage', '15')",
        [],
    )?;

    Ok(())
}

pub fn get_user_public_profile(
    conn: &Connection,
    target_user_id: i64,
    viewer_id: Option<i64>,
) -> Result<UserPublicProfile, rusqlite::Error> {
    let mut stmt = conn.prepare(
        "SELECT id, name, username, email, role_id, bio, location, website, avatar, cover,
                birthdate, gender_pronoun, profile_access_price, profile_access_price_3_months,
                profile_access_price_6_months, profile_access_price_12_months,
                public_profile, paid_profile, open_profile, enable_2fa, enable_geoblocking,
                identity_verified_at, referral_code, created_at
         FROM users WHERE id = ?1",
    )?;

    stmt.query_row(params![target_user_id], |row| {
        let uid: i64 = row.get(0)?;
        let identity_verified_at: Option<String> = row.get(21)?;

        let is_owner_or_admin = if let Some(vid) = viewer_id {
            if vid == uid {
                true
            } else {
                let role: i64 = conn
                    .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
                    .unwrap_or(2);
                role == 1
            }
        } else {
            false
        };

        let fans_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM subscriptions WHERE recipient_user_id = ?1 AND status = 'completed' AND expires_at >= datetime('now')",
                params![uid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let following_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM user_list_members ulm
                 JOIN user_lists ul ON ulm.list_id = ul.id
                 WHERE ul.user_id = ?1 AND ul.list_type = 'following'",
                params![uid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let posts_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM posts WHERE user_id = ?1 AND status = 1",
                params![uid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let media_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM attachments WHERE user_id = ?1 AND post_id IS NOT NULL",
                params![uid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let wallet_balance: f64 = if is_owner_or_admin {
            conn.query_row(
                "SELECT total FROM wallets WHERE user_id = ?1",
                params![uid],
                |r| r.get(0),
            )
            .unwrap_or(0.0)
        } else {
            0.0
        };

        let is_subscribed = if let Some(vid) = viewer_id {
            if vid == uid {
                true
            } else {
                conn.query_row(
                    "SELECT COUNT(*) FROM subscriptions WHERE sender_user_id = ?1 AND recipient_user_id = ?2 AND status = 'completed' AND expires_at >= datetime('now')",
                    params![vid, uid],
                    |r| r.get::<_, i64>(0),
                )
                .unwrap_or(0)
                    > 0
            }
        } else {
            false
        };

        let is_following = if let Some(vid) = viewer_id {
            conn.query_row(
                "SELECT COUNT(*) FROM user_list_members ulm
                 JOIN user_lists ul ON ulm.list_id = ul.id
                 WHERE ul.user_id = ?1 AND ul.list_type = 'following' AND ulm.user_id = ?2",
                params![vid, uid],
                |r| r.get::<_, i64>(0),
            )
            .unwrap_or(0)
                > 0
        } else {
            false
        };

        Ok(UserPublicProfile {
            id: uid,
            name: row.get(1)?,
            username: row.get(2)?,
            email: if is_owner_or_admin { row.get(3)? } else { String::new() },
            role_id: row.get(4)?,
            bio: row.get(5)?,
            location: row.get(6)?,
            website: row.get(7)?,
            avatar: row.get(8)?,
            cover: row.get(9)?,
            birthdate: if is_owner_or_admin { row.get(10)? } else { None },
            gender_pronoun: row.get(11)?,
            profile_access_price: row.get(12)?,
            profile_access_price_3_months: row.get(13)?,
            profile_access_price_6_months: row.get(14)?,
            profile_access_price_12_months: row.get(15)?,
            public_profile: row.get::<_, i64>(16)? != 0,
            paid_profile: row.get::<_, i64>(17)? != 0,
            open_profile: row.get::<_, i64>(18)? != 0,
            enable_2fa: if is_owner_or_admin { row.get::<_, i64>(19)? != 0 } else { false },
            enable_geoblocking: if is_owner_or_admin { row.get::<_, i64>(20)? != 0 } else { false },
            is_verified: identity_verified_at.is_some(),
            fans_count,
            following_count,
            posts_count,
            media_count,
            is_subscribed,
            is_following,
            wallet_balance,
            referral_code: row.get(22)?,
            created_at: row.get(23)?,
        })
    })
}

pub fn build_post_view(
    conn: &Connection,
    post_id: i64,
    viewer_id: Option<i64>,
) -> Result<PostView, rusqlite::Error> {
    let mut stmt = conn.prepare(
        "SELECT p.id, p.user_id, u.name, u.username, u.avatar, u.identity_verified_at,
                u.profile_access_price, u.paid_profile, u.open_profile,
                p.text, p.price, p.status, p.is_pinned, p.release_date, p.expire_date, p.created_at
         FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE p.id = ?1",
    )?;

    stmt.query_row(params![post_id], |row| {
        let pid: i64 = row.get(0)?;
        let author_id: i64 = row.get(1)?;
        let user_name: String = row.get(2)?;
        let user_username: String = row.get(3)?;
        let user_avatar: String = row.get(4)?;
        let verified_at: Option<String> = row.get(5)?;
        let user_access_price: f64 = row.get(6)?;
        let paid_profile: bool = row.get::<_, i64>(7)? != 0;
        let open_profile: bool = row.get::<_, i64>(8)? != 0;
        let raw_text: String = row.get(9)?;
        let price: f64 = row.get(10)?;
        let status: i64 = row.get(11)?;
        let is_pinned: bool = row.get::<_, i64>(12)? != 0;
        let release_date: Option<String> = row.get(13)?;
        let expire_date: Option<String> = row.get(14)?;
        let created_at: String = row.get(15)?;

        let is_owner_or_admin = if let Some(vid) = viewer_id {
            if vid == author_id {
                true
            } else {
                let role: i64 = conn
                    .query_row("SELECT role_id FROM users WHERE id = ?1", params![vid], |r| r.get(0))
                    .unwrap_or(2);
                role == 1
            }
        } else {
            false
        };

        let is_subbed = if is_owner_or_admin || open_profile || !paid_profile || user_access_price <= 0.0 {
            true
        } else if let Some(vid) = viewer_id {
            conn.query_row(
                "SELECT COUNT(*) FROM subscriptions WHERE sender_user_id = ?1 AND recipient_user_id = ?2 AND status = 'completed' AND expires_at >= datetime('now')",
                params![vid, author_id],
                |r| r.get::<_, i64>(0),
            )
            .unwrap_or(0)
                > 0
        } else {
            false
        };

        let is_purchased = if is_owner_or_admin || price <= 0.0 {
            true
        } else if let Some(vid) = viewer_id {
            conn.query_row(
                "SELECT COUNT(*) FROM transactions WHERE sender_user_id = ?1 AND post_id = ?2 AND tx_type = 'post-unlock' AND status = 'approved'",
                params![vid, pid],
                |r| r.get::<_, i64>(0),
            )
            .unwrap_or(0)
                > 0
        } else {
            false
        };

        let is_unlocked = is_owner_or_admin || (is_subbed && is_purchased);

        let text = if is_unlocked {
            raw_text
        } else {
            "🔒 Exclusive locked content — subscribe or unlock to view this post.".to_string()
        };

        let likes_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM reactions WHERE post_id = ?1",
                params![pid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let comments_count: i64 = conn
            .query_row(
                "SELECT COUNT(*) FROM post_comments WHERE post_id = ?1",
                params![pid],
                |r| r.get(0),
            )
            .unwrap_or(0);

        let (tips_count, tips_total): (i64, f64) = conn
            .query_row(
                "SELECT COUNT(*), COALESCE(SUM(amount), 0.0) FROM transactions WHERE post_id = ?1 AND tx_type = 'tip' AND status = 'approved'",
                params![pid],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .unwrap_or((0, 0.0));

        let user_liked = if let Some(vid) = viewer_id {
            conn.query_row(
                "SELECT COUNT(*) FROM reactions WHERE post_id = ?1 AND user_id = ?2",
                params![pid, vid],
                |r| r.get::<_, i64>(0),
            )
            .unwrap_or(0)
                > 0
        } else {
            false
        };

        let user_bookmarked = if let Some(vid) = viewer_id {
            conn.query_row(
                "SELECT COUNT(*) FROM user_bookmarks WHERE post_id = ?1 AND user_id = ?2",
                params![pid, vid],
                |r| r.get::<_, i64>(0),
            )
            .unwrap_or(0)
                > 0
        } else {
            false
        };

        // Load attachments
        let mut att_stmt = conn.prepare(
            "SELECT id, user_id, post_id, message_id, filename, file_url, file_type, file_size, is_vault, is_archived, created_at
             FROM attachments WHERE post_id = ?1 ORDER BY created_at ASC",
        )?;
        let attachments: Vec<Attachment> = att_stmt
            .query_map(params![pid], |arow| {
                let raw_url: String = arow.get(5)?;
                Ok(Attachment {
                    id: arow.get(0)?,
                    user_id: arow.get(1)?,
                    post_id: arow.get(2)?,
                    message_id: arow.get(3)?,
                    filename: arow.get(4)?,
                    file_url: if is_unlocked {
                        raw_url
                    } else {
                        "/img/post-locked.svg".to_string()
                    },
                    file_type: arow.get(6)?,
                    file_size: arow.get(7)?,
                    is_vault: arow.get::<_, i64>(8)? != 0,
                    is_archived: arow.get::<_, i64>(9)? != 0,
                    created_at: arow.get(10)?,
                })
            })?
            .filter_map(Result::ok)
            .collect();

        // Load poll if exists and post is unlocked
        let poll: Option<PollView> = if !is_unlocked {
            None
        } else {
            conn.query_row(
                "SELECT id FROM polls WHERE post_id = ?1",
                params![pid],
                |prow| prow.get::<_, i64>(0),
            )
            .ok()
            .and_then(|poll_id| {
                let total_votes: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM poll_user_answers WHERE poll_id = ?1",
                        params![poll_id],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);

                let user_voted_answer_id: Option<i64> = viewer_id.and_then(|vid| {
                    conn.query_row(
                        "SELECT answer_id FROM poll_user_answers WHERE poll_id = ?1 AND user_id = ?2",
                        params![poll_id, vid],
                        |r| r.get(0),
                    )
                    .ok()
                });

                let mut ans_stmt = conn
                    .prepare("SELECT id, answer FROM poll_answers WHERE poll_id = ?1 ORDER BY id ASC")
                    .ok()?;
                let answers: Vec<PollAnswerView> = ans_stmt
                    .query_map(params![poll_id], |ans_row| {
                        let aid: i64 = ans_row.get(0)?;
                        let answer_text: String = ans_row.get(1)?;
                        let vcount: i64 = conn
                            .query_row(
                                "SELECT COUNT(*) FROM poll_user_answers WHERE poll_id = ?1 AND answer_id = ?2",
                                params![poll_id, aid],
                                |r| r.get(0),
                            )
                            .unwrap_or(0);
                        let pct = if total_votes > 0 {
                            ((vcount as f64 / total_votes as f64) * 100.0).round()
                        } else {
                            0.0
                        };
                        Ok(PollAnswerView {
                            id: aid,
                            answer: answer_text,
                            votes_count: vcount,
                            percentage: pct,
                        })
                    })
                    .ok()?
                    .filter_map(Result::ok)
                    .collect();

                Some(PollView {
                    id: poll_id,
                    post_id: pid,
                    total_votes,
                    user_voted_answer_id,
                    answers,
                })
            })
        };

        // Load recent comments
        let mut cmt_stmt = conn.prepare(
            "SELECT c.id, c.post_id, c.user_id, u.name, u.username, u.avatar, u.identity_verified_at, c.message, c.created_at
             FROM post_comments c
             JOIN users u ON c.user_id = u.id
             WHERE c.post_id = ?1
             ORDER BY c.id ASC",
        )?;
        let comments: Vec<PostCommentView> = cmt_stmt
            .query_map(params![pid], |crow| {
                let cid: i64 = crow.get(0)?;
                let c_verified: Option<String> = crow.get(6)?;
                let clikes: i64 = conn
                    .query_row(
                        "SELECT COUNT(*) FROM reactions WHERE post_comment_id = ?1",
                        params![cid],
                        |r| r.get(0),
                    )
                    .unwrap_or(0);
                let culiked = if let Some(vid) = viewer_id {
                    conn.query_row(
                        "SELECT COUNT(*) FROM reactions WHERE post_comment_id = ?1 AND user_id = ?2",
                        params![cid, vid],
                        |r| r.get::<_, i64>(0),
                    )
                    .unwrap_or(0)
                        > 0
                } else {
                    false
                };
                Ok(PostCommentView {
                    id: cid,
                    post_id: crow.get(1)?,
                    user_id: crow.get(2)?,
                    user_name: crow.get(3)?,
                    user_username: crow.get(4)?,
                    user_avatar: crow.get(5)?,
                    user_verified: c_verified.is_some(),
                    message: crow.get(7)?,
                    likes_count: clikes,
                    user_liked: culiked,
                    created_at: crow.get(8)?,
                })
            })?
            .filter_map(Result::ok)
            .collect();

        Ok(PostView {
            id: pid,
            user_id: author_id,
            user_name,
            user_username,
            user_avatar,
            user_verified: verified_at.is_some(),
            user_access_price,
            text,
            price,
            status,
            is_pinned,
            release_date,
            expire_date,
            is_unlocked,
            is_subbed,
            is_purchased,
            likes_count,
            comments_count,
            tips_count,
            tips_total,
            user_liked,
            user_bookmarked,
            attachments,
            poll,
            comments,
            created_at,
        })
    })
}


use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct User {
    pub id: i64,
    pub name: String,
    pub username: String,
    pub email: String,
    #[serde(skip_serializing)]
    pub password_hash: String,
    pub role_id: i64, // 1 = admin, 2 = user
    pub bio: String,
    pub location: String,
    pub website: String,
    pub avatar: String,
    pub cover: String,
    pub birthdate: Option<String>,
    pub gender_id: Option<i64>,
    pub gender_pronoun: Option<String>,
    pub profile_access_price: f64,
    pub profile_access_price_3_months: f64,
    pub profile_access_price_6_months: f64,
    pub profile_access_price_12_months: f64,
    pub public_profile: bool,
    pub paid_profile: bool,
    pub open_profile: bool,
    pub enable_2fa: bool,
    pub enable_geoblocking: bool,
    pub identity_verified_at: Option<String>,
    pub email_verified_at: Option<String>,
    pub billing_address: Option<String>,
    pub first_name: Option<String>,
    pub last_name: Option<String>,
    pub city: Option<String>,
    pub country: Option<String>,
    pub state: Option<String>,
    pub postcode: Option<String>,
    pub referral_code: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct UserPublicProfile {
    pub id: i64,
    pub name: String,
    pub username: String,
    pub email: String,
    pub role_id: i64,
    pub bio: String,
    pub location: String,
    pub website: String,
    pub avatar: String,
    pub cover: String,
    pub birthdate: Option<String>,
    pub gender_pronoun: Option<String>,
    pub profile_access_price: f64,
    pub profile_access_price_3_months: f64,
    pub profile_access_price_6_months: f64,
    pub profile_access_price_12_months: f64,
    pub public_profile: bool,
    pub paid_profile: bool,
    pub open_profile: bool,
    pub enable_2fa: bool,
    pub enable_geoblocking: bool,
    pub is_verified: bool,
    pub fans_count: i64,
    #[serde(default)]
    pub followers_count: i64,
    pub following_count: i64,
    pub posts_count: i64,
    pub media_count: i64,
    pub is_subscribed: bool,
    pub is_following: bool,
    #[serde(default)]
    pub is_blocked: bool,
    pub wallet_balance: f64,
    #[serde(default)]
    pub first_name: Option<String>,
    #[serde(default)]
    pub last_name: Option<String>,
    #[serde(default)]
    pub billing_address: Option<String>,
    #[serde(default)]
    pub city: Option<String>,
    #[serde(default)]
    pub country: Option<String>,
    #[serde(default)]
    pub state: Option<String>,
    #[serde(default)]
    pub postcode: Option<String>,
    pub referral_code: String,
    pub created_at: String,
    #[serde(default)]
    pub creator_offer: Option<CreatorOfferView>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CreatorOfferView {
    pub id: i64,
    pub user_id: i64,
    pub old_profile_access_price: f64,
    pub old_profile_access_price_3_months: f64,
    pub old_profile_access_price_6_months: f64,
    pub old_profile_access_price_12_months: f64,
    pub discount_percent: i64,
    pub expires_at: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AgentTokenView {
    pub id: i64,
    pub user_id: i64,
    pub name: String,
    pub token_prefix: String,
    pub scopes: Vec<String>,
    pub max_spend_per_tx: f64,
    pub daily_spend_limit: f64,
    pub spent_today: f64,
    pub spent_last_24h: f64,
    pub is_active: bool,
    pub last_used_at: Option<String>,
    pub expires_at: Option<String>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct AgentAuditLogView {
    pub id: i64,
    pub user_id: i64,
    pub agent_token_id: Option<i64>,
    pub agent_name: Option<String>,
    pub tool_name: String,
    pub resource_type: String,
    pub resource_id: Option<String>,
    pub status: String,
    pub amount_spent: f64,
    pub details: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct Attachment {
    pub id: String,
    pub user_id: i64,
    pub post_id: Option<i64>,
    pub message_id: Option<i64>,
    pub filename: String,
    pub file_url: String,
    pub file_type: String, // "image", "video", "audio", "document"
    pub file_size: i64,
    pub is_vault: bool,
    pub is_archived: bool,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PollAnswerView {
    pub id: i64,
    pub answer: String,
    pub votes_count: i64,
    pub percentage: f64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PollView {
    pub id: i64,
    pub post_id: i64,
    pub total_votes: i64,
    pub user_voted_answer_id: Option<i64>,
    pub answers: Vec<PollAnswerView>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PostCommentView {
    pub id: i64,
    pub post_id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub message: String,
    pub likes_count: i64,
    pub user_liked: bool,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PostView {
    pub id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub user_access_price: f64,
    pub text: String,
    pub price: f64,
    pub status: i64, // 0 pending, 1 approved, 2 disapproved
    pub is_pinned: bool,
    pub release_date: Option<String>,
    pub expire_date: Option<String>,
    pub is_unlocked: bool,
    pub is_subbed: bool,
    pub is_purchased: bool,
    pub likes_count: i64,
    pub comments_count: i64,
    pub tips_count: i64,
    pub tips_total: f64,
    pub user_liked: bool,
    pub user_bookmarked: bool,
    pub attachments: Vec<Attachment>,
    pub poll: Option<PollView>,
    pub comments: Vec<PostCommentView>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ConversationContact {
    pub user_id: i64,
    pub name: String,
    pub username: String,
    pub avatar: String,
    pub is_verified: bool,
    pub last_message: String,
    pub last_message_at: String,
    pub unread_count: i64,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct MessageView {
    pub id: i64,
    pub sender_id: i64,
    pub receiver_id: i64,
    pub sender_name: String,
    pub sender_username: String,
    pub sender_avatar: String,
    pub message: String,
    pub price: f64,
    pub is_unlocked: bool,
    pub is_seen: bool,
    pub is_pinned: bool,
    pub attachments: Vec<Attachment>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct NotificationView {
    pub id: String,
    pub from_user_id: Option<i64>,
    pub from_user_name: Option<String>,
    pub from_user_username: Option<String>,
    pub from_user_avatar: Option<String>,
    pub to_user_id: i64,
    pub notification_type: String, // "like", "comment", "subscription", "tip", "message", "ppv-unlock"
    pub post_id: Option<i64>,
    pub message: String,
    pub is_read: bool,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct UserListView {
    pub id: i64,
    pub user_id: i64,
    pub name: String,
    pub list_type: String, // "followers", "following", "blocked", "custom"
    pub members_count: i64,
    pub members: Vec<UserPublicProfile>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct SubscriptionView {
    pub id: i64,
    pub sender_user_id: i64,
    pub recipient_user_id: i64,
    pub creator_name: String,
    pub creator_username: String,
    pub creator_avatar: String,
    pub subscriber_name: String,
    pub subscriber_username: String,
    pub subscriber_avatar: String,
    pub amount: f64,
    pub status: String, // "completed", "canceled", "expired"
    pub expires_at: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct TransactionView {
    pub id: i64,
    pub sender_user_id: i64,
    pub recipient_user_id: Option<i64>,
    pub sender_name: String,
    pub recipient_name: Option<String>,
    pub tx_type: String, // "deposit", "tip", "post-unlock", "message-unlock", "stream-unlock", "one-month-subscription"
    pub status: String,  // "approved", "pending", "declined"
    pub amount: f64,
    pub currency: String,
    pub payment_provider: String,
    pub post_id: Option<i64>,
    pub invoice_id: Option<i64>,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct WithdrawalView {
    pub id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub amount: f64,
    pub status: String, // "requested", "approved", "rejected"
    pub payment_method: String,
    pub payment_identifier: String,
    pub message: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ReelView {
    pub id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub caption: String,
    pub video_url: String,
    pub cover_url: String,
    pub price: f64,
    pub is_unlocked: bool,
    pub views_count: i64,
    pub likes_count: i64,
    pub comments_count: i64,
    pub user_liked: bool,
    pub user_bookmarked: bool,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct ReelCommentView {
    pub id: i64,
    pub reel_id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub message: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct StoryView {
    pub id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub media_url: String,
    pub media_type: String, // "image" or "video"
    pub caption: String,
    pub bg_preset: String,
    pub is_highlight: bool,
    pub views_count: i64,
    pub expires_at: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct StreamView {
    pub id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub user_verified: bool,
    pub name: String,
    pub slug: String,
    pub poster: String,
    pub price: f64,
    pub requires_subscription: bool,
    pub is_public: bool,
    pub status: String, // "in-progress", "ended"
    pub rtmp_server: String,
    pub rtmp_key: String,
    pub hls_link: String,
    pub viewer_count: i64,
    pub is_unlocked: bool,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct StreamMessageView {
    pub id: i64,
    pub stream_id: i64,
    pub user_id: i64,
    pub user_name: String,
    pub user_username: String,
    pub user_avatar: String,
    pub message: String,
    pub created_at: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PublicPageView {
    pub id: i64,
    pub slug: String,
    pub title: String,
    pub short_title: String,
    pub content: String,
    pub updated_at: String,
}


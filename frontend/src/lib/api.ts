export interface UserProfile {
  id: number;
  name: string;
  username: string;
  email: string;
  role_id: number;
  bio: string;
  location: string;
  website: string;
  avatar: string;
  cover: string;
  birthdate?: string | null;
  gender_pronoun?: string | null;
  profile_access_price: number;
  profile_access_price_3_months: number;
  profile_access_price_6_months: number;
  profile_access_price_12_months: number;
  public_profile: boolean;
  paid_profile: boolean;
  open_profile: boolean;
  enable_2fa: boolean;
  enable_geoblocking: boolean;
  is_verified: boolean;
  fans_count: number;
  following_count: number;
  posts_count: number;
  media_count: number;
  is_subscribed: boolean;
  is_following: boolean;
  wallet_balance: number;
  referral_code: string;
  created_at: string;
}

export interface Attachment {
  id: string;
  user_id: number;
  post_id?: number | null;
  message_id?: number | null;
  filename: string;
  file_url: string;
  file_type: string;
  file_size: number;
  is_vault: boolean;
  is_archived: boolean;
  created_at: string;
}

export interface PollAnswer {
  id: number;
  answer: string;
  votes_count: number;
  percentage: number;
}

export interface Poll {
  id: number;
  post_id: number;
  total_votes: number;
  user_voted_answer_id?: number | null;
  answers: PollAnswer[];
}

export interface PostComment {
  id: number;
  post_id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  message: string;
  likes_count: number;
  user_liked: boolean;
  created_at: string;
}

export interface PostItem {
  id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  user_access_price: number;
  text: string;
  price: number;
  status: number;
  is_pinned: boolean;
  release_date?: string | null;
  expire_date?: string | null;
  is_unlocked: boolean;
  is_subbed: boolean;
  is_purchased: boolean;
  likes_count: number;
  comments_count: number;
  tips_count: number;
  tips_total: number;
  user_liked: boolean;
  user_bookmarked: boolean;
  attachments: Attachment[];
  poll?: Poll | null;
  comments: PostComment[];
  created_at: string;
}

export interface ConversationContact {
  user_id: number;
  name: string;
  username: string;
  avatar: string;
  is_verified: boolean;
  last_message: string;
  last_message_at: string;
  unread_count: number;
}

export interface DirectMessage {
  id: number;
  sender_id: number;
  receiver_id: number;
  sender_name: string;
  sender_username: string;
  sender_avatar: string;
  message: string;
  price: number;
  is_unlocked: boolean;
  is_seen: boolean;
  is_pinned: boolean;
  attachments: Attachment[];
  created_at: string;
}

export interface NotificationItem {
  id: string;
  from_user_id?: number | null;
  from_user_name?: string | null;
  from_user_username?: string | null;
  from_user_avatar?: string | null;
  to_user_id: number;
  notification_type: string;
  post_id?: number | null;
  message: string;
  is_read: boolean;
  created_at: string;
}

export interface UserList {
  id: number;
  user_id: number;
  name: string;
  list_type: string;
  members_count: number;
  members: UserProfile[];
  created_at: string;
}

export interface ReelItem {
  id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  caption: string;
  video_url: string;
  cover_url: string;
  price: number;
  is_unlocked: boolean;
  views_count: number;
  likes_count: number;
  comments_count: number;
  user_liked: boolean;
  user_bookmarked: boolean;
  created_at: string;
}

export interface ReelComment {
  id: number;
  reel_id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  message: string;
  created_at: string;
}

export interface StoryItem {
  id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  media_url: string;
  media_type: string;
  caption: string;
  bg_preset: string;
  is_highlight: boolean;
  views_count: number;
  expires_at: string;
  created_at: string;
}

export interface StreamItem {
  id: number;
  user_id: number;
  user_name: string;
  user_username: string;
  user_avatar: string;
  user_verified: boolean;
  name: string;
  slug: string;
  poster: string;
  price: number;
  requires_subscription: boolean;
  is_public: boolean;
  status: string;
  rtmp_server: string;
  rtmp_key: string;
  hls_link: string;
  viewer_count: number;
  is_unlocked: boolean;
  created_at: string;
}

const TOKEN_KEY = 'justfans_jwt_token';

export function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string | null) {
  if (typeof window === 'undefined') return;
  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }
}

export async function apiFetch<T = any>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const token = getStoredToken();
  const headers: Record<string, string> = {
    ...(options.headers as Record<string, string>),
  };

  if (!(options.body instanceof FormData) && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(path, {
    ...options,
    headers,
    credentials: 'include',
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.success === false) {
    throw new Error(data.message || data.error || `Request failed (${res.status})`);
  }
  return data as T;
}


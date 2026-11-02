'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import {
  apiFetch,
  Attachment,
  PostItem,
  ReelItem,
  StoryItem,
  StreamItem,
  UserList,
  UserProfile,
} from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';
import { PostCard } from '@/components/PostCard';

export default function CreatorProfilePage() {
  const params = useParams();
  const router = useRouter();
  const username = String(params?.username || '');
  const { user, refreshUser } = useAuth();

  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [recentMedia, setRecentMedia] = useState<Attachment[]>([]);
  const [streams, setStreams] = useState<StreamItem[]>([]);
  const [reels, setReels] = useState<ReelItem[]>([]);
  const [stories, setStories] = useState<StoryItem[]>([]);
  const [activeStory, setActiveStory] = useState<StoryItem | null>(null);
  const [userLists, setUserLists] = useState<UserList[]>([]);
  const [listDrawerOpen, setListDrawerOpen] = useState(false);
  const [tipDrawerOpen, setTipDrawerOpen] = useState(false);
  const [tipAmount, setTipAmount] = useState('10');
  const [reportDrawerOpen, setReportDrawerOpen] = useState(false);
  const [reportType, setReportType] = useState('Inappropriate content');
  const [reportDetails, setReportDetails] = useState('');
  const [activeTab, setActiveTab] = useState<'posts' | 'media' | 'reels' | 'streams'>('posts');
  const [mediaViewMode, setMediaViewMode] = useState<'grid' | 'feed'>('grid');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  const loadProfile = async (filter = '') => {
    setLoading(true);
    setErrorMessage(null);
    try {
      const res = await apiFetch(
        `/api/profile/${encodeURIComponent(username)}${filter ? `?filter=${filter}` : ''}`
      );
      setProfile(res.profile);
      setPosts(res.posts || []);
      setRecentMedia(res.recent_media || []);
      setStreams(res.streams || []);
      setReels(res.reels || []);
      setStories(res.stories || []);
    } catch (e: any) {
      setProfile(null);
      setErrorMessage(e.message || null);
    } finally {
      setLoading(false);
    }
  };

  const loadUserLists = async () => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/lists');
      setUserLists(res.lists || []);
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    if (username) loadProfile();
  }, [username]);

  const handleToggleListDrawer = () => {
    const next = !listDrawerOpen;
    setListDrawerOpen(next);
    if (next) {
      loadUserLists();
    }
  };

  const handleToggleMemberInList = async (listId: number) => {
    if (!profile) return;
    try {
      const res = await apiFetch('/api/lists/members', {
        method: 'POST',
        body: JSON.stringify({
          list_id: listId,
          member_user_id: profile.id,
        }),
      });
      await loadUserLists();
      setStatusMessage(res.added ? 'Added user to list.' : 'Removed user from list.');
    } catch (err: any) {
      setStatusMessage(err.message);
    }
  };

  const handleToggleBlock = async () => {
    if (!user || !profile) return;
    try {
      const res = await apiFetch('/api/lists/block', {
        method: 'POST',
        body: JSON.stringify({ target_user_id: profile.id }),
      });
      setProfile((p) => (p ? { ...p, is_blocked: res.is_blocked } : p));
      setStatusMessage(
        res.is_blocked ? `Blocked @${profile.username}.` : `Unblocked @${profile.username}.`
      );
    } catch (err: any) {
      setStatusMessage(err.message);
    }
  };

  const handleSendDirectTip = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) {
      router.push('/login');
      return;
    }
    if (!profile) return;
    setBusy(true);
    try {
      const amt = parseFloat(tipAmount) || 5;
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'tip',
          recipient_user_id: profile.id,
          amount: amt,
          provider: 'credit',
        }),
      });
      await refreshUser();
      setTipDrawerOpen(false);
      setStatusMessage(`Sent a $${amt.toFixed(2)} tip to ${profile.name}!`);
    } catch (err: any) {
      setStatusMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleCopyProfileLink = () => {
    if (typeof window === 'undefined' || !profile) return;
    navigator.clipboard?.writeText(`${window.location.origin}/${profile.username}`);
    setStatusMessage('Profile link copied to clipboard!');
  };

  const handleOpenStory = async (s: StoryItem) => {
    setActiveStory(s);
    try {
      const res = await apiFetch(`/api/stories/${s.id}/view`, { method: 'POST' });
      setStories((prev) =>
        prev.map((item) =>
          item.id === s.id ? { ...item, views_count: res.views_count ?? item.views_count } : item
        )
      );
    } catch {
      // ignore
    }
  };

  const handleReportUser = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!profile) return;
    setBusy(true);
    try {
      const res = await apiFetch('/api/report', {
        method: 'POST',
        body: JSON.stringify({
          reported_user_id: profile.id,
          report_type: reportType,
          details: reportDetails,
        }),
      });
      setReportDrawerOpen(false);
      setReportDetails('');
      setStatusMessage(res.message);
    } catch (err: any) {
      setStatusMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSubscribe = async (months: number) => {
    if (!user) {
      router.push('/login');
      return;
    }
    if (!profile) return;
    setBusy(true);
    setStatusMessage(null);
    try {
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'subscription',
          recipient_user_id: profile.id,
          months,
          provider: 'credit',
        }),
      });
      await refreshUser();
      await loadProfile();
      setStatusMessage(`Subscribed to @${profile.username}!`);
    } catch (err: any) {
      setStatusMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleToggleFollow = async () => {
    if (!user) {
      router.push('/login');
      return;
    }
    if (!profile) return;
    try {
      const res = await apiFetch('/api/lists/follow', {
        method: 'POST',
        body: JSON.stringify({ target_user_id: profile.id }),
      });
      setProfile((p) => (p ? { ...p, is_following: res.is_following } : p));
    } catch (err: any) {
      setStatusMessage(err.message);
    }
  };

  if (loading) {
    return (
      <AppShell>
        <div className="p-5 text-center text-muted">Loading creator profile...</div>
      </AppShell>
    );
  }

  if (!profile) {
    return (
      <AppShell>
        <div className="p-5 text-center">
          <img src="/img/404.svg" alt="Not found" style={{ height: 140 }} className="mb-3" />
          <h4 className="fw-bold">{errorMessage || `Creator @${username} not found`}</h4>
          <Link href="/search" className="btn btn-primary rounded-pill mt-2">
            Explore Creators
          </Link>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="row g-0 min-vh-100">
        {/* Main Profile Feed Column */}
        <div className="col-12 col-lg-8 border-end">
          {/* Cover Banner */}
          <div
            className="position-relative border-bottom"
            style={{
              height: 210,
              backgroundImage: `url(${profile.cover || '/img/default-cover.png'})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center',
            }}
          >
            <div className="position-absolute top-0 start-0 p-3 d-flex flex-wrap gap-2">
              <span className="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                📝 {profile.posts_count} posts
              </span>
              <span className="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                🖼️ {profile.media_count} media
              </span>
              {reels.length > 0 && (
                <span className="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                  🎬 {reels.length} reels
                </span>
              )}
            </div>
            <div className="position-absolute top-0 end-0 p-3">
              <button
                type="button"
                onClick={handleCopyProfileLink}
                className="btn btn-sm btn-dark bg-opacity-75 rounded-pill px-3"
                title="Copy profile link"
              >
                🔗 Share
              </button>
            </div>
          </div>

          {/* Avatar & Profile Details */}
          <div className="px-3 pb-3 border-bottom">
            <div className="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
              <img
                src={profile.avatar || '/img/default-avatar.jpg'}
                alt={profile.name}
                className="rounded-circle border border-4 border-white shadow-sm"
                style={{ width: 104, height: 104, marginTop: -52, objectFit: 'cover' }}
              />
              <div className="d-flex flex-wrap gap-2">
                {user && user.id === profile.id ? (
                  <Link
                    href="/my/settings?tab=profile"
                    className="btn btn-outline-primary rounded-pill px-4 fw-semibold"
                  >
                    ⚙️ Edit Profile
                  </Link>
                ) : (
                  <>
                    <button
                      type="button"
                      onClick={handleToggleFollow}
                      className={`btn rounded-pill px-3 fw-semibold ${
                        profile.is_following ? 'btn-secondary' : 'btn-outline-primary'
                      }`}
                    >
                      {profile.is_following ? '✓ Following' : '+ Follow'}
                    </button>
                    <Link
                      href={`/my/messenger?contact=${profile.id}`}
                      className="btn btn-outline-secondary rounded-pill px-3"
                    >
                      💬 Message
                    </Link>
                    {user && (
                      <>
                        <button
                          type="button"
                          onClick={() => setTipDrawerOpen(!tipDrawerOpen)}
                          className="btn btn-outline-success rounded-pill px-3 fw-semibold"
                        >
                          💵 Tip
                        </button>
                        <button
                          type="button"
                          onClick={handleToggleListDrawer}
                          className="btn btn-outline-secondary rounded-pill px-3"
                          title="Add to custom list"
                        >
                          📋 Lists
                        </button>
                        <button
                          type="button"
                          onClick={handleToggleBlock}
                          className={`btn rounded-pill px-3 ${
                            profile.is_blocked ? 'btn-danger' : 'btn-outline-secondary'
                          }`}
                          title="Block or unblock user"
                        >
                          {profile.is_blocked ? '🚫 Blocked' : '🚫'}
                        </button>
                        <button
                          type="button"
                          onClick={() => setReportDrawerOpen(!reportDrawerOpen)}
                          className="btn btn-outline-danger rounded-pill px-3"
                          title="Report user"
                        >
                          🚩
                        </button>
                      </>
                    )}
                  </>
                )}
              </div>
            </div>

            {tipDrawerOpen && user && (
              <form
                onSubmit={handleSendDirectTip}
                className="p-3 mb-3 rounded-4 border bg-body-tertiary"
              >
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <strong className="small">💵 Send a Direct Tip to {profile.name}</strong>
                  <button
                    type="button"
                    onClick={() => setTipDrawerOpen(false)}
                    className="btn-close btn-sm"
                  />
                </div>
                <div className="d-flex gap-2 mb-2">
                  {['5', '10', '25', '50'].map((preset) => (
                    <button
                      key={preset}
                      type="button"
                      onClick={() => setTipAmount(preset)}
                      className={`btn btn-sm rounded-pill ${
                        tipAmount === preset ? 'btn-success' : 'btn-outline-secondary'
                      }`}
                    >
                      ${preset}
                    </button>
                  ))}
                </div>
                <div className="input-group input-group-sm">
                  <span className="input-group-text">$</span>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    value={tipAmount}
                    onChange={(e) => setTipAmount(e.target.value)}
                    className="form-control"
                  />
                  <button type="submit" disabled={busy} className="btn btn-success fw-semibold">
                    Send Tip
                  </button>
                </div>
              </form>
            )}

            {listDrawerOpen && user && (
              <div className="p-3 mb-3 rounded-4 border bg-body-tertiary">
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <strong className="small">Add @{profile.username} to a List</strong>
                  <button
                    type="button"
                    onClick={() => setListDrawerOpen(false)}
                    className="btn-close btn-sm"
                  />
                </div>
                <div className="d-flex flex-wrap gap-2">
                  {userLists.map((l) => {
                    const inList = l.members.some((m) => m.id === profile.id);
                    return (
                      <button
                        key={l.id}
                        type="button"
                        onClick={() => handleToggleMemberInList(l.id)}
                        className={`btn btn-sm rounded-pill ${
                          inList ? 'btn-primary' : 'btn-outline-secondary'
                        }`}
                      >
                        {inList ? '✓ ' : '+ '}
                        {l.name}
                      </button>
                    );
                  })}
                  <Link href="/my/lists" className="btn btn-sm btn-link text-decoration-none">
                    Manage Lists →
                  </Link>
                </div>
              </div>
            )}

            {reportDrawerOpen && user && (
              <form
                onSubmit={handleReportUser}
                className="p-3 mb-3 rounded-4 border bg-body-tertiary"
              >
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <strong className="small">🚩 Report @{profile.username}</strong>
                  <button
                    type="button"
                    onClick={() => setReportDrawerOpen(false)}
                    className="btn-close btn-sm"
                  />
                </div>
                <div className="mb-2">
                  <select
                    value={reportType}
                    onChange={(e) => setReportType(e.target.value)}
                    className="form-select form-select-sm"
                  >
                    <option value="Inappropriate content">Inappropriate content</option>
                    <option value="Impersonation or fake account">
                      Impersonation or fake account
                    </option>
                    <option value="Spam or scam">Spam or scam</option>
                    <option value="Harassment">Harassment</option>
                  </select>
                </div>
                <div className="input-group input-group-sm">
                  <input
                    type="text"
                    value={reportDetails}
                    onChange={(e) => setReportDetails(e.target.value)}
                    placeholder="Reason details (optional)..."
                    className="form-control"
                  />
                  <button type="submit" disabled={busy} className="btn btn-danger fw-semibold">
                    Submit Report
                  </button>
                </div>
              </form>
            )}

            <h4 className="fw-bold mb-0">
              {profile.name} {profile.is_verified && <span title="Verified Creator">✅</span>}
            </h4>
            <div className="text-muted small mb-2">
              @{profile.username}
              {profile.gender_pronoun ? ` · ${profile.gender_pronoun}` : ''}
            </div>

            <p className="mb-2" style={{ whiteSpace: 'pre-wrap' }}>
              {profile.bio || 'No bio added yet.'}
            </p>

            <div className="d-flex flex-wrap gap-3 small text-muted mb-3">
              {profile.location && <span>📍 {profile.location}</span>}
              {profile.website && (
                <a
                  href={profile.website}
                  target="_blank"
                  rel="noreferrer"
                  className="text-primary text-decoration-none"
                >
                  🔗 {profile.website}
                </a>
              )}
              <span>
                <strong>{profile.fans_count}</strong> Subscribers
              </span>
              {typeof profile.followers_count === 'number' && (
                <span>
                  <strong>{profile.followers_count}</strong> Followers
                </span>
              )}
              <span>
                <strong>{profile.following_count}</strong> Following
              </span>
            </div>

            {/* Creator Stories & Highlights Strip */}
            {stories.length > 0 && (
              <div className="d-flex gap-3 overflow-auto py-2 mb-2">
                {stories.map((s) => (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => handleOpenStory(s)}
                    className="btn p-0 border-0 text-center flex-shrink-0"
                    style={{ width: 68 }}
                  >
                    <div
                      className="rounded-circle p-1 mx-auto"
                      style={{
                        width: 58,
                        height: 58,
                        background: s.is_highlight
                          ? 'linear-gradient(135deg, #f59e0b, #ec4899)'
                          : 'linear-gradient(135deg, #6366f1, #ec4899)',
                      }}
                    >
                      <img
                        src={s.media_url || profile.avatar || '/img/default-avatar.jpg'}
                        alt={s.caption}
                        className="rounded-circle border border-2 border-white w-100 h-100"
                        style={{ objectFit: 'cover' }}
                      />
                    </div>
                    <div className="small text-truncate mt-1" style={{ fontSize: 11 }}>
                      {s.is_highlight ? '⭐ Highlight' : 'Story'}
                    </div>
                  </button>
                ))}
              </div>
            )}

            {statusMessage && (
              <div className="alert alert-info py-2 small mb-3">{statusMessage}</div>
            )}

            {/* Subscription Bundles Box */}
            {user?.id !== profile.id && profile.paid_profile && profile.profile_access_price > 0 && (
              <div className="p-3 rounded-4 border bg-body-tertiary">
                {profile.is_subscribed ? (
                  <div className="d-flex justify-content-between align-items-center">
                    <div>
                      <span className="badge bg-success me-2">Active Subscription</span>
                      <span className="small text-muted">
                        You have full access to @{profile.username}&apos;s subscriber posts.
                      </span>
                    </div>
                    <Link
                      href="/my/settings?tab=subscriptions"
                      className="btn btn-sm btn-outline-secondary rounded-pill"
                    >
                      Manage
                    </Link>
                  </div>
                ) : (
                  <div>
                    <div className="fw-bold small text-uppercase text-muted mb-2">
                      Subscription Bundles
                    </div>
                    <div className="d-grid gap-2">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => handleSubscribe(1)}
                        className="btn btn-primary rounded-pill py-2 fw-bold d-flex justify-content-between px-4"
                      >
                        <span>SUBSCRIBE — 1 MONTH</span>
                        <span>${profile.profile_access_price.toFixed(2)} / month</span>
                      </button>
                      <div className="row g-2">
                        <div className="col-4">
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => handleSubscribe(3)}
                            className="btn btn-outline-primary btn-sm w-100 rounded-pill"
                          >
                            3 Mo (${profile.profile_access_price_3_months.toFixed(2)}/mo)
                          </button>
                        </div>
                        <div className="col-4">
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => handleSubscribe(6)}
                            className="btn btn-outline-primary btn-sm w-100 rounded-pill"
                          >
                            6 Mo (${profile.profile_access_price_6_months.toFixed(2)}/mo)
                          </button>
                        </div>
                        <div className="col-4">
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => handleSubscribe(12)}
                            className="btn btn-outline-primary btn-sm w-100 rounded-pill"
                          >
                            12 Mo (${profile.profile_access_price_12_months.toFixed(2)}/mo)
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Content Tabs */}
          <div className="nav nav-pills nav-justified border-bottom p-2 gap-1">
            <button
              type="button"
              onClick={() => {
                setActiveTab('posts');
                loadProfile('');
              }}
              className={`nav-link rounded-pill fw-bold ${
                activeTab === 'posts' ? 'active' : ''
              }`}
            >
              📝 Posts ({profile.posts_count})
            </button>
            <button
              type="button"
              onClick={() => {
                setActiveTab('media');
                loadProfile('media');
              }}
              className={`nav-link rounded-pill fw-bold ${
                activeTab === 'media' ? 'active' : ''
              }`}
            >
              🖼️ Media ({profile.media_count})
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('reels')}
              className={`nav-link rounded-pill fw-bold ${
                activeTab === 'reels' ? 'active' : ''
              }`}
            >
              🎬 Reels ({reels.length})
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('streams')}
              className={`nav-link rounded-pill fw-bold ${
                activeTab === 'streams' ? 'active' : ''
              }`}
            >
              📡 Streams ({streams.length})
            </button>
          </div>

          {/* Tab Content */}
          {activeTab === 'streams' ? (
            <div className="p-3">
              {streams.length === 0 ? (
                <div className="text-center text-muted py-5">No streams found for this creator.</div>
              ) : (
                streams.map((s) => (
                  <div key={s.id} className="card border rounded-4 p-3 mb-3">
                    <div className="d-flex justify-content-between align-items-center">
                      <div>
                        <span
                          className={`badge me-2 ${
                            s.status === 'in-progress' ? 'bg-danger' : 'bg-secondary'
                          }`}
                        >
                          {s.status === 'in-progress' ? '🔴 LIVE' : 'VOD'}
                        </span>
                        <strong>{s.name}</strong>
                        <div className="small text-muted mt-1">
                          👁️ {s.viewer_count} viewers · {s.created_at}
                        </div>
                      </div>
                      <Link
                        href={`/stream/${s.id}/${s.slug}`}
                        className="btn btn-sm btn-primary rounded-pill px-3"
                      >
                        Watch →
                      </Link>
                    </div>
                  </div>
                ))
              )}
            </div>
          ) : activeTab === 'reels' ? (
            <div className="p-3">
              {reels.length === 0 ? (
                <div className="text-center text-muted py-5">No reels uploaded yet.</div>
              ) : (
                <div className="row g-3">
                  {reels.map((r) => (
                    <div key={r.id} className="col-6 col-md-4">
                      <div className="card border rounded-4 overflow-hidden h-100 shadow-sm">
                        <div className="position-relative bg-dark" style={{ height: 220 }}>
                          {r.is_unlocked && r.video_url ? (
                            <video
                              src={r.video_url}
                              poster={r.cover_url}
                              controls
                              className="w-100 h-100"
                              style={{ objectFit: 'cover' }}
                            />
                          ) : (
                            <img
                              src={r.cover_url || '/img/post-locked.svg'}
                              alt={r.caption}
                              className="w-100 h-100"
                              style={{ objectFit: 'cover' }}
                            />
                          )}
                          {r.price > 0 && (
                            <span className="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
                              PPV ${r.price.toFixed(2)}
                            </span>
                          )}
                        </div>
                        <div className="card-body p-2">
                          <div className="small fw-semibold text-truncate">{r.caption}</div>
                          <div className="d-flex justify-content-between align-items-center mt-1 small text-muted">
                            <span>👁️ {r.views_count}</span>
                            <Link href="/reels" className="btn btn-sm btn-link p-0 text-decoration-none">
                              Open in Reels →
                            </Link>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : activeTab === 'media' ? (
            <div>
              <div className="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-body-tertiary">
                <span className="small text-muted fw-semibold">
                  {recentMedia.length} unlocked media items
                </span>
                <div className="btn-group btn-group-sm">
                  <button
                    type="button"
                    onClick={() => setMediaViewMode('grid')}
                    className={`btn ${
                      mediaViewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary'
                    }`}
                  >
                    ⊞ Grid
                  </button>
                  <button
                    type="button"
                    onClick={() => setMediaViewMode('feed')}
                    className={`btn ${
                      mediaViewMode === 'feed' ? 'btn-primary' : 'btn-outline-secondary'
                    }`}
                  >
                    ☰ Feed
                  </button>
                </div>
              </div>
              {mediaViewMode === 'grid' ? (
                <div className="p-3">
                  {recentMedia.length === 0 ? (
                    <div className="text-center text-muted py-5">
                      {profile.paid_profile && !profile.is_subscribed
                        ? 'Subscribe to unlock this creator’s media gallery.'
                        : 'No media items found.'}
                    </div>
                  ) : (
                    <div className="row g-2">
                      {recentMedia.map((m) => (
                        <div key={m.id} className="col-4">
                          <div className="border rounded-3 overflow-hidden bg-body-tertiary">
                            {m.file_type === 'video' ? (
                              <video
                                src={m.file_url}
                                controls
                                style={{ height: 165, width: '100%', objectFit: 'cover' }}
                              />
                            ) : (
                              <img
                                src={m.file_url}
                                alt={m.filename}
                                style={{ height: 165, width: '100%', objectFit: 'cover' }}
                              />
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : posts.length === 0 ? (
                <div className="p-5 text-center text-muted">No media posts found.</div>
              ) : (
                posts.map((post) => (
                  <PostCard key={post.id} post={post} onUpdated={() => loadProfile('media')} />
                ))
              )}
            </div>
          ) : posts.length === 0 ? (
            <div className="p-5 text-center text-muted">No posts matching this filter.</div>
          ) : (
            posts.map((post) => (
              <PostCard key={post.id} post={post} onUpdated={() => loadProfile()} />
            ))
          )}
        </div>

        {/* Right Sidebar Widgets */}
        <div className="col-12 col-lg-4 p-3">
          <div className="card border rounded-4 p-3 mb-3">
            <h6 className="fw-bold text-uppercase small text-muted mb-3">Recent Media</h6>
            {recentMedia.length === 0 ? (
              <div className="small text-muted">
                {profile.paid_profile && !profile.is_subscribed
                  ? 'Subscribe to unlock creator media gallery.'
                  : 'No media uploaded yet.'}
              </div>
            ) : (
              <div className="row g-2">
                {recentMedia.slice(0, 6).map((m) => (
                  <div key={m.id} className="col-4">
                    <img
                      src={m.file_url}
                      alt={m.filename}
                      className="img-fluid rounded-3 border"
                      style={{ height: 85, width: '100%', objectFit: 'cover' }}
                    />
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Story Lightbox Modal */}
      {activeStory && (
        <div
          className="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center z-3"
          style={{ backgroundColor: 'rgba(0,0,0,0.85)' }}
          onClick={() => setActiveStory(null)}
        >
          <div
            className="card border-0 rounded-4 overflow-hidden shadow-lg text-white"
            style={{ width: 380, maxWidth: '92vw', backgroundColor: '#111' }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-3 d-flex justify-content-between align-items-center border-bottom border-secondary">
              <div className="d-flex align-items-center gap-2">
                <img
                  src={activeStory.user_avatar || '/img/default-avatar.jpg'}
                  alt={activeStory.user_name}
                  className="rounded-circle"
                  style={{ width: 36, height: 36, objectFit: 'cover' }}
                />
                <div>
                  <div className="fw-bold small">{activeStory.user_name}</div>
                  <div className="text-white-50" style={{ fontSize: 11 }}>
                    👁️ {activeStory.views_count} views
                  </div>
                </div>
              </div>
              <div className="d-flex align-items-center gap-1">
                {user && (user.id === activeStory.user_id || user.role_id === 1) && (
                  <>
                    <button
                      type="button"
                      onClick={async () => {
                        try {
                          const res = await apiFetch(`/api/stories/${activeStory.id}/pin`, {
                            method: 'POST',
                          });
                          setStories((prev) =>
                            prev.map((s) =>
                              s.id === activeStory.id ? { ...s, is_highlight: res.is_highlight } : s
                            )
                          );
                          setActiveStory((prev) =>
                            prev ? { ...prev, is_highlight: res.is_highlight } : null
                          );
                        } catch {
                          // ignore
                        }
                      }}
                      className={`btn btn-sm rounded-pill ${
                        activeStory.is_highlight ? 'btn-warning' : 'btn-outline-light'
                      }`}
                      title="Pin or unpin story highlight"
                    >
                      ⭐
                    </button>
                    <button
                      type="button"
                      onClick={async () => {
                        try {
                          await apiFetch(`/api/stories/${activeStory.id}`, { method: 'DELETE' });
                          setStories((prev) => prev.filter((s) => s.id !== activeStory.id));
                          setActiveStory(null);
                        } catch {
                          // ignore
                        }
                      }}
                      className="btn btn-sm btn-outline-danger rounded-pill"
                      title="Delete story"
                    >
                      🗑️
                    </button>
                  </>
                )}
                <button
                  type="button"
                  onClick={() => setActiveStory(null)}
                  className="btn btn-sm btn-outline-light rounded-pill"
                >
                  ✕
                </button>
              </div>
            </div>
            <div className="p-3 text-center">
              {activeStory.media_url ? (
                activeStory.media_type === 'video' ||
                activeStory.media_url.endsWith('.mp4') ||
                activeStory.media_url.endsWith('.webm') ? (
                  <video
                    src={activeStory.media_url}
                    controls
                    autoPlay
                    muted
                    playsInline
                    loop
                    className="w-100 rounded-3 mb-3 bg-black"
                    style={{ maxHeight: 380, objectFit: 'contain' }}
                  />
                ) : (
                  <img
                    src={activeStory.media_url}
                    alt={activeStory.caption}
                    className="img-fluid rounded-3 mb-3"
                    style={{ maxHeight: 380, objectFit: 'contain' }}
                  />
                )
              ) : null}
              <p className="mb-0 fw-semibold">{activeStory.caption}</p>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

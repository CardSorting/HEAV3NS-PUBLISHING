'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { apiFetch, Attachment, PostItem, StreamItem, UserList, UserProfile } from '@/lib/api';
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
  const [userLists, setUserLists] = useState<UserList[]>([]);
  const [listDrawerOpen, setListDrawerOpen] = useState(false);
  const [reportDrawerOpen, setReportDrawerOpen] = useState(false);
  const [reportType, setReportType] = useState('Inappropriate content');
  const [reportDetails, setReportDetails] = useState('');
  const [activeTab, setActiveTab] = useState<'posts' | 'media' | 'streams'>('posts');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  const loadProfile = async (filter = '') => {
    setLoading(true);
    try {
      const res = await apiFetch(
        `/api/profile/${encodeURIComponent(username)}${filter ? `?filter=${filter}` : ''}`
      );
      setProfile(res.profile);
      setPosts(res.posts || []);
      setRecentMedia(res.recent_media || []);
      setStreams(res.streams || []);
    } catch {
      setProfile(null);
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
          <h4 className="fw-bold">Creator @{username} not found</h4>
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
            <div className="position-absolute top-0 start-0 p-3 d-flex gap-2">
              <span className="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                📝 {profile.posts_count} posts
              </span>
              <span className="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                🖼️ {profile.media_count} media
              </span>
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
                          onClick={handleToggleListDrawer}
                          className="btn btn-outline-secondary rounded-pill px-3"
                          title="Add to custom list"
                        >
                          📋 Lists
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
              <span>
                <strong>{profile.following_count}</strong> Following
              </span>
            </div>

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
          <div className="nav nav-pills nav-justified border-bottom p-2">
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
    </AppShell>
  );
}

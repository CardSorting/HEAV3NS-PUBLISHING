'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, ReelComment, ReelItem } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

export default function ReelsPage() {
  const { user, refreshUser } = useAuth();
  const [reels, setReels] = useState<ReelItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeCommentReelId, setActiveCommentReelId] = useState<number | null>(null);
  const [commentsMap, setCommentsMap] = useState<Record<number, ReelComment[]>>({});
  const [commentInput, setCommentInput] = useState('');
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  const loadReels = () => {
    setLoading(true);
    apiFetch('/api/reels')
      .then((res) => setReels(res.reels || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadReels();
  }, []);

  const handleTrackView = async (reelId: number) => {
    try {
      const res = await apiFetch(`/api/reels/${reelId}/view`, { method: 'POST' });
      setReels((prev) =>
        prev.map((r) => (r.id === reelId ? { ...r, views_count: res.views_count } : r))
      );
    } catch {
      // ignore
    }
  };

  const handleUnlockReel = async (reel: ReelItem) => {
    setBusy(true);
    setFeedback(null);
    try {
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'reel-unlock',
          reel_id: reel.id,
          recipient_user_id: reel.user_id,
          provider: 'credit',
        }),
      });
      await refreshUser();
      loadReels();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleToggleLike = async (reelId: number) => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/posts/react', {
        method: 'POST',
        body: JSON.stringify({ reel_id: reelId }),
      });
      setReels((prev) =>
        prev.map((r) =>
          r.id === reelId ? { ...r, user_liked: res.liked, likes_count: res.likes_count } : r
        )
      );
    } catch {
      // ignore
    }
  };

  const handleToggleBookmark = async (reelId: number) => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/posts/bookmark', {
        method: 'POST',
        body: JSON.stringify({ reel_id: reelId }),
      });
      setReels((prev) =>
        prev.map((r) => (r.id === reelId ? { ...r, user_bookmarked: res.bookmarked } : r))
      );
    } catch {
      // ignore
    }
  };

  const handleDeleteReel = async (reelId: number) => {
    try {
      await apiFetch(`/api/reels/${reelId}`, { method: 'DELETE' });
      setReels((prev) => prev.filter((r) => r.id !== reelId));
    } catch {
      // ignore
    }
  };

  const handleOpenComments = async (reelId: number) => {
    if (activeCommentReelId === reelId) {
      setActiveCommentReelId(null);
      return;
    }
    setActiveCommentReelId(reelId);
    try {
      const res = await apiFetch(`/api/reels/${reelId}/comments`);
      setCommentsMap((prev) => ({ ...prev, [reelId]: res.comments || [] }));
    } catch {
      // ignore
    }
  };

  const handleAddComment = async (e: React.FormEvent, reelId: number) => {
    e.preventDefault();
    if (!commentInput.trim() || !user) return;
    setBusy(true);
    try {
      const res = await apiFetch('/api/reels/comments', {
        method: 'POST',
        body: JSON.stringify({ reel_id: reelId, message: commentInput }),
      });
      setCommentsMap((prev) => ({
        ...prev,
        [reelId]: [...(prev[reelId] || []), res.comment],
      }));
      setReels((prev) =>
        prev.map((r) => (r.id === reelId ? { ...r, comments_count: r.comments_count + 1 } : r))
      );
      setCommentInput('');
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleDeleteReelComment = async (reelId: number, commentId: number) => {
    try {
      await apiFetch(`/api/reels/comments/${commentId}`, { method: 'DELETE' });
      setCommentsMap((prev) => ({
        ...prev,
        [reelId]: (prev[reelId] || []).filter((c) => c.id !== commentId),
      }));
      setReels((prev) =>
        prev.map((r) =>
          r.id === reelId ? { ...r, comments_count: Math.max(0, r.comments_count - 1) } : r
        )
      );
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        <div className="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
          <div>
            <h4 className="fw-bold mb-0">🎬 Creator Reels</h4>
            <p className="small text-muted mb-0">
              Short-form vertical videos and behind-the-scenes clips from creators.
            </p>
          </div>
          <Link href="/reels/create" className="btn btn-primary rounded-pill px-4 fw-bold">
            + Create Reel
          </Link>
        </div>

        {feedback && <div className="alert alert-danger py-2 small mb-3">{feedback}</div>}

        {loading ? (
          <div className="p-5 text-center text-muted">Loading Reels...</div>
        ) : (
          <div className="row g-4">
            {reels.map((r) => (
              <div key={r.id} className="col-12 col-md-6 col-lg-4">
                <div className="card border rounded-4 overflow-hidden shadow-sm h-100 d-flex flex-column">
                  <div className="position-relative bg-dark" style={{ height: 340 }}>
                    {r.is_unlocked && r.video_url ? (
                      <video
                        src={r.video_url}
                        poster={r.cover_url || '/img/home-scene-1.svg'}
                        controls
                        playsInline
                        loop
                        muted
                        onPlay={() => handleTrackView(r.id)}
                        className="w-100 h-100"
                        style={{ objectFit: 'cover' }}
                      />
                    ) : (
                      <div className="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white p-3 text-center">
                        <img
                          src="/img/post-locked.svg"
                          alt="Locked Reel"
                          style={{ height: 110 }}
                          className="mb-2"
                        />
                        <div className="fw-bold small mb-2">
                          Pay-Per-View Reel (${r.price.toFixed(2)})
                        </div>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => handleUnlockReel(r)}
                          className="btn btn-sm btn-warning rounded-pill px-3 fw-bold"
                        >
                          Unlock Reel for ${r.price.toFixed(2)}
                        </button>
                      </div>
                    )}
                    <span className="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-2">
                      👁️ {r.views_count} views
                    </span>
                    {r.price > 0 && (
                      <span className="badge bg-warning text-dark position-absolute bottom-0 start-0 m-2">
                        PPV ${r.price.toFixed(2)}
                      </span>
                    )}
                    {user && (user.id === r.user_id || user.role_id === 1) && (
                      <button
                        type="button"
                        onClick={() => handleDeleteReel(r.id)}
                        className="btn btn-sm btn-danger rounded-pill position-absolute top-0 end-0 m-2"
                        title="Delete Reel"
                      >
                        🗑️
                      </button>
                    )}
                  </div>

                  <div className="card-body d-flex flex-column justify-content-between p-3">
                    <div>
                      <div className="d-flex align-items-center gap-2 mb-2">
                        <img
                          src={r.user_avatar || '/img/default-avatar.jpg'}
                          alt={r.user_name}
                          className="rounded-circle border"
                          style={{ width: 34, height: 34, objectFit: 'cover' }}
                        />
                        <Link
                          href={`/${r.user_username}`}
                          className="fw-bold text-reset text-decoration-none small"
                        >
                          {r.user_name} {r.user_verified && '✅'}
                        </Link>
                      </div>
                      <p className="small mb-3">{r.caption}</p>
                    </div>

                    <div>
                      <div className="d-flex justify-content-between align-items-center pt-2 border-top">
                        <div className="d-flex gap-2">
                          <button
                            type="button"
                            onClick={() => handleToggleLike(r.id)}
                            className={`btn btn-sm rounded-pill ${
                              r.user_liked ? 'btn-danger' : 'btn-outline-secondary'
                            }`}
                          >
                            {r.user_liked ? '❤️' : '🤍'} {r.likes_count}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleOpenComments(r.id)}
                            className="btn btn-sm btn-outline-secondary rounded-pill"
                          >
                            💬 {r.comments_count}
                          </button>
                        </div>

                        <button
                          type="button"
                          onClick={() => handleToggleBookmark(r.id)}
                          className={`btn btn-sm rounded-pill ${
                            r.user_bookmarked ? 'btn-primary' : 'btn-outline-secondary'
                          }`}
                          title="Bookmark Reel"
                        >
                          {r.user_bookmarked ? '🔖 Saved' : '🔖'}
                        </button>
                      </div>

                      {activeCommentReelId === r.id && (
                        <div className="mt-3 pt-2 border-top">
                          <div
                            className="d-grid gap-2 mb-2 overflow-auto"
                            style={{ maxHeight: 160 }}
                          >
                            {(commentsMap[r.id] || []).length === 0 ? (
                              <div className="small text-muted">No comments yet.</div>
                            ) : (
                              (commentsMap[r.id] || []).map((c) => (
                                <div key={c.id} className="small p-2 rounded-3 bg-body-tertiary">
                                  <div className="d-flex justify-content-between align-items-center">
                                    <strong>@{c.user_username}</strong>
                                    <div className="d-flex align-items-center gap-1">
                                      <span className="text-muted" style={{ fontSize: 11 }}>
                                        {c.created_at}
                                      </span>
                                      {user &&
                                        (user.id === c.user_id ||
                                          user.id === r.user_id ||
                                          user.role_id === 1) && (
                                          <button
                                            type="button"
                                            onClick={() => handleDeleteReelComment(r.id, c.id)}
                                            className="btn btn-link btn-sm p-0 text-danger text-decoration-none"
                                            style={{ fontSize: 11 }}
                                            title="Delete comment"
                                          >
                                            ✕
                                          </button>
                                        )}
                                    </div>
                                  </div>
                                  <div>{c.message}</div>
                                </div>
                              ))
                            )}
                          </div>
                          {user && r.is_unlocked && (
                            <form
                              onSubmit={(e) => handleAddComment(e, r.id)}
                              className="d-flex gap-1"
                            >
                              <input
                                type="text"
                                value={commentInput}
                                onChange={(e) => setCommentInput(e.target.value)}
                                placeholder="Comment on reel..."
                                className="form-control form-control-sm rounded-pill"
                              />
                              <button
                                type="submit"
                                disabled={busy}
                                className="btn btn-sm btn-primary rounded-pill px-3"
                              >
                                Send
                              </button>
                            </form>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}

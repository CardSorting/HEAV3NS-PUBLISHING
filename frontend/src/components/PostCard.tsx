'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { apiFetch, PostItem, UserProfile } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';

export function PostCard({
  post: initialPost,
  onUpdated,
  onDeleted,
}: {
  post: PostItem;
  onUpdated?: () => void;
  onDeleted?: (id: number) => void;
}) {
  const { user, refreshUser } = useAuth();
  const [post, setPost] = useState<PostItem>(initialPost);
  const [showComments, setShowComments] = useState(false);
  const [commentText, setCommentText] = useState('');
  const [tipModalOpen, setTipModalOpen] = useState(false);
  const [tipAmount, setTipAmount] = useState('10');
  const [reportModalOpen, setReportModalOpen] = useState(false);
  const [reportType, setReportType] = useState('Inappropriate content');
  const [reportDetails, setReportDetails] = useState('');
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  const handleLike = async () => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/posts/react', {
        method: 'POST',
        body: JSON.stringify({ post_id: post.id }),
      });
      setPost((p) => ({
        ...p,
        user_liked: res.liked,
        likes_count: res.likes_count,
      }));
    } catch (e: any) {
      setFeedback(e.message);
    }
  };

  const handleBookmark = async () => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/posts/bookmark', {
        method: 'POST',
        body: JSON.stringify({ post_id: post.id }),
      });
      setPost((p) => ({
        ...p,
        user_bookmarked: res.bookmarked,
      }));
    } catch (e: any) {
      setFeedback(e.message);
    }
  };

  const handlePin = async () => {
    try {
      const res = await apiFetch('/api/posts/pin', {
        method: 'POST',
        body: JSON.stringify({ post_id: post.id }),
      });
      setPost((p) => ({ ...p, is_pinned: res.is_pinned }));
    } catch (e: any) {
      setFeedback(e.message);
    }
  };

  const handleDelete = async () => {
    try {
      await apiFetch(`/api/posts/${post.id}`, { method: 'DELETE' });
      onDeleted?.(post.id);
    } catch (e: any) {
      setFeedback(e.message);
    }
  };

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!commentText.trim()) return;
    setBusy(true);
    try {
      const res = await apiFetch('/api/posts/comments', {
        method: 'POST',
        body: JSON.stringify({ post_id: post.id, message: commentText }),
      });
      setPost((p) => ({
        ...p,
        comments_count: p.comments_count + 1,
        comments: [...p.comments, res.comment],
      }));
      setCommentText('');
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleLikeComment = async (commentId: number) => {
    if (!user) return;
    try {
      const res = await apiFetch('/api/posts/react', {
        method: 'POST',
        body: JSON.stringify({ comment_id: commentId }),
      });
      setPost((p) => ({
        ...p,
        comments: p.comments.map((c) =>
          c.id === commentId ? { ...c, user_liked: res.liked, likes_count: res.likes_count } : c
        ),
      }));
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const handleDeleteComment = async (commentId: number) => {
    try {
      await apiFetch(`/api/posts/comments/${commentId}`, { method: 'DELETE' });
      setPost((p) => ({
        ...p,
        comments_count: Math.max(0, p.comments_count - 1),
        comments: p.comments.filter((c) => c.id !== commentId),
      }));
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const handleSubmitReport = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) return;
    setBusy(true);
    try {
      const res = await apiFetch('/api/report', {
        method: 'POST',
        body: JSON.stringify({
          reported_user_id: post.user_id,
          post_id: post.id,
          report_type: reportType,
          details: reportDetails,
        }),
      });
      setReportModalOpen(false);
      setReportDetails('');
      setFeedback(res.message);
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleVotePoll = async (pollId: number, answerId: number) => {
    try {
      const res = await apiFetch('/api/posts/polls/vote', {
        method: 'POST',
        body: JSON.stringify({ poll_id: pollId, answer_id: answerId }),
      });
      setPost((p) => ({ ...p, poll: res.poll }));
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const handleUnlockPost = async () => {
    setBusy(true);
    setFeedback(null);
    try {
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'post-unlock',
          post_id: post.id,
          provider: 'credit',
        }),
      });
      const refreshed = await apiFetch(`/api/posts/${post.id}`);
      setPost(refreshed.post);
      await refreshUser();
      onUpdated?.();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSubscribeCreator = async () => {
    setBusy(true);
    setFeedback(null);
    try {
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'subscription',
          recipient_user_id: post.user_id,
          provider: 'credit',
          months: 1,
        }),
      });
      const refreshed = await apiFetch(`/api/posts/${post.id}`);
      setPost(refreshed.post);
      await refreshUser();
      onUpdated?.();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSendTip = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setFeedback(null);
    try {
      const amt = parseFloat(tipAmount) || 5;
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'tip',
          post_id: post.id,
          recipient_user_id: post.user_id,
          amount: amt,
          provider: 'credit',
        }),
      });
      const refreshed = await apiFetch(`/api/posts/${post.id}`);
      setPost(refreshed.post);
      await refreshUser();
      setTipModalOpen(false);
      setFeedback(`Sent a $${amt.toFixed(2)} tip to ${post.user_name}!`);
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="card border-0 border-bottom rounded-0 mb-2 post-box" data-post-id={post.id}>
      <div className="card-body px-3 py-3">
        {/* Post Header */}
        <div className="d-flex justify-content-between align-items-start mb-2">
          <div className="d-flex align-items-center gap-2">
            <Link href={`/${post.user_username}`}>
              <img
                src={post.user_avatar || '/img/default-avatar.jpg'}
                alt={post.user_name}
                className="rounded-circle border"
                style={{ width: 46, height: 46, objectFit: 'cover' }}
              />
            </Link>
            <div>
              <div className="d-flex align-items-center gap-1">
                <Link
                  href={`/${post.user_username}`}
                  className="fw-bold text-reset text-decoration-none"
                >
                  {post.user_name}
                </Link>
                {post.user_verified && <span title="Verified Creator">✅</span>}
                {post.is_pinned && (
                  <span className="badge bg-primary-subtle text-primary ms-1">📌 Pinned</span>
                )}
                {post.price > 0 && (
                  <span className="badge bg-warning-subtle text-warning-emphasis ms-1">
                    PPV ${post.price.toFixed(2)}
                  </span>
                )}
              </div>
              <div className="small text-muted">
                <Link href={`/${post.user_username}`} className="text-muted text-decoration-none">
                  @{post.user_username}
                </Link>{' '}
                ·{' '}
                <Link
                  href={`/posts/${post.id}/${post.user_username}`}
                  className="text-muted text-decoration-none"
                >
                  {post.created_at}
                </Link>
              </div>
            </div>
          </div>

          {/* Author / Post Actions */}
          <div className="d-flex align-items-center gap-1">
            {user && user.id === post.user_id && (
              <>
                <button
                  type="button"
                  onClick={handlePin}
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                  title="Pin/Unpin post"
                >
                  📌
                </button>
                <Link
                  href={`/posts/edit/${post.id}`}
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                  title="Edit post"
                >
                  ✏️
                </Link>
                <button
                  type="button"
                  onClick={handleDelete}
                  className="btn btn-sm btn-outline-danger rounded-pill"
                  title="Delete post"
                >
                  🗑️
                </button>
              </>
            )}
            {user && user.id !== post.user_id && (
              <button
                type="button"
                onClick={() => setReportModalOpen(!reportModalOpen)}
                className="btn btn-sm btn-outline-secondary rounded-pill"
                title="Report post"
              >
                🚩
              </button>
            )}
          </div>
        </div>

        {/* Post Text */}
        <div className="mb-3" style={{ whiteSpace: 'pre-wrap' }}>
          {post.text}
        </div>

        {/* Post Media / Paywall */}
        {post.is_unlocked ? (
          <>
            {post.attachments && post.attachments.length > 0 && (
              <div className="mb-3 rounded-3 overflow-hidden border bg-body-tertiary text-center">
                {post.attachments.map((att) =>
                  att.file_type === 'video' ? (
                    <video
                      key={att.id}
                      src={att.file_url}
                      controls
                      className="w-100"
                      style={{ maxHeight: 460 }}
                    />
                  ) : (
                    <img
                      key={att.id}
                      src={att.file_url}
                      alt={att.filename}
                      className="img-fluid"
                      style={{ maxHeight: 460, objectFit: 'contain' }}
                    />
                  )
                )}
              </div>
            )}
          </>
        ) : (
          <div className="p-4 mb-3 rounded-4 border text-center bg-body-tertiary">
            <img
              src="/img/post-locked.svg"
              alt="Locked Content"
              style={{ height: 140 }}
              className="mb-3"
            />
            <h6 className="fw-bold mb-1">
              {!post.is_subbed
                ? `Subscribe to @${post.user_username} to unlock exclusive posts`
                : `Unlock this Pay-Per-View post for $${post.price.toFixed(2)}`}
            </h6>
            <p className="small text-muted mb-3">
              Instant access using your wallet balance or card checkout.
            </p>
            {!post.is_subbed ? (
              <button
                type="button"
                disabled={busy}
                onClick={handleSubscribeCreator}
                className="btn btn-primary rounded-pill px-4 fw-bold"
              >
                {busy
                  ? 'Processing...'
                  : `Subscribe for $${post.user_access_price.toFixed(2)}/mo`}
              </button>
            ) : (
              <button
                type="button"
                disabled={busy}
                onClick={handleUnlockPost}
                className="btn btn-primary rounded-pill px-4 fw-bold"
              >
                {busy ? 'Unlocking...' : `Unlock Post for $${post.price.toFixed(2)}`}
              </button>
            )}
          </div>
        )}

        {/* Poll */}
        {post.poll && post.is_unlocked && (
          <div className="p-3 mb-3 rounded-3 border bg-body-tertiary">
            <div className="small fw-bold text-uppercase text-muted mb-2">
              Creator Poll ({post.poll.total_votes} votes)
            </div>
            <div className="d-grid gap-2">
              {post.poll.answers.map((ans) => {
                const isSelected = post.poll?.user_voted_answer_id === ans.id;
                return (
                  <button
                    key={ans.id}
                    type="button"
                    onClick={() => handleVotePoll(post.poll!.id, ans.id)}
                    className={`btn text-start position-relative overflow-hidden ${
                      isSelected ? 'btn-primary' : 'btn-outline-secondary'
                    }`}
                  >
                    <div className="d-flex justify-content-between align-items-center">
                      <span>
                        {isSelected ? '✓ ' : ''}
                        {ans.answer}
                      </span>
                      <span className="small fw-semibold">
                        {ans.percentage}% ({ans.votes_count})
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {feedback && <div className="alert alert-info py-2 small mb-2">{feedback}</div>}

        {/* Post Footer Actions */}
        <div className="d-flex justify-content-between align-items-center pt-1">
          <div className="d-flex align-items-center gap-2">
            <button
              type="button"
              onClick={handleLike}
              className={`btn btn-sm rounded-pill ${
                post.user_liked ? 'btn-danger' : 'btn-outline-secondary'
              }`}
            >
              {post.user_liked ? '❤️' : '🤍'} {post.likes_count}
            </button>
            <button
              type="button"
              onClick={() => setShowComments(!showComments)}
              className="btn btn-sm btn-outline-secondary rounded-pill"
            >
              💬 {post.comments_count}
            </button>
            {user && user.id !== post.user_id && (
              <button
                type="button"
                onClick={() => setTipModalOpen(!tipModalOpen)}
                className="btn btn-sm btn-outline-success rounded-pill"
              >
                💵 Send Tip {post.tips_total > 0 ? `($${post.tips_total.toFixed(0)})` : ''}
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={handleBookmark}
            className={`btn btn-sm rounded-pill ${
              post.user_bookmarked ? 'btn-primary' : 'btn-outline-secondary'
            }`}
            title="Bookmark post"
          >
            {post.user_bookmarked ? '🔖 Saved' : '🔖 Bookmark'}
          </button>
        </div>

        {/* Tip Inline Drawer */}
        {tipModalOpen && (
          <form onSubmit={handleSendTip} className="mt-3 p-3 rounded-3 border bg-body-tertiary">
            <div className="d-flex align-items-center justify-content-between mb-2">
              <strong className="small">Send a Tip to {post.user_name}</strong>
              <button
                type="button"
                onClick={() => setTipModalOpen(false)}
                className="btn-close btn-sm"
              />
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

        {/* Report Inline Drawer */}
        {reportModalOpen && (
          <form onSubmit={handleSubmitReport} className="mt-3 p-3 rounded-3 border bg-body-tertiary">
            <div className="d-flex align-items-center justify-content-between mb-2">
              <strong className="small">🚩 Report Post</strong>
              <button
                type="button"
                onClick={() => setReportModalOpen(false)}
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
                <option value="Spam or misleading">Spam or misleading</option>
                <option value="Copyright infringement">Copyright infringement</option>
                <option value="Harassment">Harassment</option>
              </select>
            </div>
            <div className="input-group input-group-sm">
              <input
                type="text"
                value={reportDetails}
                onChange={(e) => setReportDetails(e.target.value)}
                placeholder="Additional details (optional)..."
                className="form-control"
              />
              <button type="submit" disabled={busy} className="btn btn-danger fw-semibold">
                Submit Report
              </button>
            </div>
          </form>
        )}

        {/* Comments Drawer */}
        {showComments && (
          <div className="mt-3 pt-3 border-top">
            <div className="d-grid gap-2 mb-3">
              {post.comments.length === 0 ? (
                <div className="small text-muted">No comments yet. Start the conversation!</div>
              ) : (
                post.comments.map((c) => (
                  <div key={c.id} className="d-flex gap-2 align-items-start small">
                    <img
                      src={c.user_avatar || '/img/default-avatar.jpg'}
                      alt={c.user_name}
                      className="rounded-circle"
                      style={{ width: 28, height: 28, objectFit: 'cover' }}
                    />
                    <div className="flex-fill p-2 rounded-3 bg-body-tertiary">
                      <div className="d-flex justify-content-between align-items-center">
                        <div>
                          <Link
                            href={`/${c.user_username}`}
                            className="fw-bold text-reset text-decoration-none me-2"
                          >
                            {c.user_name}
                          </Link>
                          <span className="text-muted" style={{ fontSize: 11 }}>
                            {c.created_at}
                          </span>
                        </div>
                        <div className="d-flex align-items-center gap-1">
                          <button
                            type="button"
                            onClick={() => handleLikeComment(c.id)}
                            className={`btn btn-sm py-0 px-2 rounded-pill ${
                              c.user_liked ? 'btn-danger' : 'btn-outline-secondary'
                            }`}
                            style={{ fontSize: 11 }}
                          >
                            {c.user_liked ? '❤️' : '🤍'} {c.likes_count}
                          </button>
                          {user && (user.id === c.user_id || user.role_id === 1) && (
                            <button
                              type="button"
                              onClick={() => handleDeleteComment(c.id)}
                              className="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill"
                              style={{ fontSize: 11 }}
                              title="Delete comment"
                            >
                              🗑️
                            </button>
                          )}
                        </div>
                      </div>
                      <div className="mt-1">{c.message}</div>
                    </div>
                  </div>
                ))
              )}
            </div>

            {user && (
              <form onSubmit={handleAddComment} className="d-flex gap-2">
                <input
                  type="text"
                  value={commentText}
                  onChange={(e) => setCommentText(e.target.value)}
                  placeholder="Add a comment..."
                  className="form-control form-control-sm rounded-pill"
                />
                <button
                  type="submit"
                  disabled={busy}
                  className="btn btn-sm btn-primary rounded-pill px-3"
                >
                  Post
                </button>
              </form>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

export function CreatorSuggestionCard({ profile }: { profile: UserProfile }) {
  return (
    <div className="card border rounded-4 overflow-hidden mb-3 shadow-sm">
      <div
        className="position-relative"
        style={{
          height: 85,
          backgroundImage: `url(${profile.cover || '/img/default-cover.png'})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        }}
      >
        <span className="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2">
          {profile.paid_profile && profile.profile_access_price > 0
            ? `$${profile.profile_access_price.toFixed(2)}/mo`
            : 'Free'}
        </span>
      </div>
      <div className="card-body pt-0 d-flex align-items-center gap-3">
        <img
          src={profile.avatar || '/img/default-avatar.jpg'}
          alt={profile.name}
          className="rounded-circle border border-3 border-white"
          style={{ width: 58, height: 58, marginTop: -24, objectFit: 'cover' }}
        />
        <div className="overflow-hidden flex-fill pt-1">
          <Link
            href={`/${profile.username}`}
            className="fw-bold text-reset text-decoration-none d-block text-truncate"
          >
            {profile.name} {profile.is_verified && '✅'}
          </Link>
          <div className="small text-muted text-truncate">@{profile.username}</div>
        </div>
        <Link
          href={`/${profile.username}`}
          className="btn btn-sm btn-outline-primary rounded-pill px-3"
        >
          View
        </Link>
      </div>
    </div>
  );
}

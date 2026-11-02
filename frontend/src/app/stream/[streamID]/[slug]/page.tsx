'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { apiFetch, StreamItem } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

export default function StreamWatchPage() {
  const params = useParams();
  const streamId = Number(params?.streamID);
  const { refreshUser } = useAuth();
  const [stream, setStream] = useState<StreamItem | null>(null);
  const [messages, setMessages] = useState<any[]>([]);
  const [chatInput, setChatInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  const loadStream = async () => {
    try {
      const res = await apiFetch(`/api/streams/${streamId}`);
      setStream(res.stream);
      setMessages(res.messages || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (streamId) loadStream();
  }, [streamId]);

  const handleUnlockStream = async () => {
    if (!stream) return;
    setBusy(true);
    setFeedback(null);
    try {
      if (stream.requires_subscription) {
        await apiFetch('/api/payments/initiate', {
          method: 'POST',
          body: JSON.stringify({
            payment_type: 'subscription',
            recipient_user_id: stream.user_id,
            months: 1,
            provider: 'credit',
          }),
        });
      }
      if (stream.price > 0) {
        await apiFetch('/api/payments/initiate', {
          method: 'POST',
          body: JSON.stringify({
            payment_type: 'stream-unlock',
            stream_id: stream.id,
            provider: 'credit',
          }),
        });
      }
      await refreshUser();
      await loadStream();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSendChat = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!chatInput.trim()) return;
    try {
      const res = await apiFetch('/api/streams/comment', {
        method: 'POST',
        body: JSON.stringify({
          stream_id: streamId,
          message: chatInput,
        }),
      });
      setMessages((prev) => [...prev, res.comment]);
      setChatInput('');
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        {loading ? (
          <div className="p-5 text-center text-muted">Connecting to stream...</div>
        ) : !stream ? (
          <div className="p-5 text-center">
            <h5 className="fw-bold">Stream not found</h5>
            <Link href="/my/streams" className="btn btn-primary rounded-pill mt-2">
              Back to Streams
            </Link>
          </div>
        ) : (
          <div className="row g-4">
            {/* Video Player Column */}
            <div className="col-12 col-lg-8">
              {stream.is_unlocked ? (
                <div className="rounded-4 overflow-hidden bg-dark text-white position-relative border shadow">
                  <img
                    src={stream.poster || '/img/live-stream-cover.svg'}
                    alt={stream.name}
                    className="w-100"
                    style={{ height: 420, objectFit: 'cover' }}
                  />
                  <div className="position-absolute top-0 start-0 m-3 d-flex gap-2">
                    <span
                      className={`badge px-3 py-2 ${
                        stream.status === 'in-progress' ? 'bg-danger' : 'bg-secondary'
                      }`}
                    >
                      {stream.status === 'in-progress' ? '🔴 LIVE BROADCAST' : 'ENDED VOD'}
                    </span>
                    <span className="badge bg-dark bg-opacity-75 px-3 py-2">
                      👁️ {stream.viewer_count} Viewers
                    </span>
                  </div>
                </div>
              ) : (
                <div
                  className="rounded-4 overflow-hidden bg-dark text-white p-5 text-center border shadow d-flex flex-column justify-content-center align-items-center"
                  style={{ minHeight: 420 }}
                >
                  <img
                    src="/img/post-locked.svg"
                    alt="Locked Stream"
                    style={{ height: 120 }}
                    className="mb-3"
                  />
                  <h5 className="fw-bold mb-1">🔒 VIP Live Stream</h5>
                  <p className="small text-light opacity-75 mb-3">
                    {stream.requires_subscription && stream.price > 0
                      ? `Requires active subscription to @${stream.user_username} + $${stream.price.toFixed(2)} stream ticket.`
                      : stream.requires_subscription
                      ? `Requires active subscription to @${stream.user_username}.`
                      : `Unlock this live stream for $${stream.price.toFixed(2)}.`}
                  </p>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={handleUnlockStream}
                    className="btn btn-primary rounded-pill px-4 fw-bold"
                  >
                    {busy ? 'Unlocking...' : 'Unlock Stream Access'}
                  </button>
                </div>
              )}

              {feedback && <div className="alert alert-warning mt-3 py-2 small">{feedback}</div>}

              <div className="d-flex justify-content-between align-items-center mt-3">
                <div className="d-flex align-items-center gap-3">
                  <img
                    src={stream.user_avatar}
                    alt={stream.user_name}
                    className="rounded-circle border"
                    style={{ width: 52, height: 52 }}
                  />
                  <div>
                    <h5 className="fw-bold mb-0">{stream.name}</h5>
                    <Link
                      href={`/${stream.user_username}`}
                      className="small text-muted text-decoration-none"
                    >
                      Hosted by @{stream.user_username}
                    </Link>
                  </div>
                </div>
                <Link
                  href={`/${stream.user_username}`}
                  className="btn btn-outline-primary rounded-pill px-4"
                >
                  Creator Profile
                </Link>
              </div>
            </div>

            {/* Live Stream Chat Column */}
            <div className="col-12 col-lg-4">
              <div className="card border rounded-4 h-100 d-flex flex-column" style={{ minHeight: 480 }}>
                <div className="card-header bg-body-tertiary fw-bold">💬 Live Stream Chat</div>
                <div className="card-body flex-fill overflow-auto d-flex flex-column gap-2">
                  {messages.map((m) => (
                    <div key={m.id} className="small p-2 rounded-3 bg-body-tertiary">
                      <strong className="me-1">@{m.user_username}:</strong>
                      <span>{m.message}</span>
                    </div>
                  ))}
                </div>
                <form onSubmit={handleSendChat} className="card-footer bg-transparent d-flex gap-2">
                  <input
                    type="text"
                    value={chatInput}
                    onChange={(e) => setChatInput(e.target.value)}
                    placeholder="Say something in live chat..."
                    className="form-control form-control-sm rounded-pill px-3"
                  />
                  <button type="submit" className="btn btn-sm btn-primary rounded-pill px-3">
                    Send
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppShell>
  );
}


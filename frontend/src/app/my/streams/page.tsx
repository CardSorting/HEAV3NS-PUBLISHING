'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, StreamItem } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function StreamsManagementPage() {
  const [streams, setStreams] = useState<StreamItem[]>([]);
  const [streamName, setStreamName] = useState('');
  const [streamPrice, setStreamPrice] = useState('0');
  const [requiresSub, setRequiresSub] = useState(false);
  const [loading, setLoading] = useState(true);

  const loadStreams = async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/api/streams');
      setStreams(res.streams || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadStreams();
  }, []);

  const handleStartStream = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!streamName.trim()) return;
    try {
      await apiFetch('/api/streams', {
        method: 'POST',
        body: JSON.stringify({
          name: streamName,
          price: parseFloat(streamPrice) || 0,
          requires_subscription: requiresSub,
          is_public: true,
        }),
      });
      setStreamName('');
      loadStreams();
    } catch {
      // ignore
    }
  };

  const handleStopStream = async (id: number) => {
    try {
      await apiFetch(`/api/streams/${id}/stop`, { method: 'POST' });
      loadStreams();
    } catch {
      // ignore
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 880 }}>
        <h4 className="fw-bold mb-3 pb-2 border-bottom">🔴 Live Streams & Broadcast Studio</h4>

        {/* Start New Stream Card */}
        <form onSubmit={handleStartStream} className="card border rounded-4 p-4 mb-4 bg-body-tertiary">
          <h6 className="fw-bold mb-3">Start a New Live Broadcast</h6>
          <div className="row g-3 align-items-end">
            <div className="col-12 col-md-6">
              <label className="form-label small fw-bold">Stream Title</label>
              <input
                type="text"
                required
                value={streamName}
                onChange={(e) => setStreamName(e.target.value)}
                placeholder="Live Q&A + Studio Session..."
                className="form-control rounded-pill px-3"
              />
            </div>
            <div className="col-6 col-md-3">
              <label className="form-label small fw-bold">Entry Price ($ USD)</label>
              <input
                type="number"
                min="0"
                step="1"
                value={streamPrice}
                onChange={(e) => setStreamPrice(e.target.value)}
                className="form-control rounded-pill px-3"
              />
            </div>
            <div className="col-6 col-md-3">
              <button type="submit" className="btn btn-danger w-100 rounded-pill fw-bold">
                🔴 Go Live Now
              </button>
            </div>
          </div>
          <div className="form-check form-switch mt-3">
            <input
              type="checkbox"
              id="reqSubSwitch"
              checked={requiresSub}
              onChange={(e) => setRequiresSub(e.target.checked)}
              className="form-check-input"
            />
            <label htmlFor="reqSubSwitch" className="form-check-label small fw-semibold">
              Require active creator subscription to join stream
            </label>
          </div>
        </form>

        {/* Active & Past Streams List */}
        <h6 className="fw-bold text-uppercase small text-muted mb-3">Active & Recent Streams</h6>
        {loading ? (
          <div className="p-4 text-center text-muted">Loading streams...</div>
        ) : (
          <div className="d-grid gap-3">
            {streams.map((s) => (
              <div key={s.id} className="card border rounded-4 p-3 shadow-sm">
                <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <div className="d-flex align-items-center gap-3">
                    <img
                      src={s.user_avatar}
                      alt={s.user_name}
                      className="rounded-circle border"
                      style={{ width: 48, height: 48 }}
                    />
                    <div>
                      <div className="d-flex align-items-center gap-2">
                        <span
                          className={`badge ${
                            s.status === 'in-progress' ? 'bg-danger' : 'bg-secondary'
                          }`}
                        >
                          {s.status === 'in-progress' ? '🔴 LIVE' : 'Ended'}
                        </span>
                        <strong>{s.name}</strong>
                      </div>
                      <div className="small text-muted mt-1">
                        Host: @{s.user_username} · 👁️ {s.viewer_count} viewers
                        {s.rtmp_key && ` · RTMP Key: ${s.rtmp_key}`}
                      </div>
                    </div>
                  </div>
                  <div className="d-flex gap-2">
                    <Link
                      href={`/stream/${s.id}/${s.slug}`}
                      className="btn btn-sm btn-primary rounded-pill px-3 fw-bold"
                    >
                      Open Stream Room
                    </Link>
                    {s.status === 'in-progress' && s.rtmp_key && (
                      <button
                        type="button"
                        onClick={() => handleStopStream(s.id)}
                        className="btn btn-sm btn-outline-danger rounded-pill px-3"
                      >
                        End Stream
                      </button>
                    )}
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


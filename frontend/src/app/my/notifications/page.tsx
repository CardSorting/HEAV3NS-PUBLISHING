'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, NotificationItem } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

export default function NotificationsPage() {
  const { refreshUser } = useAuth();
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [filter, setFilter] = useState('all');
  const [loading, setLoading] = useState(true);

  const loadNotifications = async (f = filter) => {
    setLoading(true);
    try {
      const res = await apiFetch(`/api/notifications?filter=${encodeURIComponent(f)}`);
      setNotifications(res.notifications || []);
      await refreshUser();
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadNotifications(filter);
  }, [filter]);

  const tabs = [
    { key: 'all', label: 'All' },
    { key: 'like', label: '❤️ Likes' },
    { key: 'comment', label: '💬 Comments' },
    { key: 'subscription', label: '💎 Subscriptions' },
    { key: 'tip', label: '💵 Tips' },
    { key: 'message', label: '✉️ Messages' },
  ];

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 820 }}>
        <h4 className="fw-bold mb-3">Notifications</h4>

        <div className="nav nav-pills gap-2 mb-4">
          {tabs.map((t) => (
            <button
              key={t.key}
              type="button"
              onClick={() => setFilter(t.key)}
              className={`nav-link rounded-pill py-1 px-3 small fw-bold ${
                filter === t.key ? 'active' : 'border'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {loading ? (
          <div className="p-5 text-center text-muted">Loading notifications...</div>
        ) : notifications.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <img
              src="/img/no-notifications.svg"
              alt="No notifications"
              style={{ height: 120 }}
              className="mb-3"
            />
            <h6 className="fw-bold">No notifications found</h6>
            <p className="small text-muted mb-0">
              New likes, comments, subscriptions, and tips will appear here.
            </p>
          </div>
        ) : (
          <div className="list-group rounded-4 overflow-hidden">
            {notifications.map((n) => (
              <div
                key={n.id}
                className="list-group-item d-flex align-items-center justify-content-between p-3"
              >
                <div className="d-flex align-items-center gap-3">
                  <img
                    src={n.from_user_avatar || '/img/default-avatar.jpg'}
                    alt={n.from_user_name || 'User'}
                    className="rounded-circle border"
                    style={{ width: 44, height: 44, objectFit: 'cover' }}
                  />
                  <div>
                    <div>
                      {n.from_user_username && (
                        <Link
                          href={`/${n.from_user_username}`}
                          className="fw-bold text-reset text-decoration-none me-1"
                        >
                          {n.from_user_name}
                        </Link>
                      )}
                      <span>{n.message}</span>
                    </div>
                    <div className="small text-muted">{n.created_at}</div>
                  </div>
                </div>
                {n.post_id && n.from_user_username && (
                  <Link
                    href={`/posts/${n.post_id}/${n.from_user_username}`}
                    className="btn btn-sm btn-outline-primary rounded-pill px-3"
                  >
                    View Post
                  </Link>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}


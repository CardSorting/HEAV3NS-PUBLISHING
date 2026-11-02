'use client';

import React, { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { apiFetch, PostItem, StreamItem, UserProfile } from '@/lib/api';
import { AppShell } from '@/components/AppShell';
import { CreatorSuggestionCard, PostCard } from '@/components/PostCard';

function SearchContent() {
  const searchParams = useSearchParams();
  const initialQuery = searchParams?.get('query') || '';
  const initialFilter = searchParams?.get('filter') || 'top';

  const [query, setQuery] = useState(initialQuery);
  const [filter, setFilter] = useState(initialFilter);
  const [users, setUsers] = useState<UserProfile[]>([]);
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [streams, setStreams] = useState<StreamItem[]>([]);
  const [loading, setLoading] = useState(true);

  const runSearch = async (q = query, f = filter) => {
    setLoading(true);
    try {
      const res = await apiFetch(
        `/api/search?query=${encodeURIComponent(q)}&filter=${encodeURIComponent(f)}`
      );
      setUsers(res.users || []);
      setPosts(res.posts || []);
      setStreams(res.streams || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    runSearch(initialQuery, initialFilter);
  }, [initialQuery, initialFilter]);

  const filters = [
    { key: 'top', label: '🔥 Top' },
    { key: 'latest', label: '🕒 Latest' },
    { key: 'people', label: '👥 People' },
    { key: 'photos', label: '🖼️ Photos' },
    { key: 'videos', label: '🎬 Videos' },
    { key: 'live', label: '🔴 Live' },
  ];

  return (
    <div className="row g-0 min-vh-100">
      <div className="col-12 col-lg-8 border-end">
        {/* Search Input Header */}
        <div className="p-3 border-bottom">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              runSearch(query, filter);
            }}
            className="d-flex gap-2"
          >
            <input
              type="search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search creators, #hashtags, or posts..."
              className="form-control rounded-pill px-4"
            />
            <button type="submit" className="btn btn-primary rounded-pill px-4 fw-bold">
              Search
            </button>
          </form>

          {/* Filter Tabs */}
          <div className="nav nav-pills nav-justified mt-3 gap-1">
            {filters.map((f) => (
              <button
                key={f.key}
                type="button"
                onClick={() => {
                  setFilter(f.key);
                  runSearch(query, f.key);
                }}
                className={`nav-link rounded-pill py-1 small fw-bold ${
                  filter === f.key ? 'active' : ''
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>

        {/* Search Results */}
        {loading ? (
          <div className="p-5 text-center text-muted">Searching...</div>
        ) : filter === 'people' ? (
          <div className="p-3">
            {users.length === 0 ? (
              <div className="text-center text-muted py-5">No creators matching your search.</div>
            ) : (
              users.map((u) => <CreatorSuggestionCard key={u.id} profile={u} />)
            )}
          </div>
        ) : filter === 'live' ? (
          <div className="p-3">
            {streams.length === 0 ? (
              <div className="text-center text-muted py-5">No live streams matching your search.</div>
            ) : (
              streams.map((s) => (
                <div key={s.id} className="card border rounded-4 p-3 mb-3">
                  <div className="d-flex justify-content-between align-items-center">
                    <div className="d-flex align-items-center gap-3">
                      <img
                        src={s.user_avatar}
                        alt={s.user_name}
                        className="rounded-circle"
                        style={{ width: 48, height: 48 }}
                      />
                      <div>
                        <span className="badge bg-danger me-2">🔴 LIVE</span>
                        <strong>{s.name}</strong>
                        <div className="small text-muted">
                          by @{s.user_username} · 👁️ {s.viewer_count} watching
                        </div>
                      </div>
                    </div>
                    <Link
                      href={`/stream/${s.id}/${s.slug}`}
                      className="btn btn-primary rounded-pill px-4 fw-bold"
                    >
                      Join Stream
                    </Link>
                  </div>
                </div>
              ))
            )}
          </div>
        ) : (
          <div>
            {posts.length === 0 ? (
              <div className="text-center text-muted py-5">No posts found.</div>
            ) : (
              posts.map((post) => (
                <PostCard key={post.id} post={post} onUpdated={() => runSearch()} />
              ))
            )}
          </div>
        )}
      </div>

      {/* Right Sidebar Creators */}
      <div className="col-12 col-lg-4 p-3">
        <h6 className="fw-bold text-uppercase small text-muted mb-3">Creator Directory</h6>
        {users.slice(0, 5).map((u) => (
          <CreatorSuggestionCard key={u.id} profile={u} />
        ))}
      </div>
    </div>
  );
}

export default function SearchPage() {
  return (
    <AppShell>
      <Suspense fallback={<div className="p-5 text-center">Loading Explore...</div>}>
        <SearchContent />
      </Suspense>
    </AppShell>
  );
}


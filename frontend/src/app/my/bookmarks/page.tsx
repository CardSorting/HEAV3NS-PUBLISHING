'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, PostItem, ReelItem } from '@/lib/api';
import { AppShell } from '@/components/AppShell';
import { PostCard } from '@/components/PostCard';

export default function BookmarksPage() {
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [reels, setReels] = useState<ReelItem[]>([]);
  const [loading, setLoading] = useState(true);

  const loadBookmarks = async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/api/bookmarks');
      setPosts(res.posts || []);
      setReels(res.reels || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBookmarks();
  }, []);

  const handleUnbookmarkReel = async (reelId: number) => {
    try {
      await apiFetch('/api/posts/bookmark', {
        method: 'POST',
        body: JSON.stringify({ reel_id: reelId }),
      });
      loadBookmarks();
    } catch {
      // ignore
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 820 }}>
        <h4 className="fw-bold mb-3 pb-2 border-bottom">Saved Bookmarks</h4>

        {loading ? (
          <div className="p-5 text-center text-muted">Loading bookmarks...</div>
        ) : posts.length === 0 && reels.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <img
              src="/img/no-content-available.svg"
              alt="Empty bookmarks"
              style={{ height: 120 }}
              className="mb-3"
            />
            <h6 className="fw-bold">No bookmarked posts or reels yet</h6>
            <p className="small text-muted">
              Click the 🔖 Bookmark button on any post or reel to save it here for quick access.
            </p>
            <Link href="/feed" className="btn btn-primary rounded-pill px-4">
              Browse Feed
            </Link>
          </div>
        ) : (
          <>
            {posts.map((post) => (
              <PostCard key={post.id} post={post} onUpdated={loadBookmarks} />
            ))}
            {reels.length > 0 && (
              <div className="mt-4">
                <h6 className="fw-bold text-uppercase small text-muted mb-3">Saved Reels</h6>
                <div className="row g-3">
                  {reels.map((r) => (
                    <div key={r.id} className="col-12 col-md-6">
                      <div className="card border rounded-4 overflow-hidden shadow-sm">
                        <video
                          src={r.video_url}
                          poster={r.cover_url || '/img/home-scene-1.svg'}
                          controls
                          playsInline
                          muted
                          className="w-100 bg-dark"
                          style={{ height: 220, objectFit: 'cover' }}
                        />
                        <div className="card-body p-3 d-flex justify-content-between align-items-center">
                          <div className="overflow-hidden">
                            <Link
                              href={`/${r.user_username}`}
                              className="fw-bold small text-reset text-decoration-none d-block"
                            >
                              {r.user_name}
                            </Link>
                            <div className="small text-muted text-truncate">{r.caption}</div>
                          </div>
                          <button
                            type="button"
                            onClick={() => handleUnbookmarkReel(r.id)}
                            className="btn btn-sm btn-outline-danger rounded-pill ms-2"
                          >
                            Remove
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </AppShell>
  );
}


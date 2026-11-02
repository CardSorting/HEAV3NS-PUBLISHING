'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { apiFetch, PostItem, StoryItem, UserProfile } from '@/lib/api';
import { AppShell } from '@/components/AppShell';
import { CreatorSuggestionCard, PostCard } from '@/components/PostCard';

export default function FeedPage() {
  const router = useRouter();
  const [posts, setPosts] = useState<PostItem[]>([]);
  const [suggestions, setSuggestions] = useState<UserProfile[]>([]);
  const [stories, setStories] = useState<StoryItem[]>([]);
  const [activeStory, setActiveStory] = useState<StoryItem | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [feedFilter, setFeedFilter] = useState('all');
  const [freeOnly, setFreeOnly] = useState(false);
  const [loading, setLoading] = useState(true);

  const loadFeed = async (filter = feedFilter) => {
    setLoading(true);
    try {
      const [feedData, storiesData] = await Promise.all([
        apiFetch(`/api/feed?filter=${encodeURIComponent(filter)}`),
        apiFetch('/api/stories'),
      ]);
      setPosts(feedData.posts || []);
      setSuggestions(feedData.suggestions || []);
      setStories(storiesData.stories || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadFeed(feedFilter);
  }, [feedFilter]);

  const handleToggleFreeSuggestions = async () => {
    const next = !freeOnly;
    setFreeOnly(next);
    try {
      const res = await apiFetch('/api/suggestions/filter', {
        method: 'POST',
        body: JSON.stringify({ free_only: next }),
      });
      setSuggestions(res.suggestions || []);
    } catch {
      // ignore
    }
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.push(`/search?query=${encodeURIComponent(searchQuery)}`);
  };

  return (
    <AppShell>
      <div className="row g-0 min-vh-100">
        {/* Center Feed Column */}
        <div className="col-12 col-lg-8 border-end">
          <div className="px-3 py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 className="fw-bold mb-0">Your Feed</h5>
            <Link href="/posts/create" className="btn btn-sm btn-primary rounded-pill px-3">
              + Create Post
            </Link>
          </div>
          <div className="px-3 py-2 border-bottom d-flex gap-2 overflow-auto">
            {[
              { key: 'all', label: 'All Posts' },
              { key: 'subscribed', label: 'Subscribed' },
              { key: 'media', label: 'Media' },
              { key: 'photos', label: 'Photos' },
              { key: 'videos', label: 'Videos' },
            ].map((f) => (
              <button
                key={f.key}
                type="button"
                onClick={() => setFeedFilter(f.key)}
                className={`btn btn-sm rounded-pill px-3 ${
                  feedFilter === f.key ? 'btn-primary' : 'btn-outline-secondary'
                }`}
              >
                {f.label}
              </button>
            ))}
          </div>

          {/* Stories Tray */}
          <div className="px-3 py-3 border-bottom bg-body-tertiary d-flex align-items-center gap-3 overflow-auto">
            <Link
              href="/stories/create"
              className="text-decoration-none text-center flex-shrink-0"
              style={{ width: 72 }}
            >
              <div
                className="rounded-circle border border-2 border-primary d-flex align-items-center justify-content-center mx-auto bg-body fw-bold text-primary fs-4"
                style={{ width: 60, height: 60 }}
              >
                +
              </div>
              <div className="small text-muted text-truncate mt-1">Add Story</div>
            </Link>
            {stories.map((story) => (
              <button
                key={story.id}
                type="button"
                onClick={() => setActiveStory(story)}
                className="btn p-0 border-0 text-center flex-shrink-0"
                style={{ width: 72 }}
              >
                <img
                  src={story.user_avatar || '/img/default-avatar.jpg'}
                  alt={story.user_name}
                  className="rounded-circle border border-3 border-primary p-1"
                  style={{ width: 60, height: 60, objectFit: 'cover' }}
                />
                <div className="small text-truncate mt-1">{story.user_name.split(' ')[0]}</div>
              </button>
            ))}
          </div>

          {/* Story Lightbox Modal */}
          {activeStory && (
            <div
              className="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-75 d-flex align-items-center justify-content-center z-3 p-3"
              onClick={() => setActiveStory(null)}
            >
              <div
                className="card border-0 rounded-4 overflow-hidden shadow-lg"
                style={{ maxWidth: 420, width: '100%' }}
                onClick={(e) => e.stopPropagation()}
              >
                <div className="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                  <div className="d-flex align-items-center gap-2">
                    <img
                      src={activeStory.user_avatar}
                      alt={activeStory.user_name}
                      className="rounded-circle"
                      style={{ width: 34, height: 34 }}
                    />
                    <div>
                      <div className="fw-bold small">{activeStory.user_name}</div>
                      <div className="text-white-50" style={{ fontSize: 11 }}>
                        @{activeStory.user_username}
                      </div>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => setActiveStory(null)}
                    className="btn btn-sm btn-outline-light rounded-pill"
                  >
                    ✕
                  </button>
                </div>
                <div className="card-body bg-dark text-white text-center p-4">
                  <img
                    src={activeStory.media_url}
                    alt="Story"
                    className="img-fluid rounded-3 mb-3"
                    style={{ maxHeight: 340 }}
                  />
                  <p className="mb-0 fw-semibold">{activeStory.caption}</p>
                </div>
              </div>
            </div>
          )}

          {/* Feed Posts List */}
          {loading ? (
            <div className="p-5 text-center text-muted">Loading feed posts...</div>
          ) : posts.length === 0 ? (
            <div className="p-5 text-center">
              <img
                src="/img/no-content-available.svg"
                alt="Empty feed"
                style={{ height: 140 }}
                className="mb-3"
              />
              <h5 className="fw-bold">No posts in your feed yet</h5>
              <p className="text-muted small">
                Explore creators or publish your first post to get started!
              </p>
              <Link href="/search" className="btn btn-primary rounded-pill px-4">
                Explore Creators
              </Link>
            </div>
          ) : (
            posts.map((post) => (
              <PostCard
                key={post.id}
                post={post}
                onUpdated={loadFeed}
                onDeleted={(id) => setPosts((prev) => prev.filter((p) => p.id !== id))}
              />
            ))
          )}
        </div>

        {/* Right Sidebar Widgets */}
        <div className="col-12 col-lg-4 p-3">
          {/* Search Widget */}
          <form onSubmit={handleSearchSubmit} className="mb-4">
            <div className="input-group">
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search creators or posts..."
                className="form-control rounded-start-pill ps-3"
              />
              <button type="submit" className="btn btn-primary rounded-end-pill px-3">
                🔍
              </button>
            </div>
          </form>

          {/* Suggestions Widget */}
          <div className="d-flex justify-content-between align-items-center mb-3">
            <h6 className="fw-bold text-uppercase small text-muted mb-0">Suggestions</h6>
            <button
              type="button"
              onClick={handleToggleFreeSuggestions}
              className={`btn btn-sm rounded-pill ${
                freeOnly ? 'btn-primary' : 'btn-outline-secondary'
              }`}
            >
              {freeOnly ? '✓ Free Only' : 'Filter Free'}
            </button>
          </div>

          {suggestions.map((profile) => (
            <CreatorSuggestionCard key={profile.id} profile={profile} />
          ))}

          {/* Quick Reels Promo */}
          <div className="card border rounded-4 p-3 bg-body-tertiary mt-4">
            <div className="d-flex justify-content-between align-items-center mb-2">
              <h6 className="fw-bold mb-0">🎬 Creator Reels</h6>
              <Link href="/reels" className="small text-primary text-decoration-none fw-bold">
                See all →
              </Link>
            </div>
            <p className="small text-muted mb-0">
              Watch short-form vertical clips from top creators or share your own Reel.
            </p>
          </div>
        </div>
      </div>
    </AppShell>
  );
}


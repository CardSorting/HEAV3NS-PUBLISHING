'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { apiFetch, PostItem, UserProfile } from '@/lib/api';
import { AppShell } from '@/components/AppShell';
import { CreatorSuggestionCard, PostCard } from '@/components/PostCard';

export default function SinglePostPage() {
  const params = useParams();
  const router = useRouter();
  const postId = Number(params?.post_id);
  const [post, setPost] = useState<PostItem | null>(null);
  const [author, setAuthor] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);

  const loadPost = async () => {
    setLoading(true);
    try {
      const res = await apiFetch(`/api/posts/${postId}`);
      setPost(res.post);
      setAuthor(res.author);
    } catch {
      setPost(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (postId) loadPost();
  }, [postId]);

  return (
    <AppShell>
      <div className="row g-0 min-vh-100">
        <div className="col-12 col-lg-8 border-end">
          <div className="px-3 py-3 border-bottom d-flex align-items-center gap-3">
            <button
              type="button"
              onClick={() => router.back()}
              className="btn btn-sm btn-outline-secondary rounded-pill"
            >
              ← Back
            </button>
            <h5 className="fw-bold mb-0">Post Details</h5>
          </div>

          {loading ? (
            <div className="p-5 text-center text-muted">Loading post...</div>
          ) : !post ? (
            <div className="p-5 text-center">
              <h5 className="fw-bold">Post not found</h5>
              <Link href="/feed" className="btn btn-primary rounded-pill mt-2">
                Return to Feed
              </Link>
            </div>
          ) : (
            <PostCard
              post={post}
              onUpdated={loadPost}
              onDeleted={() => router.push('/feed')}
            />
          )}
        </div>

        <div className="col-12 col-lg-4 p-3">
          {author && (
            <>
              <h6 className="fw-bold text-uppercase small text-muted mb-3">Creator</h6>
              <CreatorSuggestionCard profile={author} />
            </>
          )}
        </div>
      </div>
    </AppShell>
  );
}


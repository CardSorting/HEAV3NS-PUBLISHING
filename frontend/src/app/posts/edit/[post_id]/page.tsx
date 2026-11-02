'use client';

import React from 'react';
import { useParams } from 'next/navigation';
import { PostComposerForm } from '@/components/PostComposerForm';

export default function EditPostPage() {
  const params = useParams();
  const postId = Number(params?.post_id);
  return <PostComposerForm editPostId={postId} />;
}


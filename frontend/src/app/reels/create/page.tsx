'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function CreateReelPage() {
  const router = useRouter();
  const [caption, setCaption] = useState('');
  const [coverUrl, setCoverUrl] = useState('/img/home-scene-1.svg');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await apiFetch('/api/reels', {
        method: 'POST',
        body: JSON.stringify({
          caption,
          cover_url: coverUrl,
          video_url: '/img/video-loading-spinner.mp4',
        }),
      });
      router.push('/reels');
    } catch {
      // ignore
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 620 }}>
        <h4 className="fw-bold mb-3 pb-2 border-bottom">Create a New Reel</h4>
        <form onSubmit={handleSubmit} className="d-grid gap-3">
          <div>
            <label className="form-label fw-bold small">Reel Caption</label>
            <textarea
              rows={3}
              required
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              placeholder="Describe your Reel clip..."
              className="form-control rounded-4 p-3"
            />
          </div>
          <div>
            <label className="form-label fw-bold small">Cover / Scene Illustration</label>
            <select
              value={coverUrl}
              onChange={(e) => setCoverUrl(e.target.value)}
              className="form-select rounded-pill px-3"
            >
              <option value="/img/home-scene-1.svg">Neon Studio Scene</option>
              <option value="/img/home-scene-2.svg">Creator Fitness Scene</option>
              <option value="/img/home-scene-3.svg">Midnight Music Scene</option>
            </select>
          </div>
          <button
            type="submit"
            disabled={saving}
            className="btn btn-primary rounded-pill py-2 fw-bold"
          >
            {saving ? 'Publishing...' : 'Publish Reel'}
          </button>
        </form>
      </div>
    </AppShell>
  );
}


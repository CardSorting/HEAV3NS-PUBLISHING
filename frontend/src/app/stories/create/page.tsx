'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function CreateStoryPage() {
  const router = useRouter();
  const [caption, setCaption] = useState('');
  const [mediaUrl, setMediaUrl] = useState('/img/home-scene-1.svg');
  const [isHighlight, setIsHighlight] = useState(false);
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await apiFetch('/api/stories', {
        method: 'POST',
        body: JSON.stringify({
          caption,
          media_url: mediaUrl,
          is_highlight: isHighlight,
        }),
      });
      router.push('/feed');
    } catch {
      // ignore
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 620 }}>
        <h4 className="fw-bold mb-3 pb-2 border-bottom">Create 24-Hour Story</h4>
        <form onSubmit={handleSubmit} className="d-grid gap-3">
          <div>
            <label className="form-label fw-bold small">Story Caption</label>
            <input
              type="text"
              required
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              placeholder="What's happening right now?"
              className="form-control rounded-pill px-3"
            />
          </div>
          <div>
            <label className="form-label fw-bold small">Story Visual</label>
            <select
              value={mediaUrl}
              onChange={(e) => setMediaUrl(e.target.value)}
              className="form-select rounded-pill px-3"
            >
              <option value="/img/home-scene-1.svg">Studio Artwork</option>
              <option value="/img/home-scene-2.svg">Behind the Scenes</option>
              <option value="/img/home-scene-3.svg">VIP Preview</option>
            </select>
          </div>
          <div className="form-check form-switch">
            <input
              type="checkbox"
              id="highlightSwitch"
              checked={isHighlight}
              onChange={(e) => setIsHighlight(e.target.checked)}
              className="form-check-input"
            />
            <label htmlFor="highlightSwitch" className="form-check-label fw-semibold">
              Pin to Profile Story Highlights
            </label>
          </div>
          <button
            type="submit"
            disabled={saving}
            className="btn btn-primary rounded-pill py-2 fw-bold"
          >
            {saving ? 'Sharing...' : 'Share Story'}
          </button>
        </form>
      </div>
    </AppShell>
  );
}


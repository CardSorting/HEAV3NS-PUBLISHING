'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch, Attachment } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function CreateStoryPage() {
  const router = useRouter();
  const [caption, setCaption] = useState('');
  const [mediaUrl, setMediaUrl] = useState('/img/home-scene-1.svg');
  const [bgPreset, setBgPreset] = useState('gradient-purple');
  const [isHighlight, setIsHighlight] = useState(false);
  const [vaultItems, setVaultItems] = useState<Attachment[]>([]);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch('/api/vault')
      .then((res) => setVaultItems(res.items || []))
      .catch(() => {});
  }, []);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await apiFetch('/api/attachment/upload', {
        method: 'POST',
        body: formData,
      });
      if (res.attachments?.[0]) {
        setMediaUrl(res.attachments[0].file_url);
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await apiFetch('/api/stories', {
        method: 'POST',
        body: JSON.stringify({
          caption,
          media_url: mediaUrl,
          bg_preset: bgPreset,
          is_highlight: isHighlight,
        }),
      });
      router.push('/feed');
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const presetOptions = [
    '/img/home-scene-1.svg',
    '/img/home-scene-2.svg',
    '/img/home-scene-3.svg',
    ...vaultItems.filter((v) => !v.is_archived).map((v) => v.file_url),
  ].filter((v, idx, arr) => arr.indexOf(v) === idx);

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 680 }}>
        <div className="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
          <h4 className="fw-bold mb-0">Create 24-Hour Story</h4>
          <button
            type="button"
            onClick={() => router.back()}
            className="btn btn-sm btn-outline-secondary rounded-pill px-3"
          >
            ← Back
          </button>
        </div>

        {error && <div className="alert alert-danger py-2 small">{error}</div>}

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

          <div className="p-3 rounded-4 border bg-body-tertiary">
            <div className="d-flex justify-content-between align-items-center mb-2">
              <strong className="small">Story Media (Upload or Select from Vault)</strong>
              <label className="btn btn-sm btn-outline-primary rounded-pill px-3 mb-0">
                {uploading ? 'Uploading...' : '📁 Upload Photo/Video'}
                <input
                  type="file"
                  accept="image/*,video/*"
                  onChange={handleUpload}
                  className="d-none"
                />
              </label>
            </div>
            <div className="d-flex gap-2 overflow-auto py-1">
              {presetOptions.map((url) => (
                <button
                  key={url}
                  type="button"
                  onClick={() => setMediaUrl(url)}
                  className={`btn p-1 rounded-3 flex-shrink-0 ${
                    mediaUrl === url ? 'border-2 border-primary bg-primary-subtle' : 'border bg-body'
                  }`}
                >
                  <img
                    src={url}
                    alt="Story media"
                    style={{ width: 68, height: 68, objectFit: 'cover' }}
                    className="rounded-2"
                  />
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="form-label fw-bold small">Background Theme</label>
            <select
              value={bgPreset}
              onChange={(e) => setBgPreset(e.target.value)}
              className="form-select rounded-pill px-3"
            >
              <option value="gradient-purple">Neon Purple Gradient</option>
              <option value="gradient-blue">Cyber Blue Gradient</option>
              <option value="gradient-pink">Sunset Pink Gradient</option>
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
              ⭐ Pin to Profile Story Highlights (persists past 24 hours)
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


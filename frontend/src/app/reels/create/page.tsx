'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch, Attachment } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function CreateReelPage() {
  const router = useRouter();
  const [caption, setCaption] = useState('');
  const [videoUrl, setVideoUrl] = useState('/img/video-loading-spinner.mp4');
  const [coverUrl, setCoverUrl] = useState('/img/home-scene-1.svg');
  const [price, setPrice] = useState('0');
  const [vaultItems, setVaultItems] = useState<Attachment[]>([]);
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch('/api/vault')
      .then((res) => setVaultItems(res.items || []))
      .catch(() => {});
  }, []);

  const handleUploadVideo = async (e: React.ChangeEvent<HTMLInputElement>) => {
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
        setVideoUrl(res.attachments[0].file_url);
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
      await apiFetch('/api/reels', {
        method: 'POST',
        body: JSON.stringify({
          caption,
          cover_url: coverUrl,
          video_url: videoUrl,
          price: parseFloat(price) || 0,
        }),
      });
      router.push('/reels');
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const coverOptions = [
    '/img/home-scene-1.svg',
    '/img/home-scene-2.svg',
    '/img/home-scene-3.svg',
    ...vaultItems
      .filter((v) => !v.is_archived && v.file_type === 'image')
      .map((v) => v.file_url),
  ].filter((v, idx, arr) => arr.indexOf(v) === idx);

  const videoOptions = [
    '/img/video-loading-spinner.mp4',
    ...vaultItems
      .filter((v) => !v.is_archived && v.file_type === 'video')
      .map((v) => v.file_url),
  ].filter((v, idx, arr) => arr.indexOf(v) === idx);

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 680 }}>
        <div className="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
          <h4 className="fw-bold mb-0">Create a New Reel</h4>
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

          <div className="p-3 rounded-4 border bg-body-tertiary">
            <div className="d-flex justify-content-between align-items-center mb-2">
              <strong className="small">Video File</strong>
              <label className="btn btn-sm btn-outline-primary rounded-pill px-3 mb-0">
                {uploading ? 'Uploading...' : '🎥 Upload Video'}
                <input
                  type="file"
                  accept="video/*"
                  onChange={handleUploadVideo}
                  className="d-none"
                />
              </label>
            </div>
            <div className="small text-muted text-truncate mb-2">Selected: {videoUrl}</div>
            {videoOptions.length > 1 && (
              <div className="d-flex gap-2 overflow-auto py-1">
                {videoOptions.map((url) => (
                  <button
                    key={url}
                    type="button"
                    onClick={() => setVideoUrl(url)}
                    className={`btn btn-sm rounded-pill flex-shrink-0 ${
                      videoUrl === url ? 'btn-primary' : 'btn-outline-secondary'
                    }`}
                  >
                    🎬 {url.split('/').pop()}
                  </button>
                ))}
              </div>
            )}
          </div>

          <div>
            <label className="form-label fw-bold small">Cover / Scene Illustration</label>
            <div className="d-flex gap-2 overflow-auto py-1">
              {coverOptions.map((url) => (
                <button
                  key={url}
                  type="button"
                  onClick={() => setCoverUrl(url)}
                  className={`btn p-1 rounded-3 flex-shrink-0 ${
                    coverUrl === url ? 'border-2 border-primary bg-primary-subtle' : 'border bg-body'
                  }`}
                >
                  <img
                    src={url}
                    alt="Cover"
                    style={{ width: 68, height: 68, objectFit: 'cover' }}
                    className="rounded-2"
                  />
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="form-label fw-bold small">Optional Pay-Per-View (PPV) Price ($ USD)</label>
            <div className="input-group" style={{ maxWidth: 260 }}>
              <span className="input-group-text">$</span>
              <input
                type="number"
                min="0"
                step="0.50"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                className="form-control"
              />
            </div>
            <div className="form-text small">Leave 0 for free/subscriber Reel access.</div>
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


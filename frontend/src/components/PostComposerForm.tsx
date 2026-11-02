'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch, Attachment } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export function PostComposerForm({ editPostId }: { editPostId?: number }) {
  const router = useRouter();
  const [text, setText] = useState('');
  const [price, setPrice] = useState('0');
  const [isPinned, setIsPinned] = useState(false);
  const [releaseDate, setReleaseDate] = useState('');
  const [expireDate, setExpireDate] = useState('');
  const [attachments, setAttachments] = useState<string[]>([]);
  const [vaultItems, setVaultItems] = useState<Attachment[]>([]);
  const [showVault, setShowVault] = useState(false);
  const [showPoll, setShowPoll] = useState(false);
  const [pollAnswers, setPollAnswers] = useState<string[]>(['', '']);
  const [aiTopic, setAiTopic] = useState('');
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (editPostId) {
      apiFetch(`/api/posts/${editPostId}`)
        .then((res) => {
          if (res.post) {
            setText(res.post.text || '');
            setPrice(String(res.post.price || 0));
            setIsPinned(Boolean(res.post.is_pinned));
            if (res.post.release_date) setReleaseDate(res.post.release_date);
            if (res.post.expire_date) setExpireDate(res.post.expire_date);
            if (res.post.attachments) {
              setAttachments(res.post.attachments.map((a: Attachment) => a.file_url));
            }
            if (res.post.poll && res.post.poll.answers?.length > 0) {
              setShowPoll(true);
              setPollAnswers(res.post.poll.answers.map((a: any) => a.answer));
            }
          }
        })
        .catch((e) => setError(e.message));
    }
    apiFetch('/api/vault')
      .then((res) => setVaultItems(res.items || []))
      .catch(() => {});
  }, [editPostId]);

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    setUploading(true);
    setError(null);
    try {
      const formData = new FormData();
      Array.from(files).forEach((f) => formData.append('file', f));
      const res = await apiFetch('/api/attachment/upload', {
        method: 'POST',
        body: formData,
      });
      if (res.attachments) {
        const urls = res.attachments.map((a: Attachment) => a.file_url);
        setAttachments((prev) => [...prev, ...urls]);
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  };

  const handleGenerateAiCaption = async () => {
    try {
      const res = await apiFetch('/api/ai/suggest', {
        method: 'POST',
        body: JSON.stringify({ topic: aiTopic || 'exclusive photoshoot' }),
      });
      if (res.suggestion) {
        setText((prev) => (prev ? `${prev}\n\n${res.suggestion}` : res.suggestion));
      }
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const res = await apiFetch('/api/posts/save', {
        method: 'POST',
        body: JSON.stringify({
          id: editPostId,
          text,
          price: parseFloat(price) || 0,
          is_pinned: isPinned,
          release_date: releaseDate ? releaseDate.replace('T', ' ') : undefined,
          expire_date: expireDate ? expireDate.replace('T', ' ') : undefined,
          attachments,
          poll_answers: showPoll ? pollAnswers.filter((a) => a.trim().length > 0) : undefined,
        }),
      });
      router.push(`/posts/${res.post.id}/${res.post.user_username}`);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 780 }}>
        <div className="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
          <h4 className="fw-bold mb-0">{editPostId ? 'Edit Post' : 'Create New Post'}</h4>
          <button
            type="button"
            onClick={() => router.back()}
            className="btn btn-sm btn-outline-secondary rounded-pill px-3"
          >
            ← Back
          </button>
        </div>

        {error && <div className="alert alert-danger py-2 small">{error}</div>}

        <form onSubmit={handleSave} className="d-grid gap-4">
          <div>
            <label className="form-label fw-bold">Post Caption & Description</label>
            <textarea
              rows={5}
              value={text}
              onChange={(e) => setText(e.target.value)}
              placeholder="Write something engaging for your fans... Use #hashtags or @mentions!"
              className="form-control rounded-4 p-3"
            />
          </div>

          <div className="p-3 rounded-4 border bg-body-tertiary d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div className="small fw-semibold">✨ AI Caption Assistant</div>
            <div className="d-flex gap-2 flex-fill" style={{ maxWidth: 420 }}>
              <input
                type="text"
                value={aiTopic}
                onChange={(e) => setAiTopic(e.target.value)}
                placeholder="Topic (e.g. cyberpunk cosplay, workout)"
                className="form-control form-control-sm rounded-pill px-3"
              />
              <button
                type="button"
                onClick={handleGenerateAiCaption}
                className="btn btn-sm btn-outline-primary rounded-pill px-3 flex-shrink-0"
              >
                Generate
              </button>
            </div>
          </div>

          <div className="p-3 rounded-4 border">
            <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
              <strong className="small text-uppercase text-muted">Media Attachments</strong>
              <div className="d-flex gap-2">
                <label className="btn btn-sm btn-outline-primary rounded-pill px-3 mb-0">
                  {uploading ? 'Uploading...' : '📁 Upload File'}
                  <input
                    type="file"
                    multiple
                    accept="image/*,video/*"
                    onChange={handleFileUpload}
                    className="d-none"
                  />
                </label>
                <button
                  type="button"
                  onClick={() => setShowVault(!showVault)}
                  className="btn btn-sm btn-outline-secondary rounded-pill px-3"
                >
                  🖼️ Choose from Vault
                </button>
              </div>
            </div>

            {showVault && (
              <div className="p-3 mb-3 rounded-3 bg-body-tertiary border">
                <div className="small fw-bold mb-2">
                  Select from Media Vault or Preset Illustrations
                </div>
                <div className="d-flex flex-wrap gap-2">
                  {[
                    '/img/home-scene-1.svg',
                    '/img/home-scene-2.svg',
                    '/img/home-scene-3.svg',
                    '/img/home-creators.svg',
                    ...vaultItems.map((v) => v.file_url),
                  ]
                    .filter((v, idx, arr) => arr.indexOf(v) === idx)
                    .map((url) => (
                      <button
                        key={url}
                        type="button"
                        onClick={() =>
                          setAttachments((prev) =>
                            prev.includes(url) ? prev : [...prev, url]
                          )
                        }
                        className="btn p-1 border rounded-3 bg-body"
                      >
                        <img
                          src={url}
                          alt="Vault media"
                          style={{ width: 72, height: 72, objectFit: 'cover' }}
                        />
                      </button>
                    ))}
                </div>
              </div>
            )}

            {attachments.length > 0 ? (
              <div className="d-flex flex-wrap gap-2">
                {attachments.map((url, idx) => (
                  <div key={idx} className="position-relative border rounded-3 p-1">
                    <img
                      src={url}
                      alt="Attached"
                      style={{ width: 96, height: 96, objectFit: 'cover' }}
                      className="rounded-2"
                    />
                    <button
                      type="button"
                      onClick={() =>
                        setAttachments((prev) => prev.filter((_, i) => i !== idx))
                      }
                      className="btn btn-sm btn-danger rounded-circle position-absolute top-0 end-0 m-1 py-0 px-1"
                    >
                      ✕
                    </button>
                  </div>
                ))}
              </div>
            ) : (
              <div className="small text-muted">No media attached yet.</div>
            )}
          </div>

          <div className="row g-3">
            <div className="col-12 col-md-6">
              <label className="form-label fw-bold small">Pay-Per-View (PPV) Price ($ USD)</label>
              <div className="input-group">
                <span className="input-group-text">$</span>
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                  className="form-control"
                />
              </div>
              <div className="form-text small">Leave 0.00 for standard subscriber/free access.</div>
            </div>

            <div className="col-12 col-md-6 d-flex flex-column justify-content-center gap-2">
              <div className="form-check form-switch">
                <input
                  type="checkbox"
                  id="pinSwitch"
                  checked={isPinned}
                  onChange={(e) => setIsPinned(e.target.checked)}
                  className="form-check-input"
                />
                <label htmlFor="pinSwitch" className="form-check-label fw-semibold">
                  📌 Pin this post to top of profile
                </label>
              </div>
              <div className="form-check form-switch">
                <input
                  type="checkbox"
                  id="pollSwitch"
                  checked={showPoll}
                  onChange={(e) => setShowPoll(e.target.checked)}
                  className="form-check-input"
                />
                <label htmlFor="pollSwitch" className="form-check-label fw-semibold">
                  📊 Include an interactive Poll
                </label>
              </div>
            </div>
          </div>

          <div className="row g-3">
            <div className="col-12 col-md-6">
              <label className="form-label fw-bold small">🗓️ Scheduled Release Date (Optional)</label>
              <input
                type="datetime-local"
                value={releaseDate ? releaseDate.replace(' ', 'T').slice(0, 16) : ''}
                onChange={(e) => setReleaseDate(e.target.value)}
                className="form-control"
              />
              <div className="form-text small">
                If set in the future, only you can view this post until the release time.
              </div>
            </div>
            <div className="col-12 col-md-6">
              <label className="form-label fw-bold small">⏳ Post Expiration Date (Optional)</label>
              <input
                type="datetime-local"
                value={expireDate ? expireDate.replace(' ', 'T').slice(0, 16) : ''}
                onChange={(e) => setExpireDate(e.target.value)}
                className="form-control"
              />
              <div className="form-text small">
                Automatically hides the post from non-owners after this date.
              </div>
            </div>
          </div>

          {showPoll && (
            <div className="p-3 rounded-4 border bg-body-tertiary">
              <div className="fw-bold small mb-2">Poll Options</div>
              <div className="d-grid gap-2">
                {pollAnswers.map((ans, i) => (
                  <input
                    key={i}
                    type="text"
                    value={ans}
                    onChange={(e) => {
                      const copy = [...pollAnswers];
                      copy[i] = e.target.value;
                      setPollAnswers(copy);
                    }}
                    placeholder={`Option ${i + 1}`}
                    className="form-control form-control-sm rounded-pill px-3"
                  />
                ))}
                <button
                  type="button"
                  onClick={() => setPollAnswers([...pollAnswers, ''])}
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                >
                  + Add Poll Option
                </button>
              </div>
            </div>
          )}

          <div className="d-flex justify-content-end gap-2 pt-2">
            <button
              type="submit"
              disabled={saving}
              className="btn btn-primary rounded-pill px-5 py-2 fw-bold"
            >
              {saving ? 'Publishing...' : editPostId ? 'Save Changes' : 'Publish Post'}
            </button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}


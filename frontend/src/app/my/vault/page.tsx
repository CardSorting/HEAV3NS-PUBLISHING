'use client';

import React, { useEffect, useState } from 'react';
import { apiFetch, Attachment } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function MediaVaultPage() {
  const [items, setItems] = useState<Attachment[]>([]);
  const [filterTab, setFilterTab] = useState<'all' | 'image' | 'video' | 'archived'>('all');
  const [uploading, setUploading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadVault = async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/api/vault');
      setItems(res.items || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadVault();
  }, []);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    setUploading(true);
    setError(null);
    try {
      const formData = new FormData();
      Array.from(files).forEach((f) => formData.append('file', f));
      await apiFetch('/api/attachment/upload', {
        method: 'POST',
        body: formData,
      });
      await loadVault();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  };

  const handleToggleArchive = async (id: string) => {
    setError(null);
    try {
      const res = await apiFetch(`/api/vault/${id}/archive`, { method: 'POST' });
      setItems((prev) =>
        prev.map((i) => (i.id === id ? { ...i, is_archived: res.is_archived } : i))
      );
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleDelete = async (id: string) => {
    setError(null);
    try {
      await apiFetch(`/api/vault/${id}`, { method: 'DELETE' });
      setItems((prev) => prev.filter((i) => i.id !== id));
    } catch (err: any) {
      setError(err.message);
    }
  };

  const filteredItems = items.filter((item) => {
    if (filterTab === 'archived') return item.is_archived;
    if (item.is_archived) return false;
    if (filterTab === 'image') return item.file_type === 'image';
    if (filterTab === 'video') return item.file_type === 'video';
    return true;
  });

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        <div className="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
          <div>
            <h4 className="fw-bold mb-0">Media Vault</h4>
            <p className="small text-muted mb-0">
              Upload, organize, archive, and reuse your photos and videos across posts, stories, and messages.
            </p>
          </div>
          <label className="btn btn-primary rounded-pill px-4 fw-bold mb-0">
            {uploading ? 'Uploading...' : '+ Upload Media'}
            <input
              type="file"
              multiple
              accept="image/*,video/*"
              onChange={handleUpload}
              className="d-none"
            />
          </label>
        </div>

        {/* Vault Filter Tabs */}
        <div className="d-flex flex-wrap gap-2 mb-4">
          {[
            { id: 'all', label: 'All Active' },
            { id: 'image', label: '📷 Photos' },
            { id: 'video', label: '🎬 Videos' },
            { id: 'archived', label: '📦 Archived' },
          ].map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setFilterTab(t.id as any)}
              className={`btn btn-sm rounded-pill px-3 ${
                filterTab === t.id ? 'btn-primary fw-bold' : 'btn-outline-secondary'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {error && <div className="alert alert-danger py-2 small">{error}</div>}

        {loading ? (
          <div className="p-5 text-center text-muted">Loading Media Vault...</div>
        ) : filteredItems.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <h6 className="fw-bold">No media items in this view</h6>
            <p className="small text-muted mb-0">
              Upload images or videos above or switch filter tabs.
            </p>
          </div>
        ) : (
          <div className="row g-3">
            {filteredItems.map((item) => (
              <div key={item.id} className="col-6 col-md-4 col-lg-3">
                <div className="card border rounded-4 overflow-hidden h-100 shadow-sm">
                  <div className="position-relative">
                    {item.file_type === 'video' ? (
                      <video
                        src={item.file_url}
                        controls
                        style={{ height: 170, width: '100%', objectFit: 'cover' }}
                      />
                    ) : (
                      <img
                        src={item.file_url}
                        alt={item.filename}
                        style={{ height: 170, width: '100%', objectFit: 'cover' }}
                      />
                    )}
                    <div className="position-absolute top-0 start-0 m-2 d-flex flex-wrap gap-1">
                      {item.post_id && (
                        <span className="badge bg-dark bg-opacity-75">Post #{item.post_id}</span>
                      )}
                      {item.message_id && (
                        <span className="badge bg-primary bg-opacity-75">
                          DM #{item.message_id}
                        </span>
                      )}
                      {item.is_archived && (
                        <span className="badge bg-secondary">Archived</span>
                      )}
                    </div>
                  </div>
                  <div className="card-body p-2 d-flex justify-content-between align-items-center">
                    <div className="small text-truncate me-2" title={item.filename}>
                      {item.filename}
                    </div>
                    <div className="d-flex gap-1">
                      <button
                        type="button"
                        onClick={() => handleToggleArchive(item.id)}
                        className="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2"
                        title={item.is_archived ? 'Restore from archive' : 'Archive media'}
                      >
                        {item.is_archived ? '↩️' : '📦'}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(item.id)}
                        className="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                        title="Delete from vault"
                      >
                        🗑️
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}


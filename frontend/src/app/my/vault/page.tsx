'use client';

import React, { useEffect, useState } from 'react';
import { apiFetch, Attachment } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function MediaVaultPage() {
  const [items, setItems] = useState<Attachment[]>([]);
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

  const handleDelete = async (id: string) => {
    try {
      await apiFetch(`/api/vault/${id}`, { method: 'DELETE' });
      setItems((prev) => prev.filter((i) => i.id !== id));
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        <div className="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
          <div>
            <h4 className="fw-bold mb-0">Media Vault</h4>
            <p className="small text-muted mb-0">
              Upload, organize, and reuse your photos and videos across posts and messages.
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

        {error && <div className="alert alert-danger py-2 small">{error}</div>}

        {loading ? (
          <div className="p-5 text-center text-muted">Loading Media Vault...</div>
        ) : items.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <h6 className="fw-bold">Your Media Vault is empty</h6>
            <p className="small text-muted mb-0">
              Upload images or videos above to build your reusable library.
            </p>
          </div>
        ) : (
          <div className="row g-3">
            {items.map((item) => (
              <div key={item.id} className="col-6 col-md-4 col-lg-3">
                <div className="card border rounded-4 overflow-hidden h-100 shadow-sm">
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
                  <div className="card-body p-2 d-flex justify-content-between align-items-center">
                    <div className="small text-truncate me-2" title={item.filename}>
                      {item.filename}
                    </div>
                    <button
                      type="button"
                      onClick={() => handleDelete(item.id)}
                      className="btn btn-sm btn-outline-danger rounded-pill py-0 px-2"
                    >
                      🗑️
                    </button>
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


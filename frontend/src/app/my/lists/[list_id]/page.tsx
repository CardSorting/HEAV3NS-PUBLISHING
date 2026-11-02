'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { apiFetch, UserList, UserProfile } from '@/lib/api';
import { AppShell } from '@/components/AppShell';
import { CreatorSuggestionCard } from '@/components/PostCard';

export default function ListDetailPage() {
  const params = useParams();
  const router = useRouter();
  const listId = Number(params?.list_id);
  const [list, setList] = useState<UserList | null>(null);
  const [allUsers, setAllUsers] = useState<UserProfile[]>([]);
  const [selectedAddUserId, setSelectedAddUserId] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [feedback, setFeedback] = useState<string | null>(null);

  const loadList = async () => {
    setLoading(true);
    try {
      const [listRes, searchRes] = await Promise.all([
        apiFetch(`/api/lists/${listId}`),
        apiFetch('/api/search?query='),
      ]);
      setList(listRes.list);
      setAllUsers(searchRes.users || []);
    } catch {
      setList(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (listId) {
      loadList();
    }
  }, [listId]);

  const handleToggleMember = async (memberUserId: number) => {
    try {
      const res = await apiFetch('/api/lists/members', {
        method: 'POST',
        body: JSON.stringify({
          list_id: listId,
          member_user_id: memberUserId,
        }),
      });
      setFeedback(res.added ? 'Member added to list.' : 'Member removed from list.');
      await loadList();
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const handleAddSelected = async (e: React.FormEvent) => {
    e.preventDefault();
    const uid = Number(selectedAddUserId);
    if (!uid) return;
    await handleToggleMember(uid);
    setSelectedAddUserId('');
  };

  const memberIds = new Set((list?.members || []).map((m) => m.id));
  const candidates = allUsers.filter((u) => !memberIds.has(u.id));

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 820 }}>
        <div className="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
          <div className="d-flex align-items-center gap-3">
            <button
              type="button"
              onClick={() => router.push('/my/lists')}
              className="btn btn-sm btn-outline-secondary rounded-pill"
            >
              ← Back to Lists
            </button>
            <h4 className="fw-bold mb-0">{list ? list.name : 'List'}</h4>
          </div>
          {list && (
            <span className="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
              {list.members.length} members
            </span>
          )}
        </div>

        {feedback && <div className="alert alert-info py-2 small mb-3">{feedback}</div>}

        {list && candidates.length > 0 && (
          <form
            onSubmit={handleAddSelected}
            className="card border rounded-4 p-3 mb-4 bg-body-tertiary"
          >
            <label className="form-label small fw-bold mb-2">+ Add Creator or User to List</label>
            <div className="d-flex gap-2">
              <select
                value={selectedAddUserId}
                onChange={(e) => setSelectedAddUserId(e.target.value)}
                className="form-select form-select-sm rounded-pill px-3"
              >
                <option value="">Select a profile to add...</option>
                {candidates.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} (@{u.username})
                  </option>
                ))}
              </select>
              <button
                type="submit"
                disabled={!selectedAddUserId}
                className="btn btn-sm btn-primary rounded-pill px-4 fw-bold"
              >
                Add
              </button>
            </div>
          </form>
        )}

        {loading ? (
          <div className="p-5 text-center text-muted">Loading list members...</div>
        ) : !list || list.members.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <h6 className="fw-bold">This list has no members yet</h6>
            <p className="small text-muted mb-0">
              Follow creators or use the selector above to add profiles to this list.
            </p>
          </div>
        ) : (
          <div className="row">
            {list.members.map((m) => (
              <div key={m.id} className="col-12 col-md-6 position-relative">
                <CreatorSuggestionCard profile={m} />
                <button
                  type="button"
                  onClick={() => handleToggleMember(m.id)}
                  className="btn btn-sm btn-danger rounded-pill position-absolute top-0 start-0 m-3 shadow-sm"
                  style={{ fontSize: 11 }}
                  title="Remove from list"
                >
                  ✕ Remove
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}

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
  const [memberFilter, setMemberFilter] = useState('');
  const [editingName, setEditingName] = useState(false);
  const [newListName, setNewListName] = useState('');
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
      setNewListName(listRes.list?.name || '');
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

  const handleRenameList = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newListName.trim()) return;
    try {
      const res = await apiFetch(`/api/lists/${listId}`, {
        method: 'PUT',
        body: JSON.stringify({ name: newListName }),
      });
      setList((prev) => (prev ? { ...prev, name: res.name } : prev));
      setEditingName(false);
      setFeedback('List renamed!');
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const handleClearList = async () => {
    try {
      await apiFetch(`/api/lists/${listId}/clear`, { method: 'POST' });
      setFeedback('Cleared all members from list.');
      await loadList();
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

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
  const filteredMembers = (list?.members || []).filter(
    (m) =>
      m.name.toLowerCase().includes(memberFilter.toLowerCase()) ||
      m.username.toLowerCase().includes(memberFilter.toLowerCase())
  );

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 820 }}>
        <div className="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom flex-wrap gap-2">
          <div className="d-flex align-items-center gap-3">
            <button
              type="button"
              onClick={() => router.push('/my/lists')}
              className="btn btn-sm btn-outline-secondary rounded-pill"
            >
              ← Back to Lists
            </button>
            {editingName ? (
              <form onSubmit={handleRenameList} className="d-flex gap-1">
                <input
                  type="text"
                  value={newListName}
                  onChange={(e) => setNewListName(e.target.value)}
                  className="form-control form-control-sm rounded-pill px-3"
                />
                <button type="submit" className="btn btn-sm btn-primary rounded-pill">
                  Save
                </button>
                <button
                  type="button"
                  onClick={() => setEditingName(false)}
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                >
                  ✕
                </button>
              </form>
            ) : (
              <div className="d-flex align-items-center gap-2">
                <h4 className="fw-bold mb-0">{list ? list.name : 'List'}</h4>
                {list?.list_type === 'custom' && (
                  <button
                    type="button"
                    onClick={() => setEditingName(true)}
                    className="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2"
                    title="Rename list"
                  >
                    ✏️
                  </button>
                )}
              </div>
            )}
          </div>
          {list && (
            <div className="d-flex align-items-center gap-2">
              <span className="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                {list.members.length} members
              </span>
              {list.members.length > 0 && (
                <button
                  type="button"
                  onClick={handleClearList}
                  className="btn btn-sm btn-outline-danger rounded-pill"
                >
                  Clear List
                </button>
              )}
            </div>
          )}
        </div>

        {feedback && <div className="alert alert-info py-2 small mb-3">{feedback}</div>}

        {list && candidates.length > 0 && (
          <form
            onSubmit={handleAddSelected}
            className="card border rounded-4 p-3 mb-3 bg-body-tertiary"
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

        {list && list.members.length > 0 && (
          <div className="mb-3">
            <input
              type="search"
              value={memberFilter}
              onChange={(e) => setMemberFilter(e.target.value)}
              placeholder="Filter members in this list..."
              className="form-control form-control-sm rounded-pill px-3"
            />
          </div>
        )}

        {loading ? (
          <div className="p-5 text-center text-muted">Loading list members...</div>
        ) : !list || filteredMembers.length === 0 ? (
          <div className="p-5 text-center border rounded-4 bg-body-tertiary">
            <h6 className="fw-bold">No members found in this list</h6>
            <p className="small text-muted mb-0">
              Follow creators or use the selector above to add profiles to this list.
            </p>
          </div>
        ) : (
          <div className="row">
            {filteredMembers.map((m) => (
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

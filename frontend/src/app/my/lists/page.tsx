'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, UserList } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function ListsPage() {
  const [lists, setLists] = useState<UserList[]>([]);
  const [newListName, setNewListName] = useState('');
  const [loading, setLoading] = useState(true);

  const loadLists = async () => {
    setLoading(true);
    try {
      const res = await apiFetch('/api/lists');
      setLists(res.lists || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLists();
  }, []);

  const handleCreateList = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newListName.trim()) return;
    try {
      await apiFetch('/api/lists', {
        method: 'POST',
        body: JSON.stringify({ name: newListName }),
      });
      setNewListName('');
      loadLists();
    } catch {
      // ignore
    }
  };

  const handleDeleteList = async (id: number) => {
    try {
      await apiFetch(`/api/lists/${id}`, { method: 'DELETE' });
      loadLists();
    } catch {
      // ignore
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4" style={{ maxWidth: 820 }}>
        <div className="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom gap-2">
          <h4 className="fw-bold mb-0">User Lists</h4>
          <form onSubmit={handleCreateList} className="d-flex gap-2">
            <input
              type="text"
              value={newListName}
              onChange={(e) => setNewListName(e.target.value)}
              placeholder="New custom list name..."
              className="form-control form-control-sm rounded-pill px-3"
            />
            <button
              type="submit"
              className="btn btn-sm btn-primary rounded-pill px-3 fw-bold flex-shrink-0"
            >
              + Create List
            </button>
          </form>
        </div>

        {loading ? (
          <div className="p-5 text-center text-muted">Loading lists...</div>
        ) : (
          <div className="row g-3">
            {lists.map((list) => (
              <div key={list.id} className="col-12 col-md-6">
                <div className="card border rounded-4 p-3 h-100 shadow-sm">
                  <div className="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <h5 className="fw-bold mb-0">{list.name}</h5>
                      <span className="small text-muted">
                        {list.members_count} {list.members_count === 1 ? 'member' : 'members'}
                      </span>
                    </div>
                    <div className="d-flex gap-1">
                      <Link
                        href={`/my/lists/${list.id}`}
                        className="btn btn-sm btn-outline-primary rounded-pill px-3"
                      >
                        View
                      </Link>
                      {list.list_type === 'custom' && (
                        <button
                          type="button"
                          onClick={() => handleDeleteList(list.id)}
                          className="btn btn-sm btn-outline-danger rounded-pill"
                        >
                          🗑️
                        </button>
                      )}
                    </div>
                  </div>
                  <div className="d-flex gap-1 mt-2">
                    {list.members.slice(0, 5).map((m) => (
                      <img
                        key={m.id}
                        src={m.avatar || '/img/default-avatar.jpg'}
                        alt={m.name}
                        title={m.name}
                        className="rounded-circle border"
                        style={{ width: 34, height: 34, objectFit: 'cover' }}
                      />
                    ))}
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


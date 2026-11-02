'use client';

import React, { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { apiFetch, ConversationContact, DirectMessage, UserProfile } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

function MessengerContent() {
  const searchParams = useSearchParams();
  const initialContactId = Number(searchParams?.get('contact')) || null;
  const { user, refreshUser } = useAuth();

  const [contacts, setContacts] = useState<ConversationContact[]>([]);
  const [selectedUserId, setSelectedUserId] = useState<number | null>(initialContactId);
  const [activeContact, setActiveContact] = useState<UserProfile | null>(null);
  const [messages, setMessages] = useState<DirectMessage[]>([]);
  const [messageText, setMessageText] = useState('');
  const [messagePrice, setMessagePrice] = useState('0');
  const [searchFilter, setSearchFilter] = useState('');
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  const loadContacts = async () => {
    try {
      const res = await apiFetch('/api/messenger/contacts');
      const list: ConversationContact[] = res.contacts || [];
      setContacts(list);
      if (!selectedUserId && list.length > 0) {
        setSelectedUserId(list[0].user_id);
      }
    } catch {
      // ignore
    }
  };

  const loadConversation = async (uid: number) => {
    try {
      const res = await apiFetch(`/api/messenger/messages/${uid}`);
      setActiveContact(res.contact);
      setMessages(res.messages || []);
      await refreshUser();
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    loadContacts();
  }, []);

  useEffect(() => {
    if (selectedUserId) {
      loadConversation(selectedUserId);
    }
  }, [selectedUserId]);

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId || !messageText.trim()) return;
    setBusy(true);
    setFeedback(null);
    try {
      const res = await apiFetch('/api/messenger/send', {
        method: 'POST',
        body: JSON.stringify({
          receiver_id: selectedUserId,
          message: messageText,
          price: parseFloat(messagePrice) || 0,
        }),
      });
      setMessages((prev) => [...prev, res.message]);
      setMessageText('');
      setMessagePrice('0');
      loadContacts();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleUnlockMessage = async (msgId: number) => {
    setBusy(true);
    setFeedback(null);
    try {
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'message-unlock',
          message_id: msgId,
          provider: 'credit',
        }),
      });
      if (selectedUserId) {
        await loadConversation(selectedUserId);
      }
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleDeleteMessage = async (msgId: number) => {
    try {
      await apiFetch(`/api/messenger/message/${msgId}`, { method: 'DELETE' });
      setMessages((prev) => prev.filter((m) => m.id !== msgId));
      loadContacts();
    } catch (err: any) {
      setFeedback(err.message);
    }
  };

  const filteredContacts = contacts.filter(
    (c) =>
      c.name.toLowerCase().includes(searchFilter.toLowerCase()) ||
      c.username.toLowerCase().includes(searchFilter.toLowerCase())
  );

  return (
    <div className="row g-0 min-vh-100">
      {/* Contacts Sidebar */}
      <div className="col-12 col-md-4 border-end d-flex flex-column">
        <div className="p-3 border-bottom">
          <h5 className="fw-bold mb-2">Messages</h5>
          <input
            type="search"
            value={searchFilter}
            onChange={(e) => setSearchFilter(e.target.value)}
            placeholder="Search conversations..."
            className="form-control form-control-sm rounded-pill px-3"
          />
        </div>

        <div className="flex-fill overflow-auto">
          {filteredContacts.map((c) => {
            const isSelected = c.user_id === selectedUserId;
            return (
              <button
                key={c.user_id}
                type="button"
                onClick={() => setSelectedUserId(c.user_id)}
                className={`w-100 text-start border-0 border-bottom p-3 d-flex align-items-center gap-3 ${
                  isSelected ? 'bg-primary-subtle' : 'bg-transparent'
                }`}
              >
                <img
                  src={c.avatar || '/img/default-avatar.jpg'}
                  alt={c.name}
                  className="rounded-circle border"
                  style={{ width: 48, height: 48, objectFit: 'cover' }}
                />
                <div className="overflow-hidden flex-fill">
                  <div className="d-flex justify-content-between align-items-center">
                    <span className="fw-bold text-truncate">
                      {c.name} {c.is_verified && '✅'}
                    </span>
                    {c.unread_count > 0 && (
                      <span className="badge rounded-pill bg-danger">{c.unread_count}</span>
                    )}
                  </div>
                  <div className="small text-muted text-truncate">{c.last_message}</div>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Active Conversation Thread */}
      <div className="col-12 col-md-8 d-flex flex-column">
        {activeContact ? (
          <>
            <div className="p-3 border-bottom d-flex justify-content-between align-items-center">
              <div className="d-flex align-items-center gap-2">
                <img
                  src={activeContact.avatar || '/img/default-avatar.jpg'}
                  alt={activeContact.name}
                  className="rounded-circle border"
                  style={{ width: 42, height: 42, objectFit: 'cover' }}
                />
                <div>
                  <Link
                    href={`/${activeContact.username}`}
                    className="fw-bold text-reset text-decoration-none"
                  >
                    {activeContact.name} {activeContact.is_verified && '✅'}
                  </Link>
                  <div className="small text-muted">@{activeContact.username}</div>
                </div>
              </div>
              <Link
                href={`/${activeContact.username}`}
                className="btn btn-sm btn-outline-secondary rounded-pill px-3"
              >
                View Profile
              </Link>
            </div>

            <div className="p-3 flex-fill overflow-auto d-flex flex-column gap-3">
              {messages.length === 0 ? (
                <div className="text-center text-muted my-auto py-5">
                  No messages yet. Say hello to {activeContact.name}!
                </div>
              ) : (
                messages.map((m) => {
                  const isMine = user && m.sender_id === user.id;
                  return (
                    <div
                      key={m.id}
                      className={`d-flex flex-column ${
                        isMine ? 'align-items-end' : 'align-items-start'
                      }`}
                    >
                      <div
                        className={`p-3 rounded-4 ${
                          isMine ? 'bg-primary text-white' : 'bg-body-tertiary border'
                        }`}
                        style={{ maxWidth: '78%' }}
                      >
                        {m.price > 0 && (
                          <div className="mb-1">
                            <span className="badge bg-warning text-dark">
                              PPV ${m.price.toFixed(2)}
                            </span>
                          </div>
                        )}
                        <div>{m.message}</div>
                        {!m.is_unlocked && (
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => handleUnlockMessage(m.id)}
                            className="btn btn-sm btn-warning rounded-pill fw-bold mt-2"
                          >
                            Unlock Message for ${m.price.toFixed(2)}
                          </button>
                        )}
                      </div>
                      <div className="d-flex align-items-center gap-2 mt-1 px-1">
                        <span className="small text-muted" style={{ fontSize: 11 }}>
                          {m.created_at}
                        </span>
                        {isMine && (
                          <button
                            type="button"
                            onClick={() => handleDeleteMessage(m.id)}
                            className="btn btn-link btn-sm p-0 text-danger text-decoration-none"
                            style={{ fontSize: 11 }}
                            title="Delete message"
                          >
                            Delete
                          </button>
                        )}
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {feedback && (
              <div className="alert alert-danger mx-3 py-2 small mb-2">{feedback}</div>
            )}

            {/* Composer Footer */}
            <form onSubmit={handleSendMessage} className="p-3 border-top bg-body-tertiary">
              <div className="row g-2 align-items-center">
                <div className="col">
                  <input
                    type="text"
                    value={messageText}
                    onChange={(e) => setMessageText(e.target.value)}
                    placeholder={`Write a message to ${activeContact.name}...`}
                    className="form-control rounded-pill px-3"
                  />
                </div>
                <div className="col-auto" style={{ width: 130 }}>
                  <div className="input-group input-group-sm">
                    <span className="input-group-text" title="Optional PPV Price">
                      $
                    </span>
                    <input
                      type="number"
                      min="0"
                      step="1"
                      value={messagePrice}
                      onChange={(e) => setMessagePrice(e.target.value)}
                      className="form-control"
                      title="PPV Price"
                    />
                  </div>
                </div>
                <div className="col-auto">
                  <button
                    type="submit"
                    disabled={busy}
                    className="btn btn-primary rounded-pill px-4 fw-bold"
                  >
                    Send
                  </button>
                </div>
              </div>
            </form>
          </>
        ) : (
          <div className="p-5 text-center text-muted my-auto">
            Select a conversation to start messaging.
          </div>
        )}
      </div>
    </div>
  );
}

export default function MessengerPage() {
  return (
    <AppShell>
      <Suspense fallback={<div className="p-5 text-center">Loading Messenger...</div>}>
        <MessengerContent />
      </Suspense>
    </AppShell>
  );
}


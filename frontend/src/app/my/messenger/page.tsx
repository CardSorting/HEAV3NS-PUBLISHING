'use client';

import React, { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { apiFetch, Attachment, ConversationContact, DirectMessage, UserProfile } from '@/lib/api';
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
  const [attachments, setAttachments] = useState<string[]>([]);
  const [vaultItems, setVaultItems] = useState<Attachment[]>([]);
  const [vaultPickerOpen, setVaultPickerOpen] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [tipDrawerOpen, setTipDrawerOpen] = useState(false);
  const [tipAmount, setTipAmount] = useState('10');
  const [searchFilter, setSearchFilter] = useState('');
  const [mobileShowThread, setMobileShowThread] = useState(Boolean(initialContactId));
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
    } catch (e: any) {
      setFeedback(e.message);
    }
  };

  useEffect(() => {
    loadContacts();
    apiFetch('/api/vault')
      .then((res) => setVaultItems(res.items || []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (selectedUserId) {
      loadConversation(selectedUserId);
    }
  }, [selectedUserId]);

  const handleUploadMedia = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    setUploading(true);
    setFeedback(null);
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
      setFeedback(err.message);
    } finally {
      setUploading(false);
    }
  };

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId || (!messageText.trim() && attachments.length === 0)) return;
    setBusy(true);
    setFeedback(null);
    try {
      const res = await apiFetch('/api/messenger/send', {
        method: 'POST',
        body: JSON.stringify({
          receiver_id: selectedUserId,
          message: messageText,
          price: parseFloat(messagePrice) || 0,
          attachments: attachments.length > 0 ? attachments : undefined,
        }),
      });
      setMessages((prev) => [...prev, res.message]);
      setMessageText('');
      setMessagePrice('0');
      setAttachments([]);
      setVaultPickerOpen(false);
      loadContacts();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSendTip = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedUserId || !activeContact) return;
    setBusy(true);
    setFeedback(null);
    try {
      const amt = parseFloat(tipAmount) || 5;
      await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'tip',
          recipient_user_id: selectedUserId,
          amount: amt,
          provider: 'credit',
        }),
      });
      const tipMsg = await apiFetch('/api/messenger/send', {
        method: 'POST',
        body: JSON.stringify({
          receiver_id: selectedUserId,
          message: `💵 Sent a $${amt.toFixed(2)} tip!`,
          price: 0,
        }),
      });
      setMessages((prev) => [...prev, tipMsg.message]);
      setTipDrawerOpen(false);
      await refreshUser();
      loadContacts();
    } catch (err: any) {
      setFeedback(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleTogglePinMessage = async (msgId: number) => {
    try {
      const res = await apiFetch(`/api/messenger/pin/${msgId}`, { method: 'POST' });
      setMessages((prev) =>
        prev.map((m) => (m.id === msgId ? { ...m, is_pinned: res.is_pinned } : m))
      );
    } catch (err: any) {
      setFeedback(err.message);
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

  const pinnedMessages = messages.filter((m) => m.is_pinned);

  return (
    <div className="row g-0 min-vh-100">
      {/* Contacts Sidebar */}
      <div
        className={`col-12 col-md-4 border-end flex-column ${
          mobileShowThread ? 'd-none d-md-flex' : 'd-flex'
        }`}
      >
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
                onClick={() => {
                  setSelectedUserId(c.user_id);
                  setMobileShowThread(true);
                }}
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
      <div
        className={`col-12 col-md-8 flex-column ${
          mobileShowThread ? 'd-flex' : 'd-none d-md-flex'
        }`}
      >
        {activeContact ? (
          <>
            <div className="p-3 border-bottom d-flex justify-content-between align-items-center">
              <div className="d-flex align-items-center gap-2">
                <button
                  type="button"
                  onClick={() => setMobileShowThread(false)}
                  className="btn btn-sm btn-outline-secondary rounded-pill d-md-none me-1"
                >
                  ←
                </button>
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
              <div className="d-flex gap-2">
                <button
                  type="button"
                  onClick={() => setTipDrawerOpen(!tipDrawerOpen)}
                  className="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold"
                >
                  💵 Tip
                </button>
                <Link
                  href={`/${activeContact.username}`}
                  className="btn btn-sm btn-outline-secondary rounded-pill px-3"
                >
                  Profile
                </Link>
              </div>
            </div>

            {/* Pinned Messages Banner */}
            {pinnedMessages.length > 0 && (
              <div className="px-3 py-2 border-bottom bg-warning-subtle small d-flex justify-content-between align-items-center">
                <div className="text-truncate">
                  <strong>📌 Pinned:</strong> {pinnedMessages[pinnedMessages.length - 1].message}
                </div>
                <span className="badge bg-warning text-dark ms-2">{pinnedMessages.length}</span>
              </div>
            )}

            {/* In-Chat Tip Drawer */}
            {tipDrawerOpen && (
              <form onSubmit={handleSendTip} className="p-3 border-bottom bg-body-tertiary">
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <strong className="small">💵 Send Tip to {activeContact.name}</strong>
                  <button
                    type="button"
                    onClick={() => setTipDrawerOpen(false)}
                    className="btn-close btn-sm"
                  />
                </div>
                <div className="input-group input-group-sm">
                  <span className="input-group-text">$</span>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    value={tipAmount}
                    onChange={(e) => setTipAmount(e.target.value)}
                    className="form-control"
                  />
                  <button type="submit" disabled={busy} className="btn btn-success fw-semibold">
                    Send Tip
                  </button>
                </div>
              </form>
            )}

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
                        <div className="d-flex flex-wrap gap-1 mb-1">
                          {m.is_pinned && (
                            <span className="badge bg-info text-dark">📌 Pinned</span>
                          )}
                          {m.price > 0 && (
                            <span className="badge bg-warning text-dark">
                              PPV ${m.price.toFixed(2)}
                            </span>
                          )}
                        </div>
                        <div>{m.message}</div>
                        {m.is_unlocked && m.attachments && m.attachments.length > 0 && (
                          <div className="mt-2 d-flex flex-wrap gap-2">
                            {m.attachments.map((att) =>
                              att.file_type === 'video' ? (
                                <video
                                  key={att.id}
                                  src={att.file_url}
                                  controls
                                  className="rounded-3 border"
                                  style={{ maxWidth: 220, maxHeight: 220 }}
                                />
                              ) : (
                                <img
                                  key={att.id}
                                  src={att.file_url}
                                  alt={att.filename}
                                  className="rounded-3 border"
                                  style={{ maxWidth: 220, maxHeight: 220, objectFit: 'cover' }}
                                />
                              )
                            )}
                          </div>
                        )}
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
                        <button
                          type="button"
                          onClick={() => handleTogglePinMessage(m.id)}
                          className="btn btn-link btn-sm p-0 text-secondary text-decoration-none"
                          style={{ fontSize: 11 }}
                          title="Pin or unpin message"
                        >
                          {m.is_pinned ? 'Unpin' : 'Pin'}
                        </button>
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

            {/* Vault Picker Drawer */}
            {vaultPickerOpen && (
              <div className="p-2 border-top bg-body-tertiary">
                <div className="d-flex justify-content-between align-items-center mb-1 px-1">
                  <strong className="small">Attach from Media Vault</strong>
                  <button
                    type="button"
                    onClick={() => setVaultPickerOpen(false)}
                    className="btn-close btn-sm"
                  />
                </div>
                <div className="d-flex gap-2 overflow-auto py-1">
                  {[
                    '/img/home-scene-1.svg',
                    '/img/home-scene-2.svg',
                    '/img/home-scene-3.svg',
                    ...vaultItems.map((v) => v.file_url),
                  ]
                    .filter((v, idx, arr) => arr.indexOf(v) === idx)
                    .map((url) => (
                      <button
                        key={url}
                        type="button"
                        onClick={() =>
                          setAttachments((prev) => (prev.includes(url) ? prev : [...prev, url]))
                        }
                        className="btn p-1 border rounded-3 bg-body flex-shrink-0"
                      >
                        <img
                          src={url}
                          alt="Vault"
                          style={{ width: 54, height: 54, objectFit: 'cover' }}
                        />
                      </button>
                    ))}
                </div>
              </div>
            )}

            {/* Selected Attachments Preview */}
            {attachments.length > 0 && (
              <div className="px-3 py-2 border-top bg-body d-flex flex-wrap gap-2">
                {attachments.map((url, idx) => (
                  <div key={idx} className="position-relative border rounded-3 p-1">
                    <img
                      src={url}
                      alt="Attached"
                      style={{ width: 52, height: 52, objectFit: 'cover' }}
                      className="rounded-2"
                    />
                    <button
                      type="button"
                      onClick={() =>
                        setAttachments((prev) => prev.filter((_, i) => i !== idx))
                      }
                      className="btn btn-sm btn-danger rounded-circle position-absolute top-0 end-0 py-0 px-1"
                      style={{ fontSize: 10 }}
                    >
                      ✕
                    </button>
                  </div>
                ))}
              </div>
            )}

            {/* Composer Footer */}
            <form onSubmit={handleSendMessage} className="p-3 border-top bg-body-tertiary">
              <div className="row g-2 align-items-center">
                <div className="col-auto d-flex gap-1">
                  <label
                    className="btn btn-sm btn-outline-secondary rounded-pill mb-0"
                    title="Upload media attachment"
                  >
                    {uploading ? '...' : '📎'}
                    <input
                      type="file"
                      multiple
                      accept="image/*,video/*"
                      onChange={handleUploadMedia}
                      className="d-none"
                    />
                  </label>
                  <button
                    type="button"
                    onClick={() => setVaultPickerOpen(!vaultPickerOpen)}
                    className="btn btn-sm btn-outline-secondary rounded-pill"
                    title="Pick from Media Vault"
                  >
                    🖼️
                  </button>
                </div>
                <div className="col">
                  <input
                    type="text"
                    value={messageText}
                    onChange={(e) => setMessageText(e.target.value)}
                    placeholder={`Write a message to ${activeContact.name}...`}
                    className="form-control rounded-pill px-3"
                  />
                </div>
                <div className="col-auto" style={{ width: 115 }}>
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


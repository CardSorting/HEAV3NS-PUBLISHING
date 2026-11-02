'use client';

import React, { useState } from 'react';
import { apiFetch } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function ContactPage() {
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('');
  const [message, setMessage] = useState('');
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setStatusMessage(null);
    setError(null);
    try {
      const res = await apiFetch('/api/contact', {
        method: 'POST',
        body: JSON.stringify({ email, subject, message }),
      });
      setStatusMessage(res.message);
      setSubject('');
      setMessage('');
    } catch (err: any) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-5" style={{ maxWidth: 880 }}>
        <div className="row align-items-center g-4">
          <div className="col-12 col-md-7">
            <h3 className="fw-bold mb-1">Contact Us</h3>
            <p className="text-muted small mb-4">
              Have questions about creator monetization, payouts, or account support? Send us a
              message.
            </p>

            {statusMessage && <div className="alert alert-success py-2 small">{statusMessage}</div>}
            {error && <div className="alert alert-danger py-2 small">{error}</div>}

            <form onSubmit={handleSubmit} className="d-grid gap-3">
              <div>
                <label className="form-label small fw-bold">Your Email Address</label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Subject</label>
                <input
                  type="text"
                  required
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  placeholder="Creator verification / Billing question"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Message</label>
                <textarea
                  rows={5}
                  required
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  placeholder="How can we help?"
                  className="form-control rounded-4 p-3"
                />
              </div>
              <button
                type="submit"
                disabled={busy}
                className="btn btn-primary rounded-pill py-2 fw-bold"
              >
                {busy ? 'Sending...' : 'Send Message'}
              </button>
            </form>
          </div>
          <div className="col-12 col-md-5 text-center">
            <img
              src="/img/contact-page.svg"
              alt="Contact"
              className="img-fluid"
              style={{ maxHeight: 280 }}
            />
          </div>
        </div>
      </div>
    </AppShell>
  );
}


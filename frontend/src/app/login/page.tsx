'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

export default function LoginPage() {
  const { loginWithCredentials, darkMode } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await loginWithCredentials(email, password);
      router.push('/feed');
    } catch (err: any) {
      setError(err.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  const handleQuickDemo = async (demoEmail: string) => {
    setEmail(demoEmail);
    setPassword('password123');
    setError(null);
    setLoading(true);
    try {
      await loginWithCredentials(demoEmail, 'password123');
      router.push('/feed');
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container-fluid min-vh-100 d-flex align-items-center bg-body">
      <div className="row w-100 min-vh-100">
        <div className="col-12 col-md-6 d-flex align-items-center justify-content-center p-4">
          <div className="w-100" style={{ maxWidth: 420 }}>
            <Link href="/" className="d-inline-block mb-4">
              <img
                src={darkMode ? '/img/logo-white.png' : '/img/logo-black.png'}
                alt="JustFans"
                style={{ height: 42 }}
              />
            </Link>
            <h2 className="fw-bold mb-1">Welcome back</h2>
            <p className="text-muted mb-4">Sign in to your creator or subscriber account.</p>

            {error && <div className="alert alert-danger py-2 small">{error}</div>}

            <form onSubmit={handleSubmit} className="d-grid gap-3">
              <div>
                <label className="form-label small fw-bold">Email or Username</label>
                <input
                  type="text"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="aria@justfans.local or ariavance"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Password</label>
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <button
                type="submit"
                disabled={loading}
                className="btn btn-primary rounded-pill py-2 fw-bold"
              >
                {loading ? 'Signing in...' : 'Login'}
              </button>
            </form>

            {/* Demo One-Click Logins */}
            <div className="mt-4 p-3 rounded-4 border bg-body-tertiary">
              <div className="small fw-bold text-uppercase text-muted mb-2">
                Instant Demo Accounts (Password: password123)
              </div>
              <div className="d-grid gap-2">
                <button
                  type="button"
                  onClick={() => handleQuickDemo('aria@justfans.local')}
                  className="btn btn-sm btn-outline-primary rounded-pill text-start px-3"
                >
                  ✨ <strong>Aria Vance</strong> (@ariavance) — Creator & Admin
                </button>
                <button
                  type="button"
                  onClick={() => handleQuickDemo('alex@justfans.local')}
                  className="btn btn-sm btn-outline-secondary rounded-pill text-start px-3"
                >
                  💎 <strong>Alex Rivera</strong> (@alexrivera) — Subscriber ($250 Wallet)
                </button>
                <button
                  type="button"
                  onClick={() => handleQuickDemo('marcus@justfans.local')}
                  className="btn btn-sm btn-outline-secondary rounded-pill text-start px-3"
                >
                  💪 <strong>Marcus Sterling</strong> (@marcusfit) — Fitness Creator
                </button>
              </div>
            </div>

            <div className="mt-4 text-center small">
              Don&apos;t have an account?{' '}
              <Link href="/register" className="fw-bold text-primary text-decoration-none">
                Sign up now
              </Link>
            </div>
          </div>
        </div>

        <div className="col-md-6 d-none d-md-flex align-items-center justify-content-center bg-body-tertiary border-start p-5">
          <div className="text-center" style={{ maxWidth: 460 }}>
            <img
              src="/img/home-header.svg"
              alt="Creator Platform"
              className="img-fluid mb-4"
              style={{ maxHeight: 340 }}
            />
            <h4 className="fw-bold">Connect directly with your biggest fans</h4>
            <p className="text-muted small">
              Subscriptions, Pay-Per-View media, live streaming, and instant payouts powered by a
              blazing-fast Rust backend and Next.js frontend.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}


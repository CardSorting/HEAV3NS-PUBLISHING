'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

export default function RegisterPage() {
  const { registerAccount, darkMode } = useAuth();
  const router = useRouter();
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [agreed, setAgreed] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agreed) {
      setError('You must agree to the Terms and Conditions.');
      return;
    }
    setError(null);
    setLoading(true);
    try {
      await registerAccount(name, username, email, password);
      router.push('/feed');
    } catch (err: any) {
      setError(err.message || 'Registration failed');
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
            <h2 className="fw-bold mb-1">Create your account</h2>
            <p className="text-muted mb-4">
              Join JustFans and receive $100.00 in welcome wallet credits to explore creators.
            </p>

            {error && <div className="alert alert-danger py-2 small">{error}</div>}

            <form onSubmit={handleSubmit} className="d-grid gap-3">
              <div>
                <label className="form-label small fw-bold">Full Name</label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Jordan Lee"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Username</label>
                <input
                  type="text"
                  required
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="jordanlee"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Email Address</label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="jordan@example.com"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div>
                <label className="form-label small fw-bold">Password (min 6 chars)</label>
                <input
                  type="password"
                  required
                  minLength={6}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="form-check small">
                <input
                  type="checkbox"
                  id="termsCheck"
                  checked={agreed}
                  onChange={(e) => setAgreed(e.target.checked)}
                  className="form-check-input"
                />
                <label htmlFor="termsCheck" className="form-check-label">
                  I confirm I am 18+ and agree to the{' '}
                  <Link href="/pages/terms-and-conditions">Terms of Use</Link> and{' '}
                  <Link href="/pages/privacy">Privacy Policy</Link>.
                </label>
              </div>
              <button
                type="submit"
                disabled={loading}
                className="btn btn-primary rounded-pill py-2 fw-bold"
              >
                {loading ? 'Creating account...' : 'Register'}
              </button>
            </form>

            <div className="mt-4 text-center small">
              Already have an account?{' '}
              <Link href="/login" className="fw-bold text-primary text-decoration-none">
                Sign in
              </Link>
            </div>
          </div>
        </div>

        <div className="col-md-6 d-none d-md-flex align-items-center justify-content-center bg-body-tertiary border-start p-5">
          <div className="text-center" style={{ maxWidth: 460 }}>
            <img
              src="/img/become-a-creator.svg"
              alt="Register"
              className="img-fluid mb-4"
              style={{ maxHeight: 340 }}
            />
            <h4 className="fw-bold">Launch your creator business in minutes</h4>
            <p className="text-muted small">
              Set your own subscription rates, publish locked PPV posts, host live streams, and keep
              85% of your revenue.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}


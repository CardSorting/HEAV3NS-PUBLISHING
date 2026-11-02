'use client';

import React, { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { apiFetch, UserProfile } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

function SettingsContent() {
  const searchParams = useSearchParams();
  const initialTab = searchParams?.get('tab') || 'profile';
  const { user, refreshUser } = useAuth();

  const [activeTab, setActiveTab] = useState(initialTab);
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [subscriptions, setSubscriptions] = useState<any[]>([]);
  const [transactions, setTransactions] = useState<any[]>([]);
  const [withdrawals, setWithdrawals] = useState<any[]>([]);
  const [verification, setVerification] = useState<any>(null);
  const [verifyFiles, setVerifyFiles] = useState('');
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Form states
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [bio, setBio] = useState('');
  const [location, setLocation] = useState('');
  const [website, setWebsite] = useState('');
  const [pronoun, setPronoun] = useState('');
  const [birthdate, setBirthdate] = useState('');
  const [avatar, setAvatar] = useState('');
  const [cover, setCover] = useState('');

  // Rates
  const [paidProfile, setPaidProfile] = useState(true);
  const [openProfile, setOpenProfile] = useState(false);
  const [price1, setPrice1] = useState('14.99');
  const [price3, setPrice3] = useState('11.99');
  const [price6, setPrice6] = useState('9.99');
  const [price12, setPrice12] = useState('7.99');

  // Wallet
  const [depositAmount, setDepositAmount] = useState('50');
  const [depositProvider, setDepositProvider] = useState('stripe');
  const [withdrawAmount, setWithdrawAmount] = useState('50');
  const [withdrawMethod, setWithdrawMethod] = useState('Bank Transfer (IBAN)');
  const [withdrawIdentifier, setWithdrawIdentifier] = useState('');

  // Account
  const [email, setEmail] = useState('');
  const [newPassword, setNewPassword] = useState('');

  // Privacy
  const [publicProfile, setPublicProfile] = useState(true);
  const [enable2fa, setEnable2fa] = useState(false);
  const [enableGeoblocking, setEnableGeoblocking] = useState(false);

  const loadSettings = async () => {
    try {
      const res = await apiFetch('/api/settings');
      const p: UserProfile = res.profile;
      setProfile(p);
      setSubscriptions(res.subscriptions || []);
      setTransactions(res.transactions || []);
      setWithdrawals(res.withdrawals || []);
      setVerification(res.verification || null);

      setName(p.name);
      setUsername(p.username);
      setBio(p.bio);
      setLocation(p.location);
      setWebsite(p.website);
      setPronoun(p.gender_pronoun || '');
      setBirthdate(p.birthdate || '');
      setAvatar(p.avatar || '');
      setCover(p.cover || '');

      setPaidProfile(p.paid_profile);
      setOpenProfile(p.open_profile);
      setPrice1(String(p.profile_access_price));
      setPrice3(String(p.profile_access_price_3_months));
      setPrice6(String(p.profile_access_price_6_months));
      setPrice12(String(p.profile_access_price_12_months));

      setEmail(p.email);
      setPublicProfile(p.public_profile);
      setEnable2fa(p.enable_2fa);
      setEnableGeoblocking(p.enable_geoblocking);
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    setActiveTab(initialTab);
  }, [initialTab]);

  useEffect(() => {
    loadSettings();
  }, []);

  const clearAlerts = () => {
    setStatusMessage(null);
    setErrorMessage(null);
  };

  const handleFileUpload = async (
    file: File | undefined,
    onSuccess: (fileUrl: string) => void
  ) => {
    if (!file) return;
    clearAlerts();
    setBusy(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await apiFetch('/api/attachment/upload', {
        method: 'POST',
        body: formData,
      });
      const uploaded = res.attachments?.[0];
      if (uploaded?.file_url) {
        onSuccess(uploaded.file_url);
        setStatusMessage('File uploaded! Click Save to persist changes.');
      }
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/settings/profile', {
        method: 'POST',
        body: JSON.stringify({
          name,
          username,
          bio,
          location,
          website,
          gender_pronoun: pronoun,
          birthdate,
          avatar,
          cover,
        }),
      });
      setStatusMessage(res.message);
      await refreshUser();
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSubmitVerification = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!verifyFiles.trim()) return;
    clearAlerts();
    setBusy(true);
    try {
      const filesList = verifyFiles
        .split(',')
        .map((f) => f.trim())
        .filter(Boolean);
      const res = await apiFetch('/api/settings/verify', {
        method: 'POST',
        body: JSON.stringify({ files: filesList }),
      });
      setStatusMessage(res.message);
      setVerifyFiles('');
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSaveRates = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/settings/rates', {
        method: 'POST',
        body: JSON.stringify({
          paid_profile: paidProfile,
          open_profile: openProfile,
          profile_access_price: parseFloat(price1) || 0,
          profile_access_price_3_months: parseFloat(price3) || 0,
          profile_access_price_6_months: parseFloat(price6) || 0,
          profile_access_price_12_months: parseFloat(price12) || 0,
        }),
      });
      setStatusMessage(res.message);
      await refreshUser();
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleDeposit = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/payments/initiate', {
        method: 'POST',
        body: JSON.stringify({
          payment_type: 'deposit',
          amount: parseFloat(depositAmount) || 0,
          provider: depositProvider,
        }),
      });
      setStatusMessage(res.message);
      await refreshUser();
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleWithdraw = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/withdrawals/request', {
        method: 'POST',
        body: JSON.stringify({
          amount: parseFloat(withdrawAmount) || 0,
          payment_method: withdrawMethod,
          payment_identifier: withdrawIdentifier,
        }),
      });
      setStatusMessage(res.message);
      await refreshUser();
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleCancelSubscription = async (subId: number) => {
    clearAlerts();
    try {
      await apiFetch(`/api/subscriptions/${subId}/cancel`, { method: 'POST' });
      setStatusMessage('Subscription canceled.');
      await loadSettings();
    } catch (err: any) {
      setErrorMessage(err.message);
    }
  };

  const handleSaveAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/settings/account', {
        method: 'POST',
        body: JSON.stringify({
          email,
          new_password: newPassword || undefined,
        }),
      });
      setStatusMessage(res.message);
      setNewPassword('');
      await refreshUser();
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const handleSavePrivacy = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAlerts();
    setBusy(true);
    try {
      const res = await apiFetch('/api/settings/privacy', {
        method: 'POST',
        body: JSON.stringify({
          public_profile: publicProfile,
          enable_2fa: enable2fa,
          enable_geoblocking: enableGeoblocking,
        }),
      });
      setStatusMessage(res.message);
    } catch (err: any) {
      setErrorMessage(err.message);
    } finally {
      setBusy(false);
    }
  };

  const tabs = [
    { key: 'profile', label: '👤 Edit Profile' },
    { key: 'rates', label: '💲 Creator Rates' },
    { key: 'wallet', label: '👛 Wallet & Withdrawals' },
    { key: 'subscriptions', label: '💎 Subscriptions' },
    { key: 'payments', label: '🧾 Payments & Invoices' },
    { key: 'verify', label: '✅ Creator Verification' },
    { key: 'account', label: '🔒 Account Security' },
    { key: 'privacy', label: '🛡️ Privacy & 2FA' },
    { key: 'referrals', label: '🎁 Referrals' },
  ];

  return (
    <div className="row g-0 min-vh-100">
      {/* Settings Menu Column */}
      <div className="col-12 col-md-4 col-lg-3 border-end p-3">
        <h5 className="fw-bold mb-3 px-2">Settings</h5>
        <div className="nav flex-column nav-pills gap-1">
          {tabs.map((t) => (
            <button
              key={t.key}
              type="button"
              onClick={() => {
                clearAlerts();
                setActiveTab(t.key);
              }}
              className={`nav-link text-start rounded-pill px-3 py-2 fw-semibold ${
                activeTab === t.key ? 'active' : 'text-reset'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>
      </div>

      {/* Settings Content Column */}
      <div className="col-12 col-md-8 col-lg-9 p-3 p-md-4">
        {statusMessage && <div className="alert alert-success py-2 small">{statusMessage}</div>}
        {errorMessage && <div className="alert alert-danger py-2 small">{errorMessage}</div>}

        {activeTab === 'profile' && (
          <form onSubmit={handleSaveProfile} className="d-grid gap-3" style={{ maxWidth: 620 }}>
            <h4 className="fw-bold mb-1">Profile Settings</h4>
            <p className="small text-muted mb-2">
              Customize your public creator profile, avatar, cover image, bio, and social links.
            </p>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label small fw-bold">Avatar Image URL or Upload</label>
                <div className="d-flex align-items-center gap-2 mb-2">
                  <img
                    src={avatar || '/img/default-avatar.jpg'}
                    alt="Avatar preview"
                    className="rounded-circle border"
                    style={{ width: 44, height: 44, objectFit: 'cover' }}
                  />
                  <input
                    type="text"
                    value={avatar}
                    onChange={(e) => setAvatar(e.target.value)}
                    placeholder="/img/default-avatar.jpg"
                    className="form-control form-control-sm rounded-pill px-3"
                  />
                </div>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileUpload(e.target.files?.[0], (url) => setAvatar(url))}
                  className="form-control form-control-sm rounded-pill"
                />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">Cover Banner URL or Upload</label>
                <div className="d-flex align-items-center gap-2 mb-2">
                  <img
                    src={cover || '/img/default-cover.png'}
                    alt="Cover preview"
                    className="rounded-3 border"
                    style={{ width: 72, height: 44, objectFit: 'cover' }}
                  />
                  <input
                    type="text"
                    value={cover}
                    onChange={(e) => setCover(e.target.value)}
                    placeholder="/img/default-cover.png"
                    className="form-control form-control-sm rounded-pill px-3"
                  />
                </div>
                <input
                  type="file"
                  accept="image/*"
                  onChange={(e) => handleFileUpload(e.target.files?.[0], (url) => setCover(url))}
                  className="form-control form-control-sm rounded-pill"
                />
              </div>
            </div>
            <div>
              <label className="form-label small fw-bold">Display Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="form-control rounded-pill px-3"
              />
            </div>
            <div>
              <label className="form-label small fw-bold">Username</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                className="form-control rounded-pill px-3"
              />
            </div>
            <div>
              <label className="form-label small fw-bold">Bio</label>
              <textarea
                rows={4}
                value={bio}
                onChange={(e) => setBio(e.target.value)}
                className="form-control rounded-4 p-3"
              />
            </div>
            <div className="row g-3">
              <div className="col-md-3">
                <label className="form-label small fw-bold">Pronouns</label>
                <input
                  type="text"
                  value={pronoun}
                  onChange={(e) => setPronoun(e.target.value)}
                  placeholder="she/her, they/them"
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold">Birthdate</label>
                <input
                  type="date"
                  value={birthdate}
                  onChange={(e) => setBirthdate(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold">Location</label>
                <input
                  type="text"
                  value={location}
                  onChange={(e) => setLocation(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold">Website URL</label>
                <input
                  type="text"
                  value={website}
                  onChange={(e) => setWebsite(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
            </div>
            <div>
              <button
                type="submit"
                disabled={busy}
                className="btn btn-primary rounded-pill px-5 fw-bold"
              >
                Save Profile
              </button>
            </div>
          </form>
        )}

        {activeTab === 'rates' && (
          <form onSubmit={handleSaveRates} className="d-grid gap-3" style={{ maxWidth: 620 }}>
            <h4 className="fw-bold mb-1">Creator Subscription Rates</h4>
            <p className="small text-muted mb-2">
              Configure your monthly subscription pricing and multi-month promotional bundles.
            </p>
            <div className="form-check form-switch">
              <input
                type="checkbox"
                id="paidProfSwitch"
                checked={paidProfile}
                onChange={(e) => setPaidProfile(e.target.checked)}
                className="form-check-input"
              />
              <label htmlFor="paidProfSwitch" className="form-check-label fw-semibold">
                Enable Paid Subscription Paywall
              </label>
            </div>
            <div className="form-check form-switch">
              <input
                type="checkbox"
                id="openProfSwitch"
                checked={openProfile}
                onChange={(e) => setOpenProfile(e.target.checked)}
                className="form-check-input"
              />
              <label htmlFor="openProfSwitch" className="form-check-label fw-semibold">
                Open Profile (Free posts visible to everyone; PPV still locked)
              </label>
            </div>

            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label small fw-bold">Monthly Price ($ USD)</label>
                <input
                  type="number"
                  step="0.01"
                  value={price1}
                  onChange={(e) => setPrice1(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">3-Month Bundle ($/mo)</label>
                <input
                  type="number"
                  step="0.01"
                  value={price3}
                  onChange={(e) => setPrice3(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">6-Month Bundle ($/mo)</label>
                <input
                  type="number"
                  step="0.01"
                  value={price6}
                  onChange={(e) => setPrice6(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">12-Month Bundle ($/mo)</label>
                <input
                  type="number"
                  step="0.01"
                  value={price12}
                  onChange={(e) => setPrice12(e.target.value)}
                  className="form-control rounded-pill px-3"
                />
              </div>
            </div>
            <div>
              <button
                type="submit"
                disabled={busy}
                className="btn btn-primary rounded-pill px-5 fw-bold"
              >
                Save Subscription Rates
              </button>
            </div>
          </form>
        )}

        {activeTab === 'wallet' && (
          <div style={{ maxWidth: 740 }}>
            <div className="card border rounded-4 p-4 mb-4 bg-primary text-white shadow-sm">
              <div className="small text-uppercase opacity-75 fw-bold">Available Wallet Balance</div>
              <div className="display-5 fw-bold my-1">
                ${(profile?.wallet_balance ?? user?.wallet_balance ?? 0).toFixed(2)}
              </div>
              <div className="small opacity-75">
                Use wallet credits for instant subscriptions, tips, and PPV unlocks, or withdraw your
                creator earnings.
              </div>
            </div>

            <div className="row g-4">
              {/* Deposit Funds */}
              <div className="col-12 col-md-6">
                <form onSubmit={handleDeposit} className="card border rounded-4 p-3 h-100">
                  <h6 className="fw-bold mb-3">➕ Deposit Funds</h6>
                  <div className="mb-3">
                    <label className="form-label small fw-bold">Amount ($ USD)</label>
                    <input
                      type="number"
                      min="5"
                      step="1"
                      value={depositAmount}
                      onChange={(e) => setDepositAmount(e.target.value)}
                      className="form-control rounded-pill px-3"
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold">Payment Method</label>
                    <select
                      value={depositProvider}
                      onChange={(e) => setDepositProvider(e.target.value)}
                      className="form-select rounded-pill px-3"
                    >
                      <option value="stripe">Stripe Checkout (Card / Apple Pay)</option>
                      <option value="paypal">PayPal Express</option>
                      <option value="crypto">CoinGate / NowPayments Crypto</option>
                    </select>
                  </div>
                  <button
                    type="submit"
                    disabled={busy}
                    className="btn btn-primary rounded-pill fw-bold mt-auto"
                  >
                    Add Funds Now
                  </button>
                </form>
              </div>

              {/* Withdraw Earnings */}
              <div className="col-12 col-md-6">
                <form onSubmit={handleWithdraw} className="card border rounded-4 p-3 h-100">
                  <h6 className="fw-bold mb-3">🏦 Request Payout Withdrawal</h6>
                  <div className="mb-2">
                    <label className="form-label small fw-bold">Amount ($ USD, min $20)</label>
                    <input
                      type="number"
                      min="20"
                      step="1"
                      value={withdrawAmount}
                      onChange={(e) => setWithdrawAmount(e.target.value)}
                      className="form-control rounded-pill px-3"
                    />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-bold">Payout Method</label>
                    <select
                      value={withdrawMethod}
                      onChange={(e) => setWithdrawMethod(e.target.value)}
                      className="form-select rounded-pill px-3"
                    >
                      <option value="Bank Transfer (IBAN)">Bank Transfer (IBAN / ACH)</option>
                      <option value="Stripe Connect">Stripe Connect Instant Payout</option>
                      <option value="PayPal">PayPal Payouts</option>
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold">Payout Account Identifier</label>
                    <input
                      type="text"
                      value={withdrawIdentifier}
                      onChange={(e) => setWithdrawIdentifier(e.target.value)}
                      placeholder="IBAN, email, or account #"
                      className="form-control rounded-pill px-3"
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={busy}
                    className="btn btn-outline-primary rounded-pill fw-bold mt-auto"
                  >
                    Request Withdrawal
                  </button>
                </form>
              </div>
            </div>

            {withdrawals.length > 0 && (
              <div className="mt-4">
                <h6 className="fw-bold mb-2">Withdrawal History</h6>
                <div className="table-responsive border rounded-4">
                  <table className="table table-sm mb-0 align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {withdrawals.map((w) => (
                        <tr key={w.id}>
                          <td>#{w.id}</td>
                          <td className="fw-bold">${w.amount.toFixed(2)}</td>
                          <td>{w.payment_method}</td>
                          <td>
                            <span
                              className={`badge ${
                                w.status === 'approved'
                                  ? 'bg-success'
                                  : w.status === 'rejected'
                                  ? 'bg-danger'
                                  : 'bg-warning text-dark'
                              }`}
                            >
                              {w.status}
                            </span>
                          </td>
                          <td className="small text-muted">{w.created_at}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        )}

        {activeTab === 'subscriptions' && (
          <div>
            <h4 className="fw-bold mb-3">Active & Past Subscriptions</h4>
            {subscriptions.length === 0 ? (
              <div className="p-4 border rounded-4 bg-body-tertiary text-center text-muted">
                No subscriptions found.
              </div>
            ) : (
              <div className="table-responsive border rounded-4">
                <table className="table mb-0 align-middle">
                  <thead>
                    <tr>
                      <th>Creator</th>
                      <th>Subscriber</th>
                      <th>Price</th>
                      <th>Status</th>
                      <th>Renews / Expires</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {subscriptions.map((s) => (
                      <tr key={s.id}>
                        <td>
                          <Link
                            href={`/${s.creator_username}`}
                            className="fw-bold text-reset text-decoration-none"
                          >
                            {s.creator_name} (@{s.creator_username})
                          </Link>
                        </td>
                        <td>@{s.subscriber_username}</td>
                        <td>${s.amount.toFixed(2)}</td>
                        <td>
                          <span
                            className={`badge ${
                              s.status === 'completed' ? 'bg-success' : 'bg-secondary'
                            }`}
                          >
                            {s.status}
                          </span>
                        </td>
                        <td className="small text-muted">{s.expires_at}</td>
                        <td>
                          {s.status === 'completed' && (
                            <button
                              type="button"
                              onClick={() => handleCancelSubscription(s.id)}
                              className="btn btn-sm btn-outline-danger rounded-pill"
                            >
                              Cancel
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        {activeTab === 'payments' && (
          <div>
            <h4 className="fw-bold mb-3">Transaction History & Invoices</h4>
            {transactions.length === 0 ? (
              <div className="p-4 border rounded-4 bg-body-tertiary text-center text-muted">
                No transactions recorded yet.
              </div>
            ) : (
              <div className="table-responsive border rounded-4">
                <table className="table mb-0 align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Type</th>
                      <th>Amount</th>
                      <th>Provider</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th>Invoice</th>
                    </tr>
                  </thead>
                  <tbody>
                    {transactions.map((t) => (
                      <tr key={t.id}>
                        <td>#{t.id}</td>
                        <td className="text-capitalize">{t.tx_type.replace(/-/g, ' ')}</td>
                        <td className="fw-bold">
                          ${t.amount.toFixed(2)} {t.currency}
                        </td>
                        <td className="text-uppercase small">{t.payment_provider}</td>
                        <td>
                          <span className="badge bg-success">{t.status}</span>
                        </td>
                        <td className="small text-muted">{t.created_at}</td>
                        <td>
                          <Link
                            href={`/invoices/${t.invoice_id || t.id}`}
                            className="btn btn-sm btn-outline-secondary rounded-pill"
                          >
                            🧾 View Invoice
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        {activeTab === 'verify' && (
          <div style={{ maxWidth: 620 }}>
            <h4 className="fw-bold mb-1">Creator Identity Verification</h4>
            <p className="small text-muted mb-3">
              Verify your identity to unlock creator payouts, subscription pricing, and the verified
              creator badge.
            </p>
            <div className="p-3 rounded-4 border bg-body-tertiary mb-4">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <div className="small text-uppercase text-muted fw-bold">Verification Status</div>
                  <div className="fw-bold fs-5">
                    {profile?.is_verified
                      ? '✅ Verified Creator'
                      : verification?.status === 'pending'
                      ? '⏳ Verification Pending Review'
                      : verification?.status === 'rejected'
                      ? '❌ Verification Rejected'
                      : '⚪ Not Yet Verified'}
                  </div>
                </div>
                {verification?.status && (
                  <span
                    className={`badge ${
                      verification.status === 'verified'
                        ? 'bg-success'
                        : verification.status === 'rejected'
                        ? 'bg-danger'
                        : 'bg-warning text-dark'
                    }`}
                  >
                    {verification.status}
                  </span>
                )}
              </div>
              {verification?.rejection_reason && (
                <div className="small text-danger mt-2">
                  Note: {verification.rejection_reason}
                </div>
              )}
            </div>

            {!profile?.is_verified && (
              <form onSubmit={handleSubmitVerification} className="d-grid gap-3">
                <div>
                  <label className="form-label small fw-bold">
                    Upload Government ID Document or Enter Document URL
                  </label>
                  <input
                    type="file"
                    accept="image/*,.pdf"
                    onChange={(e) =>
                      handleFileUpload(e.target.files?.[0], (url) => setVerifyFiles(url))
                    }
                    className="form-control rounded-pill mb-2"
                  />
                  <input
                    type="text"
                    value={verifyFiles}
                    onChange={(e) => setVerifyFiles(e.target.value)}
                    placeholder="Document file URL (e.g. /uploads/gov-id.png)"
                    className="form-control rounded-pill px-3"
                  />
                </div>
                <div>
                  <button
                    type="submit"
                    disabled={busy || !verifyFiles.trim()}
                    className="btn btn-primary rounded-pill px-5 fw-bold"
                  >
                    Submit Verification Request
                  </button>
                </div>
              </form>
            )}
          </div>
        )}

        {activeTab === 'account' && (
          <form onSubmit={handleSaveAccount} className="d-grid gap-3" style={{ maxWidth: 520 }}>
            <h4 className="fw-bold mb-1">Account Security</h4>
            <div>
              <label className="form-label small fw-bold">Email Address</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="form-control rounded-pill px-3"
              />
            </div>
            <div>
              <label className="form-label small fw-bold">New Password (leave blank to keep)</label>
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="••••••••"
                className="form-control rounded-pill px-3"
              />
            </div>
            <div>
              <button
                type="submit"
                disabled={busy}
                className="btn btn-primary rounded-pill px-5 fw-bold"
              >
                Update Account
              </button>
            </div>
          </form>
        )}

        {activeTab === 'privacy' && (
          <form onSubmit={handleSavePrivacy} className="d-grid gap-3" style={{ maxWidth: 520 }}>
            <h4 className="fw-bold mb-1">Privacy & Device Security</h4>
            <div className="form-check form-switch">
              <input
                type="checkbox"
                id="pubProfSwitch"
                checked={publicProfile}
                onChange={(e) => setPublicProfile(e.target.checked)}
                className="form-check-input"
              />
              <label htmlFor="pubProfSwitch" className="form-check-label fw-semibold">
                Public Profile (Discoverable in Search & Explore)
              </label>
            </div>
            <div className="form-check form-switch">
              <input
                type="checkbox"
                id="twoFaSwitch"
                checked={enable2fa}
                onChange={(e) => setEnable2fa(e.target.checked)}
                className="form-check-input"
              />
              <label htmlFor="twoFaSwitch" className="form-check-label fw-semibold">
                Enable Two-Factor Device Verification (2FA)
              </label>
            </div>
            <div className="form-check form-switch">
              <input
                type="checkbox"
                id="geoSwitch"
                checked={enableGeoblocking}
                onChange={(e) => setEnableGeoblocking(e.target.checked)}
                className="form-check-input"
              />
              <label htmlFor="geoSwitch" className="form-check-label fw-semibold">
                Enable Country Geoblocking
              </label>
            </div>
            <div>
              <button
                type="submit"
                disabled={busy}
                className="btn btn-primary rounded-pill px-5 fw-bold"
              >
                Save Privacy Settings
              </button>
            </div>
          </form>
        )}

        {activeTab === 'referrals' && (
          <div style={{ maxWidth: 620 }}>
            <h4 className="fw-bold mb-2">Creator Referral Program</h4>
            <p className="text-muted small mb-3">
              Share your personal referral link and earn a 5% bonus commission on referred creator
              revenues.
            </p>
            <div className="p-3 rounded-4 border bg-body-tertiary">
              <div className="small fw-bold text-uppercase text-muted mb-1">Your Referral Code</div>
              <div className="fs-4 fw-bold text-primary">{profile?.referral_code || 'JUSTFANS'}</div>
              <div className="small text-muted mt-2">
                Referral URL: <code>https://justfans.local/register?ref={profile?.referral_code}</code>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default function SettingsPage() {
  return (
    <AppShell>
      <Suspense fallback={<div className="p-5 text-center">Loading Settings...</div>}>
        <SettingsContent />
      </Suspense>
    </AppShell>
  );
}


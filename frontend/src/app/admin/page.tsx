'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, UserProfile } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function AdminDashboardPage() {
  const [metrics, setMetrics] = useState<any>(null);
  const [users, setUsers] = useState<UserProfile[]>([]);
  const [withdrawals, setWithdrawals] = useState<any[]>([]);
  const [reports, setReports] = useState<any[]>([]);
  const [verifications, setVerifications] = useState<any[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const loadDashboard = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch('/api/admin/dashboard');
      setMetrics(res.metrics);
      setUsers(res.users || []);
      setWithdrawals(res.withdrawals || []);
      setReports(res.reports || []);
      setVerifications(res.verifications || []);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDashboard();
  }, []);

  const handleWithdrawalAction = async (id: number, action: 'approve' | 'reject') => {
    try {
      await apiFetch(`/api/admin/withdrawals/${id}`, {
        method: 'POST',
        body: JSON.stringify({ action }),
      });
      loadDashboard();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleReportAction = async (id: number, action: 'resolve' | 'dismiss') => {
    try {
      await apiFetch(`/api/admin/reports/${id}`, {
        method: 'POST',
        body: JSON.stringify({ action }),
      });
      loadDashboard();
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleVerificationAction = async (id: number, action: 'approve' | 'reject') => {
    try {
      await apiFetch(`/api/admin/verifications/${id}`, {
        method: 'POST',
        body: JSON.stringify({ action }),
      });
      loadDashboard();
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        <div className="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
          <div>
            <h4 className="fw-bold mb-0">🛡️ Platform Admin Panel</h4>
            <p className="small text-muted mb-0">
              Manage users, creator payouts, identity verifications, and content moderation reports.
            </p>
          </div>
          <button
            type="button"
            onClick={loadDashboard}
            className="btn btn-sm btn-outline-primary rounded-pill px-3"
          >
            🔄 Refresh
          </button>
        </div>

        {error && (
          <div className="alert alert-warning">
            <strong>Access Note:</strong> {error}. Sign in as <code>aria@justfans.local</code>{' '}
            (Admin) to view admin metrics.
          </div>
        )}

        {!loading && metrics && (
          <>
            {/* Metrics Row */}
            <div className="row g-3 mb-4">
              <div className="col-6 col-md-3">
                <div className="card border rounded-4 p-3 text-center bg-body-tertiary">
                  <div className="small text-muted text-uppercase fw-bold">Total Users</div>
                  <div className="fs-3 fw-bold text-primary">{metrics.total_users}</div>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border rounded-4 p-3 text-center bg-body-tertiary">
                  <div className="small text-muted text-uppercase fw-bold">Published Posts</div>
                  <div className="fs-3 fw-bold text-primary">{metrics.total_posts}</div>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border rounded-4 p-3 text-center bg-body-tertiary">
                  <div className="small text-muted text-uppercase fw-bold">Subscriptions</div>
                  <div className="fs-3 fw-bold text-primary">{metrics.total_subscriptions}</div>
                </div>
              </div>
              <div className="col-6 col-md-3">
                <div className="card border rounded-4 p-3 text-center bg-body-tertiary">
                  <div className="small text-muted text-uppercase fw-bold">Gross Volume</div>
                  <div className="fs-3 fw-bold text-success">
                    ${Number(metrics.total_revenue || 0).toFixed(2)}
                  </div>
                </div>
              </div>
            </div>

            {/* Creator Identity Verifications Table */}
            <h6 className="fw-bold text-uppercase small text-muted mb-2">
              Creator Identity Verification Requests
            </h6>
            <div className="table-responsive border rounded-4 mb-4">
              <table className="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Documents</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {verifications.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="text-center text-muted py-3">
                        No verification requests submitted.
                      </td>
                    </tr>
                  ) : (
                    verifications.map((v) => (
                      <tr key={v.id}>
                        <td>#{v.id}</td>
                        <td>
                          <Link href={`/${v.user_username}`} className="fw-bold text-reset">
                            {v.user_name} (@{v.user_username})
                          </Link>
                        </td>
                        <td>
                          <code className="small">
                            {Array.isArray(v.files) ? v.files.join(', ') : String(v.files || '')}
                          </code>
                        </td>
                        <td>
                          <span
                            className={`badge ${
                              v.status === 'verified'
                                ? 'bg-success'
                                : v.status === 'rejected'
                                ? 'bg-danger'
                                : 'bg-warning text-dark'
                            }`}
                          >
                            {v.status}
                          </span>
                        </td>
                        <td>
                          {v.status === 'pending' && (
                            <div className="d-flex gap-1">
                              <button
                                type="button"
                                onClick={() => handleVerificationAction(v.id, 'approve')}
                                className="btn btn-sm btn-success rounded-pill px-3"
                              >
                                Approve
                              </button>
                              <button
                                type="button"
                                onClick={() => handleVerificationAction(v.id, 'reject')}
                                className="btn btn-sm btn-outline-danger rounded-pill px-3"
                              >
                                Reject
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Content & User Moderation Reports Table */}
            <h6 className="fw-bold text-uppercase small text-muted mb-2">
              Moderation Reports
            </h6>
            <div className="table-responsive border rounded-4 mb-4">
              <table className="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Reporter</th>
                    <th>Target</th>
                    <th>Reason</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {reports.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="text-center text-muted py-3">
                        No moderation reports filed.
                      </td>
                    </tr>
                  ) : (
                    reports.map((r) => (
                      <tr key={r.id}>
                        <td>#{r.id}</td>
                        <td>
                          <Link href={`/${r.reporter}`} className="text-reset">
                            @{r.reporter}
                          </Link>
                        </td>
                        <td className="small">
                          {r.post_id
                            ? `Post #${r.post_id} (@${r.reported})`
                            : r.reported
                            ? `@${r.reported}`
                            : 'General'}
                        </td>
                        <td className="fw-semibold">{r.report_type}</td>
                        <td className="small text-muted">{r.details || '—'}</td>
                        <td>
                          <span
                            className={`badge ${
                              r.status === 'resolved'
                                ? 'bg-success'
                                : r.status === 'dismissed'
                                ? 'bg-secondary'
                                : 'bg-warning text-dark'
                            }`}
                          >
                            {r.status}
                          </span>
                        </td>
                        <td>
                          {r.status === 'received' && (
                            <div className="d-flex gap-1">
                              <button
                                type="button"
                                onClick={() => handleReportAction(r.id, 'resolve')}
                                className="btn btn-sm btn-success rounded-pill px-3"
                              >
                                Resolve
                              </button>
                              <button
                                type="button"
                                onClick={() => handleReportAction(r.id, 'dismiss')}
                                className="btn btn-sm btn-outline-secondary rounded-pill px-3"
                              >
                                Dismiss
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Payout Withdrawals Approval Table */}
            <h6 className="fw-bold text-uppercase small text-muted mb-2">
              Creator Withdrawal Requests
            </h6>
            <div className="table-responsive border rounded-4 mb-4">
              <table className="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Creator</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {withdrawals.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="text-center text-muted py-3">
                        No withdrawal requests pending.
                      </td>
                    </tr>
                  ) : (
                    withdrawals.map((w) => (
                      <tr key={w.id}>
                        <td>#{w.id}</td>
                        <td>
                          <Link href={`/${w.user_username}`} className="fw-bold text-reset">
                            {w.user_name} (@{w.user_username})
                          </Link>
                        </td>
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
                        <td>
                          {w.status === 'requested' && (
                            <div className="d-flex gap-1">
                              <button
                                type="button"
                                onClick={() => handleWithdrawalAction(w.id, 'approve')}
                                className="btn btn-sm btn-success rounded-pill px-3"
                              >
                                Approve
                              </button>
                              <button
                                type="button"
                                onClick={() => handleWithdrawalAction(w.id, 'reject')}
                                className="btn btn-sm btn-outline-danger rounded-pill px-3"
                              >
                                Reject
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* Users Table */}
            <h6 className="fw-bold text-uppercase small text-muted mb-2">Registered Users</h6>
            <div className="table-responsive border rounded-4 mb-4">
              <table className="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Wallet</th>
                    <th>Rate</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id}>
                      <td>#{u.id}</td>
                      <td className="fw-bold">
                        <Link href={`/${u.username}`} className="text-reset text-decoration-none">
                          {u.name} {u.is_verified && '✅'}
                        </Link>
                      </td>
                      <td>@{u.username}</td>
                      <td>{u.email}</td>
                      <td>
                        <span
                          className={`badge ${
                            u.role_id === 1 ? 'bg-warning text-dark' : 'bg-secondary'
                          }`}
                        >
                          {u.role_id === 1 ? 'Admin' : 'User'}
                        </span>
                      </td>
                      <td>${u.wallet_balance.toFixed(2)}</td>
                      <td>${u.profile_access_price.toFixed(2)}/mo</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>
    </AppShell>
  );
}


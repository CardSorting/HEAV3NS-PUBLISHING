'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { apiFetch } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { AppShell } from '@/components/AppShell';

export default function InvoicePage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuth();
  const invoiceId = String(params?.id || '1001');
  const [invoice, setInvoice] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    apiFetch(`/api/invoices/${encodeURIComponent(invoiceId)}`)
      .then((res) => setInvoice(res.invoice || null))
      .catch(() => setInvoice(null))
      .finally(() => setLoading(false));
  }, [invoiceId]);

  const amount = invoice ? Number(invoice.amount || 0) : 14.99;
  const currency = invoice?.currency || 'USD';
  const txTypeLabel = invoice?.tx_type
    ? String(invoice.tx_type).replace(/-/g, ' ')
    : 'Digital Creator Subscription / Content Unlock';
  const issuedDate = invoice?.created_at || new Date().toISOString().slice(0, 10);

  return (
    <AppShell>
      <div className="p-3 p-md-5" style={{ maxWidth: 760 }}>
        <div className="d-flex justify-content-between align-items-center mb-4">
          <button
            type="button"
            onClick={() => router.back()}
            className="btn btn-sm btn-outline-secondary rounded-pill px-3"
          >
            ← Back to Payments
          </button>
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-sm btn-primary rounded-pill px-4 fw-bold"
          >
            🖨️ Print Invoice
          </button>
        </div>

        {loading ? (
          <div className="p-5 text-center text-muted">Loading invoice...</div>
        ) : (
          <div className="card border rounded-4 p-4 p-md-5 shadow-sm">
            <div className="d-flex justify-content-between align-items-start pb-4 mb-4 border-bottom">
              <div>
                <h3 className="fw-bold mb-1">
                  INVOICE #{invoice?.invoice_id || invoiceId}
                </h3>
                <div className="small text-muted">HEAV3NS-Publishing Inc.</div>
              </div>
              <span className="badge bg-success px-3 py-2 text-uppercase">
                {invoice?.status || 'PAID'}
              </span>
            </div>

            <div className="row mb-4">
              <div className="col-6">
                <div className="small fw-bold text-uppercase text-muted">Billed To</div>
                <div className="fw-bold">{invoice?.sender_name || user?.name || 'Valued Member'}</div>
                <div className="small text-muted">
                  {invoice?.sender_email || user?.email || 'member@justfans.local'}
                </div>
              </div>
              <div className="col-6 text-end">
                <div className="small fw-bold text-uppercase text-muted">Issued Date</div>
                <div>{issuedDate}</div>
                {invoice?.payment_provider && (
                  <div className="small text-muted text-uppercase mt-1">
                    Provider: {invoice.payment_provider}
                  </div>
                )}
              </div>
            </div>

            <table className="table border-top">
              <thead>
                <tr>
                  <th>Description</th>
                  <th className="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td className="text-capitalize">
                    {txTypeLabel}
                    {invoice?.recipient_name ? ` — ${invoice.recipient_name}` : ''} (Tx #
                    {invoice?.id || invoiceId})
                  </td>
                  <td className="text-end fw-bold">
                    ${amount.toFixed(2)} {currency}
                  </td>
                </tr>
                <tr>
                  <td className="text-muted small">Platform Processing & VAT (0%)</td>
                  <td className="text-end text-muted small">$0.00 {currency}</td>
                </tr>
                <tr>
                  <td className="fw-bold">Total Paid</td>
                  <td className="text-end fw-bold fs-5 text-primary">
                    ${amount.toFixed(2)} {currency}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppShell>
  );
}


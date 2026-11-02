'use client';

import React, { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { apiFetch } from '@/lib/api';
import { AppShell } from '@/components/AppShell';

export default function PublicPageView() {
  const params = useParams();
  const slug = String(params?.slug || 'help');
  const [page, setPage] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch(`/api/pages/${encodeURIComponent(slug)}`)
      .then((res) => setPage(res.page))
      .catch(() => setPage(null))
      .finally(() => setLoading(false));
  }, [slug]);

  return (
    <AppShell>
      <div className="p-3 p-md-5" style={{ maxWidth: 820 }}>
        {loading ? (
          <div className="text-muted">Loading page...</div>
        ) : !page ? (
          <div>
            <h4 className="fw-bold">Page not found</h4>
          </div>
        ) : (
          <div>
            <h2 className="fw-bold mb-1">{page.title}</h2>
            <div className="small text-muted mb-4 pb-2 border-bottom">
              Last updated: {page.updated_at}
            </div>
            <div
              className="lh-lg"
              dangerouslySetInnerHTML={{ __html: page.content }}
            />
          </div>
        )}
      </div>
    </AppShell>
  );
}


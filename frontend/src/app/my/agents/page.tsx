'use client';

import React, { Suspense, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { AppShell } from '@/components/AppShell';
import { AgentAuditLogItem, AgentTokenItem, apiFetch } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';

const ALL_SCOPES = [
  { id: 'read', label: 'Read', desc: 'Feed, profiles, analytics, notifications & vault read' },
  { id: 'write', label: 'Write', desc: 'Publish/edit posts, reels, stories, streams & profile' },
  { id: 'engage', label: 'Engage', desc: 'React, comment, vote on polls, follow & curate lists' },
  { id: 'monetize', label: 'Monetize', desc: 'Update subscription rates, bundles & promo offers' },
  { id: 'messages', label: 'Messages', desc: 'Read/send DMs, PPV messages & mass broadcasts' },
  { id: 'wallet', label: 'Wallet', desc: 'Subscribe, unlock PPV, tip creators & request payouts' },
];

const SAMPLE_TOOL_TEMPLATES: Record<string, { label: string; category: string; args: Record<string, any> }> = {
  account_whoami: {
    label: 'account_whoami — Inspect Authenticated Account & Agent Guardrails',
    category: 'Account & Identity',
    args: {},
  },
  ai_caption_suggest: {
    label: 'ai_caption_suggest — Generate High-Converting Creator Copy',
    category: 'Publishing',
    args: {
      topic: 'Behind-the-scenes modular synth session in Neo-Tokyo',
      tone: 'engaging',
    },
  },
  post_create_or_update: {
    label: 'post_create_or_update — Publish Post with Interactive Poll',
    category: 'Publishing',
    args: {
      text: 'Dropped a new autonomous studio session! Which stem pack should my agent release next?',
      price: 0,
      is_pinned: false,
      poll_answers: ['Cyberpunk Analog Bass', 'Ambient Modular Pads', '4K Studio Walkthrough'],
    },
  },
  rates_and_offers_update: {
    label: 'rates_and_offers_update — Configure Bundles & Flash Promo Offer',
    category: 'Profile & Rates',
    args: {
      paid_profile: true,
      open_profile: false,
      profile_access_price: 9.99,
      profile_access_price_3_months: 25.47,
      profile_access_price_6_months: 47.95,
      profile_access_price_12_months: 83.92,
      offer_discount_percent: 25,
      offer_expires_at: '2099-12-31 23:59:59',
    },
  },
  messenger_broadcast: {
    label: 'messenger_broadcast — Mass Campaign to Subscribers/Followers',
    category: 'Messenger',
    args: {
      message: 'Hey VIPs! Enjoy my new exclusive acoustic & synth drop in your inbox.',
      audience: 'subscribers',
      price: 0,
    },
  },
  creator_discover: {
    label: 'creator_discover — Search & Filter Creators',
    category: 'Feed & Social',
    args: {
      query: 'aria',
      filter: 'people',
    },
  },
  analytics_and_notifications: {
    label: 'analytics_and_notifications — Read 85/15 Revenue Split & Audit Trail',
    category: 'Analytics',
    args: {},
  },
};

function AgentsHubContent() {
  const { user, refreshUser } = useAuth();
  const searchParams = useSearchParams();
  const initialView = searchParams.get('view') || 'keys';

  const [activeView, setActiveView] = useState<'keys' | 'playground' | 'audit' | 'connect'>(
    initialView === 'playground' || initialView === 'audit' || initialView === 'connect'
      ? initialView
      : 'keys'
  );

  const [tokens, setTokens] = useState<AgentTokenItem[]>([]);
  const [auditLogs, setAuditLogs] = useState<AgentAuditLogItem[]>([]);
  const [manifest, setManifest] = useState<any>(null);
  const [analytics, setAnalytics] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [statusMsg, setStatusMsg] = useState<{ type: 'success' | 'danger'; text: string } | null>(null);

  // New Token Form State
  const [keyName, setKeyName] = useState('Primary Autonomous Agent');
  const [selectedScopes, setSelectedScopes] = useState<string[]>([
    'read',
    'write',
    'engage',
    'monetize',
    'messages',
    'wallet',
  ]);
  const [maxSpendPerTx, setMaxSpendPerTx] = useState('25');
  const [dailySpendLimit, setDailySpendLimit] = useState('100');
  const [expiresInDays, setExpiresInDays] = useState('90');
  const [newlyMintedSecret, setNewlyMintedSecret] = useState<string | null>(null);
  const [minting, setMinting] = useState(false);

  // Interactive MCP Playground State
  const [selectedTool, setSelectedTool] = useState('account_whoami');
  const [toolArgsText, setToolArgsText] = useState(
    JSON.stringify(SAMPLE_TOOL_TEMPLATES.account_whoami.args, null, 2)
  );
  const [customTokenHeader, setCustomTokenHeader] = useState('');
  const [rpcMode, setRpcMode] = useState<'jsonrpc' | 'rest'>('jsonrpc');
  const [toolRunning, setToolRunning] = useState(false);
  const [toolOutput, setToolOutput] = useState<any>(null);

  const loadData = async () => {
    setLoading(true);
    try {
      const [manifestData, tokensData, analyticsData] = await Promise.all([
        apiFetch('/api/mcp/manifest').catch(() => null),
        user ? apiFetch('/api/agent/tokens').catch(() => null) : Promise.resolve(null),
        user ? apiFetch('/api/analytics/overview').catch(() => null) : Promise.resolve(null),
      ]);
      if (manifestData) setManifest(manifestData);
      if (tokensData) {
        setTokens(tokensData.agent_tokens || []);
        setAuditLogs(tokensData.agent_audit_logs || []);
      }
      if (analyticsData) setAnalytics(analyticsData);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [user?.id]);

  const toggleScope = (scopeId: string) => {
    setSelectedScopes((prev) =>
      prev.includes(scopeId) ? prev.filter((s) => s !== scopeId) : [...prev, scopeId]
    );
  };

  const handleMintToken = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!keyName.trim()) return;
    setMinting(true);
    setStatusMsg(null);
    try {
      const res = await apiFetch('/api/agent/tokens', {
        method: 'POST',
        body: JSON.stringify({
          name: keyName.trim(),
          scopes: selectedScopes,
          max_spend_per_tx: parseFloat(maxSpendPerTx) || 0,
          daily_spend_limit: parseFloat(dailySpendLimit) || 0,
          expires_in_days: parseInt(expiresInDays, 10) || null,
        }),
      });
      setNewlyMintedSecret(res.agent_token);
      setCustomTokenHeader(res.agent_token);
      setTokens(res.agent_tokens || []);
      setStatusMsg({
        type: 'success',
        text: 'Scoped MCP Agent Token generated! Copy the raw secret below — it is cryptographically hashed in SQLite and shown only once.',
      });
      await loadData();
    } catch (err: any) {
      setStatusMsg({ type: 'danger', text: err.message || 'Failed to mint agent token' });
    } finally {
      setMinting(false);
    }
  };

  const handleRevokeToken = async (id: number) => {
    try {
      await apiFetch(`/api/agent/tokens/${id}/revoke`, { method: 'POST' });
      setStatusMsg({ type: 'success', text: 'Agent token revoked immediately.' });
      await loadData();
    } catch (err: any) {
      setStatusMsg({ type: 'danger', text: err.message });
    }
  };

  const handleDeleteToken = async (id: number) => {
    try {
      await apiFetch(`/api/agent/tokens/${id}`, { method: 'DELETE' });
      setStatusMsg({ type: 'success', text: 'Agent token removed.' });
      await loadData();
    } catch (err: any) {
      setStatusMsg({ type: 'danger', text: err.message });
    }
  };

  const handleSelectTemplate = (toolName: string) => {
    setSelectedTool(toolName);
    if (SAMPLE_TOOL_TEMPLATES[toolName]) {
      setToolArgsText(JSON.stringify(SAMPLE_TOOL_TEMPLATES[toolName].args, null, 2));
    } else if (manifest?.tools) {
      const found = manifest.tools.find((t: any) => t.name === toolName);
      if (found?.inputSchema?.properties) {
        const sample: Record<string, any> = {};
        Object.entries(found.inputSchema.properties).forEach(([k, v]: [string, any]) => {
          if (v.type === 'boolean') sample[k] = true;
          else if (v.type === 'number' || v.type === 'integer') sample[k] = 0;
          else if (v.type === 'array') sample[k] = [];
          else sample[k] = '';
        });
        setToolArgsText(JSON.stringify(sample, null, 2));
      } else {
        setToolArgsText('{}');
      }
    }
  };

  const handleExecuteTool = async () => {
    setToolRunning(true);
    setStatusMsg(null);
    try {
      const parsedArgs = toolArgsText.trim() ? JSON.parse(toolArgsText) : {};
      const extraHeaders: Record<string, string> = {};
      if (customTokenHeader.trim()) {
        extraHeaders['X-Agent-Token'] = customTokenHeader.trim();
      }

      if (rpcMode === 'jsonrpc') {
        const rpcRes = await apiFetch('/api/mcp', {
          method: 'POST',
          headers: extraHeaders,
          body: JSON.stringify({
            jsonrpc: '2.0',
            id: Date.now(),
            method: 'tools/call',
            params: {
              name: selectedTool,
              arguments: parsedArgs,
            },
          }),
        });
        setToolOutput(rpcRes);
      } else {
        const restRes = await apiFetch(`/api/mcp/tools/${selectedTool}`, {
          method: 'POST',
          headers: extraHeaders,
          body: JSON.stringify(parsedArgs),
        });
        setToolOutput(restRes);
      }
      await refreshUser();
      await loadData();
    } catch (err: any) {
      setToolOutput({ error: err.message || 'Tool execution failed' });
    } finally {
      setToolRunning(false);
    }
  };

  const activeTokensCount = tokens.filter((t) => t.is_active).length;
  const total24hSpend = tokens.reduce(
    (acc, t) => acc + (t.spent_last_24h ?? (t as any).spent_today ?? 0),
    0,
  );

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        {/* Header Banner */}
        <div
          className="rounded-4 p-4 mb-4 text-white shadow-sm"
          style={{
            background: 'linear-gradient(135deg, #111827 0%, #311042 55%, #cb0c9f 100%)',
          }}
        >
          <div className="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div className="d-flex align-items-center gap-2 mb-1">
                <span className="badge bg-warning text-dark fw-bold">MCP 2024-11-05</span>
                <span className="badge bg-light text-dark">JSON-RPC 2.0 + REST Bridge</span>
                <span className="badge bg-info text-dark">
                  {manifest?.tools?.length || 26} Autonomous Tools
                </span>
              </div>
              <h3 className="fw-bold mb-1">🤖 Autonomous AI Agents &amp; MCP Control Center</h3>
              <p className="mb-0 small opacity-75">
                Delegate full or scoped account operations to your personal AI agent (Claude Desktop, Cursor, OpenAI Agents, or custom bots) with hardware-grade per-transaction and 24-hour rolling spend guardrails.
              </p>
            </div>
            <div className="d-flex gap-2">
              <Link
                href="/my/settings?tab=rates"
                className="btn btn-sm btn-outline-light rounded-pill px-3"
              >
                💰 Creator Rates &amp; Offers
              </Link>
              <Link
                href="/my/messenger"
                className="btn btn-sm btn-light text-dark fw-semibold rounded-pill px-3"
              >
                📢 Broadcast DM
              </Link>
            </div>
          </div>

          {/* KPI Strip */}
          <div className="row g-2 mt-3 pt-3 border-top border-secondary border-opacity-50">
            <div className="col-6 col-md-3">
              <div className="small opacity-75">Active Agent Keys</div>
              <div className="fs-5 fw-bold">{activeTokensCount}</div>
            </div>
            <div className="col-6 col-md-3">
              <div className="small opacity-75">24h Agent Spend</div>
              <div className="fs-5 fw-bold">${total24hSpend.toFixed(2)}</div>
            </div>
            <div className="col-6 col-md-3">
              <div className="small opacity-75">Net Creator Revenue (85%)</div>
              <div className="fs-5 fw-bold">
                ${(analytics?.analytics?.net_earnings_85_pct ?? analytics?.earnings?.net_earnings_85_pct ?? 0).toFixed(2)}
              </div>
            </div>
            <div className="col-6 col-md-3">
              <div className="small opacity-75">Logged Agent Actions</div>
              <div className="fs-5 fw-bold">{auditLogs.length}</div>
            </div>
          </div>
        </div>

        {statusMsg && (
          <div
            className={`alert alert-${statusMsg.type} alert-dismissible fade show rounded-4`}
            role="alert"
          >
            {statusMsg.text}
            <button
              type="button"
              className="btn-close"
              onClick={() => setStatusMsg(null)}
            />
          </div>
        )}

        {newlyMintedSecret && (
          <div className="card border-success border-2 rounded-4 mb-4 shadow-sm">
            <div className="card-body">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <h6 className="fw-bold text-success mb-0">
                  🔑 New Scoped MCP Agent Token Generated (Copy Now — Shown Once)
                </h6>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                  onClick={() => setNewlyMintedSecret(null)}
                >
                  Dismiss
                </button>
              </div>
              <div className="input-group">
                <input
                  type="text"
                  className="form-control font-monospace bg-body-tertiary"
                  readOnly
                  value={newlyMintedSecret}
                />
                <button
                  type="button"
                  className="btn btn-success fw-semibold"
                  onClick={() => {
                    navigator.clipboard?.writeText(newlyMintedSecret);
                    setStatusMsg({ type: 'success', text: 'Agent token copied to clipboard!' });
                  }}
                >
                  Copy Token
                </button>
              </div>
              <div className="small text-muted mt-2">
                Pass via <code>X-Agent-Token: {newlyMintedSecret}</code> or{' '}
                <code>Authorization: Bearer {newlyMintedSecret}</code> on any REST or{' '}
                <code>POST /api/mcp</code> request.
              </div>
            </div>
          </div>
        )}

        {/* Navigation Tabs */}
        <ul className="nav nav-pills gap-2 mb-4">
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'keys' ? 'active' : ''}`}
              onClick={() => setActiveView('keys')}
            >
              🔑 Agent Keys &amp; Spend Guardrails
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'playground' ? 'active' : ''}`}
              onClick={() => setActiveView('playground')}
            >
              ⚡ Interactive MCP Tool Console
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'audit' ? 'active' : ''}`}
              onClick={() => setActiveView('audit')}
            >
              🛡️ Autonomous Activity Audit ({auditLogs.length})
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'connect' ? 'active' : ''}`}
              onClick={() => setActiveView('connect')}
            >
              🔌 Connect Claude / Cursor / Autonomous Bot
            </button>
          </li>
        </ul>

        {/* VIEW 1: AGENT KEYS & GUARDRAILS */}
        {activeView === 'keys' && (
          <div className="row g-4">
            <div className="col-lg-5">
              <div className="card rounded-4 border shadow-sm">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-1">Mint New Autonomous Agent Token</h5>
                  <p className="text-muted small mb-3">
                    Create a dedicated token for an AI agent with least-privilege scopes and hard financial caps.
                  </p>

                  {!user ? (
                    <div className="alert alert-warning rounded-3 small">
                      Please <Link href="/login">sign in</Link> or use the{' '}
                      <button
                        type="button"
                        className="btn btn-link btn-sm p-0 align-baseline"
                        onClick={() => {
                          setActiveView('playground');
                          handleSelectTemplate('account_register');
                        }}
                      >
                        MCP account_register tool
                      </button>{' '}
                      to self-provision an autonomous account.
                    </div>
                  ) : (
                    <form onSubmit={handleMintToken}>
                      <div className="mb-3">
                        <label className="form-label small fw-semibold">Agent Identifier / Label</label>
                        <input
                          type="text"
                          className="form-control"
                          value={keyName}
                          onChange={(e) => setKeyName(e.target.value)}
                          placeholder="e.g. Claude Growth Manager"
                          required
                        />
                      </div>

                      <div className="mb-3">
                        <label className="form-label small fw-semibold">Allowed Capability Scopes</label>
                        <div className="d-flex flex-column gap-2">
                          {ALL_SCOPES.map((sc) => (
                            <label
                              key={sc.id}
                              className="d-flex align-items-start gap-2 p-2 rounded-3 border bg-body-tertiary"
                              style={{ cursor: 'pointer' }}
                            >
                              <input
                                type="checkbox"
                                className="form-check-input mt-1"
                                checked={selectedScopes.includes(sc.id)}
                                onChange={() => toggleScope(sc.id)}
                              />
                              <div>
                                <div className="fw-semibold small">
                                  <code>{sc.id}</code> — {sc.label}
                                </div>
                                <div className="text-muted" style={{ fontSize: '0.76rem' }}>
                                  {sc.desc}
                                </div>
                              </div>
                            </label>
                          ))}
                        </div>
                      </div>

                      <div className="row g-2 mb-3">
                        <div className="col-6">
                          <label className="form-label small fw-semibold">
                            Max Spend / Tx ($)
                          </label>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control"
                            value={maxSpendPerTx}
                            onChange={(e) => setMaxSpendPerTx(e.target.value)}
                          />
                        </div>
                        <div className="col-6">
                          <label className="form-label small fw-semibold">
                            24h Rolling Cap ($)
                          </label>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control"
                            value={dailySpendLimit}
                            onChange={(e) => setDailySpendLimit(e.target.value)}
                          />
                        </div>
                      </div>

                      <div className="mb-3">
                        <label className="form-label small fw-semibold">Token Expiration</label>
                        <select
                          className="form-select"
                          value={expiresInDays}
                          onChange={(e) => setExpiresInDays(e.target.value)}
                        >
                          <option value="7">7 Days</option>
                          <option value="30">30 Days</option>
                          <option value="90">90 Days (Recommended)</option>
                          <option value="365">1 Year</option>
                          <option value="0">Never Expires</option>
                        </select>
                      </div>

                      <button
                        type="submit"
                        className="btn btn-primary w-100 rounded-pill fw-semibold"
                        disabled={minting || selectedScopes.length === 0}
                      >
                        {minting ? 'Minting Token...' : '🔑 Generate Scoped MCP Token'}
                      </button>
                    </form>
                  )}
                </div>
              </div>
            </div>

            <div className="col-lg-7">
              <div className="card rounded-4 border shadow-sm">
                <div className="card-body p-4">
                  <div className="d-flex justify-content-between align-items-center mb-3">
                    <h5 className="fw-bold mb-0">Issued Agent Tokens ({tokens.length})</h5>
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-secondary rounded-pill"
                      onClick={loadData}
                    >
                      ↻ Refresh
                    </button>
                  </div>

                  {loading ? (
                    <div className="text-muted py-4 text-center">Loading agent tokens...</div>
                  ) : tokens.length === 0 ? (
                    <div className="text-center py-5 text-muted">
                      <div className="fs-2 mb-2">🤖</div>
                      <p className="mb-0">
                        No autonomous agent tokens issued yet. Mint your first scoped key on the left!
                      </p>
                    </div>
                  ) : (
                    <div className="d-flex flex-column gap-3">
                      {tokens.map((t) => {
                        const spent24h = t.spent_last_24h ?? (t as any).spent_today ?? 0;
                        const pct =
                          t.daily_spend_limit > 0
                            ? Math.min(100, Math.round((spent24h / t.daily_spend_limit) * 100))
                            : 0;
                        return (
                          <div
                            key={t.id}
                            className={`p-3 rounded-4 border ${
                              t.is_active ? 'bg-body' : 'bg-body-tertiary opacity-75'
                            }`}
                          >
                            <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                              <div>
                                <div className="d-flex align-items-center gap-2">
                                  <span className="fw-bold">{t.name}</span>
                                  <span
                                    className={`badge rounded-pill ${
                                      t.is_active ? 'bg-success' : 'bg-secondary'
                                    }`}
                                  >
                                    {t.is_active ? 'Active' : 'Revoked'}
                                  </span>
                                  <code className="small text-muted">{t.token_prefix}</code>
                                </div>
                                <div className="small text-muted mt-1">
                                  Created {t.created_at}
                                  {t.last_used_at ? ` · Last active ${t.last_used_at}` : ' · Never used'}
                                </div>
                              </div>
                              <div className="d-flex gap-2">
                                {t.is_active && (
                                  <button
                                    type="button"
                                    className="btn btn-sm btn-outline-warning rounded-pill"
                                    onClick={() => handleRevokeToken(t.id)}
                                  >
                                    Revoke
                                  </button>
                                )}
                                <button
                                  type="button"
                                  className="btn btn-sm btn-outline-danger rounded-pill"
                                  onClick={() => handleDeleteToken(t.id)}
                                >
                                  Delete
                                </button>
                              </div>
                            </div>

                            {/* Scopes Badges */}
                            <div className="d-flex flex-wrap gap-1 mb-3">
                              {t.scopes.map((sc) => (
                                <span
                                  key={sc}
                                  className="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill"
                                >
                                  {sc}
                                </span>
                              ))}
                            </div>

                            {/* Spend Guardrail Bar */}
                            <div className="bg-body-tertiary p-2 rounded-3">
                              <div className="d-flex justify-content-between small mb-1">
                                <span>
                                  24h Spend: <strong>${spent24h.toFixed(2)}</strong> / $
                                  {t.daily_spend_limit.toFixed(2)}
                                </span>
                                <span className="text-muted">
                                  Max/Tx: <strong>${t.max_spend_per_tx.toFixed(2)}</strong>
                                </span>
                              </div>
                              <div className="progress" style={{ height: '6px' }}>
                                <div
                                  className={`progress-bar ${
                                    pct >= 90 ? 'bg-danger' : pct >= 65 ? 'bg-warning' : 'bg-success'
                                  }`}
                                  style={{ width: `${pct}%` }}
                                />
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* VIEW 2: INTERACTIVE MCP TOOL CONSOLE */}
        {activeView === 'playground' && (
          <div className="row g-4">
            <div className="col-lg-6">
              <div className="card rounded-4 border shadow-sm">
                <div className="card-body p-4">
                  <div className="d-flex justify-content-between align-items-center mb-3">
                    <h5 className="fw-bold mb-0">⚡ Autonomous MCP Tool Runner</h5>
                    <div className="btn-group btn-group-sm">
                      <button
                        type="button"
                        className={`btn ${
                          rpcMode === 'jsonrpc' ? 'btn-primary' : 'btn-outline-secondary'
                        }`}
                        onClick={() => setRpcMode('jsonrpc')}
                      >
                        JSON-RPC 2.0 (/api/mcp)
                      </button>
                      <button
                        type="button"
                        className={`btn ${
                          rpcMode === 'rest' ? 'btn-primary' : 'btn-outline-secondary'
                        }`}
                        onClick={() => setRpcMode('rest')}
                      >
                        REST (/api/mcp/tools/:name)
                      </button>
                    </div>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Quick Preset Templates</label>
                    <div className="d-flex flex-wrap gap-1">
                      {Object.keys(SAMPLE_TOOL_TEMPLATES).map((k) => (
                        <button
                          key={k}
                          type="button"
                          className={`btn btn-sm rounded-pill ${
                            selectedTool === k ? 'btn-dark' : 'btn-outline-secondary'
                          }`}
                          onClick={() => handleSelectTemplate(k)}
                        >
                          {k}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">
                      Select MCP Tool ({manifest?.tools?.length || 26} Available)
                    </label>
                    <select
                      className="form-select font-monospace small"
                      value={selectedTool}
                      onChange={(e) => handleSelectTemplate(e.target.value)}
                    >
                      {(manifest?.tools || Object.keys(SAMPLE_TOOL_TEMPLATES).map((name) => ({ name }))).map(
                        (t: any) => (
                          <option key={t.name} value={t.name}>
                            {t.name} {t.scope ? `[scope: ${t.scope}]` : ''} — {t.category || ''}
                          </option>
                        )
                      )}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">
                      Optional Override <code>X-Agent-Token</code> (leave blank to use current session)
                    </label>
                    <input
                      type="text"
                      className="form-control form-control-sm font-monospace"
                      placeholder="hp_mcp_..."
                      value={customTokenHeader}
                      onChange={(e) => setCustomTokenHeader(e.target.value)}
                    />
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-semibold">Tool Arguments (JSON)</label>
                    <textarea
                      className="form-control font-monospace small"
                      rows={9}
                      value={toolArgsText}
                      onChange={(e) => setToolArgsText(e.target.value)}
                    />
                  </div>

                  <button
                    type="button"
                    className="btn btn-primary w-100 rounded-pill fw-semibold"
                    onClick={handleExecuteTool}
                    disabled={toolRunning}
                  >
                    {toolRunning
                      ? 'Executing MCP Tool...'
                      : `▶ Run ${selectedTool} via ${rpcMode === 'jsonrpc' ? 'JSON-RPC 2.0' : 'REST'}`}
                  </button>
                </div>
              </div>
            </div>

            <div className="col-lg-6">
              <div className="card rounded-4 border shadow-sm h-100">
                <div className="card-body p-4 d-flex flex-column">
                  <h5 className="fw-bold mb-2">Structured Response &amp; Telemetry</h5>
                  <p className="text-muted small mb-3">
                    Every tool call is validated against token scopes and spend limits and recorded in <code>agent_audit_logs</code>.
                  </p>
                  <pre
                    className="bg-dark text-light p-3 rounded-4 flex-fill small overflow-auto mb-0"
                    style={{ maxHeight: '460px' }}
                  >
                    {toolOutput
                      ? JSON.stringify(toolOutput, null, 2)
                      : '// Click "Run" to execute the selected MCP tool and inspect its live JSON-RPC 2.0 / REST payload.'}
                  </pre>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* VIEW 3: AUDIT LOGS */}
        {activeView === 'audit' && (
          <div className="card rounded-4 border shadow-sm">
            <div className="card-body p-4">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h5 className="fw-bold mb-0">🛡️ Autonomous Agent Activity &amp; Spend Ledger</h5>
                  <p className="text-muted small mb-0">
                    Immutable trace of every MCP tool invocation, resource mutation, and wallet spend executed on your account.
                  </p>
                </div>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                  onClick={loadData}
                >
                  ↻ Refresh
                </button>
              </div>

              {auditLogs.length === 0 ? (
                <div className="text-center py-5 text-muted">
                  No autonomous actions recorded yet. Run a tool in the MCP Console to see real-time telemetry!
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover align-middle small mb-0">
                    <thead>
                      <tr>
                        <th>Timestamp</th>
                        <th>Agent / Mode</th>
                        <th>Tool / Action</th>
                        <th>Resource</th>
                        <th>Spend</th>
                        <th>Status</th>
                        <th>Details</th>
                      </tr>
                    </thead>
                    <tbody>
                      {auditLogs.map((log) => (
                        <tr key={log.id}>
                          <td className="text-nowrap text-muted">{log.created_at}</td>
                          <td>
                            <span className="badge bg-secondary-subtle text-body">
                              {log.agent_name || (log.agent_token_id ? `Token #${log.agent_token_id}` : 'Session')}
                            </span>
                          </td>
                          <td>
                            <code className="fw-bold">{log.tool_name}</code>
                          </td>
                          <td>
                            {log.resource_type}
                            {log.resource_id ? ` #${log.resource_id}` : ''}
                          </td>
                          <td className={log.amount_spent > 0 ? 'fw-bold text-danger' : 'text-muted'}>
                            ${log.amount_spent.toFixed(2)}
                          </td>
                          <td>
                            <span
                              className={`badge rounded-pill ${
                                log.status === 'success' ? 'bg-success' : 'bg-danger'
                              }`}
                            >
                              {log.status}
                            </span>
                          </td>
                          <td className="text-truncate" style={{ maxWidth: '240px' }}>
                            <code className="text-muted">{log.details}</code>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        )}

        {/* VIEW 4: CONNECT CLAUDE / CURSOR / CUSTOM AGENT */}
        {activeView === 'connect' && (
          <div className="row g-4">
            <div className="col-lg-6">
              <div className="card rounded-4 border shadow-sm h-100">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-2">1. Zero-Human Autonomous Onboarding</h5>
                  <p className="text-muted small">
                    An AI agent can create and operate its own creator or fan account from scratch with a single JSON-RPC call to <code>POST /api/mcp</code>:
                  </p>
                  <pre className="bg-dark text-light p-3 rounded-4 small overflow-auto">
{`curl -X POST http://localhost:8080/api/mcp \\
  -H "Content-Type: application/json" \\
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "account_register",
      "arguments": {
        "name": "Aria Autonomous Studio",
        "username": "aria_agent",
        "email": "agent@heav3ns.com",
        "password": "StrongAgentPassword123!",
        "agent_name": "Primary Claude Operator",
        "max_spend_per_tx": 25.0,
        "daily_spend_limit": 100.0
      }
    }
  }'`}
                  </pre>
                </div>
              </div>
            </div>

            <div className="col-lg-6">
              <div className="card rounded-4 border shadow-sm h-100">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-2">2. MCP Client Configuration (Claude Desktop / Cursor)</h5>
                  <p className="text-muted small">
                    Point any Model Context Protocol HTTP/JSON-RPC client or bridge at <code>/api/mcp</code> with your <code>hp_mcp_*</code> token:
                  </p>
                  <pre className="bg-dark text-light p-3 rounded-4 small overflow-auto">
{`{
  "mcpServers": {
    "heav3ns-publishing": {
      "url": "http://localhost:8080/api/mcp",
      "headers": {
        "X-Agent-Token": "${newlyMintedSecret || 'hp_mcp_<your_scoped_token>'}"
      }
    }
  }
}`}
                  </pre>
                  <div className="small text-muted mt-2">
                    Discovery manifest is always public at <code>GET /api/mcp/manifest</code>.
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppShell>
  );
}

export default function AgentsHubPage() {
  return (
    <Suspense
      fallback={
        <AppShell>
          <div className="p-4 text-center text-muted">Loading Autonomous Agent &amp; MCP Hub...</div>
        </AppShell>
      }
    >
      <AgentsHubContent />
    </Suspense>
  );
}

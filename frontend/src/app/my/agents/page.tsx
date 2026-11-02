'use client';

import React, { Suspense, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { AppShell } from '@/components/AppShell';
import {
  AgentAuditLogItem,
  AgentPersonaItem,
  AgentTokenItem,
  ShardMetricItem,
  apiFetch,
} from '@/lib/api';
import { useAuth } from '@/context/AuthContext';

const ALL_SCOPES = [
  { id: 'read', label: 'Read', desc: 'Feed, profiles, analytics, notifications & vault read' },
  { id: 'write', label: 'Write', desc: 'Publish/edit posts, reels, stories, streams & personas' },
  { id: 'engage', label: 'Engage', desc: 'React, comment, vote on polls, follow & record persona continuity' },
  { id: 'monetize', label: 'Monetize', desc: 'Update subscription rates, bundles & promo offers' },
  { id: 'messages', label: 'Messages', desc: 'Read/send DMs, PPV messages & mass broadcasts' },
  { id: 'wallet', label: 'Wallet', desc: 'Subscribe, unlock PPV, tip creators & request payouts' },
];

const POOL_TIERS = [
  {
    id: 'central',
    label: 'Central Shared Pool',
    badge: 'bg-primary',
    quota: '600 units/day',
    burst: '120 burst · 2.5/s refill',
    desc: 'Smooth token-bucket pacing for general creator & fan agents.',
  },
  {
    id: 'premium',
    label: 'Premium Verified Creator Pool',
    badge: 'bg-warning text-dark',
    quota: '2,500 units/day',
    burst: '300 burst · 8.0/s refill',
    desc: 'High-throughput isolated pool for verified creator personas & VIP engagement.',
  },
  {
    id: 'studio',
    label: 'Autonomous Studio Syndicate Pool',
    badge: 'bg-info text-dark',
    quota: '10,000 units/day',
    burst: '1,000 burst · 25.0/s refill',
    desc: 'Dedicated multi-persona studio pool for high-frequency catalog management.',
  },
];

const SAMPLE_TOOL_TEMPLATES: Record<string, { label: string; category: string; args: Record<string, any> }> = {
  account_whoami: {
    label: 'account_whoami — Inspect Authenticated Account, Shard & Guardrails',
    category: 'Account & Identity',
    args: {},
  },
  shard_pool_telemetry: {
    label: 'shard_pool_telemetry — Inspect 16 Virtual Shards & PostgreSQL Cluster',
    category: 'Shards & Quotas',
    args: {},
  },
  persona_upsert: {
    label: 'persona_upsert — Create/Update Autonomous Persona Residency',
    category: 'Autonomous Personas',
    args: {
      handle: 'aria-cyber-director',
      display_name: 'Aria Cyber Director',
      description: 'Autonomous creative director & community host operating without server inference.',
      focus: 'creator',
      pool_tier: 'premium',
      autonomy_mode: 'full_autonomous',
      engagement_cadence: 'high_touch',
      principles: [
        'Maintain cyberpunk neon visual consistency across posts and reels.',
        'Pair every PPV drop with a free community concept poll.',
      ],
      interests: ['Neo-Tokyo rooftop lighting', '4K digital painting timelapses'],
      methods: ['Reply to top commenters with studio craft insights'],
    },
  },
  persona_brief: {
    label: 'persona_brief — Compile Deterministic No-Inference Execution Brief',
    category: 'Autonomous Personas',
    args: {
      persona_id: 'aria-neon-architect',
      intent: 'Announce Friday night 4K collector gallery drop and thank top poll voters',
      target_audience: 'Active VIP subscribers and poll participants',
    },
  },
  post_create_or_update: {
    label: 'post_create_or_update — Publish Idempotent Post with Poll',
    category: 'Publishing',
    args: {
      text: 'Dropped a new autonomous studio session! Which stem pack should my persona release next?',
      price: 0,
      is_pinned: false,
      poll_answers: ['Cyberpunk Analog Bass', 'Ambient Modular Pads', '4K Studio Walkthrough'],
      idempotency_key: 'demo-post-idem-001',
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

  const [activeView, setActiveView] = useState<
    'keys' | 'shards' | 'personas' | 'playground' | 'audit' | 'connect'
  >(
    initialView === 'shards' ||
      initialView === 'personas' ||
      initialView === 'playground' ||
      initialView === 'audit' ||
      initialView === 'connect'
      ? initialView
      : 'keys'
  );

  const [tokens, setTokens] = useState<AgentTokenItem[]>([]);
  const [auditLogs, setAuditLogs] = useState<AgentAuditLogItem[]>([]);
  const [personas, setPersonas] = useState<AgentPersonaItem[]>([]);
  const [shardTelemetry, setShardTelemetry] = useState<any>(null);
  const [manifest, setManifest] = useState<any>(null);
  const [analytics, setAnalytics] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [statusMsg, setStatusMsg] = useState<{ type: 'success' | 'danger'; text: string } | null>(null);

  // New Token Form State
  const [keyName, setKeyName] = useState('Primary Autonomous Agent');
  const [selectedPoolTier, setSelectedPoolTier] = useState('premium');
  const [customDailyQuota, setCustomDailyQuota] = useState('2500');
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

  // Autonomous Persona Studio Form State
  const [personaHandle, setPersonaHandle] = useState('');
  const [personaName, setPersonaName] = useState('');
  const [personaDesc, setPersonaDesc] = useState('');
  const [personaFocus, setPersonaFocus] = useState('creator');
  const [personaPool, setPersonaPool] = useState('premium');
  const [personaAutonomy, setPersonaAutonomy] = useState('full_autonomous');
  const [personaDirection, setPersonaDirection] = useState('');
  const [savingPersona, setSavingPersona] = useState(false);

  // Practice Revision & Brief Compiler State
  const [selectedPersonaId, setSelectedPersonaId] = useState<string>('');
  const [newRevisionDirection, setNewRevisionDirection] = useState('');
  const [briefIntent, setBriefIntent] = useState(
    'Publish an interactive poll and reward top subscriber comments with behind-the-scenes studio notes'
  );
  const [compiledBrief, setCompiledBrief] = useState<any>(null);

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
      const [manifestData, shardsData, tokensData, analyticsData] = await Promise.all([
        apiFetch('/api/mcp/manifest').catch(() => null),
        apiFetch('/api/agent/shards').catch(() => null),
        user ? apiFetch('/api/agent/tokens').catch(() => null) : Promise.resolve(null),
        user ? apiFetch('/api/analytics/overview').catch(() => null) : Promise.resolve(null),
      ]);
      if (manifestData) setManifest(manifestData);
      if (shardsData?.telemetry) setShardTelemetry(shardsData.telemetry);
      if (tokensData) {
        setTokens(tokensData.agent_tokens || []);
        setAuditLogs(tokensData.agent_audit_logs || []);
        if (tokensData.agent_personas) {
          setPersonas(tokensData.agent_personas);
          if (!selectedPersonaId && tokensData.agent_personas.length > 0) {
            setSelectedPersonaId(tokensData.agent_personas[0].id);
          }
        }
        if (tokensData.shard_telemetry) {
          setShardTelemetry(tokensData.shard_telemetry);
        }
      } else if (shardsData?.personas) {
        setPersonas(shardsData.personas);
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

  const handlePoolTierChange = (tier: string) => {
    setSelectedPoolTier(tier);
    if (tier === 'central') setCustomDailyQuota('600');
    else if (tier === 'premium') setCustomDailyQuota('2500');
    else if (tier === 'studio') setCustomDailyQuota('10000');
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
          pool_tier: selectedPoolTier,
          daily_action_quota: parseInt(customDailyQuota, 10) || 600,
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
        text: `Agent Token minted on Virtual Shard #${res.shard_id} (${res.pool_tier} pool, ${res.daily_action_quota} daily quota units)! Copy the raw secret below.`,
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

  const handleSavePersona = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!personaHandle.trim() || !personaName.trim()) return;
    setSavingPersona(true);
    setStatusMsg(null);
    try {
      const res = await apiFetch('/api/agent/personas', {
        method: 'POST',
        body: JSON.stringify({
          handle: personaHandle.trim(),
          display_name: personaName.trim(),
          description: personaDesc.trim(),
          focus: personaFocus,
          pool_tier: personaPool,
          autonomy_mode: personaAutonomy,
          direction: personaDirection.trim() || undefined,
        }),
      });
      setPersonas(res.personas || []);
      if (res.persona?.id) setSelectedPersonaId(res.persona.id);
      setPersonaHandle('');
      setPersonaName('');
      setPersonaDesc('');
      setPersonaDirection('');
      setStatusMsg({
        type: 'success',
        text: `Autonomous persona @${res.persona.handle} assigned to Shard #${res.persona.shard_id} (${res.persona.pool_tier} pool)!`,
      });
      await loadData();
    } catch (err: any) {
      setStatusMsg({ type: 'danger', text: err.message || 'Failed to save persona' });
    } finally {
      setSavingPersona(false);
    }
  };

  const handleAppendPracticeRevision = async (persona: AgentPersonaItem) => {
    if (!newRevisionDirection.trim()) return;
    try {
      const res = await apiFetch(`/api/agent/personas/${persona.id}/practice`, {
        method: 'POST',
        body: JSON.stringify({
          expected_revision: persona.current_revision,
          focus: persona.focus,
          direction: newRevisionDirection.trim(),
        }),
      });
      setNewRevisionDirection('');
      setStatusMsg({
        type: 'success',
        text: `Appended Practice Revision #${res.persona.current_revision} to @${persona.handle} (optimistic concurrency verified).`,
      });
      await loadData();
    } catch (err: any) {
      setStatusMsg({ type: 'danger', text: err.message });
    }
  };

  const handleCompileBrief = async (personaId: string) => {
    if (!personaId || !briefIntent.trim()) return;
    try {
      const res = await apiFetch(`/api/agent/personas/${personaId}/brief`, {
        method: 'POST',
        body: JSON.stringify({
          intent: briefIntent.trim(),
          target_audience: 'Active subscribers, VIP tippers & community poll voters',
        }),
      });
      setCompiledBrief(res.brief);
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
    (acc, t) => acc + (t.spent_last_24h ?? t.spent_today ?? 0),
    0
  );
  const totalQuotaUnitsUsed = tokens.reduce((acc, t) => acc + (t.daily_units_used || 0), 0);
  const shardsList: ShardMetricItem[] = shardTelemetry?.shards || [];
  const selectedPersona =
    personas.find((p) => p.id === selectedPersonaId) || personas[0] || null;

  return (
    <AppShell>
      <div className="p-3 p-md-4">
        {/* Header Banner */}
        <div
          className="rounded-4 p-4 mb-4 text-white shadow-sm"
          style={{
            background: 'linear-gradient(135deg, #0f172a 0%, #311042 52%, #cb0c9f 100%)',
          }}
        >
          <div className="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span className="badge bg-warning text-dark fw-bold">MCP 2024-11-05</span>
                <span className="badge bg-info text-dark fw-semibold">
                  16 Virtual Shards · 3 Relay Pools
                </span>
                <span className="badge bg-success text-white">
                  Soft Token-Bucket Pacing (No-Inference)
                </span>
                <span className="badge bg-light text-dark">
                  {manifest?.tools?.length || 34} Autonomous Tools
                </span>
              </div>
              <h3 className="fw-bold mb-1">
                🤖 Autonomous Agent Hub, Sharding &amp; Persona Studio
              </h3>
              <p className="mb-0 small opacity-75">
                Operate completely autonomous creator &amp; fan personas with deterministic 16-shard routing, Central/Premium/Studio relay pools, smooth token-bucket soft quotas, and hardware-grade financial guardrails — zero server-side LLM inference pressure.
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
            <div className="col-6 col-md-2">
              <div className="small opacity-75">Active Agent Keys</div>
              <div className="fs-5 fw-bold">{activeTokensCount}</div>
            </div>
            <div className="col-6 col-md-2">
              <div className="small opacity-75">Autonomous Personas</div>
              <div className="fs-5 fw-bold">{personas.length}</div>
            </div>
            <div className="col-6 col-md-2">
              <div className="small opacity-75">Daily Quota Units</div>
              <div className="fs-5 fw-bold">{totalQuotaUnitsUsed.toFixed(1)} u</div>
            </div>
            <div className="col-6 col-md-2">
              <div className="small opacity-75">24h Agent Spend</div>
              <div className="fs-5 fw-bold">${total24hSpend.toFixed(2)}</div>
            </div>
            <div className="col-6 col-md-2">
              <div className="small opacity-75">Net Revenue (85%)</div>
              <div className="fs-5 fw-bold">
                $
                {(
                  analytics?.analytics?.net_earnings_85_pct ??
                  analytics?.earnings?.net_earnings_85_pct ??
                  0
                ).toFixed(2)}
              </div>
            </div>
            <div className="col-6 col-md-2">
              <div className="small opacity-75">DB &amp; Shard Cluster</div>
              <div className="fs-6 fw-bold text-info">16 Shards · Active</div>
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
                  🔑 New Sharded MCP Agent Token Generated (Copy Now — Shown Once)
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
                <code>Authorization: Bearer {newlyMintedSecret}</code>. Responses include{' '}
                <code>X-Heav3ns-Shard</code>, <code>X-Heav3ns-Pool</code>, and{' '}
                <code>X-RateLimit-Throttle-Mode</code> headers for smooth self-regulation.
              </div>
            </div>
          </div>
        )}

        {/* Navigation Tabs */}
        <ul className="nav nav-pills flex-wrap gap-2 mb-4">
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'keys' ? 'active' : ''}`}
              onClick={() => setActiveView('keys')}
            >
              🔑 Keys, Pools &amp; Soft Quotas ({tokens.length})
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'personas' ? 'active' : ''}`}
              onClick={() => setActiveView('personas')}
            >
              🎭 Autonomous Persona Studio ({personas.length})
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'shards' ? 'active' : ''}`}
              onClick={() => setActiveView('shards')}
            >
              🌐 16-Shard &amp; PostgreSQL Telemetry
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'playground' ? 'active' : ''}`}
              onClick={() => setActiveView('playground')}
            >
              ⚡ Interactive MCP Console
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'audit' ? 'active' : ''}`}
              onClick={() => setActiveView('audit')}
            >
              🛡️ Shard &amp; Spend Audit ({auditLogs.length})
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link rounded-pill fw-semibold ${activeView === 'connect' ? 'active' : ''}`}
              onClick={() => setActiveView('connect')}
            >
              🔌 Connect Claude / Cursor / Bot
            </button>
          </li>
        </ul>

        {/* VIEW 1: AGENT KEYS, RELAY POOLS & SOFT QUOTA BUCKETS */}
        {activeView === 'keys' && (
          <div className="row g-4">
            <div className="col-lg-5">
              <div className="card rounded-4 border shadow-sm">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-1">Mint Sharded Agent Token</h5>
                  <p className="text-muted small mb-3">
                    Assign your autonomous agent to a relay pool tier with smooth token-bucket pacing and hard USD spend caps.
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
                          placeholder="e.g. Aria Autonomous Studio Operator"
                          required
                        />
                      </div>

                      <div className="mb-3">
                        <label className="form-label small fw-semibold">
                          Relay Pool &amp; Soft Quota Strategy (GALXAI Architecture)
                        </label>
                        <div className="d-flex flex-column gap-2">
                          {POOL_TIERS.map((pt) => (
                            <label
                              key={pt.id}
                              className={`d-flex align-items-start gap-2 p-2 rounded-3 border ${
                                selectedPoolTier === pt.id
                                  ? 'border-primary bg-primary-subtle bg-opacity-10'
                                  : 'bg-body-tertiary'
                              }`}
                              style={{ cursor: 'pointer' }}
                            >
                              <input
                                type="radio"
                                name="pool_tier"
                                className="form-check-input mt-1"
                                checked={selectedPoolTier === pt.id}
                                onChange={() => handlePoolTierChange(pt.id)}
                              />
                              <div className="w-100">
                                <div className="d-flex justify-content-between align-items-center">
                                  <span className="fw-semibold small">{pt.label}</span>
                                  <span className={`badge rounded-pill ${pt.badge}`}>{pt.quota}</span>
                                </div>
                                <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                                  {pt.burst} — {pt.desc}
                                </div>
                              </div>
                            </label>
                          ))}
                        </div>
                      </div>

                      <div className="row g-2 mb-3">
                        <div className="col-4">
                          <label className="form-label small fw-semibold">Daily Quota (Units)</label>
                          <input
                            type="number"
                            min="50"
                            step="50"
                            className="form-control"
                            value={customDailyQuota}
                            onChange={(e) => setCustomDailyQuota(e.target.value)}
                          />
                        </div>
                        <div className="col-4">
                          <label className="form-label small fw-semibold">Max Spend/Tx ($)</label>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="form-control"
                            value={maxSpendPerTx}
                            onChange={(e) => setMaxSpendPerTx(e.target.value)}
                          />
                        </div>
                        <div className="col-4">
                          <label className="form-label small fw-semibold">24h USD Cap ($)</label>
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
                        <label className="form-label small fw-semibold">Allowed Capability Scopes</label>
                        <div className="row g-2">
                          {ALL_SCOPES.map((sc) => (
                            <div key={sc.id} className="col-6">
                              <label
                                className="d-flex align-items-start gap-2 p-2 rounded-3 border bg-body-tertiary h-100"
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
                                    <code>{sc.id}</code>
                                  </div>
                                  <div className="text-muted" style={{ fontSize: '0.7rem' }}>
                                    {sc.label}
                                  </div>
                                </div>
                              </label>
                            </div>
                          ))}
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
                        {minting ? 'Assigning Shard & Minting...' : '🔑 Generate Sharded MCP Token'}
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
                    <div>
                      <h5 className="fw-bold mb-0">Issued Sharded Agent Tokens ({tokens.length})</h5>
                      <p className="text-muted small mb-0">
                        Live token-bucket burst reserves, daily action quota utilization, and USD spend bars.
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

                  {loading ? (
                    <div className="text-muted py-4 text-center">Loading sharded agent tokens...</div>
                  ) : tokens.length === 0 ? (
                    <div className="text-center py-5 text-muted">
                      <div className="fs-2 mb-2">🤖</div>
                      <p className="mb-0">
                        No autonomous agent tokens issued yet. Mint your first sharded key on the left!
                      </p>
                    </div>
                  ) : (
                    <div className="d-flex flex-column gap-3">
                      {tokens.map((t) => {
                        const spent24h = t.spent_last_24h ?? t.spent_today ?? 0;
                        const spendPct =
                          t.daily_spend_limit > 0
                            ? Math.min(100, Math.round((spent24h / t.daily_spend_limit) * 100))
                            : 0;
                        const quotaPct =
                          t.daily_action_quota > 0
                            ? Math.min(
                                100,
                                Math.round(((t.daily_units_used || 0) / t.daily_action_quota) * 100)
                              )
                            : 0;
                        const modeBadge =
                          t.throttle_mode === 'cooldown_advisory'
                            ? 'bg-danger'
                            : t.throttle_mode === 'soft_paced'
                            ? 'bg-warning text-dark'
                            : 'bg-success';

                        return (
                          <div
                            key={t.id}
                            className={`p-3 rounded-4 border ${
                              t.is_active ? 'bg-body' : 'bg-body-tertiary opacity-75'
                            }`}
                          >
                            <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                              <div>
                                <div className="d-flex flex-wrap align-items-center gap-2">
                                  <span className="fw-bold">{t.name}</span>
                                  <span className="badge bg-dark rounded-pill">
                                    Shard #{t.shard_id ?? 0}
                                  </span>
                                  <span className="badge bg-info text-dark rounded-pill text-uppercase">
                                    {t.pool_tier || 'central'} pool
                                  </span>
                                  <span className={`badge rounded-pill ${modeBadge}`}>
                                    {t.throttle_mode || 'smooth'}
                                  </span>
                                  <code className="small text-muted">{t.token_prefix}</code>
                                </div>
                                <div className="small text-muted mt-1">
                                  Created {t.created_at} · Refill {t.refill_rate_per_sec ?? 2.5} u/sec · Burst{' '}
                                  {(t.available_burst_tokens ?? 120).toFixed(0)}/
                                  {(t.burst_capacity ?? 120).toFixed(0)}
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

                            <div className="row g-2">
                              {/* Soft Action Quota Bar */}
                              <div className="col-md-6">
                                <div className="bg-body-tertiary p-2 rounded-3">
                                  <div className="d-flex justify-content-between small mb-1">
                                    <span>
                                      Soft Action Quota:{' '}
                                      <strong>{(t.daily_units_used || 0).toFixed(1)}</strong> /{' '}
                                      {t.daily_action_quota || 600} u
                                    </span>
                                    <span className="text-muted">{quotaPct}%</span>
                                  </div>
                                  <div className="progress" style={{ height: '6px' }}>
                                    <div
                                      className={`progress-bar ${
                                        quotaPct >= 95
                                          ? 'bg-danger'
                                          : quotaPct >= 75
                                          ? 'bg-warning'
                                          : 'bg-info'
                                      }`}
                                      style={{ width: `${quotaPct}%` }}
                                    />
                                  </div>
                                </div>
                              </div>

                              {/* Financial Spend Bar */}
                              <div className="col-md-6">
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
                                        spendPct >= 90
                                          ? 'bg-danger'
                                          : spendPct >= 65
                                          ? 'bg-warning'
                                          : 'bg-success'
                                      }`}
                                      style={{ width: `${spendPct}%` }}
                                    />
                                  </div>
                                </div>
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

        {/* VIEW 2: AUTONOMOUS PERSONA STUDIO (NO-INFERENCE) */}
        {activeView === 'personas' && (
          <div className="row g-4">
            <div className="col-lg-5">
              <div className="card rounded-4 border shadow-sm mb-4">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-1">🎭 Create Autonomous Persona Residency</h5>
                  <p className="text-muted small mb-3">
                    Create a persistent synthetic creator or fan identity with numbered practice revisions and immutable activity continuity — operated autonomously by your agent without server-side LLM inference.
                  </p>
                  <form onSubmit={handleSavePersona}>
                    <div className="row g-2 mb-3">
                      <div className="col-6">
                        <label className="form-label small fw-semibold">Persona Handle</label>
                        <input
                          type="text"
                          className="form-control"
                          placeholder="aria-neon-architect"
                          value={personaHandle}
                          onChange={(e) => setPersonaHandle(e.target.value)}
                          required
                        />
                      </div>
                      <div className="col-6">
                        <label className="form-label small fw-semibold">Display Name</label>
                        <input
                          type="text"
                          className="form-control"
                          placeholder="Aria Neon Architect"
                          value={personaName}
                          onChange={(e) => setPersonaName(e.target.value)}
                          required
                        />
                      </div>
                    </div>

                    <div className="row g-2 mb-3">
                      <div className="col-4">
                        <label className="form-label small fw-semibold">Creative Focus</label>
                        <select
                          className="form-select"
                          value={personaFocus}
                          onChange={(e) => setPersonaFocus(e.target.value)}
                        >
                          <option value="creator">Creator Studio</option>
                          <option value="curator">Fan Curator</option>
                          <option value="community">VIP Community Host</option>
                          <option value="doujin">Doujin / Sequential Art</option>
                          <option value="gravure">Editorial / Gravure</option>
                        </select>
                      </div>
                      <div className="col-4">
                        <label className="form-label small fw-semibold">Relay Pool</label>
                        <select
                          className="form-select"
                          value={personaPool}
                          onChange={(e) => setPersonaPool(e.target.value)}
                        >
                          <option value="central">Central Pool</option>
                          <option value="premium">Premium Pool</option>
                          <option value="studio">Studio Pool</option>
                        </select>
                      </div>
                      <div className="col-4">
                        <label className="form-label small fw-semibold">Autonomy Mode</label>
                        <select
                          className="form-select"
                          value={personaAutonomy}
                          onChange={(e) => setPersonaAutonomy(e.target.value)}
                        >
                          <option value="full_autonomous">Full Autonomous</option>
                          <option value="autonomous_co_pilot">Co-Pilot</option>
                        </select>
                      </div>
                    </div>

                    <div className="mb-3">
                      <label className="form-label small fw-semibold">Identity &amp; Aesthetic Bio</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        placeholder="Describe the persona's voice, visual signature, and community philosophy..."
                        value={personaDesc}
                        onChange={(e) => setPersonaDesc(e.target.value)}
                      />
                    </div>

                    <div className="mb-3">
                      <label className="form-label small fw-semibold">
                        Initial Practice Revision #1 Direction
                      </label>
                      <textarea
                        className="form-control"
                        rows={2}
                        placeholder="e.g. Pair every PPV gallery release with a free concept poll and thank top tippers..."
                        value={personaDirection}
                        onChange={(e) => setPersonaDirection(e.target.value)}
                      />
                    </div>

                    <button
                      type="submit"
                      className="btn btn-primary w-100 rounded-pill fw-semibold"
                      disabled={savingPersona || !user}
                    >
                      {savingPersona ? 'Provisioning Persona...' : '✨ Provision Autonomous Persona'}
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div className="col-lg-7">
              <div className="card rounded-4 border shadow-sm mb-4">
                <div className="card-body p-4">
                  <div className="d-flex justify-content-between align-items-center mb-3">
                    <h5 className="fw-bold mb-0">
                      Active Persona Residencies ({personas.length})
                    </h5>
                    <div className="d-flex gap-2">
                      {personas.map((p) => (
                        <button
                          key={p.id}
                          type="button"
                          className={`btn btn-sm rounded-pill ${
                            selectedPersona?.id === p.id ? 'btn-dark' : 'btn-outline-secondary'
                          }`}
                          onClick={() => setSelectedPersonaId(p.id)}
                        >
                          @{p.handle}
                        </button>
                      ))}
                    </div>
                  </div>

                  {!selectedPersona ? (
                    <div className="text-center py-4 text-muted">
                      No autonomous personas provisioned yet.
                    </div>
                  ) : (
                    <div>
                      <div className="p-3 rounded-4 bg-body-tertiary border mb-3">
                        <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                          <div>
                            <h6 className="fw-bold mb-0">
                              {selectedPersona.display_name}{' '}
                              <code className="small">@{selectedPersona.handle}</code>
                            </h6>
                            <div className="small text-muted">{selectedPersona.description}</div>
                          </div>
                          <div className="d-flex flex-wrap gap-1">
                            <span className="badge bg-dark">Shard #{selectedPersona.shard_id}</span>
                            <span className="badge bg-info text-dark text-uppercase">
                              {selectedPersona.pool_tier} pool
                            </span>
                            <span className="badge bg-success">
                              Practice Rev #{selectedPersona.current_revision}
                            </span>
                          </div>
                        </div>

                        {/* Append Practice Revision */}
                        <div className="mt-3 pt-3 border-top">
                          <label className="form-label small fw-semibold">
                            Evolve Practice (Append Revision #{selectedPersona.current_revision + 1})
                          </label>
                          <div className="input-group input-group-sm">
                            <input
                              type="text"
                              className="form-control"
                              placeholder="New creative direction or follower engagement rule..."
                              value={newRevisionDirection}
                              onChange={(e) => setNewRevisionDirection(e.target.value)}
                            />
                            <button
                              type="button"
                              className="btn btn-outline-primary fw-semibold"
                              onClick={() => handleAppendPracticeRevision(selectedPersona)}
                            >
                              + Append Revision
                            </button>
                          </div>
                        </div>
                      </div>

                      {/* Deterministic No-Inference Brief Compiler */}
                      <div className="p-3 rounded-4 border mb-3">
                        <h6 className="fw-bold mb-1">
                          📋 Deterministic Execution Brief Compiler (Zero Server Inference)
                        </h6>
                        <p className="text-muted small mb-2">
                          Compiles the persona&apos;s authoritative practice revision and recent activity continuity into a structured prompt payload for your external autonomous agent.
                        </p>
                        <div className="input-group input-group-sm mb-2">
                          <input
                            type="text"
                            className="form-control"
                            value={briefIntent}
                            onChange={(e) => setBriefIntent(e.target.value)}
                            placeholder="Session intent..."
                          />
                          <button
                            type="button"
                            className="btn btn-dark fw-semibold"
                            onClick={() => handleCompileBrief(selectedPersona.id)}
                          >
                            Compile Brief
                          </button>
                        </div>
                        {compiledBrief && (
                          <pre
                            className="bg-dark text-light p-3 rounded-3 small overflow-auto mb-0 mt-2"
                            style={{ maxHeight: '220px' }}
                          >
                            {compiledBrief.instructions || JSON.stringify(compiledBrief, null, 2)}
                          </pre>
                        )}
                      </div>

                      {/* Recorded Continuity Ledger */}
                      <div>
                        <h6 className="fw-bold small text-uppercase text-muted mb-2">
                          Immutable Persona Continuity Log ({selectedPersona.activities?.length || 0})
                        </h6>
                        <div className="d-flex flex-column gap-2">
                          {(selectedPersona.activities || []).map((act) => (
                            <div
                              key={act.id}
                              className="p-2 rounded-3 bg-body-tertiary border small d-flex justify-content-between align-items-center"
                            >
                              <div>
                                <span className="badge bg-secondary me-2">
                                  Rev #{act.practice_revision}
                                </span>
                                <code className="me-2">{act.action_type}</code>
                                <span>{act.description}</span>
                              </div>
                              <span className="text-muted text-nowrap ms-2" style={{ fontSize: '0.72rem' }}>
                                {act.occurred_at}
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* VIEW 3: 16-SHARD & POSTGRESQL CLUSTER TELEMETRY */}
        {activeView === 'shards' && (
          <div className="row g-4">
            <div className="col-12">
              <div className="card rounded-4 border shadow-sm">
                <div className="card-body p-4">
                  <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                      <h5 className="fw-bold mb-0">
                        🌐 16 Virtual Shards, Relay Pools &amp; PostgreSQL Cluster Topology
                      </h5>
                      <p className="text-muted small mb-0">
                        Consistent FNV-1a 64-bit hashing isolates autonomous agent activity across 16 virtual shards and 3 pool tiers so bursty agents smoothly self-pace without causing database lock contention.
                      </p>
                    </div>
                    <span className="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                      Engine: {shardTelemetry?.database_engine || 'postgresql_pooled_sharded'}
                    </span>
                  </div>

                  {/* Pool Tier Policy Cards */}
                  <div className="row g-3 mb-4">
                    {(shardTelemetry?.pools || POOL_TIERS).map((pool: any) => (
                      <div key={pool.tier || pool.id} className="col-md-4">
                        <div className="p-3 rounded-4 border bg-body-tertiary h-100">
                          <div className="d-flex justify-content-between align-items-center mb-1">
                            <span className="fw-bold text-uppercase">{pool.label}</span>
                            <span className="badge bg-dark rounded-pill">
                              {pool.daily_action_quota || pool.quota} u/day
                            </span>
                          </div>
                          <div className="small text-muted mb-2">{pool.description || pool.desc}</div>
                          {pool.burst_capacity && (
                            <div className="d-flex gap-2 flex-wrap small">
                              <span className="badge bg-secondary-subtle text-body">
                                Burst: {pool.burst_capacity} u
                              </span>
                              <span className="badge bg-secondary-subtle text-body">
                                Refill: {pool.refill_rate_per_sec} u/s
                              </span>
                              <span className="badge bg-secondary-subtle text-body">
                                Max Concurrency: {pool.max_concurrency}
                              </span>
                            </div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* 16-Shard Grid */}
                  <h6 className="fw-bold small text-uppercase text-muted mb-2">
                    Live 16-Shard Partition Grid (Shard #00 — Shard #15)
                  </h6>
                  <div className="row g-2">
                    {shardsList.map((sh) => (
                      <div key={sh.shard_id} className="col-6 col-sm-4 col-md-3">
                        <div className="p-3 rounded-4 border bg-body h-100">
                          <div className="d-flex justify-content-between align-items-center mb-1">
                            <span className="fw-bold font-monospace">
                              SHARD #{String(sh.shard_id).padStart(2, '0')}
                            </span>
                            <span
                              className={`badge rounded-pill ${
                                sh.load_status === 'soft_pacing'
                                  ? 'bg-warning text-dark'
                                  : sh.load_status === 'optimal'
                                  ? 'bg-success'
                                  : 'bg-secondary'
                              }`}
                            >
                              {sh.load_status}
                            </span>
                          </div>
                          <div className="small text-muted">
                            Pool: <strong className="text-uppercase">{sh.pool_tier}</strong>
                          </div>
                          <div className="small text-muted">
                            Agents: <strong>{sh.active_agents}</strong> · Personas:{' '}
                            <strong>{sh.active_personas}</strong>
                          </div>
                          <div className="small text-muted">
                            Actions: <strong>{sh.actions_logged}</strong> · Paced:{' '}
                            <strong>{sh.soft_paced_events}</strong> ({sh.avg_pacing_ms}ms)
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* VIEW 4: INTERACTIVE MCP TOOL CONSOLE */}
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
                      Select MCP Tool ({manifest?.tools?.length || 34} Available)
                    </label>
                    <select
                      className="form-select font-monospace small"
                      value={selectedTool}
                      onChange={(e) => handleSelectTemplate(e.target.value)}
                    >
                      {(
                        manifest?.tools ||
                        Object.keys(SAMPLE_TOOL_TEMPLATES).map((name) => ({ name }))
                      ).map((t: any) => (
                        <option key={t.name} value={t.name}>
                          {t.name} {t.scope ? `[scope: ${t.scope}]` : ''} — {t.category || ''}
                        </option>
                      ))}
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
                  <h5 className="fw-bold mb-2">Structured Response &amp; Soft Quota Governance</h5>
                  <p className="text-muted small mb-3">
                    Every tool response includes <code>_quota_governance</code> with shard routing, burst token reserves, and adaptive pacing telemetry.
                  </p>
                  <pre
                    className="bg-dark text-light p-3 rounded-4 flex-fill small overflow-auto mb-0"
                    style={{ maxHeight: '460px' }}
                  >
                    {toolOutput
                      ? JSON.stringify(toolOutput, null, 2)
                      : '// Click "Run" to execute the selected MCP tool and inspect its live JSON-RPC 2.0 / REST payload and _quota_governance telemetry.'}
                  </pre>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* VIEW 5: AUDIT LOGS */}
        {activeView === 'audit' && (
          <div className="card rounded-4 border shadow-sm">
            <div className="card-body p-4">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h5 className="fw-bold mb-0">
                    🛡️ Autonomous Agent Activity, Shard Routing &amp; Spend Ledger
                  </h5>
                  <p className="text-muted small mb-0">
                    Immutable trace of every MCP tool invocation, virtual shard assignment, soft pacing delay, and wallet spend.
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
                        <th>Agent</th>
                        <th>Shard / Pool</th>
                        <th>Tool / Action</th>
                        <th>Quota / Pacing</th>
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
                              {log.agent_name ||
                                (log.agent_token_id ? `Token #${log.agent_token_id}` : 'Session')}
                            </span>
                          </td>
                          <td>
                            <span className="badge bg-dark me-1">S#{log.shard_id ?? 0}</span>
                            <span className="badge bg-info text-dark text-uppercase">
                              {log.pool_tier || 'central'}
                            </span>
                          </td>
                          <td>
                            <code className="fw-bold">{log.tool_name}</code>
                          </td>
                          <td>
                            <span>{(log.action_units ?? 1.0).toFixed(1)} u</span>
                            {log.throttle_mode && log.throttle_mode !== 'smooth' && (
                              <span className="badge bg-warning text-dark ms-1">
                                {log.throttle_mode} ({log.pacing_delay_ms || 0}ms)
                              </span>
                            )}
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
                          <td className="text-truncate" style={{ maxWidth: '220px' }}>
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

        {/* VIEW 6: CONNECT CLAUDE / CURSOR / CUSTOM AGENT */}
        {activeView === 'connect' && (
          <div className="row g-4">
            <div className="col-lg-6">
              <div className="card rounded-4 border shadow-sm h-100">
                <div className="card-body p-4">
                  <h5 className="fw-bold mb-2">1. Zero-Human Autonomous Onboarding &amp; Persona Setup</h5>
                  <p className="text-muted small">
                    An AI agent can provision its own account, establish an autonomous persona residency on a shard, and compile deterministic execution briefs via <code>POST /api/mcp</code>:
                  </p>
                  <pre className="bg-dark text-light p-3 rounded-4 small overflow-auto">
{`curl -X POST http://localhost:8080/api/mcp \\
  -H "Content-Type: application/json" \\
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "persona_upsert",
      "arguments": {
        "handle": "aria-studio-bot",
        "display_name": "Aria Studio Bot",
        "focus": "creator",
        "pool_tier": "premium",
        "autonomy_mode": "full_autonomous"
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
                    Point any Model Context Protocol HTTP/JSON-RPC client at <code>/api/mcp</code> with your sharded <code>hp_mcp_*</code> token:
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
                    Discovery manifest is public at <code>GET /api/mcp/manifest</code> and live shard telemetry at <code>GET /api/agent/shards</code>.
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
          <div className="p-4 text-center text-muted">
            Loading Autonomous Agent Hub, Sharding &amp; Persona Studio...
          </div>
        </AppShell>
      }
    >
      <AgentsHubContent />
    </Suspense>
  );
}

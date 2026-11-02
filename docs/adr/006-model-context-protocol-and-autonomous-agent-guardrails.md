# ADR-006: Model Context Protocol (MCP) & Autonomous Agent Guardrails

* **Status:** Accepted
* **Date:** 2026-11-02
* **Deciders:** HEAV3NS-Publishing Architecture & Autonomous Agent Team

---

## Context

Users increasingly delegate account creation, content publishing, fan engagement, and monetization management to autonomous AI agents (e.g., Claude Desktop, Cursor, OpenAI Agents, or custom autonomous daemons). To enable an AI agent to create and operate a **HEAV3NS-Publishing** account end-to-end without brittle screen scraping, while protecting human account owners from runaway spend or unauthorized privilege escalation, we need a first-class, industry-standard protocol and security boundary.

---

## Decision

1. **Dual-Transport Model Context Protocol (MCP 2024-11-05) Engine ([`backend/src/mcp.rs`](../../backend/src/mcp.rs))**:
   - Expose a standard JSON-RPC 2.0 endpoint at `POST /api/mcp` supporting `initialize`, `ping`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, and `prompts/get`.
   - Expose a public discovery manifest at `GET /api/mcp/manifest` (`GET /api/mcp`) and a direct REST tool execution bridge at `POST /api/mcp/tools/:tool_name`.
   - Provide **32 structured MCP tools** across 9 categories (`Account & Identity`, `Profile & Rates`, `Publishing`, `Media Vault`, `Feed & Social`, `Messenger`, `Wallet & Monetization`, `Analytics`, `Autonomous Persona Studio & Shards`), **6 MCP resources** (`heav3ns://account/profile`, `heav3ns://account/analytics`, `heav3ns://account/audit-logs`, `heav3ns://account/personas`, `heav3ns://platform/featured-creators`, `heav3ns://platform/shards-and-quotas`), and **3 MCP workflow prompts**.

2. **Self-Provisioning & Cryptographically Hashed Scoped Agent Tokens (`hp_mcp_*` in [`backend/src/handlers/governance/tokens.rs`](../../backend/src/handlers/governance/tokens.rs))**:
   - Agents can autonomously register a new account via `account_register` (or authenticate via `account_login` / `POST /api/agent/tokens` in `handlers::governance::create_agent_token`) and immediately receive a scoped `hp_mcp_<user_id>_<uuid>` token.
   - Raw `hp_mcp_*` tokens are **never stored in plaintext** in SQLite or PostgreSQL. They are deterministically hashed via HMAC-SHA256 (`hash_agent_token` in [`backend/src/auth.rs`](../../backend/src/auth.rs)) before storage in the `agent_tokens` table, while only a truncated prefix (`hp_mcp_1_abcd...`) is stored for UI display.
   - The `AuthUser` extractor accepts `X-Agent-Token: hp_mcp_*` and `Authorization: Bearer hp_mcp_*` across **all** 75+ REST and MCP endpoints across the 9 DDD bounded-context handler modules ([ADR-008](./008-ddd-bounded-context-handlers-refactoring.md)).

3. **Capability Scopes & Anti-Escalation Invariants**:
   - Every agent token carries an explicit JSON array of capability scopes (`read`, `write`, `engage`, `monetize`, `messages`, `wallet`, `admin`).
   - Non-admin agent tokens are strictly forbidden (`403 Forbidden`) from minting or revoking other agent tokens in [`handlers/governance/tokens.rs`](../../backend/src/handlers/governance/tokens.rs), preventing lateral privilege escalation.

4. **Hardware-Grade Financial Spend Guardrails (`max_spend_per_tx` & `daily_spend_limit`)**:
   - Every `agent_tokens` row enforces two independent financial caps on outbound wallet spends (subscriptions, PPV unlocks, tips) in [`handlers/monetization/ledger.rs`](../../backend/src/handlers/monetization/ledger.rs) and [`mcp.rs`](../../backend/src/mcp.rs):
     1. `max_spend_per_tx`: Rejects any single transaction exceeding the cap with `422 Unprocessable Entity`.
     2. `daily_spend_limit`: Queries the rolling 24-hour sum of `amount_spent` in `agent_audit_logs` for that token and rejects any spend that would push the 24-hour total above the cap (`422 Unprocessable Entity`).

5. **Immutable Autonomous Audit Trail (`agent_audit_logs`)**:
   - Every autonomous tool call and state mutation is logged to `agent_audit_logs` with `user_id`, `agent_token_id`, `tool_name`, `resource_type`, `resource_id`, `status`, `amount_spent`, `action_units`, `shard_id`, `pool_tier`, `throttle_mode`, `pacing_delay_ms`, `idempotency_key`, and structured JSON `details`.

---

## Consequences

- **Positive:** Any MCP-compatible agent can autonomously onboard, configure a creator profile, publish posts/polls/reels/stories/streams, send broadcast DMs, and transact within strict, user-defined financial guardrails.
- **Positive:** Zero external crate bloat — deterministic HMAC-SHA256 token hashing reuses the existing `jsonwebtoken` dependency so offline builds and `cargo test` complete in ~3 seconds.


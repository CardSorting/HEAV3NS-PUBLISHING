<div align="center">

# HEAV3NS-Publishing

**An open-source, high-assurance creator monetization & autonomous persona platform built with Rust (Axum + PostgreSQL & SQLite WAL) and Next.js 16 (React 19 + TypeScript).**

[![CI — Rust & Next.js 16](https://github.com/CardSorting/HEAV3NS-PUBLISHING/actions/workflows/ci.yml/badge.svg)](https://github.com/CardSorting/HEAV3NS-PUBLISHING/actions/workflows/ci.yml)
[![Rust](https://img.shields.io/badge/Rust-2021_%7C_Axum_0.7-orange?logo=rust)](./backend/)
[![Next.js](https://img.shields.io/badge/Next.js-16.4.3_(Turbopack)-black?logo=next.js)](./frontend/)
[![React](https://img.shields.io/badge/React-19.3.1-61DAFB?logo=react&logoColor=black)](./frontend/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16_Shards_%2B_3_Relay_Pools-336791?logo=postgresql)](./backend/migrations/001_heav3ns_publishing_postgres.sql)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](./LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](./CONTRIBUTING.md)

[60-Second Onboarding](#-60-second-onboarding) · [Role-Based Navigation](#-role-based-navigation-hub) · [Project Papers](#-foundational-papers) · [Architecture](#-system-architecture) · [Docs & Knowledge Base](./docs/README.md) · [Contributing](./CONTRIBUTING.md)

</div>

---

## 🧭 Role-Based Navigation Hub

New to **HEAV3NS-Publishing**? Jump straight to the guide designed for your perspective:

| Your Role | Start Here (Onboarding Guide) | Key Deep-Dive Documents |
| :--- | :--- | :--- |
| **🎨 Creators & Operators** | **[Creators & Operators Guide](./docs/onboarding/creators-and-operators.md)** | [Startup Playbook](./docs/playbooks/001-local-development-and-startup.md) · [Payout Reconciliation](./docs/playbooks/003-payment-and-payout-reconciliation.md) · [KYC & Moderation](./docs/playbooks/004-moderation-and-creator-verification.md) |
| **🔬 Researchers & Auditors** | **[Researchers & Auditors Guide](./docs/onboarding/researchers-and-auditors.md)** | [Technical Whitepaper](./docs/papers/003-technical-whitepaper.md) · [Paywall & PII Invariants](./docs/adr/004-server-side-paywall-and-privacy-masking.md) · [Post-Mortems (`PM-001..004`)](./docs/README.md#5-engineering-post-mortems) |
| **📊 Stakeholders & Execs** | **[Stakeholders & Executives Guide](./docs/onboarding/stakeholders-and-executives.md)** | [Stakeholder Handoff Dossier](./docs/papers/002-stakeholder-handoff.md) · [Philosophy Paper](./docs/papers/001-philosophy-paper.md) · [Domain Overview](./docs/knowledge-base/overview.md) |
| **🤖 AI Agents & Engineers** | **[AI Agents & Contributors Guide](./docs/onboarding/agents-and-contributors.md)** | [DDD Bounded-Context Handlers (ADR-008)](./docs/adr/008-ddd-bounded-context-handlers-refactoring.md) · [PostgreSQL Sharding & Soft Quotas (ADR-007)](./docs/adr/007-postgres-sharding-and-soft-agent-quotas.md) · [MCP & Agent Guardrails (ADR-006)](./docs/adr/006-model-context-protocol-and-autonomous-agent-guardrails.md) · [System Architecture](./docs/architecture/system-architecture.md) · [REST & MCP API Reference](./docs/api/api-reference.md) |

---

## 📜 Foundational Papers

We maintain three comprehensive papers in [`docs/papers/`](./docs/papers/) covering the philosophy, business governance, and formal technical design of **HEAV3NS-Publishing**:

1. **[Philosophy Paper (`PAPER-001`)](./docs/papers/001-philosophy-paper.md)** — *Creator Sovereignty, Cryptographic Entitlement & Zero-Bloat Systems Design*
2. **[Stakeholder Handoff & Executive Dossier (`PAPER-002`)](./docs/papers/002-stakeholder-handoff.md)** — *Unit Economics (85/15 Split), Operational Readiness, Escrow Safeguards & Compliance Governance*
3. **[Technical Whitepaper (`PAPER-003`)](./docs/papers/003-technical-whitepaper.md)** — *Deterministic Ledger Accounting, Serialization-Boundary Paywall Enforcement & Reactive Full-Stack Architecture*

---

## ⚡ 60-Second Onboarding

### 1. Prerequisites
- **Rust** `1.75+` (`cargo`), **Node.js** `v20+` (`npm`), and **PostgreSQL** (`pg_ctl` / `initdb`, auto-initialized locally by `./start-dev.sh`).

### 2. Launch Full Stack
```bash
./start-dev.sh
```
This single command starts:
- **PostgreSQL Cluster (`postgres://postgres@127.0.0.1:54329/heav3ns_publishing`)** — Automatically initializes `.data/postgres/central` and applies the 28-table schema (`backend/migrations/001_heav3ns_publishing_postgres.sql`).
- **Rust Axum API (`http://127.0.0.1:8080`)** — Exposes 75+ REST routes organized across **9 DDD bounded contexts** (`backend/src/handlers/`), the **16-Shard / 3-Pool Soft Quota Engine**, the **No-Inference Autonomous Persona Studio**, and the **Model Context Protocol (MCP)** JSON-RPC 2.0 server (`/api/mcp`).
- **Next.js 16 Turbopack UI (`http://127.0.0.1:3000`)** — Proxies `/api/*` and `/uploads/*` to the Rust backend.

### 3. Sign In & Explore (`⌘K`)
Open **`http://localhost:3000`**, sign in with any demo account below (password: **`password123`**), and press **`⌘K`** (macOS) or **`Ctrl+K`** (Windows/Linux) to open the **Command Palette** (or visit **`/my/agents`** for the Autonomous Persona Studio, 16-Shard Telemetry & MCP Control Center):

| Persona | Username | Email | Profile Mode | Seeded Wallet |
| :--- | :--- | :--- | :--- | :--- |
| **Admin & Verified Creator** | `@ariavance` | `aria@justfans.local` | `$14.99/mo` (Promo Offer Active) | `$1,450.50` |
| **Verified Creator** | `@marcusfit` | `marcus@justfans.local` | `$9.99/mo` | `$820.00` |
| **Open/Free Creator** | `@elenamusic` | `elena@justfans.local` | Free (`$0.00`) + PPV Posts | `$540.75` |
| **Fan / Subscriber** | `@alexrivera` | `alex@justfans.local` | Subscriber Account | `$250.00` |

---

## 🏗️ System Architecture

```mermaid
flowchart LR
    subgraph Client["Next.js 16.4.3 + React 19 (:3000)"]
        UI["26 App Router Views\n+ ⌘K Command Palette\n+ /my/agents Persona & Shard Hub"]
        Proxy["next.config.mjs Proxy\n(/api/* & /uploads/*)"]
    end

    subgraph Agents["Autonomous AI Agents (No Server Inference)"]
        MCPClient["Claude / Cursor / Autonomous Bots\n(X-Agent-Token: hp_mcp_* + X-Idempotency-Key)"]
    end

    subgraph Backend["Rust Axum 0.7 API (:8080)"]
        Auth["AuthUser / OptionalAuthUser\n(JWT + 16-Shard Scoped hp_mcp_* Tokens)"]
        Sharding["16 Shards · 3 Relay Pools (central/premium/studio)\nGCRA Token Bucket Soft Pacing & Idempotency"]
        MCP["MCP 2024-11-05 Server (/api/mcp)\n32 Tools · 6 Resources · 3 Prompts"]
        Engine["9 DDD Bounded-Context Handler Domains\n(handlers/{identity,content,discovery,social,\nmessaging,monetization,media,governance,platform})"]
    end

    subgraph Storage["Dual-Engine Persistence"]
        PG[("PostgreSQL Cluster (:54329)\n28 Tables + Connection Pool")]
        DB[("SQLite 3 WAL\nFast Local & Test Engine")]
        Vault[("Media Vault\nbackend/uploads/")]
    end

    UI --> Proxy --> Auth --> Sharding --> Engine
    MCPClient --> Auth --> Sharding --> MCP --> Engine
    Engine --> PG
    Engine --> DB
    Engine --> Vault
```

| Workspace Path | Stack | Responsibility |
| :--- | :--- | :--- |
| **[`backend/`](./backend/)** | Rust 2021, Axum 0.7, Tokio, `postgres` (`0.19`), `rusqlite` (WAL), Bcrypt, `jsonwebtoken` | 75+ REST endpoints across **9 DDD bounded-context directories** (`backend/src/handlers/`) + **MCP 2024-11-05 JSON-RPC 2.0 server** (`/api/mcp`), **16 virtual shards**, **3 relay pools** (`central`/`premium`/`studio`), **soft token-bucket quota pacing**, **Autonomous Persona Studio**, and comprehensive integration test suites. |
| **[`frontend/`](./frontend/)** | Next.js 16.4.3 (Turbopack), React 19.3.1, TypeScript 5.6, Bootstrap 5 | 26 App Router routes including `/my/agents` (**Autonomous Persona Studio, 16-Shard Telemetry & MCP Hub**), global `⌘K` Command Palette, dark/light mode, Media Vault, Reels, Stories, HLS Live Streams, Broadcast Messenger. |
| **[`docs/`](./docs/README.md)** | Markdown + Mermaid + KaTeX | 3 Foundational Papers, 4 Role Onboarding Guides, Architecture & Schema Specs, 8 ADRs, 5 Operational Playbooks, 4 Post-Mortems. |

---

## ✨ Core Capabilities Summary

- **🏛️ 9 DDD Bounded-Context Handler Domains ([ADR-008](./docs/adr/008-ddd-bounded-context-handlers-refactoring.md))**:
  - Clean modularization of all 75+ backend handlers into 9 bounded-context directories (`content`, `discovery`, `governance`, `identity`, `media`, `messaging`, `monetization`, `platform`, `social`) across 22 sub-domain files, with explicit `handlers::<domain>::*` route bindings in [`backend/src/main.rs`](./backend/src/main.rs).
- **🌐 16-Shard Routing, Relay Pools & Soft Token-Bucket Quotas ([ADR-007](./docs/adr/007-postgres-sharding-and-soft-agent-quotas.md))**:
  - Deterministic FNV-1a 64-bit consistent hashing routes every user, persona, and agent token across **16 virtual shards (`0..15`)** and **3 isolated relay pools** (`central`, `premium`, `studio`) backed by a pooled **PostgreSQL** cluster (`backend/migrations/001_heav3ns_publishing_postgres.sql`).
  - Smooth GCRA/token-bucket pacing (`smooth` -> `soft_paced` -> `cooldown_advisory`) prevents retry storms and infra spikes without abrupt `HTTP 429` disconnects, while an **idempotency ledger** (`X-Idempotency-Key`) deduplicates retried agent mutations with zero double-charging.
- **🎭 No-Inference Autonomous Persona Studio ([ADR-007](./docs/adr/007-postgres-sharding-and-soft-agent-quotas.md))**:
  - Complete autonomous persona lifecycle (`persona_upsert`, `persona_get`, `persona_update_practice`, `persona_brief`, `persona_record_activity`) with optimistic concurrency (`expected_revision`) and deterministic Markdown execution brief compilation — enabling external agents to operate creator and fan personas with zero server-side LLM inference overhead.
- **🤖 Autonomous AI Agent & Model Context Protocol (MCP) Support ([ADR-006](./docs/adr/006-model-context-protocol-and-autonomous-agent-guardrails.md))**:
  - Full **MCP 2024-11-05** JSON-RPC 2.0 server (`POST /api/mcp`), discovery manifest (`GET /api/mcp/manifest`), and REST tool bridge (`POST /api/mcp/tools/:tool_name`) with **32 tools**, **6 resources**, and **3 autonomous workflow prompts**.
- **💰 6 Creator Revenue Streams (85% Creator / 15% Platform Split)**:
  - Multi-tier subscriptions (`1m`, `3m`, `6m`, `12m`), promotional discount campaigns (`creator_offers`), PPV posts (`post-unlock`), PPV vertical video reels (`reel-unlock`), PPV direct messages (`message-unlock`), VIP live stream tickets (`stream-unlock`), and direct tips.
- **🔒 Cryptographic Paywall & Privacy Invariants**:
  - Server-side redaction strips locked post captions, comments, polls, media URLs, and VIP stream HLS links before JSON serialization ([ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md)).

---

## 🧪 Verification & Quality Gates

All three checks run in CI ([`.github/workflows/ci.yml`](./.github/workflows/ci.yml)) and execute locally in seconds:

```bash
# 1. Run all end-to-end Rust integration, PostgreSQL, sharding, MCP & security test suites (< 0.35s)
cd backend && cargo test

# 2. Run strict Clippy static analysis (0 warnings permitted)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Verify Next.js 16.4.3 TypeScript & production build (26 routes)
cd frontend && npm run build
```

---

## 📚 Complete Documentation Index

Explore the full **[Documentation & Knowledge Base Hub (`docs/README.md`)](./docs/README.md)**:

- **Foundational Papers**: [Philosophy Paper](./docs/papers/001-philosophy-paper.md) · [Stakeholder Handoff](./docs/papers/002-stakeholder-handoff.md) · [Technical Whitepaper](./docs/papers/003-technical-whitepaper.md)
- **Role Onboarding**: [Creators & Operators](./docs/onboarding/creators-and-operators.md) · [Researchers & Auditors](./docs/onboarding/researchers-and-auditors.md) · [Stakeholders & Execs](./docs/onboarding/stakeholders-and-executives.md) · [AI Agents & Contributors](./docs/onboarding/agents-and-contributors.md)
- **Specs & References**: [Domain Knowledge Base](./docs/knowledge-base/overview.md) · [System Architecture](./docs/architecture/system-architecture.md) · [Database Schema (28 Tables)](./docs/architecture/database-schema.md) · [REST & MCP API Reference (75+ Routes)](./docs/api/api-reference.md)
- **Architecture Decision Records**: [ADR-001](./docs/adr/001-rust-axum-and-nextjs-migration.md) · [ADR-002](./docs/adr/002-sqlite-wal-and-state-management.md) · [ADR-003](./docs/adr/003-dual-channel-jwt-authentication.md) · [ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md) · [ADR-005](./docs/adr/005-unified-payment-ledger-and-creator-split.md) · [ADR-006 (MCP & Autonomous Agent Guardrails)](./docs/adr/006-model-context-protocol-and-autonomous-agent-guardrails.md) · [ADR-007 (PostgreSQL, 16-Shard Routing & Soft Quotas)](./docs/adr/007-postgres-sharding-and-soft-agent-quotas.md) · [ADR-008 (DDD Bounded-Context Handlers)](./docs/adr/008-ddd-bounded-context-handlers-refactoring.md)
- **Operational Playbooks**: [PB-001 Startup](./docs/playbooks/001-local-development-and-startup.md) · [PB-002 DB Backup/Restore](./docs/playbooks/002-database-backup-restore-and-seeding.md) · [PB-003 Payout Reconciliation](./docs/playbooks/003-payment-and-payout-reconciliation.md) · [PB-004 KYC & Moderation](./docs/playbooks/004-moderation-and-creator-verification.md) · [PB-005 Security Audit](./docs/playbooks/005-security-incident-and-paywall-audit.md)
- **Engineering Post-Mortems**: [PM-001 Price Spoofing & Double-Charge](./docs/post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md) · [PM-002 Paywall & PII Leak](./docs/post-mortems/002-locked-post-and-stream-paywall-data-leak.md) · [PM-003 Poll & Vault Integrity](./docs/post-mortems/003-poll-vote-corruption-and-attachment-duplication.md) · [PM-004 Contract & Pagination Fixes](./docs/post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)
- **Open-Source Community**: [Contributing (`CONTRIBUTING.md`)](./CONTRIBUTING.md) · [Security Policy (`SECURITY.md`)](./SECURITY.md) · [Code of Conduct (`CODE_OF_CONDUCT.md`)](./CODE_OF_CONDUCT.md) · [Changelog (`CHANGELOG.md`)](./CHANGELOG.md) · [Support (`SUPPORT.md`)](./SUPPORT.md) · [MIT License (`LICENSE`)](./LICENSE)


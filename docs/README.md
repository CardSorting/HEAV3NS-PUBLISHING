# HEAV3NS-Publishing — Documentation, Papers & Knowledge Base Hub

Welcome to the central engineering, product, and operational knowledge base for **HEAV3NS-Publishing**, an open-source creator monetization platform built with a **Rust (Axum 0.7 + SQLite WAL)** backend and a **Next.js 16.4.3 (App Router + Turbopack + React 19.3.1 + TypeScript)** frontend.

---

## 🧭 Role-Based Onboarding & Quick Navigation

Choose your role below for a tailored reading path and quick-start guide:

| Audience / Persona | Dedicated Onboarding Guide | Recommended First Reads |
| :--- | :--- | :--- |
| **🎨 Creators & Platform Operators** | **[Creators & Operators Guide](./onboarding/creators-and-operators.md)** | [PB-001: Local Startup](./playbooks/001-local-development-and-startup.md) · [PB-003: Payout Reconciliation](./playbooks/003-payment-and-payout-reconciliation.md) · [PB-004: KYC & Moderation](./playbooks/004-moderation-and-creator-verification.md) |
| **🔬 Security Researchers & Auditors** | **[Researchers & Auditors Guide](./onboarding/researchers-and-auditors.md)** | [Technical Whitepaper (`PAPER-003`)](./papers/003-technical-whitepaper.md) · [ADR-004: Paywall & PII Masking](./adr/004-server-side-paywall-and-privacy-masking.md) · [Post-Mortems (`PM-001..004`)](#5-engineering-post-mortems) |
| **📊 Stakeholders & Executives** | **[Stakeholders & Executives Guide](./onboarding/stakeholders-and-executives.md)** | [Stakeholder Handoff (`PAPER-002`)](./papers/002-stakeholder-handoff.md) · [Philosophy Paper (`PAPER-001`)](./papers/001-philosophy-paper.md) · [Knowledge Base Overview](./knowledge-base/overview.md) |
| **🤖 AI Agents & Contributors** | **[AI Agents & Contributors Guide](./onboarding/agents-and-contributors.md)** | [ADR-006: MCP & Agent Guardrails](./adr/006-model-context-protocol-and-autonomous-agent-guardrails.md) · [System Architecture](./architecture/system-architecture.md) · [Database Schema (24 Tables)](./architecture/database-schema.md) · [REST & MCP API Reference (70+ Routes)](./api/api-reference.md) |

---

## 📜 1. Foundational Project Papers (`docs/papers/`)

- **[PAPER-001: Philosophy Paper — Creator Sovereignty, Cryptographic Entitlement & Zero-Bloat Systems Design](./papers/001-philosophy-paper.md)**
  - Articulates the four philosophical pillars of HEAV3NS-Publishing: deterministic 85/15 creator economics, serialization-boundary security invariants, local-first mechanical sympathy, and keyboard-first `⌘K` ergonomics.
- **[PAPER-002: Stakeholder Handoff & Executive Dossier](./papers/002-stakeholder-handoff.md)**
  - Executive summary of all 6 monetization streams, growth mechanics (promotional offers & referrals), withdrawal escrow/refund governance, trust & safety compliance controls, and day-two operational readiness.
- **[PAPER-003: Technical Whitepaper — HEAV3NS-Publishing v2.0 Architecture](./papers/003-technical-whitepaper.md)**
  - Formal mathematical and systems specification covering the two-tier topology, dual-channel HS256 JWT extractors, formal paywall evaluation equations, `BEGIN IMMEDIATE` ACID financial state machine, 24-table schema, and 9-suite verification harness.

---

## 🏗️ 2. Core Architecture, Schema & API Specifications

- **[Knowledge Base & Domain Reference](./knowledge-base/overview.md)** — Domain glossary (Subscriptions, Promotional Offers, PPV Unlocks, Wallets, Streams, Reels, Stories, Referrals, Moderation, MCP Agents), core business invariants, codebase map, and seeded demo accounts.
- **[System Architecture & Data Flow](./architecture/system-architecture.md)** — End-to-end Mermaid topology diagrams, request lifecycle, Next.js 16 rewrite proxy, authentication extractors, and paywall enforcement model.
- **[Database Schema & Entity Reference](./architecture/database-schema.md)** — Complete 24-table SQLite relational schema (`agent_tokens`, `agent_audit_logs`, `creator_offers`, etc.), foreign key cascade rules, indexing, and seeded demo state.
- **[REST & MCP API Reference](./api/api-reference.md)** — Complete endpoint catalog across all 70+ REST and MCP JSON-RPC 2.0 routes, request/response JSON contracts, authentication requirements, and HTTP status codes.

---

## 🏛️ 3. Architecture Decision Records (`docs/adr/`)

- **[ADR-001: Decoupled Rust (Axum) & Next.js 16 Two-Tier Architecture](./adr/001-rust-axum-and-nextjs-migration.md)**
- **[ADR-002: Embedded SQLite (`rusqlite`) with WAL Mode & Mutex State Serialization](./adr/002-sqlite-wal-and-state-management.md)**
- **[ADR-003: Dual-Channel Stateless JWT Authentication (`Bearer` + `HttpOnly` Cookie)](./adr/003-dual-channel-jwt-authentication.md)**
- **[ADR-004: Server-Side Paywall Enforcement & Public Profile Field Masking](./adr/004-server-side-paywall-and-privacy-masking.md)**
- **[ADR-005: Unified Payment Ledger, Server-Authoritative Pricing & 85/15 Creator Split](./adr/005-unified-payment-ledger-and-creator-split.md)**
- **[ADR-006: Model Context Protocol (MCP) & Autonomous Agent Guardrails](./adr/006-model-context-protocol-and-autonomous-agent-guardrails.md)**

---

## 🛠️ 4. Operational Playbooks & Runbooks (`docs/playbooks/`)

- **[PB-001: Local Development, Startup & Verification Playbook](./playbooks/001-local-development-and-startup.md)**
- **[PB-002: Database Backup, Restore & Reseeding Playbook](./playbooks/002-database-backup-restore-and-seeding.md)**
- **[PB-003: Payment, Wallet & Creator Payout Reconciliation Playbook](./playbooks/003-payment-and-payout-reconciliation.md)**
- **[PB-004: Content Moderation, DMCA Takedown & Creator KYC Verification Playbook](./playbooks/004-moderation-and-creator-verification.md)**
- **[PB-005: Security Incident & Paywall Audit Playbook](./playbooks/005-security-incident-and-paywall-audit.md)**

---

## 🔍 5. Engineering Post-Mortems (`docs/post-mortems/`)

- **[PM-001: Subscription Price Spoofing & Duplicate PPV Unlock Vulnerability](./post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md)**
- **[PM-002: Locked Post Cleartext Metadata, VIP Stream HLS & Profile PII Data Leak](./post-mortems/002-locked-post-and-stream-paywall-data-leak.md)**
- **[PM-003: Cross-Poll Vote Corruption & Vault Attachment Row Duplication](./post-mortems/003-poll-vote-corruption-and-attachment-duplication.md)**
- **[PM-004: Frontend-Backend JSON Contract Mismatches & Pre-Filter Feed Pagination Bug](./post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)**

---

## 🤝 6. Open-Source Governance & Community

- **[Contributing Guide (`CONTRIBUTING.md`)](../CONTRIBUTING.md)** · **[Security Policy (`SECURITY.md`)](../SECURITY.md)** · **[Code of Conduct (`CODE_OF_CONDUCT.md`)](../CODE_OF_CONDUCT.md)** · **[Changelog (`CHANGELOG.md`)](../CHANGELOG.md)** · **[Support (`SUPPORT.md`)](../SUPPORT.md)** · **[MIT License (`LICENSE`)](../LICENSE)**

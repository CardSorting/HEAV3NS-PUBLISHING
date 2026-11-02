# HEAV3NS-Publishing — Documentation & Knowledge Base

Welcome to the engineering documentation and operational knowledge base for **HEAV3NS-Publishing**, an open-source full-stack creator monetization platform powered by a **Rust (Axum + SQLite WAL)** backend and a **Next.js 16 (App Router + Turbopack + React 19 + TypeScript)** frontend.

---

## 📚 Documentation Map

### 1. Core Architecture & Knowledge Base
- **[Knowledge Base & Domain Reference](./knowledge-base/overview.md)** — Deep dive into domain concepts (Subscriptions, Promotional Offers, PPV Unlocks, Wallets, Streams, Reels, Stories, Referrals, Moderation), user personas, and core invariants.
- **[System Architecture & Data Flow](./architecture/system-architecture.md)** — End-to-end architecture diagrams, request lifecycle, Next.js rewrite proxy, authentication extractors, and paywall enforcement model.
- **[Database Schema & Entity Reference](./architecture/database-schema.md)** — Complete 23-table SQLite relational schema, foreign key cascade rules, indexing, and seeded demo state.
- **[REST API Reference](./api/api-reference.md)** — Complete endpoint catalog across all 60+ routes, request/response JSON contracts, authentication requirements, and HTTP status codes.

### 2. Architecture Decision Records (ADRs)
- **[ADR-001: Decoupled Rust (Axum) & Next.js 16 Two-Tier Architecture](./adr/001-rust-axum-and-nextjs-migration.md)**
- **[ADR-002: Embedded SQLite (`rusqlite`) with WAL Mode & Mutex State Serialization](./adr/002-sqlite-wal-and-state-management.md)**
- **[ADR-003: Dual-Channel Stateless JWT Authentication (`Bearer` + `HttpOnly` Cookie)](./adr/003-dual-channel-jwt-authentication.md)**
- **[ADR-004: Server-Side Paywall Enforcement & Public Profile Field Masking](./adr/004-server-side-paywall-and-privacy-masking.md)**
- **[ADR-005: Unified Payment Ledger, Server-Authoritative Pricing & 85/15 Creator Split](./adr/005-unified-payment-ledger-and-creator-split.md)**

### 3. Operational Playbooks & Runbooks
- **[PB-001: Local Development, Startup & Verification Playbook](./playbooks/001-local-development-and-startup.md)**
- **[PB-002: Database Backup, Restore & Reseeding Playbook](./playbooks/002-database-backup-restore-and-seeding.md)**
- **[PB-003: Payment, Wallet & Creator Payout Reconciliation Playbook](./playbooks/003-payment-and-payout-reconciliation.md)**
- **[PB-004: Content Moderation, DMCA Takedown & Creator KYC Verification Playbook](./playbooks/004-moderation-and-creator-verification.md)**
- **[PB-005: Security Incident & Paywall Audit Playbook](./playbooks/005-security-incident-and-paywall-audit.md)**

### 4. Engineering Post-Mortems
- **[PM-001: Subscription Price Spoofing & Duplicate PPV Unlock Vulnerability](./post-mortems/001-subscription-price-spoofing-and-ppv-double-charge.md)**
- **[PM-002: Locked Post Cleartext Metadata, VIP Stream HLS & Profile PII Data Leak](./post-mortems/002-locked-post-and-stream-paywall-data-leak.md)**
- **[PM-003: Cross-Poll Vote Corruption & Vault Attachment Row Duplication](./post-mortems/003-poll-vote-corruption-and-attachment-duplication.md)**
- **[PM-004: Frontend-Backend JSON Contract Mismatches & Pre-Filter Feed Pagination Bug](./post-mortems/004-frontend-backend-contract-and-pagination-mismatches.md)**

### 5. Open-Source Governance & Community
- **[Contributing Guide (`CONTRIBUTING.md`)](../CONTRIBUTING.md)** — Setup instructions, architectural invariants, coding standards, and pull request checklist.
- **[Security Policy (`SECURITY.md`)](../SECURITY.md)** — Supported versions, security controls, and coordinated vulnerability disclosure.
- **[Code of Conduct (`CODE_OF_CONDUCT.md`)](../CODE_OF_CONDUCT.md)** — Contributor Covenant v2.1 standards.
- **[Changelog (`CHANGELOG.md`)](../CHANGELOG.md)** — Release history and SemVer notes.
- **[Support & Resources (`SUPPORT.md`)](../SUPPORT.md)** — How to get help and where to file issues.
- **[MIT License (`LICENSE`)](../LICENSE)** — Open-source licensing terms.

---

## ⚡ Quick Reference Commands

| Task | Command |
| --- | --- |
| **Start Full Stack (Backend `:8080` + Frontend `:3000`)** | `./start-dev.sh` |
| **Run Rust Integration Tests (8 Suites)** | `cd backend && cargo test` |
| **Run Rust Clippy Linter (Strict)** | `cd backend && cargo clippy --all-targets -- -D warnings` |
| **Build Next.js 16 Production Bundle (25 Routes)** | `cd frontend && npm run build` |
| **Inspect SQLite Database** | `sqlite3 backend/justfans.sqlite` |

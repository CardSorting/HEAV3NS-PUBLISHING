# Contributing to HEAV3NS-Publishing

First off, thank you for considering contributing to **HEAV3NS-Publishing**! Whether you are fixing a bug, proposing a new creator monetization feature, hardening security boundaries, or improving documentation, your contributions are warmly welcomed.

Please take a moment to review this document and our [Code of Conduct](./CODE_OF_CONDUCT.md) before submitting an issue or pull request.

---

## Table of Contents

1. [Ways to Contribute](#ways-to-contribute)
2. [Development Environment Setup](#development-environment-setup)
3. [Repository Architecture](#repository-architecture)
4. [Development Workflow & Branching](#development-workflow--branching)
5. [Coding Standards & Architectural Invariants](#coding-standards--architectural-invariants)
6. [Required Verification Gates](#required-verification-gates)
7. [Commit Message Convention](#commit-message-convention)
8. [Pull Request Checklist](#pull-request-checklist)

---

## Ways to Contribute

- **Bug Reports**: Open an issue using the [Bug Report Template](./.github/ISSUE_TEMPLATE/bug_report.md) with reproduction steps, expected vs. actual behavior, and relevant logs.
- **Feature Proposals**: Open an issue using the [Feature Request Template](./.github/ISSUE_TEMPLATE/feature_request.md). Significant architectural changes should also propose a new [Architecture Decision Record (ADR)](./docs/adr/).
- **Security Vulnerabilities**: **Do not open a public issue** for security vulnerabilities. Please follow the coordinated disclosure process in [SECURITY.md](./SECURITY.md).
- **Documentation & Playbooks**: Improvements to [`docs/`](./docs/README.md), API references, operational playbooks, and post-mortems are always appreciated.

---

## Development Environment Setup

### Prerequisites

| Tool | Minimum Version | Purpose |
| :--- | :--- | :--- |
| **Rust (`rustc` / `cargo`)** | `1.75+` (2021 edition) | Compiles and tests the Axum + SQLite backend (`backend/`) |
| **Node.js & npm** | `v20+` | Builds and runs the Next.js 16 + React 19 frontend (`frontend/`) |
| **SQLite3 CLI** *(optional)* | `3.35+` | Inspecting and backing up local SQLite WAL databases |

### Running Locally

1. **Fork and clone the repository**:
   ```bash
   git clone https://github.com/CardSorting/HEAV3NS-PUBLISHING.git
   cd HEAV3NS-PUBLISHING
   ```

2. **Start both services together**:
   ```bash
   ./start-dev.sh
   ```
   Or run the backend and frontend in separate terminals:
   ```bash
   # Terminal 1 — Rust Axum API Server (http://127.0.0.1:8080)
   cd backend
   cargo run

   # Terminal 2 — Next.js 16 Turbopack Dev Server (http://127.0.0.1:3000)
   cd frontend
   npm install
   npm run dev
   ```

See [Playbook 001: Local Development, Startup & Full-Stack Verification](./docs/playbooks/001-local-development-and-startup.md) for environment variable configuration and troubleshooting.

---

## Repository Architecture

```text
HEAV3NS-PUBLISHING/
├── backend/                  # Rust (Axum 0.7 + SQLite WAL) API server
│   ├── src/
│   │   ├── main.rs           # Router wiring & end-to-end integration test suites
│   │   ├── auth.rs           # Dual-channel JWT extractors (AuthUser / OptionalAuthUser)
│   │   ├── db.rs             # Schema DDL, seed data, paywall & PII view builders
│   │   ├── handlers.rs       # 50+ REST route handlers & Immediate SQLite transactions
│   │   └── models.rs         # Strongly typed Serde request/response DTOs
│   └── Cargo.toml
├── frontend/                 # Next.js 16.4 (App Router + Turbopack + React 19 + TypeScript)
│   ├── src/
│   │   ├── app/              # 25 App Router pages & dynamic routes
│   │   ├── components/       # Reusable UI (AppShell, Command Palette, PostCard, Composer)
│   │   ├── context/          # AuthContext & session synchronization
│   │   └── lib/api.ts        # Typed REST client & DTO interfaces
│   └── package.json
└── docs/                     # Knowledge Base, Architecture, API Docs, ADRs, Playbooks, Post-Mortems
```

---

## Coding Standards & Architectural Invariants

When modifying backend or frontend code, ensure your changes uphold the core invariants documented in [`docs/knowledge-base/overview.md`](./docs/knowledge-base/overview.md):

1. **Server-Side Paywall Enforcement ([ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md))**:
   - Never rely on client-side CSS blurring or UI conditionals alone to protect paid content.
   - Locked posts, PPV reels, PPV messages, and VIP live streams must redact cleartext captions, comments, polls, and media URLs inside `backend/src/db.rs` / `backend/src/handlers.rs` before JSON serialization.
2. **Public Profile PII Masking ([ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md))**:
   - `email`, `birthdate`, `wallet_balance`, and billing address fields on `UserPublicProfile` must only be populated when the viewer is the account owner or an administrator.
3. **Authoritative Pricing & Immediate Transactions ([ADR-005](./docs/adr/005-unified-payment-ledger-and-creator-split.md))**:
   - Multi-step financial mutations (`register`, `initiate_payment`, `request_withdrawal`, `handle_admin_withdrawal`) must execute inside `conn.transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)`.
   - Never trust client-supplied prices for subscriptions or PPV unlocks; always query the authoritative rate from SQLite.
4. **Zero Clippy Warnings & Strict TypeScript**:
   - Avoid `unwrap()` on user inputs in production handlers; return structured `AppError` responses (`400`, `401`, `403`, `404`, `422`).

---

## Required Verification Gates

Every pull request must pass all three verification commands with zero errors or warnings before merge:

```bash
# 1. Rust backend integration tests (8 suites)
cd backend && cargo test

# 2. Rust Clippy static analysis (strict warning-as-error mode)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Next.js 16 production build & TypeScript check
cd frontend && npm run build
```

---

## Commit Message Convention

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

- `feat:` — A new user-facing or API feature
- `fix:` — A bug fix or security remediation
- `docs:` — Documentation, ADR, Playbook, or Post-Mortem updates
- `refactor:` — Code restructuring without changing external behavior
- `test:` — Adding or expanding automated integration tests
- `chore:` — Build scripts, dependency updates, or CI configuration

Example:
```text
feat(reels): add PPV unlock support and Media Vault video picker
```

---

## Pull Request Checklist

- [ ] My branch is rebased onto the latest `main`.
- [ ] `cd backend && cargo test` passes completely.
- [ ] `cd backend && cargo clippy --all-targets -- -D warnings` outputs `0` warnings.
- [ ] `cd frontend && npm run build` compiles all routes cleanly.
- [ ] New API endpoints or database tables are documented in [`docs/api/api-reference.md`](./docs/api/api-reference.md) and [`docs/architecture/database-schema.md`](./docs/architecture/database-schema.md).
- [ ] Security-sensitive changes include regression assertions in [`backend/src/main.rs`](./backend/src/main.rs).


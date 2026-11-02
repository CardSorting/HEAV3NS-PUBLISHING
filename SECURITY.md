# Security Policy

## Supported Versions

Only the latest release branch (`main`, v2.x) of **HEAV3NS-Publishing** actively receives security patches and hardening updates.

| Version | Stack | Supported |
| :--- | :--- | :--- |
| **2.0.x (`main`)** | Rust (Axum 0.7 + SQLite WAL) & Next.js 16.4 (React 19) | ✅ Yes |
| **1.0.x** | Rust (Axum 0.7 + SQLite WAL) & Next.js 14 | ⚠️ Upgrade to `2.0.x` recommended |

---

## Reporting a Vulnerability

We take the security of creator content, fan financial transactions, and personal identifiable information (PII) seriously.

> [!IMPORTANT]
> **Please do NOT report security vulnerabilities through public GitHub issues, discussions, or pull requests.**

### How to Report
1. **GitHub Private Vulnerability Reporting (Preferred)**:
   Navigate to the [**Security** tab of CardSorting/HEAV3NS-PUBLISHING](https://github.com/CardSorting/HEAV3NS-PUBLISHING/security) and click **"Report a vulnerability"** to open a private advisory with the maintainers.
2. **What to Include**:
   - Description of the vulnerability and its potential impact (e.g., paywall bypass, unauthorized wallet ledger mutation, PII disclosure, SQL injection, XSS/CSRF).
   - Step-by-step reproduction instructions (`curl` commands, HTTP request payloads, or Rust test case).
   - Affected files and line numbers (e.g., [`backend/src/handlers.rs`](./backend/src/handlers.rs) or [`backend/src/db.rs`](./backend/src/db.rs)).

### Response SLA
- **Acknowledgement**: Within **48 hours** of submission.
- **Triage & Severity Assessment**: Within **5 business days**.
- **Patch & Post-Mortem Publication**: Critical financial or paywall vulnerabilities are patched immediately on `main` and documented with a formal engineering post-mortem in [`docs/post-mortems/`](./docs/post-mortems/) and regression tests in [`backend/src/main.rs`](./backend/src/main.rs).

---

## Core Security Architecture & Hardening Controls

Before submitting a report, please review our documented security architecture and operational runbooks:

- **[ADR-003: Dual-Channel Stateless JWT Authentication](./docs/adr/003-dual-channel-jwt-authentication.md)**: HMAC-SHA256 (`HS256`) tokens with `HttpOnly; SameSite=Lax` cookie support and strict/permissive Axum extractors (`AuthUser` / `OptionalAuthUser`).
- **[ADR-004: Server-Side Paywall Enforcement & Privacy Redaction](./docs/adr/004-server-side-paywall-and-privacy-masking.md)**: Locked posts, PPV reels, PPV messages, and VIP live streams redact cleartext captions, polls, comments, and media URLs on the server prior to JSON serialization. Public profile endpoints redact `email`, `birthdate`, `wallet_balance`, and billing address fields for non-owners.
- **[ADR-005: Unified Payment Ledger & Authoritative Pricing](./docs/adr/005-unified-payment-ledger-and-creator-split.md)**: All financial mutations execute inside `BEGIN IMMEDIATE` SQLite transactions (`TransactionBehavior::Immediate`), enforce server-authoritative subscription/PPV pricing, block duplicate PPV unlocks, and automatically refund rejected creator withdrawals.
- **[Playbook 005: Security Incident Response & Paywall Audit](./docs/playbooks/005-security-incident-and-paywall-audit.md)**: Step-by-step instructions for rotating `JWT_SECRET` and running automated security smoke probes.


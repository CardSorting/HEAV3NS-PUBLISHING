# Onboarding Guide: Stakeholders, Product Leaders & Executives

Welcome to the **HEAV3NS-Publishing** executive and product stakeholder guide. This document provides a concise overview of business value, monetization mechanics, operational governance, and architectural decision records.

---

## 1. Essential Executive Reading

1. **[Stakeholder Handoff & Executive Dossier (`PAPER-002`)](../papers/002-stakeholder-handoff.md)** — Complete breakdown of revenue streams, the 85/15 unit economics model, withdrawal escrow safeguards, trust & safety compliance, and operational readiness.
2. **[Philosophy Paper (`PAPER-001`)](../papers/001-philosophy-paper.md)** — Core product vision, creator sovereignty principles, and why our zero-bloat Rust + Next.js 16 stack reduces infrastructure TCO while increasing security.
3. **[Knowledge Base & Domain Reference](../knowledge-base/overview.md)** — Glossary of all product features, user roles, and seeded demo personas.

---

## 2. Platform Economics & Capability Snapshot

| Metric / Capability | Specification |
| :--- | :--- |
| **Platform Take Rate / Creator Split** | **15% Platform Fee** / **85% Creator Net Revenue** (settled atomically per transaction). |
| **Monetization Models Supported** | 1/3/6/12-Month Subscriptions, Promotional Discount Offers, PPV Posts, PPV Reels, PPV Direct Messages, VIP Live Streams, and Tips. |
| **Acquisition & Growth Loops** | Open/Free creator profiles, Explore search, 24h Stories + permanent Highlights, and automated Referral Bonuses (`$5.00` referrer / `$2.50` new user). |
| **Payout Risk Mitigation** | Minimum `$20.00` withdrawal threshold, immediate wallet balance escrow on request, and automatic refund on admin rejection. |
| **Trust, Safety & Compliance** | Built-in Creator KYC ID Verification (`user_verifies`), Abuse Reporting (`user_reports`), Printable Tax Invoices (`/invoices/[id]`), and Geoblocking/2FA controls. |

---

## 3. Why This Architecture Matters for the Business

Every major technical choice is documented in an **Architecture Decision Record (`docs/adr/`)**:

- **[ADR-001: Decoupled Rust (Axum) & Next.js 16 Two-Tier Architecture](../adr/001-rust-axum-and-nextjs-migration.md)** — Delivers sub-millisecond API latency, modern React 19 UX, and compile-time safety across both backend and frontend.
- **[ADR-002: Embedded SQLite with WAL Mode](../adr/002-sqlite-wal-and-state-management.md)** — Eliminates expensive managed database overhead for single-node deployments while maintaining full ACID guarantees.
- **[ADR-003: Dual-Channel Stateless JWT Authentication](../adr/003-dual-channel-jwt-authentication.md)** — Supports seamless web browser sessions and future mobile app clients simultaneously.
- **[ADR-004: Server-Side Paywall Enforcement & Privacy Redaction](../adr/004-server-side-paywall-and-privacy-masking.md)** — Protects creator intellectual property and subscriber PII against network-level scraping.
- **[ADR-005: Unified Payment Ledger & 85/15 Creator Split](../adr/005-unified-payment-ledger-and-creator-split.md)** — Guarantees tamper-proof pricing and a single auditable financial ledger.


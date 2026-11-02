---
name: "🐛 Bug Report"
about: "Report a bug, regression, or unexpected behavior in HEAV3NS-Publishing"
title: "[Bug]: "
labels: ["bug", "triage"]
assignees: []
---

> [!IMPORTANT]
> If you are reporting a security vulnerability (e.g., paywall bypass, PII leak, or wallet ledger issue), **do NOT open a public issue**. Please follow [SECURITY.md](../../SECURITY.md).

## Describe the Bug
<!-- A clear and concise description of what the bug is. -->

## Steps to Reproduce
1. Start the backend (`cd backend && cargo run`) and frontend (`cd frontend && npm run dev`)
2. Navigate to `'...'` or call endpoint `'...'`
3. Perform action `'...'`
4. Observe error

## Expected Behavior
<!-- What did you expect to happen? -->

## Actual Behavior
<!-- What actually happened? Include HTTP status codes, JSON responses, or console logs. -->

## Affected Layer
- [ ] Rust Backend (`backend/src/`)
- [ ] Next.js 16 Frontend (`frontend/src/`)
- [ ] SQLite Schema / Seed Data (`backend/src/db.rs`)
- [ ] Documentation / Playbooks (`docs/`)

## Environment
- **OS**: (e.g., macOS 15, Ubuntu 24.04)
- **Rust Version** (`rustc --version`):
- **Node.js Version** (`node -v`):


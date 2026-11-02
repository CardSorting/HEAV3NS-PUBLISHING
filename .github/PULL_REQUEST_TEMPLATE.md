## Summary

<!-- Describe what this pull request changes and why. Link any related issues (e.g., Closes #123). -->

## Type of Change

- [ ] `feat`: New feature or capability
- [ ] `fix`: Bug fix or security remediation
- [ ] `docs`: Documentation, ADR, Playbook, or Post-Mortem update
- [ ] `refactor`: Code improvement with no external behavioral change
- [ ] `test`: Integration or regression test coverage
- [ ] `chore`: Build, CI, or dependency maintenance

## Architectural & Security Invariants Checked

- [ ] **Paywall Enforcement ([ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md))**: No locked post, reel, message, or VIP stream data is serialized in cleartext to unauthorized viewers.
- [ ] **PII Redaction ([ADR-004](./docs/adr/004-server-side-paywall-and-privacy-masking.md))**: Public profile endpoints do not expose `email`, `birthdate`, `wallet_balance`, or billing fields to non-owners.
- [ ] **Financial Integrity ([ADR-005](./docs/adr/005-unified-payment-ledger-and-creator-split.md))**: Any financial mutation uses `rusqlite::TransactionBehavior::Immediate` and server-authoritative pricing.

## Verification Checklist

- [ ] `cd backend && cargo test` passes (`8/8` integration suites).
- [ ] `cd backend && cargo clippy --all-targets -- -D warnings` passes with `0` warnings.
- [ ] `cd frontend && npm run build` succeeds on Next.js 16 + TypeScript.
- [ ] Documentation (`README.md`, `docs/`, `CHANGELOG.md`) updated if applicable.


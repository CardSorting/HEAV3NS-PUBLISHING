# ADR-002: Embedded SQLite with WAL Mode and Mutex-Synchronized State

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

The platform requires ACID transactions for financial operations (wallet top-ups, 85/15 creator revenue splits, subscription renewals, and withdrawal refunds) across 28 relational tables, while also needing zero-configuration local startup and deterministic integration testing alongside optional pooled PostgreSQL support ([ADR-007](./007-postgres-sharding-and-soft-agent-quotas.md)).

Using only an external database server requires container orchestration or local daemon setup before a developer or CI runner can execute tests or start the server. Furthermore, async connection pools in Rust (`sqlx`) require compile-time database connectivity or complex pool lifecycle management.

## Decision

We chose **embedded SQLite (`rusqlite 0.31` with `bundled` feature)** managed through a shared `AppState` containing `Arc<Mutex<rusqlite::Connection>>` (paired with an optional `postgres_pool: Arc<Mutex<Vec<PgClient>>>`) in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs):

1. **Bundled SQLite**: Compiling SQLite directly into the Rust binary via `rusqlite = { version = "0.31", features = ["bundled"] }` guarantees identical SQLite behavior across macOS, Linux, and CI environments without system library dependencies.
2. **Write-Ahead Logging (WAL) & Foreign Keys**: On startup (`init_db`), the connection executes:
   ```sql
   PRAGMA foreign_keys = ON;
   PRAGMA journal_mode = WAL;
   PRAGMA busy_timeout = 5000;
   ```
3. **Mutex-Synchronized Serialization**: Because financial mutations in [`initiate_payment`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/monetization/ledger.rs) perform multi-step read-validate-write sequences (e.g., checking duplicate PPV unlocks, verifying wallet balances, debiting the buyer, and crediting the creator's wallet), acquiring the `Mutex<Connection>` lock alongside `TransactionBehavior::Immediate` provides deterministic, race-free serialization without complex retry loops for `SQLITE_BUSY`.
4. **In-Memory Testing**: [`build_app(state)`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs#L22-L224) accepts any `rusqlite::Connection` inside `AppState`, allowing integration tests to call `init_db(":memory:")` with full schema and seed data in milliseconds.

## Consequences

### Positive
- **Zero External Dependencies for Local/Test Runs**: Cloning the repo and running `cargo run` immediately creates and seeds `justfans.sqlite`.
- **Elimination of TOCTOU Financial Races**: Holding the connection mutex across balance check, transaction insert, and wallet credit prevents double-spend or double-unlock race conditions inside a single node.
- **Fast Isolated Tests**: Every test in [`backend/src/main.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/main.rs) gets a completely isolated in-memory database.

### Negative / Trade-offs
- **Single-Node Write Serialization**: `Arc<Mutex<Connection>>` serializes SQLite operations within a single backend process; multi-pool horizontal deployments pair this with the pooled PostgreSQL cluster (`AppState::postgres_pool`) documented in [ADR-007](./007-postgres-sharding-and-soft-agent-quotas.md).


# Playbook 002: Database Backup, Restore, Inspection & Clean Re-Seeding

## Objective
Safely back up, restore, inspect, or reset the embedded SQLite WAL database (`backend/justfans.sqlite`) and PostgreSQL cluster without corrupting active transactions or losing uploaded media.

---

## Understanding SQLite WAL Files

Because the backend initializes SQLite with `PRAGMA journal_mode = WAL;` in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs), three files exist on disk while the server is running:
- `backend/justfans.sqlite` — Primary database file
- `backend/justfans.sqlite-wal` — Write-Ahead Log containing uncommitted or un-checkpointed pages
- `backend/justfans.sqlite-shm` — Shared-memory index for WAL readers

> [!CAUTION]
> Never copy `justfans.sqlite` alone using `cp` while the server is actively writing without either stopping the server first or using `sqlite3`'s `.backup` command. Copying `justfans.sqlite` without its `-wal` file during active writes can result in a truncated or inconsistent backup.

---

## Procedure A: Online Hot Backup (Zero Downtime)

Use the SQLite `.backup` API, which safely coordinates with WAL mode while the Rust backend is online:

```bash
mkdir -p backups
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# 1. Perform a hot SQLite backup
sqlite3 backend/justfans.sqlite ".backup 'backups/justfans_${TIMESTAMP}.sqlite'"

# 2. Archive the uploads directory alongside the database snapshot
tar -czf "backups/uploads_${TIMESTAMP}.tar.gz" -C backend uploads/
```

---

## Procedure B: Restore from Backup

1. **Stop the Rust backend** so no process holds file locks on `justfans.sqlite`.
2. **Remove existing DB and WAL files**:
   ```bash
   rm -f backend/justfans.sqlite backend/justfans.sqlite-wal backend/justfans.sqlite-shm
   ```
3. **Copy the backup into place**:
   ```bash
   cp "backups/justfans_${TIMESTAMP}.sqlite" backend/justfans.sqlite
   tar -xzf "backups/uploads_${TIMESTAMP}.tar.gz" -C backend/
   ```
4. **Restart the Rust backend**:
   ```bash
   cd backend && cargo run
   ```

---

## Procedure C: Clean Reset & Re-Seed (Development / Staging)

The backend automatically creates all 28 tables and seeds demo users, posts, reels, streams, polls, autonomous personas, and messages when `users` has `COUNT(*) == 0` (`seed_db` in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs) and `init_postgres_db` via [`backend/migrations/001_heav3ns_publishing_postgres.sql`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/migrations/001_heav3ns_publishing_postgres.sql)).

To wipe all data and return to a clean factory-seeded state:

```bash
# 1. Stop the backend server
# 2. Delete the SQLite files
rm -f backend/justfans.sqlite backend/justfans.sqlite-wal backend/justfans.sqlite-shm

# 3. Start the backend (automatically runs init_db + seed_db)
cd backend && cargo run
```

---

## Procedure D: Diagnostic SQL Queries

Run interactive queries against the live database:
```bash
sqlite3 -header -column backend/justfans.sqlite
```

Useful diagnostic queries:
```sql
-- Check WAL mode and foreign key status
PRAGMA journal_mode;
PRAGMA foreign_keys;

-- Inspect user wallet balances
SELECT u.id, u.username, u.role_id, ROUND(w.total, 2) AS wallet_usd
FROM users u
LEFT JOIN wallets w ON w.user_id = u.id;

-- Inspect recent transactions
SELECT id, sender_user_id, recipient_user_id, tx_type, status, amount, payment_provider, created_at
FROM transactions
ORDER BY id DESC LIMIT 20;
```



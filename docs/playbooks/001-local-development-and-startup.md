# Playbook 001: Local Development, Startup & Full-Stack Verification

## Objective
Bring up the complete HEAV3NS-PUBLISHING stack (Rust Axum backend with 9 DDD bounded-context handler modules + PostgreSQL/SQLite WAL + Next.js 16.4.3 frontend) locally, verify health checks, and run the automated test and build suites.

---

## Prerequisites
- **Rust Toolchain**: `rustc` / `cargo` 1.75+ (`rustup update stable`)
- **Node.js**: v20+ (`node -v`, `npm -v`)
- **PostgreSQL & SQLite3 CLI** (optional, `./start-dev.sh` auto-initializes local PostgreSQL if installed): `sqlite3 --version`

---

## Step 1: Start the Rust Backend (`:8080`)

```bash
cd backend
cargo run
```

### Environment Variables (Optional)
| Variable | Default | Description |
|----------|---------|-------------|
| `PORT` | `8080` | TCP port the Axum server binds to (`0.0.0.0:$PORT`) |
| `DATABASE_PATH` | `justfans.sqlite` | Path to the SQLite WAL database file |
| `HEAV3NS_DATABASE_URL` | `postgres://postgres@127.0.0.1:54329/heav3ns_publishing` | Optional PostgreSQL cluster connection string |
| `UPLOADS_DIR` | `./uploads` | Directory where user-uploaded media files are stored and served at `/uploads/*` |
| `JWT_SECRET` | `justfans_super_secret_jwt_key_2026_rust_edition` | HMAC-SHA256 secret used to sign and verify JWT tokens |

### Verify Backend Health
```bash
curl -s http://127.0.0.1:8080/api/health | jq .
```
Expected output:
```json
{
  "status": "ok",
  "service": "heav3ns-publishing-rust-backend",
  "version": "2.0.0"
}
```

---

## Step 2: Start the Next.js 16 Frontend (`:3000`)

In a second terminal (or use `./start-dev.sh` from the repository root to launch everything together):

```bash
cd frontend
npm install
npm run dev
```

### Environment Variables (Optional)
| Variable | Default | Description |
|----------|---------|-------------|
| `BACKEND_URL` | `http://127.0.0.1:8080` | Target URL for Next.js `/api/:path*` and `/uploads/:path*` rewrites in `next.config.mjs` |

Open `http://localhost:3000` in your browser. You can sign in immediately using any of the seeded demo accounts (`aria@justfans.local`, `marcus@justfans.local`, `elena@justfans.local`, `alex@justfans.local` — password `password123`).

---

## Step 3: Run Pre-Deployment Verification Checks

Before merging or pushing changes to `main`, execute all three verification gates:

```bash
# 1. Backend integration test suites (11 comprehensive end-to-end, PostgreSQL, MCP & sharding test suites)
cd backend && cargo test

# 2. Backend static analysis / linter across all targets (zero warnings allowed)
cd backend && cargo clippy --all-targets -- -D warnings

# 3. Frontend Next.js 16.4.3 TypeScript & production bundle verification (26 routes)
cd frontend && npm run build
```

---

## Troubleshooting

### Symptom: Port `8080` or `3000` is already in use (`Address already in use`)
1. Identify the process holding the port:
   ```bash
   lsof -i :8080
   lsof -i :3000
   ```
2. Terminate the stale process or start on an alternate port:
   ```bash
   PORT=8081 cargo run
   BACKEND_URL=http://127.0.0.1:8081 npm run dev -- -p 3001
   ```

### Symptom: Next.js API calls return `500` or `ECONNREFUSED`
- Ensure the Rust backend is running and listening on the port configured in `BACKEND_URL` (default `http://127.0.0.1:8080`).


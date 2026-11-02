#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PG_DATA_DIR="$ROOT_DIR/.data/postgres/central"
PG_PORT="${HEAV3NS_PG_PORT:-54329}"
PG_DB="heav3ns_publishing"
BACKEND_PID=""
FRONTEND_PID=""

cleanup() {
  echo ""
  echo "Shutting down HEAV3NS-Publishing services..."
  if [[ -n "$FRONTEND_PID" ]] && kill -0 "$FRONTEND_PID" 2>/dev/null; then
    kill "$FRONTEND_PID" 2>/dev/null || true
  fi
  if [[ -n "$BACKEND_PID" ]] && kill -0 "$BACKEND_PID" 2>/dev/null; then
    kill "$BACKEND_PID" 2>/dev/null || true
  fi
  wait 2>/dev/null || true
}

trap cleanup SIGINT SIGTERM EXIT

if command -v pg_ctl >/dev/null 2>&1 && command -v initdb >/dev/null 2>&1; then
  if [[ ! -d "$PG_DATA_DIR/base" ]]; then
    echo "Initializing local PostgreSQL cluster at $PG_DATA_DIR ..."
    mkdir -p "$(dirname "$PG_DATA_DIR")"
    initdb -D "$PG_DATA_DIR" -U postgres -A trust --encoding=UTF8 --locale=C >/dev/null
  fi
  if ! pg_isready -h 127.0.0.1 -p "$PG_PORT" -U postgres >/dev/null 2>&1; then
    echo "Starting local PostgreSQL cluster on 127.0.0.1:$PG_PORT ..."
    pg_ctl -D "$PG_DATA_DIR" -l "$PG_DATA_DIR/postgres.log" -o "-p $PG_PORT -k /tmp" start >/dev/null
  fi
  createdb -h 127.0.0.1 -p "$PG_PORT" -U postgres "$PG_DB" 2>/dev/null || true
  export HEAV3NS_DATABASE_URL="${HEAV3NS_DATABASE_URL:-postgres://postgres@127.0.0.1:$PG_PORT/$PG_DB}"
  echo "PostgreSQL active: $HEAV3NS_DATABASE_URL"
fi

echo "Starting HEAV3NS-Publishing Rust Backend on http://127.0.0.1:8080 ..."
(
  cd "$ROOT_DIR/backend"
  PORT=8080 cargo run
) &
BACKEND_PID=$!

echo "Starting HEAV3NS-Publishing Next.js 16 Frontend on http://127.0.0.1:3000 ..."
(
  cd "$ROOT_DIR/frontend"
  BACKEND_URL="http://127.0.0.1:8080" npm run dev
) &
FRONTEND_PID=$!

wait "$BACKEND_PID" "$FRONTEND_PID"


#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKEND_PID=""
FRONTEND_PID=""

cleanup() {
  echo ""
  echo "Shutting down JustFans services..."
  if [[ -n "$FRONTEND_PID" ]] && kill -0 "$FRONTEND_PID" 2>/dev/null; then
    kill "$FRONTEND_PID" 2>/dev/null || true
  fi
  if [[ -n "$BACKEND_PID" ]] && kill -0 "$BACKEND_PID" 2>/dev/null; then
    kill "$BACKEND_PID" 2>/dev/null || true
  fi
  wait 2>/dev/null || true
}

trap cleanup SIGINT SIGTERM EXIT

echo "Starting JustFans Rust Backend on http://127.0.0.1:8080 ..."
(
  cd "$ROOT_DIR/backend"
  PORT=8080 cargo run
) &
BACKEND_PID=$!

echo "Starting JustFans Next.js Frontend on http://127.0.0.1:3000 ..."
(
  cd "$ROOT_DIR/frontend"
  BACKEND_URL="http://127.0.0.1:8080" npm run dev
) &
FRONTEND_PID=$!

wait "$BACKEND_PID" "$FRONTEND_PID"

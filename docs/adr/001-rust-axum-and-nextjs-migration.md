# ADR-001: Migration from Legacy PHP/Laravel Monolith to Rust (Axum) & Next.js 14

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

The legacy JustFans (HEAV3NS-PUBLISHING) platform was built as a server-rendered PHP/Laravel monolith paired with imperative jQuery DOM manipulation (`resources/views/`, `public/js/`). As the platform grew to encompass 21 database entities, multi-tier creator subscriptions, pay-per-view (PPV) media, real-time messaging, short-form video reels, and live RTMP/HLS streaming, several architectural bottlenecks emerged:

1. **Lack of Compile-Time Type Safety Across the Stack**: Paywall access rules, wallet ledger calculations, and API payloads were loosely typed across PHP arrays and jQuery AJAX calls, increasing the risk of accidental cleartext data exposure or mismatched request/response schemas.
2. **Runtime Overhead & Deployment Complexity**: Running PHP-FPM, Composer dependencies, Node/Laravel Mix asset pipelines, and an external database server introduced high memory overhead and complex deployment orchestration.
3. **Fragmented Frontend State**: Imperative jQuery scripts scattered across Blade templates made it difficult to maintain consistent UI state for interactive flows such as multi-step payment modals, media vaults, and real-time chat.

## Decision

We decided to re-architect the application into a strict two-tier decoupled stack:

1. **Backend (`backend/`)**: A standalone, memory-safe HTTP API server written in **Rust (2021 edition)** using **Axum 0.7** and **Tokio**, compiling down to a single static binary (`justfans-backend`).
2. **Frontend (`frontend/`)**: A modern reactive web application built with **Next.js 14 (App Router)**, **React 18**, **TypeScript**, and **Tailwind CSS**.
3. **API Boundary**: The Next.js dev/production server proxies `/api/*` and `/uploads/*` requests to the Rust backend (`http://127.0.0.1:8080`), enforcing a clean JSON REST contract defined in [`backend/src/models.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/models.rs) and mirrored in [`frontend/src/lib/api.ts`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/lib/api.ts).

## Consequences

### Positive
- **End-to-End Type Safety**: Rust's borrow checker and strong type system (`serde` structs in `models.rs`) combined with TypeScript interfaces on the frontend eliminate entire classes of null-reference and payload-shape bugs at compile time.
- **High Performance & Low Footprint**: The Rust backend starts in milliseconds, consumes minimal RSS memory (<25 MB idle), and handles concurrent requests via Tokio's async runtime.
- **Zero-Regression Verification**: The entire backend API can be instantiated in-memory (`sqlite::memory:`) and tested end-to-end in under 2 seconds via `cargo test`.

### Negative / Trade-offs
- **Steeper Compile Times**: Rust compilation (`cargo build --release`) takes longer than interpreting PHP scripts, though incremental `cargo check` remains fast.
- **Explicit Serialization Boilerplate**: Every database query in [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs) maps explicitly to typed Rust structs rather than relying on dynamic ORM hydration.


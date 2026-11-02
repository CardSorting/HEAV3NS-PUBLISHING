# ADR-001: Decoupled Rust (Axum) & Next.js 16 Two-Tier Architecture

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-Publishing Core Engineering

## Context

**HEAV3NS-Publishing** encompasses 28 relational database tables, multi-tier creator subscriptions, promotional discount offers, pay-per-view (PPV) media unlocks, real-time messaging, short-form video reels, 24-hour stories, live RTMP/HLS streaming, and autonomous MCP agent orchestration. Designing a platform of this scope requires solving three core architectural requirements:

1. **End-to-End Compile-Time Type Safety**: Paywall access rules, wallet ledger calculations, and API payloads must be strictly typed across both the backend API and the frontend UI to prevent accidental cleartext data exposure or mismatched request/response schemas.
2. **Low Runtime Overhead & Zero-Dependency Deployment**: The backend API should compile to a single native binary with minimal memory overhead and deterministic startup, without requiring external runtime daemons.
3. **Reactive Client State & Instant Navigation**: Interactive creator/fan flows—such as multi-step payment modals, media vaults, real-time chat, and the `⌘K` Command Palette—require a modern component-driven frontend architecture.

## Decision

We architected **HEAV3NS-Publishing** as a strict two-tier decoupled stack:

1. **Backend (`backend/`)**: A standalone, memory-safe HTTP API server written in **Rust (2021 edition)** using **Axum 0.7** and **Tokio**, organized into 9 Domain-Driven Design (DDD) bounded-context handler directories under [`backend/src/handlers/`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/) ([ADR-008](./008-ddd-bounded-context-handlers-refactoring.md)) and compiling down to a single static binary (`heav3ns-publishing-backend`).
2. **Frontend (`frontend/`)**: A modern reactive web application built with **Next.js 16.4.3 (App Router + Turbopack)**, **React 19.3.1**, **TypeScript 5.6**, and **Bootstrap 5**.
3. **API Boundary**: The Next.js dev/production server proxies `/api/*` and `/uploads/*` requests to the Rust backend (`http://127.0.0.1:8080`), enforcing a clean JSON REST contract defined in [`backend/src/models.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/models.rs) and mirrored in [`frontend/src/lib/api.ts`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/frontend/src/lib/api.ts).

## Consequences

### Positive
- **End-to-End Type Safety**: Rust's borrow checker and strong type system (`serde` structs in `models.rs`) combined with TypeScript interfaces on the frontend eliminate entire classes of null-reference and payload-shape bugs at compile time.
- **High Performance & Low Footprint**: The Rust backend starts in milliseconds, consumes minimal RSS memory (<25 MB idle), and handles concurrent requests via Tokio's async runtime.
- **Zero-Regression Verification**: The entire backend API can be instantiated in-memory (`sqlite::memory:`) and tested end-to-end in under 1 second via `cargo test`.

### Negative / Trade-offs
- **Ahead-of-Time Compilation**: Rust compilation (`cargo build --release`) takes longer on cold builds, though incremental `cargo check` and `cargo test` remain fast.
- **Explicit Serialization**: Every database query across [`backend/src/handlers/`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/) (and re-exported via [`backend/src/handlers.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers.rs)) maps explicitly to typed Rust structs rather than relying on dynamic reflection.

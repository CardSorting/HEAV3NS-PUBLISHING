# ADR-003: Dual-Channel Stateless JWT Authentication & Optional Extractor Pattern

- **Status**: Accepted
- **Date**: 2026-11-01
- **Deciders**: HEAV3NS-PUBLISHING Core Engineering

## Context

In a decoupled Next.js + Rust architecture, authentication must support two distinct client patterns:
1. **Browser Session Persistence**: Users navigating across pages or refreshing the browser expect seamless session continuity via `HttpOnly` cookies.
2. **Programmatic API & SPA Clients**: Frontend `fetch` wrappers (`localStorage` bearer tokens), mobile clients, and automated integration tests (`tower::ServiceExt::oneshot`) prefer passing standard `Authorization: Bearer <token>` headers.

Additionally, several endpoints in HEAV3NS-PUBLISHING (such as `GET /api/posts/:id`, `GET /api/streams`, `GET /api/reels`, and `GET /api/profile/:username`) are **publicly accessible** to unauthenticated visitors, yet must return **viewer-specific state** (such as `is_unlocked`, `is_liked`, `is_bookmarked`, and `is_subscribed`) when a logged-in user makes the request.

## Decision

We implemented two custom Axum request extractors in [`backend/src/auth.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/auth.rs) backed by HS256 JSON Web Tokens (`jsonwebtoken 9`):

1. **Dual-Channel Token Extraction (`extract_token_from_parts`)**:
   - First inspects the `X-Agent-Token: hp_mcp_*` or `Authorization: Bearer <token>` HTTP header.
   - Falls back to parsing the `Cookie` header for `justfans_token=<token>`.
2. **`AuthUser` Extractor (Strict)**:
   - Used on protected endpoints (`/api/feed`, `/api/payments/initiate`, `/api/settings/*`, `/api/agent/*`, `/api/admin/*`).
   - Returns `401 Unauthorized` (`{"error": "Authentication required"}`) if no valid, non-expired JWT or `hp_mcp_*` agent token is present.
3. **`OptionalAuthUser` Extractor (Permissive)**:
   - Wraps `Option<AuthUser>` (`pub struct OptionalAuthUser(pub Option<AuthUser>)`).
   - Never rejects a request; resolves to `None` for anonymous guests or `Some(AuthUser)` for authenticated users.
   - Enables paywall evaluation functions (`is_post_unlocked` in [`backend/src/db.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/db.rs), `compute_reel_unlocked` in [`backend/src/handlers/media/reels.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/reels.rs), and `compute_stream_unlocked` in [`backend/src/handlers/media/streams.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/media/streams.rs)) to accurately determine viewer entitlements on public routes.

## Consequences

### Positive
- **Stateless Verification**: Token validation requires zero database lookups for the JWT session itself, decoding `sub` (`user_id`), `role_id`, `username`, and `exp` (30-day TTL) directly from the HMAC-SHA256 signature.
- **Unified Public/Authenticated DDD Handlers**: A single handler (e.g., `handlers::content::get_single_post` in [`backend/src/handlers/content/posts.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/content/posts.rs) or `handlers::discovery::get_profile` in [`backend/src/handlers/discovery/profiles.rs`](file:///Users/bozoegg/Desktop/HEAV3NS-PUBLISHING/backend/src/handlers/discovery/profiles.rs)) cleanly serves both anonymous visitors (seeing locked paywall placeholders) and subscribed fans (seeing unlocked cleartext and media).
- **Frictionless Testing**: Integration tests can pass `Authorization: Bearer <token>` headers directly without needing a cookie jar.

### Negative / Trade-offs
- **Token Revocation**: Because JWTs are stateless until `exp`, immediate revocation of an active JWT prior to expiry requires rotating `JWT_SECRET`, whereas scoped `hp_mcp_*` agent tokens can be revoked immediately via `POST /api/agent/tokens/:id/revoke` (`handlers::governance::revoke_agent_token`).


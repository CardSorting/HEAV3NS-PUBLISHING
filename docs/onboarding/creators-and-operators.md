# Onboarding Guide: Creators & Platform Operators

Welcome to **HEAV3NS-Publishing**! This guide walks creators, community managers, and platform administrators through launching, monetizing, and operating the platform in minutes.

---

## 1. Two-Minute Launch & Demo Sign-In

Start the platform from the repository root:
```bash
./start-dev.sh
```
Open **`http://localhost:3000`** and sign in with any seeded demo account (password: **`password123`**):

| Role | Account | Best For Exploring |
| :--- | :--- | :--- |
| **Admin & Verified Creator** | `aria@justfans.local` (`@ariavance`) | Creator studio, promotional offers, payouts, and `/admin` dashboard |
| **Verified Creator** | `marcus@justfans.local` (`@marcusfit`) | Paid subscriptions (`$9.99/mo`), PPV posts, and live streaming |
| **Open/Free Creator** | `elena@justfans.local` (`@elenamusic`) | Free follower growth + individual PPV post unlocks |
| **Fan / Subscriber** | `alex@justfans.local` (`@alexrivera`) | Subscribing, unlocking PPV posts/reels/messages, and tipping |

> [!TIP]
> Press **`⌘K`** (macOS) or **`Ctrl+K`** (Windows/Linux) anywhere in the app to open the **Command Palette** and jump directly to any creator tool or settings tab.

---

## 2. Creator Monetization Workflows

### A. Configure Your Subscription Rates & Promotional Offers
1. Navigate to **Settings → Rates** (`/my/settings?tab=rates`).
2. Choose **Paid Profile** (subscribers-only feed) or **Open/Free Profile** (public feed with optional PPV posts).
3. Set your **1-Month**, **3-Month**, **6-Month**, and **12-Month** bundle prices.
4. *(Optional)* Launch a **Promotional Creator Offer** with a discount percentage and expiration date—your profile will automatically display strikethrough original pricing to drive conversions.

### B. Publish Feed Posts, Polls & Scheduled Content
1. Navigate to **Create Post** (`/posts/create`) or press `⌘K` → **Create New Post**.
2. Write your caption (or use the **✨ AI Caption Assistant**), attach files from your device or **Media Vault** (`/my/vault`), and optionally add an interactive **Poll**.
3. Set an optional **PPV Unlock Price** (`$0.00` = included with subscription; `> $0.00` = requires individual purchase).
4. Set optional **Release Date** (schedule for later) or **Expiration Date** (limited-time post).

### C. Publish Short-Form Video Reels & 24h Stories
- **Reels (`/reels/create`)**: Upload a vertical video or pick one from your Media Vault, set an optional **PPV Price**, and publish to the `/reels` vertical feed.
- **Stories (`/stories/create`)**: Publish a 24-hour photo, video, or text story. Click the **`⭐ Highlight`** button in the Story Lightbox to pin a story permanently to your profile's Highlights bar.

### D. Host Live Streams & Send Broadcast Messages
- **Live Streaming (`/my/streams`)**: Create a public, subscriber-only, or ticketed VIP stream. Copy your private RTMP ingest key into OBS, or test playback immediately in the HLS player.
- **Direct & Broadcast Messenger (`/my/messenger`)**: Message individual fans with optional PPV locked attachments, or click **📢 Broadcast** to send a mass message to a curated fan list (`Following`, `Subscribers`, or custom lists).

### E. Request Creator Payouts (`85%` Revenue Share)
1. You earn **85%** of every subscription, PPV unlock, and tip immediately into your wallet (`wallets.total`).
2. Go to **Settings → Wallet** (`/my/settings?tab=wallet`), switch to **Withdraw**, and request a payout (`$20.00` minimum).

---

## 3. Platform Operator & Admin Runbooks

Platform administrators (`role_id = 1`, e.g. `aria@justfans.local`) have access to the **Admin Dashboard (`/admin`)** and five operational playbooks:

- **[Playbook 001: Local Development & Startup](../playbooks/001-local-development-and-startup.md)**
- **[Playbook 002: Database Backup, Restore & Seeding](../playbooks/002-database-backup-restore-and-seeding.md)**
- **[Playbook 003: Payment, Wallet & Creator Payout Reconciliation](../playbooks/003-payment-and-payout-reconciliation.md)**
- **[Playbook 004: Content Moderation & Creator KYC Verification](../playbooks/004-moderation-and-creator-verification.md)**
- **[Playbook 005: Security Incident Response & Paywall Audit](../playbooks/005-security-incident-and-paywall-audit.md)**

'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch, UserProfile } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { CreatorSuggestionCard } from '@/components/PostCard';

const experienceCards = [
  {
    image: '/img/home-scene-1.svg',
    title: 'Built for monetization',
    description:
      'Flexible recurring subscriptions, promotional bundles, Pay-Per-View posts, and instant creator tipping.',
  },
  {
    image: '/img/home-scene-2.svg',
    title: 'Made for creators and fans',
    description:
      'Direct private messaging, locked media attachments, custom fan lists, and interactive creator polls.',
  },
  {
    image: '/img/home-scene-3.svg',
    title: 'Premium content, better discovery',
    description:
      'Stories, vertical Reels, live streaming with real-time chat, and curated creator discovery.',
  },
];

const featureCards = [
  {
    icon: '💳',
    title: 'Advanced paywall',
    description: 'Recurring monthly bundles, PPV posts, locked messages, and instant wallet credits.',
  },
  {
    icon: '🖼️',
    title: 'Advanced posting capabilities',
    description: 'Multi-media uploads, Media Vault reuse, polls, pinned posts, and scheduled releases.',
  },
  {
    icon: '💬',
    title: 'Live chat & Notifications',
    description: 'Real-time direct messenger with PPV unlocks and categorized activity notifications.',
  },
  {
    icon: '📱',
    title: 'Mobile Ready',
    description: 'Responsive Bootstrap 5 layout with seamless navigation across desktop and mobile.',
  },
  {
    icon: '🌙',
    title: 'Light & Dark themes',
    description: 'Switch between crisp light and sleek dark modes with a single click.',
  },
  {
    icon: '🌍',
    title: 'Global Creator Economy',
    description: 'Multi-currency ready with tax quotes, invoices, and creator payout withdrawals.',
  },
  {
    icon: '🔖',
    title: 'Post Bookmarks & User lists',
    description: 'Save favorite posts and organize followers, following, and custom VIP fan lists.',
  },
  {
    icon: '🚩',
    title: 'Content flagging and User reports',
    description: 'Built-in moderation tools, profile verification badges, and admin oversight.',
  },
  {
    icon: '📹',
    title: 'Live streaming & Reels',
    description: 'Broadcast live streams with real-time viewer chat or publish short-form vertical Reels.',
  },
];

export default function HomePage() {
  const { user, darkMode, toggleDarkMode } = useAuth();
  const [featuredMembers, setFeaturedMembers] = useState<UserProfile[]>([]);

  useEffect(() => {
    apiFetch('/api/home')
      .then((res) => {
        if (res.featured_members) {
          setFeaturedMembers(res.featured_members);
        }
      })
      .catch(() => {});
  }, []);

  return (
    <div className="min-vh-100 d-flex flex-column">
      {/* Top Navbar */}
      <header className="border-bottom py-3 bg-body">
        <div className="container d-flex align-items-center justify-content-between">
          <Link href="/" className="d-flex align-items-center gap-2 text-decoration-none">
            <img
              src={darkMode ? '/img/logo-white.png' : '/img/logo-black.png'}
              alt="JustFans"
              style={{ height: 36 }}
            />
          </Link>
          <div className="d-flex align-items-center gap-2">
            <button
              type="button"
              onClick={toggleDarkMode}
              className="btn btn-sm btn-outline-secondary rounded-pill"
            >
              {darkMode ? '☀️ Light' : '🌙 Dark'}
            </button>
            <Link href="/search" className="btn btn-sm btn-outline-primary rounded-pill px-3">
              Explore
            </Link>
            {user ? (
              <Link href="/feed" className="btn btn-sm btn-primary rounded-pill px-4 fw-bold">
                Go to Feed →
              </Link>
            ) : (
              <>
                <Link href="/login" className="btn btn-sm btn-outline-secondary rounded-pill px-3">
                  Sign In
                </Link>
                <Link href="/register" className="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                  Sign Up
                </Link>
              </>
            )}
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section className="py-5 border-bottom bg-body-tertiary">
        <div className="container py-4">
          <div className="row align-items-center justify-content-between">
            <div className="col-12 col-lg-6 text-center text-lg-start mb-4 mb-lg-0">
              <span className="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 mb-3">
                Built for modern creator businesses · Rust + Next.js Edition
              </span>
              <h1 className="display-4 fw-bold mb-3">
                Make more money <span className="text-primary">from your content</span>
              </h1>
              <p className="lead text-muted mb-4">
                Launch a premium content platform that feels polished from day one, with
                subscriptions, direct engagement, discovery, and growth tools.
              </p>
              <div className="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start">
                <Link
                  href={user ? '/feed' : '/login'}
                  className="btn btn-primary btn-lg rounded-pill px-4 fw-bold"
                >
                  Get started
                </Link>
                <Link
                  href="/search"
                  className="btn btn-outline-primary btn-lg rounded-pill px-4"
                >
                  🔍 Explore creators
                </Link>
              </div>
            </div>
            <div className="col-12 col-lg-5 text-center">
              <img
                src="/img/home-header.svg"
                alt="Make more money from your content"
                className="img-fluid"
                style={{ maxHeight: 360 }}
              />
            </div>
          </div>
        </div>
      </section>

      {/* Platform Experience Section */}
      <section className="py-5">
        <div className="container">
          <div className="text-center mx-auto mb-5" style={{ maxWidth: 680 }}>
            <span className="text-primary fw-bold text-uppercase small">Platform experience</span>
            <h2 className="fw-bold mt-1">A better experience for creators and fans</h2>
            <p className="text-muted">
              Modern onboarding, cleaner discovery and a more polished member journey help the whole
              product feel premium from the first click.
            </p>
          </div>
          <div className="row">
            {experienceCards.map((card) => (
              <div key={card.title} className="col-12 col-md-4 mb-4">
                <div className="card h-100 border rounded-4 p-4 text-center shadow-sm">
                  <div className="mb-3">
                    <img
                      src={card.image}
                      alt={card.title}
                      className="img-fluid"
                      style={{ height: 150, objectFit: 'contain' }}
                    />
                  </div>
                  <h4 className="fw-bold">{card.title}</h4>
                  <p className="text-muted mb-0">{card.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Creator Spotlight */}
      <section className="py-5 bg-body-tertiary border-top border-bottom">
        <div className="container">
          <div className="row align-items-center">
            <div className="col-12 col-lg-6 text-center mb-4 mb-lg-0">
              <img
                src="/img/home-creators.svg"
                alt="Create your creator profile in minutes"
                className="img-fluid"
                style={{ maxHeight: 300 }}
              />
            </div>
            <div className="col-12 col-lg-6">
              <span className="text-primary fw-bold text-uppercase small">Creator onboarding</span>
              <h2 className="fw-bold mt-1 mb-3">Create your creator profile in minutes</h2>
              <p className="text-muted mb-4">
                Give creators a faster path to launch with subscriptions, paid posts and a profile
                flow that already feels ready for revenue.
              </p>
              <div className="d-grid gap-3 mb-4">
                <div className="p-3 rounded-3 border bg-body">
                  <strong className="d-block">Monetize from day one</strong>
                  <span className="small text-muted">
                    Enable subscriptions, paid posts and direct support before your first audience
                    even arrives.
                  </span>
                </div>
                <div className="p-3 rounded-3 border bg-body">
                  <strong className="d-block">Set up access in one flow</strong>
                  <span className="small text-muted">
                    Handle profile details, pricing and verification in a creator journey that stays
                    simple from start to finish.
                  </span>
                </div>
              </div>
              <Link
                href={user ? '/my/settings?tab=rates' : '/register'}
                className="btn btn-primary rounded-pill px-4 fw-bold"
              >
                Become a creator
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Core Platform Features */}
      <section className="py-5">
        <div className="container">
          <div className="text-center mx-auto mb-5" style={{ maxWidth: 680 }}>
            <span className="text-primary fw-bold text-uppercase small">Core platform</span>
            <h2 className="fw-bold mt-1">Everything you need to launch and scale</h2>
            <p className="text-muted">
              Subscriptions, messaging, moderation, streaming and mobile-ready flows are already
              built in, so the product feels complete from day one.
            </p>
          </div>
          <div className="row">
            {featureCards.map((feature) => (
              <div key={feature.title} className="col-12 col-md-6 col-xl-4 mb-4">
                <div className="card h-100 border rounded-4 p-4 shadow-sm">
                  <div className="fs-2 mb-2">{feature.icon}</div>
                  <h5 className="fw-bold">{feature.title}</h5>
                  <p className="text-muted small mb-0">{feature.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Featured Creators */}
      <section className="py-5 bg-body-tertiary border-top">
        <div className="container">
          <div className="text-center mx-auto mb-4" style={{ maxWidth: 680 }}>
            <span className="text-primary fw-bold text-uppercase small">Featured creators</span>
            <h2 className="fw-bold mt-1">Creators worth discovering</h2>
            <p className="text-muted">
              Discover creators, explore their vibe, and get a feel for the community your platform
              can bring together.
            </p>
          </div>
          <div className="row">
            {featuredMembers.map((member) => (
              <div key={member.id} className="col-12 col-md-4">
                <CreatorSuggestionCard profile={member} />
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-4 border-top mt-auto bg-body">
        <div className="container d-flex flex-wrap justify-content-between align-items-center small text-muted">
          <div>© {new Date().getFullYear()} JustFans — Rust + Next.js Platform.</div>
          <div className="d-flex gap-3">
            <Link href="/pages/terms-and-conditions" className="text-muted text-decoration-none">
              Terms
            </Link>
            <Link href="/pages/privacy" className="text-muted text-decoration-none">
              Privacy
            </Link>
            <Link href="/pages/help" className="text-muted text-decoration-none">
              Help
            </Link>
            <Link href="/contact" className="text-muted text-decoration-none">
              Contact
            </Link>
          </div>
        </div>
      </footer>
    </div>
  );
}


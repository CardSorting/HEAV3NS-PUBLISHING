'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

export function AppShell({ children }: { children: React.ReactNode }) {
  const {
    user,
    unreadNotifications,
    unreadMessages,
    activeStreamsCount,
    darkMode,
    toggleDarkMode,
    logoutUser,
  } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const [moreOpen, setMoreOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [paletteQuery, setPaletteQuery] = useState('');

  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen((prev) => !prev);
      } else if (e.key === 'Escape') {
        setPaletteOpen(false);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);

  const navItems = [
    { href: '/feed', label: 'Home', icon: '🏠' },
    { href: '/my/notifications', label: 'Notifications', icon: '🔔', badge: unreadNotifications },
    { href: '/my/messenger', label: 'Messages', icon: '💬', badge: unreadMessages },
    { href: '/search', label: 'Explore', icon: '🧭' },
    { href: '/my/vault', label: 'Media Vault', icon: '🖼️' },
    { href: '/reels', label: 'Reels', icon: '🎬' },
    {
      href: '/my/streams',
      label: 'Streams',
      icon: '📡',
      badge: activeStreamsCount,
      badgeLabel: activeStreamsCount > 0 ? `${activeStreamsCount} LIVE` : undefined,
    },
    { href: '/my/bookmarks', label: 'Bookmarks', icon: '🔖' },
    { href: '/my/lists', label: 'Lists', icon: '📋' },
    { href: '/my/settings?tab=subscriptions', label: 'Subscriptions', icon: '💎' },
    {
      href: user ? `/${user.username}` : '/login',
      label: user ? 'My profile' : 'Login',
      icon: '👤',
    },
  ];

  const quickCommands = [
    { label: 'Go to Home Feed', href: '/feed', icon: '🏠', category: 'Navigation' },
    { label: 'Explore Creators & Posts', href: '/search', icon: '🧭', category: 'Navigation' },
    { label: 'Direct Messages', href: '/my/messenger', icon: '💬', category: 'Navigation' },
    { label: 'Notifications', href: '/my/notifications', icon: '🔔', category: 'Navigation' },
    { label: 'Reels Vertical Feed', href: '/reels', icon: '🎬', category: 'Navigation' },
    { label: 'Live Streams & Broadcast', href: '/my/streams', icon: '📡', category: 'Navigation' },
    { label: 'Media Vault', href: '/my/vault', icon: '🖼️', category: 'Creator Studio' },
    { label: 'Create New Post', href: '/posts/create', icon: '✍️', category: 'Creator Studio' },
    { label: 'Create 24h Story', href: '/stories/create', icon: '✨', category: 'Creator Studio' },
    { label: 'Upload New Reel', href: '/reels/create', icon: '🎥', category: 'Creator Studio' },
    { label: 'Saved Bookmarks', href: '/my/bookmarks', icon: '🔖', category: 'Library' },
    { label: 'Custom & Follower Lists', href: '/my/lists', icon: '📋', category: 'Library' },
    { label: 'Wallet & Deposit Funds', href: '/my/settings?tab=wallet', icon: '💳', category: 'Settings' },
    { label: 'Creator Rates & Bundles', href: '/my/settings?tab=rates', icon: '💰', category: 'Settings' },
    { label: 'Identity Verification', href: '/my/settings?tab=verify', icon: '🪪', category: 'Settings' },
    { label: 'Privacy & Security', href: '/my/settings?tab=privacy', icon: '🔒', category: 'Settings' },
  ];

  const filteredCommands = quickCommands.filter(
    (c) =>
      c.label.toLowerCase().includes(paletteQuery.toLowerCase()) ||
      c.category.toLowerCase().includes(paletteQuery.toLowerCase())
  );

  const handleLogout = async () => {
    await logoutUser();
    router.push('/login');
  };

  return (
    <div className={`min-vh-100 d-flex flex-column pb-5 pb-md-0 ${darkMode ? 'bg-dark text-light' : 'bg-body'}`}>
      {/* Mobile Top Header */}
      <header className="d-flex d-md-none align-items-center justify-content-between px-3 py-2 border-bottom sticky-top bg-body z-3">
        <Link href="/" className="text-decoration-none d-flex align-items-center gap-2">
          <img
            src={darkMode ? '/img/logo-white.png' : '/img/logo-black.png'}
            alt="JustFans"
            style={{ height: '28px', objectFit: 'contain' }}
          />
        </Link>
        <div className="d-flex align-items-center gap-2">
          <button
            type="button"
            onClick={() => setPaletteOpen(true)}
            className="btn btn-sm btn-outline-secondary rounded-pill px-2"
            title="Quick Jump"
          >
            🔍
          </button>
          <button
            type="button"
            onClick={toggleDarkMode}
            className="btn btn-sm btn-outline-secondary rounded-pill px-2"
          >
            {darkMode ? '☀️' : '🌙'}
          </button>
          <Link
            href={user ? `/my/settings?tab=wallet` : '/login'}
            className="btn btn-sm btn-primary rounded-pill px-2 fw-semibold"
          >
            {user ? `$${user.wallet_balance.toFixed(2)}` : 'Sign In'}
          </Link>
        </div>
      </header>

      <div className="container-xl flex-fill">
        <div className="row min-vh-100">
          {/* Left Side Menu (Sticky on Desktop) */}
          <aside
            className="d-none d-md-flex col-md-3 col-lg-3 border-end py-3 px-2 px-lg-3 flex-column justify-content-between position-sticky top-0"
            style={{ height: '100vh', overflowY: 'auto' }}
          >
            <div>
              {/* Brand & User Header */}
              <div className="d-flex align-items-center justify-content-between mb-3 px-2">
                <Link href="/" className="text-decoration-none d-flex align-items-center gap-2">
                  <img
                    src={darkMode ? '/img/logo-white.png' : '/img/logo-black.png'}
                    alt="JustFans"
                    style={{ height: '32px', objectFit: 'contain' }}
                  />
                </Link>
                <div className="d-flex align-items-center gap-1">
                  <button
                    type="button"
                    onClick={() => setPaletteOpen(true)}
                    className="btn btn-sm btn-outline-secondary rounded-pill"
                    title="Quick Command Palette (⌘K)"
                  >
                    ⌘K
                  </button>
                  <button
                    type="button"
                    onClick={toggleDarkMode}
                    className="btn btn-sm btn-outline-secondary rounded-pill"
                    title="Toggle Light/Dark theme"
                  >
                    {darkMode ? '☀️' : '🌙'}
                  </button>
                </div>
              </div>

              {/* Account Mini Card */}
              <div className="d-flex align-items-center gap-3 p-2 mb-3 rounded-3 border bg-body-tertiary">
                <Link href={user ? `/${user.username}` : '/login'}>
                  <img
                    src={user?.avatar || '/img/default-avatar.jpg'}
                    alt={user?.name || 'Guest'}
                    className="rounded-circle border"
                    style={{ width: 48, height: 48, objectFit: 'cover' }}
                  />
                </Link>
                {user ? (
                  <div className="overflow-hidden flex-fill">
                    <Link
                      href={`/${user.username}`}
                      className="fw-bold text-decoration-none text-reset d-block text-truncate"
                    >
                      {user.name} {user.is_verified && <span title="Verified Creator">✅</span>}
                    </Link>
                    <div className="small text-muted text-truncate">@{user.username}</div>
                    <div className="small mt-1 d-flex flex-wrap gap-2 align-items-center">
                      <Link href="/my/lists" className="text-reset text-decoration-none">
                        <strong>{user.fans_count}</strong> fans
                      </Link>
                      <span>·</span>
                      <Link
                        href="/my/settings?tab=wallet"
                        className="text-success fw-bold text-decoration-none"
                      >
                        ${user.wallet_balance.toFixed(2)}
                      </Link>
                    </div>
                  </div>
                ) : (
                  <div>
                    <div className="fw-semibold">Guest Viewer</div>
                    <Link href="/login" className="small text-primary text-decoration-none">
                      Sign in or Register →
                    </Link>
                  </div>
                )}
              </div>

              {/* Primary Navigation */}
              <ul className="nav flex-column gap-1">
                {navItems.map((item) => {
                  const isActive =
                    pathname === item.href ||
                    (item.href !== '/' && pathname?.startsWith(item.href.split('?')[0]));
                  return (
                    <li key={item.href} className="nav-item">
                      <Link
                        href={item.href}
                        className={`nav-link rounded-pill px-3 py-2 d-flex align-items-center justify-content-between ${
                          isActive
                            ? 'bg-primary text-white fw-bold'
                            : 'text-reset hover-bg-tertiary'
                        }`}
                      >
                        <span className="d-flex align-items-center gap-2">
                          <span>{item.icon}</span>
                          <span>{item.label}</span>
                        </span>
                        {item.badge && item.badge > 0 ? (
                          <span className="badge rounded-pill bg-danger">
                            {item.badgeLabel || item.badge}
                          </span>
                        ) : null}
                      </Link>
                    </li>
                  );
                })}
                {user?.role_id === 1 && (
                  <li className="nav-item">
                    <Link
                      href="/admin"
                      className={`nav-link rounded-pill px-3 py-2 d-flex align-items-center gap-2 ${
                        pathname === '/admin'
                          ? 'bg-warning text-dark fw-bold'
                          : 'text-warning fw-semibold'
                      }`}
                    >
                      <span>🛡️</span>
                      <span>Admin Panel</span>
                    </Link>
                  </li>
                )}
              </ul>
            </div>

            {/* Footer / Create Actions */}
            <div className="mt-4 pt-3 border-top">
              <div className="d-grid gap-2">
                <Link
                  href="/posts/create"
                  className="btn btn-primary rounded-pill fw-bold py-2"
                >
                  + New Post
                </Link>
                <div className="d-flex gap-2">
                  <Link
                    href="/stories/create"
                    className="btn btn-outline-primary btn-sm rounded-pill flex-fill"
                  >
                    + Story
                  </Link>
                  <Link
                    href="/reels/create"
                    className="btn btn-outline-primary btn-sm rounded-pill flex-fill"
                  >
                    + Reel
                  </Link>
                  <Link
                    href="/my/streams"
                    className="btn btn-outline-danger btn-sm rounded-pill flex-fill"
                  >
                    🔴 Go Live
                  </Link>
                </div>

                {/* More Menu */}
                <div className="position-relative mt-1">
                  <button
                    type="button"
                    onClick={() => setMoreOpen(!moreOpen)}
                    className="btn btn-outline-secondary btn-sm w-100 rounded-pill"
                  >
                    ⋯ More & Settings
                  </button>
                  {moreOpen && (
                    <div
                      className="card shadow position-absolute bottom-100 start-0 w-100 mb-2 p-2 z-3"
                      onClick={() => setMoreOpen(false)}
                    >
                      <Link href="/my/settings" className="dropdown-item py-1 px-2 rounded">
                        ⚙️ Settings & Wallet
                      </Link>
                      <Link href="/pages/help" className="dropdown-item py-1 px-2 rounded">
                        ❓ Help & FAQ
                      </Link>
                      <Link href="/contact" className="dropdown-item py-1 px-2 rounded">
                        ✉️ Contact Support
                      </Link>
                      {user && (
                        <>
                          <hr className="my-1" />
                          <button
                            type="button"
                            onClick={handleLogout}
                            className="dropdown-item py-1 px-2 rounded text-danger fw-semibold"
                          >
                            🚪 Logout
                          </button>
                        </>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </aside>

          {/* Main Content Column */}
          <main className="col-12 col-md-9 col-lg-9 px-0">{children}</main>
        </div>
      </div>

      {/* Mobile Bottom Navigation Bar */}
      <nav className="d-flex d-md-none position-fixed bottom-0 start-0 end-0 border-top bg-body justify-content-around align-items-center py-2 z-3 shadow-sm">
        <Link
          href="/feed"
          className={`text-decoration-none d-flex flex-column align-items-center small ${
            pathname === '/feed' ? 'text-primary fw-bold' : 'text-muted'
          }`}
        >
          <span className="fs-5">🏠</span>
          <span style={{ fontSize: 10 }}>Home</span>
        </Link>
        <Link
          href="/search"
          className={`text-decoration-none d-flex flex-column align-items-center small ${
            pathname === '/search' ? 'text-primary fw-bold' : 'text-muted'
          }`}
        >
          <span className="fs-5">🧭</span>
          <span style={{ fontSize: 10 }}>Explore</span>
        </Link>
        <Link
          href="/posts/create"
          className="btn btn-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm"
          style={{ width: 42, height: 42 }}
          title="New Post"
        >
          +
        </Link>
        <Link
          href="/reels"
          className={`text-decoration-none d-flex flex-column align-items-center small ${
            pathname === '/reels' ? 'text-primary fw-bold' : 'text-muted'
          }`}
        >
          <span className="fs-5">🎬</span>
          <span style={{ fontSize: 10 }}>Reels</span>
        </Link>
        <Link
          href="/my/messenger"
          className={`text-decoration-none d-flex flex-column align-items-center small position-relative ${
            pathname?.startsWith('/my/messenger') ? 'text-primary fw-bold' : 'text-muted'
          }`}
        >
          <span className="fs-5">💬</span>
          {unreadMessages > 0 && (
            <span
              className="badge rounded-pill bg-danger position-absolute top-0 end-0"
              style={{ fontSize: 9 }}
            >
              {unreadMessages}
            </span>
          )}
          <span style={{ fontSize: 10 }}>Chat</span>
        </Link>
        <Link
          href={user ? `/${user.username}` : '/login'}
          className={`text-decoration-none d-flex flex-column align-items-center small ${
            user && pathname === `/${user.username}` ? 'text-primary fw-bold' : 'text-muted'
          }`}
        >
          <span className="fs-5">👤</span>
          <span style={{ fontSize: 10 }}>Profile</span>
        </Link>
      </nav>

      {/* Quick Command Palette Modal (⌘K / Ctrl+K) */}
      {paletteOpen && (
        <div
          className="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-start justify-content-center pt-5 z-3"
          style={{ backgroundColor: 'rgba(0,0,0,0.6)' }}
          onClick={() => setPaletteOpen(false)}
        >
          <div
            className="card shadow-lg rounded-4 border overflow-hidden w-100 mx-3"
            style={{ maxWidth: 540 }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="p-3 border-bottom d-flex align-items-center gap-2">
              <span>🔍</span>
              <input
                type="text"
                autoFocus
                value={paletteQuery}
                onChange={(e) => setPaletteQuery(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    if (filteredCommands.length > 0) {
                      const target = filteredCommands[0];
                      setPaletteOpen(false);
                      setPaletteQuery('');
                      router.push(target.href);
                    } else if (paletteQuery.trim()) {
                      const q = paletteQuery.trim();
                      setPaletteOpen(false);
                      setPaletteQuery('');
                      router.push(`/search?query=${encodeURIComponent(q)}`);
                    }
                  }
                }}
                placeholder="Type a command or page to jump to... (Esc to close)"
                className="form-control border-0 shadow-none"
              />
              <button
                type="button"
                onClick={() => setPaletteOpen(false)}
                className="btn btn-sm btn-outline-secondary rounded-pill"
              >
                Esc
              </button>
            </div>
            <div className="list-group list-group-flush" style={{ maxHeight: 360, overflowY: 'auto' }}>
              {filteredCommands.map((cmd) => (
                <button
                  key={cmd.href}
                  type="button"
                  onClick={() => {
                    setPaletteOpen(false);
                    setPaletteQuery('');
                    router.push(cmd.href);
                  }}
                  className="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3"
                >
                  <span className="d-flex align-items-center gap-2">
                    <span>{cmd.icon}</span>
                    <span className="fw-semibold">{cmd.label}</span>
                  </span>
                  <span className="badge bg-secondary-subtle text-secondary rounded-pill">
                    {cmd.category}
                  </span>
                </button>
              ))}
              {filteredCommands.length === 0 && (
                <div className="p-4 text-center text-muted small">
                  No matching commands. Press Enter in Explore to search creators:
                  <div className="mt-2">
                    <button
                      type="button"
                      onClick={() => {
                        const q = paletteQuery.trim();
                        setPaletteOpen(false);
                        setPaletteQuery('');
                        router.push(`/search?query=${encodeURIComponent(q)}`);
                      }}
                      className="btn btn-sm btn-primary rounded-pill px-3"
                    >
                      Search &ldquo;{paletteQuery}&rdquo; in Explore →
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


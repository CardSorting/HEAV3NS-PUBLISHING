'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, unreadNotifications, unreadMessages, darkMode, toggleDarkMode, logoutUser } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const [moreOpen, setMoreOpen] = useState(false);

  const navItems = [
    { href: '/feed', label: 'Home', icon: '🏠' },
    { href: '/my/notifications', label: 'Notifications', icon: '🔔', badge: unreadNotifications },
    { href: '/my/messenger', label: 'Messages', icon: '💬', badge: unreadMessages },
    { href: '/search', label: 'Explore', icon: '🧭' },
    { href: '/my/vault', label: 'Media Vault', icon: '🖼️' },
    { href: '/reels', label: 'Reels', icon: '🎬' },
    { href: '/my/streams', label: 'Streams', icon: '📡' },
    { href: '/my/bookmarks', label: 'Bookmarks', icon: '🔖' },
    { href: '/my/lists', label: 'Lists', icon: '📋' },
    { href: '/my/settings?tab=subscriptions', label: 'Subscriptions', icon: '💎' },
    {
      href: user ? `/${user.username}` : '/login',
      label: user ? 'My profile' : 'Login',
      icon: '👤',
    },
  ];

  const handleLogout = async () => {
    await logoutUser();
    router.push('/login');
  };

  return (
    <div className={`min-vh-100 d-flex flex-column ${darkMode ? 'bg-dark text-light' : 'bg-body'}`}>
      <div className="container-xl flex-fill">
        <div className="row min-vh-100">
          {/* Left Side Menu */}
          <aside className="col-12 col-md-3 col-lg-3 border-end py-3 px-2 px-lg-3 d-flex flex-column justify-content-between">
            <div>
              {/* Brand & User Header */}
              <div className="d-flex align-items-center justify-content-between mb-4 px-2">
                <Link href="/" className="text-decoration-none d-flex align-items-center gap-2">
                  <img
                    src={darkMode ? '/img/logo-white.png' : '/img/logo-black.png'}
                    alt="JustFans"
                    style={{ height: '32px', objectFit: 'contain' }}
                  />
                </Link>
                <button
                  type="button"
                  onClick={toggleDarkMode}
                  className="btn btn-sm btn-outline-secondary rounded-pill"
                  title="Toggle Light/Dark theme"
                >
                  {darkMode ? '☀️ Light' : '🌙 Dark'}
                </button>
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
                    <div className="small mt-1 d-flex gap-2">
                      <span>
                        <strong>{user.fans_count}</strong> fans
                      </span>
                      <span>·</span>
                      <span>
                        <strong>${user.wallet_balance.toFixed(2)}</strong>
                      </span>
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
                          <span className="badge rounded-pill bg-danger">{item.badge}</span>
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
                        pathname === '/admin' ? 'bg-warning text-dark fw-bold' : 'text-warning fw-semibold'
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
    </div>
  );
}


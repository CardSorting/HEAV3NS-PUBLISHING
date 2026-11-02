'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { apiFetch, getStoredToken, setStoredToken, UserProfile } from '@/lib/api';

interface AuthContextValue {
  user: UserProfile | null;
  loading: boolean;
  unreadNotifications: number;
  unreadMessages: number;
  darkMode: boolean;
  toggleDarkMode: () => void;
  refreshUser: () => Promise<void>;
  loginWithCredentials: (email: string, password: string) => Promise<UserProfile>;
  registerAccount: (name: string, username: string, email: string, password: string) => Promise<UserProfile>;
  logoutUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue>({
  user: null,
  loading: true,
  unreadNotifications: 0,
  unreadMessages: 0,
  darkMode: false,
  toggleDarkMode: () => {},
  refreshUser: async () => {},
  loginWithCredentials: async () => {
    throw new Error('Not initialized');
  },
  registerAccount: async () => {
    throw new Error('Not initialized');
  },
  logoutUser: async () => {},
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [unreadNotifications, setUnreadNotifications] = useState(0);
  const [unreadMessages, setUnreadMessages] = useState(0);
  const [darkMode, setDarkMode] = useState(false);

  const refreshUser = async () => {
    try {
      const data = await apiFetch('/api/auth/me');
      setUser(data.user);
      setUnreadNotifications(data.unread_notifications || 0);
      setUnreadMessages(data.unread_messages || 0);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const savedTheme = typeof window !== 'undefined' ? localStorage.getItem('justfans_theme') : null;
    if (savedTheme === 'dark') {
      setDarkMode(true);
      document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
    refreshUser();
  }, []);

  const toggleDarkMode = () => {
    setDarkMode((prev) => {
      const next = !prev;
      if (typeof window !== 'undefined') {
        localStorage.setItem('justfans_theme', next ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', next ? 'dark' : 'light');
      }
      return next;
    });
  };

  const loginWithCredentials = async (email: string, password: string) => {
    const data = await apiFetch('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    if (data.token) {
      setStoredToken(data.token);
    }
    setUser(data.user);
    await refreshUser();
    return data.user as UserProfile;
  };

  const registerAccount = async (
    name: string,
    username: string,
    email: string,
    password: string
  ) => {
    const data = await apiFetch('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify({ name, username, email, password }),
    });
    if (data.token) {
      setStoredToken(data.token);
    }
    setUser(data.user);
    await refreshUser();
    return data.user as UserProfile;
  };

  const logoutUser = async () => {
    try {
      await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch {
      // ignore
    }
    setStoredToken(null);
    setUser(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        unreadNotifications,
        unreadMessages,
        darkMode,
        toggleDarkMode,
        refreshUser,
        loginWithCredentials,
        registerAccount,
        logoutUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}


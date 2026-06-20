'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import LocaleSwitcher from './LocaleSwitcher';
import UserMenuDropdown from './UserMenuDropdown';
import MobileNavPanel from './MobileNavPanel';
import { useAuth } from '@/lib/hooks/useAuth';

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  const t = useTranslations('nav');
  const travelerT = useTranslations('traveler.nav');
  const { user, isLoading, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur-sm">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-md focus:bg-[#0A2540] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
      >
        {t('skipToContent')}
      </a>
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <Link href={`/${locale}`} className="text-xl font-bold text-[#0A2540]">
          Bookly
        </Link>

        <nav className="hidden sm:flex items-center gap-6" aria-label={t('mainNavigation')}>
          <Link href={`/${locale}`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            {t('home')}
          </Link>
          <Link href={`/${locale}/search`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            {t('search')}
          </Link>
          <Link href={`/${locale}/categories`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            {t('categories')}
          </Link>
          <Link href={`/${locale}/destinations`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            {t('destinations')}
          </Link>
        </nav>

        <div className="flex items-center gap-3">
          <LocaleSwitcher />
          {!isLoading && (
            user ? (
              <div className="relative">
                <button
                  onClick={() => setMenuOpen((open) => !open)}
                  className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-[#0A2540] hover:bg-gray-50"
                  aria-haspopup="menu"
                  aria-expanded={menuOpen}
                >
                  <span className="hidden md:inline">{user.name}</span>
                  <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[#0A2540] text-xs font-semibold text-white">
                    {user.name.charAt(0).toUpperCase()}
                  </span>
                </button>
                <UserMenuDropdown
                  locale={locale}
                  userName={user.name}
                  isOpen={menuOpen}
                  onClose={() => setMenuOpen(false)}
                  onLogout={logout}
                />
              </div>
            ) : (
              <div className="hidden sm:flex items-center gap-2">
                <Link
                  href={`/${locale}/auth/login`}
                  className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors"
                >
                  {travelerT('signIn')}
                </Link>
                <Link
                  href={`/${locale}/auth/register`}
                  className="rounded-xl bg-[#FFB800] px-3 py-1.5 text-sm font-medium text-[#0A2540] hover:bg-[#e6a600] transition-colors"
                >
                  {travelerT('signUp')}
                </Link>
              </div>
            )
          )}
          <button
            onClick={() => setMobileOpen(true)}
            className="sm:hidden rounded-md p-2 text-[#5A6B7B] hover:bg-gray-100"
            aria-label={t('openMenu')}
            aria-expanded={mobileOpen}
          >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>

      <MobileNavPanel
        locale={locale}
        userName={user?.name}
        isAuthenticated={Boolean(user)}
        isOpen={mobileOpen}
        onClose={() => setMobileOpen(false)}
        onLogout={logout}
      />
    </header>
  );
}

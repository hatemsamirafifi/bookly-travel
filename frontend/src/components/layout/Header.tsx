'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import LocaleSwitcher from './LocaleSwitcher';
import { useAuth } from '@/lib/hooks/useAuth';

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  const t = useTranslations('nav');
  const travelerT = useTranslations('traveler.nav');
  const { user, isLoading, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);

  const travelerLinks = [
    { href: `/${locale}/my-bookings`, label: travelerT('myBookings') },
    { href: `/${locale}/wishlist`, label: travelerT('wishlist') },
    { href: `/${locale}/my-reviews`, label: travelerT('myReviews') },
    { href: `/${locale}/profile`, label: travelerT('profile') },
  ];

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
                {menuOpen && (
                  <div
                    className="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
                    role="menu"
                  >
                    <Link
                      href={`/${locale}/my-bookings`}
                      className="block rounded-md px-3 py-2 text-sm font-semibold text-[#0A2540] hover:bg-gray-50"
                      role="menuitem"
                      onClick={() => setMenuOpen(false)}
                    >
                      {travelerT('dashboard')}
                    </Link>
                    {travelerLinks.map((link) => (
                      <Link
                        key={link.href}
                        href={link.href}
                        className="block rounded-md px-3 py-2 text-sm text-[#5A6B7B] hover:bg-gray-50 hover:text-[#0A2540]"
                        role="menuitem"
                        onClick={() => setMenuOpen(false)}
                      >
                        {link.label}
                      </Link>
                    ))}
                    <button
                      onClick={() => {
                        setMenuOpen(false);
                        void logout();
                      }}
                      className="mt-1 w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                      role="menuitem"
                    >
                      {travelerT('signOut')}
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <div className="flex items-center gap-2">
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
            className="sm:hidden rounded-md p-2 text-[#5A6B7B] hover:bg-gray-100"
            aria-label={t('openMenu')}
            aria-expanded="false"
          >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </header>
  );
}

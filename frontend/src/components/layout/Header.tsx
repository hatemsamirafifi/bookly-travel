'use client';

import Link from 'next/link';
import LocaleSwitcher from './LocaleSwitcher';
import { useAuth } from '@/lib/hooks/useAuth';

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  const { user, isLoading, logout } = useAuth();

  return (
    <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur-sm">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-md focus:bg-[#0A2540] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
      >
        Skip to main content
      </a>
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <Link href={`/${locale}`} className="text-xl font-bold text-[#0A2540]">
          Bookly
        </Link>

        <nav className="hidden sm:flex items-center gap-6" aria-label="Main navigation">
          <Link href={`/${locale}`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            Home
          </Link>
          <Link href={`/${locale}/search`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            Search
          </Link>
          <Link href={`/${locale}/categories`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            Categories
          </Link>
          <Link href={`/${locale}/destinations`} className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors">
            Destinations
          </Link>
        </nav>

        <div className="flex items-center gap-3">
          <LocaleSwitcher />
          {!isLoading && (
            user ? (
              <div className="flex items-center gap-3">
                <span className="hidden md:inline text-sm text-[#5A6B7B]">{user.name}</span>
                <button
                  onClick={logout}
                  className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors"
                >
                  Sign Out
                </button>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <Link
                  href={`/${locale}/auth/login`}
                  className="text-sm text-[#5A6B7B] hover:text-[#0A2540] transition-colors"
                >
                  Sign In
                </Link>
                <Link
                  href={`/${locale}/auth/register`}
                  className="rounded-xl bg-[#FFB800] px-3 py-1.5 text-sm font-medium text-[#0A2540] hover:bg-[#e6a600] transition-colors"
                >
                  Sign Up
                </Link>
              </div>
            )
          )}
          <button
            className="sm:hidden rounded-md p-2 text-[#5A6B7B] hover:bg-gray-100"
            aria-label="Open menu"
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

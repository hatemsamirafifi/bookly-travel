'use client';

import { useState, useRef, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/lib/hooks/useAuth';
import { NotificationBell } from '@/components/partner/layout/NotificationBell';

interface PartnerHeaderProps {
  onMenuClick: () => void;
}

function MenuIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  );
}

function ChevronDownIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
    </svg>
  );
}

function LogoutIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
    </svg>
  );
}

export function PartnerHeader({ onMenuClick }: PartnerHeaderProps) {
  const t = useTranslations('partner.header');
  const { user, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setMenuOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <header className="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:px-6">
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={onMenuClick}
          className="inline-flex items-center justify-center rounded-lg p-2 text-gray-600 hover:bg-gray-100 md:hidden"
          aria-label={t('menuLabel', { defaultValue: 'Open navigation menu' })}
        >
          <MenuIcon className="h-6 w-6" />
        </button>
        <h2 className="text-base font-semibold text-[#0A2540]">
          {t('title', { defaultValue: 'Partner Dashboard' })}
        </h2>
      </div>

      <div className="flex items-center gap-3">
        {/*
          Spec 014 (FR-017, SC-007): live unread indicator. NotificationBell
          polls /api/partner/notifications (60s + on visibility change) via
          usePartnerRealtime, so the badge reflects the true unread count —
          never a static zero.
        */}
        <NotificationBell />

        <div className="relative" ref={menuRef}>
          <button
            type="button"
            onClick={() => setMenuOpen((prev) => !prev)}
            className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100"
            aria-expanded={menuOpen}
            aria-haspopup="true"
          >
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[#0A2540] text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'P'}
            </div>
            <span className="hidden text-sm font-medium text-gray-700 md:inline">
              {user?.name ?? t('guest', { defaultValue: 'Partner' })}
            </span>
            <ChevronDownIcon className="hidden h-4 w-4 text-gray-500 md:block" />
          </button>

          {menuOpen && (
            <div className="absolute right-0 mt-2 w-48 origin-top-right rounded-xl border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5">
              <button
                type="button"
                onClick={() => {
                  setMenuOpen(false);
                  logout();
                }}
                className="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
              >
                <LogoutIcon className="h-4 w-4 text-gray-500" />
                {t('logout', { defaultValue: 'Sign out' })}
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

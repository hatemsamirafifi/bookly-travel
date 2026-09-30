'use client';

import { useState, useRef, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/lib/hooks/useAuth';
import { NotificationBell } from '@/components/partner/layout/NotificationBell';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/IconButton';

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
  const nav = useTranslations('nav');
  const { user, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const logoutRef = useRef<HTMLButtonElement>(null);

  useEffect(() => { if (menuOpen) logoutRef.current?.focus(); }, [menuOpen]);

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
    <header className="flex h-16 items-center justify-between border-b border-border bg-surface px-4 lg:px-6">
      <div className="flex items-center gap-3">
        <IconButton
          onClick={onMenuClick}
          className="text-text-muted md:hidden"
          label={t('menuLabel')}
        >
          <MenuIcon className="h-6 w-6" />
        </IconButton>
        <h2 className="text-base font-semibold text-primary">
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
          <Button
            ref={triggerRef}
            type="button"
            variant="ghost"
            onClick={() => setMenuOpen((prev) => !prev)}
            className="flex items-center gap-2 rounded-lg p-1.5 hover:bg-surface-alt"
            aria-expanded={menuOpen}
            aria-haspopup="menu"
            aria-label={nav('accountMenu')}
          >
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-text-inverse text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'P'}
            </div>
            <span className="hidden text-sm font-medium text-primary md:inline">
              {user?.name ?? t('guest', { defaultValue: 'Partner' })}
            </span>
            <ChevronDownIcon className="hidden h-4 w-4 text-text-muted md:block" />
          </Button>

          {menuOpen && (
            <div role="menu" aria-label={nav('accountMenu')} className="absolute right-0 z-dropdown mt-2 w-48 origin-top-right rounded-xl border border-border bg-surface py-1 shadow-dropdown" onBlur={(event) => { if (!menuRef.current?.contains(event.relatedTarget as Node)) setMenuOpen(false); }} onKeyDown={(event) => {
              if (event.key === 'Escape') { event.preventDefault(); setMenuOpen(false); triggerRef.current?.focus(); }
              if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) { event.preventDefault(); logoutRef.current?.focus(); }
            }}>
              <Button
                ref={logoutRef}
                type="button"
                variant="ghost"
                role="menuitem"
                tabIndex={-1}
                onClick={() => {
                  setMenuOpen(false);
                  logout();
                }}
                className="w-full justify-start px-4 text-sm"
              >
                <LogoutIcon className="h-4 w-4 text-text-muted" />
                {t('logout', { defaultValue: 'Sign out' })}
              </Button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}

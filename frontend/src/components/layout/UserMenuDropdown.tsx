'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { useEffect, useRef } from 'react';

interface UserMenuDropdownProps {
  locale: string;
  userName: string;
  userRole?: 'traveler' | 'partner' | 'admin';
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
}

export default function UserMenuDropdown({
  locale,
  userName,
  isOpen,
  userRole,
  onClose,
  onLogout,
}: UserMenuDropdownProps) {
  const travelerT = useTranslations('traveler.nav');
  const partnerT = useTranslations('partner.nav');
  const menuRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (isOpen) menuRef.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
  }, [isOpen]);

  if (!isOpen) return null;

  const isPartner = userRole === 'partner';

  const partnerLinks = [
    { href: `/${locale}/partner/tours`, label: partnerT('tours') },
    { href: `/${locale}/partner/bookings`, label: partnerT('bookings') },
    { href: `/${locale}/partner/reviews`, label: partnerT('reviews') },
    { href: `/${locale}/partner/profile`, label: partnerT('profile') },
  ];

  const travelerLinks = [
    { href: `/${locale}/my-bookings`, label: travelerT('myBookings') },
    { href: `/${locale}/wishlist`, label: travelerT('wishlist') },
    { href: `/${locale}/my-reviews`, label: travelerT('myReviews') },
    { href: `/${locale}/profile`, label: travelerT('profile') },
  ];

  const dashboardHref = isPartner ? `/${locale}/partner` : `/${locale}/my-bookings`;
  const dashboardLabel = isPartner ? partnerT('dashboard') : travelerT('dashboard');
  const links = isPartner ? partnerLinks : travelerLinks;
  const signOutLabel = isPartner ? partnerT('signOut') : travelerT('signOut');

  return (
    <div
      ref={menuRef}
      className="absolute right-0 z-dropdown mt-2 w-56 rounded-lg border border-border bg-surface p-2 shadow-dropdown"
      role="menu"
      aria-label={userName}
      onKeyDown={(event) => {
        if (event.key === 'Escape') { event.preventDefault(); onClose(); return; }
        const items = Array.from(event.currentTarget.querySelectorAll<HTMLElement>('[role="menuitem"]'));
        const index = items.indexOf(document.activeElement as HTMLElement);
        const next = event.key === 'ArrowDown' ? (index + 1) % items.length : event.key === 'ArrowUp' ? (index - 1 + items.length) % items.length : event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : undefined;
        if (next !== undefined) { event.preventDefault(); items[next]?.focus(); }
      }}
    >
      <Link
        href={dashboardHref}
        className="flex min-h-11 items-center rounded-md px-3 py-2 text-sm font-semibold text-primary hover:bg-surface-alt"
        role="menuitem"
        tabIndex={-1}
        onClick={onClose}
      >
        {dashboardLabel}
      </Link>
      {links.map((link) => (
        <Link
          key={link.href}
          href={link.href}
          className="flex min-h-11 items-center rounded-md px-3 py-2 text-sm text-text-muted hover:bg-surface-alt hover:text-primary"
          role="menuitem"
          tabIndex={-1}
          onClick={onClose}
        >
          {link.label}
        </Link>
      ))}
      <button
        onClick={() => {
          onClose();
          void onLogout();
        }}
        className="mt-1 min-h-11 w-full rounded-md px-3 py-2 text-left text-sm text-error hover:bg-surface-alt"
        role="menuitem"
        tabIndex={-1}
      >
        {signOutLabel}
      </button>
    </div>
  );
}

'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';

interface MobileNavPanelProps {
  locale: string;
  userName?: string;
  isAuthenticated: boolean;
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
}

export default function MobileNavPanel({
  locale,
  userName,
  isAuthenticated,
  isOpen,
  onClose,
  onLogout,
}: MobileNavPanelProps) {
  const t = useTranslations('nav');
  const travelerT = useTranslations('traveler.nav');

  if (!isOpen) return null;

  const mainLinks = [
    { href: `/${locale}`, label: t('home') },
    { href: `/${locale}/search`, label: t('search') },
    { href: `/${locale}/categories`, label: t('categories') },
    { href: `/${locale}/destinations`, label: t('destinations') },
  ];

  const travelerLinks = [
    { href: `/${locale}/my-bookings`, label: travelerT('dashboard') },
    { href: `/${locale}/my-bookings`, label: travelerT('myBookings') },
    { href: `/${locale}/wishlist`, label: travelerT('wishlist') },
    { href: `/${locale}/my-reviews`, label: travelerT('myReviews') },
    { href: `/${locale}/profile`, label: travelerT('profile') },
  ];

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-black/40 sm:hidden">
      <div className="flex h-full w-72 flex-col bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-gray-200 p-4">
          <span className="text-lg font-bold text-[#0A2540]">Bookly</span>
          <button onClick={onClose} className="rounded-md p-2 text-gray-500 hover:bg-gray-100" aria-label={t('closeMenu')}>
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto p-4">
          <ul className="space-y-1">
            {mainLinks.map((link) => (
              <li key={link.href}>
                <Link href={link.href} className="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" onClick={onClose}>
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>

          {isAuthenticated && (
            <>
              <div className="my-3 border-t border-gray-200" />
              <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{userName}</p>
              <ul className="space-y-1">
                {travelerLinks.map((link) => (
                  <li key={`${link.href}-${link.label}`}>
                    <Link href={link.href} className="block rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" onClick={onClose}>
                      {link.label}
                    </Link>
                  </li>
                ))}
                <li>
                  <button
                    onClick={() => {
                      onClose();
                      void onLogout();
                    }}
                    className="w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                  >
                    {travelerT('signOut')}
                  </button>
                </li>
              </ul>
            </>
          )}

          {!isAuthenticated && (
            <>
              <div className="my-3 border-t border-gray-200" />
              <div className="space-y-2 px-3">
                <Link href={`/${locale}/auth/login`} className="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" onClick={onClose}>
                  {travelerT('signIn')}
                </Link>
                <Link href={`/${locale}/auth/register`} className="block rounded-lg bg-[#FFB800] px-3 py-2 text-center text-sm font-medium text-[#0A2540]" onClick={onClose}>
                  {travelerT('signUp')}
                </Link>
              </div>
            </>
          )}
        </nav>
      </div>
    </div>
  );
}

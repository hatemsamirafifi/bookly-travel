'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';

interface UserMenuDropdownProps {
  locale: string;
  userName: string;
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
}

export default function UserMenuDropdown({ locale, isOpen, onClose, onLogout }: UserMenuDropdownProps) {
  const travelerT = useTranslations('traveler.nav');

  const travelerLinks = [
    { href: `/${locale}/my-bookings`, label: travelerT('myBookings') },
    { href: `/${locale}/wishlist`, label: travelerT('wishlist') },
    { href: `/${locale}/my-reviews`, label: travelerT('myReviews') },
    { href: `/${locale}/profile`, label: travelerT('profile') },
  ];

  if (!isOpen) return null;

  return (
    <div
      className="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
      role="menu"
    >
      <Link
        href={`/${locale}/my-bookings`}
        className="block rounded-md px-3 py-2 text-sm font-semibold text-[#0A2540] hover:bg-gray-50"
        role="menuitem"
        onClick={onClose}
      >
        {travelerT('dashboard')}
      </Link>
      {travelerLinks.map((link) => (
        <Link
          key={link.href}
          href={link.href}
          className="block rounded-md px-3 py-2 text-sm text-[#5A6B7B] hover:bg-gray-50 hover:text-[#0A2540]"
          role="menuitem"
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
        className="mt-1 w-full rounded-md px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
        role="menuitem"
      >
        {travelerT('signOut')}
      </button>
    </div>
  );
}

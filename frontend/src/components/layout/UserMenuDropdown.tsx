'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';

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
  isOpen,
  userRole,
  onClose,
  onLogout,
}: UserMenuDropdownProps) {
  const travelerT = useTranslations('traveler.nav');
  const partnerT = useTranslations('partner.nav');

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
      className="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-lg"
      role="menu"
    >
      <Link
        href={dashboardHref}
        className="block rounded-md px-3 py-2 text-sm font-semibold text-[#0A2540] hover:bg-gray-50"
        role="menuitem"
        onClick={onClose}
      >
        {dashboardLabel}
      </Link>
      {links.map((link) => (
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
        {signOutLabel}
      </button>
    </div>
  );
}

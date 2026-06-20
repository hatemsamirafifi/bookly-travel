'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { useTranslations } from 'next-intl';

interface PartnerSidebarProps {
  onNavigate?: () => void;
}

const navItems = [
  {
    key: 'dashboard',
    href: '/partner',
    icon: DashboardIcon,
  },
  {
    key: 'tours',
    href: '/partner/tours',
    icon: ToursIcon,
  },
  {
    key: 'bookings',
    href: '/partner/bookings',
    icon: BookingsIcon,
  },
  {
    key: 'reviews',
    href: '/partner/reviews',
    icon: ReviewsIcon,
  },
  {
    key: 'profile',
    href: '/partner/profile',
    icon: ProfileIcon,
  },
];

function DashboardIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
    </svg>
  );
}

function ToursIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  );
}

function BookingsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  );
}

function ReviewsIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
    </svg>
  );
}

function ProfileIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  );
}

export function PartnerSidebar({ onNavigate }: PartnerSidebarProps) {
  const pathname = usePathname();
  const t = useTranslations('partner.nav');

  const isActive = (href: string) => {
    if (href === '/partner') {
      return pathname === href || pathname === `${href}/`;
    }
    return pathname.startsWith(href);
  };

  return (
    <nav className="flex h-full flex-col" aria-label={t('sidebarLabel', { defaultValue: 'Partner dashboard navigation' })}>
      <div className="flex items-center gap-3 px-5 py-5">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#FFB800] text-[#0A2540] font-bold text-sm">
          B
        </div>
        <span className="text-lg font-semibold text-white tracking-tight">
          {t('appName', { defaultValue: 'Bookly' })}
        </span>
      </div>

      <div className="flex-1 overflow-y-auto px-3 py-2">
        <ul className="space-y-1" role="menubar">
          {navItems.map((item) => {
            const active = isActive(item.href);
            return (
              <li key={item.key} role="none">
                <Link
                  href={item.href}
                  onClick={onNavigate}
                  role="menuitem"
                  aria-current={active ? 'page' : undefined}
                  className={[
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                    active
                      ? 'bg-[#FFB800] text-[#0A2540]'
                      : 'text-gray-300 hover:bg-white/10 hover:text-white',
                  ].join(' ')}
                >
                  <item.icon className="h-5 w-5 shrink-0" />
                  {t(item.key, { defaultValue: item.key })}
                </Link>
              </li>
            );
          })}
        </ul>
      </div>

      <div className="border-t border-white/10 px-3 py-4">
        <p className="px-3 text-xs text-gray-400">
          {t('version', { defaultValue: 'v0.1.0' })}
        </p>
      </div>
    </nav>
  );
}

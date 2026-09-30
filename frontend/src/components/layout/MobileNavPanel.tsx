'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { Drawer } from '@/components/ui/Drawer';
import { Button } from '@/components/ui/button';
import LocaleSwitcher from './LocaleSwitcher';

interface MobileNavPanelProps {
  locale: string;
  userName?: string;
  userRole?: 'traveler' | 'partner' | 'admin';
  isAuthenticated: boolean;
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
}

export default function MobileNavPanel({ locale, userName, userRole, isAuthenticated, isOpen, onClose, onLogout }: MobileNavPanelProps) {
  const t = useTranslations('nav');
  const travelerT = useTranslations('traveler.nav');
  const partnerT = useTranslations('partner.nav');
  const isPartner = userRole === 'partner';
  const mainLinks = [{ href: `/${locale}`, label: t('home') }, { href: `/${locale}/search`, label: t('search') }, { href: `/${locale}/categories`, label: t('categories') }, { href: `/${locale}/destinations`, label: t('destinations') }];
  const partnerLinks = ['dashboard', 'tours', 'bookings', 'reviews', 'profile'].map((key) => ({ href: `/${locale}/partner${key === 'dashboard' ? '' : `/${key}`}`, label: partnerT(key) }));
  const travelerLinks = [{ href: `/${locale}/my-bookings`, label: travelerT('dashboard') }, { href: `/${locale}/my-bookings`, label: travelerT('myBookings') }, { href: `/${locale}/wishlist`, label: travelerT('wishlist') }, { href: `/${locale}/my-reviews`, label: travelerT('myReviews') }, { href: `/${locale}/profile`, label: travelerT('profile') }];
  const linkClass = 'flex min-h-11 items-center rounded-md px-3 py-2 text-sm text-primary hover:bg-surface-alt';
  return <Drawer open={isOpen} onClose={onClose} title={t('mainNavigation')} closeLabel={t('closeMenu')}>
    <nav aria-label={t('mainNavigation')}>
      <ul className="space-y-1">{mainLinks.map((link) => <li key={link.href}><Link href={link.href} className={linkClass} onClick={onClose}>{link.label}</Link></li>)}</ul>
      <div className="my-4 border-t border-border" />
      <LocaleSwitcher />
      <div className="my-4 border-t border-border" />
      {isAuthenticated ? <>
        <p className="mb-2 px-3 text-sm font-semibold text-text-muted">{userName}</p>
        <ul className="space-y-1">{(isPartner ? partnerLinks : travelerLinks).map((link) => <li key={`${link.href}-${link.label}`}><Link href={link.href} className={linkClass} onClick={onClose}>{link.label}</Link></li>)}</ul>
        <Button type="button" variant="ghost" className="mt-2 w-full justify-start text-error" onClick={() => { onClose(); void onLogout(); }}>{isPartner ? partnerT('signOut') : travelerT('signOut')}</Button>
      </> : <div className="space-y-2"><Link href={`/${locale}/auth/login`} className={linkClass} onClick={onClose}>{travelerT('signIn')}</Link><Link href={`/${locale}/auth/register`} className="flex min-h-11 items-center justify-center rounded-full bg-accent px-3 py-2 text-sm font-semibold text-primary" onClick={onClose}>{travelerT('signUp')}</Link></div>}
    </nav>
  </Drawer>;
}

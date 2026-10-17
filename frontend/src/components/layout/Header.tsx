'use client';

import { useEffect, useId, useRef, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import { Heart, Menu, Search, UserRound } from 'lucide-react';
import { useTranslations } from 'next-intl';
import LocaleSwitcher from './LocaleSwitcher';
import UserMenuDropdown from './UserMenuDropdown';
import MobileNavPanel from './MobileNavPanel';
import { Container } from '@/components/ui/Container';
import { IconButton } from '@/components/ui/IconButton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/lib/hooks/useAuth';

export default function Header({ locale }: { locale: string }) {
  const pathname = usePathname();
  const t = useTranslations('nav');
  const travelerT = useTranslations('traveler.nav');
  const { user, isLoading, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const searchId = useId();
  const searchRef = useRef<HTMLInputElement>(null);
  const searchButtonRef = useRef<HTMLButtonElement>(null);
  const accountRef = useRef<HTMLButtonElement>(null);
  const accountGroupRef = useRef<HTMLDivElement>(null);

  useEffect(() => { if (searchOpen) searchRef.current?.focus(); }, [searchOpen]);
  useEffect(() => {
    if (!menuOpen) return;
    const closeOutside = (event: PointerEvent) => {
      if (!accountGroupRef.current?.contains(event.target as Node)) setMenuOpen(false);
    };
    const focusOutside = (event: FocusEvent) => {
      if (!accountGroupRef.current?.contains(event.target as Node)) setMenuOpen(false);
    };
    document.addEventListener('pointerdown', closeOutside);
    document.addEventListener('focusin', focusOutside);
    return () => {
      document.removeEventListener('pointerdown', closeOutside);
      document.removeEventListener('focusin', focusOutside);
    };
  }, [menuOpen]);

  const closeAccount = () => { setMenuOpen(false); accountRef.current?.focus(); };
  const links = [
    { href: `/${locale}`, label: t('home') },
    { href: `/${locale}/search`, label: t('searchTours') },
    { href: `/${locale}/categories`, label: t('categories') },
    { href: `/${locale}/destinations`, label: t('destinations') },
  ];
  return (
    <header className="relative z-sticky border-b border-border bg-surface">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-dropdown focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-text-inverse">{t('skipToContent')}</a>
      <Container className="flex min-h-16 items-center justify-between gap-4 py-2 xl:min-h-20 xl:py-4">
        <Link href={`/${locale}`} aria-label="Bookly" className="inline-flex min-h-11 shrink-0 items-center rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
          <Image
            src="/brand/bookly-travel-logo.webp"
            alt=""
            width={1453}
            height={308}
            className="h-auto w-32 sm:w-40 xl:w-44"
            loading="eager"
            unoptimized
          />
        </Link>
        <nav className="hidden items-center gap-6 xl:flex" aria-label={t('mainNavigation')}>
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              aria-current={pathname === link.href ? 'page' : undefined}
              className="inline-flex min-h-11 items-center rounded-sm border-b-2 border-transparent px-1 text-sm font-medium text-text-muted transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus aria-[current=page]:border-accent aria-[current=page]:font-semibold aria-[current=page]:text-primary"
            >
              {link.label}
            </Link>
          ))}
        </nav>
        <div className="flex shrink-0 items-center gap-1 sm:gap-2 xl:border-l xl:border-border xl:pl-5">
          <button ref={searchButtonRef} type="button" aria-label={t('searchTours')} aria-expanded={searchOpen} aria-controls={searchId} onClick={() => setSearchOpen((open) => !open)} className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-primary transition-colors hover:bg-surface-alt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus aria-expanded:bg-surface-alt"><Search className="size-5" aria-hidden="true" /></button>
          <div className="hidden md:block"><LocaleSwitcher /></div>
          {!isLoading && (user ? <>
            {user.role === 'traveler' && <Link href={`/${locale}/wishlist`} aria-label={travelerT('wishlist')} className="hidden min-h-11 min-w-11 items-center justify-center rounded-full text-primary hover:bg-surface-alt sm:inline-flex"><Heart className="size-5" aria-hidden="true" /></Link>}
            <div ref={accountGroupRef} className="relative"><button ref={accountRef} type="button" onClick={() => setMenuOpen((open) => !open)} aria-label={t('accountMenu')} aria-haspopup="menu" aria-expanded={menuOpen} className="flex min-h-11 min-w-11 items-center gap-2 rounded-full border border-border px-3 text-sm text-primary hover:bg-surface-alt"><UserRound className="size-5" aria-hidden="true" /><span className="hidden max-w-32 truncate md:inline">{user.name}</span></button><UserMenuDropdown locale={locale} userName={user.name} userRole={user.role} isOpen={menuOpen} onClose={closeAccount} onLogout={logout} /></div>
          </> : <><Link href={`/${locale}/auth/login`} aria-label={travelerT('signIn')} className="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-full px-2 text-sm font-semibold text-primary hover:bg-surface-alt"><UserRound className="size-5 sm:hidden" aria-hidden="true" /><span className="hidden sm:inline">{travelerT('signIn')}</span></Link><Link href={`/${locale}/auth/register`} className="hidden min-h-11 items-center rounded-full bg-accent px-4 text-sm font-semibold text-primary hover:bg-accent-dark md:inline-flex">{travelerT('signUp')}</Link></>)}
          <IconButton label={t('openMenu')} className="rounded-full xl:hidden" aria-expanded={mobileOpen} onClick={() => setMobileOpen(true)}><Menu className="size-5" aria-hidden="true" /></IconButton>
        </div>
      </Container>
      {searchOpen && <Container className="pb-4"><form id={searchId} role="search" aria-label={t('searchTours')} action={`/${locale}/search`} className="flex gap-2" onKeyDown={(event) => { if (event.key === 'Escape') { event.preventDefault(); setSearchOpen(false); searchButtonRef.current?.focus(); } }}><Input ref={searchRef} type="search" name="q" aria-label={t('searchTours')} placeholder={t('searchPlaceholder')} /><Button type="submit">{t('search')}</Button></form></Container>}
      <MobileNavPanel locale={locale} userName={user?.name} userRole={user?.role} isAuthenticated={Boolean(user)} isOpen={mobileOpen} onClose={() => setMobileOpen(false)} onLogout={logout} />
    </header>
  );
}

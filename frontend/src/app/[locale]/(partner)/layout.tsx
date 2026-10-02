'use client';

import { useRef, useState } from 'react';
import { PartnerAuthGuard } from '@/components/auth/PartnerAuthGuard';
import { PartnerSidebar } from '@/components/partner/layout/PartnerSidebar';
import { PartnerHeader } from '@/components/partner/layout/PartnerHeader';
import { MobileDrawer } from '@/components/partner/layout/MobileDrawer';
import { useTranslations } from 'next-intl';
import { Container } from '@/components/ui/Container';

export default function PartnerLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const menuTriggerRef = useRef<HTMLElement | null>(null);
  const nav = useTranslations('nav');
  const closeMobileMenu = () => {
    setMobileOpen(false);
    requestAnimationFrame(() => menuTriggerRef.current?.focus());
  };

  return (
    <PartnerAuthGuard>
      <div className="flex h-screen bg-surface-alt">
        <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-[60] focus:rounded-md focus:bg-bookly-navy focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
          {nav('skipToContent')}
        </a>
        {/* Desktop sidebar */}
        <aside
          className="hidden w-60 shrink-0 flex-col bg-bookly-navy md:flex"
          aria-label={nav('mainNavigation')}
        >
          <PartnerSidebar />
        </aside>

        {/* Mobile drawer */}
        <MobileDrawer isOpen={mobileOpen} onClose={closeMobileMenu} />

        <div className="flex min-w-0 flex-1 flex-col">
          <PartnerHeader
            onMenuClick={() => {
              menuTriggerRef.current = document.activeElement as HTMLElement;
              setMobileOpen(true);
            }}
          />
          <main
            id="main-content"
            tabIndex={-1}
            className="flex-1 overflow-y-auto p-4 md:p-6"
            role="main"
            aria-label={nav('skipToContent')}
          >
            <Container className="px-0">{children}</Container>
          </main>
        </div>
      </div>
    </PartnerAuthGuard>
  );
}

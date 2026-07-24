'use client';

import { useState } from 'react';
import { PartnerAuthGuard } from '@/components/auth/PartnerAuthGuard';
import { PartnerSidebar } from '@/components/partner/layout/PartnerSidebar';
import { PartnerHeader } from '@/components/partner/layout/PartnerHeader';
import { MobileDrawer } from '@/components/partner/layout/MobileDrawer';

export default function PartnerLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <PartnerAuthGuard>
      <div className="flex h-screen bg-gray-50">
        {/* Desktop sidebar */}
        <aside
          className="hidden w-60 shrink-0 flex-col bg-[#0A2540] md:flex"
          aria-label="Partner dashboard sidebar"
        >
          <PartnerSidebar />
        </aside>

        {/* Mobile drawer */}
        <MobileDrawer isOpen={mobileOpen} onClose={() => setMobileOpen(false)} />

        <div className="flex min-w-0 flex-1 flex-col">
          <PartnerHeader
            onMenuClick={() => setMobileOpen(true)}
          />
          <main
            id="main-content"
            className="flex-1 overflow-y-auto p-4 md:p-6"
            role="main"
            aria-label="Partner dashboard content"
          >
            <div className="mx-auto max-w-7xl">{children}</div>
          </main>
        </div>
      </div>
    </PartnerAuthGuard>
  );
}

'use client';

import { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useLocale } from 'next-intl';
import { useAuth } from '@/lib/hooks/useAuth';

interface PartnerAuthGuardProps {
  children: React.ReactNode;
}

export function PartnerAuthGuard({ children }: PartnerAuthGuardProps) {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const locale = useLocale();

  useEffect(() => {
    if (!isLoading) {
      if (!user) {
        const params = new URLSearchParams();
        params.set('returnUrl', pathname);
        router.push(`/${locale}/auth/login?${params.toString()}`);
      } else if (user.role !== 'partner') {
        // Redirect non-partners to the public home page
        router.push(`/${locale}/`);
      }
    }
  }, [user, isLoading, router, pathname, locale]);

  if (isLoading) {
    return (
      <div className="flex h-screen w-full items-center justify-center bg-gray-50">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-[#0A2540] border-t-transparent"></div>
      </div>
    );
  }

  if (!user || user.role !== 'partner') {
    return null;
  }

  return <>{children}</>;
}

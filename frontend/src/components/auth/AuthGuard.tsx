'use client';

import { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useLocale } from 'next-intl';
import { useAuth } from '@/lib/hooks/useAuth';

interface AuthGuardProps {
  children: React.ReactNode;
  requireAuth?: boolean;
}

export function AuthGuard({ children, requireAuth = true }: AuthGuardProps) {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const locale = useLocale();

  useEffect(() => {
    if (!isLoading) {
      if (requireAuth && !user) {
        // Build return url
        const params = new URLSearchParams();
        params.set('returnUrl', pathname);
        router.push(`/${locale}/auth/login?${params.toString()}`);
      } else if (!requireAuth && user) {
        // Redirect to account if user is already logged in but trying to access guest routes
        router.push(`/${locale}/`);
      }
    }
  }, [user, isLoading, requireAuth, router, pathname, locale]);

  if (isLoading) {
    return (
      <div className="flex h-screen w-full items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    );
  }

  // Prevent flash of protected content while redirecting
  if ((requireAuth && !user) || (!requireAuth && user)) {
    return null;
  }

  return <>{children}</>;
}

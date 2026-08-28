'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useTranslations, useLocale } from 'next-intl';

interface BlogUnavailableProps {
  status?: number;
  retryAfterSeconds?: number;
  onRetry?: () => void;
  message?: string;
  isRemoved?: boolean;
}

export function BlogUnavailable({
  status = 500,
  retryAfterSeconds = 10,
  onRetry,
  message,
  isRemoved = false,
}: BlogUnavailableProps) {
  const [countdown, setCountdown] = useState<number>(retryAfterSeconds);
  const isRateLimited = status === 429;
  const locale = useLocale();

  useEffect(() => {
    if (!isRateLimited || countdown <= 0) {
      if (isRateLimited && countdown === 0) {
        if (onRetry) {
          onRetry();
        } else if (typeof window !== 'undefined') {
          window.location.reload();
        }
      }
      return;
    }

    const timer = setInterval(() => {
      setCountdown((prev) => Math.max(0, prev - 1));
    }, 1000);

    return () => clearInterval(timer);
  }, [isRateLimited, countdown, onRetry]);

  if (isRemoved || status === 410) {
    return (
      <div
        role="status"
        aria-live="polite"
        className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 sm:py-24 lg:px-8"
      >
        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-600">
          <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
          Article Removed
        </h1>
        <p className="mt-3 text-base text-neutral-600">
          {message || 'This travel article has been archived or removed and is no longer available.'}
        </p>
        <div className="mt-8 flex items-center justify-center gap-4">
          <Link
            href={`/${locale}/blog`}
            className="rounded-full bg-brand-primary px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
          >
            Explore More Articles
          </Link>
          <Link
            href={`/${locale}/tours`}
            className="rounded-full border border-neutral-300 bg-white px-6 py-3 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
          >
            Browse Tours
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div
      role="status"
      aria-live="polite"
      className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 sm:py-24 lg:px-8"
    >
      <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-neutral-100 text-neutral-600">
        <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>

      <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
        {isRateLimited ? 'Too Many Requests' : 'Content Temporarily Unavailable'}
      </h1>

      <p className="mt-3 text-base text-neutral-600">
        {isRateLimited ? (
          <>
            You are requesting articles too quickly. Retrying automatically in{' '}
            <span className="font-semibold text-neutral-900">{countdown}s</span>...
          </>
        ) : (
          message || 'We are experiencing temporary difficulties loading this content. Please try again shortly.'
        )}
      </p>

      <div className="mt-8 flex items-center justify-center gap-4">
        {isRateLimited ? (
          <button
            type="button"
            onClick={() => {
              if (onRetry) onRetry();
              else if (typeof window !== 'undefined') window.location.reload();
            }}
            className="rounded-full bg-brand-primary px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
          >
            Retry Now
          </button>
        ) : (
          <Link
            href={`/${locale}/blog`}
            className="rounded-full bg-brand-primary px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-brand-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary"
          >
            Explore Blog
          </Link>
        )}

        <Link
          href={`/${locale}/tours`}
          className="rounded-full border border-neutral-300 bg-white px-6 py-3 text-sm font-semibold text-neutral-700 hover:bg-neutral-50"
        >
          Browse Tours
        </Link>
      </div>
    </div>
  );
}

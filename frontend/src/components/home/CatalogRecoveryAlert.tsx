'use client';

import { useCallback } from 'react';
import { useTranslations } from 'next-intl';

interface CatalogRecoveryAlertProps {
  onRetry?: () => void;
}

export default function CatalogRecoveryAlert({ onRetry }: CatalogRecoveryAlertProps) {
  const t = useTranslations('home');

  const handleRetry = useCallback(() => {
    if (onRetry) {
      onRetry();
    } else {
      window.location.reload();
    }
  }, [onRetry]);

  return (
    <div
      role="alert"
      className="mx-auto my-8 max-w-4xl rounded-xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900 shadow-sm"
    >
      <div className="flex flex-col items-center justify-center space-y-2">
        <svg
          className="h-8 w-8 text-amber-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
        <p className="text-base font-semibold">{t('loadError')}</p>
        <p className="text-sm text-amber-800">{t('loadErrorHint')}</p>
        <button
          type="button"
          onClick={handleRetry}
          className="mt-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
        >
          {t('retry')}
        </button>
      </div>
    </div>
  );
}


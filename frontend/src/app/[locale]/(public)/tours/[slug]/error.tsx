'use client';

import { useTranslations } from 'next-intl';
import ErrorState from '@/components/ui/ErrorState';

interface TourDetailErrorProps {
  error: Error & { digest?: string };
  unstable_retry: () => void;
}

// Route-local recoverable failure for the tour detail page. Only the generic
// localized copy is shown: the upstream error (message, status, payload) never
// reaches the UI. Recovery uses the installed Next 16.2 error contract's
// unstable_retry, which re-fetches and re-renders the segment through the
// real no-store detail read.
export default function TourDetailError({ unstable_retry }: TourDetailErrorProps) {
  const t = useTranslations('tour.states');

  return (
    <div className="py-8">
      <h1 className="mb-4 text-xl font-bold text-primary">{t('errorTitle')}</h1>
      <ErrorState message={t('errorDescription')} onRetry={unstable_retry} />
    </div>
  );
}

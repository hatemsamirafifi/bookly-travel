'use client';

import { useTranslations } from 'next-intl';
import { Button } from './button';

interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
  retryLabel?: string;
}

export default function ErrorState({ message, onRetry, retryLabel }: ErrorStateProps) {
  const t = useTranslations('common');
  return (
    <div className="rounded-lg border border-error/30 bg-surface p-card" role="alert">
      <p className="mb-3 text-sm text-error">{message}</p>
      {onRetry && (
        <Button
          type="button"
          variant="ghost"
          onClick={onRetry}
          className="text-sm font-semibold text-error underline"
        >
          {retryLabel ?? t('retry')}
        </Button>
      )}
    </div>
  );
}

'use client';

import { useState } from 'react';
import { useTranslations, useLocale } from 'next-intl';
import Link from 'next/link';
import { authApi, AuthApiError } from '@/lib/api/auth';

interface VerifyEmailClientProps {
  status?: string;
}

export function VerifyEmailClient({ status }: VerifyEmailClientProps) {
  const t = useTranslations('auth');
  const locale = useLocale();

  const [resendSuccess, setResendSuccess] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleResend = async () => {
    setServerError(null);
    setResendSuccess(false);
    setIsSubmitting(true);
    try {
      await authApi.resendVerification();
      setResendSuccess(true);
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setServerError(err.message);
      } else {
        const message = err instanceof Error ? err.message : t('errors.invalidCredentials');
        setServerError(message);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (status === 'success') {
    return (
      <div className="flex flex-col items-center gap-4 py-6 text-center text-success text-[0.9375rem] font-medium" role="status" aria-live="polite">
        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-success/15 text-2xl font-bold">✓</div>
        <p>{t('verifyEmail.verified')}</p>
        <p className="text-sm text-text-muted">
          <Link href={`/${locale}/auth/login`} className="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline">
            {t('signin.submitButton')}
          </Link>
        </p>
      </div>
    );
  }

  if (status === 'expired') {
    return (
      <div className="flex flex-col items-center gap-4">
        <div className="px-4 py-3 bg-warning/10 border border-warning/30 rounded-md text-warning text-sm w-full text-center" role="alert" aria-live="polite">
          {t('verifyEmail.expired')}
        </div>
        <button
          type="button"
          onClick={handleResend}
          disabled={isSubmitting || resendSuccess}
          className="flex items-center justify-center gap-2 w-full px-4 py-3 mt-1 bg-primary text-white text-[0.9375rem] font-semibold border-none rounded-md cursor-pointer transition-all hover:bg-primary-dark hover:shadow-[0_4px_14px_color-mix(in_srgb,var(--color-primary)_40%,transparent)] active:scale-98 disabled:opacity-70 disabled:cursor-not-allowed"
          aria-busy={isSubmitting}
        >
          {isSubmitting ? (
            <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
          ) : null}
          {t('verifyEmail.resendButton')}
        </button>
        {resendSuccess && (
          <div className="px-4 py-3 bg-success/10 border border-success/30 rounded-md text-success text-sm w-full text-center" role="status" aria-live="polite">
            {t('verifyEmail.resendSuccess')}
          </div>
        )}
        {serverError && (
          <div className="px-4 py-3 bg-error/10 border border-error/30 rounded-md text-error text-sm w-full text-center" role="alert" aria-live="assertive">
            {serverError}
          </div>
        )}
      </div>
    );
  }

  // Default: error or no status
  return (
    <div className="flex flex-col items-center gap-4">
      <div className="px-4 py-3 bg-error/10 border border-error/30 rounded-md text-error text-sm w-full text-center" role="alert" aria-live="assertive">
        {t('verifyEmail.error')}
      </div>
      <button
        type="button"
        onClick={handleResend}
        disabled={isSubmitting || resendSuccess}
        className="flex items-center justify-center gap-2 w-full px-4 py-3 mt-1 bg-primary text-white text-[0.9375rem] font-semibold border-none rounded-md cursor-pointer transition-all hover:bg-primary-dark hover:shadow-[0_4px_14px_color-mix(in_srgb,var(--color-primary)_40%,transparent)] active:scale-98 disabled:opacity-70 disabled:cursor-not-allowed"
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('verifyEmail.resendButton')}
      </button>
      {resendSuccess && (
        <div className="px-4 py-3 bg-success/10 border border-success/30 rounded-md text-success text-sm w-full text-center" role="status" aria-live="polite">
          {t('verifyEmail.resendSuccess')}
        </div>
      )}
      {serverError && (
        <div className="px-4 py-3 bg-error/10 border border-error/30 rounded-md text-error text-sm w-full text-center" role="alert" aria-live="assertive">
          {serverError}
        </div>
      )}
    </div>
  );
}

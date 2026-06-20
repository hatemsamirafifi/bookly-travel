'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations, useLocale } from 'next-intl';
import Link from 'next/link';
import { forgotPasswordSchema } from '@/lib/validators/auth';
import { authApi, AuthApiError } from '@/lib/api/auth';

type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

export function ForgotPasswordForm() {
  const t = useTranslations('auth');
  const locale = useLocale();

  const [serverError, setServerError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotPasswordFormData>({
    resolver: zodResolver(forgotPasswordSchema),
  });

  const onSubmit = async (data: ForgotPasswordFormData) => {
    setServerError(null);
    try {
      await authApi.forgotPassword(data);
      setSuccess(true);
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setServerError(err.message);
      } else {
        const message = err instanceof Error ? err.message : t('errors.invalidCredentials');
        setServerError(message);
      }
    }
  };

  if (success) {
    return (
      <div className="flex flex-col items-center gap-4 py-6 text-center text-success text-[0.9375rem] font-medium" role="status" aria-live="polite">
        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-success/15 text-2xl font-bold">✓</div>
        <p>{t('forgotPassword.successMessage')}</p>
        <p className="text-sm text-text-muted">
          <Link href={`/${locale}/auth/login`} className="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline">
            {t('signin.submitButton')}
          </Link>
        </p>
      </div>
    );
  }

  return (
    <form
      className="flex flex-col gap-5"
      onSubmit={handleSubmit(onSubmit)}
      noValidate
      aria-label={t('forgotPassword.title')}
    >
      {/* Email */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="forgot-email" className="text-sm font-semibold text-foreground">
          {t('forgotPassword.emailLabel')}
        </label>
        <input
          id="forgot-email"
          type="email"
          className={`w-full px-3.5 py-2.5 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.email ? 'border-error focus:ring-3 focus:ring-error/20' : 'border-border focus:border-primary focus:ring-3 focus:ring-primary-ring/30'}`}
          placeholder={t('signin.emailPlaceholder')}
          autoComplete="email"
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? 'forgot-email-error' : undefined}
          {...register('email')}
        />
        {errors.email && (
          <p id="forgot-email-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {errors.email.message}
          </p>
        )}
      </div>

      {/* Server-level error */}
      {serverError && (
        <div className="px-4 py-3 bg-error/10 border border-error/30 rounded-md text-error text-sm" role="alert" aria-live="assertive">
          {serverError}
        </div>
      )}

      {/* Submit */}
      <button
        id="forgot-submit"
        type="submit"
        className="flex items-center justify-center gap-2 w-full px-4 py-3 mt-1 bg-primary text-white text-[0.9375rem] font-semibold border-none rounded-md cursor-pointer transition-all hover:bg-primary-dark hover:shadow-[0_4px_14px_color-mix(in_srgb,var(--color-primary)_40%,transparent)] active:scale-98 disabled:opacity-70 disabled:cursor-not-allowed"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('forgotPassword.submitButton')}
      </button>

      {/* Back to sign-in */}
      <p className="text-center text-sm text-text-muted m-0">
        {t('signin.registerPrompt')}{' '}
        <Link href={`/${locale}/auth/login`} className="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline">
          {t('signin.submitButton')}
        </Link>
      </p>
    </form>
  );
}

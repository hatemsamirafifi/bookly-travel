'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations, useLocale } from 'next-intl';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { loginSchema } from '@/lib/validators/auth';
import { useAuth } from '@/lib/hooks/useAuth';
import { AuthApiError } from '@/lib/api/auth';
import { safeAuthReturnUrl } from '@/lib/auth/return-url';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

type LoginFormData = z.infer<typeof loginSchema>;

interface LoginFormProps {
  returnUrl?: string;
  sessionExpired?: boolean;
}

export function LoginForm({ returnUrl, sessionExpired }: LoginFormProps) {
  const t = useTranslations('auth');
  const locale = useLocale();
  const router = useRouter();
  const { login } = useAuth();

  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [showSessionExpired, setShowSessionExpired] = useState(!!sessionExpired);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  // Zod messages in lib/validators/auth.ts are auth.* i18n keys; resolve them
  // here so users never see a raw key (e.g. "auth.errors.invalidEmail").
  const formatError = (message?: string) => {
    if (!message) return '';
    return message.startsWith('auth.') ? t(message.replace('auth.', '')) : message;
  };

  const onSubmit = async (data: LoginFormData) => {
    setServerError(null);
    setShowSessionExpired(false);
    try {
      await login(data);
      
      router.push(safeAuthReturnUrl(locale, returnUrl));
    } catch (err: unknown) {
      if (err instanceof AuthApiError && err.errors) {
        for (const [field, messages] of Object.entries(err.errors)) {
          if (field in data) {
            setError(field as keyof LoginFormData, {
              message: Array.isArray(messages) ? messages[0] : String(messages),
            });
          }
        }
      } else if (err instanceof AuthApiError) {
        if (err.code === 'network_error') {
          setServerError(t('errors.networkError'));
        } else if (err.code === 'invalid_credentials') {
          setServerError(t('errors.invalidCredentials'));
        } else if (err.code === 'account_locked') {
          setServerError(t('errors.accountLocked'));
        } else {
          setServerError(err.message);
        }
      } else {
        setServerError(t('errors.genericError'));
      }
    }
  };

  return (
    <form
      className="flex flex-col gap-5"
      onSubmit={handleSubmit(onSubmit)}
      noValidate
      aria-label={t('signin.title')}
    >
      {/* Session Expired Banner */}
      {showSessionExpired && (
        <div className="px-4 py-3 bg-warning/10 border border-warning/30 rounded-md text-warning text-sm" role="alert" aria-live="polite">
          {t('errors.sessionExpired')}
        </div>
      )}

      {/* Email */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="login-email" className="text-sm font-semibold text-foreground">
          {t('signin.emailLabel')}
        </label>
        <Input
          id="login-email"
          type="email"
          className={errors.email ? 'border-error' : undefined}
          placeholder={t('signin.emailPlaceholder')}
          autoComplete="email"
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? 'login-email-error' : undefined}
          {...register('email')}
        />
        {errors.email && (
          <p id="login-email-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {formatError(errors.email.message)}
          </p>
        )}
      </div>

      {/* Password */}
      <div className="flex flex-col gap-1.5">
        <div className="flex justify-between items-center">
          <label htmlFor="login-password" className="text-sm font-semibold text-foreground">
            {t('signin.passwordLabel')}
          </label>
          <Link
            href={`/${locale}/auth/forgot-password`}
            className="text-xs font-semibold text-bookly-navy underline-offset-2 hover:underline"
          >
            {t('signin.forgotPasswordLink')}
          </Link>
        </div>
        <div className="relative">
          <Input
            id="login-password"
            type={showPassword ? 'text' : 'password'}
            className={`pr-11 ${errors.password ? 'border-error' : ''}`}
            placeholder=""
            autoComplete="current-password"
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? 'login-password-error' : undefined}
            {...register('password')}
          />
          <button
            type="button"
            className="absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none cursor-pointer text-text-muted flex items-center p-1 rounded-md transition-colors hover:text-foreground"
            onClick={() => setShowPassword((v) => !v)}
            aria-label={showPassword ? t('signin.hidePassword') : t('signin.showPassword')}
            tabIndex={0}
          >
            {showPassword ? (
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="18" height="18" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            ) : (
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="18" height="18" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            )}
          </button>
        </div>
        {errors.password && (
          <p id="login-password-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {formatError(errors.password.message)}
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
      <Button
        id="login-submit"
        type="submit"
        size="lg"
        className="mt-1 w-full gap-2 text-base"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('signin.submitButton')}
      </Button>

      {/* Register prompt */}
      <p className="text-center text-sm text-text-muted m-0">
        {t('signin.registerPrompt')}{' '}
        <Link href={`/${locale}/auth/register`} className="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline">
          {t('signin.registerLink')}
        </Link>
      </p>
    </form>
  );
}

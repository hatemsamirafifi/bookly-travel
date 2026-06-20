'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations, useLocale } from 'next-intl';
import { useRouter } from 'next/navigation';
import { registerSchema } from '@/lib/validators/auth';
import { authApi, AuthApiError } from '@/lib/api/auth';
import { useAuth } from '@/lib/hooks/useAuth';

type GuestConversionFormData = z.infer<typeof registerSchema>;

interface GuestConversionPromptProps {
  name: string;
  email: string;
  returnUrl?: string;
}

export function GuestConversionPrompt({ name, email, returnUrl }: GuestConversionPromptProps) {
  const t = useTranslations('auth');
  const locale = useLocale();
  const router = useRouter();
  const { setAuth } = useAuth();

  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<GuestConversionFormData>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name,
      email,
      locale: locale as 'en' | 'es' | 'it',
    },
  });

  const onSubmit = async (data: GuestConversionFormData) => {
    setServerError(null);
    try {
      const response = await authApi.register(data);
      setAuth(response.data, response.token);
      setSuccess(true);
      setTimeout(() => {
        router.push(returnUrl || `/${locale}/my-bookings`);
      }, 1200);
    } catch (err: unknown) {
      if (err instanceof AuthApiError && err.errors) {
        for (const [field, messages] of Object.entries(err.errors)) {
          if (field in data) {
            setError(field as keyof GuestConversionFormData, {
              message: Array.isArray(messages) ? messages[0] : String(messages),
            });
          }
        }
      } else {
        const message = err instanceof Error ? err.message : t('errors.invalidCredentials');
        setServerError(message);
      }
    }
  };

  const handleSkip = () => {
    router.push(returnUrl || `/${locale}/`);
  };

  if (success) {
    return (
      <div className="flex flex-col items-center gap-4 py-6 text-center text-success text-[0.9375rem] font-medium" role="status" aria-live="polite">
        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-success/15 text-2xl font-bold">✓</div>
        <p>{t('register.successMessage')}</p>
      </div>
    );
  }

  return (
    <form
      className="flex flex-col gap-5"
      onSubmit={handleSubmit(onSubmit)}
      noValidate
      aria-label={t('guestConversion.title')}
    >
      {/* Name (read-only pre-filled) */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="guest-name" className="text-sm font-semibold text-foreground">
          {t('register.nameLabel')}
        </label>
        <input
          id="guest-name"
          type="text"
          readOnly
          className="w-full px-3.5 py-2.5 border-[1.5px] rounded-md bg-muted text-foreground text-[0.9375rem] outline-none border-border cursor-not-allowed"
          {...register('name')}
        />
      </div>

      {/* Email (read-only pre-filled) */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="guest-email" className="text-sm font-semibold text-foreground">
          {t('register.emailLabel')}
        </label>
        <input
          id="guest-email"
          type="email"
          readOnly
          className="w-full px-3.5 py-2.5 border-[1.5px] rounded-md bg-muted text-foreground text-[0.9375rem] outline-none border-border cursor-not-allowed"
          {...register('email')}
        />
      </div>

      {/* Password */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="guest-password" className="text-sm font-semibold text-foreground">
          {t('guestConversion.passwordLabel')}
        </label>
        <div className="relative">
          <input
            id="guest-password"
            type={showPassword ? 'text' : 'password'}
            className={`w-full px-3.5 py-2.5 pr-11 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.password ? 'border-error focus:ring-3 focus:ring-error/20' : 'border-border focus:border-primary focus:ring-3 focus:ring-primary-ring/30'}`}
            placeholder={t('register.passwordPlaceholder')}
            autoComplete="new-password"
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? 'guest-password-error' : undefined}
            {...register('password')}
          />
          <button
            type="button"
            className="absolute right-3 top-1/2 -translate-y-1/2 bg-transparent border-none cursor-pointer text-text-muted flex items-center p-1 rounded-md transition-colors hover:text-foreground"
            onClick={() => setShowPassword((v) => !v)}
            aria-label={showPassword ? t('register.hidePassword') : t('register.showPassword')}
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
          <p id="guest-password-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {errors.password.message}
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
        id="guest-submit"
        type="submit"
        className="flex items-center justify-center gap-2 w-full px-4 py-3 mt-1 bg-primary text-white text-[0.9375rem] font-semibold border-none rounded-md cursor-pointer transition-all hover:bg-primary-dark hover:shadow-[0_4px_14px_color-mix(in_srgb,var(--color-primary)_40%,transparent)] active:scale-98 disabled:opacity-70 disabled:cursor-not-allowed"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('guestConversion.createAccountButton')}
      </button>

      {/* Skip */}
      <button
        type="button"
        onClick={handleSkip}
        className="w-full px-4 py-2.5 bg-transparent text-text-muted text-sm font-semibold border border-border rounded-md cursor-pointer transition-all hover:bg-muted hover:text-foreground"
      >
        {t('guestConversion.skipButton')}
      </button>
    </form>
  );
}

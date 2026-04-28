'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations, useLocale } from 'next-intl';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { registerSchema } from '@/lib/validators/auth';
import { authApi } from '@/lib/api/auth';
import { useAuth } from '@/lib/hooks/useAuth';
import { AuthApiError } from '@/lib/api/auth';

type RegisterFormData = z.infer<typeof registerSchema>;

interface RegisterFormProps {
  returnUrl?: string;
}

export function RegisterForm({ returnUrl }: RegisterFormProps) {
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
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    defaultValues: { locale: locale as 'en' | 'es' | 'it' },
  });

  const onSubmit = async (data: RegisterFormData) => {
    setServerError(null);
    try {
      const response = await authApi.register(data);
      setAuth(response.data, response.token);
      setSuccess(true);
      
      let validatedReturnUrl = `/${locale}/`;
      if (returnUrl && returnUrl.startsWith(`/${locale}/`)) {
        validatedReturnUrl = returnUrl;
      }

      // Small delay so success message shows briefly
      setTimeout(() => {
        router.push(validatedReturnUrl);
      }, 1200);
    } catch (err: unknown) {
      // Map AuthApiError field errors to react-hook-form
      if (err instanceof AuthApiError && err.errors) {
        for (const [field, messages] of Object.entries(err.errors)) {
          if (field in data) {
            setError(field as keyof RegisterFormData, {
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
      aria-label={t('register.title')}
    >
      {/* Name */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="register-name" className="text-sm font-semibold text-foreground">
          {t('register.nameLabel')}
        </label>
        <input
          id="register-name"
          type="text"
          className={`w-full px-3.5 py-2.5 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.name ? 'border-error focus:ring-3 focus:ring-error/20' : 'border-border focus:border-primary focus:ring-3 focus:ring-primary-ring/30'}`}
          placeholder={t('register.namePlaceholder')}
          autoComplete="name"
          aria-invalid={!!errors.name}
          aria-describedby={errors.name ? 'register-name-error' : undefined}
          {...register('name')}
        />
        {errors.name && (
          <p id="register-name-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {errors.name.message}
          </p>
        )}
      </div>

      {/* Email */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="register-email" className="text-sm font-semibold text-foreground">
          {t('register.emailLabel')}
        </label>
        <input
          id="register-email"
          type="email"
          className={`w-full px-3.5 py-2.5 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.email ? 'border-error focus:ring-3 focus:ring-error/20' : 'border-border focus:border-primary focus:ring-3 focus:ring-primary-ring/30'}`}
          placeholder={t('register.emailPlaceholder')}
          autoComplete="email"
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? 'register-email-error' : undefined}
          {...register('email')}
        />
        {errors.email && (
          <p id="register-email-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {errors.email.message}
          </p>
        )}
      </div>

      {/* Password */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="register-password" className="text-sm font-semibold text-foreground">
          {t('register.passwordLabel')}
        </label>
        <div className="relative">
          <input
            id="register-password"
            type={showPassword ? 'text' : 'password'}
            className={`w-full px-3.5 py-2.5 pr-11 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.password ? 'border-error focus:ring-3 focus:ring-error/20' : 'border-border focus:border-primary focus:ring-3 focus:ring-primary-ring/30'}`}
            placeholder={t('register.passwordPlaceholder')}
            autoComplete="new-password"
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? 'register-password-error' : undefined}
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
          <p id="register-password-error" className="text-[0.8125rem] text-error m-0" role="alert">
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
        id="register-submit"
        type="submit"
        className="flex items-center justify-center gap-2 w-full px-4 py-3 mt-1 bg-primary text-white text-[0.9375rem] font-semibold border-none rounded-md cursor-pointer transition-all hover:bg-primary-dark hover:shadow-[0_4px_14px_color-mix(in_srgb,var(--color-primary)_40%,transparent)] active:scale-98 disabled:opacity-70 disabled:cursor-not-allowed"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('register.submitButton')}
      </button>

      {/* Sign-in prompt */}
      <p className="text-center text-sm text-text-muted m-0">
        {t('register.signinPrompt')}{' '}
        <Link href={`/${locale}/auth/login`} className="text-primary font-semibold no-underline transition-colors hover:text-primary-dark hover:underline">
          {t('register.signinLink')}
        </Link>
      </p>
    </form>
  );
}

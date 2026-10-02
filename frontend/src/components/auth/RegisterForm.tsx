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
import { safeAuthReturnUrl } from '@/lib/auth/return-url';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

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

  // Zod messages in lib/validators/auth.ts are auth.* i18n keys; resolve them
  // here so users never see a raw key (e.g. "auth.errors.invalidEmail").
  const formatError = (message?: string) => {
    if (!message) return '';
    return message.startsWith('auth.') ? t(message.replace('auth.', '')) : message;
  };

  const onSubmit = async (data: RegisterFormData) => {
    setServerError(null);
    try {
      const response = await authApi.register(data);
      setAuth(response.data, response.token);
      setSuccess(true);
      
      const validatedReturnUrl = safeAuthReturnUrl(locale, returnUrl);

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
      } else if (err instanceof AuthApiError && err.code === 'network_error') {
        setServerError(t('errors.networkError'));
      } else if (err instanceof AuthApiError) {
        setServerError(err.message);
      } else {
        setServerError(t('errors.genericError'));
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
        <Input
          id="register-name"
          type="text"
          className={errors.name ? 'border-error' : undefined}
          placeholder={t('register.namePlaceholder')}
          autoComplete="name"
          aria-invalid={!!errors.name}
          aria-describedby={errors.name ? 'register-name-error' : undefined}
          {...register('name')}
        />
        {errors.name && (
          <p id="register-name-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {formatError(errors.name.message)}
          </p>
        )}
      </div>

      {/* Email */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="register-email" className="text-sm font-semibold text-foreground">
          {t('register.emailLabel')}
        </label>
        <Input
          id="register-email"
          type="email"
          className={errors.email ? 'border-error' : undefined}
          placeholder={t('register.emailPlaceholder')}
          autoComplete="email"
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? 'register-email-error' : undefined}
          {...register('email')}
        />
        {errors.email && (
          <p id="register-email-error" className="text-[0.8125rem] text-error m-0" role="alert">
            {formatError(errors.email.message)}
          </p>
        )}
      </div>

      {/* Password */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="register-password" className="text-sm font-semibold text-foreground">
          {t('register.passwordLabel')}
        </label>
        <div className="relative">
          <Input
            id="register-password"
            type={showPassword ? 'text' : 'password'}
            className={`pr-11 ${errors.password ? 'border-error' : ''}`}
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
        id="register-submit"
        type="submit"
        size="lg"
        className="mt-1 w-full gap-2 text-base"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" aria-hidden="true" />
        ) : null}
        {t('register.submitButton')}
      </Button>

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

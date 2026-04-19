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
      // Small delay so success message shows briefly
      setTimeout(() => {
        router.push(returnUrl || `/${locale}`);
      }, 1200);
    } catch (err: any) {
      // Parse server validation errors (error.details shape)
      const details = err?.details;
      if (details && typeof details === 'object') {
        for (const [field, messages] of Object.entries(details)) {
          if (field in data) {
            setError(field as keyof RegisterFormData, {
              message: Array.isArray(messages) ? messages[0] : String(messages),
            });
          }
        }
      } else {
        setServerError(err?.message || 'auth.errors.invalidCredentials');
      }
    }
  };

  if (success) {
    return (
      <div className="register-form__success" role="status" aria-live="polite">
        <div className="register-form__success-icon">✓</div>
        <p>{t('register.successMessage')}</p>
      </div>
    );
  }

  return (
    <form
      className="register-form"
      onSubmit={handleSubmit(onSubmit)}
      noValidate
      aria-label={t('register.title')}
    >
      {/* Name */}
      <div className="register-form__field">
        <label htmlFor="register-name" className="register-form__label">
          {t('register.nameLabel')}
        </label>
        <input
          id="register-name"
          type="text"
          className={`register-form__input ${errors.name ? 'register-form__input--error' : ''}`}
          placeholder={t('register.namePlaceholder')}
          autoComplete="name"
          aria-invalid={!!errors.name}
          aria-describedby={errors.name ? 'register-name-error' : undefined}
          {...register('name')}
        />
        {errors.name && (
          <p id="register-name-error" className="register-form__error" role="alert">
            {errors.name.message}
          </p>
        )}
      </div>

      {/* Email */}
      <div className="register-form__field">
        <label htmlFor="register-email" className="register-form__label">
          {t('register.emailLabel')}
        </label>
        <input
          id="register-email"
          type="email"
          className={`register-form__input ${errors.email ? 'register-form__input--error' : ''}`}
          placeholder={t('register.emailPlaceholder')}
          autoComplete="email"
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? 'register-email-error' : undefined}
          {...register('email')}
        />
        {errors.email && (
          <p id="register-email-error" className="register-form__error" role="alert">
            {errors.email.message}
          </p>
        )}
      </div>

      {/* Password */}
      <div className="register-form__field">
        <label htmlFor="register-password" className="register-form__label">
          {t('register.passwordLabel')}
        </label>
        <div className="register-form__password-wrapper">
          <input
            id="register-password"
            type={showPassword ? 'text' : 'password'}
            className={`register-form__input register-form__input--password ${errors.password ? 'register-form__input--error' : ''}`}
            placeholder={t('register.passwordPlaceholder')}
            autoComplete="new-password"
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? 'register-password-error' : undefined}
            {...register('password')}
          />
          <button
            type="button"
            className="register-form__password-toggle"
            onClick={() => setShowPassword((v) => !v)}
            aria-label={showPassword ? 'Hide password' : 'Show password'}
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
          <p id="register-password-error" className="register-form__error" role="alert">
            {errors.password.message}
          </p>
        )}
      </div>

      {/* Server-level error */}
      {serverError && (
        <div className="register-form__server-error" role="alert" aria-live="assertive">
          {serverError}
        </div>
      )}

      {/* Submit */}
      <button
        id="register-submit"
        type="submit"
        className="register-form__submit"
        disabled={isSubmitting}
        aria-busy={isSubmitting}
      >
        {isSubmitting ? (
          <span className="register-form__spinner" aria-hidden="true" />
        ) : null}
        {t('register.submitButton')}
      </button>

      {/* Sign-in prompt */}
      <p className="register-form__signin-prompt">
        {t('register.signinPrompt')}{' '}
        <Link href={`/${locale}/auth/login`} className="register-form__signin-link">
          {t('register.signinLink')}
        </Link>
      </p>
    </form>
  );
}

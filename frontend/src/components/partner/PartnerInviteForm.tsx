'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { completeInvite } from '@/lib/api/partner';
import { useAuth } from '@/lib/hooks/useAuth';
import type { User } from '@/lib/api/auth';
import type { InviteValidationResponse } from '@/types/partner';

interface PartnerInviteFormProps {
  token: string;
  invitation: InviteValidationResponse;
}

export function PartnerInviteForm({ token, invitation }: PartnerInviteFormProps) {
  const router = useRouter();
  const locale = useLocale();
  const { setAuth } = useAuth();
  const t = useTranslations('partnerOnboarding.register');
  const tInvite = useTranslations('partnerOnboarding.invite');
  const tErrors = useTranslations('partnerOnboarding.errors');

  const [formData, setFormData] = useState({
    name: '',
    password: '',
    password_confirmation: '',
    contact_phone: '',
    business_description: '',
    street: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    payout_country: '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const validate = (): boolean => {
    const errs: Record<string, string> = {};

    if (!formData.name.trim()) errs.name = tErrors('required');
    if (!formData.password) {
      errs.password = tErrors('required');
    } else if (formData.password.length < 8) {
      errs.password = tErrors('passwordMin');
    }

    if (!formData.password_confirmation) {
      errs.password_confirmation = tErrors('required');
    } else if (formData.password !== formData.password_confirmation) {
      errs.password_confirmation = tErrors('passwordMismatch');
    }

    if (!formData.contact_phone.trim()) errs.contact_phone = tErrors('required');
    if (!formData.business_description.trim()) errs.business_description = tErrors('required');
    if (!formData.street.trim()) errs.street = tErrors('required');
    if (!formData.city.trim()) errs.city = tErrors('required');
    if (!formData.postal_code.trim()) errs.postal_code = tErrors('required');

    if (!formData.country.trim()) {
      errs.country = tErrors('required');
    } else if (formData.country.trim().length !== 2) {
      errs.country = tErrors('invalidCountry');
    }

    if (!formData.payout_country.trim()) {
      errs.payout_country = tErrors('required');
    } else if (formData.payout_country.trim().length !== 2) {
      errs.payout_country = tErrors('invalidCountry');
    }

    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setGeneralError(null);

    if (!validate()) return;

    setIsSubmitting(true);

    try {
      const payload = {
        name: formData.name.trim(),
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        contact_phone: formData.contact_phone.trim(),
        business_description: formData.business_description.trim(),
        business_address: {
          street: formData.street.trim(),
          city: formData.city.trim(),
          state: formData.state.trim() || null,
          postal_code: formData.postal_code.trim(),
          country: formData.country.trim().toUpperCase(),
        },
        payout_country: formData.payout_country.trim().toUpperCase(),
      };

      const response = await completeInvite(token, payload);

      if (response?.data?.token && response?.data?.user) {
        const authUser: User = {
          id: response.data.user.id,
          name: response.data.user.name,
          email: response.data.user.email,
          role: (response.data.user.role as 'traveler' | 'partner' | 'admin') || 'partner',
          locale: (locale as 'en' | 'es' | 'it') || 'en',
          emailVerified: true,
          createdAt: new Date().toISOString(),
          lastLoginAt: null,
        };
        setAuth(authUser, response.data.token);
        router.push(`/${locale}/partner/onboarding`);
      }
    } catch (err: unknown) {
      const apiErr = err as { errors?: Record<string, string[] | string>; message?: string };
      if (apiErr?.errors) {
        const backendErrors: Record<string, string> = {};
        for (const [key, msgs] of Object.entries(apiErr.errors)) {
          const msgArray = Array.isArray(msgs) ? msgs : [String(msgs)];
          const fieldName = key.replace('business_address.', '');
          backendErrors[fieldName] = msgArray[0] || tErrors('generic');
        }
        setErrors(backendErrors);
      } else {
        setGeneralError(apiErr?.message || tErrors('generic'));
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-8" noValidate>
      {generalError && (
        <div className="p-4 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          {generalError}
        </div>
      )}

      <div className="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-300 flex items-start gap-3">
        <svg className="w-5 h-5 flex-shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p className="font-semibold">{tInvite('autoApprovedNotice')}</p>
        </div>
      </div>

      {/* Pre-filled Read-Only Invitation Details */}
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-foreground border-b border-border pb-2">
          {t('businessDetails')}
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1">
              {t('companyNameLabel')}
            </label>
            <input
              type="text"
              readOnly
              value={invitation.company_name}
              className="w-full px-3 py-2 border border-border rounded-lg bg-muted text-foreground cursor-not-allowed opacity-80"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-1">
              {t('contactEmailLabel')}
            </label>
            <input
              type="email"
              readOnly
              value={invitation.email}
              className="w-full px-3 py-2 border border-border rounded-lg bg-muted text-foreground cursor-not-allowed opacity-80"
            />
          </div>
        </div>
      </section>

      {/* Account Details */}
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-foreground border-b border-border pb-2">
          {t('personalDetails')}
        </h2>
        <div>
          <label className="block text-sm font-medium text-foreground mb-1">
            {t('nameLabel')} *
          </label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            placeholder={t('namePlaceholder')}
            className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
              errors.name ? 'border-red-500 focus:ring-red-500' : 'border-border focus:ring-primary'
            }`}
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              {t('passwordLabel')} *
            </label>
            <input
              type="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              placeholder={t('passwordPlaceholder')}
              className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
                errors.password ? 'border-red-500' : 'border-border'
              }`}
            />
            {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              {t('passwordConfirmationLabel')} *
            </label>
            <input
              type="password"
              name="password_confirmation"
              value={formData.password_confirmation}
              onChange={handleChange}
              placeholder={t('passwordConfirmationPlaceholder')}
              className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
                errors.password_confirmation ? 'border-red-500' : 'border-border'
              }`}
            />
            {errors.password_confirmation && (
              <p className="mt-1 text-xs text-red-500">{errors.password_confirmation}</p>
            )}
          </div>
        </div>
      </section>

      {/* Operational Details */}
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-foreground border-b border-border pb-2">
          {t('businessDetails')}
        </h2>
        <div>
          <label className="block text-sm font-medium text-foreground mb-1">
            {t('contactPhoneLabel')} *
          </label>
          <input
            type="text"
            name="contact_phone"
            value={formData.contact_phone}
            onChange={handleChange}
            placeholder={t('contactPhonePlaceholder')}
            className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
              errors.contact_phone ? 'border-red-500' : 'border-border'
            }`}
          />
          {errors.contact_phone && <p className="mt-1 text-xs text-red-500">{errors.contact_phone}</p>}
        </div>

        <div>
          <label className="block text-sm font-medium text-foreground mb-1">
            {t('businessDescriptionLabel')} *
          </label>
          <textarea
            name="business_description"
            rows={3}
            value={formData.business_description}
            onChange={handleChange}
            placeholder={t('businessDescriptionPlaceholder')}
            className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
              errors.business_description ? 'border-red-500' : 'border-border'
            }`}
          />
          {errors.business_description && (
            <p className="mt-1 text-xs text-red-500">{errors.business_description}</p>
          )}
        </div>
      </section>

      {/* Business Address */}
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-foreground border-b border-border pb-2">
          {t('businessAddress')}
        </h2>
        <div>
          <label className="block text-sm font-medium text-foreground mb-1">
            {t('streetLabel')} *
          </label>
          <input
            type="text"
            name="street"
            value={formData.street}
            onChange={handleChange}
            placeholder={t('streetPlaceholder')}
            className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
              errors.street ? 'border-red-500' : 'border-border'
            }`}
          />
          {errors.street && <p className="mt-1 text-xs text-red-500">{errors.street}</p>}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              {t('cityLabel')} *
            </label>
            <input
              type="text"
              name="city"
              value={formData.city}
              onChange={handleChange}
              placeholder={t('cityPlaceholder')}
              className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
                errors.city ? 'border-red-500' : 'border-border'
              }`}
            />
            {errors.city && <p className="mt-1 text-xs text-red-500">{errors.city}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">State / Province</label>
            <input
              type="text"
              name="state"
              value={formData.state}
              onChange={handleChange}
              placeholder="e.g. Lazio"
              className="w-full px-3 py-2 border border-border rounded-lg bg-background text-foreground"
            />
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              {t('postalCodeLabel')} *
            </label>
            <input
              type="text"
              name="postal_code"
              value={formData.postal_code}
              onChange={handleChange}
              placeholder={t('postalCodePlaceholder')}
              className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground transition-colors ${
                errors.postal_code ? 'border-red-500' : 'border-border'
              }`}
            />
            {errors.postal_code && <p className="mt-1 text-xs text-red-500">{errors.postal_code}</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-foreground mb-1">
              {t('countryLabel')} *
            </label>
            <input
              type="text"
              name="country"
              maxLength={2}
              value={formData.country}
              onChange={handleChange}
              placeholder={t('countryPlaceholder')}
              className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground uppercase transition-colors ${
                errors.country ? 'border-red-500' : 'border-border'
              }`}
            />
            {errors.country && <p className="mt-1 text-xs text-red-500">{errors.country}</p>}
          </div>
        </div>
      </section>

      {/* Payout Information */}
      <section className="space-y-4">
        <h2 className="text-lg font-semibold text-foreground border-b border-border pb-2">
          {t('payoutAndTax')}
        </h2>
        <div>
          <label className="block text-sm font-medium text-foreground mb-1">
            {t('payoutCountryLabel')} *
          </label>
          <input
            type="text"
            name="payout_country"
            maxLength={2}
            value={formData.payout_country}
            onChange={handleChange}
            placeholder={t('payoutCountryPlaceholder')}
            className={`w-full px-3 py-2 border rounded-lg bg-background text-foreground uppercase transition-colors ${
              errors.payout_country ? 'border-red-500' : 'border-border'
            }`}
          />
          {errors.payout_country && <p className="mt-1 text-xs text-red-500">{errors.payout_country}</p>}
        </div>
      </section>

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full py-3 px-4 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {isSubmitting ? tInvite('submittingButton') : tInvite('submitButton')}
      </button>
    </form>
  );
}
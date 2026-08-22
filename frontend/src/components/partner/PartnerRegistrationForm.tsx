'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations, useLocale } from 'next-intl';
import Link from 'next/link';
import { registerPartner } from '@/lib/api/partner';
import { useAuth } from '@/lib/hooks/useAuth';
import type { User } from '@/lib/api/auth';
import type { PartnerRegistrationPayload } from '@/types/partner';

interface FormErrors {
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
  company_name?: string;
  contact_email?: string;
  contact_phone?: string;
  website?: string;
  business_description?: string;
  'business_address.street'?: string;
  'business_address.city'?: string;
  'business_address.postal_code'?: string;
  'business_address.country'?: string;
  tax_id?: string;
  payout_country?: string;
  general?: string;
}

export function PartnerRegistrationForm() {
  const t = useTranslations('partnerOnboarding');
  const locale = useLocale();
  const router = useRouter();
  const { setAuth } = useAuth();

  const [formData, setFormData] = useState<PartnerRegistrationPayload>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_name: '',
    contact_email: '',
    contact_phone: '',
    website: '',
    business_description: '',
    business_address: {
      street: '',
      city: '',
      postal_code: '',
      country: '',
    },
    tax_id: '',
    payout_country: '',
  });

  const [errors, setErrors] = useState<FormErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = e.target;
    if (name.startsWith('business_address.')) {
      const field = name.split('.')[1];
      setFormData((prev) => ({
        ...prev,
        business_address: {
          ...prev.business_address,
          [field]: field === 'country' ? value.toUpperCase() : value,
        },
      }));
    } else if (name === 'payout_country') {
      setFormData((prev) => ({
        ...prev,
        payout_country: value.toUpperCase(),
      }));
    } else {
      setFormData((prev) => ({
        ...prev,
        [name]: value,
      }));
    }

    if (errors[name as keyof FormErrors]) {
      setErrors((prev) => ({ ...prev, [name]: undefined }));
    }
  };

  const validate = (): boolean => {
    const newErrors: FormErrors = {};

    if (!formData.name.trim()) {
      newErrors.name = t('errors.required');
    }
    if (!formData.email.trim()) {
      newErrors.email = t('errors.required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = t('errors.invalidEmail');
    }
    if (!formData.password) {
      newErrors.password = t('errors.required');
    } else if (formData.password.length < 8) {
      newErrors.password = t('errors.passwordMin');
    }
    if (formData.password !== formData.password_confirmation) {
      newErrors.password_confirmation = t('errors.passwordMismatch');
    }
    if (!formData.company_name.trim()) {
      newErrors.company_name = t('errors.required');
    }
    if (!formData.contact_email.trim()) {
      newErrors.contact_email = t('errors.required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.contact_email)) {
      newErrors.contact_email = t('errors.invalidEmail');
    }
    if (!formData.contact_phone.trim()) {
      newErrors.contact_phone = t('errors.required');
    }
    if (!formData.business_description.trim()) {
      newErrors.business_description = t('errors.required');
    }
    if (!formData.business_address.street.trim()) {
      newErrors['business_address.street'] = t('errors.required');
    }
    if (!formData.business_address.city.trim()) {
      newErrors['business_address.city'] = t('errors.required');
    }
    if (!formData.business_address.postal_code.trim()) {
      newErrors['business_address.postal_code'] = t('errors.required');
    }
    if (!formData.business_address.country.trim() || formData.business_address.country.length !== 2) {
      newErrors['business_address.country'] = t('errors.invalidCountry');
    }
    if (!formData.payout_country.trim() || formData.payout_country.length !== 2) {
      newErrors.payout_country = t('errors.invalidCountry');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setIsSubmitting(true);
    setErrors({});

    try {
      const payload: PartnerRegistrationPayload = {
        ...formData,
        website: formData.website?.trim() ? formData.website.trim() : undefined,
        tax_id: formData.tax_id?.trim() ? formData.tax_id.trim() : undefined,
      };

      const res = await registerPartner(payload);

      if (res?.data?.token && res?.data?.user) {
        const authUser: User = {
          id: res.data.user.id,
          name: res.data.user.name,
          email: res.data.user.email,
          role: (res.data.user.role as 'traveler' | 'partner' | 'admin') || 'partner',
          locale: (locale as 'en' | 'es' | 'it') || 'en',
          emailVerified: true,
          createdAt: new Date().toISOString(),
          lastLoginAt: null,
        };
        setAuth(authUser, res.data.token);
        setSuccess(true);
        setTimeout(() => {
          router.push(`/${locale}/partner/onboarding`);
        }, 1500);
      }
    } catch (err: unknown) {
      const apiErr = err as { errors?: Record<string, string[] | string>; message?: string };
      if (apiErr?.errors) {
        const backendErrors: FormErrors = {};
        for (const [key, val] of Object.entries(apiErr.errors)) {
          backendErrors[key as keyof FormErrors] = Array.isArray(val) ? val[0] : String(val);
        }
        setErrors(backendErrors);
      } else {
        setErrors({
          general: apiErr?.message || t('errors.generic'),
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (success) {
    return (
      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-8 text-center" role="status" aria-live="polite">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600">
          ✓
        </div>
        <h3 className="mt-4 text-xl font-bold text-gray-900">{t('register.successMessage')}</h3>
        <p className="mt-2 text-sm text-gray-600">
          Redirecting...
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-8" noValidate>
      {errors.general && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
          {errors.general}
        </div>
      )}

      {/* Account Info Section */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-[#0A2540]">{t('register.personalDetails')}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="name" className="block text-sm font-semibold text-gray-700">
              {t('register.nameLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="name"
              name="name"
              type="text"
              required
              value={formData.name}
              onChange={handleChange}
              placeholder={t('register.namePlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.name ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          <div>
            <label htmlFor="email" className="block text-sm font-semibold text-gray-700">
              {t('register.emailLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="email"
              name="email"
              type="email"
              required
              value={formData.email}
              onChange={handleChange}
              placeholder={t('register.emailPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.email ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-semibold text-gray-700">
              {t('register.passwordLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="password"
              name="password"
              type="password"
              required
              value={formData.password}
              onChange={handleChange}
              placeholder={t('register.passwordPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.password ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
          </div>

          <div>
            <label htmlFor="password_confirmation" className="block text-sm font-semibold text-gray-700">
              {t('register.passwordConfirmationLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              required
              value={formData.password_confirmation}
              onChange={handleChange}
              placeholder={t('register.passwordConfirmationPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.password_confirmation ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.password_confirmation && (
              <p className="mt-1 text-xs text-red-600">{errors.password_confirmation}</p>
            )}
          </div>
        </div>
      </div>

      {/* Business Details Section */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-[#0A2540]">{t('register.businessDetails')}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label htmlFor="company_name" className="block text-sm font-semibold text-gray-700">
              {t('register.companyNameLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="company_name"
              name="company_name"
              type="text"
              required
              value={formData.company_name}
              onChange={handleChange}
              placeholder={t('register.companyNamePlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.company_name ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.company_name && <p className="mt-1 text-xs text-red-600">{errors.company_name}</p>}
          </div>

          <div>
            <label htmlFor="contact_email" className="block text-sm font-semibold text-gray-700">
              {t('register.contactEmailLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="contact_email"
              name="contact_email"
              type="email"
              required
              value={formData.contact_email}
              onChange={handleChange}
              placeholder={t('register.contactEmailPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.contact_email ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.contact_email && <p className="mt-1 text-xs text-red-600">{errors.contact_email}</p>}
          </div>

          <div>
            <label htmlFor="contact_phone" className="block text-sm font-semibold text-gray-700">
              {t('register.contactPhoneLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="contact_phone"
              name="contact_phone"
              type="text"
              required
              value={formData.contact_phone}
              onChange={handleChange}
              placeholder={t('register.contactPhonePlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.contact_phone ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.contact_phone && <p className="mt-1 text-xs text-red-600">{errors.contact_phone}</p>}
          </div>

          <div className="sm:col-span-2">
            <label htmlFor="website" className="block text-sm font-semibold text-gray-700">
              {t('register.websiteLabel')}
            </label>
            <input
              id="website"
              name="website"
              type="url"
              value={formData.website || ''}
              onChange={handleChange}
              placeholder={t('register.websitePlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.website ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.website && <p className="mt-1 text-xs text-red-600">{errors.website}</p>}
          </div>

          <div className="sm:col-span-2">
            <label htmlFor="business_description" className="block text-sm font-semibold text-gray-700">
              {t('register.businessDescriptionLabel')} <span className="text-red-500">*</span>
            </label>
            <textarea
              id="business_description"
              name="business_description"
              rows={4}
              required
              maxLength={1000}
              value={formData.business_description}
              onChange={handleChange}
              placeholder={t('register.businessDescriptionPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.business_description ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.business_description && (
              <p className="mt-1 text-xs text-red-600">{errors.business_description}</p>
            )}
          </div>
        </div>
      </div>

      {/* Business Address Section */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-[#0A2540]">{t('register.businessAddress')}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label htmlFor="business_address.street" className="block text-sm font-semibold text-gray-700">
              {t('register.streetLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="business_address.street"
              name="business_address.street"
              type="text"
              required
              value={formData.business_address.street}
              onChange={handleChange}
              placeholder={t('register.streetPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors['business_address.street'] ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors['business_address.street'] && (
              <p className="mt-1 text-xs text-red-600">{errors['business_address.street']}</p>
            )}
          </div>

          <div>
            <label htmlFor="business_address.city" className="block text-sm font-semibold text-gray-700">
              {t('register.cityLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="business_address.city"
              name="business_address.city"
              type="text"
              required
              value={formData.business_address.city}
              onChange={handleChange}
              placeholder={t('register.cityPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors['business_address.city'] ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors['business_address.city'] && (
              <p className="mt-1 text-xs text-red-600">{errors['business_address.city']}</p>
            )}
          </div>

          <div>
            <label htmlFor="business_address.postal_code" className="block text-sm font-semibold text-gray-700">
              {t('register.postalCodeLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="business_address.postal_code"
              name="business_address.postal_code"
              type="text"
              required
              value={formData.business_address.postal_code}
              onChange={handleChange}
              placeholder={t('register.postalCodePlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors['business_address.postal_code'] ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors['business_address.postal_code'] && (
              <p className="mt-1 text-xs text-red-600">{errors['business_address.postal_code']}</p>
            )}
          </div>

          <div>
            <label htmlFor="business_address.country" className="block text-sm font-semibold text-gray-700">
              {t('register.countryLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="business_address.country"
              name="business_address.country"
              type="text"
              maxLength={2}
              required
              value={formData.business_address.country}
              onChange={handleChange}
              placeholder={t('register.countryPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm uppercase outline-none transition ${
                errors['business_address.country'] ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors['business_address.country'] && (
              <p className="mt-1 text-xs text-red-600">{errors['business_address.country']}</p>
            )}
          </div>
        </div>
      </div>

      {/* Tax & Payout Section */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-[#0A2540]">{t('register.payoutAndTax')}</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="tax_id" className="block text-sm font-semibold text-gray-700">
              {t('register.taxIdLabel')}
            </label>
            <input
              id="tax_id"
              name="tax_id"
              type="text"
              value={formData.tax_id || ''}
              onChange={handleChange}
              placeholder={t('register.taxIdPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition ${
                errors.tax_id ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.tax_id && <p className="mt-1 text-xs text-red-600">{errors.tax_id}</p>}
          </div>

          <div>
            <label htmlFor="payout_country" className="block text-sm font-semibold text-gray-700">
              {t('register.payoutCountryLabel')} <span className="text-red-500">*</span>
            </label>
            <input
              id="payout_country"
              name="payout_country"
              type="text"
              maxLength={2}
              required
              value={formData.payout_country}
              onChange={handleChange}
              placeholder={t('register.payoutCountryPlaceholder')}
              className={`mt-1.5 w-full rounded-lg border px-3.5 py-2.5 text-sm uppercase outline-none transition ${
                errors.payout_country ? 'border-red-500 focus:border-red-500' : 'border-gray-300 focus:border-[#0A2540]'
              }`}
            />
            {errors.payout_country && <p className="mt-1 text-xs text-red-600">{errors.payout_country}</p>}
          </div>
        </div>
      </div>

      <div className="flex flex-col items-center gap-4">
        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full rounded-lg bg-[#0A2540] py-3.5 text-base font-semibold text-white shadow-md transition hover:bg-[#081e35] disabled:cursor-not-allowed disabled:opacity-50"
        >
          {isSubmitting ? t('register.submittingButton') : t('register.submitButton')}
        </button>

        <p className="text-sm text-gray-600">
          {t('register.signinPrompt')}{' '}
          <Link href={`/${locale}/auth/login`} className="font-semibold text-[#0A2540] hover:underline">
            {t('register.signinLink')}
          </Link>
        </p>
      </div>
    </form>
  );
}

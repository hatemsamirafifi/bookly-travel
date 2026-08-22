'use client';

import React, { useState } from 'react';
import { useTranslations } from 'next-intl';
import type { PartnerOnboardingStatus, ResubmitPayload } from '@/types/partner';
import { resubmitApplication } from '@/lib/api/partner';

interface ResubmissionFormProps {
  initialData?: Partial<ResubmitPayload>;
  onSuccess?: (updatedStatus: PartnerOnboardingStatus) => void;
  onCancel?: () => void;
}

export function ResubmissionForm({
  initialData,
  onSuccess,
  onCancel,
}: ResubmissionFormProps) {
  const t = useTranslations('partnerOnboarding.resubmit');
  const tRegister = useTranslations('partnerOnboarding.register');
  const tErrors = useTranslations('partnerOnboarding.errors');

  const [formData, setFormData] = useState<ResubmitPayload>({
    company_name: initialData?.company_name || '',
    contact_email: initialData?.contact_email || '',
    contact_phone: initialData?.contact_phone || '',
    business_description: initialData?.business_description || '',
    website: initialData?.website || '',
    tax_id: initialData?.tax_id || '',
    payout_country: initialData?.payout_country || 'IT',
    business_address: initialData?.business_address || {
      street: '',
      city: '',
      postal_code: '',
      country: 'IT',
      state: '',
    },
  });

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

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
          [field]: field === 'country' ? value.toUpperCase().slice(0, 2) : value,
        },
      }));
    } else if (name === 'payout_country') {
      setFormData((prev) => ({
        ...prev,
        payout_country: value.toUpperCase().slice(0, 2),
      }));
    } else {
      setFormData((prev) => ({
        ...prev,
        [name]: value,
      }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setErrorMessage(null);
    setFieldErrors({});

    try {
      const res = await resubmitApplication(formData);
      if (res?.data) {
        if (onSuccess) {
          onSuccess({
            onboarding_status: 'pending',
            can_create_tours: false,
            rejection_reason: null,
            message: t('successMessage'),
          });
        }
      }
    } catch (err: unknown) {
      const apiErr = err as { errors?: Record<string, string[] | string>; message?: string };
      if (apiErr?.errors) {
        const errors: Record<string, string> = {};
        for (const [key, val] of Object.entries(apiErr.errors)) {
          errors[key] = Array.isArray(val) ? val[0] : String(val);
        }
        setFieldErrors(errors);
      } else {
        setErrorMessage(apiErr?.message || tErrors('generic'));
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm">
      <div className="border-b border-gray-100 pb-5">
        <h3 className="text-xl font-bold text-[#0A2540]">{t('title')}</h3>
        <p className="mt-1 text-sm text-gray-600">{t('subtitle')}</p>
        <p className="mt-2 text-xs text-gray-500">{t('instructions')}</p>
      </div>

      {errorMessage && (
        <div className="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {errorMessage}
        </div>
      )}

      <form onSubmit={handleSubmit} className="mt-6 space-y-6" noValidate>
        {/* Business Details */}
        <div>
          <h4 className="text-sm font-bold uppercase tracking-wider text-gray-400">
            {tRegister('businessDetails')}
          </h4>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('companyNameLabel')} *
              </label>
              <input
                type="text"
                name="company_name"
                value={formData.company_name}
                onChange={handleChange}
                placeholder={tRegister('companyNamePlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors.company_name && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.company_name}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('contactEmailLabel')} *
              </label>
              <input
                type="email"
                name="contact_email"
                value={formData.contact_email}
                onChange={handleChange}
                placeholder={tRegister('contactEmailPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors.contact_email && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.contact_email}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('contactPhoneLabel')} *
              </label>
              <input
                type="tel"
                name="contact_phone"
                value={formData.contact_phone}
                onChange={handleChange}
                placeholder={tRegister('contactPhonePlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors.contact_phone && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.contact_phone}</p>
              )}
            </div>

            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('websiteLabel')}
              </label>
              <input
                type="url"
                name="website"
                value={formData.website || ''}
                onChange={handleChange}
                placeholder={tRegister('websitePlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
              />
              {fieldErrors.website && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.website}</p>
              )}
            </div>

            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('businessDescriptionLabel')} *
              </label>
              <textarea
                name="business_description"
                rows={4}
                value={formData.business_description}
                onChange={handleChange}
                placeholder={tRegister('businessDescriptionPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors.business_description && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.business_description}</p>
              )}
            </div>
          </div>
        </div>

        {/* Address */}
        <div className="border-t border-gray-100 pt-6">
          <h4 className="text-sm font-bold uppercase tracking-wider text-gray-400">
            {tRegister('businessAddress')}
          </h4>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('streetLabel')} *
              </label>
              <input
                type="text"
                name="business_address.street"
                value={formData.business_address.street}
                onChange={handleChange}
                placeholder={tRegister('streetPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors['business_address.street'] && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors['business_address.street']}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('cityLabel')} *
              </label>
              <input
                type="text"
                name="business_address.city"
                value={formData.business_address.city}
                onChange={handleChange}
                placeholder={tRegister('cityPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors['business_address.city'] && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors['business_address.city']}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('postalCodeLabel')} *
              </label>
              <input
                type="text"
                name="business_address.postal_code"
                value={formData.business_address.postal_code}
                onChange={handleChange}
                placeholder={tRegister('postalCodePlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors['business_address.postal_code'] && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors['business_address.postal_code']}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('countryLabel')} *
              </label>
              <input
                type="text"
                name="business_address.country"
                maxLength={2}
                value={formData.business_address.country}
                onChange={handleChange}
                placeholder={tRegister('countryPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 uppercase outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors['business_address.country'] && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors['business_address.country']}</p>
              )}
            </div>
          </div>
        </div>

        {/* Tax and Payout */}
        <div className="border-t border-gray-100 pt-6">
          <h4 className="text-sm font-bold uppercase tracking-wider text-gray-400">
            {tRegister('payoutAndTax')}
          </h4>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('taxIdLabel')}
              </label>
              <input
                type="text"
                name="tax_id"
                value={formData.tax_id || ''}
                onChange={handleChange}
                placeholder={tRegister('taxIdPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
              />
              {fieldErrors.tax_id && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.tax_id}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                {tRegister('payoutCountryLabel')} *
              </label>
              <input
                type="text"
                name="payout_country"
                maxLength={2}
                value={formData.payout_country || ''}
                onChange={handleChange}
                placeholder={tRegister('payoutCountryPlaceholder')}
                className="mt-1 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 uppercase outline-none transition focus:border-[#0A2540] focus:ring-1 focus:ring-[#0A2540]"
                required
              />
              {fieldErrors.payout_country && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.payout_country}</p>
              )}
            </div>
          </div>
        </div>

        {/* Form Actions */}
        <div className="flex items-center gap-4 border-t border-gray-100 pt-6">
          <button
            type="submit"
            disabled={isSubmitting}
            className="flex-1 rounded-xl bg-[#0A2540] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#081e35] disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isSubmitting ? t('submittingButton') : t('submitButton')}
          </button>
          {onCancel && (
            <button
              type="button"
              onClick={onCancel}
              className="rounded-xl border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            >
              Cancel
            </button>
          )}
        </div>
      </form>
    </div>
  );
}

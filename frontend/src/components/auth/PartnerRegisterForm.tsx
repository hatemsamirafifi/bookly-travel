'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations, useLocale } from 'next-intl';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { partnerRegisterSchema } from '@/lib/validators/auth';
import { authApi } from '@/lib/api/auth';
import { useAuth } from '@/lib/hooks/useAuth';
import { AuthApiError } from '@/lib/api/auth';

type PartnerRegisterFormData = z.infer<typeof partnerRegisterSchema>;

interface PartnerRegisterFormProps {
  returnUrl?: string;
}

export function PartnerRegisterForm({ returnUrl }: PartnerRegisterFormProps) {
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
  } = useForm<PartnerRegisterFormData>({
    resolver: zodResolver(partnerRegisterSchema),
    defaultValues: { country: 'ES', payout_country: 'ES' },
  });

  const onSubmit = async (data: PartnerRegisterFormData) => {
    setServerError(null);
    try {
      // Map flat form structure into backend expected nested structure
      const payload = {
        name: data.name,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
        company_name: data.company_name,
        contact_email: data.contact_email,
        contact_phone: data.contact_phone,
        business_description: data.business_description,
        business_address: {
          street: data.street,
          city: data.city,
          state: data.state || '',
          postal_code: data.postal_code,
          country: data.country,
        },
        tax_id: data.tax_id || null,
        payout_country: data.payout_country,
      };

      const response = await authApi.registerPartner(payload);
      setAuth(response.data, response.token);
      setSuccess(true);

      setTimeout(() => {
        router.push(`/${locale}/partner`);
      }, 2000);
    } catch (err: unknown) {
      if (err instanceof AuthApiError && err.errors) {
        for (const [field, messages] of Object.entries(err.errors)) {
          setError(field as keyof PartnerRegisterFormData, {
            message: Array.isArray(messages) ? messages[0] : String(messages),
          });
        }
      } else {
        const message = err instanceof Error ? err.message : 'Registration failed. Please try again.';
        setServerError(message);
      }
    }
  };

  if (success) {
    return (
      <div className="flex flex-col items-center gap-4 py-8 text-center text-emerald-600 text-[0.9375rem] font-medium" role="status" aria-live="polite">
        <div className="flex items-center justify-center w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 text-3xl font-bold">✓</div>
        <h3 className="text-lg font-bold text-gray-900 mt-2">Registration Submitted!</h3>
        <p className="text-gray-600 max-w-sm">
          Your partner account registration was submitted. You will be redirected to the partner dashboard shortly.
        </p>
      </div>
    );
  }

  return (
    <form className="flex flex-col gap-6" onSubmit={handleSubmit(onSubmit)} noValidate>
      {/* Account Details Section */}
      <div>
        <h3 className="text-md font-bold text-[#0A2540] border-b border-gray-100 pb-2 mb-4">1. User Account Info</h3>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="name" className="text-sm font-semibold text-foreground">Contact Name</label>
            <input
              id="name"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.name ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. John Doe"
              {...register('name')}
            />
            {errors.name && <p className="text-[0.8125rem] text-error m-0">{errors.name.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="email" className="text-sm font-semibold text-foreground">Account Email</label>
            <input
              id="email"
              type="email"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.email ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="john@company.com"
              {...register('email')}
            />
            {errors.email && <p className="text-[0.8125rem] text-error m-0">{errors.email.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="password" className="text-sm font-semibold text-foreground">Password</label>
            <input
              id="password"
              type="password"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.password ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="Min 8 chars, 1 uppercase, 1 number"
              {...register('password')}
            />
            {errors.password && <p className="text-[0.8125rem] text-error m-0">{errors.password.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="password_confirmation" className="text-sm font-semibold text-foreground">Confirm Password</label>
            <input
              id="password_confirmation"
              type="password"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.password_confirmation ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="Repeat password"
              {...register('password_confirmation')}
            />
            {errors.password_confirmation && <p className="text-[0.8125rem] text-error m-0">{errors.password_confirmation.message}</p>}
          </div>
        </div>
      </div>

      {/* Business Details Section */}
      <div>
        <h3 className="text-md font-bold text-[#0A2540] border-b border-gray-100 pb-2 mb-4">2. Business details</h3>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="company_name" className="text-sm font-semibold text-foreground">Company / Business Name</label>
            <input
              id="company_name"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.company_name ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. Barcelona Tours SL"
              {...register('company_name')}
            />
            {errors.company_name && <p className="text-[0.8125rem] text-error m-0">{errors.company_name.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="tax_id" className="text-sm font-semibold text-foreground">Tax ID / VAT (Optional)</label>
            <input
              id="tax_id"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.tax_id ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. ESA1234567Z"
              {...register('tax_id')}
            />
            {errors.tax_id && <p className="text-[0.8125rem] text-error m-0">{errors.tax_id.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="contact_email" className="text-sm font-semibold text-foreground">Business Contact Email</label>
            <input
              id="contact_email"
              type="email"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.contact_email ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="bookings@company.com"
              {...register('contact_email')}
            />
            {errors.contact_email && <p className="text-[0.8125rem] text-error m-0">{errors.contact_email.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="contact_phone" className="text-sm font-semibold text-foreground">Business Phone</label>
            <input
              id="contact_phone"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.contact_phone ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. +34 93 123 4567"
              {...register('contact_phone')}
            />
            {errors.contact_phone && <p className="text-[0.8125rem] text-error m-0">{errors.contact_phone.message}</p>}
          </div>

          <div className="sm:col-span-2 flex flex-col gap-1.5">
            <label htmlFor="business_description" className="text-sm font-semibold text-foreground">Business Description</label>
            <textarea
              id="business_description"
              rows={3}
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.business_description ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="Describe your tour offerings, experience, and why travelers love your products..."
              {...register('business_description')}
            />
            {errors.business_description && <p className="text-[0.8125rem] text-error m-0">{errors.business_description.message}</p>}
          </div>
        </div>
      </div>

      {/* Address & Payout Details Section */}
      <div>
        <h3 className="text-md font-bold text-[#0A2540] border-b border-gray-100 pb-2 mb-4">3. Address & Payout Location</h3>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="street" className="text-sm font-semibold text-foreground">Street Address</label>
            <input
              id="street"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.street ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. Gran Via 123"
              {...register('street')}
            />
            {errors.street && <p className="text-[0.8125rem] text-error m-0">{errors.street.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="city" className="text-sm font-semibold text-foreground">City</label>
            <input
              id="city"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.city ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. Barcelona"
              {...register('city')}
            />
            {errors.city && <p className="text-[0.8125rem] text-error m-0">{errors.city.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="state" className="text-sm font-semibold text-foreground">State / Region (Optional)</label>
            <input
              id="state"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.state ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. Catalonia"
              {...register('state')}
            />
            {errors.state && <p className="text-[0.8125rem] text-error m-0">{errors.state.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="postal_code" className="text-sm font-semibold text-foreground">Postal / ZIP Code</label>
            <input
              id="postal_code"
              type="text"
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all ${errors.postal_code ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. 08001"
              {...register('postal_code')}
            />
            {errors.postal_code && <p className="text-[0.8125rem] text-error m-0">{errors.postal_code.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="country" className="text-sm font-semibold text-foreground">Country Code (ISO 2)</label>
            <input
              id="country"
              type="text"
              maxLength={2}
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all uppercase ${errors.country ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. ES"
              {...register('country')}
            />
            {errors.country && <p className="text-[0.8125rem] text-error m-0">{errors.country.message}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label htmlFor="payout_country" className="text-sm font-semibold text-foreground">Payout Country Code (ISO 2)</label>
            <input
              id="payout_country"
              type="text"
              maxLength={2}
              className={`w-full px-3.5 py-2 border-[1.5px] rounded-md bg-background text-foreground text-[0.9375rem] outline-none transition-all uppercase ${errors.payout_country ? 'border-error' : 'border-border focus:border-primary'}`}
              placeholder="e.g. ES"
              {...register('payout_country')}
            />
            {errors.payout_country && <p className="text-[0.8125rem] text-error m-0">{errors.payout_country.message}</p>}
          </div>
        </div>
      </div>

      {serverError && (
        <div className="px-4 py-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-sm" role="alert">
          {serverError}
        </div>
      )}

      <button
        type="submit"
        className="flex items-center justify-center gap-2 w-full px-4 py-3 bg-[#0A2540] text-white text-[0.9375rem] font-semibold rounded-md transition-all hover:bg-[#FFB800] hover:text-[#0A2540] active:scale-[0.99] disabled:opacity-75 disabled:cursor-not-allowed"
        disabled={isSubmitting}
      >
        {isSubmitting && <span className="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin" />}
        Submit Application
      </button>

      <p className="text-center text-sm text-gray-500 m-0">
        Already registered?{' '}
        <Link href={`/${locale}/auth/login`} className="text-[#0A2540] font-semibold hover:underline">
          Sign In
        </Link>
      </p>
    </form>
  );
}

'use client';

import { FormEvent } from 'react';
import { useTranslations } from 'next-intl';
import type { TravelerProfile } from '@/types/traveler';
import { Input } from '@/components/ui/input';

type ProfileField = 'first_name' | 'last_name' | 'phone';

interface ProfileFormProps {
  profile: TravelerProfile;
  onChange: (profile: TravelerProfile) => void;
  onSubmit: (event: FormEvent) => void;
  saving?: boolean;
  errors?: Partial<Record<ProfileField, string>>;
}

export default function ProfileForm({ profile, onChange, onSubmit, saving = false, errors }: ProfileFormProps) {
  const t = useTranslations('traveler.profile');

  return (
    <form onSubmit={onSubmit} className="rounded-xl border border-border bg-surface p-5 shadow-sm sm:p-6">
      <h2 className="mb-4 text-lg font-semibold text-bookly-navy">{t('personalInfo')}</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('firstName')}
          <Input
            value={profile.first_name}
            onChange={(e) => onChange({ ...profile, first_name: e.target.value })}
            required
            aria-invalid={!!errors?.first_name}
            aria-describedby={errors?.first_name ? 'profile-first-name-error' : undefined}
            className={errors?.first_name ? 'border-red-600' : undefined}
          />
          {errors?.first_name && (
            <span id="profile-first-name-error" className="text-xs font-normal text-red-600" role="alert">{errors.first_name}</span>
          )}
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('lastName')}
          <Input
            value={profile.last_name}
            onChange={(e) => onChange({ ...profile, last_name: e.target.value })}
            required
            aria-invalid={!!errors?.last_name}
            aria-describedby={errors?.last_name ? 'profile-last-name-error' : undefined}
            className={errors?.last_name ? 'border-red-600' : undefined}
          />
          {errors?.last_name && (
            <span id="profile-last-name-error" className="text-xs font-normal text-red-600" role="alert">{errors.last_name}</span>
          )}
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('email')}
          <Input value={profile.email} readOnly className="bg-surface-alt" />
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('phone')}
          <Input
            value={profile.phone || ''}
            onChange={(e) => onChange({ ...profile, phone: e.target.value })}
            inputMode="tel"
            pattern="^\+?[0-9\s().-]{7,20}$"
            aria-invalid={!!errors?.phone}
            aria-describedby={errors?.phone ? 'profile-phone-hint profile-phone-error' : 'profile-phone-hint'}
            className={errors?.phone ? 'border-red-600' : undefined}
          />
          <span id="profile-phone-hint" className="text-xs font-normal text-gray-500">{t('phoneHint')}</span>
          {errors?.phone && (
            <span id="profile-phone-error" className="text-xs font-normal text-red-600" role="alert">{errors.phone}</span>
          )}
        </label>
      </div>
      <button disabled={saving} className="mt-5 rounded-xl bg-bookly-gold px-5 py-2.5 text-sm font-semibold text-bookly-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:opacity-50">
        {saving ? t('saving') : t('saveChanges')}
      </button>
    </form>
  );
}

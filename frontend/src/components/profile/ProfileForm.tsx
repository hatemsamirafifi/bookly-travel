'use client';

import { FormEvent } from 'react';
import { useTranslations } from 'next-intl';
import type { TravelerProfile } from '@/types/traveler';

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
    <form onSubmit={onSubmit} className="rounded-lg border border-gray-200 bg-white p-5">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('personalInfo')}</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('firstName')}
          <input
            value={profile.first_name}
            onChange={(e) => onChange({ ...profile, first_name: e.target.value })}
            required
            aria-invalid={!!errors?.first_name}
            className={`rounded-lg border px-3 py-2 ${errors?.first_name ? 'border-red-600' : 'border-gray-300'}`}
          />
          {errors?.first_name && (
            <span className="text-xs font-normal text-red-600" role="alert">{errors.first_name}</span>
          )}
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('lastName')}
          <input
            value={profile.last_name}
            onChange={(e) => onChange({ ...profile, last_name: e.target.value })}
            required
            aria-invalid={!!errors?.last_name}
            className={`rounded-lg border px-3 py-2 ${errors?.last_name ? 'border-red-600' : 'border-gray-300'}`}
          />
          {errors?.last_name && (
            <span className="text-xs font-normal text-red-600" role="alert">{errors.last_name}</span>
          )}
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('email')}
          <input value={profile.email} readOnly className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2" />
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('phone')}
          <input
            value={profile.phone || ''}
            onChange={(e) => onChange({ ...profile, phone: e.target.value })}
            inputMode="tel"
            pattern="^\+?[0-9\s().-]{7,20}$"
            aria-invalid={!!errors?.phone}
            className={`rounded-lg border px-3 py-2 ${errors?.phone ? 'border-red-600' : 'border-gray-300'}`}
          />
          <span className="text-xs font-normal text-gray-500">{t('phoneHint')}</span>
          {errors?.phone && (
            <span className="text-xs font-normal text-red-600" role="alert">{errors.phone}</span>
          )}
        </label>
      </div>
      <button disabled={saving} className="mt-5 rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540] disabled:opacity-50">
        {saving ? t('saving') : t('saveChanges')}
      </button>
    </form>
  );
}

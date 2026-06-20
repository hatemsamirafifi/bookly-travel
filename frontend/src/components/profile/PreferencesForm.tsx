'use client';

import { useTranslations } from 'next-intl';
import type { TravelerProfile } from '@/types/traveler';

interface PreferencesFormProps {
  profile: TravelerProfile;
  onChange: (profile: TravelerProfile) => void;
}

export default function PreferencesForm({ profile, onChange }: PreferencesFormProps) {
  const t = useTranslations('traveler.profile');

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('preferences')}</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('preferredLanguage')}
          <select
            value={profile.preferred_language}
            onChange={(e) => onChange({ ...profile, preferred_language: e.target.value as TravelerProfile['preferred_language'] })}
            className="rounded-lg border border-gray-300 px-3 py-2"
          >
            <option value="en">English</option>
            <option value="es">Spanish</option>
            <option value="it">Italian</option>
          </select>
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('currency')}
          <select
            value={profile.preferred_currency}
            onChange={(e) => onChange({ ...profile, preferred_currency: e.target.value })}
            className="rounded-lg border border-gray-300 px-3 py-2"
          >
            <option value="EUR">EUR</option>
            <option value="USD">USD</option>
            <option value="GBP">GBP</option>
          </select>
        </label>
      </div>
      <label className="mt-4 flex items-center gap-2 text-sm text-gray-700">
        <input
          type="checkbox"
          checked={profile.marketing_emails}
          onChange={(e) => onChange({ ...profile, marketing_emails: e.target.checked })}
        />
        {t('marketing')}
      </label>
    </div>
  );
}

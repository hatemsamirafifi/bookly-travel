'use client';

import { useLocale, useTranslations } from 'next-intl';
import type { TravelerProfile } from '@/types/traveler';

interface PreferencesFormProps {
  profile: TravelerProfile;
  onChange: (profile: TravelerProfile) => void;
}

export default function PreferencesForm({ profile, onChange }: PreferencesFormProps) {
  const t = useTranslations('traveler.profile');
  const locale = useLocale();
  const languageNames = new Intl.DisplayNames([locale], { type: 'language' });

  return (
    <div className="rounded-xl border border-border bg-surface p-5 shadow-sm sm:p-6">
      <h2 className="mb-4 text-lg font-semibold text-bookly-navy">{t('preferences')}</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('preferredLanguage')}
          <select
            value={profile.preferred_language}
            onChange={(e) => onChange({ ...profile, preferred_language: e.target.value as TravelerProfile['preferred_language'] })}
            className="h-10 rounded-lg border border-border bg-surface px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
          >
            <option value="en">{languageNames.of('en')}</option>
            <option value="es">{languageNames.of('es')}</option>
            <option value="it">{languageNames.of('it')}</option>
          </select>
        </label>
        <label className="grid gap-1 text-sm font-medium text-gray-700">
          {t('currency')}
          <select
            value={profile.preferred_currency}
            onChange={(e) => onChange({ ...profile, preferred_currency: e.target.value })}
            className="h-10 rounded-lg border border-border bg-surface px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
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

'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { usePathname, useRouter } from 'next/navigation';
import {
  changeTravelerPassword,
  getTravelerProfile,
  updateTravelerProfile,
} from '@/lib/api/traveler';
import type { TravelerProfile } from '@/types/traveler';

const emptyProfile: TravelerProfile = {
  id: '',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  preferred_language: 'en',
  preferred_currency: 'EUR',
  marketing_emails: true,
};

export default function ProfileSettings() {
  const t = useTranslations('traveler.profile');
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();
  const [profile, setProfile] = useState<TravelerProfile>(emptyProfile);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [password, setPassword] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  useEffect(() => {
    getTravelerProfile()
      .then((res) => setProfile(res.data))
      .catch(() => setError(t('loadError')))
      .finally(() => setLoading(false));
  }, [t]);

  const submitProfile = async (event: FormEvent) => {
    event.preventDefault();
    const trimmedPhone = profile.phone?.trim() || '';
    if (trimmedPhone && !/^\+?[0-9\s().-]{7,20}$/.test(trimmedPhone)) {
      setError(t('invalidPhone'));
      setMessage(null);
      return;
    }

    setSaving(true);
    setError(null);
    setMessage(null);
    const previousLocale = locale;
    try {
      const res = await updateTravelerProfile({
        first_name: profile.first_name,
        last_name: profile.last_name,
        phone: trimmedPhone || null,
        preferred_language: profile.preferred_language,
        preferred_currency: profile.preferred_currency,
        marketing_emails: profile.marketing_emails,
      });
      setProfile(res.data);
      setMessage(t('saved'));
      if (res.data.preferred_language !== previousLocale) {
        const nextPath = pathname.replace(`/${previousLocale}`, `/${res.data.preferred_language}`);
        router.replace(nextPath);
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('saveError'));
    } finally {
      setSaving(false);
    }
  };

  const submitPassword = async (event: FormEvent) => {
    event.preventDefault();
    setPasswordSaving(true);
    setError(null);
    setMessage(null);
    try {
      await changeTravelerPassword(password);
      setPassword({ current_password: '', new_password: '', new_password_confirmation: '' });
      setMessage(t('passwordUpdated'));
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('passwordError'));
    } finally {
      setPasswordSaving(false);
    }
  };

  if (loading) {
    return <div className="h-64 animate-pulse rounded-lg bg-gray-100" />;
  }

  return (
    <div className="space-y-6">
      {(message || error) && (
        <div className={`rounded-lg p-4 text-sm ${error ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'}`} role="status">
          {error || message}
        </div>
      )}

      <form onSubmit={submitProfile} className="rounded-lg border border-gray-200 bg-white p-5">
        <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('personalInfo')}</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="grid gap-1 text-sm font-medium text-gray-700">
            {t('firstName')}
            <input
              value={profile.first_name}
              onChange={(e) => setProfile({ ...profile, first_name: e.target.value })}
              required
              className="rounded-lg border border-gray-300 px-3 py-2"
            />
          </label>
          <label className="grid gap-1 text-sm font-medium text-gray-700">
            {t('lastName')}
            <input
              value={profile.last_name}
              onChange={(e) => setProfile({ ...profile, last_name: e.target.value })}
              required
              className="rounded-lg border border-gray-300 px-3 py-2"
            />
          </label>
          <label className="grid gap-1 text-sm font-medium text-gray-700">
            {t('email')}
            <input value={profile.email} readOnly className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2" />
          </label>
          <label className="grid gap-1 text-sm font-medium text-gray-700">
            {t('phone')}
            <input
              value={profile.phone || ''}
              onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
              inputMode="tel"
              pattern="^\+?[0-9\s().-]{7,20}$"
              className="rounded-lg border border-gray-300 px-3 py-2"
            />
            <span className="text-xs font-normal text-gray-500">{t('phoneHint')}</span>
          </label>
          <label className="grid gap-1 text-sm font-medium text-gray-700">
            {t('preferredLanguage')}
            <select
              value={profile.preferred_language}
              onChange={(e) => setProfile({ ...profile, preferred_language: e.target.value as TravelerProfile['preferred_language'] })}
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
              onChange={(e) => setProfile({ ...profile, preferred_currency: e.target.value })}
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
            onChange={(e) => setProfile({ ...profile, marketing_emails: e.target.checked })}
          />
          {t('marketing')}
        </label>
        <button disabled={saving} className="mt-5 rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540] disabled:opacity-50">
          {saving ? t('saving') : t('saveChanges')}
        </button>
      </form>

      <form onSubmit={submitPassword} className="rounded-lg border border-gray-200 bg-white p-5">
        <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('changePassword')}</h2>
        <div className="grid gap-4 sm:grid-cols-3">
          <PasswordInput label={t('currentPassword')} value={password.current_password} onChange={(value) => setPassword({ ...password, current_password: value })} />
          <PasswordInput label={t('newPassword')} value={password.new_password} onChange={(value) => setPassword({ ...password, new_password: value })} />
          <PasswordInput label={t('confirmPassword')} value={password.new_password_confirmation} onChange={(value) => setPassword({ ...password, new_password_confirmation: value })} />
        </div>
        <button disabled={passwordSaving} className="mt-5 rounded-xl border border-[#0A2540] px-5 py-2.5 text-sm font-semibold text-[#0A2540] disabled:opacity-50">
          {passwordSaving ? t('updating') : t('updatePassword')}
        </button>
      </form>
    </div>
  );
}

function PasswordInput({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <label className="grid gap-1 text-sm font-medium text-gray-700">
      {label}
      <input
        type="password"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        required
        minLength={8}
        className="rounded-lg border border-gray-300 px-3 py-2"
      />
    </label>
  );
}

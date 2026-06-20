'use client';

import { FormEvent, useState, useEffect } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { usePathname, useRouter } from 'next/navigation';
import { useProfile } from '@/hooks/useProfile';
import { useChangePassword } from '@/hooks/useChangePassword';
import { profileSchema } from '@/lib/validators/profile';
import ProfileForm from './ProfileForm';
import PasswordChangeForm from './PasswordChangeForm';
import PreferencesForm from './PreferencesForm';
import Toast from '@/components/ui/Toast';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
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
  const { data: profileData, isLoading, updateProfile, isUpdating } = useProfile();
  const changePassword = useChangePassword();

  const [profile, setProfile] = useState<TravelerProfile>(() => profileData ?? emptyProfile);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<'first_name' | 'last_name' | 'phone', string>>>({});

  // Sync fetched profile into local state when data arrives
  useEffect(() => {
    if (profileData) {
      // Syncing external data (API response) into local form state is a
      // legitimate pattern; disabling the overly strict rule here.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setProfile(profileData);
    }
  }, [profileData]);

  const submitProfile = async (event: FormEvent) => {
    event.preventDefault();
    const trimmedPhone = profile.phone?.trim() || '';

    // Validate at the boundary with the shared Zod schema so phone format and
    // required fields surface as field-level errors (Spec 011 verification gate).
    const payload = {
      first_name: profile.first_name,
      last_name: profile.last_name,
      phone: trimmedPhone || null,
      preferred_language: profile.preferred_language,
      preferred_currency: profile.preferred_currency,
      marketing_emails: profile.marketing_emails,
    };
    const parsed = profileSchema.safeParse(payload);
    if (!parsed.success) {
      const fe: Partial<Record<'first_name' | 'last_name' | 'phone', string>> = {};
      for (const issue of parsed.error.issues) {
        const field = issue.path[0];
        if (field === 'first_name' || field === 'last_name' || field === 'phone') {
          if (!fe[field]) {
            fe[field] = field === 'phone' ? t('invalidPhone') : t('saveError');
          }
        }
      }
      setFieldErrors(fe);
      setError(null);
      setMessage(null);
      return;
    }

    setFieldErrors({});
    setError(null);
    setMessage(null);
    const previousLocale = locale;
    try {
      const updated = await updateProfile({
        first_name: profile.first_name,
        last_name: profile.last_name,
        phone: trimmedPhone || null,
        preferred_language: profile.preferred_language,
        preferred_currency: profile.preferred_currency,
        marketing_emails: profile.marketing_emails,
      });
      setMessage(t('saved'));
      if (updated.preferred_language !== previousLocale) {
        const nextPath = pathname.replace(`/${previousLocale}`, `/${updated.preferred_language}`);
        router.replace(nextPath);
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('saveError'));
    }
  };

  const submitPassword = async (data: { current_password: string; new_password: string; new_password_confirmation: string }) => {
    setError(null);
    setMessage(null);
    try {
      await changePassword.mutateAsync(data);
      setMessage(t('passwordUpdated'));
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('passwordError'));
    }
  };

  if (isLoading) {
    return <LoadingSkeleton variant="detail" />;
  }

  return (
    <div className="space-y-6">
      {(message || error) && (
        <Toast
          message={error || message}
          type={error ? 'error' : 'success'}
          onClose={() => {
            setMessage(null);
            setError(null);
          }}
        />
      )}

      <ProfileForm
        profile={profile}
        onChange={setProfile}
        onSubmit={submitProfile}
        saving={isUpdating}
        errors={fieldErrors}
      />

      <PreferencesForm profile={profile} onChange={setProfile} />

      <PasswordChangeForm onSubmit={submitPassword} saving={changePassword.isPending} />
    </div>
  );
}

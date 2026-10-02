'use client';

import { FormEvent, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Input } from '@/components/ui/input';

interface PasswordChangeFormProps {
  onSubmit: (data: { current_password: string; new_password: string; new_password_confirmation: string }) => Promise<void>;
  saving?: boolean;
}

export default function PasswordChangeForm({ onSubmit, saving = false }: PasswordChangeFormProps) {
  const t = useTranslations('traveler.profile');
  const [password, setPassword] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await onSubmit(password);
      setPassword({ current_password: '', new_password: '', new_password_confirmation: '' });
    } catch {
      // The parent surfaces the failure; retain the fields so a retry is possible.
    }
  };

  return (
    <form onSubmit={handleSubmit} className="rounded-xl border border-border bg-surface p-5 shadow-sm sm:p-6">
      <h2 className="mb-4 text-lg font-semibold text-bookly-navy">{t('changePassword')}</h2>
      <div className="grid gap-4 sm:grid-cols-3">
        <PasswordInput
          label={t('currentPassword')}
          value={password.current_password}
          onChange={(value) => setPassword({ ...password, current_password: value })}
        />
        <PasswordInput
          label={t('newPassword')}
          value={password.new_password}
          onChange={(value) => setPassword({ ...password, new_password: value })}
        />
        <PasswordInput
          label={t('confirmPassword')}
          value={password.new_password_confirmation}
          onChange={(value) => setPassword({ ...password, new_password_confirmation: value })}
        />
      </div>
      <button disabled={saving} className="mt-5 rounded-xl border border-bookly-navy px-5 py-2.5 text-sm font-semibold text-bookly-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:opacity-50">
        {saving ? t('updating') : t('updatePassword')}
      </button>
    </form>
  );
}

function PasswordInput({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <label className="grid gap-1 text-sm font-medium text-text-muted">
      {label}
      <Input
        type="password"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        required
        minLength={8}
      />
    </label>
  );
}

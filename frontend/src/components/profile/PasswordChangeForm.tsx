'use client';

import { FormEvent, useState } from 'react';
import { useTranslations } from 'next-intl';

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
    await onSubmit(password);
    setPassword({ current_password: '', new_password: '', new_password_confirmation: '' });
  };

  return (
    <form onSubmit={handleSubmit} className="rounded-lg border border-gray-200 bg-white p-5">
      <h2 className="mb-4 text-lg font-semibold text-gray-900">{t('changePassword')}</h2>
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
      <button disabled={saving} className="mt-5 rounded-xl border border-[#0A2540] px-5 py-2.5 text-sm font-semibold text-[#0A2540] disabled:opacity-50">
        {saving ? t('updating') : t('updatePassword')}
      </button>
    </form>
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

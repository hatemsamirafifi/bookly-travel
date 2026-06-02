'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { apiClient } from '@/lib/api/client';
import { useAuth } from '@/lib/hooks/useAuth';

interface WishlistButtonProps {
  tourId: string | number;
  locale: string;
  initialSaved?: boolean;
  compact?: boolean;
}

export default function WishlistButton({ tourId, locale, initialSaved = false, compact = false }: WishlistButtonProps) {
  const t = useTranslations('traveler.wishlistButton');
  const { user } = useAuth();
  const [saved, setSaved] = useState(initialSaved);
  const [showPrompt, setShowPrompt] = useState(false);
  const [busy, setBusy] = useState(false);

  const toggle = async () => {
    if (!user) {
      setShowPrompt(true);
      return;
    }
    const previous = saved;
    setSaved(!saved);
    setBusy(true);
    try {
      if (previous) {
        await apiClient<void>(`/traveler/wishlist/${encodeURIComponent(String(tourId))}`, {
          method: 'DELETE',
          requireCsrf: true,
        });
      } else {
        await apiClient('/traveler/wishlist', {
          method: 'POST',
          requireCsrf: true,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ tour_id: tourId }),
        });
      }
    } catch {
      setSaved(previous);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <button
        type="button"
        onClick={toggle}
        disabled={busy}
        aria-pressed={saved}
        aria-label={saved ? t('remove') : t('save')}
        className={`${compact ? 'h-9 w-9' : 'h-10 w-10'} inline-flex items-center justify-center rounded-full border border-gray-200 bg-white/95 text-lg shadow-sm transition hover:border-[#FFB800] disabled:opacity-60`}
      >
        <span aria-hidden="true" className={saved ? 'text-red-600' : 'text-gray-500'}>{saved ? '♥' : '♡'}</span>
      </button>

      {showPrompt && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" role="dialog" aria-modal="true">
          <div className="max-w-sm rounded-lg bg-white p-5 shadow-xl">
            <h2 className="text-lg font-semibold text-[#0A2540]">{t('promptTitle')}</h2>
            <p className="mt-2 text-sm text-gray-600">{t('promptBody')}</p>
            <div className="mt-5 flex gap-2">
              <Link href={`/${locale}/auth/login`} className="rounded-lg bg-[#FFB800] px-4 py-2 text-sm font-semibold text-[#0A2540]">
                {t('signIn')}
              </Link>
              <button onClick={() => setShowPrompt(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                {t('notNow')}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

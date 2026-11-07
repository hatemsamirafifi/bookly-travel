'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslations } from 'next-intl';
import { useWishlist } from '@/lib/wishlist/useWishlist';

interface WishlistHeartButtonProps {
  tourId: number | string;
  tourTitle?: string;
  compact?: boolean;
  className?: string;
}

const DEBOUNCE_MS = 350;

export default function WishlistHeartButton({
  tourId,
  tourTitle,
  compact = true,
  className = '',
}: WishlistHeartButtonProps) {
  const t = useTranslations('traveler.wishlistButton');
  const { isSaved, toggle, isLoading, error } = useWishlist(tourId);
  const [liveAnnouncement, setLiveAnnouncement] = useState<string>('');

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Tracks the desired end state across rapid clicks: alternates from the
  // last pending intent (or the committed baseline) so that intermediate
  // clicks only update intent. Only the final debounced flush acts.
  const pendingIntentRef = useRef<boolean | null>(null);
  const isSavedRef = useRef(isSaved);
  const toggleRef = useRef(toggle);
  const tourIdRef = useRef(tourId);
  const mountedRef = useRef(true);
  const title = tourTitle || 'Tour';

  useEffect(() => {
    isSavedRef.current = isSaved;
    toggleRef.current = toggle;
  }, [isSaved, toggle]);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  useEffect(() => {
    tourIdRef.current = tourId;
  }, [tourId]);

  // A failed toggle rolls back in the hook: the optimistic announcement
  // must not claim success any longer. Derive displayed announcement
  // during render to avoid cascading renders and synchronous setState in effects.
  const currentAnnouncement = error ? '' : liveAnnouncement;

  const flush = useCallback((scheduledTourId: number | string) => {
    debounceRef.current = null;
    const willSave = pendingIntentRef.current;
    pendingIntentRef.current = null;
    if (!mountedRef.current || tourIdRef.current !== scheduledTourId) return;
    // Net-zero (rapid toggling back to baseline): no request.
    if (willSave === null || willSave === isSavedRef.current) return;
    void Promise.resolve(toggleRef.current()).then(() => {
      if (!mountedRef.current || tourIdRef.current !== scheduledTourId) return;
      setLiveAnnouncement(
        willSave
          ? t('savedWith', { title })
          : t('removedWith', { title })
      );
    });
  }, [t, title]);

  const handleClick = useCallback(
    (e: React.MouseEvent) => {
      e.preventDefault();
      e.stopPropagation();

      const scheduledTourId = tourIdRef.current;
      const previousIntent = pendingIntentRef.current ?? isSavedRef.current;
      pendingIntentRef.current = !previousIntent;

      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      debounceRef.current = setTimeout(() => flush(scheduledTourId), DEBOUNCE_MS);
    },
    [flush]
  );

  const label = isSaved
    ? t('removeAria', { title })
    : t('saveAria', { title });

  return (
    <>
      <button
        type="button"
        data-testid="wishlist-button"
        data-tour-id={tourId}
        data-compact={compact ? 'true' : 'false'}
        onClick={handleClick}
        disabled={isLoading}
        aria-label={label}
        aria-pressed={isSaved}
        className={`flex min-h-11 min-w-11 items-center justify-center rounded-full bg-surface/85 backdrop-blur-xs transition hover:bg-surface hover:scale-105 active:scale-95 focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2 shadow-sm ${className}`}
      >
        <svg
          className={`h-5 w-5 transition-colors ${isSaved ? 'fill-red-500 text-red-500' : 'fill-none text-bookly-navy hover:text-red-500'
            }`}
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth={1.75}
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"
          />
        </svg>
      </button>

      {/* Screen-reader live region */}
      <span className="sr-only" aria-live="polite" aria-atomic="true">
        {currentAnnouncement}
      </span>
    </>
  );
}

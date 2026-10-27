'use client';

import { useId, useState, useRef, useCallback, useEffect, useSyncExternalStore } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { addTravelerWishlistItem, getTravelerWishlistStatus, removeTravelerWishlistItem } from '@/lib/api/traveler';
import { getAuthToken } from '@/lib/auth/token';
import { useAuth } from '@/lib/hooks/useAuth';
import { Dialog } from '@/components/ui/Dialog';

interface WishlistButtonProps {
  tourId: string | number;
  locale: string;
  initialSaved?: boolean;
  compact?: boolean;
}

type ToggleFailure = 'saveFailed' | 'denied';
const subscribeToClient = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

function toggleFailureKind(error: unknown): ToggleFailure {
  const status = (error as { status?: unknown } | null)?.status;
  return status === 401 || status === 403 ? 'denied' : 'saveFailed';
}

export default function WishlistButton({ tourId, locale, initialSaved = false, compact = false }: WishlistButtonProps) {
  const t = useTranslations('traveler.wishlistButton');
  const tc = useTranslations('common');
  const { user } = useAuth();
  const hydrated = useSyncExternalStore(subscribeToClient, clientSnapshot, serverSnapshot);
  const promptDescriptionId = useId();
  const [saved, setSaved] = useState(initialSaved);
  const [showPrompt, setShowPrompt] = useState(false);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<'saved' | 'removed' | null>(null);
  const [toggleError, setToggleError] = useState<ToggleFailure | null>(null);
  const [statusError, setStatusError] = useState(false);
  const [statusAttempt, setStatusAttempt] = useState(0);
  const [completedStatusRead, setCompletedStatusRead] = useState<string | null>(null);

  const tokenValue = getAuthToken();
  const sessionKey = user ? `user:${user.id}` : tokenValue ? `token:${tokenValue}` : 'anonymous';
  const identityKey = `${String(tourId)}::${sessionKey}`;
  const statusReadKey = JSON.stringify([identityKey, statusAttempt]);
  const statusReadComplete = completedStatusRead === statusReadKey;
  const statusPending = Boolean(user || tokenValue) && !statusReadComplete;
  // The default heart is not an authenticated server baseline. The shared
  // hydration snapshot also keeps stored-token restoration out of SSR markup.
  const statusUnknown = !hydrated || statusPending || statusError;

  const [identity, setIdentity] = useState(identityKey);
  if (identity !== identityKey) {
    setIdentity(identityKey);
    setSaved(initialSaved);
    setNotice(null);
    setToggleError(null);
    setBusy(false);
    setStatusError(false);
    setCompletedStatusRead(null);
  }

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const scheduledRef = useRef<{ tour: string | number; session: string; desired: boolean } | null>(null);
  const confirmedRef = useRef(initialSaved);
  const busyRef = useRef<{ tour: string | number; session: string } | null>(null);
  const toggleSeqRef = useRef(0);
  const prevIdentityRef = useRef<string | null>(null);
  const tourIdRef = useRef(tourId);
  const sessionRef = useRef(sessionKey);
  const mountedRef = useRef(true);

  useEffect(() => {
    tourIdRef.current = tourId;
    sessionRef.current = sessionKey;
  });

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
    if (prevIdentityRef.current !== identityKey) {
      prevIdentityRef.current = identityKey;
      toggleSeqRef.current += 1;
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      scheduledRef.current = null;
      confirmedRef.current = initialSaved;
      busyRef.current = null;
    }
    if (!user && !getAuthToken()) return;
    const tour = tourId;
    const session = sessionKey;
    let active = true;
    getTravelerWishlistStatus(tour)
      .then((response) => {
        if (!active || !mountedRef.current || tourIdRef.current !== tour) return;
        if (sessionRef.current !== session) return;
        if (scheduledRef.current?.tour === tour && scheduledRef.current?.session === session) return;
        if (busyRef.current?.tour === tour && busyRef.current?.session === session) return;
        const value = Boolean(response.data[String(tour)]);
        confirmedRef.current = value;
        setSaved(value);
        setStatusError(false);
        setCompletedStatusRead(statusReadKey);
      })
      .catch(() => {
        if (!active || !mountedRef.current || tourIdRef.current !== tour) return;
        if (sessionRef.current !== session) return;
        setStatusError(true);
        setCompletedStatusRead(statusReadKey);
      });
    return () => { active = false; };
  }, [tourId, user, initialSaved, identityKey, sessionKey, statusReadKey]);

  const toggle = useCallback(() => {
    // Gate on a token (not just the hydrated user) so the button works
    // immediately on page load, before AuthProvider's async /me restoration
    // completes. A persisted token means the user is authenticated; the API
    // call below attaches it via authHeaders(). Only show the login prompt
    // when there is genuinely no token.
    if (!user && !getAuthToken()) {
      setShowPrompt(true);
      return;
    }
    // While the saved state is unknown the baseline cannot decide a
    // destructive toggle; the explicit status retry is the only way back.
    if (statusUnknown || !statusReadComplete) return;

    const next = !saved;
    setSaved(next);
    setNotice(null);
    setToggleError(null);

    // Debounce: clear pending timer and set new one
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    const tour = tourId;
    const session = sessionKey;
    scheduledRef.current = { tour, session, desired: next };
    debounceRef.current = setTimeout(() => {
      const scheduled = scheduledRef.current;
      scheduledRef.current = null;
      if (!mountedRef.current) return;
      if (!scheduled || scheduled.tour !== tourIdRef.current) return;
      if (sessionRef.current !== scheduled.session) return;
      // A rapid save-then-undo returns to the server baseline: no request,
      // no announcement, no rollback. Only a net change is sent.
      if (scheduled.desired === confirmedRef.current) return;
      const apply = scheduled.desired;
      const seq = ++toggleSeqRef.current;
      setBusy(true);
      busyRef.current = { tour, session: scheduled.session };
      (apply ? addTravelerWishlistItem(tour) : removeTravelerWishlistItem(tour)).then(() => {
        if (!mountedRef.current || tourIdRef.current !== tour) return;
        if (sessionRef.current !== scheduled.session || toggleSeqRef.current !== seq) return;
        confirmedRef.current = apply;
        setSaved(apply);
        setNotice(apply ? 'saved' : 'removed');
        setToggleError(null);
      }).catch((error: unknown) => {
        if (!mountedRef.current || tourIdRef.current !== tour) return;
        if (sessionRef.current !== scheduled.session || toggleSeqRef.current !== seq) return;
        setSaved(confirmedRef.current);
        setNotice(null);
        setToggleError(toggleFailureKind(error));
      }).finally(() => {
        if (tourIdRef.current !== tour || sessionRef.current !== scheduled.session) return;
        if (toggleSeqRef.current !== seq) return;
        busyRef.current = null;
        if (mountedRef.current) setBusy(false);
      });
    }, 400);
  }, [user, saved, tourId, statusUnknown, statusReadComplete, sessionKey]);

  return (
    <>
      <button
        type="button"
        onClick={toggle}
        disabled={busy || statusUnknown}
        aria-pressed={statusUnknown ? 'mixed' : saved}
        aria-busy={!hydrated || statusPending || busy}
        aria-label={saved ? t('remove') : t('save')}
        data-testid="wishlist-button"
        data-tour-id={tourId}
        className={`${compact ? 'h-9 w-9' : 'h-10 w-10'} min-h-11 min-w-11 inline-flex items-center justify-center rounded-full border border-gray-200 bg-white/95 text-lg shadow-sm transition hover:border-[#FFB800] disabled:opacity-60`}
      >
        <span aria-hidden="true" className={saved ? 'text-red-600' : 'text-gray-500'}>{saved ? '♥' : '♡'}</span>
      </button>

      {notice && (
        <p role="status" className="sr-only">{t(notice)}</p>
      )}

      {toggleError && (
        <p role="alert" className="mt-2 text-sm text-error">{t(toggleError)}</p>
      )}

      {statusError && (
        <div className="mt-2">
          <p role="alert" className="text-sm text-error">{t('statusFailed')}</p>
          <button
            type="button"
            onClick={() => setStatusAttempt((attempt) => attempt + 1)}
            className="mt-1 text-sm font-medium text-primary underline"
          >
            {tc('retry')}
          </button>
        </div>
      )}

      <Dialog
        open={showPrompt}
        onClose={() => setShowPrompt(false)}
        title={t('promptTitle')}
        closeLabel={t('notNow')}
        descriptionId={promptDescriptionId}
      >
        <p id={promptDescriptionId} className="text-sm text-gray-600">{t('promptBody')}</p>
        <div className="mt-5 flex gap-2">
          <Link href={`/${locale}/auth/login`} className="rounded-lg bg-[#FFB800] px-4 py-2 text-sm font-semibold text-[#0A2540]">
            {t('signIn')}
          </Link>
          <button type="button" onClick={() => setShowPrompt(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
            {t('notNow')}
          </button>
        </div>
      </Dialog>
    </>
  );
}

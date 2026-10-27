'use client';

import { useEffect, useId, useState, useSyncExternalStore } from 'react';
import { createPortal } from 'react-dom';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { formatOfferedDateUtc, type BookingSelectionView } from '@/lib/tours/booking-selection';
import { Dialog } from '@/components/ui/Dialog';
import AvailabilityCalendar from './AvailabilityCalendar';
import {
  BookingContinuation,
  BookingCountStepper,
  BookingReasonList,
  type BookingCardAuth,
} from './BookingCard';

export interface MobileBookingBarProps {
  locale: string;
  /** Validated exact per-person base from cents, or null when the supplied
   *  price is invalid (the reason list explains; no bogus zero renders). */
  baseDisplay: string | null;
  groupMin: number;
  groupMax: number;
  view: BookingSelectionView;
  selectedDate: string | null;
  participantCount: number | null;
  dateChanged: boolean;
  countChanged: boolean;
  onDateChange: (date: string | null) => void;
  onCountChange: (count: number | null) => void;
  onContinueClick: (event: React.MouseEvent) => void;
  auth: BookingCardAuth;
  /** Same pending rule as the desktop card: the dialog selectors stay inert
   *  while auth restores. */
  controlsDisabled: boolean;
}

const BAR_ACTION_CLASS =
  'inline-flex min-h-11 items-center justify-center rounded-xl bg-bookly-gold px-5 py-2.5 text-sm font-semibold text-bookly-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-50';

const subscribeToClient = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

export default function MobileBookingBar({
  locale,
  baseDisplay,
  groupMin,
  groupMax,
  view,
  selectedDate,
  participantCount,
  dateChanged,
  countChanged,
  onDateChange,
  onCountChange,
  onContinueClick,
  auth,
  controlsDisabled,
}: MobileBookingBarProps) {
  const t = useTranslations('tour');
  const tCommon = useTranslations('common');
  const dialogDateId = useId();
  const mounted = useSyncExternalStore(subscribeToClient, clientSnapshot, serverSnapshot);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [barNode, setBarNode] = useState<HTMLDivElement | null>(null);
  const [barHeight, setBarHeight] = useState(0);

  // Measured reservation: a callback ref restarts observation whenever the
  // bar (un)mounts — including unavailable-to-available transitions — and
  // releases everything on unmount. Page tokens are scoped and cleaned.
  useEffect(() => {
    const root = document.documentElement;
    if (!barNode) {
      root.style.removeProperty('--tour-booking-bar-height');
      root.style.removeProperty('scroll-padding-bottom');
      return;
    }
    const measure = () => {
      const height = barNode.offsetHeight;
      setBarHeight(height);
      root.style.setProperty('--tour-booking-bar-height', `${height}px`);
      root.style.setProperty('scroll-padding-bottom', `${height}px`);
    };
    measure();
    if (typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver(measure);
    observer.observe(barNode);
    return () => {
      observer.disconnect();
      root.style.removeProperty('--tour-booking-bar-height');
      root.style.removeProperty('scroll-padding-bottom');
    };
  }, [barNode]);

  if (view.reasons.includes('tour-unavailable')) {
    return null;
  }

  // A valid selection links onward (checkout when signed in, safe login with
  // the full query otherwise). While auth restores, no target is exposed.
  // An incomplete/invalid selection offers the shared edit dialog instead.
  const primaryHref =
    view.canContinue && view.checkoutUrl && auth.status !== 'pending'
      ? (auth.status === 'signed-in' ? view.checkoutUrl : auth.loginUrl)
      : null;
  const primaryPending = view.canContinue && view.checkoutUrl && auth.status === 'pending';

  const summaryDate =
    selectedDate !== null ? formatOfferedDateUtc(selectedDate, locale) : t('selectDate');
  const summaryCount = participantCount !== null ? `${participantCount}` : '–';

  return (
    <>
      {mounted &&
        createPortal(
          <div aria-hidden="true" className="lg:hidden" style={{ height: barHeight }} />,
          document.body,
        )}
      <div
        ref={setBarNode}
        data-testid="mobile-booking-bar"
        className="fixed inset-x-0 bottom-0 z-sticky border-t border-border bg-surface px-4 py-3 shadow-card lg:hidden"
        style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}
      >
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
          <p className="text-sm text-bookly-navy" data-testid="mobile-bar-summary">
            {baseDisplay !== null && (
              <span className="block font-bold">
                {baseDisplay} {t('perPerson')}
              </span>
            )}
            <span className="text-text-muted">
              {summaryDate} · {summaryCount}
              {view.estimateDisplay !== null ? ` · ${view.estimateDisplay}` : ''}
            </span>
          </p>
          <div className="flex flex-col items-stretch gap-1">
            {primaryHref !== null ? (
              <Link href={primaryHref} onClick={onContinueClick} className={BAR_ACTION_CLASS}>
                {t('mobileBookingAction')}
              </Link>
            ) : primaryPending ? (
              <button
                type="button"
                disabled
                aria-disabled="true"
                aria-busy="true"
                className={BAR_ACTION_CLASS}
              >
                {t('mobileBookingAction')}
              </button>
            ) : (
              <button
                type="button"
                onClick={() => setDialogOpen(true)}
                className={BAR_ACTION_CLASS}
              >
                {t('booking.editSelection')}
              </button>
            )}
            {primaryHref !== null && (
              <button
                type="button"
                onClick={() => setDialogOpen(true)}
                className="inline-flex min-h-11 items-center justify-center py-1 text-center text-xs font-medium text-bookly-navy underline underline-offset-2"
              >
                {t('booking.editSelection')}
              </button>
            )}
          </div>
        </div>
      </div>
      <Dialog
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
        title={t('booking.editSelection')}
        closeLabel={tCommon('back')}
      >
        <div className="space-y-4">
          <AvailabilityCalendar
            offeredDates={view.offeredFutureDates}
            selectedDate={selectedDate}
            onSelect={onDateChange}
            nextAvailableDate={view.initialDate}
            locale={locale}
            id={dialogDateId}
            disabled={controlsDisabled}
          />
          <div>
            <span className="mb-1 block text-sm font-medium text-primary">
              {t('participants')}
            </span>
            <BookingCountStepper
              count={participantCount}
              groupMin={groupMin}
              groupMax={groupMax}
              onCountChange={onCountChange}
              groupLabel={t('participants')}
              decreaseLabel={t('decreaseParticipants')}
              increaseLabel={t('increaseParticipants')}
              rangeLabel={t('allowedRange', { min: groupMin, max: groupMax })}
              idPrefix={`${dialogDateId}-count`}
              disabled={controlsDisabled}
            />
          </div>
          {view.estimateDisplay !== null && (
            <p aria-live="polite" className="text-sm text-text-muted">
              {t('booking.estimatedTotal')}:{' '}
              <span className="font-semibold text-primary">{view.estimateDisplay}</span>
            </p>
          )}
          <BookingReasonList
            reasons={view.reasons}
            groupMin={groupMin}
            groupMax={groupMax}
            dateChanged={dateChanged}
            countChanged={countChanged}
          />
          <BookingContinuation
            view={view}
            auth={auth}
            actionClassName={BAR_ACTION_CLASS}
            onContinueClick={onContinueClick}
          />
        </div>
      </Dialog>
    </>
  );
}

'use client';

import Link from 'next/link';
import { useTranslations } from 'next-intl';
import AvailabilityCalendar from './AvailabilityCalendar';
import { resolveBookingReasonMessage } from '@/lib/tours/booking-reasons';
import type { BookingInvalidReason, BookingSelectionView } from '@/lib/tours/booking-selection';

export interface BookingCardAuth {
  status: 'pending' | 'signed-in' | 'signed-out';
  loginUrl: string | null;
  registerUrl: string | null;
}

export interface BookingCountStepperProps {
  count: number | null;
  groupMin: number;
  groupMax: number;
  onCountChange: (count: number | null) => void;
  groupLabel: string;
  decreaseLabel: string;
  increaseLabel: string;
  rangeLabel: string;
  idPrefix: string;
  /** External inertness (e.g. auth restoration); combines with the min/max
   *  guards so a set control never acts while disabled. */
  disabled?: boolean;
}

/** Integer count stepper with explicit range bounds and a 44px minimum touch
 *  target. A null count (invalid bounds upstream) renders honestly instead of
 *  substituting a minimum. */
export function BookingCountStepper({
  count,
  groupMin,
  groupMax,
  onCountChange,
  groupLabel,
  decreaseLabel,
  increaseLabel,
  rangeLabel,
  idPrefix,
  disabled = false,
}: BookingCountStepperProps) {
  const display = count ?? '–';
  const atMin = count === null || count <= groupMin;
  const atMax = count === null || count >= groupMax;
  return (
    <div role="group" aria-label={groupLabel} className="flex items-center gap-2">
      <button
        type="button"
        onClick={() => {
          if (count !== null) onCountChange(Math.max(groupMin, count - 1));
        }}
        disabled={disabled || atMin}
        id={`${idPrefix}-decrease`}
        className="flex min-h-11 min-w-11 items-center justify-center rounded-md border border-border text-text-muted hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-50"
        aria-label={decreaseLabel}
      >
        -
      </button>
      <output aria-live="polite" className="w-10 text-center text-sm font-medium text-primary">
        {display}
      </output>
      <button
        type="button"
        onClick={() => {
          if (count !== null) onCountChange(Math.min(groupMax, count + 1));
        }}
        disabled={disabled || atMax}
        id={`${idPrefix}-increase`}
        className="flex min-h-11 min-w-11 items-center justify-center rounded-md border border-border text-text-muted hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-50"
        aria-label={increaseLabel}
      >
        +
      </button>
      <span className="ml-2 text-xs text-text-muted">{rangeLabel}</span>
    </div>
  );
}

export interface BookingReasonListProps {
  reasons: BookingInvalidReason[];
  groupMin: number;
  groupMax: number;
  dateChanged: boolean;
  countChanged: boolean;
}

/** Localized invalid-state explanations in stable model order. */
export function BookingReasonList({ reasons, groupMin, groupMax, dateChanged, countChanged }: BookingReasonListProps) {
  const t = useTranslations('tour');
  const tCommon = useTranslations('common');
  if (reasons.length === 0) return null;
  return (
    <ul aria-live="polite" className="space-y-1 text-xs text-text-muted">
      {reasons.map((reason) => {
        const message = resolveBookingReasonMessage(reason, {
          groupMin,
          groupMax,
          dateChanged,
          countChanged,
        });
        const text =
          message.scope === 'common'
            ? tCommon(message.key, message.values)
            : t(message.key, message.values);
        return <li key={reason}>{text}</li>;
      })}
    </ul>
  );
}

export interface BookingContinuationProps {
  view: BookingSelectionView;
  auth: BookingCardAuth;
  actionClassName: string;
  onContinueClick: (event: React.MouseEvent) => void;
}

/** The single continuation switch: a valid signed-in selection links
 *  straight to checkout; a valid signed-out selection routes through safe
 *  login (registration offered alongside) with the full query preserved;
 *  anything else exposes no enabled target. */
export function BookingContinuation({ view, auth, actionClassName, onContinueClick }: BookingContinuationProps) {
  const t = useTranslations('tour');
  const tAuth = useTranslations('auth');

  if (!view.canContinue || !view.checkoutUrl) {
    return (
      <button type="button" disabled aria-disabled="true" className={actionClassName}>
        {t('bookNow')}
      </button>
    );
  }

  if (auth.status === 'signed-in') {
    return (
      <Link href={view.checkoutUrl} onClick={onContinueClick} className={actionClassName}>
        {t('bookNow')}
      </Link>
    );
  }

  if (auth.status === 'signed-out' && auth.loginUrl) {
    return (
      <div className="space-y-2">
        <p className="text-xs text-text-muted">{t('authRecovery.continueNotice')}</p>
        <Link href={auth.loginUrl} onClick={onContinueClick} className={actionClassName}>
          {t('bookNow')}
        </Link>
        {auth.registerUrl && (
          <Link
            href={auth.registerUrl}
            onClick={onContinueClick}
            className="block w-full py-1 text-center text-xs font-medium text-bookly-navy underline underline-offset-2"
          >
            {tAuth('signin.registerLink')}
          </Link>
        )}
      </div>
    );
  }

  return (
    <button
      type="button"
      disabled
      aria-disabled="true"
      aria-busy="true"
      className={actionClassName}
    >
      {t('bookNow')}
    </button>
  );
}

export interface BookingCardProps {
  locale: string;
  /** Retained for controller compatibility: the single current-tour wishlist
   *  control lives in TourHeader now, so the card no longer consumes this. */
  tourId?: string | number;
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
  dateSelectId: string;
  onDateChange: (date: string | null) => void;
  onCountChange: (count: number | null) => void;
  onContinueClick: (event: React.MouseEvent) => void;
  auth: BookingCardAuth;
  cancellationPolicy?: string | null;
  /** Content language of the supplied cancellation policy; attached only to
   *  the policy text, never the controls. */
  cancellationPolicyLocale?: string;
  /** Inert date/count controls while auth restores; continuation keeps its
   *  own pending rule in BookingContinuation. */
  controlsDisabled: boolean;
}

const CARD_ACTION_CLASS =
  'block w-full rounded-xl bg-bookly-gold py-2.5 text-center text-sm font-semibold text-bookly-navy hover:bg-accent-dark focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-colors disabled:cursor-not-allowed disabled:opacity-50';

export default function BookingCard({
  locale,
  baseDisplay,
  groupMin,
  groupMax,
  view,
  selectedDate,
  participantCount,
  dateChanged,
  countChanged,
  dateSelectId,
  onDateChange,
  onCountChange,
  onContinueClick,
  auth,
  cancellationPolicy,
  cancellationPolicyLocale,
  controlsDisabled,
}: BookingCardProps) {
  const t = useTranslations('tour');
  const bookable = !view.reasons.includes('tour-unavailable');
  const hasCancellation =
    typeof cancellationPolicy === 'string' && cancellationPolicy.trim() !== '';

  return (
    <div className="rounded-lg border border-border bg-surface p-5 shadow-card">
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          {baseDisplay !== null && (
            <>
              <span className="text-2xl font-bold text-primary">{baseDisplay}</span>
              <span className="text-sm text-text-muted"> {t('perPerson')}</span>
            </>
          )}
        </div>
      </div>

      {!bookable ? (
        <div className="rounded-md bg-surface-alt py-3 text-center" role="status">
          <p className="text-sm font-medium text-text-muted">{t('unavailable')}</p>
          <p className="mt-1 text-xs text-text-muted">{t('unavailableHint')}</p>
          <button
            type="button"
            disabled
            aria-disabled="true"
            className="mt-3 w-full cursor-not-allowed rounded-lg bg-border py-2.5 text-center text-sm font-semibold text-text-muted"
          >
            {t('bookNow')}
          </button>
        </div>
      ) : (
        <>
          <AvailabilityCalendar
            offeredDates={view.offeredFutureDates}
            selectedDate={selectedDate}
            onSelect={onDateChange}
            nextAvailableDate={view.initialDate}
            locale={locale}
            id={dateSelectId}
            disabled={controlsDisabled}
          />

          <div className="mb-3 mt-3">
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
              idPrefix={`${dateSelectId}-count`}
              disabled={controlsDisabled}
            />
          </div>

          {view.estimateDisplay !== null && (
            <p aria-live="polite" className="mb-3 text-sm text-text-muted">
              {t('booking.estimatedTotal')}:{' '}
              <span className="font-semibold text-primary">{view.estimateDisplay}</span>
            </p>
          )}

          {hasCancellation && (
            <p
              lang={cancellationPolicyLocale ?? undefined}
              className="mb-3 whitespace-pre-line text-xs text-text-muted"
            >
              {cancellationPolicy}
            </p>
          )}

          <div className="mb-3">
            <BookingReasonList
              reasons={view.reasons}
              groupMin={groupMin}
              groupMax={groupMax}
              dateChanged={dateChanged}
              countChanged={countChanged}
            />
          </div>

          <BookingContinuation
            view={view}
            auth={auth}
            actionClassName={CARD_ACTION_CLASS}
            onContinueClick={onContinueClick}
          />
        </>
      )}
    </div>
  );
}

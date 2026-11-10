'use client';

import { useTranslations } from 'next-intl';
import {
  getContentLanguageName,
  getGuideLanguageNames,
} from '@/lib/tours/detail-presentation';
import { isPriceAmountValid, isPriceCurrencySupported } from '@/lib/tours/booking-selection';
import { formatCurrency } from '@/lib/utils';
import type { PricingInfo, TourOption } from '@/lib/api/types';

interface OptionSelectorProps {
  options: TourOption[];
  /** The currently selected option id (null = none selected yet). */
  value: number | null;
  onChange: (optionId: number) => void;
  locale: string;
  /** Inert during auth restoration. */
  disabled?: boolean;
  /** Unique dialog/card prefix for stable label/input association. */
  idPrefix?: string;
}

/** Validated exact per-person display from cents through the accepted
 *  formatter; falls back to the server-preformatted string when the raw
 *  amount/currency fails the supported-booking guards (never invented). */
export function formatOptionPriceDisplay(pricing: PricingInfo, locale: string): string | null {
  const formatted = pricing?.base_price?.formatted;
  if (isPriceAmountValid(pricing) && isPriceCurrencySupported(pricing)) {
    return formatCurrency(pricing.base_price.amount, pricing.base_price.currency, locale);
  }
  return typeof formatted === 'string' && formatted.trim() !== '' ? formatted : null;
}

/**
 * Bookable-option picker shared by the desktop booking card and the mobile
 * booking dialog. Each offered option discloses its actual content language
 * (title/pickup are authored in the option's own language), the localized
 * spoken guide languages and an exact per-person price. Selection is one
 * radio group in the shared controller state so a resize never remounts it
 * and the same choice reaches checkout on both surfaces.
 */
export default function OptionSelector({
  options,
  value,
  onChange,
  locale,
  disabled = false,
  idPrefix = 'option-selector',
}: OptionSelectorProps) {
  const t = useTranslations('tour');

  return (
    <div
      role="radiogroup"
      aria-label={t('bookingOptionPicker')}
      aria-describedby={`${idPrefix}-hint`}
      className="space-y-2"
    >
      <p id={`${idPrefix}-hint`} className="mb-1 block text-sm font-medium text-primary">
        {t('bookingOptionPickerHint')}
      </p>
      {options.map((option) => {
        const checked = value === option.id;
        const priceDisplay = formatOptionPriceDisplay(option.pricing, locale);
        const guideNames = getGuideLanguageNames(option.guide_languages ?? [], locale);
        const optionLanguageNotice =
          option.content_locale !== locale
            ? t('optionContentLanguage', {
                language: getContentLanguageName(option.content_locale, locale),
              })
            : null;
        return (
          <button
            key={option.id}
            type="button"
            role="radio"
            aria-checked={checked}
            onClick={() => onChange(option.id)}
            disabled={disabled}
            className={`w-full rounded-lg border p-3 text-left space-y-2 transition-colors ${
              checked ? 'border-accent bg-accent/5' : 'border-border bg-surface hover:border-text-muted/40'
            } disabled:cursor-not-allowed disabled:opacity-60`}
            data-testid={`tour-option-${option.id}`}
          >
            <div className="flex items-start justify-between gap-2">
              <span lang={option.content_locale} className="text-sm font-semibold text-bookly-navy">
                {option.title}
              </span>
              {priceDisplay !== null && (
                <span className="shrink-0 text-sm font-bold text-bookly-navy">{priceDisplay}</span>
              )}
            </div>
            {(option.start_time ?? '') !== '' && (
              <p className="text-xs text-text-muted">
                {t('optionStartTime')}: {option.start_time}
              </p>
            )}
            {guideNames.length > 0 && (
              <p className="text-xs text-text-muted">
                {t('liveGuide')}: {guideNames.join(', ')}
              </p>
            )}
            {typeof option.pickup_instructions === 'string' && option.pickup_instructions.trim() !== '' && (
              <p
                lang={option.content_locale}
                className="whitespace-pre-line text-xs text-text-muted"
              >
                {t('optionPickupInstructions')}: {option.pickup_instructions}
              </p>
            )}
            {optionLanguageNotice && (
              <p className="text-xs font-medium text-warning">{optionLanguageNotice}</p>
            )}
          </button>
        );
      })}
    </div>
  );
}

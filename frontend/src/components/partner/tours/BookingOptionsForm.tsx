'use client';

import { useTranslations } from 'next-intl';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Plus, Trash2, Clock, Calendar, MapPin, Users } from 'lucide-react';
import { pickFieldErrors } from '@/lib/validators/partner';
import type { BookingOptionFormInput, SourceLocale } from '@/types/tour';

/** Weekday coordinates are the contract's 0=Sunday..6=Saturday codes; the
 *  short names stay display-only via the page catalog. */
const WEEKDAY_CODES = [0, 1, 2, 3, 4, 5, 6] as const;

interface BookingOptionsFormProps {
  rows: BookingOptionFormInput[];
  disabled?: boolean;
  /** Original writing language of the tour content (titles/instructions). */
  sourceLocale?: SourceLocale;
  onAdd: () => void;
  onRemove: (id: string) => void;
  onUpdate: (id: string, updates: Partial<Omit<BookingOptionFormInput, 'id'>>) => void;
  /** Client-invalidated paths carrying catalog keys (options.<i>.<field>). */
  validationErrors?: Record<string, string>;
  /** Already-localized server messages keyed by form path. */
  serverErrors?: Record<string, string>;
}

/**
 * Authored booking options editor (create wizard + tour edit page). Purely
 * presentational: rows arrive as props (preserving persistent ids) and every
 * mutation flows through the supplied callbacks, so the create wizard stores
 * them in the wizard store while the edit page keeps them page-local.
 */
export function BookingOptionsForm({
  rows,
  disabled = false,
  sourceLocale,
  onAdd,
  onRemove,
  onUpdate,
  validationErrors = {},
  serverErrors = {},
}: BookingOptionsFormProps) {
  const t = useTranslations('partner.tours');

  const rowErrors = (index: number): string[] => [
    ...pickFieldErrors(validationErrors, `options.${index}`).map((key) =>
      key.startsWith('partner.tours.') ? t(key.replace('partner.tours.', '')) : key
    ),
    ...pickFieldErrors(serverErrors, `options.${index}`),
  ];

  const changeGuideLanguages = (row: BookingOptionFormInput, raw: string) => {
    onUpdate(row.id, { guide_languages: raw.split(',').map((code) => code.trim().toLowerCase()) });
  };

  // Trailing empties from the comma input are kept while editing; the
  // placeholder-filtered submit path (getSubmittedOptionRows) and payload
  // builder drop them, never letting stray commas become guide codes.
  const changeWeekdays = (row: BookingOptionFormInput, day: number) => {
    const days = row.weekdays.includes(day)
      ? row.weekdays.filter((value) => value !== day)
      : [...row.weekdays, day].sort((a, b) => a - b);
    onUpdate(row.id, { weekdays: days });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-[#0A2540]">{t('form.bookingOptions')}</h3>
          <p className="text-sm text-gray-500">{t('form.bookingOptionsHint')}</p>
          {sourceLocale && (
            <p className="mt-1 text-xs text-gray-500">
              {t('form.bookingOptionsSourceHint', {
                language: t(`form.sourceLanguageNames.${sourceLocale}`),
              })}
            </p>
          )}
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onAdd}
          disabled={disabled || rows.length >= 10}
          className="gap-1"
        >
          <Plus className="w-4 h-4" />
          {t('form.addOption')}
        </Button>
      </div>

      {rows.length === 0 && (
        <div className="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
          {t('form.noBookingOptions')}
        </div>
      )}

      <div className="space-y-3">
        {rows.map((row, index) => {
          const messages = rowErrors(index);
          return (
            <div key={row.id} className="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-[#0A2540]">
                  {row.server_id !== undefined ? (
                    <>
                      <span className="text-gray-400">{row.server_id} · </span>
                      {t('form.bookingOptionNumber', { number: index + 1 })}
                    </>
                  ) : (
                    t('form.bookingOptionNumber', { number: index + 1 })
                  )}
                </span>
                <button
                  type="button"
                  onClick={() => onRemove(row.id)}
                  disabled={disabled}
                  className="p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                  aria-label={t('form.removeOption', { number: index + 1 })}
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor={`option-title-${row.id}`}>{t('form.optionTitle')}</Label>
                  <Input
                    id={`option-title-${row.id}`}
                    lang={sourceLocale}
                    value={row.title}
                    onChange={(e) => onUpdate(row.id, { title: e.target.value })}
                    placeholder={t('form.optionTitlePlaceholder')}
                    disabled={disabled}
                    aria-invalid={pickFieldErrors(validationErrors, `options.${index}.title`).length > 0 || pickFieldErrors(serverErrors, `options.${index}.title`).length > 0 ? true : undefined}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor={`option-guide-languages-${row.id}`}>{t('form.optionGuideLanguages')}</Label>
                  <Input
                    id={`option-guide-languages-${row.id}`}
                    value={row.guide_languages.join(', ')}
                    onChange={(e) => changeGuideLanguages(row, e.target.value)}
                    placeholder="en, es, ar"
                    aria-describedby={`option-guide-languages-${row.id}-hint`}
                    disabled={disabled}
                  />
                  <p id={`option-guide-languages-${row.id}-hint`} className="text-xs text-gray-500">
                    {t('form.guideLanguageCodesHint')}
                  </p>
                </div>

                <div className="space-y-1">
                  <Label htmlFor={`option-price-${row.id}`}>{t('form.optionPrice')}</Label>
                  <Input
                    id={`option-price-${row.id}`}
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={row.price}
                    onChange={(e) => onUpdate(row.id, { price: e.target.value })}
                    placeholder="0.00"
                    disabled={disabled}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor={`option-capacity-${row.id}`} className="flex items-center gap-1">
                    <Users className="w-3.5 h-3.5 text-gray-400" />
                    {t('form.optionCapacity')}
                  </Label>
                  <Input
                    id={`option-capacity-${row.id}`}
                    type="number"
                    min="1"
                    step="1"
                    value={row.capacity}
                    onChange={(e) => onUpdate(row.id, { capacity: e.target.value })}
                    disabled={disabled}
                  />
                </div>

                <div className="space-y-1">
                  <Label htmlFor={`option-start-${row.id}`} className="flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5 text-gray-400" />
                    {t('form.optionStartTime')}
                  </Label>
                  <Input
                    id={`option-start-${row.id}`}
                    type="time"
                    value={row.start_time}
                    onChange={(e) => onUpdate(row.id, { start_time: e.target.value })}
                    disabled={disabled}
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label className="flex items-center gap-1">
                  <Calendar className="w-3.5 h-3.5 text-gray-400" />
                  {t('form.optionWeekdays')}
                </Label>
                <div className="flex gap-1">
                  {WEEKDAY_CODES.map((day) => (
                    <button
                      key={day}
                      type="button"
                      onClick={() => changeWeekdays(row, day)}
                      disabled={disabled}
                      className={`w-9 h-9 rounded-full text-xs font-medium flex items-center justify-center transition-colors ${
                        row.weekdays.includes(day)
                          ? 'bg-[#FFB800] text-[#0A2540]'
                          : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                      } disabled:opacity-50`}
                    >
                      {t(`form.weekday${day}`)}
                    </button>
                  ))}
                </div>
                <p className="text-xs text-gray-500">{t('form.optionWeekdaysHint')}</p>
              </div>

              <div className="space-y-1">
                <Label htmlFor={`option-pickup-${row.id}`} className="flex items-center gap-1">
                  <MapPin className="w-3.5 h-3.5 text-gray-400" />
                  {t('form.optionPickupInstructions')}
                </Label>
                <Textarea
                  id={`option-pickup-${row.id}`}
                  lang={sourceLocale}
                  value={row.pickup_instructions}
                  onChange={(e) => onUpdate(row.id, { pickup_instructions: e.target.value })}
                  placeholder={t('form.optionPickupPlaceholder')}
                  rows={2}
                  disabled={disabled}
                />
              </div>

              {messages.length > 0 && (
                <p className="text-xs text-red-600" role="alert">
                  {messages.map((message, messageIndex) => (
                    <span key={messageIndex} className="block">{message}</span>
                  ))}
                </p>
              )}
            </div>
          );
        })}
      </div>

      {rows.length > 0 && (
        <p className="text-xs text-gray-500">{t('form.bookingOptionsCount', { count: rows.length })}</p>
      )}
    </div>
  );
}

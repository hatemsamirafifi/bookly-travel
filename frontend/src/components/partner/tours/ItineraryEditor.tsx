'use client';

import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { TourItineraryDay } from '@/lib/api/types';

interface ItineraryEditorProps {
  value: TourItineraryDay[];
  onChange: (days: TourItineraryDay[]) => void;
  disabled?: boolean;
  /** Field errors keyed by nested path, e.g. `itinerary.0.title` or
   *  `itinerary.0.stops.1.duration_minutes`; also accepts server paths
   *  with a `translations.en.` prefix. Announced via role="alert". */
  errors?: Record<string, string>;
}

function renumber(days: TourItineraryDay[]): TourItineraryDay[] {
  return days.map((day, index) => ({ ...day, day: index + 1 }));
}

function errorFor(errors: Record<string, string> | undefined, ...paths: string[]): string | undefined {
  if (!errors) return undefined;
  for (const path of paths) {
    if (errors[path]) return errors[path];
    const prefixed = `translations.en.${path}`;
    if (errors[prefixed]) return errors[prefixed];
  }
  return undefined;
}

export function ItineraryEditor({ value, onChange, disabled = false, errors }: ItineraryEditorProps) {
  const t = useTranslations('partner.tours.form');

  const updateDay = (index: number, patch: Partial<TourItineraryDay>) => {
    onChange(value.map((day, position) => position === index ? { ...day, ...patch } : day));
  };
  const moveDay = (index: number, offset: number) => {
    const next = [...value];
    const target = index + offset;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    onChange(renumber(next));
  };
  const updateStop = (dayIndex: number, stopIndex: number, patch: Partial<NonNullable<TourItineraryDay['stops']>[number]>) => {
    const stops = [...(value[dayIndex].stops ?? [])];
    stops[stopIndex] = { ...stops[stopIndex], ...patch };
    updateDay(dayIndex, { stops });
  };
  const moveStop = (dayIndex: number, stopIndex: number, offset: number) => {
    const stops = [...(value[dayIndex].stops ?? [])];
    const target = stopIndex + offset;
    if (target < 0 || target >= stops.length) return;
    [stops[stopIndex], stops[target]] = [stops[target], stops[stopIndex]];
    updateDay(dayIndex, { stops });
  };

  // Duration input keeps the authored number verbatim (including fractional
  // values, which validation flags) and never floors via parseInt; an empty
  // or unparseable entry clears to null (durations are nullable).
  const handleDurationChange = (dayIndex: number, stopIndex: number, raw: string) => {
    if (raw.trim() === '') {
      updateStop(dayIndex, stopIndex, { duration_minutes: null });
      return;
    }
    const parsed = Number(raw);
    updateStop(dayIndex, stopIndex, { duration_minutes: Number.isNaN(parsed) ? null : parsed });
  };

  const itineraryError = errorFor(errors, 'itinerary');

  // Authored title/description controls carry no native maxlength: it counts
  // UTF-16 units and would block the schema/server-accepted 160/2000
  // code-point boundaries. Bounds stay enforced by validation with localized
  // field feedback.
  return (
    <section className="space-y-4" aria-label={t('itinerary')}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-lg font-semibold text-bookly-navy">{t('itinerary')}</h3>
        <Button type="button" variant="outline" size="sm" disabled={disabled || value.length >= 30}
          onClick={() => onChange([...value, { day: value.length + 1, title: '', description: null, stops: [] }])}>
          {t('addDay')}
        </Button>
      </div>
      {itineraryError && (
        <p className="text-xs text-red-500 mt-1" role="alert">{itineraryError}</p>
      )}
      {value.map((day, dayIndex) => {
        const dayTitleError = errorFor(errors, `itinerary.${dayIndex}.title`);
        const dayDescError = errorFor(errors, `itinerary.${dayIndex}.description`);
        return (
        <div key={dayIndex} className="space-y-3 rounded-xl border border-border bg-surface-alt p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h4 className="font-semibold text-bookly-navy">{t('dayNumber', { number: dayIndex + 1 })}</h4>
            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" aria-label={t('moveDayUp')} disabled={disabled || dayIndex === 0} onClick={() => moveDay(dayIndex, -1)}>↑</Button>
              <Button type="button" variant="outline" size="sm" aria-label={t('moveDayDown')} disabled={disabled || dayIndex === value.length - 1} onClick={() => moveDay(dayIndex, 1)}>↓</Button>
              <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => onChange(renumber(value.filter((_, index) => index !== dayIndex)))}>{t('removeDay')}</Button>
            </div>
          </div>
          <div className="space-y-1 text-sm font-medium text-bookly-navy">
            <label htmlFor={`itinerary-day-${dayIndex}-title`}>{t('dayTitle')}</label>
            <Input id={`itinerary-day-${dayIndex}-title`} value={day.title} disabled={disabled}
              aria-invalid={dayTitleError ? true : undefined}
              aria-describedby={dayTitleError ? `itinerary-day-${dayIndex}-title-error` : undefined}
              onChange={(event) => updateDay(dayIndex, { title: event.target.value })} />
            {dayTitleError && (
              <p id={`itinerary-day-${dayIndex}-title-error`} className="text-xs text-red-500 mt-1" role="alert">{dayTitleError}</p>
            )}
          </div>
          <div className="space-y-1 text-sm font-medium text-bookly-navy">
            <label htmlFor={`itinerary-day-${dayIndex}-description`}>{t('dayDescription')}</label>
            <Textarea id={`itinerary-day-${dayIndex}-description`} value={day.description ?? ''} disabled={disabled}
              aria-invalid={dayDescError ? true : undefined}
              aria-describedby={dayDescError ? `itinerary-day-${dayIndex}-description-error` : undefined}
              onChange={(event) => updateDay(dayIndex, { description: event.target.value })} />
            {dayDescError && (
              <p id={`itinerary-day-${dayIndex}-description-error`} className="text-xs text-red-500 mt-1" role="alert">{dayDescError}</p>
            )}
          </div>
          {(day.stops ?? []).map((stop, stopIndex) => {
            const stopTitleError = errorFor(errors, `itinerary.${dayIndex}.stops.${stopIndex}.title`);
            const stopDescError = errorFor(errors, `itinerary.${dayIndex}.stops.${stopIndex}.description`);
            const stopDurationError = errorFor(errors, `itinerary.${dayIndex}.stops.${stopIndex}.duration_minutes`);
            return (
            <div key={stopIndex} className="space-y-2 rounded-lg border border-border bg-surface p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h5 className="text-sm font-semibold text-bookly-navy">{t('stopNumber', { number: stopIndex + 1 })}</h5>
                <div className="flex gap-2">
                  <Button type="button" variant="outline" size="sm" aria-label={t('moveStopUp')} disabled={disabled || stopIndex === 0} onClick={() => moveStop(dayIndex, stopIndex, -1)}>↑</Button>
                  <Button type="button" variant="outline" size="sm" aria-label={t('moveStopDown')} disabled={disabled || stopIndex === (day.stops?.length ?? 0) - 1} onClick={() => moveStop(dayIndex, stopIndex, 1)}>↓</Button>
                  <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => updateDay(dayIndex, { stops: (day.stops ?? []).filter((_, index) => index !== stopIndex) })}>{t('removeStop')}</Button>
                </div>
              </div>
              <div className="space-y-1 text-sm font-medium text-bookly-navy">
                <label htmlFor={`itinerary-day-${dayIndex}-stop-${stopIndex}-title`}>{t('stopTitle')}</label>
                <Input id={`itinerary-day-${dayIndex}-stop-${stopIndex}-title`} value={stop.title} disabled={disabled}
                  aria-invalid={stopTitleError ? true : undefined}
                  aria-describedby={stopTitleError ? `itinerary-day-${dayIndex}-stop-${stopIndex}-title-error` : undefined}
                  onChange={(event) => updateStop(dayIndex, stopIndex, { title: event.target.value })} />
                {stopTitleError && (
                  <p id={`itinerary-day-${dayIndex}-stop-${stopIndex}-title-error`} className="text-xs text-red-500 mt-1" role="alert">{stopTitleError}</p>
                )}
              </div>
              <div className="space-y-1 text-sm font-medium text-bookly-navy">
                <label htmlFor={`itinerary-day-${dayIndex}-stop-${stopIndex}-description`}>{t('stopDescription')}</label>
                <Textarea id={`itinerary-day-${dayIndex}-stop-${stopIndex}-description`} value={stop.description ?? ''} disabled={disabled}
                  aria-invalid={stopDescError ? true : undefined}
                  aria-describedby={stopDescError ? `itinerary-day-${dayIndex}-stop-${stopIndex}-description-error` : undefined}
                  onChange={(event) => updateStop(dayIndex, stopIndex, { description: event.target.value })} />
                {stopDescError && (
                  <p id={`itinerary-day-${dayIndex}-stop-${stopIndex}-description-error`} className="text-xs text-red-500 mt-1" role="alert">{stopDescError}</p>
                )}
              </div>
              <div className="space-y-1 text-sm font-medium text-bookly-navy">
                <label htmlFor={`itinerary-day-${dayIndex}-stop-${stopIndex}-duration`}>{t('stopDuration')}</label>
                <Input id={`itinerary-day-${dayIndex}-stop-${stopIndex}-duration`} type="number" min={1} max={1440} step={1}
                  value={stop.duration_minutes ?? ''} disabled={disabled}
                  aria-invalid={stopDurationError ? true : undefined}
                  aria-describedby={stopDurationError ? `itinerary-day-${dayIndex}-stop-${stopIndex}-duration-error` : undefined}
                  onChange={(event) => handleDurationChange(dayIndex, stopIndex, event.target.value)} />
                {stopDurationError && (
                  <p id={`itinerary-day-${dayIndex}-stop-${stopIndex}-duration-error`} className="text-xs text-red-500 mt-1" role="alert">{stopDurationError}</p>
                )}
              </div>
            </div>
            );
          })}
          <Button type="button" variant="outline" size="sm" disabled={disabled || (day.stops?.length ?? 0) >= 20}
            onClick={() => updateDay(dayIndex, { stops: [...(day.stops ?? []), { title: '', description: null, duration_minutes: null }] })}>{t('addStop')}</Button>
        </div>
        );
      })}
    </section>
  );
}

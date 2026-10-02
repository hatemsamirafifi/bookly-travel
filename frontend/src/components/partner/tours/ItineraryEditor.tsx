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
}

function renumber(days: TourItineraryDay[]): TourItineraryDay[] {
  return days.map((day, index) => ({ ...day, day: index + 1 }));
}

export function ItineraryEditor({ value, onChange, disabled = false }: ItineraryEditorProps) {
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

  return (
    <section className="space-y-4" aria-label={t('itinerary')}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-lg font-semibold text-bookly-navy">{t('itinerary')}</h3>
        <Button type="button" variant="outline" size="sm" disabled={disabled || value.length >= 30}
          onClick={() => onChange([...value, { day: value.length + 1, title: '', stops: [] }])}>
          {t('addDay')}
        </Button>
      </div>
      {value.map((day, dayIndex) => (
        <div key={dayIndex} className="space-y-3 rounded-xl border border-border bg-surface-alt p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h4 className="font-semibold text-bookly-navy">{t('dayNumber', { number: dayIndex + 1 })}</h4>
            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" aria-label={t('moveDayUp')} disabled={disabled || dayIndex === 0} onClick={() => moveDay(dayIndex, -1)}>↑</Button>
              <Button type="button" variant="outline" size="sm" aria-label={t('moveDayDown')} disabled={disabled || dayIndex === value.length - 1} onClick={() => moveDay(dayIndex, 1)}>↓</Button>
              <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => onChange(renumber(value.filter((_, index) => index !== dayIndex)))}>{t('removeDay')}</Button>
            </div>
          </div>
          <label className="block space-y-1 text-sm font-medium text-bookly-navy">
            <span>{t('dayTitle')}</span>
            <Input value={day.title} maxLength={160} disabled={disabled} onChange={(event) => updateDay(dayIndex, { title: event.target.value })} />
          </label>
          <label className="block space-y-1 text-sm font-medium text-bookly-navy">
            <span>{t('dayDescription')}</span>
            <Textarea value={day.description ?? ''} maxLength={2000} disabled={disabled} onChange={(event) => updateDay(dayIndex, { description: event.target.value })} />
          </label>
          {(day.stops ?? []).map((stop, stopIndex) => (
            <div key={stopIndex} className="space-y-2 rounded-lg border border-border bg-surface p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h5 className="text-sm font-semibold text-bookly-navy">{t('stopNumber', { number: stopIndex + 1 })}</h5>
                <div className="flex gap-2">
                  <Button type="button" variant="outline" size="sm" aria-label={t('moveStopUp')} disabled={disabled || stopIndex === 0} onClick={() => moveStop(dayIndex, stopIndex, -1)}>↑</Button>
                  <Button type="button" variant="outline" size="sm" aria-label={t('moveStopDown')} disabled={disabled || stopIndex === (day.stops?.length ?? 0) - 1} onClick={() => moveStop(dayIndex, stopIndex, 1)}>↓</Button>
                  <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => updateDay(dayIndex, { stops: (day.stops ?? []).filter((_, index) => index !== stopIndex) })}>{t('removeStop')}</Button>
                </div>
              </div>
              <label className="block space-y-1 text-sm font-medium text-bookly-navy">
                <span>{t('stopTitle')}</span>
                <Input value={stop.title} maxLength={160} disabled={disabled} onChange={(event) => updateStop(dayIndex, stopIndex, { title: event.target.value })} />
              </label>
              <label className="block space-y-1 text-sm font-medium text-bookly-navy">
                <span>{t('stopDescription')}</span>
                <Textarea value={stop.description ?? ''} maxLength={2000} disabled={disabled} onChange={(event) => updateStop(dayIndex, stopIndex, { description: event.target.value })} />
              </label>
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" disabled={disabled || (day.stops?.length ?? 0) >= 20}
            onClick={() => updateDay(dayIndex, { stops: [...(day.stops ?? []), { title: '' }] })}>{t('addStop')}</Button>
        </div>
      ))}
    </section>
  );
}

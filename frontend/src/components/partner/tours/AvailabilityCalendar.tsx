'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Plus, Trash2 } from 'lucide-react';
import type { AvailabilityRuleFormInput, AvailabilityExceptionFormInput } from '@/types/tour';

const WEEKDAY_INDICES = [0, 1, 2, 3, 4, 5, 6] as const;

interface AvailabilityCalendarProps {
  disabled?: boolean;
}

export function AvailabilityCalendar({ disabled = false }: AvailabilityCalendarProps) {
  const t = useTranslations('partner.tours.availability');
  const {
    formData,
    updateAvailabilityRule,
    addAvailabilityRule,
    removeAvailabilityRule,
    addAvailabilityException,
    updateAvailabilityException,
    removeAvailabilityException,
  } = useTourWizardStore();

  const { availability_rules, availability_exceptions } = formData;

  return (
    <div className="space-y-8">
      {/* ── Recurring Rules ─────────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between gap-2">
          <div>
            <h3 className="text-lg font-semibold text-[#0A2540]">{t('recurringSchedule')}</h3>
            <p className="text-sm text-gray-500">{t('recurringScheduleHint')}</p>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addAvailabilityRule}
            disabled={disabled}
            className="gap-1 shrink-0"
          >
            <Plus className="w-4 h-4" />
            {t('addRule')}
          </Button>
        </div>

        {availability_rules.length === 0 && (
          <div className="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            {t('noRulesYet')}
          </div>
        )}

        <div className="space-y-3">
          {availability_rules.map((rule) => (
            <AvailabilityRuleCard
              key={rule.id}
              rule={rule}
              onChange={(updates) => updateAvailabilityRule(rule.id, updates)}
              onRemove={() => removeAvailabilityRule(rule.id)}
              disabled={disabled}
            />
          ))}
        </div>
      </section>

      {/* ── Exceptions ───────────────────────────────────────────────── */}
      <section className="space-y-4">
        <div className="flex items-center justify-between gap-2">
          <div>
            <h3 className="text-lg font-semibold text-[#0A2540]">{t('exceptionsTitle')}</h3>
            <p className="text-sm text-gray-500">{t('exceptionsHint')}</p>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addAvailabilityException}
            disabled={disabled}
            className="gap-1 shrink-0"
          >
            <Plus className="w-4 h-4" />
            {t('addException')}
          </Button>
        </div>

        {availability_exceptions.length === 0 && (
          <div className="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            {t('noExceptionsYet')}
          </div>
        )}

        <div className="space-y-3">
          {availability_exceptions.map((exc) => (
            <ExceptionCard
              key={exc.id}
              exception={exc}
              onChange={(updates) => updateAvailabilityException(exc.id, updates)}
              onRemove={() => removeAvailabilityException(exc.id)}
              disabled={disabled}
            />
          ))}
        </div>

        {availability_exceptions.length > 0 && (
          <ExceptionCalendar exceptions={availability_exceptions} />
        )}
      </section>
    </div>
  );
}

/* ─── Availability Rule Card ──────────────────────────────────────────────── */

interface AvailabilityRuleCardProps {
  rule: AvailabilityRuleFormInput;
  onChange: (updates: Partial<Omit<AvailabilityRuleFormInput, 'id'>>) => void;
  onRemove: () => void;
  disabled?: boolean;
}

function AvailabilityRuleCard({ rule, onChange, onRemove, disabled }: AvailabilityRuleCardProps) {
  const t = useTranslations('partner.tours.availability');

  const toggleDay = (dayValue: number) => {
    const days = rule.days_of_week.includes(dayValue)
      ? rule.days_of_week.filter((d) => d !== dayValue)
      : [...rule.days_of_week, dayValue].sort();
    onChange({ days_of_week: days });
  };

  const ruleTitle =
    rule.rule_type === 'daily'
      ? t('dailyRule')
      : rule.rule_type === 'weekly'
        ? t('weeklyRule')
        : t('monthlyRule');

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-[#0A2540]">{ruleTitle}</span>
        <button
          type="button"
          onClick={onRemove}
          disabled={disabled}
          className="p-1 rounded text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
          aria-label={t('removeRule')}
        >
          <Trash2 className="w-4 h-4" />
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label id={`rule-freq-label-${rule.id}`}>{t('frequency')}</Label>
          <Select
            value={rule.rule_type}
            onValueChange={(v) => onChange({ rule_type: v as AvailabilityRuleFormInput['rule_type'] })}
          >
            <SelectTrigger aria-labelledby={`rule-freq-label-${rule.id}`}>
              <SelectValue placeholder={t('selectFrequency')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="daily">{t('daily')}</SelectItem>
              <SelectItem value="weekly">{t('weekly')}</SelectItem>
              <SelectItem value="monthly">{t('monthly')}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-time-${rule.id}`}>{t('startTime')}</Label>
          <Input
            id={`rule-time-${rule.id}`}
            type="time"
            value={rule.start_time}
            onChange={(e) => onChange({ start_time: e.target.value })}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-start-${rule.id}`}>{t('startDate')}</Label>
          <Input
            id={`rule-start-${rule.id}`}
            type="date"
            value={rule.start_date}
            onChange={(e) => onChange({ start_date: e.target.value })}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-end-${rule.id}`}>{t('endDate')}</Label>
          <Input
            id={`rule-end-${rule.id}`}
            type="date"
            value={rule.end_date}
            onChange={(e) => onChange({ end_date: e.target.value })}
            disabled={disabled}
            placeholder={t('indefinitePlaceholder')}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-capacity-${rule.id}`}>{t('capacity')}</Label>
          <Input
            id={`rule-capacity-${rule.id}`}
            type="number"
            min="1"
            value={rule.capacity}
            onChange={(e) => onChange({ capacity: Math.max(1, parseInt(e.target.value, 10) || 1) })}
            disabled={disabled}
          />
        </div>
      </div>

      {rule.rule_type === 'weekly' && (
        <div className="space-y-1">
          <Label>{t('daysOfWeek')}</Label>
          <div className="flex flex-wrap gap-1">
            {WEEKDAY_INDICES.map((dayValue) => (
              <button
                key={dayValue}
                type="button"
                aria-pressed={rule.days_of_week.includes(dayValue)}
                onClick={() => toggleDay(dayValue)}
                disabled={disabled}
                className={`w-9 h-9 rounded-full text-xs font-medium flex items-center justify-center transition-colors ${
                  rule.days_of_week.includes(dayValue)
                    ? 'bg-[#FFB800] text-[#0A2540]'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                } disabled:opacity-50`}
              >
                {t(`weekday${dayValue}`)}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ─── Exception Card ─────────────────────────────────────────────────────── */

interface ExceptionCardProps {
  exception: AvailabilityExceptionFormInput;
  onChange: (updates: Partial<Omit<AvailabilityExceptionFormInput, 'id'>>) => void;
  onRemove: () => void;
  disabled?: boolean;
}

function ExceptionCard({ exception, onChange, onRemove, disabled }: ExceptionCardProps) {
  const t = useTranslations('partner.tours.availability');
  const locale = useLocale();
  const isBlackout = exception.exception_type === 'blackout';

  return (
    <div
      className={`rounded-lg border p-4 space-y-3 ${
        isBlackout ? 'border-red-200 bg-red-50/30' : 'border-blue-200 bg-blue-50/30'
      }`}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span
            className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
              isBlackout ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'
            }`}
          >
            {isBlackout ? t('blackoutBadge') : t('specificBadge')}
          </span>
          {exception.date && (
            <span className="text-sm text-gray-600">
              {new Date(exception.date + 'T00:00:00').toLocaleDateString(locale)}
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={onRemove}
          disabled={disabled}
          className="p-1 rounded text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
          aria-label={t('removeException')}
        >
          <Trash2 className="w-4 h-4" />
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label id={`exc-type-label-${exception.id}`}>{t('typeLabel')}</Label>
          <Select
            value={exception.exception_type}
            onValueChange={(v) => onChange({ exception_type: v as 'blackout' | 'specific' })}
          >
            <SelectTrigger aria-labelledby={`exc-type-label-${exception.id}`}>
              <SelectValue placeholder={t('selectType')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="blackout">{t('blackoutOption')}</SelectItem>
              <SelectItem value="specific">{t('specificOption')}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor={`exc-date-${exception.id}`}>{t('date')}</Label>
          <Input
            id={`exc-date-${exception.id}`}
            type="date"
            value={exception.date}
            onChange={(e) => onChange({ date: e.target.value })}
            disabled={disabled}
          />
        </div>
        {!isBlackout && (
          <>
            <div className="space-y-1">
              <Label htmlFor={`exc-time-${exception.id}`}>{t('startTime')}</Label>
              <Input
                id={`exc-time-${exception.id}`}
                type="time"
                value={exception.start_time}
                onChange={(e) => onChange({ start_time: e.target.value })}
                disabled={disabled}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`exc-capacity-${exception.id}`}>{t('capacity')}</Label>
              <Input
                id={`exc-capacity-${exception.id}`}
                type="number"
                min="1"
                value={exception.capacity}
                onChange={(e) => onChange({ capacity: Math.max(1, parseInt(e.target.value, 10) || 1) })}
                disabled={disabled}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`exc-multiplier-${exception.id}`}>{t('priceMultiplier')}</Label>
              <Input
                id={`exc-multiplier-${exception.id}`}
                type="number"
                min="0.5"
                max="5"
                step="0.05"
                value={exception.price_multiplier}
                onChange={(e) => onChange({ price_multiplier: e.target.value })}
                disabled={disabled}
                placeholder={t('priceMultiplierPlaceholder')}
              />
            </div>
          </>
        )}
        <div className="space-y-1 sm:col-span-2">
          <Label htmlFor={`exc-note-${exception.id}`}>{t('note')}</Label>
          <Input
            id={`exc-note-${exception.id}`}
            value={exception.note}
            onChange={(e) => onChange({ note: e.target.value })}
            placeholder={t('notePlaceholder')}
            disabled={disabled}
          />
        </div>
      </div>
    </div>
  );
}

/* ─── Exception Calendar Summary ─────────────────────────────────────────── */

interface ExceptionCalendarProps {
  exceptions: AvailabilityExceptionFormInput[];
}

function ExceptionCalendar({ exceptions }: ExceptionCalendarProps) {
  const t = useTranslations('partner.tours.availability');
  const locale = useLocale();

  const grouped = exceptions.reduce<Record<string, AvailabilityExceptionFormInput[]>>((acc, exc) => {
    if (!exc.date) return acc;
    const key = exc.date.substring(0, 7);
    acc[key] = acc[key] ?? [];
    acc[key].push(exc);
    return acc;
  }, {});

  const sortedMonths = Object.keys(grouped).sort();
  if (sortedMonths.length === 0) return null;

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
      <h4 className="text-sm font-semibold text-[#0A2540]">{t('exceptionsCalendar')}</h4>
      {sortedMonths.map((monthKey) => {
        const monthDate = new Date(monthKey + '-01T00:00:00');
        const monthLabel = monthDate.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
        const items = grouped[monthKey];

        return (
          <div key={monthKey} className="space-y-1">
            <p className="text-xs font-medium text-gray-600 uppercase tracking-wide">{monthLabel}</p>
            <div className="flex flex-wrap gap-2">
              {items.map((exc) => {
                const day = exc.date ? new Date(exc.date + 'T00:00:00').getDate() : '?';
                const isBlackout = exc.exception_type === 'blackout';
                return (
                  <div
                    key={exc.id}
                    className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium ${
                      isBlackout ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'
                    }`}
                  >
                    <span>{day}</span>
                    {isBlackout && (
                      <svg className="w-3 h-3" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    )}
                    {exc.note && <span className="text-[10px] opacity-75">({exc.note})</span>}
                  </div>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
  );
}

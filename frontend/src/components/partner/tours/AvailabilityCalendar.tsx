'use client';

import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Plus, Trash2 } from 'lucide-react';
import type { AvailabilityRuleFormInput, AvailabilityExceptionFormInput } from '@/types/tour';

const DAYS_OF_WEEK = [
  { value: 0, label: 'Sun' },
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
];

interface AvailabilityCalendarProps {
  disabled?: boolean;
}

export function AvailabilityCalendar({ disabled = false }: AvailabilityCalendarProps) {
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
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-[#0A2540]">Recurring Schedule</h3>
            <p className="text-sm text-gray-500">
              Define when your tour runs on a recurring basis.
            </p>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addAvailabilityRule}
            disabled={disabled}
            className="gap-1"
          >
            <Plus className="w-4 h-4" />
            Add Rule
          </Button>
        </div>

        {availability_rules.length === 0 && (
          <div className="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            No recurring rules yet. Click &ldquo;Add Rule&rdquo; to create one.
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
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-[#0A2540]">Exceptions</h3>
            <p className="text-sm text-gray-500">
              Add blackout dates or specific overrides for individual days.
            </p>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={addAvailabilityException}
            disabled={disabled}
            className="gap-1"
          >
            <Plus className="w-4 h-4" />
            Add Exception
          </Button>
        </div>

        {availability_exceptions.length === 0 && (
          <div className="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
            No exceptions yet. Click &ldquo;Add Exception&rdquo; to create one.
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

        {/* Visual calendar-like summary of exceptions */}
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
  const toggleDay = (dayValue: number) => {
    const days = rule.days_of_week.includes(dayValue)
      ? rule.days_of_week.filter((d) => d !== dayValue)
      : [...rule.days_of_week, dayValue].sort();
    onChange({ days_of_week: days });
  };

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-[#0A2540]">
          {rule.rule_type === 'daily' ? 'Daily' : rule.rule_type === 'weekly' ? 'Weekly' : 'Monthly'} Rule
        </span>
        <button
          type="button"
          onClick={onRemove}
          disabled={disabled}
          className="p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
          aria-label="Remove rule"
        >
          <Trash2 className="w-4 h-4" />
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label>Frequency</Label>
          <Select
            value={rule.rule_type}
            onValueChange={(v) => onChange({ rule_type: v as AvailabilityRuleFormInput['rule_type'] })}
          >
            <SelectTrigger><SelectValue placeholder="Select frequency" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="daily">Daily</SelectItem>
              <SelectItem value="weekly">Weekly</SelectItem>
              <SelectItem value="monthly">Monthly</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-time-${rule.id}`}>Start time</Label>
          <Input
            id={`rule-time-${rule.id}`}
            type="time"
            value={rule.start_time}
            onChange={(e) => onChange({ start_time: e.target.value })}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-start-${rule.id}`}>Start date</Label>
          <Input
            id={`rule-start-${rule.id}`}
            type="date"
            value={rule.start_date}
            onChange={(e) => onChange({ start_date: e.target.value })}
            disabled={disabled}
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-end-${rule.id}`}>End date</Label>
          <Input
            id={`rule-end-${rule.id}`}
            type="date"
            value={rule.end_date}
            onChange={(e) => onChange({ end_date: e.target.value })}
            disabled={disabled}
            placeholder="Leave empty for indefinite"
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor={`rule-capacity-${rule.id}`}>Capacity</Label>
          <Input
            id={`rule-capacity-${rule.id}`}
            type="number"
            min="1"
            value={rule.capacity}
            onChange={(e) => onChange({ capacity: Math.max(1, parseInt(e.target.value) || 1) })}
            disabled={disabled}
          />
        </div>
      </div>

      {/* Days of week selector (only for weekly) */}
      {rule.rule_type === 'weekly' && (
        <div className="space-y-1">
          <Label>Days of week</Label>
          <div className="flex gap-1">
            {DAYS_OF_WEEK.map((day) => (
              <button
                key={day.value}
                type="button"
                onClick={() => toggleDay(day.value)}
                disabled={disabled}
                className={`w-9 h-9 rounded-full text-xs font-medium flex items-center justify-center transition-colors ${
                  rule.days_of_week.includes(day.value)
                    ? 'bg-[#FFB800] text-[#0A2540]'
                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                } disabled:opacity-50`}
              >
                {day.label}
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
  const isBlackout = exception.exception_type === 'blackout';

  return (
    <div className={`rounded-lg border p-4 space-y-3 ${
      isBlackout ? 'border-red-200 bg-red-50/30' : 'border-blue-200 bg-blue-50/30'
    }`}>
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
            isBlackout ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'
          }`}>
            {isBlackout ? 'Blackout' : 'Specific Override'}
          </span>
          {exception.date && (
            <span className="text-sm text-gray-600">
              {new Date(exception.date + 'T00:00:00').toLocaleDateString()}
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={onRemove}
          disabled={disabled}
          className="p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
          aria-label="Remove exception"
        >
          <Trash2 className="w-4 h-4" />
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label>Type</Label>
          <Select
            value={exception.exception_type}
            onValueChange={(v) => onChange({ exception_type: v as 'blackout' | 'specific' })}
          >
            <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="blackout">Blackout (unavailable)</SelectItem>
              <SelectItem value="specific">Specific (override)</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label htmlFor={`exc-date-${exception.id}`}>Date</Label>
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
              <Label htmlFor={`exc-time-${exception.id}`}>Start time</Label>
              <Input
                id={`exc-time-${exception.id}`}
                type="time"
                value={exception.start_time}
                onChange={(e) => onChange({ start_time: e.target.value })}
                disabled={disabled}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`exc-capacity-${exception.id}`}>Capacity</Label>
              <Input
                id={`exc-capacity-${exception.id}`}
                type="number"
                min="1"
                value={exception.capacity}
                onChange={(e) => onChange({ capacity: Math.max(1, parseInt(e.target.value) || 1) })}
                disabled={disabled}
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`exc-multiplier-${exception.id}`}>Price multiplier</Label>
              <Input
                id={`exc-multiplier-${exception.id}`}
                type="number"
                min="0.5"
                max="5"
                step="0.05"
                value={exception.price_multiplier}
                onChange={(e) => onChange({ price_multiplier: e.target.value })}
                disabled={disabled}
                placeholder="e.g., 1.20 for 20% surcharge"
              />
            </div>
          </>
        )}
        <div className="space-y-1 sm:col-span-2">
          <Label htmlFor={`exc-note-${exception.id}`}>Note</Label>
          <Input
            id={`exc-note-${exception.id}`}
            value={exception.note}
            onChange={(e) => onChange({ note: e.target.value })}
            placeholder="e.g., Holiday special pricing"
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
  // Group exceptions by month
  const grouped = exceptions.reduce<Record<string, AvailabilityExceptionFormInput[]>>((acc, exc) => {
    if (!exc.date) return acc;
    const key = exc.date.substring(0, 7); // YYYY-MM
    acc[key] = acc[key] ?? [];
    acc[key].push(exc);
    return acc;
  }, {});

  const sortedMonths = Object.keys(grouped).sort();

  if (sortedMonths.length === 0) return null;

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
      <h4 className="text-sm font-semibold text-[#0A2540]">Exceptions Calendar</h4>
      {sortedMonths.map((monthKey) => {
        const monthDate = new Date(monthKey + '-01T00:00:00');
        const monthLabel = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const items = grouped[monthKey];

        return (
          <div key={monthKey} className="space-y-1">
            <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">{monthLabel}</p>
            <div className="flex flex-wrap gap-2">
              {items.map((exc) => {
                const day = exc.date ? new Date(exc.date + 'T00:00:00').getDate() : '?';
                const isBlackout = exc.exception_type === 'blackout';
                return (
                  <div
                    key={exc.id}
                    className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium ${
                      isBlackout
                        ? 'bg-red-100 text-red-700'
                        : 'bg-blue-100 text-blue-700'
                    }`}
                  >
                    <span>{day}</span>
                    {isBlackout && (
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
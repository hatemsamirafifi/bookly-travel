'use client';

import { useCallback, useEffect, useRef, useState, use } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowLeft, Calendar, Clock, Pencil, Plus, RefreshCw, Trash2, Users, XCircle } from 'lucide-react';
import { ApiError, ValidationError } from '@/lib/api/client';
import {
  cancelPartnerDeparture,
  createPartnerAvailabilityException,
  createPartnerAvailabilityRule,
  createPartnerDeparture,
  deletePartnerAvailabilityException,
  deletePartnerAvailabilityRule,
  generatePartnerDepartures,
  getPartnerAvailability,
  getPartnerDepartures,
  updatePartnerDeparture,
} from '@/lib/api/partner';
import type {
  PartnerAvailabilityException,
  PartnerAvailabilityRule,
  PartnerDepartureOptionSummary,
  PartnerTourDeparture,
} from '@/lib/api/types';

const IANA_TIMEZONES = [
  'Europe/Rome',
  'Europe/Madrid',
  'Europe/London',
  'Europe/Paris',
  'America/New_York',
  'UTC',
] as const;

function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10);
}

function addDaysIsoDate(baseIso: string, days: number): string {
  const date = new Date(baseIso + 'T00:00:00Z');
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

function extractErrorMessage(err: unknown, fallback: string): string {
  if (err instanceof ValidationError) {
    const firstField = Object.values(err.errors)[0];
    if (Array.isArray(firstField) && firstField[0]) {
      return firstField[0];
    }
  }
  if (err instanceof ApiError && err.message) {
    return err.message;
  }
  if (err instanceof Error && err.message) {
    return err.message;
  }
  return fallback;
}

export default function AvailabilityPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const tourId = resolvedParams.id;
  const locale = useLocale();
  const tAvail = useTranslations('partner.tours.availability');
  const tDep = useTranslations('partner.tours.departures');
  const loadFailedMsgRef = useRef('');
  loadFailedMsgRef.current = tAvail('loadFailed');

  const [rules, setRules] = useState<PartnerAvailabilityRule[]>([]);
  const [exceptions, setExceptions] = useState<PartnerAvailabilityException[]>([]);
  const [departures, setDepartures] = useState<PartnerTourDeparture[]>([]);
  const [options, setOptions] = useState<PartnerDepartureOptionSummary[]>([]);

  const [isLoadingAvailability, setIsLoadingAvailability] = useState(true);
  const [isLoadingDepartures, setIsLoadingDepartures] = useState(true);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [noticeMsg, setNoticeMsg] = useState<string | null>(null);

  // Schedule creation panel state (fixed to backend enum: recurring | specific_date)
  const [activeTab, setActiveTab] = useState<'rules' | 'exceptions'>('rules');
  const [ruleType, setRuleType] = useState<'recurring' | 'specific_date'>('recurring');
  const [daysOfWeek, setDaysOfWeek] = useState<number[]>([]);
  const [startTime, setStartTime] = useState('09:00');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [capacity, setCapacity] = useState(15);

  const [exceptionType, setExceptionType] = useState<'block' | 'capacity_override' | 'price_multiplier'>('block');
  const [excDate, setExcDate] = useState('');
  const [excStartTime, setExcStartTime] = useState('');
  const [excCapacity, setExcCapacity] = useState('');
  const [priceMultiplier, setPriceMultiplier] = useState('1.00');
  const [note, setNote] = useState('');

  // Accessible inline deletion state (no window.confirm)
  const [pendingDeleteRuleId, setPendingDeleteRuleId] = useState<number | null>(null);
  const [pendingDeleteExceptionId, setPendingDeleteExceptionId] = useState<number | null>(null);

  // Explicit Departures filters & forms
  const [filterOptionId, setFilterOptionId] = useState<string>('');
  const [filterStatus, setFilterStatus] = useState<string>('');
  const [filterFrom, setFilterFrom] = useState<string>('');
  const [filterTo, setFilterTo] = useState<string>('');

  const [genOptionId, setGenOptionId] = useState<string>('');
  const [genStartDate, setGenStartDate] = useState<string>(() => todayIsoDate());
  const [genEndDate, setGenEndDate] = useState<string>(() => addDaysIsoDate(todayIsoDate(), 30));
  const [genTimezone, setGenTimezone] = useState<string>('Europe/Rome');
  const [isGenerating, setIsGenerating] = useState(false);

  const [createOptionId, setCreateOptionId] = useState<string>('');
  const [createDate, setCreateDate] = useState<string>(() => addDaysIsoDate(todayIsoDate(), 7));
  const [createTime, setCreateTime] = useState<string>('09:00');
  const [createTimezone, setCreateTimezone] = useState<string>('Europe/Rome');
  const [createCapacity, setCreateCapacity] = useState<string>('10');
  const [createCutoffHours, setCreateCutoffHours] = useState<string>('2');
  const [isCreatingDeparture, setIsCreatingDeparture] = useState(false);

  // Inline edit / cancel states for departure cards
  const [editingDepartureId, setEditingDepartureId] = useState<number | null>(null);
  const [editCapacity, setEditCapacity] = useState<string>('');
  const [editCutoff, setEditCutoff] = useState<string>('');
  const [editRowError, setEditRowError] = useState<string | null>(null);
  const [isSavingDeparture, setIsSavingDeparture] = useState(false);

  const [cancellingDepartureId, setCancellingDepartureId] = useState<number | null>(null);
  const [confirmBookedCancel, setConfirmBookedCancel] = useState(false);
  const [cancelRowError, setCancelRowError] = useState<string | null>(null);
  const [isCancellingDeparture, setIsCancellingDeparture] = useState(false);

  const fetchAvailability = useCallback(async () => {
    setIsLoadingAvailability(true);
    try {
      const json = await getPartnerAvailability(tourId);
      setRules(json.data.rules || []);
      setExceptions(json.data.exceptions || []);
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, loadFailedMsgRef.current));
    } finally {
      setIsLoadingAvailability(false);
    }
  }, [tourId]);

  const fetchDepartures = useCallback(async () => {
    setIsLoadingDepartures(true);
    try {
      const json = await getPartnerDepartures(tourId, {
        tour_option_id: filterOptionId || undefined,
        status: filterStatus || undefined,
        date_from: filterFrom || undefined,
        date_to: filterTo || undefined,
      });
      setDepartures(json.data || []);
      const fetchedOptions = json.meta?.options || [];
      setOptions(fetchedOptions);
      if (fetchedOptions.length > 0) {
        setCreateOptionId((prev) => prev || String(fetchedOptions[0].id));
      }
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, loadFailedMsgRef.current));
    } finally {
      setIsLoadingDepartures(false);
    }
  }, [tourId, filterOptionId, filterStatus, filterFrom, filterTo]);

  useEffect(() => {
    void fetchAvailability();
  }, [fetchAvailability]);

  useEffect(() => {
    void fetchDepartures();
  }, [fetchDepartures]);

  const handleCreateRule = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setNoticeMsg(null);
    try {
      await createPartnerAvailabilityRule(tourId, {
        rule_type: ruleType,
        days_of_week: ruleType === 'recurring' ? daysOfWeek : null,
        start_time: startTime,
        start_date: startDate,
        end_date: endDate || null,
        capacity,
      });
      setDaysOfWeek([]);
      setStartDate('');
      setEndDate('');
      setNoticeMsg(tAvail('ruleSavedNotice'));
      await fetchAvailability();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tAvail('createRuleFailed')));
    }
  };

  const handleCreateException = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setNoticeMsg(null);
    try {
      await createPartnerAvailabilityException(tourId, {
        exception_type: exceptionType,
        date: excDate,
        start_time: excStartTime || null,
        capacity: exceptionType === 'capacity_override' ? parseInt(excCapacity, 10) || 0 : null,
        price_multiplier: exceptionType === 'price_multiplier' ? parseFloat(priceMultiplier) || 1.0 : 1.0,
        note: note || null,
      });
      setExcDate('');
      setExcStartTime('');
      setExcCapacity('');
      setPriceMultiplier('1.00');
      setNote('');
      setNoticeMsg(tAvail('exceptionSavedNotice'));
      await fetchAvailability();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tAvail('createExceptionFailed')));
    }
  };

  const handleConfirmDeleteRule = async (id: number) => {
    setErrorMsg(null);
    setNoticeMsg(null);
    try {
      await deletePartnerAvailabilityRule(tourId, id);
      setPendingDeleteRuleId(null);
      setNoticeMsg(tAvail('ruleDeletedNotice'));
      await fetchAvailability();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tAvail('deleteRuleFailed')));
    }
  };

  const handleConfirmDeleteException = async (id: number) => {
    setErrorMsg(null);
    setNoticeMsg(null);
    try {
      await deletePartnerAvailabilityException(tourId, id);
      setPendingDeleteExceptionId(null);
      setNoticeMsg(tAvail('exceptionDeletedNotice'));
      await fetchAvailability();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tAvail('deleteExceptionFailed')));
    }
  };

  const handleGenerateDepartures = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setNoticeMsg(null);
    setIsGenerating(true);
    try {
      const res = await generatePartnerDepartures(tourId, {
        start_date: genStartDate,
        end_date: genEndDate || null,
        timezone: genTimezone,
        tour_option_id: genOptionId ? Number(genOptionId) : null,
      });
      setNoticeMsg(tDep('generatedNotice', { count: res.meta?.generated_count ?? res.data.length }));
      await fetchDepartures();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tDep('generateFailed')));
    } finally {
      setIsGenerating(false);
    }
  };

  const handleCreateDeparture = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!createOptionId) return;
    setErrorMsg(null);
    setNoticeMsg(null);
    setIsCreatingDeparture(true);
    try {
      await createPartnerDeparture(tourId, {
        tour_option_id: Number(createOptionId),
        local_date: createDate,
        local_time: createTime,
        timezone: createTimezone,
        capacity: Math.max(1, parseInt(createCapacity, 10) || 1),
        booking_cutoff_hours: Math.max(0, parseInt(createCutoffHours, 10) || 0),
      });
      setNoticeMsg(tDep('createdNotice'));
      await fetchDepartures();
    } catch (err: unknown) {
      setErrorMsg(extractErrorMessage(err, tDep('createFailed')));
    } finally {
      setIsCreatingDeparture(false);
    }
  };

  const beginEditDeparture = (dep: PartnerTourDeparture) => {
    setEditingDepartureId(dep.id);
    setEditCapacity(String(dep.capacity));
    setEditCutoff(String(dep.booking_cutoff_hours));
    setEditRowError(null);
    setCancellingDepartureId(null);
    setCancelRowError(null);
  };

  const handleSaveDepartureEdit = async (depId: number) => {
    setEditRowError(null);
    setNoticeMsg(null);
    setIsSavingDeparture(true);
    try {
      await updatePartnerDeparture(tourId, depId, {
        capacity: parseInt(editCapacity, 10) || 0,
        booking_cutoff_hours: Math.max(0, parseInt(editCutoff, 10) || 0),
      });
      setEditingDepartureId(null);
      setNoticeMsg(tDep('updatedNotice'));
      await fetchDepartures();
    } catch (err: unknown) {
      setEditRowError(extractErrorMessage(err, tDep('updateFailed')));
    } finally {
      setIsSavingDeparture(false);
    }
  };

  const beginCancelDeparture = (dep: PartnerTourDeparture) => {
    setCancellingDepartureId(dep.id);
    setConfirmBookedCancel(false);
    setCancelRowError(null);
    setEditingDepartureId(null);
    setEditRowError(null);
  };

  const handleConfirmCancelDeparture = async (dep: PartnerTourDeparture) => {
    setCancelRowError(null);
    setNoticeMsg(null);
    setIsCancellingDeparture(true);
    try {
      await cancelPartnerDeparture(tourId, dep.id, {
        confirm_booked_cancellation: dep.reserved_seats > 0 ? confirmBookedCancel : false,
      });
      setCancellingDepartureId(null);
      setConfirmBookedCancel(false);
      setNoticeMsg(tDep('cancelledNotice'));
      await fetchDepartures();
    } catch (err: unknown) {
      setCancelRowError(extractErrorMessage(err, tDep('cancelFailed')));
    } finally {
      setIsCancellingDeparture(false);
    }
  };

  const toggleDayOfWeek = (day: number) => {
    setDaysOfWeek((prev) =>
      prev.includes(day) ? prev.filter((d) => d !== day) : [...prev, day].sort()
    );
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex items-center gap-3 border-b border-gray-100 pb-4">
        <Link
          href={`/${locale}/partner`}
          aria-label={tAvail('backToDashboard')}
          className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500"
        >
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-[#0A2540]">{tAvail('pageTitle')}</h1>
          <p className="text-sm text-gray-500">{tAvail('pageSubtitle')}</p>
        </div>
      </div>

      {errorMsg && (
        <div role="alert" aria-live="assertive" className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {errorMsg}
        </div>
      )}

      {noticeMsg && (
        <div role="status" aria-live="polite" className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">
          {noticeMsg}
        </div>
      )}

      {/* ── Explicit Departures Management (D3 & D4) ────────────────────────── */}
      <section className="bg-white border border-gray-200 rounded-xl p-5 space-y-5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
          <div>
            <h2 className="font-bold text-lg text-[#0A2540] flex items-center gap-2">
              <Calendar className="w-5 h-5 text-[#0A2540]" />
              {tDep('sectionTitle')}
            </h2>
            <p className="text-sm text-gray-500">{tDep('sectionSubtitle')}</p>
          </div>
          <button
            type="button"
            onClick={() => void fetchDepartures()}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-[#0A2540] hover:bg-gray-50"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            {tDep('refreshButton')}
          </button>
        </div>

        {options.length === 0 && !isLoadingDepartures && (
          <div className="p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-900">
            {tDep('noOptionsNotice')}
          </div>
        )}

        {/* Generate + Create single departure forms */}
        <div className="grid gap-4 lg:grid-cols-2">
          <form onSubmit={handleGenerateDepartures} className="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
            <div>
              <h3 className="font-bold text-sm text-[#0A2540]">{tDep('generateHeading')}</h3>
              <p className="text-xs text-gray-600">{tDep('generateHint')}</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="flex flex-col gap-1">
                <label htmlFor="gen-option" className="text-xs font-semibold text-gray-600">{tDep('generateOption')}</label>
                <select
                  id="gen-option"
                  value={genOptionId}
                  onChange={(e) => setGenOptionId(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  <option value="">{tDep('allOptions')}</option>
                  {options.map((opt) => (
                    <option key={opt.id} value={String(opt.id)}>{opt.title}</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="gen-timezone" className="text-xs font-semibold text-gray-600">{tDep('timezoneLabel')}</label>
                <select
                  id="gen-timezone"
                  value={genTimezone}
                  onChange={(e) => setGenTimezone(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  {IANA_TIMEZONES.map((tz) => (
                    <option key={tz} value={tz}>{tz}</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="gen-start-date" className="text-xs font-semibold text-gray-600">{tDep('generateStartDate')}</label>
                <input
                  id="gen-start-date"
                  type="date"
                  required
                  value={genStartDate}
                  onChange={(e) => setGenStartDate(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="gen-end-date" className="text-xs font-semibold text-gray-600">{tDep('generateEndDate')}</label>
                <input
                  id="gen-end-date"
                  type="date"
                  required
                  value={genEndDate}
                  onChange={(e) => setGenEndDate(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
            </div>

            <button
              type="submit"
              data-testid="generate-departures-submit"
              disabled={isGenerating || options.length === 0}
              className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] text-sm font-semibold rounded-lg transition-colors disabled:opacity-50"
            >
              <Calendar className="w-4 h-4" />
              {isGenerating ? tDep('generating') : tDep('generateButton')}
            </button>
          </form>

          <form onSubmit={handleCreateDeparture} className="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
            <h3 className="font-bold text-sm text-[#0A2540]">{tDep('createHeading')}</h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-option" className="text-xs font-semibold text-gray-600">{tDep('createOption')}</label>
                <select
                  id="dep-create-option"
                  required
                  value={createOptionId}
                  onChange={(e) => setCreateOptionId(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  <option value="">{tDep('selectOption')}</option>
                  {options.map((opt) => (
                    <option key={opt.id} value={String(opt.id)}>{opt.title}</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-tz" className="text-xs font-semibold text-gray-600">{tDep('timezoneLabel')}</label>
                <select
                  id="dep-create-tz"
                  value={createTimezone}
                  onChange={(e) => setCreateTimezone(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  {IANA_TIMEZONES.map((tz) => (
                    <option key={tz} value={tz}>{tz}</option>
                  ))}
                </select>
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-date" className="text-xs font-semibold text-gray-600">{tDep('localDate')}</label>
                <input
                  id="dep-create-date"
                  type="date"
                  required
                  value={createDate}
                  onChange={(e) => setCreateDate(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-time" className="text-xs font-semibold text-gray-600">{tDep('localTime')}</label>
                <input
                  id="dep-create-time"
                  type="time"
                  required
                  value={createTime}
                  onChange={(e) => setCreateTime(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-capacity" className="text-xs font-semibold text-gray-600">{tDep('capacityLabel')}</label>
                <input
                  id="dep-create-capacity"
                  type="number"
                  required
                  min={1}
                  max={1000}
                  value={createCapacity}
                  onChange={(e) => setCreateCapacity(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1">
                <label htmlFor="dep-create-cutoff" className="text-xs font-semibold text-gray-600">{tDep('cutoffHoursLabel')}</label>
                <input
                  id="dep-create-cutoff"
                  type="number"
                  required
                  min={0}
                  max={8760}
                  value={createCutoffHours}
                  onChange={(e) => setCreateCutoffHours(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
            </div>

            <button
              type="submit"
              data-testid="create-departure-submit"
              disabled={isCreatingDeparture || !createOptionId}
              className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50"
            >
              <Plus className="w-4 h-4" />
              {isCreatingDeparture ? tDep('creating') : tDep('createButton')}
            </button>
          </form>
        </div>

        {/* Filters */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-gray-100">
          <div className="flex flex-col gap-1">
            <label htmlFor="dep-filter-option" className="text-xs font-semibold text-gray-600">{tDep('filterOption')}</label>
            <select
              id="dep-filter-option"
              data-testid="departures-filter-option"
              value={filterOptionId}
              onChange={(e) => setFilterOptionId(e.target.value)}
              className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
            >
              <option value="">{tDep('allOptions')}</option>
              {options.map((opt) => (
                <option key={opt.id} value={String(opt.id)}>{opt.title}</option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label htmlFor="dep-filter-status" className="text-xs font-semibold text-gray-600">{tDep('filterStatus')}</label>
            <select
              id="dep-filter-status"
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
            >
              <option value="">{tDep('allStatuses')}</option>
              <option value="active">{tDep('status.active')}</option>
              <option value="cancelled">{tDep('status.cancelled')}</option>
              <option value="completed">{tDep('status.completed')}</option>
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label htmlFor="dep-filter-from" className="text-xs font-semibold text-gray-600">{tDep('filterFrom')}</label>
            <input
              id="dep-filter-from"
              type="date"
              value={filterFrom}
              onChange={(e) => setFilterFrom(e.target.value)}
              className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label htmlFor="dep-filter-to" className="text-xs font-semibold text-gray-600">{tDep('filterTo')}</label>
            <input
              id="dep-filter-to"
              type="date"
              value={filterTo}
              onChange={(e) => setFilterTo(e.target.value)}
              className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
            />
          </div>
        </div>

        {/* Departures List */}
        {isLoadingDepartures ? (
          <div className="py-6 text-center text-sm text-gray-600">{tDep('loading')}</div>
        ) : departures.length === 0 ? (
          <div className="py-8 text-center text-sm text-gray-700 bg-gray-50 rounded-lg border border-dashed border-gray-200">
            {tDep('empty')}
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {departures.map((dep) => {
              const isEditing = editingDepartureId === dep.id;
              const isCancelling = cancellingDepartureId === dep.id;
              const statusClass =
                dep.status === 'active'
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : dep.status === 'cancelled'
                    ? 'bg-red-50 text-red-700 border-red-200'
                    : 'bg-gray-100 text-gray-700 border-gray-200';

              return (
                <div key={dep.id} data-testid={`departure-row-${dep.id}`} className="py-4 space-y-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-semibold text-[#0A2540]">
                          {dep.local_date} · {dep.local_time.substring(0, 5)}
                        </span>
                        <span className="text-xs font-mono text-gray-600">{dep.timezone}</span>
                        <span className={`text-[11px] px-2 py-0.5 rounded border font-semibold ${statusClass}`}>
                          {tDep(`status.${dep.status}`)}
                        </span>
                      </div>
                      <div className="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                        {dep.option_title && <span className="font-medium text-[#0A2540]">{dep.option_title}</span>}
                        <span className="inline-flex items-center gap-1">
                          <Users className="w-3.5 h-3.5" />
                          {tDep('reservedSummary', { reserved: dep.reserved_seats, capacity: dep.capacity })}
                        </span>
                        <span>{tDep('availableSummary', { available: dep.available_spots })}</span>
                        <span>{tDep('cutoffBadge', { hours: dep.booking_cutoff_hours })}</span>
                      </div>
                    </div>

                    {dep.status === 'active' && !isEditing && !isCancelling && (
                      <div className="flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          onClick={() => beginEditDeparture(dep)}
                          className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-[#0A2540] hover:bg-gray-50"
                        >
                          <Pencil className="w-3.5 h-3.5" />
                          {tDep('editButton')}
                        </button>
                        <button
                          type="button"
                          onClick={() => beginCancelDeparture(dep)}
                          className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-red-200 text-xs font-semibold text-red-700 hover:bg-red-50"
                        >
                          <XCircle className="w-3.5 h-3.5" />
                          {tDep('cancelButton')}
                        </button>
                      </div>
                    )}
                  </div>

                  {isEditing && (
                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-3">
                      {editRowError && (
                        <div role="alert" className="p-2.5 rounded bg-red-50 border border-red-200 text-xs text-red-700">
                          {editRowError}
                        </div>
                      )}
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="flex flex-col gap-1">
                          <label htmlFor={`edit-cap-${dep.id}`} className="text-xs font-semibold text-gray-600">
                            {tDep('capacityLabel')}
                          </label>
                          <input
                            id={`edit-cap-${dep.id}`}
                            type="number"
                            min={1}
                            max={1000}
                            value={editCapacity}
                            onChange={(e) => setEditCapacity(e.target.value)}
                            className="px-3 py-1.5 text-sm border rounded-lg bg-white outline-none"
                          />
                        </div>
                        <div className="flex flex-col gap-1">
                          <label htmlFor={`edit-cutoff-${dep.id}`} className="text-xs font-semibold text-gray-600">
                            {tDep('cutoffHoursLabel')}
                          </label>
                          <input
                            id={`edit-cutoff-${dep.id}`}
                            type="number"
                            min={0}
                            max={8760}
                            value={editCutoff}
                            onChange={(e) => setEditCutoff(e.target.value)}
                            className="px-3 py-1.5 text-sm border rounded-lg bg-white outline-none"
                          />
                        </div>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          disabled={isSavingDeparture}
                          onClick={() => void handleSaveDepartureEdit(dep.id)}
                          className="px-3 py-1.5 rounded-lg bg-[#0A2540] text-white text-xs font-semibold hover:bg-[#FFB800] hover:text-[#0A2540]"
                        >
                          {tDep('saveEditButton')}
                        </button>
                        <button
                          type="button"
                          onClick={() => setEditingDepartureId(null)}
                          className="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-white"
                        >
                          {tDep('cancelEditButton')}
                        </button>
                      </div>
                    </div>
                  )}

                  {isCancelling && (
                    <div className="bg-red-50/70 border border-red-200 rounded-lg p-3 space-y-3" role="region" aria-label={tDep('cancelConfirmHeading')}>
                      <p className="text-xs font-semibold text-red-900">{tDep('cancelConfirmHeading')}</p>
                      {dep.reserved_seats > 0 && (
                        <div className="space-y-2">
                          <p className="text-xs text-red-800">{tDep('cancelBookedWarning', { count: dep.reserved_seats })}</p>
                          <label className="flex items-start gap-2 text-xs text-red-900">
                            <input
                              type="checkbox"
                              checked={confirmBookedCancel}
                              onChange={(e) => setConfirmBookedCancel(e.target.checked)}
                              className="mt-0.5"
                            />
                            <span>{tDep('cancelConfirmCheckbox', { count: dep.reserved_seats })}</span>
                          </label>
                        </div>
                      )}
                      {cancelRowError && (
                        <div role="alert" className="p-2 rounded bg-red-100 text-xs text-red-800">
                          {cancelRowError}
                        </div>
                      )}
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          disabled={isCancellingDeparture || (dep.reserved_seats > 0 && !confirmBookedCancel)}
                          onClick={() => void handleConfirmCancelDeparture(dep)}
                          className="px-3 py-1.5 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 disabled:opacity-50"
                        >
                          {tDep('confirmCancelButton')}
                        </button>
                        <button
                          type="button"
                          onClick={() => setCancellingDepartureId(null)}
                          className="px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-semibold text-gray-700"
                        >
                          {tDep('keepDepartureButton')}
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* ── Recurring Schedule & Date Exceptions ────────────────────────────── */}
      <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          {/* Rules */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h2 className="font-bold text-lg text-[#0A2540] flex items-center gap-2">
                <Clock className="w-5 h-5 text-gray-400" />
                {tAvail('rulesHeading')}
              </h2>
              <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-semibold">
                {tAvail('rulesActiveCount', { count: rules.length })}
              </span>
            </div>

            {isLoadingAvailability ? (
              <div className="text-center py-6 text-gray-600">{tAvail('loadingSchedules')}</div>
            ) : rules.length === 0 ? (
              <div className="text-center py-8 text-gray-600 text-sm">{tAvail('noRulesConfigured')}</div>
            ) : (
              <div className="divide-y divide-gray-100">
                {rules.map((rule) => {
                  const ruleLabel =
                    rule.rule_type === 'specific_date'
                      ? tAvail('ruleTypeSpecificDate')
                      : tAvail('ruleTypeRecurring');
                  const rangeLabel = rule.end_date
                    ? tAvail('ruleRange', { start: rule.start_date, end: rule.end_date })
                    : tAvail('ruleRangeIndefinite', { start: rule.start_date });

                  return (
                    <div key={rule.id} className="py-4 first:pt-0 last:pb-0 space-y-2">
                      <div className="flex items-start justify-between gap-4">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold text-[#0A2540]">
                              {tAvail('ruleSlotTitle', { type: ruleLabel })}
                            </span>
                            <span className="text-xs font-mono bg-[#FFB800]/10 text-[#0A2540] px-2 py-0.5 rounded font-bold">
                              {rule.start_time.substring(0, 5)}
                            </span>
                          </div>
                          <p className="text-xs text-gray-600">{rangeLabel}</p>
                          {rule.days_of_week && (
                            <div className="flex flex-wrap gap-1 pt-1">
                              {[0, 1, 2, 3, 4, 5, 6].map((dayIdx) => {
                                const active = rule.days_of_week?.includes(dayIdx);
                                return (
                                  <span
                                    key={dayIdx}
                                    className={`px-1.5 py-0.5 rounded-full text-[10px] font-bold ${
                                      active ? 'bg-[#FFB800] text-[#0A2540]' : 'bg-gray-100 text-gray-700'
                                    }`}
                                  >
                                    {tAvail(`weekday${dayIdx}`)}
                                  </span>
                                );
                              })}
                            </div>
                          )}
                          <p className="text-xs text-gray-600 font-medium">
                            {tAvail('capacityTravelers', { capacity: rule.capacity })}
                          </p>
                        </div>
                        <button
                          type="button"
                          aria-label={tAvail('removeRule')}
                          onClick={() => setPendingDeleteRuleId(rule.id)}
                          className="p-1.5 rounded text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                      {pendingDeleteRuleId === rule.id && (
                        <div className="flex flex-wrap items-center justify-between gap-2 bg-red-50 border border-red-200 rounded-lg p-2.5 text-xs">
                          <span className="text-red-900 font-medium">{tAvail('confirmDeleteRule')}</span>
                          <div className="flex gap-2">
                            <button
                              type="button"
                              onClick={() => void handleConfirmDeleteRule(rule.id)}
                              className="px-2.5 py-1 rounded bg-red-600 text-white font-semibold"
                            >
                              {tAvail('confirmAction')}
                            </button>
                            <button
                              type="button"
                              onClick={() => setPendingDeleteRuleId(null)}
                              className="px-2.5 py-1 rounded border border-gray-200 bg-white text-gray-700 font-semibold"
                            >
                              {tAvail('cancelAction')}
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Exceptions */}
          <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h2 className="font-bold text-lg text-[#0A2540] flex items-center gap-2">
                <Calendar className="w-5 h-5 text-gray-400" />
                {tAvail('exceptionsHeading')}
              </h2>
              <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded font-semibold">
                {tAvail('exceptionsConfiguredCount', { count: exceptions.length })}
              </span>
            </div>

            {isLoadingAvailability ? (
              <div className="text-center py-6 text-gray-600">{tAvail('loadingOverrides')}</div>
            ) : exceptions.length === 0 ? (
              <div className="text-center py-8 text-gray-600 text-sm">{tAvail('noExceptionsConfigured')}</div>
            ) : (
              <div className="divide-y divide-gray-100">
                {exceptions.map((exc) => {
                  const isBlock = exc.exception_type === 'block';
                  const excTypeLabel =
                    exc.exception_type === 'block'
                      ? tAvail('exceptionTypeBlock')
                      : exc.exception_type === 'capacity_override'
                        ? tAvail('exceptionTypeCapacityOverride')
                        : tAvail('exceptionTypePriceMultiplier');

                  return (
                    <div key={exc.id} className="py-4 first:pt-0 last:pb-0 space-y-2">
                      <div className="flex items-start justify-between gap-4">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold text-gray-900">
                              {new Date(exc.date + 'T00:00:00').toLocaleDateString(locale)}
                            </span>
                            <span
                              className={`text-[10px] px-2 py-0.5 rounded font-semibold ${
                                isBlock ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-blue-50 text-blue-700 border border-blue-100'
                              }`}
                            >
                              {excTypeLabel}
                            </span>
                          </div>
                          {exc.note && <p className="text-xs text-gray-600 italic">&ldquo;{exc.note}&rdquo;</p>}
                          <div className="text-xs text-gray-600 space-y-0.5">
                            {exc.start_time && <p>{tAvail('timeSlotLabel', { time: exc.start_time.substring(0, 5) })}</p>}
                            {exc.capacity !== null && <p>{tAvail('overrideCapacityLabel', { capacity: exc.capacity })}</p>}
                            {parseFloat(exc.price_multiplier) !== 1 && (
                              <p>{tAvail('priceMultiplierLabel', { multiplier: exc.price_multiplier })}</p>
                            )}
                          </div>
                        </div>
                        <button
                          type="button"
                          aria-label={tAvail('removeException')}
                          onClick={() => setPendingDeleteExceptionId(exc.id)}
                          className="p-1.5 rounded text-gray-600 hover:text-red-600 hover:bg-red-50 transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                      {pendingDeleteExceptionId === exc.id && (
                        <div className="flex flex-wrap items-center justify-between gap-2 bg-red-50 border border-red-200 rounded-lg p-2.5 text-xs">
                          <span className="text-red-900 font-medium">{tAvail('confirmDeleteException')}</span>
                          <div className="flex gap-2">
                            <button
                              type="button"
                              onClick={() => void handleConfirmDeleteException(exc.id)}
                              className="px-2.5 py-1 rounded bg-red-600 text-white font-semibold"
                            >
                              {tAvail('confirmAction')}
                            </button>
                            <button
                              type="button"
                              onClick={() => setPendingDeleteExceptionId(null)}
                              className="px-2.5 py-1 rounded border border-gray-200 bg-white text-gray-700 font-semibold"
                            >
                              {tAvail('cancelAction')}
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Right Column: Rule / Exception Creation Panel */}
        <aside className="space-y-6">
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <div className="flex bg-gray-200/60 p-1 rounded-lg">
              <button
                type="button"
                onClick={() => setActiveTab('rules')}
                className={`flex-1 text-center py-1.5 text-xs font-semibold rounded-md transition-colors ${
                  activeTab === 'rules' ? 'bg-white text-[#0A2540] shadow-sm' : 'text-gray-700 hover:text-[#0A2540]'
                }`}
              >
                {tAvail('tabRules')}
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('exceptions')}
                className={`flex-1 text-center py-1.5 text-xs font-semibold rounded-md transition-colors ${
                  activeTab === 'exceptions' ? 'bg-white text-[#0A2540] shadow-sm' : 'text-gray-700 hover:text-[#0A2540]'
                }`}
              >
                {tAvail('tabExceptions')}
              </button>
            </div>

            {activeTab === 'rules' ? (
              <form onSubmit={handleCreateRule} className="space-y-3">
                <h3 className="font-bold text-sm text-[#0A2540] pb-1 border-b border-gray-100">{tAvail('addRuleHeading')}</h3>

                <div className="flex flex-col gap-1">
                  <label htmlFor="rule-type-select" className="text-xs font-semibold text-gray-600">{tAvail('typeLabel')}</label>
                  <select
                    id="rule-type-select"
                    value={ruleType}
                    onChange={(e) => setRuleType(e.target.value as 'recurring' | 'specific_date')}
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  >
                    <option value="recurring">{tAvail('ruleTypeRecurring')}</option>
                    <option value="specific_date">{tAvail('ruleTypeSpecificDate')}</option>
                  </select>
                </div>

                {ruleType === 'recurring' && (
                  <div className="flex flex-col gap-1">
                    <span className="text-xs font-semibold text-gray-600">{tAvail('daysOfWeek')}</span>
                    <div className="flex flex-wrap justify-between gap-1 pt-1">
                      {[0, 1, 2, 3, 4, 5, 6].map((idx) => {
                        const active = daysOfWeek.includes(idx);
                        return (
                          <button
                            type="button"
                            key={idx}
                            aria-pressed={active}
                            onClick={() => toggleDayOfWeek(idx)}
                            className={`px-2 py-1 rounded-full text-xs font-bold transition-all ${
                              active ? 'bg-[#FFB800] text-[#0A2540]' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100'
                            }`}
                          >
                            {tAvail(`weekday${idx}`)}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label htmlFor="rule-start-time" className="text-xs font-semibold text-gray-600">{tAvail('startTime')}</label>
                    <input
                      id="rule-start-time"
                      type="time"
                      required
                      value={startTime}
                      onChange={(e) => setStartTime(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label htmlFor="rule-max-capacity" className="text-xs font-semibold text-gray-600">{tAvail('maxCapacity')}</label>
                    <input
                      id="rule-max-capacity"
                      type="number"
                      required
                      min={1}
                      value={capacity}
                      onChange={(e) => setCapacity(parseInt(e.target.value, 10) || 1)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label htmlFor="rule-start-date" className="text-xs font-semibold text-gray-600">{tAvail('startDate')}</label>
                    <input
                      id="rule-start-date"
                      type="date"
                      required
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label htmlFor="rule-end-date" className="text-xs font-semibold text-gray-600">{tAvail('endDateOptional')}</label>
                    <input
                      id="rule-end-date"
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  {tAvail('saveRule')}
                </button>
              </form>
            ) : (
              <form onSubmit={handleCreateException} className="space-y-3">
                <h3 className="font-bold text-sm text-[#0A2540] pb-1 border-b border-gray-100">{tAvail('addExceptionHeading')}</h3>

                <div className="flex flex-col gap-1">
                  <label htmlFor="exc-type-select" className="text-xs font-semibold text-gray-600">{tAvail('exceptionType')}</label>
                  <select
                    id="exc-type-select"
                    value={exceptionType}
                    onChange={(e) => setExceptionType(e.target.value as 'block' | 'capacity_override' | 'price_multiplier')}
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  >
                    <option value="block">{tAvail('exceptionTypeBlock')}</option>
                    <option value="capacity_override">{tAvail('exceptionTypeCapacityOverride')}</option>
                    <option value="price_multiplier">{tAvail('exceptionTypePriceMultiplier')}</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div className="flex flex-col gap-1">
                    <label htmlFor="exc-target-date" className="text-xs font-semibold text-gray-600">{tAvail('targetDate')}</label>
                    <input
                      id="exc-target-date"
                      type="date"
                      required
                      value={excDate}
                      onChange={(e) => setExcDate(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                  <div className="flex flex-col gap-1">
                    <label htmlFor="exc-start-time" className="text-xs font-semibold text-gray-600">{tAvail('startTimeOptional')}</label>
                    <input
                      id="exc-start-time"
                      type="time"
                      value={excStartTime}
                      onChange={(e) => setExcStartTime(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                </div>

                {exceptionType === 'capacity_override' && (
                  <div className="flex flex-col gap-1">
                    <label htmlFor="exc-override-capacity" className="text-xs font-semibold text-gray-600">{tAvail('overrideCapacity')}</label>
                    <input
                      id="exc-override-capacity"
                      type="number"
                      required
                      min={0}
                      value={excCapacity}
                      onChange={(e) => setExcCapacity(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                )}

                {exceptionType === 'price_multiplier' && (
                  <div className="flex flex-col gap-1">
                    <label htmlFor="exc-price-multiplier" className="text-xs font-semibold text-gray-600">{tAvail('priceMultiplier')}</label>
                    <input
                      id="exc-price-multiplier"
                      type="number"
                      required
                      min={0.5}
                      max={5}
                      step={0.05}
                      value={priceMultiplier}
                      onChange={(e) => setPriceMultiplier(e.target.value)}
                      className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                    />
                  </div>
                )}

                <div className="flex flex-col gap-1">
                  <label htmlFor="exc-note-reason" className="text-xs font-semibold text-gray-600">{tAvail('noteReason')}</label>
                  <input
                    id="exc-note-reason"
                    type="text"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder={tAvail('noteReasonPlaceholder')}
                    className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  {tAvail('saveException')}
                </button>
              </form>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}

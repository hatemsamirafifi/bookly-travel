'use client';

import { useState, useEffect, use, useCallback, useMemo, useRef } from 'react';
import { ArrowLeft, Plus, Trash2, Edit, Save, X, CheckCircle2 } from 'lucide-react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import {
  createPartnerPricingTier,
  deletePartnerPricingTier,
  getPartnerPricing,
  syncPartnerPricingTiers,
  updatePartnerPricingTier,
} from '@/lib/api/partner';
import type { PartnerPricingOptionSummary, PartnerPricingTierRecord } from '@/lib/api/types';
import { formatCurrency } from '@/lib/utils';

interface DraftBracket {
  localKey: string;
  name: string;
  minParticipants: number;
  maxParticipants: string;
  priceEur: string;
}

function parseEuroToCents(raw: string): number | null {
  const trimmed = raw.trim();
  if (!/^\d+(?:\.\d{1,2})?$/.test(trimmed)) return null;
  const [whole, frac = ''] = trimmed.split('.');
  const cents = Number(whole) * 100 + Number(frac.padEnd(2, '0'));
  if (!Number.isSafeInteger(cents) || cents <= 0) return null;
  return cents;
}

function toEuroInput(cents: number): string {
  return (cents / 100).toFixed(2);
}

function buildStarterBrackets(
  option: PartnerPricingOptionSummary,
  optionTiers: PartnerPricingTierRecord[],
): DraftBracket[] {
  if (optionTiers.length > 0) {
    return [...optionTiers]
      .sort((a, b) => a.min_participants - b.min_participants || a.id - b.id)
      .map((tier, idx) => ({
        localKey: `tier-${tier.id}-${idx}`,
        name: tier.name,
        minParticipants: tier.min_participants,
        maxParticipants: tier.max_participants === null ? '' : String(tier.max_participants),
        priceEur: toEuroInput(tier.price_amount ?? Math.round(parseFloat(tier.price) * 100)),
      }));
  }

  return [
    {
      localKey: `starter-${option.id}`,
      name: 'Standard',
      minParticipants: 1,
      maxParticipants: String(Math.max(1, option.effective_capacity)),
      priceEur: toEuroInput(Math.max(100, option.price_amount || 5000)),
    },
  ];
}

function extractErrorMessage(err: unknown, fallback: string): string {
  if (err && typeof err === 'object') {
    const record = err as { message?: unknown; errors?: Record<string, string[]> };
    if (record.errors && typeof record.errors === 'object') {
      const firstList = Object.values(record.errors).find(
        (msgs) => Array.isArray(msgs) && msgs.length > 0 && typeof msgs[0] === 'string',
      );
      if (firstList && firstList[0]) return firstList[0];
    }
    if (typeof record.message === 'string' && record.message.trim() !== '') {
      return record.message;
    }
  }
  return fallback;
}

export default function PricingPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const tourId = resolvedParams.id;
  const locale = resolvedParams.locale;
  const t = useTranslations('partner.tours.pricingPage');

  const errorFallbacksRef = useRef({
    loadFailed: 'Failed to load pricing tiers.',
    saveFailed: 'Failed to save pricing tier configuration.',
    deleteFailed: 'Failed to delete pricing tier.',
  });
  errorFallbacksRef.current = {
    loadFailed: t('loadFailed'),
    saveFailed: t('saveFailed'),
    deleteFailed: t('deleteFailed'),
  };

  const [allTiers, setAllTiers] = useState<PartnerPricingTierRecord[]>([]);
  const [options, setOptions] = useState<PartnerPricingOptionSummary[]>([]);
  const [selectedOptionId, setSelectedOptionId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState<'option' | 'legacy'>('option');
  const [brackets, setBrackets] = useState<DraftBracket[]>([]);

  const [isLoading, setIsLoading] = useState(true);
  const [savingSchedule, setSavingSchedule] = useState(false);
  const [savingLegacy, setSavingLegacy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Legacy tour-level form states
  const [isEditing, setIsEditing] = useState<number | null>(null);
  const [legacyName, setLegacyName] = useState('');
  const [legacyPrice, setLegacyPrice] = useState('');
  const [legacyMin, setLegacyMin] = useState(1);
  const [legacyMax, setLegacyMax] = useState<number | ''>('');
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

  const fetchPricing = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await getPartnerPricing(tourId);
      const loadedTiers = Array.isArray(res.data) ? res.data : [];
      const loadedOptions = Array.isArray(res.meta?.options) ? res.meta.options : [];
      setAllTiers(loadedTiers);
      setOptions(loadedOptions);

      setSelectedOptionId((prev) => {
        if (loadedOptions.length === 0) return null;
        if (prev !== null && loadedOptions.some((opt) => opt.id === prev)) return prev;
        return loadedOptions[0].id;
      });
      if (loadedOptions.length === 0) {
        setActiveTab('legacy');
      }
    } catch (err: unknown) {
      setError(extractErrorMessage(err, errorFallbacksRef.current.loadFailed));
    } finally {
      setIsLoading(false);
    }
  }, [tourId]);

  useEffect(() => {
    fetchPricing();
  }, [fetchPricing]);

  const selectedOption = useMemo(
    () => options.find((o) => o.id === selectedOptionId) ?? null,
    [options, selectedOptionId],
  );

  useEffect(() => {
    if (!selectedOption) {
      setBrackets([]);
      return;
    }
    const optionTiers = allTiers.filter((tier) => tier.tour_option_id === selectedOption.id);
    setBrackets(buildStarterBrackets(selectedOption, optionTiers));
  }, [selectedOption, allTiers]);

  const legacyTiers = useMemo(
    () =>
      allTiers
        .filter((tier) => tier.tour_option_id === null)
        .sort((a, b) => a.min_participants - b.min_participants || a.id - b.id),
    [allTiers],
  );

  const handleAddBracket = () => {
    setBrackets((prev) => {
      const last = prev[prev.length - 1];
      const lastMax = last?.maxParticipants !== '' ? Number(last?.maxParticipants) : NaN;
      const nextMin = Number.isFinite(lastMax) && lastMax >= 1 ? lastMax + 1 : prev.length + 1;
      const defaultMax = selectedOption ? Math.max(nextMin, selectedOption.effective_capacity) : nextMin;
      return [
        ...prev,
        {
          localKey: `new-${Date.now()}-${prev.length}`,
          name: `Tier ${prev.length + 1}`,
          minParticipants: nextMin,
          maxParticipants: String(defaultMax),
          priceEur: last?.priceEur || '50.00',
        },
      ];
    });
  };

  const handleUpdateBracket = (localKey: string, patch: Partial<DraftBracket>) => {
    setBrackets((prev) =>
      prev.map((item) => (item.localKey === localKey ? { ...item, ...patch } : item)),
    );
  };

  const handleRemoveBracket = (localKey: string) => {
    setBrackets((prev) => prev.filter((item) => item.localKey !== localKey));
  };

  const handleSaveSchedule = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedOption) return;

    setError(null);
    setNotice(null);

    const payloadTiers: Array<{
      name: string;
      min_participants: number;
      max_participants: number | null;
      price_amount: number;
      currency: string;
    }> = [];

    for (let i = 0; i < brackets.length; i += 1) {
      const row = brackets[i];
      const cents = parseEuroToCents(row.priceEur);
      if (cents === null) {
        setError(t('saveFailed'));
        return;
      }
      const parsedMax =
        row.maxParticipants.trim() === '' ? null : Number.parseInt(row.maxParticipants.trim(), 10);
      if (parsedMax !== null && (!Number.isInteger(parsedMax) || parsedMax < 1)) {
        setError(t('saveFailed'));
        return;
      }
      payloadTiers.push({
        name: row.name.trim() || `Tier ${i + 1}`,
        min_participants: Math.max(1, row.minParticipants),
        max_participants: parsedMax,
        price_amount: cents,
        currency: selectedOption.currency || 'EUR',
      });
    }

    setSavingSchedule(true);
    try {
      await syncPartnerPricingTiers(tourId, {
        tour_option_id: selectedOption.id,
        pricing_mode: 'per_person',
        tiers: payloadTiers,
      });
      setNotice(t('savedScheduleNotice'));
      await fetchPricing();
    } catch (err: unknown) {
      setError(extractErrorMessage(err, errorFallbacksRef.current.saveFailed));
    } finally {
      setSavingSchedule(false);
    }
  };

  const previewRows = useMemo(() => {
    if (!selectedOption) return [];
    const maxCap = Math.max(1, selectedOption.effective_capacity || 10);
    const candidates = new Set<number>([1, 2, 3, 4, 5, maxCap]);
    for (const row of brackets) {
      if (row.minParticipants >= 1 && row.minParticipants <= maxCap) {
        candidates.add(row.minParticipants);
      }
      const maxVal = row.maxParticipants.trim() === '' ? null : Number.parseInt(row.maxParticipants.trim(), 10);
      if (maxVal !== null && maxVal >= 1 && maxVal <= maxCap) {
        candidates.add(maxVal);
      }
    }
    const counts = Array.from(candidates)
      .filter((c) => c >= 1 && c <= maxCap)
      .sort((a, b) => a - b);

    return counts.map((count) => {
      const matched = brackets.find((row) => {
        const min = row.minParticipants;
        const maxVal = row.maxParticipants.trim() === '' ? null : Number.parseInt(row.maxParticipants.trim(), 10);
        return count >= min && (maxVal === null || count <= maxVal);
      });
      const cents = matched ? parseEuroToCents(matched.priceEur) : null;
      const effectiveCents = cents ?? selectedOption.price_amount;
      const currency = selectedOption.currency || 'EUR';
      return {
        count,
        tierName: matched?.name || null,
        unitFormatted: formatCurrency(effectiveCents, currency, locale),
        totalFormatted: formatCurrency(effectiveCents * count, currency, locale),
      };
    });
  }, [brackets, locale, selectedOption]);

  const handleLegacySave = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setNotice(null);

    const priceCents = parseEuroToCents(legacyPrice);
    if (priceCents === null) {
      setError(errorFallbacksRef.current.saveFailed);
      return;
    }

    setSavingLegacy(true);
    try {
      const payload = {
        name: legacyName.trim(),
        price_amount: priceCents,
        min_participants: legacyMin,
        max_participants: legacyMax === '' ? null : legacyMax,
        tour_option_id: null,
      };
      if (isEditing === -1) {
        await createPartnerPricingTier(tourId, payload);
      } else if (isEditing !== null) {
        await updatePartnerPricingTier(tourId, isEditing, payload);
      }
      setIsEditing(null);
      setLegacyName('');
      setLegacyPrice('');
      setLegacyMin(1);
      setLegacyMax('');
      setNotice(t('tierSavedNotice'));
      await fetchPricing();
    } catch (err: unknown) {
      setError(extractErrorMessage(err, errorFallbacksRef.current.saveFailed));
    } finally {
      setSavingLegacy(false);
    }
  };

  const handleLegacyDelete = async (id: number) => {
    setError(null);
    setNotice(null);
    try {
      await deletePartnerPricingTier(tourId, id);
      setConfirmDeleteId(null);
      setNotice(t('tierDeletedNotice'));
      await fetchPricing();
    } catch (err: unknown) {
      setError(extractErrorMessage(err, errorFallbacksRef.current.deleteFailed));
    }
  };

  const startEditLegacy = (tier: PartnerPricingTierRecord) => {
    setConfirmDeleteId(null);
    setIsEditing(tier.id);
    setLegacyName(tier.name);
    setLegacyPrice(toEuroInput(tier.price_amount ?? Math.round(parseFloat(tier.price) * 100)));
    setLegacyMin(tier.min_participants);
    setLegacyMax(tier.max_participants ?? '');
  };

  const startCreateLegacy = () => {
    setConfirmDeleteId(null);
    setIsEditing(-1);
    setLegacyName('');
    setLegacyPrice('');
    setLegacyMin(1);
    setLegacyMax('');
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-4">
        <div className="flex items-center gap-3">
          <Link
            href={`/${locale}/partner/tours`}
            aria-label={t('backToDashboard')}
            className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-700"
          >
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-[#0A2540]">{t('pageTitle')}</h1>
            <p className="text-sm text-gray-700">{t('pageSubtitle')}</p>
          </div>
        </div>
        <span className="inline-flex items-center rounded-full border border-[#0A2540]/20 bg-[#F7F9FB] px-3 py-1 text-xs font-semibold text-[#0A2540]">
          {t('modeBadge')}
        </span>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm" role="alert">
          {error}
        </div>
      )}

      {notice && (
        <div className="flex items-center gap-2 p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-lg text-sm" role="status">
          <CheckCircle2 className="w-4 h-4 shrink-0" />
          <span>{notice}</span>
        </div>
      )}

      {/* Tabs when tour has active options */}
      {options.length > 0 && (
        <div className="flex flex-wrap gap-2 border-b border-gray-200" role="tablist" aria-label={t('pageTitle')}>
          <button
            type="button"
            role="tab"
            id="tab-option-schedule"
            aria-selected={activeTab === 'option'}
            aria-controls="panel-option-schedule"
            onClick={() => { setActiveTab('option'); setError(null); }}
            className={`px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors ${
              activeTab === 'option'
                ? 'border-[#0A2540] text-[#0A2540]'
                : 'border-transparent text-gray-700 hover:text-[#0A2540]'
            }`}
          >
            {t('optionScheduleTab')}
          </button>
          <button
            type="button"
            role="tab"
            id="tab-legacy-tiers"
            aria-selected={activeTab === 'legacy'}
            aria-controls="panel-legacy-tiers"
            onClick={() => { setActiveTab('legacy'); setError(null); }}
            className={`px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors ${
              activeTab === 'legacy'
                ? 'border-[#0A2540] text-[#0A2540]'
                : 'border-transparent text-gray-700 hover:text-[#0A2540]'
            }`}
          >
            {t('legacyTourTab')}
          </button>
        </div>
      )}

      {isLoading ? (
        <div className="text-center py-12 text-gray-700">{t('loading')}</div>
      ) : activeTab === 'option' && selectedOption ? (
        <div id="panel-option-schedule" role="tabpanel" aria-labelledby="tab-option-schedule" className="grid gap-6 lg:grid-cols-[1.35fr_0.9fr] lg:items-start">
          <form onSubmit={handleSaveSchedule} className="space-y-5 bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div className="space-y-1.5">
              <label htmlFor="pricing-option-select" className="block text-sm font-semibold text-[#0A2540]">
                {t('selectOptionLabel')}
              </label>
              <select
                id="pricing-option-select"
                value={selectedOption.id}
                onChange={(e) => {
                  setSelectedOptionId(Number(e.target.value));
                  setNotice(null);
                  setError(null);
                }}
                className="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm text-[#0A2540] focus:border-[#0A2540] focus:outline-none focus:ring-2 focus:ring-[#0A2540]/20"
              >
                {options.map((opt) => (
                  <option key={opt.id} value={opt.id}>
                    {opt.title} ({formatCurrency(opt.price_amount, opt.currency || 'EUR', locale)})
                  </option>
                ))}
              </select>
              <p className="text-xs text-gray-700">
                {t('selectOptionHint', { capacity: selectedOption.effective_capacity })}
              </p>
            </div>

            <div className="space-y-3">
              {brackets.map((row, idx) => (
                <div
                  key={row.localKey}
                  data-testid={`option-bracket-${idx}`}
                  className="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-[#0A2540]">
                      #{idx + 1}
                    </span>
                    <button
                      type="button"
                      onClick={() => handleRemoveBracket(row.localKey)}
                      disabled={brackets.length <= 1}
                      aria-label={`${t('delete')} #${idx + 1}`}
                      className="p-1.5 rounded-md text-gray-700 hover:text-red-700 hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="flex flex-col gap-1">
                      <label htmlFor={`bracket-name-${row.localKey}`} className="text-xs font-semibold text-gray-700">
                        {t('tierName')}
                      </label>
                      <input
                        id={`bracket-name-${row.localKey}`}
                        type="text"
                        required
                        value={row.name}
                        onChange={(e) => handleUpdateBracket(row.localKey, { name: e.target.value })}
                        placeholder={t('tierNamePlaceholder')}
                        className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-[#0A2540]"
                      />
                    </div>
                    <div className="flex flex-col gap-1">
                      <label htmlFor={`bracket-price-${row.localKey}`} className="text-xs font-semibold text-gray-700">
                        {t('priceEur')}
                      </label>
                      <input
                        id={`bracket-price-${row.localKey}`}
                        type="number"
                        required
                        min="0.01"
                        step="0.01"
                        value={row.priceEur}
                        onChange={(e) => handleUpdateBracket(row.localKey, { priceEur: e.target.value })}
                        placeholder="0.00"
                        className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-[#0A2540]"
                      />
                    </div>
                    <div className="flex flex-col gap-1">
                      <label htmlFor={`bracket-min-${row.localKey}`} className="text-xs font-semibold text-gray-700">
                        {t('minParticipants')}
                      </label>
                      <input
                        id={`bracket-min-${row.localKey}`}
                        type="number"
                        required
                        min="1"
                        value={row.minParticipants}
                        onChange={(e) =>
                          handleUpdateBracket(row.localKey, {
                            minParticipants: Math.max(1, parseInt(e.target.value, 10) || 1),
                          })
                        }
                        className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-[#0A2540]"
                      />
                    </div>
                    <div className="flex flex-col gap-1">
                      <label htmlFor={`bracket-max-${row.localKey}`} className="text-xs font-semibold text-gray-700">
                        {t('maxParticipants')}
                      </label>
                      <input
                        id={`bracket-max-${row.localKey}`}
                        type="number"
                        min="1"
                        value={row.maxParticipants}
                        onChange={(e) => handleUpdateBracket(row.localKey, { maxParticipants: e.target.value })}
                        placeholder={t('unlimitedPlaceholder')}
                        className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-[#0A2540]"
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
              <button
                type="button"
                onClick={handleAddBracket}
                disabled={brackets.length >= 10}
                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-[#0A2540] hover:bg-gray-50 disabled:opacity-40"
              >
                <Plus className="w-4 h-4" />
                {t('addBracket')}
              </button>

              <button
                type="submit"
                disabled={savingSchedule || brackets.length === 0}
                className="inline-flex items-center gap-1.5 rounded-lg bg-[#0A2540] px-4 py-2 text-sm font-semibold text-white hover:bg-[#FFB800] hover:text-[#0A2540] transition-colors disabled:opacity-50"
              >
                <Save className="w-4 h-4" />
                {savingSchedule ? t('saving') : t('saveSchedule')}
              </button>
            </div>
          </form>

          {/* Live traveler price preview */}
          <section className="rounded-xl border border-gray-200 bg-[#F7F9FB] p-5 space-y-3" aria-label={t('previewHeading')}>
            <h2 className="text-base font-bold text-[#0A2540]">{t('previewHeading')}</h2>
            <ul className="divide-y divide-gray-200 bg-white rounded-lg border border-gray-200">
              {previewRows.map((row) => (
                <li key={row.count} className="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm">
                  <div>
                    <p className="font-semibold text-[#0A2540]">
                      {t('previewTravelers', { count: row.count })}
                    </p>
                    <p className="text-xs text-gray-700">
                      {row.tierName ?? t('noMatchingTier')}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="font-mono font-semibold text-[#0A2540]">
                      {t('previewUnit', { price: row.unitFormatted })}
                    </p>
                    <p className="text-xs text-gray-700">
                      {t('previewTotal', { total: row.totalFormatted })}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        </div>
      ) : (
        <div id="panel-legacy-tiers" role="tabpanel" aria-labelledby={options.length > 0 ? 'tab-legacy-tiers' : undefined} className="space-y-5">
          {isEditing === null && (
            <div className="flex justify-end">
              <button
                type="button"
                onClick={startCreateLegacy}
                className="flex items-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors"
              >
                <Plus className="w-4 h-4" />
                {t('addSingleTier')}
              </button>
            </div>
          )}

          {isEditing !== null && (
            <form onSubmit={handleLegacySave} className="bg-gray-50 border border-gray-200 rounded-xl p-6 space-y-4">
              <h2 className="font-semibold text-lg text-[#0A2540]">
                {isEditing === -1 ? t('createTierHeading') : t('editTierHeading')}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="legacy-tier-name" className="text-sm font-semibold text-gray-700">{t('tierName')}</label>
                  <input
                    id="legacy-tier-name"
                    type="text"
                    required
                    value={legacyName}
                    onChange={(e) => setLegacyName(e.target.value)}
                    placeholder={t('tierNamePlaceholder')}
                    className="px-3.5 py-2 border border-gray-300 rounded-lg bg-white text-[#0A2540]"
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="legacy-tier-price" className="text-sm font-semibold text-gray-700">{t('priceEur')}</label>
                  <input
                    id="legacy-tier-price"
                    type="number"
                    required
                    min="0.01"
                    step="0.01"
                    value={legacyPrice}
                    onChange={(e) => setLegacyPrice(e.target.value)}
                    placeholder="0.00"
                    className="px-3.5 py-2 border border-gray-300 rounded-lg bg-white text-[#0A2540]"
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="legacy-tier-min" className="text-sm font-semibold text-gray-700">{t('minParticipants')}</label>
                  <input
                    id="legacy-tier-min"
                    type="number"
                    required
                    min="1"
                    value={legacyMin}
                    onChange={(e) => setLegacyMin(Math.max(1, parseInt(e.target.value, 10) || 1))}
                    className="px-3.5 py-2 border border-gray-300 rounded-lg bg-white text-[#0A2540]"
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="legacy-tier-max" className="text-sm font-semibold text-gray-700">{t('maxParticipants')}</label>
                  <input
                    id="legacy-tier-max"
                    type="number"
                    min="1"
                    value={legacyMax}
                    onChange={(e) => setLegacyMax(e.target.value === '' ? '' : parseInt(e.target.value, 10) || '')}
                    placeholder={t('unlimitedPlaceholder')}
                    className="px-3.5 py-2 border border-gray-300 rounded-lg bg-white text-[#0A2540]"
                  />
                </div>
              </div>

              <div className="flex items-center gap-3 pt-2 justify-end">
                <button
                  type="button"
                  onClick={() => setIsEditing(null)}
                  className="flex items-center gap-1 px-4 py-2 border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg transition-colors"
                >
                  <X className="w-4 h-4" />
                  {t('cancel')}
                </button>
                <button
                  type="submit"
                  disabled={savingLegacy}
                  className="flex items-center gap-1 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50"
                >
                  <Save className="w-4 h-4" />
                  {savingLegacy ? t('saving') : t('saveTier')}
                </button>
              </div>
            </form>
          )}

          {legacyTiers.length === 0 ? (
            <div className="text-center py-12 border border-dashed border-gray-300 rounded-xl text-gray-700">
              {t('emptyLegacy')}
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              {legacyTiers.map((tier) => (
                <div
                  key={tier.id}
                  className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-[#0A2540] transition-all flex flex-col justify-between"
                >
                  <div>
                    <div className="flex items-start justify-between gap-2">
                      <h3 className="font-bold text-lg text-[#0A2540]">{tier.name}</h3>
                      <span className="font-mono font-bold text-[#0A2540] text-lg">
                        {formatCurrency(
                          tier.price_amount ?? Math.round(parseFloat(tier.price) * 100),
                          tier.currency || 'EUR',
                          locale,
                        )}
                      </span>
                    </div>
                    <div className="mt-3 text-sm text-gray-700 space-y-1">
                      <p>
                        {t('minParticipants')}: <span className="font-semibold">{tier.min_participants}</span>
                      </p>
                      <p>
                        {t('maxParticipants')}:{' '}
                        <span className="font-semibold">{tier.max_participants ?? t('unlimited')}</span>
                      </p>
                    </div>
                  </div>

                  {confirmDeleteId === tier.id ? (
                    <div className="mt-5 border-t border-gray-200 pt-4 flex flex-wrap items-center justify-between gap-2">
                      <span className="text-xs font-medium text-red-800">{t('confirmDeletePrompt')}</span>
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => setConfirmDeleteId(null)}
                          className="px-3 py-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg"
                        >
                          {t('cancel')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleLegacyDelete(tier.id)}
                          className="px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold rounded-lg"
                        >
                          {t('confirmDelete')}
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 mt-5 border-t border-gray-200 pt-4 justify-end">
                      <button
                        type="button"
                        onClick={() => startEditLegacy(tier)}
                        className="flex items-center gap-1 px-3 py-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg transition-colors"
                      >
                        <Edit className="w-3.5 h-3.5" />
                        {t('edit')}
                      </button>
                      <button
                        type="button"
                        onClick={() => setConfirmDeleteId(tier.id)}
                        className="flex items-center gap-1 px-3 py-1.5 border border-red-200 hover:bg-red-50 text-red-700 text-xs font-semibold rounded-lg transition-colors"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                        {t('delete')}
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

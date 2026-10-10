'use client';

import { useState, useEffect, use, useCallback, useRef } from 'react';
import { ArrowLeft, Save, Calendar, Coins, Send, CheckCircle } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';
import { getApiBaseUrl, NotFoundError } from '@/lib/api/client';
import { getLatestTourDraft } from '@/lib/api/partner';
import { normalizeWizardState } from '@/lib/stores/tourWizard';
import { useTranslations } from 'next-intl';
import { localizeServerFieldError, pickFieldErrors } from '@/lib/validators/partner';
import { ImageUploader } from '@/components/partner/tours/ImageUploader';
import { ItineraryEditor } from '@/components/partner/tours/ItineraryEditor';
import { TourContentPreview } from '@/components/partner/tours/TourContentPreview';
import type { TourItineraryDay } from '@/lib/api/types';
import type { TourMedia } from '@/types/tour';

interface Translation {
  title: string;
  description: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  meeting_point: string;
  cancellation_policy: string;
  important_information: string[];
  itinerary: TourItineraryDay[];
}

const emptyTranslation = (): Translation => ({
  title: '', description: '', highlights: [], inclusions: [], exclusions: [],
  meeting_point: '', cancellation_policy: '', important_information: [], itinerary: [],
});

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

const SNAPSHOT_TEXT_FIELDS = ['title', 'description', 'meeting_point', 'cancellation_policy'] as const;
const SNAPSHOT_LIST_FIELDS = ['highlights', 'inclusions', 'exclusions', 'important_information'] as const;

interface SnapshotRestore {
  source: Partial<Translation>;
  /** Snapshot fields explicitly nulled (clearing applies on save). */
  nulled: Array<keyof Translation>;
  /** Restored gallery, or null when the snapshot omits media (retain current). */
  media: TourMedia[] | null;
}

/** Build a presence-aware restore patch from an opaque stored snapshot
 *  without mutating it: explicit nested `translations.en` keys win per
 *  field, shorthand fills only missing fields, omitted source/media stay
 *  untouched on the current form, and explicit null/[] clear per contract.
 *  Platform-owned readiness/hash fields are never read. Returns null when
 *  the payload is unusable, leaving the form intact. */
function snapshotRestorePatch(payload: unknown): SnapshotRestore | null {
  if (!isRecord(payload)) {
    return null;
  }
  const translations = payload.translations;
  const nestedEn = isRecord(translations) && isRecord(translations.en) ? translations.en : undefined;

  const readRaw = (field: string): { present: boolean; value: unknown } => {
    if (nestedEn !== undefined && field in nestedEn) {
      return { present: true, value: nestedEn[field] };
    }
    if (field in payload) {
      return { present: true, value: payload[field] };
    }
    return { present: false, value: undefined };
  };

  const source: Partial<Translation> = {};
  const nulled: Array<keyof Translation> = [];
  for (const field of SNAPSHOT_TEXT_FIELDS) {
    const { present, value } = readRaw(field);
    if (!present) {
      continue;
    }
    if (typeof value === 'string') {
      source[field] = value;
    } else if (value === null) {
      source[field] = '';
      nulled.push(field);
    }
  }
  for (const field of SNAPSHOT_LIST_FIELDS) {
    const { present, value } = readRaw(field);
    if (!present) {
      continue;
    }
    if (typeof value === 'string' || Array.isArray(value)) {
      const normalized = normalizeWizardState({ [field]: value });
      const list = normalized[field];
      if (Array.isArray(list)) {
        source[field] = list.filter((item): item is string => typeof item === 'string');
      }
    } else if (value === null) {
      source[field] = [];
      nulled.push(field);
    }
  }

  const { present: itineraryPresent, value: rawItinerary } = readRaw('itinerary');
  if (itineraryPresent) {
    if (Array.isArray(rawItinerary)) {
      source.itinerary = normalizeWizardState({ itinerary: rawItinerary }).itinerary ?? [];
    } else if (rawItinerary === null) {
      source.itinerary = [];
      nulled.push('itinerary');
    }
  }

  let media: TourMedia[] | null = null;
  if (Array.isArray(payload.media)) {
    media = payload.media
      .filter((entry): entry is Record<string, unknown> => isRecord(entry) && typeof entry.url === 'string')
      .map((entry, index) => ({
        id: `restored-${index}`,
        url: entry.url as string,
        is_cover: entry.is_cover === true,
        sort_order: index,
      }));
  }

  return { source, nulled, media };
}

/** Strip the canonical `translations.en.` prefix so nested server 422 paths
 *  map onto the actual authoring field. */
function toFormErrorPath(serverPath: string): string {
  return serverPath.startsWith('translations.en.') ? serverPath.slice('translations.en.'.length) : serverPath;
}

interface Tour {
  id: number;
  category_id: number;
  slug: string;
  location: string;
  duration_value?: number;
  duration_unit?: string;
  duration_minutes?: number;
  group_size_min: number;
  group_size_max: number;
  status: string;
  cover_image_url: string | null;
  media?: Array<Pick<TourMedia, 'id' | 'url' | 'sort_order'> & Partial<Pick<TourMedia, 'thumbnail_url' | 'alt_text'>>>;
  guide_languages?: string[];
  translation_statuses?: { es: 'pending' | 'ready' | 'stale' | 'failed'; it: 'pending' | 'ready' | 'stale' | 'failed' };
  translations: Array<{
    locale: string;
    title: string;
    description: string;
    highlights: string[] | null;
    inclusions: string[] | null;
    exclusions: string[] | null;
    meeting_point: string | null;
    cancellation_policy: string | null;
    important_information?: string[] | null;
    itinerary?: TourItineraryDay[] | null;
  }>;
}

export default function PartnerTourEditPage({ params }: { params: Promise<{ id: string; locale: string }> }) {
  const resolvedParams = use(params);
  const id = resolvedParams.id;
  const locale = resolvedParams.locale;
  const formT = useTranslations('partner.tours.form');
  const tourT = useTranslations('partner.tours');
  // Page-locale feedback strings, resolved per render as stable values so
  // async callbacks stay referentially stable (the translation function
  // itself is not a stable callback identity).
  const loadFailedMsg = formT('loadFailed');
  const saveFailedMsg = formT('saveFailed');
  const saveSucceededMsg = formT('saveSucceeded');
  const submitFailedMsg = formT('submitFailed');
  const submitSucceededMsg = formT('submitSucceeded');

  const [tour, setTour] = useState<Tour | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [restoreNotice, setRestoreNotice] = useState<string | null>(null);
  const [isRestoring, setIsRestoring] = useState(false);
  const saveErrorRef = useRef<HTMLDivElement>(null);
  // Owned EN fields that are canonically NULL (vs []): untouched nulls are
  // sent back as null on save so an unrelated edit never converts stored
  // nulls into empty values. Any user edit removes the field from the set;
  // explicit snapshot nulls add to it (clearing applies).
  const [nullSourceFields, setNullSourceFields] = useState<Set<keyof Translation>>(new Set());
  // Focus the announced save-error box only when a NEW error set appears,
  // after render — never on keystrokes while errors stay visible.
  const hasSaveErrors = error !== null || Object.keys(fieldErrors).length > 0;
  const saveErrorSignature = hasSaveErrors
    ? `${error ?? ''}|${Object.entries(fieldErrors).map(([path, message]) => `${path}:${message}`).join(';')}`
    : '';
  const prevSaveErrorSignatureRef = useRef('');
  useEffect(() => {
    if (saveErrorSignature && saveErrorSignature !== prevSaveErrorSignatureRef.current) {
      prevSaveErrorSignatureRef.current = saveErrorSignature;
      saveErrorRef.current?.focus();
    }
    if (!saveErrorSignature) {
      prevSaveErrorSignatureRef.current = '';
    }
  }, [saveErrorSignature]);

  // General fields
  const [categoryId, setCategoryId] = useState(1);
  const [location, setLocation] = useState('');
  const [coverImageUrl, setCoverImageUrl] = useState('');
  const [media, setMedia] = useState<TourMedia[]>([]);
  const [mediaDirty, setMediaDirty] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [guideLanguagesInput, setGuideLanguagesInput] = useState('');
  const [groupSizeMin, setGroupSizeMin] = useState(1);
  const [groupSizeMax, setGroupSizeMax] = useState(10);
  const [durationValue, setDurationValue] = useState(2);
  const [durationUnit, setDurationUnit] = useState('hour');

  // Partners edit the English source only; ES/IT are generated server-side.
  const activeLangTab = 'en' as const;
  const sourceErrorProps = (field: keyof Translation, hint?: string) => {
    const invalid = pickFieldErrors(fieldErrors, field).length > 0;
    return {
      'aria-invalid': invalid ? true as const : undefined,
      'aria-describedby': [hint, invalid ? `edit-${field}-errors` : undefined].filter(Boolean).join(' ') || undefined,
    };
  };
  const renderSourceErrors = (field: keyof Translation) => {
    const messages = pickFieldErrors(fieldErrors, field);
    return messages.length > 0 ? (
      <div id={`edit-${field}-errors`} className="text-xs text-red-600" role="alert">
        {messages.map((message, index) => <p key={index}>{message}</p>)}
      </div>
    ) : null;
  };
  const [translationData, setTranslationData] = useState<Record<'en' | 'es' | 'it', Translation>>({
    en: emptyTranslation(), es: emptyTranslation(), it: emptyTranslation(),
  });

  const fetchTour = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${id}`, {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error(loadFailedMsg);
      const json = await res.json();
      if (!isRecord(json.data) || typeof json.data.id !== 'number') {
        throw new Error(loadFailedMsg);
      }
      const t: Tour = json.data;
      setTour(t);

      // Populate states
      setCategoryId(t.category_id);
      setLocation(t.location);
      setCoverImageUrl(t.cover_image_url || '');
      setMedia((t.media ?? []).slice().sort((a, b) => a.sort_order - b.sort_order).map((item) => ({
        ...item, is_cover: item.url === t.cover_image_url,
      })));
      setMediaDirty(false);
      setGuideLanguagesInput((t.guide_languages ?? []).join(', '));
      setGroupSizeMin(t.group_size_min);
      setGroupSizeMax(t.group_size_max);
      
      const val = t.duration_minutes ? (t.duration_minutes >= 1440 ? t.duration_minutes / 1440 : t.duration_minutes / 60) : 2;
      const unit = t.duration_minutes && t.duration_minutes >= 1440 ? 'day' : 'hour';
      setDurationValue(val);
      setDurationUnit(unit);

      // Populate translations
      const newTrans: Record<'en' | 'es' | 'it', Translation> = {
        en: emptyTranslation(), es: emptyTranslation(), it: emptyTranslation(),
      };

      t.translations.forEach((tr) => {
        const loc = tr.locale as 'en' | 'es' | 'it';
        if (newTrans[loc]) {
          newTrans[loc] = {
            title: tr.title || '',
            description: tr.description || '',
            highlights: tr.highlights || [],
            inclusions: tr.inclusions || [],
            exclusions: tr.exclusions || [],
            meeting_point: tr.meeting_point || '',
            cancellation_policy: tr.cancellation_policy || '',
            important_information: tr.important_information || [],
            itinerary: tr.itinerary ?? [],
          };
        }
      });
      setTranslationData(newTrans);

      // Record owned EN nulls (array rows serialize null vs [] distinctly).
      const enRow = t.translations.find((tr) => tr.locale === 'en');
      if (enRow) {
        const nulls = new Set<keyof Translation>();
        (['description', 'highlights', 'inclusions', 'exclusions', 'meeting_point', 'cancellation_policy', 'important_information', 'itinerary'] as const)
          .forEach((field) => {
            if (enRow[field] === null || enRow[field] === undefined) {
              nulls.add(field);
            }
          });
        setNullSourceFields(nulls);
      } else {
        setNullSourceFields(new Set());
      }
    } catch {
      // Load feedback stays page-localized (including cross-owner 404 denial);
      // raw network/server English never reaches the UI.
      setError(loadFailedMsg);
    } finally {
      setIsLoading(false);
    }
  }, [id, loadFailedMsg]);

  useEffect(() => {
    fetchTour();
  }, [fetchTour]);

  const updateTranslationField = <K extends keyof Translation>(
    lang: 'en' | 'es' | 'it',
    field: K,
    value: Translation[K]
  ) => {
    setTranslationData((prev) => ({
      ...prev,
      [lang]: {
        ...prev[lang],
        [field]: value,
      },
    }));
    // A user-supplied value replaces the stored null, even when cleared.
    setNullSourceFields((prev) => {
      if (!prev.has(field)) {
        return prev;
      }
      const next = new Set(prev);
      next.delete(field);
      return next;
    });
  };

  const handleRestoreDraft = async () => {
    setError(null);
    setRestoreNotice(null);
    setIsRestoring(true);
    try {
      const draft = await getLatestTourDraft(id);
      const patch = snapshotRestorePatch(draft.payload);
      if (patch === null) {
        setRestoreNotice(formT('restoreFailed'));
        return;
      }
      // Merge onto the owned current source: omitted snapshot fields keep
      // their values, explicit null/[] clear, media omission retains media.
      setTranslationData((prev) => ({ ...prev, en: { ...prev.en, ...patch.source } }));
      setNullSourceFields((prev) => {
        const next = new Set(prev);
        (Object.keys(patch.source) as Array<keyof Translation>).forEach((field) => {
          next.delete(field);
        });
        patch.nulled.forEach((field) => {
          next.add(field);
        });
        return next;
      });
      if (patch.media !== null) {
        setMedia(patch.media);
        setMediaDirty(true);
      }
      setRestoreNotice(formT('draftRestored'));
    } catch (err) {
      // Only a true 404 means "no draft"; denial, network or server failure
      // keeps the form and reports a retryable localized error.
      setRestoreNotice(err instanceof NotFoundError ? formT('noDraft') : formT('restoreFailed'));
    } finally {
      setIsRestoring(false);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccessMsg(null);
    setFieldErrors({});
    try {
      const token = getAuthToken();
      // Untouched canonical nulls round-trip as null (never as []/'');
      // edited fields — including explicit user clears — send as authored.
      const enPayload: Record<string, unknown> = { ...translationData.en };
      nullSourceFields.forEach((field) => {
        enPayload[field] = null;
      });
      const translationsPayload = { en: enPayload };

      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${id}`, {
        method: 'PUT',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          category_id: categoryId,
          location,
          cover_image_url: coverImageUrl || null,
          guide_languages: guideLanguagesInput.split(',').map((code) => code.trim().toLowerCase()).filter(Boolean),
          group_size_min: groupSizeMin,
          group_size_max: groupSizeMax,
          duration_value: durationValue,
          duration_unit: durationUnit,
          translations: translationsPayload,
          ...(mediaDirty ? { media: media.map(({ url, is_cover }) => ({ url, is_cover })) } : {}),
        }),
      });

      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        if (res.status === 422 && isRecord(json.errors)) {
          const mapped: Record<string, string> = {};
          for (const [path, messages] of Object.entries(json.errors)) {
            const formPath = toFormErrorPath(path);
            if (!(formPath in mapped) && Array.isArray(messages) && messages.length > 0) {
              const first: unknown = messages[0];
              if (typeof first === 'string') {
                mapped[formPath] = localizeServerFieldError(
                  formPath, first, (key) => tourT(key.replace(/^partner\.tours\./, ''))
                );
              }
            }
          }
          setFieldErrors(mapped);
        }
        throw new Error(saveFailedMsg);
      }

      setSuccessMsg(saveSucceededMsg);
      fetchTour();
    } catch {
      // Field errors render localized inline + in the focused summary;
      // generic save failures use page-locale feedback, never raw English.
      setError(saveFailedMsg);
    }
  };

  const handleSubmitReview = async () => {
    setError(null);
    setSuccessMsg(null);
    try {
      const token = getAuthToken();
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${id}/submit`, {
        method: 'POST',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });

      if (!res.ok) {
        await res.json().catch(() => ({}));
        throw new Error(submitFailedMsg);
      }

      setSuccessMsg(submitSucceededMsg);
      fetchTour();
    } catch {
      setError(submitFailedMsg);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-500">{formT('loadingEditor')}</div>;
  }

  if (error && !tour) {
    return <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{error}</div>;
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6 p-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between border-b border-gray-100 pb-4 gap-4">
        <div className="flex items-center gap-3">
          <Link href={`/${locale}/partner`} className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-[#0A2540]">{tourT('editTour')}</h1>
              <span className={`text-xs px-2.5 py-0.5 rounded-full font-bold uppercase ${
                tour?.status === 'published' ? 'bg-emerald-100 text-emerald-800' :
                tour?.status === 'pending_review' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'
              }`}>
                {tour?.status}
              </span>
            </div>
            <p className="text-sm text-gray-500">ID: {id} — {formT('sourceLanguageNotice')}</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Link
            href={`/${locale}/partner/tours/${id}/pricing`}
            className="flex items-center gap-1 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-[#0A2540] text-sm font-semibold rounded-lg transition-colors"
          >
            <Coins className="w-4 h-4 text-gray-500" />
            Manage Pricing
          </Link>
          <Link
            href={`/${locale}/partner/tours/${id}/availability`}
            className="flex items-center gap-1 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-[#0A2540] text-sm font-semibold rounded-lg transition-colors"
          >
            <Calendar className="w-4 h-4 text-gray-500" />
            Manage Availability
          </Link>
          {tour?.status === 'draft' && (
            <button
              onClick={handleSubmitReview}
              className="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <Send className="w-4 h-4" />
              {formT('submitForReview')}
            </button>
          )}
        </div>
      </div>

      {successMsg && (
        <div role="status" aria-live="polite" className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center gap-2">
          <CheckCircle className="w-4 h-4" />
          {successMsg}
        </div>
      )}

      {hasSaveErrors && (
        <div ref={saveErrorRef} tabIndex={-1} className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" role="alert" aria-live="assertive">
          <h3 className="font-semibold">{formT('errorSummary')}</h3>
          <p>{Object.keys(fieldErrors).length > 0 ? formT('saveBlockedErrors') : error}</p>
        </div>
      )}

      {/* noValidate lets empty/fractional source reach the authoritative
      server validation so custom localized field/summary feedback runs;
      required/min/step metadata stays for assistive technology. */}
      <form onSubmit={handleSave} noValidate className="grid gap-6 lg:grid-cols-[1fr_360px]">
        {/* Left: General Settings and Language Editor */}
        <div className="space-y-6 bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
          <h2 className="font-bold text-lg text-[#0A2540] border-b border-gray-50 pb-2 mb-4">Tour Content Editing</h2>

          <p className="text-sm text-gray-600">{formT('sourceLanguageNotice')}</p>
          {tour?.translation_statuses && (
            <p className="text-sm text-gray-600" aria-live="polite">
              ES: {formT(`translationStatus.${tour.translation_statuses.es}`)} · IT: {formT(`translationStatus.${tour.translation_statuses.it}`)}
            </p>
          )}
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={handleRestoreDraft}
              disabled={isRestoring}
              className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-bookly-navy disabled:opacity-50"
            >
              {isRestoring ? formT('restoringDraft') : formT('restoreDraft')}
            </button>
            {restoreNotice && (
              <p className="text-sm text-gray-600" role="status" aria-live="polite">{restoreNotice}</p>
            )}
          </div>

          {/* Current language tab fields */}
          <div className="space-y-4">
            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-source-title" className="text-sm font-semibold text-gray-700">{formT('title')} ({activeLangTab.toUpperCase()})</label>
              <input
                id="edit-source-title"
                type="text"
                required={activeLangTab === 'en'}
                value={translationData[activeLangTab].title}
                onChange={(e) => updateTranslationField(activeLangTab, 'title', e.target.value)}
                placeholder={formT('titlePlaceholder')}
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
                  {...sourceErrorProps('title')}
              />
                {renderSourceErrors('title')}
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-source-description" className="text-sm font-semibold text-gray-700">{formT('description')} ({activeLangTab.toUpperCase()})</label>
              <textarea
                id="edit-source-description"
                rows={5}
                required={activeLangTab === 'en'}
                value={translationData[activeLangTab].description}
                onChange={(e) => updateTranslationField(activeLangTab, 'description', e.target.value)}
                placeholder={formT('descriptionPlaceholder')}
                className="px-3.5 py-2 border rounded-lg bg-white outline-none focus:border-blue-500"
                  {...sourceErrorProps('description')}
              />
                {renderSourceErrors('description')}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-source-meeting-point" className="text-sm font-semibold text-gray-700">{formT('meetingPoint')} ({activeLangTab.toUpperCase()})</label>
                <input
                  id="edit-source-meeting-point"
                  type="text"
                  value={translationData[activeLangTab].meeting_point}
                  onChange={(e) => updateTranslationField(activeLangTab, 'meeting_point', e.target.value)}
                  placeholder={formT('meetingPointPlaceholder')}
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('meeting_point')}
                />
                {renderSourceErrors('meeting_point')}
              </div>

              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-source-cancellation-policy" className="text-sm font-semibold text-gray-700">{formT('cancellationPolicy')} ({activeLangTab.toUpperCase()})</label>
                <input
                  id="edit-source-cancellation-policy"
                  type="text"
                  value={translationData[activeLangTab].cancellation_policy}
                  onChange={(e) => updateTranslationField(activeLangTab, 'cancellation_policy', e.target.value)}
                  placeholder={formT('cancellationPolicyPlaceholder')}
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('cancellation_policy')}
                />
                {renderSourceErrors('cancellation_policy')}
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-source-highlights" className="text-sm font-semibold text-gray-700">{formT('highlights')}</label>
                <input
                  id="edit-source-highlights"
                  type="text"
                  value={translationData[activeLangTab].highlights.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'highlights', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder={formT('highlightsPlaceholder')}
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('highlights', 'edit-source-lists-hint')}
                />
                {renderSourceErrors('highlights')}
              </div>

              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-source-inclusions" className="text-sm font-semibold text-gray-700">{formT('inclusions')}</label>
                <input
                  id="edit-source-inclusions"
                  type="text"
                  value={translationData[activeLangTab].inclusions.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'inclusions', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder={formT('inclusionsPlaceholder')}
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('inclusions', 'edit-source-lists-hint')}
                />
                {renderSourceErrors('inclusions')}
              </div>

              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-source-exclusions" className="text-sm font-semibold text-gray-700">{formT('exclusions')}</label>
                <input
                  id="edit-source-exclusions"
                  type="text"
                  value={translationData[activeLangTab].exclusions.join(', ')}
                  onChange={(e) => updateTranslationField(activeLangTab, 'exclusions', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                  placeholder={formT('exclusionsPlaceholder')}
                  className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('exclusions', 'edit-source-lists-hint')}
                />
                {renderSourceErrors('exclusions')}
              </div>
            </div>
            <p id="edit-source-lists-hint" className="text-xs text-gray-500">{formT('commaSeparatedHint')}</p>
            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-source-important-information" className="text-sm font-semibold text-gray-700">{formT('importantInformation')}</label>
              <input
                id="edit-source-important-information"
                type="text"
                value={translationData[activeLangTab].important_information.join(', ')}
                onChange={(e) => updateTranslationField(activeLangTab, 'important_information', e.target.value.split(',').map(s => s.trim()).filter(Boolean))}
                placeholder={formT('importantInformationPlaceholder')}
                className="px-3.5 py-2 border rounded-lg bg-white outline-none"
                  {...sourceErrorProps('important_information', 'edit-source-lists-hint')}
              />
                {renderSourceErrors('important_information')}
            </div>
            <ItineraryEditor value={translationData.en.itinerary} onChange={(days) => updateTranslationField('en', 'itinerary', days)} errors={fieldErrors} />
          </div>
        </div>

        {/* Right: Technical Metadata Settings */}
        <aside className="space-y-6">
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <h3 className="font-bold text-sm text-[#0A2540] border-b border-gray-100 pb-2">Technical Parameters</h3>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-gray-600">Location Name</label>
              <input
                type="text"
                required
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder="e.g. Rome, Italy"
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-gray-600">Cover Image URL</label>
              <input
                type="text"
                value={coverImageUrl}
                onChange={(e) => setCoverImageUrl(e.target.value)}
                placeholder="https://example.com/image.jpg"
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-guide-languages" className="text-xs font-semibold text-gray-600">{formT('guideLanguageCodes')}</label>
              <input
                id="edit-guide-languages"
                type="text"
                value={guideLanguagesInput}
                onChange={(e) => setGuideLanguagesInput(e.target.value)}
                placeholder="de, en, es"
                aria-describedby="edit-guide-languages-hint"
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
              <p id="edit-guide-languages-hint" className="text-xs text-gray-500">{formT('guideLanguageCodesHint')}</p>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Duration</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={durationValue}
                  onChange={(e) => {
                    // Never floor fractional input into a valid integer:
                    // the raw number travels to validation, which rejects it.
                    const next = Number(e.target.value);
                    setDurationValue(Number.isNaN(next) ? 0 : next);
                  }}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Unit</label>
                <select
                  value={durationUnit}
                  onChange={(e) => setDurationUnit(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  <option value="hour">Hours</option>
                  <option value="day">Days</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Min Guests</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={groupSizeMin}
                  onChange={(e) => setGroupSizeMin(parseInt(e.target.value) || 1)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-gray-600">Max Guests</label>
                <input
                  type="number"
                  required
                  min={1}
                  value={groupSizeMax}
                  onChange={(e) => setGroupSizeMax(parseInt(e.target.value) || 10)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <Save className="w-4 h-4" />
              {formT('saveDetails')}
            </button>
          </div>
          <div className="rounded-xl border border-border bg-surface p-5">
            <ImageUploader media={media} onChange={(next) => { setMedia(next); setMediaDirty(true); setCoverImageUrl(next.find((item) => item.is_cover)?.url ?? ''); }} />
          </div>
        </aside>
      </form>
      <button type="button" onClick={() => setPreviewOpen((open) => !open)} className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-bookly-navy">
        {formT(previewOpen ? 'hidePreview' : 'previewTour')}
      </button>
      {previewOpen && (
        <TourContentPreview
          title={translationData.en.title}
          description={translationData.en.description}
          itinerary={translationData.en.itinerary}
          media={media}
          highlights={translationData.en.highlights}
          inclusions={translationData.en.inclusions}
          exclusions={translationData.en.exclusions}
          important_information={translationData.en.important_information}
          meeting_point={translationData.en.meeting_point || null}
          cancellation_policy={translationData.en.cancellation_policy || null}
          translationStatuses={tour?.translation_statuses ?? null}
        />
      )}
    </div>
  );
}

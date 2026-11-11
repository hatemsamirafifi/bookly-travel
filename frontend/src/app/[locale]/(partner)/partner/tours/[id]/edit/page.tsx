'use client';

import { useState, useEffect, use, useCallback, useRef } from 'react';
import { ArrowLeft, Save, Calendar, Coins, Send, CheckCircle } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';
import { getApiBaseUrl, NotFoundError } from '@/lib/api/client';
import { getLatestTourDraft } from '@/lib/api/partner';
import { normalizeWizardState } from '@/lib/stores/tourWizard';
import { useTranslations } from 'next-intl';
import { localizeServerFieldError, pickFieldErrors, toFormErrorPath, getSubmittedOptionRows } from '@/lib/validators/partner';
import { ImageUploader } from '@/components/partner/tours/ImageUploader';
import { ItineraryEditor } from '@/components/partner/tours/ItineraryEditor';
import { TourContentPreview } from '@/components/partner/tours/TourContentPreview';
import { BookingOptionsForm } from '@/components/partner/tours/BookingOptionsForm';
import { PartnerRichTextEditor } from '@/components/partner/tours/PartnerRichTextEditor';
import { toMinorUnits } from '@/lib/utils';
import type { TourItineraryDay, ContentSourceLocale, PartnerBookingOptionInput } from '@/lib/api/types';
import type { OwnedTourOption } from '@/types/partner';
import type { TourMedia, DifficultyLevel, BookingOptionFormInput, SourceLocale } from '@/types/tour';

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
  /** Actual source language the snapshot's authored fields were read from. */
  sourceLocale: ContentSourceLocale;
  source: Partial<Translation>;
  /** Snapshot fields explicitly nulled (clearing applies on save). */
  nulled: Array<keyof Translation>;
  /** Restored gallery, or null when the snapshot omits media (retain current). */
  media: TourMedia[] | null;
  /** Restored difficulty code, or null for explicit-null/invalid content. */
  difficulty: DifficultyLevel | null;
  /** True when the snapshot carries difficulty_level (retain current when absent). */
  difficultyPresent: boolean;
  /** Restored booking options when present; null retains current rows. */
  options: BookingOptionFormInput[] | null;
}

/** Guard a snapshot option row into an editable row (strings preserved).
 *  Snapshot prices travel as integer cents in the API contract; the edit
 *  UI shows currency major units, so numeric cents convert here — never
 *  restored raw into the major-unit input (that would multiply by 100 on
 *  the next save). */
function snapshotOptionRows(payload: Record<string, unknown>): BookingOptionFormInput[] | null {
  if (!Array.isArray(payload.options)) {
    return null;
  }
  return payload.options
    .filter((entry): entry is Record<string, unknown> => isRecord(entry) && typeof entry.title === 'string')
    .map((entry, index) => {
      const rawPrice = entry.price_amount;
      // Numeric (or numeric-string) snapshot cents → major units for the UI.
      const priceCents =
        typeof rawPrice === 'number' && Number.isSafeInteger(rawPrice)
          ? rawPrice
          : typeof rawPrice === 'string' && /^\d+$/.test(rawPrice)
            ? Number(rawPrice)
            : null;
      const price = priceCents === null ? '' : String(priceCents / 100);
      const rawCapacity = entry.group_size_max;
      return {
        id: `restored-option-${index}`,
        ...(typeof entry.id === 'number' && Number.isSafeInteger(entry.id) ? { server_id: entry.id as number } : {}),
        title: entry.title as string,
        pickup_instructions: typeof entry.pickup_instructions === 'string' ? entry.pickup_instructions : '',
        guide_languages: Array.isArray(entry.guide_languages)
          ? entry.guide_languages.filter((code): code is string => typeof code === 'string')
          : [],
        price,
        capacity:
          typeof rawCapacity === 'number' && Number.isSafeInteger(rawCapacity) ? String(rawCapacity) : '',
        start_time: typeof entry.start_time === 'string' ? entry.start_time : '',
        weekdays: Array.isArray(entry.weekdays)
          ? entry.weekdays.filter((day): day is number => typeof day === 'number' && day >= 0 && day <= 6)
          : [],
      };
    });
}

/** Build a presence-aware restore patch from an opaque stored snapshot
 *  without mutating it: explicit nested `translations[<source>]` keys win
 *  per field (the source is the snapshot's own `source_locale`, defaulting
 *  to the legacy English source), shorthand fills only missing fields,
 *  omitted source/media/options stay untouched on the current form, and
 *  explicit null/[] clear per contract. Platform-owned readiness/hash
 *  fields are never read. Returns null when the payload is unusable,
 *  leaving the form intact. */
function snapshotRestorePatch(payload: unknown): SnapshotRestore | null {
  if (!isRecord(payload)) {
    return null;
  }
  const translations = payload.translations;
  const snapshotSource: ContentSourceLocale =
    payload.source_locale === 'es' || payload.source_locale === 'it' ? payload.source_locale : 'en';
  const nestedSource =
    isRecord(translations) && isRecord(translations[snapshotSource]) ? translations[snapshotSource] : undefined;

  const readRaw = (field: string): { present: boolean; value: unknown } => {
    if (nestedSource !== undefined && field in nestedSource) {
      return { present: true, value: nestedSource[field] };
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
      .map((entry, index) => {
        const restored: TourMedia = {
          id: `restored-${index}`,
          url: entry.url as string,
          is_cover: entry.is_cover === true,
          sort_order: index,
        };
        // alt_text presence is contractual: omitted stays absent (keep
        // current per-item text), explicit null clears, strings restore.
        if ('alt_text' in entry) {
          restored.alt_text = typeof entry.alt_text === 'string' ? entry.alt_text : null;
        }
        return restored;
      });
  }

  // Difficulty restores like the wizard: a supported code selects it,
  // explicit null/invalid reads truthfully as unknown, and omission retains
  // the current form state (never inferred as easy here either).
  const rawDifficulty: unknown = payload.difficulty_level;
  const difficultyPresent = 'difficulty_level' in payload;
  const difficulty: DifficultyLevel | null =
    rawDifficulty === 'easy' || rawDifficulty === 'moderate' || rawDifficulty === 'challenging'
      ? rawDifficulty
      : null;

  const options = snapshotOptionRows(payload);

  return { sourceLocale: snapshotSource, source, nulled, media, difficulty, difficultyPresent, options };
}

/** Form rows → API-contract options array: placeholders (fully empty rows)
 *  are excluded, persistent ids travel for edits, the UI's major-unit price
 *  becomes integer cents, and blank optional fields null out. */
function toOptionsPayload(rows: BookingOptionFormInput[]): PartnerBookingOptionInput[] {
  return getSubmittedOptionRows(rows).map((row) => ({
    ...(row.server_id !== undefined ? { id: row.server_id } : {}),
    title: row.title.trim(),
    pickup_instructions: row.pickup_instructions.trim() === '' ? null : row.pickup_instructions.trim(),
    guide_languages: [...new Set(row.guide_languages.map((code) => code.trim().toLowerCase()).filter(Boolean))],
    price_amount: toMinorUnits(row.price),
    group_size_max: row.capacity.trim() === '' ? null : Number(row.capacity),
    start_time: row.start_time.trim() === '' ? null : row.start_time.trim(),
    weekdays: row.weekdays.length > 0 ? [...new Set(row.weekdays)].sort((a, b) => a - b) : null,
  }));
}

interface Tour {
  id: number;
  category_id: number;
  slug: string;
  location: string;
  duration_value?: number;
  duration_unit?: string;
  duration_minutes?: number;
  /** Nullable: older tours read truthfully as null/missing, never inferred. */
  difficulty_level?: 'easy' | 'moderate' | 'challenging' | null;
  /** Actual language of the authored source content (legacy: 'en'). */
  source_locale?: 'en' | 'es' | 'it' | null;
  group_size_min: number;
  group_size_max: number;
  status: string;
  cover_image_url: string | null;
  media?: Array<Pick<TourMedia, 'id' | 'url' | 'sort_order'> & Partial<Pick<TourMedia, 'thumbnail_url' | 'alt_text'>>>;
  guide_languages?: string[];
  /** Authored active bookable options; absent means none are stored. */
  options?: OwnedTourOption[];
  translation_statuses?: { en?: 'pending' | 'ready' | 'stale' | 'failed'; es?: 'pending' | 'ready' | 'stale' | 'failed'; it?: 'pending' | 'ready' | 'stale' | 'failed' };
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
  // Original writing language of this tour's source content; immutable per
  // tour (legacy tours read as the English source), shown read-only while
  // the other two languages stay generated server-side.
  const [sourceLocale, setSourceLocale] = useState<SourceLocale>('en');
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
  const [coverImageDirty, setCoverImageDirty] = useState(false);
  const [media, setMedia] = useState<TourMedia[]>([]);
  const [mediaDirty, setMediaDirty] = useState(false);
  // FR021/US4: a pending over-long English alt draft stays local inside the
  // uploader and must block save until fixed or discarded.
  const [mediaAltValid, setMediaAltValid] = useState(true);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [guideLanguagesInput, setGuideLanguagesInput] = useState('');
  const [groupSizeMin, setGroupSizeMin] = useState(1);
  const [groupSizeMax, setGroupSizeMax] = useState(10);
  // Authored bookable options: rows keep their persistent server ids while
  // they exist, and a touched set is submitted whole (deleting all rows of a
  // previously optioned tour retires them); untouched options stay omitted.
  const [options, setOptions] = useState<BookingOptionFormInput[]>([]);
  const [optionsDirty, setOptionsDirty] = useState(false);
  const [durationValue, setDurationValue] = useState(2);
  const [durationUnit, setDurationUnit] = useState('hour');
  // Nullable difficulty: older null/missing reads truthfully as blank and is
  // omitted on save (the backend rejects supplied null); only an actually
  // chosen supported code travels, tracked by difficultyDirty.
  const [difficultyLevel, setDifficultyLevel] = useState<DifficultyLevel | null>(null);
  const [difficultyDirty, setDifficultyDirty] = useState(false);

  // Partners edit the tour's own source only; the other languages are
  // generated server-side. The tab is the tour's authoritative (immutable)
  // source locale — legacy tours read as the English source.
  const activeLangTab = sourceLocale;
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

      // Actual source language of the authored content: legacy tours read
      // as the English source.
      const actualSource: SourceLocale = t.source_locale === 'es' || t.source_locale === 'it' ? t.source_locale : 'en';
      setSourceLocale(actualSource);

      // Populate states
      setCategoryId(t.category_id);
      setLocation(t.location);
      setCoverImageUrl(t.cover_image_url || '');
      setCoverImageDirty(false);
      setMedia((t.media ?? []).slice().sort((a, b) => a.sort_order - b.sort_order).map((item) => ({
        ...item, is_cover: item.url === t.cover_image_url,
      })));
      setMediaDirty(false);
      setGuideLanguagesInput((t.guide_languages ?? []).join(', '));
      setGroupSizeMin(t.group_size_min);
      setGroupSizeMax(t.group_size_max);

      // Authored options: persisted ids are retained for edits; prices are
      // stored as integer cents and are shown in currency major units.
      setOptions((t.options ?? []).map((option, index) => ({
        id: `stored-option-${option.id ?? index}`,
        ...(option.id !== undefined ? { server_id: option.id } : {}),
        title: option.title ?? '',
        pickup_instructions: option.pickup_instructions ?? '',
        guide_languages: option.guide_languages ?? [],
        price: (Number(option.price_amount ?? 0) / 100).toString(),
        capacity: option.group_size_max === null || option.group_size_max === undefined ? '' : String(option.group_size_max),
        start_time: option.start_time ?? '',
        weekdays: option.weekdays ?? [],
      })));
      setOptionsDirty(false);
      
      const val = t.duration_minutes ? (t.duration_minutes >= 1440 ? t.duration_minutes / 1440 : t.duration_minutes / 60) : 2;
      const unit = t.duration_minutes && t.duration_minutes >= 1440 ? 'day' : 'hour';
      setDurationValue(val);
      setDurationUnit(unit);
      // Older null/missing difficulty stays unknown (blank select), never
      // inferred as easy; only supported codes restore as selected.
      setDifficultyLevel(
        t.difficulty_level === 'easy' || t.difficulty_level === 'moderate' || t.difficulty_level === 'challenging'
          ? t.difficulty_level
          : null
      );
      setDifficultyDirty(false);

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

      // Record owned source nulls (array rows serialize null vs [] distinctly)
      // for the actual source language row of this tour.
      const sourceRow = t.translations.find((tr) => tr.locale === actualSource);
      if (sourceRow) {
        const nulls = new Set<keyof Translation>();
        (['description', 'highlights', 'inclusions', 'exclusions', 'meeting_point', 'cancellation_policy', 'important_information', 'itinerary'] as const)
          .forEach((field) => {
            if (sourceRow[field] === null || sourceRow[field] === undefined) {
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
      // The authored fields restore into the snapshot's OWN source language
      // (declared by the snapshot or legacy English) — never into another.
      setTranslationData((prev) => ({ ...prev, [patch.sourceLocale]: { ...prev[patch.sourceLocale], ...patch.source } }));
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
      if (patch.difficultyPresent) {
        setDifficultyLevel(patch.difficulty);
        // A restored supported code is an actual choice and travels on save;
        // restored null/missing stays omitted (backend rejects supplied null).
        setDifficultyDirty(patch.difficulty !== null);
      }
      if (patch.options !== null) {
        setOptions(patch.options);
        setOptionsDirty(true);
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
    // Never partially save while a bad alt draft is pending: it was never
    // emitted into media and would be silently discarded.
    if (!mediaAltValid) {
      setSuccessMsg(null);
      setFieldErrors({});
      setError(formT('saveBlockedErrors'));
      return;
    }
    setError(null);
    setSuccessMsg(null);
    setFieldErrors({});
    try {
      const token = getAuthToken();
      // Untouched canonical nulls round-trip as null (never as []/'');
      // edited fields — including explicit user clears — send as authored.
      // The authored source is keyed by the tour's own (immutable) source
      // language; source_locale itself never travels on updates.
      const sourcePayload: Record<string, unknown> = { ...translationData[sourceLocale] };
      nullSourceFields.forEach((field) => {
        sourcePayload[field] = null;
      });
      const translationsPayload = { [sourceLocale]: sourcePayload };

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
          ...(coverImageDirty ? { cover_image_url: coverImageUrl || null } : {}),
          guide_languages: [...new Set(guideLanguagesInput.split(',').map((code) => code.trim().toLowerCase()).filter(Boolean))],
          group_size_min: groupSizeMin,
          group_size_max: groupSizeMax,
          duration_value: durationValue,
          duration_unit: durationUnit,
          // Old nullable difficulty unrelated to this save stays omitted
          // (backend rejects supplied null; omission preserves absence);
          // only an actually chosen supported code travels.
          ...(difficultyDirty && difficultyLevel ? { difficulty_level: difficultyLevel } : {}),
          translations: translationsPayload,
          // Touched options are submitted whole (an emptied array retires a
          // previously optioned tour); untouched options stay omitted so
          // unrelated saves never preserve/fabricate rows by accident.
          ...(optionsDirty ? { options: toOptionsPayload(options) } : {}),
          // Media carry alt_text presence exactly (omitted stays absent,
          // explicit null clears), preserving URL identity, order, and
          // cover flags; omission retains the gallery untouched.
          ...(mediaDirty ? { media: media.map((item) => ({
            url: item.url,
            is_cover: item.is_cover,
            ...('alt_text' in item ? { alt_text: item.alt_text } : {}),
          })) } : {}),
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
      setCoverImageDirty(false);
      setMediaDirty(false);
      setDifficultyDirty(false);
      setOptionsDirty(false);
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
          <Link href={`/${locale}/partner`} aria-label={formT('backToDashboard')} className="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">
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
            <p className="text-sm text-gray-500">
              ID: {id} — {formT('sourceLanguageNotice', { language: formT(`sourceLanguageNames.${sourceLocale}`) })}
            </p>
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

          <p className="text-sm text-gray-600">
            {formT('sourceLanguageLockedNotice', { language: formT(`sourceLanguageNames.${sourceLocale}`) })}
          </p>
          {tour?.translation_statuses && (
            <p className="text-sm text-gray-600" aria-live="polite">
              {(['en', 'es', 'it'] as const)
                .filter((generated) => generated !== sourceLocale && tour.translation_statuses?.[generated])
                .map((generated) => `${generated.toUpperCase()}: ${formT(`translationStatus.${tour.translation_statuses?.[generated]}`)}`)
                .join(' · ')}
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
                required
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
              <PartnerRichTextEditor
                id="edit-source-description"
                value={translationData[activeLangTab].description}
                onChange={(val) => updateTranslationField(activeLangTab, 'description', val)}
                placeholder={formT('descriptionPlaceholder')}
                aria-label={`${formT('description')} (${activeLangTab.toUpperCase()})`}
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
            <ItineraryEditor value={translationData[sourceLocale].itinerary} onChange={(days) => updateTranslationField(sourceLocale, 'itinerary', days)} errors={fieldErrors} />

            {/* Authored bookable options: rows keep their persistent ids and
            a touched set is submitted whole; untouched options stay omitted. */}
            <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <BookingOptionsForm
                rows={options}
                disabled={false}
                sourceLocale={sourceLocale}
                onAdd={() => {
                  setOptions((prev) => [...prev, {
                    id: `edit-option-${prev.length + 1}-${Date.now()}`,
                    title: '',
                    pickup_instructions: '',
                    guide_languages: [],
                    price: '',
                    capacity: '',
                    start_time: '',
                    weekdays: [],
                  }]);
                  setOptionsDirty(true);
                }}
                onRemove={(rowId) => {
                  setOptions((prev) => prev.filter((row) => row.id !== rowId));
                  setOptionsDirty(true);
                }}
                onUpdate={(rowId, updates) => {
                  setOptions((prev) => prev.map((row) => (row.id === rowId ? { ...row, ...updates } : row)));
                  setOptionsDirty(true);
                }}
                serverErrors={fieldErrors}
              />
            </div>
          </div>
        </div>

        {/* Right: Technical Metadata Settings */}
        <aside className="space-y-6">
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4 shadow-sm">
            <h3 className="font-bold text-sm text-[#0A2540] border-b border-gray-100 pb-2">{formT('tourSettings')}</h3>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-location" className="text-xs font-semibold text-gray-600">{formT('locationName')}</label>
              <input
                id="edit-location"
                type="text"
                required
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder={formT('destinationPlaceholder')}
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-cover-image-url" className="text-xs font-semibold text-gray-600">{formT('coverImageUrl')}</label>
              <input
                id="edit-cover-image-url"
                type="text"
                value={coverImageUrl}
                onChange={(e) => {
                  setCoverImageUrl(e.target.value);
                  setCoverImageDirty(true);
                }}
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
                <label htmlFor="edit-duration-value" className="text-xs font-semibold text-gray-600">{formT('durationValue')}</label>
                <input
                  id="edit-duration-value"
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
                <label htmlFor="edit-duration-unit" className="text-xs font-semibold text-gray-600">{formT('durationUnit')}</label>
                <select
                  id="edit-duration-unit"
                  value={durationUnit}
                  onChange={(e) => setDurationUnit(e.target.value)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                >
                  <option value="hour">{formT('hours')}</option>
                  <option value="day">{formT('days')}</option>
                </select>
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="edit-difficulty" className="text-xs font-semibold text-gray-600">{formT('difficultyLevel')}</label>
              <select
                id="edit-difficulty"
                value={difficultyLevel ?? ''}
                onChange={(e) => {
                  const next = e.target.value;
                  setDifficultyLevel(next === 'easy' || next === 'moderate' || next === 'challenging' ? next : null);
                  setDifficultyDirty(true);
                }}
                className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
              >
                <option value="">{formT('difficultyUnspecified')}</option>
                <option value="easy">{formT('easy')}</option>
                <option value="moderate">{formT('moderate')}</option>
                <option value="challenging">{formT('challenging')}</option>
              </select>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-min-guests" className="text-xs font-semibold text-gray-600">{formT('minGuests')}</label>
                <input
                  id="edit-min-guests"
                  type="number"
                  required
                  min={1}
                  value={groupSizeMin}
                  onChange={(e) => setGroupSizeMin(parseInt(e.target.value) || 1)}
                  className="px-3 py-2 text-sm border rounded-lg bg-white outline-none"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <label htmlFor="edit-max-guests" className="text-xs font-semibold text-gray-600">{formT('maxGuests')}</label>
                <input
                  id="edit-max-guests"
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
              disabled={!mediaAltValid}
              className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-[#0A2540] hover:bg-[#FFB800] hover:text-[#0A2540] text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
            >
              <Save className="w-4 h-4" />
              {formT('saveDetails')}
            </button>
          </div>
          <div className="rounded-xl border border-border bg-surface p-5">
            <ImageUploader
              key={tour?.id}
              media={media}
              title={translationData[sourceLocale].title}
              onValidityChange={setMediaAltValid}
              onChange={(next) => {
                setMedia(next);
                setMediaDirty(true);
                const nextCover = next.find((item) => item.is_cover)?.url;
                if (nextCover !== undefined) {
                  setCoverImageUrl(nextCover);
                  setCoverImageDirty(true);
                }
              }}
            />
          </div>
        </aside>
      </form>
      <button type="button" onClick={() => setPreviewOpen((open) => !open)} className="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-bookly-navy">
        {formT(previewOpen ? 'hidePreview' : 'previewTour')}
      </button>
      {previewOpen && (
        <TourContentPreview
          sourceLocale={sourceLocale}
          title={translationData[sourceLocale].title}
          description={translationData[sourceLocale].description}
          itinerary={translationData[sourceLocale].itinerary}
          media={media}
          highlights={translationData[sourceLocale].highlights}
          inclusions={translationData[sourceLocale].inclusions}
          exclusions={translationData[sourceLocale].exclusions}
          important_information={translationData[sourceLocale].important_information}
          meeting_point={translationData[sourceLocale].meeting_point || null}
          cancellation_policy={translationData[sourceLocale].cancellation_policy || null}
          translationStatuses={tour?.translation_statuses ?? null}
        />
      )}
    </div>
  );
}

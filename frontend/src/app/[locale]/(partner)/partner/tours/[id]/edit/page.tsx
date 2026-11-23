'use client';

import { useState, useEffect, use, useCallback, useRef, useMemo } from 'react';
import { ArrowLeft, Save, Calendar, Coins, Send, CheckCircle } from 'lucide-react';
import Link from 'next/link';
import { getAuthToken } from '@/lib/auth/token';
import { getApiBaseUrl, NotFoundError } from '@/lib/api/client';
import {
  getLatestTourDraft,
  createTourRevision,
  replaceTourRevision,
  submitTourRevision,
  withdrawTourRevision,
  respondToTourChangeRequest,
  getTourRevision,
} from '@/lib/api/partner';
import { normalizeWizardState } from '@/lib/stores/tourWizard';
import { useTranslations } from 'next-intl';
import { localizeServerFieldError, pickFieldErrors, toFormErrorPath, getSubmittedOptionRows } from '@/lib/validators/partner';
import { ImageUploader } from '@/components/partner/tours/ImageUploader';
import { ItineraryEditor } from '@/components/partner/tours/ItineraryEditor';
import { TourContentPreview } from '@/components/partner/tours/TourContentPreview';
import { BookingOptionsForm } from '@/components/partner/tours/BookingOptionsForm';
import { TourRevisionPanel } from '@/components/partner/tours/TourRevisionPanel';
import { PartnerRichTextEditor } from '@/components/partner/tours/PartnerRichTextEditor';
import { toMinorUnits } from '@/lib/utils';
import type {
  TourItineraryDay,
  ContentSourceLocale,
  PartnerBookingOptionInput,
  ContentGovernance,
  TourRevisionDetail,
  TourRevisionState,
  TourRevisionResponsePayload,
  TourRevisionPatchPayload,
  RevisionPayload,
  RevisionSnapshotOption,
  RevisionSnapshotMedia,
  EnglishSourceInput,
} from '@/lib/api/types';
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

/* ─── EGM-003 revision governance helpers ────────────────────────────────── */

/** Parse the additive owned `content_governance` object. Returns null for
 *  anything that is not a complete governance record, so a published tour
 *  without valid metadata fails closed instead of guessing capabilities. */
function parseGovernance(value: unknown): ContentGovernance | null {
  if (!isRecord(value)) return null;
  const { source_locale, generation_epoch, approved_revision, active_candidate, open_requests } = value;
  if (source_locale !== 'en' && source_locale !== 'es' && source_locale !== 'it') return null;
  if (typeof generation_epoch !== 'number' || !Number.isSafeInteger(generation_epoch) || generation_epoch < 0) return null;
  const isSummary = (entry: unknown): entry is Record<string, unknown> =>
    isRecord(entry) && typeof entry.id === 'number' && typeof entry.revision_number === 'number'
    && typeof entry.payload_hash === 'string';
  if (approved_revision !== null && !isSummary(approved_revision)) return null;
  if (active_candidate !== null && !isSummary(active_candidate)) return null;
  if (!Array.isArray(open_requests)) return null;
  return value as unknown as ContentGovernance;
}

const REVISION_SOURCE_TEXT_FIELDS = ['title', 'description', 'meeting_point', 'cancellation_policy'] as const;
const REVISION_SOURCE_LIST_FIELDS = ['highlights', 'inclusions', 'exclusions', 'important_information'] as const;

interface RevisionRestore {
  /** Candidate source tier mapped back into the authored write shape. */
  source: Partial<Translation>;
  nulled: Array<keyof Translation>;
  /** Candidate presentation tier (null when the snapshot omits it). */
  presentation: Record<string, unknown> | null;
  /** Candidate option text rows in stored (live) order. */
  options: RevisionSnapshotOption[] | null;
  /** Candidate media projection in stored order. */
  media: TourMedia[] | null;
}

/** Normalize revision snapshot options from either an array or the backend's
 *  ID-keyed object, preserving live display order when numeric keys would
 *  otherwise iterate in ascending ID order. */
function parseRevisionOptions(rawOptions: unknown, liveOptionOrder?: number[]): RevisionSnapshotOption[] | null {
  if (Array.isArray(rawOptions)) {
    return rawOptions
      .filter((entry): entry is RevisionSnapshotOption => isRecord(entry) && typeof entry.id === 'number' && typeof entry.title === 'string')
      .map((entry) => ({
        id: entry.id,
        title: entry.title,
        pickup_instructions: typeof entry.pickup_instructions === 'string' ? entry.pickup_instructions : null,
      }));
  }
  if (!isRecord(rawOptions)) return null;
  const entries: RevisionSnapshotOption[] = [];
  for (const [key, entry] of Object.entries(rawOptions)) {
    if (!isRecord(entry) || typeof entry.title !== 'string') continue;
    const keyId = /^\d+$/.test(key) ? Number(key) : NaN;
    const optionId = typeof entry.id === 'number' && Number.isSafeInteger(entry.id) && entry.id > 0
      ? entry.id
      : (Number.isSafeInteger(keyId) && keyId > 0 ? keyId : null);
    if (optionId === null) continue;
    entries.push({
      id: optionId,
      title: entry.title,
      pickup_instructions: typeof entry.pickup_instructions === 'string' ? entry.pickup_instructions : null,
    });
  }
  if (liveOptionOrder && liveOptionOrder.length > 0 && entries.length > 1) {
    const orderIndex = new Map<number, number>(liveOptionOrder.map((optionId, index) => [optionId, index]));
    entries.sort((a, b) => (orderIndex.get(a.id) ?? Number.MAX_SAFE_INTEGER) - (orderIndex.get(b.id) ?? Number.MAX_SAFE_INTEGER));
  }
  return entries;
}

/** Map a full candidate `source` tier back into the authored editor shape:
 *  the stored snapshot tier `source` holds the same nine canonical fields
 *  the write form keys under `translations.{fixed_source_locale}`, so this
 *  inverse mapping is explicit and locale-independent (never a locale swap). */
function revisionRestore(
  payload: RevisionPayload | null | undefined,
  fallbackSourceLocale: ContentSourceLocale,
  liveOptionOrder?: number[]
): RevisionRestore {
  if (!payload || !isRecord(payload.source)) {
    return { source: {}, nulled: [], presentation: null, options: null, media: null };
  }
  const source = payload.source as Record<string, unknown>;
  const mapped: Record<string, unknown> = {};
  const nulled: Array<keyof Translation> = [];
  for (const field of REVISION_SOURCE_TEXT_FIELDS) {
    if (!(field in source)) continue;
    const value = source[field];
    if (typeof value === 'string') mapped[field] = value;
    else if (value === null) {
      mapped[field] = '';
      nulled.push(field);
    }
  }
  for (const field of REVISION_SOURCE_LIST_FIELDS) {
    if (!(field in source)) continue;
    const value = source[field];
    if (typeof value === 'string' || Array.isArray(value)) {
      const normalized = normalizeWizardState({ [field]: value });
      const list = normalized[field];
      if (Array.isArray(list)) mapped[field] = list.filter((item): item is string => typeof item === 'string');
    } else if (value === null) {
      mapped[field] = [];
      nulled.push(field);
    }
  }
  if ('itinerary' in source) {
    const value = source.itinerary;
    if (Array.isArray(value)) mapped.itinerary = normalizeWizardState({ itinerary: value }).itinerary ?? [];
    else if (value === null) {
      mapped.itinerary = [];
      nulled.push('itinerary');
    }
  }
  void fallbackSourceLocale; // the source tier is locale-free; the editor binds it to the tour's fixed locale

  const presentation = isRecord(payload.presentation) ? payload.presentation : null;

  const options = parseRevisionOptions(payload.options, liveOptionOrder);

  const media = Array.isArray(payload.media)
    ? payload.media
      .filter((entry): entry is RevisionSnapshotMedia => isRecord(entry) && typeof entry.url === 'string')
      .map((entry, index) => {
        const row: TourMedia = {
          id: `revision-media-${index}`,
          url: entry.url,
          is_cover: entry.is_cover === true,
          sort_order: index,
        };
        if ('alt_text' in entry) {
          row.alt_text = typeof entry.alt_text === 'string' ? entry.alt_text : null;
        }
        return row;
      })
    : null;

  return {
    source: mapped as Partial<Translation>,
    nulled,
    presentation,
    options,
    media,
  };
}

/** Build candidate option editor rows: authored text comes from the revision
 *  snapshot while commercial values (price/capacity/schedule codes) stay
 *  seeded from the CURRENT live approved option rows by stable id. */
function candidateOptionRows(
  snapshotOptions: RevisionSnapshotOption[],
  liveOptions: OwnedTourOption[]
): BookingOptionFormInput[] {
  const liveById = new Map<number, OwnedTourOption>();
  for (const option of liveOptions) {
    if (typeof option.id === 'number') liveById.set(option.id, option);
  }
  return snapshotOptions.map((snapshot, index) => {
    const live = liveById.get(snapshot.id);
    return {
      id: `revision-option-${index}`,
      server_id: snapshot.id,
      title: snapshot.title,
      pickup_instructions: snapshot.pickup_instructions ?? '',
      guide_languages: live?.guide_languages ?? [],
      price: live ? (Number(live.price_amount ?? 0) / 100).toString() : '',
      capacity: live === undefined || live.group_size_max === null || live.group_size_max === undefined ? '' : String(live.group_size_max),
      start_time: live?.start_time ?? '',
      weekdays: live?.weekdays ?? [],
    };
  });
}

/** Presentation minutes from the edit form's value/unit pair (day = 1440). */
function derivedDurationMinutes(value: number, unit: string): number {
  return unit === 'day' ? value * 1440 : value * 60;
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
  /** Additive EGM-003 governance object; absent/invalid on a published tour
   *  fails the editor closed until it loads. */
  content_governance?: ContentGovernance | null;
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

  /* ─── EGM-003 revision governance state ────────────────────────────────── */
  const revisionT = useTranslations('partner.tours.revision');
  // Governed-object state: null means "no valid governance record". On a
  // published tour that blocks every save (fail closed with a retry); drafts
  // keep their existing opaque flow regardless.
  const [governance, setGovernance] = useState<ContentGovernance | null>(null);
  // The active candidate as it currently sits in the editor: `loaded` true
  // means the full payload was applied to the form, and replacement edits are
  // safe; a metadata-only candidate blocks content saves until reloaded.
  interface CandidateContext {
    id: number;
    revisionNumber: number;
    payloadHash: string;
    baseRevisionId: number;
    state: TourRevisionState;
    loaded: boolean;
  }
  const [candidate, setCandidate] = useState<CandidateContext | null>(null);
  const [changeSummary, setChangeSummary] = useState('');
  const [savingContent, setSavingContent] = useState(false);
  const [savingOperational, setSavingOperational] = useState(false);
  const [submittingCandidate, setSubmittingCandidate] = useState(false);
  const [withdrawingCandidate, setWithdrawingCandidate] = useState(false);
  const [respondingRequest, setRespondingRequest] = useState(false);
  const [showReloadCandidate, setShowReloadCandidate] = useState(false);
  // Page-level revision status region (guidance, outcomes, focused errors).
  const [revisionStatus, setRevisionStatus] = useState<{ tone: 'info' | 'error'; message: string } | null>(null);
  const revisionStatusRef = useRef<HTMLDivElement>(null);
  // Save baselines: the values the last completed save (approved content or
  // the restored candidate) left in place. Published routing compares the
  // current form against them; values never reset on failures.
  const [sourceBaseline, setSourceBaseline] = useState<Translation | null>(null);
  const [sourceBaselineNulled, setSourceBaselineNulled] = useState<Set<keyof Translation>>(new Set());
  const [presentationBaseline, setPresentationBaseline] = useState<{
    category_id: number;
    location: string;
    cover_image_url: string | null;
    guide_languages: string[];
    group_size_min: number;
    group_size_max: number;
    duration_minutes: number;
    difficulty_level: DifficultyLevel | null;
  } | null>(null);
  const [mediaBaseline, setMediaBaseline] = useState<TourMedia[] | null>(null);
  interface OptionTextBaselineEntry { title: string; pickup_instructions: string | null; guide_languages: string[] }
  interface OptionCommercialBaselineEntry {
    price_amount: number;
    group_size_max: number | null;
    start_time: string | null;
    weekdays: number[] | null;
  }
  const [optionTextBaseline, setOptionTextBaseline] = useState<Record<number, OptionTextBaselineEntry> | null>(null);
  const [optionOrderBaseline, setOptionOrderBaseline] = useState<number[] | null>(null);
  const [optionCommercialBaseline, setOptionCommercialBaseline] = useState<Record<number, OptionCommercialBaselineEntry> | null>(null);

  const isPublishedTour = tour?.status === 'published';
  const governanceBlocked = isPublishedTour && governance === null;
  const governanceBlockedSignature = governanceBlocked ? 'blocked' : '';

  // Focus the revision status / governance-blocked regions only when they
  // newly appear, so keyboard users hear published-save outcomes and refusals.
  const revisionStatusSignature = revisionStatus ? `${revisionStatus.tone}|${revisionStatus.message}` : '';
  const prevRevisionStatusSignatureRef = useRef('');
  useEffect(() => {
    if (revisionStatusSignature && revisionStatusSignature !== prevRevisionStatusSignatureRef.current) {
      prevRevisionStatusSignatureRef.current = revisionStatusSignature;
      revisionStatusRef.current?.focus();
    }
    if (!revisionStatusSignature) {
      prevRevisionStatusSignatureRef.current = '';
    }
  }, [revisionStatusSignature]);

  const governanceBlockedRef = useRef<HTMLDivElement>(null);
  const prevBlockedSignatureRef = useRef('');
  useEffect(() => {
    if (governanceBlockedSignature && governanceBlockedSignature !== prevBlockedSignatureRef.current) {
      prevBlockedSignatureRef.current = governanceBlockedSignature;
      governanceBlockedRef.current?.focus();
    }
    if (!governanceBlockedSignature) {
      prevBlockedSignatureRef.current = '';
    }
  }, [governanceBlockedSignature]);

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

  /* ─── Published mixed-save routing (EGM-003) ───────────────────────────── */
  const normalizeGuideCodes = (raw: string): string[] =>
    [...new Set(raw.split(',').map((code) => code.trim().toLowerCase()).filter(Boolean))].sort();
  const canonicalizeWeekdays = (days: number[]): number[] | null => (days.length > 0 ? [...new Set(days)].sort((a, b) => a - b) : null);

  /** Classifies the current form against the last-saved baseline on a
   *  published tour: governed (reviewable) vs. commercial (operational)
   *  vs. structural option changes. Never mutates anything. */
  const publishedGating = useMemo(() => {
    const emptyResult = { governedDirty: false, commercialDirty: false, structuralDirty: false, optionTextDirty: false };
    if (!isPublishedTour || !sourceBaseline || !presentationBaseline || !mediaBaseline) {
      return emptyResult;
    }

    // Governed: canonical source fields (null-aware).
    const canonicCurrent = (field: keyof Translation): unknown =>
      nullSourceFields.has(field) ? null : translationData[sourceLocale][field];
    const canonicBaseline = (field: keyof Translation): unknown => {
      const value = sourceBaseline[field];
      if (value === undefined) return undefined;
      return sourceBaselineNulled.has(field) ? null : value;
    };
    let sourceDirty = false;
    for (const field of SNAPSHOT_TEXT_FIELDS) {
      if (canonicCurrent(field) !== canonicBaseline(field)) sourceDirty = true;
    }
    for (const field of SNAPSHOT_LIST_FIELDS) {
      if (JSON.stringify(canonicCurrent(field)) !== JSON.stringify(canonicBaseline(field))) sourceDirty = true;
    }
    if (JSON.stringify(canonicCurrent('itinerary')) !== JSON.stringify(canonicBaseline('itinerary'))) {
      sourceDirty = true;
    }

    // Governed: presentation fields.
    const baseline = presentationBaseline;
    const guideNormalized = normalizeGuideCodes(guideLanguagesInput);
    const guideBaseline = [...new Set(baseline.guide_languages.map((code) => code.trim().toLowerCase()))].sort();
    let presentationDirty = false;
    if (categoryId !== baseline.category_id) presentationDirty = true;
    if (location !== baseline.location) presentationDirty = true;
    if (JSON.stringify(guideNormalized) !== JSON.stringify(guideBaseline)) presentationDirty = true;
    if (groupSizeMin !== baseline.group_size_min) presentationDirty = true;
    if (groupSizeMax !== baseline.group_size_max) presentationDirty = true;
    const derivedMinutes = derivedDurationMinutes(durationValue, durationUnit);
    if (!Number.isFinite(derivedMinutes) || derivedMinutes !== baseline.duration_minutes) presentationDirty = true;
    if (difficultyLevel !== baseline.difficulty_level) presentationDirty = true;
    if (coverImageDirty || coverImageUrl !== (baseline.cover_image_url ?? '')) presentationDirty = true;

    // Governed: media projection.
    let mediaDirtyNow = mediaDirty;
    if (!mediaDirtyNow && mediaBaseline && media.length !== mediaBaseline.length) mediaDirtyNow = true;

    // Options: authored text vs. price/capacity/schedule vs. structure.
    const submittedRows = getSubmittedOptionRows(options);
    const textBaselineMap = optionTextBaseline ?? {};
    const commercialBaselineMap = optionCommercialBaseline ?? {};
    const orderBaseline = optionOrderBaseline ?? [];
    let optionTextDirty = false;
    let commercialDirty = false;
    let structuralDirty = false;
    const submittedIds: number[] = [];
    for (const row of submittedRows) {
      if (row.server_id === undefined) {
        structuralDirty = true;
        continue;
      }
      submittedIds.push(row.server_id);
      const textBase = textBaselineMap[row.server_id];
      if (!textBase) {
        structuralDirty = true;
        continue;
      }
      if (row.title.trim() !== textBase.title) optionTextDirty = true;
      if (row.pickup_instructions.trim() !== (textBase.pickup_instructions ?? '')) optionTextDirty = true;

      const commercialBase = commercialBaselineMap[row.server_id];
      if (!commercialBase) {
        structuralDirty = true;
        continue;
      }
      if (toMinorUnits(row.price) !== commercialBase.price_amount) commercialDirty = true;
      const capacityValue = row.capacity.trim() === '' ? null : Number(row.capacity);
      if (capacityValue !== commercialBase.group_size_max) commercialDirty = true;
      if ((row.start_time.trim() === '' ? null : row.start_time.trim()) !== commercialBase.start_time) commercialDirty = true;
      if (JSON.stringify(canonicalizeWeekdays(row.weekdays)) !== JSON.stringify(commercialBase.weekdays)) commercialDirty = true;
    }
    if (JSON.stringify(submittedIds) !== JSON.stringify(orderBaseline)) structuralDirty = true;

    return {
      governedDirty: sourceDirty || presentationDirty || mediaDirtyNow || optionTextDirty || structuralDirty,
      commercialDirty,
      structuralDirty,
      optionTextDirty,
    };
  }, [
    isPublishedTour, sourceBaseline, sourceBaselineNulled, presentationBaseline, mediaBaseline,
    optionTextBaseline, optionOrderBaseline, optionCommercialBaseline, translationData, sourceLocale,
    nullSourceFields, categoryId, location, guideLanguagesInput, groupSizeMin, groupSizeMax,
    durationValue, durationUnit, difficultyLevel, coverImageDirty, coverImageUrl, media, mediaDirty, options,
  ]);

  /** Refresh ONLY the governance pointer/metadata (and live status) without
   *  touching any in-form value, so unsaved edits survive request outcomes. */
  const refreshGovernanceState = useCallback(async (): Promise<ContentGovernance | null> => {
    const token = getAuthToken();
    try {
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${id}`, {
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          Accept: 'application/json',
        },
      });
      if (!res.ok) return null;
      const json = await res.json();
      if (!isRecord(json.data) || typeof json.data.id !== 'number') return null;
      const record = json.data as Record<string, unknown>;
      const nextGovernance = parseGovernance(record.content_governance);
      setGovernance(nextGovernance);
      setTour((prev) => (prev ? { ...prev, status: typeof record.status === 'string' ? record.status : prev.status } : prev));
      return nextGovernance;
    } catch {
      return null;
    }
  }, [id]);

  /** Load the full immutable candidate payload into the editor: the stored
   *  `source` tier maps back onto the authored write shape, the presentation
   *  tier restores the technical fields, and option text comes from the
   *  candidate while commercial values stay seeded from the live rows. The
   *  candidate becomes the governed baseline for the next save.
   *  Kept in a ref (not a useCallback dep) so the initial-fetch effect keeps
   *  a stable identity — `useTranslations` returns a fresh function per
   *  render and must never enter a dependency list. */
  const applyCandidateRevision = async (
    revisionId: number,
    tourData: Tour,
    liveOptions: OwnedTourOption[],
    actualSource: SourceLocale,
    liveMedia: TourMedia[]
  ): Promise<CandidateContext | null> => {
    const res = await getTourRevision(id, revisionId);
    if (!res.ok || !res.data || !res.data.payload) {
      setRevisionStatus({ tone: 'error', message: revisionT('errors.candidateLoadFailed') });
      setShowReloadCandidate(true);
      return null;
    }
    const detail: TourRevisionDetail = res.data;
    const liveOptionOrder = liveOptions
      .map((option) => option.id)
      .filter((optionId): optionId is number => typeof optionId === 'number');
    const restore = revisionRestore(detail.payload, actualSource, liveOptionOrder);

    setTranslationData((prev) => ({ ...prev, [actualSource]: { ...prev[actualSource], ...restore.source } }));
    const nextNulled = new Set(nullSourceFields);
    restore.nulled.forEach((field) => nextNulled.add(field));
    Object.keys(restore.source).forEach((field) => {
      if (!restore.nulled.includes(field as keyof Translation)) nextNulled.delete(field as keyof Translation);
    });
    setNullSourceFields(nextNulled);

    const presentation = restore.presentation as Record<string, unknown> | null;
    const candidateDurationMinutes = typeof presentation?.duration_minutes === 'number' && presentation.duration_minutes > 0
      ? presentation.duration_minutes
      : (tourData.duration_minutes ?? 0);
    if (presentation) {
      setCategoryId(typeof presentation.category_id === 'number' ? presentation.category_id : tourData.category_id);
      setLocation(typeof presentation.location === 'string' ? presentation.location : tourData.location);
      const coverValue = typeof presentation.cover_image_url === 'string' ? presentation.cover_image_url : null;
      setCoverImageUrl(coverValue || tourData.cover_image_url || '');
      const guides = Array.isArray(presentation.guide_languages)
        ? presentation.guide_languages.filter((code): code is string => typeof code === 'string')
        : (tourData.guide_languages ?? []);
      setGuideLanguagesInput(guides.join(', '));
      setGroupSizeMin(typeof presentation.group_size_min === 'number' ? presentation.group_size_min : tourData.group_size_min);
      setGroupSizeMax(typeof presentation.group_size_max === 'number' ? presentation.group_size_max : tourData.group_size_max);
      setDurationValue(candidateDurationMinutes >= 1440 ? candidateDurationMinutes / 1440 : candidateDurationMinutes / 60);
      setDurationUnit(candidateDurationMinutes >= 1440 ? 'day' : 'hour');
      setDifficultyLevel(
        presentation.difficulty_level === 'easy' || presentation.difficulty_level === 'moderate' || presentation.difficulty_level === 'challenging'
          ? presentation.difficulty_level
          : null
      );
    }
    if (restore.options !== null) {
      setOptions(candidateOptionRows(restore.options, liveOptions));
    }
    const candidateMedia = restore.media !== null ? restore.media : liveMedia;
    if (restore.media !== null) {
      setMedia(restore.media);
    }

    // The candidate is now the governed editing baseline; the commercial
    // baseline stays live (candidate payloads never carry prices).
    setSourceBaseline({ ...emptyTranslation(), ...restore.source });
    setSourceBaselineNulled(new Set(restore.nulled));
    setPresentationBaseline({
      category_id: typeof presentation?.category_id === 'number' ? presentation.category_id : tourData.category_id,
      location: typeof presentation?.location === 'string' ? presentation.location : tourData.location,
      cover_image_url: typeof presentation?.cover_image_url === 'string' ? presentation.cover_image_url : (tourData.cover_image_url || null),
      guide_languages: Array.isArray(presentation?.guide_languages)
        ? (presentation?.guide_languages as string[])
        : (tourData.guide_languages ?? []),
      group_size_min: typeof presentation?.group_size_min === 'number' ? presentation.group_size_min : tourData.group_size_min,
      group_size_max: typeof presentation?.group_size_max === 'number' ? presentation.group_size_max : tourData.group_size_max,
      duration_minutes: candidateDurationMinutes,
      difficulty_level: (presentation?.difficulty_level === 'easy' || presentation?.difficulty_level === 'moderate' || presentation?.difficulty_level === 'challenging')
        ? presentation.difficulty_level
        : null,
    });
    setMediaBaseline(candidateMedia.map((item) => ({ ...item })));
    setMediaDirty(false);
    setCoverImageDirty(false);
    setOptionsDirty(false);
    setDifficultyDirty(false);
    setChangeSummary(typeof detail.change_summary === 'string' ? detail.change_summary : '');
    const candidateContext: CandidateContext = {
      id: detail.id,
      revisionNumber: detail.revision_number,
      payloadHash: detail.payload_hash,
      baseRevisionId: detail.base_revision_id ?? 0,
      state: detail.state,
      loaded: true,
    };
    if (restore.options !== null) {
      const textBaseline: Record<number, OptionTextBaselineEntry> = {};
      const order: number[] = [];
      for (const option of restore.options) {
        order.push(option.id);
        textBaseline[option.id] = {
          title: option.title,
          pickup_instructions: option.pickup_instructions ?? null,
          guide_languages: candidateOptionRows(restore.options, liveOptions).find((row) => row.server_id === option.id)?.guide_languages ?? [],
        };
      }
      setOptionTextBaseline(textBaseline);
      setOptionOrderBaseline(order);
    }
    setCandidate(candidateContext);
    setRevisionStatus({ tone: 'info', message: revisionT('candidateLoaded', { number: detail.revision_number }) });
    setShowReloadCandidate(false);
    return candidateContext;
  };

  // The initial-fetch effect must keep a stable identity: publish the latest
  // per-render candidate loader through the ref (assignment only, no
  // re-render loop).
  const applyCandidateRevisionRef = useRef(applyCandidateRevision);
  useEffect(() => {
    applyCandidateRevisionRef.current = applyCandidateRevision;
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

      // Additive EGM-003 governance: a published tour without a valid record
      // leaves `governance` null and every save stays blocked (fail closed).
      // Never-public tours keep their existing flow with or without it.
      const loadedGovernance = parseGovernance(t.content_governance);

      // Actual source language of the authored content: legacy tours read
      // as the English source.
      const actualSource: SourceLocale = t.source_locale === 'es' || t.source_locale === 'it' ? t.source_locale : 'en';
      setSourceLocale(actualSource);

      // Populate states
      setCategoryId(t.category_id);
      setLocation(t.location);
      setCoverImageUrl(t.cover_image_url || '');
      setCoverImageDirty(false);
      const nextMedia = (t.media ?? []).slice().sort((a, b) => a.sort_order - b.sort_order).map((item) => ({
        ...item, is_cover: item.url === t.cover_image_url,
      }));
      setMedia(nextMedia);
      setMediaBaseline(nextMedia.map((item) => ({ ...item })));
      setMediaDirty(false);
      setGuideLanguagesInput((t.guide_languages ?? []).join(', '));
      setGroupSizeMin(t.group_size_min);
      setGroupSizeMax(t.group_size_max);

      // Authored options: persisted ids are retained for edits; prices are
      // stored as integer cents and are shown in currency major units.
      const nextOptions = (t.options ?? []).map((option, index) => ({
        id: `stored-option-${option.id ?? index}`,
        ...(option.id !== undefined ? { server_id: option.id } : {}),
        title: option.title ?? '',
        pickup_instructions: option.pickup_instructions ?? '',
        guide_languages: option.guide_languages ?? [],
        price: (Number(option.price_amount ?? 0) / 100).toString(),
        capacity: option.group_size_max === null || option.group_size_max === undefined ? '' : String(option.group_size_max),
        start_time: option.start_time ?? '',
        weekdays: option.weekdays ?? [],
      }));
      setOptions(nextOptions);
      setOptionsDirty(false);
      // Baselines: approved/identity text (reviewable) and commercial values
      // (operational) are tracked separately so mixed saves route correctly.
      const nextTextBaseline: Record<number, OptionTextBaselineEntry> = {};
      const nextCommercialBaseline: Record<number, OptionCommercialBaselineEntry> = {};
      const nextOrder: number[] = [];
      for (const option of t.options ?? []) {
        if (typeof option.id !== 'number') continue;
        nextOrder.push(option.id);
        nextTextBaseline[option.id] = {
          title: option.title ?? '',
          pickup_instructions: option.pickup_instructions ?? null,
          guide_languages: option.guide_languages ?? [],
        };
        nextCommercialBaseline[option.id] = {
          price_amount: option.price_amount ?? 0,
          group_size_max: option.group_size_max ?? null,
          start_time: option.start_time ?? null,
          weekdays: option.weekdays ?? null,
        };
      }
      setOptionTextBaseline(nextTextBaseline);
      setOptionOrderBaseline(nextOrder);
      setOptionCommercialBaseline(nextCommercialBaseline);

      const val = t.duration_minutes ? (t.duration_minutes >= 1440 ? t.duration_minutes / 1440 : t.duration_minutes / 60) : 2;
      const unit = t.duration_minutes && t.duration_minutes >= 1440 ? 'day' : 'hour';
      setDurationValue(val);
      setDurationUnit(unit);
      // Older null/missing difficulty stays unknown (blank select), never
      // inferred as easy; only supported codes restore as selected.
      const loadedDifficulty =
        t.difficulty_level === 'easy' || t.difficulty_level === 'moderate' || t.difficulty_level === 'challenging'
          ? t.difficulty_level
          : null;
      setDifficultyLevel(loadedDifficulty);
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
      const nulls = new Set<keyof Translation>();
      const sourceRow = t.translations.find((tr) => tr.locale === actualSource);
      if (sourceRow) {
        (['description', 'highlights', 'inclusions', 'exclusions', 'meeting_point', 'cancellation_policy', 'important_information', 'itinerary'] as const)
          .forEach((field) => {
            if (sourceRow[field] === null || sourceRow[field] === undefined) {
              nulls.add(field);
            }
          });
      }
      setNullSourceFields(nulls);

      // Save baselines for published mixed-save routing reflect the live
      // approved content; a candidate restore below replaces them.
      setSourceBaseline({ ...newTrans[actualSource] });
      setSourceBaselineNulled(new Set(nulls));
      setPresentationBaseline({
        category_id: t.category_id,
        location: t.location,
        cover_image_url: t.cover_image_url || null,
        guide_languages: t.guide_languages ?? [],
        group_size_min: t.group_size_min,
        group_size_max: t.group_size_max,
        duration_minutes: t.duration_minutes ?? 0,
        difficulty_level: loadedDifficulty,
      });
      setGovernance(loadedGovernance);

      // Published restore: an open candidate (not the opaque editor snapshot)
      // is the editing baseline. Its full immutable payload replaces the
      // approved values in the form before anything can be saved.
      const activeCandidate = loadedGovernance?.active_candidate ?? null;
      if (t.status === 'published' && activeCandidate) {
        setCandidate({
          id: activeCandidate.id,
          revisionNumber: activeCandidate.revision_number,
          payloadHash: activeCandidate.payload_hash,
          baseRevisionId: activeCandidate.base_revision_id,
          state: activeCandidate.state,
          loaded: false,
        });
        setShowReloadCandidate(true);
        await applyCandidateRevisionRef.current(
          activeCandidate.id,
          t,
          (t.options ?? []) as OwnedTourOption[],
          actualSource,
          nextMedia
        );
      } else if (t.status !== 'published') {
        setCandidate(null);
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

  /* ─── Published routing: revision endpoint vs operational-only save ─────── */

  const setRevisionNotice = (tone: 'info' | 'error', message: string) => setRevisionStatus({ tone, message });

  const revisionFieldErrors = (errors: Record<string, string[]>): Record<string, string> => {
    const mapped: Record<string, string> = {};
    for (const [path, messages] of Object.entries(errors)) {
      const formPath = toFormErrorPath(path);
      if (!(formPath in mapped) && Array.isArray(messages) && messages.length > 0 && typeof messages[0] === 'string') {
        mapped[formPath] = localizeServerFieldError(
          formPath, messages[0], (key) => tourT(key.replace(/^partner\.tours\./, ''))
        );
      }
    }
    return mapped;
  };

  const localizedContentInvalid = () =>
    revisionT('errors.contentInvalid', { language: formT(`sourceLanguageNames.${sourceLocale}`) });

  const handleRevisionFailure = (
    res: { status: number; errorCode?: string },
    result: { errors?: Record<string, string[]> }
  ) => {
    if (res.status === 403) {
      setRevisionNotice('error', revisionT('errors.permission'));
      return;
    }
    if (res.status === 422) {
      const code = res.errorCode;
      if (result.errors) {
        // Field-level 422s hand over to the legacy focused error summary
        // (inline localized messages + focused summary box) instead of a
        // second competing announcement.
        setFieldErrors(revisionFieldErrors(result.errors));
        if (code === 'REVISION_CONTENT_INVALID') {
          setRevisionNotice('error', localizedContentInvalid());
        } else {
          setRevisionStatus(null);
        }
      } else {
        setRevisionNotice('error', code === 'REVISION_CONTENT_INVALID' ? localizedContentInvalid() : revisionT('errors.invalidFields'));
      }
      return;
    }
    if (res.errorCode === 'REVISION_CONFLICT') {
      setRevisionNotice('error', revisionT('errors.conflict'));
      setShowReloadCandidate(true);
      return;
    }
    if (res.errorCode === 'REVISION_ALREADY_OPEN') {
      setRevisionNotice('error', revisionT('errors.alreadyOpen'));
      setShowReloadCandidate(true);
      void refreshGovernanceState();
      return;
    }
    if (res.errorCode === 'REVISION_SUPERSEDED') {
      setRevisionNotice('error', revisionT('errors.superseded'));
      setShowReloadCandidate(true);
      return;
    }
    if (res.errorCode === 'REVISION_TERMINAL') {
      setRevisionNotice('error', revisionT('errors.terminal'));
      return;
    }
    setRevisionNotice('error', revisionT('errors.generic'));
  };

  /** Govern-only payload builder: full authored source (the write shape for
   *  the stored `source` tier), option authored text when dirty, the media
   *  projection when dirty, and only dirty presentation fields — never any
   *  commercial/operational key. */
  const buildGovernedPatch = (): TourRevisionPatchPayload | null => {
    if (!governance || !presentationBaseline || !sourceBaseline) return null;
    const sourcePayload: Record<string, unknown> = { ...translationData[sourceLocale] };
    nullSourceFields.forEach((field) => {
      sourcePayload[field] = null;
    });
    const patch: TourRevisionPatchPayload = {
      translations: { [sourceLocale]: sourcePayload as unknown as EnglishSourceInput },
    };
    const submittedRows = getSubmittedOptionRows(options);
    const structureOk = submittedRows.length > 0 && submittedRows.every((row) => row.server_id !== undefined);
    if (structureOk && publishedGating.optionTextDirty) {
      patch.options = submittedRows.map((row) => ({
        id: row.server_id as number,
        title: row.title.trim(),
        pickup_instructions: row.pickup_instructions.trim() === '' ? null : row.pickup_instructions.trim(),
      }));
    }
    if (mediaDirty) {
      patch.media = media.map((item) => ({
        url: item.url,
        is_cover: item.is_cover,
        ...('alt_text' in item ? { alt_text: item.alt_text } : {}),
      }));
    }
    const baseline = presentationBaseline;
    const presentation: Record<string, unknown> = {};
    if (categoryId !== baseline.category_id) presentation.category_id = categoryId;
    if (location !== baseline.location) presentation.location = location;
    if (coverImageDirty || coverImageUrl !== (baseline.cover_image_url ?? '')) presentation.cover_image_url = coverImageUrl || null;
    const guideNormalized = [...new Set(guideLanguagesInput.split(',').map((code) => code.trim().toLowerCase()).filter(Boolean))].sort();
    const guideBaseline = [...new Set(baseline.guide_languages.map((code) => code.trim().toLowerCase()))].sort();
    if (JSON.stringify(guideNormalized) !== JSON.stringify(guideBaseline)) presentation.guide_languages = guideNormalized;
    if (groupSizeMin !== baseline.group_size_min) presentation.group_size_min = groupSizeMin;
    if (groupSizeMax !== baseline.group_size_max) presentation.group_size_max = groupSizeMax;
    const derivedMinutes = derivedDurationMinutes(durationValue, durationUnit);
    if (Number.isFinite(derivedMinutes) && derivedMinutes !== baseline.duration_minutes) {
      presentation.duration_minutes = Math.round(derivedMinutes);
    }
    if (difficultyLevel !== baseline.difficulty_level && difficultyLevel) presentation.difficulty_level = difficultyLevel;
    if (Object.keys(presentation).length > 0) patch.presentation = presentation;
    const summary = changeSummary.trim();
    if (summary !== '') patch.change_summary = summary.slice(0, 500);
    return patch;
  };

  /** Refresh ONLY the governed baseline after a committed content save; unsaved
   *  commercial edits stay untouched and dirty. */
  const refreshGovernedBaseline = () => {
    setSourceBaseline({ ...translationData[sourceLocale] });
    setSourceBaselineNulled(new Set(nullSourceFields));
    const guideNormalized = [...new Set(guideLanguagesInput.split(',').map((code) => code.trim().toLowerCase()).filter(Boolean))].sort();
    setPresentationBaseline({
      category_id: categoryId,
      location,
      cover_image_url: coverImageUrl || null,
      guide_languages: guideNormalized,
      group_size_min: groupSizeMin,
      group_size_max: groupSizeMax,
      duration_minutes: Math.round(derivedDurationMinutes(durationValue, durationUnit)),
      difficulty_level: difficultyLevel,
    });
    setMediaBaseline(media.map((item) => ({ ...item })));
    setMediaDirty(false);
    setCoverImageDirty(false);
    setDifficultyDirty(false);
    setOptionsDirty(false);
    const textBaseline: Record<number, OptionTextBaselineEntry> = {};
    const order: number[] = [];
    for (const row of getSubmittedOptionRows(options)) {
      if (row.server_id === undefined) continue;
      order.push(row.server_id);
      textBaseline[row.server_id] = {
        title: row.title.trim(),
        pickup_instructions: row.pickup_instructions.trim() === '' ? null : row.pickup_instructions.trim(),
        guide_languages: optionTextBaseline?.[row.server_id]?.guide_languages ?? row.guide_languages,
      };
    }
    setOptionTextBaseline(textBaseline);
    setOptionOrderBaseline(order);
  };

  const handleSaveContentRevision = async () => {
    if (!mediaAltValid) {
      setSuccessMsg(null);
      setFieldErrors({});
      setError(formT('saveBlockedErrors'));
      return;
    }
    if (governanceBlocked || !governance) {
      setRevisionNotice('error', revisionT('errors.governanceMissing'));
      return;
    }
    if (publishedGating.structuralDirty) {
      setRevisionNotice('error', revisionT('structureChangeBlocked'));
      return;
    }
    if (candidate && !candidate.loaded) {
      // A candidate that was never loaded into the editor must never be
      // blindly replaced: reload first (fail closed against the old merge).
      setRevisionNotice('error', revisionT('errors.candidateLoadFailed'));
      return;
    }
    const governedPatch = buildGovernedPatch();
    if (governedPatch === null) {
      setRevisionNotice('error', revisionT('errors.governanceMissing'));
      return;
    }
    setSavingContent(true);
    setRevisionStatus(null);
    setFieldErrors({});
    setError(null);
    setSuccessMsg(null);
    try {
      const baselineState = candidate;
      if (baselineState && baselineState.loaded) {
        const res = await replaceTourRevision(id, baselineState.id, {
          ...governedPatch,
          base_revision_id: baselineState.baseRevisionId,
          observed_payload_hash: baselineState.payloadHash,
        });
        if (!res.ok || !res.data) {
          handleRevisionFailure(res, res);
          return;
        }
        setCandidate({
          id: res.data.id,
          revisionNumber: res.data.revision_number,
          payloadHash: res.data.payload_hash,
          baseRevisionId: res.data.base_revision_id,
          state: res.data.state,
          loaded: true,
        });
        setRevisionNotice('info', revisionT('contentReplaced', { old: baselineState.revisionNumber, number: res.data.revision_number }));
        refreshGovernedBaseline();
        await refreshGovernanceState();
        return;
      }
      const approvedId = governance.approved_revision?.id ?? null;
      if (approvedId === null) {
        setRevisionNotice('error', revisionT('errors.governanceMissing'));
        return;
      }
      const res = await createTourRevision(id, { ...governedPatch, base_revision_id: approvedId });
      if (!res.ok || !res.data) {
        handleRevisionFailure(res, res);
        return;
      }
      setCandidate({
        id: res.data.id,
        revisionNumber: res.data.revision_number,
        payloadHash: res.data.payload_hash,
        baseRevisionId: res.data.base_revision_id,
        state: res.data.state,
        loaded: true,
      });
      setRevisionNotice('info', revisionT('contentSaved', { number: res.data.revision_number }));
      refreshGovernedBaseline();
      await refreshGovernanceState();
    } finally {
      setSavingContent(false);
    }
  };

  const handleSaveOperationalChanges = async () => {
    if (governanceBlocked || !governance) {
      setRevisionNotice('error', revisionT('errors.governanceMissing'));
      return;
    }
    if (publishedGating.structuralDirty) {
      setRevisionNotice('error', revisionT('structureChangeBlocked'));
      return;
    }
    if (!publishedGating.commercialDirty) {
      setRevisionNotice('info', revisionT('nothingToSave'));
      return;
    }
    setSavingOperational(true);
    setRevisionStatus(null);
    setError(null);
    setSuccessMsg(null);
    setFieldErrors({});
    try {
      const token = getAuthToken();
      const textBaselineMap = optionTextBaseline ?? {};
      const submittedRows = getSubmittedOptionRows(options);
      const body = {
        // Operational contract: only price/capacity/schedule travel. The
        // option identity and authored text are the CURRENT APPROVED values
        // (live baseline), never the unsaved canonical text.
        options: submittedRows.map((row) => {
          const baseText = row.server_id !== undefined ? textBaselineMap[row.server_id] : undefined;
          return {
            ...(row.server_id !== undefined ? { id: row.server_id } : {}),
            title: baseText ? baseText.title : row.title.trim(),
            pickup_instructions: baseText ? baseText.pickup_instructions : (row.pickup_instructions.trim() === '' ? null : row.pickup_instructions.trim()),
            guide_languages: baseText ? baseText.guide_languages : row.guide_languages,
            price_amount: toMinorUnits(row.price),
            group_size_max: row.capacity.trim() === '' ? null : Number(row.capacity),
            start_time: row.start_time.trim() === '' ? null : row.start_time.trim(),
            weekdays: row.weekdays.length > 0 ? [...new Set(row.weekdays)].sort((a, b) => a - b) : null,
          };
        }),
      };
      const res = await fetch(`${getApiBaseUrl()}/api/partner/tours/${id}`, {
        method: 'PUT',
        headers: {
          Authorization: token ? `Bearer ${token}` : '',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        if (res.status === 422) {
          if (isRecord(json) && typeof json.error_code === 'string' && json.error_code === 'option_structure_change_not_supported') {
            setRevisionNotice('error', revisionT('structureChangeBlocked'));
          } else if (isRecord(json.errors)) {
            setFieldErrors(revisionFieldErrors(json.errors as Record<string, string[]>));
            // Field-level feedback lands in the focused legacy summary.
            setRevisionStatus(null);
          } else {
            setRevisionNotice('error', revisionT('errors.generic'));
          }
        } else if (res.status === 403) {
          setRevisionNotice('error', revisionT('errors.permission'));
        } else {
          setRevisionNotice('error', revisionT('errors.generic'));
        }
        return;
      }
      // Refresh ONLY the commercial baseline: every unsaved governed value
      // (source text, media, presentation, option text) stays editable.
      const commercialBaseline: Record<number, OptionCommercialBaselineEntry> = { ...(optionCommercialBaseline ?? {}) };
      for (const row of submittedRows) {
        if (row.server_id === undefined) continue;
        commercialBaseline[row.server_id] = {
          price_amount: toMinorUnits(row.price),
          group_size_max: row.capacity.trim() === '' ? null : Number(row.capacity),
          start_time: row.start_time.trim() === '' ? null : row.start_time.trim(),
          weekdays: row.weekdays.length > 0 ? [...new Set(row.weekdays)].sort((a, b) => a - b) : null,
        };
      }
      setOptionCommercialBaseline(commercialBaseline);
      setRevisionNotice('info', revisionT('operationalSaved'));
    } catch {
      setRevisionNotice('error', revisionT('errors.generic'));
    } finally {
      setSavingOperational(false);
    }
  };

  /** General save routing for a published tour. Mixed dirty (governed AND
   *  commercial) makes NO request: values are retained with explicit
   *  instructions so nothing is silently dropped or partially applied. */
  const routePublishedSave = () => {
    if (publishedGating.structuralDirty) {
      setRevisionNotice('error', revisionT('structureChangeBlocked'));
      return;
    }
    if (publishedGating.governedDirty && publishedGating.commercialDirty) {
      setRevisionNotice('info', revisionT('mixedBlocked'));
      return;
    }
    if (publishedGating.governedDirty) {
      void handleSaveContentRevision();
      return;
    }
    if (publishedGating.commercialDirty) {
      void handleSaveOperationalChanges();
      return;
    }
    setRevisionNotice('info', revisionT('nothingToSave'));
  };

  const handleSubmitCandidateRevision = async () => {
    if (!candidate || !candidate.loaded) {
      setRevisionNotice('error', revisionT('errors.candidateLoadFailed'));
      return;
    }
    setSubmittingCandidate(true);
    setRevisionStatus(null);
    try {
      const res = await submitTourRevision(id, candidate.id);
      if (!res.ok) {
        handleRevisionFailure(res, res);
        return;
      }
      setCandidate((prev) => (prev ? { ...prev, state: 'proposed', loaded: true } : prev));
      setRevisionNotice('info', revisionT('submitted', { number: candidate.revisionNumber }));
      await refreshGovernanceState();
    } finally {
      setSubmittingCandidate(false);
    }
  };

  const handleWithdrawCandidate = async () => {
    if (!candidate) return;
    setWithdrawingCandidate(true);
    setRevisionStatus(null);
    try {
      const res = await withdrawTourRevision(id, candidate.id);
      if (!res.ok) {
        handleRevisionFailure(res, res);
        return;
      }
      setCandidate(null);
      setShowReloadCandidate(false);
      setRevisionNotice('info', revisionT('withdrawn', { number: candidate.revisionNumber }));
      await refreshGovernanceState();
    } finally {
      setWithdrawingCandidate(false);
    }
  };

  const handleRespondToRequest = async (requestId: number, responseNote: string) => {
    const currentGovernance = governance;
    const request = currentGovernance?.open_requests.find((req) => req.id === requestId);
    if (!currentGovernance || !request) {
      setRevisionNotice('error', revisionT('errors.requestNotOpen'));
      return;
    }
    const respondNote = responseNote.trim().slice(0, 2000);
    let body: TourRevisionResponsePayload;
    if (request.context === 'initial_review') {
      // Initial context answers with the observed generation epoch; no
      // revision ID is ever invented for never-public tours.
      body = { observed_generation_epoch: currentGovernance.generation_epoch, response_note: respondNote };
    } else {
      const linkable = candidate?.state === 'proposed' && candidate.loaded
        ? candidate.id
        : (currentGovernance.active_candidate?.state === 'proposed' ? currentGovernance.active_candidate.id : null);
      if (linkable === null) {
        setRevisionNotice('error', revisionT('respondNeedsSubmittedRevision'));
        return;
      }
      body = { revision_id: linkable, response_note: respondNote };
    }
    setRespondingRequest(true);
    setRevisionStatus(null);
    try {
      const res = await respondToTourChangeRequest(id, requestId, body);
      if (!res.ok) {
        if (res.errorCode === 'REQUEST_NOT_OPEN') {
          setRevisionNotice('error', revisionT('errors.requestNotOpen'));
          return;
        }
        handleRevisionFailure(res, res);
        return;
      }
      setRevisionNotice('info', revisionT('responded'));
      await refreshGovernanceState();
    } finally {
      setRespondingRequest(false);
    }
  };

  /** Explicit user action: load/refresh the open candidate payload so its
   *  values become the editing baseline (never automatic silent overwrite). */
  const reloadCurrentCandidate = async () => {
    if (!tour || !governance?.active_candidate) return;
    const active = governance.active_candidate;
    setCandidate({
      id: active.id,
      revisionNumber: active.revision_number,
      payloadHash: active.payload_hash,
      baseRevisionId: active.base_revision_id,
      state: active.state,
      loaded: false,
    });
    await applyCandidateRevisionRef.current(
      active.id,
      tour,
      (tour.options ?? []) as OwnedTourOption[],
      sourceLocale,
      media
    );
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
    // EGM-003: a published tour NEVER uses the legacy PUT for content edits.
    // The general save routes to the revision proposal or the operational-only
    // save; with both kinds dirty it makes no request at all, keeps every
    // value and shows localized instructions to save them separately.
    if (isPublishedTour) {
      routePublishedSave();
      return;
    }
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

      {/* EGM-003 fail-closed gate: a published tour without valid governance
      metadata never saves through the legacy PUT — retry only reloads the
      owned governance read. */}
      {isPublishedTour && governanceBlocked && (
        <div ref={governanceBlockedRef} tabIndex={-1} className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm space-y-2" role="alert" aria-live="assertive">
          <p>{revisionT('errors.governanceMissing')}</p>
          <button
            type="button"
            onClick={() => {
              void fetchTour();
            }}
            className="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition-colors"
          >
            {revisionT('errors.retry')}
          </button>
        </div>
      )}

      {revisionStatus && (
        <div
          ref={revisionStatusRef}
          tabIndex={-1}
          role={revisionStatus.tone === 'error' ? 'alert' : 'status'}
          aria-live={revisionStatus.tone === 'error' ? 'assertive' : 'polite'}
          className={`p-4 rounded-lg text-sm border ${
            revisionStatus.tone === 'error'
              ? 'bg-red-50 border-red-200 text-red-700'
              : 'bg-[#FFB800]/10 border-[#FFB800]/50 text-[#0A2540]'
          }`}
        >
          <p>{revisionStatus.message}</p>
          {showReloadCandidate && (
            <button
              type="button"
              onClick={() => {
                void reloadCurrentCandidate();
              }}
              className="mt-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-[#0A2540] hover:bg-gray-50 transition-colors"
            >
              {revisionT('errors.reloadCandidate')}
            </button>
          )}
        </div>
      )}

      {/* Revision workspace: approved content, the open candidate, open
      administrator change requests and the explicit separate saves. Rendered
      for every owned tour that exposes governance (draft tours show initial
      review requests); absent metadata on a draft keeps the legacy flow. */}
      {governance && !governanceBlocked && (
        <TourRevisionPanel
          governance={governance}
          isPublished={!!isPublishedTour}
          publicUrl={isPublishedTour && tour?.slug ? `/${locale}/tours/${tour.slug}` : null}
          loadedCandidateId={candidate?.loaded ? candidate.id : null}
          loadedCandidateState={candidate?.loaded ? candidate.state : null}
          linkableCandidateId={
            candidate?.state === 'proposed' && candidate.loaded
              ? candidate.id
              : (governance.active_candidate?.state === 'proposed' ? governance.active_candidate.id : null)
          }
          changeSummary={changeSummary}
          changeSummaryError={fieldErrors.change_summary}
          onChangeSummary={(value) => {
            setChangeSummary(value);
            setFieldErrors((prev) => {
              if (!('change_summary' in prev)) return prev;
              const next = { ...prev };
              delete next.change_summary;
              return next;
            });
          }}
          onSaveContent={() => {
            void handleSaveContentRevision();
          }}
          onOperationalSave={() => {
            void handleSaveOperationalChanges();
          }}
          onSubmitCandidate={() => {
            void handleSubmitCandidateRevision();
          }}
          onWithdraw={() => {
            void handleWithdrawCandidate();
          }}
          onRespond={(requestId, responseNote) => {
            void handleRespondToRequest(requestId, responseNote);
          }}
          savingContent={savingContent}
          savingOperational={savingOperational}
          submitting={submittingCandidate}
          withdrawing={withdrawingCandidate}
          responding={respondingRequest}
        />
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
          {/* The opaque editor snapshot restores never-published drafts only;
          a published tour reopens the immutable candidate instead. */}
          {!isPublishedTour && (
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
          )}

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
                // Published-tour structure lock (EGM-003): add/remove hidden;
                // authored text and price/schedule stay editable.
                structureDisabled={!!isPublishedTour}
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

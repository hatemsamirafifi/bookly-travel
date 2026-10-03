import { z } from 'zod';

export const pricingTierSchema = z.object({
  id: z.string(),
  name: z.string().min(1, { message: 'partner.tours.errors.tierNameRequired' }),
  price: z.string().refine((val) => !isNaN(Number(val)) && Number(val) > 0, {
    message: 'partner.tours.errors.tierPricePositive',
  }),
  currency: z.string().default('USD'),
  min_participants: z.number().int().min(1, { message: 'partner.tours.errors.minParticipantsPositive' }),
  max_participants: z.number().int().min(1, { message: 'partner.tours.errors.maxParticipantsPositive' }),
}).refine((data) => data.max_participants >= data.min_participants, {
  message: 'partner.tours.errors.maxParticipantsGteMin',
  path: ['max_participants'],
});

export const availabilityRuleSchema = z.object({
  id: z.string(),
  rule_type: z.enum(['daily', 'weekly', 'monthly']),
  days_of_week: z.array(z.number().min(0).max(6)),
  start_time: z.string().regex(/^\d{2}:\d{2}$/, { message: 'partner.tours.errors.invalidTime' }),
  start_date: z.string().min(1, { message: 'partner.tours.errors.startDateRequired' }),
  end_date: z.string().optional().or(z.literal('')),
  capacity: z.number().int().min(1, { message: 'partner.tours.errors.capacityMin' }),
});

export const availabilityExceptionSchema = z.object({
  id: z.string(),
  exception_type: z.enum(['blackout', 'specific']),
  date: z.string().min(1, { message: 'partner.tours.errors.dateRequired' }),
  start_time: z.string().regex(/^\d{2}:\d{2}$/, { message: 'partner.tours.errors.invalidTime' }),
  capacity: z.number().int().min(0),
  price_multiplier: z.string().refine((val) => !isNaN(Number(val)) && Number(val) >= 0, {
    message: 'partner.tours.errors.multiplierNonNegative',
  }),
  note: z.string().optional().or(z.literal('')),
});

export const tourMediaSchema = z.object({
  id: z.string().optional(),
  url: z.string().url({ message: 'partner.tours.errors.invalidUrl' }),
  is_cover: z.boolean(),
});

// Spec 019 source bounds (backend TourContentRules): day integer 1-30,
// titles required nonblank max 160, descriptions nullable max 2000, stops
// ordered array max 20 (absent allowed, explicit null rejected), durations
// nullable integers 1-1440 (fractional rejected, never floored).
const nonBlankTitle = (message: string) =>
  z.string()
    .min(1, { message })
    .max(160, { message: 'partner.tours.errors.titleMax160' })
    .regex(/\S/, { message });

export const tourItineraryStopSchema = z.object({
  title: nonBlankTitle('partner.tours.errors.stopTitleRequired'),
  description: z.string().max(2000, { message: 'partner.tours.errors.descriptionMax2000' }).optional().nullable(),
  duration_minutes: z.number().int({ message: 'partner.tours.errors.durationMinutesInteger' })
    .min(1, { message: 'partner.tours.errors.durationMinutesRange' })
    .max(1440, { message: 'partner.tours.errors.durationMinutesRange' })
    .optional().nullable(),
});

export const tourItineraryDaySchema = z.object({
  day: z.number().int({ message: 'partner.tours.errors.dayInteger' })
    .min(1, { message: 'partner.tours.errors.dayRange' })
    .max(30, { message: 'partner.tours.errors.dayRange' }),
  title: nonBlankTitle('partner.tours.errors.dayTitleRequired'),
  description: z.string().max(2000, { message: 'partner.tours.errors.descriptionMax2000' }).optional().nullable(),
  stops: z.array(tourItineraryStopSchema)
    .max(20, { message: 'partner.tours.errors.stopsMax' })
    .optional(),
});

export const tourItinerarySchema = z.array(tourItineraryDaySchema)
  .max(30, { message: 'partner.tours.errors.itineraryMax' });

// Nullable authored source lists: at most 30 strings of at most 500 chars;
// [] clears, omitted preserves (server semantics).
const sourceList = (listMessage: string) =>
  z.array(z.string().max(500, { message: 'partner.tours.errors.listItemMax' }))
    .max(30, { message: listMessage })
    .optional()
    .default([]);

// Basic details form step (combines basic and details steps). Draft-tolerant:
// optional source fields stay optional here; the stricter publication schema
// below (and the server submit guards) require trimmed English title and
// description before review.
export const tourBasicDetailsSchema = z.object({
  title: z
    .string()
    .min(1, { message: 'partner.tours.errors.titleRequired' })
    .max(120, { message: 'partner.tours.errors.titleMax' }),
  description: z.string().min(100, { message: 'partner.tours.errors.descriptionMin' })
    .max(5000, { message: 'partner.tours.errors.descriptionMax' }),
  category: z.string().min(1, { message: 'partner.tours.errors.categoryRequired' }),
  destination: z.string().min(1, { message: 'partner.tours.errors.destinationRequired' }),
  duration_value: z.string().refine((val) => !isNaN(Number(val)) && Number(val) > 0, {
    message: 'partner.tours.errors.durationPositive',
  }),
  duration_unit: z.enum(['hour', 'day']),
  difficulty_level: z.enum(['easy', 'moderate', 'challenging']),
  meeting_point: z.string().max(500, { message: 'partner.tours.errors.meetingPointMax' }).optional().or(z.literal('')),
  highlights: sourceList('partner.tours.errors.highlightsMax'),
  inclusions: sourceList('partner.tours.errors.inclusionsMax'),
  exclusions: sourceList('partner.tours.errors.exclusionsMax'),
  important_information: sourceList('partner.tours.errors.importantInfoMax'),
  itinerary: tourItinerarySchema,
  languages: z.array(z.string()).default([]),
  cancellation_policy: z.string().max(2000, { message: 'partner.tours.errors.cancellationMax' }).optional().or(z.literal('')),
});

// Publication requires trimmed nonempty English source plus the existing
// 100-character draft minimum; incomplete drafts stay saveable above.
export const tourPublishSourceSchema = z.object({
  title: z.string().trim().min(1, { message: 'partner.tours.errors.titleRequired' })
    .max(120, { message: 'partner.tours.errors.titleMax' }),
  description: z.string().trim().min(100, { message: 'partner.tours.errors.descriptionMin' })
    .max(5000, { message: 'partner.tours.errors.descriptionMax' }),
});

export const tourMediaStepSchema = z.object({
  media: z.array(tourMediaSchema).refine((media) => media.some((m) => m.is_cover), {
    message: 'partner.tours.errors.coverImageRequired',
  }),
});

export const tourPricingStepSchema = z.object({
  pricing_tiers: z.array(pricingTierSchema).min(1, { message: 'partner.tours.errors.pricingTierMin' }),
  group_size_min: z.number().int().min(1),
  group_size_max: z.number().int().min(1),
}).refine((data) => data.group_size_max >= data.group_size_min, {
  message: 'partner.tours.errors.maxParticipantsGteMin',
  path: ['group_size_max'],
});

export const tourAvailabilityStepSchema = z.object({
  availability_rules: z.array(availabilityRuleSchema).min(1, { message: 'partner.tours.errors.availabilityRuleMin' }),
  availability_exceptions: z.array(availabilityExceptionSchema).default([]),
});

// Full tour wizard schema for final submission
export const tourWizardSchema = tourBasicDetailsSchema
  .merge(tourMediaStepSchema)
  .merge(tourPricingStepSchema)
  .merge(tourAvailabilityStepSchema);

/**
 * Map a Laravel 422 `errors` object to flat form-field paths, stripping the
 * canonical `translations.en.` prefix so nested server paths
 * (`translations.en.itinerary.0.title`) land on the actual authoring field
 * (`itinerary.0.title`). First message per path wins; messages stay raw
 * here and are localized per field via `localizeServerFieldError`.
 */
export function mapServerErrorsToFields(errors: Record<string, string[]>): Record<string, string> {
  const fields: Record<string, string> = {};
  for (const [path, messages] of Object.entries(errors)) {
    const formPath = path.startsWith('translations.en.')
      ? path.slice('translations.en.'.length)
      : path;
    if (messages.length > 0 && !(formPath in fields)) {
      fields[formPath] = messages[0];
    }
  }
  return fields;
}

/**
 * Collect the messages stored under a base field path, including indexed
 * descendants (`highlights` plus `highlights.0`, ...), for display under
 * their shared source control. Exact-path matches come first.
 */
export function pickFieldErrors(
  errors: Record<string, string>,
  basePath: string
): string[] {
  const exact = errors[basePath];
  const indexed = Object.entries(errors)
    .filter(([path]) => path.startsWith(`${basePath}.`))
    .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
    .map(([, message]) => message);
  return exact !== undefined ? [exact, ...indexed] : indexed;
}

type TranslateFn = (key: string) => string;

interface ServerErrorMapping {
  test: (formPath: string, raw: string) => boolean;
  keyFor: (formPath: string) => string;
}

const fixedKey = (key: string): ((formPath: string) => string) => () => key;

const LIST_MAX_KEYS: Record<string, string> = {
  highlights: 'partner.tours.errors.highlightsMax',
  inclusions: 'partner.tours.errors.inclusionsMax',
  exclusions: 'partner.tours.errors.exclusionsMax',
  important_information: 'partner.tours.errors.importantInfoMax',
};

const SOURCE_LIST_NAMES = 'highlights|inclusions|exclusions|important_information';
const SOURCE_LIST_PATTERN = `^(?:${SOURCE_LIST_NAMES})$`;
const SOURCE_LIST_ITEM_PATTERN = `^(?:${SOURCE_LIST_NAMES})\\.\\d+$`;

/** Scoped localization for known server 422 paths: exact form path plus a
 *  catalog key, so ES/IT authors see page-locale feedback instead of raw
 *  English. Unmapped paths fall back to the raw server message. */
const SERVER_ERROR_MAPPINGS: ServerErrorMapping[] = [
  { test: (p, m) => p === 'title' && /required/i.test(m), keyFor: fixedKey('partner.tours.errors.titleRequired') },
  { test: (p, m) => p === 'title' && /greater than/i.test(m), keyFor: fixedKey('partner.tours.errors.titleMax') },
  { test: (p, m) => p === 'description' && /at least/i.test(m), keyFor: fixedKey('partner.tours.errors.descriptionMin') },
  { test: (p, m) => p === 'description' && /greater than/i.test(m), keyFor: fixedKey('partner.tours.errors.descriptionMax') },
  {
    test: (p, m) => new RegExp(SOURCE_LIST_ITEM_PATTERN).test(p) && /greater than/i.test(m),
    keyFor: fixedKey('partner.tours.errors.listItemMax'),
  },
  {
    // Laravel array-max messages read "must not have more than N items".
    // test() already pinned the full path, so it is the list name itself.
    test: (p, m) => new RegExp(SOURCE_LIST_PATTERN).test(p) && /greater than|more than|at most|maximum/i.test(m),
    keyFor: (formPath) => LIST_MAX_KEYS[formPath] ?? 'partner.tours.errors.invalidList',
  },
  { test: (p, m) => new RegExp(SOURCE_LIST_PATTERN).test(p) && /must be a list/i.test(m), keyFor: fixedKey('partner.tours.errors.invalidList') },
  { test: (p, m) => p === 'meeting_point' && /greater than/i.test(m), keyFor: fixedKey('partner.tours.errors.meetingPointMax') },
  { test: (p, m) => p === 'cancellation_policy' && /greater than/i.test(m), keyFor: fixedKey('partner.tours.errors.cancellationMax') },
  { test: (p, m) => p === 'itinerary' && /greater than|more than/i.test(m), keyFor: fixedKey('partner.tours.errors.itineraryMax') },
  { test: (p, m) => p === 'itinerary' && /must be a list/i.test(m), keyFor: fixedKey('partner.tours.errors.invalidList') },
  { test: (p, m) => /^itinerary\.\d+\.day$/.test(p) && /an integer|whole/i.test(m), keyFor: fixedKey('partner.tours.errors.dayInteger') },
  { test: (p) => /^itinerary\.\d+\.day$/.test(p), keyFor: fixedKey('partner.tours.errors.dayRange') },
  {
    test: (p, m) => /^itinerary\.\d+\.title$/.test(p) && /greater than/i.test(m),
    keyFor: fixedKey('partner.tours.errors.titleMax160'),
  },
  { test: (p) => /^itinerary\.\d+\.title$/.test(p), keyFor: fixedKey('partner.tours.errors.dayTitleRequired') },
  {
    test: (p, m) => /^itinerary\.\d+\.stops\.\d+\.title$/.test(p) && /greater than/i.test(m),
    keyFor: fixedKey('partner.tours.errors.titleMax160'),
  },
  { test: (p) => /^itinerary\.\d+\.stops\.\d+\.title$/.test(p), keyFor: fixedKey('partner.tours.errors.stopTitleRequired') },
  {
    test: (p, m) => /^itinerary\.\d+\.(description|stops\.\d+\.description)$/.test(p) && /greater than/i.test(m),
    keyFor: fixedKey('partner.tours.errors.descriptionMax2000'),
  },
  { test: (p, m) => /stops$/.test(p) && /greater than|more than/i.test(m), keyFor: fixedKey('partner.tours.errors.stopsMax') },
  { test: (p, m) => /stops$/.test(p) && /must be a list|must be an array/i.test(m), keyFor: fixedKey('partner.tours.errors.invalidList') },
  {
    test: (p, m) => /duration_minutes$/.test(p) && /an integer|whole/i.test(m),
    keyFor: fixedKey('partner.tours.errors.durationMinutesInteger'),
  },
  { test: (p) => /duration_minutes$/.test(p), keyFor: fixedKey('partner.tours.errors.durationMinutesRange') },
];

export function localizeServerFieldError(
  formPath: string,
  rawMessage: string,
  t: TranslateFn
): string {
  for (const mapping of SERVER_ERROR_MAPPINGS) {
    if (mapping.test(formPath, rawMessage)) {
      return t(mapping.keyFor(formPath));
    }
  }
  return rawMessage;
}

/** Localize a whole mapped server-error object for page-locale display. */
export function localizeServerFieldErrors(
  errors: Record<string, string>,
  t: TranslateFn
): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors).map(([path, message]) => [path, localizeServerFieldError(path, message, t)])
  );
}

import {
  tourBasicDetailsSchema,
  tourMediaStepSchema,
  tourPricingStepSchema,
  tourAvailabilityStepSchema,
  tourPublishSourceSchema,
  mapServerErrorsToFields,
  localizeServerFieldError,
  pickFieldErrors,
  pricingTierSchema,
  availabilityRuleSchema,
} from '../partner';
import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

const validDetails = {
  title: 'Tuscan Wine Tasting',
  description: 'A full day touring vineyards with expert guides, including traditional tastings and local stories in Tuscany.',
  category: 'food',
  destination: 'Tuscany',
  duration_value: '8',
  duration_unit: 'hour' as const,
  difficulty_level: 'easy' as const,
  meeting_point: 'Piazza del Campo',
  highlights: ['Old-town route'],
  inclusions: ['Live guide'],
  exclusions: ['Lunch'],
  important_information: ['Comfortable shoes recommended'],
  cancellation_policy: 'Cancel up to 24 hours in advance.',
  languages: ['en', 'de'],
  itinerary: [],
};

describe('tourBasicDetailsSchema', () => {
  it('accepts a complete, valid details payload', () => {
    expect(tourBasicDetailsSchema.safeParse(validDetails).success).toBe(true);
  });

  it('rejects an empty title', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, title: '' });
    expect(r.success).toBe(false);
  });

  it('rejects a title over 120 characters', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, title: 'x'.repeat(121) });
    expect(r.success).toBe(false);
  });

  it('rejects a description shorter than the backend 100-character minimum', () => {
    const r = tourBasicDetailsSchema.safeParse({ ...validDetails, description: 'x'.repeat(99) });
    expect(r.success).toBe(false);
  });

  it('rejects an incomplete itinerary day before submission', () => {
    const result = tourBasicDetailsSchema.safeParse({ ...validDetails, itinerary: [{ day: 1, title: '', stops: [] }] });
    expect(result.success).toBe(false);
  });

  it('rejects an empty category and destination', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, category: '' }).success
    ).toBe(false);
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, destination: '' }).success
    ).toBe(false);
  });

  it('rejects a non-positive duration', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, duration_value: '0' }).success
    ).toBe(false);
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, duration_value: '-3' }).success
    ).toBe(false);
  });

  it('allows an empty meeting point on drafts (nullable server source)', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, meeting_point: '' }).success
    ).toBe(true);
  });

  it('rejects an over-long meeting point', () => {
    expect(
      tourBasicDetailsSchema.safeParse({ ...validDetails, meeting_point: 'x'.repeat(501) }).success
    ).toBe(false);
  });
});

describe('tourBasicDetailsSchema source fields (Spec 019)', () => {
  it('preserves source lists, ordering and nullable meeting point', () => {
    const r = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      highlights: ['b', 'a'],
      inclusions: [],
      meeting_point: undefined,
      cancellation_policy: '',
    });
    expect(r.success).toBe(true);
    if (r.success) {
      expect(r.data.highlights).toEqual(['b', 'a']);
      expect(r.data.inclusions).toEqual([]);
    }
  });

  it('rejects over-limit source lists and items with field paths', () => {
    const r = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      highlights: Array.from({ length: 31 }, () => 'ok'),
      inclusions: ['x'.repeat(501)],
      important_information: Array.from({ length: 31 }, () => 'ok'),
      cancellation_policy: 'x'.repeat(2001),
    });
    expect(r.success).toBe(false);
    if (!r.success) {
      const paths = r.error.issues.map((i) => i.path.join('.'));
      expect(paths).toContain('highlights');
      expect(paths).toContain('inclusions.0');
      expect(paths).toContain('important_information');
      expect(paths).toContain('cancellation_policy');
    }
  });

  it('rejects blank day and stop titles while keeping ordered days', () => {
    const blank = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [
        { day: 1, title: '   ', stops: [] },
        { day: 2, title: 'Valid', stops: [{ title: '' }] },
      ],
    });
    expect(blank.success).toBe(false);

    const ordered = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [
        { day: 2, title: 'Second', stops: [] },
        { day: 1, title: 'First', stops: [] },
        { day: 2, title: 'Repeated day stays valid', stops: [] },
      ],
    });
    expect(ordered.success).toBe(true);
    if (ordered.success) {
      expect(ordered.data.itinerary.map((d) => d.day)).toEqual([2, 1, 2]);
    }
  });

  it('bounds numeric and null durations without flooring fractional input', () => {
    const base = { ...validDetails, itinerary: [{ day: 1, title: 'Day', stops: [{ title: 'Stop', duration_minutes: null }] }] };
    expect(tourBasicDetailsSchema.safeParse(base).success).toBe(true);

    const fractional = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [{ day: 1, title: 'Day', stops: [{ title: 'Stop', duration_minutes: 1.5 }] }],
    });
    expect(fractional.success).toBe(false);

    for (const duration of [0, 1441]) {
      const r = tourBasicDetailsSchema.safeParse({
        ...validDetails,
        itinerary: [{ day: 1, title: 'Day', stops: [{ title: 'Stop', duration_minutes: duration }] }],
      });
      expect(r.success).toBe(false);
    }

    const bounds = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [{ day: 30, title: 'Day', stops: [{ title: 'Stop', duration_minutes: 1440 }] }],
    });
    expect(bounds.success).toBe(true);
  });

  it('rejects explicit null stops and fractional day numbers', () => {
    const rawNullStops: unknown = JSON.parse('{"day":1,"title":"Day","stops":null}');
    const nullStops = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [rawNullStops],
    });
    expect(nullStops.success).toBe(false);

    const fractionalDay = tourBasicDetailsSchema.safeParse({
      ...validDetails,
      itinerary: [{ day: 1.5, title: 'Day', stops: [] }],
    });
    expect(fractionalDay.success).toBe(false);
  });
});

describe('tourPublishSourceSchema (Spec 019 draft vs publication)', () => {
  it('requires trimmed nonempty title and the 100-character description', () => {
    expect(tourPublishSourceSchema.safeParse({ title: '   ', description: validDetails.description }).success).toBe(false);
    expect(tourPublishSourceSchema.safeParse({ title: 'Valid', description: 'x'.repeat(99) }).success).toBe(false);
    expect(tourPublishSourceSchema.safeParse({ title: 'Valid', description: validDetails.description }).success).toBe(true);
  });
});

describe('mapServerErrorsToFields (Spec 019 nested errors)', () => {
  it('strips the canonical translations.en prefix onto authoring fields', () => {
    expect(mapServerErrorsToFields({
      'translations.en.itinerary.0.title': ['Day title is required.'],
      'translations.en.meeting_point': ['Too long.'],
      category: ['Unknown category.'],
    })).toEqual({
      'itinerary.0.title': 'Day title is required.',
      meeting_point: 'Too long.',
      category: 'Unknown category.',
    });
  });
});

/** Read a dotted catalog path through an unknown boundary (no casts). */
function catalogText(catalog: unknown, path: string): string {
  const parts = path.split('.');
  let node: unknown = catalog;
  for (const part of parts) {
    if (typeof node !== 'object' || node === null || !(part in node)) {
      return path;
    }
    node = (node as Record<string, unknown>)[part];
  }
  return typeof node === 'string' ? node : path;
}

describe('localizeServerFieldError + pickFieldErrors (Spec 019 i18n)', () => {
  const catalogs: Record<'en' | 'es' | 'it', unknown> = { en: enMessages, es: esMessages, it: itMessages };

  it.each(['en', 'es', 'it'] as const)('localizes known server paths in %s', (locale) => {
    const t = (key: string) => catalogText(catalogs[locale], key);
    expect(localizeServerFieldError(
      'itinerary.0.title',
      'The translations.en.itinerary.0.title field is required.',
      t
    )).toBe(catalogText(catalogs[locale], 'partner.tours.errors.dayTitleRequired'));
    expect(localizeServerFieldError(
      'itinerary.0.stops.1.duration_minutes',
      'The translations.en.itinerary.0.stops.1.duration_minutes must be an integer.',
      t
    )).toBe(catalogText(catalogs[locale], 'partner.tours.errors.durationMinutesInteger'));
    expect(localizeServerFieldError(
      'highlights.2',
      'The translations.en.highlights.2 must not be greater than 500 characters.',
      t
    )).toBe(catalogText(catalogs[locale], 'partner.tours.errors.listItemMax'));
    // Real Laravel array-max wording ("more than N items").
    expect(localizeServerFieldError(
      'highlights',
      'The translations.en.highlights must not have more than 30 items.',
      t
    )).toBe(catalogText(catalogs[locale], 'partner.tours.errors.highlightsMax'));
    // Real catalog messages, not key echoes.
    expect(catalogText(catalogs[locale], 'partner.tours.errors.dayTitleRequired'))
      .not.toContain('partner.tours.errors.');
  });

  it('falls back to the raw server message for unmapped paths', () => {
    const t = (key: string) => key;
    expect(localizeServerFieldError('category', 'Unknown category: foo', t))
      .toBe('Unknown category: foo');
  });

  it('collects exact and indexed list errors under their shared control', () => {
    const errors = {
      highlights: 'Too many highlights.',
      'highlights.0': 'Item one too long.',
      'highlights.2': 'Item three too long.',
      title: 'Title is required.',
    };
    expect(pickFieldErrors(errors, 'highlights')).toEqual([
      'Too many highlights.',
      'Item one too long.',
      'Item three too long.',
    ]);
    expect(pickFieldErrors(errors, 'title')).toEqual(['Title is required.']);
    expect(pickFieldErrors(errors, 'missing')).toEqual([]);
  });
});

describe('pricingTierSchema', () => {
  const validTier = {
    id: '1',
    name: 'Adult',
    price: '50',
    currency: 'USD',
    min_participants: 1,
    max_participants: 10,
  };

  it('accepts a valid tier', () => {
    expect(pricingTierSchema.safeParse(validTier).success).toBe(true);
  });

  it('rejects a non-positive price', () => {
    expect(pricingTierSchema.safeParse({ ...validTier, price: '0' }).success).toBe(false);
    expect(pricingTierSchema.safeParse({ ...validTier, price: '-5' }).success).toBe(false);
  });

  it('rejects max_participants < min_participants', () => {
    const r = pricingTierSchema.safeParse({ ...validTier, min_participants: 5, max_participants: 2 });
    expect(r.success).toBe(false);
  });
});

describe('tourPricingStepSchema', () => {
  it('requires at least one pricing tier', () => {
    const r = tourPricingStepSchema.safeParse({
      pricing_tiers: [],
      group_size_min: 1,
      group_size_max: 10,
    });
    expect(r.success).toBe(false);
  });

  it('keeps tour-level group sizes under the API contract names', () => {
    const r = tourPricingStepSchema.safeParse({
      pricing_tiers: [{
        id: '1',
        name: 'Adult',
        price: '50',
        currency: 'USD',
        min_participants: 1,
        max_participants: 10,
      }],
      group_size_min: 2,
      group_size_max: 7,
    });
    expect(r.success).toBe(true);
    if (r.success) {
      expect(r.data.group_size_min).toBe(2);
      expect(r.data.group_size_max).toBe(7);
    }
  });
});

describe('tourMediaStepSchema', () => {
  it('requires at least one cover image', () => {
    const r = tourMediaStepSchema.safeParse({
      media: [{ id: '1', url: 'https://example.com/a.png', is_cover: false }],
    });
    expect(r.success).toBe(false);
  });

  it('accepts media with a cover image', () => {
    const r = tourMediaStepSchema.safeParse({
      media: [
        { id: '1', url: 'https://example.com/a.png', is_cover: true },
        { id: '2', url: 'https://example.com/b.png', is_cover: false },
      ],
    });
    expect(r.success).toBe(true);
  });
});

describe('availabilityRuleSchema', () => {
  const validRule = {
    id: '1',
    rule_type: 'weekly' as const,
    days_of_week: [1],
    start_time: '09:00',
    start_date: '2026-01-01',
    capacity: 10,
  };

  it('accepts a valid rule', () => {
    expect(availabilityRuleSchema.safeParse(validRule).success).toBe(true);
  });

  it('rejects a malformed start time', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, start_time: '9:00' }).success
    ).toBe(false);
  });

  it('rejects a missing start date', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, start_date: '' }).success
    ).toBe(false);
  });

  it('rejects capacity below 1', () => {
    expect(
      availabilityRuleSchema.safeParse({ ...validRule, capacity: 0 }).success
    ).toBe(false);
  });
});

describe('tourAvailabilityStepSchema', () => {
  it('requires at least one availability rule', () => {
    const r = tourAvailabilityStepSchema.safeParse({
      availability_rules: [],
      availability_exceptions: [],
    });
    expect(r.success).toBe(false);
  });
});

import {
  createBrokenMediaTourDetail,
  createFallbackTourDetail,
  createFullTourDetail,
  createIntentionalEmptyItineraryTourDetail,
  createSparseTourDetail,
  createUnavailableTourDetail,
} from '../../../../tests/fixtures/tour-detail';
import {
  getDetailPresentation,
  getDetailSections,
  getGuideLanguageNames,
  getPresentSectionIds,
  hasDifficulty,
  hasGenuineReviewCount,
  hasUsableEntries,
  hasUsableItinerary,
  isGenuineReviewScore,
  isUsableText,
  resolveImageAltLocale,
  shouldShowContentFallback,
  shouldShowItineraryFallback,
} from '../detail-presentation';

const ORDERED_IDS = [
  'overview',
  'facts',
  'highlights',
  'itinerary',
  'included',
  'excluded',
  'meeting',
  'cancellation',
  'important',
  'operator',
  'reviews',
  'related',
] as const;

describe('tour-detail fixtures', () => {
  it('shares no nested state between fixtures built from the same overrides', () => {
    const now = new Date('2026-10-14T00:00:00.000Z');
    const overrides = { highlights: ['Shared highlight'], group_size: { min: 2, max: 8 } };
    const first = createFullTourDetail(now, overrides);
    const second = createFullTourDetail(now, overrides);

    first.highlights.push('Mutated');
    first.group_size.min = 99;

    expect(second.highlights).toEqual(['Shared highlight']);
    expect(second.group_size).toEqual({ min: 2, max: 8 });
    expect(overrides.highlights).toEqual(['Shared highlight']);
    expect(overrides.group_size).toEqual({ min: 2, max: 8 });
  });
});

describe('detail-presentation section projection', () => {
  it('lists every supplied section for a complete tour in the prescribed order', () => {
    expect(getPresentSectionIds(createFullTourDetail())).toEqual([...ORDERED_IDS]);
  });

  it('keeps only overview and facts for a sparse tour', () => {
    expect(getPresentSectionIds(createSparseTourDetail())).toEqual(['overview', 'facts']);
  });

  it('exposes the same ordered ids with present flags', () => {
    const sections = getDetailSections(createFullTourDetail());
    expect(sections.map((section) => section.id)).toEqual([...ORDERED_IDS]);
    expect(sections.every((section) => section.present)).toBe(true);

    const sparse = getDetailSections(createSparseTourDetail());
    expect(sparse.map((section) => section.id)).toEqual([...ORDERED_IDS]);
    expect(sparse.filter((section) => section.present).map((section) => section.id)).toEqual([
      'overview',
      'facts',
    ]);
  });

  it('never invents gallery/header entries; they are layout slots', () => {
    for (const tour of [createFullTourDetail(), createSparseTourDetail(), createUnavailableTourDetail()]) {
      expect(getPresentSectionIds(tour)).not.toContain('gallery');
      expect(getPresentSectionIds(tour)).not.toContain('header');
    }
  });

  it('treats whitespace-only optional text as absent', () => {
    expect(isUsableText('   ')).toBe(false);
    expect(isUsableText('\n\t ')).toBe(false);
    expect(isUsableText('Colosseum main entrance')).toBe(true);

    const ids = getPresentSectionIds(
      createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
        highlights: [],
        inclusions: [],
        exclusions: [],
        important_information: [],
        itinerary: [],
        meeting_point: '   ',
        cancellation_policy: '\n\t ',
        operator: null,
        reviews: { average_rating: 0, count: 0, distribution: {} },
        related_tours: [],
      }),
    );
    expect(ids).not.toContain('meeting');
    expect(ids).not.toContain('cancellation');
  });

  it('requires actual usable array entries, not mere length', () => {
    expect(hasUsableEntries(['  ', '', null, undefined])).toBe(false);
    expect(hasUsableEntries(['Hidden courtyards', '  '])).toBe(true);
    expect(hasUsableEntries(undefined)).toBe(false);

    const ids = getPresentSectionIds(
      createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
        highlights: ['   '],
        inclusions: [],
        exclusions: [],
      }),
    );
    expect(ids).not.toContain('highlights');
  });

  it('suppresses the scored review section when the count is zero', () => {
    const tour = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
      reviews: { average_rating: 0, count: 0, distribution: {} },
      rating: { average: 0, count: 0 },
    });
    expect(getPresentSectionIds(tour)).not.toContain('reviews');
  });

  it('suppresses the reviews section for fractional counts like the header rule', () => {
    // Same-projection contract: section/nav presence uses the genuine-count
    // rule, so a fractional count shows neither section nor header score.
    for (const count of [1.5, 2.5]) {
      const tour = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
        reviews: { average_rating: 4.5, count, distribution: {} },
        rating: { average: 4.5, count },
      });
      expect(getPresentSectionIds(tour)).not.toContain('reviews');
    }
  });

  it('accepts only a positive integer review count as genuine', () => {
    expect(hasGenuineReviewCount(2)).toBe(true);
    expect(hasGenuineReviewCount(124)).toBe(true);
    expect(hasGenuineReviewCount(0)).toBe(false);
    expect(hasGenuineReviewCount(-3)).toBe(false);
    expect(hasGenuineReviewCount(2.5)).toBe(false);
    expect(hasGenuineReviewCount(Number.NaN)).toBe(false);
    expect(hasGenuineReviewCount('2')).toBe(false);
    expect(hasGenuineReviewCount(undefined)).toBe(false);
  });

  it('scores only a genuine count with a finite average in (0, 5]', () => {
    expect(isGenuineReviewScore(2, 4.5)).toBe(true);
    expect(isGenuineReviewScore(124, 4.7)).toBe(true);
    expect(isGenuineReviewScore(0, 4.5)).toBe(false);
    expect(isGenuineReviewScore(-2, 4.5)).toBe(false);
    expect(isGenuineReviewScore(2.5, 4.5)).toBe(false);
    expect(isGenuineReviewScore(10, 0)).toBe(false);
    expect(isGenuineReviewScore(10, -1)).toBe(false);
    expect(isGenuineReviewScore(10, 5.5)).toBe(false);
    expect(isGenuineReviewScore(10, Number.NaN)).toBe(false);
    expect(isGenuineReviewScore(10, null)).toBe(false);
    expect(isGenuineReviewScore(10, undefined)).toBe(false);
  });

  it('marks the operator section only when a summary is supplied', () => {
    expect(getPresentSectionIds(createFullTourDetail())).toContain('operator');
    const tour = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), { operator: null });
    expect(getPresentSectionIds(tour)).not.toContain('operator');
  });

  it('omits a null difficulty and keeps a supplied one', () => {
    expect(hasDifficulty(createSparseTourDetail())).toBe(false);
    expect(hasDifficulty(createFullTourDetail())).toBe(true);
  });

  it('yields no itinerary section for an empty itinerary', () => {
    expect(hasUsableItinerary([])).toBe(false);
    expect(hasUsableItinerary([{ day: 1, title: '   ', stops: [] }])).toBe(false);
    expect(getPresentSectionIds(createSparseTourDetail())).not.toContain('itinerary');
  });

  it('yields no section or notice for an intentionally empty translated itinerary', () => {
    for (const pageLocale of ['es', 'it'] as const) {
      const tour = createIntentionalEmptyItineraryTourDetail(pageLocale);
      expect(getPresentSectionIds(tour)).not.toContain('itinerary');
      expect(shouldShowItineraryFallback(tour, pageLocale)).toBe(false);
      expect(shouldShowContentFallback(tour, pageLocale)).toBe(false);
    }
  });
});

describe('detail-presentation language disclosure', () => {
  it('discloses English general content on a non-English page', () => {
    const tour = createFallbackTourDetail('es');
    expect(shouldShowContentFallback(tour, 'es')).toBe(true);
    expect(shouldShowItineraryFallback(tour, 'es')).toBe(true);
  });

  it('requires a nonempty itinerary for the itinerary notice', () => {
    const tour = createFallbackTourDetail('es');
    tour.itinerary = [];
    expect(shouldShowItineraryFallback(tour, 'es')).toBe(false);
  });

  it('discloses general and itinerary languages independently', () => {
    const tour = createIntentionalEmptyItineraryTourDetail('es');
    tour.itinerary = [{ day: 1, title: 'English day', stops: [] }];
    tour.itinerary_locale = 'en';

    expect(shouldShowContentFallback(tour, 'es')).toBe(false);
    expect(shouldShowItineraryFallback(tour, 'es')).toBe(true);
  });

  it('shows no notice when content already matches the page locale', () => {
    const tour = createIntentionalEmptyItineraryTourDetail('it');
    expect(shouldShowContentFallback(tour, 'it')).toBe(false);
    expect(shouldShowItineraryFallback(tour, 'it')).toBe(false);
  });

  it('never derives a notice from the summary flag alone', () => {
    const tour = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
      content_locale: 'es',
      itinerary_locale: 'es',
      translation_warning: 'partial_translation',
    });
    expect(shouldShowContentFallback(tour, 'es')).toBe(false);
    expect(shouldShowItineraryFallback(tour, 'es')).toBe(false);
  });

  it('keeps the English page quiet even with the summary flag', () => {
    const tour = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'), {
      translation_warning: 'partial_translation',
    });
    expect(shouldShowContentFallback(tour, 'en')).toBe(false);
    expect(shouldShowItineraryFallback(tour, 'en')).toBe(false);
  });

  it('treats broken media as fixture state without inventing sections', () => {
    const tour = createBrokenMediaTourDetail();
    expect(tour.images).toHaveLength(5);
    expect(getPresentSectionIds(tour)).toEqual([...ORDERED_IDS]);
  });
});

describe('detail-presentation spoken languages and image locale', () => {
  it('localizes guide spoken codes independently of content locale', () => {
    const names = getGuideLanguageNames(['de', 'en', 'es'], 'es');
    expect(names).toHaveLength(3);
    expect(names).not.toEqual(['de', 'en', 'es']);
    expect(names.join(', ')).toMatch(/Alemán/);
  });

  it('falls back to the raw code for ill-formed guide languages', () => {
    expect(getGuideLanguageNames(['not a code!'], 'en')).toEqual(['not a code!']);
  });

  it('returns raw codes for a malformed page locale', () => {
    expect(getGuideLanguageNames(['de', 'en'], 'en!')).toEqual(['de', 'en']);
  });

  it('keeps an explicit image alt locale and falls back to the content locale', () => {
    expect(
      resolveImageAltLocale({ url: 'https://cdn.test/a.jpg', is_cover: true, alt: 'Rome', alt_locale: 'en' }, 'es'),
    ).toBe('en');
    expect(
      resolveImageAltLocale({ url: 'https://cdn.test/a.jpg', is_cover: true, alt: 'Roma' }, 'es'),
    ).toBe('es');
  });

  it('composes sections and disclosure in one projection', () => {
    const tour = createFallbackTourDetail('it');
    const presentation = getDetailPresentation(tour, 'it');
    expect(presentation.presentSectionIds).toEqual(getPresentSectionIds(tour));
    expect(presentation.disclosure).toEqual({
      showContentFallback: true,
      showItineraryFallback: true,
    });
  });
});

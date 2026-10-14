import type { TourDetail, TourImage, TourItineraryDay } from '../api/types';

/**
 * Pure detail presentation model.
 *
 * Ordered section-presence and actual-language disclosure for the existing
 * TourDetail contract. No fetching, no store, no invented facts: optional
 * text counts only when trimmed nonempty, arrays only with usable entries,
 * and the itinerary fallback additionally requires a nonempty itinerary.
 * Gallery/header stay layout slots, never section entries.
 */

/** Stable section ids; existing route hash meanings are preserved. */
export type DetailSectionId =
  | 'overview'
  | 'facts'
  | 'highlights'
  | 'itinerary'
  | 'included'
  | 'excluded'
  | 'meeting'
  | 'cancellation'
  | 'important'
  | 'operator'
  | 'reviews'
  | 'related';

export interface DetailSection {
  id: DetailSectionId;
  present: boolean;
}

export type ContentLocale = 'en' | 'es' | 'it';

export interface LanguageDisclosure {
  showContentFallback: boolean;
  showItineraryFallback: boolean;
}

export interface DetailPresentation {
  sections: DetailSection[];
  presentSectionIds: DetailSectionId[];
  disclosure: LanguageDisclosure;
  hasDifficulty: boolean;
}

const ORDERED_SECTION_IDS: readonly DetailSectionId[] = [
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
];

/** Trimmed-nonempty text check; never rewrites the supplied value. */
export function isUsableText(value: unknown): value is string {
  return typeof value === 'string' && value.trim() !== '';
}

/** Usable string entries: trimmed-nonempty only. */
export function usableEntries(values: readonly (string | null | undefined)[] | undefined): string[] {
  if (!Array.isArray(values)) return [];
  return values.filter((value): value is string => isUsableText(value));
}

export function hasUsableEntries(values: readonly (string | null | undefined)[] | undefined): boolean {
  return usableEntries(values).length > 0;
}

function isUsableItineraryDay(day: TourItineraryDay | null | undefined): day is TourItineraryDay {
  return !!day && isUsableText(day.title);
}

/** An itinerary is usable only with at least one titled day. */
export function hasUsableItinerary(itinerary: TourDetail['itinerary']): boolean {
  return Array.isArray(itinerary) && itinerary.some(isUsableItineraryDay);
}

/** Null difficulty stays omitted; a supplied level is shown as-is. */
export function hasDifficulty(tour: TourDetail): boolean {
  return tour.difficulty_level === 'easy' || tour.difficulty_level === 'moderate' || tour.difficulty_level === 'challenging';
}

function hasOperator(tour: TourDetail): boolean {
  return tour.operator !== null && tour.operator !== undefined;
}

function hasScoredReviews(tour: TourDetail): boolean {
  return Number.isFinite(tour.reviews?.count) && (tour.reviews?.count ?? 0) > 0;
}

function hasRelated(tour: TourDetail): boolean {
  return Array.isArray(tour.related_tours) && tour.related_tours.length > 0;
}

/** Nonempty general (non-itinerary) content in the actual content locale. */
function hasNonemptyGeneralContent(tour: TourDetail): boolean {
  return (
    isUsableText(tour.title) ||
    isUsableText(tour.description) ||
    isUsableText(tour.meeting_point) ||
    isUsableText(tour.cancellation_policy) ||
    hasUsableEntries(tour.highlights) ||
    hasUsableEntries(tour.inclusions) ||
    hasUsableEntries(tour.exclusions) ||
    hasUsableEntries(tour.important_information)
  );
}

function isSectionPresent(tour: TourDetail, id: DetailSectionId): boolean {
  switch (id) {
    case 'overview':
    case 'facts':
      return true;
    case 'highlights':
      return hasUsableEntries(tour.highlights);
    case 'itinerary':
      return hasUsableItinerary(tour.itinerary);
    case 'included':
      return hasUsableEntries(tour.inclusions);
    case 'excluded':
      return hasUsableEntries(tour.exclusions);
    case 'meeting':
      return isUsableText(tour.meeting_point);
    case 'cancellation':
      return isUsableText(tour.cancellation_policy);
    case 'important':
      return hasUsableEntries(tour.important_information);
    case 'operator':
      return hasOperator(tour);
    case 'reviews':
      return hasScoredReviews(tour);
    case 'related':
      return hasRelated(tour);
  }
}

export function getDetailSections(tour: TourDetail): DetailSection[] {
  return ORDERED_SECTION_IDS.map((id) => ({ id, present: isSectionPresent(tour, id) }));
}

export function getPresentSectionIds(tour: TourDetail): DetailSectionId[] {
  return getDetailSections(tour)
    .filter((section) => section.present)
    .map((section) => section.id);
}

/**
 * General fallback: a non-English page whose actual content locale is
 * English with nonempty general content. The summary flag alone never
 * produces a notice.
 */
export function shouldShowContentFallback(tour: TourDetail, pageLocale: string): boolean {
  return pageLocale !== 'en' && tour.content_locale === 'en' && hasNonemptyGeneralContent(tour);
}

/**
 * Itinerary fallback: independent of general content and additionally
 * requiring a nonempty itinerary, so intentionally empty translated
 * itineraries stay silent without English substitution.
 */
export function shouldShowItineraryFallback(tour: TourDetail, pageLocale: string): boolean {
  return pageLocale !== 'en' && tour.itinerary_locale === 'en' && hasUsableItinerary(tour.itinerary);
}

export function getLanguageDisclosure(tour: TourDetail, pageLocale: string): LanguageDisclosure {
  return {
    showContentFallback: shouldShowContentFallback(tour, pageLocale),
    showItineraryFallback: shouldShowItineraryFallback(tour, pageLocale),
  };
}

/**
 * Spoken live-guide codes localized for the page; independent of the
 * content/itinerary locales. Unknown codes fall back to the raw code.
 */
export function getGuideLanguageNames(codes: readonly string[], pageLocale: string): string[] {
  let display: Intl.DisplayNames;
  try {
    display = new Intl.DisplayNames([pageLocale], { type: 'language' });
  } catch (error) {
    if (error instanceof RangeError) return [...codes];
    throw error;
  }
  return codes.map((code) => {
    let name: string | undefined;
    try {
      name = display.of(code);
    } catch (error) {
      if (error instanceof RangeError) return code;
      throw error;
    }
    if (name === undefined || name.length === 0) return code;
    // The constructor accepted pageLocale, so it is safe to reuse here.
    return name.charAt(0).toLocaleUpperCase(pageLocale) + name.slice(1);
  });
}

/** Image alt language: explicit alt_locale wins, else the content locale. */
export function resolveImageAltLocale(image: TourImage, contentLocale: ContentLocale): ContentLocale {
  return image.alt_locale ?? contentLocale;
}

export function getDetailPresentation(tour: TourDetail, pageLocale: string): DetailPresentation {
  const sections = getDetailSections(tour);
  return {
    sections,
    presentSectionIds: sections.filter((section) => section.present).map((section) => section.id),
    disclosure: getLanguageDisclosure(tour, pageLocale),
    hasDifficulty: hasDifficulty(tour),
  };
}

import { mergePersistedWizardState, normalizeWizardState } from '../tourWizard';
import type { TourFormData } from '@/types/tour';

const baseForm: TourFormData = {
  title: '',
  description: '',
  category: '',
  destination: '',
  duration_value: '',
  duration_unit: 'hour',
  difficulty_level: 'easy',
  itinerary: [],
  highlights: [],
  inclusions: [],
  exclusions: [],
  important_information: [],
  meeting_point: '',
  languages: [],
  cancellation_policy: '',
  media: [],
  pricing_tiers: [],
  availability_rules: [],
  availability_exceptions: [],
  group_size_min: 1,
  group_size_max: 20,
};

describe('normalizeWizardState (Spec 019)', () => {
  it('fills missing array defaults while keeping supplied order', () => {
    const normalized = normalizeWizardState({ title: 'Old snapshot' });
    expect(normalized.itinerary).toEqual([]);
    expect(normalized.highlights).toEqual([]);
    expect(normalized.inclusions).toEqual([]);
    expect(normalized.exclusions).toEqual([]);
    expect(normalized.important_information).toEqual([]);
    expect(normalized.languages).toEqual([]);
    expect(normalized.title).toBe('Old snapshot');
  });

  it('converts legacy string-array itineraries to structured days', () => {
    const stored: unknown = JSON.parse('{"itinerary":["Day 1: Arrival","Day 2: Tour"]}');
    const normalized = normalizeWizardState(stored);
    expect(normalized.itinerary).toEqual([
      { day: 1, title: 'Day 1: Arrival', description: null, stops: [] },
      { day: 2, title: 'Day 2: Tour', description: null, stops: [] },
    ]);
  });

  it('defaults absent stops to empty arrays and preserves repeated day numbers', () => {
    const stored: unknown = JSON.parse(
      '{"itinerary":[{"day":2,"title":"Second"},{"day":2,"title":"Repeated"}]}'
    );
    const normalized = normalizeWizardState(stored);
    expect(normalized.itinerary).toEqual([
      { day: 2, title: 'Second', description: null, stops: [] },
      { day: 2, title: 'Repeated', description: null, stops: [] },
    ]);
  });

  it('preserves older free-text inclusions and exclusions as bounded lists', () => {
    const stored: unknown = {
      inclusions: 'Live guide\nEntry tickets, welcome drink',
      exclusions: 'Hotel pickup, lunch',
    };
    const normalized = normalizeWizardState(stored);
    expect(normalized.inclusions).toEqual(['Live guide', 'Entry tickets', 'welcome drink']);
    expect(normalized.exclusions).toEqual(['Hotel pickup', 'lunch']);
  });

  it('drops malformed stop and day entries instead of crashing editors', () => {
    const stored: unknown = JSON.parse(
      '{"itinerary":[{"day":1,"title":"Day","stops":["oops",42,{"title":"Real stop"}]},42,null]}'
    );
    const normalized = normalizeWizardState(stored);
    expect(normalized.itinerary).toEqual([
      {
        day: 1,
        title: 'Day',
        description: null,
        stops: [{ title: 'Real stop', description: null, duration_minutes: null }],
      },
    ]);
  });

  it('keeps missing or null older difficulty absent instead of the new-tour default', () => {
    expect(normalizeWizardState({}).difficulty_level).toBeNull();
    expect(normalizeWizardState({ difficulty_level: null }).difficulty_level).toBeNull();
    expect(normalizeWizardState({ difficulty_level: 'moderate' }).difficulty_level).toBe('moderate');
  });
});

describe('mergePersistedWizardState (Spec 019 rehydration)', () => {
  const current = {
    currentStep: 'details' as const,
    formData: baseForm,
    isDirty: false,
    isSubmitting: false,
  };

  it('merges parsed older persisted form data into renderable arrays', () => {
    const raw: unknown = JSON.parse(
      '{"currentStep":"details","formData":{"title":"Old tour","inclusions":"Guide\\nTickets","itinerary":["Day 1: Arrival"],"difficulty_level":null}}'
    );
    const merged = mergePersistedWizardState(raw, current);
    expect(merged.formData.title).toBe('Old tour');
    expect(merged.formData.inclusions).toEqual(['Guide', 'Tickets']);
    expect(merged.formData.itinerary).toEqual([
      { day: 1, title: 'Day 1: Arrival', description: null, stops: [] },
    ]);
    // Null older difficulty stays absent; the new-tour easy default is not injected.
    expect(merged.formData.difficulty_level).toBeNull();
    // Render-critical arrays never crash joins/maps.
    expect(() => merged.formData.highlights.join(', ')).not.toThrow();
    expect(() => merged.formData.itinerary.map((day) => day.title)).not.toThrow();
  });

  it('ignores non-record persisted state and keeps current defaults', () => {
    expect(mergePersistedWizardState(null, current)).toBe(current);
    expect(mergePersistedWizardState('garbage', current)).toBe(current);
    expect(mergePersistedWizardState({ currentStep: 'details' }, current)).toBe(current);
  });
});

import {
  mergePersistedWizardState,
  normalizeWizardState,
  useTourWizardStore,
  isUuid,
  toServerWizardStep,
  fromServerWizardStep,
} from '../tourWizard';
import type { TourFormData } from '@/types/tour';

const baseForm: TourFormData = {
  source_locale: 'en',
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
  options: [],
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

  it('keeps an unanswered original writing language unselected and keeps valid codes', () => {
    // EGM-004: an unbound local snapshot with null/missing/invalid source is
    // NOT a legacy tour — it stays unselected so the Language gate is still
    // asked. Only identifiable materialized legacy tours default to 'en',
    // and that default is applied by the edit-flow call sites.
    expect(normalizeWizardState({}).source_locale).toBeNull();
    expect(normalizeWizardState({ source_locale: null }).source_locale).toBeNull();
    expect(normalizeWizardState({ source_locale: 'fr' }).source_locale).toBeNull();
    expect(normalizeWizardState({ source_locale: 'en' }).source_locale).toBe('en');
    expect(normalizeWizardState({ source_locale: 'es' }).source_locale).toBe('es');
    expect(normalizeWizardState({ source_locale: 'it' }).source_locale).toBe('it');
  });

  it('restores authored option rows while dropping malformed entries', () => {
    const normalized = normalizeWizardState({
      options: [
        {
          id: 'row-1',
          server_id: 12,
          title: 'Paseo privado',
          pickup_instructions: 'Recogida en el hotel',
          guide_languages: ['es', 'ar'],
          price: '45.50',
          capacity: '8',
          start_time: '10:00',
          weekdays: [1, 3, 1],
        },
        42,
        null,
        { title: 7 },
      ],
    });
    expect(normalized.options).toEqual([
      {
        id: 'row-1',
        server_id: 12,
        title: 'Paseo privado',
        pickup_instructions: 'Recogida en el hotel',
        guide_languages: ['es', 'ar'],
        price: '45.50',
        capacity: '8',
        start_time: '10:00',
        weekdays: [1, 3],
      },
    ]);
  });
});

describe('mergePersistedWizardState (Spec 019 rehydration)', () => {
  const current = {
    currentStep: 'details' as const,
    formData: baseForm,
    draftId: null,
    creationId: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    tourId: null,
    concurrencyToken: null,
    lastSyncedAt: null,
    hasUnsyncedDraftChanges: false,
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

describe('mergePersistedWizardState source gate (A2, EGM-004)', () => {
  // The live store's initial state: the Language step with nothing chosen.
  const unselectedCurrent = {
    currentStep: 'source' as const,
    formData: { ...baseForm, source_locale: null },
    draftId: null,
    creationId: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
    tourId: null,
    concurrencyToken: null,
    lastSyncedAt: null,
    hasUnsyncedDraftChanges: false,
    isDirty: false,
    isSubmitting: false,
  };

  it('routes an unselected restored draft back to the Language step even when an older step was persisted', () => {
    const raw: unknown = JSON.parse(
      '{"currentStep":"pricing","formData":{"title":"Half-written tour","source_locale":null,'
      + '"options":[{"id":"row-1","server_id":41,"title":"Evening walk","pickup_instructions":"",'
      + '"guide_languages":["es","ar"],"price":"35","capacity":"6","start_time":"18:00","weekdays":[5]}]}}'
    );
    const merged = mergePersistedWizardState(raw, unselectedCurrent);
    // A persisted later step never skips the unanswered writing-language gate.
    expect(merged.currentStep).toBe('source');
    expect(merged.formData.source_locale).toBeNull();
    // The restored draft and its authored option rows survive behind the gate.
    expect(merged.formData.title).toBe('Half-written tour');
    expect(merged.formData.options[0]).toMatchObject({ id: 'row-1', server_id: 41, guide_languages: ['es', 'ar'] });
  });

  it('keeps a persisted later step when a valid source language was chosen', () => {
    const raw: unknown = JSON.parse(
      '{"currentStep":"availability","formData":{"title":"Recorrido español","source_locale":"es"}}'
    );
    const merged = mergePersistedWizardState(raw, unselectedCurrent);
    expect(merged.currentStep).toBe('availability');
    expect(merged.formData.source_locale).toBe('es');
    expect(merged.formData.title).toBe('Recorrido español');
  });

  it('routes an older snapshot without any stored source through the Language step', () => {
    const raw: unknown = JSON.parse('{"currentStep":"details","formData":{"title":"Pre-source snapshot"}}');
    const merged = mergePersistedWizardState(raw, unselectedCurrent);
    expect(merged.currentStep).toBe('source');
    expect(merged.formData.source_locale).toBeNull();
    expect(merged.formData.title).toBe('Pre-source snapshot');
  });
});

describe('useTourWizardStore EGM-005 server draft recovery metadata (D1, D2)', () => {
  beforeEach(() => {
    localStorage.clear();
    useTourWizardStore.getState().reset();
  });

  it('generates a stable UUID creationId and rotates it on reset or regenerateCreationId', () => {
    const first = useTourWizardStore.getState().creationId;
    expect(isUuid(first)).toBe(true);

    useTourWizardStore.getState().updateField('title', 'Preserve local edits');
    useTourWizardStore.getState().setDraftSyncMeta({
      draftId: 77,
      tourId: 12,
      concurrencyToken: 'tok-1',
    });

    useTourWizardStore.getState().regenerateCreationId();
    const regenerated = useTourWizardStore.getState();
    expect(isUuid(regenerated.creationId)).toBe(true);
    expect(regenerated.creationId).not.toBe(first);
    expect(regenerated.draftId).toBeNull();
    expect(regenerated.tourId).toBeNull();
    expect(regenerated.formData.title).toBe('Preserve local edits');

    useTourWizardStore.getState().reset();
    const afterReset = useTourWizardStore.getState();
    expect(isUuid(afterReset.creationId)).toBe(true);
    expect(afterReset.creationId).not.toBe(regenerated.creationId);
    expect(afterReset.formData.title).toBe('');
  });

  it('hydrates from a server draft keeping unselected source_locale null and mapping language step', () => {
    expect(toServerWizardStep('source')).toBe('language');
    expect(fromServerWizardStep('language')).toBe('source');
    expect(fromServerWizardStep('options')).toBe('source');
    expect(fromServerWizardStep('pricing')).toBe('pricing');

    useTourWizardStore.getState().hydrateFromServerDraft({
      id: 42,
      creation_id: '8b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
      tour_id: null,
      concurrency_token: 'tok-abc',
      current_step: 'language',
      source_locale: null,
      auto_saved_at: '2026-11-21T10:00:00Z',
      payload: {
        title: 'Partial Unselected Draft',
      },
    });

    const unselectedState = useTourWizardStore.getState();
    expect(unselectedState.draftId).toBe(42);
    expect(unselectedState.creationId).toBe('8b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d');
    expect(unselectedState.concurrencyToken).toBe('tok-abc');
    expect(unselectedState.formData.source_locale).toBeNull();
    expect(unselectedState.currentStep).toBe('source');
    expect(unselectedState.formData.title).toBe('Partial Unselected Draft');

    useTourWizardStore.getState().hydrateFromServerDraft({
      draft_id: 99,
      creation_id: '7b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
      tour_id: 55,
      concurrency_token: 'tok-xyz',
      current_step: 'pricing',
      source_locale: 'es',
      payload: {
        title: 'Paseo en Sevilla',
        options: [
          {
            id: 'opt-1',
            server_id: 301,
            title: 'Grupo mañana',
            price: '45',
            capacity: '8',
          },
        ],
      },
    });

    const resumedState = useTourWizardStore.getState();
    expect(resumedState.draftId).toBe(99);
    expect(resumedState.tourId).toBe(55);
    expect(resumedState.currentStep).toBe('pricing');
    expect(resumedState.formData.source_locale).toBe('es');
    expect(resumedState.formData.options[0].server_id).toBe(301);
  });

  it('normalizes API-shaped materialized draft payloads losslessly including server_id, major price, and languages', () => {
    const normalized = normalizeWizardState({
      source_locale: 'es',
      title: 'Tour Materializado',
      guide_languages: ['es', 'fr'],
      media: [{ url: 'https://cdn.example.com/1.jpg', is_cover: true }],
      pricing_tiers: [{ name: 'General', price: 40, min_participants: 1, max_participants: 6 }],
      options: [
        {
          id: 405,
          title: 'Opción API',
          guide_languages: ['es'],
          price_amount: 4550,
          group_size_max: 8,
          start_time: '09:30:00',
          weekdays: [1, 3],
        },
      ],
    });

    expect(normalized.languages).toEqual(['es', 'fr']);
    expect(normalized.media?.[0]).toMatchObject({
      url: 'https://cdn.example.com/1.jpg',
      is_cover: true,
      sort_order: 0,
    });
    expect(normalized.pricing_tiers?.[0]).toMatchObject({
      name: 'General',
      price: '40',
      currency: 'EUR',
    });
    expect(normalized.options?.[0]).toMatchObject({
      id: 'srv-option-405',
      server_id: 405,
      title: 'Opción API',
      price: '45.5',
      capacity: '8',
      start_time: '09:30',
      weekdays: [1, 3],
    });
  });
});

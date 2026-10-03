import { act, render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TourWizard } from '../TourWizard';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { createTour, updateTour, submitTour } from '@/lib/api/partner';
import type { TourFormData, WizardStep } from '@/types/tour';

const push = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
}));

jest.mock('@/lib/api/partner', () => ({
  createTour: jest.fn(),
  updateTour: jest.fn(),
  submitTour: jest.fn(),
  getSignedUploadUrl: jest.fn(),
}));

const mockedCreateTour = createTour as jest.Mock;
const mockedUpdateTour = updateTour as jest.Mock;
const mockedSubmitTour = submitTour as jest.Mock;

if (typeof window !== 'undefined' && !window.requestAnimationFrame) {
  window.requestAnimationFrame = ((cb: FrameRequestCallback) => {
    cb(0);
    return 0;
  }) as typeof window.requestAnimationFrame;
}

const baseForm: TourFormData = {
  title: 'Morning walking tour',
  description: 'A wonderful tour through Florence with a local guide, visiting celebrated landmarks and hidden corners.',
  category: 'walking',
  destination: 'Florence, Italy',
  duration_value: '3',
  duration_unit: 'hour',
  difficulty_level: 'easy',
  itinerary: [{ day: 1, title: 'Old town', description: null, stops: [] }],
  highlights: ['Old-town route'],
  inclusions: ['Live guide'],
  exclusions: ['Lunch'],
  important_information: ['Comfortable shoes recommended'],
  meeting_point: 'Metro exit',
  languages: ['en', 'de'],
  cancellation_policy: 'Cancel up to 24 hours in advance.',
  media: [],
  pricing_tiers: [],
  availability_rules: [],
  availability_exceptions: [],
  group_size_min: 2,
  group_size_max: 7,
};

function seedForm(overrides: Partial<TourFormData> = {}, step: WizardStep = 'details') {
  useTourWizardStore.setState({
    currentStep: step,
    formData: { ...baseForm, ...overrides },
    isDirty: false,
    isSubmitting: false,
  });
}

describe('TourWizard (Spec 019)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
    useTourWizardStore.getState().reset();
    useTourWizardStore.setState({ currentStep: 'details' });
  });

  it('restores an older localStorage draft through persist and renders its preserved source', async () => {
    localStorage.setItem('partner-tour-wizard', JSON.stringify({
      state: {
        currentStep: 'details',
        formData: {
          title: 'Restored old tour',
          inclusions: 'Guide\nTickets',
          exclusions: 'Pickup, lunch',
          itinerary: ['Day 1: Arrival'],
          difficulty_level: null,
        },
      },
      version: 0,
    }));

    await act(async () => {
      await useTourWizardStore.persist.rehydrate();
    });
    render(<TourWizard />);

    expect(screen.getByLabelText('form.title')).toHaveValue('Restored old tour');
    expect(screen.getByLabelText('form.inclusions')).toHaveValue('Guide, Tickets');
    expect(screen.getByLabelText('form.exclusions')).toHaveValue('Pickup, lunch');
    expect(screen.getByDisplayValue('Day 1: Arrival')).toBeInTheDocument();
    expect(screen.getByLabelText('form.difficultyLevel')).toHaveTextContent(/^$/);
    expect(useTourWizardStore.getState().formData.difficulty_level).toBeNull();
  });

  it('renders all nine English source controls on the details step', () => {
    seedForm();
    render(<TourWizard />);
    for (const label of ['form.title', 'form.description', 'form.highlights', 'form.inclusions', 'form.exclusions', 'form.importantInformation', 'form.meetingPoint', 'form.cancellationPolicy']) {
      expect(screen.getByLabelText(label)).toBeInTheDocument();
    }
    expect(screen.getByText('itinerary')).toBeInTheDocument();
  });

  it('sends nested English source with all fields while preserving pricing and availability', async () => {
    seedForm({
      pricing_tiers: [{ id: 't1', name: 'Adult', price: '50', currency: 'USD', min_participants: 1, max_participants: 10 }],
      availability_rules: [{ id: 'r1', rule_type: 'weekly', days_of_week: [1], start_time: '09:00', start_date: '2026-01-01', end_date: '', capacity: 10 }],
    });
    mockedCreateTour.mockResolvedValue({ data: { id: 7, status: 'draft' } });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.saveDraft' }));

    await waitFor(() => expect(mockedCreateTour).toHaveBeenCalledTimes(1));
    const payload = mockedCreateTour.mock.calls[0][0];
    // Nested English source carries every field; nested keys win server-side.
    expect(payload.translations.en).toEqual({
      title: 'Morning walking tour',
      description: baseForm.description,
      highlights: ['Old-town route'],
      inclusions: ['Live guide'],
      exclusions: ['Lunch'],
      meeting_point: 'Metro exit',
      cancellation_policy: 'Cancel up to 24 hours in advance.',
      itinerary: [{ day: 1, title: 'Old town', description: null, stops: [] }],
      important_information: ['Comfortable shoes recommended'],
    });
    // Creation never publishes: no lifecycle status travels with the write.
    expect(payload).not.toHaveProperty('status');
    // Pricing, availability, group size and media survive the submission
    // under the real API contract names.
    expect(payload.pricing_tiers).toHaveLength(1);
    expect(payload.availability_rules).toHaveLength(1);
    expect(payload.group_size_min).toBe(2);
    expect(payload.group_size_max).toBe(7);
    expect(payload).not.toHaveProperty('min_participants');
    expect(payload).not.toHaveProperty('max_participants');
    // Save Draft keeps the materialized draft open with truthful feedback.
    await waitFor(() => expect(screen.getByText('wizard.saved')).toBeInTheDocument());
    expect(push).not.toHaveBeenCalled();
  });

  it('updates the retained draft instead of duplicating on save retry', async () => {
    seedForm();
    mockedCreateTour.mockResolvedValue({ data: { id: 11, status: 'draft' } });
    mockedUpdateTour.mockResolvedValue({ data: { id: 11, status: 'draft' } });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.saveDraft' }));
    await waitFor(() => expect(mockedCreateTour).toHaveBeenCalledTimes(1));

    fireEvent.click(screen.getByRole('button', { name: 'wizard.saveDraft' }));
    await waitFor(() => expect(mockedUpdateTour).toHaveBeenCalledTimes(1));
    expect(mockedUpdateTour.mock.calls[0][0]).toBe(11);
    expect(mockedCreateTour).toHaveBeenCalledTimes(1);
  });

  it('blocks submit on missing title without calling the API', async () => {
    seedForm({ title: '' });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.saveDraft' }));
    // Save Draft still attempts (server owns canonical validation)...
    await waitFor(() => expect(mockedCreateTour).toHaveBeenCalledTimes(1));

    mockedCreateTour.mockClear();
    act(() => useTourWizardStore.setState({ currentStep: 'review' }));
    fireEvent.click(await screen.findByRole('button', { name: 'wizard.submit' }));
    expect(mockedCreateTour).not.toHaveBeenCalled();
    expect(mockedSubmitTour).not.toHaveBeenCalled();
  });

  it('focuses an announced localized summary on client validation failure', async () => {
    seedForm({ title: '' });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.nextStep' }));

    const heading = await screen.findByText('form.errorSummary');
    const summary = heading.closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(document.activeElement).toBe(summary);
  });

  it('materializes a draft then submits through the guarded endpoint', async () => {
    seedForm(
      {
        media: [{ id: 'm1', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 }],
        pricing_tiers: [{ id: 't1', name: 'Adult', price: '50', currency: 'USD', min_participants: 1, max_participants: 10 }],
        availability_rules: [{ id: 'r1', rule_type: 'weekly', days_of_week: [1], start_time: '09:00', start_date: '2026-01-01', end_date: '', capacity: 10 }],
      },
      'review'
    );
    mockedCreateTour.mockResolvedValue({ data: { id: 9, status: 'draft' } });
    mockedSubmitTour.mockResolvedValue({ data: { id: 9 } });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.submit' }));

    await waitFor(() => expect(mockedCreateTour).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(mockedSubmitTour).toHaveBeenCalledWith(9));
    expect(push).toHaveBeenCalledWith('/en/partner');
  });

  it('retries a failed submit against the retained draft without duplicating', async () => {
    seedForm(
      {
        media: [{ id: 'm1', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 }],
        pricing_tiers: [{ id: 't1', name: 'Adult', price: '50', currency: 'USD', min_participants: 1, max_participants: 10 }],
        availability_rules: [{ id: 'r1', rule_type: 'weekly', days_of_week: [1], start_time: '09:00', start_date: '2026-01-01', end_date: '', capacity: 10 }],
      },
      'review'
    );
    mockedCreateTour.mockResolvedValue({ data: { id: 12, status: 'draft' } });
    mockedUpdateTour.mockResolvedValue({ data: { id: 12, status: 'draft' } });
    mockedSubmitTour.mockRejectedValueOnce(new Error('Submit refused: cover missing'));
    mockedSubmitTour.mockResolvedValue({ data: { id: 12 } });
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.submit' }));
    await waitFor(() => expect(mockedSubmitTour).toHaveBeenCalledTimes(1));

    // Retry updates the same materialized draft, then resubmits it.
    fireEvent.click(screen.getByRole('button', { name: 'wizard.submit' }));
    await waitFor(() => expect(mockedUpdateTour).toHaveBeenCalledWith(12, expect.anything()));
    await waitFor(() => expect(mockedSubmitTour).toHaveBeenCalledTimes(2));
    expect(mockedCreateTour).toHaveBeenCalledTimes(1);
    expect(push).toHaveBeenCalledWith('/en/partner');
  });

  it('maps server nested 422 paths onto fields with an announced summary', async () => {
    seedForm();
    const { ValidationError } = await import('@/lib/api/client');
    mockedCreateTour.mockRejectedValue(
      new ValidationError('Validation failed', {
        'translations.en.itinerary.0.title': ['Day title is required.'],
      })
    );
    render(<TourWizard />);
    fireEvent.click(screen.getByRole('button', { name: 'wizard.saveDraft' }));

    await waitFor(() => expect(screen.getAllByRole('alert')).not.toHaveLength(0));
    const alerts = screen.getAllByRole('alert');
    // Announced summary (heading plus raw message) and the nested field
    // mapped onto the actual input (localized here through the key mock).
    const summary = alerts.find((a) => (a.textContent ?? '').includes('form.errorSummary'));
    expect(summary?.textContent).toContain('Validation failed');
    expect(alerts.map((a) => a.textContent)).toContain('errors.dayTitleRequired');
    expect(screen.getByLabelText('form.title')).toBeInTheDocument();
  });
});

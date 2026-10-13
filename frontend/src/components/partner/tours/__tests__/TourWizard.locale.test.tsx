import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TourWizard } from '../TourWizard';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { createTour } from '@/lib/api/partner';
import type { TourFormData, WizardStep } from '@/types/tour';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../../messages/en.json';
import es from '../../../../../messages/es.json';
import itMessages from '../../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

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

type Locale = 'en' | 'es' | 'it';
const catalogs = { en, es, it: itMessages };

function catalogText(locale: Locale, path: string): string {
  let node: unknown = catalogs[locale];
  for (const part of path.split('.')) {
    if (typeof node !== 'object' || node === null || !(part in node)) {
      throw new Error(`Missing catalog key ${locale}:${path}`);
    }
    node = (node as Record<string, unknown>)[part];
  }
  if (typeof node !== 'string') {
    throw new Error(`Missing catalog key ${locale}:${path}`);
  }
  return node;
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

function renderWizard(locale: Locale) {
  render(
    <NextIntlClientProvider locale={locale} messages={catalogs[locale]} timeZone="UTC">
      <TourWizard />
    </NextIntlClientProvider>
  );
}

describe.each(['en', 'es', 'it'] as const)('TourWizard locale %s (Spec 019)', (locale) => {
  const text = (path: string) => catalogText(locale, path);

  beforeEach(() => {
    jest.clearAllMocks();
    useTourWizardStore.getState().reset();
    useTourWizardStore.setState({ currentStep: 'details' });
  });

  it('announces a localized focused summary for client validation failures', async () => {
    seedForm({ title: '' });
    renderWizard(locale);
    fireEvent.click(screen.getByRole('button', { name: text('partner.tours.wizard.nextStep') }));

    const heading = await screen.findByText(text('partner.tours.form.errorSummary'));
    const summary = heading.closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(summary).toHaveTextContent(text('partner.tours.errors.titleRequired'));
    expect(document.activeElement).toBe(summary);
  });

  it('localizes indexed server list errors under their source control', async () => {
    seedForm();
    const { ValidationError } = await import('@/lib/api/client');
    mockedCreateTour.mockRejectedValue(
      new ValidationError('Validation failed', {
        'translations.en.highlights': ['The translations.en.highlights field must not be greater than 30 items.'],
      })
    );
    renderWizard(locale);
    fireEvent.click(screen.getByRole('button', { name: text('partner.tours.wizard.saveDraft') }));

    await waitFor(() => expect(
      screen.getAllByText(text('partner.tours.errors.highlightsMax')).length
    ).toBeGreaterThan(0));
    const heading = screen.getByText(text('partner.tours.form.errorSummary'));
    expect(heading.closest('[role="alert"]')).not.toBeNull();
  });

  it('flags fractional stop durations with localized integer feedback', async () => {
    seedForm({
      itinerary: [{ day: 1, title: 'Old town', description: null, stops: [{ title: 'Stop', duration_minutes: 1.5 }] }],
    });
    renderWizard(locale);
    fireEvent.click(screen.getByRole('button', { name: text('partner.tours.wizard.nextStep') }));

    const heading = await screen.findByText(text('partner.tours.form.errorSummary'));
    const summary = heading.closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(summary).toHaveTextContent(text('partner.tours.errors.durationMinutesInteger'));
    expect(document.activeElement).toBe(summary);
  });

  it('shows translated selected category/duration/difficulty labels with stored codes unchanged', async () => {
    seedForm({ category: 'walking', duration_unit: 'hour', difficulty_level: 'easy' });
    renderWizard(locale);
    const categoryTrigger = screen.getByRole('button', { name: text('partner.tours.form.category') });
    expect(categoryTrigger).toHaveAttribute('aria-labelledby', 'category-label');
    expect(categoryTrigger).toHaveTextContent(text('partner.tours.form.walking'));
    expect(categoryTrigger.textContent).not.toBe('walking');
    const durationTrigger = screen.getByRole('button', { name: text('partner.tours.form.durationUnit') });
    expect(durationTrigger).toHaveAttribute('aria-labelledby', 'duration_unit-label');
    expect(durationTrigger).toHaveTextContent(text('partner.tours.form.hours'));
    expect(durationTrigger.textContent).not.toBe('hour');
    const difficultyTrigger = screen.getByRole('button', { name: text('partner.tours.form.difficultyLevel') });
    expect(difficultyTrigger).toHaveAttribute('aria-labelledby', 'difficulty_level-label');
    expect(difficultyTrigger).toHaveTextContent(text('partner.tours.form.easy'));
    expect(difficultyTrigger.textContent).not.toBe('easy');
    expect(useTourWizardStore.getState().formData.category).toBe('walking');
    expect(useTourWizardStore.getState().formData.duration_unit).toBe('hour');
    expect(useTourWizardStore.getState().formData.difficulty_level).toBe('easy');
  });

  it('keeps absent old difficulty blank while offering translated options via keyboard', async () => {
    seedForm({ difficulty_level: null });
    renderWizard(locale);
    const difficultyTrigger = screen.getByRole('button', { name: text('partner.tours.form.difficultyLevel') });
    expect(difficultyTrigger).toHaveTextContent(/^$/);
    expect(useTourWizardStore.getState().formData.difficulty_level).toBeNull();
    difficultyTrigger.focus();
    fireEvent.keyDown(difficultyTrigger, { key: 'ArrowDown' });
    const option = await screen.findByRole('option', { name: text('partner.tours.form.moderate') });
    fireEvent.click(option);
    expect(useTourWizardStore.getState().formData.difficulty_level).toBe('moderate');
    expect(screen.getByRole('button', { name: text('partner.tours.form.difficultyLevel') })).toHaveTextContent(
      text('partner.tours.form.moderate')
    );
  });
});

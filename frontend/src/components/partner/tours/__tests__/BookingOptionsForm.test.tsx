import { fireEvent, render, screen } from '@testing-library/react';
import { BookingOptionsForm } from '../BookingOptionsForm';
import type { BookingOptionFormInput } from '@/types/tour';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../../messages/en.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

// The catalogIntl test provider exercises the real en.json copy; the browser
// suite exercises next-intl itself, so these tests pin the actual message keys.
const englishForm = en.partner.tours.form;

function optionRow(overrides: Partial<BookingOptionFormInput> = {}): BookingOptionFormInput {
  return {
    id: 'option-1',
    title: 'Standard group',
    pickup_instructions: 'Hotel pickup on request',
    guide_languages: ['en'],
    price: '50.00',
    capacity: '10',
    start_time: '09:00',
    weekdays: [],
    ...overrides,
  };
}

const baseCallbacks = {
  onAdd: jest.fn(),
  onRemove: jest.fn(),
  onUpdate: jest.fn(),
};

function renderForm(
  overrides: {
    rows?: BookingOptionFormInput[];
    disabled?: boolean;
    structureDisabled?: boolean;
    sourceLocale?: 'en' | 'es' | 'it';
    serverErrors?: Record<string, string>;
  } = {}
) {
  const { rows, disabled, structureDisabled, sourceLocale, serverErrors } = overrides;
  return render(
    <NextIntlClientProvider locale="en" messages={en}>
      <BookingOptionsForm
        rows={rows ?? [optionRow()]}
        disabled={disabled}
        structureDisabled={structureDisabled}
        sourceLocale={sourceLocale}
        {...baseCallbacks}
        serverErrors={serverErrors}
      />
    </NextIntlClientProvider>
  );
}

describe('BookingOptionsForm structural control (EGM-003 fail-closed boundary)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('keeps structural create/remove controls available by default for the wizard and drafts', () => {
    renderForm({ rows: [optionRow(), optionRow({ id: 'option-2', title: 'Private tour' })] });

    // Default behavior regression: the wizard/draft flows keep Add and per-row
    // Remove exactly as before the governance change.
    expect(screen.getByRole('button', { name: englishForm.addOption })).toBeEnabled();
    expect(screen.getAllByRole('button', { name: /Remove option/i })).toHaveLength(2);
  });

  it('hides add and remove structural controls when structure is locked', () => {
    renderForm({ structureDisabled: true, rows: [optionRow(), optionRow({ id: 'option-2', title: 'Private tour' })] });

    expect(screen.queryByRole('button', { name: englishForm.addOption })).not.toBeInTheDocument();
    expect(screen.queryAllByRole('button', { name: /Remove option/i })).toHaveLength(0);
    // The refusal is explained, without any fabricated future link.
    expect(screen.getByText(englishForm.optionStructureLockedNotice)).toBeInTheDocument();
    expect(screen.getByText(englishForm.optionStructurePendingNote)).toBeInTheDocument();
  });

  it('keeps authored text and operational inputs editable while structure is locked', () => {
    renderForm({ structureDisabled: true });

    const title = screen.getByLabelText(englishForm.optionTitle);
    const pickup = screen.getByLabelText(englishForm.optionPickupInstructions);
    const price = screen.getByLabelText(englishForm.optionPrice);
    const capacity = screen.getByLabelText(englishForm.optionCapacity);
    const start = screen.getByLabelText(englishForm.optionStartTime);
    const guides = screen.getByLabelText(englishForm.optionGuideLanguages);

    for (const control of [title, pickup, price, capacity, start, guides]) {
      expect(control).toBeEnabled();
    }
    // Weekday toggles are operational schedule controls and stay enabled.
    fireEvent.click(screen.getByRole('button', { name: englishForm.weekday1 }));
    expect(baseCallbacks.onUpdate).toHaveBeenCalledWith('option-1', { weekdays: [1] });
  });

  it('does not disable legitimate inputs behind the structure lock through the disabled flag', () => {
    renderForm({ structureDisabled: true });
    fireEvent.change(screen.getByLabelText(englishForm.optionTitle), { target: { value: 'Edited title' } });
    expect(baseCallbacks.onUpdate).toHaveBeenCalledWith('option-1', { title: 'Edited title' });
  });

  it('still disables every input through the existing all-fields disabled prop', () => {
    renderForm({ disabled: true });

    expect(screen.getByLabelText(englishForm.optionTitle)).toBeDisabled();
    expect(screen.getByLabelText(englishForm.optionPrice)).toBeDisabled();
    expect(screen.getByLabelText(englishForm.optionPickupInstructions)).toBeDisabled();
  });

  it('renders the row source-language hint and server field errors', () => {
    renderForm({ sourceLocale: 'es', serverErrors: { 'options.0.title': 'Option 0 error' } });

    expect(screen.getByText('Titles and pickup instructions are written in Español.')).toBeInTheDocument();
    expect(screen.getByText('Option 0 error')).toBeInTheDocument();
  });

  it('shows the empty state when no rows exist', () => {
    renderForm({ rows: [] });

    expect(screen.getByText(englishForm.noBookingOptions)).toBeInTheDocument();
  });
});

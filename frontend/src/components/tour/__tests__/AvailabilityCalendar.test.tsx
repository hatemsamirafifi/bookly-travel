import { render, screen, fireEvent, within } from '@testing-library/react';
import AvailabilityCalendar from '../AvailabilityCalendar';
import { buildOfferedDates, FIXTURE_NOW_UTC } from '../../../../tests/fixtures/tour-detail';
import { formatOfferedDateUtc } from '@/lib/tours/booking-selection';
import enMessages from '../../../../messages/en.json';

// Spec 020 US2 (T025): the calendar is a controlled, labelled native select
// over every offered future date. State lives in TourBookingController; this
// component never slices, infers or stores its own selection.

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const scope = enMessages.tour as unknown as Record<string, string>;
    return scope[key] ?? key;
  },
}));

const CLOCK = new Date(FIXTURE_NOW_UTC);

function renderCalendar(overrides: Partial<React.ComponentProps<typeof AvailabilityCalendar>> = {}) {
  const dates = buildOfferedDates(CLOCK);
  const onSelect = jest.fn();
  render(
    <AvailabilityCalendar
      offeredDates={dates}
      selectedDate={dates[0]}
      onSelect={onSelect}
      nextAvailableDate={dates[0]}
      locale="en"
      id="tour-date-select"
      {...overrides}
    />,
  );
  return { dates, onSelect };
}

describe('AvailabilityCalendar controlled selector', () => {
  it('labels the native select explicitly and reflects the controlled value', () => {
    const { dates } = renderCalendar();
    const select = screen.getByLabelText('Select a Date') as HTMLSelectElement;
    expect(select.id).toBe('tour-date-select');
    expect(select.value).toBe(dates[0]);
  });

  it('exposes every offered future date, including index 31, with UTC day labels', () => {
    const { dates } = renderCalendar();
    const options = within(screen.getByLabelText('Select a Date')).getAllByRole('option');

    expect(options).toHaveLength(dates.length + 1);
    expect(options[32]).toHaveValue(dates[31]);
    expect(options[32]).toHaveTextContent(formatOfferedDateUtc(dates[31], 'en'));
  });

  it('never shifts the calendar day through the local timezone', () => {
    renderCalendar({ offeredDates: ['2026-10-15'], selectedDate: null, nextAvailableDate: null });
    const option = within(screen.getByLabelText('Select a Date')).getByRole('option', {
      name: formatOfferedDateUtc('2026-10-15', 'en'),
    });
    expect(option).toHaveValue('2026-10-15');
  });

  it('passes the exact date string back without inference', () => {
    const { dates, onSelect } = renderCalendar();
    fireEvent.change(screen.getByLabelText('Select a Date'), { target: { value: dates[7] } });
    expect(onSelect).toHaveBeenCalledTimes(1);
    expect(onSelect).toHaveBeenCalledWith(dates[7]);
  });

  it('reports an empty offered list honestly instead of fabricating slots', () => {
    renderCalendar({ offeredDates: [], selectedDate: null, nextAvailableDate: null });
    expect(screen.queryByLabelText('Select a Date')).not.toBeInTheDocument();
    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
  });
});

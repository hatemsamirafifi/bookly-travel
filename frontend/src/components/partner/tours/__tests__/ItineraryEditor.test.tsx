import { render, screen, fireEvent } from '@testing-library/react';
import { ItineraryEditor } from '../ItineraryEditor';
import type { TourItineraryDay } from '@/lib/api/types';

const days: TourItineraryDay[] = [
  {
    day: 1,
    title: 'Arrival',
    description: null,
    stops: [{ title: 'Meet the guide', description: null, duration_minutes: 30 }],
  },
  { day: 2, title: 'Explore', description: null, stops: [] },
];

describe('ItineraryEditor (Spec 019)', () => {
  it('renders a duration control per stop with an accessible label', () => {
    render(<ItineraryEditor value={days} onChange={jest.fn()} />);
    const duration = screen.getByLabelText('stopDuration');
    expect(duration).toBeInTheDocument();
    expect(duration).toHaveValue(30);
  });

  it('preserves fractional duration input instead of flooring it', () => {
    const onChange = jest.fn();
    render(<ItineraryEditor value={days} onChange={onChange} />);
    fireEvent.change(screen.getByLabelText('stopDuration'), { target: { value: '1.5' } });
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({
        stops: [expect.objectContaining({ duration_minutes: 1.5 })],
      }),
      expect.anything(),
    ]);
  });

  it('clears duration to null when the input is emptied', () => {
    const onChange = jest.fn();
    render(<ItineraryEditor value={days} onChange={onChange} />);
    fireEvent.change(screen.getByLabelText('stopDuration'), { target: { value: '' } });
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({
        stops: [expect.objectContaining({ duration_minutes: null })],
      }),
      expect.anything(),
    ]);
  });

  it('keeps zero-stop days with an add-stop affordance', () => {
    render(<ItineraryEditor value={days} onChange={jest.fn()} />);
    expect(screen.getAllByText('addStop')).toHaveLength(2);
  });

  it('surfaces nested field errors with live announcements', () => {
    render(
      <ItineraryEditor
        value={days}
        onChange={jest.fn()}
        errors={{ 'itinerary.0.stops.0.duration_minutes': 'Duration must be between 1 and 1,440 minutes.' }}
      />
    );
    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Duration must be between 1 and 1,440 minutes.');
    expect(screen.getByLabelText('stopDuration')).toHaveAttribute('aria-invalid', 'true');
  });

  it('accepts server-prefixed nested error paths', () => {
    render(
      <ItineraryEditor
        value={days}
        onChange={jest.fn()}
        errors={{ 'translations.en.itinerary.1.title': 'Day title is required.' }}
      />
    );
    expect(screen.getByRole('alert')).toHaveTextContent('Day title is required.');
  });

  it('preserves ordered edits when moving days', () => {
    const onChange = jest.fn();
    render(<ItineraryEditor value={days} onChange={onChange} />);
    fireEvent.click(screen.getAllByLabelText('moveDayDown')[0]);
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({ day: 1, title: 'Explore' }),
      expect.objectContaining({ day: 2, title: 'Arrival' }),
    ]);
  });
});

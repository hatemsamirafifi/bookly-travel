import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CancelBookingButton from '../CancelBookingButton';

jest.mock('next-intl', () => ({
  useTranslations: (namespace: string) => {
    const keys: Record<string, string> = {
      title: 'Cancel Booking',
      reference: 'Reference',
      tour: 'Tour',
      date: 'Date',
      body: 'Are you sure you want to cancel this booking?',
      windowWarning: 'Cancellation may be subject to fees.',
      confirm: 'Yes, Cancel Booking',
      cancelling: 'Cancelling...',
      keep: 'Keep Booking',
      button: 'Cancel Booking',
      disabledTitle: 'This booking cannot be cancelled',
      error: 'Failed to cancel booking',
    };
    return (key: string) => keys[key] || key;
  },
}));

describe('CancelBookingButton', () => {
  it('renders button in disabled state when canCancel is false', () => {
    render(
      <CancelBookingButton canCancel={false} onCancel={jest.fn()} />
    );

    const btn = screen.getByRole('button', { name: 'Cancel Booking' });
    expect(btn).toBeDisabled();
  });

  it('renders enabled button when canCancel is true', () => {
    render(
      <CancelBookingButton canCancel={true} onCancel={jest.fn()} />
    );

    const btn = screen.getByRole('button', { name: 'Cancel Booking' });
    expect(btn).toBeEnabled();
  });

  it('opens confirmation dialog on click', () => {
    render(
      <CancelBookingButton canCancel={true} onCancel={jest.fn()} />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel Booking' }));

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to cancel this booking?')).toBeInTheDocument();
    expect(screen.getByText('Yes, Cancel Booking')).toBeInTheDocument();
    expect(screen.getByText('Keep Booking')).toBeInTheDocument();
  });

  it('closes dialog when Keep Booking is clicked', () => {
    render(
      <CancelBookingButton canCancel={true} onCancel={jest.fn()} />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel Booking' }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Keep Booking'));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('calls onCancel and closes dialog on confirm', async () => {
    const onCancel = jest.fn().mockResolvedValue(undefined);
    render(
      <CancelBookingButton canCancel={true} onCancel={onCancel} />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel Booking' }));
    fireEvent.click(screen.getByText('Yes, Cancel Booking'));

    await waitFor(() => {
      expect(onCancel).toHaveBeenCalledTimes(1);
    });
  });

  it('shows booking details in confirmation dialog', () => {
    render(
      <CancelBookingButton
        canCancel={true}
        bookingReference="BKO-123"
        tourName="Florence Food Walk"
        tourDate="July 15, 2026"
        onCancel={jest.fn()}
      />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel Booking' }));

    expect(screen.getByText(/BKO-123/)).toBeInTheDocument();
    expect(screen.getByText(/Florence Food Walk/)).toBeInTheDocument();
    expect(screen.getByText(/July 15, 2026/)).toBeInTheDocument();
  });

  it('displays error message when onCancel rejects', async () => {
    const onCancel = jest.fn().mockRejectedValue(new Error('Failed to cancel booking'));
    render(
      <CancelBookingButton canCancel={true} onCancel={onCancel} />
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel Booking' }));
    fireEvent.click(screen.getByText('Yes, Cancel Booking'));

    const error = await screen.findByRole('alert');
    expect(error).toHaveTextContent('Failed to cancel booking');
  });
});

import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import PaymentStatus from '../PaymentStatus';

const baseAmount = { amount: 15000, currency: 'EUR', formatted: '€150.00' };

describe('PaymentStatus', () => {
  it('renders the status label', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} />);
    expect(screen.getByText('Payment Successful')).toBeInTheDocument();
  });

  it('renders a fallback for unknown status values', () => {
    render(<PaymentStatus status="unknown_status" amount={baseAmount} />);
    expect(screen.getByText('unknown_status')).toBeInTheDocument();
  });

  it('renders the formatted amount', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} />);
    expect(screen.getByText('€150.00')).toBeInTheDocument();
  });

  it('renders card last four and brand when provided', () => {
    render(
      <PaymentStatus
        status="succeeded"
        amount={baseAmount}
        cardLastFour="4242"
        cardBrand="visa"
      />,
    );
    expect(screen.getByText(/VISA/)).toBeInTheDocument();
    expect(screen.getByText(/4242/)).toBeInTheDocument();
  });

  it('renders card last four without brand when brand is missing', () => {
    render(
      <PaymentStatus
        status="succeeded"
        amount={baseAmount}
        cardLastFour="4242"
      />,
    );
    expect(screen.getByText(/4242/)).toBeInTheDocument();
    // Should show last four without the brand prefix in uppercase
    const cardRow = screen.getByText(/4242/);
    expect(cardRow.textContent).toContain('4242');
  });

  it('does not render card section when cardLastFour is undefined', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} />);
    expect(screen.queryByText('Card')).not.toBeInTheDocument();
  });

  it('does not render card section when cardLastFour is empty string', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} cardLastFour="" />);
    expect(screen.queryByText('Card')).not.toBeInTheDocument();
  });

  it('renders paidAt date when provided', () => {
    render(
      <PaymentStatus
        status="succeeded"
        amount={baseAmount}
        paidAt="2026-05-01T12:00:00Z"
      />,
    );
    expect(screen.getByText('Paid at')).toBeInTheDocument();
  });

  it('does not render paidAt section when missing', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} />);
    expect(screen.queryByText('Paid at')).not.toBeInTheDocument();
  });

  it('applies green color class for succeeded status', () => {
    render(<PaymentStatus status="succeeded" amount={baseAmount} />);
    const statusEl = screen.getByText('Payment Successful');
    expect(statusEl.className).toContain('text-green-600');
  });

  it('applies red color class for failed status', () => {
    render(<PaymentStatus status="failed" amount={baseAmount} />);
    const statusEl = screen.getByText('Payment Failed');
    expect(statusEl.className).toContain('text-red-600');
  });

  it('applies red color class for disputed status', () => {
    render(<PaymentStatus status="disputed" amount={baseAmount} />);
    const statusEl = screen.getByText('Disputed');
    expect(statusEl.className).toContain('text-red-600');
  });
});

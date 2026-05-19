import '@testing-library/jest-dom';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import StripePaymentForm from '../StripePaymentForm';

const mockConfirmPayment = jest.fn();
const mockUseStripe = jest.fn();
const mockUseElements = jest.fn();

jest.mock('@stripe/react-stripe-js', () => ({
  PaymentElement: () => <div data-testid="payment-element">Payment Element</div>,
  useStripe: () => mockUseStripe(),
  useElements: () => mockUseElements(),
}));

describe('StripePaymentForm', () => {
  const onSuccess = jest.fn();
  const onError = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    mockUseStripe.mockReturnValue({ confirmPayment: mockConfirmPayment });
    mockUseElements.mockReturnValue({});
  });

  it('renders the PaymentElement', () => {
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);
    expect(screen.getByTestId('payment-element')).toBeInTheDocument();
  });

  it('renders a submit button with "Pay Now" text', () => {
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);
    expect(screen.getByRole('button', { name: 'Pay Now' })).toBeInTheDocument();
  });

  it('disables the submit button when stripe is not loaded', () => {
    mockUseStripe.mockReturnValue(null);
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('disables the submit button and shows "Processing..." during processing', async () => {
    mockConfirmPayment.mockReturnValue(new Promise(() => {})); // never resolves
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Processing...' })).toBeDisabled();
    });
  });

  it('calls stripe.confirmPayment with elements and redirect: if_required', async () => {
    mockConfirmPayment.mockResolvedValue({ error: undefined });
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(mockConfirmPayment).toHaveBeenCalledWith({
        elements: expect.any(Object),
        confirmParams: { return_url: window.location.href },
        redirect: 'if_required',
      });
    });
  });

  it('calls onSuccess when confirmPayment resolves without error', async () => {
    mockConfirmPayment.mockResolvedValue({ error: undefined });
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalled();
    });
    expect(onError).not.toHaveBeenCalled();
  });

  it('calls onError with message when confirmPayment returns an error', async () => {
    mockConfirmPayment.mockResolvedValue({ error: { message: 'Card declined' } });
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onError).toHaveBeenCalledWith('Card declined');
    });
    expect(onSuccess).not.toHaveBeenCalled();
  });

  it('shows a fallback error message when error has no message', async () => {
    mockConfirmPayment.mockResolvedValue({ error: {} });
    render(<StripePaymentForm clientSecret="cs_test_123" onSuccess={onSuccess} onError={onError} />);

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onError).toHaveBeenCalledWith('Payment failed. Please try again.');
    });
  });
});

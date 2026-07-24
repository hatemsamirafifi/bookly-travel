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

// F16: StripePaymentForm now reads copy via next-intl ('booking' namespace) and
// builds return_url from bookingReference + locale. Mock the translator with
// the same shape the real hook exposes (t(key) → string), so the button label
// and error fallback assertions stay human-readable.
jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const translations: Record<string, string> = {
      payButton: 'Pay Now',
      paying: 'Processing...',
      'errors.paymentFailed': 'Payment failed. Please try again.',
      'errors.generic': 'Something went wrong.',
    };
    return translations[key] ?? key;
  },
}));

describe('StripePaymentForm', () => {
  const onSuccess = jest.fn();
  const onError = jest.fn();
  const bookingReference = 'BK-REF-1';
  const locale = 'en';

  const renderForm = () =>
    render(
      <StripePaymentForm
        clientSecret="cs_test_123"
        bookingReference={bookingReference}
        locale={locale}
        onSuccess={onSuccess}
        onError={onError}
      />,
    );

  beforeEach(() => {
    jest.clearAllMocks();
    mockUseStripe.mockReturnValue({ confirmPayment: mockConfirmPayment });
    mockUseElements.mockReturnValue({});
  });

  it('renders the PaymentElement', () => {
    renderForm();
    expect(screen.getByTestId('payment-element')).toBeInTheDocument();
  });

  it('renders a submit button with "Pay Now" text', () => {
    renderForm();
    expect(screen.getByRole('button', { name: 'Pay Now' })).toBeInTheDocument();
  });

  it('disables the submit button when stripe is not loaded', () => {
    mockUseStripe.mockReturnValue(null);
    renderForm();
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('disables the submit button and shows "Processing..." during processing', async () => {
    mockConfirmPayment.mockReturnValue(new Promise(() => {})); // never resolves
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Processing...' })).toBeDisabled();
    });
  });

  it('calls stripe.confirmPayment with elements, redirect: if_required, and return_url pointing at the confirmation page', async () => {
    mockConfirmPayment.mockResolvedValue({ error: undefined });
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    const expectedReturnUrl = `${window.location.origin}/${locale}/booking/confirmation?ref=${bookingReference}`;

    await waitFor(() => {
      expect(mockConfirmPayment).toHaveBeenCalledWith({
        elements: expect.any(Object),
        confirmParams: { return_url: expectedReturnUrl },
        redirect: 'if_required',
      });
    });
  });

  it('calls onSuccess when confirmPayment resolves without error', async () => {
    mockConfirmPayment.mockResolvedValue({ error: undefined });
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalled();
    });
    expect(onError).not.toHaveBeenCalled();
  });

  it('calls onError with message when confirmPayment returns an error', async () => {
    mockConfirmPayment.mockResolvedValue({ error: { message: 'Card declined' } });
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onError).toHaveBeenCalledWith('Card declined');
    });
    expect(onSuccess).not.toHaveBeenCalled();
  });

  it('shows a fallback error message when error has no message', async () => {
    mockConfirmPayment.mockResolvedValue({ error: {} });
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onError).toHaveBeenCalledWith('Payment failed. Please try again.');
    });
  });

  it('resets the processing flag and calls onError when confirmPayment rejects', async () => {
    mockConfirmPayment.mockRejectedValue(new Error('network'));
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Pay Now' }));

    await waitFor(() => {
      expect(onError).toHaveBeenCalledWith('Payment failed. Please try again.');
    });

    // finally guarantees the button un-sticks (back to "Pay Now", not disabled by processing)
    expect(screen.getByRole('button', { name: 'Pay Now' })).not.toBeDisabled();
  });
});
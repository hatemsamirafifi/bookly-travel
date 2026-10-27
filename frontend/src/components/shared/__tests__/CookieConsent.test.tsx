import { fireEvent, render, screen } from '@testing-library/react';
import CookieConsentBanner from '../CookieConsent';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) =>
    ({ message: 'We use cookies to improve your experience.', accept: 'Accept', reject: 'Reject' }[key] ?? key),
}));

const CONSENT_MESSAGE = 'We use cookies to improve your experience.';

describe('CookieConsentBanner', () => {
  beforeEach(() => {
    document.cookie = 'bookly_cookie_consent=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  });

  it('renders the message with accept and reject buttons carrying aria labels', () => {
    render(<CookieConsentBanner />);

    expect(screen.getByText(CONSENT_MESSAGE)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Accept' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument();
    expect(screen.getByRole('complementary', { name: 'Cookie consent' })).toBeInTheDocument();
  });

  it('keeps the message block compact so it cannot out-size the page h1 as an LCP candidate', () => {
    render(<CookieConsentBanner />);

    const message = screen.getByText(CONSENT_MESSAGE);
    expect(message).toHaveClass('w-full', 'sm:flex-1', 'text-xs', 'leading-snug', 'max-w-prose', 'sm:max-w-none');

    const banner = message.parentElement;
    expect(banner).toHaveClass('fixed', 'bottom-0', 'left-0', 'right-0', 'z-50', 'bg-[#0A2540]');
  });

  it('keeps both touch targets at least 44px tall', () => {
    render(<CookieConsentBanner />);

    expect(screen.getByRole('button', { name: 'Accept' })).toHaveClass('min-h-[44px]');
    expect(screen.getByRole('button', { name: 'Reject' })).toHaveClass('min-h-[44px]');
  });

  it('still grants consent under the bookly cookie and hides the banner on accept', () => {
    render(<CookieConsentBanner />);

    fireEvent.click(screen.getByRole('button', { name: 'Accept' }));

    expect(document.cookie).toContain('bookly_cookie_consent=true');
    expect(screen.queryByText(CONSENT_MESSAGE)).not.toBeInTheDocument();
  });
});

'use client';

import CookieConsent from 'react-cookie-consent';

export default function CookieConsentBanner() {
  return (
    <CookieConsent
      location="bottom"
      buttonText="Accept"
      declineButtonText="Reject"
      enableDeclineButton
      cookieName="bookly_cookie_consent"
      style={{
        background: '#0A2540',
        color: '#F7F9FB',
        padding: '16px 24px',
        fontSize: '14px',
        alignItems: 'center',
      }}
      buttonStyle={{
        background: '#FFB800',
        color: '#0A2540',
        borderRadius: '12px',
        padding: '8px 16px',
        fontSize: '14px',
        fontWeight: 600,
      }}
      declineButtonStyle={{
        background: 'transparent',
        color: '#F7F9FB',
        border: '1px solid #E2E8F0',
        borderRadius: '12px',
        padding: '8px 16px',
        fontSize: '14px',
      }}
      expires={365}
    >
      We use cookies to enhance your browsing experience and analyze our traffic. By clicking &quot;Accept&quot;, you consent to our use of cookies.
    </CookieConsent>
  );
}

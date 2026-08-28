'use client';

import CookieConsent from 'react-cookie-consent';
import { useTranslations } from 'next-intl';

export default function CookieConsentBanner() {
  const t = useTranslations('cookieConsent');

  return (
    <aside aria-label="Cookie consent">
      <CookieConsent
        location="bottom"
        disableStyles
        enableDeclineButton
        cookieName="bookly_cookie_consent"
        expires={365}
        containerClasses="fixed bottom-0 left-0 right-0 z-50 flex flex-wrap items-center justify-between gap-4 bg-[#0A2540] px-6 py-4 text-sm text-[#F7F9FB]"
        contentClasses="flex-1"
        buttonWrapperClasses="flex flex-wrap items-center gap-3"
        buttonText={t('accept')}
        declineButtonText={t('reject')}
        buttonClasses="rounded-xl bg-[#FFB800] px-4 py-2 text-sm font-semibold text-[#0A2540] transition-colors hover:bg-[#e5a700] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FFB800] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0A2540]"
        declineButtonClasses="rounded-xl border border-[#E2E8F0] bg-transparent px-4 py-2 text-sm text-[#F7F9FB] transition-colors hover:border-[#F7F9FB]/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F7F9FB]/40"
        ariaAcceptLabel={t('accept')}
        ariaDeclineLabel={t('reject')}
      >
        {t('message')}
      </CookieConsent>
    </aside>
  );
}
import { getRequestConfig } from 'next-intl/server';
import { routing } from './routing';

export default getRequestConfig(async ({ requestLocale, locale: explicitLocale }) => {
  // Spec 014: an explicit `locale` is passed when a server function is called
  // with `getTranslations({ locale })` — e.g. the root-level `/v/{reference}`
  // verification page (outside the `[locale]` segment) negotiates locale from
  // the Accept-Language header. Prefer it over the (undefined) segment value.
  let locale = explicitLocale ?? await requestLocale;

  // Validate that the incoming locale is supported
  if (!locale || !routing.locales.includes(locale as typeof routing.locales[number])) {
    locale = routing.defaultLocale;
  }

  return {
    locale,
    messages: (await import(`../../messages/${locale}.json`)).default,
  };
});
'use client';

import { useParams, usePathname, useRouter } from 'next/navigation';
import { locales } from '@/i18n/routing';

const LOCALE_LABELS: Record<string, string> = {
  en: 'English',
  es: 'Español',
  it: 'Italiano',
};

export default function LocaleSwitcher() {
  const params = useParams();
  const pathname = usePathname();
  const router = useRouter();
  const currentLocale = (params?.locale as string) || 'en';

  const switchTo = (locale: string) => {
    if (locale === currentLocale) return;
    const newPath = pathname.replace(`/${currentLocale}`, `/${locale}`);
    router.push(newPath);
  };

  return (
    <div className="relative" aria-label="Switch language">
      <select
        value={currentLocale}
        onChange={(e) => switchTo(e.target.value)}
        className="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500/30"
        aria-label="Switch language"
      >
        {locales.map((locale) => (
          <option key={locale} value={locale}>
            {LOCALE_LABELS[locale]}
          </option>
        ))}
      </select>
    </div>
  );
}

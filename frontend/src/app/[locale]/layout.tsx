import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages } from 'next-intl/server';
import { routing } from '@/i18n/routing';
import type { Locale } from '@/i18n/routing';
import { AuthProvider } from '@/lib/hooks/useAuth';
import QueryProvider from '@/lib/query-provider';
import { Inter } from "next/font/google";
import ErrorBoundary from '@/components/shared/ErrorBoundary';
import ErrorFallback from '@/components/shared/ErrorFallback';
import CookieConsentBanner from '@/components/shared/CookieConsent';
import '../globals.css';

export const metadata: Metadata = {
  title: {
    default: 'Bookly - Discover & Book Tours Worldwide',
  },
  description: 'Book unforgettable tours and experiences worldwide with Bookly.',
};

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;

  if (!routing.locales.includes(locale as Locale)) {
    notFound();
  }

  const messages = await getMessages();

  return (
    <html lang={locale} className={`${inter.variable} h-full antialiased`}>
      <body className="min-h-full flex flex-col font-sans">
        <NextIntlClientProvider messages={messages}>
          <QueryProvider>
            <AuthProvider>
              <ErrorBoundary fallback={<ErrorFallback />}>
                {children}
                <CookieConsentBanner />
              </ErrorBoundary>
            </AuthProvider>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}

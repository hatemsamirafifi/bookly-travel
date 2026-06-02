import { NextIntlClientProvider } from 'next-intl';
import { getMessages } from 'next-intl/server';
import { notFound } from 'next/navigation';
import { routing } from '@/i18n/routing';
import type { Locale } from '@/i18n/routing';
import { AuthProvider } from '@/lib/hooks/useAuth';
import QueryProvider from '@/lib/query-provider';
import Header from '@/components/layout/Header';
import Footer from '@/components/layout/Footer';
import { Inter } from "next/font/google";
import ErrorBoundary from '@/components/shared/ErrorBoundary';
import CookieConsentBanner from '@/components/shared/CookieConsent';

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
              <ErrorBoundary>
                <Header locale={locale} />
                <main id="main-content" className="flex-1">{children}</main>
                <Footer locale={locale} />
                <CookieConsentBanner />
              </ErrorBoundary>
            </AuthProvider>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}

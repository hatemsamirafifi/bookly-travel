import Link from 'next/link';
import { getTranslations } from 'next-intl/server';

export default async function NotFound({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'notFound' });

  return (
    <main className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
      <h1 className="text-6xl font-extrabold text-[#0A2540]">404</h1>
      <p className="mt-4 text-xl font-semibold text-[#0A2540]">{t('title')}</p>
      <p className="mt-2 text-[#5A6B7B]">{t('subtitle')}</p>
      <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
        <Link
          href={`/${locale}`}
          className="rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600] transition-colors"
        >
          {t('goHome')}
        </Link>
        <Link
          href={`/${locale}/search`}
          className="rounded-xl bg-[#F7F9FB] px-5 py-2.5 text-sm font-semibold text-[#0A2540] hover:bg-gray-200 transition-colors"
        >
          {t('browseTours')}
        </Link>
      </div>
    </main>
  );
}

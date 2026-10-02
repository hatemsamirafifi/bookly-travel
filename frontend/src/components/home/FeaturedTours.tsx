import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import TourCard from '@/components/search/TourCard';
import { getTranslations } from 'next-intl/server';

interface FeaturedToursProps {
  tours: TourCardType[];
  locale: string;
}

export default async function FeaturedTours({ tours, locale }: FeaturedToursProps) {
  if (tours.length === 0) return null;
  const t = await getTranslations({ locale, namespace: 'home' });

  return (
    <section className="bg-surface-alt py-12">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-8 flex items-center justify-between">
          <h2 className="text-2xl font-bold text-bookly-navy">{t('featuredTours')}</h2>
          <Link
            href={`/${locale}/search`}
            className="text-sm font-medium text-bookly-navy hover:text-bookly-gold"
          >
            {t('viewAll')} &rarr;
          </Link>
        </div>

        <div className="flex gap-6 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide">
          {tours.map((tour, index) => (
            <div key={tour.id} className="min-w-[300px] max-w-[300px] shrink-0 snap-start">
              <TourCard tour={tour} locale={locale} imagePriority={index === 0} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

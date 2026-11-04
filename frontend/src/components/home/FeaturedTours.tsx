import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import TourCard from '@/components/search/TourCard';
import { getTranslations } from 'next-intl/server';

interface FeaturedToursProps {
  tours: TourCardType[];
  locale: string;
  title?: string;
}

export default async function FeaturedTours({ tours, locale, title }: FeaturedToursProps) {
  if (!tours || tours.length === 0) return null;
  const t = await getTranslations({ locale, namespace: 'home' });
  const sectionTitle = title ?? t('featuredTours');

  return (
    <section className="bg-surface-alt py-12 border-b border-border/40" aria-label={sectionTitle}>
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-6 flex items-center justify-between">
          <h2 className="text-xl font-bold text-bookly-navy sm:text-2xl">{sectionTitle}</h2>
          <Link
            href={`/${locale}/search`}
            className="text-sm font-semibold text-bookly-navy hover:text-bookly-gold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded"
          >
            {t('viewAll')} &rarr;
          </Link>
        </div>

        <div className="flex gap-6 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide scroll-smooth">
          {tours.map((tour, index) => (
            <div key={tour.id} className="min-w-[280px] max-w-[280px] shrink-0 snap-start sm:min-w-[300px] sm:max-w-[300px]">
              <TourCard tour={tour} locale={locale} imagePriority={index === 0} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

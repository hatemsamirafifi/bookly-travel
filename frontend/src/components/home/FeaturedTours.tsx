import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import TourCard from '@/components/search/TourCard';

interface FeaturedToursProps {
  tours: TourCardType[];
  locale: string;
}

export default function FeaturedTours({ tours, locale }: FeaturedToursProps) {
  if (tours.length === 0) return null;

  return (
    <section className="py-12 bg-[#F7F9FB]">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-8 flex items-center justify-between">
          <h2 className="text-2xl font-bold text-[#0A2540]">Featured Tours</h2>
          <Link
            href={`/${locale}/search`}
            className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800]"
          >
            View all &rarr;
          </Link>
        </div>

        <div className="flex gap-6 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide">
          {tours.map((tour) => (
            <div key={tour.id} className="min-w-[300px] max-w-[300px] shrink-0 snap-start">
              <TourCard tour={tour} locale={locale} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

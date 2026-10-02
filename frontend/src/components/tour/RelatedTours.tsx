import type { TourCard as TourCardType } from '@/lib/api/types';
import TourCard from '@/components/search/TourCard';

interface RelatedToursProps {
  tours: TourCardType[];
  locale: string;
  title: string;
}

export default function RelatedTours({ tours, locale, title }: RelatedToursProps) {
  if (tours.length === 0) return null;

  return (
    <section id="related" className="scroll-mt-20 py-8" aria-labelledby="related-title">
      <h2 id="related-title" className="mb-6 text-xl font-semibold text-bookly-navy">{title}</h2>
      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {tours.map((tour) => <TourCard key={tour.id} tour={tour} locale={locale} />)}
      </div>
    </section>
  );
}

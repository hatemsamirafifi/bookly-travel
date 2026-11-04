import type { CuratedRail } from '@/lib/api/types';
import FeaturedTours from './FeaturedTours';

interface ThemedTourRailsProps {
  rails?: CuratedRail[];
  locale: string;
}

export default function ThemedTourRails({ rails, locale }: ThemedTourRailsProps) {
  if (!rails || rails.length === 0) return null;

  const validRails = rails.filter((rail) => Array.isArray(rail.tours) && rail.tours.length > 0);
  if (validRails.length === 0) return null;

  return (
    <>
      {validRails.map((rail) => (
        <FeaturedTours
          key={rail.id || rail.slug}
          tours={rail.tours}
          locale={locale}
          title={rail.title}
        />
      ))}
    </>
  );
}


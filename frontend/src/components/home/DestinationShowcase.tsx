import Link from 'next/link';
import ImageWithFallback from '@/components/ui/ImageWithFallback';
import type { Destination } from '@/lib/api/types';
import { useTranslations } from 'next-intl';

interface DestinationShowcaseProps {
  destinations: Destination[];
  locale: string;
  title?: string;
}

export default function DestinationShowcase({ destinations, locale, title }: DestinationShowcaseProps) {
  const t = useTranslations('home');
  if (destinations.length === 0) return null;

  return (
    <section className="bg-surface-alt py-12" aria-labelledby="top-destinations-heading">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 id="top-destinations-heading" className="mb-6 text-2xl font-bold text-bookly-navy">
          {title ?? t('topDestinations')}
        </h2>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {destinations.map((dest) => (
            <Link
              key={dest.slug}
              href={`/${locale}/destinations/${dest.slug}`}
              className="group relative overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition duration-200 hover:shadow-md focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2"
            >
              <div className="relative aspect-[16/9] bg-surface/50">
                {dest.image_url ? (
                  <ImageWithFallback
                    src={dest.image_url}
                    alt={dest.name}
                    fill
                    sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                    className="object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                ) : (
                  <div className="flex h-full items-center justify-center text-gray-400 text-xs">
                    <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {t('noImageAvailable')}
                  </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-4">
                  <h3 className="text-lg font-semibold text-white drop-shadow-sm">{dest.name}</h3>
                  <p className="text-xs text-gray-200 drop-shadow-sm">
                    {dest.country && `${dest.country} · `}
                    {t(dest.tour_count === 1 ? 'tourCount' : 'tourCount_plural', { count: dest.tour_count })}
                  </p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}

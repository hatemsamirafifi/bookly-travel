import Link from 'next/link';
import Image from 'next/image';
import type { Destination } from '@/lib/api/types';

interface DestinationShowcaseProps {
  destinations: Destination[];
  locale: string;
  title?: string;
}

export default function DestinationShowcase({ destinations, locale, title = 'Top Destinations' }: DestinationShowcaseProps) {
  if (destinations.length === 0) return null;

  return (
    <section className="py-12 bg-[#F7F9FB]">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 className="mb-8 text-2xl font-bold text-[#0A2540]">{title}</h2>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {destinations.map((dest) => (
            <Link
              key={dest.slug}
              href={`/${locale}/destinations/${dest.slug}`}
              className="group relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md"
            >
              <div className="relative aspect-[16/9] bg-gray-100">
                {dest.image_url ? (
                  <Image
                    src={dest.image_url}
                    alt={dest.name}
                    fill
                    sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                    className="object-cover transition-transform group-hover:scale-105"
                  />
                ) : (
                  <div className="flex h-full items-center justify-center text-gray-400">
                    <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-4">
                  <h3 className="text-lg font-semibold text-white">{dest.name}</h3>
                  <p className="text-sm text-gray-200">
                    {dest.country && `${dest.country} · `}{dest.tour_count} tour{dest.tour_count !== 1 ? 's' : ''}
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

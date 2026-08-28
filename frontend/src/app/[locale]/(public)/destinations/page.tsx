import type { Metadata } from 'next';
import { getDestinations } from '@/lib/api/destinations';
import type { Destination } from '@/lib/api/types';
import DestinationShowcase from '@/components/home/DestinationShowcase';

interface DestinationsPageProps {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata(): Promise<Metadata> {
  return {
    title: 'Top Destinations | Bookly',
    description: 'Explore top destinations and tours worldwide on Bookly.',
    robots: { index: true, follow: true },
  };
}

export default async function DestinationsPage({ params }: DestinationsPageProps) {
  const { locale } = await params;
  let destinations: Destination[] = [];
  try {
    const res = await getDestinations(locale);
    destinations = res.data || [];
  } catch {
    destinations = [];
  }

  return (
    <div className="min-h-[60vh] bg-[#F7F9FB] py-8">
      <DestinationShowcase destinations={destinations} locale={locale} title="All Destinations" />
    </div>
  );
}
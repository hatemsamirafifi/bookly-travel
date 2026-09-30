import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getTourDetail } from '@/lib/api/tours';
import { NotFoundError } from '@/lib/api/client';
import TourDetail from '@/components/tour/TourDetail';
import { TouristTripSchema, getSafeAbsoluteImageUrls } from '@/components/seo/StructuredData';

interface TourDetailPageProps {
  params: Promise<{ locale: string; slug: string }>;
}

export async function generateMetadata({ params }: TourDetailPageProps): Promise<Metadata> {
  const { locale, slug } = await params;

  try {
    const { data } = await getTourDetail(slug, locale);
    const imageUrls = getSafeAbsoluteImageUrls(data.images, process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com');
    return {
      title: data.seo.meta_title,
      description: data.seo.meta_description,
      alternates: {
        canonical: data.seo.canonical_url,
        languages: data.seo.hreflang,
      },
      openGraph: {
        title: data.seo.meta_title,
        description: data.seo.meta_description,
        type: 'website',
        locale: data.content_locale,
        images: imageUrls.map((url) => ({ url })),
      },
    };
  } catch {
    return {
      title: 'Tour Not Found | Bookly',
      robots: { index: false },
    };
  }
}

export default async function TourDetailPage({ params }: TourDetailPageProps) {
  const { locale, slug } = await params;

  let data;
  try {
    const response = await getTourDetail(slug, locale);
    data = response.data;
  } catch (e) {
    if (e instanceof NotFoundError) {
      notFound();
    }
    throw e;
  }

  return (
    <>
      <TouristTripSchema tour={data} locale={locale} />
      <TourDetail tour={data} locale={locale} />
    </>
  );
}

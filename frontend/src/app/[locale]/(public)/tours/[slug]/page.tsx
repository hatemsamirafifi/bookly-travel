import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getTranslations } from 'next-intl/server';
import { getTourDetail } from '@/lib/api/tours';
import { GoneError, NotFoundError } from '@/lib/api/client';
import TourDetail from '@/components/tour/TourDetail';
import { TouristTripSchema, getSafeAbsoluteImageUrls } from '@/components/seo/StructuredData';

interface TourDetailPageProps {
  params: Promise<{ locale: string; slug: string }>;
}

type FailureKind = 'not-found' | 'withdrawn' | 'error';

// Failure metadata is always localized to the requested page locale with a
// noindex directive. The explicit locale resolves through the real next-intl
// request config and its EN/ES/IT catalogs; upstream error text never reaches
// the metadata, so only the catalog copy for the failure kind is used. No
// fallback strings are hardcoded here: a catalog or configuration fault must
// surface instead of being masked as fabricated interface copy.
async function localizedFailureMetadata(locale: string, kind: FailureKind): Promise<Metadata> {
  const t = await getTranslations({ locale, namespace: 'tour.states' });
  const title = kind === 'withdrawn' ? t('withdrawnTitle') : kind === 'not-found' ? t('notFoundTitle') : t('errorTitle');
  return {
    title: `${title} | Bookly`,
    robots: { index: false },
  };
}

// Sync presentational copy for a known archived tour. Strings resolve in the
// async page before render so this child stays a plain sync component.
function WithdrawnTourNotice({ title, description }: { title: string; description: string }) {
  return (
    <div className="py-12 text-center">
      <h1 className="text-xl font-bold text-primary">{title}</h1>
      <p className="mt-2 text-text-muted">{description}</p>
    </div>
  );
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
  } catch (e) {
    if (e instanceof GoneError) {
      return localizedFailureMetadata(locale, 'withdrawn');
    }
    if (e instanceof NotFoundError) {
      return localizedFailureMetadata(locale, 'not-found');
    }
    return localizedFailureMetadata(locale, 'error');
  }
}

export default async function TourDetailPage({ params }: TourDetailPageProps) {
  const { locale, slug } = await params;

  let data;
  try {
    const response = await getTourDetail(slug, locale);
    data = response.data;
  } catch (e) {
    // A known archived tour keeps its backend 410 meaning: a localized
    // withdrawn state with no booking action, never a 404. Recoverable
    // failures rethrow to the route-localized error boundary; only a real
    // 404 becomes notFound(). Next.js has no HTTP-410 renderer, so no
    // status-code mechanism is invented here.
    if (e instanceof GoneError) {
      const t = await getTranslations({ locale, namespace: 'tour.states' });
      return <WithdrawnTourNotice title={t('withdrawnTitle')} description={t('withdrawnDescription')} />;
    }
    if (e instanceof NotFoundError) {
      notFound();
    }
    throw e;
  }

  return (
    <>
      <TouristTripSchema tour={data} locale={locale} />
      <TourDetail
        tour={data}
        locale={locale}
        todayUtc={new Date().toISOString().slice(0, 10)}
      />
    </>
  );
}

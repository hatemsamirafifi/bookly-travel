import type { TourDetail, TourImage } from '@/lib/api/types';

function serializeJsonLd(value: unknown): string {
  // JSON-LD is embedded in an HTML script element. Escaping '<' prevents
  // untrusted tour/blog text from closing that element before the JSON ends.
  return JSON.stringify(value, null, 2).replace(/</g, '\\u003c');
}

export function getSafeAbsoluteImageUrls(images: Pick<TourImage, 'url'>[], baseUrl: string): string[] {
  return Array.from(new Set(images.flatMap((image) => {
    try {
      const url = new URL(image.url, baseUrl);
      return url.protocol === 'https:' || url.protocol === 'http:' ? [url.href] : [];
    } catch {
      return [];
    }
  })));
}

interface TouristTripSchemaProps {
  tour: TourDetail;
  locale: string;
}

export function TouristTripSchema({ tour, locale }: TouristTripSchemaProps) {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com';
  const imageUrls = getSafeAbsoluteImageUrls(tour.images, baseUrl);
  const meetingPointLabel = { en: 'Meeting point', es: 'Punto de encuentro', it: 'Punto di incontro' }[tour.content_locale];
  const description = [
    tour.description?.substring(0, 300),
    tour.meeting_point?.trim() ? `${meetingPointLabel}: ${tour.meeting_point.trim()}` : null,
  ].filter(Boolean).join(' · ');
  const available = !tour.availability.is_unavailable
    && !!tour.availability.next_available_date
    && tour.availability.available_dates.includes(tour.availability.next_available_date)
    && tour.pricing.base_price.amount > 0;

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'TouristTrip',
    inLanguage: tour.content_locale,
    name: tour.title,
    description,
    touristType: tour.category?.name || '',
    duration: `PT${tour.duration.minutes}M`,
    ...(tour.pricing.base_price.amount > 0 ? { offers: {
      '@type': 'Offer',
      price: (tour.pricing.base_price.amount / 100).toFixed(2),
      priceCurrency: tour.pricing.base_price.currency,
      availability: available
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      validFrom: available ? tour.availability.next_available_date : undefined,
    } } : {}),
    ...(tour.reviews.count > 0 && tour.reviews.average_rating > 0 && tour.reviews.average_rating <= 5 ? { aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: tour.reviews.average_rating.toFixed(1),
      reviewCount: tour.reviews.count,
      bestRating: '5',
      worstRating: '1',
    } } : {}),
    ...(tour.itinerary.length > 0 ? { itinerary: {
      '@type': 'ItemList',
      inLanguage: tour.itinerary_locale,
      itemListElement: tour.itinerary.map((day, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        identifier: day.day,
        name: day.title,
        description: day.description || undefined,
      })),
    } } : {}),
    ...(imageUrls.length > 0 ? { image: imageUrls } : {}),
    url: `${baseUrl}/${locale}/tours/${tour.slug}`,
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(schema) }}
    />
  );
}

interface OrganizationSchemaProps {
  locale: string;
}

export function OrganizationSchema({ locale }: OrganizationSchemaProps) {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com';

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Bookly',
    url: baseUrl,
    inLanguage: locale,
    description: 'Discover and instantly book the best tours worldwide.',
    sameAs: [],
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(schema) }}
    />
  );
}

interface ItemListSchemaProps {
  items: { name: string; url: string }[];
  name: string;
}

export function ItemListSchema({ items, name }: ItemListSchemaProps) {
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name,
    itemListElement: items.map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      item: {
        '@type': 'Thing',
        name: item.name,
        url: item.url,
      },
    })),
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(schema) }}
    />
  );
}

interface BlogPostingSchemaProps {
  headline: string;
  description: string;
  datePublished?: string | null;
  dateModified?: string | null;
  authorName: string;
  authorAvatarUrl?: string | null;
  image?: string | null;
  inLanguage: string;
  url: string;
}

export function BlogPostingSchema({
  headline,
  description,
  datePublished,
  dateModified,
  authorName,
  authorAvatarUrl,
  image,
  inLanguage,
  url,
}: BlogPostingSchemaProps) {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com';

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline,
    description,
    inLanguage,
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': url,
    },
    url,
    ...(image ? { image } : {}),
    ...(datePublished ? { datePublished } : {}),
    ...(dateModified ? { dateModified } : {}),
    author: {
      '@type': 'Person',
      name: authorName,
      ...(authorAvatarUrl ? { image: authorAvatarUrl } : {}),
    },
    publisher: {
      '@type': 'Organization',
      name: 'Bookly',
      url: baseUrl,
      logo: {
        '@type': 'ImageObject',
        url: `${baseUrl}/logo.png`,
      },
    },
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(schema) }}
    />
  );
}

interface BreadcrumbItem {
  name: string;
  url: string;
}

interface BreadcrumbListSchemaProps {
  items: BreadcrumbItem[];
}

export function BreadcrumbListSchema({ items }: BreadcrumbListSchemaProps) {
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: serializeJsonLd(schema) }}
    />
  );
}


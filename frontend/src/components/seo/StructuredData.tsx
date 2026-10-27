import type { TourDetail, TourImage } from '@/lib/api/types';
import {
  areGroupBoundsValid,
  getEstimateCents,
  getOfferedFutureDates,
  isBookableAvailability,
  isOfferedFutureDate,
  isPositiveSafeInteger,
  isSupportedCurrency,
} from '@/lib/tours/booking-selection';
import { formatExactCentsDecimal } from '@/lib/utils';

function serializeJsonLd(value: unknown): string {
  // JSON-LD is embedded in an HTML script element. Escaping '<' prevents
  // untrusted tour/blog text from closing that element before the JSON ends.
  return JSON.stringify(value, null, 2).replace(/</g, '\\u003c');
}

export function getSafeAbsoluteImageUrls(images: Pick<TourImage, 'url'>[], baseUrl: string): string[] {
  return Array.from(new Set(images.flatMap((image) => {
    // Blank or whitespace-only URLs are absent media, not photos: resolving
    // them against the base would fabricate the homepage URL as an image.
    if (image.url.trim() === '') return [];
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
  const pageUrl = `${baseUrl}/${locale}/tours/${tour.slug}`;
  const imageUrls = getSafeAbsoluteImageUrls(tour.images, baseUrl);
  const meetingPointLabel = { en: 'Meeting point', es: 'Punto de encuentro', it: 'Punto di incontro' }[tour.content_locale];
  const description = [
    tour.description?.substring(0, 300),
    tour.meeting_point?.trim() ? `${meetingPointLabel}: ${tour.meeting_point.trim()}` : null,
  ].filter(Boolean).join(' · ');
  const availableDates = tour.availability;
  // Only supported, safe cents become an Offer. Formatting never validates pricing.
  const priceValid = isPositiveSafeInteger(tour.pricing.base_price.amount)
    && isSupportedCurrency(tour.pricing.base_price.currency);
  // Missing next-date metadata does not prevent explicit selection of another
  // offered future; validFrom itself must never be inferred.
  const todayUtc = new Date().toISOString().slice(0, 10);
  const boundsValid = areGroupBoundsValid(tour.group_size.min, tour.group_size.max);
  const minimumEstimateSafe = boundsValid
    && getEstimateCents(tour.pricing, tour.group_size.min) !== null;
  const offeredFutureDates = getOfferedFutureDates(availableDates, todayUtc);
  const available = priceValid
    && isBookableAvailability(availableDates)
    && boundsValid
    && minimumEstimateSafe
    && offeredFutureDates.length > 0;
  const nextValid = available
    && isOfferedFutureDate(availableDates.next_available_date, availableDates, todayUtc);
  const meetingPoint = tour.meeting_point?.trim() ? tour.meeting_point.trim() : '';

  const schema = {
    '@context': {
      '@vocab': 'https://schema.org/',
      name: { '@id': 'https://schema.org/name', '@language': tour.content_locale },
      description: { '@id': 'https://schema.org/description', '@language': tour.content_locale },
    },
    '@type': ['TouristTrip', 'Service'],
    name: tour.title,
    description,
    touristType: tour.category?.name || '',
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': pageUrl,
      inLanguage: tour.content_locale,
    },
    ...(meetingPoint ? { tripOrigin: { '@type': 'Place', name: meetingPoint } } : {}),
    ...(priceValid ? { offers: {
      '@type': 'Offer',
      price: formatExactCentsDecimal(tour.pricing.base_price.amount),
      priceCurrency: tour.pricing.base_price.currency,
      availability: available
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      validFrom: nextValid ? tour.availability.next_available_date : undefined,
    } } : {}),
    ...(isPositiveSafeInteger(tour.reviews.count) && Number.isFinite(tour.reviews.average_rating) && tour.reviews.average_rating > 0 && tour.reviews.average_rating <= 5 ? { aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: tour.reviews.average_rating.toFixed(1),
      reviewCount: tour.reviews.count,
      bestRating: '5',
      worstRating: '1',
    } } : {}),
    ...(tour.itinerary.length > 0 ? { itinerary: {
      '@type': 'ItemList',
      '@context': {
        name: { '@id': 'https://schema.org/name', '@language': tour.itinerary_locale },
        description: { '@id': 'https://schema.org/description', '@language': tour.itinerary_locale },
      },
      itemListElement: tour.itinerary.map((day, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        identifier: day.day,
        name: day.title,
        ...(day.description ? { description: day.description } : {}),
        ...(Array.isArray(day.stops) && day.stops.length > 0 ? { item: {
          '@type': 'ItemList',
          itemListElement: day.stops.map((stop, stopIndex) => ({
            '@type': 'ListItem',
            position: stopIndex + 1,
            item: {
              '@type': 'Thing',
              name: stop.title,
              ...(stop.description ? { description: stop.description } : {}),
            },
          })),
        } } : {}),
      })),
    } } : {}),
    ...(imageUrls.length > 0 ? { image: imageUrls } : {}),
    url: pageUrl,
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


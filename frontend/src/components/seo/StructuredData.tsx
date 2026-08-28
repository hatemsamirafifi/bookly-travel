import type { TourDetail } from '@/lib/api/types';

interface TouristTripSchemaProps {
  tour: TourDetail;
  locale: string;
}

export function TouristTripSchema({ tour, locale }: TouristTripSchemaProps) {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com';

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'TouristTrip',
    inLanguage: locale,
    name: tour.title,
    description: tour.description?.substring(0, 300) || '',
    touristType: tour.category?.name || '',
    duration: `PT${tour.duration.minutes}M`,
    offers: {
      '@type': 'Offer',
      price: (tour.pricing.base_price.amount / 100).toFixed(2),
      priceCurrency: tour.pricing.base_price.currency,
      availability: tour.availability.next_available_date
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      validFrom: tour.availability.next_available_date || undefined,
    },
    aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: tour.reviews.average_rating?.toFixed(1) || '0.0',
      reviewCount: tour.reviews.count,
      bestRating: '5',
      worstRating: '1',
    },
    itinerary: {
      '@type': 'Place',
      name: tour.location,
      address: tour.meeting_point || tour.location,
    },
    image: tour.images.length > 0 ? tour.images[0].url : undefined,
    url: `${baseUrl}/${locale}/tours/${tour.slug}`,
  };

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema, null, 2) }}
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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema, null, 2) }}
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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema, null, 2) }}
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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema, null, 2) }}
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
      dangerouslySetInnerHTML={{ __html: JSON.stringify(schema, null, 2) }}
    />
  );
}


# Sitemap & SEO Contracts

**Feature**: 006-public-search-discovery

## XML Sitemap

**Endpoint**: `GET /api/public/sitemap.xml`

Returns an XML sitemap listing all indexable public pages with their language variants.

### Response Headers

| Header | Value |
|--------|-------|
| `Content-Type` | `application/xml` |
| `Cache-Control` | `public, max-age=3600` |

### Response Body

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://bookly.com/en</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://bookly.com/en"/>
    <xhtml:link rel="alternate" hreflang="es" href="https://bookly.com/es"/>
    <xhtml:link rel="alternate" hreflang="it" href="https://bookly.com/it"/>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://bookly.com/en/tours/tuscany-wine-tasting</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://bookly.com/en/tours/tuscany-wine-tasting"/>
    <xhtml:link rel="alternate" hreflang="es" href="https://bookly.com/es/tours/tuscany-wine-tasting"/>
    <xhtml:link rel="alternate" hreflang="it" href="https://bookly.com/it/tours/tuscany-wine-tasting"/>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://bookly.com/en/categories/food-wine</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://bookly.com/en/categories/food-wine"/>
    <xhtml:link rel="alternate" hreflang="es" href="https://bookly.com/es/categories/food-wine"/>
    <xhtml:link rel="alternate" hreflang="it" href="https://bookly.com/it/categories/food-wine"/>
    <changefreq>daily</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc>https://bookly.com/en/destinations/florence</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://bookly.com/en/destinations/florence"/>
    <xhtml:link rel="alternate" hreflang="es" href="https://bookly.com/es/destinations/florence"/>
    <xhtml:link rel="alternate" hreflang="it" href="https://bookly.com/it/destinations/florence"/>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
</urlset>
```

## robots.txt

**Endpoint**: `GET /robots.txt` (served by Next.js or Laravel at root)

```text
User-agent: *
Allow: /en/
Allow: /es/
Allow: /it/
Disallow: /api/

Sitemap: https://bookly.com/sitemap.xml
```

## Structured Data (JSON-LD) per Page Type

### Tour Detail — TouristTrip

```json
{
  "@context": "https://schema.org",
  "@type": "TouristTrip",
  "name": "Tuscany Wine Tasting Experience",
  "description": "Explore the rolling hills of Tuscany...",
  "touristType": "Food & Wine",
  "duration": "PT5H",
  "offers": {
    "@type": "Offer",
    "price": "89.00",
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock",
    "validFrom": "2026-06-15"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.7",
    "reviewCount": "124",
    "bestRating": "5",
    "worstRating": "1"
  },
  "itinerary": {
    "@type": "Place",
    "name": "Florence, Italy",
    "address": "Piazza della Repubblica, Florence"
  },
  "image": "https://cdn.bookly.com/tours/42/cover.jpg"
}
```

### Category Page — ItemList

```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Food & Wine Tours",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "item": {
        "@type": "TouristTrip",
        "name": "Tuscany Wine Tasting Experience",
        "url": "https://bookly.com/en/tours/tuscany-wine-tasting"
      }
    }
  ]
}
```

### Destination Page — TouristDestination

```json
{
  "@context": "https://schema.org",
  "@type": "TouristDestination",
  "name": "Florence",
  "containsPlace": {
    "@type": "TouristTrip",
    "name": "Tuscany Wine Tasting Experience",
    "url": "https://bookly.com/en/tours/tuscany-wine-tasting"
  }
}
```

### Homepage — Organization + WebSite

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Bookly",
  "url": "https://bookly.com",
  "description": "Discover and instantly book the best tours.",
  "sameAs": []
}
```

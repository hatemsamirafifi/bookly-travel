import Head from 'next/head';

interface SEOHeadProps {
  title: string;
  description: string;
  canonical?: string;
  og?: {
    title?: string;
    description?: string;
    image?: string;
    type?: string;
  };
  hreflang?: Record<string, string>;
  jsonLd?: Record<string, unknown>[];
}

export default function SEOHead({ title, description, canonical, og, hreflang, jsonLd }: SEOHeadProps) {
  return (
    <Head>
      <title>{title}</title>
      <meta name="description" content={description} />
      {canonical && <link rel="canonical" href={canonical} />}

      {og && (
        <>
          <meta property="og:title" content={og.title || title} />
          <meta property="og:description" content={og.description || description} />
          <meta property="og:type" content={og.type || 'website'} />
          {og.image && <meta property="og:image" content={og.image} />}
        </>
      )}

      {hreflang &&
        Object.entries(hreflang).map(([locale, url]) => (
          <link key={locale} rel="alternate" hrefLang={locale} href={url} />
        ))}

      {jsonLd?.map((schema, i) => (
        <script
          key={i}
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }}
        />
      ))}
    </Head>
  );
}

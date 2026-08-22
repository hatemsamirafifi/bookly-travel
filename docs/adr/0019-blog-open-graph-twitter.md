# Blog Open Graph & Twitter: article OG + twitter cards

- **Blog detail**: `openGraph.type = 'article'` with `publishedTime`, `modifiedTime`,
  `authors` (from author profile), `section` (category), and `images` (cover image).
  `twitter: card = 'summary_large_image'` with the cover image.
- **Blog listing & category**: `openGraph.type = 'website'` (standard page OG).
  `twitter: card = 'summary'`.

No precedent exists for either (all existing OG is `type='website'`; no twitter cards
anywhere), so this is greenfield. Required by FR-011 (every public page MUST include OG
tags) and standard content-marketing social sharing. The article OG type carries
article-specific fields (publishedTime, modifiedTime, authors) important for blog SEO.
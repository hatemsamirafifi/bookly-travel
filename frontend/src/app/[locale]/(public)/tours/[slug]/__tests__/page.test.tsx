import { generateMetadata } from '../page';
import { getTourDetail } from '@/lib/api/tours';
import { NotFoundError } from '@/lib/api/client';

jest.mock('@/lib/api/tours', () => ({ getTourDetail: jest.fn() }));
jest.mock('@/components/tour/TourDetail', () => ({ __esModule: true, default: () => null }));

// FABRICATED FIXTURES below are unit-level stand-ins for the real public
// detail response shape (never a claimed backend response). They exercise how
// generateMetadata selects the actual derivative language, passes through
// real canonical/hreflang links and filters gallery URLs. Ordered days/stops,
// tripOrigin and escaping are covered by StructuredData.test.tsx, not here.
function fabricatedDetail(overrides: Record<string, unknown> = {}) {
  return {
    content_locale: 'en',
    images: [
      { url: 'https://cdn.example.com/cover.jpg', is_cover: true, alt: 'Cover', alt_locale: 'en' },
      { url: 'https://cdn.example.com/side.jpg', is_cover: false, alt: 'Side', alt_locale: 'en' },
    ],
    seo: {
      meta_title: 'English source | Bookly',
      meta_description: 'English source description',
      canonical_url: 'https://bookly.com/es/tours/example',
      hreflang: { en: 'https://bookly.com/en/tours/example', es: 'https://bookly.com/es/tours/example' },
    },
    ...overrides,
  };
}

it('uses the actual fallback content locale and complete gallery in tour metadata', async () => {
  (getTourDetail as jest.Mock).mockResolvedValue({ data: fabricatedDetail() });

  const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'example' }) });
  expect(metadata.openGraph).toMatchObject({ locale: 'en', images: [
    { url: 'https://cdn.example.com/cover.jpg' },
    { url: 'https://cdn.example.com/side.jpg' },
  ] });
  expect(metadata.description).toBe('English source description');
});

it('uses the selected current derivative language for metadata while canonical follows the requested locale', async () => {
  (getTourDetail as jest.Mock).mockResolvedValue({ data: fabricatedDetail({
    content_locale: 'es',
    seo: {
      meta_title: 'Título español actual | Bookly',
      meta_description: 'Descripción española actual.',
      canonical_url: 'https://bookly.com/es/tours/example',
      hreflang: {
        en: 'https://bookly.com/en/tours/example',
        es: 'https://bookly.com/es/tours/example',
        it: 'https://bookly.com/it/tours/example',
      },
    },
  }) });

  const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'example' }) });
  expect(metadata.title).toBe('Título español actual | Bookly');
  expect(metadata.description).toBe('Descripción española actual.');
  expect(metadata.openGraph).toMatchObject({ locale: 'es' });
  expect(metadata.alternates).toMatchObject({ canonical: 'https://bookly.com/es/tours/example' });
});

it('passes through actual hreflang links for the requested slug without inventing locales', async () => {
  const hreflang = {
    en: 'https://bookly.com/en/tours/example',
    es: 'https://bookly.com/es/tours/example',
    it: 'https://bookly.com/it/tours/example',
  };
  (getTourDetail as jest.Mock).mockResolvedValue({ data: fabricatedDetail({
    seo: {
      meta_title: 'Titolo italiano attuale | Bookly',
      meta_description: 'Descrizione italiana attuale.',
      canonical_url: 'https://bookly.com/it/tours/example',
      hreflang,
    },
  }) });

  const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'it', slug: 'example' }) });
  expect(metadata.alternates).toMatchObject({ canonical: 'https://bookly.com/it/tours/example', languages: hreflang });
  expect(Object.keys(metadata.alternates?.languages ?? {})).toEqual(['en', 'es', 'it']);
});

it('exposes only safe absolute gallery URLs and invents no photo when the gallery is empty', async () => {
  (getTourDetail as jest.Mock).mockResolvedValue({ data: fabricatedDetail({
    images: [
      { url: '/images/cover.jpg', is_cover: true, alt: 'Cover', alt_locale: 'en' },
      { url: 'https://cdn.example.com/side.jpg', is_cover: false, alt: 'Side', alt_locale: 'en' },
      { url: 'javascript:alert(1)', is_cover: false, alt: 'Unsafe', alt_locale: 'en' },
    ],
  }) });

  const withGallery = await generateMetadata({ params: Promise.resolve({ locale: 'en', slug: 'example' }) });
  expect(withGallery.openGraph?.images).toEqual([
    { url: 'https://bookly.com/images/cover.jpg' },
    { url: 'https://cdn.example.com/side.jpg' },
  ]);

  (getTourDetail as jest.Mock).mockResolvedValue({ data: fabricatedDetail({ images: [] }) });
  const withoutPhotos = await generateMetadata({ params: Promise.resolve({ locale: 'en', slug: 'example' }) });
  expect(withoutPhotos.openGraph?.images).toEqual([]);
});

it('returns a noindex Tour Not Found title when the backend detail 404s or fails', async () => {
  (getTourDetail as jest.Mock).mockRejectedValue(new NotFoundError('Tour not found.'));
  const missing = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'absent-tour' }) });
  expect(missing).toMatchObject({ title: 'Tour Not Found | Bookly', robots: { index: false } });

  (getTourDetail as jest.Mock).mockRejectedValue(new Error('upstream unavailable'));
  const failed = await generateMetadata({ params: Promise.resolve({ locale: 'en', slug: 'example' }) });
  expect(failed).toMatchObject({ title: 'Tour Not Found | Bookly', robots: { index: false } });
});

import { render, screen } from '@testing-library/react';
import Page, { generateMetadata } from '../page';
import { getTourDetail } from '@/lib/api/tours';
import { ApiError, GoneError, NotFoundError } from '@/lib/api/client';
import { notFound } from 'next/navigation';
import enMessages from '../../../../../../../messages/en.json';
import esMessages from '../../../../../../../messages/es.json';
import itMessages from '../../../../../../../messages/it.json';

jest.mock('@/lib/api/tours', () => ({ getTourDetail: jest.fn() }));
jest.mock('@/components/tour/TourDetail', () => ({ __esModule: true, default: () => null }));
jest.mock('next/navigation', () => ({ notFound: jest.fn(() => { throw new Error('NEXT_NOT_FOUND'); }) }));
jest.mock('next-intl/server', () => ({
  getLocale: jest.fn(async () => 'en'),
  getTranslations: jest.fn(async ({ locale, namespace }: { locale: string; namespace: string }) => {
    const catalogs = { en: enMessages, es: esMessages, it: itMessages } as Record<string, unknown>;
    const scope = namespace.split('.').reduce<unknown>(
      (node, part) => (node as Record<string, unknown> | undefined)?.[part],
      catalogs[locale],
    ) as Record<string, string>;
    return (key: string) => scope?.[key] ?? key;
  }),
}));

const mockedNotFound = notFound as unknown as jest.Mock;

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

// Corrected T035: the previous assertion encoded the bug (English-only
// not-found for every failure). A 404 localizes the not-found title; any
// other failure is recoverable and must not claim "Not Found".
it('returns localized noindex metadata distinguishing 404 from recoverable failures', async () => {
  (getTourDetail as jest.Mock).mockRejectedValue(new NotFoundError('Tour not found.'));
  const missing = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'absent-tour' }) });
  expect(missing).toMatchObject({ title: 'Tour no encontrado | Bookly', robots: { index: false } });

  (getTourDetail as jest.Mock).mockRejectedValue(new Error('upstream unavailable'));
  const failed = await generateMetadata({ params: Promise.resolve({ locale: 'en', slug: 'example' }) });
  expect(failed).toMatchObject({ title: 'This tour could not be loaded | Bookly', robots: { index: false } });
});

describe('US6 localized route states (Spec 020)', () => {
  beforeEach(() => {
    mockedNotFound.mockClear();
  });

  it.each([
    ['en', 'Tour not found | Bookly'],
    ['es', 'Tour no encontrado | Bookly'],
    ['it', 'Tour non trovato | Bookly'],
  ] as const)('localizes the 404 metadata title in %s with noindex', async (locale, title) => {
    (getTourDetail as jest.Mock).mockRejectedValue(new NotFoundError('Tour not found.'));

    const metadata = await generateMetadata({ params: Promise.resolve({ locale, slug: 'absent-tour' }) });

    expect(metadata).toMatchObject({ title, robots: { index: false } });
    expect(metadata.openGraph).toBeUndefined();
  });

  it.each([
    ['en', 'This tour is no longer available | Bookly'],
    ['es', 'Este tour ya no está disponible | Bookly'],
    ['it', 'Questo tour non è più disponibile | Bookly'],
  ] as const)('marks a known archived tour as withdrawn with noindex in %s', async (locale, title) => {
    (getTourDetail as jest.Mock).mockRejectedValue(new GoneError('Tour archived.'));

    const metadata = await generateMetadata({ params: Promise.resolve({ locale, slug: 'archived-tour' }) });

    expect(metadata).toMatchObject({ title, robots: { index: false } });
    expect(metadata.openGraph).toBeUndefined();
    expect(JSON.stringify(metadata)).not.toContain('Tour archived.');
  });

  it('uses a localized recoverable error title without blaming the tour or leaking upstream text', async () => {
    (getTourDetail as jest.Mock).mockRejectedValue(new ApiError('private upstream payload SECRET-XYZ-PRIVATE', 500));

    const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'example' }) });

    expect(metadata).toMatchObject({ title: 'No se pudo cargar este tour | Bookly', robots: { index: false } });
    expect(metadata.title).not.toContain('Tour Not Found');
    expect(JSON.stringify(metadata)).not.toContain('SECRET-XYZ-PRIVATE');
    expect(metadata.openGraph).toBeUndefined();
  });

  it('never surfaces hostile upstream messages in failure metadata', async () => {
    (getTourDetail as jest.Mock).mockRejectedValue(new Error('hostile </script> SECRET-XYZ-PRIVATE'));

    const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'it', slug: 'example' }) });

    expect(JSON.stringify(metadata)).not.toContain('SECRET-XYZ-PRIVATE');
    expect(JSON.stringify(metadata)).not.toContain('</script>');
    expect(metadata.robots).toMatchObject({ index: false });
  });

  it('routes a real 404 through Next notFound semantics', async () => {
    (getTourDetail as jest.Mock).mockRejectedValue(new NotFoundError('Tour not found.'));

    await expect(Page({ params: Promise.resolve({ locale: 'es', slug: 'absent-tour' }) })).rejects.toThrow('NEXT_NOT_FOUND');
    expect(mockedNotFound).toHaveBeenCalledTimes(1);
  });

  it('renders a localized withdrawn state with no booking for a known archived tour', async () => {
    (getTourDetail as jest.Mock).mockRejectedValue(new GoneError('Tour archived.'));

    const element = await Page({ params: Promise.resolve({ locale: 'es', slug: 'archived-tour' }) });
    expect(mockedNotFound).not.toHaveBeenCalled();
    render(element);

    expect(screen.getByText('Este tour ya no está disponible')).toBeInTheDocument();
    expect(screen.getByText('Este tour fue archivado y no se puede reservar.')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Book Now|Reservar|Prenota/i })).not.toBeInTheDocument();
  });

  it('rethrows recoverable failures to the localized error boundary instead of notFound', async () => {
    (getTourDetail as jest.Mock).mockRejectedValue(new ApiError('private upstream payload SECRET-XYZ-PRIVATE', 503));

    await expect(Page({ params: Promise.resolve({ locale: 'en', slug: 'example' }) })).rejects.toThrow('private upstream payload');
    expect(mockedNotFound).not.toHaveBeenCalled();
  });
});

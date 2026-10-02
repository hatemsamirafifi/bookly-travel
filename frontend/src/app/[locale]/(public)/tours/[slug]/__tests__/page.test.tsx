import { generateMetadata } from '../page';
import { getTourDetail } from '@/lib/api/tours';

jest.mock('@/lib/api/tours', () => ({ getTourDetail: jest.fn() }));
jest.mock('@/components/tour/TourDetail', () => ({ __esModule: true, default: () => null }));

it('uses the actual fallback content locale and complete gallery in tour metadata', async () => {
  (getTourDetail as jest.Mock).mockResolvedValue({ data: {
    content_locale: 'en',
    images: [
      { url: 'https://cdn.example.com/cover.jpg' },
      { url: 'https://cdn.example.com/side.jpg' },
    ],
    seo: {
      meta_title: 'English source | Bookly',
      meta_description: 'English source description',
      canonical_url: 'https://bookly.com/es/tours/example',
      hreflang: { en: 'https://bookly.com/en/tours/example', es: 'https://bookly.com/es/tours/example' },
    },
  } });

  const metadata = await generateMetadata({ params: Promise.resolve({ locale: 'es', slug: 'example' }) });
  expect(metadata.openGraph).toMatchObject({ locale: 'en', images: [
    { url: 'https://cdn.example.com/cover.jpg' },
    { url: 'https://cdn.example.com/side.jpg' },
  ] });
  expect(metadata.description).toBe('English source description');
});

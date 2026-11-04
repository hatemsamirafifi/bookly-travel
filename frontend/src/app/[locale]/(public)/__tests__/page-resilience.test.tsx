import { render, screen } from '@testing-library/react';
import HomePage from '../page';
import { getHomepageData } from '@/lib/api/homepage';
import { getBlogPosts } from '@/lib/api/blog';

jest.mock('next-intl/server', () => ({
  getTranslations: async () => (key: string) => key,
}));

jest.mock('@/lib/api/homepage', () => ({ getHomepageData: jest.fn() }));
jest.mock('@/lib/api/blog', () => ({ getBlogPosts: jest.fn() }));
jest.mock('@/components/seo/StructuredData', () => ({ OrganizationSchema: () => null }));
jest.mock('@/components/home/HeroSection', () => ({
  __esModule: true,
  default: () => <div data-testid="hero" />,
}));
jest.mock('@/components/home/CategoryChips', () => ({
  __esModule: true,
  default: () => <div data-testid="category-chips" />,
}));
jest.mock('@/components/home/BooklyValuePanel', () => ({
  __esModule: true,
  default: () => <div data-testid="value-panel" />,
}));
jest.mock('@/components/home/FeaturedTours', () => ({
  __esModule: true,
  default: ({ tours }: { tours: unknown[] }) => <div data-testid="featured" data-count={tours.length} />,
}));
jest.mock('@/components/home/CategoryGrid', () => ({
  __esModule: true,
  default: ({ categories }: { categories: unknown[] }) => <div data-testid="categories" data-count={categories.length} />,
}));
jest.mock('@/components/home/DestinationShowcase', () => ({
  __esModule: true,
  default: ({ destinations }: { destinations: unknown[] }) => <div data-testid="destinations" data-count={destinations.length} />,
}));
jest.mock('@/components/home/ThemedTourRails', () => ({
  __esModule: true,
  default: ({ rails }: { rails: unknown[] }) => <div data-testid="themed-rails" data-count={rails.length} />,
}));
jest.mock('@/components/home/BlogInspiration', () => ({
  __esModule: true,
  default: ({ articles }: { articles: unknown[] }) => (articles.length ? <div data-testid="blog-inspiration" /> : null),
}));
jest.mock('@/components/home/PartnerAcquisitionPanel', () => ({
  __esModule: true,
  default: () => <div data-testid="partner-panel" />,
}));
jest.mock('@/components/home/CatalogRecoveryAlert', () => ({
  __esModule: true,
  default: () => <div data-testid="recovery-alert" role="alert" />,
}));

const mockData = {
  data: {
    featured_tours: [{ id: 1 }],
    popular_categories: [{ slug: 'walking' }],
    featured_destinations: [{ slug: 'rome' }],
    curated_rails: [{ id: 'family', slug: 'family', title: 'Family Friendly', tours: [{ id: 2 }] }],
  },
  meta: { seo: { meta_title: 'Bookly', meta_description: 'Travel' } },
};

describe('HomePage Resilience (US4)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (getHomepageData as jest.Mock).mockResolvedValue(mockData);
    (getBlogPosts as jest.Mock).mockResolvedValue({ data: [] });
  });

  it('silently omits blog inspiration when blog feed fails', async () => {
    (getBlogPosts as jest.Mock).mockRejectedValue(new Error('Network failure'));

    render(await HomePage({ params: Promise.resolve({ locale: 'en' }) }));

    expect(screen.getByTestId('hero')).toBeInTheDocument();
    expect(screen.getByTestId('featured')).toBeInTheDocument();
    expect(screen.queryByTestId('blog-inspiration')).not.toBeInTheDocument();
  });

  it('omits themed rails when curated_rails is empty', async () => {
    (getHomepageData as jest.Mock).mockResolvedValue({
      ...mockData,
      data: {
        ...mockData.data,
        curated_rails: [],
      },
    });

    render(await HomePage({ params: Promise.resolve({ locale: 'en' }) }));

    expect(screen.getByTestId('hero')).toBeInTheDocument();
    expect(screen.queryByTestId('themed-rails')).not.toBeInTheDocument();
  });

  it('renders recovery alert and keeps hero search accessible when core homepage data fails', async () => {
    (getHomepageData as jest.Mock).mockRejectedValue(new Error('Core catalog 500'));

    render(await HomePage({ params: Promise.resolve({ locale: 'en' }) }));

    expect(screen.getByTestId('hero')).toBeInTheDocument();
    expect(screen.getByTestId('recovery-alert')).toBeInTheDocument();
    expect(screen.queryByTestId('featured')).not.toBeInTheDocument();
  });
});


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
jest.mock('@/components/blog/BlogCard', () => ({
  __esModule: true,
  default: ({ post }: { post: { title: string } }) => <article>{post.title}</article>,
}));

const homepage = {
  data: {
    featured_tours: [{ id: 1 }],
    popular_categories: [{ slug: 'walking' }],
    featured_destinations: [{ slug: 'rome' }],
  },
  meta: { seo: { meta_title: 'Bookly', meta_description: 'Travel' } },
};

describe('HomePage discovery', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (getHomepageData as jest.Mock).mockResolvedValue(homepage);
    (getBlogPosts as jest.Mock).mockResolvedValue({ data: [] });
  });

  it('renders only eligible data-backed discovery sections and a secondary partner invitation', async () => {
    render(await HomePage({ params: Promise.resolve({ locale: 'en' }) }));

    expect(screen.getByTestId('featured')).toHaveAttribute('data-count', '1');
    expect(screen.getByTestId('categories')).toHaveAttribute('data-count', '1');
    expect(screen.getByTestId('destinations')).toHaveAttribute('data-count', '1');
    expect(screen.queryByText('editorialTitle')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'partnerAction' })).toHaveAttribute('href', '/en/partner-register');
  });

  it('shows editorial cards only when the public blog API supplies posts', async () => {
    (getBlogPosts as jest.Mock).mockResolvedValue({ data: [{ id: 7, title: 'A real travel guide' }] });
    render(await HomePage({ params: Promise.resolve({ locale: 'es' }) }));

    expect(getBlogPosts).toHaveBeenCalledWith('es', { per_page: 3 });
    expect(screen.getByText('A real travel guide')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'viewAllArticles' })).toHaveAttribute('href', '/es/blog');
  });

  it('keeps discovery and partner invitation available if only the blog feed fails', async () => {
    (getBlogPosts as jest.Mock).mockRejectedValue(new Error('Blog unavailable'));
    render(await HomePage({ params: Promise.resolve({ locale: 'it' }) }));

    expect(screen.getByTestId('hero')).toBeInTheDocument();
    expect(screen.queryByText('editorialTitle')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'partnerAction' })).toHaveAttribute('href', '/it/partner-register');
  });
});

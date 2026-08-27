import { render, screen } from '@testing-library/react';
import BlogCard from '../BlogCard';
import type { BlogPostCard } from '@/lib/api/types';

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt }: { src: string; alt: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} data-testid="blog-card-image" />
  ),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: { minutes?: number }) => {
    if (key === 'readingTime') return `${values?.minutes ?? 0} min read`;
    if (key === 'translationWarning') return 'Content only available in English';
    return key;
  },
}));

const mockPost: BlogPostCard = {
  id: 1,
  slug: 'top-10-places-rome',
  title: 'Top 10 Places in Rome',
  excerpt: 'Discover the ultimate guide to visiting Rome with insider secrets.',
  cover_image_url: 'https://cdn.test/rome.jpg',
  cover_image_blur: null,
  reading_time: 6,
  published_at: '2026-05-15T10:00:00Z',
  is_featured: false,
  translation_warning: null,
  category: {
    slug: 'city-guides',
    name: 'City Guides',
  },
  author: {
    display_name: 'Marco Rossi',
    avatar_url: 'https://cdn.test/marco.jpg',
  },
};

describe('BlogCard', () => {
  it('renders post title, excerpt, reading time, author, and category', () => {
    render(<BlogCard post={mockPost} locale="en" />);

    expect(screen.getByText('Top 10 Places in Rome')).toBeInTheDocument();
    expect(
      screen.getByText('Discover the ultimate guide to visiting Rome with insider secrets.')
    ).toBeInTheDocument();
    expect(screen.getByText('6 min read')).toBeInTheDocument();
    expect(screen.getByText('Marco Rossi')).toBeInTheDocument();
    expect(screen.getByText('City Guides')).toBeInTheDocument();
  });

  it('links to the localized article detail page', () => {
    render(<BlogCard post={mockPost} locale="it" />);

    const articleLinks = screen.getAllByRole('link');
    const hasCorrectHref = articleLinks.some(
      (link) => link.getAttribute('href') === '/it/blog/top-10-places-rome'
    );
    expect(hasCorrectHref).toBe(true);
  });
});

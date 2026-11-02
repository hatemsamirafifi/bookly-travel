import { render, screen } from '@testing-library/react';
import BlogDetail from '../BlogDetail';
import { sanitizeHtml } from '@/lib/sanitizeHtml';
import type { BlogPostDetail } from '@/lib/api/types';

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt }: { src: string; alt: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} data-testid="blog-detail-image" />
  ),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => key,
}));

const MaliciousBody = [
  '<p>Safe paragraph with <strong>bold text</strong> inside.</p>',
  '<h2>Safe heading</h2>',
  '<a href="https://bookly.test/tours/rome">still a safe https link</a>',
  '<script>alert(1)</script>',
  '<img src="/cover.jpg" onerror="alert(1)">',
  '<a href="javascript:alert(1)">evil link</a>',
  '<svg onload="alert(1)"><circle r="1"/></svg>',
  '<style>body { display: none }</style>',
].join('');

const mockPost: BlogPostDetail = {
  id: 1,
  slug: 'security-test-post',
  title: 'Security Test Post',
  excerpt: 'Defense-in-depth sanitizer verification for the blog renderer.',
  cover_image_url: null,
  cover_image_blur: null,
  reading_time: 4,
  published_at: '2026-05-15T10:00:00Z',
  is_featured: false,
  translation_warning: null,
  category: null,
  author: {
    display_name: 'Security Tester',
    avatar_url: null,
  },
  body: MaliciousBody,
  updated_at: null,
  seo: {
    meta_title: 'Security Test Post',
    meta_description: 'Security test',
    canonical_url: 'https://bookly.test/blog/security-test-post',
    hreflang: {},
  },
  related_tours: [],
  related_posts: [],
};

describe('sanitizeHtml', () => {
  it('strips <script> elements including their payload', () => {
    expect(sanitizeHtml('<p>ok</p><script>alert(1)</script>')).not.toContain('alert(1)');
    expect(sanitizeHtml('<p>ok</p><script>alert(1)</script>')).toContain('<p>ok</p>');
  });

  it('strips dangerous <iframe>, <object>, <embed>, <svg> and <style> elements', () => {
    const result = sanitizeHtml(
      '<iframe src="https://evil.test"></iframe><object data="evil"></object><embed src="evil"><svg><circle /></svg><style>p{}</style>'
    );
    expect(result).not.toContain('<iframe');
    expect(result).not.toContain('<object');
    expect(result).not.toContain('<embed');
    expect(result).not.toContain('<svg');
    expect(result).not.toContain('<style');
  });

  it('strips inline on* event handler attributes', () => {
    expect(sanitizeHtml('<img src="/x.jpg" onerror="alert(1)">')).not.toContain('onerror');
    expect(sanitizeHtml('<p onclick="alert(1)">hi</p>')).not.toContain('onclick');
    expect(sanitizeHtml('<h2 onmouseover="alert(1)">hi</h2>')).not.toContain('onmouseover');
  });

  it('neutralizes javascript: links', () => {
    const result = sanitizeHtml('<a href="javascript:alert(1)">evil</a>');
    expect(result).not.toContain('javascript:');
    // Entity-obfuscated variants are resolved before the scheme check.
    expect(sanitizeHtml('<a href="&#106;avascript:alert(1)">evil</a>')).not.toContain('avascript');
  });

  it('neutralizes vbscript: and data:text/html URLs', () => {
    expect(sanitizeHtml('<a href="vbscript:msgbox">x</a>')).not.toContain('vbscript:');
    expect(sanitizeHtml('<a href="data:text/html;base64,PHN">x</a>')).not.toContain('data:text/html');
  });

  it('keeps safe elements and https links intact', () => {
    const result = sanitizeHtml(
      '<p>Keep <strong>bold</strong></p><h2>Heading</h2><a href="https://bookly.test/tours">tours</a>'
    );
    expect(result).toContain('<p>Keep <strong>bold</strong></p>');
    expect(result).toContain('<h2>Heading</h2>');
    expect(result).toContain('<a href="https://bookly.test/tours">tours</a>');
  });
});

describe('BlogDetail security', () => {
  it('renders the sanitized body without script payload, on* handlers, or javascript: links', () => {
    const { container } = render(<BlogDetail post={mockPost} locale="en" />);

    const article = container.querySelector('article')!;

    expect(article.innerHTML).not.toContain('<script');
    expect(article.innerHTML).not.toContain('alert(1)');
    expect(article.innerHTML).not.toContain('onerror');
    expect(article.innerHTML).not.toContain('onload');
    expect(article.innerHTML).not.toContain('javascript:');
    expect(article.innerHTML).not.toContain('<svg');
    expect(article.innerHTML).not.toContain('<style');
  });

  it('keeps the safe markup, headings, emphasis, and https links', () => {
    render(<BlogDetail post={mockPost} locale="en" />);

    expect(screen.getByRole('heading', { level: 2, name: 'Safe heading' })).toBeInTheDocument();
    expect(screen.getByText('Safe paragraph with', { exact: false })).toBeInTheDocument();
    expect(screen.getByText('bold text')).toBeInTheDocument();
    const safeLink = screen.getByRole('link', { name: /safe https link/ });
    expect(safeLink.getAttribute('href')).toBe('https://bookly.test/tours/rome');
  });
});

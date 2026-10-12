import { fireEvent, render, screen, within } from '@testing-library/react';
import ImageGallery from '../ImageGallery';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: { number?: number }) => {
    const labels: Record<string, string> = {
      open: 'Open image gallery', dialog: 'Image lightbox', close: 'Close lightbox',
      previous: 'Previous image', next: 'Next image', thumbnails: 'Thumbnail navigation',
    };
    return key === 'view' ? `View image ${values?.number}` : labels[key];
  },
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => {
    const { fill, priority, ...rest } = props;
    // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
    return <img {...rest} data-fill={fill ? 'true' : undefined} data-priority={priority ? 'true' : undefined} />;
  },
}));
jest.mock('@/lib/images', () => ({ getImagePlaceholderProps: () => ({}) }));

const images = [
  { url: 'https://images.example.com/cover.jpg', is_cover: true, alt: 'Main square' },
  { url: 'https://images.example.com/second.jpg', is_cover: false, alt: 'Old street' },
];

describe('ImageGallery keyboard behavior', () => {
  it('opens from a focusable trigger, navigates in order, and returns focus after Escape', () => {
    render(<ImageGallery images={images} title="Rome walk" />);
    const trigger = screen.getByRole('button', { name: 'Open image gallery' });
    trigger.focus();
    fireEvent.click(trigger);

    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(dialog).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Close lightbox' })).toHaveFocus();
    fireEvent.keyDown(window, { key: 'Tab', shiftKey: true });
    expect(dialog.querySelectorAll('button')[2]).toHaveFocus();
    fireEvent.keyDown(window, { key: 'Tab' });
    expect(screen.getByRole('button', { name: 'Close lightbox' })).toHaveFocus();
    fireEvent.keyDown(window, { key: 'ArrowRight' });
    expect(dialog.querySelector('img')).toHaveAttribute('alt', 'Old street');
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('does not show enlargement controls for missing media', () => {
    render(<ImageGallery images={[]} title="Rome walk" />);
    expect(screen.queryByRole('button', { name: 'Open image gallery' })).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});

describe('ImageGallery truthful media (Spec 019 US4)', () => {
  it('uses the authored English alt in its actual locale while action labels stay page-localized', () => {
    render(
      <ImageGallery
        images={[
          { url: 'https://images.example.com/harbor.jpg', is_cover: true, alt: 'Sunrise over the harbor', alt_locale: 'en' },
          { url: 'https://images.example.com/street.jpg', is_cover: false, alt: 'Old street', alt_locale: 'en' },
        ]}
        title="Passeggiata al porto"
      />
    );
    fireEvent.click(screen.getByRole('button', { name: 'Open image gallery' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(dialog.querySelector('img')).toHaveAttribute('alt', 'Sunrise over the harbor');
    // Actual image/caption text declares its real language (EN authored
    // alt on an ES/IT page), instead of inheriting the page language.
    expect(dialog.querySelector('[lang="en"]')).not.toBeNull();
    // Controls remain in the page language; the dialog is not wrapped as English.
    expect(screen.getByRole('button', { name: 'Close lightbox' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Close lightbox' }).closest('[lang="en"]')).toBeNull();
    expect(within(dialog).getByRole('button', { name: 'Previous image' })).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Next image' })).toBeInTheDocument();
  });

  it('falls back to the provided selected title when alt is empty', () => {
    render(
      <ImageGallery
        images={[{ url: 'https://images.example.com/harbor.jpg', is_cover: true, alt: '' }]}
        title="Passeggiata al porto"
      />
    );
    expect(screen.getByRole('img').getAttribute('alt')).toBe('Passeggiata al porto');
  });

  it('shows the actual cover first, keeping remaining media order', () => {
    render(
      <ImageGallery
        images={[
          { url: 'https://images.example.com/a.jpg', is_cover: false, alt: 'First alley' },
          { url: 'https://images.example.com/cover.jpg', is_cover: true, alt: 'Harbor panorama' },
          { url: 'https://images.example.com/c.jpg', is_cover: false, alt: 'Last square' },
        ]}
        title="Rome walk"
      />
    );
    expect(screen.getByRole('button', { name: 'Open image gallery' }).querySelector('img')).toHaveAttribute('src', 'https://images.example.com/cover.jpg');
    const thumbs = screen.getAllByRole('button', { name: /View image \d+/ });
    expect(thumbs[0].querySelector('img')?.getAttribute('src')).toContain('cover.jpg');
  });

  it('renders a neutral empty state with no fabricated photo for no images', () => {
    render(<ImageGallery images={[]} title="Rome walk" />);
    expect(screen.queryAllByRole('img')).toHaveLength(0);
    expect(document.querySelector('svg')).not.toBeNull();
    expect(screen.queryByRole('button', { name: 'Open image gallery' })).not.toBeInTheDocument();
  });

  it('renders alt text as text, never as raw HTML', () => {
    const raw = '<img src="x" onerror="alert(1)"> Harbor';
    render(
      <ImageGallery
        images={[{ url: 'https://images.example.com/h.jpg', is_cover: true, alt: raw }]}
        title="Rome walk"
      />
    );
    fireEvent.click(screen.getByRole('button', { name: 'Open image gallery' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(dialog.querySelectorAll('img')).toHaveLength(1);
    expect(dialog.querySelector('img')).toHaveAttribute('alt', raw);
    expect(dialog.textContent).toContain(raw);
  });

  it('wraps keyboard navigation from the first image to the last', () => {
    render(<ImageGallery images={images} title="Rome walk" />);
    fireEvent.click(screen.getByRole('button', { name: 'Open image gallery' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    fireEvent.keyDown(window, { key: 'ArrowLeft' });
    expect(dialog.querySelector('img')).toHaveAttribute('alt', 'Old street');
  });
});

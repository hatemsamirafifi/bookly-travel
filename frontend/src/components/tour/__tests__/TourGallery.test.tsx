import { fireEvent, render, screen, within } from '@testing-library/react';
import { act } from 'react';
import { renderToString } from 'react-dom/server';
import { hydrateRoot } from 'react-dom/client';
import TourGallery from '../TourGallery';
import type { TourImage } from '@/lib/api/types';

jest.mock('next-intl', () => ({
  useTranslations:
    () =>
    (key: string, values?: { number?: number; count?: number; current?: number; total?: number }) => {
      if (key === 'view') return `View image ${values?.number}`;
      if (key === 'seeAll') return `See all ${values?.count} photos`;
      if (key === 'counter') return `${values?.current} of ${values?.total}`;
      const labels: Record<string, string> = {
        open: 'Open image gallery',
        dialog: 'Image lightbox',
        close: 'Close lightbox',
        previous: 'Previous image',
        next: 'Next image',
        empty: 'No photos are available for this tour.',
        imageFailed: 'This photo is unavailable.',
      };
      return labels[key] ?? key;
    },
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => {
    // Test mock consumes next Image-specific props (incl. preload) so they do
    // not leak onto <img> as unknown DOM attributes; exposed as data-* mirrors.
    const { fill, placeholder, blurDataURL, preload, ...rest } = props;
    return (
      // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
      <img
        {...rest}
        data-fill={fill ? 'true' : undefined}
        data-placeholder={typeof placeholder === 'string' ? placeholder : undefined}
        data-blur={typeof blurDataURL === 'string' ? 'true' : undefined}
        data-preload={preload ? 'true' : undefined}
      />
    );
  },
}));

const DESKTOP_QUERY = '(min-width: 1024px)';
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

let reducedMotion = false;
let desktopViewport = false;

function installMatchMedia() {
  window.matchMedia = jest.fn((query: string) => ({
    matches: query === DESKTOP_QUERY ? desktopViewport : query === REDUCED_MOTION_QUERY ? reducedMotion : false,
    media: query,
    onchange: null,
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
    addListener: jest.fn(),
    removeListener: jest.fn(),
    dispatchEvent: jest.fn().mockReturnValue(false),
  }));
}

const photo = (n: number, over: Partial<TourImage> = {}): TourImage => ({
  url: `https://images.example.com/photo-${n}.jpg`,
  is_cover: n === 1,
  alt: `Photo ${n}`,
  ...over,
});

const track = () => screen.getByTestId('tour-gallery-track');

beforeEach(() => {
  reducedMotion = false;
  desktopViewport = false;
  installMatchMedia();
});

describe('TourGallery empty gallery', () => {
  it('shows an honest localized empty state with reserved space and no viewer', () => {
    render(<TourGallery images={[]} title="Rome walk" contentLocale="en" />);
    expect(screen.getByTestId('tour-gallery-empty')).toBeInTheDocument();
    expect(screen.getByText('No photos are available for this tour.')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});

describe('TourGallery single photo DOM', () => {
  it('mounts each original photo exactly once in cover-first caller order', () => {
    render(
      <TourGallery
        images={[
          { url: 'https://images.example.com/b.jpg', is_cover: false, alt: 'Alley' },
          { url: 'https://images.example.com/c.jpg', is_cover: true, alt: 'Harbor panorama' },
          { url: 'https://images.example.com/a.jpg', is_cover: false, alt: 'Square' },
        ]}
        title="Rome walk"
        contentLocale="en"
      />,
    );
    const sources = within(track()).getAllByRole('img').map((img) => img.getAttribute('src'));
    expect(sources).toEqual([
      'https://images.example.com/c.jpg',
      'https://images.example.com/b.jpg',
      'https://images.example.com/a.jpg',
    ]);
  });

  it('shows a cover plus up to four supporting tiles with a real-count see-all beyond five', () => {
    render(<TourGallery images={[1, 2, 3, 4, 5, 6, 7].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const tiles = within(track()).getAllByRole('button', { name: /View image \d+/ });
    expect(tiles).toHaveLength(7);
    expect(tiles[0].className).not.toMatch(/lg:hidden/);
    expect(tiles[5].className).toMatch(/lg:hidden/);
    expect(tiles[6].className).toMatch(/lg:hidden/);
    fireEvent.click(screen.getByRole('button', { name: 'See all 7 photos' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByText('1 of 7')).toBeInTheDocument();
  });

  it('reaches every supplied photo in order, including slots beyond five, with wraparound', () => {
    render(<TourGallery images={[1, 2, 3, 4, 5, 6, 7].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(screen.getByRole('button', { name: 'See all 7 photos' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    const seen: Array<string | null> = [within(dialog).getByRole('img').getAttribute('alt')];
    for (let step = 0; step < 6; step += 1) {
      fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
      seen.push(within(dialog).getByRole('img').getAttribute('alt'));
    }
    expect(seen).toEqual(['Photo 1', 'Photo 2', 'Photo 3', 'Photo 4', 'Photo 5', 'Photo 6', 'Photo 7']);
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 1');
    expect(within(dialog).getByText('1 of 7')).toBeInTheDocument();
  });

  it('opens each tile at its own original index', () => {
    render(<TourGallery images={[1, 2, 3, 4].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 3' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 3');
    expect(within(dialog).getByText('3 of 4')).toBeInTheDocument();
  });

  it('discloses the truthful count for sparse galleries with see-all access to every photo', () => {
    const { rerender } = render(
      <TourGallery images={[1, 2, 3, 4].map((n) => photo(n))} title="Rome walk" contentLocale="en" />,
    );
    expect(within(track()).getAllByRole('button', { name: /View image \d+/ })).toHaveLength(4);
    expect(within(track()).getAllByRole('img')).toHaveLength(4);
    fireEvent.click(screen.getByRole('button', { name: 'See all 4 photos' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 1');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 4');
    expect(within(dialog).getByText('4 of 4')).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'Escape' });
    rerender(<TourGallery images={[photo(1)]} title="Rome walk" contentLocale="en" />);
    expect(within(track()).getAllByRole('button', { name: /View image \d+/ })).toHaveLength(1);
    expect(screen.queryByRole('button', { name: /See all/ })).not.toBeInTheDocument();
  });

  it('keeps a failed slot stable with an honest placeholder while other photos stay reachable', () => {
    render(<TourGallery images={[1, 2, 3, 4].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const failedImg = within(track()).getAllByRole('img')[1];
    fireEvent.error(failedImg);
    expect(within(track()).getByText('This photo is unavailable.')).toBeInTheDocument();
    expect(within(track()).getAllByRole('button', { name: /View image \d+/ })).toHaveLength(4);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByText('1 of 4')).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    expect(within(dialog).getByText('2 of 4')).toBeInTheDocument();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Next image' }));
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 3');
  });

  it('recovers a failed slot when the media identity changes', () => {
    const { rerender } = render(
      <TourGallery images={[1, 2].map((n) => photo(n))} title="Rome walk" contentLocale="en" />,
    );
    fireEvent.error(within(track()).getAllByRole('img')[0]);
    expect(within(track()).getByText('This photo is unavailable.')).toBeInTheDocument();
    rerender(
      <TourGallery
        images={[
          { url: 'https://images.example.com/fresh-cover.jpg', is_cover: true, alt: 'Fresh cover' },
          { url: 'https://images.example.com/fresh-second.jpg', is_cover: false, alt: 'Fresh second' },
        ]}
        title="Rome walk"
        contentLocale="en"
      />,
    );
    expect(within(track()).queryByText('This photo is unavailable.')).not.toBeInTheDocument();
    expect(within(track()).getAllByRole('img')).toHaveLength(2);
  });

  it('marks image language from alt_locale while controls stay page-localized', () => {
    render(
      <TourGallery
        images={[
          { url: 'https://images.example.com/harbor.jpg', is_cover: true, alt: 'Sunrise over the harbor', alt_locale: 'en' },
          { url: 'https://images.example.com/street.jpg', is_cover: false, alt: 'Old street', alt_locale: 'en' },
        ]}
        title="Passeggiata al porto"
        contentLocale="es"
      />,
    );
    expect(within(track()).getAllByRole('img')[0]).toHaveAttribute('lang', 'en');
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('lang', 'en');
    const close = within(dialog).getByRole('button', { name: 'Close lightbox' });
    expect(close.closest('[lang="en"]')).toBeNull();
    expect(within(dialog).getByRole('button', { name: 'Previous image' }).closest('[lang="en"]')).toBeNull();
  });
});

describe('TourGallery desktop span balance', () => {
  const SIZES_FULL =
    '(min-width: 80rem) 76rem, (min-width: 64rem) calc(100vw - 4rem), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';
  const SIZES_HALF =
    '(min-width: 80rem) 37.75rem, (min-width: 64rem) calc((100vw - 4.5rem) / 2), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';
  const SIZES_QUARTER =
    '(min-width: 80rem) 18.625rem, (min-width: 64rem) calc((100vw - 5.5rem) / 4), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';

  it('gives both photos half width with half-frame sizes for two photos', () => {
    render(<TourGallery images={[1, 2].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const tiles = within(track()).getAllByRole('button', { name: /View image \d+/ });
    expect(tiles).toHaveLength(2);
    expect(tiles[0]).toHaveClass('lg:col-span-2');
    expect(tiles[1]).toHaveClass('lg:col-span-2');
    expect(tiles[0]).not.toHaveClass('lg:row-span-2');
    const rendered = within(track()).getAllByRole('img');
    expect(rendered[0]).toHaveAttribute('sizes', SIZES_HALF);
    expect(rendered[1]).toHaveAttribute('sizes', SIZES_HALF);
  });

  it('spans the last supporting tile across the remaining right row for four photos', () => {
    render(<TourGallery images={[1, 2, 3, 4].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const tiles = within(track()).getAllByRole('button', { name: /View image \d+/ });
    expect(tiles).toHaveLength(4);
    expect(tiles[0]).toHaveClass('lg:col-span-2', 'lg:row-span-2');
    expect(tiles[1]).toHaveClass('lg:col-span-1');
    expect(tiles[2]).toHaveClass('lg:col-span-1');
    expect(tiles[3]).toHaveClass('lg:col-span-2');
    const rendered = within(track()).getAllByRole('img');
    expect(rendered[0]).toHaveAttribute('sizes', SIZES_HALF);
    expect(rendered[1]).toHaveAttribute('sizes', SIZES_QUARTER);
    expect(rendered[2]).toHaveAttribute('sizes', SIZES_QUARTER);
    expect(rendered[3]).toHaveAttribute('sizes', SIZES_HALF);
  });

  it('keeps full, half and quarter sizes for one, three and five photos', () => {
    const { rerender } = render(<TourGallery images={[photo(1)]} title="Rome walk" contentLocale="en" />);
    expect(within(track()).getAllByRole('img')[0]).toHaveAttribute('sizes', SIZES_FULL);
    rerender(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const three = within(track()).getAllByRole('img');
    expect(three[0]).toHaveAttribute('sizes', SIZES_HALF);
    expect(three[1]).toHaveAttribute('sizes', SIZES_QUARTER);
    expect(three[2]).toHaveAttribute('sizes', SIZES_QUARTER);
    rerender(<TourGallery images={[1, 2, 3, 4, 5].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const five = within(track()).getAllByRole('img');
    expect(five.map((img) => img.getAttribute('sizes'))).toEqual([
      SIZES_HALF,
      SIZES_QUARTER,
      SIZES_QUARTER,
      SIZES_QUARTER,
      SIZES_QUARTER,
    ]);
  });
});

describe('TourGallery mobile rail', () => {
  it('shows every photo in a scroll rail with counter and controls, without thumbnails', () => {
    render(<TourGallery images={[1, 2, 3, 4, 5, 6, 7].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    expect(within(track()).getAllByRole('img')).toHaveLength(7);
    expect(screen.getByText('1 of 7')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Previous image' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Next image' })).toBeInTheDocument();
    expect(screen.queryByRole('list', { name: 'Thumbnail navigation' })).not.toBeInTheDocument();
  });

  it('updates the counter on scroll and moves the rail from visible controls', () => {
    render(<TourGallery images={[1, 2, 3, 4, 5, 6, 7].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const scroller = track();
    Object.defineProperty(scroller, 'clientWidth', { configurable: true, value: 800 });
    scroller.scrollLeft = 1600;
    fireEvent.scroll(scroller);
    expect(screen.getByText('3 of 7')).toBeInTheDocument();
    scroller.scrollTo = jest.fn();
    fireEvent.click(screen.getByRole('button', { name: 'Next image' }));
    expect(scroller.scrollTo).toHaveBeenCalledWith({ left: 2400, behavior: 'smooth' });
    expect(screen.getByText('4 of 7')).toBeInTheDocument();
  });

  it('uses instant programmatic scrolls when reduced motion is preferred', () => {
    reducedMotion = true;
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const scroller = track();
    Object.defineProperty(scroller, 'clientWidth', { configurable: true, value: 800 });
    scroller.scrollTo = jest.fn();
    fireEvent.click(screen.getByRole('button', { name: 'Next image' }));
    expect(scroller.scrollTo).toHaveBeenCalledWith({ left: 800, behavior: 'auto' });
  });

  it('reaches every rail photo through its own trigger', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 2' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 2');
    expect(screen.getByText('2 of 3')).toBeInTheDocument();
  });
});

describe('TourGallery viewer', () => {
  it('uses a fullscreen viewport panel instead of a centered card', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const panel = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(panel.className).toMatch(/max-w-none/);
    expect(panel.className).not.toMatch(/max-w-5xl/);
    expect(panel.className).not.toMatch(/max-w-lg/);
    const overlay = panel.parentElement;
    expect(overlay).not.toBeNull();
    expect(overlay).toHaveClass('fixed', 'inset-0');
    expect(overlay).not.toHaveClass('p-4');
  });

  it('fits the viewer image in the available dialog body with visible controls', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    const frame = within(dialog).getByRole('img').closest('div');
    expect(frame).not.toBeNull();
    expect(frame).toHaveClass('flex-1', 'min-h-[240px]');
    expect(within(dialog).getByRole('img')).toHaveAttribute('sizes', 'calc(100vw - 2rem)');
    expect(within(dialog).getByRole('button', { name: 'Previous image' })).toBeInTheDocument();
    expect(within(dialog).getByText('1 of 3')).toBeInTheDocument();
  });

  it('announces position even for a single photo, without navigation controls', () => {
    render(<TourGallery images={[photo(1)]} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    expect(within(dialog).getByText('1 of 1')).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Previous image' })).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Next image' })).not.toBeInTheDocument();
  });

  it('returns focus to the actual trigger on close', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    const trigger = within(track()).getByRole('button', { name: 'View image 2' });
    trigger.focus();
    fireEvent.click(trigger);
    expect(screen.getByRole('dialog', { name: 'Image lightbox' })).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('keeps focus stable on arrow navigation without refocusing close', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    const next = within(dialog).getByRole('button', { name: 'Next image' });
    const close = within(dialog).getByRole('button', { name: 'Close lightbox' });
    next.focus();
    fireEvent.keyDown(next, { key: 'ArrowRight' });
    expect(next).toHaveFocus();
    expect(close).not.toHaveFocus();
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 2');
    expect(within(dialog).getByText('2 of 3')).toBeInTheDocument();
  });

  it('advances with arrows from the dialog close button without moving focus', () => {
    render(<TourGallery images={[1, 2, 3].map((n) => photo(n))} title="Rome walk" contentLocale="en" />);
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    const close = within(dialog).getByRole('button', { name: 'Close lightbox' });
    close.focus();
    fireEvent.keyDown(close, { key: 'ArrowRight' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 2');
    expect(within(dialog).getByText('2 of 3')).toBeInTheDocument();
    expect(close).toHaveFocus();
    fireEvent.keyDown(close, { key: 'ArrowLeft' });
    expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 1');
    expect(close).toHaveFocus();
  });

  it('applies the blur placeholder only from actually supplied metadata', () => {
    render(
      <TourGallery
        images={[
          { url: 'https://images.example.com/a.jpg', is_cover: true, alt: 'Has blur', blur_data_url: 'data:image/jpeg;base64,AAA' } as TourImage,
          photo(2),
        ]}
        title="Rome walk"
        contentLocale="en"
      />,
    );
    const rendered = within(track()).getAllByRole('img');
    expect(rendered[0]).toHaveAttribute('data-blur', 'true');
    expect(rendered[0]).toHaveAttribute('data-placeholder', 'blur');
    expect(rendered[1]).not.toHaveAttribute('data-blur');
  });

  it('keeps a minimum viewer frame height with a very long caption so the photo stays visible', () => {
    const longCaption = 'Long supplied caption '.repeat(500);
    render(
      <TourGallery
        images={[{ url: 'https://images.example.com/long.jpg', is_cover: true, alt: longCaption }]}
        title="Rome walk"
        contentLocale="en"
      />,
    );
    fireEvent.click(within(track()).getByRole('button', { name: 'View image 1' }));
    const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
    const frame = within(dialog).getByRole('img').closest('div');
    expect(frame).not.toBeNull();
    // Measured real bug (isolated browser 390x900 long-caption geometry gave image
    // height 0): the frame must guarantee a minimum visible height while the
    // dialog body scrolls; the full caption stays reachable, not truncated.
    expect(frame).toHaveClass('min-h-[240px]');
    expect(within(dialog).getByText(longCaption.trim())).toBeInTheDocument();
  });
});

describe('TourGallery hydration', () => {
  it('hydrates a server snapshot on desktop without hydration errors and keeps controls usable', async () => {
    const photos = [1, 2, 3, 4, 5, 6, 7].map((n) => photo(n));
    const matchMediaMock = window.matchMedia;
    Object.defineProperty(window, 'matchMedia', { configurable: true, writable: true, value: undefined });
    let html = '';
    try {
      html = renderToString(<TourGallery images={photos} title="Rome walk" contentLocale="en" />);
    } finally {
      Object.defineProperty(window, 'matchMedia', { configurable: true, writable: true, value: matchMediaMock });
    }
    expect(html).toContain('See all 7 photos');
    desktopViewport = true;
    const calls: unknown[][] = [];
    const originalError = console.error;
    console.error = (...args: unknown[]) => {
      calls.push(args);
    };
    const container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container);
    const holder: { current: { unmount(): void } | null } = { current: null };
    try {
      await act(async () => {
        holder.current = hydrateRoot(container, <TourGallery images={photos} title="Rome walk" contentLocale="en" />);
      });
      const hydrationErrors = calls.filter((args) =>
        args.some(
          (arg) => typeof arg === 'string' && /hydrat|did not match|server-rendered|client-rendered/i.test(arg),
        ),
      );
      expect(hydrationErrors).toEqual([]);
      fireEvent.click(within(container).getByRole('button', { name: 'See all 7 photos' }));
      const dialog = screen.getByRole('dialog', { name: 'Image lightbox' });
      expect(within(dialog).getByRole('img')).toHaveAttribute('alt', 'Photo 1');
      expect(within(dialog).getByText('1 of 7')).toBeInTheDocument();
    } finally {
      console.error = originalError;
      holder.current?.unmount();
      container.remove();
    }
  });
});

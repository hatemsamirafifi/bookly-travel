import { render, screen } from '@testing-library/react';
import ImageGallery from '../ImageGallery';
import type { TourImage } from '@/lib/api/types';

const mockSeenGalleryProps: { images: TourImage[]; title: string; contentLocale?: string }[] = [];
jest.mock('../TourGallery', () => ({
  __esModule: true,
  default: (props: { images: TourImage[]; title: string; contentLocale?: string }) => {
    mockSeenGalleryProps.push(props);
    return <div data-testid="tour-gallery" />;
  },
}));

beforeEach(() => {
  mockSeenGalleryProps.length = 0;
});

const photo = (n: number, over: Partial<TourImage> = {}): TourImage => ({
  url: `https://images.example.com/photo-${n}.jpg`,
  is_cover: n === 1,
  alt: `Photo ${n}`,
  ...over,
});

describe('ImageGallery legacy alias (Spec 020 US3)', () => {
  it('delegates images, title and content locale to the canonical gallery with no second engine', () => {
    const images = [photo(1), photo(2)];
    render(<ImageGallery images={images} title="Rome walk" contentLocale="es" />);

    expect(screen.getByTestId('tour-gallery')).toBeInTheDocument();
    expect(mockSeenGalleryProps).toHaveLength(1);
    expect(mockSeenGalleryProps[0].images).toEqual(images);
    expect(mockSeenGalleryProps[0].title).toBe('Rome walk');
    expect(mockSeenGalleryProps[0].contentLocale).toBe('es');
    // No duplicate modal/state engine survives: no legacy trigger or dialog.
    expect(screen.queryByRole('button', { name: 'Open image gallery' })).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('keeps the title-only call shape used by non-migrated surfaces', () => {
    render(<ImageGallery images={[photo(1)]} title="Preview draft" />);

    expect(mockSeenGalleryProps).toHaveLength(1);
    expect(mockSeenGalleryProps[0].title).toBe('Preview draft');
    expect(mockSeenGalleryProps[0].contentLocale).toBeUndefined();
    expect(screen.getByTestId('tour-gallery')).toBeInTheDocument();
  });
});

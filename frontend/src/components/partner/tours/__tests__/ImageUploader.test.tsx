import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ImageUploader } from '../ImageUploader';
import type { TourMedia } from '@/types/partner';

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...rest }: { src: string; alt: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} {...rest} />
  ),
}));

const getSignedUploadUrl = jest.fn();
jest.mock('@/lib/api/partner', () => ({
  getSignedUploadUrl: (fileType: string, fileSize: number) => getSignedUploadUrl(fileType, fileSize),
}));

const MB = 1024 * 1024;

function makeFile(name: string, type: string, sizeBytes: number): File {
  return new File([new Uint8Array(sizeBytes)], name, { type });
}

function dropFiles(files: File[]) {
  // The drop zone is the only element with a dashed border.
  const dropZone = document.querySelector('[class*="border-dashed"]') as HTMLElement;
  fireEvent.drop(dropZone, { dataTransfer: { files } });
}

beforeEach(() => {
    jest.clearAllMocks();
    getSignedUploadUrl.mockResolvedValue({
      upload_url: 'https://upload.example.com/put',
      public_url: 'https://cdn.example.com/img.png',
    });
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 200,
    } as unknown as Response) as typeof fetch;
});

describe('ImageUploader', () => {
  it('rejects an oversized file with a visible error and does not upload', async () => {
    render(<ImageUploader media={[]} onChange={jest.fn()} />);
    dropFiles([makeFile('big.png', 'image/png', 6 * MB)]);
    await waitFor(() => {
      expect(screen.getByText(/big.png:/)).toBeInTheDocument();
      expect(screen.getByText('File is too large.')).toBeInTheDocument();
    });
    expect(getSignedUploadUrl).not.toHaveBeenCalled();
  });

  it('rejects an unsupported file type with a visible error', async () => {
    render(<ImageUploader media={[]} onChange={jest.fn()} />);
    dropFiles([makeFile('anim.gif', 'image/gif', 100)]);
    await waitFor(() => {
      expect(screen.getByText('Unsupported file type.')).toBeInTheDocument();
    });
    expect(getSignedUploadUrl).not.toHaveBeenCalled();
  });

  it('accepts a WebP image and uploads it', async () => {
    const onChange = jest.fn();
    render(<ImageUploader media={[]} onChange={onChange} />);
    dropFiles([makeFile('cover.webp', 'image/webp', 100)]);
    await waitFor(() => {
      expect(getSignedUploadUrl).toHaveBeenCalledWith('image/webp', 100);
    });
    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(
        expect.arrayContaining([expect.objectContaining({ is_cover: true })])
      );
    });
  });

  it('enforces the maximum image count', async () => {
    const fullMedia: TourMedia[] = Array.from({ length: 20 }, (_, i) => ({
      id: String(i),
      url: `https://cdn.example.com/${i}.png`,
      is_cover: i === 0,
      sort_order: i,
    }));
    render(<ImageUploader media={fullMedia} onChange={jest.fn()} />);
    dropFiles([makeFile('extra.png', 'image/png', 100)]);
    await waitFor(() => {
      expect(screen.getByText('Maximum number of images reached.')).toBeInTheDocument();
    });
    expect(getSignedUploadUrl).not.toHaveBeenCalled();
  });

  it('shows an error and does not call onChange when the upload fails', async () => {
    global.fetch = jest.fn().mockResolvedValue({ ok: false, status: 500 } as unknown as Response) as typeof fetch;
    const onChange = jest.fn();
    render(<ImageUploader media={[]} onChange={onChange} />);
    dropFiles([makeFile('broken.png', 'image/png', 100)]);

    await waitFor(() => {
      expect(screen.getByText('Upload failed: 500')).toBeInTheDocument();
    });
    expect(onChange).not.toHaveBeenCalled();
  });

  it('removes a gallery image when its remove button is clicked', () => {
    const media: TourMedia[] = [
      { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 },
      { id: 'g1', url: 'https://cdn.example.com/g1.png', is_cover: false, sort_order: 1 },
      { id: 'g2', url: 'https://cdn.example.com/g2.png', is_cover: false, sort_order: 2 },
    ];
    const onChange = jest.fn();
    render(<ImageUploader media={media} onChange={onChange} />);

    const removeButtons = screen.getAllByText('form.remove');
    expect(removeButtons).toHaveLength(2);
    fireEvent.click(removeButtons[0]);

    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({ id: 'cover', is_cover: true, sort_order: 0 }),
      expect.objectContaining({ id: 'g2', sort_order: 1 }),
    ]);
  });

  it('reorders gallery images when the move-down button is clicked', () => {
    const media: TourMedia[] = [
      { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 },
      { id: 'g1', url: 'https://cdn.example.com/g1.png', is_cover: false, sort_order: 1 },
      { id: 'g2', url: 'https://cdn.example.com/g2.png', is_cover: false, sort_order: 2 },
    ];
    const onChange = jest.fn();
    render(<ImageUploader media={media} onChange={onChange} />);

    const moveDownButtons = screen.getAllByLabelText('form.moveDown');
    expect(moveDownButtons).toHaveLength(2);
    fireEvent.click(moveDownButtons[0]);

    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({ id: 'cover', sort_order: 0 }),
      expect.objectContaining({ id: 'g2', sort_order: 1 }),
      expect.objectContaining({ id: 'g1', sort_order: 2 }),
    ]);
  });
});

describe('ImageUploader twenty-image gallery with specific alt (Spec 019 US4)', () => {
  const NON_BMP = String.fromCodePoint(0x1f30d);

  function galleryMedia(count: number): TourMedia[] {
    return Array.from({ length: count }, (_, i) => ({
      id: `m${i}`,
      url: `https://cdn.example.com/${i}.png`,
      is_cover: i === 0,
      sort_order: i,
    }));
  }

  it('accepts a twentieth image without the old ten-image cap', async () => {
    const onChange = jest.fn();
    render(<ImageUploader media={galleryMedia(19)} onChange={onChange} />);
    dropFiles([makeFile('twenty.png', 'image/png', 100)]);

    await waitFor(() => {
      expect(getSignedUploadUrl).toHaveBeenCalledWith('image/png', 100);
    });
    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith(
        expect.arrayContaining([expect.objectContaining({ url: 'https://cdn.example.com/img.png' })])
      );
    });
    expect(screen.queryByText('Maximum number of images reached.')).not.toBeInTheDocument();
  });

  it('restores twenty existing images with cover and gallery intact', () => {
    render(<ImageUploader media={galleryMedia(20)} onChange={jest.fn()} />);
    expect(screen.getByText('form.coverImage')).toBeInTheDocument();
    expect(screen.getAllByText('form.remove')).toHaveLength(19);
    expect(screen.queryByText('Maximum number of images reached.')).not.toBeInTheDocument();
  });

  it('rejects a twenty-first image while keeping the twenty', async () => {
    const onChange = jest.fn();
    render(<ImageUploader media={galleryMedia(20)} onChange={onChange} />);
    dropFiles([makeFile('extra.png', 'image/png', 100)]);

    await waitFor(() => {
      expect(screen.getByText('Maximum number of images reached.')).toBeInTheDocument();
    });
    expect(getSignedUploadUrl).not.toHaveBeenCalled();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('edits specific alt through an associated labeled field, retaining identity and order', () => {
    const media: TourMedia[] = [
      { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0, alt_text: 'Harbor sunrise' },
      { id: 'g1', url: 'https://cdn.example.com/g1.png', is_cover: false, sort_order: 1, alt_text: null },
    ];
    const onChange = jest.fn();
    render(<ImageUploader media={media} onChange={onChange} />);

    const labeled = screen.getAllByLabelText(/alt/i);
    expect(labeled.length).toBeGreaterThanOrEqual(2);
    labeled.forEach((field) => expect(field).not.toHaveAttribute('maxlength'));

    const coverAlt = screen.getByDisplayValue('Harbor sunrise');
    fireEvent.change(coverAlt, { target: { value: 'Harbor at dawn' } });
    fireEvent.blur(coverAlt);

    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({ id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0, alt_text: 'Harbor at dawn' }),
      expect.objectContaining({ id: 'g1', url: 'https://cdn.example.com/g1.png', is_cover: false, sort_order: 1, alt_text: null }),
    ]);
  });

  it('preserves omitted and written alt across gallery removal', () => {
    const media: TourMedia[] = [
      { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 },
      { id: 'g1', url: 'https://cdn.example.com/g1.png', is_cover: false, sort_order: 1, alt_text: null },
      { id: 'g2', url: 'https://cdn.example.com/g2.png', is_cover: false, sort_order: 2, alt_text: 'Stone archway' },
    ];
    const onChange = jest.fn();
    render(<ImageUploader media={media} onChange={onChange} />);

    fireEvent.click(screen.getAllByText('form.remove')[0]);

    expect(onChange).toHaveBeenCalledTimes(1);
    const next = onChange.mock.calls[0][0];
    expect(next).toHaveLength(2);
    expect(next[0]).toMatchObject({ id: 'cover', is_cover: true, sort_order: 0 });
    expect(next[0].alt_text).toBeUndefined();
    expect(next[1]).toMatchObject({ id: 'g2', sort_order: 1, alt_text: 'Stone archway' });
  });

  it('accepts a 500-code-point alt and flags a 501-code-point alt without a native maxlength', () => {
    const media: TourMedia[] = [
      { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0 },
    ];
    const onChange = jest.fn();
    const { container } = render(<ImageUploader media={media} onChange={onChange} />);

    const field = screen.getByLabelText(/alt/i);
    expect(field).not.toHaveAttribute('maxlength');

    const ok = NON_BMP.repeat(500);
    fireEvent.change(field, { target: { value: ok } });
    fireEvent.blur(field);
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({ id: 'cover', alt_text: ok }),
    ]);

    onChange.mockClear();
    fireEvent.change(field, { target: { value: NON_BMP.repeat(501) } });
    fireEvent.blur(field);
    expect(container.querySelector('[role="alert"]')).not.toBeNull();
    expect(onChange).not.toHaveBeenCalledWith([
      expect.objectContaining({ id: 'cover', alt_text: NON_BMP.repeat(501) }),
    ]);
  });
});

describe('ImageUploader truthful English previews and accessible editing', () => {
  const media: TourMedia[] = [
    { id: 'cover', url: 'https://cdn.example.com/cover.png', is_cover: true, sort_order: 0, alt_text: 'Sunrise over the harbor' },
    { id: 'gallery', url: 'https://cdn.example.com/gallery.png', is_cover: false, sort_order: 1, alt_text: null },
  ];

  it('uses specific English alt or the actual English source title for every preview', () => {
    render(<ImageUploader media={media} onChange={jest.fn()} title="Canonical English tour" />);
    expect(screen.getByRole('img', { name: 'Sunrise over the harbor' })).toHaveAttribute('lang', 'en');
    expect(screen.getByRole('img', { name: 'Canonical English tour' })).toHaveAttribute('lang', 'en');
    screen.getAllByLabelText(/alt/i).forEach((field) => expect(field).toHaveAttribute('lang', 'en'));
  });

  it('does not invent a description before a title or specific alt exists', () => {
    const { container } = render(<ImageUploader media={[{ ...media[0], alt_text: null }]} onChange={jest.fn()} />);
    expect(container.querySelector('img')).toHaveAttribute('alt', '');
  });

  it('exposes a named upload button with a native disabled state', () => {
    const { rerender } = render(<ImageUploader media={[]} onChange={jest.fn()} />);
    expect(screen.getByRole('button', { name: /^form\.dragDrop/ })).toBeEnabled();
    rerender(<ImageUploader media={[]} onChange={jest.fn()} disabled />);
    expect(screen.getByRole('button', { name: /^form\.dragDrop/ })).toBeDisabled();
  });

  it('reports invalid pending alt before save and recovers on a valid 500-codepoint edit', () => {
    const onValidityChange = jest.fn();
    const onChange = jest.fn();
    render(<ImageUploader media={[media[0]]} onChange={onChange} onValidityChange={onValidityChange} />);
    const field = screen.getByLabelText(/alt/i);
    fireEvent.change(field, { target: { value: String.fromCodePoint(0x1f30d).repeat(501) } });
    expect(onValidityChange).toHaveBeenLastCalledWith(false);
    expect(field).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('alert')).toBeVisible();
    expect(onChange).not.toHaveBeenCalled();
    fireEvent.change(field, { target: { value: String.fromCodePoint(0x1f30d).repeat(500) } });
    fireEvent.blur(field);
    expect(onValidityChange).toHaveBeenLastCalledWith(true);
    expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ alt_text: String.fromCodePoint(0x1f30d).repeat(500) })]);
  });
});

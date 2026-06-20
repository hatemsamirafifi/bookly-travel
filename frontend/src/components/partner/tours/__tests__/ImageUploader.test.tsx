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

describe('ImageUploader', () => {
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
    const fullMedia: TourMedia[] = Array.from({ length: 10 }, (_, i) => ({
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
      expect.objectContaining({ id: 'g2', sort_order: 2 }),
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

    expect(onChange).toHaveBeenCalledWith(
      expect.arrayContaining([
        expect.objectContaining({ id: 'g1', sort_order: 2 }),
        expect.objectContaining({ id: 'g2', sort_order: 1 }),
      ])
    );
  });
});
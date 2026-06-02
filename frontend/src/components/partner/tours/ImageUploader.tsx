'use client';

import { useCallback, useState, useRef } from 'react';
import { getSignedUploadUrl } from '@/lib/api/partner';
import type { TourMedia, ImageUploadState } from '@/types/tour';

const MAX_FILE_SIZE_MB = 5;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
const MAX_IMAGES = 10;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png'];

interface ImageUploaderProps {
  /** Currently saved media items */
  media: TourMedia[];
  /** Called when media list changes (cover set, images added/removed) */
  onChange: (media: TourMedia[]) => void;
  /** Whether to disable interactions */
  disabled?: boolean;
}

export function ImageUploader({ media, onChange, disabled = false }: ImageUploaderProps) {
  const [uploads, setUploads] = useState<ImageUploadState[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const coverImage = media.find((m) => m.is_cover);
  const galleryImages = media.filter((m) => !m.is_cover);
  const totalImages = media.length + uploads.filter((u) => u.status === 'done').length;

  const uploadFile = useCallback(
    async (file: File, isCover: boolean): Promise<TourMedia | null> => {
      // Generate a temp upload state entry
      const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
      const preview = URL.createObjectURL(file);

      const uploadState: ImageUploadState = {
        file,
        preview,
        progress: 0,
        status: 'uploading',
      };

      setUploads((prev) => [...prev, { ...uploadState, id: tempId } as ImageUploadState & { id: string }]);

      try {
        // 1. Get signed URL
        const urlRes = await getSignedUploadUrl(file.name, file.type, {
          maxSizeMB: MAX_FILE_SIZE_MB,
        });
        const { upload_url, public_url } = urlRes.data;

        // 2. Upload to storage
        setUploads((prev) =>
          prev.map((u) =>
            (u as ImageUploadState & { id: string }).id === tempId
              ? { ...u, progress: 50 }
              : u
          )
        );

        const uploadRes = await fetch(upload_url, {
          method: 'PUT',
          body: file,
          headers: { 'Content-Type': file.type },
        });

        if (!uploadRes.ok) {
          throw new Error(`Upload failed: ${uploadRes.status}`);
        }

        setUploads((prev) =>
          prev.map((u) =>
            (u as ImageUploadState & { id: string }).id === tempId
              ? { ...u, progress: 100, status: 'done' as const, publicUrl: public_url }
              : u
          )
        );

        // 3. Create TourMedia entry
        const newMedia: TourMedia = {
          id: `new-${Date.now()}`,
          url: public_url,
          thumbnail_url: public_url,
          is_cover: isCover,
          sort_order: isCover ? 0 : media.length,
        };

        return newMedia;
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Upload failed';
        setUploads((prev) =>
          prev.map((u) =>
            (u as ImageUploadState & { id: string }).id === tempId
              ? { ...u, status: 'error' as const, error: message }
              : u
          )
        );
        return null;
      }
    },
    [media]
  );

  const handleFiles = useCallback(
    async (files: FileList | File[]) => {
      if (disabled) return;

      const fileArray = Array.from(files);

      for (const file of fileArray) {
        // Validate type
        if (!ACCEPTED_TYPES.includes(file.type)) {
          continue;
        }
        // Validate size
        if (file.size > MAX_FILE_SIZE_BYTES) {
          continue;
        }
        // Validate total count
        if (totalImages >= MAX_IMAGES) {
          break;
        }

        const isCover = !coverImage && media.length === 0;
        const result = await uploadFile(file, isCover);
        if (result) {
          onChange([...media, result]);
        }
      }

      // Clean up completed/error uploads after a delay
      setTimeout(() => {
        setUploads((prev) => prev.filter((u) => u.status === 'uploading'));
      }, 2000);
    },
    [disabled, totalImages, coverImage, media, onChange, uploadFile]
  );

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setDragOver(false);
      if (disabled) return;
      handleFiles(e.dataTransfer.files);
    },
    [handleFiles, disabled]
  );

  const handleDragOver = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      if (!disabled) setDragOver(true);
    },
    [disabled]
  );

  const handleDragLeave = useCallback(() => {
    setDragOver(false);
  }, []);

  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      if (e.target.files) {
        handleFiles(e.target.files);
      }
      // Reset input so the same file can be re-selected
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    },
    [handleFiles]
  );

  const setCover = useCallback(
    (mediaId: string | number) => {
      const updated = media.map((m) => ({
        ...m,
        is_cover: String(m.id) === String(mediaId),
        sort_order: String(m.id) === String(mediaId) ? 0 : m.sort_order,
      }));
      onChange(updated);
    },
    [media, onChange]
  );

  const removeImage = useCallback(
    (mediaId: string | number) => {
      const filtered = media.filter((m) => String(m.id) !== String(mediaId));
      // If we removed the cover, make the first image the cover
      if (!filtered.some((m) => m.is_cover) && filtered.length > 0) {
        filtered[0] = { ...filtered[0], is_cover: true, sort_order: 0 };
      }
      onChange(filtered);
    },
    [media, onChange]
  );

  return (
    <div className="space-y-4">
      {/* Drop zone */}
      <div
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onClick={() => !disabled && fileInputRef.current?.click()}
        className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors ${
          disabled
            ? 'border-gray-200 bg-gray-50 cursor-not-allowed'
            : dragOver
            ? 'border-[#FFB800] bg-[#FFB800]/5'
            : 'border-gray-300 hover:border-[#FFB800]'
        }`}
      >
        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png"
          multiple
          onChange={handleInputChange}
          disabled={disabled}
          className="hidden"
        />
        <div className="space-y-2">
          <div className="text-gray-400">
            <svg className="w-10 h-10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M12 16v-8m0 0l-3 3m3-3l3 3M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"
              />
            </svg>
          </div>
          <p className="text-sm text-gray-600 font-medium">
            Drag and drop images here, or click to browse
          </p>
          <p className="text-xs text-gray-400">
            JPG/PNG, max {MAX_FILE_SIZE_MB}MB per image. Up to {MAX_IMAGES} images total.
          </p>
        </div>
      </div>

      {/* Upload progress indicators */}
      {uploads.filter((u) => u.status === 'uploading').length > 0 && (
        <div className="space-y-2">
          {uploads
            .filter((u) => u.status === 'uploading')
            .map((upload, idx) => (
              <div key={idx} className="flex items-center gap-3 text-sm">
                <div className="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                  <div
                    className="bg-[#FFB800] h-full rounded-full transition-all"
                    style={{ width: `${upload.progress}%` }}
                  />
                </div>
                <span className="text-gray-500 shrink-0">{upload.progress}%</span>
              </div>
            ))}
        </div>
      )}

      {/* Error indicators */}
      {uploads.filter((u) => u.status === 'error').length > 0 && (
        <div className="space-y-1">
          {uploads
            .filter((u) => u.status === 'error')
            .map((upload, idx) => (
              <p key={idx} className="text-sm text-red-600">{upload.error ?? 'Upload failed'}</p>
            ))}
        </div>
      )}

      {/* Cover image */}
      {coverImage && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-[#0A2540]">Cover Image</p>
          <div className="relative inline-block">
            <img
              src={coverImage.thumbnail_url ?? coverImage.url}
              alt="Cover"
              className="w-48 h-32 object-cover rounded-lg border border-gray-200"
            />
            <button
              type="button"
              onClick={() => removeImage(coverImage.id)}
              disabled={disabled}
              className="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-colors"
              aria-label="Remove cover image"
            >
              x
            </button>
            <span className="absolute top-2 left-2 px-2 py-0.5 bg-[#FFB800] text-[#0A2540] text-xs font-medium rounded">
              Cover
            </span>
          </div>
        </div>
      )}

      {/* Gallery thumbnails */}
      {galleryImages.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-[#0A2540]">Gallery Images</p>
          <div className="flex flex-wrap gap-3">
            {galleryImages.map((img) => (
              <div key={String(img.id)} className="relative group">
                <img
                  src={img.thumbnail_url ?? img.url}
                  alt={img.alt_text ?? 'Gallery image'}
                  className="w-24 h-20 object-cover rounded-lg border border-gray-200"
                />
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                  <button
                    type="button"
                    onClick={() => setCover(img.id)}
                    disabled={disabled}
                    className="px-2 py-1 bg-white text-[#0A2540] text-xs font-medium rounded hover:bg-gray-100"
                  >
                    Set Cover
                  </button>
                  <button
                    type="button"
                    onClick={() => removeImage(img.id)}
                    disabled={disabled}
                    className="px-2 py-1 bg-red-500 text-white text-xs font-medium rounded hover:bg-red-600"
                  >
                    Remove
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Image count */}
      <p className="text-xs text-gray-400">
        {media.length} / {MAX_IMAGES} images
      </p>
    </div>
  );
}
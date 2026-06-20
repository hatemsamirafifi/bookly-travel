'use client';

import { useCallback, useState, useRef } from 'react';
import Image from 'next/image';
import { useTranslations } from 'next-intl';
import { ChevronUp, ChevronDown } from 'lucide-react';
import { getSignedUploadUrl } from '@/lib/api/partner';
import type { TourMedia, ImageUploadState } from '@/types/tour';

const MAX_FILE_SIZE_MB = 5;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
const MAX_IMAGES = 10;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
/** Comma-separated value for the native <input accept> attribute. */
const ACCEPT_ATTR = ACCEPTED_TYPES.join(',');
const ACCEPTED_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.webp'];

interface ImageUploaderProps {
  /** Currently saved media items */
  media: TourMedia[];
  /** Called when media list changes (cover set, images added/removed) */
  onChange: (media: TourMedia[]) => void;
  /** Whether to disable interactions */
  disabled?: boolean;
}

export function ImageUploader({ media, onChange, disabled = false }: ImageUploaderProps) {
  const t = useTranslations('partner.tours');
  const [uploads, setUploads] = useState<ImageUploadState[]>([]);
  const [fileErrors, setFileErrors] = useState<{ name: string; reason: string }[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const coverImage = media.find((m) => m.is_cover);
  const galleryImages = media.filter((m) => !m.is_cover);
  const totalImages = media.length + uploads.filter((u) => u.status === 'done').length;

  const uploadFile = useCallback(
    async (file: File, isCover: boolean): Promise<TourMedia | null> => {
      const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

      const uploadState: ImageUploadState = {
        file,
        progress: 0,
        status: 'uploading',
      };

      setUploads((prev) => [...prev, { ...uploadState, id: tempId }]);

      try {
        // 1. Get signed URL
        const urlRes = await getSignedUploadUrl(file.type, file.size);
        const { upload_url, public_url } = urlRes;

        // 2. Upload to storage
        setUploads((prev) => prev.map((u) => (u.id === tempId ? { ...u, progress: 50 } : u)));

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
            u.id === tempId ? { ...u, progress: 100, status: 'done' as const, publicUrl: public_url } : u
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
        const message = err instanceof Error ? err.message : t('wizard.uploadFailed');
        setUploads((prev) =>
          prev.map((u) => (u.id === tempId ? { ...u, status: 'error' as const, error: message } : u))
        );
        return null;
      }
    },
    [media, t]
  );

  const handleFiles = useCallback(
    async (files: FileList | File[]) => {
      if (disabled) return;

      const fileArray = Array.from(files);
      const newErrors: { name: string; reason: string }[] = [];
      let acceptedCount = 0;

      for (const file of fileArray) {
        // Validate total count (against media already saved + accepted this batch)
        if (totalImages + acceptedCount >= MAX_IMAGES) {
          newErrors.push({ name: file.name, reason: t('errors.maxImagesReached', { max: MAX_IMAGES }) });
          continue;
        }
        // Validate type — check MIME, fall back to extension for browsers that give an empty type
        const lowerName = file.name.toLowerCase();
        const typeOk =
          ACCEPTED_TYPES.includes(file.type) ||
          (file.type === '' && ACCEPTED_EXTENSIONS.some((ext) => lowerName.endsWith(ext)));
        if (!typeOk) {
          newErrors.push({ name: file.name, reason: t('errors.unsupportedType') });
          continue;
        }
        // Validate size
        if (file.size > MAX_FILE_SIZE_BYTES) {
          newErrors.push({
            name: file.name,
            reason: t('errors.fileTooLarge', { mb: MAX_FILE_SIZE_MB }),
          });
          continue;
        }

        const isCover = !coverImage && media.length === 0 && acceptedCount === 0;
        const result = await uploadFile(file, isCover);
        if (result) {
          onChange([...media, result]);
          acceptedCount += 1;
        }
      }

      if (newErrors.length > 0) {
        setFileErrors((prev) => [...prev, ...newErrors]);
      }

      // Clean up completed/error uploads after a delay
      setTimeout(() => {
        setUploads((prev) => prev.filter((u) => u.status === 'uploading'));
      }, 2000);
    },
    [disabled, totalImages, coverImage, media, onChange, uploadFile, t]
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

  /** Reorders a gallery image (non-cover) by swapping sort_order with its neighbor. */
  const reorderImage = useCallback(
    (mediaId: string | number, direction: -1 | 1) => {
      const gallery = media.filter((m) => !m.is_cover);
      const index = gallery.findIndex((m) => String(m.id) === String(mediaId));
      const swapIndex = index + direction;
      if (index === -1 || swapIndex < 0 || swapIndex >= gallery.length) return;
      const target = gallery[swapIndex];
      onChange(
        media.map((m) => {
          if (String(m.id) === String(mediaId)) return { ...m, sort_order: target.sort_order };
          if (String(m.id) === String(target.id)) return { ...m, sort_order: gallery[index].sort_order };
          return m;
        })
      );
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
          accept={ACCEPT_ATTR}
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
            {t('form.dragDrop')}
          </p>
          <p className="text-xs text-gray-400">
            {t('form.maxFileSize', { mb: MAX_FILE_SIZE_MB, count: MAX_IMAGES })}
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

      {uploads.filter((u) => u.status === 'error').length > 0 && (
        <div className="space-y-1">
          {uploads
            .filter((u) => u.status === 'error')
            .map((upload, idx) => (
              <p key={idx} className="text-sm text-red-600">{upload.error ?? t('wizard.uploadFailed')}</p>
            ))}
        </div>
      )}

      {/* File validation errors (wrong type / too large / max reached) */}
      {fileErrors.length > 0 && (
        <div className="space-y-1" role="alert" aria-live="polite">
          {fileErrors.map((err, idx) => (
            <p key={idx} className="text-sm text-red-600">
              <span className="font-medium">{err.name}:</span> {err.reason}
            </p>
          ))}
          <button
            type="button"
            onClick={() => setFileErrors([])}
            className="text-xs text-gray-400 hover:text-gray-600 underline"
          >
            {t('form.dismissErrors')}
          </button>
        </div>
      )}

      {/* Cover image */}
      {coverImage && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-[#0A2540]">{t('form.coverImage')}</p>
          <div className="relative inline-block">
            <Image
              src={coverImage.thumbnail_url ?? coverImage.url}
              alt={t('form.coverImage')}
              width={192}
              height={128}
              className="object-cover rounded-lg border border-gray-200"
            />
            <button
              type="button"
              onClick={() => removeImage(coverImage.id)}
              disabled={disabled}
              className="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 transition-colors"
              aria-label={t('form.remove')}
            >
              x
            </button>
            <span className="absolute top-2 left-2 px-2 py-0.5 bg-[#FFB800] text-[#0A2540] text-xs font-medium rounded">
              {t('form.coverBadge')}
            </span>
          </div>
        </div>
      )}

      {/* Gallery thumbnails */}
      {galleryImages.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-[#0A2540]">{t('form.galleryImages')}</p>
          <div className="flex flex-wrap gap-3">
            {galleryImages.map((img, idx) => (
              <div key={String(img.id)} className="relative group">
                <Image
                  src={img.thumbnail_url ?? img.url}
                  alt={img.alt_text ?? t('form.galleryImageAlt')}
                  width={96}
                  height={80}
                  className="object-cover rounded-lg border border-gray-200"
                />
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                  <button
                    type="button"
                    onClick={() => reorderImage(img.id, -1)}
                    disabled={disabled || idx === 0}
                    aria-label={t('form.moveUp')}
                    className="px-1.5 py-1 bg-white text-[#0A2540] text-xs font-medium rounded hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronUp className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => reorderImage(img.id, 1)}
                    disabled={disabled || idx === galleryImages.length - 1}
                    aria-label={t('form.moveDown')}
                    className="px-1.5 py-1 bg-white text-[#0A2540] text-xs font-medium rounded hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronDown className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={() => setCover(img.id)}
                    disabled={disabled}
                    className="px-2 py-1 bg-white text-[#0A2540] text-xs font-medium rounded hover:bg-gray-100"
                  >
                    {t('form.setCover')}
                  </button>
                  <button
                    type="button"
                    onClick={() => removeImage(img.id)}
                    disabled={disabled}
                    className="px-2 py-1 bg-red-500 text-white text-xs font-medium rounded hover:bg-red-600"
                  >
                    {t('form.remove')}
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Image count */}
      <p className="text-xs text-gray-400">
        {t('form.imagesCount', { current: media.length, max: MAX_IMAGES })}
      </p>
    </div>
  );
}
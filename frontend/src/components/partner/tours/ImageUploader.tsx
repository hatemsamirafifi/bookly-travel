'use client';

import { useCallback, useEffect, useState, useRef } from 'react';
import Image from 'next/image';
import { useTranslations } from 'next-intl';
import { ChevronUp, ChevronDown } from 'lucide-react';
import { getSignedUploadUrl } from '@/lib/api/partner';
import type { TourMedia, ImageUploadState } from '@/types/tour';

const MAX_FILE_SIZE_MB = 5;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;
const MAX_IMAGES = 20;
const ALT_TEXT_MAX_CODEPOINTS = 500;
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
  /** English canonical author title for FR021 preview fallback (unknown/empty allowed). */
  title?: string;
  /** Reports whether all current alt drafts are within bounds. */
  onValidityChange?: (valid: boolean) => void;
}

export function ImageUploader({ media, onChange, disabled = false, title, onValidityChange }: ImageUploaderProps) {
  const t = useTranslations('partner.tours');
  const [uploads, setUploads] = useState<ImageUploadState[]>([]);
  const [fileErrors, setFileErrors] = useState<{ name: string; reason: string }[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  // Spec 019 US4: per-image English alt drafts. A draft exists only while the
  // author edits that field; untouched images keep their committed
  // omitted/null/string alt untouched. Over-long drafts stay local with an
  // inline alert and are never emitted.
  const [altDrafts, setAltDrafts] = useState<Record<string, string>>({});
  const [altErrors, setAltErrors] = useState<Record<string, boolean>>({});

  const coverImage = media.find((m) => m.is_cover);
  const galleryImages = media.filter((m) => !m.is_cover);
  const totalImages = media.length + uploads.filter((u) => u.status === 'uploading').length;

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
      const nextMedia = [...media];

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

        const isCover = !coverImage && nextMedia.length === 0;
        const result = await uploadFile(file, isCover);
        if (result) {
          nextMedia.push({ ...result, sort_order: nextMedia.length });
          onChange([...nextMedia]);
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
      const selected = media.find((m) => String(m.id) === String(mediaId));
      if (!selected) return;
      const updated = [selected, ...media.filter((m) => String(m.id) !== String(mediaId))]
        .map((m, index) => ({ ...m, is_cover: index === 0, sort_order: index }));
      onChange(updated);
    },
    [media, onChange]
  );

  /** Last validity reported to the parent; the callback fires only on change. */
  const lastValidityRef = useRef<boolean>(true);

  /**
   * Reports whether every draft attached to a currently present image is
   * within bounds. Always called outside state updaters; notifies the parent
   * only when the value actually flips.
   */
  const reportAltValidity = useCallback(
    (drafts: Record<string, string>) => {
      const present = new Set(media.map((m) => String(m.id)));
      const valid = !Object.entries(drafts).some(
        ([id, value]) => present.has(id) && Array.from(value).length > ALT_TEXT_MAX_CODEPOINTS
      );
      if (lastValidityRef.current !== valid) {
        lastValidityRef.current = valid;
        onValidityChange?.(valid);
      }
    },
    [media, onValidityChange]
  );

  const removeImage = useCallback(
    (mediaId: string | number) => {
      const key = String(mediaId);
      const filtered = media.filter((m) => String(m.id) !== String(mediaId));
      // If we removed the cover, make the first image the cover
      if (!filtered.some((m) => m.is_cover) && filtered.length > 0) {
        filtered[0] = { ...filtered[0], is_cover: true, sort_order: 0 };
      }
      // Discard drafts/errors of the removed image so no stale invalid
      // draft keeps blocking the remaining media.
      const remainingDrafts = Object.fromEntries(Object.entries(altDrafts).filter(([k]) => k !== key));
      setAltDrafts(remainingDrafts);
      setAltErrors((prev) => {
        if (!(key in prev)) return prev;
        return Object.fromEntries(Object.entries(prev).filter(([k]) => k !== key));
      });
      reportAltValidity(remainingDrafts);
      onChange(filtered.map((item, index) => ({ ...item, sort_order: index })));
    },
    [media, onChange, altDrafts, reportAltValidity]
  );

  /** Reorders the array itself: the backend persists caller array order. */
  const reorderImage = useCallback(
    (mediaId: string | number, direction: -1 | 1) => {
      const gallery = media.filter((m) => !m.is_cover);
      const index = gallery.findIndex((m) => String(m.id) === String(mediaId));
      const swapIndex = index + direction;
      if (index === -1 || swapIndex < 0 || swapIndex >= gallery.length) return;
      const next = [...media];
      const firstIndex = next.findIndex((m) => String(m.id) === String(gallery[index].id));
      const secondIndex = next.findIndex((m) => String(m.id) === String(gallery[swapIndex].id));
      [next[firstIndex], next[secondIndex]] = [next[secondIndex], next[firstIndex]];
      onChange(next.map((item, order) => ({ ...item, sort_order: order })));
    },
    [media, onChange]
  );

  /**
   * External media replacement (edit reload/restore) discards drafts of
   * images that no longer exist and re-reports; drafts of still-visible
   * images (bad text included) are never touched here.
   */
  const currentMediaIds = JSON.stringify(media.map((image) => String(image.id)).sort());
  const [draftMediaIds, setDraftMediaIds] = useState(currentMediaIds);
  if (draftMediaIds !== currentMediaIds) {
    setDraftMediaIds(currentMediaIds);
    const present = new Set(media.map((m) => String(m.id)));
    const prunedDrafts = Object.fromEntries(Object.entries(altDrafts).filter(([k]) => present.has(k)));
    const prunedErrors = Object.fromEntries(Object.entries(altErrors).filter(([k]) => present.has(k)));
    if (Object.keys(prunedDrafts).length !== Object.keys(altDrafts).length) {
      setAltDrafts(prunedDrafts);
    }
    if (Object.keys(prunedErrors).length !== Object.keys(altErrors).length) {
      setAltErrors(prunedErrors);
    }
  }
  // Notify the parent after rendering the current media. Local draft cleanup
  // above adjusts state only when the actual image identities change.
  useEffect(() => {
    reportAltValidity(altDrafts);
  }, [altDrafts, reportAltValidity]);

  /**
   * FR021 preview alt: nonblank specific English alt if present, else the
   * actual passed English title, else empty alt. Nothing generic is invented.
   */
  const previewAltFor = (image: TourMedia): { alt: string; english: boolean } => {
    if (typeof image.alt_text === 'string' && image.alt_text.trim() !== '') {
      return { alt: image.alt_text, english: true };
    }
    if (title !== undefined && title.trim() !== '') {
      return { alt: title, english: true };
    }
    return { alt: '', english: false };
  };

  const coverPreview = coverImage ? previewAltFor(coverImage) : null;

  /** Tracks the in-progress alt draft; invalid lengths report false at once. */
  const handleAltChange = useCallback(
    (mediaId: string | number, value: string) => {
      const key = String(mediaId);
      const invalid = Array.from(value).length > ALT_TEXT_MAX_CODEPOINTS;
      const nextDrafts = { ...altDrafts, [key]: value };
      setAltDrafts(nextDrafts);
      setAltErrors((prev) => {
        if (invalid) return { ...prev, [key]: true };
        if (!(key in prev)) return prev;
        return Object.fromEntries(Object.entries(prev).filter(([k]) => k !== key));
      });
      reportAltValidity(nextDrafts);
    },
    [altDrafts, reportAltValidity]
  );

  /**
   * Commits the alt draft on blur. Code points (not UTF-16 units) bound the
   * 500 limit so astral-plane text counts once. An empty edited field emits
   * explicit null (clear); an over-long draft stays local behind an inline
   * alert and is never emitted. Identity, URL, order, and cover are
   * preserved; untouched images never emit.
   */
  const handleAltBlur = useCallback(
    (mediaId: string | number) => {
      const key = String(mediaId);
      if (!(key in altDrafts)) return;
      const rawValue = altDrafts[key];
      if (Array.from(rawValue).length > ALT_TEXT_MAX_CODEPOINTS) {
        setAltErrors((prev) => ({ ...prev, [key]: true }));
        reportAltValidity(altDrafts);
        return;
      }
      const current = media.find((m) => String(m.id) === key);
      const remaining = Object.fromEntries(Object.entries(altDrafts).filter(([k]) => k !== key));
      setAltDrafts(remaining);
      if (!current) {
        reportAltValidity(remaining);
        return;
      }
      setAltErrors((prev) => {
        if (!(key in prev)) return prev;
        return Object.fromEntries(Object.entries(prev).filter(([k]) => k !== key));
      });
      const committedDisplay = current.alt_text ?? '';
      const nextAlt = rawValue === '' ? null : rawValue;
      const changed =
        rawValue !== committedDisplay || (rawValue === '' && current.alt_text === undefined);
      if (changed) {
        onChange(
          media.map((m) => (String(m.id) === key ? { ...m, alt_text: nextAlt } : m))
        );
      }
      reportAltValidity(remaining);
    },
    [altDrafts, media, onChange, reportAltValidity]
  );

  /**
   * Accessible English-specific optional alt field for one existing image.
   * Stable label/id derived from the image identity; no native maxlength so
   * a 500-code-point value (including astral-plane text) stays editable while
   * a 501-code-point draft is flagged via an associated alert.
   */
  const renderAltField = (image: TourMedia) => {
    const altKey = String(image.id);
    const altInputId = `tour-image-alt-${altKey}`;
    const altErrorId = `${altInputId}-error`;
    const altHasError = Boolean(altErrors[altKey]);
    return (
      <div className="space-y-1">
        <label htmlFor={altInputId} className="block text-xs font-medium text-[#0A2540]">
          {t('form.altText')}
        </label>
        <input
          id={altInputId}
          type="text"
          lang="en"
          value={altKey in altDrafts ? altDrafts[altKey] : (image.alt_text ?? '')}
          onChange={(e) => handleAltChange(image.id, e.target.value)}
          onBlur={() => handleAltBlur(image.id)}
          disabled={disabled}
          aria-invalid={altHasError}
          aria-describedby={altHasError ? altErrorId : undefined}
          className="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm text-gray-700 disabled:cursor-not-allowed disabled:bg-gray-50"
        />
        {altHasError && (
          <p id={altErrorId} role="alert" className="text-sm text-red-600">
            {t('errors.altTextMax')}
          </p>
        )}
      </div>
    );
  };

  return (
    <div className="space-y-4">
      {/* Drop zone: a real named button driving the hidden file input. */}
      <input
        ref={fileInputRef}
        type="file"
        accept={ACCEPT_ATTR}
        multiple
        onChange={handleInputChange}
        disabled={disabled}
        className="hidden"
      />
      <button
        type="button"
        disabled={disabled}
        onClick={() => {
          if (!disabled) fileInputRef.current?.click();
        }}
        onKeyDown={(e) => {
          if ((e.key === 'Enter' || e.key === ' ') && !disabled) {
            e.preventDefault();
            fileInputRef.current?.click();
          }
        }}
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        className={`w-full border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors ${
          disabled
            ? 'border-gray-200 bg-gray-50 cursor-not-allowed'
            : dragOver
            ? 'border-[#FFB800] bg-[#FFB800]/5'
            : 'border-gray-300 hover:border-[#FFB800]'
        }`}
      >
        <span className="space-y-2 block">
          <span className="text-gray-600 block">
            <svg aria-hidden="true" className="w-10 h-10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M12 16v-8m0 0l-3 3m3-3l3 3M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"
              />
            </svg>
          </span>
          <span className="text-sm text-gray-600 font-medium block">
            {t('form.dragDrop')}
          </span>
          <span className="text-xs text-gray-600 block">
            {t('form.maxFileSize', { mb: MAX_FILE_SIZE_MB, count: MAX_IMAGES })}
          </span>
        </span>
      </button>

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
            className="text-xs text-gray-600 hover:text-gray-600 underline"
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
              alt={coverPreview?.alt ?? ''}
              lang={coverPreview?.english ? 'en' : undefined}
              width={192}
              height={128}
              className="object-cover rounded-lg border border-gray-200"
            />
            <button
              type="button"
              onClick={() => removeImage(coverImage.id)}
              disabled={disabled}
              className="absolute -top-2 -right-2 w-6 h-6 bg-red-600 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-700 transition-colors"
              aria-label={t('form.remove')}
            >
              x
            </button>
            <span className="absolute top-2 left-2 px-2 py-0.5 bg-[#FFB800] text-[#0A2540] text-xs font-medium rounded">
              {t('form.coverBadge')}
            </span>
          </div>
          <div className="w-48 max-w-full">{renderAltField(coverImage)}</div>
        </div>
      )}

      {/* Gallery thumbnails */}
      {galleryImages.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-[#0A2540]">{t('form.galleryImages')}</p>
          <div className="flex flex-wrap gap-3">
            {galleryImages.map((img, idx) => {
              const preview = previewAltFor(img);
              return (
              <div key={String(img.id)} className="w-24 space-y-1">
                <div className="relative group">
                <Image
                  src={img.thumbnail_url ?? img.url}
                  alt={preview.alt}
                  lang={preview.english ? 'en' : undefined}
                  width={96}
                  height={80}
                  className="object-cover rounded-lg border border-gray-200"
                />
                <div className="absolute inset-x-0 bottom-0 rounded-b-lg bg-black/60 p-1 flex items-center justify-center gap-1">
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
                    className="px-2 py-1 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700"
                  >
                    {t('form.remove')}
                    </button>
                  </div>
                </div>
                {renderAltField(img)}
              </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Image count */}
      <p className="text-xs text-gray-600">
        {t('form.imagesCount', { current: media.length, max: MAX_IMAGES })}
      </p>
    </div>
  );
}

'use client';

import { useCallback, useMemo, useRef, useState, type KeyboardEvent, type UIEvent } from 'react';
import Image from 'next/image';
import { useTranslations } from 'next-intl';
import type { TourImage } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import { Dialog } from '@/components/ui/Dialog';

interface TourGalleryProps {
  images: TourImage[];
  title: string;
  contentLocale?: string;
}

type BlurCarrier = TourImage & { blur_data_url?: string; cover_image_blur?: string };

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
const VISIBLE_TILES = 5;

// Frame math from the actual page container (max-w-content 80rem with
// px-gutter 1rem / 1.5rem at 48rem+ / 2rem at 64rem+, grid gap 0.5rem):
// full frame 76rem at 80rem+, half (frame - gap) / 2, quarter (half - gap) / 2.
const SIZES_FULL =
  '(min-width: 80rem) 76rem, (min-width: 64rem) calc(100vw - 4rem), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';
const SIZES_HALF =
  '(min-width: 80rem) 37.75rem, (min-width: 64rem) calc((100vw - 4.5rem) / 2), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';
const SIZES_QUARTER =
  '(min-width: 80rem) 18.625rem, (min-width: 64rem) calc((100vw - 5.5rem) / 4), (min-width: 48rem) calc(100vw - 3rem), calc(100vw - 2rem)';
const SIZES_VIEWER = 'calc(100vw - 2rem)';

function tileSizes(total: number, position: number): string {
  if (total <= 1) return SIZES_FULL;
  if (total === 2 || position === 0 || (total === 4 && position === 3)) return SIZES_HALF;
  return SIZES_QUARTER;
}

function tileSpan(total: number, position: number): string {
  if (total === 1) return 'lg:col-span-4';
  if (total === 2) return 'lg:col-span-2';
  if (position === 0) return 'lg:col-span-2 lg:row-span-2 lg:aspect-auto lg:h-full lg:min-h-full';
  if (total === 4 && position === 3) return 'lg:col-span-2';
  return 'lg:col-span-1';
}

function hasUsableUrl(url: string | undefined): boolean {
  return typeof url === 'string' && url.trim() !== '';
}

function hasUsableAlt(alt: string | undefined): boolean {
  return typeof alt === 'string' && alt.trim() !== '';
}

function hasSuppliedBlur(image: TourImage): boolean {
  const carrier = image as BlurCarrier;
  return Boolean(carrier.blur_data_url || carrier.cover_image_blur);
}

function MissingPhotoMark({ label }: { label: string }) {
  return (
    <span className="flex h-full w-full flex-col items-center justify-center gap-2 bg-surface-alt p-4 text-center">
      <svg
        className="h-10 w-10 text-text-muted"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.5}
          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
        />
      </svg>
      <span className="text-sm text-text-muted">{label}</span>
    </span>
  );
}

/**
 * Isolated canonical tour gallery (Spec 020 US3).
 *
 * One photo DOM serves every viewport (scroll-snap rail below 1024px, mosaic
 * at >=1024px) so the server snapshot and hydrated tree stay identical. Tile
 * `sizes` match the page container math exactly; viewer arrows live on a
 * React parent around the Dialog so portal-bubbled keys navigate without
 * moving focus.
 */
export default function TourGallery({ images, title, contentLocale }: TourGalleryProps) {
  const t = useTranslations('tour.gallery');

  // Cover first everywhere, preserving caller relative order otherwise.
  const displayImages: TourImage[] = useMemo(
    () => [...images.filter((img) => img.is_cover), ...images.filter((img) => !img.is_cover)],
    [images],
  );
  const total = displayImages.length;

  const [railIndex, setRailIndex] = useState(0);
  const [viewerOpen, setViewerOpen] = useState(false);
  const [viewerIndex, setViewerIndex] = useState(0);
  const [failedUrls, setFailedUrls] = useState<readonly string[]>([]);
  const trackRef = useRef<HTMLDivElement>(null);

  const activeRailIndex = total === 0 ? 0 : Math.min(Math.max(railIndex, 0), total - 1);
  const boundedViewerIndex = total === 0 ? 0 : Math.min(Math.max(viewerIndex, 0), total - 1);
  const current = displayImages[boundedViewerIndex];

  const isFailed = useCallback(
    (image: TourImage) => !hasUsableUrl(image.url) || failedUrls.includes(image.url),
    [failedUrls],
  );

  const markFailed = useCallback((image: TourImage) => {
    if (!hasUsableUrl(image.url)) return;
    setFailedUrls((prev) => (prev.includes(image.url) ? prev : [...prev, image.url]));
  }, []);

  // Actual language of each image text: authored alt_locale, else the
  // passed actual selected content locale (fallback title language).
  const resolveImageLang = useCallback(
    (image: TourImage): string | undefined => image.alt_locale || contentLocale || undefined,
    [contentLocale],
  );

  const resolveAlt = useCallback((image: TourImage): string => (hasUsableAlt(image.alt) ? image.alt : title), [title]);

  const openViewer = useCallback(
    (index: number, trigger: HTMLElement | null) => {
      if (total === 0) return;
      trigger?.focus();
      setViewerIndex(Math.min(Math.max(index, 0), total - 1));
      setViewerOpen(true);
    },
    [total],
  );

  const closeViewer = useCallback(() => setViewerOpen(false), []);

  const stepViewer = useCallback(
    (delta: number) => {
      if (total === 0) return;
      setViewerIndex((prev) => (prev + delta + total) % total);
    },
    [total],
  );

  const handleViewerKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!viewerOpen) return;
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        stepViewer(-1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        stepViewer(1);
      }
    },
    [viewerOpen, stepViewer],
  );

  const scrollTrackTo = useCallback(
    (index: number) => {
      if (total === 0) return;
      const bounded = (index + total) % total;
      setRailIndex(bounded);
      const track = trackRef.current;
      if (!track) return;
      const width = track.clientWidth || 0;
      if (width <= 0) return;
      const reducedMotion = window.matchMedia(REDUCED_MOTION_QUERY).matches;
      track.scrollTo({ left: bounded * width, behavior: reducedMotion ? 'auto' : 'smooth' });
    },
    [total],
  );

  const handleTrackScroll = useCallback(
    (event: UIEvent<HTMLDivElement>) => {
      const track = event.currentTarget;
      const width = track.clientWidth || 1;
      const next = Math.round(track.scrollLeft / width);
      setRailIndex(Math.min(Math.max(next, 0), Math.max(total - 1, 0)));
    },
    [total],
  );

  if (total === 0) {
    return (
      <div
        data-testid="tour-gallery-empty"
        className="relative aspect-[16/10] w-full overflow-hidden rounded-xl bg-surface-alt"
      >
        <p role="status" className="flex h-full items-center justify-center px-6 text-center text-sm text-text-muted">
          {t('empty')}
        </p>
      </div>
    );
  }

  return (
    <div data-testid="tour-gallery" className="relative">
      <div
        ref={trackRef}
        onScroll={handleTrackScroll}
        data-testid="tour-gallery-track"
        className="flex snap-x snap-mandatory overflow-x-auto lg:grid lg:grid-cols-4 lg:gap-2 lg:overflow-visible lg:snap-none"
      >
        {displayImages.map((image, position) => (
          <button
            key={`${position}-${image.url}`}
            type="button"
            onClick={(event) => openViewer(position, event.currentTarget)}
            aria-label={t('view', { number: position + 1 })}
            className={`relative block aspect-[16/10] w-full shrink-0 snap-center overflow-hidden rounded-none focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus lg:rounded-xl ${tileSpan(total, position)} ${position >= VISIBLE_TILES ? 'lg:hidden' : ''}`}
          >
            {isFailed(image) ? (
              <MissingPhotoMark label={t('imageFailed')} />
            ) : (
              <Image
                src={image.url}
                alt={resolveAlt(image)}
                lang={resolveImageLang(image)}
                fill
                sizes={tileSizes(total, position)}
                className="object-cover"
                {...(position === 0 ? { preload: true, fetchPriority: 'high' as const } : { loading: 'lazy' as const })}
                onError={() => markFailed(image)}
                {...(hasSuppliedBlur(image) ? getImagePlaceholderProps(image) : {})}
              />
            )}
          </button>
        ))}
      </div>

      {total > 1 && (
        <>
          <button
            type="button"
            onClick={() => scrollTrackTo(activeRailIndex - 1)}
            aria-label={t('previous')}
            className="absolute left-2 top-1/2 inline-flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface/90 p-2 text-primary shadow-modal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:hidden"
          >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => scrollTrackTo(activeRailIndex + 1)}
            aria-label={t('next')}
            className="absolute right-2 top-1/2 inline-flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface/90 p-2 text-primary shadow-modal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:hidden"
          >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </>
      )}
      <p
        aria-live="polite"
        className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-bookly-navy px-3 py-1 text-xs font-medium text-white lg:hidden"
      >
        {t('counter', { current: activeRailIndex + 1, total })}
      </p>
      {total > 1 && (
        <button
          type="button"
          onClick={(event) => openViewer(0, event.currentTarget)}
          className="absolute bottom-3 right-3 hidden min-h-11 items-center rounded-full bg-surface px-4 text-sm font-semibold text-primary shadow-modal focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:inline-flex"
        >
          {t('seeAll', { count: total })}
        </button>
      )}

      <div onKeyDown={handleViewerKeyDown}>
        <Dialog
          open={viewerOpen}
          onClose={closeViewer}
          title={t('dialog')}
          closeLabel={t('close')}
          placement="fullscreen"
        >
          <div className="flex h-full min-h-0 flex-col">
            <div className="relative min-h-[240px] flex-1 overflow-hidden rounded-lg bg-surface-alt">
              {current && (isFailed(current) ? (
                <MissingPhotoMark label={t('imageFailed')} />
              ) : (
                <Image
                  src={current.url}
                  alt={resolveAlt(current)}
                  lang={resolveImageLang(current)}
                  fill
                  sizes={SIZES_VIEWER}
                  className="object-contain"
                  loading="lazy"
                  onError={() => markFailed(current)}
                  {...(hasSuppliedBlur(current) ? getImagePlaceholderProps(current) : {})}
                />
              ))}
            </div>
            <div className="mt-3 flex shrink-0 items-center justify-center gap-4">
              {total > 1 && (
                <button
                  type="button"
                  onClick={() => stepViewer(-1)}
                  aria-label={t('previous')}
                  className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full bg-surface-alt p-3 text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                  <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                  </svg>
                </button>
              )}
              <p aria-live="polite" className="text-sm font-medium text-primary">
                {t('counter', { current: boundedViewerIndex + 1, total })}
              </p>
              {total > 1 && (
                <button
                  type="button"
                  onClick={() => stepViewer(1)}
                  aria-label={t('next')}
                  className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full bg-surface-alt p-3 text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                  <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              )}
            </div>
            {current && hasUsableAlt(current.alt) && (
              <p lang={resolveImageLang(current)} className="mt-2 shrink-0 text-sm text-text-muted">
                {current.alt}
              </p>
            )}
          </div>
        </Dialog>
      </div>
    </div>
  );
}

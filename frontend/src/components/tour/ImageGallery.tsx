'use client';

import { useState, useCallback, useEffect, useRef } from 'react';
import Image from 'next/image';
import type { TourImage } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import { useTranslations } from 'next-intl';

interface ImageGalleryProps {
  images: TourImage[];
  title: string;
}

export default function ImageGallery({ images, title }: ImageGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const closeRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const t = useTranslations('tour.gallery');

  const closeGallery = useCallback(() => {
    setLightboxOpen(false);
    triggerRef.current?.focus();
  }, []);

  const goTo = useCallback((index: number) => {
    setActiveIndex((index + images.length) % images.length);
  }, [images.length]);

  useEffect(() => {
    if (!lightboxOpen) return;

    closeRef.current?.focus();

    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft') goTo(activeIndex - 1);
      if (e.key === 'ArrowRight') goTo(activeIndex + 1);
      if (e.key === 'Escape') closeGallery();
      if (e.key === 'Tab') {
        const buttons = Array.from(dialogRef.current?.querySelectorAll('button') ?? []);
        if (buttons.length === 0) return;
        const first = buttons[0];
        const last = buttons[buttons.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };

    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [lightboxOpen, activeIndex, goTo, closeGallery]);

  const displayImages = images.length > 0
    ? images
    : [{ url: '', is_cover: true, alt: title }];

  const currentImage = displayImages[activeIndex];

  return (
    <>
      <div className="relative overflow-hidden rounded-xl bg-surface-alt">
        <div className="relative aspect-[16/10]">
          {currentImage.url ? (
            <button
              ref={triggerRef}
              type="button"
              onClick={() => setLightboxOpen(true)}
              className="relative block h-full w-full focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus"
              aria-label={t('open')}
            >
              <Image
                src={currentImage.url}
                alt={currentImage.alt || title}
                fill
                sizes="(min-width: 1024px) 66vw, 100vw"
                className="object-cover"
                priority
                {...getImagePlaceholderProps(currentImage)}
              />
            </button>
          ) : (
            <div className="flex h-full items-center justify-center text-gray-400">
              <svg className="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          )}

          {displayImages.length > 1 && (
            <>
              <button
                onClick={() => goTo(activeIndex - 1)}
                className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-md hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#0A2540]"
                aria-label={t('previous')}
              >
                <svg className="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                onClick={() => goTo(activeIndex + 1)}
                className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-md hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#0A2540]"
                aria-label={t('next')}
              >
                <svg className="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </>
          )}
        </div>

        {displayImages.length > 1 && (
          <div className="flex gap-2 overflow-x-auto p-3" role="list" aria-label={t('thumbnails')}>
            {displayImages.map((img, i) => (
              <div key={i} role="listitem">
                <button
                  type="button"
                  onClick={() => setActiveIndex(i)}
                  className={`relative h-16 w-24 shrink-0 overflow-hidden rounded-md border-2 transition-colors ${
                    i === activeIndex ? 'border-bookly-navy' : 'border-transparent hover:border-border'
                  }`}
                  aria-label={t('view', { number: i + 1 })}
                  aria-current={i === activeIndex ? 'true' : undefined}
                >
                  {img.url ? (
                    <Image
                      src={img.url}
                      alt={img.alt || `${title} thumbnail ${i + 1}`}
                      fill
                      sizes="96px"
                      className="object-cover"
                      {...getImagePlaceholderProps(img)}
                    />
                  ) : null}
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Lightbox */}
      {lightboxOpen && (
        <div
          ref={dialogRef}
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
          onClick={closeGallery}
          role="dialog"
          aria-modal="true"
          aria-label={t('dialog')}
        >
          <button
            ref={closeRef}
            type="button"
            onClick={closeGallery}
            className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
            aria-label={t('close')}
          >
            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          {displayImages.length > 1 && (
            <>
              <button type="button" onClick={(event) => { event.stopPropagation(); goTo(activeIndex - 1); }} aria-label={t('previous')} className="absolute left-3 z-10 rounded-full bg-white/15 p-3 text-white hover:bg-white/25 focus-visible:ring-2 focus-visible:ring-white">‹</button>
              <button type="button" onClick={(event) => { event.stopPropagation(); goTo(activeIndex + 1); }} aria-label={t('next')} className="absolute right-3 z-10 rounded-full bg-white/15 p-3 text-white hover:bg-white/25 focus-visible:ring-2 focus-visible:ring-white">›</button>
            </>
          )}
          <div
            className="relative aspect-[16/10] w-[min(90vw,1100px)] max-h-[85vh]"
            onClick={(e) => e.stopPropagation()}
          >
            {currentImage.url && (
              <Image
                src={currentImage.url}
                alt={currentImage.alt || title}
                fill
                sizes="90vw"
                className="object-contain"
                {...getImagePlaceholderProps(currentImage)}
              />
            )}
          </div>
          {currentImage.alt && <p className="absolute bottom-4 max-w-[80vw] text-center text-sm text-white">{currentImage.alt}</p>}
        </div>
      )}
    </>
  );
}

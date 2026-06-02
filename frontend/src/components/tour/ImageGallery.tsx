'use client';

import { useState, useCallback, useEffect } from 'react';
import Image from 'next/image';
import type { TourImage } from '@/lib/api/types';

interface ImageGalleryProps {
  images: TourImage[];
  title: string;
}

export default function ImageGallery({ images, title }: ImageGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const [lightboxOpen, setLightboxOpen] = useState(false);

  const goTo = useCallback((index: number) => {
    setActiveIndex((index + images.length) % images.length);
  }, [images.length]);

  useEffect(() => {
    if (!lightboxOpen) return;

    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft') goTo(activeIndex - 1);
      if (e.key === 'ArrowRight') goTo(activeIndex + 1);
      if (e.key === 'Escape') setLightboxOpen(false);
    };

    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [lightboxOpen, activeIndex, goTo]);

  const displayImages = images.length > 0
    ? images
    : [{ url: '', is_cover: true, alt: title }];

  const currentImage = displayImages[activeIndex];

  return (
    <>
      <div className="relative overflow-hidden rounded-xl bg-gray-100">
        <div className="relative aspect-[16/10]">
          {currentImage.url ? (
            <Image
              src={currentImage.url}
              alt={currentImage.alt || title}
              fill
              sizes="(min-width: 1024px) 66vw, 100vw"
              className="object-cover cursor-pointer"
              onClick={() => setLightboxOpen(true)}
              priority
            />
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
                aria-label="Previous image"
              >
                <svg className="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                onClick={() => goTo(activeIndex + 1)}
                className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-md hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#0A2540]"
                aria-label="Next image"
              >
                <svg className="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </>
          )}
        </div>

        {displayImages.length > 1 && (
          <div className="flex gap-2 p-3 overflow-x-auto" role="list" aria-label="Thumbnail navigation">
            {displayImages.map((img, i) => (
              <button
                key={i}
                onClick={() => setActiveIndex(i)}
                className={`relative h-16 w-24 shrink-0 overflow-hidden rounded-md border-2 transition-colors ${
                  i === activeIndex ? 'border-[#0A2540]' : 'border-transparent hover:border-gray-300'
                }`}
                aria-label={`View image ${i + 1}`}
                aria-current={i === activeIndex ? 'true' : undefined}
                role="listitem"
              >
                {img.url ? (
                  <Image
                    src={img.url}
                    alt={img.alt || `${title} thumbnail ${i + 1}`}
                    fill
                    sizes="96px"
                    className="object-cover"
                  />
                ) : null}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Lightbox */}
      {lightboxOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
          onClick={() => setLightboxOpen(false)}
          role="dialog"
          aria-label="Image lightbox"
        >
          <button
            onClick={() => setLightboxOpen(false)}
            className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
            aria-label="Close lightbox"
          >
            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div
            className="relative max-h-[90vh] max-w-[90vw] aspect-[16/10]"
            onClick={(e) => e.stopPropagation()}
          >
            {currentImage.url && (
              <Image
                src={currentImage.url}
                alt={currentImage.alt || title}
                fill
                sizes="90vw"
                className="object-contain"
              />
            )}
          </div>
        </div>
      )}
    </>
  );
}

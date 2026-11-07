'use client';

import { useState } from 'react';
import Image, { type ImageProps } from 'next/image';

interface ImageWithFallbackProps extends ImageProps {
  fallbackSrc?: string;
  fallbackIcon?: React.ReactNode;
}

export default function ImageWithFallback({
  src,
  alt,
  fallbackSrc,
  fallbackIcon,
  className,
  ...rest
}: ImageWithFallbackProps) {
  const [error, setError] = useState(false);

  if (error) {
    if (fallbackSrc) {
      return (
        <Image
          {...rest}
          src={fallbackSrc}
          alt={alt}
          className={className}
          onError={() => setError(true)}
        />
      );
    }

    return (
      <div
        className={`flex h-full w-full items-center justify-center bg-surface-alt text-text-muted ${
          className || ''
        }`}
        aria-hidden="true"
      >
        {fallbackIcon || (
          <svg className="h-10 w-10 text-text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
        )}
      </div>
    );
  }

  return (
    <Image
      {...rest}
      src={src}
      alt={alt}
      className={className}
      onError={() => setError(true)}
    />
  );
}


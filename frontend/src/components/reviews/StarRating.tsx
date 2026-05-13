'use client';

import { useCallback } from 'react';

interface StarRatingProps {
  value: number;
  onChange?: (rating: number) => void;
  readOnly?: boolean;
  size?: 'sm' | 'md' | 'lg';
}

const sizeMap = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-8 w-8' };

export default function StarRating({
  value,
  onChange,
  readOnly = false,
  size = 'md',
}: StarRatingProps) {
  const handleClick = useCallback(
    (star: number) => {
      if (!readOnly && onChange) {
        onChange(star);
      }
    },
    [readOnly, onChange],
  );

  const handleKeyDown = useCallback(
    (star: number, e: React.KeyboardEvent) => {
      if (readOnly) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onChange?.(star);
      }
    },
    [readOnly, onChange],
  );

  return (
    <div
      className="inline-flex items-center gap-0.5"
      role="radiogroup"
      aria-label="Star rating"
      aria-readonly={readOnly}
    >
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          role="radio"
          aria-checked={star === value}
          aria-label={`${star} star${star > 1 ? 's' : ''}`}
          tabIndex={readOnly ? -1 : 0}
          disabled={readOnly}
          className={`${sizeMap[size]} transition-colors ${
            readOnly ? 'cursor-default' : 'cursor-pointer hover:scale-110'
          } focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1 rounded-sm`}
          onClick={() => handleClick(star)}
          onKeyDown={(e) => handleKeyDown(star, e)}
        >
          <svg
            className={`h-full w-full ${star <= value ? 'text-yellow-400' : 'text-gray-300'}`}
            fill="currentColor"
            viewBox="0 0 20 20"
            aria-hidden="true"
          >
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        </button>
      ))}
    </div>
  );
}

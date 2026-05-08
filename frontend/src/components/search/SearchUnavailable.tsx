'use client';

import { useState, useEffect } from 'react';

interface SearchUnavailableProps {
  /** Optional retry callback to re-attempt the search */
  onRetry?: () => void;
  /** Number of seconds before auto-retry (from Retry-After header) */
  retryAfter?: number;
}

export default function SearchUnavailable({ onRetry, retryAfter }: SearchUnavailableProps) {
  const [countdown, setCountdown] = useState(retryAfter ?? 0);

  useEffect(() => {
    if (countdown <= 0) return;

    const timer = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(timer);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [countdown]);

  return (
    <div
      className="flex flex-col items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-6 py-16 text-center"
      role="status"
      aria-live="polite"
    >
      {/* Search unavailable icon */}
      <div className="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
        <svg
          className="h-8 w-8 text-amber-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={1.5}
            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
          />
        </svg>
      </div>

      <h3 className="mb-2 text-xl font-semibold text-amber-800">
        Search is temporarily unavailable
      </h3>

      <p className="mb-6 max-w-md text-sm text-amber-700">
        We&apos;re experiencing a brief issue with our search service. Please try again shortly.
      </p>

      {countdown > 0 && (
        <p className="mb-4 text-xs text-amber-600">
          Automatically retrying in{' '}
          <span className="font-semibold">{countdown}</span>{' '}
          second{countdown !== 1 ? 's' : ''}…
        </p>
      )}

      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          disabled={countdown > 0}
          className="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          aria-label="Retry search"
        >
          Try Again
        </button>
      )}

      {!onRetry && (
        <a
          href="/"
          className="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
        >
          Return to Homepage
        </a>
      )}
    </div>
  );
}

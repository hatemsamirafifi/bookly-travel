'use client';

import { useEffect, useRef } from 'react';
import { useTranslations } from 'next-intl';

interface PriceChangeModalProps {
  oldPrice: string;
  newPrice: string;
  onConfirm: () => void;
  onCancel: () => void;
}

/**
 * WCAG 2.1 AA compliant modal for FR-027 price-change re-confirmation.
 * - Focus is trapped inside the modal while open.
 * - Escape key closes (cancel) without booking.
 * - Backdrop click cancels.
 * - aria-modal and role="dialog" for screen readers.
 */
export default function PriceChangeModal({
  oldPrice,
  newPrice,
  onConfirm,
  onCancel,
}: PriceChangeModalProps) {
  const t = useTranslations('booking.priceChangeModal');
  const confirmRef = useRef<HTMLButtonElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);

  // Move focus into modal on mount
  useEffect(() => {
    confirmRef.current?.focus();
  }, []);

  // Escape key handler
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onCancel();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onCancel]);

  // Tab trap: keep focus inside the modal
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key !== 'Tab') return;
    const focusable = [cancelRef.current, confirmRef.current].filter(Boolean) as HTMLButtonElement[];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };

  return (
    /* Backdrop */
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      role="presentation"
      onClick={onCancel}
    >
      {/* Dialog */}
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="price-change-title"
        aria-describedby="price-change-desc"
        className="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
        onKeyDown={handleKeyDown}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Icon */}
        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
          <svg
            aria-hidden="true"
            className="h-6 w-6 text-amber-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
            />
          </svg>
        </div>

        <h2 id="price-change-title" className="mb-2 text-lg font-semibold text-gray-900">
          {t('title')}
        </h2>
        <p id="price-change-desc" className="mb-4 text-sm text-gray-600">
          {t('body')}
        </p>

        {/* Price comparison */}
        <div className="mb-6 space-y-2 rounded-xl bg-gray-50 p-4">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-500">{t('from')}</span>
            <span className="text-gray-400 line-through">{oldPrice}</span>
          </div>
          <div className="flex items-center justify-between text-sm font-semibold">
            <span className="text-gray-700">{t('to')}</span>
            <span className="text-emerald-600">{newPrice}</span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex flex-col gap-3 sm:flex-row-reverse">
          <button
            ref={confirmRef}
            type="button"
            onClick={onConfirm}
            className="flex-1 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          >
            {t('confirm')}
          </button>
          <button
            ref={cancelRef}
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
          >
            {t('cancel')}
          </button>
        </div>
      </div>
    </div>
  );
}

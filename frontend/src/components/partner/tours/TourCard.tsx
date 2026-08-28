'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import Image from 'next/image';
import type { Tour } from '@/types/partner';
import Link from 'next/link';
import { MapPin, Clock, Edit, Archive } from 'lucide-react';
import { archiveTour } from '@/lib/api/partner';

const statusBadge: Record<Tour['status'], string> = {
  draft: 'bg-gray-100 text-gray-700',
  pending_review: 'bg-amber-50 text-amber-700',
  published: 'bg-emerald-50 text-emerald-700',
  rejected: 'bg-red-50 text-red-700',
  archived: 'bg-slate-100 text-slate-500',
};

const statusLabel: Record<Tour['status'], string> = {
  draft: 'Draft',
  pending_review: 'Pending Review',
  published: 'Published',
  rejected: 'Rejected',
  archived: 'Archived',
};

/** Statuses that get an explanatory hint line beneath the badge. */
const HINTED_STATUSES: Tour['status'][] = ['pending_review', 'rejected'];

interface TourCardProps {
  tour: Tour;
  /** Called after a successful archive so the parent can refetch. */
  onArchived?: () => void;
}

export function TourCard({ tour, onArchived }: TourCardProps) {
  const t = useTranslations('partner.tours');
  const [archiving, setArchiving] = useState(false);
  const [archiveError, setArchiveError] = useState<string | null>(null);

  const handleArchive = async () => {
    if (tour.status === 'archived' || archiving) return;
    const confirmed = window.confirm(t('archiveConfirm'));
    if (!confirmed) return;
    setArchiving(true);
    setArchiveError(null);
    try {
      await archiveTour(tour.id);
      onArchived?.();
    } catch (err: unknown) {
      setArchiveError(err instanceof Error ? err.message : t('archiveFailed'));
    } finally {
      setArchiving(false);
    }
  };

  const showHint = HINTED_STATUSES.includes(tour.status);

  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
      <div className="h-40 bg-gray-100 relative">
        {(() => {
          const cover = tour.media?.find((m) => m.is_cover);
          return cover?.url ? (
            <Image
              src={cover.url}
              alt={tour.title}
              fill
              className="object-cover"
              sizes="(max-width: 768px) 100vw, 33vw"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-600 text-sm">
              {t('noCoverImage')}
            </div>
          );
        })()}
        <span className={`absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-medium ${statusBadge[tour.status]}`}>
          {t(`status.${tour.status}`) ?? statusLabel[tour.status]}
        </span>
      </div>
      <div className="p-4 space-y-2">
        <h2 className="font-semibold text-[#0A2540] truncate">{tour.title ?? t('untitled')}</h2>
        {showHint && (
          <p className="text-xs text-gray-500">{t(`statusHint.${tour.status}`)}</p>
        )}
        <div className="flex items-center gap-4 text-sm text-gray-500">
          <span className="flex items-center gap-1">
            <MapPin className="w-3.5 h-3.5" />
            {tour.destination ?? tour.location ?? '-'}
          </span>
          <span className="flex items-center gap-1">
            <Clock className="w-3.5 h-3.5" />
            {tour.duration?.label ?? '-'}
          </span>
        </div>
        <div className="flex items-center justify-between pt-2">
          <span className="text-sm font-medium text-[#0A2540]">
            {tour.pricing_tiers?.length > 0
              ? `€${Math.min(...tour.pricing_tiers.map((tier) => Number(tier.price))).toFixed(2)}`
              : '—'}
          </span>
          <div className="flex items-center gap-2">
            <Link
              href={`/partner/tours/${tour.id}/edit`}
              className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#0A2540] transition-colors"
              aria-label={t('ariaEdit')}
            >
              <Edit className="w-4 h-4" />
            </Link>
            {tour.status !== 'archived' && (
              <button
                type="button"
                onClick={handleArchive}
                disabled={archiving}
                aria-label={t('ariaArchive')}
                className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Archive className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
        {archiveError && (
          <p className="text-xs text-red-600" role="alert">{archiveError}</p>
        )}
      </div>
    </div>
  );
}
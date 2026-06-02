'use client';

import type { Tour } from '@/types/partner';
import Link from 'next/link';
import { MapPin, Clock, Edit, Archive } from 'lucide-react';

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

interface TourCardProps {
  tour: Tour;
}

export function TourCard({ tour }: TourCardProps) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
      <div className="h-40 bg-gray-100 relative">
        {tour.cover_image_url ? (
          <img
            src={tour.cover_image_url}
            alt={tour.title}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-gray-400 text-sm">
            No cover image
          </div>
        )}
        <span className={`absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-medium ${statusBadge[tour.status]}`}>
          {statusLabel[tour.status]}
        </span>
      </div>
      <div className="p-4 space-y-2">
        <h3 className="font-semibold text-[#0A2540] truncate">{tour.title ?? 'Untitled Tour'}</h3>
        <div className="flex items-center gap-4 text-sm text-gray-500">
          <span className="flex items-center gap-1">
            <MapPin className="w-3.5 h-3.5" />
            {tour.destination ?? tour.location ?? 'Unknown'}
          </span>
          <span className="flex items-center gap-1">
            <Clock className="w-3.5 h-3.5" />
            {tour.duration_value ?? '-'} {tour.duration_unit ?? tour.duration_label ?? ''}
          </span>
        </div>
        <div className="flex items-center justify-between pt-2">
          <span className="text-sm font-medium text-[#0A2540]">
            {tour.price_from ? `€${Number(tour.price_from).toFixed(2)}` : '—'}
          </span>
          <div className="flex items-center gap-2">
            <Link
              href={`/partner/tours/${tour.id}/edit`}
              className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-[#0A2540] transition-colors"
              aria-label="Edit tour"
            >
              <Edit className="w-4 h-4" />
            </Link>
            {tour.status !== 'archived' && (
              <button
                type="button"
                className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-red-600 transition-colors"
                aria-label="Archive tour"
              >
                <Archive className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
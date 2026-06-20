'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Shield, AlertTriangle, Eye, EyeOff, History, X } from 'lucide-react';
import { fetchAdminReviews, hideReview, reinstateReview } from '@/lib/reviews/review-api';
import type { AdminReview } from '@/lib/reviews/review-api';
import StarRating from '@/components/reviews/StarRating';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import Toast from '@/components/ui/Toast';

export default function AdminReviewPanel() {
  const t = useTranslations('admin.reviews');
  const queryClient = useQueryClient();

  const [filters, setFilters] = useState<{
    status: string;
    tour_id: string;
    date_from: string;
    date_to: string;
    flagged: boolean;
  }>({
    status: '',
    tour_id: '',
    date_from: '',
    date_to: '',
    flagged: false,
  });

  const [appliedFilters, setAppliedFilters] = useState(filters);
  const [page, setPage] = useState(1);
  const [confirmModal, setConfirmModal] = useState<{
    review: AdminReview;
    action: 'hide' | 'reinstate';
    reason: string;
  } | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-reviews', appliedFilters, page],
    queryFn: async () =>
      fetchAdminReviews({
        status: appliedFilters.status || undefined,
        tour_id: appliedFilters.tour_id || undefined,
        date_from: appliedFilters.date_from || undefined,
        date_to: appliedFilters.date_to || undefined,
        flagged: appliedFilters.flagged || undefined,
        page,
      }),
    staleTime: 30_000,
    retry: 1,
  });

  const reviews = data?.data ?? [];
  const meta = data?.meta;

  const hideMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => hideReview(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-reviews'] });
      setToast({ message: 'Review hidden successfully.', type: 'success' });
      setConfirmModal(null);
    },
    onError: () => {
      setToast({ message: 'Failed to hide review.', type: 'error' });
    },
  });

  const reinstateMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => reinstateReview(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-reviews'] });
      setToast({ message: 'Review reinstated successfully.', type: 'success' });
      setConfirmModal(null);
    },
    onError: () => {
      setToast({ message: 'Failed to reinstate review.', type: 'error' });
    },
  });

  const handleApplyFilters = () => {
    setPage(1);
    setAppliedFilters(filters);
  };

  const handleClearFilters = () => {
    const cleared = { status: '', tour_id: '', date_from: '', date_to: '', flagged: false };
    setFilters(cleared);
    setAppliedFilters(cleared);
    setPage(1);
  };

  const handleConfirmAction = () => {
    if (!confirmModal || !confirmModal.reason.trim()) return;
    if (confirmModal.action === 'hide') {
      hideMutation.mutate({ id: confirmModal.review.id, reason: confirmModal.reason.trim() });
    } else {
      reinstateMutation.mutate({ id: confirmModal.review.id, reason: confirmModal.reason.trim() });
    }
  };

  const isPending = hideMutation.isPending || reinstateMutation.isPending;

  const statusBadge = (status: string) => {
    const styles: Record<string, string> = {
      visible: 'bg-green-50 text-green-700 border-green-200',
      hidden: 'bg-gray-100 text-gray-600 border-gray-200',
      flagged: 'bg-red-50 text-red-700 border-red-200',
    };
    return (
      <span
        className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ${
          styles[status] || styles.hidden
        }`}
      >
        {status === 'flagged' && <AlertTriangle className="h-3 w-3" />}
        {status === 'hidden' && <EyeOff className="h-3 w-3" />}
        {status === 'visible' && <Eye className="h-3 w-3" />}
        {status}
      </span>
    );
  };

  return (
    <div className="space-y-4">
      {/* Toast */}
      {toast && (
        <div className="mb-4">
          <Toast
            message={toast.message}
            type={toast.type}
            onClose={() => setToast(null)}
          />
        </div>
      )}

      {/* Filters */}
      <div className="rounded-lg border border-gray-200 bg-white p-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[140px]">
            <label className="mb-1 block text-xs font-medium text-gray-700">
              {t('filters.status')}
            </label>
            <select
              value={filters.status}
              onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
            >
              <option value="">{t('filters.all')}</option>
              <option value="visible">{t('filters.visible')}</option>
              <option value="hidden">{t('filters.hidden')}</option>
              <option value="flagged">{t('filters.flagged')}</option>
            </select>
          </div>
          <div className="flex-1 min-w-[140px]">
            <label className="mb-1 block text-xs font-medium text-gray-700">
              {t('filters.tourId')}
            </label>
            <input
              type="text"
              value={filters.tour_id}
              onChange={(e) => setFilters((f) => ({ ...f, tour_id: e.target.value }))}
              placeholder="e.g. 42"
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
            />
          </div>
          <div className="flex-1 min-w-[140px]">
            <label className="mb-1 block text-xs font-medium text-gray-700">
              {t('filters.dateFrom')}
            </label>
            <input
              type="date"
              value={filters.date_from}
              onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
            />
          </div>
          <div className="flex-1 min-w-[140px]">
            <label className="mb-1 block text-xs font-medium text-gray-700">
              {t('filters.dateTo')}
            </label>
            <input
              type="date"
              value={filters.date_to}
              onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value }))}
              className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
            />
          </div>
          <div className="flex items-center gap-2 pb-2">
            <input
              id="flagged"
              type="checkbox"
              checked={filters.flagged}
              onChange={(e) => setFilters((f) => ({ ...f, flagged: e.target.checked }))}
              className="h-4 w-4 rounded border-gray-300 text-[#FFB800] focus:ring-[#FFB800]"
            />
            <label htmlFor="flagged" className="text-sm text-gray-700">
              {t('filters.flaggedOnly')}
            </label>
          </div>
          <div className="flex gap-2 pb-2">
            <button
              onClick={handleApplyFilters}
              className="rounded-lg bg-[#0A2540] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0A2540]/90"
            >
              {t('filters.apply')}
            </button>
            <button
              onClick={handleClearFilters}
              className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              {t('filters.clear')}
            </button>
          </div>
        </div>
      </div>

      {/* Content */}
      {isLoading && <LoadingSkeleton variant="list" count={4} />}

      {!isLoading && error && (
        <ErrorState message={t('loadError')} onRetry={() => refetch()} />
      )}

      {!isLoading && !error && reviews.length === 0 && (
        <EmptyState title={t('empty')} icon={<Shield className="h-6 w-6" />} />
      )}

      {!isLoading && !error && reviews.length > 0 && (
        <>
          <div className="space-y-3">
            {reviews.map((review) => (
              <div
                key={review.id}
                className="rounded-lg border border-gray-200 bg-white p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-sm font-semibold text-gray-900">
                        {review.reviewer_name}
                      </span>
                      {statusBadge(review.status)}
                    </div>
                    <div className="flex items-center gap-2 mb-1">
                      <StarRating value={review.rating} readOnly size="sm" />
                      <span className="text-xs text-gray-400">
                        {new Date(review.created_at).toLocaleDateString()}
                      </span>
                    </div>
                    <p className="text-sm text-gray-700 leading-relaxed">{review.comment}</p>

                    {/* Audit trail */}
                    {review.audit_trail && review.audit_trail.length > 0 && (
                      <div className="mt-3 rounded-md bg-gray-50 p-3">
                        <div className="flex items-center gap-1 mb-1">
                          <History className="h-3.5 w-3.5 text-gray-500" />
                          <span className="text-xs font-medium text-gray-500">
                            {t('auditTrail')}
                          </span>
                        </div>
                        <ul className="space-y-1">
                          {review.audit_trail.slice(-3).map((entry, idx) => (
                            <li key={idx} className="text-xs text-gray-600">
                              <span className="font-medium">{entry.action}</span>
                              {entry.reason && (
                                <span className="text-gray-500"> — {entry.reason}</span>
                              )}
                              <span className="ml-1 text-gray-400">
                                {new Date(entry.created_at).toLocaleDateString()}
                              </span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    {(review.status === 'visible' || review.status === 'flagged') && (
                      <button
                        onClick={() =>
                          setConfirmModal({
                            review,
                            action: 'hide',
                            reason: '',
                          })
                        }
                        className="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100"
                      >
                        {t('hide')}
                      </button>
                    )}
                    {review.status === 'hidden' && (
                      <button
                        onClick={() =>
                          setConfirmModal({
                            review,
                            action: 'reinstate',
                            reason: '',
                          })
                        }
                        className="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-100"
                      >
                        {t('reinstate')}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4">
              <span className="text-sm text-gray-600">
                {t('page')} {meta.current_page} {t('of')} {meta.last_page}
              </span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  {t('previous')}
                </button>
                <button
                  onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
                  disabled={page >= meta.last_page}
                  className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                  {t('next')}
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Confirmation modal */}
      {confirmModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold text-gray-900">
                {confirmModal.action === 'hide'
                  ? t('hideConfirmTitle')
                  : t('reinstateConfirmTitle')}
              </h3>
              <button
                onClick={() => setConfirmModal(null)}
                className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <p className="mb-4 text-sm text-gray-600">
              {confirmModal.action === 'hide'
                ? t('hideConfirmBody')
                : t('reinstateConfirmBody')}
            </p>
            <div className="mb-4">
              <label className="mb-1 block text-sm font-medium text-gray-700">
                {t('reasonLabel')}
              </label>
              <textarea
                value={confirmModal.reason}
                onChange={(e) =>
                  setConfirmModal((m) => (m ? { ...m, reason: e.target.value } : null))
                }
                placeholder={t('reasonPlaceholder')}
                rows={3}
                className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
              />
              {!confirmModal.reason.trim() && (
                <p className="mt-1 text-xs text-red-600">{t('reasonRequired')}</p>
              )}
            </div>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setConfirmModal(null)}
                className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                {t('cancel')}
              </button>
              <button
                onClick={handleConfirmAction}
                disabled={isPending || !confirmModal.reason.trim()}
                className="rounded-lg bg-[#0A2540] px-4 py-2 text-sm font-medium text-white hover:bg-[#0A2540]/90 disabled:opacity-50"
              >
                {isPending ? '...' : t('confirm')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import { useWishlist } from '@/hooks/useWishlist';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';

interface WishlistGridProps {
  locale: string;
}

export default function WishlistGrid({ locale }: WishlistGridProps) {
  const t = useTranslations('traveler.wishlist');
  const { data: items, isLoading, error, refetch, removeItem } = useWishlist();

  if (isLoading) {
    return <LoadingSkeleton variant="grid" count={3} />;
  }

  if (error && (!items || items.length === 0)) {
    return <ErrorState message={t('loadError')} onRetry={() => refetch()} />;
  }

  if (!items || items.length === 0) {
    return (
      <EmptyState
        title={t('empty')}
        cta={{ label: t('exploreTours'), href: `/${locale}/search` }}
      />
    );
  }

  return (
    <div data-testid="wishlist-grid">
      {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{t('removeError')}</p>}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => (
          <article key={item.id} className="overflow-hidden rounded-lg border border-gray-200 bg-white">
            {item.tour.cover_image && (
              <Image src={item.tour.cover_image} alt={item.tour.name} width={420} height={260} className="h-44 w-full object-cover" />
            )}
            <div className="p-4">
              <div className="flex items-start justify-between gap-3">
                <Link href={`/${locale}/tours/${item.tour.slug}`} className="font-semibold text-[#0A2540] hover:underline">
                  {item.tour.name}
                </Link>
                <button
                  onClick={() => removeItem(item.tour.id)}
                  className="rounded-full border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-600 hover:border-red-300 hover:text-red-700"
                  aria-label={t('removeAria', { tour: item.tour.name })}
                >
                  {t('remove')}
                </button>
              </div>
              <p className="mt-1 text-sm text-gray-600">{item.tour.location} - {item.tour.duration}</p>
              <p className="mt-3 text-sm text-gray-700">{t('rating', { rating: item.tour.rating.toFixed(1), count: item.tour.review_count })}</p>
              <p className="mt-1 font-semibold text-gray-900">{formatPrice(item.tour.price)}</p>
              {!item.tour.is_available && <p className="mt-2 text-sm font-medium text-red-700">{t('unavailable')}</p>}
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}

function formatPrice(price: number | { amount?: number; formatted?: string }) {
  if (typeof price === 'object' && price.formatted) return price.formatted;
  const amount = typeof price === 'object' ? price.amount : price;
  if (typeof amount !== 'number') return '';
  return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(amount / 100);
}

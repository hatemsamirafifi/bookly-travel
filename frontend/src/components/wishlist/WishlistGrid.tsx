'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { getTravelerWishlist, removeTravelerWishlistItem } from '@/lib/api/traveler';
import type { WishlistItem } from '@/types/traveler';

export default function WishlistGrid({ locale }: { locale: string }) {
  const t = useTranslations('traveler.wishlist');
  const [items, setItems] = useState<WishlistItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getTravelerWishlist()
      .then((res) => setItems(res.data))
      .catch(() => setError(t('loadError')))
      .finally(() => setLoading(false));
  }, [t]);

  const remove = async (tourId: string | number) => {
    const previous = items;
    setItems((current) => current.filter((item) => item.tour.id !== tourId));
    try {
      await removeTravelerWishlistItem(tourId);
    } catch {
      setItems(previous);
      setError(t('removeError'));
    }
  };

  if (loading) {
    return <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{[1, 2, 3].map((i) => <div key={i} className="h-72 animate-pulse rounded-lg bg-gray-100" />)}</div>;
  }

  if (error && items.length === 0) {
    return <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</p>;
  }

  if (items.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-gray-300 bg-white py-12 text-center">
        <p className="text-gray-600">{t('empty')}</p>
        <Link href={`/${locale}/search`} className="mt-4 inline-flex rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540]">
          {t('exploreTours')}
        </Link>
      </div>
    );
  }

  return (
    <div>
      {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
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
                  onClick={() => remove(item.tour.id)}
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

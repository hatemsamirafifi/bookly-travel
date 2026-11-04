import Link from 'next/link';
import ImageWithFallback from '@/components/ui/ImageWithFallback';
import type { Category } from '@/lib/api/types';
import { useTranslations } from 'next-intl';

interface CategoryGridProps {
  categories: Category[];
  locale: string;
  title?: string;
}

export default function CategoryGrid({ categories, locale, title }: CategoryGridProps) {
  const t = useTranslations('home');
  if (!categories || categories.length === 0) return null;
  const sectionTitle = title ?? t('popularCategories');

  return (
    <section className="bg-surface py-12 border-b border-border/40" aria-label={sectionTitle}>
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 className="mb-6 text-xl font-bold text-bookly-navy sm:text-2xl">{sectionTitle}</h2>

        <div className="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          {categories.map((cat) => (
            <Link
              key={cat.slug}
              href={`/${locale}/search?category=${encodeURIComponent(cat.slug)}`}
              className="group relative overflow-hidden rounded-xl border border-border bg-surface-alt shadow-sm transition-all hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            >
              <div className="relative aspect-[4/3] bg-surface-alt">
                {cat.image_url ? (
                  <ImageWithFallback
                    src={cat.image_url}
                    alt={cat.name}
                    fill
                    sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                    className="object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                ) : (
                  <div className="flex h-full flex-col items-center justify-center p-3 text-center text-text-muted">
                    <svg className="mb-1 h-6 w-6 text-text-muted/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <span className="text-xs">{t('noImageAvailable')}</span>
                  </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-3 text-white">
                  <h3 className="text-sm font-semibold leading-snug">{cat.name}</h3>
                  <p className="text-xs text-gray-200 mt-0.5">
                    {t(cat.tour_count === 1 ? 'tourCount' : 'tourCount_plural', { count: cat.tour_count })}
                  </p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}

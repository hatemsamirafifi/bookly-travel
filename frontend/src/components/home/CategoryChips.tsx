import Link from 'next/link';
import type { Category } from '@/lib/api/types';

interface CategoryChipsProps {
  categories: Category[];
  locale: string;
}

export default function CategoryChips({ categories, locale }: CategoryChipsProps) {
  if (!categories || categories.length === 0) return null;

  return (
    <section className="bg-surface py-4 border-b border-border/50" aria-label="Tour Categories">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex gap-2.5 overflow-x-auto pb-1 -mx-4 px-4 snap-x scrollbar-hide text-sm">
          {categories.map((cat) => (
            <Link
              key={cat.slug}
              href={`/${locale}/search?category=${encodeURIComponent(cat.slug)}`}
              className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border bg-surface-alt px-4 py-2 font-medium text-bookly-navy transition-colors hover:border-bookly-navy hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            >
              <span>{cat.name}</span>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}


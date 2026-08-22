import { BlogArticleHeroSkeleton } from './BlogArticleHeroSkeleton';
import { BlogArticleBodySkeleton } from './BlogArticleBodySkeleton';

export function BlogDetailSkeleton() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 animate-pulse" aria-busy="true" aria-label="Loading article">
      <BlogArticleHeroSkeleton />
      <div className="mt-8">
        <BlogArticleBodySkeleton />
      </div>
      <div className="mt-12 p-6 rounded-2xl bg-neutral-100 flex items-center gap-4">
        <div className="h-16 w-16 rounded-full bg-neutral-200" />
        <div className="flex-1 space-y-2">
          <div className="h-5 bg-neutral-200 rounded w-1/3" />
          <div className="h-4 bg-neutral-200 rounded w-2/3" />
        </div>
      </div>
    </div>
  );
}

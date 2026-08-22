export function BlogListSkeleton() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 animate-pulse" aria-busy="true" aria-label="Loading blog posts">
      {/* Featured hero skeleton */}
      <div className="h-96 w-full rounded-2xl bg-neutral-200 mb-12" />

      {/* Category bar skeleton */}
      <div className="flex gap-3 mb-8 overflow-x-auto pb-2">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="h-10 w-28 rounded-full bg-neutral-200 shrink-0" />
        ))}
      </div>

      {/* Grid skeleton */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="flex flex-col rounded-2xl overflow-hidden border border-neutral-100 shadow-sm">
            <div className="h-48 bg-neutral-200" />
            <div className="p-6 space-y-3">
              <div className="h-4 bg-neutral-200 rounded w-1/4" />
              <div className="h-6 bg-neutral-200 rounded w-3/4" />
              <div className="h-4 bg-neutral-200 rounded w-full" />
              <div className="h-4 bg-neutral-200 rounded w-5/6" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

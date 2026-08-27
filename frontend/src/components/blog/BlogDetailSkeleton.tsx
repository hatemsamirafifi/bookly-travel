export function BlogDetailSkeleton() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 animate-pulse" aria-busy="true" aria-label="Loading article">
      {/* Hero skeleton */}
      <div className="mb-8">
        <div className="h-4 bg-neutral-200 rounded w-24 mb-4" />
        <div className="h-12 bg-neutral-200 rounded w-3/4 mb-4" />
        <div className="h-4 bg-neutral-200 rounded w-48" />
      </div>
      {/* Cover image skeleton */}
      <div className="relative mb-10 aspect-[16/9] w-full overflow-hidden rounded-2xl bg-neutral-200" />
      {/* Body skeleton */}
      <div className="mt-8 space-y-4">
        <div className="h-4 bg-neutral-200 rounded w-full" />
        <div className="h-4 bg-neutral-200 rounded w-full" />
        <div className="h-4 bg-neutral-200 rounded w-5/6" />
        <div className="h-4 bg-neutral-200 rounded w-full" />
        <div className="h-4 bg-neutral-200 rounded w-3/4" />
        <div className="h-4 bg-neutral-200 rounded w-full" />
        <div className="h-4 bg-neutral-200 rounded w-2/3" />
      </div>
      {/* Author bio skeleton */}
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
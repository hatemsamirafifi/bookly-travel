interface LoadingSkeletonProps {
  count?: number;
  variant?: 'card' | 'list' | 'detail' | 'grid';
}

export default function LoadingSkeleton({ count = 3, variant = 'card' }: LoadingSkeletonProps) {
  if (variant === 'grid') {
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: count }).map((_, i) => (
          <div key={i} className="h-72 animate-pulse rounded-lg bg-gray-100" />
        ))}
      </div>
    );
  }

  if (variant === 'detail') {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 w-48 rounded bg-gray-100" />
        <div className="h-40 rounded-lg bg-gray-100" />
        <div className="h-40 rounded-lg bg-gray-100" />
      </div>
    );
  }

  if (variant === 'list') {
    return (
      <div className="space-y-3">
        {Array.from({ length: count }).map((_, i) => (
          <div key={i} className="h-32 animate-pulse rounded-lg bg-gray-100" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="animate-pulse rounded-lg border border-gray-200 p-4">
          <div className="flex gap-4">
            <div className="h-20 w-20 rounded-lg bg-gray-100" />
            <div className="flex-1 space-y-2">
              <div className="h-5 w-48 rounded bg-gray-100" />
              <div className="h-4 w-32 rounded bg-gray-100" />
              <div className="h-4 w-24 rounded bg-gray-100" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

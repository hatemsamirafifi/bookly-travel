interface LoadingSkeletonProps {
  count?: number;
  variant?: 'card' | 'list' | 'detail' | 'grid';
  label?: string;
}

function SkeletonShapes({ count = 3, variant = 'card' }: LoadingSkeletonProps) {
  if (variant === 'grid') {
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: count }).map((_, i) => (
          <div key={i} className="h-72 animate-pulse motion-reduce:animate-none rounded-lg bg-border" />
        ))}
      </div>
    );
  }

  if (variant === 'detail') {
    return (
      <div className="animate-pulse motion-reduce:animate-none space-y-4">
        <div className="h-8 w-48 rounded bg-border" />
        <div className="h-40 rounded-lg bg-border" />
        <div className="h-40 rounded-lg bg-border" />
      </div>
    );
  }

  if (variant === 'list') {
    return (
      <div className="space-y-3">
        {Array.from({ length: count }).map((_, i) => (
          <div key={i} className="h-32 animate-pulse motion-reduce:animate-none rounded-lg bg-border" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="animate-pulse motion-reduce:animate-none rounded-lg border border-border p-4">
          <div className="flex gap-4">
            <div className="h-20 w-20 rounded-lg bg-border" />
            <div className="flex-1 space-y-2">
              <div className="h-5 w-48 rounded bg-border" />
              <div className="h-4 w-32 rounded bg-border" />
              <div className="h-4 w-24 rounded bg-border" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

export default function LoadingSkeleton({ label, ...props }: LoadingSkeletonProps) {
  return (
    <div role={label ? 'status' : undefined} aria-busy="true">
      {label && <span className="sr-only">{label}</span>}
      <div aria-hidden="true"><SkeletonShapes {...props} /></div>
    </div>
  );
}

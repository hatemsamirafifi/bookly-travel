export function SkeletonText({ lines = 1, className = '' }: { lines?: number; className?: string }) {
  return (
    <div className={`space-y-2 ${className}`}>
      {Array.from({ length: lines }).map((_, i) => (
        <div key={i} className="h-4 animate-pulse rounded bg-[#F7F9FB]" />
      ))}
    </div>
  );
}

export function SkeletonCard({ className = '' }: { className?: string }) {
  return (
    <div className={`space-y-3 ${className}`}>
      <div className="aspect-[4/3] animate-pulse rounded-lg bg-[#F7F9FB]" />
      <div className="h-5 w-3/4 animate-pulse rounded bg-[#F7F9FB]" />
      <div className="h-4 w-1/2 animate-pulse rounded bg-[#F7F9FB]" />
    </div>
  );
}

export function SkeletonTourCard() {
  return (
    <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-3">
      <div className="aspect-[4/3] animate-pulse rounded-lg bg-[#F7F9FB]" />
      <div className="h-5 w-3/4 animate-pulse rounded bg-[#F7F9FB]" />
      <div className="flex items-center gap-2">
        <div className="h-4 w-20 animate-pulse rounded bg-[#F7F9FB]" />
        <div className="h-4 w-16 animate-pulse rounded bg-[#F7F9FB]" />
      </div>
      <div className="h-6 w-24 animate-pulse rounded bg-[#F7F9FB]" />
    </div>
  );
}

export function SkeletonTourGrid({ count = 6 }: { count?: number }) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: count }).map((_, i) => (
        <SkeletonTourCard key={i} />
      ))}
    </div>
  );
}

export function SkeletonTourDetail() {
  return (
    <div className="space-y-6">
      <div className="aspect-[16/9] animate-pulse rounded-xl bg-[#F7F9FB]" />
      <div className="space-y-3">
        <div className="h-8 w-2/3 animate-pulse rounded bg-[#F7F9FB]" />
        <div className="h-4 w-1/3 animate-pulse rounded bg-[#F7F9FB]" />
      </div>
      <div className="space-y-2">
        <div className="h-4 w-full animate-pulse rounded bg-[#F7F9FB]" />
        <div className="h-4 w-5/6 animate-pulse rounded bg-[#F7F9FB]" />
        <div className="h-4 w-4/6 animate-pulse rounded bg-[#F7F9FB]" />
      </div>
    </div>
  );
}

export function SkeletonCheckout() {
  return (
    <div className="space-y-6">
      <div className="h-16 animate-pulse rounded-lg bg-[#F7F9FB]" />
      <div className="h-32 animate-pulse rounded-lg bg-[#F7F9FB]" />
      <div className="h-24 animate-pulse rounded-lg bg-[#F7F9FB]" />
    </div>
  );
}

'use client';

export function PartnerAnalyticsSkeleton() {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <div className="flex items-center justify-between">
              <div className="h-4 w-24 rounded bg-gray-100 animate-pulse" />
              <div className="h-8 w-8 rounded-lg bg-gray-100 animate-pulse" />
            </div>
            <div className="h-8 w-20 rounded bg-gray-100 animate-pulse" />
          </div>
        ))}
      </div>
      <div className="bg-white rounded-xl border border-gray-200 p-5">
        <div className="h-5 w-40 rounded bg-gray-100 animate-pulse mb-4" />
        <div className="h-64 rounded bg-gray-100 animate-pulse" />
      </div>
    </div>
  );
}

export function PartnerTourListSkeleton() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="h-6 w-32 rounded bg-gray-100 animate-pulse" />
        <div className="h-9 w-28 rounded bg-gray-100 animate-pulse" />
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="h-40 bg-gray-100 animate-pulse" />
            <div className="p-4 space-y-3">
              <div className="h-5 w-3/4 rounded bg-gray-100 animate-pulse" />
              <div className="flex items-center gap-4">
                <div className="h-4 w-20 rounded bg-gray-100 animate-pulse" />
                <div className="h-4 w-20 rounded bg-gray-100 animate-pulse" />
              </div>
              <div className="flex items-center justify-between pt-2">
                <div className="h-5 w-16 rounded bg-gray-100 animate-pulse" />
                <div className="flex items-center gap-2">
                  <div className="h-8 w-8 rounded bg-gray-100 animate-pulse" />
                  <div className="h-8 w-8 rounded bg-gray-100 animate-pulse" />
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export function PartnerBookingListSkeleton() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              {Array.from({ length: 6 }).map((_, i) => (
                <th key={i} className="px-4 py-3">
                  <div className="h-4 w-20 rounded bg-gray-100 animate-pulse" />
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {Array.from({ length: 5 }).map((_, i) => (
              <tr key={i}>
                {Array.from({ length: 6 }).map((_, j) => (
                  <td key={j} className="px-4 py-3">
                    <div className="h-4 w-full max-w-[8rem] rounded bg-gray-100 animate-pulse" />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function PartnerReviewListSkeleton() {
  return (
    <div className="space-y-4">
      {Array.from({ length: 3 }).map((_, i) => (
        <div key={i} className="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
          <div className="flex items-start justify-between">
            <div className="space-y-2">
              <div className="h-4 w-32 rounded bg-gray-100 animate-pulse" />
              <div className="h-4 w-24 rounded bg-gray-100 animate-pulse" />
            </div>
            <div className="h-4 w-20 rounded bg-gray-100 animate-pulse" />
          </div>
          <div className="h-4 w-full rounded bg-gray-100 animate-pulse" />
          <div className="h-4 w-2/3 rounded bg-gray-100 animate-pulse" />
        </div>
      ))}
    </div>
  );
}

export function PartnerProfileSkeleton() {
  return (
    <div className="space-y-6">
      <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div className="h-6 w-40 rounded bg-gray-100 animate-pulse" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="space-y-2">
              <div className="h-4 w-24 rounded bg-gray-100 animate-pulse" />
              <div className="h-10 w-full rounded bg-gray-100 animate-pulse" />
            </div>
          ))}
        </div>
      </div>
      <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div className="h-6 w-48 rounded bg-gray-100 animate-pulse" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="space-y-2">
              <div className="h-4 w-24 rounded bg-gray-100 animate-pulse" />
              <div className="h-10 w-full rounded bg-gray-100 animate-pulse" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

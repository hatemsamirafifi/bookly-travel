'use client';

import { useRouter, useSearchParams, usePathname } from 'next/navigation';

interface PaginationProps {
  currentPage: number;
  lastPage: number;
}

export default function Pagination({ currentPage, lastPage }: PaginationProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pathname = usePathname();

  if (lastPage <= 1) return null;

  const goToPage = (page: number) => {
    const sp = new URLSearchParams(searchParams.toString());
    sp.set('page', String(page));
    // Stay on the current listing page (search, category, or destination)
    // rather than always jumping to /search (F3).
    router.push(`${pathname}?${sp.toString()}`);
  };

  const isFirst = currentPage <= 1;
  const isLast = currentPage >= lastPage;

  return (
    <nav className="flex items-center justify-center gap-3 py-8" aria-label="Search results pagination">
      <button
        onClick={() => goToPage(currentPage - 1)}
        disabled={isFirst}
        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white"
        aria-label="Previous page"
      >
        Previous
      </button>

      <span className="text-sm text-gray-600">
        Page {currentPage} of {lastPage}
      </span>

      <button
        onClick={() => goToPage(currentPage + 1)}
        disabled={isLast}
        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white"
        aria-label="Next page"
      >
        Next
      </button>
    </nav>
  );
}

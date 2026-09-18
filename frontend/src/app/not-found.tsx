import Link from 'next/link';

// Root fallback 404: rendered for unmatched routes outside any `[locale]`
// segment (e.g. `/completely-invalid-page`). Intentionally locale-agnostic
// plain markup — it must never throw, so it avoids next-intl lookups and
// links into the default locale.
export default function RootNotFound() {
  return (
    <main className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
      <h1 className="text-6xl font-extrabold text-[#0A2540]">404</h1>
      <p className="mt-4 text-xl font-semibold text-[#0A2540]">Page Not Found</p>
      <p className="mt-2 text-[#5A6B7B]">
        The page you are looking for does not exist or has been moved.
      </p>
      <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
        <Link
          href="/en"
          className="rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600] transition-colors"
        >
          Go Home
        </Link>
        <Link
          href="/en/search"
          className="rounded-xl bg-[#F7F9FB] px-5 py-2.5 text-sm font-semibold text-[#0A2540] hover:bg-gray-200 transition-colors"
        >
          Browse Tours
        </Link>
      </div>
    </main>
  );
}

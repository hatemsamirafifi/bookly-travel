import Link from 'next/link';
import LocaleSwitcher from './LocaleSwitcher';

interface HeaderProps {
  locale: string;
}

export default function Header({ locale }: HeaderProps) {
  return (
    <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur-sm">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <Link href={`/${locale}`} className="text-xl font-bold text-blue-600">
          Bookly
        </Link>

        <nav className="hidden sm:flex items-center gap-6" aria-label="Main navigation">
          <Link href={`/${locale}`} className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
            Home
          </Link>
          <Link href={`/${locale}/categories`} className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
            Categories
          </Link>
          <Link href={`/${locale}/destinations`} className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
            Destinations
          </Link>
        </nav>

        <div className="flex items-center gap-3">
          <LocaleSwitcher />
          {/* Mobile hamburger placeholder */}
          <button
            className="sm:hidden rounded-md p-2 text-gray-600 hover:bg-gray-100"
            aria-label="Open menu"
            aria-expanded="false"
          >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </header>
  );
}

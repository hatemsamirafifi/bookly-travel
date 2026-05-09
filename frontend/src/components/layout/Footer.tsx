import Link from 'next/link';

interface FooterProps {
  locale: string;
}

export default function Footer({ locale }: FooterProps) {
  return (
    <footer className="bg-gray-900 text-gray-300">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-3">
          <div>
            <h3 className="mb-3 text-lg font-semibold text-white">Bookly</h3>
            <p className="text-sm text-gray-400">
              Discover and instantly book the best tours worldwide. Your next adventure awaits.
            </p>
          </div>

          <div>
            <h3 className="mb-3 text-sm font-semibold text-white">Explore</h3>
            <ul className="space-y-2 text-sm">
              <li><Link href={`/${locale}/search`} className="hover:text-white transition-colors">Search Tours</Link></li>
              <li><Link href={`/${locale}/categories`} className="hover:text-white transition-colors">Categories</Link></li>
              <li><Link href={`/${locale}/destinations`} className="hover:text-white transition-colors">Destinations</Link></li>
            </ul>
          </div>

          <div>
            <h3 className="mb-3 text-sm font-semibold text-white">Company</h3>
            <ul className="space-y-2 text-sm">
              <li><a href="#" className="hover:text-white transition-colors">About Us</a></li>
              <li><a href="#" className="hover:text-white transition-colors">Privacy Policy</a></li>
              <li><a href="#" className="hover:text-white transition-colors">Terms of Service</a></li>
            </ul>
          </div>
        </div>

        <div className="mt-8 border-t border-gray-700 pt-8 text-center text-sm text-gray-500">
          &copy; {new Date().getFullYear()} Bookly. All rights reserved.
        </div>
      </div>
    </footer>
  );
}

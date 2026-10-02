import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import LocaleSwitcher from './LocaleSwitcher';
import { Container } from '@/components/ui/Container';

interface FooterProps {
  locale: string;
}

export default async function Footer({ locale }: FooterProps) {
  const t = await getTranslations({ locale, namespace: 'footer' });
  const nav = await getTranslations({ locale, namespace: 'nav' });
  return (
    <footer className="bg-primary text-text-on-dark">
      <Container className="py-section">
        <div className="grid grid-cols-1 gap-8 sm:grid-cols-3">
          <div>
            <p className="mb-3 text-lg font-semibold text-text-inverse">Bookly</p>
            <p className="text-sm">{t('tagline')}</p>
          </div>

          <div>
            <p className="mb-3 text-sm font-semibold text-white">{t('explore')}</p>
            <ul className="space-y-2 text-sm">
              <li><Link href={`/${locale}/search`} className="hover:text-white transition-colors">{t('searchTours')}</Link></li>
              <li><Link href={`/${locale}/categories`} className="hover:text-white transition-colors">{nav('categories')}</Link></li>
              <li><Link href={`/${locale}/destinations`} className="hover:text-white transition-colors">{nav('destinations')}</Link></li>
            </ul>
          </div>

          <div>
            <p className="mb-3 text-sm font-semibold text-white">{t('company')}</p>
            <ul className="space-y-2 text-sm">
              <li><Link href={`/${locale}/privacy`} className="hover:text-white transition-colors">{t('privacy')}</Link></li>
              <li><Link href={`/${locale}/terms`} className="hover:text-white transition-colors">{t('terms')}</Link></li>
            </ul>
          </div>
        </div>

        <div className="mt-8 flex flex-col items-center justify-between gap-4 border-t border-text-on-dark/20 pt-8 text-sm sm:flex-row">
          <span>&copy; {new Date().getFullYear()} Bookly. {t('rights')}</span>
          <LocaleSwitcher />
        </div>
      </Container>
    </footer>
  );
}

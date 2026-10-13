import Link from 'next/link';
import Image from 'next/image';
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
    <footer className="border-t border-border bg-surface-alt text-text-muted">
      <Container className="py-section">
        <div className="grid grid-cols-1 gap-8 md:grid-cols-[2fr_1fr_1fr]">
          <div>
            <div className="w-full max-w-80">
              <Image
                src="/brand/bookly-travel-slogan.png"
                alt="Bookly Travel — Book smarter. Travel better."
                width={1453}
                height={427}
                className="h-auto w-full"
                unoptimized
              />
            </div>
          </div>

          <div>
            <p className="mb-3 text-sm font-semibold text-primary">{t('explore')}</p>
            <ul className="space-y-2 text-sm">
              <li><Link href={`/${locale}/search`} className="hover:text-primary transition-colors">{t('searchTours')}</Link></li>
              <li><Link href={`/${locale}/categories`} className="hover:text-primary transition-colors">{nav('categories')}</Link></li>
              <li><Link href={`/${locale}/destinations`} className="hover:text-primary transition-colors">{nav('destinations')}</Link></li>
            </ul>
          </div>

          <div>
            <p className="mb-3 text-sm font-semibold text-primary">{t('company')}</p>
            <ul className="space-y-2 text-sm">
              <li><Link href={`/${locale}/privacy`} className="hover:text-primary transition-colors">{t('privacy')}</Link></li>
              <li><Link href={`/${locale}/terms`} className="hover:text-primary transition-colors">{t('terms')}</Link></li>
            </ul>
          </div>
        </div>

        <div className="mt-8 flex flex-col items-center justify-between gap-4 border-t border-border pt-8 text-sm sm:flex-row">
          <span>&copy; {new Date().getFullYear()} Bookly. {t('rights')}</span>
          <LocaleSwitcher />
        </div>
      </Container>
    </footer>
  );
}

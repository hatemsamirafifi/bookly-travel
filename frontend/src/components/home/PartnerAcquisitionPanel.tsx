import Link from 'next/link';
import { useTranslations } from 'next-intl';

interface PartnerAcquisitionPanelProps {
  locale: string;
}

export default function PartnerAcquisitionPanel({ locale }: PartnerAcquisitionPanelProps) {
  const t = useTranslations('home');

  return (
    <section className="border-t border-border bg-surface-alt py-12" aria-labelledby="home-partner-title">
      <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div className="max-w-2xl">
          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">
            {t('partnerEyebrow')}
          </p>
          <h2 id="home-partner-title" className="text-2xl font-bold text-bookly-navy">
            {t('partnerTitle')}
          </h2>
          <p className="mt-2 text-text-muted">
            {t('partnerDescription')}
          </p>
        </div>
        <Link
          href={`/${locale}/partner-register`}
          className="inline-flex min-h-11 items-center justify-center self-start rounded-xl border border-bookly-navy px-5 py-3 text-sm font-semibold text-bookly-navy transition-colors hover:bg-bookly-navy hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        >
          {t('partnerAction')}
        </Link>
      </div>
    </section>
  );
}


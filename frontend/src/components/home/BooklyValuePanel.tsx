import { ShieldCheck, Zap, Headphones } from 'lucide-react';

interface BooklyValuePanelProps {
  locale?: string;
  labels?: {
    title?: string;
    verifiedTitle?: string;
    verifiedDesc?: string;
    confirmationTitle?: string;
    confirmationDesc?: string;
    supportTitle?: string;
    supportDesc?: string;
  };
}

export default function BooklyValuePanel({ labels }: BooklyValuePanelProps) {
  const valuePillars = [
    {
      icon: ShieldCheck,
      title: labels?.verifiedTitle || 'Verified Local Guides',
      description: labels?.verifiedDesc || 'Authentic tours led by certified local experts.',
    },
    {
      icon: Zap,
      title: labels?.confirmationTitle || 'Secure Instant Booking',
      description: labels?.confirmationDesc || 'Instant confirmation with full buyer protection.',
    },
    {
      icon: Headphones,
      title: labels?.supportTitle || '24/7 Traveler Care',
      description: labels?.supportDesc || 'Dedicated support team ready to assist anytime.',
    },
  ];

  return (
    <section
      className="border-b border-border/60 bg-surface py-10"
      aria-labelledby="value-panel-heading"
      role="region"
    >
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 id="value-panel-heading" className="sr-only">
          {labels?.title || 'Bookly Marketplace Guarantees'}
        </h2>
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {valuePillars.map((pillar, idx) => {
            const Icon = pillar.icon;
            return (
              <div
                key={idx}
                className="flex items-start gap-4 rounded-xl border border-border/80 bg-surface-alt p-5 shadow-xs transition duration-200 hover:shadow-sm"
              >
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-bookly-navy/10 text-bookly-navy">
                  <Icon className="h-6 w-6 text-bookly-navy" aria-hidden="true" />
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-bookly-navy">{pillar.title}</h3>
                  <p className="mt-1 text-xs leading-relaxed text-text-muted">{pillar.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

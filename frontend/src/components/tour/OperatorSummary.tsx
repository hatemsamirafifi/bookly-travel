import type { PublicOperatorSummary } from '@/lib/api/types';

interface OperatorSummaryProps {
  operator: PublicOperatorSummary;
  title: string;
  tourCountLabel: string;
  reviewCountLabel: string;
  /** Localized verified copy; the badge renders only when this label is
   *  provided AND operator.verified === true (approved + active profile). */
  verifiedLabel?: string;
}

export default function OperatorSummary({ operator, title, tourCountLabel, reviewCountLabel, verifiedLabel }: OperatorSummaryProps) {
  return (
    <section id="operator" className="scroll-mt-20 rounded-xl border border-border bg-surface p-5" aria-labelledby="operator-title">
      <h2 id="operator-title" className="text-xl font-semibold text-bookly-navy">{title}</h2>
      <p className="mt-3 text-lg font-semibold text-bookly-navy">{operator.name}</p>
      {operator.verified === true && verifiedLabel ? (
        <p className="mt-1"><span data-testid="operator-verified" className="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{verifiedLabel}</span></p>
      ) : null}
      {operator.description && <p className="mt-2 whitespace-pre-line text-text-muted">{operator.description}</p>}
      <div className="mt-4 flex flex-wrap gap-3 text-sm text-text-muted">
        <span>{tourCountLabel}</span>
        {operator.review_count > 0 && <span>{reviewCountLabel}</span>}
      </div>
    </section>
  );
}

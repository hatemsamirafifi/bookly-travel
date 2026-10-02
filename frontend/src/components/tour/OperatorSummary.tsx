import type { PublicOperatorSummary } from '@/lib/api/types';

interface OperatorSummaryProps {
  operator: PublicOperatorSummary;
  title: string;
  tourCountLabel: string;
  reviewCountLabel: string;
}

export default function OperatorSummary({ operator, title, tourCountLabel, reviewCountLabel }: OperatorSummaryProps) {
  return (
    <section id="operator" className="scroll-mt-20 rounded-xl border border-border bg-surface p-5" aria-labelledby="operator-title">
      <h2 id="operator-title" className="text-xl font-semibold text-bookly-navy">{title}</h2>
      <p className="mt-3 text-lg font-semibold text-bookly-navy">{operator.name}</p>
      {operator.description && <p className="mt-2 whitespace-pre-line text-text-muted">{operator.description}</p>}
      <div className="mt-4 flex flex-wrap gap-3 text-sm text-text-muted">
        <span>{tourCountLabel}</span>
        {operator.review_count > 0 && <span>{reviewCountLabel}</span>}
      </div>
    </section>
  );
}

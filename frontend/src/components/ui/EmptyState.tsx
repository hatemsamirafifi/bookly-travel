import Link from 'next/link';
import type { ReactNode } from 'react';

interface EmptyStateProps {
  title: string;
  description?: string;
  icon?: ReactNode;
  cta?: {
    label: string;
    href: string;
  };
}

export default function EmptyState({ title, description, icon, cta }: EmptyStateProps) {
  return (
    <div className="rounded-lg border border-dashed border-border bg-surface px-card py-section text-center">
      {icon && <div aria-hidden="true" className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-alt text-text-muted">{icon}</div>}
      <p className="font-semibold text-primary">{title}</p>
      {description && <p className="mt-1 text-sm text-text-muted">{description}</p>}
      {cta && (
        <Link
          href={cta.href}
          className="mt-4 inline-flex min-h-11 items-center rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-primary hover:bg-accent-dark"
        >
          {cta.label}
        </Link>
      )}
    </div>
  );
}

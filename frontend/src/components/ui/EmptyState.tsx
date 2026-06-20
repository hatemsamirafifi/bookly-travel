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
    <div className="rounded-lg border border-dashed border-gray-300 bg-white py-12 text-center">
      {icon && <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500">{icon}</div>}
      <p className="text-gray-600">{title}</p>
      {description && <p className="mt-1 text-sm text-gray-500">{description}</p>}
      {cta && (
        <Link
          href={cta.href}
          className="mt-4 inline-flex rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540]"
        >
          {cta.label}
        </Link>
      )}
    </div>
  );
}

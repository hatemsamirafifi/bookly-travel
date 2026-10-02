import type { ReactNode } from 'react';

export function SectionHeading({ title, description, action, id, level = 2 }: { title: string; description?: string; action?: ReactNode; id?: string; level?: 1 | 2 | 3 }) {
  const Heading = `h${level}` as 'h1' | 'h2' | 'h3';
  return <div className="flex flex-wrap items-end justify-between gap-4"><div><Heading id={id} className="text-section-title font-bold text-primary">{title}</Heading>{description && <p className="mt-2 text-text-muted">{description}</p>}</div>{action}</div>;
}

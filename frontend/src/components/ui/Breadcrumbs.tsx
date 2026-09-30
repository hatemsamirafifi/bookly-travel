import Link from 'next/link';

export function Breadcrumbs({ label, items }: { label: string; items: { label: string; href?: string }[] }) {
  return <nav aria-label={label}><ol className="flex flex-wrap items-center gap-2 text-sm text-text-muted">{items.map((item, index) => <li key={`${item.label}-${index}`} className="flex items-center gap-2">{index > 0 && <span aria-hidden="true">/</span>}{item.href && index < items.length - 1 ? <Link className="inline-flex min-h-11 items-center hover:text-primary" href={item.href}>{item.label}</Link> : <span aria-current={index === items.length - 1 ? 'page' : undefined}>{item.label}</span>}</li>)}</ol></nav>;
}

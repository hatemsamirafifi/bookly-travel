'use client';

import { useId, useRef, useState, type ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';

export function Accordion({ items }: { items: { id: string; title: string; content: ReactNode }[] }) {
  const prefix = useId();
  const [expanded, setExpanded] = useState<string[]>([]);
  const controls = useRef<(HTMLButtonElement | null)[]>([]);
  return <div className="divide-y divide-border rounded-lg border border-border bg-surface">{items.map((item, index) => {
    const open = expanded.includes(item.id);
    const id = `${prefix}-${item.id}`;
    return <div key={item.id}><h3><button type="button" ref={(node) => { controls.current[index] = node; }} id={`${id}-trigger`} aria-expanded={open} aria-controls={`${id}-panel`} className="flex min-h-11 w-full items-center justify-between gap-4 p-4 text-left font-semibold text-primary focus-visible:ring-2 focus-visible:ring-focus" onClick={() => setExpanded((current) => open ? current.filter((value) => value !== item.id) : [...current, item.id])} onKeyDown={(event) => {
      const next = event.key === 'ArrowDown' ? (index + 1) % items.length : event.key === 'ArrowUp' ? (index - 1 + items.length) % items.length : event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : undefined;
      if (next !== undefined) { event.preventDefault(); controls.current[next]?.focus(); }
    }}>{item.title}<ChevronDown aria-hidden="true" className={`size-5 transition-transform motion-reduce:transition-none ${open ? 'rotate-180' : ''}`} /></button></h3><div id={`${id}-panel`} role="region" aria-labelledby={`${id}-trigger`} hidden={!open} className="px-4 pb-4 text-text-muted">{item.content}</div></div>;
  })}</div>;
}

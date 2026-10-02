'use client';

import { useId, useRef, useState, type ReactNode } from 'react';

export function Tabs({ label, items, defaultTab }: { label: string; items: { id: string; label: string; content: ReactNode; disabled?: boolean }[]; defaultTab?: string }) {
  const prefix = useId();
  const [selected, setSelected] = useState(defaultTab);
  const active = items.find((item) => item.id === selected && !item.disabled)?.id ?? items.find((item) => !item.disabled)?.id;
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  return <div><div role="tablist" aria-label={label} className="flex overflow-x-auto border-b border-border">{items.map((item, index) => <button type="button" key={item.id} ref={(node) => { refs.current[index] = node; }} role="tab" id={`${prefix}-${item.id}-tab`} aria-controls={`${prefix}-${item.id}-panel`} aria-selected={item.id === active} disabled={item.disabled} tabIndex={item.id === active ? 0 : -1} className={`min-h-11 shrink-0 border-b-2 px-4 py-3 font-semibold focus-visible:ring-2 focus-visible:ring-focus disabled:opacity-50 ${item.id === active ? 'border-primary text-primary' : 'border-transparent text-text-muted'}`} onClick={() => setSelected(item.id)} onKeyDown={(event) => {
    const enabled = items.map((entry, i) => entry.disabled ? -1 : i).filter((i) => i !== -1);
    const position = enabled.indexOf(index);
    const next = event.key === 'ArrowRight' ? enabled[(position + 1) % enabled.length] : event.key === 'ArrowLeft' ? enabled[(position - 1 + enabled.length) % enabled.length] : event.key === 'Home' ? enabled[0] : event.key === 'End' ? enabled[enabled.length - 1] : undefined;
    if (next !== undefined) { event.preventDefault(); setSelected(items[next].id); refs.current[next]?.focus(); }
  }}>{item.label}</button>)}</div>{items.map((item) => <div key={item.id} role="tabpanel" id={`${prefix}-${item.id}-panel`} aria-labelledby={`${prefix}-${item.id}-tab`} hidden={item.id !== active} tabIndex={0} className="py-4 focus-visible:ring-2 focus-visible:ring-focus">{item.content}</div>)}</div>;
}

'use client';

import * as React from 'react';
import { cn } from '@/lib/utils';

interface SelectContextValue {
  value: string;
  onChange: (value: string) => void;
  open: boolean;
  setOpen: (open: boolean) => void;
  disabled?: boolean;
  triggerRef: React.RefObject<HTMLButtonElement | null>;
  triggerId: string;
  listboxId: string;
}

const SelectContext = React.createContext<SelectContextValue | null>(null);

function useSelect() {
  const ctx = React.useContext(SelectContext);
  if (!ctx) throw new Error('Select components must be used within <Select>');
  return ctx;
}

function Select({
  value,
  onValueChange,
  children,
  disabled,
}: {
  value: string;
  onValueChange: (v: string) => void;
  children: React.ReactNode;
  disabled?: boolean;
}) {
  const [open, setOpen] = React.useState(false);
  const triggerRef = React.useRef<HTMLButtonElement>(null);
  const id = React.useId();
  return (
    <SelectContext.Provider value={{ value, onChange: onValueChange, open, setOpen, disabled, triggerRef, triggerId: `${id}-trigger`, listboxId: `${id}-listbox` }}>
      <div className="relative" onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setOpen(false); }}>{children}</div>
    </SelectContext.Provider>
  );
}

function SelectTrigger({ children, className, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  const { value, open, setOpen, disabled, triggerRef, triggerId, listboxId } = useSelect();
  return (
    <button
      {...props}
      ref={triggerRef}
      id={triggerId}
      type="button"
      aria-haspopup="listbox"
      aria-expanded={open && !disabled}
      aria-controls={listboxId}
      onClick={() => !disabled && setOpen(!open)}
      disabled={disabled}
      className={cn(
        'flex min-h-11 w-full items-center justify-between rounded-lg border border-border bg-surface px-3 py-2 text-sm text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus:border-transparent',
        disabled && 'opacity-50 cursor-not-allowed bg-surface-alt',
        className
      )}
      onKeyDown={(event) => { if (!disabled && ['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); setOpen(true); } }}
    >
      <span>{children || value}</span>
      <svg aria-hidden="true" className={`w-4 h-4 text-text-muted transition-transform motion-reduce:transition-none ${open ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
      </svg>
    </button>
  );
}

function SelectContent({ children, className }: { children: React.ReactNode; className?: string }) {
  const { open, disabled, setOpen, triggerRef, triggerId, listboxId } = useSelect();
  const contentRef = React.useRef<HTMLDivElement>(null);
  React.useEffect(() => {
    if (open && !disabled) (contentRef.current?.querySelector<HTMLElement>('[aria-selected="true"]') ?? contentRef.current?.querySelector<HTMLElement>('[role="option"]'))?.focus();
  }, [open, disabled]);
  if (!open || disabled) return null;
  return (
    <div ref={contentRef} id={listboxId} role="listbox" aria-labelledby={triggerId} className={cn('absolute z-dropdown mt-1 w-full rounded-lg border border-border bg-surface shadow-dropdown py-1', className)} onKeyDown={(event) => {
      if (event.key === 'Escape') { event.preventDefault(); setOpen(false); triggerRef.current?.focus(); return; }
      const options = Array.from(event.currentTarget.querySelectorAll<HTMLElement>('[role="option"]'));
      const index = options.indexOf(document.activeElement as HTMLElement);
      const next = event.key === 'ArrowDown' ? (index + 1) % options.length : event.key === 'ArrowUp' ? (index - 1 + options.length) % options.length : event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : undefined;
      if (next !== undefined) { event.preventDefault(); options[next]?.focus(); }
    }}>
      {children}
    </div>
  );
}

function SelectItem({ value, children }: { value: string; children: React.ReactNode }) {
  const { value: selected, onChange, setOpen, triggerRef } = useSelect();
  return (
    <button
      type="button"
      role="option"
      aria-selected={selected === value}
      tabIndex={-1}
      onClick={() => { onChange(value); setOpen(false); triggerRef.current?.focus(); }}
      className={cn(
        'min-h-11 w-full px-3 py-2 text-sm text-left hover:bg-surface-alt focus-visible:ring-2 focus-visible:ring-focus',
        selected === value ? 'bg-surface-alt font-medium text-primary' : 'text-primary'
      )}
    >
      {children}
    </button>
  );
}

function SelectValue({ placeholder, displayValue }: { placeholder?: string; displayValue?: string }) {
  const { value } = useSelect();
  return <>{displayValue ?? (value || placeholder)}</>;
}

export { Select, SelectTrigger, SelectContent, SelectItem, SelectValue };

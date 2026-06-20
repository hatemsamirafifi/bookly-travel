'use client';

import * as React from 'react';
import { cn } from '@/lib/utils';

interface SelectContextValue {
  value: string;
  onChange: (value: string) => void;
  open: boolean;
  setOpen: (open: boolean) => void;
  disabled?: boolean;
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
  return (
    <SelectContext.Provider value={{ value, onChange: onValueChange, open, setOpen, disabled }}>
      <div className="relative">{children}</div>
    </SelectContext.Provider>
  );
}

function SelectTrigger({ children, className }: { children: React.ReactNode; className?: string }) {
  const { value, open, setOpen, disabled } = useSelect();
  return (
    <button
      type="button"
      onClick={() => !disabled && setOpen(!open)}
      disabled={disabled}
      className={cn(
        'flex h-10 w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent',
        disabled && 'opacity-50 cursor-not-allowed bg-gray-50',
        className
      )}
    >
      <span>{children || value || 'Select...'}</span>
      <svg className={`w-4 h-4 text-gray-500 transition-transform ${open ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
      </svg>
    </button>
  );
}

function SelectContent({ children, className }: { children: React.ReactNode; className?: string }) {
  const { open } = useSelect();
  if (!open) return null;
  return (
    <div className={cn('absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg py-1', className)}>
      {children}
    </div>
  );
}

function SelectItem({ value, children }: { value: string; children: React.ReactNode }) {
  const { value: selected, onChange, setOpen } = useSelect();
  return (
    <button
      type="button"
      onClick={() => { onChange(value); setOpen(false); }}
      className={cn(
        'w-full px-3 py-2 text-sm text-left hover:bg-gray-50',
        selected === value ? 'bg-gray-50 font-medium text-[#0A2540]' : 'text-gray-700'
      )}
    >
      {children}
    </button>
  );
}

function SelectValue({ placeholder }: { placeholder?: string }) {
  const { value } = useSelect();
  return <>{value || placeholder}</>;
}

export { Select, SelectTrigger, SelectContent, SelectItem, SelectValue };

import type { ButtonHTMLAttributes, HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type ChipProps = (ButtonHTMLAttributes<HTMLButtonElement> & { onClick: NonNullable<ButtonHTMLAttributes<HTMLButtonElement>['onClick']>; selected?: boolean }) | (HTMLAttributes<HTMLSpanElement> & { onClick?: never; selected?: never });

export function Chip({ className, selected, ...props }: ChipProps) {
  const classes = cn('inline-flex items-center rounded-full border border-border px-3 py-1 text-sm', selected ? 'bg-primary text-text-inverse' : 'bg-surface text-primary', className);
  if (props.onClick) return <button type="button" {...props as ButtonHTMLAttributes<HTMLButtonElement>} aria-pressed={selected ?? false} className={cn(classes, 'min-h-11 focus-visible:ring-2 focus-visible:ring-focus')} />;
  return <span {...props as HTMLAttributes<HTMLSpanElement>} className={classes} />;
}

import * as React from 'react';
import { cn } from '@/lib/utils';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'outline' | 'ghost';
  size?: 'default' | 'sm' | 'lg';
  loading?: boolean;
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'default', size = 'default', loading = false, disabled, children, ...props }, ref) => {
    const variants = {
      default: 'bg-primary text-text-inverse hover:bg-primary-dark',
      outline: 'border border-border bg-surface text-primary hover:bg-surface-alt',
      ghost: 'text-primary hover:bg-surface-alt',
    };
    const sizes = {
      default: 'min-h-11 px-4 py-2',
      sm: 'min-h-11 px-3 text-sm',
      lg: 'min-h-12 px-6 text-lg',
    };
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
          variants[variant],
          sizes[size],
          className
        )}
        {...props}
        disabled={disabled || loading}
        aria-busy={loading || undefined}
      >
        {loading && <span aria-hidden="true" className="size-4 animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none" />}
        {children}
      </button>
    );
  }
);
Button.displayName = 'Button';
export { Button };

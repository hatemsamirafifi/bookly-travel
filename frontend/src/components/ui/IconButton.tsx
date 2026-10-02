import { Button, type ButtonProps } from './button';
import { cn } from '@/lib/utils';

export function IconButton({ label, className, ...props }: Omit<ButtonProps, 'aria-label'> & { label: string }) {
  return <Button type="button" variant="ghost" {...props} aria-label={label} className={cn('min-h-11 min-w-11 p-2', className)} />;
}

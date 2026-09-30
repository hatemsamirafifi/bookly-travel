import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function Container({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('mx-auto w-full max-w-content px-gutter', className)} {...props} />;
}

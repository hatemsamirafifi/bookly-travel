import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function CarouselRail({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
  return <div role="region" aria-label={label} tabIndex={0} className={cn('flex snap-x snap-proximity gap-4 overflow-x-auto pb-4 motion-reduce:scroll-auto focus-visible:ring-2 focus-visible:ring-focus [&>*]:shrink-0 [&>*]:snap-start', className)}>{children}</div>;
}

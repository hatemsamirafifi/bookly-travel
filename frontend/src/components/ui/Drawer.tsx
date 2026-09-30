'use client';

import { Dialog, type DialogProps } from './Dialog';

export function Drawer({ side = 'right', ...props }: Omit<DialogProps, 'placement'> & { side?: 'left' | 'right' }) {
  return <Dialog {...props} placement={side} />;
}

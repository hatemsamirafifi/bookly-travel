'use client';

import { useEffect, useId, useRef, useSyncExternalStore, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { IconButton } from './IconButton';
import { cn } from '@/lib/utils';

// All open dialogs share the page lock; only the top layer handles keyboard input.
const modalRoots: HTMLElement[] = [];
const inertSnapshots = new Map<HTMLElement, boolean>();
let originalOverflow = '';
let pageFocus: HTMLElement | null = null;
const subscribeToClient = () => () => {};
const clientSnapshot = () => true;
const serverSnapshot = () => false;

function updateModalInertness() {
  const active = modalRoots.at(-1);
  for (const node of Array.from(document.body.children)) {
    if (!(node instanceof HTMLElement)) continue;
    if (!inertSnapshots.has(node)) inertSnapshots.set(node, Boolean(node.inert));
    node.inert = node !== active;
  }
}

function focusTargets(panel: HTMLElement) {
  return Array.from(panel.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')).filter((node) => {
    if (node.matches(':disabled, input[type="hidden"]') || node.tabIndex < 0) return false;
    let ancestor: HTMLElement | null = node;
    while (ancestor) {
      const style = getComputedStyle(ancestor);
      if (ancestor.hidden || ancestor.inert || ancestor.getAttribute('aria-hidden') === 'true' || style.display === 'none' || style.visibility === 'hidden') return false;
      ancestor = ancestor.parentElement;
    }
    return true;
  });
}

export interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: string;
  closeLabel: string;
  children: ReactNode;
  className?: string;
  placement?: 'center' | 'left' | 'right';
}

export function Dialog({ open, onClose, title, closeLabel, children, className, placement = 'center' }: DialogProps) {
  const mounted = useSyncExternalStore(subscribeToClient, clientSnapshot, serverSnapshot);
  const id = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef(onClose);
  useEffect(() => { closeRef.current = onClose; }, [onClose]);

  useEffect(() => {
    if (!open || !mounted) return;
    const panel = panelRef.current;
    if (!panel) return;
    const root = panel.parentElement!;
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (modalRoots.length === 0) {
      originalOverflow = document.body.style.overflow;
      pageFocus = previousFocus;
    }
    modalRoots.push(root);
    updateModalInertness();
    document.body.style.overflow = 'hidden';
    const focusable = () => focusTargets(panel);
    (focusable()[0] ?? panel).focus();
    const handleKey = (event: KeyboardEvent) => {
      if (modalRoots.at(-1) !== root) return;
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeRef.current(); }
      if (event.key !== 'Tab') return;
      const targets = focusable();
      const first = targets[0];
      const last = targets[targets.length - 1];
      if (!first) { event.preventDefault(); panel.focus(); }
      else if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', handleKey);
    return () => {
      document.removeEventListener('keydown', handleKey);
      const wasTop = modalRoots.at(-1) === root;
      modalRoots.splice(modalRoots.indexOf(root), 1);
      if (modalRoots.length > 0) {
        updateModalInertness();
        if (wasTop) {
          const remaining = modalRoots.at(-1)!;
          if (previousFocus?.isConnected && remaining.contains(previousFocus)) previousFocus.focus();
          else focusTargets(remaining)[0]?.focus();
        }
      } else {
        document.body.style.overflow = originalOverflow;
        inertSnapshots.forEach((value, node) => { node.inert = value; });
        inertSnapshots.clear();
        const target = previousFocus?.isConnected ? previousFocus : pageFocus;
        target?.focus();
        pageFocus = null;
      }
    };
  }, [open, mounted]);

  if (!open || !mounted) return null;
  return createPortal(
    <div
      className={cn('fixed inset-0 z-modal flex bg-overlay', placement === 'center' ? 'items-center justify-center p-4' : placement === 'left' ? 'justify-start' : 'justify-end')}
      onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={id}
        tabIndex={-1}
        className={cn('flex max-h-full flex-col bg-surface text-primary shadow-modal', placement === 'center' ? 'w-full max-w-lg rounded-lg' : 'h-full w-80 max-w-full', className)}
      >
        <div className="flex shrink-0 items-center justify-between gap-4 border-b border-border p-4">
          <h2 id={id} className="text-lg font-bold">{title}</h2>
          <IconButton label={closeLabel} className="text-inherit hover:bg-primary/10" onClick={onClose}>
            <X aria-hidden="true" className="size-5" />
          </IconButton>
        </div>
        <div className="min-h-0 overflow-y-auto p-4">{children}</div>
      </div>
    </div>,
    document.body,
  );
}

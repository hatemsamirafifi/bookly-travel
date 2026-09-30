import type { ReactNode } from 'react';
import { Container } from '@/components/ui/Container';

interface AuthPageShellProps {
  title: string;
  subtitle: string;
  children: ReactNode;
}

export function AuthPageShell({ title, subtitle, children }: AuthPageShellProps) {
  return (
    <main className="flex min-h-[70dvh] items-center justify-center bg-surface-alt px-4 py-10 sm:px-6 sm:py-16">
      <Container className="max-w-md rounded-2xl border border-border bg-surface px-5 py-8 shadow-sm sm:px-8 sm:py-10">
        <header className="mb-8 text-center">
          <p className="mb-4 text-2xl font-extrabold tracking-tight text-bookly-navy">Bookly</p>
          <h1 className="mb-2 text-2xl font-bold tracking-tight text-bookly-navy">{title}</h1>
          <p className="text-sm text-text-muted">{subtitle}</p>
        </header>
        {children}
      </Container>
    </main>
  );
}

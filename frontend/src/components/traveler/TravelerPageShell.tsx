interface TravelerPageShellProps {
  title: string;
  subtitle: string;
  children: React.ReactNode;
}

export default function TravelerPageShell({ title, subtitle, children }: TravelerPageShellProps) {
  return (
    <main className="min-h-full bg-surface-alt">
      <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <header className="mb-8 max-w-3xl">
          <h1 className="text-3xl font-bold tracking-tight text-bookly-navy sm:text-4xl">{title}</h1>
          <p className="mt-2 text-sm text-text-muted sm:text-base">{subtitle}</p>
        </header>
        {children}
      </div>
    </main>
  );
}

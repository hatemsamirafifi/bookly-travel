import SearchBar from '@/components/search/SearchBar';

interface HeroSectionProps {
  title?: string;
  subtitle?: string;
}

export default function HeroSection({ title, subtitle }: HeroSectionProps) {
  return (
    <section className="relative bg-gradient-to-b from-surface-alt to-surface py-16 px-4" aria-labelledby="hero-heading">
      <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center flex flex-col items-center">
        <h1 id="hero-heading" className="mb-3 text-4xl font-extrabold tracking-tight text-bookly-navy sm:text-5xl lg:text-6xl">
          {title ?? 'Where to?'}
        </h1>
        {subtitle && (
          <p className="mb-8 max-w-2xl text-base text-text-muted sm:text-lg">
            {subtitle}
          </p>
        )}
        <div className="w-full flex justify-center">
          <SearchBar compact={false} />
        </div>
      </div>
    </section>
  );
}

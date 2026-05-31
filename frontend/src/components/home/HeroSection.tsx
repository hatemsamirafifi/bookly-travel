'use client';

import SearchBar from '@/components/search/SearchBar';

interface HeroSectionProps {
  title: string;
  subtitle: string;
}

export default function HeroSection({ title, subtitle }: HeroSectionProps) {
  return (
    <section className="relative flex flex-col items-center justify-center bg-gradient-to-b from-[#0A2540] to-[#071b2e] px-4 py-20 text-center text-white"
    >
      <h1 className="mb-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl"
      >
        {title}
      </h1>
      <p className="mb-8 max-w-xl text-lg text-[#F7F9FB]/80"
      >
        {subtitle}
      </p>
      <div className="w-full max-w-2xl"
      >
        <SearchBar compact={false} />
      </div>
    </section>
  );
}

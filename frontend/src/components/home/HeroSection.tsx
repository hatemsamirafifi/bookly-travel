import SearchBar from '@/components/search/SearchBar';

interface HeroSectionProps {
  locale: string;
}

export default function HeroSection({ locale }: HeroSectionProps) {
  return (
    <section className="relative flex flex-col items-center justify-center bg-gradient-to-b from-blue-600 to-blue-800 px-4 py-20 text-center text-white">
      <h1 className="mb-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
        Discover & Book Amazing Tours
      </h1>
      <p className="mb-8 max-w-xl text-lg text-blue-100">
        Explore thousands of experiences curated by local experts. Your next adventure starts here.
      </p>
      <div className="w-full max-w-2xl">
        <SearchBar compact={false} />
      </div>
    </section>
  );
}

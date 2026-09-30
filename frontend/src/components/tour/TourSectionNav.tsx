interface TourSectionNavProps {
  label: string;
  sections: { id: string; label: string }[];
}

export default function TourSectionNav({ label, sections }: TourSectionNavProps) {
  return (
    <nav aria-label={label} className="sticky top-0 z-20 -mx-4 mt-8 border-y border-border bg-surface/95 px-4 backdrop-blur-sm sm:mx-0 sm:rounded-xl sm:border sm:px-3">
      <div className="flex gap-1 overflow-x-auto py-2 whitespace-nowrap">
        {sections.map(({ id, label: sectionLabel }) => (
          <a key={id} href={`#${id}`} className="rounded-lg px-3 py-2 text-sm font-medium text-bookly-navy hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus">
            {sectionLabel}
          </a>
        ))}
      </div>
    </nav>
  );
}

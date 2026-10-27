'use client';

import { useCallback, useEffect, useRef, useState, type MouseEvent } from 'react';

export interface TourSectionNavEntry {
  id: string;
  label: string;
}

interface TourSectionNavProps {
  label: string;
  sections: TourSectionNavEntry[];
}

const HEIGHT_PROPERTY = '--tour-section-nav-height';
const OFFSET_PROPERTY = '--tour-section-nav-offset';
const REVEAL_GUTTER_PX = 8;
// Native scrollIntoView can round a fractional target one CSS pixel past
// the measured edge. Reserve that pixel without scrolling during observation.
const NATIVE_SCROLL_ROUNDING_PX = 1;

function isModifiedClick(event: MouseEvent<HTMLAnchorElement>): boolean {
  return (
    event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0
  );
}

function prefersReducedMotion(): boolean {
  if (typeof window.matchMedia !== 'function') return false;
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Nearest above-band position: a known entry counts as scrolled past when its
 *  top edge sits at or above the measured sticky obstruction. */
function isScrolledPast(top: number, obstruction: number): boolean {
  return top - obstruction <= 1;
}

function findSectionLink(container: HTMLElement, id: string): HTMLAnchorElement | null {
  const links = container.getElementsByTagName('a');
  for (const link of links) {
    if (link.getAttribute('href') === `#${id}`) return link;
  }
  return null;
}

/** Horizontal-only reveal: adjust the rail scroll offset, never the page. */
function revealLinkHorizontally(container: HTMLElement, link: HTMLElement): void {
  const containerBox = container.getBoundingClientRect();
  const linkBox = link.getBoundingClientRect();
  if (linkBox.left < containerBox.left) {
    container.scrollLeft -= containerBox.left - linkBox.left + REVEAL_GUTTER_PX;
  } else if (linkBox.right > containerBox.right) {
    container.scrollLeft += linkBox.right - containerBox.right + REVEAL_GUTTER_PX;
  }
}

/** Drop persisted observer state for sections that are no longer supplied. */
function pruneUnknownTargets(
  visibility: Map<string, boolean>,
  geometry: Map<string, number>,
  known: Set<string>,
): void {
  visibility.forEach((_seen, id) => {
    if (!known.has(id)) {
      visibility.delete(id);
      geometry.delete(id);
    }
  });
}
/** Real heading first (h2 in current sections, h3 for accordion titles);
 *  the section container is only a fallback when no heading genuinely exists.
 *  Used both for the observed target and for explicit-activation focus. */
function resolveHeading(section: HTMLElement): HTMLElement {
  return section.querySelector('h1, h2, h3') ?? section;
}

function focusWithoutScroll(target: HTMLElement): void {
  if (target.tabIndex < 0 && !target.hasAttribute('tabindex')) {
    target.setAttribute('tabindex', '-1');
  }
  target.focus({ preventScroll: true });
}

/**
 * US4 reading-navigation island. Native anchors and sticky positioning stay
 * exactly as the baseline; this adds active-section tracking, measured
 * obstruction offsets and explicit-activation focus. Passive observation
 * never moves focus and never scrolls the page vertically.
 */
export default function TourSectionNav({ label, sections }: TourSectionNavProps) {
  const navRef = useRef<HTMLElement | null>(null);
  const railRef = useRef<HTMLDivElement | null>(null);
  const obstructionRef = useRef(0);
  const honoredDeepLink = useRef(false);
  // Persisted per-target observer state. Entries are deltas, not snapshots, so
  // each notification merges into these maps and selection reads the full
  // known set; keys outside the current sections are pruned on every callback.
  const visibilityRef = useRef(new Map<string, boolean>());
  const geometryRef = useRef(new Map<string, number>());
  // Pipe-joined key: the parent builds a fresh array each render, so effects
  // depend on this stable string instead of the array identity.
  const idsKey = sections.map((section) => section.id).join('|');
  const ids = idsKey === '' ? [] : idsKey.split('|');
  const [activeId, setActiveId] = useState<string | null>(sections[0]?.id ?? null);
  const [prevIdsKey, setPrevIdsKey] = useState(idsKey);
  const [navOffset, setNavOffset] = useState(0);

  // Keep the active entry inside the supplied present sections. Render-phase
  // adjustment (not an effect): when the section set is replaced, reselect
  // without waiting for the next observation round.
  if (prevIdsKey !== idsKey) {
    setPrevIdsKey(idsKey);
    setActiveId((current) => {
      if (ids.length === 0) return null;
      return current !== null && ids.includes(current) ? current : ids[0];
    });
  }

  /** Scroll clear of the measured nav and focus the real heading; the caller
   *  owns hash intent (activation pushes, deep-link honor keeps). */
  const showSection = useCallback((id: string): boolean => {
    const section = document.getElementById(id);
    if (!(section instanceof HTMLElement)) return false;
    const obstruction = obstructionRef.current;
    const top = Math.max(section.getBoundingClientRect().top + window.scrollY - obstruction, 0);
    window.scrollTo({ top, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    focusWithoutScroll(resolveHeading(section));
    setActiveId(id);
    return true;
  }, []);

  // Measure the actual sticky nav and publish it scoped to the nearest page
  // container. The relative site header contributes no obstruction, so only
  // the nav itself is measured. The lifecycle follows section identity: an
  // empty section set renders no nav (prior cleanup restores the scope), and
  // any later presence or replacement re-runs measurement from the live node.
  // Prior inline values are restored on every cleanup.
  useEffect(() => {
    const nav = navRef.current;
    const scope = nav?.parentElement;
    if (!nav || !scope) return;
    const previousHeight = scope.style.getPropertyValue(HEIGHT_PROPERTY);
    const previousOffset = scope.style.getPropertyValue(OFFSET_PROPERTY);
    const publish = () => {
      const measured = Math.max(0, Math.ceil(nav.getBoundingClientRect().height));
      const offset = measured > 0 ? measured + NATIVE_SCROLL_ROUNDING_PX : 0;
      obstructionRef.current = offset;
      setNavOffset((current) => (current === offset ? current : offset));
      scope.style.setProperty(HEIGHT_PROPERTY, `${measured}px`);
      scope.style.setProperty(OFFSET_PROPERTY, `${offset}px`);
    };
    publish();
    if (typeof ResizeObserver !== 'undefined') {
      const resizeObserver = new ResizeObserver(publish);
      resizeObserver.observe(nav);
      return () => {
        resizeObserver.disconnect();
        restoreScopedProperty(scope, HEIGHT_PROPERTY, previousHeight);
        restoreScopedProperty(scope, OFFSET_PROPERTY, previousOffset);
      };
    }
    window.addEventListener('resize', publish);
    return () => {
      window.removeEventListener('resize', publish);
      restoreScopedProperty(scope, HEIGHT_PROPERTY, previousHeight);
      restoreScopedProperty(scope, OFFSET_PROPERTY, previousOffset);
    };
  }, [idsKey]);

  // Follow actual visible headings. Each present section contributes its real
  // heading (container fallback only when no heading genuinely exists); a
  // node->section-ID map routes notifications back to href IDs. Unknown nodes
  // are ignored; the full known set is read from persisted per-target state
  // because notifications carry only deltas. When no heading is visible, pin
  // the nearest scrolled-past section instead of guessing. The first
  // observation also honors an initial deep link into a present section, since
  // the URL is an external system the island syncs with.
  useEffect(() => {
    const observedIds = idsKey === '' ? [] : idsKey.split('|');
    if (observedIds.length === 0 || typeof IntersectionObserver === 'undefined') return;
    const known = new Set(observedIds);
    const visibility = visibilityRef.current;
    const geometry = geometryRef.current;
    pruneUnknownTargets(visibility, geometry, known);
    const targetToId = new Map<HTMLElement, string>();
    for (const id of observedIds) {
      const section = document.getElementById(id);
      if (!section) continue;
      const target = resolveHeading(section);
      targetToId.set(target, id);
    }
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          const target = entry.target;
          if (!(target instanceof HTMLElement)) continue;
          const id = targetToId.get(target);
          if (id === undefined || !known.has(id)) continue;
          visibility.set(id, entry.isIntersecting);
          geometry.set(
            id,
            entry.boundingClientRect?.top ?? target.getBoundingClientRect().top,
          );
        }
        pruneUnknownTargets(visibility, geometry, known);
        if (!honoredDeepLink.current) {
          honoredDeepLink.current = true;
          const hash = window.location.hash.replace(/^#/, '');
          if (hash && known.has(hash)) {
            showSection(hash);
            return;
          }
        }
        const visible = observedIds.filter((id) => visibility.get(id) === true);
        const hashId = window.location.hash.replace(/^#/, '');
        const hashedVisible =
          hashId !== '' && known.has(hashId) && visibility.get(hashId) === true
            ? hashId
            : null;
        const selected = hashedVisible ?? (visible.length > 0 ? visible[0] : null);
        if (selected !== null) {
          setActiveId(selected);
          return;
        }
        const passed: string[] = [];
        for (const id of observedIds) {
          if (visibility.get(id) !== false) continue;
          const top = geometry.get(id);
          if (top !== undefined && isScrolledPast(top, obstructionRef.current)) {
            passed.push(id);
          }
        }
        if (passed.length > 0) setActiveId(passed[passed.length - 1]);
      },
      { rootMargin: `-${navOffset}px 0px -55% 0px`, threshold: 0 },
    );
    for (const target of targetToId.keys()) observer.observe(target);
    return () => observer.disconnect();
  }, [idsKey, navOffset, showSection]);

  // Horizontal-only rail reveal on active changes; vertical page scroll is
  // never touched here (no scrollIntoView).
  useEffect(() => {
    if (!activeId || !railRef.current) return;
    const link = findSectionLink(railRef.current, activeId);
    if (link) revealLinkHorizontally(railRef.current, link);
  }, [activeId]);

  if (sections.length === 0) return null;

  const handleActivate = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
    if (isModifiedClick(event)) return;
    event.preventDefault();
    if (showSection(id)) window.history.pushState(null, '', `#${id}`);
  };

  return (
    <nav ref={navRef} aria-label={label} className="sticky top-0 z-sticky -mx-4 mt-8 border-y border-border bg-surface/95 px-4 backdrop-blur-sm sm:mx-0 sm:rounded-xl sm:border sm:px-3">
      <div ref={railRef} className="flex gap-1 overflow-x-auto py-2 whitespace-nowrap">
        {sections.map(({ id, label: sectionLabel }) => {
          const isActive = id === activeId;
          return (
            <a
              key={id}
              href={`#${id}`}
              aria-current={isActive ? 'location' : undefined}
              onClick={(event) => handleActivate(event, id)}
              className={
                isActive
                  ? 'rounded-lg bg-surface-alt px-3 py-2 text-sm font-semibold text-bookly-navy underline decoration-accent decoration-2 underline-offset-4 focus-visible:outline-2 focus-visible:outline-focus'
                  : 'rounded-lg px-3 py-2 text-sm font-medium text-bookly-navy hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus'
              }
            >
              {sectionLabel}
            </a>
          );
        })}
      </div>
    </nav>
  );
}

function restoreScopedProperty(scope: HTMLElement, property: string, previous: string): void {
  if (previous) scope.style.setProperty(property, previous);
  else scope.style.removeProperty(property);
}

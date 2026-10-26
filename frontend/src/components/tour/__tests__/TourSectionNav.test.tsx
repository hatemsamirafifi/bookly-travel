import { act, fireEvent, render, screen } from '@testing-library/react';
import TourSectionNav from '../TourSectionNav';

const SECTIONS = [
  { id: 'overview', label: 'About' },
  { id: 'facts', label: 'Facts' },
  { id: 'reviews', label: 'Reviews' },
];

type ObserverCallback = (
  entries: Array<{
    target: Element;
    isIntersecting: boolean;
    boundingClientRect?: { top: number };
  }>,
  observer: IntersectionObserver,
) => void;

let intersectionCallbacks: ObserverCallback[] = [];
let observedTargets: Element[][] = [];
let unobservedTargets: Element[][] = [];
let disconnectedObservers = 0;
let resizeCallbacks: Array<() => void> = [];
let observedResizeTargets: Element[][] = [];
let disconnectedResizeObservers = 0;
let scrollIntoViewCalls = 0;

class MockIntersectionObserver {
  callback: ObserverCallback;
  targets: Element[] = [];
  constructor(callback: ObserverCallback) {
    this.callback = callback;
    intersectionCallbacks.push(callback);
  }
  observe(target: Element) {
    this.targets.push(target);
    observedTargets.push([...this.targets]);
  }
  unobserve(target: Element) {
    unobservedTargets.push([target]);
    this.targets = this.targets.filter((item) => item !== target);
  }
  disconnect() {
    disconnectedObservers += 1;
  }
}

class MockResizeObserver {
  callback: () => void;
  constructor(callback: () => void) {
    this.callback = callback;
    resizeCallbacks.push(callback);
  }
  observe(target: Element) {
    observedResizeTargets.push([target]);
  }
  unobserve() {}
  disconnect() {
    disconnectedResizeObservers += 1;
  }
}

function installBrowserBoundaries() {
  (window as unknown as Record<string, unknown>).IntersectionObserver =
    MockIntersectionObserver;
  (window as unknown as Record<string, unknown>).ResizeObserver = MockResizeObserver;
  window.scrollTo = jest.fn();
  window.matchMedia = jest.fn().mockReturnValue({
    matches: false,
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  }) as unknown as typeof window.matchMedia;
  Element.prototype.scrollIntoView = jest.fn(() => {
    scrollIntoViewCalls += 1;
  });
}

function renderPage(sections = SECTIONS) {
  document.body.innerHTML = '';
  const page = document.createElement('div');
  page.innerHTML = sections
    .map((section) => `<section id="${section.id}"><h2>${section.label} heading</h2></section>`)
    .join('');
  document.body.appendChild(page);
  return render(<TourSectionNav label="Sections" sections={sections} />);
}

beforeEach(() => {
  intersectionCallbacks = [];
  observedTargets = [];
  unobservedTargets = [];
  disconnectedObservers = 0;
  resizeCallbacks = [];
  observedResizeTargets = [];
  disconnectedResizeObservers = 0;
  scrollIntoViewCalls = 0;
  window.location.hash = '';
  installBrowserBoundaries();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('TourSectionNav (US4 isolated slice)', () => {
  it('renders only the supplied present sections as native anchors', () => {
    renderPage();

    for (const section of SECTIONS) {
      const link = screen.getByRole('link', { name: section.label });
      expect(link).toHaveAttribute('href', `#${section.id}`);
    }
    expect(screen.queryByRole('link', { name: 'Operator' })).not.toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Sections' })).toBeInTheDocument();
  });

  it('marks the current section with aria-current plus a non-color cue', () => {
    renderPage();

    const current = screen.getByRole('link', { name: 'About' });
    expect(current).toHaveAttribute('aria-current', 'location');
    // A non-color cue (underline and/or stronger weight) must accompany color.
    expect(current.className).toMatch(/underline|font-semibold/);
    expect(screen.getByRole('link', { name: 'Facts' })).not.toHaveAttribute('aria-current');
  });

  it('observes only actual present headings and ignores absent entries', () => {
    renderPage();
    const latest = observedTargets[observedTargets.length - 1];
    expect(latest).toHaveLength(SECTIONS.length);
    for (const section of SECTIONS) {
      expect(latest).toContain(document.querySelector(`#${section.id} h2`));
    }
    // Target membership: a node that is not a mapped present heading must
    // never become active even if the observer reports it.
    const lastCallback = intersectionCallbacks[intersectionCallbacks.length - 1];
    const ghost = document.createElement('section');
    ghost.id = 'operator';
    ghost.innerHTML = '<h2>Operator heading</h2>';
    document.body.appendChild(ghost);
    const ghostHeading = ghost.querySelector('h2') as Element;
    act(() => {
      lastCallback(
        [
          { target: ghost, isIntersecting: true },
          { target: ghostHeading, isIntersecting: true },
        ],
        {} as IntersectionObserver,
      );
    });

    expect(screen.getByRole('link', { name: 'About' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.queryByRole('link', { name: 'Operator' })).not.toBeInTheDocument();
  });

  it('follows actual headings: observer entries move the active entry', () => {
    renderPage();
    const lastCallback = intersectionCallbacks[intersectionCallbacks.length - 1];
    const factsHeading = document.querySelector('#facts h2') as Element;

    act(() => {
      lastCallback(
        [{ target: factsHeading, isIntersecting: true }],
        {} as IntersectionObserver,
      );
    });

    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'About' })).not.toHaveAttribute('aria-current');
  });

  it('passive observer updates never take focus and never scroll the page vertically', () => {
    renderPage();
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    const beforeY = window.scrollY;
    const probe = document.createElement('button');
    probe.textContent = 'probe';
    document.body.appendChild(probe);
    probe.focus();
    expect(probe).toHaveFocus();

    const lastCallback = intersectionCallbacks[intersectionCallbacks.length - 1];
    act(() => {
      lastCallback(
        [
          {
            target: document.querySelector('#reviews h2') as Element,
            isIntersecting: true,
          },
        ],
        {} as IntersectionObserver,
      );
    });

    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(window.scrollY).toBe(beforeY);
    expect(scrollIntoViewCalls).toBe(0);
    expect(screen.getByRole('link', { name: 'Reviews' })).toHaveAttribute(
      'aria-current',
      'location',
    );
  });

  it('plain activation keeps the hash, clears the measured sticky nav and focuses the real heading', () => {
    const { container } = renderPage();
    const nav = container.querySelector('nav') as HTMLElement;
    jest.spyOn(nav, 'getBoundingClientRect').mockReturnValue({
      height: 48,
      width: 300,
      top: 0,
      left: 0,
      bottom: 48,
      right: 300,
      x: 0,
      y: 0,
      toJSON: () => {},
    });
    act(() => {
      resizeCallbacks.forEach((callback) => callback());
    });
    const heading = document.querySelector('#facts h2') as HTMLElement;
    const section = document.getElementById('facts') as HTMLElement;
    jest.spyOn(section, 'getBoundingClientRect').mockReturnValue({
      height: 400,
      width: 300,
      top: 200,
      left: 0,
      bottom: 600,
      right: 300,
      x: 0,
      y: 200,
      toJSON: () => {},
    });
    const scrollTo = window.scrollTo as jest.Mock;

    fireEvent.click(screen.getByRole('link', { name: 'Facts' }));

    expect(window.location.hash).toBe('#facts');
    expect(scrollTo).toHaveBeenCalledTimes(1);
    // Measured48px obstruction plus1px native-scroll rounding allowance.
    expect(scrollTo.mock.calls[0][0]).toMatchObject({ top: 151 });
    expect(heading).toHaveFocus();
    expect(section).not.toHaveFocus();
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
  });

  it('modifier clicks keep native behavior: no interception, scroll or focus theft', () => {
    renderPage();
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    const link = screen.getByRole('link', { name: 'Facts' }) as HTMLAnchorElement;
    const heading = document.querySelector('#facts h2') as HTMLElement;

    // Not prevented: the browser keeps its native modified-click behavior.
    expect(fireEvent.click(link, { ctrlKey: true })).toBe(true);

    expect(scrollTo).not.toHaveBeenCalled();
    expect(heading).not.toHaveFocus();
    expect(screen.getByRole('link', { name: 'About' })).toHaveAttribute(
      'aria-current',
      'location',
    );
  });

  it.each([
    { height: 56, measured: 56, clearance: 57 },
    { height: 54, measured: 54, clearance: 55 },
    { height: 54.25, measured: 55, clearance: 56 },
  ])('publishes safe clearance for a $height px nav and restores the scope', ({ height, measured, clearance }) => {
    // The scope is the nearest page container (the nav parent), seeded with a
    // prior inline value the island must preserve.
    const scope = document.createElement('div');
    scope.style.setProperty('--tour-section-nav-offset', 'keep-me');
    document.body.appendChild(scope);
    const { unmount } = render(<TourSectionNav label="Sections" sections={SECTIONS} />, {
      container: scope,
    });
    const nav = screen.getByRole('navigation', { name: 'Sections' });
    expect(nav.parentElement).toBe(scope);
    jest.spyOn(nav, 'getBoundingClientRect').mockReturnValue({
      height,
      width: 300,
      top: 0,
      left: 0,
      bottom: height,
      right: 300,
      x: 0,
      y: 0,
      toJSON: () => {},
    });
    act(() => {
      resizeCallbacks.forEach((callback) => callback());
    });

    expect(scope.style.getPropertyValue('--tour-section-nav-height')).toBe(`${measured}px`);
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe(`${clearance}px`);

    unmount();
    expect(disconnectedObservers).toBeGreaterThan(0);
    expect(disconnectedResizeObservers).toBeGreaterThan(0);
    // Prior inline properties are preserved, not wiped.
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe('keep-me');
    expect(scope.style.getPropertyValue('--tour-section-nav-height')).toBe('');
    document.body.removeChild(scope);
  });

  it('re-observes when the supplied sections change and drops removed targets', () => {
    const { rerender } = renderPage();
    const firstRound = observedTargets[observedTargets.length - 1];
    expect(firstRound).toHaveLength(SECTIONS.length);
    for (const section of SECTIONS) {
      expect(firstRound).toContain(document.querySelector(`#${section.id} h2`));
    }

    rerender(
      <TourSectionNav
        label="Sections"
        sections={[
          { id: 'overview', label: 'About' },
          { id: 'facts', label: 'Facts' },
        ]}
      />,
    );

    expect(screen.queryByRole('link', { name: 'Reviews' })).not.toBeInTheDocument();
    const latest = observedTargets[observedTargets.length - 1];
    expect(latest).toHaveLength(2);
    expect(latest).toContain(document.querySelector('#overview h2'));
    expect(latest).toContain(document.querySelector('#facts h2'));
    expect(latest).not.toContain(document.querySelector('#reviews h2'));
    expect(disconnectedObservers).toBeGreaterThan(0);
    expect(unobservedTargets.flat().length + disconnectedObservers).toBeGreaterThan(0);
  });

  it('scrolls immediately without animation when reduced motion is preferred', () => {
    (window.matchMedia as jest.Mock).mockReturnValue({ matches: true });
    renderPage();
    const section = document.getElementById('overview') as HTMLElement;
    jest.spyOn(section, 'getBoundingClientRect').mockReturnValue({
      height: 400,
      width: 300,
      top: 300,
      left: 0,
      bottom: 700,
      right: 300,
      x: 0,
      y: 300,
      toJSON: () => {},
    });
    const scrollTo = window.scrollTo as jest.Mock;

    fireEvent.click(screen.getByRole('link', { name: 'About' }));

    expect(scrollTo).toHaveBeenCalledTimes(1);
    expect(scrollTo.mock.calls[0][0]).toMatchObject({ behavior: 'auto' });
  });

  it('honors an initial deep link into a present section', () => {
    window.location.hash = '#reviews';
    renderPage();
    const section = document.getElementById('reviews') as HTMLElement;
    jest.spyOn(section, 'getBoundingClientRect').mockReturnValue({
      height: 400,
      width: 300,
      top: 500,
      left: 0,
      bottom: 900,
      right: 300,
      x: 0,
      y: 500,
      toJSON: () => {},
    });
    const scrollTo = window.scrollTo as jest.Mock;
    const heading = section.querySelector('h2') as HTMLElement;
    const lastCallback = intersectionCallbacks[intersectionCallbacks.length - 1];

    act(() => {
      lastCallback(
        [{ target: heading, isIntersecting: false }],
        {} as IntersectionObserver,
      );
    });

    expect(scrollTo).toHaveBeenCalledTimes(1);
    expect(scrollTo.mock.calls[0][0]).toMatchObject({ top: 500 });
    expect(heading).toHaveFocus();
    expect(screen.getByRole('link', { name: 'Reviews' })).toHaveAttribute(
      'aria-current',
      'location',
    );
  });

  it('reveals the active entry horizontally without any vertical scrollIntoView', () => {
    renderPage();
    const lastCallback = intersectionCallbacks[intersectionCallbacks.length - 1];
    act(() => {
      lastCallback(
        [
          {
            target: document.querySelector('#reviews h2') as Element,
            isIntersecting: true,
          },
        ],
        {} as IntersectionObserver,
      );
    });

    expect(scrollIntoViewCalls).toBe(0);
  });

  it('keeps the later visible section active when an earlier one exits alone across delta notifications', () => {
    // Observer entries are deltas: a still-intersecting section produces no
    // new entry. Selection must persist per-target state, not batch-only data.
    renderPage();
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    const probe = document.createElement('button');
    probe.textContent = 'probe';
    document.body.appendChild(probe);
    probe.focus();
    expect(probe).toHaveFocus();

    const factsHeading = document.querySelector('#facts h2') as Element;
    const overviewHeading = document.querySelector('#overview h2') as Element;
    const reviewsHeading = document.querySelector('#reviews h2') as Element;

    // N1: facts heading enters the active band.
    act(() => {
      lastCallbackOf().call([
        { target: factsHeading, isIntersecting: true, boundingClientRect: { top: 100 } },
      ]);
    });
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );

    // N2: overview heading alone exits while facts heading stays intersecting
    // and silent.
    act(() => {
      lastCallbackOf().call([
        { target: overviewHeading, isIntersecting: false, boundingClientRect: { top: -320 } },
      ]);
    });
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'About' })).not.toHaveAttribute('aria-current');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(window.scrollY).toBe(0);
    expect(scrollIntoViewCalls).toBe(0);

    // N3: facts heading exits while reviews heading enters — leadership hands forward.
    act(() => {
      lastCallbackOf().call([
        { target: factsHeading, isIntersecting: false, boundingClientRect: { top: -500 } },
        { target: reviewsHeading, isIntersecting: true, boundingClientRect: { top: 120 } },
      ]);
    });
    expect(screen.getByRole('link', { name: 'Reviews' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'Facts' })).not.toHaveAttribute('aria-current');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(scrollIntoViewCalls).toBe(0);
  });

  it('measures and observes across empty, present, replacement and empty lifecycles', () => {
    // The measure/observe lifecycle must follow the actual nav node and
    // section identity: initial [] publishes nothing, later presence starts
    // measurement, replacement re-observes, and empty restores the scope.
    document.body.innerHTML = '';
    const page = document.createElement('div');
    page.innerHTML = SECTIONS.map(
      (section) => `<section id="${section.id}"><h2>${section.label} heading</h2></section>`,
    ).join('');
    const scope = document.createElement('div');
    scope.style.setProperty('--tour-section-nav-offset', 'keep-me');
    document.body.appendChild(scope);
    document.body.appendChild(page);

    const { rerender } = render(<TourSectionNav label="Sections" sections={[]} />, {
      container: scope,
    });
    expect(scope.querySelector('nav')).toBeNull();
    expect(screen.queryAllByRole('link')).toHaveLength(0);
    expect(intersectionCallbacks.length).toBe(0);
    expect(resizeCallbacks.length).toBe(0);
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe('keep-me');
    expect(scope.style.getPropertyValue('--tour-section-nav-height')).toBe('');

    // Empty -> present: measurement starts and all present sections observe.
    rerender(<TourSectionNav label="Sections" sections={SECTIONS} />);
    expect(screen.getByRole('navigation', { name: 'Sections' })).toBeInTheDocument();
    expect(screen.getAllByRole('link')).toHaveLength(3);
    expect(intersectionCallbacks.length).toBe(1);
    expect(resizeCallbacks.length).toBe(1);
    expect(observedResizeTargets[observedResizeTargets.length - 1][0]).toBe(
      screen.getByRole('navigation', { name: 'Sections' }),
    );
    expect(scope.style.getPropertyValue('--tour-section-nav-height')).toBe('0px');
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe('0px');
    expect(screen.getByRole('link', { name: 'About' })).toHaveAttribute(
      'aria-current',
      'location',
    );

    // Replacement: a fresh observer/resize pair follows the current node.
    rerender(
      <TourSectionNav
        label="Sections"
        sections={[
          { id: 'overview', label: 'About' },
          { id: 'facts', label: 'Facts' },
        ]}
      />,
    );
    expect(screen.getAllByRole('link')).toHaveLength(2);
    expect(screen.queryByRole('link', { name: 'Reviews' })).not.toBeInTheDocument();
    expect(intersectionCallbacks.length).toBe(2);
    expect(resizeCallbacks.length).toBe(2);
    expect(disconnectedObservers).toBe(1);
    expect(disconnectedResizeObservers).toBe(1);
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe('0px');

    // Present -> empty: observers disconnect and prior scope values return.
    rerender(<TourSectionNav label="Sections" sections={[]} />);
    expect(scope.querySelector('nav')).toBeNull();
    expect(screen.queryAllByRole('link')).toHaveLength(0);
    expect(disconnectedObservers).toBe(2);
    expect(disconnectedResizeObservers).toBe(2);
    expect(scope.style.getPropertyValue('--tour-section-nav-offset')).toBe('keep-me');
    expect(scope.style.getPropertyValue('--tour-section-nav-height')).toBe('');
    document.body.removeChild(scope);
    document.body.removeChild(page);
  });

  it('observes actual headings: a tall section body intersecting without its heading does not activate it', () => {
    // Contract: "IntersectionObserver follows visible headings". A tall
    // section whose heading has scrolled above the band must not count as
    // visible merely because its body still intersects the band.
    renderPage();
    const observed = observedTargets.flat();

    // Observer targets are the actual present headings, one per section.
    const latest = observedTargets[observedTargets.length - 1];
    expect(latest).toHaveLength(SECTIONS.length);
    for (const section of SECTIONS) {
      const heading = document.querySelector(`#${section.id} h2`) as Element;
      expect(observed).toContain(heading);
      expect(observed).not.toContain(document.getElementById(section.id));
    }

    // Body-only noise (what container observation would report for the tall
    // section) must not activate anything or steal focus/scroll.
    const probe = document.createElement('button');
    probe.textContent = 'probe';
    document.body.appendChild(probe);
    probe.focus();
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    act(() => {
      lastCallbackOf().call([
        {
          target: document.getElementById('facts') as Element,
          isIntersecting: true,
          boundingClientRect: { top: -350 },
        },
      ]);
    });
    expect(screen.getByRole('link', { name: 'About' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'Facts' })).not.toHaveAttribute('aria-current');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(scrollIntoViewCalls).toBe(0);

    // The heading entering the band maps back to its href section ID.
    act(() => {
      lastCallbackOf().call([
        {
          target: document.querySelector('#facts h2') as Element,
          isIntersecting: true,
          boundingClientRect: { top: 150 },
        },
      ]);
    });
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute('href', '#facts');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(scrollIntoViewCalls).toBe(0);
  });

  it('keeps the explicitly navigated section active while its heading stays visible', () => {
    window.location.hash = '#reviews';
    renderPage();
    const factsHeading = document.querySelector('#facts h2') as Element;
    const reviewsHeading = document.querySelector('#reviews h2') as Element;
    // First observation honors the deep link (existing behavior).
    lastCallbackOf().call([
      { target: reviewsHeading, isIntersecting: true, boundingClientRect: { top: 300 } },
    ]);
    expect(screen.getByRole('link', { name: 'Reviews' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    // THEN: probe button focused, scrollTo mock cleared.
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    const probe = document.createElement('button');
    probe.textContent = 'probe';
    document.body.appendChild(probe);
    probe.focus();
    expect(probe).toHaveFocus();
    // Later drift: an earlier heading reports visible alongside the hashed one.
    lastCallbackOf().call([
      { target: factsHeading, isIntersecting: true, boundingClientRect: { top: 100 } },
      { target: reviewsHeading, isIntersecting: true, boundingClientRect: { top: 300 } },
    ]);
    // The explicit hash target wins while it stays visible; the earliest-
    // visible rule only applies once it leaves.
    expect(screen.getByRole('link', { name: 'Reviews' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'Facts' })).not.toHaveAttribute('aria-current');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(scrollIntoViewCalls).toBe(0);
  });

  it('falls back to reading position when the hashed heading is not visible', () => {
    renderPage();
    const factsHeading = document.querySelector('#facts h2') as Element;
    const reviewsHeading = document.querySelector('#reviews h2') as Element;
    lastCallbackOf().call([
      { target: factsHeading, isIntersecting: true, boundingClientRect: { top: 150 } },
    ]);
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    // Set the hash AFTER the first callback so the deep-link honor path is
    // already consumed; the hashed heading itself is not visible here.
    window.location.hash = '#reviews';
    const scrollTo = window.scrollTo as jest.Mock;
    scrollTo.mockClear();
    const probe = document.createElement('button');
    probe.textContent = 'probe';
    document.body.appendChild(probe);
    probe.focus();
    expect(probe).toHaveFocus();
    lastCallbackOf().call([
      { target: reviewsHeading, isIntersecting: false, boundingClientRect: { top: -500 } },
      { target: factsHeading, isIntersecting: true, boundingClientRect: { top: 150 } },
    ]);
    expect(screen.getByRole('link', { name: 'Facts' })).toHaveAttribute(
      'aria-current',
      'location',
    );
    expect(screen.getByRole('link', { name: 'Reviews' })).not.toHaveAttribute('aria-current');
    expect(probe).toHaveFocus();
    expect(scrollTo).not.toHaveBeenCalled();
    expect(scrollIntoViewCalls).toBe(0);
  });
});

function lastCallbackOf(): { call: (entries: Parameters<ObserverCallback>[0]) => void } {
  const callback = intersectionCallbacks[intersectionCallbacks.length - 1];
  return {
    call: (entries) => {
      act(() => {
        callback(entries, {} as IntersectionObserver);
      });
    },
  };
}

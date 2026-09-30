/**
 * Semantic CSS references — globals.css owns token values.
 * Extend these before introducing new hardcoded color/spacing/shadow values.
 */
export const designTokens = {
  colors: {
    brand: {
      navy: '#0A2540',
      gold: '#FFB800',
    },
    background: {
      page: 'var(--background)',
      surface: 'var(--color-surface)',
      elevated: 'var(--color-surface)',
    },
    text: {
      primary: 'var(--foreground)',
      secondary: 'var(--color-text-muted)',
      inverse: 'var(--color-text-inverse)',
    },
    border: {
      default: 'var(--color-border)',
      focus: 'var(--color-focus)',
    },
    state: {
      success: 'var(--color-success)',
      trust: 'var(--color-trust)',
      warning: 'var(--color-warning)',
      danger: 'var(--color-error)',
    },
    interactive: {
      hover: 'var(--color-hover)',
      pressed: 'var(--color-pressed)',
    },
  },

  layout: { contentWidth: 'var(--content-width)', gutter: 'var(--page-gutter)', overlay: 'var(--color-overlay)' },

  typography: {
    fontFamily: "'Plus Jakarta Sans', system-ui, sans-serif",
    weights: {
      regular: 400,
      medium: 500,
      semibold: 600,
      bold: 700,
    },
    // Plan-aligned type scale: `{ size, lineHeight, weight }` per breakpoint,
    // with a mobile variant where it differs from desktop.
    scale: {
      pageTitle: { desktop: { size: '32px', lineHeight: '1.2', weight: 700 }, mobile: { size: '26px', lineHeight: '1.2', weight: 700 } },
      sectionTitle: { desktop: { size: '24px', lineHeight: '1.25', weight: 600 }, mobile: { size: '21px', lineHeight: '1.25', weight: 600 } },
      cardTitle: { desktop: { size: '18px', lineHeight: '1.3', weight: 600 }, mobile: { size: '18px', lineHeight: '1.3', weight: 600 } },
      subheading: { desktop: { size: '16px', lineHeight: '1.35', weight: 600 }, mobile: { size: '16px', lineHeight: '1.35', weight: 600 } },
      body: { desktop: { size: '16px', lineHeight: '1.5', weight: 400 }, mobile: { size: '16px', lineHeight: '1.5', weight: 400 } },
      small: { desktop: { size: '14px', lineHeight: '1.4', weight: 400 }, mobile: { size: '14px', lineHeight: '1.4', weight: 400 } },
      caption: { desktop: { size: '12px', lineHeight: '1.4', weight: 400 }, mobile: { size: '12px', lineHeight: '1.4', weight: 400 } },
    },
  },

  spacing: {
    grid: 8,
    scale: [0, 0.5, 1, 1.5, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24, 32, 48, 64],
    // Semantic spacing aliases (mobile / desktop).
    pagePadding: { mobile: 'var(--page-gutter)', desktop: 'var(--page-gutter)' },
    sectionGap: { mobile: 'var(--section-gap)', desktop: 'var(--section-gap)' },
    cardPadding: { mobile: 'var(--card-padding)', desktop: 'var(--card-padding)' },
    formGap: 'var(--form-gap)',
    inlineGap: 'var(--inline-gap)',
  },

  borderRadius: {
    sm: 'var(--radius-sm)',
    default: 'var(--radius-md)',
    lg: 'var(--radius-lg)',
    full: '9999px',
  },

  shadows: {
    sm: 'var(--shadow-sm)',
    card: 'var(--shadow-card)',
    dropdown: 'var(--shadow-dropdown)',
    modal: 'var(--shadow-modal)',
  },

  transition: {
    fast: 'var(--motion-fast) var(--motion-easing)',
    default: 'var(--motion-default) var(--motion-easing)',
    slow: 'var(--motion-slow) var(--motion-easing)',
  },

  z: {
    dropdown: 'var(--z-dropdown)',
    sticky: 'var(--z-sticky)',
    modal: 'var(--z-modal)',
    toast: 'var(--z-toast)',
    tooltip: 'var(--z-tooltip)',
  },

  breakpoint: {
    mobile: '390px',
    tablet: '768px',
    desktop: '1024px',
    wide: '1440px',
  },
} as const;

export type DesignToken = typeof designTokens;

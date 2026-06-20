/**
 * Centralized design tokens — aligned with docs/frontend-implementation-plan.md.
 * Extend these before introducing new hardcoded color/spacing/shadow values.
 */
export const designTokens = {
  colors: {
    brand: {
      navy: '#0A2540',
      gold: '#FFB800',
    },
    background: {
      page: '#F7F9FB',
      surface: '#FFFFFF',
      elevated: '#FFFFFF',
    },
    text: {
      primary: '#102033',
      secondary: '#5D6B7A',
      inverse: '#FFFFFF',
    },
    border: {
      default: '#DDE5EE',
      focus: '#0A2540',
    },
    state: {
      success: '#11845B',
      warning: '#B76E00',
      danger: '#C62828',
    },
    interactive: {
      hover: 'rgba(10, 37, 64, 0.08)',
      pressed: 'rgba(10, 37, 64, 0.12)',
    },
  },

  typography: {
    fontFamily: "'Inter', system-ui, sans-serif",
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
    pagePadding: { mobile: '24px', desktop: '64px' },
    sectionGap: { mobile: '48px', desktop: '64px' },
    cardPadding: { mobile: '16px', desktop: '24px' },
    formGap: '16px',
    inlineGap: '8px',
  },

  borderRadius: {
    sm: '8px',
    default: '12px',
    lg: '16px',
    full: '9999px',
  },

  shadows: {
    sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
    card: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
    dropdown: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
    modal: '0 25px 50px -12px rgb(0 0 0 / 0.25)',
  },

  transition: {
    fast: '150ms ease-out',
    default: '200ms ease-in-out',
    slow: '300ms ease-in-out',
  },

  z: {
    dropdown: 10,
    sticky: 20,
    modal: 30,
    toast: 40,
    tooltip: 50,
  },

  breakpoint: {
    mobile: '390px',
    tablet: '780px',
    desktop: '1280px',
  },
} as const;

export type DesignToken = typeof designTokens;
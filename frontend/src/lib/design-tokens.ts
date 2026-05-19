export const designTokens = {
  colors: {
    primary: '#0A2540',
    accent: '#FFB800',
    background: '#F7F9FB',
    surface: '#FFFFFF',
    text: {
      primary: '#0A2540',
      secondary: '#5A6B7B',
      inverse: '#FFFFFF',
    },
    border: '#E2E8F0',
    error: '#DC2626',
    success: '#16A34A',
  },
  typography: {
    fontFamily: "'Inter', system-ui, sans-serif",
    weights: {
      regular: 400,
      medium: 500,
      semibold: 600,
      bold: 700,
    },
    headings: {
      h1: '2.5rem/1.2',
      h2: '2rem/1.25',
      h3: '1.5rem/1.3',
      h4: '1.25rem/1.35',
    },
  },
  spacing: {
    grid: 8,
    scale: [0, 0.5, 1, 1.5, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24, 32],
  },
  borderRadius: {
    sm: '8px',
    default: '12px',
    lg: '16px',
    full: '9999px',
  },
  shadows: {
    sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
    default: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
    lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
  },
} as const;

export type DesignToken = typeof designTokens;

import { createContext, createElement, useContext, type ReactNode } from 'react';

const CatalogContext = createContext<{ messages: unknown; locale: string }>({
  messages: {}, locale: 'en',
});

// Plain field/error messages used by these tests; the browser exercises next-intl itself.
export function NextIntlClientProvider({ children, messages, locale }: {
  children: ReactNode; messages: unknown; locale: string; timeZone?: string;
}) {
  return createElement(CatalogContext.Provider, { value: { messages, locale } }, children);
}

export function useLocale() {
  return useContext(CatalogContext).locale;
}

export function useTranslations(namespace: string) {
  const { messages } = useContext(CatalogContext);
  return (key: string, values?: Record<string, string | number>) => {
    const path = `${namespace}.${key}`;
    let value: unknown = messages;
    for (const part of path.split('.')) {
      if (typeof value !== 'object' || value === null || !(part in value)) {
        throw new Error(`Missing catalog message: ${path}`);
      }
      value = (value as Record<string, unknown>)[part];
    }
    if (typeof value !== 'string') throw new Error(`Non-text catalog message: ${path}`);
    return value.replace(/\{(\w+)\}/g, (_, name: string) => String(values?.[name] ?? ''));
  };
}

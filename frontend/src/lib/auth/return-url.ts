/** Keep post-login redirects on a protected route in the active locale. */
export function safeAuthReturnUrl(locale: string, candidate?: string | null): string {
  const fallback = `/${locale}/`;
  if (!candidate || !candidate.startsWith(`/${locale}/`) || candidate.includes('\\')) {
    return fallback;
  }

  try {
    const parsed = new URL(candidate, 'https://bookly.invalid');
    if (parsed.origin !== 'https://bookly.invalid' || !parsed.pathname.startsWith(`/${locale}/`)) {
      return fallback;
    }
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return fallback;
  }
}

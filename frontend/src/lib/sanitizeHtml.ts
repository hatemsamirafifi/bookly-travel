/**
 * Minimal, dependency-free HTML sanitizer for untrusted rich-text rendered
 * via `dangerouslySetInnerHTML` (blog bodies, CMS static pages).
 *
 * This is DEFENSE-IN-DEPTH only: the backend (HtmlSanitizerService) already
 * applies a full allow-list sanitizer before persisting/serving CMS HTML.
 * This helper runs on the client and during SSR (no DOM required) and strips
 * the classic XSS vectors an allow-list may have missed:
 *
 * - dangerous elements (script/iframe/object/embed/svg/style) together with
 *   their inner payloads;
 * - inline `on*` event-handler attributes (onerror, onload, onclick, ...);
 * - `javascript:` / `vbscript:` / `data:text/html` URLs in href/src.
 */

const DANGEROUS_ELEMENTS = [
  'script',
  'iframe',
  'object',
  'embed',
  'svg',
  'style',
] as const;

/** Simple named entities that can hide a scheme delimiter past naive checks. */
const NAMED_ENTITIES: Record<string, string> = {
  '&colon;': ':',
  '&sol;': '/',
  '&NewLine;': '\n',
  '&Tab;': '\t',
  '&nbsp;': ' ',
};

/**
 * Decode HTML entities (numeric and a curated named subset) repeatedly, so
 * doubly-encoded payloads such as `&amp;#106;avascript:` are resolved too.
 */
function decodeEntities(value: string): string {
  let decoded = value;
  for (let round = 0; round < 3; round += 1) {
    let resolved = decoded
      .replace(/&amp;/g, '&')
      .replace(/&#x([0-9a-f]+);?/gi, (_m, hex: string) =>
        String.fromCodePoint(parseInt(hex, 16))
      )
      .replace(/&#(\d+);?/g, (_m, dec: string) =>
        String.fromCodePoint(parseInt(dec, 10))
      );
    for (const [entity, char] of Object.entries(NAMED_ENTITIES)) {
      resolved = resolved.split(entity).join(char);
    }
    if (resolved === decoded) break;
    decoded = resolved;
  }
  return decoded;
}

/**
 * True when the attribute URL resolves to a scheme that can execute script:
 * `javascript:`, `vbscript:`, or `data:text/html`. Other `data:` payloads
 * (e.g. `data:image/png;base64,...`) stay allowed.
 */
function isUnsafeUrl(raw: string): boolean {
  const decoded = decodeEntities(raw)
    // Strip leading whitespace/control bytes that browsers ignore in URLs.
    .replace(/^[\s\u0000-\u0020]+/, '')
    .toLowerCase();

  return /^(?:javascript|vbscript|data:text\/html)/.test(decoded);
}

export function sanitizeHtml(html: string): string {
  let output = html ?? '';

  // 1. Remove dangerous elements complete with their content. The unclosed
  //    variant removes everything after a stray opening tag so a script
  //    payload quoted after backend sanitization cannot survive as text.
  for (const element of DANGEROUS_ELEMENTS) {
    output = output.replace(
      new RegExp(`<${element}\\b[^>]*>[\\s\\S]*?<\\/${element}\\s*>`, 'gi'),
      ''
    );
    output = output.replace(
      new RegExp(`<${element}\\b[^>]*>[\\s\\S]*$`, 'gi'),
      ''
    );
    output = output.replace(
      new RegExp(`</?${element}\\b[^>]*>`, 'gi'),
      ''
    );
  }

  // 2. Sanitize each remaining tag: drop `on*` event-handler attributes and
  //    neutralize unsafe URLs in href/src.
  output = output.replace(
    /<([a-zA-Z][a-zA-Z0-9]*)((?:\s[^<>]*)?)(\/?)>/g,
    (tag, _name: string, attrs: string, selfClose: string) => {
      if (!attrs) return tag;

      attrs = attrs.replace(/\son[a-z0-9_-]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]*)/gi, '');

      attrs = attrs.replace(
        /(\s(?:href|src)\s*=\s*)("([^"]*)"|'([^']*)'|([^\s>]*))/gi,
        (match, prefix: string, value: string) => {
          const raw =
            value[0] === '"' || value[0] === "'"
              ? value.slice(1, -1)
              : value;
          return isUnsafeUrl(raw) ? '' : `${prefix}${value}`;
        }
      );

      return `<${_name}${attrs}${selfClose}>`;
    }
  );

  return output;
}

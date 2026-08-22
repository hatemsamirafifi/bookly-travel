# Blog body: HTML via Filament RichEditor

Blog post bodies are stored as HTML in the JSONB-localized `body` column, edited via
Filament's built-in `RichEditor` (no extra package). The frontend renders the body via
`dangerouslySetInnerHTML` in a React server component, matching the only existing
`dangerouslySetInnerHTML` precedent (StructuredData.tsx JSON-LD output).

We rejected markdown (adds a frontend dep, no precedent) and Tiptap block JSON (heaviest,
needs a block editor package and custom renderer). HTML via RichEditor is the simplest
path to "rich content" with no new dependency. XSS risk is mitigated by Filament's
RichEditor sanitizing on input; the frontend renders trusted-admin-authored HTML.
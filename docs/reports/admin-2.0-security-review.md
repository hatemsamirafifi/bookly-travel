# Bookly Travel — Admin 2.0 Security Review & Threat Model

**Document Status:** Complete (Pre-Implementation Security Baseline & Controls Plan)  
**Date:** 2026-11-01  
**Spec Reference:** [`specs/035-admin-2.0/spec.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/spec.md)

---

## 1. Authentication & Session Controls

1. **Admin Panel Access Enforcement:**
   - [`User::canAccessPanel()`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Models/User.php#L134-L137) must check `$this->role === 'admin' && ($this->status ?? 'active') === 'active'`.
   - Suspended or deactivated admin accounts (`status !== 'active'`) are immediately rejected at both the Filament session boundary and the Sanctum API boundary (`AdminPermissionMiddleware`).
2. **Session & Token Revocation:**
   - Suspending or deactivating an admin or traveler revokes all active Sanctum tokens (`$user->tokens()->delete()`) and invalidates active sessions.
3. **Zero Credential Exposure:**
   - `password`, `remember_token`, R2 storage secrets, Stripe keys, and AI translation API keys (`GEMINI_API_KEY`) are strictly excluded from all Filament forms, infolists, API responses, and audit log payloads.

---

## 2. Authorization & Privilege Escalation Prevention

1. **Granular Capability Matrix:**
   - Preserves all 10 existing `AdminAuthorizationService::FLAGS` and adds granular module/action permissions (`manage_media`, `manage_files`, `delete_files`, `manage_finance`, `export_data`, `manage_roles`, `manage_translations`).
2. **Anti-Privilege-Escalation Guard:**
   - An administrator editing roles or user permissions cannot grant any permission flag that the acting administrator does not personally hold (`AdminAuthorizationService::assertCanGrant($actor, $requestedFlags)`).
3. **Last Active Super Admin Protection:**
   - Deactivating, suspending, deleting, or stripping `super_admin` permissions from the last active Super Admin account throws a domain validation error (`422 Unprocessable Entity`).

---

## 3. File & Object Storage Security (Media Library & R2 File Manager)

1. **Strict MIME & Magic-Byte Validation:**
   - Every upload validates actual content MIME type via PHP `finfo_file` alongside file extension and size caps.
   - Executable, script, HTML, and SVG uploads with embedded scripts (`application/x-httpd-php`, `text/html`, `application/javascript`, `application/x-msdownload`, `.php`, `.phar`, `.sh`, `.exe`, `.html`, `.svg`) are unconditionally rejected.
2. **Path Traversal & Bucket Isolation:**
   - Object keys and virtual folder paths are normalized through `MediaStorageService::sanitizePath()`. Any path containing `..`, `\`, null bytes, or control characters is rejected before any Flysystem call.
   - The File Manager operates strictly against `MediaFolder` and `MediaAsset` records within Bookly's managed storage disk (`r2`), never exposing `storage_path()`, `base_path()`, or host OS directories.
3. **Referential Integrity on Deletion:**
   - Deleting a `MediaAsset` or File Manager object checks `media_usages`. Referenced assets cannot be permanently deleted without either detaching usage or selecting a valid replacement asset.

---

## 4. Content Security & Server-Side HTML Sanitization

1. **Allowlist HTML Sanitization (`HtmlSanitizerService`):**
   - Built on `symfony/html-sanitizer` (`v7.4.13` installed).
   - Allowed tags: `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `br`, `hr`, `strong`, `b`, `em`, `i`, `u`, `ul`, `ol`, `li`, `blockquote`, `pre`, `code`, `a`, `img`, `table`, `thead`, `tbody`, `tr`, `th`, `td`, `figure`, `figcaption`, `span`, `div`.
   - Allowed attributes: `href`, `target`, `rel` on `a` (restricted to `http://`, `https://`, `mailto:`, and relative `/` paths; forces `rel="noopener noreferrer"` when `target="_blank"`); `src`, `alt`, `title`, `width`, `height`, `loading` on `img` (restricted to `http://`, `https://`, and `/storage/` URLs); `colspan`, `rowspan` on `th`/`td`; safe `class` / text-alignment attributes.
   - Blocked unconditionally: `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<input>`, `<svg>`, `<math>`, `<style>`, all `on*` event attributes (`onerror`, `onload`, `onclick`), and `javascript:` / `data:` / `vbscript:` URIs.
2. **Applied Across All Rich Text Boundaries:**
   - Executed server-side in `UpdateBlogPostAction`, `UpdateStaticPageAction`, `TourService` / `TourResource`, and `TranslateContentFieldJob` before database persistence.

---

## 5. Preview Token, Draft Visibility & Audit Governance

1. **Signed Preview Tokens:**
   - Reuses and extends [`PreviewTokenService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Services/PreviewTokenService.php) with slug-binding, content-type binding (`blog_post` | `static_page`), HMAC-SHA256 verification, and 30-minute expiration.
   - Public listing and detail endpoints never return `draft` or future-scheduled records.
2. **Immutable Audit Trail:**
   - All administrative mutations across Admin Users, Roles, Travelers, Partners, Tours, Bookings, Reviews, Finance exports, Media Library, File Manager, Blog, Static Pages, Revisions, Translations, and Settings write append-only records to `governance_audit_logs` via `GovernanceAuditService`.


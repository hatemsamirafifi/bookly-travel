# Bookly Travel — Admin 2.0 Database Impact & Schema Strategy

**Document Status:** Complete  
**Date:** 2026-11-01  
**Spec Reference:** [`specs/035-admin-2.0/data-model.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/data-model.md)  
**Migration Policy:** 100% Additive, Non-Destructive, Reversible (`down()` supported on all migrations)

---

## 1. Existing Tables Reused Without Duplication

| Domain | Existing Table | How Admin 2.0 Reuses / Extends It |
|---|---|---|
| **Users & Travelers** | `users` | Reused for both Admin User Management (`role = 'admin'`) and Traveler Management (`role = 'traveler'`). Additive columns for account status (`status`, `suspended_at`, `suspension_reason`). |
| **Admin Permissions** | `admin_permissions` | Preserved for per-user capability flags (`flags` JSONB). Complemented by `admin_roles` and `admin_role_user` for role templates. |
| **Governance Audit** | `governance_audit_logs` | Reused as the single immutable governance audit table. Additive nullable `ip_address`, `user_agent`, and `request_id` columns (or stored in `metadata` JSONB). |
| **Blog CMS** | `blog_posts`, `blog_categories`, `author_profiles`, `blog_post_tours` | Reused directly. Additive `og_image_url` and `version` columns on `blog_posts`; new `blog_tags` and `blog_post_tags` pivot table. |
| **Static Pages CMS** | `static_pages` | Reused directly. Additive `meta_title` (JSONB), `featured_image_url`, `og_image_url`, `scheduled_at`, and `version` columns. |
| **Tours & Media** | `tours`, `tour_translations`, `tour_translation_states`, `tour_media`, `categories` | Reused directly. `tour_media` links optionally to `media_assets` via nullable `media_asset_id`. |
| **Finance** | `payments`, `financial_ledger_entries`, `stripe_webhook_events` | Reused strictly read-only in Filament Finance resources. Zero schema mutations to ledger tables. |

---

## 2. New & Additive Database Migrations

### 2.1 `admin_roles` & `admin_role_user` + `users` Status Columns
- **Table `admin_roles`:**
  - `id` (PK), `slug` (unique, 60), `name` (100), `description` (255, nullable), `permissions` (JSONB), `is_system` (boolean, default false), `timestamps`.
- **Table `admin_role_user`:**
  - `id` (PK), `user_id` (FK `users.id` cascade), `admin_role_id` (FK `admin_roles.id` cascade), `assigned_by` (FK `users.id` nullOnDelete), `created_at`. Unique `(user_id, admin_role_id)`.
- **Additive on `users`:**
  - `status` (`string(20)`, default `'active'`, indexed: `active`, `inactive`, `suspended`)
  - `suspended_at` (`timestamp`, nullable)
  - `suspension_reason` (`text`, nullable)

### 2.2 Media Library & File Manager (`media_folders`, `media_assets`, `media_tags`, `media_asset_tag`, `media_usages`)
- **Table `media_folders`:**
  - `id` (PK), `name` (120), `slug` (120), `parent_id` (self-FK `media_folders.id` restrictOnDelete, nullable), `path` (string 500, unique, indexed), `disk` (string 30, default `'r2'`), `created_by` (FK `users.id` nullOnDelete), `timestamps`, `softDeletes`.
- **Table `media_assets`:**
  - `id` (PK), `uuid` (UUID, unique), `folder_id` (FK `media_folders.id` nullOnDelete, nullable), `disk` (string 30), `object_key` (string 500, unique), `original_filename` (string 255), `display_name` (string 255), `mime_type` (string 100, indexed), `extension` (string 20), `size_bytes` (unsignedBigInteger, indexed), `width` (unsignedInteger, nullable), `height` (unsignedInteger, nullable), `image_format` (string 30, nullable), `variants` (JSONB, nullable — thumbnail/optimized keys), `alt_text` (JSONB, nullable — localized EN/ES/IT or default), `caption` (JSONB, nullable), `description` (text, nullable), `visibility` (string 20, default `'public'`), `checksum_sha256` (string 64, nullable), `processing_status` (string 20, default `'ready'`), `uploaded_by` (FK `users.id` nullOnDelete), `timestamps`, `softDeletes`.
- **Table `media_tags` & `media_asset_tag`:**
  - `media_tags`: `id`, `name` (80), `slug` (80, unique), `timestamps`.
  - `media_asset_tag`: `media_asset_id` (FK), `media_tag_id` (FK), primary `(media_asset_id, media_tag_id)`.
- **Table `media_usages`:**
  - `id` (PK), `media_asset_id` (FK `media_assets.id` restrictOnDelete), `usable_type` (string 80), `usable_id` (unsignedBigInteger), `field_name` (string 80), `created_at`.
  - Indexes: `(media_asset_id)`, `(usable_type, usable_id)`, unique `(media_asset_id, usable_type, usable_id, field_name)`.

### 2.3 CMS Upgrades (`blog_tags`, `blog_post_tags`, Additive Columns on `blog_posts` & `static_pages`)
- **Table `blog_tags` & `blog_post_tags`:**
  - `blog_tags`: `id`, `slug` (80, unique), `name` (JSONB — EN/ES/IT), `timestamps`.
  - `blog_post_tags`: `blog_post_id` (FK cascade), `blog_tag_id` (FK cascade), primary `(blog_post_id, blog_tag_id)`.
- **Additive on `blog_posts`:**
  - `og_image_url` (string 255, nullable), `media_asset_id` (FK `media_assets.id` nullOnDelete, nullable), `version` (unsignedInteger, default 1).
- **Additive on `static_pages`:**
  - `meta_title` (JSONB, nullable), `featured_image_url` (string 255, nullable), `og_image_url` (string 255, nullable), `scheduled_at` (timestamp, nullable), `version` (unsignedInteger, default 1).

### 2.4 Content Revisions (`content_revisions`)
- **Table `content_revisions`:**
  - `id` (PK)
  - `revisionable_type` (string 60 — e.g. `blog_post`, `static_page`)
  - `revisionable_id` (unsignedBigInteger)
  - `version_number` (unsignedInteger)
  - `author_id` (FK `users.id` nullOnDelete, nullable)
  - `reason` (string 255, nullable — e.g. `manual_save`, `autosave`, `restored_from_v2`, `ai_translation`)
  - `previous_snapshot` (JSONB, nullable)
  - `snapshot` (JSONB — full localized content + SEO + status state)
  - `changed_fields` (JSONB)
  - `created_at` (timestamp)
  - Indexes: unique `(revisionable_type, revisionable_id, version_number)`, index `(revisionable_type, revisionable_id, created_at)`.

### 2.5 Field-Level Translation Tracking (`translation_field_states`)
- **Table `translation_field_states`:**
  - `id` (PK)
  - `translatable_type` (string 60 — `blog_post`, `static_page`, `tour`, `blog_category`, `category`)
  - `translatable_id` (unsignedBigInteger)
  - `locale` (string 10 — `es`, `it`)
  - `field_name` (string 80 — e.g. `title`, `excerpt`, `body`, `meta_title`, `meta_description`, `description`, `highlights`, `itinerary`, etc.)
  - `status` (string 30 — `missing`, `pending`, `processing`, `completed`, `outdated`, `failed`, `manually_edited`)
  - `origin` (string 20 — `ai`, `manual`, `none`, default `'none'`)
  - `source_hash` (string 64 — current English field SHA-256 hash)
  - `translated_source_hash` (string 64, nullable — English field SHA-256 hash when this translation was generated/saved)
  - `last_error_code` (string 80, nullable)
  - `attempts` (unsignedSmallInteger, default 0)
  - `last_translated_at` (timestamp, nullable)
  - `updated_by` (FK `users.id` nullOnDelete, nullable)
  - `timestamps`
  - Indexes: unique `(translatable_type, translatable_id, locale, field_name)`, index `(status, locale)`, index `(translatable_type, status)`.

---

## 3. Data Preservation & Rollback Safety
1. No existing column is dropped, renamed, or narrowed.
2. Binary files are never stored in PostgreSQL; all binary objects reside on the configured storage disk (`r2` / local fallback), while PostgreSQL stores only metadata and object keys.
3. Every migration implements a clean `down()` method dropping only the newly added tables/columns in reverse dependency order.


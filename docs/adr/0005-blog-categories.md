# Blog categories: separate blog_categories table

Spec 016 uses a dedicated `blog_categories` table mirroring the tour `Category` shape
(slug unique, name, description, is_active, display_order, sort_order), decoupled from
tour taxonomy. A `BlogCategoryResource` in the Filament `Content` nav group lets admins
manage categories. The `/blog/category/{slug}` route filters posts by category.

We rejected reusing the tour `categories` table with a discriminator because it would couple
editorial blog taxonomy to tour taxonomy and risk cross-filter bugs. We kept the category
name NON-localized (matching the tour Category precedent) — blog category names are short
labels; the post content carries the localization weight.
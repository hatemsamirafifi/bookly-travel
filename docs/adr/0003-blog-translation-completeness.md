# Blog translation gate: EN required, ES/IT optional with fallback

A blog post may be published only when its English (`en`) title and body are non-empty.
Spanish (`es`) and Italian (`it`) are optional. When a requested locale is missing,
the API returns English content with `translation_warning: 'partial_translation'`,
matching the Tour fallback behavior (Tour.php:376-389) and Spec 006:106 / Spec 014 FR-014.

We rejected "all 3 required" because it would block content marketing on translation
throughput and has no precedent (StaticPage allows partial; tours allow partial).
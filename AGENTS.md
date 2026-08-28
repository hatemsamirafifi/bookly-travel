# bookly travel Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-08-22

## Active Technologies
- PHP 8.3+ (Laravel 11) backend; TypeScript 5+ (Next.js 16) frontend (feature/016-blog-travel-insights)
- PostgreSQL (JSONB-localized columns on `blog_posts` + `author_profiles`; `blog_post_tours` pivot for related tours; single `blog_category_id` FK on `blog_posts`; Redis for list/category/sitemap cache + queue) (feature/016-blog-travel-insights)

- PHP 8.3+ (Laravel 11), TypeScript 5+ (Next.js 16) + Laravel 11, Sanctum, Filament 3, Next.js 16, Tailwind CSS, next-intl (fix/014-notifications-vouchers-remediation)

## Project Structure

```text
backend/
frontend/
tests/
```

## Commands

npm test; npm run lint

## Code Style

PHP 8.3+ (Laravel 11), TypeScript 5+ (Next.js 16): Follow standard conventions

## Recent Changes
- feature/016-blog-travel-insights: Added PHP 8.3+ (Laravel 11) backend; TypeScript 5+ (Next.js 16) frontend

- fix/014-notifications-vouchers-remediation: Added PHP 8.3+ (Laravel 11), TypeScript 5+ (Next.js 16) + Laravel 11, Sanctum, Filament 3, Next.js 16, Tailwind CSS, next-intl

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->

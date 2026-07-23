# bookly travel Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-07-04

- PHP 8.2 (Laravel 11.x), TypeScript 5 (Next.js 14) + Laravel Sanctum (main)
- PostgreSQL 15, Redis 7 (main)

- PHP 8.2+ (Laravel), TypeScript 5.x (Next.js 14) + Laravel Sanctum (token auth), Laravel Mail (queued email), Next.js App Router, React Hook Form + Zod (frontend validation) (001-traveler-auth)

## Project Structure

```text
backend/
frontend/
tests/
```

## Commands

npm test; npm run lint

## Shell Execution (Windows — CRITICAL)

This project runs on **Windows**. All `.ps1` scripts MUST be run with Windows PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .specify/scripts/powershell/<script>.ps1 [args]
```

Do NOT use `pwsh`, `bash`, or `sh` — they are not available or will fail.
If `powershell` is unavailable in your environment, skip the scripts and read
files directly from `specs/001-traveler-auth/`.

## Code Style

PHP 8.2+ (Laravel), TypeScript 5.x (Next.js 14): Follow standard conventions

## Recent Changes
- 014-notifications-vouchers: Added PHP 8.4 / Laravel 11 (API-only) backend; TypeScript / Next.js 16 (App Router) frontend; Blade for email + voucher views. + `barryvdh/laravel-dompdf` (voucher PDF), `endroid/qr-code` or inline-SVG QR (current `VoucherService` uses an inline SVG in the voucher view — verify in research), Laravel `Mail`/Mailables, Laravel Sanctum (auth), Redis (queue + cache locks), `next-intl` (frontend i18n), `@tanstack/react-query` (frontend data).
- 008-payment-processing: Added TypeScript 5.x (Next.js 16 frontend), PHP 8.x (Laravel backend) + Next.js 16 (App Router), Laravel (API-only), PostgreSQL, Redis, `stripe/stripe-php` (backend), `@stripe/stripe-js` + `@stripe/react-stripe-js` (frontend)
- 004-traveler-signin: Added PHP 8.2, TypeScript (Next.js 14) + Laravel 11, Laravel Sanctum, React Hook Form, Zod


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->

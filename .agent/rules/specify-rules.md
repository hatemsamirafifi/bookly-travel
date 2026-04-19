# bookly travel Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-04-18

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
- 003-traveler-registration: Added PHP 8.2 (Laravel 11) + TypeScript 5 (Next.js 14) + Laravel Sanctum 4, next-intl 4, Zod 3
- main: Added PHP 8.2+ & TypeScript 5+ with Laravel + Next.js

- 001-traveler-auth: Added PHP 8.2+ (Laravel), TypeScript 5.x (Next.js 14) + Laravel Sanctum (token auth), Laravel Mail (queued email), Next.js App Router, React Hook Form + Zod (frontend validation)

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->

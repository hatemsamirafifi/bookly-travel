# bookly travel Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-04-13

## Active Technologies
- [e.g., Python 3.11, Swift 5.9, Rust 1.75 or NEEDS CLARIFICATION] + [e.g., FastAPI, UIKit, LLVM or NEEDS CLARIFICATION] (main)
- [if applicable, e.g., PostgreSQL, CoreData, files or N/A] (main)

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
- main: Added [e.g., Python 3.11, Swift 5.9, Rust 1.75 or NEEDS CLARIFICATION] + [e.g., FastAPI, UIKit, LLVM or NEEDS CLARIFICATION]

- 001-traveler-auth: Added PHP 8.2+ (Laravel), TypeScript 5.x (Next.js 14) + Laravel Sanctum (token auth), Laravel Mail (queued email), Next.js App Router, React Hook Form + Zod (frontend validation)

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->

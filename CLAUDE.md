# Bookly — Claude Code Guide

## Project Overview

Bookly is a **tours-only marketplace** platform. Travelers search and book tours;
partners list and manage tours; admins moderate and govern the platform.

**Phase 1 scope**: Core booking MVP (11 feature specs).

## Constitution

Read `.specify/memory/constitution.md` before making any architectural decision.
It is the governing document. All code MUST comply with its principles.

## Stack

| Layer | Technology |
|-------|-----------|
| Frontend (public + partner) | Next.js 14, TypeScript, Tailwind CSS |
| Backend (API + admin) | Laravel (API-only), Laravel Filament (admin) |
| Auth | Laravel Sanctum |
| Database | PostgreSQL |
| Cache / Queue | Redis |
| Search | Laravel Scout (Meilisearch later) |
| Storage | Cloudflare R2 |
| CDN | Cloudflare |
| Payments | Stripe |

## Project Structure

```
backend/          → Laravel API + Filament admin dashboard
frontend/         → Next.js 14 (public website + partner dashboard)
docs/             → Project documentation and specification strategy
specs/            → Feature specifications (001 through 011)
.specify/         → Spec Kit configuration and templates
```

## Architecture Rules

- **API-first**: All frontend surfaces consume the Laravel API. No server-rendered
  HTML from Laravel for application views (Filament admin is the exception).
- **Three surfaces**: Public website (Next.js SSR/SSG), Partner dashboard (Next.js),
  Admin dashboard (Filament).
- **Shared backend**: One Laravel app serves all surfaces. Access is separated by
  route groups, middleware, roles, and ownership policies.
- **Modular domains**: Auth, Partners, Tours, Pricing, Availability, Bookings,
  Payments, Reviews, Finance, Notifications, Translation, Admin Operations.

## Code Conventions

### Laravel Backend

- **Thin controllers**: Controllers handle request/response and authorization only.
  No business logic, no direct DB queries.
- **Services/Actions**: All business logic goes in service or action classes.
- **Form Requests**: All write endpoints use Form Request validation. No manual
  validation in controllers.
- **Route groups**: `/api/public/*`, `/api/partner/*`, `/api/admin/*`.
- **Queued jobs**: Notifications, search indexing, image processing, reports.
  All jobs MUST be retry-safe (idempotent).
- **Audit logging**: Required for admin actions, financial transactions, booking
  state changes, and partner management actions.

### Next.js Frontend

- **TypeScript**: Strict mode. No `any` types except in generated/external code.
- **Tailwind CSS**: For all styling. Follow design system tokens.
- **SSR/SSG for public pages**: All public routes must be server-rendered for SEO.
- **Multi-language**: English, Spanish, Italian. Localized routes (`/en/`, `/es/`,
  `/it/`).

### General

- **No secrets in code**: Use environment variables. Never commit `.env` files.
- **Idempotent financial flows**: Payment and booking operations use idempotency
  keys. No duplicate charges or bookings on retry.
- **Test critical paths**: Booking, payment, auth, and tour publishing flows
  MUST have automated tests.

## Phase 1 Decisions

These are binding product decisions. Do not deviate without explicit approval:

- Guest checkout is enabled (no account needed to book)
- One partner = one account (no multi-staff)
- Email notifications only (no SMS/push)
- Manual refunds only (admin via Stripe dashboard)
- Auto-complete bookings after tour date (scheduled job)
- Traveler cancellation allowed before tour date (no penalties)
- Reviews: submit only (rating + comment), no partner replies
- Languages: EN, ES, IT

## Spec Kit Workflow

Feature specs live in `specs/NNN-feature-name/`. Each feature follows:

```
/speckit.specify → /speckit.clarify → /speckit.plan → /speckit.tasks
```

See `docs/specification-strategy.md` for the full plan and all 11 spec prompts.

## Commit Convention

```
feat(NNN): description     → New feature (NNN = spec number)
fix(NNN): description      → Bug fix
docs: description           → Documentation only
refactor(NNN): description → Code restructuring
test(NNN): description     → Test additions
chore: description          → Tooling, config, dependencies
```

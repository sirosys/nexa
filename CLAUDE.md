# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

This is **NEXA**, an internal application for XNet (PT. XPlus Network Indonesia) that will serve as the administrative/operational backbone for an ISP: customer management, product/service catalog, billing, and payments via Xendit, eventually expanding to network integrations (MikroTik, OLT) and broader ISP operations.

The repository is currently a stock Laravel 13 skeleton (fresh `laravel/laravel` install) — no domain code, migrations, or modules have been built yet beyond the default `User` model/migration. `README.md` is a living architecture-planning document (in Indonesian) describing where the project is headed; treat it as the source of truth for intended structure and conventions until actual code establishes otherwise.

## Commands

```bash
# Install PHP deps and bootstrap .env / app key / sqlite db / migrations
composer install
composer run setup      # copies .env, generates key, migrates, npm install, npm build

# Local dev (serves app + queue listener + pail logs + vite, concurrently)
composer run dev

# Run the full test suite (clears config cache first)
composer run test
# equivalent to:
php artisan test

# Run a single test file / filter
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=test_method_name

# Frontend
npm run dev             # vite dev server
npm run build            # production build

# Code style (Laravel Pint)
vendor/bin/pint          # fix
vendor/bin/pint --test   # check only

# Tinker REPL
php artisan tinker
```

Default local DB is SQLite (`database/database.sqlite`), configured via `.env` (`DB_CONNECTION=sqlite`). Tests run against an in-memory SQLite DB (see `phpunit.xml`). The README describes MySQL (InnoDB, utf8mb4) as the target production database engine — expect this to change as the project matures.

## Architecture direction (from README.md)

The project intends to follow these conventions as domain code is added — apply them when creating new features rather than defaulting to fat controllers:

- **Layering**: Form Requests for validation, a Service Layer for business logic, Eloquent for persistence. Keep business logic out of controllers; use dependency injection.
- **Naming convention** per domain concept, e.g. for a `Customer` entity: `CustomerController`, `CustomerService`, `CustomerPolicy`, `CustomerRequest`.
- **API**: customer-facing apps (web/Android/iOS) will consume a versioned REST API under `/api/v1`, kept separate from the Blade admin UI.
- **Frontend tech split** — use the right tool per responsibility, don't reach for Vue by default:
  - Blade: layouts, dashboards, CRUD screens, forms, tables.
  - Alpine.js: lightweight interactivity (modals, dropdowns, accordions, toasts, toggles).
  - Vue: only for genuinely complex components (realtime dashboards, network monitoring views, charts, multi-step wizards).
- **Network integrations**: MikroTik and OLT (HSGQ) integrations are planned behind an adapter/driver pattern so new vendors can be added without touching business modules.
- Soft deletes only where actually needed, not by default; standard Laravel timestamps; foreign keys and appropriate indexes expected on all schema.

## Stack notes

- PHP ^8.3, Laravel ^13.8.
- Frontend build via Vite + `laravel-vite-plugin`, Tailwind CSS v4 (via `@tailwindcss/vite`), entry points `resources/css/app.css` and `resources/js/app.js`.
- `bootstrap/app.php` is the Laravel 13-style single-file app configuration (routing, middleware, exception handling) — there is no `app/Http/Kernel.php`.
- `app/Providers/AppServiceProvider.php` is currently the only registered application provider (see `bootstrap/providers.php`).

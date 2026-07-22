# Directory Platform

A modular, server-rendered directory application built with Laravel, Blade, Alpine.js, and Tailwind CSS.

The project is in active development. Its current foundation provides account registration, provider onboarding classifications, role-based access control, and a normalized directory schema for profiles, agencies, locations, packages, contacts, rates, media, and operational audit records.

## Current capabilities

- Member and provider registration paths
- Independent and agency provider classifications
- Admin, CSR, SEO, and subscriber roles
- Granular permission assignments
- Profile and agency ownership structures
- Configurable listing packages and durations
- Structured locations, attributes, services, contacts, and rates
- Media-processing metadata and package image limits
- Policy-acceptance and audit-log foundations
- Database-backed sessions, cache, and queues
- Automated feature and domain tests

## Technology

- PHP 8.3+
- Laravel 13
- Blade and Alpine.js
- Tailwind CSS
- MySQL or MariaDB in production
- SQLite for local development and automated tests

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

The application will be available at `http://127.0.0.1:8000` by default.

## Quality checks

```bash
./vendor/bin/pint --test
php artisan test
npm run build
composer audit --locked --no-interaction
npm audit --omit=dev --package-lock-only
```

## Security

Never commit environment files, credentials, production data, private uploads, or generated application keys. Configure deployment secrets through the hosting environment.

If you discover a security issue, report it privately to the project maintainer rather than opening a public issue.

## Status

This repository currently contains the Phase 1 application foundation. Public directory pages and complete provider/staff workflows are under development.

# Directory Platform

A modular, server-rendered directory application built with Laravel, Blade, Alpine.js, and Tailwind CSS.

The project is in active development. Its current foundation provides account registration, provider onboarding classifications, role-based access control, and a normalized directory schema for profiles, agencies, locations, packages, contacts, rates, media, and operational audit records.

## Current capabilities

- Member and provider registration paths
- Independent and agency provider classifications
- Admin, CSR, SEO, and subscriber roles
- Granular permission assignments
- Mandatory authenticator MFA and single-use recovery codes for Admin, CSR, and SEO sessions
- Profile and agency ownership structures
- Configurable listing packages and durations
- Structured locations, attributes, services, contacts, and rates
- Server-rendered public homepage, location archives, and profile pages
- Separate VIP, Premium, Basic, and New listing sections
- Stable randomized listing order with scheduled rotation
- SEO titles, descriptions, canonicals, and inventory-aware robots rules
- Three-level city, neighbourhood, and micro-location pages with higher micro-location indexing thresholds
- Database-managed homepage and location copy with audited SEO/Admin editing
- Safe Markdown content blocks below public listings
- Public Call, SMS, WhatsApp, and Telegram profile actions
- Public agency directory and active-profile agency pages
- Privacy-safe public search with location, profile attribute, availability, and service filters
- Privacy-safe related profiles prioritized by sub-location
- Dynamic, visibility-aware XML sitemaps and robots discovery
- SEO-managed redirects, 410 removals, loop protection, and audited activation controls
- Explicit profile slug changes with permanent old-URL history and redirect-chain flattening
- Admin/CSR listing workspace with private profiles and audited lifecycle actions
- Confidential public reporting, urgent safety triage, audited moderation actions, and owner appeals
- Internal age, identity, publishing-rights, and agency-authorization verification history with encrypted evidence references
- Owner profile editing, private-profile viewing, and staff-reviewed renewal requests
- Admin-managed packages, durations, listing rules, agency limits, and media constraints
- Media-processing metadata and package image limits
- Admin/SEO policy drafting, immutable publication, and public policy pages
- Versioned policy acceptance evidence across registration, profile submission, media upload, and renewal
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
composer serve
```

Run the database queue worker in a second terminal so quarantined media is processed and published after approval:

```bash
composer queue
```

The public directory will be available at `http://127.0.0.1:8000` by default. Location pages use routes such as `/nairobi-escorts` and `/nairobi/westlands-escorts` after those locations have been created by an SEO or Admin account.

The development commands raise PHP's upload, POST, and memory limits so the configured 50 MB image allowance and high-resolution image processing can operate. Production PHP-FPM or web-server configuration must provide at least `upload_max_filesize=50M`, `post_max_size=55M`, and `memory_limit=512M`.

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

Privileged MFA is enabled by default. Set `PRIVILEGED_MFA_ENFORCED=false` only for a deliberately isolated environment; production should leave it enabled.

If you discover a security issue, report it privately to the project maintainer rather than opening a public issue.

## Status

This repository contains the Phase 1 application foundation, manual provider activation and renewal workflows, secure media pipeline, SEO directory configuration, policy lifecycle management, and the first public directory experience. Further moderation, search, and administrative tooling remains under development.

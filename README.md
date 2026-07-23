# Directory Platform

A modular, server-rendered directory application built with Laravel, Blade, Alpine.js, and Tailwind CSS.

The project is in active development. Its current foundation provides account registration, provider onboarding classifications, role-based access control, and a normalized directory schema for profiles, agencies, locations, packages, contacts, rates, media, and operational audit records.

## Current capabilities

- Member and provider registration paths
- Independent and agency provider classifications
- Admin, CSR, SEO, and subscriber roles
- Granular permission assignments
- Google SSO for pre-existing Admin accounts with verified-email identity linking
- Optional Admin-controlled authenticator MFA and single-use recovery codes for privileged sessions
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
- Readiness monitoring for database, cache, scheduler, queues, disk, and backup freshness
- Scheduled native database backups with compression, checksum records, verification, and retention pruning
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

## Production operations

Run one scheduler trigger every minute and supervise at least one persistent queue worker:

```bash
* * * * * cd /var/www/directory-platform && php artisan schedule:run
php artisan queue:work --queue=media,default --tries=3 --timeout=120
```

The scheduler records its heartbeat every minute, refreshes expired verification states daily, expires package listings immediately, rotates listing order, and creates a verified database backup nightly. `composer backup` creates an on-demand backup. MySQL/MariaDB requires `mysqldump`, PostgreSQL requires `pg_dump`, and SQLite requires `sqlite3`.

Configure `OPS_BACKUP_DISK` as private, encrypted, off-host storage in production. Database backups do not replace a separate versioned backup of private and public media. Restrict temporary storage to the application user and use encrypted host volumes. Test restoration into an isolated environment on a schedule; verify the checksum, import the archive, run migrations in dry-run review, execute the automated test suite, and record the drill outside the production database.

Monitoring endpoints:

- `/up` — process liveness
- `/health/ready` — status-only readiness response
- `/admin/system-health` — Admin-only operational detail

Before a production release, run:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
composer launch-check
```

The launch check fails closed when key security, Google Admin SSO, policy, enabled MFA, scheduler, backup, HTTPS, queue, cache, session, database, or storage requirements are missing. Deployments should keep the previous release artifact and database compatibility window available for rollback. Do not roll back a database destructively; restore into an isolated database first and follow the incident plan.

## Security

Never commit environment files, credentials, production data, private uploads, or generated application keys. Configure deployment secrets through the hosting environment.

Privileged authenticator MFA is disabled by default and can be enabled from the Admin directory settings. When enabled, Admin, CSR, and SEO accounts must enroll and pass an MFA challenge. Disabling enforcement preserves existing enrollment and recovery-code data so the control can be re-enabled without resetting accounts.

Google Admin SSO requires `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and the callback URI registered as `GOOGLE_REDIRECT_URI`. Set `GOOGLE_ADMIN_ALLOWED_DOMAINS` to a comma-separated list when sign-in must be restricted further. Google sign-in never creates users or grants roles: the verified Google email must already belong to an Admin account, and the Google subject identifier is permanently linked on first successful sign-in.

If you discover a security issue, report it privately to the project maintainer rather than opening a public issue.

## Status

This repository contains the Phase 1 application foundation, manual provider activation and renewal workflows, secure media pipeline, SEO directory configuration, policy lifecycle management, and the first public directory experience. Further moderation, search, and administrative tooling remains under development.

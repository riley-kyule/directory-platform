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
- Readiness monitoring for database, cache, scheduler, queues, disk, and database/media backup freshness
- Scheduled native database and media backups with compression, checksum records, verification, and retention pruning
- Admin-editable site identity (website title, support email) that feeds page titles, structured data, and policy content
- Generic, portable starting legal policies (Terms, Privacy, Provider, Media, Agency) seeded and published by default
- Optional, Admin-toggleable 18+ consent gate with a search-crawler exemption so it never blocks indexing
- Cron-driven scheduler and queue worker (`queue:work --stop-when-empty`) — no persistent process required, so it runs on shared hosting with no supervisor/systemd
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

Or run both together as one command: `composer dev`. Either way this is two lightweight PHP processes (roughly 200-250MB combined at idle) — no Vite watcher and no log-tailing process by default, since most work in this app doesn't need either running continuously. If you're actively editing CSS/JS/Blade and want live-reloading assets, an auto-reloading queue worker, and tailed logs, use `composer dev-full` instead (the same four-process bundle this project used to default to); otherwise run `npm run build` once after a frontend change.

To exercise the full application with realistic data — every profile status, all three packages, independent and agency ownership, moderation/verification/appeals, search-term logging, and a redirect — seed the demo dataset (never runs against `APP_ENV=production`, and safe to re-run: it purges and recreates its own accounts each time):

```bash
php artisan db:seed --class=DemoDataSeeder
```

The public directory will be available at `http://127.0.0.1:8000` by default. Location pages use routes such as `/nairobi-escorts` and `/nairobi/westlands-escorts` after those locations have been created by an SEO or Admin account.

The development commands raise PHP's upload and POST limits so the configured 50 MB image allowance can operate, and cap memory at 256M — enough headroom for normal test uploads without reserving production-sized memory for a local session. Production PHP-FPM or web-server configuration must still provide at least `upload_max_filesize=50M`, `post_max_size=55M`, and `memory_limit=512M`, since real deployments need to handle the full high-resolution image processing ceiling (`dev-full` uses that same 512M locally, for testing near that ceiling).

## Quality checks

```bash
./vendor/bin/pint --test
php artisan test
npm run build
composer audit --locked --no-interaction
npm audit --omit=dev --package-lock-only
```

## Production operations

Trigger the scheduler every minute, and run the queue worker via cron rather than as a supervised persistent process — `queue:work --stop-when-empty` drains whatever is queued and exits on its own, so nothing needs a systemd unit or supervisor to stay alive:

```bash
* * * * * cd /var/www/directory-platform && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/directory-platform && flock -n /tmp/directory-platform-cron-queue.lock php artisan queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=45 >> storage/logs/queue-worker.log 2>&1
```

`deploy/install-cron.sh` installs both lines for you idempotently (safe to re-run; it replaces its own previous entries rather than duplicating them) and is called automatically by `deploy/bootstrap.sh` — on a fresh cPanel install, this means the scheduler and queue worker are live with no manual cron configuration. If your host has no `crontab` command, the script prints the two lines to paste into cPanel's "Cron Jobs" UI instead. Set `PHP_BIN` if `php` on the account's `$PATH` isn't the version selected in MultiPHP Manager.

The scheduler records its heartbeat every minute, refreshes expired verification states daily, expires package listings immediately, rotates listing order, and creates verified database and media backups nightly (`system:backup` at 02:30, `system:backup-media` at 03:00). `composer backup` creates an on-demand database backup; `php artisan system:backup-media` does the same for public profile media. MySQL/MariaDB requires `mysqldump`, PostgreSQL requires `pg_dump`, and SQLite requires `sqlite3`; the media backup shells out to `tar`.

Configure `OPS_BACKUP_DISK` as private, encrypted, off-host storage in production. Restrict temporary storage to the application user and use encrypted host volumes. Test restoration into an isolated environment on a schedule; verify the checksum, import the archive, run migrations in dry-run review, execute the automated test suite, and record the drill outside the production database.

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

On shared cPanel hosting with SSH but no root, `deploy/` has ready-to-run scripts for exactly this atomic-release-plus-rollback pattern, using plain symlinks in the account's home directory instead of systemd/WHM:

```bash
./deploy/bootstrap.sh    # once: creates releases/+shared/, backs up an existing public_html
# put a production .env at ~/directory-platform/shared/.env, then:
DEPLOY_REPO_URL=git@github.com:riley-kyule/directory-platform.git ./deploy/deploy.sh
./deploy/rollback.sh     # or ./deploy/rollback.sh <release-timestamp>
```

Requires non-interactive git access from the server (an SSH deploy key or a token-embedded HTTPS remote), PHP 8.3+ selected in MultiPHP Manager with the upload/memory limits above set in MultiPHP INI Editor, and Node available for `npm ci && npm run build` (build assets locally/in CI instead if the plan has no Node). `deploy.sh` runs `composer launch-check` before activating a release and leaves the previous one live if it fails. `rollback.sh` only ever moves symlinks and re-warms caches — it never touches the database; see the destructive-rollback guidance above. Each script's own header comment has the full details.

## Security

Never commit environment files, credentials, production data, private uploads, or generated application keys. Configure deployment secrets through the hosting environment.

Privileged authenticator MFA is disabled by default and can be enabled from the Admin directory settings. When enabled, Admin, CSR, and SEO accounts must enroll and pass an MFA challenge. Disabling enforcement preserves existing enrollment and recovery-code data so the control can be re-enabled without resetting accounts.

Google Admin SSO requires `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and the callback URI registered as `GOOGLE_REDIRECT_URI`. Set `GOOGLE_ADMIN_ALLOWED_DOMAINS` to a comma-separated list when sign-in must be restricted further. Google sign-in never creates users or grants roles: the verified Google email must already belong to an Admin account, and the Google subject identifier is permanently linked on first successful sign-in.

Session cookies default to `secure` automatically whenever `APP_URL` starts with `https://`, so a forgotten `SESSION_SECURE_COOKIE` can't silently ship insecure cookies on a real domain — set it explicitly only if you need to override that inference.

The app trusts all proxies (`at: '*'`) for `X-Forwarded-*` headers so it resolves the visitor's real IP/scheme correctly behind Cloudflare or any other TLS-terminating proxy, and `APP_URL=https://…` forces the URL generator to always emit `https://` links regardless of what scheme the proxy reports internally. Trusting all proxies for header parsing is not the same as an access-control allowlist — for defense in depth, also restrict origin traffic to Cloudflare's IP ranges at the host firewall level, or enable Cloudflare's "Authenticated Origin Pulls," if your hosting plan allows it.

If you discover a security issue, report it privately to the project maintainer rather than opening a public issue.

## Site identity and legal policies

The website title and support email shown in the header, page titles, structured data, and legal policies are editable from Admin → Settings, and fall back to the `APP_NAME` environment value when left blank. Legal policy content (Terms, Privacy, Provider, Media, Agency) is authored with `{{platform_name}}` / `{{support_email}}` tokens, substituted at render time — renaming the site or changing the support address updates every policy automatically, without re-editing them.

A generic starting policy is seeded and published for each type by default (`DirectoryDefaultsSeeder` / `database/seeders/PolicyTemplates.php`), so a fresh install has working, non-empty legal pages immediately. **This is a reasonable starting draft, not a substitute for review by qualified legal counsel** — particularly for an adult-services directory, where age-verification, advertising, and anti-trafficking law vary by jurisdiction. Review and republish each policy from Admin → Policies before relying on it for a real launch.

An optional 18+ consent gate can be turned on from Admin → Settings. When enabled, first-time visitors see an interstitial before any listing content; confirmation is remembered for a year via cookie. Known search-engine crawlers (by user agent) always bypass the gate so it never affects indexing, and policy pages, sitemaps, and `robots.txt` are never gated in the first place.

## Status

This repository contains the Phase 1 application foundation, manual provider activation and renewal workflows, secure media pipeline, SEO directory configuration, policy lifecycle management, and the first public directory experience. Further moderation, search, and administrative tooling remains under development.

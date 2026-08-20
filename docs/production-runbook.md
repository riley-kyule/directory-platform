# Production Runbook

This is the operating procedure for each deployed directory instance. Replace every example path and domain with the values for that specific primary or addon domain. Never share an application root, `.env`, storage directory, database, cache prefix, or cron lock between domains.

## 1. Required production state

- PHP 8.3 or newer for both the web handler and CLI commands.
- `APP_ENV=production`, `APP_DEBUG=false`, and an HTTPS `APP_URL` matching the canonical domain.
- Separate production database, database-backed or Redis cache/session storage, and an asynchronous queue.
- A delivery mailer; `log` and `array` are not production mailers.
- A real support email, published policy versions, and Google staff SSO credentials when SSO enforcement is enabled.
- Scheduler and queue-worker cron entries installed by `deploy/install-cron.sh`.
- Private, preferably encrypted off-host database and media backup storage.
- Google Search Console ownership token configured under Admin → Settings and `/sitemap.xml` submitted after verification.

Run the authoritative gate from the active release:

```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php
cd "$HOME/apps/example.com/current"
"$PHP_BIN" artisan system:launch-check --production
```

Errors block launch. Advisories identify work that should be closed but does not make a code release unsafe by itself.

## 2. Deploying

Use a distinct application root and document root for every domain.

Primary-domain example:

```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php \
DEPLOY_APP_ROOT="$HOME/apps/example.com" \
DEPLOY_DOCROOT="$HOME/public_html" \
DEPLOY_MANAGE_DOCROOT=1 \
DEPLOY_REPO_URL=https://github.com/OWNER/REPOSITORY.git \
DEPLOY_BRANCH=main \
bash deploy/deploy.sh
```

Addon-domain example:

```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php \
DEPLOY_APP_ROOT="$HOME/apps/addon.example" \
DEPLOY_DOCROOT="$HOME/addon.example" \
DEPLOY_MANAGE_DOCROOT=1 \
DEPLOY_REPO_URL=https://github.com/OWNER/REPOSITORY.git \
DEPLOY_BRANCH=main \
bash deploy/deploy.sh
```

The deploy is atomic: it prepares a release, runs migrations and the launch gate, and only then moves the active symlink. A failed gate leaves the previous release serving traffic. Database migrations are forward-only during routine rollback; never reverse production migrations merely to match an older code release.

## 3. Post-deploy verification

Within ten minutes of every deployment:

1. Confirm `/up` returns HTTP 200 and `/health/ready` reports `ready`.
2. Open Admin → System health. Resolve every critical result and investigate warnings.
3. Test an Admin and CSR Google login in a private browser window.
4. Open the homepage, a location page, a profile, search, and `/sitemap.xml`.
5. Submit one non-sensitive test queue job where practical and confirm the queue-worker heartbeat remains current.
6. Confirm the deployed commit shown by the deploy output matches the intended commit.
7. Run the bounded public load smoke. Start small on shared hosting:

```bash
"$PHP_BIN" artisan system:load-smoke \
  --production \
  --base-url=https://example.com \
  --requests=40 \
  --concurrency=5 \
  --max-p95-ms=1500
```

This is a smoke test, not a capacity test. Never increase beyond the command's built-in bounds or run sustained load against shared production hosting without provider approval.

## 4. Daily and weekly operations

Daily:

- Review Admin → System health for queue delay, failed jobs, stale heartbeats, overdue moderation, backup freshness, disk, and mail warnings.
- Clear urgent reports before the configured SLA and review escalation-delivery failures immediately.
- Confirm the latest database and media backups are completed and verified.

Weekly:

- Review SEO → SEO Audit and Search Insights. Fix indexability conflicts, duplicate metadata, orphan pages, thin content, stale reviews, and high-view/low-contact profiles.
- Review Google Search Console indexing, sitemap processing, manual actions, security issues, Core Web Vitals, and query/page performance.
- Review failed jobs before retrying them; understand the failure first.
- Confirm remaining disk capacity and prune only through documented retention commands.

Monthly:

- Perform an isolated restore drill with `system:restore-drill` and retain its evidence.
- Review privileged users, roles, suspended accounts, SSO links, and MFA enrollment where enforced.
- Review legal copy and privacy-retention settings with the responsible owner.
- Test a rollback in a non-production environment.

## 5. Incident response

Severity:

- **SEV-1:** data exposure, account compromise, unlawful content requiring immediate removal, destructive data loss, or full outage.
- **SEV-2:** staff login failure, broken moderation/queue processing, stale backups, major public feature failure, or widespread incorrect pages.
- **SEV-3:** isolated listing/UI/SEO defect without immediate safety or availability impact.

For every incident:

1. Record start time, reporter, affected domains, symptoms, and the last known good commit.
2. Contain first. Suspend compromised users, deactivate affected profiles, disable the failing integration, or roll back code as appropriate.
3. Preserve logs and audit evidence. Do not edit production records directly unless the normal audited workflow cannot contain the incident.
4. Diagnose using application logs, Admin → System health, queue failures, deployment output, and database/host metrics.
5. Recover, verify the public and privileged paths, and monitor for recurrence.
6. Document cause, impact, timeline, remediation, and preventive work. For privacy or legal events, escalate to qualified counsel and follow applicable notification deadlines.

### Code regression rollback

```bash
cd "$HOME/apps/example.com/current"
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php \
DEPLOY_APP_ROOT="$HOME/apps/example.com" \
DEPLOY_DOCROOT="$HOME/public_html" \
DEPLOY_MANAGE_DOCROOT=1 \
bash deploy/rollback.sh
```

Rollback moves symlinks only. Afterward, run the readiness checks and smoke paths again. If the incident involves a migration incompatibility, deploy a forward corrective migration; do not run destructive rollback commands against the live database.

### Queue or scheduler outage

1. Confirm both cron lines exist and use the correct `PHP_BIN` and application root.
2. Run `"$PHP_BIN" artisan schedule:list` and `"$PHP_BIN" artisan queue:failed`.
3. Run one bounded drain: `"$PHP_BIN" artisan queue:work --queue=media,default --stop-when-empty --max-time=50 --tries=3 --timeout=45`.
4. Recheck queue-worker, scheduler, moderation, and privacy heartbeats.
5. Retry failed jobs only after correcting the cause.

### Staff SSO outage

1. Check the exact callback URI in Google and production `.env`.
2. Confirm the staff user is active, has Admin/CSR/SEO, and the Google email matches the pre-authorized account.
3. Verify system time, HTTPS URL generation, session/cache health, and Google client credentials.
4. Do not enable public self-provisioning or grant a role from Google claims as a workaround.

### Suspected account or data compromise

1. Suspend affected accounts and revoke exposed provider/API credentials.
2. Rotate application and service secrets deliberately; understand that rotating `APP_KEY` invalidates encrypted data and sessions.
3. Preserve relevant logs/backups, scope accessed records, and obtain legal/privacy guidance.
4. Force affected sessions and credentials to be replaced before restoring access.

## 6. Backup and recovery

- Never restore over the live database as a test.
- Verify archive checksum and decryptability before importing.
- Restore into the configured isolated restore-drill database.
- Confirm core tables, pending migrations, record counts, and representative ownership/profile/media relationships.
- For a real disaster, preserve the damaged environment, create a fresh backup if safe, document the selected recovery point, restore into clean infrastructure, run migrations and launch checks, then switch traffic only after verification.

Recovery is complete only when public pages, Admin/CSR access, queue processing, media, policies, moderation history, backups, and Search Console/sitemap behavior have been checked.

## 7. Release evidence

For each production release retain:

- Domain, release timestamp, and Git commit.
- Operator and deploy command parameters excluding secrets.
- Launch-check output and load-smoke summary.
- Migration list and backup identifiers immediately before release.
- Post-deploy verification result, incidents, and rollback decision.

Never store passwords, tokens, raw identity evidence, private uploads, or production `.env` contents in release notes or tickets.

# Production Runbook

This is the operating procedure for each deployed directory instance. Replace every example path and domain with the values for that specific primary or addon domain. Never share an application root, `.env`, storage directory, database, cache prefix, or cron lock between domains.

## 1. Required production state

- PHP 8.3 or newer for both the web handler and CLI commands.
- Executable `ffmpeg` and `ffprobe` binaries if profile video is offered — video processing is fail-closed and rejects every upload without them (photos are unaffected). The launch check flags their absence as an advisory, not a blocker.
- `APP_ENV=production`, `APP_DEBUG=false`, and an HTTPS `APP_URL` matching the canonical domain.
- Separate production database, database-backed or Redis cache/session storage, and an asynchronous queue.
- A delivery mailer; `log` and `array` are not production mailers.
- A real support email, published policy versions, and Google staff SSO credentials when SSO enforcement is enabled (section 3).
- Scheduler and queue-worker cron entries installed by `deploy/install-cron.sh`.
- Private, preferably encrypted off-host database and media backup storage.
- `TRUSTED_PROXIES` limited to the real proxy/CDN ranges and `CANONICAL_HOST` matching the public hostname.
- Google Search Console ownership token configured under Admin → Settings and `/sitemap.xml` submitted after verification.

Run the authoritative gate from the active release:

```bash
PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php
cd "$HOME/apps/example.com/current"
"$PHP_BIN" artisan system:launch-check --production
```

Errors block launch. Advisories identify work that should be closed but does not make a code release unsafe by itself.

## 2. Domain and document-root layouts

Every domain gets three things under its own **application root** (`$DEPLOY_APP_ROOT`, never shared with another domain): `releases/` (one timestamped directory per deploy), `shared/` (`.env`, `storage/`, and public media/branding — everything that must survive a release), and a `current` symlink that `deploy.sh` atomically repoints at the newest good release on every successful deploy. What differs between the three layouts below is only how that release's `public/` directory becomes reachable from the web; the release/shared/current mechanics are identical in all of them, and `deploy.sh`/`rollback.sh` take the exact same three variables regardless of which one you're running.

Run `deploy/bootstrap.sh` once per domain with the variables for its layout, edit `shared/.env`, then run `deploy/deploy.sh` for the first real deploy. **Every deploy after that must reuse the same `DEPLOY_APP_ROOT`/`DEPLOY_DOCROOT`/`DEPLOY_MANAGE_DOCROOT`** — that triplet is what makes the release swap atomic and keeps domains from colliding.

### A. Primary domain, served from `~/public_html`

The account's main domain. cPanel already points `~/public_html` at it; nothing to configure in cPanel itself.

```bash
DEPLOY_APP_ROOT="$HOME/apps/example.com" \
DEPLOY_DOCROOT="$HOME/public_html" \
DEPLOY_MANAGE_DOCROOT=1 \
bash deploy/bootstrap.sh
```

Edit `$HOME/apps/example.com/shared/.env` (fill in every `<<< SET THIS` line), then:

```bash
DEPLOY_PHP_PACKAGE=ea-php83 \
DEPLOY_APP_ROOT="$HOME/apps/example.com" \
DEPLOY_DOCROOT="$HOME/public_html" \
DEPLOY_MANAGE_DOCROOT=1 \
DEPLOY_REPO_URL=https://github.com/OWNER/REPOSITORY.git \
DEPLOY_BRANCH=main \
bash deploy/deploy.sh
```

`bootstrap.sh` converts `~/public_html` from a real directory into a symlink (backing up whatever was already there — see Fallbacks below); `deploy.sh` then keeps that symlink pointed at `current/public` on every subsequent deploy.

### B. Addon domain whose docroot cPanel names after the domain

Common when "Domains → Manage" doesn't offer a custom Document Root field: adding `domain.com` as an addon domain makes cPanel create `~/domain.com` itself and use it directly as the docroot. `DEPLOY_DOCROOT` and `DEPLOY_APP_ROOT` **cannot be the same path** — `DOCROOT` becomes a symlink *into* `$APP_ROOT/current/public` — so give the app root a distinct name:

```bash
DEPLOY_APP_ROOT="$HOME/domain.com-app" \
DEPLOY_DOCROOT="$HOME/domain.com" \
DEPLOY_MANAGE_DOCROOT=1 \
bash deploy/bootstrap.sh
```

Edit `$HOME/domain.com-app/shared/.env`, then deploy with the same three variables plus the usual `DEPLOY_PHP_PACKAGE`/`DEPLOY_REPO_URL`/`DEPLOY_BRANCH`. Everything else behaves exactly like scenario A — `bootstrap.sh` backs up the real `~/domain.com` directory cPanel created and replaces it with a symlink.

### C. Addon domain with a custom Document Root, app under `~/apps/domain.com`

If "Domains → Manage" *does* let you set a custom Document Root, skip the docroot symlink entirely and point cPanel straight at the release:

```bash
DEPLOY_APP_ROOT="$HOME/apps/domain.com" \
DEPLOY_MANAGE_DOCROOT=0 \
bash deploy/bootstrap.sh
```

Then, once, in cPanel → Domains → Manage, set this domain's Document Root to (relative to the home directory):

```
apps/domain.com/current/public
```

cPanel accepts any path under the home directory as a custom Document Root, so the app root can be named however you like — `apps/domain.com` is just a convention for keeping multiple domains organized side by side; it doesn't have to live under `apps/`. Edit `$HOME/apps/domain.com/shared/.env`, then deploy with the same two variables and **no `DEPLOY_DOCROOT` at all**:

```bash
DEPLOY_PHP_PACKAGE=ea-php83 \
DEPLOY_APP_ROOT="$HOME/apps/domain.com" \
DEPLOY_MANAGE_DOCROOT=0 \
DEPLOY_REPO_URL=https://github.com/OWNER/REPOSITORY.git \
DEPLOY_BRANCH=main \
bash deploy/deploy.sh
```

This is the preferred layout when the host supports it — one fewer symlink, and cPanel's Document Root already tracks the active release automatically since `current` is part of the path it points at.

### Fallbacks

These failure modes live in the shared `bootstrap.sh`/`deploy.sh` logic, not the layout choice, so they apply the same way across all three:

- **`DEPLOY_DOCROOT` and `DEPLOY_APP_ROOT` are the same path.** `bootstrap.sh` refuses outright and tells you to either rename one or switch to scenario C (`DEPLOY_MANAGE_DOCROOT=0`, no separate docroot).
- **The docroot already exists as a real directory with files in it** — a freshly created addon domain (scenarios A/B), or an existing site being converted to atomic deploys. `bootstrap.sh` moves it to `<docroot>.pre-atomic-deploy.<timestamp>` next to itself — nothing is deleted — before creating the symlink. Confirm the new deployment serves correctly, then remove the backup yourself once you're satisfied.
- **`current` already exists as a real (non-symlink) directory the first time you deploy.** This happens when cPanel's custom-Document-Root feature (scenario C) pre-creates the whole path before any release exists. `deploy.sh` clears it automatically if it's empty scaffolding; if it already contains files, it refuses and tells you to inspect and clear it by hand rather than guessing and destroying something.
- **A deploy's migrations or launch check fail.** The release is left on disk at `releases/<timestamp>/` for inspection; `current` is never repointed, so the previous release keeps serving traffic untouched. Fix the problem and deploy again — never manually point `current` at a release that failed its own launch check.
- **The site returns 500 right after a deploy.** Almost always the web PHP version, not the code — see "Web PHP version" under section 4. `deploy.sh`'s own post-activation check requests the homepage and tells you this directly when it sees a 500/503, without your having to go looking.
- **Images, the logo, or the favicon 404 after a deploy.** `deploy.sh` already `chmod -R a+rX`'s the shared `public/` tree and adds `Options +SymLinksIfOwnerMatch` to the release `.htaccess` on every run; if it still 404s, confirm `APP_URL`/`CANONICAL_HOST` match the hostname you're actually browsing to — a mismatch 301-redirects every request, including asset requests, which reads the same as a broken image.
- **You need to abandon a deploy and go back.** `deploy/rollback.sh` takes the exact same `DEPLOY_APP_ROOT`/`DEPLOY_DOCROOT`/`DEPLOY_MANAGE_DOCROOT` as the layout you're running and only re-points symlinks to the previous release — no rebuild, no migration changes. See "Code regression rollback" in section 7.

### Updating a site already running under any of the three layouts

Re-run `deploy/deploy.sh` with **exactly the same** `DEPLOY_APP_ROOT`/`DEPLOY_DOCROOT`/`DEPLOY_MANAGE_DOCROOT` used for that domain's `bootstrap.sh` — nothing else changes between an initial deploy and a routine update. When more than one domain lives under the same cPanel account:

- **Every variable is per-domain.** Keep a short note (or a tiny wrapper script per domain) recording the exact `DEPLOY_APP_ROOT`/`DEPLOY_DOCROOT`/`DEPLOY_MANAGE_DOCROOT`/`DEPLOY_PHP_PACKAGE` for each one. Reusing another domain's `DEPLOY_APP_ROOT` by mistake silently overwrites that domain's `.env`, media, and release history.
- **Cron entries are already namespaced per domain.** `install-cron.sh` derives its marker from `DEPLOY_APP_ROOT`'s basename, so re-running it — which every `deploy.sh` does automatically at the end — only ever replaces that one domain's three cron lines and leaves every other domain's entries alone. `crontab -l` should show one scheduler line and two queue-worker lines per domain.
- **Update one domain at a time.** There's no "deploy all domains" command by design: each domain's release history, database, and rollback point are independent, so a bad deploy on one domain never has to touch the others.
- **The web PHP version stays per-domain and keeps preserving itself** (section 4) — `DEPLOY_PHP_PACKAGE` is only needed on that domain's first deploy, or to correct a handler block that's pointing at an uninstalled PHP version. Routine updates don't need it.

## 3. Google Staff SSO setup

Google sign-in is for existing **Admin/CSR/SEO staff accounts only** — it never creates a user or grants a role. The signing-in Google account's verified email must already belong to an active user with one of those roles; the Google identity is linked to that user on first successful sign-in and must match on every sign-in after.

1. **Google Cloud Console** ([console.cloud.google.com](https://console.cloud.google.com)) — create or select a project.
2. **APIs & Services → OAuth consent screen** — set the app name and support email. The default `openid profile email` scopes are all that's needed; add nothing else.
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID** → Application type **Web application**.
4. **Authorized redirect URIs** — add exactly:
   ```
   https://<canonical-domain>/auth/google/callback
   ```
   This must match `GOOGLE_REDIRECT_URI` byte-for-byte (scheme, host, no trailing slash). Add every hostname you actually serve login from (e.g. both apex and `www`) unless `CANONICAL_HOST` already redirects one to the other before it reaches this route.
5. Save, then copy the **Client ID** and **Client Secret** into `shared/.env`:
   ```bash
   GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxxxxxx
   GOOGLE_REDIRECT_URI=https://<canonical-domain>/auth/google/callback   # optional — defaults to {APP_URL}/auth/google/callback
   GOOGLE_ADMIN_ALLOWED_DOMAINS=yourcompany.com                          # optional, recommended if staff share a Workspace domain
   ```
6. Config is cached on every deploy. Editing `.env` directly on the server outside of `deploy.sh` needs `php artisan optimize` (or `config:clear`) re-run afterward, or the new values won't take effect.
7. **Verify:**
   - `system:launch-check --production` stops flagging "Google Staff SSO is configured."
   - `/login` shows a "Continue with Google as Staff" button (hidden until client ID, secret, and redirect all resolve).
   - Signing in with a Google account whose email matches an active Admin/CSR/SEO user logs them in and writes a `security.google-sso-login` audit row. A rejection (unverified email, disallowed domain, no matching staff account, or a Google identity already linked elsewhere) writes `security.google-sso-rejected`/`-failed` with a reason instead of failing silently.

Set `LAUNCH_CHECK_REQUIRE_GOOGLE_SSO=false` (config `security.require_google_admin_sso`) if you don't want Google SSO at all — staff then authenticate with email/password and, optionally, authenticator MFA.

## 4. Deploying

The deploy is atomic: it prepares a release, runs migrations and the launch gate, and only then moves the active symlink. A failed gate leaves the previous release serving traffic (see Fallbacks in section 2). Database migrations are forward-only during routine rollback; never reverse production migrations merely to match an older code release.

**Web PHP version.** `PHP_BIN` only sets the CLI used for Composer and artisan — it does not set the version Apache runs the site with. That is controlled entirely by **cPanel → MultiPHP Manager**: set the domain to PHP 8.3 (or a newer version your host provides a working web handler for) once, and Apply. `deploy.sh` never imposes a version — it carries forward whatever handler is already live (a `# php -- BEGIN cPanel-generated handler` block in the docroot `.htaccess`, or nothing when the host manages PHP at the vhost/FPM level). On a first-ever deploy with no version set yet, pass `DEPLOY_PHP_PACKAGE=ea-php83`. If a deploy leaves the site returning 500, it is almost always this — set the version in MultiPHP Manager and it sticks for every deploy after.

## 5. Post-deploy verification

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

## 6. Daily and weekly operations

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

## 7. Incident response

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

Use the `DEPLOY_APP_ROOT`/`DEPLOY_DOCROOT`/`DEPLOY_MANAGE_DOCROOT` for whichever layout (section 2) that domain actually runs — a scenario-C domain passes `DEPLOY_MANAGE_DOCROOT=0` and no `DEPLOY_DOCROOT`, exactly as it does for `deploy.sh`. Rollback moves symlinks only. Afterward, run the readiness checks and smoke paths again. If the incident involves a migration incompatibility, deploy a forward corrective migration; do not run destructive rollback commands against the live database.

### Queue or scheduler outage

1. Confirm both cron lines exist and use the correct `PHP_BIN` and application root.
2. Run `"$PHP_BIN" artisan schedule:list` and `"$PHP_BIN" artisan queue:failed`.
3. Run one bounded drain of the fast queues: `"$PHP_BIN" artisan queue:work --queue=monitoring,default --stop-when-empty --max-time=50 --tries=3 --timeout=45`, then one of the media queue: `"$PHP_BIN" artisan queue:work --queue=media --stop-when-empty --max-time=55 --tries=3 --timeout=280 --memory=512`.
4. Recheck queue-worker, scheduler, moderation, and privacy heartbeats.
5. Retry failed jobs only after correcting the cause.

### Staff SSO outage

1. Check the exact callback URI in Google Cloud Console against production `.env` — see section 3.
2. Confirm the staff user is active, has Admin/CSR/SEO, and the Google email matches the pre-authorized account.
3. Verify system time, HTTPS URL generation, session/cache health, and Google client credentials.
4. Do not enable public self-provisioning or grant a role from Google claims as a workaround.

### Suspected account or data compromise

1. Suspend affected accounts and revoke exposed provider/API credentials.
2. Rotate application and service secrets deliberately; understand that rotating `APP_KEY` invalidates encrypted data and sessions.
3. Preserve relevant logs/backups, scope accessed records, and obtain legal/privacy guidance.
4. Force affected sessions and credentials to be replaced before restoring access.

## 8. Backup and recovery

- Never restore over the live database as a test.
- Verify archive checksum and decryptability before importing.
- Restore into the configured isolated restore-drill database.
- Confirm core tables, pending migrations, record counts, and representative ownership/profile/media relationships.
- For a real disaster, preserve the damaged environment, create a fresh backup if safe, document the selected recovery point, restore into clean infrastructure, run migrations and launch checks, then switch traffic only after verification.

Recovery is complete only when public pages, Admin/CSR access, queue processing, media, policies, moderation history, backups, and Search Console/sitemap behavior have been checked.

## 9. Release evidence

For each production release retain:

- Domain, release timestamp, and Git commit.
- Operator and deploy command parameters excluding secrets.
- Launch-check output and load-smoke summary.
- Migration list and backup identifiers immediately before release.
- Post-deploy verification result, incidents, and rollback decision.

Never store passwords, tokens, raw identity evidence, private uploads, or production `.env` contents in release notes or tickets.

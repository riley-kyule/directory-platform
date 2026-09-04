#!/usr/bin/env bash
#
# Atomic-release deploy for shared cPanel hosting with SSH but no root.
# Run deploy/bootstrap.sh once first. Requires DEPLOY_REPO_URL to be
# reachable non-interactively (an SSH deploy key or a token-embedded HTTPS
# URL) since this clones on the server rather than uploading a build.
#
# Usage:
#   DEPLOY_REPO_URL=git@github.com:riley-kyule/directory-platform.git ./deploy.sh
#   DEPLOY_BRANCH=main ./deploy.sh   # branch defaults to main
#
# Pass the SAME DEPLOY_APP_ROOT/DEPLOY_DOCROOT/DEPLOY_MANAGE_DOCROOT you used
# for bootstrap.sh every time — this script re-links those same paths on
# every deploy. Required when running multiple domains under one cPanel
# account, since each domain needs its own app root (see bootstrap.sh).
#
# PHP_BIN: cPanel's MultiPHP Manager only selects which PHP handles WEB
# requests for a domain — it does not change what a bare `php`/`composer`
# resolve to in an SSH shell, which is often a much older system-default PHP
# (commonly still 7.x/8.1/8.2 even when 8.3+ is selected for the site).
# Composer/artisan need to run under the same 8.3+ version the app requires,
# so this script AUTO-DETECTS a PHP 8.3+ CLI binary (checking /opt/cpanel/
# ea-php8* and php8.3/php83/... on PATH). Only set PHP_BIN yourself if the
# detection can't find it:
#   PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php DEPLOY_APP_ROOT=... ./deploy.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/lib-php.sh"

APP_ROOT="${DEPLOY_APP_ROOT:-$HOME/directory-platform}"
RELEASES_DIR="$APP_ROOT/releases"
SHARED_DIR="$APP_ROOT/shared"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html}"
MANAGE_DOCROOT="${DEPLOY_MANAGE_DOCROOT:-1}"
KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"
BRANCH="${DEPLOY_BRANCH:-main}"
# Public repo — override only for a fork or a private mirror.
REPO_URL="${DEPLOY_REPO_URL:-https://github.com/riley-kyule/directory-platform.git}"
resolve_php_bin

# Composer is often not on a cPanel account's PATH — it's frequently at
# ~/composer.phar or ~/bin/composer. Find it or fail with a clear message
# instead of dying silently under `set -e`.
resolve_composer() {
    local c
    for c in "${COMPOSER_BIN:-}" composer composer.phar "$HOME/composer.phar" \
             "$HOME/bin/composer" "$HOME/bin/composer.phar" /usr/local/bin/composer; do
        [ -n "$c" ] || continue
        if command -v "$c" >/dev/null 2>&1; then COMPOSER_PATH="$(command -v "$c")"; return 0; fi
        if [ -f "$c" ]; then COMPOSER_PATH="$c"; return 0; fi
    done
    echo "error: could not find Composer (looked for composer / composer.phar on PATH and in ~/, ~/bin)." >&2
    echo "       Install it once: cd ~ && curl -sS https://getcomposer.org/installer | $PHP_BIN --" >&2
    echo "       then re-run, or set COMPOSER_BIN=/full/path/to/composer.phar." >&2
    exit 1
}
resolve_composer
RELEASE_NAME="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$RELEASE_NAME"
CPANEL_HANDLER_FILE="$SHARED_DIR/cpanel-php-handler.conf"

if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "error: $SHARED_DIR/.env is missing. Run bootstrap.sh first, then edit that file." >&2
    exit 1
fi
if grep -q '<<< SET THIS' "$SHARED_DIR/.env"; then
    echo "error: $SHARED_DIR/.env still has unfilled placeholders:" >&2
    grep -n '<<< SET THIS' "$SHARED_DIR/.env" | sed 's/^/       /' >&2
    echo "       Fill in each of those lines (values from cPanel), then re-run deploy.sh." >&2
    exit 1
fi

# APP_URL builds every absolute asset URL (logo, favicon, profile images).
# CANONICAL_HOST must be exactly its hostname or EnforceCanonicalHost 301s
# every request. Catch the common mismatch before it ships broken images.
env_val() { sed -n "s/^$1=//p" "$SHARED_DIR/.env" | head -n1 | tr -d '"'"'"'' | tr -d '\r'; }
APP_URL_VAL="$(env_val APP_URL)"
CANONICAL_VAL="$(env_val CANONICAL_HOST)"
APP_URL_HOST="$(printf '%s' "$APP_URL_VAL" | sed -E 's#^https?://##; s#/.*$##')"
if [ -n "$APP_URL_VAL" ] && { printf '%s' "$APP_URL_VAL" | grep -q 'example\.com' || [ "$APP_URL_VAL" = "http://localhost" ]; }; then
    echo "error: APP_URL in $SHARED_DIR/.env is still a placeholder ($APP_URL_VAL)." >&2
    exit 1
fi
if [ -n "$CANONICAL_VAL" ] && [ -n "$APP_URL_HOST" ] && [ "$CANONICAL_VAL" != "$APP_URL_HOST" ]; then
    echo "warning: CANONICAL_HOST ($CANONICAL_VAL) is not APP_URL's host ($APP_URL_HOST)." >&2
    echo "         Every request will 301-redirect. Set CANONICAL_HOST=$APP_URL_HOST unless this is deliberate." >&2
fi

echo "==> Cloning $BRANCH into $RELEASE_DIR"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_DIR"

# --- Web PHP version -------------------------------------------------------
# This is SEPARATE from $PHP_BIN (which is just the CLI used for composer and
# artisan — newest is fine there). The version Apache uses for the site is
# whatever cPanel > MultiPHP Manager set for the domain, and how it's stored
# depends on the host:
#   * PHP-FPM / vhost handler (modern default): nothing in .htaccess. It's
#     attached to the docroot path and survives the `current` symlink swap on
#     its own — deploy.sh must NOT touch it. Writing an AddHandler line here
#     fights FPM and 500s the site.
#   * suPHP / CGI handler (older): a `# php -- BEGIN cPanel-generated handler`
#     block in the docroot .htaccess. Atomic releases replace that file, so it
#     has to be copied into each new release verbatim.
# Rule: an explicit DEPLOY_PHP_PACKAGE wins; otherwise preserve whatever is
# already live; NEVER derive it from $PHP_BIN (auto-detect picks the newest
# CLI, e.g. 8.5, whose *web* handler may not exist on the account -> 500).
: > "$CPANEL_HANDLER_FILE"
write_handler_block() {
    {
        echo '# php -- BEGIN cPanel-generated handler, do not edit'
        echo "# Set the '$1' package as the default PHP programming language."
        echo '<IfModule mime_module>'
        echo "  AddHandler application/x-httpd-$1 .php .php8 .phtml"
        echo '</IfModule>'
        echo '# php -- END cPanel-generated handler, do not edit'
    } > "$CPANEL_HANDLER_FILE"
}

if [ -n "${DEPLOY_PHP_PACKAGE:-}" ]; then
    echo "==> Setting the cPanel web PHP handler to DEPLOY_PHP_PACKAGE=$DEPLOY_PHP_PACKAGE"
    write_handler_block "$DEPLOY_PHP_PACKAGE"
elif [ -f "$APP_ROOT/current/public/.htaccess" ] \
     && grep -q '# php -- BEGIN cPanel-generated handler' "$APP_ROOT/current/public/.htaccess"; then
    LIVE_PKG="$(sed -n "s#.*AddHandler application/x-httpd-\(ea-php[0-9]*\).*#\1#p" "$APP_ROOT/current/public/.htaccess" | head -n1)"
    if [ -n "$LIVE_PKG" ] && [ ! -x "/opt/cpanel/$LIVE_PKG/root/usr/bin/php" ]; then
        echo "warning: live .htaccess selects $LIVE_PKG but /opt/cpanel/$LIVE_PKG is not installed —" >&2
        echo "         dropping that handler block. Set the version in cPanel > MultiPHP Manager," >&2
        echo "         or re-run with DEPLOY_PHP_PACKAGE=ea-php83." >&2
    else
        echo "==> Carrying the live cPanel PHP handler ($LIVE_PKG) into the new release"
        sed -n '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/p' \
            "$APP_ROOT/current/public/.htaccess" > "$CPANEL_HANDLER_FILE"
    fi
elif [ -f "$APP_ROOT/current/public/.htaccess" ]; then
    echo '==> Live release has no .htaccess PHP handler — this domain sets PHP at the vhost/FPM'
    echo '    level, which survives the release swap on its own. Leaving it alone.'
else
    echo 'warning: first deploy and no web PHP version chosen.' >&2
    echo '         After activation, if the site 500s: cPanel > MultiPHP Manager, set this domain' >&2
    echo '         to PHP 8.3 (or a newer version your host has a working web handler for), Apply.' >&2
    echo '         Every deploy after that preserves your choice. Or re-run now with' >&2
    echo '         DEPLOY_PHP_PACKAGE=ea-php83 prepended.' >&2
fi

if [ -s "$CPANEL_HANDLER_FILE" ] && ! grep -q '# php -- BEGIN cPanel-generated handler' "$RELEASE_DIR/public/.htaccess"; then
    printf '\n' >> "$RELEASE_DIR/public/.htaccess"
    cat "$CPANEL_HANDLER_FILE" >> "$RELEASE_DIR/public/.htaccess"
fi

echo "==> Linking shared resources into the release"
# Self-heal the media pipeline directories in case a shared dir was lost; the
# disks are configured with throw=>true so a missing root is a hard failure.
mkdir -p "$SHARED_DIR/storage/app/private/quarantine" \
         "$SHARED_DIR/storage/app/private/media-review" \
         "$SHARED_DIR/storage/app/media-staging" \
         "$SHARED_DIR/public/media/profiles"
rm -rf "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -sf "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/public/media/profiles"
mkdir -p "$RELEASE_DIR/public/media"
ln -s "$SHARED_DIR/public/media/profiles" "$RELEASE_DIR/public/media/profiles"
mkdir -p "$SHARED_DIR/public/branding"
if [ -d "$APP_ROOT/current/public/branding" ] && [ ! -L "$APP_ROOT/current/public/branding" ]; then
    echo "==> Migrating existing branding files into shared storage"
    cp -a "$APP_ROOT/current/public/branding/." "$SHARED_DIR/public/branding/"
fi
rm -rf "$RELEASE_DIR/public/branding"
ln -s "$SHARED_DIR/public/branding" "$RELEASE_DIR/public/branding"

# The logo, favicon and profile images are static files Apache serves from
# these two symlinked directories. Make sure the targets are world-readable
# (uploads via PHP can land as 0600 under a tight umask) and that Apache is
# actually willing to follow the symlink — the #1 reason images 404 on cPanel
# while the real files under public/build/ still load.
chmod -R a+rX "$SHARED_DIR/public" 2>/dev/null || true
if ! grep -qs 'SymLinksIfOwnerMatch\|FollowSymLinks' "$RELEASE_DIR/public/.htaccess"; then
    printf '\n# Added by deploy.sh: serve the symlinked branding/ and media/ dirs.\n<IfModule mod_rewrite.c>\n    Options +SymLinksIfOwnerMatch\n</IfModule>\n' >> "$RELEASE_DIR/public/.htaccess"
fi

echo "==> Installing PHP dependencies (using $PHP_BIN: $("$PHP_BIN" -v | head -n 1))"
cd "$RELEASE_DIR"
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --optimize-autoloader --no-interaction

# APP_KEY is required for encryption, sessions and signed URLs. Generate one on
# the first deploy if the operator left it blank in shared/.env — key:generate
# writes through the .env symlink into shared/, so it persists across releases.
if ! grep -qE '^APP_KEY=base64:.+' "$SHARED_DIR/.env"; then
    echo "==> No APP_KEY set in shared/.env — generating one now"
    "$PHP_BIN" artisan key:generate --force --no-interaction
fi

if [ -f "$RELEASE_DIR/public/build/manifest.json" ]; then
    echo "==> public/build/ is already committed in this release — skipping npm entirely"
    echo "    (built assets must be rebuilt and committed locally before pushing; the server"
    echo "    never builds them, so shared-hosting Node version limits don't matter here)"
else
    echo "==> Building frontend assets"
    npm ci
    npm run build
fi

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Seeding baseline data (roles/permissions, settings, packages, taxonomy, default policies)"
"$PHP_BIN" artisan db:seed --force

echo "==> Caching config/routes/views"
"$PHP_BIN" artisan optimize

echo "==> Refreshing scheduler and queue cron definitions"
PHP_BIN="$PHP_BIN" DEPLOY_APP_ROOT="$APP_ROOT" "$RELEASE_DIR/deploy/install-cron.sh"

echo "==> Actively verifying asynchronous queue processing"
"$PHP_BIN" artisan system:dispatch-queue-heartbeat
"$PHP_BIN" artisan queue:work --queue=monitoring --stop-when-empty --max-time=30 --tries=1 --timeout=20

echo "==> Running the production launch check"
LAUNCH_CHECK_ARGS=(--production)
if [ ! -L "$APP_ROOT/current" ]; then
    echo "    No existing $APP_ROOT/current symlink — first deploy for this app root, so the"
    echo "    operational-heartbeat/backup-freshness checks are allowed to warn instead of block"
    echo "    (neither can possibly have run yet). Every later deploy enforces them normally."
    LAUNCH_CHECK_ARGS+=(--allow-cold-start)
fi
if ! "$PHP_BIN" artisan system:launch-check "${LAUNCH_CHECK_ARGS[@]}"; then
    echo "error: launch check failed — leaving $APP_ROOT/current pointed at the previous release." >&2
    echo "       This release is left in place at $RELEASE_DIR for inspection; it was not activated." >&2
    exit 1
fi

echo "==> Activating release $RELEASE_NAME"
if [ -e "$APP_ROOT/current" ] && [ ! -L "$APP_ROOT/current" ]; then
    # `ln -sfn` refuses to replace a real (non-symlink) directory — it nests
    # the new symlink INSIDE it instead of replacing it, silently. This
    # happens on the very first deploy whenever cPanel's custom Document
    # Root feature pre-creates the whole path (e.g. <app>/current/public) as
    # real directories the moment you set it, before any release exists.
    if find "$APP_ROOT/current" -mindepth 1 ! -type d | grep -q .; then
        echo "error: $APP_ROOT/current exists as a real directory containing files (not just empty scaffolding) — refusing to overwrite it automatically." >&2
        echo "       Inspect it, remove it yourself if it's safe to clear, then re-run deploy.sh." >&2
        exit 1
    fi
    echo "==> $APP_ROOT/current exists as an empty real directory (likely cPanel's Document Root auto-creating that path) — clearing it so it can become a symlink"
    rm -rf "$APP_ROOT/current"
fi
ln -sfn "$RELEASE_DIR" "$APP_ROOT/current"
if [ "$MANAGE_DOCROOT" != "1" ]; then
    : # cPanel's own Document Root points at $APP_ROOT/current/public
      # directly, which just moved above.
elif [ -L "$DOCROOT" ] || [ ! -e "$DOCROOT" ]; then
    # Already a symlink (repoint it), or never existed yet (first deploy for
    # this app root, e.g. primary-domain public_html before any release) —
    # either way it's safe for `ln -sfn` to create/update it directly.
    ln -sfn "$RELEASE_DIR/public" "$DOCROOT"
else
    echo "warning: $DOCROOT exists and is not a symlink — run bootstrap.sh first (with the same DEPLOY_DOCROOT if you set one), or confirm your cPanel document root already points at $APP_ROOT/current/public." >&2
fi

echo "==> Pruning old releases (keeping the $KEEP_RELEASES most recent)"
cd "$RELEASES_DIR"
ls -1t | tail -n "+$((KEEP_RELEASES + 1))" | while read -r old; do
    [ "$old" = "$RELEASE_NAME" ] && continue
    rm -rf "$RELEASES_DIR/$old"
done

# Post-activation smoke test for the static asset dirs that break most often.
for rel in build/manifest.json media/profiles/ branding/; do
    if [ ! -e "$APP_ROOT/current/public/$rel" ]; then
        echo "warning: $APP_ROOT/current/public/$rel does not resolve — the logo/favicon/images may 404." >&2
    fi
done
CANARY="$SHARED_DIR/public/media/profiles/.deploy-healthcheck"
: > "$CANARY" 2>/dev/null || true
if [ -n "$APP_URL_VAL" ] && command -v curl >/dev/null 2>&1; then
    home_code="$(curl -sSL -o /dev/null -w '%{http_code}' -m 15 "$APP_URL_VAL/" || echo 000)"
    if [ "$home_code" = "500" ] || [ "$home_code" = "503" ]; then
        echo "warning: GET $APP_URL_VAL/ returned $home_code — the site is down." >&2
        echo "         Almost always the web PHP version. Open cPanel > MultiPHP Manager, set this" >&2
        echo "         domain's PHP to 8.3 (or a newer version your host has a working web handler" >&2
        echo "         for) and Apply. This deploy did NOT change the PHP handler; every deploy" >&2
        echo "         from now on preserves whatever you set there." >&2
        echo "         Check: tail ~/logs/*error* or cPanel > Errors." >&2
    elif [ "$home_code" != "200" ] && [ "$home_code" != "302" ]; then
        echo "warning: GET $APP_URL_VAL/ returned $home_code (expected 200/302)." >&2
    else
        echo "==> Site responds OK ($APP_URL_VAL/ -> $home_code)"
    fi

    media_code="$(curl -s -o /dev/null -w '%{http_code}' -m 10 "$APP_URL_VAL/media/profiles/.deploy-healthcheck" || echo 000)"
    if [ "$media_code" != "200" ]; then
        echo "warning: GET $APP_URL_VAL/media/profiles/.deploy-healthcheck returned $media_code, not 200." >&2
        echo "         Apache is not serving the symlinked media directory. Check that the vhost or" >&2
        echo "         public/.htaccess allows 'Options +SymLinksIfOwnerMatch', and that APP_URL matches" >&2
        echo "         the hostname you actually browse to (scheme and www included)." >&2
    else
        echo "==> Static media directory is serving correctly ($APP_URL_VAL/media/profiles/)"
    fi
fi
rm -f "$CANARY" 2>/dev/null || true

echo "SELF_DEPLOY_COMMIT=$(git -C "$RELEASE_DIR" rev-parse HEAD)"
echo "==> Deployed $RELEASE_NAME"

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
# Composer/artisan need to run under the same 8.3+ version the app requires.
# Find your host's PHP 8.3+ CLI binary — commonly one of:
#   ls /opt/cpanel/ea-php8*/root/usr/bin/php
#   command -v php83   (or php8.3, ea-php83 — varies by host)
# then pass it explicitly:
#   PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php DEPLOY_APP_ROOT=... ./deploy.sh
set -euo pipefail

APP_ROOT="${DEPLOY_APP_ROOT:-$HOME/directory-platform}"
RELEASES_DIR="$APP_ROOT/releases"
SHARED_DIR="$APP_ROOT/shared"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html}"
MANAGE_DOCROOT="${DEPLOY_MANAGE_DOCROOT:-1}"
KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"
BRANCH="${DEPLOY_BRANCH:-main}"
REPO_URL="${DEPLOY_REPO_URL:?Set DEPLOY_REPO_URL to a git remote reachable without a password prompt}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_PATH="$(command -v "${COMPOSER_BIN:-composer}")"
RELEASE_NAME="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$RELEASE_NAME"
CPANEL_HANDLER_FILE="$SHARED_DIR/cpanel-php-handler.conf"

if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "error: $SHARED_DIR/.env is missing. Run bootstrap.sh and put a production .env there first." >&2
    exit 1
fi

echo "==> Cloning $BRANCH into $RELEASE_DIR"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_DIR"

# MultiPHP Manager commonly persists the selected WEB PHP version as a
# cPanel-generated AddHandler block inside the current document root's
# .htaccess. Atomic releases replace that file, so without carrying the
# handler forward the new release silently falls back to the account default
# (often PHP 8.2) even though Composer/artisan correctly used PHP 8.3+.
CPANEL_PHP_PACKAGE="$(printf '%s\n' "$PHP_BIN" | sed -n 's#^/opt/cpanel/\(ea-php[0-9][0-9]*\)/root/usr/bin/php$#\1#p')"
if [ -n "$CPANEL_PHP_PACKAGE" ]; then
    echo "==> Configuring cPanel web PHP handler for $CPANEL_PHP_PACKAGE"
    {
        echo '# php -- BEGIN cPanel-generated handler, do not edit'
        echo "# Set the '$CPANEL_PHP_PACKAGE' package as the default PHP programming language."
        echo '<IfModule mime_module>'
        echo "  AddHandler application/x-httpd-$CPANEL_PHP_PACKAGE .php .php8 .phtml"
        echo '</IfModule>'
        echo '# php -- END cPanel-generated handler, do not edit'
    } > "$CPANEL_HANDLER_FILE"
elif [ -f "$APP_ROOT/current/public/.htaccess" ] && grep -q '# php -- BEGIN cPanel-generated handler' "$APP_ROOT/current/public/.htaccess"; then
    echo '==> Preserving the current cPanel web PHP handler'
    sed -n '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/p' \
        "$APP_ROOT/current/public/.htaccess" > "$CPANEL_HANDLER_FILE"
fi

if [ -s "$CPANEL_HANDLER_FILE" ]; then
    if ! grep -q '# php -- BEGIN cPanel-generated handler' "$RELEASE_DIR/public/.htaccess"; then
        printf '\n' >> "$RELEASE_DIR/public/.htaccess"
        cat "$CPANEL_HANDLER_FILE" >> "$RELEASE_DIR/public/.htaccess"
    fi
else
    echo 'warning: no cPanel web PHP handler could be derived or preserved; confirm this domain inherits PHP 8.3+ at the vhost level.' >&2
fi

echo "==> Linking shared resources into the release"
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

echo "==> Installing PHP dependencies (using $PHP_BIN: $("$PHP_BIN" -v | head -n 1))"
cd "$RELEASE_DIR"
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --optimize-autoloader --no-interaction

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

echo "SELF_DEPLOY_COMMIT=$(git -C "$RELEASE_DIR" rev-parse HEAD)"
echo "==> Deployed $RELEASE_NAME"

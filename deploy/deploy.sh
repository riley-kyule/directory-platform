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

if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "error: $SHARED_DIR/.env is missing. Run bootstrap.sh and put a production .env there first." >&2
    exit 1
fi

echo "==> Cloning $BRANCH into $RELEASE_DIR"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_DIR"

echo "==> Linking shared resources into the release"
rm -rf "$RELEASE_DIR/storage"
ln -s "$SHARED_DIR/storage" "$RELEASE_DIR/storage"
ln -sf "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/public/media/profiles"
mkdir -p "$RELEASE_DIR/public/media"
ln -s "$SHARED_DIR/public/media/profiles" "$RELEASE_DIR/public/media/profiles"

echo "==> Installing PHP dependencies (using $PHP_BIN: $("$PHP_BIN" -v | head -n 1))"
cd "$RELEASE_DIR"
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --optimize-autoloader --no-interaction

echo "==> Building frontend assets"
npm ci
npm run build

echo "==> Running migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Caching config/routes/views"
"$PHP_BIN" artisan optimize

echo "==> Running the production launch check"
if ! "$PHP_BIN" "$COMPOSER_PATH" launch-check; then
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
elif [ -L "$DOCROOT" ]; then
    ln -sfn "$RELEASE_DIR/public" "$DOCROOT"
else
    echo "warning: $DOCROOT is not a symlink — run bootstrap.sh first (with the same DEPLOY_DOCROOT if you set one), or confirm your cPanel document root already points at $APP_ROOT/current/public." >&2
fi

echo "==> Pruning old releases (keeping the $KEEP_RELEASES most recent)"
cd "$RELEASES_DIR"
ls -1t | tail -n "+$((KEEP_RELEASES + 1))" | while read -r old; do
    [ "$old" = "$RELEASE_NAME" ] && continue
    rm -rf "$RELEASES_DIR/$old"
done

echo "==> Deployed $RELEASE_NAME"

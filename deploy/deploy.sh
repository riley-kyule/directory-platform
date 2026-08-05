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
set -euo pipefail

APP_ROOT="${DEPLOY_APP_ROOT:-$HOME/directory-platform}"
RELEASES_DIR="$APP_ROOT/releases"
SHARED_DIR="$APP_ROOT/shared"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html}"
MANAGE_DOCROOT="${DEPLOY_MANAGE_DOCROOT:-1}"
KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"
BRANCH="${DEPLOY_BRANCH:-main}"
REPO_URL="${DEPLOY_REPO_URL:?Set DEPLOY_REPO_URL to a git remote reachable without a password prompt}"
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

echo "==> Installing PHP dependencies"
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Building frontend assets"
npm ci
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config/routes/views"
php artisan optimize

echo "==> Running the production launch check"
if ! composer launch-check; then
    echo "error: launch check failed — leaving $APP_ROOT/current pointed at the previous release." >&2
    echo "       This release is left in place at $RELEASE_DIR for inspection; it was not activated." >&2
    exit 1
fi

echo "==> Activating release $RELEASE_NAME"
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

#!/usr/bin/env bash
#
# Rolls the live symlinks back to a previous release. Fast (no rebuild, no
# git clone) since every release built by deploy.sh is kept on disk until
# pruned. Only ever moves symlinks and re-runs artisan's cache warmers —
# never touches the database. Code rollback and database rollback are
# different operations with different risk profiles; see the note this
# script prints at the end and the README's own incident guidance.
#
# Usage:
#   ./rollback.sh                # roll back one release
#   ./rollback.sh 20260801120000 # roll back to a specific release directory
#
# Pass the same DEPLOY_APP_ROOT/DEPLOY_DOCROOT you used for deploy.sh if this
# domain doesn't use the defaults (e.g. multiple domains under one account).
set -euo pipefail

APP_ROOT="${DEPLOY_APP_ROOT:-$HOME/directory-platform}"
RELEASES_DIR="$APP_ROOT/releases"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html}"
MANAGE_DOCROOT="${DEPLOY_MANAGE_DOCROOT:-1}"
PHP_BIN="${PHP_BIN:-php}"

if [ ! -L "$APP_ROOT/current" ]; then
    echo "error: $APP_ROOT/current is not a symlink — this environment wasn't deployed with deploy.sh. Refusing to guess what to roll back." >&2
    exit 1
fi

CURRENT_RELEASE="$(basename "$(readlink "$APP_ROOT/current")")"
TARGET_RELEASE="${1:-}"

if [ -z "$TARGET_RELEASE" ]; then
    TARGET_RELEASE="$(ls -1t "$RELEASES_DIR" | grep -v "^${CURRENT_RELEASE}\$" | head -n 1 || true)"
fi

if [ -z "$TARGET_RELEASE" ] || [ ! -d "$RELEASES_DIR/$TARGET_RELEASE" ]; then
    echo "error: no valid target release found. Releases on disk:" >&2
    ls -1t "$RELEASES_DIR" >&2 || true
    exit 1
fi

if [ "$TARGET_RELEASE" = "$CURRENT_RELEASE" ]; then
    echo "error: $TARGET_RELEASE is already the active release." >&2
    exit 1
fi

TARGET_DIR="$RELEASES_DIR/$TARGET_RELEASE"

echo "==> Rolling back: $CURRENT_RELEASE -> $TARGET_RELEASE"
echo "==> Migration status of the target release (informational only — nothing is applied or reversed):"
(cd "$TARGET_DIR" && "$PHP_BIN" artisan migrate:status) || true

ln -sfn "$TARGET_DIR" "$APP_ROOT/current"
if [ "$MANAGE_DOCROOT" = "1" ] && [ -L "$DOCROOT" ]; then
    ln -sfn "$TARGET_DIR/public" "$DOCROOT"
fi
(cd "$TARGET_DIR" && "$PHP_BIN" artisan optimize)

echo "==> Rolled back to $TARGET_RELEASE"
echo "==> This only reverted code. If $CURRENT_RELEASE ran a migration that $TARGET_RELEASE doesn't"
echo "    expect, the database is still in that newer shape — do not run migrate:rollback against"
echo "    production. Restore into an isolated database first and follow the incident plan, per README.md."

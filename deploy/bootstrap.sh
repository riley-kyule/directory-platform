#!/usr/bin/env bash
#
# One-time setup for atomic-release deployment on shared cPanel hosting with
# SSH but no root: no systemd/WHM access, so this uses plain symlinks in the
# account's own home directory instead. Run this once per environment before
# the first deploy.sh. Safe to re-run — it won't touch an existing shared/.env.
#
# What it does, under $APP_ROOT (below):
#   releases/   — each deploy gets its own timestamped dir
#   shared/     — .env, storage/, and public media that must persist across
#                 releases
#   $DOCROOT    — converted from a real directory to a symlink at
#                 $APP_ROOT/current/public
#
# MULTIPLE DOMAINS UNDER ONE cPANEL ACCOUNT: $APP_ROOT defaults to
# ~/directory-platform, which is fine for a single site per account, but every
# domain sharing that same default would collide on the same releases/shared/
# current — one domain's deploy would silently overwrite another's .env and
# media. Give each domain its own app root, named after the domain:
#
#   DEPLOY_APP_ROOT="$HOME/senegalhookups.com" DEPLOY_MANAGE_DOCROOT=0 ./bootstrap.sh
#
# Then, once (via cPanel Domains -> Manage), point that domain's Document
# Root at:
#
#   senegalhookups.com/current/public   (relative to the home directory)
#
# cPanel addon domains generally accept any path under the home directory as
# the document root, not just a top-level folder — so the domain name can
# name the whole app root (releases/shared/current all live under it) while
# cPanel's own vhost config points at the nested `current/public` inside it.
# Nothing here needs to manage a separate docroot symlink at all; `current`
# already gets repointed atomically by deploy.sh on every release.
#
# DOCROOT (below) is the alternative for hosts that DON'T support a custom
# per-domain document root: it defaults to ~/public_html (correct for the
# account's PRIMARY domain only — leave DEPLOY_MANAGE_DOCROOT at its default
# of 1 in that case). For an addon domain without custom-docroot support,
# point DEPLOY_DOCROOT at the actual folder cPanel created for it instead
# (commonly ~/<domain>) and give DEPLOY_APP_ROOT a distinct name, since
# DOCROOT becomes a symlink INTO $APP_ROOT/current/public and the two can't
# be the same path.
set -euo pipefail

APP_ROOT="${DEPLOY_APP_ROOT:-$HOME/directory-platform}"
SHARED_DIR="$APP_ROOT/shared"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html}"
MANAGE_DOCROOT="${DEPLOY_MANAGE_DOCROOT:-1}"

if [ "$MANAGE_DOCROOT" = "1" ] && [ "$DOCROOT" = "$APP_ROOT" ]; then
    echo "error: DEPLOY_DOCROOT and DEPLOY_APP_ROOT are both '$APP_ROOT'." >&2
    echo "       DOCROOT becomes a symlink INTO \$APP_ROOT/current/public, so they" >&2
    echo "       can't be the same path — give DOCROOT a distinct name (e.g. a" >&2
    echo "       '-web' suffix), or set DEPLOY_MANAGE_DOCROOT=0 and point cPanel's" >&2
    echo "       Document Root at \$APP_ROOT/current/public directly instead." >&2
    exit 1
fi

echo "==> Creating release/shared directory structure at $APP_ROOT"
mkdir -p "$APP_ROOT/releases"
mkdir -p "$SHARED_DIR/storage/app/public" "$SHARED_DIR/storage/app/private/quarantine" "$SHARED_DIR/storage/app/private/media-review" "$SHARED_DIR/storage/app/media-staging"
mkdir -p "$SHARED_DIR/storage/framework/cache/data" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views" "$SHARED_DIR/storage/logs"
mkdir -p "$SHARED_DIR/public/media/profiles"
mkdir -p "$SHARED_DIR/public/branding"

if [ -f "$SHARED_DIR/.env" ]; then
    echo "==> $SHARED_DIR/.env already exists, leaving it alone"
else
    echo "==> No shared .env found yet."
    echo "    Copy your production .env to $SHARED_DIR/.env before running deploy.sh —"
    echo "    it is intentionally not created automatically."
fi

if [ "$MANAGE_DOCROOT" != "1" ]; then
    echo "==> DEPLOY_MANAGE_DOCROOT=0 — leaving $DOCROOT untouched"
    echo "    Point this domain's Document Root (Domains -> Manage in cPanel) at:"
    echo "      $APP_ROOT/current/public"
elif [ -L "$DOCROOT" ]; then
    echo "==> $DOCROOT is already a symlink, leaving it — deploy.sh will keep it updated"
elif [ -e "$DOCROOT" ]; then
    BACKUP="${DOCROOT}.pre-atomic-deploy.$(date +%Y%m%d%H%M%S)"
    echo "==> $DOCROOT exists as a real directory — moving it to $BACKUP"
    echo "    (nothing is deleted; review and remove that backup yourself once you've"
    echo "    confirmed the new deployment is serving correctly)"
    if [ "$DOCROOT" = "$HOME/public_html" ]; then
        echo "    If this is an ADDON domain, stop now — set DEPLOY_DOCROOT to that"
        echo "    domain's own folder instead; \$HOME/public_html is your primary"
        echo "    domain's docroot, not this site's."
    fi
    mv "$DOCROOT" "$BACKUP"
else
    echo "==> $DOCROOT does not exist yet, deploy.sh will create it"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "==> Installing cron entries (scheduler + queue worker)"
echo "    These point at $APP_ROOT/current, which doesn't exist until your"
echo "    first deploy.sh run — cron will just no-op (harmlessly) every"
echo "    minute until then. Nothing further to do once deploy.sh finishes."
DEPLOY_APP_ROOT="$APP_ROOT" "$SCRIPT_DIR/install-cron.sh"

echo "==> Bootstrap complete. Next: put your production .env at $SHARED_DIR/.env, then run deploy.sh."

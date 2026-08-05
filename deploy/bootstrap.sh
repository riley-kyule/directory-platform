#!/usr/bin/env bash
#
# One-time setup for atomic-release deployment on shared cPanel hosting with
# SSH but no root: no systemd/WHM access, so this uses plain symlinks in the
# account's own home directory instead. Run this once per environment before
# the first deploy.sh. Safe to re-run — it won't touch an existing shared/.env.
#
# What it does:
#   ~/directory-platform/releases/   — each deploy gets its own timestamped dir
#   ~/directory-platform/shared/     — .env, storage/, and public media that
#                                      must persist across releases
#   ~/public_html                    — converted from a real directory to a
#                                      symlink at ~/directory-platform/current/public
#
# If your cPanel account lets you set an arbitrary document root (Domains ->
# Manage -> Document Root), point it at ~/directory-platform/current/public
# instead and skip the public_html symlink step entirely — either approach
# works, this script just defaults to the one that needs no cPanel UI change.
#
# Deploying this as an ADDON DOMAIN: ~/public_html already belongs to the
# account's primary domain on virtually every cPanel account — it is NOT this
# site's document root, and the default behavior below (converting it to a
# symlink, or backing it up if it's a real directory) would touch/take down
# that other site. Set DEPLOY_MANAGE_PUBLIC_HTML=0 to skip that entirely, and
# instead set the addon domain's own Document Root (Domains -> Manage, or at
# creation time) to ~/directory-platform/current/public directly — cPanel's
# own vhost config then points at that stable path permanently, and nothing
# further is needed here since deploy.sh keeps `current` itself repointed
# atomically on every release.
#
#   DEPLOY_MANAGE_PUBLIC_HTML=0 ./bootstrap.sh
set -euo pipefail

APP_ROOT="$HOME/directory-platform"
SHARED_DIR="$APP_ROOT/shared"
PUBLIC_HTML="$HOME/public_html"
MANAGE_PUBLIC_HTML="${DEPLOY_MANAGE_PUBLIC_HTML:-1}"

echo "==> Creating release/shared directory structure at $APP_ROOT"
mkdir -p "$APP_ROOT/releases"
mkdir -p "$SHARED_DIR/storage/app/public" "$SHARED_DIR/storage/app/private/quarantine" "$SHARED_DIR/storage/app/private/media-review" "$SHARED_DIR/storage/media-staging"
mkdir -p "$SHARED_DIR/storage/framework/cache/data" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views" "$SHARED_DIR/storage/logs"
mkdir -p "$SHARED_DIR/public/media/profiles"

if [ -f "$SHARED_DIR/.env" ]; then
    echo "==> $SHARED_DIR/.env already exists, leaving it alone"
else
    echo "==> No shared .env found yet."
    echo "    Copy your production .env to $SHARED_DIR/.env before running deploy.sh —"
    echo "    it is intentionally not created automatically."
fi

if [ "$MANAGE_PUBLIC_HTML" != "1" ]; then
    echo "==> DEPLOY_MANAGE_PUBLIC_HTML=0 — leaving $PUBLIC_HTML untouched"
    echo "    Point this domain's Document Root (Domains -> Manage in cPanel) at:"
    echo "      $APP_ROOT/current/public"
elif [ -L "$PUBLIC_HTML" ]; then
    echo "==> $PUBLIC_HTML is already a symlink, leaving it — deploy.sh will keep it updated"
elif [ -e "$PUBLIC_HTML" ]; then
    BACKUP="${PUBLIC_HTML}.pre-atomic-deploy.$(date +%Y%m%d%H%M%S)"
    echo "==> $PUBLIC_HTML exists as a real directory — moving it to $BACKUP"
    echo "    (nothing is deleted; review and remove that backup yourself once you've"
    echo "    confirmed the new deployment is serving correctly)"
    echo "    If this is an ADDON domain, stop now — see the header comment and rerun"
    echo "    with DEPLOY_MANAGE_PUBLIC_HTML=0 instead; \$HOME/public_html is your"
    echo "    primary domain's docroot, not this site's."
    mv "$PUBLIC_HTML" "$BACKUP"
else
    echo "==> $PUBLIC_HTML does not exist yet, deploy.sh will create it"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "==> Installing cron entries (scheduler + queue worker)"
echo "    These point at $APP_ROOT/current, which doesn't exist until your"
echo "    first deploy.sh run — cron will just no-op (harmlessly) every"
echo "    minute until then. Nothing further to do once deploy.sh finishes."
"$SCRIPT_DIR/install-cron.sh"

echo "==> Bootstrap complete. Next: put your production .env at $SHARED_DIR/.env, then run deploy.sh."

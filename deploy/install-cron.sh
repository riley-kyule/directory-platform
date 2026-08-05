#!/usr/bin/env bash
#
# Installs the two cron entries this app needs, idempotently. Called once
# from bootstrap.sh, but safe to re-run any time (e.g. if PHP_BIN changes).
#
# Why cron and not a persistent `queue:work`: shared cPanel hosting with SSH
# but no root generally has no systemd/supervisor to keep a long-running
# process alive, and a worker that dies silently after a deploy (or a host
# reboot) means queued jobs (profile image processing) just never run again
# until someone notices. `queue:work --stop-when-empty` sidesteps this
# entirely — cron starts a worker every minute, it drains whatever is queued,
# and it exits on its own; nothing needs supervising.
#
# Usage:
#   PHP_BIN=/usr/local/bin/php83 ./install-cron.sh   # PHP_BIN defaults to `php`
set -euo pipefail

APP_ROOT="$HOME/directory-platform"
CURRENT="$APP_ROOT/current"
PHP_BIN="${PHP_BIN:-php}"
MARKER="directory-platform-cron"

if ! command -v crontab >/dev/null 2>&1; then
    cat <<EOF
==> No 'crontab' command available on this host — add these two lines
    yourself via cPanel's "Cron Jobs" UI instead (set PHP_BIN to the exact
    PHP binary cPanel's MultiPHP Manager selected, e.g. /usr/local/bin/php83):

* * * * * cd $CURRENT && $PHP_BIN artisan schedule:run >> /dev/null 2>&1 # $MARKER:scheduler
* * * * * cd $CURRENT && flock -n /tmp/${MARKER}-queue.lock $PHP_BIN artisan queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=45 >> $CURRENT/storage/logs/queue-worker.log 2>&1 # $MARKER:queue
EOF
    exit 0
fi

SCHEDULER_LINE="* * * * * cd $CURRENT && $PHP_BIN artisan schedule:run >> /dev/null 2>&1 # $MARKER:scheduler"
QUEUE_LINE="* * * * * cd $CURRENT && flock -n /tmp/${MARKER}-queue.lock $PHP_BIN artisan queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=45 >> $CURRENT/storage/logs/queue-worker.log 2>&1 # $MARKER:queue"

echo "==> Installing/refreshing cron entries (marker: $MARKER)"
{
    # Drop any previous run's lines for this app, keep everything else the
    # account's crontab already has, then append the current pair.
    crontab -l 2>/dev/null | grep -v "$MARKER" || true
    echo "$SCHEDULER_LINE"
    echo "$QUEUE_LINE"
} | crontab -

echo "==> Installed:"
echo "    $SCHEDULER_LINE"
echo "    $QUEUE_LINE"
echo "==> Verify any time with: crontab -l"

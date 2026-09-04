#!/usr/bin/env bash
#
# Shared helper: resolve a PHP >= 8.3 CLI binary into $PHP_BIN.
#
# cPanel's MultiPHP Manager only sets which PHP handles WEB requests — a bare
# `php` in an SSH shell is usually a much older system default. Rather than make
# every operator hand-find and pass PHP_BIN, the deploy scripts source this and
# auto-detect. An explicit PHP_BIN always wins (it is still validated).
#
# Usage (from a script that has `set -euo pipefail`):
#   . "$(dirname "${BASH_SOURCE[0]}")/lib-php.sh"
#   resolve_php_bin   # sets PHP_BIN or exits 1 with a clear message

# Minimum PHP the application supports.
PHP_MIN_VERSION_ID=80300
PHP_MIN_VERSION_LABEL="8.3"

_php_version_id() {
    "$1" -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0
}

resolve_php_bin() {
    local candidates=() seen=() c resolved ver

    # An explicit PHP_BIN is tried first and, if valid, used as-is.
    if [ -n "${PHP_BIN:-}" ]; then
        candidates+=("$PHP_BIN")
    fi

    # cPanel / EasyApache 4 packages, newest first.
    for c in /opt/cpanel/ea-php{85,84,83}/root/usr/bin/php; do
        [ -x "$c" ] && candidates+=("$c")
    done

    # CloudLinux alt-php and generic version-suffixed names on $PATH.
    for c in php8.5 php85 php8.4 php84 php8.3 php83 php8 php; do
        resolved="$(command -v "$c" 2>/dev/null)" && [ -n "$resolved" ] && candidates+=("$resolved")
    done
    for c in /usr/local/bin/php{8.5,85,8.4,84,8.3,83} /usr/bin/php{8.5,85,8.4,84,8.3,83}; do
        [ -x "$c" ] && candidates+=("$c")
    done

    for c in "${candidates[@]}"; do
        # De-dupe (candidate lists overlap heavily across hosts).
        case " ${seen[*]-} " in *" $c "*) continue ;; esac
        seen+=("$c")

        command -v "$c" >/dev/null 2>&1 || [ -x "$c" ] || continue
        ver="$(_php_version_id "$c")"
        if [ "$ver" -ge "$PHP_MIN_VERSION_ID" ] 2>/dev/null; then
            PHP_BIN="$c"
            return 0
        fi
    done

    echo "error: could not find a PHP $PHP_MIN_VERSION_LABEL+ CLI binary." >&2
    if [ -n "${PHP_BIN:-}" ]; then
        echo "       PHP_BIN=$PHP_BIN is $("${PHP_BIN}" -v 2>/dev/null | head -n1 || echo 'not runnable') — too old or missing." >&2
    fi
    echo "       Looked in /opt/cpanel/ea-php8*, and for php8.3/php83/... on \$PATH." >&2
    echo "       Find your host's newer PHP CLI (often under /opt/cpanel/ea-php8*/root/usr/bin/php" >&2
    echo "       or /usr/local/bin/php8x) and re-run with PHP_BIN=/full/path/to/php prepended." >&2
    return 1
}

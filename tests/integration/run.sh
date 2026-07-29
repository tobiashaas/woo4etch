#!/usr/bin/env bash
#
# Woo4Etch integration checks (issue #12, layers 4–5) — run the checks/*.php
# files through `wp eval-file` against a REAL WordPress + WooCommerce +
# Woo4Etch install. All checks are non-destructive: temp files/dirs only,
# every mutation reverted in `finally`. Safe on a live staging site.
#
# Local install:
#   tests/integration/run.sh --path /var/www/mysite
#
# Remote over SSH (key auth, or export SSHPASS and have sshpass installed):
#   tests/integration/run.sh --ssh user@host --path /var/www/mysite
#
# Inside the repo's wp-env instance (requires `.wp-env.json` mapping
# wp-content/woo4etch-tests to ./tests, as the repo's config does):
#   tests/integration/run.sh --wp-env
#
set -u

SSH_TARGET=""
WP_PATH=""
WP_ENV=0
while [ $# -gt 0 ]; do
    case "$1" in
        --ssh)    SSH_TARGET="$2"; shift 2 ;;
        --path)   WP_PATH="$2"; shift 2 ;;
        --wp-env) WP_ENV=1; shift ;;
        *) echo "unknown argument: $1" >&2; exit 2 ;;
    esac
done
if [ -z "$WP_PATH" ] && [ "$WP_ENV" -eq 0 ]; then
    echo "usage: run.sh [--ssh user@host] --path /path/to/wordpress | run.sh --wp-env" >&2
    exit 2
fi

CHECKS_DIR="$(cd "$(dirname "$0")" && pwd)/checks"

SSH_BIN="ssh"
SCP_BIN="scp"
if [ -n "${SSHPASS:-}" ] && command -v sshpass >/dev/null 2>&1; then
    SSH_BIN="sshpass -e ssh"
    SCP_BIN="sshpass -e scp"
fi

fail=0

if [ "$WP_ENV" -eq 1 ]; then
    # Inside the cli container the site URL (host-mapped port) is unreachable;
    # check 04 rewrites requests to the wordpress service via W4E_IT_HTTP_BASE.
    for check in "$CHECKS_DIR"/[0-9]*.php; do
        name=$(basename "$check")
        npx @wordpress/env run cli -- env W4E_IT_HTTP_BASE=http://wordpress wp eval-file "wp-content/woo4etch-tests/integration/checks/$name" || fail=1
    done
elif [ -n "$SSH_TARGET" ]; then
    REMOTE_DIR=$($SSH_BIN "$SSH_TARGET" "mktemp -d /tmp/w4e-integration.XXXXXX") || exit 1
    trap '$SSH_BIN "$SSH_TARGET" "rm -rf $REMOTE_DIR"' EXIT
    $SCP_BIN -q "$CHECKS_DIR"/*.php "$SSH_TARGET:$REMOTE_DIR/" || exit 1

    for check in "$CHECKS_DIR"/[0-9]*.php; do
        name=$(basename "$check")
        $SSH_BIN "$SSH_TARGET" "cd '$WP_PATH' && wp eval-file '$REMOTE_DIR/$name'" || fail=1
    done
else
    for check in "$CHECKS_DIR"/[0-9]*.php; do
        wp --path="$WP_PATH" eval-file "$check" || fail=1
    done
fi

echo "------------------------------------------------------------"
if [ "$fail" -ne 0 ]; then
    echo "INTEGRATION: FAIL"
    exit 1
fi
echo "INTEGRATION: PASS"

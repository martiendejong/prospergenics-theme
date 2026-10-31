#!/usr/bin/env bash
# Boot a throwaway WordPress (SQLite, no MySQL) with the built theme, seed one user per role and
# run the role/permission matrix against it (audit ARC-18). CI runs exactly this script
# (.github/workflows/ci.yml, job "wordpress-roles"); locally:
#
#   bash tests/e2e/run.sh            # everything: download, install, seed, serve, matrix
#   E2E_PORT=8096 E2E_DIR=/some/dir bash tests/e2e/run.sh
#
# Needs php (with pdo_sqlite), node >= 18, curl, unzip, tar. Downloads are pinned and checked
# against their SHA-256 and cached in $E2E_CACHE.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PORT="${E2E_PORT:-8096}"
WORK="${E2E_DIR:-${RUNNER_TEMP:-/tmp}/pg-e2e}"
CACHE="${E2E_CACHE:-$WORK/cache}"
SITE="$WORK/site"
BASE="http://localhost:$PORT"

# Same versions as the local audit site (scratchpad/wp/site): WordPress 6.8.3 with the
# SQLite Database Integration plugin 3.1.1, driven by WP-CLI 2.11.0.
WP_VERSION=6.8.3
WP_SHA256=92da34c9960e64d1258652c1ef73c517f7e46ac6dfd2dfc75436d3855af46b0c
SQLITE_VERSION=3.1.1
SQLITE_SHA256=5681502868907644ecc472739a75c72453b662c44bcc5dd7c1b1410f988bad44
WPCLI_VERSION=2.11.0
WPCLI_SHA256=a39021ac809530ea607580dbf93afbc46ba02f86b6cffd03de4b126ca53079f6

fetch() { # url file sha256
    if [ ! -f "$CACHE/$2" ]; then
        curl -sSfL --retry 3 -o "$CACHE/$2.part" "$1"
        mv "$CACHE/$2.part" "$CACHE/$2"
    fi
    echo "$3  $CACHE/$2" | sha256sum -c --quiet - || { echo "checksum mismatch for $2" >&2; rm -f "$CACHE/$2"; exit 1; }
}

wp() { php "$CACHE/wp-cli-$WPCLI_VERSION.phar" --allow-root --path="$SITE" "$@"; }

echo "== download (pinned)"
mkdir -p "$CACHE"
fetch "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" "wordpress-$WP_VERSION.tar.gz" "$WP_SHA256"
fetch "https://downloads.wordpress.org/plugin/sqlite-database-integration.$SQLITE_VERSION.zip" "sqlite-database-integration.$SQLITE_VERSION.zip" "$SQLITE_SHA256"
fetch "https://github.com/wp-cli/wp-cli/releases/download/v$WPCLI_VERSION/wp-cli-$WPCLI_VERSION.phar" "wp-cli-$WPCLI_VERSION.phar" "$WPCLI_SHA256"

echo "== install WordPress $WP_VERSION + SQLite $SQLITE_VERSION in $SITE"
rm -rf "${SITE:?}"
mkdir -p "$SITE"
tar -xzf "$CACHE/wordpress-$WP_VERSION.tar.gz" -C "$SITE" --strip-components=1
unzip -q "$CACHE/sqlite-database-integration.$SQLITE_VERSION.zip" -d "$SITE/wp-content/plugins"
# The drop-in the plugin writes on activation, with its placeholders filled in.
sed -e "s#'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'#__DIR__ . '/plugins/sqlite-database-integration'#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
    "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
{
    echo "<?php"
    echo "define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );"
    echo "define( 'DB_CHARSET', 'utf8' ); define( 'DB_COLLATE', '' );"
    for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
        echo "define( '$k', '$(php -r 'echo bin2hex(random_bytes(32));')' );"
    done
    echo "define( 'WP_HOME', '$BASE' ); define( 'WP_SITEURL', '$BASE' );"
    echo "define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', '$WORK/debug.log' ); define( 'WP_DEBUG_DISPLAY', false );"
    echo "define( 'DISABLE_WP_CRON', true );"
    echo "define( 'PG_E2E', true ); // enables tests/e2e/mu-plugin.php"
    echo "\$table_prefix = 'wp_';"
    echo "if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }"
    echo "require_once ABSPATH . 'wp-settings.php';"
} > "$SITE/wp-config.php"
rm -f "$WORK/debug.log"

# The built theme (wp-theme/ is generated from design/ and kept in sync by the "test" job),
# the static pages the deploy uploads next to WordPress, and the php -S router.
cp -r "$REPO/wp-theme/prospergenics-2026" "$SITE/wp-content/themes/"
for d in experts community people; do cp -r "$REPO/design/new-hero-2026-10/$d" "$SITE/"; done
cp "$REPO/tests/e2e/router.php" "$SITE/router.php"
mkdir -p "$SITE/wp-content/mu-plugins"
cp "$REPO/tests/e2e/mu-plugin.php" "$SITE/wp-content/mu-plugins/pg-e2e.php"

wp core install --url="$BASE" --title="ProsperGenics e2e" --admin_user=admin \
    --admin_password=e2e-pass-123 --admin_email=admin@example.test --skip-email
wp plugin activate sqlite-database-integration
wp rewrite structure '/%postname%/'
wp theme activate prospergenics-2026

echo "== seed"
wp eval-file "$REPO/tests/e2e/seed.php" > "$WORK/fixtures.json"
wp rewrite flush
cat "$WORK/fixtures.json"

echo "== serve $BASE"
(cd "$SITE" && exec php -S "localhost:$PORT" router.php) > "$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do
    curl -s -o /dev/null "$BASE/wp-login.php" && break
    sleep 0.2
done

echo "== role matrix"
status=0
E2E_BASE="$BASE" E2E_FIXTURES="$WORK/fixtures.json" node "$REPO/tests/e2e/role-matrix.js" || status=1

# Any PHP fatal error or uncaught exception while the matrix ran fails the job too.
if [ -f "$WORK/debug.log" ] && grep -E 'PHP (Fatal error|Parse error)|Uncaught' "$WORK/debug.log"; then
    echo "PHP fatal errors in $WORK/debug.log" >&2
    status=1
fi
exit $status

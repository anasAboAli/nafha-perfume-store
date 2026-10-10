#!/usr/bin/env bash
# Builds the upload package for the live site (see docs/DEPLOY-FREE.md):
#   dist/nafha-site.zip         WordPress root for htdocs/ (wp-config.php with placeholders)
#   dist/nafha-db.sql           database with the live URL, for phpMyAdmin → Import
#   dist/deploy-credentials.txt new admin login (git-ignored — keep it private)
#
# Usage (Git Bash, from the project root):
#   tools/deploy/build.sh https://nafha.example.com [admin-email]
#
# Works on a throwaway copy of the database; the local site is not changed.
set -euo pipefail

URL="${1:?Usage: tools/deploy/build.sh https://your-site [admin-email]}"
URL="${URL%/}"
EMAIL="${2:-}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DIST="$ROOT/dist"
PHP=/c/xampp/php/php.exe
export PATH="/c/xampp/mysql/bin:$PATH"
DB=perfume_store
COPY=perfume_store_deploy
SQL=(mysql -uroot -h127.0.0.1)
WP=("$ROOT/tools/wp.sh" --skip-plugins=wordfence,updraftplus)

mkdir -p "$DIST"
echo "1/5 Copying the database (without Wordfence tables)…"
"${SQL[@]}" -e "DROP DATABASE IF EXISTS $COPY; CREATE DATABASE $COPY CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci"
mapfile -t IGNORE < <("${SQL[@]}" -N -e "SELECT CONCAT('--ignore-table=$DB.', table_name) FROM information_schema.tables WHERE table_schema='$DB' AND table_name LIKE 'wp\\_wf%'" | tr -d '\r')
mysqldump -uroot -h127.0.0.1 --single-transaction "${IGNORE[@]}" "$DB" | "${SQL[@]}" "$COPY"

echo "2/5 Switching URLs to $URL…"
export NAFHA_DB_NAME=$COPY
"${WP[@]}" search-replace 'http://localhost:8080' "$URL" --all-tables-with-prefix --quiet
"${WP[@]}" search-replace 'http:\/\/localhost:8080' "${URL//\//\\/}" --all-tables-with-prefix --quiet

echo "3/5 Preparing for production…"
NAFHA_ADMIN_EMAIL="$EMAIL" NAFHA_CREDENTIALS_FILE="$DIST/deploy-credentials.txt" \
	"${WP[@]}" eval-file "$ROOT/tools/deploy/prepare-db.php"

echo "4/5 Exporting the database…"
mysqldump -uroot -h127.0.0.1 --single-transaction --add-drop-table --skip-comments "$COPY" > "$DIST/nafha-db.sql"
"${SQL[@]}" -e "DROP DATABASE $COPY"
unset NAFHA_DB_NAME
awk -v s="$(stat -c %s "$DIST/nafha-db.sql")" 'BEGIN { printf "    dist/nafha-db.sql (%.1f MB)\n", s / 1048576 }'

echo "5/5 Packing the site files…"
"$PHP" -r '
$salts = "";
foreach ( array( "AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY", "AUTH_SALT", "SECURE_AUTH_SALT", "LOGGED_IN_SALT", "NONCE_SALT" ) as $k ) {
	$salts .= sprintf( "define( %-20s %s );\n", "\x27$k\x27,", var_export( bin2hex( random_bytes( 32 ) ), true ) );
}
echo str_replace( "{{SALTS}}", $salts, file_get_contents( $argv[1] ) );
' "$ROOT/tools/deploy/wp-config-production.php" > "$DIST/wp-config.php"
"$PHP" "$ROOT/tools/deploy/pack.php" "$ROOT/public" "$DIST/nafha-site.zip" "$DIST/wp-config.php"
rm "$DIST/wp-config.php"

echo "Done. Admin login saved to dist/deploy-credentials.txt"

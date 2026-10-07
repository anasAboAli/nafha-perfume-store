#!/usr/bin/env bash
# WP-CLI wrapper for the local XAMPP environment (silences PHP 8.2 deprecations from the phar).
exec /c/xampp/php/php.exe -d error_reporting=E_ALL\&~E_DEPRECATED "$(dirname "$0")/wp-cli.phar" --path="$(dirname "$0")/../public" "$@"

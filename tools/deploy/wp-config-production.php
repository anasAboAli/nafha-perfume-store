<?php
/**
 * Live-site configuration. Fill in the four database values from the hosting
 * control panel (InfinityFree: Control Panel → MySQL Databases).
 */

define( 'DB_NAME', 'PUT_DATABASE_NAME_HERE' );
define( 'DB_USER', 'PUT_DATABASE_USER_HERE' );
define( 'DB_PASSWORD', 'PUT_DATABASE_PASSWORD_HERE' );
define( 'DB_HOST', 'PUT_DATABASE_HOST_HERE' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

{{SALTS}}
$table_prefix = 'wp_';

define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_POST_REVISIONS', 5 );
define( 'EMPTY_TRASH_DAYS', 14 );

// HTTPS behind the host's proxy / Cloudflare.
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';

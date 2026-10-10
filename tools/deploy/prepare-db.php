<?php
/**
 * Turns the deploy copy of the database into a clean production database.
 * Run by tools/deploy/build.sh against the copy (NAFHA_DB_NAME), never the local DB.
 *
 * - Plugins that don't fit free hosting are deactivated (Wordfence: CPU/entry-process
 *   limits; UpdraftPlus: backups would fill the account — Git + the SQL export cover it).
 * - Local-only Turnstile test keys are cleared (real keys are entered after publishing).
 * - The "admin" account gets a new login and a strong random password, saved to
 *   dist/deploy-credentials.txt (git-ignored).
 * - Sessions, transients and finished background jobs are dropped.
 *
 * @package Nafha
 */

defined( 'ABSPATH' ) || exit;

if ( 'perfume_store' === DB_NAME ) {
	WP_CLI::error( 'Refusing to run against the local database.' );
}

global $wpdb;

// Plugins.
$drop    = array( 'wordfence/wordfence.php', 'updraftplus/updraftplus.php' );
$active  = array_values( array_diff( (array) get_option( 'active_plugins', array() ), $drop ) );
update_option( 'active_plugins', $active );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'updraft%' OR option_name LIKE 'wordfence%' OR option_name LIKE 'wf\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$cron = _get_cron_array();
foreach ( $cron as $time => $hooks ) {
	foreach ( array_keys( $hooks ) as $hook ) {
		if ( preg_match( '/^(wordfence|wf_|updraft)/i', $hook ) ) {
			unset( $cron[ $time ][ $hook ] );
		}
	}
	if ( empty( $cron[ $time ] ) ) {
		unset( $cron[ $time ] );
	}
}
_set_cron_array( $cron );

// Turnstile: no protection until the real site keys are entered and tested.
update_option( 'cfturnstile_key', '' );
update_option( 'cfturnstile_secret', '' );
update_option( 'cfturnstile_tested', 'no' );

// Admin account.
$login    = getenv( 'NAFHA_ADMIN_LOGIN' ) ?: 'nafha_manager';
$email    = getenv( 'NAFHA_ADMIN_EMAIL' ) ?: '';
$user     = get_user_by( 'login', 'admin' ) ?: get_user_by( 'login', $login );
$password = wp_generate_password( 24, true, false );
if ( ! $user ) {
	WP_CLI::error( 'Administrator account not found.' );
}
$wpdb->update( $wpdb->users, array( 'user_login' => $login, 'user_nicename' => sanitize_title( $login ) ), array( 'ID' => $user->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_user_cache( $user->ID );
wp_set_password( $password, $user->ID );
if ( is_email( $email ) ) {
	wp_update_user( array( 'ID' => $user->ID, 'user_email' => $email ) );
	update_option( 'admin_email', $email );
	update_option( 'new_admin_email', $email );
	update_option( 'woocommerce_email_from_address', $email );
	update_option( 'woocommerce_stock_email_recipient', $email );
	$new_order = (array) get_option( 'woocommerce_new_order_settings', array() );
	$new_order['recipient'] = $email;
	update_option( 'woocommerce_new_order_settings', $new_order );
}

// Leftovers.
$wpdb->query( "TRUNCATE {$wpdb->prefix}woocommerce_sessions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status IN ('complete','failed','canceled')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE l FROM {$wpdb->prefix}actionscheduler_logs l LEFT JOIN {$wpdb->prefix}actionscheduler_actions a ON a.action_id = l.action_id WHERE a.action_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$out = getenv( 'NAFHA_CREDENTIALS_FILE' );
if ( $out ) {
	file_put_contents( $out, "Site: " . home_url( '/nafha-login/' ) . "\nUsername: {$login}\nPassword: {$password}\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
WP_CLI::success( 'Deploy database prepared for ' . home_url() . " (admin login: {$login})." );

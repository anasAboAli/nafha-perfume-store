<?php
/**
 * Plugin Name:       Nafha Core
 * Description:       Store features for the Nafha perfume-decant shop: fragrance notes fields, WhatsApp ordering, fragrance quiz, side cart, free-shipping bar, tracking events.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Nafha
 * Text Domain:       nafha-core
 * Domain Path:       /languages
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'NAFHA_CORE_VERSION', '0.1.0' );
define( 'NAFHA_CORE_FILE', __FILE__ );
define( 'NAFHA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAFHA_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load feature modules once WooCommerce is available.
 * Each module is a self-contained file in /includes registering its own hooks.
 */
function nafha_core_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$modules = array(
		'class-qatar-areas.php',
		'class-shipping.php',
		'class-settings.php',
		'class-fragrance-profile.php',
		'class-variation-buttons.php',
		'class-whatsapp.php',
		'class-product-page.php',
		'class-review-photos.php',
		'class-shop-filters.php',
		'class-search.php',
		'class-cart.php',
		'class-checkout.php',
		'class-whatsapp-gateway.php',
		'class-quiz.php',
		'class-offers.php',
		'class-reviews-showcase.php',
		'class-import-export.php',
	);

	foreach ( $modules as $module ) {
		require_once NAFHA_CORE_DIR . 'includes/' . $module;
	}
}
add_action( 'plugins_loaded', 'nafha_core_boot' );

/**
 * Source strings are English; Arabic ships in languages/nafha-core-ar.l10n.php.
 */
function nafha_core_load_textdomain() {
	load_plugin_textdomain( 'nafha-core', false, dirname( plugin_basename( NAFHA_CORE_FILE ) ) . '/languages' );
}
add_action( 'init', 'nafha_core_load_textdomain' );

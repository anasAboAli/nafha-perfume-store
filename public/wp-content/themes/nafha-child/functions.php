<?php
/**
 * Nafha Child theme bootstrap.
 *
 * Presentation only (styles, fonts, template tweaks). Store features
 * (custom fields, WhatsApp ordering, quiz, …) live in the nafha-core plugin.
 *
 * @package Nafha_Child
 */

defined( 'ABSPATH' ) || exit;

define( 'NAFHA_CHILD_VERSION', '0.1.0' );

/**
 * Enqueue child stylesheet after Blocksy's own styles.
 */
function nafha_child_enqueue_assets() {
	wp_enqueue_style(
		'nafha-child',
		get_stylesheet_directory_uri() . '/assets/css/main.css',
		array( 'ct-main-styles' ),
		NAFHA_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'nafha_child_enqueue_assets', 20 );

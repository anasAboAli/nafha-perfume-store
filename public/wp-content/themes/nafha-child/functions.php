<?php
/**
 * Nafha Child theme bootstrap.
 *
 * Presentation only (styles, fonts, header/footer tweaks, mobile navigation).
 * Store features (custom fields, WhatsApp ordering, quiz, …) live in the
 * nafha-core plugin so they survive a theme change.
 *
 * @package Nafha_Child
 */

defined( 'ABSPATH' ) || exit;

define( 'NAFHA_CHILD_VERSION', '0.2.0' );
define( 'NAFHA_CHILD_DIR', get_stylesheet_directory() );
define( 'NAFHA_CHILD_URL', get_stylesheet_directory_uri() );

require_once NAFHA_CHILD_DIR . '/inc/assets.php';
require_once NAFHA_CHILD_DIR . '/inc/announcement-bar.php';
require_once NAFHA_CHILD_DIR . '/inc/mobile-nav.php';

/**
 * Source strings are English; Arabic ships in languages/ar.l10n.php.
 */
function nafha_child_setup() {
	load_child_theme_textdomain( 'nafha-child', NAFHA_CHILD_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'nafha_child_setup' );

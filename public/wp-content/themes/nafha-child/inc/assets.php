<?php
/**
 * Styles, self-hosted fonts and site icons.
 *
 * @package Nafha_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Child stylesheet loads after Blocksy's so brand tokens override its variables.
 */
function nafha_child_enqueue_assets() {
	wp_enqueue_style(
		'nafha-child',
		NAFHA_CHILD_URL . '/assets/css/main.css',
		array( 'ct-main-styles' ),
		// File mtime busts browser/CDN caches on every deploy of a changed file.
		(string) filemtime( NAFHA_CHILD_DIR . '/assets/css/main.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'nafha_child_enqueue_assets', 20 );

/**
 * Preload the two Arabic font files used above the fold, so text renders in
 * the brand font on first paint instead of swapping.
 */
function nafha_child_preload_fonts() {
	foreach ( array( 400, 700 ) as $weight ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( NAFHA_CHILD_URL . '/assets/fonts/plex-arabic-arabic-' . $weight . '.woff2' )
		);
	}
}
add_action( 'wp_head', 'nafha_child_preload_fonts', 1 );

/**
 * Site icons. Used only while no Site Icon is set in Customizer, so the
 * client can replace them without touching code.
 */
function nafha_child_site_icons() {
	if ( has_site_icon() ) {
		return;
	}
	$img = NAFHA_CHILD_URL . '/assets/img/';
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( $img . 'favicon.svg' ) );
	printf( '<link rel="icon" href="%s" sizes="512x512" type="image/png">' . "\n", esc_url( $img . 'icon-512.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $img . 'icon-180.png' ) );
	echo '<meta name="theme-color" content="#2B2130">' . "\n";
}
add_action( 'wp_head', 'nafha_child_site_icons', 2 );
add_action( 'admin_head', 'nafha_child_site_icons' );
add_action( 'login_head', 'nafha_child_site_icons' );

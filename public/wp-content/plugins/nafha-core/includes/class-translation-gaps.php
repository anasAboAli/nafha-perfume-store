<?php
/**
 * Fills strings that the official Arabic language packs of Blocksy and
 * WooCommerce leave in English. They are mostly screen-reader labels
 * (found by tools/e2e/i18n-scan.js). The text goes through nafha-core's own
 * translation file, and only when the pack has no translation of its own,
 * so a future pack update takes precedence automatically.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Translation_Gaps {

	const DOMAINS = array( 'blocksy', 'woocommerce' );

	public static function register() {
		add_filter( 'gettext', array( __CLASS__, 'fill' ), 10, 3 );
		add_filter( 'login_display_language_dropdown', '__return_false' ); // Single-language site.
	}

	/**
	 * Original text => translated text (built once, on first use).
	 *
	 * @return array<string, string>
	 */
	private static function map() {
		static $map = null;
		if ( null === $map ) {
			$map = array(
				// Blocksy.
				'Close drawer'              => __( 'Close drawer', 'nafha-core' ),
				'Close search modal'        => __( 'Close search modal', 'nafha-core' ),
				'Offcanvas modal'           => __( 'Offcanvas modal', 'nafha-core' ),
				'Products pagination'       => __( 'Products pagination', 'nafha-core' ),
				'Posts pagination'          => __( 'Posts pagination', 'nafha-core' ),
				'Search results pagination' => __( 'Search results pagination', 'nafha-core' ),
				// WooCommerce.
				'Product Details'           => __( 'Product Details', 'nafha-core' ),
				/* translators: %s: product name. */
				'Remove %s from cart'       => __( 'Remove %s from cart', 'nafha-core' ),
			);
		}
		return $map;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function fill( $translation, $text, $domain ) {
		if ( $translation !== $text || ! in_array( $domain, self::DOMAINS, true ) || ! did_action( 'init' ) ) {
			return $translation; // Already translated by the language pack, or not ours to fill.
		}
		return self::map()[ $text ] ?? $translation;
	}
}

Nafha_Translation_Gaps::register();

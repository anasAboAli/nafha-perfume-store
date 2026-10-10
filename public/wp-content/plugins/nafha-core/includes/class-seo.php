<?php
/**
 * SEO adjustments on top of Rank Math.
 *
 * Rank Math names the Product schema after the SEO title template
 * ("Sauvage | Original decants | Nafha"); Google expects the product's own
 * name there, so it is reset to the product name (plus the English name as
 * an alternate). Brand comes from WooCommerce Brands via Rank Math's
 * general.product_brand setting (see tools/setup/store-setup.php).
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_SEO {

	public static function register() {
		// Late: Rank Math fills the WooCommerce product data after its entity filters run.
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'fix_product_name' ), 99 );
	}

	/**
	 * @param array $data All JSON-LD entities for the page, keyed by Rank Math.
	 * @return array
	 */
	public static function fix_product_name( $data ) {
		foreach ( $data as $key => $entity ) {
			if ( is_array( $entity ) ) {
				$data[ $key ] = self::product_entity( $entity );
			}
		}
		return $data;
	}

	/**
	 * @param array $entity JSON-LD entity.
	 * @return array
	 */
	private static function product_entity( $entity ) {
		$type = (array) ( $entity['@type'] ?? array() );
		if ( ! in_array( 'Product', $type, true ) || ! is_singular( 'product' ) ) {
			return $entity;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return $entity;
		}

		$entity['name'] = $product->get_name();
		$name_en        = $product->get_meta( '_nafha_name_en' );
		if ( $name_en ) {
			$entity['alternateName'] = $name_en;
		}
		return $entity;
	}
}

Nafha_SEO::register();

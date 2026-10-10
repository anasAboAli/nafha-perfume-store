<?php
/**
 * Product feed for Google Merchant Center and Meta Catalog (both accept the
 * Google RSS 2.0 format):  https://<site>/feed/nafha-products/
 *
 * One item per purchasable size, grouped by perfume (item_group_id), linking
 * to the product page with that size pre-selected. Cached for an hour and
 * invalidated whenever a product changes.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Product_Feed {

	const FEED = 'nafha-products';

	/** Google product category: Health & Beauty > Personal Care > Cosmetics > Perfume & Cologne. */
	const GOOGLE_CATEGORY = 479;

	public static function register() {
		add_action( 'init', static fn() => add_feed( self::FEED, array( __CLASS__, 'render' ) ) );
	}

	public static function url() {
		return get_feed_link( self::FEED );
	}

	public static function render() {
		$key = 'nafha_feed_' . WC_Cache_Helper::get_transient_version( 'product' );
		$xml = get_transient( $key );
		if ( false === $xml ) {
			$xml = self::build();
			set_transient( $key, $xml, HOUR_IN_SECONDS );
		}
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_xml() below.
	}

	private static function gender( $product_id ) {
		$slugs = wc_get_product_terms( $product_id, 'pa_gender', array( 'fields' => 'slugs' ) );
		$map   = array( 'men' => 'male', 'women' => 'female', 'unisex' => 'unisex' );
		return $slugs ? ( $map[ $slugs[0] ] ?? '' ) : '';
	}

	/**
	 * @return string RSS document.
	 */
	private static function build() {
		$ids = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => -1,
				'return'     => 'ids',
			)
		);

		$items = '';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$brands  = get_the_terms( $id, 'product_brand' );
			$cats    = get_the_terms( $id, 'product_cat' );
			$common  = array(
				'description'             => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
				'image_link'              => wp_get_attachment_url( $product->get_image_id() ),
				'brand'                   => $brands && ! is_wp_error( $brands ) ? $brands[0]->name : get_bloginfo( 'name' ),
				'product_type'            => $cats && ! is_wp_error( $cats ) ? $cats[0]->name : '',
				'google_product_category' => self::GOOGLE_CATEGORY,
				'condition'               => 'new',
				'identifier_exists'       => 'no', // Decants have no GTIN/MPN.
				'gender'                  => self::gender( $id ),
				'age_group'               => 'adult',
			);
			$gallery = array_slice( array_filter( array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() ) ), 0, 5 );

			$variants = $product->is_type( 'variable' ) ? $product->get_children() : array( $id );
			foreach ( $variants as $variant_id ) {
				$variant = wc_get_product( $variant_id );
				if ( ! $variant || ! $variant->is_purchasable() || '' === $variant->get_price() ) {
					continue;
				}
				$is_variation = $variant->is_type( 'variation' );
				$size         = $is_variation ? wc_get_formatted_variation( $variant, true, false, false ) : '';
				$fields       = array_merge(
					array(
						'id'           => $variant->get_sku() ? $variant->get_sku() : (string) $variant_id,
						'item_group_id' => $is_variation ? ( $product->get_sku() ? $product->get_sku() : (string) $id ) : '',
						'title'        => trim( $product->get_name() . ( $common['brand'] ? ' — ' . $common['brand'] : '' ) . ( $size ? ' — ' . $size : '' ) ),
						'link'         => $is_variation ? add_query_arg( $variant->get_variation_attributes(), get_permalink( $id ) ) : get_permalink( $id ),
						'availability' => $variant->is_in_stock() ? 'in_stock' : 'out_of_stock',
						'price'        => number_format( (float) $variant->get_regular_price(), 2, '.', '' ) . ' ' . get_woocommerce_currency(),
						'size'         => $size,
					),
					$common
				);
				if ( $variant->is_on_sale() ) {
					$fields['sale_price'] = number_format( (float) $variant->get_sale_price(), 2, '.', '' ) . ' ' . get_woocommerce_currency();
				}

				$items .= "\t<item>\n";
				foreach ( array_filter( $fields, static fn( $v ) => '' !== (string) $v ) as $tag => $value ) {
					$items .= "\t\t<g:{$tag}>" . esc_xml( (string) $value ) . "</g:{$tag}>\n";
				}
				foreach ( $gallery as $image ) {
					$items .= "\t\t<g:additional_image_link>" . esc_xml( $image ) . "</g:additional_image_link>\n";
				}
				$items .= "\t</item>\n";
			}
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n"
			. "\t<title>" . esc_xml( get_bloginfo( 'name' ) ) . "</title>\n"
			. "\t<link>" . esc_xml( home_url( '/' ) ) . "</link>\n"
			. "\t<description>" . esc_xml( get_bloginfo( 'description' ) ) . "</description>\n"
			. $items
			. "</channel>\n</rss>\n";
	}
}

Nafha_Product_Feed::register();

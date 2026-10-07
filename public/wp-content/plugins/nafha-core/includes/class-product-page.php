<?php
/**
 * Single-product page: assets, stock indicator, related / "you may also like".
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Product_Page {

	const RELATED_LIMIT = 4;

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'woocommerce_get_availability', array( __CLASS__, 'availability' ), 10, 2 );
		add_filter( 'woocommerce_variable_price_html', array( __CLASS__, 'from_price' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'card_button_text' ), 10, 2 );

		add_filter( 'woocommerce_related_products', array( __CLASS__, 'related_by_family' ), 10, 3 );
		// Keep our relevance order: WooCommerce shuffles IDs and then sorts the products randomly again.
		add_filter( 'woocommerce_product_related_posts_shuffle', '__return_false' );
		add_filter( 'woocommerce_output_related_products_args', array( __CLASS__, 'keep_order' ), 20 );
		add_filter( 'woocommerce_upsell_display_args', array( __CLASS__, 'keep_order' ), 20 );
		add_filter( 'woocommerce_product_related_products_heading', array( __CLASS__, 'related_heading' ) );
		add_filter( 'woocommerce_product_upsells_products_heading', array( __CLASS__, 'upsells_heading' ) );
		add_filter( 'woocommerce_product_get_upsell_ids', array( __CLASS__, 'auto_upsells' ), 10, 2 );
	}

	public static function enqueue() {
		if ( ! is_product() ) {
			return;
		}
		$css = 'assets/css/product.css';
		$js  = 'assets/js/product.js';
		wp_enqueue_style( 'nafha-product', NAFHA_CORE_URL . $css, array(), (string) filemtime( NAFHA_CORE_DIR . $css ) );
		wp_enqueue_script( 'nafha-product', NAFHA_CORE_URL . $js, array( 'jquery' ), (string) filemtime( NAFHA_CORE_DIR . $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'nafha-product',
			'nafhaProduct',
			array(
				'i18n' => array(
					'intro'      => __( 'Hello, I would like to order:', 'nafha-core' ),
					'size'       => __( 'Size', 'nafha-core' ),
					'quantity'   => __( 'Quantity', 'nafha-core' ),
					'price'      => __( 'Price', 'nafha-core' ),
					'chooseSize' => __( 'Please choose a size first.', 'nafha-core' ),
				),
			)
		);
	}

	/**
	 * "From $9" instead of "$9 – $165": the range is mostly the full bottle and reads as expensive.
	 *
	 * @param string              $html    Price range HTML.
	 * @param WC_Product_Variable $product Product.
	 * @return string
	 */
	public static function from_price( $html, $product ) {
		$min = $product->get_variation_price( 'min', true );
		if ( $min === $product->get_variation_price( 'max', true ) ) {
			return $html;
		}
		/* translators: %s: lowest variation price. */
		return sprintf( __( 'From %s', 'nafha-core' ), wc_price( $min ) );
	}

	/**
	 * Product cards: "Choose size" says what the click is for (WooCommerce: "Select options").
	 *
	 * @param string     $text    Button text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function card_button_text( $text, $product ) {
		return $product->is_type( 'variable' ) && $product->is_purchasable() ? __( 'Choose size', 'nafha-core' ) : $text;
	}

	/* ------------------------------------------------------------ stock */

	/**
	 * Three clear states: in stock / limited quantity / out of stock.
	 *
	 * @param array      $availability { availability: string, class: string }.
	 * @param WC_Product $product      Product or variation.
	 * @return array
	 */
	public static function availability( $availability, $product ) {
		if ( ! $product->is_in_stock() ) {
			return array(
				'availability' => __( 'Out of stock', 'nafha-core' ),
				'class'        => 'out-of-stock',
			);
		}

		$qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
		if ( null !== $qty && ! $product->backorders_allowed() && $qty <= wc_get_low_stock_amount( $product ) ) {
			return array(
				/* translators: %d: units left in stock. */
				'availability' => sprintf( _n( 'Limited quantity — only %d left', 'Limited quantity — only %d left', $qty, 'nafha-core' ), $qty ),
				'class'        => 'in-stock nafha-low-stock',
			);
		}

		return array(
			'availability' => __( 'In stock', 'nafha-core' ),
			'class'        => 'in-stock',
		);
	}

	/* ---------------------------------------------------------- related */

	/**
	 * Visible, in-stock products matching $tax_query, best sellers first.
	 *
	 * @return int[]
	 */
	private static function query( $tax_query, $exclude, $limit ) {
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-catalog', 'outofstock' ),
			'operator' => 'NOT IN',
		);
		return get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'post__not_in'   => $exclude,
				'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery -- small catalogue.
				'orderby'        => 'meta_value_num',
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	/**
	 * Restricts to products the same customer would wear: a men's perfume
	 * matches men's + unisex; a unisex one matches everything.
	 *
	 * @return array[] Zero or one tax_query clause.
	 */
	private static function gender_clause( $product_id ) {
		$genders = wc_get_product_terms( $product_id, 'pa_gender', array( 'fields' => 'slugs' ) );
		if ( ! $genders || in_array( 'unisex', $genders, true ) ) {
			return array();
		}
		return array(
			array(
				'taxonomy' => 'pa_gender',
				'field'    => 'slug',
				'terms'    => array_merge( $genders, array( 'unisex' ) ),
			),
		);
	}

	/**
	 * Same scent family and a compatible gender; products sharing more
	 * families rank first, best sellers break ties.
	 *
	 * @return int[]
	 */
	private static function similar( $product_id, $exclude, $limit ) {
		$families = wc_get_product_terms( $product_id, 'pa_fragrance-family', array( 'fields' => 'ids' ) );
		if ( ! $families ) {
			return array();
		}
		$tax_query  = array_merge(
			array(
				array(
					'taxonomy' => 'pa_fragrance-family',
					'terms'    => $families,
				),
			),
			self::gender_clause( $product_id )
		);
		$candidates = self::query( $tax_query, $exclude, 50 ); // Already in best-seller order.

		$score = array();
		foreach ( $candidates as $rank => $id ) {
			$shared       = count( array_intersect( $families, wc_get_product_terms( $id, 'pa_fragrance-family', array( 'fields' => 'ids' ) ) ) );
			$score[ $id ] = $shared * 1000 - $rank;
		}
		arsort( $score );
		return array_slice( array_keys( $score ), 0, $limit );
	}

	/**
	 * "Similar fragrances": same family + compatible gender, then WooCommerce's category-based picks.
	 *
	 * @param int[] $related    IDs chosen by WooCommerce (same category/tag).
	 * @param int   $product_id Current product.
	 * @param array $args       { limit, excluded_ids } — excluded_ids holds the up-sells shown above.
	 * @return int[]
	 */
	public static function related_by_family( $related, $product_id, $args = array() ) {
		$exclude = array_merge( array( $product_id ), array_map( 'intval', $args['excluded_ids'] ?? array() ) );
		$similar = self::similar( $product_id, $exclude, self::RELATED_LIMIT * 2 );
		return array_values( array_diff( array_unique( array_merge( $similar, array_map( 'intval', $related ) ) ), $exclude ) );
	}

	/**
	 * "You may also like": manual up-sells when set, otherwise best sellers
	 * for a compatible gender, skipping the closest matches (those belong in "similar").
	 *
	 * @param int[]      $ids     Up-sell IDs saved on the product.
	 * @param WC_Product $product Product.
	 * @return int[]
	 */
	public static function auto_upsells( $ids, $product ) {
		if ( $ids || is_admin() || ! is_product() || get_queried_object_id() !== $product->get_id() ) {
			return $ids;
		}
		$id      = $product->get_id();
		$exclude = array_merge( array( $id ), self::similar( $id, array( $id ), self::RELATED_LIMIT ) );
		return self::query( self::gender_clause( $id ), $exclude, self::RELATED_LIMIT );
	}

	/**
	 * @param array $args Related/up-sell display args.
	 * @return array
	 */
	public static function keep_order( $args ) {
		$args['orderby'] = 'none';
		$args['order']   = 'asc'; // "desc" reverses the list.
		return $args;
	}

	public static function related_heading() {
		return __( 'Similar fragrances', 'nafha-core' );
	}

	public static function upsells_heading() {
		return __( 'You may also like', 'nafha-core' );
	}
}

Nafha_Product_Page::register();

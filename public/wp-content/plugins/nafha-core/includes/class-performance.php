<?php
/**
 * Front-end performance (measured with Lighthouse mobile; see tools/lighthouse.sh).
 *
 * - jQuery moves to the footer (it was the largest render-blocking file) and
 *   jquery-migrate is dropped: WooCommerce, Blocksy and our scripts don't need it.
 * - Assets nothing on the page uses are not loaded (brand widget CSS, threaded
 *   comment replies, WooCommerce's order-attribution scripts — GA4 covers that).
 * - Uploaded JPEG/PNG images get WebP thumbnails automatically.
 * - Emoji detection script/styles removed (system fonts render emoji natively).
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Performance {

	public static function register() {
		add_action( 'wp_default_scripts', array( __CLASS__, 'jquery_in_footer' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_unused' ), 100 );
		add_filter( 'image_editor_output_format', array( __CLASS__, 'webp_output' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'prioritize_lcp_image' ), 20, 3 );

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * @param WP_Scripts $scripts Script registry.
	 */
	public static function jquery_in_footer( $scripts ) {
		if ( is_admin() || ! isset( $scripts->registered['jquery'] ) ) {
			return;
		}
		// "jquery" is an alias of jquery-core (+ migrate); keep only core, in the footer group.
		$scripts->registered['jquery']->deps = array( 'jquery-core' );
		$scripts->add_data( 'jquery', 'group', 1 );
		$scripts->add_data( 'jquery-core', 'group', 1 );
	}

	public static function dequeue_unused() {
		self::jquery_dependents_in_footer();
		self::inline_small_styles();

		wp_dequeue_style( 'brands-styles' ); // WooCommerce brand widgets/thumbnails — not used in this theme.

		if ( is_product() ) {
			wp_dequeue_script( 'comment-reply' ); // Reviews are flat, no threaded replies.
		}
	}

	/**
	 * WooCommerce prints several jQuery-based scripts in <head> (deferred), which drags
	 * jQuery itself into <head> as a render-blocking file. Moving every script that
	 * depends on jQuery to the footer keeps the whole chain out of the critical path;
	 * their localized/inline data moves with them.
	 */
	private static function jquery_dependents_in_footer() {
		$scripts  = wp_scripts();
		$on_jq    = array( 'jquery' => true, 'jquery-core' => true );
		$changed  = true;
		while ( $changed ) { // Walk the dependency graph until no new dependents are found.
			$changed = false;
			foreach ( $scripts->registered as $handle => $script ) {
				if ( ! isset( $on_jq[ $handle ] ) && array_intersect( $script->deps, array_keys( $on_jq ) ) ) {
					$on_jq[ $handle ] = true;
					$changed          = true;
				}
			}
		}
		foreach ( array_keys( $on_jq ) as $handle ) {
			$scripts->add_data( $handle, 'group', 1 );
		}
		// js-cookie is only used by WooCommerce's (now footer) scripts.
		foreach ( array( 'wc-js-cookie', 'js-cookie' ) as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$scripts->add_data( $handle, 'group', 1 );
			}
		}
	}

	/**
	 * Small stylesheets are printed inline instead of as separate render-blocking
	 * requests (each costs a round trip on mobile). Uses WordPress's own inliner
	 * (wp_maybe_inline_styles), which also rewrites relative url()s; Blocksy's large
	 * main bundle stays a cached file.
	 */
	private static function inline_small_styles() {
		$handles = array(
			'nafha-front'            => NAFHA_CORE_DIR . 'assets/css/front.css',
			'nafha-shop'             => NAFHA_CORE_DIR . 'assets/css/shop.css',
			'nafha-product'          => NAFHA_CORE_DIR . 'assets/css/product.css',
			'nafha-quiz'             => NAFHA_CORE_DIR . 'assets/css/quiz.css',
			'nafha-box'              => NAFHA_CORE_DIR . 'assets/css/box.css',
			'nafha-child'            => get_stylesheet_directory() . '/assets/css/main.css',
			'ct-main-rtl-styles'     => get_template_directory() . '/static/bundle/main-rtl.min.css',
			'ct-page-title-styles'   => get_template_directory() . '/static/bundle/page-title.min.css',
			'ct-pagination-styles'   => get_template_directory() . '/static/bundle/pagination.min.css',
			'ct-flexy-styles'        => get_template_directory() . '/static/bundle/flexy.min.css',
			'photoswipe'             => WC()->plugin_path() . '/assets/css/photoswipe/photoswipe.min.css',
			'photoswipe-default-skin' => WC()->plugin_path() . '/assets/css/photoswipe/default-skin/default-skin.min.css',
		);
		foreach ( $handles as $handle => $path ) {
			if ( wp_style_is( $handle, 'enqueued' ) && is_readable( $path ) ) {
				wp_style_add_data( $handle, 'path', $path );
			}
		}
		add_filter( 'styles_inline_size_limit', static fn() => 60000 ); // Bytes, uncompressed; these files total ~50 KB.
	}

	/**
	 * The largest image above the fold (first product card on shop/category pages,
	 * main gallery image on product pages) is fetched first and never lazy-loaded.
	 *
	 * @param array        $attr       Image attributes.
	 * @param WP_Post      $attachment Attachment.
	 * @param string|int[] $size       Requested size.
	 * @return array
	 */
	public static function prioritize_lcp_image( $attr, $attachment, $size ) {
		static $done = false;
		if ( $done || is_admin() ) {
			return $attr;
		}
		$archive_card = ( is_shop() || is_product_taxonomy() ) && 'woocommerce_thumbnail' === $size && ! wc_get_loop_prop( 'is_shortcode' );
		$product_main = is_product() && 'woocommerce_single' === $size;
		if ( $archive_card || $product_main ) {
			$done                  = true;
			$attr['fetchpriority'] = 'high';
			$attr['loading']       = 'eager';
			unset( $attr['decoding'] );
		}
		return $attr;
	}

	/**
	 * Generated image sizes are written as WebP (originals stay as uploaded).
	 *
	 * @param array $formats Source MIME => output MIME.
	 * @return array
	 */
	public static function webp_output( $formats ) {
		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$formats['image/jpeg'] = 'image/webp';
			$formats['image/png']  = 'image/webp';
		}
		return $formats;
	}
}

Nafha_Performance::register();

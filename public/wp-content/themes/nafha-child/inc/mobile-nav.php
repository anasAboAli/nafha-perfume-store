<?php
/**
 * Fixed bottom navigation bar for phones/tablets (hidden on desktop via CSS).
 *
 * @package Nafha_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icons (24×24, stroke-based) keyed by name.
 */
function nafha_child_icon( $name ) {
	$paths = array(
		'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		'shop'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		'quiz'    => '<path d="M12 3c0 0-6 7-6 11a6 6 0 0 0 12 0c0-4-6-11-6-11Z"/><path d="M9.5 14.5a2.5 2.5 0 0 0 2 2.4"/>',
		'cart'    => '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>',
		'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
	);
	return '<svg class="nafha-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
}

/**
 * Cart count badge markup — also returned as an AJAX cart fragment.
 */
function nafha_child_cart_count_html() {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	return sprintf(
		'<span class="nafha-bnav__count" data-count="%1$d">%1$d</span>',
		absint( $count )
	);
}

function nafha_child_render_mobile_nav() {
	if ( ! function_exists( 'WC' ) || is_checkout() ) {
		return; // Keep checkout distraction-free.
	}

	$quiz  = get_page_by_path( 'fragrance-quiz' );
	$items = array(
		'home'    => array( home_url( '/' ), __( 'Home', 'nafha-child' ), is_front_page() ),
		'shop'    => array( wc_get_page_permalink( 'shop' ), __( 'Shop', 'nafha-child' ), is_shop() || is_product_taxonomy() ),
		'quiz'    => array( $quiz ? get_permalink( $quiz ) : home_url( '/' ), __( 'Your scent', 'nafha-child' ), $quiz && is_page( $quiz->ID ) ),
		'cart'    => array( wc_get_cart_url(), __( 'Cart', 'nafha-child' ), is_cart() ),
		'account' => array( wc_get_page_permalink( 'myaccount' ), __( 'Account', 'nafha-child' ), is_account_page() ),
	);
	?>
	<nav class="nafha-bnav" aria-label="<?php esc_attr_e( 'Quick navigation', 'nafha-child' ); ?>">
		<?php foreach ( $items as $key => $item ) : ?>
			<a class="nafha-bnav__item nafha-bnav__item--<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $item[0] ); ?>"<?php echo $item[2] ? ' aria-current="page"' : ''; ?>>
				<span class="nafha-bnav__icon">
					<?php echo nafha_child_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
					<?php
					if ( 'cart' === $key ) {
						echo nafha_child_cart_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built with absint.
					}
					?>
				</span>
				<span class="nafha-bnav__label"><?php echo esc_html( $item[1] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}
add_action( 'wp_footer', 'nafha_child_render_mobile_nav', 5 );

/**
 * Refresh the badge after AJAX add-to-cart.
 *
 * @param array $fragments Selector => replacement HTML.
 * @return array
 */
function nafha_child_cart_fragment( $fragments ) {
	$fragments['.nafha-bnav__count'] = nafha_child_cart_count_html();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'nafha_child_cart_fragment' );

<?php
/**
 * Cart experience: side cart (opens on add-to-cart and from the header/bottom
 * bar), free-shipping progress bar, and the "Order via WhatsApp" button.
 *
 * The side cart body is WooCommerce's own mini-cart inside
 * .widget_shopping_cart_content, so WooCommerce's cart fragments keep it
 * fresh after every AJAX add/remove without extra endpoints.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Cart {

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_drawer' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );

		add_action( 'woocommerce_before_mini_cart', array( __CLASS__, 'render_progress' ) );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_progress' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( __CLASS__, 'render_whatsapp_button' ), 30 );
		add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'render_whatsapp_button' ), 30 );
	}

	public static function enqueue() {
		$css = 'assets/css/front.css';
		wp_enqueue_style( 'nafha-front', NAFHA_CORE_URL . $css, array(), (string) filemtime( NAFHA_CORE_DIR . $css ) );

		$drawer = 'assets/js/drawer.js';
		wp_register_script( 'nafha-drawer', NAFHA_CORE_URL . $drawer, array(), (string) filemtime( NAFHA_CORE_DIR . $drawer ), array( 'in_footer' => true, 'strategy' => 'defer' ) );

		if ( is_cart() || is_checkout() ) {
			return; // The cart is already on screen; no side cart needed.
		}
		$js = 'assets/js/cart.js';
		wp_enqueue_script( 'nafha-cart', NAFHA_CORE_URL . $js, array( 'jquery', 'nafha-drawer', 'wc-cart-fragments' ), (string) filemtime( NAFHA_CORE_DIR . $js ), array( 'in_footer' => true ) );
	}

	/* ---------------------------------------------------- free shipping */

	/**
	 * Progress towards free shipping, using the same amount WooCommerce's
	 * free-shipping method checks (subtotal after coupons).
	 *
	 * @return array{min: float, remaining: float, percent: int}|null
	 */
	public static function free_shipping_progress() {
		$min = (float) get_option( 'nafha_free_shipping_min', 0 );
		if ( $min <= 0 || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}
		$total = (float) WC()->cart->get_displayed_subtotal() - (float) WC()->cart->get_discount_total();
		if ( WC()->cart->display_prices_including_tax() ) {
			$total -= (float) WC()->cart->get_discount_tax();
		}
		return array(
			'min'       => $min,
			'remaining' => max( 0, $min - $total ),
			'percent'   => (int) min( 100, round( $total / $min * 100 ) ),
		);
	}

	public static function render_progress() {
		$progress = self::free_shipping_progress();
		if ( ! $progress ) {
			return;
		}
		$done = $progress['remaining'] <= 0;
		?>
		<div class="nafha-ship-progress<?php echo $done ? ' is-done' : ''; ?>">
			<p>
				<?php
				if ( $done ) {
					esc_html_e( 'Your order ships free!', 'nafha-core' );
				} else {
					echo wp_kses_post(
						sprintf(
							/* translators: %s: amount left to reach free shipping. */
							__( 'Add %s more for free delivery', 'nafha-core' ),
							'<strong>' . wc_price( $progress['remaining'] ) . '</strong>'
						)
					);
				}
				?>
			</p>
			<div class="nafha-ship-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress['percent'] ); ?>">
				<span style="inline-size: <?php echo esc_attr( $progress['percent'] ); ?>%"></span>
			</div>
		</div>
		<?php
	}

	/* --------------------------------------------------------- WhatsApp */

	public static function render_whatsapp_button() {
		if ( ! Nafha_Settings::whatsapp_number() || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		printf(
			'<a class="button nafha-wa-btn nafha-wa-btn--cart" href="%s">%s</a>',
			esc_url( add_query_arg( 'nafha_method', 'whatsapp', wc_get_checkout_url() ) ),
			esc_html__( 'Order via WhatsApp', 'nafha-core' )
		);
	}

	/* ------------------------------------------------------- side cart */

	private static function count_html() {
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		return '<span class="nafha-cart-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}

	/**
	 * @param array $fragments Selector => HTML replaced after AJAX cart changes.
	 * @return array
	 */
	public static function fragments( $fragments ) {
		$fragments['.nafha-cart-count'] = self::count_html();
		return $fragments;
	}

	public static function render_drawer() {
		if ( is_cart() || is_checkout() || ! function_exists( 'woocommerce_mini_cart' ) ) {
			return;
		}
		?>
		<div class="nafha-drawer nafha-cart-drawer" id="nafha-cart" role="dialog" aria-modal="true" aria-labelledby="nafha-cart-title" hidden>
			<div class="nafha-drawer__overlay" data-nafha-close></div>
			<div class="nafha-drawer__panel">
				<header class="nafha-drawer__header">
					<h2 id="nafha-cart-title"><?php esc_html_e( 'Your cart', 'nafha-core' ); ?> <?php echo self::count_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in count_html(). ?></h2>
					<button type="button" class="nafha-drawer__close" data-nafha-close aria-label="<?php esc_attr_e( 'Close', 'nafha-core' ); ?>">×</button>
				</header>
				<div class="nafha-drawer__body">
					<?php // WooCommerce replaces this inner div wholesale on every cart change, so keep it class-less. ?>
					<div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
				</div>
			</div>
		</div>
		<?php
	}
}

Nafha_Cart::register();

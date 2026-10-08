<?php
/**
 * "Order via WhatsApp" button on the product page. The link's message
 * (product, size, quantity, price, URL) is built in assets/js/product.js so
 * it follows the chosen size live; the PHP side provides a working fallback
 * href for simple products and when JS is off.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_WhatsApp {

	public static function register() {
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_product_button' ) );
	}

	/**
	 * wa.me link for a message.
	 *
	 * Echo it with esc_attr(), not esc_url(): esc_url() strips encoded line breaks
	 * (%0A) and the message would collapse onto one line. The link is safe as built:
	 * fixed https://wa.me/ prefix, digits-only number, rawurlencode()d text.
	 */
	public static function link( $message ) {
		return 'https://wa.me/' . Nafha_Settings::whatsapp_number() . '?text=' . rawurlencode( $message );
	}

	public static function render_product_button() {
		global $product;
		if ( ! Nafha_Settings::whatsapp_number() || ! $product ) {
			return;
		}

		$fallback = implode(
			"\n",
			array(
				__( 'Hello, I would like to order:', 'nafha-core' ),
				$product->get_name(),
				get_permalink( $product->get_id() ),
			)
		);
		?>
		<a class="nafha-wa-btn button" href="<?php echo esc_attr( self::link( $fallback ) ); // See link(). ?>" target="_blank" rel="noopener"
			data-number="<?php echo esc_attr( Nafha_Settings::whatsapp_number() ); ?>"
			data-name="<?php echo esc_attr( $product->get_name() ); ?>"
			data-url="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"
			data-sku="<?php echo esc_attr( $product->get_sku() ? $product->get_sku() : $product->get_id() ); ?>"
			data-price="<?php echo esc_attr( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ) ); ?>"
			data-value="<?php echo esc_attr( wc_get_price_to_display( $product ) ); ?>">
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
			<span><?php esc_html_e( 'Order via WhatsApp', 'nafha-core' ); ?></span>
		</a>
		<?php
	}
}

Nafha_WhatsApp::register();

<?php
/**
 * "Confirm via WhatsApp" checkout method.
 *
 * The order is created in WooCommerce like any other (stock reduced, admin +
 * customer e-mails, visible in the dashboard) and left "on hold"; the thank-you
 * page then opens WhatsApp with the full order summary so the customer can
 * confirm with the store. Payment itself is cash on delivery.
 *
 * The cart's "Order via WhatsApp" button links to checkout with this method
 * pre-selected (?nafha_method=whatsapp).
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Gateway_WhatsApp extends WC_Payment_Gateway {

	const ID = 'nafha_whatsapp';

	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = false;
		$this->method_title       = __( 'Confirm via WhatsApp', 'nafha-core' );
		$this->method_description = __( 'Registers the order, then opens WhatsApp with the order summary so the customer confirms it with the store. Payment on delivery.', 'nafha-core' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'nafha-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable WhatsApp confirmation', 'nafha-core' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'nafha-core' ),
				'type'    => 'text',
				'default' => __( 'Confirm via WhatsApp', 'nafha-core' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'nafha-core' ),
				'type'    => 'textarea',
				'default' => __( 'We will register your order and take you to WhatsApp to send it to our team. You pay on delivery.', 'nafha-core' ),
			),
		);
	}

	public function is_available() {
		return parent::is_available() && '' !== Nafha_Settings::whatsapp_number();
	}

	/**
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_nafha_via_whatsapp', 'yes' );
		// on-hold: reduces stock and sends the new-order e-mails, but flags it as awaiting confirmation.
		$order->update_status( 'on-hold', __( 'Awaiting confirmation via WhatsApp.', 'nafha-core' ) );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/* ----------------------------------------------------------- static */

	public static function register() {
		add_filter( 'woocommerce_payment_gateways', static fn( $gateways ) => array_merge( $gateways, array( __CLASS__ ) ) );
		add_action( 'woocommerce_thankyou_' . self::ID, array( __CLASS__, 'thankyou' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'preselect_from_url' ) );
	}

	/**
	 * Checkout reached from the cart's WhatsApp button: pre-select this method.
	 */
	public static function preselect_from_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a payment method.
		if ( is_checkout() && isset( $_GET['nafha_method'] ) && 'whatsapp' === $_GET['nafha_method'] && WC()->session ) {
			WC()->session->set( 'chosen_payment_method', self::ID );
		}
	}

	/**
	 * Order summary as a WhatsApp message.
	 */
	public static function message( WC_Order $order ) {
		$plain = static fn( $html ) => html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$lines = array(
			/* translators: %s: order number. */
			sprintf( __( 'New order #%s', 'nafha-core' ), $order->get_order_number() ),
			'',
		);
		foreach ( $order->get_items() as $item ) {
			$lines[] = sprintf( '• %s × %d — %s', $plain( $item->get_name() ), $item->get_quantity(), $plain( $order->get_formatted_line_subtotal( $item ) ) );
		}
		$lines[] = '';
		$lines[] = __( 'Shipping', 'nafha-core' ) . ': ' . ( (float) $order->get_shipping_total() > 0 ? $plain( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) ) : __( 'Free', 'nafha-core' ) );
		if ( (float) $order->get_discount_total() > 0 ) {
			$lines[] = __( 'Discount', 'nafha-core' ) . ': -' . $plain( wc_price( $order->get_discount_total(), array( 'currency' => $order->get_currency() ) ) );
		}
		$lines[] = __( 'Total', 'nafha-core' ) . ': ' . $plain( $order->get_formatted_order_total() );
		$lines[] = '';
		$lines[] = __( 'Name', 'nafha-core' ) . ': ' . $order->get_billing_first_name();
		$lines[] = __( 'Mobile', 'nafha-core' ) . ': ' . $order->get_billing_phone();
		$states  = WC()->countries->get_states( $order->get_billing_country() );
		$lines[] = __( 'Area', 'nafha-core' ) . ': ' . ( $states[ $order->get_billing_state() ] ?? $order->get_billing_state() );
		$lines[] = __( 'Address', 'nafha-core' ) . ': ' . $order->get_billing_address_1();
		if ( $order->get_customer_note() ) {
			$lines[] = __( 'Notes', 'nafha-core' ) . ': ' . $order->get_customer_note();
		}
		return implode( "\n", $lines );
	}

	/**
	 * Thank-you page: send the summary on WhatsApp (auto-opens once, button as fallback).
	 *
	 * @param int $order_id Order ID.
	 */
	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$link = Nafha_WhatsApp::link( self::message( $order ) );
		?>
		<div class="nafha-wa-confirm">
			<h2><?php esc_html_e( 'One last step: send your order on WhatsApp', 'nafha-core' ); ?></h2>
			<p><?php esc_html_e( 'Your order is registered. Send it to us on WhatsApp so our team can confirm it and arrange delivery.', 'nafha-core' ); ?></p>
			<a class="button nafha-wa-btn" id="nafha-wa-confirm" href="<?php echo esc_attr( $link ); // See Nafha_WhatsApp::link(). ?>" target="_blank" rel="noopener" data-order="<?php echo esc_attr( $order->get_order_number() ); ?>" data-value="<?php echo esc_attr( $order->get_total() ); ?>">
				<?php esc_html_e( 'Send order on WhatsApp', 'nafha-core' ); ?>
			</a>
		</div>
		<script>
			( function () {
				var btn = document.getElementById( 'nafha-wa-confirm' );
				var key = 'nafha-wa-sent-' + btn.dataset.order;
				btn.addEventListener( 'click', function () {
					window.dataLayer = window.dataLayer || [];
					window.dataLayer.push( { event: 'whatsapp_click', whatsapp_location: 'order_confirmation', transaction_id: btn.dataset.order, value: Number( btn.dataset.value ) } );
				} );
				try {
					if ( ! sessionStorage.getItem( key ) ) {
						sessionStorage.setItem( key, '1' );
						setTimeout( function () { window.location.href = btn.href; }, 1500 );
					}
				} catch ( e ) {}
			}() );
		</script>
		<?php
	}
}

Nafha_Gateway_WhatsApp::register();

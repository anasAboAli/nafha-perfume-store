<?php
/**
 * Google Tag Manager + GA4 e-commerce dataLayer + cookie consent.
 *
 * Replaces a GTM plugin: prints the container (when an ID is set in
 * WooCommerce → Settings → Nafha) and pushes GA4-format events that GTM
 * forwards to GA4 / Meta / TikTok:
 *
 *   view_item       product page (server-rendered)
 *   add_to_cart     any add (shop card, product page, discovery box): queued
 *                   in the WooCommerce session, delivered with the AJAX cart
 *                   fragments or on the next page view — never lost on redirect
 *   begin_checkout  checkout page
 *   purchase        order-received page, once per order
 *   whatsapp_click  pushed client-side by product.js / the WhatsApp gateway
 *
 * Consent: Google Consent Mode v2 defaults to "denied" until the visitor
 * accepts in the consent bar (choice kept in localStorage). With the bar
 * switched off, consent defaults to "granted".
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Tracking {

	const SESSION_KEY = 'nafha_dl_queue';

	public static function register() {
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'noscript' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'consent_bar' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'queue_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragment' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'purchase' ), 1 );
	}

	private static function consent_required() {
		return 'yes' === get_option( 'nafha_consent_banner', 'yes' );
	}

	public static function enqueue() {
		$js = 'assets/js/tracking.js';
		wp_enqueue_script( 'nafha-tracking', NAFHA_CORE_URL . $js, array(), (string) filemtime( NAFHA_CORE_DIR . $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/* ------------------------------------------------------------ items */

	/**
	 * GA4 item for a product or variation.
	 */
	private static function item( WC_Product $product, $quantity = 1 ) {
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
		$brands = get_the_terms( $parent->get_id(), 'product_brand' );
		$cats   = get_the_terms( $parent->get_id(), 'product_cat' );
		$item   = array(
			'item_id'       => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
			'item_name'     => $parent->get_name(),
			'item_brand'    => $brands && ! is_wp_error( $brands ) ? $brands[0]->name : 'نفحة',
			'item_category' => $cats && ! is_wp_error( $cats ) ? $cats[0]->name : '',
			'price'         => (float) wc_get_price_to_display( $product ),
			'quantity'      => (int) $quantity,
		);
		if ( $product->is_type( 'variation' ) ) {
			$item['item_variant'] = wc_get_formatted_variation( $product, true, false, false );
		}
		return $item;
	}

	private static function event( $name, array $items, $value = null, array $extra = array() ) {
		$value = null === $value ? array_sum( array_map( static fn( $i ) => $i['price'] * $i['quantity'], $items ) ) : $value;
		return array(
			'event'     => $name,
			'ecommerce' => array_merge(
				array(
					'currency' => get_woocommerce_currency(),
					'value'    => round( (float) $value, 2 ),
					'items'    => $items,
				),
				$extra
			),
		);
	}

	/**
	 * Inline script pushing events (each preceded by an ecommerce reset, as GA4 recommends).
	 */
	private static function push_script( array $events ) {
		if ( ! $events ) {
			return '';
		}
		$js = 'window.dataLayer=window.dataLayer||[];';
		foreach ( $events as $event ) {
			$js .= 'dataLayer.push({ecommerce:null});dataLayer.push(' . wp_json_encode( $event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . ');';
		}
		return '<script>' . $js . "</script>\n";
	}

	/* ------------------------------------------------------------- head */

	public static function head() {
		$denied  = self::consent_required() ? 'denied' : 'granted';
		$consent = wp_json_encode(
			array(
				'ad_storage'         => $denied,
				'ad_user_data'       => $denied,
				'ad_personalization' => $denied,
				'analytics_storage'  => $denied,
				'wait_for_update'    => 500,
			)
		);
		// Consent defaults must precede the container; a stored "accept" is applied straight away.
		echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{$consent});try{if(localStorage.getItem('nafha_consent')==='granted'){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});}}catch(e){}</script>\n";

		$gtm = Nafha_Settings::gtm_id();
		if ( $gtm ) {
			printf(
				"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n",
				esc_js( $gtm )
			);
		}

		$events = self::take_queue();
		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$events[] = self::event( 'view_item', array( self::item( $product ) ), self::view_price( $product ) );
			}
		} elseif ( is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
			$items = array();
			foreach ( WC()->cart->get_cart() as $line ) {
				$items[] = self::item( $line['data'], $line['quantity'] );
			}
			$events[] = self::event( 'begin_checkout', $items, WC()->cart->get_total( 'edit' ) );
		}
		echo self::push_script( $events ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded with JSON_HEX_TAG.
	}

	/**
	 * Lowest price for variable products (what the visitor first sees).
	 */
	private static function view_price( WC_Product $product ) {
		return $product->is_type( 'variable' ) ? (float) $product->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $product );
	}

	public static function noscript() {
		$gtm = Nafha_Settings::gtm_id();
		if ( $gtm ) {
			printf( '<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n", esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $gtm ) );
		}
	}

	/* ------------------------------------------------------ add_to_cart */

	public static function queue_add_to_cart( $key, $product_id, $quantity, $variation_id ) {
		if ( ! WC()->session ) {
			return;
		}
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product ) {
			return;
		}
		$event = self::event( 'add_to_cart', array( self::item( $product, $quantity ) ) );
		// WooCommerce re-applies cached fragments on later page loads; the ID lets tracking.js
		// skip events it already pushed (and lets Meta deduplicate browser/server events).
		$event['event_id'] = wp_generate_uuid4();

		$queue   = (array) WC()->session->get( self::SESSION_KEY, array() );
		$queue[] = $event;
		WC()->session->set( self::SESSION_KEY, $queue );
	}

	/**
	 * Pending events, removed from the session (each is delivered once).
	 */
	private static function take_queue() {
		if ( ! WC()->session ) {
			return array();
		}
		$queue = (array) WC()->session->get( self::SESSION_KEY, array() );
		if ( $queue ) {
			WC()->session->set( self::SESSION_KEY, array() );
		}
		return $queue;
	}

	/**
	 * AJAX adds: hand the queued events to tracking.js inside the cart fragments.
	 *
	 * @param array $fragments Selector => HTML.
	 * @return array
	 */
	public static function fragment( $fragments ) {
		$fragments['div.nafha-dl-queue'] = sprintf(
			'<div class="nafha-dl-queue" hidden data-events="%s"></div>',
			esc_attr( wp_json_encode( self::take_queue(), JSON_UNESCAPED_UNICODE ) )
		);
		return $fragments;
	}

	/* --------------------------------------------------------- purchase */

	public static function purchase( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_nafha_dl_purchase' ) ) {
			return; // Already reported (thank-you page reloaded).
		}
		$items = array();
		foreach ( $order->get_items() as $line ) {
			$product = $line->get_product();
			if ( $product ) {
				$item          = self::item( $product, $line->get_quantity() );
				$item['price'] = round( (float) $order->get_item_subtotal( $line, true ), 2 ); // What was actually paid (box discounts).
				$items[]       = $item;
			}
		}
		$coupons = $order->get_coupon_codes();
		echo self::push_script( // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded with JSON_HEX_TAG.
			array(
				self::event(
					'purchase',
					$items,
					$order->get_total(),
					array_filter(
						array(
							'transaction_id' => (string) $order->get_order_number(),
							'shipping'       => round( (float) $order->get_shipping_total(), 2 ),
							'tax'            => round( (float) $order->get_total_tax(), 2 ),
							'coupon'         => implode( ',', $coupons ),
							'payment_type'   => $order->get_payment_method(),
						),
						static fn( $v ) => '' !== $v
					)
				),
			)
		);
		$order->update_meta_data( '_nafha_dl_purchase', 1 );
		$order->save();
	}

	/* ---------------------------------------------------------- consent */

	public static function consent_bar() {
		echo '<div class="nafha-dl-queue" hidden data-events="[]"></div>'; // Fragment target for AJAX add_to_cart events.

		if ( ! self::consent_required() ) {
			return;
		}
		$privacy = get_privacy_policy_url();
		?>
		<div class="nafha-consent" role="region" aria-label="<?php esc_attr_e( 'Cookie consent', 'nafha-core' ); ?>" hidden>
			<p>
				<?php esc_html_e( 'We use cookies to improve your experience and measure site and ad performance.', 'nafha-core' ); ?>
				<?php if ( $privacy ) : ?>
					<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'nafha-core' ); ?></a>
				<?php endif; ?>
			</p>
			<div class="nafha-consent__actions">
				<button type="button" class="button" data-nafha-consent="granted"><?php esc_html_e( 'Accept', 'nafha-core' ); ?></button>
				<button type="button" class="button nafha-button-outline" data-nafha-consent="denied"><?php esc_html_e( 'Essential only', 'nafha-core' ); ?></button>
			</div>
		</div>
		<?php
	}
}

Nafha_Tracking::register();

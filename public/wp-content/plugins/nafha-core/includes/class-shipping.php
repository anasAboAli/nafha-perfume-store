<?php
/**
 * Shipping behaviour.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Shipping {

	public static function register() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'prefer_free_shipping' ), 100 );
	}

	/**
	 * Free-shipping threshold, read from the "Free shipping" methods configured in
	 * WooCommerce → Settings → Shipping (lowest enabled minimum across zones), so the
	 * announcement bar, cart progress bar and texts follow whatever the owner sets there.
	 *
	 * @return float 0 when no zone offers free shipping.
	 */
	public static function free_shipping_min() {
		static $min = null;
		if ( null !== $min ) {
			return $min;
		}
		$amounts = array();
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( 'free_shipping' === $method->id && 'yes' === $method->enabled && in_array( $method->get_option( 'requires' ), array( 'min_amount', 'either' ), true ) ) {
					$amounts[] = (float) $method->get_option( 'min_amount' );
				}
			}
		}
		$min = $amounts ? min( $amounts ) : 0.0;
		return $min;
	}

	/**
	 * Once the order qualifies for free shipping, drop the paid rates so the
	 * customer is not offered a choice that only costs them more.
	 *
	 * @param WC_Shipping_Rate[] $rates Available rates keyed by rate ID.
	 * @return WC_Shipping_Rate[]
	 */
	public static function prefer_free_shipping( $rates ) {
		$free = array_filter(
			$rates,
			static fn( $rate ) => 'free_shipping' === $rate->get_method_id()
		);
		return $free ? $free : $rates;
	}
}

Nafha_Shipping::register();

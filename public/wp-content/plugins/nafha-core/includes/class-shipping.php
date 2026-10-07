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

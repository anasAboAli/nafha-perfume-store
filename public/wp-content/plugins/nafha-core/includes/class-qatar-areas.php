<?php
/**
 * Qatar delivery areas.
 *
 * WooCommerce ships no "states" for Qatar, so shipping zones cannot tell Doha
 * from Al Khor. We register Qatar's municipalities (with Lusail split out of
 * Al Daayen because it is priced like Doha) as states, which makes them
 * selectable in checkout and usable as shipping-zone regions.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Qatar_Areas {

	/**
	 * Area code => label. Codes are stored on orders/zones, so never rename them.
	 *
	 * @return array<string,string>
	 */
	public static function areas() {
		return array(
			'DA'  => __( 'Doha', 'nafha-core' ),
			'LU'  => __( 'Lusail', 'nafha-core' ),
			'RA'  => __( 'Al Rayyan', 'nafha-core' ),
			'ZA'  => __( 'Al Daayen', 'nafha-core' ),
			'WA'  => __( 'Al Wakrah', 'nafha-core' ),
			'US'  => __( 'Umm Salal', 'nafha-core' ),
			'KH'  => __( 'Al Khor & Al Thakhira', 'nafha-core' ),
			'MS'  => __( 'Mesaieed', 'nafha-core' ),
			'SHH' => __( 'Al Shahaniya', 'nafha-core' ),
			'SH'  => __( 'Al Shamal', 'nafha-core' ),
			'DU'  => __( 'Dukhan', 'nafha-core' ),
		);
	}

	public static function register() {
		add_filter( 'woocommerce_states', array( __CLASS__, 'add_states' ) );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'address_locale' ) );
	}

	/**
	 * @param array $states Country code => states.
	 * @return array
	 */
	public static function add_states( $states ) {
		$states['QA'] = self::areas();
		return $states;
	}

	/**
	 * Qatar has no postcodes; the area dropdown is what drives shipping.
	 *
	 * @param array $locale Per-country address field overrides.
	 * @return array
	 */
	public static function address_locale( $locale ) {
		$locale['QA'] = array(
			'postcode' => array(
				'required' => false,
				'hidden'   => true,
			),
			'state'    => array(
				'label'    => __( 'Area', 'nafha-core' ),
				'required' => true,
				'priority' => 42,
			),
			'city'     => array(
				'label'    => __( 'District', 'nafha-core' ),
				'required' => false,
			),
		);
		return $locale;
	}
}

Nafha_Qatar_Areas::register();

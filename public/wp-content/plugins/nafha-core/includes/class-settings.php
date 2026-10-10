<?php
/**
 * "Nafha" tab under WooCommerce → Settings for store-specific options.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Settings {

	const TAB = 'nafha';

	public static function register() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 50 );
		add_action( 'woocommerce_settings_' . self::TAB, array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_update_options_' . self::TAB, array( __CLASS__, 'save' ) );
	}

	/**
	 * Store WhatsApp number in international format, digits only (e.g. 97455551234).
	 */
	public static function whatsapp_number() {
		return preg_replace( '/\D+/', '', (string) get_option( 'nafha_whatsapp_number', '' ) );
	}

	/**
	 * @param array $tabs Tab slug => label.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs[ self::TAB ] = __( 'Nafha', 'nafha-core' );
		return $tabs;
	}

	private static function fields() {
		return array(
			array(
				'title' => __( 'WhatsApp ordering', 'nafha-core' ),
				'type'  => 'title',
				'id'    => 'nafha_whatsapp_section',
			),
			array(
				'title'             => __( 'Store WhatsApp number', 'nafha-core' ),
				'desc'              => __( 'International format without + or leading zeros, e.g. 97455551234.', 'nafha-core' ),
				'id'                => 'nafha_whatsapp_number',
				'type'              => 'text',
				'custom_attributes' => array(
					'inputmode' => 'numeric',
					'dir'       => 'ltr',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nafha_whatsapp_section',
			),
			array(
				'title' => __( 'Tracking', 'nafha-core' ),
				'desc'  => __( 'Google Analytics 4, Meta and TikTok pixels are added inside Google Tag Manager; the site already sends the e-commerce events. See docs/TRACKING.md.', 'nafha-core' ),
				'type'  => 'title',
				'id'    => 'nafha_tracking_section',
			),
			array(
				'title'             => __( 'Google Tag Manager container ID', 'nafha-core' ),
				'desc'              => __( 'e.g. GTM-ABC1234. Leave empty to disable.', 'nafha-core' ),
				'id'                => 'nafha_gtm_id',
				'type'              => 'text',
				'custom_attributes' => array( 'dir' => 'ltr' ),
			),
			array(
				'title'   => __( 'Cookie consent bar', 'nafha-core' ),
				'desc'    => __( 'Ask visitors before analytics and advertising cookies are used (Google Consent Mode).', 'nafha-core' ),
				'id'      => 'nafha_consent_banner',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nafha_tracking_section',
			),
		);
	}

	/**
	 * GTM container ID if it looks valid, else ''.
	 */
	public static function gtm_id() {
		$id = strtoupper( trim( (string) get_option( 'nafha_gtm_id', '' ) ) );
		return preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $id ) ? $id : '';
	}

	public static function render() {
		WC_Admin_Settings::output_fields( self::fields() );
	}

	public static function save() {
		// WooCommerce verifies the settings nonce and manage_woocommerce before firing this action.
		WC_Admin_Settings::save_fields( self::fields() );
		update_option( 'nafha_whatsapp_number', self::whatsapp_number() );
		update_option( 'nafha_gtm_id', self::gtm_id() ); // Drop anything that is not a container ID.
	}
}

Nafha_Settings::register();

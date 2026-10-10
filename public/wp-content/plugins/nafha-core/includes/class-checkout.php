<?php
/**
 * Short checkout for the Qatar market: full name, mobile, area, address,
 * optional e-mail and notes. Everything else WooCommerce asks for by default
 * (last name, company, postcode, second address line, country) is removed.
 *
 * Phone numbers are accepted as typed — local Qatari (5555 1234, 55551234)
 * or international (+974…, 00974…, or any other +country number) — and stored
 * in one canonical international form so WhatsApp links and couriers work.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Checkout {

	public static function register() {
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'billing_fields' ), 20 );
		// Late: WooCommerce applies its own phone/email settings while assembling checkout fields.
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 1000 );
		// Address labels must live in the country locale too: WooCommerce's JS re-applies them on load.
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'address_locale' ), 20 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'normalize_posted_phone' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_phone' ), 10, 2 );
		add_filter( 'woocommerce_enable_order_notes_field', '__return_true' );
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'rename_strings' ), 10, 2 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'email_styles' ) );

		// No terms checkbox (an extra tap that blocks orders); consent is the privacy text under the button.
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', '__return_empty_string' );
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
	}

	/**
	 * Canonical international form: "+97455551234". Returns '' if it cannot be a valid number.
	 */
	public static function normalize_phone( $raw ) {
		// Arabic-Indic and Persian digits → Latin, then keep digits and a leading +.
		$raw    = strtr( (string) $raw, array_combine( array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ) ) );
		$plus   = 0 === strpos( ltrim( $raw ), '+' );
		$digits = preg_replace( '/\D+/', '', $raw );

		if ( ! $plus && 0 === strpos( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
			$plus   = true;
		}
		if ( ! $plus ) {
			// Local Qatari number: 8 digits, mobiles start 3/5/6/7, landlines 4.
			return preg_match( '/^[34567]\d{7}$/', $digits ) ? '+974' . $digits : '';
		}
		if ( 0 === strpos( $digits, '974' ) ) {
			return preg_match( '/^974[34567]\d{7}$/', $digits ) ? '+' . $digits : '';
		}
		// Other countries (e.g. a visitor's home number): E.164 length check only.
		return preg_match( '/^[1-9]\d{7,14}$/', $digits ) ? '+' . $digits : '';
	}

	/**
	 * @param array $fields Billing fields.
	 * @return array
	 */
	public static function billing_fields( $fields ) {
		foreach ( array( 'billing_last_name', 'billing_company', 'billing_address_2', 'billing_postcode', 'billing_city' ) as $key ) {
			unset( $fields[ $key ] );
		}

		foreach ( self::address_overrides() as $key => $override ) {
			if ( isset( $fields[ 'billing_' . $key ] ) ) {
				$fields[ 'billing_' . $key ] = array_merge( $fields[ 'billing_' . $key ], $override );
			}
		}
		if ( isset( $fields['billing_country'] ) ) {
			$fields['billing_country']['priority'] = 30;
			$fields['billing_country']['class']    = array( 'form-row-wide', 'nafha-hidden-field' ); // Qatar only.
		}
		return $fields;
	}

	/**
	 * Name / phone / area / address settings shared by the field list and the Qatar locale.
	 *
	 * @return array<string, array>
	 */
	private static function address_overrides() {
		return array(
			'first_name' => array(
				'label'        => __( 'Full name', 'nafha-core' ),
				'class'        => array( 'form-row-wide' ),
				'autocomplete' => 'name',
				'priority'     => 10,
			),
			// Phone is an address field in WooCommerce (its JS applies the locale to it).
			'phone'      => array(
				'label'       => __( 'Mobile number', 'nafha-core' ),
				'placeholder' => '5555 1234', // Digits only: the field is LTR, Arabic text would render jumbled.
				'required'    => true,
				'priority'    => 20,
			),
			'state'      => array(
				'class'    => array( 'form-row-wide', 'address-field', 'update_totals_on_change' ), // Area drives the delivery price: recalc totals on change.
				'priority' => 40,
			),
			'address_1'  => array(
				'label'       => __( 'Address', 'nafha-core' ),
				'placeholder' => __( 'Zone, street and building number', 'nafha-core' ),
				'class'       => array( 'form-row-wide', 'address-field' ),
				'priority'    => 50,
			),
		);
	}

	/**
	 * @param array $locale Per-country address field overrides.
	 * @return array
	 */
	public static function address_locale( $locale ) {
		foreach ( self::address_overrides() as $key => $override ) {
			$keep                  = array_intersect_key( $override, array_flip( array( 'label', 'placeholder', 'priority', 'required' ) ) );
			$locale['QA'][ $key ] = array_merge( $locale['QA'][ $key ] ?? array(), $keep );
		}
		return $locale;
	}

	/**
	 * Phone (required, first after the name), optional e-mail, notes.
	 * WooCommerce appends "(optional)" to non-required labels itself.
	 *
	 * @param array $fields All checkout field groups.
	 * @return array
	 */
	public static function checkout_fields( $fields ) {
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone'] = array_merge(
				$fields['billing']['billing_phone'],
				array(
					'label'             => __( 'Mobile number', 'nafha-core' ),
					'placeholder'       => '5555 1234', // Digits only: the field is LTR, Arabic text would render jumbled.
					'required'          => true,
					'type'              => 'tel',
					'validate'          => array( 'phone' ),
					'class'             => array( 'form-row-wide' ),
					'priority'          => 20,
					'custom_attributes' => array( 'dir' => 'ltr', 'inputmode' => 'tel' ),
				)
			);
		}
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['required'] = false;
			$fields['billing']['billing_email']['label']    = __( 'Email, for order updates', 'nafha-core' );
			$fields['billing']['billing_email']['priority'] = 60;
			$fields['billing']['billing_email']['class']    = array( 'form-row-wide' );
		}
		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['label']       = __( 'Notes', 'nafha-core' );
			$fields['order']['order_comments']['placeholder'] = __( 'Delivery time, landmark, gift message…', 'nafha-core' );
		}
		uasort( $fields['billing'], static fn( $a, $b ) => ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 ) );
		return $fields;
	}

	/**
	 * One address serves both billing and delivery, so call it what it is.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @return string
	 */
	public static function rename_strings( $translation, $text ) {
		if ( 'Billing &amp; Shipping' === $text || 'Billing details' === $text ) {
			return __( 'Delivery details', 'nafha-core' );
		}
		if ( 'Billing address' === $text && ! is_admin() ) {
			return __( 'Delivery address', 'nafha-core' ); // Thank-you page, My account and e-mails.
		}
		return $translation;
	}

	/**
	 * Phone numbers keep their + in front inside right-to-left e-mails.
	 *
	 * @param string $css E-mail CSS (inlined by WooCommerce).
	 * @return string
	 */
	public static function email_styles( $css ) {
		return $css . ' a[href^="tel:"] { direction: ltr; unicode-bidi: embed; }';
	}

	/**
	 * Store the canonical phone; leave invalid input untouched for the error message.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public static function normalize_posted_phone( $data ) {
		if ( ! empty( $data['billing_phone'] ) ) {
			$normalized = self::normalize_phone( $data['billing_phone'] );
			if ( $normalized ) {
				$data['billing_phone'] = $normalized;
			}
		}
		return $data;
	}

	/**
	 * @param array    $data   Posted checkout data (phone already normalised when valid).
	 * @param WP_Error $errors Validation errors.
	 */
	public static function validate_phone( $data, $errors ) {
		$phone = $data['billing_phone'] ?? '';
		// WooCommerce already rejects non-phone characters; only add our message when its check passed.
		if ( '' !== $phone && WC_Validation::is_phone( $phone ) && ! self::normalize_phone( $phone ) ) {
			$errors->add(
				'billing_phone_validation',
				__( 'Please enter a valid mobile number, e.g. 5555 1234 or +974 5555 1234.', 'nafha-core' ),
				array( 'id' => 'billing_phone' )
			);
		}
	}
}

Nafha_Checkout::register();

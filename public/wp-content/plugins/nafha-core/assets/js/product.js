/**
 * Single product page: size buttons, live price, WhatsApp message, review photo form.
 * Relies on WooCommerce's variation events (jQuery) and never replaces its logic.
 */
( function ( $ ) {
	'use strict';

	const i18n = ( window.nafhaProduct && window.nafhaProduct.i18n ) || {};

	/* ------------------------------------------------------ size buttons */

	function syncSwatches( $form ) {
		$form.find( '.nafha-swatches' ).each( function () {
			const $group = $( this );
			const $select = $form.find( 'select[name="' + $group.data( 'attribute' ) + '"]' );
			const current = $select.val() || '';
			// WooCommerce removes/disables options that cannot combine with other choices.
			const available = $select.find( 'option:enabled' ).map( ( i, o ) => o.value ).get();

			$group.find( '.nafha-swatch' ).each( function () {
				const value = String( $( this ).data( 'value' ) );
				$( this )
					.attr( 'aria-checked', value === current ? 'true' : 'false' )
					.toggleClass( 'is-unavailable', ! available.includes( value ) );
			} );
		} );
	}

	/* ------------------------------------------------------- WhatsApp */

	function whatsappHref( $btn, lines ) {
		return 'https://wa.me/' + $btn.data( 'number' ) + '?text=' + encodeURIComponent( lines.join( '\n' ) );
	}

	function updateWhatsApp( $form, $btn, variation ) {
		if ( ! $btn.length ) {
			return;
		}
		const qty = parseInt( $form.find( 'input.qty' ).val(), 10 ) || 1;
		const lines = [ i18n.intro, '', '• ' + $btn.data( 'name' ) ];
		let price = $btn.data( 'price' );

		if ( $form.hasClass( 'variations_form' ) ) {
			if ( ! variation ) {
				$btn.attr( 'href', '#' ).attr( 'aria-disabled', 'true' );
				return;
			}
			const sizes = $form.find( '.nafha-swatch[aria-checked="true"] .nafha-swatch__name' )
				.map( ( i, el ) => $( el ).text().trim() ).get();
			if ( sizes.length ) {
				lines.push( i18n.size + ': ' + sizes.join( ' / ' ) );
			}
			price = $( '<div>' ).html( variation.price_html ).find( '.woocommerce-Price-amount' ).last().text().trim() || price;
			$btn.data( 'variant', sizes.join( ' / ' ) ).data( 'value', variation.display_price );
		}

		lines.push( i18n.quantity + ': ' + qty );
		lines.push( i18n.price + ': ' + price );
		lines.push( '', $btn.data( 'url' ) );
		$btn.attr( 'href', whatsappHref( $btn, lines ) ).removeAttr( 'aria-disabled' );
	}

	/* ---------------------------------------------------------- forms */

	$( function () {
		$( 'form.cart' ).each( function () {
			const $form = $( this );
			const $btn = $form.find( '.nafha-wa-btn' );
			const $price = $form.closest( '.product' ).find( '.summary .price, .entry-summary .price' ).first();
			const originalPrice = $price.html();
			let currentVariation = null;

			$form.on( 'click', '.nafha-swatch', function () {
				const $swatch = $( this );
				if ( $swatch.hasClass( 'is-unavailable' ) ) {
					return;
				}
				$form.find( 'select[name="' + $swatch.closest( '.nafha-swatches' ).data( 'attribute' ) + '"]' )
					.val( String( $swatch.data( 'value' ) ) )
					.trigger( 'change' );
			} );

			$form.on( 'change', 'select', () => syncSwatches( $form ) );
			$form.on( 'woocommerce_update_variation_values', () => syncSwatches( $form ) );

			$form.on( 'found_variation', function ( e, variation ) {
				currentVariation = variation;
				if ( variation.price_html ) {
					// Show the chosen size's price in the main price slot instead of the range.
					$price.html( $( variation.price_html ).html() );
				}
				syncSwatches( $form );
				updateWhatsApp( $form, $btn, variation );
			} );

			$form.on( 'reset_data', function () {
				currentVariation = null;
				$price.html( originalPrice );
				syncSwatches( $form );
				updateWhatsApp( $form, $btn, null );
			} );

			$form.on( 'change input', 'input.qty', () => updateWhatsApp( $form, $btn, currentVariation ) );

			$btn.on( 'click', function ( e ) {
				if ( $btn.attr( 'aria-disabled' ) === 'true' ) {
					e.preventDefault();
					window.alert( i18n.chooseSize );
					return;
				}
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push( {
					event: 'whatsapp_click',
					whatsapp_location: 'product',
					item_id: String( $btn.data( 'sku' ) ),
					item_name: String( $btn.data( 'name' ) ),
					item_variant: $btn.data( 'variant' ) || undefined,
					value: Number( $btn.data( 'value' ) ) || undefined,
				} );
			} );

			syncSwatches( $form );
			if ( ! $form.hasClass( 'variations_form' ) ) {
				updateWhatsApp( $form, $btn, null );
			}
		} );

		// Review form: allow the optional photo upload.
		$( '#review_form form#commentform' ).attr( 'enctype', 'multipart/form-data' );
	} );
}( jQuery ) );

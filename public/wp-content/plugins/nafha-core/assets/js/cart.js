/**
 * Side cart: opens after any AJAX add-to-cart (shop cards and Blocksy's
 * single-product AJAX button) and from the header / bottom-bar cart links.
 * Contents are refreshed by WooCommerce's cart fragments.
 */
( function ( $ ) {
	'use strict';

	const ID = 'nafha-cart';

	function openCart( trigger ) {
		return window.nafhaDrawer.open( ID, trigger );
	}

	// Cart links in the header (Blocksy) and the mobile bottom bar open the drawer instead of navigating.
	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '[data-id="cart"] .ct-cart-item, .nafha-bnav__item--cart' );
		if ( link && ! event.metaKey && ! event.ctrlKey && openCart( link ) ) {
			event.preventDefault();
		}
	} );

	$( document.body ).on( 'added_to_cart', function () {
		openCart( null );
	} );

}( jQuery ) );

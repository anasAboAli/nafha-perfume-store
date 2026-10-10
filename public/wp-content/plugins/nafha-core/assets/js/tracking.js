/**
 * Tracking helpers (see Nafha_Tracking):
 *  - pushes add_to_cart events delivered inside WooCommerce cart fragments,
 *    once each (fragments are re-applied from cache on later page loads);
 *  - cookie consent bar → Google Consent Mode update.
 */
( function () {
	'use strict';

	window.dataLayer = window.dataLayer || [];
	const SEEN_KEY = 'nafha_dl_seen';

	function seen() {
		try {
			return JSON.parse( sessionStorage.getItem( SEEN_KEY ) || '[]' );
		} catch ( e ) {
			return [];
		}
	}

	function remember( ids ) {
		try {
			sessionStorage.setItem( SEEN_KEY, JSON.stringify( ids.slice( -50 ) ) );
		} catch ( e ) {}
	}

	function flushQueue() {
		const holder = document.querySelector( '.nafha-dl-queue' );
		if ( ! holder ) {
			return;
		}
		let events = [];
		try {
			events = JSON.parse( holder.dataset.events || '[]' );
		} catch ( e ) {
			return;
		}
		const done = seen();
		events.forEach( ( event ) => {
			if ( event.event_id && done.includes( event.event_id ) ) {
				return;
			}
			window.dataLayer.push( { ecommerce: null } );
			window.dataLayer.push( event );
			if ( event.event_id ) {
				done.push( event.event_id );
			}
		} );
		remember( done );
		holder.dataset.events = '[]';
	}

	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'added_to_cart wc_fragments_refreshed wc_fragments_loaded', flushQueue );
	}

	/* ------------------------------------------------------------ consent */

	const bar = document.querySelector( '.nafha-consent' );
	let stored = null;
	try {
		stored = localStorage.getItem( 'nafha_consent' );
	} catch ( e ) {}

	if ( bar && ! stored ) {
		bar.hidden = false;
	}

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-nafha-consent]' );
		if ( ! button ) {
			return;
		}
		const choice = button.dataset.nafhaConsent;
		try {
			localStorage.setItem( 'nafha_consent', choice );
		} catch ( e ) {}
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'consent', 'update', {
				ad_storage: choice,
				ad_user_data: choice,
				ad_personalization: choice,
				analytics_storage: choice,
			} );
		}
		window.dataLayer.push( { event: 'consent_update', consent_choice: choice } );
		if ( bar ) {
			bar.hidden = true;
		}
	} );
}() );

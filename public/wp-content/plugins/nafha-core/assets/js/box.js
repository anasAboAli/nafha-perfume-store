/**
 * Discovery box builder ([nafha_discovery_box]): pick box size + ml, select
 * perfumes, add them to the cart in one request, then open the side cart.
 */
( function ( $ ) {
	'use strict';

	const root = document.querySelector( '.nafha-box' );
	if ( ! root || ! window.nafhaBox ) {
		return;
	}

	const cfg = window.nafhaBox;
	const tiles = [ ...root.querySelectorAll( '.nafha-box__tile' ) ];
	const addBtn = root.querySelector( '.nafha-box__add' );
	const countEl = root.querySelector( '.nafha-box__count' );
	const fullEl = root.querySelector( '.nafha-box__amount del' );
	const totalEl = root.querySelector( '.nafha-box__amount strong' );

	const money = ( n ) => cfg.currency + n.toFixed( 2 );
	const boxCount = () => Number( root.querySelector( 'input[name="nafha_box_count"]:checked' ).value );
	const boxSize = () => root.querySelector( 'input[name="nafha_box_size"]:checked' ).value;
	const sizesOf = ( tile ) => JSON.parse( tile.dataset.sizes );
	const selected = () => tiles.filter( ( t ) => t.getAttribute( 'aria-pressed' ) === 'true' );

	function refresh() {
		const size = boxSize();
		const count = boxCount();

		tiles.forEach( ( tile ) => {
			const option = sizesOf( tile )[ size ];
			tile.disabled = ! option;
			tile.querySelector( '.nafha-box__price' ).textContent = option ? money( option.price ) : '—';
			if ( ! option ) {
				tile.setAttribute( 'aria-pressed', 'false' );
			}
		} );

		// Keep only as many selections as the box holds.
		selected().slice( count ).forEach( ( t ) => t.setAttribute( 'aria-pressed', 'false' ) );

		const chosen = selected();
		const full = chosen.reduce( ( sum, t ) => sum + sizesOf( t )[ size ].price, 0 );
		const discount = Number( cfg.boxes[ count ] ) || 0;

		countEl.textContent = cfg.i18n.chosen.replace( '%1$d', chosen.length ).replace( '%2$d', count );
		fullEl.textContent = chosen.length ? money( full ) : '';
		totalEl.textContent = chosen.length ? money( full * ( 100 - discount ) / 100 ) : '';
		addBtn.disabled = chosen.length !== count;
		tiles.forEach( ( t ) => t.classList.toggle( 'is-locked', chosen.length >= count && t.getAttribute( 'aria-pressed' ) !== 'true' ) );
	}

	root.addEventListener( 'change', refresh );

	root.addEventListener( 'click', ( event ) => {
		const tile = event.target.closest( '.nafha-box__tile' );
		if ( ! tile || tile.disabled ) {
			return;
		}
		const on = tile.getAttribute( 'aria-pressed' ) === 'true';
		if ( ! on && selected().length >= boxCount() ) {
			return; // Box is full: deselect something first.
		}
		tile.setAttribute( 'aria-pressed', on ? 'false' : 'true' );
		refresh();
	} );

	addBtn.addEventListener( 'click', () => {
		const size = boxSize();
		const items = selected().map( ( t ) => sizesOf( t )[ size ].id );
		addBtn.disabled = true;
		addBtn.classList.add( 'loading' );

		$.post( cfg.ajaxUrl, { action: 'nafha_add_box', nonce: cfg.nonce, count: boxCount(), size, items } )
			.done( ( response ) => {
				if ( ! response || ! response.fragments ) {
					window.alert( ( response && response.data && response.data.message ) || cfg.i18n.error );
					return;
				}
				$.each( response.fragments, ( selector, html ) => $( selector ).replaceWith( html ) );
				$( document.body ).trigger( 'added_to_cart', [ response.fragments, response.cart_hash ] );
				selected().forEach( ( t ) => t.setAttribute( 'aria-pressed', 'false' ) );
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push( { event: 'discovery_box_add', box_size: items.length, item_size: size } );
			} )
			.fail( ( xhr ) => {
				window.alert( ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) || cfg.i18n.error );
			} )
			.always( () => {
				addBtn.classList.remove( 'loading' );
				refresh();
			} );
	} );

	refresh();
}( jQuery ) );

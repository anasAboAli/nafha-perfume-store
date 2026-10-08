/**
 * Shared off-canvas drawer (side cart, shop filters).
 *
 *   nafhaDrawer.open( 'nafha-cart', triggerEl )
 *   nafhaDrawer.close()
 *
 * Markup: .nafha-drawer[hidden] > .nafha-drawer__overlay + .nafha-drawer__panel;
 * any [data-nafha-close] inside closes it; Escape closes; focus returns to the trigger.
 */
( function () {
	'use strict';

	let current = null;
	let trigger = null;

	function open( id, opener ) {
		const drawer = document.getElementById( id );
		if ( ! drawer ) {
			return false;
		}
		if ( current && current !== drawer ) {
			close();
		}
		current = drawer;
		trigger = opener || document.activeElement;
		drawer.hidden = false;
		document.documentElement.classList.add( 'nafha-drawer-open' );
		// Force a reflow so the slide-in transition runs (rAF would stall in background tabs).
		void drawer.offsetWidth;
		drawer.classList.add( 'is-open' );
		if ( opener ) {
			opener.setAttribute( 'aria-expanded', 'true' );
		}
		const focusable = drawer.querySelector( '.nafha-drawer__close' );
		if ( focusable ) {
			focusable.focus( { preventScroll: true } );
		}
		return true;
	}

	function close() {
		if ( ! current ) {
			return;
		}
		const drawer = current;
		current = null;
		drawer.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'nafha-drawer-open' );
		setTimeout( () => {
			if ( current !== drawer ) {
				drawer.hidden = true;
			}
		}, 250 );
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
			if ( document.contains( trigger ) ) {
				trigger.focus( { preventScroll: true } );
			}
		}
	}

	document.addEventListener( 'click', ( event ) => {
		if ( current && event.target.closest( '[data-nafha-close]' ) && current.contains( event.target ) ) {
			// Let submit buttons and links do their job; just close the drawer.
			if ( event.target.closest( 'button:not([type="submit"])' ) || event.target.closest( '.nafha-drawer__overlay' ) ) {
				event.preventDefault();
			}
			close();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			close();
		}
	} );

	window.nafhaDrawer = { open, close, isOpen: ( id ) => current !== null && current.id === id };
}() );

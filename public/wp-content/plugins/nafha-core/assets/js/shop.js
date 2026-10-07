/**
 * Shop filters: off-canvas panel + in-place results (no page reload).
 *
 * Every change builds a normal WooCommerce filter URL, fetches that page and
 * swaps the results section and the panel body, then pushes the URL to
 * history — so back/forward, sharing and refresh all work.
 */
( function () {
	'use strict';

	const RESULTS = 'main#main .ct-container > section';
	const drawer = document.getElementById( 'nafha-filters' );
	let controller = null;
	let lastToggle = null;

	/* -------------------------------------------------------- drawer */

	function openDrawer( toggle ) {
		if ( ! drawer ) {
			return;
		}
		lastToggle = toggle;
		drawer.hidden = false;
		document.documentElement.classList.add( 'nafha-drawer-open' );
		requestAnimationFrame( () => drawer.classList.add( 'is-open' ) );
		toggle.setAttribute( 'aria-expanded', 'true' );
		const first = drawer.querySelector( '.nafha-drawer__close' );
		if ( first ) {
			first.focus();
		}
	}

	function closeDrawer() {
		if ( ! drawer || drawer.hidden ) {
			return;
		}
		drawer.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'nafha-drawer-open' );
		setTimeout( () => {
			drawer.hidden = true;
		}, 250 );
		document.querySelectorAll( '.nafha-filter-toggle' ).forEach( ( t ) => t.setAttribute( 'aria-expanded', 'false' ) );
		if ( lastToggle && document.contains( lastToggle ) ) {
			lastToggle.focus();
		}
	}

	/* --------------------------------------------------- URL from form */

	function urlFromForm( form ) {
		const url = new URL( form.dataset.baseUrl, window.location.origin );
		const current = new URL( window.location.href );
		[ 'orderby', 's', 'post_type' ].forEach( ( key ) => {
			if ( current.searchParams.has( key ) ) {
				url.searchParams.set( key, current.searchParams.get( key ) );
			}
		} );

		const groups = {};
		form.querySelectorAll( 'input:checked' ).forEach( ( input ) => {
			if ( input.value === '' ) {
				return; // "Any price".
			}
			( groups[ input.name ] = groups[ input.name ] || [] ).push( input.value );
		} );

		Object.entries( groups ).forEach( ( [ group, values ] ) => {
			if ( group === 'price' ) {
				const bucket = form.querySelector( 'input[name="price"]:checked' ).dataset;
				url.searchParams.set( 'min_price', bucket.min );
				if ( bucket.max ) {
					url.searchParams.set( 'max_price', bucket.max );
				}
			} else if ( group === 'product_brand' ) {
				url.searchParams.set( 'filter_product_brand', values.join( ',' ) );
			} else {
				const name = group.replace( /^pa_/, '' );
				url.searchParams.set( 'filter_' + name, values.join( ',' ) );
				url.searchParams.set( 'query_type_' + name, 'or' );
			}
		} );
		return url.toString();
	}

	/* ---------------------------------------------------- navigation */

	async function navigate( url, { push = true, scroll = false } = {} ) {
		const results = document.querySelector( RESULTS );
		if ( ! results ) {
			window.location.href = url;
			return;
		}
		if ( controller ) {
			controller.abort();
		}
		controller = new AbortController();
		results.classList.add( 'nafha-loading' );
		drawer?.classList.add( 'nafha-loading' );

		try {
			const response = await fetch( url, { signal: controller.signal, credentials: 'same-origin' } );
			const doc = new DOMParser().parseFromString( await response.text(), 'text/html' );
			const nextResults = doc.querySelector( RESULTS );
			if ( ! nextResults ) {
				throw new Error( 'No results section' );
			}
			results.innerHTML = nextResults.innerHTML;

			// Refresh the panel's counts/footer but keep it open and keep focus where it was.
			const nextDrawer = doc.getElementById( 'nafha-filters' );
			if ( drawer && nextDrawer ) {
				[ '.nafha-drawer__body', '.nafha-drawer__footer' ].forEach( ( sel ) => {
					const open = [ ...drawer.querySelectorAll( 'details[open]' ) ].map( ( d ) => d.dataset.group );
					const target = drawer.querySelector( sel );
					const source = nextDrawer.querySelector( sel );
					if ( target && source ) {
						target.innerHTML = source.innerHTML;
						target.querySelectorAll( 'details' ).forEach( ( d ) => {
							d.open = open.includes( d.dataset.group ) || d.querySelector( 'input:checked' ) !== null;
						} );
					}
				} );
			}

			if ( push ) {
				window.history.pushState( { nafha: true }, '', url );
			}
			document.title = doc.title;
			if ( scroll ) {
				results.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
			// Let Blocksy/WooCommerce re-bind anything inside the new markup.
			document.body.dispatchEvent( new CustomEvent( 'nafha:results-updated', { bubbles: true } ) );
			if ( window.ctEvents ) {
				window.ctEvents.trigger( 'blocksy:frontend:init' );
			}
		} catch ( error ) {
			if ( error.name !== 'AbortError' ) {
				window.location.href = url; // Fall back to a normal page load.
			}
		} finally {
			results.classList.remove( 'nafha-loading' );
			drawer?.classList.remove( 'nafha-loading' );
		}
	}

	/* -------------------------------------------------------- events */

	document.addEventListener( 'click', ( event ) => {
		const toggle = event.target.closest( '.nafha-filter-toggle' );
		if ( toggle ) {
			openDrawer( toggle );
			return;
		}

		const link = event.target.closest( '[data-nafha-nav], .woocommerce-pagination a' );
		if ( link && link.href && ! event.metaKey && ! event.ctrlKey ) {
			event.preventDefault();
			navigate( link.href, { scroll: ! drawer || drawer.hidden } );
			return;
		}

		if ( event.target.closest( '[data-nafha-close]' ) ) {
			event.preventDefault();
			closeDrawer();
		}
	} );

	document.addEventListener( 'change', ( event ) => {
		const form = event.target.closest( '#nafha-filters form' );
		if ( form ) {
			navigate( urlFromForm( form ) );
		}
	} );

	// Sorting: WooCommerce reloads the page from a jQuery handler on the form. Catch the
	// change in the capture phase (before it reaches that handler) and sort in place.
	document.addEventListener(
		'change',
		( event ) => {
			if ( ! event.target.matches( '.woocommerce-ordering select.orderby' ) ) {
				return;
			}
			event.stopPropagation();
			const url = new URL( window.location.href );
			url.searchParams.set( 'orderby', event.target.value );
			url.searchParams.delete( 'paged' );
			url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
			navigate( url.toString() );
		},
		true
	);

	document.addEventListener( 'submit', ( event ) => {
		if ( event.target.closest( '.woocommerce-ordering' ) ) {
			event.preventDefault(); // Handled on change above.
			return;
		}
		if ( event.target.closest( '#nafha-filters form' ) ) {
			event.preventDefault();
			closeDrawer();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			closeDrawer();
		}
	} );

	window.addEventListener( 'popstate', () => navigate( window.location.href, { push: false } ) );
}() );

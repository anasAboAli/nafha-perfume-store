// Key customer journeys (acceptance criteria, section 21 of the requirements).
// Orders placed here use the billing name "اختبار آلي" so they can be cleaned up:
//   tools/wp.sh eval-file tools/e2e/cleanup.php
const { test, expect } = require( '@playwright/test' );

const isMobile = ( testInfo ) => /iphone|android/.test( testInfo.project.name );

test.beforeEach( async ( { page } ) => {
	// Consent already given, so the bar never covers buttons; collect JS errors.
	await page.addInitScript( () => localStorage.setItem( 'nafha_consent', 'granted' ) );
	page.jsErrors = [];
	page.on( 'pageerror', ( err ) => page.jsErrors.push( err.message ) );
} );

test.afterEach( async ( { page } ) => {
	expect( page.jsErrors, 'no JavaScript errors' ).toEqual( [] );
} );

async function openCartIfNeeded( page ) {
	await expect( page.locator( '#nafha-cart' ) ).toHaveClass( /is-open/ );
}

test( 'home page renders all sections in RTL', async ( { page } ) => {
	await page.goto( '/' );
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'dir', 'rtl' );
	await expect( page.locator( '.nafha-hero h1' ) ).toBeVisible();
	await expect( page.locator( '.nafha-categories li.product-category' ) ).toHaveCount( 4 );
	await expect( page.locator( '.nafha-best-sellers li.product' ) ).toHaveCount( 4 );
	await expect( page.locator( '.nafha-review' ).first() ).toBeVisible();
	// No horizontal scrolling on any viewport.
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	expect( overflow ).toBeLessThanOrEqual( 1 );
} );

test( 'product page: size buttons, live price, side cart, WhatsApp message', async ( { page }, testInfo ) => {
	await page.goto( '/product/creed-aventus/' );
	await page.locator( '.nafha-swatch[data-value="10ml"]' ).click();
	await expect( page.locator( '.entry-summary .price' ).first() ).toContainText( '32.00' );
	await expect( page.locator( '.nafha-wa-btn' ) ).toHaveAttribute( 'href', /wa\.me\/970598143863\?text=.*10%20%D9%85%D9%84/ );

	await page.locator( 'form.cart .single_add_to_cart_button' ).click();
	await openCartIfNeeded( page );
	await expect( page.locator( '#nafha-cart .woocommerce-mini-cart-item' ) ).toHaveCount( 1 );
	await expect( page.locator( '#nafha-cart .nafha-ship-progress' ) ).toBeVisible();

	const events = await page.evaluate( () => window.dataLayer.filter( ( e ) => e.event ).map( ( e ) => e.event ) );
	expect( events ).toEqual( expect.arrayContaining( [ 'view_item', 'add_to_cart' ] ) );

	if ( isMobile( testInfo ) ) {
		await expect( page.locator( '.nafha-bnav__count' ) ).toHaveText( '1' );
	}
} );

test( 'shop filters update results without reloading', async ( { page } ) => {
	await page.goto( '/shop/' );
	const all = await page.locator( 'ul.products > li' ).count();
	await page.evaluate( () => { window.__sameDocument = true; } );

	await page.locator( '.nafha-filter-toggle' ).first().click();
	await page.locator( '#nafha-filters input[name="pa_gender"][value="women"]' ).check();
	await expect( page ).toHaveURL( /filter_gender=women/ );
	await expect.poll( () => page.locator( 'ul.products > li' ).count() ).toBeLessThan( all );
	expect( await page.evaluate( () => window.__sameDocument ) ).toBe( true );
	await expect( page.locator( '.nafha-drawer__apply' ) ).toContainText( /\d/ );
} );

test( 'search finds perfumes by Arabic brand name and by note', async ( { page } ) => {
	await page.goto( '/?s=' + encodeURIComponent( 'شنل' ) + '&post_type=product' );
	await expect( page.locator( 'ul.products > li' ) ).toHaveCount( 2 );
	await page.goto( '/?s=' + encodeURIComponent( 'فانيليا' ) + '&post_type=product' );
	expect( await page.locator( 'ul.products > li' ).count() ).toBeGreaterThanOrEqual( 5 );
} );

test( 'fragrance quiz returns matches with a percentage', async ( { page } ) => {
	await page.goto( '/fragrance-quiz/' );
	for ( const value of [ 'women', 'winter', 'evening' ] ) {
		await page.locator( `.nafha-quiz__step:not([hidden]) input[value="${ value }"]` ).locator( '..' ).click();
	}
	await page.locator( '.nafha-quiz__step:not([hidden]) input[value="floral"]' ).locator( '..' ).click();
	await page.locator( '.nafha-quiz__step:not([hidden]) .nafha-quiz__next' ).click();
	await page.locator( '.nafha-quiz__step:not([hidden]) input[value="strong"]' ).locator( '..' ).click();
	await page.locator( '.nafha-quiz__step:not([hidden]) input[value="any"]' ).locator( '..' ).click();
	await page.locator( '.nafha-quiz__step:not([hidden]) .nafha-quiz__next' ).click();

	await expect( page.locator( '.nafha-quiz-results li.product' ).first() ).toBeVisible();
	await expect( page.locator( '.nafha-match' ).first() ).toContainText( '%' );
} );

test( 'discovery box: pick 3, discounted, added as a box', async ( { page } ) => {
	await page.goto( '/discovery-box/' );
	const tiles = page.locator( '.nafha-box__tile:not([disabled])' );
	for ( let i = 0; i < 3; i++ ) {
		await tiles.nth( i ).click();
	}
	await expect( page.locator( '.nafha-box__add' ) ).toBeEnabled();
	await page.locator( '.nafha-box__add' ).click();
	await openCartIfNeeded( page );
	await expect( page.locator( '#nafha-cart .woocommerce-mini-cart-item' ) ).toHaveCount( 3 );
	await expect( page.locator( '#nafha-cart' ) ).toContainText( 'صندوق التجربة' );
} );

test( 'guest checkout with cash on delivery: order received + purchase event', async ( { page } ) => {
	await page.goto( '/product/dior-jadore-edp/' );
	await page.locator( '.nafha-swatch[data-value="5ml"]' ).click();
	await page.locator( 'form.cart .single_add_to_cart_button' ).click();
	await openCartIfNeeded( page );

	await page.goto( '/checkout/' );
	await page.locator( '#billing_first_name' ).fill( 'اختبار آلي' );
	await page.locator( '#billing_phone' ).fill( '5512 3456' );
	await page.locator( '#billing_state' ).selectOption( 'RA', { force: true } );
	await page.locator( '#billing_address_1' ).fill( 'منطقة 52، شارع 10، مبنى 3' );
	await expect( page.locator( '.woocommerce-shipping-totals' ) ).toContainText( '6.00' );
	await page.locator( '#payment_method_cod' ).check( { force: true } );
	await page.locator( '#place_order' ).click();

	await expect( page ).toHaveURL( /order-received/, { timeout: 30_000 } );
	await expect( page.locator( '.woocommerce-order-overview__order' ) ).toBeVisible();
	const purchase = await page.evaluate( () => window.dataLayer.find( ( e ) => e.event === 'purchase' ) );
	expect( purchase.ecommerce.value ).toBe( 16 ); // $10 sample + $6 Al Rayyan delivery.
} );

test( 'invalid phone number is rejected at checkout', async ( { page } ) => {
	await page.goto( '/product/dior-jadore-edp/' );
	await page.locator( 'form.cart .single_add_to_cart_button' ).click();
	await openCartIfNeeded( page );
	await page.goto( '/checkout/' );
	await page.locator( '#billing_first_name' ).fill( 'اختبار آلي' );
	await page.locator( '#billing_phone' ).fill( '1234' );
	await page.locator( '#billing_state' ).selectOption( 'DA', { force: true } );
	await page.locator( '#billing_address_1' ).fill( 'الدوحة' );
	await page.locator( '#payment_method_cod' ).check( { force: true } );
	await page.locator( '#place_order' ).click();
	await expect( page.locator( '.woocommerce-error' ).first() ).toContainText( 'رقم جوال صحيح' );
	await expect( page ).not.toHaveURL( /order-received/ );
} );

// Lists Latin-script words visible on the storefront, to catch untranslated
// strings (acceptance criterion: no stray English in RTL). Brand / product
// names and units are expected and filtered out below.
// Run from tools/:  node e2e/i18n-scan.js
const { chromium, devices } = require( '@playwright/test' );

const BASE = process.env.NAFHA_URL || 'http://localhost:8080';
const PAGES = [ '/', '/shop/', '/product-category/men-perfumes/', '/brand/dior/', '/product/dior-sauvage-edp/',
	'/product/women-gift-set/', '/fragrance-quiz/', '/discovery-box/', '/about/', '/contact/', '/faq/',
	'/shipping-returns/', '/terms/', '/privacy-policy/', '/my-account/', '/nafha-login/',
	'/?s=%D8%B9%D9%88%D8%AF&post_type=product', '/this-page-does-not-exist/', '/cart/', '/checkout/' ];

// Expected Latin: brands, English product names, SKUs, units, currency, technical tokens.
const ALLOW = /^(dior|chanel|creed|tom|ford|yves|saint|laurent|ysl|giorgio|armani|maison|francis|kurkdjian|mfk|parfums?|de|marly|amouage|lattafa|sauvage|bleu|aventus|oud|wood|baccarat|rouge|coco|mademoiselle|libre|j'?adore|acqua|di|gi[oò]|layton|interlude|man|khamrah|eau|toilette|edp|edt|men's|women's|discovery|gift|set|nafha|decants|ml|usd|whatsapp|gtm|ga4|jpg|png|webp|mb|qr|sku|cloudflare|turnstile)$/i;

( async () => {
	const browser = await chromium.launch();
	const context = await browser.newContext( { ...devices[ 'Pixel 7' ], locale: 'ar-QA' } );
	await context.addInitScript( () => localStorage.setItem( 'nafha_consent', 'granted' ) );
	const page = await context.newPage();

	// Put something in the cart so cart/checkout render fully.
	const giftSet = process.env.NAFHA_SIMPLE_PRODUCT_ID || '292'; // A simple product: add-to-cart by URL.
	await page.goto( BASE + '/cart/?add-to-cart=' + giftSet );

	const found = {};
	for ( const path of PAGES ) {
		await page.goto( BASE + path, { waitUntil: 'load' } );
		await page.waitForTimeout( 800 ); // Turnstile keeps a connection open, so "networkidle" never comes.
		// Visible text plus placeholders, button values, alt and aria-labels a visitor or screen reader meets.
		const text = await page.evaluate( () => {
			const parts = [ document.body.innerText ];
			document.querySelectorAll( '[placeholder],[aria-label],img[alt],input[type=submit],button[value]' ).forEach( ( el ) => {
				parts.push( el.getAttribute( 'placeholder' ) || '', el.getAttribute( 'aria-label' ) || '', el.getAttribute( 'alt' ) || '', el.value || '' );
			} );
			return parts.join( '\n' );
		} );
		for ( const word of text.match( /[A-Za-z][A-Za-z'’.-]{1,}/g ) || [] ) {
			const clean = word.replace( /[.'’-]+$/, '' );
			if ( clean.length < 2 || ALLOW.test( clean ) ) {
				continue;
			}
			( found[ clean ] = found[ clean ] || new Set() ).add( path );
		}
	}
	await browser.close();

	const words = Object.keys( found ).sort();
	console.log( words.length ? 'Latin words to review:' : 'No unexpected Latin words found.' );
	words.forEach( ( w ) => console.log( `  ${ w.padEnd( 22 ) } ${ [ ...found[ w ] ].slice( 0, 4 ).join( '  ' ) }` ) );
} )();

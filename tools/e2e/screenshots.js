// Portfolio screenshots (mobile + desktop) → docs/screenshots/*.webp
//   cd tools && node e2e/screenshots.js        (NAFHA_URL to shoot another copy)
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const sharp = require( 'sharp' );

const BASE = process.env.NAFHA_URL || 'http://localhost:8080';
const OUT = path.join( __dirname, '..', '..', 'docs', 'screenshots' );

const viewports = {
	mobile: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
	desktop: { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 },
};

// name → steps run after the page loads.
const shots = {
	home: { url: '/' },
	shop: { url: '/shop/' },
	product: { url: '/product/dior-sauvage-edp/' },
	quiz: {
		url: '/fragrance-quiz/',
		async run( page ) {
			const pick = ( value ) => page.locator( `.nafha-quiz__step:not([hidden]) input[value="${ value }"]` ).locator( '..' ).click();
			const next = () => page.locator( '.nafha-quiz__step:not([hidden]) .nafha-quiz__next' ).click();
			for ( const value of [ 'women', 'winter', 'evening', 'floral' ] ) {
				await pick( value );
			}
			await next();
			await pick( 'strong' );
			await pick( 'any' );
			await next();
			await page.locator( '.nafha-quiz-results li.product' ).first().waitFor();
			await page.locator( '.nafha-quiz-results' ).scrollIntoViewIfNeeded();
		},
	},
	'discovery-box': {
		url: '/discovery-box/',
		async run( page ) {
			const tiles = page.locator( '.nafha-box__tile:not([disabled])' );
			for ( let i = 0; i < 3; i++ ) {
				await tiles.nth( i ).click();
			}
			await page.locator( '.nafha-box__add' ).scrollIntoViewIfNeeded();
		},
	},
	cart: {
		url: '/product/dior-sauvage-edp/',
		async run( page ) {
			await page.locator( '.single_add_to_cart_button' ).click();
			await page.locator( '#nafha-cart.is-open' ).waitFor();
		},
	},
};

( async () => {
	const browser = await chromium.launch();
	for ( const [ device, options ] of Object.entries( viewports ) ) {
		const context = await browser.newContext( { ...options, locale: 'ar' } );
		await context.addInitScript( () => localStorage.setItem( 'nafha_consent', 'granted' ) );
		for ( const [ name, shot ] of Object.entries( shots ) ) {
			const page = await context.newPage();
			await page.goto( BASE + shot.url, { waitUntil: 'load' } );
			if ( shot.run ) {
				await shot.run( page );
			}
			await page.waitForTimeout( 800 ); // Animations / lazy images settle.
			const file = path.join( OUT, `${ name }-${ device }.webp` );
			await sharp( await page.screenshot() ).webp( { quality: 80 } ).toFile( file );
			console.log( path.relative( process.cwd(), file ) );
			await page.close();
		}
		await context.close();
	}
	await browser.close();
} )();

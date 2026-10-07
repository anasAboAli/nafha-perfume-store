/**
 * Generates demo product images (WebP, 1000×1000) from tools/setup/demo-products.json:
 *   <slug>-1.webp  hero: decant bottle with the perfume's colour and label
 *   <slug>-2.webp  the three decant sizes side by side (variable products only)
 *
 * Usage (from tools/):  npm run images
 * These are placeholders until the client's real product photos arrive.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const sharp = require( 'sharp' );

const products = require( '../setup/demo-products.json' );
const OUT = path.join( __dirname, 'out' );
const SIZE = 1000;

fs.mkdirSync( OUT, { recursive: true } );

const esc = ( s ) => String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( /'/g, '&apos;' );

/** Mix a hex colour with white; amount 0 = colour, 1 = white. */
function tint( hex, amount ) {
	const n = parseInt( hex.slice( 1 ), 16 );
	const c = [ n >> 16, ( n >> 8 ) & 255, n & 255 ].map( ( v ) => Math.round( v + ( 255 - v ) * amount ) );
	return '#' + c.map( ( v ) => v.toString( 16 ).padStart( 2, '0' ) ).join( '' );
}

/** Font size that fits `text` into `width` px (rough average glyph width). */
const fit = ( text, width, max, ratio = 0.6 ) => Math.min( max, Math.floor( width / ( text.length * ratio ) ) );

function defs( p ) {
	return `
	<defs>
		<radialGradient id="bg" cx="50%" cy="42%" r="70%">
			<stop offset="0" stop-color="${ tint( p.color, 0.82 ) }"/>
			<stop offset="1" stop-color="#FBF8F4"/>
		</radialGradient>
		<linearGradient id="liquid" x1="0" x2="1">
			<stop offset="0" stop-color="${ tint( p.color, 0.15 ) }"/>
			<stop offset=".55" stop-color="${ p.color }"/>
			<stop offset="1" stop-color="${ tint( p.color, 0.25 ) }"/>
		</linearGradient>
		<linearGradient id="gold" x1="0" x2="1">
			<stop offset="0" stop-color="#8f6d3f"/>
			<stop offset=".35" stop-color="#e2c48f"/>
			<stop offset=".6" stop-color="#b08a57"/>
			<stop offset="1" stop-color="#7d5d33"/>
		</linearGradient>
		<linearGradient id="glass" x1="0" x2="1">
			<stop offset="0" stop-color="#fff" stop-opacity=".55"/>
			<stop offset=".5" stop-color="#fff" stop-opacity=".15"/>
			<stop offset="1" stop-color="#fff" stop-opacity=".45"/>
		</linearGradient>
	</defs>`;
}

/**
 * A decant vial with atomizer. (x, y) = top-left of the glass body.
 */
function vial( x, y, w, h, opts = {} ) {
	const capW = w * 0.62;
	const capH = h * 0.2;
	const neckH = h * 0.05;
	const r = Math.min( 18, w * 0.12 );
	const liquidTop = y + h * ( opts.fill ? 1 - opts.fill : 0.22 );
	const label = opts.label || '';
	return `
	<ellipse cx="${ x + w / 2 }" cy="${ y + h + 14 }" rx="${ w * 0.62 }" ry="${ w * 0.09 }" fill="#2b2130" opacity=".12"/>
	<rect x="${ x + ( w - capW ) / 2 }" y="${ y - neckH - capH }" width="${ capW }" height="${ capH }" rx="${ capW * 0.12 }" fill="url(#gold)"/>
	<rect x="${ x + w * 0.3 }" y="${ y - neckH }" width="${ w * 0.4 }" height="${ neckH + 2 }" fill="#d8c6a8"/>
	<rect x="${ x }" y="${ y }" width="${ w }" height="${ h }" rx="${ r }" fill="#fff" fill-opacity=".35" stroke="#2b2130" stroke-opacity=".18" stroke-width="2"/>
	<rect x="${ x + 5 }" y="${ liquidTop }" width="${ w - 10 }" height="${ y + h - 5 - liquidTop }" rx="${ r - 4 }" fill="url(#liquid)" opacity=".92"/>
	<rect x="${ x }" y="${ y }" width="${ w }" height="${ h }" rx="${ r }" fill="url(#glass)"/>
	<rect x="${ x + w * 0.12 }" y="${ y + h * 0.06 }" width="${ w * 0.06 }" height="${ h * 0.8 }" rx="${ w * 0.03 }" fill="#fff" opacity=".55"/>
	${ label }`;
}

function hero( p ) {
	const x = 370;
	const y = 330;
	const w = 260;
	const h = 470;
	const nameSize = fit( p.name_en, 200, 34, 0.52 );
	const brandSize = fit( p.brand_label, 200, 15, 0.72 );
	const label = `
		<rect x="${ x + 30 }" y="${ y + 170 }" width="${ w - 60 }" height="150" rx="10" fill="#FBF8F4" opacity=".96"/>
		<text x="${ x + w / 2 }" y="${ y + 222 }" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="${ brandSize }" font-weight="700" letter-spacing="2.5" fill="#9a7647">${ esc( p.brand_label ) }</text>
		<text x="${ x + w / 2 }" y="${ y + 222 + nameSize + 16 }" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-size="${ nameSize }" fill="#2b2130">${ esc( p.name_en ) }</text>`;

	return `<svg xmlns="http://www.w3.org/2000/svg" width="${ SIZE }" height="${ SIZE }" viewBox="0 0 ${ SIZE } ${ SIZE }">
	${ defs( p ) }
	<rect width="100%" height="100%" fill="url(#bg)"/>
	<circle cx="500" cy="560" r="300" fill="${ tint( p.color, 0.7 ) }" opacity=".35"/>
	${ vial( x, y, w, h, { label } ) }
	<text x="500" y="935" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="16" letter-spacing="6" fill="#2b2130" opacity=".45">NAFHA · DECANTS</text>
</svg>`;
}

function sizes( p ) {
	const set = [
		{ label: '5 ML', w: 120, h: 250, x: 205 },
		{ label: '10 ML', w: 150, h: 320, x: 425 },
		{ label: '30 ML', w: 190, h: 410, x: 675 },
	];
	const base = 820;
	const vials = set.map( ( s ) => vial( s.x - s.w / 2 + 30, base - s.h, s.w, s.h ) +
		`<text x="${ s.x + 30 }" y="${ base + 70 }" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="24" font-weight="700" letter-spacing="2" fill="#2b2130">${ s.label }</text>` ).join( '' );
	const nameSize = fit( p.name_en, 760, 46, 0.5 );

	return `<svg xmlns="http://www.w3.org/2000/svg" width="${ SIZE }" height="${ SIZE }" viewBox="0 0 ${ SIZE } ${ SIZE }">
	${ defs( p ) }
	<rect width="100%" height="100%" fill="url(#bg)"/>
	<text x="500" y="140" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700" letter-spacing="4" fill="#9a7647">${ esc( p.brand_label ) }</text>
	<text x="500" y="${ 150 + nameSize + 10 }" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-size="${ nameSize }" fill="#2b2130">${ esc( p.name_en ) }</text>
	${ vials }
</svg>`;
}

( async () => {
	for ( const p of products ) {
		const jobs = [ [ '1', hero( p ) ] ];
		if ( p.type !== 'simple' ) {
			jobs.push( [ '2', sizes( p ) ] );
		}
		for ( const [ n, svg ] of jobs ) {
			await sharp( Buffer.from( svg ) ).webp( { quality: 82 } ).toFile( path.join( OUT, `${ p.slug }-${ n }.webp` ) );
		}
		console.log( '✓', p.slug );
	}
} )();

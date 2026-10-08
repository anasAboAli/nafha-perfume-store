/**
 * Home page hero image (WebP, 1920×900): a row of decant vials on the brand's
 * dark plum, bottles on the left so the RTL headline sits on the right.
 *
 * Usage (from tools/):  node images/hero.js
 */
const path = require( 'path' );
const sharp = require( 'sharp' );

const W = 1920;
const H = 900;
const vials = [
	{ color: '#b5473a', h: 360 },
	{ color: '#3d6f9e', h: 440 },
	{ color: '#c9a24a', h: 500 },
	{ color: '#6b3f2a', h: 420 },
	{ color: '#d9a3a0', h: 340 },
];

function tint( hex, amount ) {
	const n = parseInt( hex.slice( 1 ), 16 );
	const c = [ n >> 16, ( n >> 8 ) & 255, n & 255 ].map( ( v ) => Math.round( v + ( 255 - v ) * amount ) );
	return '#' + c.map( ( v ) => v.toString( 16 ).padStart( 2, '0' ) ).join( '' );
}

const base = 760;
const width = 150;
const gap = 46;
let x = 170;

const parts = vials.map( ( v, i ) => {
	const top = base - v.h;
	const capW = width * 0.62;
	const capH = v.h * 0.16;
	const out = `
	<defs>
		<linearGradient id="l${ i }" x1="0" x2="1">
			<stop offset="0" stop-color="${ tint( v.color, 0.2 ) }"/>
			<stop offset=".55" stop-color="${ v.color }"/>
			<stop offset="1" stop-color="${ tint( v.color, 0.3 ) }"/>
		</linearGradient>
	</defs>
	<ellipse cx="${ x + width / 2 }" cy="${ base + 16 }" rx="${ width * 0.62 }" ry="12" fill="#000" opacity=".35"/>
	<rect x="${ x + ( width - capW ) / 2 }" y="${ top - capH - 14 }" width="${ capW }" height="${ capH }" rx="10" fill="url(#gold)"/>
	<rect x="${ x + width * 0.3 }" y="${ top - 16 }" width="${ width * 0.4 }" height="18" fill="#d8c6a8"/>
	<rect x="${ x }" y="${ top }" width="${ width }" height="${ v.h }" rx="18" fill="#fff" fill-opacity=".08" stroke="#fff" stroke-opacity=".25" stroke-width="2"/>
	<rect x="${ x + 6 }" y="${ top + v.h * 0.2 }" width="${ width - 12 }" height="${ v.h * 0.8 - 6 }" rx="14" fill="url(#l${ i })" opacity=".95"/>
	<rect x="${ x + width * 0.13 }" y="${ top + 18 }" width="9" height="${ v.h * 0.8 }" rx="4" fill="#fff" opacity=".35"/>`;
	x += width + gap;
	return out;
} ).join( '' );

const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${ W }" height="${ H }" viewBox="0 0 ${ W } ${ H }">
	<defs>
		<radialGradient id="bg" cx="25%" cy="45%" r="80%">
			<stop offset="0" stop-color="#4a3550"/>
			<stop offset=".55" stop-color="#2b2130"/>
			<stop offset="1" stop-color="#1a141e"/>
		</radialGradient>
		<radialGradient id="glow" cx="50%" cy="50%" r="50%">
			<stop offset="0" stop-color="#c9a46c" stop-opacity=".35"/>
			<stop offset="1" stop-color="#c9a46c" stop-opacity="0"/>
		</radialGradient>
		<linearGradient id="gold" x1="0" x2="1">
			<stop offset="0" stop-color="#8f6d3f"/>
			<stop offset=".35" stop-color="#e2c48f"/>
			<stop offset=".6" stop-color="#b08a57"/>
			<stop offset="1" stop-color="#7d5d33"/>
		</linearGradient>
	</defs>
	<rect width="100%" height="100%" fill="url(#bg)"/>
	<ellipse cx="560" cy="520" rx="620" ry="380" fill="url(#glow)"/>
	<rect x="110" y="${ base + 4 }" width="${ x - 110 }" height="6" rx="3" fill="#b08a57" opacity=".6"/>
	${ parts }
</svg>`;

sharp( Buffer.from( svg ) )
	.webp( { quality: 80 } )
	.toFile( path.join( __dirname, 'out', 'home-hero.webp' ) )
	.then( ( info ) => console.log( '✓ home-hero.webp', info.width + '×' + info.height, Math.round( info.size / 1024 ) + ' KB' ) );

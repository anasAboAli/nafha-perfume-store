<?php
/**
 * Lists translatable strings in our theme/plugin that have no Arabic entry.
 * Usage:  /c/xampp/php/php.exe tools/check-translations.php   (exit code 1 if any are missing)
 */

$root    = dirname( __DIR__ ) . '/public/wp-content/';
$targets = array(
	'nafha-core'  => array( 'plugins/nafha-core', 'plugins/nafha-core/languages/nafha-core-ar.l10n.php' ),
	'nafha-child' => array( 'themes/nafha-child', 'themes/nafha-child/languages/ar.l10n.php' ),
);

// __( 'text', 'domain' ) and friends; _n( 'single', 'plural', $n, 'domain' ).
$functions = '(?:__|_e|_x|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x)';
$string    = "'((?:[^'\\\\]|\\\\.)*)'";

$missing = 0;
$checked = 0;
foreach ( $targets as $domain => list( $dir, $file ) ) {
	$messages = ( include $root . $file )['messages'];
	$known    = array_map( static fn( $k ) => explode( "\0", $k )[0], array_keys( $messages ) );

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir ) );
	foreach ( $files as $path ) {
		if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, 'languages' ) ) {
			continue;
		}
		$code  = file_get_contents( $path );
		$found = array();
		preg_match_all( "/{$functions}\(\s*{$string}\s*,\s*'{$domain}'/", $code, $m );
		$found = array_merge( $found, $m[1] );
		preg_match_all( "/_n\(\s*{$string}\s*,\s*{$string}\s*,[^,]+,\s*'{$domain}'/", $code, $m );
		$found = array_merge( $found, $m[1] );

		$checked += count( $found );
		foreach ( $found as $text ) {
			$text = stripslashes( $text );
			if ( ! in_array( $text, $known, true ) ) {
				echo "MISSING [{$domain}] {$text}  (" . basename( $path ) . ")\n";
				++$missing;
			}
		}
	}
}

echo $missing ? "{$missing} missing\n" : "All {$checked} strings translated.\n";
exit( $missing ? 1 : 0 );

<?php
/**
 * Packs the site files for upload: dist/nafha-site.zip (WordPress root, ready for htdocs/).
 * Usage: php pack.php <public dir> <zip path> <wp-config path>
 *
 * Left out: local config/logs, Wordfence and UpdraftPlus (see prepare-db.php), local
 * mail catcher, captured e-mails, update leftovers.
 *
 * @package Nafha
 */

[ , $root, $zip_path, $config ] = $argv + array( null, null, null, null );
$root = rtrim( str_replace( '\\', '/', realpath( $root ) ), '/' );

$skip = array(
	'#^wp-config\.php$#',
	'#^readme(\.[a-f0-9]+)?\.html$#',
	'#^wp-content/debug\.log$#',
	'#^wp-content/(wflogs|updraft|upgrade|upgrade-temp-backup)/#',
	'#^wp-content/plugins/(wordfence|updraftplus)/#',
	'#^wp-content/mu-plugins/nafha-local-mail\.php$#',
	'#^wp-content/uploads/(nafha-mail|wc-logs)/#',
	'#(^|/)\.git(/|$)#',
);

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Cannot create $zip_path\n" );
	exit( 1 );
}
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$count = 0;
foreach ( $files as $file ) {
	$rel = substr( str_replace( '\\', '/', $file->getPathname() ), strlen( $root ) + 1 );
	foreach ( $skip as $pattern ) {
		if ( preg_match( $pattern, $rel ) ) {
			continue 2;
		}
	}
	$zip->addFile( $file->getPathname(), $rel );
	++$count;
}
$zip->addFile( $config, 'wp-config.php' );
$zip->close();
printf( "%d files -> %s (%.1f MB)\n", $count + 1, $zip_path, filesize( $zip_path ) / 1048576 );

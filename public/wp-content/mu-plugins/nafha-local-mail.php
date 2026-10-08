<?php
/**
 * Plugin Name: Nafha local mail catcher
 * Description: LOCAL DEVELOPMENT ONLY. Instead of sending, saves every e-mail to wp-content/uploads/nafha-mail/ so order e-mails can be reviewed. Does nothing unless WP_ENVIRONMENT_TYPE is "local".
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() ) {
	return;
}

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		$dir = WP_CONTENT_DIR . '/uploads/nafha-mail';
		wp_mkdir_p( $dir );

		$to      = implode( ', ', (array) $atts['to'] );
		$subject = (string) $atts['subject'];
		$name    = gmdate( 'Ymd-His' ) . '-' . substr( md5( $to . $subject . microtime() ), 0, 6 ) . '.html';
		$meta    = '<!-- To: ' . esc_html( $to ) . ' | Subject: ' . esc_html( $subject ) . ' -->' . "\n";

		file_put_contents( $dir . '/' . $name, $meta . $atts['message'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return true; // Report success so WooCommerce logs the e-mail as sent.
	},
	10,
	2
);

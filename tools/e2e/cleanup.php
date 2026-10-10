<?php
/**
 * Delete orders created by the e2e suite (billing name "اختبار آلي") and
 * restore the demo stock. Run:  tools/wp.sh eval-file tools/e2e/cleanup.php
 */
$orders = wc_get_orders( array( 'limit' => -1, 'billing_first_name' => 'اختبار آلي', 'return' => 'ids' ) );
foreach ( $orders as $id ) {
	wc_get_order( $id )->delete( true );
}
WP_CLI::log( count( $orders ) . ' test orders deleted' );
WP_CLI::runcommand( 'eval-file ' . escapeshellarg( dirname( __DIR__ ) . '/setup/demo-products.php' ) ); // Resets demo stock levels.

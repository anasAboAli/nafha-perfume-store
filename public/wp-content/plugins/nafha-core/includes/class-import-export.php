<?php
/**
 * Product CSV import/export: adds a "Brands" column (WooCommerce's importer
 * does not handle the product_brand taxonomy) so the bulk-import sample in
 * docs/products-import-sample.csv round-trips with the exporter.
 *
 * Fragrance profile fields need no code: the core importer/exporter already
 * handles "Meta: _nafha_…" columns.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Import_Export {

	const FIELD = 'nafha_brands';

	public static function register() {
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'default_columns' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'import_brands' ), 10, 2 );

		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_column_' . self::FIELD, array( __CLASS__, 'export_brands' ), 10, 2 );
	}

	/**
	 * @param array $options Field key => label for the import mapping screen.
	 * @return array
	 */
	public static function mapping_options( $options ) {
		$options[ self::FIELD ] = __( 'Brands', 'nafha-core' );
		return $options;
	}

	/**
	 * Auto-map the column by its English or Arabic heading.
	 *
	 * @param array $columns Heading => field key.
	 * @return array
	 */
	public static function default_columns( $columns ) {
		$columns['Brands']                       = self::FIELD;
		$columns[ __( 'Brands', 'nafha-core' ) ] = self::FIELD;
		return $columns;
	}

	/**
	 * Comma-separated brand names; unknown brands are created.
	 *
	 * @param WC_Product $product Imported product.
	 * @param array      $data    Parsed row.
	 */
	public static function import_brands( $product, $data ) {
		if ( empty( $data[ self::FIELD ] ) || $product->is_type( 'variation' ) ) {
			return;
		}
		$ids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $data[ self::FIELD ] ) ) ) as $name ) {
			$term = get_term_by( 'name', $name, 'product_brand' );
			if ( ! $term ) {
				$created = wp_insert_term( $name, 'product_brand' );
				$term    = is_wp_error( $created ) ? null : get_term( $created['term_id'], 'product_brand' );
			}
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $product->get_id(), $ids, 'product_brand' );
	}

	/**
	 * @param array $columns Export columns.
	 * @return array
	 */
	public static function export_columns( $columns ) {
		$columns[ self::FIELD ] = __( 'Brands', 'nafha-core' );
		return $columns;
	}

	/**
	 * @param string     $value   Column value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function export_brands( $value, $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
	}
}

Nafha_Import_Export::register();

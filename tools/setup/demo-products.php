<?php
/**
 * Import/update the demo catalogue from demo-products.json. Run with:
 *   (cd tools && npm run images)   # once, to generate the images
 *   tools/wp.sh eval-file tools/setup/demo-products.php
 *
 * Idempotent: products are matched by slug, variations by size, images by
 * file name, reviews by the demo reviewer e-mail. Demo products are flagged
 * with _nafha_demo so they can be removed in one go before launch:
 *   tools/wp.sh post delete $(tools/wp.sh post list --post_type=product --meta_key=_nafha_demo --format=ids) --force
 *
 * @package Nafha
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$nafha_dir      = dirname( __DIR__ );
$nafha_products = json_decode( file_get_contents( $nafha_dir . '/setup/demo-products.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$nafha_images   = $nafha_dir . '/images/out/';

const NAFHA_DEMO_STOCK = 20;

/**
 * Import an image once; returns the attachment ID.
 */
function nafha_demo_image( $file, $title ) {
	$name     = basename( $file );
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_nafha_demo_image', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $name, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	if ( ! file_exists( $file ) ) {
		WP_CLI::warning( "Missing image {$name} — run `npm run images` in tools/ first." );
		return 0;
	}
	$tmp = wp_tempnam( $name );
	copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_nafha_demo_image', $name );
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	return (int) $id;
}

/**
 * Taxonomy attribute with the given term slugs.
 */
function nafha_demo_attribute( $slug, $terms, $position, $variation = false ) {
	$taxonomy = wc_attribute_taxonomy_name( $slug );
	$ids      = array();
	foreach ( (array) $terms as $term_slug ) {
		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( $term ) {
			$ids[] = $term->term_id;
		} else {
			WP_CLI::warning( "Unknown term {$taxonomy}:{$term_slug}" );
		}
	}
	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
	$attr->set_name( $taxonomy );
	$attr->set_options( $ids );
	$attr->set_position( $position );
	$attr->set_visible( true );
	$attr->set_variation( $variation );
	return $attr;
}

$nafha_reviews = array(
	array( 'سارة م.', 5, 'العطر أصلي ١٠٠٪ والتغليف أنيق جدًا. جربت ٥ مل وراح أطلب العبوة الكاملة.' ),
	array( 'محمد ع.', 5, 'التوصيل داخل الدوحة كان بنفس اليوم، والثبات ممتاز.' ),
	array( 'نورة ك.', 4, 'فكرة التقسيم رائعة، تقدر تجرب أكثر من عطر بسعر عطر واحد.' ),
);

foreach ( $nafha_products as $i => $data ) {
	$is_simple = ( $data['type'] ?? 'variable' ) === 'simple';
	$existing  = get_page_by_path( $data['slug'], OBJECT, 'product' );
	$product   = $existing ? wc_get_product( $existing->ID ) : ( $is_simple ? new WC_Product_Simple() : new WC_Product_Variable() );

	$product->set_name( $data['name'] );
	$product->set_slug( $data['slug'] );
	$product->set_status( 'publish' );
	$product->set_description( $data['description'] );
	$product->set_short_description( $data['short'] );
	$product->set_featured( ! empty( $data['featured'] ) );
	$product->set_reviews_allowed( true );
	$product->set_menu_order( $i );
	$product->set_sku( $data['slug'] ); // Parents too: tracking and feeds identify products by SKU.
	$product->set_category_ids(
		array_filter( array_map( static fn( $slug ) => get_term_by( 'slug', $slug, 'product_cat' )->term_id ?? 0, $data['categories'] ) )
	);

	$attributes = array(
		nafha_demo_attribute( 'fragrance-family', $data['family'], 1 ),
		nafha_demo_attribute( 'season', $data['season'], 2 ),
		nafha_demo_attribute( 'occasion', $data['occasion'], 3 ),
		nafha_demo_attribute( 'gender', $data['gender'], 4 ),
		nafha_demo_attribute( 'concentration', $data['concentration'], 5 ),
	);
	if ( ! $is_simple ) {
		array_unshift( $attributes, nafha_demo_attribute( 'size', array_keys( $data['prices'] ), 0, true ) );
	}
	$product->set_attributes( $attributes );

	// Fragrance profile (see nafha-core/includes/class-fragrance-profile.php).
	$product->update_meta_data( '_nafha_name_en', $data['name_en'] );
	$product->update_meta_data( '_nafha_notes_top', $data['notes']['top'] );
	$product->update_meta_data( '_nafha_notes_heart', $data['notes']['heart'] );
	$product->update_meta_data( '_nafha_notes_base', $data['notes']['base'] );
	$product->update_meta_data( '_nafha_longevity', $data['longevity'] );
	$product->update_meta_data( '_nafha_sillage', $data['sillage'] );
	$product->update_meta_data( '_nafha_demo', 1 );

	$title   = $data['name'] . ' — ' . $data['name_en'];
	$main    = nafha_demo_image( $nafha_images . $data['slug'] . '-1.webp', $title );
	$gallery = $is_simple ? array() : array_filter( array( nafha_demo_image( $nafha_images . $data['slug'] . '-2.webp', $title ) ) );
	$product->set_image_id( $main );
	$product->set_gallery_image_ids( $gallery );

	if ( $is_simple ) {
		$product->set_regular_price( (string) $data['price'] );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $data['stock_qty'] ?? NAFHA_DEMO_STOCK );
	}

	$product_id = $product->save();
	if ( ! empty( $data['brand'] ) ) { // Gift sets mix brands.
		wp_set_object_terms( $product_id, $data['brand'], 'product_brand' );
	}
	foreach ( array_merge( array( $main ), $gallery ) as $attachment_id ) {
		wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $product_id ) );
	}

	// Variations: one per size, matched by attribute so re-runs update prices/stock.
	if ( ! $is_simple ) {
		$by_size = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			$by_size[ $child->get_attributes()['pa_size'] ?? '' ] = $child;
		}
		$default = null;
		foreach ( $data['prices'] as $size => $price ) {
			$variation = $by_size[ $size ] ?? new WC_Product_Variation();
			$stock     = $data['stock'][ $size ] ?? NAFHA_DEMO_STOCK;
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( 'pa_size' => $size ) );
			$variation->set_regular_price( (string) $price );
			$variation->set_sku( $data['slug'] . '-' . $size );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $stock );
			$variation->set_status( 'publish' );
			if ( 'full-bottle' === $size ) {
				$variation->set_description( sprintf( 'العبوة الأصلية الكاملة %d مل.', $data['full_ml'] ) );
			}
			$variation->save();
			if ( ! $default && $stock > 0 ) {
				$default = $size;
			}
		}
		$product = wc_get_product( $product_id );
		$product->set_default_attributes( array( 'pa_size' => $default ) );
		$product->save();
		WC_Product_Variable::sync( $product_id );
	}

	// Plausible sales so "best sellers" ordering has something to sort by.
	// Gift sets get modest numbers so the perfumes lead "best sellers".
	$sales = ! empty( $data['giftset'] ) ? 18 - $i : ( ! empty( $data['featured'] ) ? 40 + $i * 3 : 5 + $i );
	// Through the product object so WooCommerce's lookup table (used for sorting) stays in sync.
	$product = wc_get_product( $product_id );
	$product->set_total_sales( $sales );
	$product->save();

	// A few demo reviews on featured products.
	if ( ! empty( $data['featured'] ) ) {
		$has = get_comments( array( 'post_id' => $product_id, 'author_email' => 'demo-review@nafha.test', 'count' => true ) );
		if ( ! $has ) {
			foreach ( $nafha_reviews as $r => $review ) {
				$comment_id = wp_insert_comment(
					array(
						'comment_post_ID'      => $product_id,
						'comment_author'       => $review[0],
						'comment_author_email' => 'demo-review@nafha.test',
						'comment_content'      => $review[2],
						'comment_type'         => 'review',
						'comment_approved'     => 1,
						'comment_date'         => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( 3 + $r * 5 ) . ' days' ) ),
					)
				);
				update_comment_meta( $comment_id, 'rating', $review[1] );
			}
			WC_Comments::clear_transients( $product_id );
		}
	}

	WP_CLI::log( sprintf( '  %s %s (#%d)', $existing ? 'updated' : 'created', $data['name_en'], $product_id ) );
}

// The lookup table (used for "best selling" sorting) is updated lazily; rebuild the column now.
wc_update_product_lookup_tables_column( 'total_sales' );
wc_delete_product_transients();
WP_CLI::success( count( $nafha_products ) . ' demo products ready.' );

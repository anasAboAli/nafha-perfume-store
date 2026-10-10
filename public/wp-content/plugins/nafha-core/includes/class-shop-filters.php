<?php
/**
 * Shop filters: an off-canvas panel (price, gender, family, season, size,
 * brand, occasion, concentration) with live counts, plus active-filter chips.
 *
 * Filtering itself is WooCommerce's: the panel only builds the standard
 * query vars (filter_pa_x + query_type_pa_x=or, filter_product_brand,
 * min_price/max_price), so URLs are shareable/indexable and the product
 * query uses WooCommerce's attribute lookup table. assets/js/shop.js swaps
 * the results in place (no reload).
 *
 * Counts: the current archive's products, their filter terms and prices are
 * loaded once (3 queries) and cached until any product changes; faceting
 * then happens in memory.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Shop_Filters {

	/**
	 * Price buckets [min, max] in store currency; null max = open-ended.
	 */
	const PRICE_BUCKETS = array( array( 0, 10 ), array( 10, 25 ), array( 25, 50 ), array( 50, 100 ), array( 100, null ) );

	/** Data for the current request, computed once. */
	private static $state = null;

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'render_list_heading' ), 5 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'render_toggle' ), 25 );
		add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'render_chips' ), 40 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'render_toggle' ), 5 );
		add_action( 'woocommerce_no_products_found', array( __CLASS__, 'render_chips' ), 6 );
		add_action( 'wp_footer', array( __CLASS__, 'render_panel' ) );

		add_filter( 'woocommerce_catalog_orderby', array( __CLASS__, 'sorting_options' ) );
	}

	public static function is_filterable_page() {
		return is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) );
	}

	/**
	 * Filter groups, in display order. Attribute groups use the pa_* taxonomy.
	 *
	 * @return array<string, array{label: string, open: bool}>
	 */
	private static function groups() {
		return array(
			'price'               => array( 'label' => __( 'Price', 'nafha-core' ), 'open' => true ),
			'pa_gender'           => array( 'label' => __( 'Gender', 'nafha-core' ), 'open' => true ),
			'pa_fragrance-family' => array( 'label' => __( 'Fragrance family', 'nafha-core' ), 'open' => true ),
			'pa_season'           => array( 'label' => __( 'Season', 'nafha-core' ), 'open' => false ),
			'pa_size'             => array( 'label' => __( 'Size', 'nafha-core' ), 'open' => false ),
			'product_brand'       => array( 'label' => __( 'Brand', 'nafha-core' ), 'open' => false ),
			'pa_occasion'         => array( 'label' => __( 'Occasion', 'nafha-core' ), 'open' => false ),
			'pa_concentration'    => array( 'label' => __( 'Concentration', 'nafha-core' ), 'open' => false ),
		);
	}

	public static function sorting_options() {
		return array(
			'popularity' => __( 'Best selling', 'nafha-core' ),
			'date'       => __( 'Newest', 'nafha-core' ),
			'price'      => __( 'Price: low to high', 'nafha-core' ),
			'price-desc' => __( 'Price: high to low', 'nafha-core' ),
		);
	}

	public static function enqueue() {
		if ( ! self::is_filterable_page() ) {
			return;
		}
		$css = 'assets/css/shop.css';
		$js  = 'assets/js/shop.js';
		wp_enqueue_style( 'nafha-shop', NAFHA_CORE_URL . $css, array(), (string) filemtime( NAFHA_CORE_DIR . $css ) );
		wp_enqueue_script( 'nafha-shop', NAFHA_CORE_URL . $js, array( 'nafha-drawer' ), (string) filemtime( NAFHA_CORE_DIR . $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/* ------------------------------------------------------------- data */

	/**
	 * Base URL of the current archive, without pagination or filters.
	 */
	private static function base_url() {
		if ( is_search() ) {
			return add_query_arg( array( 's' => get_search_query( false ), 'post_type' => 'product' ), home_url( '/' ) );
		}
		if ( is_product_taxonomy() ) {
			return get_term_link( get_queried_object() );
		}
		return wc_get_page_permalink( 'shop' );
	}

	/**
	 * Products of the current archive with their filter terms and prices, cached.
	 *
	 * @return array{ids: int[], terms: array<int, array<string, int[]>>, prices: array<int, float[]>}
	 */
	private static function catalog() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => is_search() ? array( 'exclude-from-search' ) : array( 'exclude-from-catalog' ),
					'operator' => 'NOT IN',
				),
			),
		);
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'outofstock' ),
				'operator' => 'NOT IN',
			);
		}
		if ( is_product_taxonomy() ) {
			$term                = get_queried_object();
			$args['tax_query'][] = array(
				'taxonomy' => $term->taxonomy,
				'terms'    => array( $term->term_id ),
			);
		}
		if ( is_search() ) {
			$args['s'] = get_search_query( false );
		}

		$key    = 'nafha_facets_' . md5( wp_json_encode( $args ) ) . '_' . WC_Cache_Helper::get_transient_version( 'product' );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$ids  = array_map( 'intval', get_posts( $args ) );
		$data = array(
			'ids'    => $ids,
			'terms'  => array(),
			'prices' => array(),
		);

		if ( $ids ) {
			$taxonomies = array_filter( array_keys( self::groups() ), 'taxonomy_exists' );
			foreach ( wp_get_object_terms( $ids, $taxonomies, array( 'fields' => 'all_with_object_id' ) ) as $term ) {
				$data['terms'][ (int) $term->object_id ][ $term->taxonomy ][] = (int) $term->term_id;
			}

			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, min_price, max_price FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ($placeholders)", $ids ) );
			foreach ( $rows as $row ) {
				$data['prices'][ (int) $row->product_id ] = array( (float) $row->min_price, (float) $row->max_price );
			}
		}

		set_transient( $key, $data, DAY_IN_SECONDS );
		return $data;
	}

	/**
	 * Selected values from the request, as term IDs per group (+ price bucket index).
	 *
	 * @return array<string, int[]>
	 */
	private static function chosen() {
		$chosen = array();
		foreach ( WC_Query::get_layered_nav_chosen_attributes() as $taxonomy => $data ) {
			foreach ( $data['terms'] as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term ) {
					$chosen[ $taxonomy ][] = (int) $term->term_id;
				}
			}
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only catalogue filters.
		if ( ! empty( $_GET['filter_product_brand'] ) ) {
			$chosen['product_brand'] = array_filter( array_map( 'absint', explode( ',', wc_clean( wp_unslash( $_GET['filter_product_brand'] ) ) ) ) );
		}
		if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) {
			$min = isset( $_GET['min_price'] ) ? (float) wc_clean( wp_unslash( $_GET['min_price'] ) ) : 0;
			$max = isset( $_GET['max_price'] ) ? (float) wc_clean( wp_unslash( $_GET['max_price'] ) ) : null;
			foreach ( self::PRICE_BUCKETS as $index => $bucket ) {
				if ( (float) $bucket[0] === $min && $bucket[1] === ( null === $max ? null : (int) $max ) ) {
					$chosen['price'] = array( $index );
				}
			}
		}
		// phpcs:enable
		return $chosen;
	}

	private static function in_bucket( $prices, $bucket ) {
		if ( ! $prices ) {
			return false;
		}
		// Same overlap rule as WooCommerce's price filter: any variation price inside the range.
		return $prices[0] <= ( $bucket[1] ?? PHP_FLOAT_MAX ) && $prices[1] >= $bucket[0];
	}

	private static function matches_group( $catalog, $id, $group, $values ) {
		if ( 'price' === $group ) {
			return self::in_bucket( $catalog['prices'][ $id ] ?? null, self::PRICE_BUCKETS[ $values[0] ] );
		}
		return (bool) array_intersect( $values, $catalog['terms'][ $id ][ $group ] ?? array() ); // OR within a group.
	}

	/**
	 * Products matching every chosen group except $skip (for that group's counts).
	 *
	 * @return int[]
	 */
	private static function matching( $catalog, $chosen, $skip = null ) {
		return array_values(
			array_filter(
				$catalog['ids'],
				static function ( $id ) use ( $catalog, $chosen, $skip ) {
					foreach ( $chosen as $group => $values ) {
						if ( $group !== $skip && ! self::matches_group( $catalog, $id, $group, $values ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Options with counts for every group, the chosen values and the total.
	 */
	private static function state() {
		if ( null !== self::$state ) {
			return self::$state;
		}

		$catalog = self::catalog();
		$chosen  = self::chosen();
		$groups  = array();

		foreach ( self::groups() as $group => $meta ) {
			$pool    = self::matching( $catalog, $chosen, $group );
			$options = array();

			if ( 'price' === $group ) {
				// Radios cannot be unticked, so "any price" is an explicit choice.
				$options[] = array(
					'value'   => '',
					'label'   => __( 'Any price', 'nafha-core' ),
					'count'   => count( $pool ),
					'checked' => empty( $chosen['price'] ),
				);
				foreach ( self::PRICE_BUCKETS as $index => $bucket ) {
					$count = count( array_filter( $pool, static fn( $id ) => self::in_bucket( $catalog['prices'][ $id ] ?? null, $bucket ) ) );
					$options[] = array(
						'value'   => $index,
						'label'   => self::bucket_label( $bucket ),
						'count'   => $count,
						'checked' => in_array( $index, $chosen['price'] ?? array(), true ),
					);
				}
			} elseif ( taxonomy_exists( $group ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $group,
						'hide_empty' => true,
						'orderby'    => 'product_brand' === $group ? 'name' : 'menu_order',
					)
				);
				foreach ( $terms as $term ) {
					$count = count( array_filter( $pool, static fn( $id ) => in_array( $term->term_id, $catalog['terms'][ $id ][ $group ] ?? array(), true ) ) );
					$checked = in_array( $term->term_id, $chosen[ $group ] ?? array(), true );
					if ( ! $count && ! $checked ) {
						continue; // Hide options that cannot match anything in this archive.
					}
					$options[] = array(
						'value'   => 'product_brand' === $group ? $term->term_id : $term->slug,
						'label'   => $term->name,
						'count'   => $count,
						'checked' => $checked,
					);
				}
			}

			// Skip groups that cannot narrow anything: the archive is already scoped by it (brand page), or < 2 options.
			$usable = count( array_filter( $options, static fn( $o ) => $o['count'] || $o['checked'] ) );
			if ( is_tax( $group ) || ( $usable < 2 && empty( $chosen[ $group ] ) ) ) {
				continue;
			}
			$groups[ $group ] = $meta + array( 'options' => $options );
		}

		self::$state = array(
			'groups' => $groups,
			'chosen' => $chosen,
			'total'  => count( self::matching( $catalog, $chosen ) ),
			'active' => array_sum( array_map( 'count', $chosen ) ),
		);
		return self::$state;
	}

	private static function bucket_label( $bucket ) {
		$fmt = static fn( $n ) => wp_strip_all_tags( wc_price( $n, array( 'decimals' => 0 ) ) );
		if ( 0 === $bucket[0] ) {
			/* translators: %s: price. */
			return sprintf( __( 'Under %s', 'nafha-core' ), $fmt( $bucket[1] ) );
		}
		if ( null === $bucket[1] ) {
			/* translators: %s: price. */
			return sprintf( __( 'Over %s', 'nafha-core' ), $fmt( $bucket[0] ) );
		}
		/* translators: 1: lower price, 2: upper price. */
		return sprintf( __( '%1$s to %2$s', 'nafha-core' ), $fmt( $bucket[0] ), $fmt( $bucket[1] ) );
	}

	/**
	 * URL of the current results with one value removed (or all filters when $group is null).
	 */
	private static function url_without( $group = null, $value = null ) {
		$chosen = $group ? self::state()['chosen'] : array();
		if ( $group ) {
			$chosen[ $group ] = array_values( array_diff( $chosen[ $group ], array( $value ) ) );
		}
		return self::url_for( $chosen );
	}

	/**
	 * Build the WooCommerce filter URL for a set of chosen values.
	 */
	private static function url_for( $chosen ) {
		$args = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserve sorting only.
		if ( ! empty( $_GET['orderby'] ) ) {
			$args['orderby'] = wc_clean( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		foreach ( $chosen as $group => $values ) {
			if ( ! $values ) {
				continue;
			}
			if ( 'price' === $group ) {
				$bucket            = self::PRICE_BUCKETS[ $values[0] ];
				$args['min_price'] = $bucket[0];
				if ( null !== $bucket[1] ) {
					$args['max_price'] = $bucket[1];
				}
			} elseif ( 'product_brand' === $group ) {
				$args['filter_product_brand'] = implode( ',', $values );
			} else {
				$slugs = array_map( static fn( $id ) => get_term( $id, $group )->slug, $values );
				$name  = wc_attribute_taxonomy_slug( $group );
				$args[ 'filter_' . $name ]     = implode( ',', $slugs );
				$args[ 'query_type_' . $name ] = 'or';
			}
		}
		return add_query_arg( $args, self::base_url() );
	}

	/* ----------------------------------------------------------- render */

	/**
	 * Screen-reader heading between the page title (h1) and the product cards (h3).
	 */
	public static function render_list_heading() {
		printf( '<h2 class="screen-reader-text">%s</h2>', esc_html__( 'Products', 'nafha-core' ) );
	}

	public static function render_toggle() {
		if ( ! self::is_filterable_page() || ! self::state()['groups'] ) {
			return;
		}
		$active = self::state()['active'];
		?>
		<button type="button" class="nafha-filter-toggle" aria-controls="nafha-filters" aria-expanded="false">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
			<span><?php esc_html_e( 'Filter', 'nafha-core' ); ?></span>
			<?php if ( $active ) : ?>
				<span class="nafha-filter-toggle__count"><?php echo esc_html( number_format_i18n( $active ) ); ?></span>
			<?php endif; ?>
		</button>
		<?php
	}

	public static function render_chips() {
		if ( ! self::is_filterable_page() ) {
			return;
		}
		$state = self::state();
		if ( ! $state['active'] ) {
			return;
		}
		?>
		<div class="nafha-chips-bar" aria-label="<?php esc_attr_e( 'Active filters', 'nafha-core' ); ?>">
			<?php foreach ( $state['chosen'] as $group => $values ) : ?>
				<?php
				foreach ( $values as $value ) :
					if ( 'price' === $group ) {
						$label = self::bucket_label( self::PRICE_BUCKETS[ $value ] );
					} else {
						$term  = get_term( $value, $group );
						$label = $term && ! is_wp_error( $term ) ? $term->name : '';
					}
					?>
					<a class="nafha-chip" href="<?php echo esc_url( self::url_without( $group, $value ) ); ?>" data-nafha-nav>
						<?php echo esc_html( $label ); ?>
						<span aria-hidden="true">×</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove filter', 'nafha-core' ); ?></span>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<a class="nafha-chip nafha-chip--clear" href="<?php echo esc_url( self::url_without() ); ?>" data-nafha-nav><?php esc_html_e( 'Clear all', 'nafha-core' ); ?></a>
		</div>
		<?php
	}

	public static function render_panel() {
		if ( ! self::is_filterable_page() ) {
			return;
		}
		$state = self::state();
		if ( ! $state['groups'] ) {
			return;
		}
		?>
		<div class="nafha-drawer" id="nafha-filters" role="dialog" aria-modal="true" aria-labelledby="nafha-filters-title" hidden>
			<div class="nafha-drawer__overlay" data-nafha-close></div>
			<form class="nafha-drawer__panel" action="<?php echo esc_url( self::base_url() ); ?>" method="get" data-base-url="<?php echo esc_url( self::base_url() ); ?>">
				<header class="nafha-drawer__header">
					<h2 id="nafha-filters-title"><?php esc_html_e( 'Filter', 'nafha-core' ); ?></h2>
					<button type="button" class="nafha-drawer__close" data-nafha-close aria-label="<?php esc_attr_e( 'Close', 'nafha-core' ); ?>">×</button>
				</header>

				<div class="nafha-drawer__body">
					<?php foreach ( $state['groups'] as $group => $meta ) : ?>
						<?php $type = 'price' === $group ? 'radio' : 'checkbox'; ?>
						<details class="nafha-facet" data-group="<?php echo esc_attr( $group ); ?>"<?php echo $meta['open'] || ! empty( $state['chosen'][ $group ] ) ? ' open' : ''; ?>>
							<summary><?php echo esc_html( $meta['label'] ); ?></summary>
							<ul>
								<?php foreach ( $meta['options'] as $option ) : ?>
									<li>
										<label class="<?php echo ! $option['count'] && ! $option['checked'] ? 'is-empty' : ''; ?>">
											<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $group ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>"<?php checked( $option['checked'] ); ?><?php disabled( ! $option['count'] && ! $option['checked'] ); ?>
												<?php if ( 'price' === $group && '' !== $option['value'] ) : ?>
													data-min="<?php echo esc_attr( self::PRICE_BUCKETS[ $option['value'] ][0] ); ?>" data-max="<?php echo esc_attr( (string) self::PRICE_BUCKETS[ $option['value'] ][1] ); ?>"
												<?php endif; ?>
											>
											<span class="nafha-facet__label"><?php echo esc_html( $option['label'] ); ?></span>
											<span class="nafha-facet__count"><?php echo esc_html( number_format_i18n( $option['count'] ) ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endforeach; ?>
				</div>

				<footer class="nafha-drawer__footer">
					<a class="nafha-drawer__clear" href="<?php echo esc_url( self::url_without() ); ?>" data-nafha-nav><?php esc_html_e( 'Clear all', 'nafha-core' ); ?></a>
					<button type="submit" class="button nafha-drawer__apply" data-nafha-close>
						<?php
						/* translators: %s: number of matching products. */
						echo esc_html( sprintf( _n( 'Show %s product', 'Show %s products', $state['total'], 'nafha-core' ), number_format_i18n( $state['total'] ) ) );
						?>
					</button>
				</footer>
			</form>
		</div>
		<?php
	}
}

Nafha_Shop_Filters::register();

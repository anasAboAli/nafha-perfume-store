<?php
/**
 * Offers: the build-your-own discovery box and the sample quantity discount.
 *
 * Discovery box — shortcode [nafha_discovery_box]. The customer picks a box
 * (3 or 5 perfumes) and a size (5 or 10 ml), then the perfumes. Each perfume
 * goes into the cart as its normal variation (so stock, the side cart and
 * order lines all work as usual) tagged with a box ID; while the box is
 * complete its items are discounted, and removing one item removes the box.
 *
 * Quantity discount — 5/10 ml samples bought outside a box get a percentage
 * off from a quantity threshold, shown as a negative fee with a nudge in the
 * cart ("add 1 more sample for 10% off").
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Offers {

	const SHORTCODE = 'nafha_discovery_box';
	const CART_KEY  = 'nafha_box';

	/** Box size (number of perfumes) => discount %. */
	const BOXES = array( 3 => 10, 5 => 20 );

	/** Sizes a box can be built from. */
	const BOX_SIZES = array( '5ml', '10ml' );

	/** Samples (outside boxes) in the cart => discount %. */
	const QUANTITY_TIERS = array( 3 => 10, 5 => 15 );

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_builder' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_nafha_add_box', array( __CLASS__, 'ajax_add_box' ) );
		add_action( 'wp_ajax_nopriv_nafha_add_box', array( __CLASS__, 'ajax_add_box' ) );

		// Box pricing must hold both when totals are calculated and when the cart is just restored from the session.
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_box_prices' ), 20 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'apply_box_prices' ), 20 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'fixed_box_quantity' ), 10, 3 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'remove_whole_box' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'box_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'box_order_meta' ), 10, 3 );

		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'quantity_discount' ) );
		add_action( 'woocommerce_before_mini_cart', array( __CLASS__, 'render_quantity_hint' ), 15 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_quantity_hint' ), 15 );
	}

	public static function enqueue() {
		if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), self::SHORTCODE ) ) {
			return;
		}
		$css = 'assets/css/box.css';
		$js  = 'assets/js/box.js';
		wp_enqueue_style( 'nafha-box', NAFHA_CORE_URL . $css, array(), (string) filemtime( NAFHA_CORE_DIR . $css ) );
		wp_enqueue_script( 'nafha-box', NAFHA_CORE_URL . $js, array( 'jquery' ), (string) filemtime( NAFHA_CORE_DIR . $js ), array( 'in_footer' => true ) );
		wp_localize_script(
			'nafha-box',
			'nafhaBox',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'nafha_box' ),
				'boxes'    => self::BOXES,
				'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'i18n'     => array(
					/* translators: 1: perfumes chosen, 2: box size. */
					'chosen' => __( '%1$d of %2$d chosen', 'nafha-core' ),
					'error'  => __( 'Something went wrong. Please try again.', 'nafha-core' ),
				),
			)
		);
	}

	/* ------------------------------------------------------- box helpers */

	/**
	 * Perfumes that can go in a box: per product, the in-stock variation for each box size.
	 *
	 * @return array<int, array{name: string, brand: string, image: string, sizes: array<string, array{id: int, price: float}>}>
	 */
	private static function box_catalog() {
		$catalog = array();
		$ids     = wc_get_products(
			array(
				'status'       => 'publish',
				'type'         => 'variable',
				'visibility'   => 'catalog',
				'stock_status' => 'instock',
				'limit'        => -1,
				'orderby'      => 'popularity',
				'return'       => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			$sizes   = array();
			foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
				$size = $variation->get_attributes()['pa_size'] ?? '';
				if ( in_array( $size, self::BOX_SIZES, true ) && $variation->is_in_stock() && $variation->is_purchasable() ) {
					$sizes[ $size ] = array(
						'id'    => $variation->get_id(),
						'price' => (float) wc_get_price_to_display( $variation ),
					);
				}
			}
			if ( ! $sizes ) {
				continue;
			}
			$brands         = get_the_terms( $id, 'product_brand' );
			$catalog[ $id ] = array(
				'name'  => $product->get_name(),
				'brand' => $brands && ! is_wp_error( $brands ) ? $brands[0]->name : '',
				'image' => $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ),
				'sizes' => $sizes,
			);
		}
		return $catalog;
	}

	private static function size_label( $slug ) {
		$term = get_term_by( 'slug', $slug, 'pa_size' );
		return $term ? $term->name : $slug;
	}

	/* ----------------------------------------------------- box builder */

	public static function render_builder() {
		$catalog = self::box_catalog();
		if ( ! $catalog ) {
			return '';
		}
		ob_start();
		?>
		<div class="nafha-box" data-box="3" data-size="5ml">
			<div class="nafha-box__setup">
				<fieldset>
					<legend><?php esc_html_e( '1. Choose your box', 'nafha-core' ); ?></legend>
					<div class="nafha-box__choices">
						<?php foreach ( self::BOXES as $count => $discount ) : ?>
							<label class="nafha-box__choice">
								<input type="radio" name="nafha_box_count" value="<?php echo esc_attr( $count ); ?>"<?php checked( 3, $count ); ?>>
								<span>
									<strong>
										<?php
										/* translators: %d: number of perfumes in the box. */
										echo esc_html( sprintf( _n( '%d perfume', '%d perfumes', $count, 'nafha-core' ), $count ) );
										?>
									</strong>
									<small>
										<?php
										/* translators: %d: discount percentage. */
										echo esc_html( sprintf( __( 'Save %d%%', 'nafha-core' ), $discount ) );
										?>
									</small>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<fieldset>
					<legend><?php esc_html_e( '2. Choose the size', 'nafha-core' ); ?></legend>
					<div class="nafha-box__choices">
						<?php foreach ( self::BOX_SIZES as $i => $size ) : ?>
							<label class="nafha-box__choice">
								<input type="radio" name="nafha_box_size" value="<?php echo esc_attr( $size ); ?>"<?php checked( 0, $i ); ?>>
								<span><strong><?php echo esc_html( self::size_label( $size ) ); ?></strong></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</div>

			<h3 class="nafha-box__heading"><?php esc_html_e( '3. Pick your perfumes', 'nafha-core' ); ?></h3>
			<ul class="nafha-box__grid">
				<?php foreach ( $catalog as $id => $item ) : ?>
					<li>
						<button type="button" class="nafha-box__tile" aria-pressed="false" data-product="<?php echo esc_attr( $id ); ?>" data-sizes="<?php echo esc_attr( wp_json_encode( $item['sizes'] ) ); ?>">
							<?php echo wp_kses_post( $item['image'] ); ?>
							<span class="nafha-box__brand"><?php echo esc_html( $item['brand'] ); ?></span>
							<span class="nafha-box__name"><?php echo esc_html( $item['name'] ); ?></span>
							<span class="nafha-box__price"></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="nafha-box__summary" role="status" aria-live="polite">
				<div class="nafha-box__totals">
					<span class="nafha-box__count"></span>
					<span class="nafha-box__amount"><del></del> <strong></strong></span>
				</div>
				<button type="button" class="button nafha-box__add" disabled><?php esc_html_e( 'Add box to cart', 'nafha-core' ); ?></button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: validate the selection and add the box's items to the cart.
	 */
	public static function ajax_add_box() {
		check_ajax_referer( 'nafha_box', 'nonce' );

		$count = absint( $_POST['count'] ?? 0 );
		$size  = sanitize_key( wp_unslash( $_POST['size'] ?? '' ) );
		$items = array_values( array_unique( array_map( 'absint', (array) wp_unslash( $_POST['items'] ?? array() ) ) ) );

		if ( ! isset( self::BOXES[ $count ] ) || ! in_array( $size, self::BOX_SIZES, true ) || count( $items ) !== $count ) {
			wp_send_json_error( array( 'message' => __( 'Please choose exactly the number of perfumes for your box.', 'nafha-core' ) ), 400 );
		}

		// Every item must be an in-stock variation of the chosen size, each from a different perfume.
		$parents = array();
		foreach ( $items as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if (
				! $variation instanceof WC_Product_Variation
				|| ( $variation->get_attributes()['pa_size'] ?? '' ) !== $size
				|| ! $variation->is_in_stock()
				|| ! $variation->is_purchasable()
				|| isset( $parents[ $variation->get_parent_id() ] )
			) {
				wp_send_json_error( array( 'message' => __( 'One of the perfumes is no longer available in this size. Please refresh and try again.', 'nafha-core' ) ), 400 );
			}
			$parents[ $variation->get_parent_id() ] = $variation;
		}

		$box = array(
			'id'       => strtoupper( wp_generate_password( 6, false ) ),
			'count'    => $count,
			'size'     => $size,
			'discount' => self::BOXES[ $count ],
		);
		foreach ( $parents as $parent_id => $variation ) {
			$added = WC()->cart->add_to_cart( $parent_id, 1, $variation->get_id(), $variation->get_variation_attributes(), array( self::CART_KEY => $box ) );
			if ( ! $added ) {
				// Roll back the part of the box already added.
				self::remove_box( $box['id'] );
				wp_send_json_error( array( 'message' => __( 'One of the perfumes is no longer available in this size. Please refresh and try again.', 'nafha-core' ) ), 400 );
			}
		}

		WC_AJAX::get_refreshed_fragments(); // Sends the side cart fragments and exits.
	}

	/* ------------------------------------------------------- box in cart */

	/**
	 * Cart item keys per box ID.
	 *
	 * @return array<string, string[]>
	 */
	private static function boxes_in( $cart ) {
		$boxes = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item[ self::CART_KEY ]['id'] ) ) {
				$boxes[ $item[ self::CART_KEY ]['id'] ][] = $key;
			}
		}
		return $boxes;
	}

	private static function remove_box( $box_id ) {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ( $item[ self::CART_KEY ]['id'] ?? null ) === $box_id ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
	}

	/**
	 * Discount box items while their box is complete (prices recomputed from the product each time).
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function apply_box_prices( $cart ) {
		foreach ( self::boxes_in( $cart ) as $keys ) {
			foreach ( $keys as $key ) {
				$item     = $cart->cart_contents[ $key ];
				$box      = $item[ self::CART_KEY ];
				$base     = (float) wc_get_product( $item['variation_id'] )->get_price( 'edit' );
				$complete = count( $keys ) === (int) $box['count'];
				$item['data']->set_price( $complete ? round( $base * ( 100 - (int) $box['discount'] ) / 100, wc_get_price_decimals() ) : $base );
			}
		}
	}

	/**
	 * Box items are one of each; quantity is not editable.
	 */
	public static function fixed_box_quantity( $html, $key, $item ) {
		return isset( $item[ self::CART_KEY ] ) ? '<span class="nafha-box-qty">1</span>' : $html;
	}

	/**
	 * Removing one perfume of a box removes the rest of that box.
	 *
	 * @param string  $key  Removed cart item key.
	 * @param WC_Cart $cart Cart.
	 */
	public static function remove_whole_box( $key, $cart ) {
		$box_id = $cart->removed_cart_contents[ $key ][ self::CART_KEY ]['id'] ?? null;
		if ( $box_id ) {
			self::remove_box( $box_id );
		}
	}

	/**
	 * "Discovery box (3 perfumes) · 10% off" under each box item in cart / mini cart / checkout.
	 */
	public static function box_item_data( $data, $item ) {
		if ( isset( $item[ self::CART_KEY ] ) ) {
			$box    = $item[ self::CART_KEY ];
			$data[] = array(
				'key'   => __( 'Discovery box', 'nafha-core' ),
				/* translators: 1: perfumes in the box, 2: discount percentage. */
				'value' => sprintf( _n( '%1$d perfume · %2$d%% off', '%1$d perfumes · %2$d%% off', (int) $box['count'], 'nafha-core' ), (int) $box['count'], (int) $box['discount'] ),
			);
		}
		return $data;
	}

	/**
	 * Keep the box reference on the order so it can be packed together.
	 */
	public static function box_order_meta( $order_item, $key, $values ) {
		if ( isset( $values[ self::CART_KEY ] ) ) {
			$box = $values[ self::CART_KEY ];
			$order_item->add_meta_data(
				__( 'Discovery box', 'nafha-core' ),
				/* translators: 1: box ID, 2: perfumes in the box. */
				sprintf( __( '#%1$s (%2$d perfumes)', 'nafha-core' ), $box['id'], (int) $box['count'] )
			);
		}
	}

	/* -------------------------------------------------- quantity offer */

	/**
	 * Samples (5/10 ml, not in a box) in the cart: [quantity, subtotal].
	 */
	private static function samples_in_cart() {
		$quantity = 0;
		$subtotal = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item[ self::CART_KEY ] ) || empty( $item['variation_id'] ) ) {
				continue;
			}
			if ( in_array( $item['variation']['attribute_pa_size'] ?? '', self::BOX_SIZES, true ) ) {
				$quantity += (int) $item['quantity'];
				$subtotal += (float) $item['line_subtotal'];
			}
		}
		return array( $quantity, $subtotal );
	}

	/**
	 * Discount % for a sample quantity (0 below the first tier).
	 */
	private static function tier_for( $quantity ) {
		$pct = 0;
		foreach ( self::QUANTITY_TIERS as $min => $discount ) {
			if ( $quantity >= $min ) {
				$pct = $discount;
			}
		}
		return $pct;
	}

	/**
	 * @param WC_Cart $cart Cart.
	 */
	public static function quantity_discount( $cart ) {
		list( $quantity, $subtotal ) = self::samples_in_cart();
		$pct                         = self::tier_for( $quantity );
		if ( $pct && $subtotal > 0 ) {
			$cart->add_fee(
				/* translators: %d: discount percentage. */
				sprintf( __( 'Sample discount (%d%%)', 'nafha-core' ), $pct ),
				-round( $subtotal * $pct / 100, wc_get_price_decimals() ),
				false
			);
		}
	}

	public static function render_quantity_hint() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		list( $quantity ) = self::samples_in_cart();
		if ( ! $quantity ) {
			return;
		}
		$current = self::tier_for( $quantity );
		foreach ( self::QUANTITY_TIERS as $min => $pct ) {
			if ( $quantity < $min ) {
				$message = sprintf(
					/* translators: 1: samples still needed, 2: discount percentage. */
					_n( 'Add %1$d more sample and get %2$d%% off your samples', 'Add %1$d more samples and get %2$d%% off your samples', $min - $quantity, 'nafha-core' ),
					$min - $quantity,
					$pct
				);
				break;
			}
		}
		if ( empty( $message ) ) {
			/* translators: %d: discount percentage. */
			$message = sprintf( __( '%d%% sample discount applied', 'nafha-core' ), $current );
		}
		printf( '<p class="nafha-offer-hint%s">%s</p>', $current ? ' is-active' : '', esc_html( $message ) );
	}
}

Nafha_Offers::register();

<?php
/**
 * Variation swatches: renders each variation attribute (size) as a row of
 * buttons showing that option's price. The native <select> stays in the DOM,
 * visually hidden, so WooCommerce's variation script keeps doing the work;
 * assets/js/product.js only mirrors clicks into it.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Variation_Buttons {

	public static function register() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_args', array( __CLASS__, 'select_args' ) );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'buttons' ), 10, 2 );
	}

	/**
	 * Pre-select the first in-stock option so a price shows immediately.
	 *
	 * @param array $args Dropdown args.
	 * @return array
	 */
	public static function select_args( $args ) {
		$args['class'] = trim( ( $args['class'] ?? '' ) . ' nafha-swatch-select' );

		if ( empty( $args['selected'] ) && $args['product'] instanceof WC_Product_Variable ) {
			// WooCommerce fills "selected" after this filter, from the URL (?attribute_pa_size=10ml,
			// used by the product feed and ads) or the product's default size. Only fall back to
			// the first in-stock size when neither exists.
			$key = 'attribute_' . sanitize_title( $args['attribute'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselection.
			if ( ! empty( $_REQUEST[ $key ] ) || $args['product']->get_variation_default_attribute( $args['attribute'] ) ) {
				return $args;
			}
			$options = self::option_data( $args['product'], $args['attribute'] );
			foreach ( $args['options'] as $option ) {
				if ( ! empty( $options[ $option ]['in_stock'] ) ) {
					$args['selected'] = $option;
					break;
				}
			}
		}
		return $args;
	}

	/**
	 * Price and stock per attribute option, from the product's variations.
	 *
	 * @param WC_Product_Variable $product   Variable product.
	 * @param string              $attribute Attribute taxonomy or name.
	 * @return array<string, array{price: string, in_stock: bool}>
	 */
	private static function option_data( $product, $attribute ) {
		static $cache = array();
		$cache_key    = $product->get_id() . '|' . $attribute;
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$data = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->variation_is_visible() ) {
				continue;
			}
			$value = $variation->get_attributes()[ sanitize_title( $attribute ) ] ?? '';
			if ( '' === $value ) {
				continue; // "Any …" variation: no single price for this option.
			}
			$data[ $value ] = array(
				'price'    => wc_price( wc_get_price_to_display( $variation ) ),
				'in_stock' => $variation->is_in_stock(),
			);
		}
		return $cache[ $cache_key ] = $data;
	}

	/**
	 * @param string $html Native select markup.
	 * @param array  $args Dropdown args (attribute, options, product, selected).
	 * @return string
	 */
	public static function buttons( $html, $args ) {
		$product = $args['product'];
		if ( ! $product instanceof WC_Product_Variable || empty( $args['options'] ) ) {
			return $html;
		}

		$attribute = $args['attribute'];
		$data      = self::option_data( $product, $attribute );
		// WooCommerce passes an empty name by default, meaning "attribute_{slug}".
		$name      = ! empty( $args['name'] ) ? $args['name'] : 'attribute_' . sanitize_title( $attribute );

		// Keep the order configured in the attribute (5 ml → full bottle), not the order variations were created.
		$labels = array();
		if ( taxonomy_exists( $attribute ) ) {
			foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
				if ( in_array( $term->slug, $args['options'], true ) ) {
					$labels[ $term->slug ] = $term->name;
				}
			}
		} else {
			$labels = array_combine( $args['options'], $args['options'] );
		}

		ob_start();
		?>
		<div class="nafha-swatches" role="radiogroup" aria-label="<?php echo esc_attr( wc_attribute_label( $attribute, $product ) ); ?>" data-attribute="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $labels as $value => $label ) : ?>
				<?php
				$option   = $data[ $value ] ?? null;
				$selected = (string) $args['selected'] === (string) $value;
				$classes  = 'nafha-swatch' . ( $option && ! $option['in_stock'] ? ' is-out' : '' );
				?>
				<button type="button" class="<?php echo esc_attr( $classes ); ?>" role="radio" aria-checked="<?php echo $selected ? 'true' : 'false'; ?>" data-value="<?php echo esc_attr( $value ); ?>">
					<span class="nafha-swatch__name"><?php echo esc_html( $label ); ?></span>
					<?php if ( $option ) : ?>
						<span class="nafha-swatch__price"><?php echo wp_kses_post( $option['price'] ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
		return $html . ob_get_clean();
	}
}

Nafha_Variation_Buttons::register();

<?php
/**
 * Fragrance profile: notes pyramid, longevity/sillage ratings, English name,
 * video — edited in a "Fragrance profile" tab of the product data box and
 * rendered in the single-product summary.
 *
 * Season, occasion and family are product attributes (filterable in the shop),
 * so they are read from there rather than duplicated as meta.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Fragrance_Profile {

	const META = array(
		'name_en'     => '_nafha_name_en',
		'notes_top'   => '_nafha_notes_top',
		'notes_heart' => '_nafha_notes_heart',
		'notes_base'  => '_nafha_notes_base',
		'longevity'   => '_nafha_longevity',
		'sillage'     => '_nafha_sillage',
		'video_url'   => '_nafha_video_url',
	);

	public static function register() {
		// Admin.
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );

		// Front end.
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_brand' ), 4 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_name_en' ), 6 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_profile' ), 35 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'render_video' ), 5 );
	}

	/* -------------------------------------------------------------- data */

	public static function get( $product, $key ) {
		return $product->get_meta( self::META[ $key ] );
	}

	/**
	 * Notes are stored as a comma/Arabic-comma separated list.
	 *
	 * @return string[]
	 */
	public static function notes( $product, $layer ) {
		$raw = (string) self::get( $product, 'notes_' . $layer );
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,،\n]+/u', $raw ) ) ) );
	}

	/**
	 * Human labels for the 1–5 scales.
	 */
	public static function scale_labels( $scale ) {
		if ( 'sillage' === $scale ) {
			return array(
				1 => __( 'Close to skin', 'nafha-core' ),
				2 => __( 'Soft', 'nafha-core' ),
				3 => __( 'Moderate', 'nafha-core' ),
				4 => __( 'Strong', 'nafha-core' ),
				5 => __( 'Room-filling', 'nafha-core' ),
			);
		}
		return array(
			1 => __( 'Under 2 hours', 'nafha-core' ),
			2 => __( '2–4 hours', 'nafha-core' ),
			3 => __( '4–6 hours', 'nafha-core' ),
			4 => __( '6–8 hours', 'nafha-core' ),
			5 => __( 'Over 8 hours', 'nafha-core' ),
		);
	}

	/* ------------------------------------------------------------- admin */

	/**
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['nafha_profile'] = array(
			'label'    => __( 'Fragrance profile', 'nafha-core' ),
			'target'   => 'nafha_profile_data',
			'class'    => array(),
			'priority' => 15,
		);
		return $tabs;
	}

	public static function render_panel() {
		global $product_object;

		$scale = static fn( $labels ) => array( '' => __( '— Not set —', 'nafha-core' ) ) + $labels;
		?>
		<div id="nafha_profile_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => self::META['name_en'],
						'label'             => __( 'English name', 'nafha-core' ),
						'description'       => __( 'Shown under the title and used by search, e.g. "Sauvage Eau de Parfum".', 'nafha-core' ),
						'desc_tip'          => true,
						'value'             => self::get( $product_object, 'name_en' ),
						'custom_attributes' => array( 'dir' => 'ltr' ),
					)
				);
				?>
			</div>
			<div class="options_group">
				<?php
				$layers = array(
					'top'   => __( 'Top notes', 'nafha-core' ),
					'heart' => __( 'Heart notes', 'nafha-core' ),
					'base'  => __( 'Base notes', 'nafha-core' ),
				);
				foreach ( $layers as $layer => $label ) {
					woocommerce_wp_textarea_input(
						array(
							'id'          => self::META[ 'notes_' . $layer ],
							'label'       => $label,
							'placeholder' => __( 'Comma separated, e.g. Bergamot, Pink pepper', 'nafha-core' ),
							'value'       => self::get( $product_object, 'notes_' . $layer ),
							'rows'        => 2,
						)
					);
				}
				?>
			</div>
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'      => self::META['longevity'],
						'label'   => __( 'Longevity', 'nafha-core' ),
						'options' => $scale( self::scale_labels( 'longevity' ) ),
						'value'   => self::get( $product_object, 'longevity' ),
					)
				);
				woocommerce_wp_select(
					array(
						'id'      => self::META['sillage'],
						'label'   => __( 'Sillage', 'nafha-core' ),
						'options' => $scale( self::scale_labels( 'sillage' ) ),
						'value'   => self::get( $product_object, 'sillage' ),
					)
				);
				?>
			</div>
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => self::META['video_url'],
						'label'       => __( 'Video URL', 'nafha-core' ),
						'description' => __( 'YouTube/Vimeo link or an uploaded .mp4 file URL.', 'nafha-core' ),
						'desc_tip'    => true,
						'type'        => 'url',
						'value'       => self::get( $product_object, 'video_url' ),
					)
				);
				?>
				<p class="form-field"><em><?php esc_html_e( 'Season, occasion and fragrance family are set in the Attributes tab.', 'nafha-core' ); ?></em></p>
			</div>
		</div>
		<?php
	}

	/**
	 * WooCommerce has already verified the product-save nonce and edit capability here.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (see above).
		$post = wp_unslash( $_POST );

		$product->update_meta_data( self::META['name_en'], sanitize_text_field( $post[ self::META['name_en'] ] ?? '' ) );
		foreach ( array( 'top', 'heart', 'base' ) as $layer ) {
			$key = self::META[ 'notes_' . $layer ];
			$product->update_meta_data( $key, sanitize_textarea_field( $post[ $key ] ?? '' ) );
		}
		foreach ( array( 'longevity', 'sillage' ) as $scale ) {
			$value = absint( $post[ self::META[ $scale ] ] ?? 0 );
			$product->update_meta_data( self::META[ $scale ], $value >= 1 && $value <= 5 ? $value : '' );
		}
		$product->update_meta_data( self::META['video_url'], esc_url_raw( $post[ self::META['video_url'] ] ?? '' ) );
		// phpcs:enable
	}

	/* --------------------------------------------------------- front end */

	public static function render_brand() {
		global $product;
		$brands = get_the_terms( $product->get_id(), 'product_brand' );
		if ( ! $brands || is_wp_error( $brands ) ) {
			return;
		}
		$brand = reset( $brands );
		printf(
			'<a class="nafha-brand" href="%s">%s</a>',
			esc_url( get_term_link( $brand ) ),
			esc_html( $brand->name )
		);
	}

	public static function render_name_en() {
		global $product;
		$name = self::get( $product, 'name_en' );
		if ( $name ) {
			printf( '<p class="nafha-name-en" dir="ltr" lang="en">%s</p>', esc_html( $name ) );
		}
	}

	public static function render_profile() {
		global $product;

		$layers = array(
			'top'   => __( 'Top notes', 'nafha-core' ),
			'heart' => __( 'Heart notes', 'nafha-core' ),
			'base'  => __( 'Base notes', 'nafha-core' ),
		);
		$layers = array_filter(
			array_map(
				fn( $label, $layer ) => array( $label, self::notes( $product, $layer ) ),
				$layers,
				array_keys( $layers )
			),
			static fn( $row ) => $row[1]
		);

		$facts = array_filter(
			array(
				__( 'Fragrance family', 'nafha-core' ) => wc_get_product_terms( $product->get_id(), 'pa_fragrance-family', array( 'fields' => 'names' ) ),
				__( 'Season', 'nafha-core' )           => wc_get_product_terms( $product->get_id(), 'pa_season', array( 'fields' => 'names' ) ),
				__( 'Occasion', 'nafha-core' )         => wc_get_product_terms( $product->get_id(), 'pa_occasion', array( 'fields' => 'names' ) ),
			)
		);

		$meters = array();
		foreach ( array( 'longevity' => __( 'Longevity', 'nafha-core' ), 'sillage' => __( 'Sillage', 'nafha-core' ) ) as $scale => $label ) {
			$value = (int) self::get( $product, $scale );
			if ( $value ) {
				$meters[] = array( $label, $value, self::scale_labels( $scale )[ $value ] );
			}
		}

		if ( ! $layers && ! $facts && ! $meters ) {
			return;
		}
		?>
		<section class="nafha-profile" aria-labelledby="nafha-profile-title">
			<h2 id="nafha-profile-title" class="nafha-profile__title"><?php esc_html_e( 'Fragrance profile', 'nafha-core' ); ?></h2>

			<?php if ( $layers ) : ?>
				<div class="nafha-pyramid">
					<?php foreach ( $layers as $row ) : ?>
						<div class="nafha-pyramid__row">
							<span class="nafha-pyramid__label"><?php echo esc_html( $row[0] ); ?></span>
							<ul class="nafha-chips">
								<?php foreach ( $row[1] as $note ) : ?>
									<li><?php echo esc_html( $note ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $meters ) : ?>
				<div class="nafha-meters">
					<?php foreach ( $meters as $meter ) : ?>
						<div class="nafha-meter">
							<span class="nafha-meter__label"><?php echo esc_html( $meter[0] ); ?></span>
							<span class="nafha-meter__bar" role="meter" aria-valuemin="1" aria-valuemax="5" aria-valuenow="<?php echo esc_attr( $meter[1] ); ?>" aria-valuetext="<?php echo esc_attr( $meter[2] ); ?>">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="nafha-meter__seg<?php echo $i <= $meter[1] ? ' is-on' : ''; ?>"></span>
								<?php endfor; ?>
							</span>
							<span class="nafha-meter__value"><?php echo esc_html( $meter[2] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $facts ) : ?>
				<dl class="nafha-facts">
					<?php foreach ( $facts as $label => $names ) : ?>
						<div class="nafha-facts__row">
							<dt><?php echo esc_html( $label ); ?></dt>
							<dd><?php echo esc_html( implode( '، ', $names ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
		<?php
	}

	public static function render_video() {
		global $product;
		$url = self::get( $product, 'video_url' );
		if ( ! $url ) {
			return;
		}

		$embed = preg_match( '/\.(mp4|webm|mov)(\?.*)?$/i', $url )
			? wp_video_shortcode( array( 'src' => $url, 'preload' => 'none' ) )
			: wp_oembed_get( $url );
		if ( ! $embed ) {
			return;
		}
		?>
		<section class="nafha-video" aria-label="<?php esc_attr_e( 'Product video', 'nafha-core' ); ?>">
			<?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated embed markup. ?>
		</section>
		<?php
	}
}

Nafha_Fragrance_Profile::register();

<?php
/**
 * "Which perfume suits you?" quiz — shortcode [nafha_quiz].
 *
 * Six questions (gender, season, occasion, favourite scents, strength,
 * budget) shown one at a time by assets/js/quiz.js, submitted as a plain GET
 * form. Results are scored server-side against each product's attributes and
 * fragrance profile and rendered as normal product cards, so the result URL
 * is shareable and works without JavaScript.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Quiz {

	const SHORTCODE   = 'nafha_quiz';
	const MAX_RESULTS = 4;

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), self::SHORTCODE ) ) {
			return;
		}
		foreach ( array( 'css/quiz.css', 'js/quiz.js' ) as $asset ) {
			$path = 'assets/' . $asset;
			$ver  = (string) filemtime( NAFHA_CORE_DIR . $path );
			if ( str_ends_with( $asset, '.css' ) ) {
				wp_enqueue_style( 'nafha-quiz', NAFHA_CORE_URL . $path, array(), $ver );
			} else {
				wp_enqueue_script( 'nafha-quiz', NAFHA_CORE_URL . $path, array(), $ver, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/**
	 * Questions: key => [question, multiple?, options value => label].
	 * Values map onto attribute term slugs (or the rules in score()).
	 */
	private static function questions() {
		return array(
			'gender'   => array(
				__( 'Who is the perfume for?', 'nafha-core' ),
				false,
				array(
					'men'    => __( 'Men', 'nafha-core' ),
					'women'  => __( 'Women', 'nafha-core' ),
					'unisex' => __( 'Either — I like unisex scents', 'nafha-core' ),
				),
			),
			'season'   => array(
				__( 'When will you wear it most?', 'nafha-core' ),
				false,
				array(
					'summer'      => __( 'Summer', 'nafha-core' ),
					'winter'      => __( 'Winter', 'nafha-core' ),
					'spring'      => __( 'Spring & autumn', 'nafha-core' ),
					'all-seasons' => __( 'All year round', 'nafha-core' ),
				),
			),
			'occasion' => array(
				__( 'What is the occasion?', 'nafha-core' ),
				false,
				array(
					'daily'   => __( 'Every day', 'nafha-core' ),
					'work'    => __( 'Work', 'nafha-core' ),
					'evening' => __( 'Evenings out', 'nafha-core' ),
					'special' => __( 'Special occasions', 'nafha-core' ),
				),
			),
			'family'   => array(
				__( 'Which scents do you love? (up to two)', 'nafha-core' ),
				true,
				array(
					'fresh'    => __( 'Fresh & citrusy', 'nafha-core' ),
					'floral'   => __( 'Floral & soft', 'nafha-core' ),
					'woody'    => __( 'Woody', 'nafha-core' ),
					'oriental' => __( 'Warm & oriental (oud, amber, musk)', 'nafha-core' ),
				),
			),
			'strength' => array(
				__( 'How strong should it be?', 'nafha-core' ),
				false,
				array(
					'light'  => __( 'Light and close to the skin', 'nafha-core' ),
					'medium' => __( 'Balanced', 'nafha-core' ),
					'strong' => __( 'Strong and long-lasting', 'nafha-core' ),
				),
			),
			'budget'   => array(
				__( 'Your budget for a 10 ml sample?', 'nafha-core' ),
				false,
				array(
					'low'  => __( 'Under $15', 'nafha-core' ),
					'mid'  => __( '$15 – $25', 'nafha-core' ),
					'high' => __( 'Over $25', 'nafha-core' ),
					'any'  => __( 'Doesn’t matter', 'nafha-core' ),
				),
			),
		);
	}

	/**
	 * Answer families → fragrance-family term slugs.
	 */
	private static function family_map() {
		return array(
			'fresh'    => array( 'fresh', 'citrus' ),
			'floral'   => array( 'floral' ),
			'woody'    => array( 'woody' ),
			'oriental' => array( 'oriental', 'amber', 'musky' ),
		);
	}

	/**
	 * Validated answers from the query string (only known values survive).
	 */
	private static function answers() {
		$answers = array();
		foreach ( self::questions() as $key => list( , $multiple, $options ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET form.
			$raw    = isset( $_GET[ 'q_' . $key ] ) ? wp_unslash( $_GET[ 'q_' . $key ] ) : array();
			$values = array_values( array_intersect( array_map( 'sanitize_key', (array) $raw ), array_keys( $options ) ) );
			if ( $values ) {
				$answers[ $key ] = $multiple ? array_slice( $values, 0, 2 ) : $values[0];
			}
		}
		return $answers;
	}

	/* ---------------------------------------------------------- scoring */

	/**
	 * Score one product against the answers.
	 *
	 * @return array{score: float, max: float}|null Null when it must not be shown (wrong gender).
	 */
	private static function score( WC_Product $product, array $answers ) {
		$id    = $product->get_id();
		$terms = static fn( $tax ) => wc_get_product_terms( $id, $tax, array( 'fields' => 'slugs' ) );
		$score = 0.0;
		$max   = 0.0;

		if ( isset( $answers['gender'] ) ) {
			$genders = $terms( 'pa_gender' );
			if ( 'unisex' !== $answers['gender'] && ! array_intersect( $genders, array( $answers['gender'], 'unisex' ) ) ) {
				return null; // Never suggest a women's perfume to someone shopping for men, and vice versa.
			}
			$max   += 2;
			$score += in_array( $answers['gender'], $genders, true ) ? 2 : 1;
		}

		if ( isset( $answers['season'] ) ) {
			$seasons = $terms( 'pa_season' );
			$wanted  = 'spring' === $answers['season'] ? array( 'spring', 'autumn' ) : array( $answers['season'] );
			$max    += 2;
			if ( array_intersect( $wanted, $seasons ) ) {
				$score += 2;
			} elseif ( in_array( 'all-seasons', $seasons, true ) ) {
				$score += 1.5;
			}
		}

		if ( isset( $answers['occasion'] ) ) {
			$max   += 2;
			$score += in_array( $answers['occasion'], $terms( 'pa_occasion' ), true ) ? 2 : 0;
		}

		if ( isset( $answers['family'] ) ) {
			$families = $terms( 'pa_fragrance-family' );
			foreach ( $answers['family'] as $choice ) {
				$max   += 3;
				$score += array_intersect( self::family_map()[ $choice ], $families ) ? 3 : 0;
			}
		}

		if ( isset( $answers['strength'] ) ) {
			$power = ( (int) $product->get_meta( '_nafha_longevity' ) + (int) $product->get_meta( '_nafha_sillage' ) ) / 2;
			if ( $power > 0 ) {
				$level  = $power >= 4 ? 2 : ( $power >= 3 ? 1 : 0 );
				$wanted = array_search( $answers['strength'], array( 'light', 'medium', 'strong' ), true );
				$max   += 2;
				$score += max( 0, 2 - abs( $level - $wanted ) );
			}
		}

		if ( isset( $answers['budget'] ) && 'any' !== $answers['budget'] ) {
			$price = self::sample_price( $product );
			if ( null !== $price ) {
				$range  = array( 'low' => array( 0, 15 ), 'mid' => array( 15, 25 ), 'high' => array( 25, PHP_FLOAT_MAX ) )[ $answers['budget'] ];
				$max   += 2;
				$score += ( $price >= $range[0] && $price <= $range[1] ) ? 2 : 0;
			}
		}

		return array( 'score' => $score, 'max' => $max );
	}

	/**
	 * Price of the 10 ml decant (what the budget question asks about), or the product price.
	 */
	private static function sample_price( WC_Product $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return (float) $product->get_price();
		}
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			if ( '10ml' === ( $variation->get_attributes()['pa_size'] ?? '' ) ) {
				return (float) $variation->get_price();
			}
		}
		return null;
	}

	/**
	 * Best matches: [product_id => percent], highest first.
	 *
	 * @return array<int, int>
	 */
	private static function results( array $answers ) {
		$ids = wc_get_products(
			array(
				'status'       => 'publish',
				'visibility'   => 'catalog',
				'stock_status' => 'instock',
				'limit'        => -1,
				'return'       => 'ids',
				'orderby'      => 'popularity',
				'category'     => array_values( array_diff( get_terms( array( 'taxonomy' => 'product_cat', 'fields' => 'slugs', 'hide_empty' => false ) ), array( 'gift-sets' ) ) ),
			)
		);

		$scored = array();
		foreach ( $ids as $rank => $id ) {
			$result = self::score( wc_get_product( $id ), $answers );
			if ( $result && $result['max'] > 0 ) {
				// Popularity (query order) breaks ties.
				$scored[ $id ] = array( $result['score'] / $result['max'], -$rank );
			}
		}
		uasort( $scored, static fn( $a, $b ) => $b <=> $a );

		return array_map( static fn( $s ) => (int) round( $s[0] * 100 ), array_slice( $scored, 0, self::MAX_RESULTS, true ) );
	}

	/* ----------------------------------------------------------- render */

	public static function render() {
		$answers = self::answers();
		ob_start();

		if ( count( $answers ) >= 3 ) {
			self::render_results( $answers );
		} else {
			self::render_form();
		}
		return ob_get_clean();
	}

	private static function render_form() {
		$questions = self::questions();
		$total     = count( $questions );
		?>
		<form class="nafha-quiz" method="get" action="<?php echo esc_url( get_permalink() ); ?>" data-total="<?php echo esc_attr( $total ); ?>">
			<div class="nafha-quiz__progress" aria-hidden="true"><span></span></div>
			<?php
			$i = 0;
			foreach ( $questions as $key => list( $question, $multiple, $options ) ) :
				++$i;
				?>
				<fieldset class="nafha-quiz__step" data-step="<?php echo esc_attr( $i ); ?>" data-multiple="<?php echo $multiple ? '2' : '1'; ?>">
					<legend>
						<span class="nafha-quiz__count">
							<?php
							/* translators: 1: current question number, 2: total questions. */
							echo esc_html( sprintf( __( 'Question %1$d of %2$d', 'nafha-core' ), $i, $total ) );
							?>
						</span>
						<?php echo esc_html( $question ); ?>
					</legend>
					<div class="nafha-quiz__options">
						<?php foreach ( $options as $value => $label ) : ?>
							<label class="nafha-quiz__option">
								<input type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>" name="q_<?php echo esc_attr( $key . ( $multiple ? '[]' : '' ) ); ?>" value="<?php echo esc_attr( $value ); ?>">
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="nafha-quiz__nav">
						<?php if ( $i > 1 ) : ?>
							<button type="button" class="nafha-quiz__back"><?php esc_html_e( 'Back', 'nafha-core' ); ?></button>
						<?php endif; ?>
						<button type="<?php echo $i === $total ? 'submit' : 'button'; ?>" class="button nafha-quiz__next">
							<?php echo esc_html( $i === $total ? __( 'Show my perfumes', 'nafha-core' ) : __( 'Next', 'nafha-core' ) ); ?>
						</button>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</form>
		<?php
	}

	private static function render_results( array $answers ) {
		$results = self::results( $answers );
		?>
		<section class="nafha-quiz-results" data-results="<?php echo esc_attr( count( $results ) ); ?>">
			<h2><?php esc_html_e( 'Your perfect matches', 'nafha-core' ); ?></h2>
			<?php if ( ! $results ) : ?>
				<p><?php esc_html_e( 'We could not find a close match. Try different answers, or browse the full collection.', 'nafha-core' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Based on your answers, start with these. A 5 ml sample is the easiest way to find your signature scent.', 'nafha-core' ); ?></p>
				<?php
				// Same cards as the shop; the match percentage is added through the loop hook below.
				$add_badge = static function () use ( $results ) {
					global $product;
					if ( isset( $results[ $product->get_id() ] ) ) {
						printf(
							'<span class="nafha-match">%s</span>',
							/* translators: %d: match percentage. */
							esc_html( sprintf( __( '%d%% match', 'nafha-core' ), $results[ $product->get_id() ] ) )
						);
					}
				};
				add_action( 'woocommerce_before_shop_loop_item', $add_badge, 5 );
				wc_set_loop_prop( 'columns', min( 4, count( $results ) ) );
				woocommerce_product_loop_start();
				foreach ( array_keys( $results ) as $id ) {
					$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- standard loop setup.
					setup_postdata( $GLOBALS['post'] );
					wc_get_template_part( 'content', 'product' );
				}
				woocommerce_product_loop_end();
				wp_reset_postdata();
				remove_action( 'woocommerce_before_shop_loop_item', $add_badge, 5 );
				?>
			<?php endif; ?>
			<p class="nafha-quiz-results__actions">
				<a class="button" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Retake the quiz', 'nafha-core' ); ?></a>
				<?php $box = get_page_by_path( 'discovery-box' ); ?>
				<?php if ( $box ) : ?>
					<a class="button nafha-button-outline" href="<?php echo esc_url( get_permalink( $box ) ); ?>"><?php esc_html_e( 'Try several in a discovery box', 'nafha-core' ); ?></a>
				<?php endif; ?>
			</p>
			<script>
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push( { event: 'quiz_complete', quiz_results: <?php echo (int) count( $results ); ?> } );
			</script>
		</section>
		<?php
	}
}

Nafha_Quiz::register();

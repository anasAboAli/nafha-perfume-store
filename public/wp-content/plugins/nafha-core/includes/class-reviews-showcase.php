<?php
/**
 * [nafha_reviews limit="3"] — the latest approved 4–5★ product reviews, for
 * the home page "What customers say" section.
 *
 * Pulled from real reviews instead of hand-written testimonials, so the
 * section stays truthful and refreshes itself as customers review products.
 * Renders nothing when there are no qualifying reviews.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Reviews_Showcase {

	public static function register() {
		add_shortcode( 'nafha_reviews', array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function render( $atts ) {
		$atts     = shortcode_atts( array( 'limit' => 3 ), $atts, 'nafha_reviews' );
		$reviews  = get_comments(
			array(
				'post_type'  => 'product',
				'status'     => 'approve',
				'type'       => 'review',
				'number'     => absint( $atts['limit'] ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- small, cached page section.
					array(
						'key'     => 'rating',
						'value'   => 4,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		if ( ! $reviews ) {
			return '';
		}

		ob_start();
		?>
		<div class="nafha-reviews">
			<?php foreach ( $reviews as $review ) : ?>
				<?php $rating = (int) get_comment_meta( $review->comment_ID, 'rating', true ); ?>
				<figure class="nafha-review">
					<div class="nafha-review__stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating out of 5. */ __( 'Rated %d out of 5', 'nafha-core' ), $rating ) ); ?>">
						<?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?>
					</div>
					<blockquote><?php echo esc_html( wp_trim_words( $review->comment_content, 40 ) ); ?></blockquote>
					<figcaption>
						<strong><?php echo esc_html( $review->comment_author ); ?></strong>
						<a href="<?php echo esc_url( get_permalink( $review->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( $review->comment_post_ID ) ); ?></a>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

Nafha_Reviews_Showcase::register();

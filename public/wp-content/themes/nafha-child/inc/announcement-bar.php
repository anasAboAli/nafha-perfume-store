<?php
/**
 * Top announcement bar (free shipping, offers…), editable from
 * Appearance → Customize → Nafha: Announcement bar.
 *
 * @package Nafha_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function nafha_child_announcement_customizer( $wp_customize ) {
	$wp_customize->add_section(
		'nafha_announcement',
		array(
			'title'    => __( 'Nafha: Announcement bar', 'nafha-child' ),
			'priority' => 25,
		)
	);

	$wp_customize->add_setting(
		'nafha_announcement_enabled',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'nafha_announcement_enabled',
		array(
			'label'   => __( 'Show announcement bar', 'nafha-child' ),
			'section' => 'nafha_announcement',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'nafha_announcement_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'nafha_announcement_text',
		array(
			'label'       => __( 'Text', 'nafha-child' ),
			'description' => __( 'Leave empty to use the default free-shipping message.', 'nafha-child' ),
			'section'     => 'nafha_announcement',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'nafha_announcement_link',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'nafha_announcement_link',
		array(
			'label'   => __( 'Link (optional)', 'nafha-child' ),
			'section' => 'nafha_announcement',
			'type'    => 'url',
		)
	);
}
add_action( 'customize_register', 'nafha_child_announcement_customizer' );

function nafha_child_render_announcement() {
	if ( ! get_theme_mod( 'nafha_announcement_enabled', true ) ) {
		return;
	}

	$text = esc_html( get_theme_mod( 'nafha_announcement_text', '' ) );
	if ( '' === $text ) {
		$min  = class_exists( 'Nafha_Shipping' ) ? Nafha_Shipping::free_shipping_min() : 0; // Follows the shipping zones.
		if ( $min <= 0 ) {
			return; // No free shipping configured: nothing to announce unless a custom text is set.
		}
		$text = sprintf(
			/* translators: %s: minimum order amount for free shipping, e.g. "$60". */
			esc_html__( 'Free delivery across Qatar on orders over %s', 'nafha-child' ),
			// wc_price() wraps the amount in <bdi> so "$60" keeps its LTR order inside Arabic text.
			wc_price( $min, array( 'decimals' => 0 ) )
		);
	}
	$link = get_theme_mod( 'nafha_announcement_link', '' );
	?>
	<div class="nafha-announcement" role="region" aria-label="<?php esc_attr_e( 'Announcement', 'nafha-child' ); ?>">
		<?php if ( $link ) : ?>
			<a href="<?php echo esc_url( $link ); ?>"><?php echo wp_kses_post( $text ); ?></a>
		<?php else : ?>
			<span><?php echo wp_kses_post( $text ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'blocksy:header:before', 'nafha_child_render_announcement' );

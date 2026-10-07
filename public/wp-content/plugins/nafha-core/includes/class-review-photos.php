<?php
/**
 * Optional photo on product reviews.
 *
 * Guests can review, so uploads are locked down: one image, JPEG/PNG/WebP
 * only (checked by content, not extension), max 3 MB, a nonce, and every
 * review with a photo is held for moderation before it appears.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Review_Photos {

	const FIELD    = 'nafha_review_photo';
	const META     = 'nafha_review_photo';
	const NONCE    = 'nafha_review_photo_nonce';
	const MAX_SIZE = 3 * MB_IN_BYTES;
	const MIMES    = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	);

	public static function register() {
		add_filter( 'woocommerce_product_review_comment_form_args', array( __CLASS__, 'form_field' ) );
		add_action( 'comment_post', array( __CLASS__, 'handle_upload' ), 10, 3 );
		add_action( 'woocommerce_review_after_comment_text', array( __CLASS__, 'render_photo' ) );
		add_action( 'delete_comment', array( __CLASS__, 'delete_photo' ) );
	}

	/**
	 * @param array $args comment_form() args for the review form.
	 * @return array
	 */
	public static function form_field( $args ) {
		$field  = '<p class="comment-form-photo nafha-review-photo">';
		$field .= '<label for="' . esc_attr( self::FIELD ) . '">' . esc_html__( 'Add a photo (optional)', 'nafha-core' ) . '</label>';
		$field .= '<input type="file" id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" accept="image/jpeg,image/png,image/webp">';
		$field .= '<small>' . esc_html__( 'JPG, PNG or WebP, up to 3 MB. Reviews with photos appear after approval.', 'nafha-core' ) . '</small>';
		$field .= wp_nonce_field( 'nafha_review_photo', self::NONCE, true, false );
		$field .= '</p>';

		$args['comment_field'] = ( $args['comment_field'] ?? '' ) . $field;
		// comment_form() has no enctype option; product.js switches the form to multipart.
		return $args;
	}

	/**
	 * @param int        $comment_id  New comment ID.
	 * @param int|string $approved    1, 0 or 'spam'.
	 * @param array      $commentdata Comment data.
	 */
	public static function handle_upload( $comment_id, $approved, $commentdata ) {
		if ( 'spam' === $approved || 'product' !== get_post_type( $commentdata['comment_post_ID'] ?? 0 ) ) {
			return;
		}
		if ( empty( $_FILES[ self::FIELD ]['name'] ) || UPLOAD_ERR_NO_FILE === ( $_FILES[ self::FIELD ]['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'nafha_review_photo' ) ) {
			return;
		}

		$file = $_FILES[ self::FIELD ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below and by wp_handle_upload().
		if ( UPLOAD_ERR_OK !== $file['error'] || $file['size'] > self::MAX_SIZE ) {
			return;
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::MIMES );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], self::MIMES, true ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload(
			self::FIELD,
			(int) $commentdata['comment_post_ID'],
			array( 'post_title' => sprintf( 'Review photo #%d', $comment_id ) ),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		update_comment_meta( $comment_id, self::META, $attachment_id );
		if ( 1 === (int) $approved ) {
			wp_set_comment_status( $comment_id, 'hold' );
		}
	}

	public static function render_photo( $comment ) {
		$attachment_id = (int) get_comment_meta( $comment->comment_ID, self::META, true );
		if ( ! $attachment_id ) {
			return;
		}
		printf(
			'<a class="nafha-review-photo-thumb" href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( wp_get_attachment_image_url( $attachment_id, 'large' ) ),
			wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => esc_attr__( 'Customer photo', 'nafha-core' ) ) )
		);
	}

	public static function delete_photo( $comment_id ) {
		$attachment_id = (int) get_comment_meta( $comment_id, self::META, true );
		if ( $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}

Nafha_Review_Photos::register();

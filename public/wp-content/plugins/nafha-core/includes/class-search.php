<?php
/**
 * Product search that understands how people actually type perfume names.
 *
 * Each product gets a normalised search index (Arabic title, English name,
 * brand, notes, family, categories, SKU) stored in post meta on save.
 * Product searches — the results page and Blocksy's live search, which goes
 * through the REST search endpoint — match every typed word against it.
 *
 * Normalisation folds the Arabic spelling variants people mix freely
 * (أ/إ/آ→ا, ة→ه, ى→ي, ؤ→و, ئ→ي, diacritics, tatweel) and lower-cases Latin,
 * so "عطر الفانيلا", "فانيليا", "SAUVAGE" or "ديور" all find what they should.
 *
 * @package Nafha_Core
 */

defined( 'ABSPATH' ) || exit;

final class Nafha_Search {

	const META       = '_nafha_search_index';
	const ALIAS_META = 'nafha_aliases';

	public static function register() {
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'index_product' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 10, 4 );
		add_filter( 'posts_search', array( __CLASS__, 'search_sql' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'live_search_fields' ) );

		// Brand aliases ("ديور" for Dior): brands are named in Latin but searched in both scripts.
		add_action( 'product_brand_add_form_fields', array( __CLASS__, 'alias_field_add' ) );
		add_action( 'product_brand_edit_form_fields', array( __CLASS__, 'alias_field_edit' ) );
		add_action( 'created_product_brand', array( __CLASS__, 'save_alias' ) );
		add_action( 'edited_product_brand', array( __CLASS__, 'save_alias' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'nafha reindex', array( __CLASS__, 'cli_reindex' ) );
		}
	}

	/**
	 * Fold Arabic spelling variants and case so typed queries match the index.
	 */
	public static function normalize( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text ); // Diacritics + tatweel.
		$text = strtr(
			$text,
			array(
				'أ' => 'ا',
				'إ' => 'ا',
				'آ' => 'ا',
				'ٱ' => 'ا',
				'ة' => 'ه',
				'ى' => 'ي',
				'ؤ' => 'و',
				'ئ' => 'ي',
				'،' => ' ',
			)
		);
		$text = remove_accents( $text ); // Giò → gio.
		$text = mb_strtolower( $text, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/* ------------------------------------------------------------ index */

	/**
	 * @param WC_Product|int $product Product object or ID.
	 */
	public static function index_product( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return;
		}
		$id    = $product->get_id();
		$parts = array(
			$product->get_name(),
			$product->get_sku(),
			$product->get_meta( '_nafha_name_en' ),
			$product->get_meta( '_nafha_notes_top' ),
			$product->get_meta( '_nafha_notes_heart' ),
			$product->get_meta( '_nafha_notes_base' ),
		);
		foreach ( array( 'product_brand', 'product_cat', 'pa_fragrance-family', 'pa_gender' ) as $taxonomy ) {
			$terms = wp_get_post_terms( $id, $taxonomy );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$parts[] = $term->name;
				$parts[] = get_term_meta( $term->term_id, self::ALIAS_META, true );
			}
		}
		update_post_meta( $id, self::META, self::normalize( implode( ' | ', array_filter( $parts ) ) ) );
	}

	/**
	 * Brands/categories are often assigned after the product object is saved.
	 */
	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'product_brand', 'product_cat', 'pa_fragrance-family', 'pa_gender' ), true ) && 'product' === get_post_type( $object_id ) ) {
			self::index_product( $object_id );
		}
	}

	/**
	 * Rebuild the index for all products.
	 *
	 * ## EXAMPLES
	 *     wp nafha reindex
	 */
	public static function cli_reindex() {
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		foreach ( $ids as $id ) {
			self::index_product( $id );
		}
		WP_CLI::success( count( $ids ) . ' products indexed.' );
	}

	/* ---------------------------------------------------- brand aliases */

	public static function alias_field_add() {
		?>
		<div class="form-field">
			<label for="nafha_aliases"><?php esc_html_e( 'Search aliases', 'nafha-core' ); ?></label>
			<input type="text" id="nafha_aliases" name="nafha_aliases" value="">
			<?php wp_nonce_field( 'nafha_brand_alias', 'nafha_brand_alias_nonce' ); ?>
			<p><?php esc_html_e( 'Other spellings customers may type, e.g. the Arabic name. Comma separated.', 'nafha-core' ); ?></p>
		</div>
		<?php
	}

	public static function alias_field_edit( $term ) {
		?>
		<tr class="form-field">
			<th scope="row"><label for="nafha_aliases"><?php esc_html_e( 'Search aliases', 'nafha-core' ); ?></label></th>
			<td>
				<input type="text" id="nafha_aliases" name="nafha_aliases" value="<?php echo esc_attr( get_term_meta( $term->term_id, self::ALIAS_META, true ) ); ?>">
				<?php wp_nonce_field( 'nafha_brand_alias', 'nafha_brand_alias_nonce' ); ?>
				<p class="description"><?php esc_html_e( 'Other spellings customers may type, e.g. the Arabic name. Comma separated.', 'nafha-core' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save_alias( $term_id ) {
		if (
			! isset( $_POST['nafha_brand_alias_nonce'], $_POST['nafha_aliases'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nafha_brand_alias_nonce'] ) ), 'nafha_brand_alias' )
			|| ! current_user_can( 'manage_product_terms' )
		) {
			return;
		}
		update_term_meta( $term_id, self::ALIAS_META, sanitize_text_field( wp_unslash( $_POST['nafha_aliases'] ) ) );

		// Re-index this brand's products so the new alias is searchable right away.
		foreach ( get_objects_in_term( $term_id, 'product_brand' ) as $product_id ) {
			self::index_product( (int) $product_id );
		}
	}

	/* ------------------------------------------------------------ query */

	/**
	 * Search words: normalised, generic words dropped ("عطر سوفاج" → "سوفاج"),
	 * and the Arabic article stripped so "الفانيليا" matches the stored "فانيليا"
	 * (the shorter form is still a substring of any "ال…" form in the index).
	 *
	 * @return string[]
	 */
	public static function query_words( $s ) {
		$stopwords = array( 'عطر', 'عطور', 'perfume', 'perfumes', 'fragrance' );
		$words     = array();
		foreach ( explode( ' ', self::normalize( $s ) ) as $word ) {
			if ( '' === $word || in_array( $word, $stopwords, true ) ) {
				continue;
			}
			if ( mb_strlen( $word ) > 4 && 0 === mb_strpos( $word, 'ال' ) ) {
				$word = mb_substr( $word, 2 );
			}
			$words[] = $word;
		}
		return $words;
	}

	private static function is_product_search( WP_Query $query ) {
		if ( '' === (string) $query->get( 's' ) || ( is_admin() && ! wp_doing_ajax() && ! wp_is_serving_rest_request() ) ) {
			return false;
		}
		$types = (array) $query->get( 'post_type' );
		return array( 'product' ) === array_values( $types );
	}

	/**
	 * Every word must appear in the index (or the title, for products not indexed yet).
	 *
	 * @param string   $search Default search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function search_sql( $search, $query ) {
		if ( ! self::is_product_search( $query ) ) {
			return $search;
		}
		global $wpdb;

		$words = self::query_words( $query->get( 's' ) );
		if ( ! $words ) {
			return $search;
		}

		$clauses = array();
		foreach ( $words as $word ) {
			$like      = '%' . $wpdb->esc_like( $word ) . '%';
			$clauses[] = $wpdb->prepare(
				"( {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ) OR {$wpdb->posts}.post_title LIKE %s )",
				self::META,
				$like,
				$like
			);
		}
		return ' AND ' . implode( ' AND ', $clauses ) . ' ';
	}

	/* ------------------------------------------------------ live search */

	/**
	 * Price + stock in Blocksy's live search dropdown (its JS renders these fields when present).
	 */
	public static function live_search_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by Blocksy.
		if ( ! isset( $_GET['ct_live_search'] ) ) {
			return;
		}
		register_rest_field(
			'search-result',
			'product_price',
			array(
				'get_callback' => static function ( $item ) {
					$product = 'product' === get_post_type( $item['id'] ) ? wc_get_product( $item['id'] ) : null;
					return $product ? $product->get_price_html() : '';
				},
			)
		);
	}
}

Nafha_Search::register();

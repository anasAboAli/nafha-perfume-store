<?php
/**
 * Idempotent store configuration. Run with:  tools/wp.sh eval-file tools/setup/store-setup.php
 *
 * Everything here is "data" (options, terms, pages, theme mods) that lives in the
 * database, kept in code so a fresh install or the production server can be
 * brought to the same state with one command. Safe to re-run.
 *
 * @package Nafha
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ helpers */

function nafha_setup_log( $msg ) {
	WP_CLI::log( '  ' . $msg );
}

/**
 * Create or update a page by slug and return its ID.
 */
function nafha_setup_page( $slug, $title, $content = '', $parent = 0 ) {
	$page = get_page_by_path( $slug );
	$data = array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_name'   => $slug,
		'post_title'  => $title,
		'post_parent' => $parent,
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		// Only fill content on first creation, so edits made in wp-admin survive re-runs.
		if ( '' === trim( $page->post_content ) && $content ) {
			$data['post_content'] = $content;
		}
		return wp_update_post( $data );
	}
	$data['post_content'] = $content;
	return wp_insert_post( $data );
}

/**
 * Create or update a term and return its ID.
 */
function nafha_setup_term( $taxonomy, $slug, $name, $args = array() ) {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term ) {
		wp_update_term( $term->term_id, $taxonomy, array_merge( array( 'name' => $name ), $args ) );
		return (int) $term->term_id;
	}
	$res = wp_insert_term( $name, $taxonomy, array_merge( array( 'slug' => $slug ), $args ) );
	return is_wp_error( $res ) ? 0 : (int) $res['term_id'];
}

/* ------------------------------------------------------------ store options */

WP_CLI::log( 'Store options' );

$options = array(
	'blogname'                             => 'نفحة',
	'blogdescription'                      => 'عطور أصلية بالتقسيم — جرّب قبل أن تشتري',
	'timezone_string'                      => 'Asia/Qatar',
	'woocommerce_default_country'          => 'QA:DA',
	'woocommerce_store_city'               => 'الدوحة',
	'woocommerce_allowed_countries'        => 'specific',
	'woocommerce_specific_allowed_countries' => array( 'QA' ),
	'woocommerce_ship_to_countries'        => 'specific',
	'woocommerce_specific_ship_to_countries' => array( 'QA' ),
	'woocommerce_default_customer_address' => 'base',
	'woocommerce_currency'                 => 'USD',
	'woocommerce_currency_pos'             => 'left',
	'woocommerce_price_num_decimals'       => '2',
	'woocommerce_calc_taxes'               => 'no',
	'woocommerce_weight_unit'              => 'g',
	'woocommerce_enable_guest_checkout'    => 'yes',
	'woocommerce_enable_checkout_login_reminder' => 'no', // Guests are the norm; the notice only adds noise.
	'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
	'woocommerce_enable_coupons'           => 'yes',
	'woocommerce_manage_stock'             => 'yes',
	'woocommerce_notify_low_stock_amount'  => '3',
	'woocommerce_stock_format'             => 'no_amount',
	'woocommerce_enable_reviews'           => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_enable_review_rating'     => 'yes',
	'woocommerce_review_rating_required'   => 'yes',
	'woocommerce_ship_to_destination'      => 'billing_only',
	'woocommerce_shipping_cost_requires_address' => 'no',
	'woocommerce_email_from_name'          => 'نفحة',
	'woocommerce_email_base_color'         => '#2b2130',
	'woocommerce_email_background_color'   => '#f6f1ea',
	'woocommerce_email_body_background_color' => '#ffffff',
	'woocommerce_email_text_color'         => '#2b2130',
	'woocommerce_email_footer_text'        => 'نفحة — عطور أصلية بالتقسيم في قطر · واتساب: ‎+970 59 814 3863',
	// Consent shown under "Place order" (replaces the terms checkbox, see Nafha_Checkout).
	'woocommerce_checkout_privacy_policy_text' => 'بإتمام الطلب فإنك توافق على [terms] و[privacy_policy].',
	// Checkout order: cash on delivery first (local habit), then WhatsApp, then bank transfer.
	'woocommerce_gateway_order'            => array( 'cod' => 0, 'nafha_whatsapp' => 1, 'bacs' => 2 ),
	'woocommerce_coming_soon'              => 'no',
	'woocommerce_default_catalog_orderby'  => 'popularity',
	'woocommerce_catalog_columns'          => 4,
	'woocommerce_catalog_rows'             => 3,
	'nafha_whatsapp_number'                => '970598143863', // Client-provided store WhatsApp.
	// Fixed Latin URL bases: otherwise WooCommerce uses the translated (Arabic, space-containing) defaults.
	'woocommerce_brand_permalink'          => 'brand',
	'woocommerce_permalinks'               => array(
		'product_base'           => '/product',
		'category_base'          => 'product-category',
		'tag_base'               => 'product-tag',
		'attribute_base'         => '',
		'use_verbose_page_rules' => false,
	),
);
foreach ( $options as $key => $value ) {
	update_option( $key, $value );
}
nafha_setup_log( count( $options ) . ' options set' );

/* --------------------------------------------------------- shipping zones */

WP_CLI::log( 'Shipping zones (Qatar)' );

$free_shipping_min = 60;
$zones             = array(
	array(
		'name'  => 'الدوحة',
		'areas' => array( 'DA' ),
		'cost'  => 4,
		'title' => 'توصيل داخل الدوحة (خلال 24 ساعة)',
	),
	array(
		'name'  => 'محيط الدوحة',
		'areas' => array( 'LU', 'RA', 'ZA', 'WA', 'US' ),
		'cost'  => 6,
		'title' => 'توصيل لمحيط الدوحة (خلال 24–48 ساعة)',
	),
	array(
		'name'  => 'المناطق الخارجية',
		'areas' => array( 'KH', 'MS', 'SHH', 'SH', 'DU' ),
		'cost'  => 10,
		'title' => 'توصيل للمناطق الخارجية (خلال 2–3 أيام)',
	),
);

// Rebuild our zones from scratch so prices/areas in this file are the source of truth.
foreach ( WC_Shipping_Zones::get_zones() as $existing ) {
	WC_Shipping_Zones::delete_zone( $existing['zone_id'] );
}
foreach ( $zones as $order => $def ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( $def['name'] );
	$zone->set_zone_order( $order );
	foreach ( $def['areas'] as $code ) {
		$zone->add_location( 'QA:' . $code, 'state' );
	}
	$zone->save();

	$flat_id = $zone->add_shipping_method( 'flat_rate' );
	update_option(
		'woocommerce_flat_rate_' . $flat_id . '_settings',
		array(
			'title'      => $def['title'],
			'tax_status' => 'none',
			'cost'       => (string) $def['cost'],
		)
	);

	$free_id = $zone->add_shipping_method( 'free_shipping' );
	update_option(
		'woocommerce_free_shipping_' . $free_id . '_settings',
		array(
			'title'                => 'شحن مجاني',
			'requires'             => 'min_amount',
			'min_amount'           => (string) $free_shipping_min,
			'ignore_discounts'     => 'no',
		)
	);
	nafha_setup_log( sprintf( '%s: $%s, free over $%s', $def['name'], $def['cost'], $free_shipping_min ) );
}
update_option( 'nafha_free_shipping_min', $free_shipping_min );

/* --------------------------------------------------------- payment gateways */

WP_CLI::log( 'Payment gateways' );

update_option(
	'woocommerce_cod_settings',
	array(
		'enabled'            => 'yes',
		'title'              => 'الدفع عند الاستلام',
		'description'        => 'ادفع نقدًا أو بالبطاقة لمندوب التوصيل عند استلام طلبك.',
		'instructions'       => 'سيتواصل معك فريقنا لتأكيد الطلب قبل الشحن.',
		'enable_for_methods' => array(),
		'enable_for_virtual' => 'yes',
	)
);
update_option(
	'woocommerce_bacs_settings',
	array(
		'enabled'      => 'yes',
		'title'        => 'تحويل بنكي',
		'description'  => 'حوّل قيمة الطلب إلى حسابنا البنكي، وسيُشحن الطلب بعد تأكيد التحويل. استخدم رقم الطلب كمرجع للتحويل.',
		'instructions' => 'يرجى إرسال صورة إيصال التحويل عبر واتساب مع رقم الطلب.',
	)
);
foreach ( array( 'cheque', 'paypal' ) as $gateway ) {
	$settings            = get_option( 'woocommerce_' . $gateway . '_settings', array() );
	$settings['enabled'] = 'no';
	update_option( 'woocommerce_' . $gateway . '_settings', $settings );
}
nafha_setup_log( 'COD + bank transfer enabled' );

/* ------------------------------------------------------------------ e-mails */

WP_CLI::log( 'E-mails' );

// Customers reach the store on WhatsApp, not by e-mail: say so at the end of every order e-mail.
$email_closing = 'شكرًا لاختيارك نفحة! لأي استفسار عن طلبك تواصل معنا عبر واتساب: ‎+970 59 814 3863';
foreach ( array( 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_invoice', 'customer_note' ) as $email_id ) {
	$settings                       = get_option( 'woocommerce_' . $email_id . '_settings', array() );
	$settings['additional_content'] = $email_closing;
	update_option( 'woocommerce_' . $email_id . '_settings', $settings );
}
nafha_setup_log( 'customer e-mail closing text set' );

/* --------------------------------------------------------- product taxonomy */

WP_CLI::log( 'Product categories' );

$categories = array(
	'men-perfumes'    => array( 'عطور رجالية', 'تشكيلة من أشهر العطور الرجالية العالمية والعربية، متوفرة بأحجام تجربة 5 و10 و30 مل لتختار عطرك المميز قبل شراء العبوة الكاملة.' ),
	'women-perfumes'  => array( 'عطور نسائية', 'عطور نسائية راقية من أشهر الماركات، بأحجام تقسيم تناسب التجربة والسفر والاستخدام اليومي.' ),
	'unisex-perfumes' => array( 'عطور للجنسين', 'عطور نيش وعطور شرقية تناسب الرجال والنساء، من العود والمسك إلى الروائح المنعشة.' ),
	'gift-sets'       => array( 'باقات الهدايا', 'صناديق هدايا وصناديق تجربة جاهزة تضم مجموعة مختارة من العطور بسعر مخفض.' ),
	'full-bottles'    => array( 'عبوات كاملة', 'العبوات الأصلية الكاملة من العطور التي أحببتها بعد التجربة.' ),
);
$order = 0;
foreach ( $categories as $slug => $def ) {
	$id = nafha_setup_term( 'product_cat', $slug, $def[0], array( 'description' => $def[1] ) );
	update_term_meta( $id, 'order', $order++ );
}
// Keep WooCommerce's fallback category but give it an Arabic name.
$uncat = get_option( 'default_product_cat' );
if ( $uncat ) {
	wp_update_term( (int) $uncat, 'product_cat', array( 'name' => 'غير مصنف' ) );
}
nafha_setup_log( count( $categories ) . ' categories' );

WP_CLI::log( 'Product attributes' );

$attributes = array(
	'size'          => array(
		'name'  => 'الحجم',
		'terms' => array(
			'5ml'         => '5 مل',
			'10ml'        => '10 مل',
			'30ml'        => '30 مل',
			'full-bottle' => 'العبوة الكاملة',
		),
	),
	'fragrance-family' => array(
		'name'  => 'العائلة العطرية',
		'terms' => array(
			'woody'    => 'خشبي',
			'floral'   => 'زهري',
			'oriental' => 'شرقي',
			'citrus'   => 'حمضي',
			'fresh'    => 'فريش',
			'amber'    => 'عنبري',
			'musky'    => 'مسكي',
		),
	),
	'season'        => array(
		'name'  => 'الموسم',
		'terms' => array(
			'summer'      => 'صيف',
			'winter'      => 'شتاء',
			'spring'      => 'ربيع',
			'autumn'      => 'خريف',
			'all-seasons' => 'كل المواسم',
		),
	),
	'gender'        => array(
		'name'  => 'الجنس',
		'terms' => array(
			'men'    => 'رجالي',
			'women'  => 'نسائي',
			'unisex' => 'للجنسين',
		),
	),
	'occasion'      => array(
		'name'  => 'المناسبة',
		'terms' => array(
			'daily'   => 'يومي',
			'work'    => 'العمل',
			'evening' => 'السهرات',
			'special' => 'المناسبات الخاصة',
		),
	),
	'concentration' => array(
		'name'  => 'التركيز',
		'terms' => array(
			'parfum' => 'بارفان (Parfum)',
			'edp'    => 'أو دو بارفان (EDP)',
			'edt'    => 'أو دو تواليت (EDT)',
		),
	),
);

foreach ( $attributes as $slug => $def ) {
	$attr_id = wc_attribute_taxonomy_id_by_name( $slug );
	$args    = array(
		'name'         => $def['name'],
		'slug'         => $slug,
		'type'         => 'select',
		'order_by'     => 'menu_order',
		'has_archives' => false,
	);
	if ( $attr_id ) {
		wc_update_attribute( $attr_id, $args );
	} else {
		wc_create_attribute( $args );
	}

	// The taxonomy is only registered on the next request; register it now so terms can be inserted.
	$taxonomy = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false ) );
	}
	$order = 0;
	foreach ( $def['terms'] as $term_slug => $term_name ) {
		$term_id = nafha_setup_term( $taxonomy, $term_slug, $term_name );
		update_term_meta( $term_id, 'order', $order++ );
	}
	nafha_setup_log( $def['name'] . ': ' . count( $def['terms'] ) . ' terms' );
}
delete_transient( 'wc_attribute_taxonomies' );

WP_CLI::log( 'Brands' );

// WooCommerce's built-in Brands taxonomy (product_brand): gets its own archive pages for SEO.
// slug => [display name, search aliases].
$brands = array(
	'dior'                     => array( 'Dior', 'ديور' ),
	'chanel'                   => array( 'Chanel', 'شانيل، شنل' ),
	'tom-ford'                 => array( 'Tom Ford', 'توم فورد' ),
	'creed'                    => array( 'Creed', 'كريد' ),
	'yves-saint-laurent'       => array( 'Yves Saint Laurent', 'ايف سان لوران، YSL' ),
	'giorgio-armani'           => array( 'Giorgio Armani', 'جورجيو ارماني، ارماني' ),
	'maison-francis-kurkdjian' => array( 'Maison Francis Kurkdjian', 'ميزون فرانسيس كركدجيان، MFK' ),
	'parfums-de-marly'         => array( 'Parfums de Marly', 'بارفان دي مارلي، مارلي، PDM' ),
	'amouage'                  => array( 'Amouage', 'امواج' ),
	'lattafa'                  => array( 'Lattafa', 'لطافة' ),
);
foreach ( $brands as $slug => list( $name, $aliases ) ) {
	$brand_id = nafha_setup_term( 'product_brand', $slug, $name );
	update_term_meta( $brand_id, 'nafha_aliases', $aliases ); // Arabic spellings for search (Nafha_Search).
}
nafha_setup_log( count( $brands ) . ' brands' );

/* ------------------------------------------------------------------- pages */

WP_CLI::log( 'Pages' );

// WooCommerce created these in English before Arabic was active.
$wc_pages = array(
	'woocommerce_shop_page_id'      => 'المتجر',
	'woocommerce_cart_page_id'      => 'السلة',
	'woocommerce_checkout_page_id'  => 'إتمام الطلب',
	'woocommerce_myaccount_page_id' => 'حسابي',
);
foreach ( $wc_pages as $option => $title ) {
	$id = (int) get_option( $option );
	if ( $id ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
	}
}

// Classic cart/checkout (shortcodes) instead of the block versions: lighter JS, and our
// field, side-cart and WhatsApp customisations use the classic hooks.
$classic = array(
	'woocommerce_cart_page_id'     => '[woocommerce_cart]',
	'woocommerce_checkout_page_id' => '[woocommerce_checkout]',
);
foreach ( $classic as $option => $shortcode ) {
	$id = (int) get_option( $option );
	if ( $id ) {
		wp_update_post( array( 'ID' => $id, 'post_content' => "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->" ) );
	}
}
$refund_page = get_page_by_path( 'refund_returns' );
if ( $refund_page ) {
	wp_delete_post( $refund_page->ID, true ); // Replaced by our shipping & returns page.
}

$p = static fn( $text ) => "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";

$pages = array(
	'about'            => array( 'من نحن', $p( 'نفحة متجر قطري متخصص في تقسيم العطور الأصلية. نؤمن أن اختيار العطر تجربة شخصية، لذلك نتيح لك تجربة أرقى العطور بأحجام 5 و10 و30 مل قبل أن تقرر شراء العبوة الكاملة.' ) ),
	'contact'          => array( 'تواصل معنا', $p( 'يسعدنا تواصلك معنا عبر واتساب على الرقم 970598143863+، أو عبر البريد الإلكتروني hello@nafha.test. نرد على الرسائل يوميًا من 10 صباحًا حتى 10 مساءً.' ) ),
	'faq'              => array( 'الأسئلة الشائعة', $p( 'هل العطور أصلية؟ نعم، جميع العطور أصلية 100% ويتم تقسيمها من العبوة الأصلية في عبوات زجاجية معقّمة.' ) ),
	'shipping-returns' => array( 'سياسة الشحن والاسترجاع', $p( 'نوصل لجميع مناطق قطر. التوصيل داخل الدوحة خلال 24 ساعة، والشحن مجاني للطلبات التي تزيد عن 60 دولارًا.' ) ),
	'terms'            => array( 'الشروط والأحكام', $p( 'باستخدامك لموقع نفحة فإنك توافق على الشروط والأحكام التالية.' ) ),
	'fragrance-quiz'   => array( 'شو عطرك المناسب؟', $p( 'أجب عن ستة أسئلة بسيطة وسنقترح عليك العطور الأنسب لذوقك.' ) . "\n\n<!-- wp:shortcode -->\n[nafha_quiz]\n<!-- /wp:shortcode -->" ),
	'discovery-box'    => array( 'صندوق التجربة', $p( 'اختر 3 أو 5 عطور بسعة 5 أو 10 مل في صندوق واحد ووفّر حتى 20%. أفضل طريقة لتجربة أكثر من عطر قبل شراء العبوة الكاملة.' ) . "\n\n<!-- wp:shortcode -->\n[nafha_discovery_box]\n<!-- /wp:shortcode -->" ),
);
$page_ids = array();
foreach ( $pages as $slug => $def ) {
	$page_ids[ $slug ] = nafha_setup_page( $slug, $def[0], $def[1] );
}

// Feature pages need their shortcode even if the page already existed with placeholder text.
foreach ( array( 'fragrance-quiz' => 'nafha_quiz', 'discovery-box' => 'nafha_discovery_box' ) as $slug => $shortcode ) {
	$content = get_post_field( 'post_content', $page_ids[ $slug ] );
	if ( ! has_shortcode( $content, $shortcode ) ) {
		wp_update_post( array( 'ID' => $page_ids[ $slug ], 'post_content' => $pages[ $slug ][1] ) );
	}
}

$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
if ( $privacy_id ) {
	wp_update_post( array( 'ID' => $privacy_id, 'post_status' => 'publish', 'post_title' => 'سياسة الخصوصية', 'post_name' => 'privacy-policy' ) );
	$page_ids['privacy-policy'] = $privacy_id;
}
update_option( 'woocommerce_terms_page_id', $page_ids['terms'] );

$home_id = nafha_setup_page( 'home', 'الرئيسية' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );
nafha_setup_log( count( $page_ids ) + 1 . ' pages' );

/* ------------------------------------------------------------------- menus */

WP_CLI::log( 'Menus' );

/**
 * Recreate a menu from a list of [type, object id|url, title].
 */
function nafha_setup_menu( $name, $items ) {
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) {
		wp_delete_nav_menu( $menu->term_id );
	}
	$menu_id = wp_create_nav_menu( $name );
	foreach ( $items as $item ) {
		list( $type, $ref, $title ) = $item;
		$args = array(
			'menu-item-title'  => $title,
			'menu-item-status' => 'publish',
		);
		if ( 'page' === $type ) {
			$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $ref );
		} elseif ( 'cat' === $type ) {
			$term  = get_term_by( 'slug', $ref, 'product_cat' );
			$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $term->term_id );
		} else {
			$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $ref );
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
	return $menu_id;
}

$shop_id   = (int) get_option( 'woocommerce_shop_page_id' );
$main_menu = nafha_setup_menu(
	'القائمة الرئيسية',
	array(
		array( 'page', $shop_id, 'المتجر' ),
		array( 'cat', 'men-perfumes', 'رجالي' ),
		array( 'cat', 'women-perfumes', 'نسائي' ),
		array( 'cat', 'unisex-perfumes', 'للجنسين' ),
		array( 'cat', 'gift-sets', 'باقات الهدايا' ),
		array( 'page', $page_ids['discovery-box'], 'صندوق التجربة' ),
		array( 'page', $page_ids['fragrance-quiz'], 'شو عطرك المناسب؟' ),
	)
);
$footer_shop = nafha_setup_menu(
	'تذييل - تسوّق',
	array(
		array( 'cat', 'men-perfumes', 'عطور رجالية' ),
		array( 'cat', 'women-perfumes', 'عطور نسائية' ),
		array( 'cat', 'unisex-perfumes', 'عطور للجنسين' ),
		array( 'cat', 'gift-sets', 'باقات الهدايا' ),
		array( 'page', $page_ids['discovery-box'], 'صندوق التجربة' ),
		array( 'cat', 'full-bottles', 'عبوات كاملة' ),
	)
);
$footer_help = nafha_setup_menu(
	'تذييل - مساعدة',
	array(
		array( 'page', $page_ids['about'], 'من نحن' ),
		array( 'page', $page_ids['faq'], 'الأسئلة الشائعة' ),
		array( 'page', $page_ids['shipping-returns'], 'الشحن والاسترجاع' ),
		array( 'page', $page_ids['terms'], 'الشروط والأحكام' ),
		array( 'page', $page_ids['privacy-policy'], 'سياسة الخصوصية' ),
		array( 'page', $page_ids['contact'], 'تواصل معنا' ),
	)
);
set_theme_mod(
	'nav_menu_locations',
	array(
		'menu_1'      => $main_menu,
		'menu_mobile' => $main_menu,
	)
);
nafha_setup_log( 'main + 2 footer menus' );

/* ---------------------------------------------------------- Blocksy design */

WP_CLI::log( 'Theme (Blocksy) settings' );

// Brand palette — see nafha-child/assets/css/main.css for the same tokens.
set_theme_mod(
	'colorPalette',
	array(
		'color1'          => array( 'color' => '#2B2130' ), // Primary: ink plum (buttons, links).
		'color2'          => array( 'color' => '#9A7647' ), // Primary hover: antique gold.
		'color3'          => array( 'color' => '#4F4754' ), // Body text.
		'color4'          => array( 'color' => '#1E1722' ), // Headings.
		'color5'          => array( 'color' => '#E7DED2' ), // Borders.
		'color6'          => array( 'color' => '#F3EDE4' ), // Soft sections.
		'color7'          => array( 'color' => '#FBF8F4' ), // Page background.
		'color8'          => array( 'color' => '#FFFFFF' ),
		'current_palette' => 'palette-1',
	)
);

$header         = new Blocksy_Header_Builder();
$header_section = $header->get_structure_for(
	array(
		'id'    => 'type-1',
		'items' => array(
			'desktop' => array(
				'middle-row' => array(
					'start'  => array( 'logo' ),
					'middle' => array( 'menu' ),
					'end'    => array( 'search', 'cart' ),
				),
			),
			'mobile'  => array(
				'middle-row' => array(
					'start'  => array( 'trigger' ),
					'middle' => array( 'logo' ),
					'end'    => array( 'search', 'cart' ),
				),
				'offcanvas'  => array(
					'start' => array( 'mobile-menu' ),
				),
			),
		),
	)
);
// Header search: products only, live results with thumbnail + price.
// Header cart: no Blocksy dropdown — the icon opens nafha-core's side cart instead.
$header_section['items'] = array(
	array(
		'id'     => 'search',
		'values' => array(
			'search_through'            => array( 'post' => false, 'page' => false, 'product' => true ),
			'enable_live_results'       => 'yes',
			'searchHeaderImages'        => 'yes',
			'searchHeaderProductPrice'  => 'yes',
			'header_search_placeholder' => 'ابحث عن عطر، ماركة أو نوتة…',
		),
	),
	array(
		'id'     => 'cart',
		'values' => array(
			'has_cart_dropdown' => 'no',
		),
	),
);
set_theme_mod(
	'header_placements',
	array(
		'current_section' => 'type-1',
		'sections'        => array( $header_section ),
	)
);

$footer         = new Blocksy_Footer_Builder();
$footer_section = $footer->get_structure_for(
	array(
		'id'   => 'type-1',
		'rows' => array(
			'top-row'    => array( 'columns' => array( array(), array() ) ),
			'middle-row' => array( 'columns' => array( array( 'widget-area-1' ), array( 'widget-area-2' ), array( 'widget-area-3' ) ) ),
			'bottom-row' => array( 'columns' => array( array( 'copyright' ) ) ),
		),
	)
);
$footer_section['items'] = array(
	array(
		'id'     => 'copyright',
		'values' => array( 'copyright_text' => '© {current_year} نفحة — جميع الحقوق محفوظة.' ),
	),
);
set_theme_mod(
	'footer_placements',
	array(
		'current_section' => 'type-1',
		'sections'        => array( $footer_section ),
	)
);

// Footer widgets (rebuilt each run): brand blurb + two menu columns editable from Appearance → Menus.
update_option(
	'widget_block',
	array(
		1              => array(
			'content' => "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">نفحة</h3>\n<!-- /wp:heading -->\n<!-- wp:paragraph -->\n<p>عطور أصلية بالتقسيم في قطر. جرّب أرقى العطور بأحجام 5 و10 و30 مل قبل شراء العبوة الكاملة.</p>\n<!-- /wp:paragraph -->\n<!-- wp:paragraph -->\n<p>واتساب: <a href=\"https://wa.me/970598143863\" dir=\"ltr\">+970 59 814 3863</a></p>\n<!-- /wp:paragraph -->",
		),
		'_multiwidget' => 1,
	)
);
update_option(
	'widget_nav_menu',
	array(
		1              => array( 'title' => 'تسوّق', 'nav_menu' => $footer_shop ),
		2              => array( 'title' => 'مساعدة', 'nav_menu' => $footer_help ),
		'_multiwidget' => 1,
	)
);
$sidebars                        = get_option( 'sidebars_widgets', array() );
$sidebars['ct-footer-sidebar-1'] = array( 'block-1' );
$sidebars['ct-footer-sidebar-2'] = array( 'nav_menu-1' );
$sidebars['ct-footer-sidebar-3'] = array( 'nav_menu-2' );
update_option( 'sidebars_widgets', $sidebars );

// Product gallery: hover zoom (default) + tap-to-open lightbox, which is what works on phones.
set_theme_mod( 'has_product_single_zoom', 'yes' );
set_theme_mod( 'has_product_single_lightbox', 'yes' );

// Product page: similar / "you may also like" on every device, 2 per row on phones.
$all_devices = array( 'desktop' => true, 'tablet' => true, 'mobile' => true );
set_theme_mod( 'related_products_visibility', $all_devices );
set_theme_mod( 'upsell_products_visibility', $all_devices );
set_theme_mod(
	'woo_product_related_cards_columns',
	array(
		'desktop'   => 4,
		'tablet'    => 3,
		'mobile'    => 2,
		'__changed' => array( 'tablet', 'mobile' ),
	)
);

// Shop grid: 4 / 3 / 2 columns (phones need 2 to browse comfortably).
set_theme_mod(
	'blocksy_woo_columns',
	array(
		'desktop'   => 4,
		'tablet'    => 3,
		'mobile'    => 2,
		'__changed' => array( 'tablet', 'mobile' ),
	)
);

// Product card: brand (meta layer pointed at product_brand) above the title; no category line.
set_theme_mod(
	'woo_card_layout',
	array(
		array( 'id' => 'product_image', 'enabled' => true ),
		array(
			'id'       => 'product_meta',
			'enabled'  => true,
			'taxonomy' => 'product_brand',
			'style'    => 'simple',
			'__id'     => 'nafha-brand',
		),
		array( 'id' => 'product_title', 'enabled' => true, 'heading_tag' => 'h3' ), // h3: cards sit under section h2s.
		array( 'id' => 'product_rating', 'enabled' => true ),
		array( 'id' => 'product_price', 'enabled' => true ),
		array( 'id' => 'product_desc', 'enabled' => false ),
		array( 'id' => 'product_add_to_cart', 'enabled' => true ),
		array( 'id' => 'product_add_to_cart_and_price', 'enabled' => true ),
		array( 'id' => 'product_stock', 'enabled' => false ),
	)
);

// Blocksy caches its generated CSS; drop it so the new palette/header are rendered.
delete_transient( 'blocksy_dynamic_styles_descriptor' );

nafha_setup_log( 'palette, header, footer' );

/* --------------------------------------------------------------------- SEO */

WP_CLI::log( 'SEO (Rank Math)' );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Site icon (browser tab, Google results, Rank Math's organisation logo) from the child theme's PNG.
$icon_id = (int) get_option( 'site_icon' );
if ( ! $icon_id || ! get_post( $icon_id ) ) {
	$tmp = wp_tempnam( 'nafha-icon.png' );
	copy( get_stylesheet_directory() . '/assets/img/icon-512.png', $tmp );
	$icon_id = media_handle_sideload( array( 'name' => 'nafha-icon.png', 'tmp_name' => $tmp ), 0, 'نفحة' );
	update_option( 'site_icon', $icon_id );
}

if ( defined( 'RANK_MATH_VERSION' ) ) {
	// Only what the store uses: sitemap, structured data, WooCommerce SEO. (Analytics/AI/link counter add weight.)
	update_option( 'rank_math_modules', array( 'sitemap', 'rich-snippet', 'woocommerce' ) );

	$hero_id = (int) get_option( 'nafha_home_hero_id' );
	$titles  = array_merge(
		get_option( 'rank-math-options-titles', array() ),
		array(
			'title_separator'            => '|',
			'knowledgegraph_type'        => 'company',
			'knowledgegraph_name'        => 'نفحة',
			'website_name'               => 'نفحة',
			'knowledgegraph_logo'        => wp_get_attachment_url( $icon_id ),
			'knowledgegraph_logo_id'     => $icon_id,
			'open_graph_image'           => $hero_id ? wp_get_attachment_url( $hero_id ) : '',
			'open_graph_image_id'        => $hero_id,
			'homepage_title'             => 'نفحة %sep% عطور أصلية بالتقسيم في قطر — عينات 5 و10 و30 مل',
			'homepage_description'       => 'جرّب أشهر العطور العالمية والعربية بعينات أصلية 5 و10 و30 مل قبل شراء العبوة الكاملة. توصيل سريع لكل مناطق قطر وتوصيل مجاني فوق $60.',
			'pt_product_title'           => '%title% %sep% عطور أصلية بالتقسيم %sep% %sitename%',
			'pt_product_description'     => '%excerpt%',
			'tax_product_cat_title'      => '%term% بالتقسيم %sep% %sitename%',
			'tax_product_cat_description' => '%term_description%',
			'tax_product_brand_title'    => 'عطور %term% الأصلية بالتقسيم %sep% %sitename%',
			'tax_product_brand_description' => '%term_description%',
			'noindex_search'             => 'on',
			'noindex_empty_taxonomies'   => 'on',
		)
	);
	update_option( 'rank-math-options-titles', $titles );

	update_option(
		'rank-math-options-sitemap',
		array_merge(
			get_option( 'rank-math-options-sitemap', array() ),
			array(
				'authors_sitemap'           => 'off',
				'html_sitemap'              => 'off',
				'pt_product_sitemap'        => 'on',
				'pt_page_sitemap'           => 'on',
				'tax_product_cat_sitemap'   => 'on',
				'tax_product_brand_sitemap' => 'on',
				'include_images'            => 'on',
				'exclude_roles'             => array( 'contributor', 'subscriber', 'customer', 'shop_manager', 'administrator', 'author', 'editor' ),
			)
		)
	);

	// Keep crawlers out of the cart/checkout and the infinite filter combinations (filtered pages
	// already point their canonical at the unfiltered archive).
	$robots = implode(
		"\n",
		array(
			'User-agent: *',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'Disallow: /cart/',
			'Disallow: /checkout/',
			'Disallow: /my-account/',
			'Disallow: /*?*add-to-cart=',
			'Disallow: /*?*filter_',
			'Disallow: /*?*min_price=',
			'Disallow: /*?*orderby=',
			'',
			'Sitemap: ' . home_url( '/sitemap_index.xml' ),
		)
	);
	update_option(
		'rank-math-options-general',
		array_merge(
			get_option( 'rank-math-options-general', array() ),
			array(
				'robots_txt_content' => $robots,
				'breadcrumbs'        => 'off', // Blocksy renders breadcrumbs (with schema) already.
				'product_brand'      => 'product_brand', // Brand in Product schema = WooCommerce Brands.
			)
		)
	);

	// No setup wizard / account-connect nags for the client.
	update_option( 'rank_math_wizard_completed', true );
	update_option( 'rank_math_registration_skip', true );
	// Static front page: Rank Math reads the page's own SEO fields, not homepage_title.
	$front = (int) get_option( 'page_on_front' );
	update_post_meta( $front, 'rank_math_title', $titles['homepage_title'] );
	update_post_meta( $front, 'rank_math_description', $titles['homepage_description'] );

	// Shop page: a descriptive title instead of "Shop | Nafha".
	$shop_page = (int) get_option( 'woocommerce_shop_page_id' );
	update_post_meta( $shop_page, 'rank_math_title', 'تسوّق العطور الأصلية بالتقسيم %sep% %sitename%' );
	update_post_meta( $shop_page, 'rank_math_description', 'عطور رجالية ونسائية وللجنسين من أشهر الماركات بعينات 5 و10 و30 مل وعبوات كاملة. فلترة حسب العائلة العطرية والموسم والسعر، وتوصيل سريع في قطر.' );

	nafha_setup_log( 'Rank Math: modules, titles, sitemap, robots.txt' );
} else {
	WP_CLI::warning( 'Rank Math is not active — SEO options skipped.' );
}

// Brand archives are landing pages for "عطور ديور" style searches: give each a description.
$brand_descriptions = array(
	'dior'                     => 'عطور ديور الأصلية بالتقسيم: سوفاج، جادور وغيرها بعينات 5 و10 و30 مل، لتجرب أيقونات الدار الفرنسية قبل شراء العبوة الكاملة.',
	'chanel'                   => 'عطور شانيل الأصلية بالتقسيم: بلو دو شانيل، كوكو مدموازيل وغيرها بعينات أصلية مع توصيل سريع في قطر.',
	'tom-ford'                 => 'عطور توم فورد الفاخرة بالتقسيم، من عود وود إلى مجموعة العطور الخاصة، بعينات تناسب التجربة قبل الشراء.',
	'creed'                    => 'عطور كريد الأصلية بالتقسيم، وعلى رأسها أفينتوس، بعينات 5 و10 و30 مل وتوصيل سريع في قطر.',
	'yves-saint-laurent'       => 'عطور إيف سان لوران الأصلية بالتقسيم، مثل ليبر، بعينات تجربة بأسعار مناسبة.',
	'giorgio-armani'           => 'عطور جورجيو أرماني الأصلية بالتقسيم، مثل أكوا دي جيو، بعينات منعشة مثالية لصيف قطر.',
	'maison-francis-kurkdjian' => 'عطور ميزون فرانسيس كركدجيان بالتقسيم، وأشهرها باكارات روج 540، بعينات أصلية تجربها قبل العبوة الكاملة.',
	'parfums-de-marly'         => 'عطور بارفان دي مارلي الأصلية بالتقسيم، مثل لايتون، بعينات 5 و10 و30 مل.',
	'amouage'                  => 'عطور أمواج العُمانية الفاخرة بالتقسيم، مثل إنترلود مان، لعشّاق العطور الشرقية القوية.',
	'lattafa'                  => 'عطور لطافة العربية الأصلية، مثل خمرة، بعبوات كاملة وأسعار مميزة.',
);
foreach ( $brand_descriptions as $slug => $description ) {
	$term = get_term_by( 'slug', $slug, 'product_brand' );
	if ( $term && '' === trim( $term->description ) ) {
		wp_update_term( $term->term_id, 'product_brand', array( 'description' => $description ) );
	}
}
nafha_setup_log( 'brand descriptions, site icon' );

/* ------------------------------------------------- performance & security */

WP_CLI::log( 'Performance & security' );

// WooCommerce order attribution loads two scripts on every page; GA4 already reports traffic sources.
update_option( 'woocommerce_feature_order_attribution_enabled', 'no' );

// Server rules (Apache / LiteSpeed): compression, long browser cache for static files,
// security headers, and no access to xmlrpc.php or sensitive files.
require_once ABSPATH . 'wp-admin/includes/misc.php';
$htaccess_rules = array(
	'<IfModule mod_deflate.c>',
	'<IfModule mod_filter.c>', // AddOutputFilterByType lives in mod_filter; a missing module must not 500 the site.
	'	AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/xml application/rss+xml application/javascript text/javascript application/json image/svg+xml',
	'</IfModule>',
	'</IfModule>',
	'<IfModule mod_expires.c>',
	'	ExpiresActive On',
	'	ExpiresByType text/css "access plus 1 year"',
	'	ExpiresByType application/javascript "access plus 1 year"',
	'	ExpiresByType image/webp "access plus 1 year"',
	'	ExpiresByType image/png "access plus 1 year"',
	'	ExpiresByType image/jpeg "access plus 1 year"',
	'	ExpiresByType image/svg+xml "access plus 1 year"',
	'	ExpiresByType font/woff2 "access plus 1 year"',
	'</IfModule>',
	'<IfModule mod_headers.c>',
	'	# Versioned assets (?ver=) can be cached forever.',
	'	<FilesMatch "\.(css|js|woff2|webp|png|jpe?g|svg)$">',
	'		Header set Cache-Control "public, max-age=31536000, immutable"',
	'	</FilesMatch>',
	'	Header always set X-Content-Type-Options "nosniff"',
	'	Header always set X-Frame-Options "SAMEORIGIN"',
	'	Header always set Referrer-Policy "strict-origin-when-cross-origin"',
	'	Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"',
	'</IfModule>',
	'<Files xmlrpc.php>',
	'	Require all denied',
	'</Files>',
	'<FilesMatch "^(wp-config\.php|readme\.html|license\.txt|debug\.log)$">',
	'	Require all denied',
	'</FilesMatch>',
	'Options -Indexes',
);
$htaccess = ABSPATH . '.htaccess';
if ( insert_with_markers( $htaccess, 'Nafha', $htaccess_rules ) ) {
	// Our block must come before WordPress's rewrite block; insert_with_markers appends new blocks at the end.
	$contents = file_get_contents( $htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( preg_match( '/# BEGIN Nafha.*?# END Nafha\R?/s', $contents, $block ) && strpos( $contents, '# BEGIN Nafha' ) > strpos( $contents, '# BEGIN WordPress' ) ) {
		$contents = $block[0] . "\n" . str_replace( $block[0], '', $contents );
		file_put_contents( $htaccess, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	nafha_setup_log( '.htaccess: compression, caching, security headers' );
} else {
	WP_CLI::warning( '.htaccess is not writable — add the Nafha rules manually (see docs/DEPLOY.md).' );
}

// Login URL: /nafha-login/ instead of wp-login.php; /wp-admin for logged-out visitors → 404 (WPS Hide Login).
update_option( 'whl_page', 'nafha-login' );
update_option( 'whl_redirect_admin', '404' );

// Wordfence: lock out after 5 failed logins (4 h), instantly for non-existent usernames.
if ( class_exists( 'wfConfig' ) ) {
	foreach (
		array(
			'loginSecurityEnabled'       => 1,
			'loginSec_maxFailures'       => 5,
			'loginSec_maxForgotPasswd'   => 5,
			'loginSec_countFailMins'     => 240,
			'loginSec_lockoutMins'       => 240,
			'loginSec_lockInvalidUsers'  => 1,
			'loginSec_disableAuthorScan' => 1,
			'other_hideWPVersion'        => 1,
			'alertEmails'                => get_option( 'admin_email' ),
		) as $key => $value
	) {
		wfConfig::set( $key, $value );
	}
}

// Cloudflare Turnstile on login / register / password reset / reviews — not on checkout (no friction on orders).
// Local installs get Cloudflare's always-pass TEST keys; production needs the client's keys (docs/DEPLOY.md).
if ( 'local' === wp_get_environment_type() && ! get_option( 'cfturnstile_key' ) ) {
	update_option( 'cfturnstile_key', '1x00000000000000000000AA' );
	update_option( 'cfturnstile_secret', '1x0000000000000000000000000000000AA' );
	// The plugin only protects forms once keys pass its test (lock-out guard); test keys always pass.
	update_option( 'cfturnstile_tested', 'yes' );
}
foreach ( array( 'cfturnstile_login', 'cfturnstile_register', 'cfturnstile_reset', 'cfturnstile_comment', 'cfturnstile_woo_login', 'cfturnstile_woo_register', 'cfturnstile_woo_reset', 'cfturnstile_defer_scripts' ) as $option ) {
	update_option( $option, 1 );
}
update_option( 'cfturnstile_woo_checkout', 0 );
update_option( 'cfturnstile_language', 'ar' );
update_option( 'cfturnstile_appearance', 'interaction-only' ); // Invisible unless Cloudflare needs a click.

// Backups (UpdraftPlus): database daily (keep 14), files weekly (keep 4). Remote storage is connected on the live site.
update_option( 'updraft_interval_database', 'daily' );
update_option( 'updraft_retain_db', 14 );
update_option( 'updraft_interval', 'weekly' );
update_option( 'updraft_retain', 4 );

nafha_setup_log( 'login URL, Wordfence, Turnstile, backups' );

flush_rewrite_rules( false );
WP_CLI::success( 'Store setup complete.' );

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
	'woocommerce_enable_checkout_login_reminder' => 'yes',
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
	'woocommerce_coming_soon'              => 'no',
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
$brands = array(
	'dior'                     => 'Dior',
	'chanel'                   => 'Chanel',
	'tom-ford'                 => 'Tom Ford',
	'creed'                    => 'Creed',
	'yves-saint-laurent'       => 'Yves Saint Laurent',
	'giorgio-armani'           => 'Giorgio Armani',
	'maison-francis-kurkdjian' => 'Maison Francis Kurkdjian',
	'parfums-de-marly'         => 'Parfums de Marly',
	'amouage'                  => 'Amouage',
	'lattafa'                  => 'Lattafa',
);
foreach ( $brands as $slug => $name ) {
	nafha_setup_term( 'product_brand', $slug, $name );
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
	'fragrance-quiz'   => array( 'شو عطرك المناسب؟', $p( 'أجب عن بضعة أسئلة بسيطة وسنقترح عليك العطور الأنسب لذوقك.' ) ),
);
$page_ids = array();
foreach ( $pages as $slug => $def ) {
	$page_ids[ $slug ] = nafha_setup_page( $slug, $def[0], $def[1] );
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

$header = new Blocksy_Header_Builder();
set_theme_mod(
	'header_placements',
	array(
		'current_section' => 'type-1',
		'sections'        => array(
			$header->get_structure_for(
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
			),
		),
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

// Blocksy caches its generated CSS; drop it so the new palette/header are rendered.
delete_transient( 'blocksy_dynamic_styles_descriptor' );

nafha_setup_log( 'palette, header, footer' );

flush_rewrite_rules( false );
WP_CLI::success( 'Store setup complete.' );

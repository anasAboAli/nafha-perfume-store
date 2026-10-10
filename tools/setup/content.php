<?php
/**
 * Site content: home page layout, static pages' text, category images.
 * Run with:  tools/wp.sh eval-file tools/setup/content.php
 *
 * Unlike store-setup.php this OVERWRITES page content: run it once before
 * handover (and again only if you want to reset the pages). After that the
 * client edits pages in the block editor. Legal pages (terms, privacy,
 * shipping & returns) are a sound starting point but should be reviewed by
 * the client for their exact business terms.
 *
 * @package Nafha
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/* ---------------------------------------------------------- block helpers */

function nafha_b_p( $html, $class = '' ) {
	$attrs = $class ? ' {"className":"' . $class . '"}' : '';
	$cls   = $class ? ' class="' . $class . '"' : '';
	return "<!-- wp:paragraph{$attrs} -->\n<p{$cls}>{$html}</p>\n<!-- /wp:paragraph -->\n";
}

function nafha_b_h( $text, $level = 2, $class = '' ) {
	$json = array();
	if ( 2 !== $level ) {
		$json['level'] = $level;
	}
	if ( $class ) {
		$json['className'] = $class;
	}
	$attrs = $json ? ' ' . wp_json_encode( $json, JSON_UNESCAPED_UNICODE ) : '';
	$cls   = 'wp-block-heading' . ( $class ? ' ' . $class : '' );
	return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"{$cls}\">{$text}</h{$level}>\n<!-- /wp:heading -->\n";
}

function nafha_b_list( $items ) {
	$li = implode( "\n", array_map( static fn( $i ) => "<!-- wp:list-item -->\n<li>{$i}</li>\n<!-- /wp:list-item -->", $items ) );
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$li}</ul>\n<!-- /wp:list -->\n";
}

function nafha_b_shortcode( $code ) {
	return "<!-- wp:shortcode -->\n{$code}\n<!-- /wp:shortcode -->\n";
}

/**
 * Buttons: [ [label, url, outline?], … ].
 */
function nafha_b_buttons( $buttons, $class = '' ) {
	$out = '';
	foreach ( $buttons as $b ) {
		list( $label, $url ) = $b;
		$outline = ! empty( $b[2] );
		$attrs   = $outline ? ' {"className":"is-style-outline"}' : '';
		$cls     = 'wp-block-button' . ( $outline ? ' is-style-outline' : '' );
		$out    .= "<!-- wp:button{$attrs} -->\n<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $url ) . "\">{$label}</a></div>\n<!-- /wp:button -->\n";
	}
	$attrs = $class ? ' {"className":"' . $class . '"}' : '';
	$cls   = 'wp-block-buttons' . ( $class ? ' ' . $class : '' );
	return "<!-- wp:buttons{$attrs} -->\n<div class=\"{$cls}\">{$out}</div>\n<!-- /wp:buttons -->\n";
}

/**
 * Full-width section wrapper with a class for styling (nafha-child main.css).
 */
function nafha_b_section( $inner, $class ) {
	return "<!-- wp:group {\"align\":\"full\",\"className\":\"nafha-section {$class}\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group alignfull nafha-section {$class}\">{$inner}</div>\n<!-- /wp:group -->\n\n";
}

function nafha_b_columns( $columns, $class = '' ) {
	$cols  = implode( '', array_map( static fn( $c ) => "<!-- wp:column -->\n<div class=\"wp-block-column\">{$c}</div>\n<!-- /wp:column -->\n", $columns ) );
	$attrs = $class ? ' {"className":"' . $class . '"}' : '';
	$cls   = 'wp-block-columns' . ( $class ? ' ' . $class : '' );
	return "<!-- wp:columns{$attrs} -->\n<div class=\"{$cls}\">{$cols}</div>\n<!-- /wp:columns -->\n";
}

function nafha_b_details( $summary, $answer ) {
	return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>{$summary}</summary>" . nafha_b_p( $answer ) . "</details>\n<!-- /wp:details -->\n";
}

function nafha_set_content( $slug, $content ) {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		WP_CLI::warning( "Page {$slug} not found — run store-setup.php first." );
		return 0;
	}
	wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
	WP_CLI::log( "  {$slug}" );
	return $page->ID;
}

/* ------------------------------------------------------------- shared data */

$wa_number  = Nafha_Settings::whatsapp_number();
$wa_display = '‎+' . substr( $wa_number, 0, 3 ) . ' ' . substr( $wa_number, 3, 2 ) . ' ' . substr( $wa_number, 5, 3 ) . ' ' . substr( $wa_number, 8 );
$wa_link    = 'https://wa.me/' . $wa_number;
$free_min   = (int) Nafha_Shipping::free_shipping_min(); // From the shipping zones.
$shop_url   = wc_get_page_permalink( 'shop' );
$url        = static fn( $slug ) => get_permalink( get_page_by_path( $slug ) );
$cat_url    = static fn( $slug ) => get_term_link( $slug, 'product_cat' );

/* -------------------------------------------------------- category images */

WP_CLI::log( 'Category images' );

// Reuse demo product photos as category thumbnails until the client supplies their own.
$category_images = array(
	'men-perfumes'    => 'dior-sauvage-edp-1.webp',
	'women-perfumes'  => 'chanel-coco-mademoiselle-1.webp',
	'unisex-perfumes' => 'mfk-baccarat-rouge-540-1.webp',
	'gift-sets'       => 'women-gift-set-1.webp',
	'full-bottles'    => 'lattafa-khamrah-1.webp',
);
foreach ( $category_images as $slug => $file ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	$att  = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_nafha_demo_image', 'meta_value' => $file, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $term && $att ) {
		update_term_meta( $term->term_id, 'thumbnail_id', $att[0] );
	}
}

/* ------------------------------------------------------------- hero image */

$hero_id = (int) get_option( 'nafha_home_hero_id' );
if ( ! $hero_id || ! get_post( $hero_id ) ) {
	$src = dirname( __DIR__ ) . '/images/out/home-hero.webp';
	$tmp = wp_tempnam( 'home-hero.webp' );
	copy( $src, $tmp );
	$hero_id = media_handle_sideload( array( 'name' => 'home-hero.webp', 'tmp_name' => $tmp ), 0, 'نفحة — عطور أصلية بالتقسيم' );
	update_option( 'nafha_home_hero_id', $hero_id );
	update_post_meta( $hero_id, '_wp_attachment_image_alt', 'مجموعة عبوات عطور تجربة بألوان مختلفة' );
}
$hero_url = wp_get_attachment_url( $hero_id );

/* --------------------------------------------------------------- home page */

WP_CLI::log( 'Pages' );

$cat_ids = implode(
	',',
	array_filter(
		array_map( static fn( $s ) => get_term_by( 'slug', $s, 'product_cat' )->term_id ?? 0, array( 'men-perfumes', 'women-perfumes', 'unisex-perfumes', 'gift-sets' ) )
	)
);

$section_head = static fn( $title, $link_label, $link_url ) =>
	"<!-- wp:group {\"className\":\"nafha-section-head\",\"layout\":{\"type\":\"flex\",\"justifyContent\":\"space-between\"}} -->\n<div class=\"wp-block-group nafha-section-head\">" .
	nafha_b_h( $title ) .
	nafha_b_p( '<a href="' . esc_url( $link_url ) . '">' . $link_label . '</a>', 'nafha-section-link' ) .
	"</div>\n<!-- /wp:group -->\n";

$home  = "<!-- wp:cover {\"url\":\"" . esc_url( $hero_url ) . "\",\"id\":{$hero_id},\"dimRatio\":20,\"overlayColor\":\"black\",\"minHeight\":620,\"contentPosition\":\"center right\",\"align\":\"full\",\"className\":\"nafha-hero\"} -->\n";
$home .= "<div class=\"wp-block-cover alignfull has-custom-content-position is-position-center-right nafha-hero\" style=\"min-height:620px\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-black-background-color has-background-dim-20 has-background-dim\"></span><img class=\"wp-block-cover__image-background wp-image-{$hero_id}\" alt=\"\" src=\"" . esc_url( $hero_url ) . "\" data-object-fit=\"cover\"/><div class=\"wp-block-cover__inner-container\">";
$home .= nafha_b_p( 'عطور أصلية 100% بالتقسيم', 'nafha-kicker' );
$home .= nafha_b_h( 'جرّب أرقى العطور قبل أن تشتري العبوة الكاملة', 1 );
$home .= nafha_b_p( 'عينات 5 و10 و30 مل من عطورك المفضلة، مقسّمة من العبوة الأصلية، مع توصيل سريع لكل مناطق قطر.' );
$home .= nafha_b_buttons( array( array( 'تسوّق العطور', $shop_url ), array( 'شو عطرك المناسب؟', $url( 'fragrance-quiz' ), true ) ) );
$home .= "</div></div>\n<!-- /wp:cover -->\n\n";

$home .= nafha_b_section(
	nafha_b_h( 'تسوّق حسب الفئة' ) . nafha_b_shortcode( '[product_categories ids="' . $cat_ids . '" columns="4" orderby="include" hide_empty="0"]' ),
	'nafha-categories'
);
$home .= nafha_b_section(
	$section_head( 'الأكثر مبيعًا', 'عرض الكل', add_query_arg( 'orderby', 'popularity', $shop_url ) ) .
	nafha_b_shortcode( '[products limit="4" columns="4" best_selling="true"]' ),
	'nafha-best-sellers'
);
$home .= nafha_b_section(
	nafha_b_columns(
		array(
			nafha_b_p( 'مش متأكد من اختيارك؟', 'nafha-kicker' ) .
			nafha_b_h( 'اكتشف عطرك المناسب خلال دقيقة' ) .
			nafha_b_p( 'ست أسئلة بسيطة عن ذوقك ومناسباتك وميزانيتك، ونقترح عليك العطور الأقرب لك مع نسبة التطابق.' ),
			nafha_b_buttons( array( array( 'ابدأ الاختبار', $url( 'fragrance-quiz' ) ) ), 'nafha-band-actions' ),
		),
		'nafha-band'
	),
	'nafha-quiz-band'
);
$home .= nafha_b_section(
	$section_head( 'وصل حديثًا', 'تصفّح المتجر', add_query_arg( 'orderby', 'date', $shop_url ) ) .
	nafha_b_shortcode( '[products limit="4" columns="4" orderby="date" order="DESC" category="gift-sets" cat_operator="NOT IN"]' ),
	'nafha-new'
);
$home .= nafha_b_section(
	nafha_b_columns(
		array(
			nafha_b_p( 'هدايا وصناديق تجربة', 'nafha-kicker' ) .
			nafha_b_h( 'جرّب أكثر من عطر ووفّر حتى 20%' ) .
			nafha_b_p( 'صمّم صندوق التجربة الخاص بك من 3 أو 5 عطور بسعة 5 أو 10 مل، أو اختر من باقات الهدايا الجاهزة المغلّفة بعناية.' ) .
			nafha_b_buttons( array( array( 'صمّم صندوقك', $url( 'discovery-box' ) ), array( 'باقات الهدايا', $cat_url( 'gift-sets' ), true ) ) ),
			nafha_b_shortcode( '[products limit="2" columns="2" category="gift-sets"]' ),
		)
	),
	'nafha-gifts'
);
$home .= nafha_b_section(
	nafha_b_h( 'لماذا نفحة؟', 2, 'has-text-align-center' ) .
	nafha_b_columns(
		array(
			nafha_b_h( 'أصلي 100%', 3 ) . nafha_b_p( 'نقسّم مباشرة من العبوات الأصلية المشتراة من مصادر موثوقة، ولا نبيع أي عطر مقلّد.' ),
			nafha_b_h( 'تقسيم نظيف ومعقّم', 3 ) . nafha_b_p( 'عبوات زجاجية جديدة ومعقّمة مع بخّاخ محكم، تحافظ على العطر كما هو.' ),
			nafha_b_h( 'توصيل سريع في قطر', 3 ) . nafha_b_p( 'خلال 24 ساعة داخل الدوحة، وتوصيل مجاني للطلبات فوق $' . $free_min . '.' ),
			nafha_b_h( 'جرّب قبل أن تشتري', 3 ) . nafha_b_p( 'عينة 5 مل تكفي لأيام من التجربة قبل أن تقرر شراء العبوة الكاملة.' ),
		),
		'nafha-features'
	),
	'nafha-why'
);
$home .= nafha_b_section(
	nafha_b_h( 'آراء عملائنا', 2, 'has-text-align-center' ) . nafha_b_shortcode( '[nafha_reviews limit="3"]' ),
	'nafha-testimonials'
);
$home .= nafha_b_section(
	nafha_b_h( 'عندك سؤال عن عطر معيّن؟', 2, 'has-text-align-center' ) .
	nafha_b_p( 'فريقنا يرد عليك على واتساب يوميًا من 10 صباحًا حتى 10 مساءً، ويساعدك تختار العطر المناسب.', 'has-text-align-center' ) .
	nafha_b_buttons( array( array( 'راسلنا على واتساب', $wa_link ) ), 'is-content-justification-center nafha-wa-buttons' ),
	'nafha-contact-band'
);

$home_id = nafha_set_content( 'home', $home );
// Home: no page title, normal width, no vertical padding (sections bring their own spacing).
update_post_meta(
	$home_id,
	'blocksy_post_meta_options',
	array(
		'has_hero_section'        => 'disabled',
		'page_structure_type'     => 'type-4',
		'content_style_source'    => 'custom',
		'content_style'           => 'wide',
		'vertical_spacing_source' => 'custom',
		'content_area_spacing'    => 'none',
	)
);

/* ------------------------------------------------------------ about */

nafha_set_content(
	'about',
	nafha_b_p( '<strong>نفحة</strong> متجر قطري متخصص في تقسيم العطور الأصلية. بدأت الفكرة من سؤال بسيط: لماذا ندفع ثمن عبوة كاملة لعطر لم نجرّبه بعد على بشرتنا؟' ) .
	nafha_b_p( 'العطر تجربة شخصية جدًا؛ رائحته على الورق أو في المتجر تختلف عن رائحته على بشرتك بعد ساعات. لذلك نوفّر لك أرقى العطور العالمية والعربية بأحجام 5 و10 و30 مل، لتعيش مع العطر أيامًا قبل أن تقرر.' ) .
	nafha_b_h( 'كيف نعمل؟' ) .
	nafha_b_list(
		array(
			'نشتري العبوات الأصلية الكاملة من موزعين ومصادر موثوقة.',
			'نقسّم العطر يدويًا في عبوات زجاجية جديدة ومعقّمة، مع بخّاخ محكم الإغلاق.',
			'نلصق على كل عبوة اسم العطر وتركيزه وتاريخ التقسيم.',
			'نغلّف الطلب بعناية ونوصله لك في أسرع وقت داخل قطر.',
		)
	) .
	nafha_b_h( 'وعدنا لك' ) .
	nafha_b_p( 'عطور أصلية 100% فقط، أسعار واضحة، وخدمة شخصية تساعدك تكتشف عطرك المميز. وإذا لم تكن متأكدًا من اختيارك، جرّب <a href="' . esc_url( $url( 'fragrance-quiz' ) ) . '">اختبار شو عطرك المناسب؟</a> أو راسلنا على واتساب.' ) .
	nafha_b_p( '<em>نفحة متجر مستقل لتقسيم العطور، وغير تابع للعلامات التجارية المذكورة. جميع الأسماء والعلامات التجارية ملك لأصحابها، وتُستخدم فقط لتعريف العطر الأصلي الذي تم التقسيم منه.</em>', 'nafha-note' )
);

/* ---------------------------------------------------------- contact */

nafha_set_content(
	'contact',
	nafha_b_p( 'يسعدنا تواصلك معنا لأي سؤال عن العطور، أو لمساعدتك في اختيار العطر المناسب، أو للاستفسار عن طلبك.' ) .
	nafha_b_columns(
		array(
			nafha_b_h( 'واتساب', 3 ) . nafha_b_p( 'أسرع طريقة للتواصل معنا.' ) . nafha_b_p( '<a href="' . esc_url( $wa_link ) . '" dir="ltr">' . $wa_display . '</a>' ) .
			nafha_b_buttons( array( array( 'راسلنا على واتساب', $wa_link ) ), 'nafha-wa-buttons' ),
			nafha_b_h( 'أوقات العمل', 3 ) . nafha_b_p( 'يوميًا من 10 صباحًا حتى 10 مساءً.<br>الطلبات تُستقبل على الموقع على مدار الساعة.' ) .
			nafha_b_h( 'التوصيل', 3 ) . nafha_b_p( 'نوصل لجميع مناطق قطر. التفاصيل في <a href="' . esc_url( $url( 'shipping-returns' ) ) . '">سياسة الشحن والاسترجاع</a>.' ),
		)
	)
);

/* -------------------------------------------------------------- FAQ */

nafha_set_content(
	'faq',
	nafha_b_h( 'عن العطور' ) .
	nafha_b_details( 'هل العطور أصلية؟', 'نعم، جميع عطورنا أصلية 100%. نقسّمها مباشرة من العبوات الأصلية الكاملة المشتراة من مصادر موثوقة، ولا نبيع أي عطر مقلّد أو مستوحى.' ) .
	nafha_b_details( 'ما معنى "عطور بالتقسيم"؟', 'نأخذ كمية صغيرة من العبوة الأصلية ونضعها في عبوة زجاجية أصغر (5 أو 10 أو 30 مل) مع بخّاخ، لتجرب العطر بسعر مناسب قبل شراء العبوة الكاملة.' ) .
	nafha_b_details( 'هل تختلف رائحة العينة عن العبوة الأصلية؟', 'لا، هو نفس العطر تمامًا. نستخدم عبوات زجاجية نظيفة ومعقّمة حتى لا تتأثر الرائحة أو الثبات.' ) .
	nafha_b_details( 'كم تكفي عينة 5 مل؟', 'تكفي تقريبًا 50 إلى 70 رشّة، أي أسبوعين إلى ثلاثة أسابيع من الاستخدام اليومي، وهي مدة كافية لتعرف إن كان العطر يناسبك.' ) .
	nafha_b_details( 'كيف أعرف العطر المناسب لي؟', 'جرّب <a href="' . esc_url( $url( 'fragrance-quiz' ) ) . '">اختبار شو عطرك المناسب؟</a>، أو صمّم <a href="' . esc_url( $url( 'discovery-box' ) ) . '">صندوق تجربة</a> بعدة عطور، أو راسلنا على واتساب ونساعدك.' ) .
	nafha_b_h( 'الطلب والدفع' ) .
	nafha_b_details( 'ما طرق الدفع المتاحة؟', 'الدفع عند الاستلام (نقدًا أو بالبطاقة لمندوب التوصيل)، أو التحويل البنكي. يمكنك أيضًا تأكيد طلبك عبر واتساب.' ) .
	nafha_b_details( 'هل يجب أن أنشئ حسابًا؟', 'لا، يمكنك الطلب كضيف بالاسم ورقم الجوال والعنوان فقط. الحساب اختياري لمتابعة طلباتك السابقة.' ) .
	nafha_b_details( 'هل توجد خصومات؟', 'نعم: خصم 10% عند شراء 3 عينات و15% عند شراء 5 عينات، وخصم حتى 20% على صناديق التجربة، وتوصيل مجاني للطلبات فوق $' . $free_min . '.' ) .
	nafha_b_h( 'التوصيل' ) .
	nafha_b_details( 'إلى أين توصلون؟', 'نوصل لجميع مناطق قطر: الدوحة خلال 24 ساعة، محيط الدوحة خلال 24–48 ساعة، والمناطق الخارجية خلال 2–3 أيام.' ) .
	nafha_b_details( 'كم تكلفة التوصيل؟', 'تبدأ من $4 داخل الدوحة، والتوصيل مجاني لكل الطلبات التي تزيد عن $' . $free_min . '. التفاصيل في <a href="' . esc_url( $url( 'shipping-returns' ) ) . '">سياسة الشحن والاسترجاع</a>.' ) .
	nafha_b_details( 'هل يمكن إرسال الطلب كهدية؟', 'بالتأكيد. اكتب اسم المستلم وعنوانه، وأضف رسالة الإهداء في خانة الملاحظات وسنرفقها مع الطلب.' )
);

/* ------------------------------------------------ shipping & returns */

$zones_rows = '';
foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
	$states = WC()->countries->get_states( 'QA' );
	$areas  = implode( '، ', array_map( static fn( $l ) => $states[ explode( ':', $l->code )[1] ] ?? $l->code, $zone['zone_locations'] ) );
	$cost   = '';
	$time   = '';
	foreach ( $zone['shipping_methods'] as $method ) {
		if ( 'flat_rate' === $method->id && 'yes' === $method->enabled ) {
			$cost = wp_strip_all_tags( wc_price( $method->get_option( 'cost' ), array( 'decimals' => 0 ) ) );
			$time = preg_match( '/\((.+)\)/u', $method->get_option( 'title' ), $m ) ? $m[1] : '';
		}
	}
	$zones_rows .= '<tr><td>' . esc_html( $zone['zone_name'] ) . '</td><td>' . esc_html( $areas ) . '</td><td>' . esc_html( $cost ) . '</td><td>' . esc_html( $time ) . '</td></tr>';
}

nafha_set_content(
	'shipping-returns',
	nafha_b_h( 'مناطق التوصيل والأسعار' ) .
	"<!-- wp:table {\"className\":\"nafha-table\"} -->\n<figure class=\"wp-block-table nafha-table\"><table><thead><tr><th>المنطقة</th><th>تشمل</th><th>السعر</th><th>مدة التوصيل</th></tr></thead><tbody>{$zones_rows}</tbody></table></figure>\n<!-- /wp:table -->\n" .
	nafha_b_p( '<strong>التوصيل مجاني لكل الطلبات التي تزيد عن $' . $free_min . '</strong>، في جميع المناطق.' ) .
	nafha_b_list(
		array(
			'يبدأ تجهيز الطلب بعد تأكيده، وقد نتواصل معك هاتفيًا أو عبر واتساب قبل التوصيل.',
			'الطلبات المؤكدة قبل الساعة 6 مساءً داخل الدوحة تُوصل غالبًا في نفس اليوم أو صباح اليوم التالي.',
			'في حال الدفع بالتحويل البنكي يُشحن الطلب بعد تأكيد وصول المبلغ.',
		)
	) .
	nafha_b_h( 'الاسترجاع والاستبدال' ) .
	nafha_b_p( 'حرصًا على النظافة وسلامة المنتج، <strong>لا يمكن استرجاع العينات المقسّمة بعد فتحها أو استخدامها</strong>.' ) .
	nafha_b_list(
		array(
			'إذا وصلتك عبوة مكسورة أو تالفة أو عطر مختلف عمّا طلبت، أبلغنا عبر واتساب خلال 48 ساعة من الاستلام مع صورة، وسنستبدلها مجانًا.',
			'العبوات الكاملة المغلقة يمكن استرجاعها خلال 7 أيام من الاستلام بشرط أن تكون بغلافها الأصلي دون فتح.',
			'يُرد المبلغ بنفس طريقة الدفع أو كرصيد في المتجر خلال 7 أيام عمل من استلام المنتج المرتجع.',
		)
	) .
	nafha_b_p( 'لأي استفسار عن الشحن أو الاسترجاع <a href="' . esc_url( $wa_link ) . '">راسلنا على واتساب</a>.' )
);

/* -------------------------------------------------------------- terms */

nafha_set_content(
	'terms',
	nafha_b_p( 'باستخدامك موقع نفحة وإتمامك أي طلب، فإنك توافق على الشروط التالية. يرجى قراءتها بعناية.' ) .
	nafha_b_h( '1. المنتجات' ) .
	nafha_b_list(
		array(
			'نبيع عطورًا أصلية مقسّمة من العبوات الأصلية في عبوات أصغر، إضافة إلى عبوات كاملة وباقات هدايا.',
			'نفحة متجر مستقل وغير تابع لأي من العلامات التجارية المعروضة؛ تُستخدم الأسماء فقط لتعريف العطر الأصلي.',
			'الصور توضيحية، وقد يختلف شكل العبوة أو لونها قليلًا عن الصورة.',
			'وصف الثبات والانتشار تقديري، ويختلف أداء العطر من شخص لآخر حسب طبيعة البشرة والطقس.',
		)
	) .
	nafha_b_h( '2. الأسعار والدفع' ) .
	nafha_b_list(
		array(
			'الأسعار معروضة بالدولار الأمريكي، وتشمل تكلفة التقسيم والعبوة. تكلفة التوصيل تظهر قبل تأكيد الطلب.',
			'نحتفظ بحق تعديل الأسعار في أي وقت، دون أن يؤثر ذلك على الطلبات المؤكدة.',
			'في حال حدوث خطأ واضح في السعر سنتواصل معك قبل تنفيذ الطلب.',
		)
	) .
	nafha_b_h( '3. الطلبات' ) .
	nafha_b_list(
		array(
			'يُعتبر الطلب مؤكدًا بعد استلامنا له وتأكيده معك عند الحاجة.',
			'يحق لنا إلغاء أي طلب في حال عدم توفر المنتج أو تعذّر التواصل مع العميل، مع رد أي مبلغ مدفوع كاملًا.',
			'العروض والخصومات لا تُجمع إلا إذا ذُكر ذلك صراحةً.',
		)
	) .
	nafha_b_h( '4. التوصيل والاسترجاع' ) .
	nafha_b_p( 'تخضع لـ<a href="' . esc_url( $url( 'shipping-returns' ) ) . '">سياسة الشحن والاسترجاع</a>.' ) .
	nafha_b_h( '5. الاستخدام الآمن' ) .
	nafha_b_p( 'العطور للاستخدام الخارجي فقط. يُنصح بتجربة العطر على مساحة صغيرة من البشرة أولًا، وإبعاده عن الأطفال ومصادر الحرارة.' ) .
	nafha_b_h( '6. التواصل' ) .
	nafha_b_p( 'لأي استفسار حول هذه الشروط تواصل معنا عبر <a href="' . esc_url( $wa_link ) . '">واتساب</a>.' )
);

/* ------------------------------------------------------------ privacy */

$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
if ( $privacy_id ) {
	wp_update_post(
		array(
			'ID'           => $privacy_id,
			'post_content' =>
				nafha_b_p( 'نحترم خصوصيتك، ونجمع فقط البيانات اللازمة لتنفيذ طلبك وتحسين تجربتك.' ) .
				nafha_b_h( 'البيانات التي نجمعها' ) .
				nafha_b_list(
					array(
						'عند الطلب: الاسم، رقم الجوال، المنطقة والعنوان، والبريد الإلكتروني إن أدخلته، وتفاصيل الطلب.',
						'عند التواصل معنا عبر واتساب: رقمك ومحتوى المحادثة.',
						'عند تصفّح الموقع: بيانات تقنية مجهولة (نوع الجهاز، الصفحات التي زرتها) عبر ملفات تعريف الارتباط وأدوات التحليل.',
					)
				) .
				nafha_b_h( 'كيف نستخدم بياناتك' ) .
				nafha_b_list(
					array(
						'تنفيذ الطلب وتوصيله والتواصل معك بخصوصه.',
						'الرد على استفساراتك وتقديم الدعم.',
						'تحسين الموقع والعروض من خلال إحصاءات مجمّعة لا تحدد هويتك.',
					)
				) .
				nafha_b_h( 'مشاركة البيانات' ) .
				nafha_b_p( 'لا نبيع بياناتك لأي جهة. نشارك فقط ما يلزم مع شركة التوصيل لإيصال الطلب، ومع مزودي الخدمات التقنية (الاستضافة، أدوات التحليل والإعلانات مثل Google وMeta) وفق سياساتهم.' ) .
				nafha_b_h( 'ملفات تعريف الارتباط (الكوكيز)' ) .
				nafha_b_p( 'نستخدم ملفات ضرورية لعمل السلة والطلب، وملفات تحليلية وإعلانية لقياس أداء الموقع. يمكنك التحكم بها من إعدادات متصفحك.' ) .
				nafha_b_h( 'حقوقك' ) .
				nafha_b_p( 'يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها في أي وقت عبر التواصل معنا على <a href="' . esc_url( $wa_link ) . '">واتساب</a>.' ),
		)
	);
	WP_CLI::log( '  privacy-policy' );
}

flush_rewrite_rules( false );
WP_CLI::success( 'Content updated.' );

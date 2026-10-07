<?php
/**
 * داده‌ی آزمون برگه‌ی «درباره ما» (`/about/`).
 *
 * چه می‌سازد (عیناً ساختار `cinora/about.html`، ولی با سازه‌های وردپرس):
 *   - `.about-values` — سه کارت؛ هر کارت یک بلوک `core/group` با
 *     `tagName:"section"` که یک `h2` و یک پاراگراف دارد.
 *   - `.about-story` — یک `h2`، دو پاراگراف (پاراگراف دوم `muted`) و دکمه‌ی
 *     `manacore/cta-link`.
 *
 * قالب از نامک می‌آید (`page-about.html`)، پس متایی برای «انتخاب قالب» لازم
 * نیست و مدیر می‌تواند برگه را در ویرایشگر برگه و قالب را در ویرایشگر سایت
 * ویرایش کند.
 *
 * اجرا:
 *   wp --path=/home/user/.cache/wp eval-file …/seed-about.php
 *   wp --path=/home/user/.cache/wp eval-file …/seed-about.php restore
 *
 * @package KooheFilm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mc_args    = isset( $args ) ? (array) $args : array();
$mc_restore = in_array( 'restore', $mc_args, true );
$mc_backup  = 'manacore_qa_about_backup';

/* -------------------------------------------------------------------------
 * ۱) برگشت به حالت پیشین
 * ---------------------------------------------------------------------- */
if ( $mc_restore ) {
	$mc_saved = get_option( $mc_backup );

	if ( ! is_array( $mc_saved ) ) {
		echo "about restore ok — nothing to restore\n";
		return;
	}

	$mc_post = get_post( (int) $mc_saved['ID'] );

	if ( $mc_post ) {
		if ( ! empty( $mc_saved['created'] ) ) {
			wp_delete_post( (int) $mc_saved['ID'], true );
		} else {
			wp_update_post(
				array(
					'ID'           => (int) $mc_saved['ID'],
					'post_content' => (string) $mc_saved['post_content'],
					'post_excerpt' => (string) $mc_saved['post_excerpt'],
				)
			);
		}
	}

	delete_option( $mc_backup );
	echo "about restore ok — about page restored\n";
	return;
}

/* -------------------------------------------------------------------------
 * ۲) ساخت متن برگه (همان ساختار مرجع)
 * ---------------------------------------------------------------------- */
$mc_values = array(
	array( 'کشف، نه فقط جستجو', 'از میان ژانرها و حال‌وهواهای مختلف، داستانی را پیدا کن که با تو حرف می‌زند.' ),
	array( 'به اندازه خودت، شخصی', 'با هر انتخاب، پیشنهادها و تحلیل‌ها به سلیقه واقعی تو نزدیک‌تر می‌شوند.' ),
	array( 'هر جا، در هر قاب', 'یک تجربه راست‌به‌چپ، واکنش‌گرا و هماهنگ روی تمام نمایشگرها.' ),
);

$mc_cards = '';

foreach ( $mc_values as $mc_card ) {
	$mc_cards .= sprintf(
		"<!-- wp:group {\"tagName\":\"section\",\"layout\":{\"type\":\"constrained\",\"contentSize\":\"100%%\"}} -->\n<section class=\"wp-block-group\"><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%1\$s</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>%2\$s</p>\n<!-- /wp:paragraph --></section>\n<!-- /wp:group -->\n\n",
		esc_html( $mc_card[0] ),
		esc_html( $mc_card[1] )
	);
}

$mc_content = sprintf(
	"<!-- wp:group {\"className\":\"about-values\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group about-values\">\n\n%1\$s</div>\n<!-- /wp:group -->\n\n" .
	"<!-- wp:group {\"className\":\"about-story\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group about-story\">\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">هر شب، فرصتی برای یک دنیای تازه.</h2>\n<!-- /wp:heading -->\n\n" .
	"<!-- wp:paragraph -->\n<p>ما کوهه را برای آن‌هایی ساختیم که سینما را فقط سرگرمی نمی‌دانند؛ برای آدم‌هایی که در هر داستان، چیزی از خودشان پیدا می‌کنند. طراحی متفاوت، جزئیات فکرشده و تجربه‌ای آرام، قرار است فاصله میان تو و داستان بعدی‌ات را کمتر کند.</p>\n<!-- /wp:paragraph -->\n\n" .
	"<!-- wp:paragraph {\"className\":\"muted\"} -->\n<p class=\"muted\">این نسخه، نمایشی است و با ویدئوهای آزاد کار می‌کند؛ اما حساب کاربری، لیست تماشا، دیدگاه‌ها و داده‌های شخصی آن واقعی و قابل ذخیره‌اند.</p>\n<!-- /wp:paragraph -->\n\n" .
	"<!-- wp:manacore/cta-link {\"label\":\"دنیای کوهه را کشف کن\",\"url\":\"/browse/\",\"variant\":\"primary\",\"showChevron\":false} /-->\n\n</div>\n<!-- /wp:group -->",
	$mc_cards
);

/* -------------------------------------------------------------------------
 * ۳) نوشتن روی برگه (بدون ترش‌کردن محتوای پیشین در پایان)
 * ---------------------------------------------------------------------- */
$mc_existing = get_page_by_path( 'about' );

$mc_snapshot = $mc_existing
	? array(
		'ID'           => (int) $mc_existing->ID,
		'post_content' => (string) $mc_existing->post_content,
		'post_excerpt' => (string) $mc_existing->post_excerpt,
		'created'      => false,
	)
	: array( 'created' => true );

$mc_id = $mc_existing
	? (int) $mc_existing->ID
	: (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'درباره ما',
			'post_name'   => 'about',
		)
	);

if ( ! $mc_id || is_wp_error( $mc_id ) ) {
	echo "about seed failed — page not created\n";
	return;
}

wp_update_post(
	array(
		'ID'           => $mc_id,
		'post_status'  => 'publish',
		'post_content' => $mc_content,
		'post_excerpt' => 'کوهه یک تجربه‌ی فارسی، خلاق و یکپارچه برای کشف دنیای فیلم و سریال است.',
	)
);

update_option( $mc_backup, $mc_snapshot, false );

printf(
	"about seed ok — values=%d · story=%d · page=%d\n",
	count( $mc_values ),
	2,
	$mc_id
);

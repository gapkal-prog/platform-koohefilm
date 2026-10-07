<?php
/**
 * داده‌ی آزمون برگه‌های «راهنما» و «قوانین و حریم خصوصی».
 *
 * چه می‌سازد:
 *   - برگه‌ی «راهنما» (`help`) با شش پرسش در بلوک‌های بومی `core/details`
 *     (هر پرسش کلاس `faq-item` دارد) — همان شش پرسش مرجع در
 *     `cinora/assets/js/help.js`.
 *   - برگه‌ی «قوانین و حریم خصوصی» (`privacy`) با هفت بند، هر بند یک بلوک
 *     `core/group` با `tagName:"section"` و یک `h2` + پاراگراف.
 *
 * قالب‌ها از نامک می‌آیند (`page-help.html` و `page-privacy.html`)، پس هیچ
 * متایی برای «انتخاب قالب» لازم نیست و مدیر می‌تواند هر دو برگه را در
 * ویرایشگر سایت ویرایش کند.
 *
 * اجرا:
 *   wp --path=/home/user/.cache/wp eval-file …/seed-info.php
 *   wp --path=/home/user/.cache/wp eval-file …/seed-info.php restore
 *
 * @package KooheFilm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mc_args    = isset( $args ) ? (array) $args : array();
$mc_restore = in_array( 'restore', $mc_args, true );
$mc_backup  = 'manacore_qa_info_backup';

/* -------------------------------------------------------------------------
 * ۱) برگشت به حالت پیشین
 * ---------------------------------------------------------------------- */
if ( $mc_restore ) {
	$mc_saved = get_option( $mc_backup );

	if ( ! is_array( $mc_saved ) ) {
		echo "info restore ok — nothing to restore\n";
		return;
	}

	foreach ( (array) $mc_saved['pages'] as $mc_page ) {
		$mc_post = get_post( (int) $mc_page['ID'] );

		if ( ! $mc_post ) {
			continue;
		}

		if ( ! empty( $mc_page['created'] ) ) {
			wp_delete_post( (int) $mc_page['ID'], true );
			continue;
		}

		wp_update_post(
			array(
				'ID'           => (int) $mc_page['ID'],
				'post_content' => (string) $mc_page['post_content'],
				'post_excerpt' => (string) $mc_page['post_excerpt'],
			)
		);
	}

	delete_option( $mc_backup );
	echo "info restore ok — info pages restored\n";
	return;
}

/* -------------------------------------------------------------------------
 * ۲) ساخت (یا به‌روزرسانی) برگه‌ها
 * ---------------------------------------------------------------------- */
$mc_faq = array(
	array( 'چطور حساب کاربری بسازم؟', 'از سربرگ روی «ورود / عضویت» بزن. نام، ایمیل معتبر و یک رمز حداقل ۸ کاراکتری وارد کن. پس از ثبت‌نام، علاقه‌مندی‌های مهمان به حسابت منتقل می‌شوند.' ),
	array( 'چطور فیلم یا سریال را ذخیره کنم؟', 'روی نشان ذخیره کنار پوستر یا دکمه «لیست تماشا» در صفحه‌ی اثر بزن. لیستت از بخش حساب کاربری در دسترس است و حتی برای مهمان هم روی همین مرورگر ذخیره می‌شود.' ),
	array( 'چطور قسمت و کیفیت را انتخاب کنم؟', 'در صفحه‌ی سریال به «فصل‌ها و قسمت‌ها» برو، فصل را انتخاب کن و قسمت دلخواه را باز کن. تمام فایل‌های این نسخه، ویدئوهای نمونه‌ی آزاد هستند.' ),
	array( 'چطور اسپویل را در دیدگاه پنهان کنم؟', 'بخشی از متن دیدگاه را انتخاب کن و دکمه «اسپویل در متن» را بزن. برای ارسال دیدگاه باید وارد حساب شوی.' ),
	array( 'تحلیل سلیقه از چه داده‌ای استفاده می‌کند؟', 'ژانر آثار ذخیره‌شده، پیشرفت تماشای نمونه‌ها و امتیازهای تو به‌صورت وزن‌دار تحلیل می‌شوند. نمودارها تا قبل از اولین فعالیت، خالی می‌مانند.' ),
	array( 'آیا اشتراک و پرداخت واقعی است؟', 'خیر. این پلتفرم یک نسخه‌ی نمایشی کامل است. پرداخت بانکی ندارد، هیچ مبلغی دریافت نمی‌کند و فایل کامل آثار دارای حق نشر را ارائه نمی‌دهد.' ),
);

$mc_faq_content = '';

foreach ( $mc_faq as $mc_item ) {
	$mc_faq_content .= sprintf(
		"<!-- wp:details {\"className\":\"faq-item\"} -->\n<details class=\"wp-block-details faq-item\"><summary>%1\$s</summary><!-- wp:paragraph -->\n<p>%2\$s</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n\n",
		esc_html( $mc_item[0] ),
		esc_html( $mc_item[1] )
	);
}

$mc_legal = array(
	array( 'ماهیت نسخه‌ی نمایشی', 'کوهه در این نسخه یک نمونه‌ی کاربردی برای نمایش تجربه‌ی کاربری است و یک سرویس تجاری ارائه‌ی محتوا نیست. اطلاعات آثار، کاتالوگ نمونه‌اند و تمام فایل‌های ویدئویی، نمونه‌های آزاد هستند.' ),
	array( 'چه اطلاعاتی ذخیره می‌شود', 'برای ساخت حساب، نام و ایمیل لازم است. فهرست تماشا، تاریخچه‌ی پخش نمونه و امتیازهای شما روی همین نصب ذخیره می‌شود تا بخش «حساب کاربری» کار کند.' ),
	array( 'نگهداری و امنیت داده', 'رمز عبور هرگز به‌صورت متن ساده نگه‌داری نمی‌شود. نوشتن، حذف و تغییر داده‌های حساب تنها با احراز هویت و بررسی نانِس انجام می‌شود و هر نشست با کوکی امن تشخیص هویت می‌شود.' ),
	array( 'کوکی‌ها و حالت روشن/تاریک', 'تنها برای نگه‌داری ترجیح ظاهری و نشست ورود از کوکی محلی استفاده می‌شود. هیچ داده‌ی ردیابی تبلیغاتی رد و بدل نمی‌شود و هیچ ابزار تحلیلی بیرونی در صفحه‌ها نیست.' ),
	array( 'حقوق محتوا', 'عنوان‌ها، پوسترها و متن‌های نمونه تنها برای نمایش چیدمان‌اند. ویدئوها از نمونه‌های آزاد Big Buck Bunny و Elephants Dream (Blender Foundation) می‌آیند و با مجوز CC BY 3.0 منتشر شده‌اند.' ),
	array( 'ارتباط با ما', 'اگر پرسشی درباره‌ی داده‌ها یا این نسخه داری، از برگه‌ی «تماس با ما» بنویس. پاسخ‌دهی در نسخه‌ی نمونه‌ی این پلتفرم انجام نمی‌شود و پیام‌ها تنها برای نمایش فرم هستند.' ),
	array( 'تغییرات این سند', 'این متن نمونه است و با هر تغییر ساختاری به‌روز می‌شود. تاریخ آخرین بازبینی در پایین همین بخش ثبت می‌شود تا همیشه بدانی کدام نسخه را می‌خوانی.' ),
);

$mc_legal_content = '';

foreach ( $mc_legal as $mc_section ) {
	$mc_legal_content .= sprintf(
		"<!-- wp:group {\"tagName\":\"section\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group\"><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%1\$s</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>%2\$s</p>\n<!-- /wp:paragraph --></section>\n<!-- /wp:group -->\n\n",
		esc_html( $mc_section[0] ),
		esc_html( $mc_section[1] )
	);
}

$mc_pages = array(
	'help'    => array(
		'title'   => 'راهنما',
		'excerpt' => 'پرسش‌های پرتکرار درباره‌ی حساب، تماشا، اشتراک و دیدگاه‌ها.',
		'content' => $mc_faq_content,
	),
	'privacy' => array(
		'title'   => 'قوانین و حریم خصوصی',
		'excerpt' => 'شفافیت درباره‌ی داده‌ها، کوکی‌ها و حقوق محتوای این نسخه.',
		'content' => $mc_legal_content,
	),
);

$mc_snapshot = array();

foreach ( $mc_pages as $mc_slug => $mc_page ) {
	$mc_existing = get_page_by_path( $mc_slug );

	$mc_snapshot[] = $mc_existing
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
				'post_title'  => $mc_page['title'],
				'post_name'   => $mc_slug,
			)
		);

	if ( ! $mc_id || is_wp_error( $mc_id ) ) {
		continue;
	}

	wp_update_post(
		array(
			'ID'           => $mc_id,
			'post_title'   => $mc_page['title'],
			'post_name'    => $mc_slug,
			'post_status'  => 'publish',
			'post_content' => $mc_page['content'],
			'post_excerpt' => $mc_page['excerpt'],
		)
	);
}

update_option( $mc_backup, array( 'pages' => $mc_snapshot ), false );

printf(
	"info seed ok — help=%d · privacy=%d\n",
	count( $mc_faq ),
	count( $mc_legal )
);

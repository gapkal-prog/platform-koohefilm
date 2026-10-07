<?php
/**
 * کاشت داده‌ی آزمون برای برگه‌ی «سینورامگ» (هم‌ارز `cinora/magazine.html`).
 *
 * چه می‌سازد؟
 *   ۱) سه دسته‌ی واقعی نوشته‌ها: «نقد و بررسی»، «پیشنهاد تماشا»، «دنیای سینما»
 *      — همان دسته‌هایی که تب‌های مرجع نشان می‌دهند، ولی این‌جا از
 *      تاکسونومی واقعی وردپرس می‌آیند، نه فهرست ثابت.
 *   ۲) چهار نوشته با تصویر شاخص (تولیدشده با GD)، خلاصه، تاریخ و دسته.
 *   ۳) برگه‌ی «سینورامگ» با نامک `magazine` تا قالب `page-magazine.html`
 *      به آن بچسبد.
 *
 * برگشت‌پذیری: پیش از هر تغییر، وضعیت پیشین در گزینه‌ی
 * `manacore_qa_magazine_backup` ذخیره می‌شود و با آرگومان `restore` همه‌چیز
 * به همان حالت برمی‌گردد (همان قرارداد `seed-cast.php`).
 *
 * اجرا (مسیر **مطلق** لازم است — `wp eval-file` مسیر نسبی را از نصب
 * وردپرس می‌خواند):
 *   wp --path=/home/user/.cache/wp eval-file /path/to/seed-magazine.php
 *   wp --path=/home/user/.cache/wp eval-file /path/to/seed-magazine.php restore
 *
 * @package ManaCore\QA
 */

defined( 'ABSPATH' ) || exit;

$mc_backup_option = 'manacore_qa_magazine_backup';
$mc_restore       = in_array( 'restore', (array) $args, true );

/* -------------------------------------------------------------------------
 * ۱) برگشت به حالت پیشین
 * ---------------------------------------------------------------------- */
if ( $mc_restore ) {
	$mc_backup = get_option( $mc_backup_option );

	if ( ! is_array( $mc_backup ) ) {
		echo "magazine restore ok — nothing to restore\n";
		return;
	}

	foreach ( (array) $mc_backup['posts'] as $mc_post ) {
		if ( ! get_post( $mc_post['ID'] ) ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'          => $mc_post['ID'],
				'post_title'  => $mc_post['post_title'],
				'post_name'   => $mc_post['post_name'],
				'post_status' => $mc_post['post_status'],
				'post_date'   => $mc_post['post_date'],
				'post_excerpt' => $mc_post['post_excerpt'],
			)
		);

		wp_set_post_categories( $mc_post['ID'], (array) $mc_post['categories'] );
	}

	foreach ( (array) $mc_backup['terms'] as $mc_term ) {
		if ( ! term_exists( (int) $mc_term['term_id'], 'category' ) ) {
			wp_insert_term(
				$mc_term['name'],
				'category',
				array(
					'slug' => $mc_term['slug'],
				)
			);
		}
	}

	if ( ! empty( $mc_backup['page_id'] ) && get_post( $mc_backup['page_id'] ) ) {
		wp_trash_post( $mc_backup['page_id'] );
	}

	delete_option( $mc_backup_option );
	echo "magazine restore ok — magazine data restored\n";
	return;
}

/* -------------------------------------------------------------------------
 * ۲) پشتیبان‌گیری
 * ---------------------------------------------------------------------- */
$mc_slugs = array( 'dune-world', 'best-series', 'nolan-time', 'koohe-archive' );
$mc_backup = array(
	'terms'   => array(),
	'posts'   => array(),
	'page_id' => 0,
);

foreach ( $mc_slugs as $mc_slug ) {
	$mc_existing = get_page_by_path( $mc_slug, OBJECT, 'post' );

	if ( ! $mc_existing ) {
		continue;
	}

	$mc_backup['posts'][] = array(
		'ID'           => $mc_existing->ID,
		'post_title'   => $mc_existing->post_title,
		'post_name'    => $mc_existing->post_name,
		'post_status'  => $mc_existing->post_status,
		'post_date'    => $mc_existing->post_date,
		'post_excerpt' => $mc_existing->post_excerpt,
		'categories'   => wp_get_post_categories( $mc_existing->ID ),
	);
}

foreach ( array( 'نقد و بررسی', 'پیشنهاد تماشا', 'دنیای سینما' ) as $mc_term_name ) {
	$mc_term = get_term_by( 'name', $mc_term_name, 'category' );

	if ( $mc_term ) {
		$mc_backup['terms'][] = array(
			'term_id' => $mc_term->term_id,
			'name'    => $mc_term->name,
			'slug'    => $mc_term->slug,
		);
	}
}

$mc_page = get_page_by_path( 'magazine' );

if ( $mc_page ) {
	$mc_backup['page_id'] = (int) $mc_page->ID;
}

if ( ! get_option( $mc_backup_option ) ) {
	update_option( $mc_backup_option, $mc_backup, false );
}

/* -------------------------------------------------------------------------
 * ۳) دسته‌ها
 * ---------------------------------------------------------------------- */
$mc_cats = array();

foreach ( array( 'نقد و بررسی', 'پیشنهاد تماشا', 'دنیای سینما' ) as $mc_term_name ) {
	$mc_term = get_term_by( 'name', $mc_term_name, 'category' );

	if ( ! $mc_term ) {
		$mc_created = wp_insert_term( $mc_term_name, 'category' );
		$mc_term_id = is_wp_error( $mc_created ) ? 0 : (int) $mc_created['term_id'];
	} else {
		$mc_term_id = (int) $mc_term->term_id;
	}

	$mc_cats[ $mc_term_name ] = $mc_term_id;
}

/* -------------------------------------------------------------------------
 * ۴) تصویر شاخص ساختگی (GD) — همان روش `seed-cast.php`
 * ---------------------------------------------------------------------- */
$mc_cover = static function ( $slug, $rgb ) {
	$existing = get_page_by_path( $slug, OBJECT, 'post' );

	if ( $existing && has_post_thumbnail( $existing->ID ) ) {
		return (int) get_post_thumbnail_id( $existing->ID );
	}

	$mc_upload = wp_upload_dir();
	$mc_file   = trailingslashit( $mc_upload['path'] ) . 'qa-mag-' . $slug . '.png';

	if ( ! file_exists( $mc_file ) ) {
		$mc_img = imagecreatetruecolor( 1200, 620 );
		$mc_bg  = imagecolorallocate( $mc_img, $rgb[0], $rgb[1], $rgb[2] );
		imagefilledrectangle( $mc_img, 0, 0, 1200, 620, $mc_bg );

		$mc_line = imagecolorallocate( $mc_img, min( 255, $rgb[0] + 34 ), min( 255, $rgb[1] + 34 ), min( 255, $rgb[2] + 34 ) );

		for ( $mc_x = 40; $mc_x < 1200; $mc_x += 60 ) {
			imageline( $mc_img, $mc_x, 90, $mc_x, 530, $mc_line );
		}

		imagepng( $mc_img, $mc_file );
		imagedestroy( $mc_img );
	}

	$mc_attachment = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'QA magazine ' . $slug,
			'post_status'    => 'inherit',
		),
		$mc_file
	);

	if ( is_wp_error( $mc_attachment ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $mc_attachment, wp_generate_attachment_metadata( $mc_attachment, $mc_file ) );

	return (int) $mc_attachment;
};

/* -------------------------------------------------------------------------
 * ۵) نوشته‌ها
 * ---------------------------------------------------------------------- */
$mc_articles = array(
	array(
		'slug'     => 'dune-world',
		'title'    => 'تلماسه؛ وقتی سینما به یک جهان تازه تبدیل می‌شود',
		'english'  => 'Dune',
		'category' => 'نقد و بررسی',
		'excerpt'  => 'از شن‌های آراکیس تا موسیقی هانس زیمر؛ نگاهی به جهان‌سازی کم‌نظیر دنی ویلنوو.',
		'date'     => '2026-09-28 09:00:00',
		'rgb'      => array( 28, 36, 46 ),
	),
	array(
		'slug'     => 'best-series',
		'title'    => '۸ سریال که از اولین قسمت رهایتان نمی‌کنند',
		'english'  => 'Best Series',
		'category' => 'پیشنهاد تماشا',
		'excerpt'  => 'داستان‌هایی که شروعشان دست شماست، اما پایانشان را نمی‌توانید کنار بگذارید.',
		'date'     => '2026-09-27 09:00:00',
		'rgb'      => array( 38, 30, 46 ),
	),
	array(
		'slug'     => 'nolan-time',
		'title'    => 'زمان در سینمای نولان؛ فراتر از عقربه‌ها',
		'english'  => 'Nolan and Time',
		'category' => 'دنیای سینما',
		'excerpt'  => 'چطور نولان با زمان بازی می‌کند و ما را به بخشی از روایت تبدیل می‌کند؟',
		'date'     => '2026-09-26 09:00:00',
		'rgb'      => array( 30, 42, 34 ),
	),
	array(
		'slug'     => 'koohe-archive',
		'title'    => 'بایگانی کوهه؛ پنج فیلمی که هر سال باید دید',
		'english'  => 'Koohe Archive',
		'category' => 'نقد و بررسی',
		'excerpt'  => 'پنج انتخاب از قفسه‌ی کوهه که تماشای دوباره‌شان هر سال ارزش دارد.',
		'date'     => '2026-09-25 09:00:00',
		'rgb'      => array( 42, 34, 26 ),
	),
);

/*
 * تاریخ‌ها نسبت به «الان» ساخته می‌شوند، نه ثابت.
 *
 * چرا: کارت ویژه‌ی «سینورامگ» نخستین نوشته‌ی تازه‌ترین است و مرجع هم
 * مقاله‌ی «تلماسه» را ویژه می‌کند. با تاریخ ثابت (۲۰۲۶-۰۹-۲۸ و…)، هر
 * نوشته‌ی تازه‌تری که `seed.php` می‌سازد (مثل `cinema-notes`) جای کارت
 * ویژه را می‌گرفت و سنجش‌های محتوا‌محور (متن‌ها، ارتفاع بلوک متن،
 * جای تیتر) بی‌اعتبار می‌شد — دقیقاً همان چیزی که یک بار در مورد جای
 * تیتر کارت ویژه دیده شد.
 */
$mc_age = array(
	'dune-world'    => MINUTE_IN_SECONDS,
	'best-series'   => DAY_IN_SECONDS,
	'nolan-time'    => 2 * DAY_IN_SECONDS,
	'koohe-archive' => 3 * DAY_IN_SECONDS,
);

foreach ( $mc_articles as $mc_index => $mc_article ) {
	$mc_articles[ $mc_index ]['date'] = wp_date( 'Y-m-d H:i:s', time() - $mc_age[ $mc_article['slug'] ] );
}

$mc_post_ids = array();

foreach ( $mc_articles as $mc_article ) {
	$mc_existing = get_page_by_path( $mc_article['slug'], OBJECT, 'post' );
	$mc_data     = array(
		'post_type'    => 'post',
		'post_title'   => $mc_article['title'],
		'post_name'    => $mc_article['slug'],
		'post_status'  => 'publish',
		'post_date'    => $mc_article['date'],
		'post_excerpt' => $mc_article['excerpt'],
		'post_content' => '<p>' . $mc_article['excerpt'] . '</p><p>این متن نمونه‌ی آزمون است و از ویرایشگر وردپرس قابل تغییر است.</p>',
	);

	if ( $mc_existing ) {
		$mc_data['ID'] = $mc_existing->ID;
		$mc_id         = wp_update_post( $mc_data );
	} else {
		$mc_id = wp_insert_post( $mc_data );
	}

	if ( is_wp_error( $mc_id ) || ! $mc_id ) {
		continue;
	}

	wp_set_post_categories( $mc_id, array( $mc_cats[ $mc_article['category'] ] ) );
	update_post_meta( $mc_id, 'manacore_qa_slug', $mc_article['slug'] );

	$mc_thumb = $mc_cover( $mc_article['slug'], $mc_article['rgb'] );

	if ( $mc_thumb ) {
		set_post_thumbnail( $mc_id, $mc_thumb );
	}

	$mc_post_ids[] = (int) $mc_id;
}

/* -------------------------------------------------------------------------
 * ۶) برگه‌ی «سینورامگ»
 * ---------------------------------------------------------------------- */
$mc_page = get_page_by_path( 'magazine' );

if ( ! $mc_page ) {
	$mc_page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => 'سینورامگ',
			'post_name'   => 'magazine',
			'post_status' => 'publish',
		)
	);
} else {
	$mc_page_id = (int) $mc_page->ID;
}

printf(
	"magazine seed ok — page=%d · categories=%d · posts=%d\n",
	(int) $mc_page_id,
	count( array_filter( $mc_cats ) ),
	count( $mc_post_ids )
);

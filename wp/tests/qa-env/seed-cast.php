<?php
/**
 * کاشت داده‌ی آزمون برای برگه‌ی «بازیگران و عوامل» (`cinora/cast.html` مرجع).
 *
 * چه می‌کارد:
 *   ۱) ترم‌های واقعی تاکسونومی `person_role` («بازیگر»، «کارگردان») — دکمه‌های
 *      صافی برگه از همین ترم‌ها ساخته می‌شوند، پس افزودن نقش تازه از پیشخوان
 *      کافی است و هیچ فهرستی در کد نیست.
 *   ۲) چهره‌ها به‌عنوان نوشته‌ی نوع محتوای `person` با فراداده‌ی کامل (نام
 *      لاتین، زادروز، کشور) و **تصویر شاخص واقعی**: تصویر با GD ساخته و به
 *      کتابخانه‌ی رسانه افزوده می‌شود (نه نشانی بیرونی، نه تصویر جعلی در قالب).
 *   ۳) فراداده‌ی `manacore_cast` (ریپیتر واقعی، با `person_id`) و
 *      `manacore_director` روی شش اثر آزمون — همان کلیدهایی که
 *      `Query::person_work_ids()` می‌خواند؛ پس شمار «N اثر» و شبکه‌ی
 *      فیلموگرافی روی داده‌ی واقعی سنجیده می‌شوند، نه عدد دستی.
 *
 * معکوس‌پذیر است: هرچه این بذر می‌سازد یا تغییر می‌دهد (چهره‌ها، پیوست‌ها،
 * ترم‌های تازه، مقدار پیشین فراداده‌ی آثار) در گزینه‌ی
 * `manacore_qa_cast_backup` نگه داشته می‌شود و با آرگومان `restore` همه‌چیز به
 * حالت قبل برمی‌گردد.
 *
 * اجرا:
 *   php /usr/local/bin/wp --path=/home/user/.cache/wp eval-file wp/tests/qa-env/seed-cast.php
 *   php /usr/local/bin/wp --path=/home/user/.cache/wp eval-file wp/tests/qa-env/seed-cast.php restore
 *
 * @package ManaCore\QA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mc_backup_key = 'manacore_qa_cast_backup';
$mc_backup     = get_option( $mc_backup_key, array() );
$mc_backup     = is_array( $mc_backup ) ? $mc_backup : array();
$mc_backup     = wp_parse_args(
	$mc_backup,
	array(
		'people'   => array(),
		'terms'    => array(),
		'media'    => array(),
		'titles'   => array(),
	)
);

$mc_restore = false;
foreach ( array( isset( $args ) ? $args : array(), isset( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array() ) as $mc_arglist ) {
	foreach ( (array) $mc_arglist as $mc_arg ) {
		if ( '--restore' === $mc_arg || 'restore' === $mc_arg ) {
			$mc_restore = true;
		}
	}
}

/* ------------------------------------------------------------------ بازگردانی */
if ( $mc_restore ) {
	foreach ( $mc_backup['titles'] as $mc_id => $mc_prev ) {
		foreach ( array( 'manacore_cast', 'manacore_director' ) as $mc_meta_key ) {
			$mc_value = isset( $mc_prev[ $mc_meta_key ] ) ? (string) $mc_prev[ $mc_meta_key ] : '';

			if ( '' === $mc_value ) {
				delete_post_meta( $mc_id, $mc_meta_key );
			} else {
				update_post_meta( $mc_id, $mc_meta_key, $mc_value );
			}
		}
	}

	foreach ( $mc_backup['people'] as $mc_id ) {
		wp_delete_post( (int) $mc_id, true );
	}

	foreach ( $mc_backup['media'] as $mc_id ) {
		wp_delete_attachment( (int) $mc_id, true );
	}

	foreach ( $mc_backup['terms'] as $mc_term ) {
		if ( isset( $mc_term['taxonomy'], $mc_term['term_id'] ) ) {
			wp_delete_term( (int) $mc_term['term_id'], $mc_term['taxonomy'] );
		}
	}

	delete_option( $mc_backup_key );

	printf(
		"cast restore ok — people=%d · media=%d · titles=%d\n",
		count( $mc_backup['people'] ),
		count( $mc_backup['media'] ),
		count( $mc_backup['titles'] )
	);

	return;
}

/* --------------------------------------------------------------------- نقش‌ها */
$mc_roles = array(
	'actor'    => 'بازیگر',
	'director' => 'کارگردان',
);

$mc_role_terms = array();

foreach ( $mc_roles as $mc_slug => $mc_name ) {
	$mc_term = term_exists( $mc_slug, 'person_role' );

	if ( ! $mc_term ) {
		$mc_term = wp_insert_term( $mc_name, 'person_role', array( 'slug' => $mc_slug ) );

		if ( ! is_wp_error( $mc_term ) && isset( $mc_term['term_id'] ) ) {
			$mc_backup['terms'][] = array(
				'term_id'  => (int) $mc_term['term_id'],
				'taxonomy' => 'person_role',
			);
		}
	}

	if ( is_wp_error( $mc_term ) ) {
		continue;
	}

	$mc_role_terms[ $mc_slug ] = (int) ( is_array( $mc_term ) ? $mc_term['term_id'] : $mc_term );
}

/* --------------------------------------------------------------------- تصاویر */

/**
 * ساخت (یک‌باره) یک تصویر واقعی ۶۰۰×۸۰۰ در کتابخانه‌ی رسانه.
 *
 * تصویر با GD ساخته می‌شود چون آزمون به شبکه دسترسی ندارد؛ نتیجه یک پیوست
 * واقعی با متادیتای درست است، پس مسیر «تصویر شاخص» آزمون‌پذیر می‌ماند.
 *
 * @param string $slug     نامک چهره.
 * @param string $initials حروف لاتین روی تصویر.
 * @param array  $rgb      رنگ پس‌زمینه.
 * @return int شناسه‌ی پیوست یا صفر.
 */
$mc_media = static function ( $slug, $initials, $rgb ) use ( &$mc_backup ) {
	$mc_found = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_koohe_qa_slug',
			'meta_value'     => $slug,
		)
	);

	if ( $mc_found ) {
		return (int) $mc_found[0];
	}

	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}

	$mc_uploads = wp_upload_dir();

	if ( empty( $mc_uploads['basedir'] ) ) {
		return 0;
	}

	$mc_file = trailingslashit( $mc_uploads['basedir'] ) . 'koohe-qa-' . $slug . '.png';

	if ( ! file_exists( $mc_file ) ) {
		$mc_image = imagecreatetruecolor( 600, 800 );
		$mc_bg    = imagecolorallocate( $mc_image, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] );
		$mc_fg    = imagecolorallocate( $mc_image, 244, 248, 240 );

		imagefilledrectangle( $mc_image, 0, 0, 600, 800, $mc_bg );

		/* یک نوار روشن پایین تصویر تا قاب چهره شبیه عکس واقعی دیده شود. */
		$mc_band = imagecolorallocate( $mc_image, (int) ( $rgb[0] * 0.55 ), (int) ( $rgb[1] * 0.55 ), (int) ( $rgb[2] * 0.55 ) );
		imagefilledrectangle( $mc_image, 0, 620, 600, 800, $mc_band );

		imagestring( $mc_image, 5, 40, 380, $initials, $mc_fg );
		imagepng( $mc_image, $mc_file );
		imagedestroy( $mc_image );
	}

	$mc_attach = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'چهره‌ی ' . $initials,
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $mc_uploads['baseurl'] ) . 'koohe-qa-' . $slug . '.png',
		),
		$mc_file
	);

	if ( ! $mc_attach || is_wp_error( $mc_attach ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	wp_update_attachment_metadata( (int) $mc_attach, wp_generate_attachment_metadata( (int) $mc_attach, $mc_file ) );
	update_post_meta( (int) $mc_attach, '_koohe_qa_slug', $slug );

	$mc_backup['media'][] = (int) $mc_attach;

	return (int) $mc_attach;
};

/* --------------------------------------------------------------------- چهره‌ها */
$mc_people = array(
	array(
		'slug'    => 'christopher-nolan',
		'name'    => 'کریستوفر نولان',
		'english' => 'Christopher Nolan',
		'role'    => 'director',
		'born'    => '۳۰ جولای ۱۹۷۰',
		'country' => 'بریتانیا · آمریکا',
		'init'    => 'CN',
		'rgb'     => array( 26, 38, 52 ),
		'bio'     => 'کریستوفر نولان فیلم‌ساز بریتانیایی-آمریکایی است که با روایت‌های چندلایه، زمان‌بازی‌های ساختاری و اصرار بر جلوه‌های عملی شناخته می‌شود. تلقین یکی از شاخص‌ترین نمونه‌های همین زبان روایی است.',
		'works'   => array( 'inception' ),
	),
	array(
		'slug'    => 'leonardo-dicaprio',
		'name'    => 'لئوناردو دیکاپریو',
		'english' => 'Leonardo DiCaprio',
		'role'    => 'actor',
		'born'    => '۱۱ نوامبر ۱۹۷۴',
		'country' => 'آمریکا',
		'init'    => 'LD',
		'rgb'     => array( 34, 44, 34 ),
		'bio'     => 'لئوناردو دیکاپریو بازیگر آمریکایی و برنده‌ی اسکار است. همکاری‌های او با کارگردانان بزرگ معاصر، او را به یکی از چهره‌های محوری سینمای تازه تبدیل کرده است.',
		'works'   => array( 'inception' ),
	),
	array(
		'slug'    => 'tom-hardy',
		'name'    => 'تام هاردی',
		'english' => 'Tom Hardy',
		'role'    => 'actor',
		'born'    => '۱۵ سپتامبر ۱۹۷۷',
		'country' => 'بریتانیا',
		'init'    => 'TH',
		'rgb'     => array( 48, 32, 30 ),
		'bio'     => 'تام هاردی بازیگر بریتانیایی است که با نقش‌های فیزیکی و دگردیسی‌های ظاهری‌اش شناخته می‌شود.',
		'works'   => array( 'inception' ),
	),
	array(
		'slug'    => 'francis-ford-coppola',
		'name'    => 'فرانسیس فورد کوپولا',
		'english' => 'Francis Ford Coppola',
		'role'    => 'director',
		'born'    => '۷ آوریل ۱۹۳۹',
		'country' => 'آمریکا',
		'init'    => 'FC',
		'rgb'     => array( 38, 28, 46 ),
		'bio'     => 'فرانسیس فورد کوپولا کارگردان و فیلم‌نامه‌نویس آمریکایی است؛ پدرخوانده او را به یکی از تأثیرگذارترین فیلم‌سازان تاریخ تبدیل کرد.',
		'works'   => array( 'the-godfather' ),
	),
	array(
		'slug'    => 'marlon-brando',
		'name'    => 'مارلون براندو',
		'english' => 'Marlon Brando',
		'role'    => 'actor',
		'born'    => '۳ آوریل ۱۹۲۴',
		'country' => 'آمریکا',
		'init'    => 'MB',
		'rgb'     => array( 30, 36, 44 ),
		'bio'     => 'مارلون براندو بازیگر آمریکایی و از چهره‌های تغییردهنده‌ی سبک بازیگری مدرن است.',
		'works'   => array( 'the-godfather' ),
	),
	array(
		'slug'    => 'al-pacino',
		'name'    => 'آل پاچینو',
		'english' => 'Al Pacino',
		'role'    => 'actor',
		'born'    => '۲۵ آوریل ۱۹۴۰',
		'country' => 'آمریکا',
		'init'    => 'AP',
		'rgb'     => array( 44, 36, 26 ),
		'bio'     => 'آل پاچینو بازیگر آمریکایی و برنده‌ی اسکار است؛ نقش‌هایش در پدرخوانده بخشی از حافظه‌ی جمعی سینماست.',
		'works'   => array( 'the-godfather' ),
	),
	array(
		'slug'    => 'hiroyuki-sanada',
		'name'    => 'هیرویوکی سانادا',
		'english' => 'Hiroyuki Sanada',
		'role'    => 'actor',
		'born'    => '۱۲ اکتبر ۱۹۶۰',
		'country' => 'ژاپن',
		'init'    => 'HS',
		'rgb'     => array( 26, 42, 42 ),
		'bio'     => 'هیرویوکی سانادا بازیگر ژاپنی است که در سینمای شرق و غرب، از فیلم‌های سامورایی تا تولیدات تلویزیونی بزرگ، حضور داشته است.',
		'works'   => array( 'shogun' ),
	),
	array(
		'slug'    => 'anna-sawai',
		'name'    => 'آنا ساوایی',
		'english' => 'Anna Sawai',
		'role'    => 'actor',
		'born'    => '۱۱ ژوئن ۱۹۹۲',
		'country' => 'ژاپن · نیوزیلند',
		'init'    => 'AS',
		'rgb'     => array( 40, 30, 40 ),
		'bio'     => 'آنا ساوایی بازیگر و خواننده‌ی ژاپنی-نیوزیلندی است که با نقش‌های دقیق و کم‌گو در سریال‌های معاصر دیده شد.',
		'works'   => array( 'shogun' ),
	),
	array(
		'slug'    => 'claire-foy',
		'name'    => 'کلر فوی',
		'english' => 'Claire Foy',
		'role'    => 'actor',
		'born'    => '۱۶ آوریل ۱۹۸۴',
		'country' => 'بریتانیا',
		'init'    => 'CF',
		'rgb'     => array( 34, 34, 48 ),
		'bio'     => 'کلر فوی بازیگر بریتانیایی است که با نقش‌های تاریخی و درام‌های تلویزیونی به شهرت رسید.',
		'works'   => array( 'the-crown' ),
	),
	array(
		'slug'    => 'jared-harris',
		'name'    => 'جرد هریس',
		'english' => 'Jared Harris',
		'role'    => 'actor',
		'born'    => '۲۴ اوت ۱۹۶۱',
		'country' => 'بریتانیا',
		'init'    => 'JH',
		'rgb'     => array( 44, 44, 34 ),
		/* دو اثر: هم «چرنوبیل» و هم «تاج» — تا شمار «۲ اثر» داده‌ی واقعی بسنجد. */
		'bio'     => 'جرد هریس بازیگر بریتانیایی است؛ نقش‌هایش در درام‌های تاریخی و سریال‌های معاصر او را به چهره‌ای آشنا تبدیل کرده است.',
		'works'   => array( 'chernobyl', 'the-crown' ),
	),
	array(
		'slug'    => 'stellan-skarsgard',
		'name'    => 'استلان اسکاشگورد',
		'english' => 'Stellan Skarsgård',
		'role'    => 'actor',
		'born'    => '۱۳ ژوئن ۱۹۵۱',
		'country' => 'سوئد',
		'init'    => 'SS',
		'rgb'     => array( 24, 40, 30 ),
		'bio'     => 'استلان اسکاشگورد بازیگر سوئدی است که در سینمای اروپا و آمریکا نقش‌های گوناگونی بازی کرده است.',
		'works'   => array( 'chernobyl' ),
	),
	array(
		'slug'    => 'hiroyuki-imaishi',
		'name'    => 'هیرویوکی ایماایشی',
		'english' => 'Hiroyuki Imaishi',
		'role'    => 'director',
		'born'    => '۴ اکتبر ۱۹۷۱',
		'country' => 'ژاپن',
		'init'    => 'HI',
		'rgb'     => array( 48, 26, 36 ),
		'bio'     => 'هیرویوکی ایماایشی کارگردان انیمه و از بنیان‌گذاران استودیو تریگر است؛ سبک پرانرژی و رنگین او در آثار علمی‌تخیلی شناخته‌شده است.',
		'works'   => array( 'cyberpunk' ),
	),
);

$mc_people_ids = array();

foreach ( $mc_people as $mc_person ) {
	$mc_existing = get_page_by_path( $mc_person['slug'], OBJECT, 'person' );
	$mc_payload  = array(
		'post_type'    => 'person',
		'post_status'  => 'publish',
		'post_title'   => $mc_person['name'],
		'post_name'    => $mc_person['slug'],
		'post_excerpt' => $mc_person['bio'],
		'post_content' => '<!-- wp:paragraph --><p>' . $mc_person['bio'] . '</p><!-- /wp:paragraph -->',
	);

	if ( $mc_existing instanceof WP_Post ) {
		$mc_payload['ID'] = (int) $mc_existing->ID;
		wp_update_post( $mc_payload );
		$mc_person_id = (int) $mc_existing->ID;
	} else {
		$mc_person_id = (int) wp_insert_post( $mc_payload );

		if ( $mc_person_id ) {
			$mc_backup['people'][] = $mc_person_id;
		}
	}

	if ( ! $mc_person_id ) {
		continue;
	}

	$mc_attach = $mc_media( $mc_person['slug'], $mc_person['init'], $mc_person['rgb'] );

	update_post_meta( $mc_person_id, 'manacore_person_english', $mc_person['english'] );
	update_post_meta( $mc_person_id, 'manacore_person_born', $mc_person['born'] );
	update_post_meta( $mc_person_id, 'manacore_country', $mc_person['country'] );

	if ( $mc_attach ) {
		set_post_thumbnail( $mc_person_id, $mc_attach );
		update_post_meta( $mc_person_id, 'manacore_person_photo', wp_get_attachment_url( $mc_attach ) );
	}

	if ( isset( $mc_role_terms[ $mc_person['role'] ] ) ) {
		wp_set_object_terms( $mc_person_id, array( $mc_role_terms[ $mc_person['role'] ] ), 'person_role' );
	}

	$mc_people_ids[ $mc_person['slug'] ] = array(
		'id'    => $mc_person_id,
		'name'  => $mc_person['name'],
		'role'  => $mc_person['role'],
		'photo' => $mc_attach ? wp_get_attachment_url( $mc_attach ) : '',
	);
}

/* -------------------------------------------------- فراداده‌ی آثار (بازیگر/کارگردان) */
$mc_title_slugs = array( 'inception', 'the-godfather', 'shogun', 'the-crown', 'chernobyl', 'cyberpunk' );

foreach ( $mc_title_slugs as $mc_title_slug ) {
	$mc_title = get_page_by_path( $mc_title_slug, OBJECT, array( 'movie', 'series', 'anime' ) );

	if ( ! $mc_title instanceof WP_Post ) {
		continue;
	}

	$mc_title_id = (int) $mc_title->ID;

	if ( ! isset( $mc_backup['titles'][ $mc_title_id ] ) ) {
		$mc_backup['titles'][ $mc_title_id ] = array(
			'manacore_cast'     => (string) get_post_meta( $mc_title_id, 'manacore_cast', true ),
			'manacore_director' => (string) get_post_meta( $mc_title_id, 'manacore_director', true ),
		);
	}

	$mc_cast      = array();
	$mc_directors = array();

	foreach ( $mc_people as $mc_source ) {
		if ( ! in_array( $mc_title_slug, $mc_source['works'], true ) ) {
			continue;
		}

		$mc_entry = isset( $mc_people_ids[ $mc_source['slug'] ] ) ? $mc_people_ids[ $mc_source['slug'] ] : null;

		if ( ! $mc_entry ) {
			continue;
		}

		if ( 'actor' === $mc_source['role'] ) {
			$mc_cast[] = array(
				'name'      => $mc_source['name'],
				'character' => '',
				'photo'     => $mc_entry['photo'],
				'person_id' => $mc_entry['id'],
			);
		} else {
			$mc_directors[] = $mc_source['name'];
		}
	}

	if ( $mc_cast ) {
		update_post_meta( $mc_title_id, 'manacore_cast', wp_json_encode( $mc_cast, JSON_UNESCAPED_UNICODE ) );
	}

	if ( $mc_directors ) {
		update_post_meta( $mc_title_id, 'manacore_director', implode( '، ', $mc_directors ) );
	}
}

update_option( $mc_backup_key, $mc_backup, false );

/* پاک‌کردن کش شمارش آثار تا اعداد تازه فوراً دیده شوند. */
foreach ( $mc_people_ids as $mc_entry ) {
	delete_transient( 'manacore_person_works_' . $mc_entry['id'] );
}

printf(
	"cast seed ok — roles=%d · people=%d · titles=%d\n",
	count( $mc_role_terms ),
	count( $mc_people_ids ),
	count( $mc_backup['titles'] )
);

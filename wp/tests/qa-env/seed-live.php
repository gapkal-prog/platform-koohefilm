<?php
/**
 * کاشت داده‌ی آزمون برای برگه‌ی «پخش زنده» (`live.html` مرجع).
 *
 * چه چیزی می‌کارد:
 *   ۱) سه کانال واقعی از نوع محتوای `channel` با فراداده‌ی کامل.
 *   ۲) زمان‌بندی **امروز** روی چند اثر، تا «قاب‌های امروز» داده‌ی واقعی
 *      داشته باشد (روز به‌صورت پویا محاسبه می‌شود؛ پس آزمون در هر روز هفته
 *      معنا دارد).
 *   ۳) یک اثر بدون زمان‌بندی برای آزمون شاخه‌ی دیگر.
 *
 * idempotent است: هر بار اجرا، همان‌ها به‌روزرسانی می‌شوند و رکورد تکراری
 * ساخته نمی‌شود. خروجی یک خط خلاصه است که آزمون مرورگری به آن تکیه می‌کند.
 *
 * اجرا:
 *   php /usr/local/bin/wp --path=/home/user/.cache/wp eval-file wp/tests/qa-env/seed-live.php
 *
 * @package ManaCore\QA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ایجاد/به‌روزرسانی یک کانال.
 *
 * @param array $args داده‌ی کانال.
 * @return int شناسه.
 */
$mc_channel = static function ( $args ) {
	$existing = get_page_by_path( $args['slug'], OBJECT, 'channel' );
	$id       = $existing ? (int) $existing->ID : 0;

	$payload = array(
		'post_type'    => 'channel',
		'post_status'  => 'publish',
		'post_title'   => $args['name'],
		'post_name'    => $args['slug'],
		'post_excerpt' => isset( $args['excerpt'] ) ? $args['excerpt'] : '',
		'post_content' => isset( $args['content'] ) ? $args['content'] : '',
	);

	if ( $id ) {
		$payload['ID'] = $id;
		wp_update_post( $payload );
	} else {
		$id = (int) wp_insert_post( $payload );
	}

	if ( ! $id ) {
		return 0;
	}

	update_post_meta( $id, 'manacore_channel_title', $args['title'] );
	update_post_meta( $id, 'manacore_channel_subtitle', $args['subtitle'] );
	update_post_meta( $id, 'manacore_channel_quality', $args['quality'] );
	update_post_meta( $id, 'manacore_channel_icon', $args['icon'] );
	update_post_meta( $id, 'manacore_channel_video', $args['video'] );
	update_post_meta( $id, 'manacore_channel_poster', $args['poster'] );

	return $id;
};

$mc_sample = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/';

$mc_channel(
	array(
		'slug'     => 'koohe-cinema',
		'name'     => 'کوهه سینما',
		'title'    => 'یک قرار با دنیای سینما',
		'subtitle' => 'تجربه آزمایشی کانال سینمایی',
		'quality'  => '1080',
		'icon'     => 'film',
		'video'    => $mc_sample . 'BigBuckBunny.mp4',
		'poster'   => 'https://placehold.co/1280x720/0e141b/c6ed7b?text=KOOHE',
	)
);

$mc_channel(
	array(
		'slug'     => 'koohe-animation',
		'name'     => 'کوهه انیمیشن',
		'title'    => 'به جهان خیال خوش آمدی',
		'subtitle' => 'نمونه آزاد انیمیشن Big Buck Bunny',
		'quality'  => '720',
		'icon'     => 'star',
		'video'    => $mc_sample . 'ElephantsDream.mp4',
		'poster'   => 'https://placehold.co/1280x720/12181f/9dbeef?text=ANIMATION',
	)
);

$mc_channel(
	array(
		'slug'     => 'koohe-select',
		'name'     => 'کوهه منتخب',
		'title'    => 'انتخاب‌های متفاوت، کنار هم',
		'subtitle' => 'چرخه آزمایشی آثار منتخب',
		'quality'  => '360',
		'icon'     => 'crown',
		'video'    => $mc_sample . 'ForBiggerBlazes.mp4',
		'poster'   => 'https://placehold.co/1280x720/12181f/eaba7f?text=SELECT',
	)
);

/*
 * زمان‌بندی «قاب‌های امروز».
 *
 * نام روز **انگلیسی** لازم است (کلیدهای `manacore_air_day`)، در حالی که
 * `wp_date('l')` با زبان فارسی «دوشنبه» برمی‌گرداند؛ پس مثل خودِ افزونه از
 * `DateTimeImmutable` استفاده می‌شود که مستقل از زبان، `Monday` می‌دهد.
 *
 * قاعده‌ی آزمون‌پذیری: این بذر **معکوس‌پذیر** است. روز/ساعت پیشین هر اثری که
 * دست می‌خورد در گزینه‌ی `manacore_qa_live_air_backup` پشتیبان‌گیری می‌شود و
 * با `seed-live.php restore` وضعیت پیشین برمی‌گردد؛ بدون آن، آزمون «پخش
 * زنده» برنامه‌ی هفتگیِ آزمون «برنامه پخش» را خراب می‌کرد (پنجشنبه خالی و
 * ساعت ۲۱:۳۰ تبدیل به ۲۱:۰۰ می‌شد).
 */
$mc_restore = false;

/*
 * سه راه برای درخواست بازگردانی: آرگومان موضعی `restore` (راه توصیه‌شده؛
 * `wp eval-file` پرچم‌های `--x` را خودش می‌خورد و خطای پارامتر می‌دهد)،
 * `--restore` (اگر جای دیگری صدا زده شد) و متغیر محیطی.
 */
foreach ( array( isset( $args ) ? $args : array(), isset( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array() ) as $mc_arglist ) {
	foreach ( (array) $mc_arglist as $mc_arg ) {
		if ( in_array( $mc_arg, array( 'restore', '--restore' ), true ) ) {
			$mc_restore = true;
		}
	}
}

if ( getenv( 'MANACORE_SEED_RESTORE' ) ) {
	$mc_restore = true;
}

$mc_backup_key = 'manacore_qa_live_air_backup';
$mc_backup     = get_option( $mc_backup_key, array() );
$mc_backup     = is_array( $mc_backup ) ? $mc_backup : array();

if ( $mc_restore ) {
	$mc_restored = 0;

	foreach ( $mc_backup as $mc_id => $mc_prev ) {
		foreach ( array( 'manacore_air_day', 'manacore_air_time' ) as $mc_meta_key ) {
			$mc_value = isset( $mc_prev[ $mc_meta_key ] ) ? (string) $mc_prev[ $mc_meta_key ] : '';

			if ( '' === $mc_value ) {
				delete_post_meta( $mc_id, $mc_meta_key );
			} else {
				update_post_meta( $mc_id, $mc_meta_key, $mc_value );
			}
		}

		++$mc_restored;
	}

	delete_option( $mc_backup_key );

	printf( "live restore ok — frames reset=%d\n", $mc_restored );

	return;
}

$mc_clock = new DateTimeImmutable( 'now', wp_timezone() );
$mc_today = strtolower( $mc_clock->format( 'l' ) );
$mc_times = array( '16:00', '20:00', '22:00' );

/**
 * انتخاب آثار برای قاب‌های امروز.
 *
 * @param bool $only_unscheduled فقط آثاری که روز پخش ندارند.
 * @return array<int,\WP_Post>
 */
$mc_pick = static function ( $only_unscheduled ) use ( $mc_times ) {
	return get_posts(
		array(
			'post_type'      => array( 'series', 'anime' ),
			'post_status'    => 'publish',
			'posts_per_page' => count( $mc_times ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => $only_unscheduled ? array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => 'manacore_air_day',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'manacore_air_day',
					'value'   => '',
					'compare' => '=',
				),
			) : array(),
		)
	);
};

/*
 * اول آثار بی‌زمان‌بندی تا برنامه‌ی هفتگی موجود دست‌نخورده بماند؛ اگر کم
 * آمدند، آثار زمان‌بندی‌شده هم (با پشتیبان‌گیری) به کار می‌روند.
 */
$mc_titles = $mc_pick( true );

if ( count( $mc_titles ) < count( $mc_times ) ) {
	$mc_taken = wp_list_pluck( $mc_titles, 'ID' );

	foreach ( $mc_pick( false ) as $mc_candidate ) {
		if ( in_array( $mc_candidate->ID, $mc_taken, true ) ) {
			continue;
		}

		$mc_titles[] = $mc_candidate;

		if ( count( $mc_titles ) >= count( $mc_times ) ) {
			break;
		}
	}
}

$mc_scheduled = 0;

foreach ( $mc_titles as $mc_index => $mc_post ) {
	if ( ! isset( $mc_backup[ $mc_post->ID ] ) ) {
		$mc_backup[ $mc_post->ID ] = array(
			'manacore_air_day'  => (string) get_post_meta( $mc_post->ID, 'manacore_air_day', true ),
			'manacore_air_time' => (string) get_post_meta( $mc_post->ID, 'manacore_air_time', true ),
		);
	}

	update_post_meta( $mc_post->ID, 'manacore_air_day', $mc_today );
	update_post_meta( $mc_post->ID, 'manacore_air_time', $mc_times[ $mc_index ] );
	++$mc_scheduled;
}

/* یک اثر بی‌زمان‌بندی هم می‌ماند تا حالت «قاب خالی» آزمون‌پذیر باشد. */
$mc_extra = get_posts(
	array(
		'post_type'      => 'movie',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
	)
);

foreach ( $mc_extra as $mc_post ) {
	if ( ! isset( $mc_backup[ $mc_post->ID ] ) ) {
		$mc_backup[ $mc_post->ID ] = array(
			'manacore_air_day'  => (string) get_post_meta( $mc_post->ID, 'manacore_air_day', true ),
			'manacore_air_time' => (string) get_post_meta( $mc_post->ID, 'manacore_air_time', true ),
		);
	}

	delete_post_meta( $mc_post->ID, 'manacore_air_day' );
	delete_post_meta( $mc_post->ID, 'manacore_air_time' );
}

if ( $mc_backup ) {
	update_option( $mc_backup_key, $mc_backup, false );
}

printf(
	"live seed ok — channels=%d · today=%s · scheduled=%d\n",
	3,
	$mc_today,
	$mc_scheduled
);

<?php
/**
 * داده‌ی آزمون محیط QA (اجرا: wp eval-file wp/tests/qa-env/seed.php).
 *
 * این پرونده پیش‌تر فقط در پوشه‌ی موقت سندباکس (`/home/user/.cache/seed.php`)
 * زندگی می‌کرد و با پاک‌شدن سندباکس از بین می‌رفت. اکنون نسخه‌ی مرجعش اینجاست
 * تا «آزموده‌شده» با یک فرمان قابل بازتولید باشد.
 *
 * محتوا در صورت وجود، ساخته نمی‌شود (idempotent)؛ اجرای دوباره چیزی را
 * خراب نمی‌کند.
 *
 * @package ManaCore\QA
 */

defined( 'ABSPATH' ) || die( 'WP only' );

/* -------------------------------------------------------------------------
 * ۱) تاکسونومی‌ها
 * ---------------------------------------------------------------------- */

$mc_terms = array(
	'genre'    => array( 'درام', 'جنایی', 'علمی‌تخیلی', 'اکشن', 'ماجراجویی', 'انیمیشن', 'ترسناک', 'کمدی', 'هیجان‌انگیز', 'تاریخی' ),
	'country'  => array( 'آمریکا', 'ژاپن', 'انگلستان' ),
	'language' => array( 'زیرنویس فارسی', 'دوبله فارسی', 'زبان اصلی' ),
	'quality'  => array( '1080p', '720p', '360p' ),
);

foreach ( $mc_terms as $mc_tax => $mc_names ) {
	if ( ! taxonomy_exists( $mc_tax ) ) {
		continue;
	}
	foreach ( $mc_names as $mc_name ) {
		if ( ! term_exists( $mc_name, $mc_tax ) ) {
			wp_insert_term( $mc_name, $mc_tax );
		}
	}
}

/** یافتن یا ساخت یک پست بر اساس نامک. */
$mc_post = static function ( $args, $meta = array(), $terms = array() ) {
	$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( $args );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	foreach ( $terms as $tax => $names ) {
		if ( taxonomy_exists( $tax ) ) {
			wp_set_object_terms( $id, $names, $tax );
		}
	}
	return $id;
};

$mc_trailer = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
$mc_stream  = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4';

/** ساخت متای لینک‌ها (سه کیفیت، هر کدام پخش + دانلود). */
$mc_links = static function ( $prefix ) use ( $mc_stream, $mc_trailer ) {
	$qualities = array(
		array( '1080p', 'Full HD', '۱.۲ گیگابایت' ),
		array( '720p', 'HD', '۷۴۰ مگابایت' ),
		array( '360p', 'کم‌حجم', '۳۱۰ مگابایت' ),
	);

	$groups = array();
	foreach ( $qualities as $index => $quality ) {
		$groups[] = array(
			'id'       => $prefix . '-' . $index,
			'title'    => $quality[0] . ' · ' . $quality[1],
			'season'   => '',
			'quality'  => $quality[0],
			'language' => 'sub_fa',
			'encoder'  => 'MP4 · H.264',
			'size'     => $quality[2],
			'note'     => '',
			'premium'  => false,
			'items'    => array(
				array( 'id' => $prefix . '-' . $index . '-stream', 'label' => '', 'episode' => '', 'url' => $mc_stream, 'type' => 'stream', 'size' => $quality[2], 'quality' => '', 'language' => '', 'note' => '' ),
				array( 'id' => $prefix . '-' . $index . '-direct', 'label' => '', 'episode' => '', 'url' => $mc_trailer, 'type' => 'direct', 'size' => $quality[2], 'quality' => '', 'language' => '', 'note' => '' ),
			),
		);
	}

	return wp_json_encode( $groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
};

/* -------------------------------------------------------------------------
 * ۲) آثار
 * ---------------------------------------------------------------------- */

$mc_movie = $mc_post(
	array(
		'post_type'    => 'movie',
		'post_status'  => 'publish',
		'post_title'   => 'پدرخوانده',
		'post_name'    => 'the-godfather',
		'post_content' => '<!-- wp:paragraph --><p>داستان خانواده‌ی کورلیونه؛ یکی از مهم‌ترین فیلم‌های تاریخ سینما.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'The Godfather',
		'manacore_tagline'        => 'یک پیشنهاد که نمی‌توانی رد کنی.',
		'manacore_year'           => 1972,
		'manacore_release_date'   => '1972-03-24',
		'manacore_runtime'        => 175,
		'manacore_imdb_rating'    => 9.2,
		'manacore_tmdb_rating'    => 8.7,
		'manacore_trailer_url'    => $mc_trailer,
		'manacore_status'         => 'released',
		/*
		 * کلید «فقط دوبله فارسی» سایدبار کشف روی همین فراداده کار می‌کند؛
		 * سه اثر از پنج اثر نمونه دوبله دارند تا فیلترشان قابل سنجش باشد.
		 */
		'manacore_dubbed'         => 1,
		'manacore_links'          => $mc_links( 'gf' ),
		'manacore_custom_notice'  => 'برای رعایت حقوق نشر، پخش و دانلود این نسخه از نمونه‌ی آزاد Big Buck Bunny استفاده می‌کند.',
	),
	array( 'genre' => array( 'درام', 'جنایی' ), 'country' => array( 'آمریکا' ), 'language' => array( 'زیرنویس فارسی' ), 'quality' => array( '1080p', '720p' ) )
);

$mc_movie2 = $mc_post(
	array(
		'post_type'    => 'movie',
		'post_status'  => 'publish',
		'post_title'   => 'تلقین',
		'post_name'    => 'inception',
		'post_content' => '<!-- wp:paragraph --><p>دزدی از رؤیا؛ معمایی در چند لایه.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'Inception',
		'manacore_year'           => 2010,
		'manacore_runtime'        => 148,
		'manacore_imdb_rating'    => 8.8,
		'manacore_dubbed'         => 1,
		'manacore_trailer_url'    => $mc_trailer,
		'manacore_links'          => $mc_links( 'in' ),
	),
	array( 'genre' => array( 'علمی‌تخیلی', 'اکشن' ), 'country' => array( 'آمریکا' ), 'quality' => array( '1080p' ) )
);

$mc_series = $mc_post(
	array(
		'post_type'    => 'series',
		'post_status'  => 'publish',
		'post_title'   => 'شوگان',
		'post_name'    => 'shogun',
		'post_content' => '<!-- wp:paragraph --><p>حماسه‌ای تاریخی در ژاپن فئودالی.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'Shōgun',
		'manacore_year'           => 2024,
		'manacore_runtime'        => 60,
		'manacore_imdb_rating'    => 8.5,
		'manacore_trailer_url'    => $mc_trailer,
		'manacore_total_seasons'  => 2,
		'manacore_total_episodes' => 8,
		'manacore_dubbed'         => 1,
		/* برنامه‌ی هفتگی: روز و ساعت پخش (ویرایش‌پذیر از متاباکس). */
		'manacore_air_day'        => 'thursday',
		'manacore_air_time'       => '21:30',
		'manacore_links'          => $mc_links( 'sh' ),
	),
	array( 'genre' => array( 'درام', 'تاریخی' ), 'country' => array( 'آمریکا' ), 'quality' => array( '1080p', '720p' ) )
);

/*
 * سریال دوم روزِ پنجشنبه: برای این‌که یک روز برنامه دو ردیف داشته باشد و
 * سنجش «ردیف میانی» در `schedule-parity.cjs` واقعاً اجرا شود (قاعده‌ی
 * `:last-child` ردیف آخر را صفر می‌کند و با تک‌ردیفی سنجیدنی نیست).
 */
$mc_series2 = $mc_post(
	array(
		'post_type'    => 'series',
		'post_status'  => 'publish',
		'post_title'   => 'تاج',
		'post_name'    => 'the-crown',
		'post_content' => '<!-- wp:paragraph --><p>روایتی از دهه‌های سلطنت.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'The Crown',
		'manacore_year'           => 2016,
		'manacore_runtime'        => 58,
		'manacore_imdb_rating'    => 8.6,
		'manacore_trailer_url'    => $mc_trailer,
		'manacore_total_seasons'  => 2,
		'manacore_total_episodes' => 16,
		'manacore_dubbed'         => 1,
		'manacore_air_day'        => 'thursday',
		'manacore_air_time'       => '20:00',
		'manacore_links'          => $mc_links( 'cr' ),
	),
	array( 'genre' => array( 'درام', 'تاریخی' ), 'country' => array( 'انگلستان' ), 'quality' => array( '1080p' ) )
);

$mc_anime = $mc_post(
	array(
		'post_type'    => 'anime',
		'post_status'  => 'publish',
		'post_title'   => 'سایبرپانک',
		'post_name'    => 'cyberpunk',
		'post_content' => '<!-- wp:paragraph --><p>آینده‌ای نئونی و بی‌رحم.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'Cyberpunk: Edgerunners',
		'manacore_year'           => 2022,
		'manacore_runtime'        => 24,
		'manacore_imdb_rating'    => 8.3,
		'manacore_trailer_url'    => $mc_trailer,
		'manacore_links'          => $mc_links( 'cp' ),
	),
	array( 'genre' => array( 'انیمیشن', 'علمی‌تخیلی' ), 'country' => array( 'ژاپن' ), 'quality' => array( '1080p' ) )
);

/* قسمت‌ها: دو فصل × چهار قسمت برای سریال شوگان. */
$mc_episode_ids = array();
for ( $mc_season = 1; $mc_season <= 2; $mc_season++ ) {
	for ( $mc_number = 1; $mc_number <= 4; $mc_number++ ) {
		$mc_episode_ids[] = $mc_post(
			array(
				'post_type'    => 'episode',
				'post_status'  => 'publish',
				'post_title'   => sprintf( 'شوگان — فصل %d قسمت %d', $mc_season, $mc_number ),
				'post_name'    => sprintf( 'shogun-season-%d-episode-%d', $mc_season, $mc_number ),
				'post_content' => '<!-- wp:paragraph --><p>خلاصه‌ی این قسمت…</p><!-- /wp:paragraph -->',
			),
			array(
				'manacore_parent_title'    => $mc_series,
				'manacore_season_number'   => $mc_season,
				'manacore_episode_number'  => $mc_number,
				'manacore_episode_runtime' => 58 + $mc_number,
				'manacore_air_date'        => gmdate( 'Y-m-d', strtotime( sprintf( '2024-0%d-0%d', min( 9, $mc_season ), $mc_number ) ) ),
				'manacore_links'           => $mc_links( sprintf( 'sh-s%de%d', $mc_season, $mc_number ) ),
			),
			array( 'genre' => array( 'درام' ) )
		);
	}
}

/*
 * سریال «چرنوبیل» + قسمت اول فصل ۱.
 *
 * چرا جدا از شوگان؟ هارنس مرورگر پیوند عمیق
 * `/series/chernobyl/season-1/episode-1/` را می‌سنجد؛ این نشانی همان
 * بازنویسی «سریال/فصل/قسمت» افزونه است و باید در محیط آزمون وجود
 * داشته باشد تا آزمون ۲۰۰ بودن برگه واقعاً سنجیده شود.
 */
$mc_chernobyl = $mc_post(
	array(
		'post_type'    => 'series',
		'post_status'  => 'publish',
		'post_title'   => 'چرنوبیل',
		'post_name'    => 'chernobyl',
		'post_content' => '<!-- wp:paragraph --><p>روایت حادثه‌ی نیروگاه چرنوبیل.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title'   => 'Chernobyl',
		/*
		 * بازه‌ی «سال ساخت» سایدبار از `manacore_year` خوانده می‌شود؛ این
		 * اثر پیش‌تر فقط `manacore_release_year` داشت و در بازه‌ی سال
		 * دیده نمی‌شد.
		 */
		'manacore_year'             => 2019,
		'manacore_release_year'     => 2019,
		'manacore_imdb_rating'      => 9.4,
		'manacore_dubbed'           => 1,
		'manacore_total_episodes'   => 1,
		'manacore_series_status'    => 'پایان‌یافته',
		'manacore_air_day'          => 'monday',
		'manacore_air_time'         => '22:00',
	),
	array( 'genre' => array( 'درام', 'تاریخی' ) )
);

$mc_post(
	array(
		'post_type'    => 'episode',
		'post_status'  => 'publish',
		'post_title'   => 'چرنوبیل — فصل ۱ قسمت ۱',
		'post_name'    => 'chernobyl-season-1-episode-1',
		'post_content' => '<!-- wp:paragraph --><p>قسمت نخست.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_parent_title'    => $mc_chernobyl,
		'manacore_season_number'   => 1,
		'manacore_episode_number'  => 1,
		'manacore_episode_runtime' => 65,
		'manacore_air_date'        => '2019-05-06',
		'manacore_links'           => $mc_links( 'ch-s1e1' ),
	),
	array( 'genre' => array( 'درام' ) )
);

/* شخص. */
$mc_person = $mc_post(
	array(
		'post_type'    => 'person',
		'post_status'  => 'publish',
		'post_title'   => 'کریستوفر نولان',
		'post_name'    => 'christopher-nolan',
		'post_content' => '<!-- wp:paragraph --><p>کارگردان، نویسنده و تهیه‌کننده.</p><!-- /wp:paragraph -->',
	),
	array(
		'manacore_original_title' => 'Christopher Nolan',
		'manacore_content_type'   => 'کارگردان',
	)
);

/* نوشته‌های مجله. */
foreach ( array(
	array( 'پنج فیلمی که باید دید', 'five-movies', 'فهرستی از فیلم‌های پیشنهادی برای شب‌های پاییز.' ),
	array( 'پشت صحنه‌ی سریال شوگان', 'behind-shogun', 'روایتی از ساخت پرخرج‌ترین سریال سال.' ),
	array( 'یادداشت‌های سینمایی', 'cinema-notes', 'یادداشت‌هایی پراکنده از تماشای فیلم‌ها.' ),
) as $mc_article ) {
	$mc_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $mc_article[0],
			'post_name'    => $mc_article[1],
			'post_content' => '<!-- wp:paragraph --><p>' . $mc_article[2] . '</p><!-- /wp:paragraph -->',
		)
	);
}

/* -------------------------------------------------------------------------
 * ۳) برگه‌ها
 * ---------------------------------------------------------------------- */

foreach ( array(
	/*
	 * نامک «subscribe» است، نه «subscription»: سربرگ، پابرگ، الگوی
	 * subscribe-cta و جانشین افزونه‌ی اشتراک همه `/subscribe/` می‌سازند
	 * (`manacore_subscribe_url`). با نامک دیگر، همه‌ی آن دکمه‌ها ۴۰۴
	 * می‌شدند.
	 */
	array( 'خرید اشتراک', 'subscribe', 'پلن مناسب خودت را انتخاب کن.' ),
	array( 'دسته‌بندی‌ها', 'categories-hub', 'همه‌ی دسته‌بندی‌ها یک‌جا.' ),
	array( 'درباره ما', 'about', 'کوهه‌فیلم؛ سینما هر جا که تو باشی.' ),
	array( 'تماس با ما', 'contact', 'پیام‌ها را از همین‌جا بفرست.' ),
	array( 'کنش‌های آزمون', 'qa-actions', 'برگه‌ی آزمون بلوک‌های کنشی.' ),
	/*
	 * برگه‌ی «کشف داستان‌ها»: صفحه‌ی مرورِ آثار با نوار فیلتر کناری.
	 *
	 * نامک آن باید دقیقاً `browse` باشد؛ قالب `templates/page-browse.html`
	 * از طریق سلسله‌مراتب قالب وردپرس (`page-{slug}.html`) به همین نامک
	 * وصل می‌شود و به متای قالب نیازی نیست. اگر نامک عوض شود، برگه به
	 * قالب پیش‌فرض `page.html` برمی‌گردد و نوار فیلتر و شبکه‌ی چهارستونی
	 * از دست می‌رود.
	 */
	array( 'کشف داستان‌ها', 'browse', 'از میان ژانرها، کشورها و دنیاهای مختلف، انتخاب خودت را پیدا کن.' ),
	array( 'آزمون کشف', 'qa-browse', 'حلقه‌ی صفحه‌بندی‌شده برای آزمون «داستان‌های بیشتر».' ),
	/*
	 * برگه‌ی «برنامه پخش» (هم‌ارز `schedule.html` مرجع). مثل برگه‌ی کشف،
	 * نامک `schedule` قالب `templates/page-schedule.html` را از طریق
	 * سلسله‌مراتب قالب فرق می‌کند؛ عنوان همان `h1` مرجع است.
	 */
	array( 'هر هفته، یک قرار تماشایی.', 'schedule', 'زمان داستان‌های موردعلاقه‌ات را پیدا کن و هیچ قسمتی را از دست نده.' ),
/*
 * برگه‌ی «حساب کاربری» (هم‌ارز `account.html` مرجع).
 * نامک `account` قالب `templates/page-account.html` را از طریق
 * سلسله‌مرتبه قالب وردپرس (`page-{slug}.html`) فرق می‌کند؛
 * همان قراردادی که برگه‌های «کشف داستان‌ها» و «برنامه پخش» دارند.
 * عنوان و ریزسطر مرجع در خودِ قالب است (بلوک سرصفحه‌ی حساب).
 */
array( 'حساب کاربری', 'account', 'هر داستانی که می‌بینی، ما را یک قدم به سلیقه‌ات نزدیک‌تر می‌کند.' ),
/*
 * برگه‌ی «پخش زنده» (هم‌ارز `live.html` مرجع). نامک `live` قالب
 * `templates/page-live.html` را از سلسله‌مراتب قالب وردپرس می‌گیرد؛
 * کانال‌ها در `seed-live.php` کاشته می‌شوند.
 */
array( 'پخش زنده', 'live', 'یک تجربه تازه از تماشای پیوسته در کانال‌های ما.' ),
/*
 * برگه‌ی «بازیگران و عوامل» (هم‌ارز `cast.html` مرجع). نامک `cast` قالب
 * `templates/page-cast.html` را از سلسله‌مراتب قالب وردپرس می‌گیرد؛
 * چهره‌ها و نقش‌ها در `seed-cast.php` کاشته می‌شوند.
 */
array( 'بازیگران و عوامل', 'cast', 'از بازی‌های فراموش‌نشدنی تا نگاه‌های خلاق پشت دوربین.' ),
/*
 * برگه‌ی «سینورامگ» (هم‌ارز `magazine.html` مرجع). نامک `magazine` قالب
 * `page-magazine.html` را فعال می‌کند و مقاله‌ها/دسته‌ها را
 * `seed-magazine.php` می‌کارد.
 */
array( 'سینورامگ', 'magazine', 'پشت هر قاب، یک داستان.' ),
) as $mc_page ) {
	$mc_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $mc_page[0],
			'post_name'    => $mc_page[1],
			'post_content' => '<!-- wp:paragraph --><p>' . $mc_page[2] . '</p><!-- /wp:paragraph -->',
		)
	);
}

/*
 * برگه‌ی «کنش‌های آزمون» یک بلوک «فهرست قسمت‌ها» با سریال انتخاب‌شده
 * دارد؛ آزمون مرورگر `editor-episodes-probe.cjs` روی همین بلوک می‌سنجد
 * که انتخاب سریال در ویرایشگر، بوم را همان لحظه به‌روز می‌کند.
 */
/*
 * برگه‌ی «آزمون کشف» یک حلقه‌ی **صفحه‌بندی‌شده** دارد (چهار در هر صفحه).
 *
 * عدد چهار انتخاب شده چون داده‌ی آزمون پنج اثر دارد و نتیجه دو صفحه
 * می‌شود؛ با مقدار برگه‌ی اصلی (۲۴) همیشه یک صفحه بود و شاخه‌ی
 * «داستان‌های بیشتر» هیچ‌وقت اجرا نمی‌شد. آزمون مرورگر
 * `browse-parity.cjs` روی همین برگه دکمه را می‌زند و می‌سنجد که کارت‌ها
 * افزوده می‌شوند و پیام پایان ظاهر می‌شود.
 */
$mc_qa_browse = get_page_by_path( 'qa-browse', OBJECT, 'page' );
if ( $mc_qa_browse instanceof WP_Post ) {
	wp_update_post(
		array(
			'ID'           => $mc_qa_browse->ID,
			'post_content' => '<!-- wp:manacore/titles-grid {"heading":"آزمون صفحه‌بندی","source":"latest","postTypes":["movie","series","anime"],"count":4,"columns":4,"columnsTablet":3,"columnsMobile":2,"showFilterSummary":true,"loadMore":true,"inheritFilters":true,"showMore":false,"emptyText":"کمی از فیلترها کم کن؛ یک داستان تازه منتظرت است.","layout":"grid"} /-->',
		)
	);
}

$mc_qa_page = get_page_by_path( 'qa-actions', OBJECT, 'page' );
if ( $mc_qa_page instanceof WP_Post ) {
	$mc_qa_blocks = implode(
		"\n\n",
		array(
			'<!-- wp:manacore/episodes-list {"heading":"قسمت‌های آزمون","postId":' . (int) $mc_series . '} /-->',
			'<!-- wp:manacore/download-links {"heading":"بخش دانلود آزمون"} /-->',
			'<!-- wp:manacore/player-page /-->',
			'<!-- wp:manacore/titles-grid {"heading":"شبکه‌ی آزمون","count":6} /-->',
			'<!-- wp:manacore/rating-box /-->',
			'<!-- wp:manacore/cast-list {"heading":"بازیگران آزمون","count":6} /-->',
		)
	);
	if ( false === strpos( (string) $mc_qa_page->post_content, 'manacore/episodes-list' ) ) {
		wp_update_post(
			array(
				'ID'           => $mc_qa_page->ID,
				'post_content' => $mc_qa_blocks,
			)
		);
	}
}

/* کالکشن‌ها (نوع محتوای `collection`). */
if ( post_type_exists( 'collection' ) ) {
	foreach ( array(
		array( 'بهترین‌های ۲۰۲۴', 'best-2024', 'گزیده‌ی سال.' ),
	) as $mc_collection ) {
		$mc_post(
			array(
				'post_type'    => 'collection',
				'post_status'  => 'publish',
				'post_title'   => $mc_collection[0],
				'post_name'    => $mc_collection[1],
				'post_content' => '<!-- wp:paragraph --><p>' . $mc_collection[2] . '</p><!-- /wp:paragraph -->',
			)
		);
	}
}

/* صفحه‌ی پخش. */
if ( class_exists( 'ManaCore\Core\Player' ) ) {
	ManaCore\Core\Player::ensure_page();
}

/* -------------------------------------------------------------------------
 * ۴) دیدگاه‌ها
 * ---------------------------------------------------------------------- */

$mc_comments = array(
	array( $mc_movie, 'کیوان', 'فیلم‌برداری این اثر هنوز هم بی‌رقیب است.', 'داوری بی‌نقص و ریتمی که هرگز افت نمی‌کند.' ),
	array( $mc_movie, 'سارا', 'نسخه‌ی ۱۰۸۰p کیفیت تصویر عالی‌ای داشت.', 'پخش روان بود و صدا هم بدون مشکل.' ),
	array( $mc_movie, 'مهدی', 'کارگردانی، موسیقی و بازی‌ها همه در اوج‌اند.', 'یکی از آن فیلم‌هایی که هر چند سال باید دید.' ),
	array( $mc_series, 'نگار', 'امتیاز بازی‌ها باورپذیر است.', 'منتظر فصل بعدی‌ام.' ),
);

foreach ( $mc_comments as $mc_row ) {
	$mc_exists = get_comments(
		array(
			'post_id' => $mc_row[0],
			'author'  => $mc_row[1],
			'count'   => true,
		)
	);
	if ( $mc_exists ) {
		continue;
	}
	wp_insert_comment(
		array(
			'comment_post_ID'      => (int) $mc_row[0],
			'comment_author'       => $mc_row[1],
			'comment_author_email' => 'qa-' . md5( $mc_row[1] ) . '@example.com',
			'comment_content'      => $mc_row[2] . ' ' . $mc_row[3],
			'comment_approved'     => 1,
			'comment_date'         => gmdate( 'Y-m-d H:i:s' ),
		)
	);
}

/* -------------------------------------------------------------------------
 * ۵) تنظیمات
 * ---------------------------------------------------------------------- */

/*
 * دو دیدگاه نمونه روی قسمت اول چرنوبیل.
 *
 * بدون آن‌ها «حالت پرشده‌ی» بخش دیدگاه‌ها هرگز آزموده نمی‌شد: سرصفحه با
 * شمار دیدگاه، سطر نام/تاریخ، اسپویل درون‌متنی، اسپویل کل دیدگاه و
 * نشان «⚠ اسپویل» فقط با دیدگاه واقعی رندر می‌شوند.
 */
$mc_commented = get_page_by_path( 'chernobyl-season-1-episode-1', OBJECT, 'episode' );

if ( $mc_commented && 0 === (int) get_comments_number( $mc_commented->ID ) ) {
	$mc_first = wp_insert_comment(
		array(
			'comment_post_ID'      => $mc_commented->ID,
			'comment_author'       => 'کیوان',
			'comment_author_email' => 'keivan@example.test',
			'comment_content'      => 'قسمت اول دقیقاً همان چیزی بود که از این داستان انتظار داشتم؛ صحنه‌ی انفجار را [spoiler]سه بار پشت سر هم دیدم[/spoiler] و هنوز هم لرزه به تنم می‌اندازد.',
			'comment_approved'     => 1,
			'comment_date'         => '2026-09-28 21:14:00',
		)
	);
	update_comment_meta( $mc_first, 'manacore_comment_spoiler', 0 );

	$mc_second = wp_insert_comment(
		array(
			'comment_post_ID'      => $mc_commented->ID,
			'comment_author'       => 'مریم',
			'comment_author_email' => 'maryam@example.test',
			'comment_content'      => 'بازی‌ها و کارگردانی این قسمت فوق‌العاده بود اما پایانش برایم قابل‌پیش‌بینی بود.',
			'comment_approved'     => 1,
			'comment_date'         => '2026-10-02 19:40:00',
		)
	);
	update_comment_meta( $mc_second, 'manacore_comment_spoiler', 1 );
}

/* -------------------------------------------------------------------------
 * ۸) برنامه‌ی «امروز» — داده‌ی روزِ آزمون
 *
 * چرا لازم است: پنل برنامه‌ی صفحه‌ی نخست و تب پیش‌فرض برگه‌ی «برنامه پخش»
 * «امروز» را نشان می‌دهند. فیکسچرهای ثابت روی `thursday`/`monday` نشسته‌اند،
 * پس اگر روز آزمون یکی از آن دو نباشد تب «امروز» خالی می‌ماند و سنجش‌های
 * هندسه‌ی ردیف (پوستر، قاب پنل، نام اصلی، ردیف میانی) بی‌موضوع می‌شوند —
 * یک بار در اجرای کامل سوئیت‌ها همین اتفاق افتاد.
 *
 * نکته‌ی مهم درباره‌ی نوع اثر: بلوک برنامه‌ی صفحه‌ی نخست در قالب با
 * `postTypes:["series"]` تنظیم شده («برنامه هفتگی سریال‌ها»)، پس فقط سریال
 * در این پنل دیده می‌شود؛ زمان‌بندی فیلم برای «امروز» تب را پر نمی‌کند.
 * بنابراین اگر هیچ سریالی برای امروز زمان‌بندی نشده باشد، یک سریال
 * فیکسچرِ مخصوص آزمون ساخته می‌شود.
 *
 * تاریخ انتشار این سریال عمداً قدیمی است تا در هیچ فهرست «جدیدترین»
 * (مثل کارت ویژه‌ی صفحه‌ی نخست) جا نزند.
 * ---------------------------------------------------------------------- */
$mc_today_key = strtolower( ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'l' ) );

$mc_today_series = get_posts(
	array(
		'post_type'      => 'series',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => 'manacore_air_day',
				'value' => $mc_today_key,
			),
		),
	)
);

if ( ! $mc_today_series ) {
	$mc_post(
		array(
			'post_type'    => 'series',
			'post_status'  => 'publish',
			'post_title'   => 'شهر خاکستری',
			'post_name'    => 'gray-city',
			'post_date'    => '2021-05-04 12:00:00',
			'post_content' => '<!-- wp:paragraph --><p>مجموعه‌ای معمایی در شهری بی‌نام.</p><!-- /wp:paragraph -->',
		),
		array(
			'manacore_original_title' => 'Gray City',
			'manacore_year'           => 2021,
			'manacore_runtime'        => 52,
			'manacore_imdb_rating'    => 8.1,
			'manacore_trailer_url'    => $mc_trailer,
			'manacore_total_seasons'  => 1,
			'manacore_total_episodes' => 6,
			'manacore_dubbed'         => 1,
			'manacore_air_day'        => $mc_today_key,
			'manacore_air_time'       => '21:15',
			'manacore_links'          => $mc_links( 'gc' ),
		),
		array( 'genre' => array( 'معمایی', 'درام' ), 'country' => array( 'آمریکا' ), 'quality' => array( '1080p' ) )
	);
}

printf( "air-day today=%s\n", $mc_today_key );

update_option( 'blogname', 'Koohe Film QA' );
update_option( 'posts_per_page', 9 );
/*
 * ثبت‌نام باز است تا دکمه‌ی «ساخت حساب» برگه‌ی حساب
 * مقصد واقعی داشته باشد (رفتار مرجع).
 */
update_option( 'users_can_register', 1 );
update_option( 'thread_comments', 1 );

echo "seed ok\n";

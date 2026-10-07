<?php
/**
 * داده‌ی آزمون برگه‌ی تک‌نوشته (هم‌ارز `cinora/article.html`).
 *
 * چهار مقاله‌ی «سینورامگ» را به یک مقاله‌ی کامل تبدیل می‌کند: تصویر شاخص،
 * چکیده، **چهار فصل** (`h2` در متن)، نقل‌قول، برچسب‌ها و نوشته‌ی نویسنده.
 * هیچ داده‌ی نمایشی‌ای در قالب نیست؛ همین محتوا در وردپرس ویرایش می‌شود و
 * همه‌ی بخش‌های برگه (پاراگراف لید، فصل‌ها، فهرست، برچسب‌ها، آثار مرتبط)
 * از آن ساخته می‌شوند.
 *
 * اجرا:  wp eval-file wp/tests/qa-env/seed-article.php
 * برگشت: wp eval-file wp/tests/qa-env/seed-article.php restore
 *
 * @package KooheFilm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mc_restore        = in_array( 'restore', (array) $args, true );
$mc_backup_option  = 'manacore_qa_article_backup';

/* -------------------------------------------------------------------------
 * ۱) برگشت به حالت پیشین
 * ---------------------------------------------------------------------- */
if ( $mc_restore ) {
	$mc_backup = get_option( $mc_backup_option );

	if ( ! is_array( $mc_backup ) ) {
		echo "article restore ok — nothing to restore\n";
		return;
	}

	foreach ( (array) $mc_backup['posts'] as $mc_post ) {
		if ( ! get_post( $mc_post['ID'] ) ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $mc_post['ID'],
				'post_title'   => $mc_post['post_title'],
				'post_name'    => $mc_post['post_name'],
				'post_status'  => $mc_post['post_status'],
				'post_date'    => $mc_post['post_date'],
				'post_excerpt' => $mc_post['post_excerpt'],
				'post_content' => $mc_post['post_content'],
			)
		);

		if ( ! empty( $mc_post['author'] ) ) {
			wp_update_post(
				array(
					'ID'          => $mc_post['ID'],
					'post_author' => (int) $mc_post['author'],
				)
			);
		}

		update_post_meta( $mc_post['ID'], 'manacore_original_title', $mc_post['original'] );

		if ( $mc_post['thumb'] ) {
			set_post_thumbnail( $mc_post['ID'], (int) $mc_post['thumb'] );
		} else {
			delete_post_thumbnail( $mc_post['ID'] );
		}

		wp_set_post_tags( $mc_post['ID'], (array) $mc_post['tags'] );
	}

	echo "article restore ok — article data restored\n";
	return;
}

/* -------------------------------------------------------------------------
 * ۲) پشتیبان‌گیری
 * ---------------------------------------------------------------------- */
$mc_slugs = array( 'dune-world', 'best-series', 'nolan-time', 'koohe-archive' );
$mc_posts = array();

foreach ( $mc_slugs as $mc_slug ) {
	$mc_post = get_page_by_path( $mc_slug, OBJECT, 'post' );

	if ( ! $mc_post ) {
		echo "article seed failed — post `{$mc_slug}` not found (run seed-magazine.php first)\n";
		return;
	}

	$mc_posts[ $mc_slug ] = $mc_post;
}

$mc_backup = array( 'posts' => array() );

foreach ( $mc_posts as $mc_post ) {
	$mc_backup['posts'][] = array(
		'ID'           => $mc_post->ID,
		'post_title'   => $mc_post->post_title,
		'post_name'    => $mc_post->post_name,
		'post_status'  => $mc_post->post_status,
		'post_date'    => $mc_post->post_date,
		'post_excerpt' => $mc_post->post_excerpt,
		'post_content' => $mc_post->post_content,
		'author'       => (int) $mc_post->post_author,
		'thumb'        => (int) get_post_thumbnail_id( $mc_post->ID ),
		'tags'         => wp_list_pluck( (array) wp_get_post_tags( $mc_post->ID ), 'name' ),
		'original'     => (string) get_post_meta( $mc_post->ID, 'manacore_original_title', true ),
	);
}

/*
 * پشتیبان فقط یک‌بار نوشته می‌شود؛ اجرای دوباره‌ی seed نباید پشتیبان
 * اصیل را با حالت میانی بازنویسی کند (وگرنه `restore` بی‌اثر می‌شود).
 */
if ( ! get_option( $mc_backup_option ) ) {
	update_option( $mc_backup_option, $mc_backup, false );
}

/* -------------------------------------------------------------------------
 * ۳) تصویر شاخص (اگر مقاله تصویر ندارد، یک قاب ساختگی ساخته می‌شود)
 * ---------------------------------------------------------------------- */
if ( ! function_exists( 'mc_article_cover' ) ) {
	/**
	 * ساخت تصویر شاخص با GD.
	 *
	 * @param string $slug نامک (نام فایل).
	 * @param array  $rgb  رنگ پایه.
	 * @return int شناسه‌ی پیوست یا ۰.
	 */
	function mc_article_cover( $slug, $rgb ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$width  = 1280;
		$height = 720;
		$image  = imagecreatetruecolor( $width, $height );

		if ( ! $image ) {
			return 0;
		}

		// پس‌زمینه: گرادیان ساده از رنگ پایه به سیاه.
		for ( $y = 0; $y < $height; $y++ ) {
			$ratio = $y / $height;
			$color = imagecolorallocate(
				$image,
				(int) ( $rgb[0] * ( 1 - $ratio * 0.65 ) ),
				(int) ( $rgb[1] * ( 1 - $ratio * 0.65 ) ),
				(int) ( $rgb[2] * ( 1 - $ratio * 0.65 ) )
			);
			imageline( $image, 0, $y, $width, $y, $color );
		}

		$line = imagecolorallocate( $image, 210, 210, 210 );
		imagesetthickness( $image, 4 );
		imagerectangle( $image, 40, 40, $width - 40, $height - 40, $line );
		imagefilledrectangle( $image, 120, 420, 380, 470, $line );

		$path = wp_upload_dir()['path'] . '/mc-article-' . $slug . '.jpg';

		if ( ! imagejpeg( $image, $path, 88 ) ) {
			imagedestroy( $image );
			return 0;
		}

		imagedestroy( $image );

		$attachment = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'mc-article-' . $slug,
				'post_status'    => 'inherit',
			),
			$path
		);

		if ( is_wp_error( $attachment ) || ! $attachment ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $path ) );

		return (int) $attachment;
	}
}

/* -------------------------------------------------------------------------
 * ۴) متن مقاله‌ها: فصل‌دار، با لید و نقل‌قول
 * ---------------------------------------------------------------------- */
$mc_articles = array(
	'dune-world'   => array(
				'description' => 'از شن‌های آراکیس تا موسیقی هانس زیمر؛ نگاهی به جهان‌سازی کم‌نظیر دنی ویلنوو.',
		'author'   => 'تحریریه کوهه',
		'original' => 'Dune',
		'tags'     => array( 'سینما', 'سینورامگ', 'علمی‌تخیلی' ),
		'rgb'      => array( 46, 38, 28 ),
		'lead'     => 'بعضی فیلم‌ها را می‌بینیم و بعضی فیلم‌ها، ما را به جهانی دیگر می‌برند. تلماسه از دسته‌ی دوم است.',
		'quote'    => 'سینما فقط تماشای یک داستان نیست؛ تجربه‌کردن دنیایی است که تا پیش از آن نمی‌شناختیم.',
		'chapters' => array(
			array( 'جهانی که نفس می‌کشد', array( 'دنی ویلنوو در اقتباس خود از رمان فرانک هربرت، تنها به بازگویی داستان اکتفا نمی‌کند؛ آراکیس برای او یک پس‌زمینه نیست.', 'لباس‌ها، معماری و رفتار فرمن‌ها با دقت کنار هم چیده شده‌اند؛ همین جزئیات کوچک‌اند که یک جهان خیالی را باورپذیر می‌کنند.' ) ),
			array( 'قهرمانی در مرز انتخاب', array( 'پل آتریدس قهرمانی ساده و بی‌نقص نیست؛ او میان خواسته‌های شخصی و انتظارهای یک جامعه گرفتار است.', 'چانی در این میان نگاه انسانی داستان را حفظ می‌کند و اجازه نمی‌دهد احساسات در شکوه تصاویر گم شوند.' ) ),
			array( 'وقتی تصویر با صدا حرف می‌زند', array( 'تصویربرداری گریگ فریزر از تضاد نور و سایه برای ساختن حال‌وهوا استفاده می‌کند؛ رنگ‌های گرم آراکیس در برابر جهان‌های سرد می‌نشینند.', 'موسیقی هانس زیمر هم بخشی از هویت جهان می‌شود، نه همراهی صرف با تصویر.' ) ),
			array( 'پیش از تماشا', array( 'بهتر است ابتدا بخش اول تلماسه را ببینید تا روابط شخصیت‌ها و زمینه‌ی سیاسی داستان روشن‌تر باشد.', 'تلماسه یادآوری می‌کند که سینمای جریان اصلی هم می‌تواند جسور و تأمل‌برانگیز باشد.' ) ),
		),
	),
	'best-series'  => array(
				'description' => 'داستان‌هایی که شروعشان دست شماست، اما پایانشان را نمی‌توانید کنار بگذارید.',
		'author'   => 'تحریریه کوهه',
		'original' => 'Best Series',
		'tags'     => array( 'سریال', 'سینورامگ', 'پیشنهاد تماشا' ),
		'rgb'      => array( 40, 32, 48 ),
		'lead'     => 'پیدا کردن سریالی که هم شخصیت‌هایش ماندگار باشند و هم داستانش کشش داشته باشد، همیشه ساده نیست.',
		'quote'    => 'سریال خوب، شبی نیست که تمام شود؛ هفته‌ها با شما می‌ماند.',
		'chapters' => array(
			array( 'با یک جهان تازه شروع کن', array( 'شوگان ما را به ژاپن آغاز قرن هفدهم می‌برد؛ جزئیات فرهنگی و روابط پیچیده‌ی شخصیت‌ها تجربه‌ای متفاوت می‌سازند.', 'اگر دنیایی دیگر می‌خواهید، آرکین با زبان بصری خاص خود گزینه‌ی خوبی است.' ) ),
			array( 'شخصیت‌هایی که واقعی به نظر می‌رسند', array( 'شخصیت‌های درام‌های خانوادگی امروز، بیشتر از آن‌که دوست‌داشتنی باشند، پیچیده‌اند و همین آن‌ها را باورپذیر می‌کند.' ) ),
			array( 'برای دوستداران معما', array( 'سریال‌هایی که هر جزئیات کوچک در آن‌ها بخشی از یک تصویر بزرگ‌تر است، برای تماشای فعال ساخته شده‌اند.' ) ),
			array( 'داستان‌هایی درباره‌ی انتخاب', array( 'قدرت روایت‌های امروزی در این است که تصمیم‌ها را بدون پیامد رها نمی‌کنند.' ) ),
		),
	),
	'nolan-time'   => array(
				'description' => 'چطور نولان با زمان بازی می‌کند و ما را به بخشی از روایت تبدیل می‌کند؟',
		'author'   => 'تحریریه کوهه',
		'original' => 'Nolan and Time',
		'tags'     => array( 'کارگردان', 'سینورامگ', 'تحلیل' ),
		'rgb'      => array( 30, 44, 36 ),
		'lead'     => 'در فیلم‌های کریستوفر نولان، زمان فقط یک خط مستقیم نیست؛ گاهی کش می‌آید و گاهی با حافظه گره می‌خورد.',
		'quote'    => 'وقتی ترتیب روایت می‌شکند، تماشاگر از شاهد به مشارکت‌کننده تبدیل می‌شود.',
		'chapters' => array(
			array( 'زمان به‌عنوان ساختار', array( 'نولان اغلب ترتیب روایت را تغییر می‌دهد تا ما اطلاعات را هم‌زمان با شخصیت‌ها کشف کنیم؛ این یک ترفند نیست، راهی برای نزدیک‌شدن به تجربه‌ی ذهنی آن‌هاست.' ) ),
			array( 'رؤیا، حافظه و تلقین', array( 'در تلقین، زمان در هر لایه ریتم متفاوتی دارد؛ یک لحظه در جهان بیرون می‌تواند در ذهن به تجربه‌ای طولانی بدل شود.' ) ),
			array( 'میان‌ستاره‌ای و فاصله‌ی عاطفی', array( 'زمان ازدست‌رفته در میان‌ستاره‌ای فقط یک عدد نیست؛ خاطراتی است که بازنمی‌گردد.' ) ),
			array( 'اوپنهایمر؛ روایت در چند مسیر', array( 'اوپنهایمر از مسیرهای زمانی متفاوت برای کنارهم‌گذاشتن تجربه‌ی شخصی و قضاوت تاریخی استفاده می‌کند.' ) ),
		),
	),
	'koohe-archive' => array(
				'description' => 'پنج انتخاب از قفسه‌ی کوهه که تماشای دوباره‌شان هر سال ارزش دارد.',
		'author'   => 'تحریریه کوهه',
		'original' => 'Koohe Archive',
		'tags'     => array( 'بایگانی', 'سینورامگ', 'فیلم' ),
		'rgb'      => array( 44, 36, 30 ),
		'lead'     => 'پنج انتخاب از قفسه‌ی کوهه که تماشای دوباره‌شان هر سال ارزش دارد.',
		'quote'    => 'بعضی فیلم‌ها را یک‌بار می‌بینیم، بعضی را هر سال.',
		'chapters' => array(
			array( 'چرا بایگانی؟', array( 'فهرست‌های «بهترین‌ها» زود پیر می‌شوند؛ بایگانی اما با شما رشد می‌کند و هر سال چیز تازه‌ای به آن اضافه می‌شود.' ) ),
			array( 'پنج انتخاب امسال', array( 'از درام‌های خانوادگی تا علمی‌تخیلی‌های آرام؛ پنج اثر که تماشای دوباره‌شان چیز تازه‌ای نشان می‌دهد.' ) ),
			array( 'چطور تماشا کنیم', array( 'یک شب در هفته را به بایگانی بدهید و همان برنامه را با دوستانتان قسمت کنید؛ تجربه‌ی تماشا با گفت‌وگو کامل می‌شود.' ) ),
			array( 'سال بعد', array( 'هر سال دو انتخاب از فهرست بیرون می‌رود و دو انتخاب تازه وارد می‌شود تا فهرست زنده بماند.' ) ),
		),
	),
);

$mc_seeded = 0;

foreach ( $mc_articles as $mc_slug => $mc_article ) {
	$mc_post = $mc_posts[ $mc_slug ];

	$mc_body  = '<!-- wp:paragraph -->' . "\n";
	$mc_body .= '<p>' . $mc_article['lead'] . '</p>' . "\n";
	$mc_body .= '<!-- /wp:paragraph -->' . "\n\n";
	$mc_body .= '<!-- wp:quote -->' . "\n";
	$mc_body .= '<blockquote class=\"wp-block-quote\"><p>' . $mc_article['quote'] . '</p></blockquote>' . "\n";
	$mc_body .= '<!-- /wp:quote -->' . "\n\n";

	foreach ( $mc_article['chapters'] as $mc_chapter ) {
		$mc_body .= '<!-- wp:heading {"level":2} -->' . "\n";
		$mc_body .= '<h2 class="wp-block-heading">' . $mc_chapter[0] . '</h2>' . "\n";
		$mc_body .= '<!-- /wp:heading -->' . "\n\n";

		foreach ( $mc_chapter[1] as $mc_paragraph ) {
			$mc_body .= '<!-- wp:paragraph -->' . "\n<p>" . $mc_paragraph . '</p>' . "\n<!-- /wp:paragraph -->\n\n";
		}
	}

	wp_update_post(
		array(
			'ID'           => $mc_post->ID,
			'post_content' => $mc_body,
			'post_excerpt' => $mc_article['description'],
		)
	);

	$mc_author = get_user_by( 'login', 'admin' );

	if ( $mc_author ) {
		wp_update_post(
			array(
				'ID'          => $mc_post->ID,
				'post_author' => (int) $mc_author->ID,
			)
		);
	}

	update_post_meta( $mc_post->ID, 'manacore_original_title', $mc_article['original'] );
	wp_set_post_tags( $mc_post->ID, $mc_article['tags'], false );

	if ( ! has_post_thumbnail( $mc_post->ID ) ) {
		$mc_thumb = mc_article_cover( $mc_slug, $mc_article['rgb'] );

		if ( $mc_thumb ) {
			set_post_thumbnail( $mc_post->ID, $mc_thumb );
		}
	}

	$mc_seeded++;
}

echo "article seed ok — articles={$mc_seeded} · chapters=" . count( $mc_articles['dune-world']['chapters'] ) . "\n";

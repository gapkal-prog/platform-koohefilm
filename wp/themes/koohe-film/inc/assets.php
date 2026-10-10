<?php
/**
 * بارگذاری دارایی‌های قالب.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * شماره‌ی نسخه بر پایه‌ی زمان تغییر فایل (برای کش‌شکنی در توسعه).
 *
 * @param string $relative مسیر نسبی فایل.
 * @return string
 */
function koohe_asset_version( $relative ) {
	$path = KOOHE_DIR . ltrim( $relative, '/' );
	if ( file_exists( $path ) ) {
		return KOOHE_VERSION . '.' . (string) filemtime( $path );
	}
	return KOOHE_VERSION;
}

/**
 * استایل و اسکریپت سمت کاربر.
 */
/**
 * فهرست ژانرهای پرمحتوا برای بخش «ژانرها» در پوسته‌ی جستجو.
 *
 * نتیجه کش ترنزینت می‌شود و با ایجاد، ویرایش یا حذف ژانر پاک می‌شود، تا
 * هر بارگذاری صفحه کوئری تازه نزند.
 *
 * @return array<int,array{name:string,url:string}>
 */
function koohe_search_genres() {
	$cached = get_transient( 'koohe_search_genres' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'genre',
			'hide_empty' => true,
			'number'     => 12,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	$genres = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$url = get_term_link( $term );

			if ( is_string( $url ) ) {
				$genres[] = array(
					'name' => $term->name,
					'url'  => $url,
				);
			}
		}
	}

	set_transient( 'koohe_search_genres', $genres, 12 * HOUR_IN_SECONDS );

	return $genres;
}

/** پاک‌کردن کش ژانرهای پوسته‌ی جستجو با هر تغییر در ترم‌های ژانر. */
function koohe_flush_search_genres() {
	delete_transient( 'koohe_search_genres' );
}
add_action( 'created_genre', 'koohe_flush_search_genres' );
add_action( 'edited_genre', 'koohe_flush_search_genres' );
add_action( 'delete_genre', 'koohe_flush_search_genres' );

function koohe_enqueue_front() {
	wp_enqueue_style(
		'koohe-film',
		KOOHE_URI . 'assets/css/theme.css',
		array(),
		koohe_asset_version( 'assets/css/theme.css' )
	);

	/*
	 * توجه: theme.css به‌طور کامل دوسویه (bidirectional) نوشته شده است؛
	 * از خصوصیات منطقی (inset-inline, margin-inline, padding-inline) و
	 * انتخاب‌گرهای [dir="rtl"] / [dir="ltr"] استفاده می‌کند. بنابراین به
	 * فایل جداگانه‌ی theme-rtl.css نیازی نیست و wp_style_add_data( 'rtl' )
	 * فراخوانی نمی‌شود تا درخواست ۴۰۴ ایجاد نشود.
	 * در صورت نیاز به بازنویسی راست‌به‌چپ اختصاصی، فایل زیر افزوده می‌شود.
	 */
	$rtl_file = KOOHE_DIR . 'assets/css/theme-rtl.css';
	if ( is_rtl() && file_exists( $rtl_file ) ) {
		wp_enqueue_style(
			'koohe-film-rtl',
			KOOHE_URI . 'assets/css/theme-rtl.css',
			array( 'koohe-film' ),
			koohe_asset_version( 'assets/css/theme-rtl.css' )
		);
	}

	/* فونت محلی وزیرمتن در صورت وجود، در غیر این صورت CDN. */
	$local_font = KOOHE_DIR . 'assets/fonts/vazirmatn.css';
	if ( file_exists( $local_font ) ) {
		wp_enqueue_style(
			'koohe-font',
			KOOHE_URI . 'assets/fonts/vazirmatn.css',
			array(),
			koohe_asset_version( 'assets/fonts/vazirmatn.css' )
		);
	} elseif ( apply_filters( 'koohe_load_remote_font', true ) ) {
		wp_enqueue_style(
			'koohe-font',
			'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		);
	}

	wp_enqueue_script(
		'koohe-film',
		KOOHE_URI . 'assets/js/theme.js',
		array(),
		koohe_asset_version( 'assets/js/theme.js' ),
		true
	);

	$has_core = function_exists( 'manacore_get_option' );

	wp_localize_script(
		'koohe-film',
		'kooheFilm',
		array(
			'restUrl'     => esc_url_raw( rest_url( 'manacore/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'defaultMode' => koohe_default_color_mode(),
			'hasCore'     => $has_core,

			/*
			 * نشانی‌های جستجو.
			 *
			 * پیش‌تر `theme.js` نشانی را دستی می‌ساخت (`restUrl` + مسیر
			 * `wp/v2/search`) و چون `restUrl` فضای‌نام افزونه است، درخواست
			 * به `…/wp-json/manacore/v1/wp/v2/search` می‌رفت و ۴۰۴ می‌گرفت؛
			 * خطا هم بلعیده می‌شد و پیام «پیدا نکردیم» نمایش داده می‌شد.
			 * حالا مسیرها از اینجا می‌آید و اگر افزونه فعال نباشد خالی
			 * می‌ماند تا جستجوی زنده اجرا نشود و فرم به برگه‌ی جستجو برود.
			 */
			'searchUrl'   => $has_core ? esc_url_raw( rest_url( 'manacore/v1/search' ) ) : '',
			'titlesUrl'   => $has_core ? esc_url_raw( rest_url( 'manacore/v1/titles' ) ) : '',
			'searchPage'  => esc_url_raw( home_url( '/' ) ),
			'genres'      => koohe_search_genres(),
			'i18n'        => array(
				'toDark'    => __( 'حالت تیره', 'koohe-film' ),
				'toLight'   => __( 'حالت روشن', 'koohe-film' ),
				'menu'      => __( 'فهرست', 'koohe-film' ),
				'close'     => __( 'بستن', 'koohe-film' ),
				'top'       => __( 'بازگشت به بالا', 'koohe-film' ),
				'loading'   => __( 'در حال بارگذاری…', 'koohe-film' ),
				'noResult'  => __( 'نتیجه‌ای یافت نشد.', 'koohe-film' ),
				'searching' => __( 'در حال جستجو…', 'koohe-film' ),
				'error'     => __( 'جستجو انجام نشد. یک‌بار دیگر تلاش کنید.', 'koohe-film' ),
				/* translators: %s: شمار نتایج. */
				'results'   => __( '%s نتیجه پیشنهادی', 'koohe-film' ),
				'trending'  => __( 'این روزها بیشتر جستجو می‌شوند', 'koohe-film' ),
				'genres'    => __( 'ژانرها', 'koohe-film' ),
				'history'   => __( 'تاریخچه‌ی جستجو', 'koohe-film' ),
				'clear'     => __( 'پاک‌کردن', 'koohe-film' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'koohe_enqueue_front' );

/**
	 * CSS متغیرهای ظاهر سفارشی‌ساز.
	 *
	 * رنگ تأکید، عرض چیدمان و مقیاس شعاع گوشه‌ها از پنل «کوه فیلم» می‌آیند و
	 * به‌صورت متغیرهای CSS روی `html:root` بازنویسی می‌شوند.
	 * خاص‌بودن برگزیننده (html:root) و قرارگرفتن این قواعد بعد از
	 * شیوه‌نامه‌ی theme.json تضمین می‌کند که همیشه بر مقدار پیش‌فرض غلبه کنند؛
	 * theme.css همان متغیرها را با var() می‌خواند.
	 *
	 * نکته: هیچ استایل درون‌خطی دیگری در صفحه تزریق نمی‌شود.
	 */
function koohe_appearance_inline_css() {
	$rules = array();

	$accent = sanitize_hex_color( get_theme_mod( 'koohe_accent_color', '' ) );
	if ( $accent && function_exists( 'koohe_accent_contrast' ) ) {
		$rules[] = '--wp--preset--color--accent:' . $accent;
		$rules[] = '--wp--preset--color--accent-contrast:' . koohe_accent_contrast( $accent );
	}

	if ( function_exists( 'koohe_layout_width_choices' ) ) {
		$widths = koohe_layout_width_choices();
		$width  = get_theme_mod( 'koohe_layout_width', 'standard' );
		if ( isset( $widths[ $width ] ) ) {
			$rules[] = '--wp--style--global--content-size:' . $widths[ $width ]['content'];
			$rules[] = '--wp--style--global--wide-size:' . $widths[ $width ]['wide'];
		}
	}

	if ( function_exists( 'koohe_radius_scale_choices' ) ) {
		$scales = koohe_radius_scale_choices();
		$scale  = get_theme_mod( 'koohe_radius_scale', 'standard' );
		if ( isset( $scales[ $scale ] ) ) {
			$rules[] = '--wp--custom--radius--sm:' . $scales[ $scale ]['sm'];
			$rules[] = '--wp--custom--radius--base:' . $scales[ $scale ]['base'];
			$rules[] = '--wp--custom--radius--lg:' . $scales[ $scale ]['lg'];
		}
	}

	if ( ! $rules ) {
		return;
	}

	wp_add_inline_style( 'koohe-film', 'html:root{' . implode( ';', $rules ) . '}' );
}
add_action( 'wp_enqueue_scripts', 'koohe_appearance_inline_css', 20 );

/**
 * استایل ویرایشگر بلوک.
 */
function koohe_enqueue_editor() {
	wp_enqueue_style(
		'koohe-editor',
		KOOHE_URI . 'assets/css/editor.css',
		array(),
		koohe_asset_version( 'assets/css/editor.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'koohe_enqueue_editor' );

/**
 * حذف استایل اضافی هسته که در قالب مینیمال نیاز نیست.
 */
function koohe_dequeue_extras() {
	if ( ! apply_filters( 'koohe_remove_global_styles_svg', false ) ) {
		return;
	}
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
}
add_action( 'init', 'koohe_dequeue_extras' );

/**
 * افزودن preconnect برای CDN فونت.
 *
 * @param array  $urls  آدرس‌ها.
 * @param string $relation نوع رابطه.
 * @return array
 */
function koohe_resource_hints( $urls, $relation ) {
	if ( 'preconnect' === $relation && wp_style_is( 'koohe-font', 'enqueued' ) ) {
		$urls[] = array(
			'href'        => 'https://cdn.jsdelivr.net',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'koohe_resource_hints', 10, 2 );

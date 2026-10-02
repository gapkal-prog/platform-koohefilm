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

	wp_localize_script(
		'koohe-film',
		'kooheFilm',
		array(
			'restUrl'     => esc_url_raw( rest_url( 'manacore/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'defaultMode' => koohe_default_color_mode(),
			'hasCore'     => function_exists( 'manacore_get_option' ),
			'i18n'        => array(
				'toDark'   => __( 'حالت تیره', 'koohe-film' ),
				'toLight'  => __( 'حالت روشن', 'koohe-film' ),
				'menu'     => __( 'فهرست', 'koohe-film' ),
				'close'    => __( 'بستن', 'koohe-film' ),
				'top'      => __( 'بازگشت به بالا', 'koohe-film' ),
				'loading'  => __( 'در حال بارگذاری…', 'koohe-film' ),
				'noResult' => __( 'نتیجه‌ای یافت نشد.', 'koohe-film' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'koohe_enqueue_front' );

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

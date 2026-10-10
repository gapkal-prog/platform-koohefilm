<?php
/**
 * راه‌اندازی اولیه‌ی قالب.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * پشتیبانی‌های قالب.
 */
function koohe_setup() {
	load_theme_textdomain( 'koohe-film', KOOHE_DIR . 'languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-line-height' );
	add_theme_support( 'custom-spacing' );
	add_theme_support( 'custom-units' );
	add_theme_support( 'appearance-tools' );
	add_theme_support( 'border' );
	add_theme_support( 'link-color' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );

	/*
	 * استایل ویرایشگر.
	 *
	 * theme.css نخست بارگذاری می‌شود تا «ویرایشگر سایت» همان طراحی
	 * سمت کاربر را نشان دهد (پیش‌تر فقط editor.css بارگذاری می‌شد و
	 * بوم ویرایشگر بدون استایل می‌ماند). editor.css پس از آن می‌آید تا
	 * بازنویسی‌های ویژه‌ی ویرایشگر اولویت داشته باشند.
	 */
	add_editor_style(
		array(
			'assets/css/theme.css',
			'assets/css/editor.css',
		)
	);

	/* اندازه‌های تصویر اختصاصی برای پوستر و بک‌دراپ. */
	add_image_size( 'koohe-poster', 400, 600, true );
	add_image_size( 'koohe-poster-sm', 200, 300, true );
	add_image_size( 'koohe-backdrop', 1600, 900, true );
	add_image_size( 'koohe-thumb', 480, 270, true );

	/* منوهای کلاسیک برای سازگاری با افزونه‌هایی که به آن نیاز دارند. */
	register_nav_menus(
		array(
			'primary' => __( 'فهرست اصلی', 'koohe-film' ),
			'footer'  => __( 'فهرست پابرگ', 'koohe-film' ),
			'mobile'  => __( 'فهرست موبایل', 'koohe-film' ),
		)
	);
}
add_action( 'after_setup_theme', 'koohe_setup' );

/**
 * افزودن اندازه‌های تصویر به انتخابگر رسانه.
 *
 * @param array $sizes اندازه‌ها.
 * @return array
 */
function koohe_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'koohe-poster'   => __( 'پوستر (۲:۳)', 'koohe-film' ),
			'koohe-backdrop' => __( 'بک‌دراپ (۱۶:۹)', 'koohe-film' ),
			'koohe-thumb'    => __( 'بندانگشتی', 'koohe-film' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'koohe_image_size_names' );

/**
 * ثبت نواحی ابزارک (برای سازگاری با افزونه‌های قدیمی‌تر).
 */
function koohe_widgets() {
	register_sidebar(
		array(
			'name'          => __( 'نوار کناری', 'koohe-film' ),
			'id'            => 'koohe-sidebar',
			'description'   => __( 'ابزارک‌های نوار کناری آرشیوها و نوشته‌ها.', 'koohe-film' ),
			'before_widget' => '<section id="%1$s" class="widget koohe-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title koohe-widget-title">',
			'after_title'   => '</h3>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'پابرگ', 'koohe-film' ),
			'id'            => 'koohe-footer',
			'description'   => __( 'ابزارک‌های ستون پابرگ.', 'koohe-film' ),
			'before_widget' => '<section id="%1$s" class="widget koohe-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title koohe-widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'koohe_widgets' );

/**
 * اعمال حالت رنگ ذخیره‌شده پیش از رندر (جلوگیری از پرش رنگ).
 */
function koohe_color_mode_script() {
	$default = koohe_default_color_mode();
	?>
	<script id="koohe-color-mode">
	( function () {
		var d = <?php echo wp_json_encode( $default ); ?>;
		var m = d;
		try {
			var s = window.localStorage.getItem( 'manacore-color-mode' );
			if ( s === 'dark' || s === 'light' ) {
				m = s;
			} else if ( d === 'auto' ) {
				m = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
			}
		} catch ( e ) {
			if ( d === 'auto' ) { m = 'light'; }
		}
		document.documentElement.setAttribute( 'data-color-mode', m );
	} )();
	</script>
	<?php
}
add_action( 'wp_head', 'koohe_color_mode_script', 1 );

/**
 * حالت رنگ پیش‌فرض از تنظیمات ManaCore یا سفارشی‌ساز.
 *
 * @return string light|dark|auto
 */
function koohe_default_color_mode() {
	$mode = '';

	if ( function_exists( 'manacore_get_option' ) ) {
		/*
		 * کلید «default_color_mode» است — همان کلیدی که تب «ظاهر و استایل»
		 * افزونه ذخیره می‌کند. پیش‌تر این‌جا «color_mode» خوانده می‌شد که
		 * هیچ‌گاه ذخیره نمی‌شد و تنظیم افزونه بی‌اثر می‌ماند.
		 */
		$mode = (string) manacore_get_option( 'default_color_mode', '' );
	}

	if ( '' === $mode ) {
		$mode = (string) get_theme_mod( 'koohe_color_mode', 'dark' );
	}

	return in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ? $mode : 'dark';
}

/**
 * افزودن کلاس‌های مفید به بدنه.
 *
 * @param array $classes کلاس‌ها.
 * @return array
 */
function koohe_body_class( $classes ) {
	$classes[] = 'koohe-film';

	if ( is_singular() && function_exists( 'manacore_title_post_types' ) ) {
		$types = (array) manacore_title_post_types();
		if ( in_array( get_post_type(), $types, true ) ) {
			$classes[] = 'koohe-single-title';
			$classes[] = 'koohe-type-' . sanitize_html_class( get_post_type() );
		}
	}

	if ( is_active_sidebar( 'koohe-sidebar' ) ) {
		$classes[] = 'koohe-has-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'koohe_body_class' );

/**
 * پشتیبانی از RTL خودکار برای فایل استایل.
 *
 * @param string $classes کلاس html.
 * @return string
 */
function koohe_html_dir( $classes ) {
	return $classes;
}
add_filter( 'language_attributes', 'koohe_html_dir' );

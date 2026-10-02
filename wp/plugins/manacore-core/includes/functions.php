<?php
/**
 * توابع کمکی عمومی ManaCore.
 *
 * @package ManaCore\Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * دریافت یک تنظیم از تنظیمات افزونه.
 *
 * @param string $key     کلید.
 * @param mixed  $default مقدار پیش‌فرض.
 * @return mixed
 */
function manacore_get_option( $key, $default = '' ) {
	$options = get_option( 'manacore_settings', array() );
	return isset( $options[ $key ] ) && '' !== $options[ $key ] ? $options[ $key ] : $default;
}

/**
 * فهرست انواع محتوای مدیریت‌شده توسط ManaCore.
 *
 * @return array<string,string>
 */
function manacore_post_types() {
	return apply_filters(
		'manacore_post_types',
		array(
			'movie'   => __( 'فیلم', 'manacore' ),
			'series'  => __( 'سریال', 'manacore' ),
			'anime'   => __( 'انیمه', 'manacore' ),
			'episode' => __( 'قسمت', 'manacore' ),
		)
	);
}

/**
 * انواع محتوایی که «اثر» محسوب می‌شوند (بدون قسمت).
 *
 * @return string[]
 */
function manacore_title_post_types() {
	return apply_filters( 'manacore_title_post_types', array( 'movie', 'series', 'anime' ) );
}

/**
 * انواع محتوایی که فصل و قسمت دارند.
 *
 * @return string[]
 */
function manacore_serial_post_types() {
	return apply_filters( 'manacore_serial_post_types', array( 'series', 'anime' ) );
}

/**
 * نگاشت تاکسونومی به پارامتر آدرس برای فیلترهای آرشیو.
 *
 * تنها منبع حقیقت است: هم نوار فیلتر (رندر فیلدها) و هم کلاس Query
 * (اعمال روی کوئری اصلی) از همین فهرست استفاده می‌کنند تا هیچ فیلتری
 * بی‌اثر نماند.
 *
 * @return array<string,string> کلید: نام تاکسونومی، مقدار: نام پارامتر آدرس.
 */
function manacore_filter_params() {
	/**
	 * فیلتر نگاشت تاکسونومی به پارامتر آدرس.
	 *
	 * @param array<string,string> $map نگاشت.
	 */
	return (array) apply_filters(
		'manacore_filter_bar_params',
		array(
			'genre'        => 'mc_genre',
			'country'      => 'mc_country',
			'release_year' => 'mc_year',
			'quality'      => 'mc_quality',
			'network'      => 'mc_network',
			'studio'       => 'mc_studio',
			'language'     => 'mc_lang',
			'category'     => 'mc_cat',
			'post_tag'     => 'mc_tag',
		)
	);
}

/**
 * فهرست حالت‌های مرتب‌سازی پشتیبانی‌شده در آرشیوها.
 *
 * @return array<string,string> کلید: مقدار پارامتر mc_sort، مقدار: برچسب.
 */
function manacore_sort_options() {
	/**
	 * فیلتر گزینه‌های مرتب‌سازی آرشیو.
	 *
	 * @param array<string,string> $options گزینه‌ها.
	 */
	return (array) apply_filters(
		'manacore_sort_options',
		array(
			''       => __( 'پیش‌فرض', 'manacore' ),
			'newest' => __( 'جدیدترین', 'manacore' ),
			'oldest' => __( 'قدیمی‌ترین', 'manacore' ),
			'rating' => __( 'بیشترین امتیاز', 'manacore' ),
			'views'  => __( 'پربازدیدترین', 'manacore' ),
			'year'   => __( 'سال انتشار', 'manacore' ),
			'title'  => __( 'حروف الفبا', 'manacore' ),
		)
	);
}

/**
 * نشانی پایه‌ی آرشیو جاری، بدون بخش صفحه‌بندی و بدون رشته‌ی پرسمان.
 *
 * برای دکمه‌ی «پاک‌سازی» و ویژگی action فرم فیلتر به کار می‌رود؛ بدون این
 * کار، اعمال فیلتر از صفحه‌ی دوم کاربر را روی «page/2» نگه می‌دارد و اگر
 * نتیجه‌ی فیلترشده کمتر از دو صفحه باشد خطای ۴۰۴ رخ می‌دهد.
 *
 * @return string
 */
function manacore_archive_base_url() {
	$base = '';

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$base = (string) $link;
			}
		}
	} elseif ( is_post_type_archive() ) {
		$type = get_query_var( 'post_type' );
		$type = is_array( $type ) ? reset( $type ) : $type;
		$link = $type ? get_post_type_archive_link( (string) $type ) : false;
		if ( $link ) {
			$base = (string) $link;
		}
	} elseif ( is_search() ) {
		$base = (string) get_search_link( '' );
	} elseif ( is_author() ) {
		$base = (string) get_author_posts_url( (int) get_query_var( 'author' ) );
	} elseif ( is_home() && ! is_front_page() ) {
		$page_for_posts = (int) get_option( 'page_for_posts' );
		if ( $page_for_posts ) {
			$base = (string) get_permalink( $page_for_posts );
		}
	}

	if ( ! $base ) {
		// جایگزین امن: مسیر درخواست جاری با حذف بخش صفحه‌بندی و پرسمان.
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = strtok( (string) $path, '?' );
		$base = home_url( (string) $path );
	}

	// حذف /page/N/ یا ?paged=N از پایه.
	$base = (string) remove_query_arg( array( 'paged', 'page' ), $base );
	$base = (string) preg_replace( '#/page/\d+/?#', '/', $base );

	return $base;
}

/**
 * پارامترهای فیلتر موجود در آدرس جاری.
 *
 * @return array<string,string> کلید: نام پارامتر، مقدار: مقدار پاک‌سازی‌شده.
 */
function manacore_active_filters() {
	$active = array();
	$params = array_values( manacore_filter_params() );
	$params[] = 'mc_sort';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	foreach ( $params as $param ) {
		if ( empty( $_GET[ $param ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_GET[ $param ] );

		if ( 'mc_sort' === $param ) {
			$value = sanitize_key( (string) $raw );
			if ( '' !== $value ) {
				$active[ $param ] = $value;
			}
			continue;
		}

		/*
		 * پاک‌سازی هر نامک به‌طور جداگانه با sanitize_title.
		 *
		 * نامک ترم‌های فارسی در ووردپرس به شکل درصدرمزگذاری‌شده ذخیره می‌شود
		 * (مثل %d9%81%db%8c%d9%84%d9%85). تابع sanitize_text_field این هشت‌گانه‌ها
		 * را نابود می‌کند و رشته را به «-» فرو می‌کاهد، در نتیجه برچسب فیلتر و
		 * پیوند حذف آن می‌شکست. sanitize_title این شکل را دست‌نخورده نگه می‌دارد.
		 */
		$slugs = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$slugs = array_filter( array_map( 'sanitize_title', $slugs ) );

		if ( $slugs ) {
			$active[ $param ] = implode( ',', $slugs );
		}
	}
	// phpcs:enable

	return $active;
}

/**
 * فهرست کیفیت‌های قابل انتخاب برای لینک دانلود.
 *
 * @return array<string,string>
 */
function manacore_qualities() {
	$defaults = array(
		'360p'   => '360p',
		'480p'   => '480p',
		'720p'   => '720p',
		'1080p'  => '1080p',
		'1080pX' => '1080p x265',
		'2160p'  => '4K 2160p',
		'HDR'    => 'HDR',
		'DV'     => 'Dolby Vision',
		'BluRay' => 'BluRay',
		'WEBDL'  => 'WEB-DL',
		'WEBRip' => 'WEBRip',
		'HDTV'   => 'HDTV',
		'CAM'    => 'CAM',
	);

	$custom = manacore_get_option( 'custom_qualities', '' );
	if ( $custom ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $custom ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$key   = sanitize_key( $parts[0] );
			if ( $key ) {
				$defaults[ $key ] = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : $parts[0];
			}
		}
	}

	return apply_filters( 'manacore_qualities', $defaults );
}

/**
 * فهرست نوع زبان/دوبله لینک.
 *
 * @return array<string,string>
 */
function manacore_languages() {
	return apply_filters(
		'manacore_languages',
		array(
			'sub_fa'    => __( 'زیرنویس فارسی چسبیده', 'manacore' ),
			'soft_fa'   => __( 'زیرنویس فارسی جدا', 'manacore' ),
			'dub_fa'    => __( 'دوبله فارسی', 'manacore' ),
			'dual'      => __( 'دو زبانه', 'manacore' ),
			'original'  => __( 'زبان اصلی', 'manacore' ),
			'censored'  => __( 'سانسور شده', 'manacore' ),
			'uncut'     => __( 'بدون سانسور', 'manacore' ),
		)
	);
}

/**
 * فهرست نوع لینک.
 *
 * @return array<string,string>
 */
function manacore_link_types() {
	return apply_filters(
		'manacore_link_types',
		array(
			'direct'  => __( 'دانلود مستقیم', 'manacore' ),
			'stream'  => __( 'پخش آنلاین', 'manacore' ),
			'torrent' => __( 'تورنت', 'manacore' ),
			'magnet'  => __( 'مگنت', 'manacore' ),
			'subtitle'=> __( 'زیرنویس', 'manacore' ),
			'external'=> __( 'لینک خارجی', 'manacore' ),
		)
	);
}

/**
 * تبدیل بایت به شکل خوانا.
 *
 * @param string $size اندازه‌ی متنی وارد شده توسط کاربر.
 * @return string
 */
function manacore_format_size( $size ) {
	$size = trim( (string) $size );
	if ( '' === $size ) {
		return '';
	}
	if ( is_numeric( $size ) ) {
		return size_format( (float) $size * MB_IN_BYTES, 1 );
	}
	return $size;
}

/**
 * دریافت گروه‌های لینک دانلود یک پست.
 *
 * @param int $post_id شناسه‌ی پست.
 * @return array
 */
function manacore_get_links( $post_id ) {
	return ManaCore\Core\Links::get( $post_id );
}

/**
 * آیا کاربر جاری اجازه‌ی دیدن لینک‌های این پست را دارد؟
 * افزونه‌ی اشتراک با این فیلتر تصمیم می‌گیرد.
 *
 * @param int $post_id شناسه‌ی پست.
 * @return bool
 */
function manacore_user_can_access( $post_id ) {
	return (bool) apply_filters( 'manacore_user_can_access', true, $post_id, get_current_user_id() );
}

/**
 * نمایش امتیاز به صورت درصد برای نوار پیشرفت.
 *
 * @param float $rating امتیاز از ۱۰.
 * @return float
 */
function manacore_rating_percent( $rating ) {
	$rating = (float) $rating;
	return max( 0, min( 100, $rating * 10 ) );
}

/**
 * دریافت پوستر پست با نسخه‌ی جایگزین.
 *
 * @param int    $post_id شناسه‌ی پست.
 * @param string $size    اندازه‌ی تصویر.
 * @return string
 */
function manacore_poster_url( $post_id, $size = 'medium_large' ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return (string) get_the_post_thumbnail_url( $post_id, $size );
	}
	$remote = get_post_meta( $post_id, 'manacore_poster_url', true );
	if ( $remote ) {
		return esc_url_raw( $remote );
	}
	return MANACORE_URL . 'assets/placeholder.svg';
}

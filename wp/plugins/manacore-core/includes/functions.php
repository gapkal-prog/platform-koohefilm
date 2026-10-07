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
 * نگاشت مقدار `mc_sort` به آرگومان‌های کوئری.
 *
 * یک منبع حقیقت برای هر دو مسیر: کوئری اصلی آرشیوها (`Query`) و
 * حلقه‌های بلوکی که فیلترهای نشانی را ارث می‌برند (`Block_Query` با ویژگی
 * `inheritFilters`). بدون این اشتراک، دو پیاده‌سازی از هم فاصله می‌گرفتند.
 *
 * @param string $sort مقدار پارامتر `mc_sort`.
 * @return array آرایه‌ی توصیفی؛ `meta` برای مرتب‌سازی عددی روی فیلد سفارشی،
 *               `orderby`/`order` برای مرتب‌سازی ساده، و آرایه‌ی خالی اگر
 *               مقدار ناشناخته باشد.
 */
function manacore_sort_query_args( $sort ) {
	$sort = sanitize_key( (string) $sort );

	$meta = array(
		'rating' => 'manacore_imdb_rating',
		'views'  => 'manacore_views',
		'year'   => 'manacore_year',
	);

	if ( isset( $meta[ $sort ] ) ) {
		return array( 'meta' => $meta[ $sort ] );
	}

	if ( 'title' === $sort ) {
		return array(
			'orderby' => 'title',
			'order'   => 'ASC',
		);
	}

	if ( 'oldest' === $sort ) {
		return array(
			'orderby' => 'date',
			'order'   => 'ASC',
		);
	}

	if ( 'newest' === $sort ) {
		return array(
			'orderby' => 'date',
			'order'   => 'DESC',
		);
	}

	return array();
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
 * نشانی برگه‌ی «کشف داستان‌ها» — هم‌ارزِ `browse.html` در مرجع.
 *
 * مرجع، پیوند «مشاهده همه»ی سرصفحه‌ی بخش‌های صفحه‌ی نخست را به برگه‌ی
 * کشف می‌برد (`a.text-link` → `browse.html`). برگه‌ی هم‌ارز در این پروژه
 * برگه‌ای با نامک `browse` است (صفحه‌های کشف/آرشیو تازه). اگر آن برگه
 * منتشر نشده باشد رشته‌ی خالی برمی‌گردد تا فراخواننده به رفتار پیشین
 * (آرشیو همان نوع محتوا) برگردد و هیچ پیوندی نشکند.
 *
 * @param array $args پرسمان‌های افزودنی، مثل `array( 'type' => 'series' )`.
 * @return string نشانی کامل یا رشته‌ی خالی.
 */
function manacore_discovery_url( $args = array() ) {
	/** فیلتر نامک برگه‌ی کشف. */
	$slug = (string) apply_filters( 'manacore_discovery_slug', 'browse' );

	$page = $slug ? get_page_by_path( $slug, OBJECT, 'page' ) : null;

	if ( ! $page instanceof \WP_Post || 'publish' !== $page->post_status ) {
		return '';
	}

	$url = (string) get_permalink( $page );

	return $args ? (string) add_query_arg( $args, $url ) : $url;
}

/**
 * پارامترهای فیلتری که بر فراداده (نه تاکسونومی) کار می‌کنند.
 *
 * مرجع در سایدبار «کشف داستان‌ها» چهار کنترل دارد که هیچ‌کدام تاکسونومی
 * نیستند: بازه‌ی سال ساخت، حداقل امتیاز IMDb، کلید «فقط دوبله فارسی» و
 * مرتب‌سازی. همین‌ها اینجا به پارامترهای نشانی نگاشت می‌شوند تا هم
 * نوار فیلتر رندرشان کند و هم کوئری (اصلی و بلوکی) اعمالشان کند؛
 * نگاشت تاکسونومی‌ها در `manacore_filter_params()` است.
 *
 * @return array<string,string> کلید: نام پارامتر، مقدار: نوع داده.
 */
function manacore_meta_filter_params() {
	/**
	 * فیلتر فهرست پارامترهای فراداده‌ای.
	 *
	 * @param array<string,string> $params نگاشت پارامتر به نوع.
	 */
	return (array) apply_filters(
		'manacore_meta_filter_params',
		array(
			'mc_year_min'   => 'year',
			'mc_year_max'   => 'year',
			'mc_rating_min' => 'rating',
			'mc_dubbed'     => 'flag',
		)
	);
}

/**
 * کلید فراداده‌ای که کلید «دوبله فارسی» را می‌خواند.
 *
 * مرجع این کلید را سمت کاربر و روی داده‌ی ثابت خودش دارد؛ در وردپرس
 * معادل درستش یک فیلد فراداده‌ای واقعی است (در متاباکس اثر ویرایش
 * می‌شود). اگر سایت فیلد دیگری داشته باشد، با همین فیلتر عوض می‌شود.
 *
 * @return string
 */
function manacore_dubbed_meta_key() {
	return (string) apply_filters( 'manacore_dubbed_meta_key', 'manacore_dubbed' );
}

/**
 * مقادیر پاک‌سازی‌شده‌ی فیلترهای فراداده‌ای از آدرس جاری.
 *
 * @return array<string,int> فقط کلیدهایی که مقدار دارند.
 */
function manacore_request_meta_filters() {
	$values = array();
	$types  = manacore_meta_filter_params();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	foreach ( $types as $param => $type ) {
		if ( empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			continue;
		}

		$raw = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'flag' === $type ) {
			$values[ $param ] = 1;
			continue;
		}

		$number = (float) $raw;
		if ( $number > 0 ) {
			$values[ $param ] = $number;
		}
	}
	// phpcs:enable

	/* بازه‌ی وارونه (کمینه بزرگ‌تر از بیشینه) بی‌اثر است؛ جایشان عوض می‌شود. */
	if ( isset( $values['mc_year_min'], $values['mc_year_max'] )
		&& $values['mc_year_min'] > $values['mc_year_max'] ) {
		$swap                        = $values['mc_year_min'];
		$values['mc_year_min']       = $values['mc_year_max'];
		$values['mc_year_max']       = $swap;
	}

	return $values;
}

/**
 * بندهای `meta_query` برای فیلترهای فراداده‌ای.
 *
 * یک منبع حقیقت برای هر دو مسیر کوئری (آرشیو اصلی و حلقه‌ی بلوک‌ها) تا
 * فیلتری که در سایدبار دیده می‌شود هرگز بی‌اثر نماند.
 *
 * @param array $values خروجی `manacore_request_meta_filters()` (اختیاری).
 * @return array بندهای آماده برای ادغام در `meta_query`.
 */
function manacore_meta_filter_clauses( $values = null ) {
	$values  = is_array( $values ) ? $values : manacore_request_meta_filters();
	$clauses = array();

	if ( isset( $values['mc_year_min'], $values['mc_year_max'] ) ) {
		$clauses[] = array(
			'key'     => 'manacore_year',
			'value'   => array( (int) $values['mc_year_min'], (int) $values['mc_year_max'] ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	} elseif ( isset( $values['mc_year_min'] ) ) {
		$clauses[] = array(
			'key'     => 'manacore_year',
			'value'   => (int) $values['mc_year_min'],
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	} elseif ( isset( $values['mc_year_max'] ) ) {
		$clauses[] = array(
			'key'     => 'manacore_year',
			'value'   => (int) $values['mc_year_max'],
			'type'    => 'NUMERIC',
			'compare' => '<=',
		);
	}

	if ( isset( $values['mc_rating_min'] ) ) {
		$clauses[] = array(
			'key'     => 'manacore_imdb_rating',
			'value'   => (float) $values['mc_rating_min'],
			'type'    => 'DECIMAL(4,1)',
			'compare' => '>=',
		);
	}

	if ( ! empty( $values['mc_dubbed'] ) ) {
		$clauses[] = array(
			'key'     => manacore_dubbed_meta_key(),
			'value'   => '1',
			'compare' => '=',
		);
	}

	return $clauses;
}

/**
 * ادغام بندهای فراداده‌ای در یک `meta_query` موجود.
 *
 * اگر بند تازه‌ای نباشد، آرگومان دست‌نخورده برگردانده می‌شود تا رفتار
 * مسیرهای بدون فیلتر عوض نشود.
 *
 * @param array $meta_query بندهای موجود.
 * @param array $clauses    بندهای تازه.
 * @return array
 */
function manacore_merge_meta_query( $meta_query, $clauses ) {
	$meta_query = (array) $meta_query;
	$clauses    = array_values( array_filter( (array) $clauses ) );

	if ( ! $clauses ) {
		return $meta_query;
	}

	foreach ( $clauses as $clause ) {
		$meta_query[] = $clause;
	}

	/* شرط چندبندی همیشه AND است؛ `relation` تکراری نوشته نمی‌شود. */
	$count = count( array_filter( array_keys( $meta_query ), 'is_int' ) );
	if ( $count > 1 ) {
		$meta_query['relation'] = 'AND';
	}

	return $meta_query;
}

/**
 * شمار آثار منتشرشده‌ی هر ترم روی انواع «اثر».
 *
 * مرجع کنار هر ژانر در سایدبار شمارِ داستان‌های همان ژانر را نشان می‌دهد.
 * شمارِ خودِ ترم (`->count`) همه‌ی انواع محتوا — از جمله قسمت‌ها و
 * مجموعه‌ها — را می‌شمارد، پس اینجا یک کوئری گروهی روی انواع اثر زده
 * می‌شود و نتیجه پنج دقیقه کش می‌ماند.
 *
 * @param string $taxonomy نام تاکسونومی.
 * @return array<int,int> نگاشت term_id به شمار.
 */
function manacore_term_counts( $taxonomy ) {
	$taxonomy = sanitize_key( (string) $taxonomy );

	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$types     = manacore_title_post_types();
	$cache_key = 'manacore_counts_' . $taxonomy . '_' . md5( implode( ',', $types ) . '|' . manacore_dubbed_meta_key() );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	global $wpdb;

	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$sql          = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT t.term_id AS term_id, COUNT( DISTINCT p.ID ) AS total
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
		INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		WHERE tt.taxonomy = %s AND p.post_status = 'publish' AND p.post_type IN ( {$placeholders} )
		GROUP BY t.term_id",
		array_merge( array( $taxonomy ), $types )
	);

	$rows   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$counts = array();

	foreach ( (array) $rows as $row ) {
		$counts[ (int) $row['term_id'] ] = (int) $row['total'];
	}

	set_transient( $cache_key, $counts, 5 * MINUTE_IN_SECONDS );

	return $counts;
}

/**
 * کمینه و بیشینه‌ی سال انتشار روی انواع «اثر».
 *
 * گزینشگرهای «از سال / تا سال» مرجع از داده پر می‌شوند، نه از بازه‌ی
 * دست‌نویس؛ وگرنه کاربر سال‌هایی را می‌بیند که هیچ اثری ندارند.
 *
 * @return array{min:int,max:int}
 */
function manacore_year_bounds() {
	$cache_key = 'manacore_year_bounds_' . md5( implode( ',', manacore_title_post_types() ) );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
		return $cached;
	}

	global $wpdb;

	$placeholders = implode( ', ', array_fill( 0, count( manacore_title_post_types() ), '%s' ) );
	$sql          = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT MIN( CAST( pm.meta_value AS UNSIGNED ) ) AS min_year,
			MAX( CAST( pm.meta_value AS UNSIGNED ) ) AS max_year
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key = 'manacore_year' AND pm.meta_value != ''
			AND p.post_status = 'publish' AND p.post_type IN ( {$placeholders} )",
		manacore_title_post_types()
	);

	$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$bounds = array(
		'min' => isset( $row['min_year'] ) ? (int) $row['min_year'] : 0,
		'max' => isset( $row['max_year'] ) ? (int) $row['max_year'] : 0,
	);

	/* در نبود داده، بازه‌ی متعارفی می‌نشیند تا گزینشگر خالی نماند. */
	if ( ! $bounds['min'] || ! $bounds['max'] ) {
		$current       = (int) gmdate( 'Y' );
		$bounds['min'] = $current - 40;
		$bounds['max'] = $current;
	}

	set_transient( $cache_key, $bounds, 5 * MINUTE_IN_SECONDS );

	return $bounds;
}

/**
 * پارامترهای نشانی پس از برداشتن یک فیلتر.
 *
 * منطق ساده‌ی «حذف همان کلید» برای بازه‌ی سال کافی نیست: بازه دو
 * پارامتر دارد (`mc_year_min` و `mc_year_max`) ولی **یک** کنترل و یک
 * برچسب است، پس برداشتن برچسبش باید هر دو را بردارد؛ وگرنه کاربر با
 * برچسبی روبه‌رو می‌شود که نصف بازه را نگه داشته است.
 *
 * @param array  $active پارامترهای فعال.
 * @param string $param  پارامتری که برچسبش زده شده.
 * @return array پارامترهایی که باید در نشانی بمانند.
 */
function manacore_chip_removal_args( $active, $param ) {
	$remove = array( $param );

	if ( 0 === strpos( (string) $param, 'mc_year_' ) ) {
		$remove = array( 'mc_year_min', 'mc_year_max' );
	}

	return array_diff_key( (array) $active, array_flip( $remove ) );
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

	/*
	 * فیلترهای فراداده‌ای (بازه‌ی سال، حداقل امتیاز، دوبله) هم مثل
	 * تاکسونومی‌ها در فهرست فیلترهای فعال می‌آیند؛ وگرنه تراشه‌ی حذف
	 * ندارند و کاربر نمی‌تواند از حالت خالی بیرون بیاید.
	 */
	foreach ( manacore_request_meta_filters() as $meta_param => $meta_value ) {
		$active[ $meta_param ] = (string) $meta_value;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	/*
	 * جستجو روی برگه‌های غیرآرشیوی (صفحه‌ی «کشف داستان‌ها») با پارامتر
	 * خودمان می‌آید؛ `s` در نشانی، وردپرس را به حالت جستجو می‌برد و برگه
	 * ۴۰۴ می‌شود (سنجیده‌شده: `/about/?s=test` → ۴۰۴).
	 */
	if ( ! empty( $_GET['manacore_q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term = sanitize_text_field( wp_unslash( $_GET['manacore_q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $term ) {
			$active['manacore_q'] = $term;
		}
	}

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
/**
 * تبدیل رقم‌های لاتین به رقم‌های فارسی.
 *
 * @param string|int $value مقدار ورودی.
 * @return string
 */
function manacore_fa_digits( $value ) {
	return strtr(
		(string) $value,
		array(
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		)
	);
}

/**
 * تبدیل تاریخ میلادی به هجری شمسی.
 *
 * چرا دستی و نه کتابخانه؟ وردپرس تقویم شمسی ندارد و بخش‌هایی مثل «ردیف
 * مجله» و «برنامه‌ی هفتگی» باید تاریخ را به شکل متعارف سایت‌های فارسی
 * نشان دهند. الگوریتم استاندارد (برگرفته از jalaali) است و در
 * `wp/tests/test-jalali.cjs` با تاریخ‌های مرجع آزموده می‌شود.
 *
 * @param int $gy سال میلادی.
 * @param int $gm ماه میلادی (۱–۱۲).
 * @param int $gd روز میلادی.
 * @return array array( سال، ماه، روز ) شمسی.
 */
function manacore_gregorian_to_jalali( $gy, $gm, $gd ) {
	/*
	 * روزهای سپری‌شده تا ابتدای هر ماه میلادی (نه شمار روزهای ماه). اشتباه
	 * گرفتن این دو، تاریخ را حدود یک ماه جابه‌جا می‌کند — همان باگی که در
	 * نخستین نسخه رخ داد و با آزمون تاریخ‌های مرجع نوروز گرفته شد.
	 */
	$g_days_before = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

	$gy = (int) $gy;
	$gm = (int) $gm;
	$gd = (int) $gd;

	$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days = 355666 + ( 365 * $gy ) + ( (int) ( ( $gy2 + 3 ) / 4 ) ) - ( (int) ( ( $gy2 + 99 ) / 100 ) )
		+ ( (int) ( ( $gy2 + 399 ) / 400 ) ) + $gd + $g_days_before[ $gm - 1 ];

	$jy  = -1595 + ( 33 * ( (int) ( $days / 12053 ) ) );
	$day = $days % 12053;

	$jy   += 4 * ( (int) ( $day / 1461 ) );
	$day  %= 1461;

	if ( $day > 365 ) {
		$jy  += (int) ( ( $day - 1 ) / 365 );
		$day  = ( $day - 1 ) % 365;
	}

	if ( $day < 186 ) {
		$jm = 1 + (int) ( $day / 31 );
		$jd = 1 + ( $day % 31 );
	} else {
		$jm = 7 + (int) ( ( $day - 186 ) / 30 );
		$jd = 1 + ( ( $day - 186 ) % 30 );
	}

	return array( $jy, $jm, $jd );
}

/**
 * قالب‌بندی تاریخ به شکل خوانای فارسی («۱۰ مهر ۱۴۰۳»).
 *
 * برچسب ماه‌ها به سایت‌های فارسی محدود نیست؛ اگر ترجمه‌ی فارسی وردپرس
 * نصب باشد `date_i18n` هم همان نام‌ها را می‌دهد، ولی تقویم را شمسی
 * نمی‌کند؛ پس تبدیل را خودمان انجام می‌دهیم.
 *
 * @param string $mysql_date تاریخ به شکل MySQL یا هر چیزی که strtotime بفهمد.
 * @param bool   $with_year  نمایش سال.
 * @return string
 */
function manacore_fa_date( $mysql_date, $with_year = true ) {
	$time = strtotime( (string) $mysql_date );
	if ( ! $time ) {
		return '';
	}

	list( $jy, $jm, $jd ) = manacore_gregorian_to_jalali(
		(int) wp_date( 'Y', $time ),
		(int) wp_date( 'n', $time ),
		(int) wp_date( 'j', $time )
	);

	$months = array(
		1  => __( 'فروردین', 'manacore' ),
		2  => __( 'اردیبهشت', 'manacore' ),
		3  => __( 'خرداد', 'manacore' ),
		4  => __( 'تیر', 'manacore' ),
		5  => __( 'مرداد', 'manacore' ),
		6  => __( 'شهریور', 'manacore' ),
		7  => __( 'مهر', 'manacore' ),
		8  => __( 'آبان', 'manacore' ),
		9  => __( 'آذر', 'manacore' ),
		10 => __( 'دی', 'manacore' ),
		11 => __( 'بهمن', 'manacore' ),
		12 => __( 'اسفند', 'manacore' ),
	);

	$out = manacore_fa_digits( $jd ) . ' ' . ( isset( $months[ $jm ] ) ? $months[ $jm ] : '' );
	if ( $with_year ) {
		$out .= ' ' . manacore_fa_digits( $jy );
	}

	return trim( $out );
}

/**
 * شمار فصل‌های یک سریال.
 *
 * اولویت با فراداده‌ی «تعداد فصل‌ها» است؛ اگر مدیر آن را پر نکرده باشد،
 * شمارِ ردیف‌های تکرارشونده‌ی «فصل‌ها» شمرده می‌شود. هر دو از داده‌ی
 * واقعی خوانده می‌شوند تا هیچ عدد ثابتی در قالب نماند.
 *
 * @param int $post_id شناسه‌ی سریال.
 * @return int
 */
function manacore_series_seasons_count( $post_id ) {
	$total = (int) get_post_meta( (int) $post_id, 'manacore_total_seasons', true );
	if ( $total > 0 ) {
		return $total;
	}

	$rows = get_post_meta( (int) $post_id, 'manacore_seasons', true );

	return is_array( $rows ) ? count( $rows ) : 0;
}

/**
 * شمار قسمت‌های یک سریال.
 *
 * اولویت با فراداده‌ی «تعداد کل قسمت‌ها» است؛ وگرنه مجموع ستون «تعداد
 * قسمت» در ردیف‌های فصل‌ها.
 *
 * @param int $post_id شناسه‌ی سریال.
 * @return int
 */
function manacore_series_episodes_count( $post_id ) {
	$total = (int) get_post_meta( (int) $post_id, 'manacore_total_episodes', true );
	if ( $total > 0 ) {
		return $total;
	}

	$rows = get_post_meta( (int) $post_id, 'manacore_seasons', true );
	if ( ! is_array( $rows ) ) {
		return 0;
	}

	$sum = 0;
	foreach ( $rows as $row ) {
		if ( is_array( $row ) && isset( $row['episodes'] ) ) {
			$sum += (int) $row['episodes'];
		}
	}

	return $sum;
}

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

/**
 * تصویر عریض (Backdrop) اثر، با زنجیره‌ی جانشین مشخص.
 *
 * ترتیب: تصویر شاخص → `manacore_backdrop_url` → `manacore_poster_url` →
 * تصویر جانشین افزونه. پیش‌تر این زنجیره در چند نقطه‌ی کد تکرار شده بود
 * (باکس‌های محتوا، مگامنو، هیرو) و هرکدام ترتیب متفاوتی داشتند؛ این
 * تابع یک منبع حقیقت مشترک می‌سازد.
 *
 * @param int    $post_id شناسه‌ی اثر.
 * @param string $size    اندازه‌ی تصویر شاخص.
 * @return string نشانی تصویر (هرگز خالی).
 */
function manacore_backdrop_url( $post_id, $size = 'large' ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return MANACORE_URL . 'assets/placeholder.svg';
	}

	$thumb = get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		$url = wp_get_attachment_image_url( $thumb, $size );
		if ( $url ) {
			return esc_url_raw( $url );
		}
	}

	foreach ( array( 'manacore_backdrop_url', 'manacore_poster_url' ) as $key ) {
		$url = (string) get_post_meta( $post_id, $key, true );
		if ( '' !== trim( $url ) ) {
			return esc_url_raw( $url );
		}
	}

	return manacore_poster_url( $post_id, $size );
}

/**
 * نشانی «حساب کاربری» — مهمان به ورود و عضو به حساب خودش می‌رسد.
 *
 * یک منبع حقیقت برای بلوک‌هایی که به حساب کاربر اشاره می‌کنند (کارت
 * ترویجی «لیست تماشا» و مانند آن). اگر پوسته تابع خودش را داشته باشد
 * (پوسته‌ی کوهه: `koohe_account_url`) همان مقدم است؛ وگرنه ووکامرس و
 * سپس نشانی پیش‌فرض وردپرس به کار می‌رود.
 *
 * @param array $args پرسمان‌های افزودنی، مثل `array( 'tab' => 'watchlist' )`.
 * @return string
 */
function manacore_account_url( $args = array() ) {
	$base = '';

	/*
	 * ترتیب اولویت:
	 *   ۱) برگه‌ی «حساب کاربری» خودمان (قالب `page-account`) — منبع حقیقت
	 *      برگه‌ی حساب در این محصول.
	 *   ۲) نشانی حساب ووکامرس (اگر ووکامرس فعال باشد و برگه‌اش ساخته شده
	 *      باشد).
	 *   ۳) پوسته (اگر تابع خودش را داشته باشد).
	 *   ۴) پیشخوان کاربر / ورود.
	 * مهمان‌ها هم به همان برگه می‌روند؛ حالت مهمان برگه را خودِ قالب
	 * مدیریت می‌کند (مرجع هم همین کار را می‌کند).
	 */
	if ( class_exists( '\\ManaCore\\Core\\Account' ) ) {
		$base = (string) \ManaCore\Core\Account::page_url();
	}

	if ( ! $base && function_exists( 'wc_get_page_permalink' ) ) {
		$base = (string) wc_get_page_permalink( 'myaccount' );
	}

	if ( ! $base && function_exists( 'koohe_account_url' ) ) {
		$base = (string) koohe_account_url();
	}

	if ( ! $base ) {
		$base = is_user_logged_in()
			? admin_url( 'profile.php' )
			: wp_login_url();
	}

	/** فیلتر نشانی حساب کاربری. */
	$base = (string) apply_filters( 'manacore_account_url', $base );

	if ( ! $base ) {
		return '';
	}

	if ( ! empty( $args ) ) {
		$base = (string) add_query_arg( array_map( 'sanitize_text_field', (array) $args ), $base );
	}

	return $base;
}

/**
 * شناسه‌ی برگه‌ی «حساب کاربری».
 *
 * @return int صفر اگر برگه ساخته نشده باشد.
 */
function manacore_account_page_id() {
	return class_exists( '\\ManaCore\\Core\\Account' ) ? \ManaCore\Core\Account::page_id() : 0;
}

/**
 * گشودن «نشانه»های پیوند به نشانی واقعی.
 *
 * ویژگی‌های پیوند در ویرایشگر جای URL خام، این نشانه‌ها را هم می‌پذیرند تا
 * مقصد از داده‌ی خودِ سایت ساخته شود و هیچ نشانی‌ای سخت‌کد نشود:
 *
 *   `account`            → نشانی حساب کاربری (مهمان: ورود)
 *   `account:watchlist`  → همان + `?tab=watchlist`
 *   `discovery`          → برگه‌ی کشف داستان‌ها
 *   `subscribe`          → برگه/فیلتر اشتراک
 *
 * هر مقدار دیگری دست‌نخورده برمی‌گردد تا URL عادی هم کار کند.
 *
 * @param string $value مقدار ویژگی.
 * @return string
 */
function manacore_cast_url() {
	$page = get_page_by_path( 'cast' );

	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		return (string) apply_filters( 'manacore_cast_url', get_permalink( $page ) );
	}

	$archive = get_post_type_archive_link( 'person' );

	return (string) apply_filters( 'manacore_cast_url', $archive ? $archive : home_url( '/' ) );
}

/**
 * حل نشانک‌های نشانی (مثل `account`، `discovery`، `cast`).
 *
 * @param string $value نشانی یا نشانک.
 * @return string
 */
function manacore_resolve_link( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return $value;
	}

	$parts = explode( ':', $value, 2 );
	$token = strtolower( $parts[0] );
	$extra = isset( $parts[1] ) ? trim( $parts[1] ) : '';

	switch ( $token ) {
		case 'account':
			return manacore_account_url( '' !== $extra ? array( 'tab' => $extra ) : array() );

		case 'discovery':
			return manacore_discovery_url();

		case 'cast':
			/* برگه‌ی «بازیگران و عوامل»؛ در نبودش، آرشیو چهره‌ها. */
			return manacore_cast_url();

		case 'subscribe':
			/** همان فیلتری که پوسته برای دکمه‌های اشتراک به کار می‌برد. */
			return (string) apply_filters( 'manacore_subscribe_url', home_url( '/subscribe/' ) );

		default:
			return $value;
	}
}

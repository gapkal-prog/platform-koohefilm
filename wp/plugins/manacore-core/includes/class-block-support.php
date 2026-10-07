<?php
/**
 * لایه‌ی مشترک نمایش بلوک‌ها (ویژگی‌ها، پوشش، سرتیتر، کارت و شبکه).
 *
 * تقسیم مسئولیت‌ها در ManaCore:
 *
 * - {@see Block_Data}       داده‌های موردنیاز ویرایشگر (پل به JavaScript).
 * - {@see Block_Query}      تبدیل ویژگی‌های کوئری به آرگومان‌های WP_Query.
 * - {@see Block_Visibility} ارزیابی قواعد نمایش شرطی سمت سرور.
 * - {@see Block_Support}    ویژگی‌های ظاهری و قطعه‌های مشترک رندر (این کلاس).
 *
 * این کلاس هیچ منطقی را تکرار نمی‌کند و صرفاً سه کلاس بالا را در یک نقطه‌ی
 * ورودی ساده برای {@see Blocks} کنار هم می‌گذارد.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Support
 */
class Block_Support {

	/**
	 * حداکثر ستون شبکه.
	 */
	const MAX_COLUMNS = 8;

	/**
	 * آخرین کوئری حلقه (برای شمار کل/صفحه‌بندی).
	 *
	 * @var \WP_Query|null
	 */
	protected static $last_query = null;

	/* ---------------------------------------------------------------------
	 * ۱) گروه‌های ویژگی
	 * ------------------------------------------------------------------ */

	/**
	 * ویژگی‌های ظاهری عمومی (کلاس افزوده، لنگر، حالت خالی).
	 *
	 * @return array
	 */
	public static function presentation_attributes() {
		return array(
			'extraClass'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'anchor'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyText'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'hideIfEmpty' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'cacheTtl'    => array(
				'type'    => 'number',
				'default' => 0,
			),
		);
	}

	/**
	 * ویژگی‌های سرتیتر بخش و پیوند «مشاهده همه».
	 *
	 * @return array
	 */
	public static function header_attributes() {
		return array(
			'heading'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'headingLevel' => array(
				'type'    => 'string',
				'default' => 'h2',
			),
			'headingIcon'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'subheading'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'showMore'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'moreUrl'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'moreLabel'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'moreTarget'   => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * ویژگی‌های صفحه‌بندی حلقه («داستان‌های بیشتر» + شمار کل).
	 *
	 * مرجع: `.load-more-zone` با دکمه‌ی «داستان‌های بیشتر» و
	 * `.results-summary` با شمار آثار. هر دو فقط وقتی معنا دارند که حلقه
	 * بداند چند صفحه دارد، پس `no_found_rows` هم در `query_args()` خاموش
	 * می‌شود (پیش‌فرض روشن است و شمار کل را نمی‌دهد).
	 *
	 * @return array
	 */
	public static function pagination_attributes() {
		return array(
			'showFilterSummary' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'loadMore'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'loadText'  => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * ویژگی‌های چیدمان شبکه.
	 *
	 * @return array
	 */
	public static function layout_attributes() {
		return array(
			'layout'        => array(
				'type'    => 'string',
				'default' => 'grid',
			),
			'columns'       => array(
				'type'    => 'number',
				'default' => 6,
			),
			'columnsTablet' => array(
				'type'    => 'number',
				'default' => 3,
			),
			'columnsMobile' => array(
				'type'    => 'number',
				'default' => 2,
			),
			'gap'           => array(
				'type'    => 'number',
				'default' => 0,
			),
		);
	}

	/**
	 * ویژگی‌های نمایش کارت اثر.
	 *
	 * @return array
	 */
	public static function card_attributes() {
		return array(
			'cardStyle'      => array(
				'type'    => 'string',
				'default' => 'poster',
			),
			'imageRatio'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleTag'       => array(
				'type'    => 'string',
				'default' => 'h3',
			),
			'titleLines'     => array(
				'type'    => 'number',
				'default' => 0,
			),
			'showRating'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showYear'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showType'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showQuality'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showGenre'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showWatchlist'  => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showPremium'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showOverlay'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showEpisode'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showViews'      => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showRuntime'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'showExcerpt'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'excerptWords'   => array(
				'type'    => 'number',
				'default' => 14,
			),
			'cardLinkTarget' => array(
				'type'    => 'string',
				'default' => '',
			),
			'cardClass'      => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * ترکیب چند گروه ویژگی در یک آرایه.
	 *
	 * همیشه ویژگی‌های نمایش شرطی و ظاهری عمومی افزوده می‌شود تا همه‌ی
	 * بلوک‌های ManaCore رفتار یکسانی داشته باشند.
	 *
	 * @param array ...$groups گروه‌های ویژگی.
	 * @return array
	 */
	public static function compose( ...$groups ) {
		$attributes = array_merge(
			Block_Visibility::attribute(),
			self::presentation_attributes()
		);

		foreach ( $groups as $group ) {
			if ( is_array( $group ) ) {
				$attributes = array_merge( $attributes, $group );
			}
		}

		return $attributes;
	}

	/**
	 * مجموعه‌ی کامل ویژگی‌های یک بلوک حلقه (کوئری + چیدمان + کارت + سرتیتر).
	 *
	 * @param array $extra ویژگی‌های اختصاصی بلوک.
	 * @return array
	 */
	public static function loop_attributes( $extra = array() ) {
		return self::compose(
			self::header_attributes(),
			self::pagination_attributes(),
			Block_Query::attributes(),
			self::layout_attributes(),
			self::card_attributes(),
			$extra
		);
	}

	/**
	 * مقادیر پیش‌فرض یک آرایه‌ی ویژگی.
	 *
	 * @param array $attributes تعریف ویژگی‌ها.
	 * @return array
	 */
	public static function defaults( $attributes ) {
		$defaults = array();
		foreach ( (array) $attributes as $key => $definition ) {
			$defaults[ $key ] = isset( $definition['default'] ) ? $definition['default'] : null;
		}
		return $defaults;
	}

	/* ---------------------------------------------------------------------
	 * ۲) نمایش شرطی
	 * ------------------------------------------------------------------ */

	/**
	 * آیا بلوک باید رندر شود؟ (واسط {@see Block_Visibility}).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return bool
	 */
	public static function should_render( $attrs ) {
		return Block_Visibility::should_render( $attrs );
	}

	/**
	 * آیا درخواست از ویرایشگر است؟
	 *
	 * @return bool
	 */
	public static function is_editor_preview() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return current_user_can( 'edit_posts' );
		}
		return is_admin();
	}

	/* ---------------------------------------------------------------------
	 * ۳) پوشش بلوک
	 * ------------------------------------------------------------------ */

	/**
	 * ساخت ویژگی‌های تگ پوشش بلوک.
	 *
	 * @param array        $attrs ویژگی‌های بلوک.
	 * @param array|string $base  کلاس(های) پایه.
	 * @return string
	 */
	public static function wrapper( $attrs, $base = '' ) {
		$classes = array( 'manacore-block' );

		foreach ( (array) $base as $chunk ) {
			foreach ( preg_split( '/\s+/', (string) $chunk ) as $token ) {
				$token = sanitize_html_class( $token );
				if ( $token ) {
					$classes[] = $token;
				}
			}
		}

		$devices = Block_Visibility::device_classes( $attrs );
		if ( $devices ) {
			$classes[] = $devices;
		}

		if ( ! empty( $attrs['extraClass'] ) ) {
			foreach ( preg_split( '/\s+/', (string) $attrs['extraClass'] ) as $token ) {
				$token = sanitize_html_class( $token );
				if ( $token ) {
					$classes[] = $token;
				}
			}
		}

		$args = array( 'class' => implode( ' ', array_unique( $classes ) ) );

		if ( ! empty( $attrs['anchor'] ) ) {
			$anchor = sanitize_title( (string) $attrs['anchor'] );
			if ( $anchor ) {
				$args['id'] = $anchor;
			}
		}

		return get_block_wrapper_attributes( $args );
	}

	/* ---------------------------------------------------------------------
	 * ۴) کوئری
	 * ------------------------------------------------------------------ */

	/**
	 * ساخت آرگومان‌های کوئری از ویژگی‌های بلوک.
	 *
	 * علاوه بر {@see Block_Query::build()} پرچم `manacore_force_order` را
	 * می‌گذارد تا مرتب‌سازی صریح ادمین توسط منبع بازنویسی نشود.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @param int   $max   حداکثر تعداد.
	 * @return array
	 */
	public static function query_args( $attrs, $max = 48 ) {
		$args = Block_Query::build( $attrs, array( 'max' => (int) $max ) );

		if ( ! empty( $attrs['orderBy'] ) ) {
			$args['manacore_force_order'] = true;
		}

		/*
		 * شمار کل و شمار صفحه‌ها: پیش‌فرض `Query::get_titles()` روی
		 * `no_found_rows = true` است (سبک‌تر). بلوکی که «شمار آثار» یا
		 * «داستان‌های بیشتر» می‌خواهد به `found_posts`/`max_num_pages` نیاز
		 * دارد، پس همان‌جا خاموش می‌شود — نه برای همه‌ی حلقه‌ها.
		 */
		if ( ! empty( $attrs['showFilterSummary'] ) || ! empty( $attrs['loadMore'] ) ) {
			$args['no_found_rows'] = false;
		}

		return $args;
	}

	/**
	 * دریافت نوشته‌های حلقه (با کش اختیاری).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @param int   $max   حداکثر تعداد.
	 * @return \WP_Post[]
	 */
	public static function get_posts( $attrs, $max = 48 ) {
		$args = self::query_args( $attrs, $max );
		$ttl  = isset( $attrs['cacheTtl'] ) ? max( 0, (int) $attrs['cacheTtl'] ) : 0;

		self::$last_query = null;

		if ( $ttl > 0 && ! self::is_editor_preview() ) {
			$key    = 'mc_blk_' . md5( wp_json_encode( $args ) . '|' . Block_Visibility::current_post_id() );
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				$posts = array_filter( array_map( 'get_post', $cached ) );
				if ( count( $posts ) === count( $cached ) ) {
					return $posts;
				}
			}
			$posts = Query::get_titles( $args )->posts;
			set_transient( $key, wp_list_pluck( $posts, 'ID' ), min( $ttl, DAY_IN_SECONDS ) );
			return $posts;
		}

		self::$last_query = Query::get_titles( $args );

		return self::$last_query->posts;
	}

	/**
	 * شمار آثار همان کوئری، بدون گرفتن ردیف‌ها.
	 *
	 * برای جانگهدار `{count}` در سرتیتر لازم است. `render_header()` پیش از
	 * اجرای حلقه صدا زده می‌شود، پس به `last_query()` نمی‌توان تکیه کرد؛
	 * این‌جا همان آرگومان‌ها با `fields = ids` و یک ردیف اجرا می‌شوند و
	 * نتیجه در همان درخواست کش می‌شود تا چند بلوک با ویژگی‌های یکسان فقط
	 * یک پرس‌وجو بزنند.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return int
	 */
	public static function count_posts( $attrs, $max = 48 ) {
		$args = self::query_args( $attrs, $max );
		$key  = md5( wp_json_encode( $args ) );

		if ( isset( self::$counts[ $key ] ) ) {
			return (int) self::$counts[ $key ];
		}

		$args['posts_per_page'] = 1;
		$args['fields']         = 'ids';
		$args['no_found_rows']  = false;

		unset( $args['paged'] );

		self::$counts[ $key ] = (int) Query::get_titles( $args )->found_posts;

		return (int) self::$counts[ $key ];
	}

	/**
	 * آخرین کوئری اجراشده‌ی حلقه‌ها.
	 *
	 * برای شمار کل، شمار صفحه‌ها و ساخت پیوند «صفحه‌ی بعد» لازم است. اگر
	 * حلقه از کش برگردد (ویژگی `cacheTtl`) مقدار `null` است و رندرکننده
	 * به شمار کارت‌های همان صفحه بسنده می‌کند.
	 *
	 * @return \WP_Query|null
	 */
	public static function last_query() {
		return self::$last_query;
	}

	/**
	 * کش شمارش‌های درون‌درخواستی (`count_posts()`).
	 *
	 * @var array<string,int>
	 */
	protected static $counts = array();

	/* ---------------------------------------------------------------------
	 * ۵) قطعه‌های مشترک رندر
	 * ------------------------------------------------------------------ */

	/**
	 * رندر سرتیتر بخش.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	/**
	 * آیا این نشانی به همان صفحه‌ای اشاره می‌کند که در حال نمایش است؟
	 *
	 * مقایسه روی میزبان + مسیر (با نادیده‌گرفتن اسلش پایانی) + پرسمانِ مرتب‌شده
	 * انجام می‌شود. پرسمان هم سنجیده می‌شود چون نمایی مثل `/?post_type=post`
	 * (برگه‌ی مقالات) واقعاً مقصدی دیگر است، در حالی که ریشه‌ی سایت روی
	 * صفحه‌ی نخست، خودِ همان صفحه است.
	 *
	 * @param string $url نشانی.
	 * @return bool
	 */
	protected static function is_current_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}

		$target = wp_parse_url( $url );
		if ( empty( $target['host'] ) ) {
			return false; // نشانی نسبی؛ داوری‌اش با نویسنده است.
		}

		$home = wp_parse_url( home_url( '/' ) );
		if ( empty( $home['host'] ) || 0 !== strcasecmp( (string) $target['host'], (string) $home['host'] ) ) {
			return false;
		}

		$current = wp_parse_url( home_url( add_query_arg( array() ) ) );

		$target_path  = isset( $target['path'] ) ? (string) $target['path'] : '/';
		$current_path = isset( $current['path'] ) ? (string) $current['path'] : '/';
		if ( '' === $target_path ) {
			$target_path = '/';
		}
		if ( '' === $current_path ) {
			$current_path = '/';
		}

		if ( untrailingslashit( $target_path ) !== untrailingslashit( $current_path ) ) {
			return false;
		}

		return self::normalized_query( isset( $target['query'] ) ? $target['query'] : '' )
			=== self::normalized_query( isset( $current['query'] ) ? $current['query'] : '' );
	}

	/**
	 * پرسمان را برای مقایسه‌ی نظری به شکل پایدار درمی‌آورد (کلیدها مرتب‌شده).
	 *
	 * @param string $query پرسمان خام.
	 * @return string
	 */
	protected static function normalized_query( $query ) {
		$query = (string) $query;
		if ( '' === $query ) {
			return '';
		}

		$pairs = array();
		parse_str( $query, $parsed );
		foreach ( (array) $parsed as $key => $value ) {
			$pairs[ (string) $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		}
		ksort( $pairs );

		return http_build_query( $pairs, '', '&' );
	}

	/**
	 * مقصد پیوند «مشاهده همه» — تنها منبع حقیقت برای همه‌ی بلوک‌ها.
	 *
	 * مرجع پیوند «مشاهده همه» را همیشه به برگه‌ی کشف می‌برد (`a.text-link`
	 * → `browse.html`)، پس برگه‌ی «کشف داستان‌ها» بر آرشیو نوع محتوا مقدم
	 * است. اگر بلوک خودش نوعی داشته باشد (ردیف «آخرین فیلم‌ها» →
	 * `movie`)، همان نوع به‌صورت `?type=` به نشانی می‌رود تا مقصد با
	 * محتوای ردیف بخواند؛ وگرنه نوعِ زمینه (اثرِ در حال نمایش یا نوعِ
	 * نخستین کارت) به کار می‌رود. در نبودِ برگه‌ی کشف، رفتار پیشین
	 * (آرشیو همان نوع) برقرار می‌ماند.
	 *
	 * پیش‌تر دو پیاده‌سازی از هم جدا داشتیم: یکی در همین کلاس (بر پایه‌ی
	 * `get_post_type()` جاری) و یکی در `render_titles_grid()` (بر پایه‌ی
	 * نخستین کارت). ردیف‌های صفحه‌ی نخست از مسیر دوم می‌رفتند و با تغییر
	 * مسیر اول، همچنان به آرشیو می‌رسیدند؛ این تابع هر دو را یکی می‌کند.
	 *
	 * @param array  $attrs        ویژگی‌های بلوک.
	 * @param string $context_type نوع محتوای زمینه (اختیاری).
	 * @return string نشانی یا رشته‌ی خالی.
	 */
	public static function resolve_more_url( $attrs, $context_type = '' ) {
		$block_type = ! empty( $attrs['postType'] ) ? sanitize_key( (string) $attrs['postType'] ) : '';

		if ( '' === $block_type && ! empty( $attrs['postTypes'] ) ) {
			$block_types = array_values( array_filter( (array) $attrs['postTypes'] ) );
			if ( 1 === count( $block_types ) ) {
				$block_type = sanitize_key( (string) reset( $block_types ) );
			}
		}

		$context_type = (string) $context_type;

		/*
		 * ردیفِ چندنوعی (ردیف داغ با تب‌های «همه/فیلم‌ها/سریال‌ها»): فیلتر
		 * تک‌نوعی معنا ندارد — مرجع هم برای چنین ردیفی به `browse.html`
		 * بدون فیلتر می‌رود. پس نه نوعِ بلوک و نه نوعِ زمینه به کار نمی‌رود.
		 */
		if ( ! empty( $attrs['showTypeTabs'] ) ) {
			$block_type   = '';
			$context_type = '';
		}

		if ( ! in_array( $block_type, manacore_title_post_types(), true ) ) {
			$block_type = in_array( $context_type, manacore_title_post_types(), true ) ? $context_type : '';
		}

		$discovery = manacore_discovery_url( $block_type ? array( 'type' => $block_type ) : array() );

		if ( $discovery ) {
			return $discovery;
		}

		/* آرشیو نوع محتوا؛ صفحه‌ی قسمت به آرشیو سریال‌ها می‌رسد. */
		$archive_type = 'episode' === $context_type ? 'series' : $block_type;

		if ( '' === $archive_type ) {
			$archive_type = $context_type;
		}

		return $archive_type ? (string) get_post_type_archive_link( $archive_type ) : '';
	}

	/**
	 * نام همان شیئی که کوئری جاری نشان می‌دهد (نوشته، ترم یا نوع محتوا).
	 *
	 * برای جانگهدار `{name}` در سرتیتر/زیرتیتر بلوک‌هاست.
	 *
	 * @return string
	 */
	public static function current_object_title() {
		/*
		 * آرشیو نوع محتوا را صریح می‌سنجیم: `get_queried_object()` در بعضی
		 * مسیرهای وردپرس (و هنگام رندر قالب پیش از شروع حلقه) نوشته‌ی
		 * جاری را برمی‌گرداند و سرتیتر آرشیو نام یک چهره‌ی تصادفی می‌شد.
		 */
		if ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$type = is_array( $type ) ? reset( $type ) : $type;
			$obj  = $type ? get_post_type_object( $type ) : null;

			if ( $obj instanceof \WP_Post_Type ) {
				return (string) $obj->labels->name;
			}
		}

		$object = get_queried_object();

		if ( $object instanceof \WP_Post ) {
			return (string) get_the_title( $object );
		}

		if ( $object instanceof \WP_Term ) {
			return (string) $object->name;
		}

		/*
		 * آرشیو نوع محتوا: شیء کوئری یک `WP_Post_Type` است، نه نوشته. اگر
		 * این شاخه نباشد، `get_the_ID()` نخستین نوشته‌ی حلقه را برمی‌گرداند
		 * و سرتیتر آرشیو نام یک چهره‌ی تصادفی می‌شد.
		 */
		if ( $object instanceof \WP_Post_Type ) {
			return (string) $object->labels->name;
		}

		/* در ویرایشگر و پیش‌نمایش، شیء کوئری همان نوشته‌ی در دست ویرایش است. */
		$post_id = (int) get_the_ID();

		return $post_id ? (string) get_the_title( $post_id ) : '';
	}

	/**
	 * ساخت سرصفحه‌ی بلوک (سرتیتر + زیرتیتر + تب‌ها + پیوند «همه»).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @param array $tabs  تب‌ها.
	 * @return string
	 */
	public static function render_header( $attrs, $tabs = array(), $tabs_label = '', $tabs_key = 'data-type' ) {
		$heading    = isset( $attrs['heading'] ) ? trim( (string) $attrs['heading'] ) : '';
		$subheading = isset( $attrs['subheading'] ) ? trim( (string) $attrs['subheading'] ) : '';
		$tabs       = is_array( $tabs ) ? array_values( array_filter( $tabs ) ) : array();

		/*
		 * پیوند «مشاهده همه» مرجع (`a.text-link`) همیشه یک مقصد دارد. اگر
		 * قالب مقصد را تعیین نکرده باشد، آرشیو همان نوعِ محتوای جاری
		 * می‌شود؛ در صفحه‌ی پخش سریال → آرشیو سریال‌ها و در فیلم →
		 * آرشیو فیلم‌ها. بدون این کار پیوند به‌کل رندر نمی‌شد
		 * (`render_header` پیوند بدون مقصد را چاپ نمی‌کند).
		 */
		$attrs     = self::resolve_more_attrs( $attrs );
		$show_more = '' !== self::more_link( $attrs );

		/*
		 * پیوندی که به همین صفحه اشاره می‌کند کاربر را جایی نمی‌برد.
		 *
		 * ریشه‌ی واقعی این نگهبان: نوار فیلترِ صفحه‌ی نخست ویژگی `moreUrl`
		 * ندارد و گفتار بالا «آرشیو همان نوع محتوا» را می‌گذاشت؛ روی صفحه‌ی
		 * نخست این آرشیو خودِ صفحه‌ی نخست است، پس «مشاهده همه» به نشانی
		 * کنونی (ریشه‌ی سایت) اشاره می‌کرد — یک پیوند بی‌مقصد کنار سرصفحه‌ی
		 * بی‌عنوان. مرجع هیچ‌جا چنین پیوندی ندارد (`a.text-link` همیشه کنار
		 * سرتیتر و به `browse.html` می‌رود). با این نگهبان، هم پیوند حذف
		 * می‌شود و هم اگر سرتیتر/زیرتیتری نباشد سرصفحه‌ی خالی رندر نمی‌شود.
		 */
		if ( '' === $heading && '' === $subheading && ! $show_more && empty( $tabs ) ) {
			return '';
		}

		/*
		 * جانگهدار شمار: `{count}` در سرتیتر/زیرتیتر با شمار آثار همان
		 * کوئری پر می‌شود (مثل زیرتیتر «{count} انتخاب، به سلیقه خودت» در
		 * تب لیست تماشای مرجع).
		 *
		 * نکته: `render_header()` **قبل از** حلقه صدازده می‌شود، پس
		 * `last_query()` این‌جا کوئری بلوک قبلی است؛ شمار درست از
		 * `count_posts()` می‌آید (سبک: فقط شمارش، بدون ردیف).
		 */
		if ( false !== strpos( $heading . $subheading, '{count}' ) ) {
			$count      = manacore_fa_digits( self::count_posts( $attrs ) );
			$heading    = str_replace( '{count}', $count, $heading );
			$subheading = str_replace( '{count}', $count, $subheading );
		}

		/*
		 * جانگهدار نام: `{name}` با نام همان شیئی که کوئری نشان می‌دهد پر
		 * می‌شود. سرتیتر فیلموگرافی برگه‌ی چهره («قاب‌هایی با حضور {name}»)
		 * و سرتیتر آرشیوها از همین راه پویا می‌شوند؛ بدون آن، سرتیتر باید
		 * دستی نوشته شود و با تغییر نام چهره کهنه می‌ماند.
		 */
		if ( false !== strpos( $heading . $subheading, '{name}' ) ) {
			$name       = self::current_object_title();
			$heading    = str_replace( '{name}', $name, $heading );
			$subheading = str_replace( '{name}', $name, $subheading );
		}

		$level = isset( $attrs['headingLevel'] ) ? strtolower( (string) $attrs['headingLevel'] ) : 'h2';
		if ( ! in_array( $level, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ) {
			$level = 'h2';
		}

		$icon = self::heading_icon( isset( $attrs['headingIcon'] ) ? $attrs['headingIcon'] : '' );

		ob_start();
		echo '<header class="manacore-block-head">';
		echo '<div class="manacore-block-head-text">';

		if ( '' !== $heading ) {
			printf(
				'<%1$s class="manacore-section-title">%2$s%3$s</%1$s>',
				esc_html( $level ),
				$icon, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $heading )
			);
		}

		if ( '' !== $subheading ) {
			echo '<p class="manacore-section-subtitle">' . esc_html( $subheading ) . '</p>';
		}

		echo '</div>';

		/*
		 * تب‌های نوع (`.section-tabs` مرجع): همان ترتیب سرصفحه‌ی مرجع —
		 * متن، تب‌ها، پیوند «مشاهده همه». اندازه‌ها و رنگ‌ها در
		 * `front.css` بخش ۱۲.۴ عیناً از مرجع گرفته شده‌اند.
		 */
		if ( ! empty( $tabs ) ) {
			/*
			 * برچسب گروه و نام ویژگی داده قابل تنظیم است: تب‌های نوع در
			 * سرصفحه‌ی ردیف‌های آثار `data-type` می‌گیرند و تب‌های دسته
			 * در سرصفحه‌ی مجله `data-category`؛ بدون این پارامترها،
			 * مجموعه‌ی فیلترِ متناظر در JS گزینه‌های خود را پیدا نمی‌کرد.
			 */
			$tabs_label = '' !== $tabs_label ? $tabs_label : __( 'پالایش بر اساس نوع', 'manacore' );
			$tabs_key   = preg_match( '/^data-[a-z0-9-]+$/', $tabs_key ) ? $tabs_key : 'data-type';

			echo '<div class="section-tabs" role="group" aria-label="' . esc_attr( $tabs_label ) . '">';
			foreach ( $tabs as $tab ) {
				$tab    = (array) $tab;
				$active = ! empty( $tab['active'] );
				printf(
					'<button type="button" class="%1$s" ' . $tabs_key . '="%2$s" aria-pressed="%3$s">%4$s</button>',
					$active ? 'active' : '',
					esc_attr( isset( $tab['value'] ) ? $tab['value'] : '' ),
					$active ? 'true' : 'false',
					esc_html( isset( $tab['label'] ) ? $tab['label'] : '' )
				);
			}
			echo '</div>';
		}

		if ( $show_more ) {
			echo self::more_link( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</header>';

		return (string) ob_get_clean();
	}

	/**
	 * آماده‌سازی ویژگی‌های پیوند «مشاهده همه».
	 *
	 * مقصد پیش‌فرض و نگهبانِ «پیوند به همین صفحه» این‌جا یک‌جا انجام
	 * می‌شود تا هم `render_header()` و هم سرصفحه‌های اختصاصی بلوک‌ها
	 * (مثل `.schedule-title` برنامه‌ی هفتگی) یک رفتار داشته باشند.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return array ویژگی‌های اصلاح‌شده.
	 */
	public static function resolve_more_attrs( $attrs ) {
		if ( empty( $attrs['showMore'] ) || ! empty( $attrs['moreUrl'] ) ) {
			return $attrs;
		}

		$current_type = (string) get_post_type();

		/* روی صفحه‌ی تک‌برگه/آرشیو، نوعِ اثر در حال نمایش مرجع است. */
		if ( ! in_array( $current_type, manacore_title_post_types(), true ) ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Post
				&& in_array( $queried->post_type, manacore_title_post_types(), true ) ) {
				$current_type = (string) $queried->post_type;
			}
		}

		$resolved = self::resolve_more_url( $attrs, $current_type );

		if ( $resolved ) {
			$attrs['moreUrl'] = $resolved;
		}

		return $attrs;
	}

	/**
	 * پیوند «مشاهده همه» (`.text-link` مرجع) — یا رشته‌ی خالی.
	 *
	 * همرنگیِ کلاس با مرجع: `class="text-link"` و همان شِورُنِ چرخیده‌ی
	 * مرجع. کلاس `manacore-more-link` قلاب CSS/آزمون خودمان است.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public static function more_link( $attrs ) {
		if ( empty( $attrs['showMore'] ) || empty( $attrs['moreUrl'] ) || self::is_current_url( $attrs['moreUrl'] ) ) {
			return '';
		}

		/*
		 * نشانی بدون اسکیم و بدون اسلش آغازین، از دید `esc_url()` یک نشانی
		 * ناقص است و وردپرس پیش‌فرض پروتکل را می‌چسباند؛ نتیجه‌اش پیوند
		 * شکسته‌ی `http://cast` بود. اگر مقدار نامک یک برگه‌ی موجود باشد،
		 * همان پیوند واقعی جایش می‌نشیند؛ وگرنه به سایت نسبت داده می‌شود.
		 */
		$url = trim( (string) $attrs['moreUrl'] );

		if ( '' !== $url && ! preg_match( '~^([a-z][a-z0-9+.\-]*:|//|/|#|\?)~i', $url ) ) {
			$page = get_page_by_path( $url );

			$url = $page instanceof \WP_Post
				? (string) get_permalink( $page )
				: home_url( '/' . ltrim( $url, '/' ) );
		}

		$label  = ! empty( $attrs['moreLabel'] ) ? $attrs['moreLabel'] : __( 'مشاهده همه', 'manacore' );
		$target = ( isset( $attrs['moreTarget'] ) && '_blank' === $attrs['moreTarget'] )
			? ' target="_blank" rel="noopener"'
			: '';

		return sprintf(
			'<a class="text-link manacore-more-link" href="%1$s"%2$s>%3$s<svg class="manacore-more-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg></a>',
			esc_url( $url ),
			$target,
			esc_html( $label )
		);
	}

	/**
	 * برچسب فارسی تب‌های نوع (`.section-tabs` مرجع).
	 *
	 * تنها انواعی که در فهرست کارت‌ها حاضرند تب می‌گیرند، ولی ترتیب تب‌ها
	 * همیشه ثابت و بر اساس همین نگاشت است تا جای دکمه‌ها با تغییر داده
	 * جابه‌جا نشود.
	 *
	 * @return array
	 */
	public static function type_tab_labels() {
		return array(
			'movie'      => __( 'فیلم‌ها', 'manacore' ),
			'series'     => __( 'سریال‌ها', 'manacore' ),
			'anime'      => __( 'انیمه‌ها', 'manacore' ),
			'collection' => __( 'مجموعه‌ها', 'manacore' ),
			'episode'    => __( 'قسمت‌ها', 'manacore' ),
			'post'       => __( 'مقالات', 'manacore' ),
		);
	}

	/**
	 * آیکون سرتیتر.
	 *
	 * @param string $icon شناسه‌ی آیکون.
	 * @return string
	 */
	public static function heading_icon( $icon ) {
		$icon = sanitize_key( (string) $icon );
		if ( ! $icon ) {
			return '';
		}

		$paths = self::heading_icon_paths();
		if ( ! isset( $paths[ $icon ] ) ) {
			return '';
		}

		/*
		 * آیکون‌ها **خطی** (stroke) رندر می‌شوند، عیناً مثل مرجع: `fill="none"`،
		 * `stroke="currentColor"` و ضخامت ۲. پیش‌تر مسیر `fill="currentColor"`
		 * داشت و چون ویژگی نمایشی روی خودِ `<path>` بر مقدار ارث‌بری‌شده از
		 * `<svg>` چیره می‌شود، آیکون‌ها توپر (لکه‌ی رنگی) درمی‌آمدند نه خطی.
		 * `data-icon` هم قلاب CSS برای رنگ‌های ویژه (مثل آیکون داغِ طلایی) است.
		 */
		return sprintf(
			'<svg class="manacore-section-icon" data-icon="%1$s" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%2$s"/></svg>',
			esc_attr( $icon ),
			esc_attr( $paths[ $icon ] )
		);
	}

	/**
	 * مسیرهای SVG آیکون‌های سرتیتر.
	 *
	 * @return array
	 */
	public static function heading_icon_paths() {
		return array(
			'film'     => 'M4 3h16a1 1 0 011 1v16a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm2 2v2h2V5H6zm10 0v2h2V5h-2zM6 9v6h12V9H6zm0 8v2h2v-2H6zm10 0v2h2v-2h-2z',
			'star'     => 'M12 2l2.9 6.3 6.8.8-5 4.7 1.3 6.8L12 17.4 6 20.6l1.3-6.8-5-4.7 6.8-.8z',
			/* شعله — عیناً مسیر مرجع (بخش «پرطرفدارترین‌ها» در صفحه‌ی نخست). */
			'fire'     => 'M12 2s4 4 4 8a4 4 0 0 1-8 0c0-4 4-8 4-8zM5 14a7 7 0 0 0 14 0',
			'clock'    => 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 5h-2v6l5 3 1-1.7-4-2.3V7z',
			'eye'      => 'M12 5C6.5 5 2.7 9.2 2 12c.7 2.8 4.5 7 10 7s9.3-4.2 10-7c-.7-2.8-4.5-7-10-7zm0 11a4 4 0 110-8 4 4 0 010 8zm0-2a2 2 0 100-4 2 2 0 000 4z',
			'grid'     => 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z',
			'playlist' => 'M3 5h12v2H3V5zm0 4h12v2H3V9zm0 4h8v2H3v-2zm14-8v8.6a3 3 0 101.5 2.6V7h3V5h-4.5z',
			'heart'    => 'M12 21s-8-4.9-8-10.3A4.7 4.7 0 0112 7a4.7 4.7 0 018 3.7C20 16.1 12 21 12 21z',
			'crown'    => 'M3 7l4 4 5-7 5 7 4-4v11H3V7z',
			'compass'  => 'M12 2a10 10 0 100 20 10 10 0 000-20zm3.4 6.6l-2.1 5.3-5.3 2.1 2.1-5.3 5.3-2.1z',
			/* تقویم — عیناً مسیر مرجع (بخش «هر روز، یک قسمت تازه»). */
			'calendar' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z',
			/*
			 * تلویزیون — آیکون سرتیتر «فقط یک قسمت دیگر...»؛ مسیر مرجع که یک
			 * `<rect>` و یک `<path>` بود، این‌جا به یک مسیر یکپارچه تبدیل شده
			 * (قاب + آنتن + پایه) تا سازنده‌ی تک‌مسیری همان شکل را بکشد.
			 */
			'tv'       => 'M4 4h16a2 2 0 012 2v11a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zM8 2l4 2 4-2M8 19h8',
			/* درخشش — آیکون سرتیتر «امشب با چه حال‌وهوایی؟» (عیناً مسیر مرجع). */
			'sparkle'  => 'm12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z',
			/*
			 * روزنامه — آیکون سرتیتر «کوهه‌مگ»؛ مسیر مرجع (rect + سه خط) به یک
			 * مسیر یکپارچه تبدیل شده است.
			 */
			'newspaper' => 'M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2zM7 3v4M17 3v4M3 10h18',
			/*
			 * اطلاعات — جعبه‌ی سرصفحه‌ی برگه‌های «راهنما» و «قوانین و حریم
			 * خصوصی»؛ عیناً همان دو شکل مرجع:
			 *   `<circle cx="12" cy="12" r="9"/>` → کمان کامل در قالب یک مسیر
			 *   `M12 11v5m0-8h.01`             → میله‌ی «i» و نقطه‌ی بالای آن
			 * با `stroke-linecap: round` (در CSS) نقطه هم مثل مرجع گرد درمی‌آید.
			 */
			'info'     => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zM12 11v5M12 8h.01',
			/* چهره — سرتیتر «بازیگران و کارگردان‌ها» و نقش‌های عوامل. */
			'person'   => 'M12 3a4.5 4.5 0 100 9 4.5 4.5 0 000-9zm0 11c-4.4 0-8 2.6-8 5.8V21h16v-1.2C20 16.6 16.4 14 12 14z',
		);
	}

	/**
	 * رندر حالت خالی.
	 *
	 * @param array        $attrs ویژگی‌ها.
	 * @param array|string $base  کلاس پایه.
	 * @return string
	 */
	public static function render_empty( $attrs, $base = '' ) {
		if ( ! empty( $attrs['hideIfEmpty'] ) && ! self::is_editor_preview() ) {
			return '';
		}

		/*
		 * در بازدید عمومی، پیام «خالی» فقط وقتی نمایش داده می‌شود که نویسنده
		 * خودش متنی نوشته باشد. حالت‌های خالیِ پیش‌فرض (مثل بلوک متادیتایی که
		 * روی یک قسمت هیچ فیلدی ندارد) نباید در برگه‌ی نهایی دیده شوند؛
		 * در بوم ویرایشگر همچنان نمایش داده می‌شوند تا نویسنده بفهمد بلوک
		 * خالی است و بتواند متن بگذارد.
		 */
		if ( empty( $attrs['emptyText'] ) && ! self::is_editor_preview() ) {
			return '';
		}

		$text = ! empty( $attrs['emptyText'] )
			? $attrs['emptyText']
			: __( 'محتوایی برای نمایش وجود ندارد.', 'manacore' );

		return '<div ' . self::wrapper( $attrs, array( $base, 'is-empty' ) ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput
			. self::render_header( $attrs )
			. '<p class="manacore-empty-state">' . esc_html( $text ) . '</p>'
			. '</div>';
	}

	/**
	 * نگاشت ویژگی‌های بلوک به آرگومان‌های {@see Templates::card()}.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return array
	 */
	public static function card_args( $attrs ) {
		$defaults = self::defaults( self::card_attributes() );
		$attrs    = wp_parse_args( is_array( $attrs ) ? $attrs : array(), $defaults );

		return array(
			'show_rating'    => ! empty( $attrs['showRating'] ),
			'show_year'      => ! empty( $attrs['showYear'] ),
			'show_type'      => ! empty( $attrs['showType'] ),
			'show_quality'   => ! empty( $attrs['showQuality'] ),
			'show_genre'     => ! empty( $attrs['showGenre'] ),
			'show_watchlist' => ! empty( $attrs['showWatchlist'] ),
			'show_premium'   => ! empty( $attrs['showPremium'] ),
			'show_overlay'   => ! empty( $attrs['showOverlay'] ),
			'show_episode'   => ! empty( $attrs['showEpisode'] ),
			'show_views'     => ! empty( $attrs['showViews'] ),
			'show_runtime'   => ! empty( $attrs['showRuntime'] ),
			'show_excerpt'   => ! empty( $attrs['showExcerpt'] ),
			'excerpt_words'  => max( 4, (int) $attrs['excerptWords'] ),
			'title_tag'      => (string) $attrs['titleTag'],
			'title_lines'    => max( 0, (int) $attrs['titleLines'] ),
			'image_ratio'    => (string) $attrs['imageRatio'],
			'style'          => (string) $attrs['cardStyle'],
			'link_target'    => (string) $attrs['cardLinkTarget'],
			'extra_class'    => (string) $attrs['cardClass'],
		);
	}

	/**
	 * گزینش یک مقدار معتبر از میان فهرست مجاز.
	 *
	 * ویژگی‌های بلوک در محتوای ذخیره‌شده باقی می‌مانند؛ اگر گزینه‌ای بعداً
	 * حذف یا تغییر نام داده شود، مقدار کهنه به کلاسی بی‌اثر تبدیل می‌شد.
	 * این کمک‌تابع همیشه یا مقداری از فهرست مجاز برمی‌گرداند یا پیش‌فرض را.
	 *
	 * @param mixed  $value    مقدار ورودی.
	 * @param array  $allowed  فهرست مجاز (کلید => برچسب).
	 * @param string $fallback مقدار پیش‌فرض.
	 * @return string
	 */
	public static function pick( $value, $allowed, $fallback ) {
		$value = sanitize_key( (string) $value );

		return isset( $allowed[ $value ] ) ? $value : $fallback;
	}

	/**
	 * ساخت style درون‌خطی شبکه.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public static function grid_style( $attrs ) {
		$vars = array();

		$columns = isset( $attrs['columns'] ) ? (int) $attrs['columns'] : 6;
		$vars[]  = '--mc-columns:' . max( 1, min( self::MAX_COLUMNS, $columns ) );

		if ( ! empty( $attrs['columnsTablet'] ) ) {
			$vars[] = '--mc-columns-tablet:' . max( 1, min( self::MAX_COLUMNS, (int) $attrs['columnsTablet'] ) );
		}
		if ( ! empty( $attrs['columnsMobile'] ) ) {
			$vars[] = '--mc-columns-mobile:' . max( 1, min( self::MAX_COLUMNS, (int) $attrs['columnsMobile'] ) );
		}
		if ( ! empty( $attrs['gap'] ) ) {
			$vars[] = '--mc-gap:' . max( 0, min( 64, (int) $attrs['gap'] ) ) . 'px';
		}

		return $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : '';
	}

	/**
	 * کلاس‌های ظرف کارت‌ها بر اساس چیدمان.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public static function grid_classes( $attrs ) {
		$layout  = self::pick( isset( $attrs['layout'] ) ? $attrs['layout'] : '', Block_Data::layouts(), 'grid' );
		$classes = array( 'manacore-grid-cards' );

		/*
		 * نگاشت چیدمان به کلاس. کلید «grid» کلاس افزوده‌ای ندارد چون
		 * .manacore-grid-cards خودش شبکه است.
		 */
		$map = array(
			'carousel'  => 'is-scroll',
			'list'      => 'is-list',
			'masonry'   => 'is-masonry',
			'spotlight' => 'is-spotlight',
			'showcase'  => 'is-showcase',
			'metro'     => 'is-metro',
			'ranking'   => 'is-ranking',
		);

		if ( isset( $map[ $layout ] ) ) {
			$classes[] = $map[ $layout ];
		}

		return implode( ' ', $classes );
	}
}

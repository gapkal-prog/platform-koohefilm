<?php
/**
 * موتور جستجوی یکپارچه: نرمال‌سازی فارسی، شکل‌های جایگزین و پرس‌وجوی مشترک.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Search
 *
 * چرا این کلاس هست؟
 *
 * جستجو در وردپرس با `LIKE` خام روی ستون‌های نوشته انجام می‌شود و دیتابیس
 * هیچ نرمال‌سازی‌ای ندارد؛ پس «شوگان» با «شوگان» یکی است ولی «شوگان» با
 * «شوگان» (با «ی/ي» عربی یا نیم‌فاصله) یکی نیست. فارسی‌زبان‌ها هر دو شکل را
 * تایپ می‌کنند و عنوان‌ها هم با هر دو شکل ثبت شده‌اند. راه‌حل بدون دست‌زدن به
 * دیتابیس: به‌جای هر شرط `LIKE`، یک گروهِ `OR` از شکل‌های ممکن واژه بسازیم.
 *
 * این کلاس تنها منبعِ حقیقت جستجو است:
 *   • مسیر REST افزونه (`manacore/v1/search`) از `query()` استفاده می‌کند؛
 *   • برگه‌ی جستجوی قالب (`/s/...`) از `expand_sql()` شکل‌های جایگزین
 *     می‌گیرد (کوئری اصلی)؛
 *   • بلوک «جستجوی زنده» و اورلی سربرگ هر دو به همان مسیر REST می‌زنند.
 */
class Search {

	use Singleton;

	/**
	 * بیشترین تعداد شکل جایگزین برای هر واژه.
	 *
	 * هر شکل یک شرط `LIKE` اضافه می‌کند؛ سقفِ کوچک هم بار دیتابیس را مهار
	 * می‌کند و هم از انفجار ترکیبی (cross product) جلوگیری می‌کند.
	 */
	const MAX_VARIANTS = 6;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		/*
		 * اولویت ۱۱: بعد از `Query::search_meta()` (اولویت ۱۰) اجرا می‌شود تا
		 * شرط متای «نام اصلی/نام‌های دیگر» که آن افزوده هم شکل‌های جایگزین
		 * بگیرد؛ وگرنه جستجوی «Shogun» روی اثری با نام اصلی لاتین و شکل
		 * عربی همان نام پیدا نمی‌شد.
		 */
		add_filter( 'posts_search', array( $this, 'expand_sql' ), 11, 2 );
	}

	/* ---------------------------------------------------------------------
	 * نرمال‌سازی
	 * ------------------------------------------------------------------ */

	/**
	 * نگاشت نویسه‌های هم‌ارز به شکل فارسیِ رایج.
	 *
	 * @return array<string,string>
	 */
	protected static function char_map() {
		return array(
			'ي' => 'ی', // ی عربی.
			'ى' => 'ی', // الف مقصوره.
			'ك' => 'ک', // ک عربی.
			'ة' => 'ه', // تای گرد.
			'ۀ' => 'ه',
			'ؤ' => 'و',
			'إ' => 'ا',
			'أ' => 'ا',
			'ٱ' => 'ا',
			'ﻻ' => 'لا',
			'ـ' => '',  // کشیدگی (tatweel).
		);
	}

	/**
	 * نرمال‌سازی متن برای مقایسه‌ی فارسی/چندزبانه.
	 *
	 * کارها: یکسان‌سازی نویسه‌های عربی/فارسی، حذف اعراب و نویسه‌های کنترلی
	 * راست‌به‌چپ، تبدیل نیم‌فاصله به فاصله، تبدیل رقم‌های فارسی/عربی به لاتین،
	 * و جمع‌کردن فاصله‌های تکراری.
	 *
	 * @param string $text متن خام.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = (string) $text;

		if ( '' === $text ) {
			return '';
		}

		$text = strtr( $text, self::char_map() );

		/* نیم‌فاصله، نویسه‌های جهت‌دهی و فاصله‌ی سخت => فاصله‌ی ساده. */
		$text = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8D", "\xE2\x80\x8E", "\xE2\x80\x8F", "\xC2\xA0" ), ' ', $text );

		/*
		 * اعراب (فتحه، کسره، ضمه، سکون، تنوین و اعراب قرآنی). پاک‌سازی با
		 * preg_replace روی بازه‌های یونیکد انجام می‌شود تا به افزونه‌ی mbstring
		 * وابسته نباشیم.
		 */
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text );

		/* رقم‌های فارسی و عربی => لاتین. */
		$text = strtr(
			$text,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);

		/* حروف بزرگ/کوچک لاتین یکسان می‌شود تا «dune» و «Dune» یکی باشند. */
		$text = preg_replace( '/\s+/u', ' ', $text );

		if ( function_exists( 'mb_strtolower' ) ) {
			$text = mb_strtolower( trim( $text ), 'UTF-8' );
		} else {
			$text = strtolower( trim( $text ) );
		}

		return (string) $text;
	}

	/**
	 * شکل‌های جایگزین یک واژه‌ی جستجو.
	 *
	 * ترتیب مهم است: شکل خودِ کاربر اول می‌آید تا شرط دقیق در ابتدای گروهِ
	 * `OR` بماند و خروجی وردپرس هم بدون تغییر حفظ شود.
	 *
	 * @param string $term واژه.
	 * @return string[]
	 */
	public static function variants( $term ) {
		$term = trim( (string) $term );

		if ( '' === $term ) {
			return array();
		}

		$variants = array( $term );
		$push     = static function ( $candidate ) use ( &$variants ) {
			$candidate = trim( (string) $candidate );

			if ( '' === $candidate || count( $variants ) >= self::MAX_VARIANTS ) {
				return;
			}

			if ( ! in_array( $candidate, $variants, true ) ) {
				$variants[] = $candidate;
			}
		};

		/* ۱) شکل نرمال‌شده (اعراب‌زدایی، یکسان‌سازی نویسه‌ها و رقم‌ها). */
		$push( self::normalize( $term ) );

		/* ۲) نیم‌فاصله: هم چسبیده و هم با فاصله. */
		$zwnj = "\xE2\x80\x8C";
		if ( false !== strpos( $term, $zwnj ) ) {
			$push( str_replace( $zwnj, '', $term ) );
			$push( str_replace( $zwnj, ' ', $term ) );
		}

		/* ۳) لاتین‌سازی رقم‌ها (کاربر گاهی «۲۰۲۴» را «2024» می‌نویسد). */
		$push(
			strtr(
				$term,
				array(
					'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
					'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
				)
			)
		);

		/*
		 * ۴) شکل عربیِ نویسه‌ها. «نرمال‌سازی» فقط به سمت فارسی می‌رود، ولی
		 * داده‌ی قدیمی سایت هم ممکن است با «ي/ك» ثبت شده باشد؛ پس معکوسِ آن
		 * هم باید جست‌وجو شود.
		 */
		$push( strtr( $term, array( 'ی' => 'ي', 'ک' => 'ك' ) ) );

		return $variants;
	}

	/* ---------------------------------------------------------------------
	 * پرس‌وجو
	 * ------------------------------------------------------------------ */

	/**
	 * ساخت پرس‌وجوی جستجو روی آثار (فیلم/سریال/انیمه).
	 *
	 * @param array $args {
	 *     آرگومان‌ها.
	 *
	 *     @type string $s          عبارت جستجو.
	 *     @type string $type       نوع‌های محتوا با کاما (خالی = همه‌ی آثار).
	 *     @type int    $per_page   تعداد در هر برگه.
	 *     @type bool   $found_rows شمارش کل (برای صفحه‌بندی لازم است).
	 *     @type string $orderby    مرتب‌سازی (پیش‌فرض: مرتبط‌ترین‌ها).
	 *     @type string $order      جهت مرتب‌سازی.
	 * }
	 * @return \WP_Query
	 */
	public static function query( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				's'          => '',
				'type'       => '',
				'per_page'   => 8,
				'found_rows' => false,
				'orderby'    => '',
				'order'      => '',
			)
		);

		$term = trim( (string) $args['s'] );

		/*
		 * عبارت خالی (یا فقط فاصله) نباید همه‌ی محتوا را برگرداند؛ نه از نظر
		 * کارایی درست است و نه از نظر رفتار. `post__in` تهی، پاسخ خالی
		 * قطعی می‌دهد.
		 */
		if ( '' === $term ) {
			return new \WP_Query(
				array(
					'post__in'       => array( 0 ),
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
		}

		$types = manacore_title_post_types();
		$only  = self::sanitize_types( (string) $args['type'] );

		if ( $only ) {
			$intersect = array_values( array_intersect( $only, $types ) );

			if ( $intersect ) {
				$types = $intersect;
			}
		}

		$query_args = array(
			'post_type'            => $types,
			'post_status'          => 'publish',
			's'                    => $term,
			'posts_per_page'       => min( 24, max( 1, (int) $args['per_page'] ) ),
			'ignore_sticky_posts'  => true,
			'no_found_rows'        => empty( $args['found_rows'] ),
			'update_post_term_cache' => false,

			/*
			 * پرچم‌های خودمان: `manacore_search` شکل‌های جایگزین را روشن
			 * می‌کند و `manacore_search_meta` جستجو در «نام اصلی/نام‌های
			 * دیگر» را (از `Query::search_meta()`) به همین کوئری می‌آورد.
			 * بدون پرچم دوم، فقط عنوان/خلاصه/متن بررسی می‌شد و جستجوی نام
			 * لاتین روی اثری با عنوان فارسی نتیجه نمی‌داد.
			 */
			'manacore_search'      => true,
			'manacore_search_meta' => true,
		);

		if ( '' !== (string) $args['orderby'] ) {
			$query_args['orderby'] = (string) $args['orderby'];
		}

		if ( '' !== (string) $args['order'] ) {
			$query_args['order'] = (string) $args['order'];
		}

		/**
		 * فیلتر آرگومان‌های پرس‌وجوی جستجو.
		 *
		 * @param array $query_args آرگومان‌های آماده.
		 * @param array $args       آرگومان‌های ورودی.
		 */
		$query_args = apply_filters( 'manacore_search_query_args', $query_args, $args );

		return new \WP_Query( $query_args );
	}

	/**
	 * پاک‌سازی فهرست نوع‌های محتوا (رشته با کاما/فاصله یا آرایه).
	 *
	 * @param string|array $types ورودی.
	 * @return string[]
	 */
	public static function sanitize_types( $types ) {
		if ( is_array( $types ) ) {
			$parts = $types;
		} else {
			$parts = preg_split( '/[\s,،]+/', (string) $types, -1, PREG_SPLIT_NO_EMPTY );
		}

		$out = array();

		foreach ( (array) $parts as $type ) {
			$key = sanitize_key( $type );

			if ( '' !== $key && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * شکل‌های جایگزین در SQL
	 * ------------------------------------------------------------------ */

	/**
	 * گسترش شرط‌های `LIKE` به شکل‌های جایگزین واژه.
	 *
	 * ساختار رشته‌ای که وردپرس می‌سازد (هر واژه یک گروهِ سه‌شرطی):
	 *
	 *   AND ( ((عنوان LIKE '%واژه%') OR (خلاصه LIKE '%واژه%') OR (متن LIKE '%واژه%')) )
	 *
	 * اینجا هر شرط `ستون LIKE '%واژه%'` با یک گروهِ `OR` از شکل‌های ممکن
	 * جایگزین می‌شود و بقیه‌ی ساختار — یعنی «و» میان واژه‌ها، سدِّ رمز و
	 * ترتیب اصلی — دست‌نخورده می‌ماند:
	 *
	 *   AND ( ((عنوان LIKE '%واژه%' OR عنوان LIKE '%شکل۲%') OR ...) )
	 *
	 * @param string    $search شرط SQL جستجو.
	 * @param \WP_Query $query  کوئری.
	 * @return string
	 */
	public function expand_sql( $search, $query ) {
		global $wpdb;

		/*
		 * رشته‌ی `posts_search` با فاصله شروع می‌شود (`\" AND (...) \"`) و
		 * مستقیماً به `WHERE` چسبانده می‌شود؛ پس اینجا هیچ trim ای انجام
		 * نمی‌شود — کم‌کردن فاصله‌ی آغازین، کوئری را به خطای نحوی می‌برد
		 * (`...post_type = 'movie'AND (...)`).
		 */
		$search = (string) $search;

		if ( '' === trim( $search ) || is_admin() ) {
			return $search;
		}

		$term = (string) $query->get( 's' );

		if ( '' === trim( $term ) ) {
			return $search;
		}

		/*
		 * همان دروازه‌ی `Query::search_meta()`، به‌اضافه‌ی پرچم مشترک: یا
		 * کوئری اصلیِ برگه‌ی جستجو، یا کوئری‌ای که خودمان ساخته‌ایم.
		 */
		$flagged = (bool) $query->get( 'manacore_search' ) || (bool) $query->get( 'manacore_search_meta' );

		if ( ! $flagged && ! ( $query->is_search() && $query->is_main_query() ) ) {
			return $search;
		}

		/*
		 * فقط ستون‌هایی که معنی دارند و فقط واژه‌های ساده (بدون نقل‌قول و
		 * نویسه‌های ویژه‌ی `LIKE`). اگر واژه `%` یا `_` یا بک‌اسلش داشته
		 * باشد، دست‌نخورده رها می‌شود تا معنای شرط عوض نشود.
		 */
		$pattern = '/([A-Za-z0-9_]+\.(?:post_title|post_excerpt|post_content|meta_value))\s+LIKE\s+\'%([^\'\\\\%_]*)%\'/';

		$touched = false;

		$expanded = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $wpdb, &$touched ) {
				$variants = self::variants( $matches[2] );

				if ( count( $variants ) < 2 ) {
					return $matches[0];
				}

				$touched = true;
				$parts   = array();

				foreach ( $variants as $variant ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام ستون از الگوی بالا می‌آید و مقدارش با prepare بسته می‌شود.
					$parts[] = $wpdb->prepare( "{$matches[1]} LIKE %s", '%' . $wpdb->esc_like( $variant ) . '%' );
				}

				return '( ' . implode( ' OR ', $parts ) . ' )';
			},
			$search
		);

		return $touched && is_string( $expanded ) ? $expanded : $search;
	}
}

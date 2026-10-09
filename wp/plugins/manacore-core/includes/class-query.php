<?php
/**
 * تغییرات کوئری، فیلترها و جستجوی پیشرفته.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Query
 */
class Query {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'pre_get_posts', array( $this, 'adjust' ) );
		add_filter( 'posts_search', array( $this, 'search_meta' ), 10, 2 );
		add_action( 'manacore_after_save_meta', array( $this, 'flush_person_cache' ) );
		add_action( 'save_post_person', array( $this, 'flush_person_cache' ) );
	}

	/**
	 * پاک‌کردن کش «آثار این عامل» پس از تغییر اثر یا عامل.
	 *
	 * برای اثر، کش همه‌ی عواملی که در آن آمده‌اند پاک می‌شود؛ برای عامل، کش خودش.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 */
	public function flush_person_cache( $post_id ) {
		$post_id = absint( $post_id );

		if ( 'person' === get_post_type( $post_id ) ) {
			delete_transient( 'manacore_person_works_' . $post_id );
			return;
		}

		$roles = array_merge( array_values( Crew::ROLE_FIELDS ), array( 'cast' ) );
		foreach ( $roles as $role ) {
			foreach ( Crew::items( $post_id, $role ) as $item ) {
				if ( ! empty( $item['person_id'] ) ) {
					delete_transient( 'manacore_person_works_' . absint( $item['person_id'] ) );
				}
			}
		}
	}

	/**
	 * تنظیم کوئری اصلی.
	 *
	 * @param \WP_Query $query کوئری.
	 */
	public function adjust( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$types = manacore_title_post_types();

		// شامل کردن انواع محتوا در نتایج جستجو.
		if ( $query->is_search() && ! $query->get( 'post_type' ) ) {
			$query->set( 'post_type', array_merge( array( 'post', 'page' ), $types ) );
		}

		/*
		 * تعداد آیتم در آرشیوها.
		 *
		 * علاوه بر آرشیو انواع محتوا و تاکسونومی‌های افزونه، دسته‌ها و
		 * برچسب‌های هسته و صفحه‌ی جستجو نیز باید از همین تنظیم پیروی کنند؛
		 * وگرنه شبکه‌ی پوستری در آن صفحه‌ها ناهماهنگ می‌شود.
		 */
		$archive_taxonomies = array_keys( Taxonomies::instance()->definitions() );

		if (
			$query->is_post_type_archive( $types )
			|| $query->is_tax( $archive_taxonomies )
			|| $query->is_category()
			|| $query->is_tag()
			|| $query->is_search()
		) {
			$query->set( 'posts_per_page', (int) manacore_get_option( 'items_per_page', 24 ) );
		}

		$this->apply_filters_from_request( $query );
	}

	/**
	 * اعمال فیلترهای موجود در نشانی (ژانر، سال، کیفیت، مرتب‌سازی).
	 *
	 * @param \WP_Query $query کوئری.
	 */
	protected function apply_filters_from_request( $query ) {
		if ( ! $query->is_archive() && ! $query->is_search() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tax_query = (array) $query->get( 'tax_query' );

		/*
		 * نگاشت مشترک با نوار فیلتر (manacore_filter_params) تا هر فیلدی که
		 * در نوار فیلتر رندر می‌شود عملاً روی کوئری هم اعمال شود.
		 * کلید نگاشت، نام تاکسونومی است؛ پس معکوس می‌کنیم.
		 */
		$mappings = array_flip( manacore_filter_params() );

		foreach ( $mappings as $param => $taxonomy ) {
			if ( empty( $_GET[ $param ] ) ) {
				continue;
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$raw   = wp_unslash( $_GET[ $param ] );
			$terms = is_array( $raw ) ? array_map( 'sanitize_title', $raw ) : array_map( 'sanitize_title', explode( ',', (string) $raw ) );
			$terms = array_filter( $terms );
			if ( $terms ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
				);
			}
		}

		// شمارش تنها بندهای عددی (کلید relation نباید شمرده شود).
		$clauses = array_filter( array_keys( $tax_query ), 'is_int' );
		if ( count( $clauses ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}

		/*
		 * فیلترهای فراداده‌ای سایدبار (بازه‌ی سال ساخت، حداقل امتیاز IMDb،
		 * دوبله). بندهای آماده از `manacore_meta_filter_clauses()` می‌آید
		 * تا مسیر آرشیو و مسیر حلقه‌ی بلوکی یک رفتار داشته باشند.
		 */
		$query->set(
			'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			manacore_merge_meta_query( (array) $query->get( 'meta_query' ), manacore_meta_filter_clauses() )
		);

		$orderby = isset( $_GET['mc_sort'] ) ? sanitize_key( wp_unslash( $_GET['mc_sort'] ) ) : '';
		// phpcs:enable

		/*
		 * نگاشت مقدار به آرگومان‌ها در `manacore_sort_query_args()` است تا
		 * حلقه‌های بلوکیِ صفحه‌ی «کشف» هم دقیقاً همین رفتار را داشته باشند.
		 */
		$sorted = manacore_sort_query_args( $orderby );

		if ( isset( $sorted['meta'] ) ) {
			$this->order_by_meta_num( $query, $sorted['meta'] );
		} elseif ( isset( $sorted['orderby'] ) ) {
			$query->set( 'orderby', $sorted['orderby'] );
			$query->set( 'order', $sorted['order'] );
		}
	}

	/**
	 * مرتب‌سازی نزولی بر پایه‌ی یک فیلد عددی، بدون حذف آثار بدون آن فیلد.
	 *
	 * استفاده‌ی ساده از meta_key سبب می‌شود ووردپرس یک INNER JOIN بزند و
	 * هر اثری که آن فیلد را ندارد از نتیجه حذف شود (آرشیو ناقص می‌شود).
	 * با meta_query دارای بند EXISTS/NOT EXISTS، آثار بدون مقدار در انتها
	 * قرار می‌گیرند و از فهرست بیرون نمی‌افتند.
	 *
	 * @param \WP_Query $query کوئری.
	 * @param string    $key   کلید فیلد سفارشی.
	 */
	protected function order_by_meta_num( $query, $key ) {
		$meta_query = (array) $query->get( 'meta_query' );

		/*
		 * نوع ستون باید اعشاری باشد، نه `NUMERIC`:
		 * `NUMERIC` در وردپرس به `CAST(... AS SIGNED)` نگاشته می‌شود و
		 * اعشار را می‌بُرد، پس «۹.۴» و «۹.۲» هر دو ۹ می‌شدند و ترتیب
		 * میان‌شان با تاریخ — یعنی تصادفی — تعیین می‌شد. روی همین داده
		 * سنجیده شد: با `NUMERIC` ترتیب «۶، ۱۸، ۷، ۸، ۹» و با
		 * `DECIMAL(10,2)` ترتیب درست «۱۸، ۶، ۷، ۸، ۹» به دست می‌آید.
		 */
		$meta_query['manacore_sort'] = array(
			'key'     => $key,
			'compare' => 'EXISTS',
			'type'    => 'DECIMAL(10,2)',
		);
		$meta_query['manacore_sort_missing'] = array(
			'key'     => $key,
			'compare' => 'NOT EXISTS',
		);
		$meta_query['relation'] = 'OR';

		$query->set( 'meta_query', $meta_query );
		$query->set(
			'orderby',
			array(
				'manacore_sort' => 'DESC',
				'date'          => 'DESC',
			)
		);
	}

	/**
	 * گسترش جستجو به عنوان اصلی و نام‌های دیگر.
	 *
	 * @param string    $search عبارت SQL جستجو.
	 * @param \WP_Query $query  کوئری.
	 * @return string
	 */
	public function search_meta( $search, $query ) {
		global $wpdb;

		if ( is_admin() || empty( $search ) ) {
			return $search;
		}

		/*
		 * مسیر همیشگی: کوئری اصلی برگه‌ی جستجو. مسیر دوم: حلقه‌ی بلوکی که
		 * با ویژگی `inheritFilters` جستجوی نوار فیلتر را ارث برده است
		 * (صفحه‌ی «کشف داستان‌ها») — آنجا هم باید نام اصلی/نام‌های دیگر
		 * جست‌وجو شوند، وگرنه جستجوی «Shogun» روی اثری با عنوان فارسی
		 * «شوگان» چیزی پیدا نمی‌کند در حالی که در آرشیوها پیدا می‌کند.
		 */
		$block_search = (bool) $query->get( 'manacore_search_meta' );

		if ( ! $block_search && ( ! $query->is_search() || ! $query->is_main_query() ) ) {
			return $search;
		}

		$term = $query->get( 's' );
		if ( ! $term ) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		$meta_sql = $wpdb->prepare(
			" OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} mcm
				WHERE mcm.post_id = {$wpdb->posts}.ID
				AND mcm.meta_key IN ('manacore_original_title', 'manacore_alt_titles')
				AND mcm.meta_value LIKE %s
			) ",
			$like
		);

		return $this->search_with_meta( $search, $meta_sql );
	}

	/**
	 * افزودن «نام اصلی/نام‌های دیگر» به‌عنوان گزینه‌ی جایگزینِ عبارت جستجو.
	 *
	 * شکلِ واقعیِ رشته‌ای که وردپرس به این فیلتر می‌دهد (بررسی‌شده روی
	 * `wp-includes/class-wp-query.php::parse_search()` در همین نصب) چنین است:
	 *
	 *   " AND ( (((عنوان LIKE …) OR (خلاصه LIKE …) OR (متن LIKE …))) )  AND (post_password = '') "
	 *
	 * یعنی سه نکته: (۱) رشته با `AND` شروع می‌شود، (۲) خودِ عبارت در یک
	 * پرانتز بسته شده، و (۳) سدِّ رمز `post_password` **بیرون** و بعد از
	 * گروهِ عبارت چسبیده است. پس هر پیاده‌سازی که شرط متا را «به آخر
	 * رشته» یا «به آخرین پرانتز» اضافه کند، آن را به سدِّ رمز می‌چسباند و
	 * شرط بی‌اثر می‌شود — همان چیزی که پیش‌تر روی همین نصب رخ داده بود.
	 *
	 * اینجا هر سه نکته رعایت می‌شود: سدِّ رمز جدا و دست‌نخورده پس
	 * برگردانده می‌شود، و شرط متا کنارِ خودِ عبارت (نه کنارِ سدِّ رمز)
	 * می‌نشیند:
	 *
	 *   " AND ( (((عنوان LIKE …) OR …) OR EXISTS (متا LIKE …)) )  AND (post_password = '') "
	 *
	 * @param string $search   خروجی وردپرس برای `posts_search`.
	 * @param string $meta_sql شرط آماده‌ی متا (با «OR» ابتدایی).
	 * @return string
	 */
	protected function search_with_meta( $search, $meta_sql ) {
		$search = trim( (string) $search );
		$meta   = trim( (string) $meta_sql );

		if ( '' === $search || '' === $meta ) {
			return $search;
		}

		global $wpdb;

		// (۳) جدا کردن سدِّ رمز و بازگرداندن دست‌نخورده‌ی آن در پایان.
		$password = '';
		$preg     = '/\sAND\s+\(\s*' . preg_quote( $wpdb->posts, '/' ) . '\.post_password\s*=\s*\'\'\s*\)\s*$/';

		if ( preg_match( $preg, $search, $match ) ) {
			$password = ' ' . trim( $match[0] ) . ' ';
			$search   = rtrim( substr( $search, 0, -strlen( $match[0] ) ) );
		}

		// (۱) حذف `AND` سرصفحه تا دوباره اضافه شود.
		$search = trim( preg_replace( '/^AND\s+/', '', $search, 1 ) );

		if ( '' === $search ) {
			return $search;
		}

		// (۲) گروهِ عبارت، به‌تنهایی، داخل یک پرانتز تازه می‌نشیند.
		$wrapped = ' AND ( ' . $search . ' ' . $meta . ' ) ';

		return $wrapped . $password;
	}

	/**
	 * دریافت آثار با شرایط دلخواه (کاربردی برای بلوک‌ها).
	 *
	 * @param array $args پارامترها.
	 * @return \WP_Query
	 */
	public static function get_titles( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'post_type'           => manacore_title_post_types(),
				'posts_per_page'      => 12,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$source = isset( $args['manacore_source'] ) ? $args['manacore_source'] : '';
		unset( $args['manacore_source'] );

		/*
		 * شرط‌های متای افزوده‌شده از سوی فراخوان (مثلاً بلوک‌ها) که باید با شرط‌های
		 * منبع ادغام شوند، نه جایگزین آن‌ها.
		 */
		$extra_meta = array();
		if ( isset( $args['manacore_extra_meta_query'] ) ) {
			$extra_meta = (array) $args['manacore_extra_meta_query'];
			unset( $args['manacore_extra_meta_query'] );
		}

		/*
		 * وقتی فراخوان مرتب‌سازی صریح تعیین کرده است، منبع نباید آن را بازنویسی کند.
		 */
		$force_order  = ! empty( $args['manacore_force_order'] );
		$caller_order = array();
		if ( $force_order ) {
			foreach ( array( 'orderby', 'order', 'meta_key' ) as $key ) {
				if ( isset( $args[ $key ] ) ) {
					$caller_order[ $key ] = $args[ $key ];
				}
			}
		}
		unset( $args['manacore_force_order'] );

		switch ( $source ) {
			case 'featured':
				$args['meta_query'] = self::merge_meta_query(
					isset( $args['meta_query'] ) ? $args['meta_query'] : array(),
					array(
						array(
							'key'   => 'manacore_is_featured',
							'value' => '1',
						),
					)
				);
				break;
			case 'top_rated':
				$args['meta_key'] = 'manacore_imdb_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'most_viewed':
				$args['meta_key'] = 'manacore_views';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'trending':
				$ids = Ratings::instance()->trending( 7, (int) $args['posts_per_page'] );
				if ( $ids ) {
					$args['post__in'] = $ids;
					$args['orderby']  = 'post__in';
				}
				break;
			case 'random':
				$args['orderby'] = 'rand';
				break;
			case 'related':
				$args = self::related_args( $args );
				break;
			case 'person_works':
				$args = self::person_works_args( $args );
				break;
			case 'collection':
				$args = self::collection_args( $args );
				break;
			case 'watchlist':
				/*
				 * «لیست تماشای من» — شناسه‌ها از متای کاربر می‌آید نه از یک
				 * کوئری عمومی؛ پس ترتیب هم همان ترتیب افزودن کاربر است.
				 * فهرست خالی با `post__in => array(0)` به «هیچ» می‌رسد تا
				 * بلوک، حالت خالی مرجع را رندر کند (نه آخرین آثار سایت).
				 */
				$ids              = Account::watchlist();
				$args['post__in'] = $ids ? $ids : array( 0 );
				$args['orderby']  = 'post__in';
				break;
			case 'recommended':
				/*
				 * «برای تو» — پیشنهاد بر پایه‌ی ژانرهای لیست تماشا و تاریخچه.
				 * اگر کاربر هیچ سلیقه‌ای ثبت نکرده باشد، فهرست خالی برمی‌گردد
				 * و بلوک به کوئری پیش‌فرض برمی‌گردد (همان رفتار مرجع که
				 * «آثار دیده‌نشده» را پیشنهاد می‌کند).
				 */
				$ids = Account::recommended_ids( (int) $args['posts_per_page'] );
				if ( $ids ) {
					$args['post__in'] = $ids;
					$args['orderby']  = 'post__in';
				} else {
					$seen = array_merge( Account::watchlist(), Account::watched() );
					if ( $seen ) {
						$args['post__not_in'] = $seen;
					}
				}
				break;
		}

		// ادغام شرط‌های متای فراخوان با شرط‌های منبع.
		if ( $extra_meta ) {
			$args['meta_query'] = self::merge_meta_query(
				isset( $args['meta_query'] ) ? $args['meta_query'] : array(),
				$extra_meta
			);
		}

		// بازگرداندن مرتب‌سازی صریح فراخوان.
		if ( $force_order && $caller_order ) {
			// در حالت post__in نباید ترتیب دستی از بین برود مگر فراخوان صریحاً خواسته باشد.
			$args = array_merge( $args, $caller_order );
			if ( isset( $caller_order['orderby'] ) && 'rand' === $caller_order['orderby'] ) {
				unset( $args['meta_key'] );
			}
		}

		/**
		 * فیلتر پارامترهای نهایی کوئری آثار.
		 *
		 * برای افزودن منبع‌های سفارشی (source) در قالب یا افزونه‌های دیگر.
		 *
		 * @param array  $args   پارامترها.
		 * @param string $source نام منبع.
		 */
		$args = apply_filters( 'manacore_get_titles_args', $args, $source );

		return new \WP_Query( $args );
	}

	/**
	 * ادغام دو مجموعه شرط متا با حفظ ساختار relation.
	 *
	 * @param mixed $base  شرط‌های موجود.
	 * @param mixed $extra شرط‌های افزوده.
	 * @return array
	 */
	protected static function merge_meta_query( $base, $extra ) {
		$base  = self::normalize_meta_clauses( $base );
		$extra = self::normalize_meta_clauses( $extra );

		$merged = array_merge( $base, $extra );

		if ( empty( $merged ) ) {
			return array();
		}

		if ( count( $merged ) > 1 ) {
			$merged['relation'] = 'AND';
		}

		return $merged;
	}

	/**
	 * استخراج شرط‌های عددی یک meta_query (حذف کلید relation).
	 *
	 * @param mixed $query شرط‌ها.
	 * @return array
	 */
	protected static function normalize_meta_clauses( $query ) {
		if ( empty( $query ) || ! is_array( $query ) ) {
			return array();
		}

		$out = array();
		foreach ( $query as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}
			if ( is_array( $clause ) ) {
				$out[] = $clause;
			}
		}

		// اگر آرایه‌ی ورودی خودش یک شرط تنها بود.
		if ( empty( $out ) && ( isset( $query['key'] ) || isset( $query['compare'] ) ) ) {
			$out[] = $query;
		}

		return $out;
	}

	/**
	 * شناسه‌ی آثاری که یک چهره در آن‌ها آمده است.
	 *
	 * فیلد بازیگران (`manacore_cast`) یک ریپیتر JSON است، پس جست‌وجوی متنی
	 * روی نام چهره انجام می‌شود؛ نتیجه در ترنزینت کش می‌شود تا هم حلقه‌ی
	 * «آثار این عامل» و هم شمار «N اثر» کارت‌های چهره از **یک** محاسبه
	 * بخورند (بدون SQL تکراری).
	 *
	 * @param int $person_id شناسه‌ی چهره.
	 * @return array<int,int> شناسه‌ی آثار.
	 */
	public static function person_work_ids( $person_id ) {
		$person_id = absint( $person_id );

		if ( ! $person_id || 'person' !== get_post_type( $person_id ) ) {
			return array();
		}

		$cached = get_transient( 'manacore_person_works_' . $person_id );

		if ( ! is_array( $cached ) ) {
			global $wpdb;

			$name = trim( (string) get_the_title( $person_id ) );

			/*
			 * پیوند دقیق با شناسه (person_id در JSON عوامل و بازیگران) و پیوند
			 * قدیمی با نام (متن ساده‌ی ایمپورتر و داده‌های بی‌شناسه). نام خالی
			 * نباید به LIKE '%%' تبدیل شود که همه‌ی آثار را برمی‌گرداند.
			 */
			$id_like = '%"person_id":' . $person_id . '}%';
			$like    = '' !== $name ? '%' . $wpdb->esc_like( $name ) . '%' : '';
			$by_name = '' !== $like ? $like : $id_like;

			$found = array_map(
				'absint',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
						 WHERE meta_key IN ('manacore_cast','manacore_director','manacore_writer','manacore_producer','manacore_composer')
						 AND (meta_value LIKE %s OR meta_value LIKE %s)
						 LIMIT 200",
						$by_name,
						$id_like
					)
				)
			);

			/*
			 * فقط آثار **منتشرشده‌ی** انواع عنوان (و قسمت) می‌مانند تا شمار
			 * «N اثر» با شبکه‌ی فیلموگرافی، که همان `publish` را کوئری
			 * می‌کند، دقیقاً یک مجموعه باشد؛ وگرنه پیش‌نویس/زباله‌دان شمار را
			 * بیشتر نشان می‌داد.
			 */
			$cached = $found
				? array_map(
					'absint',
					(array) get_posts(
						array(
							'post_type'      => array_merge( manacore_title_post_types(), array( 'episode' ) ),
							'post_status'    => 'publish',
							'post__in'       => $found,
							'posts_per_page' => 200,
							'orderby'        => 'post__in',
							'fields'         => 'ids',
							'no_found_rows'  => true,
						)
					)
				)
				: array();

			set_transient( 'manacore_person_works_' . $person_id, $cached, HOUR_IN_SECONDS );
		}

		return array_values( array_diff( array_map( 'absint', (array) $cached ), array( $person_id ) ) );
	}

	/**
	 * شمار واقعی آثار یک چهره («N اثر در کوهه»).
	 *
	 * @param int $person_id شناسه‌ی چهره.
	 * @return int
	 */
	public static function person_work_count( $person_id ) {
		return count( self::person_work_ids( $person_id ) );
	}

	/**
	 * منبع «آثار این عامل»: در صفحه‌ی عامل (person)، آثاری که این شخص در
	 * فیلد بازیگران (`manacore_cast`) یا کارگردان/نویسنده‌ی متنی‌شان آمده
	 * باشد.
	 *
	 * @param array $args آرگومان‌های کوئری.
	 * @return array
	 */
	private static function person_works_args( $args ) {
		$post_id = get_the_ID();

		if ( ! $post_id || 'person' !== get_post_type( $post_id ) ) {
			return $args;
		}

		$ids = self::person_work_ids( $post_id );

		if ( empty( $ids ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		$args['post__in'] = $ids;
		$args['orderby']  = 'post__in';

		/* اگر فراخوان نوع محتوا را تعیین نکرده باشد، همه‌ی انواع عنوان. */
		if ( empty( $args['post_type'] ) ) {
			$args['post_type'] = array_merge( manacore_title_post_types(), array( 'episode' ) );
		}

		return $args;
	}

	/**
	 * پارامترهای «آثار مرتبط» بر پایه‌ی اثر جاری.
	 *
	 * معیار ارتباط به ترتیب اولویت:
	 *   ۱. اشتراک در ژانر (اصلی‌ترین معیار)
	 *   ۲. در نبود ژانر: هم‌کشور یا هم‌سال
	 *   ۳. در نبود هر دو: تازه‌ترین آثار همان نوع محتوا
	 *
	 * @param array $args پارامترهای پایه.
	 * @return array
	 */
	private static function related_args( $args ) {
		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return $args;
		}

		/* برای قسمت‌ها، اثر والد مبنای ارتباط است. */
		if ( 'episode' === get_post_type( $post_id ) ) {
			$parent = (int) get_post_meta( $post_id, 'manacore_parent_title', true );
			if ( $parent ) {
				$post_id = $parent;
			}
		}

		$excluded             = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
		$excluded[]           = $post_id;
		$args['post__not_in'] = array_values( array_unique( array_map( 'absint', array_filter( $excluded ) ) ) );

		/* محدود کردن به همان نوع محتوا در صورت نبود تعیین صریح. */
		$post_type = get_post_type( $post_id );
		if ( $post_type && in_array( $post_type, manacore_title_post_types(), true ) && empty( $args['post_type'] ) ) {
			$args['post_type'] = array( $post_type );
		}

		if ( ! empty( $args['tax_query'] ) ) {
			return $args;
		}

		$genres = wp_get_object_terms( $post_id, 'genre', array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'genre',
					'field'    => 'term_id',
					'terms'    => $genres,
				),
			);
			$args['orderby']   = 'rand';

			return $args;
		}

		/* جایگزین: هم‌کشور یا هم‌سال. */
		$year    = (int) get_post_meta( $post_id, 'manacore_year', true );
		$country = wp_get_object_terms( $post_id, 'country', array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $country ) && ! empty( $country ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'country',
					'field'    => 'term_id',
					'terms'    => $country,
				),
			);
		} elseif ( $year ) {
			$args['meta_query'] = self::merge_meta_query(
				isset( $args['meta_query'] ) ? $args['meta_query'] : array(),
				array(
					array(
						'key'   => 'manacore_year',
						'value' => $year,
					),
				)
			);
		}

		$args['orderby'] = 'rand';

		return $args;
	}

	/**
	 * پارامترهای «آثار یک مجموعه».
	 *
	 * آثاری که تکسونومی/متای مجموعه‌ی آن‌ها به مجموعه‌ی جاری اشاره دارد.
	 * اگر شناسه‌ی مجموعه در پارامترها نباشد، از پست جاری استفاده می‌شود.
	 *
	 * @param array $args پارامترهای پایه.
	 * @return array
	 */
	private static function collection_args( $args ) {
		$collection_id = isset( $args['manacore_collection'] ) ? (int) $args['manacore_collection'] : 0;
		unset( $args['manacore_collection'] );

		if ( ! $collection_id ) {
			$current = get_the_ID();
			if ( $current && 'collection' === get_post_type( $current ) ) {
				$collection_id = (int) $current;
			}
		}

		if ( ! $collection_id ) {
			return $args;
		}

		$args['meta_query'] = self::merge_meta_query(
			isset( $args['meta_query'] ) ? $args['meta_query'] : array(),
			array(
				array(
					'key'   => 'manacore_collection',
					'value' => $collection_id,
				),
			)
		);

		$ordered = Collection::items( $collection_id );

		/* فهرست دستی مجموعه، ترتیب و اعضا را تعیین می‌کند. */
		if ( $ordered ) {
			// شرط متای مجموعه حذف می‌شود، اما شرط‌های افزوده‌ی فراخوان حفظ می‌شوند.
			$args['meta_query'] = self::merge_meta_query(
				array(),
				array_values(
					array_filter(
						self::normalize_meta_clauses( $args['meta_query'] ),
						static function ( $clause ) {
							return ! isset( $clause['key'] ) || 'manacore_collection' !== $clause['key'];
						}
					)
				)
			);
			if ( empty( $args['meta_query'] ) ) {
				unset( $args['meta_query'] );
			}

			if ( ! empty( $args['post__in'] ) ) {
				$ordered = array_values( array_intersect( $ordered, array_map( 'absint', (array) $args['post__in'] ) ) );
			}
			if ( ! empty( $args['post__not_in'] ) ) {
				$ordered = array_values( array_diff( $ordered, array_map( 'absint', (array) $args['post__not_in'] ) ) );
			}
			if ( empty( $ordered ) ) {
				$ordered = array( 0 );
			}

			$args['post__in'] = $ordered;
			if ( empty( $args['orderby'] ) || 'post__in' === $args['orderby'] ) {
				$args['orderby'] = 'post__in';
			}
		} else {
			/* بدون فهرست دستی، ترتیب خودکار تنظیم‌شده‌ی مجموعه اعمال می‌شود. */
			$sort = Collection::sort( $collection_id );
			if ( 'manual' !== $sort ) {
				$args = array_merge( $args, Collection::sort_args( $sort ) );
			}
		}

		return $args;
	}
}

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

		$orderby = isset( $_GET['mc_sort'] ) ? sanitize_key( wp_unslash( $_GET['mc_sort'] ) ) : '';
		// phpcs:enable

		switch ( $orderby ) {
			case 'rating':
				$this->order_by_meta_num( $query, 'manacore_imdb_rating' );
				break;
			case 'views':
				$this->order_by_meta_num( $query, 'manacore_views' );
				break;
			case 'year':
				$this->order_by_meta_num( $query, 'manacore_year' );
				break;
			case 'title':
				$query->set( 'orderby', 'title' );
				$query->set( 'order', 'ASC' );
				break;
			case 'oldest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'ASC' );
				break;
			case 'newest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
				break;
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

		$meta_query['manacore_sort'] = array(
			'key'     => $key,
			'compare' => 'EXISTS',
			'type'    => 'NUMERIC',
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

		if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() || empty( $search ) ) {
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

		// افزودن شرط متا داخل پرانتز جستجوی موجود.
		return preg_replace( '/\)\s*$/', $meta_sql . ')', $search, 1 );
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
	/**
	 * منبع «آثار این عامل»: در صفحه‌ی عامل (person)، آثاری که این شخص در
	 * فیلد بازیگران (manacore_cast) یا کارگردان/نویسنده‌ی متنی‌شان آمده
	 * باشد. روی نصب‌های بزرگ سنگین است، پس در ترنزینت cache می‌شود.
	 *
	 * @param array $args آرگومان‌های کوئری.
	 * @return array
	 */
	private static function person_works_args( $args ) {
		$post_id = get_the_ID();

		if ( ! $post_id || 'person' !== get_post_type( $post_id ) ) {
			return $args;
		}

		$cached = get_transient( 'manacore_person_works_' . $post_id );

		if ( ! is_array( $cached ) ) {
			global $wpdb;

			$name = get_the_title( $post_id );
			$like = '%' . $wpdb->esc_like( $name ) . '%';

			/*
			 * manacore_cast یک فیلد JSON است (repeater)؛ جستجوی متنی روی
			 * نام + بازیگر + کارگردان/نویسنده‌ی متنی، هر دو مسیر را پوشش می‌دهد.
			 */
			$ids = (array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
					 WHERE meta_key IN ('manacore_cast','manacore_director','manacore_writer')
					 AND meta_value LIKE %s
					 LIMIT 200",
					$like
				)
			);

			$ids = array_map( 'absint', $ids );
			set_transient( 'manacore_person_works_' . $post_id, $ids, HOUR_IN_SECONDS );
		}

		$ids = array_values( array_diff( array_map( 'absint', (array) $cached ), array( $post_id ) ) );

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

		$ordered = get_post_meta( $collection_id, 'manacore_collection_items', true );

		if ( is_array( $ordered ) ) {
			$ordered = array_values( array_filter( array_map( 'absint', $ordered ) ) );
		} elseif ( is_string( $ordered ) && '' !== $ordered ) {
			$ordered = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $ordered ) ) ) );
		} else {
			$ordered = array();
		}

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
		}

		return $args;
	}
}

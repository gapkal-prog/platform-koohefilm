<?php
/**
 * ساخت آرگومان‌های کوئری از ویژگی‌های بلوک حلقه.
 *
 * این کلاس ویژگی‌های ویرایشگر (تاکسونومی‌ها، بازه‌ی سال، امتیاز، مرتب‌سازی و…)
 * را به آرگومان‌های استاندارد WP_Query تبدیل می‌کند تا رندرها ساده بمانند.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Query
 */
class Block_Query {

	/**
	 * ویژگی‌های مشترک کوئری برای بلوک‌های حلقه.
	 *
	 * @return array
	 */
	public static function attributes() {
		return array(
			'source'         => array(
				'type'    => 'string',
				'default' => 'latest',
			),
			// سازگاری با نسخه‌ی قبلی: تک نوع محتوا.
			'postType'       => array(
				'type'    => 'string',
				'default' => '',
			),
			// چند نوع محتوا (اولویت بر postType).
			'postTypes'      => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'string' ),
			),
			// سازگاری با نسخه‌ی قبلی: تک ژانر.
			'genre'          => array(
				'type'    => 'string',
				'default' => '',
			),
			/*
			 * قواعد تاکسونومی:
			 * array( array( 'taxonomy' => 'genre', 'terms' => array('action'), 'operator' => 'IN' ), ... )
			 */
			'taxQuery'       => array(
				'type'    => 'array',
				'default' => array(),
			),
			'taxRelation'    => array(
				'type'    => 'string',
				'default' => 'AND',
			),
			'authors'        => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'number' ),
			),
			'includeIds'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'excludeIds'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'excludeCurrent' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'search'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'yearFrom'       => array(
				'type'    => 'number',
				'default' => 0,
			),
			'yearTo'         => array(
				'type'    => 'number',
				'default' => 0,
			),
			'minRating'      => array(
				'type'    => 'number',
				'default' => 0,
			),
			'premiumFilter'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'onlyWithLinks'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'onlyWithPoster' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'orderBy'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'order'          => array(
				'type'    => 'string',
				'default' => 'DESC',
			),
			'metaOrderKey'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'offset'         => array(
				'type'    => 'number',
				'default' => 0,
			),
			'count'          => array(
				'type'    => 'number',
				'default' => 12,
			),
			'stickyMode'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'inheritQuery'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * مقادیر پیش‌فرض ویژگی‌های کوئری.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::attributes() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}
		return $defaults;
	}

	/**
	 * گزینه‌های مرتب‌سازی برای ویرایشگر.
	 *
	 * @return array
	 */
	public static function order_options() {
		return array(
			''               => __( 'پیش‌فرض منبع', 'manacore' ),
			'date'           => __( 'تاریخ انتشار', 'manacore' ),
			'modified'       => __( 'تاریخ ویرایش', 'manacore' ),
			'title'          => __( 'عنوان (الفبا)', 'manacore' ),
			'rand'           => __( 'تصادفی', 'manacore' ),
			'comment_count'  => __( 'تعداد دیدگاه', 'manacore' ),
			'menu_order'     => __( 'ترتیب دستی (menu_order)', 'manacore' ),
			'rating'         => __( 'امتیاز IMDb', 'manacore' ),
			'tmdb_rating'    => __( 'امتیاز TMDB', 'manacore' ),
			'views'          => __( 'بازدید', 'manacore' ),
			'year'           => __( 'سال انتشار', 'manacore' ),
			'runtime'        => __( 'مدت زمان', 'manacore' ),
			'episode_number' => __( 'شماره‌ی قسمت', 'manacore' ),
			'meta_num'       => __( 'کلید متای دلخواه (عددی)', 'manacore' ),
			'meta_text'      => __( 'کلید متای دلخواه (متنی)', 'manacore' ),
		);
	}

	/**
	 * نگاشت مرتب‌سازی به کلید متا.
	 *
	 * @return array
	 */
	protected static function meta_order_map() {
		return array(
			'rating'         => 'manacore_imdb_rating',
			'tmdb_rating'    => 'manacore_tmdb_rating',
			'views'          => 'manacore_views',
			'year'           => 'manacore_year',
			'runtime'        => 'manacore_runtime',
			'episode_number' => 'manacore_episode_number',
		);
	}

	/**
	 * گزینه‌های فیلتر اشتراکی.
	 *
	 * @return array
	 */
	public static function premium_options() {
		return array(
			''     => __( 'بدون فیلتر', 'manacore' ),
			'only' => __( 'فقط محتوای اشتراکی', 'manacore' ),
			'free' => __( 'فقط محتوای آزاد', 'manacore' ),
		);
	}

	/**
	 * گزینه‌های نوشته‌ی چسبان.
	 *
	 * @return array
	 */
	public static function sticky_options() {
		return array(
			''        => __( 'نادیده گرفتن', 'manacore' ),
			'include' => __( 'اولویت به چسبان‌ها', 'manacore' ),
			'only'    => __( 'فقط چسبان‌ها', 'manacore' ),
			'exclude' => __( 'حذف چسبان‌ها', 'manacore' ),
		);
	}

	/**
	 * تبدیل ویژگی‌های بلوک به آرگومان‌های WP_Query.
	 *
	 * @param array $attrs  ویژگی‌های بلوک (پیش از نرمال‌سازی هم پذیرفته می‌شود).
	 * @param array $limits محدودیت‌ها: array( 'max' => 48 ).
	 * @return array
	 */
	public static function build( $attrs, $limits = array() ) {
		$attrs  = wp_parse_args( is_array( $attrs ) ? $attrs : array(), self::defaults() );
		$limits = wp_parse_args(
			$limits,
			array(
				'max' => 48,
				'min' => 1,
			)
		);

		$per_page = max( (int) $limits['min'], min( (int) $limits['max'], (int) $attrs['count'] ) );

		$args = array(
			'posts_per_page'  => $per_page,
			'manacore_source' => sanitize_key( $attrs['source'] ),
		);

		// --- نوع محتوا -------------------------------------------------
		$post_types = array_filter( array_map( 'sanitize_key', (array) $attrs['postTypes'] ) );
		if ( empty( $post_types ) && $attrs['postType'] ) {
			$post_types = array( sanitize_key( $attrs['postType'] ) );
		}
		$post_types = array_values( array_filter( $post_types, 'post_type_exists' ) );
		if ( $post_types ) {
			$args['post_type'] = $post_types;
		}

		// --- تاکسونومی‌ها ----------------------------------------------
		$tax_query = self::build_tax_query( $attrs );
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// --- متا (سال، امتیاز، اشتراک، لینک، پوستر) ---------------------
		$meta_query = self::build_meta_query( $attrs );
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// --- نویسنده ---------------------------------------------------
		$authors = array_filter( array_map( 'absint', (array) $attrs['authors'] ) );
		if ( $authors ) {
			$args['author__in'] = array_values( $authors );
		}

		// --- شناسه‌ها ---------------------------------------------------
		$include = self::parse_ids( $attrs['includeIds'] );
		if ( $include ) {
			$args['post__in'] = $include;
			if ( ! $attrs['orderBy'] ) {
				$args['orderby'] = 'post__in';
			}
		}

		$exclude = self::parse_ids( $attrs['excludeIds'] );
		if ( ! empty( $attrs['excludeCurrent'] ) ) {
			$current = Block_Visibility::current_post_id();
			if ( $current ) {
				$exclude[] = $current;
			}
		}
		if ( $exclude ) {
			$args['post__not_in'] = array_values( array_unique( $exclude ) );
		}

		// --- جستجو -----------------------------------------------------
		if ( '' !== trim( (string) $attrs['search'] ) ) {
			$args['s'] = sanitize_text_field( $attrs['search'] );
		}

		// --- مرتب‌سازی --------------------------------------------------
		$args = self::apply_order( $args, $attrs );

		// --- افست ------------------------------------------------------
		$offset = max( 0, (int) $attrs['offset'] );
		if ( $offset ) {
			$args['offset'] = $offset;
		}

		// --- نوشته‌های چسبان --------------------------------------------
		$args = self::apply_sticky( $args, $attrs );

		// --- ارث‌بری از کوئری اصلی (برای آرشیوها) ------------------------
		if ( ! empty( $attrs['inheritQuery'] ) ) {
			$args = self::inherit( $args );
		}

		/**
		 * فیلتر آرگومان‌های ساخته‌شده از ویژگی‌های بلوک.
		 *
		 * @param array $args  آرگومان‌ها.
		 * @param array $attrs ویژگی‌ها.
		 */
		return (array) apply_filters( 'manacore_block_query_args', $args, $attrs );
	}

	/**
	 * ساخت tax_query.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return array
	 */
	protected static function build_tax_query( $attrs ) {
		$clauses = array();

		// سازگاری با ویژگی قدیمی genre.
		if ( ! empty( $attrs['genre'] ) ) {
			$clauses[] = array(
				'taxonomy' => 'genre',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', array_filter( array_map( 'trim', explode( ',', (string) $attrs['genre'] ) ) ) ),
				'operator' => 'IN',
			);
		}

		foreach ( (array) $attrs['taxQuery'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['taxonomy'] ) ) {
				continue;
			}
			$taxonomy = sanitize_key( $rule['taxonomy'] );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = array_values( array_filter( array_map( 'strval', (array) ( isset( $rule['terms'] ) ? $rule['terms'] : array() ) ), 'strlen' ) );
			if ( empty( $terms ) ) {
				continue;
			}

			$operator = isset( $rule['operator'] ) ? strtoupper( (string) $rule['operator'] ) : 'IN';
			if ( ! in_array( $operator, array( 'IN', 'NOT IN', 'AND' ), true ) ) {
				$operator = 'IN';
			}

			// اگر ترم‌ها عددی باشند با شناسه فیلتر می‌کنیم، وگرنه با اسلاگ.
			$numeric = true;
			foreach ( $terms as $term ) {
				if ( ! ctype_digit( (string) $term ) ) {
					$numeric = false;
					break;
				}
			}

			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => $numeric ? 'term_id' : 'slug',
				'terms'    => $numeric ? array_map( 'absint', $terms ) : array_map( 'sanitize_title', $terms ),
				'operator' => $operator,
			);
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		if ( count( $clauses ) > 1 ) {
			$relation = 'OR' === strtoupper( (string) $attrs['taxRelation'] ) ? 'OR' : 'AND';
			$clauses  = array_merge( array( 'relation' => $relation ), $clauses );
		}

		return $clauses;
	}

	/**
	 * ساخت meta_query.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return array
	 */
	protected static function build_meta_query( $attrs ) {
		$clauses = array();

		$year_from = max( 0, (int) $attrs['yearFrom'] );
		$year_to   = max( 0, (int) $attrs['yearTo'] );

		if ( $year_from && $year_to ) {
			$clauses[] = array(
				'key'     => 'manacore_year',
				'value'   => array( min( $year_from, $year_to ), max( $year_from, $year_to ) ),
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			);
		} elseif ( $year_from ) {
			$clauses[] = array(
				'key'     => 'manacore_year',
				'value'   => $year_from,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		} elseif ( $year_to ) {
			$clauses[] = array(
				'key'     => 'manacore_year',
				'value'   => $year_to,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		$min_rating = (float) $attrs['minRating'];
		if ( $min_rating > 0 ) {
			$clauses[] = array(
				'key'     => 'manacore_imdb_rating',
				'value'   => $min_rating,
				'type'    => 'DECIMAL(4,1)',
				'compare' => '>=',
			);
		}

		$premium = sanitize_key( (string) $attrs['premiumFilter'] );
		if ( 'only' === $premium ) {
			$clauses[] = array(
				'key'     => 'manacore_is_premium',
				'value'   => '1',
				'compare' => '=',
			);
		} elseif ( 'free' === $premium ) {
			$clauses[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'manacore_is_premium',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'manacore_is_premium',
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		if ( ! empty( $attrs['onlyWithLinks'] ) ) {
			$clauses[] = array(
				'key'     => 'manacore_links',
				'compare' => 'EXISTS',
			);
		}

		if ( ! empty( $attrs['onlyWithPoster'] ) ) {
			$clauses[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'manacore_poster_url',
					'compare' => 'EXISTS',
				),
			);
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		if ( count( $clauses ) > 1 ) {
			$clauses = array_merge( array( 'relation' => 'AND' ), $clauses );
		}

		return $clauses;
	}

	/**
	 * اعمال مرتب‌سازی.
	 *
	 * @param array $args  آرگومان‌ها.
	 * @param array $attrs ویژگی‌ها.
	 * @return array
	 */
	protected static function apply_order( $args, $attrs ) {
		$order_by = sanitize_key( (string) $attrs['orderBy'] );
		if ( ! $order_by ) {
			return $args;
		}

		$order          = 'ASC' === strtoupper( (string) $attrs['order'] ) ? 'ASC' : 'DESC';
		$args['order']  = $order;
		$meta_order_map = self::meta_order_map();

		if ( isset( $meta_order_map[ $order_by ] ) ) {
			$args['meta_key'] = $meta_order_map[ $order_by ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_value_num';
			return $args;
		}

		if ( 'meta_num' === $order_by || 'meta_text' === $order_by ) {
			$key = sanitize_key( (string) $attrs['metaOrderKey'] );
			if ( ! $key ) {
				unset( $args['order'] );
				return $args;
			}
			$args['meta_key'] = $key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['orderby']  = 'meta_num' === $order_by ? 'meta_value_num' : 'meta_value';
			return $args;
		}

		$allowed = array( 'date', 'modified', 'title', 'rand', 'comment_count', 'menu_order', 'ID', 'author', 'name' );
		if ( in_array( $order_by, $allowed, true ) ) {
			$args['orderby'] = $order_by;
			if ( 'rand' === $order_by ) {
				unset( $args['order'] );
			}
			return $args;
		}

		unset( $args['order'] );
		return $args;
	}

	/**
	 * اعمال تنظیم نوشته‌های چسبان.
	 *
	 * @param array $args  آرگومان‌ها.
	 * @param array $attrs ویژگی‌ها.
	 * @return array
	 */
	protected static function apply_sticky( $args, $attrs ) {
		$mode = sanitize_key( (string) $attrs['stickyMode'] );
		if ( ! $mode ) {
			return $args;
		}

		$sticky = array_map( 'absint', (array) get_option( 'sticky_posts', array() ) );

		switch ( $mode ) {
			case 'include':
				$args['ignore_sticky_posts'] = false;
				break;
			case 'only':
				if ( empty( $sticky ) ) {
					// هیچ نوشته‌ی چسبانی وجود ندارد؛ نتیجه باید خالی بماند.
					$args['post__in'] = array( 0 );
					break;
				}
				$args['post__in'] = isset( $args['post__in'] )
					? array_values( array_intersect( $args['post__in'], $sticky ) )
					: $sticky;
				if ( empty( $args['post__in'] ) ) {
					$args['post__in'] = array( 0 );
				}
				break;
			case 'exclude':
				if ( $sticky ) {
					$args['post__not_in'] = isset( $args['post__not_in'] )
						? array_values( array_unique( array_merge( $args['post__not_in'], $sticky ) ) )
						: $sticky;
				}
				break;
		}

		return $args;
	}

	/**
	 * ارث‌بری فیلترهای کوئری اصلی (نوع محتوا، ترم آرشیو، جستجو، صفحه‌بندی).
	 *
	 * @param array $args آرگومان‌ها.
	 * @return array
	 */
	protected static function inherit( $args ) {
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $args;
		}

		if ( is_search() ) {
			$args['s'] = get_search_query();
		}

		if ( is_post_type_archive() ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Post_Type ) {
				$args['post_type'] = array( $queried->name );
			}
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$clause = array(
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => array( (int) $term->term_id ),
				);
				if ( isset( $args['tax_query'] ) ) {
					$existing = $args['tax_query'];
					if ( ! isset( $existing['relation'] ) ) {
						$existing = array_merge( array( 'relation' => 'AND' ), $existing );
					}
					$existing[]         = $clause;
					$args['tax_query']  = $existing; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				} else {
					$args['tax_query'] = array( $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				}
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				$args['author__in'] = array( (int) $author->ID );
			}
		}

		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged > 1 ) {
			$args['paged']         = $paged;
			$args['no_found_rows'] = false;
		}

		return $args;
	}

	/**
	 * تبدیل رشته‌ی شناسه‌ها به آرایه.
	 *
	 * @param string $raw رشته‌ی «۱۲, ۱۳ ۱۴».
	 * @return array
	 */
	public static function parse_ids( $raw ) {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}
		$parts = preg_split( '/[\s,،]+/', (string) $raw );
		if ( ! $parts ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
	}
}

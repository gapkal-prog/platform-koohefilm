<?php
/**
 * قواعد نمایش شرطی بلوک‌ها (Conditional Visibility).
 *
 * هر بلوک ManaCore ویژگی‌ای به نام `visibility` دارد که ساختار آن چنین است:
 *
 *     array(
 *         'enabled'  => true,
 *         'action'   => 'show'|'hide',   // نتیجه‌ی تطبیق قواعد
 *         'relation' => 'AND'|'OR',
 *         'rules'    => array(
 *             array( 'type' => 'post_type', 'operator' => 'is', 'values' => array( 'movie' ) ),
 *             array( 'type' => 'taxonomy', 'taxonomy' => 'genre', 'operator' => 'is', 'values' => array( 'action' ) ),
 *             ...
 *         ),
 *     )
 *
 * ارزیابی همیشه سمت سرور انجام می‌شود تا با کش صفحه و امنیت سازگار باشد.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Visibility
 */
class Block_Visibility {

	/**
	 * تعریف ویژگی مشترک برای ثبت بلوک.
	 *
	 * @return array
	 */
	public static function attribute() {
		return array(
			'visibility' => array(
				'type'    => 'object',
				'default' => array(
					'enabled'  => false,
					'action'   => 'show',
					'relation' => 'AND',
					'rules'    => array(),
					'devices'  => array(),
				),
			),
		);
	}

	/**
	 * فهرست نوع قواعد برای ویرایشگر.
	 *
	 * @return array
	 */
	public static function rule_types() {
		$types = array(
			'context'      => __( 'زمینه‌ی صفحه', 'manacore' ),
			'post_type'    => __( 'نوع محتوا', 'manacore' ),
			'taxonomy'     => __( 'ترم تاکسونومی (اثر جاری)', 'manacore' ),
			'archive_term' => __( 'آرشیو ترم جاری', 'manacore' ),
			'post_ids'     => __( 'شناسه‌ی نوشته‌های مشخص', 'manacore' ),
			'login'        => __( 'وضعیت ورود', 'manacore' ),
			'user_role'    => __( 'نقش کاربر', 'manacore' ),
			'subscription' => __( 'سطح اشتراک', 'manacore' ),
			'premium'      => __( 'محتوای اشتراکی', 'manacore' ),
			'has_links'    => __( 'داشتن لینک دانلود', 'manacore' ),
			'date_range'   => __( 'بازه‌ی تاریخ', 'manacore' ),
			'query_var'    => __( 'پارامتر آدرس (Query String)', 'manacore' ),
		);

		return (array) apply_filters( 'manacore_visibility_rule_types', $types );
	}

	/**
	 * گزینه‌های زمینه‌ی صفحه.
	 *
	 * @return array
	 */
	public static function contexts() {
		return array(
			'front_page' => __( 'صفحه‌ی نخست', 'manacore' ),
			'home'       => __( 'فهرست نوشته‌ها', 'manacore' ),
			'singular'   => __( 'صفحه‌ی تک (اثر/نوشته/برگه)', 'manacore' ),
			'archive'    => __( 'آرشیو', 'manacore' ),
			'tax'        => __( 'آرشیو تاکسونومی', 'manacore' ),
			'search'     => __( 'نتایج جستجو', 'manacore' ),
			'author'     => __( 'آرشیو نویسنده', 'manacore' ),
			'date'       => __( 'آرشیو تاریخ', 'manacore' ),
			'404'        => __( 'صفحه‌ی ۴۰۴', 'manacore' ),
			'paged'      => __( 'صفحه‌بندی‌شده (صفحه ۲ به بعد)', 'manacore' ),
		);
	}

	/**
	 * گزینه‌های دستگاه (با CSS اعمال می‌شود تا با کش سازگار باشد).
	 *
	 * @return array
	 */
	public static function devices() {
		return array(
			'mobile'  => __( 'موبایل (کمتر از ۶۰۰px)', 'manacore' ),
			'tablet'  => __( 'تبلت (۶۰۰ تا ۷۸۱px)', 'manacore' ),
			'desktop' => __( 'دسکتاپ (۷۸۲px و بیشتر)', 'manacore' ),
		);
	}

	/**
	 * نرمال‌سازی ساختار visibility.
	 *
	 * @param mixed $raw داده‌ی خام.
	 * @return array
	 */
	public static function normalize( $raw ) {
		$defaults = array(
			'enabled'  => false,
			'action'   => 'show',
			'relation' => 'AND',
			'rules'    => array(),
			'devices'  => array(),
		);

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$config = wp_parse_args( $raw, $defaults );

		$config['enabled']  = ! empty( $config['enabled'] );
		$config['action']   = 'hide' === $config['action'] ? 'hide' : 'show';
		$config['relation'] = 'OR' === strtoupper( (string) $config['relation'] ) ? 'OR' : 'AND';
		$config['devices']  = array_values(
			array_intersect(
				array_map( 'sanitize_key', (array) $config['devices'] ),
				array_keys( self::devices() )
			)
		);

		$rules = array();
		foreach ( (array) $config['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
				continue;
			}
			$rules[] = array(
				'type'     => sanitize_key( $rule['type'] ),
				'operator' => ( isset( $rule['operator'] ) && 'is_not' === $rule['operator'] ) ? 'is_not' : 'is',
				'taxonomy' => isset( $rule['taxonomy'] ) ? sanitize_key( $rule['taxonomy'] ) : '',
				'values'   => array_values( array_filter( array_map( 'strval', (array) ( isset( $rule['values'] ) ? $rule['values'] : array() ) ), 'strlen' ) ),
				'from'     => isset( $rule['from'] ) ? sanitize_text_field( (string) $rule['from'] ) : '',
				'to'       => isset( $rule['to'] ) ? sanitize_text_field( (string) $rule['to'] ) : '',
				'key'      => isset( $rule['key'] ) ? sanitize_key( $rule['key'] ) : '',
			);
		}
		$config['rules'] = $rules;

		return $config;
	}

	/**
	 * تصمیم نهایی: آیا بلوک رندر شود؟
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return bool
	 */
	public static function should_render( $attrs ) {
		$config = self::normalize( isset( $attrs['visibility'] ) ? $attrs['visibility'] : array() );

		/**
		 * امکان بازنویسی کامل تصمیم نمایش.
		 *
		 * @param null|bool $decision نتیجه (null یعنی ادامه‌ی ارزیابی عادی).
		 * @param array     $config   تنظیمات نرمال‌شده.
		 * @param array     $attrs    ویژگی‌های بلوک.
		 */
		$pre = apply_filters( 'manacore_block_visibility_pre', null, $config, $attrs );
		if ( null !== $pre ) {
			return (bool) $pre;
		}

		if ( ! $config['enabled'] || empty( $config['rules'] ) ) {
			return true;
		}

		// در ویرایشگر همیشه نمایش داده می‌شود تا ادمین بتواند بلوک را ببیند و ویرایش کند.
		if ( self::is_editor_request() ) {
			return true;
		}

		$results = array();
		foreach ( $config['rules'] as $rule ) {
			$match = self::match_rule( $rule );
			if ( 'is_not' === $rule['operator'] ) {
				$match = ! $match;
			}
			$results[] = $match;
		}

		$matched = 'OR' === $config['relation']
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );

		$decision = 'hide' === $config['action'] ? ! $matched : $matched;

		/**
		 * فیلتر نتیجه‌ی نهایی.
		 *
		 * @param bool  $decision نتیجه.
		 * @param array $config   تنظیمات.
		 * @param array $attrs    ویژگی‌ها.
		 */
		return (bool) apply_filters( 'manacore_block_visibility', $decision, $config, $attrs );
	}

	/**
	 * کلاس‌های پنهان‌سازی بر اساس دستگاه.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public static function device_classes( $attrs ) {
		$config = self::normalize( isset( $attrs['visibility'] ) ? $attrs['visibility'] : array() );
		if ( empty( $config['devices'] ) ) {
			return '';
		}
		$classes = array();
		foreach ( $config['devices'] as $device ) {
			$classes[] = 'manacore-hide-' . $device;
		}
		return implode( ' ', $classes );
	}

	/**
	 * آیا درخواست از سمت ویرایشگر است؟
	 *
	 * @return bool
	 */
	protected static function is_editor_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// درخواست ServerSideRender از ویرایشگر.
			return current_user_can( 'edit_posts' );
		}
		return is_admin();
	}

	/**
	 * تطبیق یک قاعده.
	 *
	 * @param array $rule قاعده.
	 * @return bool
	 */
	protected static function match_rule( $rule ) {
		$values = $rule['values'];

		switch ( $rule['type'] ) {

			case 'context':
				return self::match_context( $values );

			case 'post_type':
				$type = self::current_post_type();
				return $type && in_array( $type, array_map( 'sanitize_key', $values ), true );

			case 'taxonomy':
				return self::match_taxonomy( $rule['taxonomy'], $values );

			case 'archive_term':
				return self::match_archive_term( $rule['taxonomy'], $values );

			case 'post_ids':
				$post_id = self::current_post_id();
				return $post_id && in_array( (int) $post_id, array_map( 'absint', $values ), true );

			case 'login':
				$want = isset( $values[0] ) ? $values[0] : 'logged_in';
				return 'logged_out' === $want ? ! is_user_logged_in() : is_user_logged_in();

			case 'user_role':
				return self::match_role( $values );

			case 'subscription':
				return self::match_subscription( $values );

			case 'premium':
				$post_id = self::current_post_id();
				$premium = $post_id ? (bool) get_post_meta( $post_id, 'manacore_is_premium', true ) : false;
				$want    = isset( $values[0] ) ? $values[0] : 'yes';
				return 'no' === $want ? ! $premium : $premium;

			case 'has_links':
				$post_id = self::current_post_id();
				$count   = ( $post_id && class_exists( __NAMESPACE__ . '\\Links' ) ) ? (int) Links::count( $post_id ) : 0;
				$want    = isset( $values[0] ) ? $values[0] : 'yes';
				return 'no' === $want ? 0 === $count : $count > 0;

			case 'date_range':
				return self::match_date_range( $rule['from'], $rule['to'] );

			case 'query_var':
				return self::match_query_var( $rule['key'], $values );
		}

		/**
		 * تطبیق قواعد سفارشی.
		 *
		 * @param bool  $match نتیجه.
		 * @param array $rule  قاعده.
		 */
		return (bool) apply_filters( 'manacore_visibility_match_rule', false, $rule );
	}

	/**
	 * تطبیق زمینه‌ی صفحه.
	 *
	 * @param array $values زمینه‌های انتخابی.
	 * @return bool
	 */
	protected static function match_context( $values ) {
		foreach ( array_map( 'sanitize_key', $values ) as $context ) {
			switch ( $context ) {
				case 'front_page':
					if ( is_front_page() ) {
						return true;
					}
					break;
				case 'home':
					if ( is_home() ) {
						return true;
					}
					break;
				case 'singular':
					if ( is_singular() ) {
						return true;
					}
					break;
				case 'archive':
					if ( is_archive() ) {
						return true;
					}
					break;
				case 'tax':
					if ( is_tax() || is_category() || is_tag() ) {
						return true;
					}
					break;
				case 'search':
					if ( is_search() ) {
						return true;
					}
					break;
				case 'author':
					if ( is_author() ) {
						return true;
					}
					break;
				case 'date':
					if ( is_date() ) {
						return true;
					}
					break;
				case '404':
					if ( is_404() ) {
						return true;
					}
					break;
				case 'paged':
					if ( is_paged() ) {
						return true;
					}
					break;
			}
		}
		return false;
	}

	/**
	 * تطبیق ترم اثر جاری.
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @param array  $values   اسلاگ ترم‌ها (خالی = داشتن هر ترمی).
	 * @return bool
	 */
	protected static function match_taxonomy( $taxonomy, $values ) {
		$post_id = self::current_post_id();
		if ( ! $post_id || ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		if ( empty( $values ) ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			return ( $terms && ! is_wp_error( $terms ) );
		}
		return has_term( array_map( 'sanitize_title', $values ), $taxonomy, $post_id );
	}

	/**
	 * تطبیق آرشیو ترم جاری.
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @param array  $values   اسلاگ ترم‌ها.
	 * @return bool
	 */
	protected static function match_archive_term( $taxonomy, $values ) {
		if ( ! is_tax() && ! is_category() && ! is_tag() ) {
			return false;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}
		if ( $taxonomy && $term->taxonomy !== $taxonomy ) {
			return false;
		}
		if ( empty( $values ) ) {
			return true;
		}
		return in_array( $term->slug, array_map( 'sanitize_title', $values ), true );
	}

	/**
	 * تطبیق نقش کاربر.
	 *
	 * @param array $values نقش‌ها.
	 * @return bool
	 */
	protected static function match_role( $values ) {
		if ( ! is_user_logged_in() ) {
			return in_array( 'guest', $values, true );
		}
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		return (bool) array_intersect( $roles, array_map( 'sanitize_key', $values ) );
	}

	/**
	 * تطبیق سطح اشتراک (نیازمند افزونه‌ی اشتراک).
	 *
	 * @param array $values سطوح.
	 * @return bool
	 */
	protected static function match_subscription( $values ) {
		if ( ! function_exists( 'manacore_subs_user_level' ) ) {
			return false;
		}
		$level = (string) manacore_subs_user_level();
		if ( empty( $values ) ) {
			return function_exists( 'manacore_subs_is_subscriber' ) && manacore_subs_is_subscriber();
		}
		return in_array( $level, array_map( 'sanitize_key', $values ), true );
	}

	/**
	 * تطبیق بازه‌ی تاریخ (بر اساس زمان سایت).
	 *
	 * @param string $from تاریخ شروع (Y-m-d یا Y-m-d H:i).
	 * @param string $to   تاریخ پایان.
	 * @return bool
	 */
	protected static function match_date_range( $from, $to ) {
		$now = (int) current_time( 'timestamp' );

		if ( $from ) {
			$start = strtotime( $from );
			if ( $start && $now < $start ) {
				return false;
			}
		}
		if ( $to ) {
			$end = strtotime( $to );
			if ( $end && $now > $end ) {
				return false;
			}
		}
		return ( $from || $to );
	}

	/**
	 * تطبیق پارامتر آدرس.
	 *
	 * ابتدا متغیرهای ثبت‌شده‌ی وردپرس بررسی می‌شوند (تا پیوندهای یکتا و
	 * بازنویسی‌ها هم پوشش داده شوند) و اگر مقداری نبود، به $_GET برمی‌گردیم.
	 *
	 * @param string $key    نام پارامتر.
	 * @param array  $values مقادیر مجاز (خالی = فقط وجود پارامتر).
	 * @return bool
	 */
	protected static function match_query_var( $key, $values ) {
		if ( ! $key ) {
			return false;
		}

		$current = null;

		if ( function_exists( 'get_query_var' ) ) {
			$from_wp = get_query_var( $key, null );
			if ( null !== $from_wp && '' !== $from_wp ) {
				$current = $from_wp;
			}
		}

		if ( null === $current ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET[ $key ] ) ) {
				return false;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current = wp_unslash( $_GET[ $key ] );
		}

		if ( is_array( $current ) ) {
			$current = reset( $current );
		}

		$current = sanitize_text_field( (string) $current );

		// مقدار خالی یعنی پارامتر عملاً وجود ندارد.
		if ( '' === $current ) {
			return false;
		}

		// فهرست خالی: فقط وجود پارامتر کافی است.
		if ( empty( $values ) ) {
			return true;
		}

		$allowed = array_map( 'sanitize_text_field', array_map( 'strval', (array) $values ) );

		return in_array( $current, $allowed, true );
	}

	/**
	 * شناسه‌ی محتوای جاری (با پشتیبانی از ویرایشگر و آرشیو).
	 *
	 * @return int
	 */
	public static function current_post_id() {
		$post_id = get_the_ID();
		if ( $post_id ) {
			return (int) $post_id;
		}

		/*
		 * صفحه‌ی پخش (`/watch/?manacore_id=…`) هیچ زمینه‌ی پستی ندارد؛ اثر
		 * جاری از کوئری خوانده می‌شود تا مشخصات، دانلود و کارت‌های مکمل
		 * همه به همان اثر اشاره کنند.
		 */
		if ( class_exists( __NAMESPACE__ . '\\Player' ) ) {
			$watched = Player::watched_id();
			if ( $watched ) {
				return (int) $watched;
			}
		}
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post ) {
			return (int) $queried->ID;
		}
		return 0;
	}

	/**
	 * نوع محتوای جاری (تک، آرشیو نوع محتوا، یا آرشیو تاکسونومی).
	 *
	 * @return string
	 */
	public static function current_post_type() {
		$post_id = self::current_post_id();
		if ( $post_id ) {
			return (string) get_post_type( $post_id );
		}
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Post_Type ) {
			return (string) $queried->name;
		}
		if ( $queried instanceof \WP_Term ) {
			$taxonomy = get_taxonomy( $queried->taxonomy );
			if ( $taxonomy && ! empty( $taxonomy->object_type ) ) {
				return (string) reset( $taxonomy->object_type );
			}
		}
		return '';
	}
}

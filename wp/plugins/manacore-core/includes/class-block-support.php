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

		return Query::get_titles( $args )->posts;
	}

	/* ---------------------------------------------------------------------
	 * ۵) قطعه‌های مشترک رندر
	 * ------------------------------------------------------------------ */

	/**
	 * رندر سرتیتر بخش.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public static function render_header( $attrs ) {
		$heading    = isset( $attrs['heading'] ) ? trim( (string) $attrs['heading'] ) : '';
		$subheading = isset( $attrs['subheading'] ) ? trim( (string) $attrs['subheading'] ) : '';
		$show_more  = ! empty( $attrs['showMore'] ) && ! empty( $attrs['moreUrl'] );

		if ( '' === $heading && '' === $subheading && ! $show_more ) {
			return '';
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

		if ( $show_more ) {
			$label  = ! empty( $attrs['moreLabel'] ) ? $attrs['moreLabel'] : __( 'مشاهده همه', 'manacore' );
			$target = ( isset( $attrs['moreTarget'] ) && '_blank' === $attrs['moreTarget'] )
				? ' target="_blank" rel="noopener"'
				: '';
			printf(
				'<a class="manacore-more-link" href="%1$s"%2$s>%3$s<svg class="manacore-more-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M15.4 12L9.7 6.3l1.4-1.4L18.2 12l-7.1 7.1-1.4-1.4z"/></svg></a>',
				esc_url( $attrs['moreUrl'] ),
				$target, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $label )
			);
		}

		echo '</header>';

		return (string) ob_get_clean();
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

		return sprintf(
			'<svg class="manacore-section-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="%s"/></svg>',
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
			'fire'     => 'M12 2s4 4.5 4 8a4 4 0 01-8 0c0-1 .3-2 .8-2.8C7.1 8.6 6 10.7 6 13a6 6 0 0012 0c0-4.6-6-11-6-11z',
			'clock'    => 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 5h-2v6l5 3 1-1.7-4-2.3V7z',
			'eye'      => 'M12 5C6.5 5 2.7 9.2 2 12c.7 2.8 4.5 7 10 7s9.3-4.2 10-7c-.7-2.8-4.5-7-10-7zm0 11a4 4 0 110-8 4 4 0 010 8zm0-2a2 2 0 100-4 2 2 0 000 4z',
			'grid'     => 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z',
			'playlist' => 'M3 5h12v2H3V5zm0 4h12v2H3V9zm0 4h8v2H3v-2zm14-8v8.6a3 3 0 101.5 2.6V7h3V5h-4.5z',
			'heart'    => 'M12 21s-8-4.9-8-10.3A4.7 4.7 0 0112 7a4.7 4.7 0 018 3.7C20 16.1 12 21 12 21z',
			'crown'    => 'M3 7l4 4 5-7 5 7 4-4v11H3V7z',
			'compass'  => 'M12 2a10 10 0 100 20 10 10 0 000-20zm3.4 6.6l-2.1 5.3-5.3 2.1 2.1-5.3 5.3-2.1z',
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

<?php
/**
 * کانال‌های پخش زنده — خواندن، آماده‌سازی و پاسخ REST.
 *
 * هم‌ارز آرایه‌ی سخت‌کدشده‌ی `channels` در `cinora/assets/js/live.js`؛ این‌جا
 * همان داده از **نوع محتوای `channel`** و فراداده‌ی ویرایش‌پذیر خوانده می‌شود
 * تا مدیر بتواند کانال اضافه/کم کند، کیفیت و ویدئو و نشانه‌اش را عوض کند.
 *
 * سه مصرف‌کننده دارد و همه‌ی منطق را همین‌جا جمع می‌کند تا تکرار نسازیم:
 *
 * - بلوک `manacore/live-player` و `manacore/live-channels` (رندر سمت سرور)
 * - نقطه‌ی REST `manacore/v1/live/<slug>` (جابه‌جایی درجای کانال با JS)
 * - متاباکس ویرایشگر (فهرست نشانه‌ها از `icon_options()`)
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Channel
 */
class Channel {

	use Singleton;

	/**
	 * کلید فراداده‌های کانال.
	 */
	const META = array(
		'title'    => 'manacore_channel_title',
		'subtitle' => 'manacore_channel_subtitle',
		'quality'  => 'manacore_channel_quality',
		'icon'     => 'manacore_channel_icon',
		'video'    => 'manacore_channel_video',
		'poster'   => 'manacore_channel_poster',
		'now'      => 'manacore_channel_now',
		'hidden'   => 'manacore_channel_hidden',
	);

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_rewrites' ), 20 );
		add_action( 'init', array( $this, 'maybe_migrate_meta' ), 30 );
		add_filter( 'manage_channel_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_channel_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * ستون‌های فهرست کانال‌ها در پیشخوان: وضعیت پخش، اثر در حال پخش و کیفیت.
	 *
	 * @param array $columns ستون‌های پیش‌فرض.
	 * @return array
	 */
	public function columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['manacore_status']  = __( 'وضعیت پخش', 'manacore' );
				$out['manacore_now']     = __( 'اثر در حال پخش', 'manacore' );
				$out['manacore_quality'] = __( 'کیفیت', 'manacore' );
			}
		}

		return $out;
	}

	/**
	 * محتوای ستون‌های سفارشی فهرست کانال‌ها.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه‌ی کانال.
	 */
	public function column( $column, $post_id ) {
		switch ( $column ) {
			case 'manacore_status':
				if ( self::is_hidden( $post_id ) ) {
					echo esc_html__( 'پنهان از فهرست', 'manacore' );
				} else {
					echo esc_html__( 'نمایش در فهرست', 'manacore' );
				}
				break;

			case 'manacore_now':
				$work = self::now_work( $post_id );
				if ( $work ) {
					echo esc_html( get_the_title( $work ) );
				} else {
					echo '—';
				}
				break;

			case 'manacore_quality':
				$quality = (string) get_post_meta( $post_id, self::META['quality'], true );
				echo esc_html( $quality ? manacore_fa_digits( $quality ) . 'p' : '—' );
				break;
		}
	}

	/**
	 * جای‌نگهدار نشانی کانال (پرس‌وجوی `manacore_channel`).
	 *
	 * پیوند عمومی برگه‌ی پخش زنده از پارامتر `?channel=<slug>` استفاده
	 * می‌کند؛ این تگ، پرس‌وجوی نوع محتوای کانال را جدا نگه می‌دارد تا با
	 * آن پارامتر تداخل نکند.
	 */
	public function register_rewrites() {
		add_rewrite_tag( '%manacore_channel%', '([^&]+)' );
	}

	/**
	 * فهرست نشانه‌های قابل انتخاب کانال.
	 *
	 * همان مجموعه‌ی آیکون سرتیترهای افزونه است (یک منبع حقیقت) با برچسب
	 * فارسی؛ پس هیچ نویسه‌ی یونیکد سخت‌کدی در قالب نمی‌ماند.
	 *
	 * @return array<string,string>
	 */
	public static function icon_options() {
		$labels = array(
			'film'     => __( 'فیلم', 'manacore' ),
			'star'     => __( 'ستاره', 'manacore' ),
			'fire'     => __( 'شعله', 'manacore' ),
			'clock'    => __( 'ساعت', 'manacore' ),
			'eye'      => __( 'چشم', 'manacore' ),
			'grid'     => __( 'شبکه', 'manacore' ),
			'playlist' => __( 'فهرست پخش', 'manacore' ),
			'heart'    => __( 'قلب', 'manacore' ),
			'crown'    => __( 'تاج', 'manacore' ),
			'compass'  => __( 'قطب‌نما', 'manacore' ),
			'calendar' => __( 'تقویم', 'manacore' ),
			'tv'       => __( 'تلویزیون', 'manacore' ),
			'person'   => __( 'چهره', 'manacore' ),
		);

		$options = array();
		foreach ( Block_Support::heading_icon_paths() as $key => $path ) {
			$options[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
		}

		return $options;
	}

	/**
	 * کانال‌ها به ترتیب نمایش.
	 *
	 * کانال‌های «پنهان از فهرست» جز با $include_hidden حذف می‌شوند؛ نشانی
	 * مستقیم آن‌ها (resolve با نامک) همچنان کار می‌کند.
	 *
	 * @param int  $limit          شمار کانال‌ها (۰ = همه).
	 * @param bool $include_hidden نمایش کانال‌های پنهان هم (برای مهاجرت و پیشخوان).
	 * @return array<int,\WP_Post>
	 */
	public static function all( $limit = 0, $include_hidden = false ) {
		$args = array(
			'post_type'      => 'channel',
			'post_status'    => 'publish',
			'posts_per_page' => $limit > 0 ? $limit : 50,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
				/*
				 * کلید سوم عمدی است: بذر آزمون هر سه کانال را در یک ثانیه
				 * می‌سازد، پس `menu_order`/`date` یکسان می‌شوند و بدون
				 * شکست‌شکن، «کانال نخست» از یک درخواست به درخواست دیگر
				 * عوض می‌شد (ناهم‌خوانی کارت فعال با فهرست).
				 */
				'ID'         => 'ASC',
			),
			'no_found_rows'  => true,
		);

		if ( ! $include_hidden ) {
			$args['meta_query'] = array(  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => self::META['hidden'],
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META['hidden'],
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * آیا کانال از فهرست پنهان است؟
	 *
	 * @param int $post_id شناسه‌ی کانال.
	 * @return bool
	 */
	public static function is_hidden( $post_id ) {
		return '1' === (string) get_post_meta( (int) $post_id, self::META['hidden'], true );
	}

	/**
	 * اثر انتخاب‌شده‌ی «در حال پخش» با هر وضعیت انتشار، یا null.
	 *
	 * @param int $post_id شناسه‌ی کانال.
	 * @return \WP_Post|null
	 */
	public static function now_work( $post_id ) {
		$work_id = absint( get_post_meta( (int) $post_id, self::META['now'], true ) );
		if ( ! $work_id || ! in_array( get_post_type( $work_id ), array_merge( manacore_title_post_types(), array( 'episode' ) ), true ) ) {
			return null;
		}

		$work = get_post( $work_id );

		return $work instanceof \WP_Post ? $work : null;
	}

	/**
	 * اثر «در حال پخش» برای نمایش عمومی (فقط آثار منتشرشده).
	 *
	 * @param int $post_id شناسه‌ی کانال.
	 * @return array{id:int,title:string,url:string}|null
	 */
	public static function now_playing( $post_id ) {
		$work = self::now_work( $post_id );
		if ( ! $work || 'publish' !== $work->post_status ) {
			return null;
		}

		return array(
			'id'    => (int) $work->ID,
			'title' => get_the_title( $work ),
			'url'   => (string) get_permalink( $work ),
		);
	}

	/**
	 * پاک‌سازی نشانی ویدئوی پخش: فقط نشانی مطلق http(s) پذیرفته می‌شود.
	 *
	 * @param string $url نشانی خام.
	 * @return string
	 */
	public static function clean_video( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$valid = wp_http_validate_url( $url );

		return $valid ? esc_url_raw( $valid ) : '';
	}

	/**
	 * کانال انتخابی از نشانی (`?channel=slug`) یا کانال نخست.
	 *
	 * @param string $slug نامک درخواستی.
	 * @return \WP_Post|null
	 */
	public static function resolve( $slug = '' ) {
		$slug = sanitize_title( (string) $slug );

		if ( $slug ) {
			$found = get_posts(
				array(
					'post_type'      => 'channel',
					'post_status'    => 'publish',
					'name'           => $slug,
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			if ( $found ) {
				return $found[0];
			}
		}

		$all = self::all( 1 );

		return $all ? $all[0] : null;
	}

	/**
	 * مهاجرت یک‌باره‌ی فراداده پس از جدا شدن «تیتر» از «زیرعنوان».
	 *
	 * تا پیش از این نسخه فقط کلید `manacore_channel_subtitle` وجود داشت و همان
	 * خط تیتر کارت «در حال پخش» را نگه می‌داشت. اکنون مرجع دو خط جدا دارد
	 * (`title`/`subtitle`)، پس مقدار قدیمی به کلید تازه منتقل و کلید قدیمی پاک
	 * می‌شود؛ یک گزینه‌ی نگهبان تضمین می‌کند این کار فقط یک‌بار انجام شود.
	 */
	public function maybe_migrate_meta() {
		if ( 'done' === get_option( 'manacore_channel_meta_migrated' ) ) {
			return;
		}

		foreach ( self::all( 0, true ) as $post ) {
			if ( '' !== (string) get_post_meta( $post->ID, self::META['title'], true ) ) {
				continue;
			}

			$legacy = (string) get_post_meta( $post->ID, self::META['subtitle'], true );

			if ( '' === $legacy ) {
				continue;
			}

			update_post_meta( $post->ID, self::META['title'], $legacy );
			delete_post_meta( $post->ID, self::META['subtitle'] );
		}

		update_option( 'manacore_channel_meta_migrated', 'done', false );
	}

	/**
	 * فراداده‌ی خام کانال.
	 *
	 * @param \WP_Post|int $post کانال.
	 * @return array
	 */
	public static function meta( $post ) {
		$id  = $post instanceof \WP_Post ? $post->ID : (int) $post;
		$out = array();

		foreach ( self::META as $key => $meta_key ) {
			$out[ $key ] = (string) get_post_meta( $id, $meta_key, true );
		}

		return $out;
	}

	/**
	 * پوستر کانال: تصویر شاخص بر فراداده‌ی «نشانی پوستر» اولویت دارد.
	 *
	 * @param \WP_Post|int $post کانال.
	 * @return string
	 */
	public static function poster( $post ) {
		$id = $post instanceof \WP_Post ? $post->ID : (int) $post;

		$thumbnail = get_the_post_thumbnail_url( $id, 'full' );
		if ( $thumbnail ) {
			return (string) $thumbnail;
		}

		$meta = self::meta( $id );

		return $meta['poster'];
	}

	/**
	 * داده‌ی آماده‌ی نمایش/JSON یک کانال.
	 *
	 * @param \WP_Post|int $post کانال.
	 * @return array
	 */
	public static function payload( $post ) {
		$id  = $post instanceof \WP_Post ? $post->ID : (int) $post;
		$obj = get_post( $id );

		if ( ! $obj || 'channel' !== $obj->post_type || 'publish' !== $obj->post_status ) {
			return array();
		}

		$meta    = self::meta( $id );
		$quality = $meta['quality'] ? $meta['quality'] : '1080';
		$icons   = self::icon_options();
		$icon    = isset( $icons[ $meta['icon'] ] ) ? $meta['icon'] : 'tv';

		return array(
			'id'       => $id,
			'slug'     => $obj->post_name,
			'name'     => get_the_title( $id ),
			'title'    => $meta['title'] ? $meta['title'] : get_the_title( $id ),
			'subtitle' => $meta['subtitle'],
			'quality'  => $quality,
			'icon'     => $icon,
			'video'    => self::clean_video( $meta['video'] ),
			'poster'   => esc_url_raw( (string) self::poster( $id ) ),
			'url'      => self::url( $obj ),
			'now'      => self::now_playing( $id ),
		);
	}

	/**
	 * نشانی کانال (پارامتر `channel` روی همان برگه).
	 *
	 * @param \WP_Post|int $post کانال.
	 * @return string
	 */
	public static function url( $post ) {
		$id   = $post instanceof \WP_Post ? $post->ID : (int) $post;
		$slug = get_post_field( 'post_name', $id );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$base = is_singular() || is_page() ? get_permalink() : home_url( '/' );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:enable

		$args = array( 'channel' => $slug );
		if ( $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, $base );
	}
}

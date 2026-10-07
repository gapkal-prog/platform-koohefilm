<?php
/**
 * دانلود امضاشده (Signed Links) و شمارش سرورسوی دانلود.
 *
 * چرا لازم است: نشانی خام فایل روی صفحه یعنی هر کسی می‌تواند لینک را کپی کند
 * و جاهای دیگر بگذارد (hotlink)؛ برای سایت‌های اشتراکی این یعنی از دست رفتن
 * درآمد. با روشن‌کردن «امضای لینک»، دکمه‌ی دانلود به یک مسیر داخلی می‌رود که
 * سه چیز را می‌سنجد و بعد کاربر را به فایل اصلی می‌فرستد:
 *   ۱. **امضا** با `hash_hmac` روی (شناسه‌ی اثر | شناسه‌ی لینک | زمان انقضا)
 *      — تغییر هر قسمت، امضا را بی‌اعتبار می‌کند.
 *   ۲. **انقضا** — لینک پس از بازه‌ی تنظیم‌شده (پیش‌فرض ۲۴ ساعت) می‌سوزد.
 *   ۳. **دسترسی** — گروه ویژه‌ی مشترکین بدون اشتراک باز نمی‌شود.
 *
 * مقصد بازفرست همیشه از متای خودِ اثر خوانده می‌شود؛ هیچ‌گاه از پارامتر نشانی،
 * پس این مسیر به «بازفرست باز» (open redirect) تبدیل نمی‌شود.
 *
 * شمارش دانلود (`count()`) هم این‌جا متمرکز شده است: هم مسیر امضاشده و هم
 * مسیر عمومی `track-download` از آن استفاده می‌کنند تا یک کلیک، دو بار شمرده
 * نشود و ربات/رفرش پشت‌سرهم آمار را باد نکند.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Downloads
 */
class Downloads {

	use Singleton;

	/**
	 * مسیر REST دانلود امضاشده.
	 */
	const ROUTE = '/download/(?P<id>\d+)';

	/**
	 * پنجره‌ی ضدرعدّ‌سازی شمارش (ثانیه).
	 */
	const DEDUPE_WINDOW = 30;

	/**
	 * بازه‌ی مجاز اعتبار لینک (دقیقه).
	 */
	const MIN_TTL = 1;
	const MAX_TTL = 10080;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * آیا امضای لینک روشن است؟
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) manacore_get_option( 'download_signing', 0 );
	}

	/**
	 * اعتبار لینک (ثانیه) — از تنظیمات، با کرانه‌گذاری.
	 *
	 * @return int
	 */
	public static function ttl() {
		$minutes = (int) manacore_get_option( 'download_ttl', 1440 );
		$minutes = max( self::MIN_TTL, min( self::MAX_TTL, $minutes ) );

		return $minutes * MINUTE_IN_SECONDS;
	}

	/**
	 * یافتن یک لینک (item) در گروه‌های لینک یک اثر.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @return array|null `url`، `type`، `premium`، `quality`.
	 */
	public static function item( $post_id, $item_id ) {
		$item_id = sanitize_key( (string) $item_id );

		if ( '' === $item_id || ! $post_id ) {
			return null;
		}

		foreach ( Links::get( $post_id ) as $group ) {
			foreach ( (array) $group['items'] as $item ) {
				if ( (string) $item['id'] !== $item_id ) {
					continue;
				}

				return array(
					'url'     => (string) $item['url'],
					'type'    => (string) $item['type'],
					'premium' => ! empty( $group['premium'] ),
					'quality' => (string) $group['quality'],
				);
			}
		}

		return null;
	}

	/**
	 * یافتن شناسه‌ی لینکی که نشانی‌اش با این URL یکی است.
	 *
	 * برای آن است که پلیر (که فقط نشانی را می‌شناسد، نه شناسه‌ی لینک) هم
	 * بتواند دکمه‌ی دانلودش را امضا کند.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $url     نشانی فایل.
	 * @return string شناسه یا رشته‌ی خالی.
	 */
	public static function item_id_for_url( $post_id, $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$found = '';

		foreach ( Links::get( $post_id ) as $group ) {
			foreach ( (array) $group['items'] as $item ) {
				if ( (string) $item['url'] === $url ) {
					$found = (string) $item['id'];

					/* نخستین لینک «دانلودی» بر «پخش» مقدم است. */
					if ( 'stream' !== (string) $item['type'] ) {
						return $found;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * امضای یک لینک.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @param int    $exp     زمان انقضا (برچسب یونیکس).
	 * @return string
	 */
	public static function signature( $post_id, $item_id, $exp ) {
		return hash_hmac( 'sha256', (int) $post_id . '|' . sanitize_key( $item_id ) . '|' . (int) $exp, wp_salt( 'manacore_downloads' ) );
	}

	/**
	 * نشانی امضاشده‌ی یک لینک.
	 *
	 * مقادیر با `rawurlencode()` کد می‌شوند؛ `add_query_arg()` مقادیر تازه را
	 * کدگذاری نمی‌کند و شناسه‌های لینک می‌توانند نویسه‌ی غیرساده داشته باشند.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @param int    $exp     زمان انقضا (۰ = پیش‌فرض تنظیمات).
	 * @return string
	 */
	public static function signed_url( $post_id, $item_id, $exp = 0 ) {
		$exp = $exp ? (int) $exp : time() + self::ttl();

		return add_query_arg(
			array(
				'item' => rawurlencode( sanitize_key( $item_id ) ),
				'exp'  => (int) $exp,
				'sig'  => rawurlencode( self::signature( $post_id, $item_id, $exp ) ),
			),
			rest_url( Rest_Api::NS . '/download/' . (int) $post_id )
		);
	}

	/**
	 * نشانی نهایی یک دکمه‌ی دانلود.
	 *
	 * تک نقطه‌ی تصمیم: اگر امضا خاموش باشد، لینک شناخته نشود یا نوعش «پخش»
	 * باشد، همان نشانی خام برمی‌گردد و هیچ رفتاری عوض نمی‌شود.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $url     نشانی خام فایل.
	 * @param string $type    نوع لینک (`stream`، `direct`، …).
	 * @return string
	 */
	public static function url_for( $post_id, $url, $type = 'direct' ) {
		if ( ! self::enabled() || ! $post_id ) {
			return (string) $url;
		}

		$item_id = self::item_id_for_url( $post_id, $url );

		if ( '' === $item_id ) {
			return (string) $url;
		}

		$item = self::item( $post_id, $item_id );

		if ( ! $item || 'stream' === $item['type'] ) {
			return (string) $url;
		}

		return self::signed_url( $post_id, $item_id );
	}

	/**
	 * اعتبارسنجی امضا و انقضا.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @param int    $exp     زمان انقضا.
	 * @param string $sig     امضای رسیده.
	 * @return string `ok`، `expired` یا `bad`.
	 */
	public static function verify( $post_id, $item_id, $exp, $sig ) {
		$sig = (string) $sig;

		if ( '' === $sig ) {
			return 'bad';
		}

		$expected = self::signature( $post_id, $item_id, $exp );

		if ( ! hash_equals( $expected, strtolower( $sig ) ) ) {
			return 'bad';
		}

		if ( (int) $exp < time() ) {
			return 'expired';
		}

		return 'ok';
	}

	/**
	 * شمارش یک دانلود با پنجره‌ی ضدرعدّ‌سازی.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $type    نوع آمار.
	 * @return bool آیا شمرده شد؟
	 */
	public static function count( $post_id, $type = 'download' ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return false;
		}

		$key = 'manacore_dl_' . $post_id . '_' . md5( manacore_visitor_hash( 'download' ) );

		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, self::DEDUPE_WINDOW );

		if ( class_exists( __NAMESPACE__ . '\\Ratings' ) ) {
			Ratings::instance()->log_stat( $post_id, $type );
		}

		/**
		 * پس از شمارش یک دانلود.
		 *
		 * @param int    $post_id شناسه‌ی اثر.
		 * @param string $type    نوع آمار.
		 */
		do_action( 'manacore_download_counted', $post_id, $type );

		return true;
	}

	/**
	 * ثبت مسیر REST.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Api::NS,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'resolve' ),
				/*
				 * دروازه‌بان عمداً باز است: خودِ امضا (HMAC) و انقضا و
				 * بررسی دسترسی، اعتبار درخواست را می‌سازند و نمی‌شود از
				 * کاربر واردنشده نشانی (nonce) خواست — لینک دانلود ممکن
				 * است ساعتی بعد در نرم‌افزار مدیریت دانلود باز شود.
				 */
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'item' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'exp'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'sig'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * حل یک لینک امضاشده و بازفرست به فایل.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resolve( $request ) {
		$post_id = (int) $request['id'];
		$item_id = (string) $request['item'];
		$exp     = (int) $request['exp'];
		$sig     = (string) $request['sig'];

		if ( ! self::enabled() ) {
			return new \WP_Error(
				'manacore_download_disabled',
				__( 'امضای لینک دانلود در این سایت خاموش است.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		$item = self::item( $post_id, $item_id );

		if ( ! $item ) {
			return new \WP_Error(
				'manacore_download_missing',
				__( 'این لینک دانلود پیدا نشد؛ صفحه را دوباره باز کنید.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		$state = self::verify( $post_id, $item_id, $exp, $sig );

		if ( 'bad' === $state ) {
			return new \WP_Error(
				'manacore_download_signature',
				__( 'نشانی دانلود معتبر نیست. صفحه را دوباره باز کنید و از دکمه‌ی دانلود استفاده کنید.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		if ( 'expired' === $state ) {
			return new \WP_Error(
				'manacore_download_expired',
				__( 'اعتبار این نشانی دانلود تمام شده است؛ صفحه را دوباره باز کنید.', 'manacore' ),
				array( 'status' => 410 )
			);
		}

		/* گروه ویژه‌ی مشترکین بدون دسترسی، باز نمی‌شود. */
		if ( $item['premium'] && function_exists( 'manacore_user_can_access' ) && ! manacore_user_can_access( $post_id ) ) {
			return new \WP_Error(
				'manacore_download_locked',
				__( 'این لینک ویژه‌ی اعضای اشتراکی است.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		self::count( $post_id );

		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $item['url'] );

		return $response;
	}
}

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
 * «پل دانلود» گام میانی اختیاری است: دکمه‌ی دانلود به یک برگه‌ی سبک می‌رود که
 * کارت کیفیت/حجم/زبان و یادداشت مدیر را نشان می‌دهد و بعد کاربر با یک کلیک
 * دیگر به فایل می‌رسد. خودِ آن نشانی هم امضاشده است و با همان `exp` می‌سوزد؛
 * پس پل، درِ پشتی امضا نیست. شمارش دانلود همان‌جا می‌ماند که بود (مسیر
 * بازفرست)، تا بازکردن پل شمارش را باد نکند.
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
	 * نام کوئری‌وارِ برگه‌ی «پل دانلود».
	 */
	const BRIDGE_VAR = 'manacore_download';

	/**
	 * نام کوئری‌وارِ شناسه‌ی لینک در برگه‌ی پل.
	 */
	const BRIDGE_ITEM_VAR = 'manacore_download_item';

	/**
	 * پیشوند پیش‌فرض نشانی پل دانلود.
	 *
	 * با فیلتر `manacore_download_bridge_slug` عوض می‌شود؛ پیشوند
	 * یکتاست تا با برگه/دسته‌ی هم‌نام سایت تصادم نکند.
	 */
	const BRIDGE_SLUG = 'manacore-download';

	/**
	 * گزینه‌ی مهر «قواعد بازنویسی به‌روز است».
	 */
	const REWRITE_STAMP = 'manacore_download_rewrite_stamp';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_bridge' ) );
		add_action( 'admin_init', array( $this, 'sync_rewrite' ) );
	}

	/**
	 * آیا «پل دانلود» روشن است؟
	 *
	 * بدون امضای لینک، پل معنایی ندارد (نشانی‌ای نیست که امضا شود)، پس
	 * این گزینه فقط با روشن‌بودن امضا اثر می‌کند.
	 *
	 * @return bool
	 */
	public static function bridge_enabled() {
		return self::enabled() && (bool) manacore_get_option( 'download_bridge', 1 );
	}

	/**
	 * پیشوند نشانی پل (قابل تغییر با فیلتر).
	 *
	 * @return string
	 */
	public static function bridge_slug() {
		/**
		 * فیلتر پیشوند نشانی «پل دانلود».
		 *
		 * @param string $slug پیشوند.
		 */
		$slug = sanitize_title( (string) apply_filters( 'manacore_download_bridge_slug', self::BRIDGE_SLUG ) );

		return '' !== $slug ? $slug : self::BRIDGE_SLUG;
	}

	/**
	 * ثبت قاعده‌ی بازنویسی نشانی پل دانلود.
	 *
	 * @return void
	 */
	public function register_rewrite() {
		if ( ! self::bridge_enabled() ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( self::bridge_slug(), '/' ) . '/(\d+)/([A-Za-z0-9_\-]+)/?$',
			'index.php?' . self::BRIDGE_VAR . '=$matches[1]&' . self::BRIDGE_ITEM_VAR . '=$matches[2]',
			'top'
		);
	}

	/**
	 * هم‌گام‌سازی قواعد بازنویسی با وضعیت پل.
	 *
	 * قاعده‌ی بازنویسی در `init` ثبت می‌شود، ولی فهرست قواعد در
	 * پایگاه‌داده ذخیره شده است؛ پس سایتی که افزونه را به‌روز می‌کند یا
	 * تیک پل را می‌زند، تا یک بازسازی، نشانی پل را ۴۰۴ می‌بیند. مهر
	 * نسخه + پیشوند + وضعیت، این کار را یک‌بار و فقط وقتی لازم است
	 * انجام می‌دهد (روی `admin_init`، نه هر درخواست کاربر).
	 *
	 * @return void
	 */
	public function sync_rewrite() {
		$stamp = MANACORE_VERSION . '|' . self::bridge_slug() . '|' . ( self::bridge_enabled() ? '1' : '0' );

		if ( $stamp === (string) get_option( self::REWRITE_STAMP, '' ) ) {
			return;
		}

		update_option( self::REWRITE_STAMP, $stamp, false );
		flush_rewrite_rules( false );
	}

	/**
	 * افزودن کوئری‌وارهای پل به فهرست مجاز وردپرس.
	 *
	 * @param string[] $vars کوئری‌وارها.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::BRIDGE_VAR;
		$vars[] = self::BRIDGE_ITEM_VAR;

		return $vars;
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
		if ( ! $post_id ) {
			return null;
		}

		/* تنها نقطه‌ی جست‌وجوی ردیف لینک در پروژه: `Links::find()`. */
		return Links::find( $post_id, $item_id );
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

		/* پل دانلود روشن است؟ دکمه به گام میانی می‌رود، نه مستقیم به فایل. */
		if ( self::bridge_enabled() ) {
			return self::bridge_url( $post_id, $item_id );
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

	/* -----------------------------------------------------------------
	 * پل دانلود
	 * -------------------------------------------------------------- */

	/**
	 * نشانی برگه‌ی پل دانلود برای یک ردیف لینک.
	 *
	 * همان امضای مسیر بازفرست را با خود می‌برد (`exp` و `sig`)، پس پل
	 * هم زمان‌دار است و کسی که نشانی را جای دیگر بگذارد، پس از پایان
	 * اعتبار به فایل نمی‌رسد. هیچ مقصدی در آدرس نمی‌آید.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @param int    $exp     زمان انقضا (۰ = پیش‌فرض تنظیمات).
	 * @return string
	 */
	public static function bridge_url( $post_id, $item_id, $exp = 0 ) {
		$item_id = sanitize_key( (string) $item_id );
		$exp     = $exp ? (int) $exp : time() + self::ttl();

		return add_query_arg(
			array(
				'exp' => (int) $exp,
				'sig' => rawurlencode( self::signature( $post_id, $item_id, $exp ) ),
			),
			home_url( user_trailingslashit( self::bridge_slug() . '/' . (int) $post_id . '/' . $item_id ) )
		);
	}

	/**
	 * رندر پل دانلود اگر درخواست به برگه‌ی پل خورده باشد.
	 *
	 * @return void
	 */
	public function maybe_render_bridge() {
		if ( is_admin() || ! self::bridge_enabled() ) {
			return;
		}

		$post_id = (int) get_query_var( self::BRIDGE_VAR );

		if ( $post_id <= 0 ) {
			return;
		}

		$this->render_bridge( $post_id, (string) get_query_var( self::BRIDGE_ITEM_VAR ) );
	}

	/**
	 * برگه‌ی «پل دانلود».
	 *
	 * چرا لازم است؟ دکمه‌ی دانلود تا امروز بی‌واسطه به فایل می‌رفت؛ کاربر
	 * نمی‌فهمید چه چیزی (کیفیت، حجم، زبان) و با چه شرطی (اشتراک) در
	 * انتظار اوست. این برگه آن گام را شفاف می‌کند و در همان حال جای
	 * یادآوری نرم اشتراک است — بدون هیچ شمارش یا تزئینی.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $item_id شناسه‌ی لینک.
	 * @return void
	 */
	protected function render_bridge( $post_id, $item_id ) {
		$item_id = sanitize_key( $item_id );
		$item    = self::item( $post_id, $item_id );

		if ( ! $item ) {
			$this->end_bridge(
				array(
					'status' => 404,
					'title'  => __( 'این لینک پیدا نشد', 'manacore' ),
					'text'   => __( 'نشانی دانلود معتبر نیست یا لینک از اثر برداشته شده است. از صفحه‌ی اثر، دکمه‌ی دانلود را دوباره بزنید.', 'manacore' ),
				)
			);
		}

		$state = self::verify(
			$post_id,
			$item_id,
			isset( $_GET['exp'] ) ? absint( wp_unslash( $_GET['exp'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- امضای HMAC جای نانِس را گرفته است.
			isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '' // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( 'expired' === $state ) {
			$this->end_bridge(
				array(
					'status' => 410,
					'title'  => __( 'اعتبار این نشانی تمام شد', 'manacore' ),
					'text'   => __( 'نشانی‌های دانلود زمان‌دارند تا کسی آن‌ها را جای دیگر منتشر نکند. صفحه را باز کنید و دکمه‌ی دانلود را دوباره بزنید.', 'manacore' ),
				)
			);
		}

		if ( 'ok' !== $state ) {
			$this->end_bridge(
				array(
					'status' => 403,
					'title'  => __( 'این نشانی معتبر نیست', 'manacore' ),
					'text'   => __( 'نشانی دانلود دست‌کاری شده یا ناقص کپی شده است. از صفحه‌ی اثر، دکمه‌ی دانلود را دوباره بزنید.', 'manacore' ),
				)
			);
		}

		$locked = $item['premium'] && function_exists( 'manacore_user_can_access' ) && ! manacore_user_can_access( $post_id );

		$this->end_bridge(
			array(
				'status' => $locked ? 403 : 200,
				'item'   => $item,
				'locked' => $locked,
				'post'   => $post_id,
				/* دکمه‌ی پایانی همان مسیر امضاشده است؛ پل امضا را دور نمی‌زند. */
				'url'    => $locked ? '' : self::signed_url(
					$post_id,
					$item_id,
					isset( $_GET['exp'] ) ? absint( wp_unslash( $_GET['exp'] ) ) : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				),
			)
		);
	}

	/**
	 * چاپ سند پل و پایان درخواست.
	 *
	 * `bridge_document()` عمداً رشته برمی‌گرداند (نه چاپ + پایان) تا
	 * آزمون‌ها بتوانند خروجی را بی‌آنکه اجرا تمام شود بسنجند؛ پایان دادن
	 * به درخواست فقط کار همین لایه است.
	 *
	 * @param array $args وضعیت و محتوای برگه.
	 * @return void
	 */
	protected function end_bridge( $args ) {
		echo $this->bridge_document( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- سند همین کلاس و امن است.

		exit;
	}

	/**
	 * ساخت و چاپ سندِ کامل برگه‌ی پل.
	 *
	 * برگه مستقل است (نه الگوی قالب): افزونه نباید به وجود الگوی خاصی در
	 * قالب وابسته باشد. سر و پای صفحه از قالب می‌آید (`wp_head`,
	 * `wp_footer`) تا رنگ‌ها و قلم‌ها همان زبان بصری سایت بمانند.
	 *
	 * خروجی رشته است (نه چاپ + پایان) تا افزونه‌های دیگر و آزمون‌ها هم
	 * بتوانند همان کارت را بی‌آنکه درخواست تمام شود بسازند.
	 *
	 * @param array $args وضعیت و محتوای برگه.
	 * @return string
	 */
	public function bridge_document( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'status' => 200,
				'title'  => '',
				'text'   => '',
				'post'   => 0,
				'item'   => array(),
				'locked' => false,
				'url'    => '',
			)
		);

		if ( ! headers_sent() ) {
			status_header( (int) $args['status'] );

			/*
			 * برگه‌ی امضاشده هرگز کش نشود: کش عمومی یعنی نشانی امضاشده
			 * در دست کسی که نباید بنشیند.
			 */
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}

		wp_enqueue_style(
			'manacore-bridge',
			MANACORE_URL . 'assets/css/bridge.css',
			array( 'manacore-front' ),
			MANACORE_VERSION
		);

		$post_id  = (int) $args['post'];
		$title    = '' !== (string) $args['title'] ? (string) $args['title'] : get_the_title( $post_id );
		$home     = home_url( '/' );
		$back     = $post_id ? get_permalink( $post_id ) : '';
		$doc_title = '' !== (string) $args['title'] ? (string) $args['title'] : get_bloginfo( 'name' );

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="referrer" content="no-referrer" />
	<title><?php echo esc_html( $doc_title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'manacore-bridge' ); ?>>
	<div class="mc-bridge" data-manacore-bridge="<?php echo esc_attr( (int) $args['status'] ); ?>">
		<header class="mc-bridge__bar">
			<a class="mc-bridge__brand" href="<?php echo esc_url( $home ); ?>">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
			</a>
		</header>

		<main class="mc-bridge__main" id="manacore-content">
			<?php
			if ( ! empty( $args['item'] ) ) {
				$this->bridge_card( $args );
			} else {
				$this->bridge_message( (string) $args['title'], (string) $args['text'] );
			}
			?>
		</main>

		<footer class="mc-bridge__foot">
			<?php if ( '' !== (string) $back ) : ?>
				<a class="mc-bridge__back" href="<?php echo esc_url( (string) $back ); ?>">
					<?php
					printf(
						/* translators: %s: نام اثر */
						esc_html__( 'بازگشت به «%s»', 'manacore' ),
						esc_html( get_the_title( $post_id ) )
					);
					?>
				</a>
			<?php else : ?>
				<a class="mc-bridge__back" href="<?php echo esc_url( $home ); ?>"><?php esc_html_e( 'بازگشت به خانه', 'manacore' ); ?></a>
			<?php endif; ?>
		</footer>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * کارت «چه چیزی دانلود می‌کنم؟» و دکمه‌ی شروع.
	 *
	 * @param array $args وضعیت برگه.
	 * @return void
	 */
	protected function bridge_card( $args ) {
		$post_id = (int) $args['post'];
		$item    = (array) $args['item'];
		$locked  = ! empty( $args['locked'] );
		$thumb   = get_the_post_thumbnail(
			$post_id,
			'medium',
			array(
				'class'   => 'mc-bridge__poster',
				'loading' => 'eager',
				'alt'     => get_the_title( $post_id ),
			)
		);

		$facts = array();

		if ( '' !== (string) $item['quality'] ) {
			$facts[] = array( __( 'کیفیت', 'manacore' ), Links::quality_label( (string) $item['quality'] ) );
		}

		if ( '' !== (string) $item['size'] ) {
			$facts[] = array( __( 'حجم', 'manacore' ), (string) $item['size'] );
		}

		if ( '' !== (string) $item['language'] ) {
			$facts[] = array( __( 'زبان', 'manacore' ), Links::language_label( (string) $item['language'] ) );
		}

		if ( '' !== (string) $item['type'] ) {
			$facts[] = array( __( 'نوع', 'manacore' ), Links::type_label( (string) $item['type'] ) );
		}

		if ( '' !== (string) $item['encoder'] ) {
			$facts[] = array( __( 'انکودر', 'manacore' ), (string) $item['encoder'] );
		}

		$facts[] = array( __( 'اثر', 'manacore' ), get_the_title( $post_id ) );

		$row_title = '' !== (string) $item['group_title'] ? (string) $item['group_title'] : (string) $item['label'];
		$notice    = self::bridge_notice( $post_id, $item );
		?>
		<article class="mc-bridge__card">
			<?php if ( '' !== $thumb ) : ?>
				<div class="mc-bridge__art"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی امن `get_the_post_thumbnail()`. ?></div>
			<?php endif; ?>

			<div class="mc-bridge__body">
				<p class="mc-bridge__eyebrow"><?php esc_html_e( 'آماده‌ی دانلود', 'manacore' ); ?></p>
				<h1 class="mc-bridge__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>

				<?php if ( '' !== $row_title ) : ?>
					<p class="mc-bridge__row"><?php echo esc_html( $row_title ); ?></p>
				<?php endif; ?>

				<ul class="mc-bridge__facts">
					<?php foreach ( $facts as $fact ) : ?>
						<li>
							<span><?php echo esc_html( $fact[0] ); ?></span>
							<b><?php echo esc_html( $fact[1] ); ?></b>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( '' !== $notice ) : ?>
					<p class="mc-bridge__notice"><?php echo esc_html( $notice ); ?></p>
				<?php endif; ?>

				<?php
				if ( $locked ) {
					$this->bridge_locked( $post_id );
				} else {
					?>
					<div class="mc-bridge__actions">
						<a class="mc-bridge__start" href="<?php echo esc_url( (string) $args['url'] ); ?>" rel="nofollow noopener">
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>
							<?php esc_html_e( 'شروع دانلود', 'manacore' ); ?>
						</a>
						<span class="mc-bridge__hint"><?php esc_html_e( 'فایل در پنجره‌ی همان مرورگر باز می‌شود.', 'manacore' ); ?></span>
					</div>
					<?php
				}
				?>
			</div>
		</article>
		<?php
	}

	/**
	 * حالت «قفل»: یادآوری نرم اشتراک به‌جای خطای خشک.
	 *
	 * @param int $post_id شناسه‌ی اثر.
	 * @return void
	 */
	protected function bridge_locked( $post_id ) {
		$subscribe_url   = (string) manacore_get_option( 'subscribe_url', '' );
		$subscribe_label = (string) manacore_get_option( 'subscribe_label', '' );

		if ( '' === $subscribe_label ) {
			$subscribe_label = __( 'تهیه اشتراک', 'manacore' );
		}
		?>
		<div class="mc-bridge__locked">
			<p class="mc-bridge__locked-text">
				<?php esc_html_e( 'این لینک ویژه‌ی اعضای اشتراکی است. با اشتراک، بی‌تبلیغ و با کیفیت کامل دانلود کنید و لینک‌های تازه را زودتر ببینید.', 'manacore' ); ?>
			</p>
			<div class="mc-bridge__actions">
				<?php if ( '' !== $subscribe_url ) : ?>
					<a class="mc-bridge__start" href="<?php echo esc_url( $subscribe_url ); ?>" rel="nofollow noopener">
						<?php echo esc_html( $subscribe_label ); ?>
					</a>
				<?php endif; ?>

				<?php if ( ! is_user_logged_in() ) : ?>
					<a class="mc-bridge__alt" href="<?php echo esc_url( wp_login_url( (string) get_permalink( $post_id ) ) ); ?>">
						<?php esc_html_e( 'ورود به حساب', 'manacore' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * پیام ساده‌ی برگه (خطای امضا، انقضا، نبودِ لینک).
	 *
	 * @param string $title سرتیتر.
	 * @param string $text  توضیح.
	 * @return void
	 */
	protected function bridge_message( $title, $text ) {
		?>
		<div class="mc-bridge__message">
			<h1 class="mc-bridge__title"><?php echo esc_html( $title ); ?></h1>
			<p class="mc-bridge__text"><?php echo esc_html( $text ); ?></p>
		</div>
		<?php
	}

	/**
	 * متن یادداشت پل: تنظیم مدیر، با جای‌نگهدارهای اثر.
	 *
	 * @param int   $post_id شناسه‌ی اثر.
	 * @param array $item    ردیف لینک.
	 * @return string
	 */
	public static function bridge_notice( $post_id, $item ) {
		$text = (string) manacore_get_option( 'bridge_notice_text', '' );

		if ( '' === trim( $text ) ) {
			$text = __( 'پیش از دانلود، از کیفیت و حجم مطمئن شوید. اگر لینک خراب بود، از صفحه‌ی اثر گزارش دهید تا سریع جایگزین شود.', 'manacore' );
		}

		$replace = array(
			'{title}'   => get_the_title( $post_id ),
			'{quality}' => '' !== (string) $item['quality'] ? Links::quality_label( (string) $item['quality'] ) : '',
			'{size}'    => (string) $item['size'],
		);

		return trim( str_replace( array_keys( $replace ), array_values( $replace ), $text ) );
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

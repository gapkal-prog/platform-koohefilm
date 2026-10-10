<?php
/**
 * پخش‌کننده‌ی ویدیو و تریلر.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Player
 */
class Player {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_shortcode( 'manacore_player', array( $this, 'shortcode' ) );
		add_action( 'wp_footer', array( $this, 'modal' ) );
		add_filter( 'request', array( $this, 'strip_own_params' ) );
	}

	/**
	 * حذف پارامترهای مخصوص صفحه‌ی پخش از پرسمانِ اصلی وردپرس.
	 *
	 * چرا لازم است؟ پارامترهای `season`/`episode`/`quality` داده‌ی خودِ
	 * صفحه‌ی پخش‌اند و همین صفحه آن‌ها را از `$_GET` می‌خواند، ولی نام
	 * `episode` با متغیر پرسمانِ عمومیِ نوع‌محتوای «قسمت» همنام است
	 * (`register_post_type( 'episode', query_var: true )`). نتیجه در آزمون
	 * واقعی: هر پیوند پخشِ قسمت (`/watch/?manacore_id=8&season=1&episode=2`)
	 * **۴۰۴** می‌داد، چون وردپرس می‌خواست قسمتِ با نامک «2» را در پرسمانِ
	 * اصلی بیابد. اینجا درست پیش از ساخت `WP_Query` از پرسمان بیرون
	 * گذاشته می‌شوند تا صفحه‌ی پخش عادی resolve شود.
	 *
	 * فقط وقتی فعال است که `manacore_id` معتبر در نشانی باشد؛ پس هیچ
	 * پرسمانِ دیگری در سایت تغییر نمی‌کند (مثلاً آرشیو با `?season=2`
	 * همچنان کار می‌کند).
	 *
	 * @param array $vars متغیرهای پرسمان.
	 * @return array
	 */
	public function strip_own_params( $vars ) {
		if ( ! self::watched_id() ) {
			return $vars;
		}

		if ( isset( $vars['episode'] ) && isset( $vars['post_type'] ) && 'episode' === (string) $vars['post_type'] ) {
			/*
			 * وردپرس پیش از فیلتر `request` مقدار `?episode=` را به
			 * `post_type=episode` و `name=<مقدار>` ترجمه می‌کند
			 * (`WP::parse_request()`)، و همین `post_type` جای پرسمانِ برگه را
			 * می‌گیرد. اگر مقدار از پارامتر خودمان آمده باشد، برگردانده
			 * می‌شود.
			 */
			if ( (string) $vars['episode'] === (string) ( $vars['name'] ?? '' ) ) {
				unset( $vars['post_type'], $vars['name'] );
			}
		}

		foreach ( array( 'season', 'episode', 'quality' ) as $key ) {
			unset( $vars[ $key ] );
		}

		return $vars;
	}

	/**
	 * تشخیص نوع منبع و ساخت آدرس جاسازی.
	 *
	 * @param string $url آدرس.
	 * @return array{type:string,src:string}
	 */
	public static function resolve( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return array(
				'type' => 'none',
				'src'  => '',
			);
		}

		// یوتیوب.
		if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $url, $m ) ) {
			return array(
				'type' => 'iframe',
				'src'  => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1',
			);
		}

		// آپارات.
		if ( preg_match( '#aparat\.com/v/([A-Za-z0-9]+)#i', $url, $m ) ) {
			return array(
				'type' => 'iframe',
				'src'  => 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame',
			);
		}

		// ویمئو.
		if ( preg_match( '#vimeo\.com/(\d+)#i', $url, $m ) ) {
			return array(
				'type' => 'iframe',
				'src'  => 'https://player.vimeo.com/video/' . $m[1],
			);
		}

		// فایل ویدیویی مستقیم یا HLS.
		if ( preg_match( '#\.(mp4|webm|ogv|m3u8)(\?|$)#i', $url ) ) {
			return array(
				'type' => 'video',
				'src'  => $url,
			);
		}

		return array(
			'type' => 'iframe',
			'src'  => $url,
		);
	}

	/**
	 * رندر پخش‌کننده.
	 *
	 * @param string $url   آدرس.
	 * @param string $title عنوان.
	 * @return string
	 */
	public static function render( $url, $title = '' ) {
		$resolved = self::resolve( $url );
		if ( 'none' === $resolved['type'] ) {
			return '';
		}

		ob_start();
		?>
		<div class="manacore-player">
			<?php if ( 'video' === $resolved['type'] ) : ?>
				<video controls preload="metadata" playsinline
					<?php if ( $title ) : ?>title="<?php echo esc_attr( $title ); ?>"<?php endif; ?>>
					<source src="<?php echo esc_url( $resolved['src'] ); ?>" />
					<?php esc_html_e( 'مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.', 'manacore' ); ?>
				</video>
			<?php else : ?>
				<iframe src="<?php echo esc_url( $resolved['src'] ); ?>"
					title="<?php echo esc_attr( $title ? $title : __( 'پخش‌کننده', 'manacore' ) ); ?>"
					loading="lazy" allowfullscreen
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					referrerpolicy="strict-origin-when-cross-origin"></iframe>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد پخش‌کننده.
	 *
	 * @param array $atts پارامترها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'url'        => '',
				'title'      => '',
				'id'         => 0,
				'show_title' => '',
			),
			$atts,
			'manacore_player'
		);

		/*
		 * صفحه‌ی پخش (`/watch/?manacore_id=…`) شناسه را از کوئری می‌خواند؛
		 * همان کاری که مرجع با `player.html?id=…` می‌کند. نام پارامتر
		 * اختصاصی است تا با کوئری‌های خود وردپرس تلاقی نکند.
		 */
		if ( ! $atts['id'] && isset( $_GET['manacore_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$atts['id'] = absint( wp_unslash( $_GET['manacore_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$url = $atts['url'];
		if ( ! $url && $atts['id'] ) {
			$url = get_post_meta( absint( $atts['id'] ), 'manacore_trailer_url', true );
		}
		if ( ! $url && is_singular() ) {
			$url = get_post_meta( get_the_ID(), 'manacore_trailer_url', true );
		}

		$title = (string) $atts['title'];
		if ( '' === $title && $atts['id'] ) {
			$title = get_the_title( absint( $atts['id'] ) );
		}

		/*
		 * در صفحه‌ی پخش، نام اثر باید دیده شود؛ پیش‌فرض نمایش عنوان فقط
		 * وقتی است که شناسه از کوئری آمده باشد (خودِ صفحه‌ی پخش) تا در
		 * جاهای دیگر رفتار قبلی دست‌نخورده بماند.
		 */
		$show_title = '' === $atts['show_title']
			? (bool) self::watched_id()
			: rest_sanitize_boolean( $atts['show_title'] );

		$html = self::render( $url, $title );

		if ( $html && $show_title ) {
			$html = '<h2 class="manacore-player-title">' . esc_html( $title ) . '</h2>' . $html;
		}

		if ( '' === $html ) {
			return '<p class="manacore-empty-state">'
				. esc_html__( 'برای این اثر تریلری ثبت نشده است.', 'manacore' )
				. '</p>';
		}

		return $html;
	}

	/**
	 * انواع محتوایی که می‌توانند در صفحه‌ی پخش باز شوند.
	 *
	 * `episode` هم اضافه شده است: جدول دانلود هر قسمت، دکمه‌ی «پخش» را با
	 * شناسه‌ی خودِ قسمت می‌سازد (`variant_cells()`)، پس اگر این نوع پذیرفته
	 * نشود، `watched_id()` صفر می‌دهد و صفحه‌ی پخش به‌جای قسمت، برگه‌ی
	 * پخش را به‌عنوان هدف می‌گیرد و خالی می‌ماند.
	 *
	 * @return string[]
	 */
	public static function playable_post_types() {
		return (array) apply_filters(
			'manacore_playable_post_types',
			array_merge( manacore_title_post_types(), array( 'episode' ) )
		);
	}

	/**
	 * شناسه‌ی اثری که صفحه‌ی پخش باید نشان دهد (`?manacore_id=`).
	 *
	 * @return int صفر اگر نشانی معتبر نباشد.
	 */
	public static function watched_id() {
		if ( ! isset( $_GET['manacore_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}

		$post_id = absint( wp_unslash( $_GET['manacore_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return 0;
		}

		return in_array( get_post_type( $post_id ), self::playable_post_types(), true ) ? $post_id : 0;
	}

	/**
	 * دارد‌بودن منبع پخش (لینک) برای یک پست.
	 *
	 * هر پست یک‌بار در هر درخواست سنجیده می‌شود؛ چون صفحه‌ی پخش و جدول
	 * دانلود ممکن است چند بار همین پرسش را بپرسند.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return bool
	 */
	public static function has_sources( $post_id ) {
		static $cache = array();

		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}

		if ( ! isset( $cache[ $post_id ] ) ) {
			$cache[ $post_id ] = class_exists( __NAMESPACE__ . '\\Links' )
				? \ManaCore\Core\Links::count( $post_id ) > 0
				: false;
		}

		return (bool) $cache[ $post_id ];
	}

	/**
	 * پستی که واقعاً باید پخش شود.
	 *
	 * چرا لازم است؟ روش متعارف سایت‌های سریالی این است که لینک‌ها روی
	 * **قسمت‌ها** ثبت شوند، نه روی خودِ سریال. پیش از این، دکمه‌ی «پخش»
	 * هر قسمت به `?manacore_id=<سریال>` می‌رفت و پلیر منابع را از پست
	 * سریال می‌خواند؛ نتیجه یک صفحه‌ی پخش خالی بود.
	 *
	 * اکنون هدف با اولویت «هر پستی که لینک دارد» انتخاب می‌شود:
	 *   ۱) خودِ پست اگر لینک دارد،
	 *   ۲) قسمتِ خواسته‌شده (فصل/قسمت) اگر لینک دارد،
	 *   ۳) والدِ سریال اگر لینک دارد،
	 *   ۴) نخستین قسمتِ همان فصل که لینک دارد،
	 *   ۵) در نهایت خودِ پست (تا پلیر پیام حالت خالی بدهد، نه صفحه‌ی سفید).
	 *
	 * @param int $post_id شناسه‌ی اثر/قسمت.
	 * @param int $season  شماره‌ی فصل (۰ = هر فصل).
	 * @param int $episode شماره‌ی قسمت (۰ = هر قسمت).
	 * @return int شناسه‌ی هدف یا صفر.
	 */
	public static function resolve_target( $post_id, $season = 0, $episode = 0 ) {
		$post_id = (int) $post_id;
		$season  = max( 0, (int) $season );
		$episode = max( 0, (int) $episode );

		if ( ! $post_id || ! in_array( get_post_type( $post_id ), self::playable_post_types(), true ) ) {
			return 0;
		}

		if ( 'episode' === get_post_type( $post_id ) ) {
			if ( self::has_sources( $post_id ) ) {
				return $post_id;
			}

			/*
			 * قسمت بدون لینک: اگر خودِ سریال یا قسمت دیگری از همان فصل
			 * لینک داشته باشد، همان پخش می‌شود؛ وگرنه خودِ قسمت برمی‌گردد
			 * تا صفحه‌ی پخش پیام «لینکی ثبت نشده» بدهد، نه صفحه‌ی خالی.
			 */
			$parent = (int) get_post_meta( $post_id, 'manacore_parent_title', true );

			if ( $parent ) {
				$season  = $season ? $season : (int) get_post_meta( $post_id, 'manacore_season_number', true );
				$episode = $episode ? $episode : (int) get_post_meta( $post_id, 'manacore_episode_number', true );

				$resolved = self::resolve_target( $parent, $season, $episode );

				if ( $resolved ) {
					return $resolved;
				}
			}

			return $post_id;
		}

		if ( ! in_array( get_post_type( $post_id ), manacore_serial_post_types(), true ) ) {
			return $post_id;
		}

		$wanted = self::find_episode( $post_id, $season, $episode );

		if ( $wanted && self::has_sources( $wanted ) ) {
			return $wanted;
		}

		if ( self::has_sources( $post_id ) ) {
			return $post_id;
		}

		if ( $wanted ) {
			return $wanted;
		}

		$fallback = self::find_episode( $post_id, $season, 0, true );

		return $fallback ? $fallback : $post_id;
	}

	/**
	 * یافتن قسمتِ یک سریال با شماره‌ی فصل/قسمت.
	 *
	 * @param int  $series_id  شناسه‌ی سریال/انیمه.
	 * @param int  $season      شماره‌ی فصل (۰ = بی‌اهمیت).
	 * @param int  $episode     شماره‌ی قسمت (۰ = بی‌اهمیت).
	 * @param bool $require_src فقط قسمت‌هایی که لینک دارند.
	 * @return int صفر اگر پیدا نشد.
	 */
	protected static function find_episode( $series_id, $season = 0, $episode = 0, $require_src = false ) {
		$meta_query = array(
			array(
				'key'   => 'manacore_parent_title',
				'value' => (int) $series_id,
			),
		);

		if ( $season ) {
			$meta_query[] = array(
				'key'   => 'manacore_season_number',
				'value' => (int) $season,
			);
		}

		if ( $episode ) {
			$meta_query[] = array(
				'key'   => 'manacore_episode_number',
				'value' => (int) $episode,
			);
		}

		$ids = get_posts(
			array(
				'post_type'        => 'episode',
				'post_status'      => 'publish',
				'posts_per_page'   => $require_src ? 50 : 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => 'manacore_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'          => array( 'meta_value_num' => 'ASC' ),
				'meta_query'       => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		foreach ( (array) $ids as $id ) {
			if ( ! $require_src || self::has_sources( $id ) ) {
				return (int) $id;
			}
		}

		return 0;
	}

	/**
	 * آیا این نشانی یک فهرست‌پخش HLS است؟
	 *
	 * پسوند `.m3u8` (با یا بی رشته‌ی پرس‌وجو) نشانه‌ی HLS است. مرورگرهای
	 * دسکتاپ آن را بومی پخش نمی‌کنند و به `hls.js` نیاز دارند، ولی
	 * Safari/iOS از خودش پخش می‌کند — همین تفکیک در
	 * `data-player-media` به جاوااسکریپت داده می‌شود.
	 *
	 * @param string $url نشانی منبع.
	 * @return bool
	 */
	public static function is_hls_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			$path = $url;
		}

		$path = preg_replace( '/[?#].*$/', '', $path );

		return (bool) preg_match( '/\.m3u8$/i', (string) $path );
	}

	/**
	 * نوع MIME مناسب برای یک منبع پخش.
	 *
	 * @param string $url نشانی.
	 * @return string نوع MIME یا رشته‌ی خالی (مرورگر خودش حدس بزند).
	 */
	public static function mime_for( $url ) {
		if ( self::is_hls_url( $url ) ) {
			return 'application/vnd.apple.mpegurl';
		}

		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );

		if ( preg_match( '/\.webm$/i', $path ) ) {
			return 'video/webm';
		}

		if ( preg_match( '/\.(mp4|m4v)$/i', $path ) ) {
			return 'video/mp4';
		}

		if ( preg_match( '/\.ogv$/i', $path ) ) {
			return 'video/ogg';
		}

		return '';
	}

	/**
	 * آیا هر یک از منبع‌ها HLS است؟
	 *
	 * @param array<string,string> $sources نقشه‌ی کیفیت → نشانی.
	 * @return bool
	 */
	public static function has_hls( $sources ) {
		foreach ( (array) $sources as $url ) {
			if ( self::is_hls_url( $url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * نشانی کتابخانه‌ی HLS (فروشده در افزونه، نه CDN).
	 *
	 * فیلتر `manacore_hls_script_url` اجازه می‌دهد سایت‌دار نسخه‌ی
	 * روزآمد یا نسخه‌ی سبک‌تر را جایگزین کند.
	 *
	 * @return string
	 */
	public static function hls_script_url() {
		$url = MANACORE_URL . 'assets/vendor/hls/hls.min.js';

		/**
		 * فیلتر نشانی کتابخانه‌ی HLS.
		 *
		 * @param string $url نشانی پیش‌فرض.
		 */
		return (string) apply_filters( 'manacore_hls_script_url', $url );
	}

	/**
	 * شناسه‌ی برگه‌ی پخش.
	 *
	 * سه لایه، به ترتیب اولویت: گزینه‌ی `manacore_watch_page` (که هنگام
	 * ساخت برگه یا انتخاب مدیر ذخیره می‌شود) → برگه‌ی نامک `watch` →
	 * فیلتر توسعه‌دهنده. نتیجه در همان درخواست کش می‌شود تا هر جدول
	 * دانلود یک `get_page_by_path` تازه نزند.
	 *
	 * @return int صفر اگر برگه‌ی پخش وجود نداشته باشد.
	 */
	public static function page_id() {
		static $page_id = null;

		if ( null !== $page_id ) {
			return $page_id;
		}

		$page_id   = 0;
		$candidate = (int) get_option( 'manacore_watch_page', 0 );

		if ( $candidate && 'page' === get_post_type( $candidate ) && 'publish' === get_post_status( $candidate ) ) {
			$page_id = $candidate;
		} else {
			$page = get_page_by_path( 'watch', OBJECT, 'page' );
			if ( $page && 'publish' === get_post_status( $page ) ) {
				$page_id = (int) $page->ID;
			}
		}

		/**
		 * فیلتر شناسه‌ی برگه‌ی پخش.
		 *
		 * @param int $page_id شناسه (۰ در نبود برگه).
		 */
		$page_id = (int) apply_filters( 'manacore_watch_page_id', $page_id );

		return $page_id;
	}

	/**
	 * گزارش سلامت برگه‌ی پخش — برای نمایش در پنل مدیریت.
	 *
	 * @return array<string,mixed>
	 */
	public static function page_status() {
		$page_id = self::page_id();

		$status = array(
			'id'        => $page_id,
			'exists'    => false,
			'published' => false,
			'url'       => '',
			'edit_url'  => '',
			'has_block' => false,
			'option'    => (int) get_option( 'manacore_watch_page', 0 ),
		);

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return $status;
		}

		$status['exists']    = true;
		$status['published'] = 'publish' === get_post_status( $page_id );
		$status['url']        = (string) get_permalink( $page_id );
		$status['edit_url']   = (string) get_edit_post_link( $page_id, 'raw' );
		$status['has_block']  = false !== strpos( (string) get_post_field( 'post_content', $page_id ), 'manacore/player-page' );

		return $status;
	}

	/**
	 * ساخت برگه‌ی پخش در صورت نبودن (از پنل مدیریت یا فعال‌سازی).
	 *
	 * @return int شناسه‌ی برگه یا صفر.
	 */
	public static function create_page() {
		$existing = self::page_id();

		if ( $existing ) {
			update_option( 'manacore_watch_page', (int) $existing );
			return (int) $existing;
		}

		$page = get_page_by_path( 'watch', OBJECT, 'page' );

		if ( $page ) {
			update_option( 'manacore_watch_page', (int) $page->ID );
			return (int) $page->ID;
		}

		/*
		 * برگه بدون محتوا ساخته می‌شود و چیدمان از قالب `page-watch` قالب
		 * فعال می‌آید (بلوک `manacore/player-page`)؛ شورت‌کد
		 * `[manacore_player]` همچنان برای قالب‌هایی که این قالب را ندارند
		 * کار می‌کند و در «راهنمای پخش» برگه آمده است.
		 */
		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'پخش آنلاین', 'manacore' ),
				'post_name'    => 'watch',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'manacore_watch_page', (int) $page_id );
			return (int) $page_id;
		}

		return 0;
	}

	/**
	 * ساخت یک‌باره‌ی صفحه‌ی پخش (`/watch/`) هنگام فعال‌سازی افزونه.
	 *
	 * تنها اگر چنین صفحه‌ای نباشد ساخته می‌شود؛ صفحه‌ی موجود هرگز بازنویسی
	 * نمی‌شود تا کاربر آزاد باشد قالب یا متن آن را دلخواه تغییر دهد.
	 */
	public static function ensure_page() {
		self::create_page();
	}

	/**
	 * آدرس «صفحه‌ی پخش» برای یک اثر.
	 *
	 * صفحه‌ی پخش با گزینه‌ی `manacore_watch_page` (و در نبودش نامک `watch`)
	 * پیدا می‌شود؛ اگر چنین صفحه‌ای نباشد یا منتشر نشده باشد، رشته‌ی خالی
	 * برمی‌گردد تا فراخوان‌ها به ساز‌و‌کار جانشین (مُدال پخش) برگردند و
	 * پیوندِ مرده ساخته نشود.
	 *
	 * @param int   $post_id شناسه‌ی اثر.
	 * @param array $args    پارامترهای افزوده (مثل `quality`/`season`/`episode`).
	 * @return string آدرس یا رشته‌ی خالی.
	 */
	public static function page_url( $post_id = 0, $args = array() ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		/* صفحه‌ی پخش فقط برای بازدیدکننده‌ی عادی معنا دارد، نه پیش‌نمایش ویرایشگر. */
		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		$url = self::url_for( $post_id, $args );

		return $url;
	}

	/**
	 * ساخت نشانی پخش بدون بررسی حالت مدیریت.
	 *
	 * @param int   $post_id شناسه‌ی اثر/قسمت.
	 * @param array $args    پارامترهای افزوده.
	 * @return string آدرس یا رشته‌ی خالی.
	 */
	public static function url_for( $post_id, $args = array() ) {
		$post_id = (int) $post_id;
		$page_id = self::page_id();

		if ( ! $post_id || ! $page_id ) {
			return '';
		}

		$base = (string) get_permalink( $page_id );

		if ( '' === $base ) {
			return '';
		}

		$args = array_filter(
			(array) $args,
			static function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);

		$url = (string) add_query_arg( array_merge( array( 'manacore_id' => $post_id ), $args ), $base );

		/**
		 * فیلتر نشانی نهایی صفحه‌ی پخش.
		 *
		 * @param string $url     نشانی ساخته‌شده.
		 * @param int    $post_id شناسه‌ی اثر/قسمت.
		 * @param array  $args    پارامترهای افزوده.
		 */
		return (string) apply_filters( 'manacore_watch_url', $url, $post_id, $args );
	}

	/**
	 * مودال پخش سراسری.
	 */
	public function modal() {
		if ( is_admin() ) {
			return;
		}
		?>
		<div class="manacore-modal" id="manacore-player-modal" hidden aria-hidden="true" role="dialog" aria-modal="true">
			<div class="manacore-modal-backdrop" data-manacore-close></div>
			<div class="manacore-modal-box">
				<header class="manacore-modal-head">
					<h2 class="manacore-modal-title" data-modal-title><?php esc_html_e( 'پخش', 'manacore' ); ?></h2>
					<button type="button" class="manacore-modal-cinema" data-manacore-cinema aria-pressed="false">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<rect width="18" height="12" x="3" y="6" rx="2"/>
							<path d="M4 10h16"/>
						</svg>
						<?php esc_html_e( 'سینما', 'manacore' ); ?>
					</button>
					<button type="button" class="manacore-modal-close" data-manacore-close
						aria-label="<?php esc_attr_e( 'بستن', 'manacore' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
							<path fill="currentColor" d="M19 6.4L17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/>
						</svg>
					</button>
				</header>
				<div class="manacore-modal-body" data-modal-body></div>
			</div>
		</div>
		<?php
	}
}

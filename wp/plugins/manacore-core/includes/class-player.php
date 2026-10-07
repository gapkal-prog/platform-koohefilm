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
	 * آدرس «صفحه‌ی پخش» برای یک اثر.
	 *
	 * صفحه‌ای با نامک `watch` میزبان شورت‌کد `[manacore_player]` است؛ اگر
	 * چنین صفحه‌ای ساخته نشده باشد، رشته‌ی خالی برمی‌گردد تا فراخوان‌ها
	 * به ساز‌و‌کار جانشین (مُدال پخش) برگردند و پیوندِ مرده ساخته نشود.
	 *
	 * @param int $post_id شناسه‌ی اثر.
	 * @return string آدرس یا رشته‌ی خالی.
	 */
	public static function watched_id() {
		if ( ! isset( $_GET['manacore_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}

		$post_id = absint( wp_unslash( $_GET['manacore_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return 0;
		}

		return in_array( get_post_type( $post_id ), manacore_title_post_types(), true ) ? $post_id : 0;
	}

	/**
	 * ساخت یک‌باره‌ی صفحه‌ی پخش (`/watch/`) هنگام فعال‌سازی افزونه.
	 *
	 * تنها اگر چنین صفحه‌ای نباشد ساخته می‌شود؛ صفحه‌ی موجود هرگز بازنویسی
	 * نمی‌شود تا کاربر آزاد باشد قالب یا متن آن را دلخواه تغییر دهد.
	 */
	public static function ensure_page() {
		if ( get_page_by_path( 'watch', OBJECT, 'page' ) ) {
			return;
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
		}
	}

	public static function page_url( $post_id = 0 ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$page = get_page_by_path( 'watch', OBJECT, 'page' );
		/* صفحه‌ی پخش فقط برای بازدیدکننده‌ی عادی معنا دارد، نه پیش‌نمایش ویرایشگر. */
		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}
		if ( ! $page || 'publish' !== get_post_status( $page ) ) {
			return '';
		}

		return (string) add_query_arg( 'manacore_id', $post_id, get_permalink( $page ) );
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

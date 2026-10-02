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
				'url'   => '',
				'title' => '',
				'id'    => 0,
			),
			$atts,
			'manacore_player'
		);

		$url = $atts['url'];
		if ( ! $url && $atts['id'] ) {
			$url = get_post_meta( absint( $atts['id'] ), 'manacore_trailer_url', true );
		}
		if ( ! $url && is_singular() ) {
			$url = get_post_meta( get_the_ID(), 'manacore_trailer_url', true );
		}

		return self::render( $url, $atts['title'] );
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

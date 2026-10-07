<?php
/**
 * جایگاه‌های تبلیغاتی بنری.
 *
 * بنرها یک نوع محتوای خصوصی (`manacore_ad`) هستند تا مدیر همان ابزارهای
 * آشنای وردپرس (کتابخانه‌ی رسانه، پیش‌نویس، زمان‌بندی انتشار) را داشته
 * باشد؛ ولی خودِ بنر محتوای سایت نیست و در هیچ آرشیوی دیده نمی‌شود.
 *
 * هر بنر:
 * - یک یا چند «جایگاه» دارد (بالای صفحه، پیش/پس از محتوا، پیش از پلیر)،
 * - می‌تواند زمان‌بندی شروع/پایان داشته باشد،
 * - وزن دارد (فیلد «ترتیب» خود وردپرس) و در هر جایگاه بر پایه‌ی وزن
 *   چیده می‌شود،
 * - شمار نمایش و کلیک را نگه می‌دارد تا مدیر بازده هر بنر را ببیند.
 *
 * شمارش سمت کاربر با یک «بادکننده» (beacon) انجام می‌شود، نه در رندر
 * سمت سرور: صفحات سایت کش می‌شوند و شمارش هنگام رندر، عددی بی‌معنا
 * می‌داد. کلیک هم روی همان مسیر REST ثبت می‌شود تا رفتن کاربر به مقصد
 * هیچ‌وقت کند یا مسدود نشود.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ads
 */
class Ads {

	use Singleton;

	/**
	 * نوع محتوای بنر.
	 */
	const POST_TYPE = 'manacore_ad';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );

		add_shortcode( 'manacore_ad', array( $this, 'shortcode' ) );

		/* جای‌گذاری خودکار در جایگاه‌های برگزیده. */
		add_action( 'wp_body_open', array( $this, 'render_top' ), 5 );
		add_filter( 'the_content', array( $this, 'inject_content' ), 20 );
		add_action( 'manacore_before_player', array( $this, 'render_before_player' ) );

		/* ستون‌های پیشخوان */
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	/* ---------------------------------------------------------------------
	 * ثبت نوع محتوا و جایگاه‌ها
	 * ------------------------------------------------------------------ */

	/**
	 * ثبت نوع محتوای بنر.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'تبلیغات', 'manacore' ),
					'singular_name' => __( 'بنر تبلیغاتی', 'manacore' ),
					'menu_name'     => __( 'تبلیغات', 'manacore' ),
					'all_items'     => __( 'همه‌ی بنرها', 'manacore' ),
					'add_new'       => __( 'بنر تازه', 'manacore' ),
					'add_new_item'  => __( 'افزودن بنر تبلیغاتی', 'manacore' ),
					'edit_item'     => __( 'ویرایش بنر', 'manacore' ),
					'new_item'      => __( 'بنر تازه', 'manacore' ),
					'search_items'  => __( 'جست‌وجوی بنرها', 'manacore' ),
					'not_found'     => __( 'بنری پیدا نشد.', 'manacore' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'manacore',
				'show_in_rest'        => false,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-megaphone',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
			)
		);
	}

	/**
	 * جایگاه‌های تبلیغاتی.
	 *
	 * @return array<string,string>
	 */
	public static function positions() {
		/**
		 * فهرست جایگاه‌های تبلیغاتی.
		 *
		 * @param array<string,string> $positions نگاشت کلید به برچسب.
		 */
		return apply_filters(
			'manacore_ad_positions',
			array(
				'top'            => __( 'بالای صفحه (پیش از سربرگ)', 'manacore' ),
				'before-content' => __( 'پیش از محتوای نوشته', 'manacore' ),
				'after-content'  => __( 'پس از محتوای نوشته', 'manacore' ),
				'before-player'  => __( 'پیش از پخش‌کننده', 'manacore' ),
			)
		);
	}

	/**
	 * کلید متای جایگاه‌ها.
	 */
	const POSITIONS_META = 'manacore_ad_positions';

	/**
	 * تنظیمات ماژول تبلیغات.
	 *
	 * @return array
	 */
	public static function settings() {
		$all_positions = array_keys( self::positions() );
		$positions     = manacore_get_option( 'ads_positions', $all_positions );
		$positions     = is_array( $positions ) ? array_values( array_intersect( array_map( 'sanitize_key', $positions ), $all_positions ) ) : $all_positions;

		$settings = array(
			'enabled'      => (bool) manacore_get_option( 'ads_enabled', 0 ),
			'label'        => (string) manacore_get_option( 'ads_label', __( 'تبلیغ', 'manacore' ) ),
			'hide_members' => (bool) manacore_get_option( 'ads_hide_members', 0 ),
			'counters'     => (bool) manacore_get_option( 'ads_counters', 1 ),
			'per_position' => max( 1, min( 3, (int) manacore_get_option( 'ads_per_position', 1 ) ) ),
			'positions'    => $positions,
		);

		/**
		 * تنظیمات ماژول تبلیغات.
		 *
		 * @param array $settings تنظیمات.
		 */
		return apply_filters( 'manacore_ad_settings', $settings );
	}

	/**
	 * آیا این جایگاه در تنظیمات روشن است؟
	 *
	 * @param string $position کلید جایگاه.
	 * @return bool
	 */
	public static function position_enabled( $position ) {
		$settings = self::settings();

		return $settings['enabled'] && in_array( $position, $settings['positions'], true );
	}

	/* ---------------------------------------------------------------------
	 * انتخاب و رندر بنر
	 * ------------------------------------------------------------------ */

	/**
	 * بنرهای فعال یک جایگاه.
	 *
	 * @param string $position جایگاه.
	 * @return int[] شناسه‌ی بنرها (به ترتیب وزن).
	 */
	public static function active_ads( $position ) {
		$settings = self::settings();
		$position = sanitize_key( $position );
		$today    = current_time( 'Y-m-d' );

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				/* وزن با فیلد «ترتیب» خود وردپرس نگه داشته می‌شود. */
				'orderby'        => array(
					'menu_order' => 'DESC',
					'date'       => 'DESC',
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'manacore_ad_active',
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => self::POSITIONS_META,
						'value'   => '"' . $position . '"',
						'compare' => 'LIKE',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'manacore_ad_start',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'manacore_ad_start',
							'value'   => $today,
							'compare' => '<=',
							'type'    => 'DATE',
						),
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'manacore_ad_end',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'manacore_ad_end',
							'value'   => $today,
							'compare' => '>=',
							'type'    => 'DATE',
						),
					),
				),
			)
		);

		$ids = array_map( 'absint', (array) $query->posts );

		/* بنرهای هم‌وزن به‌صورت تصادفی چیده می‌شوند تا نمایش عادلانه بماند. */
		usort(
			$ids,
			static function ( $a, $b ) {
				$wa = (int) get_post_field( 'menu_order', $a );
				$wb = (int) get_post_field( 'menu_order', $b );

				if ( $wa === $wb ) {
					return wp_rand( -1, 1 );
				}

				return $wb <=> $wa;
			}
		);

		$ids = array_slice( $ids, 0, $settings['per_position'] );

		/**
		 * بنرهای یک جایگاه.
		 *
		 * @param int[]  $ids      شناسه‌ی بنرها.
		 * @param string $position جایگاه.
		 */
		return apply_filters( 'manacore_active_ads', $ids, $position );
	}

	/**
	 * رندر بنرهای یک جایگاه.
	 *
	 * @param string $position جایگاه.
	 * @param array  $args     بازنویسی‌ها (label، layout).
	 * @return string
	 */
	public static function render( $position, $args = array() ) {
		$position = sanitize_key( $position );
		$settings = self::settings();
		$args     = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'label'  => $settings['label'],
				'layout' => 'auto',
			)
		);

		if ( ! $settings['enabled'] || ! in_array( $position, $settings['positions'], true ) ) {
			return '';
		}

		/* اعضای ویژه با تنظیم «پنهان از اعضا» بنر نمی‌بینند. */
		if ( $settings['hide_members'] && is_user_logged_in() ) {
			return '';
		}

		$ids = self::active_ads( $position );

		if ( ! $ids ) {
			return '';
		}

		$out = '';

		foreach ( $ids as $ad_id ) {
			$out .= self::markup( $ad_id, $position, $args, $settings );
		}

		if ( '' === $out ) {
			return '';
		}

		return sprintf(
			'<div class="manacore-ad-slot manacore-ad-slot--%s" data-ad-slot="%s">%s</div>',
			esc_attr( $position ),
			esc_attr( $position ),
			$out
		);
	}

	/**
	 * مارک‌آپ یک بنر.
	 *
	 * @param int    $ad_id    شناسه.
	 * @param string $position جایگاه.
	 * @param array  $args     بازنویسی‌ها.
	 * @param array  $settings تنظیمات.
	 * @return string
	 */
	protected static function markup( $ad_id, $position, $args, $settings ) {
		$image = (string) get_post_meta( $ad_id, 'manacore_ad_image', true );

		if ( '' === $image ) {
			$image = (string) get_the_post_thumbnail_url( $ad_id, 'full' );
		}

		/* بنر بی‌تصویر رندر نمی‌شود (جای خالی و کادر شکسته نمی‌سازیم). */
		if ( '' === $image ) {
			return '';
		}

		$url    = (string) get_post_meta( $ad_id, 'manacore_ad_url', true );
		$target = '_self' === get_post_meta( $ad_id, 'manacore_ad_target', true ) ? '_self' : '_blank';
		$alt    = (string) get_post_meta( $ad_id, 'manacore_ad_alt', true );
		$label  = (string) $args['label'];
		$title  = get_the_title( $ad_id );

		if ( '' === $alt ) {
			$alt = $title;
		}

		$attrs = sprintf(
			' class="manacore-ad manacore-ad--%1$s"%2$s',
			esc_attr( $position ),
			$settings['counters']
				? ' data-manacore-ad data-ad-id="' . esc_attr( (string) $ad_id ) . '"'
				: ''
		);

		$media = sprintf(
			'<img src="%1$s" alt="%2$s" loading="lazy" decoding="async" />',
			esc_url( $image ),
			esc_attr( $alt )
		);

		if ( '' !== $url ) {
			$inner = sprintf(
				'<a class="manacore-ad__link" href="%s" target="%s" rel="noopener noreferrer nofollow sponsored"%s>%s</a>',
				esc_url( $url ),
				esc_attr( $target ),
				$settings['counters'] ? ' data-manacore-ad-link data-ad-id="' . esc_attr( (string) $ad_id ) . '"' : '',
				$media
			);
		} else {
			$inner = $media;
		}

		$output = sprintf(
			'<aside%1$s>%2$s%3$s</aside>',
			$attrs,
			$inner,
			'' !== $label ? '<span class="manacore-ad__label">' . esc_html( $label ) . '</span>' : ''
		);

		/**
		 * مارک‌آپ یک بنر تبلیغاتی.
		 *
		 * @param string $output   مارک‌آپ.
		 * @param int    $ad_id    شناسه‌ی بنر.
		 * @param string $position جایگاه.
		 */
		return (string) apply_filters( 'manacore_ad_markup', $output, $ad_id, $position );
	}

	/**
	 * رندر جایگاه «بالای صفحه».
	 */
	public function render_top() {
		echo self::render( 'top' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * رندر جایگاه «پیش از پلیر».
	 *
	 * @param int $post_id شناسه‌ی اثری که پلیرش رندر می‌شود.
	 */
	public function render_before_player( $post_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		echo self::render( 'before-player' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * تزریق بنر پیش/پس از محتوای یکتایی‌ها.
	 *
	 * @param string $content محتوا.
	 * @return string
	 */
	public function inject_content( $content ) {
		if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$before = self::render( 'before-content' );
		$after  = self::render( 'after-content' );

		if ( '' === $before && '' === $after ) {
			return $content;
		}

		return $before . $content . $after;
	}

	/**
	 * شورت‌کد بنر.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'position' => 'before-content',
				'label'    => '',
			),
			is_array( $atts ) ? $atts : array(),
			'manacore_ad'
		);

		return self::render(
			$atts['position'],
			array(
				'label' => '' !== $atts['label'] ? $atts['label'] : null,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * آمار نمایش/کلیک
	 * ------------------------------------------------------------------ */

	/**
	 * ثبت مسیر REST رویداد بنر.
	 */
	public function register_route() {
		register_rest_route(
			Rest_Api::NS,
			'/ad-event',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_event' ),
				'permission_callback' => array( Rest_Api::instance(), 'verify_public_write' ),
				'args'                => array(
					'ad_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'event' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'impression', 'click' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * پاسخ مسیر رویداد.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_event( $request ) {
		$result = self::record_event( (int) $request->get_param( 'ad_id' ), (string) $request->get_param( 'event' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * ثبت یک رویداد (نمایش یا کلیک) روی بنر.
	 *
	 * @param int    $ad_id شناسه‌ی بنر.
	 * @param string $event نوع رویداد.
	 * @return array|\WP_Error
	 */
	public static function record_event( $ad_id, $event ) {
		$settings = self::settings();

		if ( ! $settings['enabled'] || ! $settings['counters'] ) {
			return new \WP_Error(
				'manacore_ad_off',
				__( 'شمارش تبلیغات خاموش است.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		if ( ! in_array( $event, array( 'impression', 'click' ), true ) ) {
			return new \WP_Error(
				'manacore_ad_event',
				__( 'رویداد نامعتبر است.', 'manacore' ),
				array( 'status' => 400 )
			);
		}

		$ad_id = absint( $ad_id );

		if ( self::POST_TYPE !== get_post_type( $ad_id ) || 'publish' !== get_post_status( $ad_id ) ) {
			return new \WP_Error(
				'manacore_ad_missing',
				__( 'این بنر در دسترس نیست.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		/* هر بازدیدکننده در هر بازه یک بار (نمایش ۳۰ ثانیه، کلیک ۵ ثانیه). */
		$ttl     = 'click' === $event ? 5 : 30;
		$key     = 'manacore_ad_' . $event . '_' . $ad_id . '_' . md5( manacore_visitor_hash( 'ad' ) );
		$limited = (bool) get_transient( $key );

		if ( ! $limited ) {
			set_transient( $key, 1, $ttl );
			self::bump( $ad_id, $event );
		}

		return array(
			'ok'      => true,
			'ad_id'   => $ad_id,
			'event'   => $event,
			'counted' => ! $limited,
		);
	}

	/**
	 * افزودن یک شماره به شمارنده‌ی بنر.
	 *
	 * @param int    $ad_id شناسه‌ی بنر.
	 * @param string $event نوع رویداد.
	 */
	protected static function bump( $ad_id, $event ) {
		$meta = 'click' === $event ? 'manacore_ad_clicks' : 'manacore_ad_impressions';
		$now  = (int) get_post_meta( $ad_id, $meta, true );

		update_post_meta( $ad_id, $meta, $now + 1 );
	}

	/**
	 * آمار یک بنر.
	 *
	 * @param int $ad_id شناسه‌ی بنر.
	 * @return array<string,int>
	 */
	public static function stats( $ad_id ) {
		$impressions = (int) get_post_meta( $ad_id, 'manacore_ad_impressions', true );
		$clicks      = (int) get_post_meta( $ad_id, 'manacore_ad_clicks', true );

		return array(
			'impressions' => $impressions,
			'clicks'      => $clicks,
			'ctr'         => $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0.0,
		);
	}

	/**
	 * وضعیت زمان‌بندی بنر.
	 *
	 * @param int $ad_id شناسه‌ی بنر.
	 * @return string یکی از active|scheduled|expired|paused
	 */
	public static function state( $ad_id ) {
		if ( '1' !== (string) get_post_meta( $ad_id, 'manacore_ad_active', true ) ) {
			return 'paused';
		}

		$today = current_time( 'Y-m-d' );
		$start = (string) get_post_meta( $ad_id, 'manacore_ad_start', true );
		$end   = (string) get_post_meta( $ad_id, 'manacore_ad_end', true );

		if ( $start && $start > $today ) {
			return 'scheduled';
		}

		if ( $end && $end < $today ) {
			return 'expired';
		}

		return 'active';
	}

	/* ---------------------------------------------------------------------
	 * پیشخوان
	 * ------------------------------------------------------------------ */

	/**
	 * جعبه‌ی تنظیمات بنر.
	 */
	public function metabox() {
		add_meta_box(
			'manacore_ad_details',
			__( 'تنظیمات بنر', 'manacore' ),
			array( $this, 'render_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * رندر جعبه‌ی تنظیمات بنر.
	 *
	 * @param \WP_Post $post پست.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'manacore_ad_save_' . $post->ID, 'manacore_ad_nonce' );

		$image     = (string) get_post_meta( $post->ID, 'manacore_ad_image', true );
		$url       = (string) get_post_meta( $post->ID, 'manacore_ad_url', true );
		$target    = (string) get_post_meta( $post->ID, 'manacore_ad_target', true );
		$alt       = (string) get_post_meta( $post->ID, 'manacore_ad_alt', true );
		$start     = (string) get_post_meta( $post->ID, 'manacore_ad_start', true );
		$end       = (string) get_post_meta( $post->ID, 'manacore_ad_end', true );
		$active    = (string) get_post_meta( $post->ID, 'manacore_ad_active', true );
		$positions = (array) get_post_meta( $post->ID, self::POSITIONS_META, true );
		$stats     = self::stats( $post->ID );

		if ( '' === $active && 'auto-draft' === $post->post_status ) {
			$active = '1';
		}
		?>
		<style>
			.manacore-ad-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin:8px 0}
			.manacore-ad-field{display:flex;flex-direction:column;gap:6px}
			.manacore-ad-field > span{font-weight:600}
			.manacore-ad-positions{display:flex;flex-wrap:wrap;gap:12px}
			.manacore-ad-stats{display:flex;gap:22px;margin:10px 0;padding:10px 14px;background:#f6f7f7;border-radius:8px}
			.manacore-ad-stats b{display:block;font-size:1.3rem}
		</style>

		<div class="manacore-ad-grid">
			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'نشانی تصویر بنر', 'manacore' ); ?></span>
				<input type="text" dir="ltr" id="manacore_ad_image" name="manacore_ad_image" class="widefat" value="<?php echo esc_attr( $image ); ?>"
					placeholder="<?php esc_attr_e( 'خالی بگذارید تا از تصویر شاخص استفاده شود', 'manacore' ); ?>" />
				<span>
					<button type="button" class="button" data-manacore-ad-media><?php esc_html_e( 'انتخاب از کتابخانه', 'manacore' ); ?></button>
				</span>
			</label>

			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'نشانی مقصد (کلیک)', 'manacore' ); ?></span>
				<input type="url" dir="ltr" name="manacore_ad_url" class="widefat" value="<?php echo esc_attr( $url ); ?>" placeholder="https://" />
			</label>

			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'متن جانشین (alt)', 'manacore' ); ?></span>
				<input type="text" name="manacore_ad_alt" class="widefat" value="<?php echo esc_attr( $alt ); ?>"
					placeholder="<?php esc_attr_e( 'خالی بماند = نام بنر', 'manacore' ); ?>" />
			</label>

			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'باز شدن در', 'manacore' ); ?></span>
				<select name="manacore_ad_target">
					<option value="_blank" <?php selected( $target, '_blank' ); ?>><?php esc_html_e( 'پنجره‌ی تازه', 'manacore' ); ?></option>
					<option value="_self" <?php selected( $target, '_self' ); ?>><?php esc_html_e( 'همان صفحه', 'manacore' ); ?></option>
				</select>
			</label>

			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'شروع نمایش', 'manacore' ); ?></span>
				<input type="date" name="manacore_ad_start" value="<?php echo esc_attr( $start ); ?>" />
			</label>

			<label class="manacore-ad-field">
				<span><?php esc_html_e( 'پایان نمایش', 'manacore' ); ?></span>
				<input type="date" name="manacore_ad_end" value="<?php echo esc_attr( $end ); ?>" />
			</label>
		</div>

		<p><strong><?php esc_html_e( 'جایگاه‌ها', 'manacore' ); ?></strong></p>
		<div class="manacore-ad-positions">
			<?php foreach ( self::positions() as $key => $label ) : ?>
				<label>
					<input type="checkbox" name="manacore_ad_positions[]" value="<?php echo esc_attr( $key ); ?>"
						<?php checked( in_array( $key, $positions, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<p>
			<label>
				<input type="checkbox" name="manacore_ad_active" value="1" <?php checked( '1', (string) $active ); ?> />
				<?php esc_html_e( 'بنر فعال است', 'manacore' ); ?>
			</label>
		</p>

		<p class="description">
			<?php esc_html_e( 'برای چیدن چند بنر در یک جایگاه، از فیلد «ترتیب» در ستون کنار (بخش انتشار) استفاده کنید؛ عدد بزرگ‌تر زودتر دیده می‌شود.', 'manacore' ); ?>
		</p>

		<div class="manacore-ad-stats">
			<span><b><?php echo esc_html( number_format_i18n( $stats['impressions'] ) ); ?></b><?php esc_html_e( 'نمایش', 'manacore' ); ?></span>
			<span><b><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></b><?php esc_html_e( 'کلیک', 'manacore' ); ?></span>
			<span><b><?php echo esc_html( number_format_i18n( $stats['ctr'], 2 ) ); ?>٪</b><?php esc_html_e( 'نرخ کلیک', 'manacore' ); ?></span>
		</div>
		<?php
	}

	/**
	 * ذخیره‌ی متای بنر.
	 *
	 * @param int      $post_id شناسه‌ی پست.
	 * @param \WP_Post $post    پست.
	 */
	public function save_meta( $post_id, $post = null ) {
		if ( ! $post || 'revision' === $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['manacore_ad_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['manacore_ad_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'manacore_ad_save_' . $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/* متن‌ها */
		$image = isset( $_POST['manacore_ad_image'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['manacore_ad_image'] ) ) ) : '';
		$url   = isset( $_POST['manacore_ad_url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['manacore_ad_url'] ) ) ) : '';
		$alt   = isset( $_POST['manacore_ad_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['manacore_ad_alt'] ) ) : '';

		self::update_or_delete( $post_id, 'manacore_ad_image', $image );
		self::update_or_delete( $post_id, 'manacore_ad_url', $url );
		self::update_or_delete( $post_id, 'manacore_ad_alt', $alt );

		$target = isset( $_POST['manacore_ad_target'] ) && '_self' === $_POST['manacore_ad_target'] ? '_self' : '_blank';
		update_post_meta( $post_id, 'manacore_ad_target', $target );

		/* تاریخ‌ها: فقط تاریخ معتبر نگه داشته می‌شود. */
		foreach ( array( 'manacore_ad_start', 'manacore_ad_end' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';

			self::update_or_delete( $post_id, $key, $value );
		}

		/* جایگاه‌ها: فقط کلیدهای شناخته‌شده. */
		$allowed   = array_keys( self::positions() );
		$positions = isset( $_POST['manacore_ad_positions'] ) ? (array) wp_unslash( $_POST['manacore_ad_positions'] ) : array();
		$positions = array_values( array_intersect( array_map( 'sanitize_key', $positions ), $allowed ) );

		if ( $positions ) {
			update_post_meta( $post_id, self::POSITIONS_META, $positions );
		} else {
			delete_post_meta( $post_id, self::POSITIONS_META );
		}

		update_post_meta( $post_id, 'manacore_ad_active', empty( $_POST['manacore_ad_active'] ) ? '0' : '1' );
	}

	/**
	 * به‌روزرسانی متا یا حذف آن در صورت خالی بودن مقدار.
	 *
	 * @param int    $post_id شناسه.
	 * @param string $key     کلید متا.
	 * @param string $value   مقدار.
	 */
	protected static function update_or_delete( $post_id, $key, $value ) {
		if ( '' === (string) $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * ستون‌های فهرست بنرها.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );

		$columns['manacore_ad_positions'] = __( 'جایگاه‌ها', 'manacore' );
		$columns['manacore_ad_schedule']  = __( 'بازه‌ی نمایش', 'manacore' );
		$columns['manacore_ad_stats']     = __( 'نمایش / کلیک / نرخ', 'manacore' );
		$columns['manacore_ad_state']     = __( 'وضعیت', 'manacore' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * محتوای ستون‌های بنر.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه.
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'manacore_ad_positions':
				$positions = (array) get_post_meta( $post_id, self::POSITIONS_META, true );
				$labels    = self::positions();
				$names     = array();

				foreach ( $positions as $key ) {
					if ( isset( $labels[ $key ] ) ) {
						$names[] = $labels[ $key ];
					}
				}

				echo $names ? esc_html( implode( '، ', $names ) ) : '—';
				break;

			case 'manacore_ad_schedule':
				$start = (string) get_post_meta( $post_id, 'manacore_ad_start', true );
				$end   = (string) get_post_meta( $post_id, 'manacore_ad_end', true );

				if ( '' === $start && '' === $end ) {
					esc_html_e( 'همیشه', 'manacore' );
					break;
				}

				echo esc_html( sprintf( '%s → %s', $start ? $start : '∞', $end ? $end : '∞' ) );
				break;

			case 'manacore_ad_stats':
				$stats = self::stats( $post_id );
				printf(
					'%s / %s / %s٪',
					esc_html( manacore_fa_digits( number_format_i18n( $stats['impressions'] ) ) ),
					esc_html( manacore_fa_digits( number_format_i18n( $stats['clicks'] ) ) ),
					esc_html( manacore_fa_digits( (string) $stats['ctr'] ) )
				);
				break;

			case 'manacore_ad_state':
				$states = array(
					'active'    => __( 'فعال', 'manacore' ),
					'paused'    => __( 'خاموش', 'manacore' ),
					'scheduled' => __( 'زمان‌بندی‌شده', 'manacore' ),
					'expired'   => __( 'پایان‌یافته', 'manacore' ),
				);
				$state  = self::state( $post_id );

				printf(
					'<span class="manacore-ad-state is-%s">%s</span>',
					esc_attr( $state ),
					esc_html( isset( $states[ $state ] ) ? $states[ $state ] : $state )
				);
				break;
		}
	}

	/**
	 * اسکریپت کوچک انتخاب تصویر در صفحه‌ی ویرایش بنر.
	 *
	 * @param string $hook قلاب صفحه.
	 */
	public function admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'manacore-admin-ads',
			MANACORE_URL . 'assets/js/admin-ads.js',
			array( 'jquery' ),
			MANACORE_VERSION,
			true
		);
	}
}

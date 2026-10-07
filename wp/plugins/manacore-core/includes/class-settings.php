<?php
/**
 * صفحه‌ی تنظیمات ManaCore.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );

		/* ابزارهای تب «وضعیت و ابزارها» (نانِس + دسترسی مدیریت). */
		add_action( 'admin_post_manacore_tool', array( $this, 'handle_tool' ) );

		/* پیام «تنظیمات ذخیره شد» در تب‌ها. */
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * فهرست تب‌های صفحه‌ی تنظیمات.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'general'   => __( 'تنظیمات عمومی', 'manacore' ),
			'watch'     => __( 'پخش و دانلود', 'manacore' ),
			'requests'  => __( 'درخواست‌ها', 'manacore' ),
			'ads'       => __( 'تبلیغات', 'manacore' ),
			'mega'      => __( 'مگامنو', 'manacore' ),
			'analytics' => __( 'تحلیل و آمار', 'manacore' ),
			'tools'     => __( 'وضعیت و ابزارها', 'manacore' ),
		);
	}

	/**
	 * کلیدهای هر تب — تنها منبع حقیقت «کدام کلید به کدام تب تعلق دارد».
	 *
	 * دو مصرف‌کننده دارد: پشتیبان‌گیری/بازگردانی تنظیمات (`Portability`)
	 * که باید بداند هر کلید را با پاک‌سازی همان تب وارد کند، و آزمون
	 * نگهبان که می‌سنجد خروجی `sanitize()` هیچ کلیدی بیرون از این فهرست
	 * ندارد. اگر کلیدی تازه به `sanitize()` اضافه شد و اینجا نیامد،
	 * آزمون `test-portability.php` شکست می‌خورد.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function keys_by_tab() {
		return array(
			'general'  => array( 'slug_movie', 'slug_series', 'slug_anime', 'slug_episode', 'slug_person', 'slug_collection', 'enable_ratings', 'enable_watchlist', 'enable_views', 'links_login_only', 'items_per_page', 'default_color_mode', 'custom_qualities' ),
			'watch'    => array( 'download_notice_text', 'player_notice_text', 'subscribe_label', 'subscribe_url', 'download_signing', 'download_ttl' ),
			'requests' => array( 'requests_enabled', 'requests_guests', 'requests_show_pending', 'requests_heading', 'requests_board_title', 'requests_button', 'requests_intro', 'requests_thanks', 'requests_per_page' ),
			'ads'      => array( 'ads_enabled', 'ads_hide_members', 'ads_counters', 'ads_label', 'ads_per_position', 'ads_positions' ),
			'mega'     => array( 'mega_enabled', 'mega_show_korean', 'mega_show_cast', 'mega_eyebrow', 'mega_title', 'mega_quick_label', 'mega_feature_label', 'mega_cta_label', 'mega_rating_label', 'mega_newest_label', 'mega_korean_label', 'mega_cast_label', 'mega_taxonomy', 'mega_terms', 'mega_columns', 'mega_hub_url', 'mega_featured_id' ),
		);
	}

	/**
	 * همه‌ی کلیدهای تنظیمات، سرتخت (برای جست‌وجوی سریع در فهرست).
	 *
	 * @return array<int,string>
	 */
	public static function option_keys() {
		$keys = array();

		foreach ( self::keys_by_tab() as $tab_keys ) {
			$keys = array_merge( $keys, $tab_keys );
		}

		return $keys;
	}

	/**
	 * تب فعال (از `?tab=` با اعتبارسنجی فهرست سفید).
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = self::tabs();

		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * افزودن منو.
	 */
	public function menu() {
		add_menu_page(
			__( 'ManaCore', 'manacore' ),
			__( 'ManaCore', 'manacore' ),
			'manage_options',
			'manacore',
			array( $this, 'render' ),
			'dashicons-video-alt2',
			3
		);

		/*
		 * زیرمنوها فقط میان‌برِ تب‌های صفحه‌اند (همان `page=manacore` با
		 * پارامتر بازگشتی)، نه صفحه‌ی تکراری. پیش‌تر دو ورودی با پارامتر
		 * یکسان ساخته می‌شد که تب نخست را همیشه باز می‌کرد.
		 */
		foreach ( self::tabs() as $slug => $label ) {
			if ( 'general' === $slug ) {
				continue; // ورودی خودِ منوی اصلی همین تب است.
			}

			add_submenu_page(
				'manacore',
				$label,
				$label,
				'manage_options',
				'manacore&tab=' . $slug,
				'__return_null',
				1
			);
		}
	}

	/**
	 * ثبت تنظیمات.
	 */
	public function register() {
		register_setting(
			'manacore_settings_group',
			'manacore_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		/*
		 * برگه‌ی پخش یک شناسه‌ی مستقل است (نه بخشی از آرایه‌ی تنظیمات) تا
		 * افزونه‌های دیگر و قالب هم بتوانند مستقیم بخوانند؛ همان گزینه‌ای
		 * که `Player::page_id()` مصرف می‌کند. با این حال در **همان گروه**
		 * ثبت می‌شود تا `options.php` هر دو را در یک فرم ذخیره کند.
		 */
		register_setting(
			'manacore_settings_group',
			'manacore_watch_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * پاک‌سازی تنظیمات.
	 *
	 * صفحه‌ی تنظیمات چهار تب دارد و هر تب فرم مستقل خودش را به
	 * `options.php` می‌فرستد؛ پس فقط کلیدهای همان تب پردازش می‌شوند و
	 * بقیه از مقدار ذخیره‌شده دست‌نخورده می‌مانند. (پیش‌تر خالی‌بودن یک
	 * چک‌باکس، تنظیمات تب‌های دیگر را صفر می‌کرد.)
	 *
	 * @param mixed $input ورودی فرم.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = get_option( 'manacore_settings', array() );
		$existing = is_array( $existing ) ? $existing : array();

		$tabs = self::tabs();
		$tab  = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
		$all  = ( '' === $tab || ! isset( $tabs[ $tab ] ) );
		$do   = static function ( $name ) use ( $all, $tab ) {
			return $all || $name === $tab;
		};

		$clean = array();

		/* ---------------- تب عمومی ---------------- */
		if ( $do( 'general' ) ) {
			$slug_keys = array( 'slug_movie', 'slug_series', 'slug_anime', 'slug_episode', 'slug_person', 'slug_collection' );
			foreach ( $slug_keys as $key ) {
				if ( isset( $input[ $key ] ) ) {
					$clean[ $key ] = sanitize_title( $input[ $key ] );
				}
			}

			$bool_keys = array( 'enable_ratings', 'enable_watchlist', 'enable_views', 'links_login_only' );
			foreach ( $bool_keys as $key ) {
				$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}

			$clean['items_per_page'] = isset( $input['items_per_page'] ) ? max( 1, min( 100, (int) $input['items_per_page'] ) ) : 24;

			$clean['default_color_mode'] = isset( $input['default_color_mode'] ) && in_array( $input['default_color_mode'], array( 'dark', 'light', 'auto' ), true )
				? $input['default_color_mode']
				: 'dark';

			$clean['custom_qualities'] = isset( $input['custom_qualities'] )
				? sanitize_textarea_field( $input['custom_qualities'] )
				: '';
		}

		/* ---------------- تب پخش و دانلود ---------------- */
		if ( $do( 'watch' ) ) {
			foreach ( array( 'download_notice_text', 'player_notice_text', 'subscribe_label' ) as $key ) {
				$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
			}

			$clean['subscribe_url'] = isset( $input['subscribe_url'] ) ? esc_url_raw( trim( (string) $input['subscribe_url'] ) ) : '';

			/* امضای لینک دانلود و اعتبار آن (دقیقه، با کرانه‌گذاری). */
			$clean['download_signing'] = empty( $input['download_signing'] ) ? 0 : 1;
			$clean['download_ttl']     = isset( $input['download_ttl'] )
				? max( Downloads::MIN_TTL, min( Downloads::MAX_TTL, (int) $input['download_ttl'] ) )
				: 1440;
		}

		/* ---------------- تب درخواست‌ها ---------------- */
		if ( $do( 'requests' ) ) {
			foreach ( array( 'requests_enabled', 'requests_guests', 'requests_show_pending' ) as $key ) {
				$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}

			foreach ( array( 'requests_heading', 'requests_board_title', 'requests_button' ) as $key ) {
				$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			}

			foreach ( array( 'requests_intro', 'requests_thanks' ) as $key ) {
				$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
			}

			$clean['requests_per_page'] = isset( $input['requests_per_page'] )
				? max( 3, min( 60, (int) $input['requests_per_page'] ) )
				: 12;
		}

		/* ---------------- تب تبلیغات ---------------- */
		if ( $do( 'ads' ) ) {
			foreach ( array( 'ads_enabled', 'ads_hide_members', 'ads_counters' ) as $key ) {
				$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}

			$clean['ads_label']        = isset( $input['ads_label'] ) ? sanitize_text_field( $input['ads_label'] ) : '';
			$clean['ads_per_position'] = isset( $input['ads_per_position'] ) ? max( 1, min( 3, (int) $input['ads_per_position'] ) ) : 1;

			/*
			 * جایگاه‌ها: فقط کلیدهای شناخته‌شده پذیرفته می‌شوند. خالی بودن
			 * فهرست یعنی «هیچ جایگاهی» (نه «همه») — همان چیزی که مدیر با
			 * برداشتن همه‌ی تیک‌ها می‌خواهد.
			 */
			$allowed   = class_exists( __NAMESPACE__ . '\\Ads' ) ? array_keys( Ads::positions() ) : array();
			$positions = isset( $input['ads_positions'] ) ? (array) $input['ads_positions'] : array();
			$positions = array_map( 'sanitize_key', $positions );

			$clean['ads_positions'] = array_values( array_intersect( $positions, $allowed ) );
		}

		/* ---------------- تب مگامنو ---------------- */
		if ( $do( 'mega' ) ) {
			$clean['mega_enabled']     = empty( $input['mega_enabled'] ) ? 0 : 1;
			$clean['mega_show_korean'] = empty( $input['mega_show_korean'] ) ? 0 : 1;
			$clean['mega_show_cast']   = empty( $input['mega_show_cast'] ) ? 0 : 1;

			foreach ( array( 'mega_eyebrow', 'mega_title', 'mega_quick_label', 'mega_feature_label', 'mega_cta_label', 'mega_rating_label', 'mega_newest_label', 'mega_korean_label', 'mega_cast_label' ) as $key ) {
				if ( isset( $input[ $key ] ) ) {
					$clean[ $key ] = sanitize_text_field( $input[ $key ] );
				}
			}

			if ( isset( $input['mega_taxonomy'] ) ) {
				$tax                    = sanitize_key( $input['mega_taxonomy'] );
				$clean['mega_taxonomy'] = taxonomy_exists( $tax ) ? $tax : 'genre';
			}

			$clean['mega_terms']   = isset( $input['mega_terms'] ) ? max( 3, min( 30, (int) $input['mega_terms'] ) ) : 12;
			$clean['mega_columns'] = isset( $input['mega_columns'] ) ? max( 2, min( 4, (int) $input['mega_columns'] ) ) : 3;
			$clean['mega_hub_url'] = isset( $input['mega_hub_url'] ) ? esc_url_raw( trim( (string) $input['mega_hub_url'] ) ) : '';

			$featured = isset( $input['mega_featured_id'] ) ? absint( $input['mega_featured_id'] ) : 0;

			/* اثر ویژه فقط از نوع‌های ManaCore و منتشرشده پذیرفته می‌شود. */
			if ( $featured ) {
				$type = (string) get_post_type( $featured );
				if ( ! array_key_exists( $type, manacore_post_types() ) || 'publish' !== get_post_status( $featured ) ) {
					$featured = 0;
				}
			}

			$clean['mega_featured_id'] = $featured;
		}

		$merged = array_merge( $existing, $clean );

		/*
		 * کش ترنزینت‌های مگامنو با ذخیره‌ی تنظیمات پاک می‌شود تا متن/تعداد
		 * تازه بی‌درنگ در پنل و شورت‌کد دیده شود.
		 */
		if ( class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ) {
			Mega_Menu::flush_cache();
		}

		/**
		 * فیلتر تنظیمات نهایی.
		 *
		 * @param array $merged   تنظیمات پاک‌شده.
		 * @param array $input    ورودی خام.
		 */
		return apply_filters( 'manacore_sanitize_settings', $merged, $input );
	}

	/**
	 * پیام‌های مدیریتی (ذخیره/ابزارها).
	 */
	public function notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'toplevel_page_manacore' !== $screen->id ) {
			return;
		}

		$notice = isset( $_GET['manacore_notice'] ) ? sanitize_key( wp_unslash( $_GET['manacore_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $notice ) {
			return;
		}

		$map = array(
			'watch-ok'      => array( 'success', __( 'برگه‌ی پخش ساخته/بازیابی شد.', 'manacore' ) ),
			'mega-ok'       => array( 'success', __( 'فهرست راهبری مگامنو بازسازی شد.', 'manacore' ) ),
			'cache-ok'      => array( 'success', __( 'کش مگامنو و برگه‌های گذرا پاک شد.', 'manacore' ) ),
			'rewrite-ok'    => array( 'success', __( 'قواعد پیوندهای یکتا بازسازی شد.', 'manacore' ) ),
			'report-ok'     => array( 'success', __( 'گزارش به‌روز شد.', 'manacore' ) ),
			'request-ok'    => array( 'success', __( 'برگه‌ی درخواست‌ها ساخته شد.', 'manacore' ) ),
			'demo-ok'       => array( 'success', __( 'محتوای نمایشی ساخته شد.', 'manacore' ) ),
			'demo-exists'   => array( 'warning', __( 'محتوای نمایشی از قبل ساخته شده است؛ دوباره ساخته نشد.', 'manacore' ) ),
			'demo-removed'  => array( 'success', __( 'محتوای نمایشی حذف شد.', 'manacore' ) ),
			'import-ok'     => array( 'success', __( 'تنظیمات بازگردانی شد.', 'manacore' ) ),
			'import-dry'    => array( 'info', __( 'بررسی فایل انجام شد؛ حالت آزمایشی روشن بود و چیزی ذخیره نشد.', 'manacore' ) ),
			'import-error'  => array( 'error', __( 'بازگردانی تنظیمات انجام نشد.', 'manacore' ) ),
			'failed'        => array( 'error', __( 'ابزار اجرا نشد؛ شرایط پیش‌نیاز را ببینید.', 'manacore' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$reason = isset( $_GET['manacore_reason'] ) ? sanitize_key( wp_unslash( $_GET['manacore_reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);

		$details = self::notice_details( $notice, $reason );

		if ( '' !== $details ) {
			echo '<p>' . esc_html( $details ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * سطر توضیحی زیر پیام مدیریتی (دلیل خطا یا شمار تنظیمات اعمال‌شده).
	 *
	 * @param string $notice کد پیام.
	 * @param string $reason دلیل خطا (فقط برای `import-error`).
	 * @return string
	 */
	protected static function notice_details( $notice, $reason = '' ) {
		if ( 'import-error' === $notice ) {
			$reasons = array(
				'empty'   => __( 'چیزی برای بازگردانی فرستاده نشد؛ فایل JSON یا متن آن را وارد کنید.', 'manacore' ),
				'json'    => __( 'متن JSON خوانده نشد؛ فایل خراب یا ناقص است.', 'manacore' ),
				'format'  => __( 'این فایل مربوط به تنظیمات ManaCore نیست.', 'manacore' ),
				'version' => __( 'فایل با نسخه‌ی تازه‌تری از افزونه ساخته شده است؛ نخست افزونه را به‌روز کنید.', 'manacore' ),
				'size'    => __( 'حجم فایل بیش از حد مجاز است.', 'manacore' ),
			);

			return isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : __( 'فایل را بررسی کنید و دوباره تلاش کنید.', 'manacore' );
		}

		if ( ! in_array( $notice, array( 'import-ok', 'import-dry' ), true ) ) {
			return '';
		}

		$applied = isset( $_GET['manacore_applied'] ) ? absint( wp_unslash( $_GET['manacore_applied'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped = isset( $_GET['manacore_skipped'] ) ? absint( wp_unslash( $_GET['manacore_skipped'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/* translators: 1: تعداد تنظیم‌های اعمال‌شده، 2: تعداد کلیدهای ناشناخته */
		return sprintf(
			__( '%1$s تنظیم اعمال شد و %2$s کلید ناشناخته نادیده گرفته شد.', 'manacore' ),
			number_format_i18n( $applied ),
			number_format_i18n( $skipped )
		);
	}

	/**
	 * اجرای ابزارهای تب «وضعیت و ابزارها».
	 *
	 * همه‌ی ابزارها از `admin_post` می‌آیند: نانِس اختصاصی + بررسی
	 * `manage_options` + بازگشت به همان تب با پیام.
	 */
	public function handle_tool() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
		check_admin_referer( 'manacore_tool_' . $tool );

		$notice = 'failed';

		switch ( $tool ) {
			case 'watch-page':
				$page_id = Player::create_page();
				$notice  = $page_id ? 'watch-ok' : 'failed';
				break;

			case 'request-page':
				$page_id = Requests::create_page();
				$notice  = $page_id ? 'request-ok' : 'failed';
				break;

			case 'demo-create':
				$result = Demo::seed();

				if ( ! empty( $result['created'] ) ) {
					$notice = 'demo-ok';
				} else {
					/* نبودِ «ساخته‌شده» یا یعنی از قبل هست، یا یعنی خطا خورده است. */
					$notice = empty( $result['skipped'] ) ? 'failed' : 'demo-exists';
				}
				break;

			case 'demo-remove':
				/*
				 * حذف عمداً idempotent است: اگر محتوایی نباشد هم «انجام شد»
				 * گزارش می‌شود، چون نتیجه‌ی نهایی (نبودِ محتوای نمایشی)
				 * همان است و خطا دادن، مدیر را بی‌دلیل می‌ترساند.
				 */
				Demo::remove();
				$notice = 'demo-removed';
				break;

			case 'mega-rebuild':
				$notice = function_exists( 'koohe_rebuild_navigation' ) && koohe_rebuild_navigation() ? 'mega-ok' : 'failed';
				break;

			case 'flush-cache':
				if ( class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ) {
					Mega_Menu::flush_cache();
				}
				delete_transient( 'manacore_home_stats' );

				if ( class_exists( __NAMESPACE__ . '\\Analytics' ) ) {
					Analytics::instance()->flush();
				}

				$notice = 'cache-ok';
				break;

			case 'flush-rewrite':
				flush_rewrite_rules( false );
				$notice = 'rewrite-ok';
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'manacore',
					'tab'              => 'tools',
					'manacore_notice'  => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * کارت گزارش‌های خرابی لینک.
	 *
	 * گزارش‌های «تازه» اول می‌آیند و مدیر می‌تواند هر ردیف را
	 * «اصلاح‌شده»، «نادیده‌گرفته‌شده» یا «حذف‌شده» علامت بزند.
	 *
	 * @return void
	 */
	protected function render_reports() {
		if ( ! class_exists( __NAMESPACE__ . '\\Reports' ) ) {
			return;
		}

		$counts  = Reports::counts();
		$reports = Reports::query( array( 'status' => 'new', 'per_page' => 20 ) );
		?>
		<div class="card" style="grid-column:1 / -1">
			<h2>
				<?php esc_html_e( 'گزارش‌های خرابی لینک', 'manacore' ); ?>
				<?php if ( $counts['new'] ) : ?>
					<span class="count"><?php echo esc_html( number_format_i18n( $counts['new'] ) ); ?></span>
				<?php endif; ?>
			</h2>

			<p class="description">
				<?php
				printf(
					/* translators: 1: تازه، 2: اصلاح‌شده، 3: نادیده‌گرفته‌شده */
					esc_html__( 'تازه: %1$s — اصلاح‌شده: %2$s — نادیده‌گرفته‌شده: %3$s', 'manacore' ),
					esc_html( number_format_i18n( $counts['new'] ) ),
					esc_html( number_format_i18n( $counts['fixed'] ) ),
					esc_html( number_format_i18n( $counts['ignored'] ) )
				);
				?>
			</p>

			<?php if ( ! $reports ) : ?>
				<p><?php esc_html_e( 'گزارش تازه‌ای نیست. کاربران وقتی لینکی کار نکند، از زیر جدول دانلود گزارش می‌دهند.', 'manacore' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'اثر', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'کیفیت', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'لینک', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'توضیح', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'تاریخ', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'کنش', 'manacore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $reports as $report ) : ?>
							<tr>
								<td>
									<?php
									$post_id = (int) $report['post_id'];
									$edit    = get_edit_post_link( $post_id );
									$title   = get_the_title( $post_id );

									if ( $edit ) {
										printf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html( '' !== $title ? $title : '#' . $post_id ) );
									} else {
										echo esc_html( '' !== $title ? $title : '#' . $post_id );
									}
									?>
								</td>
								<td><code><?php echo esc_html( (string) $report['quality'] ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( (string) $report['link_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
										<?php echo esc_html( '' !== (string) $report['link_label'] ? mb_substr( (string) $report['link_label'], 0, 40 ) : __( 'بازکردن لینک', 'manacore' ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( (string) $report['reason'] ); ?></td>
								<td><code><?php echo esc_html( (string) $report['created_at'] ); ?></code></td>
								<td>
									<?php $this->report_action( (int) $report['id'], 'fixed', __( 'اصلاح شد', 'manacore' ) ); ?>
									<?php $this->report_action( (int) $report['id'], 'ignored', __( 'نادیده بگیر', 'manacore' ) ); ?>
									<?php $this->report_action( (int) $report['id'], 'delete', __( 'حذف', 'manacore' ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * پیوند کنش روی یک گزارش (با نانِس).
	 *
	 * @param int    $id     شناسه‌ی گزارش.
	 * @param string $action کنش.
	 * @param string $label  برچسب.
	 * @return void
	 */
	protected function report_action( $id, $action, $label ) {
		$url = add_query_arg(
			array(
				'action'        => 'manacore_report_action',
				'report_id'     => (int) $id,
				'report_action' => $action,
			),
			admin_url( 'admin-post.php' )
		);

		printf(
			'<a class="button button-small" href="%s">%s</a> ',
			esc_url( wp_nonce_url( $url, 'manacore_report_' . (int) $id ) ),
			esc_html( $label )
		);
	}

	/**
	 * کارت وضعیت برگه‌ی پخش.
	 *
	 * @return void
	 */
	protected function render_watch_status() {
		$status = Player::page_status();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'وضعیت برگه‌ی پخش', 'manacore' ); ?></h2>
			<p>
				<?php if ( $status['exists'] && $status['published'] ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>
					<?php
					printf(
						/* translators: %d: شناسه‌ی برگه */
						esc_html__( 'برگه آماده است (شناسه %d).', 'manacore' ),
						(int) $status['id']
					);
					?>
				<?php else : ?>
					<span class="dashicons dashicons-warning" style="color:#dba617"></span>
					<?php esc_html_e( 'برگه‌ی پخش پیدا نشد یا منتشر نشده است؛ دکمه‌های «پخش» فقط مُدال را باز می‌کنند.', 'manacore' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<?php if ( '' !== $status['url'] ) : ?>
					<a class="button" href="<?php echo esc_url( $status['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'نمایش برگه', 'manacore' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $status['edit_url'] ) : ?>
					<a class="button" href="<?php echo esc_url( $status['edit_url'] ); ?>"><?php esc_html_e( 'ویرایش در ویرایشگر', 'manacore' ); ?></a>
				<?php endif; ?>
			</p>
			<?php $this->tool_button( 'watch-page', __( 'ساخت / بازیابی برگه‌ی پخش', 'manacore' ) ); ?>
			<p class="description"><?php esc_html_e( 'برگه با نامک «watch» ساخته می‌شود و قالب «پخش آنلاین» آن را پر می‌کند. برگه‌ی موجود هرگز بازنویسی نمی‌شود.', 'manacore' ); ?></p>
		</div>
		<?php
	}

	/**
	 * کارت وضعیت مگامنو.
	 *
	 * @return void
	 */
	protected function render_mega_status() {
		$menu_id  = function_exists( 'koohe_primary_navigation_id' ) ? (int) koohe_primary_navigation_id() : 0;
		$menu     = $menu_id ? get_post( $menu_id ) : null;
		$content  = $menu ? (string) $menu->post_content : '';
		$has_mega = false !== strpos( $content, 'koohe-mega' );
		$terms    = class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ? count( Mega_Menu::menu_terms( 'genre', 30 ) ) : 0;
		?>
		<div class="card">
			<h2><?php esc_html_e( 'وضعیت مگامنو', 'manacore' ); ?></h2>
			<ul>
				<li>
					<?php if ( $has_mega ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>
						<?php esc_html_e( 'فهرست راهبری آیتم مگامنو دارد.', 'manacore' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color:#dba617"></span>
						<?php esc_html_e( 'فهرست راهبری فعلی آیتم مگامنو ندارد؛ با دکمه‌ی زیر بسازید.', 'manacore' ); ?>
					<?php endif; ?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: تعداد ژانر */
						esc_html__( 'ژانرهای موجود برای پنل: %s', 'manacore' ),
						esc_html( number_format_i18n( $terms ) )
					);
					?>
				</li>
			</ul>
			<?php $this->tool_button( 'mega-rebuild', __( 'بازسازی فهرست راهبری مگامنو', 'manacore' ), __( 'مطمئنید؟ فهرست راهبری با ساختار تازه‌ی قالب بازنویسی می‌شود.', 'manacore' ) ); ?>
			<p class="description"><?php esc_html_e( 'با تغییر متن‌ها یا تعداد ژانرها در تب مگامنو، فهرست در نخستین بازدید خودکار به‌روز می‌شود؛ این دکمه برای بازسازی فوری است.', 'manacore' ); ?></p>
		</div>
		<?php
	}

	/**
	 * دکمه‌ی ابزار (فرم امن admin-post).
	 *
	 * @param string $tool   کلید ابزار.
	 * @param string $label  برچسب.
	 * @param string $confirm پیام تأیید اختیاری.
	 * @return void
	 */
	protected function tool_button( $tool, $label, $confirm = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:6px 0">
			<input type="hidden" name="action" value="manacore_tool" />
			<input type="hidden" name="tool" value="<?php echo esc_attr( $tool ); ?>" />
			<?php wp_nonce_field( 'manacore_tool_' . $tool ); ?>
			<button type="submit" class="button button-primary"<?php echo '' !== $confirm ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * رندر صفحه‌ی تنظیمات (چهار تب).
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = self::tabs();
		$tab  = self::current_tab();
		?>
		<div class="wrap">
			<div class="manacore-settings-header">
				<span class="manacore-brand-mark">MC</span>
				<div>
					<h1><?php esc_html_e( 'ManaCore — هسته‌ی سایت فیلم و سریال', 'manacore' ); ?></h1>
					<p><?php esc_html_e( 'طراحی و توسعه توسط گروه ManaCore', 'manacore' ); ?></p>
				</div>
			</div>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'manacore', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'analytics':
					$this->render_analytics_tab();
					break;

				case 'watch':
					$this->render_watch_tab();
					break;

				case 'requests':
					$this->render_requests_tab();
					break;

				case 'ads':
					$this->render_ads_tab();
					break;

				case 'mega':
					$this->render_mega_tab();
					break;

				case 'tools':
					$this->render_tools_tab();
					break;

				default:
					$this->render_general_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * فیلدهای پنهان مشترک فرم‌های تنظیمات.
	 *
	 * @param string $tab             تب جاری.
	 * @param bool   $watch_fallback  ارسال شناسه‌ی برگه‌ی پخش به‌صورت پنهان؛
	 *                                در تبی که خودش این فیلد را دارد `false`.
	 * @return void
	 */
	protected function hidden_fields( $tab, $watch_fallback = true ) {
		?>
		<input type="hidden" name="manacore_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
		<?php
		/*
		 * شناسه‌ی برگه‌ی پخش (گزینه‌ی مستقل در همان گروه) در تب‌هایی که
		 * فیلدش را ندارند پنهان فرستاده می‌شود تا ذخیره‌شان آن را خالی
		 * نکند. اگر تب خودش فیلد را دارد، مقدار مرئی مقدم است.
		 */
		if ( $watch_fallback ) :
			?>
		<input type="hidden" name="manacore_watch_page" value="<?php echo esc_attr( (int) get_option( 'manacore_watch_page', 0 ) ); ?>" />
			<?php
		endif;
	}

	/**
	 * فرم استاندارد یک تب (همه از `options.php` رد می‌شوند).
	 *
	 * @param string $tab      کلید تب.
	 * @param string $callback تابع رندر محتوای فرم.
	 * @return void
	 */
	protected function tab_form( $tab, $callback ) {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'manacore_settings_group' );
			$this->hidden_fields( $tab );
			call_user_func( $callback );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * تب «تنظیمات عمومی» (نشانی‌های یکتا، امکانات، کیفیت‌های سفارشی).
	 *
	 * @return void
	 */
	protected function render_general_tab() {
		ob_start();
		?>
				<h2 class="title"><?php esc_html_e( 'نشانی‌های یکتا (Slug)', 'manacore' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'پس از تغییر، به تنظیمات » پیوندهای یکتا بروید و یک‌بار ذخیره کنید.', 'manacore' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<?php
					$slugs = array(
						'slug_movie'      => __( 'فیلم', 'manacore' ),
						'slug_series'     => __( 'سریال', 'manacore' ),
						'slug_anime'      => __( 'انیمه', 'manacore' ),
						'slug_episode'    => __( 'قسمت', 'manacore' ),
						'slug_person'     => __( 'عوامل', 'manacore' ),
						'slug_collection' => __( 'مجموعه', 'manacore' ),
					);
					foreach ( $slugs as $key => $label ) :
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( $key ); ?>"
									name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( manacore_get_option( $key ) ); ?>"
									class="regular-text" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'امکانات', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					/*
					 * توضیح کوتاه زیر گزینه‌هایی که معنی‌شان از برچسبشان پیدا
					 * نیست. «شمارش تماشا» عمداً روشن است: عدد آمار باید از
					 * رویداد واقعی بیاید، نه از بازشدن صفحه.
					 */
					$toggle_help = array(
						'enable_views' => __( 'فقط وقتی کاربر پخش را شروع کند شمرده می‌شود؛ بازشدن صفحه و رفرش، آمار را بالا نمی‌برد.', 'manacore' ),
					);

					$toggles = array(
						'enable_ratings'   => __( 'فعال بودن امتیازدهی کاربران', 'manacore' ),
						'enable_watchlist' => __( 'فعال بودن لیست تماشا', 'manacore' ),
						'enable_views'     => __( 'شمارش تماشا (شروع واقعی پخش)', 'manacore' ),
						'links_login_only' => __( 'نمایش لینک‌ها فقط برای کاربران وارد شده', 'manacore' ),
					);
					foreach ( $toggles as $key => $label ) :
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
										value="1" <?php checked( 1, (int) manacore_get_option( $key, 0 ) ); ?> />
									<?php esc_html_e( 'فعال', 'manacore' ); ?>
								</label>
								<?php if ( ! empty( $toggle_help[ $key ] ) ) : ?>
									<p class="description"><?php echo esc_html( $toggle_help[ $key ] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="items_per_page"><?php esc_html_e( 'تعداد آیتم در هر صفحه', 'manacore' ); ?></label></th>
						<td>
							<input type="number" id="items_per_page" name="manacore_settings[items_per_page]" min="1" max="100"
								value="<?php echo esc_attr( manacore_get_option( 'items_per_page', 24 ) ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_color_mode"><?php esc_html_e( 'حالت رنگی پیش‌فرض', 'manacore' ); ?></label></th>
						<td>
							<select id="default_color_mode" name="manacore_settings[default_color_mode]">
								<?php
								$modes = array(
									'dark'  => __( 'تیره', 'manacore' ),
									'light' => __( 'روشن', 'manacore' ),
									'auto'  => __( 'خودکار (بر اساس سیستم)', 'manacore' ),
								);
								foreach ( $modes as $key => $label ) :
									?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( manacore_get_option( 'default_color_mode', 'dark' ), $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'کیفیت‌های سفارشی', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="custom_qualities"><?php esc_html_e( 'کیفیت‌های اضافه', 'manacore' ); ?></label></th>
						<td>
							<textarea id="custom_qualities" name="manacore_settings[custom_qualities]" rows="5" class="large-text code"
								placeholder="key|برچسب نمایشی"><?php echo esc_textarea( manacore_get_option( 'custom_qualities', '' ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'هر خط یک کیفیت. نمونه: remux|BluRay REMUX', 'manacore' ); ?>
							</p>
						</td>
					</tr>
				</table>
		<?php
		$this->tab_form( 'general', function () {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput — HTML امنِ ساخته‌شده در همین متد
		} );
	}

	/**
	 * تب «پخش و دانلود»: برگه‌ی پخش، متن‌های پیش‌فرض و پیوند اشتراک.
	 *
	 * @return void
	 */
	protected function render_watch_tab() {
		$page_id = (int) get_option( 'manacore_watch_page', 0 );
		$status  = Player::page_status();
		ob_start();
		?>
				<h2 class="title"><?php esc_html_e( 'برگه‌ی پخش', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="manacore_watch_page"><?php esc_html_e( 'برگه‌ی پخش', 'manacore' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'manacore_watch_page',
									'id'                => 'manacore_watch_page',
									'selected'          => $page_id,
									'show_option_none'  => __( '— خودکار (بر پایه‌ی نامک watch) —', 'manacore' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'همه‌ی دکمه‌های «پخش» به این برگه می‌روند و شناسه‌ی اثر با پارامتر «manacore_id» می‌آید. خالی بگذارید تا برگه‌ی دارای نامک watch خودکار پیدا شود.', 'manacore' ); ?>
							</p>
							<?php if ( $status['exists'] && $status['published'] ) : ?>
								<p>
									<a href="<?php echo esc_url( $status['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'نمایش برگه', 'manacore' ); ?></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'متن‌های پیش‌فرض', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="download_notice_text"><?php esc_html_e( 'یادداشت باکس دانلود', 'manacore' ); ?></label></th>
						<td>
							<textarea id="download_notice_text" name="manacore_settings[download_notice_text]" rows="3" class="large-text"><?php echo esc_textarea( manacore_get_option( 'download_notice_text', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'اگر اثری یادداشت اختصاصی نداشته باشد، همین متن زیر سرتیتر باکس دانلود می‌آید. خالی = متن پیش‌فرض افزونه.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="player_notice_text"><?php esc_html_e( 'یادداشت صفحه‌ی پخش', 'manacore' ); ?></label></th>
						<td>
							<textarea id="player_notice_text" name="manacore_settings[player_notice_text]" rows="3" class="large-text"><?php echo esc_textarea( manacore_get_option( 'player_notice_text', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'زیر پلیر نمایش داده می‌شود. برای درج نام اثر از {title} استفاده کنید.', 'manacore' ); ?></p>
							<p class="description"><?php esc_html_e( 'نمونه: پلیر {title} با ویدئوی نمونه کار می‌کند.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="subscribe_url"><?php esc_html_e( 'پیوند خرید اشتراک', 'manacore' ); ?></label></th>
						<td>
							<input type="url" id="subscribe_url" name="manacore_settings[subscribe_url]" class="regular-text"
								value="<?php echo esc_attr( manacore_get_option( 'subscribe_url', '' ) ); ?>" dir="ltr" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="subscribe_label"><?php esc_html_e( 'برچسب دکمه‌ی اشتراک', 'manacore' ); ?></label></th>
						<td>
							<input type="text" id="subscribe_label" name="manacore_settings[subscribe_label]" class="regular-text"
								value="<?php echo esc_attr( manacore_get_option( 'subscribe_label', '' ) ); ?>"
								placeholder="<?php esc_attr_e( 'تهیه اشتراک', 'manacore' ); ?>" />
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'امضای لینک دانلود', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'وضعیت', 'manacore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="manacore_settings[download_signing]" value="1" <?php checked( 1, (int) manacore_get_option( 'download_signing', 0 ) ); ?> />
								<?php esc_html_e( 'دکمه‌های دانلود با نشانی امضاشده و زمان‌دار کار کنند', 'manacore' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'با روشن‌کردن این گزینه، نشانی خام فایل روی صفحه نمی‌آید و هر دانلود از یک مسیر داخلی می‌گذرد؛ پس اگر لینک را کسی جای دیگر بگذارد، پس از پایان اعتبار کار نمی‌کند. لینک‌های «پخش» و فایل‌های ضمیمه دست‌نخورده می‌مانند.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_ttl"><?php esc_html_e( 'اعتبار هر لینک (دقیقه)', 'manacore' ); ?></label></th>
						<td>
							<input type="number" id="download_ttl" name="manacore_settings[download_ttl]"
								min="<?php echo esc_attr( Downloads::MIN_TTL ); ?>" max="<?php echo esc_attr( Downloads::MAX_TTL ); ?>"
								value="<?php echo esc_attr( (int) manacore_get_option( 'download_ttl', 1440 ) ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'پیش‌فرض ۱۴۴۰ دقیقه (۲۴ ساعت) — کوتاه‌تر یعنی امن‌تر، بلندتر یعنی راحت‌تر برای مدیریت دانلود شبانه.', 'manacore' ); ?></p>
						</td>
					</tr>
				</table>
		<?php
		$this->tab_form( 'watch', function () {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
		} );
	}

	/**
	 * تب «تحلیل و آمار».
	 *
	 * @return void
	 */
	protected function render_analytics_tab() {
		if ( ! class_exists( __NAMESPACE__ . '\Analytics' ) ) {
			return;
		}

		$summary = Analytics::summary();
		$ratings = Analytics::ratings_summary();

		$cards = array(
			array( __( 'تماشا امروز', 'manacore' ), $summary['views_today'], '' ),
			array( __( 'تماشا ۷ روز', 'manacore' ), $summary['views_week'], '' ),
			array( __( 'بازدید ۳۰ روز', 'manacore' ), $summary['views_month'], '' ),
			array( __( 'دانلود ۷ روز', 'manacore' ), $summary['downloads_week'], '' ),
			array( __( 'امتیاز میانگین', 'manacore' ), number_format_i18n( $ratings['average'], 2 ), sprintf( __( '%s رأی', 'manacore' ), number_format_i18n( $ratings['total'] ) ) ),
			array( __( 'گزارش خرابی تازه', 'manacore' ), $summary['reports_new'], '' ),
			array( __( 'درخواست در انتظار', 'manacore' ), $summary['requests_pending'], sprintf( __( '%s تأییدشده', 'manacore' ), number_format_i18n( $summary['requests_publish'] ) ) ),
			array( __( 'دیدگاه در صف', 'manacore' ), $summary['comments_pending'], '' ),
		);

		if ( $summary['ads']['impressions'] || $summary['ads']['clicks'] ) {
			$cards[] = array(
				__( 'نمایش بنرها', 'manacore' ),
				number_format_i18n( $summary['ads']['impressions'] ),
				sprintf( __( '%1$s کلیک — نرخ %2$s٪', 'manacore' ), number_format_i18n( $summary['ads']['clicks'] ), number_format_i18n( $summary['ads']['ctr'], 2 ) ),
			);
		}
		?>
		<style>
			.manacore-stat-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin:14px 0}
			.manacore-stat{padding:14px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
			.manacore-stat b{display:block;font-size:1.5rem;line-height:1.4}
			.manacore-stat span{color:#646970;font-size:.8125rem}
			.manacore-stat em{display:block;color:#646970;font-size:.75rem;font-style:normal}
			.manacore-analytics-cols{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:6px}
		</style>

		<div class="manacore-stat-grid">
			<?php foreach ( $cards as $card ) : ?>
				<div class="manacore-stat">
					<b><?php echo esc_html( manacore_fa_digits( (string) $card[1] ) ); ?></b>
					<span><?php echo esc_html( $card[0] ); ?></span>
					<?php if ( '' !== $card[2] ) : ?>
						<em><?php echo esc_html( manacore_fa_digits( (string) $card[2] ) ); ?></em>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="description">
			<?php esc_html_e( 'عددها هر ۵ دقیقه یک‌بار تازه می‌شوند (کش) تا بازکردن پیشخوان روی سایت بزرگ کند نشود.', 'manacore' ); ?>
		</p>

		<div class="manacore-analytics-cols">
			<?php
			$this->analytics_table(
				__( 'پربازدیدترین‌های ۷ روز', 'manacore' ),
				Analytics::top( 'view', 7, 10 ),
				__( 'بازدید', 'manacore' )
			);

			$this->analytics_table(
				__( 'پربارگیری‌شده‌ترین‌های ۷ روز', 'manacore' ),
				Analytics::top( 'download', 7, 10 ),
				__( 'دانلود', 'manacore' )
			);

			$this->analytics_table(
				__( 'بیشترین گزارش خرابی لینک', 'manacore' ),
				Analytics::top_reported( 10 ),
				__( 'گزارش', 'manacore' ),
				'open'
			);

			$this->analytics_table(
				__( 'پررأی‌ترین درخواست‌ها', 'manacore' ),
				Analytics::top_requests( 10 ),
				__( 'رأی', 'manacore' ),
				'votes',
				true
			);
			?>
		</div>

		<div class="card" style="max-width:900px;margin-top:16px">
			<h2><?php esc_html_e( 'کارهای باز', 'manacore' ); ?></h2>
			<ul style="margin:8px 0;padding-inline-start:20px;list-style:disc">
				<?php if ( $summary['reports_new'] ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: تعداد */
							esc_html__( '%s گزارش خرابی لینک تازه در انتظار بررسی است.', 'manacore' ),
							esc_html( number_format_i18n( $summary['reports_new'] ) )
						);
						?>
					</li>
				<?php endif; ?>

				<?php if ( $summary['requests_pending'] ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: تعداد */
							esc_html__( '%s درخواست کاربر در انتظار تأیید است.', 'manacore' ),
							esc_html( number_format_i18n( $summary['requests_pending'] ) )
						);
						?>
					</li>
				<?php endif; ?>

				<?php if ( $summary['comments_pending'] ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: تعداد */
							esc_html__( '%s دیدگاه در صف بازبینی است.', 'manacore' ),
							esc_html( number_format_i18n( $summary['comments_pending'] ) )
						);
						?>
					</li>
				<?php endif; ?>

				<?php if ( ! $summary['reports_new'] && ! $summary['requests_pending'] && ! $summary['comments_pending'] ) : ?>
					<li><?php esc_html_e( 'صف بازبینی خالی است. کار خوبی کرده‌اید!', 'manacore' ); ?></li>
				<?php endif; ?>
			</ul>

			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'manacore', 'tab' => 'tools' ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'گزارش‌های خرابی لینک', 'manacore' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_status' => 'pending' ), admin_url( 'edit-comments.php' ) ) ); ?>">
					<?php esc_html_e( 'دیدگاه‌های در صف', 'manacore' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * جدول کوچک یک گزارش تحلیلی.
	 *
	 * @param string $title     عنوان.
	 * @param array  $rows      ردیف‌ها.
	 * @param string $value     برچسب ستون شمار.
	 * @param string $extra_key کلید ستون جانبی (اختیاری).
	 * @param bool   $pending   نمایش نشان «در انتظار» برای وضعیت pending.
	 * @return void
	 */
	protected function analytics_table( $title, $rows, $value, $extra_key = '', $pending = false ) {
		?>
		<div class="card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! $rows ) : ?>
				<p class="description"><?php esc_html_e( 'داده‌ای در این بازه ثبت نشده است.', 'manacore' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'اثر', 'manacore' ); ?></th>
							<th><?php echo esc_html( $value ); ?></th>
							<?php if ( $extra_key ) : ?>
								<th><?php echo esc_html( 'open' === $extra_key ? __( 'باز', 'manacore' ) : __( 'وضعیت', 'manacore' ) ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$edit   = current_user_can( 'edit_post', (int) $row['id'] ) ? get_edit_post_link( (int) $row['id'] ) : '';
							$amount = isset( $row['total'] ) ? $row['total'] : ( isset( $row['votes'] ) ? $row['votes'] : 0 );
							?>
							<tr>
								<td>
									<?php if ( $edit ) : ?>
										<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $row['title'] ); ?>
									<?php endif; ?>

									<?php if ( $pending && isset( $row['status'] ) && 'publish' !== $row['status'] ) : ?>
										<em><?php esc_html_e( '— در انتظار تأیید', 'manacore' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( manacore_fa_digits( number_format_i18n( (int) $amount ) ) ); ?></td>
								<?php if ( $extra_key ) : ?>
									<td>
										<?php
										if ( 'open' === $extra_key ) {
											echo esc_html( manacore_fa_digits( number_format_i18n( isset( $row['open'] ) ? (int) $row['open'] : 0 ) ) );
										} else {
											echo esc_html( isset( $row['status'] ) && 'publish' === $row['status'] ? __( 'تأییدشده', 'manacore' ) : __( 'در انتظار', 'manacore' ) );
										}
										?>
									</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * تب «درخواست‌ها».
	 *
	 * @return void
	 */
	protected function render_requests_tab() {
		$this->tab_form(
			'requests',
			function () {
				?>
				<h2 class="title"><?php esc_html_e( 'درخواست فیلم و سریال', 'manacore' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'کاربران عنوانی را که در آرشیو نیست ثبت می‌کنند، بقیه رأی می‌دهند و شما از فهرست «درخواست‌ها» بازبینی می‌کنید. برای نمایش، بلوک «فرم درخواست فیلم/سریال» و «تخته‌ی درخواست‌ها» را در هر برگه‌ای بگذارید (یا از دکمه‌ی ساخت برگه در پایین استفاده کنید).', 'manacore' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'وضعیت', 'manacore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="manacore_settings[requests_enabled]" value="1" <?php checked( 1, (int) manacore_get_option( 'requests_enabled', 1 ) ); ?> />
								<?php esc_html_e( 'درخواست‌ها فعال باشد', 'manacore' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="manacore_settings[requests_guests]" value="1" <?php checked( 1, (int) manacore_get_option( 'requests_guests', 1 ) ); ?> />
								<?php esc_html_e( 'کاربران مهمان هم می‌توانند درخواست بدهند', 'manacore' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="manacore_settings[requests_show_pending]" value="1" <?php checked( 1, (int) manacore_get_option( 'requests_show_pending', 1 ) ); ?> />
								<?php esc_html_e( 'درخواست‌های در انتظار تأیید هم در تخته دیده شوند (با برچسب)', 'manacore' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_heading"><?php esc_html_e( 'عنوان فرم', 'manacore' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="requests_heading" name="manacore_settings[requests_heading]"
								value="<?php echo esc_attr( (string) manacore_get_option( 'requests_heading', __( 'فیلم یا سریالی که دنبالش هستید را بگویید', 'manacore' ) ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_intro"><?php esc_html_e( 'توضیح کوتاه فرم', 'manacore' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="2" id="requests_intro" name="manacore_settings[requests_intro]"><?php echo esc_textarea( (string) manacore_get_option( 'requests_intro', __( 'عنوان را ثبت کنید؛ هرچه رأی بیشتری بگیرد، زودتر منتشر می‌شود.', 'manacore' ) ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_button"><?php esc_html_e( 'متن دکمه‌ی ثبت', 'manacore' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="requests_button" name="manacore_settings[requests_button]"
								value="<?php echo esc_attr( (string) manacore_get_option( 'requests_button', __( 'ثبت درخواست', 'manacore' ) ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_thanks"><?php esc_html_e( 'پیام پس از ثبت', 'manacore' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="2" id="requests_thanks" name="manacore_settings[requests_thanks]"><?php echo esc_textarea( (string) manacore_get_option( 'requests_thanks', __( 'درخواست شما ثبت شد. به‌محض تأیید، به تخته‌ی درخواست‌ها اضافه می‌شود.', 'manacore' ) ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_board_title"><?php esc_html_e( 'عنوان تخته', 'manacore' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="requests_board_title" name="manacore_settings[requests_board_title]"
								value="<?php echo esc_attr( (string) manacore_get_option( 'requests_board_title', __( 'درخواست‌های کاربران', 'manacore' ) ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="requests_per_page"><?php esc_html_e( 'تعداد در هر صفحه', 'manacore' ); ?></label></th>
						<td>
							<input type="number" min="3" max="60" class="small-text" id="requests_per_page" name="manacore_settings[requests_per_page]"
								value="<?php echo esc_attr( (string) (int) manacore_get_option( 'requests_per_page', 12 ) ); ?>" />
						</td>
					</tr>
				</table>
				<?php
			}
		);

		$this->render_requests_status();
	}

	/**
	 * کارت وضعیت درخواست‌ها.
	 *
	 * @return void
	 */
	protected function render_requests_status() {
		if ( ! class_exists( __NAMESPACE__ . '\\Requests' ) ) {
			return;
		}

		$counts = Requests::counts();
		$page   = Requests::page_url();
		?>
		<div class="card" style="max-width:900px;margin-top:16px">
			<h2><?php esc_html_e( 'وضعیت درخواست‌ها', 'manacore' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: ۱: در انتظار، ۲: تأییدشده، ۳: ردشده */
					esc_html__( 'در انتظار بازبینی: %1$s — تأییدشده: %2$s — ردشده: %3$s', 'manacore' ),
					esc_html( number_format_i18n( $counts['pending'] ) ),
					esc_html( number_format_i18n( $counts['publish'] ) ),
					esc_html( number_format_i18n( $counts['draft'] ) )
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => Requests::POST_TYPE ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'فهرست درخواست‌ها', 'manacore' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => Requests::POST_TYPE, 'post_status' => 'pending' ), admin_url( 'edit.php' ) ) ); ?>">
					<?php esc_html_e( 'در انتظار بازبینی', 'manacore' ); ?>
				</a>
				<?php if ( $page ) : ?>
					<a class="button" href="<?php echo esc_url( $page ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'دیدن برگه‌ی درخواست‌ها', 'manacore' ); ?></a>
				<?php endif; ?>
			</p>

			<?php if ( ! $page ) : ?>
				<p class="description"><?php esc_html_e( 'هنوز برگه‌ای برای درخواست‌ها ساخته نشده است. با دکمه‌ی زیر یک برگه با فرم و تخته ساخته می‌شود.', 'manacore' ); ?></p>
			<?php endif; ?>

			<?php $this->tool_button( 'request-page', __( 'ساخت برگه‌ی درخواست‌ها', 'manacore' ) ); ?>

			<p class="description" style="margin-top:12px">
				<?php esc_html_e( 'شورت‌کدهای معادل:', 'manacore' ); ?>
				<code>[manacore_request_form]</code> — <code>[manacore_requests orderby="votes"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * تب «تبلیغات».
	 *
	 * @return void
	 */
	protected function render_ads_tab() {
		$positions = class_exists( __NAMESPACE__ . '\\Ads' ) ? Ads::positions() : array();
		$enabled   = (array) manacore_get_option( 'ads_positions', array_keys( $positions ) );

		$this->tab_form(
			'ads',
			function () use ( $positions, $enabled ) {
				?>
				<h2 class="title"><?php esc_html_e( 'جایگاه‌های تبلیغاتی', 'manacore' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: پیوند فهرست بنرها */
						esc_html__( 'بنرها را از %s بسازید؛ تصویر، مقصد، جایگاه و زمان‌بندی هر بنر در همان صفحه تنظیم می‌شود.', 'manacore' ),
						'<a href="' . esc_url( add_query_arg( array( 'post_type' => 'manacore_ad' ), admin_url( 'edit.php' ) ) ) . '">' . esc_html__( 'فهرست تبلیغات', 'manacore' ) . '</a>'
					);
					?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'وضعیت', 'manacore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="manacore_settings[ads_enabled]" value="1" <?php checked( 1, (int) manacore_get_option( 'ads_enabled', 0 ) ); ?> />
								<?php esc_html_e( 'نمایش تبلیغات فعال باشد', 'manacore' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="manacore_settings[ads_hide_members]" value="1" <?php checked( 1, (int) manacore_get_option( 'ads_hide_members', 0 ) ); ?> />
								<?php esc_html_e( 'برای کاربران وارد‌شده نمایش داده نشود', 'manacore' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="manacore_settings[ads_counters]" value="1" <?php checked( 1, (int) manacore_get_option( 'ads_counters', 1 ) ); ?> />
								<?php esc_html_e( 'شمارش نمایش و کلیک روشن باشد', 'manacore' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'جایگاه‌های فعال', 'manacore' ); ?></th>
						<td>
							<?php foreach ( $positions as $key => $label ) : ?>
								<label style="display:block;margin-bottom:4px">
									<input type="checkbox" name="manacore_settings[ads_positions][]" value="<?php echo esc_attr( $key ); ?>"
										<?php checked( in_array( $key, $enabled, true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( '«بالای صفحه» پیش از سربرگ، «پیش از محتوا» و «پس از محتوا» در محتوای نوشته‌ها و «پیش از پخش‌کننده» بالای پلیر می‌آید. برای جای‌گذاری دستی، بلوک «جایگاه تبلیغاتی» یا شورت‌کد را به کار ببرید.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ads_label"><?php esc_html_e( 'برچسب روی بنر', 'manacore' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="ads_label" name="manacore_settings[ads_label]"
								value="<?php echo esc_attr( (string) manacore_get_option( 'ads_label', __( 'تبلیغ', 'manacore' ) ) ); ?>" />
							<p class="description"><?php esc_html_e( 'خالی بگذارید تا هیچ برچسبی روی بنر نیاید.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ads_per_position"><?php esc_html_e( 'تعداد بنر در هر جایگاه', 'manacore' ); ?></label></th>
						<td>
							<input type="number" min="1" max="3" class="small-text" id="ads_per_position" name="manacore_settings[ads_per_position]"
								value="<?php echo esc_attr( (string) (int) manacore_get_option( 'ads_per_position', 1 ) ); ?>" />
						</td>
					</tr>
				</table>
				<?php
			}
		);

		if ( ! class_exists( __NAMESPACE__ . '\\Ads' ) ) {
			return;
		}
		?>
		<div class="card" style="max-width:900px;margin-top:16px">
			<h2><?php esc_html_e( 'بنرهای فعال', 'manacore' ); ?></h2>
			<?php
			$ads = get_posts(
				array(
					'post_type'      => Ads::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 8,
					'orderby'        => 'menu_order',
					'order'          => 'DESC',
				)
			);
			?>
			<?php if ( ! $ads ) : ?>
				<p><?php esc_html_e( 'هنوز بنری ساخته نشده است.', 'manacore' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'بنر', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'نمایش', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'کلیک', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'نرخ کلیک', 'manacore' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'manacore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ads as $ad ) : ?>
							<?php $stats = Ads::stats( $ad->ID ); ?>
							<tr>
								<td><a href="<?php echo esc_url( (string) get_edit_post_link( $ad->ID ) ); ?>"><?php echo esc_html( get_the_title( $ad->ID ) ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( $stats['impressions'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $stats['ctr'], 2 ) ); ?>٪</td>
								<td><code><?php echo esc_html( Ads::state( $ad->ID ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p class="description" style="margin-top:12px">
				<?php esc_html_e( 'شورت‌کد:', 'manacore' ); ?> <code>[manacore_ad position="before-content"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * تب «مگامنو»: تاکسونومی، تعداد، متن‌ها و کارت ویژه.
	 *
	 * @return void
	 */
	protected function render_mega_tab() {
		$settings = class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ? Mega_Menu::settings() : array();
		ob_start();
		?>
				<h2 class="title"><?php esc_html_e( 'پنل مگامنو', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'نمایش پنل', 'manacore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="manacore_settings[mega_enabled]" value="1" <?php checked( 1, (int) manacore_get_option( 'mega_enabled', 1 ) ); ?> />
								<?php esc_html_e( 'پنل مگامنو فعال باشد', 'manacore' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mega_taxonomy"><?php esc_html_e( 'تاکسونومی ژانرها', 'manacore' ); ?></label></th>
						<td>
							<select id="mega_taxonomy" name="manacore_settings[mega_taxonomy]">
								<?php
								$taxes = get_taxonomies( array( 'public' => true ), 'objects' );
								foreach ( $taxes as $tax ) {
									printf(
										'<option value="%1$s" %2$s>%3$s</option>',
										esc_attr( $tax->name ),
										selected( $settings['taxonomy'] ?? 'genre', $tax->name, false ),
										esc_html( $tax->labels->name )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mega_terms"><?php esc_html_e( 'تعداد ژانرها', 'manacore' ); ?></label></th>
						<td>
							<input type="number" id="mega_terms" name="manacore_settings[mega_terms]" min="3" max="30"
								value="<?php echo esc_attr( (int) manacore_get_option( 'mega_terms', 12 ) ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'ترم‌ها به ترتیب بیشترین محتوا انتخاب می‌شوند.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mega_hub_url"><?php esc_html_e( 'نشانی «مرکز دسته‌بندی‌ها»', 'manacore' ); ?></label></th>
						<td>
							<input type="url" id="mega_hub_url" name="manacore_settings[mega_hub_url]" class="regular-text" dir="ltr"
								value="<?php echo esc_attr( manacore_get_option( 'mega_hub_url', '' ) ); ?>"
								placeholder="<?php echo esc_attr( home_url( '/categories-hub/' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'خالی = برگه‌ی categories-hub یا browse یا آرشیو فیلم، هرکدام موجود بود.', 'manacore' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mega_featured_id"><?php esc_html_e( 'کارت ویژه (شناسه‌ی اثر)', 'manacore' ); ?></label></th>
						<td>
							<input type="number" id="mega_featured_id" name="manacore_settings[mega_featured_id]" min="0"
								value="<?php echo esc_attr( (int) manacore_get_option( 'mega_featured_id', 0 ) ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'شناسه‌ی فیلم/سریال برای ستون سوم پنل. صفر = خودکار (تازه‌ترین اثر دارای پوستر).', 'manacore' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'متن‌های پنل', 'manacore' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$texts = array(
						'mega_eyebrow'       => array( __( 'سرستون پنل', 'manacore' ), __( 'یک دنیا انتخاب', 'manacore' ) ),
						'mega_title'         => array( __( 'تیتر پنل', 'manacore' ), __( 'حال‌وهوای امشبت چیه؟', 'manacore' ) ),
						'mega_quick_label'   => array( __( 'سرستون ستون دسترسی سریع', 'manacore' ), __( 'به انتخاب سینورا', 'manacore' ) ),
						'mega_rating_label'  => array( __( 'ردیف «بالاترین امتیازها»', 'manacore' ), '' ),
						'mega_newest_label'  => array( __( 'ردیف «تازه‌ها»', 'manacore' ), '' ),
						'mega_korean_label'  => array( __( 'ردیف «کره‌ای»', 'manacore' ), '' ),
						'mega_cast_label'    => array( __( 'ردیف «بازیگران»', 'manacore' ), '' ),
						'mega_feature_label' => array( __( 'سطر ریز کارت ویژه', 'manacore' ), '' ),
						'mega_cta_label'     => array( __( 'برچسب کنش کارت ویژه', 'manacore' ), __( 'کشف داستان', 'manacore' ) ),
					);

					foreach ( $texts as $key => $row ) :
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $row[0] ); ?></label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( $key ); ?>" name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
									class="regular-text" placeholder="<?php echo esc_attr( $row[1] ); ?>"
									value="<?php echo esc_attr( manacore_get_option( $key, '' ) ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'ردیف‌های اختیاری', 'manacore' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="manacore_settings[mega_show_korean]" value="1" <?php checked( 1, (int) manacore_get_option( 'mega_show_korean', 1 ) ); ?> />
								<?php esc_html_e( 'ردیف «فیلم و سریال کره‌ای» (نیازمند ترم کشور کره)', 'manacore' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="manacore_settings[mega_show_cast]" value="1" <?php checked( 1, (int) manacore_get_option( 'mega_show_cast', 1 ) ); ?> />
								<?php esc_html_e( 'ردیف «بازیگران و کارگردان‌ها»', 'manacore' ); ?>
							</label>
						</td>
					</tr>
				</table>
		<?php
		$this->tab_form( 'mega', function () {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
		} );
	}

	/**
	 * تب «وضعیت و ابزارها».
	 *
	 * @return void
	 */
	protected function render_tools_tab() {
		$settings = class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ? Mega_Menu::settings() : array();
		?>
		<style>
			.manacore-brand-mark{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:#1d2327;color:#fff;font-weight:700;letter-spacing:.5px}
			.manacore-settings-header{display:flex;align-items:center;gap:12px;margin:14px 0 4px}
			.manacore-settings-header h1{margin:0;font-size:1.4rem}
			.manacore-settings-header p{margin:2px 0 0;color:#646970}
		</style>

		<form method="post" action="options.php" style="max-width:900px">
			<?php
			settings_fields( 'manacore_settings_group' );
			/* این تب فیلد مرئی برگه‌ی پخش دارد، پس پنهان نمی‌فرستیم. */
			$this->hidden_fields( 'tools', false );
			?>
			<h2 class="title"><?php esc_html_e( 'تنظیم تند', 'manacore' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tools_watch_page"><?php esc_html_e( 'برگه‌ی پخش', 'manacore' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'manacore_watch_page',
								'id'                => 'tools_watch_page',
								'selected'          => (int) get_option( 'manacore_watch_page', 0 ),
								'show_option_none'  => __( '— خودکار —', 'manacore' ),
								'option_none_value' => 0,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'پنل مگامنو', 'manacore' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="manacore_settings[mega_enabled]" value="1" <?php checked( 1, (int) manacore_get_option( 'mega_enabled', 1 ) ); ?> />
							<?php esc_html_e( 'فعال باشد', 'manacore' ); ?>
						</label>
						&nbsp;
						<label>
							<?php esc_html_e( 'تعداد ژانرها:', 'manacore' ); ?>
							<input type="number" name="manacore_settings[mega_terms]" min="3" max="30"
								value="<?php echo esc_attr( (int) manacore_get_option( 'mega_terms', 12 ) ); ?>" class="small-text" />
						</label>
						<input type="hidden" name="manacore_settings[mega_taxonomy]" value="<?php echo esc_attr( isset( $settings['taxonomy'] ) ? $settings['taxonomy'] : 'genre' ); ?>" />
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'ذخیره‌ی تنظیم تند', 'manacore' ) ); ?>
		</form>

		<div class="manacore-tools" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));margin-top:16px">
			<?php
			$this->render_watch_status();
			$this->render_mega_status();
			$this->render_reports();
			?>
			<div class="card">
				<h2><?php esc_html_e( 'کش‌ها و پیوندهای یکتا', 'manacore' ); ?></h2>
				<p class="description"><?php esc_html_e( 'کش داده‌های مگامنو یک‌ساعته است؛ تغییر ترم‌ها یا تنظیمات خودش پاکش می‌کند. این دکمه برای پاک‌سازی دستی است.', 'manacore' ); ?></p>
				<?php
				$this->tool_button( 'flush-cache', __( 'پاک‌کردن کش', 'manacore' ) );
				$this->tool_button( 'flush-rewrite', __( 'بازسازی پیوندهای یکتا', 'manacore' ) );
				?>
			</div>
			<div class="card">
				<h2><?php esc_html_e( 'شورت‌کدها', 'manacore' ); ?></h2>
				<p class="description"><?php esc_html_e( 'برای قالب‌هایی که الگوهای کوهه را ندارند.', 'manacore' ); ?></p>
				<ul>
					<li><code>[manacore_mega_menu]</code> — <?php esc_html_e( 'پنل مگامنو', 'manacore' ); ?></li>
					<li><code>[manacore_player]</code> — <?php esc_html_e( 'پخش‌کننده (با manacore_id)', 'manacore' ); ?></li>
					<li><code>[manacore_request_form]</code> — <?php esc_html_e( 'فرم درخواست فیلم/سریال', 'manacore' ); ?></li>
					<li><code>[manacore_requests]</code> — <?php esc_html_e( 'تخته‌ی درخواست‌ها با رأی‌گیری', 'manacore' ); ?></li>
					<li><code>[manacore_ad position="top"]</code> — <?php esc_html_e( 'جایگاه تبلیغاتی', 'manacore' ); ?></li>
				</ul>
			</div>
			<?php
			$this->render_demo_card();
			$this->render_backup_card();
			?>
		</div>
		<?php
	}

	/**
	 * کارت «محتوای نمایشی».
	 *
	 * خریدار افزونه باید در چند دقیقه سایتی پُر ببیند؛ این ابزار یک دسته
	 * محتوای نمونه‌ی هم‌خوان با ساختار افزونه (فیلم/سریال/قسمت/عوامل/مجموعه
	 * + لینک دانلود + دیدگاه) می‌سازد و همان‌ها را هم برمی‌دارد.
	 *
	 * @return void
	 */
	protected function render_demo_card() {
		if ( ! class_exists( __NAMESPACE__ . '\\Demo' ) ) {
			return;
		}

		$status = Demo::status();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'محتوای نمایشی', 'manacore' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'برای دیدن سریع قالب و افزونه: چند فیلم، سریال با قسمت‌ها، عوامل، مجموعه و دیدگاه نمونه ساخته می‌شود. همه‌ی این نوشته‌ها با نشانه‌ی «نمایشی» ذخیره می‌شوند تا فقط همین‌ها قابل حذف باشند.', 'manacore' ); ?>
			</p>
			<?php if ( ! empty( $status['total'] ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: ۱: تعداد کل نوشته‌ها، ۲: فیلم، ۳: سریال، ۴: قسمت */
						esc_html__( 'اکنون %1$s نوشته‌ی نمایشی هست (%2$s فیلم، %3$s سریال، %4$s قسمت).', 'manacore' ),
						esc_html( number_format_i18n( (int) $status['total'] ) ),
						esc_html( number_format_i18n( (int) $status['counts']['movie'] ) ),
						esc_html( number_format_i18n( (int) $status['counts']['series'] ) ),
						esc_html( number_format_i18n( (int) $status['counts']['episode'] ) )
					);
					?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'هنوز محتوای نمایشی‌ای ساخته نشده است.', 'manacore' ); ?></p>
			<?php endif; ?>
			<?php
			if ( empty( $status['total'] ) ) {
				$this->tool_button( 'demo-create', __( 'ساخت محتوای نمایشی', 'manacore' ) );
			}
			$this->tool_button(
				'demo-remove',
				__( 'حذف محتوای نمایشی', 'manacore' ),
				__( 'مطمئنید؟ همه‌ی نوشته‌های نمایشی برای همیشه حذف می‌شوند.', 'manacore' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * کارت «پشتیبان‌گیری و بازگردانی تنظیمات».
	 *
	 * @return void
	 */
	protected function render_backup_card() {
		if ( ! class_exists( __NAMESPACE__ . '\\Portability' ) ) {
			return;
		}

		$summary = Portability::summary();
		?>
		<div class="card">
			<h2><?php esc_html_e( 'پشتیبان‌گیری تنظیمات', 'manacore' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: ۱: تعداد کلیدهای تنظیمات، ۲: تعداد جایگاه‌های تبلیغاتی */
					esc_html__( 'همه‌ی %1$s کلید تنظیمات (به‌همراه %2$s جایگاه تبلیغاتی) در یک فایل JSON؛ برای انتقال سایت از محیط آزمایش به سایت اصلی یا بازگردانی پس از بازنشانی.', 'manacore' ),
					esc_html( number_format_i18n( (int) $summary['keys'] ) ),
					esc_html( number_format_i18n( (int) $summary['ads'] ) )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="manacore_export" />
				<?php wp_nonce_field( 'manacore_export' ); ?>
				<?php submit_button( __( 'دریافت فایل پشتیبان', 'manacore' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="manacore_import" />
				<?php wp_nonce_field( 'manacore_import' ); ?>
				<p>
					<label for="manacore_import_file"><strong><?php esc_html_e( 'فایل پشتیبان (JSON)', 'manacore' ); ?></strong></label><br />
					<input type="file" name="manacore_import_file" id="manacore_import_file" accept="application/json,.json" />
				</p>
				<p>
					<label for="manacore_import_json"><strong><?php esc_html_e( 'یا متن JSON', 'manacore' ); ?></strong></label><br />
					<textarea name="manacore_import_json" id="manacore_import_json" rows="4" class="large-text code" dir="ltr" placeholder='{"format":"manacore-settings","settings":{…}}'></textarea>
				</p>
				<p>
					<label>
						<input type="checkbox" name="manacore_import_dry" value="1" />
						<?php esc_html_e( 'فقط بررسی کن (هیچ چیزی ذخیره نشود)', 'manacore' ); ?>
					</label>
				</p>
				<?php
				/*
				 * کلیدهای ناشناخته نادیده گرفته می‌شوند و هر تب با پاک‌سازی
				 * خودش وارد می‌شود؛ پس فایلِ ناقص، بقیه‌ی تنظیمات را صفر
				 * نمی‌کند.
				 */
				submit_button( __( 'بازگردانی تنظیمات', 'manacore' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'تنظیمات فعلی با مقادیر این فایل جایگزین می‌شود. ادامه؟', 'manacore' ) ) . "');" ) );
				?>
			</form>
		</div>
		<?php
	}
}

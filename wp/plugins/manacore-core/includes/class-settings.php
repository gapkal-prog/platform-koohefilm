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

		/* ابزارهای تب‌ها (نانِس + دسترسی مدیریت). */
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
			'reports'   => __( 'گزارش خرابی لینک', 'manacore' ),
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
			'link-tool-ok'  => array( 'success', __( 'ابزار لینک اجرا شد؛ نتیجه پایین همین صفحه آمده است.', 'manacore' ) ),
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
	 * اجرای ابزارهای پنل (هر ابزار به تب خودش برمی‌گردد).
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

		/*
		 * هر ابزار به تب خودش برمی‌گردد تا مدیر همان‌جا پیام را ببیند؛
		 * ابزارهای نگه‌داری و محتوای نمایشی در تب «ابزارها» می‌مانند.
		 */
		$back_to = array(
			'watch-page'   => 'watch',
			'request-page' => 'requests',
			'mega-rebuild' => 'mega',
		);
		$tab     = isset( $back_to[ $tool ] ) ? $back_to[ $tool ] : 'tools';

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
					'page'            => 'manacore',
					'tab'             => $tab,
					'manacore_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * رندر صفحه‌ی تنظیمات.
	 *
	 * ساختار: سرصفحه‌ی برند + نوار تب + محتوای تب جاری. هر تب یک موضوع
	 * کامل است و همه‌ی بخش‌هایش قالب مشترک «پنل» را دارند تا ظاهر
	 * صفحه یک‌دست بماند.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = self::tabs();
		$tab  = self::current_tab();
		?>
		<div class="wrap manacore-settings">
			<div class="manacore-settings-header">
				<span class="manacore-brand-mark">MC</span>
				<div>
					<h1><?php esc_html_e( 'ManaCore — هسته‌ی سایت فیلم و سریال', 'manacore' ); ?></h1>
					<p><?php esc_html_e( 'پخش و دانلود، درخواست‌ها، گزارش‌های خرابی لینک، مگامنو و تحلیل آمار.', 'manacore' ); ?></p>
				</div>
			</div>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( self::tab_url( $key ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'watch':
					$this->render_watch_tab();
					break;

				case 'requests':
					$this->render_requests_tab();
					break;

				case 'reports':
					$this->render_reports_tab();
					break;

				case 'mega':
					$this->render_mega_tab();
					break;

				case 'analytics':
					$this->render_analytics_tab();
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
	 * @param string $tab            تب جاری.
	 * @param bool   $watch_fallback ارسال شناسه‌ی برگه‌ی پخش به‌صورت پنهان؛
	 *                               در تبی که خودش این فیلد را دارد `false`.
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
	 * نشانی یک تب از صفحه‌ی تنظیمات.
	 *
	 * @param string $tab کلید تب.
	 * @return string
	 */
	protected static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => 'manacore',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * باز کردن یک بخش پنل.
	 *
	 * همه‌ی بخش‌های صفحه — چه فرم تنظیمات و چه کارت وضعیت — همین قالب را
	 * دارند تا ظاهر یک‌دست بماند. استایل در `assets/css/admin.css` است و
	 * هیچ‌جای صفحه استایل درون‌خطی تزریق نمی‌شود.
	 *
	 * @param string $title سرتیتر بخش.
	 * @param string $hint  توضیح کوتاه زیر سرتیتر (اختیاری).
	 * @param string $meta  نشان کنار سرتیتر؛ HTML امنِ آماده (اختیاری).
	 * @return void
	 */
	protected function panel_open( $title, $hint = '', $meta = '' ) {
		?>
		<section class="manacore-panel">
			<h2 class="manacore-panel__title">
				<?php echo esc_html( $title ); ?>
				<?php echo $meta; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML امن، در همین کلاس ساخته می‌شود. ?>
			</h2>
			<?php if ( '' !== $hint ) : ?>
				<p class="manacore-panel__hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
		<?php
	}

	/**
	 * بستن بخش پنل.
	 *
	 * @return void
	 */
	protected function panel_close() {
		?>
		</section>
		<?php
	}

	/**
	 * ردیف شمارنده‌های یک بخش.
	 *
	 * @param array<int,array<int,string>> $stats جفت‌های «برچسب، مقدار».
	 * @return void
	 */
	protected function stat_list( $stats ) {
		?>
		<ul class="manacore-stats">
			<?php foreach ( $stats as $stat ) : ?>
				<li>
					<span><?php echo esc_html( $stat[0] ); ?></span>
					<b><?php echo esc_html( manacore_fa_digits( (string) $stat[1] ) ); ?></b>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * ردیف دکمه‌های پیوندی یک بخش.
	 *
	 * @param array<int,array<int,string>> $links جفت‌های «برچسب، نشانی».
	 * @return void
	 */
	protected function action_links( $links ) {
		if ( ! $links ) {
			return;
		}
		?>
		<p class="manacore-actions">
			<?php foreach ( $links as $link ) : ?>
				<a class="button" href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php
	}

	/**
	 * دکمه‌ی ابزار (فرم امن admin-post).
	 *
	 * @param string $tool    کلید ابزار.
	 * @param string $label   برچسب.
	 * @param string $confirm پیام تأیید اختیاری.
	 * @param bool   $primary دکمه‌ی اصلی باشد.
	 * @return void
	 */
	protected function tool_button( $tool, $label, $confirm = '', $primary = false ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="manacore-tool__form">
			<input type="hidden" name="action" value="manacore_tool" />
			<input type="hidden" name="tool" value="<?php echo esc_attr( $tool ); ?>" />
			<?php wp_nonce_field( 'manacore_tool_' . $tool ); ?>
			<button type="submit" class="button<?php echo $primary ? ' button-primary' : ''; ?>"<?php echo '' !== $confirm ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * یک ردیف ابزار: عنوان، توضیح و دکمه.
	 *
	 * @param string $tool    کلید ابزار.
	 * @param string $label   برچسب دکمه.
	 * @param string $title   عنوان ردیف.
	 * @param string $hint    توضیح ردیف (اختیاری).
	 * @param string $confirm پیام تأیید (اختیاری).
	 * @param bool   $primary دکمه‌ی اصلی باشد.
	 * @return void
	 */
	protected function tool_row( $tool, $label, $title, $hint = '', $confirm = '', $primary = false ) {
		?>
		<div class="manacore-tool">
			<div class="manacore-tool__text">
				<strong><?php echo esc_html( $title ); ?></strong>
				<?php if ( '' !== $hint ) : ?>
					<span><?php echo esc_html( $hint ); ?></span>
				<?php endif; ?>
			</div>
			<?php $this->tool_button( $tool, $label, $confirm, $primary ); ?>
		</div>
		<?php
	}

	/**
	 * وضعیت یک کارت به‌شکل نشان خوانا (تیک/هشدار + متن).
	 *
	 * @param bool   $ok   وضعیت درست است؟
	 * @param string $text متن وضعیت.
	 * @return void
	 */
	protected function status_line( $ok, $text ) {
		?>
		<p class="manacore-status<?php echo $ok ? ' is-ok' : ' is-warn'; ?>">
			<span class="dashicons <?php echo $ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
			<span><?php echo esc_html( $text ); ?></span>
		</p>
		<?php
	}

	/**
	 * تب «تنظیمات عمومی»: نشانی‌ها، امکانات، نمایش و کیفیت‌ها.
	 *
	 * @return void
	 */
	protected function render_general_tab() {
		$this->tab_form(
			'general',
			function () {
				$this->panel_open(
					__( 'نشانی‌های یکتا (Slug)', 'manacore' ),
					__( 'پس از تغییر، به تنظیمات » پیوندهای یکتا بروید و یک‌بار ذخیره کنید.', 'manacore' )
				);

				$slugs = array(
					'slug_movie'      => __( 'فیلم', 'manacore' ),
					'slug_series'     => __( 'سریال', 'manacore' ),
					'slug_anime'      => __( 'انیمه', 'manacore' ),
					'slug_episode'    => __( 'قسمت', 'manacore' ),
					'slug_person'     => __( 'عوامل', 'manacore' ),
					'slug_collection' => __( 'مجموعه', 'manacore' ),
				);
				?>
				<table class="form-table" role="presentation">
					<?php foreach ( $slugs as $key => $label ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( $key ); ?>"
									name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( manacore_get_option( $key ) ); ?>"
									class="regular-text" dir="ltr" />
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php
				$this->panel_close();

				$this->panel_open( __( 'امکانات', 'manacore' ), __( 'خاموش‌کردن هر گزینه فقط همان قابلیت را از دید کاربران پنهان می‌کند؛ داده‌ها پاک نمی‌شوند.', 'manacore' ) );

				/*
				 * «شمارش تماشا» عمداً توضیح دارد: عدد آمار باید از رویداد
				 * واقعی (شروع پخش) بیاید، نه از بازشدن صفحه.
				 */
				$toggles = array(
					'enable_ratings'   => array( __( 'فعال بودن امتیازدهی کاربران', 'manacore' ), '' ),
					'enable_watchlist' => array( __( 'فعال بودن لیست تماشا', 'manacore' ), '' ),
					'enable_views'     => array( __( 'شمارش تماشا (شروع واقعی پخش)', 'manacore' ), __( 'فقط وقتی کاربر پخش را شروع کند شمرده می‌شود؛ بازشدن صفحه و رفرش، آمار را بالا نمی‌برد.', 'manacore' ) ),
					'links_login_only' => array( __( 'نمایش لینک‌ها فقط برای کاربران وارد شده', 'manacore' ), '' ),
				);
				?>
				<table class="form-table" role="presentation">
					<?php foreach ( $toggles as $key => $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row[0] ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
										value="1" <?php checked( 1, (int) manacore_get_option( $key, 0 ) ); ?> />
									<?php esc_html_e( 'فعال', 'manacore' ); ?>
								</label>
								<?php if ( '' !== $row[1] ) : ?>
									<p class="description"><?php echo esc_html( $row[1] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php
				$this->panel_close();

				$this->panel_open( __( 'نمایش', 'manacore' ) );
				?>
				<table class="form-table" role="presentation">
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
				<?php
				$this->panel_close();

				$this->panel_open( __( 'کیفیت‌های سفارشی', 'manacore' ), __( 'کیفیت‌هایی که افزونه نمی‌شناسد را این‌جا اضافه کنید تا در فهرست کیفیت‌های باکس دانلود و ویرایشگر بیایند.', 'manacore' ) );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="custom_qualities"><?php esc_html_e( 'کیفیت‌های اضافه', 'manacore' ); ?></label></th>
						<td>
							<textarea id="custom_qualities" name="manacore_settings[custom_qualities]" rows="5" class="large-text code"
								placeholder="key|برچسب نمایشی"><?php echo esc_textarea( manacore_get_option( 'custom_qualities', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'هر خط یک کیفیت. نمونه: remux|BluRay REMUX', 'manacore' ); ?></p>
						</td>
					</tr>
				</table>
				<?php
				$this->panel_close();
			}
		);
	}

	/**
	 * تب «پخش و دانلود»: برگه‌ی پخش، متن‌ها، اشتراک و امنیت لینک دانلود.
	 *
	 * @return void
	 */
	protected function render_watch_tab() {
		$page_id = (int) get_option( 'manacore_watch_page', 0 );

		$this->tab_form(
			'watch',
			function () use ( $page_id ) {
				$this->panel_open(
					__( 'برگه‌ی پخش', 'manacore' ),
					__( 'همه‌ی دکمه‌های «پخش» به این برگه می‌روند و شناسه‌ی اثر با پارامتر «manacore_id» می‌آید. خالی بگذارید تا برگه‌ی دارای نامک watch خودکار پیدا شود.', 'manacore' )
				);
				?>
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
						</td>
					</tr>
				</table>
				<?php
				$this->panel_close();

				$this->panel_open( __( 'متن‌های پیش‌فرض', 'manacore' ), __( 'این متن‌ها وقتی نمایش داده می‌شوند که اثر خودش یادداشت اختصاصی نداشته باشد.', 'manacore' ) );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="download_notice_text"><?php esc_html_e( 'یادداشت باکس دانلود', 'manacore' ); ?></label></th>
						<td>
							<textarea id="download_notice_text" name="manacore_settings[download_notice_text]" rows="3" class="large-text"><?php echo esc_textarea( manacore_get_option( 'download_notice_text', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'زیر سرتیتر باکس دانلود می‌آید. خالی = متن پیش‌فرض افزونه.', 'manacore' ); ?></p>
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
				</table>
				<?php
				$this->panel_close();

				$this->panel_open( __( 'اشتراک', 'manacore' ) );
				?>
				<table class="form-table" role="presentation">
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
				<?php
				$this->panel_close();

				$this->panel_open( __( 'لینک دانلود امضاشده', 'manacore' ) );
				?>
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
							<p class="description">
								<?php
								printf(
									/* translators: ۱: کمترین اعتبار، ۲: بیشترین اعتبار */
									esc_html__( 'پیش‌فرض ۱۴۴۰ دقیقه (۲۴ ساعت). بازه‌ی مجاز: %1$s تا %2$s دقیقه.', 'manacore' ),
									esc_html( manacore_fa_digits( (string) Downloads::MIN_TTL ) ),
									esc_html( manacore_fa_digits( (string) Downloads::MAX_TTL ) )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php
				$this->panel_close();
			}
		);

		/* اقدام‌ها بیرون از فرم تنظیمات‌اند تا فرم تودرتو ساخته نشود. */
		$this->watch_status_panel();
	}

	/**
	 * کارت وضعیت برگه‌ی پخش و اقدام ساخت/بازیابی آن.
	 *
	 * @return void
	 */
	protected function watch_status_panel() {
		$status = Player::page_status();

		$this->panel_open( __( 'وضعیت برگه‌ی پخش', 'manacore' ) );

		if ( $status['exists'] && $status['published'] ) {
			/* translators: %d: شناسه‌ی برگه */
			$this->status_line( true, sprintf( __( 'برگه آماده است (شناسه %d).', 'manacore' ), (int) $status['id'] ) );
		} else {
			$this->status_line( false, __( 'برگه‌ی پخش پیدا نشد یا منتشر نشده است؛ دکمه‌های «پخش» فقط مُدال را باز می‌کنند.', 'manacore' ) );
		}

		$links = array();

		if ( '' !== $status['url'] ) {
			$links[] = array( __( 'نمایش برگه', 'manacore' ), $status['url'] );
		}

		if ( '' !== $status['edit_url'] ) {
			$links[] = array( __( 'ویرایش در ویرایشگر', 'manacore' ), $status['edit_url'] );
		}

		$this->action_links( $links );

		$this->tool_row(
			'watch-page',
			__( 'ساخت / بازیابی برگه', 'manacore' ),
			__( 'برگه‌ی پخش با نامک watch', 'manacore' ),
			__( 'برگه با نامک «watch» ساخته می‌شود و قالب «پخش آنلاین» آن را پر می‌کند. برگه‌ی موجود هرگز بازنویسی نمی‌شود.', 'manacore' ),
			'',
			true
		);

		$this->panel_close();
	}

	/**
	 * تب «درخواست‌ها»: تنظیمات فرم و تخته + وضعیت و اقدام‌ها.
	 *
	 * @return void
	 */
	protected function render_requests_tab() {
		$this->tab_form(
			'requests',
			function () {
				$this->panel_open(
					__( 'درخواست فیلم و سریال', 'manacore' ),
					__( 'کاربران عنوانی را که در آرشیو نیست ثبت می‌کنند، بقیه رأی می‌دهند و شما از فهرست «درخواست‌ها» بازبینی می‌کنید.', 'manacore' )
				);
				?>
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
				</table>
				<?php
				$this->panel_close();

				$this->panel_open( __( 'متن‌های فرم و تخته', 'manacore' ) );
				?>
				<table class="form-table" role="presentation">
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
				$this->panel_close();
			}
		);

		$this->requests_status_panel();
	}

	/**
	 * کارت وضعیت درخواست‌ها و اقدام ساخت برگه.
	 *
	 * @return void
	 */
	protected function requests_status_panel() {
		if ( ! class_exists( __NAMESPACE__ . '\\Requests' ) ) {
			return;
		}

		$counts = Requests::counts();
		$page   = Requests::page_url();

		$this->panel_open( __( 'وضعیت درخواست‌ها', 'manacore' ) );

		$this->stat_list(
			array(
				array( __( 'در انتظار بازبینی', 'manacore' ), number_format_i18n( (int) $counts['pending'] ) ),
				array( __( 'تأییدشده', 'manacore' ), number_format_i18n( (int) $counts['publish'] ) ),
				array( __( 'ردشده', 'manacore' ), number_format_i18n( (int) $counts['draft'] ) ),
			)
		);

		$links = array(
			array( __( 'فهرست درخواست‌ها', 'manacore' ), add_query_arg( array( 'post_type' => Requests::POST_TYPE ), admin_url( 'edit.php' ) ) ),
			array( __( 'در انتظار بازبینی', 'manacore' ), add_query_arg( array( 'post_type' => Requests::POST_TYPE, 'post_status' => 'pending' ), admin_url( 'edit.php' ) ) ),
		);

		if ( $page ) {
			$links[] = array( __( 'دیدن برگه‌ی درخواست‌ها', 'manacore' ), $page );
		}

		$this->action_links( $links );

		if ( ! $page ) {
			$this->tool_row(
				'request-page',
				__( 'ساخت برگه‌ی درخواست‌ها', 'manacore' ),
				__( 'برگه‌ی فرم و تخته‌ی درخواست‌ها', 'manacore' ),
				__( 'هنوز برگه‌ای برای درخواست‌ها ساخته نشده است؛ با این دکمه یک برگه با فرم و تخته ساخته می‌شود. در قالب‌های دیگر می‌توانید از شورت‌کدها استفاده کنید.', 'manacore' ),
				'',
				true
			);
		}

		$this->panel_close();

		$this->panel_open( __( 'نمایش در قالب دلخواه', 'manacore' ), __( 'اگر برگه‌ی درخواست‌ها را خودتان می‌سازید، این شورت‌کدها را در آن بگذارید.', 'manacore' ) );
		?>
		<ul class="manacore-codes">
			<li><code>[manacore_request_form]</code> — <?php esc_html_e( 'فرم ثبت درخواست', 'manacore' ); ?></li>
			<li><code>[manacore_requests orderby="votes"]</code> — <?php esc_html_e( 'تخته‌ی درخواست‌ها با رأی‌گیری', 'manacore' ); ?></li>
			<li><code>[manacore_my_requests]</code> — <?php esc_html_e( 'پنل «درخواست‌های من» برای کاربر واردشده', 'manacore' ); ?></li>
		</ul>
		<?php
		$this->panel_close();
	}

	/**
	 * تب «خرابی لینک‌ها»: کارهای باز + فهرست گزارش‌ها.
	 *
	 * @return void
	 */
	protected function render_reports_tab() {
		$this->open_work_panel();
		$this->reports_panel();
	}

	/**
	 * کارت «کارهای باز»: صف بازبینی سایت در یک نگاه.
	 *
	 * @return void
	 */
	protected function open_work_panel() {
		$summary = class_exists( __NAMESPACE__ . '\\Analytics' ) ? Analytics::summary() : array();

		$reports  = isset( $summary['reports_new'] ) ? (int) $summary['reports_new'] : 0;
		$requests = isset( $summary['requests_pending'] ) ? (int) $summary['requests_pending'] : 0;
		$comments = isset( $summary['comments_pending'] ) ? (int) $summary['comments_pending'] : 0;

		$this->panel_open( __( 'کارهای باز', 'manacore' ), __( 'صف بازبینی سایت: گزارش‌ها، درخواست‌ها و دیدگاه‌های در انتظار.', 'manacore' ) );

		$this->stat_list(
			array(
				array( __( 'گزارش خرابی تازه', 'manacore' ), number_format_i18n( $reports ) ),
				array( __( 'درخواست در انتظار', 'manacore' ), number_format_i18n( $requests ) ),
				array( __( 'دیدگاه در صف', 'manacore' ), number_format_i18n( $comments ) ),
			)
		);

		if ( ! $reports && ! $requests && ! $comments ) {
			$this->status_line( true, __( 'صف بازبینی خالی است. کار خوبی کرده‌اید!', 'manacore' ) );
		}

		$this->action_links(
			array(
				array( __( 'فهرست گزارش‌ها', 'manacore' ), add_query_arg( 'report_status', 'new', self::tab_url( 'reports' ) ) ),
				array( __( 'درخواست‌های در انتظار', 'manacore' ), add_query_arg( array( 'post_type' => 'manacore_request', 'post_status' => 'pending' ), admin_url( 'edit.php' ) ) ),
				array( __( 'دیدگاه‌های در صف', 'manacore' ), add_query_arg( 'post_status', 'pending', admin_url( 'edit-comments.php' ) ) ),
			)
		);

		$this->panel_close();
	}

	/**
	 * فهرست گزارش‌های خرابی لینک با فیلتر وضعیت و صفحه‌بندی.
	 *
	 * @return void
	 */
	protected function reports_panel() {
		if ( ! class_exists( __NAMESPACE__ . '\\Reports' ) ) {
			return;
		}

		$counts = Reports::counts();
		$status = isset( $_GET['report_status'] ) ? sanitize_key( wp_unslash( $_GET['report_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = in_array( $status, Reports::STATUSES, true ) ? $status : '';
		$paged  = isset( $_GET['report_page'] ) ? max( 1, absint( wp_unslash( $_GET['report_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$per_page = 20;
		$total    = array_sum( $counts );
		$in_view  = '' === $status ? $total : (int) $counts[ $status ];
		$reports  = Reports::query(
			array(
				'status'   => $status,
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);

		$labels = array(
			'new'     => __( 'تازه', 'manacore' ),
			'fixed'   => __( 'اصلاح‌شده', 'manacore' ),
			'ignored' => __( 'نادیده‌گرفته‌شده', 'manacore' ),
		);

		$this->panel_open(
			__( 'گزارش‌های خرابی لینک', 'manacore' ),
			__( 'کاربران وقتی لینکی کار نکند، از همان ردیف جدول دانلود گزارش می‌دهند. هر ردیف را می‌توانید «اصلاح‌شده»، «نادیده‌گرفته‌شده» یا حذف علامت بزنید.', 'manacore' )
		);

		$filters  = array(
			array(
				'label'  => __( 'همه', 'manacore' ),
				'url'    => self::tab_url( 'reports' ),
				'count'  => $total,
				'active' => '' === $status,
			),
		);

		foreach ( $labels as $key => $label ) {
			$filters[] = array(
				'label'  => $label,
				'url'    => add_query_arg( 'report_status', $key, self::tab_url( 'reports' ) ),
				'count'  => (int) $counts[ $key ],
				'active' => $key === $status,
			);
		}
		?>
		<ul class="subsubsub">
			<?php foreach ( $filters as $index => $filter ) : ?>
				<li>
					<a href="<?php echo esc_url( $filter['url'] ); ?>"<?php echo $filter['active'] ? ' class="current" aria-current="page"' : ''; ?>>
						<?php echo esc_html( $filter['label'] ); ?>
						<span class="count">(<?php echo esc_html( manacore_fa_digits( number_format_i18n( (int) $filter['count'] ) ) ); ?>)</span>
					</a>
					<?php echo $index < count( $filters ) - 1 ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="clear"></div>

		<?php if ( ! $reports ) : ?>
			<p class="manacore-empty"><?php esc_html_e( 'گزارشی با این وضعیت نیست.', 'manacore' ); ?></p>
		<?php else : ?>
			<table class="widefat striped manacore-reports">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'اثر', 'manacore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'کیفیت', 'manacore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'لینک', 'manacore' ); ?></th>
						<th scope="col" class="column-reason"><?php esc_html_e( 'توضیح کاربر', 'manacore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'تاریخ', 'manacore' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'کنش', 'manacore' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $reports as $report ) : ?>
						<?php
						$post_id = (int) $report['post_id'];
						$edit    = get_edit_post_link( $post_id );
						$title   = get_the_title( $post_id );
						$title   = '' !== $title ? $title : '#' . $post_id;
						?>
						<tr>
							<td class="column-title">
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $title ); ?>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( (string) $report['quality'] ); ?></code></td>
							<td class="column-link">
								<?php if ( '' !== (string) $report['link_url'] ) : ?>
									<a href="<?php echo esc_url( (string) $report['link_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
										<?php echo esc_html( '' !== (string) $report['link_label'] ? mb_substr( (string) $report['link_label'], 0, 40 ) : __( 'بازکردن لینک', 'manacore' ) ); ?>
									</a>
								<?php else : ?>
									<?php esc_html_e( 'لینک ثبت نشده', 'manacore' ); ?>
								<?php endif; ?>
							</td>
							<td class="column-reason"><?php echo esc_html( (string) $report['reason'] ); ?></td>
							<td><time datetime="<?php echo esc_attr( (string) $report['created_at'] ); ?>"><?php echo esc_html( (string) $report['created_at'] ); ?></time></td>
							<td class="column-actions">
								<?php
								$this->report_action( (int) $report['id'], 'fixed', __( 'اصلاح شد', 'manacore' ), $status );
								$this->report_action( (int) $report['id'], 'ignored', __( 'نادیده بگیر', 'manacore' ), $status );
								$this->report_action( (int) $report['id'], 'delete', __( 'حذف', 'manacore' ), $status );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$pages = (int) ceil( $in_view / $per_page );

			if ( $pages > 1 ) {
				$base = '' === $status
					? add_query_arg( 'report_page', '%#%', self::tab_url( 'reports' ) )
					: add_query_arg(
						array(
							'report_status' => $status,
							'report_page'   => '%#%',
						),
						self::tab_url( 'reports' )
					);

				$pagination = paginate_links(
					array(
						'base'      => $base,
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '‹',
						'next_text' => '›',
						'type'      => 'plain',
					)
				);

				if ( $pagination ) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . $pagination . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی امن `paginate_links()`.
				}
			}
			?>
		<?php endif; ?>
		<?php
		$this->panel_close();
	}

	/**
	 * پیوند کنش روی یک گزارش (با نانِس).
	 *
	 * @param int    $id     شناسه‌ی گزارش.
	 * @param string $action کنش.
	 * @param string $label  برچسب.
	 * @param string $status فیلتر جاری فهرست (برای بازگشت به همان نما).
	 * @return void
	 */
	protected function report_action( $id, $action, $label, $status = '' ) {
		$args = array(
			'action'        => 'manacore_report_action',
			'report_id'     => (int) $id,
			'report_action' => $action,
		);

		if ( '' !== $status ) {
			$args['report_status'] = $status;
		}

		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );

		printf(
			'<a class="button button-small" href="%s">%s</a> ',
			esc_url( wp_nonce_url( $url, 'manacore_report_' . (int) $id ) ),
			esc_html( $label )
		);
	}

	/**
	 * تب «مگامنو»: تنظیمات پنل + وضعیت و بازسازی.
	 *
	 * @return void
	 */
	protected function render_mega_tab() {
		$settings = class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ? Mega_Menu::settings() : array();
		$taxonomy = isset( $settings['taxonomy'] ) ? (string) $settings['taxonomy'] : 'genre';

		$this->tab_form(
			'mega',
			function () use ( $taxonomy ) {
				$this->panel_open(
					__( 'پنل مگامنو', 'manacore' ),
					__( 'پنل کشویی ژانرها که در فهرست راهبری قالب باز می‌شود.', 'manacore' )
				);
				?>
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
								foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
									printf(
										'<option value="%1$s" %2$s>%3$s</option>',
										esc_attr( $tax->name ),
										selected( $taxonomy, $tax->name, false ),
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
				<?php
				$this->panel_close();

				$this->panel_open( __( 'متن‌های پنل', 'manacore' ), __( 'خالی بگذارید تا متن پیش‌فرض قالب بیاید.', 'manacore' ) );

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
				?>
				<table class="form-table" role="presentation">
					<?php foreach ( $texts as $key => $row ) : ?>
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
				$this->panel_close();
			}
		);

		$this->mega_status_panel();
	}

	/**
	 * کارت وضعیت مگامنو و بازسازی فهرست راهبری.
	 *
	 * @return void
	 */
	protected function mega_status_panel() {
		$menu_id  = function_exists( 'koohe_primary_navigation_id' ) ? (int) koohe_primary_navigation_id() : 0;
		$menu     = $menu_id ? get_post( $menu_id ) : null;
		$content  = $menu ? (string) $menu->post_content : '';
		$has_mega = false !== strpos( $content, 'koohe-mega' );
		$terms    = class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ? count( Mega_Menu::menu_terms( 'genre', 30 ) ) : 0;

		$this->panel_open( __( 'وضعیت مگامنو', 'manacore' ) );

		if ( $has_mega ) {
			$this->status_line( true, __( 'فهرست راهبری آیتم مگامنو دارد.', 'manacore' ) );
		} else {
			$this->status_line( false, __( 'فهرست راهبری فعلی آیتم مگامنو ندارد؛ با دکمه‌ی زیر بسازید.', 'manacore' ) );
		}

		$this->stat_list(
			array(
				array( __( 'ژانر آماده برای پنل', 'manacore' ), number_format_i18n( $terms ) ),
			)
		);

		$this->tool_row(
			'mega-rebuild',
			__( 'بازسازی فهرست راهبری', 'manacore' ),
			__( 'بازسازی فهرست راهبری مگامنو', 'manacore' ),
			__( 'با تغییر متن‌ها یا تعداد ژانرها در تب مگامنو، فهرست در نخستین بازدید خودکار به‌روز می‌شود؛ این دکمه برای بازسازی فوری است.', 'manacore' ),
			__( 'مطمئنید؟ فهرست راهبری با ساختار تازه‌ی قالب بازنویسی می‌شود.', 'manacore' ),
			true
		);

		$this->panel_close();
	}

	/**
	 * تب «تحلیل و آمار»: شمار کلی و جدول‌های برترین‌ها.
	 *
	 * @return void
	 */
	protected function render_analytics_tab() {
		if ( ! class_exists( __NAMESPACE__ . '\\Analytics' ) ) {
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

		$this->panel_open(
			__( 'شمار کلی', 'manacore' ),
			__( 'عددها هر ۵ دقیقه یک‌بار تازه می‌شوند (کش) تا بازکردن پیشخوان روی سایت بزرگ کند نشود.', 'manacore' )
		);
		?>
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
		<?php
		$this->action_links(
			array(
				array( __( 'گزارش‌های خرابی لینک', 'manacore' ), self::tab_url( 'reports' ) ),
				array( __( 'درخواست‌ها', 'manacore' ), self::tab_url( 'requests' ) ),
				array( __( 'دیدگاه‌های در صف', 'manacore' ), add_query_arg( 'post_status', 'pending', admin_url( 'edit-comments.php' ) ) ),
			)
		);
		$this->panel_close();
		?>
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
		$this->panel_open( $title );

		if ( ! $rows ) {
			?>
			<p class="description"><?php esc_html_e( 'داده‌ای در این بازه ثبت نشده است.', 'manacore' ); ?></p>
			<?php
			$this->panel_close();

			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'اثر', 'manacore' ); ?></th>
					<th scope="col"><?php echo esc_html( $value ); ?></th>
					<?php if ( $extra_key ) : ?>
						<th scope="col"><?php echo esc_html( 'open' === $extra_key ? __( 'باز', 'manacore' ) : __( 'وضعیت', 'manacore' ) ); ?></th>
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
		<?php
		$this->panel_close();
	}

	/**
	 * تب «ابزارها»: نگه‌داری، محتوای نمایشی و پشتیبان‌گیری تنظیمات.
	 *
	 * این تب هیچ فیلد تنظیماتی ندارد؛ همه‌ی کنش‌ها از `admin-post` با
	 * نانِس اختصاصی اجرا می‌شوند تا فرم تنظیمات تودرتو نشود.
	 *
	 * @return void
	 */
	protected function render_tools_tab() {
		$this->panel_open( __( 'نگه‌داری', 'manacore' ), __( 'کارهای دوره‌ای سایت. این دکمه‌ها چیزی را حذف نمی‌کنند.', 'manacore' ) );
		$this->tool_row(
			'flush-cache',
			__( 'پاک‌کردن کش', 'manacore' ),
			__( 'پاک‌کردن کش‌ها', 'manacore' ),
			__( 'کش داده‌های مگامنو یک‌ساعته است؛ تغییر ترم‌ها یا تنظیمات خودش پاکش می‌کند. این دکمه برای پاک‌سازی دستی است.', 'manacore' )
		);
		$this->tool_row(
			'flush-rewrite',
			__( 'بازسازی پیوندهای یکتا', 'manacore' ),
			__( 'بازسازی پیوندهای یکتا', 'manacore' ),
			__( 'پس از تغییر نشانی‌های یکتا (Slug) در تب عمومی لازم است.', 'manacore' )
		);
		$this->panel_close();

		$this->panel_open( __( 'شورت‌کدها', 'manacore' ), __( 'برای قالب‌هایی که الگوهای کوهه را ندارند.', 'manacore' ) );
		?>
		<ul class="manacore-codes">
			<li><code>[manacore_mega_menu]</code> — <?php esc_html_e( 'پنل مگامنو', 'manacore' ); ?></li>
			<li><code>[manacore_player]</code> — <?php esc_html_e( 'پخش‌کننده (با manacore_id)', 'manacore' ); ?></li>
			<li><code>[manacore_request_form]</code> — <?php esc_html_e( 'فرم درخواست فیلم/سریال', 'manacore' ); ?></li>
			<li><code>[manacore_requests]</code> — <?php esc_html_e( 'تخته‌ی درخواست‌ها با رأی‌گیری', 'manacore' ); ?></li>
			<li><code>[manacore_my_requests]</code> — <?php esc_html_e( 'پنل «درخواست‌های من»', 'manacore' ); ?></li>
		</ul>
		<?php
		$this->panel_close();

		$this->render_demo_panel();
		$this->render_link_tools_panel();
		$this->render_backup_panel();
	}

	/**
	 * بخش «محتوای نمایشی».
	 *
	 * خریدار افزونه باید در چند دقیقه سایتی پُر ببیند؛ این ابزار یک دسته
	 * محتوای نمونه‌ی هم‌خوان با ساختار افزونه (فیلم/سریال/قسمت/عوامل/مجموعه
	 * + لینک دانلود + دیدگاه) می‌سازد و همان‌ها را هم برمی‌دارد.
	 *
	 * @return void
	 */
	protected function render_demo_panel() {
		if ( ! class_exists( __NAMESPACE__ . '\\Demo' ) ) {
			return;
		}

		$status = Demo::status();

		$this->panel_open( __( 'محتوای نمایشی', 'manacore' ), __( 'همه‌ی نوشته‌های نمونه با نشانه‌ی «نمایشی» ذخیره می‌شوند تا فقط همین‌ها قابل حذف باشند.', 'manacore' ) );

		if ( ! empty( $status['total'] ) ) {
			$this->stat_list(
				array(
					array( __( 'کل نوشته‌ها', 'manacore' ), number_format_i18n( (int) $status['total'] ) ),
					array( __( 'فیلم', 'manacore' ), number_format_i18n( (int) $status['counts']['movie'] ) ),
					array( __( 'سریال', 'manacore' ), number_format_i18n( (int) $status['counts']['series'] ) ),
					array( __( 'قسمت', 'manacore' ), number_format_i18n( (int) $status['counts']['episode'] ) ),
				)
			);
		} else {
			$this->status_line( true, __( 'هنوز محتوای نمایشی‌ای ساخته نشده است.', 'manacore' ) );
		}

		$this->tool_row(
			'demo-create',
			__( 'ساخت محتوای نمایشی', 'manacore' ),
			__( 'ساخت محتوای نمایشی', 'manacore' ),
			__( 'چند فیلم، سریال با قسمت‌ها، عوامل، مجموعه و دیدگاه نمونه ساخته می‌شود.', 'manacore' ),
			'',
			empty( $status['total'] )
		);

		$this->tool_row(
			'demo-remove',
			__( 'حذف محتوای نمایشی', 'manacore' ),
			__( 'حذف محتوای نمایشی', 'manacore' ),
			__( 'فقط نوشته‌های نشان‌دار «نمایشی» حذف می‌شوند.', 'manacore' ),
			__( 'مطمئنید؟ همه‌ی نوشته‌های نمایشی برای همیشه حذف می‌شوند.', 'manacore' )
		);

		$this->panel_close();
	}

	/**
	 * بخش «پشتیبان‌گیری و بازگردانی تنظیمات».
	 *
	 * @return void
	 */
	protected function render_backup_panel() {
		if ( ! class_exists( __NAMESPACE__ . '\\Portability' ) ) {
			return;
		}

		$summary = Portability::summary();

		$this->panel_open( __( 'پشتیبان‌گیری تنظیمات', 'manacore' ) );
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: تعداد کلیدهای تنظیمات */
				esc_html__( 'همه‌ی %s کلید تنظیمات در یک فایل JSON؛ برای انتقال سایت از محیط آزمایش به سایت اصلی یا بازگردانی پس از بازنشانی.', 'manacore' ),
				esc_html( number_format_i18n( (int) $summary['keys'] ) )
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="manacore-backup">
			<input type="hidden" name="action" value="manacore_export" />
			<?php wp_nonce_field( 'manacore_export' ); ?>
			<?php submit_button( __( 'دریافت فایل پشتیبان', 'manacore' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr />

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="manacore-backup">
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
		<?php
		$this->panel_close();
	}

	/**
	 * بخش «نگه‌داشت لینک‌ها»: سه ابزار گروهی با پیش‌نمایش.
	 *
	 * هر ابزار فرم مستقل خودش را دارد (تب ابزارها فرم تنظیمات ندارد، پس
	 * فرم تودرتو ساخته نمی‌شود) و نتیجه‌ی آخرین اجرا از ترنزینت کاربر
	 * خوانده و همان‌جا نمایش داده می‌شود.
	 *
	 * @return void
	 */
	protected function render_link_tools_panel() {
		if ( ! class_exists( __NAMESPACE__ . '\\Link_Tools' ) ) {
			return;
		}

		$this->panel_open(
			__( 'نگه‌داشت لینک‌ها', 'manacore' ),
			__( 'کارهای گروهی روی لینک‌های دانلود: جایگزینی پیشوند نشانی، کپی گروه لینک و بازرسی ساختاری. هر ابزار پیش از نوشتن، پیش‌نمایش می‌دهد.', 'manacore' )
		);

		$action = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="manacore-link-tools">
			<form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url شده است. ?>" class="manacore-link-form">
				<input type="hidden" name="action" value="manacore_link_tool" />
				<input type="hidden" name="tool" value="domain" />
				<?php wp_nonce_field( 'manacore_link_tool_domain' ); ?>
				<label>
					<span><?php esc_html_e( 'از (نشانی کنونی)', 'manacore' ); ?></span>
					<input type="text" name="from" dir="ltr" placeholder="https://old.example.com/files" required />
				</label>
				<label>
					<span><?php esc_html_e( 'به (نشانی تازه)', 'manacore' ); ?></span>
					<input type="text" name="to" dir="ltr" placeholder="https://new.example.com/files" required />
				</label>
				<label class="manacore-link-form__check">
					<input type="checkbox" name="apply" value="1" />
					<span><?php esc_html_e( 'اعمال کن (بدون تیک: فقط پیش‌نمایش)', 'manacore' ); ?></span>
				</label>
				<?php submit_button( __( 'اجرا', 'manacore' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url شده است. ?>" class="manacore-link-form">
				<input type="hidden" name="action" value="manacore_link_tool" />
				<input type="hidden" name="tool" value="copy" />
				<?php wp_nonce_field( 'manacore_link_tool_copy' ); ?>
				<label>
					<span><?php esc_html_e( 'شناسه‌ی نوشته‌ی مبدأ', 'manacore' ); ?></span>
					<input type="number" name="source" min="1" step="1" required />
				</label>
				<label>
					<span><?php esc_html_e( 'شناسه‌های مقصد (با ویرگول)', 'manacore' ); ?></span>
					<input type="text" name="targets" dir="ltr" placeholder="120,121,122" required />
				</label>
				<label>
					<span><?php esc_html_e( 'حالت', 'manacore' ); ?></span>
					<select name="mode">
						<option value="missing"><?php esc_html_e( 'فقط نوشته‌های بی‌لینک', 'manacore' ); ?></option>
						<option value="append"><?php esc_html_e( 'افزودن به گروه‌های موجود', 'manacore' ); ?></option>
						<option value="replace"><?php esc_html_e( 'جایگزینی کامل لینک‌ها', 'manacore' ); ?></option>
					</select>
				</label>
				<?php submit_button( __( 'کپی کن', 'manacore' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url شده است. ?>" class="manacore-link-form">
				<input type="hidden" name="action" value="manacore_link_tool" />
				<input type="hidden" name="tool" value="audit" />
				<?php wp_nonce_field( 'manacore_link_tool_audit' ); ?>
				<p class="description"><?php esc_html_e( 'لینک‌های بی‌کیفیت، بی‌حجم، با کیفیت تکراری و نشانی‌های بدون پسوند فایل شناخته‌شده را فهرست می‌کند. هیچ چیزی نوشته نمی‌شود.', 'manacore' ); ?></p>
				<?php submit_button( __( 'بازرسی لینک‌ها', 'manacore' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
		$this->render_link_tool_result();

		$this->panel_close();
	}

	/**
	 * نمایش نتیجه‌ی آخرین اجرای ابزار لینک.
	 *
	 * @return void
	 */
	protected function render_link_tool_result() {
		$result = Link_Tools::take_result();

		if ( ! $result ) {
			return;
		}

		$tool = isset( $result['tool'] ) ? (string) $result['tool'] : '';

		if ( ! empty( $result['reason'] ) ) {
			$this->status_line( false, Link_Tools::reason_label( (string) $result['reason'] ) );

			return;
		}

		if ( 'domain' === $tool ) {
			$this->stat_list(
				array(
					array( __( 'نوشته‌ی بازرسی‌شده', 'manacore' ), number_format_i18n( (int) $result['scanned'] ) ),
					array( __( 'نوشته‌ی تغییرکرده', 'manacore' ), number_format_i18n( (int) $result['posts'] ) ),
					array( __( 'لینک تغییرکرده', 'manacore' ), number_format_i18n( (int) $result['links'] ) ),
				)
			);

			$this->status_line(
				true,
				! empty( $result['dry_run'] )
					? __( 'پیش‌نمایش بود؛ هیچ چیزی نوشته نشد. برای اعمال، تیک «اعمال کن» را بزنید.', 'manacore' )
					: __( 'تغییرها ذخیره شد.', 'manacore' )
			);

			$this->render_link_result_rows( $result, 'samples' );
		}

		if ( 'copy' === $tool ) {
			$this->stat_list(
				array(
					array( __( 'کپی‌شده', 'manacore' ), number_format_i18n( (int) $result['copied'] ) ),
					array( __( 'ردشده', 'manacore' ), number_format_i18n( (int) $result['skipped'] ) ),
					array( __( 'شناسه‌ی نامعتبر', 'manacore' ), number_format_i18n( (int) $result['missing'] ) ),
				)
			);
			$this->render_link_result_rows( $result, 'details' );
		}

		if ( 'audit' === $tool ) {
			$labels = array(
				'bad-url'      => __( 'نشانی نامعتبر', 'manacore' ),
				'no-quality'   => __( 'بی‌کیفیت', 'manacore' ),
				'no-size'      => __( 'بی‌حجم', 'manacore' ),
				'dup-quality'  => __( 'کیفیت تکراری', 'manacore' ),
				'unknown-file' => __( 'بدون پسوند شناخته‌شده', 'manacore' ),
			);

			$stats = array();

			foreach ( (array) $result['totals'] as $key => $count ) {
				$stats[] = array( $labels[ $key ] ?? $key, number_format_i18n( (int) $count ) );
			}

			$this->stat_list( $stats );
			$this->render_link_result_rows( $result, 'rows', $labels );
		}

		if ( ! empty( $result['truncated'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: تعداد نوشته‌های بازرسی‌شده */
						__( 'فقط %s نوشته‌ی نخست بازرسی شد. برای سایت بزرگ‌تر، همان کار را با WP-CLI و پرچم `--limit` اجرا کنید.', 'manacore' ),
						number_format_i18n( Link_Tools::max_posts() )
					)
				)
			);
		}
	}

	/**
	 * جدول ردیف‌های نتیجه‌ی ابزار لینک.
	 *
	 * @param array  $result نتیجه.
	 * @param string $key    کلید ردیف‌ها (`samples`، `details` یا `rows`).
	 * @param array  $labels برچسب فارسی کدها (اختیاری).
	 * @return void
	 */
	protected function render_link_result_rows( $result, $key, $labels = array() ) {
		$rows = isset( $result[ $key ] ) ? (array) $result[ $key ] : array();

		if ( ! $rows ) {
			return;
		}

		?>
		<table class="widefat striped manacore-link-report">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'نوشته', 'manacore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'جزئیات', 'manacore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'نشانی', 'manacore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'کنش', 'manacore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['title'] ); ?> <span class="description">#<?php echo (int) $row['post_id']; ?></span></td>
						<td>
							<?php
							if ( isset( $row['issue'] ) ) {
								echo esc_html( $labels[ $row['issue'] ] ?? $row['issue'] );
							} elseif ( isset( $row['action'] ) ) {
								echo esc_html( $this->link_action_label( (string) $row['action'] ) );
							} elseif ( isset( $row['from'] ) ) {
								echo esc_html( (string) $row['from'] . ' → ' . (string) $row['to'] );
							}
							?>
						</td>
						<td dir="ltr">
							<?php
							if ( isset( $row['url'] ) ) {
								echo esc_html( (string) $row['url'] );
							} elseif ( isset( $row['label'] ) && '' !== $row['label'] ) {
								echo esc_html( (string) $row['label'] );
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $row['post_id'] ) ); ?>">
								<?php esc_html_e( 'ویرایش', 'manacore' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * برچسب فارسی کنش کپی گروه لینک.
	 *
	 * @param string $action کد کنش.
	 * @return string
	 */
	protected function link_action_label( $action ) {
		$labels = array(
			'replaced' => __( 'جایگزین شد', 'manacore' ),
			'appended' => __( 'افزوده شد', 'manacore' ),
			'skipped'  => __( 'لینک داشت؛ رد شد', 'manacore' ),
			'missing'  => __( 'نوشته پیدا نشد', 'manacore' ),
		);

		return $labels[ $action ] ?? $action;
	}
}

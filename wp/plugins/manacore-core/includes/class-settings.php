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
			'general' => __( 'تنظیمات عمومی', 'manacore' ),
			'watch'   => __( 'پخش و دانلود', 'manacore' ),
			'mega'    => __( 'مگامنو', 'manacore' ),
			'tools'   => __( 'وضعیت و ابزارها', 'manacore' ),
		);
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
			'failed'        => array( 'error', __( 'ابزار اجرا نشد؛ شرایط پیش‌نیاز را ببینید.', 'manacore' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
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

			case 'mega-rebuild':
				$notice = function_exists( 'koohe_rebuild_navigation' ) && koohe_rebuild_navigation() ? 'mega-ok' : 'failed';
				break;

			case 'flush-cache':
				if ( class_exists( __NAMESPACE__ . '\\Mega_Menu' ) ) {
					Mega_Menu::flush_cache();
				}
				delete_transient( 'manacore_home_stats' );
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
				case 'watch':
					$this->render_watch_tab();
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
					$toggles = array(
						'enable_ratings'   => __( 'فعال بودن امتیازدهی کاربران', 'manacore' ),
						'enable_watchlist' => __( 'فعال بودن لیست تماشا', 'manacore' ),
						'enable_views'     => __( 'شمارش بازدید', 'manacore' ),
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
		<?php
		$this->tab_form( 'watch', function () {
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput
		} );
	}

	/**
	 * تب «مگامنو»: تاکسونومی، تعداد، متن‌ها و کارت ویژه.
	 *
	 * @return void
	 */
	protected function render_mega_tab() {
		$settings = class_exists( __NAMESPACE__ . '\Mega_Menu' ) ? Mega_Menu::settings() : array();
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
				</ul>
			</div>
		</div>
		<?php
	}
}

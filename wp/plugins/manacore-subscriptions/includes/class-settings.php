<?php
/**
 * Subscriptions settings page (submenu of the ManaCore menu).
 *
 * Values live inside the shared `manacore_settings` option under the `sub_`
 * prefix so the core plugin stays the single source of truth.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Settings {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'manacore_sanitize_settings', array( $this, 'sanitize' ), 10, 2 );
	}

	/**
	 * Add the submenu page.
	 */
	public function menu() {
		add_submenu_page(
			'manacore',
			__( 'اشتراک‌ها', 'manacore' ),
			__( 'اشتراک‌ها', 'manacore' ),
			'manage_options',
			'manacore-subscriptions',
			array( $this, 'render' )
		);
	}

	/**
	 * Load the shared admin stylesheet on this page.
	 */
	public function assets() {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( (string) $screen->id, 'manacore-subscriptions' ) ) {
			return;
		}

		/*
		 * وابستگی به `manacore-admin` (استایل مشترک ManaCore) تا ظاهر
		 * سرصفحه/کارت‌ها یکی باشد و ترتیب بارگذاری هم تضمین شود. اگر
		 * افزونه‌ی هسته فعال نباشد، وردپرس این وابستگی را نادیده می‌گیرد.
		 */
		wp_enqueue_style(
			'manacore-subs-admin',
			MANACORE_SUBS_URL . 'assets/admin.css',
			array( 'manacore-admin' ),
			MANACORE_SUBS_VERSION
		);
	}

	/* ---------------------------------------------------------------------
	 * Fields
	 * ------------------------------------------------------------------ */

	/**
	 * Default values for every option this page owns.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'sub_enabled'              => 1,
			'sub_strict_levels'        => 1,
			'sub_default_level'        => 'basic',
			'sub_any_product'          => 1,
			'sub_allow_pending_cancel' => 1,
			'sub_product_map'          => '',
			'sub_page_id'              => 0,
			'sub_page_url'             => '',
			'sub_grant_on_order'       => 0,
			'sub_order_days'           => 30,
			'sub_gate_content'         => 0,
			'sub_teaser_words'         => 60,
			'sub_account_notice'       => 1,
		);
	}

	/**
	 * Register the settings, sections and fields.
	 */
	public function register() {
		register_setting(
			'manacore_subs_group',
			'manacore_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_page' ) )
		);

		add_settings_section(
			'manacore_subs_general',
			__( 'تنظیمات عمومی', 'manacore' ),
			function () {
				echo '<p>' . esc_html__( 'رفتار کلی سیستم اشتراک و سطح پیش‌فرض محتوای ویژه.', 'manacore' ) . '</p>';
			},
			'manacore-subscriptions'
		);

		add_settings_section(
			'manacore_subs_woo',
			__( 'اتصال به ووکامرس', 'manacore' ),
			array( $this, 'woo_section_intro' ),
			'manacore-subscriptions'
		);

		add_settings_section(
			'manacore_subs_pages',
			__( 'صفحات و نمایش', 'manacore' ),
			function () {
				echo '<p>' . esc_html__( 'مقصد دکمه‌ی «تهیه اشتراک» و نحوه‌ی نمایش محتوای قفل‌شده.', 'manacore' ) . '</p>';
			},
			'manacore-subscriptions'
		);

		$fields = array(
			array( 'sub_enabled', __( 'فعال بودن سیستم اشتراک', 'manacore' ), 'checkbox', 'manacore_subs_general', __( 'با غیرفعال کردن، همه‌ی لینک‌ها برای همه باز می‌شود.', 'manacore' ) ),
			array( 'sub_strict_levels', __( 'اعمال دقیق سطح‌ها', 'manacore' ), 'checkbox', 'manacore_subs_general', __( 'اگر غیرفعال باشد، هر اشتراک فعالی همه‌ی محتوای ویژه را باز می‌کند.', 'manacore' ) ),
			array( 'sub_default_level', __( 'سطح پیش‌فرض محتوای ویژه', 'manacore' ), 'level', 'manacore_subs_general', __( 'برای نوشته‌هایی که تیک «محتوای ویژه» دارند ولی سطحی برایشان انتخاب نشده است.', 'manacore' ) ),
			array( 'sub_any_product', __( 'هر محصول اشتراکی، دسترسی می‌دهد', 'manacore' ), 'checkbox', 'manacore_subs_woo', __( 'محصولاتی که در نگاشت زیر نیستند، سطح پیش‌فرض را می‌گیرند.', 'manacore' ) ),
			array( 'sub_allow_pending_cancel', __( 'پذیرش اشتراک‌های در انتظار لغو', 'manacore' ), 'checkbox', 'manacore_subs_woo', __( 'کاربری که اشتراکش را لغو کرده تا پایان دوره دسترسی دارد.', 'manacore' ) ),
			array( 'sub_product_map', __( 'نگاشت محصول به سطح', 'manacore' ), 'textarea', 'manacore_subs_woo', __( 'هر خط: شناسه‌ی محصول = نامک سطح — مثال: 128 = vip', 'manacore' ) ),
			array( 'sub_grant_on_order', __( 'اعطای دسترسی با سفارش ساده', 'manacore' ), 'checkbox', 'manacore_subs_woo', __( 'با تکمیل سفارشِ محصولِ نگاشت‌شده، اشتراک زمان‌دار داده می‌شود.', 'manacore' ) ),
			array( 'sub_order_days', __( 'مدت اعتبار سفارش ساده (روز)', 'manacore' ), 'number', 'manacore_subs_woo', '' ),
			array( 'sub_page_id', __( 'برگه‌ی تهیه اشتراک', 'manacore' ), 'page', 'manacore_subs_pages', __( 'در صورت خالی بودن، از آدرس دستی یا فروشگاه ووکامرس استفاده می‌شود.', 'manacore' ) ),
			array( 'sub_page_url', __( 'آدرس دستی', 'manacore' ), 'url', 'manacore_subs_pages', '' ),
			array( 'sub_gate_content', __( 'قفل کردن متن نوشته', 'manacore' ), 'checkbox', 'manacore_subs_pages', __( 'به‌صورت پیش‌فرض تنها لینک‌های دانلود قفل می‌شوند.', 'manacore' ) ),
			array( 'sub_teaser_words', __( 'تعداد کلمات پیش‌نمایش', 'manacore' ), 'number', 'manacore_subs_pages', '' ),
			array( 'sub_account_notice', __( 'نمایش وضعیت در حساب ووکامرس', 'manacore' ), 'checkbox', 'manacore_subs_pages', '' ),
		);

		foreach ( $fields as $field ) {
			list( $key, $label, $type, $section, $description ) = $field;

			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				'manacore-subscriptions',
				$section,
				array(
					'key'         => $key,
					'type'        => $type,
					'description' => $description,
					'label_for'   => $key,
				)
			);
		}
	}

	/**
	 * Intro paragraph for the WooCommerce section.
	 */
	public function woo_section_intro() {
		if ( manacore_subs_woo_active() ) {
			echo '<p><span class="manacore-pill is-on">' . esc_html__( 'ووکامرس اشتراک فعال است', 'manacore' ) . '</span></p>';
			return;
		}

		if ( manacore_subs_wc_active() ) {
			echo '<p><span class="manacore-pill is-off">' . esc_html__( 'افزونه‌ی WooCommerce Subscriptions نصب نیست', 'manacore' ) . '</span> ';
			esc_html_e( 'می‌توانید از اعطای دستی یا سفارش ساده استفاده کنید.', 'manacore' );
			echo '</p>';
			return;
		}

		echo '<p><span class="manacore-pill is-off">' . esc_html__( 'ووکامرس نصب نیست', 'manacore' ) . '</span> ';
		esc_html_e( 'سیستم اشتراک با اعطای دستی سطح دسترسی همچنان کار می‌کند.', 'manacore' );
		echo '</p>';
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$key      = $args['key'];
		$type     = $args['type'];
		$defaults = $this->defaults();
		$fallback = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		$value    = manacore_get_option( $key, $fallback );
		$name     = 'manacore_settings[' . esc_attr( $key ) . ']';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%1$s" id="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $name ),
					esc_attr( $key ),
					checked( 1, (int) $value, false ),
					esc_html__( 'فعال', 'manacore' )
				);
				break;

			case 'number':
				printf(
					'<input type="number" min="0" step="1" class="small-text" name="%1$s" id="%2$s" value="%3$s" />',
					esc_attr( $name ),
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
				break;

			case 'url':
				printf(
					'<input type="url" class="regular-text code" name="%1$s" id="%2$s" value="%3$s" placeholder="https://" />',
					esc_attr( $name ),
					esc_attr( $key ),
					esc_attr( (string) $value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea class="large-text code" rows="6" name="%1$s" id="%2$s" placeholder="128 = vip">%3$s</textarea>',
					esc_attr( $name ),
					esc_attr( $key ),
					esc_textarea( (string) $value )
				);
				break;

			case 'level':
				echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $key ) . '">';

				foreach ( manacore_subs_levels() as $slug => $level ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $slug ),
						selected( $value, $slug, false ),
						esc_html( $level['label'] )
					);
				}

				echo '</select>';
				break;

			case 'page':
				wp_dropdown_pages(
					array(
						'name'              => $name,
						'id'                => $key,
						'selected'          => absint( $value ),
						'show_option_none'  => __( '— انتخاب نشده —', 'manacore' ),
						'option_none_value' => '0',
					)
				);
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/* ---------------------------------------------------------------------
	 * Sanitising
	 * ------------------------------------------------------------------ */

	/**
	 * Clean the values this page owns.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	protected function clean( $input ) {
		$out = array();

		$checkboxes = array(
			'sub_enabled',
			'sub_strict_levels',
			'sub_any_product',
			'sub_allow_pending_cancel',
			'sub_grant_on_order',
			'sub_gate_content',
			'sub_account_notice',
		);

		// Checkboxes are only normalised when this page was the submitter,
		// otherwise saving the core page would silently switch them all off.
		$is_this_page = array_key_exists( 'sub_default_level', $input );

		foreach ( $checkboxes as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			} elseif ( $is_this_page ) {
				$out[ $key ] = 0;
			}
		}

		if ( array_key_exists( 'sub_default_level', $input ) ) {
			$level = sanitize_key( $input['sub_default_level'] );
			$out['sub_default_level'] = Plans::instance()->exists( $level )
				? $level
				: Plans::instance()->lowest();
		}

		if ( array_key_exists( 'sub_product_map', $input ) ) {
			$out['sub_product_map'] = sanitize_textarea_field( (string) $input['sub_product_map'] );
		}

		if ( array_key_exists( 'sub_page_id', $input ) ) {
			$out['sub_page_id'] = absint( $input['sub_page_id'] );
		}

		if ( array_key_exists( 'sub_page_url', $input ) ) {
			$out['sub_page_url'] = esc_url_raw( (string) $input['sub_page_url'] );
		}

		if ( array_key_exists( 'sub_order_days', $input ) ) {
			$days = absint( $input['sub_order_days'] );
			$out['sub_order_days'] = $days ? min( 3650, $days ) : 30;
		}

		if ( array_key_exists( 'sub_teaser_words', $input ) ) {
			$out['sub_teaser_words'] = min( 500, absint( $input['sub_teaser_words'] ) );
		}

		return $out;
	}

	/**
	 * Sanitize callback for this page – merges into the shared option so the
	 * core and sources settings are preserved.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_page( $input ) {
		$existing = get_option( 'manacore_settings', array() );
		$existing = is_array( $existing ) ? $existing : array();

		return array_merge( $existing, $this->clean( (array) $input ) );
	}

	/**
	 * Contribute to the core plugin's sanitising filter, so values submitted
	 * from the core settings screen survive too.
	 *
	 * @param array $clean Cleaned values so far.
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $clean, $input ) {
		return array_merge( (array) $clean, $this->clean( (array) $input ) );
	}

	/* ---------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	/**
	 * Render the settings page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap manacore-settings-wrap">
			<div class="manacore-settings-header">
				<span class="manacore-brand-mark">MC</span>
				<div>
					<h1><?php esc_html_e( 'اشتراک‌ها', 'manacore' ); ?></h1>
					<p><?php esc_html_e( 'سطح‌های دسترسی، اتصال به ووکامرس و نمایش محتوای ویژه.', 'manacore' ); ?></p>
				</div>
			</div>

			<?php $this->render_levels(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'manacore_subs_group' );
				do_settings_sections( 'manacore-subscriptions' );
				submit_button();
				?>
			</form>

			<?php $this->render_help(); ?>
		</div>
		<?php
	}

	/**
	 * Show the configured levels for reference.
	 */
	protected function render_levels() {
		?>
		<table class="widefat manacore-provider-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'سطح', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'نامک', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'وزن', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'محصولات نگاشت‌شده', 'manacore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$map = Plans::instance()->product_map();

				foreach ( manacore_subs_levels() as $slug => $level ) :
					$products = array_keys( $map, $slug, true );
					?>
					<tr>
						<td><strong><?php echo esc_html( $level['label'] ); ?></strong></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $level['weight'] ) ); ?></td>
						<td>
							<?php
							echo $products
								? esc_html( implode( '، ', array_map( 'strval', $products ) ) )
								: '<span class="manacore-pill is-off">' . esc_html__( 'ندارد', 'manacore' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Short usage help.
	 */
	protected function render_help() {
		?>
		<div class="manacore-subs-help">
			<h2><?php esc_html_e( 'راهنمای سریع', 'manacore' ); ?></h2>
			<ul>
				<li><code>[manacore_subscription]</code> — <?php esc_html_e( 'نمایش کارت وضعیت اشتراک کاربر.', 'manacore' ); ?></li>
				<li><code>[manacore_plans]</code> — <?php esc_html_e( 'نمایش فهرست پلن‌ها.', 'manacore' ); ?></li>
				<li><code>[manacore_members_only level="vip"]…[/manacore_members_only]</code> — <?php esc_html_e( 'نمایش بخشی از محتوا فقط به مشترکان.', 'manacore' ); ?></li>
				<li><?php esc_html_e( 'برای اعطای دستی اشتراک، به صفحه‌ی ویرایش کاربر بروید.', 'manacore' ); ?></li>
			</ul>
		</div>
		<?php
	}
}

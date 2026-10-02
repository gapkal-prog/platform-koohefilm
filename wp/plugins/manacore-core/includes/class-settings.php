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

		add_submenu_page(
			'manacore',
			__( 'تنظیمات عمومی', 'manacore' ),
			__( 'تنظیمات عمومی', 'manacore' ),
			'manage_options',
			'manacore',
			array( $this, 'render' )
		);
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
	}

	/**
	 * پاک‌سازی تنظیمات.
	 *
	 * @param mixed $input ورودی.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$text_keys = array(
			'slug_movie',
			'slug_series',
			'slug_anime',
			'slug_episode',
			'slug_person',
			'slug_collection',
		);
		foreach ( $text_keys as $key ) {
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

		$existing = get_option( 'manacore_settings', array() );

		return apply_filters( 'manacore_sanitize_settings', array_merge( $existing, $clean ), $input );
	}

	/**
	 * رندر صفحه.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<div class="manacore-settings-header">
				<span class="manacore-brand-mark">MC</span>
				<div>
					<h1><?php esc_html_e( 'ManaCore — هسته‌ی سایت فیلم و سریال', 'manacore' ); ?></h1>
					<p><?php esc_html_e( 'طراحی و توسعه توسط گروه ManaCore', 'manacore' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'manacore_settings_group' ); ?>

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

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

<?php
/**
 * Settings screen for the Sources plugin.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 *
 * All values live inside the core plugin's `manacore_settings` option under the
 * `src_` prefix, so there is a single source of truth for the whole ecosystem.
 */
class Settings {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'manacore_sanitize_settings', array( $this, 'sanitize' ), 10, 2 );
	}

	/**
	 * Load the shared sources admin stylesheet on this settings page.
	 *
	 * The fetch metabox enqueues the same handle on edit screens; WordPress
	 * de-duplicates it so registering here is safe.
	 */
	public function assets() {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( (string) $screen->id, 'manacore-sources' ) ) {
			return;
		}

		wp_enqueue_style(
			'manacore-sources-admin',
			MANACORE_SOURCES_URL . 'assets/admin.css',
			array(),
			MANACORE_SOURCES_VERSION
		);
	}

	/**
	 * Add the submenu page under the ManaCore menu.
	 */
	public function menu() {
		add_submenu_page(
			'manacore',
			__( 'منابع اطلاعات', 'manacore' ),
			__( 'منابع اطلاعات', 'manacore' ),
			'manage_options',
			'manacore-sources',
			array( $this, 'render' )
		);
	}

	/**
	 * Register the settings fields.
	 */
	public function register() {
		register_setting(
			'manacore_sources_group',
			'manacore_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_page' ),
			)
		);

		add_settings_section(
			'manacore_sources_mode',
			__( 'حالت دریافت اطلاعات', 'manacore' ),
			array( $this, 'section_mode' ),
			'manacore-sources'
		);

		add_settings_field(
			'src_mode',
			__( 'حالت فعال', 'manacore' ),
			array( $this, 'field_mode' ),
			'manacore-sources',
			'manacore_sources_mode'
		);

		add_settings_field(
			'src_tmdb_key',
			__( 'کلید TMDB', 'manacore' ),
			array( $this, 'field_tmdb_key' ),
			'manacore-sources',
			'manacore_sources_mode'
		);

		add_settings_field(
			'src_tmdb_language',
			__( 'زبان TMDB', 'manacore' ),
			array( $this, 'field_tmdb_language' ),
			'manacore-sources',
			'manacore_sources_mode'
		);

		add_settings_section(
			'manacore_sources_free',
			__( 'منابع آزاد (بدون کلید)', 'manacore' ),
			array( $this, 'section_free' ),
			'manacore-sources'
		);

		add_settings_field(
			'src_wikidata_language',
			__( 'زبان Wikidata', 'manacore' ),
			array( $this, 'field_wikidata_language' ),
			'manacore-sources',
			'manacore_sources_free'
		);

		add_settings_field(
			'src_omdb_key',
			__( 'کلید OMDb (اختیاری)', 'manacore' ),
			array( $this, 'field_omdb_key' ),
			'manacore-sources',
			'manacore_sources_free'
		);

		add_settings_section(
			'manacore_sources_import',
			__( 'رفتار درج اطلاعات', 'manacore' ),
			'__return_false',
			'manacore-sources'
		);

		add_settings_field(
			'src_overwrite',
			__( 'بازنویسی پیش‌فرض', 'manacore' ),
			array( $this, 'field_overwrite' ),
			'manacore-sources',
			'manacore_sources_import'
		);

		add_settings_field(
			'src_sideload',
			__( 'دانلود تصویر شاخص', 'manacore' ),
			array( $this, 'field_sideload' ),
			'manacore-sources',
			'manacore_sources_import'
		);

		add_settings_field(
			'src_enrich',
			__( 'تکمیل خودکار از منابع دیگر', 'manacore' ),
			array( $this, 'field_enrich' ),
			'manacore-sources',
			'manacore_sources_import'
		);

		add_settings_field(
			'src_cast_limit',
			__( 'حداکثر تعداد بازیگر', 'manacore' ),
			array( $this, 'field_cast_limit' ),
			'manacore-sources',
			'manacore_sources_import'
		);
	}

	/* ---------------------------------------------------------------------
	 * Section descriptions
	 * ------------------------------------------------------------------ */

	/**
	 * Mode section description.
	 */
	public function section_mode() {
		echo '<p class="description">';
		esc_html_e( 'اگر کلید TMDB ثبت شود، سیستم به‌صورت پیش‌فرض از آن استفاده می‌کند و در صورت خطا به‌طور خودکار به منابع آزاد بازمی‌گردد. بدون کلید هم همه‌چیز کار می‌کند.', 'manacore' );
		echo '</p>';
	}

	/**
	 * Free section description.
	 */
	public function section_free() {
		echo '<p class="description">';
		esc_html_e( 'این منابع بدون هیچ کلیدی کار می‌کنند: Wikidata برای فیلم، TVMaze برای سریال و Jikan/MyAnimeList برای انیمه.', 'manacore' );
		echo '</p>';
	}

	/* ---------------------------------------------------------------------
	 * Fields
	 * ------------------------------------------------------------------ */

	/**
	 * Mode selector.
	 */
	public function field_mode() {
		$value = manacore_sources_get_option( 'mode', 'auto' );
		$modes = array(
			'auto' => __( 'خودکار — اگر کلید هست، کلید اولویت دارد (پیشنهادی)', 'manacore' ),
			'tmdb' => __( 'فقط TMDB (نیازمند کلید)', 'manacore' ),
			'free' => __( 'فقط منابع آزاد (بدون کلید)', 'manacore' ),
		);
		?>
		<select name="manacore_settings[src_mode]">
			<?php foreach ( $modes as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php
			printf(
				/* translators: %s: active mode label. */
				esc_html__( 'حالت فعال در این لحظه: %s', 'manacore' ),
				'<strong>' . ( 'tmdb' === manacore_sources_active_mode()
					? esc_html__( 'TMDB (با کلید)', 'manacore' )
					: esc_html__( 'منابع آزاد', 'manacore' ) ) . '</strong>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * TMDB key field.
	 */
	public function field_tmdb_key() {
		$value = manacore_sources_get_option( 'tmdb_key', '' );
		?>
		<input
			type="password"
			class="regular-text"
			name="manacore_settings[src_tmdb_key]"
			value="<?php echo esc_attr( $value ); ?>"
			autocomplete="off"
			placeholder="<?php esc_attr_e( 'کلید v3 یا توکن v4', 'manacore' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'از حساب TMDB خود کلید API را بگیرید. هر دو نوع کلید v3 و توکن Bearer نسخه v4 پشتیبانی می‌شود.', 'manacore' ); ?>
			<?php if ( defined( 'MANACORE_TMDB_API_KEY' ) ) : ?>
				<br /><strong><?php esc_html_e( 'توجه: کلید از طریق wp-config تعریف شده و اولویت دارد.', 'manacore' ); ?></strong>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * TMDB language field.
	 */
	public function field_tmdb_language() {
		$value = manacore_sources_get_option( 'tmdb_language', 'fa-IR' );
		?>
		<select name="manacore_settings[src_tmdb_language]">
			<?php foreach ( manacore_sources_languages() as $code => $label ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $value, $code ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'اگر ترجمه فارسی موجود نباشد، TMDB متن اصلی را برمی‌گرداند.', 'manacore' ); ?></p>
		<?php
	}

	/**
	 * Wikidata language field.
	 */
	public function field_wikidata_language() {
		$value = manacore_sources_get_option( 'wikidata_language', 'fa' );
		$langs = array(
			'fa' => __( 'فارسی', 'manacore' ),
			'en' => __( 'انگلیسی', 'manacore' ),
			'ar' => __( 'عربی', 'manacore' ),
			'tr' => __( 'ترکی', 'manacore' ),
		);
		?>
		<select name="manacore_settings[src_wikidata_language]">
			<?php foreach ( $langs as $code => $label ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $value, $code ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * OMDb key field.
	 */
	public function field_omdb_key() {
		$value = manacore_sources_get_option( 'omdb_key', '' );
		?>
		<input
			type="password"
			class="regular-text"
			name="manacore_settings[src_omdb_key]"
			value="<?php echo esc_attr( $value ); ?>"
			autocomplete="off"
		/>
		<p class="description">
			<?php esc_html_e( 'اختیاری. برای تکمیل امتیاز IMDb، تعداد رأی، Metascore و Rotten Tomatoes استفاده می‌شود.', 'manacore' ); ?>
		</p>
		<?php
	}

	/**
	 * Overwrite toggle.
	 */
	public function field_overwrite() {
		$this->checkbox( 'src_overwrite', manacore_sources_get_option( 'overwrite', 0 ), __( 'به‌طور پیش‌فرض مقادیر پرشده بازنویسی شوند', 'manacore' ) );
	}

	/**
	 * Sideload toggle.
	 */
	public function field_sideload() {
		$this->checkbox( 'src_sideload', manacore_sources_get_option( 'sideload', 1 ), __( 'پوستر به‌عنوان تصویر شاخص در کتابخانه رسانه ذخیره شود', 'manacore' ) );
	}

	/**
	 * Enrich toggle.
	 */
	public function field_enrich() {
		$this->checkbox( 'src_enrich', manacore_sources_get_option( 'enrich', 1 ), __( 'فیلدهای خالی از منابع مکمل پر شوند', 'manacore' ) );
	}

	/**
	 * Cast limit field.
	 */
	public function field_cast_limit() {
		$value = (int) manacore_sources_get_option( 'cast_limit', 15 );
		?>
		<input
			type="number"
			class="small-text"
			name="manacore_settings[src_cast_limit]"
			value="<?php echo esc_attr( $value ? $value : 15 ); ?>"
			min="1"
			max="60"
		/>
		<?php
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Current value.
	 * @param string $label Label text.
	 */
	protected function checkbox( $key, $value, $label ) {
		?>
		<label>
			<input
				type="checkbox"
				name="manacore_settings[<?php echo esc_attr( $key ); ?>]"
				value="1"
				<?php checked( ! empty( $value ) ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Sanitizing
	 * ------------------------------------------------------------------ */

	/**
	 * Sanitize the settings submitted from this page.
	 *
	 * Merges into the existing option so the core settings page values survive.
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
	 * Add our keys when the core settings page saves.
	 *
	 * @param array $clean Sanitized values.
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $clean, $input ) {
		return array_merge( (array) $clean, $this->clean( (array) $input ) );
	}

	/**
	 * Sanitize only the `src_` keys.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	protected function clean( $input ) {
		$out = array();

		if ( isset( $input['src_mode'] ) ) {
			$mode               = sanitize_key( $input['src_mode'] );
			$out['src_mode'] = in_array( $mode, array( 'auto', 'tmdb', 'free' ), true ) ? $mode : 'auto';
		}

		if ( isset( $input['src_tmdb_key'] ) ) {
			$out['src_tmdb_key'] = trim( sanitize_text_field( $input['src_tmdb_key'] ) );
		}

		if ( isset( $input['src_omdb_key'] ) ) {
			$out['src_omdb_key'] = trim( sanitize_text_field( $input['src_omdb_key'] ) );
		}

		if ( isset( $input['src_tmdb_language'] ) ) {
			$lang = sanitize_text_field( $input['src_tmdb_language'] );
			$out['src_tmdb_language'] = array_key_exists( $lang, manacore_sources_languages() ) ? $lang : 'fa-IR';
		}

		if ( isset( $input['src_wikidata_language'] ) ) {
			$out['src_wikidata_language'] = sanitize_key( $input['src_wikidata_language'] );
		}

		if ( isset( $input['src_cast_limit'] ) ) {
			$limit = absint( $input['src_cast_limit'] );
			$out['src_cast_limit'] = min( 60, max( 1, $limit ? $limit : 15 ) );
		}

		// Checkboxes only appear in $input when checked, so they must be
		// normalized explicitly whenever this page was the submitter.
		if ( isset( $input['src_mode'] ) ) {
			foreach ( array( 'src_overwrite', 'src_sideload', 'src_enrich' ) as $key ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}

		return $out;
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
				<span class="manacore-brand-mark">ManaCore</span>
				<h1><?php esc_html_e( 'منابع اطلاعات', 'manacore' ); ?></h1>
			</div>

			<?php $this->render_status(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'manacore_sources_group' );
				do_settings_sections( 'manacore-sources' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the provider availability table.
	 */
	protected function render_status() {
		?>
		<table class="widefat manacore-provider-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'منبع', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'نیاز به کلید', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'manacore' ); ?></th>
					<th><?php esc_html_e( 'انواع پشتیبانی‌شده', 'manacore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( Registry::instance()->all() as $provider ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $provider->get_label() ); ?></strong></td>
						<td><?php echo $provider->requires_key() ? esc_html__( 'بله', 'manacore' ) : esc_html__( 'خیر', 'manacore' ); ?></td>
						<td>
							<?php if ( $provider->is_available() ) : ?>
								<span class="manacore-pill is-on"><?php esc_html_e( 'فعال', 'manacore' ); ?></span>
							<?php else : ?>
								<span class="manacore-pill is-off"><?php esc_html_e( 'غیرفعال', 'manacore' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$kinds = array();

							foreach ( manacore_sources_kinds() as $kind => $label ) {
								if ( $provider->supports( $kind ) ) {
									$kinds[] = $label;
								}
							}

							echo esc_html( implode( '، ', $kinds ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

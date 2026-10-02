<?php
/**
 * Fetch metabox UI (hooks into the core plugin's fetch box).
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Metabox.
 */
class Metabox {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'manacore_fetch_metabox', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Enqueue admin assets on supported edit screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! array_key_exists( $screen->post_type, manacore_post_types() ) ) {
			return;
		}

		wp_enqueue_style(
			'manacore-sources-admin',
			MANACORE_SOURCES_URL . 'assets/admin.css',
			array(),
			MANACORE_SOURCES_VERSION
		);

		wp_enqueue_script(
			'manacore-sources-admin',
			MANACORE_SOURCES_URL . 'assets/admin.js',
			array( 'wp-i18n' ),
			MANACORE_SOURCES_VERSION,
			true
		);

		$providers = array();

		foreach ( Registry::instance()->all() as $id => $provider ) {
			$providers[] = array(
				'id'        => $id,
				'label'     => $provider->get_label(),
				'available' => $provider->is_available(),
			);
		}

		wp_localize_script(
			'manacore-sources-admin',
			'manaCoreSources',
			array(
				'restUrl'   => esc_url_raw( rest_url( Rest::NS . '/sources' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'postId'    => get_the_ID(),
				'kind'      => manacore_sources_kind_for_post_type( $screen->post_type ),
				'mode'      => manacore_sources_active_mode(),
				'hasKey'    => manacore_sources_has_key(),
				'providers' => $providers,
				'settingsUrl' => esc_url_raw( admin_url( 'admin.php?page=manacore-sources' ) ),
				'i18n'      => array(
					'searching'    => __( 'در حال جستجو…', 'manacore' ),
					'noResults'    => __( 'نتیجه‌ای یافت نشد.', 'manacore' ),
					'searchLabel'  => __( 'نام فیلم/سریال/انیمه', 'manacore' ),
					'searchButton' => __( 'جستجو', 'manacore' ),
					'yearLabel'    => __( 'سال', 'manacore' ),
					'sourceLabel'  => __( 'منبع', 'manacore' ),
					'autoSource'   => __( 'خودکار (پیشنهادی)', 'manacore' ),
					'import'       => __( 'درج اطلاعات', 'manacore' ),
					'importing'    => __( 'در حال درج…', 'manacore' ),
					'imported'     => __( 'اطلاعات با موفقیت درج شد.', 'manacore' ),
					'fieldsUpdated' => __( 'فیلدها به‌روزرسانی شدند؛ برای ثبت نهایی نوشته را ذخیره کنید.', 'manacore' ),
					'reloadHint'   => __( 'برای دیدن تغییرات، صفحه را بازخوانی کنید.', 'manacore' ),
					'reload'       => __( 'بازخوانی صفحه', 'manacore' ),
					'overwrite'    => __( 'بازنویسی مقادیر پرشده', 'manacore' ),
					'sections'     => __( 'بخش‌های وارد شده', 'manacore' ),
					'secTitle'     => __( 'عنوان', 'manacore' ),
					'secContent'   => __( 'خلاصه و متن', 'manacore' ),
					'secMeta'      => __( 'مشخصات و امتیازها', 'manacore' ),
					'secTax'       => __( 'ژانر/کشور/شبکه', 'manacore' ),
					'secCast'      => __( 'بازیگران', 'manacore' ),
					'secSeasons'   => __( 'فصل‌ها', 'manacore' ),
					'secGallery'   => __( 'گالری', 'manacore' ),
					'secThumb'     => __( 'تصویر شاخص', 'manacore' ),
					'modeTmdb'     => __( 'حالت فعال: TMDB (با کلید)', 'manacore' ),
					'modeFree'     => __( 'حالت فعال: منابع آزاد (بدون کلید)', 'manacore' ),
					'settings'     => __( 'تنظیمات منابع', 'manacore' ),
					'error'        => __( 'خطا در ارتباط با سرور.', 'manacore' ),
					'saveFirst'    => __( 'ابتدا نوشته را ذخیره کنید (پیش‌نویس) تا امکان درج فراهم شود.', 'manacore' ),
					'cast'         => __( 'بازیگر', 'manacore' ),
					'seasons'      => __( 'فصل', 'manacore' ),
				),
			)
		);
	}

	/**
	 * Render the fetch box body.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( $post ) {
		$mode = manacore_sources_active_mode();
		?>
		<div class="manacore-fetch-box" data-manacore-sources>
			<p class="manacore-fetch-mode">
				<span class="manacore-mode-badge <?php echo 'tmdb' === $mode ? 'is-key' : 'is-free'; ?>">
					<?php
					echo 'tmdb' === $mode
						? esc_html__( 'TMDB (با کلید)', 'manacore' )
						: esc_html__( 'منابع آزاد (بدون کلید)', 'manacore' );
					?>
				</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=manacore-sources' ) ); ?>">
					<?php esc_html_e( 'تنظیمات', 'manacore' ); ?>
				</a>
			</p>

			<div class="manacore-fetch-form">
				<input
					type="search"
					class="widefat manacore-fetch-query"
					placeholder="<?php esc_attr_e( 'نام اثر را بنویسید…', 'manacore' ); ?>"
					value="<?php echo esc_attr( $post->post_title ); ?>"
				/>

				<div class="manacore-fetch-row">
					<input
						type="number"
						class="manacore-fetch-year"
						placeholder="<?php esc_attr_e( 'سال', 'manacore' ); ?>"
						min="1870"
						max="<?php echo esc_attr( (int) gmdate( 'Y' ) + 5 ); ?>"
					/>
					<select class="manacore-fetch-provider">
						<option value=""><?php esc_html_e( 'خودکار (پیشنهادی)', 'manacore' ); ?></option>
						<?php foreach ( Registry::instance()->all() as $id => $provider ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php disabled( ! $provider->is_available() ); ?>>
								<?php echo esc_html( $provider->get_label() ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="button" class="button button-primary manacore-fetch-submit">
					<?php esc_html_e( 'جستجو', 'manacore' ); ?>
				</button>
			</div>

			<div class="manacore-fetch-status" aria-live="polite"></div>
			<div class="manacore-fetch-results"></div>

			<details class="manacore-fetch-options">
				<summary><?php esc_html_e( 'گزینه‌های درج', 'manacore' ); ?></summary>

				<label class="manacore-fetch-check">
					<input type="checkbox" class="manacore-fetch-overwrite" />
					<?php esc_html_e( 'بازنویسی مقادیری که از قبل پر شده‌اند', 'manacore' ); ?>
				</label>

				<div class="manacore-fetch-sections">
					<?php
					$sections = array(
						'title'      => __( 'عنوان', 'manacore' ),
						'content'    => __( 'خلاصه و متن', 'manacore' ),
						'meta'       => __( 'مشخصات و امتیازها', 'manacore' ),
						'taxonomies' => __( 'ژانر/کشور/شبکه', 'manacore' ),
						'cast'       => __( 'بازیگران', 'manacore' ),
						'seasons'    => __( 'فصل‌ها', 'manacore' ),
						'gallery'    => __( 'گالری', 'manacore' ),
						'thumbnail'  => __( 'تصویر شاخص', 'manacore' ),
					);

					foreach ( $sections as $key => $label ) :
						?>
						<label class="manacore-fetch-check">
							<input type="checkbox" data-section="<?php echo esc_attr( $key ); ?>" checked />
							<?php echo esc_html( $label ); ?>
						</label>
						<?php
					endforeach;
					?>
				</div>
			</details>

			<?php $this->render_provenance( $post->ID ); ?>
		</div>
		<?php
	}

	/**
	 * Show which source last filled this post.
	 *
	 * @param int $post_id Post id.
	 */
	protected function render_provenance( $post_id ) {
		$provider = get_post_meta( $post_id, '_manacore_source_provider', true );
		$synced   = get_post_meta( $post_id, '_manacore_source_synced', true );

		if ( ! $provider ) {
			return;
		}

		$instance = Registry::instance()->get( $provider );
		$label    = $instance ? $instance->get_label() : $provider;
		$url      = get_post_meta( $post_id, '_manacore_source_url', true );
		?>
		<p class="manacore-fetch-provenance">
			<?php
			printf(
				/* translators: 1: provider label, 2: date. */
				esc_html__( 'آخرین دریافت از %1$s در %2$s', 'manacore' ),
				'<strong>' . esc_html( $label ) . '</strong>',
				esc_html( mysql2date( 'Y/m/d H:i', $synced ) )
			);

			if ( $url ) {
				echo ' — <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'مشاهده منبع', 'manacore' ) . '</a>';
			}
			?>
		</p>
		<?php
	}
}

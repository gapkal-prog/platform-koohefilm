<?php
/**
 * مدیریت لینک‌ها از «فهرست» پیشخوان.
 *
 * چرا لازم است؟ باکس دانلود هر اثر از متای `manacore_links` ساخته می‌شود
 * و در سریال‌ها لینک هر قسمت روی پست همان قسمت می‌نشیند؛ از فهرست
 * پیشخوان هیچ‌جای این وضعیت دیده نمی‌شد و برای فهمیدن «کدام قسمت لینک
 * ندارد» باید همه‌ی قسمت‌ها یکی‌یکی باز می‌شدند.
 *
 * این کلاس سه چیز اضافه می‌کند:
 *   • ستون «لینک‌ها» در فهرست فیلم/سریال/انیمه/قسمت (با جمع لینک
 *     قسمت‌ها برای سریال‌ها)،
 *   • پالایه‌ی «دارای لینک / بدون لینک» برای رسیدن سریع به کارهای
 *     ناتمام،
 *   • پیوند سریع «لینک‌ها» روی هر ردیف که مستقیم متاباکس لینک‌ها را
 *     باز می‌کند.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Links_Admin
 */
class Links_Admin {

	use Singleton;

	/**
	 * شناسه‌ی ستون.
	 */
	const COLUMN = 'manacore_links';

	/**
	 * پارامتر آدرس پالایه.
	 */
	const QUERY_VAR = 'manacore_link_state';

	/**
	 * اتصال قلاب‌ها.
	 *
	 * ثبت خودِ ستون‌ها به `init` سپرده می‌شود و در زمان بارگذاری افزونه
	 * انجام نمی‌گیرد: فهرست نوع‌های محتوا از `manacore_post_types()`
	 * می‌آید که برچسب‌هایش ترجمه‌پذیر است و درخواست ترجمه پیش از
	 * `after_setup_theme` هشدار `_load_textdomain_just_in_time` می‌دهد.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * ثبت ستون، پالایه و پیوند سریع برای هر نوع محتوا.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( self::post_types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . $type . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		}

		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filter' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * انواع محتوایی که ستون لینک می‌گیرند.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return array_map( 'sanitize_key', array_keys( manacore_post_types() ) );
	}

	/**
	 * افزودن ستون «لینک‌ها» پیش از ستون تاریخ.
	 *
	 * @param array $columns ستون‌های فهرست.
	 * @return array
	 */
	public function columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );

		$columns[ self::COLUMN ] = __( 'لینک‌ها', 'manacore' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * محتوای ستون «لینک‌ها».
	 *
	 * برای سریال/انیمه، جمع لینک‌های قسمت‌ها هم شمرده می‌شود تا مدیر
	 * ببیند لینک روی قسمت‌ها هست یا نه (حالت متعارف سایت‌های سریالی).
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه‌ی پست.
	 */
	public function column_content( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$links = Links::count( $post_id );
		$parts = array();

		if ( $links ) {
			$parts[] = sprintf(
				/* translators: %s: تعداد لینک */
				__( '%s لینک', 'manacore' ),
				manacore_fa_digits( number_format_i18n( $links ) )
			);
		}

		if ( in_array( get_post_type( $post_id ), manacore_serial_post_types(), true ) ) {
			$episode_links = Links::episode_count( $post_id );

			if ( $episode_links ) {
				$parts[] = sprintf(
					/* translators: %s: تعداد لینک ثبت‌شده روی قسمت‌ها */
					__( '%s در قسمت‌ها', 'manacore' ),
					manacore_fa_digits( number_format_i18n( $episode_links ) )
				);
			}
		}

		$url   = self::links_url( $post_id );
		$state = $parts ? 'is-ok' : 'is-empty';

		printf(
			'<span class="manacore-links-state %1$s">%2$s</span> <a class="manacore-links-edit" href="%3$s">%4$s</a>',
			esc_attr( $state ),
			esc_html( $parts ? implode( ' · ', $parts ) : __( 'بدون لینک', 'manacore' ) ),
			esc_url( $url ),
			esc_html( $parts ? __( 'ویرایش', 'manacore' ) : __( 'افزودن لینک', 'manacore' ) )
		);
	}

	/**
	 * پالایه‌ی وضعیت لینک در بالای فهرست.
	 *
	 * @param string $post_type نوع محتوای فهرست جاری.
	 */
	public function filter_dropdown( $post_type ) {
		if ( ! in_array( $post_type, self::post_types(), true ) ) {
			return;
		}

		$current = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( self::QUERY_VAR ); ?>">
			<?php esc_html_e( 'پالایش بر پایه‌ی وضعیت لینک', 'manacore' ); ?>
		</label>
		<select name="<?php echo esc_attr( self::QUERY_VAR ); ?>" id="<?php echo esc_attr( self::QUERY_VAR ); ?>">
			<option value=""><?php esc_html_e( 'همه‌ی وضعیت‌های لینک', 'manacore' ); ?></option>
			<option value="yes" <?php selected( $current, 'yes' ); ?>><?php esc_html_e( 'دارای لینک', 'manacore' ); ?></option>
			<option value="no" <?php selected( $current, 'no' ); ?>><?php esc_html_e( 'بدون لینک', 'manacore' ); ?></option>
		</select>
		<?php
	}

	/**
	 * اعمال پالایه روی کوئری فهرست.
	 *
	 * @param \WP_Query $query کوئری جاری.
	 */
	public function apply_filter( $query ) {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$state = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $state, array( 'yes', 'no' ), true ) ) {
			return;
		}

		$type = (string) $query->get( 'post_type' );

		if ( ! in_array( $type, self::post_types(), true ) ) {
			return;
		}

		/*
		 * ادغام با شرط‌های موجود از همان کمکی استفاده می‌کند که آرشیوها
		 * به‌کار می‌برند (`manacore_merge_meta_query`) تا پالایه‌های دیگر
		 * (افزونه‌ها/قالب) پاک نشوند. عضوهای نامعتبر هم پیش از ادغام
		 * کنار گذاشته می‌شوند؛ `WP_Query::get()` در نبود مقدار، رشته‌ی
		 * خالی می‌دهد و آن نباید به شرط فراداده تبدیل شود.
		 */
		$existing = array_values( array_filter( (array) $query->get( 'meta_query' ), 'is_array' ) );

		$query->set(
			'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			manacore_merge_meta_query(
				$existing,
				array(
					array(
						'key'     => Links::META_KEY,
						'compare' => 'yes' === $state ? 'EXISTS' : 'NOT EXISTS',
					),
				)
			)
		);
	}

	/**
	 * پیوند سریع «لینک‌ها» روی هر ردیف فهرست.
	 *
	 * @param array    $actions پیوندهای ردیف.
	 * @param \WP_Post $post    پست ردیف.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['manacore_links'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::links_url( $post->ID ) ),
			esc_html__( 'لینک‌ها', 'manacore' )
		);

		return $actions;
	}

	/**
	 * نشانی ویرایشگر با پرش به متاباکس لینک‌ها.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return string
	 */
	public static function links_url( $post_id ) {
		$url = (string) get_edit_post_link( $post_id, 'raw' );

		/* شناسه‌ی متاباکس `manacore-links` است؛ لنگر همان را باز می‌کند. */
		return '' === $url ? '' : $url . '#manacore-links';
	}
}

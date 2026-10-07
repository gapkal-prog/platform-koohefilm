<?php
/**
 * گزارش خرابی لینک.
 *
 * کاربری که لینک شکسته می‌بیند، از همان ردیف جدول دانلود گزارش می‌دهد؛
 * گزارش در جدول اختصاصی ذخیره و در پیشخوان (تب «وضعیت و ابزارها» ←
 * گزارش‌ها) فهرست می‌شود. مدیر می‌تواند هر گزارش را «اصلاح‌شده»،
 * «نادیده‌گرفته‌شده» یا حذف‌شده علامت بزند.
 *
 * چرا جدول جداگانه و نه متا یا دیدگاه؟
 *   • دیدگاه باید تأیید شود و نویز محتوایی ایجاد می‌کند.
 *   • متا روی پست، به‌ازای هر گزارش یک ردیف تازه می‌سازد و فهرست‌کردن
 *     «همه‌ی گزارش‌های اصلاح‌نشده» را گران می‌کند.
 * جدول با کلید یکتا `(post_id, reporter_hash, quality)` جلوی انبوه
 * گزارش تکراری از یک بازدیدکننده برای یک لینک را می‌گیرد.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Reports
 */
class Reports {

	use Singleton;

	/**
	 * وضعیت‌های مجاز یک گزارش.
	 */
	const STATUSES = array( 'new', 'fixed', 'ignored' );

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'admin_post_manacore_report_action', array( $this, 'handle_admin_action' ) );

		/*
		 * ارتقای پایگاه‌داده (جدول گزارش‌ها در ۱٫۱٫۰ افزوده شد). روی
		 * `admin_init` اجرا می‌شود تا در بازدید عمومی هیچ کوئری ادمینی
		 * اجرا نشود.
		 */
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * ساخت جدول گزارش‌ها در صورت نیاز.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( ! class_exists( __NAMESPACE__ . '\\Install' ) ) {
			return;
		}

		Install::maybe_upgrade();
	}

	/**
	 * ثبت مسیر REST گزارش.
	 */
	public function register_route() {
		register_rest_route(
			Rest_Api::NS,
			'/report-link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_report' ),
				'permission_callback' => array( Rest_Api::instance(), 'verify_public_write' ),
				'args'                => array(
					'post_id'    => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'link_url'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'link_label' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'quality'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * پاسخ REST.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_report( $request ) {
		$post_id  = (int) $request->get_param( 'post_id' );
		$link_url = (string) $request->get_param( 'link_url' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error(
				'manacore_report_unknown_post',
				__( 'اثر موردنظر پیدا نشد.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		if ( self::is_duplicate( $post_id, (string) $request->get_param( 'quality' ) ) ) {
			return new \WP_REST_Response(
				array(
					'ok'        => true,
					'duplicate' => true,
					'message'   => __( 'گزارش شما از پیش ثبت شده است؛ ممنون که اطلاع دادید.', 'manacore' ),
				),
				200
			);
		}

		$id = self::insert(
			array(
				'post_id'    => $post_id,
				'link_url'   => $link_url,
				'link_label' => (string) $request->get_param( 'link_label' ),
				'quality'    => (string) $request->get_param( 'quality' ),
				'reason'     => (string) $request->get_param( 'reason' ),
			)
		);

		if ( ! $id ) {
			return new \WP_Error(
				'manacore_report_failed',
				__( 'ثبت گزارش ممکن نشد. کمی بعد دوباره تلاش کنید.', 'manacore' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * پس از ثبت گزارش خرابی لینک.
		 *
		 * @param int $id   شناسه‌ی گزارش.
		 * @param int $post_id شناسه‌ی اثر.
		 */
		do_action( 'manacore_link_reported', (int) $id, $post_id );

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'id'      => (int) $id,
				'message' => __( 'گزارش خرابی ثبت شد. ممنون که به بهترشدن سایت کمک کردید!', 'manacore' ),
			),
			201
		);
	}

	/**
	 * درهم‌سازی گزارش‌دهنده: کاربر وارد‌شده شناسه، مهمان نشانی شبکه.
	 *
	 * @return string
	 */
	public static function reporter_hash() {
		/*
		 * همان سازوکار مشترک `manacore_visitor_hash()`: عضو با شناسه‌ی
		 * کاربری، مهمان با درهم‌سازی نمک‌دار نشانی شبکه («reporter» دامنه‌ی
		 * جدا می‌سازد تا رأی‌های درخواست‌ها با گزارش‌ها قاطی نشوند).
		 */
		return manacore_visitor_hash( 'reporter' );
	}

	/**
	 * آیا همین کاربر برای همین لینک از پیش گزارش داده است؟
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $quality کیفیت.
	 * @return bool
	 */
	public static function is_duplicate( $post_id, $quality ) {
		global $wpdb;

		$table = Install::reports_table();

		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND reporter_hash = %s AND quality = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $post_id,
				self::reporter_hash(),
				(string) $quality
			)
		);

		return ! empty( $found );
	}

	/**
	 * درج گزارش.
	 *
	 * @param array $args داده‌های گزارش.
	 * @return int شناسه‌ی گزارش یا صفر.
	 */
	public static function insert( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'post_id'    => 0,
				'link_url'   => '',
				'link_label' => '',
				'quality'    => '',
				'reason'     => '',
			)
		);

		/*
		 * بشکن `<` و `>` در نشانی: `esc_url_raw()` کاراکترهای غیرمجاز را
		 * حذف می‌کند ولی تگ‌مانند را دست‌نخورده می‌گذارد؛ اینجا هرچه مانده
		 * پاک می‌شود تا در جدول پیشخوان و در `<a href>` امن بماند.
		 */
		$url = str_replace( array( '<', '>' ), '', (string) $args['link_url'] );

		$data = array(
			'post_id'       => absint( $args['post_id'] ),
			'link_url'      => esc_url_raw( $url ),
			'link_label'    => mb_substr( sanitize_text_field( (string) $args['link_label'] ), 0, 190 ),
			'quality'       => mb_substr( sanitize_text_field( (string) $args['quality'] ), 0, 60 ),
			'reason'        => mb_substr( sanitize_text_field( (string) $args['reason'] ), 0, 190 ),
			'reporter_id'   => (int) get_current_user_id(),
			'reporter_hash' => self::reporter_hash(),
			'status'        => 'new',
			'created_at'    => current_time( 'mysql' ),
		);

		if ( ! $data['post_id'] || '' === $data['link_url'] ) {
			return 0;
		}

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Install::reports_table(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * فهرست گزارش‌ها برای پیشخوان.
	 *
	 * @param array $args فیلترها: status, per_page, page.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'per_page' => 50,
				'page'     => 1,
			)
		);

		$table  = Install::reports_table();
		$where  = '';
		$params = array();

		if ( in_array( (string) $args['status'], self::STATUSES, true ) ) {
			$where    = 'WHERE status = %s';
			$params[] = (string) $args['status'];
		}

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( ( max( 1, (int) $args['page'] ) - 1 ) * $per_page ) );
		$params[] = $per_page;
		$params[] = $offset;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * شمارش گزارش‌ها به تفکیک وضعیت.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		global $wpdb;

		$table = Install::reports_table();
		$out   = array_fill_keys( self::STATUSES, 0 );

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $rows as $row ) {
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';

			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] = (int) $row['total'];
			}
		}

		return $out;
	}

	/**
	 * تغییر وضعیت یک گزارش.
	 *
	 * @param int    $id     شناسه‌ی گزارش.
	 * @param string $status وضعیت تازه.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		if ( ! in_array( (string) $status, self::STATUSES, true ) ) {
			return false;
		}

		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Install::reports_table(),
			array( 'status' => (string) $status ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $ok;
	}

	/**
	 * حذف یک گزارش.
	 *
	 * @param int $id شناسه.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$ok = $wpdb->delete( Install::reports_table(), array( 'id' => absint( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return false !== $ok;
	}

	/**
	 * اجرای کنش‌های پیشخوان (اصلاح/نادیده‌گرفتن/حذف).
	 */
	public function handle_admin_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		$action = isset( $_REQUEST['report_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['report_action'] ) ) : '';
		$id     = isset( $_REQUEST['report_id'] ) ? absint( wp_unslash( $_REQUEST['report_id'] ) ) : 0;

		check_admin_referer( 'manacore_report_' . $id );

		switch ( $action ) {
			case 'fixed':
			case 'ignored':
				self::set_status( $id, $action );
				break;

			case 'delete':
				self::delete( $id );
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'manacore',
					'tab'             => 'tools',
					'manacore_notice' => 'report-ok',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * دکمه‌ی گزارش خرابی برای جدول دانلود (سمت کاربر).
	 *
	 * به‌صورت عنصر واقعی `<button>` ساخته می‌شود تا با کیبورد هم کار کند؛
	 * `data-manacore-report` و داده‌های لینک را جاوااسکریپت می‌خواند.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $url     نشانی لینک.
	 * @param string $quality کیفیت.
	 * @return string
	 */
	public static function button( $post_id, $url, $quality = '' ) {
		/**
		 * فیلتر نمایش دکمه‌ی گزارش خرابی.
		 *
		 * @param bool $show آیا نمایش داده شود؟
		 */
		if ( ! apply_filters( 'manacore_show_report_button', true ) ) {
			return '';
		}

		return sprintf(
			'<button type="button" class="manacore-report" data-manacore-report data-post-id="%1$d" data-link-url="%2$s" data-quality="%3$s" aria-label="%4$s"><span aria-hidden="true">⚑</span> %5$s</button>',
			absint( $post_id ),
			esc_url( $url ),
			esc_attr( $quality ),
			esc_attr__( 'گزارش خرابی لینک', 'manacore' ),
			esc_html__( 'خراب است؟', 'manacore' )
		);
	}
}

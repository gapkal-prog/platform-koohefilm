<?php
/**
 * درخواست فیلم/سریال توسط کاربران + رأی‌گیری.
 *
 * یکی از پرکاربردترین بخش‌های سایت‌های فارسی «درخواست کاربر» است: کاربر
 * عنوانی را که در آرشیو نیست ثبت می‌کند، بقیه به آن رأی می‌دهند و مدیر
 * بر پایه‌ی رأی‌ها برنامه‌ریزی انتشار می‌کند. طراحی این ماژول:
 *
 * - درخواست‌ها یک نوع محتوای خصوصی (`manacore_request`) هستند، پس مدیریت
 *   آن‌ها همان فهرست/ویرایشگر استاندارد وردپرس است؛ ولی چون محتوای واقعی
 *   سایت نیستند در آرشیو و جست‌وجو و نقشه‌ی سایت ظاهر نمی‌شوند.
 * - چرخه‌ی کار با وضعیت‌های خودِ وردپرس: `pending` = در انتظار بازبینی،
 *   `publish` = تأییدشده (روی تخته‌ی درخواست‌ها دیده می‌شود)،
 *   `draft` = رد‌شده. هیچ وضعیت سفارشی‌ای ساخته نمی‌شود.
 * - رأی‌ها در جدول اختصاصی با کلید یکتا ذخیره می‌شوند (نه در متا)، چون
 *   هم شمارش دقیق می‌ماند و هم رأی تکراری از یک کاربر ممکن نیست.
 *   شمارش هر درخواست در متا آینه می‌شود تا مرتب‌سازی «پررأی‌ترین‌ها» یک
 *   کوئری ارزان باشد.
 * - ثبت درخواست تکراری، درخواست تازه نمی‌سازد؛ رأی کاربر را به درخواست
 *   موجود اضافه می‌کند (رفتار درست‌تر و همان چیزی که کاربر می‌خواهد).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Requests
 */
class Requests {

	use Singleton;

	/**
	 * نوع محتوای درخواست‌ها.
	 */
	const POST_TYPE = 'manacore_request';

	/**
	 * کلید متایی که شمارش رأی‌ها را آینه می‌کند.
	 */
	const COUNT_META = 'manacore_request_votes';

	/**
	 * عنوان نشست محدودسازی نرخ ثبت.
	 */
	const RATE_KEY = 'manacore_request_rate_';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_shortcode( 'manacore_request_form', array( $this, 'form_shortcode' ) );
		add_shortcode( 'manacore_requests', array( $this, 'board_shortcode' ) );

		/* ستون‌های پیشخوان */
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'admin_filters' ) );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'admin_views' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );

		/* کنش‌های بازبینی */
		add_action( 'admin_post_manacore_request_action', array( $this, 'handle_admin_action' ) );

		/* پاک‌سازی رأی‌ها همراه با حذف درخواست */
		add_action( 'before_delete_post', array( $this, 'delete_votes' ) );
	}

	/* ---------------------------------------------------------------------
	 * ثبت نوع محتوا
	 * ------------------------------------------------------------------ */

	/**
	 * ثبت نوع محتوای «درخواست».
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'درخواست‌ها', 'manacore' ),
					'singular_name'      => __( 'درخواست', 'manacore' ),
					'menu_name'          => __( 'درخواست‌ها', 'manacore' ),
					'all_items'          => __( 'همه‌ی درخواست‌ها', 'manacore' ),
					'add_new'            => __( 'افزودن درخواست', 'manacore' ),
					'add_new_item'       => __( 'افزودن درخواست تازه', 'manacore' ),
					'edit_item'          => __( 'ویرایش درخواست', 'manacore' ),
					'new_item'           => __( 'درخواست تازه', 'manacore' ),
					'view_item'          => __( 'دیدن درخواست', 'manacore' ),
					'search_items'       => __( 'جست‌وجوی درخواست‌ها', 'manacore' ),
					'not_found'          => __( 'درخواستی پیدا نشد.', 'manacore' ),
					'not_found_in_trash' => __( 'درخواستی در زباله‌دان نیست.', 'manacore' ),
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
				'supports'            => array( 'title', 'editor' ),
			)
		);
	}

	/**
	 * انواع محتوایی که می‌توان درخواست کرد.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		$titles = array_flip( manacore_title_post_types() );
		$types  = array_intersect_key( manacore_post_types(), $titles );

		/**
		 * فهرست انواع درخواست‌شدنی.
		 *
		 * @param array<string,string> $types نگاشت اسلاگ به برچسب.
		 */
		return apply_filters( 'manacore_request_types', $types );
	}

	/**
	 * تنظیمات ماژول درخواست‌ها.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = array(
			'enabled'       => (bool) manacore_get_option( 'requests_enabled', 1 ),
			'guests'        => (bool) manacore_get_option( 'requests_guests', 1 ),
			'heading'       => (string) manacore_get_option( 'requests_heading', __( 'فیلم یا سریالی که دنبالش هستید را بگویید', 'manacore' ) ),
			'intro'         => (string) manacore_get_option( 'requests_intro', __( 'عنوان را ثبت کنید؛ هرچه رأی بیشتری بگیرد، زودتر منتشر می‌شود.', 'manacore' ) ),
			'button'        => (string) manacore_get_option( 'requests_button', __( 'ثبت درخواست', 'manacore' ) ),
			'thanks'        => (string) manacore_get_option( 'requests_thanks', __( 'درخواست شما ثبت شد. به‌محض تأیید، به تخته‌ی درخواست‌ها اضافه می‌شود.', 'manacore' ) ),
			'board_title'   => (string) manacore_get_option( 'requests_board_title', __( 'درخواست‌های کاربران', 'manacore' ) ),
			'per_page'      => (int) manacore_get_option( 'requests_per_page', 12 ),
			'show_pending'  => (bool) manacore_get_option( 'requests_show_pending', 1 ),
		);

		$settings['per_page'] = max( 3, min( 60, $settings['per_page'] ) );

		/**
		 * تنظیمات ماژول درخواست‌ها.
		 *
		 * @param array $settings تنظیمات.
		 */
		return apply_filters( 'manacore_request_settings', $settings );
	}

	/* ---------------------------------------------------------------------
	 * نوشتن: ثبت درخواست و رأی
	 * ------------------------------------------------------------------ */

	/**
	 * کلید مقایسه‌ی عنوان‌ها (بی‌توجه به نویسه‌های عربی/فاصله‌ی مجازی).
	 *
	 * کاربران فارسی «ي/ك» عربی و نیم‌فاصله را متفاوت می‌نویسند؛ بدون
	 * یکسان‌سازی، یک درخواست ده‌بار ثبت می‌شد.
	 *
	 * @param string $title عنوان.
	 * @return string
	 */
	public static function normalize_title( $title ) {
		$title = (string) $title;
		$title = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8B", "\xEF\xBB\xBF" ), '', $title );
		$title = str_replace( array( 'ي', 'ك', 'ۀ', 'ﻩ', 'أ', 'إ', 'آ' ), array( 'ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا' ), $title );
		$title = preg_replace( '/[\x{200C}-\x{200F}]/u', '', $title );
		$title = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $title );

		return trim( mb_strtolower( (string) $title ) );
	}

	/**
	 * یافتن درخواست موجود با همان عنوان (تکراری‌سنجی).
	 *
	 * @param string $title   عنوان.
	 * @param string $type    نوع محتوا (خالی = همه).
	 * @return int شناسه‌ی درخواست یا صفر.
	 */
	public static function find_existing( $title, $type = '' ) {
		$needle = self::normalize_title( $title );

		if ( '' === $needle ) {
			return 0;
		}

		$args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => array( 'publish', 'pending' ),
			'posts_per_page'         => 200,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => false,
			'update_post_term_cache' => false,
		);

		if ( '' !== $type ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'manacore_request_type',
					'value' => $type,
				),
			);
		}

		foreach ( get_posts( $args ) as $candidate ) {
			if ( self::normalize_title( get_the_title( $candidate ) ) === $needle ) {
				return (int) $candidate;
			}
		}

		return 0;
	}

	/**
	 * ثبت یک درخواست تازه.
	 *
	 * @param array $data داده‌ی خام (عنوان، نوع، سال، پیوند، توضیح، نام).
	 * @return array|\WP_Error
	 */
	public static function submit( $data ) {
		$settings = self::settings();

		if ( ! $settings['enabled'] ) {
			return new \WP_Error(
				'manacore_request_disabled',
				__( 'ثبت درخواست در این سایت خاموش است.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $settings['guests'] && ! is_user_logged_in() ) {
			return new \WP_Error(
				'manacore_request_login',
				__( 'برای ثبت درخواست وارد حساب خود شوید.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		/* تله‌ی ربات‌ها: این فیلد پنهان است و کاربر واقعی پرش نمی‌کند. */
		if ( ! empty( $data['hp'] ) ) {
			return new \WP_Error(
				'manacore_request_spam',
				__( 'ارسال ناموفق بود.', 'manacore' ),
				array( 'status' => 400 )
			);
		}

		$title = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		$title = trim( mb_substr( $title, 0, 190 ) );

		if ( mb_strlen( $title ) < 2 ) {
			return new \WP_Error(
				'manacore_request_title',
				__( 'نام فیلم یا سریال را کامل بنویسید.', 'manacore' ),
				array( 'status' => 400 )
			);
		}

		$types = self::types();
		$type  = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : '';
		$type  = isset( $types[ $type ] ) ? $type : (string) key( $types );

		$year = isset( $data['year'] ) ? absint( $data['year'] ) : 0;
		if ( $year && ( $year < 1900 || $year > ( (int) gmdate( 'Y' ) + 3 ) ) ) {
			$year = 0;
		}

		$link = isset( $data['link'] ) ? esc_url_raw( trim( (string) $data['link'] ) ) : '';
		$link = str_replace( array( '<', '>' ), '', $link );
		$link = mb_substr( $link, 0, 500 );

		$note = isset( $data['note'] ) ? sanitize_textarea_field( (string) $data['note'] ) : '';
		$note = mb_substr( trim( $note ), 0, 600 );

		/* نرخ: هر بازدیدکننده در هر دقیقه یک درخواست. */
		$rate_key = self::RATE_KEY . md5( manacore_visitor_hash( 'request' ) );
		if ( get_transient( $rate_key ) ) {
			return new \WP_Error(
				'manacore_request_slow',
				__( 'کمی صبر کنید و دوباره تلاش کنید.', 'manacore' ),
				array( 'status' => 429 )
			);
		}

		/* درخواست تکراری: رأی کاربر به درخواست موجود اضافه می‌شود. */
		$existing = self::find_existing( $title, $type );

		if ( $existing ) {
			$vote = self::vote( $existing );

			if ( is_wp_error( $vote ) ) {
				return $vote;
			}

			return array(
				'ok'        => true,
				'id'        => $existing,
				'votes'     => (int) $vote['votes'],
				'duplicate' => true,
				'message'   => __( 'این عنوان پیش‌تر ثبت شده بود؛ رأی شما هم به آن اضافه شد.', 'manacore' ),
			);
		}

		$user_id = get_current_user_id();

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_title'   => $title,
				'post_content' => $note,
				'post_status'  => 'pending',
				'post_author'  => $user_id,
				'meta_input'   => array(
					'manacore_request_type'  => $type,
					'manacore_request_year'  => $year,
					'manacore_request_link'  => $link,
					'manacore_request_hash'  => manacore_visitor_hash( 'request' ),
					self::COUNT_META         => 0,
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		/* ثبت‌کننده خودش اولین رأی را می‌دهد. */
		$vote = self::vote( (int) $post_id );

		/**
		 * پس از ثبت درخواست تازه.
		 *
		 * @param int   $post_id شناسه‌ی درخواست.
		 * @param array $data    داده‌ی خام.
		 */
		do_action( 'manacore_request_created', (int) $post_id, $data );

		return array(
			'ok'        => true,
			'id'        => (int) $post_id,
			'votes'     => is_array( $vote ) ? (int) $vote['votes'] : 1,
			'duplicate' => false,
			'message'   => $settings['thanks'],
		);
	}

	/**
	 * رأی به یک درخواست.
	 *
	 * @param int $request_id شناسه‌ی درخواست.
	 * @return array|\WP_Error
	 */
	public static function vote( $request_id ) {
		global $wpdb;

		$request_id = absint( $request_id );
		$post       = get_post( $request_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'pending' ), true ) ) {
			return new \WP_Error(
				'manacore_request_missing',
				__( 'این درخواست در دسترس نیست.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		$table  = Install::request_votes_table();
		$hash   = manacore_visitor_hash( 'request' );
		$user   = get_current_user_id();
		$insert = $wpdb->insert(
			$table,
			array(
				'request_id' => $request_id,
				'voter_id'   => $user,
				'voter_hash' => $hash,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		$duplicate = ( false === $insert );

		$count = self::recount( $request_id );

		if ( ! $duplicate ) {
			/**
			 * پس از ثبت رأی تازه.
			 *
			 * @param int $request_id شناسه‌ی درخواست.
			 * @param int $count      شمار رأی‌ها.
			 */
			do_action( 'manacore_request_voted', $request_id, $count );
		}

		return array(
			'ok'        => true,
			'id'        => $request_id,
			'votes'     => $count,
			'duplicate' => $duplicate,
			'message'   => $duplicate
				? __( 'شما پیش‌تر به این درخواست رأی داده‌اید.', 'manacore' )
				: __( 'رأی شما ثبت شد.', 'manacore' ),
		);
	}

	/**
	 * بازشماری رأی‌های یک درخواست از جدول و آینه‌کردن آن در متا.
	 *
	 * @param int $request_id شناسه‌ی درخواست.
	 * @return int
	 */
	public static function recount( $request_id ) {
		global $wpdb;

		$request_id = absint( $request_id );
		$table      = Install::request_votes_table();
		$count      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE request_id = %d", $request_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		update_post_meta( $request_id, self::COUNT_META, $count );

		return $count;
	}

	/**
	 * شمار رأی‌های یک درخواست.
	 *
	 * @param int $request_id شناسه‌ی درخواست.
	 * @return int
	 */
	public static function votes( $request_id ) {
		$stored = get_post_meta( $request_id, self::COUNT_META, true );

		if ( '' === $stored ) {
			return self::recount( $request_id );
		}

		return (int) $stored;
	}

	/**
	 * حذف رأی‌های یک درخواست (هنگام پاک‌کردن خود درخواست).
	 *
	 * @param int $post_id شناسه‌ی پست.
	 */
	public function delete_votes( $post_id ) {
		global $wpdb;

		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$table = Install::request_votes_table();
		$wpdb->delete( $table, array( 'request_id' => absint( $post_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ---------------------------------------------------------------------
	 * خواندن: فهرست درخواست‌ها
	 * ------------------------------------------------------------------ */

	/**
	 * فهرست درخواست‌ها برای تخته‌ی عمومی.
	 *
	 * @param array $args ترتیب/تعداد/وضعیت.
	 * @return \WP_Query
	 */
	public static function query( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'votes',
				'per_page' => 12,
				'paged'   => 1,
				'status'  => 'approved',
				'type'    => '',
			)
		);

		$statuses = 'pending' === $args['status']
			? array( 'pending' )
			: ( 'all' === $args['status'] ? array( 'publish', 'pending' ) : array( 'publish' ) );

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => max( 1, min( 60, (int) $args['per_page'] ) ),
			'paged'          => max( 1, (int) $args['paged'] ),
			'meta_key'       => self::COUNT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'votes' === $args['orderby']
				? array( 'meta_value_num' => 'DESC', 'date' => 'DESC' )
				: array( 'date' => 'DESC' ),
		);

		if ( '' !== $args['type'] ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'manacore_request_type',
					'value' => sanitize_key( $args['type'] ),
				),
			);
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * شمار درخواست‌ها به تفکیک وضعیت.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		$counts = (array) wp_count_posts( self::POST_TYPE );

		return array(
			'pending' => isset( $counts['pending'] ) ? (int) $counts['pending'] : 0,
			'publish' => isset( $counts['publish'] ) ? (int) $counts['publish'] : 0,
			'draft'   => isset( $counts['draft'] ) ? (int) $counts['draft'] : 0,
			'trash'   => isset( $counts['trash'] ) ? (int) $counts['trash'] : 0,
		);
	}

	/* ---------------------------------------------------------------------
	 * REST
	 * ------------------------------------------------------------------ */

	/**
	 * ثبت مسیرهای REST.
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Api::NS,
			'/request',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_submit' ),
				'permission_callback' => array( Rest_Api::instance(), 'verify_public_write' ),
				'args'                => array(
					'title' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'year'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'link'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'note'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'hp'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			Rest_Api::NS,
			'/requests',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_list' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'status'   => array(
						'type'              => 'string',
						'default'           => 'approved',
						'sanitize_callback' => 'sanitize_key',
					),
					'orderby'  => array(
						'type'              => 'string',
						'default'           => 'votes',
						'sanitize_callback' => 'sanitize_key',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 12,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			Rest_Api::NS,
			'/request-vote',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_vote' ),
				'permission_callback' => array( Rest_Api::instance(), 'verify_public_write' ),
				'args'                => array(
					'request_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * پاسخ مسیر ثبت درخواست.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_submit( $request ) {
		$result = self::submit(
			array(
				'title' => $request->get_param( 'title' ),
				'type'  => $request->get_param( 'type' ),
				'year'  => $request->get_param( 'year' ),
				'link'  => $request->get_param( 'link' ),
				'note'  => $request->get_param( 'note' ),
				'hp'    => $request->get_param( 'hp' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, $result['duplicate'] ? 200 : 201 );
	}

	/**
	 * پاسخ مسیر فهرست درخواست‌ها (تازه‌سازی زنده‌ی شمار رأی‌ها).
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function rest_list( $request ) {
		$query = self::query(
			array(
				'status'   => (string) $request->get_param( 'status' ),
				'orderby'  => (string) $request->get_param( 'orderby' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'     => (int) $post->ID,
				'votes'  => self::votes( (int) $post->ID ),
				'status' => (string) $post->post_status,
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'total' => (int) $query->found_posts,
				'items' => $items,
			),
			200
		);
	}

	/**
	 * پاسخ مسیر رأی.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_vote( $request ) {
		$result = self::vote( $request->get_param( 'request_id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/* ---------------------------------------------------------------------
	 * رندر سمت کاربر
	 * ------------------------------------------------------------------ */

	/**
	 * رندر فرم درخواست.
	 *
	 * @param array $attrs ویژگی‌های بلوک/شورت‌کد.
	 * @return string
	 */
	public function render_form( $attrs = array() ) {
		$settings = self::settings();
		$attrs    = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			array(
				'heading'  => '',
				'intro'    => '',
				'button'   => '',
				'showTypes' => true,
				'className' => '',
			)
		);

		if ( ! $settings['enabled'] ) {
			return '';
		}

		$logged_in = is_user_logged_in();

		if ( ! $settings['guests'] && ! $logged_in ) {
			return sprintf(
				'<div class="manacore-request-gate">%s <a href="%s">%s</a></div>',
				esc_html__( 'برای ثبت درخواست باید وارد حساب خود شوید.', 'manacore' ),
				esc_url( wp_login_url( get_permalink() ? get_permalink() : home_url( '/' ) ) ),
				esc_html__( 'ورود به حساب', 'manacore' )
			);
		}

		$types   = self::types();
		$heading = '' !== $attrs['heading'] ? $attrs['heading'] : $settings['heading'];
		$intro   = '' !== $attrs['intro'] ? $attrs['intro'] : $settings['intro'];
		$button  = '' !== $attrs['button'] ? $attrs['button'] : $settings['button'];

		ob_start();
		?>
		<section class="manacore-request-form-wrap">
			<?php if ( '' !== trim( (string) $heading ) ) : ?>
				<h2 class="manacore-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) $intro ) ) : ?>
				<p class="manacore-request-form__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>

			<form class="manacore-request-form" data-manacore-request-form novalidate>
				<div class="manacore-request-form__grid">
					<label class="manacore-request-form__field manacore-request-form__field--wide">
						<span><?php esc_html_e( 'نام فیلم یا سریال', 'manacore' ); ?> <b aria-hidden="true">*</b></span>
						<input type="text" name="title" required minlength="2" maxlength="190"
							placeholder="<?php esc_attr_e( 'مثلاً: نام سریال — فصل ۲', 'manacore' ); ?>" />
					</label>

					<?php if ( $attrs['showTypes'] && count( $types ) > 1 ) : ?>
						<label class="manacore-request-form__field">
							<span><?php esc_html_e( 'نوع', 'manacore' ); ?></span>
							<select name="type">
								<?php foreach ( $types as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>

					<label class="manacore-request-form__field">
						<span><?php esc_html_e( 'سال ساخت (اختیاری)', 'manacore' ); ?></span>
						<input type="number" name="year" min="1900" max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 3 ) ); ?>" inputmode="numeric" />
					</label>

					<label class="manacore-request-form__field">
						<span><?php esc_html_e( 'پیوند منبع (اختیاری)', 'manacore' ); ?></span>
						<input type="url" name="link" dir="ltr" placeholder="https://" />
					</label>

					<label class="manacore-request-form__field manacore-request-form__field--wide">
						<span><?php esc_html_e( 'توضیح کوتاه (اختیاری)', 'manacore' ); ?></span>
						<textarea name="note" rows="3" maxlength="600" placeholder="<?php esc_attr_e( 'مثلاً: نسخه‌ی دوبله‌ی فارسی با کیفیت ۱۰۸۰p', 'manacore' ); ?>"></textarea>
					</label>
				</div>

				<?php /* تله‌ی ربات: از دید کاربر پنهان است. */ ?>
				<div class="manacore-hp" aria-hidden="true">
					<label>
						<span><?php esc_html_e( 'این فیلد را خالی بگذارید', 'manacore' ); ?></span>
						<input type="text" name="hp" tabindex="-1" autocomplete="off" />
					</label>
				</div>

				<div class="manacore-request-form__actions">
					<button type="submit" class="manacore-btn is-primary">
						<span data-manacore-request-submit-label><?php echo esc_html( $button ); ?></span>
					</button>
					<p class="manacore-request-form__status" data-manacore-request-status role="status" aria-live="polite"></p>
				</div>
			</form>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * رندر تخته‌ی درخواست‌ها.
	 *
	 * @param array $attrs ویژگی‌های بلوک/شورت‌کد.
	 * @return string
	 */
	public function render_board( $attrs = array() ) {
		$settings = self::settings();
		$attrs    = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			array(
				'heading'     => '',
				'perPage'     => 0,
				'orderby'     => 'votes',
				'showPending' => (bool) $settings['show_pending'],
				'type'        => '',
			)
		);

		$status = $attrs['showPending'] ? 'all' : 'approved';

		$query = self::query(
			array(
				'orderby'  => in_array( $attrs['orderby'], array( 'votes', 'date' ), true ) ? $attrs['orderby'] : 'votes',
				'per_page' => $attrs['perPage'] ? (int) $attrs['perPage'] : $settings['per_page'],
				'status'   => $status,
				'type'     => $attrs['type'],
			)
		);

		$heading = '' !== $attrs['heading'] ? $attrs['heading'] : $settings['board_title'];
		$types   = self::types();

		ob_start();
		?>
		<section class="manacore-requests" data-manacore-requests>
			<?php if ( '' !== trim( (string) $heading ) ) : ?>
				<h2 class="manacore-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<?php if ( ! $query->have_posts() ) : ?>
				<p class="manacore-requests__empty"><?php esc_html_e( 'هنوز درخواستی ثبت نشده است. اولین نفر باشید!', 'manacore' ); ?></p>
			<?php else : ?>
				<ul class="manacore-requests__list">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$id      = get_the_ID();
						$type    = (string) get_post_meta( $id, 'manacore_request_type', true );
						$year    = (int) get_post_meta( $id, 'manacore_request_year', true );
						$link    = (string) get_post_meta( $id, 'manacore_request_link', true );
						$pending = 'publish' !== get_post_status( $id );
						?>
						<li class="manacore-request<?php echo $pending ? ' is-pending' : ''; ?>" data-request-id="<?php echo esc_attr( (string) $id ); ?>">
							<button type="button" class="manacore-request__vote" data-manacore-vote data-request-id="<?php echo esc_attr( (string) $id ); ?>"
								aria-label="<?php echo esc_attr( sprintf( /* translators: %s: نام اثر */ __( 'رأی دادن به %s', 'manacore' ), get_the_title() ) ); ?>">
								<span class="manacore-request__count" data-request-count><?php echo esc_html( manacore_fa_digits( number_format_i18n( self::votes( $id ) ) ) ); ?></span>
								<span class="manacore-request__vote-label"><?php esc_html_e( 'رأی', 'manacore' ); ?></span>
							</button>

							<div class="manacore-request__body">
								<h3 class="manacore-request__title"><?php the_title(); ?></h3>

								<p class="manacore-request__meta">
									<?php if ( isset( $types[ $type ] ) ) : ?>
										<span class="manacore-request__chip"><?php echo esc_html( $types[ $type ] ); ?></span>
									<?php endif; ?>

									<?php if ( $year ) : ?>
										<span class="manacore-request__chip"><?php echo esc_html( manacore_fa_digits( (string) $year ) ); ?></span>
									<?php endif; ?>

									<?php if ( $pending ) : ?>
										<span class="manacore-request__chip is-pending"><?php esc_html_e( 'در انتظار تأیید', 'manacore' ); ?></span>
									<?php endif; ?>

									<span class="manacore-request__date"><?php echo esc_html( manacore_fa_date( get_the_date( 'Y-m-d H:i:s' ) ) ); ?></span>
								</p>

								<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
									<p class="manacore-request__note"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 30 ) ); ?></p>
								<?php endif; ?>

								<?php if ( $link ) : ?>
									<a class="manacore-request__source" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer nofollow">
										<?php esc_html_e( 'دیدن منبع', 'manacore' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</li>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</ul>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد فرم.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function form_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'heading' => '',
				'intro'   => '',
				'button'  => '',
			),
			is_array( $atts ) ? $atts : array(),
			'manacore_request_form'
		);

		return $this->render_form( $atts );
	}

	/**
	 * شورت‌کد تخته‌ی درخواست‌ها.
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function board_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'heading'       => '',
				'per_page'      => 0,
				'orderby'       => 'votes',
				'show_pending'  => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'manacore_requests'
		);

		return $this->render_board(
			array(
				'heading'     => $atts['heading'],
				'perPage'     => (int) $atts['per_page'],
				'orderby'     => $atts['orderby'],
				'showPending' => in_array( (string) $atts['show_pending'], array( '1', 'true', 'yes' ), true ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * پیشخوان
	 * ------------------------------------------------------------------ */

	/**
	 * ستون‌های فهرست درخواست‌ها.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );

		$columns[ self::COUNT_META ] = __( 'رأی', 'manacore' );
		$columns['manacore_type']    = __( 'نوع', 'manacore' );
		$columns['manacore_year']    = __( 'سال', 'manacore' );
		$columns['manacore_source']  = __( 'پیوند منبع', 'manacore' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * محتوای ستون‌های اختصاصی.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه‌ی پست.
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case self::COUNT_META:
				echo '<strong>' . esc_html( manacore_fa_digits( number_format_i18n( self::votes( $post_id ) ) ) ) . '</strong>';
				break;

			case 'manacore_type':
				$types = self::types();
				$type  = (string) get_post_meta( $post_id, 'manacore_request_type', true );
				echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : '—' );
				break;

			case 'manacore_year':
				$year = (int) get_post_meta( $post_id, 'manacore_request_year', true );
				echo esc_html( $year ? manacore_fa_digits( (string) $year ) : '—' );
				break;

			case 'manacore_source':
				$link = (string) get_post_meta( $post_id, 'manacore_request_link', true );

				if ( '' === $link ) {
					echo '—';
					break;
				}

				printf(
					'<a href="%s" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
					esc_url( $link ),
					esc_html__( 'بازکردن', 'manacore' )
				);
				break;
		}
	}

	/**
	 * ستون‌های مرتب‌شدنی.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns[ self::COUNT_META ] = self::COUNT_META;

		return $columns;
	}

	/**
	 * بازنویسی ترتیب فهرست پیشخوان بر پایه‌ی متا.
	 *
	 * @param \WP_Query $query کوئری فهرست.
	 */
	public function admin_orderby( $query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// ترتیب ستون «رأی» از طریق پارامتر `orderby` وردپرس با `meta_value_num` انجام می‌شود؛
		// این متد تنها برای مستندسازی محل قلاب نگه داشته شده است.
	}

	/**
	 * فهرست کشویی نوع در فهرست پیشخوان.
	 *
	 * @param string $post_type نوع پست جاری.
	 */
	public function admin_filters( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$current = isset( $_GET['manacore_request_type'] ) ? sanitize_key( wp_unslash( $_GET['manacore_request_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$types   = self::types();
		?>
		<label class="screen-reader-text" for="manacore_request_type"><?php esc_html_e( 'پالایش بر پایه‌ی نوع', 'manacore' ); ?></label>
		<select name="manacore_request_type" id="manacore_request_type">
			<option value=""><?php esc_html_e( 'همه‌ی نوع‌ها', 'manacore' ); ?></option>
			<?php foreach ( $types as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * پیوند «در انتظار بازبینی» در بالای فهرست.
	 *
	 * @param array $views نماها.
	 * @return array
	 */
	public function admin_views( $views ) {
		$counts = self::counts();

		if ( $counts['pending'] ) {
			$url = add_query_arg(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'pending',
				),
				admin_url( 'edit.php' )
			);

			$current        = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$views['pending'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				'pending' === $current ? ' class="current" aria-current="page"' : '',
				esc_html__( 'در انتظار بازبینی', 'manacore' ),
				esc_html( number_format_i18n( $counts['pending'] ) )
			);
		}

		return $views;
	}

	/**
	 * کنش‌های ردیف: تأیید، رد، ساخت پیش‌نویس اثر.
	 *
	 * @param array    $actions کنش‌ها.
	 * @param \WP_Post $post    پست.
	 * @return array
	 */
	public function row_actions( $actions, $post = null ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$base = admin_url( 'admin-post.php' );

		$link = static function ( $id, $do, $label, $class = '' ) use ( $base ) {
			$url = add_query_arg(
				array(
					'action'     => 'manacore_request_action',
					'request_id' => (int) $id,
					'do'         => $do,
				),
				$base
			);

			return array(
				'label' => $label,
				'url'   => wp_nonce_url( $url, 'manacore_request_' . (int) $id . '_' . $do ),
				'class' => $class,
			);
		};

		$prepared = array();

		if ( 'publish' !== $post->post_status ) {
			$prepared[] = $link( $post->ID, 'approve', __( 'تأیید', 'manacore' ) );
		} else {
			$prepared[] = $link( $post->ID, 'reject', __( 'لغو تأیید', 'manacore' ) );
		}

		if ( 'draft' !== $post->post_status ) {
			$prepared[] = $link( $post->ID, 'reject', __( 'رد کردن', 'manacore' ) );
		}

		$prepared[] = $link( $post->ID, 'convert', __( 'ساخت پیش‌نویس اثر', 'manacore' ) );

		foreach ( $prepared as $item ) {
			$actions[ 'manacore_' . md5( $item['url'] ) ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $item['url'] ),
				$item['class'] ? ' class="' . esc_attr( $item['class'] ) . '"' : '',
				esc_html( $item['label'] )
			);
		}

		return $actions;
	}

	/**
	 * اجرای کنش‌های بازبینی.
	 */
	public function handle_admin_action() {
		$request_id = isset( $_GET['request_id'] ) ? absint( wp_unslash( $_GET['request_id'] ) ) : 0;
		$do         = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

		if ( ! $request_id || ! current_user_can( 'edit_post', $request_id ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		check_admin_referer( 'manacore_request_' . $request_id . '_' . $do );

		$notice = 'failed';

		switch ( $do ) {
			case 'approve':
				wp_update_post(
					array(
						'ID'          => $request_id,
						'post_status' => 'publish',
					)
				);
				$notice = 'approved';
				break;

			case 'reject':
				wp_update_post(
					array(
						'ID'          => $request_id,
						'post_status' => 'draft',
					)
				);
				$notice = 'rejected';
				break;

			case 'convert':
				$notice = self::convert_to_draft( $request_id ) ? 'converted' : 'failed';
				break;
		}

		/**
		 * پس از اجرای کنش بازبینی درخواست.
		 *
		 * @param int    $request_id شناسه‌ی درخواست.
		 * @param string $do         کنش.
		 * @param string $notice     پیام.
		 */
		do_action( 'manacore_request_action_done', $request_id, $do, $notice );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => self::POST_TYPE,
					'manacore_notice'  => $notice,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * ساخت پیش‌نویس «اثر» از یک درخواست تأییدشده.
	 *
	 * مدیر پس از فراهم‌کردن فایل‌ها، فقط باید لینک‌ها و پوستر را اضافه
	 * کند؛ عنوان/سال/نوع از قبل پر شده است.
	 *
	 * @param int $request_id شناسه‌ی درخواست.
	 * @return int شناسه‌ی پیش‌نویس تازه یا صفر.
	 */
	public static function convert_to_draft( $request_id ) {
		$request_id = absint( $request_id );
		$post       = get_post( $request_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return 0;
		}

		$types = self::types();
		$type  = (string) get_post_meta( $request_id, 'manacore_request_type', true );
		$type  = isset( $types[ $type ] ) ? $type : (string) key( $types );

		$year = (int) get_post_meta( $request_id, 'manacore_request_year', true );
		$link = (string) get_post_meta( $request_id, 'manacore_request_link', true );

		$draft_id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => $post->post_title,
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
				'post_content' => '',
				'meta_input'   => array(
					'manacore_year'           => $year ? $year : '',
					'manacore_request_source' => $request_id,
					'manacore_import_url'     => $link,
				),
			),
			true
		);

		if ( is_wp_error( $draft_id ) || ! $draft_id ) {
			return 0;
		}

		/* نشانه‌گذاری درخواست تا مدیر بداند پیش‌نویسش ساخته شده است. */
		update_post_meta( $request_id, 'manacore_request_draft_id', (int) $draft_id );
		wp_update_post(
			array(
				'ID'          => $request_id,
				'post_status' => 'publish',
			)
		);

		/**
		 * پس از ساخت پیش‌نویس اثر از روی درخواست.
		 *
		 * @param int $draft_id   شناسه‌ی پیش‌نویس.
		 * @param int $request_id شناسه‌ی درخواست.
		 */
		do_action( 'manacore_request_converted', (int) $draft_id, $request_id );

		return (int) $draft_id;
	}

	/* ---------------------------------------------------------------------
	 * ساخت برگه
	 * ------------------------------------------------------------------ */

	/**
	 * ساخت (یا یافتن) برگه‌ی «درخواست‌ها».
	 *
	 * برگه با مارک‌آپ بلوک‌ها ساخته می‌شود تا مدیر بتواند بعداً در
	 * ویرایشگر جابه‌جایش کند و برگه‌ی موجود هرگز بازنویسی نمی‌شود.
	 *
	 * @return int شناسه‌ی برگه.
	 */
	public static function create_page() {
		$existing = (int) get_option( 'manacore_request_page', 0 );

		if ( $existing && 'publish' === get_post_status( $existing ) ) {
			return $existing;
		}

		$page = get_page_by_path( 'requests', OBJECT, 'page' );

		if ( $page ) {
			update_option( 'manacore_request_page', (int) $page->ID );

			return (int) $page->ID;
		}

		$content = "<!-- wp:manacore/request-form /-->\n\n<!-- wp:manacore/requests {\"heading\":\"\"} /-->";

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'درخواست فیلم و سریال', 'manacore' ),
				'post_name'    => 'requests',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'manacore_request_page', (int) $page_id );

			return (int) $page_id;
		}

		return 0;
	}

	/**
	 * آدرس برگه‌ی درخواست‌ها (اگر ساخته شده باشد).
	 *
	 * @return string
	 */
	public static function page_url() {
		$page_id = (int) get_option( 'manacore_request_page', 0 );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}

		$page = get_page_by_path( 'requests', OBJECT, 'page' );

		return $page ? (string) get_permalink( $page->ID ) : '';
	}
}

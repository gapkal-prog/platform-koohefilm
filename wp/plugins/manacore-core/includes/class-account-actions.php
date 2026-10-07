<?php
/**
 * کنش‌های نوشتنی برگه‌ی «حساب کاربری» — فرم‌ها، خروجی داده و API پیشرفت.
 *
 * خواندنِ داده در {@see Account} است؛ این کلاس لایه‌ی نوشتن و رفت‌وبرگشت
 * کاربر است:
 *
 *   • فرم‌های بومی (بدون جاوااسکریپت): پروفایل، رمز عبور، عکس، پاک‌کردن
 *     تاریخچه — با نانس و الگوی POST/Redirect/GET تا ذخیره‌ی دوباره با
 *     رفرش رخ ندهد.
 *   • خروجی داده‌های کاربر (GET با نانس) — همان «داده‌هایت متعلق به توست.».
 *   • مسیر REST ثبت پیشرفت پخش، که پلیر با آن پیشرفت واقعی را ذخیره
 *     می‌کند (مرجع این کار را در `localStorage` می‌کرد).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Account_Actions
 */
class Account_Actions {

	use Singleton;

	/**
	 * نامک کنش فرم‌ها.
	 */
	const FORM_KEY = 'manacore_form';

	/**
	 * اندازه‌ی بیشینه‌ی عکس پروفایل (۵ مگابایت، همان سقف مرجع).
	 */
	const MAX_AVATAR = 5242880;

	/**
	 * قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'template_redirect', array( $this, 'handle_forms' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'pre_get_avatar_data', array( $this, 'filter_avatar' ), 10, 2 );
	}

	/**
	 * نشانی برگه‌ی حساب.
	 *
	 * @return string
	 */
	protected function page_url() {
		return (string) Account::page_url( array( 'tab' => 'settings' ) );
	}

	/**
	 * رسیدگی به فرم‌های بومی برگه‌ی حساب.
	 *
	 * @return void
	 */
	public function handle_forms() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- نانس پایین‌تر بررسی می‌شود.
		if ( ! isset( $_POST[ self::FORM_KEY ] ) ) {
			$this->maybe_export();
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST[ self::FORM_KEY ] ) );
		$nonce  = isset( $_POST['manacore_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['manacore_nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $this->page_url() ) );
			exit;
		}

		if ( ! wp_verify_nonce( $nonce, 'manacore_account_form' ) ) {
			$this->redirect( 'error' );
		}

		$notice = 'saved';
		$tab    = 'settings';

		switch ( $action ) {
			case 'profile':
				$notice = $this->save_profile();
				break;
			case 'password':
				$notice = $this->save_password();
				break;
			case 'avatar':
				$notice = $this->save_avatar();
				break;
			case 'clear-history':
				Account::clear_history();
				/*
				 * پاک کردن تاریچه از خودِ تب تاریخچه انجام می‌شود؛ کاربر باید
				 * همان‌جا بماند تا نتیجه‌ی کارش (حالت خالی) را ببیند، نه اینکه
				 * به تب تنظیمات پرت شود.
				 */
				$tab = 'history';
				break;
		}

		$this->redirect( $notice, $tab );
	}

	/**
	 * ذخیره‌ی نام نمایشی و تنظیمات تماشا.
	 *
	 * @return string کد پیام.
	 */
	protected function save_profile() {
		$user_id = get_current_user_id();
		$name    = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';

		if ( mb_strlen( $name ) < 2 ) {
			return 'error';
		}

		$result = wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
			)
		);

		if ( is_wp_error( $result ) ) {
			return 'error';
		}

		Account::set_pref( 'notifications', ! empty( $_POST['notifications'] ), $user_id );
		Account::set_pref( 'autoplay', ! empty( $_POST['autoplay'] ), $user_id );

		$quality = isset( $_POST['quality'] ) ? sanitize_key( wp_unslash( $_POST['quality'] ) ) : '1080';
		Account::set_pref( 'quality', in_array( $quality, array( '1080', '720', '360' ), true ) ? $quality : '1080', $user_id );

		return 'saved';
	}

	/**
	 * تغییر رمز عبور با تأیید رمز فعلی.
	 *
	 * @return string کد پیام.
	 */
	protected function save_password() {
		$user = wp_get_current_user();

		$current = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$new     = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
		$confirm = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

		if ( mb_strlen( $new ) < 8 || $new !== $confirm ) {
			return 'password-mismatch';
		}

		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			return 'password-wrong';
		}

		$result = wp_update_user(
			array(
				'ID'        => $user->ID,
				'user_pass' => $new,
			)
		);

		return is_wp_error( $result ) ? 'error' : 'password-saved';
	}

	/**
	 * ذخیره یا حذف عکس پروفایل.
	 *
	 * @return string کد پیام.
	 */
	protected function save_avatar() {
		$user_id = get_current_user_id();

		if ( ! empty( $_POST['manacore_remove_avatar'] ) ) {
			delete_user_meta( $user_id, Account::AVATAR_KEY );

			return 'avatar-removed';
		}

		if ( empty( $_FILES['manacore_avatar']['name'] ) ) {
			return 'error';
		}

		$file = $_FILES['manacore_avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- مسیر زیر با wp_handle_upload اعتبارسنجی می‌شود.

		if ( (int) $file['size'] > self::MAX_AVATAR ) {
			return 'avatar-too-big';
		}

		/*
		 * `wp_check_filetype()` آرایه برمی‌گرداند (`array( 'ext' => …, 'type' => … )`).
		 * قبلاً خودِ آرایه با رشته‌ها مقایسه می‌شد و شرط همیشه درست می‌شد؛
		 * یعنی هر بارگذاری سالم هم با پیام «فقط تصویر PNG…» برمی‌گشت.
		 */
		$check = wp_check_filetype( (string) $file['name'] );
		$mime  = isset( $check['type'] ) ? (string) $check['type'] : '';

		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return 'avatar-type';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		/*
		 * عکس پروفایل از دارایی خودِ کاربر است، نه محتوای سایت؛ پس
		 * جدا از دسترسی `upload_files` ذخیره می‌شود و پیوست به نام
		 * همان کاربر ثبت می‌گردد. نوع و حجم بالا بررسی شد.
		 */
		$attachment_id = media_handle_upload(
			'manacore_avatar',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return 'error';
		}

		wp_update_post(
			array(
				'ID'          => (int) $attachment_id,
				'post_author' => $user_id,
			)
		);

		$previous = (int) get_user_meta( $user_id, Account::AVATAR_KEY, true );
		update_user_meta( $user_id, Account::AVATAR_KEY, (int) $attachment_id );

		if ( $previous && $previous !== (int) $attachment_id ) {
			wp_delete_attachment( $previous, true );
		}

		return 'avatar-saved';
	}

	/**
	 * بازگشت به برگه‌ی حساب با کد پیام (الگوی PRG).
	 *
	 * @param string $notice کد پیام.
	 * @param string $tab    تبی که کاربر باید در آن بماند.
	 * @return void
	 */
	protected function redirect( $notice, $tab = 'settings' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'    => sanitize_key( $tab ),
					'notice' => sanitize_key( $notice ),
				),
				(string) Account::page_url()
			)
		);
		exit;
	}

	/**
	 * خروجی JSON از داده‌های کاربر.
	 *
	 * @return void
	 */
	protected function maybe_export() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- نانس پایین بررسی می‌شود.
		if ( empty( $_GET['manacore_export'] ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'manacore_export' ) ) {
			wp_die( esc_html__( 'دسترسی به این خروجی مجاز نیست.', 'manacore' ), '', array( 'response' => 403 ) );
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$payload = array(
			'exported_at' => gmdate( 'c' ),
			'profile'     => array(
				'id'           => $user_id,
				'name'         => $user->display_name,
				'email'        => $user->user_email,
				'registered'   => $user->user_registered,
			),
			'preferences' => Account::prefs( $user_id ),
			'watchlist'   => array_map(
				static function ( $post_id ) {
					return array(
						'id'    => $post_id,
						'title' => get_the_title( $post_id ),
						'url'   => get_permalink( $post_id ),
					);
				},
				Account::watchlist( $user_id )
			),
			'history'     => array_map(
				static function ( $row ) {
					$row['title'] = get_the_title( $row['post_id'] );
					$row['url']   = get_permalink( $row['post_id'] );
					return $row;
				},
				Account::history( $user_id, 200 )
			),
			'stats'       => Account::stats( $user_id ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=manacore-my-data.json' );
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * مسیرهای REST برگه‌ی حساب.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'manacore/v1',
			'/progress',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_progress' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'percent' => array(
						'required'          => true,
						'type'              => 'number',
						'sanitize_callback' => static function ( $value ) {
							return max( 0, min( 100, (float) $value ) );
						},
					),
					'minutes' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * ثبت پیشرفت پخش.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_progress( $request ) {
		$result = Account::record_progress(
			(int) $request->get_param( 'post_id' ),
			(float) $request->get_param( 'percent' ),
			(int) $request->get_param( 'minutes' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * عکس پروفایل سفارشی کاربر، در همه‌ی جاهایی که وردپرس آواتار می‌خواهد.
	 *
	 * @param array             $args داده‌های آواتار.
	 * @param int|string|object $id_or_email کاربر.
	 * @return array
	 */
	public function filter_avatar( $args, $id_or_email ) {
		$user_id = 0;

		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->ID ) ) {
			$user_id = (int) $id_or_email->ID;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user    = get_user_by( 'email', $id_or_email );
			$user_id = $user ? (int) $user->ID : 0;
		}

		if ( ! $user_id ) {
			return $args;
		}

		$attachment_id = (int) get_user_meta( $user_id, Account::AVATAR_KEY, true );
		if ( ! $attachment_id ) {
			return $args;
		}

		$url = wp_get_attachment_image_url( $attachment_id, array( 144, 144 ) );
		if ( ! $url ) {
			return $args;
		}

		$args['url']          = $url;
		$args['found_avatar'] = true;

		return $args;
	}
}

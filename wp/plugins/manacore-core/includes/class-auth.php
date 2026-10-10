<?php
/**
 * ورود و ثبت‌نام با مودال تب‌دار (الگوی مرجع `cinora/`).
 *
 * دو مسیر REST زیر `manacore/v1`:
 *   • `POST auth/login`    — ورود با ایمیل (یا نام کاربری) و رمز.
 *   • `POST auth/register` — ساخت حساب؛ فقط وقتی «اجازه‌ی عضویت» وردپرس روشن است.
 *
 * امنیت:
 *   • نانس `wp_rest` در سرصفحه‌ی `X-WP-Nonce` بررسی می‌شود (کاربر واردنشده
 *     از کوکی احراز نمی‌شود، پس خودمان بررسی می‌کنیم).
 *   • محدودیت نرخ به‌ازای IP: ورود پس از ۸ تلاش ناموفق در ۱۵ دقیقه، و ثبت‌نام
 *     پس از ۵ درخواست در یک ساعت.
 *   • پیام خطای ورود عمداً یکسان است تا وجود یک حساب فاش نشود.
 *   • نشانی بازگشت فقط به میزبان همین سایت (`wp_validate_redirect`).
 *   • ثبت‌نام عمومی همیشه نقش «مشترک» می‌گیرد، نه نقش پیش‌فرض سایت.
 *
 * @package ManaCore\\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Auth
 */
class Auth {

	use Singleton;

	/**
	 * بیشینه‌ی تلاش ناموفق ورود در هر پنجره.
	 */
	const LOGIN_MAX = 8;

	/**
	 * پنجره‌ی شمارش تلاش ناموفق ورود (ثانیه).
	 */
	const LOGIN_WINDOW = 900;

	/**
	 * بیشینه‌ی درخواست ثبت‌نام در هر ساعت.
	 */
	const REGISTER_MAX = 5;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_footer', array( $this, 'modal' ) );
	}

	/**
	 * ثبت مسیرهای ورود و ثبت‌نام.
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Api::NS,
			'/auth/login',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'login' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
					'redirect' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			Rest_Api::NS,
			'/auth/register',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'name'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
					'redirect' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	/**
	 * بررسی نانس برای درخواست‌های کاربر واردنشده.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return true|\WP_Error
	 */
	public function verify_nonce( $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'manacore_bad_nonce',
				__( 'نشست این صفحه منقضی شده است. صفحه را تازه کنید و دوباره تلاش کنید.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * ورود.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function login( $request ) {
		if ( is_user_logged_in() ) {
			return new \WP_Error( 'manacore_logged_in', __( 'شما از قبل وارد شده‌اید.', 'manacore' ), array( 'status' => 400 ) );
		}

		$ip = self::client_ip();

		if ( self::is_limited( 'login', $ip, self::LOGIN_MAX ) ) {
			return self::too_many_attempts();
		}

		$login = trim( (string) $request['email'] );
		$pass  = (string) $request['password'];

		if ( '' === $login || '' === $pass ) {
			return new \WP_Error( 'manacore_missing', __( 'ایمیل و رمز عبور را وارد کنید.', 'manacore' ), array( 'status' => 400 ) );
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::bump( 'login', $ip, self::LOGIN_WINDOW );

			return new \WP_Error(
				'manacore_login_failed',
				__( 'ایمیل یا رمز عبور درست نیست.', 'manacore' ),
				array( 'status' => 401 )
			);
		}

		self::forget( 'login', $ip );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'redirect' => self::redirect_target( (string) $request['redirect'] ),
			)
		);
	}

	/**
	 * ثبت‌نام و ورود خودکار.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register( $request ) {
		if ( is_user_logged_in() ) {
			return new \WP_Error( 'manacore_logged_in', __( 'شما از قبل وارد شده‌اید.', 'manacore' ), array( 'status' => 400 ) );
		}

		if ( ! get_option( 'users_can_register' ) ) {
			return new \WP_Error(
				'manacore_registration_closed',
				__( 'ثبت‌نام در این سایت فعال نیست.', 'manacore' ),
				array( 'status' => 403 )
			);
		}

		$ip = self::client_ip();

		if ( self::is_limited( 'register', $ip, self::REGISTER_MAX ) ) {
			return self::too_many_attempts();
		}

		self::bump( 'register', $ip, HOUR_IN_SECONDS );

		$name  = trim( (string) $request['name'] );
		$email = (string) $request['email'];
		$pass  = (string) $request['password'];

		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 50 ) {
			return new \WP_Error( 'manacore_name', __( 'نام باید بین ۲ تا ۵۰ نویسه باشد.', 'manacore' ), array( 'status' => 400 ) );
		}

		if ( ! is_email( $email ) || strlen( $email ) > 200 ) {
			return new \WP_Error( 'manacore_email', __( 'نشانی ایمیل معتبر نیست.', 'manacore' ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $pass ) < 8 || mb_strlen( $pass ) > 128 ) {
			return new \WP_Error( 'manacore_password', __( 'رمز عبور باید بین ۸ تا ۱۲۸ نویسه باشد.', 'manacore' ), array( 'status' => 400 ) );
		}

		/*
		 * پیام یکسان برای «ایمیل تکراری» و خطاهای دیگر: وجود حساب را فاش
		 * نمی‌کند، ولی راهنمای بعدی را هم می‌دهد.
		 */
		$generic = __( 'ثبت‌نام انجام نشد. اگر حساب دارید، وارد شوید؛ وگرنه ایمیل دیگری امتحان کنید.', 'manacore' );

		if ( email_exists( $email ) ) {
			return new \WP_Error( 'manacore_register_failed', $generic, array( 'status' => 409 ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_login( $email ),
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'nickname'     => $name,
				'first_name'   => $name,
				'role'         => 'subscriber', // عضویت عمومی هرگز نقش بالاتر نمی‌گیرد.
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'manacore_register_failed', $generic, array( 'status' => 400 ) );
		}

		wp_set_current_user( (int) $user_id );
		wp_set_auth_cookie( (int) $user_id, true, is_ssl() );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'redirect' => self::redirect_target( (string) $request['redirect'] ),
			)
		);
	}

	/**
	 * مودال ورود/ثبت‌نام تب‌دار (ساختار و متن مرجع `cinora/`؛ فقط برای کاربر واردنشده).
	 */
	public function modal() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		$can_register = (bool) get_option( 'users_can_register' );
		$privacy      = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
		$svg_attrs    = 'viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
		?>
		<div class="manacore-auth" data-manacore-auth data-can-register="<?php echo $can_register ? '1' : '0'; ?>" hidden>
			<div class="manacore-auth__backdrop" data-auth-close></div>
			<div class="manacore-auth__panel" role="dialog" aria-modal="true" aria-labelledby="manacore-auth-title" tabindex="-1">
				<button type="button" class="manacore-auth__close" data-auth-close aria-label="<?php esc_attr_e( 'بستن', 'manacore' ); ?>">
					<svg <?php echo $svg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- مقدار ثابت. ?>><path d="m18 6-12 12M6 6l12 12"/></svg>
				</button>

				<div class="manacore-auth__logo" aria-hidden="true">
					<svg <?php echo $svg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- مقدار ثابت. ?>><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18M17 3v18M3 7h4M3 12h18M3 17h4M17 7h4M17 17h4"/></svg>
				</div>
				<p class="manacore-auth__eyebrow"><?php esc_html_e( 'یک حساب، هزاران داستان', 'manacore' ); ?></p>
				<h2 id="manacore-auth-title" class="manacore-auth__title" data-auth-title><?php esc_html_e( 'داستانت از اینجا شروع می‌شود.', 'manacore' ); ?></h2>
				<p class="manacore-auth__copy" data-auth-copy><?php esc_html_e( 'برای ساختن لیست تماشا و دریافت پیشنهادهای شخصی عضو شو.', 'manacore' ); ?></p>

				<div class="manacore-auth__tabs" role="tablist" aria-label="<?php esc_attr_e( 'ورود یا عضویت', 'manacore' ); ?>">
					<button type="button" role="tab" id="manacore-auth-tab-login" aria-selected="false" aria-controls="manacore-auth-form" data-auth-mode="login"><?php esc_html_e( 'ورود', 'manacore' ); ?></button>
					<button type="button" role="tab" id="manacore-auth-tab-register" aria-selected="true" aria-controls="manacore-auth-form" data-auth-mode="register"<?php echo $can_register ? '' : ' hidden'; ?>><?php esc_html_e( 'عضویت', 'manacore' ); ?></button>
				</div>

				<form class="manacore-auth__form" id="manacore-auth-form" data-auth-form role="tabpanel" aria-labelledby="manacore-auth-tab-register" novalidate>
					<p class="manacore-auth__error" role="alert" data-auth-error hidden></p>

					<label class="manacore-auth__field" data-auth-name-field>
						<span><?php esc_html_e( 'نام شما', 'manacore' ); ?></span>
						<input type="text" name="name" autocomplete="name" minlength="2" maxlength="50" placeholder="<?php esc_attr_e( 'با چه نامی صدایت کنیم؟', 'manacore' ); ?>" />
					</label>

					<label class="manacore-auth__field">
						<span><?php esc_html_e( 'ایمیل', 'manacore' ); ?></span>
						<input type="email" name="email" dir="ltr" autocomplete="username" required maxlength="200" placeholder="you@example.com" />
					</label>

					<label class="manacore-auth__field">
						<span><?php esc_html_e( 'رمز عبور', 'manacore' ); ?></span>
						<span class="manacore-auth__password">
							<input type="password" name="password" dir="ltr" autocomplete="new-password" required minlength="8" maxlength="128" placeholder="<?php esc_attr_e( 'حداقل ۸ نویسه', 'manacore' ); ?>" />
							<button type="button" class="manacore-auth__toggle" data-auth-password-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'نمایش رمز', 'manacore' ); ?>">
								<svg <?php echo $svg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- مقدار ثابت. ?>><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
							</button>
						</span>
					</label>

					<button type="submit" class="manacore-auth__submit" data-auth-submit>
						<span data-auth-submit-label><?php esc_html_e( 'ساخت حساب کاربری', 'manacore' ); ?></span>
						<svg <?php echo $svg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- مقدار ثابت. ?>><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg>
					</button>
				</form>

				<p class="manacore-auth__note">
					<svg <?php echo $svg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- مقدار ثابت. ?>><path d="m5 12 4 4L19 6"/></svg>
					<?php esc_html_e( 'اطلاعات شما امن است؛ داستان‌هایت هم شخصی.', 'manacore' ); ?>
				</p>

				<?php if ( '' !== $privacy ) : ?>
					<p class="manacore-auth__fine">
						<?php
						printf(
							/* translators: %s: نشانی صفحه‌ی قوانین */
							wp_kses(
								__( 'با ورود یا عضویت، <a href="%s">قوانین استفاده</a> را می‌پذیرید.', 'manacore' ),
								array( 'a' => array( 'href' => array() ) )
							),
							esc_url( $privacy )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * نشانی بازگشت پس از ورود: فقط به همین میزبان.
	 *
	 * @param string $raw نشانی پیشنهادی.
	 * @return string
	 */
	protected static function redirect_target( $raw ) {
		$raw = trim( (string) $raw );

		return '' === $raw ? home_url( '/' ) : wp_validate_redirect( $raw, home_url( '/' ) );
	}

	/**
	 * نام کاربری یکتا از بخش پیش از @ ایمیل.
	 *
	 * @param string $email ایمیل.
	 * @return string
	 */
	protected static function unique_login( $email ) {
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'user';
		$login = $base;

		for ( $i = 0; $i < 5 && username_exists( $login ); $i++ ) {
			$login = $base . wp_rand( 100, 99999 );
		}

		return $login;
	}

	/**
	 * نشانی IP درخواست (بدون اعتماد به هدرهای قابل‌جعل).
	 *
	 * @return string
	 */
	protected static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * کلید شمارنده‌ی یک کنش و IP.
	 *
	 * @param string $action کنش.
	 * @param string $ip     نشانی IP.
	 * @return string
	 */
	protected static function key( $action, $ip ) {
		return 'manacore_auth_' . $action . '_' . md5( $ip );
	}

	/**
	 * آیا شمارنده به سقف رسیده است؟
	 *
	 * @param string $action کنش.
	 * @param string $ip     نشانی IP.
	 * @param int    $max    سقف.
	 * @return bool
	 */
	protected static function is_limited( $action, $ip, $max ) {
		return (int) get_transient( self::key( $action, $ip ) ) >= $max;
	}

	/**
	 * افزایش شمارنده‌ی یک کنش.
	 *
	 * @param string $action کنش.
	 * @param string $ip     نشانی IP.
	 * @param int    $window مدت اعتبار (ثانیه).
	 */
	protected static function bump( $action, $ip, $window ) {
		$key = self::key( $action, $ip );
		set_transient( $key, (int) get_transient( $key ) + 1, $window );
	}

	/**
	 * پاک‌کردن شمارنده پس از موفقیت.
	 *
	 * @param string $action کنش.
	 * @param string $ip     نشانی IP.
	 */
	protected static function forget( $action, $ip ) {
		delete_transient( self::key( $action, $ip ) );
	}

	/**
	 * پاسخ استاندارد «تلاش زیاد».
	 *
	 * @return \WP_Error
	 */
	protected static function too_many_attempts() {
		return new \WP_Error(
			'manacore_rate_limited',
			__( 'تلاش‌های زیادی انجام شده است. کمی بعد دوباره امتحان کنید.', 'manacore' ),
			array( 'status' => 429 )
		);
	}
}

<?php
/**
 * پشتیبان‌گیری و بازگردانی تنظیمات (JSON).
 *
 * چرا لازم است: تحویل حرفه‌ای یک سایت فیلم یعنی بتوان همه‌ی تنظیمات را از
 * محیط آزمایش به سایت اصلی برد. پیش‌تر مدیر باید ده‌ها فیلد را دستی دوباره
 * وارد می‌کرد و هر جا یک کلید جا می‌ماند، سایت نیم‌بند می‌شد.
 *
 * سه قاعده‌ی ایمنی در این کلاس:
 *   ۱. **هیچ کلید ناشناختی ذخیره نمی‌شود.** ورودی از همان
 *      `Settings::sanitize()` هر تب می‌گذرد و نامِ کلیدها از
 *      `Settings::keys_by_tab()` می‌آید.
 *   ۲. **فایل ناقص، تنظیمات دیگر را صفر نمی‌کند.** مقادیر تب با مقدار
 *      ذخیره‌شده‌ی همان تب ادغام می‌شوند، پس نبودِ یک کلید در فایل یعنی
 *      «دست نزن»، نه «خالی کن».
 *   ۳. **شناسه‌ی برگه‌ها منتقل نمی‌شود.** فایل، نامک (slug) برگه‌های پخش و
 *      درخواست‌ها را می‌برد و هنگام بازگردانی نامک به شناسه‌ی همان سایت
 *      تبدیل می‌شود؛ شناسه‌ی سایت مبدأ روی سایت مقصد بی‌معناست.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Portability
 */
class Portability {

	use Singleton;

	/**
	 * نام قالب فایل پشتیبان.
	 */
	const FORMAT = 'manacore-settings';

	/**
	 * نسخه‌ی قالب فایل.
	 */
	const FORMAT_VERSION = 1;

	/**
	 * گزینه‌ی نگه‌دارنده‌ی تنظیمات.
	 */
	const OPTION = 'manacore_settings';

	/**
	 * گزینه‌ی برگه‌ی پخش.
	 */
	const WATCH_OPTION = 'manacore_watch_page';

	/**
	 * گزینه‌ی برگه‌ی درخواست‌ها.
	 */
	const REQUEST_OPTION = 'manacore_request_page';

	/**
	 * سقف حجم ورودی (۲۵۶ کیلوبایت) — فایل تنظیمات هرگز این‌قدر نمی‌شود.
	 */
	const MAX_BYTES = 262144;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'admin_post_manacore_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_manacore_import', array( $this, 'handle_import' ) );
	}

	/**
	 * برگه‌هایی که با نامک منتقل می‌شوند (گزینه => برچسب).
	 *
	 * @return array<string,string>
	 */
	public static function page_options() {
		return array(
			self::WATCH_OPTION   => __( 'برگه‌ی پخش', 'manacore' ),
			self::REQUEST_OPTION => __( 'برگه‌ی درخواست‌ها', 'manacore' ),
		);
	}

	/**
	 * خلاصه‌ی آنچه پشتیبان‌گیری می‌شود (برای کارت پنل).
	 *
	 * @return array<string,int>
	 */
	public static function summary() {
		$settings = get_option( self::OPTION, array() );
		$keys     = is_array( $settings ) ? count( $settings ) : 0;

		return array(
			'keys' => $keys,
			'have' => $keys ? 1 : 0,
		);
	}

	/**
	 * ساخت بسته‌ی پشتیبان.
	 *
	 * @return array<string,mixed>
	 */
	public static function export() {
		$settings = get_option( self::OPTION, array() );
		$pages    = array();

		foreach ( array_keys( self::page_options() ) as $option ) {
			$id = (int) get_option( $option, 0 );

			if ( $id ) {
				$slug = (string) get_post_field( 'post_name', $id );

				if ( '' !== $slug ) {
					$pages[ $option ] = $slug;
				}
			}
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::FORMAT_VERSION,
			'plugin'   => defined( 'MANACORE_VERSION' ) ? MANACORE_VERSION : '',
			'site'     => home_url( '/' ),
			'exported' => gmdate( 'c' ),
			'settings' => is_array( $settings ) ? $settings : array(),
			'pages'    => $pages,
		);
	}

	/**
	 * بسته‌ی پشتیبان به‌صورت متن JSON.
	 *
	 * @return string
	 */
	public static function to_json() {
		return (string) wp_json_encode(
			self::export(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/**
	 * نام فایل پیشنهادی برای بارگیری.
	 *
	 * @return string
	 */
	public static function file_name() {
		return 'manacore-settings-' . gmdate( 'Y-m-d' ) . '.json';
	}

	/**
	 * بازگردانی تنظیمات از متن JSON (یا آرایه‌ی آماده).
	 *
	 * @param string|array $raw  متن JSON یا آرایه‌ی بسته.
	 * @param array        $args `dry_run` برای بررسی بدون ذخیره.
	 * @return array<string,mixed> نتیجه: ok، applied، skipped، tabs، errors.
	 */
	public static function import( $raw, $args = array() ) {
		$args   = wp_parse_args( $args, array( 'dry_run' => false ) );
		$result = array(
			'ok'      => false,
			'dry_run' => (bool) $args['dry_run'],
			'applied' => 0,
			'skipped' => array(),
			'tabs'    => array(),
			'pages'   => array(),
			'errors'  => array(),
		);

		/* ---------------- ۱) خواندن ورودی ---------------- */

		if ( is_array( $raw ) ) {
			$data = $raw;
		} elseif ( is_string( $raw ) ) {
			$raw = trim( $raw );

			if ( '' === $raw ) {
				$result['errors'][] = 'empty';
				return $result;
			}

			if ( strlen( $raw ) > self::MAX_BYTES ) {
				$result['errors'][] = 'size';
				return $result;
			}

			$data = json_decode( $raw, true );

			if ( ! is_array( $data ) ) {
				$result['errors'][] = 'json';
				return $result;
			}
		} else {
			$result['errors'][] = 'empty';
			return $result;
		}

		/* ---------------- ۲) اعتبارسنجی قالب ---------------- */

		if ( isset( $data['format'] ) && self::FORMAT !== $data['format'] ) {
			$result['errors'][] = 'format';
			return $result;
		}

		if ( isset( $data['version'] ) && (int) $data['version'] > self::FORMAT_VERSION ) {
			$result['errors'][] = 'version';
			return $result;
		}

		/*
		 * دو حالت پذیرفته می‌شود: بسته‌ی کامل افزونه (`settings`) و فایل
		 * دست‌ساز که فقط نقشه‌ی کلید/مقدار است. حالت دوم برای پشتیبانی
		 * و «فقط یک تب را عوض کن» بسیار پرکاربرد است.
		 */
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings = $data['settings'];
		} elseif ( ! isset( $data['format'] ) ) {
			$settings = $data;
		} else {
			$settings = array();
		}

		/* ---------------- ۳) دسته‌بندی کلیدها ---------------- */

		$known = Settings::option_keys();

		foreach ( $settings as $key => $value ) {
			if ( ! in_array( (string) $key, $known, true ) ) {
				$result['skipped'][] = (string) $key;
			}
		}

		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();

		foreach ( Settings::keys_by_tab() as $tab => $keys ) {
			$picked = array_intersect_key( $settings, array_flip( $keys ) );

			if ( ! $picked ) {
				continue;
			}

			$result['tabs'][ $tab ] = count( $picked );
			$result['applied']     += count( $picked );

			if ( $result['dry_run'] ) {
				continue;
			}

			/*
			 * مقادیر فعلی همان تب زیر مقادیر فایل می‌نشینند: کلیدهای غایب
			 * فایل، مقدار ذخیره‌شده‌ی خودشان را نگه می‌دارند و پاک‌سازی
			 * تب‌به‌تب هم دست‌نخورده می‌ماند.
			 */
			$seed  = array_intersect_key( $current, array_flip( $keys ) );
			$input = array_merge( $seed, $picked );
			$input['_tab'] = $tab;

			$clean = Settings::instance()->sanitize( $input );
			$clean = is_array( $clean ) ? $clean : array();

			update_option( self::OPTION, $clean );

			/* پاک‌سازی هم‌زمان با ذخیره‌ی هر تب، مبنای تب بعدی می‌شود. */
			$current = $clean;
		}

		/* ---------------- ۴) برگه‌ها بر پایه‌ی نامک ---------------- */

		$pages = isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : array();

		foreach ( array_keys( self::page_options() ) as $option ) {
			$slug = isset( $pages[ $option ] ) ? sanitize_title( (string) $pages[ $option ] ) : '';

			if ( '' === $slug ) {
				continue;
			}

			$page = get_page_by_path( $slug, OBJECT, 'page' );

			if ( ! $page instanceof \WP_Post ) {
				continue;
			}

			$result['pages'][ $option ] = (int) $page->ID;

			if ( ! $result['dry_run'] ) {
				update_option( $option, (int) $page->ID );
			}
		}

		$result['ok'] = empty( $result['errors'] );

		return $result;
	}

	/**
	 * کد پیام مدیریتی بر پایه‌ی نتیجه‌ی بازگردانی.
	 *
	 * @param array $result نتیجه‌ی `import()`.
	 * @return array{0:string,1:string} کد پیام و دلیل.
	 */
	public static function notice_for( $result ) {
		if ( ! empty( $result['errors'] ) ) {
			return array( 'import-error', (string) $result['errors'][0] );
		}

		if ( ! $result['applied'] && ! $result['pages'] ) {
			return array( 'failed', '' );
		}

		if ( ! empty( $result['dry_run'] ) ) {
			return array( 'import-dry', '' );
		}

		return array( 'import-ok', '' );
	}

	/**
	 * بارگیری فایل پشتیبان (admin-post).
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		check_admin_referer( 'manacore_export' );

		$json = self::to_json();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::file_name() . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		// خروجی همان متن JSON است که خودمان ساخته‌ایم؛ جای escape نیست.
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * بازگردانی تنظیمات از فرم پنل (admin-post).
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		check_admin_referer( 'manacore_import' );

		$raw = '';

		if ( ! empty( $_FILES['manacore_import_file']['tmp_name'] ) ) {
			$file = $_FILES['manacore_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- فایل JSON است و در ادامه اعتبارسنجی می‌شود.

			if ( UPLOAD_ERR_OK === (int) $file['error'] && is_uploaded_file( $file['tmp_name'] ) ) {
				if ( (int) $file['size'] > self::MAX_BYTES ) {
					self::redirect( 'import-error', 'size' );
				}

				/*
				 * فایل در پوشه‌ی بارگذاری کپی نمی‌شود (`wp_handle_upload`
				 * لازم نیست): فقط متن JSON را می‌خوانیم و دور می‌ریزیم.
				 */
				$raw = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		if ( '' === trim( $raw ) && isset( $_POST['manacore_import_json'] ) ) {
			$raw = wp_unslash( (string) $_POST['manacore_import_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- متن JSON با json_decode و پاک‌سازی هر تب اعتبارسنجی می‌شود.
		}

		$result = self::import(
			$raw,
			array( 'dry_run' => ! empty( $_POST['manacore_import_dry'] ) )
		);

		list( $notice, $reason ) = self::notice_for( $result );

		self::redirect( $notice, $reason, $result );
	}

	/**
	 * بازگشت به تب ابزارها همراه پیام.
	 *
	 * @param string $notice کد پیام.
	 * @param string $reason دلیل خطا.
	 * @param array  $result نتیجه (برای شمارش‌ها).
	 * @return void
	 */
	protected static function redirect( $notice, $reason = '', $result = array() ) {
		$args = array(
			'page'            => 'manacore',
			'tab'             => 'tools',
			'manacore_notice' => $notice,
		);

		if ( '' !== $reason ) {
			$args['manacore_reason'] = $reason;
		}

		if ( ! empty( $result['applied'] ) ) {
			$args['manacore_applied'] = (int) $result['applied'];
		}

		if ( ! empty( $result['skipped'] ) ) {
			$args['manacore_skipped'] = count( $result['skipped'] );
		}

		if ( ! empty( $result['dry_run'] ) ) {
			$args['manacore_dry'] = 1;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

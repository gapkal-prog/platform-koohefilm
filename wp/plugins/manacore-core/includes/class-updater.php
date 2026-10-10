<?php
/**
 * به‌روزرسانی افزونه از سرور فروش.
 *
 * چرا لازم است؟ نسخه‌ای که خریده می‌شود باید بتواند خودش را به‌روز کند؛
 * وگرنه هر اصلاح امنیتی یا سازگاری، به «دوباره فایل بفرست» تبدیل می‌شود و
 * سایت‌ها روی نسخه‌های قدیمی جا می‌مانند. این کلاس یک سازوکار سبک و
 * استاندارد است: یک نشانی JSON (نقطه‌ی پایانی) که نسخه‌ی تازه و فایل بسته
 * را معرفی می‌کند، و همان چیزی که وردپرس خودش می‌فهمد.
 *
 * سه تصمیم مهم:
 * - **بی‌اثر اگر تعریف نشده باشد.** تا وقتی `MANACORE_UPDATE_ENDPOINT` یا
 *   فیلتر `manacore_update_endpoint` تعریف نشود، هیچ درخواستی به بیرون
 *   زده نمی‌شود؛ پس نصب روی سایت داخلی/آفلاین هم بی‌عارض است.
 * - **فقط HTTPS و اعتبارسنجی پاسخ.** نشانی ناامن رد می‌شود و پاسخ دوردست
 *   پیش از استفاده به فیلدهای شناخته‌شده پاک می‌شود؛ محتوای ناشناخته
 *   هرگز داخل transient وردپرس نمی‌رود.
 * - **کش ۶ ساعته.** بررسی به‌روزرسانی روی هر بار بازکردن پیشخوان، سرور
 *   فروش را زیر بار می‌برد؛ ۶ ساعت تعادل معقولی است و با دکمه‌ی «بررسی
 *   به‌روزرسانی» در تب ابزارها یا فیلتر می‌توان فوری‌اش کرد.
 *
 * قالبی که نقطه‌ی پایانی باید برگرداند (JSON):
 *
 *   {
 *     "version": "1.2.0",
 *     "download_url": "https://shop.example.com/files/manacore-1.2.0.zip",
 *     "details_url": "https://shop.example.com/manacore/changelog",
 *     "requires": "6.4",
 *     "tested": "6.8",
 *     "requires_php": "7.4",
 *     "last_updated": "2026-09-01 10:00:00",
 *     "changelog": "<p>…</p>"
 *   }
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Updater
 */
class Updater {

	use Singleton;

	/**
	 * کلید کش اطلاعات نسخه.
	 */
	const CACHE_KEY = 'manacore_update_info';

	/**
	 * عمر کش پاسخ موفق (۶ ساعت).
	 */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * عمر کش پاسخ ناموفق (۱۵ دقیقه) تا سرور خاموش هم پشت‌سرهم صدا نشود.
	 */
	const FAIL_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
	}

	/**
	 * شکل تهی اطلاعات نسخه.
	 *
	 * همه‌ی خواننده‌ها (تزریق در فهرست افزونه‌ها، پنجره‌ی جزئیات، کارت پنل)
	 * روی کلیدهای همین شکل حساب می‌کنند؛ پس نبودِ پاسخ هم باید همین شکل را
	 * بدهد، نه آرایه‌ی خالی — وگرنه هشدار «کلید تعریف‌نشده» می‌گیریم.
	 *
	 * @return array<string,string>
	 */
	public static function blank() {
		return array(
			'version'      => '',
			'download_url' => '',
			'details_url'  => '',
			'requires'     => '',
			'tested'       => '',
			'requires_php' => '',
			'last_updated' => '',
			'changelog'    => '',
		);
	}

	/**
	 * نقطه‌ی پایانی به‌روزرسانی.
	 *
	 * پیش‌فرض خالی است (بی‌اثر). فروشنده/خریدار می‌تواند آن را با ثابت
	 * `MANACORE_UPDATE_ENDPOINT` در `wp-config.php` یا با فیلتر تعیین کند.
	 *
	 * @return string
	 */
	public static function endpoint() {
		$endpoint = defined( 'MANACORE_UPDATE_ENDPOINT' ) ? (string) MANACORE_UPDATE_ENDPOINT : '';

		/**
		 * فیلتر نشانی نقطه‌ی پایانی به‌روزرسانی.
		 *
		 * @param string $endpoint نشانی JSON.
		 */
		$endpoint = (string) apply_filters( 'manacore_update_endpoint', $endpoint );

		/* فقط HTTPS و نشانی معتبر؛ پاسخ ناامن هرگز خوانده نمی‌شود. */
		if ( '' === $endpoint || ! wp_http_validate_url( $endpoint ) || 0 !== strpos( $endpoint, 'https://' ) ) {
			return '';
		}

		return $endpoint;
	}

	/**
	 * اطلاعات نسخه‌ی تازه (با کش).
	 *
	 * @param bool $force نادیده‌گرفتن کش.
	 * @return array<string,mixed>
	 */
	public static function remote( $force = false ) {
		$endpoint = self::endpoint();

		if ( '' === $endpoint ) {
			return self::blank();
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( ! $force && is_array( $cached ) && array() !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'    => 10,
				'user-agent' => 'ManaCore/' . MANACORE_VERSION . '; ' . home_url( '/' ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, self::blank(), self::FAIL_TTL );

			return self::blank();
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			set_transient( self::CACHE_KEY, self::blank(), self::FAIL_TTL );

			return self::blank();
		}

		$info = self::sanitize( $data );

		set_transient( self::CACHE_KEY, $info, '' === $info['version'] ? self::FAIL_TTL : self::CACHE_TTL );

		return $info;
	}

	/**
	 * پاک‌سازی پاسخ دوردست به فیلدهای شناخته‌شده.
	 *
	 * هر کلید ناشناخته دور ریخته می‌شود؛ فایل بسته هم باید HTTPS باشد،
	 * وگرنه نسخه‌ی معرفی‌شده بی‌اثر می‌ماند.
	 *
	 * @param array $data پاسخ خام.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $data ) {
		$info = self::blank();

		if ( isset( $data['version'] ) ) {
			/*
			 * نسخه باید دقیقاً الگوی نسخه باشد (`1.2.3` یا `1.2.3-beta.1`).
			 * نه فقط نویسه‌های مجاز را نگه می‌داریم، بلکه شکل را هم می‌سنجیم؛
			 * وگرنه «1.2.0» آغشته به متن اضافه هم معتبر شمرده می‌شد.
			 */
			$version = trim( (string) $data['version'] );

			if ( preg_match( '/^\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.\-]+)?$/', $version ) ) {
				$info['version'] = $version;
			}
		}

		foreach ( array( 'download_url', 'details_url' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$url = esc_url_raw( (string) $data[ $key ] );

				$info[ $key ] = ( 'download_url' === $key && 0 !== strpos( $url, 'https://' ) ) ? '' : $url;
			}
		}

		foreach ( array( 'requires', 'tested', 'requires_php', 'last_updated' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				$info[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			}
		}

		if ( ! empty( $data['changelog'] ) ) {
			$info['changelog'] = wp_kses_post( (string) $data['changelog'] );
		}

		/* نسخه‌ی بی‌فایل بی‌فایده است؛ هر دو باید باشند. */
		if ( '' === $info['version'] || '' === $info['download_url'] ) {
			$info['version'] = '';
		}

		return $info;
	}

	/**
	 * وضعیت خوانا برای پنل مدیریت.
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$info = self::remote();

		return array(
			'endpoint'  => '' !== self::endpoint(),
			'current'   => (string) MANACORE_VERSION,
			'remote'    => (string) $info['version'],
			'available' => '' !== $info['version'] && version_compare( (string) $info['version'], (string) MANACORE_VERSION, '>' ),
			'url'       => (string) $info['details_url'],
		);
	}

	/**
	 * افزودن به‌روزرسانی به فهرست افزونه‌های پیشخوان.
	 *
	 * @param mixed $transient مقدار transient به‌روزرسانی افزونه‌ها.
	 * @return mixed
	 */
	public function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info = self::remote();

		if ( '' === $info['version'] || ! version_compare( $info['version'], (string) MANACORE_VERSION, '>' ) ) {
			return $transient;
		}

		$plugin = plugin_basename( MANACORE_FILE );

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		/*
		 * افزونه‌ای که وردپرس «بی‌نیاز به به‌روزرسانی» می‌داندش، تا وقتی از
		 * فهرست `no_update` برداشته نشود دکمه‌ی به‌روزرسانی نمی‌گیرد.
		 */
		if ( isset( $transient->no_update[ $plugin ] ) ) {
			unset( $transient->no_update[ $plugin ] );
		}

		$transient->response[ $plugin ] = (object) array(
			'slug'         => 'manacore-core',
			'plugin'       => $plugin,
			'new_version'  => $info['version'],
			'url'          => $info['details_url'],
			'package'      => $info['download_url'],
			'requires'     => $info['requires'],
			'tested'       => $info['tested'],
			'requires_php' => $info['requires_php'],
			'icons'        => array(),
			'banners'      => array(),
		);

		return $transient;
	}

	/**
	 * پنجره‌ی «جزئیات نسخه» در فهرست افزونه‌ها.
	 *
	 * @param mixed  $result نتیجه‌ی پیش‌فرض.
	 * @param string $action کنش درخواستی.
	 * @param object $args   پارامترهای درخواست.
	 * @return mixed
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! is_object( $args ) || ! isset( $args->slug ) || 'manacore-core' !== $args->slug ) {
			return $result;
		}

		$info = self::remote();

		if ( '' === $info['version'] ) {
			return $result;
		}

		return (object) array(
			'name'          => 'ManaCore',
			'slug'          => 'manacore-core',
			'version'       => $info['version'],
			'author'        => 'ManaCore',
			'homepage'      => $info['details_url'],
			'download_link' => $info['download_url'],
			'requires'      => $info['requires'],
			'tested'        => $info['tested'],
			'requires_php'  => $info['requires_php'],
			'last_updated'  => $info['last_updated'],
			'sections'      => array(
				'changelog' => '' !== $info['changelog'] ? $info['changelog'] : '<p>' . esc_html__( 'فهرست تغییرها در دسترس نیست.', 'manacore' ) . '</p>',
			),
		);
	}

	/**
	 * پس از هر به‌روزرسانی، کش تازه شود.
	 *
	 * @param mixed $upgrader نمونه‌ی به‌روزرسان.
	 * @param array $options  پارامترهای اجرا.
	 * @return void
	 */
	public function after_upgrade( $upgrader, $options ) {
		unset( $upgrader );

		if ( ! is_array( $options ) || empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
			return;
		}

		if ( in_array( plugin_basename( MANACORE_FILE ), $options['plugins'], true ) ) {
			self::flush();
		}
	}

	/**
	 * پاک‌کردن کش اطلاعات نسخه.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}
}

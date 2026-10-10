<?php
/**
 * خواندن مشخصات یک لینک دانلود: کیفیت، زبان/دوبله، انکودر، نام و حجم.
 *
 * دو بخش دارد:
 *  1) تجزیه‌ی متنی (بدون شبکه): کیفیت، زبان و انکودر از نام فایل و برچسب
 *     لینک خوانده می‌شود. این تجزیه در هر درخواستی سبک است و نتیجه‌اش فقط
 *     پیشنهاد است؛ مدیر همیشه می‌تواند آن را عوض کند.
 *  2) سنجش حجم از سرور میزبان: فقط در ویرایشگر و فقط با مجوز `edit_posts`،
 *     با `wp_safe_remote_head` (و در نبود Content-Length، یک GET بسیار سبک با
 *     Range). هیچ‌گاه هنگام رندر فرانت یا ذخیره‌ی پست اجرا نمی‌شود.
 *
 * @package ManaCore\\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Link_Meta
 */
class Link_Meta {

	use Singleton;

	/**
	 * مسیر REST (زیر `manacore/v1`).
	 */
	const ROUTE = '/links/inspect';

	/**
	 * سقف درخواست‌های سنجش هر کاربر در هر پنجره.
	 */
	const RATE_MAX = 30;

	/**
	 * طول پنجره‌ی محدودیت نرخ (ثانیه).
	 */
	const RATE_WINDOW = 600;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * ثبت مسیر سنجش (فقط برای نویسنده‌ها).
	 */
	public function register_routes() {
		register_rest_route(
			Rest_Api::NS,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'inspect_route' ),
				'permission_callback' => array( __CLASS__, 'can_inspect' ),
				'args'                => array(
					'url'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && '' !== trim( $value ) && strlen( $value ) <= 2000;
						},
					),
					'label' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'probe' => array(
						'type'              => 'boolean',
						'default'           => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	/**
	 * دسترسی: ویرایش نوشته (همان سطحی که متاباکس لینک را می‌بیند).
	 *
	 * @return bool
	 */
	public static function can_inspect() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * پاسخ مسیر سنجش.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function inspect_route( $request ) {
		$url = (string) $request['url'];

		if ( ! self::is_http_url( $url ) ) {
			return new \WP_Error(
				'manacore_invalid_url',
				__( 'نشانی لینک باید با http یا https شروع شود.', 'manacore' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * تجزیه‌ی متنی (probe=0) بدون شبکه است و محدودیت نرخ ندارد؛ فقط
		 * سنجش شبکه‌ای (حجم) درخواست خروجی می‌سازد و شمارش می‌شود.
		 */
		$do_probe = (bool) $request['probe'];

		if ( $do_probe && ! self::within_rate_limit( get_current_user_id() ) ) {
			return new \WP_Error(
				'manacore_rate_limited',
				__( 'درخواست‌های سنجش زیاد شد؛ چند دقیقه دیگر دوباره امتحان کنید.', 'manacore' ),
				array( 'status' => 429 )
			);
		}

		$parsed = self::parse( $url, (string) $request['label'] );
		$probe  = $do_probe
			? self::probe( $url )
			: array(
				'bytes'    => 0,
				'filename' => '',
				'reached'  => false,
			);

		return rest_ensure_response(
			array(
				'url'      => $url,
				'name'     => $parsed['name'],
				'quality'  => $parsed['quality'],
				'language' => $parsed['language'],
				'encoder'  => $parsed['encoder'],
				'size'     => self::format_size( (int) $probe['bytes'] ),
				'filename' => (string) $probe['filename'],
				'reached'  => (bool) $probe['reached'],
			)
		);
	}

	/**
	 * تجزیه‌ی متنی یک نشانی و برچسب (بدون شبکه).
	 *
	 * @param string $url   نشانی لینک.
	 * @param string $label برچسب لینک (اختیاری).
	 * @return array{name:string,quality:string,language:string,encoder:string}
	 */
	public static function parse( $url, $label = '' ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$file = rawurldecode( basename( $path ) );
		$stem = (string) preg_replace( '/\.[A-Za-z0-9]{2,5}$/', '', $file );
		$text = $stem . ' ' . (string) $label;

		$name = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( array( '.', '_', '+' ), ' ', $stem ) ) );

		return array(
			'name'     => self::clip( $name, 180 ),
			'quality'  => self::detect_quality( $text ),
			'language' => self::detect_language( $text ),
			'encoder'  => self::detect_encoder( $stem, (string) $label ),
		);
	}

	/**
	 * کیفیت: اول وضوح (۱۰۸۰p و…)، بعد برچسب منبع (BluRay، WEB-DL و…).
	 *
	 * مقدار فقط وقتی برمی‌گردد که کلیدی معتبر در فهرست کیفیت‌ها باشد؛ وگرنه
	 * ویرایشگر یک گزینه‌ی ناشناس می‌گرفت.
	 *
	 * @param string $text متن جست‌وجو.
	 * @return string
	 */
	public static function detect_quality( $text ) {
		$known = manacore_qualities();
		$found = '';

		if ( preg_match( '/(?<![0-9])(2160|1440|1080|720|576|480|360)\s*p(?![a-z])/i', $text, $m ) ) {
			$found = $m[1] . 'p';
		} elseif ( preg_match( '/(?<![a-z])4k(?![a-z])/i', $text ) ) {
			$found = '2160p';
		} else {
			$tags = array(
				'BluRay' => '/blu[\s._-]?ray|bdrip|brrip/i',
				'WEBDL'  => '/web[\s._-]?dl/i',
				'WEBRip' => '/web[\s._-]?rip/i',
				'HDR'    => '/\bhdr10?\+?\b/i',
				'DV'     => '/dolby[\s._-]?vision|\bdv\b/i',
			);

			foreach ( $tags as $key => $pattern ) {
				if ( preg_match( $pattern, $text ) ) {
					$found = $key;
					break;
				}
			}
		}

		if ( '' === $found ) {
			return '';
		}

		/* فقط کلیدهای فهرست کیفیت پذیرفته می‌شوند (مثلاً 1440p در فهرست نیست). */
		if ( isset( $known[ $found ] ) ) {
			return $found;
		}

		return '';
	}

	/**
	 * زبان/دوبله از روی واژه‌های رایج (فارسی و لاتین).
	 *
	 * @param string $text متن جست‌وجو.
	 * @return string کلید زبان یا رشته‌ی خالی.
	 */
	public static function detect_language( $text ) {
		$known = manacore_languages();

		if ( preg_match( '/\bdual\b|دو\s?زبانه/iu', $text ) ) {
			$key = 'dual';
		} elseif ( preg_match( '/دوبله|\bdub(?:bed)?\b/iu', $text ) ) {
			$key = 'dub_fa';
		} elseif ( preg_match( '/hard\s?sub|hardsub|زیرنویس\s*چسبیده/iu', $text ) ) {
			$key = 'sub_fa';
		} elseif ( preg_match( '/soft\s?sub|softsub|زیرنویس\s*جدا/iu', $text ) ) {
			$key = 'soft_fa';
		} elseif ( preg_match( '/\bsub(?:bed|s)?\b|زیرنویس/iu', $text ) ) {
			$key = 'sub_fa';
		} elseif ( preg_match( '/بدون\s*سانسور|\buncut\b/iu', $text ) ) {
			$key = 'uncut';
		} elseif ( preg_match( '/سانسور\s*شده|\bcensored\b/iu', $text ) ) {
			$key = 'censored';
		} else {
			return '';
		}

		return isset( $known[ $key ] ) ? $key : '';
	}

	/**
	 * انکودر (گروه انتشار): بخش پس از آخرین خط‌تیره‌ی نام فایل، مثل `-PSA`.
	 *
	 * واژه‌های فنی (WEB-DL، x264، HEVC…) کنار گذاشته می‌شوند تا «DL» یا «x264»
	 * به‌عنوان انکودر ثبت نشود.
	 *
	 * @param string $stem  نام فایل بدون پسوند.
	 * @param string $label برچسب لینک.
	 * @return string
	 */
	public static function detect_encoder( $stem, $label = '' ) {
		static $technical = array(
			'dl', 'web', 'webrip', 'webdl', 'bluray', 'brrip', 'bdrip', 'hdtv', 'hevc', 'avc', 'aac',
			'ac3', 'ddp', 'dts', 'x264', 'x265', 'h264', 'h265', 'av1', 'hdr', 'hdr10', 'dv', '10bit',
			'8bit', 'mkv', 'mp4', 'dual', 'sub', 'subs', 'dub', 'dubbed', 'remux', 'proper', 'repack',
		);

		$stem = trim( $stem );
		$pos  = strrpos( $stem, '-' );

		if ( false !== $pos ) {
			$candidate = trim( substr( $stem, $pos + 1 ) );

			if (
				preg_match( '/^[A-Za-z0-9]{2,15}$/', $candidate )
				&& ! in_array( strtolower( $candidate ), $technical, true )
				&& ! preg_match( '/^\d+$/', $candidate )
			) {
				return $candidate;
			}
		}

		if ( preg_match( '/\[([A-Za-z0-9][A-Za-z0-9 ._-]{1,18})\]/', $label, $m ) ) {
			return trim( $m[1] );
		}

		return '';
	}

	/**
	 * سنجش حجم و نام فایل از میزبان (فقط ویرایشگر).
	 *
	 * نتیجه برای ۶ ساعت (موفق) یا ۱۰ دقیقه (ناموفق) کش می‌شود تا یک نشانی
	 * یک‌بار سنجیده شود و میزبان بی‌جهت بار نگیرد.
	 *
	 * @param string $url نشانی.
	 * @return array{bytes:int,filename:string,reached:bool}
	 */
	public static function probe( $url ) {
		$key    = 'manacore_link_probe_' . md5( $url );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = array(
			'timeout'             => 8,
			'redirection'         => 3,
			'limit_response_size' => 2048,
			'user-agent'          => 'ManaCore/' . ( defined( 'MANACORE_VERSION' ) ? MANACORE_VERSION : '1.0' ) . '; ' . home_url( '/' ),
		);

		$response = wp_safe_remote_head( $url, $args );
		$bytes    = 0;
		$disp     = '';

		if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 400 ) {
			$bytes = (int) self::header( $response, 'content-length' );
			$disp  = self::header( $response, 'content-disposition' );
		}

		if ( $bytes <= 0 ) {
			$args['headers'] = array( 'Range' => 'bytes=0-0' );
			$response        = wp_safe_remote_get( $url, $args );

			if ( ! is_wp_error( $response ) ) {
				$range = self::header( $response, 'content-range' );

				if ( preg_match( '#/(\d+)\s*$#', $range, $m ) ) {
					$bytes = (int) $m[1];
				}
				if ( '' === $disp ) {
					$disp = self::header( $response, 'content-disposition' );
				}
			}
		}

		$result = array(
			'bytes'    => max( 0, $bytes ),
			'filename' => self::filename_from_disposition( $disp ),
			'reached'  => $bytes > 0,
		);

		set_transient( $key, $result, $result['reached'] ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * تبدیل بایت به برچسب کوتاه («1.2GB»، «350MB»).
	 *
	 * @param int $bytes اندازه‌ی فایل.
	 * @return string
	 */
	public static function format_size( $bytes ) {
		$bytes = (int) $bytes;

		if ( $bytes <= 0 ) {
			return '';
		}

		if ( $bytes >= 1073741824 ) {
			return rtrim( rtrim( number_format( $bytes / 1073741824, 2, '.', '' ), '0' ), '.' ) . 'GB';
		}

		return max( 1, (int) round( $bytes / 1048576 ) ) . 'MB';
	}

	/**
	 * نام فایل از Content-Disposition (هم `filename*` و هم `filename`).
	 *
	 * @param string $disposition مقدار هدر.
	 * @return string
	 */
	public static function filename_from_disposition( $disposition ) {
		$disposition = (string) $disposition;

		if ( preg_match( "/filename\\*\\s*=\\s*UTF-8''([^;]+)/i", $disposition, $m ) ) {
			return self::clip( trim( rawurldecode( trim( $m[1], " \"'" ) ) ), 200 );
		}

		if ( preg_match( '/filename\s*=\s*"?([^";]+)"?/i', $disposition, $m ) ) {
			return self::clip( trim( $m[1] ), 200 );
		}

		return '';
	}

	/**
	 * آیا نشانی http(s) با میزبان معتبر است؟
	 *
	 * @param string $url نشانی.
	 * @return bool
	 */
	protected static function is_http_url( $url ) {
		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& ! empty( $parts['host'] )
			&& in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true );
	}

	/**
	 * محدودیت نرخ سنجش برای هر کاربر.
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return bool
	 */
	protected static function within_rate_limit( $user_id ) {
		$key   = 'manacore_inspect_' . (int) $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );

		return true;
	}

	/**
	 * خواندن یک هدر پاسخ (رشته‌ی خام یا آرایه).
	 *
	 * @param array  $response پاسخ HTTP.
	 * @param string $name     نام هدر.
	 * @return string
	 */
	protected static function header( $response, $name ) {
		$value = wp_remote_retrieve_header( $response, $name );

		return is_array( $value ) ? (string) reset( $value ) : (string) $value;
	}

	/**
	 * برش امن رشته بر پایه‌ی UTF-8.
	 *
	 * @param string $text  متن.
	 * @param int    $limit سقف طول.
	 * @return string
	 */
	protected static function clip( $text, $limit ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $limit, 'UTF-8' ) : substr( (string) $text, 0, $limit );
	}
}

<?php
/**
 * ابزارهای گروهی نگه‌داشت لینک‌ها.
 *
 * چرا لازم است؟ لینک‌های یک سایت دانلودی عمر کوتاه دارند و کارهای روزمره‌ی
 * مدیر همیشه «یکی‌یکی» نیست: مهاجرت هاست (تغییر پیشوند همه‌ی نشانی‌ها)،
 * چسباندن گروه لینک یک اثر به چند قسمت، و پیدا کردن لینک‌های ناقص
 * (بی‌کیفیت، بی‌حجم، تکراری یا بدون پسوند فایل). پیش از این، انجام این
 * کارها فقط با بازکردن تک‌تک نوشته‌ها ممکن بود — کاری چندساعته و پرخطا.
 *
 * چهار کار این کلاس:
 *   ۱. `replace_domain()` — جایگزینی پیشوند (دامنه/مسیر) در همه‌ی لینک‌ها،
 *      با پیش‌نمایش: چیزی تا تأیید مدیر نوشته نمی‌شود.
 *   ۲. `copy_links()` — کپی گروه لینک یک اثر روی چند نوشته، با سه حالت
 *      «فقط خالی‌ها»، «افزودن» و «جایگزینی».
 *   ۳. `audit()` — بازرسی ساختاری بدون درخواست شبکه: کیفیت/حجم ناقص،
 *      کیفیت تکراری در یک گروه و نشانی بدون پسوند فایل قابل‌شناسایی.
 *   ۴. `check()` — سنجش دوره‌ای نشانی‌ها (کرون یا دستی) و ثبت لینک‌های
 *      مرده در همان صف «گزارش خرابی لینک» که کاربران هم در آن گزارش
 *      می‌دهند؛ لینکی که دوباره سالم شود، گزارش بازش خودکار بسته می‌شود.
 *
 * همه‌ی نوشتن‌ها از `Links::save()` می‌گذرد تا پاک‌سازی و ساختار متا
 * یک‌جا بمانَد (یک منبع حقیقت با متاباکس و REST).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Link_Tools
 */
class Link_Tools {

	use Singleton;

	/**
	 * کلید ترنزینت نتیجه (به‌ازای هر کاربر).
	 */
	const RESULT_KEY = 'manacore_link_tool_result';

	/**
	 * کد پیام نتیجه در آدرس بازگشت.
	 */
	const NOTICE = 'link-tool-ok';

	/**
	 * سقف پیش‌فرض نوشته‌های اسکن‌شده در هر اجرا.
	 *
	 * عمداً محدود است: ابزار پنل باید سریع پاسخ دهد و سایت بزرگ با
	 * ۵۰٬۰۰۰ لینک هم مدیر را با درخواست بی‌پایان روبه‌رو نکند. اگر
	 * بالاترین مرز لازم بود، با فیلتر `manacore_link_tool_max_posts`
	 * یا پرچم `--limit` در WP-CLI قابل تغییر است.
	 */
	const MAX_POSTS = 2000;

	/**
	 * سقف ردیف‌های گزارش برای نمایش.
	 */
	const MAX_ROWS = 50;

	/**
	 * قلاب کرون بررسی سلامت لینک.
	 */
	const CHECK_EVENT = 'manacore_links_check';

	/**
	 * کلید گزینه‌ی خلاصه‌ی آخرین بررسی (برای کارت وضعیت پنل).
	 */
	const CHECK_LOG = 'manacore_links_check_log';

	/**
	 * سقف مطلق نشانی‌های سنجیده‌شده در یک اجرا.
	 *
	 * سنجش شبکه‌ای است و هر درخواست می‌تواند تا `timeout` ثانیه طول
	 * بکشد؛ پس سقف سخت‌گیرانه است تا نه کرون سایت را قفل کند و نه
	 * صفحه‌ی پیشخوان. برای پویش کامل، WP-CLI با `--offset` را چند بار
	 * اجرا کنید (هر اجرا از جایی که ماند ادامه می‌دهد).
	 */
	const MAX_CHECKS = 50;

	/**
	 * سقف سنجش اجرای دستی از پنل (بودجه‌ی زمان پاسخ صفحه).
	 */
	const MANUAL_CHECKS = 3;

	/**
	 * مهلت درخواست در اجرای دستی از پنل (ثانیه).
	 */
	const MANUAL_TIMEOUT = 5;

	/**
	 * پیش‌فرض‌ها و مرزهای تنظیمات بررسی.
	 */
	const MIN_BATCH      = 1;
	const MIN_TIMEOUT    = 2;
	const MAX_TIMEOUT    = 15;
	const DEFAULT_INTERVAL = 'daily';
	const DEFAULT_BATCH    = 10;
	const DEFAULT_TIMEOUT  = 5;

	/**
	 * اتصال قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_manacore_link_tool', array( $this, 'handle' ) );

		/*
		 * بررسی سلامت لینک‌ها: یک رویداد کرون با ریتم انتخابی مدیر، و
		 * یک هم‌گام‌سازی سبک در بازدید پیشخوان تا اگر رویداد از دست
		 * رفت دوباره ساخته شود.
		 */
		add_action( self::CHECK_EVENT, array( $this, 'cron' ) );
		add_action( 'admin_init', array( $this, 'sync_schedule' ) );
	}

	/* -----------------------------------------------------------------
	 * زیرساخت
	 * -------------------------------------------------------------- */

	/**
	 * نوع‌های محتوایی که گروه لینک دارند.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return array_merge( manacore_title_post_types(), array( 'episode' ) );
	}

	/**
	 * پسوندهای شناخته‌شده‌ی فایل دانلودی.
	 *
	 * @return string[]
	 */
	public static function extensions() {
		/**
		 * فیلتر فهرست پسوندهای فایل.
		 *
		 * @param string[] $extensions پسوندها بدون نقطه.
		 */
		return (array) apply_filters(
			'manacore_link_file_extensions',
			array( 'mkv', 'mp4', 'm4v', 'avi', 'mov', 'wmv', 'flv', 'ts', 'webm', 'mp3', 'aac', 'flac', 'rar', 'zip', '7z', 'tar', 'gz', 'srt', 'ass', 'vtt', 'torrent', 'iso' )
		);
	}

	/**
	 * سقف نوشته‌های هر اجرا (قابل تنظیم با فیلتر).
	 *
	 * @return int
	 */
	public static function max_posts() {
		/**
		 * فیلتر سقف نوشته‌های اسکن‌شده در ابزارهای لینک.
		 *
		 * @param int $max سقف.
		 */
		$max = (int) apply_filters( 'manacore_link_tool_max_posts', self::MAX_POSTS );

		return $max > 0 ? $max : self::MAX_POSTS;
	}

	/**
	 * شناسه‌ی نوشته‌هایی که گروه لینک دارند.
	 *
	 * @param int $limit سقف تعداد.
	 * @param int $page  شماره‌ی صفحه (برای پیمایش دسته‌ای).
	 * @return int[]
	 */
	public static function posts_with_links( $limit = 0, $page = 1 ) {
		$limit = $limit > 0 ? (int) $limit : self::max_posts();

		$ids = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'paged'          => max( 1, (int) $page ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				/*
				 * `meta_key` (نه `meta_query`) تا شرط «متا دارد» ساده و
				 * سبک بماند؛ کوئری‌های پیچیده‌ی متا روی سایت بزرگ کندند.
				 */
				'meta_key'       => Links::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * پاک‌سازی یک پیشوند نشانی (بدون اسلش انتهایی).
	 *
	 * @param string $url نشانی ورودی.
	 * @return string
	 */
	protected static function clean_prefix( $url ) {
		$url = trim( (string) $url );

		return rtrim( $url, '/' );
	}

	/**
	 * اعتبار یک پیشوند نشانی http/https.
	 *
	 * @param string $url پیشوند.
	 * @return bool
	 */
	protected static function is_http_prefix( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		return in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true );
	}

	/**
	 * عنوان نوشته برای گزارش (با افتادن به شناسه).
	 *
	 * @param int $post_id شناسه.
	 * @return string
	 */
	protected static function title( $post_id ) {
		$title = get_the_title( (int) $post_id );

		return '' !== trim( (string) $title ) ? (string) $title : '#' . (int) $post_id;
	}

	/**
	 * شناسه‌های تازه برای گروه‌ها و ردیف‌های کپی‌شده.
	 *
	 * بدون این کار، «افزودن» گروه یک اثر به اثری که همان گروه را دارد،
	 * شناسه‌ی تکراری می‌سازد و دکمه‌ی دانلود هر دو ردیف به یک شناسه
	 * اشاره می‌کند (امضای دانلود هم روی همان حساب می‌شود).
	 *
	 * @param array $groups گروه‌ها.
	 * @return array
	 */
	protected static function regen_ids( $groups ) {
		$out = array();

		foreach ( (array) $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$group['id'] = Links::uid( 'grp' );

			$items = array();

			foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$item['id']  = Links::uid( 'lnk' );
				$items[]     = $item;
			}

			$group['items'] = $items;
			$out[]          = $group;
		}

		return $out;
	}

	/* -----------------------------------------------------------------
	 * ۱) جایگزینی پیشوند نشانی
	 * -------------------------------------------------------------- */

	/**
	 * جایگزینی پیشوند (دامنه/مسیر) در نشانی همه‌ی لینک‌ها.
	 *
	 * @param string $from     پیشوند کنونی.
	 * @param string $to       پیشوند تازه.
	 * @param bool   $dry_run  فقط بررسی (پیش‌فرض).
	 * @param int    $limit    سقف نوشته‌ها.
	 * @return array نتیجه‌ی اجرا.
	 */
	public static function replace_domain( $from, $to, $dry_run = true, $limit = 0 ) {
		$from = self::clean_prefix( $from );
		$to   = self::clean_prefix( $to );

		$result = array(
			'ok'        => false,
			'reason'    => '',
			'dry_run'   => (bool) $dry_run,
			'scanned'   => 0,
			'posts'     => 0,
			'links'     => 0,
			'truncated' => false,
			'samples'   => array(),
		);

		if ( ! self::is_http_prefix( $from ) ) {
			$result['reason'] = 'bad-from';

			return $result;
		}

		if ( ! self::is_http_prefix( $to ) ) {
			$result['reason'] = 'bad-to';

			return $result;
		}

		if ( 0 === strcasecmp( $from, $to ) ) {
			$result['reason'] = 'same-prefix';

			return $result;
		}

		$limit = $limit > 0 ? (int) $limit : self::max_posts();
		$ids   = self::posts_with_links( $limit );

		/* اگر سقف پر شده باشد، شاید نوشته‌ی دیگری هم مانده باشد. */
		$result['truncated'] = count( $ids ) >= $limit;

		foreach ( $ids as $post_id ) {
			$result['scanned']++;

			$groups = Links::get( $post_id );

			if ( empty( $groups ) ) {
				continue;
			}

			$changed = 0;

			foreach ( $groups as $g_index => $group ) {
				foreach ( (array) ( $group['items'] ?? array() ) as $i_index => $item ) {
					$url = (string) ( $item['url'] ?? '' );

					if ( '' === $url || 0 !== stripos( $url, $from ) ) {
						continue;
					}

					$new = $to . substr( $url, strlen( $from ) );

					if ( $new === $url ) {
						continue;
					}

					if ( count( $result['samples'] ) < self::MAX_ROWS ) {
						$result['samples'][] = array(
							'post_id' => (int) $post_id,
							'title'   => self::title( $post_id ),
							'label'   => (string) ( $item['label'] ?? '' ),
							'from'    => $url,
							'to'      => $new,
						);
					}

					$groups[ $g_index ]['items'][ $i_index ]['url'] = $new;
					$changed++;
				}
			}

			if ( ! $changed ) {
				continue;
			}

			$result['posts']++;
			$result['links'] += $changed;

			if ( ! $dry_run ) {
				Links::save( $post_id, $groups );
			}
		}

		$result['ok'] = true;

		return $result;
	}

	/* -----------------------------------------------------------------
	 * ۲) کپی گروه لینک
	 * -------------------------------------------------------------- */

	/**
	 * کپی گروه لینک یک اثر روی چند نوشته.
	 *
	 * @param int          $source_id شناسه‌ی اثر مبدأ.
	 * @param array|string $targets   فهرست شناسه‌ها (آرایه یا «۱,۲,۳»).
	 * @param string       $mode      `missing` | `append` | `replace`.
	 * @return array نتیجه‌ی اجرا.
	 */
	public static function copy_links( $source_id, $targets, $mode = 'missing' ) {
		$source_id = absint( $source_id );
		$mode      = in_array( $mode, array( 'missing', 'append', 'replace' ), true ) ? $mode : 'missing';

		$result = array(
			'ok'      => false,
			'reason'  => '',
			'mode'    => $mode,
			'source'  => $source_id,
			'copied'  => 0,
			'skipped' => 0,
			'missing' => 0,
			'details' => array(),
		);

		if ( ! in_array( get_post_type( $source_id ), self::post_types(), true ) ) {
			$result['reason'] = 'bad-source';

			return $result;
		}

		$source = Links::get( $source_id );

		if ( empty( $source ) ) {
			$result['reason'] = 'empty-source';

			return $result;
		}

		if ( ! is_array( $targets ) ) {
			$targets = explode( ',', (string) $targets );
		}

		$ids = array();

		foreach ( (array) $targets as $target ) {
			$id = absint( trim( (string) $target ) );

			if ( $id && $id !== $source_id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			$result['reason'] = 'no-targets';

			return $result;
		}

		foreach ( $ids as $target_id ) {
			if ( ! in_array( get_post_type( $target_id ), self::post_types(), true ) ) {
				$result['missing']++;

				if ( count( $result['details'] ) < self::MAX_ROWS ) {
					$result['details'][] = array(
						'post_id' => (int) $target_id,
						'title'   => self::title( $target_id ),
						'action'  => 'missing',
					);
				}

				continue;
			}

			$existing = Links::get( $target_id );
			$action   = '';

			if ( 'missing' === $mode && ! empty( $existing ) ) {
				$action = 'skipped';
			} elseif ( 'replace' === $mode || empty( $existing ) ) {
				$action = 'replaced';
			} else {
				$action = 'appended';
			}

			if ( 'skipped' === $action ) {
				$result['skipped']++;

				if ( count( $result['details'] ) < self::MAX_ROWS ) {
					$result['details'][] = array(
						'post_id' => (int) $target_id,
						'title'   => self::title( $target_id ),
						'action'  => 'skipped',
					);
				}

				continue;
			}

			$groups = ( 'appended' === $action )
				? array_merge( $existing, self::regen_ids( $source ) )
				: $source;

			Links::save( $target_id, $groups );

			$result['copied']++;

			if ( count( $result['details'] ) < self::MAX_ROWS ) {
				$result['details'][] = array(
					'post_id' => (int) $target_id,
					'title'   => self::title( $target_id ),
					'action'  => $action,
				);
			}
		}

		$result['ok'] = true;

		return $result;
	}

	/* -----------------------------------------------------------------
	 * ۳) بازرسی ساختاری
	 * -------------------------------------------------------------- */

	/**
	 * بازرسی گروه‌های لینک بدون درخواست شبکه.
	 *
	 * @param int $limit سقف نوشته‌ها.
	 * @return array گزارش.
	 */
	public static function audit( $limit = 0 ) {
		$limit = $limit > 0 ? (int) $limit : self::max_posts();
		$ids   = self::posts_with_links( $limit );

		$report = array(
			'scanned'   => 0,
			'truncated' => count( $ids ) >= $limit,
			'totals'    => array(
				'bad-url'      => 0,
				'no-quality'   => 0,
				'no-size'      => 0,
				'dup-quality'  => 0,
				'unknown-file' => 0,
			),
			'rows'      => array(),
		);

		$extensions = array_map( 'strtolower', self::extensions() );

		foreach ( $ids as $post_id ) {
			$report['scanned']++;

			$groups = Links::get( $post_id );

			foreach ( $groups as $group ) {
				$seen = array();

				foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
					/*
					 * یک ردیف می‌تواند چند ایراد داشته باشد (مثلاً هم بی‌کیفیت
					 * هم بی‌حجم)؛ همه شمرده می‌شوند تا گزارش واقعی باشد.
					 */
					$issues = array();

					$url = trim( (string) ( $item['url'] ?? '' ) );

					if ( '' === $url || 0 !== strpos( $url, 'magnet:' ) ) {
						$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

						if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
							$issues[] = 'bad-url';
						} else {
							$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
							$ext  = (string) pathinfo( $path, PATHINFO_EXTENSION );

							if ( '' === $ext || ! in_array( $ext, $extensions, true ) ) {
								$issues[] = 'unknown-file';
							}
						}
					}

					$quality = trim( (string) ( $item['quality'] ?? '' ) );

					if ( '' === $quality ) {
						$quality = trim( (string) ( $group['quality'] ?? '' ) );
					}

					if ( '' === $quality ) {
						$issues[] = 'no-quality';
					} elseif ( isset( $seen[ $quality ] ) ) {
						$issues[] = 'dup-quality';
					} else {
						$seen[ $quality ] = true;
					}

					$size = trim( (string) ( $item['size'] ?? '' ) );

					if ( '' === $size ) {
						$size = trim( (string) ( $group['size'] ?? '' ) );
					}

					if ( '' === $size ) {
						$issues[] = 'no-size';
					}

					foreach ( array_unique( $issues ) as $issue ) {
						$report['totals'][ $issue ]++;

						if ( count( $report['rows'] ) < self::MAX_ROWS ) {
							$report['rows'][] = array(
								'post_id' => (int) $post_id,
								'title'   => self::title( $post_id ),
								'issue'   => $issue,
								'label'   => (string) ( $item['label'] ?? '' ),
								'quality' => $quality,
								'url'     => $url,
							);
						}
					}
				}
			}
		}

		return $report;
	}

	/* -----------------------------------------------------------------
	 * ۴) سلامت لینک‌ها
	 * -------------------------------------------------------------- */

	/**
	 * بازه‌های مجاز بررسی و معادلشان در زمان‌بندی وردپرس.
	 *
	 * @return array<string,array{label:string,recurrence:string}>
	 */
	public static function intervals() {
		return array(
			'off'        => array(
				'label'      => __( 'خاموش', 'manacore' ),
				'recurrence' => '',
			),
			'hourly'     => array(
				'label'      => __( 'ساعتی', 'manacore' ),
				'recurrence' => 'hourly',
			),
			'twicedaily' => array(
				'label'      => __( 'دو بار در روز', 'manacore' ),
				'recurrence' => 'twicedaily',
			),
			'daily'      => array(
				'label'      => __( 'روزی یک بار', 'manacore' ),
				'recurrence' => 'daily',
			),
			'weekly'     => array(
				'label'      => __( 'هفتگی', 'manacore' ),
				'recurrence' => 'weekly',
			),
		);
	}

	/**
	 * مرزهای مجاز تنظیمات بررسی (برای پاک‌سازی پنل).
	 *
	 * @return array<string,array<int,int>>
	 */
	public static function check_limits() {
		return array(
			'batch'   => array( self::MIN_BATCH, self::MAX_CHECKS ),
			'timeout' => array( self::MIN_TIMEOUT, self::MAX_TIMEOUT ),
		);
	}

	/**
	 * تنظیمات بررسی سلامت لینک.
	 *
	 * پیش‌فرض‌ها همان مقادیری هستند که پنل هم نشان می‌دهد؛ پس پیش از
	 * نخستین ذخیره هم رفتار افزونه روشن و قابل پیش‌بینی است.
	 *
	 * @return array{interval:string,recurrence:string,batch:int,timeout:int}
	 */
	public static function check_settings() {
		$settings = get_option( 'manacore_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$intervals = self::intervals();
		$interval  = isset( $settings['links_check_interval'] ) ? (string) $settings['links_check_interval'] : self::DEFAULT_INTERVAL;

		if ( ! isset( $intervals[ $interval ] ) ) {
			$interval = self::DEFAULT_INTERVAL;
		}

		$batch   = isset( $settings['links_check_batch'] ) ? (int) $settings['links_check_batch'] : self::DEFAULT_BATCH;
		$timeout = isset( $settings['links_check_timeout'] ) ? (int) $settings['links_check_timeout'] : self::DEFAULT_TIMEOUT;

		return array(
			'interval'   => $interval,
			'recurrence' => (string) $intervals[ $interval ]['recurrence'],
			'batch'      => max( self::MIN_BATCH, min( self::MAX_CHECKS, $batch ) ),
			'timeout'    => max( self::MIN_TIMEOUT, min( self::MAX_TIMEOUT, $timeout ) ),
		);
	}

	/**
	 * هم‌گام‌سازی زمان‌بندی کرون با تنظیمات.
	 *
	 * idempotent است: اگر رویداد نباشد می‌سازد، اگر ریتمش عوض شده
	 * جایگزین می‌کند و اگر خاموش شده باشد پاکش می‌کند. روی `admin_init`
	 * اجرا می‌شود تا ذخیره‌ی تنظیمات بی‌درنگ اثر کند و سایت خودش را
	 * درمان کند.
	 *
	 * @return void
	 */
	public function sync_schedule() {
		$recurrence = self::check_settings()['recurrence'];
		$scheduled  = wp_next_scheduled( self::CHECK_EVENT );

		if ( '' === $recurrence ) {
			if ( $scheduled ) {
				wp_clear_scheduled_hook( self::CHECK_EVENT );
			}

			return;
		}

		if ( $scheduled && wp_get_schedule( self::CHECK_EVENT ) === $recurrence ) {
			return;
		}

		if ( $scheduled ) {
			wp_clear_scheduled_hook( self::CHECK_EVENT );
		}

		/* نخستین اجرا یک ساعت بعد؛ ذخیره‌ی تنظیمات باعث هجوم درخواست نشود. */
		wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::CHECK_EVENT );
	}

	/**
	 * اجرای زمان‌بندی‌شده (کرون).
	 *
	 * @return void
	 */
	public function cron() {
		self::check();
	}

	/**
	 * همه‌ی ردیف‌های لینک سایت: نوشته، کیفیت و نشانی.
	 *
	 * عمداً یک‌بار خوانده و در همان اجرا چند بار پیمایش می‌شود؛ سنجش
	 * شبکه‌ای است و خواندن مکرر متا فقط کندی می‌آورد.
	 *
	 * @return array<int,array{post_id:int,quality:string,label:string,url:string}>
	 */
	public static function link_rows() {
		$rows = array();

		foreach ( self::posts_with_links() as $post_id ) {
			foreach ( Links::get( $post_id ) as $group ) {
				foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
					$url = trim( (string) ( $item['url'] ?? '' ) );

					if ( '' === $url ) {
						continue;
					}

					$quality = trim( (string) ( $item['quality'] ?? '' ) );

					if ( '' === $quality ) {
						$quality = trim( (string) ( $group['quality'] ?? '' ) );
					}

					$rows[] = array(
						'post_id' => (int) $post_id,
						'quality' => $quality,
						'label'   => (string) ( $item['label'] ?? '' ),
						'url'     => $url,
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * سنجش گروهی لینک‌ها و ثبت لینک‌های مرده در صف گزارش‌ها.
	 *
	 * سه اصل:
	 *   ۱. محافظه‌کار است — تنها «۴۰۴/۴۱۰» و «بی‌پاسخ ماندن» مرده
	 *      حساب می‌شوند؛ ۵xx و محدودیت نرخ گزارش نمی‌شوند.
	 *   ۲. بی‌خطر است — نشانی همین سایت و دامنه‌های خصوصی/محلی سنجیده
	 *      نمی‌شوند، پس نه سایت با درخواست به خودش درگیر می‌شود و نه
	 *      ابزار به کلیدی برای شبکه‌ی داخلی تبدیل می‌شود.
	 *   ۳. خودترمیم است — لینکی که این بار سالم پاسخ دهد، گزارش بازش
	 *      «اصلاح‌شده» می‌شود و از صف خارج می‌گردد.
	 *
	 * @param array $args گزینه‌ها: limit، offset، timeout، report، resolve و dry_run.
	 * @return array گزارش اجرا.
	 */
	public static function check( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'   => 0,
				'offset'  => -1,
				'timeout' => 0,
				'report'  => true,
				'resolve' => true,
				'dry_run' => false,
			)
		);

		$settings = self::check_settings();
		$timeout  = (int) $args['timeout'] > 0 ? (int) $args['timeout'] : (int) $settings['timeout'];

		/**
		 * فیلتر سقف سنجش‌های شبکه در هر اجرا.
		 *
		 * @param int $max سقف.
		 */
		$max   = (int) apply_filters( 'manacore_link_check_max', self::MAX_CHECKS );
		$max   = $max > 0 ? $max : self::MAX_CHECKS;
		$limit = (int) $args['limit'] > 0 ? min( (int) $args['limit'], $max ) : min( (int) $settings['batch'], $max );

		$rows    = self::link_rows();
		$urls    = array();
		$skipped = 0;

		foreach ( $rows as $row ) {
			if ( ! self::is_checkable( $row['url'] ) ) {
				$skipped++;
				continue;
			}

			$urls[ $row['url'] ] = true;
		}

		$queue  = array_keys( $urls );
		$total  = count( $queue );
		$offset = (int) $args['offset'];

		if ( $offset < 0 ) {
			$offset = self::next_offset();
		}

		if ( $offset >= $total ) {
			$offset = 0;
		}

		$slice = array_slice( $queue, $offset, $limit );
		$next  = $offset + count( $slice );

		if ( $next >= $total ) {
			$next = 0;
		}

		$report = array(
			'ok'        => true,
			'reason'    => '',
			'dry_run'   => (bool) $args['dry_run'],
			'timeout'   => $timeout,
			'total'     => $total,
			'checked'   => 0,
			'alive'     => 0,
			'dead'      => 0,
			'unknown'   => 0,
			'filed'     => 0,
			'resolved'  => 0,
			'skipped'   => $skipped,
			'truncated' => ( $offset + count( $slice ) ) < $total,
			'next'      => $next,
			'time'      => time(),
			'rows'      => array(),
		);

		foreach ( $slice as $url ) {
			$probe = self::probe( $url, $timeout );

			$report['checked']++;

			if ( 'alive' === $probe['status'] ) {
				$report['alive']++;
			} elseif ( 'dead' === $probe['status'] ) {
				$report['dead']++;
			} else {
				$report['unknown']++;
			}

			/* هر نوشته‌ای که همین نشانی را دارد، هدف گزارش است. */
			$targets = array();

			foreach ( $rows as $row ) {
				if ( $row['url'] === $url ) {
					$targets[] = $row;
				}
			}

			if ( 'dead' === $probe['status'] && ! empty( $args['report'] ) && empty( $args['dry_run'] ) ) {
				foreach ( $targets as $target ) {
					$report['filed'] += self::file_report( $target, $probe );
				}
			}

			if ( 'alive' === $probe['status'] && ! empty( $args['resolve'] ) && empty( $args['dry_run'] ) ) {
				foreach ( $targets as $target ) {
					$report['resolved'] += self::resolve_report( $target, $url );
				}
			}

			if ( count( $report['rows'] ) < self::MAX_ROWS ) {
				$first = $targets[0];

				$report['rows'][] = array(
					'post_id' => (int) $first['post_id'],
					'title'   => self::title( (int) $first['post_id'] ),
					'quality' => (string) $first['quality'],
					'url'     => (string) $url,
					'status'  => (string) $probe['status'],
					'detail'  => (string) $probe['detail'],
				);
			}
		}

		if ( empty( $args['dry_run'] ) ) {
			self::save_log( $report );
		}

		return $report;
	}

	/**
	 * آیا این نشانی سنجیدنی است؟
	 *
	 * @param string $url نشانی.
	 * @return bool
	 */
	public static function is_checkable( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );

		if ( '' === $host || $host === self::site_host() ) {
			return false;
		}

		$ips = self::public_ips( $host );

		if ( ! $ips ) {
			return false;
		}

		/*
		 * اگر یکی از نشانی‌های IP دامنه خصوصی/رزروشده باشد، کل دامنه کنار
		 * گذاشته می‌شود؛ دامنه‌ای که هم IP عمومی و هم خصوصی دارد جای
		 * دودلی است و ابزار مدیر نباید لایه‌ی شبکه را بکاود.
		 */
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * میزبان همین سایت.
	 *
	 * @return string
	 */
	protected static function site_host() {
		return strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}

	/**
	 * نشانی‌های IP یک دامنه، با کش درون‌اجرایی.
	 *
	 * اگر دامنه پیدا نشود فهرست خالی برمی‌گردد و آن لینک سنجیده
	 * نمی‌شود؛ «پیدا نشدن دامنه» از این سو مدرکی بر خرابی فایل نیست.
	 *
	 * @param string $host نام دامنه یا خود IP.
	 * @return string[]
	 */
	protected static function public_ips( $host ) {
		static $cache = array();

		if ( isset( $cache[ $host ] ) ) {
			return $cache[ $host ];
		}

		$ips = array();

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host;
		} else {
			$ipv4 = gethostbyname( $host );

			if ( filter_var( $ipv4, FILTER_VALIDATE_IP ) ) {
				$ips[] = $ipv4;
			}

			if ( function_exists( 'dns_get_record' ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- نبودِ رکورد AAAA خطا نیست.
				foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = (string) $record['ipv6'];
					}
				}
			}
		}

		$cache[ $host ] = $ips;

		return $ips;
	}

	/**
	 * سنجش یک نشانی و برگرداندن وضعیتش.
	 *
	 * @param string $url     نشانی.
	 * @param int    $timeout مهلت درخواست (ثانیه)؛ صفر = پیش‌فرض.
	 * @return array{status:string,code:int,detail:string}
	 */
	public static function probe( $url, $timeout = 0 ) {
		$timeout = $timeout > 0 ? (int) $timeout : self::DEFAULT_TIMEOUT;

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 3,
			'user-agent'  => 'ManaCore/' . ( defined( 'MANACORE_VERSION' ) ? MANACORE_VERSION : '1.0' ) . '; ' . home_url( '/' ),
		);

		$result = self::classify( wp_remote_head( $url, $args ) );

		/*
		 * بعضی میزبان‌ها HEAD را نمی‌پذیرند (۴۰۳/۴۰۵/۵۰۱)؛ با یک درخواست
		 * سبک GET که فقط یک بایت می‌خواهد دوباره می‌سنجیم تا لینک سالم
		 * بی‌دلیل «نامشخص» نماند.
		 */
		if ( 'unknown' === $result['status'] && in_array( (int) $result['code'], array( 403, 405, 501 ), true ) ) {
			$args['headers'] = array( 'Range' => 'bytes=0-0' );

			$result = self::classify( wp_remote_get( $url, $args ) );
		}

		return $result;
	}

	/**
	 * تبدیل پاسخ HTTP به وضعیت سه‌گانه.
	 *
	 * @param array|\WP_Error $response پاسخ درخواست.
	 * @return array{status:string,code:int,detail:string}
	 */
	protected static function classify( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 'dead',
				'code'   => 0,
				'detail' => self::error_detail( $response ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 400 ) {
			return array(
				'status' => 'alive',
				'code'   => $code,
				'detail' => '',
			);
		}

		if ( 404 === $code ) {
			return array(
				'status' => 'dead',
				'code'   => $code,
				'detail' => __( 'پاسخ ۴۰۴ (فایل پیدا نشد)', 'manacore' ),
			);
		}

		if ( 410 === $code ) {
			return array(
				'status' => 'dead',
				'code'   => $code,
				'detail' => __( 'پاسخ ۴۱۰ (فایل برای همیشه برداشته شده)', 'manacore' ),
			);
		}

		return array(
			'status' => 'unknown',
			'code'   => $code,
			'detail' => sprintf(
				/* translators: %s: کد وضعیت HTTP یا «بی‌پاسخ» */
				__( 'پاسخ %s (نامشخص؛ گزارش نشد)', 'manacore' ),
				$code > 0 ? (string) $code : __( 'بی‌پاسخ', 'manacore' )
			),
		);
	}

	/**
	 * توضیح فارسی خطای شبکه.
	 *
	 * @param \WP_Error $error خطای درخواست.
	 * @return string
	 */
	protected static function error_detail( $error ) {
		$labels = array(
			'http_request_failed'       => __( 'ارتباط برقرار نشد یا مهلت پاسخ تمام شد', 'manacore' ),
			'connect_error'             => __( 'اتصال به میزبان برقرار نشد', 'manacore' ),
			'timeout'                   => __( 'مهلت پاسخ تمام شد', 'manacore' ),
			'http_request_not_executed' => __( 'درخواست اجرا نشد', 'manacore' ),
		);

		$code = (string) $error->get_error_code();

		if ( isset( $labels[ $code ] ) ) {
			return $labels[ $code ];
		}

		$message = trim( (string) $error->get_error_message() );

		return '' !== $message ? $message : __( 'خطای شبکه', 'manacore' );
	}

	/**
	 * ثبت یک لینک مرده در صف گزارش‌ها.
	 *
	 * اگر برای همین نوشته و همین کیفیت گزارشی باز باشد، چیز تازه‌ای
	 * ثبت نمی‌شود؛ صف مدیر جای ردیف‌های تکراری نیست.
	 *
	 * @param array $row   ردیف لینک.
	 * @param array $probe نتیجه‌ی سنجش.
	 * @return int یک اگر گزارش تازه ثبت شد، وگرنه صفر.
	 */
	protected static function file_report( $row, $probe ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Reports' ) ) {
			return 0;
		}

		if ( Reports::has_open( (int) $row['post_id'], (string) $row['quality'] ) ) {
			return 0;
		}

		$id = (int) Reports::insert(
			array(
				'post_id'    => (int) $row['post_id'],
				'link_url'   => (string) $row['url'],
				'link_label' => (string) $row['label'],
				'quality'    => (string) $row['quality'],
				'reason'     => sprintf(
					/* translators: %s: توضیح فنی سنجش خودکار */
					__( 'بررسی خودکار: %s', 'manacore' ),
					(string) $probe['detail']
				),
			)
		);

		return $id > 0 ? 1 : 0;
	}

	/**
	 * بستن گزارش بازِ یک نشانی که سالم پاسخ داده است.
	 *
	 * @param array  $row ردیف لینک.
	 * @param string $url نشانی سنجیده‌شده.
	 * @return int شمار گزارش‌های بسته‌شده.
	 */
	protected static function resolve_report( $row, $url ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Reports' ) ) {
			return 0;
		}

		return (int) Reports::resolve_url( (int) $row['post_id'], (string) $url );
	}

	/**
	 * ذخیره‌ی خلاصه‌ی آخرین اجرا (برای کارت وضعیت پنل).
	 *
	 * @param array $report گزارش اجرا.
	 * @return void
	 */
	protected static function save_log( $report ) {
		update_option(
			self::CHECK_LOG,
			array(
				'time'     => (int) $report['time'],
				'total'    => (int) $report['total'],
				'checked'  => (int) $report['checked'],
				'alive'    => (int) $report['alive'],
				'dead'     => (int) $report['dead'],
				'unknown'  => (int) $report['unknown'],
				'filed'    => (int) $report['filed'],
				'resolved' => (int) $report['resolved'],
				'skipped'  => (int) $report['skipped'],
				'next'     => (int) $report['next'],
			),
			false
		);
	}

	/**
	 * خلاصه‌ی آخرین اجرای بررسی.
	 *
	 * @return array
	 */
	public static function check_log() {
		$log = get_option( self::CHECK_LOG, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * نقطه‌ی ادامه‌ی پویش.
	 *
	 * هر اجرا از جایی ادامه می‌دهد که اجرای پیشین ماند و در پایان
	 * فهرست به ابتدا برمی‌گردد؛ پس سایت بزرگ هم در چند اجرا کامل
	 * دیده می‌شود و همیشه فقط لینک‌های نخست بررسی نمی‌شوند.
	 *
	 * @return int
	 */
	protected static function next_offset() {
		$log = self::check_log();

		return isset( $log['next'] ) ? max( 0, (int) $log['next'] ) : 0;
	}

	/* -----------------------------------------------------------------
	 * فرم پنل و پیام‌ها
	 * -------------------------------------------------------------- */

	/**
	 * اجرای ابزار از پنل (admin-post) و ذخیره‌ی نتیجه برای نمایش.
	 *
	 * نتیجه در ترنزینت کوتاه‌عمر به‌ازای هر کاربر می‌نشیند و در همان صفحه
	 * خوانده و پاک می‌شود؛ پس گزارش سنگین در آدرس جابه‌جا نمی‌شود.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی لازم را ندارید.', 'manacore' ), 403 );
		}

		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

		check_admin_referer( 'manacore_link_tool_' . $tool );

		$result = array();

		switch ( $tool ) {
			case 'domain':
				$from   = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
				$to     = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
				$dry    = empty( $_POST['apply'] );
				$result = self::replace_domain( $from, $to, $dry );
				$result['tool'] = 'domain';
				break;

			case 'copy':
				$source = isset( $_POST['source'] ) ? absint( wp_unslash( $_POST['source'] ) ) : 0;
				$targets = isset( $_POST['targets'] ) ? sanitize_text_field( wp_unslash( $_POST['targets'] ) ) : '';
				$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'missing';
				$result = self::copy_links( $source, $targets, $mode );
				$result['tool'] = 'copy';
				break;

			case 'audit':
				$result         = self::audit();
				$result['tool'] = 'audit';
				break;
		}

		set_transient( self::RESULT_KEY . '_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'manacore',
					'tab'             => 'tools',
					'manacore_notice' => self::NOTICE,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * خواندن و پاک‌کردن نتیجه‌ی آخرین اجرا (برای نمایش در پنل).
	 *
	 * @return array
	 */
	public static function take_result() {
		$key    = self::RESULT_KEY . '_' . get_current_user_id();
		$result = get_transient( $key );

		if ( false === $result || ! is_array( $result ) ) {
			return array();
		}

		delete_transient( $key );

		return $result;
	}

	/**
	 * برچسب فارسی دلیل‌های خطا (برای پنل و WP-CLI).
	 *
	 * @param string $reason کد دلیل.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			'bad-from'    => __( 'نشانی «از» معتبر نیست؛ باید با http:// یا https:// و نام دامنه بیاید.', 'manacore' ),
			'bad-to'      => __( 'نشانی «به» معتبر نیست؛ باید با http:// یا https:// و نام دامنه بیاید.', 'manacore' ),
			'same-prefix' => __( 'نشانی «از» و «به» یکی است؛ چیزی برای تغییر نیست.', 'manacore' ),
			'bad-source'  => __( 'نوشته‌ی مبدأ پیدا نشد یا نوعش فیلم/سریال/انیمه/قسمت نیست.', 'manacore' ),
			'empty-source' => __( 'نوشته‌ی مبدأ هیچ گروه لینکی ندارد.', 'manacore' ),
			'no-targets'  => __( 'هیچ شناسه‌ی مقصد معتبری وارد نشده است.', 'manacore' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : '';
	}
}

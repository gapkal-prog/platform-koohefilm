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
 * سه کار این کلاس:
 *   ۱. `replace_domain()` — جایگزینی پیشوند (دامنه/مسیر) در همه‌ی لینک‌ها،
 *      با پیش‌نمایش: چیزی تا تأیید مدیر نوشته نمی‌شود.
 *   ۲. `copy_links()` — کپی گروه لینک یک اثر روی چند نوشته، با سه حالت
 *      «فقط خالی‌ها»، «افزودن» و «جایگزینی».
 *   ۳. `audit()` — بازرسی ساختاری بدون درخواست شبکه: کیفیت/حجم ناقص،
 *      کیفیت تکراری در یک گروه و نشانی بدون پسوند فایل قابل‌شناسایی.
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
	 * اتصال قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_manacore_link_tool', array( $this, 'handle' ) );
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

<?php
/**
 * داشبورد تحلیلی مدیر.
 *
 * همه‌ی داده‌ی این گزارش‌ها از پیش در سایت هست (جدول `manacore_stats` برای
 * تماشا/دانلود، امتیازها، گزارش‌های خرابی لینک، درخواست‌ها و
 * دیدگاه‌ها)؛ چیزی که کم بود «یک جا دیدنشان» بود. این کلاس فقط می‌خواند و
 * هیچ‌وقت داده‌ی تازه‌ای نمی‌سازد.
 *
 * دو تصمیم مهم:
 * - **نتیجه‌ها ۵ دقیقه کش می‌شوند.** چند پرس‌وجوی GROUP BY روی جدول آمار در
 *   هر بازکردن پیشخوان، روی سایت بزرگ هزینه‌ی بی‌دلیل است؛ ۵ دقیقه تأخیر در
 *   گزارش مدیریتی ضرری ندارد.
 * - هر متد حتی با صفر داده ساختار درست برمی‌گرداند (بدون هشدار تقسیم بر صفر
 *   و بدون رشته‌ی خالی در جدول).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Analytics
 */
class Analytics {

	use Singleton;

	/**
	 * پیشوند کلید کش.
	 */
	const CACHE_PREFIX = 'manacore_analytics_';

	/**
	 * عمر کش (ثانیه).
	 */
	const CACHE_TTL = 300;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );

		/* ذخیره‌ی تنظیمات و تغییر نوع محتوا، گزارش را کهنه می‌کند. */
		add_action( 'update_option_manacore_settings', array( $this, 'flush' ) );
		add_action( 'manacore_db_upgraded', array( $this, 'flush' ) );
		add_action( 'save_post_manacore_request', array( $this, 'flush' ) );
		add_action( 'manacore_link_reported', array( $this, 'flush' ) );
		add_action( 'manacore_request_voted', array( $this, 'flush' ) );
	}

	/**
	 * خواندن یک گزارش با کش.
	 *
	 * @param string   $key      کلید گزارش.
	 * @param callable $callback سازنده‌ی گزارش.
	 * @return mixed
	 */
	protected static function remember( $key, $callback ) {
		$cache_key = self::CACHE_PREFIX . $key;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = call_user_func( $callback );

		set_transient( $cache_key, $value, self::CACHE_TTL );

		return $value;
	}

	/**
	 * پاک‌کردن کش گزارش‌ها.
	 */
	public function flush() {
		$keys = array( 'summary', 'top_reported', 'top_requests', 'ratings' );

		/*
		 * کلیدهای «پرتماشاترین» به نوع و بازه وابسته‌اند؛ همان بازه‌های
		 * رایج پاک می‌شوند تا کلید جامانده‌ای نماند.
		 */
		foreach ( array( 'view', 'download' ) as $type ) {
			foreach ( array( 1, 7, 30 ) as $days ) {
				$keys[] = 'top_' . $type . '_' . $days;
			}
		}

		foreach ( $keys as $key ) {
			delete_transient( self::CACHE_PREFIX . $key );
		}
	}

	/**
	 * جمع تماشا یا دانلود در یک بازه‌ی زمانی.
	 *
	 * @param string $type نوع آمار (view|download).
	 * @param int    $days تعداد روز.
	 * @return int
	 */
	public static function total( $type, $days ) {
		global $wpdb;

		$table = Install::stats_table();
		$since = gmdate( 'Y-m-d', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(stat_count) FROM {$table} WHERE stat_type = %s AND stat_date >= %s",
				sanitize_key( $type ),
				$since
			)
		);

		return max( 0, (int) $total );
	}

	/**
	 * خلاصه‌ی وضعیت سایت.
	 *
	 * @return array<string,mixed>
	 */
	public static function summary() {
		return self::remember(
			'summary',
			static function () {
				$comments = (array) wp_count_comments();
				$reports  = class_exists( __NAMESPACE__ . '\\Reports' ) ? Reports::counts() : array( 'new' => 0, 'fixed' => 0, 'ignored' => 0 );
				$requests = class_exists( __NAMESPACE__ . '\\Requests' ) ? Requests::counts() : array( 'pending' => 0, 'publish' => 0, 'draft' => 0 );

				return array(
					'views_today'      => self::total( 'view', 1 ),
					'views_week'       => self::total( 'view', 7 ),
					'views_month'      => self::total( 'view', 30 ),
					'downloads_week'   => self::total( 'download', 7 ),
					'reports_new'      => isset( $reports['new'] ) ? (int) $reports['new'] : 0,
					'requests_pending' => isset( $requests['pending'] ) ? (int) $requests['pending'] : 0,
					'requests_publish' => isset( $requests['publish'] ) ? (int) $requests['publish'] : 0,
					'comments_pending' => isset( $comments['moderated'] ) ? (int) $comments['moderated'] : 0,
					'comments_spam'    => isset( $comments['spam'] ) ? (int) $comments['spam'] : 0,
				);
			}
		);
	}

	/**
	 * پرتماشاترین/پربارگیری‌شده‌ترین آثار یک بازه.
	 *
	 * @param string $type  نوع آمار.
	 * @param int    $days  تعداد روز.
	 * @param int    $limit تعداد نتیجه.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top( $type, $days, $limit = 10 ) {
		$type  = 'download' === $type ? 'download' : 'view';
		$days  = max( 1, (int) $days );
		$limit = max( 1, min( 50, (int) $limit ) );
		$key   = 'top_' . $type . '_' . $days;

		return self::remember(
			$key,
			static function () use ( $type, $days, $limit ) {
				global $wpdb;

				$table = Install::stats_table();
				$since = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id, SUM(stat_count) AS total FROM {$table}
						WHERE stat_type = %s AND stat_date >= %s
						GROUP BY post_id ORDER BY total DESC LIMIT %d",
						$type,
						$since,
						$limit
					),
					ARRAY_A
				);

				$out = array();

				foreach ( (array) $rows as $row ) {
					$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;

					if ( ! $post_id ) {
						continue;
					}

					$out[] = array(
						'id'    => $post_id,
						'title' => (string) get_the_title( $post_id ),
						'type'  => (string) get_post_type( $post_id ),
						'total' => isset( $row['total'] ) ? (int) $row['total'] : 0,
					);
				}

				return $out;
			}
		);
	}

	/**
	 * بیشترین گزارش خرابی لینک به تفکیک اثر.
	 *
	 * @param int $limit تعداد نتیجه.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top_reported( $limit = 10 ) {
		$limit = max( 1, min( 50, (int) $limit ) );

		return self::remember(
			'top_reported',
			static function () use ( $limit ) {
				global $wpdb;

				if ( ! class_exists( __NAMESPACE__ . '\\Reports' ) ) {
					return array();
				}

				$table = Install::reports_table();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id, COUNT(*) AS total, SUM(status = 'new') AS open_count
						FROM {$table} GROUP BY post_id ORDER BY total DESC LIMIT %d",
						$limit
					),
					ARRAY_A
				);

				$out = array();

				foreach ( (array) $rows as $row ) {
					$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;

					if ( ! $post_id ) {
						continue;
					}

					$out[] = array(
						'id'    => $post_id,
						'title' => (string) get_the_title( $post_id ),
						'total' => isset( $row['total'] ) ? (int) $row['total'] : 0,
						'open'  => isset( $row['open_count'] ) ? (int) $row['open_count'] : 0,
					);
				}

				return $out;
			}
		);
	}

	/**
	 * پررأی‌ترین درخواست‌های کاربران.
	 *
	 * @param int $limit تعداد نتیجه.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top_requests( $limit = 10 ) {
		$limit = max( 1, min( 50, (int) $limit ) );

		return self::remember(
			'top_requests',
			static function () use ( $limit ) {
				if ( ! class_exists( __NAMESPACE__ . '\\Requests' ) ) {
					return array();
				}

				$posts = get_posts(
					array(
						'post_type'      => Requests::POST_TYPE,
						'post_status'    => array( 'publish', 'pending' ),
						'posts_per_page' => $limit,
						'orderby'        => 'meta_value_num',
						'meta_key'       => Requests::COUNT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'order'          => 'DESC',
					)
				);

				$out = array();

				foreach ( $posts as $post ) {
					$out[] = array(
						'id'     => (int) $post->ID,
						'title'  => (string) $post->post_title,
						'status' => (string) $post->post_status,
						'votes'  => Requests::votes( (int) $post->ID ),
					);
				}

				return $out;
			}
		);
	}

	/**
	 * وضعیت امتیازها.
	 *
	 * @return array<string,mixed>
	 */
	public static function ratings_summary() {
		return self::remember(
			'ratings',
			static function () {
				global $wpdb;

				$table = Install::ratings_table();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row = $wpdb->get_row(
					$wpdb->prepare( "SELECT COUNT(*) AS total, AVG(rating) AS average FROM {$table} WHERE rating > %d", 0 ),
					ARRAY_A
				);

				$total   = isset( $row['total'] ) ? (int) $row['total'] : 0;
				$average = isset( $row['average'] ) ? round( (float) $row['average'], 2 ) : 0.0;

				return array(
					'total'   => $total,
					'average' => $average,
					'percent' => $average > 0 ? round( ( $average / 5 ) * 100, 1 ) : 0.0,
				);
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * ویجت پیشخوان وردپرس
	 * ------------------------------------------------------------------ */

	/**
	 * ثبت ویجت پیشخوان.
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'manacore_overview',
			__( 'ManaCore — نگاه یک‌صفحه‌ای سایت', 'manacore' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * رندر ویجت پیشخوان.
	 *
	 * این ویجت «داشبورد» واقعی افزونه است: عددهای کلیدی و دو فهرست کوتاه
	 * برترین‌های هفته. استایل از `assets/css/admin.css` می‌آید (همان فایل
	 * صفحه‌ی تنظیمات) و هیچ استایلی درون‌خطی تزریق نمی‌شود.
	 *
	 * @return void
	 */
	public function render_widget() {
		$summary = self::summary();
		$ratings = self::ratings_summary();

		$cells = array(
			__( 'تماشا امروز', 'manacore' )  => number_format_i18n( (int) $summary['views_today'] ),
			__( 'تماشا ۷ روز', 'manacore' )  => number_format_i18n( (int) $summary['views_week'] ),
			__( 'دانلود ۷ روز', 'manacore' )  => number_format_i18n( (int) $summary['downloads_week'] ),
			__( 'امتیاز میانگین', 'manacore' ) => number_format_i18n( (float) $ratings['average'], 2 ),
			__( 'گزارش تازه', 'manacore' )    => number_format_i18n( (int) $summary['reports_new'] ),
			__( 'درخواست باز', 'manacore' )   => number_format_i18n( (int) $summary['requests_pending'] ),
		);
		?>
		<ul class="manacore-widget-grid">
			<?php foreach ( $cells as $label => $value ) : ?>
				<li>
					<b><?php echo esc_html( manacore_fa_digits( (string) $value ) ); ?></b>
					<span><?php echo esc_html( $label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php

		$this->widget_list( __( 'پربازدیدترین‌های ۷ روز', 'manacore' ), self::top( 'view', 7, 5 ), __( 'بازدید', 'manacore' ) );
		$this->widget_list( __( 'پربارگیری‌شده‌ترین‌های ۷ روز', 'manacore' ), self::top( 'download', 7, 5 ), __( 'دانلود', 'manacore' ) );
		?>
		<p class="manacore-widget-actions">
			<a class="button" href="<?php echo esc_url( Settings::tab_url( 'reports' ) ); ?>"><?php esc_html_e( 'گزارش‌های خرابی لینک', 'manacore' ); ?></a>
			<a class="button" href="<?php echo esc_url( Settings::tab_url( 'general' ) ); ?>"><?php esc_html_e( 'تنظیمات ManaCore', 'manacore' ); ?></a>
		</p>
		<?php
	}

	/**
	 * فهرست کوتاه رتبه‌ای در ویجت پیشخوان.
	 *
	 * @param string $title عنوان فهرست.
	 * @param array  $rows  ردیف‌های آماده از `self::top()`.
	 * @param string $value برچسب ستون شمار.
	 * @return void
	 */
	protected function widget_list( $title, $rows, $value ) {
		if ( ! $rows ) {
			return;
		}
		?>
		<div class="manacore-widget-list">
			<h3>
				<?php echo esc_html( $title ); ?>
				<span><?php echo esc_html( $value ); ?></span>
			</h3>
			<ol>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$edit = current_user_can( 'edit_post', (int) $row['id'] ) ? get_edit_post_link( (int) $row['id'] ) : '';
					?>
					<li>
						<?php if ( $edit ) : ?>
							<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( $row['title'] ); ?></span>
						<?php endif; ?>
						<b><?php echo esc_html( manacore_fa_digits( number_format_i18n( isset( $row['total'] ) ? (int) $row['total'] : 0 ) ) ); ?></b>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}
}

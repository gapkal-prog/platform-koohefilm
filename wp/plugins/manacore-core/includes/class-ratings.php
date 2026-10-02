<?php
/**
 * امتیازدهی کاربران و شمارش بازدید.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ratings
 */
class Ratings {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'wp', array( $this, 'maybe_count_view' ) );
	}

	/**
	 * شناسه‌ی یکتای رأی‌دهنده.
	 *
	 * @return string
	 */
	protected function voter_hash() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			return 'u' . $user_id;
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return 'g' . substr( md5( $ip . $agent . wp_salt() ), 0, 32 );
	}

	/**
	 * ثبت رأی.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @param int $rating  امتیاز ۱ تا ۱۰.
	 * @return array|\WP_Error
	 */
	public function vote( $post_id, $rating ) {
		global $wpdb;

		$post_id = absint( $post_id );
		$rating  = max( 1, min( 10, absint( $rating ) ) );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'manacore_invalid_post', __( 'محتوا یافت نشد.', 'manacore' ), array( 'status' => 404 ) );
		}

		$table = Install::ratings_table();
		$hash  = $this->voter_hash();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, user_id, user_hash, rating, created_at)
				VALUES (%d, %d, %s, %d, %s)
				ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = VALUES(created_at)",
				$post_id,
				get_current_user_id(),
				$hash,
				$rating,
				current_time( 'mysql' )
			)
		);
		// phpcs:enable

		$this->refresh_cache( $post_id );

		return $this->summary( $post_id );
	}

	/**
	 * به‌روزرسانی میانگین در متا برای خواندن سریع.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 */
	public function refresh_cache( $post_id ) {
		global $wpdb;
		$table = Install::ratings_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM {$table} WHERE post_id = %d",
				$post_id
			),
			ARRAY_A
		);
		// phpcs:enable

		$average = $row ? round( (float) $row['avg_rating'], 1 ) : 0;
		$total   = $row ? (int) $row['total'] : 0;

		update_post_meta( $post_id, 'manacore_user_rating', $average );
		update_post_meta( $post_id, 'manacore_user_rating_count', $total );
	}

	/**
	 * خلاصه‌ی امتیاز یک پست.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return array
	 */
	public function summary( $post_id ) {
		return array(
			'average' => (float) get_post_meta( $post_id, 'manacore_user_rating', true ),
			'count'   => (int) get_post_meta( $post_id, 'manacore_user_rating_count', true ),
			'mine'    => $this->user_vote( $post_id ),
		);
	}

	/**
	 * رأی کاربر جاری.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return int
	 */
	public function user_vote( $post_id ) {
		global $wpdb;
		$table = Install::ratings_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rating FROM {$table} WHERE post_id = %d AND user_hash = %s LIMIT 1",
				$post_id,
				$this->voter_hash()
			)
		);
		// phpcs:enable

		return (int) $value;
	}

	/**
	 * شمارش بازدید صفحه‌ی تکی.
	 */
	public function maybe_count_view() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		if ( ! manacore_get_option( 'enable_views', 1 ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$types   = array_merge( manacore_title_post_types(), array( 'episode' ) );
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), $types, true ) ) {
			return;
		}

		$cookie = 'manacore_v_' . $post_id;
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}

		$views = (int) get_post_meta( $post_id, 'manacore_views', true );
		update_post_meta( $post_id, 'manacore_views', $views + 1 );
		$this->log_stat( $post_id, 'view' );

		if ( ! headers_sent() ) {
			setcookie( $cookie, '1', time() + 6 * HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
		}
	}

	/**
	 * ثبت آمار روزانه.
	 *
	 * @param int    $post_id شناسه‌ی پست.
	 * @param string $type    نوع آمار.
	 */
	public function log_stat( $post_id, $type = 'view' ) {
		global $wpdb;
		$table = Install::stats_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, stat_type, stat_date, stat_count)
				VALUES (%d, %s, %s, 1)
				ON DUPLICATE KEY UPDATE stat_count = stat_count + 1",
				$post_id,
				sanitize_key( $type ),
				current_time( 'Y-m-d' )
			)
		);
		// phpcs:enable
	}

	/**
	 * پربازدیدترین‌ها در بازه‌ی زمانی.
	 *
	 * @param int    $days  تعداد روز.
	 * @param int    $limit تعداد نتیجه.
	 * @param string $type  نوع آمار.
	 * @return int[]
	 */
	public function trending( $days = 7, $limit = 10, $type = 'view' ) {
		global $wpdb;
		$table = Install::stats_table();
		$since = gmdate( 'Y-m-d', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$table}
				WHERE stat_type = %s AND stat_date >= %s
				GROUP BY post_id
				ORDER BY SUM(stat_count) DESC
				LIMIT %d",
				sanitize_key( $type ),
				$since,
				absint( $limit )
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}
}

<?php
/**
 * لیست تماشا و علاقه‌مندی کاربران.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Watchlist
 */
class Watchlist {

	use Singleton;

	/**
	 * کلید متای کاربر.
	 */
	const META_KEY = 'manacore_watchlist';

	/**
	 * کلید متای «دیده شده».
	 */
	const WATCHED_KEY = 'manacore_watched';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_shortcode( 'manacore_watchlist', array( $this, 'shortcode' ) );
	}

	/**
	 * دریافت فهرست.
	 *
	 * @param int    $user_id شناسه‌ی کاربر.
	 * @param string $key     کلید متا.
	 * @return int[]
	 */
	public function get( $user_id = 0, $key = self::META_KEY ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$list = get_user_meta( $user_id, $key, true );
		return is_array( $list ) ? array_map( 'intval', $list ) : array();
	}

	/**
	 * تغییر وضعیت یک آیتم.
	 *
	 * @param int    $post_id شناسه‌ی پست.
	 * @param int    $user_id شناسه‌ی کاربر.
	 * @param string $key     کلید متا.
	 * @return array|\WP_Error
	 */
	public function toggle( $post_id, $user_id = 0, $key = self::META_KEY ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$post_id = absint( $post_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'manacore_not_logged_in', __( 'ابتدا وارد حساب کاربری شوید.', 'manacore' ), array( 'status' => 401 ) );
		}
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'manacore_invalid_post', __( 'محتوا یافت نشد.', 'manacore' ), array( 'status' => 404 ) );
		}

		$list  = $this->get( $user_id, $key );
		$index = array_search( $post_id, $list, true );

		if ( false !== $index ) {
			unset( $list[ $index ] );
			$active = false;
		} else {
			$list[] = $post_id;
			$active = true;
		}

		update_user_meta( $user_id, $key, array_values( array_unique( $list ) ) );

		return array(
			'active' => $active,
			'count'  => count( $list ),
		);
	}

	/**
	 * آیا آیتم در فهرست هست؟
	 *
	 * @param int    $post_id شناسه‌ی پست.
	 * @param int    $user_id شناسه‌ی کاربر.
	 * @param string $key     کلید متا.
	 * @return bool
	 */
	public function has( $post_id, $user_id = 0, $key = self::META_KEY ) {
		return in_array( absint( $post_id ), $this->get( $user_id, $key ), true );
	}

	/**
	 * شورت‌کد نمایش لیست تماشا.
	 *
	 * @param array $atts پارامترها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns' => 5,
				'type'    => 'watchlist',
			),
			$atts,
			'manacore_watchlist'
		);

		if ( ! is_user_logged_in() ) {
			return '<p class="manacore-empty-state">' . esc_html__( 'برای دیدن لیست تماشا وارد حساب کاربری شوید.', 'manacore' ) . '</p>';
		}

		$key = 'watched' === $atts['type'] ? self::WATCHED_KEY : self::META_KEY;
		$ids = $this->get( 0, $key );

		if ( empty( $ids ) ) {
			return '<p class="manacore-empty-state">' . esc_html__( 'فهرست شما خالی است.', 'manacore' ) . '</p>';
		}

		$query = new \WP_Query(
			array(
				'post__in'            => $ids,
				'post_type'           => array_merge( manacore_title_post_types(), array( 'episode' ) ),
				'orderby'             => 'post__in',
				'posts_per_page'      => 60,
				'ignore_sticky_posts' => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return '<p class="manacore-empty-state">' . esc_html__( 'فهرست شما خالی است.', 'manacore' ) . '</p>';
		}

		ob_start();
		echo '<div class="manacore-grid-cards" style="--mc-columns:' . esc_attr( absint( $atts['columns'] ) ) . '">';
		while ( $query->have_posts() ) {
			$query->the_post();
			echo Templates::card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		wp_reset_postdata();

		return (string) ob_get_clean();
	}
}

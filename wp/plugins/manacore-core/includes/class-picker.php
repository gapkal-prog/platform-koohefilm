<?php
/**
 * جست‌وجوی آثار برای انتخابگرهای پیشخوان (مجموعه، کانال و …).
 *
 * @package ManaCore\\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Picker
 */
class Picker {

	/**
	 * وضعیت‌های قابل نمایش در انتخابگر (پیش‌نویس و زمان‌بندی‌شده هم مجازند).
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'publish', 'draft', 'pending', 'private', 'future' );
	}

	/**
	 * برچسب فارسی یک نوع محتوا.
	 *
	 * @param string $post_type نوع محتوا.
	 * @return string
	 */
	public static function type_label( $post_type ) {
		$labels = array(
			'movie'   => __( 'فیلم', 'manacore' ),
			'series'  => __( 'سریال', 'manacore' ),
			'anime'   => __( 'انیمه', 'manacore' ),
			'episode' => __( 'قسمت', 'manacore' ),
		);

		return isset( $labels[ $post_type ] ) ? $labels[ $post_type ] : $post_type;
	}

	/**
	 * برچسب فارسی یک وضعیت انتشار.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'publish' => __( 'منتشر شده', 'manacore' ),
			'draft'   => __( 'پیش‌نویس', 'manacore' ),
			'pending' => __( 'در انتظار بازبینی', 'manacore' ),
			'private' => __( 'خصوصی', 'manacore' ),
			'future'  => __( 'زمان‌بندی‌شده', 'manacore' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * نمایش یک اثر به شکل سبک برای پیشخوان.
	 *
	 * @param \WP_Post|int $post اثر.
	 * @return array|null
	 */
	public static function item( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$thumb = get_the_post_thumbnail_url( $post, 'thumbnail' );

		return array(
			'id'           => (int) $post->ID,
			'title'        => get_the_title( $post ),
			'type'         => $post->post_type,
			'type_label'   => self::type_label( $post->post_type ),
			'year'         => Templates::year( $post->ID ),
			'status'       => $post->post_status,
			'status_label' => self::status_label( $post->post_status ),
			'thumb'        => $thumb ? esc_url_raw( $thumb ) : '',
			'edit_url'     => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	/**
	 * جست‌وجوی آثار.
	 *
	 * @param string $term    عبارت جست‌وجو.
	 * @param string $type    نوع محتوا (خالی = همه‌ی نوع‌های عنوان).
	 * @param int    $limit   حداکثر نتیجه.
	 * @param int[]  $exclude شناسه‌های حذف‌شده از نتایج.
	 * @param array  $types   نوع‌های مجاز (پیش‌فرض: نوع‌های عنوان).
	 * @return array[]
	 */
	public static function works( $term = '', $type = '', $limit = 15, $exclude = array(), $types = null ) {
		$types = $types ? $types : manacore_title_post_types();
		if ( '' !== $type && in_array( $type, $types, true ) ) {
			$types = array( $type );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => self::statuses(),
				'posts_per_page'         => min( 30, max( 1, (int) $limit ) ),
				's'                      => trim( (string) $term ),
				'post__not_in'           => array_map( 'absint', (array) $exclude ),
				'orderby'                => '' === trim( (string) $term ) ? 'date' : 'title',
				'order'                  => '' === trim( (string) $term ) ? 'DESC' : 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::item( $post );
		}

		return $items;
	}

	/**
	 * شناسه‌ی معتبر یک پست از نوع‌های مجاز، یا 0.
	 *
	 * @param mixed    $raw   ورودی خام.
	 * @param string[] $types نوع‌های مجاز.
	 * @return int
	 */
	public static function valid_id( $raw, $types = null ) {
		$id    = absint( $raw );
		$types = $types ? $types : manacore_title_post_types();

		if ( $id <= 0 ) {
			return 0;
		}

		return in_array( get_post_type( $id ), $types, true ) ? $id : 0;
	}
}

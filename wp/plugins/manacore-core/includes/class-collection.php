<?php
/**
 * منطق مجموعه‌ها: فهرست مرتب آثار، ترتیب خودکار نمایش و کاور.
 *
 * داده‌ها:
 *  - manacore_collection_items : فهرست مرتب شناسه‌ی آثار (آرایه‌ی عدد). ترتیب
 *    همان ترتیبی است که مدیر در پیشخوان چیده است.
 *  - manacore_collection_sort  : ترتیب خودکار (manual|newest|oldest|rating|title)
 *    که فقط وقتی فهرست دستی خالی است اعمال می‌شود.
 *  - manacore_collection_cover : نشانی تصویر کاور (در نبود آن، تصویر شاخص).
 *
 * @package ManaCore\\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Collection
 */
class Collection {

	/**
	 * حداکثر تعداد آثار هر مجموعه.
	 */
	const MAX_ITEMS = 200;

	/**
	 * ترتیب‌های مجاز نمایش خودکار.
	 *
	 * @return string[]
	 */
	public static function sorts() {
		return array( 'manual', 'newest', 'oldest', 'rating', 'title' );
	}

	/**
	 * پاک‌سازی یک فهرست خام به شناسه‌های معتبر آثار.
	 *
	 * ترتیب ورودی حفظ می‌شود، تکراری‌ها حذف می‌شوند، و فقط نوع‌های عنوان
	 * پذیرفته می‌شوند. ورودی می‌تواند آرایه یا رشته‌ی جداشده با ویرگول باشد.
	 *
	 * @param mixed    $raw   ورودی خام.
	 * @param callable $check اختیاری: تابع اعتبارسنجی هر شناسه (پیش‌فرض: نوع عنوان).
	 * @return int[]
	 */
	public static function sanitize_items( $raw, $check = null ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$check = $check ? $check : array( __CLASS__, 'is_work' );
		$clean = array();

		foreach ( $raw as $value ) {
			$id = absint( $value );
			if ( $id <= 0 || in_array( $id, $clean, true ) ) {
				continue;
			}
			if ( ! call_user_func( $check, $id ) ) {
				continue;
			}
			$clean[] = $id;
			if ( count( $clean ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * آیا شناسه، اثری با نوع عنوان است؟
	 *
	 * @param int $id شناسه.
	 * @return bool
	 */
	public static function is_work( $id ) {
		return in_array( get_post_type( $id ), manacore_title_post_types(), true );
	}

	/**
	 * فهرست دستی آثار یک مجموعه، به ترتیب.
	 *
	 * داده‌های قدیمی (رشته‌ی جداشده با ویرگول) هم خوانده می‌شوند.
	 *
	 * @param int $collection_id شناسه‌ی مجموعه.
	 * @return int[]
	 */
	public static function items( $collection_id ) {
		$stored = get_post_meta( $collection_id, 'manacore_collection_items', true );

		if ( is_array( $stored ) ) {
			return array_values( array_filter( array_map( 'absint', $stored ) ) );
		}
		if ( is_string( $stored ) && '' !== $stored ) {
			return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $stored ) ) ) );
		}

		return array();
	}

	/**
	 * ترتیب خودکار نمایش یک مجموعه.
	 *
	 * @param int $collection_id شناسه‌ی مجموعه.
	 * @return string
	 */
	public static function sort( $collection_id ) {
		$sort = (string) get_post_meta( $collection_id, 'manacore_collection_sort', true );
		return in_array( $sort, self::sorts(), true ) ? $sort : 'manual';
	}

	/**
	 * آرگومان‌های WP_Query برای یک ترتیب خودکار.
	 *
	 * @param string $sort یکی از sorts().
	 * @return array
	 */
	public static function sort_args( $sort ) {
		switch ( $sort ) {
			case 'newest':
				return array(
					'orderby' => 'date',
					'order'   => 'DESC',
				);

			case 'oldest':
				return array(
					'orderby' => 'date',
					'order'   => 'ASC',
				);

			case 'title':
				return array(
					'orderby' => 'title',
					'order'   => 'ASC',
				);

			case 'rating':
				return array(
					'meta_key' => 'manacore_imdb_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'  => 'meta_value_num',
					'order'    => 'DESC',
				);

			default:
				return array();
		}
	}

	/**
	 * نشانی کاور یک مجموعه: کاور اختصاصی، سپس تصویر شاخص.
	 *
	 * @param int    $collection_id شناسه‌ی مجموعه.
	 * @param string $size          اندازه‌ی تصویر شاخص.
	 * @return string
	 */
	public static function cover( $collection_id, $size = 'large' ) {
		$cover = (string) get_post_meta( $collection_id, 'manacore_collection_cover', true );
		if ( '' !== $cover ) {
			return esc_url( $cover );
		}

		$thumb = get_the_post_thumbnail_url( $collection_id, $size );
		return $thumb ? esc_url( $thumb ) : '';
	}
}

<?php
/**
 * عوامل اثر (کارگردان، نویسنده، تهیه‌کننده، آهنگساز، بازیگر) و پیوند آن‌ها به CPT عوامل.
 *
 * ساختار داده:
 *  - متای نقش‌های تیمی (manacore_director و ...) به‌صورت JSON فهرست
 *    `[ { "name": "...", "person_id": 12 }, ... ]` ذخیره می‌شود؛ person_id = 0
 *    یعنی نام آزاد (عامل ثبت‌نشده).
 *  - داده‌های قدیمی و ایمپورتر (`manacore-sources`) همچنان متن ساده‌ی جداشده با
 *    ویرگول‌اند؛ همه‌ی خواندن‌ها هر دو قالب را می‌پذیرند و نیازی به مهاجرت نیست.
 *
 * @package ManaCore\\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Crew
 */
class Crew {

	/**
	 * فیلدهای نقش تیمی (کلید متا => نقش).
	 */
	const ROLE_FIELDS = array(
		'manacore_director' => 'director',
		'manacore_writer'   => 'writer',
		'manacore_producer' => 'producer',
		'manacore_composer' => 'composer',
	);

	/**
	 * فیلد بازیگران (ریپیتر).
	 */
	const CAST_FIELD = 'manacore_cast';

	/**
	 * حداکثر تعداد عامل در هر نقش.
	 */
	const MAX_ITEMS = 50;

	/**
	 * نام ترم‌های تاکسونومی person_role برای هر نقش (برای فیلتر جست‌وجو).
	 *
	 * @return array<string,string>
	 */
	public static function role_terms() {
		return array(
			'director' => 'کارگردان',
			'writer'   => 'نویسنده',
			'producer' => 'تهیه‌کننده',
			'composer' => 'آهنگساز',
			'cast'     => 'بازیگر',
		);
	}

	/**
	 * تجزیه‌ی ورودی (JSON، فهرست آماده یا متن ساده‌ی جداشده با ویرگول) به فهرست یکدست.
	 *
	 * @param mixed $raw ورودی.
	 * @return array<int,array{name:string,person_id:int}>
	 */
	public static function parse( $raw ) {
		return self::normalize( self::raw_items( $raw ) );
	}

	/**
	 * ورودی خام را به فهرست آیتم‌های خام (رشته یا آرایه) تبدیل می‌کند.
	 *
	 * @param mixed $raw ورودی.
	 * @return array
	 */
	protected static function raw_items( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) ) {
			return array();
		}

		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}

		if ( '[' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// متن قدیمی: «علی، رضا» یا «علی، رضا».
		$names = preg_split( '/[,،]+/u', $raw );

		return is_array( $names ) ? $names : array();
	}

	/**
	 * پاک‌سازی یک فهرست خام: نام خالی، تکرار و شناسه‌ی نامعتبر.
	 *
	 * @param array $items      فهرست خام.
	 * @param bool  $keep_linked اگر true باشد، ردیفِ بی‌نام ولی دارای شناسه نگه داشته می‌شود
	 *                           تا نام آن در ذخیره از عنوان عامل پر شود.
	 * @return array<int,array{name:string,person_id:int}>
	 */
	protected static function normalize( array $items, $keep_linked = false ) {
		$out  = array();
		$seen = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$name      = isset( $item['name'] ) ? (string) $item['name'] : '';
				$person_id = isset( $item['person_id'] ) ? absint( $item['person_id'] ) : 0;
			} else {
				$name      = is_scalar( $item ) ? (string) $item : '';
				$person_id = 0;
			}

			$name = trim( wp_strip_all_tags( $name ) );
			if ( '' === $name && ! ( $keep_linked && $person_id ) ) {
				continue;
			}

			$key = $person_id ? 'id:' . $person_id : 'name:' . strtolower( $name );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$out[] = array(
				'name'      => $name,
				'person_id' => $person_id,
			);

			if ( count( $out ) >= self::MAX_ITEMS ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * شناسه‌ی عامل معتبر (پست نوع person) یا صفر.
	 *
	 * @param mixed $raw ورودی.
	 * @return int
	 */
	public static function valid_person_id( $raw ) {
		$id = absint( $raw );

		return ( $id && 'person' === get_post_type( $id ) ) ? $id : 0;
	}

	/**
	 * پاک‌سازی فهرست پیش از ذخیره: شناسه فقط برای پست‌های «عامل» معتبر می‌ماند و
	 * نام ردیف‌های پیوندخورده‌ی بی‌نام از عنوان عامل پر می‌شود.
	 *
	 * @param mixed $raw ورودی JSON یا فهرست.
	 * @return array<int,array{name:string,person_id:int}>
	 */
	public static function sanitize( $raw ) {
		$clean = array();

		foreach ( self::normalize( self::raw_items( $raw ), true ) as $item ) {
			$person_id = self::valid_person_id( $item['person_id'] );
			$name      = sanitize_text_field( $item['name'] );

			if ( $person_id && '' === $name ) {
				$name = (string) get_the_title( $person_id );
			}

			if ( '' === $name ) {
				continue;
			}

			$clean[] = array(
				'name'      => $name,
				'person_id' => $person_id,
			);
		}

		return $clean;
	}

	/**
	 * تبدیل فهرست به JSON یکنواخت برای ذخیره.
	 *
	 * @param array $items فهرست پاک‌شده.
	 * @return string
	 */
	public static function encode( array $items ) {
		return (string) wp_json_encode( array_values( $items ), JSON_UNESCAPED_UNICODE );
	}

	/**
	 * نام‌ها به‌صورت متن ساده (برای SEO و خروجی‌های متنی).
	 *
	 * @param array $items فهرست.
	 * @return string[]
	 */
	public static function names( array $items ) {
		return array_values(
			array_map(
				static function ( $item ) {
					return (string) $item['name'];
				},
				$items
			)
		);
	}

	/**
	 * لینک یک عامل به صفحه‌ی او؛ فقط برای عامل منتشرشده، وگرنه نام ساده.
	 *
	 * @param array $item عامل.
	 * @return string HTML امن.
	 */
	public static function link_html( array $item ) {
		$name      = esc_html( (string) $item['name'] );
		$person_id = (int) $item['person_id'];

		if ( $person_id && 'person' === get_post_type( $person_id ) && 'publish' === get_post_status( $person_id ) ) {
			$url = get_permalink( $person_id );
			if ( $url ) {
				return '<a class="manacore-person-link" href="' . esc_url( $url ) . '">' . $name . '</a>';
			}
		}

		return $name;
	}

	/**
	 * فهرست HTML نام‌های یک نقش برای یک اثر.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $role    کلید نقش (director، writer، producer، composer، cast).
	 * @return string
	 */
	public static function html( $post_id, $role ) {
		$items = self::items( $post_id, $role );
		if ( ! $items ) {
			return '';
		}

		$parts = array_map( array( __CLASS__, 'link_html' ), $items );

		return implode( '، ', $parts );
	}

	/**
	 * فهرست عوامل یک نقش برای اثر.
	 *
	 * @param int    $post_id شناسه‌ی اثر.
	 * @param string $role    کلید نقش.
	 * @return array<int,array{name:string,person_id:int}>
	 */
	public static function items( $post_id, $role ) {
		$key = array_search( $role, self::ROLE_FIELDS, true );
		if ( false !== $key ) {
			return self::parse( get_post_meta( $post_id, $key, true ) );
		}

		if ( 'cast' === $role ) {
			return self::cast( $post_id );
		}

		return array();
	}

	/**
	 * بازیگران یک اثر (از ریپیتر manacore_cast) با نام، نقش و شناسه.
	 *
	 * @param int $post_id شناسه‌ی اثر.
	 * @return array<int,array<string,mixed>>
	 */
	public static function cast( $post_id ) {
		$cast = get_post_meta( $post_id, self::CAST_FIELD, true );
		$cast = is_string( $cast ) ? json_decode( $cast, true ) : $cast;

		if ( ! is_array( $cast ) ) {
			return array();
		}

		$out = array();
		foreach ( $cast as $row ) {
			if ( ! is_array( $row ) || '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
				continue;
			}

			$person_id = self::valid_person_id( $row['person_id'] ?? 0 );

			$out[] = array(
				'name'      => trim( wp_strip_all_tags( (string) $row['name'] ) ),
				'person_id' => $person_id,
				'character' => isset( $row['character'] ) ? trim( wp_strip_all_tags( (string) $row['character'] ) ) : '',
				'photo'     => isset( $row['photo'] ) ? esc_url_raw( (string) $row['photo'] ) : '',
			);
		}

		return $out;
	}

	/**
	 * تصویر عامل: تصویر شاخص، سپس فیلد manacore_person_photo.
	 *
	 * @param int $person_id شناسه‌ی عامل.
	 * @return string
	 */
	public static function photo( $person_id ) {
		$person_id = absint( $person_id );
		if ( ! $person_id ) {
			return '';
		}

		$thumb = get_the_post_thumbnail_url( $person_id, 'thumbnail' );
		if ( $thumb ) {
			return (string) $thumb;
		}

		return (string) get_post_meta( $person_id, 'manacore_person_photo', true );
	}

	/**
	 * خلاصه‌ی یک عامل برای جست‌وجو و نمایش.
	 *
	 * @param int  $person_id   شناسه‌ی عامل.
	 * @param bool $role_match  آیا با نقش جست‌وجو هم‌خوان است.
	 * @return array<string,mixed>
	 */
	public static function summary( $person_id, $role_match = false ) {
		$person_id = absint( $person_id );
		$roles     = wp_get_post_terms( $person_id, 'person_role', array( 'fields' => 'names' ) );

		return array(
			'id'         => $person_id,
			'name'       => (string) get_the_title( $person_id ),
			'english'    => (string) get_post_meta( $person_id, 'manacore_person_english', true ),
			'original'   => (string) get_post_meta( $person_id, 'manacore_person_original_name', true ),
			'birth_year' => (int) get_post_meta( $person_id, 'manacore_person_birth_year', true ),
			'birthplace' => (string) get_post_meta( $person_id, 'manacore_person_birthplace', true ),
			'roles'      => is_wp_error( $roles ) ? array() : array_values( $roles ),
			'photo'      => self::photo( $person_id ),
			'url'        => (string) get_permalink( $person_id ),
			'role_match' => (bool) $role_match,
		);
	}

	/**
	 * جست‌وجوی عوامل ثبت‌شده.
	 *
	 * ابتدا عواملی که نقش مورد نظر را دارند می‌آیند (اولویت نقش)، سپس بقیه تا سقف
	 * `$limit`. جست‌وجو روی عنوان (نام فارسی)، نام لاتین و نام اصلی انجام می‌شود.
	 *
	 * @param string $term  عبارت جست‌وجو.
	 * @param string $role  کلید نقش یا خالی.
	 * @param int    $limit حداکثر نتیجه.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( $term, $role = '', $limit = 10 ) {
		$term  = trim( sanitize_text_field( (string) $term ) );
		$limit = min( 20, max( 1, (int) $limit ) );
		$roles     = self::role_terms();
		$term_name = isset( $roles[ $role ] ) && taxonomy_exists( 'person_role' ) ? $roles[ $role ] : '';

		$matched = self::find_ids( $term, $term_name, $limit, array() );
		$rest    = $limit - count( $matched );
		$others  = array();

		if ( '' !== $term_name && $rest > 0 ) {
			$others = self::find_ids( $term, '', $rest, $matched );
		}

		$items = array();
		foreach ( $matched as $id ) {
			$items[] = self::summary( $id, '' !== $term_name );
		}
		foreach ( $others as $id ) {
			$items[] = self::summary( $id, false );
		}

		return $items;
	}

	/**
	 * شناسه‌های عاملِ منطبق با جست‌وجو (عنوان، نام لاتین، نام اصلی).
	 *
	 * @param string $term    عبارت.
	 * @param string $role    نام ترم نقش یا خالی.
	 * @param int    $limit   سقف.
	 * @param int[]  $exclude شناسه‌های حذف‌شده.
	 * @return int[]
	 */
	protected static function find_ids( $term, $role, $limit, array $exclude ) {
		$base = array(
			'post_type'        => 'person',
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'      => $limit,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'post__not_in'     => array_map( 'absint', $exclude ),
		);

		if ( '' !== $role ) {
			$base['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- سقف کوچک و ادمین.
				array(
					'taxonomy' => 'person_role',
					'field'    => 'name',
					'terms'    => $role,
				),
			);
		}

		if ( '' === $term ) {
			return array_map( 'absint', (array) get_posts( $base ) );
		}

		$by_title = (array) get_posts( $base + array( 's' => $term ) );

		$by_meta = (array) get_posts(
			$base + array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- سقف کوچک و ادمین.
					'relation' => 'OR',
					array(
						'key'     => 'manacore_person_english',
						'value'   => $term,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'manacore_person_original_name',
						'value'   => $term,
						'compare' => 'LIKE',
					),
				),
			)
		);

		$ids = array_values( array_unique( array_map( 'absint', array_merge( $by_title, $by_meta ) ) ) );

		return array_slice( $ids, 0, $limit );
	}
}

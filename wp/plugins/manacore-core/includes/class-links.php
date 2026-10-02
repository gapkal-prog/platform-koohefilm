<?php
/**
 * مدیریت گروه‌های لینک دانلود و پخش.
 *
 * ساختار داده در متای manacore_links (JSON):
 * [
 *   {
 *     "id": "grp_xxx",
 *     "title": "فصل ۱ - کیفیت ۱۰۸۰",
 *     "season": 1,
 *     "quality": "1080p",
 *     "language": "sub_fa",
 *     "encoder": "PSA",
 *     "size": "1.2GB",
 *     "note": "",
 *     "premium": false,
 *     "items": [
 *       { "id":"lnk_x", "label":"قسمت ۱", "episode":1, "url":"https://...",
 *         "type":"direct", "size":"350MB", "quality":"", "language":"", "note":"" }
 *     ]
 *   }
 * ]
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Links
 */
class Links {

	/**
	 * کلید متا.
	 */
	const META_KEY = 'manacore_links';

	/**
	 * دریافت گروه‌های لینک یک پست.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return array
	 */
	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $raw ) ) {
			return array();
		}
		if ( is_array( $raw ) ) {
			$data = $raw;
		} else {
			$data = json_decode( (string) $raw, true );
		}
		if ( ! is_array( $data ) ) {
			return array();
		}
		return self::normalize( $data );
	}

	/**
	 * ذخیره‌ی گروه‌ها.
	 *
	 * @param int   $post_id شناسه‌ی پست.
	 * @param array $groups  گروه‌ها.
	 * @return bool
	 */
	public static function save( $post_id, $groups ) {
		$groups = self::sanitize( $groups );
		if ( empty( $groups ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return true;
		}
		$json = wp_json_encode( $groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return (bool) update_post_meta( $post_id, self::META_KEY, wp_slash( $json ) );
	}

	/**
	 * نرمال‌سازی ساختار (تکمیل کلیدهای غایب).
	 *
	 * @param array $groups گروه‌ها.
	 * @return array
	 */
	public static function normalize( array $groups ) {
		$out = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$normalized = wp_parse_args(
				$group,
				array(
					'id'       => self::uid( 'grp' ),
					'title'    => '',
					'season'   => '',
					'quality'  => '',
					'language' => '',
					'encoder'  => '',
					'size'     => '',
					'note'     => '',
					'premium'  => false,
					'items'    => array(),
				)
			);

			$items = array();
			foreach ( (array) $normalized['items'] as $item ) {
				if ( ! is_array( $item ) || empty( $item['url'] ) ) {
					continue;
				}
				$items[] = wp_parse_args(
					$item,
					array(
						'id'       => self::uid( 'lnk' ),
						'label'    => '',
						'episode'  => '',
						'url'      => '',
						'type'     => 'direct',
						'size'     => '',
						'quality'  => '',
						'language' => '',
						'note'     => '',
					)
				);
			}
			$normalized['items'] = $items;
			$out[]               = $normalized;
		}
		return $out;
	}

	/**
	 * پاک‌سازی امن داده‌های ورودی.
	 *
	 * @param mixed $groups گروه‌ها.
	 * @return array
	 */
	public static function sanitize( $groups ) {
		if ( is_string( $groups ) ) {
			$groups = json_decode( $groups, true );
		}
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$clean = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$items = array();
			foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$url = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
				if ( '' === $url ) {
					continue;
				}
				// magnet: و لینک‌های عادی هر دو پشتیبانی می‌شوند.
				$url = 0 === strpos( $url, 'magnet:' )
					? esc_url_raw( $url, array( 'magnet' ) )
					: esc_url_raw( $url );

				if ( '' === $url ) {
					continue;
				}

				$items[] = array(
					'id'       => sanitize_key( $item['id'] ?? '' ) ?: self::uid( 'lnk' ),
					'label'    => sanitize_text_field( $item['label'] ?? '' ),
					'episode'  => '' === ( $item['episode'] ?? '' ) ? '' : absint( $item['episode'] ),
					'url'      => $url,
					'type'     => sanitize_key( $item['type'] ?? 'direct' ),
					'size'     => sanitize_text_field( $item['size'] ?? '' ),
					'quality'  => sanitize_text_field( $item['quality'] ?? '' ),
					'language' => sanitize_key( $item['language'] ?? '' ),
					'note'     => sanitize_text_field( $item['note'] ?? '' ),
				);
			}

			$title = sanitize_text_field( $group['title'] ?? '' );
			if ( '' === $title && empty( $items ) ) {
				continue;
			}

			$clean[] = array(
				'id'       => sanitize_key( $group['id'] ?? '' ) ?: self::uid( 'grp' ),
				'title'    => $title,
				'season'   => '' === ( $group['season'] ?? '' ) ? '' : absint( $group['season'] ),
				'quality'  => sanitize_text_field( $group['quality'] ?? '' ),
				'language' => sanitize_key( $group['language'] ?? '' ),
				'encoder'  => sanitize_text_field( $group['encoder'] ?? '' ),
				'size'     => sanitize_text_field( $group['size'] ?? '' ),
				'note'     => sanitize_text_field( $group['note'] ?? '' ),
				'premium'  => ! empty( $group['premium'] ),
				'items'    => $items,
			);
		}

		return $clean;
	}

	/**
	 * تولید شناسه‌ی یکتا.
	 *
	 * @param string $prefix پیشوند.
	 * @return string
	 */
	public static function uid( $prefix = 'id' ) {
		return $prefix . '_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
	}

	/**
	 * گروه‌بندی لینک‌ها بر اساس فصل.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return array<int|string,array>
	 */
	public static function by_season( $post_id ) {
		$groups = self::get( $post_id );
		$out    = array();
		foreach ( $groups as $group ) {
			$season          = '' === $group['season'] ? 0 : (int) $group['season'];
			$out[ $season ][] = $group;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * شمارش کل لینک‌های یک پست.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return int
	 */
	public static function count( $post_id ) {
		$total = 0;
		foreach ( self::get( $post_id ) as $group ) {
			$total += count( $group['items'] );
		}
		return $total;
	}

	/**
	 * برچسب خوانای کیفیت.
	 *
	 * @param string $key کلید کیفیت.
	 * @return string
	 */
	public static function quality_label( $key ) {
		$list = manacore_qualities();
		return isset( $list[ $key ] ) ? $list[ $key ] : $key;
	}

	/**
	 * برچسب خوانای زبان.
	 *
	 * @param string $key کلید زبان.
	 * @return string
	 */
	public static function language_label( $key ) {
		$list = manacore_languages();
		return isset( $list[ $key ] ) ? $list[ $key ] : $key;
	}

	/**
	 * برچسب خوانای نوع لینک.
	 *
	 * @param string $key کلید نوع.
	 * @return string
	 */
	public static function type_label( $key ) {
		$list = manacore_link_types();
		return isset( $list[ $key ] ) ? $list[ $key ] : $key;
	}
}

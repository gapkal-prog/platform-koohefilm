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
						'encoder'  => '',
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
	 * یافتن یک ردیف لینک با متن گروهش.
	 *
	 * مقصدهایی مثل پل دانلود و مسیر امضاشده فقط «شناسه‌ی لینک» را
	 * می‌شناسند؛ برای نمایش کارت (کیفیت، حجم، زبان) به همان ردیف و
	 * تنظیم‌های گروهش با هم نیاز دارند. این متد تنها نقطه‌ی جست‌وجو
	 * است تا معنای «یک لینک» همه‌جا یکی بماند.
	 *
	 * @param int    $post_id شناسه‌ی نوشته.
	 * @param string $item_id شناسه‌ی ردیف لینک.
	 * @return array|null کلیدها: id، url، type، label، episode، size، quality،
	 *                    language، note و متن گروه (group_id، group_title،
	 *                    season، premium).
	 */
	public static function find( $post_id, $item_id ) {
		$item_id = sanitize_key( (string) $item_id );

		if ( '' === $item_id ) {
			return null;
		}

		foreach ( self::get( $post_id ) as $group ) {
			foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) || (string) ( $item['id'] ?? '' ) !== $item_id ) {
					continue;
				}

				$url = trim( (string) ( $item['url'] ?? '' ) );

				if ( '' === $url ) {
					return null;
				}

				return array(
					'id'          => (string) $item['id'],
					'url'         => $url,
					'type'        => (string) self::value( $item, $group, 'type' ),
					'label'       => trim( (string) ( $item['label'] ?? '' ) ),
					'episode'     => trim( (string) ( $item['episode'] ?? '' ) ),
					'note'        => trim( (string) ( $item['note'] ?? '' ) ),
					'group_id'    => (string) ( $group['id'] ?? '' ),
					'group_title' => trim( (string) ( $group['title'] ?? '' ) ),
					'season'      => trim( (string) ( $group['season'] ?? '' ) ),
					'premium'     => ! empty( $group['premium'] ),
					/* ردیف بر گروه مقدم است؛ خالی‌بودن ردیف یعنی «مثل گروه». */
					'quality'     => self::value( $item, $group, 'quality' ),
					'language'    => self::value( $item, $group, 'language' ),
					'size'        => self::value( $item, $group, 'size' ),
					'encoder'     => self::value( $item, $group, 'encoder' ),
				);
			}
		}

		return null;
	}

	/**
	 * مقدار یک صفت، با افتادن به مقدار همان صفت در گروه.
	 *
	 * ردیف‌های لینک عمداً می‌توانند «کیفیت» و «حجم» را خالی بگذارند تا
	 * از گروه ارث ببرند؛ این متد همان قاعده را در یک جا نگه می‌دارد.
	 *
	 * @param array  $item  ردیف لینک.
	 * @param array  $group گروه لینک.
	 * @param string $key   نام صفت.
	 * @return string
	 */
	public static function value( $item, $group, $key ) {
		$value = trim( (string) ( $item[ $key ] ?? '' ) );

		return '' !== $value ? $value : trim( (string) ( $group[ $key ] ?? '' ) );
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
					'encoder'  => sanitize_text_field( $item['encoder'] ?? '' ),
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
	 * کلید یک گزینه‌ی پخش: کیفیت + زبان/دوبله + انکودر.
	 *
	 * همین کلید هم در پیوند «پخش» جدول دانلود (`quality=`) و هم در فهرست
	 * کیفیت صفحه‌ی پخش به کار می‌رود تا دو سوی یک پیوند هم‌دیگر را پیدا
	 * کنند. قسمتی که دو لینک با کیفیت یکسان اما زبان یا انکودر متفاوت دارد
	 * (مثلاً ۱۰۸۰p دوبله و ۱۰۸۰p زیرنویس) دو گزینه‌ی جدا می‌گیرد. نبودِ
	 * کیفیت، نوع لینک را جای آن می‌نشاند تا گزینه بی‌نام نماند.
	 *
	 * @param string $quality  کیفیت (کلید یا متن آزاد).
	 * @param string $type     نوع لینک (برای جای‌گزینی نبود کیفیت).
	 * @param string $language کلید زبان/دوبله.
	 * @param string $encoder  نام انکودر.
	 * @return string
	 */
	public static function variant_key( $quality, $type = '', $language = '', $encoder = '' ) {
		$base = trim( (string) $quality );

		if ( '' === $base && '' !== trim( (string) $type ) ) {
			$base = self::type_label( (string) $type );
		}

		$parts = array_filter(
			array(
				$base,
				'' !== trim( (string) $language ) ? self::language_label( (string) $language ) : '',
				trim( (string) $encoder ),
			),
			static function ( $part ) {
				return '' !== $part;
			}
		);

		return implode( ' · ', $parts );
	}

	/**
	 * همه‌ی گزینه‌های پخشِ یک قسمت، از هر دو محل ثبت لینک.
	 *
	 * یک قسمت می‌تواند لینکش را (الف) روی ردیف شماره‌دارِ خودِ سریال داشته
	 * باشد، یا (ب) روی پست جدای همان قسمت. صفحه‌ی پخش باید «همه‌ی کیفیت‌های
	 * همان قسمت» را ببیند، پس هر دو منبع یک‌جا خوانده می‌شوند. نتیجه، فهرست
	 * تخت است و هر ردیف با `url`، `type`، `quality`، `language`، `encoder`،
	 * `size` و `premium` (با ارث‌بری از گروه) برمی‌گردد.
	 *
	 * @param int $series_id شناسه‌ی سریال/انیمه.
	 * @param int $season    شماره‌ی فصل (۰ = هر فصل).
	 * @param int $episode   شماره‌ی قسمت.
	 * @return array<int,array>
	 */
	public static function episode_variants( $series_id, $season, $episode ) {
		$series_id = (int) $series_id;
		$season    = max( 0, (int) $season );
		$episode   = max( 0, (int) $episode );
		$out       = array();

		if ( ! $series_id || ! $episode ) {
			return $out;
		}

		foreach ( self::numbered_groups( $series_id ) as $row_season => $rows ) {
			if ( $season && $season !== (int) $row_season ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( (int) $row['episode'] === $episode ) {
					$out = array_merge( $out, self::flatten( $row ) );
				}
			}
		}

		$meta_query = array(
			array(
				'key'   => 'manacore_parent_title',
				'value' => $series_id,
			),
			array(
				'key'   => 'manacore_episode_number',
				'value' => $episode,
			),
		);

		if ( $season ) {
			$meta_query[] = array(
				'key'   => 'manacore_season_number',
				'value' => $season,
			);
		}

		$episodes = get_posts(
			array(
				'post_type'      => 'episode',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		foreach ( (array) $episodes as $episode_id ) {
			foreach ( self::get( (int) $episode_id ) as $group ) {
				$out = array_merge( $out, self::flatten( $group ) );
			}
		}

		return $out;
	}

	/**
	 * همه‌ی ردیف‌های لینک یک پست به‌صورت تخت (برای صفحه‌ی پخش و ابزارها).
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return array<int,array>
	 */
	public static function post_rows( $post_id ) {
		$out = array();

		foreach ( self::get( (int) $post_id ) as $group ) {
			$out = array_merge( $out, self::flatten( $group ) );
		}

		return $out;
	}

	/**
	 * تخت‌کردن یک گروه به ردیف‌های لینک با ارث‌بری کامل از گروه.
	 *
	 * @param array $group گروه لینک.
	 * @return array<int,array>
	 */
	public static function flatten( $group ) {
		$out = array();

		foreach ( (array) ( $group['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || '' === trim( (string) ( $item['url'] ?? '' ) ) ) {
				continue;
			}

			$out[] = array(
				'url'      => trim( (string) $item['url'] ),
				'type'     => '' !== trim( (string) ( $item['type'] ?? '' ) ) ? (string) $item['type'] : 'direct',
				'label'    => trim( (string) ( $item['label'] ?? '' ) ),
				'quality'  => self::value( $item, $group, 'quality' ),
				'language' => self::value( $item, $group, 'language' ),
				'encoder'  => self::value( $item, $group, 'encoder' ),
				'size'     => self::value( $item, $group, 'size' ),
				'premium'  => ! empty( $group['premium'] ),
				'owner'    => (int) ( $group['owner'] ?? 0 ),
				'episode'  => (int) ( $group['episode'] ?? 0 ),
			);
		}

		return $out;
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
	 * بسته‌های کامل فصل یک سریال، به تفکیک فصل.
	 *
	 * فقط آیتم‌های «بی‌شماره» می‌مانند؛ آیتمی که شماره‌ی قسمت دارد، ردیف
	 * قسمت است و در `numbered_groups()` می‌آید. گروهی که همه‌ی آیتم‌هایش
	 * شماره‌دار باشد، از فهرست بسته‌ها کنار می‌رود تا دوبار نشان داده نشود.
	 *
	 * @param int $post_id شناسه‌ی سریال/انیمه.
	 * @return array<int,array>
	 */
	public static function season_packs( $post_id ) {
		$out = array();

		foreach ( self::get( $post_id ) as $group ) {
			$items = array_values(
				array_filter(
					(array) $group['items'],
					static function ( $item ) {
						return (int) ( $item['episode'] ?? 0 ) <= 0;
					}
				)
			);

			if ( ! $items ) {
				continue;
			}

			$group['items'] = $items;
			$season         = '' === $group['season'] ? 0 : (int) $group['season'];
			$out[ $season ][] = $group;
		}

		ksort( $out );

		return $out;
	}

	/**
	 * ردیف‌های قسمتِ ثبت‌شده روی خودِ پست سریال، به تفکیک فصل.
	 *
	 * مدیر در متاباکس «لینک‌های دانلود و پخش» برای هر آیتم می‌تواند شماره‌ی
	 * قسمت بدهد (و ورود گروهی هم همین را ثبت می‌کند). هر آیتمِ شماره‌دار به
	 * یک گروه مجازی تبدیل می‌شود که فقط همان یک آیتم را دارد؛ مالک آن خودِ
	 * سریال است (امضا و پخش روی همان می‌نشیند). کیفیت، حجم و زبانِ خودِ
	 * آیتم بر مقادیر گروه مقدم‌اند؛ نبودنشان یعنی ارث‌بری از گروه.
	 *
	 * @param int $post_id شناسه‌ی سریال/انیمه.
	 * @return array<int,array>
	 */
	public static function numbered_groups( $post_id ) {
		$post_id = (int) $post_id;
		$out     = array();

		if ( ! $post_id ) {
			return $out;
		}

		foreach ( self::get( $post_id ) as $group ) {
			$season = '' === $group['season'] ? 0 : (int) $group['season'];

			foreach ( (array) $group['items'] as $item ) {
				$number = (int) ( $item['episode'] ?? 0 );

				if ( $number <= 0 ) {
					continue;
				}

				$row             = $group;
				$row['items']    = array( $item );
				$row['quality']  = '' !== trim( (string) $item['quality'] ) ? (string) $item['quality'] : (string) $group['quality'];
				$row['size']     = '' !== trim( (string) $item['size'] ) ? (string) $item['size'] : (string) $group['size'];
				$row['language'] = '' !== trim( (string) $item['language'] ) ? (string) $item['language'] : (string) $group['language'];
				$row['encoder']  = self::value( $item, $group, 'encoder' );
				$row['owner']    = $post_id;
				$row['episode']  = $number;
				$row['episode_label'] = '';

				$out[ $season ][] = $row;
			}
		}

		foreach ( $out as $season => $rows ) {
			usort(
				$rows,
				static function ( $a, $b ) {
					return $a['episode'] - $b['episode'];
				}
			);
			$out[ $season ] = $rows;
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
	 * جمع شمارش لینک‌های یک ساختار فصل‌بندی‌شده.
	 *
	 * روی خروجی `by_season()` یا `episode_groups()` کار می‌کند؛ چون
	 * «باکس دانلود» ممکن است از دو منبع (لینک‌های خودِ اثر و لینک‌های
	 * قسمت‌ها) پر شود و شمارش سرصفحه باید مجموع واقعیِ ردیف‌های رندرشده
	 * باشد، نه فقط لینک‌های خودِ اثر.
	 *
	 * @param array $by_season گروه‌ها به تفکیک فصل.
	 * @return int
	 */
	public static function total( $by_season ) {
		$total = 0;

		foreach ( (array) $by_season as $groups ) {
			foreach ( (array) $groups as $group ) {
				$total += count( (array) ( $group['items'] ?? array() ) );
			}
		}

		return $total;
	}

	/**
	 * گروه‌های لینک قسمت‌های یک سریال/انیمه، به تفکیک فصل.
	 *
	 * روش متعارف سایت‌های سریالی این است که لینک هر قسمت روی پست همان
	 * قسمت ثبت شود. باکس دانلود سریالی باید همه‌ی آن‌ها را یک‌جا نشان
	 * دهد، وگرنه باکسِ سریالِ بدون لینک روی خودش خالی می‌ماند.
	 *
	 * هر گروه با سه کلید کمکی برمی‌گردد که فقط هنگام رندر معنا دارند:
	 *   • `owner`   → شناسه‌ی قسمت (امضای دانلود و شمارش روی همان است)،
	 *   • `episode` → شماره‌ی قسمت برای نشان ردیف،
	 *   • `episode_label` → عنوان قسمت (اگر مدیر نوشته باشد).
	 *
	 * @param int   $parent_id شناسه‌ی سریال/انیمه.
	 * @param array $args      season (۰ = همه)، order (ASC/DESC)، limit.
	 * @return array<int,array>
	 */
	public static function episode_groups( $parent_id, $args = array() ) {
		$parent_id = (int) $parent_id;

		if ( ! $parent_id ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'season' => 0,
				'order'  => 'ASC',
				'limit'  => 0,
			)
		);

		$meta_query = array(
			array(
				'key'   => 'manacore_parent_title',
				'value' => (string) $parent_id,
			),
		);

		$season_filter = max( 0, (int) $args['season'] );

		if ( $season_filter ) {
			$meta_query[] = array(
				'key'   => 'manacore_season_number',
				'value' => $season_filter,
			);
		}

		$limit = max( 0, (int) $args['limit'] );

		$episodes = get_posts(
			array(
				'post_type'      => 'episode',
				'posts_per_page' => $limit ? $limit : 500,
				'post_status'    => 'publish',
				'meta_key'       => 'manacore_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => array( 'meta_value_num' => 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC' ),
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$out = array();

		foreach ( $episodes as $episode ) {
			$groups = self::get( $episode->ID );

			if ( ! $groups ) {
				continue;
			}

			$season = (int) get_post_meta( $episode->ID, 'manacore_season_number', true );
			$number = (int) get_post_meta( $episode->ID, 'manacore_episode_number', true );

			foreach ( $groups as $group ) {
				$group['owner']         = (int) $episode->ID;
				$group['episode']       = $number;
				/* `get_the_title()` روی شیء پست هم کار می‌کند و همیشه تعریف‌شده است. */
				$group['episode_label'] = (string) get_the_title( $episode );
				$out[ $season ][]       = $group;
			}
		}

		ksort( $out );

		return $out;
	}

	/**
	 * شمارش لینک‌های ثبت‌شده روی قسمت‌های یک سریال.
	 *
	 * @param int $parent_id شناسه‌ی سریال/انیمه.
	 * @return int
	 */
	public static function episode_count( $parent_id ) {
		return self::total( self::episode_groups( $parent_id ) );
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

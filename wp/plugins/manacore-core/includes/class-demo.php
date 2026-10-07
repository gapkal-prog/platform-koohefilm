<?php
/**
 * محتوای نمایشی یک‌کلیکی.
 *
 * چرا لازم است: خریدار افزونه باید در چند دقیقه سایتی پُر و واقعی ببیند؛
 * پیش‌تر برای دیدن قالب باید دستی چند فیلم و سریال با لینک و قسمت می‌ساخت.
 *
 * سه قاعده:
 *   ۱. **قابل بازگشت.** هر نوشته‌ی ساخته‌شده متای `_manacore_demo` می‌گیرد و
 *      `remove()` فقط همان‌ها را حذف می‌کند؛ محتوای واقعی مدیر دست‌نخورده
 *      می‌ماند.
 *   ۲. **بی‌اثر در اجرای دوباره.** تا وقتی نشانه‌ی ساخت هست، `seed()` کاری
 *      نمی‌کند (مگر با `force`).
 *   ۳. **هم‌خوان با ساختار خود افزونه.** متاها و لینک‌ها از راه همان
 *      کلاس‌های واقعی (`Links::save()`) نوشته می‌شوند، نه با JSON دست‌ساز؛
 *      پس اگر ساختار متا عوض شود، محتوای نمایشی هم خودش را تازه می‌کند.
 *
 * رسانه‌ها نمونه‌های آزاد `gtv-videos-bucket` هستند (Big Buck Bunny و
 * Elephants Dream) تا هیچ محتوای دارای حق تکثیر ثالثی داخل بسته نباشد.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Demo
 */
class Demo {

	/**
	 * نشانه‌ی نوشته‌های نمایشی.
	 */
	const META = '_manacore_demo';

	/**
	 * گزینه‌ی زمان ساخت (برچسب یونیکس).
	 */
	const OPTION = 'manacore_demo_seeded';

	/**
	 * دقیقه‌ی هر قسمت نمونه.
	 */
	const EPISODE_RUNTIME = 58;

	/**
	 * نشانی نمونه‌ی آزاد (پخش و پیش‌پرده).
	 *
	 * @return array<string,string>
	 */
	public static function media() {
		return array(
			'stream'  => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
			'trailer' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
		);
	}

	/**
	 * نقشه‌ی محتوای نمایشی (داده‌ی خالص، بی‌تماس با وردپرس).
	 *
	 * همین تابع در آزمون‌ها هم مصرف می‌شود تا شمارش‌ها از یک منبع بیاید.
	 *
	 * @return array<string,mixed>
	 */
	public static function blueprint() {
		return array(
			'terms'  => array(
				'genre'    => array( 'درام', 'جنایی', 'علمی‌تخیلی', 'اکشن', 'ماجراجویی', 'انیمیشن', 'تاریخی' ),
				'country'  => array( 'آمریکا', 'ژاپن', 'کره جنوبی', 'انگلستان' ),
				'language' => array( 'زیرنویس فارسی', 'دوبله فارسی', 'زبان اصلی' ),
				'quality'  => array( '1080p', '720p', '360p' ),
			),
			'people' => array(
				array( 'name' => 'مارلون براندو', 'role' => 'بازیگر' ),
				array( 'name' => 'لئوناردو دی‌کاپریو', 'role' => 'بازیگر' ),
				array( 'name' => 'سونگ کانگ هو', 'role' => 'بازیگر' ),
				array( 'name' => 'کریستوفر نولان', 'role' => 'کارگردان' ),
			),
			'movies' => array(
				array(
					'title'     => 'پدرخوانده',
					'slug'      => 'the-godfather',
					'original'  => 'The Godfather',
					'year'      => 1972,
					'runtime'   => 175,
					'rating'    => '9.2',
					'genres'    => array( 'درام', 'جنایی' ),
					'countries' => array( 'آمریکا' ),
					'tagline'   => 'پیشنهادی که نمی‌توانی رد کنی.',
					'days_ago'  => 12,
				),
				array(
					'title'     => 'تلقین',
					'slug'      => 'inception',
					'original'  => 'Inception',
					'year'      => 2010,
					'runtime'   => 148,
					'rating'    => '8.8',
					'genres'    => array( 'علمی‌تخیلی', 'اکشن' ),
					'countries' => array( 'آمریکا' ),
					'tagline'   => 'رؤیا، آخرین مرز دزدی است.',
					'days_ago'  => 8,
				),
				array(
					'title'     => 'انگل',
					'slug'      => 'parasite',
					'original'  => 'Parasite',
					'year'      => 2019,
					'runtime'   => 132,
					'rating'    => '8.5',
					'genres'    => array( 'درام', 'هیجان‌انگیز' ),
					'countries' => array( 'کره جنوبی' ),
					'tagline'   => 'یک خانواده، یک نقشه، یک طبقه‌ی دیگر.',
					'days_ago'  => 5,
				),
			),
			'series' => array(
				array(
					'title'     => 'شوگان',
					'slug'      => 'shogun',
					'original'  => 'Shōgun',
					'year'      => 2024,
					'rating'    => '8.5',
					'genres'    => array( 'درام', 'تاریخی' ),
					'countries' => array( 'آمریکا' ),
					'tagline'   => 'حماسه‌ای در ژاپن فئودالی.',
					'days_ago'  => 3,
					'air_day'   => 'thursday',
					'air_time'  => '21:30',
					'seasons'   => array(
						array( 'number' => 1, 'episodes' => 4 ),
						array( 'number' => 2, 'episodes' => 2 ),
					),
				),
				array(
					'title'     => 'چرنوبیل',
					'slug'      => 'chernobyl',
					'original'  => 'Chernobyl',
					'year'      => 2019,
					'rating'    => '9.4',
					'genres'    => array( 'درام', 'تاریخی' ),
					'countries' => array( 'انگلستان' ),
					'tagline'   => 'روایت حادثه‌ای که هیچ‌وقت نباید فراموش شود.',
					'days_ago'  => 2,
					'air_day'   => 'monday',
					'air_time'  => '22:00',
					'seasons'   => array(
						array( 'number' => 1, 'episodes' => 3 ),
					),
				),
			),
			'anime'  => array(
				array(
					'title'     => 'سایبرپانک',
					'slug'      => 'cyberpunk-edgerunners',
					'original'  => 'Cyberpunk: Edgerunners',
					'year'      => 2022,
					'runtime'   => 24,
					'rating'    => '8.3',
					'genres'    => array( 'انیمیشن', 'علمی‌تخیلی' ),
					'countries' => array( 'ژاپن' ),
					'tagline'   => 'آینده‌ای نئونی و بی‌رحم.',
					'days_ago'  => 1,
				),
			),
			'collection' => array(
				'title' => 'گزیده‌ی نمایشی',
				'slug'  => 'demo-picks',
			),
			'comments' => array(
				array( 'title' => 'پدرخوانده', 'author' => 'کیوان', 'text' => 'فیلم‌برداری این اثر هنوز هم بی‌رقیب است؛ نسخه‌ی ۱۰۸۰p روان پخش شد.' ),
				array( 'title' => 'پدرخوانده', 'author' => 'سارا', 'text' => 'یکی از آن فیلم‌هایی که هر چند سال باید دید.' ),
				array( 'title' => 'شوگان', 'author' => 'نگار', 'text' => 'امتیاز بازی‌ها باورپذیر است؛ منتظر فصل بعدی‌ام.' ),
			),
		);
	}

	/**
	 * شمارش قسمت‌های یک سریال نمونه.
	 *
	 * @param array $series ردیف سریال از `blueprint()`.
	 * @return int
	 */
	public static function episode_count( array $series ) {
		$total = 0;

		foreach ( (array) ( $series['seasons'] ?? array() ) as $season ) {
			$total += max( 0, (int) ( $season['episodes'] ?? 0 ) );
		}

		return $total;
	}

	/**
	 * گروه‌های لینک نمونه (سه کیفیت × دو کنش).
	 *
	 * @param string $prefix پیشوند شناسه‌ها.
	 * @return array<int,array>
	 */
	public static function links( $prefix ) {
		$media     = self::media();
		$qualities = array(
			array( '1080p', 'Full HD', '۱.۲ گیگابایت' ),
			array( '720p', 'HD', '۷۴۰ مگابایت' ),
			array( '360p', 'کم‌حجم', '۳۱۰ مگابایت' ),
		);

		$groups = array();

		foreach ( $qualities as $index => $quality ) {
			$groups[] = array(
				'id'       => $prefix . '-' . $index,
				'title'    => $quality[0] . ' · ' . $quality[1],
				'season'   => '',
				'quality'  => $quality[0],
				'language' => 'sub_fa',
				'encoder'  => 'MP4 · H.264',
				'size'     => $quality[2],
				'note'     => '',
				'premium'  => false,
				'items'    => array(
					array(
						'id'       => $prefix . '-' . $index . '-stream',
						'label'    => '',
						'episode'  => '',
						'url'      => $media['stream'],
						'type'     => 'stream',
						'size'     => $quality[2],
						'quality'  => '',
						'language' => '',
						'note'     => '',
					),
					array(
						'id'       => $prefix . '-' . $index . '-direct',
						'label'    => '',
						'episode'  => '',
						'url'      => $media['trailer'],
						'type'     => 'direct',
						'size'     => $quality[2],
						'quality'  => '',
						'language' => '',
						'note'     => '',
					),
				),
			);
		}

		return $groups;
	}

	/**
	 * ساخت محتوای نمایشی.
	 *
	 * @param array $args `force` برای ساخت دوباره.
	 * @return array<string,mixed> نتیجه: created، skipped، ids، terms.
	 */
	public static function seed( $args = array() ) {
		$args   = wp_parse_args( $args, array( 'force' => false ) );
		$result = array(
			'created' => 0,
			'skipped' => false,
			'terms'   => 0,
			'ids'     => array(),
		);

		if ( ! empty( $args['force'] ) ) {
			self::remove();
		} elseif ( get_option( self::OPTION ) && self::counts() ) {
			$result['skipped'] = true;
			return $result;
		}

		$blueprint = self::blueprint();
		$result['terms'] = self::seed_terms( $blueprint['terms'] );

		$people = self::seed_people( $blueprint['people'], $result );

		/* ---------------- فیلم‌ها و انیمه ---------------- */

		foreach ( array( 'movie' => $blueprint['movies'], 'anime' => $blueprint['anime'] ) as $type => $rows ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$id = self::insert_title( $type, $row, $people );

				if ( $id ) {
					$result['ids'][ $type ][] = $id;
				}
			}
		}

		/* ---------------- سریال‌ها و قسمت‌ها ---------------- */

		if ( post_type_exists( 'series' ) ) {
			foreach ( $blueprint['series'] as $row ) {
				$series_id = self::insert_title( 'series', $row, $people );

				if ( ! $series_id ) {
					continue;
				}

				$result['ids']['series'][] = $series_id;

				foreach ( (array) $row['seasons'] as $season ) {
					for ( $number = 1; $number <= (int) $season['episodes']; $number++ ) {
						$episode_id = self::insert_episode( $series_id, $row, (int) $season['number'], $number );

						if ( $episode_id ) {
							$result['ids']['episode'][] = $episode_id;
						}
					}
				}
			}
		}

		/* ---------------- مجموعه ---------------- */

		if ( post_type_exists( 'collection' ) ) {
			$collection_id = self::insert_post(
				array(
					'post_type'    => 'collection',
					'post_title'   => $blueprint['collection']['title'],
					'post_name'    => $blueprint['collection']['slug'],
					'post_content' => '<!-- wp:paragraph --><p>گزیده‌ای از محتوای نمایشی برای دیدن سریع قالب.</p><!-- /wp:paragraph -->',
				)
			);

			if ( $collection_id ) {
				$result['ids']['collection'][] = $collection_id;
			}
		}

		/* ---------------- برگه‌ها ---------------- */

		if ( class_exists( __NAMESPACE__ . '\\Player' ) ) {
			$watch = Player::create_page();

			if ( $watch ) {
				$result['ids']['page'][] = $watch;
			}
		}

		if ( class_exists( __NAMESPACE__ . '\\Requests' ) ) {
			$requests = Requests::create_page();

			if ( $requests ) {
				$result['ids']['page'][] = $requests;
			}
		}

		/* ---------------- دیدگاه‌های نمونه ---------------- */

		self::seed_comments( $blueprint['comments'] );

		update_option( self::OPTION, time() );

		$result['created'] = count( $result['ids'], COUNT_RECURSIVE ) - count( $result['ids'] );

		/**
		 * پس از ساخت محتوای نمایشی.
		 *
		 * @param array $result نتیجه‌ی ساخت.
		 */
		do_action( 'manacore_demo_seeded', $result );

		return $result;
	}

	/**
	 * ساخت ترم‌های نمونه (فقط تاکسونومی‌های موجود).
	 *
	 * @param array $terms تاکسونومی => نام‌ها.
	 * @return int تعداد ترم ساخته‌شده.
	 */
	protected static function seed_terms( $terms ) {
		$created = 0;

		foreach ( (array) $terms as $taxonomy => $names ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			foreach ( (array) $names as $name ) {
				if ( term_exists( $name, $taxonomy ) ) {
					continue;
				}

				if ( ! is_wp_error( wp_insert_term( $name, $taxonomy ) ) ) {
					$created++;
				}
			}
		}

		return $created;
	}

	/**
	 * ساخت نوشته‌های عوامل (اگر نوع محتوا موجود باشد).
	 *
	 * @param array $people ردیف‌های عوامل.
	 * @param array $result نتیجه (شناسه‌ها به آن افزوده می‌شود).
	 * @return array<string,int> نام => شناسه.
	 */
	protected static function seed_people( $people, &$result ) {
		$ids = array();

		if ( ! post_type_exists( 'person' ) ) {
			return $ids;
		}

		foreach ( (array) $people as $person ) {
			$id = self::insert_post(
				array(
					'post_type'    => 'person',
					'post_title'   => $person['name'],
					'post_name'    => sanitize_title( $person['name'] ),
					'post_content' => '<!-- wp:paragraph --><p>' . $person['role'] . '</p><!-- /wp:paragraph -->',
				),
				array( 'manacore_person_role' => $person['role'] )
			);

			if ( $id ) {
				$ids[ $person['name'] ] = $id;
				$result['ids']['person'][] = $id;
			}
		}

		return $ids;
	}

	/**
	 * ساخت یک اثر (فیلم/سریال/انیمه) با متا و ترم‌ها.
	 *
	 * @param string $type  نوع محتوا.
	 * @param array  $row   ردیف نقشه.
	 * @param array  $people نام => شناسه‌ی عوامل.
	 * @return int شناسه یا صفر.
	 */
	protected static function insert_title( $type, array $row, array $people = array() ) {
		$meta = array(
			'manacore_original_title' => (string) ( $row['original'] ?? '' ),
			'manacore_year'           => (int) ( $row['year'] ?? 0 ),
			'manacore_tagline'        => (string) ( $row['tagline'] ?? '' ),
			'manacore_imdb_rating'    => (string) ( $row['rating'] ?? '' ),
			'manacore_trailer_url'    => self::media()['trailer'],
			'manacore_status'         => 'released',
			'manacore_custom_notice'  => __( 'لینک‌های این نسخه‌ی نمایشی، نمونه‌ی ویدئوی آزاد است؛ برای اثر واقعی از پنل «لینک‌ها» عوضش کنید.', 'manacore' ),
		);

		if ( ! empty( $row['runtime'] ) ) {
			$meta['manacore_runtime'] = (int) $row['runtime'];
		}

		if ( 'series' === $type ) {
			$meta['manacore_total_seasons']  = count( (array) $row['seasons'] );
			$meta['manacore_total_episodes'] = self::episode_count( $row );
			$meta['manacore_air_day']        = (string) ( $row['air_day'] ?? '' );
			$meta['manacore_air_time']       = (string) ( $row['air_time'] ?? '' );
			$meta['manacore_episode_runtime'] = self::EPISODE_RUNTIME;

			$seasons = array();

			foreach ( (array) $row['seasons'] as $season ) {
				$seasons[] = array(
					'number'   => (int) $season['number'],
					'name'     => sprintf( __( 'فصل %s', 'manacore' ), number_format_i18n( (int) $season['number'] ) ),
					'episodes' => (int) $season['episodes'],
					'year'     => (int) ( $row['year'] ?? 0 ),
				);
			}

			$meta['manacore_seasons'] = wp_json_encode( $seasons, JSON_UNESCAPED_UNICODE );
		}

		/*
		 * بازیگران نمونه از فهرست عوامل انتخاب می‌شوند تا فهرست بازیگران
		 * (که شناسه‌ی عامل را می‌خواند) هم پُر باشد.
		 */
		$cast = array();

		foreach ( $people as $name => $person_id ) {
			$cast[] = array(
				'name'      => $name,
				'character' => __( 'نقش نمونه', 'manacore' ),
				'photo'     => '',
				'person_id' => (int) $person_id,
			);
		}

		if ( $cast ) {
			$meta['manacore_cast'] = wp_json_encode( $cast, JSON_UNESCAPED_UNICODE );
		}

		$id = self::insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => (string) $row['title'],
				'post_name'    => (string) $row['slug'],
				'post_status'  => 'publish',
				'post_date'    => self::days_ago( (int) ( $row['days_ago'] ?? 1 ) ),
				'post_content' => '<!-- wp:paragraph --><p>' . (string) ( $row['tagline'] ?? '' ) . '</p><!-- /wp:paragraph -->',
			),
			$meta
		);

		if ( ! $id ) {
			return 0;
		}

		self::assign_terms( $id, $row );

		if ( class_exists( __NAMESPACE__ . '\\Links' ) ) {
			Links::save( $id, self::links( $type . '-' . $id ) );
		}

		return $id;
	}

	/**
	 * ساخت یک قسمت با متا و لینک‌ها.
	 *
	 * @param int   $series_id شناسه‌ی سریال.
	 * @param array $row       ردیف سریال.
	 * @param int   $season    شماره‌ی فصل.
	 * @param int   $number    شماره‌ی قسمت.
	 * @return int شناسه یا صفر.
	 */
	protected static function insert_episode( $series_id, array $row, $season, $number ) {
		if ( ! post_type_exists( 'episode' ) ) {
			return 0;
		}

		$title = sprintf(
			/* translators: ۱: نام سریال، ۲: شماره فصل، ۳: شماره قسمت */
			__( '%1$s — فصل %2$s قسمت %3$s', 'manacore' ),
			(string) $row['title'],
			number_format_i18n( $season ),
			number_format_i18n( $number )
		);

		$id = self::insert_post(
			array(
				'post_type'    => 'episode',
				'post_title'   => $title,
				'post_name'    => sprintf( '%s-season-%d-episode-%d', (string) $row['slug'], $season, $number ),
				'post_status'  => 'publish',
				'post_date'    => self::days_ago( (int) ( $row['days_ago'] ?? 1 ) ),
				'post_content' => '<!-- wp:paragraph --><p>' . __( 'خلاصه‌ی این قسمت به‌زودی تکمیل می‌شود.', 'manacore' ) . '</p><!-- /wp:paragraph -->',
			),
			array(
				'manacore_parent_title'    => (int) $series_id,
				'manacore_season_number'   => (int) $season,
				'manacore_episode_number'  => (int) $number,
				'manacore_episode_runtime' => self::EPISODE_RUNTIME,
			)
		);

		if ( ! $id ) {
			return 0;
		}

		if ( class_exists( __NAMESPACE__ . '\\Links' ) ) {
			Links::save( $id, self::links( sprintf( 'ep-%d', $id ) ) );
		}

		return $id;
	}

	/**
	 * درج نوشته با نشانه‌ی نمایشی و متاها.
	 *
	 * @param array $postarr آرگومان‌های `wp_insert_post`.
	 * @param array $meta    متاها.
	 * @return int شناسه یا صفر.
	 */
	protected static function insert_post( array $postarr, array $meta = array() ) {
		$postarr['post_status'] = $postarr['post_status'] ?? 'publish';

		$id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		$id = (int) $id;

		update_post_meta( $id, self::META, 1 );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return $id;
	}

	/**
	 * چسباندن ترم‌های ردیف به نوشته.
	 *
	 * @param int   $id  شناسه‌ی نوشته.
	 * @param array $row ردیف نقشه.
	 * @return void
	 */
	protected static function assign_terms( $id, array $row ) {
		$map = array(
			'genre'    => 'genres',
			'country'  => 'countries',
		);

		foreach ( $map as $taxonomy => $key ) {
			if ( empty( $row[ $key ] ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			wp_set_object_terms( $id, (array) $row[ $key ], $taxonomy, false );
		}

		if ( taxonomy_exists( 'language' ) ) {
			wp_set_object_terms( $id, 'زیرنویس فارسی', 'language', false );
		}

		if ( taxonomy_exists( 'quality' ) ) {
			wp_set_object_terms( $id, array( '1080p', '720p' ), 'quality', false );
		}
	}

	/**
	 * دیدگاه‌های نمونه روی نخستین اثر هم‌نام.
	 *
	 * @param array $rows ردیف‌های دیدگاه.
	 * @return int تعداد دیدگاه ساخته‌شده.
	 */
	protected static function seed_comments( $rows ) {
		$created = 0;

		foreach ( (array) $rows as $row ) {
			$post = get_page_by_path( sanitize_title( self::slug_for_title( (string) $row['title'] ) ), OBJECT, array( 'movie', 'series', 'anime' ) );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( get_comments( array( 'post_id' => $post->ID, 'author' => $row['author'], 'count' => true ) ) ) {
				continue;
			}

			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $post->ID,
					'comment_author'       => (string) $row['author'],
					'comment_author_email' => 'demo-' . md5( (string) $row['author'] ) . '@example.com',
					'comment_content'      => (string) $row['text'],
					'comment_approved'     => 1,
				)
			);

			if ( $comment_id ) {
				$created++;
			}
		}

		return $created;
	}

	/**
	 * نامک یک عنوان از نقشه‌ی نمایشی (برای یافتن نوشته‌ی ساخته‌شده).
	 *
	 * @param string $title عنوان.
	 * @return string
	 */
	protected static function slug_for_title( $title ) {
		$blueprint = self::blueprint();

		foreach ( array_merge( $blueprint['movies'], $blueprint['series'], $blueprint['anime'] ) as $row ) {
			if ( $row['title'] === $title ) {
				return (string) $row['slug'];
			}
		}

		return $title;
	}

	/**
	 * تاریخ `Y-m-d H:i:s` چند روز پیش (به وقت سایت).
	 *
	 * @param int $days تعداد روز.
	 * @return string
	 */
	protected static function days_ago( $days ) {
		$days = max( 0, (int) $days );

		return (string) wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * شناسه‌ی نوشته‌های نمایشی.
	 *
	 * @return array<int,int>
	 */
	public static function post_ids() {
		$types = array( 'movie', 'series', 'anime', 'episode', 'person', 'collection' );
		$types = array_values( array_filter( $types, 'post_type_exists' ) );

		if ( ! $types ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * شمارش نوشته‌های نمایشی به تفکیک نوع.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		$counts = array(
			'movie'      => 0,
			'series'     => 0,
			'episode'    => 0,
			'person'     => 0,
			'collection' => 0,
			'anime'      => 0,
		);

		foreach ( self::post_ids() as $id ) {
			$type = (string) get_post_type( $id );

			if ( isset( $counts[ $type ] ) ) {
				$counts[ $type ]++;
			}
		}

		return $counts;
	}

	/**
	 * وضعیت محتوای نمایشی (برای کارت پنل).
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$counts = self::counts();
		$total  = array_sum( $counts );

		return array(
			'seeded' => (bool) get_option( self::OPTION ),
			'when'   => (int) get_option( self::OPTION ),
			'counts' => $counts,
			'total'  => (int) $total,
		);
	}

	/**
	 * حذف محتوای نمایشی (فقط نوشته‌های نشان‌دار).
	 *
	 * @return int تعداد حذف‌شده.
	 */
	public static function remove() {
		$deleted = 0;

		foreach ( self::post_ids() as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$deleted++;
			}
		}

		delete_option( self::OPTION );

		/**
		 * پس از حذف محتوای نمایشی.
		 *
		 * @param int $deleted تعداد حذف‌شده.
		 */
		do_action( 'manacore_demo_removed', $deleted );

		return $deleted;
	}
}

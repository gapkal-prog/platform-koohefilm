<?php
/**
 * نقاط پایانی REST.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rest_Api
 */
class Rest_Api {

	use Singleton;

	/**
	 * فضای نام.
	 */
	const NS = 'manacore/v1';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * ثبت مسیرها.
	 */
	public function register_routes() {

		register_rest_route(
			self::NS,
			'/rate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'rating'  => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/watchlist',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'watchlist' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'list'    => array(
						'type'              => 'string',
						'default'           => 'watchlist',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
					// محدودسازی به نوع‌های محتوا؛ چند مقدار با کاما جدا می‌شود.
					'type'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/titles',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'titles' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/links/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'links' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/track-download',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_download' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * ثبت امتیاز.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rate( $request ) {
		if ( ! manacore_get_option( 'enable_ratings', 1 ) ) {
			return new \WP_Error( 'manacore_disabled', __( 'امتیازدهی غیرفعال است.', 'manacore' ), array( 'status' => 403 ) );
		}

		$result = Ratings::instance()->vote( $request['post_id'], $request['rating'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * افزودن/حذف از لیست تماشا.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function watchlist( $request ) {
		$key    = 'watched' === $request['list'] ? Watchlist::WATCHED_KEY : Watchlist::META_KEY;
		$result = Watchlist::instance()->toggle( $request['post_id'], 0, $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * جستجوی زنده.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function search( $request ) {
		$post_types = manacore_title_post_types();

		// اگر بلوک جستجو نوع‌های خاصی را تعیین کرده باشد، فقط همان‌ها جستجو می‌شوند.
		$requested = (string) $request->get_param( 'type' );
		if ( '' !== $requested ) {
			$filtered = array_intersect(
				array_map( 'sanitize_key', preg_split( '/[\s,،]+/', $requested, -1, PREG_SPLIT_NO_EMPTY ) ),
				$post_types
			);
			if ( $filtered ) {
				$post_types = array_values( $filtered );
			}
		}

		$query = new \WP_Query(
			array(
				'post_type'           => $post_types,
				'post_status'         => 'publish',
				's'                   => $request['q'],
				'posts_per_page'      => min( 20, max( 1, (int) $request['per_page'] ) ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_post( $post );
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * فهرست آثار با فیلتر.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function titles( $request ) {
		$args = array(
			'posts_per_page'  => min( 48, max( 1, (int) $request->get_param( 'per_page' ) ?: 12 ) ),
			'paged'           => max( 1, (int) $request->get_param( 'page' ) ),
			'manacore_source' => sanitize_key( (string) $request->get_param( 'source' ) ),
			'no_found_rows'   => false,
		);

		$post_type = $request->get_param( 'type' );
		if ( $post_type ) {
			$types             = array_intersect( (array) explode( ',', sanitize_text_field( $post_type ) ), manacore_title_post_types() );
			$args['post_type'] = $types ? $types : manacore_title_post_types();
		}

		$genre = $request->get_param( 'genre' );
		if ( $genre ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'genre',
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', explode( ',', sanitize_text_field( $genre ) ) ),
				),
			);
		}

		$query = Query::get_titles( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->format_post( $post );
		}

		return rest_ensure_response(
			array(
				'items'    => $items,
				'total'    => (int) $query->found_posts,
				'pages'    => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * دریافت لینک‌های یک اثر.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function links( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'manacore_not_found', __( 'محتوا یافت نشد.', 'manacore' ), array( 'status' => 404 ) );
		}

		if ( manacore_get_option( 'links_login_only', 0 ) && ! is_user_logged_in() ) {
			return new \WP_Error( 'manacore_login_required', __( 'ابتدا وارد شوید.', 'manacore' ), array( 'status' => 401 ) );
		}

		$has_access = manacore_user_can_access( $post_id );
		$groups     = Links::get( $post_id );

		// حذف لینک‌های ویژه برای کاربران بدون دسترسی.
		foreach ( $groups as &$group ) {
			if ( $group['premium'] && ! $has_access ) {
				$group['items']  = array();
				$group['locked'] = true;
			} else {
				$group['locked'] = false;
			}
		}
		unset( $group );

		return rest_ensure_response(
			array(
				'groups'     => $groups,
				'has_access' => $has_access,
			)
		);
	}

	/**
	 * ثبت آمار دانلود.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function track_download( $request ) {
		Ratings::instance()->log_stat( (int) $request['post_id'], 'download' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * قالب‌بندی خروجی یک پست.
	 *
	 * @param \WP_Post $post پست.
	 * @return array
	 */
	protected function format_post( $post ) {
		$labels = manacore_post_types();

		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'original'  => (string) get_post_meta( $post->ID, 'manacore_original_title', true ),
			'url'       => get_permalink( $post ),
			'poster'    => manacore_poster_url( $post->ID, 'medium' ),
			'year'      => Templates::year( $post->ID ),
			'rating'    => Templates::best_rating( $post->ID ),
			'type'      => $post->post_type,
			'typeLabel' => isset( $labels[ $post->post_type ] ) ? $labels[ $post->post_type ] : $post->post_type,
			'excerpt'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'premium'   => (bool) get_post_meta( $post->ID, 'manacore_is_premium', true ),
		);
	}
}

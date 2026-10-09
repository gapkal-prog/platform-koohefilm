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
	 * دروازه‌بان نوشتنِ عمومی.
	 *
	 * مسیرهای `/rate` و `/track-download` عمداً برای کاربر وارد‌نشده هم باز
	 * هستند (امتیازدهی مهمان و شمارش دانلود)، ولی پیش‌تر
	 * `permission_callback => '__return_true'` داشتند؛ یعنی هیچ نشانی
	 * (nonce) بررسی نمی‌شد. وردپرس توکن `X-WP-Nonce` را فقط زمانی اعتبارسنجی
	 * می‌کند که خودِ دروازه‌بان این کار را بخواهد، پس یک درخواست
	 * cross-site می‌توانست امتیاز و شمارش دانلود را تغییر دهد
	 * (آزمون‌شده: POST بی‌نشان و بی‌نام از بیرون پاسخ ۲۰۰ می‌گرفت).
	 *
	 * اکنون حداقلِ لازم اعمال می‌شود: یک نشستِ معتبر `wp_rest`.
	 * کاربر وارد‌نشده همان نشانی را از `wp_create_nonce( 'wp_rest' )`
	 * می‌گیرد که افزونه با `manaCore.nonce` به front.js می‌دهد، پس رفتار
	 * سمت کاربر تغییری نمی‌کند.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return bool|\WP_Error
	 */
	public function verify_public_write( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		return new \WP_Error(
			'manacore_bad_nonce',
			__( 'نشست شما منقضی شده است. صفحه را دوباره بارگذاری کنید.', 'manacore' ),
			array( 'status' => 403 )
		);
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
				'permission_callback' => array( $this, 'verify_public_write' ),
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
			'/live/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'live_channel' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		// جست‌وجوی عوامل برای پیشخوان (فقط ویرایشگران).
		register_rest_route(
			self::NS,
			'/people',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'people' ),
				'permission_callback' => array( $this, 'verify_editor' ),
				'args'                => array(
					'q'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
						'enum'              => array( '', 'director', 'writer', 'producer', 'composer', 'cast' ),
					),
					'limit' => array(
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
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

		/*
		 * شمارش تماشا: از سمت مرورگر و تنها با شروع واقعی پخش صدا زده
		 * می‌شود (همان الگوی `track-download`).
		 */
		register_rest_route(
			self::NS,
			'/track-view',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_view' ),
				'permission_callback' => array( $this, 'verify_public_write' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
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
				'permission_callback' => array( $this, 'verify_public_write' ),
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
	 * بدنه‌ی جستجو در `Search` است تا مسیر REST، کوئری اصلی برگه‌ی جستجو و
	 * همه‌ی مصرف‌کننده‌های سمت کاربر یک رفتار داشته باشند.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function search( $request ) {
		$term     = trim( (string) $request->get_param( 'q' ) );
		$type     = (string) $request->get_param( 'type' );
		$per_page = min( 20, max( 1, (int) $request->get_param( 'per_page' ) ) );

		/*
		 * کش کوتاه‌مدت: جستجوی زنده با هر مکثِ تایپ اجرا می‌شود؛ روی سایتی با
		 * چند هزار اثر، پاسخ تکراریِ چند کاربر در یک بازه‌ی کوتاه نباید دیتابیس
		 * را دوباره درگیر کند. کلید از هر سه ورودی ساخته می‌شود تا پاسخ
		 * محدودسازی‌شده با پاسخ عمومی قاطی نشود.
		 */
		$cache_key = 'manacore_search_' . md5( $term . '|' . $type . '|' . $per_page );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$query = Search::query(
			array(
				's'        => $term,
				'type'     => $type,
				'per_page' => $per_page,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->format_post( $post );
		}

		$payload = array(
			'items' => $items,
			'total' => count( $items ),
			'query' => $term,
		);

		if ( '' !== $term ) {
			set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );
		}

		return rest_ensure_response( $payload );
	}

	/**
	 * مجوز ویرایشگر برای مسیرهای پیشخوان.
	 *
	 * @return bool
	 */
	public function verify_editor() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * جست‌وجوی عوامل ثبت‌شده (CPT person) برای برچسب‌های پیشخوان.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function people( $request ) {
		$items = Crew::search(
			(string) $request->get_param( 'q' ),
			(string) $request->get_param( 'role' ),
			(int) $request->get_param( 'limit' )
		);

		return rest_ensure_response( array( 'items' => $items ) );
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
	 * ثبت شمارش تماشا (رویداد واقعی شروع پخش).
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function track_view( $request ) {
		/*
		 * «تماشا» فقط با رویداد واقعی پخش از مرورگر می‌آید؛ سرور هم
		 * پنجره‌ی ضدرعدّ‌سازی دارد تا یک تماشا چند بار شمرده نشود.
		 */
		$counted = Ratings::instance()->count_watch( (int) $request['post_id'] );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'counted' => (bool) $counted,
			)
		);
	}

	/**
	 * شمارش دانلود.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response
	 */
	public function track_download( $request ) {
		/*
		 * شمارش از پنجره‌ی ضدرعدّ‌سازی `Downloads` می‌گذرد تا کلیک دوباره
		 * (یا رفرش پشت‌سرهم) آمار را باد نکند و با مسیر امضاشده‌ی دانلود
		 * هم دوباره‌شماری پیش نیاید.
		 */
		$counted = Downloads::count( (int) $request['post_id'] );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'counted' => (bool) $counted,
			)
		);
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

	/**
	 * داده‌ی یک کانال پخش زنده برای جابه‌جایی درجا.
	 *
	 * `live.js` مرجع کل آرایه‌ی کانال‌ها را در جاوااسکریپت داشت؛ این‌جا داده
	 * از نوع محتوای `channel` خوانده می‌شود و این نقطه فقط همان یک کانال را
	 * برمی‌گرداند. پاسخ هیچ فیلد مدیریتی (متای خام، وضعیت، نویسنده) را
	 * بیرون نمی‌دهد و فقط کانال **منتشرشده** را می‌پذیرد؛ نامک ناشناس
	 * پاسخ ۴۰۴ می‌گیرد تا رفتار سمت کاربر قابل اتکا باشد.
	 *
	 * @param \WP_REST_Request $request درخواست.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function live_channel( $request ) {
		$slug    = sanitize_title( (string) $request->get_param( 'slug' ) );
		$channel = Channel::resolve( $slug );

		/*
		 * `Channel::resolve()` در نبود کانال، نخستین کانال را برمی‌گرداند
		 * (رفتار درست برای رندر سرور). این‌جا اما نامک ناشناس باید صریح
		 * پاسخ ۴۰۴ بگیرد؛ وگرنه کاربر نشانی اشتباه می‌گیرد و کانال دیگری
		 * می‌بیند.
		 */
		if ( ! $channel || ( $slug && $slug !== $channel->post_name ) ) {
			return new \WP_Error(
				'manacore_channel_not_found',
				__( 'کانال درخواستی پیدا نشد.', 'manacore' ),
				array( 'status' => 404 )
			);
		}

		$payload = Channel::payload( $channel );
		$payload['iconSvg'] = Block_Support::heading_icon( $payload['icon'] );

		return rest_ensure_response( $payload );
	}

}

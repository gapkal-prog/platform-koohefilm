<?php
/**
 * ثبت نوع‌های محتوا.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Post_Types
 */
class Post_Types {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'init', array( $this, 'register_episode_rewrites' ), 20 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'episode_permalink' ), 10, 2 );
		add_filter( 'query_vars', array( $this, 'episode_query_vars' ) );
		add_filter( 'request', array( $this, 'resolve_episode_request' ) );
	}

	/**
	 * نام متغیرهای پرسمانِ نشانیِ تو در توی قسمت.
	 */
	const EPISODE_VARS = array( 'mc_ep_type', 'mc_ep_parent', 'mc_ep_season', 'mc_ep_number', 'mc_ep_lookup' );

	/**
	 * ثبت قانون بازنویسی برای نشانی تو در توی قسمت.
	 *
	 * چرا لازم است؟
	 *
	 * متد `episode_permalink()` نشانی هر قسمت را به شکل
	 * `{نشانی سریال}/season-N/episode-M/` بازنویسی می‌کند، ولی هیچ قانون
	 * بازنویسی‌ای برای خواندنِ همین شکل ثبت نشده بود. نتیجه در آزمون واقعی:
	 * بلوک «فصل‌ها و قسمت‌ها» شش پیوند قسمت را در صفحه‌ی سریال چاپ می‌کرد و
	 * **هر شش پیوند ۴۰۴ می‌داد** (`/series/chernobyl/season-1/episode-1/`).
	 * یعنی مسیر اصلی رفتن به یک قسمت از خودِ سایت شکسته بود.
	 *
	 * قانون اینجا نشانی را به یک پرسمان نشانه‌دار تبدیل می‌کند و
	 * `resolve_episode_request()` آن را به قسمت واقعی نگاشت می‌کند.
	 */
	public function register_episode_rewrites() {
		$types = array_map( 'preg_quote', manacore_title_post_types() );
		if ( ! $types ) {
			return;
		}

		add_rewrite_rule(
			'^(' . implode( '|', $types ) . ')/([^/]+)/season-([0-9]+)/episode-([0-9]+)/?$',
			'index.php?mc_ep_lookup=1&mc_ep_type=$matches[1]&mc_ep_parent=$matches[2]&mc_ep_season=$matches[3]&mc_ep_number=$matches[4]',
			'top'
		);

		$this->maybe_flush_episode_rewrites();
	}

	/**
	 * بازآوری یک‌باره‌ی قواعد بازنویسی پس از افزودن قانون قسمت.
	 *
	 * بدون این کار قانون تازه تا نخستین ذخیره‌ی «پیوندهای یکتا» بی‌اثر
	 * می‌ماند و همچنان ۴۰۴ دیده می‌شود. نسخه در یک گزینه نگه داشته
	 * می‌شود تا فقط یک بار انجام شود.
	 */
	private function maybe_flush_episode_rewrites() {
		$key = 'manacore_episode_rewrite_version';
		if ( get_option( $key ) === MANACORE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( $key, MANACORE_VERSION, false );
	}

	/**
	 * افزودن متغیرهای پرسمان قسمت به فهرست مجاز وردپرس.
	 *
	 * @param array $vars متغیرها.
	 * @return array
	 */
	public function episode_query_vars( $vars ) {
		return array_merge( (array) $vars, self::EPISODE_VARS );
	}

	/**
	 * نگاشت درخواستِ نشانی تو در توی قسمت به خودِ قسمت.
	 *
	 * این تابع روی فیلتر `request` می‌نشیند، نه `pre_get_posts`. دلیلش
	 * سنجیده شد: در `pre_get_posts` مرحله‌ی `parse_query()` پیش‌تر اجرا شده
	 * و پرچم‌های `is_single` / `is_singular` را روی «خانه» گذاشته است.
	 * نتیجه‌اش این بود که صفحه با قالب `single-episode` رندر نمی‌شد و
	 * کلاس‌های بدنه `single` و `postid-` را نداشت. فیلتر `request` پیش از
	 * `parse_query` اجرا می‌شود، پس وردپرس مثل یک درخواست تکیِ معمولی
	 * همه‌چیز را درست می‌سازد: قالب، کلاس‌های بدنه و تغییرمسیر یکتاسازی.
	 *
	 * @param array $vars متغیرهای پرسمان.
	 * @return array
	 */
	public function resolve_episode_request( $vars ) {
		if ( empty( $vars['mc_ep_lookup'] ) ) {
			return $vars;
		}

		$parent_slug = sanitize_title( isset( $vars['mc_ep_parent'] ) ? (string) $vars['mc_ep_parent'] : '' );
		$parent_type = sanitize_key( isset( $vars['mc_ep_type'] ) ? (string) $vars['mc_ep_type'] : '' );
		$season      = isset( $vars['mc_ep_season'] ) ? absint( $vars['mc_ep_season'] ) : 0;
		$number      = isset( $vars['mc_ep_number'] ) ? absint( $vars['mc_ep_number'] ) : 0;

		if ( '' === $parent_slug || '' === $parent_type || ! $season || ! $number ) {
			return array( 'error' => '404' );
		}

		/*
		 * `get_page_by_path()` وقتی `$post_type` اسکالر باشد، همیشه «attachment»
		 * را هم در پرسمان می‌گنجاند (`array( $post_type, 'attachment' )`)؛ پس
		 * اگر پیوستی هم‌نام با سریال وجود داشته باشد، نتیجه می‌تواند پیوست
		 * باشد و نشانی قسمت به اثر اشتباه برسد. شکل آرایه‌ای، نوع را دقیق
		 * می‌کند و بررسی صریحِ نوع هم لایه‌ی دوم اطمینان است.
		 */
		$parent = get_page_by_path( $parent_slug, OBJECT, array( $parent_type ) );
		if ( ! $parent || $parent->post_type !== $parent_type ) {
			return array( 'error' => '404' );
		}

		$episode = get_posts(
			array(
				'post_type'        => 'episode',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'manacore_parent_title',
						'value' => $parent->ID,
					),
					array(
						'key'   => 'manacore_season_number',
						'value' => $season,
					),
					array(
						'key'   => 'manacore_episode_number',
						'value' => $number,
					),
				),
			)
		);

		if ( empty( $episode ) ) {
			return array( 'error' => '404' );
		}

		/*
		 * پرسمان به «یک قسمت مشخص» تبدیل می‌شود. از `p` استفاده می‌کنیم که
		 * خودش `is_single`/`is_singular` و `$post` را درست می‌سازد. نشانی‌ای
		 * که get_permalink تولید می‌کند همان درخواست فعلی است، پس
		 * یکتاسازیِ تغییرمسیر حلقه نمی‌سازد.
		 */
		return array(
			'post_type' => 'episode',
			'p'         => (int) $episode[0],
		);
	}

	/**
	 * ثبت همه‌ی نوع‌های محتوا.
	 */
	public function register() {
		$this->register_movie();
		$this->register_series();
		$this->register_anime();
		$this->register_episode();
		$this->register_person();
		$this->register_collection();
		$this->register_channel();
	}

	/**
	 * ساخت آرایه‌ی برچسب‌ها.
	 *
	 * @param string $singular مفرد.
	 * @param string $plural   جمع.
	 * @return array
	 */
	private function labels( $singular, $plural ) {
		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'افزودن', 'manacore' ),
			/* translators: %s: نام مفرد نوع محتوا */
			'add_new_item'          => sprintf( __( 'افزودن %s جدید', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'edit_item'             => sprintf( __( 'ویرایش %s', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'new_item'              => sprintf( __( '%s جدید', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'view_item'             => sprintf( __( 'نمایش %s', 'manacore' ), $singular ),
			/* translators: %s: نام جمع نوع محتوا */
			'search_items'          => sprintf( __( 'جستجوی %s', 'manacore' ), $plural ),
			/* translators: %s: نام جمع نوع محتوا */
			'not_found'             => sprintf( __( 'هیچ %s یافت نشد', 'manacore' ), $plural ),
			'not_found_in_trash'    => __( 'موردی در زباله‌دان نیست', 'manacore' ),
			'all_items'             => $plural,
			'featured_image'        => __( 'پوستر', 'manacore' ),
			'set_featured_image'    => __( 'انتخاب پوستر', 'manacore' ),
			'remove_featured_image' => __( 'حذف پوستر', 'manacore' ),
			'use_featured_image'    => __( 'استفاده به عنوان پوستر', 'manacore' ),
			'items_list'            => $plural,
			'item_published'        => __( 'منتشر شد.', 'manacore' ),
			'item_updated'          => __( 'به‌روزرسانی شد.', 'manacore' ),
		);
	}

	/**
	 * آرگومان‌های مشترک.
	 *
	 * @param array $args آرگومان‌های اختصاصی.
	 * @return array
	 */
	private function base_args( array $args ) {
		return wp_parse_args(
			$args,
			array(
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => '',
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => false,
				'menu_position'      => 5,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
				'template_lock'      => false,
			)
		);
	}

	/**
	 * نوع محتوای فیلم.
	 */
	private function register_movie() {
		register_post_type(
			'movie',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'فیلم', 'manacore' ), __( 'فیلم‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-format-video',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_movie', 'movie' ),
						'with_front' => false,
					),
					'rest_base'    => 'movies',
					'menu_position'=> 20,
				)
			)
		);
	}

	/**
	 * نوع محتوای سریال.
	 */
	private function register_series() {
		register_post_type(
			'series',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'سریال', 'manacore' ), __( 'سریال‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-editor-ol',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_series', 'series' ),
						'with_front' => false,
					),
					'rest_base'    => 'series',
					'menu_position'=> 21,
				)
			)
		);
	}

	/**
	 * نوع محتوای انیمه.
	 */
	private function register_anime() {
		register_post_type(
			'anime',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'انیمه', 'manacore' ), __( 'انیمه‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-buddicons-activity',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_anime', 'anime' ),
						'with_front' => false,
					),
					'rest_base'    => 'anime',
					'menu_position'=> 22,
				)
			)
		);
	}

	/**
	 * نوع محتوای قسمت.
	 */
	private function register_episode() {
		register_post_type(
			'episode',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'قسمت', 'manacore' ), __( 'قسمت‌ها', 'manacore' ) ),
					'menu_icon'     => 'dashicons-playlist-video',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_episode', 'episode' ),
						'with_front' => false,
					),
					'rest_base'     => 'episodes',
					'has_archive'   => false,
					'menu_position' => 23,
					'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields' ),
				)
			)
		);
	}

	/**
	 * نوع محتوای عوامل (بازیگر/کارگردان).
	 */
	private function register_person() {
		register_post_type(
			'person',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'عامل', 'manacore' ), __( 'عوامل', 'manacore' ) ),
					'menu_icon'     => 'dashicons-groups',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_person', 'person' ),
						'with_front' => false,
					),
					'rest_base'     => 'people',
					'menu_position' => 24,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				)
			)
		);
	}

	/**
	 * نوع محتوای «کانال» (پخش زنده).
	 *
	 * هم‌ارز آرایه‌ی سخت‌کدشده‌ی `channels` در `cinora/assets/js/live.js`:
	 * نام کانال، زیرعنوان، کیفیت، پوستر و ویدئو از پیشخوان مدیریت می‌شود،
	 * نه از یک آرایه در جاوااسکریپت. آرشیو عمومی ندارد چون همان برگه‌ی
	 * «پخش زنده» (قالب `page-live.html`) همه‌ی کانال‌ها را نشان می‌دهد.
	 */
	private function register_channel() {
		register_post_type(
			'channel',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'کانال', 'manacore' ), __( 'کانال‌ها', 'manacore' ) ),
					'menu_icon'     => 'dashicons-video-alt3',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_channel', 'channel' ),
						'with_front' => false,
					),
					'rest_base'     => 'channels',
					/*
					 * پرس‌وجوی عمومی `?channel=` در نشانی برگه‌ی «پخش زنده» رزرو است
					 * (پیوند کارت‌های کانال). اگر همین نام روی نوع محتوا بماند، وردپرس
					 * پارامتر را به «تک‌کانال» تفسیر می‌کند و برگه‌ی پخش زنده رندر
					 * نمی‌شود؛ پس پرس‌وجوی کانال نام اختصاصی می‌گیرد و نشانی یکتای
					 * کانال هم مثل قبل با نامک `channel/<slug>` می‌ماند.
					 */
					'query_var'     => 'manacore_channel',
					'has_archive'   => false,
					'menu_position' => 26,
					'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				)
			)
		);
	}

	/**
	 * نوع محتوای مجموعه (کالکشن).
	 */
	private function register_collection() {
		register_post_type(
			'collection',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'مجموعه', 'manacore' ), __( 'مجموعه‌ها', 'manacore' ) ),
					'menu_icon'     => 'dashicons-images-alt2',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_collection', 'collection' ),
						'with_front' => false,
					),
					'rest_base'     => 'collections',
					'menu_position' => 25,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				)
			)
		);
	}

	/**
	 * متن راهنمای فیلد عنوان.
	 *
	 * @param string   $text متن.
	 * @param \WP_Post $post پست.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		switch ( $post->post_type ) {
			case 'movie':
				return __( 'نام فیلم را وارد کنید', 'manacore' );
			case 'series':
				return __( 'نام سریال را وارد کنید', 'manacore' );
			case 'anime':
				return __( 'نام انیمه را وارد کنید', 'manacore' );
			case 'episode':
				return __( 'نام قسمت را وارد کنید', 'manacore' );
			case 'person':
				return __( 'نام عامل را وارد کنید', 'manacore' );
		}
		return $text;
	}

	/**
	 * پیوند یکتای قسمت را زیر اثر والد قرار می‌دهد.
	 *
	 * @param string   $link پیوند.
	 * @param \WP_Post $post پست.
	 * @return string
	 */
	public function episode_permalink( $link, $post ) {
		if ( 'episode' !== $post->post_type ) {
			return $link;
		}
		$parent = (int) get_post_meta( $post->ID, 'manacore_parent_title', true );
		if ( ! $parent ) {
			return $link;
		}
		$season  = (int) get_post_meta( $post->ID, 'manacore_season_number', true );
		$episode = (int) get_post_meta( $post->ID, 'manacore_episode_number', true );
		if ( ! $season || ! $episode ) {
			return $link;
		}
		return trailingslashit( get_permalink( $parent ) ) . sprintf( 'season-%d/episode-%d/', $season, $episode );
	}
}

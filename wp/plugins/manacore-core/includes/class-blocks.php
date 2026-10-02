<?php
/**
 * بلوک‌های ویرایشگر (رندر سمت سرور).
 *
 * هر بلوک از سه لایه‌ی مشترک استفاده می‌کند:
 *
 * - {@see Block_Support} ویژگی‌های ظاهری و قطعه‌های مشترک رندر.
 * - {@see Block_Query}   تبدیل ویژگی‌ها به آرگومان‌های WP_Query.
 * - {@see Block_Visibility} قواعد نمایش شرطی (دسته، برچسب، تاکسونومی، نوع محتوا، نقش، اشتراک و…).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocks
 */
class Blocks {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'category' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * افزودن دسته‌بندی بلوک.
	 *
	 * @param array $categories دسته‌ها.
	 * @return array
	 */
	public function category( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'manacore',
				'title' => __( 'ManaCore — فیلم و سریال', 'manacore' ),
				'icon'  => 'video-alt2',
			)
		);
		return $categories;
	}

	/**
	 * تعریف بلوک‌ها.
	 *
	 * @return array
	 */
	public function definitions() {
		$blocks = array(

			'manacore/titles-grid'    => array(
				'title'       => __( 'شبکه‌ی آثار', 'manacore' ),
				'description' => __( 'حلقه‌ی فیلم، سریال و انیمه با کوئری، چیدمان، کارت و نمایش شرطی کاملاً قابل تنظیم.', 'manacore' ),
				'icon'        => 'grid-view',
				'attributes'  => Block_Support::loop_attributes(
					array(
						'ranked' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					)
				),
				'render'      => array( $this, 'render_titles_grid' ),
			),

			'manacore/hero-slider'    => array(
				'title'       => __( 'اسلایدر ویژه', 'manacore' ),
				'description' => __( 'اسلایدر بزرگ بالای صفحه با کنترل کامل روی منبع، پخش خودکار و اجزای نمایش.', 'manacore' ),
				'icon'        => 'slides',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					Block_Query::attributes(),
					array(
						'sliderStyle'  => array(
							'type'    => 'string',
							'default' => 'cinematic',
						),
						'effect'       => array(
							'type'    => 'string',
							'default' => 'fade',
						),
						'contentAlign' => array(
							'type'    => 'string',
							'default' => 'start',
						),
						'overlay'      => array(
							'type'    => 'number',
							'default' => 0,
						),
						'height'       => array(
							'type'    => 'string',
							'default' => 'medium',
						),
						'autoplay'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'interval'     => array(
							'type'    => 'number',
							'default' => 7,
						),
						'showDots'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showArrows'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showLogo'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showMeta'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showGenres'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'genreCount'   => array(
							'type'    => 'number',
							'default' => 3,
						),
						'showExcerpt'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'excerptWords' => array(
							'type'    => 'number',
							'default' => 28,
						),
						'showTrailer'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'primaryLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
						'trailerLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_hero_slider' ),
			),

			'manacore/title-meta'     => array(
				'title'       => __( 'مشخصات اثر', 'manacore' ),
				'description' => __( 'جدول مشخصات اثر جاری با انتخاب دقیق فیلدها، چیدمان و پیوند ترم‌ها.', 'manacore' ),
				'icon'        => 'list-view',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'fields'     => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'linkTerms'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'metaLayout' => array(
							'type'    => 'string',
							'default' => 'rows',
						),
						'hideEmpty'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'labelWidth' => array(
							'type'    => 'number',
							'default' => 0,
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_title_meta' ),
			),

			'manacore/download-links' => array(
				'title'       => __( 'لینک‌های دانلود', 'manacore' ),
				'description' => __( 'جدول لینک‌های دانلود و پخش با فیلتر نوع، کیفیت و فصل.', 'manacore' ),
				'icon'        => 'download',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'boxStyle'   => array(
							'type'    => 'string',
							'default' => 'cards',
						),
						'showIcon'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCount'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showNotice' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showTabs'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'types'      => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'qualities'  => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'season'     => array(
							'type'    => 'number',
							'default' => 0,
						),
						'openFirst'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_links' ),
			),

			'manacore/rating-box'     => array(
				'title'       => __( 'جعبه‌ی امتیاز', 'manacore' ),
				'description' => __( 'امتیازهای IMDb/TMDB/MAL/سردبیر و امتیازدهی کاربران با انتخاب منابع.', 'manacore' ),
				'icon'        => 'star-filled',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'showScores'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'scoreSources'   => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'showBars'       => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showUserRating' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSummary'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'userLabel'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'postId'         => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_rating_box' ),
			),

			'manacore/cast-list'      => array(
				'title'       => __( 'فهرست بازیگران', 'manacore' ),
				'description' => __( 'بازیگران و نقش‌هایشان به شکل ریل افقی یا شبکه‌ای.', 'manacore' ),
				'icon'        => 'groups',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'count'         => array(
							'type'    => 'number',
							'default' => 12,
						),
						'offset'        => array(
							'type'    => 'number',
							'default' => 0,
						),
						'layout'        => array(
							'type'    => 'string',
							'default' => 'carousel',
						),
						'columns'       => array(
							'type'    => 'number',
							'default' => 6,
						),
						'columnsTablet' => array(
							'type'    => 'number',
							'default' => 4,
						),
						'columnsMobile' => array(
							'type'    => 'number',
							'default' => 3,
						),
						'showPhoto'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCharacter' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'imageRatio'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'postId'        => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_cast' ),
			),

			'manacore/trailer'        => array(
				'title'       => __( 'تریلر', 'manacore' ),
				'description' => __( 'پخش‌کننده‌ی تریلر اثر جاری با نسبت تصویر و پوستر دلخواه.', 'manacore' ),
				'icon'        => 'video-alt3',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'videoRatio' => array(
							'type'    => 'string',
							'default' => '16-9',
						),
						'showPoster' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'metaKey'    => array(
							'type'    => 'string',
							'default' => 'manacore_trailer_url',
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_trailer' ),
			),

			'manacore/filter-bar'     => array(
				'title'       => __( 'نوار فیلتر', 'manacore' ),
				'description' => __( 'فیلتر آثار بر اساس تاکسونومی‌های انتخابی، جستجو و مرتب‌سازی.', 'manacore' ),
				'icon'        => 'filter',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						// آرایه‌ی تاکسونومی‌ها؛ رشته‌ی کاما‌جدا هم برای سازگاری پذیرفته می‌شود.
						'taxonomies'    => array(
							'type'    => 'array',
							'default' => array( 'genre', 'release_year', 'country' ),
							'items'   => array( 'type' => 'string' ),
						),
						'showSort'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSearch'    => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showSubmit'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showReset'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showActiveChips' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'submitLabel'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'hideEmptyTerms'=> array(
							'type'    => 'boolean',
							'default' => true,
						),
						'termLimit'     => array(
							'type'    => 'number',
							'default' => 200,
						),
						'formLayout'    => array(
							'type'    => 'string',
							'default' => 'inline',
						),
					)
				),
				'render'      => array( $this, 'render_filter_bar' ),
			),

			'manacore/search-box'     => array(
				'title'       => __( 'جستجوی زنده', 'manacore' ),
				'description' => __( 'کادر جستجو با نتایج آنی و محدودسازی به نوع‌های محتوای دلخواه.', 'manacore' ),
				'icon'        => 'search',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'placeholder'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'buttonLabel'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'showButton'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showIcon'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'liveResults'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'resultCount'  => array(
							'type'    => 'number',
							'default' => 8,
						),
						'searchTypes'  => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'inputSize'    => array(
							'type'    => 'string',
							'default' => 'medium',
						),
					)
				),
				'render'      => array( $this, 'render_search_box' ),
			),

			'manacore/episodes-list'  => array(
				'title'       => __( 'فهرست قسمت‌ها', 'manacore' ),
				'description' => __( 'قسمت‌های سریال یا انیمه به تفکیک فصل با ترتیب و اجزای دلخواه.', 'manacore' ),
				'icon'        => 'playlist-video',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'seasonNumber'   => array(
							'type'    => 'number',
							'default' => 0,
						),
						'episodeOrder'   => array(
							'type'    => 'string',
							'default' => 'ASC',
						),
						'seasonOrder'    => array(
							'type'    => 'string',
							'default' => 'ASC',
						),
						'limit'          => array(
							'type'    => 'number',
							'default' => 0,
						),
						'openSeason'     => array(
							'type'    => 'string',
							'default' => 'first',
						),
						'showAirDate'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showEpisodeName'=> array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCount'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showThumb'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'postId'         => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_episodes' ),
			),
		);

		/**
		 * فیلتر تعریف بلوک‌های ManaCore.
		 *
		 * @param array $blocks تعریف‌ها.
		 */
		return (array) apply_filters( 'manacore_block_definitions', $blocks );
	}

	/**
	 * فهرست بلوک‌ها برای ثبت در سمت ویرایشگر (JS).
	 *
	 * ساختار ویژگی‌ها و پشتیبانی‌ها یک‌بار در PHP تعریف می‌شود و همان
	 * تعریف برای ثبت سمت جاوااسکریپت هم استفاده می‌گردد تا هیچ‌گاه
	 * دو فهرست ناهمگون نداشته باشیم.
	 *
	 * @return array
	 */
	public function editor_registry() {
		$registry = array();

		foreach ( $this->definitions() as $name => $block ) {
			$registry[ $name ] = array(
				'title'       => $block['title'],
				'description' => $block['description'],
				'icon'        => $block['icon'],
				'category'    => 'manacore',
				'attributes'  => $block['attributes'],
				'supports'    => isset( $block['supports'] ) ? $block['supports'] : $this->default_supports(),
			);
		}

		return $registry;
	}

	/**
	 * ثبت بلوک‌ها در سمت سرور.
	 */
	public function register() {
		foreach ( $this->definitions() as $name => $block ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => $block['title'],
					'description'     => $block['description'],
					'category'        => 'manacore',
					'icon'            => $block['icon'],
					'attributes'      => $block['attributes'],
					'render_callback' => $block['render'],
					'supports'        => isset( $block['supports'] ) ? $block['supports'] : $this->default_supports(),
				)
			);
		}
	}

	/**
	 * پشتیبانی‌های پیش‌فرض بلوک.
	 *
	 * @return array
	 */
	protected function default_supports() {
		return array(
			'html'       => false,
			'anchor'     => true,
			'align'      => array( 'wide', 'full' ),
			'spacing'    => array(
				'margin'  => true,
				'padding' => true,
			),
			'color'      => array(
				'background' => true,
				'text'       => true,
				'gradients'  => true,
			),
			'typography' => array(
				'fontSize'   => true,
				'lineHeight' => true,
			),
		);
	}

	/**
	 * اسکریپت ویرایشگر.
	 */
	public function editor_assets() {
		wp_enqueue_script(
			'manacore-blocks',
			MANACORE_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			MANACORE_VERSION,
			true
		);

		wp_localize_script( 'manacore-blocks', 'manaCoreBlocks', Block_Data::payload() );
		// از inline script استفاده می‌کنیم تا نوع داده‌ها (بولین/عدد) دست‌نخورده بماند.
		wp_add_inline_script(
			'manacore-blocks',
			'window.manaCoreBlockRegistry = ' . wp_json_encode( $this->editor_registry() ) . ';',
			'before'
		);

		wp_enqueue_style(
			'manacore-blocks-editor',
			MANACORE_URL . 'assets/css/editor.css',
			array(),
			MANACORE_VERSION
		);
	}

	/* ---------------------------------------------------------------------
	 * کمکی‌ها
	 * ------------------------------------------------------------------ */

	/**
	 * شناسه‌ی اثر هدف یک بلوک تک‌اثری.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return int
	 */
	protected function target_post( $attrs ) {
		if ( ! empty( $attrs['postId'] ) ) {
			return (int) $attrs['postId'];
		}

		$post_id = Block_Visibility::current_post_id();

		/*
		 * در ویرایشگر، بلوک‌های «یک اثر» (مشخصات، دانلود، بازیگران، …)
		 * هیچ زمینه‌ی پستی ندارند و همه‌شان پیام «محتوایی برای نمایش
		 * وجود ندارد» را نشان می‌دادند؛ یعنی چیدمان قالب در ویرایشگر
		 * عملاً خالی دیده می‌شد. برای پیش‌نمایش، یک اثر نمونه انتخاب
		 * می‌شود تا ویرایشگر همان چیزی را نشان دهد که بازدیدکننده
		 * می‌بیند. این جایگزینی فقط در پیش‌نمایش رخ می‌دهد و خروجی
		 * سمت کاربر را تغییر نمی‌دهد.
		 */
		if ( ! $post_id && Block_Support::is_editor_preview() ) {
			$post_id = $this->sample_post_id();
		}

		return $post_id;
	}

	/**
	 * شناسه‌ی یک اثر نمونه برای پیش‌نمایش ویرایشگر.
	 *
	 * @return int صفر اگر هیچ اثری منتشر نشده باشد.
	 */
	protected function sample_post_id() {
		$cached = wp_cache_get( 'manacore_sample_post' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		/*
		 * چند اثر خوانده می‌شود، نه یکی: تازه‌ترین اثر ممکن است تازه
		 * ساخته و خالی باشد و همان پیام «محتوایی نیست» را بدهد. اثری
		 * ترجیح داده می‌شود که بیشترین داده را دارد تا پیش‌نمایش
		 * ویرایشگر نماینده‌ی واقعی طراحی باشد.
		 */
		$posts = get_posts(
			array(
				'post_type'        => manacore_title_post_types(),
				'post_status'      => 'publish',
				'numberposts'      => 20,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);

		$id   = 0;
		$best = -1;

		foreach ( (array) $posts as $candidate ) {
			$candidate = (int) $candidate;
			$score     = 0;

			if ( class_exists( __NAMESPACE__ . '\Links' ) && Links::count( $candidate ) > 0 ) {
				$score += 4;
			}
			if ( get_post_meta( $candidate, 'manacore_cast', true ) ) {
				$score += 2;
			}
			if ( get_post_meta( $candidate, 'manacore_trailer_url', true ) ) {
				$score += 2;
			}
			if ( has_post_thumbnail( $candidate ) || get_post_meta( $candidate, 'manacore_poster_url', true ) ) {
				$score += 1;
			}
			if ( get_post_meta( $candidate, 'manacore_imdb_rating', true ) ) {
				$score += 1;
			}

			if ( $score > $best ) {
				$best = $score;
				$id   = $candidate;
			}
		}
		wp_cache_set( 'manacore_sample_post', $id, '', 300 );

		return $id;
	}

	/**
	 * تبدیل مقدار به آرایه (پشتیبانی از رشته‌ی کاما‌جدا برای سازگاری).
	 *
	 * @param mixed $value مقدار.
	 * @return array
	 */
	protected function to_array( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'strval', $value ), 'strlen' ) );
		}
		$parts = preg_split( '/[\s,،]+/', (string) $value );
		return $parts ? array_values( array_filter( $parts, 'strlen' ) ) : array();
	}

	/* ---------------------------------------------------------------------
	 * رندرها
	 * ------------------------------------------------------------------ */

	/**
	 * رندر شبکه‌ی آثار.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_titles_grid( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$attrs = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( Block_Support::loop_attributes() )
		);

		$posts = Block_Support::get_posts( $attrs, 48 );
		if ( empty( $posts ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-titles-block' );
		}

		$layout    = sanitize_key( (string) $attrs['layout'] );
		$card_args = Block_Support::card_args( $attrs );

		/*
		 * کارت رتبه‌دار سینورا: روی ردیف‌های داغ (مثل «پرطرفدارترین‌های هفته»)
		 * شماره‌ی رتبه با خط دوران سبز گوشه‌ی کارت می‌نشیند.
		 */
		$ranked = ! empty( $attrs['ranked'] );

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-titles-block', 'is-layout-' . $layout ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		printf(
			'<div class="%1$s"%2$s>',
			esc_attr( Block_Support::grid_classes( $attrs ) ),
			Block_Support::grid_style( $attrs ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		foreach ( $posts as $index => $post ) {
			$card = Templates::card( $post->ID, $card_args );

			if ( $ranked ) {
				$card = $this->card_with_rank( $card, (int) $index + 1 );
			}

			echo $card; // phpcs:ignore WordPress.Security.EscapeOutput — خروجی Templates::card است.
		}

		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * افزودن شماره‌ی رتبه به کارت (کارت رتبه‌دار سینورا).
	 *
	 * شماره با خط دوران سبز گوشه‌ی کارت می‌نشیند؛ کارت باید نسبت به خودش
	 * positioned باشد تا شماره به آن لنگر شود.
	 *
	 * @param string $card خروجی Templates::card.
	 * @param int    $rank شماره‌ی رتبه (از ۱).
	 * @return string
	 */
	protected function card_with_rank( $card, $rank ) {
		$card = (string) $card;

		if ( '' === $card || $rank < 1 ) {
			return $card;
		}

		$rank  = (int) $rank;
		$fa    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$label = strtr( (string) $rank, array_combine(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$fa
		) );

		/*
		 * نخستین تگ article کارت را با فرزند رتبه گسترش می‌دهیم. شماره داخل
		 * کادر کارت می‌نشیند (کارت overflow:hidden دارد و بیرون‌زدگی مرجع
		 * سینورا در این ساختار بریده می‌شود).
		 */
		$card = (string) preg_replace(
			'/<article\s([^>]*?)>/',
			'<article $1><span class="mc-rank" aria-hidden="true">' . esc_html( $label ) . '</span>',
			$card,
			1,
			$count
		);

		return $card;
	}

	/**
	 * رندر اسلایدر ویژه.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_hero_slider( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/hero-slider']['attributes'] )
		);

		// اسلایدر پیش‌فرض روی «منتخب سردبیر» و تعداد کمتر تنظیم می‌شود.
		if ( 'latest' === $attrs['source'] && ! isset( $attrs['sourceTouched'] ) ) {
			$attrs['source'] = 'featured';
		}

		$posts = Block_Support::get_posts( $attrs, 12 );
		if ( empty( $posts ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-hero' );
		}

		$height   = sanitize_html_class( (string) $attrs['height'] );
		$autoplay = ! empty( $attrs['autoplay'] );
		$interval = max( 2, min( 60, (int) $attrs['interval'] ) );
		$total    = count( $posts );

		/*
		 * هر گزینه‌ی ظاهری با فهرست مشترک Block_Data اعتبارسنجی می‌شود؛
		 * بنابراین مقدار دست‌ساز یا کهنه در ویژگی‌های بلوک هرگز به کلاس
		 * بی‌اثر یا نامعتبر در HTML تبدیل نمی‌شود.
		 */
		$style  = Block_Support::pick( $attrs['sliderStyle'], Block_Data::slider_styles(), 'cinematic' );
		$effect = Block_Support::pick( $attrs['effect'], Block_Data::slider_effects(), 'fade' );
		$align  = Block_Support::pick( $attrs['contentAlign'], Block_Data::slider_alignments(), 'start' );

		$overlay = max( 0, min( 100, (int) $attrs['overlay'] ) );
		$inline  = $overlay ? sprintf( ' style="--mc-hero-overlay:%s"', esc_attr( $overlay / 100 ) ) : '';

		ob_start();
		printf(
			'<div %1$s%5$s data-manacore-hero data-autoplay="%2$s" data-interval="%3$d" data-effect="%4$s">',
			Block_Support::wrapper( // phpcs:ignore WordPress.Security.EscapeOutput
				$attrs,
				array(
					'manacore-hero',
					'is-height-' . $height,
					'is-slider-' . $style,
					'is-effect-' . $effect,
					'is-align-' . $align,
				)
			),
			$autoplay ? 'true' : 'false',
			esc_attr( $interval * 1000 ),
			esc_attr( $effect ),
			$inline // phpcs:ignore WordPress.Security.EscapeOutput
		);

		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="manacore-hero-track">';

		foreach ( $posts as $index => $post ) {
			$backdrop = get_post_meta( $post->ID, 'manacore_backdrop_url', true );
			$backdrop = $backdrop ? $backdrop : manacore_poster_url( $post->ID, 'full' );
			$logo     = ! empty( $attrs['showLogo'] ) ? get_post_meta( $post->ID, 'manacore_logo_url', true ) : '';
			$rating   = Templates::best_rating( $post->ID );
			$year     = Templates::year( $post->ID );
			$trailer  = ! empty( $attrs['showTrailer'] ) ? get_post_meta( $post->ID, 'manacore_trailer_url', true ) : '';
			?>
			<div class="manacore-hero-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-hero-slide="<?php echo esc_attr( $index ); ?>">
				<img class="manacore-hero-bg" src="<?php echo esc_url( $backdrop ); ?>"
					alt="" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" decoding="async" />
				<div class="manacore-hero-content">
					<?php if ( $logo ) : ?>
						<img class="manacore-hero-logo" src="<?php echo esc_url( $logo ); ?>"
							alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy" />
					<?php else : ?>
						<h2 class="manacore-hero-title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showMeta'] ) ) : ?>
						<div class="manacore-hero-meta">
							<?php if ( $rating ) : ?>
								<span class="manacore-chip is-quality">⭐ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
							<?php endif; ?>
							<?php if ( $year ) : ?>
								<span class="manacore-chip"><?php echo esc_html( $year ); ?></span>
							<?php endif; ?>
							<?php
							if ( ! empty( $attrs['showGenres'] ) ) {
								$genres = get_the_terms( $post->ID, 'genre' );
								if ( $genres && ! is_wp_error( $genres ) ) {
									$limit = max( 1, (int) $attrs['genreCount'] );
									foreach ( array_slice( $genres, 0, $limit ) as $genre ) {
										echo '<span class="manacore-chip">' . esc_html( $genre->name ) . '</span>';
									}
								}
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showExcerpt'] ) ) : ?>
						<p class="manacore-hero-excerpt">
							<?php
							echo esc_html(
								wp_trim_words(
									wp_strip_all_tags( get_the_excerpt( $post ) ),
									max( 6, (int) $attrs['excerptWords'] ),
									'…'
								)
							);
							?>
						</p>
					<?php endif; ?>

					<div class="manacore-hero-actions">
						<a class="manacore-btn is-primary" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
							<?php
							echo esc_html(
								! empty( $attrs['primaryLabel'] )
									? $attrs['primaryLabel']
									: __( 'مشاهده و دانلود', 'manacore' )
							);
							?>
						</a>
						<?php if ( $trailer ) : ?>
							<button type="button" class="manacore-btn is-ghost"
								data-manacore-play="<?php echo esc_url( $trailer ); ?>"
								data-title="<?php echo esc_attr( get_the_title( $post ) ); ?>">
								<?php
								echo esc_html(
									! empty( $attrs['trailerLabel'] )
										? $attrs['trailerLabel']
										: __( 'تریلر', 'manacore' )
								);
								?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		echo '</div>';

		if ( ! empty( $attrs['showArrows'] ) && $total > 1 ) {
			printf(
				'<button type="button" class="manacore-hero-arrow is-prev" data-hero-prev aria-label="%s"></button>'
				. '<button type="button" class="manacore-hero-arrow is-next" data-hero-next aria-label="%s"></button>',
				esc_attr__( 'اسلاید قبلی', 'manacore' ),
				esc_attr__( 'اسلاید بعدی', 'manacore' )
			);
		}

		if ( ! empty( $attrs['showDots'] ) && $total > 1 ) {
			echo '<div class="manacore-hero-dots" role="tablist">';
			foreach ( $posts as $index => $post ) {
				printf(
					'<button type="button" class="manacore-hero-dot%1$s" data-hero-dot="%2$d" role="tab" aria-selected="%3$s" aria-label="%4$s"></button>',
					0 === $index ? ' is-active' : '',
					esc_attr( $index ),
					0 === $index ? 'true' : 'false',
					esc_attr( get_the_title( $post ) )
				);
			}
			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر مشخصات اثر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_title_meta( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/title-meta']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-meta-block' );
		}

		$layout = in_array( $attrs['metaLayout'], array( 'rows', 'columns', 'inline' ), true )
			? $attrs['metaLayout']
			: 'rows';

		$html = Templates::meta_list(
			$post_id,
			array(
				'fields'      => $this->to_array( $attrs['fields'] ),
				'link_terms'  => ! empty( $attrs['linkTerms'] ),
				'layout'      => $layout,
				'hide_empty'  => ! empty( $attrs['hideEmpty'] ),
				'label_width' => max( 0, (int) $attrs['labelWidth'] ),
			)
		);

		if ( ! $html ) {
			return Block_Support::render_empty( $attrs, 'manacore-meta-block' );
		}

		return '<div ' . Block_Support::wrapper( $attrs, 'manacore-meta-block' ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput
			. Block_Support::render_header( $attrs )
			. $html
			. '</div>';
	}

	/**
	 * رندر لینک‌های دانلود.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_links( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/download-links']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-links-block' );
		}

		$level = isset( $attrs['headingLevel'] ) ? strtolower( (string) $attrs['headingLevel'] ) : 'h2';
		if ( ! in_array( $level, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$level = 'h2';
		}

		$box_style = Block_Support::pick( $attrs['boxStyle'], Block_Data::download_styles(), 'cards' );

		$html = Templates::links(
			$post_id,
			array(
				'box_style'    => $box_style,
				'heading'      => (string) $attrs['heading'],
				'heading_tag'  => $level,
				'show_heading' => '' !== trim( (string) $attrs['heading'] ),
				'show_icon'    => ! empty( $attrs['showIcon'] ),
				'show_count'   => ! empty( $attrs['showCount'] ),
				'show_notice'  => ! empty( $attrs['showNotice'] ),
				'show_tabs'    => ! empty( $attrs['showTabs'] ),
				'types'        => $this->to_array( $attrs['types'] ),
				'qualities'    => $this->to_array( $attrs['qualities'] ),
				'season'       => max( 0, (int) $attrs['season'] ),
				'open_first'   => ! empty( $attrs['openFirst'] ),
			)
		);

		if ( ! $html ) {
			return Block_Support::render_empty( $attrs, 'manacore-links-block' );
		}

		return '<div ' . Block_Support::wrapper( $attrs, 'manacore-links-block' ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * رندر جعبه‌ی امتیاز.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_rating_box( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/rating-box']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-rating-box' );
		}

		$map = array(
			'imdb_rating'  => array( 'manacore_imdb_rating', 'IMDb' ),
			'tmdb_rating'  => array( 'manacore_tmdb_rating', 'TMDB' ),
			'mal_rating'   => array( 'manacore_mal_rating', 'MAL' ),
			'editor_score' => array( 'manacore_editor_score', __( 'سردبیر', 'manacore' ) ),
			'user_rating'  => array( 'manacore_user_rating', __( 'کاربران', 'manacore' ) ),
		);

		$selected = $this->to_array( $attrs['scoreSources'] );
		if ( empty( $selected ) ) {
			$selected = array( 'imdb_rating', 'tmdb_rating', 'mal_rating', 'editor_score' );
		}

		$scores = array();
		if ( ! empty( $attrs['showScores'] ) ) {
			foreach ( $selected as $key ) {
				$key = sanitize_key( $key );
				if ( ! isset( $map[ $key ] ) ) {
					continue;
				}
				$value = (float) get_post_meta( $post_id, $map[ $key ][0], true );
				if ( $value > 0 ) {
					$scores[ $map[ $key ][1] ] = $value;
				}
			}
		}

		$show_user  = ! empty( $attrs['showUserRating'] ) && manacore_get_option( 'enable_ratings', 1 );
		$user_avg   = (float) get_post_meta( $post_id, 'manacore_user_rating', true );
		$user_count = (int) get_post_meta( $post_id, 'manacore_user_rating_count', true );

		if ( empty( $scores ) && ! $show_user ) {
			return Block_Support::render_empty( $attrs, 'manacore-rating-box' );
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-rating-box' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $scores ) {
			echo '<div class="manacore-scores">';
			foreach ( $scores as $label => $value ) {
				?>
				<div class="manacore-score">
					<span class="manacore-score-value"><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></span>
					<span class="manacore-score-label"><?php echo esc_html( $label ); ?></span>
					<?php if ( ! empty( $attrs['showBars'] ) ) : ?>
						<span class="manacore-score-bar"><i style="width:<?php echo esc_attr( manacore_rating_percent( $value ) ); ?>%"></i></span>
					<?php endif; ?>
				</div>
				<?php
			}
			echo '</div>';
		}

		if ( $show_user ) {
			$mine = Ratings::instance()->user_vote( $post_id );
			?>
			<div class="manacore-user-rating" data-manacore-rating="<?php echo esc_attr( $post_id ); ?>">
				<span class="manacore-user-rating-label">
					<?php
					echo esc_html(
						! empty( $attrs['userLabel'] ) ? $attrs['userLabel'] : __( 'امتیاز شما:', 'manacore' )
					);
					?>
				</span>
				<div class="manacore-stars" role="radiogroup">
					<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
						<button type="button" class="manacore-star<?php echo $i <= $mine ? ' is-on' : ''; ?>"
							data-value="<?php echo esc_attr( $i ); ?>" role="radio"
							aria-checked="<?php echo $i === $mine ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: امتیاز */ __( 'امتیاز %d', 'manacore' ), $i ) ); ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
								<path fill="currentColor" d="M12 2l2.9 6.3 6.8.8-5 4.7 1.3 6.8L12 17.4 6 20.6l1.3-6.8-5-4.7 6.8-.8z"/>
							</svg>
						</button>
					<?php endfor; ?>
				</div>
				<?php if ( ! empty( $attrs['showSummary'] ) ) : ?>
					<span class="manacore-rating-summary" data-rating-summary>
						<?php if ( $user_count ) : ?>
							<?php
							printf(
								/* translators: 1: میانگین امتیاز 2: تعداد رأی */
								esc_html__( '%1$s از ۱۰ (%2$s رأی)', 'manacore' ),
								esc_html( number_format_i18n( $user_avg, 1 ) ),
								esc_html( number_format_i18n( $user_count ) )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'هنوز رأیی ثبت نشده', 'manacore' ); ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
			<?php
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * رندر فهرست بازیگران.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_cast( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/cast-list']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$cast = get_post_meta( $post_id, 'manacore_cast', true );
		$cast = is_string( $cast ) ? json_decode( $cast, true ) : $cast;
		if ( ! is_array( $cast ) || empty( $cast ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$offset = max( 0, (int) $attrs['offset'] );
		$limit  = max( 1, (int) $attrs['count'] );
		$cast   = array_slice( $cast, $offset, $limit );

		if ( empty( $cast ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$layout = sanitize_key( (string) $attrs['layout'] );
		$ratio  = sanitize_html_class( (string) $attrs['imageRatio'] );

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-cast', 'is-layout-' . $layout ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		printf(
			'<div class="manacore-cast-track%1$s"%2$s>',
			'carousel' === $layout ? ' is-scroll' : '',
			Block_Support::grid_style( $attrs ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		foreach ( $cast as $person ) {
			if ( empty( $person['name'] ) ) {
				continue;
			}
			$photo = ! empty( $person['photo'] ) ? $person['photo'] : MANACORE_URL . 'assets/avatar.svg';
			?>
			<figure class="manacore-cast-item<?php echo $ratio ? ' is-ratio-' . esc_attr( $ratio ) : ''; ?>">
				<?php if ( ! empty( $attrs['showPhoto'] ) ) : ?>
					<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $person['name'] ); ?>"
						loading="lazy" decoding="async" />
				<?php endif; ?>
				<figcaption>
					<strong><?php echo esc_html( $person['name'] ); ?></strong>
					<?php if ( ! empty( $attrs['showCharacter'] ) && ! empty( $person['character'] ) ) : ?>
						<span><?php echo esc_html( $person['character'] ); ?></span>
					<?php endif; ?>
				</figcaption>
			</figure>
			<?php
		}

		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر تریلر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_trailer( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/trailer']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-trailer' );
		}

		$meta_key = sanitize_key( (string) $attrs['metaKey'] );
		$meta_key = $meta_key ? $meta_key : 'manacore_trailer_url';
		$url      = get_post_meta( $post_id, $meta_key, true );

		if ( ! $url ) {
			return Block_Support::render_empty( $attrs, 'manacore-trailer' );
		}

		$ratio = sanitize_html_class( (string) $attrs['videoRatio'] );

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-trailer', $ratio ? 'is-ratio-' . $ratio : '' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo Player::render( $url, get_the_title( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر نوار فیلتر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_filter_bar( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/filter-bar']['attributes'] )
		);

		$list = array_filter( array_map( 'sanitize_key', $this->to_array( $attrs['taxonomies'] ) ) );

		// نگاشت مشترک با کلاس Query تا هیچ فیلدی بی‌اثر نماند.
		$param_map = manacore_filter_params();

		$layout = sanitize_key( (string) $attrs['formLayout'] );

		/*
		 * نشانی پایه‌ی آرشیو (بدون /page/N/). ویژگی action فرم باید همین باشد،
		 * وگرنه اعمال فیلتر از صفحه‌ی دوم کاربر را روی page/2 نگه می‌دارد و
		 * اگر نتیجه‌ی فیلترشده کمتر از دو صفحه باشد ۴۰۴ می‌گیرد.
		 */
		$base   = Block_Support::is_editor_preview() ? home_url( '/' ) : manacore_archive_base_url();
		$active = Block_Support::is_editor_preview() ? array() : manacore_active_filters();

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-filter-bar' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form method="get" action="<?php echo esc_url( $base ); ?>"
			class="manacore-filter-form is-layout-<?php echo esc_attr( $layout ); ?>">
			<?php
			// حفظ عبارت جستجو هنگام فیلتر کردن نتایج جستجو.
			if ( empty( $attrs['showSearch'] ) && is_search() ) {
				printf( '<input type="hidden" name="s" value="%s" />', esc_attr( get_search_query() ) );
			}
			?>
			<?php
			if ( ! empty( $attrs['showSearch'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
				?>
				<label class="manacore-filter-field is-search">
					<span class="manacore-filter-label"><?php esc_html_e( 'جستجو', 'manacore' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( $term ); ?>"
						placeholder="<?php esc_attr_e( 'نام اثر…', 'manacore' ); ?>" />
				</label>
				<?php
			}

			foreach ( $list as $taxonomy ) :
				if ( ! taxonomy_exists( $taxonomy ) || ! isset( $param_map[ $taxonomy ] ) ) {
					continue;
				}
				$param = $param_map[ $taxonomy ];
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$current = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : '';

				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => ! empty( $attrs['hideEmptyTerms'] ),
						'number'     => max( 10, min( 500, (int) $attrs['termLimit'] ) ),
						'orderby'    => 'name',
					)
				);
				$terms = is_wp_error( $terms ) ? array() : (array) $terms;

				/*
				 * اگر ترم انتخاب‌شده در فهرست نباشد (مثلاً با hide_empty حذف شده
				 * یا از حد termLimit بیرون افتاده) آن را دستی می‌افزاییم؛ وگرنه
				 * فیلد ناپدید می‌شود و کاربر هیچ راهی برای برداشتن فیلتر ندارد.
				 */
				if ( $current ) {
					$slugs = wp_list_pluck( $terms, 'slug' );
					if ( ! in_array( $current, (array) $slugs, true ) ) {
						$selected_term = get_term_by( 'slug', $current, $taxonomy );
						if ( $selected_term instanceof \WP_Term ) {
							array_unshift( $terms, $selected_term );
						}
					}
				}

				if ( empty( $terms ) ) {
					continue;
				}

				$object = get_taxonomy( $taxonomy );
				$label  = $object->labels->singular_name;
				?>
				<label class="<?php echo esc_attr( $current ? 'manacore-filter-field is-active' : 'manacore-filter-field' ); ?>">
					<span class="manacore-filter-label"><?php echo esc_html( $label ); ?></span>
					<select name="<?php echo esc_attr( $param ); ?>">
						<option value="">
							<?php
							/* translators: %s: نام تاکسونومی، مثلاً ژانر. */
							printf( esc_html__( 'همه‌ی %s', 'manacore' ), esc_html( $label ) );
							?>
						</option>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php
			endforeach;
			?>

			<?php if ( ! empty( $attrs['showSort'] ) ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$sort  = isset( $_GET['mc_sort'] ) ? sanitize_key( wp_unslash( $_GET['mc_sort'] ) ) : '';
				$sorts = manacore_sort_options();
				?>
				<label class="<?php echo esc_attr( $sort ? 'manacore-filter-field is-active' : 'manacore-filter-field' ); ?>">
					<span class="manacore-filter-label"><?php esc_html_e( 'مرتب‌سازی', 'manacore' ); ?></span>
					<select name="mc_sort">
						<?php foreach ( $sorts as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showSubmit'] ) || ! empty( $attrs['showReset'] ) ) : ?>
				<div class="manacore-filter-actions">
					<?php if ( ! empty( $attrs['showSubmit'] ) ) : ?>
						<button type="submit" class="manacore-btn is-primary">
							<?php
							echo esc_html(
								! empty( $attrs['submitLabel'] ) ? $attrs['submitLabel'] : __( 'اعمال فیلتر', 'manacore' )
							);
							?>
						</button>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showReset'] ) ) : ?>
						<?php
						/*
						 * پایه‌ی آرشیو بدون /page/N/ و بدون پرسمان؛ استفاده‌ی پیشین از
						 * strtok تنها رشته‌ی پرسمان را حذف می‌کرد و بخش صفحه‌بندی
						 * در نشانی باقی می‌ماند.
						 */
						?>
						<a class="manacore-btn is-ghost manacore-filter-reset"
							href="<?php echo esc_url( $base ); ?>"
							aria-disabled="<?php echo esc_attr( $active ? 'false' : 'true' ); ?>">
							<?php esc_html_e( 'پاک‌سازی', 'manacore' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</form>

		<?php
		// نشانگر فیلترهای فعال: هر برچسب پیوندی است که همان فیلتر را حذف می‌کند.
		if ( $active && ! empty( $attrs['showActiveChips'] ) ) {
			$labels = $this->filter_chip_labels( $active, $param_map );
			if ( $labels ) {
				?>
				<div class="manacore-filter-chips">
					<span class="manacore-filter-chips-title"><?php esc_html_e( 'فیلترهای فعال:', 'manacore' ); ?></span>
					<?php foreach ( $labels as $param => $text ) : ?>
						<a class="manacore-filter-chip"
							href="<?php echo esc_url( add_query_arg( array_diff_key( $active, array( $param => '' ) ), $base ) ); ?>">
							<span><?php echo esc_html( $text ); ?></span>
							<span class="manacore-filter-chip-x" aria-hidden="true">&times;</span>
							<span class="screen-reader-text"><?php esc_html_e( 'حذف این فیلتر', 'manacore' ); ?></span>
						</a>
					<?php endforeach; ?>
					<a class="manacore-filter-chip is-clear" href="<?php echo esc_url( $base ); ?>">
						<?php esc_html_e( 'حذف همه', 'manacore' ); ?>
					</a>
				</div>
				<?php
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * ساخت برچسب خوانا برای هر فیلتر فعال.
	 *
	 * @param array<string,string> $active    فیلترهای فعال (پارامتر => مقدار).
	 * @param array<string,string> $param_map نگاشت تاکسونومی => پارامتر.
	 * @return array<string,string> پارامتر => برچسب خوانا.
	 */
	protected function filter_chip_labels( $active, $param_map ) {
		$labels   = array();
		$reverse  = array_flip( $param_map );
		$sorts    = manacore_sort_options();

		foreach ( $active as $param => $value ) {
			if ( 'mc_sort' === $param ) {
				if ( isset( $sorts[ $value ] ) && '' !== $value ) {
					$labels[ $param ] = $sorts[ $value ];
				}
				continue;
			}

			if ( empty( $reverse[ $param ] ) || ! taxonomy_exists( $reverse[ $param ] ) ) {
				continue;
			}

			$names = array();
			foreach ( explode( ',', $value ) as $slug ) {
				$term = get_term_by( 'slug', sanitize_title( $slug ), $reverse[ $param ] );
				if ( $term instanceof \WP_Term ) {
					$names[] = $term->name;
				}
			}

			if ( $names ) {
				$labels[ $param ] = implode( '، ', $names );
			}
		}

		return $labels;
	}

	/**
	 * رندر کادر جستجو.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_search_box( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/search-box']['attributes'] )
		);

		$placeholder = ! empty( $attrs['placeholder'] )
			? $attrs['placeholder']
			: __( 'نام فیلم، سریال یا انیمه…', 'manacore' );

		$types = array_values( array_filter( array_map( 'sanitize_key', $this->to_array( $attrs['searchTypes'] ) ), 'post_type_exists' ) );
		$size  = sanitize_key( (string) $attrs['inputSize'] );

		ob_start();
		printf(
			'<div %1$s data-manacore-search data-live="%2$s" data-limit="%3$d"%4$s>',
			Block_Support::wrapper( $attrs, array( 'manacore-search', 'is-size-' . $size ) ), // phpcs:ignore WordPress.Security.EscapeOutput
			! empty( $attrs['liveResults'] ) ? 'true' : 'false',
			esc_attr( max( 1, min( 20, (int) $attrs['resultCount'] ) ) ),
			$types ? ' data-types="' . esc_attr( implode( ',', $types ) ) . '"' : ''
		);

		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="manacore-search-form">
			<?php if ( ! empty( $attrs['showIcon'] ) ) : ?>
				<span class="manacore-search-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18">
						<path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>
					</svg>
				</span>
			<?php endif; ?>
			<input type="search" name="s" class="manacore-search-input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				aria-label="<?php esc_attr_e( 'جستجو', 'manacore' ); ?>" autocomplete="off" />
			<?php foreach ( $types as $type ) : ?>
				<input type="hidden" name="post_type[]" value="<?php echo esc_attr( $type ); ?>" />
			<?php endforeach; ?>
			<?php if ( ! empty( $attrs['showButton'] ) ) : ?>
				<button type="submit" class="manacore-btn is-primary is-small">
					<?php
					echo esc_html(
						! empty( $attrs['buttonLabel'] ) ? $attrs['buttonLabel'] : __( 'جستجو', 'manacore' )
					);
					?>
				</button>
			<?php endif; ?>
		</form>
		<?php if ( ! empty( $attrs['liveResults'] ) ) : ?>
			<?php
			/*
			 * ناحیه‌ی زنده: نتایج با جاوااسکریپت جایگزین می‌شوند و بدون
			 * aria-live هیچ‌گاه به صفحه‌خوان اعلام نمی‌شدند. polite انتخاب
			 * شده تا تایپ کاربر را قطع نکند، و aria-atomic تا کل فهرست
			 * یک‌جا خوانده شود نه تکه‌تکه.
			 */
			?>
			<div class="manacore-search-results" data-search-results hidden
				role="status" aria-live="polite" aria-atomic="true"></div>
		<?php endif; ?>
		<?php
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * رندر فهرست قسمت‌ها.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_episodes( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/episodes-list']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$parent = in_array( get_post_type( $post_id ), manacore_serial_post_types(), true )
			? $post_id
			: (int) get_post_meta( $post_id, 'manacore_parent_title', true );

		if ( ! $parent ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$meta_query = array(
			array(
				'key'   => 'manacore_parent_title',
				'value' => $parent,
			),
		);

		$season_filter = max( 0, (int) $attrs['seasonNumber'] );
		if ( $season_filter ) {
			$meta_query[] = array(
				'key'   => 'manacore_season_number',
				'value' => $season_filter,
			);
		}

		$order = 'DESC' === strtoupper( (string) $attrs['episodeOrder'] ) ? 'DESC' : 'ASC';
		$limit = max( 0, (int) $attrs['limit'] );

		$episodes = get_posts(
			array(
				'post_type'      => 'episode',
				'posts_per_page' => $limit ? $limit : 500,
				'post_status'    => 'publish',
				'meta_key'       => 'manacore_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => array( 'meta_value_num' => $order ),
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		if ( empty( $episodes ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$by_season = array();
		foreach ( $episodes as $episode ) {
			$season                 = (int) get_post_meta( $episode->ID, 'manacore_season_number', true );
			$by_season[ $season ][] = $episode;
		}

		if ( 'DESC' === strtoupper( (string) $attrs['seasonOrder'] ) ) {
			krsort( $by_season );
		} else {
			ksort( $by_season );
		}

		$open_mode  = sanitize_key( (string) $attrs['openSeason'] );
		$season_idx = 0;

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-episodes' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		foreach ( $by_season as $season => $items ) {
			$is_open = 'all' === $open_mode || ( 'first' === $open_mode && 0 === $season_idx );
			++$season_idx;
			?>
			<details class="manacore-season" <?php echo $is_open ? 'open' : ''; ?>>
				<summary>
					<?php
					echo $season
						/* translators: %s: شماره فصل */
						? esc_html( sprintf( __( 'فصل %s', 'manacore' ), number_format_i18n( $season ) ) )
						: esc_html__( 'قسمت‌ها', 'manacore' );
					?>
					<?php if ( ! empty( $attrs['showCount'] ) ) : ?>
						<span class="manacore-badge"><?php echo esc_html( number_format_i18n( count( $items ) ) ); ?></span>
					<?php endif; ?>
				</summary>
				<ul class="manacore-episode-list">
					<?php foreach ( $items as $episode ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $episode ) ); ?>">
								<?php if ( ! empty( $attrs['showThumb'] ) ) : ?>
									<img class="manacore-episode-thumb" src="<?php echo esc_url( manacore_poster_url( $episode->ID, 'thumbnail' ) ); ?>"
										alt="" loading="lazy" decoding="async" />
								<?php endif; ?>
								<span class="manacore-episode-number">
									<?php echo esc_html( number_format_i18n( (int) get_post_meta( $episode->ID, 'manacore_episode_number', true ) ) ); ?>
								</span>
								<?php if ( ! empty( $attrs['showEpisodeName'] ) ) : ?>
									<span class="manacore-episode-title"><?php echo esc_html( get_the_title( $episode ) ); ?></span>
								<?php endif; ?>
								<?php
								$air = ! empty( $attrs['showAirDate'] )
									? get_post_meta( $episode->ID, 'manacore_air_date', true )
									: '';
								?>
								<?php if ( $air ) : ?>
									<time class="manacore-episode-date" datetime="<?php echo esc_attr( $air ); ?>">
										<?php echo esc_html( $air ); ?>
									</time>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
			<?php
		}

		echo '</div>';
		return (string) ob_get_clean();
	}
}

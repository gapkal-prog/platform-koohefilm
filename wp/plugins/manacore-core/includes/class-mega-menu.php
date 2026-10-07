<?php
/**
 * مگامنوی ژانرها — سرویس مشترک افزونه و قالب.
 *
 * این کلاس **داده**ی مگامنو را می‌سازد و از سه جا مصرف می‌شود:
 *   ۱) قالب کوهه (`inc/blocks.php`) که همان داده را به آیتم‌های واقعی
 *      فهرست راهبری وردپرس تبدیل می‌کند تا مدیر بتواند در پیشخوان
 *      ویرایشش کند.
 *   ۲) شورت‌کد `[manacore_mega_menu]` برای قالب‌های غیرِ کوهه.
 *   ۳) پنل مدیریت (زبان «مگامنو») برای تنظیم متن‌ها، تعداد و کارت ویژه.
 *
 * ساختار پنل، بازآفرینی مگامنوی مرجع است: سه ستون
 *   ۱) شبکه‌ی ژانرها (ترم‌های واقعی تاکسونومی)
 *   ۲) فهرست دسترسی سریع
 *   ۳) اثر ویژه‌ی انتخابی (پوستر با روکش گرادیانی)
 *
 * **همه‌ی متن‌ها، تعدادها، تاکسونومی، پیوند میانی و اثر ویژه** از تنظیمات
 * خوانده می‌شوند (`manacore_settings`) و با فیلتر `manacore_mega_settings`
 * قابل تغییرند. پیش‌فرض‌ها همان متن‌های مرجع‌اند تا ظاهر فعلی سایت
 * دست‌نخورده بماند؛ ولی هیچ رشته‌ای دیگر سخت‌کد نیست.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Mega_Menu
 */
class Mega_Menu {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_shortcode( 'manacore_mega_menu', array( $this, 'shortcode' ) );

		/*
		 * با ذخیره‌ی تنظیمات، کش داده‌های مگامنو پاک می‌شود تا تغییر متن
		 * یا تعداد ژانرها بی‌درنگ دیده شود (ترنزینت‌ها یک‌ساعته‌اند).
		 */
		add_action( 'update_option_manacore_settings', array( __CLASS__, 'flush_cache' ) );
		add_action( 'edited_term', array( __CLASS__, 'flush_cache' ) );
		add_action( 'created_term', array( __CLASS__, 'flush_cache' ) );
		add_action( 'delete_term', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * تنظیمات مگامنو با پیش‌فرض‌های امن.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings() {
		$defaults = array(
			'enabled'        => (bool) manacore_get_option( 'mega_enabled', 1 ),
			'taxonomy'       => (string) manacore_get_option( 'mega_taxonomy', 'genre' ),
			'number'         => (int) manacore_get_option( 'mega_terms', 12 ),
			'columns'        => (int) manacore_get_option( 'mega_columns', 3 ),
			'hub_url'        => (string) manacore_get_option( 'mega_hub_url', '' ),
			'eyebrow'        => (string) manacore_get_option( 'mega_eyebrow', __( 'یک دنیا انتخاب', 'manacore' ) ),
			'title'          => (string) manacore_get_option( 'mega_title', __( 'حال‌وهوای امشبت چیه؟', 'manacore' ) ),
			'quick_label'    => (string) manacore_get_option( 'mega_quick_label', __( 'به انتخاب سینورا', 'manacore' ) ),
			'feature_label'  => (string) manacore_get_option( 'mega_feature_label', __( 'انتخاب ویژه این هفته', 'manacore' ) ),
			'cta_label'      => (string) manacore_get_option( 'mega_cta_label', __( 'کشف داستان', 'manacore' ) ),
			'featured_id'    => (int) manacore_get_option( 'mega_featured_id', 0 ),
			'rating_label'   => (string) manacore_get_option( 'mega_rating_label', __( 'بالاترین امتیازها', 'manacore' ) ),
			'newest_label'   => (string) manacore_get_option( 'mega_newest_label', __( 'تازه‌های کوهه', 'manacore' ) ),
			'korean_label'   => (string) manacore_get_option( 'mega_korean_label', __( 'فیلم و سریال کره‌ای', 'manacore' ) ),
			'cast_label'     => (string) manacore_get_option( 'mega_cast_label', __( 'بازیگران و کارگردان‌ها', 'manacore' ) ),
			'show_korean'    => (bool) manacore_get_option( 'mega_show_korean', 1 ),
			'show_cast'      => (bool) manacore_get_option( 'mega_show_cast', 1 ),
		);

		/* تاکسونومی نامعتبر به ژانر برمی‌گردد تا کوئری هرگز خطا ندهد. */
		if ( ! taxonomy_exists( $defaults['taxonomy'] ) ) {
			$defaults['taxonomy'] = 'genre';
		}

		$defaults['number']  = max( 3, min( 30, (int) $defaults['number'] ) );
		$defaults['columns'] = max( 2, min( 4, (int) $defaults['columns'] ) );

		/**
		 * فیلتر تنظیمات مگامنو.
		 *
		 * @param array<string,mixed> $defaults تنظیمات نرمال‌شده.
		 */
		return (array) apply_filters( 'manacore_mega_settings', $defaults );
	}

	/**
	 * آیا پنل مگامنو فعال است؟
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = self::settings();

		return ! empty( $settings['enabled'] );
	}

	/**
	 * نشانی «مرکز دسته‌بندی‌ها» (ستون اول و پیوند «دسته‌بندی‌ها»).
	 *
	 * اولویت: تنظیم مدیر → برگه‌ی `categories-hub` → برگه‌ی `browse` →
	 * آرشیو فیلم → خانه. پیش‌تر `/categories-hub/` سخت‌کد بود و اگر آن
	 * برگه وجود نداشت، همه‌ی پیوندهای ستون اول ۴۰۴ می‌دادند.
	 *
	 * @return string
	 */
	public static function hub_url() {
		$settings = self::settings();
		$url      = trim( (string) $settings['hub_url'] );

		if ( '' === $url ) {
			foreach ( array( 'categories-hub', 'browse', 'movies' ) as $slug ) {
				$page = get_page_by_path( $slug, OBJECT, 'page' );
				if ( $page && 'publish' === get_post_status( $page->ID ) ) {
					$url = (string) get_permalink( $page->ID );
					break;
				}
			}
		}

		if ( '' === $url ) {
			$archive = get_post_type_archive_link( 'movie' );
			$url     = $archive ? $archive : home_url( '/' );
		}

		/**
		 * فیلتر نشانی مرکز دسته‌بندی‌ها.
		 *
		 * @param string $url نشانی.
		 */
		return (string) apply_filters( 'manacore_mega_hub_url', $url );
	}

	/**
	 * شورت‌کد [manacore_mega_menu].
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$settings = self::settings();
		$atts     = shortcode_atts(
			array(
				'taxonomy' => $settings['taxonomy'],
				'number'   => $settings['number'],
				'columns'  => $settings['columns'],
				'featured' => $settings['featured_id'],
			),
			$atts,
			'manacore_mega_menu'
		);

		return self::render(
			array(
				'taxonomy' => (string) $atts['taxonomy'],
				'number'   => absint( $atts['number'] ),
				'columns'  => absint( $atts['columns'] ),
				'featured' => absint( $atts['featured'] ),
			)
		);
	}

	/**
	 * رندر پنل مگامنو (برای قالب‌های غیرِ کوهه و پیش‌نمایش).
	 *
	 * @param array $args آرگومان‌ها.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$settings = self::settings();

		if ( empty( $settings['enabled'] ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'taxonomy' => $settings['taxonomy'],
				'number'   => $settings['number'],
				'columns'  => $settings['columns'],
				'featured' => $settings['featured_id'],
			)
		);

		$taxonomy = taxonomy_exists( (string) $args['taxonomy'] ) ? (string) $args['taxonomy'] : 'genre';
		$number   = max( 3, min( 30, (int) $args['number'] ) );
		$columns  = max( 2, min( 4, (int) $args['columns'] ) );

		$terms = self::menu_terms( $taxonomy, $number );
		if ( empty( $terms ) ) {
			return '';
		}

		$featured_id = self::featured_id( (int) $args['featured'] );
		$backdrop    = $featured_id ? manacore_backdrop_url( $featured_id ) : '';

		ob_start();
		?>
		<div class="manacore-mega" data-mega-menu hidden>
			<div class="manacore-mega__main">
				<p class="koohe-eyebrow"><?php echo esc_html( (string) $settings['eyebrow'] ); ?></p>
				<h3 class="manacore-mega__heading"><?php echo esc_html( (string) $settings['title'] ); ?></h3>
				<div class="manacore-mega__genres" style="--mc-mega-columns:<?php echo esc_attr( $columns ); ?>">
					<?php foreach ( $terms as $term ) : ?>
						<a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
							<?php echo esc_html( $term->name ); ?>
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="manacore-mega__quick">
				<p class="koohe-eyebrow"><?php echo esc_html( (string) $settings['quick_label'] ); ?></p>
				<?php foreach ( self::quick_links() as $link ) : ?>
					<a href="<?php echo esc_url( $link['url'] ); ?>">
						<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $link['icon']; // phpcs:ignore WordPress.Security.EscapeOutput — SVG ثابت داخلی ?></svg>
						<?php echo esc_html( $link['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $featured_id ) : ?>
				<a class="manacore-mega__feature" href="<?php echo esc_url( get_permalink( $featured_id ) ); ?>">
					<img src="<?php echo esc_url( $backdrop ); ?>" alt="<?php echo esc_attr( get_the_title( $featured_id ) ); ?>" loading="lazy" decoding="async" />
					<span>
						<small><?php echo esc_html( (string) $settings['feature_label'] ); ?></small>
						<strong><?php echo esc_html( get_the_title( $featured_id ) ); ?></strong>
						<span>
							<?php echo esc_html( (string) $settings['cta_label'] ); ?>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 12-7 7-7-7" transform="rotate(-90 12 12)"/></svg>
						</span>
					</span>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ترم‌های منو با cache؛ در نبود ترم، برگشت به فهرست مرجع.
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @param int    $number   حداکثر تعداد.
	 * @return array<int,\WP_Term>
	 */
	public static function menu_terms( $taxonomy, $number ) {
		$taxonomy = taxonomy_exists( (string) $taxonomy ) ? (string) $taxonomy : 'genre';
		$number   = max( 3, min( 30, (int) $number ) );

		$cache_key = 'manacore_mega_terms_' . sanitize_key( $taxonomy . '_' . $number );
		$slugs     = get_transient( $cache_key );

		if ( ! is_array( $slugs ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => $number,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);

			$slugs = is_wp_error( $terms ) ? array() : wp_list_pluck( (array) $terms, 'slug' );
			set_transient( $cache_key, $slugs, HOUR_IN_SECONDS );
		}

		if ( empty( $slugs ) ) {
			$slugs = self::fallback_slugs();
		}

		$terms = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$terms[] = $term;
			}
		}

		/**
		 * فیلتر ترم‌های مگامنو.
		 *
		 * @param array<int,\WP_Term> $terms    ترم‌ها.
		 * @param string              $taxonomy تاکسونومی.
		 */
		return (array) apply_filters( 'manacore_mega_terms', $terms, $taxonomy );
	}

	/**
	 * نامک‌های پیش‌فرض مرجع برای سایت‌های تازه‌تاسیس.
	 *
	 * @return array<int,string>
	 */
	protected static function fallback_slugs() {
		return array(
			'علمی‌تخیلی',
			'درام',
			'اکشن',
			'ماجراجویی',
			'کمدی',
			'جنایی',
			'فانتزی',
			'معمایی',
			'انیمیشن',
			'عاشقانه',
			'تاریخی',
			'هیجان‌انگیز',
		);
	}

	/**
	 * پیوندهای دسترسی سریع ستون میانی.
	 *
	 * برچسب‌ها از تنظیمات می‌آیند (`mega_rating_label` و…)، پس مدیر
	 * می‌تواند متن‌ها را بدون کد تغییر دهد.
	 *
	 * @return array<int,array{url:string,label:string,icon:string,key:string}>
	 */
	public static function quick_links() {
		$settings = self::settings();

		$person_base = get_post_type_archive_link( 'person' );
		$browse_base = get_post_type_archive_link( 'movie' );
		$browse_base = $browse_base ? $browse_base : home_url( '/' );

		$links = array(
			array(
				'key'   => 'rating',
				'url'   => add_query_arg( 'mc_sort', 'rating', $browse_base ),
				'label' => (string) $settings['rating_label'],
				'icon'  => '<path d="m12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z"/>',
			),
			array(
				'key'   => 'newest',
				'url'   => add_query_arg( 'mc_sort', 'newest', $browse_base ),
				'label' => (string) $settings['newest_label'],
				'icon'  => '<path d="m4 4 16 0M3 8h18M5 4l2 4M10 4l2 4M15 4l2 4M4 8v12h16V8"/>',
			),
		);

		/*
		 * «فیلم و سریال کره‌ای» تنها وقتی می‌آید که کشور متناظری در
		 * تاکسونومی وجود داشته باشد؛ وگرنه ردیف بی‌مقصد ساخته می‌شد.
		 */
		if ( ! empty( $settings['show_korean'] ) ) {
			$korean = get_terms(
				array(
					'taxonomy'   => 'country',
					'hide_empty' => false,
					'number'     => 1,
					'name__like' => 'کره',
				)
			);

			if ( ! is_wp_error( $korean ) && $korean ) {
				$links[] = array(
					'key'   => 'korean',
					'url'   => (string) get_term_link( $korean[0] ),
					'label' => (string) $settings['korean_label'],
					'icon'  => '<path d="M5 3v18M5 8h14M19 3v18"/>',
				);
			}
		}

		if ( ! empty( $settings['show_cast'] ) && $person_base ) {
			$links[] = array(
				'key'   => 'cast',
				'url'   => (string) $person_base,
				'label' => (string) $settings['cast_label'],
				'icon'  => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
			);
		}

		/**
		 * فیلتر پیوندهای دسترسی سریع مگامنو.
		 *
		 * @param array<int,array{url:string,label:string,icon:string,key:string}> $links پیوندها.
		 */
		return (array) apply_filters( 'manacore_mega_quick_links', $links );
	}

	/**
	 * اثر ویژه‌ی ستون سوم.
	 *
	 * اولویت: شناسه‌ی صریح → تنظیمات مدیر → ترنزینت خودکار (تازه‌ترین اثر
	 * دارای پوستر). پیش‌تر «بهترین امتیاز» در قالب سخت‌کد بود و مدیر هیچ
	 * راهی برای انتخاب کارت ویژه نداشت.
	 *
	 * @param int $featured_id شناسه‌ی صریح اختیاری.
	 * @return int
	 */
	public static function featured_id( $featured_id = 0 ) {
		$settings = self::settings();

		if ( ! $featured_id ) {
			$featured_id = (int) $settings['featured_id'];
		}

		if ( $featured_id && 'publish' === get_post_status( $featured_id ) && array_key_exists( (string) get_post_type( $featured_id ), manacore_post_types() ) ) {
			return (int) $featured_id;
		}

		$cached = (int) get_transient( 'manacore_mega_featured' );
		if ( $cached && 'publish' === get_post_status( $cached ) ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => array_values( manacore_title_post_types() ),
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		/*
		 * نخستین اثر تازه که پوستر واقعی دارد (تصویر شاخص یا آدرس پوستر
		 * دستی) انتخاب ویژه می‌شود؛ `manacore_poster_url` همیشه جایی
		 * می‌رود (placeholder)، پس واقعی‌بودن پوستر را خودمان می‌سنجیم.
		 */
		$id = 0;
		foreach ( $query->posts as $post ) {
			if ( has_post_thumbnail( $post->ID ) || get_post_meta( $post->ID, 'manacore_poster_url', true ) ) {
				$id = (int) $post->ID;
				break;
			}
		}

		if ( ! $id && $query->posts ) {
			$id = (int) $query->posts[0]->ID;
		}

		set_transient( 'manacore_mega_featured', $id, HOUR_IN_SECONDS );

		/**
		 * فیلتر اثر ویژه‌ی مگامنو.
		 *
		 * @param int   $id  شناسه‌ی اثر.
		 * @param array $settings تنظیمات.
		 */
		return (int) apply_filters( 'manacore_mega_featured_id', $id, $settings );
	}

	/**
	 * اثر ویژه برای ویرایشگر: عنوان و پیوند کارت.
	 *
	 * @return array{id:int,title:string,url:string,image:string}
	 */
	public static function featured_card() {
		$id    = self::featured_id();
		$image = $id ? manacore_backdrop_url( $id ) : '';

		return array(
			'id'    => $id,
			'title' => $id ? get_the_title( $id ) : '',
			'url'   => $id ? (string) get_permalink( $id ) : '',
			'image' => $id ? (string) $image : '',
		);
	}

	/**
	 * پاک‌کردن کش مگامنو (ترنزینت‌ها).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		$like = $wpdb->esc_like( '_transient_manacore_mega_' ) . '%';

		$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$wpdb->esc_like( '_transient_timeout_manacore_mega_' ) . '%'
			)
		);

		foreach ( (array) $keys as $key ) {
			$name = str_replace( array( '_transient_timeout_', '_transient_' ), '', (string) $key );
			delete_transient( $name );
		}
	}
}

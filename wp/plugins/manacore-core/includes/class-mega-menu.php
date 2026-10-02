<?php
/**
 * مگامنوی ژانرها — بازآفرینی مگامنوی مرجع سینورا.
 *
 * منوی «دسته‌بندی‌ها» در سربرگ، پنلی تمام‌عرض باز می‌کند با سه ستون:
 *   ۱) شبکه‌ی ژانرها (سه‌ستونه) بر پایه‌ی ترم‌های واقعی تاکسونومی genre؛
 *   ۲) فهرست دسترسی سریع (آثار برتر، تازه‌ها، بازیگران)؛
 *   ۳) اثر ویژه‌ی انتخابی (پوستر با روکش گرادیانی).
 *
 * ترم‌ها با get_terms خوانده و در ترنزینت cache می‌شوند؛ اگر هنوز ژانری
 * ثبت نشده باشد، به فهرست پیش‌فرض مرجع برمی‌گردیم تا پنل هرگز خالی نماند.
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
	}

	/**
	 * شورت‌کد [manacore_mega_menu].
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'taxonomy'  => 'genre',
				'number'    => 12,
				'columns'   => 3,
				'featured'  => 0,
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
	 * رندر پنل مگامنو.
	 *
	 * @param array $args آرگومان‌ها.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'taxonomy' => 'genre',
				'number'   => 12,
				'columns'  => 3,
				'featured' => 0,
			)
		);

		$taxonomy = taxonomy_exists( (string) $args['taxonomy'] ) ? (string) $args['taxonomy'] : 'genre';
		$number   = max( 3, min( 24, (int) $args['number'] ) );
		$columns  = max( 2, min( 4, (int) $args['columns'] ) );

		$terms = self::menu_terms( $taxonomy, $number );
		if ( empty( $terms ) ) {
			return '';
		}

		$featured_id = self::featured_id( (int) $args['featured'] );

		ob_start();
		?>
		<div class="manacore-mega" data-mega-menu hidden>
			<div class="manacore-mega__main">
				<p class="koohe-eyebrow"><?php esc_html_e( 'یک دنیا انتخاب', 'manacore' ); ?></p>
				<h3 class="manacore-mega__heading"><?php esc_html_e( 'حال‌وهوای امشبت چیه؟', 'manacore' ); ?></h3>
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
				<p class="koohe-eyebrow"><?php esc_html_e( 'به انتخاب کوهه', 'manacore' ); ?></p>
				<?php foreach ( self::quick_links() as $link ) : ?>
					<a href="<?php echo esc_url( $link['url'] ); ?>">
						<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $link['icon']; // phpcs:ignore WordPress.Security.EscapeOutput — SVG ثابت داخلی ?></svg>
						<?php echo esc_html( $link['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $featured_id ) : ?>
				<?php
				$backdrop = manacore_poster_url( $featured_id );
				?>
				<a class="manacore-mega__feature" href="<?php echo esc_url( get_permalink( $featured_id ) ); ?>">
					<img src="<?php echo esc_url( $backdrop ); ?>" alt="<?php echo esc_attr( get_the_title( $featured_id ) ); ?>" loading="lazy" decoding="async" />
					<span>
						<small><?php esc_html_e( 'انتخاب ویژه این هفته', 'manacore' ); ?></small>
						<strong><?php echo esc_html( get_the_title( $featured_id ) ); ?></strong>
						<span>
							<?php esc_html_e( 'کشف داستان', 'manacore' ); ?>
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
	protected static function menu_terms( $taxonomy, $number ) {
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

		/*
		 * اگر هیچ‌کدام از نامک‌های بازگشتی ترم نداشتند (تاکسونومی غریبه)،
		 * پنل خالی می‌ماند و رندر همان رشته‌ی تهی برمی‌گردد.
		 */
		return $terms;
	}

	/**
	 * نامک‌های پیش‌فرض مرجع سینورا برای سایت‌های تازه‌تاسیس.
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
	 * @return array<int,array{url:string,label:string,icon:string}>
	 */
	protected static function quick_links() {
		$person_base = get_post_type_archive_link( 'person' );
		$browse_base = get_post_type_archive_link( 'movie' );

		/*
		 * مرتب‌سازی mc_sort تنها روی آرشیو اعمال می‌شود؛ پس لینک‌های سریع
		 * به آرشیو فیلم می‌روند (معادل browse.html مرجع سینورا).
		 */
		$browse_base = $browse_base ? $browse_base : home_url( '/' );

		$links = array(
			array(
				'url'   => add_query_arg( 'mc_sort', 'rating', $browse_base ),
				'label' => __( 'بالاترین امتیازها', 'manacore' ),
				'icon'  => '<path d="m12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z"/>',
			),
			array(
				'url'   => add_query_arg( 'mc_sort', 'newest', $browse_base ),
				'label' => __( 'تازه‌های کوهه', 'manacore' ),
				'icon'  => '<path d="m4 4 16 0M3 8h18M5 4l2 4M10 4l2 4M15 4l2 4M4 8v12h16V8"/>',
			),
		);

		if ( $person_base ) {
			$links[] = array(
				'url'   => $person_base,
				'label' => __( 'بازیگران و کارگردان‌ها', 'manacore' ),
				'icon'  => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
			);
		}

		/**
		 * فیلتر پیوندهای دسترسی سریع مگامنو.
		 *
		 * @param array<int,array{url:string,label:string,icon:string}> $links پیوندها.
		 */
		return (array) apply_filters( 'manacore_mega_quick_links', $links );
	}

	/**
	 * اثر ویژه‌ی ستون سوم: شناسه‌ی صریح، وگرنه تازه‌ترین اثر دارای پوستر.
	 *
	 * @param int $featured_id شناسه‌ی صریح اختیاری.
	 * @return int
	 */
	protected static function featured_id( $featured_id ) {
		$type = $featured_id ? (string) get_post_type( $featured_id ) : '';
		if ( ! $type || ! array_key_exists( $type, manacore_post_types() ) ) {
			$featured_id = 0;
		}

		if ( $featured_id && 'publish' === get_post_status( $featured_id ) ) {
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
		 * نخستین اثر تازه که پوستر واقعی دارد (تصویر شاخص یا آدرس پوسترس
		 * دستی) انتخاب ویژه می‌شود؛ manacore_poster_url همیشه جایی می‌رود
		 * (placeholder)، پس واقعی‌بودن پوستر را خودمان می‌سنجیم.
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

		return $id;
	}
}

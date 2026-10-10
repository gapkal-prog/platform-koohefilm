<?php
/**
 * توابع رندر قطعات مشترک (کارت، جدول لینک، متادیتا).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Templates
 */
class Templates {

	/**
	 * رندر کارت یک اثر.
	 *
	 * @param int   $post_id شناسه‌ی پست.
	 * @param array $args    تنظیمات.
	 * @return string
	 */
	public static function card( $post_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_rating'    => true,
				'show_year'      => true,
				'show_quality'   => true,
				'show_watchlist' => true,
				'show_type'      => true,
				'show_genre'     => true,
				'show_premium'   => true,
				'show_excerpt'   => false,
				'show_overlay'   => true,
				'show_episode'   => false,
				'show_views'     => false,
				'show_runtime'   => false,
				'excerpt_words'  => 14,
				'excerpt_length' => 0,
				'title_tag'      => 'h3',
				'title_lines'    => 0,
				'image_ratio'    => '',
				'aspect_ratio'   => '',
				'link_target'    => '',
				'extra_class'    => '',
				'style'          => 'poster',
			)
		);

		// سازگاری: excerpt_length مترادف excerpt_words است (نام مورد استفاده در بلوک‌ها).
		if ( ! empty( $args['excerpt_length'] ) ) {
			$args['excerpt_words'] = (int) $args['excerpt_length'];
		}

		$post_type   = get_post_type( $post_id );
		$type_labels = manacore_post_types();
		$rating      = self::best_rating( $post_id );
		$year        = self::year( $post_id );
		$is_premium  = get_post_meta( $post_id, 'manacore_is_premium', true );
		$title_tag   = in_array( $args['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $args['title_tag'] : 'h3';
		$new_tab     = '_blank' === $args['link_target'];

		$classes = array( 'manacore-card' );

		/*
		 * سبک کارت با فهرست مشترک Block_Data اعتبارسنجی می‌شود تا مقدار
		 * کهنه یا دست‌ساز به کلاسی بی‌اثر تبدیل نشود. «poster» پیش‌فرض
		 * است و کلاس جداگانه نمی‌گیرد.
		 */
		$style = Block_Support::pick( $args['style'], Block_Data::card_styles(), 'poster' );

		/*
		 * سازگاری با محتوای ذخیره‌شده‌ی قدیمی: «عریض» و «افقی (۱۶:۹)» یک
		 * نتیجه داشتند و از فهرست گزینه‌ها یکی شدند، اما مقدار `wide` در
		 * نوشته‌های قدیمی باید همان ظاهر پیشین را نگه دارد.
		 */
		if ( 'wide' === $style ) {
			$style = 'landscape';
		}

		if ( 'poster' !== $style ) {
			$classes[] = 'is-style-' . $style;
		}

		/*
		 * کارت افقی ۱۶:۹ در طرح مرجع سه نشانه‌ی ویژه دارد: برچسب فصل/قسمت
		 * روی تصویر، دکمه‌ی پخش با برچسب «کشف داستان» و نوار پایین تصویر.
		 */
		$is_landscape = ( 'landscape' === $style );
		if ( $args['image_ratio'] ) {
			$classes[] = 'is-ratio-' . sanitize_html_class( $args['image_ratio'] );
		}
		if ( $args['show_excerpt'] ) {
			$classes[] = 'has-excerpt';
		}
		if ( $args['extra_class'] ) {
			foreach ( preg_split( '/\s+/', (string) $args['extra_class'] ) as $custom ) {
				$custom = sanitize_html_class( $custom );
				if ( $custom ) {
					$classes[] = $custom;
				}
			}
		}

		$inline = array();
		if ( ! empty( $args['title_lines'] ) ) {
			$inline[] = '--mc-title-lines:' . max( 1, min( 4, (int) $args['title_lines'] ) );
		}
		if ( ! empty( $args['aspect_ratio'] ) ) {
			$ratio = preg_replace( '/[^0-9\/.:]/', '', (string) $args['aspect_ratio'] );
			if ( $ratio ) {
				$inline[] = '--mc-poster-ratio:' . str_replace( ':', '/', $ratio );
			}
		}
		$inline_style = $inline ? ' style="' . esc_attr( implode( ';', $inline ) ) . '"' : '';

		$quality = '';
		if ( $args['show_quality'] ) {
			$quality_terms = get_the_terms( $post_id, 'quality' );
			if ( $quality_terms && ! is_wp_error( $quality_terms ) ) {
				$quality = $quality_terms[0]->name;
			}
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>"<?php echo $inline_style; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<a class="manacore-card-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"
				<?php echo $new_tab ? ' target="_blank" rel="noopener"' : ''; ?>>
				<div class="manacore-card-poster">
					<?php if ( $is_landscape && $args['show_episode'] ) : ?>
						<?php $badge = wp_strip_all_tags( self::episode_badge( $post_id, $post_type ) ); ?>
						<?php if ( $badge ) : ?>
							<span class="manacore-card-badge"><?php echo esc_html( $badge ); ?></span>
						<?php endif; ?>
					<?php endif; ?>

					<img
						src="<?php echo esc_url( manacore_poster_url( $post_id ) ); ?>"
						alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"
						loading="lazy" decoding="async" />

					<?php if ( $args['show_rating'] && $rating ) : ?>
						<span class="manacore-card-rating">
							<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
								<path fill="currentColor" d="M12 2l2.9 6.3 6.8.8-5 4.7 1.3 6.8L12 17.4 6 20.6l1.3-6.8-5-4.7 6.8-.8z"/>
							</svg>
							<?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $args['show_premium'] && $is_premium ) : ?>
						<span class="manacore-card-premium" title="<?php esc_attr_e( 'محتوای اشتراکی', 'manacore' ); ?>">
							<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true">
								<path fill="currentColor" d="M12 1l3 6 6.5 1-4.7 4.6 1.1 6.4L12 16l-5.9 3 1.1-6.4L2.5 8 9 7z"/>
							</svg>
						</span>
					<?php endif; ?>

					<?php if ( $args['show_type'] && isset( $type_labels[ $post_type ] ) ) : ?>
						<span class="manacore-card-type"><?php echo esc_html( $type_labels[ $post_type ] ); ?></span>
					<?php endif; ?>

					<?php if ( $quality ) : ?>
						<span class="manacore-card-quality"><?php echo esc_html( $quality ); ?></span>
					<?php endif; ?>

					<?php if ( $args['show_overlay'] ) : ?>
						<div class="manacore-card-overlay">
							<span class="manacore-card-play" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
							</span>
							<span class="manacore-card-play-label"><?php esc_html_e( 'کشف داستان', 'manacore' ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<div class="manacore-card-body">
					<<?php echo esc_html( $title_tag ); ?> class="manacore-card-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></<?php echo esc_html( $title_tag ); ?>>
					<?php if ( $args['show_year'] || $args['show_genre'] || $args['show_episode'] || $args['show_views'] || $args['show_runtime'] ) : ?>
						<div class="manacore-card-meta">
							<?php if ( $args['show_year'] && $year ) : ?>
								<span><?php echo esc_html( $year ); ?></span>
							<?php endif; ?>
							<?php
							if ( $args['show_genre'] ) :
								$genres = get_the_terms( $post_id, 'genre' );
								if ( $genres && ! is_wp_error( $genres ) ) :
									?>
									<span><?php echo esc_html( $genres[0]->name ); ?></span>
									<?php
								endif;
							endif;
							?>
							<?php
							if ( $args['show_runtime'] ) :
								$runtime = (int) get_post_meta( $post_id, 'manacore_runtime', true );
								if ( $runtime ) :
									?>
									<span>
										<?php
										printf(
											/* translators: %s: تعداد دقیقه */
											esc_html__( '%s دقیقه', 'manacore' ),
											esc_html( number_format_i18n( $runtime ) )
										);
										?>
									</span>
									<?php
								endif;
							endif;
							?>
							<?php
							if ( $args['show_views'] ) :
								$views = (int) get_post_meta( $post_id, 'manacore_views', true );
								if ( $views ) :
									?>
									<span>
										<?php
										printf(
											/* translators: %s: تعداد بازدید */
											esc_html__( '%s بازدید', 'manacore' ),
											esc_html( number_format_i18n( $views ) )
										);
										?>
									</span>
									<?php
								endif;
							endif;
							?>
							<?php
							if ( $args['show_episode'] ) :
								echo wp_kses_post( self::episode_badge( $post_id, $post_type ) );
							endif;
							?>
						</div>
					<?php endif; ?>
					<?php if ( $args['show_excerpt'] ) : ?>
						<?php $excerpt = wp_strip_all_tags( get_the_excerpt( $post_id ) ); ?>
						<?php if ( $excerpt ) : ?>
							<p class="manacore-card-excerpt">
								<?php echo esc_html( wp_trim_words( $excerpt, max( 4, (int) $args['excerpt_words'] ), '…' ) ); ?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</a>

			<?php if ( $args['show_watchlist'] && manacore_get_option( 'enable_watchlist', 1 ) ) : ?>
				<button type="button" class="manacore-card-fav"
					data-manacore-watchlist="<?php echo esc_attr( $post_id ); ?>"
					aria-label="<?php esc_attr_e( 'افزودن به لیست تماشا', 'manacore' ); ?>"
					aria-pressed="<?php echo Watchlist::instance()->has( $post_id ) ? 'true' : 'false'; ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
						<path fill="currentColor" d="M17 3H7a2 2 0 0 0-2 2v16l7-4 7 4V5a2 2 0 0 0-2-2z"/>
					</svg>
				</button>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * نشان فصل/قسمت برای کارت.
	 *
	 * برای سریال و انیمه: «۲ فصل • ۲۴ قسمت»
	 * برای قسمت: «S02E05»
	 *
	 * @param int    $post_id   شناسه‌ی پست.
	 * @param string $post_type نوع محتوا.
	 * @return string
	 */
	public static function episode_badge( $post_id, $post_type = '' ) {
		$post_type = $post_type ? $post_type : get_post_type( $post_id );

		if ( 'episode' === $post_type ) {
			$season  = (int) get_post_meta( $post_id, 'manacore_season_number', true );
			$episode = (int) get_post_meta( $post_id, 'manacore_episode_number', true );
			if ( ! $season && ! $episode ) {
				return '';
			}
			return '<span class="manacore-card-episode">'
				. esc_html( sprintf( 'S%02dE%02d', $season, $episode ) )
				. '</span>';
		}

		if ( ! in_array( $post_type, manacore_serial_post_types(), true ) ) {
			return '';
		}

		$parts    = array();
		$seasons  = (int) get_post_meta( $post_id, 'manacore_total_seasons', true );
		$episodes = (int) get_post_meta( $post_id, 'manacore_total_episodes', true );

		if ( $seasons ) {
			$parts[] = sprintf(
				/* translators: %s: تعداد فصل */
				__( '%s فصل', 'manacore' ),
				number_format_i18n( $seasons )
			);
		}
		if ( $episodes ) {
			$parts[] = sprintf(
				/* translators: %s: تعداد قسمت */
				__( '%s قسمت', 'manacore' ),
				number_format_i18n( $episodes )
			);
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return '<span class="manacore-card-episode">' . esc_html( implode( ' • ', $parts ) ) . '</span>';
	}

	/**
	 * بهترین امتیاز موجود برای اثر.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return float
	 */
	public static function best_rating( $post_id ) {
		$keys = array( 'manacore_editor_score', 'manacore_imdb_rating', 'manacore_tmdb_rating', 'manacore_mal_rating', 'manacore_user_rating' );
		foreach ( $keys as $key ) {
			$value = (float) get_post_meta( $post_id, $key, true );
			if ( $value > 0 ) {
				return round( $value, 1 );
			}
		}
		return 0;
	}

	/**
	 * سال اثر.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return string
	 */
	public static function year( $post_id ) {
		$year = get_post_meta( $post_id, 'manacore_year', true );
		if ( $year ) {
			return (string) (int) $year;
		}
		$date = get_post_meta( $post_id, 'manacore_release_date', true );
		if ( $date ) {
			return substr( (string) $date, 0, 4 );
		}
		$terms = get_the_terms( $post_id, 'release_year' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}
		return '';
	}

	/**
	 * رندر بخش لینک‌های دانلود.
	 *
	 * حالت (`mode`) تعیین می‌کند سرصفحه‌ی پیش‌فرض چه باشد و جدول برای کدام
	 * گونه‌ی محتوا ساخته شود:
	 *   • `auto`   → بر پایه‌ی نوع پست (سریال/انیمه → `series`، بقیه → `movie`)
	 *   • `movie`  → جدول کیفیت‌ها (مرجع `detail.html` سینورا)
	 *   • `series` → فصل‌به‌فصل: بسته‌های کامل فصل + کارت هر قسمت (آکاردئون)
	 *   • `episode`→ جدول کیفیت‌های یک قسمت
	 *
	 * منبع داده (`source`) برای سریال‌ها تعیین می‌کند ردیف‌ها از کجا
	 * بیایند:
	 *   • `auto`     → فیلم/قسمت: لینک‌های خودِ اثر؛ سریال: هر دو منبع
	 *   • `post`     → فقط لینک‌های خودِ اثر (بسته‌های فصل)
	 *   • `episodes` → فقط لینک‌های قسمت‌های فرزند
	 *   • `both`     → هر دو
	 *
	 * @param int   $post_id شناسه‌ی پست.
	 * @param array $args    تنظیمات: mode، source، box_style، heading، subtitle،
	 *                       heading_tag، show_heading/icon/count/notice/tabs،
	 *                       types، qualities، season، pack_label، size_label،
	 *                       episode_label.
	 * @return string
	 */
	public static function links( $post_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'box_style'     => 'cards',
				'heading'       => '',
				'subtitle'      => '',
				'heading_tag'   => 'h2',
				'show_heading'  => true,
				'show_icon'     => true,
				'show_count'    => true,
				'show_notice'   => true,
				'show_tabs'     => true,
				'mode'          => 'auto',
				'pack_label'    => '',
				'size_label'    => '',
				'types'         => array(),
				'qualities'     => array(),
				'season'        => 0,
				'source'        => 'auto',
				'episode_label' => '',
			)
		);

		$args['mode'] = self::resolve_mode( $args['mode'], $post_id );
		$source       = self::resolve_source( $args['source'], $post_id, $args['mode'] );
		$is_series    = 'series' === $args['mode'];

		if ( get_post_meta( $post_id, 'manacore_disable_links', true ) ) {
			return '';
		}

		$groups        = Links::get( $post_id );
		$season_filter = max( 0, (int) $args['season'] );

		/*
		 * لینک‌های قسمت‌های فرزند (پست‌های CPT قسمت). در سریال، هر قسمتی که
		 * لینک خودش را دارد، «مالکِ» آن شماره است؛ پس ردیف شماره‌دارِ همان
		 * شماره روی پست سریال تکرار نمی‌شود.
		 */
		$child_groups = Links::episode_groups( $post_id, array( 'season' => $season_filter ) );
		$episode_rows = in_array( $source, array( 'episodes', 'both' ), true ) ? $child_groups : array();

		$own_episodes = array();

		if ( $is_series && in_array( $source, array( 'post', 'both' ), true ) ) {
			$taken = array();

			foreach ( $child_groups as $child_season => $season_groups ) {
				foreach ( $season_groups as $child ) {
					$taken[ (int) $child_season ][ (int) $child['episode'] ] = true;
				}
			}

			foreach ( Links::numbered_groups( $post_id ) as $own_season => $season_groups ) {
				if ( $season_filter && $season_filter !== (int) $own_season ) {
					continue;
				}

				foreach ( $season_groups as $own ) {
					if ( isset( $taken[ (int) $own_season ][ (int) $own['episode'] ] ) ) {
						continue;
					}

					$own_episodes[ (int) $own_season ][] = $own;
				}
			}
		}

		if ( empty( $groups ) && empty( $episode_rows ) && empty( $own_episodes ) ) {
			return self::editor_hint( $post_id, $args['mode'] );
		}

		$login_only = (int) manacore_get_option( 'links_login_only', 0 );
		if ( $login_only && ! is_user_logged_in() ) {
			return self::locked_notice(
				__( 'برای مشاهده‌ی لینک‌های دانلود وارد حساب کاربری شوید.', 'manacore' ),
				wp_login_url( get_permalink( $post_id ) ),
				__( 'ورود به حساب', 'manacore' )
			);
		}

		$notice     = $args['show_notice'] ? (string) get_post_meta( $post_id, 'manacore_custom_notice', true ) : '';

		/*
		 * مرجع همیشه یک یادداشت زیر سرستون دارد (`demo-notice`). اگر مدیر
		 * یادداشت اثر را ننوشته باشد، متن پیش‌فرض کتابخانه می‌آید تا
		 * ساختار دقیقاً همان مرجع بماند.
		 */
		if ( $args['show_notice'] && '' === trim( (string) $notice ) ) {
			/*
			 * متن پیش‌فرض از پنل مدیریت خوانده می‌شود (تب «پخش و دانلود»)
			 * و در نبودش متن کتابخانه می‌آید؛ پس مدیر می‌تواند لحن/متن
			 * یادداشت همه‌ی باکس‌ها را یک‌جا عوض کند.
			 */
			$default_notice = trim( (string) manacore_get_option( 'download_notice_text', '' ) );

			if ( '' === $default_notice ) {
				$default_notice = __( 'برای رعایت حقوق نشر، پخش و دانلود این نسخه از نمونه ۱۰ ثانیه‌ای ویدئوی آزاد استفاده می‌کند.', 'manacore' );
			}

			$notice = (string) apply_filters( 'manacore_download_notice', $default_notice, $post_id );
		}

		if ( ! $args['show_notice'] ) {
			$notice = '';
		}

		/*
		 * بسته‌های فصل: در سریال فقط آیتم‌های بی‌شماره؛ در بقیه‌ی حالت‌ها
		 * همان فهرست گروه‌ها به تفکیک فصل (رفتار پیشین دست‌نخورده).
		 */
		$by_season = $is_series ? Links::season_packs( $post_id ) : Links::by_season( $post_id );

		// فیلتر فصل: یا همان فصل، یا هیچ‌چیز (نه همه‌ی فصل‌ها).
		if ( $season_filter ) {
			$by_season = array_intersect_key( $by_season, array( $season_filter => true ) );
		}

		// ادغام بسته‌ها با ردیف‌های قسمت‌ها؛ هر فصل تب خودش را دارد.
		foreach ( array( $episode_rows, $own_episodes ) as $extra ) {
			foreach ( $extra as $extra_season => $extra_groups ) {
				$by_season[ $extra_season ] = array_merge(
					isset( $by_season[ $extra_season ] ) ? (array) $by_season[ $extra_season ] : array(),
					(array) $extra_groups
				);
			}
		}

		ksort( $by_season );

		// فیلتر بر اساس نوع لینک و کیفیت.
		$type_filter    = array_filter( array_map( 'sanitize_key', (array) $args['types'] ) );
		$quality_filter = array_filter( array_map( 'sanitize_key', (array) $args['qualities'] ) );

		if ( $type_filter || $quality_filter ) {
			$by_season = self::filter_link_seasons( $by_season, $type_filter, $quality_filter );
		}

		if ( empty( $by_season ) ) {
			return '';
		}

		$multi       = $args['show_tabs'] && ( count( $by_season ) > 1 || ! isset( $by_season[0] ) );
		$heading     = $args['heading'] ? $args['heading'] : self::default_heading( $args['mode'] );
		$heading_tag = in_array( $args['heading_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $args['heading_tag'] : 'h2';

		// برچسب ستون‌ها و نشان‌های سطر، بر پایه‌ی حالت.
		$size_label = '' !== trim( (string) $args['size_label'] )
			? (string) $args['size_label']
			: __( 'حجم نمونه', 'manacore' );
		$pack_label = '' !== trim( (string) $args['pack_label'] )
			? (string) $args['pack_label']
			: __( 'بسته‌ی کامل فصل', 'manacore' );

		// سبک ظاهری با فهرست مشترک اعتبارسنجی می‌شود.
		$box_style = Block_Support::pick( $args['box_style'], Block_Data::download_styles(), 'cards' );

		$labels = array(
			'mode'    => $args['mode'],
			'episode' => (string) $args['episode_label'],
			'pack'    => $pack_label,
			'size'    => $size_label,
		);

		/*
		 * هر فصل را یک بار به «بسته‌ها» و «قسمت‌ها» بخش می‌کنیم؛ قسمت‌ها با
		 * کلید مالک:شماره گروه می‌شوند تا آیتم‌های یک قسمتِ چندکیفیتی یک کارت
		 * بسازند. ترتیب کارت‌ها بر پایه‌ی شماره‌ی قسمت است.
		 */
		$layout = array();

		foreach ( $by_season as $season => $season_groups ) {
			$packs = array();
			$cards = array();

			foreach ( (array) $season_groups as $group ) {
				if ( $is_series && ! empty( $group['owner'] ) ) {
					$cards[ (int) $group['owner'] . ':' . (int) $group['episode'] ][] = $group;
				} else {
					$packs[] = $group;
				}
			}

			$cards = array_values( $cards );
			usort(
				$cards,
				static function ( $a, $b ) {
					return (int) $a[0]['episode'] - (int) $b[0]['episode'];
				}
			);

			$layout[ $season ] = array(
				'packs' => $packs,
				'cards' => $cards,
			);
		}

		/*
		 * مرجع: `.download-section` → همین قاب؛ `.section-heading` → سرستون با
		 * شمارش و گزینش فصل؛ `.season-tabs` → نوار فصل‌ها؛ `.demo-notice` →
		 * یادداشت اثر؛ `.episode-card` → هر قسمت (آکاردئون با جدول کیفیت)؛
		 * `.download-table` → جدول کیفیت/فرمت/حجم/کنش‌ها. قسمت‌ها و فصل‌ها
		 * با همان قرارداد بلوک `manacore/episodes-list` کار می‌کنند، پس
		 * `front.js` (`initEpisodeCards`) بی‌تغییر کار می‌کند.
		 */
		ob_start();
		?>
		<section class="download-section<?php echo 'cards' === $box_style ? '' : ' is-' . esc_attr( $box_style ); ?><?php echo $is_series ? ' is-series' : ''; ?>"
			id="download" data-manacore-downloads="<?php echo esc_attr( $post_id ); ?>"
			<?php if ( $is_series ) : ?>data-manacore-episodes="<?php echo esc_attr( $post_id ); ?>"<?php endif; ?>
			data-mode="<?php echo esc_attr( $args['mode'] ); ?>">
			<div class="section-heading">
				<div class="heading-title">
					<?php if ( $args['show_icon'] ) : ?>
						<?php /* نشانه‌ی بخش، مثل مرجع یک نویسه‌ی متنی است (`detail.html`). */ ?>
						<span class="section-icon" aria-hidden="true"><?php echo $is_series ? '▤' : '⇩'; ?></span>
					<?php endif; ?>
					<<?php echo esc_html( $heading_tag ); ?>><?php echo esc_html( $heading ); ?></<?php echo esc_html( $heading_tag ); ?>>
				</div>
				<?php if ( $args['show_count'] ) : ?>
					<span class="text-link">
						<?php
						printf(
							/* translators: %s: تعداد لینک */
							esc_html__( '%s لینک', 'manacore' ),
							esc_html( number_format_i18n( Links::total( $by_season ) ) )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $multi ) : ?>
					<label class="manacore-season-select">
						<span class="screen-reader-text"><?php esc_html_e( 'انتخاب فصل', 'manacore' ); ?></span>
						<select data-season-select aria-label="<?php esc_attr_e( 'انتخاب فصل', 'manacore' ); ?>">
							<?php $first = true; ?>
							<?php foreach ( array_keys( $by_season ) as $season ) : ?>
								<option value="<?php echo esc_attr( $season ); ?>"<?php echo $first ? ' selected' : ''; ?>><?php echo esc_html( self::season_label( $season ) ); ?></option>
								<?php $first = false; ?>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
			</div>

			<?php if ( '' !== trim( (string) $args['subtitle'] ) ) : ?>
				<p class="muted"><?php echo esc_html( $args['subtitle'] ); ?></p>
			<?php endif; ?>

			<?php if ( $notice ) : ?>
				<div class="demo-notice">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/>
					</svg>
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $multi ) : ?>
				<div class="season-tabs manacore-season-tabs" role="tablist" aria-label="<?php esc_attr_e( 'فصل‌ها', 'manacore' ); ?>">
					<?php $first = true; ?>
					<?php foreach ( $layout as $season => $parts ) : ?>
						<button type="button" role="tab" class="<?php echo $first ? 'active' : ''; ?>"
							data-season="<?php echo esc_attr( $season ); ?>"
							aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( self::season_panel_id( $post_id, $season ) ); ?>">
							<?php echo esc_html( self::season_label( $season ) ); ?>
							<?php if ( $args['show_count'] && ! empty( $parts['cards'] ) ) : ?>
								<small><?php echo esc_html( sprintf( /* translators: %s: تعداد قسمت */ __( '%s قسمت', 'manacore' ), manacore_fa_digits( number_format_i18n( count( $parts['cards'] ) ) ) ) ); ?></small>
							<?php endif; ?>
						</button>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php $first = true; ?>
			<?php foreach ( $layout as $season => $parts ) : ?>
				<div class="manacore-season-panel<?php echo ( ! $multi || $first ) ? ' is-active' : ''; ?>"
					id="<?php echo esc_attr( self::season_panel_id( $post_id, $season ) ); ?>"
					data-season-panel="<?php echo esc_attr( $season ); ?>"<?php echo ( $multi && ! $first ) ? ' hidden' : ''; ?>>
					<?php if ( $parts['packs'] ) : ?>
						<?php
						$pack_rows = self::pack_rows( $parts['packs'], 'series' === $labels['mode'] ? (string) $labels['pack'] : '' );
						$pack_cols = self::download_columns( $pack_rows );
						?>
						<div class="download-table download-packs" data-season-packs="<?php echo esc_attr( $season ); ?>">
							<?php echo self::download_head( $pack_cols, $size_label ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php
							foreach ( $pack_rows as $row ) {
								// ردیف‌های بی‌شماره، ردیف «بسته» هستند؛ مالک آن‌ها خودِ اثر است.
								$row_play = ! empty( $row['owner'] ) ? self::episode_play_url( $post_id, (int) $season, $row ) : '';

								echo self::link_row( $row, $pack_cols, $post_id, $row_play ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( $parts['cards'] ) : ?>
						<div class="episode-list">
							<?php foreach ( $parts['cards'] as $index => $card_groups ) : ?>
								<?php echo self::episode_card( $card_groups, (int) $season, $post_id, 0 === $index, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php $first = false; ?>
			<?php endforeach; ?>

			<?php
			/*
			 * «لینکی خراب است؟» — عمداً **بیرون** جدول و بعد از آن می‌نشیند:
			 * جدول دانلود عیناً مرجع است و آزمون هم‌سانی هندسه‌ی ردیف‌ها را
			 * می‌سنجد؛ افزودن ستون یا دکمه در ردیف، آن قرارداد را می‌شکست.
			 */
			if ( class_exists( __NAMESPACE__ . '\\Reports' ) ) {
				$first_group = null;
				foreach ( $by_season as $season_groups ) {
					if ( ! empty( $season_groups ) ) {
						$first_group = $season_groups[0];
						break;
					}
				}

				$report_url = '';

				if ( $first_group && ! empty( $first_group['items'][0]['url'] ) ) {
					$report_url = (string) $first_group['items'][0]['url'];
				}

				echo '<div class="download-report">' . Reports::button( $post_id, $report_url, $first_group ? (string) $first_group['quality'] : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput — خروجی Reports::button خودش escape شده است
			}
			?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * کارت یک قسمت (`.episode-card` مرجع): سرِ کلیک‌خور با شماره، عنوان،
	 * زیرنویس و فلش، دکمه‌ی پخش گرد، و جدول کیفیت‌های همان قسمت.
	 *
	 * جدول‌ها از سمت سرور ساخته می‌شوند تا جست‌وجو و خزش‌گرها همه‌ی لینک‌ها
	 * را ببینند؛ `front.js` فقط باز/بسته‌کردن را به عهده دارد.
	 *
	 * @param array  $groups   گروه‌های یک قسمت (هم‌مالک و هم‌شماره).
	 * @param int    $season   شماره‌ی فصل (۰ = عمومی).
	 * @param int    $post_id  شناسه‌ی سریال.
	 * @param bool   $expanded  آیا کارت باز باشد.
	 * @param array  $labels    برچسب‌های قسمت، بسته و حجم.
	 * @return string
	 */
	protected static function episode_card( $groups, $season, $post_id, $expanded, $labels ) {
		$first    = $groups[0];
		$number   = max( 0, (int) $first['episode'] );
		$owner    = (int) ( $first['owner'] ?? $post_id );
		$body_id  = 'episode-download-' . $owner . '-' . (int) $season . '-' . $number;
		$title    = trim( (string) ( $first['episode_label'] ?? '' ) );
		$play_url = self::episode_play_url( $post_id, (int) $season, $first );
		$number_fa = manacore_fa_digits( number_format_i18n( $number ) );
		$play_label = sprintf(
			/* translators: %s: شماره قسمت */
			__( 'پخش قسمت %s', 'manacore' ),
			$number_fa
		);

		$language = '';
		foreach ( $groups as $group ) {
			if ( ! empty( $group['language'] ) ) {
				$language = Links::language_label( $group['language'] );
				break;
			}
		}

		$rows = self::download_rows( $groups );
		$cols = self::download_columns( $rows );

		$meta = array();
		if ( (int) $season > 0 ) {
			$meta[] = self::season_label( $season );
		}
		if ( '' !== $title ) {
			$meta[] = $title;
		}
		$small = implode( ' · ', $meta );

		ob_start();
		?>
		<article class="episode-card<?php echo $expanded ? ' expanded' : ''; ?>">
			<div class="episode-heading">
				<button type="button" class="episode-toggle" data-episode="<?php echo esc_attr( $number ); ?>"
					aria-controls="<?php echo esc_attr( $body_id ); ?>"
					aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
					<span class="episode-number"><?php echo esc_html( manacore_fa_digits( str_pad( (string) $number, 2, '0', STR_PAD_LEFT ) ) ); ?></span>
					<span>
						<strong><?php echo esc_html( self::episode_number_label( $number, $labels['episode'] ) ); ?></strong>
						<?php if ( '' !== $small ) : ?>
							<small><?php echo esc_html( $small ); ?></small>
						<?php endif; ?>
					</span>
					<?php if ( '' !== $language ) : ?>
						<span class="episode-subtitle"><?php echo esc_html( $language ); ?></span>
					<?php endif; ?>
					<span class="episode-chevron" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
					</span>
				</button>
				<?php if ( '' !== $play_url ) : ?>
					<a class="episode-play" href="<?php echo esc_url( $play_url ); ?>"
						title="<?php echo esc_attr( $play_label ); ?>" aria-label="<?php echo esc_attr( $play_label ); ?>">
						<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="m8 5 11 7-11 7V5Z"/></svg>
					</a>
				<?php endif; ?>
			</div>
			<div class="download-table episode-download" id="<?php echo esc_attr( $body_id ); ?>"<?php echo $expanded ? '' : ' hidden'; ?>>
				<?php echo self::download_head( $cols, $labels['size'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php
				foreach ( $rows as $row ) {
					echo self::link_row( $row, $cols, $owner, $play_url ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شناسه‌ی پایدار پنل یک فصل (برای `aria-controls` تب‌ها).
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @param int $season  شماره‌ی فصل.
	 * @return string
	 */
	protected static function season_panel_id( $post_id, $season ) {
		return 'manacore-season-' . (int) $post_id . '-' . (int) $season;
	}

	/**
	 * برچسب فصل (۰ = «عمومی»).
	 *
	 * @param int $season شماره‌ی فصل.
	 * @return string
	 */
	protected static function season_label( $season ) {
		if ( (int) $season <= 0 ) {
			return __( 'عمومی', 'manacore' );
		}

		return sprintf(
			/* translators: %s: شماره فصل */
			__( 'فصل %s', 'manacore' ),
			manacore_fa_digits( number_format_i18n( (int) $season ) )
		);
	}

	/**
	 * برچسب شماره‌ی قسمت؛ الگوی مدیر می‌تواند `%s` داشته باشد.
	 *
	 * @param int    $number   شماره‌ی قسمت.
	 * @param string $template الگوی دلخواه (خالی = پیش‌فرض).
	 * @return string
	 */
	protected static function episode_number_label( $number, $template = '' ) {
		$template = trim( (string) $template );

		if ( '' === $template ) {
			$template = __( 'قسمت %s', 'manacore' );
		}

		$digits = manacore_fa_digits( number_format_i18n( max( 0, (int) $number ) ) );

		return false === strpos( $template, '%' )
			? trim( $template . ' ' . $digits )
			: str_replace( array( '%s', '%d' ), $digits, $template );
	}

	/**
	 * تعیین حالت باکس دانلود از نوع پست.
	 *
	 * @param string $mode    حالت درخواستی (`auto|movie|series|episode`).
	 * @param int    $post_id شناسه‌ی پست.
	 * @return string
	 */
	public static function resolve_mode( $mode, $post_id = 0 ) {
		$mode  = sanitize_key( (string) $mode );
		$types = array( 'auto', 'movie', 'series', 'episode' );

		if ( ! in_array( $mode, $types, true ) ) {
			$mode = 'auto';
		}

		if ( 'auto' !== $mode || ! $post_id ) {
			return $mode;
		}

		$type = (string) get_post_type( $post_id );

		if ( 'episode' === $type ) {
			return 'episode';
		}

		if ( in_array( $type, manacore_serial_post_types(), true ) ) {
			return 'series';
		}

		return 'movie';
	}

	/**
	 * تعیین منبع داده‌ی باکس دانلود.
	 *
	 * `auto` برای فیلم و قسمت یعنی «لینک‌های خودِ اثر» و برای سریال/انیمه
	 * یعنی «هم بسته‌های فصل، هم لینک قسمت‌ها» — چون مدیر می‌تواند هر کدام
	 * را جای دیگری ثبت کرده باشد.
	 *
	 * @param string $source  منبع درخواستی (`auto|post|episodes|both`).
	 * @param int    $post_id شناسه‌ی پست.
	 * @param string $mode    حالت باکس.
	 * @return string
	 */
	public static function resolve_source( $source, $post_id = 0, $mode = 'movie' ) {
		$source = sanitize_key( (string) $source );
		$types  = array( 'auto', 'post', 'episodes', 'both' );

		if ( ! in_array( $source, $types, true ) ) {
			$source = 'auto';
		}

		if ( 'auto' !== $source ) {
			return $source;
		}

		return 'series' === $mode ? 'both' : 'post';
	}

	/**
	 * نشانی پخش برای ردیفِ یک قسمت.
	 *
	 * اولویت مثل `Blocks::render_episodes()` است: پستِ هدف با همان
	 * فصل/قسمت، بعد خودِ قسمت، بعد سریال؛ و در نبود هر منبعی رشته‌ی
	 * خالی برمی‌گردد تا پیوند ناقص به کاربر نرسد.
	 *
	 * @param int   $parent شناسه‌ی سریال.
	 * @param int   $season شماره‌ی فصل.
	 * @param array $group  گروه لینک (با کلید owner).
	 * @return string
	 */
	protected static function episode_play_url( $parent, $season, $group ) {
		if ( ! class_exists( __NAMESPACE__ . '\Player' ) ) {
			return '';
		}

		$episode_id = (int) ( $group['owner'] ?? 0 );
		$number     = max( 0, (int) ( $group['episode'] ?? 0 ) );
		$season     = max( 0, (int) $season );
		$args       = array(
			'season'  => $season,
			'episode' => $number,
		);

		$target = Player::resolve_target( (int) $parent, $season, $number );

		if ( $target && Player::has_sources( $target ) ) {
			return Player::url_for( $target, $args );
		}

		if ( $episode_id && Player::has_sources( $episode_id ) ) {
			return Player::url_for( $episode_id, $args );
		}

		return Player::has_sources( (int) $parent ) ? Player::url_for( (int) $parent, $args ) : '';
	}

	/**
	 * راهنمای مدیر وقتی اثری هیچ لینکی ندارد.
	 *
	 * در نمای همگانی هیچ‌چیز چاپ نمی‌شود (مارک‌آپ دست‌نخورده می‌ماند)، ولی
	 * در پیش‌نمایش ویرایشگر/بافت مدیریت، جای خالی باکس با پیوند
	 * «افزودن لینک‌ها» پر می‌شود؛ پیش‌تر باکس بی‌صدا ناپدید می‌شد و
	 * تشخیص علتش سخت بود.
	 *
	 * @param int    $post_id شناسه‌ی پست.
	 * @param string $mode    حالت باکس.
	 * @return string
	 */
	protected static function editor_hint( $post_id, $mode = 'movie' ) {
		/*
		 * راهنما فقط در بافتِ مدیریت/پیش‌نمایش ویرایشگر می‌آید: در نمای
		 * همگانی — حتی برای مدیر وارد‌شده — هیچ مارک‌آپی اضافه نمی‌شود تا
		 * هندسه‌ی صفحه‌ی عمومی و خروجی خزش‌گرها دست‌نخورده بماند.
		 */
		$in_admin = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		if ( ! $post_id || ! $in_admin || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}

		/*
		 * نشانی ویرایشگر از همان متد کلاس `Links_Admin` می‌آید تا لنگر
		 * متاباکس لینک‌ها در یک جا ساخته شود (یک منبع حقیقت).
		 */
		$edit_url = class_exists( __NAMESPACE__ . '\Links_Admin' )
			? Links_Admin::links_url( $post_id )
			: (string) get_edit_post_link( $post_id, 'raw' );

		if ( '' === $edit_url ) {
			return '';
		}

		$label = 'series' === $mode
			? __( 'برای این سریال و قسمت‌هایش هنوز لینکی ثبت نشده است.', 'manacore' )
			: __( 'برای این اثر هنوز لینکی ثبت نشده است.', 'manacore' );

		ob_start();
		?>
		<section class="download-section is-empty" id="download" data-mode="<?php echo esc_attr( $mode ); ?>">
			<div class="demo-notice">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="12" cy="12" r="9"/><path d="M12 16v-5"/><path d="M12 8h.01"/>
				</svg>
				<p>
					<?php echo esc_html( $label ); ?>
					<a class="text-link" href="<?php echo esc_url( $edit_url ); ?>">
						<?php esc_html_e( 'افزودن لینک‌ها', 'manacore' ); ?>
					</a>
				</p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * سرصفحه‌ی پیش‌فرض هر حالت.
	 *
	 * @param string $mode حالت.
	 * @return string
	 */
	protected static function default_heading( $mode ) {
		if ( 'series' === $mode ) {
			return __( 'دانلود کامل فصل‌ها', 'manacore' );
		}

		if ( 'episode' === $mode ) {
			return __( 'لینک‌های این قسمت', 'manacore' );
		}

		return __( 'لینک‌های دانلود و پخش', 'manacore' );
	}

	/**
	 * پالایش گروه‌های لینک بر پایه‌ی نوع و کیفیت.
	 *
	 * هر دو فیلتر روی «لینک‌ها» اعمال می‌شوند، نه روی گروه: اگر گروهی
	 * چند لینک از چند نوع داشته باشد، فقط لینک‌های خواسته‌شده می‌مانند و
	 * گروهی که هیچ لینکی برایش نماند حذف می‌شود. فصل‌های خالی هم حذف
	 * می‌شوند تا تبِ «فصل» بدون محتوا ساخته نشود.
	 *
	 * این متد پیش‌تر صدا زده می‌شد ولی تعریف نشده بود؛ نتیجه‌اش خطای
	 * مرگبار (`Call to undefined method`) روی هر سایتی بود که برای بلوک
	 * «لینک‌های دانلود» فیلتر نوع یا کیفیت انتخاب می‌کرد.
	 *
	 * @param array $by_season      گروه‌ها به تفکیک فصل.
	 * @param array $type_filter    کلیدهای نوع لینک.
	 * @param array $quality_filter کلیدهای کیفیت.
	 * @return array
	 */
	public static function filter_link_seasons( $by_season, $type_filter = array(), $quality_filter = array() ) {
		$type_filter    = array_filter( array_map( 'sanitize_key', (array) $type_filter ) );
		$quality_filter = array_filter( array_map( 'sanitize_key', (array) $quality_filter ) );

		if ( ! $type_filter && ! $quality_filter ) {
			return (array) $by_season;
		}

		$out = array();

		foreach ( (array) $by_season as $season => $groups ) {
			$kept = array();

			foreach ( (array) $groups as $group ) {
				$quality = sanitize_key( (string) $group['quality'] );
				$title   = sanitize_key( (string) $group['title'] );

				if ( '' === $quality ) {
					$quality = $title;
				}

				if ( $quality_filter && ! in_array( $quality, $quality_filter, true ) ) {
					continue;
				}

				$items = (array) $group['items'];

				if ( $type_filter ) {
					$items = array_values(
						array_filter(
							$items,
							static function ( $item ) use ( $type_filter ) {
								return in_array( sanitize_key( (string) $item['type'] ), $type_filter, true );
							}
						)
					);

					if ( ! $items ) {
						continue;
					}

					$group['items'] = $items;
				}

				$kept[] = $group;
			}

			if ( $kept ) {
				$out[ $season ] = $kept;
			}
		}

		return $out;
	}

	/**
	 * ردیف‌های تخت جدول دانلود برای گروه‌های یک نقطه‌ی نمایش.
	 *
	 * هر لینک یک ردیف است؛ کیفیت، نام، زبان/دوبله، انکودر و حجمِ هر لینک از
	 * خودِ همان لینک خوانده می‌شود و اگر خالی باشد، از گروه ارث می‌برد
	 * (`Links::flatten()`). لینکِ بدون نشانی در خروجی نمی‌آید.
	 *
	 * @param array $groups گروه‌های لینک.
	 * @return array<int,array>
	 */
	public static function download_rows( $groups ) {
		$rows = array();

		foreach ( (array) $groups as $group ) {
			if ( is_array( $group ) ) {
				$rows = array_merge( $rows, Links::flatten( $group ) );
			}
		}

		return $rows;
	}

	/**
	 * کدام ستون‌های جدول دانلود داده دارند؟
	 *
	 * ستونی که در هیچ ردیفی مقدار نداشته باشد، کلاً حذف می‌شود: هم سرستونش
	 * و هم سلول‌هایش، تا جای خالی باقی نماند. نام هر ردیف، نام خودِ لینک یا
	 * در نبودش کلید `badge` همان ردیف است. ستون «پخش و دانلود» همیشه هست.
	 *
	 * @param array $rows ردیف‌های تخت (خروجی `download_rows()`).
	 * @return array{quality:bool,name:bool,language:bool,encoder:bool,size:bool}
	 */
	public static function download_columns( $rows ) {
		$cols = array(
			'quality'  => false,
			'name'     => false,
			'language' => false,
			'encoder'  => false,
			'size'     => false,
		);

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name_values = array(
				'quality'  => $row['quality'] ?? '',
				'language' => $row['language'] ?? '',
				'encoder'  => $row['encoder'] ?? '',
				'size'     => $row['size'] ?? '',
			);

			foreach ( $name_values as $col => $value ) {
				if ( '' !== trim( (string) $value ) ) {
					$cols[ $col ] = true;
				}
			}

			if ( '' !== self::row_name( $row ) ) {
				$cols['name'] = true;
			}
		}

		return $cols;
	}

	/**
	 * ردیف‌های تخت یک فهرست بسته، با نام جایگزین هر ردیف.
	 *
	 * بسته‌ی کامل فصل، با `$badge` نام می‌گیرد؛ ردیفی که به یک قسمت تعلق دارد
	 * (مالک ≠ اثر) نام بسته نمی‌گیرد و به‌جایش نامِ خودِ لینک می‌ماند.
	 *
	 * @param array  $groups گروه‌های بسته.
	 * @param string $badge  برچسب نام بسته (خالی = بدون برچسب).
	 * @return array<int,array>
	 */
	public static function pack_rows( $groups, $badge = '' ) {
		$rows = self::download_rows( $groups );

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['badge'] = empty( $row['owner'] ) ? $badge : '';
		}

		return $rows;
	}

	/**
	 * نام نمایشی یک ردیف: نام خودِ لینک، و در نبودش برچسب `badge` همان ردیف.
	 *
	 * @param array $row ردیف تخت.
	 * @return string
	 */
	public static function row_name( $row ) {
		$name = trim( (string) ( $row['label'] ?? '' ) );

		return '' !== $name ? $name : trim( (string) ( $row['badge'] ?? '' ) );
	}

	/**
	 * سرستون جدول دانلود؛ فقط برچسب ستون‌هایی که داده دارند.
	 *
	 * کلاس‌های `dl-col-*` همان‌هایی است که ردیف‌ها دارند، پس عرض ستون‌ها
	 * در سرستون و ردیف‌ها یکسان می‌ماند.
	 *
	 * @param array  $cols       خروجی `download_columns()`.
	 * @param string $size_label برچسب ستون حجم (از تنظیمات بلوک).
	 * @return string
	 */
	public static function download_head( $cols, $size_label = '' ) {
		$size_label = '' !== trim( (string) $size_label ) ? (string) $size_label : __( 'حجم', 'manacore' );

		ob_start();
		?>
		<div class="download-table-header">
			<?php if ( ! empty( $cols['quality'] ) ) : ?>
				<span class="dl-col-quality"><?php esc_html_e( 'کیفیت تصویر', 'manacore' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['name'] ) ) : ?>
				<span class="dl-col-name"><?php esc_html_e( 'نام', 'manacore' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['language'] ) ) : ?>
				<span class="dl-col-lang"><?php esc_html_e( 'زبان / دوبله', 'manacore' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['encoder'] ) ) : ?>
				<span class="dl-col-encoder"><?php esc_html_e( 'انکودر', 'manacore' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['size'] ) ) : ?>
				<span class="dl-col-size"><?php echo esc_html( $size_label ); ?></span>
			<?php endif; ?>
			<span class="dl-col-actions"><?php esc_html_e( 'پخش و دانلود', 'manacore' ); ?></span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * یک ردیف جدول دانلود (`.download-row`) برای یک لینک.
	 *
	 * ستون‌هایی که در `$cols` نیستند، اصلاً رندر نمی‌شوند. کنش‌ها:
	 *   «▶ پخش» برای لینک‌های آنلاین (به صفحه‌ی پخش با کیفیت همین ردیف)،
	 *   «⇩ دانلود» برای بقیه؛ لینکِ گروه ویژه به‌جایش دکمه‌ی اشتراک می‌گیرد.
	 *
	 * @param array  $row               ردیف تخت (خروجی `download_rows()`).
	 * @param array  $cols              ستون‌های نمایشی (خروجی `download_columns()`).
	 * @param int    $post_id           شناسه‌ی پست نمایش (مالک پیش‌فرض ردیف).
	 * @param string $play_url_override نشانی پخش صریح (برای قسمت‌ها).
	 * @return string
	 */
	public static function link_row( $row, $cols, $post_id, $play_url_override = '' ) {
		$owner  = ! empty( $row['owner'] ) ? (int) $row['owner'] : (int) $post_id;
		$locked = ! empty( $row['premium'] ) && ! manacore_user_can_access( $owner );

		$quality  = trim( (string) ( $row['quality'] ?? '' ) );
		$name     = self::row_name( $row );
		$language = '' !== trim( (string) ( $row['language'] ?? '' ) ) ? Links::language_label( (string) $row['language'] ) : '';
		$encoder  = trim( (string) ( $row['encoder'] ?? '' ) );
		$size     = trim( (string) ( $row['size'] ?? '' ) );
		$type     = (string) ( $row['type'] ?? 'direct' );
		$url      = (string) ( $row['url'] ?? '' );
		$label    = '' !== $name ? $name : Links::type_label( $type );

		/*
		 * کلید گزینه‌ی این ردیف (کیفیت + زبان + انکودر) همان کلیدی است که
		 * صفحه‌ی پخش می‌سازد؛ پس پیش‌انتخابِ درستِ همان دوبله/زیرنویس روی
		 * صفحه‌ی پخش می‌نشیند.
		 */
		$item_key = Links::variant_key( $quality, $type, (string) ( $row['language'] ?? '' ), $encoder );
		$player   = class_exists( '\\ManaCore\\Core\\Player' ) ? Player::page_url( $owner ) : '';

		ob_start();
		?>
		<div class="download-row">
			<?php if ( ! empty( $cols['quality'] ) ) : ?>
				<span class="quality-name dl-col-quality"><b dir="ltr"><?php echo esc_html( $quality ); ?></b></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['name'] ) ) : ?>
				<span class="download-name dl-col-name"><?php echo esc_html( $name ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['language'] ) ) : ?>
				<span class="download-lang dl-col-lang"><?php echo esc_html( $language ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['encoder'] ) ) : ?>
				<span class="format-tag dl-col-encoder"><?php echo esc_html( $encoder ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $cols['size'] ) ) : ?>
				<span class="download-size dl-col-size"><?php echo esc_html( $size ); ?></span>
			<?php endif; ?>
			<div class="download-actions dl-col-actions">
				<?php if ( $locked ) : ?>
					<?php
					/* برچسب دکمه‌ی اشتراک از پنل مدیریت می‌آید (خالی = پیش‌فرض). */
					$subscribe_label = trim( (string) manacore_get_option( 'subscribe_label', '' ) );

					if ( '' === $subscribe_label ) {
						$subscribe_label = __( 'تهیه اشتراک', 'manacore' );
					}
					?>
					<a class="manacore-btn is-primary is-small"
						href="<?php echo esc_url( apply_filters( 'manacore_subscribe_url', manacore_get_option( 'subscribe_url', home_url( '/subscribe/' ) ) ) ); ?>">
						<?php echo esc_html( $subscribe_label ); ?>
					</a>
				<?php elseif ( '' !== $player && class_exists( '\\ManaCore\\Core\\Blocks' ) && Blocks::is_playable_row( $row ) ) : ?>
					<?php
					/*
					 * «پخش» مثل مرجع به صفحه‌ی پخش می‌رود (نه مُدال) تا
					 * تمام‌صفحه و کیفیت‌ها همان صفحه باشد. کیفیت این ردیف
					 * در نشانی می‌آید تا پلیر همان را پیش‌انتخاب کند.
					 *
					 * `add_query_arg()` مقادیر تازه را کدگذاری نمی‌کند، پس
					 * کدگذاری اینجا با `rawurlencode()` انجام می‌شود؛ وگرنه
					 * کیفیت‌های فارسی/فاصله‌دار در نشانی می‌شکنند.
					 */
					$base_url = '' !== $play_url_override ? $play_url_override : $player;
					$play_url = '' !== $item_key
						? add_query_arg( 'quality', rawurlencode( $item_key ), $base_url )
						: $base_url;
					?>
					<a class="manacore-btn is-secondary is-small" href="<?php echo esc_url( $play_url ); ?>"
						aria-label="<?php echo esc_attr( $label ); ?>">
						<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 5 11 7-11 7V5Z"/></svg>
						<?php esc_html_e( 'پخش', 'manacore' ); ?>
					</a>
				<?php elseif ( 'stream' === $type ) : ?>
					<button type="button" class="manacore-btn is-secondary is-small"
						data-manacore-play="<?php echo esc_url( $url ); ?>"
						data-title="<?php echo esc_attr( $label ); ?>">
						<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 5 11 7-11 7V5Z"/></svg>
						<?php esc_html_e( 'پخش', 'manacore' ); ?>
					</button>
				<?php else : ?>
					<?php
					/*
					 * با روشن‌بودن «امضای لینک دانلود»، نشانی خام فایل روی
					 * صفحه نمی‌آید و جایش یک مسیر داخلی زمان‌دار می‌نشیند.
					 * خاموش‌بودن گزینه = همان رفتار پیشین.
					 */
					$download_url = class_exists( '\\ManaCore\\Core\\Downloads' )
						? Downloads::url_for( $owner, $url, $type )
						: $url;
					?>
					<a class="manacore-btn is-primary is-small" href="<?php echo esc_url( $download_url ); ?>"
						rel="nofollow noopener" target="_blank"
						aria-label="<?php echo esc_attr( $label ); ?>"
						data-manacore-download="<?php echo esc_attr( $owner ); ?>">
						<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>
						<?php esc_html_e( 'دانلود', 'manacore' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * جعبه‌ی قفل.
	 *
	 * @param string $message پیام.
	 * @param string $url     آدرس دکمه.
	 * @param string $label   برچسب دکمه.
	 * @return string
	 */
	public static function locked_notice( $message, $url, $label ) {
		ob_start();
		?>
		<div class="manacore-locked">
			<span class="manacore-locked-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="26" height="26">
					<path fill="currentColor" d="M18 8h-1V6a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zM9 6a3 3 0 0 1 6 0v2H9V6z"/>
				</svg>
			</span>
			<p><?php echo esc_html( $message ); ?></p>
			<a class="manacore-btn is-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * رندر فهرست مشخصات اثر.
	 *
	 * @param int $post_id شناسه‌ی پست.
	 * @return string
	 */
	public static function meta_list( $post_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'fields'      => array(),
				'link_terms'  => true,
				'layout'      => 'rows',
				'hide_empty'  => true,
				'label_width' => 0,
			)
		);

		$fields = array_filter( array_map( 'sanitize_key', (array) $args['fields'] ) );
		if ( empty( $fields ) ) {
			$fields = array( 'original_title', 'year', 'runtime', 'genre', 'country', 'network', 'studio', 'director', 'writer', 'status' );
		}

		$rows = array();
		foreach ( $fields as $field ) {
			$row = self::meta_row( $post_id, $field, (bool) $args['link_terms'] );
			if ( null === $row ) {
				continue;
			}
			// جلوگیری از بازنویسی برچسب‌های یکسان.
			$label = $row['label'];
			if ( isset( $rows[ $label ] ) ) {
				continue;
			}
			$rows[ $label ] = $row['value'];
		}

		/**
		 * فیلتر ردیف‌های جدول مشخصات.
		 *
		 * @param array $rows    ردیف‌ها (برچسب => HTML).
		 * @param int   $post_id شناسه‌ی پست.
		 * @param array $args    تنظیمات.
		 */
		$rows = (array) apply_filters( 'manacore_meta_list_rows', $rows, $post_id, $args );

		if ( empty( $rows ) ) {
			return '';
		}

		$classes = array( 'manacore-meta-list' );
		if ( in_array( $args['layout'], array( 'columns', 'inline' ), true ) ) {
			$classes[] = 'is-layout-' . $args['layout'];
		}

		$style = $args['label_width'] ? sprintf( '--mc-meta-label:%dpx', max( 60, min( 320, (int) $args['label_width'] ) ) ) : '';

		ob_start();
		?>
		<dl class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
			<?php foreach ( $rows as $label => $value ) : ?>
				<div class="manacore-meta-row">
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo wp_kses_post( $value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ساخت یک ردیف جدول مشخصات.
	 *
	 * @param int    $post_id    شناسه‌ی پست.
	 * @param string $field      کلید فیلد (بدون پیشوند manacore_).
	 * @param bool   $link_terms پیوند دادن ترم‌ها.
	 * @return array|null آرایه‌ی array( 'label', 'value' ) یا null در صورت خالی بودن.
	 */
	protected static function meta_row( $post_id, $field, $link_terms = true ) {
		$labels     = Block_Data::meta_fields();
		$label      = isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
		$taxonomies = array( 'genre', 'country', 'network', 'studio', 'language', 'quality', 'release_year' );

		// تاکسونومی‌ها.
		if ( in_array( $field, $taxonomies, true ) ) {
			$terms = get_the_terms( $post_id, $field );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return null;
			}
			$parts = array();
			foreach ( $terms as $term ) {
				if ( $link_terms ) {
					$url     = get_term_link( $term );
					$parts[] = is_wp_error( $url )
						? esc_html( $term->name )
						: '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
				} else {
					$parts[] = esc_html( $term->name );
				}
			}
			return array(
				'label' => $label,
				'value' => implode( '، ', $parts ),
			);
		}

		switch ( $field ) {
			case 'director':
			case 'writer':
			case 'producer':
			case 'composer':
				$crew = Crew::html( $post_id, $field );
				return '' !== $crew ? array(
					'label' => $label,
					'value' => $crew,
				) : null;

			case 'year':
				$year = self::year( $post_id );
				return $year ? array(
					'label' => $label,
					'value' => esc_html( $year ),
				) : null;

			case 'runtime':
			case 'episode_runtime':
				$minutes = (int) get_post_meta( $post_id, 'manacore_' . $field, true );
				return $minutes ? array(
					'label' => $label,
					'value' => esc_html(
						sprintf(
							/* translators: %s: تعداد دقیقه */
							__( '%s دقیقه', 'manacore' ),
							number_format_i18n( $minutes )
						)
					),
				) : null;

			case 'status':
			case 'air_day':
				$value = get_post_meta( $post_id, 'manacore_' . $field, true );
				if ( ! $value ) {
					return null;
				}
				$schema  = Meta::instance()->flat_fields();
				$options = isset( $schema[ 'manacore_' . $field ]['options'] ) ? $schema[ 'manacore_' . $field ]['options'] : array();
				return array(
					'label' => $label,
					'value' => esc_html( isset( $options[ $value ] ) ? $options[ $value ] : $value ),
				);

			case 'imdb_rating':
			case 'tmdb_rating':
			case 'mal_rating':
			case 'editor_score':
				$value = (float) get_post_meta( $post_id, 'manacore_' . $field, true );
				return $value > 0 ? array(
					'label' => $label,
					'value' => esc_html( number_format_i18n( $value, 1 ) ),
				) : null;

			case 'views':
			case 'total_seasons':
			case 'total_episodes':
			case 'season_number':
			case 'episode_number':
				$value = (int) get_post_meta( $post_id, 'manacore_' . $field, true );
				return $value ? array(
					'label' => $label,
					'value' => esc_html( number_format_i18n( $value ) ),
				) : null;

			default:
				$value = get_post_meta( $post_id, 'manacore_' . $field, true );
				if ( is_array( $value ) ) {
					$value = implode( '، ', array_filter( array_map( 'strval', $value ) ) );
				}
				$value = trim( (string) $value );
				return '' !== $value ? array(
					'label' => $label,
					'value' => esc_html( $value ),
				) : null;
		}
	}
}

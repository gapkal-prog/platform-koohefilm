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
		if ( 'poster' !== $style ) {
			$classes[] = 'is-style-' . $style;
		}
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
	 * @param int $post_id شناسه‌ی پست.
	 * @return string
	 */
	public static function links( $post_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'box_style'    => 'cards',
				'heading'      => '',
				'heading_tag'  => 'h2',
				'show_heading' => true,
				'show_icon'    => true,
				'show_count'   => true,
				'show_notice'  => true,
				'show_tabs'    => true,
				'types'        => array(),
				'qualities'    => array(),
				'season'       => 0,
				'open_first'   => true,
			)
		);

		if ( get_post_meta( $post_id, 'manacore_disable_links', true ) ) {
			return '';
		}

		$groups = Links::get( $post_id );
		if ( empty( $groups ) ) {
			return '';
		}

		$login_only = (int) manacore_get_option( 'links_login_only', 0 );
		if ( $login_only && ! is_user_logged_in() ) {
			return self::locked_notice(
				__( 'برای مشاهده‌ی لینک‌های دانلود وارد حساب کاربری شوید.', 'manacore' ),
				wp_login_url( get_permalink( $post_id ) ),
				__( 'ورود به حساب', 'manacore' )
			);
		}

		$has_access = manacore_user_can_access( $post_id );
		$notice     = $args['show_notice'] ? get_post_meta( $post_id, 'manacore_custom_notice', true ) : '';
		$by_season  = Links::by_season( $post_id );

		// فیلتر بر اساس فصل انتخاب‌شده در بلوک.
		$season_filter = (int) $args['season'];
		if ( $season_filter && isset( $by_season[ $season_filter ] ) ) {
			$by_season = array( $season_filter => $by_season[ $season_filter ] );
		}

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
		$heading     = $args['heading'] ? $args['heading'] : __( 'لینک‌های دانلود و پخش', 'manacore' );
		$heading_tag = in_array( $args['heading_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $args['heading_tag'] : 'h2';

		// سبک ظاهری با فهرست مشترک اعتبارسنجی می‌شود.
		$box_style = Block_Support::pick( $args['box_style'], Block_Data::download_styles(), 'cards' );

		ob_start();
		?>
		<section class="manacore-links is-box-<?php echo esc_attr( $box_style ); ?>" id="download">
			<?php if ( $args['show_heading'] || $args['show_count'] ) : ?>
				<header class="manacore-links-header">
					<?php if ( $args['show_heading'] ) : ?>
						<<?php echo esc_html( $heading_tag ); ?> class="manacore-section-title">
							<?php if ( $args['show_icon'] ) : ?>
								<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
									<path fill="currentColor" d="M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z"/>
								</svg>
							<?php endif; ?>
							<?php echo esc_html( $heading ); ?>
						</<?php echo esc_html( $heading_tag ); ?>>
					<?php endif; ?>
					<?php if ( $args['show_count'] ) : ?>
						<span class="manacore-links-total">
							<?php
							printf(
								/* translators: %s: تعداد لینک */
								esc_html__( '%s لینک', 'manacore' ),
								esc_html( number_format_i18n( Links::count( $post_id ) ) )
							);
							?>
						</span>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<?php if ( $notice ) : ?>
				<div class="manacore-inline-notice"><?php echo esc_html( $notice ); ?></div>
			<?php endif; ?>

			<?php if ( $multi ) : ?>
				<div class="manacore-season-tabs" role="tablist">
					<?php $first = true; ?>
					<?php foreach ( array_keys( $by_season ) as $season ) : ?>
						<button type="button" class="manacore-season-tab<?php echo $first ? ' is-active' : ''; ?>"
							data-season-tab="<?php echo esc_attr( $season ); ?>" role="tab"
							aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
							<?php
							echo $season
								/* translators: %s: شماره فصل */
								? esc_html( sprintf( __( 'فصل %s', 'manacore' ), number_format_i18n( $season ) ) )
								: esc_html__( 'عمومی', 'manacore' );
							?>
						</button>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php $first = true; ?>
			<?php foreach ( $by_season as $season => $season_groups ) : ?>
				<div class="manacore-season-panel<?php echo ( ! $multi || $first ) ? ' is-active' : ''; ?>"
					data-season-panel="<?php echo esc_attr( $season ); ?>">
					<?php foreach ( $season_groups as $group ) : ?>
						<?php echo self::link_group( $group, $has_access, $post_id, $box_style ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
				<?php $first = false; ?>
			<?php endforeach; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * فیلتر گروه‌های لینک بر اساس نوع و کیفیت.
	 *
	 * @param array $by_season      گروه‌ها به تفکیک فصل.
	 * @param array $type_filter    نوع‌های مجاز.
	 * @param array $quality_filter کیفیت‌های مجاز.
	 * @return array
	 */
	protected static function filter_link_seasons( $by_season, $type_filter, $quality_filter ) {
		$out = array();

		foreach ( $by_season as $season => $groups ) {
			$kept = array();

			foreach ( $groups as $group ) {
				if ( $quality_filter && ! in_array( sanitize_key( (string) $group['quality'] ), $quality_filter, true ) ) {
					continue;
				}

				if ( $type_filter ) {
					$items = array();
					foreach ( $group['items'] as $item ) {
						if ( in_array( sanitize_key( (string) $item['type'] ), $type_filter, true ) ) {
							$items[] = $item;
						}
					}
					if ( empty( $items ) ) {
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
	 * رندر یک گروه لینک.
	 *
	 * @param array  $group      گروه.
	 * @param bool   $has_access دسترسی کاربر.
	 * @param int    $post_id    شناسه‌ی پست.
	 * @param string $box_style  سبک ظاهری جعبه.
	 * @return string
	 */
	protected static function link_group( $group, $has_access, $post_id, $box_style = 'cards' ) {
		$locked = $group['premium'] && ! $has_access;

		/*
		 * در سبک آکاردئونی از <details>/<summary> بومی استفاده می‌شود؛
		 * بازشو/بست‌شدن، دسترسی با صفحه‌کلید و اعلام وضعیت برای صفحه‌خوان
		 * را خودِ مرورگر تأمین می‌کند و هیچ جاوااسکریپتی لازم نیست.
		 */
		$accordion = 'accordion' === $box_style;
		$wrap_tag  = $accordion ? 'details' : 'div';
		$head_tag  = $accordion ? 'summary' : 'div';

		/*
		 * ویژگی open به‌صورت رشته‌ی آماده ساخته می‌شود تا در حالت بسته،
		 * فاصله‌ی اضافی هم در تگ نماند.
		 */
		$open_attr = $accordion && ! $locked ? ' open' : '';

		ob_start();
		?>
		<<?php echo esc_html( $wrap_tag ); ?> class="<?php echo esc_attr( $locked ? 'manacore-link-group is-locked' : 'manacore-link-group' ); ?>"<?php echo esc_html( $open_attr ); ?>>
			<<?php echo esc_html( $head_tag ); ?> class="manacore-link-group-head">
				<h3 class="manacore-link-group-title"><?php echo esc_html( $group['title'] ); ?></h3>
				<div class="manacore-chips">
					<?php if ( $group['quality'] ) : ?>
						<span class="manacore-chip is-quality"><?php echo esc_html( Links::quality_label( $group['quality'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $group['language'] ) : ?>
						<span class="manacore-chip"><?php echo esc_html( Links::language_label( $group['language'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $group['encoder'] ) : ?>
						<span class="manacore-chip"><?php echo esc_html( $group['encoder'] ); ?></span>
					<?php endif; ?>
					<?php if ( $group['size'] ) : ?>
						<span class="manacore-chip"><?php echo esc_html( $group['size'] ); ?></span>
					<?php endif; ?>
					<?php if ( $group['premium'] ) : ?>
						<span class="manacore-chip is-premium"><?php esc_html_e( 'ویژه', 'manacore' ); ?></span>
					<?php endif; ?>
				</div>
			</<?php echo esc_html( $head_tag ); ?>>

			<?php if ( $group['note'] ) : ?>
				<p class="manacore-link-note"><?php echo esc_html( $group['note'] ); ?></p>
			<?php endif; ?>

			<?php if ( $locked ) : ?>
				<?php
				echo self::locked_notice( // phpcs:ignore WordPress.Security.EscapeOutput
					__( 'این بخش مخصوص کاربران دارای اشتراک فعال است.', 'manacore' ),
					apply_filters( 'manacore_subscribe_url', home_url( '/subscribe/' ) ),
					__( 'تهیه اشتراک', 'manacore' )
				);
				?>
			<?php else : ?>
				<ul class="manacore-link-list">
					<?php foreach ( $group['items'] as $item ) : ?>
						<li class="manacore-link-item">
							<span class="manacore-link-icon" data-type="<?php echo esc_attr( $item['type'] ); ?>" aria-hidden="true">
								<?php echo self::link_icon( $item['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</span>

							<span class="manacore-link-label">
								<?php
								echo esc_html(
									$item['label']
										? $item['label']
										: Links::type_label( $item['type'] )
								);
								?>
								<?php if ( '' !== $item['episode'] ) : ?>
									<small class="manacore-link-episode">
										<?php
										printf(
											/* translators: %s: شماره قسمت */
											esc_html__( 'قسمت %s', 'manacore' ),
											esc_html( number_format_i18n( $item['episode'] ) )
										);
										?>
									</small>
								<?php endif; ?>
							</span>

							<span class="manacore-link-tags">
								<?php if ( $item['quality'] ) : ?>
									<span class="manacore-chip is-small"><?php echo esc_html( $item['quality'] ); ?></span>
								<?php endif; ?>
								<?php if ( $item['size'] ) : ?>
									<span class="manacore-chip is-small"><?php echo esc_html( $item['size'] ); ?></span>
								<?php endif; ?>
							</span>

							<span class="manacore-link-actions">
								<?php if ( 'stream' === $item['type'] ) : ?>
									<button type="button" class="manacore-btn is-primary is-small"
										data-manacore-play="<?php echo esc_url( $item['url'] ); ?>"
										data-title="<?php echo esc_attr( $item['label'] ); ?>">
										<?php esc_html_e( 'پخش', 'manacore' ); ?>
									</button>
								<?php endif; ?>
								<a class="manacore-btn is-small" href="<?php echo esc_url( $item['url'] ); ?>"
									rel="nofollow noopener" target="_blank"
									data-manacore-download="<?php echo esc_attr( $post_id ); ?>">
									<?php esc_html_e( 'دریافت', 'manacore' ); ?>
								</a>
								<button type="button" class="manacore-btn is-ghost is-small"
									data-manacore-copy="<?php echo esc_url( $item['url'] ); ?>"
									aria-label="<?php esc_attr_e( 'کپی لینک', 'manacore' ); ?>">
									<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">
										<path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/>
									</svg>
								</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</<?php echo esc_html( $wrap_tag ); ?>>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * آیکون نوع لینک.
	 *
	 * @param string $type نوع.
	 * @return string
	 */
	protected static function link_icon( $type ) {
		$paths = array(
			'direct'   => 'M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z',
			'stream'   => 'M8 5v14l11-7z',
			'torrent'  => 'M12 2L2 7l10 5 10-5-10-5zm0 9L2 16l10 5 10-5-10-5z',
			'magnet'   => 'M15 3v8a3 3 0 1 1-6 0V3H5v8a7 7 0 1 0 14 0V3h-4z',
			'subtitle' => 'M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM6 14h5v2H6v-2zm12 0h-5v2h5v-2z',
			'external' => 'M14 3v2h3.6l-9.8 9.8 1.4 1.4L19 6.4V10h2V3h-7zM5 5h5V3H3v18h18v-7h-2v5H5V5z',
		);
		$path  = isset( $paths[ $type ] ) ? $paths[ $type ] : $paths['direct'];
		return '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="' . esc_attr( $path ) . '"/></svg>';
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

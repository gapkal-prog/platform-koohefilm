<?php
/**
 * Title: هیرو سینمایی اثر
 * Slug: koohe-film/title-hero
 * Categories: koohe, koohe-single
 * Keywords: اثر, هیرو, جزئیات
 * Description: هیروی صفحه‌ی اثر — بازآفرینی دقیق detail-hero مرجع سینورا با بج‌ها، نام اصلی، متای درون‌خطی، ژانرها، خلاصه و کنش‌های فعال (پخش، لیست تماشا، کپی لینک).
 * Viewport Width: 1400
 *
 * در بافت صفحه‌ی تکی (single) رندر می‌شود؛ در ویرایشگر با نبود زمینه،
 * کنش‌ها به‌صورت ایستا و بدون شناسه رندر می‌شوند تا خراب نشوند.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

$koohe_post_id = get_the_ID();
$koohe_title   = $koohe_post_id ? get_the_title( $koohe_post_id ) : __( 'نام اثر', 'koohe-film' );
$koohe_orig    = $koohe_post_id ? (string) get_post_meta( $koohe_post_id, 'manacore_original_title', true ) : '';
$koohe_year    = $koohe_post_id ? ManaCore\Core\Templates::year( $koohe_post_id ) : '';
$koohe_runtime = $koohe_post_id ? (int) get_post_meta( $koohe_post_id, 'manacore_runtime', true ) : 0;
$koohe_rating  = $koohe_post_id ? ManaCore\Core\Templates::best_rating( $koohe_post_id ) : 0;
$koohe_ptype   = $koohe_post_id ? get_post_type( $koohe_post_id ) : '';
$koohe_types   = function_exists( 'manacore_post_types' ) ? manacore_post_types() : array();
$koohe_genres  = $koohe_post_id ? get_the_terms( $koohe_post_id, 'genre' ) : array();
$koohe_origin  = $koohe_post_id ? get_the_terms( $koohe_post_id, 'country' ) : array();

/* در ویرایشگر بلوک زمینه‌ی پست نیست؛ خروجی کنش‌ها باید ایستا بماند. */
$koohe_id_attr   = $koohe_post_id ? (int) $koohe_post_id : 0;
$koohe_permalink = $koohe_post_id ? get_permalink( $koohe_post_id ) : '#';
?>
<!-- wp:html -->
<section class="koohe-detail-hero">
	<div class="koohe-detail-hero__backdrop" aria-hidden="true">
		<?php if ( $koohe_post_id && has_post_thumbnail( $koohe_post_id ) ) : ?>
			<?php echo get_the_post_thumbnail( $koohe_post_id, 'full', array( 'alt' => '' ) ); ?>
		<?php endif; ?>
		<span class="koohe-detail-hero__shade"></span>
	</div>

	<div class="koohe-container">
		<div class="koohe-breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'koohe-film' ); ?></a>
			<span>‹</span>
			<?php if ( isset( $koohe_types[ $koohe_ptype ] ) ) : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( $koohe_ptype ) ); ?>"><?php echo esc_html( $koohe_types[ $koohe_ptype ] ); ?></a>
				<span>‹</span>
			<?php endif; ?>
			<span><?php echo esc_html( $koohe_title ); ?></span>
		</div>

		<div class="koohe-detail-hero__main">
			<div class="koohe-detail-hero__poster">
				<?php
				if ( $koohe_post_id ) {
					echo get_the_post_thumbnail( $koohe_post_id, 'medium_large', array( 'alt' => esc_attr( $koohe_title ) ) );
				}
				?>
				<a class="koohe-btn koohe-btn--glass" href="#trailer">▶ <?php esc_html_e( 'پخش تریلر', 'koohe-film' ); ?></a>
			</div>

			<div class="koohe-detail-hero__copy">
				<div class="koohe-detail-hero__tags">
					<?php if ( isset( $koohe_types[ $koohe_ptype ] ) ) : ?>
						<span><?php echo esc_html( $koohe_types[ $koohe_ptype ] ); ?></span>
					<?php endif; ?>
					<?php
					$age = $koohe_post_id ? get_the_terms( $koohe_post_id, 'age_rating' ) : array();
					if ( $age && ! is_wp_error( $age ) ) :
						?>
						<span class="koohe-detail-hero__age"><?php echo esc_html( $age[0]->name ); ?></span>
					<?php endif; ?>
				</div>

				<h1><?php echo esc_html( $koohe_title ); ?></h1>

				<?php if ( $koohe_orig ) : ?>
					<p class="koohe-detail-hero__original" dir="ltr"><?php echo esc_html( $koohe_orig ); ?></p>
				<?php endif; ?>

				<div class="koohe-detail-hero__meta">
					<?php if ( $koohe_rating ) : ?>
						<span class="koohe-detail-hero__imdb"><b>IMDb</b><strong><?php echo esc_html( number_format_i18n( (float) $koohe_rating, 1 ) ); ?></strong>★</span>
						<i></i>
					<?php endif; ?>
					<?php if ( $koohe_year ) : ?>
						<span><?php echo esc_html( $koohe_year ); ?></span>
						<i></i>
					<?php endif; ?>
					<?php if ( $koohe_runtime ) : ?>
						<span>⏱ <?php echo esc_html( sprintf( __( '%s دقیقه', 'koohe-film' ), number_format_i18n( $koohe_runtime ) ) ); ?></span>
						<i></i>
					<?php endif; ?>
					<?php
					if ( $koohe_origin && ! is_wp_error( $koohe_origin ) ) :
						$koohe_countries = array_slice( wp_list_pluck( $koohe_origin, 'name' ), 0, 2 );
						?>
						<span>◎ <?php echo esc_html( implode( '، ', $koohe_countries ) ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $koohe_genres && ! is_wp_error( $koohe_genres ) ) : ?>
					<div class="koohe-detail-hero__genres">
						<?php foreach ( $koohe_genres as $koohe_genre ) : ?>
							<a href="<?php echo esc_url( get_term_link( $koohe_genre ) ); ?>"><?php echo esc_html( $koohe_genre->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $koohe_post_id && get_the_excerpt( $koohe_post_id ) ) : ?>
					<p class="koohe-detail-hero__summary"><?php echo esc_html( wp_trim_words( get_the_excerpt( $koohe_post_id ), 42 ) ); ?></p>
				<?php endif; ?>

				<div class="koohe-detail-hero__actions">
					<a class="koohe-btn koohe-btn--primary" href="#download">▶ <?php esc_html_e( 'پخش و دانلود', 'koohe-film' ); ?></a>
					<button class="koohe-btn koohe-btn--glass koohe-detail-hero__watchlist" type="button" data-manacore-watchlist="<?php echo esc_attr( $koohe_id_attr ); ?>">♡ <?php esc_html_e( 'لیست تماشا', 'koohe-film' ); ?></button>
					<button class="koohe-btn koohe-btn--glass" type="button" data-manacore-copy="<?php echo esc_url( $koohe_permalink ); ?>">⧉ <?php esc_html_e( 'کپی لینک', 'koohe-film' ); ?></button>
				</div>

				<p class="koohe-detail-hero__note"><span class="koohe-live-dot" aria-hidden="true"></span><?php esc_html_e( 'دوبله فارسی و زیرنویس اختصاصی', 'koohe-film' ); ?></p>
			</div>
		</div>
	</div>
</section>
<!-- /wp:html -->

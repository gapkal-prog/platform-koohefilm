<?php
/**
 * Title: بنر اشتراک (داستان‌های بیشتر، محدودیت‌های کمتر)
 * Slug: koohe-film/subscribe-cta
 * Categories: koohe, koohe-page, call-to-action
 * Keywords: اشتراک, ویژه, subscribe, cta, بنر
 * Description: بنر اشتراک مطابق مرجع: آیکون، برچسب، تیتر، توضیح، دکمه و مدار تزئینی.
 * Viewport Width: 1400
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"subscription-banner","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide subscription-banner"><!-- wp:html -->
<span class="subscription-banner-icon" aria-hidden="true">
	<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 4 3 12h14l3-12-6 5-4-7-4 7-6-5zM5 20h14"/></svg>
</span>
<!-- /wp:html -->

	<!-- wp:group {"className":"koohe-subscription-copy","layout":{"type":"default"}} -->
	<div class="wp-block-group koohe-subscription-copy">
		<!-- wp:paragraph {"className":"eyebrow"} -->
		<p class="eyebrow"><?php echo esc_html__( 'کوهه پلاس', 'koohe-film' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'داستان‌های بیشتر. محدودیت‌های کمتر.', 'koohe-film' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'با اشتراک کوهه، دنیای فیلم و سریال همیشه همراه توست.', 'koohe-film' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/subscribe/' ) ); ?>"><?php echo esc_html__( 'اشتراک خودت را انتخاب کن', 'koohe-film' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->

	<!-- wp:html -->
	<span class="banner-orbit" aria-hidden="true"></span>
	<!-- /wp:html -->
</div>
<!-- /wp:group -->

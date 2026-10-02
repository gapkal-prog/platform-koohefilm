<?php
/**
 * Title: فراخوان اشتراک ویژه
 * Slug: koohe-film/subscribe-cta
 * Categories: koohe, koohe-page, call-to-action
 * Keywords: اشتراک, ویژه, subscribe, cta
 * Description: بخش فراخوان تهیه‌ی اشتراک ویژه با دکمه‌ی خرید.
 * Viewport Width: 1400
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"koohe-cta is-style-koohe-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"20px"}},"backgroundColor":"surface-2","layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group alignwide koohe-cta is-style-koohe-card has-surface-2-background-color has-background" style="border-radius:20px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":2,"fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-text-align-center has-xx-large-font-size"><?php echo esc_html__( 'دسترسی نامحدود با اشتراک ویژه', 'koohe-film' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"medium","style":{"color":{"text":"var:preset|color|muted"}}} -->
<p class="has-text-align-center has-text-color has-medium-font-size"><?php echo esc_html__( 'همه‌ی کیفیت‌ها، بدون تبلیغات، با سرعت کامل و پشتیبانی اختصاصی.', 'koohe-film' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--30)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/subscribe/"><?php echo esc_html__( 'تهیه‌ی اشتراک', 'koohe-film' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-koohe-ghost"} -->
<div class="wp-block-button is-style-koohe-ghost"><a class="wp-block-button__link wp-element-button" href="/subscribe/#faq"><?php echo esc_html__( 'اطلاعات بیشتر', 'koohe-film' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

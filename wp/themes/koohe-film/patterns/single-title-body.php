<?php
/**
 * Title: بدنه‌ی صفحه‌ی اثر
 * Slug: koohe-film/single-title-body
 * Categories: koohe, koohe-single
 * Keywords: فیلم, تریلر, دانلود, بازیگران
 * Description: بخش‌های استاندارد صفحه‌ی تکی یک اثر: تریلر، لینک دانلود، بازیگران و آثار مشابه.
 * Block Types: core/post-content
 * Viewport Width: 1200
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"koohe-content-block","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group koohe-content-block"><!-- wp:manacore/trailer {"heading":"<?php echo esc_attr__( 'تریلر رسمی', 'koohe-film' ); ?>"} /-->

<!-- wp:manacore/download-links /-->

<!-- wp:manacore/cast-list {"heading":"<?php echo esc_attr__( 'بازیگران و عوامل', 'koohe-film' ); ?>","count":16} /-->

<!-- wp:manacore/titles-grid {"heading":"<?php echo esc_attr__( 'آثار مشابه', 'koohe-film' ); ?>","source":"related","count":6,"columns":6,"showMore":false,"layout":"grid"} /--></div>
<!-- /wp:group -->

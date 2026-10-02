<?php
/**
 * Title: ابر ژانرها
 * Slug: koohe-film/genre-cloud
 * Categories: koohe, koohe-listing
 * Keywords: ژانر, دسته, genre
 * Description: نمایش فهرست ژانرها به شکل قرص‌های قابل کلیک.
 * Viewport Width: 1400
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"koohe-genre-cloud","style":{"spacing":{"blockGap":"14px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide koohe-genre-cloud"><!-- wp:heading {"level":2,"className":"is-style-koohe-section","fontSize":"x-large"} -->
<h2 class="wp-block-heading is-style-koohe-section has-x-large-font-size"><?php echo esc_html__( 'مرور بر پایه‌ی ژانر', 'koohe-film' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"taxonomy":"genre","showTagCounts":true,"numberOfTags":30,"smallestFontSize":"0.8125rem","largestFontSize":"0.9375rem","className":"koohe-tagcloud"} /--></div>
<!-- /wp:group -->

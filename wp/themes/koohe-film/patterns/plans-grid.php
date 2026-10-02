<?php
/**
 * Title: جدول طرح‌های اشتراک
 * Slug: koohe-film/plans-grid
 * Categories: koohe, koohe-page
 * Keywords: اشتراک, طرح, پلن, plans
 * Description: نمایش طرح‌های اشتراک به همراه وضعیت اشتراک کاربر جاری.
 * Viewport Width: 1200
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"koohe-plans-section","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide koohe-plans-section"><!-- wp:heading {"textAlign":"center","level":2,"className":"is-style-koohe-underline"} -->
<h2 class="wp-block-heading has-text-align-center is-style-koohe-underline"><?php echo esc_html__( 'طرح‌های اشتراک', 'koohe-film' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:manacore/subscription-status {"compact":true} /-->

<!-- wp:manacore/subscription-plans /--></div>
<!-- /wp:group -->

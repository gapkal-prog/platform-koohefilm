<?php
/**
 * Title: میان‌برهای کشف
 * Slug: koohe-film/discovery-shortcuts
 * Categories: koohe, koohe-listing
 * Keywords: میان‌بر, کشف, discovery
 * Description: ردیف چهارستونه‌ی دسترسی سریع به فیلم، سریال، انیمه و بازیگران — بازآفرینی discovery-shortcuts مرجع سینورا.
 * Viewport Width: 1400
 *
 * پیوندها هنگام هر رندر از آرشیوهای واقعی سایت خوانده می‌شوند.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

$links = array(
	array(
		'url'   => get_post_type_archive_link( 'movie' ),
		'title' => esc_attr__( 'سینمای جهان', 'koohe-film' ),
		'hint'  => esc_attr__( 'یک بلیت به هزاران دنیا', 'koohe-film' ),
		'icon'  => '<path d="m4 4 16 0M3 8h18M5 4l2 4M10 4l2 4M15 4l2 4M4 8v12h16V8"/>',
	),
	array(
		'url'   => get_post_type_archive_link( 'series' ),
		'title' => esc_attr__( 'دنیای سریال‌ها', 'koohe-film' ),
		'hint'  => esc_attr__( 'فقط یک قسمت دیگر…', 'koohe-film' ),
		'icon'  => '<rect width="20" height="15" x="2" y="4" rx="2"/><path d="M8 21h8M12 17v4"/>',
	),
	array(
		'url'   => get_post_type_archive_link( 'anime' ),
		'title' => esc_attr__( 'جهان انیمیشن', 'koohe-film' ),
		'hint'  => esc_attr__( 'خیال، بدون مرز', 'koohe-film' ),
		'icon'  => '<path d="m12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z"/>',
	),
	array(
		'url'   => get_post_type_archive_link( 'person' ),
		'title' => esc_attr__( 'بازیگران و کارگردان‌ها', 'koohe-film' ),
		'hint'  => esc_attr__( 'چهره‌های ماندگار', 'koohe-film' ),
		'icon'  => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
	),
);
?>
<!-- wp:html -->
<div class="koohe-shortcuts">
	<?php foreach ( $links as $link ) : ?>
		<?php
		if ( empty( $link['url'] ) ) {
			continue;
		}
		?>
		<a href="<?php echo esc_url( $link['url'] ); ?>">
			<span class="koohe-shortcuts__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $link['icon']; // phpcs:ignore WordPress.Security.EscapeOutput — SVG ثابت داخلی ?></svg>
			</span>
			<span class="koohe-shortcuts__text">
				<strong><?php echo esc_html( $link['title'] ); ?></strong>
				<small><?php echo esc_html( $link['hint'] ); ?></small>
			</span>
			<svg class="koohe-shortcuts__arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
		</a>
	<?php endforeach; ?>
</div>
<!-- /wp:html -->

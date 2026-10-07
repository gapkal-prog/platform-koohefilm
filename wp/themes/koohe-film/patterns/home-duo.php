<?php
/**
 * Title: هر روز، یک قسمت تازه (دو ستون)
 * Slug: koohe-film/home-duo
 * Categories: koohe, koohe-listing, query
 * Keywords: برنامه, هفتگی, سلیقه, home-duo
 * Description: دو ستون مرجع: برنامه‌ی هفتگی قسمت‌ها («هر روز، یک قسمت تازه») و بنر «سلیقه‌ات را کشف کن.».
 * Viewport Width: 1400
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"wide","className":"koohe-home-duo","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide koohe-home-duo">
	<?php
	/*
	 * پنل «هر روز، یک قسمت تازه» — همان `.schedule-panel` صفحه‌ی نخست مرجع.
	 *
	 * سه چیز این‌جا عیناً با مرجع هم‌راستا شده است:
	 *   • منبع داده «آثار زمان‌بندی‌شده» است (مرجع سریال هر روز را نشان
	 *     می‌دهد، نه قسمت‌ها) و روزهای بی‌برنامه همان `.schedule-empty`
	 *     مرجع را می‌گیرند.
	 *   • پیوند «برنامه کامل» به **برگه‌ی برنامه پخش** می‌رود
	 *     (`schedule.html` مرجع)، نه به آرشیو سریال‌ها.
	 *   • یادداشت پایین پنل روشن است (مرجع هم دارد).
	 */
	$koohe_schedule = array(
		'layout'            => 'block',
		'mode'              => 'series',
		'postTypes'         => array( 'series' ),
		'perDay'            => 4,
		'activeDay'         => 'today',
		'heading'           => __( 'هر روز، یک قسمت تازه', 'koohe-film' ),
		'subheading'        => __( 'برنامه هفتگی سریال‌ها', 'koohe-film' ),
		'headingIcon'       => 'calendar',
		'showTime'          => true,
		'showThumb'         => true,
		'showOriginalTitle' => true,
		'showSeasonMeta'    => true,
		'showPlay'          => true,
		'showFootnote'      => true,
		'showMore'          => true,
		'moreLabel'         => __( 'برنامه کامل', 'koohe-film' ),
		'moreUrl'           => home_url( '/schedule/' ),
		'emptyMessage'      => __( 'امروز، وقت کشف یک داستان تازه است.', 'koohe-film' ),
		'emptyLinkLabel'    => __( 'پیشنهادهای ما', 'koohe-film' ),
	);
	?>
	<!-- wp:manacore/schedule <?php echo wp_json_encode( $koohe_schedule ); ?> /-->

	<!-- wp:manacore/taste-banner {"eyebrow":"<?php echo esc_attr__( 'به اندازه خودت، متفاوت', 'koohe-film' ); ?>","heading":"<?php echo esc_attr__( 'سلیقه‌ات را کشف کن.', 'koohe-film' ); ?>","text":"<?php echo esc_attr__( 'از داستان‌هایی که دوست داری، به داستان‌هایی که عاشقشان می‌شوی.', 'koohe-film' ); ?>","linkLabel":"<?php echo esc_attr__( 'دنیای مخصوص من', 'koohe-film' ); ?>","linkUrl":"<?php echo esc_url( home_url( '/movie/' ) ); ?>","badge":"MADE FOR YOU","showArt":true} /-->
</div>
<!-- /wp:group -->

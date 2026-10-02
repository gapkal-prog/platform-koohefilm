<?php
/**
 * توابع کمکی نمایشی قالب.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * آیا افزونه‌ی هسته‌ی ManaCore فعال است؟
 *
 * @return bool
 */
function koohe_has_core() {
	return function_exists( 'manacore_get_option' ) && function_exists( 'manacore_post_types' );
}

/**
 * آیا افزونه‌ی اشتراک فعال است؟
 *
 * @return bool
 */
function koohe_has_subs() {
	return function_exists( 'manacore_subs_user_level' );
}

/**
 * آیا ووکامرس فعال است؟
 *
 * @return bool
 */
function koohe_has_woo() {
	return class_exists( 'WooCommerce' );
}

/**
 * دکمه‌ی تغییر حالت تیره/روشن.
 *
 * @param array $args آرگومان‌ها.
 * @return string
 */
function koohe_theme_toggle( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'class' => '',
			'label' => __( 'تغییر حالت رنگ', 'koohe-film' ),
		)
	);

	$classes = trim( 'koohe-toggle ' . $args['class'] );

	ob_start();
	?>
	<button
		type="button"
		class="<?php echo esc_attr( $classes ); ?>"
		data-manacore-theme-toggle
		aria-pressed="false"
		aria-label="<?php echo esc_attr( $args['label'] ); ?>"
		title="<?php echo esc_attr( $args['label'] ); ?>"
	>
		<span class="koohe-toggle-track" aria-hidden="true">
			<span class="koohe-toggle-thumb">
				<svg class="koohe-icon-sun" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
					<circle cx="12" cy="12" r="4"></circle>
					<path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
				</svg>
				<svg class="koohe-icon-moon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
				</svg>
			</span>
		</span>
	</button>
	<?php
	return (string) ob_get_clean();
}

/**
 * شورت‌کد دکمه‌ی تغییر حالت رنگ: [koohe_theme_toggle]
 *
 * @param array $atts پارامترها.
 * @return string
 */
function koohe_theme_toggle_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'class' => '',
			'label' => __( 'تغییر حالت رنگ', 'koohe-film' ),
		),
		$atts,
		'koohe_theme_toggle'
	);

	return koohe_theme_toggle( $atts );
}
add_shortcode( 'koohe_theme_toggle', 'koohe_theme_toggle_shortcode' );

/**
 * رندر بلوک کلید حالت رنگ.
 *
 * @param array $attrs ویژگی‌ها.
 * @return string
 */
function koohe_render_toggle_block( $attrs = array() ) {
	$label   = ! empty( $attrs['label'] ) ? $attrs['label'] : __( 'تغییر حالت رنگ', 'koohe-film' );
	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'koohe-toggle-wrap' ) )
		: 'class="koohe-toggle-wrap"';

	return '<div ' . $wrapper . '>' . koohe_theme_toggle( array( 'label' => $label ) ) . '</div>';
}

/**
 * فهرست دسته‌بندی بلوک در صورت نبود افزونه‌ی هسته.
 *
 * @param array $categories دسته‌ها.
 * @return array
 */
function koohe_block_category( $categories ) {
	foreach ( $categories as $category ) {
		if ( isset( $category['slug'] ) && 'manacore' === $category['slug'] ) {
			return $categories;
		}
	}

	array_unshift(
		$categories,
		array(
			'slug'  => 'manacore',
			'title' => __( 'ManaCore — فیلم و سریال', 'koohe-film' ),
			'icon'  => 'video-alt2',
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'koohe_block_category', 5 );

/**
 * نشان اشتراک کاربر در سربرگ.
 *
 * @return string
 */
function koohe_account_badge() {
	if ( ! is_user_logged_in() ) {
		return '<a class="koohe-btn koohe-btn-ghost" href="' . esc_url( wp_login_url( home_url( add_query_arg( array() ) ) ) ) . '">'
			. esc_html__( 'ورود', 'koohe-film' ) . '</a>';
	}

	$user  = wp_get_current_user();
	$url   = koohe_has_woo() && function_exists( 'wc_get_page_permalink' )
		? wc_get_page_permalink( 'myaccount' )
		: admin_url( 'profile.php' );
	$level = '';

	if ( koohe_has_subs() ) {
		$slug = manacore_subs_user_level();
		if ( $slug && function_exists( 'manacore_subs_levels' ) ) {
			$levels = manacore_subs_levels();
			$level  = isset( $levels[ $slug ]['label'] ) ? $levels[ $slug ]['label'] : $slug;
		}
	}

	$out  = '<a class="koohe-account" href="' . esc_url( $url ) . '">';
	$out .= get_avatar( $user->ID, 28, '', esc_attr( $user->display_name ), array( 'class' => 'koohe-account-avatar' ) );
	$out .= '<span class="koohe-account-name">' . esc_html( $user->display_name ) . '</span>';
	if ( $level ) {
		$out .= '<span class="koohe-account-level">' . esc_html( $level ) . '</span>';
	}
	$out .= '</a>';

	return $out;
}

/**
 * شورت‌کد نشان حساب کاربری: [koohe_account]
 *
 * @return string
 */
function koohe_account_shortcode() {
	return koohe_account_badge();
}
add_shortcode( 'koohe_account', 'koohe_account_shortcode' );

/**
 * رندر بلوک حساب کاربری.
 *
 * @return string
 */
function koohe_render_account_block() {
	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'koohe-account-wrap' ) )
		: 'class="koohe-account-wrap"';

	return '<div ' . $wrapper . '>' . koohe_account_badge() . '</div>';
}

/**
 * دریافت آدرس پوستر با پشتیبانی از افزونه‌ی هسته.
 *
 * @param int    $post_id شناسه‌ی نوشته.
 * @param string $size    اندازه.
 * @return string
 */
function koohe_poster( $post_id = 0, $size = 'koohe-poster' ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();

	if ( function_exists( 'manacore_poster_url' ) ) {
		$url = manacore_poster_url( $post_id, $size );
		if ( $url ) {
			return $url;
		}
	}

	$thumb = get_the_post_thumbnail_url( $post_id, $size );
	if ( $thumb ) {
		return $thumb;
	}

	return KOOHE_URI . 'assets/img/poster-placeholder.svg';
}

/**
 * رندر بلوک حق نشر.
 *
 * @return string
 */
function koohe_render_copyright_block() {
	if ( ! function_exists( 'koohe_copyright_text' ) ) {
		return '';
	}

	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'koohe-copyright' ) )
		: 'class="koohe-copyright"';

	$html = '<p ' . $wrapper . '>' . koohe_copyright_text() . '</p>';

	if ( function_exists( 'koohe_get_flag' ) && koohe_get_flag( 'koohe_footer_credit' ) ) {
		$html .= '<p class="koohe-credit">' . sprintf(
			/* translators: %s پیوند سازنده. */
			esc_html__( 'طراحی و توسعه: %s', 'koohe-film' ),
			'<a href="https://manacore.dev" rel="nofollow noopener" target="_blank">ManaCore</a>'
		) . '</p>';
	}

	return $html;
}

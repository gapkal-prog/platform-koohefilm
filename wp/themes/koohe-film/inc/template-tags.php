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
 * نشانی «حساب کاربری» — ورود برای مهمان، حساب کاربری برای عضو.
 *
 * یک منبع حقیقت برای دکمه‌ی سربرگ و ردیف پایانی کشوی موبایل؛ پیش‌تر همین
 * منطق دو جا تکرار می‌شد.
 *
 * @return string
 */
function koohe_account_url( $args = array() ) {
	/*
	 * برگه‌ی «حساب کاربری» خودِ محصول (قالب `page-account`) بر هر مقصد
	 * دیگری مقدم است — همان برگه‌ای که هم‌ارز `account.html` مرجع است.
	 * `$args` پرسمان‌های افزودنی است (مثلاً برگه‌ی تب: `array('tab'=>'watchlist')`)
	 * و همین چیزی است که نشانه‌های `account:<tab>` را به کار می‌اندازد.
	 */
	if ( function_exists( 'manacore_account_page_id' ) ) {
		$page_id = manacore_account_page_id();

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$url = (string) get_permalink( $page_id );

			if ( ! empty( $args ) ) {
				$url = (string) add_query_arg( array_map( 'sanitize_text_field', (array) $args ), $url );
			}

			return $url;
		}
	}

	if ( ! is_user_logged_in() ) {
		return wp_login_url( home_url( add_query_arg( array() ) ) );
	}

	if ( koohe_has_woo() && function_exists( 'wc_get_page_permalink' ) ) {
		$url = wc_get_page_permalink( 'myaccount' );

		if ( $url ) {
			return (string) $url;
		}
	}

	return admin_url( 'profile.php' );
}

/**
 * نشان اشتراک کاربر در سربرگ.
 *
 * @return string
 */
function koohe_account_badge() {
	if ( ! is_user_logged_in() ) {
		/*
		 * در موبایل این کنترل به آیکن تبدیل می‌شود و متنش پنهان می‌ماند
		 * (همان الگوی طرح مرجع). آیکن از ابتدا در مارک‌آپ هست و با CSS
		 * نمایش داده می‌شود تا در ۳۹۰px حدود ۲۰px از سربرگ آزاد شود؛
		 * بدون آن، جمع پهنای سربرگ از عرض موجود بیشتر می‌شد و دکمه‌ی
		 * منوی موبایل له می‌شد. برای صفحه‌خوان‌ها نام قابل‌دسترس باقی
		 * می‌ماند و متن «ورود» در دسکتاپ دست‌نخورده است.
		 */
		return '<a class="koohe-btn koohe-btn-ghost koohe-account-login" href="' . esc_url( koohe_account_url() ) . '">'
			. '<svg class="koohe-account-login__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
			. '<span class="koohe-account-login__text">' . esc_html__( 'ورود', 'koohe-film' ) . '</span></a>';
	}

	$user  = wp_get_current_user();
	$url   = koohe_account_url();
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

/*
 * ---------------------------------------------------------------------------
 * بلوک‌های کنش اثر (لیست تماشا و کپی پیوند)
 * ---------------------------------------------------------------------------
 */

/**
 * ساخت کلاس دکمه بر پایه‌ی سبک انتخابی.
 *
 * @param string $style سبک (glass | primary | ghost).
 * @return string
 */
/**
 * کلاس دکمه‌ی استاندارد قالب.
 *
 * بلوک‌های افزونه‌ای که کنششان باید به زبان طراحی قالب باشد (مثل دکمه‌ی
 * «پخش تریلر») از این تابع کلاس می‌گیرند تا هرگز کلاس‌های قالب به‌صورت
 * رشته‌ی ثابت داخل افزونه تکرار نشوند؛ اگر قالب فعال نباشد، افزونه کلاس
 * خودش را می‌گذارد.
 *
 * @param string $style سبک: glass، primary، ghost، soft.
 * @param string $size  اندازه: خالی، small.
 * @return string
 */
function koohe_btn_class( $style = 'glass', $size = '' ) {
	$styles  = array( 'glass', 'primary', 'ghost', 'soft' );
	$style   = in_array( $style, $styles, true ) ? $style : 'glass';
	$classes = 'koohe-btn koohe-btn--' . $style;

	if ( $size ) {
		$classes .= ' koohe-btn--' . sanitize_html_class( $size );
	}

	return $classes;
}

function koohe_action_button_class( $style ) {
	$allowed = array( 'glass', 'primary', 'ghost' );
	$style   = in_array( $style, $allowed, true ) ? $style : 'glass';

	return 'koohe-btn koohe-btn--small koohe-btn--' . $style . ' koohe-title-action';
}

/**
 * آیکن قلب (سبک خطی، هم‌راستا با بقیه‌ی آیکن‌های قالب).
 *
 * @return string
 */
function koohe_icon_heart() {
	return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>';
}

/**
 * آیکن پیوند.
 *
 * @return string
 */
function koohe_icon_link() {
	return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>';
}

/**
 * رندر بلوک «افزودن به لیست تماشا».
 *
 * در ویرایشگر زمینه‌ی پست وجود ندارد؛ آنجا دکمه غیرفعال و توضیحی رندر
 * می‌شود تا مدیر پیش‌نمایش درستی ببیند و کد با شناسه‌ی صفر ساخته نشود.
 *
 * @param array $attrs ویژگی‌ها.
 * @return string
 */
function koohe_render_watchlist_block( $attrs = array() ) {
	$attrs = is_array( $attrs ) ? $attrs : array();

	$label   = ! empty( $attrs['label'] ) ? $attrs['label'] : __( 'لیست تماشا', 'koohe-film' );
	$saved   = ! empty( $attrs['labelSaved'] ) ? $attrs['labelSaved'] : __( 'در لیست تماشا', 'koohe-film' );
	$style   = isset( $attrs['style'] ) ? $attrs['style'] : 'glass';
	$icon    = ! isset( $attrs['showIcon'] ) || $attrs['showIcon'];
	$post_id = koohe_block_target_id();
	$classes = koohe_action_button_class( $style );

	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'koohe-title-watchlist-wrap' ) )
		: 'class="koohe-title-watchlist-wrap"';

	if ( ! $post_id ) {
		return '<div ' . $wrapper . '><button type="button" class="' . esc_attr( $classes ) . '" disabled="disabled">'
			. ( $icon ? koohe_icon_heart() : '' )
			. '<span>' . esc_html( $label ) . '</span></button></div>';
	}

	$is_saved = function_exists( 'manacore_is_in_watchlist' ) && manacore_is_in_watchlist( $post_id );

	return '<div ' . $wrapper . '><button type="button" class="' . esc_attr( $classes ) . '"'
		. ' data-manacore-watchlist="' . esc_attr( $post_id ) . '"'
		. ' data-label-add="' . esc_attr( $label ) . '"'
		. ' data-label-saved="' . esc_attr( $saved ) . '"'
		. ' aria-pressed="' . ( $is_saved ? 'true' : 'false' ) . '">'
		. ( $icon ? koohe_icon_heart() : '' )
		. '<span>' . esc_html( $is_saved ? $saved : $label ) . '</span></button></div>';
}

/**
 * رندر بلوک «کپی پیوند».
 *
 * @param array $attrs ویژگی‌ها.
 * @return string
 */
function koohe_render_copy_link_block( $attrs = array() ) {
	$attrs = is_array( $attrs ) ? $attrs : array();

	$label   = ! empty( $attrs['label'] ) ? $attrs['label'] : __( 'کپی لینک', 'koohe-film' );
	$style   = isset( $attrs['style'] ) ? $attrs['style'] : 'glass';
	$icon    = ! isset( $attrs['showIcon'] ) || $attrs['showIcon'];
	$post_id = koohe_block_target_id();
	$classes = koohe_action_button_class( $style );

	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => 'koohe-title-copy-wrap' ) )
		: 'class="koohe-title-copy-wrap"';

	$url = $post_id ? get_permalink( $post_id ) : '';

	if ( ! $url ) {
		return '<div ' . $wrapper . '><button type="button" class="' . esc_attr( $classes ) . '" disabled="disabled">'
			. ( $icon ? koohe_icon_link() : '' )
			. '<span>' . esc_html( $label ) . '</span></button></div>';
	}

	return '<div ' . $wrapper . '><button type="button" class="' . esc_attr( $classes ) . '"'
		. ' data-manacore-copy="' . esc_url( $url ) . '"'
		. ' aria-label="' . esc_attr( $label ) . '">'
		. ( $icon ? koohe_icon_link() : '' )
		. '<span>' . esc_html( $label ) . '</span></button></div>';
}

/**
 * شناسه‌ی اثری که بلوک کنش باید به آن اشاره کند.
 *
 * در ویرایشگر قالب/الگو زمینه‌ی پست وجود ندارد؛ اگر بلوک در حالت
 * پیش‌نمایش باشد شناسه صفر برمی‌گردد تا دکمه غیرفعال رندر شود.
 *
 * @return int
 */
function koohe_block_target_id() {
	if ( function_exists( 'is_admin' ) && is_admin() && ! wp_doing_ajax() ) {
		return 0;
	}

	return (int) get_the_ID();
}

/* ---------------------------------------------------------------------------
 * دسترس‌پذیری فرم دیدگاه
 * ------------------------------------------------------------------------- */

/**
 * سرعنوان فرم دیدگاه از h3 به h2.
 *
 * هسته به‌طور پیش‌فرض `<h3 id="reply-title">` می‌سازد («دیدگاهی بنویسید»).
 * در برگه‌های تک‌اثر، سرعنوان اصلی برگه h1 است و بعد از آن مستقیماً h3
 * می‌آید؛ axe-core این را نقض `heading-order` می‌بیند. این فرم زیربخشِ
 * همان برگه است، پس h2 سطح درست است.
 *
 * @param array $defaults پیش‌فرض‌های فرم دیدگاه.
 * @return array
 */
function koohe_comment_form_heading_level( $defaults ) {
	$defaults['title_reply_before'] = '<h2 id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']  = '</h2>';

	return $defaults;
}
add_filter( 'comment_form_defaults', 'koohe_comment_form_heading_level' );

/**
 * برچسب فارسی نوع محتوای یک اثر (فیلم/سریال/انیمه/قسمت).
 *
 * منبع حقیقت همان `manacore_post_types()` افزونه‌ی هسته است تا برچسب‌ها
 * یک‌جا و یکدست بمانند؛ اگر نوعی در آن فهرست نبود، از برچسب تکینِ خودِ
 * نوع پست استفاده می‌شود.
 *
 * @param int $post_id شناسه‌ی اثر (۰ = اثر جاری).
 * @return string برچسب یا رشته‌ی خالی.
 */
function koohe_post_type_label( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : koohe_block_target_id();
	if ( ! $post_id ) {
		return '';
	}

	$type = get_post_type( $post_id );
	if ( ! $type ) {
		return '';
	}

	$types = function_exists( 'manacore_post_types' ) ? manacore_post_types() : array();
	if ( isset( $types[ $type ] ) ) {
		return (string) $types[ $type ];
	}

	$object = get_post_type_object( $type );

	return $object && isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : '';
}

/**
 * نشانِ نوع محتوا (بلوک `koohe/post-type`).
 *
 * همان چیپِ تأکیدی سربرگِ جزئیات در الگوی مرجع: پس‌زمینه‌ی بسیار کم‌رنگ
 * از رنگ تأکید، قاب نازک و متن کوچک. اگر برچسب خالی باشد (مثل بوم
 * ویرایشگر که اثری در جریان نیست) چیزی رندر نمی‌شود تا نشانِ بی‌معنا
 * دیده نشود؛ برچسب را می‌توان از ویرایشگر هم دستی تعیین کرد.
 *
 * @param array $attrs ویژگی‌های بلوک.
 * @return string
 */
function koohe_render_post_type_block( $attrs = array() ) {
	$attrs = is_array( $attrs ) ? $attrs : array();

	$style = isset( $attrs['style'] ) && in_array( $attrs['style'], array( 'accent', 'soft' ), true )
		? (string) $attrs['style']
		: 'accent';

	$label = ! empty( $attrs['label'] ) ? (string) $attrs['label'] : koohe_post_type_label();

	if ( '' === trim( $label ) ) {
		return '';
	}

	$classes = 'accent' === $style ? 'koohe-badge koohe-badge--accent' : 'koohe-badge koohe-badge--soft';
	$wrapper = function_exists( 'get_block_wrapper_attributes' )
		? get_block_wrapper_attributes( array( 'class' => $classes ) )
		: 'class="' . esc_attr( $classes ) . '"';

	$post_id = koohe_block_target_id();
	$url     = ! empty( $attrs['linkToArchive'] ) && $post_id ? get_post_type_archive_link( get_post_type( $post_id ) ) : '';

	if ( $url ) {
		return '<span ' . $wrapper . '><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></span>';
	}

	return '<span ' . $wrapper . '>' . esc_html( $label ) . '</span>';
}

/**
 * دکمه‌ی جستجوی سربرگ (بازکننده‌ی پوسته‌ی تمام‌صفحه).
 *
 * الگوی دقیق مرجع: در دسکتاپ یک پیل با آیکن ذره‌بین + متن راهنما + میان‌بر
 * ⌘K، و در موبایل فقط آیکن. روی کلیک، پوسته‌ی جستجو (`data-koohe-search-overlay`)
 * باز می‌شود؛ همان مسیری که میان‌بر ⌘K/Ctrl+K هم استفاده می‌کند. متن
 * راهنما و نمایش میان‌بر از ویرایشگر قابل تنظیم‌اند.
 *
 * @param array $attributes ویژگی‌های بلوک.
 * @return string
 */
function koohe_render_search_trigger_block( $attributes = array() ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$label = isset( $attributes['placeholder'] ) && '' !== trim( (string) $attributes['placeholder'] )
		? (string) $attributes['placeholder']
		: __( 'دنبال چی می‌گردی؟', 'koohe-film' );

	$show_hint = ! isset( $attributes['showHint'] ) || ! empty( $attributes['showHint'] );

	$anchor = isset( $attributes['anchor'] ) && '' !== $attributes['anchor']
		? ' id="' . esc_attr( (string) $attributes['anchor'] ) . '"'
		: '';

	ob_start();
	?>
	<button type="button" class="koohe-search-trigger"<?php echo $anchor; /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
		data-koohe-search-open
		aria-haspopup="dialog"
		aria-expanded="false"
		aria-controls="koohe-search-overlay">
		<svg class="koohe-search-trigger__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
		<span class="koohe-search-trigger__label"><?php echo esc_html( $label ); ?></span>
		<?php if ( $show_hint ) : ?>
			<kbd class="koohe-search-trigger__kbd" aria-hidden="true">⌘ K</kbd>
		<?php endif; ?>
	</button>
	<?php
	return (string) ob_get_clean();
}

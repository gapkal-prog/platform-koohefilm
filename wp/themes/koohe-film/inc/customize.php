<?php
/**
 * تنظیمات سبک قالب در سفارشی‌ساز (برای گزینه‌هایی که در theme.json جای نمی‌گیرند).
 *
 * ساختار: یک پنل «کوه فیلم» با پنج بخش (سربرگ، رنگ، آرشیو، خوانش، پابرگ).
 *
 * نکته‌ی مهم درباره‌ی چک‌باکس‌ها:
 * مقدارها به‌صورت رشته‌ی '1' یا '' ذخیره می‌شوند، نه بولی PHP. دلیل:
 * سفارشی‌ساز مقدار را از prop('checked') می‌خواند، ولی قالب کنترل هسته
 * ویژگی value="<?php echo esc_attr( $this->value() ); ?>" را نیز چاپ می‌کند.
 * ذخیره‌ی رشته هر دو مسیر (جاوااسکریپت سفارشی‌ساز و ارسال فرم) را
 * قابل‌اعتماد می‌کند و از تبدیل‌های ناخواسته‌ی (bool) جلوگیری می‌نماید.
 * خواندن همیشه از koohe_get_flag() انجام می‌شود.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * پیش‌فرض گزینه‌های بولی قالب — تنها منبع حقیقت.
 *
 * @return array<string,bool> نام گزینه => پیش‌فرض.
 */
function koohe_boolean_defaults() {
	return array(
		'koohe_sticky_header'    => true,
		'koohe_shrink_header'    => true,
		'koohe_show_toggle'      => true,
		'koohe_card_hover_zoom'  => true,
		'koohe_rounded_media'    => true,
		'koohe_reading_progress' => false,
		'koohe_back_to_top'      => true,
		'koohe_mobile_actionbar' => true,
		'koohe_footer_credit'    => true,
	);
}

/**
 * خواندن گزینه‌ی بولی قالب به‌صورت ایمن.
 *
 * @param string    $key     نام گزینه (بدون پیشوند اضافه).
 * @param bool|null $default پیش‌فرض؛ در صورت null از koohe_boolean_defaults() خوانده می‌شود.
 * @return bool
 */
function koohe_get_flag( $key, $default = null ) {
	if ( null === $default ) {
		$defaults = koohe_boolean_defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : false;
	}

	return wp_validate_boolean( get_theme_mod( $key, $default ? '1' : '' ) );
}

/**
 * ثبت تنظیمات.
 *
 * @param WP_Customize_Manager $wp_customize مدیر سفارشی‌ساز.
 */
function koohe_customize_register( $wp_customize ) {
	$defaults = koohe_boolean_defaults();

	/* پیش‌نمایش زنده برای عنوان و توضیح سایت. */
	foreach ( array( 'blogname', 'blogdescription' ) as $core_setting ) {
		$setting = $wp_customize->get_setting( $core_setting );

		if ( $setting ) {
			$setting->transport = 'postMessage';
		}
	}

	/* ---------------------------------------------------------------------
	 * پنل و بخش‌ها
	 * ------------------------------------------------------------------ */

	$wp_customize->add_panel(
		'koohe_panel',
		array(
			'title'       => __( 'کوه فیلم', 'koohe-film' ),
			'description' => __( 'گزینه‌های نمایشی قالب کوه فیلم (طراحی: ManaCore). رنگ‌ها و تایپوگرافی در «ویرایشگر سایت» تنظیم می‌شوند.', 'koohe-film' ),
			'priority'    => 20,
		)
	);

	$sections = array(
		'koohe_header'  => array(
			'title'       => __( 'سربرگ', 'koohe-film' ),
			'description' => __( 'رفتار نوار بالای سایت.', 'koohe-film' ),
			'priority'    => 10,
		),
		'koohe_colors'  => array(
			'title'       => __( 'رنگ و حالت', 'koohe-film' ),
			'description' => __( 'حالت پیش‌فرض تیره یا روشن و رنگ تأکید سایت.', 'koohe-film' ),
			'priority'    => 20,
		),
		'koohe_layout'  => array(
			'title'       => __( 'چیدمان و گوشه‌ها', 'koohe-film' ),
			'description' => __( 'عرض محتوای چیدمان و شعاع گوشه‌ی اجزا.', 'koohe-film' ),
			'priority'    => 25,
		),
		'koohe_archive' => array(
			'title'       => __( 'آرشیو و کارت‌ها', 'koohe-film' ),
			'description' => __( 'نمایش پوستر و افکت کارت‌های فهرست آثار.', 'koohe-film' ),
			'priority'    => 30,
		),
		'koohe_reading' => array(
			'title'       => __( 'خوانش و پیمایش', 'koohe-film' ),
			'description' => __( 'کمک‌ابزارهای پیمایش صفحه.', 'koohe-film' ),
			'priority'    => 40,
		),
		'koohe_footer'  => array(
			'title'       => __( 'پابرگ', 'koohe-film' ),
			'description' => __( 'متن حق نشر و اعتبار طراح.', 'koohe-film' ),
			'priority'    => 50,
		),
	);

	foreach ( $sections as $id => $args ) {
		$wp_customize->add_section( $id, array_merge( $args, array( 'panel' => 'koohe_panel' ) ) );
	}

	/* ---------------------------------------------------------------------
	 * ۱. سربرگ
	 * ------------------------------------------------------------------ */

	koohe_add_checkbox(
		$wp_customize,
		'koohe_sticky_header',
		$defaults['koohe_sticky_header'],
		'koohe_header',
		__( 'سربرگ چسبان', 'koohe-film' ),
		__( 'سربرگ هنگام پیمایش در بالای صفحه ثابت می‌ماند.', 'koohe-film' )
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_shrink_header',
		$defaults['koohe_shrink_header'],
		'koohe_header',
		__( 'کوچک‌شدن سربرگ هنگام پیمایش', 'koohe-film' ),
		__( 'تنها زمانی اثر دارد که «سربرگ چسبان» فعال باشد.', 'koohe-film' )
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_show_toggle',
		$defaults['koohe_show_toggle'],
		'koohe_header',
		__( 'نمایش کلید تیره/روشن', 'koohe-film' ),
		__( 'با خاموش‌کردن، کلید تغییر حالت رنگ از سربرگ حذف می‌شود.', 'koohe-film' )
	);

	/* ---------------------------------------------------------------------
	 * ۲. حالت رنگ
	 * ------------------------------------------------------------------ */

	$wp_customize->add_setting(
		'koohe_color_mode',
		array(
			'default'           => 'dark',
			'transport'         => 'refresh',
			'sanitize_callback' => 'koohe_sanitize_color_mode',
		)
	);
	$wp_customize->add_control(
		'koohe_color_mode',
		array(
			'label'       => __( 'حالت رنگ پیش‌فرض', 'koohe-film' ),
			'description' => __( 'اگر افزونه‌ی ManaCore فعال باشد، تنظیم آن اولویت دارد. انتخاب کاربر در مرورگر خودش ذخیره می‌شود.', 'koohe-film' ),
			'section'     => 'koohe_colors',
			'type'        => 'select',
			'choices'     => array(
				'dark'  => __( 'تیره', 'koohe-film' ),
				'light' => __( 'روشن', 'koohe-film' ),
				'auto'  => __( 'بر پایه‌ی تنظیم سیستم', 'koohe-film' ),
			),
		)
	);

	/*
	 * رنگ تأکید: متغیرهای --wp--preset--color--accent و
	 * --wp--preset--color--accent-contrast theme.json بازنویسی می‌شوند
	 * (بخش koohe_appearance_css در inc/assets.php). خالی = مقدار پیش‌فرض theme.json.
	 */
	$wp_customize->add_setting(
		'koohe_accent_color',
		array(
			'default'           => '',
			'transport'         => 'refresh',
			'sanitize_callback' => 'koohe_sanitize_hex_color_or_empty',
		)
	);
	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'koohe_accent_color',
			array(
				'label'       => __( 'رنگ تأکید سایت', 'koohe-film' ),
				'description' => __( 'رنگ دکمه‌ها، لینک‌ها و نشانه‌های اصلی. خالی بگذارید تا رنگ پیش‌فرض قالب (theme.json) به کار رود.', 'koohe-film' ),
				'section'     => 'koohe_colors',
			)
		)
	);

	/* ---------------------------------------------------------------------
	 * ۲.۵ چیدمان و گوشه‌ها
	 * ------------------------------------------------------------------ */

	$wp_customize->add_setting(
		'koohe_layout_width',
		array(
			'default'           => 'standard',
			'transport'         => 'refresh',
			'sanitize_callback' => 'koohe_sanitize_layout_width',
		)
	);
	$wp_customize->add_control(
		'koohe_layout_width',
		array(
			'label'       => __( 'عرض چیدمان', 'koohe-film' ),
			'description' => __( 'حداکثر پهنای محتوای متنی و فهرست‌ها.', 'koohe-film' ),
			'section'     => 'koohe_layout',
			'type'        => 'select',
			'choices'     => koohe_layout_width_choices(),
		)
	);

	$wp_customize->add_setting(
		'koohe_radius_scale',
		array(
			'default'           => 'standard',
			'transport'         => 'refresh',
			'sanitize_callback' => 'koohe_sanitize_radius_scale',
		)
	);
	$wp_customize->add_control(
		'koohe_radius_scale',
		array(
			'label'       => __( 'شعاع گوشه‌ها', 'koohe-film' ),
			'description' => __( 'گردی گوشه‌ی کارت‌ها، دکمه‌ها و تصاویر.', 'koohe-film' ),
			'section'     => 'koohe_layout',
			'type'        => 'select',
			'choices'     => koohe_radius_scale_choices(),
		)
	);

	/* ---------------------------------------------------------------------
	 * ۳. آرشیو و کارت‌ها
	 * ------------------------------------------------------------------ */

	$wp_customize->add_setting(
		'koohe_poster_ratio',
		array(
			'default'           => '2/3',
			'transport'         => 'postMessage',
			'sanitize_callback' => 'koohe_sanitize_poster_ratio',
		)
	);
	$wp_customize->add_control(
		'koohe_poster_ratio',
		array(
			'label'       => __( 'نسبت پوستر', 'koohe-film' ),
			'description' => __( 'نسبت ابعاد تصویر شاخص در کارت‌های آرشیو.', 'koohe-film' ),
			'section'     => 'koohe_archive',
			'type'        => 'select',
			'choices'     => koohe_poster_ratio_choices(),
		)
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_card_hover_zoom',
		$defaults['koohe_card_hover_zoom'],
		'koohe_archive',
		__( 'بزرگ‌نمایی پوستر با نشانگر', 'koohe-film' ),
		__( 'در حالت «کاهش حرکت» مرورگر به‌طور خودکار غیرفعال می‌شود.', 'koohe-film' )
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_rounded_media',
		$defaults['koohe_rounded_media'],
		'koohe_archive',
		__( 'گوشه‌های گرد برای تصاویر', 'koohe-film' )
	);

	/* ---------------------------------------------------------------------
	 * ۴. خوانش و پیمایش
	 * ------------------------------------------------------------------ */

	koohe_add_checkbox(
		$wp_customize,
		'koohe_back_to_top',
		$defaults['koohe_back_to_top'],
		'koohe_reading',
		__( 'نمایش دکمه‌ی بازگشت به بالا', 'koohe-film' )
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_mobile_actionbar',
		$defaults['koohe_mobile_actionbar'],
		'koohe_reading',
		__( 'نوار کنش پایین صفحه در موبایل', 'koohe-film' ),
		__( 'در صفحه‌ی اثر، دکمه‌های دانلود و تریلر را در دسترس شست نگه می‌دارد.', 'koohe-film' )
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_reading_progress',
		$defaults['koohe_reading_progress'],
		'koohe_reading',
		__( 'نوار پیشرفت خوانش', 'koohe-film' ),
		__( 'تنها در برگه‌ها و نوشته‌های تک‌صفحه‌ای نمایش داده می‌شود.', 'koohe-film' )
	);

	/* ---------------------------------------------------------------------
	 * ۵. پابرگ
	 * ------------------------------------------------------------------ */

	$wp_customize->add_setting(
		'koohe_copyright',
		array(
			'default'           => '',
			'transport'         => 'postMessage',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'koohe_copyright',
		array(
			'label'       => __( 'متن حق نشر پابرگ', 'koohe-film' ),
			'description' => __( 'شناسه‌های {year} و {site} جایگزین می‌شوند. خالی بگذارید تا متن پیش‌فرض به کار رود. برای نمایش، بلوک «حق نشر (کوه فیلم)» را در پابرگ قرار دهید.', 'koohe-film' ),
			'section'     => 'koohe_footer',
			'type'        => 'textarea',
		)
	);

	koohe_add_checkbox(
		$wp_customize,
		'koohe_footer_credit',
		$defaults['koohe_footer_credit'],
		'koohe_footer',
		__( 'نمایش اعتبار طراح (ManaCore)', 'koohe-film' )
	);

	/* پیش‌نمایش گزینشی متن حق نشر. */
	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'koohe_copyright',
			array(
				'selector'            => '.koohe-copyright',
				'container_inclusive' => false,
				'render_callback'     => 'koohe_copyright_text',
				'fallback_refresh'    => false,
			)
		);
	}
}
add_action( 'customize_register', 'koohe_customize_register' );

/**
 * افزودن یک چک‌باکس با تنظیم و کنترل هم‌زمان.
 *
 * @param WP_Customize_Manager $wp_customize مدیر سفارشی‌ساز.
 * @param string               $id           شناسه‌ی تنظیم.
 * @param bool                 $default      پیش‌فرض.
 * @param string               $section      بخش.
 * @param string               $label        برچسب.
 * @param string               $description   توضیح اختیاری.
 */
function koohe_add_checkbox( $wp_customize, $id, $default, $section, $label, $description = '' ) {
	$wp_customize->add_setting(
		$id,
		array(
			'default'           => $default ? '1' : '',
			'transport'         => 'refresh',
			'sanitize_callback' => 'koohe_sanitize_checkbox',
		)
	);

	$wp_customize->add_control(
		$id,
		array(
			'label'       => $label,
			'description' => $description,
			'section'     => $section,
			'type'        => 'checkbox',
		)
	);
}

/**
 * گزینه‌های نسبت پوستر.
 *
 * @return array<string,string>
 */
function koohe_poster_ratio_choices() {
	return array(
		'2/3'  => __( '۲ به ۳ (پوستر سینمایی)', 'koohe-film' ),
		'3/4'  => __( '۳ به ۴', 'koohe-film' ),
		'1/1'  => __( 'مربع', 'koohe-film' ),
		'16/9' => __( '۱۶ به ۹ (عریض)', 'koohe-film' ),
	);
}

/**
 * پاکسازی حالت رنگ.
 *
 * @param string $value مقدار.
 * @return string
 */
function koohe_sanitize_color_mode( $value ) {
	return in_array( $value, array( 'dark', 'light', 'auto' ), true ) ? $value : 'dark';
}

/**
 * پاکسازی نسبت پوستر.
 *
 * @param string $value مقدار.
 * @return string
 */
function koohe_sanitize_poster_ratio( $value ) {
	$choices = koohe_poster_ratio_choices();

	return isset( $choices[ $value ] ) ? $value : '2/3';
}

/**
 * پاکسازی چک‌باکس؛ همیشه رشته‌ی '1' یا '' برمی‌گرداند.
 *
 * @param mixed $value مقدار خام.
 * @return string
 */
function koohe_sanitize_checkbox( $value ) {
	return wp_validate_boolean( $value ) ? '1' : '';
}

/**
 * پاکسازی رنگ هگز؛ رشته‌ی خالی (حذف رنگ) مجاز است.
 *
 * @param mixed $value مقدار خام.
 * @return string
 */
function koohe_sanitize_hex_color_or_empty( $value ) {
	$value = sanitize_hex_color( $value );

	return $value ? $value : '';
}

/**
 * گزینه‌های عرض چیدمان.
 *
 * مقدار «استاندارد» همان contentSize/wideSize در theme.json است تا
 * پیش‌فرض فعلی سایت دست‌نخورده بماند.
 *
 * @return array<string,array{label:string,content:string,wide:string}>
 */
function koohe_layout_width_choices() {
	return array(
		'standard' => array(
			'label'   => __( 'استاندارد (۸۲۰px)', 'koohe-film' ),
			'content' => '820px',
			'wide'    => '1440px',
		),
		'wide'     => array(
			'label'   => __( 'عریض (۱۱۰۰px)', 'koohe-film' ),
			'content' => '1100px',
			'wide'    => '1720px',
		),
		'narrow'   => array(
			'label'   => __( 'تنگ (۷۲۰px)', 'koohe-film' ),
			'content' => '720px',
			'wide'    => '1000px',
		),
	);
}

/**
 * پاکسازی عرض چیدمان.
 *
 * @param mixed $value مقدار خام.
 * @return string
 */
function koohe_sanitize_layout_width( $value ) {
	$choices = koohe_layout_width_choices();

	return isset( $choices[ $value ] ) ? $value : 'standard';
}

/**
 * گزینه‌های مقیاس شعاع گوشه‌ها.
 *
 * «استاندارد» همان مقادیر custom.radius در theme.json است.
 *
 * @return array<string,array{label:string,sm:string,base:string,lg:string}>
 */
function koohe_radius_scale_choices() {
	return array(
		'standard' => array(
			'label' => __( 'استاندارد', 'koohe-film' ),
			'sm'    => '6px',
			'base'  => '10px',
			'lg'    => '14px',
		),
		'compact'  => array(
			'label' => __( 'فشرده (گوشه‌های تیز)', 'koohe-film' ),
			'sm'    => '4px',
			'base'  => '8px',
			'lg'    => '10px',
		),
		'round'    => array(
			'label' => __( 'گرد (گوشه‌های نرم)', 'koohe-film' ),
			'sm'    => '10px',
			'base'  => '16px',
			'lg'    => '22px',
		),
	);
}

/**
 * پاکسازی مقیاس شعاع گوشه‌ها.
 *
 * @param mixed $value مقدار خام.
 * @return string
 */
function koohe_sanitize_radius_scale( $value ) {
	$choices = koohe_radius_scale_choices();

	return isset( $choices[ $value ] ) ? $value : 'standard';
}

/**
 * رنگ متن متناسب با رنگ تأکید (بر اساس روشنایی نسبی).
 *
 * رنگ‌های روشن، متن تیره می‌گیرند (همان accent-contrast در theme.json)
	 * و رنگ‌های تیره، متن روشن؛ کنتراست متن با پس‌زمینه همیشه کافی می‌ماند.
 *
 * @param string $hex رنگ هگز (مثل #c6ed7b).
 * @return string رنگ هگز متناسب.
 */
function koohe_accent_contrast( $hex ) {
	$hex = sanitize_hex_color( $hex );

	if ( ! $hex ) {
		return '#1b2712';
	}

	$red   = hexdec( substr( $hex, 1, 2 ) );
	$green = hexdec( substr( $hex, 3, 2 ) );
	$blue  = hexdec( substr( $hex, 5, 2 ) );

	/* روشنایی نسبی (WCAG) روی مقیاس ۰ تا ۲۵۵. */
	$luminance = ( 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue ) / 255;

	return $luminance > 0.45 ? '#1b2712' : '#eef1f3';
}

/**
 * پاکسازی مقدار بولی.
 *
 * @deprecated 1.1.0 از koohe_sanitize_checkbox() استفاده کنید.
 *
 * @param mixed $value مقدار.
 * @return string
 */
function koohe_sanitize_bool( $value ) {
	return koohe_sanitize_checkbox( $value );
}

/**
 * انتقال گزینه‌ها به کلاس بدنه.
 *
 * @param array $classes کلاس‌ها.
 * @return array
 */
function koohe_customize_body_class( $classes ) {
	$map = array(
		'koohe_sticky_header'    => 'koohe-sticky-header',
		'koohe_shrink_header'    => 'koohe-shrink-header',
		'koohe_card_hover_zoom'  => 'koohe-card-zoom',
		'koohe_rounded_media'    => 'koohe-rounded-media',
		'koohe_reading_progress' => 'koohe-reading-progress',
		'koohe_show_toggle'      => 'koohe-has-toggle',
	);

	foreach ( $map as $option => $class ) {
		if ( koohe_get_flag( $option ) ) {
			$classes[] = $class;
		}
	}

	$ratio = koohe_sanitize_poster_ratio( get_theme_mod( 'koohe_poster_ratio', '2/3' ) );

	$classes[] = 'koohe-poster-' . sanitize_html_class( str_replace( '/', '-', $ratio ) );

	return $classes;
}
add_filter( 'body_class', 'koohe_customize_body_class' );

/**
 * استایل درون‌خطی برای گزینه‌هایی که مقدار آزاد دارند.
 */
function koohe_customize_inline_css() {
	$ratio = koohe_sanitize_poster_ratio( get_theme_mod( 'koohe_poster_ratio', '2/3' ) );

	/* تنها زمانی چاپ می‌شود که با پیش‌فرض تفاوت داشته باشد (صرفه‌جویی در بایت). */
	if ( '2/3' === $ratio ) {
		return;
	}

	wp_add_inline_style(
		'koohe-film',
		':root{--koohe-poster-ratio:' . str_replace( '/', ' / ', $ratio ) . ';}'
	);
}
add_action( 'wp_enqueue_scripts', 'koohe_customize_inline_css', 20 );

/**
 * متن حق نشر پابرگ با جایگزینی شناسه‌ها.
 *
 * @return string HTML امن.
 */
function koohe_copyright_text() {
	$raw = (string) get_theme_mod( 'koohe_copyright', '' );

	if ( '' === trim( $raw ) ) {
		/* translators: %1$s سال جاری، %2$s نام سایت. */
		$raw = sprintf( __( '© %1$s %2$s — همه‌ی حقوق محفوظ است.', 'koohe-film' ), '{year}', '{site}' );
	}

	$text = str_replace(
		array( '{year}', '{site}' ),
		array( wp_date( 'Y' ), get_bloginfo( 'name', 'display' ) ),
		$raw
	);

	return wp_kses_post( $text );
}

/**
 * افزودن کلاس به پوشش قطعه‌ی قالب سربرگ.
 *
 * دلیل: در قالب‌های FSE سربرگ درون <div class="wp-block-template-part">
 * قرار می‌گیرد. ارتفاع این پوشش برابر ارتفاع سربرگ است، پس اگر خودِ
 * سربرگ position:sticky بگیرد هیچ فضایی برای حرکت ندارد و نمی‌چسبد.
 * راه‌حل استاندارد: چسبان‌کردن همان پوشش. برای اینکه به انتخاب‌گر
 * :has() وابسته نباشیم، کلاس را سمت سرور تزریق می‌کنیم.
 *
 * @param string|null $block_content محتوای رندرشده.
 * @param array       $block         داده‌های بلوک.
 * @return string|null
 */
function koohe_tag_header_slot( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
		return $block_content;
	}

	$area = isset( $block['attrs']['area'] ) ? $block['attrs']['area'] : '';
	$slug = isset( $block['attrs']['slug'] ) ? $block['attrs']['slug'] : '';

	if ( 'header' !== $area && 'header' !== $slug ) {
		return $block_content;
	}

	/* تنها نخستین ویژگی class پوشش بیرونی جایگزین می‌شود. */
	return (string) preg_replace(
		'/class="wp-block-template-part/',
		'class="wp-block-template-part koohe-header-slot',
		(string) $block_content,
		1
	);
}
add_filter( 'render_block', 'koohe_tag_header_slot', 10, 2 );

/**
 * حذف کلید حالت رنگ در صورت خاموش‌بودن گزینه.
 *
 * @param string|null $block_content محتوای رندرشده.
 * @param array       $block         داده‌های بلوک.
 * @return string|null
 */
function koohe_maybe_hide_toggle( $block_content, $block ) {
	if ( isset( $block['blockName'] ) && 'koohe/theme-toggle' === $block['blockName'] && ! koohe_get_flag( 'koohe_show_toggle' ) ) {
		return '';
	}

	return $block_content;
}
add_filter( 'render_block', 'koohe_maybe_hide_toggle', 10, 2 );

/**
 * دکمه‌ی بازگشت به بالا.
 */
function koohe_render_back_to_top() {
	if ( ! koohe_get_flag( 'koohe_back_to_top' ) ) {
		return;
	}
	?>
	<button type="button" class="koohe-to-top" data-koohe-to-top aria-label="<?php esc_attr_e( 'بازگشت به بالا', 'koohe-film' ); ?>">
		<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M12 19V5M5 12l7-7 7 7"></path>
		</svg>
	</button>
	<?php
}
add_action( 'wp_footer', 'koohe_render_back_to_top', 20 );

/**
 * آیا نوار کنش موبایل باید در این درخواست چاپ شود؟
 *
 * تنها در صفحه‌ی یکی از انواع محتوای ManaCore معنا دارد و دست‌کم یک
 * کنش (دانلود یا تریلر) باید موجود باشد؛ نوار خالی فقط فضا می‌گیرد.
 *
 * @return bool
 */
function koohe_actionbar_active() {
	if ( ! is_singular() || ! koohe_get_flag( 'koohe_mobile_actionbar' ) ) {
		return false;
	}

	if ( ! koohe_has_core() ) {
		return false;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id || ! array_key_exists( (string) get_post_type( $post_id ), manacore_post_types() ) ) {
		return false;
	}

	return (bool) koohe_actionbar_items( $post_id );
}

/**
 * کنش‌های موجود برای نوار پایین صفحه.
 *
 * @param int $post_id شناسه‌ی پست.
 * @return array فهرست کنش‌ها.
 */
function koohe_actionbar_items( $post_id ) {
	$items = array();

	$has_links = class_exists( '\ManaCore\Core\Links' )
		&& ! get_post_meta( $post_id, 'manacore_disable_links', true )
		&& \ManaCore\Core\Links::count( $post_id ) > 0;

	if ( $has_links ) {
		$items['download'] = array(
			'type'  => 'link',
			'href'  => '#download',
			'label' => __( 'دانلود و پخش', 'koohe-film' ),
			'class' => 'koohe-actionbar-btn is-primary',
		);
	}

	$trailer = (string) get_post_meta( $post_id, 'manacore_trailer_url', true );
	if ( $trailer ) {
		$items['trailer'] = array(
			'type'    => 'button',
			'label'   => __( 'تریلر', 'koohe-film' ),
			'class'   => 'koohe-actionbar-btn',
			'trailer' => $trailer,
		);
	}

	/**
	 * فیلتر کنش‌های نوار پایین صفحه.
	 *
	 * @param array $items   کنش‌ها.
	 * @param int   $post_id شناسه‌ی پست.
	 */
	return (array) apply_filters( 'koohe_actionbar_items', $items, $post_id );
}

/**
 * افزودن کلاس بدنه هنگام فعال بودن نوار کنش.
 *
 * بدون این کلاس، CSS نمی‌داند فضای پایین صفحه را باز کند و نوار روی
 * محتوا می‌افتد.
 *
 * @param array $classes کلاس‌ها.
 * @return array
 */
function koohe_actionbar_body_class( $classes ) {
	if ( koohe_actionbar_active() ) {
		$classes[] = 'koohe-has-actionbar';
	}

	return $classes;
}
add_filter( 'body_class', 'koohe_actionbar_body_class' );

/**
 * نوار کنش پایین صفحه در موبایل.
 */
function koohe_render_actionbar() {
	if ( ! koohe_actionbar_active() ) {
		return;
	}

	$items = koohe_actionbar_items( get_queried_object_id() );
	?>
	<nav class="koohe-actionbar" aria-label="<?php esc_attr_e( 'کنش‌های سریع این اثر', 'koohe-film' ); ?>">
		<?php foreach ( $items as $item ) : ?>
			<?php if ( 'link' === $item['type'] ) : ?>
				<a class="<?php echo esc_attr( $item['class'] ); ?>" href="<?php echo esc_url( $item['href'] ); ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="<?php echo esc_attr( $item['class'] ); ?>"
					data-manacore-play="<?php echo esc_url( $item['trailer'] ); ?>"
					data-title="<?php echo esc_attr( get_the_title( get_queried_object_id() ) ); ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</button>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php
}
add_action( 'wp_footer', 'koohe_render_actionbar', 21 );

/**
 * نوار پیشرفت خوانش (تنها در صفحات تک‌آیتمی).
 */
function koohe_render_reading_progress() {
	if ( ! is_singular() || ! koohe_get_flag( 'koohe_reading_progress' ) ) {
		return;
	}
	?>
	<div class="koohe-progress" data-koohe-progress aria-hidden="true">
		<div class="koohe-progress-bar"></div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'koohe_render_reading_progress' );

/**
 * اسکریپت پیش‌نمایش زنده‌ی سفارشی‌ساز.
 */
function koohe_customize_preview_js() {
	wp_enqueue_script(
		'koohe-customize-preview',
		KOOHE_URI . 'assets/js/customize-preview.js',
		array( 'customize-preview' ),
		koohe_asset_version( 'assets/js/customize-preview.js' ),
		true
	);
}
add_action( 'customize_preview_init', 'koohe_customize_preview_js' );

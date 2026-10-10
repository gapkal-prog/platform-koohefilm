<?php
/**
 * ثبت بلوک‌های قالب کوه فیلم.
 *
 * تنها منبع حقیقت برای تعریف بلوک‌ها. همین تعریف‌ها هم به
 * register_block_type (سمت سرور) داده می‌شوند و هم به‌صورت JSON به
 * assets/js/blocks.js می‌رسند تا ویرایشگر پیاده‌سازی edit داشته باشد.
 *
 * چرا؟ پیش‌تر بلوک‌ها فقط در PHP ثبت می‌شدند و ویرایشگر پیام
 * «سایت شما از بلوک … پشتیبانی نمی‌کند» را نشان می‌داد.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * تعریف بلوک‌های قالب.
 *
 * @return array<string,array> نام بلوک => تعریف.
 */
function koohe_block_definitions() {
	$definitions = array(
		'koohe/theme-toggle' => array(
			'title'           => __( 'کلید حالت تیره/روشن', 'koohe-film' ),
			'description'     => __( 'دکمه‌ی جابه‌جایی میان حالت تیره و روشن سایت.', 'koohe-film' ),
			'icon'            => 'visibility',
			'keywords'        => array( __( 'تیره', 'koohe-film' ), __( 'روشن', 'koohe-film' ), 'dark', 'light' ),
			'attributes'      => array(
				'label' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'editorFields'    => array(
				'label' => array(
					'type'  => 'text',
					'label' => __( 'برچسب دسترس‌پذیری', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا متن پیش‌فرض به کار رود.', 'koohe-film' ),
				),
			),
			'supports'        => array(
				'html'    => false,
				'align'   => false,
				'anchor'  => true,
				'color'   => array(
					'text'       => true,
					'background' => false,
				),
				'spacing' => array(
					'margin' => true,
				),
			),
			'render_callback' => 'koohe_render_toggle_block',
		),
		'koohe/account'      => array(
			'title'           => __( 'حساب کاربری (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'نمایش دکمه‌ی ورود یا نام کاربر و سطح اشتراک.', 'koohe-film' ),
			'icon'            => 'admin-users',
			'keywords'        => array( __( 'ورود', 'koohe-film' ), __( 'اشتراک', 'koohe-film' ), 'account' ),
			'attributes'      => array(),
			'editorFields'    => array(),
			'supports'        => array(
				'html'    => false,
				'anchor'  => true,
				'spacing' => array(
					'margin' => true,
				),
			),
			'render_callback' => 'koohe_render_account_block',
		),
		'koohe/copyright'    => array(
			'title'           => __( 'حق نشر (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'متن حق نشر از سفارشی‌ساز، با پشتیبانی از {year} و {site}.', 'koohe-film' ),
			'icon'            => 'shield',
			'keywords'        => array( __( 'حق نشر', 'koohe-film' ), __( 'پابرگ', 'koohe-film' ), 'copyright' ),
			'attributes'      => array(),
			'editorFields'    => array(),
			'supports'        => array(
				'html'       => false,
				'anchor'     => true,
				'color'      => array(
					'text'       => true,
					'background' => false,
				),
				'typography' => array(
					'fontSize' => true,
				),
			),
			'render_callback' => 'koohe_render_copyright_block',
		),
		/*
		 * کنش‌های صفحه‌ی اثر (افزودن به لیست تماشا و کپی پیوند).
		 *
		 * پیش‌تر این دو دکمه به‌صورت HTML ایستا در میان الگو نوشته می‌شدند
		 * و شناسه‌ی اثر با PHP خام درج می‌شد. آن روش دو اشکال داشت:
		 *   ۱) در «الگوی بخش» (فایل .html) وردپرس PHP اجرا نمی‌کند، پس
		 *      کد به‌صورت متن خام در صفحه چاپ می‌شد؛
		 *   ۲) مدیر سایت نمی‌توانست متن، آیکن یا ترتیبشان را از ویرایشگر
		 *      تغییر دهد و برای هر جای تازه باید کد تکرار می‌شد.
		 * اکنون هر دو به بلوک سمت‌سرور تبدیل شده‌اند: در «الگو» و
		 * «بخش» و «الگوی آماده» یکسان کار می‌کنند، در ویرایشگر
		 * پیش‌نمایش واقعی دارند و از نوار کنار قابل تنظیم‌اند.
		 */
		'koohe/watchlist-button' => array(
			'title'           => __( 'دکمه‌ی لیست تماشا (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'افزودن/حذف اثر جاری از لیست تماشای کاربر.', 'koohe-film' ),
			'icon'            => 'heart',
			'keywords'        => array( __( 'لیست تماشا', 'koohe-film' ), __( 'نشان‌شده', 'koohe-film' ), 'watchlist', 'favorite' ),
			'attributes'      => array(
				'label'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'labelSaved' => array(
					'type'    => 'string',
					'default' => '',
				),
				'showIcon'   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'style'      => array(
					'type'    => 'string',
					'default' => 'glass',
				),
			),
			'editorFields'    => array(
				'label'      => array(
					'type'  => 'text',
					'label' => __( 'متن دکمه', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «لیست تماشا» به کار رود.', 'koohe-film' ),
				),
				'labelSaved' => array(
					'type'  => 'text',
					'label' => __( 'متن پس از افزودن', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «در لیست تماشا» به کار رود.', 'koohe-film' ),
				),
				'showIcon'   => array(
					'type'  => 'toggle',
					'label' => __( 'نمایش آیکن قلب', 'koohe-film' ),
				),
				'style'      => array(
					'type'    => 'select',
					'label'   => __( 'سبک دکمه', 'koohe-film' ),
					'options' => array(
						'glass'   => __( 'شیشه‌ای (روی تصویر)', 'koohe-film' ),
						'primary' => __( 'تأکیدی', 'koohe-film' ),
						'ghost'   => __( 'ساده', 'koohe-film' ),
					),
				),
			),
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'koohe_render_watchlist_block',
		),
		'koohe/copy-link'        => array(
			'title'           => __( 'کپی پیوند (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'دکمه‌ای که نشانی اثر جاری را در کلیپ‌بورد کپی می‌کند.', 'koohe-film' ),
			'icon'            => 'admin-links',
			'keywords'        => array( __( 'کپی', 'koohe-film' ), __( 'اشتراک‌گذاری', 'koohe-film' ), 'copy', 'share' ),
			'attributes'      => array(
				'label'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'showIcon' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'style'    => array(
					'type'    => 'string',
					'default' => 'glass',
				),
			),
			'editorFields'    => array(
				'label'    => array(
					'type'  => 'text',
					'label' => __( 'متن دکمه', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «کپی لینک» به کار رود.', 'koohe-film' ),
				),
				'showIcon' => array(
					'type'  => 'toggle',
					'label' => __( 'نمایش آیکن پیوند', 'koohe-film' ),
				),
				'style'    => array(
					'type'    => 'select',
					'label'   => __( 'سبک دکمه', 'koohe-film' ),
					'options' => array(
						'glass'   => __( 'شیشه‌ای (روی تصویر)', 'koohe-film' ),
						'primary' => __( 'تأکیدی', 'koohe-film' ),
						'ghost'   => __( 'ساده', 'koohe-film' ),
					),
				),
			),
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'koohe_render_copy_link_block',
		),
		'koohe/post-type'        => array(
			'title'           => __( 'نشانِ نوع محتوا (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'چیپِ کوچکِ نوع اثر: فیلم، سریال، انیمه یا قسمت — همان چیپِ سربرگِ جزئیات در الگوی مرجع.', 'koohe-film' ),
			'icon'            => 'tag',
			'keywords'        => array( __( 'نوع', 'koohe-film' ), __( 'نشان', 'koohe-film' ), 'type', 'badge', 'chip' ),
			'attributes'      => array(
				'label'          => array(
					'type'    => 'string',
					'default' => '',
				),
				'style'          => array(
					'type'    => 'string',
					'default' => 'accent',
				),
				'linkToArchive'  => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'editorFields'    => array(
				'label'          => array(
					'type'  => 'text',
					'label' => __( 'برچسب', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا نوعِ خودکارِ اثر (فیلم/سریال/انیمه/قسمت) نوشته شود.', 'koohe-film' ),
				),
				'style'          => array(
					'type'    => 'select',
					'label'   => __( 'سبک نشان', 'koohe-film' ),
					'options' => array(
						'accent' => __( 'تأکیدی (سبز کوه)', 'koohe-film' ),
						'soft'   => __( 'ملایم (خاکستری)', 'koohe-film' ),
					),
				),
				'linkToArchive'  => array(
					'type'  => 'toggle',
					'label' => __( 'پیوند به آرشیو همان نوع محتوا', 'koohe-film' ),
				),
			),
			'supports'        => array(
				'html'   => false,
				'anchor' => true,
			),
			'render_callback' => 'koohe_render_post_type_block',
		),
		'koohe/search-trigger'   => array(
			'title'           => __( 'دکمه‌ی جستجو (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'دکمه‌ی سربرگ که پوسته‌ی جستجوی تمام‌صفحه را باز می‌کند — همان الگوی مرجع: آیکن + متن راهنما + میان‌بر ⌘K.', 'koohe-film' ),
			'icon'            => 'search',
			'keywords'        => array( 'search', __( 'جستجو', 'koohe-film' ), '⌘K' ),
			'attributes'      => array(
				'placeholder' => array(
					'type'    => 'string',
					'default' => '',
				),
				'showHint'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'editorFields'    => array(
				'placeholder' => array(
					'type'  => 'text',
					'label' => __( 'متن راهنما', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «دنبال چی می‌گردی؟» به کار رود.', 'koohe-film' ),
				),
				'showHint'    => array(
					'type'  => 'toggle',
					'label' => __( 'نمایش میان‌بر ⌘K', 'koohe-film' ),
				),
			),
			'supports'        => array(
				'html'    => false,
				'anchor'  => true,
				'color'   => array(
					'text' => true,
				),
				'spacing' => array(
					'margin' => true,
				),
			),
			'render_callback' => 'koohe_render_search_trigger_block',
		),
		'koohe/mobile-drawer'    => array(
			'title'           => __( 'کشوی منوی موبایل (کوه فیلم)', 'koohe-film' ),
			'description'     => __( 'کشوی کنارِ همسان با مرجع: پس‌زمینه‌ی تار، دکمه‌ی بستن، ردیف جستجو، ردیف‌های فهرست راهبری با شِوران، دکمه‌ی اشتراک و یک سطر پایانی.', 'koohe-film' ),
			'icon'            => 'menu-alt',
			'keywords'        => array( 'drawer', __( 'منو', 'koohe-film' ), 'mobile', __( 'همبرگری', 'koohe-film' ) ),
			'attributes'      => array(
				'label'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'searchLabel' => array(
					'type'    => 'string',
					'default' => '',
				),
				'ctaLabel'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'note'        => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'editorFields'    => array(
				'label'       => array(
					'type'  => 'text',
					'label' => __( 'نام قابل‌دسترس گفت‌وگو', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «منوی اصلی» به کار رود.', 'koohe-film' ),
				),
				'searchLabel' => array(
					'type'  => 'text',
					'label' => __( 'متن ردیف جستجو', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «جستجوی فیلم و سریال» به کار رود.', 'koohe-film' ),
				),
				'ctaLabel'    => array(
					'type'  => 'text',
					'label' => __( 'متن دکمه‌ی اشتراک', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «خرید اشتراک» به کار رود.', 'koohe-film' ),
				),
				'note'        => array(
					'type'  => 'text',
					'label' => __( 'سطر پایانی', 'koohe-film' ),
					'help'  => __( 'خالی بگذارید تا «هر داستان، یک دنیای تازه.» به کار رود.', 'koohe-film' ),
				),
			),
			'supports'        => array(
				'html'    => false,
				'anchor'  => true,
				'spacing' => array(
					'margin' => true,
				),
			),
			'render_callback' => 'koohe_render_mobile_drawer_block',
		),
	);

	/**
	 * فیلتر تعریف بلوک‌های قالب.
	 *
	 * @param array $definitions تعریف‌ها.
	 */
	return (array) apply_filters( 'koohe_block_definitions', $definitions );
}

/**
 * ثبت همه‌ی بلوک‌های قالب در سمت سرور.
 */
function koohe_register_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	foreach ( koohe_block_definitions() as $name => $definition ) {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			continue;
		}

		/* editorFields تنها برای ویرایشگر است و به هسته فرستاده نمی‌شود. */
		unset( $definition['editorFields'] );

		register_block_type(
			$name,
			array_merge(
				array(
					'api_version' => 3,
					'category'    => 'manacore',
				),
				$definition
			)
		);
	}
}
add_action( 'init', 'koohe_register_blocks', 25 );

/**
 * داده‌ی ثبت بلوک برای ویرایشگر (بدون callback های PHP).
 *
 * @return array
 */
function koohe_editor_registry() {
	$registry = array();

	foreach ( koohe_block_definitions() as $name => $definition ) {
		$registry[ $name ] = array(
			'title'        => isset( $definition['title'] ) ? $definition['title'] : $name,
			'description'  => isset( $definition['description'] ) ? $definition['description'] : '',
			'category'     => isset( $definition['category'] ) ? $definition['category'] : 'manacore',
			'icon'         => isset( $definition['icon'] ) ? $definition['icon'] : 'video-alt2',
			'keywords'     => isset( $definition['keywords'] ) ? array_values( (array) $definition['keywords'] ) : array(),
			'attributes'   => isset( $definition['attributes'] ) ? $definition['attributes'] : array(),
			'editorFields' => isset( $definition['editorFields'] ) ? $definition['editorFields'] : array(),
			'supports'     => isset( $definition['supports'] ) ? $definition['supports'] : array(),
		);
	}

	return $registry;
}

/**
 * اسکریپت ویرایشگر برای بلوک‌های قالب.
 */
function koohe_enqueue_block_editor_assets() {
	wp_enqueue_script(
		'koohe-blocks',
		KOOHE_URI . 'assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		koohe_asset_version( 'assets/js/blocks.js' ),
		true
	);

	/*
	 * از inline script استفاده می‌شود تا نوع داده‌ها (بولین/عدد) در
	 * JSON دست‌نخورده بماند؛ wp_localize_script همه را رشته می‌کند.
	 */
	wp_add_inline_script(
		'koohe-blocks',
		'window.kooheBlockRegistry = ' . wp_json_encode( koohe_editor_registry() ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'koohe_enqueue_block_editor_assets' );

/* ---------------------------------------------------------------------------
 * اصلاح نشانه‌گذاری ناوبری هسته
 * ------------------------------------------------------------------------- */

/**
 * تخت‌کردن فهرست برگه‌ها در بلوک ناوبری.
 *
 * وقتی به بلوک Navigation هیچ فهرست (wp_navigation) نسبت داده نشده باشد،
 * هسته به‌عنوان جانشین، بلوک Page List را داخل آن می‌رندر می‌کند و نتیجه
 * این ساختار تودرتو می‌شود:
 *
 *     ul.wp-block-navigation__container
 *       └ ul.wp-block-page-list
 *           └ li …
 *
 * دو پیامد دارد: (۱) از نظر معنایی نامعتبر است — فرزند مستقیم `ul` باید
 * `li` باشد — و axe-core آن را نقض `[serious] list` می‌بیند (سنجیده‌شده:
 * ۲ نمونه در هر برگه)؛ (۲) آیتم‌ها فرزند مستقیم ظرف ناوبری نیستند، پس
 * چیدمان افقی/اسکرول درست روی همان ظرف اعمال نمی‌شود.
 *
 * این فیلتر فقط و فقط لایه‌ی بسته‌بندی `ul.wp-block-page-list` را برمی‌دارد
 * و `li`ها را به همان ظرف می‌آورد. اگر فهرست برگه زیرمنو داشته باشد
 * (یعنی `ul` تودرتو داشته باشد) دست‌نخورده رها می‌شود تا ساختار خراب نشود.
 *
 * @param string $block_content HTML رندرشده‌ی بلوک.
 * @param array  $block         داده‌ی بلوک.
 * @return string
 */
function koohe_flatten_nav_page_list( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! is_string( $block_content ) || false === strpos( $block_content, 'wp-block-page-list' ) ) {
		return $block_content;
	}

	$flattened = preg_replace_callback(
		'#<ul\b[^>]*wp-block-page-list[^>]*>(.*?)</ul>#s',
		static function ( $matches ) {
			/* زیرمنو دارد؟ دست نزن. */
			if ( preg_match( '#<ul\b#i', $matches[1] ) ) {
				return $matches[0];
			}

			return $matches[1];
		},
		$block_content
	);

	return is_string( $flattened ) ? $flattened : $block_content;
}
/**
 * سرصفحه‌ی دیدگاه‌ها را به شکل `.section-heading` مرجع درمی‌آورد.
 *
 * هسته `core/comments-title` را به‌صورت یک `<h2>` تنها رندر می‌کند. الگوی
 * مرجع، سرصفحه‌ی دیدگاه‌ها را در قاب `.section-heading > .heading-title`
 * با آیکن بخش و نشانِ شمار دیدگاه‌ها می‌گذارد. اینجا همان قاب ساخته می‌شود
 * و متن سرعنوان یکدست می‌شود («دیدگاه‌ها» + شمار) تا در همه‌ی قالب‌ها یک
 * شکل داشته باشد.
 *
 * @param string $block_content خروجی بلوک.
 * @param array  $block         بلوک.
 * @return string
 */
function koohe_comments_title_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/comments-title' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! is_string( $block_content ) || ! function_exists( 'get_comments_number' ) ) {
		return $block_content;
	}

	if ( is_admin() || ! preg_match( '#<h([1-6])([^>]*)>(.*?)</h\1>#s', $block_content, $matches ) ) {
		return $block_content;
	}

	koohe_comments_heading_printed( true );

	/*
	 * پریست اندازه‌ی قلم هسته از ویژگی‌ها برداشته می‌شود.
	 *
	 * بلوک `core/comments-title` در قالب‌ها با `fontSize` تنظیم شده بود و
	 * هسته کلاس `has-x-large-font-size` را با **`!important`** چاپ
	 * می‌کند؛ نتیجه این بود که `h2` در ۲۸px رندر می‌شد در حالی که مرجع
	 * `.comments-section h2{font-size:18px}` دارد (سنجیده‌شده در ۱۴۴۰px).
	 */
	$attrs = preg_replace_callback(
		'#class="([^"]*)"#i',
		static function ( $class_match ) {
			$kept = preg_grep( '#font-size$#', array_filter( explode( ' ', $class_match[1] ) ), PREG_GREP_INVERT );

			return $kept ? 'class="' . esc_attr( implode( ' ', $kept ) ) . '"' : '';
		},
		(string) $matches[2]
	);

	return koohe_comments_heading( esc_html( $matches[1] ), (string) $attrs );
}

/**
 * سرصفحه‌ی آماده‌ی دیدگاه‌ها — `.section-heading > .heading-title > h2`
 * با آیکن بخش، نشان شمار دیدگاه‌ها و گزینشگر مرتب‌سازی (`#comment-sort`).
 *
 * @param string $level سطح سرعنوان (`2` پیش‌فرض).
 * @param string $attrs ویژگی‌های اضافی تگ سرعنوان (از خودِ هسته).
 * @return string
 */
function koohe_comments_heading( $level = '2', $attrs = '' ) {
	$post_id = (int) get_the_ID();
	$count   = $post_id ? (int) get_comments_number( $post_id ) : 0;

	$heading = sprintf(
		'<h%1$s%2$s>%3$s<span class="count-badge">%4$s</span></h%1$s>',
		esc_html( (string) $level ),
		$attrs, // phpcs:ignore WordPress.Security.EscapeOutput -- ویژگی‌های خودِ هسته یا ثابت.
		esc_html__( 'دیدگاه‌ها', 'koohe-film' ),
		esc_html( number_format_i18n( $count ) )
	);

	/*
	 * مرجع در سرصفحه‌ی دیدگاه‌ها یک گزینشگر مرتب‌سازی هم دارد
	 * (`#comment-sort`). مرتب‌سازی سمت کاربر انجام می‌شود
	 * (`theme.js` روی `time[datetime]` هر دیدگاه) تا با ساختار
	 * تودرتوی پاسخ‌ها درگیر نشود.
	 */
	$sort = '<select id="comment-sort" class="comment-sort" data-comment-sort aria-label="' . esc_attr__( 'ترتیب دیدگاه‌ها', 'koohe-film' ) . '">'
		. '<option value="latest">' . esc_html__( 'جدیدترین دیدگاه‌ها', 'koohe-film' ) . '</option>'
		. '<option value="oldest">' . esc_html__( 'قدیمی‌ترین دیدگاه‌ها', 'koohe-film' ) . '</option>'
		. '</select>';

	return '<div class="section-heading"><div class="heading-title">'
		. '<span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>'
		. $heading
		. '</div>'
		. $sort
		. '</div>';
}

/**
 * ثبت اینکه سرصفحه‌ی دیدگاه‌ها چاپ شد یا نه.
 *
 * بلوک `core/comments-title` هسته وقتی دیدگاهی وجود ندارد **هیچ خروجی‌ای
 * ندارد**؛ پس در آن حالت سرصفحه‌ی بخش («دیدگاه‌ها» + شمار + مرتب‌سازی)
 * کامل غایب می‌شد (سنجیده‌شده روی برگه‌ی قسمتی بدون دیدگاه). اکنون
 * حالت خالی همان سرصفحه را هم چاپ می‌کند.
 *
 * @param bool|null $set مقدار تازه (اختیاری).
 * @return bool
 */
function koohe_comments_heading_printed( $set = null ) {
	static $printed = false;

	if ( null !== $set ) {
		$printed = (bool) $set;
	}

	return $printed;
}

/**
 * حالت خالی دیدگاه‌ها، مثل `.comment-empty` مرجع.
 *
 * بلوک `core/comment-template` وقتی دیدگاهی نیست چیزی چاپ نمی‌کند؛
 * مرجع در آن حالت پیام «اولین نفری باش که از این داستان می‌نویسد.»
 * را نشان می‌دهد.
 *
 * @param string $block_content خروجی بلوک.
 * @param array  $block         بلوک.
 * @return string
 */
function koohe_comments_empty_block( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/comment-template' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( '' !== trim( (string) $block_content ) ) {
		return $block_content;
	}

	$post_id = isset( $block['context']['postId'] ) ? (int) $block['context']['postId'] : (int) get_the_ID();
	if ( $post_id && (int) get_comments_number( $post_id ) > 0 ) {
		return $block_content; // صفحه‌بندی: دیدگاه هست، فقط در این صفحه نه.
	}

	$empty = '<div class="comment-empty">'
		. '<span aria-hidden="true">▢</span>'
		. '<p>' . esc_html__( 'اولین نفری باش که از این داستان می‌نویسد.', 'koohe-film' ) . '</p>'
		. '</div>';

	/* سرصفحه‌ی بخش، اگر هسته چیزی چاپ نکرده باشد (بدون دیدگاه). */
	return koohe_comments_heading_printed() ? $empty : koohe_comments_heading() . $empty;
}

add_filter( 'render_block', 'koohe_flatten_nav_page_list', 10, 2 );
add_filter( 'render_block', 'koohe_comments_title_block', 10, 2 );
add_filter( 'render_block', 'koohe_comments_empty_block', 10, 2 );

/* ---------------------------------------------------------------------------
 * مگامنو روی ساز‌و‌کار استاندارد وردپرس
 *
 * مگامنو کاملاً در خود فهرست راهبری (ظاهر ← ویرایشگر ← ناوبری) مدیریت
 * می‌شود: متن‌ها، ترتیب، ژانرها، ردیف‌های دسترسی سریع و کارت ویژه همه
 * آیتم‌های فهرست‌اند. نقش هر آیتم در بخش «مگامنو» کنترل‌های کناری
 * ویرایشگر تعیین می‌شود (`assets/js/mega-menu.js`) و به کلاس‌های CSS
 * تبدیل می‌شود. هیچ تنظیم جداگانه‌ای در افزونه ندارد.
 * ------------------------------------------------------------------------- */

/**
 * شناسه‌ی فهرست راهبری فعال.
 *
 * سفارش انتخاب: فهرستی که این قالب ساخته و در theme_mod ذخیره کرده، سپس
 * تازه‌ترین فهرست راهبری منتشرشده‌ی سایت. صفر یعنی هیچ فهرستی وجود ندارد
 * و بلوک ناوبری باید به جانشین پیش‌فرض هسته برگردد.
 *
 * @return int
 */
function koohe_primary_navigation_id() {
	$stored = (int) get_theme_mod( 'koohe_navigation_id', 0 );
	if ( $stored && 'publish' === get_post_status( $stored ) ) {
		return $stored;
	}

	$menus = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return $menus ? (int) $menus[0] : 0;
}

/**
 * اتصال بلوک ناوبری به فهرست راهبری وردپرس.
 *
 * بلوک ناوبری قطعه‌ی سربرگ بدون `ref` رندر می‌شود؛ در آن حالت هسته به
 * «فهرست برگه‌ها» برمی‌گردد و هر برگه‌ای — از جمله برگه‌های فنی و آزمون —
 * به سربرگ راه پیدا می‌کند، در حالی که فهرستی که مدیر در پیشخوان ساخته
 * هیچ‌جا دیده نمی‌شود. این فیلتر پیش از رندر، `ref` خالی را به فهرست
 * راهبری واقعی وصل می‌کند.
 *
 * @param array $parsed_block بلوک تجزیه‌شده.
 * @return array
 */
function koohe_bind_navigation_menu( $parsed_block ) {
	if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
		return $parsed_block;
	}

	if ( ! empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}

	$menu_id = koohe_primary_navigation_id();
	if ( $menu_id && ! is_admin() ) {
		$parsed_block['attrs']['ref'] = $menu_id;
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'koohe_bind_navigation_menu' );

/**
 * نشانی «مرکز دسته‌بندی‌ها» برای پیش‌فرض‌های فهرست.
 *
 * اولویت: برگه‌ی `categories-hub` → `browse` → `movies` → آرشیو فیلم → خانه.
 *
 * @return string
 */
function koohe_mega_hub_url() {
	foreach ( array( 'categories-hub', 'browse', 'movies' ) as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && 'publish' === get_post_status( $page->ID ) ) {
			return (string) get_permalink( $page->ID );
		}
	}

	$archive = get_post_type_archive_link( 'movie' );

	return $archive ? (string) $archive : home_url( '/' );
}

/**
 * آرایه‌کردن کارت ویژه‌ی مگامنو: پوستر، سطر «تماشای …» و نشانی کارت.
 *
 * مرجع کارت ویژه را چنین می‌سازد:
 *
 *     <a class="mega-feature" href="…">
 *       <img src="…" alt="…">
 *       <span>
 *         <small>انتخاب ویژه این هفته</small>
 *         <strong>تلماسه</strong>
 *         <span>تماشای تلماسه <svg…/></span>
 *       </span>
 *     </a>
 *
 * آیتم فهرست راهبری وردپرس فرزند نمی‌پذیرد (برچسب و توضیح دارد)، پس پوستر
 * و سطر سوم در خروجی رندر تزریق می‌شوند. «تماشای X» از برچسب همان آیتم
 * ساخته می‌شود، پس اگر مدیر کارت را به اثر دیگری ببندد متن هم عوض می‌شود.
 *
 * @param string $block_content HTML رندرشده‌ی بلوک.
 * @param array  $block         داده‌ی بلوک.
 * @return string
 */
function koohe_mega_feature_card( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! is_string( $block_content ) || false === strpos( $block_content, 'koohe-mega-feature' ) ) {
		return $block_content;
	}

	$pattern = '#(<li[^>]*koohe-mega-feature[^>]*>\s*<a[^>]*>)(.*?)(</a>)#s';

	$content = preg_replace_callback(
		$pattern,
		static function ( $matches ) {
			if ( false !== strpos( $matches[2], 'koohe-mega-feature__cta' ) ) {
				return $matches[0]; // پیش‌تر تزریق شده است.
			}

			$inner = $matches[2];

			/* برچسب آیتم = نام اثر؛ سطر سوم از همان ساخته می‌شود. */
			$label = '';

			if ( preg_match( '#<span class="wp-block-navigation-item__label">(.*?)</span>#s', $inner, $found ) ) {
				$label = trim( wp_strip_all_tags( $found[1] ) );
			}

			if ( '' === $label ) {
				return $matches[0];
			}

			/**
			 * فیلتر متن کنش کارت ویژه‌ی مگامنو.
			 *
			 * @param string $cta_text متن پیش‌فرض («تماشای نام اثر»).
			 * @param string $label    نام اثر.
			 */
			$cta_text = (string) apply_filters(
				'koohe_mega_feature_cta',
				sprintf( /* translators: %s: نام اثر */ __( 'تماشای %s', 'koohe-film' ), $label ),
				$label
			);

			$cta = '<span class="koohe-mega-feature__cta">' . esc_html( $cta_text ) . '</span>';

			/* پوستر: شناسه‌ی اثر از نشانی همان پیوندِ کارت. */
			$post_id = preg_match( '#href="([^"]+)"#', $matches[1], $href ) ? (int) url_to_postid( $href[1] ) : 0;

			/*
			 * پوستر با زنجیره‌ی مشترک افزونه («تصویر شاخص → backdrop →
			 * poster») خوانده می‌شود تا آثار دارای پوستر دستی هم تصویر داشته باشند.
			 */
			$placeholder = defined( 'MANACORE_URL' ) ? MANACORE_URL . 'assets/placeholder.svg' : '';

			if ( $post_id && function_exists( 'manacore_backdrop_url' ) ) {
				$image = (string) manacore_backdrop_url( $post_id, 'large' );
				if ( '' !== $placeholder && 0 === strpos( $image, $placeholder ) ) {
					$image = '';
				}
			} else {
				$image = $post_id ? (string) get_the_post_thumbnail_url( $post_id, 'large' ) : '';
			}

			$poster = '';

			if ( '' !== $image ) {
				$poster = '<img class="koohe-mega-feature__img" src="' . esc_url( $image ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async">';
			}

			return $matches[1] . $poster . $inner . $cta . $matches[3];
		},
		$block_content
	);

	return is_string( $content ) ? $content : $block_content;
}
add_filter( 'render_block', 'koohe_mega_feature_card', 10, 2 );

/**
 * نقطه‌ی کنار آیتم «برنامه پخش» در ناوبری.
 *
 * مرجع این نقطه را داخل خودِ لنگر گذاشته است. بلوک `core/navigation-link`
 * فقط برچسب متنی می‌پذیرد، پس همان نقطه با فیلتر رندر تزریق می‌شود؛ آیتم
 * با کلاس `koohe-nav-dot` نشانه‌گذاری می‌شود (کلید «نقطه‌ی تزئینی» در
 * ویرایشگر فهرست همین کلاس را می‌گذارد).
 *
 * @param string $block_content HTML رندرشده‌ی بلوک.
 * @param array  $block         داده‌ی بلوک.
 * @return string
 */
function koohe_nav_item_dot( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/navigation-link' !== $block['blockName'] ) {
		return $block_content;
	}

	$classes = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	if ( false === strpos( $classes, 'koohe-nav-dot' ) || false === strpos( $block_content, '</a>' ) ) {
		return $block_content;
	}

	return str_replace( '</a>', '<span class="nav-dot" aria-hidden="true"></span></a>', $block_content );
}
add_filter( 'render_block', 'koohe_nav_item_dot', 10, 2 );

/**
 * ساخت نشانه‌گذاری فهرست راهبری پیش‌فرض.
 *
 * این نشانه‌گذاری **فقط یک‌بار** و هنگام فعال‌سازی قالب ساخته می‌شود. پس
 * از آن فهرست متعلق به مدیر است: هر تغییری در ویرایشگر فهرست می‌ماند و
 * هیچ مسیر تازه‌سازی خودکاری آن را بازنویسی نمی‌کند.
 *
 * @return string
 */
function koohe_primary_navigation_markup() {
	$hub    = koohe_mega_hub_url();
	$browse = get_post_type_archive_link( 'movie' );
	$browse = $browse ? $browse : home_url( '/' );

	/*
	 * `$class` کلاس نقش آیتم در مگامنو است (همان قراردادی که
	 * `assets/js/mega-menu.js` در ویرایشگر می‌نویسد).
	 */
	$link = static function ( $label, $url, $kind = 'custom', $type = '', $id = 0, $class = '', $description = '' ) {
		return '<!-- wp:navigation-link ' . wp_json_encode(
			array_filter(
				array(
					'label'       => $label,
					'url'         => $url,
					'kind'        => $kind,
					'type'        => $type,
					'id'          => $id ? $id : null,
					'className'   => $class,
					'description' => $description,
				)
			),
			JSON_UNESCAPED_UNICODE
		) . ' /-->';
	};

	/*
	 * `$group` = زیرمنوی تودرتو؛ همان ظرف‌های مرجع (`.mega-genres` و
	 * `.mega-quick`). برچسب گروه در پنل دسکتاپ دیده نمی‌شود.
	 */
	$group = static function ( $label, $url, $class, $children ) {
		return '<!-- wp:navigation-submenu ' . wp_json_encode(
			array(
				'label'     => $label,
				'url'       => $url,
				'kind'      => 'custom',
				'className' => 'koohe-mega-group ' . $class,
			),
			JSON_UNESCAPED_UNICODE
		) . ' -->' . implode( '', $children ) . '<!-- /wp:navigation-submenu -->';
	};

	/* ژانرها: ترم‌های واقعی تاکسونومی ژانر (حداکثر ۱۲ مورد پرتکرار). */
	$genre_links = array();
	$genres      = get_terms(
		array(
			'taxonomy'   => 'genre',
			'hide_empty' => false,
			'number'     => 12,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( ! is_wp_error( $genres ) ) {
		foreach ( $genres as $genre ) {
			$genre_links[] = $link( $genre->name, (string) get_term_link( $genre ), 'taxonomy', 'genre', (int) $genre->term_id, 'koohe-mega-genre' );
		}
	}

	if ( ! $genre_links ) {
		/* پنل خالی نماند تا وقتی ترمی ساخته نشده است. */
		$genre_links[] = $link( __( 'همه دسته‌بندی‌ها', 'koohe-film' ), $hub, 'custom', '', 0, 'koohe-mega-genre' );
	}

	/* دسترسی سریع: ردیف‌های مرجع با آیکن اختصاصی هر ردیف. */
	$quick_links = array(
		$link( __( 'بالاترین امتیازها', 'koohe-film' ), add_query_arg( 'mc_sort', 'rating', $browse ), 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--rating' ),
		$link( __( 'تازه‌های کوهه', 'koohe-film' ), add_query_arg( 'mc_sort', 'newest', $browse ), 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--newest' ),
	);

	/* «کره‌ای» فقط وقتی کشور متناظر وجود دارد، وگرنه ردیف بی‌مقصد می‌شد. */
	$korean = get_terms(
		array(
			'taxonomy'   => 'country',
			'hide_empty' => false,
			'number'     => 1,
			'name__like' => 'کره',
		)
	);

	if ( ! is_wp_error( $korean ) && $korean ) {
		$quick_links[] = $link( __( 'فیلم و سریال کره‌ای', 'koohe-film' ), (string) get_term_link( $korean[0] ), 'taxonomy', 'country', (int) $korean[0]->term_id, 'koohe-mega-quick koohe-mega-quick--korean' );
	}

	$person_archive = get_post_type_archive_link( 'person' );

	if ( $person_archive ) {
		$quick_links[] = $link( __( 'بازیگران و کارگردان‌ها', 'koohe-film' ), $person_archive, 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--cast' );
	}

	/* کارت ویژه: پیش‌فرض، پرامتیازترین فیلم دارای امتیاز IMDb است. */
	$feature_title = __( 'برترین‌های کوهه', 'koohe-film' );
	$feature_url   = $browse;

	$featured = get_posts(
		array(
			'post_type'        => 'movie',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'meta_key'         => 'manacore_imdb_rating',
			'orderby'          => 'meta_value_num',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	if ( $featured ) {
		$feature_title = get_the_title( $featured[0] );
		$feature_url   = (string) get_permalink( $featured[0] );
	}

	/*
	 * ساختار پنل، سه ستونِ مرجع:
	 *
	 *   ستون ۱ — سرستون + تیتر + گروه «ژانرها» (شبکه‌ی سه‌ستونه)
	 *   ستون ۲ — گروه «دسترسی سریع» (برچسب گروه = سرستون ستون)
	 *   ستون ۳ — کارت ویژه (`.mega-feature`)
	 */
	$submenu = array(
		$link( __( 'یک دنیا انتخاب', 'koohe-film' ), $hub, 'custom', '', 0, 'koohe-mega-eyebrow' ),
		$link( __( 'حال‌وهوای امشبت چیه؟', 'koohe-film' ), $hub, 'custom', '', 0, 'koohe-mega-title' ),
		$group( __( 'ژانرها', 'koohe-film' ), $hub, 'koohe-mega-genres', $genre_links ),
		$group( __( 'به انتخاب سینورا', 'koohe-film' ), $hub, 'koohe-mega-quick', $quick_links ),
		$link( $feature_title, $feature_url, 'custom', '', 0, 'koohe-mega-feature', __( 'انتخاب ویژه این هفته', 'koohe-film' ) ),
	);

	$mega_item = '<!-- wp:navigation-submenu ' . wp_json_encode(
		array(
			'label'     => __( 'دسته‌بندی‌ها', 'koohe-film' ),
			'url'       => $hub,
			'kind'      => 'custom',
			'className' => 'koohe-mega',
		),
		JSON_UNESCAPED_UNICODE
	) . ' -->' . implode( '', $submenu ) . '<!-- /wp:navigation-submenu -->';

	/*
	 * ترتیب آیتم‌ها همان ترتیب مرجع است: «خانه» نخست، سپس «دسته‌بندی‌ها»
	 * و «برنامه پخش» با نقطه‌ی کنارش، بعد «اشتراک».
	 */
	return $link( __( 'خانه', 'koohe-film' ), home_url( '/' ) )
		. $mega_item
		. $link( __( 'برنامه پخش', 'koohe-film' ), home_url( '/schedule/' ), 'custom', '', 0, 'koohe-nav-dot' )
		. $link( __( 'اشتراک', 'koohe-film' ), home_url( '/subscribe/' ) )
		. $link( __( 'درباره ما', 'koohe-film' ), home_url( '/about/' ) )
		. $link( __( 'تماس با ما', 'koohe-film' ), home_url( '/contact/' ) );
}

/**
 * ساخت فهرست راهبری پیش‌فرض هنگام فعال‌سازی قالب.
 *
 * اگر فهرستی از پیش وجود داشته باشد (ساخته‌شده توسط مدیر یا نسخه‌ی پیشین)
 * دست‌نخورده می‌ماند و چیزی ساخته نمی‌شود.
 *
 * @return void
 */
function koohe_seed_primary_navigation() {
	if ( koohe_primary_navigation_id() ) {
		return;
	}

	$menu_id = wp_insert_post(
		array(
			'post_title'   => __( 'فهرست اصلی', 'koohe-film' ),
			'post_name'    => 'primary',
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			/* `wp_insert_post` خودش wp_unslash می‌کند؛ پس پیش از ذخیره wp_slash لازم است. */
			'post_content' => wp_slash( koohe_primary_navigation_markup() ),
		)
	);

	if ( $menu_id && ! is_wp_error( $menu_id ) ) {
		set_theme_mod( 'koohe_navigation_id', (int) $menu_id );
	}
}
add_action( 'after_switch_theme', 'koohe_seed_primary_navigation' );

/**
 * بارگذاری کنترل‌های «مگامنو» در ویرایشگر فهرست راهبری و سایت.
 *
 * @return void
 */
function koohe_enqueue_mega_menu_editor() {
	wp_enqueue_script(
		'koohe-mega-menu',
		KOOHE_URI . 'assets/js/mega-menu.js',
		array( 'wp-blocks', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-hooks', 'wp-i18n' ),
		koohe_asset_version( 'assets/js/mega-menu.js' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'koohe_enqueue_mega_menu_editor' );


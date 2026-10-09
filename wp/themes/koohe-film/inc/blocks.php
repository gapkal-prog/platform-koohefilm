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
 * راهبری واقعی وصل می‌کند تا مدیریت مگامنو کاملاً از پیشخوان وردپرس
 * (ظاهر ← ویرایشگر ← فهرست راهبری) انجام شود.
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
 * آیتم فهرست راهبری وردپرس فرزند نمی‌پذیرد (برچسب و توضیح دارد)، پس
 * بخش‌های دیگر همان‌جا در خروجی رندر تزریق می‌شوند: پوستر به‌عنوان
 * `<img>` واقعی داخل پیوند و سطر سوم به‌صورت `.koohe-mega-feature__cta`.
 * «تماشای X» از برچسب همان آیتم ساخته می‌شود، پس اگر مدیر کارت را به
 * اثر دیگری ببندد، متن هم خودبه‌خود عوض می‌شود.
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

	/*
	 * برچسب کنش کارت ویژه از تنظیمات («کشف داستان» پیش‌فرض) و سطر سوم
	 * «تماشای ‹نام اثر›» می‌ماند — مثل مرجع. قالب متن را با فیلتر
	 * `koohe_mega_feature_cta` هم قابل تغییر می‌کند.
	 */
	$cta_label = class_exists( '\ManaCore\Core\Mega_Menu' )
		? (string) \ManaCore\Core\Mega_Menu::settings()['cta_label']
		: __( 'کشف داستان', 'koohe-film' );

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
			 * @param string $label نام اثر.
			 * @param string $cta   برچسب تنظیم‌شده‌ی کنش.
			 */
			$cta_text = (string) apply_filters(
				'koohe_mega_feature_cta',
				sprintf( /* translators: %s: نام اثر */ __( 'تماشای %s', 'koohe-film' ), $label ),
				$cta_label
			);

			$cta = '<span class="koohe-mega-feature__cta">' . esc_html( $cta_text ) . '</span>';

			/* پوستر: شناسه‌ی اثر از نشانی همان پیوندِ کارت. */
			$post_id = preg_match( '#href="([^"]+)"#', $matches[1], $href ) ? (int) url_to_postid( $href[1] ) : 0;

			/*
			 * پوستر با زنجیره‌ی مشترک افزونه («تصویر شاخص → backdrop →
			 * poster») خوانده می‌شود؛ پیش‌تر فقط تصویر شاخص وردپرس بود و
			 * آثار دارای پوستر دستی بی‌تصویر می‌ماندند.
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
 * متغیر تعداد ستون‌های شبکه‌ی ژانرها در مگامنو.
 *
 * تنظیم «تعداد ستون‌های شبکه‌ی ژانرها» (تب مگامنو) پیش‌تر فقط در شورت‌کد
 * اثر داشت و فهرست راهبری قالب همیشه سه‌ستونه می‌ماند. این فیلتر متغیر
 * `--koohe-mega-columns` را روی `<li>` گروه می‌گذارد؛ شبکه‌ی CSS
 * (`grid-template-columns`) آن را می‌خواند (متغیرهای سفارشی ارث‌بری می‌شوند).
 *
 * @param string $block_content محتوای رندرشده‌ی بلوک.
 * @param array  $block         داده‌ی بلوک.
 * @return string
 */
function koohe_mega_columns_var( $block_content, $block ) {
	if ( ! is_string( $block_content ) || false === strpos( $block_content, 'koohe-mega-genres' ) ) {
		return $block_content;
	}

	if ( ! class_exists( '\\ManaCore\\Core\\Mega_Menu' ) ) {
		return $block_content;
	}

	$settings = \ManaCore\Core\Mega_Menu::settings();
	$columns  = isset( $settings['columns'] ) ? max( 2, min( 4, (int) $settings['columns'] ) ) : 3;
	$var      = '--koohe-mega-columns:' . $columns;

	$content = preg_replace_callback(
		'/(<li\b[^>]*class="[^"]*koohe-mega-genres[^"]*"[^>]*>)/',
		static function ( $matches ) use ( $var ) {
			$tag = (string) $matches[1];

			if ( false !== strpos( $tag, 'style="' ) ) {
				/* ویژگی style موجود است؛ متغیر به‌صورت الحاقی اضافه می‌شود. */
				$tag = (string) preg_replace( '/style="([^"]*)"/', 'style="$1;' . $var . '"', $tag, 1 );
			} else {
				$tag = str_replace( '>', ' style="' . $var . '">', $tag );
			}

			return $tag;
		},
		$block_content
	);

	return is_string( $content ) ? $content : $block_content;
}
add_filter( 'render_block', 'koohe_mega_columns_var', 10, 2 );

/**
 * نقطه‌ی کنار آیتم «برنامه پخش» در ناوبری.
 *
 * مرجع این نقطه را داخل خودِ لنگر گذاشته است:
 *
 *     <a href="schedule.html">برنامه پخش<span class="nav-dot"></span></a>
 *
 * بلوک `core/navigation-link` فقط برچسب متنی می‌پذیرد، پس همان نقطه با
 * فیلتر رندر تزریق می‌شود؛ آیتم با کلاس `koohe-nav-dot` نشانه‌گذاری شده
 * است تا مدیر بتواند در ویرایشگر فهرست آن را جابه‌جا یا حذف کند و این
 * فیلتر بی‌اثر بماند.
 *
 * نقطه تزئینی است (`aria-hidden`) و در کشوی موبایل تکرار نمی‌شود، چون
 * کشو مارک‌آپ خودش را می‌سازد و مرجع هم این نقطه را در کشو ندارد.
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
 * ساخت فهرست راهبری پیش‌فرض هنگام فعال‌سازی قالب.
 *
 * آیتم‌ها همان ساختار مرجع است: «دسته‌بندی‌ها» یک زیرمنوی مگامنوی
 * `koohe-mega` با پیوند ترم‌های واقعی ژانر است، پس مدیر می‌تواند همان
 * فهرست را در پیشخوان ویرایش کند. اگر مدیر فهرست را حذف کند، دیگر
 * ساخته نمی‌شود (`koohe_nav_seeded`).
 */
function koohe_primary_navigation_markup() {
	/*
	 * `$class` برای آیتم‌های «دسترسی سریع» لازم است: همان کلاس در سمت
	 * قالب ظاهرشان را از چیپ‌های ژانر جدا می‌کند (`.mega-quick` مرجع).
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
	 * `$group` = زیرمنوی تودرتو؛ همان ظرف‌های مرجع
	 * (`.mega-genres` و `.mega-quick`). برچسب گروه در پنل دسکتاپ قیافه
	 * می‌گیرد (متن سرستون/eyebrow) و در کشوی موبایل ردیف نمی‌شود.
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

	/*
	 * ترتیب آیتم‌ها همان ترتیب مرجع است: «خانه» نخست (در RTL سمت راست)
	 * و بعد «دسته‌بندی‌ها» که زیرمنوی مگامنو را باز می‌کند.
	 */
	$before_mega = array(
		$link( __( 'خانه', 'koohe-film' ), home_url( '/' ) ),
	);

	/*
	 * «برنامه پخش» عیناً جای مرجع است: بعد از «دسته‌بندی‌ها» و پیش از
	 * «اشتراک»، با نشانه‌ی نقطه‌ای (`.nav-dot`) که مرجع هم روی همین
	 * آیتم گذاشته است. کلاس `koohe-nav-dot` نشانه‌ی درجِ همان نقطه در
	 * فیلتر `koohe_nav_item_dot` است.
	 */
	$after_mega = array(
		$link( __( 'برنامه پخش', 'koohe-film' ), home_url( '/schedule/' ), 'custom', '', 0, 'koohe-nav-dot' ),
		$link( __( 'اشتراک', 'koohe-film' ), home_url( '/subscribe/' ) ),
	);

	/*
	 * داده‌ی مگامنو از سرویس مشترک افزونه می‌آید (`Mega_Menu`): نشانی مرکز
	 * دسته‌بندی‌ها، تاکسونومی/تعداد ژانرها، متن‌های سرستون و کارت ویژه.
	 * اگر افزونه‌ی هسته فعال نباشد، همان رفتار پیشین با پیش‌فرض‌های امن
	 * اجرا می‌شود تا قالب به‌تنهایی هم کار کند.
	 */
	$mega = class_exists( '\ManaCore\Core\Mega_Menu' )
		? \ManaCore\Core\Mega_Menu::settings()
		: array(
			'enabled'       => true,
			'taxonomy'      => 'genre',
			'number'        => 12,
			'hub_url'       => '',
			'eyebrow'       => __( 'یک دنیا انتخاب', 'koohe-film' ),
			'title'         => __( 'حال‌وهوای امشبت چیه؟', 'koohe-film' ),
			'genre_label'   => __( 'ژانرها', 'koohe-film' ),
			'quick_label'   => __( 'به انتخاب سینورا', 'koohe-film' ),
			'feature_label' => __( 'انتخاب ویژه این هفته', 'koohe-film' ),
			'cta_label'     => __( 'کشف داستان', 'koohe-film' ),
			'featured_id'   => 0,
			'show_feature'  => true,
		);

	$hub = class_exists( '\ManaCore\Core\Mega_Menu' )
		? \ManaCore\Core\Mega_Menu::hub_url()
		: home_url( '/categories-hub/' );

	/* مگامنوی ژانرها: زیرمنو با ترم‌های واقعی. */
	$genre_links = array();

	if ( class_exists( '\ManaCore\Core\Mega_Menu' ) ) {
		foreach ( \ManaCore\Core\Mega_Menu::menu_terms( (string) $mega['taxonomy'], (int) $mega['number'] ) as $genre ) {
			$genre_links[] = $link( $genre->name, (string) get_term_link( $genre ), 'taxonomy', (string) $genre->taxonomy, (int) $genre->term_id, 'koohe-mega-genre' );
		}
	} else {
		$genres = get_terms(
			array(
				'taxonomy'   => 'genre',
				'hide_empty' => false,
				'number'     => 12,
			)
		);

		if ( ! is_wp_error( $genres ) ) {
			foreach ( $genres as $genre ) {
				$genre_links[] = $link( $genre->name, (string) get_term_link( $genre ), 'taxonomy', 'genre', (int) $genre->term_id, 'koohe-mega-genre' );
			}
		}
	}

	if ( ! $genre_links ) {
		/* پنل خالی نماند تا وقتی ترمی ساخته نشده است. */
		$genre_links[] = $link( __( 'همه دسته‌بندی‌ها', 'koohe-film' ), $hub, 'custom', '', 0, 'koohe-mega-genre' );
	}

	/*
	 * «دسترسی سریع» ستون میانی: داده از سرویس مشترک افزونه می‌آید
	 * (`Mega_Menu::quick_links()`) تا متن‌ها از پنل مدیریت قابل تنظیم
	 * باشند و قالب/شورت‌کد/پنل یک منبع حقیقت داشته باشند. اگر افزونه
	 * فعال نباشد، همان فهرست پیشینی ساخته می‌شود تا قالب تنها بماند.
	 */
	$quick_links = array();

	/*
	 * نگاشت صریح کلید → کلاس ظاهری. کلاس‌ها در CSS به‌ازای هر ردیف آیکون
	 * اختصاصی دارند (`.koohe-mega-quick--rating` و…)، پس نگاشت صریح
	 * می‌ماند تا قرارداد ظاهری پایدار و قابل جست‌وجو بماند.
	 */
	$quick_classes = array(
		'rating' => 'koohe-mega-quick koohe-mega-quick--rating',
		'newest' => 'koohe-mega-quick koohe-mega-quick--newest',
		'korean' => 'koohe-mega-quick koohe-mega-quick--korean',
		'cast'   => 'koohe-mega-quick koohe-mega-quick--cast',
	);

	if ( class_exists( '\ManaCore\Core\Mega_Menu' ) ) {
		foreach ( \ManaCore\Core\Mega_Menu::quick_links() as $item ) {
			$key    = isset( $item['key'] ) ? (string) $item['key'] : '';
			$kind   = 'custom';
			$type   = '';
			$id     = 0;

			/* ردیف «کره‌ای» به آرشیو ترم کشور اشاره می‌کند، نه پیوند دلخواه. */
			if ( 'korean' === $key ) {
				$korean = get_terms(
					array(
						'taxonomy'   => 'country',
						'hide_empty' => false,
						'number'     => 1,
						'name__like' => 'کره',
					)
				);

				if ( ! is_wp_error( $korean ) && $korean ) {
					$kind = 'taxonomy';
					$type = 'country';
					$id   = (int) $korean[0]->term_id;
				}
			}

			$quick_links[] = $link(
				(string) $item['label'],
				(string) $item['url'],
				$kind,
				$type,
				$id,
				$quick_classes[ $key ] ?? 'koohe-mega-quick'
			);
		}
	}

	if ( ! $quick_links ) {
		$browse = get_post_type_archive_link( 'movie' );
		$browse = $browse ? $browse : home_url( '/' );

		$quick_links = array(
			$link( __( 'بالاترین امتیازها', 'koohe-film' ), add_query_arg( 'mc_sort', 'rating', $browse ), 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--rating' ),
			$link( __( 'تازه‌های کوهه', 'koohe-film' ), add_query_arg( 'mc_sort', 'newest', $browse ), 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--newest' ),
		);

		$person_archive = get_post_type_archive_link( 'person' );

		if ( $person_archive ) {
			$quick_links[] = $link( __( 'بازیگران و کارگردان‌ها', 'koohe-film' ), $person_archive, 'custom', '', 0, 'koohe-mega-quick koohe-mega-quick--cast' );
		}
	}

	/*
	 * کارت ویژه‌ی ستون آخر (`.mega-feature` مرجع). متن کوتاه روی
	 * `description` آیتم راهبری می‌نشیند و تیتر همان برچسب آیتم است.
	 * اثر ویژه از تنظیمات مدیر می‌آید (`mega_featured_id`) و در نبودش
	 * سرویس افزونه تازه‌ترین اثر دارای پوستر را انتخاب می‌کند؛ اگر افزونه
	 * نباشد، بهترین امتیاز فیلم‌ها (رفتار پیشین قالب) به کار می‌رود.
	 */
	$feature_title = __( 'برترین‌های کوهه', 'koohe-film' );
	$feature_url   = $browse;

	if ( class_exists( '\ManaCore\Core\Mega_Menu' ) ) {
		$card = \ManaCore\Core\Mega_Menu::featured_card();

		if ( ! empty( $card['id'] ) ) {
			$feature_title = (string) $card['title'];
			$feature_url   = (string) $card['url'];
		}
	} else {
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
	}

	/*
	 * ساختار پنل، سه ستونِ مرجع:
	 *
	 *   ستون ۱ — سرستون پنل + گروه «ژانرها» (`.mega-genres`، شبکه‌ی سه‌ستونه)
	 *   ستون ۲ — گروه «به انتخاب سینورا» (`.mega-quick`، برچسب گروه = eyebrow)
	 *   ستون ۳ — کارت ویژه (`.mega-feature`)
	 */
	$submenu = array(
		$link( (string) $mega['eyebrow'], $hub, 'custom', '', 0, 'koohe-mega-eyebrow' ),
		$link( (string) $mega['title'], $hub, 'custom', '', 0, 'koohe-mega-title' ),
		$group( (string) $mega['genre_label'], $hub, 'koohe-mega-genres', $genre_links ),
		$group( (string) $mega['quick_label'], $hub, 'koohe-mega-quick', $quick_links ),
	);

	/*
	 * کارت ویژه (ستون سوم) فقط وقتی «نمایش کارت ویژه» در تنظیمات روشن باشد
	 * ساخته می‌شود؛ در غیر این صورت ستون سوم به‌کلی حذف می‌شود.
	 */
	if ( ! isset( $mega['show_feature'] ) || ! empty( $mega['show_feature'] ) ) {
		/*
		 * برچسب = نام اثر (`.mega-feature strong` مرجع) و توضیح = سطر
		 * ریز بالا؛ سطر سوم («تماشای …» + شِوران) را فیلتر رندر تزریق
		 * می‌کند، چون آیتم راهبری فرزند نمی‌پذیرد.
		 */
		$submenu[] = $link(
			$feature_title,
			$feature_url,
			'custom',
			'',
			0,
			'koohe-mega-feature',
			(string) $mega['feature_label']
		);
	}

	/*
	 * با خاموش‌کردن «پنل مگامنو» در تنظیمات، آیتم «دسته‌بندی‌ها» یک
	 * پیوند ساده به مرکز دسته‌بندی‌ها می‌شود (بدون زیرمنو).
	 */
	$mega_item = isset( $mega['enabled'] ) && empty( $mega['enabled'] )
		? $link( __( 'دسته‌بندی‌ها', 'koohe-film' ), $hub )
		: '<!-- wp:navigation-submenu ' . wp_json_encode(
			array(
				'label'     => __( 'دسته‌بندی‌ها', 'koohe-film' ),
				'url'       => $hub,
				'kind'      => 'custom',
				'className' => 'koohe-mega',
			),
			JSON_UNESCAPED_UNICODE
		) . ' -->'
		. implode( '', $submenu )
		. '<!-- /wp:navigation-submenu -->';

	$content = implode( '', $before_mega )
		. $mega_item
		. implode( '', $after_mega )
		. $link( __( 'درباره ما', 'koohe-film' ), home_url( '/about/' ) )
		. $link( __( 'تماس با ما', 'koohe-film' ), home_url( '/contact/' ) );

	return $content;
}

/**
 * ساخت فهرست راهبری پیش‌فرض هنگام فعال‌سازی قالب.
 *
 * آیتم‌ها همان ساختار مرجع است: «دسته‌بندی‌ها» یک زیرمنوی مگامنوی
 * `koohe-mega` با پیوند ترم‌های واقعی ژانر است، پس مدیر می‌تواند همان
 * فهرست را در پیشخوان ویرایش کند. اگر مدیر فهرست را حذف کند، دیگر
 * ساخته نمی‌شود (`koohe_nav_seeded`).
 */
function koohe_seed_primary_navigation() {
	if ( get_option( 'koohe_nav_seeded' ) ) {
		return;
	}

	if ( koohe_primary_navigation_id() ) {
		update_option( 'koohe_nav_seeded', 1 );
		return;
	}

	$content = koohe_primary_navigation_markup();

	$menu_id = wp_insert_post(
		array(
			'post_title'   => __( 'فهرست اصلی', 'koohe-film' ),
			'post_name'    => 'primary',
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			/* `wp_insert_post` خودش wp_unslash می‌کند؛ پس پیش از ذخیره wp_slash لازم است. */
			'post_content' => wp_slash( $content ),
		)
	);

	if ( $menu_id && ! is_wp_error( $menu_id ) ) {
		set_theme_mod( 'koohe_navigation_id', (int) $menu_id );
		update_option( 'koohe_nav_seeded', 1 );
		update_option( 'koohe_nav_seed_hash', md5( $content ) );
	}
}
add_action( 'after_switch_theme', 'koohe_seed_primary_navigation' );

/**
 * نسخه‌ی ساختار فهرست راهبریِ خودِ قالب.
 *
 * با هر تغییری در `koohe_primary_navigation_markup()` این عدد را یکی
 * بالا ببرید. دلیلی که این عدد لازم است: تازه‌سازی فهرست با نشانه‌ی
 * «ترم‌ها ساخته شده‌اند» یک‌بار برای همیشه خاموش می‌شد و آیتم تازه‌ی
 * قالب (مثلاً «برنامه پخش») هرگز به سایت‌هایی که از قبل نصب بودند
 * نمی‌رسید. اکنون تازه‌سازی هم به «محتوای ذخیره‌شده دست‌نخورده مانده»
 * وابسته است و هم به این نسخه.
 *
 * @var int
 */
const KOOKE_NAV_MARKUP_VERSION = 3;

/**
 * تازه‌سازی یک‌باره‌ی فهرست راهبری‌ای که خود قالب ساخته است.
 *
 * چرا لازم است؟ قالب در «فعال‌سازی» فهرست را می‌سازد، ولی سایت تازه هنوز
 * نه ترم ژانری دارد و نه اثری؛ نتیجه این بود که پنل مگامنو تا همیشه بدون
 * چیپ‌های ژانر و بدون کارت ویژه‌ی تصویری می‌ماند (سنجیده‌شده: پنل ۲۰۷px
 * در برابر ۲۹۲px مرجع، صفر چیپ در برابر ۱۲ چیپ). حالا اگر محتوا بعد از
 * فعال‌سازی اضافه شود، فهرست **یک بار** بازسازی می‌شود.
 *
 * دو شرط ایمنی: فهرستی که مدیر دست‌کاری کرده باشد هرگز بازنویسی نمی‌شود
 * (اثر انگشت محتوا با آنچه خودمان نوشتیم مقایسه می‌شود) و بازسازی تنها
 * وقتی رخ می‌دهد که چیپ‌های ژانر جایش خالی باشد.
 *
 * @return void
 */
function koohe_refresh_seeded_navigation() {
	if ( ! get_option( 'koohe_nav_seeded' ) ) {
		return;
	}

	/*
	 * اگر فهرست با همین نسخه‌ی ساختار و با ترم‌های موجود ساخته شده باشد،
	 * دیگر کوئری اضافه‌ای نمی‌زنیم.
	 */
	$needs_terms   = ! get_option( 'koohe_nav_seed_terms' );
	$needs_version = (int) get_option( 'koohe_nav_seed_version', 0 ) !== KOOKE_NAV_MARKUP_VERSION;

	/*
	 * اثر انگشت تنظیمات مگامنو: با هر تغییر متن/تعداد/کارت ویژه در پنل
	 * مدیریت، فهرست یک‌بار بازسازی می‌شود تا متن‌های تازه در سایت دیده
	 * شوند. نشانه‌ی محتوایی (hash) همچنان از دست‌کاری دستی مدیر محافظت
	 * می‌کند: فهرستِ ویرایش‌شده بازنویسی نمی‌شود.
	 */
	$fingerprint     = koohe_nav_settings_fingerprint();
	$needs_settings  = (string) get_option( 'koohe_nav_seed_settings', '' ) !== $fingerprint;

	if ( ! $needs_terms && ! $needs_version && ! $needs_settings ) {
		return;
	}

	$menu_id = (int) get_theme_mod( 'koohe_navigation_id', 0 );

	if ( ! $menu_id ) {
		return;
	}

	$menu = get_post( $menu_id );

	if ( ! $menu || 'wp_navigation' !== $menu->post_type || 'publish' !== $menu->post_status ) {
		return;
	}

	$content = (string) $menu->post_content;

	$hash = (string) get_option( 'koohe_nav_seed_hash' );

	if ( '' !== $hash ) {
		if ( md5( $content ) !== $hash ) {
			return; // مدیر ویرایشش کرده است؛ دست نمی‌زنیم.
		}
	} elseif ( false === strpos( $content, 'یک دنیا انتخاب' ) ) {
		/*
		 * سایت‌هایی که با نسخه‌ی پیشین قالب فهرست را ساخته‌اند اثر انگشت
		 * ذخیره‌شده ندارند؛ در آن حالت تنها فهرستی بازسازی می‌شود که
		 * نشانه‌ی خودِ قالب را داشته باشد.
		 */
		return;
	}

	if ( false === strpos( $content, 'koohe-mega' ) ) {
		return; // مگامنویی در کار نیست.
	}

	$genres = get_terms(
		array(
			'taxonomy'   => 'genre',
			'hide_empty' => false,
			'number'     => 1,
		)
	);

	if ( is_wp_error( $genres ) || ! $genres ) {
		return; // هنوز ترمی نیست؛ بازسازی هم چیزی عوض نمی‌کند.
	}

	$fresh = koohe_primary_navigation_markup();

	if ( $fresh !== $content ) {
		wp_update_post(
			array(
				'ID'           => $menu_id,
				'post_content' => wp_slash( $fresh ),
			)
		);

		update_option( 'koohe_nav_seed_hash', md5( $fresh ) );
	}

	/* حالا فهرست با ترم‌های واقعی و همین نسخه‌ی ساختار ساخته شده است. */
	update_option( 'koohe_nav_seed_terms', 1 );
	update_option( 'koohe_nav_seed_version', KOOKE_NAV_MARKUP_VERSION );
	update_option( 'koohe_nav_seed_settings', $fingerprint );
}

/**
 * اثر انگشت تنظیمات مؤثر بر مگامنو.
 *
 * فقط کلیدهایی که در ساخت فهرست راهبری مصرف می‌شوند سنجیده می‌شوند؛
 * تغییر یک تنظیم بی‌ربط نباید فهرست را بازسازی کند.
 *
 * @return string
 */
function koohe_nav_settings_fingerprint() {
	if ( ! class_exists( '\ManaCore\Core\Mega_Menu' ) ) {
		return 'theme-only';
	}

	$settings = \ManaCore\Core\Mega_Menu::settings();

	$relevant = array(
		'enabled',
		'taxonomy',
		'number',
		'columns',
		'hub_url',
		'eyebrow',
		'title',
		'genre_label',
		'quick_label',
		'feature_label',
		'featured_id',
		'rating_label',
		'newest_label',
		'korean_label',
		'cast_label',
		'show_feature',
		'show_rating',
		'show_newest',
		'show_korean',
		'show_cast',
	);

	$slice = array();
	foreach ( $relevant as $key ) {
		$slice[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	return md5( (string) wp_json_encode( $slice ) );
}

/**
 * بازسازی اجباری فهرست راهبری مگامنو (ابزار پنل مدیریت).
 *
 * برخلاف بازسازی خودکار، این یکی از محافظ «فهرست دست‌کاری‌شده» می‌گذرد؛
 * چون مدیر خودش دکمه را زده و انتظار ساخت دوباره دارد. اگر افزونه‌ی
 * مگامنو در دسترس نباشد یا فهرست ساخته‌شده‌ی قالب وجود نداشته باشد،
 * `false` برمی‌گردد تا پنل پیام گویا بدهد.
 *
 * @return bool
 */
function koohe_rebuild_navigation() {
	if ( ! function_exists( 'koohe_primary_navigation_markup' ) ) {
		return false;
	}

	$menu_id = (int) get_theme_mod( 'koohe_navigation_id', 0 );

	/*
	 * اگر فهرست ساخته‌شده وجود ندارد، همان مسیر «ساخت نخستین» اجرا
	 * می‌شود (فقط اگر قبلاً چیزی ساخته نشده باشد).
	 */
	if ( ! $menu_id ) {
		if ( koohe_primary_navigation_id() ) {
			return true;
		}

		koohe_seed_primary_navigation();

		return true;
	}

	$menu = get_post( $menu_id );

	if ( ! $menu || 'wp_navigation' !== $menu->post_type ) {
		return false;
	}

	$content = koohe_primary_navigation_markup();

	wp_update_post(
		array(
			'ID'           => $menu_id,
			'post_content' => wp_slash( $content ),
		)
	);

	update_option( 'koohe_nav_seed_hash', md5( $content ) );
	update_option( 'koohe_nav_seed_terms', 1 );
	update_option( 'koohe_nav_seed_version', KOOKE_NAV_MARKUP_VERSION );
	update_option( 'koohe_nav_seed_settings', koohe_nav_settings_fingerprint() );

	return true;
}
add_action( 'init', 'koohe_refresh_seeded_navigation', 30 );


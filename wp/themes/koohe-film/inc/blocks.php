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

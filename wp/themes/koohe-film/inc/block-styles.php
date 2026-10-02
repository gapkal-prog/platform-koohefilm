<?php
/**
 * سبک‌های بلوک اختصاصی قالب.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت سبک‌های بلوک.
 */
function koohe_register_block_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	$styles = array(
		array(
			'block' => 'core/group',
			'name'  => 'koohe-card',
			'label' => __( 'کارت کوه فیلم', 'koohe-film' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'koohe-glass',
			'label' => __( 'شیشه‌ای', 'koohe-film' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'koohe-outline',
			'label' => __( 'کادر ساده', 'koohe-film' ),
		),
		array(
			'block' => 'core/columns',
			'name'  => 'koohe-gapless',
			'label' => __( 'بدون فاصله', 'koohe-film' ),
		),
		array(
			'block' => 'core/heading',
			'name'  => 'koohe-section',
			'label' => __( 'عنوان بخش', 'koohe-film' ),
		),
		array(
			'block' => 'core/heading',
			'name'  => 'koohe-underline',
			'label' => __( 'زیرخط تأکید', 'koohe-film' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'koohe-poster',
			'label' => __( 'پوستر (۲:۳)', 'koohe-film' ),
		),
		array(
			'block' => 'core/image',
			'name'  => 'koohe-rounded',
			'label' => __( 'گوشه‌گرد', 'koohe-film' ),
		),
		array(
			'block' => 'core/button',
			'name'  => 'koohe-ghost',
			'label' => __( 'شبح', 'koohe-film' ),
		),
		array(
			'block' => 'core/button',
			'name'  => 'koohe-soft',
			'label' => __( 'ملایم', 'koohe-film' ),
		),
		array(
			'block' => 'core/separator',
			'name'  => 'koohe-dots',
			'label' => __( 'نقطه‌چین', 'koohe-film' ),
		),
		array(
			'block' => 'core/list',
			'name'  => 'koohe-check',
			'label' => __( 'تیک‌دار', 'koohe-film' ),
		),
		array(
			'block' => 'core/post-terms',
			'name'  => 'koohe-pills',
			'label' => __( 'قرصی', 'koohe-film' ),
		),
		array(
			'block' => 'core/query',
			'name'  => 'koohe-rail',
			'label' => __( 'ردیف افقی', 'koohe-film' ),
		),

		/*
		 * تنوع چیدمان برای حلقه‌ی کوئری هسته. هر سبک تنها با یک کلاس
		 * روی ظرفِ بلوک کار می‌کند و نشانه‌گذاری الگوها را تغییر نمی‌دهد،
		 * پس روی هر الگوی موجود هم قابل اعمال است.
		 */
		array(
			'block' => 'core/query',
			'name'  => 'koohe-showcase',
			'label' => __( 'ویترین (اولی بزرگ)', 'koohe-film' ),
		),
		array(
			'block' => 'core/query',
			'name'  => 'koohe-ranking',
			'label' => __( 'رتبه‌بندی شماره‌دار', 'koohe-film' ),
		),
		array(
			'block' => 'core/query',
			'name'  => 'koohe-listing',
			'label' => __( 'فهرست افقی (تصویر کنار متن)', 'koohe-film' ),
		),
		array(
			'block' => 'core/query',
			'name'  => 'koohe-overlay',
			'label' => __( 'متن روی تصویر', 'koohe-film' ),
		),
		array(
			'block' => 'core/query',
			'name'  => 'koohe-compact',
			'label' => __( 'فشرده', 'koohe-film' ),
		),

		// تنوع گروه برای جعبه‌های محتوایی.
		array(
			'block' => 'core/group',
			'name'  => 'koohe-elevated',
			'label' => __( 'برجسته (سایه‌دار)', 'koohe-film' ),
		),
		array(
			'block' => 'core/group',
			'name'  => 'koohe-accent-bar',
			'label' => __( 'نوار تأکید کناری', 'koohe-film' ),
		),
	);

	foreach ( $styles as $style ) {
		register_block_style( $style['block'], $style );
	}
}
add_action( 'init', 'koohe_register_block_styles' );

/**
 * ثبت تنوع بلوک‌ها (Block Variations) در ویرایشگر.
 */
function koohe_block_variations_script() {
	$path = KOOHE_DIR . 'assets/js/editor.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'koohe-editor',
		KOOHE_URI . 'assets/js/editor.js',
		array( 'wp-blocks', 'wp-dom-ready', 'wp-i18n' ),
		koohe_asset_version( 'assets/js/editor.js' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'koohe_block_variations_script' );

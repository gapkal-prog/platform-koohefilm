<?php
/**
 * داده‌ی نمایشی طرح‌های اشتراک — هم‌سان با کاتالوگ مرجع «سینورا».
 *
 * چرا در قالب و نه در افزونه؟
 * افزونه‌ی `manacore-subscriptions` قابلیت را می‌سازد (ساختار کامل کارت:
 * نشان، تگ‌لاین، قیمت پیشین و تخفیف، معادل ماهانه، فهرست ویژگی‌ها) و منبع
 * حقیقتِ قیمت در نصب واقعی، محصول ووکامرسِ نگاشت‌شده است. اما «چه قیمتی،
 * چه تگ‌لاینی، چه ویژگی‌هایی» داده‌ی همین سایت است؛ پس از فیلتر
 * `manacore_subs_levels` — همان نقطه‌ی توسعه‌ای که افزونه در اختیار
 * می‌گذارد — تزریق می‌شود تا:
 *
 *   • صفحه‌ی «اشتراک» با مرجع پاریتی داشته باشد (سه طرح یک‌ماهه/سه‌ماهه/
 *     یک‌ساله با همان قیمت‌ها و همان ویژگی‌ها)؛
 *   • نصب واقعی بتواند این داده را با یک `remove_filter()` کنار بگذارد،
 *     یا با نگاشت محصول ووکامرس (که اولویت دارد) بازنویسی کند.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * ویژگی‌های مشترکِ همه‌ی طرح‌ها، با «اعتبار … ماهه» مخصوص هر طرح.
 *
 * @param int $months طول دوره‌ی طرح به ماه.
 * @return array<string>
 */
function koohe_demo_plan_features( $months ) {
	return array(
		__( 'پخش و دانلود نمونه‌ها در همه کیفیت‌ها', 'koohe-film' ),
		__( 'لیست تماشا و تاریخچه همگام‌شده', 'koohe-film' ),
		__( 'تحلیل سلیقه و پیشنهادهای مخصوص تو', 'koohe-film' ),
		__( 'پروفایل و تنظیمات شخصی', 'koohe-film' ),
		sprintf(
			/* translators: %s: number of months. */
			__( 'اعتبار %s ماهه آزمایشی', 'koohe-film' ),
			function_exists( 'manacore_fa_digits' ) ? manacore_fa_digits( (string) $months ) : (string) $months
		),
	);
}

/**
 * تزریق داده‌ی نمایشی سه طرح روی سطوح اشتراک.
 *
 * ترتیب و نگاشت: `basic` → یک‌ماهه، `plus` → سه‌ماهه (طرح محبوب)،
 * `vip` → یک‌ساله. نامک‌ها دست‌نخورده می‌مانند، چون دروازه‌بانی محتوا
 * (متای «حداقل سطح اشتراک») با همین نامک‌ها کار می‌کند.
 *
 * @param array $levels سطوح اشتراک.
 * @return array
 */
function koohe_demo_plan_levels( $levels ) {
	$demo = array(
		'basic' => array(
			'title'      => __( 'اشتراک یک‌ماهه', 'koohe-film' ),
			'label'      => __( 'یک‌ماهه', 'koohe-film' ),
			'tag'        => __( 'شروع یک داستان تازه', 'koohe-film' ),
			'amount'     => 89000,
			'old_amount' => 119000,
			'months'     => 1,
			'icon'       => 'grid',
			'features'   => koohe_demo_plan_features( 1 ),
		),
		'plus'  => array(
			'title'      => __( 'اشتراک سه‌ماهه', 'koohe-film' ),
			'label'      => __( 'سه‌ماهه', 'koohe-film' ),
			'tag'        => __( 'انتخاب محبوب سینورایی‌ها', 'koohe-film' ),
			'amount'     => 229000,
			'old_amount' => 357000,
			'months'     => 3,
			'icon'       => 'crown',
			'ribbon'     => '✦ ' . __( 'انتخاب محبوب', 'koohe-film' ),
			'featured'   => true,
			'features'   => koohe_demo_plan_features( 3 ),
		),
		'vip'   => array(
			'title'      => __( 'اشتراک یک‌ساله', 'koohe-film' ),
			'label'      => __( 'یک‌ساله', 'koohe-film' ),
			'tag'        => __( 'یک سال، هزاران داستان', 'koohe-film' ),
			'amount'     => 699000,
			'old_amount' => 1428000,
			'months'     => 12,
			'icon'       => 'circle',
			'features'   => koohe_demo_plan_features( 12 ),
		),
	);

	foreach ( $demo as $slug => $data ) {
		if ( isset( $levels[ $slug ] ) ) {
			// داده‌ی نمایشی، برچسب و وزنِ ثبت‌شده را بازنویسی نمی‌کند.
			$levels[ $slug ] = array_merge( $levels[ $slug ], $data );
		}
	}

	return $levels;
}
add_filter( 'manacore_subs_levels', 'koohe_demo_plan_levels' );

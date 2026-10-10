<?php
/**
 * پوسته‌ی کوچکِ ماژول‌های اختیاری برای آزمون داشبورد تحلیلی.
 *
 * داشبورد باید ماژول‌های دیگر (گزارش‌های خرابی لینک، درخواست‌ها، تبلیغات) را
 * «اگر بودند» به کار ببرد و در نبودشان هم بی‌خطا کار کند. برای آزمودن مسیر
 * «هستند»، نسخه‌ی کوچکی در همان فضای نام تعریف می‌شود.
 *
 * این فایل فقط از `test-analytics.php` بارگذاری می‌شود و هیچ‌جای دیگری
 * استفاده‌ای ندارد.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

/**
 * پوسته‌ی گزارش‌های خرابی لینک.
 */
class Reports {

	/**
	 * وضعیت‌های مجاز.
	 */
	const STATUSES = array( 'new', 'fixed', 'ignored' );

	/**
	 * شمار گزارش‌ها به تفکیک وضعیت.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		return array(
			'new'     => 6,
			'fixed'   => 2,
			'ignored' => 1,
		);
	}
}

/**
 * پوسته‌ی درخواست‌های کاربران.
 */
class Requests {

	/**
	 * نوع محتوا.
	 */
	const POST_TYPE = 'manacore_request';

	/**
	 * کلید متای شمار رأی.
	 */
	const COUNT_META = 'manacore_request_votes';

	/**
	 * شمار درخواست‌ها به تفکیک وضعیت.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		return array(
			'pending' => 5,
			'publish' => 9,
			'draft'   => 1,
			'trash'   => 0,
		);
	}

	/**
	 * شمار رأی یک درخواست.
	 *
	 * @param int $id شناسه.
	 * @return int
	 */
	public static function votes( $id ) {
		return 3;
	}
}

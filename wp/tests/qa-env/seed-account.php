<?php
/**
 * کاشت **قطعی** داده‌ی «حساب کاربری» برای کاربر مدیر (متن آزمون‌های مرورگری).
 *
 * چرا جدا از `seed.php`: برگه‌ی حساب برای مهمان حالت‌های خالی را نشان
 * می‌دهد و آزمون‌های دیگر روی «لیست تماشای خالی» حساب می‌کنند؛ پس این
 * فایل فقط وقتی صریحاً اجرا شود (و همیشه با ریست) داده می‌کارد:
 *
 *   wp --path=/home/user/.cache/wp eval-file wp/tests/qa-env/seed-account.php
 *
 * داده‌ها با **همان API خودِ افزونه** کاشته می‌شوند (`Watchlist::toggle()` و
 * `Account::record_progress()` و `Account::set_pref()`) تا آزمون مسیر تولید را
 * بسنجد، نه اینکه متای خام را دست‌کاری کند. اجرای مکرر همان نتیجه را
 * می‌دهد: اول همه‌ی کلیدهای حساب کاربر پاک می‌شوند، بعد فیکسچر می‌نشیند.
 *
 * فیکسچر (هم‌ارز داده‌ای که در مرجع به `localStorage` تزریق می‌شود):
 *   لیست تماشا : ۷، ۸، ۹، ۱۰، ۱۱        (۵ اثر)
 *   تاریخچه   : ۹→۴۲٪/۵۵د · ۷→۸۸٪/۱۲۰د · ۱۰→۱۲٪/۲۰د · ۲۰→۱۰۰٪/۹۵د
 *   تمام‌شده   : ۱ اثر (۲۰)              → کارت «داستان تماشاشده»
 *   دقیقه      : ۲۹۰
 *   تنظیمات   : اعلان=خاموش · پخش خودکار=روشن · کیفیت=۷۲۰
 *
 * @package ManaCore\QA
 */

if ( ! class_exists( '\ManaCore\Core\Account' ) ) {
	echo "manacore-core فعال نیست.\n";
	return;
}

$user = get_user_by( 'login', 'admin' );

if ( ! $user ) {
	echo "کاربر admin پیدا نشد.\n";
	return;
}

$user_id = (int) $user->ID;

/* ۰) ریست: حساب کاربر همیشه از صفر ساخته می‌شود. */
foreach ( array( \ManaCore\Core\Account::PROGRESS_KEY, 'manacore_watchlist', \ManaCore\Core\Watchlist::WATCHED_KEY, \ManaCore\Core\Account::PREF_KEY ) as $key ) {
	delete_user_meta( $user_id, $key );
}

/* ۱) لیست تماشا: دو فیلم، دو سریال و یک انیمه. */
foreach ( array( 7, 8, 9, 10, 11 ) as $post_id ) {
	\ManaCore\Core\Watchlist::instance()->toggle( $post_id, $user_id );
}

/* ۲) پیشرفت تماشا: درصد و دقیقه‌ی واقعی (رکورد از همان API تولید). */
foreach ( array( 9 => array( 42.0, 55 ), 7 => array( 88.0, 120 ), 10 => array( 12.0, 20 ), 20 => array( 100.0, 95 ) ) as $post_id => $row ) {
	$result = \ManaCore\Core\Account::record_progress( $post_id, $row[0], $row[1], $user_id );

	if ( is_wp_error( $result ) ) {
		echo 'خطا در ' . $post_id . ': ' . $result->get_error_message() . "\n";
	}
}

/* ۳) تنظیمات تماشا (همان کلیدهایی که فرم تنظیمات ذخیره می‌کند). */
\ManaCore\Core\Account::set_pref( 'notifications', false, $user_id );
\ManaCore\Core\Account::set_pref( 'autoplay', true, $user_id );
\ManaCore\Core\Account::set_pref( 'quality', '720', $user_id );

$stats = \ManaCore\Core\Account::stats( $user_id );

printf(
	"account seed ok — watchlist=%d · history=%d · watched=%d · minutes=%d · genre=%s\n",
	count( \ManaCore\Core\Account::watchlist( $user_id ) ),
	count( \ManaCore\Core\Account::history( $user_id, 50 ) ),
	(int) $stats['watched'],
	(int) $stats['minutes'],
	(string) $stats['genre']
);

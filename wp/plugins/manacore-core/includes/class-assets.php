<?php
/**
 * بارگذاری فایل‌های استایل و اسکریپت.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
class Assets {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front' ) );

		/*
		 * استایل بلوک‌ها باید در بومِ ویرایشگر هم بارگذاری شود.
		 *
		 * پیش‌تر front.css تنها روی wp_enqueue_scripts صف می‌شد، پس در
		 * «ویرایشگر سایت» و ویرایشگر نوشته هیچ قاعده‌ای برای کلاس‌های
		 * manacore-* وجود نداشت: پیش‌نمایش سمت سرور HTML درست را
		 * برمی‌گرداند ولی بی‌استایل و بی‌چیدمان دیده می‌شد.
		 *
		 * enqueue_block_assets در پیشخوان برای بومِ ویرایشگر اجرا می‌شود
		 * و در فرانت هم اجرا می‌شود؛ برای همین با is_admin() تفکیک
		 * می‌کنیم تا در فرانت دوباره صف نشود.
		 */
		add_action( 'enqueue_block_assets', array( $this, 'editor_canvas' ) );
	}

	/**
	 * اسکریپت‌های پیشخوان.
	 *
	 * @param string $hook صفحه‌ی جاری.
	 */
	public function admin( $hook ) {
		$screen     = get_current_screen();
		$post_types = array_merge( manacore_title_post_types(), array( 'episode', 'collection', 'channel' ) );
		$is_editor  = $screen && in_array( $screen->post_type, $post_types, true )
			&& in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		/*
		 * صفحه‌های ManaCore و همچنین پیشخوان: ویجت «نگاه یک‌صفحه‌ای سایت»
		 * کارت‌هایش را با کلاس‌های manacore-* می‌سازد و پیش‌تر روی پیشخوان
		 * بی‌استایل (و ناخوانا) دیده می‌شد.
		 */
		$is_settings = $screen && ( false !== strpos( (string) $screen->id, 'manacore' ) || 'dashboard' === $screen->id );

		if ( ! $is_editor && ! $is_settings ) {
			return;
		}

		wp_enqueue_style(
			'manacore-admin',
			MANACORE_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			MANACORE_VERSION
		);

		if ( ! $is_editor ) {
			return;
		}

		wp_enqueue_media();

		// ابزارهای مشترک انتخابگرها؛ داده‌ی محلی‌سازی‌شده‌ی manaCoreAdmin روی همین فایل است.
		wp_enqueue_script(
			'manacore-admin-picker',
			MANACORE_URL . 'assets/js/admin-picker.js',
			array(),
			MANACORE_VERSION,
			true
		);

		wp_enqueue_script(
			'manacore-admin-links',
			MANACORE_URL . 'assets/js/admin-links.js',
			array(),
			MANACORE_VERSION,
			true
		);

		wp_enqueue_script(
			'manacore-admin-people',
			MANACORE_URL . 'assets/js/admin-people.js',
			array( 'manacore-admin-picker' ),
			MANACORE_VERSION,
			true
		);

		wp_enqueue_script(
			'manacore-admin-works',
			MANACORE_URL . 'assets/js/admin-works.js',
			array( 'manacore-admin-picker' ),
			MANACORE_VERSION,
			true
		);

		wp_localize_script(
			'manacore-admin-picker',
			'manaCoreAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'manacore/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'totalLinks'    => __( 'مجموع لینک‌ها:', 'manacore' ),
					'askSeason'     => __( 'شماره فصل را وارد کنید:', 'manacore' ),
					'askEpisodes'   => __( 'تعداد قسمت‌های این فصل:', 'manacore' ),
					'episode'       => __( 'قسمت', 'manacore' ),
					'season'        => __( 'فصل', 'manacore' ),
					'bulkHelp'      => __( 'هر خط یک لینک. قالب: عنوان | آدرس | شماره قسمت (اختیاری) | حجم (اختیاری)', 'manacore' ),
					'importedGroup' => __( 'گروه وارد شده', 'manacore' ),
					'noGroups'      => __( 'هنوز گروهی اضافه نشده است. با دکمه‌ی «افزودن گروه لینک» شروع کنید.', 'manacore' ),
					'untitledGroup' => __( 'گروه بدون عنوان', 'manacore' ),
					'roleMatch'     => __( 'نقش مطابق', 'manacore' ),
					'addFree'       => __( 'افزودن «%s» به‌عنوان نام آزاد', 'manacore' ),
					'noResults'     => __( 'عاملی پیدا نشد.', 'manacore' ),
					'searchError'   => __( 'خطا در جست‌وجو. دوباره تلاش کنید.', 'manacore' ),
					'linked'        => __( 'به صفحه‌ی عامل پیوند خورده', 'manacore' ),
					'removeName'    => __( 'حذف %s', 'manacore' ),
					'roleMissing'   => __( 'این نقش برای این نوع محتوا فعال نیست.', 'manacore' ),
					'linkUnit'      => __( 'لینک', 'manacore' ),
					'toggle'        => __( 'باز/بسته کردن', 'manacore' ),
					'moveUp'        => __( 'انتقال به بالا', 'manacore' ),
					'moveDown'      => __( 'انتقال به پایین', 'manacore' ),
					'removeGroup'   => __( 'حذف گروه', 'manacore' ),
					'confirmGroup'  => __( 'این گروه و همه‌ی لینک‌های آن حذف شود؟', 'manacore' ),
					'groupTitle'    => __( 'عنوان گروه', 'manacore' ),
					'groupTitlePh'  => __( 'مثلا: فصل ۱ — کیفیت ۱۰۸۰p', 'manacore' ),
					'quality'       => __( 'کیفیت', 'manacore' ),
					'language'      => __( 'زبان / دوبله', 'manacore' ),
					'encoder'       => __( 'انکودر', 'manacore' ),
					'size'          => __( 'حجم', 'manacore' ),
					'note'          => __( 'توضیح', 'manacore' ),
					'premiumGroup'  => __( 'فقط اعضای اشتراکی', 'manacore' ),
					'addLink'       => __( 'افزودن لینک', 'manacore' ),
					'duplicate'     => __( 'تکثیر گروه', 'manacore' ),
					'copySuffix'    => __( '(کپی)', 'manacore' ),
					'label'         => __( 'عنوان', 'manacore' ),
					'labelPh'       => __( 'قسمت ۱ / لینک مستقیم', 'manacore' ),
					'episodeNo'     => __( 'قسمت', 'manacore' ),
					'url'           => __( 'آدرس لینک', 'manacore' ),
					'type'          => __( 'نوع', 'manacore' ),
					'qualityOverride' => __( 'کیفیت', 'manacore' ),
					'inherit'       => __( 'ارث از گروه', 'manacore' ),
					'removeLink'    => __( 'حذف لینک', 'manacore' ),
					'choose'        => __( '— انتخاب —', 'manacore' ),
					'selectImage'   => __( 'انتخاب تصویر', 'manacore' ),
					'untitled'      => __( 'بدون عنوان', 'manacore' ),
					'moveUpShort'   => __( 'بالا', 'manacore' ),
					'moveDownShort' => __( 'پایین', 'manacore' ),
					'removeShort'   => __( 'حذف', 'manacore' ),
					'moveUpWork'    => __( 'انتقال «%s» به بالا', 'manacore' ),
					'moveDownWork'  => __( 'انتقال «%s» به پایین', 'manacore' ),
					'removeWork'    => __( 'حذف «%s» از مجموعه', 'manacore' ),
					'removeItem'    => __( 'حذف «%s»', 'manacore' ),
					'clearWork'     => __( 'حذف انتخاب', 'manacore' ),
					'countWorks'    => __( '%s اثر', 'manacore' ),
					'importLinked'  => __( 'افزودن آثار متصل (%s)', 'manacore' ),
					'alreadyAdded'  => __( 'در فهرست', 'manacore' ),
					'noWorks'       => __( 'اثری پیدا نشد.', 'manacore' ),
					'maxWorks'      => __( 'حداکثر %s اثر در هر مجموعه مجاز است.', 'manacore' ),
					'workAdded'     => __( '«%s» افزوده شد.', 'manacore' ),
					'workRemoved'   => __( '«%s» حذف شد.', 'manacore' ),
					'workMoved'     => __( '«%s» به موقعیت %s منتقل شد.', 'manacore' ),
					'linkedAdded'   => __( '%s اثر متصل افزوده شد.', 'manacore' ),
					'confirmClear'  => __( 'همه‌ی آثار این مجموعه از فهرست حذف شوند؟', 'manacore' ),
					'clearedWorks'  => __( 'فهرست آثار خالی شد.', 'manacore' ),
				),
			)
		);
	}

	/**
	 * استایل بلوک‌ها در بومِ ویرایشگر.
	 *
	 * تنها CSS بارگذاری می‌شود، نه JS سمت کاربر: front.js رفتارهایی مثل
	 * اسلایدر و کپی لینک را راه می‌اندازد که در ویرایشگر مزاحم ویرایش
	 * می‌شوند و با پیش‌نمایش‌های بازتولیدشده تضاد دارند.
	 */
	public function editor_canvas() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'manacore-front',
			MANACORE_URL . 'assets/css/front.css',
			array(),
			MANACORE_VERSION
		);

		wp_enqueue_style(
			'manacore-editor-canvas',
			MANACORE_URL . 'assets/css/editor-canvas.css',
			array( 'manacore-front' ),
			MANACORE_VERSION
		);
	}

	/**
	 * اسکریپت‌های سمت کاربر.
	 */
	public function front() {
		wp_enqueue_style(
			'manacore-front',
			MANACORE_URL . 'assets/css/front.css',
			array(),
			MANACORE_VERSION
		);

		wp_enqueue_script(
			'manacore-front',
			MANACORE_URL . 'assets/js/front.js',
			array(),
			MANACORE_VERSION,
			true
		);

		wp_localize_script(
			'manacore-front',
			'manaCore',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'manacore/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'loggedIn'  => is_user_logged_in(),
				'loginUrl'  => wp_login_url( get_permalink() ),

				/*
				 * نشانی کتابخانه‌ی HLS. از خودِ افزونه سرو می‌شود (نه CDN)
				 * و فقط وقتی برگه منبع `.m3u8` داشته باشد بارگذاری می‌شود.
				 * با فیلتر `manacore_hls_script_url` قابل جایگزینی است.
				 */
				'hlsUrl'    => Player::hls_script_url(),
				'i18n'      => array(
					'copied'        => __( 'کپی شد', 'manacore' ),
					'copy'          => __( 'کپی لینک', 'manacore' ),
					'loginRequired' => __( 'برای این کار باید وارد حساب کاربری شوید.', 'manacore' ),
					'added'         => __( 'به لیست تماشا اضافه شد', 'manacore' ),
					'removed'       => __( 'از لیست تماشا حذف شد', 'manacore' ),
					'error'         => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'manacore' ),
					'searching'     => __( 'در حال جستجو…', 'manacore' ),
					'noResults'     => __( 'نتیجه‌ای یافت نشد.', 'manacore' ),
					/* کنترل‌های افزودنی پلیر. */
					'playbackSpeed' => __( 'سرعت پخش', 'manacore' ),
					'pictureInPicture' => __( 'تصویر در تصویر', 'manacore' ),
					'nextEpisode'   => __( 'قسمت بعدی', 'manacore' ),
					/* گزارش خرابی لینک. */
					'reportTitle'   => __( 'گزارش خرابی لینک', 'manacore' ),
					'reportWhich'   => __( 'کدام لینک کار نمی‌کند؟', 'manacore' ),
					'reportReason'  => __( 'توضیح کوتاه (اختیاری)', 'manacore' ),
					'reportSend'    => __( 'ارسال گزارش', 'manacore' ),
					'reportGeneric' => __( 'لینک این بخش', 'manacore' ),
					'reportDone'    => __( 'گزارش ثبت شد. ممنون که اطلاع دادید!', 'manacore' ),
					/* درخواست فیلم/سریال. */
					'requestTitle'  => __( 'نام فیلم یا سریال را کامل بنویسید.', 'manacore' ),
					'requestSending'=> __( 'در حال ارسال…', 'manacore' ),
					'requestDone'   => __( 'درخواست شما ثبت شد.', 'manacore' ),
					'requestError'  => __( 'ارسال نشد؛ دوباره تلاش کنید.', 'manacore' ),
					'requestVoted'  => __( 'رأی شما ثبت شد.', 'manacore' ),
					'playNext'      => __( 'پخش قسمت بعدی', 'manacore' ),
					'cancel'        => __( 'لغو', 'manacore' ),
					/*
					 * پیام‌های برگه‌ی حساب؛ کلیدها همان کدهایی هستند که
					 * `Account_Actions` با `?notice=` برمی‌گرداند.
					 */
					'account'       => array(
						'saved'            => __( 'تغییرات ذخیره شد.', 'manacore' ),
						'error'            => __( 'ذخیره نشد؛ دوباره تلاش کنید.', 'manacore' ),
						'password-saved'   => __( 'رمز عبور تغییر کرد.', 'manacore' ),
						'password-mismatch'=> __( 'تکرار رمز جدید یکسان نیست.', 'manacore' ),
						'password-wrong'   => __( 'رمز فعلی درست نیست.', 'manacore' ),
						'avatar-saved'     => __( 'عکس پروفایل به‌روز شد.', 'manacore' ),
						'avatar-removed'   => __( 'عکس پروفایل حذف شد.', 'manacore' ),
						'avatar-too-big'   => __( 'عکس باید کوچک‌تر از ۵ مگابایت باشد.', 'manacore' ),
						'avatar-type'      => __( 'فقط تصویر PNG، JPEG یا WebP پذیرفته می‌شود.', 'manacore' ),
						'avatarMax'        => 5242880,
					),
				),
			)
		);
	}
}

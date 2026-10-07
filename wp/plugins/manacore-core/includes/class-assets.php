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
		$post_types = array_merge( manacore_title_post_types(), array( 'episode' ) );
		$is_editor  = $screen && in_array( $screen->post_type, $post_types, true )
			&& in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_settings = $screen && false !== strpos( (string) $screen->id, 'manacore' );

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

		wp_enqueue_script(
			'manacore-admin-links',
			MANACORE_URL . 'assets/js/admin-links.js',
			array(),
			MANACORE_VERSION,
			true
		);

		wp_localize_script(
			'manacore-admin-links',
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
				'i18n'      => array(
					'copied'        => __( 'کپی شد', 'manacore' ),
					'copy'          => __( 'کپی لینک', 'manacore' ),
					'loginRequired' => __( 'برای این کار باید وارد حساب کاربری شوید.', 'manacore' ),
					'added'         => __( 'به لیست تماشا اضافه شد', 'manacore' ),
					'removed'       => __( 'از لیست تماشا حذف شد', 'manacore' ),
					'error'         => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'manacore' ),
					'searching'     => __( 'در حال جستجو…', 'manacore' ),
					'noResults'     => __( 'نتیجه‌ای یافت نشد.', 'manacore' ),
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

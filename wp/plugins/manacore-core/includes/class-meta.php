<?php
/**
 * اسکیمای فیلدهای متا و ثبت آن‌ها در وردپرس/REST.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta
 */
class Meta {

	use Singleton;

	/**
	 * کش اسکیما.
	 *
	 * @var array|null
	 */
	protected $schema = null;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_meta' ), 8 );
	}

	/**
	 * اسکیمای کامل فیلدها، گروه‌بندی شده در تب‌های متاباکس.
	 *
	 * @return array
	 */
	public function schema() {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		$titles  = manacore_title_post_types();
		$serials = manacore_serial_post_types();
		$all     = array_merge( $titles, array( 'episode' ) );
		$people  = array( 'person' );

		$schema = array(

			/* ---------------- تب: اطلاعات کلی ---------------- */
			'general' => array(
				'label'  => __( 'اطلاعات کلی', 'manacore' ),
				'icon'   => 'info',
				'fields' => array(
					'manacore_original_title' => array(
						'label'       => __( 'عنوان اصلی (لاتین)', 'manacore' ),
						'type'        => 'text',
						'post_types'  => $all,
						'description' => __( 'نام اثر به زبان اصلی برای نمایش و جستجو.', 'manacore' ),
					),
					'manacore_alt_titles' => array(
						'label'       => __( 'نام‌های دیگر', 'manacore' ),
						'type'        => 'textarea',
						'post_types'  => $titles,
						'description' => __( 'هر نام در یک خط. برای بهبود جستجوی داخلی.', 'manacore' ),
					),
					'manacore_tagline' => array(
						'label'      => __( 'شعار / خط معرفی', 'manacore' ),
						'type'       => 'text',
						'post_types' => $titles,
					),
					'manacore_release_date' => array(
						'label'      => __( 'تاریخ انتشار', 'manacore' ),
						'type'       => 'date',
						'post_types' => $all,
					),
					'manacore_year' => array(
						'label'      => __( 'سال انتشار', 'manacore' ),
						'type'       => 'number',
						'post_types' => $titles,
						'attrs'      => array(
							'min' => 1888,
							'max' => 2200,
						),
					),
					'manacore_runtime' => array(
						'label'       => __( 'مدت زمان (دقیقه)', 'manacore' ),
						'type'        => 'number',
						'post_types'  => $all,
						'attrs'       => array( 'min' => 0 ),
					),
					'manacore_status' => array(
						'label'      => __( 'وضعیت پخش', 'manacore' ),
						'type'       => 'select',
						'post_types' => $titles,
						'options'    => array(
							''            => __( '— انتخاب کنید —', 'manacore' ),
							'released'    => __( 'منتشر شده', 'manacore' ),
							'airing'      => __( 'در حال پخش', 'manacore' ),
							'upcoming'    => __( 'به‌زودی', 'manacore' ),
							'ended'       => __( 'پایان یافته', 'manacore' ),
							'canceled'    => __( 'لغو شده', 'manacore' ),
							'in_production' => __( 'در حال تولید', 'manacore' ),
						),
					),
					'manacore_content_type' => array(
						'label'      => __( 'نوع محتوا (انیمه)', 'manacore' ),
						'type'       => 'select',
						'post_types' => array( 'anime' ),
						'options'    => array(
							''        => __( '— انتخاب کنید —', 'manacore' ),
							'tv'      => __( 'سریال تلویزیونی', 'manacore' ),
							'movie'   => __( 'فیلم', 'manacore' ),
							'ova'     => 'OVA',
							'ona'     => 'ONA',
							'special' => __( 'ویژه', 'manacore' ),
						),
					),
					'manacore_country' => array(
						'label'      => __( 'کشور سازنده (متن)', 'manacore' ),
						'type'       => 'text',
						'post_types' => $titles,
					),
					'manacore_spoken_languages' => array(
						'label'      => __( 'زبان‌های اثر', 'manacore' ),
						'type'       => 'text',
						'post_types' => $titles,
					),
					'manacore_is_featured' => array(
						'label'       => __( 'نمایش در اسلایدر ویژه', 'manacore' ),
						'type'        => 'checkbox',
						'post_types'  => $titles,
					),
					/*
					 * کلید «فقط دوبله فارسی» سایدبار کشف، همین فیلد را
					 * می‌خواند (`manacore_dubbed_meta_key()`). مرجع این
					 * کلید را سمت کاربر داشت؛ در وردپرس معادل درستش یک
					 * فراداده‌ی ویرایش‌پذیر در همین متاباکس است.
					 */
					'manacore_dubbed' => array(
						'label'       => __( 'دوبله فارسی دارد', 'manacore' ),
						'type'        => 'checkbox',
						'post_types'  => $titles,
						'description' => __( 'با فعال بودن، کلید «فقط دوبله فارسی» در نوار فیلتر این اثر را نشان می‌دهد.', 'manacore' ),
					),
					'manacore_is_premium' => array(
						'label'       => __( 'محتوای ویژه‌ی اشتراکی', 'manacore' ),
						'type'        => 'checkbox',
						'post_types'  => $all,
						'description' => __( 'در صورت فعال بودن، دسترسی به لینک‌ها نیازمند اشتراک فعال است.', 'manacore' ),
					),
				),
			),

			/* ---------------- تب: امتیاز و آمار ---------------- */
			'ratings' => array(
				'label'  => __( 'امتیاز و آمار', 'manacore' ),
				'icon'   => 'star-filled',
				'fields' => array(
					'manacore_imdb_id' => array(
						'label'       => __( 'شناسه IMDb', 'manacore' ),
						'type'        => 'text',
						'post_types'  => $all,
						'attrs'       => array( 'placeholder' => 'tt0111161' ),
					),
					'manacore_imdb_rating' => array(
						'label'      => __( 'امتیاز IMDb', 'manacore' ),
						'type'       => 'number',
						'post_types' => $all,
						'attrs'      => array(
							'step' => '0.1',
							'min'  => 0,
							'max'  => 10,
						),
					),
					'manacore_imdb_votes' => array(
						'label'      => __( 'تعداد آرای IMDb', 'manacore' ),
						'type'       => 'number',
						'post_types' => $all,
					),
					'manacore_tmdb_id' => array(
						'label'      => __( 'شناسه TMDB', 'manacore' ),
						'type'       => 'text',
						'post_types' => $all,
					),
					'manacore_tmdb_rating' => array(
						'label'      => __( 'امتیاز TMDB', 'manacore' ),
						'type'       => 'number',
						'post_types' => $all,
						'attrs'      => array(
							'step' => '0.1',
							'min'  => 0,
							'max'  => 10,
						),
					),
					'manacore_mal_id' => array(
						'label'      => __( 'شناسه MyAnimeList', 'manacore' ),
						'type'       => 'text',
						'post_types' => array( 'anime' ),
					),
					'manacore_mal_rating' => array(
						'label'      => __( 'امتیاز MyAnimeList', 'manacore' ),
						'type'       => 'number',
						'post_types' => array( 'anime' ),
						'attrs'      => array(
							'step' => '0.1',
							'min'  => 0,
							'max'  => 10,
						),
					),
					'manacore_rotten_score' => array(
						'label'      => __( 'امتیاز Rotten Tomatoes', 'manacore' ),
						'type'       => 'number',
						'post_types' => $titles,
						'attrs'      => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'manacore_metascore' => array(
						'label'      => __( 'متااسکور', 'manacore' ),
						'type'       => 'number',
						'post_types' => $titles,
						'attrs'      => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'manacore_editor_score' => array(
						'label'       => __( 'امتیاز سردبیر (۰ تا ۱۰)', 'manacore' ),
						'type'        => 'number',
						'post_types'  => $all,
						'attrs'       => array(
							'step' => '0.1',
							'min'  => 0,
							'max'  => 10,
						),
					),
					'manacore_views' => array(
						'label'      => __( 'تعداد بازدید', 'manacore' ),
						'type'       => 'number',
						'post_types' => $all,
						'attrs'      => array( 'readonly' => true ),
					),
				),
			),

			/* ---------------- تب: رسانه ---------------- */
			'media' => array(
				'label'  => __( 'رسانه و تصاویر', 'manacore' ),
				'icon'   => 'format-image',
				'fields' => array(
					'manacore_poster_url' => array(
						'label'       => __( 'آدرس پوستر (خارجی)', 'manacore' ),
						'type'        => 'image_url',
						'post_types'  => $all,
						'description' => __( 'در صورت نبود تصویر شاخص، از این آدرس استفاده می‌شود.', 'manacore' ),
					),
					'manacore_backdrop_url' => array(
						'label'      => __( 'آدرس تصویر پس‌زمینه', 'manacore' ),
						'type'       => 'image_url',
						'post_types' => $all,
					),
					'manacore_logo_url' => array(
						'label'      => __( 'آدرس لوگوی اثر', 'manacore' ),
						'type'       => 'image_url',
						'post_types' => $titles,
					),
					'manacore_trailer_url' => array(
						'label'       => __( 'آدرس تریلر', 'manacore' ),
						'type'        => 'url',
						'post_types'  => $all,
						'description' => __( 'یوتیوب، آپارات یا فایل mp4.', 'manacore' ),
					),
					/*
					 * زیرنویس‌ها به‌صورت تکرارشونده («زبان | برچسب | آدرس
					 * فایل WebVTT | پیش‌فرض») تعریف می‌شوند تا هر اثر
					 * بتواند چند زبان داشته باشد و استودیو بتواند فایل
					 * را روی CDN خودش بگذارد.
					 */
					'manacore_subtitles' => array(
						'label'       => __( 'زیرنویس‌ها', 'manacore' ),
						'type'        => 'repeater',
						'post_types'  => $all,
						'description' => __( 'هر ردیف یک زبان. «کد زبان» استاندارد است (fa، en، ar…) و آدرس باید فایل WebVTT با پسوند .vtt باشد.', 'manacore' ),
						'subfields'   => array(
							'lang'    => array(
								'label'       => __( 'کد زبان', 'manacore' ),
								'type'        => 'text',
								'description' => __( 'مانند fa یا en-US', 'manacore' ),
							),
							'label'   => array(
								'label' => __( 'برچسب زبان', 'manacore' ),
								'type'  => 'text',
							),
							'url'     => array(
								'label' => __( 'آدرس فایل .vtt', 'manacore' ),
								'type'  => 'url',
							),
							'default' => array(
								'label' => __( 'پیش‌فرض باشد', 'manacore' ),
								'type'  => 'checkbox',
							),
						),
					),
					'manacore_gallery' => array(
						'label'      => __( 'گالری تصاویر', 'manacore' ),
						'type'       => 'gallery',
						'post_types' => $titles,
					),
				),
			),

			/* ---------------- تب: عوامل ---------------- */
			'credits' => array(
				'label'  => __( 'عوامل', 'manacore' ),
				'icon'   => 'groups',
				'fields' => array(
					'manacore_director' => array(
						'label'       => __( 'کارگردان', 'manacore' ),
						'type'        => 'text',
						'post_types'  => $all,
						'description' => __( 'نام‌ها را با ویرگول جدا کنید.', 'manacore' ),
					),
					'manacore_writer' => array(
						'label'      => __( 'نویسنده', 'manacore' ),
						'type'       => 'text',
						'post_types' => $all,
					),
					'manacore_producer' => array(
						'label'      => __( 'تهیه‌کننده', 'manacore' ),
						'type'       => 'text',
						'post_types' => $titles,
					),
					'manacore_composer' => array(
						'label'      => __( 'آهنگساز', 'manacore' ),
						'type'       => 'text',
						'post_types' => $titles,
					),
					'manacore_cast' => array(
						'label'      => __( 'بازیگران', 'manacore' ),
						'type'       => 'repeater',
						'post_types' => $titles,
						'subfields'  => array(
							'name'      => array(
								'label' => __( 'نام', 'manacore' ),
								'type'  => 'text',
							),
							'character' => array(
								'label' => __( 'نقش', 'manacore' ),
								'type'  => 'text',
							),
							'photo'     => array(
								'label' => __( 'تصویر', 'manacore' ),
								'type'  => 'image_url',
							),
							'person_id' => array(
								'label' => __( 'شناسه عامل', 'manacore' ),
								'type'  => 'number',
							),
						),
					),
				),
			),

			/* ---------------- تب: سریال ---------------- */
			'series' => array(
				'label'  => __( 'اطلاعات سریال', 'manacore' ),
				'icon'   => 'editor-ol',
				'fields' => array(
					'manacore_total_seasons' => array(
						'label'      => __( 'تعداد فصل‌ها', 'manacore' ),
						'type'       => 'number',
						'post_types' => $serials,
					),
					'manacore_total_episodes' => array(
						'label'      => __( 'تعداد کل قسمت‌ها', 'manacore' ),
						'type'       => 'number',
						'post_types' => $serials,
					),
					'manacore_episode_runtime' => array(
						'label'      => __( 'مدت هر قسمت (دقیقه)', 'manacore' ),
						'type'       => 'number',
						'post_types' => $serials,
					),
					'manacore_air_day' => array(
						'label'      => __( 'روز پخش', 'manacore' ),
						'type'       => 'select',
						'post_types' => $serials,
						'options'    => array(
							''          => __( '— انتخاب کنید —', 'manacore' ),
							'saturday'  => __( 'شنبه', 'manacore' ),
							'sunday'    => __( 'یکشنبه', 'manacore' ),
							'monday'    => __( 'دوشنبه', 'manacore' ),
							'tuesday'   => __( 'سه‌شنبه', 'manacore' ),
							'wednesday' => __( 'چهارشنبه', 'manacore' ),
							'thursday'  => __( 'پنجشنبه', 'manacore' ),
							'friday'    => __( 'جمعه', 'manacore' ),
						),
					),
					'manacore_air_time' => array(
						'label'       => __( 'ساعت پخش', 'manacore' ),
						'type'        => 'text',
						'post_types'  => $serials,
						'description' => __( 'مثلاً «۲۱:۳۰». در برنامه‌ی هفتگی کنار نام اثر نمایش داده می‌شود.', 'manacore' ),
					),
					'manacore_next_episode_date' => array(
						'label'      => __( 'تاریخ قسمت بعدی', 'manacore' ),
						'type'       => 'date',
						'post_types' => $serials,
					),
					'manacore_seasons' => array(
						'label'       => __( 'فصل‌ها', 'manacore' ),
						'type'        => 'repeater',
						'post_types'  => $serials,
						'description' => __( 'اطلاعات هر فصل. لینک‌های دانلود در تب «لینک‌ها» مدیریت می‌شوند.', 'manacore' ),
						'subfields'   => array(
							'number'   => array(
								'label' => __( 'شماره فصل', 'manacore' ),
								'type'  => 'number',
							),
							'name'     => array(
								'label' => __( 'نام فصل', 'manacore' ),
								'type'  => 'text',
							),
							'episodes' => array(
								'label' => __( 'تعداد قسمت', 'manacore' ),
								'type'  => 'number',
							),
							'year'     => array(
								'label' => __( 'سال', 'manacore' ),
								'type'  => 'number',
							),
							'poster'   => array(
								'label' => __( 'پوستر فصل', 'manacore' ),
								'type'  => 'image_url',
							),
							'overview' => array(
								'label' => __( 'خلاصه', 'manacore' ),
								'type'  => 'textarea',
							),
						),
					),
				),
			),

			/* ---------------- تب: قسمت ---------------- */
			'episode' => array(
				'label'  => __( 'اطلاعات قسمت', 'manacore' ),
				'icon'   => 'playlist-video',
				'fields' => array(
					'manacore_parent_title' => array(
						'label'       => __( 'اثر والد', 'manacore' ),
						'type'        => 'post_select',
						'post_types'  => array( 'episode' ),
						'source'      => $serials,
						'description' => __( 'سریال یا انیمه‌ای که این قسمت به آن تعلق دارد.', 'manacore' ),
					),
					'manacore_season_number' => array(
						'label'      => __( 'شماره فصل', 'manacore' ),
						'type'       => 'number',
						'post_types' => array( 'episode' ),
					),
					'manacore_episode_number' => array(
						'label'      => __( 'شماره قسمت', 'manacore' ),
						'type'       => 'number',
						'post_types' => array( 'episode' ),
					),
					'manacore_air_date' => array(
						'label'      => __( 'تاریخ پخش', 'manacore' ),
						'type'       => 'date',
						'post_types' => array( 'episode' ),
					),
				),
			),

			/* ---------------- تب: سئو و نمایش ---------------- */
			'display' => array(
				'label'  => __( 'نمایش و سئو', 'manacore' ),
				'icon'   => 'visibility',
				'fields' => array(
					'manacore_seo_title' => array(
						'label'      => __( 'عنوان سئو', 'manacore' ),
						'type'       => 'text',
						'post_types' => $all,
					),
					'manacore_seo_description' => array(
						'label'      => __( 'توضیحات متا', 'manacore' ),
						'type'       => 'textarea',
						'post_types' => $all,
					),
					'manacore_custom_notice' => array(
						'label'       => __( 'یادداشت اختصاصی صفحه', 'manacore' ),
						'type'        => 'textarea',
						'post_types'  => $all,
						'description' => __( 'بالای بخش لینک‌ها نمایش داده می‌شود.', 'manacore' ),
					),
					'manacore_disable_links' => array(
						'label'      => __( 'غیرفعال کردن بخش لینک‌ها', 'manacore' ),
						'type'       => 'checkbox',
						'post_types' => $all,
					),
					'manacore_collection' => array(
						'label'       => __( 'مجموعه', 'manacore' ),
						'type'        => 'post_select',
						'post_types'  => $titles,
						'source'      => array( 'collection' ),
						'description' => __( 'مجموعه‌ای که این اثر عضو آن است (برای بلوک «آثار مجموعه»).', 'manacore' ),
					),
				),
			),

			/* ---------------- تب: کانال پخش زنده ---------------- */
			'channel' => array(
				'label'  => __( 'کانال', 'manacore' ),
				'icon'   => 'video-alt3',
				'fields' => array(
					'manacore_channel_title' => array(
						'label'       => __( 'تیتر کانال', 'manacore' ),
						'type'        => 'text',
						'post_types'  => array( 'channel' ),
						'description' => __( 'خط تیتر کارت «در حال پخش»؛ مثلاً «یک قرار با دنیای سینما».', 'manacore' ),
					),
					'manacore_channel_subtitle' => array(
						'label'       => __( 'زیرعنوان کانال', 'manacore' ),
						'type'        => 'text',
						'post_types'  => array( 'channel' ),
						'description' => __( 'توضیح کوتاه زیر تیتر؛ مثلاً «تجربه آزمایشی کانال سینمایی».', 'manacore' ),
					),
					'manacore_channel_quality' => array(
						'label'      => __( 'کیفیت پخش', 'manacore' ),
						'type'       => 'select',
						'post_types' => array( 'channel' ),
						'options'    => array(
							'360'  => '۳۶۰p',
							'480'  => '۴۸۰p',
							'720'  => '۷۲۰p',
							'1080' => '۱۰۸۰p',
						),
					),
					'manacore_channel_icon' => array(
						'label'       => __( 'نشانه‌ی کانال', 'manacore' ),
						'type'        => 'select',
						'post_types'  => array( 'channel' ),
						'options'     => Channel::icon_options(),
						'description' => __( 'آیکون تازه‌ی همین افزونه؛ مرجع به‌جای آن نویسه‌ی یونیکد سخت‌کد داشت.', 'manacore' ),
					),
					'manacore_channel_video' => array(
						'label'       => __( 'نشانی ویدئو', 'manacore' ),
						'type'        => 'url',
						'post_types'  => array( 'channel' ),
						'description' => __( 'فایل یا نشانی ویدئوی پخش؛ خالی بگذارید تا فقط پوستر نشان داده شود.', 'manacore' ),
					),
					'manacore_channel_poster' => array(
						'label'       => __( 'نشانی پوستر', 'manacore' ),
						'type'        => 'image_url',
						'post_types'  => array( 'channel' ),
						'description' => __( 'اگر تصویر شاخص گذاشته شود، همان اولویت دارد.', 'manacore' ),
					),
				),
			),

			/* ---------------- تب: عوامل ---------------- */
			'person' => array(
				'label'  => __( 'عوامل', 'manacore' ),
				'icon'   => 'groups',
				'fields' => array(
					'manacore_person_english' => array(
						'label'       => __( 'نام لاتین', 'manacore' ),
						'type'        => 'text',
						'post_types'  => $people,
						'description' => __( 'زیر نام فارسی و چپ‌چین نمایش داده می‌شود (هم‌ارز `person-english` مرجع).', 'manacore' ),
					),
					'manacore_person_born' => array(
						'label'      => __( 'زادروز', 'manacore' ),
						'type'       => 'text',
						'post_types' => $people,
					),
					'manacore_country' => array(
						'label'      => __( 'کشور', 'manacore' ),
						'type'       => 'text',
						'post_types' => $people,
					),
					'manacore_person_photo' => array(
						'label'       => __( 'نشانی تصویر چهره', 'manacore' ),
						'type'        => 'url',
						'post_types'  => $people,
						'description' => __( 'اگر تصویر شاخص نگذاشته باشید، همین تصویر در کارت‌ها و قاب چهره استفاده می‌شود.', 'manacore' ),
					),
				),
			),

			/* ---------------- تب: مقاله ---------------- */
			'article' => array(
				'label'  => __( 'مقاله', 'manacore' ),
				'icon'   => 'welcome-write-blog',
				'fields' => array(
					'manacore_reading_time' => array(
						'label'       => __( 'زمان مطالعه (دقیقه)', 'manacore' ),
						'type'        => 'number',
						'post_types'  => array( 'post' ),
						'description' => __( 'خالی بگذارید تا از شمار واژه‌های متن محاسبه شود.', 'manacore' ),
					),
				),
			),
		);

		$this->schema = apply_filters( 'manacore_meta_schema', $schema );
		return $this->schema;
	}

	/**
	 * فهرست تخت همه‌ی فیلدها.
	 *
	 * @return array
	 */
	public function flat_fields() {
		$out = array();
		foreach ( $this->schema() as $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				$out[ $key ] = $field;
			}
		}
		return $out;
	}

	/**
	 * فیلدهای مربوط به یک نوع محتوا.
	 *
	 * @param string $post_type نوع محتوا.
	 * @return array
	 */
	public function groups_for( $post_type ) {
		$groups = array();
		foreach ( $this->schema() as $key => $group ) {
			$fields = array();
			foreach ( $group['fields'] as $field_key => $field ) {
				if ( in_array( $post_type, $field['post_types'], true ) ) {
					$fields[ $field_key ] = $field;
				}
			}
			if ( $fields ) {
				$group['fields'] = $fields;
				$groups[ $key ]  = $group;
			}
		}
		return $groups;
	}

	/**
	 * ثبت متا در وردپرس برای دسترسی REST و ویرایشگر بلوک.
	 */
	public function register_meta() {
		foreach ( $this->schema() as $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				foreach ( $field['post_types'] as $post_type ) {
					register_post_meta(
						$post_type,
						$key,
						array(
							'type'              => $this->rest_type( $field['type'] ),
							'description'       => $field['label'],
							'single'            => true,
							'show_in_rest'      => $this->rest_schema( $field ),
							'sanitize_callback' => array( $this, 'sanitize_by_type' ),
							'auth_callback'     => static function () {
								return current_user_can( 'edit_posts' );
							},
						)
					);
				}
			}
		}

		// متای لینک‌ها به صورت جداگانه (ساختار پیچیده).
		foreach ( array_merge( manacore_title_post_types(), array( 'episode' ) ) as $post_type ) {
			register_post_meta(
				$post_type,
				'manacore_links',
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		// فهرست مرتب اعضای مجموعه (آرایه‌ی شناسه‌ها).
		register_post_meta(
			'collection',
			'manacore_collection_items',
			array(
				'type'          => 'array',
				'description'   => __( 'آثار این مجموعه به ترتیب نمایش.', 'manacore' ),
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'auth_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * نگاشت نوع فیلد به نوع REST.
	 *
	 * @param string $type نوع فیلد.
	 * @return string
	 */
	protected function rest_type( $type ) {
		switch ( $type ) {
			case 'number':
				return 'number';
			case 'checkbox':
				return 'boolean';
			case 'repeater':
			case 'gallery':
				return 'string';
			default:
				return 'string';
		}
	}

	/**
	 * اسکیمای REST فیلد.
	 *
	 * @param array $field فیلد.
	 * @return array|bool
	 */
	protected function rest_schema( $field ) {
		return true;
	}

	/**
	 * پاک‌سازی مقدار.
	 *
	 * @param mixed $value مقدار.
	 * @return mixed
	 */
	public function sanitize_by_type( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}
		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return $value;
		}
		return wp_kses_post( (string) $value );
	}
}

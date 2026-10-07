<?php
/**
 * داده‌های موردنیاز ویرایشگر بلوک‌ها (پل انتقال به JavaScript).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Data
 */
class Block_Data {

	/**
	 * حداکثر تعداد ترم ارسالی برای هر تاکسونومی.
	 */
	const TERM_LIMIT = 300;

	/**
	 * ساخت کل بسته‌ی داده برای ویرایشگر.
	 *
	 * @return array
	 */
	public static function payload() {
		$payload = array(
			'sources'        => self::sources(),
			'postTypes'      => self::post_types(),
			'allPostTypes'   => self::all_post_types(),
			'taxonomies'     => self::taxonomies(),
			'orderOptions'   => Block_Query::order_options(),
			'premiumOptions' => Block_Query::premium_options(),
			'stickyOptions'  => Block_Query::sticky_options(),
			'layouts'        => self::layouts(),
			'cardStyles'     => self::card_styles(),
			'sliderStyles'   => self::slider_styles(),
			'sliderEffects'  => self::slider_effects(),
			'sliderAligns'   => self::slider_alignments(),
			'downloadStyles' => self::download_styles(),
			'headingLevels'  => self::heading_levels(),
			/* نقش‌های چهره برای بازرس بلوک «شبکه‌ی چهره‌ها». */
			'personRoles'    => self::term_options( 'person_role' ),
			'imageRatios'    => self::image_ratios(),
			'metaFields'     => self::meta_fields(),
			'linkTypes'      => function_exists( 'manacore_link_types' ) ? manacore_link_types() : array(),
			'qualities'      => function_exists( 'manacore_qualities' ) ? manacore_qualities() : array(),
			'languages'      => function_exists( 'manacore_languages' ) ? manacore_languages() : array(),
			'roles'          => self::roles(),
			'subscription'   => self::subscription_levels(),
			'visibility'     => array(
				'ruleTypes' => Block_Visibility::rule_types(),
				'contexts'  => Block_Visibility::contexts(),
				'devices'   => Block_Visibility::devices(),
			),
			'pickableTypes'  => self::pickable_types(),
			'hasSubs'        => function_exists( 'manacore_subs_user_level' ),
			/* تب‌های برگه‌ی حساب — برای گزینه‌های ویرایشگر (منبع: کلاس Account). */
			'accountTabs'    => Account::tabs(),
			// سازگاری با نسخه‌ی قبلی اسکریپت.
			'genres'         => self::term_options( 'genre' ),
		);

		/**
		 * فیلتر داده‌های ویرایشگر بلوک.
		 *
		 * @param array $payload داده‌ها.
		 */
		return (array) apply_filters( 'manacore_block_editor_data', $payload );
	}

	/**
	 * منابع داده‌ی حلقه.
	 *
	 * @return array
	 */
	public static function sources() {
		$sources = array(
			'latest'      => __( 'جدیدترین', 'manacore' ),
			'featured'    => __( 'منتخب سردبیر', 'manacore' ),
			'top_rated'   => __( 'بالاترین امتیاز', 'manacore' ),
			'most_viewed' => __( 'پربازدیدترین', 'manacore' ),
			'trending'    => __( 'داغ هفته', 'manacore' ),
			'random'      => __( 'تصادفی', 'manacore' ),
			'related'     => __( 'آثار مشابه (بر اساس اثر جاری)', 'manacore' ),
			'person_works' => __( 'آثار این عامل (صفحه‌ی عامل)', 'manacore' ),
			'collection'  => __( 'آثار یک مجموعه', 'manacore' ),
			/* منابع کاربرمحور برگه‌ی حساب (کلاس Account). */
			'watchlist'   => __( 'لیست تماشای من', 'manacore' ),
			'recommended' => __( 'پیشنهاد برای من (بر پایه ژانرها)', 'manacore' ),
		);

		/**
		 * فیلتر منابع حلقه.
		 *
		 * @param array $sources منابع.
		 */
		return (array) apply_filters( 'manacore_block_sources', $sources );
	}

	/**
	 * نوع‌های محتوای ManaCore.
	 *
	 * @return array
	 */
	public static function post_types() {
		return function_exists( 'manacore_post_types' ) ? manacore_post_types() : array();
	}

	/**
	 * همه‌ی نوع‌های محتوای عمومی (برای حلقه‌های عمومی و قواعد نمایش).
	 *
	 * @return array
	 */
	/**
	 * نوع‌های محتوای قابل انتخاب در گزینش‌گر «اثر» ویرایشگر.
	 *
	 * گزینش‌گر ویرایشگر برای یافتن یک اثر مشخص (مثلاً سریال میزبانِ قسمت‌ها)
	 * باید بتواند در REST وردپرس جست‌وجو کند؛ پس هر نوع محتوا همراه با
	 * `rest_base` خودش برگردانده می‌شود. تنها نوع‌هایی می‌آیند که
	 * `show_in_rest` دارند، وگرنه گزینش‌گر آن‌ها را بی‌پاسخ می‌گذارد.
	 *
	 * @return array نگاشت slug => array( label, restBase ).
	 */
	public static function pickable_types() {
		$out = array();

		foreach ( self::all_post_types() as $slug => $label ) {
			$object = get_post_type_object( $slug );
			if ( ! $object || empty( $object->show_in_rest ) ) {
				continue;
			}

			$out[ $slug ] = array(
				'label'    => $label,
				'restBase' => ! empty( $object->rest_base ) ? $object->rest_base : $slug,
			);
		}

		return $out;
	}

	public static function all_post_types() {
		$out     = array();
		$objects = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		foreach ( $objects as $object ) {
			if ( 'attachment' === $object->name ) {
				continue;
			}
			$out[ $object->name ] = $object->labels->singular_name ? $object->labels->singular_name : $object->name;
		}
		return $out;
	}

	/**
	 * فهرست تاکسونومی‌ها همراه با ترم‌ها.
	 *
	 * ساختار: array( slug => array( 'label', 'hierarchical', 'postTypes', 'terms' => array( array('value','label','count') ) ) )
	 *
	 * @return array
	 */
	public static function taxonomies() {
		$out        = array();
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		foreach ( $taxonomies as $taxonomy ) {
			if ( 'post_format' === $taxonomy->name ) {
				continue;
			}

			$out[ $taxonomy->name ] = array(
				'label'        => $taxonomy->labels->singular_name ? $taxonomy->labels->singular_name : $taxonomy->name,
				'plural'       => $taxonomy->labels->name,
				'hierarchical' => (bool) $taxonomy->hierarchical,
				'postTypes'    => array_values( (array) $taxonomy->object_type ),
				'terms'        => self::terms( $taxonomy->name ),
			);
		}

		return $out;
	}

	/**
	 * ترم‌های یک تاکسونومی به شکل مناسب SelectControl.
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @return array
	 */
	public static function terms( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => self::TERM_LIMIT,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'value' => $term->slug,
				'label' => $term->name,
				'id'    => (int) $term->term_id,
				'count' => (int) $term->count,
			);
		}
		return $out;
	}

	/**
	 * ترم‌ها به شکل «اسلاگ => نام» (سازگاری با نسخه‌ی قبل).
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @return array
	 */
	public static function term_options( $taxonomy ) {
		$out = array();
		foreach ( self::terms( $taxonomy ) as $term ) {
			$out[ $term['value'] ] = $term['label'];
		}
		return $out;
	}

	/**
	 * چیدمان‌های حلقه.
	 *
	 * @return array
	 */
	public static function layouts() {
		return (array) apply_filters(
			'manacore_block_layouts',
			array(
				'grid'      => __( 'شبکه‌ای', 'manacore' ),
				'carousel'  => __( 'ریل افقی (اسکرول)', 'manacore' ),
				'list'      => __( 'فهرست عمودی', 'manacore' ),
				'masonry'   => __( 'آجری (Masonry)', 'manacore' ),
				'spotlight' => __( 'یک شاخص + فهرست کنار', 'manacore' ),
				'showcase'  => __( 'ویترین (سطر اول بزرگ)', 'manacore' ),
				'metro'     => __( 'مترو (کاشی نامتقارن)', 'manacore' ),
				'ranking'   => __( 'رتبه‌بندی شماره‌دار', 'manacore' ),
			)
		);
	}

	/**
	 * سبک‌های کارت اثر.
	 *
	 * منبع یگانه‌ی حقیقت؛ هم ویرایشگر (از راه payload) و هم اعتبارسنجی
	 * سمت سرور از همین فهرست استفاده می‌کنند تا هیچ گزینه‌ای در ویرایشگر
	 * دیده نشود که در خروجی بی‌اثر باشد.
	 *
	 * @return array
	 */
	public static function card_styles() {
		return (array) apply_filters(
			'manacore_card_styles',
			array(
				'poster'    => __( 'پوستر', 'manacore' ),
				/*
				 * «عریض» از فهرست حذف شد: با «افقی (۱۶:۹)» هم‌پوشانی کامل
				 * داشت (هر دو نسبت ۱۶:۹) و در ویرایشگر دو انتخاب برای یک
				 * نتیجه دیده می‌شد. مقدار کهنه در `Templates::card` به
				 * «افقی» نگاشت می‌شود تا محتوای قدیمی ظاهرش را از دست ندهد.
				 */
				'minimal'   => __( 'مینیمال', 'manacore' ),
				'text'      => __( 'فقط متن', 'manacore' ),
				'overlay'   => __( 'متن روی تصویر', 'manacore' ),
				'glass'     => __( 'شیشه‌ای', 'manacore' ),
				'outline'   => __( 'کادر ساده', 'manacore' ),
				'elevated'  => __( 'برجسته (سایه‌دار)', 'manacore' ),
				'landscape' => __( 'افقی (۱۶:۹)', 'manacore' ),
			)
		);
	}

	/**
	 * سبک‌های ظاهری اسلایدر ویژه.
	 *
	 * @return array
	 */
	public static function slider_styles() {
		return (array) apply_filters(
			'manacore_slider_styles',
			array(
				'cinematic' => __( 'سینمایی (تمام‌عرض)', 'manacore' ),
				'boxed'     => __( 'کادردار', 'manacore' ),
				'split'     => __( 'دوبخشی (متن کنار تصویر)', 'manacore' ),
				'minimal'   => __( 'مینیمال', 'manacore' ),
				'poster'    => __( 'پوستری (تصویر کوچک کنار متن)', 'manacore' ),
			)
		);
	}

	/**
	 * جلوه‌ی گذر اسلاید.
	 *
	 * @return array
	 */
	public static function slider_effects() {
		return (array) apply_filters(
			'manacore_slider_effects',
			array(
				'fade'  => __( 'محو شدن', 'manacore' ),
				'slide' => __( 'لغزش افقی', 'manacore' ),
				'zoom'  => __( 'بزرگ‌نمایی آرام', 'manacore' ),
				'none'  => __( 'بدون جلوه', 'manacore' ),
			)
		);
	}

	/**
	 * جای‌گیری محتوای اسلاید.
	 *
	 * @return array
	 */
	public static function slider_alignments() {
		return (array) apply_filters(
			'manacore_slider_alignments',
			array(
				'start'  => __( 'ابتدای کادر', 'manacore' ),
				'center' => __( 'وسط', 'manacore' ),
				'end'    => __( 'انتهای کادر', 'manacore' ),
			)
		);
	}

	/**
	 * سبک‌های ظاهری بخش دانلود.
	 *
	 * قرارداد تازه (هم‌شکل مرجع `cinora/detail.html`):
	 *   `cards` → جدول کادردار با سرستون و ردیف‌ها (پیش‌فرض)
	 *   `table` → همان جدول بدون کادر بیرونی
	 *
	 * سبک‌های جعبه‌ای قدیمی (`compact`، `accordion`، `buttons`) با
	 * حذف CSS آن‌ها بی‌اثر می‌شدند و گزینه‌ی بی‌اثر در ویرایشگر
	 * نمی‌مانیم؛ پس از فهرست هم برداشته شدند.
	 *
	 * @return array
	 */
	public static function download_styles() {
		return (array) apply_filters(
			'manacore_download_styles',
			array(
				'cards' => __( 'کادردار (پیش‌فرض)', 'manacore' ),
				'table' => __( 'جدولی بدون کادر', 'manacore' ),
			)
		);
	}

	/**
	 * سطح‌های سرتیتر.
	 *
	 * @return array
	 */
	public static function heading_levels() {
		return array(
			'h2'   => __( 'H2', 'manacore' ),
			'h3'   => __( 'H3', 'manacore' ),
			'h4'   => __( 'H4', 'manacore' ),
			'h5'   => __( 'H5', 'manacore' ),
			'h6'   => __( 'H6', 'manacore' ),
			'div'  => __( 'بدون سرتیتر معنایی (div)', 'manacore' ),
		);
	}

	/**
	 * نسبت‌های تصویر کارت.
	 *
	 * @return array
	 */
	public static function image_ratios() {
		return array(
			''      => __( 'همراه با سبک کارت', 'manacore' ),
			'2-3'   => __( 'پوستر ۲:۳', 'manacore' ),
			'3-4'   => __( 'عمودی ۳:۴', 'manacore' ),
			'1-1'   => __( 'مربع ۱:۱', 'manacore' ),
			'16-9'  => __( 'عریض ۱۶:۹', 'manacore' ),
			'21-9'  => __( 'سینمایی ۲۱:۹', 'manacore' ),
		);
	}

	/**
	 * فیلدهای قابل نمایش در جدول مشخصات.
	 *
	 * @return array
	 */
	public static function meta_fields() {
		$fields = array(
			'original_title'  => __( 'نام اصلی', 'manacore' ),
			'alt_titles'      => __( 'نام‌های دیگر', 'manacore' ),
			'tagline'         => __( 'شعار', 'manacore' ),
			'year'            => __( 'سال انتشار', 'manacore' ),
			'release_date'    => __( 'تاریخ انتشار', 'manacore' ),
			'runtime'         => __( 'مدت زمان', 'manacore' ),
			'status'          => __( 'وضعیت', 'manacore' ),
			'genre'           => __( 'ژانر', 'manacore' ),
			'country'         => __( 'کشور', 'manacore' ),
			'network'         => __( 'شبکه', 'manacore' ),
			'studio'          => __( 'استودیو', 'manacore' ),
			'language'        => __( 'زبان', 'manacore' ),
			'quality'         => __( 'کیفیت', 'manacore' ),
			'release_year'    => __( 'سال (تاکسونومی)', 'manacore' ),
			'director'        => __( 'کارگردان', 'manacore' ),
			'writer'          => __( 'نویسنده', 'manacore' ),
			'producer'        => __( 'تهیه‌کننده', 'manacore' ),
			'composer'        => __( 'آهنگساز', 'manacore' ),
			'imdb_rating'     => __( 'امتیاز IMDb', 'manacore' ),
			'tmdb_rating'     => __( 'امتیاز TMDB', 'manacore' ),
			'mal_rating'      => __( 'امتیاز MyAnimeList', 'manacore' ),
			'editor_score'    => __( 'امتیاز سردبیر', 'manacore' ),
			'total_seasons'   => __( 'تعداد فصل', 'manacore' ),
			'total_episodes'  => __( 'تعداد قسمت', 'manacore' ),
			'episode_runtime' => __( 'مدت هر قسمت', 'manacore' ),
			'air_day'         => __( 'روز پخش', 'manacore' ),
			'air_date'        => __( 'تاریخ پخش قسمت', 'manacore' ),
			'season_number'   => __( 'شماره‌ی فصل', 'manacore' ),
			'episode_number'  => __( 'شماره‌ی قسمت', 'manacore' ),
			'views'           => __( 'بازدید', 'manacore' ),
		);

		/**
		 * فیلتر فیلدهای جدول مشخصات.
		 *
		 * @param array $fields فیلدها.
		 */
		return (array) apply_filters( 'manacore_meta_display_fields', $fields );
	}

	/**
	 * نقش‌های کاربری.
	 *
	 * @return array
	 */
	public static function roles() {
		$roles = array( 'guest' => __( 'مهمان (وارد نشده)', 'manacore' ) );

		if ( ! function_exists( 'wp_roles' ) ) {
			return $roles;
		}

		foreach ( wp_roles()->get_names() as $slug => $label ) {
			$roles[ $slug ] = translate_user_role( $label );
		}
		return $roles;
	}

	/**
	 * سطوح اشتراک (در صورت فعال بودن افزونه).
	 *
	 * @return array
	 */
	public static function subscription_levels() {
		if ( ! function_exists( 'manacore_subs_levels' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) manacore_subs_levels() as $slug => $level ) {
			if ( is_array( $level ) ) {
				$out[ $slug ] = isset( $level['label'] ) ? $level['label'] : $slug;
			} else {
				$out[ $slug ] = (string) $level;
			}
		}
		return $out;
	}
}

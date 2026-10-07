<?php
/**
 * بلوک‌های ویرایشگر (رندر سمت سرور).
 *
 * هر بلوک از سه لایه‌ی مشترک استفاده می‌کند:
 *
 * - {@see Block_Support} ویژگی‌های ظاهری و قطعه‌های مشترک رندر.
 * - {@see Block_Query}   تبدیل ویژگی‌ها به آرگومان‌های WP_Query.
 * - {@see Block_Visibility} قواعد نمایش شرطی (دسته، برچسب، تاکسونومی، نوع محتوا، نقش، اشتراک و…).
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocks
 */
class Blocks {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'block_categories_all', array( $this, 'category' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * افزودن دسته‌بندی بلوک.
	 *
	 * @param array $categories دسته‌ها.
	 * @return array
	 */
	public function category( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'manacore',
				'title' => __( 'ManaCore — فیلم و سریال', 'manacore' ),
				'icon'  => 'video-alt2',
			)
		);
		return $categories;
	}

	/**
	 * تعریف بلوک‌ها.
	 *
	 * @return array
	 */
	public function definitions() {
		$blocks = array(

			'manacore/titles-grid'    => array(
				'title'       => __( 'شبکه‌ی آثار', 'manacore' ),
				'description' => __( 'حلقه‌ی فیلم، سریال و انیمه با کوئری، چیدمان، کارت و نمایش شرطی کاملاً قابل تنظیم.', 'manacore' ),
				'icon'        => 'grid-view',
				'attributes'  => Block_Support::loop_attributes(
					array(
						'ranked' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						/*
						 * تب‌های نوع سرصفحه (`.section-tabs` مرجع): «همه /
						 * فیلم‌ها / سریال‌ها…». مرجع این تب‌ها را در بخش
						 * «این روزها، روی بورس» صفحه‌ی نخست دارد و با
						 * `home.js` شبکه را سمت کاربر پالایش می‌کند.
						 */
						'showTypeTabs' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						/*
						 * حالت خالیِ پنل: عنوان/متن/دکمه از بلوک می‌آید
						 * (پیش‌فرض همین متن‌های پیشین می‌مانند). تب «لیست
						 * تماشای برگه‌ی حساب» از این‌ها استفاده می‌کند.
						 */
						'emptyTitle'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'emptyLinkLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
						'emptyLinkUrl' => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_titles_grid' ),
			),

			'manacore/hero-slider'    => array(
				'title'       => __( 'اسلایدر ویژه', 'manacore' ),
				'description' => __( 'اسلایدر بزرگ بالای صفحه با کنترل کامل روی منبع، پخش خودکار و اجزای نمایش.', 'manacore' ),
				'icon'        => 'slides',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					Block_Query::attributes(),
					array(
						'sliderStyle'  => array(
							'type'    => 'string',
							'default' => 'cinematic',
						),
						'effect'       => array(
							'type'    => 'string',
							'default' => 'fade',
						),
						'contentAlign' => array(
							'type'    => 'string',
							'default' => 'start',
						),
						'overlay'      => array(
							'type'    => 'number',
							'default' => 0,
						),
						'height'       => array(
							'type'    => 'string',
							'default' => 'medium',
						),
						'autoplay'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'interval'     => array(
							'type'    => 'number',
							'default' => 7,
						),
						'showDots'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showArrows'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showLogo'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showMeta'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showGenres'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'genreCount'   => array(
							'type'    => 'number',
							'default' => 3,
						),
						'showExcerpt'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'excerptWords' => array(
							'type'    => 'number',
							'default' => 28,
						),
						'showTrailer'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'primaryLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
						'trailerLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_hero_slider' ),
			),

			'manacore/title-meta'     => array(
				'title'       => __( 'مشخصات اثر', 'manacore' ),
				'description' => __( 'جدول مشخصات اثر جاری با انتخاب دقیق فیلدها، چیدمان و پیوند ترم‌ها.', 'manacore' ),
				'icon'        => 'list-view',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'fields'     => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'linkTerms'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'metaLayout' => array(
							'type'    => 'string',
							'default' => 'rows',
						),
						'hideEmpty'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'labelWidth' => array(
							'type'    => 'number',
							'default' => 0,
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_title_meta' ),
			),

			'manacore/download-links' => array(
				'title'       => __( 'لینک‌های دانلود', 'manacore' ),
				'description' => __( 'جدول لینک‌های دانلود و پخش با فیلتر نوع، کیفیت و فصل.', 'manacore' ),
				'icon'        => 'download',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'boxStyle'   => array(
							'type'    => 'string',
							'default' => 'cards',
						),
						/*
						 * `mode` طراحی و منطق باکس را از نوع محتوا جدا می‌کند:
						 * فیلم → جدول کیفیت، سریال → بسته‌های کامل فصل،
						 * قسمت → جدول کیفیت همان قسمت. `auto` از نوع پست
						 * تشخیص می‌دهد تا قالب‌های موجود دست‌نخورده بمانند.
						 */
						'mode'       => array(
							'type'    => 'string',
							'default' => 'auto',
						),
						'packLabel'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'sizeLabel'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'subtitle'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'showIcon'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCount'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showNotice' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showTabs'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'types'      => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'qualities'  => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'season'     => array(
							'type'    => 'number',
							'default' => 0,
						),
						/*
						 * `openFirst` فقط برای سبک آکاردئونی معنا داشت. آن سبک در
						 * بازطراحی جدول دانلود (هم‌شکل `.download-section` مرجع) حذف
						 * شد؛ صفت برای سازگاری محتوای ذخیره‌شده می‌ماند ولی کنترل
						 * ویرایشگری و مصرفی ندارد.
						 */
						'openFirst'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_links' ),
			),

			'manacore/rating-box'     => array(
				'title'       => __( 'جعبه‌ی امتیاز', 'manacore' ),
				'description' => __( 'امتیازهای IMDb/TMDB/MAL/سردبیر و امتیازدهی کاربران با انتخاب منابع.', 'manacore' ),
				'icon'        => 'star-filled',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'showScores'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'scoreSources'   => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'showBars'       => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showUserRating' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSummary'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'userLabel'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'postId'         => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_rating_box' ),
			),

			'manacore/cast-list'      => array(
				'title'       => __( 'فهرست بازیگران', 'manacore' ),
				'description' => __( 'بازیگران و نقش‌هایشان به شکل ریل افقی یا شبکه‌ای.', 'manacore' ),
				'icon'        => 'groups',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'count'         => array(
							'type'    => 'number',
							'default' => 12,
						),
						'offset'        => array(
							'type'    => 'number',
							'default' => 0,
						),
						'layout'        => array(
							'type'    => 'string',
							'default' => 'carousel',
						),
						'columns'       => array(
							'type'    => 'number',
							'default' => 6,
						),
						'columnsTablet' => array(
							'type'    => 'number',
							'default' => 4,
						),
						'columnsMobile' => array(
							'type'    => 'number',
							'default' => 3,
						),
						'showPhoto'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCharacter' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'imageRatio'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'postId'        => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_cast' ),
			),

			'manacore/trailer'        => array(
				'title'       => __( 'تریلر', 'manacore' ),
				'description' => __( 'پخش‌کننده‌ی تریلر اثر جاری با نسبت تصویر و پوستر دلخواه.', 'manacore' ),
				'icon'        => 'video-alt3',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'videoRatio' => array(
							'type'    => 'string',
							'default' => '16-9',
						),
						'showPoster' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						/*
						 * حالت «فقط دکمه»: در سرصفحه‌ی تک‌قسمت (الگوی مرجع)
						 * فقط دکمه‌ی شیشه‌ای «پخش تریلر» لازم است، نه پخش‌کننده.
						 * پیش‌فرض `true` است تا رفتار موجود بلوک تغییر نکند.
						 */
						'showPlayer' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'metaKey'    => array(
							'type'    => 'string',
							'default' => 'manacore_trailer_url',
						),
						'ctaLabel'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'postId'     => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_trailer' ),
			),

			'manacore/player-page'    => array(
				'title'       => __( 'صفحه‌ی پخش', 'manacore' ),
				'description' => __( 'صفحه‌ی تمام‌عیار پخش (الگوی «در حال پخش»): عنوان اثر، ویدئو، انتخاب کیفیت، حالت سینما، تمام‌صفحه، لیست تماشا و دکمه‌ی دانلود.', 'manacore' ),
				'icon'        => 'controls-play',
				'attributes'  => array(
					'eyebrow'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'backLabel'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'notice'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'showNotice'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBadges'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'postId'      => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render'      => array( $this, 'render_player_page' ),
			),

			'manacore/filter-bar'     => array(
				'title'       => __( 'نوار فیلتر', 'manacore' ),
				'description' => __( 'فیلتر آثار بر اساس تاکسونومی‌های انتخابی، جستجو و مرتب‌سازی.', 'manacore' ),
				'icon'        => 'filter',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						// آرایه‌ی تاکسونومی‌ها؛ رشته‌ی کاما‌جدا هم برای سازگاری پذیرفته می‌شود.
						'taxonomies'    => array(
							'type'    => 'array',
							'default' => array( 'genre', 'release_year', 'country' ),
							'items'   => array( 'type' => 'string' ),
						),
						'showSort'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSearch'    => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showSubmit'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showReset'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showActiveChips' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'submitLabel'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'hideEmptyTerms'=> array(
							'type'    => 'boolean',
							'default' => true,
						),
						'termLimit'     => array(
							'type'    => 'number',
							'default' => 200,
						),
						'formLayout'    => array(
							'type'    => 'string',
							'default' => 'inline',
						),

						/*
						 * گروه‌های تازه‌ی سایدبار «فیلتر پیشرفته» (مرجع:
						 * `browse.html` → `.filter-sidebar`). هر گروه
						 * مستقل خاموش/روشن می‌شود تا نویسنده ترکیب
						 * دلخواه بسازد؛ عنوان‌ها هم ویرایش‌پذیرند.
						 */
						'sidebarTitle'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'checkTaxonomy'   => array(
							'type'    => 'string',
							'default' => 'genre',
						),
						'showGenreChecks' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showYearRange'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'yearLabel'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'showRating'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'ratingMax'       => array(
							'type'    => 'number',
							'default' => 9,
						),
						'ratingLabel'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'showDubbed'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'dubbedLabel'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'resetLabel'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'showHint'        => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'hintTitle'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'hintText'        => array(
							'type'    => 'string',
							'default' => '',
						),
						'hintLabel'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'hintUrl'         => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_filter_bar' ),
			),

			/*
			 * نوار مرور برگه‌ی کشف (`.browse-toolbar` مرجع): جستجو، صافی نوع،
			 * دکمه‌ی فیلترهای موبایل، مرتب‌سازی و حالت نمایش.
			 *
			 * چرا بلوک جدا و نه بخشی از «نوار فیلتر»: در مرجع این نوار بالای
			 * چیدمان دوستونه می‌نشیند (نه داخل سایدبار)، هر کنترلش معنای
			 * جداگانه‌ای دارد و بر خلاف سایدبار در موبایل جمع می‌شود. بلوک
			 * بودنش هم یعنی نویسنده در ویرایشگر گرافیکی می‌تواند هر بخش را
			 * جدا خاموش/روشن کند.
			 */
			'manacore/browse-toolbar' => array(
				'title'       => __( 'نوار مرور', 'manacore' ),
				'description' => __( 'نوار بالای برگه‌ی کشف: جستجو، صافی نوع، مرتب‌سازی، حالت نمایش و دکمه‌ی فیلترها برای موبایل.', 'manacore' ),
				'icon'        => 'filter',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'searchPlaceholder' => array(
							'type'    => 'string',
							'default' => '',
						),
						'showSearch'        => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showTypes'         => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSort'          => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showViewMode'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showMobileFilter'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'filterId'          => array(
							'type'    => 'string',
							'default' => 'filter-sidebar',
						),
						/*
						 * حالت «چهره‌ها»: نوار برگه‌ی «بازیگران و عوامل» — جست‌وجوی
						 * درجای کارت‌ها به‌جای ارسال فرم، و دکمه‌های نقش به‌جای تب‌های
						 * نوع محتوا (از تاکسونومی واقعی `person_role`).
						 */
						'mode'              => array(
							'type'    => 'string',
							'default' => 'titles',
						),
						'allLabel'          => array(
							'type'    => 'string',
							'default' => __( 'همه چهره‌ها', 'manacore' ),
						),
					)
				),
				'render'      => array( $this, 'render_browse_toolbar' ),
			),


			/*
			 * «امشب با چه حال‌وهوایی؟» — کالکشن‌های مرجع (collections-grid).
			 */
			'manacore/collection-row' => array(
				'title'       => __( 'ردیف کالکشن‌ها', 'manacore' ),
				'description' => __( 'کارت‌های تصویری کالکشن‌ها با شماره، عنوان، توضیح و فلش — بخش «امشب با چه حال‌وهوایی؟».', 'manacore' ),
				'icon'        => 'grid-view',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'count'          => array(
							'type'    => 'number',
							'default' => 6,
						),
						'columns'        => array(
							'type'    => 'number',
							'default' => 3,
						),
						'columnsTablet'  => array(
							'type'    => 'number',
							'default' => 2,
						),
						'columnsMobile'  => array(
							'type'    => 'number',
							'default' => 1,
						),
						'label'          => array(
							'type'    => 'string',
							'default' => __( 'کالکشن کوهه', 'manacore' ),
						),
						'showNumber'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showDescription'=> array(
							'type'    => 'boolean',
							'default' => true,
						),
					)
				),
				'render'      => array( $this, 'render_collection_row' ),
			),

			/*
			 * «سینورامگ؛ پشت هر قاب، یک داستان» — مقالات مرجع (articles-grid).
			 */
			'manacore/magazine-row'   => array(
				'title'       => __( 'ردیف مجله', 'manacore' ),
				'description' => __( 'کارت‌های مقاله با تصویر، برچسب، زمان مطالعه، تاریخ و خلاصه — بخش «پشت هر قاب، یک داستان».', 'manacore' ),
				'icon'        => 'edit-page',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'count'         => array(
							'type'    => 'number',
							'default' => 3,
						),
						'columns'       => array(
							'type'    => 'number',
							'default' => 3,
						),
						'columnsTablet' => array(
							'type'    => 'number',
							'default' => 2,
						),
						'columnsMobile' => array(
							'type'    => 'number',
							'default' => 1,
						),
						/* روی خودِ برگه‌ی مقاله، نوشته‌ی جاری نباید تکرار شود. */
						'excludeCurrent' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'category'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'badge'         => array(
							'type'    => 'string',
							'default' => __( 'نقد و بررسی', 'manacore' ),
						),
						'showCategoryBadge' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'linkLabel'     => array(
							'type'    => 'string',
							'default' => __( 'ادامه داستان', 'manacore' ),
						),
						'showCategoryTabs' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'categoryTabAllLabel' => array(
							'type'    => 'string',
							'default' => __( 'همه داستان‌ها', 'manacore' ),
						),
						'showReadTime'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showDate'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showExcerpt'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'excerptWords'  => array(
							'type'    => 'number',
							'default' => 18,
						),
					)
				),
				'render'      => array( $this, 'render_magazine_row' ),
			),

			/*
			 * «سرصفحه‌ی مجله» — هم‌ارز `.magazine-feature-grid` مرجع:
			 * یک مقاله‌ی ویژه‌ی بزرگ + دو (یا چند) کارت کوچک کنارش.
			 * هر دو از نوشته‌های واقعی ساخته می‌شوند و دسته/زمان مطالعه
			 * از تاکسونومی و متن خودِ نوشته می‌آید.
			 */
			'manacore/magazine-hero'  => array(
				'title'       => __( 'سرصفحه‌ی مجله', 'manacore' ),
				'description' => __( 'مقاله‌ی ویژه با تصویر تمام‌قد و کارت‌های کوچک کنارِ آن (بخش «پشت هر قاب، یک داستان»).', 'manacore' ),
				'icon'        => 'format-image',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'count'        => array(
							'type'    => 'number',
							'default' => 3,
						),
						'category'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'readLabel'    => array(
							'type'    => 'string',
							'default' => __( '{count} دقیقه مطالعه', 'manacore' ),
						),
						'linkLabel'    => array(
							'type'    => 'string',
							'default' => __( 'ادامه داستان', 'manacore' ),
						),
						'sideIcon'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showExcerpt'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'excerptWords' => array(
							'type'    => 'number',
							'default' => 22,
						),
					)
				),
				'render'      => array( $this, 'render_magazine_hero' ),
			),

			/*
			 * «سرصفحه‌ی مقاله» — هم‌ارز `.article-page-header` مرجع:
			 * نشان دسته، تیتر، توضیح، سطر نویسنده (نگاره + نام + فراداده)
			 * و دکمه‌ی «کپی لینک». همه از خودِ نوشته‌ی جاری می‌آید.
			 */
			'manacore/article-header' => array(
				'title'       => __( 'سرصفحه‌ی مقاله', 'manacore' ),
				'description' => __( 'نشان دسته، تیتر، توضیح و سطر نویسنده‌ی نوشته‌ی جاری با دکمه‌ی کپی لینک (بخش «داستان‌های سینورامگ»).', 'manacore' ),
				'icon'        => 'post-author',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'showCategoryBadge' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showDescription' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'authorFallback' => array(
							'type'    => 'string',
							'default' => __( 'تحریریه کوهه', 'manacore' ),
						),
						'showMeta'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'metaFormat'    => array(
							'type'    => 'string',
							'default' => '{date} · {minutes} دقیقه مطالعه',
						),
						'showCopyLink'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'copyLabel'     => array(
							'type'    => 'string',
							'default' => '↗',
						),
					)
				),
				'render'      => array( $this, 'render_article_header' ),
			),

			/*
			 * «فهرست مقاله» — هم‌ارز `.toc-card` مرجع: فهرست فصل‌های متن
			 * نوشته، نوار پیشرفت مطالعه و درصد خوانده‌شده. فصل‌ها از
			 * تیترهای `h2` خودِ ویرایشگر می‌آیند (نه داده‌ی موازی).
			 */
			'manacore/article-toc' => array(
				'title'       => __( 'فهرست مقاله', 'manacore' ),
				'description' => __( 'کارت «در این داستان می‌خوانی» با فهرست فصل‌ها، نوار پیشرفت و درصد مطالعه.', 'manacore' ),
				'icon'        => 'list-view',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'eyebrow'      => array(
							'type'    => 'string',
							'default' => __( 'در این داستان می‌خوانی', 'manacore' ),
						),
						'heading'      => array(
							'type'    => 'string',
							'default' => __( 'قاب به قاب، همراه ما', 'manacore' ),
						),
						'showNumbers'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showProgress' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'percentLabel' => array(
							'type'    => 'string',
							'default' => '{percent}٪ از داستان را خواندی',
						),
					)
				),
				'render'      => array( $this, 'render_article_toc' ),
			),

			/*
			 * «بعد از خواندن، ببین.» — هم‌ارز `.article-related` مرجع: ردیف
			 * کارت‌های پوستری ستون کنار. منبع: انتخاب دستی نویسنده (گزینشگر)
			 * یا خودکار از هم‌دسته/هم‌برچسب‌ها و در نهایت تازه‌ترین آثار.
			 */
			'manacore/related-titles' => array(
				'title'       => __( 'آثار مرتبط', 'manacore' ),
				'description' => __( 'ردیف «بعد از خواندن، ببین.» با پوستر، عنوان و نام اصلی اثر.', 'manacore' ),
				'icon'        => 'format-video',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'heading'      => array(
							'type'    => 'string',
							'default' => __( 'بعد از خواندن، ببین.', 'manacore' ),
						),
						'count'        => array(
							'type'    => 'number',
							'default' => 3,
						),
						'postTypes'    => array(
							'type'    => 'array',
							'default' => array( 'movie', 'series' ),
							'items'   => array( 'type' => 'string' ),
						),
						/* پین‌کردن یک اثر در ابتدای ردیف (اختیاری). */
						'postId'       => array(
							'type'    => 'number',
							'default' => 0,
						),
						'showOriginalTitle' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showMore'     => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'moreLabel'    => array(
							'type'    => 'string',
							'default' => __( 'همه‌ی آثار', 'manacore' ),
						),
						'moreUrl'      => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_related_titles' ),
			),

			/*
			 * «هر روز، یک قسمت تازه» — معادل بخش home-duo مرجع.
			 *
			 * منبع داده: قسمت‌ها با تاریخ پخش (`manacore_air_date`). هر قسمت به
			 * روز هفته‌ی خودش نسبت داده می‌شود و تب‌های روز، پنل همان روز را
			 * نشان می‌دهند؛ اگر تاریخ پخشی ثبت نشده باشد، برچسب «program» پست
			 * به‌عنوان روز پخش خوانده می‌شود.
			 */
			'manacore/schedule'       => array(
				'title'       => __( 'برنامه‌ی هفتگی', 'manacore' ),
				'description' => __( 'تب‌های روزهای هفته و فهرست قسمت‌های همان روز — بخش «هر روز، یک قسمت تازه».', 'manacore' ),
				'icon'        => 'calendar-alt',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'perDay'      => array(
							'type'    => 'number',
							'default' => 5,
						),
						'activeDay'   => array(
							'type'    => 'string',
							'default' => 'today',
						),
						'showTime'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showThumb'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showEpisode' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'footnote'    => array(
							'type'    => 'string',
							'default' => '',
						),
						/*
						 * دو چیزِ تازه برای هم‌ارزی با برگه‌ی «برنامه پخش» مرجع:
						 * `mode` منبع داده را عوض می‌کند (قسمت‌ها یا سریال‌های
						 * زمان‌بندی‌شده) و `layout: panel` قاب `.schedule-panel
						 * .full-schedule` مرجع را با سرصفحه‌ی خودش می‌سازد.
						 */
						'layout'      => array(
							'type'    => 'string',
							'default' => 'block',
						),
						'mode'        => array(
							'type'    => 'string',
							'default' => 'episode',
						),
						'postTypes'   => array(
							'type'    => 'array',
							'default' => array( 'series' ),
							'items'   => array( 'type' => 'string' ),
						),
						'panelTitle'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'panelSubtitle' => array(
							'type'    => 'string',
							'default' => '',
						),
						'panelIcon'   => array(
							'type'    => 'string',
							'default' => '▦',
						),
						'showTimezone' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'timezoneLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
						'showOriginalTitle' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showSeasonMeta' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showPlay'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showFootnote' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'emptyMessage' => array(
							'type'    => 'string',
							'default' => '',
						),
						'emptyLinkLabel' => array(
							'type'    => 'string',
							'default' => '',
						),
						'emptyLinkUrl' => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_schedule' ),
			),

			'manacore/info-card'      => array(
				'title'       => __( 'کارت اطلاعاتی', 'manacore' ),
				'description' => __( 'کارت یادداشت یا ترویجی ستون کنار (هم‌ارز `.schedule-note-card` و `.sidebar-promo` مرجع).', 'manacore' ),
				'icon'        => 'info-outline',
				'attributes'  => array(
					'variant'     => array(
						'type'    => 'string',
						'default' => 'note',
					),
					'iconText'    => array(
						'type'    => 'string',
						'default' => '◷',
					),
					'title'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'text'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'metaText'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'showMetaDot' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'linkLabel'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'linkUrl'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( $this, 'render_info_card' ),
			),

			/*
			 * «سلیقه‌ات را کشف کن.» — بنر سمت راست home-duo.
			 */
			'manacore/taste-banner'   => array(
				'title'       => __( 'بنر سلیقه', 'manacore' ),
				'description' => __( 'بنر گرادیانی با آیکون‌های تزئینی، برچسب انگلیسی و پیوند — بخش «سلیقه‌ات را کشف کن.».', 'manacore' ),
				'icon'        => 'star-filled',
				'attributes'  => array(
					'eyebrow'     => array(
						'type'    => 'string',
						'default' => __( 'به اندازه خودت، متفاوت', 'manacore' ),
					),
					'heading'     => array(
						'type'    => 'string',
						'default' => __( 'سلیقه‌ات را کشف کن.', 'manacore' ),
					),
					'text'        => array(
						'type'    => 'string',
						'default' => __( 'از داستان‌هایی که دوست داری، به داستان‌هایی که عاشقشان می‌شوی.', 'manacore' ),
					),
					'linkLabel'   => array(
						'type'    => 'string',
						'default' => __( 'دنیای مخصوص من', 'manacore' ),
					),
					'linkUrl'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'badge'       => array(
						'type'    => 'string',
						'default' => 'MADE FOR YOU',
					),
					'showArt'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render'      => array( $this, 'render_taste_banner' ),
			),

			/*
			 * «سرصفحه‌ی برگه» — هم‌ارز `.page-title-row` / `.info-intro` /
			 * `.magazine-intro` مرجع.
			 *
			 * مرجع همین یک الگو را در پنج برگه تکرار می‌کند (پخش زنده، بازیگران،
			 * سینورامگ، راهنما، حریم خصوصی) با سه چیدمان متفاوت؛ پس یک بلوک
			 * مشترک با سه «چیدمان» ساخته می‌شود تا پاریتی بدون تکرار کد به‌دست
			 * آید. هیچ متنی در کد نیست: همه از ویژگی‌های ویرایشگر می‌آید.
			 */
			'manacore/page-intro'   => array(
				'title'       => __( 'سرصفحه‌ی برگه', 'manacore' ),
				'description' => __( 'مسیر راهنما، ریزسطر، عنوان، توضیح، آیکون و ساعت زنده — در سه چیدمان هم‌ارز مرجع.', 'manacore' ),
				'icon'        => 'editor-textcolor',
				'attributes'  => array(
					'showBreadcrumb' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'breadcrumbHome' => array(
						'type'    => 'string',
						'default' => '',
					),
					'breadcrumbLabel' => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'      => array(
						'type'    => 'string',
						'default' => 'split',
					),
					'eyebrow'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'title'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'accent'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'text'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'headingTag'  => array(
						'type'    => 'string',
						'default' => 'h1',
					),
					'icon'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'iconBox'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showClock'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'clockLabel'  => array(
						'type'    => 'string',
						'default' => __( 'تهران', 'manacore' ),
					),
					'clockTimezone' => array(
						'type'    => 'string',
						'default' => 'Asia/Tehran',
					),
				),
				'render'      => array( $this, 'render_page_intro' ),
			),

			/*
			 * «بازیگران و عوامل» — هم‌ارز `.people-grid`/`.person-card` و
			 * `.cast-grid`/`.cast-card` در `cinora/cast.html`.
			 */
			'manacore/people-grid'  => array(
				'title'       => __( 'شبکه‌ی چهره‌ها', 'manacore' ),
				'description' => __( 'کارت چهره‌ها با نشان نقش، نام لاتین و شمار واقعی آثار — دو حالت «کارت بلند» (برگه‌ی بازیگران) و «کارت کوچک» (بخش چهره‌های دیگر).', 'manacore' ),
				'icon'        => 'groups',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					Block_Support::header_attributes(),
					array(
						'variant'    => array(
							'type'    => 'string',
							'default' => 'cards',
						),
						'count'      => array(
							'type'    => 'number',
							'default' => 12,
						),
						'columns'    => array(
							'type'    => 'number',
							'default' => 4,
						),
						'roles'      => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'orderby'    => array(
							'type'    => 'string',
							'default' => 'menu_order',
						),
						'excludeCurrent' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showWorks'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'worksLabel' => array(
							'type'    => 'string',
							'default' => __( '{count} اثر در کوهه', 'manacore' ),
						),
						'emptyHeading' => array(
							'type'    => 'string',
							'default' => __( 'این چهره در کاتالوگ فعلی پیدا نشد.', 'manacore' ),
						),
						'countLabel' => array(
							'type'    => 'string',
							'default' => __( '{count} چهره', 'manacore' ),
						),
					)
				),
				'render'      => array( $this, 'render_people_grid' ),
			),

			/*
			 * «دکمه‌ی پیوند» — دکمه‌های پوسته (`.button`/`.button.primary`).
			 * بلوک هسته‌ی دکمه کلاس‌های خودش را می‌آورد و با پوسته یکی
			 * نمی‌شود؛ این بلوک همان مارک‌آپ مرجع را با داده‌ی ویرایشگر
			 * می‌سازد (برگه‌ی چهره: «آثار در کوهه ‹»).
			 */
			'manacore/cta-link'     => array(
				'title'       => __( 'دکمه‌ی پیوند', 'manacore' ),
				'description' => __( 'یک دکمه‌ی پیوند با گونه‌های پوسته (اصلی، دوم، شیشه‌ای) و نشانه‌ی فلش.', 'manacore' ),
				'icon'        => 'button',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'label'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'url'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'variant'     => array(
							'type'    => 'string',
							'default' => 'primary',
						),
						'small'       => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'showChevron' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'target'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'rel'         => array(
							'type'    => 'string',
							'default' => '',
						),
					)
				),
				'render'      => array( $this, 'render_cta_link' ),
			),

			/*
			 * «شناسنامه‌ی چهره» — هم‌ارز `.person-english` + `.person-facts`
			 * در `.person-copy` مرجع (نام لاتین، زادروز، کشور، شمار آثار).
			 */
			'manacore/person-meta'  => array(
				'title'       => __( 'شناسنامه‌ی چهره', 'manacore' ),
				'description' => __( 'نام لاتین و ردیف دانستنی‌ها (زادروز، کشور، شمار واقعی آثار) برای برگه‌ی چهره.', 'manacore' ),
				'icon'        => 'id-alt',
				'attributes'  => Block_Support::compose(
					Block_Support::presentation_attributes(),
					array(
						'postId'      => array(
							'type'    => 'number',
							'default' => 0,
						),
						'variant'     => array(
							'type'    => 'string',
							'default' => 'identity',
						),
						'showEnglish' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showBorn'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCountry' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showWorks'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'worksLabel'  => array(
							'type'    => 'string',
							'default' => __( '{count} اثر در کوهه', 'manacore' ),
						),
					)
				),
				'render'      => array( $this, 'render_person_meta' ),
			),

			/*
			 * «مسیر صفحه» — هم‌ارز `div.breadcrumb` مرجع. `page-intro` هم
			 * مسیر دارد، اما آن مخصوص سرصفحه است؛ برگه‌ی چهره مسیر سه‌پله‌ی
			 * جداگانه‌ی خودش را بالای `.person-hero` می‌خواهد.
			 */
			'manacore/breadcrumb'   => array(
				'title'       => __( 'مسیر صفحه', 'manacore' ),
				'description' => __( 'مسیر راهنما (خانه › … › صفحه‌ی جاری) با جدامایه‌ی نشانه‌ای یا متنی.', 'manacore' ),
				'icon'        => 'admin-links',
				'attributes'  => array(
					'homeLabel'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'parentLabel' => array(
						'type'    => 'string',
						'default' => '',
					),
					'parentUrl'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'currentLabel' => array(
						'type'    => 'string',
						'default' => '',
					),
					'separator'   => array(
						'type'    => 'string',
						'default' => 'chevron',
					),
					'extraClass'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'anchor'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( $this, 'render_breadcrumb' ),
			),

			/*
			 * «پخش زنده» — هم‌ارز `section.live-player` در `cinora/live.html`.
			 *
			 * قاب ویدئو، نوار «در حال پخش»، یادداشت، و «قاب‌های امروز» که از
			 * **زمان‌بندی واقعی پروژه** (`manacore_air_day`/`manacore_air_time`)
			 * خوانده می‌شود، نه از داده‌ی نمایشی.
			 */
			'manacore/live-player'  => array(
				'title'       => __( 'پخش زنده', 'manacore' ),
				'description' => __( 'پخش‌کننده‌ی کانال با قاب ویدئو، نوار «در حال پخش»، یادداشت و شبکه‌ی قاب‌های امروز از زمان‌بندی واقعی.', 'manacore' ),
				'icon'        => 'video-alt3',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'channelId'    => array(
							'type'    => 'number',
							'default' => 0,
						),
						'onAirLabel'   => array(
							'type'    => 'string',
							'default' => __( 'پخش زنده', 'manacore' ),
						),
						'nowLabel'     => array(
							'type'    => 'string',
							'default' => __( 'در حال پخش', 'manacore' ),
						),
						'scheduleLabel' => array(
							'type'    => 'string',
							'default' => __( 'زمان‌بندی نمایشی', 'manacore' ),
						),
						'fallbackLabel' => array(
							'type'    => 'string',
							'default' => __( 'برنامه‌ی نزدیک', 'manacore' ),
						),
						'clockGlyph'   => array(
							'type'    => 'string',
							'default' => '◷',
						),
						'autoplay'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'titleOverride' => array(
							'type'    => 'string',
							'default' => '',
						),
						'subtitleOverride' => array(
							'type'    => 'string',
							'default' => '',
						),
						'showNotice'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'noticeText'   => array(
							'type'    => 'string',
							'default' => __( 'این نسخه چرخه‌ی آزمایشی ویدئو را نمایش می‌دهد. کانال و کیفیت را تغییر بده و تجربه را امتحان کن.', 'manacore' ),
						),
						'errorText'    => array(
							'type'    => 'string',
							'default' => __( 'ویدئو پخش نشد.', 'manacore' ),
						),
						'retryLabel'   => array(
							'type'    => 'string',
							'default' => __( 'تلاش دوباره', 'manacore' ),
						),
						'showProgram'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'programCount' => array(
							'type'    => 'number',
							'default' => 3,
						),
						'programFallback' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'programTypes' => array(
							'type'    => 'array',
							'default' => array( 'series', 'anime' ),
							'items'   => array( 'type' => 'string' ),
						),
					),
				),
				'render'      => array( $this, 'render_live_player' ),
			),

			/*
			 * «فهرست کانال‌ها» — هم‌ارز `aside.live-channels` مرجع.
			 * فهرست از نوع محتوای `channel` می‌آید و هر کارت یک پیوند واقعی
			 * است؛ بدون جاوااسکریپت هم جابه‌جایی کانال کار می‌کند.
			 */
			'manacore/live-channels' => array(
				'title'       => __( 'فهرست کانال‌ها', 'manacore' ),
				'description' => __( 'ستون کنار پخش زنده: سرتیتر، فهرست کانال‌ها از پیشخوان، و کارت یادداشت.', 'manacore' ),
				'icon'        => 'playlist-video',
				'attributes'  => array(
					'heading'     => array(
						'type'    => 'string',
						'default' => __( 'کانال‌ها', 'manacore' ),
					),
					'headingIcon' => array(
						'type'    => 'string',
						'default' => 'tv',
					),
					'countLabel'  => array(
						'type'    => 'string',
						'default' => __( '{count} کانال', 'manacore' ),
					),
					'onlineLabel' => array(
						'type'    => 'string',
						'default' => __( 'پخش', 'manacore' ),
					),
					'qualitySuffix' => array(
						'type'    => 'string',
						'default' => __( 'نمونه آزاد', 'manacore' ),
					),
					'limit'       => array(
						'type'    => 'number',
						'default' => 0,
					),
					'showNote'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					/*
					 * مرجع در `live.html` کارت یادداشت را **بی‌نشانه** رندر
					 * می‌کند (قاعده‌ی `>svg` در CSS هست ولی گره‌ای در HTML نیست)،
					 * پس پیش‌فرض خالی است تا هندسه بیت‌به‌بیت مرجع بماند؛ مدیر
					 * می‌تواند از ویرایشگر نشانه بگذارد.
					 */
					'noteIcon'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'noteTitle'   => array(
						'type'    => 'string',
						'default' => __( 'سینما، بدون فاصله.', 'manacore' ),
					),
					'noteText'    => array(
						'type'    => 'string',
						'default' => __( 'کانال دلخواهت را انتخاب کن؛ بقیه‌ی ماجرا را با خیال راحت به ما بسپار.', 'manacore' ),
					),
					'noteLinkLabel' => array(
						'type'    => 'string',
						'default' => __( 'کشف فیلم‌ها', 'manacore' ),
					),
					'noteLinkUrl' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( $this, 'render_live_channels' ),
			),

			/*
			 * «حساب کاربری» — هم‌ارز `account.html` مرجع.
			 *
			 * سه بلوک کوچک به‌جای یک بلوک غول: سرصفحه (`account-greeting`)،
			 * ستون کنار (`account-nav`) و ظرف تب‌ها (`account-panel`).
			 * هر بخش داده‌ی واقعی خودش را از کلاس Account می‌گیرد.
			 */
			'manacore/account-greeting' => array(
				'title'       => __( 'سرصفحه‌ی حساب کاربری', 'manacore' ),
				'description' => __( 'خوش‌آمدِ بالای برگه‌ی حساب با نام واقعی کاربر (هم‌ارز `.account-greeting` مرجع).', 'manacore' ),
				'icon'        => 'admin-users',
				'attributes'  => array(
					'eyebrow'         => array(
						'type'    => 'string',
						'default' => 'YOUR OWN UNIVERSE',
					),
					'guestHeading'    => array(
						'type'    => 'string',
						'default' => __( 'مهمان عزیز، اینجا دنیای توست.', 'manacore' ),
					),
					'userHeading'     => array(
						'type'    => 'string',
						'default' => __( '{name}، اینجا دنیای توست.', 'manacore' ),
					),
					'text'            => array(
						'type'    => 'string',
						'default' => __( 'هر داستانی که می‌بینی، ما را یک قدم به سلیقه‌ات نزدیک‌تر می‌کند.', 'manacore' ),
					),
					'guestButtonLabel'=> array(
						'type'    => 'string',
						'default' => __( 'ساخت حساب', 'manacore' ),
					),
					'userButtonLabel' => array(
						'type'    => 'string',
						'default' => __( 'ویرایش پروفایل', 'manacore' ),
					),
					'loginButtonLabel' => array(
						'type'    => 'string',
						'default' => __( 'ورود به حساب', 'manacore' ),
					),
					'buttonUrl'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonTab'       => array(
						'type'    => 'string',
						'default' => 'settings',
					),
					'showButton'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render'      => array( $this, 'render_account_greeting' ),
			),

			'manacore/account-nav'    => array(
				'title'       => __( 'ستون کنار حساب کاربری', 'manacore' ),
				'description' => __( 'پروفایل، تب‌ها، خروج و کارت ارتقا (هم‌ارز `.account-sidebar` مرجع).', 'manacore' ),
				'icon'        => 'menu-alt',
				'attributes'  => array(
					'navLabel'        => array(
						'type'    => 'string',
						'default' => __( 'بخش‌های حساب کاربری', 'manacore' ),
					),
					'showProfile'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showLogout'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showUpgrade'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'upgradeHeading'  => array(
						'type'    => 'string',
						'default' => __( 'داستان‌ها را بیشتر زندگی کن.', 'manacore' ),
					),
					'upgradeLabel'    => array(
						'type'    => 'string',
						'default' => __( 'کشف کوهه‌فیلم پلاس', 'manacore' ),
					),
					'upgradeUrl'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( $this, 'render_account_nav' ),
			),

			'manacore/account-stats'  => array(
				'title'       => __( 'شمارنده‌های حساب', 'manacore' ),
				'description' => __( 'چهار کارت آماری برگه‌ی حساب: لیست تماشا، دیده‌شده، دقیقه و ژانر (هم‌ارز `.stat-grid` مرجع).', 'manacore' ),
				'icon'        => 'chart-bar',
				'attributes'  => array(
					'showWatchlist'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showWatched'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMinutes'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showGenre'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'watchlistIcon'   => array(
						'type'    => 'string',
						'default' => '▣',
					),
					'watchedIcon'     => array(
						'type'    => 'string',
						'default' => '◉',
					),
					'minutesIcon'     => array(
						'type'    => 'string',
						'default' => '◷',
					),
					'genreIcon'       => array(
						'type'    => 'string',
						'default' => '♥',
					),
					'watchlistLabel'  => array(
						'type'    => 'string',
						'default' => __( 'داستان در لیست تماشا', 'manacore' ),
					),
					'watchedLabel'    => array(
						'type'    => 'string',
						'default' => __( 'داستان تماشاشده', 'manacore' ),
					),
					'minutesLabel'    => array(
						'type'    => 'string',
						'default' => __( 'دقیقه تماشای نمونه', 'manacore' ),
					),
					'genreLabel'      => array(
						'type'    => 'string',
						'default' => __( 'ژانر موردعلاقه', 'manacore' ),
					),
					'genreEmptyText'  => array(
						'type'    => 'string',
						'default' => __( 'هنوز کشف نشده', 'manacore' ),
					),
				),
				'render'      => array( $this, 'render_account_stats' ),
			),

			'manacore/account-panel'  => array(
				'title'       => __( 'تب حساب کاربری', 'manacore' ),
				'description' => __( 'ظرف یک تب از برگه‌ی حساب (`data-panel`). هر بلوکی را می‌توان داخلش گذاشت.', 'manacore' ),
				'icon'        => 'index-card',
				'attributes'  => array(
					'tab'   => array(
						'type'    => 'string',
						'default' => 'overview',
					),
					'label' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render'      => array( $this, 'render_account_panel' ),
			),

			'manacore/account-welcome' => array(
				'title'       => __( 'بنر خوش‌آمد حساب', 'manacore' ),
				'description' => __( 'بنر «سلیقه‌ات به اندازه خودت، خاص است.» بالای تب «دنیای من» (هم‌ارز `.account-welcome-banner` مرجع).', 'manacore' ),
				'icon'        => 'star-filled',
				'attributes'  => array(
					'eyebrow'        => array(
						'type'    => 'string',
						'default' => __( 'داستان بعدی، به سلیقه تو', 'manacore' ),
					),
					'title'          => array(
						'type'    => 'string',
						'default' => __( 'سلیقه‌ات به اندازه خودت، خاص است.', 'manacore' ),
					),
					'titleWithGenre' => array(
						'type'    => 'string',
						'default' => __( 'انگار به دنیای {genre} علاقه داری!', 'manacore' ),
					),
					'text'           => array(
						'type'    => 'string',
						'default' => __( 'اولین اثر را به لیست تماشایت اضافه کن تا دنیای مخصوص تو شکل بگیرد.', 'manacore' ),
					),
					'textWithGenre'  => array(
						'type'    => 'string',
						'default' => __( 'پیشنهادهای مخصوصت را بر اساس لیست تماشا، امتیازها و سابقه‌ات آماده کردیم.', 'manacore' ),
					),
					'linkLabel'      => array(
						'type'    => 'string',
						'default' => __( 'کشف سلیقه من', 'manacore' ),
					),
					'linkTab'        => array(
						'type'    => 'string',
						'default' => 'analytics',
					),
					'artIcon'        => array(
						'type'    => 'string',
						'default' => '✦',
					),
					'showArt'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render'      => array( $this, 'render_account_welcome' ),
			),

			'manacore/account-history' => array(
				'title'       => __( 'تاریخچه تماشا', 'manacore' ),
				'description' => __( 'فهرست آثار دیده‌شده با نوار پیشرفت و پیوند «ادامه تماشا» (هم‌ارز `data-panel=\"history\"` مرجع).', 'manacore' ),
				'icon'        => 'backup',
				'attributes'  => array(
					'heading'         => array(
						'type'    => 'string',
						'default' => __( 'داستان‌هایی که با تو همراه شدند', 'manacore' ),
					),
					'subheading'      => array(
						'type'    => 'string',
						'default' => __( 'پیشرفت تماشای نمونه‌های ویدئویی تو', 'manacore' ),
					),
					'clearLabel'      => array(
						'type'    => 'string',
						'default' => __( 'پاک کردن تاریچه', 'manacore' ),
					),
					'showClear'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'limit'           => array(
						'type'    => 'number',
						'default' => 12,
					),
					'resumeLabel'     => array(
						'type'    => 'string',
						'default' => __( 'ادامه تماشا', 'manacore' ),
					),
					'completeLabel'   => array(
						'type'    => 'string',
						'default' => __( 'تماشای نمونه کامل شده', 'manacore' ),
					),
					'percentLabel'    => array(
						'type'    => 'string',
						'default' => __( '{percent}٪ تماشا شده', 'manacore' ),
					),
					'emptyTitle'      => array(
						'type'    => 'string',
						'default' => __( 'هر داستان، یک ردپا.', 'manacore' ),
					),
					'emptyText'       => array(
						'type'    => 'string',
						'default' => __( 'با شروع تماشا، داستان‌ها و پیشرفتت در این‌جا ذخیره می‌شوند.', 'manacore' ),
					),
					'emptyLinkLabel'  => array(
						'type'    => 'string',
						'default' => __( 'شروع یک داستان', 'manacore' ),
					),
					'emptyLinkUrl'    => array(
						'type'    => 'string',
						'default' => 'discovery',
					),
				),
				'render'      => array( $this, 'render_account_history' ),
			),

			'manacore/account-analytics' => array(
				'title'       => __( 'تحلیل سلیقه', 'manacore' ),
				'description' => __( 'چهار کارت تحلیل: حلقه‌ی ژانرها، سهم فیلم/سریال، نمودار هفته و کشورها (هم‌ارز `data-panel=\"analytics\"` مرجع).', 'manacore' ),
				'icon'        => 'chart-pie',
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => __( 'سلیقه‌ات، به زبان داستان‌ها', 'manacore' ),
					),
					'subheading'     => array(
						'type'    => 'string',
						'default' => __( 'تحلیل بر اساس لیست تماشا، پیشرفت و امتیازهای تو', 'manacore' ),
					),
					'genreTitle'     => array(
						'type'    => 'string',
						'default' => __( 'دنیای ژانرهای تو', 'manacore' ),
					),
					'genreText'      => array(
						'type'    => 'string',
						'default' => __( 'سهم هر ژانر از علاقه‌مندی‌ها و تماشا', 'manacore' ),
					),
					'donutEmptyTitle' => array(
						'type'    => 'string',
						'default' => __( 'دنیای تو', 'manacore' ),
					),
					'donutEmptyText' => array(
						'type'    => 'string',
						'default' => __( 'منتظر اولین داستان', 'manacore' ),
					),
					'legendEmptyText' => array(
						'type'    => 'string',
						'default' => __( 'با اولین انتخابت شروع کن.', 'manacore' ),
					),
					'formatTitle'    => array(
						'type'    => 'string',
						'default' => __( 'فیلم یا سریال؟', 'manacore' ),
					),
					'formatText'     => array(
						'type'    => 'string',
						'default' => __( 'چه نوع داستانی بیشتر جذبَت می‌کند؟', 'manacore' ),
					),
					'movieIcon'      => array(
						'type'    => 'string',
						'default' => '◉',
					),
					'seriesIcon'     => array(
						'type'    => 'string',
						'default' => '▣',
					),
					'activityTitle'  => array(
						'type'    => 'string',
						'default' => __( 'هفته تو، قاب به قاب', 'manacore' ),
					),
					'activityText'   => array(
						'type'    => 'string',
						'default' => __( 'تعداد داستان‌های تماشاشده در هفت روز اخیر', 'manacore' ),
					),
					'countryTitle'   => array(
						'type'    => 'string',
						'default' => __( 'داستان‌هایت از کجا می‌آیند؟', 'manacore' ),
					),
					'countryText'    => array(
						'type'    => 'string',
						'default' => __( 'کشورهای سازنده آثار انتخابی', 'manacore' ),
					),
					'countryEmptyText' => array(
						'type'    => 'string',
						'default' => __( 'هنوز سفری شروع نشده است.', 'manacore' ),
					),
					'privacyText'    => array(
						'type'    => 'string',
						'default' => __( 'تحلیل‌ها فقط از فعالیت خودت ساخته می‌شوند؛ اطلاعاتت با دیگران به اشتراک گذاشته نمی‌شود.', 'manacore' ),
					),
					'privacyIcon'    => array(
						'type'    => 'string',
						'default' => '◈',
					),
					'showGenreCard'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showFormatCard' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showActivityCard' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCountryCard' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render'      => array( $this, 'render_account_analytics' ),
			),

			'manacore/account-invoices' => array(
				'title'       => __( 'اشتراک و صورت‌حساب', 'manacore' ),
				'description' => __( 'وضعیت اشتراک واقعی کاربر و فهرست سفارش‌ها (هم‌ارز `data-panel=\"subscription\"` مرجع).', 'manacore' ),
				'icon'        => 'money-alt',
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => __( 'اشتراک و صورت‌حساب‌ها', 'manacore' ),
					),
					'subheading'     => array(
						'type'    => 'string',
						'default' => __( 'شفاف، ساده و همیشه در دسترس', 'manacore' ),
					),
					'statusIcon'     => array(
						'type'    => 'string',
						'default' => '♛',
					),
					'statusEyebrow'  => array(
						'type'    => 'string',
						'default' => 'KOOHE PLUS',
					),
					'activeTitle'    => array(
						'type'    => 'string',
						'default' => __( 'اشتراک {plan} فعال است.', 'manacore' ),
					),
					'activeText'     => array(
						'type'    => 'string',
						'default' => __( 'اعتبار تا {date} · {days} روز باقی‌مانده.', 'manacore' ),
					),
					'guestTitle'     => array(
						'type'    => 'string',
						'default' => __( 'داستان‌های بیشتر، منتظر تو هستند.', 'manacore' ),
					),
					'guestText'      => array(
						'type'    => 'string',
						'default' => __( 'با انتخاب اشتراک به همه‌ی کیفیت‌ها و لینک‌های ویژه دسترسی پیدا می‌کنی.', 'manacore' ),
					),
					'guestButtonLabel' => array(
						'type'    => 'string',
						'default' => __( 'انتخاب اشتراک', 'manacore' ),
					),
					'manageLabel'    => array(
						'type'    => 'string',
						'default' => __( 'مدیریت اشتراک', 'manacore' ),
					),
					'invoicesTitle'  => array(
						'type'    => 'string',
						'default' => __( 'صورت‌حساب‌های من', 'manacore' ),
					),
					'planLabel'      => array(
						'type'    => 'string',
						'default' => __( 'طرح اشتراک', 'manacore' ),
					),
					'dateLabel'      => array(
						'type'    => 'string',
						'default' => __( 'تاریخ', 'manacore' ),
					),
					'amountLabel'    => array(
						'type'    => 'string',
						'default' => __( 'مبلغ', 'manacore' ),
					),
					'statusLabel'    => array(
						'type'    => 'string',
						'default' => __( 'وضعیت', 'manacore' ),
					),
					'emptyTitle'     => array(
						'type'    => 'string',
						'default' => __( 'هنوز صورت‌حسابی نداری.', 'manacore' ),
					),
					'emptyText'      => array(
						'type'    => 'string',
						'default' => __( 'پس از تهیه‌ی اشتراک، رسید آن در این بخش ذخیره می‌شود.', 'manacore' ),
					),
					'limit'          => array(
						'type'    => 'number',
						'default' => 5,
					),
				),
				'render'      => array( $this, 'render_account_invoices' ),
			),

			'manacore/account-settings' => array(
				'title'       => __( 'تنظیمات حساب', 'manacore' ),
				'description' => __( 'پروفایل، تنظیمات تماشا، امنیت حساب و خروجی داده (هم‌ارز `data-panel=\"settings\"` مرجع).', 'manacore' ),
				'icon'        => 'admin-generic',
				'attributes'  => array(
					'heading'        => array(
						'type'    => 'string',
						'default' => __( 'پروفایلت، به سلیقه خودت', 'manacore' ),
					),
					'avatarButton'   => array(
						'type'    => 'string',
						'default' => __( 'انتخاب عکس', 'manacore' ),
					),
					'avatarHint'     => array(
						'type'    => 'string',
						'default' => __( 'عکس تو، در هدر و پروفایل نمایش داده می‌شود.', 'manacore' ),
					),
					'removeAvatar'   => array(
						'type'    => 'string',
						'default' => __( 'حذف عکس', 'manacore' ),
					),
					'nameLabel'      => array(
						'type'    => 'string',
						'default' => __( 'نام نمایشی', 'manacore' ),
					),
					'emailLabel'     => array(
						'type'    => 'string',
						'default' => __( 'ایمیل حساب', 'manacore' ),
					),
					'emailHint'      => array(
						'type'    => 'string',
						'default' => __( 'ایمیل برای امنیت حساب قابل تغییر نیست.', 'manacore' ),
					),
					'prefsTitle'     => array(
						'type'    => 'string',
						'default' => __( 'تجربه تماشای من', 'manacore' ),
					),
					'notifyTitle'    => array(
						'type'    => 'string',
						'default' => __( 'اعلان‌های کوهه‌فیلم', 'manacore' ),
					),
					'notifyText'     => array(
						'type'    => 'string',
						'default' => __( 'خبرهای تازه و برنامه پخش را دریافت کن.', 'manacore' ),
					),
					'autoplayTitle'  => array(
						'type'    => 'string',
						'default' => __( 'پخش خودکار نمونه', 'manacore' ),
					),
					'autoplayText'   => array(
						'type'    => 'string',
						'default' => __( 'هنگام ورود به پلیر، نمونه را خودکار پخش کن.', 'manacore' ),
					),
					'qualityTitle'   => array(
						'type'    => 'string',
						'default' => __( 'کیفیت پیش‌فرض', 'manacore' ),
					),
					'qualityText'    => array(
						'type'    => 'string',
						'default' => __( 'کیفیت دلخواهت برای پخش ویدئوها.', 'manacore' ),
					),
					'saveLabel'      => array(
						'type'    => 'string',
						'default' => __( '✓ ذخیره تغییرات', 'manacore' ),
					),
					'passwordTitle'  => array(
						'type'    => 'string',
						'default' => __( 'امنیت حساب', 'manacore' ),
					),
					'passwordText'   => array(
						'type'    => 'string',
						'default' => __( 'یک رمز منحصربه‌فرد و حداقل ۸ کاراکتری انتخاب کن.', 'manacore' ),
					),
					'currentLabel'   => array(
						'type'    => 'string',
						'default' => __( 'رمز فعلی', 'manacore' ),
					),
					'newLabel'       => array(
						'type'    => 'string',
						'default' => __( 'رمز جدید', 'manacore' ),
					),
					'confirmLabel'   => array(
						'type'    => 'string',
						'default' => __( 'تکرار رمز جدید', 'manacore' ),
					),
					'changeLabel'    => array(
						'type'    => 'string',
						'default' => __( 'تغییر رمز عبور', 'manacore' ),
					),
					'exportTitle'    => array(
						'type'    => 'string',
						'default' => __( 'داده‌هایت متعلق به توست.', 'manacore' ),
					),
					'exportText'     => array(
						'type'    => 'string',
						'default' => __( 'یک نسخه از پروفایل، لیست تماشا و تاریخچه‌ات دریافت کن.', 'manacore' ),
					),
					'exportLabel'    => array(
						'type'    => 'string',
						'default' => __( 'دریافت داده‌ها', 'manacore' ),
					),
				),
				'render'      => array( $this, 'render_account_settings' ),
			),

			'manacore/search-box'     => array(
				'title'       => __( 'جستجوی زنده', 'manacore' ),
				'description' => __( 'کادر جستجو با نتایج آنی و محدودسازی به نوع‌های محتوای دلخواه.', 'manacore' ),
				'icon'        => 'search',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'placeholder'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'buttonLabel'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'showButton'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showIcon'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'liveResults'  => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'resultCount'  => array(
							'type'    => 'number',
							'default' => 8,
						),
						'searchTypes'  => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
						'inputSize'    => array(
							'type'    => 'string',
							'default' => 'medium',
						),
					)
				),
				'render'      => array( $this, 'render_search_box' ),
			),

			'manacore/episodes-list'  => array(
				'title'       => __( 'فهرست قسمت‌ها', 'manacore' ),
				'description' => __( 'قسمت‌های سریال یا انیمه به تفکیک فصل با ترتیب و اجزای دلخواه.', 'manacore' ),
				'icon'        => 'playlist-video',
				'attributes'  => Block_Support::compose(
					Block_Support::header_attributes(),
					array(
						'boxStyle'       => array(
							'type'    => 'string',
							'default' => 'cards',
						),
						'showNotice'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'packTitle'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'sizeLabel'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'playLabel'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'showPackList'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'seasonNumber'   => array(
							'type'    => 'number',
							'default' => 0,
						),
						'episodeOrder'   => array(
							'type'    => 'string',
							'default' => 'ASC',
						),
						'seasonOrder'    => array(
							'type'    => 'string',
							'default' => 'ASC',
						),
						'limit'          => array(
							'type'    => 'number',
							'default' => 0,
						),
						'openSeason'     => array(
							'type'    => 'string',
							'default' => 'first',
						),
						'showAirDate'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showEpisodeName'=> array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showIcon'       => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showCount'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'showThumb'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'postId'         => array(
							'type'    => 'number',
							'default' => 0,
						),
					)
				),
				'render'      => array( $this, 'render_episodes' ),
			),
		);

		/**
		 * فیلتر تعریف بلوک‌های ManaCore.
		 *
		 * @param array $blocks تعریف‌ها.
		 */
		return (array) apply_filters( 'manacore_block_definitions', $blocks );
	}

	/**
	 * فهرست بلوک‌ها برای ثبت در سمت ویرایشگر (JS).
	 *
	 * ساختار ویژگی‌ها و پشتیبانی‌ها یک‌بار در PHP تعریف می‌شود و همان
	 * تعریف برای ثبت سمت جاوااسکریپت هم استفاده می‌گردد تا هیچ‌گاه
	 * دو فهرست ناهمگون نداشته باشیم.
	 *
	 * @return array
	 */
	public function editor_registry() {
		$registry = array();

		foreach ( $this->definitions() as $name => $block ) {
			$registry[ $name ] = array(
				'title'       => $block['title'],
				'description' => $block['description'],
				'icon'        => $block['icon'],
				'category'    => 'manacore',
				'attributes'  => $block['attributes'],
				'supports'    => isset( $block['supports'] ) ? $block['supports'] : $this->default_supports(),
			);
		}

		return $registry;
	}

	/**
	 * ثبت بلوک‌ها در سمت سرور.
	 */
	public function register() {
		foreach ( $this->definitions() as $name => $block ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => $block['title'],
					'description'     => $block['description'],
					'category'        => 'manacore',
					'icon'            => $block['icon'],
					'attributes'      => $block['attributes'],
					'render_callback' => $block['render'],
					'supports'        => isset( $block['supports'] ) ? $block['supports'] : $this->default_supports(),
				)
			);
		}
	}

	/**
	 * پشتیبانی‌های پیش‌فرض بلوک.
	 *
	 * @return array
	 */
	protected function default_supports() {
		return array(
			'html'       => false,
			'anchor'     => true,
			'align'      => array( 'wide', 'full' ),
			'spacing'    => array(
				'margin'  => true,
				'padding' => true,
			),
			'color'      => array(
				'background' => true,
				'text'       => true,
				'gradients'  => true,
			),
			'typography' => array(
				'fontSize'   => true,
				'lineHeight' => true,
			),
		);
	}

	/**
	 * اسکریپت ویرایشگر.
	 */
	public function editor_assets() {
		wp_enqueue_script(
			'manacore-blocks',
			MANACORE_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-api-fetch' ),
			MANACORE_VERSION,
			true
		);

		wp_localize_script( 'manacore-blocks', 'manaCoreBlocks', Block_Data::payload() );
		// از inline script استفاده می‌کنیم تا نوع داده‌ها (بولین/عدد) دست‌نخورده بماند.
		wp_add_inline_script(
			'manacore-blocks',
			'window.manaCoreBlockRegistry = ' . wp_json_encode( $this->editor_registry() ) . ';',
			'before'
		);

		wp_enqueue_style(
			'manacore-blocks-editor',
			MANACORE_URL . 'assets/css/editor.css',
			array(),
			MANACORE_VERSION
		);
	}

	/* ---------------------------------------------------------------------
	 * کمکی‌ها
	 * ------------------------------------------------------------------ */

	/**
	 * شناسه‌ی اثر هدف یک بلوک تک‌اثری.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return int
	 */
	protected function target_post( $attrs, $types = array() ) {
		if ( ! empty( $attrs['postId'] ) ) {
			return (int) $attrs['postId'];
		}

		$post_id = Block_Visibility::current_post_id();

		/*
		 * در ویرایشگر، بلوک‌های «یک اثر» (مشخصات، دانلود، بازیگران، …)
		 * هیچ زمینه‌ی پستی ندارند و همه‌شان پیام «محتوایی برای نمایش
		 * وجود ندارد» را نشان می‌دادند؛ یعنی چیدمان قالب در ویرایشگر
		 * عملاً خالی دیده می‌شد. برای پیش‌نمایش، یک اثر نمونه انتخاب
		 * می‌شود تا ویرایشگر همان چیزی را نشان دهد که بازدیدکننده
		 * می‌بیند. این جایگزینی فقط در پیش‌نمایش رخ می‌دهد و خروجی
		 * سمت کاربر را تغییر نمی‌دهد.
		 */
		if ( ! $post_id && Block_Support::is_editor_preview() ) {
			$post_id = $this->sample_post_id( $types );
		}

		return $post_id;
	}

	/**
	 * شناسه‌ی یک اثر نمونه برای پیش‌نمایش ویرایشگر.
	 *
	 * @return int صفر اگر هیچ اثری منتشر نشده باشد.
	 */
	protected function sample_post_id( $types = array() ) {
		$types = array_filter( array_map( 'strval', (array) $types ) );

		/*
		 * نوع‌های درخواستی بخشی از کلید کش‌اند: بلوک «فهرست قسمت‌ها» باید
		 * نمونه‌ای از سریال/انیمه بگیرد، نه فیلم؛ وگرنه پیش‌نمایش ویرایشگر
		 * همیشه «محتوایی برای نمایش وجود ندارد» نشان می‌داد.
		 */
		$cache_key = 'manacore_sample_post' . ( $types ? '_' . md5( implode( ',', $types ) ) : '' );
		$cached    = wp_cache_get( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		/*
		 * چند اثر خوانده می‌شود، نه یکی: تازه‌ترین اثر ممکن است تازه
		 * ساخته و خالی باشد و همان پیام «محتوایی نیست» را بدهد. اثری
		 * ترجیح داده می‌شود که بیشترین داده را دارد تا پیش‌نمایش
		 * ویرایشگر نماینده‌ی واقعی طراحی باشد.
		 */
		$posts = get_posts(
			array(
				'post_type'        => $types ? $types : manacore_title_post_types(),
				'post_status'      => 'publish',
				'numberposts'      => 20,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);

		$id   = 0;
		$best = -1;

		foreach ( (array) $posts as $candidate ) {
			$candidate = (int) $candidate;
			$score     = 0;

			if ( class_exists( __NAMESPACE__ . '\Links' ) && Links::count( $candidate ) > 0 ) {
				$score += 4;
			}
			if ( get_post_meta( $candidate, 'manacore_cast', true ) ) {
				$score += 2;
			}
			if ( get_post_meta( $candidate, 'manacore_trailer_url', true ) ) {
				$score += 2;
			}
			if ( has_post_thumbnail( $candidate ) || get_post_meta( $candidate, 'manacore_poster_url', true ) ) {
				$score += 1;
			}
			if ( get_post_meta( $candidate, 'manacore_imdb_rating', true ) ) {
				$score += 1;
			}

			/*
			 * وقتی نمونه از نوع سریال/انیمه خواسته شده، اثری که واقعاً
			 * قسمت دارد بر اثری بدون قسمت ترجیح داده می‌شود.
			 */
			if ( array_intersect( $types, manacore_serial_post_types() ) ) {
				$has_children = get_posts(
					array(
						'post_type'        => 'episode',
						'post_status'      => 'publish',
						'numberposts'      => 1,
						'fields'           => 'ids',
						'no_found_rows'    => true,
						'meta_query'       => array(
							array(
								'key'   => 'manacore_parent_title',
								'value' => $candidate,
							),
						),
					)
				);
				if ( $has_children ) {
					$score += 6;
				}
			}

			if ( $score > $best ) {
				$best = $score;
				$id   = $candidate;
			}
		}
		wp_cache_set( $cache_key, $id, '', 300 );

		return $id;
	}

	/**
	 * تبدیل مقدار به آرایه (پشتیبانی از رشته‌ی کاما‌جدا برای سازگاری).
	 *
	 * @param mixed $value مقدار.
	 * @return array
	 */
	protected function to_array( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'strval', $value ), 'strlen' ) );
		}
		$parts = preg_split( '/[\s,،]+/', (string) $value );
		return $parts ? array_values( array_filter( $parts, 'strlen' ) ) : array();
	}

	/* ---------------------------------------------------------------------
	 * رندرها
	 * ------------------------------------------------------------------ */

	/**
	 * رندر شبکه‌ی آثار.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_titles_grid( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$attrs = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( Block_Support::loop_attributes() )
		);

		$posts = Block_Support::get_posts( $attrs, 48 );
		if ( empty( $posts ) ) {
			/*
			 * حلقه‌ی برگه‌ی کشف (شمار کل + «داستان‌های بیشتر») حالت خالیِ
			 * مرجع را می‌گیرد: پنل `.empty-state` با راه بازگشت به فهرست
			 * کامل — چون آنجا کاربر با فیلترهای خودش به بن‌بست خورده است.
			 */
			if ( ! empty( $attrs['showFilterSummary'] ) || ! empty( $attrs['loadMore'] )
				|| ! empty( $attrs['emptyTitle'] ) || ! empty( $attrs['emptyLinkUrl'] ) ) {
				/*
				 * صفحه‌ی بیرون از محدوده (`?paged=9` با سه صفحه نتیجه) هم به
				 * همین شاخه می‌رسد: وردپرس در آن حالت `found_posts` را صفر
				 * برمی‌گرداند (سنجیده‌شده)، پس «شمار واقعی» در دست نیست.
				 * پیامِ پنل خالی برای این حالت جداگانه است تا کاربر فکر
				 * نکند فیلترهایش بی‌نتیجه بوده.
				 */
				$paged_now = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				return $this->render_discovery_empty( $attrs, $paged_now );
			}

			return Block_Support::render_empty( $attrs, 'manacore-titles-block' );
		}

		$layout    = sanitize_key( (string) $attrs['layout'] );
		$card_args = Block_Support::card_args( $attrs );

		/*
		 * کارت رتبه‌دار سینورا: روی ردیف‌های داغ (مثل «پرطرفدارترین‌های هفته»)
		 * شماره‌ی رتبه با خط دوران سبز گوشه‌ی کارت می‌نشیند.
		 */
		$ranked = ! empty( $attrs['ranked'] );

		/*
		 * مقصد پیوند «مشاهده همه»: مرجع همیشه این پیوند را دارد
		 * (`a.text-link`). اگر قالب مقصد را تعیین نکرده باشد، آرشیو
		 * «زمینه‌ی جاری» انتخاب می‌شود:
		 *   ۱) اثرِ در حال نمایش (تک‌برگه یا صفحه‌ی پخش) → آرشیو همان نوع
		 *   ۲) در نبود آن، نوعِ نخستین کارت فهرست
		 * نتیجه: در صفحه‌ی پخش سریال → آرشیو سریال‌ها و در فیلم → فیلم‌ها.
		 */
		if ( ! empty( $attrs['showMore'] ) && empty( $attrs['moreUrl'] ) ) {
			$context_type = '';
			$queried      = get_queried_object();

			if ( $queried instanceof \WP_Post && in_array( $queried->post_type, manacore_title_post_types(), true ) ) {
				$context_type = $queried->post_type;
			}

			if ( ! $context_type ) {
				$watched = Player::watched_id();
				if ( $watched ) {
					$context_type = (string) get_post_type( $watched );
				}
			}

			if ( ! $context_type ) {
				$context_type = (string) get_post_type( $posts[0] );
			}

			/*
			 * مقصد از همان تابع مشترک می‌آید که `render_header()` هم از آن
			 * استفاده می‌کند؛ وگرنه دو پیاده‌سازی از هم فاصله می‌گرفتند
			 * (ردیف‌های صفحه‌ی نخست به آرشیو می‌رفتند و بقیه به برگه‌ی کشف).
			 */
			$attrs['moreUrl'] = Block_Support::resolve_more_url( $attrs, $context_type );
		}

		/*
		 * تب‌های نوع: تنها وقتی معنا دارند که فهرست بیش از یک نوع محتوا
		 * داشته باشد؛ وگرنه تبی که چیزی را عوض نمی‌کند نمایش داده نمی‌شود.
		 */
		$tabs     = array();
		$type_map = array();
		if ( ! empty( $attrs['showTypeTabs'] ) ) {
			foreach ( $posts as $post ) {
				$type = (string) $post->post_type;
				if ( ! isset( $type_map[ $type ] ) ) {
					$type_map[ $type ] = 0;
				}
				$type_map[ $type ]++;
			}

			if ( count( $type_map ) > 1 ) {
				$labels = Block_Support::type_tab_labels();
				$tabs[] = array(
					'value'  => 'all',
					'label'  => __( 'همه', 'manacore' ),
					'active' => true,
				);
				foreach ( $labels as $key => $label ) {
					if ( isset( $type_map[ $key ] ) ) {
						$tabs[] = array(
							'value'  => $key,
							'label'  => $label,
							'active' => false,
						);
					}
				}
				foreach ( array_keys( $type_map ) as $type ) {
					if ( ! isset( $labels[ $type ] ) ) {
						$tabs[] = array(
							'value'  => $type,
							'label'  => $type,
							'active' => false,
						);
					}
				}
			}
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-titles-block', 'is-layout-' . $layout ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		echo Block_Support::render_header( $attrs, $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput

		/*
		 * شمار کل و صفحه‌بندی: از همان کوئریِ حلقه خوانده می‌شود
		 * (`Block_Support::last_query()`)، چون ویژگی‌های بلوک فقط تعدادِ
		 * همین صفحه را می‌دانند.
		 */
		$query     = Block_Support::last_query();
		$total     = $query ? (int) $query->found_posts : count( $posts );
		$max_pages = $query ? max( 1, (int) $query->max_num_pages ) : 1;
		$paged     = $query ? max( 1, (int) $query->get( 'paged' ) ) : 1;

		if ( ! empty( $attrs['showFilterSummary'] ) ) {
			echo $this->render_filter_summary( $total ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		printf(
			'<div class="%1$s"%2$s>',
			esc_attr( Block_Support::grid_classes( $attrs ) ),
			Block_Support::grid_style( $attrs ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		foreach ( $posts as $index => $post ) {
			$card = Templates::card( $post->ID, $card_args );

			/*
			 * نشان نوع کارت: تب‌های سرصفحه در مرورگر با همین نشان، شبکه را
			 * پالایش می‌کنند (`front.js` → `initSectionTypeTabs`).
			 */
			$card = $this->card_with_type( $card, (string) $post->post_type );

			if ( $ranked ) {
				$card = $this->card_with_rank( $card, (int) $index + 1 );
			}

			echo $card; // phpcs:ignore WordPress.Security.EscapeOutput — خروجی Templates::card است.
		}

		echo '</div>';

		if ( ! empty( $attrs['loadMore'] ) ) {
			echo $this->render_load_more( $attrs, $paged, $max_pages, $total ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر خلاصه‌ی نتایج و برچسب فیلترهای فعال (`.results-summary` و
	 * `.active-filter-chips` مرجع).
	 *
	 * مرجع هر دو را در ستون نتایج می‌گذارد، نه در سایدبار: کاربر همان‌جا که
	 * فهرست را می‌بیند، شمار نتیجه و فیلترهای مؤثر را می‌خواند و هر برچسب را
	 * با یک کلیک برمی‌دارد. برچسب‌ها پیوندند (نه دکمه‌ی جاوااسکریپتی) تا
	 * بدون اسکریپت هم کار کنند.
	 *
	 * @param int $total شمار کل آثار.
	 * @return string
	 */
	protected function render_filter_summary( $total ) {
		$active = Block_Support::is_editor_preview() ? array() : manacore_active_filters();
		$base   = Block_Support::is_editor_preview() ? home_url( '/' ) : manacore_archive_base_url();
		$labels = $active ? $this->filter_chip_labels( $active, manacore_filter_params() ) : array();

		ob_start();
		?>
		<div class="results-summary">
			<span>
				<b class="results-count-number"><?php echo esc_html( manacore_fa_digits( number_format_i18n( $total ) ) ); ?></b>
				<?php esc_html_e( 'داستان برای کشف کردن', 'manacore' ); ?>
			</span>
			<?php if ( $active ) : ?>
				<a class="results-clear-all" href="<?php echo esc_url( $base ); ?>">
					<?php esc_html_e( 'حذف فیلترها', 'manacore' ); ?>
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</a>
			<?php endif; ?>
		</div>
		<?php if ( $labels ) : ?>
			<div class="active-filter-chips">
				<?php foreach ( $labels as $param => $text ) : ?>
					<a class="manacore-filter-chip"
						href="<?php echo esc_url( add_query_arg( manacore_chip_removal_args( $active, $param ), $base ) ); ?>">
						<span><?php echo esc_html( $text ); ?></span>
						<span class="manacore-filter-chip-x" aria-hidden="true">&times;</span>
						<span class="screen-reader-text"><?php esc_html_e( 'حذف این فیلتر', 'manacore' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * رندر ناحیه‌ی «داستان‌های بیشتر» (`.load-more-zone` مرجع).
	 *
	 * تفاوت آگاهانه با مرجع: آنجا `<button>` است و همه‌چیز سمت کاربر با
	 * `browse.js` انجام می‌شود. اینجا عنصر یک `<a>` است تا **بدون
	 * جاوااسکریپت هم** کار کند؛ `initLoadMore()` در `front.js` کلیک را
	 * می‌گیرد، صفحه‌ی بعد را می‌آورد و کارت‌ها را به انتهای شبکه می‌چسباند
	 * (رفتار مرجع)؛ اگر جاوااسکریپت نباشد، همان پیوند صفحه‌ی بعد را
	 * می‌آورد. در پایانِ صفحه‌بندی، همان پیام مرجع چاپ می‌شود.
	 *
	 * @param array $attrs     ویژگی‌ها.
	 * @param int   $paged     صفحه‌ی جاری.
	 * @param int   $max_pages شمار صفحه‌ها.
	 * @param int   $total     شمار کل آثار.
	 * @return string
	 */
	protected function render_load_more( $attrs, $paged, $max_pages, $total ) {
		$label = ! empty( $attrs['loadText'] ) ? (string) $attrs['loadText'] : __( 'داستان‌های بیشتر', 'manacore' );

		if ( $paged >= $max_pages ) {
			/*
			 * صفحه‌ی نخست با یک صفحه‌ی نتیجه هم پیام پایانی نمی‌گیرد؛ آنجا
			 * چیزی برای «بیشتر» نیست و پیام هم بی‌معنا می‌شود.
			 */
			if ( $paged < 2 ) {
				return '';
			}

			return '<div class="load-more-zone"><p>'
				. esc_html( sprintf(
					/* translators: %s: شمار کل آثار. */
					__( 'همه %s داستان را دیدی. حالا وقت انتخاب است.', 'manacore' ),
					manacore_fa_digits( number_format_i18n( $total ) )
				) )
				. '</p></div>';
		}

		$next = add_query_arg( 'paged', $paged + 1 );

		return '<div class="load-more-zone"><a class="button secondary" href="' . esc_url( $next ) . '" rel="next" data-manacore-load-more>'
			. esc_html( $label )
			. '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'
			. '</a></div>';
	}

	/**
	 * پنل حالت خالیِ برگه‌ی کشف (`.empty-state` مرجع).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	protected function render_discovery_empty( $attrs, $paged = 1 ) {
		if ( empty( $attrs['emptyText'] ) && ! Block_Support::is_editor_preview() ) {
			return '';
		}

		$text = ! empty( $attrs['emptyText'] )
			? (string) $attrs['emptyText']
			: __( 'کمی از فیلترها کم کن؛ یک داستان تازه منتظرت است.', 'manacore' );

		$title = ! empty( $attrs['emptyTitle'] )
			? (string) $attrs['emptyTitle']
			: ( $paged > 1
				? __( 'این صفحه دیگر داستانی ندارد.', 'manacore' )
				: __( 'با این فیلترها داستانی پیدا نشد.', 'manacore' ) );

		/*
		 * مقصد دکمه‌ی حالت خالی: پیش‌فرض «نمایش همه‌ی آثار» (بازگشت از
		 * فیلترها)، و اگر بلوک مقصد خودش را داشته باشد همان (مثل تب
		 * «لیست تماشا» که به برگه‌ی کشف می‌رود).
		 */
		$link_label = ! empty( $attrs['emptyLinkLabel'] )
			? (string) $attrs['emptyLinkLabel']
			: __( 'نمایش همه‌ی آثار', 'manacore' );

		$reset = ! empty( $attrs['emptyLinkUrl'] )
			? (string) manacore_resolve_link( (string) $attrs['emptyLinkUrl'] )
			: manacore_archive_base_url();

		return '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-titles-block', 'is-empty' ) ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput
			. Block_Support::render_header( $attrs )
			/*
			 * مرجع حتی وقتی چیزی پیدا نشده، شمار (۰) و راه حذف فیلترها را
			 * بالای پنل خالی نشان می‌دهد؛ همان‌جا که کاربر انتظارش را دارد.
			 */
			. ( ! empty( $attrs['showFilterSummary'] ) ? $this->render_filter_summary( 0 ) : '' )
			. '<div class="empty-state">'
			. '<h3>' . esc_html( $title ) . '</h3>'
			. '<p>' . esc_html( $text ) . '</p>'
			. ( $reset ? '<a class="button primary" href="' . esc_url( $reset ) . '">' . esc_html( $link_label ) . '</a>' : '' )
			. '</div></div>';
	}

	/**
	 * افزودن نشان نوع محتوا به تگ `article` کارت.
	 *
	 * تب‌های سرصفحه (`.section-tabs`) در مرورگر با این نشان کار می‌کنند؛
	 * با همان روشی که `card_with_rank()` نشان رتبه را تزریق می‌کند تا
	 * رندرکننده‌ی کارت دست‌نخورده بماند.
	 *
	 * @param string $card      خروجی Templates::card.
	 * @param string $post_type نوع محتوا.
	 * @return string
	 */
	protected function card_with_type( $card, $post_type ) {
		$card      = (string) $card;
		$post_type = sanitize_key( $post_type );

		if ( '' === $card || '' === $post_type || false === strpos( $card, '<article' ) ) {
			return $card;
		}

		return (string) preg_replace(
			'/<article\s([^>]*?)>/',
			'<article data-manacore-type="' . esc_attr( $post_type ) . '" $1>',
			$card,
			1
		);
	}

	/**
	 * افزودن شماره‌ی رتبه به کارت (کارت رتبه‌دار سینورا).
	 *
	 * شماره با خط دوران سبز گوشه‌ی کارت می‌نشیند؛ کارت باید نسبت به خودش
	 * positioned باشد تا شماره به آن لنگر شود.
	 *
	 * @param string $card خروجی Templates::card.
	 * @param int    $rank شماره‌ی رتبه (از ۱).
	 * @return string
	 */
	protected function card_with_rank( $card, $rank ) {
		$card = (string) $card;

		if ( '' === $card || $rank < 1 ) {
			return $card;
		}

		$rank  = (int) $rank;
		$fa    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$label = strtr( (string) $rank, array_combine(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$fa
		) );

		/*
		 * نخستین تگ article کارت را با فرزند رتبه گسترش می‌دهیم. شماره داخل
		 * کادر کارت می‌نشیند (کارت overflow:hidden دارد و بیرون‌زدگی مرجع
		 * سینورا در این ساختار بریده می‌شود).
		 */
		$card = (string) preg_replace(
			'/<article\s([^>]*?)>/',
			'<article $1><span class="mc-rank" aria-hidden="true">' . esc_html( $label ) . '</span>',
			$card,
			1,
			$count
		);

		return $card;
	}

	/**
	 * رندر اسلایدر ویژه.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_hero_slider( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/hero-slider']['attributes'] )
		);

		// اسلایدر پیش‌فرض روی «منتخب سردبیر» و تعداد کمتر تنظیم می‌شود.
		if ( 'latest' === $attrs['source'] && ! isset( $attrs['sourceTouched'] ) ) {
			$attrs['source'] = 'featured';
		}

		$posts = Block_Support::get_posts( $attrs, 12 );
		if ( empty( $posts ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-hero' );
		}

		$height   = sanitize_html_class( (string) $attrs['height'] );
		$autoplay = ! empty( $attrs['autoplay'] );
		$interval = max( 2, min( 60, (int) $attrs['interval'] ) );
		$total    = count( $posts );

		/*
		 * هر گزینه‌ی ظاهری با فهرست مشترک Block_Data اعتبارسنجی می‌شود؛
		 * بنابراین مقدار دست‌ساز یا کهنه در ویژگی‌های بلوک هرگز به کلاس
		 * بی‌اثر یا نامعتبر در HTML تبدیل نمی‌شود.
		 */
		$style  = Block_Support::pick( $attrs['sliderStyle'], Block_Data::slider_styles(), 'cinematic' );
		$effect = Block_Support::pick( $attrs['effect'], Block_Data::slider_effects(), 'fade' );
		$align  = Block_Support::pick( $attrs['contentAlign'], Block_Data::slider_alignments(), 'start' );

		$overlay = max( 0, min( 100, (int) $attrs['overlay'] ) );
		$inline  = $overlay ? sprintf( ' style="--mc-hero-overlay:%s"', esc_attr( $overlay / 100 ) ) : '';

		ob_start();
		printf(
			'<div %1$s%5$s data-manacore-hero data-autoplay="%2$s" data-interval="%3$d" data-effect="%4$s">',
			Block_Support::wrapper( // phpcs:ignore WordPress.Security.EscapeOutput
				$attrs,
				array(
					'manacore-hero',
					'is-height-' . $height,
					'is-slider-' . $style,
					'is-effect-' . $effect,
					'is-align-' . $align,
				)
			),
			$autoplay ? 'true' : 'false',
			esc_attr( $interval * 1000 ),
			esc_attr( $effect ),
			$inline // phpcs:ignore WordPress.Security.EscapeOutput
		);

		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="manacore-hero-track">';

		foreach ( $posts as $index => $post ) {
			$backdrop = get_post_meta( $post->ID, 'manacore_backdrop_url', true );
			$backdrop = $backdrop ? $backdrop : manacore_poster_url( $post->ID, 'full' );
			$logo     = ! empty( $attrs['showLogo'] ) ? get_post_meta( $post->ID, 'manacore_logo_url', true ) : '';
			$rating   = Templates::best_rating( $post->ID );
			$year     = Templates::year( $post->ID );
			$trailer  = ! empty( $attrs['showTrailer'] ) ? get_post_meta( $post->ID, 'manacore_trailer_url', true ) : '';
			?>
			<div class="manacore-hero-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-hero-slide="<?php echo esc_attr( $index ); ?>">
				<img class="manacore-hero-bg" src="<?php echo esc_url( $backdrop ); ?>"
					alt="" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" decoding="async" />
				<div class="manacore-hero-content">
					<?php if ( $logo ) : ?>
						<img class="manacore-hero-logo" src="<?php echo esc_url( $logo ); ?>"
							alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy" />
					<?php else : ?>
						<h2 class="manacore-hero-title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showMeta'] ) ) : ?>
						<div class="manacore-hero-meta">
							<?php if ( $rating ) : ?>
								<span class="manacore-chip is-quality">⭐ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
							<?php endif; ?>
							<?php if ( $year ) : ?>
								<span class="manacore-chip"><?php echo esc_html( $year ); ?></span>
							<?php endif; ?>
							<?php
							if ( ! empty( $attrs['showGenres'] ) ) {
								$genres = get_the_terms( $post->ID, 'genre' );
								if ( $genres && ! is_wp_error( $genres ) ) {
									$limit = max( 1, (int) $attrs['genreCount'] );
									foreach ( array_slice( $genres, 0, $limit ) as $genre ) {
										echo '<span class="manacore-chip">' . esc_html( $genre->name ) . '</span>';
									}
								}
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showExcerpt'] ) ) : ?>
						<p class="manacore-hero-excerpt">
							<?php
							echo esc_html(
								wp_trim_words(
									wp_strip_all_tags( get_the_excerpt( $post ) ),
									max( 6, (int) $attrs['excerptWords'] ),
									'…'
								)
							);
							?>
						</p>
					<?php endif; ?>

					<div class="manacore-hero-actions">
						<a class="manacore-btn is-primary" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
							<?php
							echo esc_html(
								! empty( $attrs['primaryLabel'] )
									? $attrs['primaryLabel']
									: __( 'مشاهده و دانلود', 'manacore' )
							);
							?>
						</a>
						<?php if ( $trailer ) : ?>
							<button type="button" class="manacore-btn is-ghost"
								data-manacore-play="<?php echo esc_url( $trailer ); ?>"
								data-title="<?php echo esc_attr( get_the_title( $post ) ); ?>">
								<?php
								echo esc_html(
									! empty( $attrs['trailerLabel'] )
										? $attrs['trailerLabel']
										: __( 'تریلر', 'manacore' )
								);
								?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		echo '</div>';

		if ( ! empty( $attrs['showArrows'] ) && $total > 1 ) {
			printf(
				'<button type="button" class="manacore-hero-arrow is-prev" data-hero-prev aria-label="%s"></button>'
				. '<button type="button" class="manacore-hero-arrow is-next" data-hero-next aria-label="%s"></button>',
				esc_attr__( 'اسلاید قبلی', 'manacore' ),
				esc_attr__( 'اسلاید بعدی', 'manacore' )
			);
		}

		if ( ! empty( $attrs['showDots'] ) && $total > 1 ) {
			echo '<div class="manacore-hero-dots" role="tablist">';
			foreach ( $posts as $index => $post ) {
				printf(
					'<button type="button" class="manacore-hero-dot%1$s" data-hero-dot="%2$d" role="tab" aria-selected="%3$s" aria-label="%4$s"></button>',
					0 === $index ? ' is-active' : '',
					esc_attr( $index ),
					0 === $index ? 'true' : 'false',
					esc_attr( get_the_title( $post ) )
				);
			}
			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر مشخصات اثر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_title_meta( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/title-meta']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-meta-block' );
		}

		$layout = in_array( $attrs['metaLayout'], array( 'rows', 'columns', 'inline' ), true )
			? $attrs['metaLayout']
			: 'rows';

		$meta_args = array(
			'fields'      => $this->to_array( $attrs['fields'] ),
			'link_terms'  => ! empty( $attrs['linkTerms'] ),
			'layout'      => $layout,
			'hide_empty'  => ! empty( $attrs['hideEmpty'] ),
			'label_width' => max( 0, (int) $attrs['labelWidth'] ),
		);

		$html = Templates::meta_list( $post_id, $meta_args );

		/*
		 * سرصفحه‌ی تک‌قسمت: خودِ قسمت میدان‌های متادیتا را حمل نمی‌کند
		 * (کارگردان، ژانر، مدت…) و فهرست خالی می‌ماند. مثل تریلر
		 * (`render_trailer`) به اثر مادر برگشت می‌زنیم تا کارتِ متادیتا در
		 * برگه‌ی قسمت هم محتوا داشته باشد.
		 */
		if ( ! $html && 'episode' === get_post_type( $post_id ) ) {
			$parent = (int) get_post_meta( $post_id, 'manacore_parent_title', true );
			if ( $parent && $parent !== $post_id && 'publish' === get_post_status( $parent ) ) {
				$html = Templates::meta_list( $parent, $meta_args );
			}
		}

		if ( ! $html ) {
			return Block_Support::render_empty( $attrs, 'manacore-meta-block' );
		}

		return '<div ' . Block_Support::wrapper( $attrs, 'manacore-meta-block' ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput
			. Block_Support::render_header( $attrs )
			. $html
			. '</div>';
	}

	/**
	 * رندر لینک‌های دانلود.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_links( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/download-links']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-links-block' );
		}

		$level = isset( $attrs['headingLevel'] ) ? strtolower( (string) $attrs['headingLevel'] ) : 'h2';
		if ( ! in_array( $level, array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$level = 'h2';
		}

		$box_style = Block_Support::pick( $attrs['boxStyle'], Block_Data::download_styles(), 'cards' );

		$html = Templates::links(
			$post_id,
			array(
				'box_style'    => $box_style,
				'mode'         => (string) $attrs['mode'],
				'pack_label'   => (string) $attrs['packLabel'],
				'size_label'   => (string) $attrs['sizeLabel'],
				'heading'      => (string) $attrs['heading'],
				'subtitle'     => (string) $attrs['subtitle'],
				'heading_tag'  => $level,
				'show_heading' => '' !== trim( (string) $attrs['heading'] ),
				'show_icon'    => ! empty( $attrs['showIcon'] ),
				'show_count'   => ! empty( $attrs['showCount'] ),
				'show_notice'  => ! empty( $attrs['showNotice'] ),
				'show_tabs'    => ! empty( $attrs['showTabs'] ),
				'types'        => $this->to_array( $attrs['types'] ),
				'qualities'    => $this->to_array( $attrs['qualities'] ),
				'season'       => max( 0, (int) $attrs['season'] ),
			)
		);

		if ( ! $html ) {
			return Block_Support::render_empty( $attrs, 'manacore-links-block' );
		}

		return '<div ' . Block_Support::wrapper( $attrs, 'manacore-links-block' ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * رندر جعبه‌ی امتیاز.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_rating_box( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/rating-box']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-rating-box' );
		}

		$map = array(
			'imdb_rating'  => array( 'manacore_imdb_rating', 'IMDb' ),
			'tmdb_rating'  => array( 'manacore_tmdb_rating', 'TMDB' ),
			'mal_rating'   => array( 'manacore_mal_rating', 'MAL' ),
			'editor_score' => array( 'manacore_editor_score', __( 'سردبیر', 'manacore' ) ),
			'user_rating'  => array( 'manacore_user_rating', __( 'کاربران', 'manacore' ) ),
		);

		$selected = $this->to_array( $attrs['scoreSources'] );
		if ( empty( $selected ) ) {
			$selected = array( 'imdb_rating', 'tmdb_rating', 'mal_rating', 'editor_score' );
		}

		$scores = array();
		if ( ! empty( $attrs['showScores'] ) ) {
			foreach ( $selected as $key ) {
				$key = sanitize_key( $key );
				if ( ! isset( $map[ $key ] ) ) {
					continue;
				}
				$value = (float) get_post_meta( $post_id, $map[ $key ][0], true );
				if ( $value > 0 ) {
					$scores[ $map[ $key ][1] ] = $value;
				}
			}
		}

		$show_user  = ! empty( $attrs['showUserRating'] ) && manacore_get_option( 'enable_ratings', 1 );
		$user_avg   = (float) get_post_meta( $post_id, 'manacore_user_rating', true );
		$user_count = (int) get_post_meta( $post_id, 'manacore_user_rating_count', true );

		if ( empty( $scores ) && ! $show_user ) {
			return Block_Support::render_empty( $attrs, 'manacore-rating-box' );
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-rating-box' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $scores ) {
			echo '<div class="manacore-scores">';
			foreach ( $scores as $label => $value ) {
				?>
				<div class="manacore-score">
					<span class="manacore-score-value"><?php echo esc_html( number_format_i18n( $value, 1 ) ); ?></span>
					<span class="manacore-score-label"><?php echo esc_html( $label ); ?></span>
					<?php if ( ! empty( $attrs['showBars'] ) ) : ?>
						<span class="manacore-score-bar"><i style="width:<?php echo esc_attr( manacore_rating_percent( $value ) ); ?>%"></i></span>
					<?php endif; ?>
				</div>
				<?php
			}
			echo '</div>';
		}

		if ( $show_user ) {
			$mine = Ratings::instance()->user_vote( $post_id );
			?>
			<div class="manacore-user-rating" data-manacore-rating="<?php echo esc_attr( $post_id ); ?>">
				<span class="manacore-user-rating-label">
					<?php
					echo esc_html(
						! empty( $attrs['userLabel'] ) ? $attrs['userLabel'] : __( 'امتیاز شما:', 'manacore' )
					);
					?>
				</span>
				<div class="manacore-stars" role="radiogroup">
					<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
						<button type="button" class="manacore-star<?php echo $i <= $mine ? ' is-on' : ''; ?>"
							data-value="<?php echo esc_attr( $i ); ?>" role="radio"
							aria-checked="<?php echo $i === $mine ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: امتیاز */ __( 'امتیاز %d', 'manacore' ), $i ) ); ?>">
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
								<path fill="currentColor" d="M12 2l2.9 6.3 6.8.8-5 4.7 1.3 6.8L12 17.4 6 20.6l1.3-6.8-5-4.7 6.8-.8z"/>
							</svg>
						</button>
					<?php endfor; ?>
				</div>
				<?php if ( ! empty( $attrs['showSummary'] ) ) : ?>
					<span class="manacore-rating-summary" data-rating-summary>
						<?php if ( $user_count ) : ?>
							<?php
							printf(
								/* translators: 1: میانگین امتیاز 2: تعداد رأی */
								esc_html__( '%1$s از ۱۰ (%2$s رأی)', 'manacore' ),
								esc_html( number_format_i18n( $user_avg, 1 ) ),
								esc_html( number_format_i18n( $user_count ) )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'هنوز رأیی ثبت نشده', 'manacore' ); ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
			<?php
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * رندر فهرست بازیگران.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_cast( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/cast-list']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$cast = get_post_meta( $post_id, 'manacore_cast', true );
		$cast = is_string( $cast ) ? json_decode( $cast, true ) : $cast;
		if ( ! is_array( $cast ) || empty( $cast ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$offset = max( 0, (int) $attrs['offset'] );
		$limit  = max( 1, (int) $attrs['count'] );
		$cast   = array_slice( $cast, $offset, $limit );

		if ( empty( $cast ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-cast' );
		}

		$layout = sanitize_key( (string) $attrs['layout'] );
		$ratio  = sanitize_html_class( (string) $attrs['imageRatio'] );

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-cast', 'is-layout-' . $layout ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		printf(
			'<div class="manacore-cast-track%1$s"%2$s>',
			'carousel' === $layout ? ' is-scroll' : '',
			Block_Support::grid_style( $attrs ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		foreach ( $cast as $person ) {
			if ( empty( $person['name'] ) ) {
				continue;
			}
			$photo = ! empty( $person['photo'] ) ? $person['photo'] : MANACORE_URL . 'assets/avatar.svg';
			?>
			<figure class="manacore-cast-item<?php echo $ratio ? ' is-ratio-' . esc_attr( $ratio ) : ''; ?>">
				<?php if ( ! empty( $attrs['showPhoto'] ) ) : ?>
					<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $person['name'] ); ?>"
						loading="lazy" decoding="async" />
				<?php endif; ?>
				<figcaption>
					<strong><?php echo esc_html( $person['name'] ); ?></strong>
					<?php if ( ! empty( $attrs['showCharacter'] ) && ! empty( $person['character'] ) ) : ?>
						<span><?php echo esc_html( $person['character'] ); ?></span>
					<?php endif; ?>
				</figcaption>
			</figure>
			<?php
		}

		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر تریلر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	/**
	 * رندر صفحه‌ی پخش (الگوی «در حال پخش»).
	 *
	 * همان بخش‌های `player.html` مرجع: نوار بازگشت، سرصفحه‌ی اثر، کادر
	 * ویدئو، نوار کنترل (کیفیت، حالت سینما، تمام‌صفحه، لیست تماشا، دانلود)
	 * و یادداشت نمونه. داده‌ها از خودِ اثر می‌آید: منبع‌ها از لینک‌های
	 * «پخش آنلاین» هر کیفیت و در نبودشان از تریلر اثر.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_player_page( $attrs = array() ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/player-page']['attributes'] )
		);

		/*
		 * ترتیب تعیین اثر: شناسه‌ی صریح بلوک → `?manacore_id=` صفحه‌ی پخش
		 * (همان قرارداد `Player::watched_id()`) → اثر جاری → نمونه‌ی
		 * ویرایشگر. بدون گام دوم، صفحه‌ی پخش خودش را نشان می‌داد نه اثر.
		 *
		 * دو شناسه نگه داشته می‌شود:
		 *   `$display_id` — اثری که سرصفحه/دکمه‌ی بازگشت/امتیاز از آن می‌آید.
		 *   `$source_id`  — پستی که منبع‌های پخش از آن خوانده می‌شود.
		 * برای سریال، کاربر از صفحه‌ی سریال می‌آید ولی لینک‌ها روی قسمت‌ها
		 * ثبت شده‌اند؛ `Player::resolve_target()` قسمتِ درست را پیدا می‌کند
		 * تا صفحه‌ی پخش خالی نماند.
		 */
		$display_id = ! empty( $attrs['postId'] ) ? (int) $attrs['postId'] : Player::watched_id();
		if ( ! $display_id ) {
			$display_id = $this->target_post( $attrs, manacore_title_post_types() );
		}
		if ( ! $display_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-player-page' );
		}

		// فصل/قسمت درخواستی (از پیوندهای «پخش» جدول دانلود).
		$req_season  = isset( $_GET['season'] ) ? absint( wp_unslash( $_GET['season'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$req_episode = isset( $_GET['episode'] ) ? absint( wp_unslash( $_GET['episode'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$source_id = Player::resolve_target( $display_id, $req_season, $req_episode );
		if ( ! $source_id ) {
			$source_id = $display_id;
		}

		$title    = get_the_title( $display_id );
		$original = (string) get_post_meta( $display_id, 'manacore_original_title', true );
		$rating   = (float) get_post_meta( $display_id, 'manacore_imdb_rating', true );
		$poster   = (string) get_post_meta( $display_id, 'manacore_backdrop_url', true );
		if ( '' === $poster ) {
			$poster = (string) get_post_meta( $display_id, 'manacore_poster_url', true );
		}

		/* منبع‌های پخش: هر گروه لینک، یک کیفیت. */
		$sources   = array();
		$downloads = array();
		$download  = '';
		foreach ( Links::get( $source_id ) as $group ) {
			$quality = trim( (string) $group['quality'] );
			foreach ( $group['items'] as $item ) {
				$key = '' !== $quality ? $quality : Links::type_label( $item['type'] );

				if ( 'stream' === $item['type'] ) {
					$sources[ $key ] = $item['url'];
				} else {
					/*
					 * پیوند دانلود هر کیفیت جدا نگه داشته می‌شود تا دکمه‌ی
					 * «دانلود نمونه» — مثل مرجع — همان کیفیتی را بدهد که
					 * کاربر در نوار کنترل انتخاب کرده است.
					 */
					if ( ! isset( $downloads[ $key ] ) ) {
						$downloads[ $key ] = $item['url'];
					}
					if ( '' === $download ) {
						$download = $item['url'];
					}
				}
			}
		}

		$trailer = (string) get_post_meta( $source_id, 'manacore_trailer_url', true );
		if ( '' === $trailer && $source_id !== $display_id ) {
			$trailer = (string) get_post_meta( $display_id, 'manacore_trailer_url', true );
		}
		if ( ! $sources && $trailer ) {
			$sources[ __( 'کیفیت اصلی', 'manacore' ) ] = $trailer;
		}
		if ( '' === $download ) {
			$download = $trailer;
		}

		/*
		 * حالت خالی گویا: در بازدید عمومی پیام `render_empty()` پیش‌فرض
		 * چیزی چاپ نمی‌کند و کاربر صفحه‌ی سفید می‌بیند؛ اینجا یک کارت
		 * کوتاه با نام اثر و راه بازگشت ساخته می‌شود تا «صفحه‌ی پخش خالی»
		 * به «صفحه‌ی بی‌محتوا» تبدیل نشود. در بوم ویرایشگر همان رفتار
		 * پیشین (`render_empty`) حفظ می‌شود تا نویسنده راهنمای بلوک را ببیند.
		 */
		if ( ! $sources ) {
			if ( Block_Support::is_editor_preview() ) {
				return Block_Support::render_empty( $attrs, 'manacore-player-page' );
			}

			$empty_text = ! empty( $attrs['emptyText'] )
				? (string) $attrs['emptyText']
				: __( 'برای این اثر هنوز لینک پخشی ثبت نشده است.', 'manacore' );

			$empty = '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-player-page', 'is-empty' ) ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput
				. '<div class="player-page player-page--empty" data-manacore-player-page="' . esc_attr( $source_id ) . '">'
				. '<div class="player-title"><div><p class="eyebrow">' . esc_html( $eyebrow ) . '</p><h1>' . esc_html( $title ) . '</h1></div></div>'
				. '<div class="demo-notice"><span aria-hidden="true">ⓘ</span><p>' . esc_html( $empty_text ) . '</p></div>'
				. '<p><a class="manacore-btn is-secondary is-small" href="' . esc_url( (string) get_permalink( $display_id ) ) . '">'
				. '‹ ' . esc_html( $back_label ) . '</a></p>'
				. '</div></div>';

			return $empty;
		}

		/*
		 * کیفیت درخواستی (از دکمه‌ی «پخش» جدول دانلود) مقدم است؛ ولی فقط
		 * اگر در همان اثر واقعاً وجود داشته باشد. این «فهرست سفید» جلوی
		 * مقدار ساختگیِ `?quality=` را می‌گیرد: پیش‌تر هر رشته‌ای که
		 * سرویس‌دهنده می‌پذیرفت بی‌اثر بود، ولی اکنون ناسازگارها به
		 * نخستین کیفیت موجود برمی‌گردند (نه پیوند شکسته).
		 */
		$requested = isset( $_GET['quality'] ) ? sanitize_text_field( wp_unslash( $_GET['quality'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current   = '' !== $requested && isset( $sources[ $requested ] ) ? $requested : (string) key( $sources );

		$eyebrow    = $attrs['eyebrow'] ? (string) $attrs['eyebrow'] : __( 'NOW PLAYING · DEMO', 'manacore' );
		$back_label = $attrs['backLabel'] ? (string) $attrs['backLabel'] : __( 'بازگشت به جزئیات', 'manacore' );
		$notice     = $attrs['notice'] ? (string) $attrs['notice'] : (string) get_post_meta( $display_id, 'manacore_custom_notice', true );

		/*
		 * مرجع همیشه زیر پلیر یک یادداشت نمونه دارد (`player.js` متن ثابت
		 * Big Buck Bunny را می‌نویسد). اگر مدیر متن ویژه‌ای ننوشته باشد،
		 * همین یادداشت پیش‌فرض با نام اثر چاپ می‌شود.
		 */
		if ( '' === trim( $notice ) ) {
			/* متن پیش‌فرض یادداشت پلیر از پنل مدیریت (تب «پخش و دانلود»). */
			$default_notice = trim( (string) manacore_get_option( 'player_notice_text', '' ) );

			$notice = '' !== $default_notice
				? str_replace( '{title}', $title, $default_notice )
				: sprintf(
					/* translators: %s: نام اثر */
					__( 'این پلیر با ویدئوی نمونه‌ی آزاد کار می‌کند، نه فایل اصلی %s. کیفیت‌ها واقعی و پیشرفت تماشا قابل ذخیره است.', 'manacore' ),
					$title
				);
		}

		$resolved = Player::resolve( $sources[ $current ] );

		/*
		 * زمینه‌ی قسمت: صفحه‌ی پخش سریال با `?season=&episode=` باز می‌شود
		 * (همان قرارداد دکمه‌های «پخش» جدول دانلود). همان‌جا هم سطر دوم
		 * تیتر «نام اصلی · فصل ۱، قسمت ۳» می‌شود و هم بخش
		 * «قسمت‌های فصل» زیر پلیر ساخته می‌شود — عیناً رفتار مرجع.
		 */
		$series_parent = (int) get_post_meta( $display_id, 'manacore_parent_title', true );
		$series_id     = in_array( get_post_type( $display_id ), manacore_serial_post_types(), true ) ? $display_id : $series_parent;

		$season_number  = $req_season ? $req_season : (int) get_post_meta( $source_id, 'manacore_season_number', true );
		$episode_number = $req_episode ? $req_episode : (int) get_post_meta( $source_id, 'manacore_episode_number', true );

		if ( ! $season_number ) {
			$season_number = 1;
		}

		$episodes   = array();
		$next_item  = null;   // قسمت بعدی برای پخش خودکار.
		$next_value = 0;

		if ( $series_id ) {
			$episodes = get_posts(
				array(
					'post_type'      => 'episode',
					'posts_per_page' => 500,
					'post_status'    => 'publish',
					'meta_key'       => 'manacore_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'        => array( 'meta_value_num' => 'ASC' ),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => 'manacore_parent_title',
							'value' => $series_id,
						),
					),
				)
			);

			$episodes = array_values(
				array_filter(
					$episodes,
					static function ( $episode ) use ( $season_number ) {
						return (int) get_post_meta( $episode->ID, 'manacore_season_number', true ) === $season_number;
					}
				)
			);

			/*
			 * وقتی نشانی فصل/قسمت مشخص نکرده باشد (مثلاً کلیک روی «پخش» خودِ
			 * سریال)، قسمتِ فعال همان قسمتی است که پخش می‌شود؛ اگر آن هم
			 * نامشخص باشد، نخستین قسمت فصل تا فهرست و ناوبری بی‌نشان نمانند.
			 */
			if ( ! $episode_number && $episodes ) {
				$episode_number = (int) get_post_meta( $episodes[0]->ID, 'manacore_episode_number', true );
			}

			/*
			 * قسمت بعدی برای پخش خودکار پس از پایان قسمت جاری. کوچک‌ترین
			 * شماره‌ی بزرگ‌تر از قسمت جاری انتخاب می‌شود (نه «قسمت بعد در
			 * فهرست») تا با شماره‌گذاری نامرتب هم درست کار کند.
			 */
			foreach ( $episodes as $episode_item ) {
				$number = (int) get_post_meta( $episode_item->ID, 'manacore_episode_number', true );

				if ( $number > $episode_number && ( ! $next_item || $number < $next_value ) ) {
					$next_item  = $episode_item;
					$next_value = $number;
				}
			}
		}

		/*
		 * پخش خودکار قسمت بعدی: نشانی و نام قسمت بعدی روی ریشه‌ی صفحه
		 * می‌نشیند تا جاوااسکریپت وقتی `ended` شد، کارت «قسمت بعدی» را
		 * با شمارش معکوس نشان دهد. برای فیلم (بی‌قسمت بعدی) هیچ‌چیز چاپ
		 * نمی‌شود و رفتار پیشین دست‌نخورده می‌ماند.
		 */
		$next_url   = '';
		$next_title = '';

		if ( $next_item && $series_id ) {
			$next_url   = Player::url_for(
				(int) $series_id,
				array(
					'season'  => $season_number,
					'episode' => $next_value,
					'quality' => $current,
				)
			);
			$next_title = (string) get_the_title( $next_item->ID );
		}

		$is_hls = Player::has_hls( $sources );

		ob_start();
		?>
		<div class="player-page" data-manacore-player-page="<?php echo esc_attr( $source_id ); ?>"
			<?php echo '' !== $next_url ? 'data-next-url="' . esc_url( $next_url ) . '" data-next-title="' . esc_attr( $next_title ) . '"' : ''; ?>>
			<div class="player-top">
				<a class="text-link" href="<?php echo esc_url( (string) get_permalink( $display_id ) ); ?>">
					<?php echo esc_html( '← ' . $back_label ); ?>
				</a>
				<span><span class="live-dot" aria-hidden="true"></span><?php esc_html_e( 'سینما، هر جا که تو باشی.', 'manacore' ); ?></span>
			</div>

			<div class="player-title">
				<div>
					<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php
					$sub_title = $original;

					if ( $series_parent ) {
						$sub_title .= ( '' !== $sub_title ? ' · ' : '' ) . sprintf(
							/* translators: 1: شماره فصل، 2: شماره قسمت */
							__( 'فصل %1$s، قسمت %2$s', 'manacore' ),
							number_format_i18n( $season_number ),
							number_format_i18n( $episode_number )
						);
					}

					if ( '' !== trim( $sub_title ) ) :
						?>
						<p dir="ltr"><?php echo esc_html( $sub_title ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $attrs['showBadges'] ) ) : ?>
					<div class="player-badges">
						<?php if ( $rating ) : ?>
							<?php /* مرجع امتیاز را با ارقام فارسی و جداکننده‌ی نقطه می‌نویسد: «★ ۸.۵». */ ?>
							<span><span aria-hidden="true">★</span><?php echo esc_html( manacore_fa_digits( number_format( (float) $rating, 1, '.', '' ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $current ) : ?>
							<span dir="ltr"><?php echo esc_html( $current ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="video-frame" data-player-frame>
				<?php if ( 'video' === $resolved['type'] ) : ?>
					<video controls preload="metadata" playsinline data-player-video
						data-player-media="<?php echo $is_hls ? 'hls' : 'file'; ?>"
						<?php echo $poster ? ' poster="' . esc_url( $poster ) . '"' : ''; ?>
						title="<?php echo esc_attr( $title ); ?>">
						<?php
						/*
						 * کیفیت جاری نخست می‌آید تا مرورگر همان را بارگذاری کند؛
						 * بقیه فقط `data-src` می‌گیرند و جاوااسکریپت با تغییر
						 * `video.src` جابه‌جا می‌کند.
						 */
						$ordered = $sources;
						if ( isset( $ordered[ $current ] ) ) {
							$first_source = array( $current => $ordered[ $current ] );
							unset( $ordered[ $current ] );
							$ordered = $first_source + $ordered;
						}
						$first = true;
						?>
						<?php foreach ( $ordered as $label => $url ) : ?>
							<?php $mime = Player::mime_for( $url ); ?>
							<source <?php echo $first ? 'src="' . esc_url( $url ) . '"' : ''; ?>
								data-quality="<?php echo esc_attr( (string) $label ); ?>"
								<?php echo '' !== $mime ? 'type="' . esc_attr( $mime ) . '"' : ''; ?>
								data-src="<?php echo esc_url( $url ); ?>" />
							<?php $first = false; ?>
						<?php endforeach; ?>
						<?php esc_html_e( 'مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.', 'manacore' ); ?>
					</video>
				<?php else : ?>
					<iframe src="<?php echo esc_url( $resolved['src'] ); ?>" title="<?php echo esc_attr( $title ); ?>"
						allowfullscreen loading="lazy" data-player-frame-src
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
						referrerpolicy="strict-origin-when-cross-origin"></iframe>
				<?php endif; ?>
			</div>

			<div class="player-controls">
				<?php if ( count( $sources ) > 1 ) : ?>
					<label>
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 11.5 4a2 2 0 1 1 4 0 1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 19.4 11a2 2 0 1 1 0 4z"/>
						</svg>
						<?php esc_html_e( 'کیفیت پخش', 'manacore' ); ?>
						<?php
						/*
						 * برچسب گزینه‌ها عیناً مثل مرجع است
						 * («1080p · Full HD»)، ولی مقدارِ گزینه همان کلید
						 * کیفیت می‌ماند تا قرارداد `data-quality` و پیوندهای
						 * «پخش» نشکند.
						 */
						$quality_labels = array(
							'1080p' => __( '1080p · Full HD', 'manacore' ),
							'720p'  => __( '720p · HD', 'manacore' ),
							'360p'  => __( '360p · کم‌حجم', 'manacore' ),
						);
						?>
						<select data-player-quality aria-label="<?php esc_attr_e( 'کیفیت پخش', 'manacore' ); ?>">
							<?php foreach ( $sources as $label => $url ) : ?>
								<option value="<?php echo esc_attr( (string) $label ); ?>"
									data-src="<?php echo esc_url( (string) $url ); ?>"
									<?php echo isset( $downloads[ $label ] ) ? 'data-download="' . esc_url( $downloads[ $label ] ) . '"' : ''; ?>
									<?php selected( $current, (string) $label ); ?>>
									<?php echo esc_html( isset( $quality_labels[ $label ] ) ? $quality_labels[ $label ] : (string) $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>

				<button type="button" class="manacore-btn is-secondary is-small" data-player-cinema aria-pressed="false">
					<span aria-hidden="true">▣</span> <?php esc_html_e( 'حالت سینما', 'manacore' ); ?>
				</button>

				<button type="button" class="manacore-btn is-secondary is-small" data-player-fullscreen>
					<span aria-hidden="true">⛶</span> <?php esc_html_e( 'تمام‌صفحه', 'manacore' ); ?>
				</button>

				<?php
				/*
				 * «لیست تماشا» و رویداد «track-download» روی اثری ثبت
				 * می‌شوند که کاربر واقعاً تماشا می‌کند (`$source_id`)
				 * نه سریالی که فقط ظرف صفحه است.
				 */
				$in_watchlist = function_exists( 'manacore_in_watchlist' ) ? (bool) manacore_in_watchlist( $source_id ) : false;
				?>
				<button type="button" class="manacore-btn is-secondary is-small<?php echo $in_watchlist ? ' is-active' : ''; ?>"
					data-manacore-watchlist="<?php echo esc_attr( $source_id ); ?>"
					aria-pressed="<?php echo $in_watchlist ? 'true' : 'false'; ?>">
					<span aria-hidden="true">♡</span> <?php esc_html_e( 'لیست تماشا', 'manacore' ); ?>
				</button>

				<?php
				$download_url = isset( $downloads[ $current ] ) ? $downloads[ $current ] : $download;
				?>
				<?php if ( $download_url ) : ?>
					<a class="manacore-btn is-primary is-small" href="<?php echo esc_url( $download_url ); ?>"
						rel="nofollow noopener" target="_blank" download
						data-player-download
						data-manacore-download="<?php echo esc_attr( $source_id ); ?>">
						<span aria-hidden="true">⇩</span> <?php esc_html_e( 'دانلود نمونه', 'manacore' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $attrs['showNotice'] ) ) : ?>
				<?php /* نشانه‌ی یادداشت در مرجع یک نویسه‌ی متنی است، نه آیکون. */ ?>
				<div class="demo-notice">
					<span aria-hidden="true">ⓘ</span>
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $episodes ) : ?>
				<section class="player-episodes">
					<div class="section-heading">
						<div class="heading-title">
							<span class="section-icon" aria-hidden="true"><span aria-hidden="true">▤</span></span>
							<h2>
								<?php
								/* translators: %s: شماره فصل */
								echo esc_html( sprintf( __( 'قسمت‌های فصل %s', 'manacore' ), number_format_i18n( $season_number ) ) );
								?>
							</h2>
						</div>
						<div class="episode-navigation">
							<?php
							$prev_episode = null;
							$next_episode = null;

							foreach ( $episodes as $index => $episode_item ) {
								$number = (int) get_post_meta( $episode_item->ID, 'manacore_episode_number', true );

								if ( $number === $episode_number ) {
									$prev_episode = $index > 0 ? $episodes[ $index - 1 ] : null;
									$next_episode = isset( $episodes[ $index + 1 ] ) ? $episodes[ $index + 1 ] : null;
									break;
								}
							}

							$args_for = static function ( $episode_item ) use ( $series_id, $season_number, $current ) {
								$number = (int) get_post_meta( $episode_item->ID, 'manacore_episode_number', true );

								return add_query_arg(
									array(
										'season'  => $season_number,
										'episode' => $number,
										'quality' => rawurlencode( $current ),
									),
									Player::page_url( $series_id )
								);
							};

							if ( $prev_episode ) :
								?>
								<a class="manacore-btn is-secondary is-small" href="<?php echo esc_url( $args_for( $prev_episode ) ); ?>">
									<span aria-hidden="true">→</span> <?php esc_html_e( 'قسمت قبل', 'manacore' ); ?>
								</a>
								<?php
							endif;

							if ( $next_episode ) :
								?>
								<a class="manacore-btn is-primary is-small" href="<?php echo esc_url( $args_for( $next_episode ) ); ?>">
									<?php esc_html_e( 'قسمت بعد', 'manacore' ); ?> <span aria-hidden="true">←</span>
								</a>
								<?php
							endif;
							?>
						</div>
					</div>
					<div class="episode-pills">
						<?php foreach ( $episodes as $episode_item ) : ?>
							<?php
							$number    = (int) get_post_meta( $episode_item->ID, 'manacore_episode_number', true );
							$is_active = $number === $episode_number;
							?>
							<a class="<?php echo $is_active ? 'active' : ''; ?>"
								href="<?php echo esc_url( $args_for( $episode_item ) ); ?>"
								<?php echo $is_active ? 'aria-current="true"' : ''; ?>>
								<span aria-hidden="true">▶</span>
								<?php
								/* translators: %s: شماره قسمت */
								echo esc_html( sprintf( __( 'قسمت %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $number ) ) ) );
								?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function render_trailer( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/trailer']['attributes'] )
		);

		$post_id = $this->target_post( $attrs );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-trailer' );
		}

		$meta_key = sanitize_key( (string) $attrs['metaKey'] );
		$meta_key = $meta_key ? $meta_key : 'manacore_trailer_url';

		/*
		 * منبع تریلر: خود اثر، و اگر تک‌قسمت بود، اثر مادر.
		 *
		 * تک‌قسمت‌ها فیلد تریلر ندارند (نقشه‌ی متا آن را فقط برای انواع
		 * اثر ثبت می‌کند)، ولی مرجع در سرصفحه‌ی قسمت هم دکمه‌ی «پخش
		 * تریلر» دارد. پس وقتی تریلر اثر خالی بود، تریلر سریال/انیمه‌ی
		 * مادر (`manacore_parent_title`) خوانده می‌شود.
		 */
		$trailer_post = $post_id;
		$url          = get_post_meta( $trailer_post, $meta_key, true );

		if ( ! $url && 'manacore_trailer_url' === $meta_key ) {
			$parent = (int) get_post_meta( $post_id, 'manacore_parent_title', true );
			if ( $parent && 'publish' === get_post_status( $parent ) ) {
				$parent_url = get_post_meta( $parent, $meta_key, true );
				if ( $parent_url ) {
					$trailer_post = $parent;
					$url          = $parent_url;
				}
			}
		}

		if ( ! $url ) {
			return Block_Support::render_empty( $attrs, 'manacore-trailer' );
		}

		$ratio = sanitize_html_class( (string) $attrs['videoRatio'] );

		/*
		 * کنش «تریلر رسمی» مطابق مرجع سینورا: یک دکمه‌ی شیشه‌ای که به
		 * صفحه‌ی پخش واقعی می‌رود (`/watch/?manacore_id=…`). اگر آن صفحه
		 * ساخته نشده باشد، همان دکمه مُدال پخش را باز می‌کند تا هرگز
		 * پیوند مرده ساخته نشود. کلاس دکمه از قالب خوانده می‌شود
		 * (`koohe-btn koohe-btn--glass`) و در نبود قالب به کلاس خودِ
		 * افزونه برمی‌گردد.
		 */
		$watch = Player::page_url( $trailer_post );
		$class = function_exists( 'koohe_btn_class' ) ? koohe_btn_class( 'glass' ) : 'manacore-btn is-ghost is-small';
		$label = ! empty( $attrs['ctaLabel'] ) ? $attrs['ctaLabel'] : __( 'پخش تریلر', 'manacore' );

		/* خروجی خالی وقتی هیچ‌چیز برای نمایش نیست (نه پخش‌کننده، نه دکمه). */
		$show_player = ! isset( $attrs['showPlayer'] ) || $attrs['showPlayer'];

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-trailer', $ratio ? 'is-ratio-' . $ratio : '' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $show_player ) {
			echo Player::render( $url, get_the_title( $trailer_post ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		if ( $watch ) {
			printf(
				'<p class="manacore-trailer-cta"><a class="%1$s" href="%2$s">▶ %3$s</a></p>',
				esc_attr( $class ),
				esc_url( $watch ),
				esc_html( $label )
			);
		} else {
			printf(
				'<p class="manacore-trailer-cta"><button type="button" class="%1$s" data-manacore-play="%2$s" data-title="%3$s">▶ %4$s</button></p>',
				esc_attr( $class ),
				esc_url( $url ),
				esc_attr( get_the_title( $trailer_post ) ),
				esc_html( $label )
			);
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر نوار فیلتر.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_filter_bar( $attrs = array() ) {
		/*
		 * نوار فیلتر «بخش محتوا» نیست و مرجع هم کنار نوار فیلتر پیوند
		 * «مشاهده همه» ندارد (`.browse-toolbar` بی‌پیوند است). پس برای این
		 * بلوک مقصد خودکار ساخته نمی‌شود؛ پیوند فقط وقتی می‌آید که نویسنده
		 * صریحاً `moreUrl` داده باشد.
		 */
		if ( empty( $attrs['moreUrl'] ) ) {
			$attrs['showMore'] = false;
		}

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/filter-bar']['attributes'] )
		);

		$list = array_filter( array_map( 'sanitize_key', $this->to_array( $attrs['taxonomies'] ) ) );

		// نگاشت مشترک با کلاس Query تا هیچ فیلدی بی‌اثر نماند.
		$param_map = manacore_filter_params();

		$layout     = sanitize_key( (string) $attrs['formLayout'] );
		$is_sidebar = ( 'sidebar' === $layout );

		/*
		 * نشانی پایه‌ی آرشیو (بدون /page/N/). ویژگی action فرم باید همین باشد،
		 * وگرنه اعمال فیلتر از صفحه‌ی دوم کاربر را روی page/2 نگه می‌دارد و
		 * اگر نتیجه‌ی فیلترشده کمتر از دو صفحه باشد ۴۰۴ می‌گیرد.
		 */
		$base   = Block_Support::is_editor_preview() ? home_url( '/' ) : manacore_archive_base_url();
		$active = Block_Support::is_editor_preview() ? array() : manacore_active_filters();

		/*
		 * تاکسونومی‌ای که «گروه تیک‌زنی» را می‌سازد از حلقه‌ی گزینشگرها
		 * کنار می‌رود تا همان فیلتر دو بار دیده نشود.
		 */
		$check_taxonomy = ( $is_sidebar && ! empty( $attrs['showGenreChecks'] ) )
			? sanitize_key( (string) $attrs['checkTaxonomy'] )
			: '';
		if ( $check_taxonomy && ! in_array( $check_taxonomy, $list, true ) ) {
			$check_taxonomy = '';
		}
		if ( $check_taxonomy ) {
			$list = array_values( array_diff( $list, array( $check_taxonomy ) ) );
		}

		/*
		 * در حالت سایدبار، خودِ پوشش بلوک نقش `.filter-sidebar` مرجع را
		 * می‌گیرد (و نشانه‌ی `filter-sidebar` را، چون دکمه‌ی «فیلترها»ی
		 * نوار مرور در موبایل با `aria-controls` همین را باز/بسته می‌کند).
		 * پیش‌تر این پوسته در قالب دست‌نویس بود؛ حالا بخشی از بلوک است تا
		 * در هر جایی که نویسنده بلوک را بگذارد همان رفتار تکرار شود.
		 */
		if ( $is_sidebar ) {
			$wrapper_classes = array( 'manacore-filter-bar', 'filter-sidebar' );
			if ( empty( $attrs['anchor'] ) ) {
				$attrs['anchor'] = 'filter-sidebar';
			}
		} else {
			$wrapper_classes = 'manacore-filter-bar';
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, $wrapper_classes ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form method="get" action="<?php echo esc_url( $base ); ?>"
			class="manacore-filter-form is-layout-<?php echo esc_attr( $layout ); ?>">
			<?php
			// حفظ عبارت جستجو هنگام فیلتر کردن نتایج جستجو.
			if ( empty( $attrs['showSearch'] ) && is_search() ) {
				printf( '<input type="hidden" name="s" value="%s" />', esc_attr( get_search_query() ) );
			}

			/*
			 * حفظ صافی نوعِ نشانی (`?type=series`) هنگام فیلتر کردن از سایدبار.
			 * پیوندهای «مشاهده همه»ی صفحه‌ی نخست همین پارامتر را می‌آورند
			 * (مرجع: `browse.html?type=series`)؛ بدون این ورودی، نخستین
			 * تغییری در سایدبار آن را از نشانی می‌انداخت و فهرست به «همه»
			 * برمی‌گشت.
			 */
			if ( ! Block_Support::is_editor_preview() ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$keep_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
				if ( '' !== $keep_type && in_array( $keep_type, manacore_title_post_types(), true ) ) {
					printf( '<input type="hidden" name="type" value="%s" />', esc_attr( $keep_type ) );
				}
			}
			?>

			<?php
			/*
			 * سایدبار «فیلتر پیشرفته» (مرجع: `.filter-sidebar` در
			 * `browse.html`): سرصفحه، گروه ژانرهای تیک‌زنی، بازه‌ی سال،
			 * لغزنده‌ی امتیاز، کلید دوبله، دکمه‌ی پاک‌سازی و پنل راهنما.
			 * همه از ویژگی‌های بلوک می‌آیند؛ هیچ‌کدام در قالب دست‌نویس
			 * نشده‌اند.
			 */
			if ( $is_sidebar ) {
				?>
				<div class="filter-header">
					<h2>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
						<?php
						echo esc_html(
							! empty( $attrs['sidebarTitle'] ) ? $attrs['sidebarTitle'] : __( 'فیلتر پیشرفته', 'manacore' )
						);
						?>
					</h2>
					<a class="filter-reset-icon" href="<?php echo esc_url( $base ); ?>" aria-label="<?php esc_attr_e( 'حذف تمام فیلترها', 'manacore' ); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v6h6" /></svg>
					</a>
				</div>
				<?php
				$this->render_sidebar_groups( $attrs, $check_taxonomy, $base );
			}
			?>

			<?php
			if ( ! empty( $attrs['showSearch'] ) ) {
				/*
				 * نام پارامتر جستجو: در آرشیو و برگه‌ی جستجو `s` طبیعی است،
				 * ولی در برگه‌ی عادی (صفحه‌ی «کشف داستان‌ها») آوردن `s` در
				 * نشانی، وردپرس را به حالت جستجو می‌برد و همان برگه ۴۰۴
				 * می‌شود (سنجیده‌شده: `/about/?s=test` → ۴۰۴). پس در آن حالت
				 * پارامتر خودمان فرستاده می‌شود و حلقه هم آن را می‌خواند.
				 */
				$search_param = ( is_archive() || is_search() ) ? 's' : 'manacore_q';

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$term = isset( $_GET[ $search_param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) ) : '';
				?>
				<label class="manacore-filter-field is-search">
					<span class="manacore-filter-label"><?php esc_html_e( 'جستجو', 'manacore' ); ?></span>
					<input type="search" name="<?php echo esc_attr( $search_param ); ?>" value="<?php echo esc_attr( $term ); ?>"
						placeholder="<?php esc_attr_e( 'نام اثر…', 'manacore' ); ?>" />
				</label>
				<?php
			}

			foreach ( $list as $taxonomy ) :
				if ( ! taxonomy_exists( $taxonomy ) || ! isset( $param_map[ $taxonomy ] ) ) {
					continue;
				}
				$param = $param_map[ $taxonomy ];
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$current = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : '';

				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => ! empty( $attrs['hideEmptyTerms'] ),
						'number'     => max( 10, min( 500, (int) $attrs['termLimit'] ) ),
						'orderby'    => 'name',
					)
				);
				$terms = is_wp_error( $terms ) ? array() : (array) $terms;

				/*
				 * اگر ترم انتخاب‌شده در فهرست نباشد (مثلاً با hide_empty حذف شده
				 * یا از حد termLimit بیرون افتاده) آن را دستی می‌افزاییم؛ وگرنه
				 * فیلد ناپدید می‌شود و کاربر هیچ راهی برای برداشتن فیلتر ندارد.
				 */
				if ( $current ) {
					$slugs = wp_list_pluck( $terms, 'slug' );
					if ( ! in_array( $current, (array) $slugs, true ) ) {
						$selected_term = get_term_by( 'slug', $current, $taxonomy );
						if ( $selected_term instanceof \WP_Term ) {
							array_unshift( $terms, $selected_term );
						}
					}
				}

				if ( empty( $terms ) ) {
					continue;
				}

				$object = get_taxonomy( $taxonomy );
				$label  = $object->labels->singular_name;

				/*
				 * در حالت سایدبار هر گزینشگر همان شکل مرجع را می‌گیرد:
				 * `.filter-group` با سرتیتر `h3` و گزینشگر تمام‌عرض
				 * (`select.full`) — مرجع «کشور سازنده» را همین‌طور
				 * می‌سازد. در چیدمان‌های دیگر شکل پیشین می‌ماند.
				 */
				if ( $is_sidebar ) :
					?>
					<div class="<?php echo esc_attr( $current ? 'filter-group is-active' : 'filter-group' ); ?>">
						<h3><?php echo esc_html( $label ); ?></h3>
						<select class="full" name="<?php echo esc_attr( $param ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
					<?php
				else :
					?>
					<label class="<?php echo esc_attr( $current ? 'manacore-filter-field is-active' : 'manacore-filter-field' ); ?>">
						<span class="manacore-filter-label"><?php echo esc_html( $label ); ?></span>
						<select name="<?php echo esc_attr( $param ); ?>">
					<?php
				endif;
				?>
						<option value="">
							<?php
							/* translators: %s: نام تاکسونومی، مثلاً ژانر. */
							printf( esc_html__( 'همه‌ی %s', 'manacore' ), esc_html( $label ) );
							?>
						</option>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php if ( $is_sidebar ) : ?>
					</div>
				<?php else : ?>
					</label>
				<?php endif; ?>
				<?php
			endforeach;
			?>

			<?php if ( ! empty( $attrs['showSort'] ) ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$sort  = isset( $_GET['mc_sort'] ) ? sanitize_key( wp_unslash( $_GET['mc_sort'] ) ) : '';
				$sorts = manacore_sort_options();
				?>
				<label class="<?php echo esc_attr( $sort ? 'manacore-filter-field is-active' : 'manacore-filter-field' ); ?>">
					<span class="manacore-filter-label"><?php esc_html_e( 'مرتب‌سازی', 'manacore' ); ?></span>
					<select name="mc_sort">
						<?php foreach ( $sorts as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<?php if ( $is_sidebar ) : ?>
				<?php
				/*
				 * در چیدمانِ سایدبار، ردیف دکمه‌ها پنهان است: مرجع هم چنین
				 * ردیفی ندارد و همه‌چیز با ارسال خودکار اعمال می‌شود؛ اما
				 * کاربرِ بی‌جاوااسکریپت باید راهی برای اعمال فیلتر داشته
				 * باشد. پس همان ردیف داخل `<noscript>` می‌آید: برای مرورگر
				 * دارای جاوااسکریپت هیچ جعبه‌ای نمی‌سازد (پس ارتفاع و
				 * پیکسل‌ها دقیقاً مثل مرجع می‌ماند) و برای بقیه کار می‌کند.
				 */
				?>
				<noscript>
					<div class="manacore-filter-actions">
						<button type="submit" class="manacore-btn is-primary">
							<?php
							echo esc_html(
								! empty( $attrs['submitLabel'] ) ? $attrs['submitLabel'] : __( 'اعمال فیلتر', 'manacore' )
							);
							?>
						</button>
						<a class="manacore-btn is-ghost manacore-filter-reset"
							href="<?php echo esc_url( $base ); ?>">
							<?php esc_html_e( 'پاک‌سازی', 'manacore' ); ?>
						</a>
					</div>
				</noscript>
			<?php elseif ( ! empty( $attrs['showSubmit'] ) || ! empty( $attrs['showReset'] ) ) : ?>
				<div class="manacore-filter-actions">
					<?php if ( ! empty( $attrs['showSubmit'] ) ) : ?>
						<button type="submit" class="manacore-btn is-primary">
							<?php
							echo esc_html(
								! empty( $attrs['submitLabel'] ) ? $attrs['submitLabel'] : __( 'اعمال فیلتر', 'manacore' )
							);
							?>
						</button>
					<?php endif; ?>

					<?php if ( ! empty( $attrs['showReset'] ) ) : ?>
						<?php
						/*
						 * پایه‌ی آرشیو بدون /page/N/ و بدون پرسمان؛ استفاده‌ی پیشین از
						 * strtok تنها رشته‌ی پرسمان را حذف می‌کرد و بخش صفحه‌بندی
						 * در نشانی باقی می‌ماند.
						 */
						?>
						<a class="manacore-btn is-ghost manacore-filter-reset"
							href="<?php echo esc_url( $base ); ?>"
							aria-disabled="<?php echo esc_attr( $active ? 'false' : 'true' ); ?>">
							<?php esc_html_e( 'پاک‌سازی', 'manacore' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			if ( $is_sidebar ) {
				$this->render_sidebar_footer( $attrs, $base );
			}
			?>
		</form>

		<?php
		// نشانگر فیلترهای فعال: هر برچسب پیوندی است که همان فیلتر را حذف می‌کند.
		if ( $active && ! empty( $attrs['showActiveChips'] ) ) {
			$labels = $this->filter_chip_labels( $active, $param_map );
			if ( $labels ) {
				?>
				<div class="manacore-filter-chips">
					<span class="manacore-filter-chips-title"><?php esc_html_e( 'فیلترهای فعال:', 'manacore' ); ?></span>
					<?php foreach ( $labels as $param => $text ) : ?>
						<a class="manacore-filter-chip"
							href="<?php echo esc_url( add_query_arg( manacore_chip_removal_args( $active, $param ), $base ) ); ?>">
							<span><?php echo esc_html( $text ); ?></span>
							<span class="manacore-filter-chip-x" aria-hidden="true">&times;</span>
							<span class="screen-reader-text"><?php esc_html_e( 'حذف این فیلتر', 'manacore' ); ?></span>
						</a>
					<?php endforeach; ?>
					<a class="manacore-filter-chip is-clear" href="<?php echo esc_url( $base ); ?>">
						<?php esc_html_e( 'حذف همه', 'manacore' ); ?>
					</a>
				</div>
				<?php
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * گروه‌های بالای سایدبار «فیلتر پیشرفته» (مرجع: `.filter-group`).
	 *
	 * ترتیب دقیقاً ترتیب مرجع است: ژانرهای تیک‌زنی (با شمار آثار) →
	 * بازه‌ی سال ساخت → لغزنده‌ی امتیاز IMDb → کلید «فقط دوبله فارسی».
	 * گزینشگرهای تاکسونومیِ دیگر (کشور، کیفیت، زبان، …) همان‌جا که در
	 * حلقه‌ی پیش‌فرض رندر می‌شوند می‌مانند.
	 *
	 * @param array  $attrs          ویژگی‌های بلوک.
	 * @param string $check_taxonomy تاکسونومی گروه تیک‌زنی (اگر روشن باشد).
	 * @param string $base           نشانی پایه‌ی آرشیو.
	 * @return void
	 */
	protected function render_sidebar_groups( $attrs, $check_taxonomy, $base ) {
		unset( $base );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		/* ۱) ژانرها به‌صورت تیک‌زنی چندگانه، با شمار آثار هر ژانر. */
		if ( $check_taxonomy ) {
			$param_map = manacore_filter_params();
			$param     = isset( $param_map[ $check_taxonomy ] ) ? $param_map[ $check_taxonomy ] : '';

			$terms  = get_terms(
				array(
					'taxonomy'   => $check_taxonomy,
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			);
			$terms  = is_wp_error( $terms ) ? array() : (array) $terms;
			$counts = manacore_term_counts( $check_taxonomy );
			$chosen = array();

			if ( $param && ! empty( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$raw    = wp_unslash( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$chosen = array_filter( array_map( 'sanitize_title', is_array( $raw ) ? $raw : explode( ',', (string) $raw ) ) );
			}

			$object = get_taxonomy( $check_taxonomy );
			$label  = $object ? $object->labels->singular_name : $check_taxonomy;

			if ( $param && $terms ) {
				?>
				<div class="filter-group">
					<h3><?php echo esc_html( $label ); ?></h3>
					<div class="genre-checks">
						<?php foreach ( $terms as $term ) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $param ); ?>[]"
									value="<?php echo esc_attr( $term->slug ); ?>"
									<?php checked( in_array( $term->slug, $chosen, true ) ); ?> />
								<span class="custom-check" aria-hidden="true">
									<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>
								</span>
								<?php echo esc_html( $term->name ); ?>
								<small><?php echo esc_html( manacore_fa_digits( isset( $counts[ $term->term_id ] ) ? (int) $counts[ $term->term_id ] : 0 ) ); ?></small>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}
		}

		/* ۲) بازه‌ی سال ساخت؛ گزینه‌ها از داده ساخته می‌شوند. */
		if ( ! empty( $attrs['showYearRange'] ) ) {
			$bounds   = manacore_year_bounds();
			$year_min = ! empty( $_GET['mc_year_min'] ) ? (int) wp_unslash( $_GET['mc_year_min'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$year_max = ! empty( $_GET['mc_year_max'] ) ? (int) wp_unslash( $_GET['mc_year_max'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			/*
			 * اگر نشانی سالی بیرون از بازه‌ی داده داشته باشد (مثلاً
			 * `?mc_year_max=2026` روی کاتالوگی که تا ۲۰۲۴ اثر دارد)، همان
			 * سال هم به گزینه‌ها افزوده می‌شود؛ وگرنه گزینشگر «همه» نشان
			 * می‌داد و کاربر با یک ارسال، فیلترش را بی‌خبر از دست می‌داد.
			 */
			if ( $year_min > 0 && $year_min < $bounds['min'] && $year_min > 1800 ) {
				$bounds['min'] = $year_min;
			}
			if ( $year_max > $bounds['max'] && $year_max < 2300 ) {
				$bounds['max'] = $year_max;
			}
			$label    = ! empty( $attrs['yearLabel'] ) ? (string) $attrs['yearLabel'] : __( 'سال ساخت', 'manacore' );
			?>
			<div class="filter-group">
				<h3><?php echo esc_html( $label ); ?></h3>
				<div class="year-range">
					<select name="mc_year_min" aria-label="<?php esc_attr_e( 'از سال', 'manacore' ); ?>">
						<option value=""><?php esc_html_e( 'همه', 'manacore' ); ?></option>
						<?php for ( $year = $bounds['min']; $year <= $bounds['max']; $year++ ) : ?>
							<option value="<?php echo esc_attr( (string) $year ); ?>" <?php selected( $year_min, $year ); ?>>
								<?php echo esc_html( manacore_fa_digits( $year ) ); ?>
							</option>
						<?php endfor; ?>
					</select>
					<span><?php esc_html_e( 'تا', 'manacore' ); ?></span>
					<select name="mc_year_max" aria-label="<?php esc_attr_e( 'تا سال', 'manacore' ); ?>">
						<option value=""><?php esc_html_e( 'همه', 'manacore' ); ?></option>
						<?php for ( $year = $bounds['max']; $year >= $bounds['min']; $year-- ) : ?>
							<option value="<?php echo esc_attr( (string) $year ); ?>" <?php selected( $year_max, $year ); ?>>
								<?php echo esc_html( manacore_fa_digits( $year ) ); ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
			</div>
			<?php
		}

		/* ۳) لغزنده‌ی امتیاز IMDb. */
		if ( ! empty( $attrs['showRating'] ) ) {
			$max      = max( 1, min( 10, (int) $attrs['ratingMax'] ) );
			$min      = ! empty( $_GET['mc_rating_min'] ) ? (int) wp_unslash( $_GET['mc_rating_min'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$min      = max( 0, min( $max, $min ) );
			$heading  = ! empty( $attrs['ratingLabel'] ) ? (string) $attrs['ratingLabel'] : __( 'امتیاز IMDb', 'manacore' );
			$all_text = __( 'همه', 'manacore' );
			?>
			<div class="filter-group">
				<h3>
					<?php echo esc_html( $heading ); ?>
					<span class="accent" id="rating-label"><?php echo esc_html( $min ? manacore_fa_digits( $min ) . '+' : $all_text ); ?></span>
				</h3>
				<input class="rating-range" id="min-rating" type="range" name="mc_rating_min"
					min="0" max="<?php echo esc_attr( (string) $max ); ?>" step="1"
					value="<?php echo esc_attr( (string) $min ); ?>"
					data-all-label="<?php echo esc_attr( $all_text ); ?>"
					aria-label="<?php esc_attr_e( 'حداقل امتیاز', 'manacore' ); ?>" />
				<div class="range-labels">
					<span><?php echo esc_html( manacore_fa_digits( 0 ) ); ?></span>
					<span><?php echo esc_html( manacore_fa_digits( $max ) ); ?></span>
				</div>
			</div>
			<?php
		}

		// phpcs:enable
	}

	/**
	 * دکمه‌ی «پاک کردن فیلترها» و پنل راهنمای پایین سایدبار.
	 *
	 * @param array  $attrs ویژگی‌های بلوک.
	 * @param string $base  نشانی پایه‌ی آرشیو.
	 * @return void
	 */
	protected function render_sidebar_footer( $attrs, $base ) {
		$active = Block_Support::is_editor_preview() ? array() : manacore_active_filters();

		/*
		 * کلید «فقط دوبله فارسی».
		 *
		 * در مرجع این کلید **آخرین** گروه سایدبار است (پس از گروه
		 * تاکسونومی‌ها) و همین‌جا رندر می‌شود تا ترتیب دیده‌شده دقیقاً همان
		 * باشد: ژانر، سال، امتیاز، تاکسونومی‌ها، دوبله، پاک‌سازی، راهنما.
		 * اگر در `render_sidebar_groups()` می‌ماند، پیش از گزینشگرهای
		 * تاکسونومی می‌افتاد و جای کلید با مرجع جابه‌جا می‌شد.
		 */
		if ( ! empty( $attrs['showDubbed'] ) ) {
			$dubbed = ! empty( $attrs['dubbedLabel'] ) ? (string) $attrs['dubbedLabel'] : __( 'فقط دوبله فارسی', 'manacore' );
			?>
			<div class="filter-group">
				<label class="toggle-label">
					<span><?php echo esc_html( $dubbed ); ?></span>
					<input type="checkbox" name="mc_dubbed" value="1" <?php checked( ! empty( $_GET['mc_dubbed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> />
					<span class="toggle-switch" aria-hidden="true"></span>
				</label>
			</div>
			<?php
		}

		if ( ! empty( $attrs['showReset'] ) ) {
			$label = ! empty( $attrs['resetLabel'] ) ? (string) $attrs['resetLabel'] : __( 'پاک کردن فیلترها', 'manacore' );
			?>
			<a class="button secondary full reset-filters" href="<?php echo esc_url( $base ); ?>"
				aria-disabled="<?php echo esc_attr( $active ? 'false' : 'true' ); ?>">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v6h6" /></svg>
				<?php echo esc_html( $label ); ?>
			</a>
			<?php
		}

		/*
		 * پنل «انتخاب سخت شده؟» مرجع. دکمه‌ی مرجع سمت کاربر فیلترها را
		 * عوض می‌کند (`reset(); sort='rating'; minRating=8`)؛ اینجا به
		 * نشانی معادلش پیوند می‌خورد تا بدون جاوااسکریپت هم کار کند و
		 * مقصدش از ویرایشگر قابل تغییر باشد.
		 */
		if ( ! empty( $attrs['showHint'] ) ) {
			$title = ! empty( $attrs['hintTitle'] ) ? (string) $attrs['hintTitle'] : __( 'انتخاب سخت شده؟', 'manacore' );
			$text  = ! empty( $attrs['hintText'] ) ? (string) $attrs['hintText'] : __( 'به سلیقه خودت اعتماد کن؛ یا از پیشنهادهای ما شروع کن.', 'manacore' );
			$label = ! empty( $attrs['hintLabel'] ) ? (string) $attrs['hintLabel'] : __( 'شاهکارها را ببین', 'manacore' );

			$url = (string) $attrs['hintUrl'];
			if ( '' === $url ) {
				$url = add_query_arg(
					array(
						'mc_sort'       => 'rating',
						'mc_rating_min' => 8,
					),
					$base
				);
			}
			?>
			<div class="filter-hint">
				<svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7L12 3Z" /></svg>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo esc_html( $text ); ?></p>
				<a href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 14 0M13 6l6 6-6 6" /></svg>
				</a>
			</div>
			<?php
		}
	}

	/**
	 * رندر نوار مرور برگه‌ی کشف (`.browse-toolbar` مرجع).
	 *
	 * همه‌ی وضعیت‌ها از نشانی خوانده می‌شوند تا صفحه بدون جاوااسکریپت هم
	 * درست باشد: عبارت جستجو در کادر می‌ماند، دکمه‌ی نوعِ فعال نشانه می‌خورد
	 * و گزینشگر مرتب‌سازی همان گزینه‌ی کاربر را نشان می‌دهد. پارامترهای
	 * دیگری که این فرم رندر نمی‌کند (مثل فیلترهای سایدبار) به‌صورت
	 * `input[type=hidden]` همراه می‌شوند؛ وگرنه تغییر «مرتب‌سازی» یا
	 * «جستجو» فیلترهای کناری را از نشانی می‌انداخت.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	/**
	 * نوار مرور حالت «چهره‌ها» — هم‌ارز `.browse-toolbar` برگه‌ی «بازیگران و عوامل».
	 *
	 * برخلاف حالت آثار، فرم نیست: جست‌وجو درجا روی کارت‌های رندرشده در
	 * مرورگر انجام می‌شود (`initPeopleFilter()` در `front.js`) و دکمه‌های
	 * نقش از ترم‌های واقعی `person_role` ساخته می‌شوند. بدون جاوااسکریپت،
	 * همه‌ی کارت‌ها دیده می‌شوند و این نوار فقط کنترل‌های بی‌اثر می‌شود؛
	 * پس دکمه‌ها `type="button"` و بدون ارسال فرم‌اند.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	protected function render_people_toolbar( $attrs ) {
		$placeholder = ! empty( $attrs['searchPlaceholder'] )
			? (string) $attrs['searchPlaceholder']
			: __( 'نام بازیگر یا کارگردان…', 'manacore' );

		$term_list = get_terms(
			array(
				'taxonomy'   => 'person_role',
				'hide_empty' => true,
			)
		);

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-browse-toolbar' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<div class="browse-toolbar" data-manacore-people-toolbar>
			<div class="browse-search">
				<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
				<input id="cast-search" type="search" placeholder="<?php echo esc_attr( $placeholder ); ?>"
					aria-label="<?php esc_attr_e( 'جستجوی عوامل', 'manacore' ); ?>"
					data-manacore-people-search autocomplete="off" />
			</div>
			<div class="segmented-control" role="group" aria-label="<?php esc_attr_e( 'پالایش بر اساس نقش', 'manacore' ); ?>" data-manacore-people-roles>
				<button type="button" data-role="all" class="active" aria-pressed="true"><?php echo esc_html( (string) $attrs['allLabel'] ); ?></button>
				<?php
				if ( ! is_wp_error( $term_list ) ) {
					foreach ( $term_list as $term ) {
						printf(
							'<button type="button" data-role="%1$s" aria-pressed="false">%2$s</button>',
							esc_attr( $term->slug ),
							esc_html( $term->name )
						);
					}
				}
				?>
			</div>
		</div>
		<?php
		echo '</div>';

		return (string) ob_get_clean();
	}

	public function render_browse_toolbar( $attrs ) {
		$mode = isset( $attrs['mode'] ) ? sanitize_key( (string) $attrs['mode'] ) : 'titles';

		if ( 'people' === $mode ) {
			return $this->render_people_toolbar( (array) $attrs );
		}

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();

		$attrs = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/browse-toolbar']['attributes'] )
		);

		$editor = Block_Support::is_editor_preview();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$base         = $editor ? home_url( '/' ) : manacore_archive_base_url();
		$search_param = ( is_archive() || is_search() ) ? 's' : 'manacore_q';
		$term         = isset( $_GET[ $search_param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) ) : '';
		$sort         = isset( $_GET['mc_sort'] ) ? sanitize_key( wp_unslash( $_GET['mc_sort'] ) ) : '';
		$type         = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'all';
		$current      = $editor ? array() : $_GET;
		// phpcs:enable

		if ( ! in_array( $type, manacore_title_post_types(), true ) ) {
			$type = 'all';
		}

		$placeholder = ! empty( $attrs['searchPlaceholder'] )
			? (string) $attrs['searchPlaceholder']
			: __( 'جستجوی نام فیلم یا سریال…', 'manacore' );

		/*
		 * نوع‌های پیشنهادی از همان نگاشت برچسبِ تب‌های سرصفحه می‌آید تا در
		 * سراسر سایت یک نام داشته باشند.
		 */
		$labels = Block_Support::type_tab_labels();
		$types  = array();
		foreach ( manacore_title_post_types() as $post_type ) {
			$types[ $post_type ] = isset( $labels[ $post_type ] ) ? $labels[ $post_type ] : $post_type;
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-browse-toolbar' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<form class="browse-toolbar" method="get" action="<?php echo esc_url( $base ); ?>" data-manacore-browse-toolbar>
			<?php
			/*
			 * حفظ پارامترهایی که این فرم رندر نمی‌کند (فیلترهای سایدبار،
			 * جستجوی آرشیو و…): بدون آن‌ها تغییر یک کنترل، بقیه را پاک می‌کرد.
			 */
			$skip = array_merge(
				array( $search_param, 'mc_sort', 'type', 'paged', 'page' ),
				array_values( manacore_filter_params() )
			);

			foreach ( $current as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || in_array( $key, $skip, true ) || ! is_scalar( $value ) ) {
					continue;
				}
				printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $key ), esc_attr( wp_unslash( (string) $value ) ) );
			}
			?>

			<?php if ( ! empty( $attrs['showSearch'] ) ) : ?>
				<div class="browse-search">
					<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
					<input id="browse-search" type="search" name="<?php echo esc_attr( $search_param ); ?>"
						value="<?php echo esc_attr( $term ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-label="<?php esc_attr_e( 'جستجو در آثار', 'manacore' ); ?>" />
					<button id="clear-search" type="button" aria-label="<?php esc_attr_e( 'پاک کردن جستجو', 'manacore' ); ?>"<?php echo '' === $term ? ' hidden' : ''; ?>>
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showTypes'] ) && $types ) : ?>
				<div class="segmented-control" role="group" aria-label="<?php esc_attr_e( 'پالایش بر اساس نوع', 'manacore' ); ?>" data-manacore-type-tabs>
					<button type="button" data-type="all" class="<?php echo 'all' === $type ? 'active' : ''; ?>" aria-pressed="<?php echo 'all' === $type ? 'true' : 'false'; ?>"><?php esc_html_e( 'همه', 'manacore' ); ?></button>
					<?php foreach ( $types as $key => $label ) : ?>
						<button type="button" data-type="<?php echo esc_attr( $key ); ?>" class="<?php echo $key === $type ? 'active' : ''; ?>" aria-pressed="<?php echo $key === $type ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showMobileFilter'] ) ) : ?>
				<button type="button" class="button secondary mobile-filter-trigger" id="mobile-filter"
					aria-controls="<?php echo esc_attr( $attrs['filterId'] ); ?>" aria-expanded="false">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
					<?php esc_html_e( 'فیلترها', 'manacore' ); ?>
				</button>
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showSort'] ) ) : ?>
				<label class="sort-select">
					<span><?php esc_html_e( 'مرتب‌سازی:', 'manacore' ); ?></span>
					<select id="sort" name="mc_sort">
						<?php foreach ( manacore_sort_options() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showViewMode'] ) ) : ?>
				<?php
				/*
				 * حالت نمایش فقط ظاهر شبکه را عوض می‌کند (`list-layout` در
				 * `front.js`) و سمت سرور چیزی برای حفظ کردن ندارد.
				 */
				?>
				<div class="view-buttons" role="group" aria-label="<?php esc_attr_e( 'حالت نمایش', 'manacore' ); ?>">
					<button type="button" class="active" data-mode="grid" aria-pressed="true" aria-label="<?php esc_attr_e( 'نمایش شبکه‌ای', 'manacore' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
					</button>
					<button type="button" data-mode="list" aria-pressed="false" aria-label="<?php esc_attr_e( 'نمایش فهرستی', 'manacore' ); ?>">
						<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
					</button>
				</div>
			<?php endif; ?>
		</form>
		<?php
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * ساخت برچسب خوانا برای هر فیلتر فعال.
	 *
	 * @param array<string,string> $active    فیلترهای فعال (پارامتر => مقدار).
	 * @param array<string,string> $param_map نگاشت تاکسونومی => پارامتر.
	 * @return array<string,string> پارامتر => برچسب خوانا.
	 */
	protected function filter_chip_labels( $active, $param_map ) {
		$labels   = array();
		$reverse  = array_flip( $param_map );
		$sorts    = manacore_sort_options();

		$year_min = isset( $active['mc_year_min'] ) ? (int) $active['mc_year_min'] : 0;
		$year_max = isset( $active['mc_year_max'] ) ? (int) $active['mc_year_max'] : 0;

		foreach ( $active as $param => $value ) {
			/*
			 * فیلترهای فراداده‌ای سایدبار (بازه‌ی سال، حداقل امتیاز، دوبله)
			 * برچسب فارسی خودشان را دارند؛ بدون این، فیلتر اعمال می‌شد
			 * ولی تراشه‌ای برای برداشتنش نبود.
			 */
			if ( 'mc_year_min' === $param && $year_min ) {
				/* translators: %1$s: کمینه‌ی سال، %2$s: بیشینه‌ی سال. */
				$labels[ $param ] = $year_max
					? sprintf( __( 'سال: %1$s تا %2$s', 'manacore' ), manacore_fa_digits( $year_min ), manacore_fa_digits( $year_max ) )
					: sprintf( __( 'از سال %s', 'manacore' ), manacore_fa_digits( $year_min ) );
				continue;
			}

			if ( 'mc_year_max' === $param && $year_max ) {
				/* برچسب بازه یک‌بار و روی کمینه می‌آید؛ اینجا فقط وقتی کمینه نبود. */
				if ( ! $year_min ) {
					$labels[ $param ] = sprintf( __( 'تا سال %s', 'manacore' ), manacore_fa_digits( $year_max ) );
				}
				continue;
			}

			if ( 'mc_rating_min' === $param ) {
				/* translators: %s: امتیاز. */
				$labels[ $param ] = sprintf( __( 'امتیاز %s به بالا', 'manacore' ), manacore_fa_digits( $value ) );
				continue;
			}

			if ( 'mc_dubbed' === $param ) {
				$labels[ $param ] = __( 'فقط دوبله فارسی', 'manacore' );
				continue;
			}

			if ( 'manacore_q' === $param ) {
				/* translators: %s: عبارت جستجو. */
				$labels[ $param ] = sprintf( __( 'جستجو: %s', 'manacore' ), $value );
				continue;
			}

			if ( 'mc_sort' === $param ) {
				if ( isset( $sorts[ $value ] ) && '' !== $value ) {
					$labels[ $param ] = $sorts[ $value ];
				}
				continue;
			}

			if ( empty( $reverse[ $param ] ) || ! taxonomy_exists( $reverse[ $param ] ) ) {
				continue;
			}

			$names = array();
			foreach ( explode( ',', $value ) as $slug ) {
				$term = get_term_by( 'slug', sanitize_title( $slug ), $reverse[ $param ] );
				if ( $term instanceof \WP_Term ) {
					$names[] = $term->name;
				}
			}

			if ( $names ) {
				$labels[ $param ] = implode( '، ', $names );
			}
		}

		return $labels;
	}

	/**
	 * رندر کادر جستجو.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_search_box( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/search-box']['attributes'] )
		);

		$placeholder = ! empty( $attrs['placeholder'] )
			? $attrs['placeholder']
			: __( 'نام فیلم، سریال یا انیمه…', 'manacore' );

		$types = array_values( array_filter( array_map( 'sanitize_key', $this->to_array( $attrs['searchTypes'] ) ), 'post_type_exists' ) );
		$size  = sanitize_key( (string) $attrs['inputSize'] );

		ob_start();
		printf(
			'<div %1$s data-manacore-search data-live="%2$s" data-limit="%3$d"%4$s>',
			Block_Support::wrapper( $attrs, array( 'manacore-search', 'is-size-' . $size ) ), // phpcs:ignore WordPress.Security.EscapeOutput
			! empty( $attrs['liveResults'] ) ? 'true' : 'false',
			esc_attr( max( 1, min( 20, (int) $attrs['resultCount'] ) ) ),
			$types ? ' data-types="' . esc_attr( implode( ',', $types ) ) . '"' : ''
		);

		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<?php
		/*
		 * نشانِ `role="search"` باید نام دسترس‌پذیر یکتا داشته باشد.
		 *
		 * در سربرگ، نوار کناری، برگه‌ی ۴۰۴ و برگه‌ی جستجو بیش از یک نمونه
		 * از این بلوک هست؛ بدون نام، axe-core نقض `landmark-unique`
		 * (متوسط) می‌داد. متن راهنمای هر نمونه در همین قالب یکتاست، پس
		 * همان نام می‌شود؛ در نبودش، نام عمومی «جستجو».
		 */
		$koohe_search_label = '' !== trim( (string) $placeholder ) ? $placeholder : __( 'جستجو', 'manacore' );
		?>
		<form role="search" aria-label="<?php echo esc_attr( $koohe_search_label ); ?>" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="manacore-search-form">
			<?php if ( ! empty( $attrs['showIcon'] ) ) : ?>
				<span class="manacore-search-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18">
						<path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>
					</svg>
				</span>
			<?php endif; ?>
			<input type="search" name="s" class="manacore-search-input"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				aria-label="<?php esc_attr_e( 'جستجو', 'manacore' ); ?>" autocomplete="off" />
			<?php foreach ( $types as $type ) : ?>
				<input type="hidden" name="post_type[]" value="<?php echo esc_attr( $type ); ?>" />
			<?php endforeach; ?>
			<?php if ( ! empty( $attrs['showButton'] ) ) : ?>
				<button type="submit" class="manacore-btn is-primary is-small">
					<?php
					echo esc_html(
						! empty( $attrs['buttonLabel'] ) ? $attrs['buttonLabel'] : __( 'جستجو', 'manacore' )
					);
					?>
				</button>
			<?php endif; ?>
		</form>
		<?php if ( ! empty( $attrs['liveResults'] ) ) : ?>
			<?php
			/*
			 * ناحیه‌ی زنده: نتایج با جاوااسکریپت جایگزین می‌شوند و بدون
			 * aria-live هیچ‌گاه به صفحه‌خوان اعلام نمی‌شدند. polite انتخاب
			 * شده تا تایپ کاربر را قطع نکند، و aria-atomic تا کل فهرست
			 * یک‌جا خوانده شود نه تکه‌تکه.
			 */
			?>
			<div class="manacore-search-results" data-search-results hidden
				role="status" aria-live="polite" aria-atomic="true"></div>
		<?php endif; ?>
		<?php
		echo '</div>';
		return (string) ob_get_clean();
	}
	/**
	 * تصویر یک کالکشن یا مقاله.
	 *
	 * ترتیب اولویت: تصویر شاخص → `manacore_backdrop_url` → `manacore_poster_url`
	 * → تصویر جانشین قالب. (پیش‌تر `manacore_backdrop_url` هیچ مصرف‌کننده‌ای
	 * نداشت؛ اینجا معنادار شد.)
	 *
	 * @param int    $post_id شناسه.
	 * @param string $size    اندازه‌ی تصویر.
	 * @return string نشانی تصویر.
	 */
	protected function media_url( $post_id, $size = 'large' ) {
		/* همان زنجیره‌ی مشترک `manacore_backdrop_url()` — یک منبع حقیقت. */
		return manacore_backdrop_url( $post_id, $size );
	}

	/**
	 * شماره‌ی فارسی دو‌رقمی («۰۱»، «۰۲»، …).
	 *
	 * @param int $number شماره.
	 * @return string
	 */
	protected function fa_index( $number ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return strtr( str_pad( (string) max( 1, (int) $number ), 2, '0', STR_PAD_LEFT ), array_combine(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$fa
		) );
	}

	/**
	 * زمان تقریبی مطالعه بر پایه‌ی شمار واژه‌های محتوا (۲۰۰ واژه در دقیقه).
	 *
	 * @param int $post_id شناسه‌ی نوشته.
	 * @return int دقیقه.
	 */
	protected function read_minutes( $post_id ) {
		/*
		 * یک منبع حقیقت: همان قراردادی که سرصفحه‌ی مقاله و فهرست
		 * ستون کنار هم از آن استفاده می‌کنند (۲۰۰ واژه در دقیقه).
		 */
		return Article::read_minutes( $post_id );
	}

	/**
	 * رندر «ردیف کالکشن‌ها» (collections-grid مرجع).
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_collection_row( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/collection-row']['attributes'] )
		);

		$count = max( 1, min( 24, (int) $attrs['count'] ) );
		$items = get_posts(
			array(
				'post_type'      => 'collection',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);

		/*
		 * اگر کالکشنی ساخته نشده باشد، به‌جای بخش خالی، دسته‌بندی‌های ژانر
		 * نمایش داده می‌شوند تا بخش همیشه معنا داشته باشد (مرجع هم کارت‌های
		 * «کالکشن» را به ژانرها پیوند می‌دهد).
		 */
		$is_taxonomy = false;
		if ( ! $items ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'genre',
					'hide_empty' => true,
					'number'     => $count,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			if ( is_wp_error( $terms ) || ! $terms ) {
				return Block_Support::render_empty( $attrs, 'manacore-collections' );
			}
			$items       = $terms;
			$is_taxonomy = true;
		}

		$label  = trim( (string) $attrs['label'] );
		$footer = Block_Support::render_header( $attrs );

		ob_start();
		printf(
			'<div %s>',
			Block_Support::wrapper( $attrs, array( 'manacore-collections', 'wp-block-manacore-collection-row' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
		echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="' . esc_attr( Block_Support::grid_classes( array( 'layout' => 'grid' ) ) . ' collections-grid' ) . '"' . Block_Support::grid_style( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		$index = 0;
		foreach ( $items as $item ) {
			$index++;

			if ( $is_taxonomy ) {
				$title = $item->name;
				$desc  = $item->description ? wp_strip_all_tags( $item->description ) : '';
				$url   = get_term_link( $item );
				$image = (string) get_term_meta( $item->term_id, 'manacore_term_image', true );
			} else {
				$title = get_the_title( $item );
				$desc  = wp_strip_all_tags( get_the_excerpt( $item ) );
				$url   = get_permalink( $item );
				$image = $this->media_url( $item->ID, 'large' );
			}

			$url = is_wp_error( $url ) ? '#' : $url;
			?>
			<a class="collection-card" href="<?php echo esc_url( $url ); ?>">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
				<?php endif; ?>

				<?php if ( ! empty( $attrs['showNumber'] ) ) : ?>
					<span class="collection-number" aria-hidden="true"><?php echo esc_html( $this->fa_index( $index ) ); ?></span>
				<?php endif; ?>

				<span class="collection-content">
					<?php if ( $label ) : ?>
						<small><?php echo esc_html( $label ); ?></small>
					<?php endif; ?>
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( ! empty( $attrs['showDescription'] ) && $desc ) : ?>
						<p><?php echo esc_html( wp_trim_words( $desc, 9, '…' ) ); ?></p>
					<?php endif; ?>
				</span>

				<span class="collection-arrow" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg>
				</span>
			</a>
			<?php
		}

		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر «ردیف مجله» (articles-grid مرجع).
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_magazine_row( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/magazine-row']['attributes'] )
		);

		$count    = max( 1, min( 24, (int) $attrs['count'] ) );
		$category = sanitize_title( (string) $attrs['category'] );
		$query    = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
		);

		if ( ! empty( $attrs['excludeCurrent'] ) && is_singular( 'post' ) ) {
			$query['post__not_in'] = array( (int) get_the_ID() );
		}

		if ( $category ) {
			$query['category_name'] = $category;
		}

		$posts = get_posts( $query );

		if ( ! $posts ) {
			return Block_Support::render_empty( $attrs, 'manacore-magazine' );
		}

		$badge  = trim( (string) $attrs['badge'] );
		$words  = max( 4, (int) $attrs['excerptWords'] );
		$link   = trim( (string) $attrs['linkLabel'] );

		/*
		 * تب‌های دسته — مثل مرجع (`#magazine-tabs`) که «همه داستان‌ها» را
		 * کنار نام دسته‌ها می‌گذارد و برجا پالایش می‌کند. دسته‌ها از
		 * تاکسونومی واقعیِ همان نوشته‌های همین شبکه ساخته می‌شوند
		 * (نه فهرست ثابت) تا با هر محتوایی درست بمانند.
		 */
		$tabs        = array();
		$category_map = array();

		if ( ! empty( $attrs['showCategoryTabs'] ) ) {
			foreach ( $posts as $post ) {
				$terms = get_the_category( $post->ID );

				foreach ( $terms as $term ) {
					/*
					 * کلید = شناسه‌ی ترم. نامک دسته‌های غیرلاتین در
					 * وردپرس به شکل `%d9%86...` ذخیره می‌شود و
					 * به‌عنوان مقدار `data-*` ناخوانا و شکننده است؛
					 * شناسه در هر زبان و پس از تغییر نامک هم پایدار
					 * می‌ماند.
					 */
					if ( ! isset( $category_map[ (int) $term->term_id ] ) ) {
						$category_map[ (int) $term->term_id ] = $term->name;
					}
				}
			}

			if ( $category_map ) {
				$tabs[] = array(
					'value'  => 'all',
					'label'  => trim( (string) $attrs['categoryTabAllLabel'] ),
					'active' => true,
				);

				foreach ( $category_map as $term_id => $name ) {
					$tabs[] = array(
						'value'  => (string) $term_id,
						'label'  => $name,
						'active' => false,
					);
				}
			}
		}

		ob_start();
		printf(
			'<div %s>',
			Block_Support::wrapper( $attrs, array( 'manacore-magazine', 'wp-block-manacore-magazine-row' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
		echo Block_Support::render_header( // phpcs:ignore WordPress.Security.EscapeOutput
			$attrs,
			$tabs,
			__( 'پالایش بر اساس دسته', 'manacore' ),
			'data-category'
		);

		$grid_attrs = Block_Support::grid_style( $attrs );

		if ( $tabs ) {
			$grid_attrs .= ' data-manacore-article-filter';
		}

		echo '<div class="' . esc_attr( Block_Support::grid_classes( array( 'layout' => 'grid' ) ) . ' articles-grid' ) . '"' . $grid_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		foreach ( $posts as $post ) {
			$card_categories = implode( ' ', wp_list_pluck( get_the_category( $post->ID ), 'term_id' ) );
			$image   = $this->media_url( $post->ID, 'large' );
			$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
			?>
			<a class="article-card" data-category="<?php echo esc_attr( $card_categories ); ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<div class="article-image">
					<?php if ( $image ) : ?>
						<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
					<?php endif; ?>
					<?php
					/*
					 * برچسب روی تصویر: اگر «دسته‌ی واقعی» روشن باشد نام
					 * نخستین دسته‌ی همان نوشته می‌نشیند (رفتار مرجع) و
					 * وگرنه رشته‌ی ثابتِ ویژگی بلوک.
					 */
					$card_terms  = ! empty( $attrs['showCategoryBadge'] ) ? get_the_category( $post->ID ) : array();
					$card_badge  = $card_terms ? $card_terms[0]->name : $badge;

					if ( $card_badge ) :
						?>
						<span><?php echo esc_html( $card_badge ); ?></span>
					<?php endif; ?>
				</div>

				<div class="article-info">
					<small>
						<?php if ( ! empty( $attrs['showReadTime'] ) ) : ?>
							<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
							<?php
							printf(
								/* translators: %s: تعداد دقیقه‌ی مطالعه */
								esc_html__( '%s دقیقه مطالعه', 'manacore' ),
								esc_html( manacore_fa_digits( number_format_i18n( $this->read_minutes( $post->ID ) ) ) )
							);
							?>
						<?php endif; ?>

						<?php if ( ! empty( $attrs['showDate'] ) ) : ?>
							<?php if ( ! empty( $attrs['showReadTime'] ) ) : ?>
								<i aria-hidden="true"></i>
							<?php endif; ?>
							<?php echo esc_html( manacore_fa_date( $post->post_date ) ); ?>
						<?php endif; ?>
					</small>

					<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>

					<?php if ( ! empty( $attrs['showExcerpt'] ) && $excerpt ) : ?>
						<p><?php echo esc_html( wp_trim_words( $excerpt, $words, '…' ) ); ?></p>
					<?php endif; ?>

					<?php if ( $link ) : ?>
						<span>
							<?php echo esc_html( $link ); ?>
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg>
						</span>
					<?php endif; ?>
				</div>
			</a>
			<?php
		}

		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * رندر «سرصفحه‌ی مجله» — هم‌ارز `.magazine-feature-grid` مرجع.
	 *
	 * ساختار مرجع: یک پیوند بزرگ (`.magazine-feature`) با تصویر، برچسب
	 * دسته، تیتر `h2`، توضیح و ردیف «N دقیقه مطالعه»؛ کنارش
	 * `.magazine-side-features` با کارت‌های کوچک (تصویر + دسته + `h3` +
	 * زمان مطالعه). همه از نوشته‌های واقعی ساخته می‌شود.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_magazine_hero( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/magazine-hero']['attributes'] )
		);

		$count    = max( 2, min( 8, (int) $attrs['count'] ) );
		$category = sanitize_title( (string) $attrs['category'] );
		$query    = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
		);

		if ( $category ) {
			$query['category_name'] = $category;
		}

		$posts = get_posts( $query );

		if ( ! $posts ) {
			return Block_Support::render_empty( $attrs, 'manacore-magazine-hero' );
		}

		$lead  = array_shift( $posts );
		$label = trim( (string) $attrs['readLabel'] );
		$link  = trim( (string) $attrs['linkLabel'] );
		$icon  = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
		$more  = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg>';

		$read = function ( $post_id ) use ( $label, $icon ) {
			if ( '' === $label ) {
				return '';
			}

			return $icon . esc_html(
				str_replace( '{count}', manacore_fa_digits( number_format_i18n( $this->read_minutes( $post_id ) ) ), $label )
			);
		};

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-magazine-hero', 'magazine-feature-grid' ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* مقاله‌ی ویژه. */
		$lead_image = $this->media_url( $lead->ID, 'full' );
		$lead_terms = get_the_category( $lead->ID );
		?>
		<a class="magazine-feature" href="<?php echo esc_url( get_permalink( $lead ) ); ?>">
			<?php if ( $lead_image ) : ?>
				<img src="<?php echo esc_url( $lead_image ); ?>" alt="" decoding="async" />
			<?php endif; ?>
			<div>
				<?php if ( $lead_terms ) : ?>
					<span class="exclusive-tag"><?php echo esc_html( $lead_terms[0]->name ); ?></span>
				<?php endif; ?>
				<h2><?php echo esc_html( get_the_title( $lead ) ); ?></h2>
				<?php if ( ! empty( $attrs['showExcerpt'] ) ) : ?>
					<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $lead ) ), max( 4, (int) $attrs['excerptWords'] ), '…' ) ); ?></p>
				<?php endif; ?>
				<?php if ( $label ) : ?>
					<small><?php echo $read( $lead->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo $more; // phpcs:ignore WordPress.Security.EscapeOutput ?></small>
				<?php endif; ?>
			</div>
		</a>
		<?php

		if ( $posts ) {
			echo '<div class="magazine-side-features">';

			foreach ( $posts as $post ) {
				$image = $this->media_url( $post->ID, 'large' );
				$terms = get_the_category( $post->ID );
				?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
					<?php if ( $image ) : ?>
						<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
					<?php endif; ?>
					<div>
						<?php if ( $terms ) : ?>
							<small><?php echo esc_html( $terms[0]->name ); ?></small>
						<?php endif; ?>
						<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>
						<?php if ( ! empty( $attrs['sideIcon'] ) ) : ?>
							<span>
								<?php echo $read( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php echo $more; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</span>
						<?php endif; ?>
					</div>
				</a>
				<?php
			}

			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * روزهای هفته به ترتیب تقویم ایرانی (شنبه اول).
	 *
	 * @return array نگاشت کلید انگلیسی => برچسب فارسی.
	 */
	public static function week_days() {
		return array(
			'saturday'  => __( 'شنبه', 'manacore' ),
			'sunday'    => __( 'یکشنبه', 'manacore' ),
			'monday'    => __( 'دوشنبه', 'manacore' ),
			'tuesday'   => __( 'سه‌شنبه', 'manacore' ),
			'wednesday' => __( 'چهارشنبه', 'manacore' ),
			'thursday'  => __( 'پنجشنبه', 'manacore' ),
			'friday'    => __( 'جمعه', 'manacore' ),
		);
	}

	/**
	 * کلید روز هفته (انگلیسی، حروف کوچک) با احترام به منطقه‌ی زمانی سایت.
	 *
	 * `wp_date( 'l' )` برچسب روز را بر اساس زبان سایت برمی‌گرداند (مثلاً
	 * «شنبه» در فارسی)، پس برای مقایسه با کلیدهای {@see week_days()} بی‌فایده
	 * است و برنامه‌ی هفتگی روی سایت‌های غیرانگلیسی خالی می‌شد. این متد فقط
	 * منطقه‌ی زمانی را از وردپرس می‌گیرد و نام روز را همیشه انگلیسی می‌دهد.
	 *
	 * @param int|null $timestamp زمان یونیکس؛ خالی یعنی «اکنون».
	 * @return string نام روز انگلیسی با حروف کوچک (مثل `saturday`).
	 */
	protected static function day_key( $timestamp = null ) {
		$time = null === $timestamp ? time() : (int) $timestamp;

		$date = new \DateTimeImmutable( '@' . $time );
		$date = $date->setTimezone( wp_timezone() );

		return $date->format( 'l' );
	}

	/**
	 * کلید روز هفته‌ی یک قسمت.
	 *
	 * اولویت با تاریخ پخش میلادی (`manacore_air_date`) است؛ اگر نبود،
	 * برچسب `program` پست خوانده می‌شود (همان چیزی که در فراداده‌ی
	 * سریال‌ها به‌عنوان «روز پخش» ذخیره می‌شود).
	 *
	 * @param int $post_id شناسه‌ی قسمت.
	 * @return string کلید روز یا رشته‌ی خالی.
	 */
	/**
	 * نوشته‌ی جاری (یا آخرین نوشته در بوم ویرایشگر) — یک منبع برای سه بلوک مقاله.
	 *
	 * در بوم ویرایشگر، «نوشته‌ی جاری» وجود ندارد؛ برای این‌که قالب خالی
	 * نباشد، آخرین نوشته‌ی منتشرشده استفاده می‌شود. همین کار باعث می‌شود
	 * ممیزی ویرایشگر سایت معنادار باشد (بلوک‌ها واقعاً رندر می‌شوند).
	 *
	 * @return \WP_Post|null
	 */
	protected function article_context_post() {
		$post = get_post();

		if ( $post instanceof \WP_Post && 'post' === $post->post_type ) {
			return $post;
		}

		$latest = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);

		return $latest ? $latest[0] : null;
	}

	/**
	 * سرصفحه‌ی مقاله — هم‌ارز `.article-page-header` مرجع.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_article_header( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/article-header']['attributes'] )
		);

		$post = $this->article_context_post();

		if ( ! $post ) {
			return Block_Support::render_empty( $attrs, 'manacore-article-header' );
		}

		$title    = get_the_title( $post );
		$excerpt  = trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) );
		$terms    = get_the_category( $post->ID );
		$category = $terms ? $terms[0] : null;
		$minutes  = manacore_fa_digits( number_format_i18n( Article::read_minutes( $post->ID ) ) );

		$author = trim( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
		if ( '' === $author ) {
			$author = trim( (string) $attrs['authorFallback'] );
		}

		$meta = str_replace(
			array( '{date}', '{minutes}' ),
			array( manacore_fa_date( $post->post_date ), $minutes ),
			trim( (string) $attrs['metaFormat'] )
		);

		ob_start();
		printf(
			'<div %s>',
			Block_Support::wrapper( $attrs, array( 'article-page-header', 'wp-block-manacore-article-header' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		if ( ! empty( $attrs['showCategoryBadge'] ) && $category ) {
			printf(
				'<a class="exclusive-tag" href="%1$s" data-manacore-article-tag>%2$s</a>',
				esc_url( get_term_link( $category ) ),
				esc_html( $category->name )
			);
		}

		printf( '<h1 data-manacore-article-title>%s</h1>', esc_html( $title ) );

		if ( ! empty( $attrs['showDescription'] ) && '' !== $excerpt ) {
			printf( '<p data-manacore-article-description>%s</p>', esc_html( $excerpt ) );
		}

		$initial = function_exists( 'mb_substr' ) ? mb_substr( $author, 0, 1 ) : substr( $author, 0, 1 );
		?>
		<div class="article-author">
			<span class="comment-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
			<span>
				<strong><?php echo esc_html( $author ); ?></strong>
				<?php if ( ! empty( $attrs['showMeta'] ) && '' !== $meta ) : ?>
					<small data-manacore-article-meta><?php echo esc_html( $meta ); ?></small>
				<?php endif; ?>
			</span>
			<?php if ( ! empty( $attrs['showCopyLink'] ) ) : ?>
				<button type="button" class="icon-button" data-manacore-copy-link
					aria-label="<?php esc_attr_e( 'کپی لینک مقاله', 'manacore' ); ?>"><?php echo esc_html( (string) $attrs['copyLabel'] ); ?></button>
			<?php endif; ?>
		</div>
		<?php
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * فهرست مقاله — هم‌ارز `.toc-card` + `.reading-progress` مرجع.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_article_toc( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/article-toc']['attributes'] )
		);

		$post     = $this->article_context_post();
		$chapters = $post ? Article::chapters( $post->ID ) : array();
		$heading  = trim( (string) $attrs['heading'] );
		$eyebrow  = trim( (string) $attrs['eyebrow'] );

		if ( ! $post || ( '' === $heading && ! $chapters ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-article-toc' );
		}

		ob_start();
		printf(
			'<div %s data-manacore-reading>',
			Block_Support::wrapper( $attrs, array( 'toc-card', 'wp-block-manacore-article-toc' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
		);

		if ( '' !== $eyebrow ) {
			printf( '<span class="eyebrow">%s</span>', esc_html( $eyebrow ) );
		}

		if ( '' !== $heading ) {
			printf( '<h3>%s</h3>', esc_html( $heading ) );
		}

		if ( $chapters ) {
			echo '<nav aria-label="' . esc_attr__( 'فصل‌های مقاله', 'manacore' ) . '" data-manacore-toc>';
			foreach ( $chapters as $chapter ) {
				printf( '<a href="#%1$s" data-chapter="%2$d">', esc_attr( $chapter['id'] ), (int) $chapter['index'] );

				if ( ! empty( $attrs['showNumbers'] ) ) {
					printf( '<span aria-hidden="true">%s</span>', esc_html( manacore_fa_digits( (string) ( $chapter['index'] + 1 ) ) ) );
				}

				echo esc_html( $chapter['text'] ) . '</a>';
			}
			echo '</nav>';
		}

		if ( ! empty( $attrs['showProgress'] ) ) {
			$label = str_replace(
				'{percent}',
				'<b data-manacore-percent>0</b>',
				esc_html( trim( (string) $attrs['percentLabel'] ) )
			);
			?>
			<div class="reading-progress"><span data-manacore-progress></span></div>
			<small><?php echo wp_kses( $label, array( 'b' => array( 'data-manacore-percent' => true ) ) ); ?></small>
			<?php
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * آثار مرتبط — هم‌ارز `.article-related` مرجع.
	 *
	 * منبع، واقعی و دومرحله‌ای است:
	 *   ۱) اثر پین‌شده‌ی نویسنده (`postId`، از همان گزینشگر بلوک قسمت‌ها)،
	 *   ۲) پرکردن بقیه از آثاری که برچسب مشترک با همین نوشته دارند و اگر
	 *      نبود، تازه‌ترین آثار منتشرشده.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_related_titles( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/related-titles']['attributes'] )
		);

		$count = max( 1, min( 8, (int) $attrs['count'] ) );
		$types = array_filter( array_map( 'sanitize_key', (array) $attrs['postTypes'] ) );
		$types = $types ? array_values( $types ) : array( 'movie', 'series' );
		$post  = $this->article_context_post();

		$items  = array();
		$taken  = array();
		$source = 'latest';
		$pin    = (int) $attrs['postId'];

		if ( $pin && 'publish' === get_post_status( $pin ) ) {
			$items[]  = get_post( $pin );
			$taken[]  = $pin;
			$source   = 'pinned';
		}

		$need = $count - count( $items );

		if ( $need > 0 && $post ) {
			$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );

			if ( ! is_wp_error( $tags ) && $tags ) {
				$by_tag = get_posts(
					array(
						'post_type'      => $types,
						'post_status'    => 'publish',
						'posts_per_page' => $need,
						'post__not_in'   => $taken, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'post_tag',
								'field'    => 'term_id',
								'terms'    => $tags,
							),
						),
					)
				);

				if ( $by_tag ) {
					$items  = array_merge( $items, $by_tag );
					$taken  = array_merge( $taken, wp_list_pluck( $by_tag, 'ID' ) );
					$source = 'pinned' === $source ? 'pinned+tag' : 'tag';
					$need   = $count - count( $items );
				}
			}
		}

		if ( $need > 0 ) {
			$latest = get_posts(
				array(
					'post_type'      => $types,
					'post_status'    => 'publish',
					'posts_per_page' => $need,
					'post__not_in'   => $taken, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				)
			);

			$items = array_merge( $items, $latest );
		}

		$heading = trim( (string) $attrs['heading'] );
		$link    = Block_Support::more_link( $attrs );

		if ( ! $items ) {
			return '' !== $heading ? Block_Support::render_empty( $attrs, 'manacore-related-titles' ) : '';
		}

		ob_start();
		printf(
			'<div %s data-manacore-related="%s">',
			Block_Support::wrapper( $attrs, array( 'article-related', 'wp-block-manacore-related-titles' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_attr( $source )
		);

		if ( '' !== $heading ) {
			printf( '<h3>%s</h3>', esc_html( $heading ) );
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof \WP_Post ) {
				continue;
			}

			$original = trim( (string) get_post_meta( $item->ID, 'manacore_original_title', true ) );
			?>
			<a href="<?php echo esc_url( (string) get_permalink( $item ) ); ?>">
				<img src="<?php echo esc_url( manacore_poster_url( $item->ID, 'medium' ) ); ?>"
					alt="<?php echo esc_attr( get_the_title( $item ) ); ?>" loading="lazy" decoding="async" />
				<span>
					<strong><?php echo esc_html( get_the_title( $item ) ); ?></strong>
					<?php if ( ! empty( $attrs['showOriginalTitle'] ) && '' !== $original ) : ?>
						<small dir="ltr"><?php echo esc_html( $original ); ?></small>
					<?php endif; ?>
				</span>
				<i aria-hidden="true">‹</i>
			</a>
			<?php
		}

		if ( $link ) {
			echo $link; // phpcs:ignore WordPress.Security.EscapeOutput -- در more_link ساخته و escape شده.
		}

		echo '</div>';

		return (string) ob_get_clean();
	}


	/**
	 * رندر «برنامه‌ی هفتگی» (home-duo مرجع).
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	/**
	 * رندر «برنامه‌ی هفتگی» — دو حالت.
	 *
	 * حالت `episode` همان رفتار صفحه‌ی نخست است: قسمت‌های منتشرشده بر
	 * اساس روز پخششان. حالت `series` هم‌ارز برگه‌ی «برنامه پخش» مرجع
	 * است: هر اثر زمان‌بندی‌شده (با فراداده‌ی «روز پخش») با ساعت پخش و
	 * شمار فصل/قسمت — همان‌طور که در `schedule.html` رندر می‌شود.
	 *
	 * چیدمان `panel` قاب `.schedule-panel.full-schedule` مرجع را می‌سازد
	 * (سرصفحه‌ی `h2`/زیرنویس/نشان‌گر «به وقت …») و چیدمان `block` همان
	 * سرصفحه‌ی استاندارد بلوک‌ها را نگه می‌دارد تا صفحه‌ی نخست دست‌نخورده
	 * بماند.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_schedule( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/schedule']['attributes'] )
		);

		$days    = self::week_days();
		$mode    = 'series' === $attrs['mode'] ? 'series' : 'episode';
		$panel   = 'panel' === $attrs['layout'];
		$per_day = max( 1, min( 24, (int) $attrs['perDay'] ) );

		$by_day = 'series' === $mode
			? $this->schedule_series_by_day( $attrs, $per_day )
			: $this->schedule_episodes_by_day( $per_day );

		/*
		 * در چیدمان «پنل» حتی با داده‌ی خالی هم قاب ساخته می‌شود: مرجع
		 * سرصفحه و نوار روزها را همیشه دارد و جای خالی را با
		 * `.schedule-empty` پر می‌کند.
		 */
		if ( ! $by_day && ! $panel ) {
			return Block_Support::render_empty( $attrs, 'manacore-schedule' );
		}

		$today     = strtolower( self::day_key() );
		$preferred = sanitize_key( (string) $attrs['activeDay'] );
		$active    = 'today' === $preferred ? $today : $preferred;

		if ( ! isset( $days[ $active ] ) ) {
			$active = 'saturday';
		}

		/*
		 * در حالت «سریال» روز انتخابی عوض نمی‌شود حتی اگر خالی باشد؛
		 * مرجع هم همین کار را می‌کند و برای آن روز حالت خالی نشان می‌دهد.
		 * در حالت «قسمت» رفتار پیشین (پریدن به نخستین روزِ دارای داده)
		 * حفظ می‌شود تا صفحه‌ی نخست تغییر نکند.
		 */
		if ( 'episode' === $mode && ! isset( $by_day[ $active ] ) ) {
			$keys   = array_keys( $by_day );
			$active = $keys[0];
		}

		$footnote = trim( (string) $attrs['footnote'] );
		if ( '' === $footnote ) {
			$footnote = 'series' === $mode
				? __( 'روز و ساعت هر اثر از فراداده‌ی خودش خوانده می‌شود.', 'manacore' )
				: __( 'زمان‌بندی این بخش نمونه است و از تاریخ انتشار قسمت‌ها ساخته می‌شود.', 'manacore' );
		}

		/*
		 * کلاس‌ها: `wp-block-manacore-schedule` برای مهار، `manacore-schedule`
		 * برای قاعده‌های پایه‌ی بلوک، و در چیدمان پنل `schedule-panel` و
		 * `full-schedule` برای نام‌های دقیق مرجع. افزودن `manacore-schedule`
		 * به پنل باعث می‌شود بلوک در هر پوسته‌ای (و در بوم ویرایشگر) قاب
		 * درست خودش را داشته باشد.
		 */
		$classes = array( 'wp-block-manacore-schedule', 'manacore-schedule', 'schedule-panel' );
		if ( $panel ) {
			$classes[] = 'full-schedule';
		}

		ob_start();
		?>
		<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php
			/*
			 * سرصفحه در **هر دو** چیدمان همان `.schedule-title` مرجع است.
			 * پیش‌تر چیدمان «بلوک» سرصفحه‌ی عمومی بلوک‌ها را می‌ساخت و
			 * نتیجه، دو ساختار و دو دسته اندازه‌ی متفاوت با مرجع بود.
			 */
			$this->render_schedule_title( $attrs, $panel );
			?>

			<div class="week-tabs" role="tablist" aria-label="<?php esc_attr_e( 'روزهای هفته', 'manacore' ); ?>">
				<?php foreach ( $days as $key => $label ) : ?>
					<?php $has = ! empty( $by_day[ $key ] ); ?>
					<button type="button"
						class="manacore-day-tab<?php echo $key === $active ? ' active' : ''; ?>"
						role="tab"
						id="schedule-tab-<?php echo esc_attr( $key ); ?>"
						aria-controls="schedule-panel-<?php echo esc_attr( $key ); ?>"
						aria-selected="<?php echo $key === $active ? 'true' : 'false'; ?>"
						data-day="<?php echo esc_attr( $key ); ?>"
						<?php echo $has ? '' : 'data-empty="1"'; ?>>
						<?php echo esc_html( $label ); ?>
						<?php if ( $key === $today ) : ?>
							<span class="today-dot" aria-hidden="true"></span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $days as $key => $label ) : ?>
				<div class="manacore-schedule-day schedule-day"
					id="schedule-panel-<?php echo esc_attr( $key ); ?>"
					role="tabpanel"
					aria-labelledby="schedule-tab-<?php echo esc_attr( $key ); ?>"
					tabindex="0"
					<?php echo $key === $active ? '' : 'hidden'; ?>>
					<?php if ( empty( $by_day[ $key ] ) ) : ?>
						<?php $this->render_schedule_empty( $attrs, $mode ); ?>
					<?php else : ?>
						<div class="schedule-items">
							<?php foreach ( $by_day[ $key ] as $schedule_item ) : ?>
								<?php
								if ( 'series' === $mode ) {
									$this->render_schedule_series_item( $attrs, $schedule_item );
								} else {
									$this->render_schedule_episode_item( $attrs, $schedule_item );
								}
								?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<?php if ( ! empty( $attrs['showFootnote'] ) && '' !== $footnote ) : ?>
				<p class="schedule-footnote">
					<span class="live-dot" aria-hidden="true"></span>
					<?php echo esc_html( $footnote ); ?>
				</p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * سرصفحه‌ی پنل برنامه (`.schedule-title` مرجع).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return void
	 */
	protected function render_schedule_title( $attrs, $panel = true ) {
		if ( $panel ) {
			$title    = trim( (string) $attrs['panelTitle'] );
			$title    = '' !== $title ? $title : __( 'قرارهای این هفته', 'manacore' );
			$subtitle = trim( (string) $attrs['panelSubtitle'] );
			$subtitle = '' !== $subtitle ? $subtitle : __( 'برنامه‌ی هفتگی آثار', 'manacore' );
			$icon     = trim( (string) $attrs['panelIcon'] );
		} else {
			$title    = trim( (string) $attrs['heading'] );
			$title    = '' !== $title ? $title : __( 'هر روز، یک قسمت تازه', 'manacore' );
			$subtitle = trim( (string) $attrs['subheading'] );
			$subtitle = '' !== $subtitle ? $subtitle : __( 'برنامه هفتگی سریال‌ها', 'manacore' );
			$icon     = '';
		}

		/*
		 * نشانه‌ی سرصفحه: در چیدمان پنل نویسه‌ی دلخواه (`panelIcon`) و در
		 * چیدمان بلوک همان آیکون SVG سیستم (`headingIcon`) — همان کاری که
		 * صفحه‌ی نخست مرجع با آیکون تقویم می‌کند.
		 */
		$svg = Block_Support::heading_icon( isset( $attrs['headingIcon'] ) ? $attrs['headingIcon'] : '' );
		?>
		<div class="schedule-title">
			<?php if ( '' !== $svg || '' !== $icon ) : ?>
				<span class="section-icon" aria-hidden="true"><?php echo '' !== $svg ? $svg : esc_html( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php endif; ?>
			<div>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $subtitle ); ?></p>
			</div>
			<?php if ( $panel && ! empty( $attrs['showTimezone'] ) ) : ?>
				<?php $zone = trim( (string) $attrs['timezoneLabel'] ); ?>
				<span class="timezone">
					<span aria-hidden="true">◷</span>
					<?php echo esc_html( '' !== $zone ? $zone : __( 'به وقت محلی', 'manacore' ) ); ?>
				</span>
			<?php elseif ( ! $panel ) : ?>
				<?php echo Block_Support::more_link( Block_Support::resolve_more_attrs( $attrs ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * حالت خالی یک روز.
	 *
	 * @param array  $attrs ویژگی‌ها.
	 * @param string $mode  حالت بلوک.
	 * @return void
	 */
	protected function render_schedule_empty( $attrs, $mode ) {
		if ( 'series' !== $mode ) {
			?>
			<p class="manacore-schedule-empty"><?php esc_html_e( 'برای این روز قسمتی ثبت نشده است.', 'manacore' ); ?></p>
			<?php
			return;
		}

		$message = trim( (string) $attrs['emptyMessage'] );
		$message = '' !== $message ? $message : __( 'امروز، وقت کشف یک داستان تازه است.', 'manacore' );

		$label = trim( (string) $attrs['emptyLinkLabel'] );
		$url   = trim( (string) $attrs['emptyLinkUrl'] );
		if ( '' === $label ) {
			$label = __( 'پیشنهادهای ما', 'manacore' );
		}
		if ( '' === $url ) {
			$url = manacore_discovery_url( array( 'mc_sort' => 'rating' ) );
		}
		?>
		<div class="schedule-empty">
			<span aria-hidden="true">✦</span>
			<p><?php echo esc_html( $message ); ?></p>
			<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?> <span aria-hidden="true">‹</span></a>
		</div>
		<?php
	}

	/**
	 * یک ردیف برنامه در حالت «سریال» (`.schedule-item` مرجع).
	 *
	 * @param array   $attrs ویژگی‌ها.
	 * @param WP_Post $item  اثر زمان‌بندی‌شده.
	 * @return void
	 */
	protected function render_schedule_series_item( $attrs, $item ) {
		$original = trim( (string) get_post_meta( $item->ID, 'manacore_original_title', true ) );
		$seasons  = manacore_series_seasons_count( $item->ID );
		$episodes = manacore_series_episodes_count( $item->ID );
		$time     = trim( (string) get_post_meta( $item->ID, 'manacore_air_time', true ) );
		?>
		<a class="schedule-item" href="<?php echo esc_url( (string) get_permalink( $item ) ); ?>">
			<?php if ( ! empty( $attrs['showThumb'] ) ) : ?>
				<img src="<?php echo esc_url( manacore_poster_url( $item->ID, 'medium' ) ); ?>"
					alt="" loading="lazy" decoding="async" />
			<?php endif; ?>
			<div>
				<h3><?php echo esc_html( get_the_title( $item ) ); ?></h3>
				<?php if ( ! empty( $attrs['showOriginalTitle'] ) && '' !== $original ) : ?>
					<p dir="ltr"><?php echo esc_html( $original ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $attrs['showSeasonMeta'] ) && ( $seasons || $episodes ) ) : ?>
					<span>
						<?php
						$bits = array();
						if ( $seasons ) {
							/* translators: %s: شمار فصل‌ها */
							$bits[] = sprintf( __( 'فصل %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $seasons ) ) );
						}
						if ( $episodes ) {
							/* translators: %s: شمار قسمت‌ها */
							$bits[] = sprintf( __( '%s قسمت', 'manacore' ), manacore_fa_digits( number_format_i18n( $episodes ) ) );
						}
						$first = array_shift( $bits );
						echo esc_html( (string) $first );
						foreach ( $bits as $bit ) {
							echo '<i aria-hidden="true"></i>' . esc_html( $bit );
						}
						?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $attrs['showTime'] ) && '' !== $time ) : ?>
				<?php /* نشانه‌ی ساعت در مرجع نویسه‌ی متنی است، نه آیکون. */ ?>
				<span class="schedule-time">◷ <?php echo esc_html( manacore_fa_digits( $time ) ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $attrs['showPlay'] ) ) : ?>
				<span class="schedule-play" aria-hidden="true">▶</span>
			<?php endif; ?>
		</a>
		<?php
	}

	/**
	 * یک ردیف برنامه در حالت «قسمت» (رفتار پیشین صفحه‌ی نخست).
	 *
	 * @param array   $attrs   ویژگی‌ها.
	 * @param WP_Post $episode قسمت.
	 * @return void
	 */
	protected function render_schedule_episode_item( $attrs, $episode ) {
		?>
		<a class="schedule-item" href="<?php echo esc_url( get_permalink( $episode ) ); ?>">
			<?php if ( ! empty( $attrs['showThumb'] ) ) : ?>
				<img src="<?php echo esc_url( manacore_poster_url( $episode->ID, 'thumbnail' ) ); ?>"
					alt="" loading="lazy" decoding="async" width="46" height="46" />
			<?php endif; ?>
			<div class="manacore-schedule-item-text">
				<h3><?php echo esc_html( get_the_title( $episode ) ); ?></h3>
				<?php if ( ! empty( $attrs['showEpisode'] ) ) : ?>
					<span>
						<?php
						$season  = (int) get_post_meta( $episode->ID, 'manacore_season_number', true );
						$number  = (int) get_post_meta( $episode->ID, 'manacore_episode_number', true );
						$runtime = (int) get_post_meta( $episode->ID, 'manacore_runtime', true );

						$bits = array();
						if ( $season ) {
							/* translators: %s: شماره فصل */
							$bits[] = sprintf( __( 'فصل %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $season ) ) );
						}
						if ( $number ) {
							/* translators: %s: شماره قسمت */
							$bits[] = sprintf( __( 'قسمت %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $number ) ) );
						}
						if ( $runtime ) {
							/* translators: %s: تعداد دقیقه */
							$bits[] = sprintf( __( '%s دقیقه', 'manacore' ), manacore_fa_digits( number_format_i18n( $runtime ) ) );
						}

						echo esc_html( implode( ' • ', $bits ) );
						?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $attrs['showTime'] ) ) : ?>
				<?php $air = (string) get_post_meta( $episode->ID, 'manacore_air_date', true ); ?>
				<?php if ( $air ) : ?>
					<span class="schedule-time">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
						<?php echo esc_html( manacore_fa_digits( wp_date( 'H:i', strtotime( $air ) ) ) ); ?>
					</span>
				<?php endif; ?>
			<?php endif; ?>
			<span class="schedule-play" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
			</span>
		</a>
		<?php
	}

	/**
	 * قسمت‌های منتشرشده، گروه‌بندی‌شده بر اساس روز پخش.
	 *
	 * @param int $per_day سقف هر روز.
	 * @return array
	 */
	protected function schedule_episodes_by_day( $per_day ) {
		$days     = self::week_days();
		$episodes = get_posts(
			array(
				'post_type'      => 'episode',
				'post_status'    => 'publish',
				'posts_per_page' => $per_day * count( $days ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$by_day = array();
		foreach ( $episodes as $episode ) {
			$day = $this->episode_day( $episode->ID );
			if ( ! $day || ! isset( $days[ $day ] ) ) {
				continue;
			}
			if ( ! isset( $by_day[ $day ] ) ) {
				$by_day[ $day ] = array();
			}
			if ( count( $by_day[ $day ] ) >= $per_day ) {
				continue;
			}
			$by_day[ $day ][] = $episode;
		}

		return $by_day;
	}

	/**
	 * آثار زمان‌بندی‌شده (`manacore_air_day`)، گروه‌بندی‌شده بر اساس روز.
	 *
	 * @param array $attrs   ویژگی‌ها.
	 * @param int   $per_day سقف هر روز.
	 * @return array
	 */
	protected function schedule_series_by_day( $attrs, $per_day ) {
		$days  = self::week_days();
		$types = array();

		foreach ( (array) $attrs['postTypes'] as $type ) {
			$type = sanitize_key( (string) $type );
			if ( $type && in_array( $type, manacore_title_post_types(), true ) ) {
				$types[] = $type;
			}
		}

		if ( ! $types ) {
			$types = array( 'series' );
		}

		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => min( 200, $per_day * count( $days ) * count( $types ) ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'manacore_air_day',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'manacore_air_day',
						'value'   => '',
						'compare' => '!=',
					),
				),
				'orderby'        => array(
					'date' => 'DESC',
				),
			)
		);

		$by_day = array();
		foreach ( $posts as $post ) {
			$day = strtolower( (string) get_post_meta( $post->ID, 'manacore_air_day', true ) );
			if ( ! $day || ! isset( $days[ $day ] ) ) {
				continue;
			}
			if ( ! isset( $by_day[ $day ] ) ) {
				$by_day[ $day ] = array();
			}
			if ( count( $by_day[ $day ] ) >= $per_day ) {
				continue;
			}
			$by_day[ $day ][] = $post;
		}

		return $by_day;
	}

	/**
	 * بنر خوش‌آمد تب «دنیای من» (`.account-welcome-banner`).
	 *
	 * متن بنر با «سلیقه‌ی» واقعی کاربر عوض می‌شود: اگر ژانر غالبی داشته
	 * باشد، عنوان و توضیح دومی می‌آید که ژانر را نام می‌برد.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_welcome( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-welcome']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$stats   = Account::stats();
		$genre   = (string) $stats['genre'];
		$title   = '' !== $genre
			? str_replace( '{genre}', $genre, (string) $attrs['titleWithGenre'] )
			: (string) $attrs['title'];
		$text    = '' !== $genre ? (string) $attrs['textWithGenre'] : (string) $attrs['text'];
		$tab     = sanitize_key( (string) $attrs['linkTab'] );
		$url     = (string) Account::page_url( $tab ? array( 'tab' => $tab ) : array() );
		$eyebrow = trim( (string) $attrs['eyebrow'] );

		ob_start();
		?>
		<section <?php echo Block_Support::wrapper( $attrs, 'account-welcome-banner' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<div>
				<?php if ( '' !== $eyebrow ) : ?>
					<span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<?php endif; ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $text ); ?></p>
				<?php if ( '' !== trim( (string) $attrs['linkLabel'] ) && '' !== $url ) : ?>
					<?php
					/*
					 * مرجع فلش را **درون همان متن** دارد («کشف سلیقه من ‹») و چون
					 * لینک `inline-flex` است، فاصله‌ی `gap` بین دو فرزند نصبی
					 * نمی‌شود؛ برای هم‌سانی پیکسلی (۸۲px ↔ ۸۲px) هم همان کار
					 * انجام می‌شود و فلش بخشی از همان رشته‌ی متنی است.
					 */
					$label = trim( (string) $attrs['linkLabel'] );
					?>
					<a class="text-link" data-tab-link="<?php echo esc_attr( $tab ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( '' !== $label ? $label . ' ‹' : '' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $attrs['showArt'] ) && '' !== trim( (string) $attrs['artIcon'] ) ) : ?>
				<span aria-hidden="true"><?php echo esc_html( (string) $attrs['artIcon'] ); ?></span>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * تاریخچه‌ی تماشا (`.history-list`) با نوار پیشرفت واقعی.
	 *
	 * ردیف‌ها از `Account::history()` می‌آیند: پیشرفت ثبت‌شده‌ی پلیر
	 * (`manacore_progress`) و آثار «دیده‌شده» کاربر.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_history( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-history']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$rows = Account::history( 0, max( 1, (int) $attrs['limit'] ) );

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'account-history' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php if ( '' !== trim( (string) $attrs['heading'] ) || '' !== trim( (string) $attrs['subheading'] ) ) : ?>
				<div class="section-heading">
					<div class="heading-title">
						<div>
							<?php if ( '' !== trim( (string) $attrs['heading'] ) ) : ?>
								<h2><?php echo esc_html( (string) $attrs['heading'] ); ?></h2>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $attrs['subheading'] ) ) : ?>
								<p><?php echo esc_html( (string) $attrs['subheading'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( ! empty( $attrs['showClear'] ) && $rows ) : ?>
						<form method="post" action="<?php echo esc_url( (string) Account::page_url( array( 'tab' => 'history' ) ) ); ?>">
							<input type="hidden" name="manacore_form" value="clear-history">
							<?php wp_nonce_field( 'manacore_account_form', 'manacore_nonce' ); ?>
							<button type="submit" class="text-link danger"><?php echo esc_html( (string) $attrs['clearLabel'] ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="history-list">
				<?php if ( ! $rows ) : ?>
					<div class="empty-state">
						<?php if ( '' !== trim( (string) $attrs['emptyTitle'] ) ) : ?>
							<h3><?php echo esc_html( (string) $attrs['emptyTitle'] ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $attrs['emptyText'] ) ) : ?>
							<p><?php echo esc_html( (string) $attrs['emptyText'] ); ?></p>
						<?php endif; ?>
						<?php
						$empty_url = (string) manacore_resolve_link( (string) $attrs['emptyLinkUrl'] );
						if ( '' !== trim( (string) $attrs['emptyLinkLabel'] ) && '' !== $empty_url ) :
							?>
							<a class="button primary" href="<?php echo esc_url( $empty_url ); ?>"><?php echo esc_html( (string) $attrs['emptyLinkLabel'] ); ?> <span aria-hidden="true">‹</span></a>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<?php
					foreach ( $rows as $row ) :
						$post_id  = (int) $row['post_id'];
						$title    = get_the_title( $post_id );
						$original = (string) get_post_meta( $post_id, 'manacore_original_title', true );
						$percent  = max( 0, min( 100, (float) $row['percent'] ) );
						$poster   = function_exists( 'manacore_poster_url' ) ? (string) manacore_poster_url( $post_id, 'medium' ) : '';
						$player   = Player::page_url( $post_id );
						?>
						<div class="history-item">
							<?php if ( '' !== $poster ) : ?>
								<img src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async">
							<?php endif; ?>
							<div>
								<h3><?php echo esc_html( $title ); ?></h3>
								<?php if ( '' !== $original ) : ?>
									<p><?php echo esc_html( $original ); ?></p>
								<?php endif; ?>
								<div class="history-progress"><span style="width:<?php echo esc_attr( (string) round( $percent, 1 ) ); ?>%"></span></div>
								<small>
									<?php
									if ( $percent >= 95 ) {
										echo esc_html( (string) $attrs['completeLabel'] );
									} else {
										echo esc_html(
											str_replace( '{percent}', manacore_fa_digits( (int) round( $percent ) ), (string) $attrs['percentLabel'] )
										);
									}
									?>
								</small>
							</div>
							<?php if ( '' !== $player ) : ?>
								<a class="button secondary small" href="<?php echo esc_url( $player ); ?>">▶ <?php echo esc_html( (string) $attrs['resumeLabel'] ); ?></a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * چهار کارت تحلیل سلیقه (`.analytics-grid`).
	 *
	 * همه‌ی نمودارها از `Account::analytics()` ساخته می‌شوند: حلقه‌ی ژانرها
	 * با `conic-gradient`، سهم فرمت، نمودار هفت‌روزه و کشورها.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_analytics( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-analytics']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$data    = Account::analytics();
		$genres  = (array) $data['genres'];
		$colors  = array( '#c7ef76', '#9dbeef', '#eaba7f', '#c1a2ec', '#69c7b5' );
		$stops   = array();
		$offset  = 0;
		$legend  = array();

		foreach ( $genres as $index => $row ) {
			$color    = $colors[ $index % count( $colors ) ];
			$stops[]  = $color . ' ' . $offset . '% ' . ( $offset + (int) $row['percent'] ) . '%';
			$offset  += (int) $row['percent'];
			$legend[] = array(
				'color'   => $color,
				'name'    => (string) $row['name'],
				'percent' => manacore_fa_digits( (int) $row['percent'] ) . '٪',
			);
		}

		$donut = $stops
			? 'background:conic-gradient(' . implode( ',', $stops ) . ');'
			: 'background:var(--mc-border);';

		$top_genre = $genres ? (string) $genres[0]['name'] : (string) $attrs['donutEmptyTitle'];
		$top_pct   = $genres
			? manacore_fa_digits( (int) $genres[0]['percent'] ) . '٪'
			: (string) $attrs['donutEmptyText'];

		/* سهم فرمت: فیلم و سریال (انیمه هم مثل مرجع به فهرست فیلم/سریال می‌آید). */
		$formats = array();
		foreach ( (array) $data['formats'] as $row ) {
			$formats[ (string) $row['type'] ] = $row;
		}
		$movie = isset( $formats['movie'] ) ? $formats['movie'] : array( 'count' => 0, 'percent' => 0 );
		$serie = isset( $formats['series'] ) ? $formats['series'] : array( 'count' => 0, 'percent' => 0 );

		$activity = (array) $data['activity'];
		$max_act  = 0;
		foreach ( $activity as $day ) {
			$max_act = max( $max_act, (int) $day['count'] );
		}

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'account-analytics' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php if ( '' !== trim( (string) $attrs['heading'] ) || '' !== trim( (string) $attrs['subheading'] ) ) : ?>
				<div class="section-heading">
					<div class="heading-title">
						<div>
							<?php if ( '' !== trim( (string) $attrs['heading'] ) ) : ?>
								<h2><?php echo esc_html( (string) $attrs['heading'] ); ?></h2>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $attrs['subheading'] ) ) : ?>
								<p><?php echo esc_html( (string) $attrs['subheading'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="analytics-grid">
				<?php if ( ! empty( $attrs['showGenreCard'] ) ) : ?>
					<section class="analytics-card genre-chart-card">
						<h3><?php echo esc_html( (string) $attrs['genreTitle'] ); ?></h3>
						<p><?php echo esc_html( (string) $attrs['genreText'] ); ?></p>
						<div class="genre-chart-body">
							<div class="donut-chart" style="<?php echo esc_attr( $donut ); ?>">
								<span>
									<strong><?php echo esc_html( $top_genre ); ?></strong>
									<small><?php echo esc_html( $top_pct ); ?></small>
								</span>
							</div>
							<div class="chart-legend">
								<?php if ( $legend ) : ?>
									<?php foreach ( $legend as $row ) : ?>
										<div>
											<span style="background:<?php echo esc_attr( $row['color'] ); ?>"></span>
											<p><?php echo esc_html( $row['name'] ); ?></p>
											<b><?php echo esc_html( $row['percent'] ); ?></b>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="muted"><?php echo esc_html( (string) $attrs['legendEmptyText'] ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $attrs['showFormatCard'] ) ) : ?>
					<section class="analytics-card format-chart-card">
						<h3><?php echo esc_html( (string) $attrs['formatTitle'] ); ?></h3>
						<p><?php echo esc_html( (string) $attrs['formatText'] ); ?></p>
						<div class="format-stat">
							<span aria-hidden="true"><?php echo esc_html( (string) $attrs['movieIcon'] ); ?></span>
							<div>
								<strong><?php esc_html_e( 'فیلم سینمایی', 'manacore' ); ?></strong>
								<small><?php echo esc_html( manacore_fa_digits( (int) $movie['count'] ) . ' ' . __( 'اثر', 'manacore' ) ); ?></small>
							</div>
							<b><?php echo esc_html( manacore_fa_digits( (int) $movie['percent'] ) . '٪' ); ?></b>
						</div>
						<div class="format-progress"><span style="width:<?php echo esc_attr( (string) (int) $movie['percent'] ); ?>%"></span></div>
						<div class="format-stat series">
							<span aria-hidden="true"><?php echo esc_html( (string) $attrs['seriesIcon'] ); ?></span>
							<div>
								<strong><?php esc_html_e( 'سریال', 'manacore' ); ?></strong>
								<small><?php echo esc_html( manacore_fa_digits( (int) $serie['count'] ) . ' ' . __( 'اثر', 'manacore' ) ); ?></small>
							</div>
							<b><?php echo esc_html( manacore_fa_digits( (int) $serie['percent'] ) . '٪' ); ?></b>
						</div>
						<div class="format-progress series"><span style="width:<?php echo esc_attr( (string) (int) $serie['percent'] ); ?>%"></span></div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $attrs['showActivityCard'] ) ) : ?>
					<section class="analytics-card activity-chart-card">
						<h3><?php echo esc_html( (string) $attrs['activityTitle'] ); ?></h3>
						<p><?php echo esc_html( (string) $attrs['activityText'] ); ?></p>
						<div class="bar-chart">
							<?php foreach ( $activity as $day ) : ?>
								<?php
								/*
								 * میله‌ی هر روز از داده‌ی واقعی ساخته می‌شود: نسبت
								 * به پرکارترین روز هفته، با کمینه‌ی ۳ پیکسل مثل مرجع.
								 */
								$height = $max_act ? max( 3, (int) round( ( (int) $day['count'] / $max_act ) * 90 ) ) : 3;
								?>
								<div>
									<span><?php echo esc_html( manacore_fa_digits( (int) $day['count'] ) ); ?></span>
									<i style="height:<?php echo esc_attr( (string) $height ); ?>px"></i>
									<small><?php echo esc_html( (string) $day['label'] ); ?></small>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $attrs['showCountryCard'] ) ) : ?>
					<section class="analytics-card country-chart-card">
						<h3><?php echo esc_html( (string) $attrs['countryTitle'] ); ?></h3>
						<p><?php echo esc_html( (string) $attrs['countryText'] ); ?></p>
						<div class="country-bars">
							<?php if ( ! empty( $data['countries'] ) ) : ?>
								<?php foreach ( (array) $data['countries'] as $row ) : ?>
									<div>
										<span><?php echo esc_html( (string) $row['name'] ); ?><b><?php echo esc_html( manacore_fa_digits( (int) $row['count'] ) . ' ' . __( 'اثر', 'manacore' ) ); ?></b></span>
										<i><span style="width:<?php echo esc_attr( (string) (int) $row['percent'] ); ?>%"></span></i>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<p class="muted"><?php echo esc_html( (string) $attrs['countryEmptyText'] ); ?></p>
							<?php endif; ?>
						</div>
					</section>
				<?php endif; ?>
			</div>

			<?php if ( '' !== trim( (string) $attrs['privacyText'] ) ) : ?>
				<p class="analytics-privacy">
					<?php if ( '' !== trim( (string) $attrs['privacyIcon'] ) ) : ?>
						<span aria-hidden="true"><?php echo esc_html( (string) $attrs['privacyIcon'] ); ?></span>
					<?php endif; ?>
					<?php echo esc_html( (string) $attrs['privacyText'] ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * وضعیت اشتراک + فهرست صورت‌حساب‌ها (تب «اشتراک و صورت‌حساب»).
	 *
	 * منبع حقیقت، افزونه‌ی اشتراک‌ها است: سطح کاربر (`Access`)، تاریخ
	 * اعتبار و سفارش‌های ووکامرس (اگر فعال باشد). هیچ ردیفی ساخته نمی‌شود.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_invoices( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-invoices']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$profile = Account::profile();
		$level   = '';
		$expiry  = 0;

		if ( function_exists( 'manacore_subs_user_level' ) ) {
			$level  = (string) manacore_subs_user_level();
			$expiry = (int) manacore_subs_expiry();
		}

		$label    = Account::membership_label();
		$title    = (string) $attrs['guestTitle'];
		$text     = (string) $attrs['guestText'];
		$button   = (string) $attrs['guestButtonLabel'];
		$button_u = function_exists( 'manacore_subs_url' ) ? (string) manacore_subs_url() : '';

		if ( $level ) {
			$title  = str_replace( '{plan}', $label, (string) $attrs['activeTitle'] );
			$text   = str_replace(
				array( '{date}', '{days}' ),
				array(
					$expiry ? date_i18n( get_option( 'date_format' ), $expiry ) : __( 'نامحدود', 'manacore' ),
					$expiry ? manacore_fa_digits( (int) ceil( max( 0, $expiry - time() ) / DAY_IN_SECONDS ) ) : '∞',
				),
				(string) $attrs['activeText']
			);
			$button = (string) $attrs['manageLabel'];
			$button_u = function_exists( 'manacore_subs_wc_active' ) && manacore_subs_wc_active() && function_exists( 'wc_get_account_endpoint_url' )
				? (string) wc_get_account_endpoint_url( 'subscriptions' )
				: $button_u;
		}

		$orders = $this->account_orders( max( 1, (int) $attrs['limit'] ) );

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'account-invoices' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php if ( '' !== trim( (string) $attrs['heading'] ) || '' !== trim( (string) $attrs['subheading'] ) ) : ?>
				<div class="section-heading">
					<div>
						<?php if ( '' !== trim( (string) $attrs['heading'] ) ) : ?>
							<h2><?php echo esc_html( (string) $attrs['heading'] ); ?></h2>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $attrs['subheading'] ) ) : ?>
							<p><?php echo esc_html( (string) $attrs['subheading'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="subscription-status-card">
				<?php if ( '' !== trim( (string) $attrs['statusIcon'] ) ) : ?>
					<span aria-hidden="true"><?php echo esc_html( (string) $attrs['statusIcon'] ); ?></span>
				<?php endif; ?>
				<div>
					<?php if ( '' !== trim( (string) $attrs['statusEyebrow'] ) ) : ?>
						<p class="eyebrow"><?php echo esc_html( (string) $attrs['statusEyebrow'] ); ?></p>
					<?php endif; ?>
					<h2><?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $text ); ?></p>
				</div>
				<?php if ( '' !== $button_u ) : ?>
					<a class="button primary" href="<?php echo esc_url( $button_u ); ?>"><?php echo esc_html( $button ); ?> <span aria-hidden="true">‹</span></a>
				<?php endif; ?>
			</div>

			<section class="account-section">
				<?php if ( '' !== trim( (string) $attrs['invoicesTitle'] ) ) : ?>
					<h3 class="subsection-title"><?php echo esc_html( (string) $attrs['invoicesTitle'] ); ?></h3>
				<?php endif; ?>
				<div class="invoice-table">
					<?php if ( ! $orders ) : ?>
						<div class="empty-state">
							<h3><?php echo esc_html( (string) $attrs['emptyTitle'] ); ?></h3>
							<p><?php echo esc_html( (string) $attrs['emptyText'] ); ?></p>
						</div>
					<?php else : ?>
						<div class="invoice-row invoice-header">
							<span><?php echo esc_html( (string) $attrs['planLabel'] ); ?></span>
							<span><?php echo esc_html( (string) $attrs['dateLabel'] ); ?></span>
							<span><?php echo esc_html( (string) $attrs['amountLabel'] ); ?></span>
							<span><?php echo esc_html( (string) $attrs['statusLabel'] ); ?></span>
							<span></span>
						</div>
						<?php foreach ( $orders as $order ) : ?>
							<div class="invoice-row">
								<strong><?php echo esc_html( $order['plan'] ); ?></strong>
								<span><?php echo esc_html( $order['date'] ); ?></span>
								<span><?php echo esc_html( $order['amount'] ); ?></span>
								<b class="invoice-status"><?php echo esc_html( $order['status'] ); ?></b>
								<?php if ( $order['url'] ) : ?>
									<a class="icon-button" href="<?php echo esc_url( $order['url'] ); ?>" aria-label="<?php esc_attr_e( 'مشاهده‌ی صورت‌حساب', 'manacore' ); ?>">↓</a>
								<?php else : ?>
									<span></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ردیف‌های صورت‌حساب از سفارش‌های واقعی ووکامرس.
	 *
	 * @param int $limit بیشترین تعداد ردیف.
	 * @return array
	 */
	protected function account_orders( $limit = 5 ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! is_user_logged_in() ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer' => get_current_user_id(),
				'limit'    => max( 1, (int) $limit ),
				'orderby'  => 'date',
				'order'    => 'DESC',
				'status'   => array_keys( wc_get_order_statuses() ),
			)
		);

		$rows = array();
		foreach ( (array) $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
				continue;
			}

			$items  = $order->get_items();
			$plan   = '';
			foreach ( (array) $items as $item ) {
				$plan = $item->get_name();
				break;
			}

			$rows[] = array(
				'plan'   => '' !== $plan ? $plan : __( 'خرید', 'manacore' ),
				'date'   => $order->get_date_created() ? date_i18n( get_option( 'date_format' ), $order->get_date_created()->getTimestamp() ) : '',
				'amount' => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'status' => wc_get_order_status_name( $order->get_status() ),
				'url'    => function_exists( 'wc_get_endpoint_url' ) ? (string) $order->get_view_order_url() : '',
			);
		}

		return $rows;
	}

	/**
	 * پروفایل و تنظیمات کاربر (تب «تنظیمات حساب»).
	 *
	 * فرم‌ها **بومی** هستند (`method="post"` روی همین برگه با نانس) و
	 * `front.js` فقط تجربه را بهتر می‌کند؛ پس تنظیمات با جاوااسکریپت خاموش
	 * هم ذخیره می‌شود.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_settings( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-settings']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : false;
		$prefs   = Account::prefs();
		$profile = Account::profile();

		$prefs = wp_parse_args(
			is_array( $prefs ) ? $prefs : array(),
			array(
				'notifications' => true,
				'autoplay'      => true,
				'quality'       => '1080',
			)
		);

		$action  = (string) Account::page_url( array( 'tab' => 'settings' ) );
		$photo   = (string) $profile['photo'];
		$has_ava = '' !== $photo;

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'account-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php if ( '' !== trim( (string) $attrs['heading'] ) ) : ?>
				<div class="section-heading">
					<div>
						<h2><?php echo esc_html( (string) $attrs['heading'] ); ?></h2>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! $user ) : ?>
				<div class="settings-card">
					<h3 class="subsection-title"><?php echo esc_html( (string) $attrs['passwordTitle'] ); ?></h3>
					<p class="muted"><?php esc_html_e( 'برای تغییر تنظیمات، ابتدا وارد حساب کاربری شوید.', 'manacore' ); ?></p>
					<a class="button primary" href="<?php echo esc_url( wp_login_url( $action ) ); ?>"><?php esc_html_e( 'ورود به حساب', 'manacore' ); ?></a>
				</div>
			<?php else : ?>

				<section class="settings-card">
					<form class="settings-avatar" method="post" action="<?php echo esc_url( $action ); ?>" enctype="multipart/form-data">
						<span class="large-avatar">
							<?php if ( $has_ava ) : ?>
								<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $profile['name'] ); ?>" width="144" height="144" loading="lazy" decoding="async">
							<?php else : ?>
								<?php echo esc_html( '' !== $profile['initial'] ? $profile['initial'] : '◯' ); ?>
							<?php endif; ?>
						</span>
						<div>
							<input type="hidden" name="manacore_form" value="avatar">
							<?php wp_nonce_field( 'manacore_account_form', 'manacore_nonce' ); ?>
							<label class="button secondary small"><?php echo esc_html( (string) $attrs['avatarButton'] ); ?>
								<input id="avatar-file" name="manacore_avatar" type="file" accept="image/png,image/jpeg,image/webp" hidden>
							</label>
							<small><?php echo esc_html( (string) $attrs['avatarHint'] ); ?></small>
							<?php
							/*
							 * مرجع دکمه‌ی «بارگذاری عکس» ندارد: به‌محض انتخاب فایل، عکس
							 * ذخیره می‌شود (`initAccountAvatar()` همین کار را می‌کند). اما
							 * بی‌جاوااسکریپت باید راهی بماند؛ پس دکمه داخل `<noscript>`
							 * می‌نشیند تا برای کاربر دارای JS هیچ جعبه‌ای نسازد و هندسه‌ی
							 * کارت تنظیمات عیناً مرجع بماند (سنجیده‌شده: بلوک آواتار
							 * ۸۱px در برابر ۷۲px مرجع).
							 */
							?>
							<noscript><button type="submit" class="text-link">✓ <?php esc_html_e( 'بارگذاری عکس', 'manacore' ); ?></button></noscript>
							<?php if ( $has_ava ) : ?>
								<button type="submit" class="text-link danger" name="manacore_remove_avatar" value="1"><?php echo esc_html( (string) $attrs['removeAvatar'] ); ?></button>
							<?php endif; ?>
						</div>
					</form>

					<form method="post" action="<?php echo esc_url( $action ); ?>">
						<input type="hidden" name="manacore_form" value="profile">
						<?php wp_nonce_field( 'manacore_account_form', 'manacore_nonce' ); ?>
						<div class="settings-fields">
							<label class="form-label"><?php echo esc_html( (string) $attrs['nameLabel'] ); ?>
								<input name="display_name" minlength="2" maxlength="50" required value="<?php echo esc_attr( $profile['name'] ); ?>">
							</label>
							<label class="form-label"><?php echo esc_html( (string) $attrs['emailLabel'] ); ?>
								<input readonly dir="ltr" value="<?php echo esc_attr( (string) $user->user_email ); ?>">
								<small><?php echo esc_html( (string) $attrs['emailHint'] ); ?></small>
							</label>
						</div>

						<h3 class="subsection-title"><?php echo esc_html( (string) $attrs['prefsTitle'] ); ?></h3>

						<div class="settings-toggle-row">
							<div>
								<strong><?php echo esc_html( (string) $attrs['notifyTitle'] ); ?></strong>
								<p><?php echo esc_html( (string) $attrs['notifyText'] ); ?></p>
							</div>
							<label class="toggle-label">
								<input type="checkbox" name="notifications" value="1"<?php checked( ! empty( $prefs['notifications'] ) ); ?>>
								<span class="toggle-switch"></span>
								<span class="screen-reader-text"><?php echo esc_html( (string) $attrs['notifyTitle'] ); ?></span>
							</label>
						</div>

						<div class="settings-toggle-row">
							<div>
								<strong><?php echo esc_html( (string) $attrs['autoplayTitle'] ); ?></strong>
								<p><?php echo esc_html( (string) $attrs['autoplayText'] ); ?></p>
							</div>
							<label class="toggle-label">
								<input type="checkbox" name="autoplay" value="1"<?php checked( ! empty( $prefs['autoplay'] ) ); ?>>
								<span class="toggle-switch"></span>
								<span class="screen-reader-text"><?php echo esc_html( (string) $attrs['autoplayTitle'] ); ?></span>
							</label>
						</div>

						<div class="settings-toggle-row">
							<div>
								<strong><?php echo esc_html( (string) $attrs['qualityTitle'] ); ?></strong>
								<p><?php echo esc_html( (string) $attrs['qualityText'] ); ?></p>
							</div>
							<select name="quality" aria-label="<?php echo esc_attr( (string) $attrs['qualityTitle'] ); ?>">
								<option value="1080"<?php selected( '1080', (string) $prefs['quality'] ); ?>>1080p · Full HD</option>
								<option value="720"<?php selected( '720', (string) $prefs['quality'] ); ?>>720p · HD</option>
								<option value="360"<?php selected( '360', (string) $prefs['quality'] ); ?>>360p · کم‌حجم</option>
							</select>
						</div>

						<button type="submit" class="button primary"><?php echo esc_html( (string) $attrs['saveLabel'] ); ?></button>
					</form>
				</section>

				<section class="settings-card">
					<h3 class="subsection-title"><?php echo esc_html( (string) $attrs['passwordTitle'] ); ?></h3>
					<p class="muted"><?php echo esc_html( (string) $attrs['passwordText'] ); ?></p>
					<form method="post" action="<?php echo esc_url( $action ); ?>">
						<input type="hidden" name="manacore_form" value="password">
						<?php wp_nonce_field( 'manacore_account_form', 'manacore_nonce' ); ?>
						<div class="settings-fields">
							<label class="form-label"><?php echo esc_html( (string) $attrs['currentLabel'] ); ?>
								<input name="current_password" type="password" required minlength="8" autocomplete="current-password">
							</label>
							<label class="form-label"><?php echo esc_html( (string) $attrs['newLabel'] ); ?>
								<input name="new_password" type="password" required minlength="8" autocomplete="new-password">
							</label>
							<label class="form-label"><?php echo esc_html( (string) $attrs['confirmLabel'] ); ?>
								<input name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
							</label>
						</div>
						<button type="submit" class="button secondary"><?php echo esc_html( (string) $attrs['changeLabel'] ); ?></button>
					</form>
				</section>

				<section class="settings-card settings-export">
					<div>
						<h3><?php echo esc_html( (string) $attrs['exportTitle'] ); ?></h3>
						<p class="muted"><?php echo esc_html( (string) $attrs['exportText'] ); ?></p>
					</div>
					<a class="button secondary" href="<?php echo esc_url( $this->account_export_url() ); ?>"><?php echo esc_html( (string) $attrs['exportLabel'] ); ?></a>
				</section>

			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * نشانی دانلود خروجی داده‌ها (با نانس، بدون جاوااسکریپت).
	 *
	 * @return string
	 */
	protected function account_export_url() {
		return wp_nonce_url(
			add_query_arg( 'manacore_export', '1', (string) Account::page_url( array( 'tab' => 'settings' ) ) ),
			'manacore_export'
		);
	}

	/**
	 * سرصفحه‌ی برگه‌ی حساب (`.account-greeting`).
	 *
	 * مرجع عنوان و ریزسطر را با جاوااسکریپت از `localStorage` می‌سازد؛ این‌جا
	 * از کاربر واقعی وردپرس ساخته می‌شود. برای کاربر وارد‌شده `{name}` در
	 * عنوان با نام نمایشی جایگزین می‌شود و دکمه به تب تنظیمات می‌رود؛ برای
	 * مهمان همان متن و دکمه‌ی «ساخت حساب» مرجع می‌آید.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_greeting( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-greeting']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$profile = Account::profile();

		$heading = $profile['guest']
			? (string) $attrs['guestHeading']
			: str_replace( '{name}', (string) $profile['name'], (string) $attrs['userHeading'] );

		$eyebrow = trim( (string) $attrs['eyebrow'] );
		$text    = trim( (string) $attrs['text'] );
		$label   = $profile['guest'] ? trim( (string) $attrs['guestButtonLabel'] ) : trim( (string) $attrs['userButtonLabel'] );

		$url = trim( (string) $attrs['buttonUrl'] );
		if ( '' !== $url ) {
			$url = (string) manacore_resolve_link( $url );
		} elseif ( $profile['guest'] ) {
			/*
			 * مهمان: مقصد هوشمند. اگر ثبت‌نام سایت باز باشد «ساخت حساب»
			 * مرجع می‌آید؛ اگر بسته باشد، همان دکمه به ورود می‌رود و
			 * برچسبش هم عوض می‌شود تا کاربر به جای ثبت‌نام، منتظر ورود
			 * نماند.
			 */
			if ( get_option( 'users_can_register' ) ) {
				$url = wp_registration_url();
			} else {
				$url   = wp_login_url( (string) Account::page_url() );
				$label = trim( (string) $attrs['loginButtonLabel'] );
			}
		} else {
			$tab = sanitize_key( (string) $attrs['buttonTab'] );
			$url = (string) Account::page_url( $tab ? array( 'tab' => $tab ) : array() );
		}

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'account-greeting' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<div>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h1 id="account-greeting-title"><?php echo esc_html( $heading ); ?></h1>
				<?php if ( '' !== $text ) : ?>
					<p class="muted"><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $attrs['showButton'] ) && '' !== $label && '' !== $url ) : ?>
				<a class="button primary" id="open-register" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ستون کنار برگه‌ی حساب (`.account-sidebar`).
	 *
	 * ردیف‌های تب **پیوند واقعی**‌اند (`?tab=…`) نه دکمه‌ی بی‌عمل: با
	 * جاوااسکریپت، `front.js` همان رفتار مرجع را می‌سازد (جابه‌جایی تب
	 * بدون بازخوانی) و بی جاوااسکریپت هم هر تب بازشدنی است.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_nav( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-nav']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$profile = Account::profile();
		$items   = Account::nav_items();
		$is_user = ! $profile['guest'];

		/* کارت ارتقا: مقصد از تنظیمات اشتراک، مگر نویسنده نشانی دیگری بدهد. */
		$upgrade_url = trim( (string) $attrs['upgradeUrl'] );
		if ( '' !== $upgrade_url ) {
			$upgrade_url = (string) manacore_resolve_link( $upgrade_url );
		} elseif ( function_exists( 'manacore_subs_url' ) ) {
			$upgrade_url = (string) manacore_subs_url();
		}

		ob_start();
		?>
		<aside <?php echo Block_Support::wrapper( $attrs, 'account-sidebar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php if ( ! empty( $attrs['showProfile'] ) ) : ?>
				<div class="account-profile">
					<span class="large-avatar" id="account-avatar">
						<?php if ( $is_user && $profile['photo'] ) : ?>
							<img src="<?php echo esc_url( $profile['photo'] ); ?>" alt="<?php echo esc_attr( $profile['name'] ); ?>" width="144" height="144" loading="lazy" decoding="async">
						<?php else : ?>
							<?php echo esc_html( '' !== $profile['initial'] ? $profile['initial'] : '◯' ); ?>
						<?php endif; ?>
					</span>
					<h3 id="account-name"><?php echo esc_html( $profile['name'] ); ?></h3>
					<p dir="ltr" id="account-email"><?php echo esc_html( $profile['email_line'] ); ?></p>
					<span class="membership-pill"><span class="live-dot" aria-hidden="true"></span><?php echo esc_html( $profile['membership'] ); ?></span>
				</div>
			<?php endif; ?>

			<nav aria-label="<?php echo esc_attr( (string) $attrs['navLabel'] ); ?>">
				<?php foreach ( $items as $item ) : ?>
					<a
						href="<?php echo esc_url( $item['url'] ); ?>"
						data-tab="<?php echo esc_attr( $item['slug'] ); ?>"
						aria-controls="account-panel-<?php echo esc_attr( $item['slug'] ); ?>"
						<?php echo $item['active'] ? ' class="active" aria-current="true"' : ''; ?>
					><?php echo esc_html( $item['label'] ); ?><?php
						if ( '' !== $item['badge'] ) {
							echo '<span id="watch-count">' . esc_html( $item['badge'] ) . '</span>';
						}
						if ( '' !== $item['tag'] ) {
							echo '<small>' . esc_html( $item['tag'] ) . '</small>';
						}
					?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $is_user && ! empty( $attrs['showLogout'] ) ) : ?>
				<a class="account-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'خروج از حساب', 'manacore' ); ?></a>
			<?php endif; ?>

			<?php if ( ! $is_user && ! empty( $attrs['showUpgrade'] ) && '' !== $upgrade_url ) : ?>
				<a class="account-upgrade" href="<?php echo esc_url( $upgrade_url ); ?>">
					<strong><?php echo esc_html( (string) $attrs['upgradeHeading'] ); ?></strong>
					<span><?php echo esc_html( (string) $attrs['upgradeLabel'] ); ?> <span aria-hidden="true">‹</span></span>
				</a>
			<?php endif; ?>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * چهار شمارنده‌ی بالای برگه‌ی حساب (`.stat-grid`).
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_account_stats( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-stats']['attributes'] )
		);

		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$stats = Account::stats();
		$genre = '' !== $stats['genre'] ? $stats['genre'] : (string) $attrs['genreEmptyText'];

		$cards = array(
			'watchlist' => array(
				'icon'  => (string) $attrs['watchlistIcon'],
				'value' => manacore_fa_digits( $stats['watchlist'] ),
				'label' => (string) $attrs['watchlistLabel'],
			),
			'watched'   => array(
				'icon'  => (string) $attrs['watchedIcon'],
				'value' => manacore_fa_digits( $stats['watched'] ),
				'label' => (string) $attrs['watchedLabel'],
			),
			'minutes'   => array(
				'icon'  => (string) $attrs['minutesIcon'],
				'value' => manacore_fa_digits( $stats['minutes'] ),
				'label' => (string) $attrs['minutesLabel'],
			),
			'genre'     => array(
				'icon'  => (string) $attrs['genreIcon'],
				'value' => $genre,
				'label' => (string) $attrs['genreLabel'],
			),
		);

		ob_start();
		?>
		<div <?php echo Block_Support::wrapper( $attrs, 'stat-grid' ); // phpcs:ignore WordPress.Security.EscapeOutput -- امن. ?>>
			<?php foreach ( $cards as $key => $card ) : ?>
				<?php if ( empty( $attrs[ 'show' . ucfirst( $key ) ] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="stat-card">
					<?php if ( '' !== $card['icon'] ) : ?>
						<span aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></span>
					<?php endif; ?>
					<strong><?php echo esc_html( (string) $card['value'] ); ?></strong>
					<p><?php echo esc_html( $card['label'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * ظرف یک تب از برگه‌ی حساب (`section[data-panel]`).
	 *
	 * تب فعال سمت سرور باز است و بقیه با `hidden` بسته می‌شوند؛ پس حالت
	 * نخستین رنگ‌آمیزی همان تبِ نشانی است و تبِ پیش‌فرض «دنیای من».
	 *
	 * @param array  $attrs   ویژگی‌های بلوک.
	 * @param string $content محتوای درونی بلوک.
	 * @return string
	 */
	public function render_account_panel( $attrs, $content = '' ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/account-panel']['attributes'] )
		);

		$tabs = Account::tabs();
		$tab  = sanitize_key( (string) $attrs['tab'] );

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'overview';
		}

		$content = (string) $content;
		if ( '' === trim( $content ) ) {
			return '';
		}

		$active = ( $tab === Account::current_tab() );

		return '<section id="account-panel-' . esc_attr( $tab ) . '" data-panel="' . esc_attr( $tab ) . '"'
			. ' aria-label="' . esc_attr( $tabs[ $tab ] ) . '"'
			. ( $active ? '' : ' hidden' ) . '>'
			. $content
			. '</section>';
	}

	/**
	 * کارت اطلاعاتی ستون کنار (یادداشت یا ترویجی).
	 *
	 * مرجع دو کارت دارد: `.schedule-note-card` که یک `div` با نشانه،
	 * عنوان، متن و ردیف فراداده است، و `.sidebar-promo` که خودش پیوند
	 * است. هر دو از ویژگی‌های بلوک ساخته می‌شوند تا هیچ متنی در قالب
	 * سخت‌کد نماند.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_info_card( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/info-card']['attributes'] )
		);

		$title   = trim( (string) $attrs['title'] );
		$text    = trim( (string) $attrs['text'] );
		$variant = 'promo' === $attrs['variant'] ? 'promo' : 'note';
		/* نشانه‌هایی مثل `account:watchlist` این‌جا به نشانی واقعی تبدیل می‌شوند. */
		$url   = manacore_resolve_link( (string) $attrs['linkUrl'] );
		$label = trim( (string) $attrs['linkLabel'] );

		/*
		 * کارت خالی نباید قاب بی‌محتوا بسازد. برای گونه‌ی ترویجی، برچسب
		 * پیوند هم محتوا حساب می‌شود.
		 */
		$has_content = '' !== $title || '' !== $text || ( 'promo' === $variant && '' !== $label );

		if ( ! $has_content ) {
			return Block_Support::render_empty( $attrs, 'manacore-info-card' );
		}

		ob_start();

		if ( 'promo' === $variant ) {
			/*
			 * بدون نشانی، لنگرِ بی‌`href` ساخته نمی‌شود؛ همان قاب به‌صورت
			 * `div` رندر می‌شود تا نه فوکوس‌پذیر باشد و نه شبیه پیوند.
			 */
			$tag = $url ? 'a' : 'div';
			?>
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت است. ?> class="sidebar-promo wp-block-manacore-info-card"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ''; ?>>
				<?php if ( $title ) : ?>
					<h3><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
				<?php if ( $text ) : ?>
					<p><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
				<?php if ( $label ) : ?>
					<span><?php echo esc_html( $label ); ?> <span aria-hidden="true">‹</span></span>
				<?php endif; ?>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت است. ?>>
			<?php
			return (string) ob_get_clean();
		}

		$icon    = trim( (string) $attrs['iconText'] );
		$meta    = trim( (string) $attrs['metaText'] );
		?>
		<div class="schedule-note-card wp-block-manacore-info-card">
			<?php if ( $icon ) : ?>
				<span aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<?php endif; ?>
			<?php if ( $title ) : ?>
				<h3><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<?php if ( $text ) : ?>
				<p><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
			<?php if ( $meta ) : ?>
				<span>
					<?php if ( ! empty( $attrs['showMetaDot'] ) ) : ?>
						<span class="live-dot" aria-hidden="true"></span>
					<?php endif; ?>
					<?php echo esc_html( $meta ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * رندر بنر «سلیقه‌ات را کشف کن.».
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_taste_banner( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/taste-banner']['attributes'] )
		);

		$url = trim( (string) $attrs['linkUrl'] );

		ob_start();
		?>
		<section class="taste-banner wp-block-manacore-taste-banner">
			<?php if ( ! empty( $attrs['showArt'] ) ) : ?>
				<div class="taste-art" aria-hidden="true">
					<span></span><span></span><span></span>
					<svg viewBox="0 0 24 24" width="33" height="33" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z"/></svg>
					<i>✦</i><i>✧</i>
				</div>
			<?php endif; ?>

			<?php if ( trim( (string) $attrs['eyebrow'] ) ) : ?>
				<span class="eyebrow"><?php echo esc_html( $attrs['eyebrow'] ); ?></span>
			<?php endif; ?>

			<?php if ( trim( (string) $attrs['heading'] ) ) : ?>
				<h2><?php echo esc_html( $attrs['heading'] ); ?></h2>
			<?php endif; ?>

			<?php if ( trim( (string) $attrs['text'] ) ) : ?>
				<p><?php echo esc_html( $attrs['text'] ); ?></p>
			<?php endif; ?>

			<?php if ( $url && trim( (string) $attrs['linkLabel'] ) ) : ?>
				<a class="text-link" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $attrs['linkLabel'] ); ?>
					<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 12-7 7-7-7" transform="rotate(90 12 12)"/></svg>
				</a>
			<?php endif; ?>

			<?php if ( trim( (string) $attrs['badge'] ) ) : ?>
				<span class="taste-label"><?php echo esc_html( $attrs['badge'] ); ?></span>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}


	/**
	 * رندر فهرست قسمت‌ها.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_episodes( $attrs = array() ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/episodes-list']['attributes'] )
		);

		$post_id = $this->target_post( $attrs, manacore_serial_post_types() );
		if ( ! $post_id ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$parent = in_array( get_post_type( $post_id ), manacore_serial_post_types(), true )
			? $post_id
			: (int) get_post_meta( $post_id, 'manacore_parent_title', true );

		if ( ! $parent ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$meta_query = array(
			array(
				'key'   => 'manacore_parent_title',
				'value' => $parent,
			),
		);

		$season_filter = max( 0, (int) $attrs['seasonNumber'] );
		if ( $season_filter ) {
			$meta_query[] = array(
				'key'   => 'manacore_season_number',
				'value' => $season_filter,
			);
		}

		$order = 'DESC' === strtoupper( (string) $attrs['episodeOrder'] ) ? 'DESC' : 'ASC';
		$limit = max( 0, (int) $attrs['limit'] );

		$episodes = get_posts(
			array(
				'post_type'      => 'episode',
				'posts_per_page' => $limit ? $limit : 500,
				'post_status'    => 'publish',
				'meta_key'       => 'manacore_episode_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => array( 'meta_value_num' => $order ),
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		if ( empty( $episodes ) ) {
			return Block_Support::render_empty( $attrs, 'manacore-episodes' );
		}

		$by_season = array();
		foreach ( $episodes as $episode ) {
			$season                 = (int) get_post_meta( $episode->ID, 'manacore_season_number', true );
			$by_season[ $season ][] = $episode;
		}

		if ( 'DESC' === strtoupper( (string) $attrs['seasonOrder'] ) ) {
			krsort( $by_season );
		} else {
			ksort( $by_season );
		}

		$open_mode = sanitize_key( (string) $attrs['openSeason'] );
		$heading   = $attrs['heading'] ? (string) $attrs['heading'] : __( 'هر فصل، یک داستان تازه', 'manacore' );

		/*
		 * سبک جعبه از همان فهرست مشترک `Block_Data::download_styles()`
		 * می‌آید تا با «لینک‌های دانلود» یکی باشد. پیش‌تر این خط از کلید
		 * `box_style` می‌خواند که در تعریف بلوک وجود نداشت: هشدار
		 * «Undefined array key» در PHP 8.0+ و بی‌اثر بودن سبک جعبه.
		 */
		$box_style = Block_Support::pick( $attrs['boxStyle'], Block_Data::download_styles(), 'cards' );

		$pack_label = '' !== trim( (string) $attrs['packTitle'] )
			? (string) $attrs['packTitle']
			: __( 'بسته‌ی کامل فصل', 'manacore' );
		$size_label = '' !== trim( (string) $attrs['sizeLabel'] )
			? (string) $attrs['sizeLabel']
			: __( 'حجم نمونه', 'manacore' );
		$play_label = '' !== trim( (string) $attrs['playLabel'] )
			? (string) $attrs['playLabel']
			: __( 'پخش قسمت %s', 'manacore' );

		/*
		 * یادداشت بخش، مثل `.demo-notice` مرجع: متن ویژه‌ی اثر (اگر مدیر
		 * نوشته باشد) و در نبودش متن پیش‌فرض کتابخانه.
		 */
		$notice = ! empty( $attrs['showNotice'] ) ? (string) get_post_meta( $parent, 'manacore_custom_notice', true ) : '';
		if ( ! empty( $attrs['showNotice'] ) && '' === trim( $notice ) ) {
			$default_notice = trim( (string) manacore_get_option( 'download_notice_text', '' ) );

			if ( '' === $default_notice ) {
				$default_notice = __( 'لینک‌های این نسخه، نمونه ویدئوی آزاد ۱۰ ثانیه‌ای هستند؛ نه فایل اصلی سریال.', 'manacore' );
			}

			$notice = (string) apply_filters( 'manacore_download_notice', $default_notice, $parent );
		}

		/*
		 * «بسته‌های فصل»: لینک‌هایی که مدیر روی پست خودِ سریال ثبت کرده
		 * است (مثلاً «دانلود کامل فصل ۱ با کیفیت ۱۰۸۰»). پیش‌تر این داده
		 * هیچ‌جا رندر نمی‌شد، چون فقط لینک‌های قسمت‌ها نمایش داده می‌شدند.
		 */
		$packs = ! empty( $attrs['showPackList'] ) ? Links::by_season( $parent ) : array();

		$season_index = 0;
		$is_multi     = count( $by_season ) > 1;

		ob_start();
		?>
		<section class="download-section manacore-episodes<?php echo 'cards' === $box_style ? '' : ' is-' . esc_attr( $box_style ); ?>"
			id="episodes" data-manacore-episodes="<?php echo esc_attr( $parent ); ?>">
			<div class="section-heading">
				<div class="heading-title">
					<?php if ( ! empty( $attrs['showIcon'] ) ) : ?>
						<span class="section-icon" aria-hidden="true">
							<span aria-hidden="true">▤</span>
						</span>
					<?php endif; ?>
					<h2><?php echo esc_html( $heading ); ?></h2>
				</div>
				<?php if ( $is_multi ) : ?>
					<label class="manacore-season-select">
						<span class="screen-reader-text"><?php esc_html_e( 'انتخاب فصل', 'manacore' ); ?></span>
						<select data-season-select aria-label="<?php esc_attr_e( 'انتخاب فصل', 'manacore' ); ?>">
							<?php foreach ( array_keys( $by_season ) as $season ) : ?>
								<option value="<?php echo esc_attr( $season ); ?>">
									<?php
									echo 0 < $season
										/* translators: %s: شماره فصل */
										? esc_html( sprintf( __( 'فصل %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $season ) ) ) )
										: esc_html__( 'عمومی', 'manacore' );
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
			</div>

			<?php if ( $is_multi ) : ?>
				<div class="season-tabs">
					<?php $first = true; ?>
					<?php foreach ( $by_season as $season => $items ) : ?>
						<button type="button" class="<?php echo $first || 'all' === $open_mode ? 'active' : ''; ?>"
							data-season="<?php echo esc_attr( $season ); ?>">
							<?php
							echo 0 < $season
								/* translators: %s: شماره فصل */
								? esc_html( sprintf( __( 'فصل %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $season ) ) ) )
								: esc_html__( 'عمومی', 'manacore' );
							?>
							<small><?php echo esc_html( sprintf( /* translators: %s: تعداد قسمت */ __( '%s قسمت', 'manacore' ), manacore_fa_digits( number_format_i18n( count( $items ) ) ) ) ); ?></small>
						</button>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== trim( $notice ) ) : ?>
				<div class="demo-notice">
					<span aria-hidden="true">ⓘ</span>
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			$season_index = 0;
			foreach ( $by_season as $season => $items ) :
				$is_open = 'all' === $open_mode || ( 'first' === $open_mode && 0 === $season_index ) || ( ! $is_multi && 0 === $season_index );
				++$season_index;
				?>
				<div class="episode-list" id="episode-list" data-season-panel="<?php echo esc_attr( $season ); ?>" <?php echo $is_open ? '' : 'hidden'; ?>>
					<?php
					/*
					 * ۱) بسته‌های کامل فصل (لینک‌های خودِ سریال برای همین فصل).
					 *    این جدول هم‌مارک‌آپ جدول قسمت است تا استایل و
					 *    آزمون‌های هندسی موجود دست‌نخورده بمانند.
					 */
					$season_packs = isset( $packs[ $season ] ) ? (array) $packs[ $season ] : (array) ( $packs[0] ?? array() );

					if ( $season_packs ) :
						?>
						<div class="download-table download-packs" data-season-packs="<?php echo esc_attr( $season ); ?>">
							<div class="download-table-header">
								<span><?php esc_html_e( 'کیفیت تصویر', 'manacore' ); ?></span>
								<span><?php esc_html_e( 'فرمت', 'manacore' ); ?></span>
								<span><?php echo esc_html( $size_label ); ?></span>
								<span><?php esc_html_e( 'پخش و دانلود', 'manacore' ); ?></span>
							</div>
							<?php
							$pack_access = manacore_user_can_access( $parent );
							$pack_play   = class_exists( '\\ManaCore\\Core\\Player' )
								? Player::url_for( $parent, 0 < $season ? array( 'season' => $season ) : array() )
								: '';

							foreach ( $season_packs as $pack_group ) {
								echo Templates::link_row( $pack_group, $pack_access, $parent, $pack_play, $pack_label ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</div>
						<?php
					endif;

					$episode_index = 0;
					foreach ( $items as $episode ) :
						$number    = (int) get_post_meta( $episode->ID, 'manacore_episode_number', true );
						$air_date  = (string) get_post_meta( $episode->ID, 'manacore_air_date', true );
						$expanded  = $is_open && 0 === $episode_index;
						$sub_label = $air_date ? $air_date : __( 'نسخه نمایشی', 'manacore' );

						/*
						 * هدف پخش با `Player::resolve_target()` تعیین می‌شود:
						 * اگر خودِ قسمت لینک داشته باشد، همان قسمت پخش
						 * می‌شود (نه سریال)؛ وگرنه والد یا نخستین قسمتِ
						 * دارای لینک. در نبود برگه‌ی پخش، `url_for()`
						 * رشته‌ی خالی می‌دهد و دکمه ساخته نمی‌شود تا
						 * پیوندِ ناقص (فقط `?season=…`) به کاربر نرسد.
						 */
						$play_url = '';

						if ( class_exists( '\ManaCore\Core\Player' ) ) {
							$play_target = Player::resolve_target( $parent, $season, $number );
							$play_args   = array(
								'season'  => $season,
								'episode' => $number,
							);

							if ( $play_target && Player::has_sources( $play_target ) ) {
								$play_url = Player::url_for( $play_target, $play_args );
							} elseif ( Player::has_sources( $parent ) ) {
								$play_url = Player::url_for( $parent, $play_args );
							}
						}

						++$episode_index;
						?>
						<article class="episode-card<?php echo $expanded ? ' expanded' : ''; ?>">
							<div class="episode-heading">
								<button type="button" class="episode-toggle" data-episode="<?php echo esc_attr( $number ); ?>"
									aria-controls="episode-download-<?php echo esc_attr( $episode->ID ); ?>"
									aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
									<?php /* مرجع شماره‌ی کارت را دو رقمی و با ارقام فارسی می‌نویسد: «۰۱». */ ?>
									<span class="episode-number"><?php echo esc_html( manacore_fa_digits( str_pad( (string) number_format_i18n( $number ), 2, '0', STR_PAD_LEFT ) ) ); ?></span>
									<span>
										<strong>
											<?php
											/* translators: %s: شماره قسمت */
											echo esc_html( sprintf( __( 'قسمت %s', 'manacore' ), manacore_fa_digits( number_format_i18n( $number ) ) ) );
											?>
										</strong>
										<small>
											<?php
											echo 0 < $season
												/* translators: 1: شماره فصل، 2: برچسب قسمت */
												? esc_html( sprintf( __( 'فصل %1$s · %2$s', 'manacore' ), manacore_fa_digits( number_format_i18n( $season ) ), manacore_fa_digits( $sub_label ) ) )
												: esc_html( manacore_fa_digits( $sub_label ) );
											?>
										</small>
									</span>
									<?php if ( ! empty( $attrs['showEpisodeName'] ) && '' !== trim( (string) $episode->post_title ) ) : ?>
										<span class="episode-subtitle"><?php echo esc_html( get_the_title( $episode ) ); ?></span>
									<?php endif; ?>
									<span class="episode-chevron" aria-hidden="true">
										<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="m6 9 6 6 6-6"/>
										</svg>
									</span>
								</button>
								<?php if ( $play_url ) : ?>
									<a class="episode-play" href="<?php echo esc_url( $play_url ); ?>"
										title="<?php echo esc_attr( sprintf( $play_label, manacore_fa_digits( number_format_i18n( $number ) ) ) ); ?>"
										aria-label="<?php echo esc_attr( sprintf( $play_label, manacore_fa_digits( number_format_i18n( $number ) ) ) ); ?>">
										<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="m8 5 11 7-11 7V5Z"/></svg>
									</a>
								<?php endif; ?>
							</div>
							<div class="download-table episode-download" id="episode-download-<?php echo esc_attr( $episode->ID ); ?>" <?php echo $expanded ? '' : 'hidden'; ?>>
								<div class="download-table-header">
									<span><?php esc_html_e( 'کیفیت تصویر', 'manacore' ); ?></span>
									<span><?php esc_html_e( 'فرمت', 'manacore' ); ?></span>
									<span><?php esc_html_e( 'حجم نمونه', 'manacore' ); ?></span>
									<span><?php esc_html_e( 'پخش و دانلود', 'manacore' ); ?></span>
								</div>
								<?php
								$has_access = manacore_user_can_access( $episode->ID );
								$groups     = Links::get( $episode->ID );

								if ( ! $groups ) :
									?>
									<p class="muted"><?php esc_html_e( 'برای این قسمت هنوز فایلی ثبت نشده است.', 'manacore' ); ?></p>
									<?php
								else :
									foreach ( $groups as $group ) :
										$quality  = trim( (string) $group['quality'] );
										$play_row = '';
										$quality  = '' !== $quality ? $quality : trim( (string) $group['title'] );

										if ( $play_url && '' !== $quality ) {
											$play_row = add_query_arg( 'quality', rawurlencode( $quality ), $play_url );
										} elseif ( $play_url ) {
											$play_row = $play_url;
										}

										echo Templates::link_row( $group, $has_access, $episode->ID, $play_row ); // phpcs:ignore WordPress.Security.EscapeOutput
									endforeach;
								endif;
								?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * رندر «سرصفحه‌ی برگه» (`.page-title-row` / `.info-intro` / `.magazine-intro`).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_page_intro( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/page-intro']['attributes'] )
		);

		$title  = trim( (string) $attrs['title'] );
		/* جانگهدار نام: روی آرشیوها و برگه‌های پویا («{name}») پر می‌شود. */
		if ( false !== strpos( $title, '{name}' ) ) {
			$title = str_replace( '{name}', Block_Support::current_object_title(), $title );
		}
		$text   = trim( (string) $attrs['text'] );
		$accent = trim( (string) $attrs['accent'] );
		$eyebrow = trim( (string) $attrs['eyebrow'] );

		if ( '' === $title && '' === $text && '' === $eyebrow ) {
			return Block_Support::render_empty( $attrs, 'manacore-page-intro' );
		}

		$layout = (string) $attrs['layout'];
		if ( ! in_array( $layout, array( 'split', 'centered', 'magazine' ), true ) ) {
			$layout = 'split';
		}

		$tag = strtolower( (string) $attrs['headingTag'] );
		if ( ! in_array( $tag, array( 'h1', 'h2' ), true ) ) {
			$tag = 'h1';
		}

		/*
		 * واژه‌ی تأکیدی داخل خود عنوان می‌نشیند (`.accent` در split و
		 * `<span>` در magazine) — عیناً مثل مرجع.
		 */
		$heading = esc_html( $title );
		if ( '' !== $accent ) {
			$heading .= ' <span class="accent">' . esc_html( $accent ) . '</span>';
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-page-intro', 'page-intro', 'page-intro-' . $layout ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ! empty( $attrs['showBreadcrumb'] ) ) {
			$home  = trim( (string) $attrs['breadcrumbHome'] );
			$home  = '' !== $home ? $home : get_bloginfo( 'name' );
			$label = trim( (string) $attrs['breadcrumbLabel'] );
			$label = '' !== $label ? $label : $title;
			?>
			<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'manacore' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $home ); ?></a>
				<svg class="icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
				<span><?php echo esc_html( $label ); ?></span>
			</nav>
			<?php
		}

		$icon_markup = '';
		$icon_key    = sanitize_key( (string) $attrs['icon'] );
		if ( $icon_key ) {
			$svg = Block_Support::heading_icon( $icon_key );
			if ( $svg ) {
				$icon_markup = ! empty( $attrs['iconBox'] )
					? '<span class="pricing-crown koohe-pricing-crown" aria-hidden="true">' . $svg . '</span>'
					: '<span class="page-title-icon" aria-hidden="true">' . $svg . '</span>';
			}
		}

		$clock_markup = '';
		if ( ! empty( $attrs['showClock'] ) ) {
			$clock_markup = sprintf(
				'<span class="live-clock" data-manacore-clock data-timezone="%1$s"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><b id="live-clock">--:--</b><small>%2$s</small></span>',
				esc_attr( (string) $attrs['clockTimezone'] ),
				esc_html( (string) $attrs['clockLabel'] )
			);
		}

		if ( 'centered' === $layout ) {
			?>
			<section class="info-intro">
				<?php echo $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput -- از سازنده‌ی امن ساخته شده است. ?>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت مجاز است. ?>><?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput -- عنوان و واژه‌ی تأکیدی هر دو esc شده‌اند. ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( '' !== $text ) : ?>
					<p><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</section>
			<?php
		} elseif ( 'magazine' === $layout ) {
			?>
			<div class="magazine-intro">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( '' !== $text ) : ?>
					<p class="muted"><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		} else {
			?>
			<div class="page-title-row">
				<div class="page-title-text">
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo 'h1' === $tag ? '' : ' class="is-h2"'; ?>><?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
					<?php if ( '' !== $text ) : ?>
						<p class="muted"><?php echo esc_html( $text ); ?></p>
					<?php endif; ?>
				</div>
				<?php echo $clock_markup . $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput -- از سازنده‌ی امن ساخته شده‌اند. ?>
			</div>
			<?php
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * دکمه‌ی پیوند — همان `a.button` مرجع.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_cta_link( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/cta-link']['attributes'] )
		);

		$label = trim( (string) $attrs['label'] );
		$url   = trim( (string) $attrs['url'] );

		if ( '' === $label || '' === $url ) {
			return '';
		}

		$variant = sanitize_html_class( (string) $attrs['variant'] );
		$variant = in_array( $variant, array( 'primary', 'secondary', 'glass', 'plain' ), true ) ? $variant : 'primary';

		$classes = 'button';
		if ( 'plain' !== $variant ) {
			$classes .= ' ' . $variant;
		}
		if ( ! empty( $attrs['small'] ) ) {
			$classes .= ' small';
		}

		$chevron = ! empty( $attrs['showChevron'] )
			? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>'
			: '';

		$target = trim( (string) $attrs['target'] );
		$rel    = trim( (string) $attrs['rel'] );

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-cta' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی تابع وردپرس.
		printf(
			'<a class="%1$s" href="%2$s"%3$s%4$s>%5$s%6$s</a>',
			esc_attr( $classes ),
			esc_url( manacore_resolve_link( $url ) ),
			'' !== $target ? ' target="' . esc_attr( $target ) . '"' : '',
			'' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
			esc_html( $label ),
			$chevron // phpcs:ignore WordPress.Security.EscapeOutput -- SVG ثابت داخلی.
		);
		echo '</div>';

		return ( string ) ob_get_clean();
	}

	/**
	 * شناسنامه‌ی چهره — هم‌ارز `.person-english` و `.person-facts` مرجع.
	 *
	 * داده از فراداده‌ی خودِ چهره می‌آید و شمار آثار از همان محاسبه‌ی
	 * کش‌شده‌ی {@see Query::person_work_count()} — یعنی کارت‌های شبکه‌ی
	 * چهره‌ها، برگه‌ی چهره و شبکه‌ی فیلموگرافی همه یک عدد را نشان می‌دهند.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_person_meta( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/person-meta']['attributes'] )
		);

		$post_id = (int) $attrs['postId'];
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}

		if ( ! $post_id || 'person' !== get_post_type( $post_id ) ) {
			return '';
		}

		/*
		 * گونه‌ی «نشان نقش»: فقط نشان گوشه‌ی تصویر. مرجع این نشان را
		 * داخل `.person-portrait` می‌گذارد و بیرون از آن معنا ندارد.
		 */
		if ( 'role' === $attrs['variant'] ) {
			$terms = wp_get_post_terms( $post_id, 'person_role', array( 'fields' => 'names' ) );

			if ( is_wp_error( $terms ) || ! $terms ) {
				return Block_Support::render_empty( $attrs, 'manacore-person-meta' );
			}

			return sprintf(
				'<div %1$s><span class="person-role">%2$s</span></div>',
				Block_Support::wrapper( $attrs, 'manacore-person-meta' ), // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی تابع وردپرس.
				esc_html( implode( ' · ', $terms ) )
			);
		}

		$english = trim( (string) get_post_meta( $post_id, 'manacore_person_english', true ) );
		$born    = trim( (string) get_post_meta( $post_id, 'manacore_person_born', true ) );
		$country = trim( (string) get_post_meta( $post_id, 'manacore_country', true ) );

		$facts = array();

		if ( ! empty( $attrs['showBorn'] ) && '' !== $born ) {
			$facts[] = sprintf(
				'<span><span class="person-fact-icon" aria-hidden="true">◉</span>%s</span>',
				esc_html( $born )
			);
		}

		if ( ! empty( $attrs['showCountry'] ) && '' !== $country ) {
			$facts[] = sprintf(
				'<span><span class="person-fact-icon" aria-hidden="true">◌</span>%s</span>',
				esc_html( $country )
			);
		}

		if ( ! empty( $attrs['showWorks'] ) ) {
			$facts[] = sprintf(
				'<span><span class="person-fact-icon" aria-hidden="true">▣</span>%s</span>',
				esc_html(
					str_replace(
						'{count}',
						manacore_fa_digits( Query::person_work_count( $post_id ) ),
						(string) $attrs['worksLabel']
					)
				)
			);
		}

		$english_html = ( ! empty( $attrs['showEnglish'] ) && '' !== $english )
			? '<p class="person-english" dir="ltr">' . esc_html( $english ) . '</p>'
			: '';

		if ( '' === $english_html && ! $facts ) {
			return Block_Support::render_empty( $attrs, 'manacore-person-meta' );
		}

		ob_start();
		echo '<div ' . Block_Support::wrapper( $attrs, 'manacore-person-meta' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی تابع وردپرس.
		echo $english_html; // phpcs:ignore WordPress.Security.EscapeOutput -- در همین تابع escape شده.
		if ( $facts ) {
			echo '<div class="person-facts">' . implode( '', $facts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- در همین تابع escape شده.
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * مسیر صفحه — هم‌ارز `div.breadcrumb` مرجع.
	 *
	 * برخلاف `page-intro` (که مسیر را داخل سرصفحه‌ی چیدمان‌دار می‌سازد)،
	 * این بلوک مسیر را جدا و مستقل رندر می‌کند؛ برگه‌ی چهره به آن نیاز
	 * دارد چون سرصفحه‌اش `.person-hero` است، نه `.page-title-row`.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_breadcrumb( $attrs ) {
		$attrs = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			array(
				'homeLabel'    => '',
				'parentLabel'  => '',
				'parentUrl'    => '',
				'currentLabel' => '',
				'currentTaxonomy' => '',
				'separator'    => 'chevron',
				'extraClass'   => '',
				'anchor'       => '',
			)
		);

		$home    = trim( (string) $attrs['homeLabel'] );
		$home    = '' !== $home ? $home : get_bloginfo( 'name' );
		$parent  = trim( (string) $attrs['parentLabel'] );
		$parent_url = trim( (string) $attrs['parentUrl'] );

		/*
		 * همان درسِ پیوند «همه چهره‌ها»: مقدار بی‌اسکیم مثل `magazine` را
		 * `esc_url()` با پروتکل پیش‌فرض چاپ می‌کند (`http://magazine`).
		 * اگر نامکِ یک برگه‌ی موجود باشد، پیوند واقعی جایش می‌نشیند؛
		 * وگرنه `manacore_resolve_link()` نشانی‌های نشانه‌دار
		 * (`post:12`، `account:watchlist`) را حل می‌کند.
		 */
		if ( '' !== $parent_url ) {
			if ( preg_match( '~^([a-z][a-z0-9+.\-]*:|//|/|#|\?)~i', $parent_url ) ) {
				$parent_url = manacore_resolve_link( $parent_url );
			} else {
				$page       = get_page_by_path( $parent_url );
				$parent_url = $page instanceof \WP_Post
					? (string) get_permalink( $page )
					: manacore_resolve_link( $parent_url );
			}
		}
		$current = trim( (string) $attrs['currentLabel'] );
		$current = '' !== $current
			? str_replace( '{name}', Block_Support::current_object_title(), $current )
			: Block_Support::current_object_title();

		/*
		 * برگه‌ی مقاله‌ی مرجع، آخرین خانه‌ی مسیر را نام **دسته** می‌گذارد
		 * (نه تیتر نوشته). اگر مالک سایت تاکسونومی بدهد، نخستین ترم همان
		 * نوشته‌ی جاری جایش می‌نشیند؛ تاکسونومی نبود، رفتار پیشین می‌ماند.
		 */
		$taxonomy = sanitize_key( (string) $attrs['currentTaxonomy'] );

		if ( '' !== $taxonomy ) {
			$post_id = (int) get_the_ID();
			$terms   = $post_id ? wp_get_post_terms( $post_id, $taxonomy ) : array();

			if ( ! is_wp_error( $terms ) && $terms ) {
				$current = (string) $terms[0]->name;
			}
		}

		$sep = 'text' === $attrs['separator']
			? '<span aria-hidden="true">‹</span>'
			: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';

		$classes = trim( 'breadcrumb ' . (string) $attrs['extraClass'] );
		$id      = '' !== trim( (string) $attrs['anchor'] ) ? ' id="' . esc_attr( (string) $attrs['anchor'] ) . '"' : '';

		ob_start();
		printf(
			'<nav class="%1$s"%2$s aria-label="%3$s"><a href="%4$s">%5$s</a>%6$s',
			esc_attr( $classes ),
			$id, // phpcs:ignore WordPress.Security.EscapeOutput -- در همین تابع escape شده.
			esc_attr__( 'مسیر صفحه', 'manacore' ),
			esc_url( home_url( '/' ) ),
			esc_html( $home ),
			$sep // phpcs:ignore WordPress.Security.EscapeOutput -- نشانه‌ی ثابت داخلی.
		);

		if ( '' !== $parent ) {
			if ( '' !== $parent_url ) {
				printf( '<a href="%1$s">%2$s</a>%3$s', esc_url( $parent_url ), esc_html( $parent ), $sep ); // phpcs:ignore WordPress.Security.EscapeOutput -- نشانه‌ی ثابت.
			} else {
				printf( '<a href="%1$s">%2$s</a>%3$s', esc_url( home_url( '/' ) ), esc_html( $parent ), $sep ); // phpcs:ignore WordPress.Security.EscapeOutput -- نشانه‌ی ثابت.
			}
		}

		echo '<span>' . esc_html( $current ) . '</span></nav>';

		return (string) ob_get_clean();
	}

	/**
	 * شبکه‌ی چهره‌ها — هم‌ارز `.people-grid` برگه‌ی «بازیگران و عوامل».
	 *
	 * دو حالت دارد:
	 *   - `cards`  : کارت بلند ۳:۴ با نشان نقش، نام لاتین و شمار آثار
	 *   - `related`: کارت کوچک بخش «چهره‌های دیگر را کشف کن» در برگه‌ی چهره
	 *
	 * منبع، نوع محتوای واقعی `person` است؛ نقش از تاکسونومی `person_role`
	 * و شمار آثار از {@see Query::person_work_count()} (همان محاسبه‌ی
	 * کش‌شده‌ی «آثار این عامل») می‌آید — نه فهرست دستی، نه داده‌ی نمایشی.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return string
	 */
	public function render_people_grid( $attrs ) {
		if ( ! Block_Support::should_render( $attrs ) ) {
			return '';
		}

		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/people-grid']['attributes'] )
		);

		/*
		 * پیوند پیش‌فرض «همه چهره‌ها» به مرکز چهره‌ها می‌رود، نه به برگه‌ی
		 * کشف؛ اگر مالک سایت نشانی دیگری بدهد همان می‌ماند. مرجع هم روی
		 * برگه‌ی چهره به `cast.html` پیوند می‌دهد.
		 */
		if ( '' === trim( (string) $attrs['moreUrl'] ) && function_exists( 'manacore_cast_url' ) ) {
			$attrs['moreUrl'] = manacore_cast_url();
		}

		$variant = 'related' === $attrs['variant'] ? 'related' : 'cards';
		$limit   = max( 1, min( 60, (int) $attrs['count'] ) );
		$columns = max( 1, min( 6, (int) $attrs['columns'] ) );

		$orderby = (string) $attrs['orderby'];
		if ( ! in_array( $orderby, array( 'menu_order', 'title', 'date', 'random' ), true ) ) {
			$orderby = 'menu_order';
		}

		$args = array(
			'post_type'      => 'person',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			/*
			 * شکست‌شکن `ID` عمدی است: بدون آن ترتیب رکوردهای هم‌تاریخ قطعی
			 * نیست و کارت‌ها بین دو بازخوانی جابه‌جا می‌شوند.
			 */
			'orderby'        => 'random' === $orderby ? 'rand' : array( $orderby => 'ASC', 'ID' => 'ASC' ),
		);

		$roles = $this->people_role_slugs( $attrs );
		if ( $roles ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'person_role',
					'field'    => 'slug',
					'terms'    => $roles,
				),
			);
		}

		if ( ! empty( $attrs['excludeCurrent'] ) ) {
			$current = (int) get_the_ID();
			if ( $current ) {
				$args['post__not_in'] = array( $current );
			}
		}

		$people = get_posts( $args );

		if ( ! $people ) {
			return Block_Support::render_empty( $attrs, 'manacore-people-grid' );
		}

		if ( '' === trim( (string) $attrs['moreLabel'] ) ) {
			$attrs['moreLabel'] = __( 'همه چهره‌ها', 'manacore' );
		}

		$empty_heading = trim( (string) $attrs['emptyHeading'] );

		ob_start();

		echo '<div ' . Block_Support::wrapper( $attrs, array( 'manacore-people-grid', 'people-grid-block', 'is-' . $variant ) ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput

		/* سرصفحه با همان سازنده‌ی بقیه‌ی بلوک‌ها: `.manacore-block-head`. */
		echo Block_Support::render_header( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی امن همین کلاس.

		if ( 'cards' === $variant && '' !== $empty_heading ) {
			/*
			 * ناحیه‌ی زنده‌ی شمار نتایج (فقط برای صفحه‌خوان). صافی درجای
			 * `initPeopleFilter()` متنش را با الگوی `countLabel` پر می‌کند.
			 */
			printf(
				'<p class="screen-reader-text" data-manacore-people-count data-count-template="%s" aria-live="polite"></p>',
				esc_attr( (string) $attrs['countLabel'] )
			);
		}

		if ( 'related' === $variant ) {
			echo '<div class="cast-grid">';
		} else {
			$style = 4 === $columns ? '' : sprintf( ' style="--manacore-people-columns:%d"', $columns );
			echo '<div class="people-grid" data-manacore-people-grid' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		foreach ( $people as $person ) {
			$id      = (int) $person->ID;
			$name    = get_the_title( $person );
			$english = trim( (string) get_post_meta( $id, 'manacore_person_english', true ) );
			$termlist = wp_get_post_terms( $id, 'person_role', array( 'fields' => 'all' ) );
			$role    = ( is_array( $termlist ) && $termlist ) ? $termlist[0]->name : '';
			$slugs   = ( is_array( $termlist ) && $termlist ) ? wp_list_pluck( $termlist, 'slug' ) : array();
			/*
			 * تصویر شاخص اولویت دارد؛ اگر نبود، نشانی تصویر جایگزین
			 * (`manacore_person_photo`) — همان الگویی که کانال‌های پخش زنده
			 * با `manacore_channel_poster` دارند.
			 */
			$photo   = (string) get_the_post_thumbnail_url( $id, 'large' );
			$photo   = '' !== $photo ? $photo : trim( (string) get_post_meta( $id, 'manacore_person_photo', true ) );
			$count   = Query::person_work_count( $id );

			if ( 'related' === $variant ) {
				?>
				<a class="cast-card" href="<?php echo esc_url( get_permalink( $person ) ); ?>">
					<?php if ( $photo ) : ?>
						<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
					<?php endif; ?>
					<h3><?php echo esc_html( $name ); ?></h3>
					<p><?php echo esc_html( $role ); ?></p>
				</a>
				<?php
				continue;
			}

			$works = str_replace(
				'{count}',
				manacore_fa_digits( $count ),
				(string) $attrs['worksLabel']
			);
			?>
			<a class="person-card" href="<?php echo esc_url( get_permalink( $person ) ); ?>"
				data-name="<?php echo esc_attr( $name ); ?>"
				data-english="<?php echo esc_attr( $english ); ?>"
				data-search="<?php echo esc_attr( $name . ' ' . $english ); ?>"
				data-role="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $slugs ) ) ); ?>">
				<div>
					<?php if ( $photo ) : ?>
						<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
					<?php endif; ?>
					<?php if ( '' !== $role ) : ?>
						<span><?php echo esc_html( $role ); ?></span>
					<?php endif; ?>
					<i aria-hidden="true">↖</i>
				</div>
				<h2><?php echo esc_html( $name ); ?></h2>
				<?php if ( '' !== $english ) : ?>
					<p dir="ltr"><?php echo esc_html( $english ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $attrs['showWorks'] ) ) : ?>
					<small><?php echo esc_html( $works ); ?></small>
				<?php endif; ?>
			</a>
			<?php
		}

		/*
		 * حالت خالی (`.empty-state` مرجع) **داخل** شبکه می‌نشیند تا صافی
		 * درجای `initPeopleFilter()` بتواند با کلیدوم پنهان/آشکارش کند.
		 * بدون جاوااسکریپت پنهان می‌ماند، چون همه‌ی کارت‌ها از سرور آمده‌اند.
		 */
		if ( 'cards' === $variant && '' !== $empty_heading ) {
			printf(
				'<div class="empty-state" hidden><h3>%1$s</h3>%2$s</div>',
				esc_html( $empty_heading ),
				'' !== trim( (string) $attrs['emptyText'] ) ? '<p>' . esc_html( (string) $attrs['emptyText'] ) . '</p>' : ''
			);
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * نامک‌های نقش‌های خواسته‌شده در ویژگی‌های بلوک.
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return array<int,string>
	 */
	protected function people_role_slugs( $attrs ) {
		$raw = isset( $attrs['roles'] ) ? $attrs['roles'] : array();

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[,\s]+/', $raw );
		}

		$slugs = array();
		foreach ( (array) $raw as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * قاب‌های امروز: آثار زمان‌بندی‌شده‌ی یک روز، مرتب بر حسب ساعت پخش.
	 *
	 * منبع، فراداده‌ی واقعی پروژه است (`manacore_air_day` + `manacore_air_time`)
	 * — همان داده‌ای که برگه‌ی «برنامه پخش» از آن ساخته می‌شود؛ پس «قاب‌های
	 * امروز» هرگز داده‌ی نمایشی نیست.
	 *
	 * @param array $attrs ویژگی‌های بلوک.
	 * @return array{day:string,fallback:bool,items:array<int,array>}
	 */
	protected function live_program_items( $attrs ) {
		$types = array();
		foreach ( (array) $attrs['programTypes'] as $type ) {
			$type = sanitize_key( (string) $type );
			if ( $type && in_array( $type, manacore_title_post_types(), true ) ) {
				$types[] = $type;
			}
		}
		if ( ! $types ) {
			$types = manacore_title_post_types();
		}

		$limit = max( 1, min( 12, (int) $attrs['programCount'] ) );
		$days  = self::week_days();
		$today = strtolower( self::day_key() );

		$posts = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 120,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'manacore_air_day',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'manacore_air_day',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$by_day = array();
		foreach ( $posts as $post ) {
			$day = strtolower( (string) get_post_meta( $post->ID, 'manacore_air_day', true ) );
			if ( ! $day || ! isset( $days[ $day ] ) ) {
				continue;
			}
			$by_day[ $day ][] = $post;
		}

		$day      = isset( $days[ $today ] ) ? $today : 'saturday';
		$fallback = false;

		if ( empty( $by_day[ $day ] ) && ! empty( $attrs['programFallback'] ) && $by_day ) {
			$keys     = array_keys( $by_day );
			$day      = $keys[0];
			$fallback = true;
		}

		$list = isset( $by_day[ $day ] ) ? $by_day[ $day ] : array();

		/*
		 * مرتب‌سازی بر حسب ساعت پخش: مقدار متا متن آزاد است («۲۱:۳۰»،
		 * «21:30»)، پس ارقام فارسی به لاتین برمی‌گردند و سپس مقایسه‌ی
		 * رشته‌ای انجام می‌شود (همیشه دو رقمی برای ساعت و دقیقه).
		 */
		usort(
			$list,
			static function ( $a, $b ) {
				$ta = self::normalize_time( (string) get_post_meta( $a->ID, 'manacore_air_time', true ) );
				$tb = self::normalize_time( (string) get_post_meta( $b->ID, 'manacore_air_time', true ) );
				return strcmp( $ta, $tb );
			}
		);

		$now = self::normalize_time( current_time( 'H:i' ) );

		$items         = array();
		$active_index  = -1;
		foreach ( $list as $index => $post ) {
			$time = self::normalize_time( (string) get_post_meta( $post->ID, 'manacore_air_time', true ) );
			if ( '' === $time ) {
				continue;
			}
			if ( $time <= $now ) {
				$active_index = count( $items );
			}
			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'time'      => (string) get_post_meta( $post->ID, 'manacore_air_time', true ),
				'permalink' => get_permalink( $post ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		/* اگر ساعت فعلی از همه‌ی قاب‌ها گذشته باشد، آخرین قاب «در حال پخش» است. */
		if ( $active_index < 0 && $items ) {
			$active_index = count( $items ) - 1;
		}

		return array(
			'day'      => $day,
			'fallback' => $fallback,
			'items'    => $items,
			'active'   => $active_index,
		);
	}

	/**
	 * تبدیل ساعت به شکل قابل مقایسه (`09:05`).
	 *
	 * @param string $value مقدار خام.
	 * @return string
	 */
	protected static function normalize_time( $value ) {
		$value = strtr(
			trim( $value ),
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
			)
		);

		if ( ! preg_match( '/^(\d{1,2})[:.]?(\d{2})?/', $value, $m ) ) {
			return '';
		}

		return sprintf( '%02d:%02d', (int) $m[1], isset( $m[2] ) ? (int) $m[2] : 0 );
	}

	/**
	 * رندر «پخش زنده» (`.live-player` مرجع).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_live_player( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/live-player']['attributes'] )
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['channel'] ) ? sanitize_title( wp_unslash( $_GET['channel'] ) ) : '';
		// phpcs:enable

		$channel_id = (int) $attrs['channelId'];
		$channel    = $channel_id ? Channel::payload( $channel_id ) : array();
		if ( ! $channel ) {
			$post = Channel::resolve( $slug );
			$channel = $post ? Channel::payload( $post ) : array();
		}

		if ( ! $channel ) {
			return Block_Support::render_empty(
				$attrs,
				'manacore-live-player'
			);
		}

		$title    = trim( (string) $attrs['titleOverride'] );
		$title    = '' !== $title ? $title : $channel['title'];
		$subtitle = trim( (string) $attrs['subtitleOverride'] );
		$subtitle = '' !== $subtitle ? $subtitle : $channel['subtitle'];

		$program = ! empty( $attrs['showProgram'] )
			? $this->live_program_items( $attrs )
			: array(
				'items'  => array(),
				'active' => -1,
			);

		$heading = trim( (string) $attrs['heading'] );
		if ( '' === $heading ) {
			$heading = __( 'قاب‌های امروز', 'manacore' );
		}

		ob_start();
		?>
		<section <?php echo Block_Support::wrapper( $attrs, array( 'manacore-live-player', 'live-player' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			data-manacore-live="<?php echo esc_attr( (string) wp_json_encode( array( 'channel' => $channel['slug'] ) ) ); ?>">
			<div class="live-video-frame">
				<video id="live-video" poster="<?php echo esc_url( $channel['poster'] ); ?>"<?php echo $channel['video'] ? ' src="' . esc_url( $channel['video'] ) . '"' : ''; ?> muted loop playsinline controls<?php echo ! empty( $attrs['autoplay'] ) ? ' autoplay' : ''; ?>></video>
				<span class="on-air"><span aria-hidden="true"></span><?php echo esc_html( (string) $attrs['onAirLabel'] ); ?></span>
				<div class="video-error hidden" id="video-error">
					<p><?php echo esc_html( (string) $attrs['errorText'] ); ?></p>
					<button class="button primary" id="retry-video" type="button"><?php echo esc_html( (string) $attrs['retryLabel'] ); ?></button>
				</div>
			</div>

			<div class="live-now-info">
				<span class="live-channel-logo" aria-hidden="true"><?php echo Block_Support::heading_icon( $channel['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- سازنده‌ی امن. ?></span>
				<div>
					<small><?php echo esc_html( (string) $attrs['nowLabel'] ); ?> · <?php echo esc_html( manacore_fa_digits( $channel['quality'] ) ); ?>p</small>
					<h2 id="live-title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( '' !== $subtitle ) : ?>
						<p id="live-subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
				<div class="live-player-actions">
					<button class="icon-button" id="mute-video" type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'روشن کردن صدا', 'manacore' ); ?>">
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5z"/><path d="m17 9 4 6M21 9l-4 6"/></svg>
					</button>
					<button class="icon-button" id="fullscreen-video" type="button" aria-label="<?php esc_attr_e( 'تمام‌صفحه', 'manacore' ); ?>">
						<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 15v5h-5M15 4h5v5M9 20H4v-5"/></svg>
					</button>
				</div>
			</div>

			<?php if ( ! empty( $attrs['showNotice'] ) && '' !== trim( (string) $attrs['noticeText'] ) ) : ?>
				<div class="demo-notice">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
					<p><?php echo esc_html( (string) $attrs['noticeText'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $attrs['showProgram'] ) && ! empty( $program['items'] ) ) : ?>
				<section class="live-program">
					<div class="section-heading">
						<div><h2><?php echo esc_html( $heading ); ?></h2></div>
					</div>
					<div class="program-grid">
						<?php foreach ( $program['items'] as $index => $item ) : ?>
							<?php $is_active = ( (int) $program['active'] === $index ); ?>
							<div class="<?php echo $is_active ? 'active' : ''; ?>">
								<small>
									<?php
									if ( $is_active ) {
										echo esc_html( (string) $attrs['nowLabel'] );
									} elseif ( ! empty( $program['fallback'] ) ) {
										echo esc_html( (string) $attrs['fallbackLabel'] );
									} else {
										echo esc_html( (string) $attrs['scheduleLabel'] );
									}
									?>
								</small>
								<h3><a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
								<span><span aria-hidden="true"><?php echo esc_html( (string) $attrs['clockGlyph'] ); ?></span> <?php echo esc_html( manacore_fa_digits( $item['time'] ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * رندر «فهرست کانال‌ها» (`.live-channels` مرجع).
	 *
	 * @param array $attrs ویژگی‌ها.
	 * @return string
	 */
	public function render_live_channels( $attrs ) {
		$definitions = $this->definitions();
		$attrs       = wp_parse_args(
			is_array( $attrs ) ? $attrs : array(),
			Block_Support::defaults( $definitions['manacore/live-channels']['attributes'] )
		);

		$limit    = max( 0, (int) $attrs['limit'] );
		$channels = Channel::all( $limit );

		if ( ! $channels ) {
			return Block_Support::render_empty( $attrs, 'manacore-live-channels' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['channel'] ) ? sanitize_title( wp_unslash( $_GET['channel'] ) ) : '';
		// phpcs:enable

		$current = Channel::resolve( $slug );

		$note_title = trim( (string) $attrs['noteTitle'] );
		$note_text  = trim( (string) $attrs['noteText'] );
		$note_link  = manacore_resolve_link( (string) $attrs['noteLinkUrl'] );
		$note_label = trim( (string) $attrs['noteLinkLabel'] );

		ob_start();
		?>
		<aside <?php echo Block_Support::wrapper( $attrs, array( 'manacore-live-channels', 'live-channels' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="section-heading">
				<div>
					<h2><?php echo Block_Support::heading_icon( (string) $attrs['headingIcon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- سازنده‌ی امن. ?><?php echo esc_html( (string) $attrs['heading'] ); ?></h2>
				</div>
				<?php if ( '' !== trim( (string) $attrs['countLabel'] ) ) : ?>
					<span><?php echo esc_html( str_replace( '{count}', manacore_fa_digits( count( $channels ) ), (string) $attrs['countLabel'] ) ); ?></span>
				<?php endif; ?>
			</div>

			<div id="channel-list" role="list">
				<?php
				foreach ( $channels as $channel ) :
					$data     = Channel::payload( $channel );
					$is_now   = $current && (int) $current->ID === (int) $channel->ID;
					$suffix   = trim( (string) $attrs['qualitySuffix'] );
					$subtitle = manacore_fa_digits( $data['quality'] ) . 'p';
					if ( '' !== $suffix ) {
						$subtitle .= ' · ' . $suffix;
					}
					?>
					<a class="channel-card<?php echo $is_now ? ' active' : ''; ?>" role="listitem"
						href="<?php echo esc_url( $data['url'] ); ?>"
						data-channel="<?php echo esc_attr( $data['slug'] ); ?>"
						<?php echo $is_now ? ' aria-current="true"' : ''; ?>>
						<span class="channel-icon" aria-hidden="true"><?php echo Block_Support::heading_icon( $data['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- سازنده‌ی امن. ?></span>
						<span>
							<strong><?php echo esc_html( $data['name'] ); ?></strong>
							<small><?php echo esc_html( $subtitle ); ?></small>
						</span>
						<?php if ( $is_now ) : ?>
							<span class="channel-on-air"><span class="live-dot" aria-hidden="true"></span><?php echo esc_html( (string) $attrs['onlineLabel'] ); ?></span>
						<?php else : ?>
							<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $attrs['showNote'] ) && ( '' !== $note_title || '' !== $note_text || '' !== $note_label ) ) : ?>
				<div class="live-sidebar-note">
					<?php echo Block_Support::heading_icon( (string) $attrs['noteIcon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- سازنده‌ی امن. ?>
					<?php if ( '' !== $note_title ) : ?>
						<h3><?php echo esc_html( $note_title ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $note_text ) : ?>
						<p><?php echo esc_html( $note_text ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $note_label && '' !== $note_link ) : ?>
						<a href="<?php echo esc_url( $note_link ); ?>"><?php echo esc_html( $note_label ); ?> <span aria-hidden="true">‹</span></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

}

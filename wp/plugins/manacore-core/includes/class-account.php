<?php
/**
 * برگه‌ی «حساب کاربری» — داده‌ی واقعی کاربر (هم‌ارز `account.html` مرجع).
 *
 * مرجع همه‌ی شمارنده‌ها و تحلیل‌های خود را از `localStorage` می‌سازد
 * (کلیدهای `cinora-watchlist`، `cinora-progress`، `cinora-ratings` و…).
 * در محصول، منبع حقیقت **داده‌ی وردپرس** است:
 *
 *   • لیست تماشا  → متای کاربر `manacore_watchlist` (کلاس Watchlist)
 *   • دیده‌شده‌ها  → متای کاربر `manacore_watched`
 *   • پیشرفت پخش   → متای کاربر `manacore_progress` (شناسه‌ی اثر → درصد/دقیقه/زمان)
 *   • امتیازها     → جدول `manacore_ratings` (کلاس Ratings)
 *
 * localStorage در محصول فقط «کشِ خوش‌بینانه‌ی» سمت کاربر می‌ماند تا پخش
 * بی‌وقفه باشد؛ عددی که در برگه‌ی حساب دیده می‌شود همیشه از همین کلاس
 * می‌آید. هیچ شمارنده‌ای در قالب یا جاوااسکریپت سخت‌کد نمی‌شود.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Account
 */
class Account {

	/**
	 * کلاس یگانه.
	 *
	 * @var Account|null
	 */
	protected static $instance = null;

	/**
	 * نامک برگه‌ی حساب.
	 */
	const PAGE_SLUG = 'account';

	/**
	 * کلید متای پیشرفت پخش.
	 */
	const PROGRESS_KEY = 'manacore_progress';

	/**
	 * کلید متای تنظیمات شخصی (ردیف‌های کلیدی برگه‌ی تنظیمات).
	 */
	const PREF_KEY = 'manacore_prefs';

	/**
	 * کلید متای شناسه‌ی پیوستِ عکس پروفایل.
	 */
	const AVATAR_KEY = 'manacore_avatar_id';

	/**
	 * نمونه‌ی یگانه.
	 *
	 * @return Account
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		/* برگه‌ی حساب در نصب تازه ساخته می‌شود (مثل برگه‌ی پخش). */
		add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_page' ) );
	}

	/**
	 * ساخت برگه در پس‌زمینه، یک بار.
	 *
	 * @return void
	 */
	public static function maybe_ensure_page() {
		if ( get_option( 'manacore_account_page_checked' ) ) {
			return;
		}
		self::ensure_page();
		update_option( 'manacore_account_page_checked', 1 );
	}

	/**
	 * شناسه‌ی برگه‌ی حساب (اگر وجود داشته باشد).
	 *
	 * @return int
	 */
	public static function page_id() {
		$page = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		return $page ? (int) $page->ID : 0;
	}

	/**
	 * ساخت برگه‌ی حساب اگر نبود.
	 *
	 * صفحه‌ی موجود هرگز بازنویسی نمی‌شود تا کاربر آزاد باشد متن/قالبش را
	 * دلخواه کند — همان قراردادی که برگه‌ی پخش دارد.
	 *
	 * @return int
	 */
	public static function ensure_page() {
		$existing = self::page_id();
		if ( $existing ) {
			return $existing;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'حساب کاربری', 'manacore' ),
				'post_name'    => self::PAGE_SLUG,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		return ( is_wp_error( $page_id ) || ! $page_id ) ? 0 : (int) $page_id;
	}

	/**
	 * نشانی برگه‌ی حساب با پارامترهای دلخواه.
	 *
	 * @param array $args پارامترهای نشانی (مثلاً `array( 'tab' => 'watchlist' )`).
	 * @return string
	 */
	public static function page_url( $args = array() ) {
		$page_id = self::page_id();
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		$url = (string) get_permalink( $page_id );

		if ( ! empty( $args ) ) {
			$url = (string) add_query_arg( array_map( 'sanitize_text_field', (array) $args ), $url );
		}

		return $url;
	}

	/**
	 * تب‌های برگه‌ی حساب — عیناً همان شش تب مرجع.
	 *
	 * @return array شناسه => برچسب.
	 */
	public static function tabs() {
		return array(
			'overview'     => __( 'دنیای من', 'manacore' ),
			'watchlist'    => __( 'لیست تماشا', 'manacore' ),
			'requests'     => __( 'درخواست‌های من', 'manacore' ),
			'history'      => __( 'تاریخچه تماشا', 'manacore' ),
			'analytics'    => __( 'سلیقه من', 'manacore' ),
			/*
			 * نیم‌فاصله این‌جا عمداً گذاشته نشده: مرجع در `account.html`
			 * هر سه بار «صورتحساب» را سرهم نوشته و سنجش هم‌سانی برچسب‌های
			 * ستون کنار، کاراکتر‌به‌کاراکتر مقایسه می‌کند.
			 */
			'subscription' => __( 'اشتراک و صورتحساب', 'manacore' ),
			'settings'     => __( 'تنظیمات حساب', 'manacore' ),
		);
	}

	/**
	 * ردیف‌های ناوبری ستون کنار (`.account-sidebar nav`) — همان شش تب،
	 * با نشان شمارشی روی «لیست تماشا» و برچسب کوچک روی «سلیقه من».
	 *
	 * ساختار هر ردیف: `slug`, `label`, `url`, `badge`, `tag`, `active`.
	 *
	 * @return array
	 */
	public static function nav_items() {
		$tabs     = self::tabs();
		$current  = self::current_tab();
		$watch    = count( self::watchlist() );
		$items    = array();

		/*
		 * نشان شمارشی همیشه هست — مرجع برای کاربر بی‌لیست هم «۰» نشان
		 * می‌دهد؛ پنهان‌کردن آن جای ردیف تب را جابه‌جا می‌کرد.
		 *
		 * تب درخواست‌ها نشان را فقط وقتی می‌گیرد که کاربر درخواستی
		 * داشته باشد؛ «۰» کنار «درخواست‌های من» فقط شلوغی است.
		 */
		$badges = array( 'watchlist' => manacore_fa_digits( $watch ) );

		if ( class_exists( Requests::class ) ) {
			$requests = Requests::mine_total();

			if ( $requests > 0 ) {
				$badges['requests'] = manacore_fa_digits( number_format_i18n( $requests ) );
			}
		}
		$tags   = array( 'analytics' => __( 'برای تو', 'manacore' ) );

		foreach ( $tabs as $slug => $label ) {
			$items[] = array(
				'slug'   => $slug,
				'label'  => $label,
				'url'    => (string) self::page_url( array( 'tab' => $slug ) ),
				'badge'  => isset( $badges[ $slug ] ) ? $badges[ $slug ] : '',
				'tag'    => isset( $tags[ $slug ] ) ? $tags[ $slug ] : '',
				'active' => ( $slug === $current ),
			);
		}

		return $items;
	}

	/**
	 * پروفایل کاربر برای سرِ ستون کنار (`.account-profile`).
	 *
	 * مهمان هم ردیف واقعی دارد (نه جای‌گیر): نام و متن مرجع، آواتار «◯» و
	 * نشان عضویت خالی — همان حالتی که مرجع با `localStorage` خالی نشان می‌دهد.
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function profile( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return array(
				'guest'      => true,
				'name'       => __( 'مهمان کوهه‌فیلم', 'manacore' ),
				'email_line' => 'Your next story starts here',
				'avatar'     => '',
				'photo'      => '',
				'initial'    => '◯',
				'membership' => __( 'عاشق داستان', 'manacore' ),
			);
		}

		$name  = $user->display_name ? $user->display_name : $user->user_login;
		$email = (string) $user->user_email;

		return array(
			'guest'      => false,
			'name'       => $name,
			'email_line' => $email ? $email : 'Your next story starts here',
			'avatar'     => (string) get_avatar_url( $user_id, array( 'size' => 144 ) ),
			'photo'      => self::avatar_url( $user_id ),
			'initial'    => mb_substr( $name, 0, 1 ),
			'membership' => self::membership_label( $user_id ),
		);
	}

	/**
	 * نشانی عکس پروفایلِ **بارگذاری‌شده‌ی خودِ کاربر** (یا رشته‌ی خالی).
	 *
	 * چرا جدا از `avatar`: آواتار وردپرس همیشه نشانی دارد (گراواتار یا
	 * تصویر پیش‌فرض)، اما طرح مرجع یا عکس بارگذاری‌شده را می‌کند یا حرف
	 * اول نام را؛ «مرد مرموز» گراواتار هیچ‌جای مرجع نیست و در برگه‌ی حساب
	 * با کارت تنظیمات هم ناهمخوان می‌شد (سنجیده‌شده: پس از حذف عکس، ستون
	 * کنار تصویر پیش‌فرض نشان می‌داد و کارت تنظیمات حرف اول نام را).
	 *
	 * `avatar` وردپرسی سر جایش هست: کارت‌های نظرات و پیشخوان همچنان با
	 * فیلتر `pre_get_avatar_data` همین عکس بارگذاری‌شده را می‌گیرند.
	 *
	 * @param int $user_id شناسه‌ی کاربر (۰ = کاربر جاری).
	 * @return string
	 */
	public static function avatar_url( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$attachment_id = (int) get_user_meta( $user_id, self::AVATAR_KEY, true );

		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		return $url ? (string) $url : '';
	}

	/**
	 * برچسب نشان عضویت (`.membership-pill`) از وضعیت واقعی اشتراک.
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return string
	 */
	public static function membership_label( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( $user_id && function_exists( 'manacore_subs_user_level' ) ) {
			$level = (string) manacore_subs_user_level( $user_id );

			if ( '' !== $level && function_exists( 'manacore_subs_levels' ) ) {
				$levels = (array) manacore_subs_levels();

				if ( isset( $levels[ $level ]['label'] ) && $levels[ $level ]['label'] ) {
					return (string) $levels[ $level ]['label'];
				}
			}
		}

		return __( 'عاشق داستان', 'manacore' );
	}

	/**
	 * تب جاری از نشانی.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all = self::tabs();

		return isset( $all[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * فهرست شناسه‌های لیست تماشا.
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return int[]
	 */
	public static function watchlist( $user_id = 0 ) {
		return Watchlist::instance()->get( $user_id );
	}

	/**
	 * فهرست شناسه‌های دیده‌شده.
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return int[]
	 */
	public static function watched( $user_id = 0 ) {
		return Watchlist::instance()->get( $user_id, Watchlist::WATCHED_KEY );
	}

	/**
	 * پیشرفت پخش کاربر.
	 *
	 * ساختار: array( post_id => array( 'percent' => float, 'minutes' => int, 'updated' => int ) )
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function progress( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$raw = get_user_meta( $user_id, self::PROGRESS_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $post_id => $row ) {
			$row = (array) $row;
			$out[ (int) $post_id ] = array(
				'percent' => isset( $row['percent'] ) ? (float) $row['percent'] : 0.0,
				'minutes' => isset( $row['minutes'] ) ? (int) $row['minutes'] : 0,
				'updated' => isset( $row['updated'] ) ? (int) $row['updated'] : 0,
			);
		}

		return $out;
	}

	/**
	 * ثبت پیشرفت یک اثر (از پخش‌کننده یا API).
	 *
	 * @param int   $post_id شناسه‌ی اثر.
	 * @param float $percent درصد دیده‌شده.
	 * @param int   $minutes دقیقه‌ی تماشاشده.
	 * @param int   $user_id شناسه‌ی کاربر.
	 * @return array|WP_Error
	 */
	public static function record_progress( $post_id, $percent, $minutes = 0, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$post_id = absint( $post_id );

		if ( ! $user_id ) {
			return new \WP_Error( 'manacore_not_logged_in', __( 'ابتدا وارد حساب کاربری شوید.', 'manacore' ), array( 'status' => 401 ) );
		}
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'manacore_invalid_post', __( 'محتوا یافت نشد.', 'manacore' ), array( 'status' => 404 ) );
		}

		$percent = max( 0, min( 100, (float) $percent ) );
		$all     = self::progress( $user_id );
		$before  = isset( $all[ $post_id ] ) ? $all[ $post_id ] : array( 'minutes' => 0 );

		$all[ $post_id ] = array(
			'percent' => round( $percent, 2 ),
			/* دقیقه‌ها انبار می‌شوند (هر بار فقط افزایش). */
			'minutes' => max( (int) $before['minutes'], absint( $minutes ) ),
			'updated' => time(),
		);

		update_user_meta( $user_id, self::PROGRESS_KEY, $all );

		/*
		 * «دیده‌شده» یعنی تمام‌شده — نه «شروع‌شده».
		 *
		 * مرجع شمارنده‌ی کارت «داستان تماشاشده» را با
		 * `history.filter(x => x.progress >= 95).length` می‌سازد؛ اگر هر
		 * ثبت پیشرفت (حتی ۵٪) را «دیده‌شده» بشماریم، همان کارت با مرجع
		 * یکی درنمی‌آمد (سنجیده‌شده: ۴ در برابر ۱ با فیکسچر یکسان).
		 * پس فقط اثر تمام‌شده خودکار به «دیده‌شده‌ها» اضافه می‌شود؛
		 * علامت‌گذاری دستی کاربر (`list=watched` در REST) سر جای خود است.
		 */
		if ( $all[ $post_id ]['percent'] >= 95 ) {
			$watched = self::watched( $user_id );
			if ( ! in_array( $post_id, $watched, true ) ) {
				$watched[] = $post_id;
				update_user_meta( $user_id, Watchlist::WATCHED_KEY, array_values( array_unique( $watched ) ) );
			}
		}

		return array(
			'post_id' => $post_id,
			'percent' => $all[ $post_id ]['percent'],
			'minutes' => $all[ $post_id ]['minutes'],
			'count'   => count( $all ),
		);
	}

	/**
	 * پاک کردن تاریخچه‌ی تماشا (پیشرفت و دیده‌شده‌ها).
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return int شمار ردیف‌های حذف‌شده.
	 */
	public static function clear_history( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		$count = count( self::progress( $user_id ) );

		delete_user_meta( $user_id, self::PROGRESS_KEY );
		update_user_meta( $user_id, Watchlist::WATCHED_KEY, array() );

		return $count;
	}

	/**
	 * دقیقه‌ی تماشای کاربر.
	 *
	 * اگر پیشرفت ثبت‌شده باشد از همان دقیقه‌ها؛ وگرنه جمع مدت آثار
	 * «دیده‌شده» (`manacore_runtime` و برای سریال‌ها `manacore_episode_runtime`).
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return int
	 */
	public static function minutes( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		$total = 0;
		foreach ( self::progress( $user_id ) as $row ) {
			$total += (int) $row['minutes'];
		}

		if ( $total > 0 ) {
			return $total;
		}

		foreach ( self::watched( $user_id ) as $post_id ) {
			$total += self::item_minutes( $post_id );
		}

		return $total;
	}

	/**
	 * مدت یک اثر به دقیقه (فیلم = مدت کل، سریال = مدت هر قسمت × تعداد).
	 *
	 * @param int $post_id شناسه‌ی اثر.
	 * @return int
	 */
	public static function item_minutes( $post_id ) {
		$runtime = (int) get_post_meta( $post_id, 'manacore_runtime', true );

		if ( $runtime > 0 ) {
			return $runtime;
		}

		$per_episode = (int) get_post_meta( $post_id, 'manacore_episode_runtime', true );
		if ( $per_episode > 0 ) {
			$episodes = function_exists( 'manacore_series_episodes_count' )
				? (int) manacore_series_episodes_count( $post_id )
				: 0;
			return $per_episode * max( 1, $episodes );
		}

		return 0;
	}

	/**
	 * شمارنده‌های چهارگانه‌ی بالای برگه (`.stat-grid` مرجع).
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function stats( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		$stats = array(
			'watchlist' => 0,
			'watched'   => 0,
			'minutes'   => 0,
			'genre'     => '',
		);

		if ( ! $user_id ) {
			return $stats;
		}

		$stats['watchlist'] = count( self::watchlist( $user_id ) );
		$stats['watched']   = count( self::watched( $user_id ) );
		$stats['minutes']   = self::minutes( $user_id );

		$top = self::genre_tally( array_merge( self::watchlist( $user_id ), self::watched( $user_id ) ) );
		if ( $top ) {
			$stats['genre'] = $top[0]['name'];
		}

		return $stats;
	}

	/**
	 * چیدن ژانرهای فهرستی از آثار، بیشترین اول.
	 *
	 * @param int[] $ids شناسه‌ها.
	 * @return array array( array( 'slug', 'name', 'count', 'percent' ), … )
	 */
	public static function genre_tally( $ids ) {
		$ids = array_values( array_unique( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$terms = wp_get_object_terms( $ids, 'genre', array( 'fields' => 'all_with_object_id' ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$counts = array();
		$names  = array();
		foreach ( $terms as $term ) {
			$counts[ $term->term_id ] = isset( $counts[ $term->term_id ] ) ? $counts[ $term->term_id ] + 1 : 1;
			$names[ $term->term_id ]  = $term->name;
		}

		arsort( $counts );
		$total = array_sum( $counts );
		$out   = array();
		foreach ( $counts as $term_id => $count ) {
			$out[] = array(
				'slug'    => (string) get_term_field( 'slug', $term_id, 'genre' ),
				'name'    => (string) $names[ $term_id ],
				'count'   => (int) $count,
				'percent' => $total ? round( ( $count / $total ) * 100 ) : 0,
			);
		}

		return $out;
	}

	/**
	 * شناسه‌های پیشنهادی «برای تو» بر پایه‌ی ژانرهای سلیقه‌ی کاربر.
	 *
	 * سلیقه = ژانرهای آثار «لیست تماشا» + «دیده‌شده». سه ژانر نخست ملاک
	 * است تا فهرست خروجی به سلیقه‌ی غالب کاربر بچسبد، و آثار خودِ کاربر از
	 * پیشنهاد کنار گذاشته می‌شوند.
	 *
	 * @param int $count   بیشترین تعداد.
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return int[]
	 */
	public static function recommended_ids( $count = 8, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$seen = array_values( array_unique( array_merge( self::watchlist( $user_id ), self::watched( $user_id ) ) ) );
		$tally = self::genre_tally( $seen );
		if ( ! $tally ) {
			return array();
		}

		$slugs = array();
		foreach ( array_slice( $tally, 0, 3 ) as $row ) {
			if ( ! empty( $row['slug'] ) ) {
				$slugs[] = $row['slug'];
			}
		}
		if ( ! $slugs ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'           => manacore_title_post_types(),
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, (int) $count ),
				'post__not_in'        => $seen,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'genre',
						'field'    => 'slug',
						'terms'    => $slugs,
					),
				),
				'meta_key'            => 'manacore_imdb_rating',
				'orderby'             => 'meta_value_num',
				'order'               => 'DESC',
			)
		);

		return array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * تحلیل‌های تب «سلیقه من».
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function analytics( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$ids     = $user_id ? array_merge( self::watched( $user_id ), self::watchlist( $user_id ) ) : array();
		$ids     = array_values( array_unique( array_map( 'absint', $ids ) ) );

		$out = array(
			'genres'    => array(),
			'formats'   => array(),
			'activity'  => array(),
			'countries' => array(),
			'total'     => count( $ids ),
		);

		if ( ! $ids ) {
			return $out;
		}

		$out['genres'] = array_slice( self::genre_tally( $ids ), 0, 6 );

		/* سهم فرمت: فیلم / سریال / انیمه (همان سه نوع مرجع). */
		$labels = array(
			'movie'  => __( 'فیلم', 'manacore' ),
			'series' => __( 'سریال', 'manacore' ),
			'anime'  => __( 'انیمه', 'manacore' ),
		);
		$counts = array( 'movie' => 0, 'series' => 0, 'anime' => 0 );
		foreach ( $ids as $post_id ) {
			$type = get_post_type( $post_id );
			if ( isset( $counts[ $type ] ) ) {
				$counts[ $type ]++;
			}
		}
		$total = array_sum( $counts );
		foreach ( $counts as $type => $count ) {
			$out['formats'][] = array(
				'type'    => $type,
				'label'   => $labels[ $type ],
				'count'   => $count,
				'percent' => $total ? round( ( $count / $total ) * 100 ) : 0,
			);
		}

		/* فعالیت هفتگی: شمار رویدادهای ثبت‌شده در هفت روز گذشته. */
		$progress = self::progress( $user_id );
		$days     = array();
		$today    = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		for ( $i = 6; $i >= 0; $i-- ) {
			$day          = gmdate( 'Y-m-d', $today - ( $i * DAY_IN_SECONDS ) );
			$days[ $day ] = 0;
		}
		foreach ( $progress as $row ) {
			if ( empty( $row['updated'] ) ) {
				continue;
			}
			$day = gmdate( 'Y-m-d', (int) $row['updated'] );
			if ( isset( $days[ $day ] ) ) {
				$days[ $day ]++;
			}
		}
		$max = max( 1, max( $days ) );
		foreach ( $days as $day => $count ) {
			$out['activity'][] = array(
				'date'    => $day,
				'label'   => self::weekday_label( $day ),
				'count'   => $count,
				'percent' => (int) round( ( $count / $max ) * 100 ),
			);
		}

		/* کشورها از تاکسونومی `country` (اگر ترمی باشد). */
		$terms = wp_get_object_terms( $ids, 'country', array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			$tally = array();
			$names = array();
			foreach ( $terms as $term ) {
				$tally[ $term->term_id ] = isset( $tally[ $term->term_id ] ) ? $tally[ $term->term_id ] + 1 : 1;
				$names[ $term->term_id ] = $term->name;
			}
			arsort( $tally );
			$sum = array_sum( $tally );
			foreach ( array_slice( $tally, 0, 5, true ) as $term_id => $count ) {
				$out['countries'][] = array(
					'name'    => (string) $names[ $term_id ],
					'count'   => (int) $count,
					'percent' => $sum ? round( ( $count / $sum ) * 100 ) : 0,
				);
			}
		}

		return $out;
	}

	/**
	 * نام روز هفته برای تاریخ میلادی (برچسب ستون‌های نمودار).
	 *
	 * @param string $date تاریخ `Y-m-d`.
	 * @return string
	 */
	public static function weekday_label( $date ) {
		$stamp = strtotime( $date . ' 12:00:00' );
		if ( ! $stamp ) {
			return '';
		}

		global $wp_locale;
		$index = (int) gmdate( 'w', $stamp );
		if ( isset( $wp_locale->weekday[ $index ] ) && $wp_locale->weekday[ $index ] ) {
			return $wp_locale->weekday[ $index ];
		}

		return (string) gmdate( 'D', $stamp );
	}

	/**
	 * ردیف‌های «تاریخچه تماشا».
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @param int $limit   بیشترین ردیف.
	 * @return array
	 */
	public static function history( $user_id = 0, $limit = 12 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$progress = self::progress( $user_id );
		$watched  = self::watched( $user_id );
		$rows     = array();

		foreach ( $watched as $post_id ) {
			$row      = isset( $progress[ $post_id ] ) ? $progress[ $post_id ] : array( 'percent' => 100, 'updated' => 0 );
			$rows[]   = array(
				'post_id' => $post_id,
				'percent' => (float) $row['percent'],
				'updated' => (int) $row['updated'],
			);
		}

		/* پیشرفت‌های بدون «دیده‌شده» هم ردیف می‌سازند. */
		foreach ( $progress as $post_id => $row ) {
			if ( in_array( $post_id, $watched, true ) ) {
				continue;
			}
			$rows[] = array(
				'post_id' => (int) $post_id,
				'percent' => (float) $row['percent'],
				'updated' => (int) $row['updated'],
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['updated'] <=> $a['updated'];
			}
		);

		return array_slice( $rows, 0, max( 1, (int) $limit ) );
	}

	/**
	 * تنظیمات شخصی کاربر (ردیف‌های کلیدی برگه‌ی تنظیمات).
	 *
	 * @param int $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function prefs( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$raw = get_user_meta( $user_id, self::PREF_KEY, true );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * ذخیره‌ی یک تنظیم شخصی.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $value   مقدار.
	 * @param int    $user_id شناسه‌ی کاربر.
	 * @return array
	 */
	public static function set_pref( $key, $value, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$prefs         = self::prefs( $user_id );
		$prefs[ sanitize_key( $key ) ] = $value;
		update_user_meta( $user_id, self::PREF_KEY, $prefs );

		return $prefs;
	}
}

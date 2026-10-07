<?php
/**
 * کشوی منوی موبایل — همسان با مرجع «سینورا».
 *
 * ساختار خروجی عیناً همان مرجع است تا هم اندازه‌ها و هم رفتار یکسان باشد:
 *
 *     .drawer-backdrop[data-mobile-drawer][hidden]
 *       └ .mobile-drawer[role=dialog][aria-modal=true]
 *           ├ .drawer-top        (برند + دکمه‌ی بستن [data-mobile-close])
 *           ├ .header-search     (ردیف جستجو، بازکننده‌ی پوسته‌ی جستجو)
 *           ├ nav > a …          (ردیف‌های فهرست + شِوران)
 *           ├ a.button.primary.full   (CTA اشتراک)
 *           └ p.muted
 *
 * چرا ردیف‌ها سمت سرور ساخته می‌شوند؟
 * هسته‌ی وردپرس در ≤۹۸۰px به‌طور پیش‌فرض «پوشش تمام‌صفحه» می‌سازد، نه کشوی
 * کنارِ مرجع. برای هم‌سانی، بلوک ناوبری سربرگ با `overlayMenu: never`
 * رندر می‌شود و پوسته‌ی موبایل را همین بلوک می‌سازد. ردیف‌ها از همان
 * فهرست راهبری‌ای می‌آیند که سربرگ استفاده می‌کند
 * (`koohe_primary_navigation_id()`)، پس با هر ویرایش فهرست در
 * «ظاهر ← ویرایشگر ← فهرست راهبری» همگام می‌مانند.
 *
 * @package KooheFilm
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس‌هایی که در کشو ردیف نمی‌شوند.
 *
 * این آیتم‌ها فقط تزئینات پنل مگامنوی دسکتاپ‌اند (سرستون، چیپ‌های ژانر و
 * کارت ویژه)، نه مقصدهای ناوبری. مرجع هم در کشو چنین آیتم‌هایی ندارد.
 *
 * @return array<string>
 */
function koohe_mobile_drawer_skipped_classes() {
	return array( 'koohe-mega-eyebrow', 'koohe-mega-title', 'koohe-mega-feature', 'koohe-mega-group' );
}

/**
 * فهرست راهبری‌ای که سربرگ به آن بسته است.
 *
 * @return int
 */
function koohe_mobile_drawer_menu_id() {
	$menu_id = function_exists( 'koohe_primary_navigation_id' ) ? (int) koohe_primary_navigation_id() : 0;

	if ( $menu_id ) {
		return $menu_id;
	}

	/* جانشین هسته: نخستین فهرست راهبری منتشرشده. */
	$menus = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return $menus ? (int) $menus[0] : 0;
}

/**
 * تبدیل یک بلوک ناوبری به ردیف کشو.
 *
 * @param array $block بلوک تجزیه‌شده (navigation-link یا navigation-submenu).
 * @return array|null آرایه‌ی {label,url,current} یا null اگر ردیف نباشد.
 */
function koohe_mobile_drawer_item( $block ) {
	$attrs   = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$classes = array();

	if ( ! empty( $attrs['className'] ) ) {
		$classes = preg_split( '/\s+/', trim( (string) $attrs['className'] ) );
	}

	foreach ( (array) $classes as $class ) {
		if ( in_array( $class, koohe_mobile_drawer_skipped_classes(), true ) ) {
			return null;
		}

		/* چیپ‌های ژانر پنل مگامنو: `koohe-mega-genre` و `…-genre-a|b`. */
		if ( 0 === strpos( $class, 'koohe-mega-genre' ) ) {
			return null;
		}
	}

	$label = isset( $attrs['label'] ) ? trim( (string) $attrs['label'] ) : '';
	$url   = isset( $attrs['url'] ) ? trim( (string) $attrs['url'] ) : '';

	if ( '' === $url && ! empty( $attrs['id'] ) ) {
		$kind = isset( $attrs['kind'] ) ? (string) $attrs['kind'] : '';

		if ( 'post_type' === $kind ) {
			$url = (string) get_permalink( (int) $attrs['id'] );
		} elseif ( 'taxonomy' === $kind ) {
			$term = get_term( (int) $attrs['id'], isset( $attrs['type'] ) ? (string) $attrs['type'] : '' );

			if ( $term && ! is_wp_error( $term ) ) {
				$url = (string) get_term_link( $term );
			}
		}
	}

	if ( '' === $label || '' === $url ) {
		return null;
	}

	return array(
		'label'   => $label,
		'url'     => $url,
		'current' => koohe_mobile_drawer_is_current( $url ),
	);
}

/**
 * آیا این نشانی همان برگه‌ی جاری است؟
 *
 * @param string $url نشانی ردیف.
 * @return bool
 */
function koohe_mobile_drawer_is_current( $url ) {
	$queried = (int) get_queried_object_id();

	if ( $queried && ( is_singular() || is_page() ) ) {
		return $queried === (int) url_to_postid( $url );
	}

	/*
	 * مقایسه‌ی «مسیر + پارامترها» — نه فقط مسیر.
	 *
	 * نسخه‌ی پیشین کوئری را دور می‌ریخت، پس در صفحه‌ی خانه هر ردیفی که
	 * با پارامتر کار می‌کند (`/?post_type=movie&mc_sort=rating`،
	 * `/?post_type=movie&mc_sort=newest`، `/?post_type=person`) هم‌نشانی
	 * خانه می‌شد و هم‌زمان با خانه «جاری» علامت می‌خورد: چهار ردیف سبز
	 * در کشو (سنجیده‌شده). اکنون پارامترها هم مقایسه می‌شوند.
	 */
	$normalize = static function ( $value ) {
		$parts = wp_parse_url( (string) $value );
		$query = array();

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			unset( $query['paged'] ); // صفحه‌بندی، همان برگه است.
			ksort( $query );
		}

		return array(
			'host'  => isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '',
			'path'  => untrailingslashit( isset( $parts['path'] ) ? (string) $parts['path'] : '/' ),
			'query' => $query,
		);
	};

	$row     = $normalize( $url );
	$current = $normalize( home_url( add_query_arg( array() ) ) );

	if ( $row['host'] === $current['host'] && $row['path'] === $current['path'] && $row['query'] === $current['query'] ) {
		return true;
	}

	/*
	 * آرشیوِ نوع محتوا (`/movie/?mc_sort=rating`، `/?post_type=movie&mc_sort=rating`)
	 * همان نمای ردیف‌های پارامتری است، فقط محدود به یک نوع محتوا؛ پس ردیف
	 * «بالاترین امتیازها» باید در آن هم جاری باشد. شرط‌ها:
	 *
	 *   1. ردیف پارامتر داشته باشد — وگرنه «خانه» روی هر آرشیوی جاری می‌شد
	 *      (خطای نسخه‌ی پیشین).
	 *   2. تنها تفاضل، پارامتر `post_type` باشد؛ پارامترهای فیلتر و
	 *      مرتب‌سازی باید کاملاً برابر باشند تا دو نمای متفاوت یکی شمرده نشوند.
	 *   3. مسیر ردیف همان آرشیوِ همان نوع محتوا باشد.
	 *
	 * نکته‌ی سنجیده‌شده: این بلوک پیش‌تر *بعد* از بازگشتِ «مسیر یکی نیست»
	 * نوشته شده بود و در نتیجه هرگز اجرا نمی‌شد؛ چون وردپرس
	 * `/?post_type=movie&mc_sort=rating` را به `/movie/` تغییر مسیر نمی‌دهد،
	 * مسیر ردیف (`/movie`) با مسیر جاری (`/`) یکی نبود و ردیف جاری در
	 * آرشیو هیچ‌وقت سبز نمی‌شد (کشف‌شده با `drawer-parity.cjs`).
	 */
	if ( ! $row['query'] || empty( $current['query']['post_type'] ) ) {
		return false;
	}

	$type         = (string) $current['query']['post_type'];
	$archive_url  = get_post_type_archive_link( $type );
	$archive_path = $archive_url ? untrailingslashit( (string) wp_parse_url( $archive_url, PHP_URL_PATH ) ) : '';

	if ( '' === $archive_path || $row['path'] !== $archive_path ) {
		return false;
	}

	unset( $current['query']['post_type'] );

	return $row['query'] === $current['query'];
}

/**
 * گردآوری ردیف‌های کشو از بلوک‌های فهرست راهبری.
 *
 * تودرتویی آزاد است: گروه‌های پنل مگامنو (`.mega-genres` و `.mega-quick`)
 * زیرمنوی درون‌زیرمنو هستند، پس پیمایش بازگشتی لازم است. برچسب خودِ گروه
 * ردیف نمی‌شود (`koohe-mega-group` در فهرست نادیده‌گرفتنی‌ها است) ولی
 * فرزندانش — یعنی چیپ‌های ژانر و ردیف‌های دسترسی سریع — سر جای خودشان
 * می‌آیند.
 *
 * @param array<array> $blocks بلوک‌های تجزیه‌شده.
 * @param array<array> $items  ردیف‌های گردآوری‌شده (با ارجاع).
 * @return void
 */
function koohe_mobile_drawer_collect( $blocks, &$items ) {
	foreach ( (array) $blocks as $block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( 'core/navigation-link' === $name ) {
			$item = koohe_mobile_drawer_item( $block );

			if ( $item ) {
				$items[] = $item;
			}

			continue;
		}

		if ( 'core/navigation-submenu' !== $name ) {
			continue;
		}

		$parent = koohe_mobile_drawer_item( $block );

		if ( $parent ) {
			$items[] = $parent;
		}

		koohe_mobile_drawer_collect( isset( $block['innerBlocks'] ) ? (array) $block['innerBlocks'] : array(), $items );
	}
}

/**
 * ردیف‌های کشو: فهرست سربرگ، یک‌سطحی‌شده، به‌همراه ردیف «حساب کاربری».
 *
 * @return array<array>
 */
function koohe_mobile_drawer_items() {
	$items   = array();
	$menu_id = koohe_mobile_drawer_menu_id();
	$menu    = $menu_id ? get_post( $menu_id ) : null;

	if ( $menu && '' !== trim( (string) $menu->post_content ) ) {
		koohe_mobile_drawer_collect( (array) parse_blocks( $menu->post_content ), $items );
	}

	/* ردیف پایانی مرجع: «حساب کاربری». */
	$account_url = function_exists( 'koohe_account_url' ) ? koohe_account_url() : wp_login_url();

	$items[] = array(
		'label'   => is_user_logged_in()
			? (string) wp_get_current_user()->display_name
			: __( 'حساب کاربری', 'koohe-film' ),
		'url'     => $account_url,
		'current' => false,
	);

	/**
	 * فیلتر ردیف‌های کشوی موبایل.
	 *
	 * @param array<array> $items ردیف‌ها.
	 */
	return (array) apply_filters( 'koohe_mobile_drawer_items', $items );
}

/**
 * برند کشو — همان نشان و نام سربرگ.
 *
 * @return string
 */
function koohe_mobile_drawer_brand() {
	return (string) do_blocks( '<!-- wp:site-logo {"width":36,"shouldSyncIcon":true} /--><!-- wp:site-title {"level":0,"className":"koohe-logo-text"} /-->' );
}

/**
 * آیکن شِوران ردیف‌ها (همان مسیر مرجع).
 *
 * @return string
 */
function koohe_mobile_drawer_chevron() {
	return '<svg class="drawer-chevron" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"/></svg>';
}

/**
 * آیکن تاج دکمه‌ی اشتراک (همان مسیر مرجع).
 *
 * @return string
 */
function koohe_mobile_drawer_crown() {
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m2 4 3 12h14l3-12-6 5-4-7-4 7-6-5zM5 20h14"/></svg>';
}

/**
 * رندر بلوک کشوی موبایل.
 *
 * @param array $attributes ویژگی‌های بلوک.
 * @return string
 */
function koohe_render_mobile_drawer_block( $attributes = array() ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$dialog_label = isset( $attributes['label'] ) && '' !== trim( (string) $attributes['label'] )
		? (string) $attributes['label']
		: __( 'منوی اصلی', 'koohe-film' );

	$search_label = isset( $attributes['searchLabel'] ) && '' !== trim( (string) $attributes['searchLabel'] )
		? (string) $attributes['searchLabel']
		: __( 'جستجوی فیلم و سریال', 'koohe-film' );

	$cta_label = isset( $attributes['ctaLabel'] ) && '' !== trim( (string) $attributes['ctaLabel'] )
		? (string) $attributes['ctaLabel']
		: __( 'خرید اشتراک', 'koohe-film' );

	$note = isset( $attributes['note'] ) && '' !== trim( (string) $attributes['note'] )
		? (string) $attributes['note']
		: __( 'هر داستان، یک دنیای تازه.', 'koohe-film' );

	/*
	 * همان منبعی که افزونه‌ی هسته برای دکمه‌ی اشتراک استفاده می‌کند
	 * (`class-templates.php`)، تا کشو و بقیه‌ی سایت یک نشانی داشته باشند.
	 */
	$cta_url = (string) apply_filters( 'manacore_subscribe_url', home_url( '/subscribe/' ) );

	$items = koohe_mobile_drawer_items();

	ob_start();
	?>
	<div class="drawer-backdrop" data-mobile-drawer hidden>
		<?php
		/*
		 * عنصر `div` است نه `aside`: مرجع `aside role="dialog"` دارد و
		 * axe آن را نقض `aria-allowed-role` (minor) می‌بیند، چون `dialog`
		 * نقش مجاز برای `aside` نیست. رفتار و کلاس‌ها همان مرجع است.
		 */
		?>
		<div class="mobile-drawer" id="koohe-mobile-drawer" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $dialog_label ); ?>">
			<div class="drawer-top">
				<div class="brand koohe-brand"><?php echo koohe_mobile_drawer_brand(); // phpcs:ignore WordPress.Security.EscapeOutput -- بلوک‌های هسته خودشان گریز می‌زنند. ?></div>
				<button type="button" class="icon-button" data-mobile-close aria-label="<?php esc_attr_e( 'بستن منو', 'koohe-film' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m18 6-12 12M6 6l12 12"/></svg>
				</button>
			</div>

			<button type="button" class="header-search" data-koohe-search-open data-mobile-search aria-haspopup="dialog" aria-expanded="false" aria-controls="koohe-search-overlay">
				<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				<?php echo esc_html( $search_label ); ?>
			</button>

			<nav aria-label="<?php echo esc_attr( $dialog_label ); ?>">
				<?php foreach ( $items as $item ) : ?>
					<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo ! empty( $item['current'] ) ? ' class="is-current" aria-current="page"' : ''; ?>>
						<span><?php echo esc_html( $item['label'] ); ?></span>
						<?php echo koohe_mobile_drawer_chevron(); // phpcs:ignore WordPress.Security.EscapeOutput -- مارک‌آپ ثابت. ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<a href="<?php echo esc_url( $cta_url ); ?>" class="button primary full"><?php echo koohe_mobile_drawer_crown(); // phpcs:ignore WordPress.Security.EscapeOutput -- مارک‌آپ ثابت. ?><?php echo esc_html( $cta_label ); ?></a>

			<p class="muted"><?php echo esc_html( $note ); ?></p>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

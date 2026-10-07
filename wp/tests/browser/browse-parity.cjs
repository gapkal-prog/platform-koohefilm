/**
 * آزمون هم‌سانی برگه‌ی «کشف داستان‌ها» با مرجع «سینورا» + آزمون رفتار.
 *
 * سه لایه سنجیده می‌شود:
 *   ۱) هم‌سانی چیدمان با `cinora/browse.html` در ۱۴۴۰px و ۳۹۰px: حضور
 *      همان کلاس‌ها و همان هندسه (نوار مرور، سایدبار، ستون نتایج، شمار
 *      ستون‌ها، خلاصه‌ی نتایج).
 *   ۱.۵) سایدبار «فیلتر پیشرفته»: پنج گروه مرجع (ژانرهای تیک‌زنی با شمار،
 *      بازه‌ی سال، لغزنده‌ی امتیاز، گزینشگر تاکسونومی، کلید دوبله) + دکمه‌ی
 *      پاک‌سازی و پنل راهنما، با همان متریک‌های محاسبه‌شده‌ی مرجع، و رفتار
 *      واقعی هرکدام روی داده (نه فقط رندر).
 *   ۲) رفتار سمت سرور: صافی نوع (`?type=`)، مرتب‌سازی (`?mc_sort=`)،
 *      جستجو (`?manacore_q=`)، برچسب فیلترهای فعال، حالت خالی، صفحه‌ی
 *      بیرون از محدوده و حفظ وضعیت در فرم‌ها.
 *   ۳) رفتار سمت کاربر: دکمه‌های نوع، حالت فهرستی، برداشتن برچسب فیلتر،
 *      جستجوی تأخیری، دکمه‌ی فیلترهای موبایل و «داستان‌های بیشتر».
 *
 * «داستان‌های بیشتر» روی برگه‌ی آزمون `/qa-browse/` سنجیده می‌شود، چون
 * آن حلقه چهار در هر صفحه می‌ریزد و کاتالوگ آزمون پنج اثر دارد؛ روی
 * برگه‌ی اصلی (۲۴ در هر صفحه) همیشه یک صفحه است و دکمه هرگز رندر نمی‌شد.
 *
 * اجرا:
 *   node browse-parity.cjs
 *   WP_URL=http://localhost:8099/ node browse-parity.cjs
 *   REF_URL=http://localhost:8098/browse.html node browse-parity.cjs
 *
 * @package KooheFilm
 */

'use strict';

let chromium;
let axePath = '';

try {
	( { chromium } = require( 'playwright' ) );
} catch ( error ) {
	console.error( 'playwright نصب نیست؛ این آزمون اجرا نشد.' );
	console.error( error.message );
	process.exit( 2 );
}

try {
	axePath = require.resolve( 'axe-core' );
} catch ( error ) {
	axePath = '';
}

const WP_URL  = process.env.WP_URL || 'http://localhost:8099/';
const REF_URL = process.env.REF_URL || 'http://localhost:8098/browse.html';

const EDITOR_TITLES = [ 'همه', 'فیلم‌ها', 'سریال‌ها', 'انیمه‌ها' ];

let pass = 0;
let fail = 0;

/**
 * ثبت یک سنجش.
 *
 * @param {boolean} ok    نتیجه.
 * @param {string}  label برچسب.
 * @param {string}  detail جزئیات.
 */
function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + ( detail ? '  → ' + detail : '' ) );
	}
}

/**
 * اختلاف مجاز.
 *
 * @param {number} a   مقدار اول.
 * @param {number} b   مقدار دوم.
 * @param {number} tol تلورانس.
 * @return {boolean} نتیجه.
 */
function near( a, b, tol ) {
	return Math.abs( a - b ) <= tol;
}

/**
 * اندازه‌ها و وضعیت‌های برگه‌ی کشف.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page ) {
	return page.evaluate( () => {
		const box = ( sel ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { x: Math.round( r.x ), y: Math.round( r.y ), w: Math.round( r.width ), h: Math.round( r.height ) };
		};
		const display = ( sel ) => {
			const el = document.querySelector( sel );
			return el ? getComputedStyle( el ).display : null;
		};
		const cards = [ ...document.querySelectorAll( '.manacore-card, .media-card' ) ];
		const rows  = {};
		cards.forEach( ( c ) => {
			const top = Math.round( c.getBoundingClientRect().top );
			rows[ top ] = ( rows[ top ] || 0 ) + 1;
		} );

		return {
			has: {
				toolbar: !!document.querySelector( '.browse-toolbar' ),
				search: !!document.querySelector( '.browse-search' ),
				segmented: !!document.querySelector( '.segmented-control' ),
				sortSelect: !!document.querySelector( '.sort-select' ),
				viewButtons: !!document.querySelector( '.view-buttons' ),
				sidebar: !!document.querySelector( '.filter-sidebar' ),
				results: !!document.querySelector( '.browse-results' ),
				titleRow: !!document.querySelector( '.page-title-row' ),
				breadcrumb: !!document.querySelector( '.breadcrumb' ),
				summary: !!document.querySelector( '.results-summary' ),
				loadMore: !!document.querySelector( '.load-more-zone' ),
			},
			box: {
				toolbar: box( '.browse-toolbar' ),
				sidebar: box( '.filter-sidebar' ),
				results: box( '.browse-results' ),
			},
			display: {
				titleIcon: display( '.page-title-icon' ),
				mobileTrigger: display( '.mobile-filter-trigger' ),
				sidebar: display( '.filter-sidebar' ),
			},
			summaryText: document.querySelector( '.results-summary' )?.textContent.replace( /\s+/g, ' ' ).trim() || null,
			summaryNumber: document.querySelector( '.results-summary b' )?.textContent.trim() || null,
			columnCounts: Object.values( rows ).sort( ( a, b ) => b - a ),
			gridGap: ( () => {
				const grid = document.querySelector( '.manacore-grid-cards, .media-grid' );
				return grid ? getComputedStyle( grid ).gap || getComputedStyle( grid ).columnGap : null;
			} )(),
			typeButtons: [ ...document.querySelectorAll( '.segmented-control button' ) ].map( ( b ) => b.textContent.trim() ),
			activeType: document.querySelector( '.segmented-control button.active' )?.getAttribute( 'data-type' ) || null,
		};
	} );
}

/**
 * سنجش یک نشانی: کارت‌ها، شمار، برچسب‌ها و پنل خالی.
 *
 * @param {import('playwright').Page} page صفحه.
 * @param {string}                    url  نشانی.
 * @return {Promise<Object>} وضعیت.
 */
async function snapshot( page, url ) {
	await page.goto( url, { waitUntil: 'load' } );
	await page.waitForTimeout( 250 );
	return page.evaluate( () => ( {
		ids: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-post-id' ) ),
		/* عنوان‌ها برای سنجش‌های جستجو لازم‌اند تا آزمون به شناسه‌ی نصب وابسته نباشد. */
		titles: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => {
			const t = c.querySelector( '.manacore-card-title' );
			return t ? t.textContent.trim() : '';
		} ),
		types: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-manacore-type' ) ),
		ratings: [ ...document.querySelectorAll( '.manacore-card-rating' ) ].map( ( c ) => parseFloat( c.textContent.replace( /[^\d.,]/g, '' ).replace( ',', '.' ) ) ),
		activeType: document.querySelector( '.segmented-control button.active' )?.getAttribute( 'data-type' ) || null,
		countNumber: document.querySelector( '.results-count-number' )?.textContent.trim() || null,
		chips: [ ...document.querySelectorAll( '.active-filter-chips .manacore-filter-chip' ) ].map( ( c ) => c.textContent.replace( /\s+/g, ' ' ).replace( '×', '' ).trim() ),
		emptiness: document.querySelector( '.empty-state' )?.querySelector( 'h3' )?.textContent.trim() || null,
		emptyReset: !!document.querySelector( '.empty-state a.button' ),
		loadMoreHref: document.querySelector( '[data-manacore-load-more]' )?.getAttribute( 'href' ) || null,
		loadMoreDone: document.querySelector( '.load-more-zone p' )?.textContent.replace( /\s+/g, ' ' ).trim() || null,
		sidebarHiddenType: document.querySelector( '.manacore-filter-form input[name="type"]' )?.getAttribute( 'value' ) || null,
		toolbarHiddenType: document.querySelector( '[data-manacore-browse-toolbar] input[name="type"]' )?.getAttribute( 'value' ) || null,
		toolbarSort: ( () => {
			const sel = document.querySelector( '[data-manacore-browse-toolbar] select[name="mc_sort"]' );
			return sel ? sel.value : null;
		} )(),
	} ) );
}

/**
 * متریک‌های اجزای سایدبار «فیلتر پیشرفته».
 *
 * همه‌ی مقادیر «محاسبه‌شده»‌اند تا با همان عددِ مرجع سنجیده شوند؛ حضور
 * کلاس به‌تنهایی کافی نیست (دامِ «کلاس هست، قاعده نیست»).
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} متریک‌ها.
 */
async function measureSidebar( page ) {
	return page.evaluate( () => {
		const el = ( sel ) => document.querySelector( sel );
		const cs = ( sel, prop ) => {
			const node = el( sel );
			return node ? getComputedStyle( node )[ prop ] : null;
		};
		const box = ( sel ) => {
			const node = el( sel );
			if ( ! node ) {
				return null;
			}
			const r = node.getBoundingClientRect();
			return [ Math.round( r.width ), Math.round( r.height ) ];
		};
		const groups = [ ...document.querySelectorAll( '.filter-group' ) ];

		return {
			has: {
				group: groups.length,
				header: !!el( '.filter-header' ),
				checks: !!el( '.genre-checks' ),
				customCheck: !!el( '.custom-check' ),
				yearRange: !!el( '.year-range' ),
				rating: !!el( '.rating-range' ),
				rangeLabels: !!el( '.range-labels' ),
				toggle: !!el( '.toggle-label' ),
				switch: !!el( '.toggle-switch' ),
				reset: !!el( '.reset-filters' ),
				hint: !!el( '.filter-hint' ),
			},
			metric: {
				panelBg: cs( '.filter-sidebar', 'backgroundColor' ),
				panelPad: cs( '.filter-sidebar', 'padding' ),
				panelRadius: cs( '.filter-sidebar', 'borderRadius' ),
				headerPb: cs( '.filter-header', 'paddingBottom' ),
				h2Font: cs( '.filter-header > h2', 'fontSize' ),
				groupMt: cs( '.filter-group', 'marginTop' ),
				groupPb: cs( '.filter-group', 'paddingBottom' ),
				h3Font: cs( '.filter-group > h3', 'fontSize' ),
				labelFont: cs( '.genre-checks > label', 'fontSize' ),
				labelPad: cs( '.genre-checks > label', 'padding' ),
				checkBox: box( '.custom-check' ),
				smallFont: cs( '.genre-checks small', 'fontSize' ),
				yearSelectFont: cs( '.year-range > select', 'fontSize' ),
				yearSelectW: box( '.year-range > select' ),
				ratingH: box( '.rating-range' )[ 1 ],
				rangeFont: cs( '.range-labels', 'fontSize' ),
				toggleFont: cs( '.toggle-label', 'fontSize' ),
				switchBox: box( '.toggle-switch' ),
				resetFont: cs( '.reset-filters', 'fontSize' ),
				resetPad: cs( '.reset-filters', 'padding' ),
				resetMinH: cs( '.reset-filters', 'minHeight' ),
				hintPad: cs( '.filter-hint', 'padding' ),
				hintRadius: cs( '.filter-hint', 'borderRadius' ),
				/*
				 * متریک‌هایی که پس از «هم‌ترازی ارتفاع خط» و اصلاح ردیف
				 * دکمه‌ها اضافه شدند: بدون این‌ها همان انحراف‌ها می‌توانست
				 * بی‌صدا برگردد (پنل ۱۲۴px بلندتر از مرجع بود).
				 */
				labelLh: cs( '.genre-checks > label', 'lineHeight' ),
				hintH3Mb: cs( '.filter-hint > h3', 'marginBottom' ),
				selectFullFont: cs( '.filter-group select.full', 'fontSize' ),
				selectFullPad: cs( '.filter-group select.full', 'padding' ),
				formDisplay: cs( '.manacore-filter-form', 'display' ),
				formGap: cs( '.manacore-filter-form', 'gap' ),
			},
			panelH: box( '.filter-sidebar' )[ 1 ],
			rowH: box( '.genre-checks > label' )[ 1 ],
			/*
			 * ترتیب گروه‌ها: در مرجع آخرین گروه سایدبار کلید «دوبله» است و
			 * پیش از این در محصول پیش از گزینشگرهای تاکسونومی می‌آمد.
			 */
			lastGroupIsToggle: ( () => {
				const list = document.querySelectorAll( '.filter-group' );
				return !! ( list.length && list[ list.length - 1 ].querySelector( '.toggle-label' ) );
			} )(),
			/* ردیف دکمه‌های فرم در چیدمان سایدبار نباید جعبه بسازد. */
			actionsH: box( '.filter-sidebar .manacore-filter-actions' ),
			counts: [ ...document.querySelectorAll( '.genre-checks small' ) ].map( ( n ) => n.textContent.trim() ),
			hintHref: el( '.filter-hint a' )?.getAttribute( 'href' ) || null,
			resetHref: el( '.reset-filters' )?.getAttribute( 'href' ) || null,
			selects: [ ...document.querySelectorAll( '.filter-group select' ) ].map( ( s ) => s.name ),
			yearOptions: [ ...document.querySelectorAll( '.year-range select' ) ].map( ( s ) => s.options.length ),
		};
	} );
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	try {
		/* ---------------------------------------------------------------
		 * ۱) هم‌سانی چیدمان — دسکتاپ ۱۴۴۰px
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── دسکتاپ ۱۴۴۰px: هم‌سانی با مرجع ─────────────────────' );

		const wpWide  = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
		const refWide = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );

		await wpWide.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 400 );
		await refWide.goto( REF_URL, { waitUntil: 'load' } );
		await refWide.waitForTimeout( 900 );

		const wpM  = await measure( wpWide );
		const refM = await measure( refWide );

		Object.keys( refM.has ).forEach( ( key ) => {
			if ( 'loadMore' === key ) {
				// شمار کارت‌ها در دو طرف یکی نیست، پس حضور ناحیه‌ی «بیشتر»
				// هم‌ارز نیست؛ قراردادش در بخش ۳ سنجیده می‌شود.
				return;
			}
			check( wpM.has[ key ] && refM.has[ key ], 'کلاس «' + key + '» در هر دو طرف هست', JSON.stringify( { wp: wpM.has[ key ], ref: refM.has[ key ] } ) );
		} );

		check( near( wpM.box.toolbar.w, refM.box.toolbar.w, 2 ), 'عرض نوار مرور مثل مرجع', wpM.box.toolbar.w + ' / ' + refM.box.toolbar.w );
		check( near( wpM.box.toolbar.h, refM.box.toolbar.h, 2 ), 'ارتفاع نوار مرور مثل مرجع', wpM.box.toolbar.h + ' / ' + refM.box.toolbar.h );
		check( near( wpM.box.toolbar.x, refM.box.toolbar.x, 2 ), 'فاصله‌ی افقی نوار مرور مثل مرجع', wpM.box.toolbar.x + ' / ' + refM.box.toolbar.x );
		check( near( wpM.box.sidebar.x, refM.box.sidebar.x, 3 ), 'جای سایدبار (راست) مثل مرجع', wpM.box.sidebar.x + ' / ' + refM.box.sidebar.x );
		check( near( wpM.box.sidebar.w, refM.box.sidebar.w, 3 ), 'عرض سایدبار ۲۲۷px مثل مرجع', wpM.box.sidebar.w + ' / ' + refM.box.sidebar.w );
		check( near( wpM.box.results.x, refM.box.results.x, 3 ), 'جای ستون نتایج مثل مرجع', wpM.box.results.x + ' / ' + refM.box.results.x );
		check( near( wpM.box.results.w, refM.box.results.w, 4 ), 'عرض ستون نتایج مثل مرجع', wpM.box.results.w + ' / ' + refM.box.results.w );
		check(
			near( wpM.box.results.y, wpM.box.sidebar.y, 2 ) && near( refM.box.results.y, refM.box.sidebar.y, 2 ),
			'هر دو ستون از یک خط شروع می‌شوند (بدون فاصله‌ی سرگردان)',
			JSON.stringify( { wp: [ wpM.box.results.y, wpM.box.sidebar.y ], ref: [ refM.box.results.y, refM.box.sidebar.y ] } )
		);
		check( wpM.columnCounts[ 0 ] === refM.columnCounts[ 0 ] && 4 === wpM.columnCounts[ 0 ], 'شبکه‌ی نتایج ۴ستونه مثل مرجع', JSON.stringify( wpM.columnCounts ) );
		check( !! wpM.summaryNumber && /^[۰-۹]+$/.test( wpM.summaryNumber ), 'شمار نتایج عددی رندر شده', String( wpM.summaryNumber ) );
		check( wpM.display.titleIcon === refM.display.titleIcon, 'نمایش آیکون سرصفحه مثل مرجع', wpM.display.titleIcon + ' / ' + refM.display.titleIcon );
		check( wpM.typeButtons.length >= 3 && wpM.typeButtons[ 0 ] === EDITOR_TITLES[ 0 ] && wpM.typeButtons[ 1 ] === EDITOR_TITLES[ 1 ], 'دکمه‌های نوع با برچسب مرجع («' + EDITOR_TITLES.join( '، ' ) + '»)', JSON.stringify( wpM.typeButtons ) );

		/* ---------------------------------------------------------------
		 * ۲) هم‌سانی چیدمان — موبایل ۳۹۰px
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── موبایل ۳۹۰px: هم‌سانی با مرجع ──────────────────────' );

		const wpNarrow  = await browser.newPage( { viewport: { width: 390, height: 844 }, locale: 'fa-IR' } );
		const refNarrow = await browser.newPage( { viewport: { width: 390, height: 844 }, locale: 'fa-IR' } );

		await wpNarrow.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await wpNarrow.waitForTimeout( 400 );
		await refNarrow.goto( REF_URL, { waitUntil: 'load' } );
		await refNarrow.waitForTimeout( 900 );

		const wpS  = await measure( wpNarrow );
		const refS = await measure( refNarrow );

		check( 'none' === refS.display.sidebar, 'مرجع: سایدبار در موبایل پنهان است', String( refS.display.sidebar ) );
		check( 'none' === wpS.display.sidebar, 'ما: سایدبار در موبایل پنهان است', String( wpS.display.sidebar ) );
		check( 'flex' === refS.display.mobileTrigger, 'مرجع: دکمه‌ی فیلترها دیده می‌شود', String( refS.display.mobileTrigger ) );
		check( 'flex' === wpS.display.mobileTrigger, 'ما: دکمه‌ی فیلترها دیده می‌شود', String( wpS.display.mobileTrigger ) );
		check( 'none' === wpS.display.titleIcon && 'none' === refS.display.titleIcon, 'آیکون سرصفحه در موبایل پنهان است', wpS.display.titleIcon + ' / ' + refS.display.titleIcon );

		await wpNarrow.click( '#mobile-filter' );
		await wpNarrow.waitForTimeout( 250 );
		const wpOpen = await wpNarrow.evaluate( () => ( {
			display: getComputedStyle( document.querySelector( '.filter-sidebar' ) ).display,
			expanded: document.querySelector( '#mobile-filter' ).getAttribute( 'aria-expanded' ),
		} ) );
		check( 'block' === wpOpen.display && 'true' === wpOpen.expanded, 'کلیک «فیلترها» سایدبار را باز می‌کند (مرجع: کلاس open)', JSON.stringify( wpOpen ) );

		await refNarrow.click( '#mobile-filter' );
		await refNarrow.waitForTimeout( 250 );
		const refOpen = await refNarrow.evaluate( () => getComputedStyle( document.querySelector( '.filter-sidebar' ) ).display );
		check( 'block' === refOpen, 'مرجع هم با همان کلیک باز می‌شود (شاهد)', String( refOpen ) );

		/* ---------------------------------------------------------------
		 * ۲.۵) سایدبار «فیلتر پیشرفته» — هندسه و رفتار
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── سایدبار فیلتر پیشرفته: همان پنج گروه مرجع ────────────' );

		const wpSide  = await measureSidebar( wpWide );
		const refSide = await measureSidebar( refWide );

		/* حضور همان اجزا در دو طرف. */
		Object.keys( refSide.has ).forEach( ( key ) => {
			if ( 'group' === key ) {
				return;
			}
			check(
				wpSide.has[ key ] && refSide.has[ key ],
				'جزء «' + key + '» سایدبار در هر دو طرف هست',
				JSON.stringify( { wp: wpSide.has[ key ], ref: refSide.has[ key ] } )
			);
		} );

		/*
		 * شمار گروه‌های مرجع ۵ است (ژانر، سال، امتیاز، کشور، دوبله). گروه
		 * ششمِ احتمالی در محصول فقط اگر نویسنده تاکسونومی تازه‌ای اضافه
		 * کند می‌آید، پس «کمینه» سنجیده می‌شود نه برابری عددی.
		 */
		check( wpSide.has.group >= 5 && refSide.has.group >= 5, 'شمار گروه‌های سایدبار در حد مرجع', wpSide.has.group + ' / ' + refSide.has.group );

		/* متریک‌های محاسبه‌شده در برابر همان مقدار مرجع. */
		const metricPairs = [
			[ 'panelBg', 'زمینه‌ی پنل سایدبار' ],
			[ 'panelPad', 'پدینگ پنل' ],
			[ 'panelRadius', 'شعاع پنل' ],
			[ 'headerPb', 'پدینگ پایین سرصفحه‌ی سایدبار' ],
			[ 'h2Font', 'اندازه‌ی قلم عنوان سایدبار' ],
			[ 'groupMt', 'فاصله‌ی بالای گروه' ],
			[ 'groupPb', 'پدینگ پایین گروه' ],
			[ 'h3Font', 'اندازه‌ی قلم سرتیتر گروه' ],
			[ 'labelFont', 'اندازه‌ی قلم برچسب ژانر' ],
			[ 'labelPad', 'پدینگ برچسب ژانر' ],
			[ 'smallFont', 'اندازه‌ی قلم شمار ژانر' ],
			[ 'yearSelectFont', 'اندازه‌ی قلم گزینشگر سال' ],
			[ 'rangeFont', 'اندازه‌ی قلم برچسب‌های بازه' ],
			[ 'toggleFont', 'اندازه‌ی قلم کلید دوبله' ],
			[ 'resetFont', 'اندازه‌ی قلم دکمه‌ی پاک‌سازی' ],
			[ 'resetPad', 'پدینگ دکمه‌ی پاک‌سازی' ],
			[ 'resetMinH', 'کمینه‌ی ارتفاع دکمه‌ی پاک‌سازی' ],
			[ 'hintPad', 'پدینگ پنل راهنما' ],
			[ 'hintRadius', 'شعاع پنل راهنما' ],
		];

		metricPairs.forEach( ( [ key, label ] ) => {
			check( wpSide.metric[ key ] === refSide.metric[ key ], label + ' مثل مرجع', wpSide.metric[ key ] + ' / ' + refSide.metric[ key ] );
		} );

		check( JSON.stringify( wpSide.metric.checkBox ) === JSON.stringify( refSide.metric.checkBox ), 'اندازه‌ی مربع تیک ژانر ۱۲×۱۲ مثل مرجع', JSON.stringify( [ wpSide.metric.checkBox, refSide.metric.checkBox ] ) );
		check( near( wpSide.metric.ratingH, refSide.metric.ratingH, 1 ), 'ارتفاع لغزنده‌ی امتیاز مثل مرجع', wpSide.metric.ratingH + ' / ' + refSide.metric.ratingH );
		check( JSON.stringify( wpSide.metric.switchBox ) === JSON.stringify( refSide.metric.switchBox ), 'اندازه‌ی کلید دوبله ۳۴×۱۹ مثل مرجع', JSON.stringify( [ wpSide.metric.switchBox, refSide.metric.switchBox ] ) );
		check( near( wpSide.metric.yearSelectW[ 0 ], refSide.metric.yearSelectW[ 0 ], 2 ), 'عرض گزینشگر سال مثل مرجع', JSON.stringify( [ wpSide.metric.yearSelectW, refSide.metric.yearSelectW ] ) );

		/* متریک‌های تازه‌ی هم‌ترازی. */
		check(
			wpSide.metric.labelLh === refSide.metric.labelLh,
			'ارتفاع خط برچسب ژانر مثل مرجع (پنل را بلندتر نمی‌کند)',
			wpSide.metric.labelLh + ' / ' + refSide.metric.labelLh
		);
		check(
			wpSide.metric.hintH3Mb === refSide.metric.hintH3Mb,
			'حاشیه‌ی پایین سرتیتر پنل راهنما مثل مرجع',
			wpSide.metric.hintH3Mb + ' / ' + refSide.metric.hintH3Mb
		);
		check(
			wpSide.metric.selectFullFont === refSide.metric.selectFullFont &&
				wpSide.metric.selectFullPad === refSide.metric.selectFullPad,
			'گزینشگر تاکسونومی همان اندازه/پدینگ سراسری مرجع را دارد',
			wpSide.metric.selectFullFont + ' ' + wpSide.metric.selectFullPad + ' / ' + refSide.metric.selectFullFont + ' ' + refSide.metric.selectFullPad
		);
		/*
		 * مرجع `` ندارد (ستون همان `aside` است)، پس این‌جا برابری با
		 * مرجع معنا ندارد؛ مقدار درست، همان چیزی است که چیدمان مرجع
		 * می‌سازد: یک ستون ساده بدون `gap`.
		 */
		check(
			'block' === wpSide.metric.formDisplay && '0px' === wpSide.metric.formGap,
			'فرم سایدبار یک‌ستونی است و فاصله‌ی اضافه‌ی شبکه ندارد',
			wpSide.metric.formDisplay + ' / gap ' + wpSide.metric.formGap
		);
		check(
			wpSide.lastGroupIsToggle && refSide.lastGroupIsToggle,
			'ترتیب گروه‌ها مثل مرجع (کلید دوبله آخرین گروه است)',
			JSON.stringify( { wp: wpSide.lastGroupIsToggle, ref: refSide.lastGroupIsToggle } )
		);
		check(
			! wpSide.actionsH || 0 === wpSide.actionsH[ 1 ],
			'ردیف دکمه‌های فرم در سایدبار جعبه نمی‌سازد (فقط نسخه‌ی بی‌جاوااسکریپت)',
			JSON.stringify( wpSide.actionsH )
		);

		/*
		 * ارتفاع پنل: تفاضل دو طرف فقط باید از «شمار ژانرها» بیاید. هر ژانر
		 * یک ردیف است، پس اختلاف شمار ردیف‌ها از ارتفاع مرجع کم می‌شود.
		 */
		const expectedPanel = refSide.panelH - ( refSide.counts.length - wpSide.counts.length ) * refSide.rowH;
		check(
			near( wpSide.panelH, expectedPanel, 4 ),
			'ارتفاع پنل سایدبار با مرجع یکی است (به‌جز تفاضل داده‌ی ژانرها)',
			wpSide.panelH + ' / ' + expectedPanel + ' (مرجع ' + refSide.panelH + '، ردیف ' + refSide.rowH + ')'
		);

		/* شمارها باید رقم فارسی و از داده باشند (نه صفر یکدست). */
		check( wpSide.counts.length >= 8, 'شمار آثار کنار ژانرها رندر شده', wpSide.counts.length + ' ژانر' );
		check( wpSide.counts.every( ( c ) => /^[۰-۹]+$/.test( c ) ), 'شمار ژانرها با ارقام فارسی', wpSide.counts.slice( 0, 4 ).join( '، ' ) );
		check( wpSide.counts.some( ( c ) => c !== '۰' ), 'شمارها از داده‌ی واقعی می‌آیند (دست‌کم یک ژانر غیرصفر)', wpSide.counts.join( '، ' ) );

		check( wpSide.selects.includes( 'mc_year_min' ) && wpSide.selects.includes( 'mc_year_max' ), 'گزینشگرهای بازه‌ی سال نام درست دارند', JSON.stringify( wpSide.selects ) );
		check( wpSide.yearOptions.every( ( n ) => n > 10 ), 'گزینشگرهای سال از داده پر می‌شوند', JSON.stringify( wpSide.yearOptions ) );
		check( /mc_sort=rating/.test( wpSide.hintHref || '' ) && /mc_rating_min=8/.test( wpSide.hintHref || '' ), 'پیوند «شاهکارها را ببین» همان رفتار مرجع را می‌برد', String( wpSide.hintHref ) );
		check( !! wpSide.resetHref, 'دکمه‌ی پاک کردن فیلترها مقصد دارد', String( wpSide.resetHref ) );
		const dubbedInput = await wpWide.evaluate( () => {
			const input = document.querySelector( '.toggle-label > input' );
			return input ? { type: input.type, name: input.name, value: input.value } : null;
		} );
		check(
			!! dubbedInput && 'checkbox' === dubbedInput.type && 'mc_dubbed' === dubbedInput.name,
			'کلید دوبله یک checkbox واقعی با نام پارامتر درست است',
			JSON.stringify( dubbedInput )
		);

		/* رفتار: تیک ژانر → فهرست کوچک‌تر و برچسب فیلتر تازه. */
		const beforeGenre = await wpWide.evaluate( () => document.querySelectorAll( '.manacore-card' ).length );
		await wpWide.check( '.genre-checks label:nth-child(1) input' );
		await wpWide.waitForLoadState( 'load' );
		await wpWide.waitForTimeout( 300 );
		const afterGenre = await wpWide.evaluate( () => ( {
			count: document.querySelectorAll( '.manacore-card' ).length,
			chips: [ ...document.querySelectorAll( '.active-filter-chips .manacore-filter-chip' ) ].map( ( c ) => c.textContent.replace( /\s+/g, ' ' ).replace( '×', '' ).trim() ),
			checked: !!document.querySelector( '.genre-checks input:checked' ),
		} ) );
		check( afterGenre.count < beforeGenre && afterGenre.count > 0, 'تیک ژانر فهرست را پالایش می‌کند', beforeGenre + ' → ' + afterGenre.count );
		check( afterGenre.checked, 'تیک ژانر پس از ارسال هم تیک‌خورده می‌ماند', JSON.stringify( afterGenre.checked ) );
		check( afterGenre.chips.length >= 1, 'تیک ژانر برچسب فیلتر فعال می‌سازد', JSON.stringify( afterGenre.chips ) );

		/* رفتار: لغزنده‌ی امتیاز → فقط آثار بالای حد. */
		await wpWide.goto( WP_URL + 'browse/?mc_rating_min=9', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 300 );
		const ratingState = await wpWide.evaluate( () => ( {
			ids: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-post-id' ) ),
			ratings: [ ...document.querySelectorAll( '.manacore-card-rating' ) ].map( ( c ) => parseFloat( c.textContent.replace( /[^\d.]/g, '' ) ) ),
			label: document.querySelector( '#rating-label' )?.textContent.trim() || null,
			value: document.querySelector( '#min-rating' )?.value || null,
		} ) );
		check( ratingState.ratings.length > 0 && ratingState.ratings.every( ( r ) => r >= 9 ), 'لغزنده‌ی امتیاز فقط آثار ۹+ را نشان می‌دهد', JSON.stringify( ratingState.ratings ) );
		check( '9' === ratingState.value, 'مقدار لغزنده از نشانی خوانده می‌شود', String( ratingState.value ) );
		check( '۹+' === ratingState.label || '9+' === ratingState.label, 'برچسب امتیاز همان قرارداد مرجع (عدد + +) را دارد', String( ratingState.label ) );

		const wpLiveRating = await wpWide.evaluate( async () => {
			const range = document.querySelector( '#min-rating' );
			range.value = 8;
			range.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			return document.querySelector( '#rating-label' ).textContent.trim();
		} );
		check( '۸+' === wpLiveRating || '8+' === wpLiveRating, 'کشیدن لغزنده برچسب را بی‌درنگ به‌روز می‌کند', String( wpLiveRating ) );

		/*
		 * رفتار: کلید دوبله → زیرمجموعه‌ای از همان فهرست.
		 *
		 * پیش‌تر این‌جا عدد ثابت ۵ بود و با افزودن یک اثر به داده‌ی آزمون
		 * شکست. سنجش درست «زیرمجموعه‌بودن» است، نه شمار ثابت: فهرست
		 * کامل خوانده می‌شود و بعد همان فهرست با کلید دوبله.
		 */
		await wpWide.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 300 );
		const allIds = await wpWide.evaluate( () => [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-post-id' ) ) );

		await wpWide.goto( WP_URL + 'browse/?mc_dubbed=1', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 300 );
		const dubbed = await wpWide.evaluate( () => ( {
			ids: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-post-id' ) ),
			checked: document.querySelector( '.toggle-label > input' )?.checked || false,
			count: document.querySelector( '.results-count-number' )?.textContent.trim() || null,
		} ) );
		check(
			dubbed.ids.length > 0 && dubbed.ids.length < allIds.length && dubbed.ids.every( ( id ) => allIds.includes( id ) ),
			'کلید دوبله فهرست را به زیرمجموعه‌ی آثار دوبله‌دار محدود می‌کند',
			JSON.stringify( { dubbed: dubbed.ids, all: allIds } )
		);
		check( dubbed.checked, 'کلید دوبله در نشانی، تیک‌خورده رندر می‌شود', String( dubbed.checked ) );
		check( /^[۰-۹]+$/.test( dubbed.count || '' ), 'شمار نتایج با فیلتر دوبله به‌روز می‌شود', String( dubbed.count ) );

		/* رفتار: بازه‌ی سال. */
		await wpWide.goto( WP_URL + 'browse/?mc_year_min=2020&mc_year_max=2026', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 300 );
		const years = await wpWide.evaluate( () => ( {
			ids: [ ...document.querySelectorAll( '.manacore-card' ) ].map( ( c ) => c.getAttribute( 'data-post-id' ) ),
			min: document.querySelector( 'select[name="mc_year_min"]' )?.value || null,
			max: document.querySelector( 'select[name="mc_year_max"]' )?.value || null,
		} ) );
		check( years.ids.length > 0 && years.ids.length < 5, 'بازه‌ی سال فهرست را محدود می‌کند', JSON.stringify( years.ids ) );
		check( '2020' === years.min && '2026' === years.max, 'گزینشگرهای سال مقدار نشانی را نشان می‌دهند', years.min + ' / ' + years.max );

		/* رفتار: برداشتن برچسب فیلتر فراداده‌ای. */
		const metaChipHref = await wpWide.evaluate( () => document.querySelector( '.active-filter-chips .manacore-filter-chip' )?.getAttribute( 'href' ) || null );
		check( !! metaChipHref && ! /mc_year_min|mc_year_max/.test( metaChipHref ), 'برچسب حذف فیلتر، خودش را از نشانی برمی‌دارد', String( metaChipHref ) );

		/* و دوباره به حالت بی‌فیلتر برمی‌گردیم تا بخش‌های بعدی دست‌نخورده بمانند. */
		await wpWide.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await wpWide.waitForTimeout( 250 );

		/* ---------------------------------------------------------------
		 * ۳) رفتار سمت سرور
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── رفتار سمت سرور (صافی نوع، مرتب‌سازی، جستجو) ─────────' );

		const page = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

		const base = await snapshot( page, WP_URL + 'browse/' );
		check( base.ids.length > 0 && 'all' === base.activeType, 'نمای پیش‌فرض: دکمه‌ی «همه» فعال', JSON.stringify( { n: base.ids.length, active: base.activeType } ) );

		for ( const type of [ 'movie', 'series', 'anime' ] ) {
			const s = await snapshot( page, WP_URL + 'browse/?type=' + type );
			check(
				s.types.length > 0 && s.types.every( ( t ) => t === type ),
				'`?type=' + type + '` فهرست را صال می‌کند',
				JSON.stringify( s.types )
			);
			check( type === s.activeType, '`?type=' + type + '` دکمه‌ی همان نوع را فعال می‌کند', String( s.activeType ) );
			check( s.countNumber === toFa( s.ids.length ), 'شمار نتایج با شمار کارت‌ها یکی است (' + type + ')', s.countNumber + ' / ' + s.ids.length );
		}

		const rated = await snapshot( page, WP_URL + 'browse/?mc_sort=rating' );
		const sortedDesc = rated.ratings.every( ( v, i ) => 0 === i || rated.ratings[ i - 1 ] >= v );
		check( sortedDesc && rated.ratings.length > 1, '`?mc_sort=rating` نزولی بر امتیاز مرتب می‌کند', JSON.stringify( rated.ratings ) );
		check( rated.chips.some( ( c ) => /بیشترین امتیاز|بالاترین امتیاز/.test( c ) ), 'برچسب «مرتب‌سازی» در فیلترهای فعال می‌آید', JSON.stringify( rated.chips ) );

		const searched = await snapshot( page, WP_URL + 'browse/?manacore_q=chernobyl' );
		/*
		 * سنجش روی **عنوان** انجام می‌شود، نه شناسه: شناسه‌ی نوشته‌ها به
		 * ترتیب نصب تازه‌ی محیط QA بستگی دارد و آزمون را شکننده می‌کرد.
		 */
		check( 1 === searched.ids.length && /چرنوبیل/.test( searched.titles[ 0 ] ), '`?manacore_q=chernobyl` تنها «چرنوبیل» را می‌آورد', JSON.stringify( searched.titles ) );
		check( searched.chips.some( ( c ) => /chernobyl/.test( c ) ), 'برچسب جستجو در فیلترهای فعال می‌آید', JSON.stringify( searched.chips ) );

		const latin = await snapshot( page, WP_URL + 'browse/?manacore_q=shogun' );
		check( 1 === latin.ids.length && /شوگان/.test( latin.titles[ 0 ] ), 'جستجوی نام اصلی (Shōgun) هم اثر را پیدا می‌کند', JSON.stringify( latin.titles ) );

		const genre = await snapshot( page, WP_URL + 'browse/?mc_genre=' + encodeURIComponent( 'اکشن' ) );
		check( 1 === genre.ids.length, 'فیلتر ژانر از سایدبار نتیجه را محدود می‌کند', JSON.stringify( genre.ids ) );
		check( genre.chips.length >= 1, 'برچسب فیلتر فعال در ستون نتایج رندر می‌شود', JSON.stringify( genre.chips ) );

		const empty = await snapshot( page, WP_URL + 'browse/?mc_genre=zzzz' );
		check( 'با این فیلترها داستانی پیدا نشد.' === empty.emptiness, 'حالت خالی پیام مرجع را دارد', String( empty.emptiness ) );
		check( empty.emptyReset && '۰' === empty.countNumber, 'حالت خالی راه بازگشت و شمار ۰ دارد', JSON.stringify( { reset: empty.emptyReset, count: empty.countNumber } ) );

		const beyond = await snapshot( page, WP_URL + 'browse/?paged=2' );
		check( 'این صفحه دیگر داستانی ندارد.' === beyond.emptiness, 'صفحه‌ی بیرون از محدوده پیام درست می‌دهد', String( beyond.emptiness ) );

		const keep = await snapshot( page, WP_URL + 'browse/?type=series&mc_sort=title' );
		check( 'series' === keep.toolbarHiddenType && 'series' === keep.sidebarHiddenType, 'صافی نوع هم در نوار مرور و هم در فرم سایدبار حفظ می‌شود', JSON.stringify( { toolbar: keep.toolbarHiddenType, sidebar: keep.sidebarHiddenType } ) );
		check( 'title' === keep.toolbarSort, 'گزینشگر مرتب‌سازی همان گزینه‌ی نشانی را نشان می‌دهد', String( keep.toolbarSort ) );

		/* ---------------------------------------------------------------
		 * ۴) رفتار سمت کاربر
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── رفتار سمت کاربر (دکمه‌ها و «داستان‌های بیشتر») ───────' );

		await page.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await page.waitForTimeout( 300 );

		await page.click( '.view-buttons [data-mode="list"]' );
		await page.waitForTimeout( 200 );
		const listMode = await page.evaluate( () => {
			const grid = document.querySelector( '.manacore-grid-cards' );
			const card = document.querySelector( '.manacore-card' );
			return {
				cls: grid.className.includes( 'list-layout' ),
				display: getComputedStyle( card ).display,
				columns: getComputedStyle( grid ).gridTemplateColumns.split( ' ' ).length,
			};
		} );
		check( listMode.cls && 'flex' === listMode.display && 2 === listMode.columns, 'حالت فهرستی شبکه را دوستونه‌ی افقی می‌کند', JSON.stringify( listMode ) );

		await page.click( '.view-buttons [data-mode="grid"]' );
		await page.waitForTimeout( 200 );
		check( await page.evaluate( () => ! document.querySelector( '.manacore-grid-cards' ).className.includes( 'list-layout' ) ), 'برگشت به حالت شبکه‌ای کار می‌کند', '' );

		await page.click( '.segmented-control button[data-type="series"]' );
		await page.waitForLoadState( 'load' );
		await page.waitForTimeout( 300 );
		check( /[?&]type=series/.test( page.url() ) && 'series' === ( await snapshot( page, page.url() ) ).activeType, 'کلیک دکمه‌ی نوع، نشانی و نتیجه را عوض می‌کند', page.url() );

		await page.goto( WP_URL + 'browse/?mc_genre=' + encodeURIComponent( 'اکشن' ), { waitUntil: 'load' } );
		await page.waitForTimeout( 250 );
		const chipHref = await page.getAttribute( '.active-filter-chips .manacore-filter-chip', 'href' );
		await page.click( '.active-filter-chips .manacore-filter-chip' );
		await page.waitForLoadState( 'load' );
		await page.waitForTimeout( 250 );
		const afterChip = await page.evaluate( () => ( { url: location.search, cards: document.querySelectorAll( '.manacore-card' ).length } ) );
		check( ! /mc_genre/.test( afterChip.url ) && afterChip.cards > 1, 'برداشتن برچسب فیلتر، فیلتر را حذف می‌کند', JSON.stringify( { href: chipHref, after: afterChip } ) );

		await page.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
		await page.waitForTimeout( 250 );
		await page.fill( '#browse-search', 'chernobyl' );
		await page.waitForLoadState( 'load' );
		await page.waitForTimeout( 1200 );
		const searchState = await page.evaluate( () => ( { url: location.search, cards: document.querySelectorAll( '.manacore-card' ).length } ) );
		check( /manacore_q=chernobyl/.test( searchState.url ) && 1 === searchState.cards, 'جستجوی تأخیری نوار مرور نتیجه را می‌آورد', JSON.stringify( searchState ) );

		/* «داستان‌های بیشتر» روی برگه‌ی آزمون صفحه‌بندی‌شده. */
		await page.goto( WP_URL + 'qa-browse/', { waitUntil: 'load' } );
		await page.waitForTimeout( 300 );
		const beforeMore = await page.evaluate( () => ( {
			/* عدد از خود صفحه خوانده می‌شود؛ داده‌ی آزمون ممکن است بزرگ‌تر شود. */
			total: parseInt( ( document.querySelector( '.results-count-number' )?.textContent.trim() || '0' ).replace( /[۰-۹]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ), 10 ),
			perPage: document.querySelectorAll( '.manacore-card' ).length,
			href: document.querySelector( '[data-manacore-load-more]' )?.getAttribute( 'href' ) || null,
			tag: document.querySelector( '.load-more-zone > *' )?.tagName || null,
			text: document.querySelector( '[data-manacore-load-more]' )?.textContent.trim() || null,
		} ) );
		check( 4 === beforeMore.perPage && /paged=2$/.test( beforeMore.href || '' ) && /داستان‌های بیشتر/.test( beforeMore.text || '' ), 'ناحیه‌ی «داستان‌های بیشتر» با پیوند صفحه‌ی بعد رندر می‌شود', JSON.stringify( beforeMore ) );

		await page.click( '[data-manacore-load-more]' );
		await page.waitForTimeout( 1500 );
		const afterMore = await page.evaluate( () => ( {
			cards: document.querySelectorAll( '.manacore-card' ).length,
			done: document.querySelector( '.load-more-zone p' )?.textContent.replace( /\s+/g, ' ' ).trim() || null,
			link: !! document.querySelector( '[data-manacore-load-more]' ),
			count: document.querySelector( '.results-count-number' )?.textContent.trim() || null,
		} ) );
		const faTotal = String( beforeMore.total ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ +d ] );
		check(
			beforeMore.total === afterMore.cards && ! afterMore.link && afterMore.done.includes( faTotal ),
			'کلیک، کارت‌ها را می‌چسباند و پیام پایان را می‌گذارد',
			JSON.stringify( { total: beforeMore.total, after: afterMore } )
		);
		check( faTotal === afterMore.count, 'شمار کل پس از «بیشتر» ثابت می‌ماند', JSON.stringify( { before: faTotal, after: afterMore.count } ) );

		check( 0 === errors.length, 'خطای اجرای جاوااسکریپت در مسیر آزمون‌ها نیست', JSON.stringify( errors.slice( 0, 3 ) ) );

		/* ---------------------------------------------------------------
		 * ۵) دسترس‌پذیری
		 * ------------------------------------------------------------ */
		console.log( '' );
		console.log( '── دسترس‌پذیری (axe) ──────────────────────────────────' );

		if ( axePath ) {
			await page.goto( WP_URL + 'browse/', { waitUntil: 'load' } );
			await page.waitForTimeout( 400 );
			await page.addScriptTag( { path: axePath } );
			const axe = await page.evaluate( async () => await window.axe.run( document, { resultTypes: [ 'violations' ] } ) );
			check( 0 === axe.violations.length, 'برگه‌ی کشف بدون نقض دسترس‌پذیری', JSON.stringify( axe.violations.map( ( v ) => v.id ) ) );
		} else {
			console.log( '  ! axe-core نصب نبود؛ بررسی دسترس‌پذیری اجرا نشد.' );
		}
	} finally {
		await browser.close();
	}

	console.log( '' );
	console.log( '==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )();

/**
 * تبدیل عدد لاتین به رقم فارسی (همان کاری که `manacore_fa_digits` می‌کند).
 *
 * @param {number} value عدد.
 * @return {string} رقم فارسی.
 */
function toFa( value ) {
	return String( value ).replace( /[0-9]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ Number( d ) ] );
}

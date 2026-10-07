/**
 * آزمون هم‌سانی کشوی منوی موبایل با مرجع «سینورا».
 *
 * اعداد داخل این فایل از مرجع زنده سنجیده شده‌اند
 * (`cinora/index.html` در ۳۹۰px و ۷۶۸px و ۱۰۲۴px) و این‌جا به‌عنوان
 * «قرارداد» ثبت شده‌اند: هر تغییری که اندازه‌ی کشو، پس‌زمینه، ردیف‌ها،
 * ردیف جستجو، دکمه‌ی اشتراک یا رفتار قفل پیمایش را از مرجع دور کند،
 * این آزمون شکست می‌خورد.
 *
 * اجرا (از پوشه‌ی wp/tests/browser که node_modules در آن لینک است):
 *   node drawer-parity.cjs
 * با نشانی دلخواه:
 *   WP_URL=http://localhost:8099/ node drawer-parity.cjs
 *   REF_URL=http://localhost:8098/index.html node drawer-parity.cjs   (diff زنده، اختیاری)
 *
 * @package KooheFilm
 */

'use strict';

let chromium;

try {
	( { chromium } = require( 'playwright' ) );
} catch ( error ) {
	console.error( 'playwright نصب نیست؛ این آزمون اجرا نشد.' );
	console.error( error.message );
	process.exit( 2 );
}

const WP_URL  = process.env.WP_URL || 'http://localhost:8099/';
const REF_URL = process.env.REF_URL || 'http://localhost:8098/index.html';

let pass = 0;
let fail = 0;

/**
 * سنجش وضعیت باز‌شده‌ی کشو در یک صفحه.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page ) {
	return page.evaluate( () => {
		const box = ( el ) => {
			const rect = el.getBoundingClientRect();
			return {
				x: Math.round( rect.x ),
				y: Math.round( rect.y ),
				w: Math.round( rect.width ),
				h: Math.round( rect.height ),
			};
		};
		const css = ( el, props ) => {
			const style = getComputedStyle( el );
			const out = {};
			props.forEach( ( prop ) => {
				out[ prop ] = style[ prop ];
			} );
			return out;
		};

		const backdrop = document.querySelector( '.drawer-backdrop' );
		const drawer   = document.querySelector( '.mobile-drawer' );
		const top      = document.querySelector( '.drawer-top' );
		const close    = document.querySelector( '[data-mobile-close]' );
		const search   = document.querySelector( '.mobile-drawer .header-search' );
		const rows     = Array.from( document.querySelectorAll( '.mobile-drawer nav a' ) );
		const cta      = document.querySelector( '.mobile-drawer > .button' );
		const note     = document.querySelector( '.mobile-drawer > .muted' );
		const chevron  = rows.length ? rows[ 0 ].querySelector( 'svg' ) : null;
		const header   = document.querySelector( '.koohe-header' ) || document.querySelector( '.site-header' );
		const toggle   = document.querySelector( '[data-mobile-open]' );
		const desktop  = document.querySelector( '.koohe-nav' ) || document.querySelector( '.desktop-nav' );

		return {
			backdrop: backdrop ? Object.assign( box( backdrop ), css( backdrop, [ 'position', 'zIndex', 'backgroundColor', 'backdropFilter' ] ) ) : null,
			drawer: drawer ? Object.assign( box( drawer ), css( drawer, [ 'position', 'backgroundColor', 'padding', 'animationName', 'animationDuration', 'width' ] ) ) : null,
			top: top ? css( top, [ 'marginBottom', 'justifyContent' ] ) : null,
			close: close ? Object.assign( box( close ), css( close, [ 'borderRadius', 'color' ] ) ) : null,
			search: search ? Object.assign( box( search ), css( search, [ 'fontSize', 'color', 'justifyContent', 'backgroundColor', 'borderTopWidth' ] ) ) : null,
			row: rows.length ? Object.assign( box( rows[ 0 ] ), css( rows[ 0 ], [ 'padding', 'fontSize', 'justifyContent', 'borderBottom', 'color' ] ) ) : null,
			rowCount: rows.length,
			chevron: chevron ? box( chevron ) : null,
			cta: cta ? Object.assign( box( cta ), css( cta, [ 'backgroundColor', 'color', 'fontSize', 'padding', 'borderRadius' ] ) ) : null,
			note: note ? css( note, [ 'fontSize', 'color', 'marginTop', 'textAlign' ] ) : null,
			headerHeight: header ? box( header ).h : 0,
			toggle: toggle ? Object.assign( box( toggle ), { display: getComputedStyle( toggle ).display } ) : null,
			toggleDisplay: toggle ? getComputedStyle( toggle ).display : null,
			brand: ( () => {
				const el = document.querySelector( '.koohe-header-start .koohe-brand' ) || document.querySelector( '.header-inner .brand' );
				return el ? box( el ) : null;
			} )(),
			desktopDisplay: desktop ? getComputedStyle( desktop ).display : null,
			bodyClass: document.body.className,
			bodyOverflow: getComputedStyle( document.body ).overflow,
			bodyInline: document.body.getAttribute( 'style' ) || '',
			scrollWidth: document.documentElement.scrollWidth,
			clientWidth: document.documentElement.clientWidth,
		};
	} );
}

/**
 * باز کردن کشو.
 *
 * @param {import('playwright').Page} page صفحه.
 * @param {boolean}                   withCssRef آیا نرخ انیمیشن باید نادیده گرفته شود.
 */
async function openDrawer( page ) {
	const toggle = await page.$( '[data-mobile-open]' );

	if ( ! toggle ) {
		throw new Error( 'دکمه‌ی همبرگری [data-mobile-open] پیدا نشد.' );
	}

	await toggle.click();
	await page.waitForTimeout( 450 );
}

function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + ( detail ? '   → ' + detail : '' ) );
	}
}

function near( value, expected, tolerance ) {
	return Math.abs( Number( value ) - Number( expected ) ) <= tolerance;
}

/**
 * مقایسه‌ی سنجه‌های سایت با قرارداد مرجع.
 *
 * @param {Object} m    سنجه‌ها.
 * @param {number} width عرض viewport.
 */
function compareWithReference( m, width ) {
	const expectedWidth = Math.min( 345, Math.round( width * 0.88 ) );

	check( m.backdrop && 'fixed' === m.backdrop.position, 'پس‌زمینه: position: fixed' );
	check( m.backdrop && near( m.backdrop.w, width, 1 ) && near( m.backdrop.h, 844, 1 ), 'پس‌زمینه تمام‌صفحه است', m.backdrop && JSON.stringify( m.backdrop ) );
	check( m.backdrop && 'rgba(0, 0, 0, 0.6)' === m.backdrop.backgroundColor, 'پس‌زمینه: rgba(0,0,0,.6)', m.backdrop && m.backdrop.backgroundColor );
	check( m.backdrop && 'blur(5px)' === m.backdrop.backdropFilter, 'پس‌زمینه: backdrop-filter: blur(5px)', m.backdrop && m.backdrop.backdropFilter );
	check( m.backdrop && '100' === m.backdrop.zIndex, 'پس‌زمینه: z-index 100', m.backdrop && m.backdrop.zIndex );

	check( m.drawer && 'absolute' === m.drawer.position, 'کشو: position: absolute' );
	check( m.drawer && near( m.drawer.w, expectedWidth, 1 ), 'پهنای کشو: min(345px, 88vw) = ' + expectedWidth, m.drawer && String( m.drawer.w ) );
	check( m.drawer && near( m.drawer.x, width - expectedWidth, 1 ), 'کشو از لبه‌ی راست: x=' + ( width - expectedWidth ), m.drawer && String( m.drawer.x ) );
	check( m.drawer && near( m.drawer.h, 844, 2 ), 'ارتفاع کشو: تمام‌قد', m.drawer && String( m.drawer.h ) );
	check( m.drawer && '24px' === m.drawer.padding, 'پدینگ کشو: 24px', m.drawer && m.drawer.padding );
	check( m.drawer && 'drawerIn' === m.drawer.animationName, 'انیمیشن: drawerIn', m.drawer && m.drawer.animationName );
	check( m.drawer && '0.25s' === m.drawer.animationDuration, 'مدت انیمیشن: 0.25s', m.drawer && m.drawer.animationDuration );

	check( m.top && '30px' === m.top.marginBottom, 'فاصله‌ی سر کشو: margin-bottom 30px', m.top && m.top.marginBottom );
	check( m.close && near( m.close.w, 36, 1 ) && near( m.close.h, 36, 1 ), 'دکمه‌ی بستن: ۳۶×۳۶', m.close && JSON.stringify( m.close ) );
	check( m.close && '7px' === m.close.borderRadius, 'دکمه‌ی بستن: radius 7', m.close && m.close.borderRadius );

	check( m.search && near( m.search.h, 42, 1 ), 'ردیف جستجو: ارتفاع ۴۲', m.search && String( m.search.h ) );
	check( m.search && '12px' === m.search.fontSize, 'ردیف جستجو: font 12', m.search && m.search.fontSize );
	check( m.search && 'center' === m.search.justifyContent, 'ردیف جستجو: تراز وسط', m.search && m.search.justifyContent );
	check( m.search && '0px' === m.search.borderTopWidth && 'rgba(0, 0, 0, 0)' === m.search.backgroundColor, 'ردیف جستجو: بدون مرز و بدون پس‌زمینه', m.search && m.search.borderTopWidth + ' / ' + m.search.backgroundColor );

	check( m.row && near( m.row.h, 53, 1 ), 'ردیف فهرست: ارتفاع ۵۳', m.row && String( m.row.h ) );
	check( m.row && '16px 5px' === m.row.padding, 'ردیف فهرست: padding 16px 5px', m.row && m.row.padding );
	check( m.row && '13px' === m.row.fontSize, 'ردیف فهرست: font 13', m.row && m.row.fontSize );
	check( m.row && 'space-between' === m.row.justifyContent, 'ردیف فهرست: space-between', m.row && m.row.justifyContent );
	check( m.row && /^1px solid rgb\(41, 48, 57\)$/.test( m.row.borderBottom ), 'ردیف فهرست: مرز ۱px رنگ --border', m.row && m.row.borderBottom );
	check( m.chevron && near( m.chevron.w, 17, 1 ) && near( m.chevron.h, 17, 1 ), 'شِوران ردیف: ۱۷×۱۷', m.chevron && JSON.stringify( m.chevron ) );

	check( m.cta && near( m.cta.h, 43, 1 ), 'دکمه‌ی اشتراک: ارتفاع ۴۳', m.cta && String( m.cta.h ) );
	check( m.cta && 'rgb(198, 237, 123)' === m.cta.backgroundColor, 'دکمه‌ی اشتراک: پس‌زمینه‌ی accent', m.cta && m.cta.backgroundColor );
	check( m.cta && 'rgb(27, 39, 18)' === m.cta.color, 'دکمه‌ی اشتراک: متن accent-contrast', m.cta && m.cta.color );
	check( m.cta && '11px 19px' === m.cta.padding, 'دکمه‌ی اشتراک: padding 11px 19px', m.cta && m.cta.padding );
	check( m.cta && '7px' === m.cta.borderRadius, 'دکمه‌ی اشتراک: radius 7', m.cta && m.cta.borderRadius );
	check( m.cta && near( m.cta.w, expectedWidth - 48, 1 ), 'دکمه‌ی اشتراک: تمام‌عرض (' + ( expectedWidth - 48 ) + ')', m.cta && String( m.cta.w ) );

	check( m.note && '11px' === m.note.fontSize, 'سطر پایانی: font 11', m.note && m.note.fontSize );
	check( m.note && 'rgb(139, 148, 159)' === m.note.color, 'سطر پایانی: رنگ muted', m.note && m.note.color );
	check( m.note && near( parseFloat( m.note.marginTop ), 20, 1 ), 'سطر پایانی: margin-top 20', m.note && m.note.marginTop );
	check( m.note && 'center' === m.note.textAlign, 'سطر پایانی: تراز وسط', m.note && m.note.textAlign );

	check( /drawer-open/.test( m.bodyClass ), 'بدنه: کلاس drawer-open', m.bodyClass );
	check( 'hidden' === m.bodyOverflow, 'بدنه: overflow: hidden', m.bodyOverflow );
	check( /overflow:\s*hidden/.test( m.bodyInline ), 'بدنه: overflow درون‌خطی (مثل مرجع)', m.bodyInline );
	check( m.scrollWidth === m.clientWidth, 'بدون سرریز افقی', m.scrollWidth + ' / ' + m.clientWidth );
	check( near( m.headerHeight, 76, 1 ), 'ارتفاع سربرگ در ≤۹۸۰: ۷۶px', String( m.headerHeight ) );
	check( 'none' === m.desktopDisplay, 'ناوبری دسکتاپ در ≤۹۸۰ پنهان است', String( m.desktopDisplay ) );
	check( m.rowCount >= 8, 'ردیف‌های فهرست از فهرست راهبری ساخته شده‌اند (' + m.rowCount + ')', String( m.rowCount ) );

	/* هندسه‌ی همبرگری — مرجع: ۳۹۰px → ۲۸×۳۲ در x=۳۴۶ | ۷۶۸px → ۳۶×۳۶ در x=۷۱۲ */
	const toggle = 390 === width ? { w: 28, h: 32, x: 346 } : { w: 36, h: 36, x: 712 };

	check( m.toggle && near( m.toggle.w, toggle.w, 1 ) && near( m.toggle.h, toggle.h, 1 ), 'همبرگری در ' + width + ': ' + toggle.w + '×' + toggle.h, m.toggle && JSON.stringify( m.toggle ) );
	check( m.toggle && near( m.toggle.x, toggle.x, 1 ), 'همبرگری در ' + width + ': x=' + toggle.x, m.toggle && String( m.toggle.x ) );
	check( m.brand && near( m.brand.x + m.brand.w, toggle.x - ( 390 === width ? 10 : 13 ), 2 ), 'برند بلافاصله پس از همبرگری (فاصله‌ی مرجع)', m.brand && JSON.stringify( m.brand ) );
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	try {
		for ( const width of [ 390, 768 ] ) {
			console.log( '' );
			console.log( '── عرض ' + width + 'px ─────────────────────────────' );
			const page = await browser.newPage( { viewport: { width, height: 844 }, locale: 'fa-IR' } );
			const errors = [];
			page.on( 'pageerror', ( error ) => errors.push( String( error ) ) );
			await page.goto( WP_URL, { waitUntil: 'load' } );
			await page.waitForTimeout( 400 );
			await openDrawer( page );
			compareWithReference( await measure( page ), width );
			check( 0 === errors.length, 'بدون خطای اجرا', errors.join( ' | ' ) );

			/* بستن با پس‌زمینه. */
			await page.mouse.click( 5, 400 );
			await page.waitForTimeout( 250 );
			const closed = await page.evaluate( () => ( {
				hidden: document.querySelector( '[data-mobile-drawer]' ).hidden,
				body: document.body.className,
				overflow: getComputedStyle( document.body ).overflow,
			} ) );
			check( closed.hidden && ! /drawer-open/.test( closed.body ) && 'visible' === closed.overflow, 'کلیک روی پس‌زمینه کشو را می‌بندد و قفل پیمایش را باز می‌کند', JSON.stringify( closed ) );

			/* بستن با دکمه‌ی ×. */
			await openDrawer( page );
			await page.click( '[data-mobile-close]' );
			await page.waitForTimeout( 250 );
			check( await page.evaluate( () => document.querySelector( '[data-mobile-drawer]' ).hidden ), 'دکمه‌ی بستن کار می‌کند' );

			/* Escape. */
			await openDrawer( page );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 250 );
			check( await page.evaluate( () => document.querySelector( '[data-mobile-drawer]' ).hidden ), 'Escape کشو را می‌بندد (افزوده‌ی دسترس‌پذیری)' );

			/* ردیف جستجو: کشو بسته و پوسته‌ی جستجو باز شود. */
			await openDrawer( page );
			await page.click( '.mobile-drawer .header-search' );
			await page.waitForTimeout( 300 );
			const search = await page.evaluate( () => ( {
				drawerHidden: document.querySelector( '[data-mobile-drawer]' ).hidden,
				overlayHidden: document.querySelector( '[data-koohe-search-overlay]' ).hidden,
			} ) );
			check( search.drawerHidden && ! search.overlayHidden, 'ردیف جستجو: کشو بسته و پوسته‌ی جستجو باز می‌شود', JSON.stringify( search ) );

			/*
			 * ردیف «جاری». پیش‌تر هر ردیفی که با پارامتر کار می‌کند
			 * (`/?post_type=movie&mc_sort=rating` و…) کوئری‌اش دور
			 * ریخته می‌شد و در خانه هم‌نشانی خانه می‌شد؛ نتیجه چهار
			 * ردیف سبز هم‌زمان بود (سنجیده‌شده).
			 */
			const current = await page.evaluate( () => ( {
				count: document.querySelectorAll( '.mobile-drawer nav a.is-current' ).length,
				labels: [ ...document.querySelectorAll( '.mobile-drawer nav a.is-current' ) ].map( ( a ) => a.textContent.trim() ),
				aria: [ ...document.querySelectorAll( '.mobile-drawer nav a.is-current' ) ].map( ( a ) => a.getAttribute( 'aria-current' ) ),
			} ) );
			check( 1 === current.count && /خانه/.test( current.labels[ 0 ] ) && 'page' === current.aria[ 0 ], 'در خانه تنها ردیف «خانه» جاری است', JSON.stringify( current ) );

			await page.close();
		}

		/* برگه‌ی آرشیو: ردیف جاری باید همان آرشیو باشد، نه خانه. */
		{
			const archive = await browser.newPage( { viewport: { width: 390, height: 844 }, locale: 'fa-IR' } );
			await archive.goto( WP_URL + '?post_type=movie&mc_sort=rating', { waitUntil: 'load' } );
			await archive.waitForTimeout( 350 );
			const current = await archive.evaluate( () => ( {
				count: document.querySelectorAll( '.mobile-drawer nav a.is-current' ).length,
				labels: [ ...document.querySelectorAll( '.mobile-drawer nav a.is-current' ) ].map( ( a ) => a.textContent.trim() ),
			} ) );
			check( 1 === current.count && /بالاترین امتیازها/.test( current.labels[ 0 ] ), 'در آرشیو «بالاترین امتیازها» تنها ردیف جاری است', JSON.stringify( current ) );
			await archive.close();
		}

		/* عرض دسکتاپ: همبرگری پنهان، ناوبری دسکتاپ دیده می‌شود. */
		console.log( '' );
		console.log( '── عرض 1024px ─────────────────────────────' );
		const wide = await browser.newPage( { viewport: { width: 1024, height: 844 }, locale: 'fa-IR' } );
		await wide.goto( WP_URL, { waitUntil: 'load' } );
		await wide.waitForTimeout( 400 );
		const open = await wide.evaluate( () => ( {
			toggle: getComputedStyle( document.querySelector( '[data-mobile-open]' ) ).display,
			nav: getComputedStyle( document.querySelector( '.koohe-nav' ) ).display,
			header: Math.round( document.querySelector( '.koohe-header' ).getBoundingClientRect().height ),
		} ) );
		check( 'none' === open.toggle, 'همبرگری در ۱۰۲۴px پنهان است', open.toggle );
		check( 'flex' === open.nav, 'ناوبری دسکتاپ در ۱۰۲۴px دیده می‌شود', open.nav );
		check( near( open.header, 84, 1 ), 'ارتفاع سربرگ در ۱۰۲۴px: ۸۴px', String( open.header ) );
		await wide.close();
	} finally {
		await browser.close();
	}

	console.log( '' );
	console.log( '==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )();

/**
 * آزمون «مگامنو در موبایل» — پیوستگی ناوبری دسکتاپ و کشوی موبایل.
 *
 * مرجع «سینورا» در دسکتاپ یک پنل مگامنو دارد و در موبایل همان مقصدها را با
 * یک کشوی **تخت** (بدون آکاردئون و بدون زیرمنوی تودرتو) می‌رساند. پس این
 * آزمون سه چیز را می‌سنجد:
 *
 *   ۱) مگامنو در عرض‌های موبایل دیده نمی‌شود و در دسکتاپ در DOM هست،
 *   ۲) هر مقصد نوار دسکتاپ (و سرگروه‌های مگامنو) در کشو هم هست — یعنی
 *      موبایل چیزی از ناوبری را گم نمی‌کند،
 *   ۳) کشو مثل مرجع تخت است: پیوندها فرزند مستقیم `nav`، هر کدام برچسب و
 *      نشان راهنما دارند و هیچ زیرمنوی تودرتویی در آن نیست.
 *
 * هندسه‌ی دقیق کشو (پهنا، پدینگ، انیمیشن، دکمه‌ی اشتراک…) در
 * `drawer-parity.cjs` سنجیده می‌شود و این‌جا تکرار نمی‌شود.
 *
 * اجرا (از همین پوشه که `node_modules` در آن لینک است):
 *   PLAYWRIGHT_BROWSERS_PATH=… node mega-mobile-probe.cjs
 * با نشانی دلخواه:
 *   WP_URL=http://localhost:8099/ node mega-mobile-probe.cjs
 *   REF_URL=http://localhost:8098/index.html node mega-mobile-probe.cjs
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
 * نشانی بدون دامنه، برای مقایسه‌ی مقصدهای وردپرس و مرجع.
 *
 * @param {string} href نشانی کامل یا نسبی.
 * @return {string} مسیر با اسلش پایانی.
 */
function pathOf( href ) {
	try {
		const url = new URL( href );
		return url.pathname.endsWith( '/' ) ? url.pathname : url.pathname + '/';
	} catch ( error ) {
		return String( href || '' ).split( '?' )[ 0 ];
	}
}

/**
 * سنجش «به‌هم‌ریختگی» پیوندها: مقصدهایی که در دسکتاپ هست و در کشو نیست.
 *
 * @param {Array<Object>} desktop مقصدهای دسکتاپ.
 * @param {Array<Object>} drawer  پیوندهای کشو.
 * @return {Array<string>} برچسب مقصدهای گم‌شده.
 */
function missing( desktop, drawer ) {
	const have = drawer.map( ( item ) => pathOf( item.href ) );

	return desktop
		.filter( ( item ) => ! have.includes( pathOf( item.href ) ) )
		.map( ( item ) => item.label );
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	try {
		/* -------------------------------------------------------------
		 * ۱) مگامنو در نردبان عرض‌ها
		 * ---------------------------------------------------------- */
		console.log( '── مگامنو و نوار دسکتاپ ────────────────────────────────' );

		const desktop = await browser.newPage( { viewport: { width: 1024, height: 900 }, locale: 'fa-IR' } );
		const errors  = [];

		desktop.on( 'pageerror', ( error ) => errors.push( String( error ) ) );

		await desktop.goto( WP_URL, { waitUntil: 'load' } );
		await desktop.waitForTimeout( 400 );

		const wide = await desktop.evaluate( () => {
			const panel = document.querySelector( '.koohe-mega > .wp-block-navigation__submenu-container' );
			const nav   = document.querySelector( '.koohe-nav' );

			return {
				panelInDom: !! panel,
				navDisplay: nav ? getComputedStyle( nav ).display : null,
				toggleHidden: getComputedStyle( document.querySelector( '[data-mobile-open]' ) ).display,
				topLevel: [ ...document.querySelectorAll( '.koohe-nav > ul > li' ) ]
					.map( ( li ) => {
						const a = li.querySelector( ':scope > a' );
						return a ? { label: a.textContent.trim(), href: a.href } : null;
					} )
					.filter( Boolean ),
				megaGroups: [ ...document.querySelectorAll( '.koohe-mega > .wp-block-navigation__submenu-container > li' ) ]
					.filter( ( li ) => li.querySelector( ':scope > ul' ) )
					.map( ( li ) => {
						const a = li.querySelector( ':scope > a' );
						return a ? { label: a.textContent.trim(), href: a.href } : null;
					} )
					.filter( Boolean ),
				megaParent: ( () => {
					const a = document.querySelector( 'li.koohe-mega > a.wp-block-navigation-item__content' );
					return a ? { label: a.textContent.trim(), href: a.href } : null;
				} )(),
			};
		} );

		check( wide.panelInDom, 'مگامنو در ۱۰۲۴px در DOM هست' );
		check( 'flex' === wide.navDisplay, 'نوار دسکتاپ در ۱۰۲۴px دیده می‌شود', String( wide.navDisplay ) );
		check( 'none' === wide.toggleHidden, 'همبرگری در ۱۰۲۴px پنهان است', String( wide.toggleHidden ) );
		check( wide.topLevel.length >= 4, 'نوار دسکتاپ آیتم دارد', wide.topLevel.map( ( i ) => i.label ).join( '، ' ) );
		check( !! wide.megaParent, 'سرگروه مگامنو (مقصد) شناسایی شد', JSON.stringify( wide.megaParent ) );

		await desktop.close();

		/* -------------------------------------------------------------
		 * ۲) کشو در ۳۹۰px — تخت، کامل و بدون زیرمنوی تودرتو
		 * ---------------------------------------------------------- */
		console.log( '\n── کشوی موبایل در ۳۹۰px ────────────────────────────────' );

		const page = await browser.newPage( {
			viewport: { width: 390, height: 844 },
			locale: 'fa-IR',
			isMobile: true,
			hasTouch: true,
		} );

		page.on( 'pageerror', ( error ) => errors.push( String( error ) ) );

		await page.goto( WP_URL, { waitUntil: 'load' } );
		await page.waitForTimeout( 400 );

		const closedState = await page.evaluate( () => {
			const nav   = document.querySelector( '.koohe-nav' );
			const panel = document.querySelector( '.koohe-mega > .wp-block-navigation__submenu-container' );
			const toggle = document.querySelector( '[data-mobile-open]' );
			const rect  = toggle.getBoundingClientRect();

			return {
				navWidth: nav ? Math.round( nav.getBoundingClientRect().width ) : 0,
				panelWidth: panel ? Math.round( panel.getBoundingClientRect().width ) : 0,
				toggle: { w: Math.round( rect.width ), h: Math.round( rect.height ) },
				hidden: document.querySelector( '[data-mobile-drawer]' ).hidden,
			};
		} );

		check( 0 === closedState.navWidth, 'نوار دسکتاپ در ۳۹۰px عرضی ندارد (پنهان)', String( closedState.navWidth ) );
		check( 0 === closedState.panelWidth, 'مگامنو در ۳۹۰px بیرون از چیدمان است', String( closedState.panelWidth ) );
		check( closedState.toggle.w > 0 && closedState.toggle.h > 0, 'همبرگری در ۳۹۰px دیده می‌شود', JSON.stringify( closedState.toggle ) );
		check( true === closedState.hidden, 'کشو در آغاز بسته است' );

		await page.click( '[data-mobile-open]' );
		await page.waitForTimeout( 450 );

		const drawer = await page.evaluate( () => {
			const box = ( el ) => {
				const rect = el.getBoundingClientRect();
				return { w: Math.round( rect.width ), h: Math.round( rect.height ) };
			};
			const backdrop = document.querySelector( '[data-mobile-drawer]' );
			const nav      = document.querySelector( '.mobile-drawer nav' );
			const rows     = [ ...document.querySelectorAll( '.mobile-drawer nav a' ) ];

			return {
				open: ! backdrop.hidden,
				drawerBox: box( document.querySelector( '.mobile-drawer' ) ),
				bodyClass: document.body.className,
				rows: rows.map( ( a ) => {
					const chevron = a.querySelector( 'svg' );
					return {
						label: a.textContent.trim(),
						href: a.getAttribute( 'href' ) || '',
						direct: a.parentElement === nav,
						chevron: chevron ? box( chevron ) : null,
					};
				} ),
				nested: document.querySelectorAll( '.mobile-drawer nav ul, .mobile-drawer nav button' ).length,
				search: !! document.querySelector( '.mobile-drawer [data-mobile-search]' ),
			};
		} );

		check( drawer.open && drawer.drawerBox.w > 0, 'کشو با [data-mobile-open] باز شد', JSON.stringify( drawer.drawerBox ) );
		check( /drawer-open/.test( drawer.bodyClass ), 'بدنه کلاس drawer-open گرفت', drawer.bodyClass );
		check( drawer.rows.length >= 8, 'کشو به‌اندازه‌ی کافی مقصد دارد', drawer.rows.length + ' پیوند' );
		check( 0 === drawer.nested, 'کشو زیرمنوی تودرتو یا آکاردئون ندارد (مرجع هم ندارد)', String( drawer.nested ) );
		check( drawer.rows.every( ( row ) => row.direct ), 'همه‌ی پیوندها فرزند مستقیم nav هستند', JSON.stringify( drawer.rows.filter( ( r ) => ! r.direct ).map( ( r ) => r.label ) ) );
		check( drawer.rows.every( ( row ) => row.chevron && near( row.chevron.w, 17, 2 ) && near( row.chevron.h, 17, 2 ) ), 'هر ردیف نشان راهنما ۱۷×۱۷ دارد', JSON.stringify( drawer.rows.find( ( r ) => ! r.chevron ) || '' ) );
		check( drawer.rows.every( ( row ) => '' !== row.label && /^https?:\/\//.test( row.href ) && '#' !== row.href ), 'هر ردیف برچسب و نشانی واقعی دارد', JSON.stringify( drawer.rows.filter( ( r ) => '' === r.label ).map( ( r ) => r.href ) ) );
		check( drawer.search, 'ردیف جستجو در کشو هست (مثل مرجع)' );

		/* مقصدهای دسکتاپ و سرگروه‌های مگامنو نباید در موبایل گم شوند. */
		const wanted = [ wide.megaParent, ...wide.megaGroups, ...wide.topLevel ].filter( Boolean );
		const gone   = missing( wanted, drawer.rows );

		check(
			0 === gone.length,
			'همه‌ی مقصدهای دسکتاپ/مگامنو در کشو هم هستند',
			gone.join( '، ' )
		);

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );

		check( 0 === overflow, 'بدون سرریز افقی با کشوی باز', String( overflow ) );

		await page.screenshot( { path: 'shots/mobile-drawer-mega.png' } );

		/* -------------------------------------------------------------
		 * ۳) جستجو و بستن — همان قرارداد مرجع
		 * ---------------------------------------------------------- */
		console.log( '\n── جستجو و بستن ────────────────────────────────────────' );

		await page.click( '.mobile-drawer [data-mobile-search]' );
		await page.waitForTimeout( 350 );

		const afterSearch = await page.evaluate( () => ( {
			drawerHidden: document.querySelector( '[data-mobile-drawer]' ).hidden,
			overlay: ! document.querySelector( '[data-koohe-search-overlay]' ).hidden,
			toggle: document.querySelector( '[data-mobile-open]' ).getAttribute( 'aria-expanded' ),
		} ) );

		check( afterSearch.drawerHidden && afterSearch.overlay, 'جستجو از کشو: کشو بسته و پوسته‌ی جستجو باز می‌شود', JSON.stringify( afterSearch ) );
		check( 'false' === afterSearch.toggle, 'حالت aria-expanded همبرگری همگام است', String( afterSearch.toggle ) );

		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 250 );
		await page.click( '[data-mobile-open]' );
		await page.waitForTimeout( 350 );
		await page.click( '[data-mobile-close]' );
		await page.waitForTimeout( 250 );

		const afterClose = await page.evaluate( () => ( {
			hidden: document.querySelector( '[data-mobile-drawer]' ).hidden,
			drawerOpen: document.body.classList.contains( 'drawer-open' ),
			focus: document.activeElement === document.querySelector( '[data-mobile-open]' ),
		} ) );

		check( afterClose.hidden && ! afterClose.drawerOpen, 'دکمه‌ی بستن کشو را می‌بندد', JSON.stringify( afterClose ) );
		check( afterClose.focus, 'فوکوس به همبرگری برمی‌گردد' );

		/*
		 * شمار پیوندهای کشو با مرجع: مرجع هم کشوی تخت دارد؛ سنجه این‌جاست که
		 * موبایل ما ناوبری کمتری از مرجع نداشته باشد (عدد ثابت نیست چون
		 * فهرست وردپرس از داده‌ی واقعی سایت ساخته می‌شود).
		 */
		let refRows = 9;

		try {
			const refPage = await browser.newPage( { viewport: { width: 390, height: 844 }, locale: 'fa-IR' } );
			await refPage.goto( REF_URL, { waitUntil: 'load', timeout: 15000 } );
			await refPage.click( '[data-mobile-open]' );
			await refPage.waitForTimeout( 300 );
			refRows = await refPage.evaluate( () => document.querySelectorAll( '.mobile-drawer nav a' ).length );
			await refPage.close();
		} catch ( error ) {
			refRows = 9; /* عدد اندازه‌گیری‌شده‌ی مرجع؛ صفحه‌ی مرجع بالا نیامد. */
		}

		check( drawer.rows.length >= refRows, 'کشو کمتر از مرجع مقصد ندارد (' + drawer.rows.length + ' ≥ ' + refRows + ')' );

		console.log( '\n── خطای اجرا ───────────────────────────────────────────' );
		check( 0 === errors.length, 'بدون خطای جاوااسکریپت در ۳۹۰px و ۱۰۲۴px', errors.slice( 0, 2 ).join( ' | ' ) );

		await page.close();
	} finally {
		await browser.close();
	}

	console.log( '' );
	console.log( '==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )();

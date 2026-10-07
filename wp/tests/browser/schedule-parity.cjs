/**
 * آزمون هم‌سانی برگه‌ی «برنامه پخش» با مرجع «سینورا» + آزمون رفتار.
 *
 * سه لایه سنجیده می‌شود:
 *
 *   ۱) هم‌سانی هندسه و تایپوگرافی با `cinora/schedule.html` در ۱۴۴۰px:
 *      قاب پنل (`.schedule-panel.full-schedule`)، سرصفحه (`‎.schedule-title`)،
 *      نشانگر زمان (`.timezone`)، نوار روزها (`.week-tabs`)، ردیف‌ها
 *      (`.schedule-item`) و نقطه‌ی یادداشت پایین (`.schedule-footnote`)،
 *      به‌همراه کارت‌های ستون کنار (`.schedule-note-card` و `.sidebar-promo`).
 *   ۲) رفتار: هفت تب نقش‌دار، جابه‌جایی پنل با کلیک، `aria-selected`،
 *      روز خالی با `.schedule-empty` و پیوند پیشنهادها، ردیف‌های
 *      زمان‌بندی‌شده با ساعت پخش از فراداده، و ردیف پیشنهادهای پایین.
 *   ۳) نردبان پاسخگو در ۹۸۰/۷۶۸/۳۹۰px و دسترس‌پذیری (axe) روی برگه.
 *
 * اجرا (از پوشه‌ی `wp/tests/browser` که `node_modules` در آن لینک است):
 *
 *   node schedule-parity.cjs
 *   WP_URL=http://localhost:8099/schedule/ node schedule-parity.cjs
 *   REF_URL=http://localhost:8098/schedule.html node schedule-parity.cjs
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

/*
 * کمینه‌ی داده‌ی آزمون برای این سوئیت — بیرون از بلوک try تا در سراسر فایل
 * دیده شود.
 *
 * چرا `seedAll()` اینجا نه؟ چون بذر «پخش زنده» دو سریال را موقتاً به «امروز»
 * منتقل می‌کند و پنل برنامه‌ی صفحه‌ی نخست را سه‌ردیفی می‌کند، در حالی که مرجع
 * یک ردیف دارد → سه سنجه‌ی ارتفاع سرخ می‌شد (تگ w19c). پس فقط بذر پایه را
 * می‌کاریم و تضمین می‌کنیم بذر پخش زنده فعال نباشد.
 */
const { seedCore, ensureLiveRestored } = require( './qa-seeds.cjs' );

const WP_URL  = process.env.WP_URL || 'http://localhost:8099/schedule/';
const REF_URL = process.env.REF_URL || 'http://localhost:8098/schedule.html';

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
 * متریک‌های برگه‌ی برنامه‌ی پخش.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} متریک‌ها.
 */
async function measure( page ) {
	return page.evaluate( () => {
		/*
		 * یک تب فعال است و بقیه‌ی پنل‌ها `hidden`؛ پس متریک‌های ردیف‌ها
		 * باید از پنل دیده‌شده خوانده شوند، وگرنه `getBoundingClientRect`
		 * صفر برمی‌گرداند. مرجع پنل تب ندارد و همان سند دامنه است.
		 */
		const scope = document.querySelector( '[role="tabpanel"]:not([hidden])' ) || document;
		const find  = ( sel ) => scope.querySelector( sel );

		const num = ( v ) => {
			const n = parseFloat( v );
			return Number.isFinite( n ) ? Math.round( n * 100 ) / 100 : null;
		};
		const box = ( sel, root ) => {
			const el = ( root || document ).querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { x: Math.round( r.x ), y: Math.round( r.y ), w: Math.round( r.width ), h: Math.round( r.height ) };
		};
		const css = ( sel, props, root ) => {
			const el = ( root || document ).querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const out = {};
			const cs = getComputedStyle( el );
			props.forEach( ( p ) => {
				const v = cs[ p ];
				out[ p ] = /^[\d.]+px$/.test( v ) ? num( v ) : v;
			} );
			return out;
		};
		const rect = ( sel, root ) => {
			const el = ( root || document ).querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { w: Math.round( r.width ), h: Math.round( r.height ) };
		};

		const tabs = [ ...document.querySelectorAll( '[role="tab"]' ) ];

		return {
			has: {
				panel: !!document.querySelector( '.schedule-panel' ),
				full: !!document.querySelector( '.full-schedule' ),
				title: !!document.querySelector( '.schedule-title' ),
				timezone: !!document.querySelector( '.timezone' ),
				tabs: !!document.querySelector( '.week-tabs' ),
				footnote: !!document.querySelector( '.schedule-footnote' ),
				note: !!document.querySelector( '.schedule-note-card' ),
				promo: !!document.querySelector( '.sidebar-promo' ),
				aside: !!document.querySelector( '.schedule-page-aside' ),
				layout: !!document.querySelector( '.schedule-page-layout' ),
			},
			panel: css( '.schedule-panel', [ 'paddingTop', 'paddingBottom', 'paddingInlineStart', 'borderRadius', 'minHeight', 'backgroundColor', 'display' ] ),
			title: css( '.schedule-title', [ 'gap', 'display' ] ),
			titleH2: css( '.schedule-title h2', [ 'fontSize', 'fontWeight' ] ),
			titleP: css( '.schedule-title p', [ 'fontSize', 'marginTop' ] ),
			/*
			 * همان منطق ساعت: `margin-inline-start:auto` در کرومیوم به px
			 * حل می‌شود، پس جای نشانگر با نسبت سنجیده می‌شود.
			 */
			zoneRatio: ( () => {
				const row = document.querySelector( '.schedule-title' );
				const z   = row ? row.querySelector( '.timezone' ) : null;
				if ( ! row || ! z ) {
					return null;
				}
				const r1 = row.getBoundingClientRect();
				const r2 = z.getBoundingClientRect();
				return Math.round( ( ( r2.x + r2.width / 2 - r1.x ) / r1.width ) * 100 ) / 100;
			} )(),
			zone: css( '.timezone', [ 'fontSize', 'gap', 'marginInlineStart', 'color' ] ),
			tabs: css( '.week-tabs', [ 'gridTemplateColumns', 'gap', 'paddingTop', 'borderRadius', 'marginTop' ] ),
			tab: css( '.week-tabs > button', [ 'fontSize', 'paddingTop', 'paddingBottom', 'borderRadius' ] ),
			tabActive: css( '.week-tabs > button.active', [ 'color' ] ),
			items: css( '.schedule-items', [ 'marginTop', 'minHeight' ], scope ),
			/*
			 * ردیف‌های میانی سنجیده می‌شوند (`:not(:last-child)`): ردیف آخر
			 * در مرجع صفر می‌شود و در روز تک‌ردیفی، همان ردیف اول هم آخر است.
			 */
			item: css( '.schedule-item:not(:last-child)', [ 'gap', 'paddingTop', 'paddingBottom', 'marginBottom' ], scope ),
			lastItem: css( '.schedule-item:last-child', [ 'marginBottom', 'paddingBottom', 'borderBottomWidth' ], scope ),
			itemImg: css( '.schedule-item > img', [ 'borderRadius', 'objectFit' ], scope ),
			imgBox: rect( '.schedule-item > img', scope ),
			itemH3: css( '.schedule-item h3', [ 'fontSize', 'fontWeight' ], scope ),
			time: css( '.schedule-time', [ 'fontSize', 'paddingTop', 'borderRadius', 'color', 'marginInlineStart' ], scope ),
			play: css( '.schedule-play', [ 'borderRadius' ], scope ),
			playBox: rect( '.schedule-play', scope ),
			footnote: css( '.schedule-footnote', [ 'fontSize', 'paddingTop', 'marginTop', 'gap' ] ),
			dot: css( '.schedule-footnote > .live-dot', [ 'width', 'height', 'backgroundColor' ] ),
			note: css( '.schedule-note-card', [ 'paddingTop', 'paddingInlineStart', 'borderRadius', 'backgroundColor' ] ),
			noteH3: css( '.schedule-note-card > h3', [ 'fontSize', 'marginTop' ] ),
			noteP: css( '.schedule-note-card > p', [ 'fontSize', 'marginTop', 'lineHeight' ] ),
			noteMeta: css( '.schedule-note-card > span:last-child', [ 'fontSize', 'gap', 'marginTop' ] ),
			promo: css( '.sidebar-promo', [ 'paddingTop', 'paddingInlineStart', 'borderRadius', 'display' ] ),
			promoH3: css( '.sidebar-promo h3', [ 'fontSize' ] ),
			promoP: css( '.sidebar-promo p', [ 'fontSize', 'marginTop' ] ),
			promoLabel: css( '.sidebar-promo > span', [ 'fontSize', 'gap', 'marginTop' ] ),
			layout: css( '.schedule-page-layout', [ 'gridTemplateColumns', 'gap', 'display' ] ),
			pageIcon: rect( '.page-title-icon' ),
			tabsCount: tabs.length,
			tabsRole: tabs.length ? tabs.map( ( t ) => t.getAttribute( 'role' ) ).join( ',' ) : '',
			emptyBox: rect( '.schedule-empty', scope ),
			emptyFont: css( '.schedule-empty', [ 'fontSize', 'gap' ], scope ),
			itemCount: scope.querySelectorAll ? scope.querySelectorAll( '.schedule-item' ).length : 0,
			/*
			 * جای ساعت در ردیف: مرجع آن را با `margin-inline-start:auto` به
			 * انتهای ردیف می‌برد؛ چون کرومیوم مقدار محاسبه‌شده‌ی `auto` را
			 * به px می‌دهد، به‌جای خودِ مقدار، نسبت جای مرکز ساعت به پهنای
			 * ردیف سنجیده می‌شود.
			 */
			timeRatio: ( () => {
				const row = scope.querySelector( '.schedule-item' );
				const t   = row ? row.querySelector( '.schedule-time' ) : null;
				if ( ! row || ! t ) {
					return null;
				}
				const r1 = row.getBoundingClientRect();
				const r2 = t.getBoundingClientRect();
				return Math.round( ( ( r2.x + r2.width / 2 - r1.x ) / r1.width ) * 100 ) / 100;
			} )(),
			bodyOverflowX: document.documentElement.scrollWidth > window.innerWidth + 1,
		};
	} );
}

/**
 * روزی را انتخاب می‌کند که داده دارد (اگر چنین روزی باشد).
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<boolean>} آیا روزی با داده پیدا شد.
 */
async function pickDayWithItems( page ) {
	const count = await page.evaluate( () => document.querySelectorAll( '[role="tab"]' ).length );
	let best = { index: -1, rows: 0 };

	for ( let i = 0; i < count; i++ ) {
		/*
		 * مرجع پس از هر کلیک، نوار تب‌ها را از نو می‌سازد؛ پس هر بار
		 * دوباره از داخل صفحه کلیک می‌کنیم تا دسته‌ی عنصر جدا‌شده نگیریم.
		 */
		await page.evaluate( ( idx ) => {
			const tabs = document.querySelectorAll( '[role="tab"]' );
			if ( tabs[ idx ] ) {
				tabs[ idx ].click();
			}
		}, i );
		await page.waitForTimeout( 150 );

		const rows = await page.evaluate( () => {
			const scope = document.querySelector( '[role="tabpanel"]:not([hidden])' ) || document;
			return scope.querySelectorAll( '.schedule-item' ).length;
		} );

		if ( rows > best.rows ) {
			best = { index: i, rows };
		}
	}

	/* روی پرشمارترین روز می‌مانیم تا ردیف‌های میانی هم سنجیدنی باشند. */
	if ( -1 !== best.index ) {
		await page.evaluate( ( idx ) => {
			const tabs = document.querySelectorAll( '[role="tab"]' );
			if ( tabs[ idx ] ) {
				tabs[ idx ].click();
			}
		}, best.index );
		await page.waitForTimeout( 150 );
	}

	return best.rows;
}

/* eslint-disable max-lines-per-function */
( async () => {
	/*
	 * پیش‌نیاز داده: این سنجه‌ها روی برگه‌ی بذرگرفته اجرا می‌شوند و ترتیب اجرا
	 * تضمین‌شده نیست (هر سوئیت در پایان داده‌ی خودش را برمی‌گرداند). یک بار در
	 * پیمایش w19a همین وابستگی به وضعیت داده، این دو سوئیت را سرخ کرد.
	 */
	console.log( '— برآوردن پیش‌نیاز داده —' );
	ensureLiveRestored();
	seedCore();

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	/* ------------------------------------------------------------------
	 * ۱) هم‌سانی دسکتاپ
	 * --------------------------------------------------------------- */
	console.log( '\n── هم‌سانی دسکتاپ (۱۴۴۰px) ─────────────────────────────' );

	const wpDesktop  = await browser.newContext( { viewport: { width: 1440, height: 900 }, locale: 'fa-IR' } );
	const refDesktop = await browser.newContext( { viewport: { width: 1440, height: 900 }, locale: 'fa-IR' } );
	const wpPage     = await wpDesktop.newPage();
	const refPage    = await refDesktop.newPage();

	const wpErrors = [];
	wpPage.on( 'pageerror', ( e ) => wpErrors.push( String( e ) ) );

	const wpResponse = await wpPage.goto( WP_URL, { waitUntil: 'load', timeout: 60000 } ).catch( () => null );
	const refResponse = await refPage.goto( REF_URL, { waitUntil: 'load', timeout: 60000 } ).catch( () => null );

	check( wpResponse && 200 === wpResponse.status(), 'برگه‌ی «برنامه پخش» وردپرس با ۲۰۰ باز می‌شود', wpResponse ? String( wpResponse.status() ) : 'no-response' );
	check( !!refResponse, 'برگه‌ی مرجع در دسترس است', REF_URL );

	await wpPage.waitForTimeout( 400 );
	await refPage.waitForTimeout( 400 );

	/* هر دو طرف را روی روزی با داده می‌بریم تا ردیف‌ها سنجیدنی شوند. */
	const wpRows  = await pickDayWithItems( wpPage );
	const refRows = await pickDayWithItems( refPage );

	check( wpRows > 0, 'در وردپرس روزی با ردیف زمان‌بندی‌شده وجود دارد', String( wpRows ) );
	check( refRows > 0, 'در مرجع روزی با ردیف وجود دارد', String( refRows ) );

	/*
	 * سنجش ردیف‌های میانی فقط وقتی معنا دارد که هر دو طرف روزی با دست‌کم
	 * دو ردیف داشته باشند؛ در غیر این صورت همان ردیف، «ردیف آخر» است و
	 * قاعده‌ی `:last-child` اندازه‌هایش را صفر می‌کند. این حالت جداگانه
	 * بالا گزارش می‌شود و این‌جا پنهان نمی‌شود.
	 */
	const multiRow = wpRows >= 2 && refRows >= 2;
	console.log( multiRow
		? '  · روز سنجش: ' + wpRows + ' ردیف در وردپرس و ' + refRows + ' ردیف در مرجع'
		: '  · روز سنجش تک‌ردیفی است (وردپرس ' + wpRows + ' / مرجع ' + refRows + ')؛ سنجش ردیف میانی اجرا نمی‌شود' );

	const wp  = await measure( wpPage );
	const ref = await measure( refPage );

	check( wp.has.panel && wp.has.full, 'قاب پنل با کلاس‌های مرجع رندر شده است', JSON.stringify( wp.has ) );
	check( wp.has.title && wp.has.timezone && wp.has.tabs && wp.has.footnote, 'سرصفحه، نشانگر زمان، نوار روزها و یادداشت پایین هست', JSON.stringify( wp.has ) );
	check( wp.has.aside && wp.has.note && wp.has.promo && wp.has.layout, 'ستون کنار با کارت یادداشت و کارت ترویجی و شبکه‌ی دوستونه هست', JSON.stringify( wp.has ) );

	const pairs = [
		[ 'قاب: padding بالا', wp.panel.paddingTop, ref.panel.paddingTop, 1 ],
		[ 'قاب: padding پایین', wp.panel.paddingBottom, ref.panel.paddingBottom, 1 ],
		[ 'قاب: padding کناری (full-schedule)', wp.panel.paddingInlineStart, ref.panel.paddingInlineStart, 1 ],
		[ 'قاب: گردی گوشه', wp.panel.borderRadius, ref.panel.borderRadius, 0.5 ],
		[ 'قاب: کمینه‌ی ارتفاع', wp.panel.minHeight, ref.panel.minHeight, 1 ],
		[ 'سرصفحه: فاصله‌ی درونی', wp.title.gap, ref.title.gap, 0.5 ],
		[ 'سرصفحه: اندازه‌ی h2', wp.titleH2.fontSize, ref.titleH2.fontSize, 1 ],
		[ 'سرصفحه: وزن h2', wp.titleH2.fontWeight, ref.titleH2.fontWeight, 0 ],
		[ 'سرصفحه: اندازه‌ی زیرنویس', wp.titleP.fontSize, ref.titleP.fontSize, 1 ],
		[ 'نشانگر زمان: اندازه‌ی قلم', wp.zone.fontSize, ref.zone.fontSize, 1 ],
		[ 'نشانگر زمان: فاصله‌ی آیکن و متن', wp.zone.gap, ref.zone.gap, 1 ],
		[ 'نوار روزها: شبکه‌ی هفت‌ستونه', wp.tabs.gridTemplateColumns, ref.tabs.gridTemplateColumns, 1 ],
		[ 'نوار روزها: فاصله‌ی ستون‌ها', wp.tabs.gap, ref.tabs.gap, 0.5 ],
		[ 'نوار روزها: padding', wp.tabs.paddingTop, ref.tabs.paddingTop, 0.5 ],
		[ 'نوار روزها: گردی گوشه', wp.tabs.borderRadius, ref.tabs.borderRadius, 0.5 ],
		[ 'نوار روزها: فاصله‌ی از سرصفحه', wp.tabs.marginTop, ref.tabs.marginTop, 1 ],
		[ 'دکمه‌ی روز: اندازه‌ی قلم', wp.tab.fontSize, ref.tab.fontSize, 1 ],
		[ 'دکمه‌ی روز: padding عمودی', wp.tab.paddingTop, ref.tab.paddingTop, 1 ],
		[ 'دکمه‌ی روز: گردی گوشه', wp.tab.borderRadius, ref.tab.borderRadius, 0.5 ],
		[ 'فهرست روز: فاصله‌ی از نوار', wp.items.marginTop, ref.items.marginTop, 1 ],
		[ 'فهرست روز: کمینه‌ی ارتفاع', wp.items.minHeight, ref.items.minHeight, 1 ],
		[ 'پوستر ردیف: گردی گوشه', wp.itemImg.borderRadius, ref.itemImg.borderRadius, 0.5 ],
		[ 'پوستر ردیف: نسبت قاب', wp.itemImg.objectFit, ref.itemImg.objectFit, 0 ],
		[ 'پوستر ردیف: پهنای قاب', wp.imgBox ? wp.imgBox.w : null, ref.imgBox ? ref.imgBox.w : null, 2 ],
		[ 'پوستر ردیف: ارتفاع قاب', wp.imgBox ? wp.imgBox.h : null, ref.imgBox ? ref.imgBox.h : null, 2 ],
		[ 'عنوان ردیف: اندازه‌ی قلم', wp.itemH3.fontSize, ref.itemH3.fontSize, 1 ],
		[ 'عنوان ردیف: وزن', wp.itemH3.fontWeight, ref.itemH3.fontWeight, 0 ],
		[ 'ساعت پخش: اندازه‌ی قلم', wp.time.fontSize, ref.time.fontSize, 1 ],
		[ 'ساعت پخش: padding', wp.time.paddingTop, ref.time.paddingTop, 1 ],
		[ 'ساعت پخش: گردی گوشه', wp.time.borderRadius, ref.time.borderRadius, 0.5 ],
		[ 'دکمه‌ی پخش: گردی', wp.play.borderRadius, ref.play.borderRadius, 1 ],
		[ 'دکمه‌ی پخش: قاب', wp.playBox ? wp.playBox.w : null, ref.playBox ? ref.playBox.w : null, 2 ],
		[ 'یادداشت پایین: اندازه‌ی قلم', wp.footnote.fontSize, ref.footnote.fontSize, 1 ],
		[ 'یادداشت پایین: فاصله‌ی از فهرست', wp.footnote.marginTop, ref.footnote.marginTop, 1 ],
		[ 'یادداشت پایین: padding بالای خط', wp.footnote.paddingTop, ref.footnote.paddingTop, 1 ],
		[ 'نقطه‌ی یادداشت: اندازه', wp.dot ? wp.dot.width : null, ref.dot ? ref.dot.width : null, 0.5 ],
		[ 'نقطه‌ی یادداشت: رنگ کرم مرجع', wp.dot ? wp.dot.backgroundColor : null, ref.dot ? ref.dot.backgroundColor : null, 0 ],
		[ 'کارت یادداشت: padding', wp.note.paddingTop, ref.note.paddingTop, 1 ],
		[ 'کارت یادداشت: گردی گوشه', wp.note.borderRadius, ref.note.borderRadius, 0.5 ],
		[ 'کارت یادداشت: اندازه‌ی h3', wp.noteH3.fontSize, ref.noteH3.fontSize, 1 ],
		[ 'کارت یادداشت: فاصله‌ی h3', wp.noteH3.marginTop, ref.noteH3.marginTop, 1 ],
		[ 'کارت یادداشت: اندازه‌ی متن', wp.noteP.fontSize, ref.noteP.fontSize, 1 ],
		[ 'کارت یادداشت: ارتفاع خط متن', wp.noteP.lineHeight, ref.noteP.lineHeight, 1 ],
		[ 'کارت یادداشت: سطر پایین', wp.noteMeta.fontSize, ref.noteMeta.fontSize, 1 ],
		[ 'کارت ترویجی: padding', wp.promo.paddingTop, ref.promo.paddingTop, 1 ],
		[ 'کارت ترویجی: گردی گوشه', wp.promo.borderRadius, ref.promo.borderRadius, 0.5 ],
		[ 'کارت ترویجی: اندازه‌ی h3', wp.promoH3.fontSize, ref.promoH3.fontSize, 1 ],
		[ 'کارت ترویجی: اندازه‌ی متن', wp.promoP.fontSize, ref.promoP.fontSize, 1 ],
		[ 'کارت ترویجی: سطر پیوند', wp.promoLabel.fontSize, ref.promoLabel.fontSize, 1 ],
		[ 'شبکه‌ی برگه: ستون پنل و ستون کنار', wp.layout.gridTemplateColumns, ref.layout.gridTemplateColumns, 1 ],
		[ 'شبکه‌ی برگه: فاصله', wp.layout.gap, ref.layout.gap, 1 ],
		[ 'آیکن سرصفحه‌ی برگه: قاب', wp.pageIcon ? wp.pageIcon.w : null, ref.pageIcon ? ref.pageIcon.w : null, 2 ],
	];

	if ( multiRow ) {
		pairs.push(
			[ 'ردیف میانی: فاصله‌ی اجزا', wp.item && wp.item.gap, ref.item && ref.item.gap, 1 ],
			[ 'ردیف میانی: padding بالا', wp.item && wp.item.paddingTop, ref.item && ref.item.paddingTop, 1 ],
			[ 'ردیف میانی: padding پایین', wp.item && wp.item.paddingBottom, ref.item && ref.item.paddingBottom, 1 ],
			[ 'ردیف میانی: فاصله‌ی ردیف‌ها', wp.item && wp.item.marginBottom, ref.item && ref.item.marginBottom, 1 ]
		);
	}

	pairs.forEach( ( [ label, a, b, tol ] ) => {
		if ( null === a || null === b || undefined === a || undefined === b ) {
			check( false, label, 'عنصر در یک طرف پیدا نشد → ' + JSON.stringify( { wp: a, ref: b } ) );
			return;
		}

		const ok = 'number' === typeof a ? near( a, b, tol ) : a === b;
		check( ok, label, JSON.stringify( { wp: a, ref: b } ) );
	} );

	check(
		null !== wp.timeRatio && null !== ref.timeRatio && near( wp.timeRatio, ref.timeRatio, 0.05 ),
		'ساعت پخش همان جای مرجع در ردیف می‌نشیند (`margin-inline-start:auto`)',
		JSON.stringify( { wp: wp.timeRatio, ref: ref.timeRatio } )
	);
	check(
		null !== wp.zoneRatio && null !== ref.zoneRatio && near( wp.zoneRatio, ref.zoneRatio, 0.05 ),
		'نشانگر زمان همان جای مرجع در سرصفحه می‌نشیند (`margin-inline-start:auto`)',
		JSON.stringify( { wp: wp.zoneRatio, ref: ref.zoneRatio } )
	);
	check(
		null !== wp.lastItem && 0 === wp.lastItem.marginBottom && 0 === wp.lastItem.paddingBottom && 0 === wp.lastItem.borderBottomWidth,
		'ردیف آخر: بی‌خط، بی‌فاصله و بی‌padding پایین (مثل مرجع)',
		JSON.stringify( { wp: wp.lastItem, ref: ref.lastItem } )
	);
	check(
		null !== ref.lastItem && 0 === ref.lastItem.marginBottom && 0 === ref.lastItem.borderBottomWidth,
		'مرجع هم ردیف آخر را صفر می‌کند (پشتوانه‌ی سنجش بالا)',
		JSON.stringify( ref.lastItem )
	);
	check( wp.tabsCount === 7 && 'tab,tab,tab,tab,tab,tab,tab' === wp.tabsRole, 'هفت تب با نقش tab ساخته شده‌اند', String( wp.tabsCount ) );
	check( ! wp.bodyOverflowX, 'بدون سرریز افقی در ۱۴۴۰px' );

	/* ------------------------------------------------------------------
	 * ۲) رفتار و داده
	 * --------------------------------------------------------------- */
	console.log( '\n── رفتار و داده ─────────────────────────────────────────' );

	const behaviour = await wpPage.evaluate( async () => {
		const tabs = [ ...document.querySelectorAll( '[role="tab"]' ) ];

		const selected = () => tabs.filter( ( t ) => 'true' === t.getAttribute( 'aria-selected' ) ).map( ( t ) => t.id );
		const visible  = () => [ ...document.querySelectorAll( '[role="tabpanel"]' ) ].filter( ( p ) => ! p.hidden ).map( ( p ) => p.id );

		const initial = { selected: selected(), visible: visible(), active: tabs.filter( ( t ) => t.classList.contains( 'active' ) ).length };

		/* روز خالی (اگر باشد) و روزی با داده. */
		const days = tabs.map( ( t ) => ( {
			id: t.id,
			empty: !! document.getElementById( t.getAttribute( 'aria-controls' ) )?.querySelector( '.schedule-empty' ),
			items: document.getElementById( t.getAttribute( 'aria-controls' ) )?.querySelectorAll( '.schedule-item' ).length || 0,
		} ) );

		/* کلیک روی یک روز خالی. */
		const emptyTab = tabs[ days.findIndex( ( d ) => 0 === d.items ) ];
		let emptyState = null;

		if ( emptyTab ) {
			emptyTab.click();
			await new Promise( ( r ) => setTimeout( r, 120 ) );
			const panel = document.getElementById( emptyTab.getAttribute( 'aria-controls' ) );
			emptyState = {
				selected: selected(),
				visible: visible(),
				message: panel.querySelector( '.schedule-empty p' )?.textContent.trim() || '',
				link: panel.querySelector( '.schedule-empty a' )?.getAttribute( 'href' ) || '',
				label: panel.querySelector( '.schedule-empty a' )?.textContent.replace( /‹/g, '' ).trim() || '',
			};
		}

		/*
		 * همه‌ی روزها یک‌بار کلیک می‌شوند و ردیف‌های همه‌ی روزها جمع می‌شوند؛
		 * سنجش فقط روی «نخستین روزی که داده دارد» کافی نبود، چون اثرهای
		 * آزمون روی روزهای مختلف پخش شده‌اند.
		 */
		const rowsByDay = {};

		for ( const tab of tabs ) {
			tab.click();
			await new Promise( ( r ) => setTimeout( r, 120 ) );
			const panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
			rowsByDay[ tab.id ] = [ ...panel.querySelectorAll( '.schedule-item' ) ].map( ( a ) => ( {
				href: a.getAttribute( 'href' ),
				title: a.querySelector( 'h3' )?.textContent.trim() || '',
				original: a.querySelector( 'p' )?.textContent.trim() || '',
				meta: a.querySelector( 'div > span' )?.textContent.trim() || '',
				time: a.querySelector( '.schedule-time' )?.textContent.trim() || '',
				play: !! a.querySelector( '.schedule-play' ),
				img: !! a.querySelector( 'img' ),
			} ) );
		}

		const rows = Object.values( rowsByDay ).flat();

		/* پیشنهادهای پایین برگه. */
		const heads = [ ...document.querySelectorAll( '.manacore-block-head h2' ) ].map( ( h ) => h.textContent.trim() );
		const recs  = {
			heading: heads.find( ( h ) => /شروع کنیم/.test( h ) ) || '',
			cards: document.querySelectorAll( '.manacore-titles-block .manacore-card, .manacore-titles-block .media-card' ).length,
			more: document.querySelector( '.manacore-more-link' )?.getAttribute( 'href' ) || '',
		};

		const promo = {
			href: document.querySelector( '.sidebar-promo' )?.getAttribute( 'href' ) || '',
			lines: ( document.querySelector( '.sidebar-promo' )?.textContent || '' ).replace( /\s+/g, ' ' ).trim(),
		};

		const note = {
			icon: document.querySelector( '.schedule-note-card > span:first-child' )?.textContent.trim() || '',
			title: document.querySelector( '.schedule-note-card > h3' )?.textContent.trim() || '',
			dot: !! document.querySelector( '.schedule-note-card .live-dot' ),
			meta: document.querySelector( '.schedule-note-card > span:last-child' )?.textContent.replace( /\s+/g, ' ' ).trim() || '',
		};

		return { initial, days, emptyState, rows, rowsByDay, recs, promo, note };
	} );

	check( 1 === behaviour.initial.selected.length && behaviour.initial.selected[ 0 ] === behaviour.initial.visible[ 0 ].replace( 'schedule-panel-', 'schedule-tab-' ), 'در آغاز یک تب انتخاب‌شده و همان پنل دیده می‌شود', JSON.stringify( behaviour.initial ) );
	check( 1 === behaviour.initial.active, 'هم‌زمان تنها یک تب کلاس active دارد', String( behaviour.initial.active ) );
	check( 7 === behaviour.days.length, 'هفت روز در مدل داده هست', String( behaviour.days.length ) );

	const emptyCount = behaviour.days.filter( ( d ) => d.empty ).length;
	const dataCount  = behaviour.days.filter( ( d ) => d.items > 0 ).length;

	check( emptyCount + dataCount === 7, 'هر روز یا ردیف دارد یا حالت خالی (بدون روز بی‌حالت)', JSON.stringify( behaviour.days ) );
	check( dataCount >= 2, 'دست‌کم دو روز با ردیف زمان‌بندی‌شده هست (شنبه‌ی آزمون: دوشنبه و پنجشنبه)', JSON.stringify( behaviour.days ) );

	if ( behaviour.emptyState ) {
		check( behaviour.emptyState.selected[ 0 ] === behaviour.emptyState.visible[ 0 ].replace( 'schedule-panel-', 'schedule-tab-' ), 'با کلیک روی روز خالی، همان پنل دیده می‌شود', JSON.stringify( behaviour.emptyState ) );
		check( /داستان تازه/.test( behaviour.emptyState.message ), 'پیام روز خالی همان پیام مرجع است', behaviour.emptyState.message );
		check( /browse|کشف|\/browse\//.test( behaviour.emptyState.link ) && '' !== behaviour.emptyState.label, 'پیوند پیشنهادها در روز خالی هست', JSON.stringify( behaviour.emptyState ) );
	} else {
		check( false, 'روز خالی برای سنجش حالت خالی وجود دارد', 'همه‌ی روزها داده دارند' );
	}

	const shogun = behaviour.rows.find( ( r ) => /شوگان/.test( r.title ) );
	const chern = behaviour.rows.find( ( r ) => /چرنوبیل/.test( r.title ) );

	check( !!shogun, 'در روز داده‌دار، اثر زمان‌بندی‌شده‌ی آزمون دیده می‌شود', JSON.stringify( behaviour.rows.map( ( r ) => r.title ) ) );
	check( !!shogun && /۲۱:۳۰/.test( shogun.time ) && /◷/.test( shogun.time ), 'ساعت پخش از فراداده آمده است (۲۱:۳۰)', shogun ? shogun.time : '' );
	check( !!shogun && /فصل/.test( shogun.meta ) && /قسمت/.test( shogun.meta ), 'سطر «فصل/قسمت» از شمار واقعی داده ساخته می‌شود', shogun ? shogun.meta : '' );
	check( !!shogun && shogun.play && shogun.img && '' !== shogun.href, 'ردیف پوستر، دکمه‌ی پخش و مقصد دارد', shogun ? JSON.stringify( { play: shogun.play, img: shogun.img, href: shogun.href } ) : '' );
	check( true === ( !! shogun || !! chern ), 'اثر زمان‌بندی‌شده‌ی آزمون در فهرست هست' );

	check( /شروع کنیم/.test( behaviour.recs.heading ), 'سرتیتر بخش پیشنهادها همان تیتر مرجع است', behaviour.recs.heading );
	check(
		behaviour.recs.cards >= 2 && behaviour.recs.cards <= 6,
		'کارت‌های پیشنهاد به تعداد سریال‌های موجود رندر شده‌اند (داده‌ی آزمون دو سریال دارد؛ سقف مرجع ۶)',
		String( behaviour.recs.cards )
	);
	check( /type=series/.test( behaviour.recs.more ), 'پیوند «مشاهده همه» به فهرست سریال‌ها می‌رود', behaviour.recs.more );

	check( '' !== behaviour.promo.href, 'کارت ترویجی به نشانی حساب کاربری می‌رود (نشانه‌ی account:watchlist)', behaviour.promo.href );
	check( /watchlist/.test( behaviour.promo.href ), 'پارامتر tab=watchlist روی نشانی حساب هست', behaviour.promo.href );
	check( /لیست تماشای من/.test( behaviour.promo.lines ), 'سه سطر کارت ترویجی کامل است', behaviour.promo.lines );

	check( '◷' === behaviour.note.icon, 'نشانه‌ی کارت یادداشت همان نویسه‌ی مرجع است', behaviour.note.icon );
	check( behaviour.note.dot && /به وقت تهران/.test( behaviour.note.meta ), 'سطر پایین کارت یادداشت با نقطه‌ی زنده هست', JSON.stringify( behaviour.note ) );

	check( 0 === wpErrors.length, 'بدون خطای اجرا در برگه‌ی وردپرس', JSON.stringify( wpErrors.slice( 0, 2 ) ) );

	/* ------------------------------------------------------------------
	 * ۳) نردبان پاسخگو
	 * --------------------------------------------------------------- */
	console.log( '\n── نردبان پاسخگو ────────────────────────────────────────' );

	const breakpoints = [
		{ width: 980, label: '۹۸۰px' },
		{ width: 768, label: '۷۶۸px' },
		{ width: 480, label: '۴۸۰px' },
		{ width: 390, label: '۳۹۰px' },
	];

	for ( const bp of breakpoints ) {
		const ctx  = await browser.newContext( { viewport: { width: bp.width, height: 900 }, locale: 'fa-IR' } );
		const page = await ctx.newPage();
		await page.goto( WP_URL, { waitUntil: 'load', timeout: 60000 } );
		await page.waitForTimeout( 300 );

		const m = await page.evaluate( () => {
			const css = ( sel, props ) => {
				const el = document.querySelector( sel );
				if ( ! el ) {
					return null;
				}
				const cs = getComputedStyle( el );
				const out = {};
				props.forEach( ( p ) => {
					const v = cs[ p ];
					out[ p ] = /^[\d.]+px$/.test( v ) ? Math.round( parseFloat( v ) * 100 ) / 100 : v;
				} );
				return out;
			};
			/*
			 * در وردپرس هر روز یک `role="tabpanel"` جدا دارد و روزهای غیرفعال با
			 * `hidden` نشانه‌گذاری می‌شوند؛ مرجع یک فهرست واحد دارد. پس اول
			 * «دیده‌شدنی» انتخاب می‌شود تا اندازه‌ها به روزِ فعال مربوط باشند
			 * (مرجع `hidden` ندارد و رفتارش تغییری نمی‌کند).
			 */
			const visible = ( sel ) => {
				const nodes = [ ...document.querySelectorAll( sel ) ];
				return nodes.find( ( el ) => ! el.closest( '[hidden]' ) ) || nodes[ 0 ] || null;
			};
			const rect = ( sel ) => {
				const el = visible( sel );
				if ( ! el ) {
					return null;
				}
				const r = el.getBoundingClientRect();
				return { w: Math.round( r.width ), h: Math.round( r.height ) };
			};

			const asideCards = [ ...document.querySelectorAll( '.schedule-page-aside > *' ) ].map( ( el ) => Math.round( el.getBoundingClientRect().top ) );

			return {
				asideSideBySide: asideCards.length > 1 && asideCards[ 0 ] === asideCards[ 1 ],
				layout: css( '.schedule-page-layout', [ 'display', 'gridTemplateColumns' ] ),
				aside: css( '.schedule-page-aside', [ 'display', 'gridTemplateColumns' ] ),
				panel: css( '.schedule-panel', [ 'paddingTop', 'paddingInlineStart' ] ),
				tab: css( '.week-tabs > button', [ 'fontSize' ] ),
				img: rect( '.schedule-item > img' ),
				overflowX: document.documentElement.scrollWidth > window.innerWidth + 1,
				asideTop: rect( '.schedule-page-aside' ) ? Math.round( document.querySelector( '.schedule-page-aside' ).getBoundingClientRect().top ) : null,
				panelBottom: Math.round( document.querySelector( '.schedule-panel' ).getBoundingClientRect().bottom ),
			};
		} );

		console.log( '  · ' + bp.label + ' → ' + JSON.stringify( m ) );

		const expected = {
			980: { panelPaddingTop: 23, tabFont: 10, asideCols: 228 },
			768: { panelPaddingTop: 23, tabFont: 9, stack: true },
			480: { panelPaddingTop: 20, tabFont: 8, img: 52, asideStack: 'stacked' },
			390: { panelPaddingTop: 20, tabFont: 8, img: 52, asideStack: 'stacked' },
		}[ bp.width ];

		check( near( m.panel.paddingTop, expected.panelPaddingTop, 1 ), 'پنل در ' + bp.label + ': padding بالا ' + expected.panelPaddingTop + 'px', JSON.stringify( m.panel ) );
		check( near( m.tab.fontSize, expected.tabFont, 1 ), 'تب روز در ' + bp.label + ': قلم ' + expected.tabFont + 'px', JSON.stringify( m.tab ) );

		if ( expected.stack ) {
			check( 'block' === m.layout.display, 'شبکه‌ی برگه در ' + bp.label + ' تک‌ستونه می‌شود (مرجع: `display:block`)', JSON.stringify( m.layout ) );
			check( 'grid' === m.aside.display && m.asideSideBySide, 'دو کارت ستون کنار در ' + bp.label + ' کنار هم (دوستونه) می‌نشینند', JSON.stringify( m.aside ) );
			check( m.asideTop >= m.panelBottom - 1, 'ستون کنار در ' + bp.label + ' زیر پنل می‌آید', JSON.stringify( { asideTop: m.asideTop, panelBottom: m.panelBottom } ) );
		}

		if ( 'stacked' === ( expected.asideStack || '' ) ) {
			check( ! m.asideSideBySide, 'دو کارت ستون کنار در ' + bp.label + ' زیر هم می‌نشینند', JSON.stringify( m.aside ) );
		}

		if ( expected.asideCols && m.layout.gridTemplateColumns ) {
			check( /228px/.test( m.layout.gridTemplateColumns ), 'شبکه‌ی برگه در ' + bp.label + ': ستون کنار ۲۲۸px', m.layout.gridTemplateColumns );
		}

		if ( expected.img ) {
			check( m.img && near( m.img.w, expected.img, 2 ) && near( m.img.h, 77, 3 ), 'پوستر ردیف در ' + bp.label + ': ' + expected.img + '×۷۷', JSON.stringify( m.img ) );
		}

		check( ! m.overflowX, 'بدون سرریز افقی در ' + bp.label );

		await ctx.close();
	}

	/* ------------------------------------------------------------------
	 * ۳.۵) پنل صفحه‌ی نخست — همان جزء، چیدمان بلوک
	 *
	 * مرجع همین جزء را در دو جا به کار می‌برد: صفحه‌ی نخست با
	 * `class="schedule-panel"` (بدون `full-schedule`) و برگه‌ی برنامه با
	 * همان قاب + `full-schedule`. پیش‌تر چیدمان بلوک سرصفحه‌ی عمومی
	 * بلوک‌ها را می‌ساخت، پنل ۱۸px بلندتر از مرجع بود و پوستر ردیف‌ها
	 * ۴۶×۴۶ (مرجع ۴۹×۶۹) — این بخش همان را قفل می‌کند.
	 * --------------------------------------------------------------- */
	console.log( '\n── پنل صفحه‌ی نخست (چیدمان بلوک) ───────────────────────' );

	const homeCtx = await browser.newContext( { viewport: { width: 1440, height: 900 }, locale: 'fa-IR' } );
	const homeWp  = await homeCtx.newPage();
	const homeRef = await homeCtx.newPage();

	const homeMetrics = async ( page, url, scopeSel ) => {
		await page.goto( url, { waitUntil: 'load' } );
		await page.waitForTimeout( 600 );
		return page.evaluate( ( scopeSel ) => {
			const scope = document.querySelector( scopeSel );
			if ( ! scope ) {
				return null;
			}
			const box = ( el ) => {
				if ( ! el ) {
					return null;
				}
				const r = el.getBoundingClientRect();
				return { w: Math.round( r.width ), h: Math.round( r.height * 10 ) / 10 };
			};
			/*
			 * مرجع یک فهرست روز دارد؛ پیاده‌سازی وردپرس هفت `role="tabpanel"`
			 * می‌سازد و غیرفعال‌ها را `hidden` می‌کند. پس سنجه‌های مکانی باید از
			 * پنلِ دیده‌شدنی خوانده شوند، نه از نخستین گره‌ی سند (که ممکن است در
			 * پنل پنهان باشد و اندازه‌اش صفر برگردد). اگر `hidden`ی در کار نباشد
			 * (سمت مرجع) همان نخستین گره‌ی منطبق برگردانده می‌شود.
			 */
			const pick = ( sel ) => {
				const nodes = [ ...scope.querySelectorAll( sel ) ];
				return nodes.find( ( el ) => ! el.closest( '[hidden]' ) ) || nodes[ 0 ] || null;
			};
			const at  = ( sel ) => box( pick( sel ) );
			const css = ( sel, props ) => {
				const el = pick( sel );
				if ( ! el ) {
					return null;
				}
				const cs  = getComputedStyle( el );
				const out = {};
				props.forEach( ( prop ) => {
					const value = cs[ prop ];
					out[ prop ] = /^[\d.]+px$/.test( value ) ? Math.round( parseFloat( value ) * 100 ) / 100 : value;
				} );
				return out;
			};
			const moreLink = scope.querySelector( '.schedule-title > .text-link' );

			return {
				panel: box( scope ),
				title: at( '.schedule-title' ),
				icon: at( '.schedule-title .section-icon' ),
				titleH2: at( '.schedule-title h2' ),
				titleP: at( '.schedule-title p' ),
				link: at( '.schedule-title > .text-link' ),
				tabs: at( '.week-tabs' ),
				items: at( '.schedule-items' ),
				item: at( '.schedule-item' ),
				itemImg: at( '.schedule-item img' ),
				itemDiv: at( '.schedule-item > div' ),
				itemP: at( '.schedule-item > div > p' ),
				itemSpan: at( '.schedule-item > div > span' ),
				time: at( '.schedule-time' ),
				foot: at( '.schedule-footnote' ),
				row: css( '.schedule-item:not(:last-child)', [ 'gap', 'paddingTop', 'paddingBottom', 'marginBottom' ] ),
				last: css( '.schedule-item:last-child', [ 'paddingBottom', 'marginBottom' ] ),
				img: css( '.schedule-item img', [ 'width', 'height', 'borderRadius' ] ),
				tab: css( '.week-tabs button', [ 'fontSize' ] ),
				footCss: css( '.schedule-footnote', [ 'fontSize', 'marginTop', 'paddingTop' ] ),
				titleCss: css( '.schedule-title h2', [ 'fontSize' ] ),
				shape: {
					title: !! scope.querySelector( '.schedule-title' ),
					hasFull: !! document.querySelector( '.full-schedule' ),
					moreHref: moreLink ? ( moreLink.getAttribute( 'href' ) || '' ) : '',
				},
			};
		}, scopeSel );
	};

	const wpHome  = await homeMetrics( homeWp, 'http://localhost:8099/', '.manacore-schedule' );
	const refHome = await homeMetrics( homeRef, 'http://localhost:8098/index.html', '.schedule-panel' );

	check( !! wpHome && !! refHome, 'پنل برنامه در صفحه‌ی نخست هر دو طرف پیدا شد' );

	if ( wpHome && refHome ) {
		check( wpHome.shape.title && refHome.shape.title, 'سرصفحه در هر دو طرف همان `.schedule-title` مرجع است', JSON.stringify( { wp: wpHome.shape.title, ref: refHome.shape.title } ) );
		check( ! wpHome.shape.hasFull && ! refHome.shape.hasFull, 'چیدمان صفحه‌ی نخست `full-schedule` ندارد (مرجع هم ندارد)', JSON.stringify( wpHome.shape.hasFull ) );
		check(
			/\/schedule\/|schedule\.html/.test( wpHome.shape.moreHref ) && /schedule\.html/.test( refHome.shape.moreHref ),
			'پیوند «برنامه کامل» در هر دو طرف به برگه‌ی برنامه‌ی هفتگی می‌رود',
			JSON.stringify( { wp: wpHome.shape.moreHref, ref: refHome.shape.moreHref } )
		);

		[
			[ 'panel', 'قاب پنل' ],
			[ 'title', 'سرصفحه' ],
			[ 'icon', 'نشانه‌ی سرصفحه' ],
			[ 'titleH2', 'عنوان سرصفحه' ],
			[ 'titleP', 'زیرنویس سرصفحه' ],
			[ 'link', 'پیوند سرصفحه' ],
			[ 'tabs', 'نوار روزها' ],
			[ 'items', 'فهرست روز' ],
			[ 'item', 'ردیف اثر' ],
			[ 'itemImg', 'پوستر ردیف' ],
			[ 'itemDiv', 'متن ردیف' ],
			[ 'itemP', 'نام اصلی ردیف' ],
			[ 'itemSpan', 'زیرنویس فصل/قسمت' ],
			[ 'time', 'قرص ساعت' ],
			[ 'foot', 'یادداشت پایین' ],
		].forEach( ( [ key, label ] ) => {
			const a = wpHome[ key ] && wpHome[ key ].h;
			const b = refHome[ key ] && refHome[ key ].h;
			check( null !== a && null !== b && near( a, b, 1 ), 'پنل صفحه‌ی نخست: ارتفاع «' + label + '»', JSON.stringify( { wp: wpHome[ key ], ref: refHome[ key ] } ) );
		} );

		check( wpHome.panel.w === refHome.panel.w, 'پنل صفحه‌ی نخست: پهنای قاب', JSON.stringify( { wp: wpHome.panel.w, ref: refHome.panel.w } ) );

		[
			[ 'tab', 'fontSize', 'دکمه‌ی روز: قلم' ],
			[ 'footCss', 'fontSize', 'یادداشت پایین: قلم' ],
			[ 'footCss', 'marginTop', 'یادداشت پایین: فاصله از فهرست' ],
			[ 'footCss', 'paddingTop', 'یادداشت پایین: padding بالای خط' ],
			[ 'titleCss', 'fontSize', 'عنوان سرصفحه: قلم' ],
			[ 'img', 'width', 'پوستر ردیف: پهنا' ],
			[ 'img', 'height', 'پوستر ردیف: ارتفاع' ],
			[ 'img', 'borderRadius', 'پوستر ردیف: گردی گوشه' ],
		].forEach( ( [ group, prop, label ] ) => {
			const a = wpHome[ group ] && wpHome[ group ][ prop ];
			const b = refHome[ group ] && refHome[ group ][ prop ];
			check( null !== a && null !== b && near( parseFloat( a ), parseFloat( b ), 1 ), 'پنل صفحه‌ی نخست — ' + label, JSON.stringify( { wp: a, ref: b } ) );
		} );

		/* اندازه‌های ردیف: مرجع در صفحه‌ی نخست تک‌ردیفی است، پس از قاعده‌ی پایه. */
		if ( wpHome.row && ! refHome.row ) {
			check(
				near( wpHome.row.gap, 16, 0.5 ) && near( wpHome.row.paddingTop, 4, 0.5 )
					&& near( wpHome.row.paddingBottom, 12, 0.5 ) && near( wpHome.row.marginBottom, 12, 0.5 ),
				'پنل صفحه‌ی نخست: ردیف مطابق قاعده‌ی پایه‌ی مرجع (gap ۱۶ / padding ۴ و ۱۲ / margin ۱۲)',
				JSON.stringify( wpHome.row )
			);
		} else if ( wpHome.row && refHome.row ) {
			check(
				near( wpHome.row.gap, refHome.row.gap, 1 ) && near( wpHome.row.paddingTop, refHome.row.paddingTop, 1 )
					&& near( wpHome.row.paddingBottom, refHome.row.paddingBottom, 1 ) && near( wpHome.row.marginBottom, refHome.row.marginBottom, 1 ),
				'پنل صفحه‌ی نخست: اندازه‌های ردیف با مرجع',
				JSON.stringify( { wp: wpHome.row, ref: refHome.row } )
			);
		}

		if ( wpHome.last && refHome.last ) {
			check(
				0 === wpHome.last.paddingBottom && 0 === wpHome.last.marginBottom
					&& 0 === refHome.last.paddingBottom && 0 === refHome.last.marginBottom,
				'پنل صفحه‌ی نخست: ردیف آخر در هر دو طرف صفر می‌شود',
				JSON.stringify( { wp: wpHome.last, ref: refHome.last } )
			);
		}
	}

	await homeCtx.close();

	/* ------------------------------------------------------------------
	 * ۴) دسترس‌پذیری
	 * --------------------------------------------------------------- */
	console.log( '\n── دسترس‌پذیری (axe) ───────────────────────────────────' );

	if ( axePath ) {
		await wpPage.addScriptTag( { path: axePath } );
		const axe = await wpPage.evaluate( async () => await window.axe.run( document, { resultTypes: [ 'violations' ] } ) );
		check(
			0 === axe.violations.length,
			'برگه‌ی «برنامه پخش» بدون نقض دسترس‌پذیری',
			JSON.stringify( axe.violations.map( ( v ) => ( { id: v.id, nodes: v.nodes.map( ( n ) => n.target ) } ) ) )
		);
	} else {
		console.log( '  ! axe-core نصب نبود؛ بررسی دسترس‌پذیری اجرا نشد.' );
	}

	await browser.close();


	console.log( '\n==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail ? 1 : 0 );
} )();

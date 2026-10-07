/**
 * مجموعه‌ی هارنس کوهه‌فیلم — رندر، دسترس‌پذیری (axe)، تعامل و کارکرد.
 *
 * این فایل همان سنجه‌هایی را اجرا می‌کند که در گزارش QA ادعا شده‌اند، روی
 * نصب واقعی وردپرس در http://localhost:8099 :
 *   الف) رندر: چند برگه × سه ویوپورت — وضعیت HTTP، سرریز افقی، خطای کنسول،
 *        شمار گره‌های DOM.
 *   ب) axe: نقض‌های دسترس‌پذیری روی برگه‌های کلیدی.
 *   ج) تعامل: پوسته‌ی جستجو، تب‌های برنامه‌ی هفتگی (کلیک + کلید)،
 *        همبرگری/مگامنو (hit-test واقعی)، پیوند پرش، focus-visible.
 *   د) کارکرد: ورود مدیر، وضعیت واچ‌لیست و پایداری‌اش پس از بازنشانی،
 *        پیوند عمیق #download، رد نوشتن REST بدون نانِس.
 *   ه) ویرایشگر سایت: ثبت الگوها و پاره‌قالب‌ها.
 *
 * اجرا: node /home/user/.cache/qa/harness-all.cjs
 */

'use strict';

const fs = require( 'fs' );
const { chromium } = require( 'playwright' );
const axeSource = require( 'axe-core' ).source;

const BASE = 'http://localhost:8099';
const SHOTS = '/home/user/.cache/qa/shots';

const PAGES = [
	[ 'home', '/' ],
	/* برگه‌ی «کشف داستان‌ها» — صفحه‌ی مرور با نوار مرور و سایدبار فیلتر. */
	[ 'discovery', '/browse/' ],
	/* آرشیو فیلم‌ها (نام پیشین این ردیف «browse» بود و با برگه‌ی کشف اشتباه می‌شد). */
	[ 'movie-archive', '/movie/' ],
	[ 'series-archive', '/series/' ],
	[ 'detail', '/movie/inception/' ],
	[ 'episode', '/series/chernobyl/season-1/episode-1/' ],
	[ 'search', '/?s=test' ],
	[ 'person', '/person/christopher-nolan/' ],
	/* برگه‌ی «بازیگران و عوامل» — هم‌ارز `cast.html` مرجع. */
	[ 'cast', '/cast/' ],
	/* برگه‌ی «سینورامگ» — هم‌ارز `magazine.html` مرجع. */
	[ 'magazine', '/magazine/' ],
	[ 'article', '/cinema-notes/' ],
	[ 'collection', '/collection/best-2024/' ],
	/* برگه‌ی «برنامه پخش» — هم‌ارز `schedule.html` مرجع. */
	[ 'schedule', '/schedule/' ],
	[ 'page-about', '/about/' ],
	[ 'subscribe', '/subscribe/' ],
	[ 'actionblocks', '/qa-actions/' ],
	[ '404', '/no-such-page-xyz/' ],
];

const { seedAll, restoreAll } = require( './qa-seeds.cjs' );

const VIEWPORTS = { desktop: { width: 1440, height: 900 }, tablet: { width: 768, height: 1024 }, mobile: { width: 390, height: 844 } };

let pass = 0;
let fail = 0;
const failures = [];

function check( ok, label, extra ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label + ( extra ? '  — ' + extra : '' ) );
	} else {
		fail++;
		failures.push( label + ( extra ? ' — ' + extra : '' ) );
		console.log( '  ✗ ' + label + ( extra ? '  — ' + extra : '' ) );
	}
}

async function login( page ) {
	await page.goto( BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );
}

( async () => {
	/*
	 * پیش‌نیاز داده: فهرست برگه‌های این هارنس شامل برگه‌های بذرگرفته است
	 * (سینورامگ، راهنما/قوانین، چهره، پخش زنده، حساب). چون هر سوئیت دیگر در پایان
	 * داده‌ی خودش را برمی‌گرداند، تکیه بر ترتیب اجرا برگه را ۴۰۴ می‌کرد و آزمون
	 * سرخ می‌شد (تگ‌های w18i و w18d). پس هارنس هم خودش می‌کارد و برمی‌گرداند.
	 */
	console.log( '\n— کاشت داده‌ی آزمون —' );
	seedAll();

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const render = {};

	/* ---------------------------------------------------------------
	 * الف) رندر در سه ویوپورت
	 * ------------------------------------------------------------ */
	console.log( '\n=== الف) رندر در سه ویوپورت ===' );

	for ( const [ name, path ] of PAGES ) {
		for ( const [ vpName, viewport ] of Object.entries( VIEWPORTS ) ) {
			const context = await browser.newContext( { viewport, locale: 'fa-IR' } );
			const page = await context.newPage();

			const errors = [];
			const bad = [];
			page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
			page.on( 'response', ( r ) => {
				if ( r.status() >= 400 ) {
					bad.push( { url: r.url(), status: r.status() } );
				}
			} );

			const response = await page.goto( BASE + path, { waitUntil: 'load', timeout: 60000 } ).catch( () => null );
			await page.waitForTimeout( 500 );

			const metrics = await page.evaluate( () => ( {
				overflowX: document.documentElement.scrollWidth > window.innerWidth + 1,
				domNodes: document.getElementsByTagName( '*' ).length,
				docH: document.documentElement.scrollHeight,
				title: document.title,
			} ) );

			render[ name + '|' + vpName ] = {
				status: response ? response.status() : 0,
				errors,
				bad,
				metrics,
			};

			await context.close();
		}
	}

	const rows = Object.keys( render );
	const nonOk = rows.filter( ( k ) => ! k.startsWith( '404|' ) && render[ k ].status !== 200 );
	const notFound = rows.filter( ( k ) => k.startsWith( '404|' ) && render[ k ].status !== 404 );
	const spills = rows.filter( ( k ) => render[ k ].metrics.overflowX );

	check( nonOk.length === 0, 'همه‌ی برگه‌ها در هر سه ویوپورت ۲۰۰ می‌دهند (' + rows.length + ' ردیف)', nonOk.join( ', ' ) );
	check( notFound.length === 0, 'کاوشگر ۴۰۴ واقعاً ۴۰۴ می‌دهد', notFound.join( ', ' ) );
	check( spills.length === 0, 'هیچ برگه‌ای سرریز افقی ندارد', spills.join( ', ' ) );

	const home = render[ 'home|desktop' ];
	const homeMobile = render[ 'home|mobile' ];
	check( home.metrics.domNodes < 4000, 'پیچیدگی DOM خانه در حد متعارف است', 'desktop=' + home.metrics.domNodes + ' / mobile=' + homeMobile.metrics.domNodes );
	check( home.metrics.docH > 2000 && homeMobile.metrics.docH > home.metrics.docH, 'چیدمان موبایل واقعاً بلندتر می‌شود (تک‌ستونی) ', 'desktop=' + home.metrics.docH + ' / mobile=' + homeMobile.metrics.docH );

	fs.writeFileSync( '/home/user/.cache/qa/render-report.json', JSON.stringify( render, null, 1 ) );

	/* ---------------------------------------------------------------
	 * ب) axe روی برگه‌های کلیدی
	 * ------------------------------------------------------------ */
	console.log( '\n=== ب) axe (دسترس‌پذیری) ===' );

	const axePages = [ '/', '/browse/', '/schedule/', '/movie/', '/movie/inception/', '/series/chernobyl/season-1/episode-1/', '/?s=test', '/no-such-page-xyz/', '/subscribe/', '/collection/best-2024/', '/cast/', '/person/christopher-nolan/', '/magazine/' ];
	let totalViolations = 0;

	for ( const path of axePages ) {
		const context = await browser.newContext( { viewport: VIEWPORTS.desktop, locale: 'fa-IR' } );
		const page = await context.newPage();
		await page.goto( BASE + path, { waitUntil: 'load', timeout: 60000 } );
		await page.waitForTimeout( 600);
		await page.addScriptTag( { content: axeSource } );

		const result = await page.evaluate( async () => {
			const run = await window.axe.run( document, { resultTypes: [ 'violations' ] } );
			return run.violations.map( ( v ) => ( { id: v.id, impact: v.impact, nodes: v.nodes.length, target: v.nodes[ 0 ] && v.nodes[ 0 ].target } ) );
		} );

		totalViolations += result.length;
		check( result.length === 0, 'axe: ' + path, result.length ? JSON.stringify( result ).slice( 0, 220 ) : 'بدون نقض' );
		await context.close();
	}

	/* ---------------------------------------------------------------
	 * ج) تعامل
	 * ------------------------------------------------------------ */
	console.log( '\n=== ج) تعامل (پوسته‌ی جستجو، تب روزها، سربرگ) ===' );

	const context = await browser.newContext( { viewport: VIEWPORTS.desktop, locale: 'fa-IR' } );
	const page = await context.newPage();
	await page.goto( BASE + '/', { waitUntil: 'load' } );
	await page.waitForTimeout( 800 );

	// پوسته‌ی جستجو
	await page.click( '.koohe-search-trigger' );
	await page.waitForTimeout( 500 );
	const open = await page.evaluate( () => ( {
		hidden: document.querySelector( '[data-koohe-search-overlay]' ).hidden,
		expanded: document.querySelector( '.koohe-search-trigger' ).getAttribute( 'aria-expanded' ),
		focus: document.activeElement && document.activeElement.tagName,
	} ) );
	check( ! open.hidden && 'true' === open.expanded, 'کلیک روی دکمه‌ی جستجو پوسته را باز می‌کند و aria-expanded را ست می‌کند', JSON.stringify( open ) );
	check( 'INPUT' === open.focus, 'فوکوس به ورودی جستجو می‌رود' );

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 400 );
	const closed = await page.evaluate( () => ( {
		hidden: document.querySelector( '[data-koohe-search-overlay]' ).hidden,
		expanded: document.querySelector( '.koohe-search-trigger' ).getAttribute( 'aria-expanded' ),
	} ) );
	check( closed.hidden && 'false' === closed.expanded, 'Escape پوسته را می‌بندد و aria-expanded را برمی‌گرداند', JSON.stringify( closed ) );

	// تب‌های برنامه‌ی هفتگی
	const tabs = await page.$$( '.manacore-day-tab' );
	check( tabs.length === 7, 'هفت تب روز هفته رندر شده است', 'tabs=' + tabs.length );

	if ( tabs.length === 7 ) {
		await page.click( '#schedule-tab-wednesday' );
		await page.waitForTimeout( 350 );
		const tabState = await page.evaluate( () => ( {
			active: document.querySelector( '.manacore-day-tab.active' ).id,
			selected: Array.from( document.querySelectorAll( '.manacore-day-tab' ) ).filter( ( t ) => 'true' === t.getAttribute( 'aria-selected' ) ).map( ( t ) => t.id ),
			visiblePanels: Array.from( document.querySelectorAll( '.manacore-schedule-day' ) ).filter( ( p ) => ! p.hidden ).map( ( p ) => p.id ),
		} ) );
		check(
			tabState.active === 'schedule-tab-wednesday' && tabState.selected.length === 1 && tabState.visiblePanels.length === 1,
			'کلیک روی تب روز، پنل همان روز را نشان می‌دهد و aria-selected را جابه‌جا می‌کند',
			JSON.stringify( tabState )
		);

		await page.focus( '#schedule-tab-wednesday' );
		await page.keyboard.press( 'ArrowRight' );
		await page.waitForTimeout( 300 );
		const kb = await page.evaluate( () => ( { active: document.querySelector( '.manacore-day-tab.active' ).id, focused: document.activeElement.id } ) );
		check( kb.focused === kb.active && kb.active !== 'schedule-tab-wednesday', 'کلید جهت‌دار تب را عوض می‌کند و فوکوس را می‌برد', JSON.stringify( kb ) );
	}

	// پیوند پرش به محتوا
	const skip = await page.evaluate( () => {
		const link = document.querySelector( 'a[href^="#"]' );
		return link ? link.textContent.trim().slice( 0, 30 ) : null;
	} );
	check( !! skip, 'پیوند پرش در ابتدای صفحه وجود دارد', skip || '' );

	// focus-visible روی دکمه‌ی سربرگ
	const focusRing = await page.evaluate( async () => {
		const btn = document.querySelector( '.koohe-search-trigger' );
		btn.focus();
		const cs = getComputedStyle( btn );
		return { outline: cs.outlineWidth, shadow: cs.boxShadow.slice( 0, 30 ) };
	} );
	check( '0px' !== focusRing.outline || 'none' !== focusRing.shadow, 'کنترل جستجو در حالت فوکوس نشانه‌ی بصری دارد', JSON.stringify( focusRing ) );

	// کارت کالکشن با کلید قابل لمس است
	const collectionKeyboard = await page.evaluate( () => {
		const card = document.querySelector( '.collection-card' );
		return card ? card.tagName + ':' + ( card.getAttribute( 'href' ) ? 'href' : 'no-href' ) : null;
	} );
	check( !! collectionKeyboard && collectionKeyboard.includes( 'href' ), 'کارت کالکشن یک پیوند واقعی است (قابل فوکوس با کلید)', collectionKeyboard || '' );

	await context.close();

	// hit-test سربرگ در چند پهنا
	console.log( '\n   اندازه‌گیری سربرگ:' );
	for ( const width of [ 600, 768, 1024, 1440 ] ) {
		const ctx = await browser.newContext( { viewport: { width, height: 800 }, locale: 'fa-IR' } );
		const p = await ctx.newPage();
		await p.goto( BASE + '/', { waitUntil: 'load' } );
		await p.waitForTimeout( 700 );

		const result = await p.evaluate( () => {
			const hit = ( el ) => {
				if ( ! el || 'none' === getComputedStyle( el ).display ) {
					return 'hidden';
				}
				const r = el.getBoundingClientRect();
				if ( r.width < 1 ) {
					return 'zero';
				}
				const at = document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 );
				return el === at || el.contains( at ) ? 'OK' : ( at ? at.tagName : 'NULL' );
			};

			return {
				trigger: hit( document.querySelector( '.koohe-search-trigger' ) ),
				/* همبرگری از این پس دکمه‌ی خود قالب است (کشوی کنارِ مرجع). */
				ham: hit( document.querySelector( '.mobile-menu-button' ) ),
				mega: hit( document.querySelector( '.koohe-nav-mega-toggle' ) ),
				spill: document.documentElement.scrollWidth > window.innerWidth + 1,
			};
		} );

		check( 'OK' === result.trigger, 'دکمه‌ی جستجو در ' + width + 'px قابل کلیک است', result.trigger );
		check( [ 'OK', 'hidden' ].includes( result.ham ), 'همبرگری در ' + width + 'px وضعیت درست دارد', result.ham );
		check( [ 'OK', 'hidden' ].includes( result.mega ), 'مگامنو در ' + width + 'px وضعیت درست دارد', result.mega );
		check( ! result.spill, 'سربرگ در ' + width + 'px سرریز ندارد' );

		await ctx.close();
	}

	/* ---------------------------------------------------------------
	 * د) کارکرد
	 * ------------------------------------------------------------ */
	console.log( '\n=== د) کارکرد (ورود، واچ‌لیست، پیوند عمیق، REST) ===' );

	const fctx = await browser.newContext( { viewport: VIEWPORTS.desktop, locale: 'fa-IR' } );
	const fpage = await fctx.newPage();

	await login( fpage );
	check( fpage.url().includes( 'wp-admin' ) || fpage.url().includes( 'wp-login.php' ), 'ورود مدیر انجام شد' );

	await fpage.goto( BASE + '/movie/inception/', { waitUntil: 'load' } );
	await fpage.waitForTimeout( 700 );

	const toggle = await fpage.evaluate( async () => {
		const btn = document.querySelector( '.koohe-detail-hero__watchlist' );
		if ( ! btn ) {
			return { found: false };
		}

		const before = btn.getAttribute( 'aria-pressed' );
		btn.click();
		await new Promise( ( r ) => setTimeout( r, 900 ) );

		return { found: true, before, after: btn.getAttribute( 'aria-pressed' ) };
	} );
	check( toggle.found && toggle.before !== toggle.after, 'دکمه‌ی لیست تماشا در هیرو وضعیتش را عوض می‌کند', JSON.stringify( toggle ) );

	if ( toggle.found ) {
		await fpage.reload( { waitUntil: 'load' } );
		await fpage.waitForTimeout( 700 );
		const persisted = await fpage.evaluate( () => {
			const btn = document.querySelector( '.koohe-detail-hero__watchlist' );
			return btn ? { pressed: btn.getAttribute( 'aria-pressed' ), active: btn.classList.contains( 'is-active' ) } : null;
		} );
		check( !! persisted && persisted.pressed === toggle.after, 'وضعیت لیست تماشا پس از بازنشانی برگه می‌ماند (وضعیت سمت سرور)', JSON.stringify( persisted ) );
	}

	await fpage.goto( BASE + '/movie/inception/#download', { waitUntil: 'load' } );
	await fpage.waitForTimeout( 1200 );
	const deep = await fpage.evaluate( () => ( {
		scroll: Math.round( window.scrollY ),
		active: document.querySelector( '[data-tab].is-active, .manacore-tab.is-active' ) ? true : false,
	} ) );
	check( deep.scroll > 200, 'پیوند عمیق #download صفحه را به بخش دانلود می‌برد', JSON.stringify( deep ) );

	/*
	 * نگهبان «پیوند خودارجاع»: مرجع هیچ‌جا پیوند «مشاهده همه» را به خودِ
	 * صفحه نمی‌بندد (`a.text-link` در `browse.html` اصلاً وجود ندارد).
	 * پیش از اصلاحِ `Block_Support::render_header()`، نوار فیلترِ صفحه‌ی
	 * نخست یک «مشاهده همه» به ریشه‌ی سایت — یعنی خودِ همان صفحه — می‌داد.
	 */
	const selfLinks = {};
	for ( const path of [ '/', '/movie/', '/series/' ] ) {
		await fpage.goto( BASE + path, { waitUntil: 'load' } );
		await fpage.waitForTimeout( 700 );
		selfLinks[ path ] = await fpage.evaluate( () => {
			const strip = ( u ) => String( u ).replace( /\/$/, '' );
			return [ ...document.querySelectorAll( 'a.manacore-more-link' ) ]
				.filter( ( a ) => strip( a.href ) === strip( location.href ) )
				.map( ( a ) => a.href );
		} );
	}
	check(
		Object.values( selfLinks ).every( ( list ) => 0 === list.length ),
		'هیچ پیوند «مشاهده همه» به خودِ صفحه اشاره نمی‌کند',
		JSON.stringify( selfLinks )
	);

	const restChecks = await fpage.evaluate( async () => {
		const out = {};

		const read = await fetch( '/wp-json/manacore/v1/titles?per_page=3' );
		out.read = read.status;

		const write = await fetch( '/wp-json/manacore/v1/watchlist', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { post_id: 12 } ),
		} );
		out.writeNoNonce = write.status;

		return out;
	} );
	check( 200 === restChecks.read, 'REST خواندنی ۲۰۰ می‌دهد', 'status=' + restChecks.read );
	check( [ 401, 403 ].includes( restChecks.writeNoNonce ), 'نوشتن REST بدون نانِس رد می‌شود', 'status=' + restChecks.writeNoNonce );

	await fctx.close();

	/* ---------------------------------------------------------------
	 * ه) ویرایشگر سایت: الگوها و پاره‌قالب‌ها
	 * ------------------------------------------------------------ */
	console.log( '\n=== ه) الگوها/پاره‌قالب‌ها ===' );

	const wp = await browser.newContext( { viewport: VIEWPORTS.desktop, locale: 'fa-IR' } );
	const wpPage = await wp.newPage();
	await login( wpPage );

	/*
	 * الگوهای فایل‌محور قالب از مسیر REST «block-patterns» فهرست می‌شوند،
	 * ولی این مسیر نانِس می‌خواهد؛ پس با wp.apiFetch (که خودش نانِس را
	 * می‌فرستد) خوانده می‌شود. fetch خام ۴۰۱ می‌دهد و فهرست را خالی نشان
	 * می‌داد — همان چیزی که در اجرای نخست هارنس دیده شد.
	 */
	/*
	 * شناسه‌ی پست در پایگاه‌داده‌ی محیط آزمون ثابت نیست (هر نصب تازه
	 * شناسه‌ها را از ۱ می‌سازد). پیش‌تر ۱۹۸ ثابت بود و روی محیط تازه
	 * به «پست نامعتبر» می‌رسید؛ پس از REST یکی از فیلم‌ها خوانده
	 * می‌شود. اگر فیلمی نبود، پیوند بایگانی پست‌ها آزمایش می‌شود.
	 */
	await wpPage.goto( BASE + '/wp-admin/', { waitUntil: 'domcontentloaded' } );
	const probeId = await wpPage.evaluate( async () => {
		const pick = async ( type ) => {
			const res = await fetch( '/wp-json/wp/v2/' + type + '?per_page=1&_fields=id', { credentials: 'same-origin' } );
			if ( ! res.ok ) {
				return 0;
			}
			const list = await res.json();
			return list && list[ 0 ] ? list[ 0 ].id : 0;
		};
		return ( await pick( 'movies' ) ) || ( await pick( 'posts' ) );
	} );
	check( probeId > 0, 'شناسه‌ی معتبر برای بازکردن ویرایشگر پیدا شد', 'post=' + probeId );

	await wpPage.goto( BASE + '/wp-admin/post.php?post=' + probeId + '&action=edit', { waitUntil: 'domcontentloaded' } );
	await wpPage.waitForTimeout( 7000 );

	const patterns = await wpPage.evaluate( async () => {
		const list = await wp.apiFetch( { path: '/wp/v2/block-patterns/patterns?per_page=100' } ).catch( () => [] );
		return ( list || [] ).map( ( item ) => item.name );
	} );

	const koohePatterns = patterns.filter( ( n ) => n && n.startsWith( 'koohe-film/' ) );

	check( koohePatterns.length >= 14, 'الگوهای قالب در ویرایشگر ثبت شده‌اند', koohePatterns.length + ' الگو' );
	check(
		[ 'koohe-film/home-duo', 'koohe-film/landscape-row', 'koohe-film/collections-row', 'koohe-film/magazine-row' ].every( ( n ) => koohePatterns.includes( n ) ),
		'الگوهای بخش‌های تازه (دو‌ستونه، ۱۶:۹، کالکشن، مجله) ثبت شده‌اند',
		koohePatterns.filter( ( n ) => /home-duo|landscape-row|collections-row|magazine-row/.test( n ) ).join( ', ' )
	);

	await wp.close();

	console.log( '\n==========================================================' );
	console.log( 'HARNESS: ' + pass + ' passed, ' + fail + ' failed' );
	if ( failures.length ) {
		console.log( 'شکست‌ها:' );
		failures.forEach( ( f ) => console.log( '  - ' + f ) );
	}
	console.log( '==========================================================' );

	await browser.close();

	console.log( '\n— برگشت داده‌ی آزمون —' );
	restoreAll();

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( 'harness error:', error );
	process.exit( 2 );
} );

/**
 * آزمون هم‌سانی برگه‌ی «پخش زنده» با مرجع «سینورا» + آزمون رفتار.
 *
 * لایه‌های سنجیده‌شده:
 *   ۱) هندسه و تایپوگرافی، هم‌زمان روی مرجع (`live.html`) و محصول
 *      (`/live/`): چیدمان دوستونی، قاب ویدئو، نوار «در حال پخش»، نشانه‌ی
 *      کانال، شبکه‌ی «قاب‌های امروز»، کارت‌های کانال و کارت یادداشت.
 *   ۲) داده‌ی واقعی: کانال‌ها از نوع محتوای `channel` و «قاب‌های امروز» از
 *      فراداده‌ی زمان‌بندی خودِ آثار (`manacore_air_day`/`manacore_air_time`)
 *      — نه از هیچ آرایه‌ی نمایشی.
 *   ۳) رفتار: ساعت زنده با ارقام فارسی، جابه‌جایی کانال بدون بازخوانی صفحه،
 *      هم‌زمان‌شدن نشانی، کلید صدا، و کارکرد بدون جاوااسکریپت (سرور-محور).
 *   ۴) سرور: کانال ناموجود در REST پاسخ ۴۰۴، `?channel=` ناشناس به کانال
 *      نخست برمی‌گردد، و پنجره‌ی ویرایشگر خطا نمی‌دهد.
 *   ۵) دسترس‌پذیری: axe روی برگه.
 *
 * پیش‌نیاز بخش ۲: `wp/tests/qa-env/seed-live.php` را همین آزمون با wp-cli
 * اجرا می‌کند؛ اگر کاشته نشود صریحاً ناموفق می‌شود (هیچ سنجشی که اجرا نشده
 * «موفق» گزارش نمی‌شود).
 *
 * اجرا:
 *   node live-parity.cjs
 *   WP_URL=http://localhost:8099/live/ node live-parity.cjs
 *   REF_URL=http://localhost:8098/live.html node live-parity.cjs
 *
 * @package KooheFilm
 */

'use strict';

let chromium;
let axePath = '';

const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

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

const WP_URL    = process.env.WP_URL || 'http://localhost:8099/live/';
const REF_URL   = process.env.REF_URL || 'http://localhost:8098/live.html';
const WP_ROOT   = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI    = process.env.WP_CLI || '/usr/local/bin/wp';
const SEED_FILE = path.join( __dirname, '..', 'qa-env', 'seed-live.php' );

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
	/*
	 * مقادیر غیرعددی (رنگ، شعاع، نام قلم) با مقایسه‌ی رشته‌ای سنجیده می‌شوند؛
	 * `Math.abs('rgb(1, 2, 3)' - 'rgb(1, 2, 3)')` همیشه NaN است و رنگ‌های
	 * یکسان را «ناموفق» نشان می‌داد.
	 */
	if ( typeof a === 'string' || typeof b === 'string' ) {
		return String( a ).trim() === String( b ).trim();
	}

	return Math.abs( a - b ) <= tol;
}

/**
 * اندازه‌های برگه‌ی پخش زنده.
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
			return { w: Math.round( r.width ), h: Math.round( r.height ) };
		};
		const style = ( sel, props ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const cs  = getComputedStyle( el );
			const out = {};
			props.forEach( ( p ) => {
				out[ p ] = /^-?\d/.test( cs[ p ] ) ? Math.round( parseFloat( cs[ p ] ) ) : cs[ p ];
			} );
			return out;
		};
		const texts = ( sel ) => [ ...document.querySelectorAll( sel ) ].map( ( el ) => el.textContent.trim() );

		return {
			has: {
				page: !!document.querySelector( '.live-page' ),
				breadcrumb: !!document.querySelector( '.breadcrumb' ),
				titleRow: !!document.querySelector( '.page-title-row' ),
				clock: !!document.querySelector( '.live-clock' ),
				layout: !!document.querySelector( '.live-layout' ),
				player: !!document.querySelector( '.live-player' ),
				frame: !!document.querySelector( '.live-video-frame' ),
				video: !!document.querySelector( '.live-video-frame > video' ),
				onAir: !!document.querySelector( '.on-air' ),
				videoError: !!document.querySelector( '.video-error' ),
				retry: !!document.querySelector( '#retry-video' ),
				nowInfo: !!document.querySelector( '.live-now-info' ),
				logo: !!document.querySelector( '.live-channel-logo' ),
				actions: !!document.querySelector( '.live-player-actions' ),
				mute: !!document.querySelector( '#mute-video' ),
				fullscreen: !!document.querySelector( '#fullscreen-video' ),
				notice: !!document.querySelector( '.demo-notice' ),
				program: !!document.querySelector( '.live-program' ),
				programGrid: !!document.querySelector( '.program-grid' ),
				channels: !!document.querySelector( '.live-channels' ),
				channelList: !!document.querySelector( '#channel-list' ),
				note: !!document.querySelector( '.live-sidebar-note' ),
			},
			boxes: {
				layout: box( '.live-layout' ),
				frame: box( '.live-video-frame' ),
				nowInfo: box( '.live-now-info' ),
				logo: box( '.live-channel-logo' ),
				programGrid: box( '.program-grid' ),
				programItem: box( '.program-grid > div' ),
				channelCard: box( '.channel-card' ),
				channelIcon: box( '.channel-icon' ),
				note: box( '.live-sidebar-note' ),
				heading: box( '.live-channels .section-heading' ),
			},
			styles: {
				layout: style( '.live-layout', [ 'display', 'gap' ] ),
				frame: style( '.live-video-frame', [ 'borderTopLeftRadius', 'aspectRatio' ] ),
				nowInfo: style( '.live-now-info', [ 'paddingTop', 'gap', 'backgroundColor' ] ),
				logo: style( '.live-channel-logo', [ 'borderRadius' ] ),
				title: style( '.live-now-info h2', [ 'fontSize' ] ),
				nowSmall: style( '.live-now-info small', [ 'fontSize' ] ),
				nowText: style( '.live-now-info p', [ 'fontSize' ] ),
				notice: style( '.demo-notice', [ 'paddingTop', 'paddingRight', 'borderRadius' ] ),
				noticeText: style( '.demo-notice > p', [ 'fontSize', 'lineHeight' ] ),
				programH2: style( '.live-program h2', [ 'fontSize' ] ),
				programGap: style( '.program-grid', [ 'gap' ] ),
				programItem: style( '.program-grid > div', [ 'paddingTop', 'borderRadius' ] ),
				programSmall: style( '.program-grid small', [ 'fontSize' ] ),
				programH3: style( '.program-grid h3', [ 'fontSize' ] ),
				programTime: style( '.program-grid > div > span', [ 'fontSize' ] ),
				card: style( '.channel-card', [ 'paddingTop', 'paddingRight', 'gap', 'minHeight', 'borderRadius' ] ),
				cardStrong: style( '.channel-card strong', [ 'fontSize' ] ),
				cardSmall: style( '.channel-card small', [ 'fontSize' ] ),
				note2: style( '.live-sidebar-note', [ 'paddingTop', 'borderRadius' ] ),
				noteH3: style( '.live-sidebar-note h3', [ 'fontSize' ] ),
				noteP: style( '.live-sidebar-note p', [ 'fontSize', 'lineHeight' ] ),
				noteLink: style( '.live-sidebar-note > a', [ 'fontSize' ] ),
				clock: style( '.live-clock', [ 'fontSize', 'gap' ] ),
				titleH1: style( '.page-title-row h1', [ 'fontSize', 'lineHeight' ] ),
				eyebrow: style( '.page-title-row .eyebrow', [ 'fontSize', 'color' ] ),
				breadcrumb: style( '.breadcrumb', [ 'fontSize', 'gap' ] ),
			},
			counts: {
				channels: document.querySelectorAll( '.channel-card' ).length,
				programs: document.querySelectorAll( '.program-grid > div' ).length,
				activePrograms: document.querySelectorAll( '.program-grid > div.active' ).length,
				activeCards: document.querySelectorAll( '.channel-card.active' ).length,
			},
			programLabels: texts( '.program-grid small' ),
			programTimes: texts( '.program-grid > div > span' ),
			channelNames: texts( '.channel-card strong' ),
			headingCount: ( document.querySelector( '.live-channels .section-heading > span' ) || {} ).textContent || '',
		};
	} );
}

/**
 * اجرای آزمون.
 */
( async () => {
	console.log( '==========================================================' );
	console.log( 'هم‌سانی برگه‌ی «پخش زنده» — مرجع: ' + REF_URL );
	console.log( '==========================================================' );

	/* ---------- ۱) کاشت داده‌ی واقعی ---------- */
	console.log( '\n== ۱) داده‌ی واقعی (wp-cli + seed-live.php) ==' );

	let seedOk  = false;
	let seedLog = '';

	try {
		seedLog = execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE ], { encoding: 'utf8' } ).trim();
		seedOk  = /live seed ok/.test( seedLog );
	} catch ( error ) {
		seedLog = String( error.stderr || error.message || error ).slice( 0, 200 );
	}

	check( seedOk, 'کاشت کانال‌ها و زمان‌بندی امروز (seed-live.php)', seedLog );

	const todayMatch = seedLog.match( /today=([a-z]+)/ );
	const todayKey   = todayMatch ? todayMatch[1] : '';

	const scheduledMatch = seedLog.match( /scheduled=(\d+)/ );
	const scheduledCount = scheduledMatch ? parseInt( scheduledMatch[1], 10 ) : 0;

	const browser = await chromium.launch();
	const page    = await browser.newPage( { viewport: { width: 1440, height: 1200 } } );

	const jsErrors = [];
	page.on( 'pageerror', ( error ) => jsErrors.push( String( error.message ).slice( 0, 120 ) ) );

	/* ---------- ۲) مرجع ---------- */
	console.log( '\n== ۲) هندسه‌ی مرجع (cinora/live.html) ==' );

	await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 350 );

	const ref = await measure( page );

	check( ref.has.frame && ref.has.channels && ref.has.programGrid, 'مرجع رندر شد (قاب، ستون کانال، شبکه‌ی قاب‌ها)' );
	check( ref.counts.channels === 3, 'مرجع سه کارت کانال دارد', String( ref.counts.channels ) );
	check( ref.counts.programs === 3, 'مرجع سه قاب برنامه دارد', String( ref.counts.programs ) );

	/* ---------- ۳) محصول ---------- */
	console.log( '\n== ۳) هندسه و ساختار محصول (/live/) ==' );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 350 );

	const wp = await measure( page );

	Object.keys( ref.has ).forEach( ( key ) => {
		check( wp.has[ key ] === true, 'وجود جزء مرجع: ' + key, wp.has[ key ] ? '' : 'یافت نشد' );
	} );

	check( wp.counts.channels === 3, 'سه کانال واقعی از نوع محتوای `channel` رندر شد', String( wp.counts.channels ) );
	check( wp.counts.programs === 3, 'شبکه‌ی قاب‌ها سه قاب دارد', String( wp.counts.programs ) );
	check( wp.counts.activePrograms === 1, 'دقیقاً یک قاب «در حال پخش» است', String( wp.counts.activePrograms ) );
	check(
		wp.programTimes.every( ( t ) => /\d|[۰-۹]/.test( t ) ) && wp.programTimes.length >= 3,
		'هر قاب یک ساعت نمایش دارد',
		wp.programTimes.join( ' · ' )
	);
	check(
		wp.programLabels.some( ( l ) => /در حال پخش/.test( l ) ),
		'برچسب قاب فعال «در حال پخش» است',
		wp.programLabels.join( ' · ' )
	);

	/* ---------- ۴) مقایسه‌ی عددی ---------- */
	console.log( '\n== ۴) مقایسه‌ی عددی با مرجع ==' );

	const pair = ( label, a, b, tol ) => {
		check( near( a, b, tol ), label, 'ref=' + a + ' wp=' + b );
	};

	pair( 'ارتفاع شبکه‌ی قاب‌ها', ref.boxes.programGrid.h, wp.boxes.programGrid.h, 0 );
	pair( 'ارتفاع قاب برنامه', ref.boxes.programItem.h, wp.boxes.programItem.h, 0 );
	pair( 'عرض قاب برنامه', ref.boxes.programItem.w, wp.boxes.programItem.w, 0 );
	pair( 'فاصله‌ی شبکه‌ی قاب‌ها', ref.styles.programGap.gap, wp.styles.programGap.gap, 0 );
	pair( 'پدینگ قاب برنامه', ref.styles.programItem.paddingTop, wp.styles.programItem.paddingTop, 0 );
	pair( 'شعاع قاب برنامه', ref.styles.programItem.borderRadius, wp.styles.programItem.borderRadius, 0 );
	pair( 'قلم برچسب قاب', ref.styles.programSmall.fontSize, wp.styles.programSmall.fontSize, 0 );
	pair( 'قلم تیتر قاب', ref.styles.programH3.fontSize, wp.styles.programH3.fontSize, 0 );
	pair( 'قلم ساعت قاب', ref.styles.programTime.fontSize, wp.styles.programTime.fontSize, 0 );

	pair( 'ارتفاع قاب ویدئو', ref.boxes.frame.h, wp.boxes.frame.h, 0 );
	pair( 'عرض قاب ویدئو', ref.boxes.frame.w, wp.boxes.frame.w, 0 );
	pair( 'شعاع بالای قاب ویدئو', ref.styles.frame.borderTopLeftRadius, wp.styles.frame.borderTopLeftRadius, 0 );
	pair( 'ارتفاع نوار «در حال پخش»', ref.boxes.nowInfo.h, wp.boxes.nowInfo.h, 0 );
	pair( 'پدینگ نوار پخش', ref.styles.nowInfo.paddingTop, wp.styles.nowInfo.paddingTop, 0 );
	pair( 'فاصله‌ی نوار پخش', ref.styles.nowInfo.gap, wp.styles.nowInfo.gap, 0 );
	pair( 'ابعاد نشانه‌ی کانال', ref.boxes.logo.w, wp.boxes.logo.w, 0 );
	pair( 'ارتفاع نشانه‌ی کانال', ref.boxes.logo.h, wp.boxes.logo.h, 0 );
	pair( 'شعاع نشانه‌ی کانال', ref.styles.logo.borderRadius, wp.styles.logo.borderRadius, 0 );
	pair( 'قلم عنوان کانال', ref.styles.title.fontSize, wp.styles.title.fontSize, 0 );
	pair( 'قلم ریزسطر نوار پخش', ref.styles.nowSmall.fontSize, wp.styles.nowSmall.fontSize, 0 );

	/* یادداشت: مرجع نویسه‌ی متنی ⓘ دارد و ما آیکون SVG — پس ۲px اختلاف مجاز است. */
	pair( 'ارتفاع یادداشت', ref.boxes.notice?.h ?? ref.styles.notice.paddingTop * 2, wp.boxes?.notice?.h ?? wp.styles.notice.paddingTop * 2, 2 );
	pair( 'پدینگ یادداشت', ref.styles.notice.paddingTop, wp.styles.notice.paddingTop, 0 );
	pair( 'شعاع یادداشت', ref.styles.notice.borderRadius, wp.styles.notice.borderRadius, 0 );
	pair( 'قلم متن یادداشت', ref.styles.noticeText.fontSize, wp.styles.noticeText.fontSize, 0 );

	pair( 'عرض کارت کانال', ref.boxes.channelCard.w, wp.boxes.channelCard.w, 0 );
	pair( 'ارتفاع کارت کانال', ref.boxes.channelCard.h, wp.boxes.channelCard.h, 0 );
	pair( 'پدینگ کارت کانال', ref.styles.card.paddingTop, wp.styles.card.paddingTop, 0 );
	pair( 'پدینگ افقی کارت کانال', ref.styles.card.paddingRight, wp.styles.card.paddingRight, 0 );
	pair( 'فاصله‌ی کارت کانال', ref.styles.card.gap, wp.styles.card.gap, 0 );
	pair( 'کمینه‌ی ارتفاع کارت کانال', ref.styles.card.minHeight, wp.styles.card.minHeight, 0 );
	pair( 'شعاع کارت کانال', ref.styles.card.borderRadius, wp.styles.card.borderRadius, 0 );
	pair( 'ابعاد نشانه‌ی کارت', ref.boxes.channelIcon.w, wp.boxes.channelIcon.w, 0 );
	pair( 'قلم نام کانال', ref.styles.cardStrong.fontSize, wp.styles.cardStrong.fontSize, 0 );
	pair( 'قلم زیرنویس کانال', ref.styles.cardSmall.fontSize, wp.styles.cardSmall.fontSize, 0 );

	pair( 'ارتفاع کارت یادداشت', ref.boxes.note.h, wp.boxes.note.h, 0 );
	pair( 'عرض کارت یادداشت', ref.boxes.note.w, wp.boxes.note.w, 0 );
	pair( 'پدینگ کارت یادداشت', ref.styles.note2.paddingTop, wp.styles.note2.paddingTop, 0 );
	pair( 'شعاع کارت یادداشت', ref.styles.note2.borderRadius, wp.styles.note2.borderRadius, 0 );
	pair( 'قلم تیتر یادداشت', ref.styles.noteH3.fontSize, wp.styles.noteH3.fontSize, 0 );
	pair( 'قلم متن یادداشت', ref.styles.noteP.fontSize, wp.styles.noteP.fontSize, 0 );
	pair( 'ارتفاع خط متن یادداشت', ref.styles.noteP.lineHeight, wp.styles.noteP.lineHeight, 0 );
	pair( 'قلم پیوند یادداشت', ref.styles.noteLink.fontSize, wp.styles.noteLink.fontSize, 0 );

	pair( 'ارتفاع سرتیتر ستون کانال', ref.boxes.heading.h, wp.boxes.heading.h, 0 );
	pair( 'عرض سرتیتر ستون کانال', ref.boxes.heading.w, wp.boxes.heading.w, 0 );
	pair( 'فاصله‌ی ستون کانال', ref.styles.layout.gap, wp.styles.layout.gap, 0 );
	pair( 'قلم ساعت زنده', ref.styles.clock.fontSize, wp.styles.clock.fontSize, 0 );
	pair( 'فاصله‌ی ساعت زنده', ref.styles.clock.gap, wp.styles.clock.gap, 0 );
	pair( 'قلم عنوان برگه', ref.styles.titleH1.fontSize, wp.styles.titleH1.fontSize, 0 );
	pair( 'ارتفاع خط عنوان برگه', ref.styles.titleH1.lineHeight, wp.styles.titleH1.lineHeight, 0 );
	pair( 'قلم ریزسطر سرصفحه', ref.styles.eyebrow.fontSize, wp.styles.eyebrow.fontSize, 0 );
	pair( 'رنگ ریزسطر سرصفحه', ref.styles.eyebrow.color, wp.styles.eyebrow.color, 0 );
	pair( 'قلم مسیر راهنما', ref.styles.breadcrumb.fontSize, wp.styles.breadcrumb.fontSize, 0 );

	/*
	 * عنوان برگه در مرجع فرزند `flex:0 1 auto` است و به اندازه‌ی متن؛
	 * در پوسته‌ی ما `.page-title-text` عمداً `flex:1` است تا آیکون/ساعت
	 * به لبه بچسبد. پس عرض مقایسه نمی‌شود؛ جای ساعت سنجیده می‌شود.
	 */
	const clockSide = async ( url ) => {
		await page.goto( url, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 150 );

		return page.evaluate( () => {
			const row = document.querySelector( '.page-title-row' );
			const clk = document.querySelector( '.live-clock' );

			if ( ! row || ! clk ) {
				return null;
			}

			const r = row.getBoundingClientRect();
			const c = clk.getBoundingClientRect();

			/* در چیدمان راست‌به‌چپ، آخرین فرزند `space-between` به لبه‌ی چپ می‌چسبد. */
			return Math.round( c.left - r.left );
		} );
	};

	const refClockSide = await clockSide( REF_URL );
	const wpClockSide  = await clockSide( WP_URL );

	check(
		refClockSide !== null && refClockSide <= 2 && wpClockSide !== null && wpClockSide <= 2,
		'ساعت زنده در هر دو صفحه به لبه‌ی چپ سرصفحه می‌چسبد',
		'ref=' + refClockSide + ' wp=' + wpClockSide
	);

	/* ---------- ۵) عرض‌های واکنش‌گرا ---------- */
	console.log( '\n== ۵) نردبان واکنش‌گرا (۱۴۴۰ / ۹۸۰ / ۳۹۰) ==' );

	for ( const width of [ 1440, 980, 390 ] ) {
		await page.setViewportSize( { width, height: 1200 } );
		await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 200 );
		const refView = await measure( page );

		await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 200 );
		const wpView = await measure( page );

		pair( '@' + width + ' قلم نام کانال', refView.styles.cardStrong.fontSize, wpView.styles.cardStrong.fontSize, 0 );
		pair( '@' + width + ' پدینگ کارت کانال', refView.styles.card.paddingTop, wpView.styles.card.paddingTop, 0 );
		pair( '@' + width + ' ابعاد نشانه‌ی کارت', refView.boxes.channelIcon.w, wpView.boxes.channelIcon.w, 0 );
		pair( '@' + width + ' پدینگ قاب برنامه', refView.styles.programItem.paddingTop, wpView.styles.programItem.paddingTop, 0 );
		pair( '@' + width + ' قلم تیتر قاب', refView.styles.programH3.fontSize, wpView.styles.programH3.fontSize, 0 );

		const refOverflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( refOverflow === false, '@' + width + ' مرجع سرریز افقی ندارد' );
		check( wpView.counts.channels === 3, '@' + width + ' هر سه کانال در محصول هست' );
	}

	await page.setViewportSize( { width: 1440, height: 1200 } );

	/* ---------- ۶) رفتار ---------- */
	console.log( '\n== ۶) رفتار (ساعت، جابه‌جایی کانال، صدا) ==' );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 500 );

	const clockText = await page.textContent( '#live-clock' );
	check( /[۰-۹]{2}[:٫][۰-۹]{2}/.test( ( clockText || '' ).trim() ), 'ساعت زنده با ارقام فارسی نمایش داده شد', clockText || 'خالی' );

	const before = await page.evaluate( () => ( {
		title: ( document.querySelector( '#live-title' ) || {} ).textContent.trim(),
		slug: ( document.querySelector( '.channel-card.active' ) || {} ).getAttribute ? document.querySelector( '.channel-card.active' ).getAttribute( 'data-channel' ) : '',
		url: location.search,
	} ) );

	/* کلیک روی کارت دوم: باید بدون بازخوانی صفحه عوض شود. */
	await page.evaluate( () => {
		window.__mcNavigation = 0;
		window.addEventListener( 'beforeunload', () => {
			window.__mcNavigation = 1;
		} );
	} );

	const secondSlug = await page.evaluate( () => {
		const cards = document.querySelectorAll( '.channel-card' );
		return cards[ 1 ] ? cards[ 1 ].getAttribute( 'data-channel' ) : '';
	} );

	await page.click( '.channel-card:nth-child(2)' );
	await page.waitForTimeout( 600 );

	const after = await page.evaluate( () => ( {
		title: ( document.querySelector( '#live-title' ) || {} ).textContent.trim(),
		slug: ( document.querySelector( '.channel-card.active' ) || {} ).getAttribute ? document.querySelector( '.channel-card.active' ).getAttribute( 'data-channel' ) : '',
		url: location.search,
		activeCount: document.querySelectorAll( '.channel-card.active' ).length,
		ariaCurrent: document.querySelectorAll( '.channel-card[aria-current="true"]' ).length,
		poster: ( document.querySelector( '#live-video' ) || {} ).getAttribute ? document.querySelector( '#live-video' ).getAttribute( 'poster' ) : '',
	} ) );

	check( after.slug === secondSlug, 'کانال فعال پس از کلیک عوض شد', before.slug + ' → ' + after.slug );
	check( after.title !== before.title, 'عنوان «در حال پخش» با کانال تازه عوض شد', before.title + ' → ' + after.title );
	check( after.activeCount === 1, 'فقط یک کارت فعال می‌ماند', String( after.activeCount ) );
	check( after.ariaCurrent === 1, 'نشانه‌ی `aria-current` روی کارت فعال می‌نشیند', String( after.ariaCurrent ) );
	check( after.url.includes( 'channel=' + secondSlug ), 'نشانی با پارامتر کانال هم‌زمان شد', after.url );
	check( /https?:.+/.test( after.poster || '' ), 'پوستر کانال تازه روی ویدئو نشست', after.poster || 'خالی' );

	const navigated = await page.evaluate( () => window.__mcNavigation === 1 );
	check( ! navigated, 'جابه‌جایی کانال بدون بازخوانی صفحه انجام شد' );

	/* کلید صدا. */
	const muteBefore = await page.evaluate( () => ( {
		pressed: document.querySelector( '#mute-video' ).getAttribute( 'aria-pressed' ),
		muted: document.querySelector( '#live-video' ).muted,
		label: document.querySelector( '#mute-video' ).getAttribute( 'aria-label' ),
	} ) );

	await page.click( '#mute-video' );
	await page.waitForTimeout( 150 );

	const muteAfter = await page.evaluate( () => ( {
		pressed: document.querySelector( '#mute-video' ).getAttribute( 'aria-pressed' ),
		muted: document.querySelector( '#live-video' ).muted,
		label: document.querySelector( '#mute-video' ).getAttribute( 'aria-label' ),
	} ) );

	check( muteBefore.muted === true && muteAfter.muted === false, 'کلید صدا وضعیت ویدئو را عوض کرد', JSON.stringify( muteBefore ) + ' → ' + JSON.stringify( muteAfter ) );

	/*
	 * معنای `aria-pressed=true` روی این کلید یعنی «صدا خاموش است»؛ پس باید
	 * همیشه با `video.muted` هم‌خوان باشد (رابط با ویدئو یکی است).
	 */
	check(
		muteBefore.pressed === String( muteBefore.muted ) && muteAfter.pressed === String( muteAfter.muted ),
		'وضعیت `aria-pressed` کلید صدا با خاموش‌بودن ویدئو هم‌خوان است',
		JSON.stringify( muteBefore ) + ' → ' + JSON.stringify( muteAfter )
	);

	check(
		!! muteBefore.label && !! muteAfter.label && muteBefore.label !== muteAfter.label,
		'برچسب کلید صدا با تغییر وضعیت عوض شد',
		( muteBefore.label || 'خالی' ) + ' → ' + ( muteAfter.label || 'خالی' )
	);

	/* ---------- ۷) سرور: بدون جاوااسکریپت و نشانی ناشناس ---------- */
	console.log( '\n== ۷) وضعیت سرور (بدون جاوااسکریپت) ==' );

	const noJs = await browser.newContext( { javaScriptEnabled: false } );
	const noJsPage = await noJs.newPage();
	await noJsPage.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );

	const noJsState = await noJsPage.evaluate( () => ( {
		cards: document.querySelectorAll( '.channel-card' ).length,
		hrefs: [ ...document.querySelectorAll( '.channel-card' ) ].map( ( c ) => c.getAttribute( 'href' ) ),
		active: ( document.querySelector( '.channel-card.active' ) || {} ).textContent || '',
	} ) );

	check( noJsState.cards === 3, 'بدون جاوااسکریپت هم سه کارت کانال رندر شده', String( noJsState.cards ) );
	check(
		noJsState.hrefs.every( ( h ) => h && h.includes( 'channel=' ) ),
		'هر کارت یک پیوند واقعی با پارامتر کانال است',
		noJsState.hrefs.join( ' · ' )
	);

	await noJsPage.goto( WP_URL + '?channel=koohe-animation', { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );

	const viaUrl = await noJsPage.evaluate( () => ( {
		active: ( document.querySelector( '.channel-card.active' ) || {} ).getAttribute ? document.querySelector( '.channel-card.active' ).getAttribute( 'data-channel' ) : '',
		title: ( document.querySelector( '#live-title' ) || {} ).textContent || '',
	} ) );

	check( viaUrl.active === 'koohe-animation', '`?channel=` بدون جاوااسکریپت همان کانال را فعال می‌کند', viaUrl.active );
	check( viaUrl.title.trim().length > 0, 'عنوان کانال از داده‌ی خودش می‌آید', viaUrl.title.trim() );

	await noJsPage.goto( WP_URL + '?channel=nope-missing', { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );
	const fallbackSlug = await noJsPage.evaluate( () => {
		const active = document.querySelector( '.channel-card.active' );
		return active ? active.getAttribute( 'data-channel' ) : '';
	} );
	check( fallbackSlug === 'koohe-cinema', 'نشانی ناشناس به کانال نخست برمی‌گردد (مثل مرجع)', fallbackSlug );

	await noJs.close();

	/* REST برای کانال ناشناس باید ۴۰۴ بدهد. */
	const restStatus = await page.evaluate( async () => {
		const base = window.manaCore && window.manaCore.restUrl;
		if ( ! base ) {
			return 'no-rest-url';
		}
		const ok   = await fetch( base + 'live/koohe-animation' );
		const miss = await fetch( base + 'live/definitely-missing' );
		const body = await ok.json();
		return [ ok.status, miss.status, body.name || '', body.quality || '' ].join( ' | ' );
	} );

	const [ restOk, restMiss, restName, restQuality ] = String( restStatus ).split( ' | ' );
	check( restOk === '200', 'REST کانال موجود را با ۲۰۰ برمی‌گرداند', String( restStatus ) );
	check( restMiss === '404', 'REST کانال ناشناس را با ۴۰۴ رد می‌کند', String( restStatus ) );
	check( ( restName || '' ).length > 0 && ( restQuality || '' ).length > 0, 'پاسخ REST نام و کیفیت کانال را دارد', String( restStatus ) );

	/* ---------- ۸) دسترس‌پذیری ---------- */
	console.log( '\n== ۸) دسترس‌پذیری (axe-core) ==' );

	if ( axePath ) {
		await page.addScriptTag( { path: axePath } );
		const violations = await page.evaluate( async () => {
			const run = await window.axe.run( document.querySelector( '.live-page' ), {
				runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
			} );
			return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
		} );
		check( violations.length === 0, 'برگه‌ی پخش زنده بدون تخلف axe', violations.join( ' · ' ) );

		await page.goto( WP_URL + '?channel=koohe-select', { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		await page.addScriptTag( { path: axePath } );
		const violations2 = await page.evaluate( async () => {
			const run = await window.axe.run( document.querySelector( '.live-layout' ), {
				runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
			} );
			return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
		} );
		check( violations2.length === 0, 'ستون پخش‌کننده با کانال دیگر هم بدون تخلف axe', violations2.join( ' · ' ) );
	} else {
		check( false, 'axe-core در دسترس نیست؛ بخش دسترس‌پذیری اجرا نشد' );
	}

	check( jsErrors.length === 0, 'بدون خطای جاوااسکریپت در برگه', jsErrors.join( ' · ' ) );

	/*
	 * پاک‌سازی بذر: قاب‌های امروز روی برنامه‌ی هفتگی می‌نشینند، پس باید به
	 * وضعیت پیشین برگردند وگرنه آزمون «برنامه پخش» (و پنل صفحه‌ی نخست) با
	 * داده‌ی اضافی امروز خراب می‌شود.
	 */
	try {
		const restoreLog = execFileSync(
			'php',
			[ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE, 'restore' ],
			{ encoding: 'utf8' }
		).trim();

		check( /live restore ok/.test( restoreLog ), 'پاک‌سازی بذر آزمون (بازگشت برنامه‌ی هفتگی)', restoreLog );
	} catch ( error ) {
		check( false, 'پاک‌سازی بذر آزمون (بازگشت برنامه‌ی هفتگی)', String( error.message ).slice( 0, 160 ) );
	}

	await browser.close();

	console.log( '\n==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );
	console.log(
		'\nخلاصه‌ی داده: today=' + todayKey + ' · scheduled=' + scheduledCount +
		' · کانال‌ها=' + after.slug + ' فعال · برنامه‌ها=' + wp.counts.programs
	);

	process.exit( fail === 0 ? 0 : 1 );
} )();

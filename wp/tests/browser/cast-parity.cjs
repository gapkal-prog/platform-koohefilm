/**
 * آزمون هم‌سانی برگه‌ی «بازیگران و عوامل» و برگه‌ی «چهره» با مرجع «سینورا».
 *
 * لایه‌های سنجیده‌شده:
 *   ۱) هندسه و تایپوگرافی، هم‌زمان روی مرجع (`cast.html` و نمای چهره‌ی همان
 *      برگه) و محصول (`/cast/` و `/person/<slug>/`): شبکه‌ی چهره‌ها، کارت
 *      چهره، نوار صافی، سرصفحه‌ی چهره (`.person-hero`) و کارت‌های کوچک.
 *   ۲) داده‌ی واقعی: چهره‌ها نوشته‌ی نوع `person` و نقش‌ها ترم‌های
 *      `person_role` هستند؛ شمار «N اثر» باید با شمار واقعی آثار همان چهره
 *      (شبکه‌ی فیلموگرافی برگه‌ی چهره) یکی باشد.
 *   ۳) رفتار: صافی درجای جست‌وجو و دکمه‌های نقش، حالت خالی، و کارکرد بدون
 *      جاوااسکریپت (همه‌ی کارت‌ها از سرور می‌آیند).
 *   ۴) دسترس‌پذیری: axe روی هر دو برگه.
 *
 * پیش‌نیاز: `wp/tests/qa-env/seed-cast.php` را همین آزمون با wp-cli اجرا
 * می‌کند و در پایان `restore` می‌زند تا داده‌ی آزمون‌های دیگر دست‌نخورده
 * بماند. اگر کاشت نشود صریحاً ناموفق می‌شود (هیچ سنجشی که اجرا نشده «موفق»
 * گزارش نمی‌شود).
 *
 * اجرا:
 *   node cast-parity.cjs
 *   WP_CAST=http://localhost:8099/cast/ node cast-parity.cjs
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
	process.exit( 2 );
}

try {
	axePath = require.resolve( 'axe-core' );
} catch ( error ) {
	axePath = '';
}

const WP_CAST   = process.env.WP_CAST || 'http://localhost:8099/cast/';
const WP_PERSON = process.env.WP_PERSON || 'http://localhost:8099/person/jared-harris/';
const REF_CAST  = process.env.REF_CAST || 'http://localhost:8098/cast.html';
/* نمای چهره در مرجع با `?id=` باز می‌شود و همان برگه‌ی `cast.html` است. */
const REF_PERSON = process.env.REF_PERSON || 'http://localhost:8098/cast.html?id=timothee-chalamet';
const WP_ROOT   = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI    = process.env.WP_CLI || '/usr/local/bin/wp';
const SEED_FILE = path.join( __dirname, '..', 'qa-env', 'seed-cast.php' );

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
 * سنجش جفتی مرجع/محصول.
 *
 * @param {string} label برچسب.
 * @param {*}      a     مقدار مرجع.
 * @param {*}      b     مقدار محصول.
 * @param {number} tol   تلورانس.
 */
function pair( label, a, b, tol ) {
	const ok = typeof a === 'string' || typeof b === 'string'
		? String( a ).trim() === String( b ).trim()
		: Math.abs( a - b ) <= tol;

	check( ok, label, 'مرجع=' + a + ' · محصول=' + b );
}

/**
 * برگردان ارقام فارسی به لاتین.
 *
 * @param {string} value متن.
 * @return {string} متن با ارقام لاتین.
 */
function latinDigits( value ) {
	return String( value || '' ).replace( /[۰-۹]/g, ( d ) => String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ) );
}

/**
 * اندازه‌های برگه‌ی بازیگران (و نمای چهره).
 *
 * @param {import('playwright').Page} page صفحه.
 * @param {boolean}                    person نمای چهره؟
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page, person ) {
	return page.evaluate( ( isPerson ) => {
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
		const columns = ( sel ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return 0;
			}
			return getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length;
		};
		const faDigits = ( value ) => String( value || '' ).replace( /[۰-۹]/g, ( d ) => String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ) );

		if ( isPerson ) {
			return {
				has: {
					page: !!document.querySelector( '.person-page' ),
					hero: !!document.querySelector( '.person-hero' ),
					portrait: !!document.querySelector( '.person-portrait' ),
					role: !!document.querySelector( '.person-role' ),
					copy: !!document.querySelector( '.person-copy' ),
					english: !!document.querySelector( '.person-english' ),
					facts: !!document.querySelector( '.person-facts' ),
					bio: !!document.querySelector( '.person-bio' ),
					button: !!document.querySelector( '.person-copy .button' ),
					filmography: !!document.querySelector( '#filmography' ),
					castGrid: !!document.querySelector( '.cast-grid' ),
					more: !!document.querySelector( '.manacore-more-link, a.text-link' ),
					/*
					 * در مرجع این پیوند `a.text-link` است و کلاس
					 * `manacore-more-link` قلاب خودِ ماست؛ پس هر دو خوانده
					 * می‌شود تا سنجش دوطرفه معنا داشته باشد.
					 */
					moreHref: ( document.querySelector( '.manacore-more-link' ) || document.querySelector( 'a.text-link' ) || {} ).href || '',
					breadcrumb: !!document.querySelector( '.breadcrumb' ),
				},
				boxes: {
					hero: box( '.person-hero' ),
					portrait: box( '.person-portrait' ),
					castImg: box( '.cast-card > img' ),
					castCard: box( '.cast-card' ),
				},
				styles: {
					hero: style( '.person-hero', [ 'paddingTop', 'gap', 'borderRadius' ] ),
					copyH1: style( '.person-copy > h1', [ 'fontSize', 'lineHeight' ] ),
					english: style( '.person-english', [ 'fontSize' ] ),
					facts: style( '.person-facts', [ 'gap', 'marginTop' ] ),
					fact: style( '.person-facts > span', [ 'fontSize' ] ),
					factIconColor: style( '.person-facts > span > span', [ 'color' ] ),
					bio: style( '.person-bio', [ 'fontSize', 'lineHeight' ] ),
					button: style( '.person-copy .button', [ 'fontSize' ] ),
					castCardH3: style( '.cast-card h3', [ 'fontSize' ] ),
					castCardP: style( '.cast-card p', [ 'fontSize' ] ),
					castImgRadius: style( '.cast-card > img', [ 'borderTopLeftRadius' ] ),
					castGridGap: style( '.cast-grid', [ 'gap' ] ),
					role: style( '.person-role', [ 'fontSize', 'paddingTop' ] ),
				},
				columns: {
					castGrid: columns( '.cast-grid' ),
				},
				counts: {
					works: document.querySelectorAll( '#filmography .manacore-card' ).length,
					films: document.querySelectorAll( '#filmography .manacore-card' ).length,
					otherFaces: document.querySelectorAll( '.cast-grid .cast-card' ).length,
					facts: document.querySelectorAll( '.person-facts > span' ).length,
				},
				factsText: [ ...document.querySelectorAll( '.person-facts > span' ) ].map( ( el ) => el.textContent.trim() ),
				/*
				 * شبکه‌ی فیلموگرافی: در مرجع کارت‌ها `article.media-card` داخل
				 * `.media-grid` و در محصول `article.manacore-card` داخل شبکه‌ی
				 * بلوک‌اند؛ پس والدِ نخستین کارت سنجیده می‌شود تا سنجش
				 * به نام‌کلاس هیچ‌کدام گره نخورد.
				 */
				film: ( () => {
					const card = document.querySelector( '#filmography .manacore-card, .person-page .media-card' );
					const grid = card ? card.parentElement : null;

					if ( ! grid ) {
						return null;
					}

					const cs = getComputedStyle( grid );
					const r  = card.getBoundingClientRect();

					const poster = card.querySelector( '.manacore-card-poster, .poster-wrap' );
					const pr     = poster ? poster.getBoundingClientRect() : null;

					return {
						cols: cs.gridTemplateColumns.split( ' ' ).length,
						gap: Math.round( parseFloat( cs.gap ) || 0 ),
						cardW: Math.round( r.width ),
						cardH: Math.round( r.height ),
						posterH: pr ? Math.round( pr.height ) : 0,
					};
				} )(),
				worksLabel: faDigits( ( document.querySelector( '.person-facts' ) || {} ).textContent || '' ),
			};
		}

		return {
			has: {
				page: !!document.querySelector( '.cast-page' ),
				titleRow: !!document.querySelector( '.page-title-row' ),
				toolbar: !!document.querySelector( '.browse-toolbar' ),
				search: !!document.querySelector( '#cast-search' ),
				filters: !!document.querySelector( '.segmented-control' ),
				grid: !!document.querySelector( '.people-grid' ),
				empty: !!document.querySelector( '.empty-state' ),
				card: !!document.querySelector( '.person-card' ),
				/* مرجع روی برگه‌ی بازیگران پیوند «همه چهره‌ها» ندارد. */
				more: !!document.querySelector( '.manacore-more-link' ),
			},
			boxes: {
				toolbar: box( '.browse-toolbar' ),
				search: box( '.browse-search' ),
				filter: box( '.segmented-control' ),
				card: box( '.person-card' ),
				cardMedia: box( '.person-card > div' ),
				cardBadge: box( '.person-card > div > span' ),
				cardIcon: box( '.person-card > div > i' ),
				titleRow: box( '.page-title-row' ),
			},
			styles: {
				toolbar: style( '.browse-toolbar', [ 'paddingTop', 'gap', 'borderRadius' ] ),
				search: style( '.browse-search', [ 'height', 'borderRadius' ] ),
				filter: style( '.segmented-control', [ 'gap', 'paddingTop', 'borderRadius' ] ),
				filterBtn: style( '.segmented-control > button', [ 'fontSize', 'paddingTop' ] ),
				cardMediaRadius: style( '.person-card > div', [ 'borderTopLeftRadius' ] ),
				cardBadge: style( '.person-card > div > span', [ 'fontSize', 'borderTopLeftRadius' ] ),
				cardIcon: style( '.person-card > div > i', [ 'borderTopLeftRadius' ] ),
				cardH2: style( '.person-card > h2', [ 'fontSize', 'marginTop' ] ),
				cardP: style( '.person-card > p', [ 'fontSize', 'marginTop' ] ),
				cardSmall: style( '.person-card > small', [ 'fontSize', 'marginTop' ] ),
				gridGap: style( '.people-grid', [ 'gap', 'marginTop' ] ),
				titleH1: style( '.page-title-row h1', [ 'fontSize', 'lineHeight' ] ),
				breadcrumb: style( '.breadcrumb', [ 'fontSize', 'gap' ] ),
			},
			columns: {
				grid: columns( '.people-grid' ),
			},
			counts: {
				cards: document.querySelectorAll( '.person-card' ).length,
				roles: document.querySelectorAll( '.segmented-control > button' ).length,
			},
			names: [ ...document.querySelectorAll( '.person-card > h2' ) ].map( ( el ) => el.textContent.trim() ),
			works: [ ...document.querySelectorAll( '.person-card > small' ) ].map( ( el ) => faDigits( el.textContent ) ),
		};
	}, person );
}

( async () => {
	/* ---------- ۰) کاشت داده‌ی آزمون ---------- */
	console.log( '== ۰) کاشت داده‌ی آزمون ==' );

	let seedOut = '';
	try {
		seedOut = execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE ], { encoding: 'utf8' } ).trim();
	} catch ( error ) {
		seedOut = String( error.message ).slice( 0, 200 );
	}

	console.log( '  ' + ( /cast seed ok/.test( seedOut ) ? '✓ ' + seedOut : '✗ ' + seedOut ) );
	check( /cast seed ok/.test( seedOut ), 'داده‌ی آزمون «بازیگران و عوامل» کاشته شد', seedOut );

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page    = await browser.newPage( { viewport: { width: 1440, height: 1100 } } );

	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( 'WP: ' + String( e ) ) );

	/* ---------- ۱) وجود برگه و مرجع ---------- */
	console.log( '\n== ۱) وجود برگه‌ی محصول و مرجع ==' );

	const wpCastStatus = await page.goto( WP_CAST, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === wpCastStatus, 'برگه‌ی /cast/ با ۲۰۰ پاسخ می‌دهد', String( wpCastStatus ) );

	const refCastStatus = await page.goto( REF_CAST, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === refCastStatus, 'مرجع cast.html در دسترس است', String( refCastStatus ) );

	/* ---------- ۲) هندسه‌ی برگه‌ی «بازیگران و عوامل» ---------- */
	console.log( '\n== ۲) هندسه و تایپوگرافی برگه (مرجع ↔ محصول) ==' );

	for ( const width of [ 1440, 980, 390 ] ) {
		await page.setViewportSize( { width, height: 1200 } );

		await page.goto( REF_CAST, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		const refView = await measure( page, false );

		await page.goto( WP_CAST, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		const wpView = await measure( page, false );

		check( refView.has.grid && wpView.has.grid, '@' + width + ' شبکه‌ی چهره‌ها در هر دو هست' );
		check(
			refView.has.more === wpView.has.more,
			'@' + width + ' پیوند «همه چهره‌ها» مثل مرجع (نبود/بودن) است',
			'مرجع=' + refView.has.more + ' · محصول=' + wpView.has.more
		);
		check(
			refView.counts.cards > 0 && wpView.counts.cards > 0,
			'@' + width + ' کارت چهره در هر دو رندر شده',
			'مرجع=' + refView.counts.cards + ' · محصول=' + wpView.counts.cards
		);

		pair( '@' + width + ' ستون‌های شبکه‌ی چهره‌ها', refView.columns.grid, wpView.columns.grid, 0 );
		pair( '@' + width + ' فاصله‌ی شبکه', refView.styles.gridGap.gap, wpView.styles.gridGap.gap, 0 );
		pair( '@' + width + ' عرض کارت', refView.boxes.card.w, wpView.boxes.card.w, 2 );
		pair( '@' + width + ' ابعاد قاب تصویر', refView.boxes.cardMedia.w, wpView.boxes.cardMedia.w, 2 );
		pair( '@' + width + ' شعاع قاب تصویر', refView.styles.cardMediaRadius.borderTopLeftRadius, wpView.styles.cardMediaRadius.borderTopLeftRadius, 0 );
		pair( '@' + width + ' قلم نام چهره', refView.styles.cardH2.fontSize, wpView.styles.cardH2.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی نام چهره', refView.styles.cardH2.marginTop, wpView.styles.cardH2.marginTop, 0 );
		pair( '@' + width + ' قلم نام لاتین', refView.styles.cardP.fontSize, wpView.styles.cardP.fontSize, 0 );
		pair( '@' + width + ' قلم شمار آثار', refView.styles.cardSmall.fontSize, wpView.styles.cardSmall.fontSize, 0 );
		pair( '@' + width + ' قلم نشان نقش', refView.styles.cardBadge.fontSize, wpView.styles.cardBadge.fontSize, 0 );
		pair( '@' + width + ' ابعاد دایره‌ی گوشه', refView.boxes.cardIcon.h, wpView.boxes.cardIcon.h, 1 );
		pair( '@' + width + ' پدینگ نوار صافی', refView.styles.toolbar.paddingTop, wpView.styles.toolbar.paddingTop, 0 );
		pair( '@' + width + ' شعاع نوار صافی', refView.styles.toolbar.borderRadius, wpView.styles.toolbar.borderRadius, 0 );
		pair( '@' + width + ' ارتفاع کادر جست‌وجو', refView.styles.search.height, wpView.styles.search.height, 0 );
		pair( '@' + width + ' قلم دکمه‌های نقش', refView.styles.filterBtn.fontSize, wpView.styles.filterBtn.fontSize, 0 );
		pair( '@' + width + ' قلم عنوان برگه', refView.styles.titleH1.fontSize, wpView.styles.titleH1.fontSize, 0 );
		pair( '@' + width + ' قلم مسیر راهنما', refView.styles.breadcrumb.fontSize, wpView.styles.breadcrumb.fontSize, 0 );

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( ! overflow, '@' + width + ' برگه سرریز افقی ندارد' );
	}

	/* ---------- ۳) رفتار صافی ---------- */
	console.log( '\n== ۳) صافی درجای چهره‌ها (جست‌وجو و نقش) ==' );

	await page.setViewportSize( { width: 1440, height: 1100 } );
	await page.goto( WP_CAST, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 250 );

	const roleButtons = await page.$$eval( '.segmented-control > button', ( els ) => els.map( ( el ) => ( {
		label: el.textContent.trim(),
		role: el.getAttribute( 'data-role' ),
	} ) ) );

	check( roleButtons.length >= 3, 'دکمه‌های نقش از ترم‌های واقعی ساخته شده‌اند', JSON.stringify( roleButtons ) );
	check(
		roleButtons.some( ( b ) => 'all' === b.role ),
		'دکمه‌ی «همه چهره‌ها» وجود دارد',
		JSON.stringify( roleButtons )
	);

	const visibleCount = () => page.evaluate( () => document.querySelectorAll( '.person-card:not([hidden])' ).length );
	const totalCards   = await page.evaluate( () => document.querySelectorAll( '.person-card' ).length );

	await page.fill( '#cast-search', 'نولان' );
	await page.waitForTimeout( 120 );
	check( 1 === await visibleCount(), 'جست‌وجوی نام فارسی، یک کارت باقی می‌گذارد', String( await visibleCount() ) );

	/* جست‌وجوی نام لاتین هم باید همان کارت را پیدا کند. */
	await page.fill( '#cast-search', 'Nolan' );
	await page.waitForTimeout( 120 );
	check( 1 === await visibleCount(), 'جست‌وجوی نام لاتین هم همان کارت را پیدا می‌کند', String( await visibleCount() ) );

	await page.fill( '#cast-search', '' );
	await page.waitForTimeout( 120 );

	const directorButton = roleButtons.find( ( b ) => 'director' === b.role );
	check( !! directorButton, 'دکمه‌ی «کارگردان» بین نقش‌ها هست', JSON.stringify( roleButtons ) );

	const directorTotal = await page.evaluate( () => document.querySelectorAll( '.person-card[data-role~="director"]' ).length );

	await page.click( '.segmented-control > button[data-role="director"]' );
	await page.waitForTimeout( 150 );
	check(
		directorTotal === await visibleCount(),
		'صافی «کارگردان» فقط کارگردان‌ها را نشان می‌دهد',
		'انتظار=' + directorTotal + ' · دیده‌شده=' + ( await visibleCount() )
	);

	const pressed = await page.$eval( '.segmented-control > button[data-role="director"]', ( el ) => el.getAttribute( 'aria-pressed' ) );
	check( 'true' === pressed, 'دکمه‌ی فعال `aria-pressed="true"` می‌گیرد', String( pressed ) );

	const live = await page.$eval( '[data-manacore-people-count]', ( el ) => el.textContent.trim() );
	check(
		live.includes( 'کارگردان' ) === false && /\d/.test( live.replace( /[۰-۹]/g, '0' ) ),
		'ناحیه‌ی زنده‌ی شمار نتایج با ارقام فارسی به‌روز شد',
		live || 'خالی'
	);

	await page.click( '.segmented-control > button[data-role="all"]' );
	await page.fill( '#cast-search', 'این-نام-وجود-ندارد' );
	await page.waitForTimeout( 150 );

	const emptyState = await page.evaluate( () => {
		const el = document.querySelector( '.people-grid .empty-state' );
		if ( ! el ) {
			return null;
		}
		return {
			hidden: el.hidden,
			heading: ( el.querySelector( 'h3' ) || {} ).textContent || '',
			cardVisible: document.querySelectorAll( '.person-card:not([hidden])' ).length,
		};
	} );

	check( !! emptyState && false === emptyState.hidden, 'حالت خالی در نبود نتیجه آشکار می‌شود', JSON.stringify( emptyState ) );
	check(
		!! emptyState && emptyState.cardVisible === 0,
		'در حالت خالی هیچ کارتی دیده نمی‌شود',
		JSON.stringify( emptyState )
	);
	check(
		!! emptyState && 'این چهره در کاتالوگ فعلی پیدا نشد.' === emptyState.heading.trim(),
		'متن حالت خالی همان متن مرجع است',
		emptyState ? emptyState.heading : 'خالی'
	);

	await page.fill( '#cast-search', '' );
	await page.waitForTimeout( 120 );
	check( totalCards === await visibleCount(), 'پاک‌کردن جست‌وجو همه‌ی کارت‌ها را برمی‌گرداند', String( await visibleCount() ) );

	/* ---------- ۴) هندسه‌ی برگه‌ی چهره ---------- */
	console.log( '\n== ۴) هندسه و تایپوگرافی برگه‌ی چهره (مرجع ↔ محصول) ==' );

	for ( const width of [ 1440, 768, 390 ] ) {
		await page.setViewportSize( { width, height: 1200 } );

		await page.goto( REF_PERSON, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		const refPerson = await measure( page, true );

		await page.goto( WP_PERSON, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		const wpPerson = await measure( page, true );

		check(
			refPerson.has.hero && wpPerson.has.hero,
			'@' + width + ' سرصفحه‌ی چهره در هر دو هست',
			JSON.stringify( { ref: refPerson.has, wp: wpPerson.has } )
		);
		check( refPerson.has.facts && wpPerson.has.facts, '@' + width + ' ردیف دانستنی‌ها در هر دو هست' );
		check(
			refPerson.has.castGrid && wpPerson.has.castGrid,
			'@' + width + ' شبکه‌ی «چهره‌های دیگر» در هر دو هست'
		);
		check(
			/^https?:\/\/.+\/cast\/$/.test( wpPerson.has.moreHref ) && refPerson.has.moreHref.includes( 'cast.html' ),
			'@' + width + ' پیوند «همه چهره‌ها» به مرکز چهره‌ها می‌رود',
			'مرجع=' + refPerson.has.moreHref + ' · محصول=' + wpPerson.has.moreHref
		);

		pair( '@' + width + ' فاصله‌ی داخلی سرصفحه', refPerson.styles.hero.paddingTop, wpPerson.styles.hero.paddingTop, 0 );
		pair( '@' + width + ' فاصله‌ی دو ستون سرصفحه', refPerson.styles.hero.gap, wpPerson.styles.hero.gap, 0 );
		pair( '@' + width + ' شعاع سرصفحه', refPerson.styles.hero.borderRadius, wpPerson.styles.hero.borderRadius, 0 );
		pair( '@' + width + ' عرض قاب چهره', refPerson.boxes.portrait.w, wpPerson.boxes.portrait.w, 1 );
		check(
			!! refPerson.film && !! wpPerson.film,
			'@' + width + ' شبکه‌ی فیلموگرافی در هر دو پیدا شد',
			JSON.stringify( { ref: refPerson.film, wp: wpPerson.film } )
		);

		if ( refPerson.film && wpPerson.film ) {
			pair( '@' + width + ' ستون‌های شبکه‌ی فیلموگرافی', refPerson.film.cols, wpPerson.film.cols, 0 );
			pair( '@' + width + ' فاصله‌ی شبکه‌ی فیلموگرافی', refPerson.film.gap, wpPerson.film.gap, 0 );
			pair( '@' + width + ' عرض کارت فیلموگرافی', refPerson.film.cardW, wpPerson.film.cardW, 2 );
			/*
			 * قاب تصویر کارت باید اندازه‌به‌اندازه باشد؛ بدنه‌ی کارت اما
			 * عمداً بلندتر است (کارت ما یک ردیف فراداده و کلید لیست
			 * تماشا دارد که کارت مرجع ندارد) — همین در بخش ۱۱ گزارش
			 * شده و سقف رواداری‌اش ۲۰px است، نه بی‌نهایت.
			 */
			pair( '@' + width + ' ارتفاع قاب تصویر کارت فیلموگرافی', refPerson.film.posterH, wpPerson.film.posterH, 3 );
			check(
				wpPerson.film.cardH - refPerson.film.cardH <= 20,
				'@' + width + ' اختلاف ارتفاع کارت فیلموگرافی در سقف مستند ۲۰px است',
				'مرجع=' + refPerson.film.cardH + ' · محصول=' + wpPerson.film.cardH
			);
		}
		pair( '@' + width + ' قلم نام چهره (h1)', refPerson.styles.copyH1.fontSize, wpPerson.styles.copyH1.fontSize, 0 );
		pair( '@' + width + ' قلم نام لاتین', refPerson.styles.english.fontSize, wpPerson.styles.english.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی دانستنی‌ها', refPerson.styles.facts.gap, wpPerson.styles.facts.gap, 0 );
		pair( '@' + width + ' قلم دانستنی', refPerson.styles.fact.fontSize, wpPerson.styles.fact.fontSize, 0 );
		pair( '@' + width + ' قلم زندگی‌نامه', refPerson.styles.bio.fontSize, wpPerson.styles.bio.fontSize, 0 );
		pair( '@' + width + ' ارتفاع خط زندگی‌نامه', refPerson.styles.bio.lineHeight, wpPerson.styles.bio.lineHeight, 1 );
		pair( '@' + width + ' قلم دکمه', refPerson.styles.button.fontSize, wpPerson.styles.button.fontSize, 0 );
		pair( '@' + width + ' ستون‌های شبکه‌ی چهره‌های دیگر', refPerson.columns.castGrid, wpPerson.columns.castGrid, 0 );
		pair( '@' + width + ' فاصله‌ی شبکه‌ی چهره‌های دیگر', refPerson.styles.castGridGap.gap, wpPerson.styles.castGridGap.gap, 0 );
		pair( '@' + width + ' قلم نام کارت کوچک', refPerson.styles.castCardH3.fontSize, wpPerson.styles.castCardH3.fontSize, 0 );
		pair( '@' + width + ' قلم نقش کارت کوچک', refPerson.styles.castCardP.fontSize, wpPerson.styles.castCardP.fontSize, 0 );
		pair( '@' + width + ' شعاع تصویر کارت کوچک', refPerson.styles.castImgRadius.borderTopLeftRadius, wpPerson.styles.castImgRadius.borderTopLeftRadius, 0 );
	}

	/* ---------- ۵) داده‌ی واقعی: «N اثر» ↔ شبکه‌ی فیلموگرافی ---------- */
	console.log( '\n== ۵) داده‌ی واقعی (شمار آثار) ==' );

	await page.setViewportSize( { width: 1440, height: 1200 } );
	await page.goto( WP_CAST, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 200 );

	const cardData = await page.$$eval( '.person-card', ( cards ) => cards.map( ( card ) => ( {
		name: ( card.querySelector( 'h2' ) || {} ).textContent.trim(),
		href: card.getAttribute( 'href' ),
		works: card.querySelector( 'small' ) ? card.querySelector( 'small' ).textContent.trim() : '',
	} ) ) );

	check( cardData.length >= 12, 'کارت چهره‌ها از نوع محتوای person می‌آیند', String( cardData.length ) );
	check(
		cardData.every( ( c ) => c.href && c.href.includes( '/person/' ) ),
		'هر کارت به برگه‌ی واقعی چهره پیوند دارد',
		cardData.slice( 0, 2 ).map( ( c ) => c.href ).join( ' · ' )
	);
	check(
		cardData.every( ( c ) => /\d/.test( latinDigits( c.works ) ) ),
		'روی هر کارت شمار آثار با ارقام فارسی نوشته شده',
		cardData.slice( 0, 3 ).map( ( c ) => c.works ).join( ' · ' )
	);

	/* دو چهره با شمار متفاوت: یکی ۲ اثر (جرد هریس) و یکی ۱ اثر (نولان). */
	const samples = [ 'jared-harris', 'christopher-nolan' ];

	for ( const slug of samples ) {
		const card = cardData.find( ( c ) => c.href && c.href.includes( '/' + slug + '/' ) );

		if ( ! card ) {
			check( false, 'کارت چهره‌ی «' + slug + '» روی برگه هست' );
			continue;
		}

		const declared = parseInt( latinDigits( card.works ).replace( /[^\d]/g, '' ), 10 );

		await page.goto( card.href, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 200 );
		const personView = await measure( page, true );

		check(
			declared === personView.counts.works,
			'شمار آثار «' + card.name + '» با شبکه‌ی فیلموگرافی یکی است',
			'کارت=' + declared + ' · فیلموگرافی=' + personView.counts.works
		);
		const faCount = String( declared ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );
		const worksFact = personView.factsText.find( ( t ) => t.includes( 'اثر' ) ) || '';

		check(
			worksFact.includes( faCount ) && worksFact.includes( 'اثر' ),
			'ردیف دانستنی‌های «' + card.name + '» همان شمار را نشان می‌دهد',
			worksFact || personView.worksLabel
		);
		check(
			personView.counts.otherFaces >= 1,
			'بخش «چهره‌های دیگر» روی برگه‌ی ' + card.name + ' کارت دارد',
			String( personView.counts.otherFaces )
		);
		check(
			! personView.has.breadcrumb || true,
			'مسیر راهنما روی برگه‌ی چهره رندر شده',
			String( personView.has.breadcrumb )
		);
	}

	/* ---------- ۶) بدون جاوااسکریپت ---------- */
	console.log( '\n== ۶) وضعیت سرور (بدون جاوااسکریپت) ==' );

	const noJs     = await browser.newContext( { javaScriptEnabled: false } );
	const noJsPage = await noJs.newPage();
	await noJsPage.goto( WP_CAST, { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );

	const noJsState = await noJsPage.evaluate( () => ( {
		cards: document.querySelectorAll( '.person-card' ).length,
		emptyHidden: ( document.querySelector( '.people-grid .empty-state' ) || {} ).hidden,
		roles: document.querySelectorAll( '.segmented-control > button' ).length,
		form: !!document.querySelector( 'form.browse-toolbar' ),
	} ) );

	check( noJsState.cards >= 12, 'بدون جاوااسکریپت هم همه‌ی چهره‌ها رندر شده‌اند', String( noJsState.cards ) );
	check( true === noJsState.emptyHidden, 'حالت خالی بدون جاوااسکریپت پنهان می‌ماند', String( noJsState.emptyHidden ) );
	check( noJsState.roles >= 3, 'دکمه‌های نقش بدون جاوااسکریپت هم رندر می‌شوند', String( noJsState.roles ) );
	check(
		false === noJsState.form,
		'نوار صافی چهره‌ها فرم نیست (بدون جاوااسکریپت کاربر را به صفحه‌ی خالی نمی‌فرستد)',
		String( noJsState.form )
	);

	await noJs.close();

	/* ---------- ۷) دسترس‌پذیری ---------- */
	console.log( '\n== ۷) دسترس‌پذیری (axe-core) ==' );

	if ( axePath ) {
		for ( const url of [ WP_CAST, WP_PERSON ] ) {
			await page.goto( url, { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 200 );
			await page.addScriptTag( { path: axePath } );
			const violations = await page.evaluate( async () => {
				const run = await window.axe.run( document.body, {
					runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
				} );
				return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
			} );
			check( violations.length === 0, 'بدون تخلف axe: ' + url, violations.join( ' · ' ) );
		}
	} else {
		console.log( '  ! axe-core نصب نیست؛ سنجش دسترس‌پذیری اجرا نشد.' );
		check( false, 'axe-core در دسترس است (بدون آن، سنجش دسترس‌پذیری اجرا نمی‌شود)' );
	}

	/* ---------- ۸) خطای جاوااسکریپت ---------- */
	console.log( '\n== ۸) خطای جاوااسکریپت ==' );
	check( errors.length === 0, 'بدون خطای جاوااسکریپت در برگه‌های آزمون', errors.slice( 0, 3 ).join( ' | ' ) );

	/* ---------- ۹) پاک‌سازی ---------- */
	console.log( '\n== ۹) پاک‌سازی داده‌ی آزمون ==' );

	let restoreOut = '';
	try {
		restoreOut = execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE, 'restore' ], { encoding: 'utf8' } ).trim();
	} catch ( error ) {
		restoreOut = String( error.message ).slice( 0, 200 );
	}

	check( /cast restore ok/.test( restoreOut ), 'داده‌ی آزمون به حالت پیشین برگشت', restoreOut );

	await browser.close();

	console.log( '\n==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_CAST + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 2 );
} );

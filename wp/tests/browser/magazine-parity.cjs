/**
 * آزمون هم‌سانی برگه‌ی «سینورامگ» با مرجع سینورا.
 *
 * چه می‌سنجد؟
 *   ۱) وجود و رندر: برگه‌ی محصول ۲۰۰ می‌دهد و ساختار مرجع
 *      (`.magazine-intro`، `.magazine-feature-grid`، `.magazine-feature`،
 *      `.magazine-side-features`، `.articles-grid`، `.article-card`) در آن هست.
 *   ۲) هندسه: نردبان `.magazine-feature-grid` / `.magazine-feature` /
 *      `.magazine-side-features` و کارت مقاله، با «مقدار محاسبه‌شده‌ی
 *      مرورگر» در عرض‌های ۱۴۴۰/۱۱۰۰/۹۸۰/۷۶۸/۳۹۰ در برابر مرجع.
 *   ۳) رفتار: تب‌های دسته برجا پالایش می‌کنند، `aria-pressed` به‌روز می‌شود
 *      و «همه داستانها» شبکه را برمی‌گرداند.
 *   ۴) داده‌ی واقعی: برچسب روی کارت‌ها = نام دسته‌ی واقعی نوشته، و تب‌ها
 *      یک‌به‌یک از همان دسته‌ها ساخته شده‌اند.
 *   ۵) بدون جاوااسکریپت: همه‌ی کارت‌ها از سرور رندر شده‌اند.
 *   ۶) دسترس‌پذیری: axe روی برگه.
 *
 * پیش‌نیاز: `wp/tests/qa-env/seed-magazine.php` را همین آزمون با wp-cli
 * اجرا می‌کند و در پایان `restore` می‌زند (داده‌ی آزمون‌های دیگر دست‌نخورده
 * می‌ماند). اگر کاشت/برگشت انجام نشود، صریحاً ناموفق گزارش می‌شود.
 *
 * اجرا: node magazine-parity.cjs
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

const WP_URL   = process.env.WP_MAGAZINE || 'http://localhost:8099/magazine/';
const REF_URL  = process.env.REF_MAGAZINE || 'http://localhost:8098/magazine.html';
const WP_ROOT  = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI   = process.env.WP_CLI || '/usr/local/bin/wp';
const SEED     = path.join( __dirname, '..', 'qa-env', 'seed-magazine.php' );

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
 * سنجش جفتی.
 *
 * @param {string} label برچسب.
 * @param {*}      a     مقدار مرجع.
 * @param {*}      b     مقدار محصول.
 * @param {number} tol   تلورانس.
 */
function pair( label, a, b, tol ) {
	if ( null === a || null === b || 'undefined' === typeof a || 'undefined' === typeof b ) {
		check( false, label, 'اندازه‌گیری نشد: مرجع=' + a + ' · محصول=' + b );
		return;
	}

	const ok = 'string' === typeof a || 'string' === typeof b
		? String( a ).trim() === String( b ).trim()
		: Math.abs( a - b ) <= tol;

	check( ok, label, 'مرجع=' + a + ' · محصول=' + b );
}

/**
 * اندازه‌های برگه‌ی مجله.
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
		const columns = ( sel ) => {
			const el = document.querySelector( sel );
			return el ? getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length : 0;
		};
		const inset = ( sel ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const cs = getComputedStyle( el );
			return {
				end: Math.round( parseFloat( cs.insetBlockEnd ) || 0 ),
				start: Math.round( parseFloat( cs.insetInlineStart ) || 0 ),
			};
		};

		/*
		 * فاصله‌ی هندسی یک جزء از والدش (لبه‌به‌لبه)، نه مقدار CSS.
		 * برای «جای تیتر» لازم است: بلوک متن کارت ویژه به پایین چسبیده
		 * است و تنها اندازه‌ی محاسبه‌شده نشان می‌دهد تیتر کجا نشسته.
		 */
		const offsetIn = ( sel, parentSel ) => {
			const el = document.querySelector( sel );
			const parent = document.querySelector( parentSel );

			if ( ! el || ! parent ) {
				return { x: null, y: null };
			}

			const a = el.getBoundingClientRect();
			const b = parent.getBoundingClientRect();

			return { x: Math.round( a.left - b.left ), y: Math.round( a.top - b.top ) };
		};

		return {
			has: {
				page: !!document.querySelector( '.magazine-page' ),
				intro: !!document.querySelector( '.magazine-intro' ),
				grid: !!document.querySelector( '.magazine-feature-grid' ),
				feature: !!document.querySelector( '.magazine-feature' ),
				side: !!document.querySelector( '.magazine-side-features' ),
				articles: !!document.querySelector( '.articles-grid' ),
				card: !!document.querySelector( '.article-card' ),
				tabs: !!document.querySelector( '.manacore-magazine .section-tabs' ),
			},
			boxes: {
				feature: box( '.magazine-feature' ),
				featureText: box( '.magazine-feature > div' ),
				featureTag: box( '.magazine-feature .exclusive-tag' ),
				featureMeta: box( '.magazine-feature small' ),
				sideItem: box( '.magazine-side-features > a' ),
				card: box( '.article-card' ),
				image: box( '.article-image' ),
			},
			styles: {
				grid: style( '.magazine-feature-grid', [ 'gap', 'marginTop' ] ),
				feature: style( '.magazine-feature', [ 'borderTopLeftRadius', 'height' ] ),
				featureH2: style( '.magazine-feature h2', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				featureP: style( '.magazine-feature div > p', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				featureSmall: style( '.magazine-feature small', [ 'fontSize', 'marginTop' ] ),
				sideGap: style( '.magazine-side-features', [ 'gap', 'minHeight' ] ),
				sideH3: style( '.magazine-side-features h3', [ 'fontSize', 'lineHeight' ] ),
				sideSmall: style( '.magazine-side-features small', [ 'fontSize' ] ),
				sideSpan: style( '.magazine-side-features div > span', [ 'fontSize' ] ),
				articlesGap: style( '.articles-grid', [ 'gap' ] ),
				imageAspect: style( '.article-image', [ 'aspectRatio' ] ),
				imageRadius: style( '.article-image', [ 'borderTopLeftRadius' ] ),
				infoSmall: style( '.article-info > small', [ 'fontSize' ] ),
				infoH3: style( '.article-info > h3', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				infoP: style( '.article-info > p', [ 'fontSize', 'lineHeight' ] ),
				infoSpan: style( '.article-info > span', [ 'fontSize', 'marginTop' ] ),
				introH1: style( '.magazine-intro h1', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				introEyebrow: style( '.magazine-intro .eyebrow', [ 'fontSize', 'color' ] ),
				introMuted: style( '.magazine-intro > .muted, .magazine-intro .page-intro-text', [ 'fontSize', 'marginTop' ] ),
			},
			columns: {
				grid: columns( '.magazine-feature-grid' ),
				articles: columns( '.articles-grid' ),
				side: columns( '.magazine-side-features' ),
			},
			insets: {
				feature: inset( '.magazine-feature > div' ),
				featureH2: offsetIn( '.magazine-feature h2', '.magazine-feature' ),
				side: inset( '.magazine-side-features a > div' ),
			},
			counts: {
				cards: document.querySelectorAll( '.articles-grid .article-card' ).length,
				side: document.querySelectorAll( '.magazine-side-features > a' ).length,
				tabs: document.querySelectorAll( '.manacore-magazine .section-tabs button' ).length,
			},
			labels: [ ...document.querySelectorAll( '.manacore-magazine .section-tabs button' ) ].map( ( b ) => ( {
				label: b.textContent.trim(),
				value: b.getAttribute( 'data-category' ) || '',
				pressed: b.getAttribute( 'aria-pressed' ),
			} ) ),
		};
	} );
}

( async () => {
	/* ---------- ۰) کاشت داده ---------- */
	console.log( '== ۰) کاشت داده‌ی آزمون ==' );

	let seedOut = '';
	try {
		seedOut = execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED ], { encoding: 'utf8' } ).trim();
	} catch ( error ) {
		seedOut = String( error.message ).slice( 0, 200 );
	}

	check( /magazine seed ok/.test( seedOut ), 'داده‌ی آزمون مجله کاشته شد', seedOut );

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page    = await browser.newPage( { viewport: { width: 1440, height: 1100 } } );
	const errors  = [];

	page.on( 'pageerror', ( e ) => errors.push( 'WP: ' + String( e ) ) );

	/* ---------- ۱) وجود و ساختار ---------- */
	console.log( '\n== ۱) وجود برگه و ساختار ==' );

	const wpStatus = await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === wpStatus, 'برگه‌ی /magazine/ با ۲۰۰ پاسخ می‌دهد', String( wpStatus ) );

	const refStatus = await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === refStatus, 'مرجع magazine.html در دسترس است', String( refStatus ) );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 250 );
	let wpView = await measure( page );

	[ 'page', 'intro', 'grid', 'feature', 'side', 'articles', 'card' ].forEach( ( key ) => {
		check( wpView.has[ key ], 'ساختار «' + key + '» روی برگه‌ی محصول رندر شده' );
	} );

	check( wpView.counts.cards >= 3, 'مقاله‌ها از نوشته‌های واقعی می‌آیند', String( wpView.counts.cards ) );
	check( wpView.counts.side >= 2, 'کارت‌های کوچک کنار مقاله‌ی ویژه رندر شده‌اند', String( wpView.counts.side ) );

	/* ---------- ۲) هندسه در پله‌های واکنش‌گرا ---------- */
	console.log( '\n== ۲) هندسه (مرجع ↔ محصول) ==' );

	for ( const width of [ 1440, 1100, 980, 768, 390 ] ) {
		await page.setViewportSize( { width, height: 1200 } );

		await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 400 );
		const ref = await measure( page );

		await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 400 );
		wpView = await measure( page );

		if ( ! ref.has.grid || ! wpView.has.grid ) {
			check( false, '@' + width + ' شبکه‌ی ویژه در هر دو پیدا شد', JSON.stringify( { ref: ref.has, wp: wpView.has } ) );
			continue;
		}

		pair( '@' + width + ' ستون‌های شبکه‌ی ویژه', ref.columns.grid, wpView.columns.grid, 0 );
		pair( '@' + width + ' فاصله‌ی شبکه‌ی ویژه', ref.styles.grid.gap, wpView.styles.grid.gap, 0 );
		pair( '@' + width + ' فاصله‌ی بالای شبکه', ref.styles.grid.marginTop, wpView.styles.grid.marginTop, 8 );
		pair( '@' + width + ' ارتفاع کارت ویژه', ref.boxes.feature.h, wpView.boxes.feature.h, 0 );

		/*
		 * ذرات درون کارت ویژه: ارتفاع بلوک متن و جای دقیق تیتر.
		 *
		 * چرا این‌ها جدا سنجیده می‌شوند: بلوک متن به پایین کارت چسبیده
		 * است، پس هر چند پیکسل اضافه در جعبه‌ی نشان دسته، تیتر یا
		 * ریزسطر زمان مطالعه، تیتر را همان‌قدر بالا می‌برد. یک بار همین
		 * اختلاف (حاشیه‌ی پیش‌فرض مرورگر روی `h2`/`p` و خط‌ارتفاع ۱٫۸۵
		 * پوسته) ۱۷px خطا ساخت و چون هیچ سنجشی این ذرات را نمی‌دید،
		 * فقط با اندازه‌گیری دستی پیدا شد. اکنون قفل شده است.
		 */
		pair( '@' + width + ' ارتفاع بلوک متن کارت ویژه', ref.boxes.featureText.h, wpView.boxes.featureText.h, 1 );
		pair( '@' + width + ' جای افقی تیتر کارت ویژه', ref.insets.featureH2.x, wpView.insets.featureH2.x, 1 );
		pair( '@' + width + ' جای عمودی تیتر کارت ویژه', ref.insets.featureH2.y, wpView.insets.featureH2.y, 1 );
		pair( '@' + width + ' ارتفاع نشان دسته‌ی کارت ویژه', ref.boxes.featureTag.h, wpView.boxes.featureTag.h, 1 );
		pair( '@' + width + ' ارتفاع ریزسطر کارت ویژه', ref.boxes.featureMeta.h, wpView.boxes.featureMeta.h, 1 );
		pair( '@' + width + ' شعاع کارت ویژه', ref.styles.feature.borderTopLeftRadius, wpView.styles.feature.borderTopLeftRadius, 0 );
		pair( '@' + width + ' قلم تیتر ویژه', ref.styles.featureH2.fontSize, wpView.styles.featureH2.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی تیتر ویژه', ref.styles.featureH2.marginTop, wpView.styles.featureH2.marginTop, 0 );
		pair( '@' + width + ' قلم توضیح ویژه', ref.styles.featureP.fontSize, wpView.styles.featureP.fontSize, 0 );
		pair( '@' + width + ' قلم زمان مطالعه ویژه', ref.styles.featureSmall.fontSize, wpView.styles.featureSmall.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی کارت‌های کوچک', ref.styles.sideGap.gap, wpView.styles.sideGap.gap, 0 );
		pair( '@' + width + ' قلم تیتر کارت کوچک', ref.styles.sideH3.fontSize, wpView.styles.sideH3.fontSize, 0 );
		pair( '@' + width + ' قلم دسته‌ی کارت کوچک', ref.styles.sideSmall.fontSize, wpView.styles.sideSmall.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی شبکه‌ی مقاله‌ها', ref.styles.articlesGap.gap, wpView.styles.articlesGap.gap, 0 );
		pair( '@' + width + ' ستون‌های شبکه‌ی مقاله‌ها', ref.columns.articles, wpView.columns.articles, 0 );
		pair( '@' + width + ' نسبت تصویر مقاله', ref.styles.imageAspect.aspectRatio, wpView.styles.imageAspect.aspectRatio, 0 );
		pair( '@' + width + ' شعاع تصویر مقاله', ref.styles.imageRadius.borderTopLeftRadius, wpView.styles.imageRadius.borderTopLeftRadius, 0 );
		pair( '@' + width + ' قلم ریزسطر مقاله', ref.styles.infoSmall.fontSize, wpView.styles.infoSmall.fontSize, 0 );
		pair( '@' + width + ' قلم تیتر مقاله', ref.styles.infoH3.fontSize, wpView.styles.infoH3.fontSize, 0 );
		pair( '@' + width + ' فاصله‌ی تیتر مقاله', ref.styles.infoH3.marginTop, wpView.styles.infoH3.marginTop, 0 );
		pair( '@' + width + ' قلم خلاصه‌ی مقاله', ref.styles.infoP.fontSize, wpView.styles.infoP.fontSize, 0 );
		pair( '@' + width + ' قلم پیوند مقاله', ref.styles.infoSpan.fontSize, wpView.styles.infoSpan.fontSize, 0 );
		pair( '@' + width + ' قلم تیتر معرفی', ref.styles.introH1.fontSize, wpView.styles.introH1.fontSize, 0 );
		pair( '@' + width + ' قلم ریزسطر معرفی', ref.styles.introEyebrow.fontSize, wpView.styles.introEyebrow.fontSize, 0 );
		pair( '@' + width + ' قلم توضیح معرفی', ref.styles.introMuted.fontSize, wpView.styles.introMuted.fontSize, 0 );

		if ( ref.insets.feature && wpView.insets.feature ) {
			pair( '@' + width + ' فاصله‌ی پایین متن ویژه', ref.insets.feature.end, wpView.insets.feature.end, 0 );
			pair( '@' + width + ' فاصله‌ی کنار متن ویژه', ref.insets.feature.start, wpView.insets.feature.start, 0 );
		}

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( ! overflow, '@' + width + ' برگه سرریز افقی ندارد' );
	}

	/* ---------- ۳) تب‌های دسته ---------- */
	console.log( '\n== ۳) تب‌های دسته (پالایش برجا) ==' );

	await page.setViewportSize( { width: 1440, height: 1100 } );
	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 300 );
	wpView = await measure( page );

	check( wpView.has.tabs, 'تب‌های دسته در سرصفحه رندر شده‌اند' );
	check( wpView.counts.tabs >= 2, 'دست‌کم یک تب دسته + تب «همه» هست', String( wpView.counts.tabs ) );
	check(
		wpView.labels[ 0 ] && 'all' === wpView.labels[ 0 ].value && 'true' === wpView.labels[ 0 ].pressed,
		'تب نخست «همه داستانها» و فعال است',
		JSON.stringify( wpView.labels[ 0 ] || null )
	);

	const categoriesInCards = await page.$$eval( '.articles-grid .article-card', ( cards ) => [ ...new Set(
		cards.flatMap( ( c ) => ( c.getAttribute( 'data-category' ) || '' ).split( /\s+/ ).filter( Boolean ) )
	) ].sort() );

	check(
		categoriesInCards.length >= 1 && wpView.labels.length === categoriesInCards.length + 1,
		'تب‌ها یک‌به‌یک از دسته‌های واقعیِ کارت‌ها ساخته شده‌اند',
		'دسته‌ها=' + categoriesInCards.join( ',' ) + ' · تب‌ها=' + wpView.labels.length
	);
	check(
		categoriesInCards.every( ( slug ) => wpView.labels.some( ( l ) => l.value === slug ) ),
		'هر دسته‌ی کارت‌ها تب خودش را دارد',
		categoriesInCards.join( ',' )
	);

	const total      = await page.evaluate( () => document.querySelectorAll( '.articles-grid .article-card' ).length );
	const visibleNow = () => page.evaluate( () => document.querySelectorAll( '.articles-grid .article-card:not([hidden])' ).length );

	check( total === ( await visibleNow() ), 'در آغاز همه‌ی مقاله‌ها دیده می‌شوند', String( await visibleNow() ) );

	const firstCategory = categoriesInCards[ 0 ];
	const expected      = await page.evaluate( ( slug ) => document.querySelectorAll( '.articles-grid .article-card' ).length
		&& [ ...document.querySelectorAll( '.articles-grid .article-card' ) ]
			.filter( ( c ) => ( c.getAttribute( 'data-category' ) || '' ).split( /\s+/ ).includes( slug ) ).length, firstCategory );

	await page.click( `.manacore-magazine .section-tabs button[data-category="${ firstCategory }"]` );
	await page.waitForTimeout( 200 );

	check(
		expected === ( await visibleNow() ),
		'کلیک روی دسته‌ی «' + firstCategory + '» فقط کارت‌های همان دسته را نشان می‌دهد',
		'انتظار=' + expected + ' · دیده‌شده=' + ( await visibleNow() )
	);

	const pressed = await page.$eval( `.manacore-magazine .section-tabs button[data-category="${ firstCategory }"]`, ( el ) => el.getAttribute( 'aria-pressed' ) );
	check( 'true' === pressed, 'تب فعال `aria-pressed="true"` می‌گیرد', String( pressed ) );

	await page.click( '.manacore-magazine .section-tabs button[data-category="all"]' );
	await page.waitForTimeout( 200 );
	check( total === ( await visibleNow() ), 'بازگشت به «همه داستانها» همه‌ی کارت‌ها را برمی‌گرداند', String( await visibleNow() ) );

	/* ---------- ۴) برچسب دسته روی کارت ---------- */
	console.log( '\n== ۴) داده‌ی واقعی روی کارت‌ها ==' );

	const cardData = await page.$$eval( '.articles-grid .article-card', ( cards ) => cards.map( ( card ) => ( {
		badge: card.querySelector( '.article-image > span' ) ? card.querySelector( '.article-image > span' ).textContent.trim() : '',
		title: card.querySelector( 'h3' ) ? card.querySelector( 'h3' ).textContent.trim() : '',
		meta: card.querySelector( '.article-info > small' ) ? card.querySelector( '.article-info > small' ).textContent.trim() : '',
		href: card.getAttribute( 'href' ),
	} ) ) );

	check( cardData.length >= 3, 'کارت‌های مقاله از نوشته‌های واقعی ساخته شده‌اند', String( cardData.length ) );
	check( cardData.every( ( c ) => c.href && c.href.length > 0 ), 'هر کارت به نوشته‌ی واقعی پیوند دارد' );
	check( cardData.every( ( c ) => '' !== c.badge ), 'برچسب هر کارت نام دسته‌ی واقعی است', cardData.slice( 0, 2 ).map( ( c ) => c.badge ).join( ' · ' ) );
	check(
		cardData.every( ( c ) => /\d/.test( c.meta.replace( /[۰-۹]/g, '0' ) ) ),
		'ریزسطر هر کارت شمار دقیقه‌ی مطالعه را نشان می‌دهد',
		cardData.slice( 0, 2 ).map( ( c ) => c.meta ).join( ' · ' )
	);
	check(
		cardData.every( ( c ) => /[۰-۹]/.test( c.meta ) ),
		'زمان مطالعه با ارقام فارسی نوشته شده',
		cardData[ 0 ] ? cardData[ 0 ].meta : ''
	);

	/* ---------- ۵) بدون جاوااسکریپت ---------- */
	console.log( '\n== ۵) وضعیت سرور (بدون جاوااسکریپت) ==' );

	const noJs     = await browser.newContext( { javaScriptEnabled: false } );
	const noJsPage = await noJs.newPage();
	await noJsPage.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );

	const noJsState = await noJsPage.evaluate( () => ( {
		cards: document.querySelectorAll( '.articles-grid .article-card' ).length,
		hidden: document.querySelectorAll( '.articles-grid .article-card[hidden]' ).length,
		tabs: document.querySelectorAll( '.manacore-magazine .section-tabs button' ).length,
	} ) );

	check( noJsState.cards >= 3, 'بدون جاوااسکریپت همه‌ی مقاله‌ها رندر شده‌اند', String( noJsState.cards ) );
	check( 0 === noJsState.hidden, 'بدون جاوااسکریپت هیچ کارتی پنهان نیست', String( noJsState.hidden ) );
	check( noJsState.tabs >= 2, 'تب‌ها بدون جاوااسکریپت هم رندر می‌شوند', String( noJsState.tabs ) );

	await noJs.close();

	/* ---------- ۶) دسترس‌پذیری ---------- */
	console.log( '\n== ۶) دسترس‌پذیری (axe-core) ==' );

	if ( axePath ) {
		await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 200 );
		await page.addScriptTag( { path: axePath } );
		const violations = await page.evaluate( async () => {
			const run = await window.axe.run( document.body, {
				runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
			} );
			return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
		} );
		check( violations.length === 0, 'بدون تخلف axe روی /magazine/', violations.join( ' · ' ) );
	} else {
		check( false, 'axe-core در دسترس است (بدون آن، سنجش دسترس‌پذیری اجرا نمی‌شود)' );
	}

	/* ---------- ۷) خطای جاوااسکریپت ---------- */
	console.log( '\n== ۷) خطای جاوااسکریپت ==' );
	check( errors.length === 0, 'بدون خطای جاوااسکریپت روی برگه‌ی مجله', errors.slice( 0, 3 ).join( ' | ' ) );

	/* ---------- ۸) پاک‌سازی ---------- */
	console.log( '\n== ۸) پاک‌سازی داده‌ی آزمون ==' );

	let restoreOut = '';
	try {
		restoreOut = execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED, 'restore' ], { encoding: 'utf8' } ).trim();
	} catch ( error ) {
		restoreOut = String( error.message ).slice( 0, 200 );
	}

	check( /magazine restore ok/.test( restoreOut ), 'داده‌ی آزمون به حالت پیشین برگشت', restoreOut );

	await browser.close();

	console.log( '\n==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 2 );
} );

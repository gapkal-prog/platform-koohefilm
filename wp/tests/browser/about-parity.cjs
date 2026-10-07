/**
 * آزمون هم‌سانی برگه‌ی «درباره ما» با مرجع سینورا (`cinora/about.html`).
 *
 * چه می‌سنجد؟
 *   ۱) ساختار: همان کلاس‌های مرجع روی برگه‌ی محصول رندر شده‌اند
 *      (`.info-page`، `.breadcrumb`، `.info-intro`، `.pricing-crown`،
 *      `.eyebrow`، `.about-values` با سه `section`، `.about-story`، دکمه).
 *   ۲) هندسه و سبک: مقدار محاسبه‌شده‌ی مرورگر در ۱۴۴۰/۹۸۰/۷۶۸/۳۹۰ در برابر
 *      مرجع — شبکه‌ی سه‌ستونی و پله‌هایش، پدینگ/شعاع/پس‌زمینه‌ی کارت‌ها،
 *      فاصله‌ها، اندازه و خط‌ارتفاع سرتیتر و بندها، عرض و تراز روایت پایانی و
 *      فاصله‌ی دکمه.
 *   ۳) داده‌ی واقعی: متن کارت‌ها و روایت از **محتوای خودِ برگه** می‌آید
 *      (نه از قالب و نه از جاوااسکریپت) و ویرایش زنده‌ی برگه بی‌درنگ در
 *      سربرگ دیده می‌شود — یعنی نمی‌توان آن را در قالب سخت‌کد کرد.
 *   ۴) بدون جاوااسکریپت: هر سه کارت و روایت از سرور رندر می‌شوند.
 *   ۵) دسترس‌پذیری (axe) و صفر خطای جاوااسکریپت و صفر سرریز افقی.
 *   ۶) قالب ویرایشگر سایت: `page-about.html` در پوسته هست و ثبت می‌شود.
 *
 * پیش‌نیاز: `seed-about.php` (این آزمون خودش می‌کارد و در پایان برمی‌گرداند).
 *
 * اجرا: cd wp/tests/browser && node about-parity.cjs
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

const WP_ABOUT  = process.env.WP_ABOUT || 'http://localhost:8099/about/';
const REF_ABOUT = process.env.REF_ABOUT || 'http://localhost:8098/about.html';
const WP_ROOT   = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI    = process.env.WP_CLI || '/usr/local/bin/wp';
const QA_DIR    = path.join( __dirname, '..', 'qa-env' );
const THEME     = path.join( __dirname, '..', '..', 'themes', 'koohe-film' );

const WIDTHS = [ 1440, 980, 768, 390 ];

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
 * سنجش جفتی (مرجع در برابر محصول).
 *
 * @param {string} label برچسب.
 * @param {*}      a     مقدار مرجع.
 * @param {*}      b     مقدار محصول.
 * @param {number} tol   تلورانس عددی.
 */
function pair( label, a, b, tol ) {
	if ( null === a || null === b || 'undefined' === typeof a || 'undefined' === typeof b ) {
		check( false, label, 'مقدار سنجیده نشد: مرجع=' + a + ' · محصول=' + b );
		return;
	}

	const ok = 'string' === typeof a || 'string' === typeof b
		? String( a ).trim() === String( b ).trim()
		: Math.abs( a - b ) <= tol;

	check( ok, label, 'مرجع=' + a + ' · محصول=' + b );
}

/**
 * سنجش یک ویژگی CSS از دو سوییچ.
 *
 * @param {string} label برچسب.
 * @param {Object} a     سوییچ مرجع.
 * @param {Object} b     سوییچ محصول.
 * @param {string} prop  نام ویژگی.
 * @param {number} tol   تلورانس.
 */
function prop( label, a, b, prop, tol ) {
	pair( label, a && a[ prop ], b && b[ prop ], tol );
}

/**
 * اجرای wp-cli.
 *
 * @param {string[]} args آرگومان‌ها.
 * @return {string} خروجی.
 */
function wpCli( args ) {
	try {
		return execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT ].concat( args ), { encoding: 'utf8' } ).trim();
	} catch ( error ) {
		return String( error.message ).slice( 0, 200 );
	}
}

/**
 * اندازه‌گیری برگه.
 *
 * همان کلیدها روی مرجع و محصول استخراج می‌شوند تا سطرهای آزمون جفتی شوند.
 *
 * تفاوت‌های ساختاری آگاهانه که این آزمون کنار می‌گذارد:
 *   - در مرجع، دکمه‌ی پایانی فرزند مستقیم `.about-story` است؛ در محصول
 *     بلوک `manacore/cta-link` یک قاب `div.manacore-cta` دور آن می‌گذارد
 *     (قاب، سازه‌ی استاندارد بلوک است). پس «فاصله‌ی دکمه» در محصول از
 *     لبه‌ی همان قاب سنجیده می‌شود، نه از دکمه.
 *   - کارت‌های کارت‌ها در مرجع `.about-values>section` است و در محصول هم
 *     `core/group` با `tagName:"section"` همان ساختار را می‌سازد.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page ) {
	return page.evaluate( () => {
		const box = ( el ) => {
			if ( ! el ) {
				return null;
			}

			const r = el.getBoundingClientRect();

			return { w: Math.round( r.width ), h: Math.round( r.height ), x: Math.round( r.left ), y: Math.round( r.top ) };
		};

		const style = ( el, props ) => {
			if ( ! el ) {
				return null;
			}

			const s = getComputedStyle( el );
			const out = {};

			props.forEach( ( p ) => {
				out[ p ] = s[ p ];
			} );

			return out;
		};

		const mix = ( el, props ) => ( el ? Object.assign( box( el ), style( el, props ) ) : null );
		const root = document.querySelector( 'main.info-page' ) || document.querySelector( 'main' );
		const values = document.querySelector( '.about-values' );
		const cards = Array.from( document.querySelectorAll( '.about-values > section' ) );
		const card = cards[ 0 ] || null;
		const story = document.querySelector( '.about-story' );
		const storyButton = story ? story.querySelector( '.button' ) : null;
		const storyBtnBox = story ? story.querySelector( '.manacore-cta' ) || storyButton : null;

		/*
		 * فاصله‌های واقعی از هندسه خوانده می‌شوند، نه از `margin` محاسبه‌شده:
		 * در محصول ممکن است حاشیه روی قاب بلوک نشسته باشد و در مرجع روی خودِ
		 * عنصر — آنچه کاربر می‌بیند فاصله‌ی دیده‌شده است.
		 */
		const gapAfter = ( el, next ) => ( el && next ? Math.round( next.getBoundingClientRect().top - el.getBoundingClientRect().bottom ) : null );

		/*
		 * پهنای ستون‌ها: `gridTemplateColumns` محاسبه‌شده در مرورگر به px حل
		 * می‌شود؛ تعداد ستون‌های حل‌شده، معیار «سه‌ستونی / یک‌ستونی» است.
		 */
		const cols = values ? getComputedStyle( values ).gridTemplateColumns.split( ' ' ).filter( Boolean ) : [];

		return {
			url: location.pathname,
			has: {
				values: !! values,
				story: !! story,
				intro: !! document.querySelector( '.info-intro' ),
				crown: !! document.querySelector( '.pricing-crown' ),
				breadcrumb: !! document.querySelector( '.breadcrumb' ),
				button: !! storyButton,
			},
			main: mix( root, [ 'paddingTop', 'paddingBottom' ] ),
			h1: mix( document.querySelector( '.info-intro h1' ), [ 'fontSize', 'lineHeight', 'marginTop', 'marginBottom' ] ),
			ip: mix( document.querySelector( '.info-intro > p:not(.eyebrow)' ), [ 'fontSize', 'lineHeight', 'marginTop' ] ),
			eyebrow: mix( document.querySelector( '.info-intro > .eyebrow' ), [ 'fontSize' ] ),
			crown: mix( document.querySelector( '.pricing-crown' ), [ 'marginBottom', 'borderTopLeftRadius', 'transform' ] ),
			values: mix( values, [ 'gap', 'marginTop', 'display' ] ),
			cols: cols.length,
			cards: cards.length,
			card: mix( card, [ 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderTopLeftRadius', 'backgroundColor' ] ),
			cardH2: mix( card ? card.querySelector( 'h2' ) : null, [ 'fontSize', 'lineHeight', 'marginTop', 'marginBottom' ] ),
			cardP: mix( card ? card.querySelector( 'p' ) : null, [ 'fontSize', 'lineHeight', 'marginTop', 'color' ] ),
			introToValues: gapAfter( document.querySelector( '.info-intro' ), values ),
			card1ToCard2: gapAfter( cards[ 0 ], cards[ 1 ] ),
			valuesToStory: gapAfter( values, story ),
			story: mix( story, [ 'maxWidth', 'marginTop', 'textAlign' ] ),
			storyH2: mix( story ? story.querySelector( 'h2' ) : null, [ 'fontSize', 'lineHeight', 'marginTop' ] ),
			storyP: mix( story ? story.querySelector( 'p' ) : null, [ 'fontSize', 'lineHeight', 'marginTop' ] ),
			storyH2ToP: gapAfter( story ? story.querySelector( 'h2' ) : null, story ? story.querySelector( 'p' ) : null ),
			storyPToButton: gapAfter( story ? story.querySelector( 'p' ) : null, storyBtnBox ),
			button: mix( storyButton, [ 'fontSize', 'paddingTop', 'paddingRight', 'borderTopLeftRadius' ] ),
			buttonBox: box( storyBtnBox ),
			cardTexts: cards.map( ( el ) => ( el.querySelector( 'h2' ) || {} ).textContent ? el.querySelector( 'h2' ).textContent.trim() : '' ),
			overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		};
	} );
}

/**
 * بخش اصلی آزمون.
 */
( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	console.log( '\n== ۱) کاشت داده‌ی آزمون ==' );

	const seed = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-about.php' ) ] );

	check( /about seed ok/.test( seed ), 'برگه‌ی «درباره ما» کاشته شد', seed );

	/* ---------------------------------------------------------------------
	 * ۲) ساختار و کد وضعیت
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۲) ساختار برگه (مرجع ↔ محصول) ==' );

	const page = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
	const wpResponse = await page.goto( WP_ABOUT, { waitUntil: 'domcontentloaded' } );
	const wpStructure = await measure( page );
	const refPage = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
	const refResponse = await refPage.goto( REF_ABOUT, { waitUntil: 'domcontentloaded' } );
	await refPage.waitForTimeout( 300 );
	const refStructure = await measure( refPage );

	check( 200 === wpResponse.status(), 'برگه‌ی «درباره ما» با کد ۲۰۰ رندر می‌شود', String( wpResponse.status() ) );
	check( 200 === refResponse.status(), 'مرجع `about.html` هم ۲۰۰ می‌دهد', String( refResponse.status() ) );

	Object.keys( refStructure.has ).forEach( ( key ) => {
		check(
			refStructure.has[ key ] && wpStructure.has[ key ],
			'سازه‌ی `' + key + '` در هر دو هست',
			JSON.stringify( { ref: refStructure.has[ key ], wp: wpStructure.has[ key ] } )
		);
	} );

	pair( 'تعداد کارت‌های ارزش‌ها', refStructure.cards, wpStructure.cards, 0 );
	pair( 'متن کارت‌ها مو‌به‌مو یکی است', JSON.stringify( refStructure.cardTexts ), JSON.stringify( wpStructure.cardTexts ), 0 );

	/* ---------------------------------------------------------------------
	 * ۳) هندسه و سبک در چهار ویوپورت
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۳) هندسه و سبک در چهار ویوپورت ==' );

	for ( const width of WIDTHS ) {
		await page.setViewportSize( { width, height: 1000 } );
		await page.goto( WP_ABOUT, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 200 );
		const wp = await measure( page );

		await refPage.setViewportSize( { width, height: 1000 } );
		await refPage.goto( REF_ABOUT, { waitUntil: 'domcontentloaded' } );
		await refPage.waitForTimeout( 200 );
		const ref = await measure( refPage );

		prop( '@' + width + ' پدینگ بالای برگه', ref.main, wp.main, 'paddingTop', 0 );
		prop( '@' + width + ' پدینگ پایین برگه', ref.main, wp.main, 'paddingBottom', 0 );
		prop( '@' + width + ' تیتر سربرگ — اندازه', ref.h1, wp.h1, 'fontSize', 0 );
		prop( '@' + width + ' بند سربرگ — اندازه', ref.ip, wp.ip, 'fontSize', 0 );
		prop( '@' + width + ' جعبه‌ی سربرگ — فاصله‌ی پایین', ref.crown, wp.crown, 'marginBottom', 0 );

		/* شبکه: سه‌ستونی تا ۹۸۰ و یک‌ستونی زیر ۷۶۸ — عیناً مرجع. */
		pair( '@' + width + ' تعداد ستون‌های شبکه‌ی ارزش‌ها', ref.cols, wp.cols, 0 );
		prop( '@' + width + ' فاصله‌ی ستون‌ها', ref.values, wp.values, 'gap', 0 );
		prop( '@' + width + ' فاصله‌ی سربرگ تا شبکه', ref.values, wp.values, 'marginTop', 0 );
		prop( '@' + width + ' پدینگ بالای کارت', ref.card, wp.card, 'paddingTop', 0 );
		prop( '@' + width + ' پدینگ کنار کارت', ref.card, wp.card, 'paddingLeft', 0 );
		prop( '@' + width + ' شعاع کارت', ref.card, wp.card, 'borderTopLeftRadius', 0 );
		prop( '@' + width + ' ضخامت قاب کارت', ref.card, wp.card, 'borderTopWidth', 0 );
		prop( '@' + width + ' سرتیتر کارت — اندازه', ref.cardH2, wp.cardH2, 'fontSize', 0 );
		prop( '@' + width + ' سرتیتر کارت — خط‌ارتفاع', ref.cardH2, wp.cardH2, 'lineHeight', 0 );
		prop( '@' + width + ' سرتیتر کارت — فاصله‌ی بالا', ref.cardH2, wp.cardH2, 'marginTop', 0 );
		prop( '@' + width + ' بند کارت — اندازه', ref.cardP, wp.cardP, 'fontSize', 0 );
		prop( '@' + width + ' بند کارت — خط‌ارتفاع', ref.cardP, wp.cardP, 'lineHeight', 0 );
		prop( '@' + width + ' بند کارت — فاصله‌ی بالا', ref.cardP, wp.cardP, 'marginTop', 0 );

		pair( '@' + width + ' فاصله‌ی سربرگ تا شبکه (دیده‌شده)', ref.introToValues, wp.introToValues, 1 );
		pair( '@' + width + ' فاصله‌ی کارت‌ها (دیده‌شده)', ref.card1ToCard2, wp.card1ToCard2, 1 );
		pair( '@' + width + ' فاصله‌ی شبکه تا روایت (دیده‌شده)', ref.valuesToStory, wp.valuesToStory, 1 );

		prop( '@' + width + ' عرض روایت', ref.story, wp.story, 'maxWidth', 0 );
		prop( '@' + width + ' تراز روایت', ref.story, wp.story, 'textAlign', 0 );
		prop( '@' + width + ' تیتر روایت — اندازه', ref.storyH2, wp.storyH2, 'fontSize', 0 );
		prop( '@' + width + ' تیتر روایت — خط‌ارتفاع', ref.storyH2, wp.storyH2, 'lineHeight', 0 );
		prop( '@' + width + ' بند روایت — اندازه', ref.storyP, wp.storyP, 'fontSize', 0 );
		prop( '@' + width + ' بند روایت — خط‌ارتفاع', ref.storyP, wp.storyP, 'lineHeight', 0 );
		prop( '@' + width + ' بند روایت — فاصله‌ی بالا', ref.storyP, wp.storyP, 'marginTop', 0 );

		pair( '@' + width + ' فاصله‌ی تیتر تا بند روایت (دیده‌شده)', ref.storyH2ToP, wp.storyH2ToP, 1 );
		pair( '@' + width + ' فاصله‌ی بند تا دکمه (دیده‌شده)', ref.storyPToButton, wp.storyPToButton, 1 );
		prop( '@' + width + ' اندازه‌ی متن دکمه', ref.button, wp.button, 'fontSize', 0 );
		prop( '@' + width + ' پدینگ بالای دکمه', ref.button, wp.button, 'paddingTop', 0 );
		prop( '@' + width + ' شعاع دکمه', ref.button, wp.button, 'borderTopLeftRadius', 0 );

		check( wp.overflow <= 1, '@' + width + ' بدون سرریز افقی', String( wp.overflow ) );
	}

	/* ---------------------------------------------------------------------
	 * ۴) داده‌ی واقعی: ویرایش برگه بی‌درنگ دیده می‌شود
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۴) داده‌ی واقعی از پایگاه‌داده ==' );

	const wpId = wpCli( [ 'post', 'list', '--post_type=page', '--name=about', '--field=ID' ] ).split( '\n' ).pop().trim();
	const originalContent = wpCli( [ 'post', 'get', wpId, '--field=post_content' ] );
	const marker = 'سنجش داده‌ی زنده‌ی صفحه‌ی درباره';

	wpCli( [ 'post', 'update', wpId, '--post_content=' + originalContent.replace( 'کشف، نه فقط جستجو', marker ) ] );

	await page.setViewportSize( { width: 1440, height: 1000 } );
	await page.goto( WP_ABOUT, { waitUntil: 'domcontentloaded' } );
	const liveCard = await page.evaluate( () => {
		const h2 = document.querySelector( '.about-values > section > h2' );

		return h2 ? h2.textContent.trim() : null;
	} );

	check( marker === liveCard, 'عنوان کارت اول از محتوای برگه می‌آید (ویرایش زنده دیده می‌شود)', String( liveCard ) );

	wpCli( [ 'post', 'update', wpId, '--post_content=' + originalContent ] );

	await page.goto( WP_ABOUT, { waitUntil: 'domcontentloaded' } );
	const restoredCard = await page.evaluate( () => {
		const h2 = document.querySelector( '.about-values > section > h2' );

		return h2 ? h2.textContent.trim() : null;
	} );

	check( 'کشف، نه فقط جستجو' === restoredCard, 'پس از برگشت، متن اصلی برمی‌گردد', String( restoredCard ) );

	/* ---------------------------------------------------------------------
	 * ۵) بدون جاوااسکریپت
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۵) بدون جاوااسکریپت ==' );

	const noJs = await browser.newContext( { javaScriptEnabled: false, viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
	const noJsPage = await noJs.newPage();

	await noJsPage.goto( WP_ABOUT, { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 200 );

	const noJsState = await noJsPage.evaluate( () => ( {
		cards: document.querySelectorAll( '.about-values > section' ).length,
		story: document.querySelectorAll( '.about-story' ).length,
		button: document.querySelectorAll( '.about-story .button' ).length,
	} ) );

	check( 3 === noJsState.cards, 'سه کارت بدون جاوااسکریپت هم رندر می‌شوند', String( noJsState.cards ) );
	check( 1 === noJsState.story && 1 === noJsState.button, 'روایت و دکمه‌اش از سرور می‌آیند', JSON.stringify( noJsState ) );
	await noJs.close();

	/* ---------------------------------------------------------------------
	 * ۶) دسترس‌پذیری، کنسول و قالب ویرایشگر
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۶) دسترس‌پذیری، کنسول و قالب ==' );

	const errors = [];
	const axePage = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );

	axePage.on( 'pageerror', ( error ) => errors.push( String( error.message ).slice( 0, 120 ) ) );
	axePage.on( 'console', ( message ) => {
		if ( 'error' === message.type() && ! /404/.test( message.text() ) ) {
			errors.push( message.text().slice( 0, 120 ) );
		}
	} );

	await axePage.goto( WP_ABOUT, { waitUntil: 'load' } );
	await axePage.waitForTimeout( 400 );

	if ( axePath ) {
		await axePage.addScriptTag( { path: axePath } );
		const violations = await axePage.evaluate( async () => {
			const results = await window.axe.run( document, { resultTypes: [ 'violations' ] } );

			return results.violations.map( ( v ) => v.id + ':' + v.nodes.length );
		} );

		check( 0 === violations.length, 'axe بدون تخلف روی برگه‌ی «درباره ما»', violations.join( ' | ' ) );
	} else {
		console.log( '  ! axe-core نصب نیست؛ بخش دسترس‌پذیری اجرا نشد.' );
	}

	check( 0 === errors.length, 'صفر خطای جاوااسکریپت/کنسول', errors.join( ' | ' ) );

	const templateFile = path.join( THEME, 'templates', 'page-about.html' );
	const templateOk = require( 'fs' ).existsSync( templateFile );
	/*
	 * فهرست قالب‌های ویرایشگر سایت: `get_block_templates()` هسته‌ی وردپرس
	 * (نه متد پوسته — فراخوانی `WP_Theme::get_block_templates()` در نسخه‌ی
	 * این نصب وجود ندارد و آزمون را با خطای مرگبار می‌شکست).
	 */
	const templateList = wpCli( [ 'eval', '$t = get_block_templates( array(), "wp_template" ); echo implode(",", wp_list_pluck( $t, "slug" ) );' ] );

	check( templateOk, 'قالب `page-about.html` در پوشه‌ی templates پوسته هست', templateFile );
	check( /page-about/.test( templateList ), 'قالب برگه‌ی «درباره ما» در فهرست قالب‌های ویرایشگر سایت هست', templateList.slice( 0, 200 ) );

	await browser.close();

	/* ---------------------------------------------------------------------
	 * ۷) بازگرداندن داده
	 * ------------------------------------------------------------------ */
	console.log( '\n== ۷) بازگرداندن داده ==' );

	const restore = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-about.php' ), 'restore' ] );

	check( /about restore ok/.test( restore ), 'داده‌ی برگه به حالت پیشین برگشت', restore );

	console.log( '\n' + '='.repeat( 58 ) );
	console.log( pass + ' موفق / ' + fail + ' ناموفق' );
	console.log( '='.repeat( 58 ) );

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( 'اجرای آزمون شکست خورد:', error );
	process.exit( 2 );
} );

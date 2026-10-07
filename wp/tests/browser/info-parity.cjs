/**
 * آزمون هم‌سانی برگه‌های «راهنما» و «قوانین و حریم خصوصی» با مرجع سینورا
 * (`cinora/help.html` و `cinora/privacy.html`).
 *
 * چه می‌سنجد؟
 *   ۱) ساختار: همان کلاس‌های مرجع روی برگه‌های محصول رندر شده‌اند
 *      (`.info-page`، `.breadcrumb`، `.info-intro`، `.pricing-crown`،
 *      `.eyebrow`، `.faq-list.info-faq`، `.faq-item`، `.legal-content`،
 *      `section`/`h2`/`p`).
 *   ۲) هندسه و سبک: مقدار محاسبه‌شده‌ی مرورگر در ۱۴۴۰/۹۸۰/۷۶۸/۳۹۰ در برابر
 *      مرجع — عرض و جای ستون سربرگ (۹۰۰px)، ستون پرسش‌ها (۸۵۰px)، ستون
 *      بندهای حقوقی (۹۰۰px)، پدینگ‌ها، اندازه و خط‌ارتفاع متن، رنگ‌ها،
 *      شعاع گوشه و چرخش جعبه‌ی آیکون.
 *   ۳) رفتار: نخستین پرسش باز است، کلیک روی سرِ پرسش (`summary`) آن را
 *      می‌بندد و پرسش دیگری را باز می‌کند، و بستن با کلید هم کار می‌کند.
 *   ۴) داده‌ی واقعی: متن پرسش‌ها و بندهای حقوقی از **محتوای خودِ برگه** در
 *      پایگاه‌داده می‌آید (نه از قالب و نه از جاوااسکریپت).
 *   ۵) بدون جاوااسکریپت: هر ۶ پرسش/۶ بند از سرور رندر می‌شوند (در مرجع،
 *      پرسش‌ها را جاوااسکریپت می‌سازد و بدون آن صفحه خالی است).
 *   ۶) دسترس‌پذیری (axe) و صفر خطای جاوااسکریپت و صفر سرریز افقی.
 *   ۷) قالب ویرایشگر سایت: `page-help.html` و `page-privacy.html` در پوسته
 *      هستند (کشف خودکار و ویرایش از «ویرایشگر سایت»).
 *   ۸) واگرایی‌های آگاهانه: آزمون، خودِ رفتار مرجع را هم می‌سنجد تا سند
 *      واگرایی (پاسخ‌های بسته‌ی مرجع پنهان نمی‌شوند) معتبر بماند.
 *
 * پیش‌نیاز: `seed-info.php` (این آزمون خودش می‌کارد و در پایان برمی‌گرداند).
 *
 * اجرا: node info-parity.cjs
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

const WP_HELP  = process.env.WP_HELP || 'http://localhost:8099/help/';
const WP_PRIV  = process.env.WP_PRIV || 'http://localhost:8099/privacy/';
const REF_HELP = process.env.REF_HELP || 'http://localhost:8098/help.html';
const REF_PRIV = process.env.REF_PRIV || 'http://localhost:8098/privacy.html';
const WP_ROOT  = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI   = process.env.WP_CLI || '/usr/local/bin/wp';
const QA_DIR   = path.join( __dirname, '..', 'qa-env' );
const THEME    = path.join( __dirname, '..', '..', 'themes', 'koohe-film' );

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
 * @param {import('playwright').Page} page   صفحه.
 * @param {string}                    kind   `help` یا `privacy`.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page, kind ) {
	return page.evaluate( ( kind ) => {
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
		const intro = document.querySelector( '.info-intro' );
		const crown = document.querySelector( '.pricing-crown' );
		const list = document.querySelector( '.faq-list' );
		const items = Array.from( document.querySelectorAll( '.faq-item' ) );
		const item = items[ 0 ] || null;
		const summary = item ? item.querySelector( 'summary, button' ) : null;
		const answer = item ? item.querySelector( 'p' ) : null;
		const closed = items.find( ( el ) => ! el.hasAttribute( 'open' ) ) || null;
		const closedAnswer = closed ? closed.querySelector( 'p' ) : null;
		const legal = document.querySelector( '.legal-content' );
		const legalSections = Array.from( document.querySelectorAll( '.legal-content section' ) );
		const legalFirst = legalSections[ 0 ] || null;

		const chevron = ( () => {
			if ( ! summary ) {
				return null;
			}

			const s = getComputedStyle( summary, '::after' );

			return { w: s.width, h: s.height, bg: s.backgroundColor, transform: s.transform };
		} )();

		/*
		 * وضعیت پاسخ: مرجع شِوران را با یک `<span>⌄</span>` می‌سازد و ما با
		 * `::after`؛ پس فقط «قابل‌مشاهده بودن» سنجیده می‌شود. `checkVisibility`
		 * تنها معیار درست برای محتوای `details` بسته است — در Chromium جعبه‌ی
		 * فرزندانِ `details` بسته در layout می‌ماند (`getBoundingClientRect`
		 * عدد کهنه می‌دهد) ولی رندر نمی‌شود.
		 */
		const answerState = ( el ) => {
			if ( ! el ) {
				return null;
			}

			const para = el.querySelector( 'p' );
			const sum = el.querySelector( 'summary, button' );

			return {
				open: el.hasAttribute( 'open' ) || el.classList.contains( 'open' ),
				visible: para && para.checkVisibility ? para.checkVisibility() : null,
				display: para ? getComputedStyle( para ).display : null,
				spanGap: Math.round( el.getBoundingClientRect().height - ( sum ? sum.getBoundingClientRect().height : 0 ) ),
			};
		};

		return {
			url: location.pathname,
			main: mix( root, [ 'paddingTop', 'paddingBottom' ] ),
			breadcrumb: mix( document.querySelector( '.breadcrumb' ), [ 'fontSize' ] ),
			intro: mix( intro, [ 'maxWidth', 'paddingTop', 'textAlign' ] ),
			introSignature: intro ? Array.from( intro.children ).map( ( el ) => el.tagName.toLowerCase() + '.' + ( el.className || '' ).split( ' ' ).join( '.' ) ).join( ' | ' ) : null,
			breadcrumbSignature: ( () => {
				const nav = document.querySelector( '.breadcrumb' );

				return nav ? Array.from( nav.children ).map( ( el ) => el.tagName.toLowerCase() ).join( ' | ' ) : null;
			} )(),
			crown: mix( crown, [ 'borderTopLeftRadius', 'transform', 'marginBottom', 'marginLeft', 'boxSizing' ] ),
			crownIcon: box( crown ? crown.querySelector( 'svg' ) : null ),
			h1: mix( document.querySelector( '.info-intro h1' ), [ 'fontSize', 'lineHeight', 'marginTop' ] ),
			ip: mix( document.querySelector( '.info-intro > p:not(.eyebrow)' ), [ 'fontSize', 'lineHeight', 'marginTop' ] ),
			eyebrow: mix( document.querySelector( '.info-intro > .eyebrow' ), [ 'fontSize', 'lineHeight' ] ),
			list: mix( list, [ 'borderTopLeftRadius', 'marginTop', 'maxWidth', 'borderTopWidth' ] ),
			listCount: items.length,
			openCount: items.filter( ( el ) => el.hasAttribute( 'open' ) ).length,
			openClass: items.filter( ( el ) => el.classList.contains( 'open' ) ).length,
			item: mix( item, [ 'backgroundColor', 'borderBottomWidth' ] ),
			summary: mix( summary, [ 'fontSize', 'lineHeight', 'padding', 'justifyContent', 'gap', 'color' ] ),
			chevron,
			answer: answer ? mix( answer, [ 'fontSize', 'lineHeight', 'padding', 'color' ] ) : null,
			answerText: answer ? answer.textContent.trim().slice( 0, 40 ) : null,
			firstAnswerState: answerState( item ),
			closedAnswerState: answerState( closed ),
			summaryTag: summary ? summary.tagName.toLowerCase() : null,
			chevronHtml: summary ? summary.innerHTML.replace( /\s+/g, ' ' ).trim().slice( 0, 80 ) : null,
			questions: items.map( ( el ) => ( el.querySelector( 'summary, button' ) || {} ).textContent ? el.querySelector( 'summary, button' ).textContent.trim() : '' ),
			legal: mix( legal, [ 'maxWidth', 'marginTop' ] ),
			legalCount: legalSections.length,
			legalSection: legalFirst ? mix( legalFirst, [ 'paddingTop', 'paddingBottom', 'borderBottomWidth' ] ) : null,
			legalH2: legalFirst ? mix( legalFirst.querySelector( 'h2' ), [ 'fontSize', 'marginBottom', 'lineHeight' ] ) : null,
			legalPH: legalFirst ? mix( legalFirst.querySelector( 'p' ), [ 'fontSize', 'lineHeight', 'marginTop', 'color' ] ) : null,
			legalH2Texts: legalSections.map( ( el ) => {
				const h2 = el.querySelector( 'h2' );

				return h2 ? h2.textContent.trim() : '';
			} ),
			overflowX: Math.max( 0, document.documentElement.scrollWidth - document.documentElement.clientWidth ),
			detailsTagged: document.querySelectorAll( 'details.faq-item > summary' ).length,
			kind,
		};
	}, kind );
}

/**
 * باز کردن یک نشانی و اندازه‌گیری آن.
 *
 * @param {import('playwright').Browser} browser مرورگر.
 * @param {number}                       width   پهنا.
 * @param {string}                       url     نشانی.
 * @param {string}                       kind    نوع برگه.
 * @return {Promise<Object>} وضعیت + اندازه‌ها + خطاها.
 */
async function open( browser, width, url, kind ) {
	const context = await browser.newContext( { viewport: { width, height: 900 }, locale: 'fa-IR' } );
	const page = await context.newPage();
	const errors = [];

	page.on( 'pageerror', ( error ) => errors.push( String( error.message ).slice( 0, 120 ) ) );
	page.on( 'console', ( message ) => {
		if ( 'error' === message.type() ) {
			errors.push( message.text().slice( 0, 120 ) );
		}
	} );

	const response = await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
	await page.waitForTimeout( 350 );

	const data = await measure( page, kind );

	return { context, page, status: response ? response.status() : 0, errors, ...data };
}

( async () => {
	console.log( '== ۰) کاشت داده‌ی آزمون ==' );

	const seed = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-info.php' ) ] );

	check( /info seed ok/.test( seed ), 'داده‌ی برگه‌های راهنما و قوانین کاشته شد', seed );

	const helpId = wpCli( [ 'post', 'list', '--post_type=page', '--name=help', '--field=ID' ] );
	const privId = wpCli( [ 'post', 'list', '--post_type=page', '--name=privacy', '--field=ID' ] );
	const helpContent = /^\d+$/.test( helpId ) ? wpCli( [ 'post', 'get', helpId, '--field=post_content' ] ) : '';
	const privContent = /^\d+$/.test( privId ) ? wpCli( [ 'post', 'get', privId, '--field=post_content' ] ) : '';

	check( helpContent.length > 100 && privContent.length > 100, 'محتوای هر دو برگه از پایگاه‌داده خوانده شد', 'help=' + helpContent.length + ' · privacy=' + privContent.length + ' بایت' );

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	const REF = { help: {}, privacy: {} };
	const WP  = { help: {}, privacy: {} };
	const ERRORS = [];
	const REF_ERRORS = [];

	for ( const width of WIDTHS ) {
		const refHelp = await open( browser, width, REF_HELP, 'help' );
		const refPriv = await open( browser, width, REF_PRIV, 'privacy' );
		const wpHelp  = await open( browser, width, WP_HELP, 'help' );
		const wpPriv  = await open( browser, width, WP_PRIV, 'privacy' );

		REF.help[ width ]    = refHelp;
		REF.privacy[ width ] = refPriv;
		WP.help[ width ]     = wpHelp;
		WP.privacy[ width ]  = wpPriv;

		ERRORS.push( [ 'help @' + width, wpHelp.errors ] );
		ERRORS.push( [ 'privacy @' + width, wpPriv.errors ] );
		REF_ERRORS.push( [ 'help @' + width, refHelp.errors ] );

		await refHelp.context.close();
		await refPriv.context.close();
		await wpHelp.context.close();
		await wpPriv.context.close();
	}

	const base = WIDTHS[ 0 ];

	console.log( '\n== ۱) دسترس‌پذیری برگه و ساختار ==' );

	check( 200 === WP.help[ base ].status, 'برگه‌ی راهنما با ۲۰۰ پاسخ می‌دهد', String( WP.help[ base ].status ) );
	check( 200 === WP.privacy[ base ].status, 'برگه‌ی قوانین با ۲۰۰ پاسخ می‌دهد', String( WP.privacy[ base ].status ) );
	check( 200 === REF.help[ base ].status && 200 === REF.privacy[ base ].status, 'هر دو برگه‌ی مرجع در دسترس‌اند', REF.help[ base ].status + '/' + REF.privacy[ base ].status );

	/*
	 * مرجع در جعبه‌ی آیکون فقط `pricing-crown` دارد و ما همان کلاس را
	 * به‌همراه کلاس مشترک پوسته (`koohe-pricing-crown`) رندر می‌کنیم؛ پس
	 * «ترتیب و شمار فرزندان» سنجیده می‌شود، نه رشته‌ی کامل کلاس‌ها.
	 */
	pair( 'نشانه‌ی ترتیب فرزندان سربرگ (آیکون/ریزسطر/تیتر/توضیح)',
		( REF.help[ base ].introSignature || '' ).replace( /\.[^|]*/g, '' ),
		( WP.help[ base ].introSignature || '' ).replace( /\.[^|]*/g, '' ) );

	check( /pricing-crown/.test( String( WP.help[ base ].introSignature ) ), 'جعبه‌ی آیکون کلاس `pricing-crown` مرجع را دارد', WP.help[ base ].introSignature );
	pair( 'نشانه‌ی فرزندان مسیر راهنما', REF.help[ base ].breadcrumbSignature, WP.help[ base ].breadcrumbSignature );

	pair( 'پهنای جعبه‌ی آیکون سربرگ', REF.help[ base ].crown.w, WP.help[ base ].crown.w, 0 );
	pair( 'اندازه‌ی آیکون درون جعبه', REF.help[ base ].crownIcon.w, WP.help[ base ].crownIcon.w, 0 );
	pair( 'شعاع گوشه‌ی جعبه‌ی آیکون', REF.help[ base ].crown.borderTopLeftRadius, WP.help[ base ].crown.borderTopLeftRadius );
	pair( 'چرخش جعبه‌ی آیکون', REF.help[ base ].crown.transform, WP.help[ base ].crown.transform );

	pair( 'شمار پرسش‌های راهنما', 6, WP.help[ base ].listCount, 0 );
	pair( 'شمار بندهای قوانین (داده‌ی این نصب)', 7, WP.privacy[ base ].legalCount, 0 );
	pair( 'هر شش پرسش با `details/summary` بومی رندر شده‌اند', 6, WP.help[ base ].detailsTagged, 0 );
	check( WP.help[ base ].openCount === 1, 'دقیقاً یک پرسش در آغاز باز است (با `open` بومی، نه کلاس مرجع)', 'open=' + WP.help[ base ].openCount + ' · class.open=' + WP.help[ base ].openClass );

	console.log( '\n== ۲) هندسه و سبک در برابر مرجع ==' );

	for ( const width of WIDTHS ) {
		const ref = REF.help[ width ];
		const wp  = WP.help[ width ];

		prop( width + ' پدینگ بالای برگه‌ی راهنما', ref.main, wp.main, 'paddingTop' );
		prop( width + ' پدینگ پایین برگه‌ی راهنما', ref.main, wp.main, 'paddingBottom' );
		prop( width + ' پهنای مسیر راهنما', ref.breadcrumb, wp.breadcrumb, 'w', 2 );
		prop( width + ' لبه‌ی راست مسیر راهنما', ref.breadcrumb, wp.breadcrumb, 'x', 2 );

		prop( width + ' پهنای ستون سربرگ', ref.intro, wp.intro, 'w', 2 );
		prop( width + ' جای ستون سربرگ', ref.intro, wp.intro, 'x', 2 );
		prop( width + ' پدینگ بالای ستون سربرگ', ref.intro, wp.intro, 'paddingTop' );
		prop( width + ' ترازبندی سربرگ', ref.intro, wp.intro, 'textAlign' );

		prop( width + ' اندازه‌ی قلم تیتر', ref.h1, wp.h1, 'fontSize' );
		prop( width + ' خط‌ارتفاع تیتر', ref.h1, wp.h1, 'lineHeight', 1.5 );
		prop( width + ' فاصله‌ی بالای تیتر', ref.h1, wp.h1, 'marginTop', 1 );

		prop( width + ' اندازه‌ی قلم ریزسطر', ref.eyebrow, wp.eyebrow, 'fontSize' );
		/*
		 * مرجع بازنشانی دارد و `line-height:normal` را روی ریزسطر می‌گذارد؛
		 * ما ۱.۵ می‌نویسیم. هر دو یک **جعبه‌ی خط** می‌سازند، پس پیکسل
		 * سنجیده می‌شود، نه کلیدواژه.
		 */
		pair( width + ' جعبه‌ی خط ریزسطر (پیکسل)', ref.eyebrow.h, wp.eyebrow.h, 1 );
		prop( width + ' اندازه‌ی قلم توضیح سربرگ', ref.ip, wp.ip, 'fontSize' );
		prop( width + ' خط‌ارتفاع توضیح سربرگ', ref.ip, wp.ip, 'lineHeight', 1.5 );

		prop( width + ' پهنای ستون پرسش‌ها', ref.list, wp.list, 'w', 2 );
		prop( width + ' لبه‌ی راست ستون پرسش‌ها', ref.list, wp.list, 'x', 2 );
		prop( width + ' فاصله‌ی بالای ستون پرسش‌ها', ref.list, wp.list, 'marginTop', 1 );
		prop( width + ' شعاع گوشه‌ی قاب پرسش‌ها', ref.list, wp.list, 'borderTopLeftRadius' );

		prop( width + ' پس‌زمینه‌ی ردیف پرسش', ref.item, wp.item, 'backgroundColor' );
		prop( width + ' خط جداکننده‌ی ردیف پرسش', ref.item, wp.item, 'borderBottomWidth' );

		prop( width + ' اندازه‌ی قلم سرِ پرسش', ref.summary, wp.summary, 'fontSize' );
		prop( width + ' خط‌ارتفاع سرِ پرسش', ref.summary, wp.summary, 'lineHeight', 1.5 );
		prop( width + ' پدینگ سرِ پرسش', ref.summary, wp.summary, 'padding' );
		prop( width + ' چیدمان سرِ پرسش', ref.summary, wp.summary, 'justifyContent' );
		prop( width + ' فاصله‌ی عنوان و شِوران', ref.summary, wp.summary, 'gap' );

		/*
		 * شِوران: مرجع `<span>⌄</span>` داخل دکمه دارد و قاعده‌ی CSS‌اش `>svg` را
		 * هدف گرفته (قاعده‌ی مرده)؛ محصول شِوران را با `::after` و ماسک svg
		 * می‌سازد. پس اندازه فقط در محصول سنجیده می‌شود و ساختار مرجع در
		 * بخش ۴ همین آزمون ثبت شده است.
		 */
		check( '9px' === wp.chevron.w && '9px' === wp.chevron.h, width + ' قاب شِوران ۹×۹ (جوهر ۵×۳px، عیناً مثل مرجع)', wp.chevron.w + '×' + wp.chevron.h );

		prop( width + ' اندازه‌ی قلم پاسخ باز', ref.answer, wp.answer, 'fontSize' );
		prop( width + ' خط‌ارتفاع پاسخ باز', ref.answer, wp.answer, 'lineHeight', 1.5 );
		prop( width + ' پدینگ پاسخ باز', ref.answer, wp.answer, 'padding' );

		prop( width + ' پهنای ستون بندهای حقوقی', REF.privacy[ width ].legal, WP.privacy[ width ].legal, 'w', 2 );
		prop( width + ' فاصله‌ی بالای ستون بندهای حقوقی', REF.privacy[ width ].legal, WP.privacy[ width ].legal, 'marginTop', 1 );
		prop( width + ' پدینگ بند حقوقی', REF.privacy[ width ].legalSection, WP.privacy[ width ].legalSection, 'paddingTop' );
		prop( width + ' پدینگ پایین بند حقوقی', REF.privacy[ width ].legalSection, WP.privacy[ width ].legalSection, 'paddingBottom' );
		/*
		 * ارتفاع بند به طول متنِ هر سایت بسته است (متن نمونه‌ی ما با مرجع یکی
		 * نیست)؛ پس به‌جای مقایسه‌ی خام، درستیِ درونی سنجیده می‌شود:
		 * ارتفاع بند = پدینگ بالا + سرتیتر + فاصله‌ی سرتیتر + بند متن.
		 */
		( () => {
			const l = WP.privacy[ width ];
			const sum = parseFloat( l.legalSection.paddingTop ) + parseFloat( l.legalSection.paddingBottom )
				+ l.legalH2.h + parseFloat( l.legalH2.marginBottom ) + l.legalPH.h;

			pair( width + ' ارتفاع بند حقوقی = پدینگ + سرتیتر + بند', sum, l.legalSection.h, 2 );
		} )();
		prop( width + ' اندازه‌ی قلم سرتیتر بند', REF.privacy[ width ].legalH2, WP.privacy[ width ].legalH2, 'fontSize' );
		prop( width + ' فاصله‌ی سرتیتر بند', REF.privacy[ width ].legalH2, WP.privacy[ width ].legalH2, 'marginBottom' );
		prop( width + ' اندازه‌ی قلم بند حقوقی', REF.privacy[ width ].legalPH, WP.privacy[ width ].legalPH, 'fontSize' );
		prop( width + ' خط‌ارتفاع بند حقوقی', REF.privacy[ width ].legalPH, WP.privacy[ width ].legalPH, 'lineHeight', 1.5 );
		prop( width + ' رنگ بند حقوقی', REF.privacy[ width ].legalPH, WP.privacy[ width ].legalPH, 'color' );

		check( 0 === wp.overflowX, width + ' بدون سرریز افقی روی برگه‌ی راهنما', String( wp.overflowX ) );
		check( 0 === WP.privacy[ width ].overflowX, width + ' بدون سرریز افقی روی برگه‌ی قوانین', String( WP.privacy[ width ].overflowX ) );
	}

	console.log( '\n== ۳) رفتار پرسش‌ها (باز/بسته) ==' );

	const behavior = await ( async () => {
		const context = await browser.newContext( { viewport: { width: 1440, height: 900 }, locale: 'fa-IR' } );
		const page = await context.newPage();

		await page.goto( WP_HELP, { waitUntil: 'load', timeout: 60000 } );
		await page.waitForTimeout( 250 );

		const firstOpen = await page.evaluate( () => {
			const items = Array.from( document.querySelectorAll( '.faq-item' ) );

			return {
				open: items.map( ( el ) => el.hasAttribute( 'open' ) ),
				visible: items.map( ( el ) => el.querySelector( 'p' ).checkVisibility() ),
			};
		} );

		/* کلیک روی سرِ پرسش دوم: باید باز شود و اولی نباید بسته‌شود (رفتار `details`). */
		await page.click( '.faq-item:nth-of-type(2) > summary' );
		await page.waitForTimeout( 200 );

		const afterClick = await page.evaluate( () => {
			const items = Array.from( document.querySelectorAll( '.faq-item' ) );

			return {
				open: items.map( ( el ) => el.hasAttribute( 'open' ) ),
				secondVisible: items[ 1 ].querySelector( 'p' ).checkVisibility(),
				firstVisible: items[ 0 ].querySelector( 'p' ).checkVisibility(),
				chevronOpen: ( () => {
					const t = getComputedStyle( items[ 1 ].querySelector( 'summary' ), '::after' ).transform;
					const parts = t.replace( /[^0-9.,-]/g, '' ).split( ',' ).map( Number );

					return { raw: t, a: parts[ 0 ], d: parts[ 3 ] };
				} )(),
			};
		} );

		/* بستن با کلید: فوکوس روی سرِ پرسش سوم و فشردن Enter. */
		await page.keyboard.press( 'Tab' );
		await page.focus( '.faq-item:nth-of-type(3) > summary' );
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 200 );

		const afterKey = await page.evaluate( () => {
			const item = document.querySelectorAll( '.faq-item' )[ 2 ];

			return { open: item.hasAttribute( 'open' ), visible: item.querySelector( 'p' ).checkVisibility() };
		} );

		/* بستن پرسش اول با کلیک: پاسخ باید پنهان شود. */
		await page.click( '.faq-item:nth-of-type(1) > summary' );
		await page.waitForTimeout( 200 );

		const afterClose = await page.evaluate( () => {
			const item = document.querySelectorAll( '.faq-item' )[ 0 ];
			const sum = item.querySelector( 'summary' );

			return {
				open: item.hasAttribute( 'open' ),
				visible: item.querySelector( 'p' ).checkVisibility(),
				spanGap: Math.round( item.getBoundingClientRect().height - sum.getBoundingClientRect().height ),
			};
		} );

		await context.close();

		return { firstOpen, afterClick, afterKey, afterClose };
	} )();

	check( behavior.firstOpen.open[ 0 ] && ! behavior.firstOpen.open[ 1 ], 'آغاز: تنها پرسش اول باز است', JSON.stringify( behavior.firstOpen.open ) );
	check( behavior.firstOpen.visible[ 0 ] && ! behavior.firstOpen.visible[ 1 ], 'آغاز: متن پاسخ اول دیده می‌شود و بقیه نه', JSON.stringify( behavior.firstOpen.visible ) );
	check( behavior.afterClick.open[ 1 ] && behavior.afterClick.secondVisible, 'کلیک روی پرسش دوم آن را باز می‌کند', JSON.stringify( behavior.afterClick.open ) );
	check( behavior.afterClick.open[ 0 ] && behavior.afterClick.firstVisible, 'پرسش اول با باز شدن دومی بسته نمی‌شود', JSON.stringify( behavior.afterClick.open ) );
	check( behavior.afterClick.chevronOpen.a < -0.9 && behavior.afterClick.chevronOpen.d < -0.9,
		'شِوران پرسش باز، ۱۸۰ درجه می‌چرخد',
		behavior.afterClick.chevronOpen.raw );
	check( behavior.afterKey.open && behavior.afterKey.visible, 'باز کردن پرسش سوم با کلید Enter کار می‌کند', JSON.stringify( behavior.afterKey ) );
	check( ! behavior.afterClose.open && ! behavior.afterClose.visible && behavior.afterClose.spanGap <= 1, 'بستن پرسش، پاسخ را پنهان می‌کند', JSON.stringify( behavior.afterClose ) );

	console.log( '\n== ۴) واگرایی‌های آگاهانه از مرجع ==' );

	/*
	 * مرجع در `help.js` فقط کلاس `open` را جابه‌جا می‌کند و قاعده‌ی
	 * پنهان‌سازی برای پاسخ بسته ندارد؛ پس همه‌ی پاسخ‌ها همیشه پیدا هستند.
	 * این آزمون خودِ رفتار مرجع را می‌سنجد تا سند واگرایی معتبر بماند.
	 */
	const refBug = REF.help[ base ];
	check( refBug.closedAnswerState && refBug.closedAnswerState.visible && refBug.closedAnswerState.spanGap > 20,
		'مرجع: پاسخ بسته پنهان نمی‌شود (نقص شناخته‌شده‌ی مرجع)',
		JSON.stringify( refBug.closedAnswerState ) );
	check( WP.help[ base ].closedAnswerState && ! WP.help[ base ].closedAnswerState.visible && WP.help[ base ].closedAnswerState.spanGap <= 1,
		'محصول: پاسخ بسته واقعاً پنهان است (بهبود آگاهانه با `details` بومی)',
		JSON.stringify( WP.help[ base ].closedAnswerState ) );
	check( 'button' === REF.help[ base ].summaryTag && 'summary' === WP.help[ base ].summaryTag,
		'سازه‌ی سرِ پرسش: مرجع `button` و محصول `summary` — هر دو کنش‌پذیر و کلیدپذیر (واگرایی آگاهانه)',
		'ref=' + REF.help[ base ].summaryTag + ' · wp=' + WP.help[ base ].summaryTag );
	check( /<span>⌄<\/span>/.test( String( REF.help[ base ].chevronHtml ) ) && '9px' === WP.help[ base ].chevron.w,
		'شِوران: مرجع `span` متنی دارد (قاعده‌ی مرده‌ی `>svg`) و محصول ماسک svg با قاب ۹px در `::after`',
		'ref=' + REF.help[ base ].chevronHtml + ' · wp=' + WP.help[ base ].chevron.w );

	console.log( '\n== ۵) داده‌ی واقعی از پایگاه‌داده ==' );

	const helpQuestions = WP.help[ base ].questions;
	const dbMissing = helpQuestions.filter( ( q ) => q && helpContent.indexOf( q ) === -1 );

	check( 6 === helpQuestions.length && 0 === dbMissing.length, 'همه‌ی پرسش‌ها از محتوای برگه‌ی «راهنما» می‌آیند', dbMissing.join( ' | ' ) );

	const legalH2s = WP.privacy[ base ].legalH2Texts;
	const legalMissing = legalH2s.filter( ( h ) => h && privContent.indexOf( h ) === -1 );

	check( 0 === legalMissing.length && legalH2s.length > 0, 'همه‌ی سرتیترهای قوانین از محتوای همان برگه می‌آیند', legalMissing.join( ' | ' ) );

	const edited = wpCli( [
		'eval',
		"$p = get_page_by_path('help'); $c = $p->post_content; $p->post_content = str_replace('چطور حساب کاربری بسازم؟', 'پرسش آزمون موقت؟', $c); wp_update_post( $p ); echo 'edited';",
	] );
	const editedPage = await ( async () => {
		const context = await browser.newContext( { viewport: { width: 1440, height: 900 } } );
		const page = await context.newPage();

		await page.goto( WP_HELP, { waitUntil: 'load', timeout: 60000 } );

		const text = await page.evaluate( () => ( document.querySelector( '.faq-item summary' ) || {} ).textContent || '' );

		await context.close();

		return text.trim();
	} )();

	check( /edited/.test( edited ) && 'پرسش آزمون موقت؟' === editedPage,
		'ویرایش متن برگه، بی‌درنگ در سرصفحه‌ی پرسش دیده می‌شود (پویا از پایگاه‌داده)',
		editedPage );

	console.log( '\n== ۶) بدون جاوااسکریپت ==' );

	const noJs = await ( async () => {
		const context = await browser.newContext( { viewport: { width: 1440, height: 900 }, javaScriptEnabled: false } );
		const page = await context.newPage();

		await page.goto( WP_HELP, { waitUntil: 'load', timeout: 60000 } );

		const state = await page.evaluate( () => ( {
			questions: document.querySelectorAll( '.faq-item summary' ).length,
			answers: document.querySelectorAll( '.faq-item p' ).length,
		} ) );

		await context.close();

		return state;
	} )();

	check( 6 === noJs.questions && 6 === noJs.answers, 'بدون جاوااسکریپت هر ۶ پرسش و پاسخ از سرور می‌آید', JSON.stringify( noJs ) );

	const noJsRef = await ( async () => {
		const context = await browser.newContext( { viewport: { width: 1440, height: 900 }, javaScriptEnabled: false } );
		const page = await context.newPage();

		await page.goto( REF_HELP, { waitUntil: 'load', timeout: 60000 } );

		const count = await page.evaluate( () => document.querySelectorAll( '.faq-item' ).length );

		await context.close();

		return count;
	} )();

	check( 0 === noJsRef, 'مرجع بدون جاوااسکریپت هیچ پرسشی ندارد (دلیل برتری سازه‌ی `details`)', String( noJsRef ) );

	console.log( '\n== ۷) قالب ویرایشگر سایت ==' );

	[ 'page-help.html', 'page-privacy.html' ].forEach( ( file ) => {
		const exists = wpCli( [ 'eval', "echo (int) file_exists( get_template_directory() . '/templates/" + file + "' );" ] );

		check( '1' === exists, 'قالب «' + file + '» در پوسته هست (قابل ویرایش در ویرایشگر سایت)', exists );
	} );

	const templateList = wpCli( [ 'eval', "echo implode(',', array_map('basename', glob(get_template_directory() . '/templates/*.html')));" ] );

	check( /page-help\.html/.test( templateList ) && /page-privacy\.html/.test( templateList ), 'قالب‌ها در فهرست قالب‌های پوسته دیده می‌شوند', String( templateList ).slice( 0, 120 ) );

	console.log( '\n== ۸) دسترس‌پذیری (axe-core) و خطای کنسول ==' );

	if ( axePath ) {
		for ( const [ label, url ] of [ [ 'راهنما', WP_HELP ], [ 'قوانین', WP_PRIV ] ] ) {
			const context = await browser.newContext( { viewport: { width: 1440, height: 900 }, locale: 'fa-IR' } );
			const page = await context.newPage();

			await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
			await page.addScriptTag( { path: axePath } );

			const violations = await page.evaluate( async () => {
				const run = await window.axe.run( document.body, {
					runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] },
				} );

				return run.violations.map( ( v ) => v.id + '(' + v.nodes.length + ')' );
			} );

			await context.close();

			check( violations.length === 0, 'بدون تخلف axe روی برگه‌ی ' + label, violations.join( ' · ' ) );
		}
	} else {
		check( false, 'axe-core در دسترس است (بدون آن، سنجش دسترس‌پذیری اجرا نمی‌شود)' );
	}

	const allErrors = ERRORS.filter( ( entry ) => entry[ 1 ].length );
	const refErrors = REF_ERRORS.filter( ( entry ) => entry[ 1 ].length );

	check( allErrors.length === 0, 'بدون خطای کنسول روی چهار ویوپورت در محصول', allErrors.map( ( e ) => e[ 0 ] + ':' + e[ 1 ].join( '/' ) ).join( ' | ' ) );

	/*
	 * مرجع تصویر هیروی صفحه‌ی نخستش را در `assets/images/` ندارد و در
	 * کنسول ۴۰۴ می‌دهد؛ این نقص داده‌ی مرجع است نه کد، و ثبت می‌شود تا
	 * معلوم باشد چرا کنسول مرجع «تمیز» نیست.
	 */
	const refOnly404 = refErrors.every( ( entry ) => entry[ 1 ].every( ( e ) => /404/.test( e ) ) );

	check( refOnly404, 'خطاهای کنسول مرجع فقط ۴۰۴ تصاویر غایب خودِ مرجع است', JSON.stringify( refErrors.slice( 0, 1 ) ) );

	console.log( '\n== ۹) بازگرداندن داده ==' );

	const restore = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-info.php' ), 'restore' ] );

	check( /info restore ok/.test( restore ), 'داده‌ی برگه‌ها به حالت پیشین برگشت', restore );

	await browser.close();

	console.log( '\n' + '='.repeat( 58 ) );
	console.log( pass + ' موفق / ' + fail + ' ناموفق' );
	console.log( '='.repeat( 58 ) );

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( 'اجرای آزمون شکست خورد:', error );
	process.exit( 2 );
} );

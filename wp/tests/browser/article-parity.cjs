/**
 * آزمون هم‌سانی برگه‌ی تک‌نوشته با مرجع سینورا (`cinora/article.html`).
 *
 * چه می‌سنجد؟
 *   ۱) ساختار: همان کلاس‌های مرجع روی برگه‌ی محصول رندر شده‌اند
 *      (`.article-page-header`، `.article-author`، `.article-layout`،
 *      `.article-body`، `.article-lead`، `.article-chapter`،
 *      `.article-tags`، `.toc-card`، `.reading-progress`،
 *      `.article-related`، `.comments-section`).
 *   ۲) هندسه: مقدار محاسبه‌شده‌ی مرورگر در ۱۴۴۰/۱۱۰۰/۹۸۰/۷۶۸/۳۹۰ در
 *      برابر مرجع، برای سرصفحه، شبکه‌ی متن/ستون کنار، پوشش، لید،
 *      نقل‌قول، فصل‌ها، برچسب‌ها، کارت فهرست و ردیف آثار مرتبط.
 *   ۳) رفتار: فهرست فصل‌ها به فصل‌های واقعی متن اشاره می‌کند، نوار
 *      پیشرفت با پیمایش جلو می‌رود و درصد فارسی می‌شود، فصل فعال
 *      نشانه می‌گیرد و دکمه‌ی کپی لینک واکنش نشان می‌دهد.
 *   ۴) داده‌ی واقعی: فصل‌ها از تیترهای `h2` خودِ نوشته، برچسب‌ها از
 *      تاکسونومی واقعی، آثار مرتبط از آثار واقعی، نویسنده از کاربر
 *      وردپرس و تاریخ/زمان مطالعه از خودِ نوشته.
 *   ۵) بدون جاوااسکریپت: متن کامل و فصل‌ها از سرور رندر می‌شوند.
 *   ۶) دسترس‌پذیری (axe) و صفر خطای جاوااسکریپت.
 *
 * پیش‌نیاز: `seed-magazine.php` و `seed-article.php` (این آزمون خودش
 * هر دو را می‌کارد و در پایان برمی‌گرداند).
 *
 * اجرا: node article-parity.cjs
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

const WP_URL  = process.env.WP_ARTICLE || 'http://localhost:8099/dune-world/';
const REF_URL = process.env.REF_ARTICLE || 'http://localhost:8098/article.html';
const WP_ROOT = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI  = process.env.WP_CLI || '/usr/local/bin/wp';
const QA_DIR  = path.join( __dirname, '..', 'qa-env' );

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
 * @param {string} label  برچسب.
 * @param {Object} a      سوییچ مرجع.
 * @param {Object} b      سوییچ محصول.
 * @param {string} prop   نام ویژگی.
 * @param {number} tol    تلورانس.
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
 * اندازه‌های برگه‌ی مقاله.
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

			return { w: Math.round( r.width ), h: Math.round( r.height ), x: Math.round( r.left ) };
		};

		const style = ( sel, props ) => {
			const el = document.querySelector( sel );

			if ( ! el ) {
				return null;
			}

			const cs  = getComputedStyle( el );
			const out = {};

			props.forEach( ( p ) => {
				const value = cs[ p ];
				out[ p ] = /^-?\d*\.?\d+(px|)$/.test( value ) ? Math.round( parseFloat( value ) ) : value;
			} );

			return out;
		};

		const columns = ( sel ) => {
			const el = document.querySelector( sel );

			return el ? getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length : 0;
		};

		return {
			has: {
				page: !!document.querySelector( '.article-page' ),
				header: !!document.querySelector( '.article-page-header' ),
				author: !!document.querySelector( '.article-author' ),
				layout: !!document.querySelector( '.article-layout' ),
				body: !!document.querySelector( '.article-body' ),
				lead: !!document.querySelector( '.article-lead' ),
				chapter: !!document.querySelector( '.article-chapter' ),
				tags: !!document.querySelector( '.article-tags' ),
				sidebar: !!document.querySelector( '.article-sidebar' ),
				toc: !!document.querySelector( '.toc-card' ),
				progress: !!document.querySelector( '.reading-progress' ),
				related: !!document.querySelector( '.article-related' ),
				comments: !!document.querySelector( '.comments-section' ),
			},
			boxes: {
				page: box( '.article-page' ),
				header: box( '.article-page-header' ),
				body: box( '.article-body' ),
				lead: box( '.article-lead' ),
				chapter: box( '.article-chapter' ),
				cover: box( '.article-cover img' ) || box( '.article-cover' ),
				relatedImg: box( '.article-related > a > img' ),
				tocCard: box( '.toc-card' ),
				more: box( '.home-section .articles-grid' ),
				moreCard: box( '.home-section .articles-grid .article-card' ),
			},
			styles: {
				page: style( '.article-page', [ 'paddingTop', 'paddingBottom' ] ),
				header: style( '.article-page-header', [ 'maxWidth', 'marginBottom' ] ),
				h1: style( '.article-page-header h1', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				desc: style( '.article-page-header > p', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				author: style( '.article-author', [ 'gap', 'marginTop' ] ),
				authorStrong: style( '.article-author strong', [ 'fontSize' ] ),
				authorSmall: style( '.article-author small', [ 'fontSize', 'marginTop' ] ),
				avatar: style( '.article-author .comment-avatar', [ 'width', 'height', 'fontSize' ] ),
				exclusiveTag: style( '.article-page-header .exclusive-tag', [ 'fontSize', 'paddingTop', 'paddingInlineStart', 'borderTopLeftRadius' ] ),
				layout: style( '.article-layout', [ 'gap', 'display' ] ),
				lead: style( '.article-lead', [ 'fontSize', 'lineHeight', 'marginTop', 'fontWeight' ] ),
				quote: style( '.article-body blockquote', [ 'fontSize', 'lineHeight', 'marginTop', 'paddingTop', 'paddingInlineStart', 'borderTopLeftRadius', 'borderInlineStartWidth' ] ),
				chapter: style( '.article-chapter', [ 'paddingTop', 'paddingBottom', 'borderBottomWidth', 'scrollMarginTop' ] ),
				chapterNum: style( '.article-chapter > span', [ 'fontSize' ] ),
				chapterH2: style( '.article-chapter h2', [ 'fontSize', 'marginTop' ] ),
				chapterP: style( '.article-chapter p', [ 'fontSize', 'lineHeight', 'marginTop', 'textAlign' ] ),
				paragraph: style( '.article-body .wp-block-post-content > p:not(.article-lead), .article-body p:not(.article-lead)', [ 'fontSize', 'lineHeight' ] ),
				tags: style( '.article-tags', [ 'gap', 'marginTop' ] ),
				tag: style( '.article-tags a', [ 'fontSize', 'paddingTop', 'paddingInlineStart', 'borderTopLeftRadius' ] ),
				tagHash: { content: window.getComputedStyle( document.querySelector( '.article-tags a' ), '::before' ).content },
				sidebar: style( '.article-sidebar', [ 'position', 'top', 'display' ] ),
				/*
				 * ستون کنار در ≥۹۸۱px هست و در موبایل پنهان می‌شود؛ پس سنجه‌های
				 * مخصوص آن باید مشروط به همین پرچم باشند. پیش‌تر پرچم
				 * `__sidebarVisible` هرگز مقدار نمی‌گرفت و شاخه‌ی مربوطه هرگز
				 * اجرا نمی‌شد (نقص آزمون، کشف‌شده در موج هجدهم).
				 */
				sidebarVisible: ( () => {
					const el = document.querySelector( '.article-sidebar' );

					return !! el && 'none' !== getComputedStyle( el ).display && el.getBoundingClientRect().width > 0;
				} )(),
				/*
				 * فاصله‌ی عمودی فرزندان ستون کنار. مرجع: `block` + حاشیه‌ها که
				 * جمع می‌شوند (۲۰px بلوک‌ها + ۲۳px کارت ترویجی → ۲۳px دیده‌شده).
				 */
				sidebarGaps: ( () => {
					const sb = document.querySelector( '.article-sidebar' );

					if ( ! sb ) {
						return null;
					}

					const kids = [ ...sb.children ].map( ( el ) => el.getBoundingClientRect() );

					return kids.slice( 1 ).map( ( r, i ) => Math.round( r.top - kids[ i ].bottom ) );
				} )(),
				promo: style( '.sidebar-promo', [ 'paddingTop', 'marginTop', 'borderTopLeftRadius' ] ),
				promoH3: style( '.sidebar-promo h3', [ 'fontSize', 'marginTop' ] ),
				promoP: style( '.sidebar-promo p', [ 'fontSize', 'marginTop' ] ),
				promoSpan: style( '.sidebar-promo > span', [ 'fontSize', 'marginTop' ] ),
				commentArea: style( '.comments-section', [ 'marginTop' ] ),
				commentH2: style( '.comments-section h2', [ 'fontSize' ] ),
				commentTextarea: style( '.comment-form textarea', [ 'fontSize', 'lineHeight' ] ),
				toc: style( '.toc-card', [ 'paddingTop', 'paddingInlineStart', 'borderTopLeftRadius' ] ),
				tocEyebrow: style( '.toc-card .eyebrow', [ 'fontSize' ] ),
				tocH3: style( '.toc-card h3', [ 'fontSize', 'lineHeight', 'marginTop' ] ),
				tocNav: style( '.toc-card nav', [ 'gap', 'marginTop' ] ),
				tocLink: style( '.toc-card nav > a', [ 'fontSize', 'paddingTop', 'paddingInlineStart', 'gap' ] ),
				tocNum: style( '.toc-card nav > a > span', [ 'width', 'paddingTop', 'fontSize', 'borderTopLeftRadius' ] ),
				progress: style( '.reading-progress', [ 'height', 'marginTop', 'borderTopLeftRadius' ] ),
				tocSmall: style( '.toc-card > small', [ 'fontSize', 'marginTop' ] ),
				related: style( '.article-related', [ 'marginTop', 'paddingTop', 'borderTopLeftRadius' ] ),
				relatedH3: style( '.article-related h3', [ 'fontSize', 'gap', 'marginBottom' ] ),
				relatedLink: style( '.article-related > a', [ 'gap', 'marginTop' ] ),
				relatedStrong: style( '.article-related strong', [ 'fontSize' ] ),
				relatedSmall: style( '.article-related small', [ 'fontSize', 'marginTop' ] ),
			},
			columns: {
				layout: columns( '.article-layout' ),
				more: columns( '.home-section .articles-grid' ),
			},
			counts: {
				chapters: document.querySelectorAll( '.article-chapter' ).length,
				tocLinks: document.querySelectorAll( '.toc-card nav > a' ).length,
				tags: document.querySelectorAll( '.article-tags a' ).length,
				related: document.querySelectorAll( '.article-related > a' ).length,
				more: document.querySelectorAll( '.home-section .articles-grid .article-card' ).length,
			},
			text: {
				h1: ( document.querySelector( '.article-page-header h1' ) || {} ).textContent || '',
				desc: ( document.querySelector( '.article-page-header > p' ) || {} ).textContent || '',
				meta: ( document.querySelector( '[data-manacore-article-meta]' ) || {} ).textContent || '',
				author: ( document.querySelector( '.article-author strong' ) || {} ).textContent || '',
				percent: ( document.querySelector( '[data-manacore-percent]' ) || {} ).textContent || '',
			},
			tocHrefs: [ ...document.querySelectorAll( '.toc-card nav > a' ) ].map( ( a ) => a.getAttribute( 'href' ) ),
			chapterIds: [ ...document.querySelectorAll( '.article-chapter' ) ].map( ( h ) => h.id ),
		};
	} );
}

( async () => {
	/* ---------- ۰) کاشت داده ---------- */
	console.log( '== ۰) کاشت داده‌ی آزمون ==' );

	const seedMagazine = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-magazine.php' ) ] );
	const seedArticle  = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-article.php' ) ] );

	check( /magazine seed ok/.test( seedMagazine ), 'داده‌ی مجله کاشته شد', seedMagazine );
	check( /article seed ok/.test( seedArticle ), 'داده‌ی مقاله کاشته شد', seedArticle );

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page    = await browser.newPage( { viewport: { width: 1440, height: 1100 } } );
	const errors  = [];

	page.on( 'pageerror', ( e ) => errors.push( 'WP: ' + String( e ) ) );

	/* ---------- ۱) وجود و ساختار ---------- */
	console.log( '\n== ۱) وجود برگه و ساختار ==' );

	const wpStatus = await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === wpStatus, 'برگه‌ی نوشته با ۲۰۰ پاسخ می‌دهد', String( wpStatus ) );

	const refStatus = await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } ).then( ( r ) => ( r ? r.status() : 0 ) ).catch( () => 0 );
	check( 200 === refStatus, 'مرجع article.html در دسترس است', String( refStatus ) );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 400 );
	let wp = await measure( page );

	const structure = [ 'page', 'header', 'author', 'layout', 'body', 'lead', 'chapter', 'tags', 'sidebar', 'toc', 'progress', 'related', 'comments' ];
	structure.forEach( ( key ) => {
		check( wp.has[ key ], 'ساختار «' + key + '» رندر شده' );
	} );

	check( wp.counts.chapters >= 3, 'فصل‌های متن ساخته شده‌اند', String( wp.counts.chapters ) );
	check( wp.counts.tags >= 1, 'برچسب‌های واقعی رندر شده‌اند', String( wp.counts.tags ) );
	check( wp.counts.related >= 1, 'ردیف «بعد از خواندن، ببین.» پر شده', String( wp.counts.related ) );
	check( wp.counts.more >= 1, 'بخش «داستان هنوز ادامه دارد...» کارت دارد', String( wp.counts.more ) );

	/* ---------- ۲) هندسه در پنج عرض ---------- */
	console.log( '\n== ۲) هندسه (مرجع ↔ محصول) ==' );

	for ( const width of [ 1440, 1100, 980, 768, 390 ] ) {
		await page.setViewportSize( { width, height: 1200 } );

		await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 500 );
		const ref = await measure( page );

		await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 500 );
		wp = await measure( page );

		const w = '@' + width;

		/* --- سرصفحه --- */
		pair( w + ' عرض سرصفحه‌ی مقاله', ref.boxes.header.w, wp.boxes.header.w, 2 );
		prop( w + ' فاصله‌ی بالای برگه', ref.styles.page, wp.styles.page, 'paddingTop', 0 );
		prop( w + ' فاصله‌ی پایین برگه', ref.styles.page, wp.styles.page, 'paddingBottom', 0 );
		prop( w + ' حاشیه‌ی پایین سرصفحه', ref.styles.header, wp.styles.header, 'marginBottom', 0 );
		prop( w + ' قلم تیتر مقاله', ref.styles.h1, wp.styles.h1, 'fontSize', 0 );
		prop( w + ' خط‌ارتفاع تیتر', ref.styles.h1, wp.styles.h1, 'lineHeight', 2 );
		prop( w + ' فاصله‌ی تیتر', ref.styles.h1, wp.styles.h1, 'marginTop', 1 );
		prop( w + ' قلم توضیح', ref.styles.desc, wp.styles.desc, 'fontSize', 0 );
		prop( w + ' خط‌ارتفاع توضیح', ref.styles.desc, wp.styles.desc, 'lineHeight', 2 );
		prop( w + ' فاصله‌ی توضیح', ref.styles.desc, wp.styles.desc, 'marginTop', 1 );
		prop( w + ' قلم نشان دسته', ref.styles.exclusiveTag, wp.styles.exclusiveTag, 'fontSize', 0 );

		/* --- سطر نویسنده --- */
		prop( w + ' فاصله‌ی سطر نویسنده', ref.styles.author, wp.styles.author, 'gap', 0 );
		prop( w + ' فاصله‌ی بالای نویسنده', ref.styles.author, wp.styles.author, 'marginTop', 0 );
		prop( w + ' قلم نام نویسنده', ref.styles.authorStrong, wp.styles.authorStrong, 'fontSize', 0 );
		prop( w + ' قلم فراداده‌ی نویسنده', ref.styles.authorSmall, wp.styles.authorSmall, 'fontSize', 0 );
		prop( w + ' عرض نگاره‌ی نویسنده', ref.styles.avatar, wp.styles.avatar, 'width', 1 );
		prop( w + ' ارتفاع نگاره‌ی نویسنده', ref.styles.avatar, wp.styles.avatar, 'height', 1 );
		prop( w + ' قلم نگاره‌ی نویسنده', ref.styles.avatar, wp.styles.avatar, 'fontSize', 0 );

		/* --- پهنای ستون متن --- */
		pair( w + ' پهنای ستون متن', ref.boxes.body.w, wp.boxes.body.w, 2 );
		pair( w + ' پهنای پاراگراف لید', ref.boxes.lead.w, wp.boxes.lead.w, 2 );
		pair( w + ' لبه‌ی راست پاراگراف لید', ref.boxes.lead.x, wp.boxes.lead.x, 2 );
		pair( w + ' پهنای فصل', ref.boxes.chapter.w, wp.boxes.chapter.w, 2 );

		/* --- شبکه و متن --- */
		prop( w + ' نمایش شبکه‌ی مقاله', ref.styles.layout, wp.styles.layout, 'display', 0 );
		prop( w + ' فاصله‌ی شبکه‌ی مقاله', ref.styles.layout, wp.styles.layout, 'gap', 0 );

		if ( 'grid' === ref.styles.layout.display ) {
			pair( w + ' ستون‌های شبکه‌ی مقاله', ref.columns.layout, wp.columns.layout, 0 );
		}

		/*
		 * بخش «داستان هنوز ادامه دارد…» بیرون از شبکه‌ی مقاله است و در مرجع
		 * پهنای کامل ظرف (۱۴۴۰px → ۱۳۲۸) را می‌گیرد؛ اگر این بخش داخل
		 * چیدمان «محدود» قالب بیفتد، به پهنای ستون متن (۸۲۰) له می‌شود و
		 * کارت‌ها ۲۵۸px می‌شوند — همان پس‌رفتی که در موج شانزدهم گرفته شد.
		 */
		pair( w + ' ستون‌های شبکه‌ی «داستان هنوز ادامه دارد»', ref.columns.more, wp.columns.more, 0 );
		pair( w + ' پهنای بخش «داستان هنوز ادامه دارد»', ref.boxes.more && ref.boxes.more.w, wp.boxes.more && wp.boxes.more.w, 2 );
		pair( w + ' لبه‌ی راست بخش «داستان هنوز ادامه دارد»', ref.boxes.more && ref.boxes.more.x, wp.boxes.more && wp.boxes.more.x, 2 );
		pair( w + ' پهنای کارت بخش «داستان هنوز ادامه دارد»', ref.boxes.moreCard && ref.boxes.moreCard.w, wp.boxes.moreCard && wp.boxes.moreCard.w, 2 );

		prop( w + ' فاصله‌ی لید', ref.styles.lead, wp.styles.lead, 'marginTop', 1 );
		prop( w + ' قلم لید', ref.styles.lead, wp.styles.lead, 'fontSize', 0 );
		prop( w + ' خط‌ارتفاع لید', ref.styles.lead, wp.styles.lead, 'lineHeight', 2 );
		prop( w + ' وزن لید', ref.styles.lead, wp.styles.lead, 'fontWeight', 0 );
		prop( w + ' قلم نقل‌قول', ref.styles.quote, wp.styles.quote, 'fontSize', 0 );
		prop( w + ' پدینگ نقل‌قول', ref.styles.quote, wp.styles.quote, 'paddingTop', 0 );
		prop( w + ' فاصله‌ی نقل‌قول', ref.styles.quote, wp.styles.quote, 'marginTop', 0 );

		/* --- فصل‌ها --- */
		prop( w + ' پدینگ بالای فصل', ref.styles.chapter, wp.styles.chapter, 'paddingTop', 0 );
		prop( w + ' پدینگ پایین فصل', ref.styles.chapter, wp.styles.chapter, 'paddingBottom', 0 );
		prop( w + ' خط جداکننده‌ی فصل', ref.styles.chapter, wp.styles.chapter, 'borderBottomWidth', 0 );
		prop( w + ' فاصله‌ی اسکرول فصل', ref.styles.chapter, wp.styles.chapter, 'scrollMarginTop', 0 );
		prop( w + ' قلم شماره‌ی فصل', ref.styles.chapterNum, wp.styles.chapterNum, 'fontSize', 0 );
		prop( w + ' قلم تیتر فصل', ref.styles.chapterH2, wp.styles.chapterH2, 'fontSize', 0 );
		prop( w + ' فاصله‌ی تیتر فصل', ref.styles.chapterH2, wp.styles.chapterH2, 'marginTop', 0 );
		prop( w + ' قلم متن فصل', ref.styles.chapterP, wp.styles.chapterP, 'fontSize', 0 );
		prop( w + ' خط‌ارتفاع متن فصل', ref.styles.chapterP, wp.styles.chapterP, 'lineHeight', 2 );
		prop( w + ' فاصله‌ی متن فصل', ref.styles.chapterP, wp.styles.chapterP, 'marginTop', 1 );
		prop( w + ' تراز متن فصل', ref.styles.chapterP, wp.styles.chapterP, 'textAlign', 0 );

		/* --- برچسب‌ها --- */
		prop( w + ' فاصله‌ی برچسب‌ها', ref.styles.tags, wp.styles.tags, 'gap', 0 );
		prop( w + ' فاصله‌ی بالای برچسب‌ها', ref.styles.tags, wp.styles.tags, 'marginTop', 0 );
		prop( w + ' قلم برچسب', ref.styles.tag, wp.styles.tag, 'fontSize', 0 );
		prop( w + ' پدینگ برچسب', ref.styles.tag, wp.styles.tag, 'paddingTop', 0 );

		/* --- ستون کنار --- */
		prop( w + ' چسبندگی ستون کنار', ref.styles.sidebar, wp.styles.sidebar, 'position', 0 );
		prop( w + ' نمایش ستون کنار (مرجع `block` است، نه flex)', ref.styles.sidebar, wp.styles.sidebar, 'display' );

		if ( ref.styles.sidebarVisible && wp.styles.sidebarVisible && ref.styles.sidebarGaps && wp.styles.sidebarGaps ) {
			pair(
				w + ' فاصله‌ی فرزند اول و دوم ستون کنار',
				ref.styles.sidebarGaps[ 0 ],
				wp.styles.sidebarGaps[ 0 ],
				1
			);
			pair(
				w + ' فاصله‌ی فرزند دوم و کارت ترویجی',
				ref.styles.sidebarGaps[ ref.styles.sidebarGaps.length - 1 ],
				wp.styles.sidebarGaps[ wp.styles.sidebarGaps.length - 1 ],
				1
			);
		}
		prop( w + ' پدینگ کارت فهرست', ref.styles.toc, wp.styles.toc, 'paddingTop', 0 );
		prop( w + ' قلم ریزسطر فهرست', ref.styles.tocEyebrow, wp.styles.tocEyebrow, 'fontSize', 0 );
		prop( w + ' قلم تیتر فهرست', ref.styles.tocH3, wp.styles.tocH3, 'fontSize', 0 );
		prop( w + ' فاصله‌ی ردیف‌های فهرست', ref.styles.tocNav, wp.styles.tocNav, 'gap', 0 );
		prop( w + ' قلم پیوند فهرست', ref.styles.tocLink, wp.styles.tocLink, 'fontSize', 0 );
		prop( w + ' عرض شماره‌ی فصل در فهرست', ref.styles.tocNum, wp.styles.tocNum, 'width', 0 );
		prop( w + ' ارتفاع نوار پیشرفت', ref.styles.progress, wp.styles.progress, 'height', 0 );
		prop( w + ' قلم درصد مطالعه', ref.styles.tocSmall, wp.styles.tocSmall, 'fontSize', 0 );
		prop( w + ' پدینگ کارت ترویجی', ref.styles.promo, wp.styles.promo, 'paddingTop', 0 );
		prop( w + ' فاصله‌ی کارت ترویجی', ref.styles.promo, wp.styles.promo, 'marginTop', 0 );
		prop( w + ' قلم تیتر کارت ترویجی', ref.styles.promoH3, wp.styles.promoH3, 'fontSize', 0 );
		prop( w + ' فاصله‌ی تیتر کارت ترویجی', ref.styles.promoH3, wp.styles.promoH3, 'marginTop', 0 );
		prop( w + ' قلم متن کارت ترویجی', ref.styles.promoP, wp.styles.promoP, 'fontSize', 0 );
		prop( w + ' قلم پیوند کارت ترویجی', ref.styles.promoSpan, wp.styles.promoSpan, 'fontSize', 0 );
		prop( w + ' فاصله‌ی بالای آثار مرتبط', ref.styles.related, wp.styles.related, 'marginTop', 0 );
		prop( w + ' پدینگ آثار مرتبط', ref.styles.related, wp.styles.related, 'paddingTop', 0 );
		prop( w + ' قلم تیتر آثار مرتبط', ref.styles.relatedH3, wp.styles.relatedH3, 'fontSize', 0 );
		prop( w + ' فاصله‌ی تیتر آثار مرتبط', ref.styles.relatedH3, wp.styles.relatedH3, 'marginBottom', 0 );
		prop( w + ' قلم نام اثر مرتبط', ref.styles.relatedStrong, wp.styles.relatedStrong, 'fontSize', 0 );
		prop( w + ' قلم نام اصلی اثر مرتبط', ref.styles.relatedSmall, wp.styles.relatedSmall, 'fontSize', 0 );

		/*
		 * ردیف «بعد از خواندن، ببین.»: مرجع پیوندها را داخل
		 * `#related-items` می‌گذارد، پس قاعده‌ی خودش (`.article-related>a`)
		 * هرگز روی آن‌ها نمی‌نشیند و پوسترها بی‌اندازه می‌مانند. ما همان
		 * قاعده‌ی طراحی‌شده‌ی مرجع را پیاده می‌کنیم؛ پس این‌جا «عدد نوشته‌ی
		 * مرجع» سنجیده می‌شود، نه مقدار محاسبه‌شده‌ی صفحه‌ی مرجع.
		 */
		if ( ref.styles.sidebarVisible && wp.styles.sidebarVisible ) {
			pair( w + ' عرض پوستر آثار مرتبط (طراحی مرجع)', 43, wp.boxes.relatedImg.w, 1 );
			pair( w + ' ارتفاع پوستر آثار مرتبط (طراحی مرجع)', 64, wp.boxes.relatedImg.h, 1 );
			prop( w + ' فاصله‌ی ردیف آثار مرتبط (طراحی مرجع)', { gap: 10, marginTop: 15 }, wp.styles.relatedLink, 'gap', 0 );
			prop( w + ' فاصله‌ی بالای ردیف آثار مرتبط (طراحی مرجع)', { gap: 10, marginTop: 15 }, wp.styles.relatedLink, 'marginTop', 1 );
		}

		/* --- دیدگاه‌ها --- */
		prop( w + ' فاصله‌ی بالای دیدگاه‌ها', ref.styles.commentArea, wp.styles.commentArea, 'marginTop', 0 );
		prop( w + ' قلم تیتر دیدگاه‌ها', ref.styles.commentH2, wp.styles.commentH2, 'fontSize', 0 );
		prop( w + ' قلم کادر دیدگاه', ref.styles.commentTextarea, wp.styles.commentTextarea, 'fontSize', 0 );
		prop( w + ' خط‌ارتفاع کادر دیدگاه', ref.styles.commentTextarea, wp.styles.commentTextarea, 'lineHeight', 2 );

		if ( ref.boxes.cover && wp.boxes.cover ) {
			pair( w + ' ارتفاع پوشش مقاله', ref.boxes.cover.h, wp.boxes.cover.h, 3 );
		}

		const overflow = await page.evaluate( () => document.documentElement.scrollWidth > window.innerWidth + 1 );
		check( ! overflow, w + ' برگه سرریز افقی ندارد' );
	}

	/* ---------- ۳) فهرست، پیشرفت و کپی لینک ---------- */
	console.log( '\n== ۳) رفتار (فهرست، پیشرفت، کپی) ==' );

	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 400 );
	wp = await measure( page );

	check( wp.counts.tocLinks === wp.counts.chapters, 'شمار پیوندهای فهرست = شمار فصل‌ها', wp.counts.tocLinks + ' ↔ ' + wp.counts.chapters );
	check( wp.counts.tocLinks >= 3, 'فهرست دست‌کم سه فصل دارد', String( wp.counts.tocLinks ) );

	const anchorsExist = await page.evaluate( () => [ ...document.querySelectorAll( '.toc-card nav > a' ) ]
		.every( ( a ) => !! document.getElementById( a.getAttribute( 'href' ).slice( 1 ) ) ) );
	check( anchorsExist, 'هر پیوند فهرست به فصل واقعی متن اشاره می‌کند' );

	const percentBefore = wp.text.percent;
	const widthBefore = await page.evaluate( () => document.querySelector( '.reading-progress > span' ).getBoundingClientRect().width );

	await page.evaluate( () => window.scrollTo( 0, Math.round( document.body.scrollHeight * 0.6 ) ) );
	await page.waitForTimeout( 400 );

	const afterScroll = await page.evaluate( () => ( {
		percent: ( document.querySelector( '[data-manacore-percent]' ) || {} ).textContent || '',
		width: document.querySelector( '.reading-progress > span' ).getBoundingClientRect().width,
		active: document.querySelectorAll( '.toc-card nav > a.active' ).length,
		aria: document.querySelectorAll( '.toc-card nav > a[aria-current]' ).length,
	} ) );

	check( afterScroll.width > widthBefore, 'نوار پیشرفت با پیمایش جلو می‌رود', widthBefore + ' → ' + afterScroll.width );
	check( /[۰-۹]/.test( afterScroll.percent ), 'درصد مطالعه با ارقام فارسی نوشته می‌شود', percentBefore + ' → ' + afterScroll.percent );
	check( afterScroll.active >= 1, 'فصل فعال در فهرست نشانه می‌گیرد', String( afterScroll.active ) );
	check( afterScroll.aria === afterScroll.active, 'فصل فعال `aria-current` هم می‌گیرد', String( afterScroll.aria ) );

	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 300 );

	const copyState = await page.evaluate( async () => {
		const button = document.querySelector( '[data-manacore-copy-link]' );
		const before = button.classList.contains( 'is-copied' );
		button.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
		const after = button.classList.contains( 'is-copied' );

		return { before, after, label: button.getAttribute( 'aria-label' ) };
	} );

	check( ! copyState.before && copyState.after, 'دکمه‌ی کپی لینک واکنش نشان می‌دهد', JSON.stringify( copyState ) );

	/* ---------- ۴) داده‌ی واقعی ---------- */
	console.log( '\n== ۴) داده‌ی واقعی روی برگه ==' );

	const data = wpCli( [ 'eval', `
$p = get_page_by_path( 'dune-world', OBJECT, 'post' );
echo implode( '|', array(
	$p->post_title,
	wp_strip_all_tags( get_the_excerpt( $p ) ),
	get_the_author_meta( 'display_name', $p->post_author ),
	implode( ',', wp_list_pluck( wp_get_post_tags( $p->ID ), 'name' ) ),
) );` ] );

	const [ title, desc, author, tags ] = data.split( '|' );

	check( wp.text.h1.trim() === title.trim(), 'تیتر از خودِ نوشته می‌آید', wp.text.h1.trim() + ' ↔ ' + title.trim() );
	check( wp.text.desc.trim() === desc.trim(), 'توضیح از چکیده‌ی واقعی می‌آید', wp.text.desc.trim() + ' ↔ ' + desc.trim() );
	check( wp.text.author.trim() === author.trim(), 'نام نویسنده از کاربر واقعی وردپرس می‌آید', wp.text.author.trim() + ' ↔ ' + author.trim() );
	check( /[۰-۹]/.test( wp.text.meta ) && /دقیقه/.test( wp.text.meta ), 'فراداده‌ی نویسنده تاریخ و زمان مطالعه دارد', wp.text.meta );

	const cardTags = await page.$$eval( '.article-tags a', ( links ) => links.map( ( a ) => a.textContent.trim() ) );
	check( cardTags.join( ',' ) === tags.trim(), 'برچسب‌ها از تاکسونومی واقعی نوشته می‌آیند', cardTags.join( ',' ) + ' ↔ ' + tags.trim() );
	check( ( wp.styles.tagHash.content || '' ).indexOf( '#' ) !== -1, 'هر برچسب با «#» نمایش داده می‌شود', String( wp.styles.tagHash.content ) );

	const related = await page.evaluate( () => [ ...document.querySelectorAll( '.article-related > a' ) ].map( ( a ) => ( {
		href: a.getAttribute( 'href' ),
		img: !! a.querySelector( 'img' ),
		title: ( a.querySelector( 'strong' ) || {} ).textContent || '',
	} ) ) );

	check( related.length >= 1 && related.every( ( r ) => r.href && r.img && r.title ), 'هر ردیف آثار مرتبط پیوند + پوستر + عنوان دارد', JSON.stringify( related.slice( 0, 2 ) ) );

	const relatedAlive = await page.evaluate( async () => {
		const href = ( document.querySelector( '.article-related > a' ) || {} ).href;

		if ( ! href ) {
			return 0;
		}

		const response = await fetch( href, { redirect: 'follow' } );

		return response.status;
	} );

	check( 200 === relatedAlive, 'پیوند نخستین اثر مرتبط زنده است', String( relatedAlive ) );

	/* ---------- ۵) بدون جاوااسکریپت ---------- */
	console.log( '\n== ۵) وضعیت سرور (بدون جاوااسکریپت) ==' );

	const noJs     = await browser.newContext( { javaScriptEnabled: false } );
	const noJsPage = await noJs.newPage();
	await noJsPage.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await noJsPage.waitForTimeout( 250 );

	const noJsState = await noJsPage.evaluate( () => ( {
		chapters: document.querySelectorAll( '.article-chapter' ).length,
		lead: document.querySelectorAll( '.article-lead' ).length,
		toc: document.querySelectorAll( '.toc-card nav > a' ).length,
		comments: document.querySelectorAll( '.comments-section #comment' ).length,
	} ) );

	check( noJsState.chapters >= 3, 'فصل‌ها بدون جاوااسکریپت هم رندر می‌شوند', String( noJsState.chapters ) );
	check( 1 === noJsState.lead, 'پاراگراف لید از سرور می‌آید', String( noJsState.lead ) );
	check( noJsState.toc >= 3, 'فهرست فصل‌ها بدون جاوااسکریپت هم هست', String( noJsState.toc ) );
	check( 1 === noJsState.comments, 'فرم دیدگاه بدون جاوااسکریپت کار می‌کند', String( noJsState.comments ) );
	await noJs.close();

	/* ---------- ۵.۵) نشانه‌گذاری بخش دیدگاه ---------- */

	/*
	 * قرارداد دستور: ظرف دیدگاه باید کلاس مرجع `comments-section` را داشته
	 * باشد و کادر اسپویل شناسه‌ی `comment-spoiler` مرجع را. مرجع فرمی با یک
	 * `textarea` + تیک اسپویل + دکمه دارد؛ وردپرس برای **مهمان** ناچار فیلدهای
	 * هویت (نام/ایمیل/نشانی/کوکی) را هم می‌گذارد — یک واگرایی معماری
	 * مستندشده، نه نقص. برای کاربر وارد‌شده، مجموعه‌ی فیلدها همان مرجع است.
	 */
	const commentsMarkup = await page.evaluate( () => {
		const sec = document.querySelector( '.comments-section' );
		const spoiler = document.querySelector( '#comment-spoiler' );
		const label = document.querySelector( 'label[for="comment-spoiler"]' );
		const form = sec ? sec.querySelector( 'form' ) : null;

		return {
			hasClass: !! sec,
			classList: sec ? sec.classList.contains( 'wp-block-comments' ) : false,
			spoiler: !! spoiler && 'checkbox' === spoiler.type,
			spoilerName: spoiler ? spoiler.getAttribute( 'name' ) : null,
			labelFor: !! label,
			textarea: form ? form.querySelectorAll( 'textarea' ).length : 0,
			identityFields: form ? form.querySelectorAll( 'input#author, input#email, input#url' ).length : 0,
			submit: form ? form.querySelectorAll( 'input#submit, button[type="submit"]' ).length : 0,
		};
	} );

	check( commentsMarkup.hasClass, 'بخش دیدگاه کلاس مرجع `comments-section` را دارد', JSON.stringify( commentsMarkup ) );
	check( commentsMarkup.spoiler && 'manacore_spoiler' === commentsMarkup.spoilerName, 'کادر «تمام دیدگاه اسپویل دارد» با شناسه‌ی مرجع `#comment-spoiler` رندر شده است', String( commentsMarkup.spoilerName ) );
	check( commentsMarkup.labelFor, 'برچسب کادر اسپویل با `for` به همان فیلد بسته شده است', String( commentsMarkup.labelFor ) );
	check( 1 === commentsMarkup.textarea && 1 === commentsMarkup.submit, 'فرم دیدگاه یک کادر متن و یک دکمه‌ی ارسال دارد (شکل مرجع)', JSON.stringify( commentsMarkup ) );
	check( 3 === commentsMarkup.identityFields, 'فیلدهای هویت مهمان (نام/ایمیل/نشانی) هستند — رفتار بومی وردپرس برای مهمان', String( commentsMarkup.identityFields ) );

	/* ---------- ۶) دسترس‌پذیری ---------- */
	console.log( '\n== ۶) دسترس‌پذیری (axe-core) ==' );

	if ( axePath ) {
		await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 250 );
		await page.addScriptTag( { path: axePath } );
		const violations = await page.evaluate( async () => {
			const run = await window.axe.run( document.body, {
				runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
			} );
			return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
		} );
		check( violations.length === 0, 'بدون تخلف axe روی برگه‌ی مقاله', violations.join( ' · ' ) );
	} else {
		check( false, 'axe-core در دسترس است (بدون آن، سنجش دسترس‌پذیری اجرا نمی‌شود)' );
	}

	/* ---------- ۷) خطای جاوااسکریپت ---------- */
	console.log( '\n== ۷) خطای جاوااسکریپت ==' );
	check( errors.length === 0, 'بدون خطای جاوااسکریپت روی برگه‌ی مقاله', errors.slice( 0, 3 ).join( ' | ' ) );

	/* ---------- ۸) پاک‌سازی ---------- */
	console.log( '\n== ۸) پاک‌سازی داده‌ی آزمون ==' );

	const restore = wpCli( [ 'eval-file', path.join( QA_DIR, 'seed-article.php' ), 'restore' ] );
	check( /article restore ok/.test( restore ), 'داده‌ی آزمون به حالت پیشین برگشت', restore );

	await browser.close();

	console.log( '\n==========================================================' );
	console.log( 'موفق: ' + pass + '   ناموفق: ' + fail + '   (مرجع: ' + REF_URL + ')' );
	console.log( '==========================================================' );

	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 2 );
} );

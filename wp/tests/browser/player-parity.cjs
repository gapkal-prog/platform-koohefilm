/*
 * پاریتی «صفحه‌ی پخش» با مرجع cinora (`cinora/player.html`).
 *
 * دو سناریو:
 *   ۱) سریال: پلیر + بخش «قسمت‌های فصل» + بخش پیشنهاد
 *   ۲) فیلم: پلیر بدون بخش قسمت‌ها
 *
 * سنجش‌ها:
 *   • هندسه و سبک اجزای پلیر (نوار بالا، تیتر، نشان‌ها، قاب ویدئو، کنترل‌ها،
 *     یادداشت، نوار قسمت‌ها) بین مرجع و وردپرس
 *   • رفتار واقعی: کلیک روی «پخش» در صفحه‌ی سریال → باز شدن همین صفحه،
 *     جابه‌جایی کیفیت، حالت سینما، لیست تماشا، پیمایش قسمت‌ها، پیوند بازگشت
 *
 * اجرا (از همین پوشه):
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node player-parity.cjs
 */
const { chromium } = require( 'playwright' );

let pass = 0, fail = 0;
const failures = [];

function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
		return;
	}

	fail++;
	failures.push( label );
	console.log( '  ✗ ' + label + ( detail ? '   → ' + detail : '' ) );
}

function near( a, b, tol = 1 ) {
	return Math.abs( Number( a ) - Number( b ) ) <= tol;
}

/*
 * سنجه‌ی مشترک صفحه‌ی پخش — با نام‌کلاس‌های خودِ مرجع، چون هدف همین
 * هم‌نامی است: `.player-page`, `.player-top`, `.player-title`, `.player-badges`,
 * `.video-frame`, `.player-controls`, `.demo-notice`, `.player-episodes`,
 * `.episode-pills`, `.episode-navigation`.
 */
function measure( sel ) {
	const q = ( root, s ) => ( root ? root.querySelector( s ) : null );
	const cs = ( el, props ) => {
		if ( ! el ) { return null; }
		const c = getComputedStyle( el );
		const o = {};
		props.forEach( ( p ) => { o[ p ] = c[ p ]; } );
		return o;
	};
	const box = ( el ) => ( el
		? [ Math.round( el.getBoundingClientRect().width ), Math.round( el.getBoundingClientRect().height ) ]
		: null );

	const root = q( document, sel.root );
	if ( ! root ) { return { missing: true }; }

	/*
	 * ظرف صفحه: در مرجع خودِ `.player-page` ظرف است (`padding-inline: 56px`
	 * با `max-width: 1440px`)، ولی در وردپرس قالب، ظرف والدِ آن است.
	 * پس مقدار مؤثر سنجیده می‌شود: نخستین نیا/خودی که پدینگ افقی دارد،
	 * به‌همراه جعبه‌ی محتوا (پهنا و شروع محتوا). این، تفاوت ساختاری
	 * دو پیاده‌سازی را به یک عدد قابل‌قیاس تبدیل می‌کند.
	 */
	const container = ( () => {
		let el = root;
		while ( el && getComputedStyle( el ).paddingInlineStart === '0px' ) {
			el = el.parentElement;
		}
		return el || root;
	} )();
	const rootCs = getComputedStyle( root );
	const rootRect = root.getBoundingClientRect();
	const content = [
		Math.round( rootRect.width - parseFloat( rootCs.paddingInlineStart ) - parseFloat( rootCs.paddingInlineEnd ) ),
		Math.round( rootRect.x + parseFloat( rootCs.paddingInlineStart ) ),
	];

	const top = q( root, '.player-top' );
	const title = q( root, '.player-title' );
	const badges = q( root, '.player-badges' );
	const frame = q( root, '.video-frame' );
	const controls = q( root, '.player-controls' );
	const notice = q( root, '.demo-notice' );
	const eps = q( root, sel.episodes );
	const nav = eps ? q( eps, '.episode-navigation' ) : null;
	const pills = eps ? q( eps, '.episode-pills' ) : null;

	const out = {
		root: cs( root, [ 'display', 'marginInline', 'paddingTop' ] ),
		containerPad: ( getComputedStyle( container ).paddingInlineStart + '/' + getComputedStyle( container ).paddingInlineEnd ),
		content: content,
		rootBox: box( root ),
		top: cs( top, [ 'display', 'alignItems', 'justifyContent', 'gap', 'paddingBottom', 'borderBottomWidth', 'fontSize', 'color' ] ),
		topLink: cs( q( top, 'a' ), [ 'fontSize', 'color', 'fontWeight' ] ),
		liveDot: cs( q( top, '.live-dot' ), [ 'width', 'height', 'borderRadius', 'backgroundColor', 'display' ] ),
		title: cs( title, [ 'display', 'alignItems', 'justifyContent', 'gap', 'paddingTop', 'paddingBottom', 'borderBottomWidth', 'marginTop' ] ),
		eyebrow: cs( q( title, '.eyebrow' ), [ 'fontSize', 'fontWeight', 'letterSpacing', 'color', 'textTransform', 'marginBottom' ] ),
		h1: cs( q( title, 'h1' ), [ 'fontSize', 'fontWeight', 'lineHeight', 'color' ] ),
		original: cs( q( title, 'p:not(.eyebrow)' ), [ 'fontSize', 'color', 'marginTop' ] ),
		badges: cs( badges, [ 'display', 'alignItems', 'gap', 'marginTop', 'flexWrap' ] ),
		badge: cs( badges ? badges.querySelector( 'span' ) : null, [ 'fontSize', 'padding', 'borderRadius', 'backgroundColor', 'color', 'borderWidth', 'borderStyle', 'lineHeight' ] ),
		frame: cs( frame, [ 'borderRadius', 'overflow', 'backgroundColor', 'borderWidth', 'marginTop', 'position' ] ),
		frameBox: box( frame ),
		video: box( q( frame, 'video' ) ),
		videoCs: cs( q( frame, 'video' ), [ 'width', 'height', 'display', 'backgroundColor' ] ),
		controls: cs( controls, [ 'display', 'alignItems', 'gap', 'padding', 'marginTop', 'borderWidth', 'borderRadius', 'backgroundColor', 'flexWrap' ] ),
		/*
		 * `fontFamily` سنجیده نمی‌شود: رشته‌ی فهرست قلم‌ها در دو پروژه
		 * متفاوت است ولی نخستین قلم هر دو Vazirmatn است (رندر یکسان).
		 */
		select: cs( q( controls, 'select' ), [ 'fontSize', 'padding', 'borderRadius', 'backgroundColor', 'color', 'borderWidth', 'borderColor' ] ),
		label: cs( q( controls, 'label' ), [ 'fontSize', 'color', 'display', 'alignItems', 'gap' ] ),
		notice: cs( notice, [ 'display', 'alignItems', 'gap', 'padding', 'borderRadius', 'fontSize', 'color', 'marginTop', 'borderWidth', 'backgroundColor' ] ),
		noticeGlyph: null,
		eps: cs( eps, [ 'marginTop', 'padding', 'borderWidth', 'borderRadius', 'backgroundColor' ] ),
		epsHeading: cs( eps ? q( eps, '.section-heading' ) : null, [ 'display', 'alignItems', 'justifyContent', 'gap', 'marginBottom', 'flexWrap' ] ),
		epsTitle: cs( eps ? q( eps, '.heading-title h2' ) : null, [ 'fontSize', 'fontWeight', 'color' ] ),
		epsIcon: cs( eps ? q( eps, '.heading-title .section-icon' ) : null, [ 'fontSize', 'color', 'display' ] ),
		epsIconBox: box( eps ? q( eps, '.heading-title .section-icon' ) : null ),
		nav: cs( nav, [ 'display', 'alignItems', 'gap', 'flexWrap' ] ),
		pills: cs( pills, [ 'display', 'gap', 'flexWrap', 'marginTop' ] ),
	};

	out.noticeGlyph = notice ? ( notice.textContent.trim().charAt( 0 ) || '' ) : '';

	/* دکمه‌های کنترل: «حالت سینما», «تمام‌صفحه», «لیست تماشا», «دانلود نمونه». */
	const ctrlButtons = controls
		? Array.from( controls.querySelectorAll( sel.ctrlBtn ) )
		: [];
	out.ctrlCount = ctrlButtons.length;
	out.ctrl = ctrlButtons.map( ( b ) => ( {
		text: b.textContent.trim().replace( /\s+/g, ' ' ),
		svg: !! b.querySelector( 'svg' ),
		style: cs( b, [ 'minHeight', 'padding', 'borderRadius', 'fontSize', 'fontWeight', 'color', 'backgroundColor', 'borderColor', 'gap' ] ),
		height: box( b ) ? box( b )[ 1 ] : null,
	} ) );

	/* پیوندهای پیمایش قسمت‌ها. */
	out.navLinks = nav
		? Array.from( nav.querySelectorAll( 'a' ) ).map( ( a ) => ( {
			text: a.textContent.trim().replace( /\s+/g, ' ' ),
			style: cs( a, [ 'minHeight', 'padding', 'borderRadius', 'fontSize', 'color', 'backgroundColor', 'borderColor' ] ),
			height: box( a ) ? box( a )[ 1 ] : null,
		} ) )
		: [];

	/* قرص‌های قسمت + حالت فعال. */
	out.pillCount = pills ? pills.querySelectorAll( 'a' ) .length : 0;
	const pill = pills ? pills.querySelector( 'a' ) : null;
	const active = pills ? pills.querySelector( 'a.active, a[aria-current="true"]' ) : null;
	out.pill = cs( pill, [ 'fontSize', 'padding', 'borderRadius', 'color', 'backgroundColor', 'borderWidth', 'borderColor' ] );
	out.pillActive = cs( active, [ 'fontSize', 'padding', 'borderRadius', 'color', 'backgroundColor', 'borderWidth', 'borderColor' ] );
	out.pillActiveText = active ? active.textContent.trim().replace( /\s+/g, ' ' ) : '';

	/* بخش پیشنهاد زیر پلیر. */
	const related = document.querySelector( sel.related );
	out.relatedHeading = cs( related ? related.querySelector( sel.relatedH2 ) : null, [ 'fontSize', 'fontWeight', 'color' ] );
	const more = related ? related.querySelector( sel.moreLink ) : null;
	out.relatedMore = cs( more, [ 'fontSize', 'color', 'display' ] );
	const grid = related ? related.querySelector( sel.grid ) : null;
	out.relatedCols = ( ( cs( grid, [ 'gridTemplateColumns' ] ) || {} ).gridTemplateColumns || '' ).split( ' ' ).filter( Boolean ).length;
	out.relatedItems = grid ? grid.children.length : 0;

	return out;
}

const SERIES = {
	ref: {
		url: 'http://localhost:8098/player.html?id=shogun&season=1&episode=1&quality=1080p',
		sel: {
			root: '.player-page', episodes: '.player-episodes', related: 'section.home-section',
			grid: '.media-grid', relatedH2: '.heading-title h2', moreLink: '.text-link',
			ctrlBtn: '.button.secondary',
		},
	},
	wp: {
		url: 'http://localhost:8099/watch/?manacore_id=8&season=1&episode=1&quality=1080p',
		sel: {
			root: '.player-page', episodes: '.player-episodes', related: '.manacore-titles-block',
			grid: '.manacore-grid-cards', relatedH2: '.manacore-section-title', moreLink: '.manacore-more-link',
			ctrlBtn: '.manacore-btn.is-secondary',
		},
	},
};

const MOVIE = {
	ref: {
		url: 'http://localhost:8098/player.html?id=dune&quality=1080p',
		sel: {
			root: '.player-page', episodes: '.player-episodes', related: 'section.home-section',
			grid: '.media-grid', relatedH2: '.heading-title h2', moreLink: '.text-link',
			ctrlBtn: '.button.secondary',
		},
	},
	wp: {
		url: 'http://localhost:8099/watch/?manacore_id=6&quality=1080p',
		sel: {
			root: '.player-page', episodes: '.player-episodes', related: '.manacore-titles-block',
			grid: '.manacore-grid-cards', relatedH2: '.manacore-section-title', moreLink: '.manacore-more-link',
			ctrlBtn: '.manacore-btn.is-secondary',
		},
	},
};

/* کلیدهایی که به‌جای برابری رشته‌ای جداگانه سنجیده می‌شوند. */
const NON_TEXT_KEYS = [
	'ctrlCount', 'ctrl', 'navLinks', 'pillCount', 'pill', 'pillActive', 'pillActiveText',
	'relatedCols', 'relatedItems', 'relatedMore', 'frameBox', 'video', 'epsIconBox', 'rootBox',
	'noticeGlyph', 'content',
];

async function loadRef( page, url ) {
	await page.goto( url, { waitUntil: 'load' } );
	/* مرجع کل صفحه را با جاوااسکریپت می‌سازد؛ تا آماده‌شدن قرص‌ها صبر می‌کنیم. */
	await page.waitForFunction( () => !! document.querySelector( '.player-page .player-controls' ) );
	await page.waitForTimeout( 400 );
}

async function compare( ctx, label, cfg ) {
	console.log( '\n── ' + label + ' ──' );
	const refPage = await ctx.newPage();
	await loadRef( refPage, cfg.ref.url );
	const wpPage = await ctx.newPage();
	await wpPage.goto( cfg.wp.url, { waitUntil: 'load' } );
	await wpPage.waitForTimeout( 700 );

	const ref = await refPage.evaluate( measure, cfg.ref.sel );
	const wp = await wpPage.evaluate( measure, cfg.wp.sel );

	if ( ref.missing || wp.missing ) {
		check( false, label + ': صفحه‌ی پخش رندر شد', JSON.stringify( { refMissing: !! ref.missing, wpMissing: !! wp.missing } ) );
		return { refPage, wpPage };
	}

	Object.keys( ref )
		.filter( ( k ) => ! NON_TEXT_KEYS.includes( k ) )
		.forEach( ( key ) => {
			const r = JSON.stringify( ref[ key ] );
			const w = JSON.stringify( wp[ key ] );
			check( r === w, label + ' / ' + key, r === w ? '' : 'REF ' + r + '  WP ' + w );
		} );

	/* جعبه‌ی محتوای ظرف: پهنا و شروع محتوا باید یکی باشد (رواداری ۱px). */
	check(
		near( ref.content[ 0 ], wp.content[ 0 ], 1 ) && near( ref.content[ 1 ], wp.content[ 1 ], 1 ),
		label + ' / جعبه‌ی محتوای صفحه',
		'REF ' + ref.content + '  WP ' + wp.content
	);
	check(
		ref.containerPad === wp.containerPad,
		label + ' / پدینگ افقی ظرف',
		ref.containerPad === wp.containerPad ? '' : 'REF ' + ref.containerPad + '  WP ' + wp.containerPad
	);

	/*
	 * جعبه‌ها با رواداری گِردکردن گِرد می‌شوند. `rootBox` سنجیده نمی‌شود
	 * چون در مرجع خودِ ریشه ظرف است و در وردپرس ظرف والد؛ همان تفاوت
	 * با «جعبه‌ی محتوای صفحه» + «پدینگ افقی ظرف» سنجیده شده است.
	 */
	[ 'frameBox', 'video', 'epsIconBox' ].forEach( ( key ) => {
		const r = ref[ key ], w = wp[ key ];
		if ( ! r || ! w ) {
			check( !! r === !! w, label + ' / ' + key, 'REF ' + JSON.stringify( r ) + '  WP ' + JSON.stringify( w ) );
			return;
		}
		const ok = near( r[ 0 ], w[ 0 ], 2 ) && near( r[ 1 ], w[ 1 ], 2 );
		check( ok, label + ' / ' + key, ok ? '' : 'REF ' + r + '  WP ' + w );
	} );

	/* کنترل‌ها: تعداد، متن و سبک. */
	check( ref.ctrlCount === wp.ctrlCount, label + ' / تعداد دکمه‌های کنترل', 'REF ' + ref.ctrlCount + '  WP ' + wp.ctrlCount );
	const ctrlCount = Math.min( ref.ctrlCount, wp.ctrlCount );
	for ( let i = 0; i < ctrlCount; i++ ) {
		const r = ref.ctrl[ i ], w = wp.ctrl[ i ];
		check(
			r.text === w.text,
			label + ' / کنترل ' + ( i + 1 ) + ' متن',
			r.text === w.text ? '' : 'REF «' + r.text + '»  WP «' + w.text + '»'
		);
		check(
			JSON.stringify( r.style ) === JSON.stringify( w.style ),
			label + ' / کنترل ' + ( i + 1 ) + ' سبک',
			JSON.stringify( r.style ) === JSON.stringify( w.style ) ? '' : 'REF ' + JSON.stringify( r.style ) + '  WP ' + JSON.stringify( w.style )
		);
		check(
			near( r.height, w.height, 2 ),
			label + ' / کنترل ' + ( i + 1 ) + ' ارتفاع',
			near( r.height, w.height, 2 ) ? '' : 'REF ' + r.height + '  WP ' + w.height
		);
	}

	/* پیمایش قسمت‌ها: تعداد و متن. */
	if ( ref.navLinks.length || wp.navLinks.length ) {
		check(
			ref.navLinks.length === wp.navLinks.length,
			label + ' / تعداد پیوندهای پیمایش قسمت',
			'REF ' + ref.navLinks.length + '  WP ' + wp.navLinks.length
		);
		check(
			JSON.stringify( ref.navLinks.map( ( l ) => l.text ) ) === JSON.stringify( wp.navLinks.map( ( l ) => l.text ) ),
			label + ' / متن پیوندهای پیمایش',
			'REF ' + JSON.stringify( ref.navLinks.map( ( l ) => l.text ) ) + '  WP ' + JSON.stringify( wp.navLinks.map( ( l ) => l.text ) )
		);
		const n = Math.min( ref.navLinks.length, wp.navLinks.length );
		if ( n ) {
			check(
				JSON.stringify( ref.navLinks[ 0 ].style ) === JSON.stringify( wp.navLinks[ 0 ].style ),
				label + ' / سبک پیوند پیمایش',
				JSON.stringify( ref.navLinks[ 0 ].style ) === JSON.stringify( wp.navLinks[ 0 ].style ) ? '' : 'REF ' + JSON.stringify( ref.navLinks[ 0 ].style ) + '  WP ' + JSON.stringify( wp.navLinks[ 0 ].style )
			);
		}
	} else {
		console.log( '  – فیلم است؛ پیمایش قسمتی وجود ندارد (مرجع هم ندارد)' );
	}

	/*
	 * قرص‌های قسمت: تعداد در دو سایت به داده بسته است (مرجع ۱۰ قسمت شوگان
	 * را دارد، داده‌ی آزمون ما ۴ قسمت). پس قالب سنجیده می‌شود: ارقام
	 * فارسی، پیشوند «▶» و شماره‌ی قرص فعال.
	 */
	if ( wp.pillCount ) {
		check( ref.pillCount > 0 && wp.pillCount > 0, label + ' / قرص‌های قسمت رندر شده‌اند', 'REF ' + ref.pillCount + '  WP ' + wp.pillCount );
		check( ! /\d/.test( wp.pillActiveText ) && ! /\d/.test( ref.pillActiveText ), label + ' / قرص‌ها با ارقام فارسی‌اند', 'REF «' + ref.pillActiveText + '»  WP «' + wp.pillActiveText + '»' );
		check(
			ref.pillActiveText.replace( /[۰-۹]/g, '#' ) === wp.pillActiveText.replace( /[۰-۹۰-۹]/g, '#' ),
			label + ' / قالب متن قرص فعال',
			'REF «' + ref.pillActiveText + '»  WP «' + wp.pillActiveText + '»'
		);
		check( !! wp.pillActive, label + ' / قرص فعال مشخص است' );
	}

	/* بخش پیشنهاد. */
	check( ref.relatedCols === wp.relatedCols, label + ' / ستون‌های شبکه‌ی پیشنهاد', 'REF ' + ref.relatedCols + '  WP ' + wp.relatedCols );
	check(
		wp.relatedItems > 0 && wp.relatedItems <= ref.relatedItems,
		label + ' / تعداد کارت پیشنهاد (تا سقف مرجع)',
		'REF ' + ref.relatedItems + '  WP ' + wp.relatedItems
	);
	check(
		JSON.stringify( ref.relatedMore ) === JSON.stringify( wp.relatedMore ),
		label + ' / پیوند «مشاهده همه»',
		JSON.stringify( ref.relatedMore ) === JSON.stringify( wp.relatedMore ) ? '' : 'REF ' + JSON.stringify( ref.relatedMore ) + '  WP ' + JSON.stringify( wp.relatedMore )
	);

	/* یادداشت آزمایشی: نشانه‌ی متنی مثل مرجع. */
	if ( ref.notice ) {
		check( ref.noticeGlyph === wp.noticeGlyph, label + ' / نشانه‌ی یادداشت', 'REF «' + ref.noticeGlyph + '»  WP «' + wp.noticeGlyph + '»' );
	}

	return { refPage, wpPage };
}

/**
 * شناسه‌ی اثر را از خودِ سایت می‌خواند (از پیوند «پخش» صفحه‌ی جزئیات).
 *
 * چرا: شناسه‌ی نوشته‌ها به ترتیب نصب محیط QA بستگی دارد؛ آزمون با شناسه‌ی
 * ثابت پس از هر `provision.sh --force` می‌شکند، در حالی که محصول سالم است.
 * این‌جا همان مقصدی خوانده می‌شود که کاربر واقعی با کلیک «پخش» می‌گیرد.
 *
 * @param {import('playwright').Page} page صفحه.
 * @param {string}                    path مسیر صفحه‌ی جزئیات.
 * @return {Promise<string>} نشانی پایه‌ی صفحه‌ی پخش.
 */
async function playerBase( page, path ) {
	await page.goto( 'http://localhost:8099' + path, { waitUntil: 'domcontentloaded' } );
	const href = await page.evaluate( () => {
		const link = document.querySelector( 'a[href*="manacore_id="]' );
		return link ? link.getAttribute( 'href' ).replace( /&#0?38;/g, '&' ) : '';
	} );
	const id = ( href.match( /manacore_id=(\d+)/ ) || [] )[ 1 ] || '';

	return id ? href.split( '?' )[ 0 ] + '?manacore_id=' + id : '';
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	/*
	 * نشانی‌های آزمون از خودِ سایت خوانده می‌شوند تا آزمون به شناسه‌های
	 * یک نصب خاص گره نخورد (پس از `provision.sh --force` هم سبز بماند).
	 */
	const probe = await browser.newPage();
	const seriesBase = await playerBase( probe, '/series/shogun/' );
	const movieBase  = await playerBase( probe, '/movie/inception/' );
	await probe.close();

	check( !! seriesBase, 'نشانی پخش سریال از پیوند «پخش» صفحه‌ی سریال خوانده شد', seriesBase );
	check( !! movieBase, 'نشانی پخش فیلم از پیوند «پخش» صفحه‌ی فیلم خوانده شد', movieBase );

	if ( seriesBase ) {
		SERIES.wp.url = seriesBase + '&season=1&episode=1&quality=1080p';
	}
	if ( movieBase ) {
		MOVIE.wp.url = movieBase + '&quality=1080p';
	}

	for ( const width of [ 1440, 980, 390 ] ) {
		const ctx = await browser.newContext( { viewport: { width, height: 1200 }, locale: 'fa-IR' } );
		console.log( '\n══════ عرض ' + width + 'px ══════' );

		const series = await compare( ctx, 'سریال', SERIES );

		/* ── رفتار: از صفحه‌ی سریال تا پخش و کیفیت و سینما ── */
		if ( ! series.wpPage ) {
			console.log( '  – صفحه‌ی وردپرس ساخته نشد؛ سنجش رفتار انجام نشد' );
			await ctx.close();
			continue;
		}

		const seriesPage = await ctx.newPage();
		await seriesPage.goto( 'http://localhost:8099/series/shogun/', { waitUntil: 'load' } );
		await seriesPage.waitForTimeout( 400 );

		const playHref = await seriesPage.evaluate( () => {
			const wrap = document.querySelector( '[data-manacore-episodes]' );
			if ( wrap ) {
				wrap.querySelectorAll( '[hidden]' ).forEach( ( el ) => el.removeAttribute( 'hidden' ) );
			}
			const a = document.querySelector( '.download-section .episode-card .download-row a.manacore-btn.is-secondary' )
				|| document.querySelector( '.download-section .download-row a.manacore-btn.is-secondary' );
			return a ? { href: a.getAttribute( 'href' ), text: a.textContent.trim() } : null;
		} );
		check( !! playHref, 'دکمه‌ی «پخش» در جدول دانلود وجود دارد' );
		if ( playHref ) {
			check( /manacore_id=\d+/.test( playHref.href ), 'پیوند پخش شناسه‌ی اثر را دارد', playHref.href );
			check( /season=\d+/.test( playHref.href ) && /episode=\d+/.test( playHref.href ), 'پیوند پخش فصل و قسمت را دارد', playHref.href );
			/* `\b` با حروف فارسی کار نمی‌کند؛ مقایسه‌ی متنی ساده درست است. */
			check( playHref.text.includes( 'پخش' ), 'برچسب دکمه «پخش» است', playHref.text );
		}

		const wpPage = series.wpPage;

		/* کلیک روی «پخش» و رسیدن به صفحه‌ی پخش. */
		await Promise.all( [
			seriesPage.waitForNavigation( { waitUntil: 'load' } ),
			seriesPage.click( '.download-section .episode-card .download-row a.manacore-btn.is-secondary, .download-section .download-row a.manacore-btn.is-secondary' ),
		] );
		await seriesPage.waitForTimeout( 500 );
		check( seriesPage.url().includes( '/watch/' ), 'کلیک «پخش» به صفحه‌ی پخش می‌رود', seriesPage.url() );
		check( await seriesPage.evaluate( () => !! document.querySelector( '.player-page' ) ), 'صفحه‌ی پخش با همین صفحه‌ی قالب ساخته می‌شود' );

		/* کیفیت: تغییر مقدار ← تغییر منبع و نشان کیفیت. */
		const before = await wpPage.evaluate( () => ( {
			badge: ( document.querySelector( '.player-badges > span:last-child' ) || {} ).textContent,
			src: ( document.querySelector( 'video[data-player-video], .video-frame video' ) || {} ).src,
			dl: ( document.querySelector( '.player-controls a.manacore-btn.is-primary' ) || {} ).getAttribute ? document.querySelector( '.player-controls a.manacore-btn.is-primary' ).getAttribute( 'href' ) : null,
		} ) );
		await wpPage.selectOption( '.player-controls select', '360p' ).catch( () => {} );
		await wpPage.waitForTimeout( 300 );
		const after = await wpPage.evaluate( () => {
			const select = document.querySelector( '.player-controls select' );
			const option = select.options[ select.selectedIndex ];
			const link = document.querySelector( '.player-controls a.manacore-btn.is-primary' );
			return {
				badge: ( document.querySelector( '.player-badges > span:last-child' ) || {} ).textContent,
				src: ( document.querySelector( 'video[data-player-video], .video-frame video' ) || {} ).src,
				dl: link ? link.getAttribute( 'href' ) : null,
				optionDownload: option ? option.getAttribute( 'data-download' ) : null,
				optionStream: option ? option.getAttribute( 'data-src' ) : null,
			};
		} );
		check( before.badge !== after.badge && /360/.test( after.badge || '' ), 'تغییر کیفیت، نشان کیفیت را به‌روز می‌کند', JSON.stringify( { before: before.badge, after: after.badge } ) );
		check( before.src !== after.src, 'تغییر کیفیت، منبع ویدئو را عوض می‌کند', JSON.stringify( { before: before.src, after: after.src } ) );
		/*
		 * پیوند دانلود: در داده‌ی آزمون، فایل دانلود هر سه کیفیت یکی است؛
		 * پس برابری رشته‌ای «تغییر» را اثبات نمی‌کند. به‌جایش هم‌گامی با
		 * `data-download` گزینه سنجیده می‌شود و — مهم‌تر — اینکه پیوند به
		 * نشانی *پخش* (`data-src`) نیفتاده باشد، چون آن دو در این داده
		 * متفاوت‌اند و اشتباهِ گرفتن یکی به‌جای دیگری گرفته می‌شود.
		 */
		check(
			!! after.dl && !! after.optionDownload && after.dl === after.optionDownload,
			'پیوند دانلود با کیفیت گزینش‌شده هم‌گام است',
			JSON.stringify( { dl: after.dl, optionDownload: after.optionDownload } )
		);
		check(
			!! after.dl && after.dl !== after.optionStream,
			'پیوند دانلود، فایل دانلود را می‌دهد نه نشانی پخش',
			JSON.stringify( { dl: after.dl, optionStream: after.optionStream } )
		);

		/*
		 * حالت سینما — عیناً رفتار مرجع: کلاس `cinema-mode` روی ریشه‌ی
		 * صفحه و قاب ویدئو با `aspect-ratio: 2.1` / `max-height: 760px`.
		 */
		const refCinema = await series.refPage.evaluate( async () => {
			document.querySelector( '#cinema' ).click();
			const frame = document.querySelector( '.video-frame' );
			const c = getComputedStyle( frame );
			return {
				rootClass: document.querySelector( '.player-page' ).className,
				ratio: c.aspectRatio,
				maxHeight: c.maxHeight,
			};
		} );
		await wpPage.click( '[data-player-cinema]' );
		await wpPage.waitForTimeout( 250 );
		const cinemaOn = await wpPage.evaluate( () => {
			const root = document.querySelector( '.player-page' );
			const frame = document.querySelector( '.video-frame' );
			const c = getComputedStyle( frame );
			return {
				pressed: document.querySelector( '[data-player-cinema]' ).getAttribute( 'aria-pressed' ),
				rootHasClass: root.classList.contains( 'cinema-mode' ),
				ratio: c.aspectRatio,
				maxHeight: c.maxHeight,
			};
		} );
		check( 'true' === cinemaOn.pressed, 'دکمه‌ی سینما وضعیت فشرده را نشان می‌دهد', JSON.stringify( cinemaOn ) );
		check( cinemaOn.rootHasClass && /cinema-mode/.test( refCinema.rootClass ), 'حالت سینما کلاس را روی ریشه‌ی صفحه می‌گذارد', JSON.stringify( { ref: refCinema.rootClass, wp: cinemaOn.rootHasClass } ) );
		check(
			cinemaOn.ratio === refCinema.ratio && cinemaOn.maxHeight === refCinema.maxHeight,
			'حالت سینما قاب ویدئو را مثل مرجع پهن می‌کند',
			'REF ' + JSON.stringify( refCinema ) + '  WP ' + JSON.stringify( cinemaOn )
		);
		await wpPage.keyboard.press( 'Escape' );
		await wpPage.waitForTimeout( 200 );
		check(
			'false' === await wpPage.evaluate( () => document.querySelector( '[data-player-cinema]' ).getAttribute( 'aria-pressed' ) ),
			'Escape از حالت سینما بیرون می‌آید'
		);

		/* پیمایش قسمت با قرص‌ها. */
		const pills = await wpPage.$$( '.episode-pills a' );
		check( pills.length > 0, 'قرص قسمت‌ها در صفحه‌ی پخش هست' );
		if ( pills.length > 1 ) {
			await Promise.all( [ wpPage.waitForNavigation( { waitUntil: 'load' } ), pills[ 1 ].click() ] );
			await wpPage.waitForTimeout( 400 );
			check( /episode=2/.test( wpPage.url() ), 'کلیک قرص دوم به قسمت ۲ می‌رود', wpPage.url() );
			check(
				await wpPage.evaluate( () => !! document.querySelector( '.episode-pills a.active, .episode-pills a[aria-current="true"]' ) ),
				'قرص فعال روی قسمت جاری نشانه‌گذاری شده است'
			);
		}

		/* پیوند بازگشت. */
		const back = await wpPage.evaluate( () => {
			const a = document.querySelector( '.player-top a' );
			return a ? a.getAttribute( 'href' ) : null;
		} );
		check( !! back && back.includes( '/series/' ), 'پیوند «بازگشت به جزئیات» به صفحه‌ی سریال می‌رود', String( back ) );

		await compare( ctx, 'فیلم', MOVIE );

		await ctx.close();
	}

	console.log( '\n' + pass + ' / ' + fail );
	if ( failures.length ) {
		console.log( 'شکست‌ها:\n - ' + failures.join( '\n - ' ) );
	}

	await browser.close();
	process.exit( fail ? 1 : 0 );
} )();

/*
 * پاریتی ناحیه‌ی دانلود با مرجع cinora — دو سناریو:
 *   ۱) سریال: بخش «فصل‌ها و قسمت‌ها» با کارت‌های قسمت و جدول کیفیت هر قسمت
 *   ۲) فیلم: بخش «بهترین کیفیت را انتخاب کن.» با سه ردیف کیفیت
 *
 * سنجش: هندسه‌ی سبک‌ها (اندازه‌ی قلم، پدینگ، شعاع، رنگ، شبکه‌ی ستون‌ها،
 * ارتفاع جعبه‌ها) در حالت visible — چون مرجع کارت بازشده را می‌سازد و
 * قالب ما همه را سمت سرور می‌سازد و بقیه را `hidden` می‌کند.
 *
 * اجرا (از همین پوشه):
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node download-parity.cjs
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

/* نزدیک‌بودن با رواداری پیکسلیِ گِردکردن چیدمان. */
function near( a, b, tol = 1 ) {
	return Math.abs( Number( a ) - Number( b ) ) <= tol;
}

/*
 * سنجه‌ی مشترک: ساختار و سبک بخش دانلود.
 * فقط ردیف‌های visible و کارت باز شده سنجیده می‌شوند تا «محتوای پنهان»
 * مقایسه را خراب نکند.
 */
function measure( sel ) {
	const q = ( root, s ) => root.querySelector( s );
	const cs = ( el, props ) => {
		if ( ! el ) { return null; }
		const c = getComputedStyle( el );
		const o = {};
		props.forEach( ( p ) => { o[ p ] = c[ p ]; } );
		return o;
	};
	const h = ( el ) => ( el ? Math.round( el.getBoundingClientRect().height ) : null );
	const w = ( el ) => ( el ? Math.round( el.getBoundingClientRect().width ) : null );

	const section = q( document, sel.section );
	if ( ! section ) { return { missing: true }; }

	const card = section.querySelector( sel.card );
	const table = card ? ( card.matches( sel.table ) ? card : card.querySelector( sel.table ) ) : section.querySelector( sel.table );
	const row = table ? table.querySelector( sel.row ) : null;

	const out = {
		section: cs( section, [ 'marginTop', 'padding' ] ),
		heading: cs( q( section, sel.heading ), [ 'display', 'alignItems', 'gap', 'minHeight', 'marginBottom' ] ),
		title: cs( q( section, sel.title ), [ 'display', 'alignItems', 'gap' ] ),
		h2: cs( q( section, sel.h2 ), [ 'fontSize', 'fontWeight' ] ),
		/* line-height فقط رشته‌ی محاسبه‌شده است و پیکسل نمی‌سازد؛ جعبه جدا سنجیده می‌شود. */
		icon: cs( q( section, sel.icon ), [ 'color', 'fontSize', 'display' ] ),
		iconBox: [ w( q( section, sel.icon ) ), h( q( section, sel.icon ) ) ],
		notice: cs( q( section, sel.notice ), [ 'display', 'alignItems', 'gap', 'marginTop', 'padding', 'borderRadius', 'fontSize', 'color' ] ),
		tabs: cs( q( section, sel.tabsWrap ), [ 'display', 'gap', 'paddingBottom', 'overflowX' ] ),
		tab: cs( q( section, sel.tabsWrap + '>button' ), [ 'display', 'alignItems', 'gap', 'padding', 'borderRadius', 'fontSize', 'color', 'backgroundColor', 'borderColor' ] ),
		tabSmall: cs( q( section, sel.tabsWrap + '>button small' ), [ 'fontSize', 'opacity' ] ),
		tableBox: [ w( table ), h( table ) ],
		table: cs( table, [ 'padding', 'borderRadius', 'borderTopWidth', 'borderLeftWidth', 'backgroundColor' ] ),
		header: cs( q( table, sel.tableHeader ), [ 'display', 'gap', 'padding', 'fontSize', 'color', 'borderBottomWidth' ] ),
		headerCols: ( ( cs( q( table, sel.tableHeader ), [ 'gridTemplateColumns' ] ) || {} ).gridTemplateColumns || '' ).split( ' ' ).length,
		row: cs( row, [ 'display', 'gap', 'minHeight', 'fontSize', 'borderBottomWidth' ] ),
		rowCols: ( ( cs( row, [ 'gridTemplateColumns' ] ) || {} ).gridTemplateColumns || '' ).split( ' ' ).length,
		quality: cs( row ? row.querySelector( sel.quality ) : null, [ 'display', 'alignItems', 'gap' ] ),
		qualityB: cs( row ? row.querySelector( sel.qualityB ) : null, [ 'fontFamily', 'fontSize', 'fontWeight' ] ),
		qualitySmall: cs( row ? row.querySelector( sel.qualitySmall ) : null, [ 'fontSize', 'padding', 'borderRadius', 'borderWidth' ] ),
		format: cs( row ? row.querySelector( sel.format ) : null, [ 'fontFamily', 'fontSize', 'color' ] ),
		size: cs( row ? row.querySelector( sel.size ) : null, [ 'fontSize', 'color' ] ),
		actions: cs( row ? row.querySelector( sel.actions ) : null, [ 'display', 'justifyContent', 'gap' ] ),
	};

	const playBtn = row ? row.querySelector( sel.playBtn ) : null;
	const dlBtn = row ? row.querySelector( sel.dlBtn ) : null;
	[ [ 'playBtn', playBtn ], [ 'dlBtn', dlBtn ] ].forEach( ( pair ) => {
		const btn = pair[ 1 ];
		out[ pair[ 0 ] ] = btn
			? {
				svg: !! btn.querySelector( 'svg' ),
				text: btn.textContent.trim().replace( /\s+/g, ' ' ),
				sizes: cs( btn, [ 'minHeight', 'padding', 'borderRadius', 'fontSize', 'fontWeight', 'color', 'backgroundColor', 'borderColor', 'gap' ] ),
				box: [ w( btn ), h( btn ) ],
			}
			: null;
	} );

	if ( card ) {
		out.card = cs( card, [ 'borderRadius', 'backgroundColor', 'borderTopWidth', 'overflow' ] );
		out.cardH = h( card );
		out.cardBtn = cs( card.querySelector( sel.cardBtn ), [ 'display', 'alignItems', 'gap', 'padding', 'textAlign' ] );
		out.num = cs( card.querySelector( sel.number ), [ 'width', 'height', 'borderRadius', 'fontSize', 'color', 'display', 'backgroundColor' ] );
		out.numBox = [ w( card.querySelector( sel.number ) ), h( card.querySelector( sel.number ) ) ];
		out.strong = cs( card.querySelector( sel.strong ), [ 'fontSize', 'fontWeight' ] );
		out.small = cs( card.querySelector( sel.small ), [ 'display', 'fontSize', 'marginTop', 'color' ] );
		out.subtitle = cs( card.querySelector( sel.subtitle ), [ 'fontSize', 'color', 'display' ] );
		out.play = cs( card.querySelector( sel.play ), [ 'width', 'height', 'borderRadius', 'color', 'backgroundColor', 'display', 'margin' ] );
		out.playBox = [ w( card.querySelector( sel.play ) ), h( card.querySelector( sel.play ) ) ];
	}

	return out;
}

const SERIES_REF = {
	section: '.download-section', heading: '.section-heading', title: '.heading-title', h2: '.heading-title h2', icon: '.section-icon',
	notice: '.demo-notice', tabsWrap: '.season-tabs', card: '.episode-card', cardBtn: '.episode-heading>button',
	number: '.episode-number', strong: '.episode-heading strong', small: '.episode-heading small', subtitle: '.episode-subtitle', play: '.episode-play',
	table: '.episode-card .download-table', tableHeader: '.download-table-header', row: '.download-row',
	quality: '.quality-name', qualityB: '.quality-name>b', qualitySmall: '.quality-name>small', format: '.format-tag', size: '.download-size',
	actions: '.download-actions', playBtn: '.button.secondary', dlBtn: '.button.primary',
};
const SERIES_WP = Object.assign( {}, SERIES_REF, {
	playBtn: '.manacore-btn.is-secondary', dlBtn: '.manacore-btn.is-primary',
} );
const MOVIE_REF = {
	section: '.download-section', heading: '.section-heading', title: '.heading-title', h2: '.heading-title h2', icon: '.section-icon',
	notice: '.demo-notice', card: '.download-table', table: '.download-table', tableHeader: '.download-table-header', row: '.download-row',
	quality: '.quality-name', qualityB: '.quality-name>b', qualitySmall: '.quality-name>small', format: '.format-tag', size: '.download-size',
	actions: '.download-actions', playBtn: '.button.secondary', dlBtn: '.button.primary',
};
const MOVIE_WP = Object.assign( {}, MOVIE_REF, {
	playBtn: '.manacore-btn.is-secondary', dlBtn: '.manacore-btn.is-primary',
} );

async function compare( ctx, label, refUrl, wpUrl, refSel, wpSel ) {
	console.log( '\n── ' + label + ' ──' );
	const refPage = await ctx.newPage();
	await refPage.goto( refUrl, { waitUntil: 'load' } );
	/*
	 * صفحه‌ی مرجع ساختارش را با جاوااسکریپت می‌سازد؛ تا وقتی ستون «حجم» و
	 * ردیف کیفیت ساخته نشده، سنجش بی‌معناست (یک‌بار خواندن زودهنگام عدد
	 * کهنه داد).
	 */
	await refPage.waitForFunction( () => !! document.querySelector( '.download-section .download-size' ) && !! document.querySelector( '.download-row' ) );
	await refPage.waitForTimeout( 400 );
	const wpPage = await ctx.newPage();
	await wpPage.goto( wpUrl, { waitUntil: 'load' } );
	await wpPage.waitForTimeout( 700 );

	const ref = await refPage.evaluate( measure, refSel );
	const wp = await wpPage.evaluate( measure, wpSel );

	if ( ref.missing || wp.missing ) {
		check( false, label + ': بخش دانلود رندر شد', JSON.stringify( { refMissing: !! ref.missing, wpMissing: !! wp.missing } ) );
		return { refPage, wpPage };
	}

	const keys = Object.keys( ref ).filter( ( k ) => ! [ 'headerCols', 'rowCols', 'tableBox', 'iconBox', 'numBox', 'playBox', 'cardH', 'playBtn', 'dlBtn' ].includes( k ) );
	keys.forEach( ( key ) => {
		const r = JSON.stringify( ref[ key ] );
		const w = JSON.stringify( wp[ key ] );
		check( r === w, label + ' / ' + key, r === w ? '' : 'REF ' + r + '  WP ' + w );
	} );

	/* دکمه‌های ردیف: متن، آیکون و سبک. */
	[ 'playBtn', 'dlBtn' ].forEach( ( key ) => {
		const r = ref[ key ], w = wp[ key ];
		const sameText = r.text === w.text && r.svg === w.svg;
		const sameStyle = JSON.stringify( r.sizes ) === JSON.stringify( w.sizes );
		check( sameText, label + ' / ' + key + ' (متن و آیکون)' , 'REF ' + r.text + '/' + r.svg + '  WP ' + w.text + '/' + w.svg );
		check( sameStyle, label + ' / ' + key + ' (سبک)', sameStyle ? '' : 'REF ' + JSON.stringify( r.sizes ) + '  WP ' + JSON.stringify( w.sizes ) );
		check( near( r.box[ 1 ], w.box[ 1 ] ), label + ' / ' + key + ' (ارتفاع)', 'REF ' + r.box[ 1 ] + '  WP ' + w.box[ 1 ] );
	} );

	/* جعبه‌ها: پهنا با رواداری ۱px (گِردکردن ستون چیدمان). */
	[ 'tableBox', 'iconBox', 'numBox', 'playBox' ].forEach( ( key ) => {
		const r = ref[ key ], w = wp[ key ];
		/*
		 * پهنای جدول ممکن است ۱px از گِردکردن ستون چیدمان فاصله بگیرد؛
		 * ارتفاع هم چون متن کیفیت/فرمت در دو سایت یکی نیست، رواداری
		 * محتوایی می‌گیرد (سبک ردیف جداگانه سنجیده می‌شود).
		 */
		const ok = Math.abs( r[ 0 ] - w[ 0 ] ) <= 2 && Math.abs( r[ 1 ] - w[ 1 ] ) <= 8;
		check( ok, label + ' / ' + key, ok ? '' : 'REF ' + r + '  WP ' + w );
	} );

	check( ref.headerCols === wp.headerCols, label + ' / ستون‌های سرستون جدول', 'REF ' + ref.headerCols + '  WP ' + wp.headerCols );
	check( ref.rowCols === wp.rowCols, label + ' / ستون‌های ردیف جدول', 'REF ' + ref.rowCols + '  WP ' + wp.rowCols );

	return { refPage, wpPage };
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	for ( const width of [ 1440, 980 ] ) {
		const ctx = await browser.newContext( { viewport: { width, height: 1200 }, locale: 'fa-IR' } );
		console.log( '\n══════ عرض ' + width + 'px ══════' );

		const series = await compare(
			ctx,
			'سریال', 'http://localhost:8098/detail.html?id=shogun', 'http://localhost:8099/series/shogun/',
			SERIES_REF, SERIES_WP
		);

		/* رفتار: باز/بسته‌کردن کارت قسمت و جابه‌جایی فصل. */
		const wpSeries = series.wpPage;
		const behavior = await wpSeries.evaluate( () => {
			const wrap = document.querySelector( '[data-manacore-episodes]' );
			const cards = Array.from( wrap.querySelectorAll( '.episode-card' ) );
			const first = cards[ 0 ];
			const second = cards[ 1 ];
			const state = ( card ) => ( {
				expanded: card.classList.contains( 'expanded' ),
				tableHidden: card.querySelector( '.episode-download' ).hidden,
				aria: card.querySelector( '.episode-toggle' ).getAttribute( 'aria-expanded' ),
			} );

			return {
				before: state( first ),
				secondBefore: state( second ),
				tabs: wrap.querySelectorAll( '.season-tabs>button' ).length,
				panels: wrap.querySelectorAll( '[data-season-panel]' ).length,
				panelsVisible: Array.from( wrap.querySelectorAll( '[data-season-panel]' ) ).filter( ( p ) => ! p.hidden ).length,
			};
		} );

		check( behavior.before.expanded && ! behavior.before.tableHidden && 'true' === behavior.before.aria, 'کارت نخست از سمت سرور باز است', JSON.stringify( behavior.before ) );
		check( ! behavior.secondBefore.expanded && behavior.secondBefore.tableHidden, 'کارت دوم بسته است', JSON.stringify( behavior.secondBefore ) );
		check( behavior.panelsVisible >= 1, 'دست‌کم یک پنل فصل دیده می‌شود', String( behavior.panelsVisible ) );

		/* کلیک روی کارت دوم: جدولش باز می‌شود. */
		const secondToggle = wpSeries.locator( '.episode-card .episode-toggle' ).nth( 1 );
		await secondToggle.click();
		await wpSeries.waitForTimeout( 200 );
		const afterToggle = await wpSeries.evaluate( () => {
			const card = document.querySelectorAll( '.episode-card' )[ 1 ];
			return {
				expanded: card.classList.contains( 'expanded' ),
				tableHidden: card.querySelector( '.episode-download' ).hidden,
				aria: card.querySelector( '.episode-toggle' ).getAttribute( 'aria-expanded' ),
				rows: card.querySelectorAll( '.download-row' ).length,
			};
		} );
		check( afterToggle.expanded && ! afterToggle.tableHidden && 'true' === afterToggle.aria, 'کلیک کارت دوم آن را باز می‌کند', JSON.stringify( afterToggle ) );
		check( afterToggle.rows > 0, 'کارت بازشده ردیف‌های کیفیت دارد (' + afterToggle.rows + ')' );

		/* جابه‌جایی فصل با دکمه‌ی نوار فصل. */
		if ( behavior.tabs > 1 ) {
			await wpSeries.locator( '.season-tabs>button' ).nth( 1 ).click();
			await wpSeries.waitForTimeout( 200 );
			const afterTab = await wpSeries.evaluate( () => {
				const wrap = document.querySelector( '[data-manacore-episodes]' );
				const panels = Array.from( wrap.querySelectorAll( '[data-season-panel]' ) );
				const select = wrap.querySelector( '[data-season-select]' );
				return {
					visible: panels.filter( ( p ) => ! p.hidden ).map( ( p ) => p.getAttribute( 'data-season-panel' ) ),
					active: Array.from( wrap.querySelectorAll( '.season-tabs>button' ) ).filter( ( b ) => b.classList.contains( 'active' ) ).map( ( b ) => b.getAttribute( 'data-season' ) ),
					select: select ? select.value : null,
				};
			} );
			check( 1 === afterTab.visible.length && afterTab.visible[ 0 ] === afterTab.active[ 0 ], 'کلیک فصل، پنل همان فصل را نشان می‌دهد', JSON.stringify( afterTab ) );
			check( null === afterTab.select || afterTab.select === afterTab.active[ 0 ], 'گزینش فصل با نوار هم‌گام است', JSON.stringify( afterTab ) );
		} else {
			console.log( '  – تک‌فصل است؛ سنجش نوار فصل انجام نشد (مرجع هم تک‌فصل است)' );
		}

		await compare(
			ctx,
			'فیلم', 'http://localhost:8098/detail.html?id=dune', 'http://localhost:8099/movie/the-godfather/',
			MOVIE_REF, MOVIE_WP
		);

		await ctx.close();
	}

	console.log( '\n' + pass + ' / ' + fail );
	if ( failures.length ) {
		console.log( 'شکست‌ها:\n - ' + failures.join( '\n - ' ) );
	}

	await browser.close();
	process.exit( fail ? 1 : 0 );
} )();

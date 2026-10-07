/*
 * قرارداد اجرایی «تب‌های نوع» سرصفحه‌ی بخش در برابر مرجع سینورا.
 *
 * مرجع در بخش «این روزها، روی بورس» صفحه‌ی نخست یک ردیف تب دارد
 * (`.section-tabs`: همه / فیلم‌ها / سریال‌ها) که با `cinora/assets/js/home.js`
 * شبکه را سمت کاربر پالایش می‌کند. محصول همین قابلیت را در بلوک «شبکه‌ی
 * آثار» با ویژگی `showTypeTabs` دارد و این آزمون سه چیز را می‌سنجد:
 *
 *   ۱) هندسه و استایل محاسبه‌شده‌ی تب‌ها، در چهار پله (۱۴۴۰/۹۸۰/۷۶۸/۳۹۰)
 *      در برابر مقدار واقعیِ همان عرض در مرجع.
 *   ۲) رفتار پالایش در مرورگر واقعی: کلیک «سریال‌ها» → تنها کارت‌های سریال،
 *      کلیک «فیلم‌ها» → تنها فیلم‌ها، کلیک «همه» → بازگشت کامل.
 *   ۳) بدون خطای اجرای JS.
 *
 * اجرا:
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers \
 *     node wp/tests/browser/home-parity.cjs
 *
 * (نیازمند هر دو سرور: ۸۰۹۹ محصول، ۸۰۹۸ مرجع.)
 */
const { chromium } = require( 'playwright' );
const { seedAll, restoreAll } = require( './qa-seeds.cjs' );

const WP = 'http://localhost:8099/';
const REF = 'http://localhost:8098/index.html';
const WIDTHS = [ 1440, 980, 768, 390 ];

const WP_TABS = '.manacore-block-head .section-tabs';
const REF_TABS = '.section-heading .section-tabs';

let pass = 0;
let fail = 0;

function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + '  → ' + ( detail || '' ) );
	}
}

/* اندازه‌ها/استایل‌های محاسبه‌شده‌ی یک ردیف تب. */
function measure( page, sel ) {
	return page.evaluate( ( sel ) => {
		const box = document.querySelector( sel );

		if ( ! box ) {
			return null;
		}

		const first = box.querySelector( 'button' );
		const active = box.querySelector( 'button.active' );
		const cs = getComputedStyle( box );
		const bs = first ? getComputedStyle( first ) : null;
		const as = active ? getComputedStyle( active ) : null;
		const r = box.getBoundingClientRect();

		/*
		 * هر تب جداگانه هم سنجیده می‌شود. دلیلش داده است: شمار تب‌ها
		 * از نوع‌های موجود در کوئری می‌آید (اگر انیمه در ردیف باشد، تب
		 * «انیمه‌ها» هم هست) در حالی که مرجع فهرست تب‌هایش را در HTML
		 * نوشته است؛ پس مقایسه‌ی «عرض کل قاب» دو چیز ناهمسان را
		 * می‌سنجید. با پهنای تک‌تک تب‌ها و اسپنِ تب‌های مشترک، هندسه‌ی
		 * مرجع دقیقاً قفل می‌شود.
		 */
		const buttons = [ ...box.querySelectorAll( 'button' ) ].map( ( b ) => {
			const bb = b.getBoundingClientRect();

			return {
				label: b.textContent.trim(),
				datasetType: b.getAttribute( 'data-type' ) || '',
				width: Math.round( bb.width ),
				left: bb.left,
				right: bb.right,
			};
		} );

		return {
			w: Math.round( r.width ),
			buttons,
			h: Math.round( r.height ),
			gap: cs.gap,
			marginInlineStart: cs.marginInlineStart,
			display: cs.display,
			order: cs.order,
			btnPadding: bs ? bs.padding : null,
			btnRadius: bs ? bs.borderRadius : null,
			btnFont: bs ? bs.fontSize : null,
			btnWhiteSpace: bs ? bs.whiteSpace : null,
			activeColor: as ? as.color : null,
			activeBg: as ? as.backgroundColor : null,
			labels: [ ...box.querySelectorAll( 'button' ) ].map( ( b ) => b.textContent.trim() ),
		};
	}, sel );
}

/* وضعیت شبکه‌ی همان بلوکی که تب‌ها به آن تعلق دارند. */
function snapshot( page ) {
	return page.evaluate( () => {
		const block = document.querySelector( '.manacore-titles-block .section-tabs' );
		const root = block ? block.closest( '.manacore-titles-block' ) : null;

		if ( ! root ) {
			return null;
		}

		const cards = [ ...root.querySelectorAll( '.manacore-card' ) ];
		const visible = cards.filter( ( c ) => ! c.classList.contains( 'is-filtered-out' ) );

		return {
			total: cards.length,
			visible: visible.length,
			types: [ ...new Set( visible.map( ( c ) => c.getAttribute( 'data-manacore-type' ) ) ) ].sort(),
			pressed: [ ...block.querySelectorAll( 'button' ) ]
				.map( ( t ) => t.getAttribute( 'data-type' ) + ':' + t.getAttribute( 'aria-pressed' ) )
				.join( ' ' ),
		};
	} );
}

( async () => {
	/*
	 * پیش‌نیاز داده: این سنجه‌ها روی برگه‌ی بذرگرفته اجرا می‌شوند و ترتیب اجرا
	 * تضمین‌شده نیست (هر سوئیت در پایان داده‌ی خودش را برمی‌گرداند). یک بار در
	 * پیمایش w19a همین وابستگی به وضعیت داده، این دو سوئیت را سرخ کرد.
	 */
	console.log( '— کاشت داده‌ی آزمون —' );
	seedAll();

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	for ( const width of WIDTHS ) {
		const ctx = await browser.newContext( { viewport: { width, height: 1000 }, locale: 'fa-IR' } );
		const page = await ctx.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

		await page.goto( WP, { waitUntil: 'load' } );
		await page.waitForTimeout( 800 );
		const wp = await measure( page, WP_TABS );
		const wpRowTypes = await page.evaluate( () => {
			const block = document.querySelector( '.manacore-titles-block .section-tabs' );
			const root  = block ? block.closest( '.manacore-titles-block' ) : null;

			return root
				? [ ...new Set( [ ...root.querySelectorAll( '.manacore-card' ) ]
					.map( ( c ) => c.getAttribute( 'data-manacore-type' ) )
					.filter( Boolean ) ) ].sort()
				: [];
		} );

		await page.goto( REF, { waitUntil: 'load' } );
		await page.waitForTimeout( 900 );
		const ref = await measure( page, REF_TABS );

		if ( ! wp || ! ref ) {
			check( false, `@${ width }: ردیف تب در هر دو سمت پیدا شد`, JSON.stringify( { wp: !! wp, ref: !! ref } ) );
			await ctx.close();
			continue;
		}

		for ( const key of [ 'h', 'gap', 'marginInlineStart', 'display', 'order', 'btnPadding', 'btnRadius', 'btnFont', 'btnWhiteSpace', 'activeColor', 'activeBg' ] ) {
			check(
				wp[ key ] === ref[ key ],
				`@${ width } ${ key }: ${ wp[ key ] } (مرجع ${ ref[ key ] })`,
				`محصول «${ wp[ key ] }» در برابر مرجع «${ ref[ key ] }»`
			);
		}

		const sameSet = wp.labels.join( '|' ) === ref.labels.join( '|' );
		const refInWp  = ref.labels.every( ( l, i ) => wp.labels[ i ] === l );

		/*
		 * با مجموعه‌ی برابری تب‌ها، عرض قاب هم باید عدد‌به‌عدد یکی باشد؛
		 * با تب داده‌ایِ افزوده (نوعی که مرجع در ردیفش ندارد)، اسپنِ
		 * همان تب‌های مرجع سنجیده می‌شود — یعنی هندسه‌ی مرجع در عرض
		 * ما هم عیناً تکرار شده باشد.
		 */
		if ( sameSet ) {
			check( wp.w === ref.w, `@${ width } w: ${ wp.w } (مرجع ${ ref.w })`, `محصول «${ wp.w }» در برابر مرجع «${ ref.w }»` );
		} else {
			/*
			 * اسپن در چیدمان راست‌به‌چپ: نخستین تب سمت راست و آخرین تب سمت
			 * چپ است، پس فاصله از لبه‌ی راستِ تب اول تا لبه‌ی چپِ تب آخر
			 * گرفته می‌شود. پیش‌تر `last.right - first.left` بود که در RTL
			 * عدد منفی می‌داد (نقص خودِ آزمون، کشف‌شده در موج ۱۸ وقتی تب
			 * داده‌ای «انیمه‌ها» به ردیف اضافه شد و این شاخه فعال شد).
			 */
			const refFirst = ref.buttons[ 0 ];
			const refLast  = ref.buttons[ ref.labels.length - 1 ];
			const refSpan  = refFirst && refLast ? Math.round( refFirst.right - refLast.left ) : -1;
			const first    = wp.buttons[ 0 ];
			const last     = wp.buttons[ ref.labels.length - 1 ];
			const span     = first && last ? Math.round( first.right - last.left ) : -1;

			check(
				span === refSpan,
				`@${ width } اسپن تب‌های مرجع: ${ span } (مرجع ${ refSpan })`,
				`تب‌های مشترک «${ ref.labels.join( ' / ' ) }» در محصول ${ span }px اشغال می‌کنند`
			);
		}

		check(
			refInWp,
			`@${ width } برچسب تب‌ها: ${ wp.labels.join( ' / ' ) } (مرجع ${ ref.labels.join( ' / ' ) })`,
			'تب‌های مرجع باید با همان ترتیب و برچسب در محصول باشند'
		);

		/* پهنای هر تبِ مشترک با مرجع، تابه‌تا. */
		const refWidths = {};
		ref.buttons.forEach( ( b ) => { refWidths[ b.label ] = b.width; } );

		wp.buttons
			.filter( ( b ) => undefined !== refWidths[ b.label ] )
			.forEach( ( b ) => {
				check(
					b.width === refWidths[ b.label ],
					`@${ width } پهنای تب «${ b.label }»: ${ b.width } (مرجع ${ refWidths[ b.label ] })`,
					`محصول «${ b.width }» در برابر مرجع «${ refWidths[ b.label ] }»`
				);
			} );

		/*
		 * تب‌های افزوده باید از داده بیایند: به‌ازای هر نوع حاضر در
		 * کارت‌های همان ردیف، یک تب — و نه بیشتر.
		 */
		const wpTypes = wp.buttons.map( ( b ) => b.datasetType ).filter( Boolean );
		check(
			wpTypes.length === wpRowTypes.length + 1 && wpRowTypes.every( ( t ) => wpTypes.includes( t ) ),
			`@${ width } تب‌ها یک‌به‌یک از نوع‌های حاضر در ردیف ساخته شده‌اند (به‌همراه «همه»)`,
			`انواع ردیف=${ wpRowTypes.join( ',' ) } · تب‌ها=${ wpTypes.join( ',' ) }`
		);

		/* رفتار پالایش — فقط روی پله‌ی نخست و پله‌ی موبایل اجرا می‌شود. */
		if ( 1440 === width || 390 === width ) {
			await page.goto( WP, { waitUntil: 'load' } );
			await page.waitForTimeout( 800 );

			const before = await snapshot( page );
			check( !! before && before.visible === before.total, `@${ width } شروع: همه‌ی کارت‌ها دیده می‌شوند`, JSON.stringify( before ) );
			check(
				before && before.pressed.startsWith( 'all:true' ),
				`@${ width } شروع: تب «همه» فعال است`,
				before ? before.pressed : ''
			);

			await page.click( `${ WP_TABS } button[data-type="series"]` );
			await page.waitForTimeout( 200 );
			const series = await snapshot( page );
			check(
				series && series.visible > 0 && series.types.length === 1 && 'series' === series.types[ 0 ],
				`@${ width } کلیک «سریال‌ها»: تنها کارت‌های سریال (${ series ? series.visible : '-' } از ${ series ? series.total : '-' })`,
				JSON.stringify( series )
			);
			check(
				series && series.pressed.includes( 'series:true' ) && series.pressed.includes( 'all:false' ),
				`@${ width } کلیک «سریال‌ها»: وضعیت aria-pressed تب‌ها به‌روز شد`,
				series ? series.pressed : ''
			);

			await page.click( `${ WP_TABS } button[data-type="movie"]` );
			await page.waitForTimeout( 200 );
			const movie = await snapshot( page );
			check(
				movie && movie.visible > 0 && movie.types.length === 1 && 'movie' === movie.types[ 0 ],
				`@${ width } کلیک «فیلم‌ها»: تنها کارت‌های فیلم (${ movie ? movie.visible : '-' } از ${ movie ? movie.total : '-' })`,
				JSON.stringify( movie )
			);

			await page.click( `${ WP_TABS } button[data-type="all"]` );
			await page.waitForTimeout( 200 );
			const restored = await snapshot( page );
			check(
				restored && restored.visible === restored.total && restored.pressed.startsWith( 'all:true' ),
				`@${ width } کلیک «همه»: بازگشت کامل به حالت اول`,
				JSON.stringify( restored )
			);
		}

		check( 0 === errors.length, `@${ width } بدون خطای اجرای JS`, errors.join( ' | ' ) );

		await ctx.close();
	}

	await browser.close();

	console.log( '— برگشت داده‌ی آزمون —' );
	restoreAll();

	console.log( '' );
	console.log( '==========================================================' );
	console.log( `موفق: ${ pass }   ناموفق: ${ fail }   (مرجع: ${ REF })` );
	console.log( '==========================================================' );
	process.exit( fail ? 1 : 0 );
} )();

/* بازرسی همه‌ی قالب‌ها و بخش‌ها در ویرایشگر سایت: بلوک نامعتبر؟ بلوک‌ها رندر می‌شوند؟ */
const { chromium } = require( 'playwright' );

const TARGETS = [
	[ 'wp_template', 'koohe-film//single-series' ],
	[ 'wp_template', 'koohe-film//single-movie' ],
	[ 'wp_template', 'koohe-film//single-anime' ],
	[ 'wp_template', 'koohe-film//front-page' ],
	[ 'wp_template', 'koohe-film//archive-collection' ],
	[ 'wp_template', 'koohe-film//single-episode' ],
	[ 'wp_template', 'koohe-film//page-watch' ],
	/* صفحه‌ی اشتراک: کارت‌های طرح + اطلاعیه‌ی قیمت. */
	[ 'wp_template', 'koohe-film//page-subscribe' ],
	/* برگه‌ی «کشف داستان‌ها»: نوار مرور + سایدبار فیلتر + شبکه‌ی کشف. */
	[ 'wp_template', 'koohe-film//page-browse' ],
	/* برگه‌ی «برنامه پخش»: پنل هفتگی + ستون کنار + پیشنهادها. */
	[ 'wp_template', 'koohe-film//page-schedule' ],
	/* برگه‌ی «بازیگران و عوامل» و برگه‌ی چهره: نوار صافی چهره‌ها + شبکه‌ی چهره‌ها. */
	[ 'wp_template', 'koohe-film//page-cast' ],
	[ 'wp_template', 'koohe-film//archive-person' ],
	[ 'wp_template', 'koohe-film//single-person' ],
	[ 'wp_template', 'koohe-film//page-magazine' ],
	[ 'wp_template_part', 'koohe-film//title-header' ],
	[ 'wp_template_part', 'koohe-film//sidebar' ],
	[ 'wp_template_part', 'koohe-film//header' ],
	[ 'wp_template_part', 'koohe-film//footer' ],
];

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const ctx = await browser.newContext( { viewport: { width: 1700, height: 1000 }, locale: 'fa-IR' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

	await page.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'load' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.click( '#wp-submit' ) ] );

	const report = [];

	for ( const [ type, id ] of TARGETS ) {
		const url = 'http://localhost:8099/wp-admin/site-editor.php?postType=' + type +
			'&postId=' + encodeURIComponent( id ) + '&canvas=edit';
		await page.goto( url, { waitUntil: 'load' } );
		try {
			await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 60000 } );
		} catch ( e ) {
			report.push( { id, error: 'no canvas' } );
			continue;
		}
		await page.waitForTimeout( 7000 );

		const state = await page.evaluate( () => {
			const walk = ( bs ) => bs.flatMap( ( b ) => [ b ].concat( b.innerBlocks ? walk( b.innerBlocks ) : [] ) );
			try {
				const flat = walk( wp.data.select( 'core/block-editor' ).getBlocks() );
				return {
					blocks: flat.length,
					invalid: flat.filter( ( b ) => b.isValid === false ).map( ( b ) => b.name ),
				};
			} catch ( e ) {
				return { error: String( e ) };
			}
		} );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		const counts = {
			manacoreBlocks: await canvas.locator( '.manacore-block' ).count(),
			warnings: await canvas.locator( '.block-editor-warning__message' ).count(),
			seasons: await canvas.locator( '.manacore-season' ).count(),
			/* ردیف واقعی هر قسمت: `<li>` داخل `.manacore-episode-list`. */
			episodeRows: await canvas.locator( '.manacore-episode-list > li' ).count(),
			trailer: await canvas.locator( '.manacore-trailer' ).count(),
			/* بخش دانلود پس از یکسان‌سازی با مرجع: `.download-section`. */
			downloads: await canvas.locator( '.download-section' ).count(),
			downloadRows: await canvas.locator( '.download-section .download-row' ).count(),
			comments: await canvas.locator( '.comments-section' ).count(),
			playerPage: await canvas.locator( '.player-page' ).count(),
			/* نوار مرور برگه‌ی کشف و اجزای اطرافش. */
			browseToolbar: await canvas.locator( '.browse-toolbar' ).count(),
			browseSidebar: await canvas.locator( '.filter-sidebar' ).count(),
			resultsSummary: await canvas.locator( '.results-summary' ).count(),
			/* برگه‌ی «برنامه پخش»: پنل، تب‌های روز، ردیف‌ها و کارت‌های ستون کنار. */
			schedulePanel: await canvas.locator( '.schedule-panel' ).count(),
			scheduleTabs: await canvas.locator( '.week-tabs [role="tab"]' ).count(),
			scheduleItems: await canvas.locator( '.schedule-item' ).count(),
			scheduleNote: await canvas.locator( '.schedule-note-card' ).count(),
			schedulePromo: await canvas.locator( '.sidebar-promo' ).count(),
			/* برگه‌های «بازیگران و عوامل» و «چهره». */
			castSearch: await canvas.locator( '#cast-search' ).count(),
			peopleGrid: await canvas.locator( '.people-grid' ).count(),
			personCards: await canvas.locator( '.person-card' ).count(),
			personHero: await canvas.locator( '.person-hero' ).count(),
			personFacts: await canvas.locator( '.person-facts' ).count(),
			personWorks: await canvas.locator( '#filmography' ).count(),
			castGrid: await canvas.locator( '.cast-grid' ).count(),
			/* برگه‌ی «سینورامگ». */
			magazineHero: await canvas.locator( '.magazine-feature-grid' ).count(),
			magazineSide: await canvas.locator( '.magazine-side-features' ).count(),
			articlesGrid: await canvas.locator( '.articles-grid' ).count(),
			articleCards: await canvas.locator( '.articles-grid .article-card' ).count(),
		};

		report.push( { id, ...state, ...counts } );
	}

	console.log( JSON.stringify( report, null, 1 ) );
	console.log( 'PAGE ERRORS:', JSON.stringify( errors.slice( 0, 5 ) ) );
	await browser.close();
} )();

/*
 * دسترس‌پذیری صفحه‌ی پخش (`page-watch`) با axe-core.
 *
 * چرا جداگانه؟ صفحه‌ی پخش بعد از دکمه‌ی «پخش» باز می‌شود و شامل کنترل‌های
 * تازه (انتخاب کیفیت، حالت سینما، تمام‌صفحه، لیست تماشا، دانلود، قرص‌های
 * قسمت) است؛ هیچ‌کدام در آزمون‌های صفحه‌ی قسمت سنجیده نمی‌شدند.
 *
 * اجرا:
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node axe-player.cjs
 * خروجی: فهرست تخلف‌ها (آرایه‌ی خالی = بدون تخلف). کد خروج ۱ اگر تخلفی باشد.
 */
const { chromium } = require( 'playwright' );
const axePath = require.resolve( 'axe-core' );

/*
 * شناسه‌های آثار از پیوند «پخش» خودِ سایت خوانده می‌شوند تا آزمون به ترتیب
 * نصب محیط QA وابسته نباشد (پس از `provision.sh --force` هم درست کار کند).
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
	let violations = 0;

	const probe = await browser.newPage();
	const seriesBase = await playerBase( probe, '/series/shogun/' );
	const movieBase  = await playerBase( probe, '/movie/inception/' );
	await probe.close();

	const PAGES = [
		[ 'سریال', seriesBase + '&season=1&episode=1&quality=1080p', 1440 ],
		[ 'سریال (موبایل)', seriesBase + '&season=1&episode=2', 390 ],
		[ 'فیلم', movieBase, 1440 ],
	];

	if ( ! seriesBase || ! movieBase ) {
		console.log( 'نشانی پخش از صفحه‌ی جزئیات خوانده نشد (' + seriesBase + ' / ' + movieBase + ')' );
		violations += 1;
	}

	for ( const [ label, url, width ] of PAGES ) {
		const page = await browser.newPage( { viewport: { width, height: 1000 }, locale: 'fa-IR' } );
		await page.goto( url, { waitUntil: 'load' } );
		await page.waitForTimeout( 400 );

		if ( ! await page.evaluate( () => !! document.querySelector( '.player-page' ) ) ) {
			console.log( label + ': صفحه‌ی پخش رندر نشد (' + url + ')' );
			violations += 1;
			await page.close();
			continue;
		}

		await page.addScriptTag( { path: axePath } );
		const res = await page.evaluate( async () => await window.axe.run( document, { resultTypes: [ 'violations' ] } ) );
		const list = res.violations.map( ( v ) => ( {
			id: v.id,
			impact: v.impact,
			nodes: v.nodes.length,
			targets: v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ),
		} ) );

		violations += list.length;
		console.log( label + ' @' + width + ': ' + JSON.stringify( list ) );
		await page.close();
	}

	console.log( violations ? 'تخلف‌ها: ' + violations : 'بدون تخلف' );
	await browser.close();
	process.exit( violations ? 1 : 0 );
} )();

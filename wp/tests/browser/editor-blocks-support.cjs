/*
 * پشتیبانی بلوک‌های افزونه‌ی اشتراک در ویرایشگر سایت.
 *
 * چرا این آزمون وجود دارد؟
 * بلوک‌هایی که فقط سمت سرور ثبت می‌شوند (`register_block_type` بدون
 * اسکریپت ویرایشگر) در بوم ویرایشگر به‌شکل «سایت شما از بلوک … پشتیبانی
 * نمی‌کند» (`core/missing`) دیده می‌شوند؛ یعنی کارفرما در «ویرایشگر وردپرس»
 * نمی‌تواند آن‌ها را ببیند یا تنظیم کند. این آزمون قالب «صفحه‌ی اشتراک» را
 * باز می‌کند و سه چیز را می‌سنجد:
 *
 *   ۱) هیچ بلوکی پشتیبانی‌نشده و هیچ هشداری در بوم نباشد؛
 *   ۲) هر دو بلوک اشتراک در فهرست بلوک‌های ویرایشگر ثبت شده باشند؛
 *   ۳) پیش‌نمایش واقعی سمت سرور رندر شود (سه کارت طرح در بوم).
 *
 * اجرا:
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers \
 *     node wp/tests/browser/editor-blocks-support.cjs
 */
const { chromium } = require( 'playwright' );

const TEMPLATE = 'koohe-film//page-subscribe';
const EXPECTED = [ 'manacore/subscription-status', 'manacore/subscription-plans' ];

let pass = 0;
let fail = 0;

function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + ( detail ? '  → ' + detail : '' ) );
	}
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page = await browser.newPage( { viewport: { width: 1700, height: 1000 }, locale: 'fa-IR' } );
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

	await page.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'load' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.click( '#wp-submit' ) ] );

	await page.goto(
		'http://localhost:8099/wp-admin/site-editor.php?postType=wp_template&postId=' +
			encodeURIComponent( TEMPLATE ) + '&canvas=edit',
		{ waitUntil: 'load' }
	);
	await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 60000 } );
	await page.waitForTimeout( 7000 );

	const state = await page.evaluate( ( expected ) => {
		const walk = ( bs ) => bs.flatMap( ( b ) => [ b ].concat( b.innerBlocks ? walk( b.innerBlocks ) : [] ) );
		const flat = walk( wp.data.select( 'core/block-editor' ).getBlocks() );
		const types = [ ...new Set( flat.map( ( b ) => b.name ) ) ];

		return {
			types,
			// بلوک‌های پشتیبانی‌نشده یا به‌شکل core/missing می‌آیند یا invalid می‌شوند.
			invalid: flat.filter( ( b ) => b.isValid === false ).map( ( b ) => b.name ),
			missing: types.filter( ( n ) => 'core/missing' === n ),
			registered: expected.filter( ( n ) => !! wp.blocks.getBlockType( n ) ),
		};
	}, EXPECTED );

	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const warns = await canvas.locator( '.block-editor-warning__message' ).allTextContents();
	const planCards = await canvas.locator( '.manacore-plan' ).count();
	const previews = await canvas.locator( '.manacore-block-preview' ).count();

	console.log( '── ویرایشگر سایت: ' + TEMPLATE + ' ──────────────────' );
	check( 0 === state.invalid.length, 'هیچ بلوک نامعتبری نیست', state.invalid.join( ', ' ) );
	check( 0 === state.missing.length, 'هیچ بلوکی «پشتیبانی‌نشده» نیست', state.missing.join( ', ' ) );
	check( 0 === warns.length, 'هیچ هشدار بلوکی در بوم نیست', warns.join( ' | ' ) );
	check(
		2 === state.registered.length,
		'هر دو بلوک اشتراک در فهرست ویرایشگر ثبت شده‌اند',
		state.registered.join( ', ' )
	);
	check( previews > 0, `پیش‌نمایش سمت سرور در بوم رندر می‌شود (${ previews })` );
	check( 3 === planCards, `سه کارت طرح در پیش‌نمایش بوم (${ planCards })` );
	check( 0 === errors.length, 'خطای صفحه‌ای ندارد', errors.join( ' | ' ) );

	await browser.close();
	console.log( '' );
	console.log( `موفق: ${ pass }   ناموفق: ${ fail }` );
	process.exit( fail ? 1 : 0 );
} )();

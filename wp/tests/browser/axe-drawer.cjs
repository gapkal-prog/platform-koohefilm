/**
 * آزمون دسترس‌پذیری کشوی منوی موبایل (axe-core).
 *
 * کشو یک `role="dialog" aria-modal="true"` است که روی صفحه باز می‌شود؛ پس
 * جدا از اسکن کل صفحه، زیردرخت خودش هم اسکن می‌شود. اجرا از همین پوشه:
 *   PLAYWRIGHT_BROWSERS_PATH=… node axe-drawer.cjs
 *
 * @package KooheFilm
 */

'use strict';

let chromium;
let axePath;

try {
	( { chromium } = require( 'playwright' ) );
	axePath = require.resolve( 'axe-core' );
} catch ( error ) {
	console.error( 'playwright یا axe-core نصب نیست؛ این آزمون اجرا نشد.' );
	console.error( error.message );
	process.exit( 2 );
}

const WP_URL = process.env.WP_URL || 'http://localhost:8099/';

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page = await browser.newPage( { viewport: { width: 390, height: 844 }, locale: 'fa-IR' } );
	const errors = [];

	page.on( 'pageerror', ( error ) => errors.push( String( error ) ) );

	await page.goto( WP_URL, { waitUntil: 'load' } );
	await page.addScriptTag( { path: axePath } );

	/* بسته: کشو نباید در درخت دسترس‌پذیری باشد. */
	const closed = await page.evaluate( async () => {
		const result = await window.axe.run( document, { resultTypes: [ 'violations' ] } );
		return result.violations.map( ( v ) => ( { id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ) } ) );
	} );

	/* باز: اسکن کل صفحه و اسکن زیردرخت کشو. */
	await page.click( '[data-mobile-open]' );
	await page.waitForTimeout( 450 );

	const opened = await page.evaluate( async () => {
		const whole = await window.axe.run( document, { resultTypes: [ 'violations' ] } );
		const scope = await window.axe.run( document.querySelector( '.mobile-drawer' ), { resultTypes: [ 'violations' ] } );
		const brief = ( res ) => res.violations.map( ( v ) => ( { id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ) } ) );
		return { whole: brief( whole ), drawer: brief( scope ), hidden: document.querySelector( '[data-mobile-drawer]' ).hidden };
	} );

	/* فوکوس: با باز شدن کشو باید به داخل آن منتقل شود. */
	const focusInDrawer = await page.evaluate( () => document.querySelector( '.mobile-drawer' ).contains( document.activeElement ) );

	/* Escape: فوکوس باید به همبرگری برگردد. */
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 250 );
	const focusBack = await page.evaluate( () => document.activeElement === document.querySelector( '[data-mobile-open]' ) );

	console.log( JSON.stringify( { closed, opened, focusInDrawer, focusBack, errors }, null, 1 ) );

	await browser.close();

	const bad = closed.length + opened.whole.length + opened.drawer.length + ( focusInDrawer ? 0 : 1 ) + ( focusBack ? 0 : 1 ) + errors.length;

	process.exit( bad > 0 ? 1 : 0 );
} )();

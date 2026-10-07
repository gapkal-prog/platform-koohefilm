/**
 * شاهد چشمی برگه‌ی «حساب کاربری» — مهمان و کاربر دارای داده.
 *
 * برای هر حالت، هر شش تب در سه عرض (۱۴۴۰/۹۸۰/۳۹۰) برش گرفته می‌شود تا
 * بازبینی چشمی و مقایسه‌ی کنار‌هم با مرجع ممکن باشد.
 *
 * پیش‌نیاز (برای حالت «کاربر دارای داده»): کاشت فیکسچر
 *   php /usr/local/bin/wp --path=/home/user/.cache/wp eval-file wp/tests/qa-env/seed-account.php
 *
 * اجرا:
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node account-shots.cjs
 *
 * @package KooheFilm
 */

'use strict';

const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );

const OUT  = path.join( __dirname, '..', '..', '..', 'docs', 'shots' );
const TABS = [ 'overview', 'watchlist', 'history', 'analytics', 'subscription', 'settings' ];
const WIDTHS = [ 1440, 980, 390 ];

/**
 * ورود به حساب مدیر.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<void>}
 */
async function login( page ) {
	await page.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
}

( async () => {
	fs.mkdirSync( OUT, { recursive: true } );

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	let shots = 0;

	for ( const mode of [ 'guest', 'member' ] ) {
		for ( const width of WIDTHS ) {
			const context = await browser.newContext( {
				viewport: { width, height: 1000 },
				locale: 'fa-IR',
			} );
			const page = await context.newPage();

			if ( 'member' === mode ) {
				await login( page );
			}

			for ( const tab of TABS ) {
				await page.goto( 'http://localhost:8099/account/?tab=' + tab, { waitUntil: 'domcontentloaded' } );
				await page.waitForTimeout( 250 );
				await page.screenshot( {
					path: path.join( OUT, 'account-' + mode + '-' + tab + '-' + width + '.png' ),
					fullPage: false,
				} );
				shots++;
			}

			await context.close();
		}
	}

	await browser.close();
	console.log( shots + ' برش در ' + OUT + ' نوشته شد.' );
} )();

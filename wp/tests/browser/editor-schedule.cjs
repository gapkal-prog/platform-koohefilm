/*
 * آزمون ویرایشگر سایت برای بلوک «برنامه پخش» (manacore/schedule).
 *
 * چه چیزی سنجیده می‌شود؟
 *   ۱) الگوی `page-schedule` در ویرایشگر سایت باز می‌شود و بلوک سالم است.
 *   ۲) با انتخاب بلوک، پنل‌های بازرس (inspector) در نوار کنار می‌آیند:
 *      «تنظیمات برنامه»، «چیدمان و منبع»، «سرصفحه‌ی پنل»، «حالت خالی روز».
 *   ۳) تغییر یک کنترل، پیش‌نمایش بوم را بی‌درنگ عوض می‌کند (رفتار واقعی، نه تزئینی).
 *   ۴) کنترل‌های وابسته فقط وقتی معنی دارند دیده می‌شوند (منطق شرطی پنل).
 *
 * اجرا: PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node /home/user/.cache/qa/sched-editor.cjs
 */

'use strict';

const { chromium } = require( 'playwright' );

const BASE = 'http://localhost:8099';
let pass = 0;
let fail = 0;

function check( ok, label, extra ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label + ( extra ? '  — ' + extra : '' ) );
	} else {
		fail++;
		console.log( '  ✗ ' + label + ( extra ? '  — ' + extra : '' ) );
	}
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const ctx = await browser.newContext( { viewport: { width: 1700, height: 1000 }, locale: 'fa-IR' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

	await page.goto( BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

	await page.goto( BASE + '/wp-admin/site-editor.php?postType=wp_template&postId=' + encodeURIComponent( 'koohe-film//page-schedule' ) + '&canvas=edit', { waitUntil: 'load' } );
	await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 60000 } );

	async function dismissOverlays() {
		for ( let i = 0; i < 4; i++ ) {
			const overlay = page.locator( '.components-modal__screen-overlay' ).first();
			if ( ! ( await overlay.isVisible().catch( () => false ) ) ) return;
			const closeBtn = overlay.locator( '.components-modal__header button.components-button' ).first();
			if ( await closeBtn.count() ) await closeBtn.click( { timeout: 5000 } ).catch( () => {} );
			else await page.keyboard.press( 'Escape' ).catch( () => {} );
			await page.waitForTimeout( 600 );
		}
	}
	await page.waitForTimeout( 6000 );
	await dismissOverlays();

	/* انتخاب بلوک با همان کاری که ویرایشگر هنگام کلیک انجام می‌دهد. */
	const selected = await page.evaluate( () => {
		const store = wp.data.select( 'core/block-editor' );
		const stack = ( store.getBlocks() || [] ).slice();
		while ( stack.length ) {
			const block = stack.shift();
			if ( block.name === 'manacore/schedule' ) {
				wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
				return { clientId: block.clientId, attrs: block.attributes };
			}
			( block.innerBlocks || [] ).forEach( ( inner ) => stack.push( inner ) );
		}
		return null;
	} );
	check( !! selected, 'بلوک «برنامه پخش» در الگوی برگه انتخاب شد', JSON.stringify( selected && { id: selected.clientId, layout: selected.attrs.layout, mode: selected.attrs.mode } ) );
	check( selected && 'panel' === selected.attrs.layout, 'چیدمان بلوک در الگو «پنل کامل» است', selected ? String( selected.attrs.layout ) : '—' );
	check( selected && 'series' === selected.attrs.mode, 'منبع داده‌ی بلوک «آثار زمان‌بندی‌شده» است', selected ? String( selected.attrs.mode ) : '—' );

	/* نوار کنارِ تنظیمات باید باز باشد تا InspectorControls رندر شود. */
	const sidebar = page.locator( '.interface-interface-skeleton__sidebar' ).first();
	if ( ! ( await sidebar.isVisible().catch( () => false ) ) ) {
		await page.locator( 'button[aria-label="Settings"], button[aria-label="تنظیمات"]' ).first().click().catch( () => {} );
	}
	await sidebar.waitFor( { state: 'visible', timeout: 15000 } ).catch( () => {} );
	await page.waitForTimeout( 1800 );

	const panels = await page.evaluate( () => [ ...document.querySelectorAll( '.interface-interface-skeleton__sidebar .components-panel__body-title button' ) ].map( ( b ) => b.textContent.trim() ) );
	const wanted = [ 'تنظیمات برنامه', 'چیدمان و منبع', 'سرصفحه‌ی پنل', 'حالت خالی روز' ];
	wanted.forEach( ( w ) => check( panels.includes( w ), 'پنل بازرس «' + w + '» در نوار کنار هست' ) );
	check( panels.length >= 4, 'شمار پنل‌های بازرس بلوک برنامه', panels.length + ' → ' + JSON.stringify( panels ) );

	/* کنترل‌های وابسته: با mode=series باید «نام اصلی» و «دکمه‌ی پخش» بیایند. */
	const labels = await page.evaluate( () => {
		const sel = [
			'.interface-interface-skeleton__sidebar .components-toggle-control__label',
			'.interface-interface-skeleton__sidebar .components-base-control__label',
			/* کنترل چندمقداری (نوع محتوا) برچسبش را در FormTokenField می‌گذارد. */
			'.interface-interface-skeleton__sidebar .components-form-token-field__label',
			'.interface-interface-skeleton__sidebar .components-form-token-field label',
			'.interface-interface-skeleton__sidebar legend',
		].join( ',' );
		return [ ...document.querySelectorAll( sel ) ].map( ( l ) => l.textContent.trim() );
	} );
	[ 'نمایش نام اصلی', 'نمایش دکمه‌ی پخش', 'نمایش نشانگر زمان', 'نوع محتوا' ].forEach( ( l ) => check( labels.includes( l ), 'کنترل «' + l + '» دیده می‌شود' ) );

	/* رفتار واقعی: خاموش‌کردن «نمایش نشانگر زمان» باید بوم را عوض کند. */
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	check( 1 === await canvas.locator( '.timezone' ).count(), 'پیش از تغییر: نشانگر زمان در بوم هست' );

	const toggled = await page.evaluate( () => {
		const store = wp.data.select( 'core/block-editor' );
		const stack = ( store.getBlocks() || [] ).slice();
		while ( stack.length ) {
			const block = stack.shift();
			if ( block.name === 'manacore/schedule' ) {
				wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( block.clientId, { showTimezone: false } );
				return true;
			}
			( block.innerBlocks || [] ).forEach( ( inner ) => stack.push( inner ) );
		}
		return false;
	} );
	check( toggled, 'ویژگی «نمایش نشانگر زمان» از راه ویرایشگر تغییر کرد' );
	await page.waitForTimeout( 2500 );
	check( 0 === await canvas.locator( '.timezone' ).count(), 'پس از تغییر: نشانگر زمان از بوم رفت (پیش‌نمایش زنده)' );

	/* برگرداندن مقدار، تا سند ویرایش‌شده ذخیره نشود. */
	await page.evaluate( () => {
		const store = wp.data.select( 'core/block-editor' );
		const stack = ( store.getBlocks() || [] ).slice();
		while ( stack.length ) {
			const block = stack.shift();
			if ( block.name === 'manacore/schedule' ) {
				wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( block.clientId, { showTimezone: true } );
				return;
			}
			( block.innerBlocks || [] ).forEach( ( inner ) => stack.push( inner ) );
		}
	} );
	await page.waitForTimeout( 1500 );
	check( 1 === await canvas.locator( '.timezone' ).count(), 'بازگرداندن مقدار، نشانگر را به بوم برگرداند' );

	/* بلوک‌های کنار: کارت اطلاعاتی باید بازرس داشته باشد. */
	const infoSelected = await page.evaluate( () => {
		const store = wp.data.select( 'core/block-editor' );
		const stack = ( store.getBlocks() || [] ).slice();
		while ( stack.length ) {
			const block = stack.shift();
			if ( block.name === 'manacore/info-card' ) {
				wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
				return block.attributes.variant;
			}
			( block.innerBlocks || [] ).forEach( ( inner ) => stack.push( inner ) );
		}
		return null;
	} );
	await page.waitForTimeout( 1200 );
	const cardPanels = await page.evaluate( () => [ ...document.querySelectorAll( '.interface-interface-skeleton__sidebar .components-panel__body-title button' ) ].map( ( b ) => b.textContent.trim() ) );
	check( !! infoSelected && cardPanels.includes( 'کارت' ), 'کارت اطلاعاتی ستون کنار بازرس «کارت» دارد', JSON.stringify( { variant: infoSelected, panels: cardPanels.slice( 0, 5 ) } ) );

	console.log( '\n' + pass + ' / ' + fail );
	console.log( 'PAGE ERRORS:', JSON.stringify( errors.slice( 0, 5 ) ) );
	await browser.close();
	process.exit( fail ? 1 : 0 );
} )();

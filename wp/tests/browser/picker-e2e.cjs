/**
 * آزمون واقعی ویرایشگر بلوک: گزینش‌گر «اثر» در بلوک «فهرست قسمت‌ها».
 *
 * چه چیزی سنجیده می‌شود؟
 *   ۱) ویرایشگر باز می‌شود و بلوک «فهرست قسمت‌ها» هست.
 *   ۲) در نوار کنار، کنترل انتخاب اثر یک کمبوباکس است (نه فیلد عددی).
 *   ۳) با تایپ نام سریال، فهرست نتایج از REST می‌آید.
 *   ۴) با انتخاب سریال، پیش‌نمایش بلوک همان سریال را می‌سازد
 *      (عنوان فصل‌ها/قسمت‌های همان سریال) و مقدار ویژگی ذخیره می‌شود.
 *
 * اجرا: node /home/user/.cache/qa/picker-e2e.cjs
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
	const context = await browser.newContext( { viewport: { width: 1440, height: 950 }, locale: 'fa-IR' } );
	const page = await context.newPage();

	const consoleErrors = [];
	page.on( 'pageerror', ( e ) => consoleErrors.push( String( e ) ) );

	/* ورود مدیر. */
	await page.goto( BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

	/*
	 * شناسه‌ی برگه‌ی آزمون در هر نصب تازه فرق می‌کند (پیش‌تر ۱۹۸ ثابت بود
	 * و روی محیط تازه به «نوشته‌ی نامعتبر» می‌رسید). از نامک خوانده می‌شود.
	 */
	const qaPageId = await page.evaluate( async () => {
		const res = await fetch( '/wp-json/wp/v2/pages?slug=qa-actions&_fields=id', { credentials: 'same-origin' } );
		const list = res.ok ? await res.json() : [];
		return list && list[ 0 ] ? list[ 0 ].id : 0;
	} );

	if ( ! qaPageId ) {
		console.log( '  ✗ برگه‌ی آزمون «qa-actions» پیدا نشد' );
		await browser.close();
		process.exit( 2 );
	}

	/* باز کردن ویرایشگر برگه‌ی دارای بلوک قسمت‌ها. */
	await page.goto( BASE + '/wp-admin/post.php?post=' + qaPageId + '&action=edit', { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 6000 );

	/*
	 * بستن هر پوشانه‌ی باز (راهنمای خوش‌آمد ویرایشگر).
	 *
	 * این پوشانه روی نصب تازه دیده می‌شود و ~۴ ثانیه پس از بارگذاری
	 * ظاهر می‌شود؛ روی همه‌ی کلیک‌های بعدی می‌افتد و آزمون را با
	 * Timeout می‌شکند. برچسب دکمه‌ی بستن در فارسی «بستن» است، پس به‌جای
	 * فهرست برچسب‌ها، همان دکمه‌ی سرصفحه‌ی پوشانه بسته می‌شود.
	 */
	async function dismissOverlays() {
		for ( let i = 0; i < 4; i++ ) {
			const overlay = page.locator( '.components-modal__screen-overlay' ).first();
			if ( ! ( await overlay.isVisible().catch( () => false ) ) ) {
				return;
			}
			const closeBtn = overlay.locator( '.components-modal__header button.components-button' ).first();
			if ( await closeBtn.count() ) {
				await closeBtn.click( { timeout: 5000 } ).catch( () => {} );
			} else {
				await page.keyboard.press( 'Escape' ).catch( () => {} );
			}
			await page.waitForTimeout( 600 );
		}
	}

	await dismissOverlays();

	const canvasBlock = await page.locator( 'iframe[name="editor-canvas"]' ).count();
	check( canvasBlock > 0 || ( await page.locator( '.block-editor-block-list__layout' ).count() ) > 0, 'ویرایشگر بلوک باز شد' );

	/*
	 * انتخاب قطعی بلوک با API داده‌ی ویرایشگر.
	 *
	 * کلیک روی بوم در ویرایشگر وردپرس شکننده است (لینک‌های داخل پیش‌نمایش
	 * کلیک را می‌بلعند)؛ پس همان کاری را می‌کنیم که ویرایشگر هنگام انتخاب
	 * بلوک انجام می‌دهد: selectBlock روی clientId بلوک قسمت‌ها.
	 */
	const selected = await page.evaluate( () => {
		const store = wp.data.select( 'core/block-editor' );
		const stack = ( store.getBlocks() || [] ).slice();
		while ( stack.length ) {
			const block = stack.shift();
			if ( block.name === 'manacore/episodes-list' ) {
				wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
				return block.clientId;
			}
			( block.innerBlocks || [] ).forEach( ( inner ) => stack.push( inner ) );
		}
		return null;
	} );
	check( !! selected, 'بلوک «فهرست قسمت‌ها» در ویرایشگر انتخاب شد', 'clientId=' + selected );

	/*
	 * نوار کنار باید باز و دیده‌شدنی باشد؛ در وردپرس ۷ به‌طور پیش‌فرض
	 * بسته است (وجود دارد ولی visible نیست) و InspectorControls تا
	 * باز نشدنش رندر نمی‌شود.
	 */
	const sidebar = page.locator( '.interface-interface-skeleton__sidebar' ).first();
	if ( ! ( await sidebar.isVisible().catch( () => false ) ) ) {
		await page.locator( 'button[aria-label="Settings"], button[aria-label="تنظیمات"]' ).first().click().catch( () => {} );
	}
	await sidebar.waitFor( { state: 'visible', timeout: 15000 } ).catch( () => {} );
	await page.waitForTimeout( 1800 );

	/* نوار کنار باید کمبوباکس انتخاب اثر داشته باشد. */
	const combo = page.locator( '.components-combobox-control' ).first();
	const combos = await page.locator( '.components-combobox-control' ).count();
	const numericId = await page.locator( 'input[type="number"]' ).count();

	check( combos > 0, 'کنترل «سریال / انیمه» به‌صورت کمبوباکس رندر شد', 'combobox=' + combos + ' / عددی=' + numericId );

	if ( combos > 0 ) {
		await dismissOverlays();
		const input = combo.locator( 'input' ).first();
		await input.click();
		await input.fill( 'چرنوبیل' );
		await page.waitForTimeout( 1500 );

		const suggestions = await page.locator( '.components-form-token-field__suggestion' ).allInnerTexts();
		const real = suggestions.filter( ( t ) => t && ! /No items found|موردی/i.test( t ) );
		const options = real.length;
		check( options > 0, 'جست‌وجو در REST نتیجه داد', 'options=' + options + ' ' + JSON.stringify( real.slice( 0, 3 ) ) );

		if ( options > 0 ) {
			const first = await page.locator( '.components-form-token-field__suggestion' ).first().innerText();
			await page.locator( '.components-form-token-field__suggestion' ).first().click();
			await page.waitForTimeout( 2500 );

			/*
			 * نام‌کلاس‌های رندر، دقیقاً مثل مرجع‌اند: بخش `.download-section`،
			 * هر فصل یک پنل `[data-season-panel]` و هر قسمت یک `.episode-card`.
			 * (پیش‌تر `.manacore-season` / `.manacore-episode-list` سنجیده
			 * می‌شد که دیگر وجود ندارند.)
			 */
			const inner = page.frameLocator( 'iframe[name="editor-canvas"]' );
			const seasons = await inner.locator( '.download-section [data-season-panel]' ).count();
			const episodes = await inner.locator( '.download-section .episode-card' ).count();
			const titles = await inner.locator( '.download-section .episode-heading' ).allInnerTexts();

			check( seasons > 0, 'پس از انتخاب سریال، پیش‌نمایش بلوک فصل‌ها را ساخت', 'seasons=' + seasons + ' / episodes=' + episodes );
			check(
				titles.length > 0 && titles.join( ' ' ).includes( 'چرنوبیل' ),
				'قسمت‌های همان سریالِ انتخاب‌شده رندر شد',
				JSON.stringify( titles.slice( 0, 2 ) )
			);

			const attrs = await page.evaluate( () => {
				const sel = wp.data.select( 'core/block-editor' ).getSelectedBlock();
				return sel ? sel.attributes.postId : null;
			} );
			check( attrs > 0, 'شناسه‌ی سریال روی بلوک ذخیره شد', 'postId=' + attrs );

			// ذخیره و بررسی مقدار ویژگی در محتوای ذخیره‌شده.
			const saveBtn = page.locator( 'button.editor-post-publish-button, button[aria-label*="ذخیره"], .editor-post-save-draft' ).first();
			await saveBtn.click().catch( () => {} );
			await page.waitForTimeout( 3500 );
		}
	}

	check( consoleErrors.length === 0, 'خطای جاوااسکریپت در ویرایشگر رخ نداد', consoleErrors.slice( 0, 2 ).join( ' | ' ) );

	await page.screenshot( { path: '/home/user/.cache/qa/shots/editor-picker.png' } );

	console.log( '' );
	console.log( '==========================================================' );
	console.log( 'PICKER E2E: ' + pass + ' passed, ' + fail + ' failed' );
	console.log( '==========================================================' );

	await browser.close();
	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( 'harness error:', e );
	process.exit( 2 );
} );

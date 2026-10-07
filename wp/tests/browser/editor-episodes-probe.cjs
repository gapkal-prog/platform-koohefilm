/* آزمون واقعی: در بلوک «فهرست قسمت‌ها» یک سریال انتخاب می‌کنیم و می‌بینیم
   آیا بوم ویرایشگر همان لحظه به‌روز می‌شود یا نه. */
const { chromium } = require( 'playwright' );

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const ctx = await browser.newContext( { viewport: { width: 1680, height: 1000 }, locale: 'fa-IR' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

	await page.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'load' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.click( '#wp-submit' ) ] );

	/*
	 * شناسه‌ی برگه در هر نصب تازه فرق می‌کند؛ پیش‌تر ۲۵۴ ثابت بود و روی
	 * محیط تازه به «پست نامعتبر» می‌رسید. برگه از روی نامک خوانده
	 * می‌شود (seed.php آن را با بلوک «فهرست قسمت‌ها» می‌سازد).
	 */
	const pageId = await page.evaluate( async () => {
		const res = await fetch( '/wp-json/wp/v2/pages?slug=qa-actions&_fields=id', { credentials: 'same-origin' } );
		const list = res.ok ? await res.json() : [];
		return list && list[ 0 ] ? list[ 0 ].id : 0;
	} );
	console.log( 'QA PAGE ID:', pageId );
	if ( ! pageId ) {
		console.log( 'برگه‌ی آزمون پیدا نشد؛ اجرا متوقف شد.' );
		await browser.close();
		process.exit( 2 );
	}
	await page.goto( 'http://localhost:8099/wp-admin/post.php?post=' + pageId + '&action=edit', { waitUntil: 'load' } );
	await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 60000 } );
	await page.waitForTimeout( 3000 );

	/*
	 * نخستین ورود به ویرایشگر «راهنمای خوش‌آمد» را به‌صورت لایه‌ی مُدال
	 * نشان می‌دهد و کلیک‌ها را می‌گیرد. یک‌بار بسته می‌شود تا آزمون به
	 * خود ویرایشگر برسد (این مُدال رفتار خود وردپرس است، نه قالب).
	 */
	await page.evaluate( () => {
		try {
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuideTemplate', false );
		} catch ( e ) { /* نسخه‌های دیگر */ }
	} );
	const modalClose = page.locator( '.components-modal__screen-overlay button[aria-label]' ).first();
	if ( await modalClose.count() ) {
		await modalClose.click( { timeout: 5000 } ).catch( () => {} );
	}
	await page.keyboard.press( 'Escape' ).catch( () => {} );
	await page.waitForTimeout( 2000 );

	/* انتخاب بلوک */
	await page.evaluate( () => {
		const be = wp.data.select( 'core/block-editor' );
		const block = be.getBlocks().find( ( b ) => b.name === 'manacore/episodes-list' );
		if ( block ) { wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId ); }
	} );
	await page.waitForTimeout( 2000 );

	/*
	 * نشانه‌ی رندر این بلوک، بخش `.download-section` است (نام‌کلاس‌های
	 * دقیقاً مثل مرجع) که `<section id="download" data-manacore-episodes="ID">`
	 * را می‌سازد. پیش‌تر اینجا `.manacore-episodes` سنجیده می‌شد که دیگر
	 * وجود ندارد و آزمون را بی‌دلیل «ناموفق» نشان می‌داد.
	 */
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const before = await canvas.locator( '.download-section, .manacore-block' ).first().innerText().catch( () => '' );
	console.log( 'BEFORE(first 120):', JSON.stringify( ( before || '' ).slice( 0, 120 ) ) );

	/* باز کردن پنل تنظیمات قسمت‌ها */
	const panelBtn = page.locator( '.components-panel__body-toggle', { hasText: 'تنظیمات قسمت‌ها' } ).first();
	if ( await panelBtn.count() ) {
		const expanded = await panelBtn.getAttribute( 'aria-expanded' );
		if ( expanded === 'false' ) { await panelBtn.click(); }
		console.log( 'PANEL: opened' );
	} else {
		console.log( 'PANEL: NOT FOUND' );
	}
	await page.waitForTimeout( 1200 );

	/* گزینش‌گر: کمبوباکس سریال */
	const combo = page.locator( '.components-combobox-control input' ).first();
	console.log( 'COMBO count:', await page.locator( '.components-combobox-control input' ).count() );
	await combo.click();
	await combo.fill( 'شوگان' );
	await page.waitForTimeout( 1800 );

	const opts = await page.locator( '[role="listbox"] [role="option"], .components-form-token-field__suggestion' ).allInnerTexts().catch( () => [] );
	console.log( 'OPTIONS:', JSON.stringify( opts ) );

	let firstAttr = null;

	if ( opts.length ) {
		await page.locator( '[role="listbox"] [role="option"], .components-form-token-field__suggestion' ).first().click();
		await page.waitForTimeout( 4000 );

		firstAttr = await page.evaluate( () => {
			const be = wp.data.select( 'core/block-editor' );
			const block = be.getBlocks().find( ( b ) => b.name === 'manacore/episodes-list' );
			return block.attributes.postId;
		} );
		console.log( 'ATTR postId:', firstAttr );

		const html = await canvas.locator( '.download-section' ).first().innerHTML().catch( ( e ) => 'ERR ' + e.message );
		const clean = html.replace( /\s+/g, ' ' );
		/* کلاس‌های واقعیِ رندر: کارت هر قسمت `.episode-card` و ردیف کیفیت `.download-row` */
		const cards = ( html.match( /class="episode-card/g ) || [] ).length;
		const rows  = ( html.match( /class="download-row"/g ) || [] ).length;
		const empty = /هیچ|وجود ندارد/.test( html );
		const owner = await canvas.locator( '[data-manacore-episodes]' ).first().getAttribute( 'data-manacore-episodes' ).catch( () => null );
		console.log( 'CANVAS:', JSON.stringify( { owner, cards, rows, empty, head: clean.slice( 0, 160 ) } ) );
	}

	/*
	 * مرحله‌ی دوم: انتخاب یک سریال *دیگر* و سنجش به‌روزرسانی بوم.
	 * این همان چیزی است که کارفرما خواست: «در بلوک قسمت‌ها وقتی
	 * انتخاب بشه کار کنه». اگر بوم عوض نشود، اینجا دیده می‌شود.
	 */
	const combo2 = page.locator( '.components-combobox-control input' ).first();
	await combo2.click();
	await combo2.fill( '' );
	await combo2.fill( 'چرنوبیل' );
	await page.waitForTimeout( 1800 );

	const opts2 = await page.locator( '[role="listbox"] [role="option"], .components-form-token-field__suggestion' ).allInnerTexts().catch( () => [] );
	console.log( 'OPTIONS 2:', JSON.stringify( opts2 ) );

	if ( opts2.length ) {
		await page.locator( '[role="listbox"] [role="option"], .components-form-token-field__suggestion' ).first().click();
		await page.waitForTimeout( 4000 );

		const attr2 = await page.evaluate( () => {
			const be = wp.data.select( 'core/block-editor' );
			const block = be.getBlocks().find( ( b ) => b.name === 'manacore/episodes-list' );
			return block.attributes.postId;
		} );
		const text2 = await canvas.locator( '.download-section' ).first().innerText().catch( () => '' );
		const owner2 = await canvas.locator( '[data-manacore-episodes]' ).first().getAttribute( 'data-manacore-episodes' ).catch( () => null );
		const cards2 = await canvas.locator( '.download-section .episode-card' ).count().catch( () => 0 );
		console.log( 'ATTR postId 2:', attr2 );
		console.log( 'CANVAS 2 (first 200):', JSON.stringify( text2.replace( /\s+/g, ' ' ).slice( 0, 200 ) ) );
		console.log( 'OWNER/CARDS 2:', owner2, cards2 );
		console.log( 'CHANGED:', firstAttr !== null && attr2 !== firstAttr && String( owner2 ) === String( attr2 ) && cards2 > 0 );
	}

	console.log( 'PAGE ERRORS:', JSON.stringify( errors ) );
	await browser.close();
} )();

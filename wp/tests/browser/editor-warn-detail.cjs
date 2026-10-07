/* جزئیات هشدارهای بوم ویرایشگر برای هدف‌های داده‌شده */
const { chromium } = require( 'playwright' );
const TARGETS = [
	[ 'wp_template', 'koohe-film//single-episode' ],
	[ 'wp_template_part', 'koohe-film//title-header' ],
];
( async () => {
	const b = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const ctx = await b.newContext( { viewport: { width: 1700, height: 1000 }, locale: 'fa-IR' } );
	const p = await ctx.newPage();
	await p.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'load' } );
	await p.fill( '#user_login', 'admin' ); await p.fill( '#user_pass', 'admin' );
	await Promise.all( [ p.waitForNavigation( { waitUntil: 'load' } ), p.click( '#wp-submit' ) ] );
	for ( const [ type, id ] of TARGETS ) {
		await p.goto( 'http://localhost:8099/wp-admin/site-editor.php?postType=' + type + '&postId=' + encodeURIComponent( id ) + '&canvas=edit', { waitUntil: 'load' } );
		await p.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 90000 } );
		await p.waitForTimeout( 8000 );
		const canvas = p.frameLocator( 'iframe[name="editor-canvas"]' );
		const info = await canvas.locator( '.block-editor-warning' ).evaluateAll( function ( els ) {
			return els.map( function ( el ) {
				const block = el.closest( '[data-type]' );
				return { cls: ( el.className || '' ).slice( 0, 80 ), text: ( el.innerText || '' ).replace( /\s+/g, ' ' ).slice( 0, 160 ), dataType: block ? block.getAttribute( 'data-type' ) : null };
			} );
		} ).catch( function ( e ) { return 'ERR ' + e.message; } );
		console.log( id, JSON.stringify( info ) );
	}
	await b.close();
} )();

/* سنجش هندسه مگامنو: پنل وردپرس در برابر پنل مرجع cinora */
const { chromium } = require( 'playwright' );

function wpProbe( vw ) {
	const item = document.querySelector( '.koohe-nav .wp-block-navigation-item.koohe-mega' );
	const content = item && item.querySelector( ':scope > .wp-block-navigation-item__content' );
	const panel = item && item.querySelector( ':scope > .wp-block-navigation__submenu-container' );
	const box = function ( el ) { if ( ! el ) { return null; } const b = el.getBoundingClientRect(); return { x: Math.round( b.x ), y: Math.round( b.y ), w: Math.round( b.width ), h: Math.round( b.height ) }; };
	const chain = [];
	let el = item;
	while ( el && el !== document.body ) { chain.push( ( el.className || '' ).toString().slice( 0, 44 ) + ':' + getComputedStyle( el ).position ); el = el.parentElement; }
	const out = { vw: vw, chain: chain.slice( 0, 9 ), header: box( document.querySelector( '.koohe-header' ) ), slot: box( document.querySelector( '.koohe-header-slot' ) ), item: box( content ), panel: box( panel ), chips: [] };
	if ( panel ) {
		const cs = getComputedStyle( panel );
		out.panelCS = { display: cs.display, cols: cs.gridTemplateColumns, radius: cs.borderRadius, pad: cs.padding, top: cs.top };
		out.chips = Array.prototype.slice.call( panel.querySelectorAll( 'a' ) ).map( function ( a ) { return a.textContent.trim(); } ).filter( Boolean );
		out.rows = Array.prototype.slice.call( panel.children ).map( function ( li ) { const b = li.getBoundingClientRect(); return { cls: ( li.className || '' ).replace( /wp-block-navigation-item|wp-block-navigation-link/g, '' ).trim(), y: Math.round( b.y ), x: Math.round( b.x ), w: Math.round( b.width ) }; } );
	}
	return out;
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	const ctx = await browser.newContext( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
	const page = await ctx.newPage();
	await page.goto( 'http://localhost:8099/', { waitUntil: 'load' } );
	await page.waitForTimeout( 800 );
	await page.locator( '.koohe-nav .wp-block-navigation-item.koohe-mega .wp-block-navigation-item__content' ).first().hover();
	await page.waitForTimeout( 450 );
	for ( const w of [ 1440, 1300, 1024, 1920 ] ) {
		await page.setViewportSize( { width: w, height: 1000 } );
		await page.waitForTimeout( 300 );
		await page.locator( '.koohe-nav .wp-block-navigation-item.koohe-mega .wp-block-navigation-item__content' ).first().hover();
		await page.waitForTimeout( 350 );
		const r = await page.evaluate( wpProbe, w );
		console.log( 'WP::' + w + '  ' + JSON.stringify( { header: r.header, item: r.item, panel: r.panel, panelCS: r.panelCS, chain: r.chain.slice( 0, 3 ), chips: r.chips.length, chip0: r.chips[ 0 ] } ) );
	}
	await page.setViewportSize( { width: 390, height: 900 } );
	await page.waitForTimeout( 400 );
	const mob = await page.evaluate( function () {
		const t = document.querySelector( '.koohe-nav .wp-block-navigation__responsive-container-open' );
		return { trigger: t ? ( t.className || '' ).toString() : null, overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth };
	} );
	console.log( 'WP::390   ' + JSON.stringify( mob ) );
	await ctx.close();

	const ctx2 = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
	const p2 = await ctx2.newPage();
	await p2.goto( 'file:///home/user/koohefilm/cinora/index.html', { waitUntil: 'load' } );
	await p2.waitForTimeout( 600 );
	const ref = await p2.evaluate( function () {
		const menu = document.querySelector( '.mega-menu' );
		const trigger = document.querySelector( '[data-mega-menu], .has-mega, .nav-item.has-dropdown, .main-nav .has-menu' );
		const box = function ( el ) { if ( ! el ) { return null; } const b = el.getBoundingClientRect(); return { x: Math.round( b.x ), y: Math.round( b.y ), w: Math.round( b.width ), h: Math.round( b.height ) }; };
		if ( trigger ) { trigger.dispatchEvent( new MouseEvent( 'mouseenter', { bubbles: true } ) ); trigger.dispatchEvent( new MouseEvent( 'mouseover', { bubbles: true } ) ); }
		const out = { header: box( document.querySelector( 'header' ) ), trigger: box( trigger ), menu: box( menu ), chips: [] };
		if ( menu ) {
			const cs = getComputedStyle( menu );
			out.menuCS = { display: cs.display, cols: cs.gridTemplateColumns, radius: cs.borderRadius, pad: cs.padding, top: cs.top, pos: cs.position };
			out.chips = Array.prototype.slice.call( menu.querySelectorAll( 'a' ) ).map( function ( a ) { return a.textContent.trim(); } ).filter( Boolean ).slice( 0, 14 );
		}
		return out;
	} );
	console.log( 'REF::1440 ' + JSON.stringify( ref ) );
	await ctx2.close();
	await browser.close();
} )();

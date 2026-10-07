/* هندسه‌ی تک‌تک آیتم‌های پنل مگامنو (کلاس، متن، جعبه) */
const { chromium } = require( 'playwright' );
( async () => {
	const b = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const p = await b.newPage( { viewport: { width: 1440, height: 700 }, locale: 'fa-IR' } );
	await p.goto( 'http://localhost:8099/', { waitUntil: 'load' } );
	await p.waitForTimeout( 800 );
	await p.locator( '.koohe-nav .koohe-mega .wp-block-navigation-item__content' ).first().hover();
	await p.waitForTimeout( 500 );
	const out = await p.evaluate( () => {
		const panel = document.querySelector( '.koohe-nav .koohe-mega > .wp-block-navigation__submenu-container' );
		const box = ( el ) => { const r = el.getBoundingClientRect(); return { x: Math.round( r.x ), y: Math.round( r.y ), w: Math.round( r.width ), h: Math.round( r.height ) }; };
		const items = Array.from( panel.children ).map( ( li ) => {
			const cls = li.className.split( ' ' ).filter( ( c ) => c.indexOf( 'koohe' ) === 0 || c.indexOf( 'wp-block' ) === 0 ).join( '.' );
			return { cls: cls.replace( 'wp-block-navigation-item', 'li' ).replace( 'wp-block-navigation-link', 'link' ).replace( 'wp-block-navigation-submenu', '' ), text: ( li.innerText || '' ).replace( /\s+/g, ' ' ).slice( 0, 24 ), ...box( li ) };
		} );
		const f = panel.querySelector( ':scope > .koohe-mega-feature' );
		const cs = f ? getComputedStyle( f ) : null;
		return { panel: box( panel ), items: items, feature: cs ? { pos: cs.position, top: cs.top, bottom: cs.bottom, inlineStart: cs.insetInlineStart, width: cs.width, gridColumn: cs.gridColumn } : null };
	} );
	console.log( JSON.stringify( out, null, 1 ) );
	await b.close();
} )();

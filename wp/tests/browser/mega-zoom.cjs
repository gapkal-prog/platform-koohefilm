/* نمای نزدیک پنل مگامنو با مقیاس ۲ برای بازرسی بصری */
const { chromium } = require( 'playwright' );
( async () => {
	const b = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const p = await b.newPage( { viewport: { width: 1440, height: 700 }, deviceScaleFactor: 2, locale: 'fa-IR' } );
	await p.goto( 'http://localhost:8099/', { waitUntil: 'load' } );
	await p.waitForTimeout( 800 );
	await p.locator( '.koohe-nav .koohe-mega .wp-block-navigation-item__content' ).first().hover();
	await p.waitForTimeout( 500 );
	await p.screenshot( { path: 'shots/mega-zoom.png', clip: { x: 56, y: 83, width: 1328, height: 300 } } );
	const info = await p.evaluate( () => {
		const f = document.querySelector( '.koohe-mega-feature .wp-block-navigation-item__content' );
		const d = f && f.querySelector( '.wp-block-navigation-item__description' );
		const l = f && f.querySelector( '.wp-block-navigation-item__label' );
		const cs = d ? getComputedStyle( d ) : null;
		return {
			descOrder: cs && cs.order, descColor: cs && cs.color, descFont: cs && cs.fontSize,
			labelBox: l ? l.getBoundingClientRect().top : null, descBox: d ? d.getBoundingClientRect().top : null,
			chips: Array.from( document.querySelectorAll( '.koohe-mega > .wp-block-navigation__submenu-container > .koohe-mega-genre' ) ).map( ( li ) => li.innerText.trim() ).join( ',' ),
		};
	} );
	console.log( JSON.stringify( info ) );
	await b.close();
} )();

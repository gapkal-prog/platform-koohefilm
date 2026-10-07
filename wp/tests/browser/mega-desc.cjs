const { chromium } = require( 'playwright' );
( async () => {
	const b = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const p = await b.newPage( { viewport: { width: 1440, height: 700 }, locale: 'fa-IR' } );
	await p.goto( 'http://localhost:8099/', { waitUntil: 'load' } );
	await p.waitForTimeout( 700 );
	await p.locator( '.koohe-nav .koohe-mega .wp-block-navigation-item__content' ).first().hover();
	await p.waitForTimeout( 400 );
	console.log( JSON.stringify( await p.evaluate( () => {
		const cards = Array.from( document.querySelectorAll( '.koohe-mega-feature' ) );
		return cards.map( ( li ) => {
			const a = li.querySelector( ':scope > .wp-block-navigation-item__content' );
			const d = a && a.querySelector( '.wp-block-navigation-item__description' );
			const l = a && a.querySelector( '.wp-block-navigation-item__label' );
			const r = li.getBoundingClientRect();
			const cs = d ? getComputedStyle( d ) : null;
			return {
				visible: r.width > 0,
				liCls: li.className.slice( 0, 70 ),
				hasDesc: !! d, desc: d ? d.textContent.trim() : null,
				descDisplay: cs && cs.display, descVis: cs && cs.visibility, descOrder: cs && cs.order,
				labelTop: l ? Math.round( l.getBoundingClientRect().top ) : null,
				descTop: d ? Math.round( d.getBoundingClientRect().top ) : null,
			};
		} );
	} ), null, 1 ) );
	await b.close();
} )();

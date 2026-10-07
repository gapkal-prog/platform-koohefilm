const { chromium } = require( 'playwright' );
const axePath = require.resolve( 'axe-core' );
( async () => {
	const b = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const p = await b.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );
	await p.goto( 'http://localhost:8099/series/chernobyl/season-1/episode-1/', { waitUntil: 'load' } );
	await p.addScriptTag( { path: axePath } );
	const res = await p.evaluate( async () => await window.axe.run( document, { resultTypes: [ 'violations' ] } ) );
	console.log( JSON.stringify( res.violations.map( v => ( { id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.map( n => n.target.join( ' ' ) ).slice( 0, 3 ) } ) ) ) );
	await b.close();
} )();

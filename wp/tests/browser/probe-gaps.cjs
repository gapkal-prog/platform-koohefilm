'use strict';
const { chromium } = require( 'playwright' );
const TARGETS = [
  [ 'help', 'http://localhost:8099/help/', 'http://localhost:8098/help.html', '.info-intro', '.faq-list' ],
  [ 'privacy', 'http://localhost:8099/privacy/', 'http://localhost:8098/privacy.html', '.info-intro', '.legal-content' ],
  [ 'about', 'http://localhost:8099/about/', 'http://localhost:8098/about.html', '.info-intro', '.about-values' ],
];
( async () => {
  const b = await chromium.launch({ args:['--no-sandbox'] });
  for ( const [ name, wp, ref, aSel, bSel ] of TARGETS ) {
    for ( const width of [ 1440, 768 ] ) {
      const out = {};
      for ( const [ side, url ] of [ [ 'ref', ref ], [ 'wp', wp ] ] ) {
        const p = await b.newPage({ viewport:{width,height:1000}, locale:'fa-IR' });
        await p.goto( url, { waitUntil:'domcontentloaded' } );
        await p.waitForTimeout( 250 );
        out[ side ] = await p.evaluate( ( [ a, bb ] ) => {
          const A = document.querySelector( a ), B = document.querySelector( bb );
          if ( ! A || ! B ) return null;
          return Math.round( B.getBoundingClientRect().top - A.getBoundingClientRect().bottom );
        }, [ aSel, bSel ] );
        await p.close();
      }
      console.log( name, '@' + width, 'ref=' + out.ref, 'wp=' + out.wp, 'Δ=' + ( out.wp - out.ref ) );
    }
  }
  await b.close();
} )();

'use strict';
const { chromium } = require( 'playwright' );
( async () => {
  const b = await chromium.launch({ args:['--no-sandbox'] });
  const p = await b.newPage({ viewport:{width:1440,height:1000}, locale:'fa-IR' });
  await p.goto( 'http://localhost:8099/about/', { waitUntil:'domcontentloaded' } );
  await p.waitForTimeout( 300 );
  console.log( JSON.stringify( await p.evaluate( () => {
    const main = document.querySelector( 'main.info-page' );
    const k = Array.from( main.children );
    const info = ( el ) => { const s = getComputedStyle( el ); const r = el.getBoundingClientRect();
      return { cls: el.className.slice(0,45), top: Math.round(r.top), bottom: Math.round(r.bottom), offsetTop: el.offsetTop, h: Math.round(r.height),
        mt: s.marginTop, mb: s.marginBottom, bt: s.borderTopWidth, pt: s.paddingTop, pos: s.position, tr: s.transform, mbs: s.marginBlockStart, mbe: s.marginBlockEnd }; };
    const before = ( el ) => { const s = getComputedStyle( el, '::before' ); const a = getComputedStyle( el, '::after' );
      return { before: { h: s.height, mt: s.marginTop, mb: s.marginBottom, display: s.display }, after: { h: a.height, mt: a.marginTop, mb: a.marginBottom, display: a.display } }; };
    return { kids: k.map( info ), pseudo: k.map( before ), mainGap: getComputedStyle( main ).getPropertyValue('--wp--style--block-gap'), mainCls: main.className };
  } ), null, 1 ) );
  await b.close();
} )();

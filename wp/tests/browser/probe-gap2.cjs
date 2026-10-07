'use strict';
const { chromium } = require( 'playwright' );
( async () => {
  const b = await chromium.launch({ args:['--no-sandbox'] });
  const p = await b.newPage({ viewport:{width:1440,height:1000}, locale:'fa-IR' });
  await p.goto( 'http://localhost:8099/about/', { waitUntil:'domcontentloaded' } );
  await p.waitForTimeout( 300 );
  console.log( JSON.stringify( await p.evaluate( () => {
    const chain = [ '.info-intro', '.about-content', '.about-content > .wp-block-post-content', '.about-values', '.about-values > section', '.about-story' ];
    return chain.map( ( sel ) => {
      const el = document.querySelector( sel );
      if ( ! el ) return { sel, missing:true };
      const r = el.getBoundingClientRect(); const s = getComputedStyle( el );
      return { sel, y: Math.round(r.top), bottom: Math.round(r.bottom), marginTop: s.marginTop, gapVar: s.getPropertyValue('--wp--style--block-gap'), cls: el.className.split(' ').filter(c=>c.includes('layout')||c.includes('container')).join(' ') };
    } );
  } ), null, 1 ) );
  await b.close();
} )();

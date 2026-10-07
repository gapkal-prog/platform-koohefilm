'use strict';
const { chromium } = require( 'playwright' );
( async () => {
  const b = await chromium.launch({ args:['--no-sandbox'] });
  const p = await b.newPage({ viewport:{width:1440,height:1000}, locale:'fa-IR' });
  await p.goto( 'http://localhost:8099/about/', { waitUntil:'domcontentloaded' } );
  await p.waitForTimeout( 300 );
  console.log( JSON.stringify( await p.evaluate( () => {
    const main = document.querySelector( 'main.info-page' );
    const kids = Array.from( main.children ).map( ( el ) => {
      const r = el.getBoundingClientRect(); const s = getComputedStyle( el );
      return { cls: el.className.slice(0,60), y: Math.round(r.top), bottom: Math.round(r.bottom),
        mt: s.marginTop, mb: s.marginBottom, pt: s.paddingTop, pb: s.paddingBottom, gap: s.rowGap, display: s.display };
    } );
    const intro = document.querySelector( '.info-intro' );
    const introKid = intro ? Array.from( intro.children ).map( el => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return { t: el.tagName, mt: s.marginTop, mb: s.marginBottom, bottom: Math.round(r.bottom) }; } ) : null;
    /* همه‌ی قواعدی که به فرزند دومِ main حاشیه/فاصله می‌دهند */
    const rules = [];
    for ( const sheet of Array.from( document.styleSheets ) ) {
      let list; try { list = sheet.cssRules; } catch ( e ) { continue; }
      const walk = ( arr ) => { for ( const rule of Array.from( arr ) ) {
        if ( rule.cssRules ) { walk( rule.cssRules ); continue; }
        if ( ! rule.selectorText ) continue;
        if ( /\* \+ \*|block-gap|> \*/.test( rule.selectorText ) ) {
          const text = rule.cssText;
          if ( text.includes( 'block-gap' ) || text.includes( 'margin' ) ) rules.push( text.slice( 0, 220 ) );
        }
      } };
      walk( list );
    }
    return { kids, introKid, mainMarginBottom: getComputedStyle( main ).marginBottom, rules: rules.slice( 0, 14 ) };
  } ), null, 1 ) );
  await b.close();
} )();

'use strict';
const { chromium } = require( 'playwright' );
( async () => {
  const b = await chromium.launch({ args:['--no-sandbox'] });
  const p = await b.newPage({ viewport:{width:1440,height:900}, locale:'fa-IR' });
  await p.goto( 'http://localhost:8099/', { waitUntil:'load' } );
  await p.waitForTimeout( 600 );
  console.log( JSON.stringify( await p.evaluate( () => {
    const scope = document.querySelector( '.schedule-panel' );
    const days = Array.from( scope.querySelectorAll( '[role="tab"]' ) ).map( ( t ) => t.textContent.trim() );
    const visible = Array.from( scope.querySelectorAll( '.schedule-item' ) ).filter( ( el ) => el.checkVisibility() );
    const active = Array.from( scope.querySelectorAll( '[role="tab"]' ) ).find( ( t ) => t.getAttribute( 'aria-selected' ) === 'true' );
    return {
      dayTabs: days,
      active: active ? active.textContent.trim() : null,
      visibleRows: visible.length,
      rows: visible.map( ( el ) => ( el.querySelector( 'h3' ) || {} ).textContent ),
      dayListH: Math.round( ( document.querySelector( '.schedule-panel .schedule-list, .schedule-panel [role="tabpanel"]:not([hidden])' ) || {} ).getBoundingClientRect?.().height || 0 ),
      panelH: Math.round( scope.getBoundingClientRect().height ),
    };
  } ), null, 1 ) );
  await b.close();
} )();

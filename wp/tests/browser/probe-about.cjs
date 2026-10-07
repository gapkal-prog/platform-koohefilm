/*
 * کاوشگر: چه قاعده‌ای اندازه‌ی سرتیتر کارت‌های «درباره ما» را برنده می‌شود و
 * فاصله‌ی دیده‌شده‌ی بخش‌ها از کجا می‌آید؟ (سنجش یک‌باره، نه سوئیت)
 *
 * اجرا: cd wp/tests/browser && PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers node probe-about.cjs
 */
'use strict';

const { chromium } = require( 'playwright' );

const URL = process.env.WP_ABOUT || 'http://localhost:8099/about/';

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	const page = await browser.newPage( { viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' } );

	await page.goto( URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 300 );

	const dump = await page.evaluate( () => {
		const target = ( sel ) => document.querySelector( sel );
		const matches = ( el, props ) => {
			const out = [];

			for ( const sheet of Array.from( document.styleSheets ) ) {
				let rules;

				try {
					rules = sheet.cssRules;
				} catch ( e ) {
					continue;
				}

				const walk = ( list, media ) => {
					for ( const rule of Array.from( list ) ) {
						if ( rule.cssRules ) {
							walk( rule.cssRules, ( media ? media + ' && ' : '' ) + ( rule.conditionText || rule.media?.mediaText || rule.name || '' ) );
							continue;
						}

						if ( ! rule.selectorText ) {
							continue;
						}

						props.forEach( ( p ) => {
							if ( ! rule.style[ p ] ) {
								return;
							}

							let hit = false;

							try {
								hit = el.matches( rule.selectorText );
							} catch ( e ) {
								hit = false;
							}

							if ( hit ) {
								out.push( {
									prop: p,
									value: rule.style[ p ],
									selector: rule.selectorText,
									source: sheet.href ? sheet.href.split( '/' ).slice( -1 )[ 0 ] : 'inline',
									match: media || '',
									order: Array.from( sheet.cssRules ).indexOf( rule ),
								} );
							}
						} );
					}
				};

				walk( rules, '' );
			}

			return out;
		};

		const h2 = target( '.about-values > section > h2' );
		const values = target( '.about-values' );
		const story = target( '.about-story' );
		const main = target( 'main.info-page' );
		const post = target( '.about-content > .wp-block-post-content' );

		const geom = ( el ) => {
			if ( ! el ) {
				return null;
			}

			const r = el.getBoundingClientRect();
			const s = getComputedStyle( el );

			return { x: Math.round( r.left ), y: Math.round( r.top ), w: Math.round( r.width ), h: Math.round( r.height ), marginTop: s.marginTop, fontSize: s.fontSize };
		};

		return {
			classList: main ? main.className : null,
			mainGapVar: getComputedStyle( document.documentElement ).getPropertyValue( '--wp--style--block-gap' ),
			mainStyle: main ? getComputedStyle( main ).rowGap + ' / ' + getComputedStyle( main ).display : null,
			blockGapVarOnMain: main ? getComputedStyle( main ).getPropertyValue( '--wp--style--block-gap' ) : null,
			blockGapVarOnValues: values ? getComputedStyle( values ).getPropertyValue( '--wp--style--block-gap' ) : null,
			blockGapVarOnPost: post ? getComputedStyle( post ).getPropertyValue( '--wp--style--block-gap' ) : null,
			geom: { h2: geom( h2 ), values: geom( values ), story: geom( story ), main: geom( main ), post: geom( post ) },
			h2Rules: matches( h2, [ 'font-size', 'line-height', 'margin-top' ] ),
			valuesRules: matches( values, [ 'margin-top', 'gap', 'row-gap' ] ),
			storyRules: matches( story, [ 'margin-top', 'max-width' ] ),
			containerRules: Array.from( document.querySelectorAll( '[class*="wp-container-core-group-is-layout"]' ) )
				.slice( 0, 6 )
				.map( ( el ) => ( { cls: el.className.split( ' ' ).filter( ( c ) => c.startsWith( 'wp-container' ) )[ 0 ], gap: getComputedStyle( el ).getPropertyValue( '--wp--style--block-gap' ) } ) ),
		};
	} );

	console.log( JSON.stringify( dump, null, 1 ) );
	await browser.close();
} )();

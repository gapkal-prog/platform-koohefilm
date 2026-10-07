/*
 * قرارداد اجرایی بخش «طرح‌های اشتراک» در برابر مرجع سینورا.
 *
 * هر عدد و هر رشته‌ای که مرجع در کارت طرح نشان می‌دهد، اینجا با محصول
 * مقایسه می‌شود: هندسه‌ی کارت و اجزایش، متن‌ها (قیمت، درصد تخفیف، معادل
 * ماهانه، عنوان، تگ‌لاین، برچسب محبوب)، ترتیب کارت‌ها و هندسه‌ی اطلاعیه‌ی
 * قیمت. شکست‌ها شمرده و در پایان چاپ می‌شوند، تا رگرسیون پنهان نماند.
 *
 * اجرا:
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers \
 *     node wp/tests/browser/plans-parity.cjs
 *
 * (نیازمند هر دو سرور: ۸۰۹۹ محصول، ۸۰۹۸ مرجع.)
 */
const { chromium } = require( 'playwright' );

const WP = 'http://localhost:8099/subscribe/';
const REF = 'http://localhost:8098/subscription.html';
const WIDTHS = [ 1440, 980, 480 ];

const CARD = { ref: '.pricing-card.featured', wp: '.manacore-plan.is-featured' };
const DISCLAIMER = { ref: '.pricing-disclaimer', wp: '.koohe-pricing-disclaimer' };

let pass = 0;
let fail = 0;

function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + '  → ' + ( detail || '' ) );
	}
}

/** اندازه‌ها و استایل‌های محاسبه‌شده‌ی یک گزینش‌گر. */
function measure( page, sel, inner ) {
	return page.evaluate(
		( { sel, inner } ) => {
			const el = document.querySelector( sel );

			if ( ! el ) {
				return null;
			}

			const one = ( s ) => {
				/* زیرگزینش‌گرها نسبت به خود کارت خوانده می‌شوند. */
				const e = s
					? el.querySelector( s.replace( /^\s+/, '' ).replace( /^>/, ':scope >' ) )
					: el;

				if ( ! e ) {
					return null;
				}

				const c = getComputedStyle( e );
				const b = e.getBoundingClientRect();

				return {
					w: Math.round( b.width ),
					h: Math.round( b.height ),
					fs: c.fontSize,
					pad: c.padding,
					mt: c.marginTop,
					gap: c.gap,
					radius: c.borderRadius,
					color: c.color,
					bg: c.backgroundColor,
					fw: c.fontWeight,
				};
			};

			const out = { box: one( '' ) };

			for ( const key of Object.keys( inner ) ) {
				out[ key ] = one( inner[ key ] );
			}

			return out;
		},
		{ sel, inner }
	);
}

/** متن‌های دیده‌شده در هر کارت. */
function strings( page, sel ) {
	return page.evaluate( ( sel ) => {
		const cards = [ ...document.querySelectorAll( sel ) ];

		return {
			dir: getComputedStyle( cards[ 0 ].parentElement ).direction,
			cards: cards.map( ( c ) => ( {
				title: ( c.querySelector( 'h2, h3' ) || {} ).textContent.trim(),
				tag: ( c.querySelector( 'p' ) || {} ).textContent.trim(),
				del: ( c.querySelector( 'del' ) || {} ).textContent.trim(),
				discount: ( c.querySelector( '.discount, .manacore-plan-discount' ) || {} ).textContent.trim(),
				price: ( c.querySelector( 'strong' ) || {} ).textContent.trim().replace( /\s+/g, ' ' ),
				monthly: ( c.querySelector( '.plan-price>p, .manacore-plan-price>p' ) || {} ).textContent.trim().replace( /\s+/g, ' ' ),
				li0: ( c.querySelector( 'li' ) || {} ).textContent.trim().replace( /^[^\p{L}]+/u, '' ),
				li4: ( ( c.querySelectorAll( 'li' )[ 4 ] || {} ).textContent || '' ).trim().replace( /^[^\p{L}]+/u, '' ),
				ribbon: ( ( c.querySelector( '.popular-plan, .manacore-plan-ribbon' ) || {} ).textContent || '' ).trim(),
				x: Math.round( c.getBoundingClientRect().left ),
			} ) ),
		};
	}, sel );
}

( async () => {
	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );

	for ( const width of WIDTHS ) {
		const ctx = await browser.newContext( { viewport: { width, height: 1000 }, locale: 'fa-IR' } );
		const refPage = await ctx.newPage();
		await refPage.goto( REF, { waitUntil: 'load' } );
		await refPage.waitForTimeout( 400 );
		const wpPage = await ctx.newPage();
		await wpPage.goto( WP, { waitUntil: 'load' } );
		await wpPage.waitForTimeout( 400 );

		console.log( '' );
		console.log( '── عرض ' + width + 'px ───────────────────────────────' );

		const wpCard = await measure( wpPage, CARD.wp, {
			title: ' h3',
			tag: ' > p',
			priceStrong: ' strong',
			priceSmall: ' strong small',
			priceP: ' .manacore-plan-price > p',
			del: ' del',
			discount: ' .manacore-plan-discount',
			ul: ' ul',
			li: ' ul li',
			cta: ' .manacore-btn',
			ribbon: ' .manacore-plan-ribbon',
		} );
		const refCardBox = await measure( refPage, CARD.ref, {
			title: ' h2',
			tag: ' > p',
			priceStrong: ' strong',
			priceSmall: ' strong small',
			priceP: ' .plan-price > p',
			del: ' del',
			discount: ' .discount',
			ul: ' ul',
			li: ' ul li',
			cta: ' .button',
			ribbon: ' .popular-plan',
		} );

		check(
			refCardBox.box.w === wpCard.box.w && refCardBox.box.h === wpCard.box.h,
			`کارت طرح: ${ wpCard.box.w }×${ wpCard.box.h } (مرجع ${ refCardBox.box.w }×${ refCardBox.box.h })`
		);
		check( refCardBox.box.pad === wpCard.box.pad, `پدینگ کارت ${ wpCard.box.pad }` );
		check( refCardBox.box.radius === wpCard.box.radius, `شعاع کارت ${ wpCard.box.radius }` );

		for ( const key of Object.keys( refCardBox ) ) {
			if ( 'box' === key || ! refCardBox[ key ] || ! wpCard[ key ] ) {
				continue;
			}

			const r = refCardBox[ key ];
			const w = wpCard[ key ];
			const same = r.fs === w.fs && r.mt === w.mt && r.pad === w.pad && r.radius === w.radius && r.fw === w.fw;
			const sizeOk = 'ribbon' === key || 'cta' === key || 'ul' === key
				? r.h === w.h
				: r.h === w.h;
			check(
				same && sizeOk,
				`${ key }: ${ w.fs }/${ w.h }px fw${ w.fw } pad ${ w.pad } (مرجع ${ r.fs }/${ r.h }px fw${ r.fw } pad ${ r.pad })`
			);
		}

		/* متن‌ها: قیمت‌ها با جداکننده‌ی فارسی، تخفیف، معادل ماهانه و برچسب‌ها. */
		const refStrings = await strings( refPage, CARD.ref );
		const wpStrings = await strings( wpPage, CARD.wp );

		check( refStrings.dir === wpStrings.dir, `جهت شبکه: ${ wpStrings.dir }` );
		check(
			refStrings.cards.length === wpStrings.cards.length,
			`کارت نمونه در هر دو سمت پیدا شد (${ wpStrings.cards.length })`
		);

		refStrings.cards.forEach( ( rc, i ) => {
			const wc = wpStrings.cards[ i ] || {};

			check( rc.title === wc.title, `عنوان کارت ${ i + 1 }: ${ wc.title }` );
			check( rc.tag === wc.tag, `تگ‌لاین کارت ${ i + 1 }: ${ wc.tag }` );
			check( rc.del === wc.del, `قیمت پیشین کارت ${ i + 1}: ${ wc.del }` );
			check( rc.discount === wc.discount, `تخفیف کارت ${ i + 1}: ${ wc.discount }` );
			check( rc.price === wc.price, `قیمت کارت ${ i + 1}: ${ wc.price }` );
			check( rc.monthly === wc.monthly, `معادل ماهانه کارت ${ i + 1}: ${ wc.monthly }` );
			check( rc.li0 === wc.li0, `ویژگی اول کارت ${ i + 1}` );
			check( rc.li4 === wc.li4, `ویژگی پنجم کارت ${ i + 1}: ${ wc.li4 }` );
			check( rc.ribbon === wc.ribbon, `برچسب محبوب کارت ${ i + 1}: «${ wc.ribbon }»` );
			check( rc.x === wc.x, `جای کارت ${ i + 1} در شبکه: x${ wc.x } (مرجع x${ rc.x })` );
		} );

		/* اطلاعیه‌ی زیر جدول. */
		const refNote = await measure( refPage, DISCLAIMER.ref, {} );
		const wpNote = await measure( wpPage, DISCLAIMER.wp, {} );

		if ( refNote && wpNote ) {
			check(
				refNote.box.w === wpNote.box.w && refNote.box.h === wpNote.box.h && refNote.box.fs === wpNote.box.fs &&
					refNote.box.mt === wpNote.box.mt && refNote.box.gap === wpNote.box.gap && refNote.box.color === wpNote.box.color,
				`اطلاعیه‌ی قیمت: ${ wpNote.box.w }×${ wpNote.box.h } fs${ wpNote.box.fs } gap${ wpNote.box.gap } (مرجع ${ refNote.box.w }×${ refNote.box.h } fs${ refNote.box.fs } gap${ refNote.box.gap })`
			);
		} else {
			check( false, 'اطلاعیه‌ی قیمت در هر دو سمت پیدا شد' );
		}

		await ctx.close();
	}

	await browser.close();

	console.log( '' );
	console.log( '==========================================================' );
	console.log( `موفق: ${ pass }   ناموفق: ${ fail }   (مرجع: ${ REF })` );
	console.log( '==========================================================' );
	process.exit( fail ? 1 : 0 );
} )();

/**
 * آزمون هم‌سانی برگه‌ی «حساب کاربری» با مرجع «سینورا» + آزمون رفتار.
 *
 * لایه‌های سنجیده‌شده:
 *   ۱) هندسه و تایپوگرافی شش تب، هم‌زمان روی مرجع (`account.html`) و محصول
 *      (`/account/`): چیدمان ستون‌ها، ستون کنار، سرصفحه، کارت‌های آماری،
 *      بنر خوش‌آمد، حلقه‌ی ژانرها، سهم فرمت، نمودار هفته، کشورها،
 *      وضعیت اشتراک و حالت‌های خالی.
 *   ۲) رفتار: جابه‌جایی تب‌ها با جاوااسکریپت، وضعیت سرور با `?tab=` (با
 *      جاوااسکریپت خاموش)، برچسب‌ها و مقصدهای مهمان.
 *   ۳) کاربر وارد‌شده: نام/آواتار/خروج، وضعیت اشتراک واقعی، و **ذخیره‌ی
 *      واقعی** پروفایل (POST + PRG + پیام) و بازگشت مقدار.
 *   ۴) عملیات واقعی: دانلود JSON خروجی داده، بارگذاری و حذف عکس پروفایل.
 *   ۵) کاربر با داده‌ی واقعی (فیکسچر): شمارنده‌ها، تاریخچه و نوار پیشرفت،
 *      تحلیل‌ها (حلقه/سهم فرمت/نمودار هفته/کشورها)، پالایش لیست تماشا،
 *      مقادیر ذخیره‌شده‌ی تنظیمات و «پاک کردن تاریچه» با POST واقعی.
 *   ۶) دسترس‌پذیری: axe روی هر شش تب.
 *
 * پیش‌نیاز بخش ۹: `wp/tests/qa-env/seed-account.php` خودِ آزمون آن را اجرا
 * می‌کند (wp-cli)؛ اگر اجرا نشود، آزمون صریحاً ناموفق می‌شود — هرگز سنجشی
 * که اجرا نشده «موفق» گزارش نمی‌شود.
 *
 * اجرا:
 *   node account-parity.cjs
 *   WP_URL=http://localhost:8099/account/ node account-parity.cjs
 *   REF_URL=http://localhost:8098/account.html node account-parity.cjs
 *
 * @package KooheFilm
 */

'use strict';

let chromium;
let axePath = '';

const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

try {
	( { chromium } = require( 'playwright' ) );
} catch ( error ) {
	console.error( 'playwright نصب نیست؛ این آزمون اجرا نشد.' );
	console.error( error.message );
	process.exit( 2 );
}

try {
	axePath = require.resolve( 'axe-core' );
} catch ( error ) {
	axePath = '';
}

const WP_URL    = process.env.WP_URL || 'http://localhost:8099/account/';
const WP_ROOT   = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI    = process.env.WP_CLI || '/usr/local/bin/wp';
const SEED_FILE = path.join( __dirname, '..', 'qa-env', 'seed-account.php' );
const REF_URL = process.env.REF_URL || 'http://localhost:8098/account.html';

const TABS = [ 'overview', 'watchlist', 'history', 'analytics', 'subscription', 'settings' ];

let pass = 0;
let fail = 0;

/**
 * ثبت یک سنجش.
 *
 * @param {boolean} ok    نتیجه.
 * @param {string}  label برچسب.
 * @param {string}  detail جزئیات.
 */
function check( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label + ( detail ? '  → ' + detail : '' ) );
	}
}

/**
 * اختلاف مجاز.
 *
 * @param {number} a   مقدار اول.
 * @param {number} b   مقدار دوم.
 * @param {number} tol تلورانس.
 * @return {boolean} نتیجه.
 */
function near( a, b, tol ) {
	return Math.abs( a - b ) <= tol;
}

/**
 * اندازه‌های برگه‌ی حساب.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measure( page ) {
	return page.evaluate( () => {
		const box = ( sel ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { x: Math.round( r.x ), y: Math.round( r.y ), w: Math.round( r.width ), h: Math.round( r.height ) };
		};
		const style = ( sel, props ) => {
			const el = document.querySelector( sel );
			if ( ! el ) {
				return null;
			}
			const cs = getComputedStyle( el );
			const out = {};
			props.forEach( ( p ) => {
				out[ p ] = cs[ p ];
			} );
			return out;
		};
		const num = ( sel, props ) => {
			const computed = style( sel, props );
			if ( ! computed ) {
				return null;
			}
			const out = {};
			Object.keys( computed ).forEach( ( key ) => {
				out[ key ] = Math.round( parseFloat( computed[ key ] ) );
			} );
			return out;
		};
		const texts = ( sel ) => [ ...document.querySelectorAll( sel ) ].map( ( el ) => el.textContent.trim() );

		const tabs  = [ ...document.querySelectorAll( '[data-tab]' ) ];
		const cards = [ ...document.querySelectorAll( '.stat-card' ) ];

		return {
			has: {
				layout: !!document.querySelector( '.account-layout' ),
				sidebar: !!document.querySelector( '.account-sidebar' ),
				profile: !!document.querySelector( '.account-profile' ),
				avatar: !!document.querySelector( '.large-avatar' ),
				pill: !!document.querySelector( '.membership-pill' ),
				upgrade: !!document.querySelector( '.account-upgrade' ),
				greeting: !!document.querySelector( '.account-greeting' ),
				greetingButton: !!document.querySelector( '.account-greeting > .button' ),
				stats: !!document.querySelector( '.stat-grid' ),
				welcome: !!document.querySelector( '.account-welcome-banner' ),
				emptyState: !!document.querySelector( '.empty-state' ),
			},
			tabLabels: texts( '[data-tab]' ),
			tabBoxes: tabs.map( ( el ) => {
				const r = el.getBoundingClientRect();
				return { w: Math.round( r.width ), h: Math.round( r.height ) };
			} ),
			tabStyle: num( '[data-tab]', [ 'fontSize', 'paddingTop', 'paddingBottom', 'minHeight', 'borderRadius' ] ),
			boxes: {
				sidebar: box( '.account-sidebar' ),
				avatar: box( '.large-avatar' ),
				pill: box( '.membership-pill' ),
				upgrade: box( '.account-upgrade' ),
				greeting: box( '.account-greeting' ),
				h1: box( '.account-greeting h1' ),
				statGrid: box( '.stat-grid' ),
				statCard: box( '.stat-card' ),
				statIcon: box( '.stat-card > span' ),
				statValue: box( '.stat-card > strong' ),
				welcome: box( '.account-welcome-banner' ),
			},
			styles: {
				layout: style( '.account-layout', [ 'display', 'gridTemplateColumns', 'gap' ] ),
				h1: num( '.account-greeting h1', [ 'fontSize' ] ),
				muted: num( '.account-greeting .muted', [ 'fontSize' ] ),
				statGrid: num( '.stat-grid', [ 'gap' ] ),
				statCard: num( '.stat-card', [ 'paddingTop', 'minHeight', 'borderRadius' ] ),
				statValue: num( '.stat-card > strong', [ 'fontSize' ] ),
				statLabel: num( '.stat-card > p', [ 'fontSize' ] ),
				welcomeH2: num( '.account-welcome-banner h2', [ 'fontSize' ] ),
				welcomeP: num( '.account-welcome-banner p', [ 'fontSize' ] ),
				sidebar: num( '.account-sidebar', [ 'paddingTop', 'borderRadius' ] ),
				profile: num( '.account-profile', [ 'paddingBottom' ] ),
				sidebarNav: num( '.account-sidebar > nav', [ 'gap' ] ),
				avatarFont: num( '.large-avatar', [ 'fontSize' ] ),
				pill: num( '.membership-pill', [ 'fontSize' ] ),
				upgrade: num( '.account-upgrade', [ 'paddingTop', 'borderRadius', 'gap' ] ),
			},
			stats: cards.map( ( card ) => ( {
				icon: card.querySelector( 'span' ) ? card.querySelector( 'span' ).textContent.trim() : '',
				value: card.querySelector( 'strong' ) ? card.querySelector( 'strong' ).textContent.trim() : '',
				label: card.querySelector( 'p' ) ? card.querySelector( 'p' ).textContent.trim() : '',
				h: Math.round( card.getBoundingClientRect().height ),
			} ) ),
		};
	} );
}

/**
 * اندازه‌های یک تب (پس از کلیک روی ردیف تب).
 *
 * @param {import('playwright').Page} page صفحه.
 * @param {string}                    tab  شناسه‌ی تب.
 * @return {Promise<Object>} اندازه‌ها.
 */
async function measureTab( page, tab ) {
	await page.click( `[data-tab="${ tab }"]` );
	await page.waitForTimeout( 120 );

	return page.evaluate( () => {
		const panel = document.querySelector( '[data-panel]:not([hidden])' );
		const box   = ( sel ) => {
			const el = panel ? panel.querySelector( sel ) : null;
			if ( ! el ) {
				return null;
			}
			const r = el.getBoundingClientRect();
			return { w: Math.round( r.width ), h: Math.round( r.height ) };
		};
		const style = ( sel, props ) => {
			const el = panel ? panel.querySelector( sel ) : null;
			if ( ! el ) {
				return null;
			}
			const cs  = getComputedStyle( el );
			const out = {};
			props.forEach( ( p ) => {
				out[ p ] = /^-?\d/.test( cs[ p ] ) ? Math.round( parseFloat( cs[ p ] ) ) : cs[ p ];
			} );
			return out;
		};

		return {
			tab: document.querySelector( '.account-sidebar nav > .active, .account-sidebar nav > [aria-current]' )
				? document.querySelector( '.account-sidebar nav > .active, .account-sidebar nav > [aria-current]' ).textContent.trim()
				: '',
			hiddenPanels: document.querySelectorAll( '[data-panel][hidden]' ).length,
			has: {
				grid: !!document.querySelector( '.manacore-grid-cards, .media-grid' ),
				empty: !!( panel && panel.querySelector( '.empty-state' ) ),
				headings: panel ? panel.querySelectorAll( '.section-heading h2' ).length : 0,
				analytics: !!( panel && panel.querySelector( '.analytics-grid' ) ),
				history: !!( panel && panel.querySelector( '.history-list' ) ),
				invoices: !!( panel && panel.querySelector( '.invoice-table' ) ),
				settings: !!( panel && panel.querySelector( '.settings-card' ) ),
			},
			boxes: {
				grid: box( '.manacore-grid-cards, .media-grid' ),
				empty: box( '.empty-state' ),
				heading: box( '.section-heading h2' ),
				analyticsGrid: box( '.analytics-grid' ),
				analyticsCard: box( '.analytics-card' ),
				donut: box( '.donut-chart' ),
				formatIcon: box( '.format-stat > span' ),
				barChart: box( '.bar-chart' ),
				statusCard: box( '.subscription-status-card' ),
				invoiceTable: box( '.invoice-table' ),
				historyList: box( '.history-list' ),
				settingsCard: box( '.settings-card' ),
			},
			styles: {
				grid: style( '.manacore-grid-cards, .media-grid', [ 'gridTemplateColumns', 'gap' ] ),
				heading: style( '.section-heading h2', [ 'fontSize' ] ),
				headingP: style( '.section-heading p', [ 'fontSize' ] ),
				emptyMin: style( '.empty-state', [ 'minHeight' ] ),
				analyticsGrid: style( '.analytics-grid', [ 'gridTemplateColumns', 'gap' ] ),
				analyticsCard: style( '.analytics-card', [ 'paddingTop' ] ),
				analyticsH3: style( '.analytics-card > h3', [ 'fontSize' ] ),
				analyticsP: style( '.analytics-card > p', [ 'fontSize' ] ),
				donut: style( '.donut-chart', [ 'width', 'height', 'paddingTop' ] ),
				formatStat: style( '.format-stat', [ 'gap' ] ),
				formatIcon: style( '.format-stat > span', [ 'width', 'height' ] ),
				barChart: style( '.bar-chart', [ 'minHeight' ] ),
				countryMuted: style( '.country-bars > .muted', [ 'fontSize', 'marginTop' ] ),
				privacy: style( '.analytics-privacy', [ 'fontSize' ] ),
				statusCard: style( '.subscription-status-card', [ 'paddingTop', 'gap', 'borderRadius' ] ),
				statusH2: style( '.subscription-status-card h2', [ 'fontSize' ] ),
				statusP: style( '.subscription-status-card p:not(.eyebrow)', [ 'fontSize' ] ),
				historyList: style( '.history-list', [ 'gap' ] ),
				settingsCard: style( '.settings-card', [ 'paddingTop', 'borderRadius' ] ),
			},
		};
	} );
}

/**
 * ورود با حساب مدیر.
 *
 * @param {import('playwright').Page} page صفحه.
 * @return {Promise<boolean>} نتیجه.
 */
async function login( page ) {
	await page.goto( 'http://localhost:8099/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );

	return await page.evaluate( () => document.body.classList.contains( 'logged-in' ) || !!document.querySelector( '#wpadminbar' ) );
}

/**
 * اجرای آزمون.
 */
async function main() {
	const browser = await chromium.launch();
	const context = await browser.newContext( { viewport: { width: 1440, height: 1000 } } );
	const page    = await context.newPage();
	const errors  = [];

	page.on( 'pageerror', ( error ) => errors.push( String( error ) ) );

	console.log( '\n== ۱) مهمان: هم‌سانی هندسه با مرجع (۱۴۴۰px) ==' );

	await page.goto( REF_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 250 );
	const ref = await measure( page );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 250 );
	const wp = await measure( page );

	check( wp.has.layout && ref.has.layout, 'چیدمان دوستونه‌ی حساب (`.account-layout`)' );
	check( wp.has.sidebar && ref.has.sidebar, 'ستون کنار (`.account-sidebar`)' );
	check( wp.has.profile && ref.has.profile, 'کارت پروفایل (`.account-profile`)' );
	check( wp.has.avatar && ref.has.avatar, 'آواتار بزرگ (`.large-avatar`)' );
	check( wp.has.pill && ref.has.pill, 'نشان عضویت (`.membership-pill`)' );
	check( wp.has.upgrade && ref.has.upgrade, 'کارت ارتقا (`.account-upgrade`)' );
	check( wp.has.greeting && ref.has.greeting, 'سرصفحه‌ی خوش‌آمد (`.account-greeting`)' );
	check( wp.has.stats && ref.has.stats, 'شبکه‌ی شمارنده‌ها (`.stat-grid`)' );
	check( wp.has.welcome && ref.has.welcome, 'بنر «سلیقه‌ات خاص است» (`.account-welcome-banner`)' );

	check(
		JSON.stringify( wp.tabLabels ) === JSON.stringify( ref.tabLabels ),
		'برچسب شش تب ستون کنار یکسان است',
		wp.tabLabels.join( ' | ' ) + ' ≠ ' + ref.tabLabels.join( ' | ' )
	);

	check(
		wp.styles.layout.display === ref.styles.layout.display &&
			wp.styles.layout.gridTemplateColumns === ref.styles.layout.gridTemplateColumns &&
			near( parseFloat( wp.styles.layout.gap ), parseFloat( ref.styles.layout.gap ), 0.5 ),
		'شبکه‌ی `230px + 1fr` و فاصله‌ی ۳۰px',
		wp.styles.layout.gridTemplateColumns + ' / ' + wp.styles.layout.gap
	);

	const compare = ( label, ours, theirs, tol ) => {
		check(
			ours !== null && theirs !== null && near( parseFloat( ours ), parseFloat( theirs ), tol || 0.5 ),
			label,
			ours + ' ≠ ' + theirs
		);
	};

	compare( 'اندازه‌ی h1 خوش‌آمد (۳۰px)', wp.styles.h1.fontSize, ref.styles.h1.fontSize );
	compare( 'اندازه‌ی ریزسطر خوش‌آمد', wp.styles.muted.fontSize, ref.styles.muted.fontSize );
	compare( 'اندازه‌ی کارت آماری (۱۴۹px کمینه)', wp.boxes.statCard.h, ref.boxes.statCard.h, 1.5 );
	compare( 'فاصله‌ی شبکه‌ی شمارنده‌ها (۱۶px)', wp.styles.statGrid.gap, ref.styles.statGrid.gap );
	compare( 'اندازه‌ی عدد کارت آماری (۲۶px)', wp.styles.statValue.fontSize, ref.styles.statValue.fontSize );
	compare( 'اندازه‌ی برچسب کارت آماری', wp.styles.statLabel.fontSize, ref.styles.statLabel.fontSize );
	compare( 'نشانه‌ی گوشه‌ی کارت آماری ۳۰×۳۰', wp.boxes.statIcon.w + 'x' + wp.boxes.statIcon.h, ref.boxes.statIcon.w + 'x' + ref.boxes.statIcon.h, 0 );
	compare( 'آواتار بزرگ ۷۲×۷۲', wp.boxes.avatar.w + 'x' + wp.boxes.avatar.h, ref.boxes.avatar.w + 'x' + ref.boxes.avatar.h, 0 );
	compare( 'برچسب عضویت', wp.boxes.pill.h, ref.boxes.pill.h, 1 );
	compare( 'کارت ارتقا (padding/radius)', wp.styles.upgrade.paddingTop + '|' + wp.styles.upgrade.borderRadius, ref.styles.upgrade.paddingTop + '|' + ref.styles.upgrade.borderRadius, 0 );
	compare( 'ارتفاع بنر خوش‌آمد', wp.boxes.welcome.h, ref.boxes.welcome.h, 2 );
	compare( 'عنوان بنر خوش‌آمد', wp.styles.welcomeH2.fontSize, ref.styles.welcomeH2.fontSize );
	compare( 'ارتفاع ردیف تب ستون کنار', wp.tabBoxes[ 0 ] && wp.tabBoxes[ 0 ].h, ref.tabBoxes[ 0 ] && ref.tabBoxes[ 0 ].h, 1 );
	compare( 'اندازه‌ی متن ردیف تب', wp.tabStyle.fontSize, ref.tabStyle.fontSize );
	compare( 'فاصله‌ی عمودی ستون کنار', wp.styles.sidebarNav.gap, ref.styles.sidebarNav.gap, 0 );

	check(
		wp.stats.length === ref.stats.length &&
			wp.stats.every( ( card, index ) => card.icon === ref.stats[ index ].icon && card.value === ref.stats[ index ].value ),
		'چهار کارت آماری با همان نشانه/مقدار مهمان (۰/◉/◷/هنوز کشف نشده)',
		JSON.stringify( wp.stats.map( ( s ) => s.icon + s.value ) )
	);

	console.log( '\n== ۲) مهمان: هندسه‌ی هر تب ==' );

	for ( const tab of TABS ) {
		const wpTab  = await measureTab( page, tab );
		const refTab = await measureTab( page, tab );

		check(
			wpTab.hiddenPanels === 5 && refTab.hiddenPanels === 5,
			'تب «' + tab + '»: تنها یک پنل باز است',
			wpTab.hiddenPanels + ' / ' + refTab.hiddenPanels
		);

		if ( 'settings' === tab ) {
			check( wpTab.has.settings, 'تب تنظیمات برای مهمان دعوت به ورود دارد (سرور)', '' );
			continue;
		}

		if ( wpTab.boxes.heading && refTab.boxes.heading ) {
			check(
				wpTab.styles.heading.fontSize === refTab.styles.heading.fontSize,
				'تب «' + tab + '»: اندازه‌ی سرتیتر بخش (' + wpTab.styles.heading.fontSize + 'px)',
				wpTab.styles.heading.fontSize + ' ≠ ' + refTab.styles.heading.fontSize
			);
		}

		if ( wpTab.styles.grid && refTab.styles.grid ) {
			check(
				wpTab.styles.grid.gridTemplateColumns === refTab.styles.grid.gridTemplateColumns &&
					wpTab.styles.grid.gap === refTab.styles.grid.gap,
				'تب «' + tab + '»: شبکه‌ی چهارستونی با فاصله‌ی ۱۸px',
				wpTab.styles.grid.gridTemplateColumns + ' / ' + wpTab.styles.grid.gap
			);
		}

		if ( wpTab.styles.emptyMin && refTab.styles.emptyMin ) {
			check(
				near( wpTab.styles.emptyMin.minHeight, refTab.styles.emptyMin.minHeight, 4 ),
				'تب «' + tab + '»: کمینه‌ی ارتفاع حالت خالی',
				wpTab.styles.emptyMin.minHeight + ' ≠ ' + refTab.styles.emptyMin.minHeight
			);
		}

		if ( 'analytics' === tab ) {
			check(
				wpTab.styles.analyticsGrid.gridTemplateColumns === refTab.styles.analyticsGrid.gridTemplateColumns &&
					wpTab.styles.analyticsGrid.gap === refTab.styles.analyticsGrid.gap,
				'تب تحلیل: شبکه‌ی `1.2fr 1fr` با فاصله‌ی ۲۰px',
				wpTab.styles.analyticsGrid.gridTemplateColumns
			);
			check(
				wpTab.styles.donut.width === refTab.styles.donut.width && wpTab.styles.donut.height === refTab.styles.donut.height,
				'تب تحلیل: حلقه‌ی ژانرها ۱۴۹×۱۴۹',
				wpTab.styles.donut.width + ' ≠ ' + refTab.styles.donut.width
			);
			check(
				wpTab.styles.formatIcon.width === refTab.styles.formatIcon.width,
				'تب تحلیل: نشانه‌ی سهم فرمت ۳۷×۳۷',
				wpTab.styles.formatIcon.width + ' ≠ ' + refTab.styles.formatIcon.width
			);
			check(
				wpTab.styles.barChart.minHeight === refTab.styles.barChart.minHeight,
				'تب تحلیل: کمینه‌ی نمودار هفته ۱۶۲px',
				wpTab.styles.barChart.minHeight + ' ≠ ' + refTab.styles.barChart.minHeight
			);
			check(
				wpTab.styles.countryMuted.marginTop === refTab.styles.countryMuted.marginTop,
				'تب تحلیل: متن خالی کشورها با فاصله‌ی مرجع',
				wpTab.styles.countryMuted.marginTop + ' ≠ ' + refTab.styles.countryMuted.marginTop
			);
			check(
				wpTab.styles.privacy.fontSize === refTab.styles.privacy.fontSize,
				'تب تحلیل: اندازه‌ی یادداشت حریم خصوصی',
				wpTab.styles.privacy.fontSize + ' ≠ ' + refTab.styles.privacy.fontSize
			);
		}

		if ( 'subscription' === tab ) {
			check(
				wpTab.styles.statusCard.paddingTop === refTab.styles.statusCard.paddingTop &&
					wpTab.styles.statusCard.borderRadius === refTab.styles.statusCard.borderRadius,
				'تب اشتراک: کارت وضعیت (padding/radius)',
				wpTab.styles.statusCard.paddingTop + ' ≠ ' + refTab.styles.statusCard.paddingTop
			);
			check(
				wpTab.styles.statusH2.fontSize === refTab.styles.statusH2.fontSize,
				'تب اشتراک: اندازه‌ی عنوان کارت وضعیت',
				wpTab.styles.statusH2.fontSize + ' ≠ ' + refTab.styles.statusH2.fontSize
			);
		}

		if ( 'history' === tab ) {
			check(
				wpTab.styles.historyList.gap === refTab.styles.historyList.gap,
				'تب تاریخچه: فاصله‌ی ردیف‌های تاریخچه',
				wpTab.styles.historyList.gap + ' ≠ ' + refTab.styles.historyList.gap
			);
		}
	}

	console.log( '\n== ۳) مهمان: برچسب‌ها و مقصدها ==' );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );

	const guest = await page.evaluate( () => ( {
		heading: document.querySelector( '#account-greeting-title' ).textContent.trim(),
		button: document.querySelector( '.account-greeting > .button' ).textContent.trim(),
		buttonHref: document.querySelector( '.account-greeting > .button' ).getAttribute( 'href' ),
		badge: document.querySelector( '#watch-count' ) ? document.querySelector( '#watch-count' ).textContent.trim() : '',
		avatar: document.querySelector( '.large-avatar' ).textContent.trim(),
		name: document.querySelector( '#account-name' ).textContent.trim(),
		email: document.querySelector( '#account-email' ).textContent.trim(),
		logout: !!document.querySelector( '.account-logout' ),
	} ) );

	check( guest.heading === 'مهمان عزیز، اینجا دنیای توست.', 'عنوان مهمان مطابق مرجع', guest.heading );
	check( guest.button === 'ساخت حساب', 'دکمه‌ی مهمان «ساخت حساب»', guest.button );
	check( guest.buttonHref.indexOf( 'action=register' ) !== -1, 'دکمه‌ی مهمان به ثبت‌نام می‌رود', guest.buttonHref );
	check( guest.badge === '۰', 'نشان لیست تماشا برای مهمان «۰» است', guest.badge );
	check( guest.avatar === '◯', 'آواتار مهمان «◯» است', guest.avatar );
	check( guest.name === 'مهمان کوهه‌فیلم', 'نام مهمان', guest.name );
	check( guest.email === 'Your next story starts here', 'ریزسطر ایمیل مهمان مطابق مرجع', guest.email );
	check( ! guest.logout, 'مهمان دکمه‌ی خروج ندارد' );

	console.log( '\n== ۴) جاوااسکریپت: جابه‌جایی تب‌ها و نشانی ==' );
	await page.click( '[data-tab="analytics"]' );
	await page.waitForTimeout( 120 );
	const afterClick = await page.evaluate( () => ( {
		active: document.querySelector( '[data-panel]:not([hidden])' ).getAttribute( 'data-panel' ),
		url: window.location.search,
		pressed: document.querySelector( '[data-tab="analytics"]' ).getAttribute( 'aria-current' ),
	} ) );
	check( afterClick.active === 'analytics', 'کلیک روی تب، پنل را عوض می‌کند', afterClick.active );
	check( afterClick.url.indexOf( 'tab=analytics' ) !== -1, 'نشانی با `?tab=` به‌روز می‌شود (بدون بازخوانی)', afterClick.url );
	check( afterClick.pressed === 'true', 'ردیف فعال با `aria-current` نشانه می‌خورد' );

	console.log( '\n== ۵) بدون جاوااسکریپت: تب از سرور ==' );
	const noJs = await browser.newContext( { viewport: { width: 1440, height: 1000 }, javaScriptEnabled: false } );
	const noJsPage = await noJs.newPage();
	await noJsPage.goto( WP_URL + '?tab=history', { waitUntil: 'domcontentloaded' } );
	const noJsView = await noJsPage.evaluate( () => ( {
		open: document.querySelector( '[data-panel]:not([hidden])' ).getAttribute( 'data-panel' ),
		all: document.querySelectorAll( '[data-panel]' ).length,
	} ) );
	check( noJsView.open === 'history', 'با `?tab=history` سرور همان تب را باز می‌کند', noJsView.open );
	check( noJsView.all === 6, 'هر شش پنل در HTML هستند', String( noJsView.all ) );
	await noJs.close();

	console.log( '\n== ۶) کاربر وارد‌شده: پروفایل، خروج و ذخیره‌ی واقعی ==' );
	const loggedIn = await login( page );
	check( loggedIn, 'ورود با حساب مدیر' );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	const member = await page.evaluate( () => ( {
		name: document.querySelector( '#account-name' ).textContent.trim(),
		heading: document.querySelector( '#account-greeting-title' ).textContent.trim(),
		logout: !!document.querySelector( '.account-logout' ),
		logoutHref: document.querySelector( '.account-logout' ) ? document.querySelector( '.account-logout' ).getAttribute( 'href' ) : '',
		button: document.querySelector( '.account-greeting > .button' ).textContent.trim(),
		avatar: !!document.querySelector( '.large-avatar img, .large-avatar > img' ),
		initial: document.querySelector( '.account-sidebar .large-avatar' ).textContent.trim(),
		upgrade: !!document.querySelector( '.account-upgrade' ),
	} ) );
	check( member.name === 'admin', 'نام نمایشی واقعی کاربر در ستون کنار', member.name );
	check( member.heading.indexOf( 'admin' ) === 0, 'عنوان با نام کاربر ساخته می‌شود', member.heading );
	check( member.logout && member.logoutHref.indexOf( 'action=logout' ) !== -1, 'دکمه‌ی «خروج از حساب» با نشانی واقعی خروج', member.logoutHref );
	check( member.button === 'ویرایش پروفایل', 'دکمه‌ی سرصفحه برای کاربر وارد‌شده', member.button );
	check(
		member.avatar || member.initial.toLowerCase() === member.name.slice( 0, 1 ).toLowerCase(),
		'آواتار کاربر مثل مرجع است: عکس بارگذاری‌شده، وگرنه حرف اول نام',
		member.avatar ? 'عکس' : member.initial
	);
	check( ! member.upgrade, 'برای کاربر وارد‌شده کارت ارتقا پنهان است' );

	await page.click( '[data-tab="settings"]' );
	await page.waitForTimeout( 120 );
	const form = await page.evaluate( () => ( {
		cards: document.querySelectorAll( '[data-panel="settings"] .settings-card' ).length,
		name: document.querySelector( 'input[name="display_name"]' ).value,
		nonce: !!document.querySelector( 'input[name="manacore_nonce"]' ),
		forms: document.querySelectorAll( '[data-panel="settings"] form' ).length,
		export: ( document.querySelector( '.settings-export a' ) || {} ).getAttribute
			? document.querySelector( '.settings-export a' ).getAttribute( 'href' )
			: '',
	} ) );
	check( form.cards === 3, 'تب تنظیمات سه کارت مرجع را دارد (پروفایل/امنیت/خروجی)', String( form.cards ) );
	check( form.name === 'admin' && form.nonce, 'مقدار واقعی نام و نانس امنیتی در فرم', form.name + ' / ' + form.nonce );
	/*
	 * مرجع دو فرم دارد (پروفایل و رمز) و دانلود داده را با دکمه‌ی
	 * جاوااسکریپتی انجام می‌دهد. در محصول، بارگذاری عکس فرم جداگانه‌ی
	 * خودش را دارد (چون فرم تودرتو در HTML مجاز نیست) و دانلود داده به‌جای
	 * دکمه‌ی فقط-جاوااسکریپت، **پیوند نانس‌دار سروری** است تا بدون
	 * جاوااسکریپت هم کار کند؛ پس معیار درست «سه فرم + پیوند واقعی» است،
	 * نه چهار فرم.
	 */
	check( form.forms === 3, 'سه فرم واقعی (عکس، پروفایل، رمز)', String( form.forms ) );
	check(
		/manacore_export=1/.test( form.export ) && /_wpnonce=/.test( form.export ),
		'خروجی داده پیوند نانس‌دار سروری است (بدون فرم ساختگی)',
		form.export
	);

	await page.fill( 'input[name="display_name"]', 'admin' );
	const [ saveResponse ] = await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '[data-panel="settings"] button[type="submit"].button.primary' ),
	] );
	const saved = await page.evaluate( () => ( {
		url: window.location.search,
		toast: document.querySelector( '.manacore-toast' ) ? document.querySelector( '.manacore-toast' ).textContent.trim() : '',
		expected: ( ( window.manaCore || {} ).i18n || {} ).account ? window.manaCore.i18n.account.saved : '',
		name: document.querySelector( '#account-name' ).textContent.trim(),
	} ) );

	/*
	 * جاوااسکریپت بعد از نمایش پیام، `notice` را از نشانی برمی‌دارد
	 * (همان کاری که مرجع می‌کند)؛ پس گواه سرور، **نشانی خودِ پاسخ
	 * ناوبری** است، نه نشانی پس از اجرای اسکریپت.
	 */
	const responseUrl = saveResponse ? saveResponse.url() : '';
	check(
		responseUrl.indexOf( 'notice=saved' ) !== -1,
		'ذخیره‌ی پروفایل با POST/PRG برمی‌گردد (`notice=saved`)',
		responseUrl || saved.url
	);
	check(
		'' !== saved.toast && saved.toast === saved.expected,
		'پیام موفقیت به‌شکل toast با متن مرجع نمایش داده می‌شود',
		saved.toast + ' / ' + saved.expected
	);
	check( saved.name === 'admin', 'نام پس از ذخیره درست است', saved.name );

	console.log( '\n== ۷) عملیات واقعی: خروجی داده و عکس پروفایل ==' );

	const exportHref = await page.evaluate( () => document.querySelector( '.settings-export a' ).getAttribute( 'href' ) );
	const exported   = await context.request.get( exportHref );
	let exportJson   = null;
	try {
		exportJson = JSON.parse( await exported.text() );
	} catch ( error ) {
		exportJson = null;
	}
	check( exported.status() === 200, 'خروجی داده با کد ۲۰۰ می‌آید', String( exported.status() ) );
	check(
		/json/.test( exported.headers()[ 'content-type' ] || '' ),
		'نوع پاسخ خروجی JSON است',
		exported.headers()[ 'content-type' ]
	);
	check(
		!! exportJson && Array.isArray( exportJson.watchlist ) && !! exportJson.profile,
		'خروجی شامل پروفایل و لیست تماشای واقعی است',
		exportJson ? Object.keys( exportJson ).join( ',' ) : 'JSON نامعتبر'
	);

	const beforeAvatar = await page.evaluate( () => ( {
		remove: !! document.querySelector( '.settings-avatar .danger' ),
		img: !! document.querySelector( '.account-sidebar .large-avatar img' ),
	} ) );

	await page.setInputFiles( '#avatar-file', path.join( __dirname, 'fixtures', 'avatar-64.png' ) );
	await page.waitForTimeout( 600 );
	await page.goto( WP_URL + '?tab=settings', { waitUntil: 'domcontentloaded' } );

	const afterAvatar = await page.evaluate( () => ( {
		img: !! document.querySelector( '.account-sidebar .large-avatar img' ),
		src: document.querySelector( '.account-sidebar .large-avatar img' )
			? document.querySelector( '.account-sidebar .large-avatar img' ).getAttribute( 'src' )
			: '',
		remove: !! document.querySelector( '.settings-avatar .danger' ),
		settingsImg: !! document.querySelector( '.settings-avatar .large-avatar img' ),
	} ) );
	check(
		afterAvatar.img && /wp-content\/uploads/.test( afterAvatar.src ),
		'بارگذاری عکس پروفایل واقعاً ذخیره و رندر می‌شود',
		afterAvatar.src
	);
	check( afterAvatar.remove, 'پس از بارگذاری، دکمه‌ی «حذف عکس» ظاهر می‌شود' );

	if ( afterAvatar.remove ) {
		const [ removeResponse ] = await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
			page.click( '.settings-avatar .danger' ),
		] );
		const removed = await page.evaluate( () => ( {
			img: !! document.querySelector( '.account-sidebar .large-avatar img' ),
			placeholder: document.querySelector( '.account-sidebar .large-avatar' ).textContent.trim().toLowerCase(),
			// مرجع پس از حذف عکس، حرف اول نام نمایشی را نشان می‌دهد (نه «◯»).
			initial: document.querySelector( '#account-name' ).textContent.trim().slice( 0, 1 ).toLowerCase(),
		} ) );
		check(
			( removeResponse ? removeResponse.url() : '' ).indexOf( 'notice=avatar-removed' ) !== -1,
			'حذف عکس پروفایل با PRG برمی‌گردد',
			removeResponse ? removeResponse.url() : ''
		);
		check(
			! removed.img && '' !== removed.placeholder && removed.placeholder === removed.initial,
			'پس از حذف عکس، حرف اول نام نمایشی برمی‌گردد (رفتار مرجع)',
			removed.placeholder + ' / ' + removed.initial
		);
	} else {
		check( false, 'حذف عکس پروفایل اجرا نشد (دکمه در دسترس نبود)', JSON.stringify( beforeAvatar ) );
	}


	console.log( '\n== ۹) کاربر با داده‌ی واقعی: شمارنده‌ها، تاریخچه و تحلیل ==' );

	/*
	 * کاشت فیکسچر با همان API خودِ افزونه. اگر wp-cli نباشد، آزمون
	 * صریحاً ناموفق می‌شود و هرگز «سبز» گزارش نمی‌شود.
	 */
	let seeded = false;
	let seedLog = '';

	try {
		seedLog = execFileSync(
			'php',
			[ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE ],
			{ encoding: 'utf8' }
		).trim();
		seeded = /account seed ok/.test( seedLog );
	} catch ( error ) {
		seedLog = String( error.stderr || error.message || error ).slice( 0, 200 );
	}

	check( seeded, 'کاشت داده‌ی واقعی برای کاربر مدیر (seed-account.php)', seedLog );

	await page.goto( WP_URL, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 200 );

	const filled = await page.evaluate( () => {
		const panel = document.querySelector( '[data-panel="overview"]' );
		const cards = [ ...panel.querySelectorAll( '.stat-card' ) ];
		const txt   = ( el ) => ( el ? el.textContent.trim() : '' );

		return {
			stats: cards.map( ( c ) => ( { icon: txt( c.querySelector( 'span' ) ), value: txt( c.querySelector( 'strong' ) ), label: txt( c.querySelector( 'p' ) ) } ) ),
			welcomeTitle: txt( panel.querySelector( '.account-welcome-banner h2' ) ),
			welcomeCopy: txt( panel.querySelector( '.account-welcome-banner p' ) ),
			sections: panel.querySelectorAll( '.account-section' ).length,
			previewCards: panel.querySelectorAll( '.manacore-card, .media-card' ).length,
		};
	} );

	const digits = ( n ) => String( n ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ Number( d ) ] );

	check( 4 === filled.stats.length, 'چهار شمارنده رندر می‌شود', String( filled.stats.length ) );
	check(
		filled.stats[ 0 ].value === digits( 5 ) && filled.stats[ 1 ].value === digits( 1 ) && filled.stats[ 2 ].value === digits( 290 ),
		'شمارنده‌ها از داده‌ی واقعی می‌آیند (۵ تماشا · ۱ تمام‌شده · ۲۹۰ دقیقه)',
		filled.stats.map( ( s ) => s.value ).join( ' | ' )
	);
	check(
		filled.stats.every( ( s ) => '' !== s.icon && '' !== s.label ),
		'هر شمارنده نشانه و برچسب دارد',
		filled.stats.map( ( s ) => s.icon + ' ' + s.label ).join( ' | ' )
	);
	check( filled.sections >= 2, 'تب «دنیای من» دو بخش فهرست (تماشا و پیشنهاد) دارد', String( filled.sections ) );
	check( filled.previewCards >= 4, 'بخش‌های فهرست کارت واقعی رندر می‌کنند', String( filled.previewCards ) );
	check(
		filled.welcomeTitle !== 'سلیقه‌ات به اندازه خودت، خاص است.',
		'بنر خوش‌آمد با سلیقه‌ی واقعی کاربر متن را عوض می‌کند',
		filled.welcomeTitle
	);
	check(
		filled.welcomeTitle.indexOf( filled.stats[ 3 ].value ) !== -1,
		'ژانر بنر با ژانر کارت «ژانر موردعلاقه» یکی است',
		filled.welcomeTitle + ' / ' + filled.stats[ 3 ].value
	);

	/* ── تاریخچه تماشا: نوار پیشرفت واقعی ── */
	await page.click( '[data-tab="history"]' );
	await page.waitForTimeout( 200 );

	const history = await page.evaluate( () => {
		const items = [ ...document.querySelectorAll( '[data-panel="history"] .history-item' ) ];
		return {
			count: items.length,
			widths: items.map( ( i ) => i.querySelector( '.history-progress > span' ).style.width ),
			notes: items.map( ( i ) => i.querySelector( 'small' ).textContent.trim() ),
			resume: items.every( ( i ) => /manacore_id=\d+/.test( i.querySelector( 'a.button' ).getAttribute( 'href' ) ) ),
			posts: items.every( ( i ) => !! i.querySelector( 'img' ) ),
		};
	} );

	check( 4 === history.count, 'چهار ردیف تاریخچه از داده‌ی واقعی', String( history.count ) );
	check(
		JSON.stringify( history.widths.slice().sort() ) === JSON.stringify( [ '100%', '12%', '42%', '88%' ] ),
		'نوار پیشرفت، همان درصدهای ثبت‌شده را نشان می‌دهد',
		JSON.stringify( history.widths )
	);
	check(
		history.notes.filter( ( n ) => /کامل شده/.test( n ) ).length === 1,
		'فقط اثر ۱۰۰٪ «تماشای نمونه کامل شده» می‌گیرد (قرارداد ≥۹۵٪ مرجع)',
		JSON.stringify( history.notes )
	);
	check( history.resume, 'هر ردیف به پخش همان اثر پیوند دارد' );
	check( history.posts, 'هر ردیف پوستر واقعی اثر را دارد' );

	/* ── تحلیل‌ها: حلقه، سهم فرمت، نمودار هفته، کشورها ── */
	await page.click( '[data-tab="analytics"]' );
	await page.waitForTimeout( 200 );

	const analytics = await page.evaluate( () => {
		const panel  = document.querySelector( '[data-panel="analytics"]' );
		const legend = [ ...panel.querySelectorAll( '.chart-legend > div' ) ];
		const formats = [ ...panel.querySelectorAll( '.format-stat' ) ];
		const bars   = [ ...panel.querySelectorAll( '.bar-chart > div' ) ];

		return {
			legend: legend.map( ( row ) => ( {
				genre: row.querySelector( 'p' ).textContent.trim(),
				pct: parseInt( row.querySelector( 'b' ).textContent.replace( /[^\d۰-۹]/g, '' ).replace( /[۰-۹]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ), 10 ),
				color: row.querySelector( 'span' ).style.background || getComputedStyle( row.querySelector( 'span' ) ).backgroundColor,
			} ) ),
			donut: getComputedStyle( panel.querySelector( '.donut-chart' ) ).backgroundImage,
			donutTitle: panel.querySelector( '.donut-chart strong' ).textContent.trim(),
			donutPct: panel.querySelector( '.donut-chart small' ).textContent.trim(),
			formats: formats.map( ( f ) => ( {
				name: f.querySelector( 'strong' ).textContent.trim(),
				pct: f.querySelector( 'b' ).textContent.trim(),
				bar: f.nextElementSibling ? f.nextElementSibling.querySelector( 'span' ).style.width : '',
			} ) ),
			bars: bars.length,
			tallest: Math.max( ...bars.map( ( b ) => parseInt( b.querySelector( 'i' ).style.height, 10 ) || 0 ) ),
			shortest: Math.min( ...bars.map( ( b ) => parseInt( b.querySelector( 'i' ).style.height, 10 ) || 0 ) ),
			countries: [ ...panel.querySelectorAll( '.country-bars > div' ) ].map( ( r ) => ( {
				name: r.querySelector( 'span' ).childNodes[ 0 ].textContent.trim(),
				count: r.querySelector( 'b' ).textContent.trim(),
				width: r.querySelector( 'i > span' ).style.width,
			} ) ),
			privacy: panel.querySelector( '.analytics-privacy' ).textContent.trim().slice( 0, 12 ),
			recCards: panel.querySelectorAll( '.manacore-section-title' ).length,
		};
	} );

	check( analytics.legend.length >= 4, 'حلقه‌ی ژانرها ردیف‌های واقعی دارد', String( analytics.legend.length ) );
	/*
	 * رنگ‌های حلقه: پنج رنگ ثابت مرجع (`#c7ef76 #9dbeef #eaba7f #c1a2ec #69c7b5`)
	 * که یک‌به‌یک تکرار می‌شوند — همان نگاشت `colors[i % length]` مرجع.
	 */
	const REF_COLORS = [ 'rgb(199, 239, 118)', 'rgb(157, 190, 239)', 'rgb(234, 186, 127)', 'rgb(193, 162, 236)', 'rgb(105, 199, 181)' ];
	check(
		analytics.legend.every( ( r ) => REF_COLORS.includes( r.color ) ) &&
			analytics.legend.some( ( r, i ) => i >= REF_COLORS.length && r.color === analytics.legend[ i - REF_COLORS.length ].color ),
		'رنگ ردیف‌های حلقه همان نگاشت چرخشی پنج‌رنگ مرجع است',
		analytics.legend.map( ( r ) => r.color ).join( ' | ' )
	);

	/*
	 * مرجع درصد هر ژانر را جداگانه گرد می‌کند (`Math.round(n / total * 100)`)،
	 * پس جمعشان می‌تواند ۹۹ یا ۱۰۱ شود؛ قرارداد درست همان «±۱ حول ۱۰۰» است.
	 */
	const legendSum = analytics.legend.reduce( ( sum, r ) => sum + r.pct, 0 );
	check(
		Math.abs( legendSum - 100 ) <= 1,
		'جمع درصدهای حلقه با گردکردن مرجع همراستاست (۱۰۰ ± ۱)',
		String( legendSum )
	);
	check( /conic-gradient/.test( analytics.donut ), 'حلقه با `conic-gradient` واقعی رندر می‌شود', analytics.donut.slice( 0, 60 ) );
	check(
		analytics.donutTitle === analytics.legend[ 0 ].genre && /٪/.test( analytics.donutPct ),
		'مرکز حلقه همان ژانر اول و درصدش را نشان می‌دهد',
		analytics.donutTitle + ' / ' + analytics.donutPct
	);
	check( 2 === analytics.formats.length, 'سهم فرمت دو ردیف «فیلم/سریال» مرجع را دارد', String( analytics.formats.length ) );
	check(
		analytics.formats.every( ( f ) => '' !== f.bar ),
		'نوار سهم فرمت با درصد واقعی پر می‌شود',
		analytics.formats.map( ( f ) => f.name + ' ' + f.pct + '/' + f.bar ).join( ' | ' )
	);
	check( 7 === analytics.bars, 'نمودار هفته هفت ستون دارد', String( analytics.bars ) );
	check(
		analytics.tallest === 90 && analytics.shortest === 3,
		'ارتفاع ستون‌ها قاعده‌ی مرجع (بیشینه ۹۰px و کمینه ۳px) را رعایت می‌کند',
		analytics.tallest + ' / ' + analytics.shortest
	);
	check( analytics.countries.length >= 2, 'نوارهای کشور از متای واقعی ساخته می‌شوند', JSON.stringify( analytics.countries ) );
	check(
		analytics.countries.every( ( c ) => /%$/.test( c.width ) ),
		'هر نوار کشور پهنای واقعی دارد',
		analytics.countries.map( ( c ) => c.name + c.count ).join( ' | ' )
	);
	check( '' !== analytics.privacy, 'یادداشت حریم خصوصی تحلیل‌ها هست', analytics.privacy );

	/* ── لیست تماشا: زیرتیتر سروری + پالایش نوع ── */
	await page.goto( WP_URL + '?tab=watchlist', { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 200 );

	const watchlist = await page.evaluate( () => ( {
		subtitle: document.querySelector( '[data-panel="watchlist"] .manacore-section-subtitle' ).textContent.trim(),
		cards: document.querySelectorAll( '[data-panel="watchlist"] .manacore-card' ).length,
		tabs: [ ...document.querySelectorAll( '[data-panel="watchlist"] .section-tabs button' ) ].map( ( b ) => b.textContent.trim() ),
	} ) );

	check( /^۵ /.test( watchlist.subtitle ), 'زیرتیتر لیست تماشا شمار سروری دارد (بدون جاوااسکریپت)', watchlist.subtitle );
	check( 5 === watchlist.cards, 'پنج کارت لیست تماشا رندر شده است', String( watchlist.cards ) );
	check( watchlist.tabs.length >= 3, 'تب‌های نوع در تب لیست تماشا هست', JSON.stringify( watchlist.tabs ) );

	await page.click( '[data-panel="watchlist"] .section-tabs button:nth-child(2)' );
	await page.waitForTimeout( 200 );
	const filtered = await page.evaluate( () => ( {
		visible: [ ...document.querySelectorAll( '[data-panel="watchlist"] .manacore-card' ) ]
			.filter( ( c ) => ! c.classList.contains( 'is-filtered-out' ) && c.getBoundingClientRect().height > 0 ).length,
		active: document.querySelector( '[data-panel="watchlist"] .section-tabs button.active' ).textContent.trim(),
	} ) );
	check( 2 === filtered.visible, 'پالایش «فیلم‌ها» فقط فیلم‌ها را نشان می‌دهد', JSON.stringify( filtered ) );

	/* ── تنظیمات: مقادیر ذخیره‌شده ── */
	await page.goto( WP_URL + '?tab=settings', { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 200 );
	const prefs = await page.evaluate( () => ( {
		notifications: document.querySelector( 'input[name="notifications"]' ).checked,
		autoplay: document.querySelector( 'input[name="autoplay"]' ).checked,
		quality: document.querySelector( 'select[name="quality"]' ).value,
	} ) );
	check(
		! prefs.notifications && prefs.autoplay && '720' === prefs.quality,
		'تنظیمات ذخیره‌شده کاربر در فرم برمی‌گردد',
		JSON.stringify( prefs )
	);

	/* ── پاک کردن تاریچه: POST واقعی و بازگشت داده ── */
	await page.goto( WP_URL + '?tab=history', { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 200 );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '[data-panel="history"] button.text-link.danger' ),
	] );
	const cleared = await page.evaluate( () => ( {
		items: document.querySelectorAll( '[data-panel="history"] .history-item' ).length,
		empty: !! document.querySelector( '[data-panel="history"] .empty-state' ),
		tab: new URLSearchParams( window.location.search ).get( 'tab' ),
	} ) );
	check( 0 === cleared.items && cleared.empty, '«پاک کردن تاریچه» واقعاً تاریخچه را خالی می‌کند', JSON.stringify( cleared ) );
	check( 'history' === cleared.tab, 'پس از پاک کردن، کاربر در همان تب می‌ماند', String( cleared.tab ) );

	/* فیکسچر دوباره کاشته می‌شود تا اجرای بعدی آزمون تمیز شروع شود. */
	try {
		execFileSync( 'php', [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', SEED_FILE ], { encoding: 'utf8' } );
		check( true, 'فیکسچر برای اجرای بعدی دوباره کاشته شد' );
	} catch ( error ) {
		check( false, 'کاشت دوباره‌ی فیکسچر', String( error.message ) );
	}

	console.log( '\n== ۱۰) دسترس‌پذیری (axe) روی شش تب ==' );
	if ( axePath ) {
		await page.addScriptTag( { path: axePath } );
		for ( const tab of TABS ) {
			await page.click( `[data-tab="${ tab }"]` );
			await page.waitForTimeout( 120 );
			const result = await page.evaluate( async () => {
				const run = await window.axe.run( document.querySelector( '.account-layout' ).parentElement, {
					runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa' ] },
				} );
				return run.violations.map( ( v ) => v.id + ' → ' + v.nodes.length );
			} );
			check( result.length === 0, 'تب «' + tab + '» بدون تخلف axe', result.join( ', ' ) );
		}
	} else {
		console.log( '  ! axe-core نصب نیست؛ آزمون دسترس‌پذیری اجرا نشد.' );
	}

	check( errors.length === 0, 'بدون خطای جاوااسکریپت در برگه', errors.join( ' | ' ) );

	console.log( '\n== نتیجه: ' + pass + ' / ' + fail + ' ==\n' );

	await context.close();
	await browser.close();

	process.exit( fail ? 1 : 0 );
}

main().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );

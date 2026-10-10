/**
 * آزمون قرارداد صفحه‌بندی «داستان‌های بیشتر» و حالت شماره‌ی صفحه‌ها در
 * بلوک `manacore/titles-grid`.
 *
 * ریشه‌های مشکلی که این آزمون از بازگشتشان جلوگیری می‌کند:
 *   ۱. `?paged=` بدون `inheritFilters` خوانده نمی‌شد؛ پس دکمه همان صفحه‌ی
 *      نخست را دوباره می‌آورد و کارت‌ها تکراری می‌شدند. هر حلقه اکنون
 *      پارامتر و شماره‌ی خودش را دارد (`mc_page_N`).
 *   ۲. کلیک دکمه‌ی «داستان‌های بیشتر» گاهی ناوبری کامل می‌کرد، چون پیوند
 *      با `aria-busy` قبلاً رد می‌شد و رویدادش `preventDefault` نداشت.
 *   ۳. شبکه‌ی مقصد با `document.querySelector` پیدا می‌شد؛ با چند بلوک، کارت
 *      به بلوک اشتباه می‌رفت.
 *   ۴. کش حلقه‌ی صفحه‌بندی‌شده، شمار صفحه‌ها را خالی می‌گذاشت.
 *
 * این آزمون قرارداد هر لایه را روی متن منبع می‌سنجد، و نحو فایل‌های JS را
 * با `vm.Script` بررسی می‌کند. رفتار واقعی مرورگر در `wp/tests/browser`
 * بررسی می‌شود.
 *
 * اجرا: node wp/tests/test-pagination.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );
var vm   = require( 'vm' );

var PLUGIN = path.join( __dirname, '..', 'plugins', 'manacore-core' );

function read( rel ) {
	return fs.readFileSync( path.join( PLUGIN, rel ), 'utf8' );
}

var pass = 0;
var fail = 0;

function ok( cond, label, extra ) {
	if ( cond ) {
		pass++;
		console.log( '  ✓ ' + label + ( extra ? '  — ' + extra : '' ) );
	} else {
		fail++;
		console.log( '  ✗ ' + label );
	}
}

/** متن یک تابع (از امضا تا بستن بدنه) را با شمارش آکولاد برمی‌گرداند. */
function functionBody( src, name ) {
	var start = src.indexOf( 'function ' + name + '(' );
	if ( start < 0 ) {
		return '';
	}
	var open = src.indexOf( '{', start );
	var depth = 0;
	for ( var i = open; i < src.length; i++ ) {
		if ( '{' === src[ i ] ) {
			depth++;
		} else if ( '}' === src[ i ] ) {
			depth--;
			if ( 0 === depth ) {
				return src.slice( start, i + 1 );
			}
		}
	}
	return '';
}

var front   = read( 'assets/js/front.js' );
var blocks  = read( 'assets/js/blocks.js' );
var render  = read( 'includes/class-blocks.php' );
var support = read( 'includes/class-block-support.php' );
var css     = read( 'assets/css/front.css' );

/* ---------------------------------------------------------------
 * ۱) نحو JS
 * ------------------------------------------------------------ */
console.log( '۱) نحو فایل‌های JS' );
[ [ 'front.js', front ], [ 'blocks.js', blocks ] ].forEach( function ( pair ) {
	var parsed = true;
	try {
		new vm.Script( pair[ 1 ] );
	} catch ( e ) {
		parsed = false;
	}
	ok( parsed, pair[ 0 ] + ' نحو معتبر دارد' );
} );

/* ---------------------------------------------------------------
 * ۲) جاوااسکریپت صفحه‌ی جلو: کلیک، شبکه‌ی همان بلوک، بی‌تکرار
 * ------------------------------------------------------------ */
console.log( '۲) رفتار دکمه در front.js' );
var init = functionBody( front, 'initLoadMore' );
ok( /addEventListener\( 'click'/.test( init ) && /\[data-manacore-load-more\]/.test( init ), 'یک شنونده‌ی کلیک تفویضی برای دکمه' );
ok( /event\.preventDefault\(\)/.test( init ), 'کلیک معتبر ناوبری کامل را متوقف می‌کند' );
ok( ! /document\.querySelector\( '\.manacore-grid-cards'/.test( front ), 'شبکه‌ی مقصد با querySelector سراسری پیدا نمی‌شود' );
var loopOf = functionBody( front, 'loopBlockOf' );
ok( /closest\( '\.manacore-titles-block' \)/.test( loopOf ), 'بلوک هر دکمه از نزدیک‌ترین بلوک آثار خوانده می‌شود' );

var request = functionBody( front, 'requestMore' );
ok( /aria-busy/.test( request ) && /'true' === link\.getAttribute\( 'aria-busy' \)/.test( request ), 'واکشی همزمان برای یک دکمه رد می‌شود' );
ok( /data-manacore-loop/.test( request ), 'حلقه با شماره‌ی data-manacore-loop پیدا می‌شود' );
ok( /credentials: 'same-origin'/.test( request ), 'درخواست فقط به مبدأ همین سایت می‌رود' );
ok( /showLoadError\( zone, link \)/.test( request ), 'خطای واکشی پیام می‌دهد و صفحه بازبارگذاری نمی‌شود' );

var uniq = functionBody( front, 'appendUniqueCards' );
ok( /data-post-id/.test( uniq ) && /seen\[/.test( uniq ), 'کارت تکراری (همان data-post-id) دوباره افزوده نمی‌شود' );

var auto = functionBody( front, 'initLoadMoreAutoload' );
ok( /IntersectionObserver/.test( auto ) && /rootMargin/.test( auto ), 'بارگذاری خودکار با IntersectionObserver و حاشیه‌ی پیش‌واکشی' );
ok( /data-manacore-autoload/.test( auto ), 'فقط ظرف‌های دارای نشانه‌ی خودکار مشاهده می‌شوند' );
ok( /unobserve/.test( auto ) && /observer\.observe\( zone \)/.test( auto ), 'مشاهده بعد از هر موفقیت دوباره برقرار می‌شود' );

/* ---------------------------------------------------------------
 * ۳) PHP: پارامتر صفحه، خروجی دکمه و شماره‌ها
 * ------------------------------------------------------------ */
console.log( '۳) خروجی PHP' );
ok( /loopPage/.test( support ) && /\$args\['paged'\]\s*=\s*\(int\) \$attrs\['loopPage'\]/.test( support ), 'query_args صفحه‌ی حلقه را روی paged می‌گذارد' );
ok( /no_found_rows'\]\s*=\s*false/.test( support ), 'حلقه‌ی صفحه‌بندی‌شده شمار صفحه را می‌خواهد' );
ok( /! self::paginates\( \$attrs \)/.test( support ), 'حلقه‌ی صفحه‌بندی‌شده کش نمی‌شود' );
ok( /data-manacore-loop/.test( render ), 'شبکه‌ی هر بلوک شماره‌ی حلقه را دارد' );
ok( /data-manacore-autoload/.test( render ) && /data-manacore-error/.test( render ), 'دکمه نشانه‌ی خودکار و متن خطا را دارد' );
ok( /nav class="koohe-pagination manacore-pagination"|koohe-pagination manacore-pagination/.test( render ), 'شماره‌ها در nav.koohe-pagination.manacore-pagination' );
ok( /paginate_links/.test( render ) && /%#%/.test( render ), 'شماره‌ها با paginate_links و پایهٔ %#% ساخته می‌شوند' );

/* ---------------------------------------------------------------
 * ۴) ویرایشگر: حالت، همگام‌سازی loadMore، و پنل
 * ------------------------------------------------------------ */
console.log( '۴) پنل ویرایشگر' );
var panel = functionBody( blocks, 'paginationPanel' );
ok( panel.length > 0, 'پنل صفحه‌بندی در blocks.js تعریف شده' );
ok( /value: 'none'/.test( panel ) && /value: 'loadMore'/.test( panel ) && /value: 'numbered'/.test( panel ), 'سه حالت: none، loadMore، numbered' );
ok( /paginationMode: value, loadMore: 'loadMore' === value/.test( panel ), 'loadMore با حالت همگام می‌ماند' );
ok( /'loadMore' === mode/.test( panel ) && /autoLoad/.test( panel ) && /loadText/.test( panel ), 'autoLoad و loadText فقط در حالت دکمه' );
ok( /queryPanels[\s\S]*paginationPanel\( props \)/.test( blocks ), 'پنل در پنل‌های کوئری ثبت شده' );

/* ---------------------------------------------------------------
 * ۵) استایل خطا
 * ------------------------------------------------------------ */
console.log( '۵) استایل' );
ok( /\.load-more-zone \.load-more-error\s*\{/.test( css ), '.load-more-error در front.css تعریف شده' );

console.log( '\nموفق: ' + pass + '   ناموفق: ' + fail );
process.exit( fail > 0 ? 1 : 0 );

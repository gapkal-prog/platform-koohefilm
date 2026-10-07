/**
 * آزمون بومِ ویرایشگر برای بلوک‌های افزونه.
 *
 * ریشه‌ی مشکلی که این آزمون از بازگشتش جلوگیری می‌کند:
 *
 *   front.css تنها روی قلاب wp_enqueue_scripts صف می‌شد. آن قلاب فقط
 *   در سمت کاربر اجرا می‌شود، پس در «ویرایشگر سایت» و ویرایشگر نوشته
 *   هیچ قاعده‌ای برای کلاس‌های manacore-* وجود نداشت. نتیجه: پیش‌نمایش
 *   سمت سرور HTML درست را برمی‌گرداند (اثبات شد: ۵۲۵۲ بایت برای
 *   titles-grid) ولی بی‌استایل و بی‌چیدمان دیده می‌شد — همان «در
 *   ویرایشگر html نمایش داده نمی‌شود و چینش خالی است».
 *
 *   دو باگ همراه که همان نشانه را می‌ساختند:
 *     • بلوک‌های «یک اثر» در ویرایشگر زمینه‌ی پست ندارند، پس هفت بلوک
 *       از ده بلوک پیام «محتوایی برای نمایش وجود ندارد» می‌دادند.
 *     • اسلایدر و تب فصل به front.js وابسته‌اند که در بوم اجرا نمی‌شود؛
 *       همه‌ی اسلایدها opacity:0 می‌ماندند و کادر خالی دیده می‌شد.
 *
 * اجرا: node wp/tests/test-editor-canvas.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );

var WP     = path.join( __dirname, '..' );
var PLUGIN = path.join( WP, 'plugins', 'manacore-core' );

var pass = 0;
var fail = 0;

function assert( ok, label ) {
	if ( ok ) {
		pass++;
		console.log( '  ✓ ' + label );
	} else {
		fail++;
		console.log( '  ✗ ' + label );
	}
}

function read( file ) {
	return fs.existsSync( file ) ? fs.readFileSync( file, 'utf8' ) : '';
}

function stripComments( src ) {
	return src.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^[ \t]*\/\/.*$/gm, '' );
}

function stripCss( src ) {
	return src.replace( /\/\*[\s\S]*?\*\//g, '' );
}

/** استخراج بدنه‌ی یک تابع/متد PHP بر پایه‌ی شمارش آکولاد. */
function phpBody( src, signature ) {
	var at = src.indexOf( signature );
	if ( at < 0 ) {
		return '';
	}
	var open = src.indexOf( '{', at );
	if ( open < 0 ) {
		return '';
	}
	var depth = 0;
	for ( var i = open; i < src.length; i++ ) {
		if ( '{' === src[ i ] ) {
			depth++;
		} else if ( '}' === src[ i ] ) {
			depth--;
			if ( 0 === depth ) {
				return src.slice( open, i + 1 );
			}
		}
	}
	return '';
}

var assetsPhp = read( path.join( PLUGIN, 'includes', 'class-assets.php' ) );
var blocksPhp = read( path.join( PLUGIN, 'includes', 'class-blocks.php' ) );
var blocksJs  = read( path.join( PLUGIN, 'assets', 'js', 'blocks.js' ) );

var canvasCssPath = path.join( PLUGIN, 'assets', 'css', 'editor-canvas.css' );
var canvasCss     = read( canvasCssPath );

var assetsCode = stripComments( assetsPhp );
var blocksCode = stripComments( blocksPhp );
var jsCode     = stripComments( blocksJs );
var canvasBare = stripCss( canvasCss );

console.log( '==========================================================' );
console.log( 'آزمون بومِ ویرایشگر بلوک‌های ManaCore' );
console.log( '==========================================================' );
console.log( '' );

/* ------------------------------------------------------------------
 * الف) بارگذاری استایل در بوم
 * --------------------------------------------------------------- */

console.log( 'الف) استایل بلوک‌ها باید به بوم ویرایشگر برسد' );
console.log( '----------------------------------------------------------' );

assert(
	/add_action\(\s*'enqueue_block_assets'/.test( assetsCode ),
	'قلاب enqueue_block_assets ثبت شده است (بوم ویرایشگر)'
);

var hooksBody = phpBody( assetsCode, 'function hooks()' );
assert(
	/'wp_enqueue_scripts'/.test( hooksBody ) && /'enqueue_block_assets'/.test( hooksBody ),
	'هم قلاب سمت کاربر و هم قلاب بوم ثبت می‌شوند'
);

var canvasBody = phpBody( assetsCode, 'function editor_canvas()' );
assert( '' !== canvasBody, 'متد editor_canvas() تعریف شده است' );
assert(
	/front\.css/.test( canvasBody ),
	'front.css در بوم ویرایشگر صف می‌شود (ریشه‌ی «چینش خالی»)'
);
assert(
	/editor-canvas\.css/.test( canvasBody ),
	'استایل ویژه‌ی بوم نیز صف می‌شود'
);

/*
 * enqueue_block_assets در فرانت هم اجرا می‌شود؛ بدون این محافظ،
 * front.css دو بار صف می‌شد.
 */
assert(
	/if\s*\(\s*!\s*is_admin\(\)\s*\)\s*\{[\s\S]{0,40}?return/.test( canvasBody ),
	'با is_admin() از صف‌شدن دوباره در سمت کاربر جلوگیری می‌شود'
);

// JS سمت کاربر نباید در بوم اجرا شود؛ با پیش‌نمایش‌ها تضاد دارد.
assert(
	! /front\.js/.test( canvasBody ),
	'front.js در بوم بارگذاری نمی‌شود (مزاحم ویرایش است)'
);

// فایل باید واقعاً وجود داشته باشد، وگرنه ۴۰۴ می‌دهد.
assert( fs.existsSync( canvasCssPath ), 'فایل editor-canvas.css روی دیسک موجود است' );
assert( canvasCss.length > 500, 'editor-canvas.css خالی نیست (' + canvasCss.length + ' بایت)' );

/* ------------------------------------------------------------------
 * ب) پیش‌نمایش سمت سرور
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ب) پیش‌نمایش سمت سرور' );
console.log( '----------------------------------------------------------' );

assert(
	/ServerSideRender/.test( jsCode ) && /httpMethod:\s*'POST'/.test( jsCode ),
	'بلوک‌ها با ServerSideRender پیش‌نمایش می‌شوند'
);
assert(
	/manacore-block-preview/.test( jsCode ),
	'پیش‌نمایش در پوشش .manacore-block-preview قرار می‌گیرد'
);
assert(
	/\.manacore-block-preview/.test( canvasBare ),
	'پوشش پیش‌نمایش در CSS بوم مهار می‌شود (جلوگیری از سرریز)'
);
assert(
	/pointer-events:\s*none/.test( canvasBare ),
	'کلیک روی اجزای پیش‌نمایش کاربر را از ویرایشگر بیرون نمی‌برد'
);

/* ------------------------------------------------------------------
 * پ) زمینه‌ی پست نمونه برای بلوک‌های «یک اثر»
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'پ) بلوک‌های یک‌اثر در ویرایشگر محتوا نشان می‌دهند' );
console.log( '----------------------------------------------------------' );

var targetBody = phpBody( blocksCode, 'function target_post(' );
assert( '' !== targetBody, 'بدنه‌ی target_post() یافت شد' );
assert(
	/Block_Support::is_editor_preview\(\)/.test( targetBody ),
	'target_post() حالت پیش‌نمایش ویرایشگر را تشخیص می‌دهد'
);
assert(
	/sample_post_id\(\s*\$types\s*\)/.test( targetBody ),
	'در پیش‌نمایش، اثر نمونه‌ی هم‌نوعِ درخواست جای زمینه‌ی نبودِ پست را می‌گیرد'
);

/*
 * جایگزینی باید فقط در پیش‌نمایش باشد. اگر شرط is_editor_preview
 * حذف شود، خروجی سمت کاربر هم عوض می‌شود که باگ جدی است.
 */
assert(
	/!\s*\$post_id\s*&&\s*Block_Support::is_editor_preview\(\)/.test( targetBody ),
	'اثر نمونه تنها وقتی به کار می‌رود که زمینه‌ای نباشد و در ویرایشگر باشیم'
);

var sampleBody = phpBody( blocksCode, 'function sample_post_id( $types = array() )' );
assert( '' !== sampleBody, 'متد sample_post_id( $types ) تعریف شده است' );
assert(
	/\$cache_key\s*=\s*'manacore_sample_post'/.test( sampleBody )
		&& /wp_cache_get\(\s*\$cache_key\s*\)/.test( sampleBody )
		&& /wp_cache_set\(\s*\$cache_key/.test( sampleBody ),
	'نتیجه با کلید نوع‌آگاه cache می‌شود تا هر پیش‌نمایش یک کوئری تازه نزند'
);
assert(
	/manacore_title_post_types\(\)/.test( sampleBody ),
	'در نبود نوع درخواستی، همه‌ی انواع اثر بررسی می‌شوند'
);

var numberposts = sampleBody.match( /'numberposts'\s*=>\s*(\d+)/ );
assert(
	numberposts && parseInt( numberposts[ 1 ], 10 ) > 1,
	'بیش از یک نامزد بررسی می‌شود (تازه‌ترین اثر ممکن است خالی باشد)'
);
assert(
	/Links::count\(/.test( sampleBody ),
	'اثری که لینک دانلود دارد امتیاز بیشتری می‌گیرد'
);
assert(
	/'no_found_rows'\s*=>\s*true/.test( sampleBody ),
	'کوئری نمونه no_found_rows دارد (بی‌نیاز از شمارش کل)'
);

/* ------------------------------------------------------------------
 * ت) چیزهایی که به جاوااسکریپت وابسته‌اند
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ت) اجزای وابسته به JS در بوم ایستا دیده می‌شوند' );
console.log( '----------------------------------------------------------' );

/*
 * front.js در بوم اجرا نمی‌شود، پس هر چیزی که وضعیت اولیه‌اش را از JS
 * می‌گیرد باید در CSS بوم به‌زور دیده شود.
 */
assert(
	/\.manacore-hero-slide:first-child/.test( canvasBare ),
	'اسلاید نخست اسلایدر در بوم دیده می‌شود (بدون JS همه پنهان بودند)'
);
assert(
	/\.manacore-hero-slide:first-child[\s\S]{0,220}?opacity:\s*1/.test( canvasBare ),
	'شفافیت اسلاید نخست بازنویسی می‌شود'
);
assert(
	/\.manacore-hero-slide:first-child[\s\S]{0,220}?visibility:\s*visible/.test( canvasBare ),
	'visibility اسلاید نخست بازنویسی می‌شود'
);
assert(
	/\.manacore-season-panel[\s\S]{0,80}?display:\s*block/.test( canvasBare ),
	'پنل فصل‌ها در بوم باز است (تب‌ها بدون JS کار نمی‌کنند)'
);
assert(
	/\.manacore-grid-cards\.is-scroll[\s\S]{0,220}?overflow-x:\s*auto/.test( canvasBare ),
	'ریل افقی در بوم هم ریل می‌ماند (فقط اسکرول‌پذیر می‌شود) و به شبکه تبدیل نمی‌شود'
);

/* ------------------------------------------------------------------
 * ث) حالت «داده‌ای نیست» به‌صورت راهنما
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ث) حالت خالی در ویرایشگر گویا است' );
console.log( '----------------------------------------------------------' );

assert(
	/\.manacore-block\.is-empty/.test( canvasBare ),
	'حالت خالی در بوم قاعده‌ی اختصاصی دارد'
);
assert(
	/\.manacore-block\.is-empty[\s\S]{0,240}?border:\s*1px dashed/.test( canvasBare ),
	'کادر نقطه‌چین مثل جای‌نگهدار بلوک‌های هسته (با «بلوک خراب» اشتباه نشود)'
);
assert(
	/\.manacore-empty-state/.test( canvasBare ),
	'متن حالت خالی در بوم قالب‌بندی می‌شود'
);

/* ------------------------------------------------------------------
 * ج) اجزای شناور و حرکت
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ج) اجزای شناور و حرکت در بوم' );
console.log( '----------------------------------------------------------' );

/*
 * position: fixed در بوم به قاب iframe می‌چسبد و روی ابزار ویرایش
 * می‌افتد.
 */
[ 'manacore-toast', 'manacore-modal', 'manacore-player' ].forEach( function ( cls ) {
	assert(
		new RegExp( '\\.' + cls ).test( canvasBare ),
		'جزء شناور .' + cls + ' در بوم پنهان می‌شود'
	);
} );

assert(
	/animation:\s*none/.test( canvasBare ),
	'انیمیشن در بوم خاموش است (حواس را از ویرایش نبرد)'
);

/* ------------------------------------------------------------------
 * چ) دامنه‌ی قواعد
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'چ) قواعد بوم به سمت کاربر نشت نکنند' );
console.log( '----------------------------------------------------------' );

/*
 * هر قاعده‌ای که رفتار فرانت را عوض می‌کند باید به
 * .editor-styles-wrapper محدود باشد، وگرنه اسلایدر سمت کاربر هم
 * خراب می‌شود (همه‌ی اسلایدها هم‌زمان دیده می‌شوند).
 */
var LEAK_PRONE = [
	'manacore-hero-slide',
	'manacore-season-panel',
	'manacore-grid-cards',
	'is-empty',
	'manacore-toast',
];

var leaks = [];
canvasBare.split( '}' ).forEach( function ( chunk ) {
	var head = chunk.split( '{' )[ 0 ];
	if ( ! head || head.indexOf( '@' ) > -1 ) {
		return;
	}
	head.split( ',' ).forEach( function ( sel ) {
		sel = sel.trim();
		if ( ! sel ) {
			return;
		}
		var risky = LEAK_PRONE.some( function ( cls ) {
			return sel.indexOf( cls ) > -1;
		} );
		if ( risky && sel.indexOf( '.editor-styles-wrapper' ) < 0 ) {
			leaks.push( sel );
		}
	} );
} );

assert(
	0 === leaks.length,
	'همه‌ی قواعد رفتاری به .editor-styles-wrapper محدودند' +
		( leaks.length ? ' — نشتی: ' + leaks.slice( 0, 4 ).join( ' | ' ) : '' )
);

// آکولادها متوازن باشند.
var opens  = ( canvasBare.match( /\{/g ) || [] ).length;
var closes = ( canvasBare.match( /\}/g ) || [] ).length;
assert( opens === closes, 'آکولادهای editor-canvas.css متوازن‌اند (' + opens + ' / ' + closes + ')' );

/*
 * ------------------------------------------------------------------
 * هم‌خوانی فهرست آیکون‌های سرتیتر بین PHP و JS
 * ---------------------------------------------------------------
 *
 * ریشه‌ی مشکلی که این سنجه از بازگشتش جلوگیری می‌کند:
 *
 *   آیکون سرتیتر دو فهرست جدا دارد — مسیرهای SVG در
 *   `Block_Support::heading_icon_paths()` (PHP) و برچسب‌های گزینش‌گر در
 *   `HEADING_ICONS` (JS). اگر آیکونی فقط در PHP اضافه شود، در HTML
 *   رندر می‌شود ولی مدیر نمی‌تواند از ویرایشگر انتخابش کند (و با یک بار
 *   ذخیره‌ی بلوک، مقدارش پاک می‌شود). همین اتفاق برای آیکون «tv» رخ داد.
 */
var supportPhp = fs.readFileSync( path.join( PLUGIN, 'includes', 'class-block-support.php' ), 'utf8' );
var blocksJs   = fs.readFileSync( path.join( PLUGIN, 'assets', 'js', 'blocks.js' ), 'utf8' );

function iconKeys( source, startMarker, endMarker ) {
	var from = source.indexOf( startMarker );
	if ( from < 0 ) {
		return null;
	}
	var to  = source.indexOf( endMarker, from );
	var box = source.slice( from, to < 0 ? source.length : to );
	var out = [];
	var re  = /['\"]([a-z][a-z0-9_-]*)['\"]\s*=>|^\s*([a-z][a-z0-9_-]*):\s*__\(/gm;
	var m;
	while ( ( m = re.exec( box ) ) !== null ) {
		var key = m[1] || m[2];
		if ( key && out.indexOf( key ) < 0 ) {
			out.push( key );
		}
	}
	return out;
}

var phpIcons = iconKeys( supportPhp, 'function heading_icon_paths', '\n\t}' );
var jsIcons  = iconKeys( blocksJs, 'var HEADING_ICONS = {', '\n\t};' );

assert( !! phpIcons && phpIcons.length > 0, 'فهرست آیکون‌های سرتیتر در PHP خوانده شد (' + ( phpIcons ? phpIcons.length : 0 ) + ' آیکون)' );
assert( !! jsIcons && jsIcons.length > 0, 'فهرست آیکون‌های سرتیتر در JS خوانده شد (' + ( jsIcons ? jsIcons.length : 0 ) + ' آیکون)' );

var missingInJs  = ( phpIcons || [] ).filter( function ( k ) { return ( jsIcons || [] ).indexOf( k ) < 0; } );
var missingInPhp = ( jsIcons || [] ).filter( function ( k ) { return ( phpIcons || [] ).indexOf( k ) < 0; } );

assert(
	0 === missingInJs.length,
	'هر آیکون PHP در گزینش‌گر ویرایشگر هست' + ( missingInJs.length ? ' — بی‌گزینش‌گر: ' + missingInJs.join( ', ' ) : '' )
);
assert(
	0 === missingInPhp.length,
	'هر گزینه‌ی ویرایشگر مسیر SVG دارد' + ( missingInPhp.length ? ' — بی‌مسیر: ' + missingInPhp.join( ', ' ) : '' )
);

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

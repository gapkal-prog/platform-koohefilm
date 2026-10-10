/**
 * آزمون سلامت ظاهر پیشخوان (CSS در برابر کد).
 *
 * این آزمون همان دسته اشکال‌هایی را می‌گیرد که در پنل تنظیمات واقعاً رخ داده
 * بودند و هیچ‌کدام خطای نحوی یا خطای زمان‌اجرا نمی‌دادند:
 *
 *   ۱. قاعده‌ی بی‌مصرف: کلاسی در `admin.css` استایل دارد ولی هیچ‌جای پروژه
 *      تولید نمی‌شود. نمونه‌ی واقعی: `.manacore-fetch-result*`،
 *      `.manacore-notice` و `.manacore-spinner` — از نسخه‌ی پیشین کادر
 *      «دریافت از منبع» مانده بودند، در حالی که کد امروز کلاس‌های
 *      `.manacore-fetch-item` را می‌سازد؛ پس قاعده‌ها بی‌اثر بودند و فقط
 *      احتمال تصادم داشتند.
 *
 *   ۲. تصادم بین افزونه‌ها: یک کلاس `manacore-*` در دو افزونه تعریف شود.
 *      استایل هر افزونه جدا بارگذاری می‌شود ولی وقتی هر دو روی یک صفحه
 *      باشند، برنده‌ی رقابت به ترتیب بارگذاری بستگی پیدا می‌کند.
 *
 *   ۳. متغیر مرده: `var(--mc-*)` خوانده شود ولی هیچ‌جا تعیین نشود (یا
 *      تعیین شود و هیچ‌جا خوانده نشود) — نمونه‌ی واقعی: متغیرهای طراحی فقط
 *      در صفحه‌ی تنظیمات تعریف شده بودند و متای‌باکس ویرایشگر رنگ نمی‌گرفت.
 *
 *   ۴. تکرار بلوک: یک کلاس پایه دو بار با مقدار متفاوت تعریف شده باشد
 *      (نمونه‌ی واقعی: دو بلوک `.manacore-settings-header` با نشان نارنجی و
 *      نشان تیره).
 *
 *   ۵. استایل درون‌خطی و تگ `<style>` در صفحه‌ی تنظیمات/ویجت که ظاهر
 *      یک‌دست را می‌شکند.
 *
 * اجرا: node wp/tests/test-admin-ui.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );

var WP      = path.join( __dirname, '..' );
var PLUGINS = path.join( WP, 'plugins' );
var THEMES  = path.join( WP, 'themes' );

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

function stripCss( src ) {
	return src.replace( /\/\*[\s\S]*?\*\//g, '' );
}

/**
 * حذف بلوک‌های `@media` (با تطبیق آکولادها) تا قاعده‌های پایه جدا شمرده شوند.
 *
 * @param {string} src CSS بدون توضیح.
 * @return {string} CSS بدون بلوک‌های media.
 */
function stripMedia( src ) {
	var out = '';
	var i   = 0;

	while ( i < src.length ) {
		var at = src.indexOf( '@media', i );

		if ( at === -1 ) {
			out += src.slice( i );
			break;
		}

		out += src.slice( i, at );

		var open = src.indexOf( '{', at );

		if ( open === -1 ) {
			break;
		}

		var depth = 0;
		var j     = open;

		for ( ; j < src.length; j++ ) {
			if ( '{' === src[ j ] ) {
				depth++;
			} else if ( '}' === src[ j ] ) {
				depth--;

				if ( 0 === depth ) {
					break;
				}
			}
		}

		i = j + 1;
	}

	return out;
}

/**
 * فایل‌های PHP/JS/HTML پروژه (بدون آزمون‌ها).
 */
function sources() {
	var out = [];

	function walk( dir ) {
		if ( ! fs.existsSync( dir ) ) {
			return;
		}

		fs.readdirSync( dir, { withFileTypes: true } ).forEach( function ( entry ) {
			var full = path.join( dir, entry.name );

			if ( entry.isDirectory() ) {
				if ( [ 'node_modules', 'tests', '.git', 'languages', 'vendor' ].indexOf( entry.name ) !== -1 ) {
					return;
				}

				walk( full );
				return;
			}

			if ( /\.(php|js|html)$/.test( entry.name ) ) {
				out.push( full );
			}
		} );
	}

	walk( PLUGINS );
	walk( THEMES );

	return out;
}

/**
 * همه‌ی کلاس‌های manacore-* که کد پروژه تولید می‌کند.
 */
function emittedClasses() {
	var found = {};

	sources().forEach( function ( file ) {
		var src = read( file );

		/*
		 * نام کلاس‌ها به سه شکل می‌آیند: در HTML (`class="…"`)، در جاوااسکریپت
		 * (`className = '…'` یا `querySelector('.…')`) و در ادغام‌های PHP.
		 * همه‌ی رشته‌ها را می‌خوانیم و هر توکن manacore-* را برمی‌داریم؛ اگر
		 * توکنی فقط در توضیح باشد هم مهم نیست، چون این آزمون دنبال «قاعده‌ی
		 * بی‌مصرف» است نه شمارش دقیق.
		 */
		( src.match( /manacore-[a-z0-9][a-z0-9_-]*/g ) || [] ).forEach( function ( token ) {
			found[ token ] = true;
		} );
	} );

	return found;
}

/**
 * کلاس‌های تعریف‌شده در یک فایل CSS.
 *
 * @param {string} file مسیر فایل.
 * @return {Object} مجموعه‌ی نام‌ها.
 */
function definedClasses( file ) {
	var found = {};

	( stripCss( read( file ) ).match( /\.(manacore-[a-z0-9_-]+)/g ) || [] ).forEach( function ( token ) {
		found[ token.replace( /^\./, '' ) ] = true;
	} );

	return found;
}

var CORE_CSS = path.join( PLUGINS, 'manacore-core', 'assets', 'css', 'admin.css' );
var coreCss  = stripCss( read( CORE_CSS ) );
var emitted  = emittedClasses();
var defined  = definedClasses( CORE_CSS );

/* ------------------------------------------------------------------
 * الف) قاعده‌ی بی‌مصرف
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'الف) کلاس‌های پنل: هر قاعده در CSS یک مصرف‌کننده دارد' );
console.log( '----------------------------------------------------------' );

var orphans = Object.keys( defined ).filter( function ( name ) {
	return ! emitted[ name ];
} ).sort();

assert(
	0 === orphans.length,
	'هیچ قاعده‌ی بی‌مصرفی در admin.css نیست' + ( orphans.length ? ' — ' + orphans.join( ', ' ) : '' )
);

/* ------------------------------------------------------------------
 * ب) تصادم بین افزونه‌ها
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ب) تصادم بین افزونه‌ها' );
console.log( '----------------------------------------------------------' );

var pluginCss = {};

fs.readdirSync( PLUGINS ).forEach( function ( plugin ) {
	var slug = plugin;

	function walk( dir ) {
		if ( ! fs.existsSync( dir ) ) {
			return;
		}

		fs.readdirSync( dir, { withFileTypes: true } ).forEach( function ( entry ) {
			var full = path.join( dir, entry.name );

			if ( entry.isDirectory() ) {
				walk( full );
				return;
			}

			if ( ! /\.css$/.test( entry.name ) || /front|theme|editor/.test( entry.name ) ) {
				return;
			}

			Object.keys( definedClasses( full ) ).forEach( function ( name ) {
				pluginCss[ name ] = pluginCss[ name ] || {};
				pluginCss[ name ][ slug ] = true;
			} );
		} );
	}

	walk( path.join( PLUGINS, plugin ) );
} );

/*
 * استایل پیشخوان هر افزونه‌ی دیگر (کادر دریافت منبع، صفحه‌ی اشتراک‌ها) نباید
 * همان کلاس‌های هسته را دوباره تعریف کند؛ وگرنه برنده‌ی رقابت به ترتیب
 * بارگذاری بستگی پیدا می‌کند.
 */
/*
 * `.manacore-settings-wrap` عمداً مشترک است: پوسته‌ی صفحه‌های افزونه‌های
 * ManaCore، که استایل پایه‌اش در هسته است و هر افزونه فقط بخش خودش را
 * به آن اضافه می‌کند. بقیه‌ی کلاس‌ها نباید بین افزونه‌ها مشترک باشند.
 */
var SHARED_SCAFFOLD = [ 'manacore-settings-wrap' ];

var clashes = Object.keys( pluginCss ).filter( function ( name ) {
	var owners = Object.keys( pluginCss[ name ] );

	return owners.length > 1
		&& owners.indexOf( 'manacore-core' ) !== -1
		&& SHARED_SCAFFOLD.indexOf( name ) === -1;
} ).sort();

assert(
	0 === clashes.length,
	'هیچ کلاسی بین admin.css هسته و استایل افزونه‌های دیگر مشترک نیست' + ( clashes.length ? ' — ' + clashes.join( ', ' ) : '' )
);

/* ------------------------------------------------------------------
 * ج) متغیرهای طراحی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ج) متغیرهای طراحی (--mc-*)' );
console.log( '----------------------------------------------------------' );

var usedVars    = ( coreCss.match( /var\(\s*(--mc-[a-z0-9-]+)/g ) || [] ).map( function ( token ) {
	return token.replace( /var\(\s*/, '' );
} );
var definedVars = ( coreCss.match( /(--mc-[a-z0-9-]+)\s*:/g ) || [] ).map( function ( token ) {
	return token.replace( /\s*:$/, '' );
} );

usedVars    = usedVars.filter( function ( value, index, list ) { return list.indexOf( value ) === index; } );
definedVars = definedVars.filter( function ( value, index, list ) { return list.indexOf( value ) === index; } );

var missing = usedVars.filter( function ( name ) {
	return definedVars.indexOf( name ) === -1;
} );

var unused = definedVars.filter( function ( name ) {
	return usedVars.indexOf( name ) === -1;
} );

assert( 0 === missing.length, 'هر متغیری که خوانده می‌شود، تعریف هم شده است' + ( missing.length ? ' — ' + missing.join( ', ' ) : '' ) );
assert( 0 === unused.length, 'هیچ متغیر تعریف‌شده‌ای بی‌استفاده نمانده است' + ( unused.length ? ' — ' + unused.join( ', ' ) : '' ) );
assert(
	/--mc-radius:[^;]+;/.test( coreCss ) && /--mc-border:[^;]+;/.test( coreCss ),
	'نشانه‌های پایه‌ی طراحی (شعاع و حاشیه) تعریف شده‌اند'
);
assert(
	/\.manacore-metabox,\s*\.manacore-settings,\s*\.manacore-settings-wrap\s*\{/.test( coreCss ),
	'متغیرها روی هر سه سطح پیشخوان (متای‌باکس، تنظیمات، اشتراک‌ها) تعریف شده‌اند'
);

/* ------------------------------------------------------------------
 * د) تکرار بلوک و استایل درون‌خطی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'د) تکرار بلوک و استایل درون‌خطی' );
console.log( '----------------------------------------------------------' );

var baseCss      = stripMedia( coreCss );
var headerBlocks = ( baseCss.match( /\.manacore-settings-header\s*\{/g ) || [] ).length;

assert( 1 === headerBlocks, 'سرصفحه‌ی تنظیمات در حالت پایه فقط یک بلوک دارد (' + headerBlocks + ')' );
assert(
	/\@media screen and \(max-width: 782px\)[\s\S]*\.manacore-settings-header/.test( coreCss ),
	'همان سرصفحه در موبایل قاعده‌ی جدا دارد'
);

[ 'class-settings.php', 'class-analytics.php' ].forEach( function ( file ) {
	var src = read( path.join( PLUGINS, 'manacore-core', 'includes', file ) );

	assert( src.indexOf( 'style="' ) === -1, file + ': هیچ استایل درون‌خطی ندارد' );
	assert( src.indexOf( '<style' ) === -1, file + ': هیچ تگ <style> ندارد' );
} );

/* ------------------------------------------------------------------
 * ه) اجزای پنل: تعریف و مصرف
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ه) اجزای پنل' );
console.log( '----------------------------------------------------------' );

[
	'manacore-settings-header',
	'manacore-brand-mark',
	'manacore-panel',
	'manacore-panel__title',
	'manacore-panel__hint',
	'manacore-stats',
	'manacore-status',
	'manacore-tool',
	'manacore-filters',
	'manacore-filter',
	'manacore-filter__count',
	'manacore-rank',
	'manacore-rank__flag',
	'manacore-row-actions',
	'manacore-empty',
	'manacore-codes',
	'manacore-widget-grid',
	'manacore-widget-list',
	'manacore-widget-actions',
].forEach( function ( name ) {
	assert( !! defined[ name ], name + ': در admin.css تعریف شده است' );
	assert( !! emitted[ name ], name + ': در کد هم تولید می‌شود' );
} );

/* ------------------------------------------------------------------
 * و) واکنش‌گرایی و توازن نحوی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'و) واکنش‌گرایی و توازن نحوی' );
console.log( '----------------------------------------------------------' );

var mobile = coreCss.slice( coreCss.indexOf( '@media screen and (max-width: 782px)' ) );

assert( coreCss.indexOf( '@media screen and (max-width: 782px)' ) !== -1, 'نقطه‌ی شکست موبایل پیشخوان تعریف شده است' );
assert( mobile.indexOf( '.manacore-panel' ) !== -1, 'کارت‌ها در موبایل پدینگ/چیدمان جدا دارند' );
assert( mobile.indexOf( '.manacore-tool' ) !== -1, 'ردیف ابزارها در موبایل ستونی می‌شود' );
assert( mobile.indexOf( '.manacore-link-form' ) !== -1, 'فرم‌های ابزار لینک در موبایل ستونی می‌شوند' );

var opens  = ( coreCss.match( /\{/g ) || [] ).length;
var closes = ( coreCss.match( /\}/g ) || [] ).length;

assert( opens === closes, 'آکولادهای admin.css متوازن‌اند (' + opens + ' / ' + closes + ')' );

/* ------------------------------------------------------------------
 * جمع‌بندی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

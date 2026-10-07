/**
 * آزمون ریسپانسیو، دسترس‌پذیری و نبودِ گزینشگر یتیم (UI/UX).
 *
 * این آزمون از بازگشت چهار دسته اشکال جلوگیری می‌کند که همه‌ی آن‌ها در
 * همین پروژه رخ داده بودند و «بی‌صدا» بودند — نه خطای نحوی می‌دادند و نه
 * در اعتبارسنج CSS دیده می‌شدند:
 *
 *   ۱. گزینشگر یتیم: قاعده‌ای که کلاسی را هدف می‌گیرد که هیچ‌جای
 *      PHP/JS/HTML تولید نمی‌شود، پس هرگز منطبق نمی‌شود. سه نمونه‌ی
 *      واقعی که پیدا و رفع شد:
 *        • .manacore-cast-list  (رندرکننده .manacore-cast-track می‌سازد)
 *          ← گزینه‌ی چیدمان «شبکه‌ای» بازیگران کاملاً بی‌اثر بود.
 *        • .manacore-meta-label (برچسب یک <dt> ساده است)
 *          ← تنظیم «پهنای برچسب» بی‌اثر بود.
 *        • .manacore-search-box (بلوک .manacore-search می‌سازد)
 *          ← پنهان‌سازی جستجو در تبلت بی‌اثر بود.
 *
 *   ۲. همپوشانی نقطه‌ی شکست: اگر هم max-width:Npx و هم min-width:Npx
 *      وجود داشته باشد، در پهنای دقیقاً N هر دو منطبق می‌شوند و قواعد
 *      موبایل و دسکتاپ با هم اعمال می‌شوند. نمونه‌ی واقعی: عرض محتوای
 *      اسلایدر در ۷۸۲px هم 100% و هم 46% تعیین می‌شد.
 *
 *   ۳. متغیر مرده: var(--x) خوانده شود ولی --x هیچ‌جا تعیین نشود.
 *      نمونه‌ی واقعی: --mc-hero-dir که جهت پوشش اسلایدر را در RTL
 *      همیشه اشتباه می‌گذاشت.
 *
 *   ۴. هدف لمسی کوچک و ناحیه‌ی بی‌اعلام: دکمه‌های زیر ۴۴px و نتایج
 *      جستجوی زنده که بدون aria-live به صفحه‌خوان اعلام نمی‌شدند.
 *
 * اجرا: node wp/tests/test-responsive.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );

var WP     = path.join( __dirname, '..' );
var PLUGIN = path.join( WP, 'plugins', 'manacore-core' );
var THEME  = path.join( WP, 'themes', 'koohe-film' );

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

/** همه‌ی فایل‌های یک پوشه با پسوندهای دلخواه (بازگشتی). */
function allFiles( dir, exts ) {
	var out = [];
	if ( ! fs.existsSync( dir ) ) {
		return out;
	}
	fs.readdirSync( dir, { withFileTypes: true } ).forEach( function ( entry ) {
		var full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			if ( 'node_modules' !== entry.name && 'dist' !== entry.name ) {
				out = out.concat( allFiles( full, exts ) );
			}
		} else if (
			exts.some( function ( ext ) {
				return entry.name.endsWith( ext );
			} )
		) {
			out.push( full );
		}
	} );
	return out;
}

var frontCss = read( path.join( PLUGIN, 'assets', 'css', 'front.css' ) );
var themeCss = read( path.join( THEME, 'assets', 'css', 'theme.css' ) );
var blocksPhp = read( path.join( PLUGIN, 'includes', 'class-blocks.php' ) );
var customizePhp = read( path.join( THEME, 'inc', 'customize.php' ) );

var frontBare = stripCss( frontCss );
var themeBare = stripCss( themeCss );

/*
 * پیکره‌ی جست‌وجو: هر جایی که ممکن است کلاسی تولید شود. فایل‌های CSS
 * عمداً بیرون گذاشته می‌شوند، وگرنه هر کلاس «خودش را» تأیید می‌کرد.
 */
var sourceText = allFiles( WP, [ '.php', '.js', '.html', '.json' ] )
	.filter( function ( file ) {
		return ! file.endsWith( '.css' ) && ! /[\\/]tests[\\/]/.test( file );
	} )
	.map( read )
	.join( '\n' );

console.log( '==========================================================' );
console.log( 'آزمون ریسپانسیو، دسترس‌پذیری و گزینشگرهای یتیم' );
console.log( '==========================================================' );
console.log( '' );

/* ------------------------------------------------------------------
 * الف) گزینشگر یتیم
 * --------------------------------------------------------------- */

console.log( 'الف) هر کلاس در CSS باید در کد تولید شود' );
console.log( '----------------------------------------------------------' );

/*
 * استثناهای موجه:
 *   • manacore-hide-* : به‌صورت 'manacore-hide-' . $device ساخته می‌شود،
 *     پس رشته‌ی کامل در کد نیست.
 *   • koohe-no-dim : کلاس فرار برای کاربر است تا تصویری در حالت تیره
 *     ملایم نشود؛ در کد قالب تولید نمی‌شود و نباید بشود.
 *   • manacore-ad-slot-- : جایگاه تبلیغاتی با
 *     `manacore-ad-slot--%s` از کلید جایگاه ساخته می‌شود، پس نام کامل
 *     جایگاه‌ها (top/before-content/…) در کد نیست.
 */
var ALLOWED_ORPHANS = [ 'koohe-no-dim' ];
var DYNAMIC_PREFIXES = [ 'manacore-hide-', 'manacore-ad-slot--' ];

[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	var name = pair[ 0 ];
	var css  = pair[ 1 ];

	var classes = {};
	var re = /\.((?:manacore|koohe)-[a-z0-9-]+)/g;
	var m;
	while ( ( m = re.exec( css ) ) ) {
		classes[ m[ 1 ] ] = true;
	}

	var orphans = Object.keys( classes ).filter( function ( cls ) {
		if ( ALLOWED_ORPHANS.indexOf( cls ) > -1 ) {
			return false;
		}
		if (
			DYNAMIC_PREFIXES.some( function ( prefix ) {
				return 0 === cls.indexOf( prefix );
			} )
		) {
			return false;
		}
		return sourceText.indexOf( cls ) < 0;
	} ).sort();

	assert(
		0 === orphans.length,
		name + ': هیچ گزینشگر یتیمی ندارد (' + Object.keys( classes ).length + ' کلاس بررسی شد)' +
			( orphans.length ? ' — یتیم: ' + orphans.join( ', ' ) : '' )
	);
} );

// سه باگ مشخصی که رفع شد، نباید برگردند.
assert(
	frontBare.indexOf( '.manacore-cast-list' ) < 0,
	'گزینشگر ناکارآمد .manacore-cast-list حذف شده است'
);
assert(
	/\.manacore-cast\.is-layout-grid \.manacore-cast-track/.test( frontBare ),
	'چیدمان شبکه‌ای بازیگران کلاس واقعی (.manacore-cast-track) را هدف می‌گیرد'
);
assert(
	frontBare.indexOf( '[style*="--mc-meta-label"] .manacore-meta-label' ) < 0,
	'گزینشگر ناکارآمد پهنای برچسب حذف شده است'
);
assert(
	/\.manacore-meta-list\[style\*="--mc-meta-label"\] \.manacore-meta-row > dt/.test( frontBare ),
	'پهنای برچسب روی <dt> واقعی اعمال می‌شود'
);
assert(
	themeBare.indexOf( '.manacore-search-box' ) < 0,
	'گزینشگر ناکارآمد .manacore-search-box حذف شده است'
);
/*
 * سربرگ دیگر «کادر جستجو» ندارد؛ جستجو مثل مرجع به یک دکمه‌ی پیل
 * (آیکن + متن راهنما + ⌘K) تبدیل شده که پوسته‌ی تمام‌صفحه را باز می‌کند.
 * پس دو چیز سنجیده می‌شود: (۱) خودِ بلوک در پارت سربرگ هست، (۲) هیچ
 * قاعده‌ی CSS مرده‌ای برای کادر جستجوی حذف‌شده نمانده است.
 */
var headerPart = read( path.join( THEME, 'parts', 'header.html' ) );
assert(
	/wp:koohe\/search-trigger/.test( headerPart ),
	'دکمه‌ی جستجوی سربرگ (بلوک koohe/search-trigger) در پارت سربرگ هست'
);
assert(
	'/wp:manacore/search-box' === 'x' || headerPart.indexOf( 'manacore/search-box' ) < 0,
	'کادر جستجوی درون‌خطی از سربرگ برداشته شده است (جستجو با دکمه باز می‌شود)'
);
assert(
	'/.koohe-header-end .manacore-search' === 'x' || ! /\.koohe-header-end \.manacore-search\b/.test( themeBare ),
	'قاعده‌ی مرده‌ی کادر جستجوی سربرگ از theme.css حذف شده است'
);
assert(
	/\.koohe-search-trigger\b/.test( themeBare ),
	'دکمه‌ی جستجو در theme.css استایل دارد'
);

/* ------------------------------------------------------------------
 * ب) همپوشانی نقطه‌ی شکست
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ب) نقاط شکست نباید همپوشانی داشته باشند' );
console.log( '----------------------------------------------------------' );

/*
 * قواعد نوار مدیریت وردپرس استثنا هستند: هسته ارتفاع نوار را در
 * max-width:782px عوض می‌کند، پس قالب باید همان مرز را تکرار کند.
 * گزینشگرهای آن‌ها (.admin-bar …) با قواعد چیدمان تضاد ندارند.
 */
function breakpoints( css, kind ) {
	var out = {};
	var re  = new RegExp( '@media[^{]*\\(\\s*' + kind + '-width\\s*:\\s*(\\d+)px\\s*\\)', 'g' );
	var m;
	while ( ( m = re.exec( css ) ) ) {
		out[ m[ 1 ] ] = true;
	}
	return out;
}

// در front.css هیچ استثنایی نیست، پس همپوشانی ممنوع است.
var frontMax = breakpoints( frontBare, 'max' );
var frontMin = breakpoints( frontBare, 'min' );
var frontOverlap = Object.keys( frontMax ).filter( function ( bp ) {
	return frontMin[ bp ];
} );
assert(
	0 === frontOverlap.length,
	'front.css: هیچ نقطه‌ی شکستی هم max و هم min ندارد' +
		( frontOverlap.length ? ' — همپوشان: ' + frontOverlap.join( ', ' ) + 'px' : '' )
);

// قواعد چیدمانِ «تا تبلت» باید 781 باشند نه 782.
assert(
	! /@media \(max-width: 782px\)[\s\S]{0,200}?\.manacore-hero-content/.test( frontBare ),
	'عرض محتوای اسلایدر در مرز ۷۸۲px با قاعده‌ی دسکتاپ تضاد ندارد'
);
assert(
	/@media \(max-width: 781px\)[\s\S]{0,200}?\.manacore-hero-content/.test( frontBare ),
	'قاعده‌ی موبایلِ محتوای اسلایدر روی ۷۸۱px است'
);

// مرز موبایل باید با هسته (۵۹۹/۶۰۰) یکی باشد.
[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	assert(
		! /@media \(max-width: 600px\)/.test( pair[ 1 ] ),
		pair[ 0 ] + ': مرز موبایل ۵۹۹px است (نه ۶۰۰px که با کلاس‌های نمایش ناهمخوان بود)'
	);
} );

// در theme.css تنها استثنای مجاز، قواعد نوار مدیریت است.
var themeMax = breakpoints( themeBare, 'max' );
var themeMin = breakpoints( themeBare, 'min' );
var themeOverlap = Object.keys( themeMax ).filter( function ( bp ) {
	return themeMin[ bp ];
} );
var overlapOnlyAdminBar = themeOverlap.every( function ( bp ) {
	// هر بلوک max با این مرز فقط باید گزینشگر .admin-bar داشته باشد.
	var re = new RegExp( '@media \\(max-width: ' + bp + 'px\\) \\{([\\s\\S]*?)\\n\\}', 'g' );
	var m;
	var ok = true;
	while ( ( m = re.exec( themeBare ) ) ) {
		if ( m[ 1 ].indexOf( '.admin-bar' ) < 0 ) {
			ok = false;
		}
	}
	return ok;
} );
assert(
	overlapOnlyAdminBar,
	'theme.css: همپوشانی نقطه‌ی شکست تنها برای قواعد نوار مدیریت است (همسو با هسته)'
);

/* ------------------------------------------------------------------
 * پ) متغیر مرده
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'پ) هیچ متغیر تعیین‌نشده‌ای خوانده نشود' );
console.log( '----------------------------------------------------------' );

// این‌ها را PHP به‌صورت درون‌خطی روی عنصر می‌گذارد.
var INLINE_VARS = [ 'mc-poster-ratio', 'mc-title-lines', 'mc-meta-label', 'mc-hero-overlay' ];

[
	[ 'front.css', frontBare, 'mc-' ],
	[ 'theme.css', themeBare, 'koohe-' ],
].forEach( function ( row ) {
	var declared = {};
	var dRe = /--([a-z0-9-]+)\s*:/g;
	var dm;
	while ( ( dm = dRe.exec( row[ 1 ] ) ) ) {
		declared[ dm[ 1 ] ] = true;
	}

	var undeclared = [];
	var uRe = /var\(\s*--([a-z0-9-]+)/g;
	var um;
	while ( ( um = uRe.exec( row[ 1 ] ) ) ) {
		var v = um[ 1 ];
		if ( 0 !== v.indexOf( row[ 2 ] ) || declared[ v ] || INLINE_VARS.indexOf( v ) > -1 ) {
			continue;
		}
		if ( undeclared.indexOf( v ) < 0 ) {
			undeclared.push( v );
		}
	}

	assert(
		0 === undeclared.length,
		row[ 0 ] + ': هیچ متغیر تعیین‌نشده‌ای نمی‌خواند' +
			( undeclared.length ? ' — تعیین‌نشده: ' + undeclared.join( ', ' ) : '' )
	);
} );

/* ------------------------------------------------------------------
 * ت) هدف لمسی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ت) هدف لمسی امن (WCAG 2.5.5)' );
console.log( '----------------------------------------------------------' );

assert(
	/@media \(pointer: coarse\)/.test( frontBare ),
	'front.css برای نمایشگر لمسی قواعد جداگانه دارد'
);
assert(
	/@media \(pointer: coarse\)/.test( themeBare ),
	'theme.css برای نمایشگر لمسی قواعد جداگانه دارد'
);

// در حالت لمسی، هدف‌های کوچک با شبه‌عنصر بزرگ می‌شوند.
[ 'manacore-hero-dot', 'manacore-hero-arrow', 'manacore-filter-chip', 'manacore-season-tab', 'manacore-card-fav' ].forEach(
	function ( cls ) {
		assert(
			new RegExp( '\\.' + cls + '::after' ).test( frontBare ),
			'ناحیه‌ی لمسی .' + cls + ' با شبه‌عنصر بزرگ می‌شود'
		);
	}
);

assert(
	/min-width: 44px/.test( frontBare ) && /min-height: 44px/.test( frontBare ),
	'front.css هدف ۴۴px را تعریف می‌کند'
);
assert(
	/min-height: 44px/.test( themeBare ),
	'theme.css هدف ۴۴px را تعریف می‌کند'
);

// دکمه‌ی بالارو و نوار کنش نباید هم‌پوشان شوند.
assert(
	/\.koohe-has-actionbar \.koohe-to-top/.test( themeBare ),
	'دکمه‌ی بالارو هنگام وجود نوار کنش جابه‌جا می‌شود'
);

/* ------------------------------------------------------------------
 * ث) سرریز افقی و ناحیه‌ی امن
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ث) جلوگیری از سرریز و احترام به ناحیه‌ی امن' );
console.log( '----------------------------------------------------------' );

[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	assert(
		/overflow-wrap: anywhere/.test( pair[ 1 ] ),
		pair[ 0 ] + ': متن بلند صفحه را جانبی‌پیمایش نمی‌کند'
	);
	assert(
		/max-width: 100%/.test( pair[ 1 ] ),
		pair[ 0 ] + ': رسانه از ظرف بیرون نمی‌زند'
	);
} );

assert(
	/env\(\s*safe-area-inset-bottom/.test( themeBare ),
	'نوار پایین صفحه ناحیه‌ی امن گوشی‌های بدون دکمه‌ی خانه را رعایت می‌کند'
);
assert(
	/@media \(max-height: 500px\) and \(orientation: landscape\)/.test( frontBare ),
	'حالت افقی با ارتفاع کم پوشش داده شده است'
);

/* ------------------------------------------------------------------
 * ج) دسترس‌پذیری نشانه‌گذاری
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ج) دسترس‌پذیری نشانه‌گذاری' );
console.log( '----------------------------------------------------------' );

// نتایج جستجوی زنده با جاوااسکریپت جایگزین می‌شوند؛ بدون ناحیه‌ی زنده
// صفحه‌خوان هیچ‌گاه آن‌ها را اعلام نمی‌کرد.
assert(
	/data-search-results[\s\S]{0,120}?aria-live="polite"/.test( blocksPhp ),
	'نتایج جستجوی زنده ناحیه‌ی aria-live دارند'
);
assert(
	/data-search-results[\s\S]{0,120}?role="status"/.test( blocksPhp ),
	'نتایج جستجوی زنده role="status" دارند'
);
assert(
	/data-search-results[\s\S]{0,120}?aria-atomic="true"/.test( blocksPhp ),
	'فهرست نتایج یک‌جا خوانده می‌شود نه تکه‌تکه'
);

// نوار کنش باید عنوان دسترس‌پذیر داشته باشد.
assert(
	/<nav class="koohe-actionbar" aria-label=/.test( customizePhp ),
	'نوار کنش موبایل برچسب دسترس‌پذیر دارد'
);
assert(
	/function koohe_actionbar_active\(\)/.test( customizePhp ),
	'شرط نمایش نوار کنش در تابع مستقل و آزمون‌پذیر است'
);
assert(
	/is_singular\(\)/.test( customizePhp ) && /koohe_actionbar_items/.test( customizePhp ),
	'نوار کنش تنها در صفحه‌ی اثر و با وجود کنش واقعی چاپ می‌شود'
);
assert(
	/koohe-has-actionbar/.test( customizePhp ),
	'کلاس بدنه افزوده می‌شود تا فضای پایین صفحه باز شود'
);

// تمرکز دیدنی باید با outline باشد نه صرفاً box-shadow.
assert(
	/:focus-visible[\s\S]{0,80}?outline: 2px solid/.test( frontBare ),
	'حلقه‌ی تمرکز با outline ساخته می‌شود (در حالت کنتراست بالا هم می‌ماند)'
);

/* ------------------------------------------------------------------
 * چ) احترام به تنظیمات کاربر
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'چ) احترام به تنظیمات کاربر' );
console.log( '----------------------------------------------------------' );

[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	assert(
		/@media \(prefers-reduced-motion: reduce\)/.test( pair[ 1 ] ),
		pair[ 0 ] + ': کاهش حرکت را رعایت می‌کند'
	);
	assert(
		/@media \(prefers-contrast: more\)/.test( pair[ 1 ] ),
		pair[ 0 ] + ': حالت کنتراست بالا را پوشش می‌دهد'
	);
} );

/* ------------------------------------------------------------------
 * ح) توازن نحوی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ح) توازن نحوی' );
console.log( '----------------------------------------------------------' );

[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	var opens  = ( pair[ 1 ].match( /\{/g ) || [] ).length;
	var closes = ( pair[ 1 ].match( /\}/g ) || [] ).length;
	assert( opens === closes, 'آکولادهای ' + pair[ 0 ] + ' متوازن‌اند (' + opens + ' / ' + closes + ')' );
} );

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

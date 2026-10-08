/**
 * آزمون تنوع ظاهری قالب، الگو و بلوک‌ها (P5).
 *
 * ریشه‌ی مشکلی که این آزمون از بازگشتش جلوگیری می‌کند «گزینه‌ی بی‌اثر»
 * است: گزینه‌ای که در ویرایشگر دیده می‌شود ولی در خروجی هیچ اثری ندارد.
 * این حالت سه‌گونه رخ می‌دهد و هر سه پوشش داده شده‌اند:
 *
 *   ۱. فهرست گزینه‌ها در جاوااسکریپت سخت‌کد شده و با فهرست PHP یکی نیست
 *      (همان اشکالی که در نوار فیلتر هم بود: ۹ پارامتر در UI، ۶ در Query).
 *      نمونه‌ی واقعی: CARD_STYLES در blocks.js چهار گزینه داشت درحالی‌که
 *      PHP نُه سبک را می‌پذیرد.
 *   ۲. گزینه در هر دو سو هست ولی CSSِ متناظر نوشته نشده، پس کلاس تولید
 *      می‌شود و هیچ قاعده‌ای آن را نمی‌گیرد. نمونه‌ی واقعی: گزینه‌ی ارتفاع
 *      «تمام‌صفحه» (is-height-full) که هیچ قاعده‌ای نداشت.
 *   ۳. CSS نوشته شده ولی مقدارش نامعتبر است و مرورگر اعلان را دور
 *      می‌اندازد. نمونه‌های واقعی: «to inline-end» در linear-gradient و
 *      متغیر --mc-hero-dir که هیچ‌جا تعیین نمی‌شد.
 *
 * افزون بر این، اعتبارسنجی سمت سرور بررسی می‌شود: مقدار کهنه یا دست‌ساز
 * در ویژگی‌های بلوک نباید به کلاسِ بی‌اثر در HTML تبدیل شود.
 *
 * اجرا: node wp/tests/test-variants.cjs
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

/** حذف توضیحات بلوکی PHP/JS تا رشته‌های داخل توضیح، آزمون را فریب ندهند. */
function stripComments( src ) {
	return src
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /^[ \t]*\/\/.*$/gm, '' );
}

/** حذف توضیحات CSS. */
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

/** کلیدهای یک آرایه‌ی PHP به شکل 'key' => __( ... ). */
function phpKeys( body ) {
	var out = [];
	var re  = /'([a-z0-9_-]+)'\s*=>\s*__\(/g;
	var m;
	while ( ( m = re.exec( body ) ) ) {
		out.push( m[ 1 ] );
	}
	return out;
}

var dataPhp      = read( path.join( PLUGIN, 'includes', 'class-block-data.php' ) );
var supportPhp   = read( path.join( PLUGIN, 'includes', 'class-block-support.php' ) );
var blocksPhp    = read( path.join( PLUGIN, 'includes', 'class-blocks.php' ) );
var templatesPhp = read( path.join( PLUGIN, 'includes', 'class-templates.php' ) );
var frontCss     = read( path.join( PLUGIN, 'assets', 'css', 'front.css' ) );
var blocksJs     = read( path.join( PLUGIN, 'assets', 'js', 'blocks.js' ) );
var frontJs      = read( path.join( PLUGIN, 'assets', 'js', 'front.js' ) );
var stylesPhp    = read( path.join( THEME, 'inc', 'block-styles.php' ) );
var themeCss     = read( path.join( THEME, 'assets', 'css', 'theme.css' ) );

var dataCode      = stripComments( dataPhp );
var supportCode   = stripComments( supportPhp );
var blocksCode    = stripComments( blocksPhp );
var templatesCode = stripComments( templatesPhp );
var jsCode        = stripComments( blocksJs );
var stylesCode    = stripComments( stylesPhp );
var frontJsCode   = stripComments( frontJs );
var frontBare     = stripCss( frontCss );
var themeBare     = stripCss( themeCss );

console.log( '==========================================================' );
console.log( 'آزمون تنوع ظاهری قالب، الگو و بلوک‌ها' );
console.log( '==========================================================' );
console.log( '' );

/* ------------------------------------------------------------------
 * الف) فهرست‌های مشترک در Block_Data
 * --------------------------------------------------------------- */

console.log( 'الف) منبع یگانه‌ی گزینه‌های ظاهری' );
console.log( '----------------------------------------------------------' );

var SETS = [
	[ 'layouts', 'manacore_block_layouts', 8 ],
	[ 'card_styles', 'manacore_card_styles', 8 ],
	[ 'slider_styles', 'manacore_slider_styles', 5 ],
	[ 'slider_effects', 'manacore_slider_effects', 4 ],
	[ 'slider_alignments', 'manacore_slider_alignments', 3 ],
	/*
	 * دو سبک مانده است: `cards` (پیش‌فرض، همان کنش‌های سطر) و `table`
	 * (جدول خالی بدون کادر جدول). سبک‌های جعبه‌ای قدیمی حذف شدند.
	 */
	[ 'download_styles', 'manacore_download_styles', 2 ],
];

var keysOf = {};

SETS.forEach( function ( set ) {
	var name = set[ 0 ];
	var body = phpBody( dataCode, 'function ' + name + '()' );
	assert( '' !== body, 'تابع ' + name + '() تعریف شده است' );

	var keys = phpKeys( body );
	keysOf[ name ] = keys;
	assert(
		keys.length === set[ 2 ],
		name + '() دقیقاً ' + set[ 2 ] + ' گزینه دارد (' + keys.length + ')'
	);
	assert(
		body.indexOf( "'" + set[ 1 ] + "'" ) > -1,
		name + '() با فیلتر ' + set[ 1 ] + ' قابل توسعه است'
	);
} );

// همه در payload ویرایشگر باشند، وگرنه کنترل ویرایشگر خالی می‌ماند.
[
	[ 'layouts', 'layouts' ],
	[ 'cardStyles', 'card_styles' ],
	[ 'sliderStyles', 'slider_styles' ],
	[ 'sliderEffects', 'slider_effects' ],
	[ 'sliderAligns', 'slider_alignments' ],
	[ 'downloadStyles', 'download_styles' ],
].forEach( function ( pair ) {
	var re = new RegExp( "'" + pair[ 0 ] + "'\\s*=>\\s*self::" + pair[ 1 ] + "\\(\\)" );
	assert( re.test( dataCode ), 'payload کلید ' + pair[ 0 ] + ' را از ' + pair[ 1 ] + '() می‌گیرد' );
} );

/* ------------------------------------------------------------------
 * ب) اعتبارسنجی سمت سرور با pick()
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ب) اعتبارسنجی مقدار در سمت سرور' );
console.log( '----------------------------------------------------------' );

var pickBody = phpBody( supportCode, 'function pick(' );
assert( '' !== pickBody, 'کمک‌تابع Block_Support::pick() تعریف شده است' );
assert(
	/sanitize_key\(\s*\(string\)\s*\$value\s*\)/.test( pickBody ),
	'pick() ورودی را با sanitize_key پاک‌سازی می‌کند'
);
assert(
	/isset\(\s*\$allowed\[\s*\$value\s*\]\s*\)\s*\?\s*\$value\s*:\s*\$fallback/.test( pickBody ),
	'pick() مقدار خارج از فهرست را به پیش‌فرض برمی‌گرداند'
);

// هر گزینه‌ی ظاهری باید از pick() بگذرد.
[
	[ blocksCode, 'sliderStyle', 'slider_styles', 'cinematic' ],
	[ blocksCode, 'effect', 'slider_effects', 'fade' ],
	[ blocksCode, 'contentAlign', 'slider_alignments', 'start' ],
	[ blocksCode, 'boxStyle', 'download_styles', 'cards' ],
].forEach( function ( row ) {
	var re = new RegExp(
		'Block_Support::pick\\(\\s*\\$attrs\\[\\s*\'' + row[ 1 ] +
		'\'\\s*\\],\\s*Block_Data::' + row[ 2 ] + '\\(\\),\\s*\'' + row[ 3 ] + '\''
	);
	assert( re.test( row[ 0 ] ), 'ویژگی ' + row[ 1 ] + ' با ' + row[ 2 ] + '() اعتبارسنجی می‌شود' );
} );

assert(
	/Block_Support::pick\(\s*\$args\['style'\],\s*Block_Data::card_styles\(\),\s*'poster'\s*\)/.test( templatesCode ),
	'سبک کارت با card_styles() اعتبارسنجی می‌شود'
);

// دیگر نباید کلاس مستقیماً از ورودی خام ساخته شود.
assert(
	! /\$style\s*=\s*sanitize_key\(\s*\$args\['style'\]\s*\)/.test( templatesCode ),
	'سبک کارت دیگر با sanitize_key خام ساخته نمی‌شود (کلاس بی‌اثر می‌ساخت)'
);

var gridBody = phpBody( supportCode, 'function grid_classes(' );
assert( '' !== gridBody, 'بدنه‌ی grid_classes() یافت شد' );
assert(
	/self::pick\(\s*isset\(\s*\$attrs\['layout'\]\s*\)\s*\?\s*\$attrs\['layout'\]\s*:\s*''\s*,\s*Block_Data::layouts\(\),\s*'grid'\s*\)/.test( gridBody ),
	'grid_classes() چیدمان را با layouts() اعتبارسنجی می‌کند'
);
assert(
	! /switch\s*\(\s*\$layout\s*\)/.test( gridBody ),
	'switch سخت‌کدشده‌ی پیشین جای خود را به نگاشت داده است'
);

/* ------------------------------------------------------------------
 * پ) هیچ فهرست موازیِ سخت‌کدشده در جاوااسکریپت نمانده باشد
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'پ) نبودِ فهرست موازی در ویرایشگر' );
console.log( '----------------------------------------------------------' );

assert(
	/data\.cardStyles/.test( jsCode ),
	'CARD_STYLES از payload خوانده می‌شود نه فهرست سخت‌کدشده'
);

// هر کنترل تازه باید به کلید payload وصل باشد.
[
	[ 'sliderStyle', 'sliderStyles' ],
	[ 'effect', 'sliderEffects' ],
	[ 'contentAlign', 'sliderAligns' ],
	[ 'boxStyle', 'downloadStyles' ],
].forEach( function ( pair ) {
	var re = new RegExp(
		"attr:\\s*'" + pair[ 0 ] + "'[\\s\\S]{0,200}?toOptions\\(\\s*data\\." + pair[ 1 ] + '\\s*\\)'
	);
	assert( re.test( jsCode ), 'کنترل ' + pair[ 0 ] + ' گزینه‌ها را از data.' + pair[ 1 ] + ' می‌گیرد' );
} );

assert(
	/attr:\s*'overlay'[\s\S]{0,160}?type:\s*'range'/.test( jsCode ),
	'کنترل شدت پوشش تصویر به‌صورت لغزنده افزوده شده است'
);

/* ------------------------------------------------------------------
 * ت) هر گزینه CSS متناظر دارد (گزینه‌ی بی‌اثر نمانده باشد)
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ت) پوشش CSS برای همه‌ی گزینه‌ها' );
console.log( '----------------------------------------------------------' );

// چیدمان‌های حلقه: نگاشت داخل grid_classes باید با CSS بخواند.
var layoutMap = {
	carousel: 'is-scroll',
	list: 'is-list',
	masonry: 'is-masonry',
	spotlight: 'is-spotlight',
	showcase: 'is-showcase',
	metro: 'is-metro',
	ranking: 'is-ranking',
};

var layoutMissing = [];
keysOf.layouts.forEach( function ( key ) {
	if ( 'grid' === key ) {
		return; // پایه؛ کلاس افزوده ندارد.
	}
	var cls = layoutMap[ key ];
	if ( ! cls ) {
		layoutMissing.push( key + ' (نگاشت نشده)' );
		return;
	}
	if ( gridBody.indexOf( "'" + cls + "'" ) < 0 ) {
		layoutMissing.push( key + ' (در PHP نیست)' );
		return;
	}
	if ( frontBare.indexOf( '.manacore-grid-cards.' + cls ) < 0 ) {
		layoutMissing.push( key + ' (در CSS نیست)' );
	}
} );
assert(
	0 === layoutMissing.length,
	'هر ' + keysOf.layouts.length + ' چیدمان کلاس و CSS دارد' +
		( layoutMissing.length ? ' — کم: ' + layoutMissing.join( ', ' ) : '' )
);

// سبک‌های کارت.
var cardMissing = keysOf.card_styles.filter( function ( key ) {
	return 'poster' !== key && frontBare.indexOf( '.manacore-card.is-style-' + key ) < 0;
} );
assert(
	0 === cardMissing.length,
	'هر ' + keysOf.card_styles.length + ' سبک کارت CSS دارد' +
		( cardMissing.length ? ' — کم: ' + cardMissing.join( ', ' ) : '' )
);

// سبک‌های اسلایدر.
var sliderMissing = keysOf.slider_styles.filter( function ( key ) {
	return 'cinematic' !== key && frontBare.indexOf( '.manacore-hero.is-slider-' + key ) < 0;
} );
assert(
	0 === sliderMissing.length,
	'هر ' + keysOf.slider_styles.length + ' سبک اسلایدر CSS دارد' +
		( sliderMissing.length ? ' — کم: ' + sliderMissing.join( ', ' ) : '' )
);

// جلوه‌های گذر.
var effectMissing = keysOf.slider_effects.filter( function ( key ) {
	return 'fade' !== key && frontBare.indexOf( '.manacore-hero.is-effect-' + key ) < 0;
} );
assert(
	0 === effectMissing.length,
	'هر ' + keysOf.slider_effects.length + ' جلوه‌ی گذر CSS دارد' +
		( effectMissing.length ? ' — کم: ' + effectMissing.join( ', ' ) : '' )
);

// جای‌گیری محتوا.
var alignMissing = keysOf.slider_alignments.filter( function ( key ) {
	return 'start' !== key && frontBare.indexOf( '.manacore-hero.is-align-' + key ) < 0;
} );
assert(
	0 === alignMissing.length,
	'هر ' + keysOf.slider_alignments.length + ' جای‌گیری محتوا CSS دارد' +
		( alignMissing.length ? ' — کم: ' + alignMissing.join( ', ' ) : '' )
);

/*
 * سبک‌های بخش دانلود.
 *
 * قرارداد پیشین (`.manacore-links.is-box-*` با پنج سبک جعبه و آکاردئون
 * بومی) به درخواست کارفرما کنار گذاشته شد: بخش دانلود باید دقیقاً مثل
 * مرجع `cinora` یک جدول `download-section` باشد. این آزمون اکنون همان
 * قرارداد تازه را قفل می‌کند تا سبک‌های قدیمی برنگردند.
 */
var boxMissing = keysOf.download_styles.filter( function ( key ) {
	return 'cards' !== key && frontBare.indexOf( '.download-section.is-' + key ) < 0;
} );
assert(
	0 === boxMissing.length,
	'هر ' + keysOf.download_styles.length + ' سبک بخش دانلود CSS دارد' +
		( boxMissing.length ? ' — کم: ' + boxMissing.join( ', ' ) : '' )
);

// گزینه‌ی ارتفاعِ «تمام‌صفحه» که پیش‌تر هیچ قاعده‌ای نداشت.
var heightOpts = [];
var heightRe   = /attr:\s*'height'[\s\S]{0,420}?\]/;
var heightHit  = jsCode.match( heightRe );
if ( heightHit ) {
	var vRe = /value:\s*'([a-z]+)'/g;
	var vm;
	while ( ( vm = vRe.exec( heightHit[ 0 ] ) ) ) {
		heightOpts.push( vm[ 1 ] );
	}
}
assert( heightOpts.length > 0, 'گزینه‌های ارتفاع اسلایدر در ویرایشگر یافت شد (' + heightOpts.join( ', ' ) + ')' );
var heightMissing = heightOpts.filter( function ( key ) {
	return frontBare.indexOf( '.manacore-hero.is-height-' + key ) < 0;
} );
assert(
	0 === heightMissing.length,
	'هر گزینه‌ی ارتفاع قاعده‌ی CSS دارد' +
		( heightMissing.length ? ' — کم: ' + heightMissing.join( ', ' ) : '' )
);
/*
 * ارتفاع سربرگ تصویری با متغیر `--mc-hero-h` تعیین می‌شود و حالت
 * «تمام‌صفحه» مقدار ۱۰۰vh می‌گیرد؛ پس هیچ سقف ثابتی آن را خنثی نمی‌کند.
 */
assert(
	/--mc-hero-h/.test( frontBare ),
	'ارتفاع سربرگ تصویری با متغیر --mc-hero-h تعیین می‌شود'
);
assert(
	/\.manacore-hero\.is-height-full[\s\S]{0,40}?100vh/.test( frontBare ),
	'حالت تمام‌صفحه ارتفاع ۱۰۰vh می‌گیرد و سقف ثابتی ندارد'
);
assert(
	/\.manacore-hero-bg[\s\S]{0,240}?object-fit:\s*cover/.test( frontBare )
		&& /\.manacore-hero-bg[\s\S]{0,240}?height:\s*100%/.test( frontBare ),
	'تصویر زمینه‌ی سربرگ خودش کشیده می‌شود (width/height/object-fit روی همان عنصر)'
);

/* ------------------------------------------------------------------
 * ث) سبک‌های بلوک قالب
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ث) سبک‌های بلوک قالب' );
console.log( '----------------------------------------------------------' );

var themeStyleNames = [];
var tsRe = /'name'\s*=>\s*'(koohe-[a-z0-9-]+)'/g;
var tsm;
while ( ( tsm = tsRe.exec( stylesCode ) ) ) {
	themeStyleNames.push( tsm[ 1 ] );
}

assert( themeStyleNames.length >= 21, 'حداقل ۲۱ سبک بلوک ثبت شده است (' + themeStyleNames.length + ')' );

var themeMissing = themeStyleNames.filter( function ( name ) {
	return themeBare.indexOf( 'is-style-' + name ) < 0;
} );
assert(
	0 === themeMissing.length,
	'هر سبک بلوک قالب CSS دارد' +
		( themeMissing.length ? ' — بدون CSS: ' + themeMissing.join( ', ' ) : '' )
);

// چیدمان‌های تازه‌ی حلقه‌ی کوئری هسته.
[ 'koohe-showcase', 'koohe-ranking', 'koohe-listing', 'koohe-overlay', 'koohe-compact' ].forEach(
	function ( name ) {
		assert(
			themeStyleNames.indexOf( name ) > -1 && themeBare.indexOf( '.is-style-' + name ) > -1,
			'سبک ' + name + ' ثبت شده و CSS دارد'
		);
	}
);

[ 'koohe-elevated', 'koohe-accent-bar' ].forEach( function ( name ) {
	assert(
		themeStyleNames.indexOf( name ) > -1 && themeBare.indexOf( '.is-style-' + name ) > -1,
		'سبک گروه ' + name + ' ثبت شده و CSS دارد'
	);
} );

// هر سبک باید برچسب ترجمه‌پذیر داشته باشد.
var labelCount = ( stylesCode.match( /'label'\s*=>\s*__\(/g ) || [] ).length;
assert(
	labelCount === themeStyleNames.length,
	'همه‌ی سبک‌ها برچسب ترجمه‌پذیر دارند (' + labelCount + ' / ' + themeStyleNames.length + ')'
);

/* ------------------------------------------------------------------
 * ج) قرارداد بخش دانلود و صفحه‌ی پخش (هم‌شکل مرجع سینورا)
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ج) بخش دانلود و صفحه‌ی پخش' );
console.log( '----------------------------------------------------------' );

/*
 * چرا این بخش بازنویسی شد؟ درخواست کارفرما این بود که بخش دانلود
 * *عیناً* `class="download-section"` باشد (نه جعبه‌های اختصاصی)، و
 * کلیک «پخش» به صفحه‌ای مثل «NOW PLAYING · DEMO» مرجع برود که خودش یک
 * قالب در ویرایشگر سایت است. آزمون‌های قدیمی همین قرارداد را می‌سنجند
 * تا کسی بدون دلیل به عقب برنگرداند.
 */
var linksBody = phpBody( templatesCode, 'function links(' );
assert( '' !== linksBody, 'بدنه‌ی links() یافت شد' );

assert(
	/<section class="download-section/.test( linksBody ),
	'بخش دانلود با <section class="download-section"> ساخته می‌شود'
);
assert(
	/data-manacore-downloads/.test( linksBody ),
	'بخش دانلود شناسه‌ی اثر را برای تب فصل‌ها نگه می‌دارد'
);
var rowBody = phpBody( templatesCode, 'function link_row(' );
assert(
	/class="download-table"/.test( linksBody ) && /download-table-header/.test( linksBody ),
	'ساختار جدول دانلود (سرستون و ستون‌ها) ساخته می‌شود'
);
assert(
	/class="download-row"/.test( rowBody ) && /class="download-actions"/.test( rowBody ) &&
		/quality-name/.test( rowBody ) && /format-tag/.test( rowBody ),
	'ردیف‌ها کیفیت، فرمت و کنش‌ها را دارند'
);
assert(
	'' !== phpBody( templatesCode, 'function link_row(' ),
	'ردیف دانلود در تابع مستقل link_row() ساخته می‌شود'
);
assert(
	! /manacore-links(?!-block)|is-box-/.test( templatesCode ),
	'هیچ نشانه‌ای از جعبه‌های قدیمی دانلود در PHP نمانده است'
);
assert(
	! /manacore-links(?!-block)|is-box-|link_icon/.test( blocksCode + jsCode ),
	'هیچ نشانه‌ای از جعبه‌های قدیمی دانلود در بلوک و ویرایشگر نمانده است (جز پوشش بلوک)'
);
assert(
	-1 === frontBare.indexOf( '.manacore-links' ),
	'CSS سبک‌های جعبه‌ی دانلود پاک شده است'
);
assert(
	/\.download-section \.download-actions/.test( frontBare ),
	'CSS بخش دانلود کنش‌ها را می‌چیند'
);

// صفحه‌ی پخش: بلوک، قالب ویرایشگر و CSS.
assert(
	/'manacore\/player-page'/.test( blocksCode ),
	'بلوک manacore/player-page در PHP ثبت شده است'
);
assert(
	function () { return /function render_player_page\(/.test( blocksCode ); }(),
	'متد render_player_page() خروجی صفحه‌ی پخش را می‌سازد'
);
var watchTemplate = read( path.join( THEME, 'templates', 'page-watch.html' ) );
assert(
	/wp:manacore\/player-page/.test( watchTemplate ),
	'قالب 「پخش آنلاین」 در ویرایشگر سایت بلوک پخش را نشان می‌دهد'
);
assert(
	/wp:manacore\/download-links/.test( read( path.join( THEME, 'templates', 'single-movie.html' ) ) ) === false ||
		/wp:manacore\/player-page/.test( watchTemplate ),
	'صفحه‌ی پخش مستقل از قالب جزئیات است'
);
assert(
	/\.player-page/.test( frontBare ) && /\.video-frame/.test( frontBare ) &&
		/\.player-controls/.test( frontBare ) && /\.player-badges/.test( frontBare ),
	'CSS صفحه‌ی پخش (قاب ویدئو، نوار کنترل و نشان‌ها) نوشته شده است'
);
assert(
	/data-player-quality/.test( frontJsCode ) && /data-player-cinema/.test( frontJsCode ) &&
		/data-player-fullscreen/.test( frontJsCode ),
	'کنترل‌های پخش (کیفیت، سینما، تمام‌صفحه) در front.js پیاده شده‌اند'
);

/* ------------------------------------------------------------------
 * چ) اعتبار مقادیر CSS
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'چ) اعتبار مقادیر CSS' );
console.log( '----------------------------------------------------------' );

// «to inline-end» در CSS معتبر نیست و مرورگر کل اعلان را دور می‌اندازد.
[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	assert(
		pair[ 1 ].indexOf( 'to inline-end' ) < 0 && pair[ 1 ].indexOf( 'to inline-start' ) < 0,
		pair[ 0 ] + ' جهت گرادیان نامعتبر (to inline-*) ندارد'
	);
	assert(
		! /transform-origin:\s*(inset|inline)-/.test( pair[ 1 ] ),
		pair[ 0 ] + ' مقدار نامعتبر برای transform-origin ندارد'
	);
} );

// هیچ متغیر تعیین‌نشده‌ای خوانده نشود (مگر آن‌ها که PHP درون‌خطی می‌گذارد).
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
		if ( 0 !== v.indexOf( row[ 2 ] ) ) {
			continue;
		}
		if ( declared[ v ] || INLINE_VARS.indexOf( v ) > -1 ) {
			continue;
		}
		if ( undeclared.indexOf( v ) < 0 ) {
			undeclared.push( v );
		}
	}

	assert(
		0 === undeclared.length,
		row[ 0 ] + ' هیچ متغیر تعیین‌نشده‌ای نمی‌خواند' +
			( undeclared.length ? ' — تعیین‌نشده: ' + undeclared.join( ', ' ) : '' )
	);
} );

// --mc-hero-dir پیش‌تر خوانده می‌شد ولی هیچ‌جا تعیین نمی‌شد.
assert(
	frontBare.indexOf( '--mc-hero-dir' ) < 0,
	'متغیر مرده‌ی --mc-hero-dir حذف شده و جهت پوشش با [dir] تعیین می‌شود'
);
/*
 * پوشش تصویر پیش‌تر با `.manacore-hero-bg::after` ساخته می‌شد؛ شبه‌عنصر
 * روی عنصر جانشین‌شده (`<img>`) رندر نمی‌شود، پس اسلایدر هیچ پرده‌ای
 * نداشت و متن روی بخش روشن تصویر می‌افتاد. اکنون پرده یک عنصر واقعی
 * (`.manacore-hero-gradient`) است و جهت آن برای LTR جدا نوشته می‌شود.
 */
assert(
	/\.manacore-hero-gradient/.test( frontBare ) && frontBare.indexOf( '.manacore-hero-bg::after' ) < 0,
	'پرده‌ی تصویر روی عنصر واقعی است، نه شبه‌عنصر روی <img>'
);
assert(
	/\[dir="ltr"\] \.manacore-hero-gradient/.test( frontBare ),
	'جهت پوشش اسلایدر برای LTR جداگانه بازنویسی می‌شود'
);

/* ------------------------------------------------------------------
 * ذ) اجزای اسلایدر هیرو (هم‌شکل مرجع cinora)
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ذ) اجزای اسلایدر هیرو' );
console.log( '----------------------------------------------------------' );

var heroSource = phpBody( blocksCode, 'function render_hero_slider(' );

[
	[ 'manacore-hero-image', 'لایه‌ی تصویر' ],
	[ 'manacore-hero-gradient', 'پرده‌ی گرادیانی' ],
	[ 'manacore-hero-grain', 'دانه‌ی سطح تصویر' ],
	[ 'manacore-hero-quality', 'نشان کیفیت' ],
	[ 'manacore-hero-eyebrow', 'نشان ویژه' ],
	[ 'manacore-hero-original', 'عنوان لاتین' ],
	[ 'manacore-hero-rating', 'جعبه‌ی امتیاز' ],
	[ 'manacore-hero-age', 'رده‌ی سنی' ],
	[ 'manacore-hero-tags', 'برچسب‌های زبان و ژانر' ],
	[ 'manacore-hero-wordmark', 'واترمارک' ],
	[ 'manacore-hero-controls', 'نوار کنترل' ],
	[ 'manacore-hero-counter', 'شمارنده‌ی اسلاید' ],
	[ 'manacore-hero-note', 'یادداشت پایین' ],
	[ 'manacore-hero-save', 'دکمه‌ی لیست تماشا' ],
].forEach( function ( row ) {
	assert(
		frontBare.indexOf( '.' + row[ 0 ] ) > -1 && heroSource.indexOf( row[ 0 ] ) > -1,
		row[ 1 ] + ' هم CSS دارد و هم در رندر بلوک ساخته می‌شود'
	);
} );

assert(
	/\.manacore-hero-bg[\s\S]{0,240}?width:\s*79%/.test( frontBare ) &&
		/\.manacore-hero-bg[\s\S]{0,240}?inset-inline-end:\s*0/.test( frontBare ),
	'تصویر ۷۹٪ عرض را می‌گیرد و از سمت پایان کادر می‌چسبد (هندسه‌ی مرجع)'
);
assert(
	/\.manacore-hero-content[\s\S]{0,300}?width:\s*53%/.test( frontBare ),
	'ستون محتوا عرض ۵۳٪ دارد (هندسه‌ی مرجع)'
);
assert(
	/--mc-hero-scrim:\s*[0-9]/.test( frontBare ) && /--mc-hero-tilt:/.test( frontBare ) &&
		/--mc-hero-overlay/.test( heroSource ) &&
		/\[style\*="--mc-hero-overlay"\][\s\S]{0,140}?opacity: var\(--mc-hero-overlay\)/.test( frontBare ),
	'متغیرهای پرده، چرخش و شدت پوشش اسلایدر تعریف شده‌اند و کلید پوشش به CSS می‌رسد'
);
assert(
	/--mc-hero-tilt/.test( frontJsCode ) && /data-tilt/.test( heroSource ) && /attr: 'tilt'/.test( jsCode ),
	'چرخش ملایم با کلید tilt در ویرایشگر کنترل و در JS به متغیر CSS نوشته می‌شود'
);
assert(
	/data-manacore-watchlist/.test( heroSource ) && /Watchlist::instance\(\)->has/.test( heroSource ),
	'دکمه‌ی لیست تماشا از همان قرارداد data-manacore-watchlist و وضعیت واقعی کاربر استفاده می‌کند'
);
assert(
	/data-hero-prev/.test( heroSource ) && /data-hero-next/.test( heroSource ) &&
		/data-hero-dot/.test( heroSource ) && /aria-pressed/.test( heroSource ) &&
		! /role="tab"/.test( heroSource ) && /aria-pressed/.test( frontJsCode ),
	'کنترل‌های اسلایدر نشانه‌های data و وضعیت دسترس‌پذیری مرجع (aria-pressed) را دارند'
);
assert(
	/manacore-hero-counter" dir="ltr"/.test( heroSource ),
	'شمارنده‌ی اسلاید در جهت LTR و با رقم لاتین است (مثل مرجع)'
);
assert(
	/showEyebrow/.test( heroSource ) && /showQuality/.test( heroSource ) && /showLanguages/.test( heroSource ) &&
		/showWordmark/.test( heroSource ) && /showCounter/.test( heroSource ) && /showNote/.test( heroSource ),
	'هر جزء اسلایدر کلید روشن/خاموش خودش را دارد'
);
assert(
	/manacore_tagline/.test( heroSource ) && /manacore_original_title/.test( blocksCode ) &&
		/manacore_is_featured/.test( blocksCode ) && /manacore_dubbed/.test( blocksCode ) &&
		/age_rating/.test( blocksCode ),
	'داده‌های اسلایدر از فراداده و تاکسونومی‌های خود اثر خوانده می‌شوند (نه متن ثابت)'
);
assert(
	'' !== phpBody( blocksCode, 'function hero_language_tags(' ) &&
		'' !== phpBody( blocksCode, 'function hero_quality_badge(' ) &&
		'' !== phpBody( blocksCode, 'function hero_wordmark(' ) &&
		'' !== phpBody( blocksCode, 'function hero_runtime_text(' ),
	'کمک‌تابع‌های اسلایدر (زبان، کیفیت، واترمارک، مدت) جدا و آزمون‌پذیرند'
);
assert(
	/\.manacore-hero-wordmark,\s*\n\s*\.manacore-hero-note\s*\{\s*\n\s*display:\s*none/.test( frontBare ),
	'در موبایل واترمارک و یادداشت اسلایدر پنهان می‌شوند (مثل مرجع)'
);
assert(
	/@media \(max-width: 781px\)[\s\S]{0,200}?\.manacore-hero-content[\s\S]{0,200}?justify-content:\s*flex-end/.test( frontBare ),
	'در موبایل محتوا تمام‌عرض و از پایین کادر می‌آید (مثل مرجع)'
);

/* ------------------------------------------------------------------
 * ح) امنیت خروجی و توازن نحوی
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ح) امنیت خروجی و توازن نحوی' );
console.log( '----------------------------------------------------------' );

var heroBody = phpBody( blocksCode, 'function render_hero_slider(' );
assert( '' !== heroBody, 'بدنه‌ی render_hero_slider() یافت شد' );

// هر echo خام (بدون esc_*) در بدنه یک خطر است.
var rawEchoes = ( heroBody.match( /<\?php echo \$[a-z_]+/gi ) || [] );
assert(
	0 === rawEchoes.length,
	'هیچ خروجی خام بدون esc_* در اسلایدر نیست' +
		( rawEchoes.length ? ' (' + rawEchoes.join( ', ' ) + ')' : '' )
);
assert(
	/esc_attr\(\s*\$overlay \/ 100\s*\)/.test( heroBody ),
	'شدت پوشش با esc_attr فرار داده می‌شود'
);
assert(
	/max\(\s*0,\s*min\(\s*100,\s*\(int\) \$attrs\['overlay'\]\s*\)\s*\)/.test( heroBody ),
	'شدت پوشش به بازه‌ی ۰ تا ۱۰۰ محدود می‌شود'
);

/*
 * ردیف‌های دانلود هم مثل اسلایدر باید کامل فرار داده شوند؛ آزمون قبلی
 * این را روی تابع آکاردئون (link_group) می‌سنجید که حذف شده است.
 */
var safeTernary = linksBody + rowBody;
safeTernary = safeTernary.replace( /<\?php echo \$\w+ \? [^>]*?\?>/g, '' );
var rowRaw = ( safeTernary.match( /<\?php echo \$[a-z_]+/gi ) || [] );
assert(
	0 === rowRaw.length,
	'هیچ خروجی خام بدون esc_* در بخش دانلود نیست' +
		( rowRaw.length ? ' (' + rowRaw.join( ', ' ) + ')' : '' )
);

[
	[ 'front.css', frontBare ],
	[ 'theme.css', themeBare ],
].forEach( function ( pair ) {
	var opens  = ( pair[ 1 ].match( /\{/g ) || [] ).length;
	var closes = ( pair[ 1 ].match( /\}/g ) || [] ).length;
	assert( opens === closes, 'آکولادهای ' + pair[ 0 ] + ' متوازن‌اند (' + opens + ' / ' + closes + ')' );
} );

/* ------------------------------------------------------------------
 * ط) کشف داستان‌ها: صفحه‌بندی، «بیشتر»، حالت خالی، مرتب‌سازی
 *
 * هر بند این بخش یک رفتار واقعیِ موج یازدهم را می‌سنجد؛ دو تای آخرین
 * نگهبانِ دو اشکالی هستند که همین‌جا کشف و رفع شدند:
 *   • پیمایش روی مجموعه‌ی زنده‌ی `children` هنگام افزودن کارت تازه —
 *     هر کارت دوم از قلم می‌افتاد (صفحه‌ی دوم با ۲ کارت، ۱ کارت اضافه
 *     می‌کرد).
 *   • `CAST(... AS SIGNED)` در مرتب‌سازی عددی امتیاز — اعشار ۹.۴ و ۹.۲
 *     هر دو ۹ می‌شدند و ترتیب تابع تاریخ (تصادفی) بود.
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ط) کشف داستان‌ها: صفحه‌بندی، بارگذاری بیشتر و حالت خالی' );
console.log( '----------------------------------------------------------' );

var paginationBody = phpBody( supportCode, 'function pagination_attributes(' );
assert( '' !== paginationBody, 'تابع pagination_attributes() یافت شد' );
assert( /'showFilterSummary'/.test( paginationBody ), 'ویژگی showFilterSummary در ویژگی‌های صفحه‌بندی هست' );
assert( /'loadMore'/.test( paginationBody ), 'ویژگی loadMore در ویژگی‌های صفحه‌بندی هست' );
assert( /'loadText'/.test( paginationBody ), 'ویژگی loadText در ویژگی‌های صفحه‌بندی هست' );

/* «شمار آثار» و «بیشتر» به max_num_pages/found_posts نیاز دارند. */
var queryArgsBody = phpBody( supportCode, 'function query_args(' );
assert(
	/showFilterSummary/.test( queryArgsBody ) && /loadMore/.test( queryArgsBody ) &&
	/\$args\['no_found_rows'\]\s*=\s*false/.test( queryArgsBody ),
	'query_args() برای خلاصه‌ی نتیجه و «بیشتر» شمارش کل را روشن می‌کند'
);

var loadMoreBody = phpBody( blocksCode, 'function render_load_more(' );
assert( '' !== loadMoreBody, 'تابع render_load_more() یافت شد' );
assert( /data-manacore-load-more/.test( loadMoreBody ), 'دکمه‌ی «بیشتر» نشانه‌ی data-manacore-load-more دارد' );
assert( /add_query_arg\(\s*'paged'/.test( loadMoreBody ), 'نشانی دکمه با add_query_arg( \'paged\', … ) ساخته می‌شود' );
assert(
	/داستان\u200c?های بیشتر/.test( loadMoreBody ),
	'برچسب پیش‌فرض دکمه «داستان‌های بیشتر» است'
);

var emptyBody = phpBody( blocksCode, 'function render_discovery_empty(' );
assert( '' !== emptyBody, 'تابع render_discovery_empty() یافت شد' );
assert(
	/با این فیلترها داستانی پیدا نشد\./.test( emptyBody ) && /این صفحه دیگر داستانی ندارد\./.test( emptyBody ),
	'هر دو سرصفحه‌ی حالت خالی (فیلترشده و صفحه‌ی بی‌سرانجام) موجود است'
);
assert(
	/نمایش همه\u200c?ی آثار/.test( emptyBody ) && /manacore_archive_base_url\(/.test( emptyBody ),
	'حالت خالی دکمه‌ی «نمایش همه‌ی آثار» با نشانی پایه‌ی آرشیو دارد'
);

var summaryBody = phpBody( blocksCode, 'function render_filter_summary(' );
assert( '' !== summaryBody, 'تابع render_filter_summary() یافت شد' );
assert(
	/results-count-number/.test( summaryBody ) && /results-clear-all/.test( summaryBody ),
	'خلاصه‌ی نتیجه شمارنده و دکمه‌ی پاک‌سازی دارد'
);
assert( /active-filter-chips/.test( summaryBody ), 'تراشه‌های فیلتر فعال در خلاصه‌ی نتیجه رندر می‌شوند' );

assert(
	/Array\.prototype\.slice\.call\(\s*page\.children\s*\)/.test( frontJsCode ),
	'initLoadMore() پیش از افزودن، از children رونوشت می‌گیرد (هر کارت دوم حذف نشود)'
);

var queryPhp      = stripComments( read( path.join( PLUGIN, 'includes', 'class-query.php' ) ) );
var blockQueryPhp = stripComments( read( path.join( PLUGIN, 'includes', 'class-block-query.php' ) ) );
var functionsPhp  = stripComments( read( path.join( PLUGIN, 'includes', 'functions.php' ) ) );

assert(
	/'rating'\s*=>\s*'manacore_imdb_rating'/.test( functionsPhp ),
	'نگاشت مرتب‌سازی امتیاز در manacore_sort_query_args() تعریف شده است'
);
assert(
	/DECIMAL\(10,2\)/.test( queryPhp ) && /DECIMAL\(10,2\)/.test( blockQueryPhp ),
	'مرتب‌سازی عددی با DECIMAL(10,2) انجام می‌شود، نه SIGNED (اعشار امتیاز بریده نشود)'
);
/* مقصد «مشاهده همه»: تنها منبع حقیقت و ترجیح برگه‌ی کشف. */
var resolveBody = phpBody( supportCode, 'function resolve_more_url(' );
assert( '' !== resolveBody, 'تابع مشترک resolve_more_url() یافت شد' );
assert(
	/manacore_discovery_url\(/.test( resolveBody ),
	'resolve_more_url() برگه‌ی کشف را بر آرشیو مقدم می‌دارد (مثل a.text-link مرجع)'
);
assert(
	/Block_Support::resolve_more_url\(\s*\$attrs,\s*\$context_type\s*\)/.test( blocksCode ),
	'render_titles_grid() هم از همان تابع مشترک استفاده می‌کند (دو پیاده‌سازی جدا نماند)'
);
assert(
	/class="text-link manacore-more-link"/.test( supportCode ),
	'پیوند «مشاهده همه» کلاس مرجع (text-link) را هم دارد'
);

/* ------------------------------------------------------------------
 * ی) سایدبار «فیلتر پیشرفته» — همان گروه‌های `.filter-sidebar` مرجع
 *
 * سه چیز سنجیده می‌شود: (۱) ویژگی‌های تازه در تعریف بلوک و پنل ویرایشگر
 * (وگرنه چیزی که دیده می‌شود ویرایش‌پذیر نیست)، (۲) رندر و CSS هر جزء
 * (وگرنه کلاس بی‌قاعده می‌ماند)، و (۳) مسیر کوئری هر فیلتر در **هر دو**
 * حلقه (آرشیو و بلوک) — چون فیلتری که فقط در یکی اثر کند، فیلتر بی‌اثر
 * است.
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ی) سایدبار «فیلتر پیشرفته»' );
console.log( '----------------------------------------------------------' );

var metasPhp = stripComments( read( path.join( PLUGIN, 'includes', 'class-meta.php' ) ) );
var filterAttrs = phpBody( blocksCode, "function render_filter_bar(" );
var groupsBody  = phpBody( blocksCode, "function render_sidebar_groups(" );
var footerBody  = phpBody( blocksCode, "function render_sidebar_footer(" );

assert( '' !== groupsBody, 'متد render_sidebar_groups() وجود دارد' );
assert( '' !== footerBody, 'متد render_sidebar_footer() وجود دارد' );

[
	'sidebarTitle',
	'checkTaxonomy',
	'showGenreChecks',
	'showYearRange',
	'showRating',
	'ratingMax',
	'showDubbed',
	'dubbedLabel',
	'showHint',
	'hintUrl',
	'resetLabel',
].forEach( function ( attr ) {
	assert(
		blocksCode.indexOf( "'" + attr + "'" ) !== -1,
		'ویژگی «' + attr + '» در تعریف بلوک هست'
	);
	assert(
		jsCode.indexOf( "'" + attr + "'" ) !== -1,
		'ویژگی «' + attr + '» در پنل ویرایشگر هم هست'
	);
} );

assert(
	/'sidebar'\s*===\s*a\.formLayout|\( ?'sidebar' ?\?/.test( jsCode ) && /value: 'sidebar'/.test( jsCode ),
	'گزینه‌ی چیدمان «سایدبار پیشرفته» در گزینشگر ویرایشگر هست'
);

[
	[ 'genre-checks', 'فهرست تیک‌زنی ژانر' ],
	[ 'custom-check', 'مربع تیک سفارشی' ],
	[ 'year-range', 'بازه‌ی سال' ],
	[ 'rating-range', 'لغزنده‌ی امتیاز' ],
	[ 'range-labels', 'برچسب‌های بازه' ],
	[ 'toggle-label', 'کلید دوبله' ],
	[ 'toggle-switch', 'کلید کشویی' ],
	[ 'reset-filters', 'دکمه‌ی پاک‌سازی' ],
	[ 'filter-hint', 'پنل راهنما' ],
].forEach( function ( pair ) {
	assert(
		groupsBody.indexOf( pair[ 0 ] ) !== -1 || footerBody.indexOf( pair[ 0 ] ) !== -1,
		'جزء «' + pair[ 1 ] + '» در رندر سایدبار آمده است'
	);
	assert(
		themeBare.indexOf( '.' + pair[ 0 ] ) !== -1,
		'قاعده‌ی CSS برای «' + pair[ 1 ] + '» نوشته شده است'
	);
} );

/* شمار آثار کنار ژانر و بازه‌ی سال: هر دو از داده می‌آیند، نه ثابت. */
assert( /manacore_term_counts\(/.test( functionsPhp ), 'شمار آثار هر ترم از داده محاسبه می‌شود' );
assert( /manacore_year_bounds\(/.test( functionsPhp ), 'بازه‌ی سال از داده خوانده می‌شود' );
assert(
	/get_transient\(|set_transient\(/.test( functionsPhp ) && /MINUTE_IN_SECONDS/.test( functionsPhp ),
	'شمارها کش می‌شوند (هر بارگذاری، کوئری گران تکرار نمی‌شود)'
);
assert( /function\s+manacore_meta_filter_params/.test( functionsPhp ), 'فهرست پارامترهای فراداده‌ای تعریف شده است' );

/* مسیر کوئری: هر دو حلقه باید بندهای فراداده‌ای را بگیرند. */
assert(
	/manacore_merge_meta_query\(\s*\(array\)\s*\$query->get\(\s*'meta_query'\s*\),\s*manacore_meta_filter_clauses\(\)/.test( queryPhp ),
	'کوئری اصلی (آرشیوها) فیلترهای فراداده‌ای سایدبار را اعمال می‌کند'
);
assert(
	/manacore_merge_meta_query\(/.test( blockQueryPhp ) && /manacore_meta_filter_clauses\(/.test( blockQueryPhp ),
	'حلقه‌ی بلوک هم همان فیلترهای فراداده‌ای را اعمال می‌کند'
);
assert(
	/function\s+manacore_chip_removal_args/.test( functionsPhp ),
	'برداشتن برچسب فیلتر، بازه‌ی سال را به‌صورت یک فیلتر برمی‌دارد'
);

/* کلید دوبله روی فراداده‌ی واقعی و ویرایش‌پذیر اثر. */
assert( /'manacore_dubbed'\s*=>\s*array\(/.test( metasPhp ), 'فیلد «دوبله فارسی» در متاباکس اثرها تعریف شده است' );
assert( /function\s+manacore_dubbed_meta_key/.test( functionsPhp ), 'کلید فراداده‌ای دوبله با فیلتر قابل تغییر است' );

/* رفتار جاوااسکریپت: نام‌دار بودن به‌روزرسانی برچسب امتیاز و ارسال خودکار. */
assert( /#rating-label|rating-label/.test( frontJsCode ), 'برچسب امتیاز در جاوااسکریپت به‌روز می‌شود' );
assert( /input\[type="checkbox"\], input\[type="radio"\]/.test( frontJsCode ), 'تیک‌باکس‌ها فرم را خودکار ارسال می‌کنند' );

/* ------------------------------------------------------------------
 * ک) برگه‌ی «برنامه پخش» — هم‌ارز `schedule.html` مرجع
 *
 * مرجع یک صفحه‌ی کامل دارد: سرصفحه با نشان تقویم، پنل هفتگی با تب‌های
 * روز، ستون کنار (کارت یادداشت + کارت ترویجی) و بخش پیشنهادها. این‌جا
 * سه چیز قفل می‌شود تا موج بعدی بدون آزمون پس‌رفت نکند:
 *   ۱) ساختار رندرشده (کلاس‌ها و نقش‌های دسترس‌پذیری) همان مرجع است.
 *   ۲) هر گزینه‌ی پنل ویرایشگر، هم در PHP و هم در JS هست.
 *   ۳) الگوی برگه و رفتار سمت کاربر از داده‌ی واقعی می‌آید (نه دست‌نویس).
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ک) برگه‌ی «برنامه پخش»' );
console.log( '----------------------------------------------------------' );

/*
 * رندر این بلوک بین چند متد تقسیم شده است (رندر پنل، سرتیتر، روز خالی،
 * ردیف آثار). پس به‌جای بدنه‌ی یک متد، اجتماع بدنه‌ی همه‌ی متدهای همین
 * بلوک سنجیده می‌شود — وگرنه کلاسی که در متد کمکی تولید می‌شود از چشم
 * آزمون می‌افتد.
 */
var scheduleBody = [
	'function render_schedule(',
	'function render_schedule_title(',
	'function render_schedule_empty(',
	'function render_schedule_series_item(',
	'function render_schedule_episode_item(',
	'function schedule_episodes_by_day(',
	'function schedule_series_by_day(',
	'function week_days(',
	'function day_key(',
	'function episode_day(',
].map( function ( signature ) {
	return phpBody( blocksCode, signature );
} ).join( '\n' );

var schedulePage = read( path.join( THEME, 'templates', 'page-schedule.html' ) );

assert( '' !== scheduleBody, 'متد render_schedule() وجود دارد' );
assert( '' !== schedulePage, 'الگوی برگه‌ی «برنامه پخش» در پوسته هست' );
assert( /manacore\/schedule/.test( schedulePage ), 'الگو بلوک «برنامه پخش» را می‌سازد' );
assert( /"layout":"panel"|layout.*panel/.test( schedulePage ), 'چیدمان بلوک در الگو «پنل کامل» است' );
assert( /manacore\/info-card/.test( schedulePage ), 'ستون کنار از بلوک «کارت اطلاعاتی» ساخته می‌شود (ویرایش‌پذیر، نه مارک‌آپ دست‌نویس)' );
assert( /account:watchlist/.test( schedulePage ), 'مقصد کارت ترویجی با نشانه‌ی حساب کاربری تعیین شده است' );

/* ساختار رندرشده: کلاس‌ها باید عیناً مثل مرجع باشد. */
[
	[ 'schedule-panel', 'پنل هفتگی' ],
	[ 'full-schedule', 'چیدمان تمام‌قد پنل' ],
	[ 'week-tabs', 'ردیف تب‌های روز' ],
	[ 'schedule-items', 'ظرف ردیف‌های روز' ],
	[ 'schedule-item', 'ردیف اثر' ],
	[ 'schedule-time', 'ساعت پخش' ],
	[ 'schedule-play', 'نشانه‌ی پخش' ],
	[ 'schedule-empty', 'حالت روز خالی' ],
	[ 'schedule-footnote', 'یادداشت پایین پنل' ],
	[ 'today-dot', 'نقطه‌ی روز جاری' ],
	[ 'schedule-title', 'سرتیتر پنل' ],
	[ 'timezone', 'نشانگر زمان' ],
].forEach( function ( pair ) {
	assert( scheduleBody.indexOf( pair[ 0 ] ) !== -1, 'رندر پنل «' + pair[ 1 ] + '» را می‌سازد' );
	assert( frontBare.indexOf( '.' + pair[ 0 ] ) !== -1, 'قاعده‌ی CSS برای «' + pair[ 1 ] + '» نوشته شده است' );
} );

/* تب‌های روز: نقش‌های ARIA و کلیدهای داده. */
assert( /role="tab"/.test( scheduleBody ), 'تب‌های روز نقش tab دارند' );
assert( /role="tablist"/.test( scheduleBody ), 'ردیف تب‌ها نقش tablist دارد' );
assert( /role="tabpanel"/.test( scheduleBody ), 'هر روز یک tabpanel دارد' );
assert( /aria-selected/.test( scheduleBody ), 'تب فعال با aria-selected نشانه‌گذاری می‌شود' );
assert( /hidden/.test( scheduleBody ), 'روزهای غیرفعال با hidden پنهان می‌شوند' );
assert( /data-day/.test( scheduleBody ), 'هر تب روز، کلید داده‌ی خود را دارد' );

/* گزینه‌های ویرایشگر: همان ویژگی باید هم در PHP و هم در JS باشد. */
[
	[ 'layout', null, 'چیدمان' ],
	[ 'mode', null, 'منبع داده' ],
	[ 'panelTitle', null, 'عنوان پنل' ],
	[ 'panelSubtitle', null, 'زیرنویس پنل' ],
	[ 'panelIcon', null, 'نشانه‌ی پنل' ],
	[ 'showTimezone', null, 'نمایش نشانگر زمان' ],
	[ 'timezoneLabel', null, 'متن نشانگر زمان' ],
	[ 'showSeasonMeta', null, 'نمایش فصل/قسمت' ],
	[ 'showOriginalTitle', null, 'نمایش نام اصلی' ],
	[ 'showPlay', null, 'نمایش دکمه‌ی پخش' ],
	[ 'emptyMessage', null, 'پیام روز خالی' ],
	[ 'emptyLinkLabel', null, 'برچسب پیوند روز خالی' ],
	[ 'emptyLinkUrl', null, 'مقصد پیوند روز خالی' ],
	[ 'postTypes', null, 'نوع محتوا' ],
	[ 'perDay', null, 'قسمت در هر روز' ],
	[ 'activeDay', null, 'روز فعال در آغاز' ],
].forEach( function ( row ) {
	var needle = "'" + row[ 0 ] + "'";
	assert( blocksCode.indexOf( needle ) !== -1, 'ویژگی «' + row[ 2 ] + '» در تعریف بلوک هست' );
	assert( jsCode.indexOf( needle ) !== -1, 'ویژگی «' + row[ 2 ] + '» در پنل ویرایشگر هم هست' );
} );

/* گزینه‌های گزینشگر: چیدمان پنل و منبع «آثار زمان‌بندی‌شده». */
assert( jsCode.indexOf( 'panel' ) !== -1 && jsCode.indexOf( 'series' ) !== -1, 'گزینشگرهای چیدمان/منبع در ویرایشگر هستند' );
assert( /schedule-panel/.test( frontBare ) && /full-schedule/.test( frontBare ), 'قاعده‌های چیدمان پنل در CSS افزونه هست' );
assert( /function render_info_card\(/.test( blocksCode ) && /schedule-note-card/.test( blocksCode ) && /sidebar-promo/.test( blocksCode ), 'بلوک کارت اطلاعاتی کلاس‌های مرجع ستون کنار را می‌سازد' );

/* پنل‌های بازرس ویرایشگر برای چیدمان پنل. */
[ 'تنظیمات برنامه', 'چیدمان و منبع', 'سرصفحه‌ی پنل', 'حالت خالی روز' ].forEach( function ( title ) {
	assert( jsCode.indexOf( title ) !== -1, 'پنل بازرس «' + title + '» در ویرایشگر هست' );
} );

/* رفتار سمت کاربر: تب‌های روز و حالت خالی. */
assert( /role=.tab|manacore-day-tab|week-tabs/.test( frontJsCode ), 'جاوااسکریپت تب‌های روز را پیدا می‌کند' );
assert( /schedule-panel/.test( frontJsCode ), 'جاوااسکریپت چیدمان پنل را هم می‌شناسد (نه فقط بلوک صفحه‌ی نخست)' );

/* فراداده‌ی روز/ساعت پخش باید ویرایش‌پذیر باشد (متا‌باکس، نه مقدار ثابت). */
assert( /air_day/.test( metasPhp ), 'روز پخش روی متاباکس ویرایش می‌شود' );
assert( /air_time/.test( metasPhp ), 'ساعت پخش روی متاباکس ویرایش می‌شود' );
assert(
	/get_post_meta\([^;]*'manacore_air_day'/.test( blocksCode ) && /get_post_meta\([^;]*'manacore_air_time'/.test( blocksCode ),
	'رندر، روز و ساعت پخش را از فراداده‌ی واقعی اثر می‌خواند (نه جدول ثابت)'
);

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

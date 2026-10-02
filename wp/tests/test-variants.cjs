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
var stylesPhp    = read( path.join( THEME, 'inc', 'block-styles.php' ) );
var themeCss     = read( path.join( THEME, 'assets', 'css', 'theme.css' ) );

var dataCode      = stripComments( dataPhp );
var supportCode   = stripComments( supportPhp );
var blocksCode    = stripComments( blocksPhp );
var templatesCode = stripComments( templatesPhp );
var jsCode        = stripComments( blocksJs );
var stylesCode    = stripComments( stylesPhp );
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
	[ 'card_styles', 'manacore_card_styles', 9 ],
	[ 'slider_styles', 'manacore_slider_styles', 5 ],
	[ 'slider_effects', 'manacore_slider_effects', 4 ],
	[ 'slider_alignments', 'manacore_slider_alignments', 3 ],
	[ 'download_styles', 'manacore_download_styles', 5 ],
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

// سبک‌های جعبه‌ی دانلود.
var boxMissing = keysOf.download_styles.filter( function ( key ) {
	return 'cards' !== key && frontBare.indexOf( '.manacore-links.is-box-' + key ) < 0;
} );
assert(
	0 === boxMissing.length,
	'هر ' + keysOf.download_styles.length + ' سبک جعبه‌ی دانلود CSS دارد' +
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
assert(
	/--mc-hero-max/.test( frontBare ),
	'سقف بیشینه‌ی ارتفاع متغیر است تا حالت تمام‌صفحه با سقف ثابت خنثی نشود'
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
 * ج) نشانه‌گذاری آکاردئون
 * --------------------------------------------------------------- */

console.log( '' );
console.log( 'ج) آکاردئون بومی جعبه‌ی دانلود' );
console.log( '----------------------------------------------------------' );

var groupBody = phpBody( templatesCode, 'function link_group(' );
assert( '' !== groupBody, 'بدنه‌ی link_group() یافت شد' );
assert(
	/\$wrap_tag\s*=\s*\$accordion\s*\?\s*'details'\s*:\s*'div'/.test( groupBody ),
	'در سبک آکاردئونی ظرف به <details> تبدیل می‌شود'
);
assert(
	/\$head_tag\s*=\s*\$accordion\s*\?\s*'summary'\s*:\s*'div'/.test( groupBody ),
	'سرِ گروه در آکاردئون <summary> می‌شود'
);
assert(
	/<\/<\?php echo esc_html\(\s*\$wrap_tag\s*\); \?>>/.test( groupBody ),
	'تگ پایانی ظرف با همان متغیر بسته می‌شود (نشانه‌گذاری نامتوازن نمی‌ماند)'
);
assert(
	/<\/<\?php echo esc_html\(\s*\$head_tag\s*\); \?>>/.test( groupBody ),
	'تگ پایانی سرِ گروه با همان متغیر بسته می‌شود'
);
assert(
	/is-box-<\?php echo esc_attr\(\s*\$box_style\s*\); \?>/.test( templatesCode ),
	'کلاس سبک جعبه با esc_attr روی <section> نوشته می‌شود'
);
assert(
	/details-marker/.test( frontBare ),
	'نشانگر پیش‌فرض <details> در وبکیت پنهان می‌شود'
);
assert(
	/\.manacore-links\.is-box-accordion \.manacore-link-group-head:focus-visible/.test( frontBare ),
	'سرِ آکاردئون حلقه‌ی تمرکز دیدنی دارد'
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
assert(
	/\[dir="ltr"\] \.manacore-hero-bg::after/.test( frontBare ),
	'جهت پوشش اسلایدر برای LTR جداگانه بازنویسی می‌شود'
);

// حرکت‌کاهی برای هر جلوه‌ی متحرک.
assert(
	/@media \(prefers-reduced-motion: reduce\)[\s\S]{0,400}?is-effect-slide/.test( frontBare ),
	'جلوه‌های اسلایدر با prefers-reduced-motion غیرفعال می‌شوند'
);
assert(
	/@media \(prefers-reduced-motion: reduce\)[\s\S]{0,300}?is-style-elevated/.test( frontBare ),
	'سبک برجسته‌ی کارت با prefers-reduced-motion آرام می‌شود'
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

var groupRaw = ( groupBody.match( /<\?php echo \$[a-z_]+/gi ) || [] );
assert(
	0 === groupRaw.length,
	'هیچ خروجی خام بدون esc_* در گروه لینک نیست' +
		( groupRaw.length ? ' (' + groupRaw.join( ', ' ) + ')' : '' )
);

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

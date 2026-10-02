/**
 * آزمون استایل فهرست همبرگری موبایل (NEW-C / P7).
 *
 * ریشه‌ی مشکل که این آزمون از بازگشتش جلوگیری می‌کند:
 * هسته‌ی وردپرس برای فهرست بازشده این قواعد را دارد —
 *
 *   .wp-block-navigation:not(.has-background)
 *     .…__responsive-container.is-menu-open:not(.disable-default-overlay)
 *     { background-color: #fff }
 *   .wp-block-navigation:not(.has-text-color) … { color: #000 }
 *
 * ویژهگی هرکدام (0,5,0) است. قاعده‌ی پیشین قالب تنها (0,3,0) بود، پس
 * هسته برنده می‌شد و فهرست بازشده در حالت تیره سفید/مشکی می‌ماند.
 *
 * این آزمون یک «حل‌کننده‌ی آبشار» کوچک دارد که CSS هسته و CSS قالب را
 * به‌همان ترتیب واقعی بار شدن می‌خواند و برنده‌ی نهایی هر ویژگی را
 * حساب می‌کند — یعنی واقعاً رفتار مرورگر را می‌سنجد، نه وجود یک رشته.
 *
 * اجرا: node wp/tests/test-mobile-nav.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );

var THEME = path.join( __dirname, '..', 'themes', 'koohe-film' );
var CSS   = path.join( THEME, 'assets', 'css', 'theme.css' );

/*
 * CSS هسته: اگر نصب آزمایشی وردپرس در دسترس باشد از آن، وگرنه از
 * نسخه‌ی مرجعِ درون مخزن (tests/reference) استفاده می‌شود تا آزمون
 * در هر محیطی اجرا شود.
 */
var CORE_CANDIDATES = [
	'/tmp/wpsite/wp-includes/blocks/navigation/style.min.css',
	path.join( __dirname, 'reference', 'core-navigation.css' ),
];

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
	return !! ok;
}

var themeCss = fs.readFileSync( CSS, 'utf8' );
var bare     = themeCss.replace( /\/\*[\s\S]*?\*\//g, '' );

/* ---------------------------------------------------------------------
 * حل‌کننده‌ی آبشار
 * ------------------------------------------------------------------ */

/** ویژهگی انتخابگر. :where() صفر است؛ محتوای :not() شمرده می‌شود. */
function spec( sel ) {
	var s = sel
		.replace( /:where\([^()]*\)/g, '' )
		.replace( /:not\(([^()]*)\)/g, ' $1 ' );

	var ids = ( s.match( /#[\w-]+/g ) || [] ).length;
	var cls = ( s.match( /\.[\w-]+/g ) || [] ).length +
		( s.match( /\[[^\]]+\]/g ) || [] ).length +
		( s.match( /(?<!:):(?!:)[a-z-]+/g ) || [] ).length;
	var els = ( s.match( /(?:^|[\s>+~])[a-z][\w-]*/g ) || [] ).length +
		( s.match( /::[a-z-]+/g ) || [] ).length;

	return [ ids, cls, els ];
}

function cmpSpec( a, b ) {
	for ( var i = 0; i < 3; i++ ) {
		if ( a[ i ] !== b[ i ] ) {
			return a[ i ] - b[ i ];
		}
	}
	return 0;
}

/** آیا یک بخش از انتخابگر با عنصر داده‌شده جور است؟ */
function segMatch( seg, tag, classes ) {
	var nots = [];
	var re   = /:not\(([^()]*)\)/g;
	var m;
	while ( ( m = re.exec( seg ) ) ) {
		nots.push( m[1] );
	}

	var plain = seg.replace( /:not\([^()]*\)/g, '' );
	var want  = ( plain.match( /\.[\w-]+/g ) || [] ).map( function ( c ) {
		return c.slice( 1 );
	} );

	var i;
	for ( i = 0; i < want.length; i++ ) {
		if ( classes.indexOf( want[ i ] ) === -1 ) {
			return false;
		}
	}

	var tagMatch = plain.match( /^([a-z][\w-]*)/ );
	if ( tagMatch && tagMatch[1] !== tag ) {
		return false;
	}

	for ( i = 0; i < nots.length; i++ ) {
		var nc = ( nots[ i ].match( /\.[\w-]+/g ) || [] ).map( function ( c ) {
			return c.slice( 1 );
		} );
		if ( nc.length && nc.every( function ( c ) {
			return classes.indexOf( c ) !== -1;
		} ) ) {
			return false;
		}
	}

	return true;
}

/** استخراج قواعد تخت (شامل درون @media) از یک شیت. */
function rules( css, src ) {
	css = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	var out = [];
	var re  = /([^{}]+)\{([^{}]*)\}/g;
	var m;
	while ( ( m = re.exec( css ) ) ) {
		var head = m[1];
		if ( head.indexOf( '@' ) !== -1 ) {
			head = head.slice( head.lastIndexOf( '{' ) + 1 );
		}
		head.split( ',' ).map( function ( s ) {
			return s.trim();
		} ).filter( function ( s ) {
			return s && '@' !== s.charAt( 0 );
		} ).forEach( function ( sel ) {
			out.push( { sel: sel, body: m[2], src: src } );
		} );
	}
	return out;
}

/**
 * برنده‌ی یک ویژگی روی یک عنصر فرضی.
 *
 * @param {Array}  sheets  فهرست قواعد به ترتیب بار شدن.
 * @param {Array}  tree    اجداد: {tag, classes}.
 * @param {Object} self    خود عنصر: {tag, classes}.
 * @param {string} prop    نام ویژگی.
 * @return {Object|null}
 */
function resolve( sheets, tree, self, prop ) {
	function matches( sel ) {
		/* ترکیب‌کننده‌ها و شبه‌کلاس‌های تعاملی خارج از دامنه‌ی این حل‌کننده‌اند. */
		if ( /[+~>]|::|:hover|:focus|:active/.test( sel ) ) {
			return false;
		}
		var segs = sel.trim().split( /\s+/ );
		if ( ! segMatch( segs[ segs.length - 1 ], self.tag, self.classes ) ) {
			return false;
		}
		var ti = 0;
		for ( var i = 0; i < segs.length - 1; i++ ) {
			var found = false;
			while ( ti < tree.length ) {
				var node = tree[ ti++ ];
				if ( segMatch( segs[ i ], node.tag, node.classes ) ) {
					found = true;
					break;
				}
			}
			if ( ! found ) {
				return false;
			}
		}
		return true;
	}

	var win = null;
	var re  = new RegExp( '(?:^|;)\\s*' + prop + '\\s*:\\s*([^;!]+?)\\s*(!important)?\\s*(?=;|$)', 'i' );

	sheets.forEach( function ( r ) {
		if ( ! matches( r.sel ) ) {
			return;
		}
		var m = r.body.match( re );
		if ( ! m ) {
			return;
		}
		var cand = {
			sel: r.sel,
			val: m[1].trim(),
			imp: !! m[2],
			sp:  spec( r.sel ),
			src: r.src,
		};
		if ( ! win ) {
			win = cand;
			return;
		}
		if ( cand.imp !== win.imp ) {
			if ( cand.imp ) {
				win = cand;
			}
			return;
		}
		/* >= چون شیت بعدی/قاعده‌ی بعدی در تساوی برنده است. */
		if ( cmpSpec( cand.sp, win.sp ) >= 0 ) {
			win = cand;
		}
	} );

	return win;
}

console.log( '' );
console.log( 'الف) آبشار رنگ فهرست بازشده — قالب باید بر هسته پیروز شود' );
console.log( '----------------------------------------------------------' );

var corePath = null;
CORE_CANDIDATES.forEach( function ( p ) {
	if ( ! corePath && fs.existsSync( p ) ) {
		corePath = p;
	}
} );

if ( corePath ) {
	console.log( '  (CSS هسته: ' + corePath + ')' );

	var sheets = rules( fs.readFileSync( corePath, 'utf8' ), 'core' )
		.concat( rules( themeCss, 'theme' ) );

	/* درخت واقعی: html > body > nav.koohe-nav > div.…container.is-menu-open */
	var tree = [
		{ tag: 'html', classes: [] },
		{ tag: 'body', classes: [ 'koohe-sticky-header', 'koohe-has-toggle' ] },
		{ tag: 'nav', classes: [ 'wp-block-navigation', 'koohe-nav', 'is-responsive', 'is-layout-flex' ] },
	];
	var self = {
		tag: 'div',
		classes: [ 'wp-block-navigation__responsive-container', 'is-menu-open' ],
	};

	[
		[ 'background-color', 'var(--koohe-surface)' ],
		[ 'color', 'var(--koohe-text)' ],
	].forEach( function ( pair ) {
		var win = resolve( sheets, tree, self, pair[0] );
		var ok  = win && win.val === pair[1];
		assert(
			ok,
			pair[0] + ' فهرست بازشده = ' + pair[1] +
				( win ? '   (فعلی: ' + win.val + ' از ' + win.src + ' با ویژهگی ' + win.sp.join( ',' ) + ')' : '   (هیچ قاعده‌ای)' )
		);
	} );

	/* اطمینان از این‌که پیروزی با !important به‌دست نیامده باشد. */
	var overlayRule = bare.match(
		/\.koohe-nav\.wp-block-navigation\s+\.wp-block-navigation__responsive-container\.is-menu-open:not\(\.disable-default-overlay\)\s*\{[^}]*\}/
	);
	assert(
		overlayRule && ! /!important/.test( overlayRule[0] ),
		'پیروزی بدون !important به‌دست آمده است (ویژهگی برابر + ترتیب بار شدن)'
	);
	assert(
		overlayRule && /:not\(\.disable-default-overlay\)/.test( overlayRule[0] ),
		'انتخابگر همان الگوی هسته را دارد تا پوشش سفارشی (overlay template part) نشکند'
	);
} else {
	console.log( '  ! CSS هسته پیدا نشد؛ آزمون آبشار رد شد (بررسی ساختاری ادامه دارد).' );
}

console.log( '' );
console.log( 'ب) پوشش زیرعنصرهای فهرست بازشده' );
console.log( '----------------------------------------------------------' );

/*
 * هر یک از این زیرعنصرها را هسته می‌سازد. اگر قالب برای آن‌ها قاعده‌ای
 * نداشته باشد، فهرست بازشده «بی‌استایل» به‌نظر می‌رسد.
 */
[
	[ 'wp-block-navigation__responsive-container-content', 'لایه‌ی محتوا' ],
	[ 'wp-block-navigation__responsive-container-close', 'دکمه‌ی بستن (×)' ],
	[ 'wp-block-navigation__responsive-container-open', 'دکمه‌ی همبرگر' ],
	[ 'wp-block-navigation__responsive-dialog', 'محفظه‌ی دیالوگ' ],
	[ 'wp-block-navigation__responsive-close', 'قاب امن محتوا' ],
	[ 'has-modal-open', 'وضعیت باز بودن مودال' ],
].forEach( function ( pair ) {
	assert(
		bare.indexOf( pair[0] ) !== -1,
		'استایل برای ' + pair[1] + ' («' + pair[0] + '») وجود دارد'
	);
} );

console.log( '' );
console.log( 'پ) دسترس‌پذیری و کیفیت لمسی' );
console.log( '----------------------------------------------------------' );

var overlayBlock = bare.slice(
	bare.indexOf( '.koohe-nav.wp-block-navigation .wp-block-navigation__responsive-container.is-menu-open' )
);
overlayBlock = overlayBlock.slice( 0, overlayBlock.indexOf( '4. کلید حالت' ) > 0 ? overlayBlock.indexOf( '4. کلید حالت' ) : 12000 );

assert(
	/min-height:\s*44px/.test( overlayBlock ),
	'آیتم‌های فهرست دست‌کم ۴۴px ارتفاع دارند (هدف لمسی استاندارد)'
);
assert(
	/:focus-visible/.test( overlayBlock ),
	'حالت :focus-visible برای پیمایش با کیبورد تعریف شده است'
);
assert(
	/outline:\s*2px solid var\(--koohe-accent\)/.test( overlayBlock ),
	'حلقه‌ی فوکوس با رنگ تأکید قالب دیده می‌شود'
);
assert(
	/env\(\s*safe-area-inset/.test( overlayBlock ),
	'ناحیه‌ی امن (safe-area) گوشی‌های دارای notch رعایت شده است'
);
assert(
	/overscroll-behavior:\s*contain/.test( overlayBlock ),
	'overscroll-behavior: contain مانع کشیده‌شدن صفحه‌ی پشت می‌شود'
);

console.log( '' );
console.log( 'ت) درستی RTL — نباید left/right ثابت به‌کار رود' );
console.log( '----------------------------------------------------------' );

/*
 * .…container-close در هسته با right:0 چیده شده است. در RTL باید
 * سمت شروع بیفتد، پس قالب باید از ویژگی‌های منطقی استفاده کند.
 */
var closeRule = bare.match( /\.koohe-nav \.wp-block-navigation__responsive-container-close\s*\{[^}]*\}/ );
assert( !! closeRule, 'قاعده‌ی دکمه‌ی بستن پیدا شد' );
if ( closeRule ) {
	assert(
		/inset-inline-end/.test( closeRule[0] ),
		'دکمه‌ی بستن با inset-inline-end جای‌گذاری شده است (سازگار با RTL)'
	);
	assert(
		/right:\s*auto/.test( closeRule[0] ),
		'right: 0 هسته با right: auto بی‌اثر شده است'
	);
}

/* در کل بخش overlay نباید padding-left/right یا left/right جهت‌دار بماند. */
var directional = ( overlayBlock.match( /(?:^|[\s;{])(?:padding|margin|border)-(?:left|right)\s*:/g ) || [] );
assert(
	0 === directional.length,
	'هیچ ویژگی جهت‌دار padding/margin/border-left|right در بخش overlay نمانده است' +
		( directional.length ? ' (' + directional.length + ' مورد)' : '' )
);
assert(
	/inset-inline-start|padding-inline-start|margin-inline-start|border-inline-start/.test( overlayBlock ),
	'از ویژگی‌های منطقی (inline-start/end) استفاده شده است'
);

console.log( '' );
console.log( 'ث) احترام به تنظیمات کاربر و سازگاری با نوار مدیریت' );
console.log( '----------------------------------------------------------' );

assert(
	/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.koohe-nav[^}]*animation:\s*none/.test( bare ),
	'انیمیشن محوشدن با prefers-reduced-motion خاموش می‌شود'
);
assert(
	/\.has-modal-open \.admin-bar[^{]*container-close/.test( bare ),
	'دکمه‌ی بستن با نوار مدیریت وردپرس تداخل ندارد'
);
assert(
	/\.has-modal-open \.koohe-header-slot\s*\{[^}]*z-index/.test( bare ),
	'سربرگ چسبان روی فهرست بازشده نمی‌افتد (z-index کاهش می‌یابد)'
);

console.log( '' );
console.log( 'ج) هم‌خوانی با سامانه‌ی رنگ تیره/روشن' );
console.log( '----------------------------------------------------------' );

/*
 * نباید هیچ رنگ سختِ hex در بخش overlay باشد؛ همه باید از توکن‌های
 * قالب بیاید تا حالت تیره/روشن به‌درستی سوییچ کند.
 */
var hexes = ( overlayBlock.match( /#[0-9a-fA-F]{3,8}\b/g ) || [] );
assert(
	0 === hexes.length,
	'هیچ رنگ ثابت hex در بخش overlay نیست' + ( hexes.length ? ' (' + hexes.join( ', ' ) + ')' : '' )
);

[ '--koohe-surface', '--koohe-text', '--koohe-border', '--koohe-accent' ].forEach( function ( token ) {
	assert(
		overlayBlock.indexOf( token ) !== -1,
		'توکن ' + token + ' در بخش overlay استفاده شده است'
	);
} );

console.log( '' );
console.log( 'چ) صحت نحوی CSS' );
console.log( '----------------------------------------------------------' );

var opens  = ( bare.match( /\{/g ) || [] ).length;
var closes = ( bare.match( /\}/g ) || [] ).length;
assert( opens === closes, 'آکولادها متوازن‌اند (' + opens + ' / ' + closes + ')' );

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

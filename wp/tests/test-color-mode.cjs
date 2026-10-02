/**
 * آزمون حالت تیره/روشن (P2).
 *
 * چرا این آزمون لازم است؟ سه اشتباهِ ساده می‌تواند حالت تیره را بی‌اثر کند و
 * هیچ‌کدام خطای نحوی تولید نمی‌کنند، پس فقط با آزمون قابل تشخیص‌اند:
 *
 *   ۱) کم بودن ویژگی‌مندی (specificity): وردپرس متغیرهای پالت را روی «:root»
 *      چاپ می‌کند (0,1,0). یک انتخابگر «[data-color-mode]» تنها هم همین
 *      ویژگی‌مندی را دارد؛ برابری، نتیجه را به ترتیب چاپ استایل‌ها گره می‌زند.
 *      انتخابگر باید «html[data-color-mode=...]» باشد تا (0,1,1) شود.
 *
 *   ۲) بازنویسی‌نشدن توکن‌های پیش‌فرض وردپرس: اگر فقط --koohe-* عوض شود،
 *      بلوک‌های هسته و ManaCore در حالت تیره روشن می‌مانند.
 *
 *   ۳) سخت‌کد شدن رنگ‌ها در CSS: در آن حالت «تنوع استایل»‌های سینما و نیمه‌شب
 *      با زدن کلید تیره/روشن هویت خود را از دست می‌دهند.
 *
 * این آزمون به وردپرس نیاز ندارد: theme.json و فایل‌های styles/ را می‌خواند،
 * متغیرها را مثل وردپرس به --wp--custom--* تبدیل می‌کند و سپس زنجیره‌ی
 * var() را حل می‌کند تا رنگ نهایی هر حالت را بسنجد.
 *
 * @package KooheFilm
 * @author  ManaCore
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const THEME = path.join( __dirname, '..', 'themes', 'koohe-film' );
const CSS_FILE = path.join( THEME, 'assets', 'css', 'theme.css' );
const CORE_CSS = path.join( __dirname, '..', 'plugins', 'manacore-core', 'assets', 'css', 'front.css' );

let failures = 0;
let checks = 0;

/**
 * ثبت نتیجه‌ی یک بررسی.
 *
 * @param {boolean} pass نتیجه.
 * @param {string}  label عنوان.
 * @param {string}  detail توضیح در صورت خطا.
 */
function assert( pass, label, detail ) {
	checks++;

	if ( pass ) {
		console.log( '  ✓ ' + label );
		return;
	}

	failures++;
	console.log( '  ✗ ' + label + ( detail ? '\n      ' + detail : '' ) );
}

/* ---------------------------------------------------------------------------
 * ۱. تبدیل کلیدهای settings.custom به نام متغیر، مطابق منطق وردپرس.
 * ------------------------------------------------------------------------ */

/**
 * تبدیل کلید به kebab-case (هم‌ارز _wp_to_kebab_case وردپرس).
 *
 * @param {string} key کلید.
 * @return {string} کلید kebab.
 */
function toKebab( key ) {
	return String( key )
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /([a-zA-Z])(\d)/g, '$1-$2' )
		.replace( /[\s_.]+/g, '-' )
		.replace( /-+/g, '-' )
		.toLowerCase();
}

/**
 * صاف‌کردن درختِ settings.custom به متغیرهای --wp--custom--*.
 *
 * @param {Object} node گره.
 * @param {string} prefix پیشوند.
 * @param {Object} out خروجی.
 * @return {Object} نقشه‌ی متغیرها.
 */
function flattenCustom( node, prefix, out ) {
	out = out || {};

	Object.keys( node || {} ).forEach( function ( key ) {
		const name = prefix + '--' + toKebab( key );
		const value = node[ key ];

		if ( value && 'object' === typeof value && ! Array.isArray( value ) ) {
			flattenCustom( value, name, out );
			return;
		}

		out[ name ] = String( value );
	} );

	return out;
}

/**
 * ساخت نقشه‌ی متغیرهای «ریشه» برای یک تنوع استایل.
 *
 * @param {Object} themeJson theme.json.
 * @param {Object} variation تنوع (یا null برای پیش‌فرض).
 * @return {Object} نقشه‌ی متغیر.
 */
function rootVars( themeJson, variation ) {
	const vars = {};

	/* پالت رنگ: theme.json و سپس بازنویسی تنوع. */
	const palettes = [
		( themeJson.settings && themeJson.settings.color && themeJson.settings.color.palette ) || [],
		( variation && variation.settings && variation.settings.color && variation.settings.color.palette ) || [],
	];

	palettes.forEach( function ( palette ) {
		palette.forEach( function ( item ) {
			vars[ '--wp--preset--color--' + item.slug ] = item.color;
		} );
	} );

	/* سایه‌ها. */
	[
		( themeJson.settings && themeJson.settings.shadow && themeJson.settings.shadow.presets ) || [],
		( variation && variation.settings && variation.settings.shadow && variation.settings.shadow.presets ) || [],
	].forEach( function ( presets ) {
		presets.forEach( function ( item ) {
			vars[ '--wp--preset--shadow--' + item.slug ] = item.shadow;
		} );
	} );

	/* settings.custom → --wp--custom--* */
	[
		( themeJson.settings && themeJson.settings.custom ) || {},
		( variation && variation.settings && variation.settings.custom ) || {},
	].forEach( function ( custom ) {
		Object.assign( vars, flattenCustom( custom, '--wp--custom' ) );
	} );

	return vars;
}

/* ---------------------------------------------------------------------------
 * ۲. تجزیه‌ی سبکِ theme.css برای انتخابگرهای حالت.
 * ------------------------------------------------------------------------ */

/**
 * استخراج اعلان‌های یک انتخابگر دقیق از CSS.
 *
 * @param {string} css متن CSS.
 * @param {string} selector انتخابگر.
 * @return {Object|null} نقشه‌ی خصیصه‌ها.
 */
function ruleFor( css, selector ) {
	/* کامنت‌ها حذف شوند تا با آکولادهای درون توضیح اشتباه نشود. */
	const clean = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const index = clean.indexOf( selector + ' {' );

	if ( -1 === index ) {
		return null;
	}

	const start = clean.indexOf( '{', index );
	const end = clean.indexOf( '}', start );

	if ( -1 === start || -1 === end ) {
		return null;
	}

	const body = clean.slice( start + 1, end );
	const out = {};

	body.split( ';' ).forEach( function ( line ) {
		const colon = line.indexOf( ':' );

		if ( colon < 1 ) {
			return;
		}

		out[ line.slice( 0, colon ).trim() ] = line.slice( colon + 1 ).trim();
	} );

	return out;
}

/**
 * حل زنجیره‌ی var() تا رسیدن به مقدار واقعی.
 *
 * از var(--a, fallback) و تودرتویی پشتیبانی می‌کند.
 *
 * @param {string} value مقدار خام.
 * @param {Object} vars نقشه‌ی متغیرها.
 * @param {number} depth عمق (محافظ حلقه).
 * @return {string} مقدار حل‌شده.
 */
function resolve( value, vars, depth ) {
	depth = depth || 0;

	if ( depth > 24 || 'string' !== typeof value ) {
		return String( value );
	}

	const open = value.indexOf( 'var(' );

	if ( -1 === open ) {
		return value.trim();
	}

	/* یافتن پرانتز بسته‌ی متناظر. */
	let level = 0;
	let close = -1;

	for ( let i = open + 3; i < value.length; i++ ) {
		if ( '(' === value[ i ] ) {
			level++;
		} else if ( ')' === value[ i ] ) {
			level--;

			if ( 0 === level ) {
				close = i;
				break;
			}
		}
	}

	if ( -1 === close ) {
		return value.trim();
	}

	const inner = value.slice( open + 4, close );
	const comma = inner.indexOf( ',' );
	const name = ( comma === -1 ? inner : inner.slice( 0, comma ) ).trim();
	const fallback = comma === -1 ? '' : inner.slice( comma + 1 ).trim();

	let replacement;

	if ( Object.prototype.hasOwnProperty.call( vars, name ) ) {
		replacement = resolve( vars[ name ], vars, depth + 1 );
	} else {
		replacement = resolve( fallback, vars, depth + 1 );
	}

	const rebuilt = value.slice( 0, open ) + replacement + value.slice( close + 1 );

	return resolve( rebuilt, vars, depth + 1 );
}

/**
 * محاسبه‌ی پالت نهایی برای یک حالت.
 *
 * @param {string} css متن theme.css.
 * @param {Object} base متغیرهای ریشه.
 * @param {string} mode dark|light.
 * @return {Object} نقشه‌ی حل‌شده.
 */
function computeMode( css, base, mode ) {
	const rule = ruleFor( css, 'html[data-color-mode="' + mode + '"]' );

	if ( ! rule ) {
		return null;
	}

	/*
	 * ترتیب آبشار، از ضعیف به قوی:
	 *   ۱) متغیرهای وردپرس از theme.json روی :root
	 *   ۲) پل‌های --koohe-* از بلوک :root در theme.css
	 *   ۳) بازنویسی حالت روی html[data-color-mode="…"]
	 *
	 * گنجاندن مرحله‌ی ۲ ضروری است: پل‌ها با var() به توکن‌های وردپرس اشاره
	 * می‌کنند، پس تنها راه سنجیدن این‌که «پل درست کار می‌کند» همین است.
	 */
	const bridges = ruleFor( css, ':root' ) || {};
	const merged = Object.assign( {}, base, bridges, rule );
	const out = {};

	Object.keys( merged ).forEach( function ( key ) {
		if ( 0 === key.indexOf( '--wp--preset--color--' ) || 0 === key.indexOf( '--wp--preset--shadow--' ) || 0 === key.indexOf( '--koohe-' ) ) {
			out[ key ] = resolve( merged[ key ], merged );
		}
	} );

	out[ 'color-scheme' ] = rule[ 'color-scheme' ] || '';

	return out;
}

/* ---------------------------------------------------------------------------
 * ۳. اجرا
 * ------------------------------------------------------------------------ */

const css = fs.readFileSync( CSS_FILE, 'utf8' );
const themeJson = JSON.parse( fs.readFileSync( path.join( THEME, 'theme.json' ), 'utf8' ) );

console.log( 'الف) ویژگی‌مندی انتخابگرهای حالت' );

assert(
	-1 !== css.indexOf( 'html[data-color-mode="dark"] {' ),
	'انتخابگر حالت تیره با «html» شروع می‌شود (ویژگی‌مندی 0,1,1)',
	'باید html[data-color-mode="dark"] باشد تا از :root وردپرس قوی‌تر شود.'
);

assert(
	-1 !== css.indexOf( 'html[data-color-mode="light"] {' ),
	'انتخابگر حالت روشن با «html» شروع می‌شود',
	'باید html[data-color-mode="light"] باشد.'
);

assert(
	! /^\s*\[data-color-mode="(dark|light)"\]\s*\{/m.test( css.replace( /\/\*[\s\S]*?\*\//g, '' ) ),
	'هیچ انتخابگر حالتِ بدون «html» باقی نمانده است',
	'انتخابگر بدون html هم‌ویژگی با :root است و بازنویسی قطعی نمی‌شود.'
);

console.log( '\nب) بازنویسی توکن‌های پیش‌فرض وردپرس' );

const REQUIRED_COLORS = [
	'surface', 'surface-2', 'surface-3', 'foreground',
	'muted', 'accent', 'accent-contrast', 'border', 'success', 'danger',
];

[ 'dark', 'light' ].forEach( function ( mode ) {
	const rule = ruleFor( css, 'html[data-color-mode="' + mode + '"]' );

	assert( !! rule, 'قاعده‌ی حالت «' + mode + '» پیدا شد' );

	if ( ! rule ) {
		return;
	}

	const missing = REQUIRED_COLORS.filter( function ( slug ) {
		return ! Object.prototype.hasOwnProperty.call( rule, '--wp--preset--color--' + slug );
	} );

	assert(
		0 === missing.length,
		'حالت «' + mode + '» همه‌ی ۱۰ رنگ پیش‌فرض وردپرس را بازنویسی می‌کند',
		'رنگ‌های جامانده: ' + missing.join( ', ' )
	);

	assert(
		Object.prototype.hasOwnProperty.call( rule, '--wp--preset--shadow--soft' ) &&
			Object.prototype.hasOwnProperty.call( rule, '--wp--preset--shadow--raised' ),
		'حالت «' + mode + '» سایه‌های پیش‌فرض را بازنویسی می‌کند'
	);

	assert(
		mode === rule[ 'color-scheme' ],
		'حالت «' + mode + '» ویژگی color-scheme را روی «' + mode + '» می‌گذارد',
		'مقدار فعلی: ' + rule[ 'color-scheme' ]
	);
} );

console.log( '\nپ) رنگ‌ها سخت‌کد نشده‌اند و از theme.json می‌آیند' );

[ 'dark', 'light' ].forEach( function ( mode ) {
	const rule = ruleFor( css, 'html[data-color-mode="' + mode + '"]' );

	if ( ! rule ) {
		return;
	}

	const hardcoded = REQUIRED_COLORS.filter( function ( slug ) {
		const value = rule[ '--wp--preset--color--' + slug ] || '';

		return -1 === value.indexOf( 'var(--wp--custom--scheme--' + mode + '--' );
	} );

	assert(
		0 === hardcoded.length,
		'حالت «' + mode + '» رنگ‌ها را از --wp--custom--scheme--' + mode + '--* می‌خواند',
		'سخت‌کدشده: ' + hardcoded.join( ', ' )
	);
} );

console.log( '\nت) نتیجه‌ی نهایی برای هر تنوع استایل' );

const variations = [ null ].concat(
	fs.readdirSync( path.join( THEME, 'styles' ) )
		.filter( function ( f ) {
			return /\.json$/.test( f );
		} )
		.sort()
);

variations.forEach( function ( file ) {
	const variation = file
		? JSON.parse( fs.readFileSync( path.join( THEME, 'styles', file ), 'utf8' ) )
		: null;
	const label = file || '(پیش‌فرض theme.json)';
	const base = rootVars( themeJson, variation );

	const dark = computeMode( css, base, 'dark' );
	const light = computeMode( css, base, 'light' );

	if ( ! dark || ! light ) {
		assert( false, label + ' — محاسبه‌ی حالت‌ها ناموفق' );
		return;
	}

	/* هیچ متغیر حل‌نشده‌ای نباید بماند. */
	const unresolved = [];

	[ dark, light ].forEach( function ( map ) {
		Object.keys( map ).forEach( function ( key ) {
			if ( -1 !== String( map[ key ] ).indexOf( 'var(' ) ) {
				unresolved.push( key );
			}
		} );
	} );

	assert(
		0 === unresolved.length,
		label + ' — همه‌ی متغیرها حل می‌شوند',
		'حل‌نشده: ' + unresolved.join( ', ' )
	);

	/* تیره و روشن باید واقعاً متفاوت باشند. */
	assert(
		dark[ '--wp--preset--color--surface' ] !== light[ '--wp--preset--color--surface' ],
		label + ' — پس‌زمینه‌ی تیره و روشن متفاوت است',
		'هر دو: ' + dark[ '--wp--preset--color--surface' ]
	);

	assert(
		dark[ '--wp--preset--color--foreground' ] !== light[ '--wp--preset--color--foreground' ],
		label + ' — رنگ متن تیره و روشن متفاوت است'
	);

	/* --koohe-* هم باید تابع حالت باشد (پل درست کار کند). */
	assert(
		dark[ '--koohe-surface' ] === dark[ '--wp--preset--color--surface' ] &&
			light[ '--koohe-surface' ] === light[ '--wp--preset--color--surface' ],
		label + ' — پل --koohe-surface با حالت فعال هم‌گام است',
		'تیره: ' + dark[ '--koohe-surface' ] + ' / روشن: ' + light[ '--koohe-surface' ]
	);

	/* روشنایی: پس‌زمینه‌ی تیره باید تیره‌تر از روشن باشد. */
	const luma = function ( hex ) {
		const m = /^#([0-9a-f]{6})$/i.exec( String( hex ).trim() );

		if ( ! m ) {
			return null;
		}

		const n = parseInt( m[ 1 ], 16 );

		return ( ( ( n >> 16 ) & 255 ) * 0.299 + ( ( n >> 8 ) & 255 ) * 0.587 + ( n & 255 ) * 0.114 ) / 255;
	};

	const darkLuma = luma( dark[ '--wp--preset--color--surface' ] );
	const lightLuma = luma( light[ '--wp--preset--color--surface' ] );

	if ( null !== darkLuma && null !== lightLuma ) {
		assert(
			darkLuma < 0.35 && lightLuma > 0.65,
			label + ' — روشناییِ حالت‌ها منطقی است',
			'تیره=' + darkLuma.toFixed( 2 ) + ' روشن=' + lightLuma.toFixed( 2 )
		);
	}

	/* تنوع‌های رنگی باید هویت خود (رنگ تأکید) را در هر دو حالت نگه دارند. */
	if ( file && /cinema|midnight/.test( file ) ) {
		const accentDark = dark[ '--wp--preset--color--accent' ];
		const accentDefault = '#f59e0b';

		assert(
			accentDark.toLowerCase() !== accentDefault,
			label + ' — رنگ تأکیدِ تنوع در حالت تیره حفظ می‌شود',
			'رنگ تأکید به پیش‌فرض قالب برگشته است: ' + accentDark
		);
	}
} );

console.log( '\nث) تنوع‌های استایل با کلید حالت درگیر نمی‌شوند' );

fs.readdirSync( path.join( THEME, 'styles' ) )
	.filter( function ( f ) {
		return /\.json$/.test( f );
	} )
	.sort()
	.forEach( function ( file ) {
		const data = JSON.parse( fs.readFileSync( path.join( THEME, 'styles', file ), 'utf8' ) );
		const inline = ( data.styles && data.styles.css ) || '';

		assert(
			-1 === inline.replace( /\s+/g, '' ).indexOf( 'color-scheme:' ),
			file + ' — color-scheme را سخت‌کد نمی‌کند',
			'کلید تیره/روشن باید تعیین‌کننده باشد، نه تنوع استایل.'
		);

		const scheme = data.settings && data.settings.custom && data.settings.custom.scheme;

		assert(
			!! ( scheme && scheme.dark && scheme.light ),
			file + ' — پالت هر دو حالت را تعریف می‌کند'
		);
	} );

console.log( '\nج) افزونه‌ی هسته' );

const coreCss = fs.readFileSync( CORE_CSS, 'utf8' );

assert(
	-1 !== coreCss.indexOf( '[data-color-mode="light"]' ),
	'front.css حالت روشن را نیز صریح تعریف می‌کند'
);

assert(
	/\[data-color-mode="dark"\][\s\S]{0,1200}color-scheme:\s*dark/.test( coreCss ),
	'front.css در حالت تیره color-scheme را تنظیم می‌کند'
);

console.log( '\n----------------------------------------------------------' );

if ( 0 === failures ) {
	console.log( 'آزمون حالت رنگ: ' + checks + ' بررسی، همه موفق ✓' );
	process.exit( 0 );
}

console.log( 'آزمون حالت رنگ: ' + failures + ' از ' + checks + ' بررسی ناموفق ✗' );
process.exit( 1 );

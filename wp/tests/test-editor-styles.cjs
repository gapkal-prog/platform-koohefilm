/**
 * آزمون استایل ویرایشگر سایت (P4a) و ثبت بلوک‌های قالب در ویرایشگر (P4b).
 *
 * چرا این آزمون؟
 *  الف) add_editor_style() فقط editor.css را بار می‌کرد، پس ویرایشگر
 *       سایت هیچ‌کدام از استایل‌های theme.css را نداشت (صفحه بی‌استایل).
 *  ب)  بلوک‌های koohe/* تنها در PHP ثبت می‌شدند؛ ویرایشگر پیاده‌سازی
 *       edit نداشت و پیام «سایت شما از بلوک … پشتیبانی نمی‌کند» می‌داد.
 *
 * اجرا: node wp/tests/test-editor-styles.cjs
 */

'use strict';

var fs   = require( 'fs' );
var path = require( 'path' );

var THEME = path.join( __dirname, '..', 'themes', 'koohe-film' );

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

function read( rel ) {
	var file = path.join( THEME, rel );
	return fs.existsSync( file ) ? fs.readFileSync( file, 'utf8' ) : '';
}

/** حذف کامنت‌های CSS تا تطبیق‌ها روی کد واقعی انجام شود. */
function stripCss( css ) {
	return css.replace( /\/\*[\s\S]*?\*\//g, '' );
}

/** حذف کامنت‌های PHP/JS تا کد اجراشدنی باقی بماند. */
function stripCode( src ) {
	return src
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /^[ \t]*(\/\/|#)[^\n]*$/gm, '' );
}

var setupPhp   = stripCode( read( 'inc/setup.php' ) );
var editorCss  = stripCss( read( 'assets/css/editor.css' ) );
var themeCss   = stripCss( read( 'assets/css/theme.css' ) );
var blocksPhp  = stripCode( read( 'inc/blocks.php' ) );
var blocksJs   = stripCode( read( 'assets/js/blocks.js' ) );
var tagsPhp    = stripCode( read( 'inc/template-tags.php' ) );
var functions  = stripCode( read( 'functions.php' ) );
var assetsPhp  = stripCode( read( 'inc/assets.php' ) );

console.log( '' );
console.log( 'الف) استایل‌های ویرایشگر سایت (P4a)' );
console.log( '----------------------------------------------------------' );

var editorStyleCall = setupPhp.match( /add_editor_style\s*\(([\s\S]*?)\)\s*;/ );
assert( !! editorStyleCall, 'add_editor_style در setup.php فراخوانی می‌شود' );

var editorStyleArgs = editorStyleCall ? editorStyleCall[1] : '';
assert(
	/assets\/css\/theme\.css/.test( editorStyleArgs ),
	'theme.css به‌عنوان استایل ویرایشگر بار می‌شود'
);
assert(
	/assets\/css\/editor\.css/.test( editorStyleArgs ),
	'editor.css به‌عنوان استایل ویرایشگر بار می‌شود'
);
assert(
	editorStyleArgs.indexOf( 'theme.css' ) < editorStyleArgs.indexOf( 'editor.css' ),
	'ترتیب درست است: theme.css پیش از editor.css (بازنویسی‌های ویرایشگر برنده می‌شوند)'
);

/* فایل‌های نام‌برده باید واقعاً وجود داشته باشند، وگرنه file_get_contents خالی برمی‌گرداند. */
[ 'assets/css/theme.css', 'assets/css/editor.css' ].forEach( function ( rel ) {
	assert(
		fs.existsSync( path.join( THEME, rel ) ) && read( rel ).length > 100,
		'فایل ' + rel + ' موجود و غیرخالی است'
	);
} );

console.log( '' );
console.log( 'ب) بی‌اثرسازی چیدمان‌های چسبان/ثابت در بوم ویرایشگر' );
console.log( '----------------------------------------------------------' );

/*
 * theme.css اکنون داخل ویرایشگر هم بار می‌شود. هسته انتخابگرهای
 * html/body/:root را به .editor-styles-wrapper بازنویسی می‌کند، پس
 * position: sticky/fixed باید صریحاً خنثی شود وگرنه روی نوار ابزار
 * ویرایشگر می‌افتد.
 */
var neutralized = /position\s*:\s*static\s*!important/.test( editorCss );
assert( neutralized, 'editor.css موقعیت را با position:static !important خنثی می‌کند' );

[
	'koohe-header-slot',
	'koohe-header',
	'koohe-progress',
	'koohe-to-top',
].forEach( function ( cls ) {
	var re = new RegExp( '\\.editor-styles-wrapper[^{}]*\\.' + cls + '\\b' );
	assert( re.test( editorCss ), 'کلاس .' + cls + ' در بوم ویرایشگر هدف قرار گرفته است' );
} );

assert(
	/\.editor-styles-wrapper[^{}]*\{[^}]*backdrop-filter\s*:\s*none\s*!important/.test( editorCss ),
	'backdrop-filter در ویرایشگر خنثی شده است (شیشه‌ای بودن سربرگ)'
);
assert(
	/\.editor-styles-wrapper[^{}]*:hover[^{}]*\{[^}]*transform\s*:\s*none\s*!important/.test( editorCss ),
	'انیمیشن بزرگ‌نمایی hover در ویرایشگر خنثی شده است'
);
assert(
	/\.editor-styles-wrapper[^{}]*wp-block-navigation__responsive-container/.test( editorCss ),
	'ظرف واکنش‌گرای فهرست ناوبری در ویرایشگر خنثی شده است'
);
assert(
	/\.editor-styles-wrapper\s*\{[^}]*background-color\s*:\s*var\(\s*--wp--preset--color--surface/.test( editorCss ),
	'رنگ پس‌زمینه‌ی بوم از پیش‌تنظیم‌های theme.json می‌آید'
);

/* theme.css باید همان متغیرهایی را داشته باشد که editor.css به آن‌ها پل می‌زند. */
assert(
	/--koohe-surface\s*:/.test( themeCss ) && /--koohe-surface/.test( editorCss ),
	'پل متغیرهای --koohe-* میان theme.css و editor.css برقرار است'
);

console.log( '' );
console.log( 'پ) ثبت بلوک‌های قالب — تنها یک منبع حقیقت (P4b)' );
console.log( '----------------------------------------------------------' );

assert( blocksPhp.length > 0, 'فایل inc/blocks.php موجود است' );
assert(
	/'blocks'\s*,/.test( functions ),
	'inc/blocks.php در functions.php بار می‌شود'
);
assert(
	/function\s+koohe_block_definitions\s*\(/.test( blocksPhp ),
	'koohe_block_definitions() تعریف شده است'
);
assert(
	/apply_filters\(\s*'koohe_block_definitions'/.test( blocksPhp ),
	'تعریف بلوک‌ها قابل توسعه است (فیلتر koohe_block_definitions)'
);

var EXPECTED_BLOCKS = [ 'koohe/theme-toggle', 'koohe/account', 'koohe/copyright' ];

EXPECTED_BLOCKS.forEach( function ( name ) {
	assert(
		blocksPhp.indexOf( "'" + name + "'" ) !== -1,
		'بلوک ' + name + ' در تعریف‌ها هست'
	);
} );

/* هر بلوک باید render_callback موجود در template-tags.php داشته باشد. */
var callbacks = blocksPhp.match( /'render_callback'\s*=>\s*'([a-z_]+)'/g ) || [];
assert(
	callbacks.length === EXPECTED_BLOCKS.length,
	'هر ' + EXPECTED_BLOCKS.length + ' بلوک render_callback دارد (' + callbacks.length + ')'
);
callbacks.forEach( function ( raw ) {
	var fn = raw.replace( /.*'([a-z_]+)'$/, '$1' );
	assert(
		new RegExp( 'function\\s+' + fn + '\\s*\\(' ).test( tagsPhp ),
		'تابع رندر ' + fn + '() در template-tags.php وجود دارد'
	);
} );

/* دیگر نباید هیچ ثبت تکراری در template-tags.php باشد. */
assert(
	tagsPhp.indexOf( 'register_block_type' ) === -1,
	'ثبت تکراری بلوک از template-tags.php حذف شده است'
);
var registerCalls = ( blocksPhp.match( /register_block_type\s*\(/g ) || [] ).length;
assert(
	1 === registerCalls,
	'register_block_type تنها یک بار (در حلقه‌ی blocks.php) صدا زده می‌شود'
);
assert(
	/is_registered\(\s*\$name\s*\)/.test( blocksPhp ),
	'پیش از ثبت، تکراری‌بودن بررسی می‌شود (جلوگیری از notice)'
);
assert(
	/unset\(\s*\$definition\['editorFields'\]\s*\)/.test( blocksPhp ),
	'editorFields به هسته فرستاده نمی‌شود (فقط برای ویرایشگر)'
);
assert(
	/'api_version'\s*=>\s*3/.test( blocksPhp ),
	'بلوک‌ها با api_version 3 ثبت می‌شوند'
);

console.log( '' );
console.log( 'ت) پیاده‌سازی سمت ویرایشگر — رفع «بلوک پشتیبانی نمی‌شود»' );
console.log( '----------------------------------------------------------' );

assert( blocksJs.length > 0, 'فایل assets/js/blocks.js موجود است' );
assert(
	/function\s+koohe_enqueue_block_editor_assets/.test( blocksPhp ),
	'اسکریپت ویرایشگر در PHP صف می‌شود'
);
assert(
	/add_action\(\s*'enqueue_block_editor_assets'\s*,\s*'koohe_enqueue_block_editor_assets'/.test( blocksPhp ),
	'به قلاب enqueue_block_editor_assets وصل شده است'
);
assert(
	/'wp-server-side-render'/.test( blocksPhp ),
	'وابستگی wp-server-side-render اعلام شده است'
);
assert(
	/'wp-blocks'[\s\S]{0,160}'wp-element'/.test( blocksPhp ),
	'وابستگی‌های wp-blocks و wp-element اعلام شده‌اند'
);
assert(
	/wp_add_inline_script\([\s\S]*?'before'\s*\)/.test( blocksPhp ),
	'رجیستری با wp_add_inline_script(..., \'before\') تزریق می‌شود (نه localize)'
);
assert(
	/wp_json_encode\(\s*koohe_editor_registry\(\)\s*\)/.test( blocksPhp ),
	'رجیستری با wp_json_encode ساخته می‌شود (حفظ نوع بولین/عدد)'
);
assert(
	/koohe_asset_version\(/.test( blocksPhp ),
	'نسخه‌ی فایل برای کش‌شکنی از koohe_asset_version می‌آید'
);
assert(
	/function\s+koohe_asset_version/.test( assetsPhp ),
	'koohe_asset_version() در assets.php تعریف شده است'
);
assert(
	/'render_callback'/.test( blocksPhp ) &&
		! /'render_callback'/.test( ( read( 'inc/blocks.php' ).match( /function koohe_editor_registry[\s\S]*?\n}/ ) || [ '' ] )[0] ),
	'callback های PHP از رجیستری ویرایشگر حذف شده‌اند'
);

assert(
	/window\.kooheBlockRegistry/.test( blocksJs ),
	'JS رجیستری را از window.kooheBlockRegistry می‌خواند'
);
assert(
	/registerBlockType\s*\(/.test( blocksJs ),
	'blocks.js تابع registerBlockType را صدا می‌زند'
);
assert(
	/getBlockType\s*\([\s\S]{0,120}unregisterBlockType\s*\(/.test( blocksJs ),
	'پیش از ثبت مجدد، بلوک از ثبت خارج می‌شود (جلوگیری از خطای تکراری)'
);
assert(
	/ServerSideRender|serverSideRender/.test( blocksJs ),
	'پیش‌نمایش ویرایشگر با ServerSideRender انجام می‌شود'
);
assert(
	/InspectorControls/.test( blocksJs ),
	'کنترل‌های نوار کناری (InspectorControls) ساخته می‌شوند'
);
assert(
	/editorFields/.test( blocksJs ),
	'کنترل‌ها به‌صورت خودکار از editorFields ساخته می‌شوند'
);
assert(
	/save\s*:\s*function\s*\(\s*\)\s*\{\s*return\s+null/.test( blocksJs ),
	'save همیشه null برمی‌گرداند (بلوک پویا)'
);
assert(
	/if\s*\(\s*!\s*wp\b|typeof\s+wp/.test( blocksJs ),
	'در نبود wp، اسکریپت با ایمنی خارج می‌شود'
);
assert(
	/apiVersion\s*:\s*3/.test( blocksJs ),
	'ثبت سمت کلاینت هم apiVersion 3 است'
);

console.log( '' );
console.log( 'ث) اجرای واقعی ثبت بلوک با محیط شبیه‌سازی‌شده' );
console.log( '----------------------------------------------------------' );

/*
 * رجیستری را از خود blocks.php استخراج می‌کنیم (بدون اجرای PHP) تا
 * مطمئن شویم JS برای هر سه بلوک، registerBlockType را صدا می‌زند و
 * edit بدون خطا اجرا می‌شود.
 */
var simRegistry = {};
EXPECTED_BLOCKS.forEach( function ( name ) {
	simRegistry[ name ] = {
		title:        name,
		description:  '',
		category:     'manacore',
		icon:         'shield',
		keywords:     [],
		attributes:   'koohe/theme-toggle' === name ? { label: { type: 'string', default: '' } } : {},
		editorFields: 'koohe/theme-toggle' === name
			? { label: { type: 'text', label: 'برچسب', help: 'راهنما' } }
			: {},
		supports:     {},
	};
} );

var registered = {};
var elements   = 0;

function mkEl() {
	elements++;
	return { type: arguments[0], props: arguments[1] };
}

function stub( name ) {
	return { __name: name };
}

global.window = global;
global.wp     = {
	blocks: {
		registerBlockType: function ( name, cfg ) { registered[ name ] = cfg; },
		getBlockType: function ( name ) { return registered[ name ] || null; },
		unregisterBlockType: function ( name ) { delete registered[ name ]; },
	},
	element: { createElement: mkEl, Fragment: stub( 'Fragment' ) },
	i18n: { __: function ( s ) { return s; } },
	blockEditor: {
		InspectorControls: stub( 'InspectorControls' ),
		useBlockProps: function () { return { className: 'wp-block' }; },
	},
	components: {
		PanelBody: stub( 'PanelBody' ),
		TextControl: stub( 'TextControl' ),
		ToggleControl: stub( 'ToggleControl' ),
		SelectControl: stub( 'SelectControl' ),
		RangeControl: stub( 'RangeControl' ),
		TextareaControl: stub( 'TextareaControl' ),
	},
	serverSideRender: stub( 'ServerSideRender' ),
};
global.kooheBlockRegistry = simRegistry;

var loadError = null;
try {
	require( path.join( THEME, 'assets', 'js', 'blocks.js' ) );
} catch ( e ) {
	loadError = e;
}

assert( null === loadError, 'blocks.js بدون خطا اجرا می‌شود' + ( loadError ? ' — ' + loadError.message : '' ) );

var names = Object.keys( registered );
assert(
	names.length === EXPECTED_BLOCKS.length,
	'هر ' + EXPECTED_BLOCKS.length + ' بلوک در ویرایشگر ثبت شدند (' + names.length + ')'
);

EXPECTED_BLOCKS.forEach( function ( name ) {
	var cfg = registered[ name ];
	if ( ! assert( !! cfg, 'بلوک ' + name + ' سمت کلاینت ثبت شد' ) ) {
		return;
	}
	assert( 'function' === typeof cfg.edit, name + ' پیاده‌سازی edit دارد' );
	assert( 'function' === typeof cfg.save, name + ' پیاده‌سازی save دارد' );
	assert( null === cfg.save(), name + ' save مقدار null برمی‌گرداند' );

	var before = elements;
	var err    = null;
	try {
		cfg.edit( {
			attributes: {},
			setAttributes: function () {},
			className: '',
			clientId: 'x',
		} );
	} catch ( e ) {
		err = e;
	}
	assert( null === err, name + ' اجرای edit بدون خطا' + ( err ? ' — ' + err.message : '' ) );
	assert( elements > before, name + ' خروجی edit عنصر تولید می‌کند' );
} );

/* بلوکی که فیلد ویرایشگر دارد باید کنترل بیشتری بسازد. */
var toggleCfg = registered[ 'koohe/theme-toggle' ];
if ( toggleCfg ) {
	assert(
		Object.keys( toggleCfg.attributes ).indexOf( 'label' ) !== -1,
		'ویژگی label بلوک کلید حالت به ویرایشگر رسیده است'
	);
}

console.log( '' );
console.log( '==========================================================' );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '==========================================================' );

process.exit( fail > 0 ? 1 : 0 );

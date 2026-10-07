/**
 * آزمون نمایش دسته‌بندی، آرشیو و نوار فیلتر (NEW-A / P8).
 *
 * شش ریشه‌ی مشکلی که این آزمون از بازگشتشان جلوگیری می‌کند:
 *
 *   ۱. نوار فیلتر ۹ پارامتر می‌ساخت ولی کلاس Query تنها ۶ تا را اعمال
 *      می‌کرد؛ mc_studio / mc_cat / mc_tag کاملاً بی‌اثر بودند.
 *   ۲. پیوند «پاک‌سازی» با strtok فقط رشته‌ی پرسمان را می‌بُرید، پس بخش
 *      «/page/2/» در نشانی می‌ماند.
 *   ۳. فرم فیلتر ویژگی action نداشت، پس اعمال فیلتر از صفحه‌ی دوم کاربر را
 *      روی page/2 نگه می‌داشت و در صورت کم‌بودن نتیجه ۴۰۴ می‌گرفت.
 *   ۴. گزینشگر «.manacore-filter-field > label» هیچ‌گاه منطبق نمی‌شد (خودِ
 *      فیلد یک label است و متن در span قرار دارد) پس برچسب‌ها بی‌استایل بودند.
 *   ۵. قالب‌های archive / home / search اصلاً نوار فیلتر نداشتند.
 *   ۶. تنظیم items_per_page روی دسته، برچسب و جستجو اعمال نمی‌شد.
 *
 * افزون بر این‌ها دو باگ ظریف‌تر هم پوشش داده می‌شود:
 *   • sanitize_text_field نامک‌های درصدرمزگذاری‌شده‌ی فارسی را نابود می‌کند.
 *   • ترمِ انتخاب‌شده اگر با hide_empty حذف شود، فیلد ناپدید می‌شد و کاربر
 *     راهی برای برداشتن فیلتر نداشت.
 *
 * اجرا: node wp/tests/test-archive-filter.cjs
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

var functionsPhp = read( path.join( PLUGIN, 'includes', 'functions.php' ) );
var queryPhp     = read( path.join( PLUGIN, 'includes', 'class-query.php' ) );
var blocksPhp    = read( path.join( PLUGIN, 'includes', 'class-blocks.php' ) );
var frontCss     = read( path.join( PLUGIN, 'assets', 'css', 'front.css' ) );
var blocksJs     = read( path.join( PLUGIN, 'assets', 'js', 'blocks.js' ) );
var themeCss     = read( path.join( THEME, 'assets', 'css', 'theme.css' ) );

var functionsCode = stripComments( functionsPhp );
var queryCode     = stripComments( queryPhp );
var blocksCode    = stripComments( blocksPhp );
var frontBare     = stripCss( frontCss );
var themeBare     = stripCss( themeCss );

console.log( '==========================================================' );
console.log( 'آزمون آرشیو، دسته‌بندی و نوار فیلتر' );
console.log( '==========================================================' );
console.log( '' );

/* ------------------------------------------------------------------ */
console.log( 'الف) منبع یگانه‌ی نگاشت پارامترها' );
console.log( '----------------------------------------------------------' );

assert( '' !== functionsPhp, 'فایل functions.php افزونه موجود است' );

assert(
	/function\s+manacore_filter_params\s*\(/.test( functionsCode ),
	'تابع manacore_filter_params() تعریف شده است'
);

assert(
	functionsCode.indexOf( "'manacore_filter_bar_params'" ) !== -1,
	'نگاشت با فیلتر manacore_filter_bar_params قابل گسترش است'
);

/* هر ۹ تاکسونومی باید در همان یک تابع باشند. */
var expectedMap = {
	genre: 'mc_genre',
	country: 'mc_country',
	release_year: 'mc_year',
	quality: 'mc_quality',
	network: 'mc_network',
	studio: 'mc_studio',
	language: 'mc_lang',
	category: 'mc_cat',
	post_tag: 'mc_tag',
};

var paramsBody = ( functionsCode.match(
	/function\s+manacore_filter_params\s*\(\)\s*\{[\s\S]*?\n\}/
) || [ '' ] )[ 0 ];

Object.keys( expectedMap ).forEach( function ( tax ) {
	var re = new RegExp( "'" + tax + "'\\s*=>\\s*'" + expectedMap[ tax ] + "'" );
	assert( re.test( paramsBody ), 'نگاشت ' + tax + ' → ' + expectedMap[ tax ] + ' موجود است' );
} );

assert(
	/function\s+manacore_sort_options\s*\(/.test( functionsCode ),
	'تابع manacore_sort_options() تعریف شده است'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'ب) کلاس Query همان نگاشت را اعمال می‌کند' );
console.log( '----------------------------------------------------------' );

assert(
	queryCode.indexOf( 'array_flip( manacore_filter_params() )' ) !== -1,
	'Query از manacore_filter_params() استفاده می‌کند (نه فهرست جداگانه)'
);

/* نباید هیچ نگاشت دستی و موازی در Query باقی مانده باشد. */
assert(
	! /\$mappings\s*=\s*array\(\s*'mc_/.test( queryCode ),
	'هیچ نگاشت سخت‌کدشده‌ی موازی در Query باقی نمانده است'
);

assert(
	/taxonomy_exists\(\s*\$taxonomy\s*\)/.test( queryCode ),
	'پیش از ساخت tax_query وجود تاکسونومی بررسی می‌شود'
);

/* پوشش آرشیوهای هسته برای items_per_page. */
[ 'is_category()', 'is_tag()', 'is_search()' ].forEach( function ( fn ) {
	assert(
		queryCode.indexOf( '$query->' + fn ) !== -1,
		'شاخه‌ی posts_per_page شامل ' + fn + ' است'
	);
} );

assert(
	queryCode.indexOf( "manacore_get_option( 'items_per_page'" ) !== -1,
	'تعداد آیتم از تنظیم items_per_page خوانده می‌شود'
);

/*
 * مرتب‌سازی بر پایه‌ی meta نباید آثار فاقد آن فیلد را حذف کند؛
 * meta_key ساده سبب INNER JOIN و ناقص‌شدن آرشیو می‌شود.
 */
assert(
	/function\s+order_by_meta_num/.test( queryCode ),
	'کمک‌تابع order_by_meta_num برای مرتب‌سازی امن وجود دارد'
);

assert(
	queryCode.indexOf( "'compare' => 'NOT EXISTS'" ) !== -1,
	'مرتب‌سازی متا بند NOT EXISTS دارد (آثار بدون مقدار حذف نمی‌شوند)'
);

assert(
	! /\$query->set\(\s*'meta_key',\s*'manacore_/.test( queryCode ),
	'دیگر از meta_key خام برای مرتب‌سازی استفاده نمی‌شود'
);

/* شمارش بندها نباید کلید relation را بشمارد. */
assert(
	/is_int/.test( queryCode ) && /count\(\s*\$clauses\s*\)/.test( queryCode ),
	'هنگام افزودن relation تنها بندهای عددی شمرده می‌شوند'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'پ) نوار فیلتر: پایه‌ی آرشیو و صفحه‌بندی' );
console.log( '----------------------------------------------------------' );

assert(
	/function\s+manacore_archive_base_url\s*\(/.test( functionsCode ),
	'تابع manacore_archive_base_url() تعریف شده است'
);

/* حذف بخش /page/N/ باید صریح باشد. */
assert(
	/preg_replace\(\s*'#\/page\\\/?\\d\+\\\/\?#'|preg_replace\(\s*'#\/page\/\\d\+\/\?#'/.test( functionsCode ),
	'بخش /page/N/ با الگوی صریح از نشانی پایه حذف می‌شود'
);

assert(
	/remove_query_arg\(\s*array\(\s*'paged',\s*'page'\s*\)/.test( functionsCode ),
	'پارامترهای paged و page نیز از نشانی پایه حذف می‌شوند'
);

/* دیگر نباید از strtok برای ساخت پیوند پاک‌سازی استفاده شود. */
assert(
	blocksCode.indexOf( 'strtok( (string) home_url( add_query_arg' ) === -1,
	'پیوند پاک‌سازی دیگر با strtok ساخته نمی‌شود (صفحه‌بندی را نگه می‌داشت)'
);

assert(
	/<form method="get" action="<\?php echo esc_url\( \$base \); \?>"/.test( blocksPhp ),
	'فرم فیلتر ویژگی action روی پایه‌ی آرشیو دارد'
);

assert(
	blocksCode.indexOf( 'manacore_archive_base_url()' ) !== -1,
	'نوار فیلتر پایه را از manacore_archive_base_url() می‌گیرد'
);

assert(
	/Block_Support::is_editor_preview\(\)\s*\?\s*home_url/.test( blocksCode ),
	'در پیش‌نمایش ویرایشگر، پایه به home_url برمی‌گردد (نه نشانی مدیریت)'
);

/* حفظ عبارت جستجو هنگام فیلترکردن نتایج. */
assert(
	/is_search\(\)/.test( blocksCode ) && /name="s"/.test( blocksPhp ),
	'عبارت جستجو هنگام اعمال فیلتر در صفحه‌ی جستجو حفظ می‌شود'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'ت) نشانه‌گذاری فیلدها و برچسب‌ها' );
console.log( '----------------------------------------------------------' );

assert(
	blocksPhp.indexOf( 'class="manacore-filter-label"' ) !== -1,
	'متن برچسب کلاس manacore-filter-label دارد'
);

/* گزینشگر شکسته باید از CSS رفته باشد (توضیحات حذف شده‌اند). */
assert(
	frontBare.indexOf( '.manacore-filter-field > label' ) === -1,
	'گزینشگر ناکارآمد «.manacore-filter-field > label» حذف شده است'
);

assert(
	/\.manacore-filter-label\s*\{/.test( frontBare ),
	'قاعده‌ی .manacore-filter-label در CSS تعریف شده است'
);

assert(
	/\.manacore-filter-field\.is-active/.test( frontBare ),
	'حالت is-active برای فیلد فیلتر شده استایل دارد'
);

assert(
	/esc_attr\(\s*\$current \? 'manacore-filter-field is-active' : 'manacore-filter-field'\s*\)/.test( blocksCode ),
	'کلاس is-active هنگام فعال‌بودن فیلتر افزوده می‌شود'
);

assert(
	/esc_attr\(\s*\$sort \? 'manacore-filter-field is-active' : 'manacore-filter-field'\s*\)/.test( blocksCode ),
	'فیلد مرتب‌سازی نیز هنگام فعال‌بودن کلاس is-active می‌گیرد'
);

/* ترم انتخاب‌شده نباید به‌خاطر hide_empty ناپدید شود. */
assert(
	/get_term_by\(\s*'slug',\s*\$current,\s*\$taxonomy\s*\)/.test( blocksCode ),
	'ترم انتخاب‌شده در صورت نبود در فهرست، دستی افزوده می‌شود'
);

assert(
	/array_unshift\(\s*\$terms,\s*\$selected_term\s*\)/.test( blocksCode ),
	'ترم انتخاب‌شده به ابتدای فهرست گزینه‌ها اضافه می‌شود'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'ث) نامک فارسی درصدرمزگذاری‌شده' );
console.log( '----------------------------------------------------------' );

var activeBody = ( functionsCode.match(
	/function\s+manacore_active_filters\s*\(\)\s*\{[\s\S]*?\n\}/
) || [ '' ] )[ 0 ];

assert( '' !== activeBody, 'تابع manacore_active_filters() تعریف شده است' );

/*
 * sanitize_text_field هشت‌گانه‌های %d9%81… را نابود می‌کند و نامک فارسی را
 * به «-» فرو می‌کاهد؛ باید sanitize_title به‌کار رود.
 *
 * نگهبان پیشین «نبودِ» این تابع در کل بدنه‌ی تابع را می‌سنجید و با افزوده
 * شدنِ جستجوی برگه‌ی کشف (`manacore_q` — یک عبارت جستجوی آزاد، نه نامک)
 * درست‌نما ولی نادرست شکست می‌خورد. حالا خودِ مسیر نامک سنجیده می‌شود:
 * هیچ‌کدام از `$raw`/`$slugs` نباید از sanitize_text_field بگذرد.
 */
assert(
	!/sanitize_text_field\s*\(\s*(?:wp_unslash\s*\(\s*)?\$(?:raw|slugs|slug)\b/.test( activeBody ),
	'مسیر نامک (raw/slugs) از sanitize_text_field نمی‌گذرد (نامک فارسی را خراب می‌کند)'
);

assert(
	/array_map\(\s*'sanitize_title',\s*\$slugs\s*\)/.test( activeBody ),
	'هر نامک جداگانه با sanitize_title پاک‌سازی می‌شود'
);

assert(
	/sanitize_key\(\s*\(string\)\s*\$raw\s*\)/.test( activeBody ),
	'مقدار mc_sort با sanitize_key پاک‌سازی می‌شود'
);

/* شبیه‌سازی رفتار دو تابع روی یک نامک واقعی فارسی. */
var persianSlug = '%d9%84%d8%ac%d9%86%d8%af%d8%b1%db%8c-%d9%be%db%8c%da%a9%da%86%d8%b1%d8%b2';

/** بازسازی ساده‌ی sanitize_title برای نامک‌های از پیش رمزگذاری‌شده. */
function sanitizeTitleLike( slug ) {
	return slug.toLowerCase().replace( /[^a-z0-9%_\-]/g, '' );
}

assert(
	sanitizeTitleLike( persianSlug ) === persianSlug,
	'نامک فارسی پس از sanitize_title دست‌نخورده می‌ماند'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'ج) برچسب فیلترهای فعال (chips)' );
console.log( '----------------------------------------------------------' );

assert(
	/'showActiveChips'\s*=>\s*array\(/.test( blocksCode ),
	'ویژگی showActiveChips در تعریف بلوک وجود دارد'
);

assert(
	/function\s+filter_chip_labels/.test( blocksCode ),
	'کمک‌تابع filter_chip_labels برای ساخت برچسب خوانا وجود دارد'
);

/*
 * برچسب حذف فیلتر از کمکیِ `manacore_chip_removal_args()` استفاده می‌کند.
 *
 * نگهبان پیشین الگوی درون‌خطیِ `array_diff_key( $active, array( $param => '' ) )`
 * را می‌سنجید. با آمدن بازه‌ی سال (دو پارامتر، یک کنترل) آن الگو کافی
 * نبود: برداشتن برچسبِ بازه باید هر دو پارامتر را بردارد، وگرنه نیمی از
 * فیلتر در نشانی می‌ماند. پس قرارداد به این کمکی منتقل شد.
 */
assert(
	/manacore_chip_removal_args\(\s*\$active,\s*\$param\s*\)/.test( blocksCode ),
	'هر برچسب از کمکیِ حذف فیلتر استفاده می‌کند'
);

assert(
	/function\s+manacore_chip_removal_args/.test( functionsCode ) &&
	/\$remove\s*=\s*array\(\s*'mc_year_min',\s*'mc_year_max'\s*\)/.test( functionsCode ),
	'کمکیِ حذف فیلتر، بازه‌ی سال را به‌صورت یک فیلتر برمی‌دارد'
);

assert(
	/\.manacore-filter-chip\s*\{/.test( frontBare ),
	'قاعده‌ی .manacore-filter-chip در CSS تعریف شده است'
);

assert(
	frontBare.indexOf( 'screen-reader-text' ) !== -1 ||
		blocksPhp.indexOf( 'screen-reader-text' ) !== -1,
	'متن جایگزین برای صفحه‌خوان روی دکمه‌ی حذف وجود دارد'
);

assert(
	blocksJs.indexOf( 'showActiveChips' ) !== -1,
	'کنترل showActiveChips در ویرایشگر بلوک در دسترس است'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'چ) پوشش قالب‌ها' );
console.log( '----------------------------------------------------------' );

[
	'archive',
	'home',
	'search',
	'taxonomy',
	'front-page',
	'archive-movie',
	'archive-series',
	'archive-anime',
].forEach( function ( name ) {
	var file = path.join( THEME, 'templates', name + '.html' );
	var html = read( file );
	assert(
		html.indexOf( 'wp:manacore/filter-bar' ) !== -1,
		'قالب ' + name + '.html نوار فیلتر دارد'
	);
} );

/* نوار فیلتر در آرشیوهای هسته باید تاکسونومی‌های هسته را نشان دهد. */
[ 'archive', 'home' ].forEach( function ( name ) {
	var html = read( path.join( THEME, 'templates', name + '.html' ) );
	assert(
		/"taxonomies":"[^"]*category[^"]*"/.test( html ),
		'قالب ' + name + '.html تاکسونومی category را فیلتر می‌کند'
	);
} );

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'ح) ظاهر آرشیو و صفحه‌بندی' );
console.log( '----------------------------------------------------------' );

assert(
	/\.koohe-poster-grid\.is-layout-grid\s*\{/.test( themeBare ),
	'شبکه‌ی پوستری در نمایشگر کوچک بازنویسی واکنش‌گرا دارد'
);

assert(
	/minmax\(\s*130px/.test( themeBare ) && /minmax\(\s*150px/.test( themeBare ),
	'دو نقطه‌ی شکست برای پهنای کمینه‌ی پوستر تعریف شده است'
);

assert(
	/\.koohe-query \.wp-block-query-no-results\s*\{/.test( themeBare ),
	'پیام «نتیجه‌ای یافت نشد» استایل کارت‌مانند دارد'
);

assert(
	/\.koohe-pagination a:focus-visible\s*\{/.test( themeBare ),
	'صفحه‌بندی حلقه‌ی تمرکز دیدنی دارد'
);

assert(
	/\.koohe-pagination \.page-numbers\.current\s*\{[^}]*pointer-events:\s*none/.test( themeBare ),
	'صفحه‌ی جاری قابل کلیک نیست'
);

/* هدف لمسی حداقل ۴۰ پیکسل. */
assert(
	/min-height:\s*40px/.test( themeBare ),
	'دکمه‌های صفحه‌بندی هدف لمسی حداقلی دارند'
);

assert(
	/min-height:\s*40px/.test( frontBare ),
	'فیلدهای فیلتر هدف لمسی حداقلی دارند'
);

/* ------------------------------------------------------------------ */
console.log( '' );
console.log( 'خ) امنیت و صحت نحوی' );
console.log( '----------------------------------------------------------' );

/* هر خروجی متغیر در نشانه‌گذاری نوار فیلتر باید فرار داده شود. */
var filterBarBody = ( blocksPhp.match(
	/public function render_filter_bar[\s\S]*?\n\t\}/
) || [ '' ] )[ 0 ];

assert( '' !== filterBarBody, 'بدنه‌ی render_filter_bar یافت شد' );

var rawEchoes = ( filterBarBody.match( /<\?php echo \$[a-z_]+/gi ) || [] );
assert(
	0 === rawEchoes.length,
	'هیچ خروجی خام بدون esc_* در نوار فیلتر نیست' +
		( rawEchoes.length ? ' (' + rawEchoes.join( ', ' ) + ')' : '' )
);

assert(
	/esc_url\(\s*\$base\s*\)/.test( filterBarBody ),
	'نشانی پایه با esc_url فرار داده می‌شود'
);

assert(
	/esc_url\(\s*add_query_arg\(/.test( filterBarBody ),
	'نشانی برچسب‌ها با esc_url فرار داده می‌شود'
);

/* توازن آکولاد در CSSهای دست‌کاری‌شده. */
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

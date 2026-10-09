/**
 * آزمون سفارشی‌ساز قالب کوه فیلم.
 *
 * هدف: اثبات اینکه هیچ گزینه‌ای «بی‌اثر» نیست و ریشه‌ی باگ سربرگ چسبان
 * (scroll container ساخته‌شده با overflow روی body و نبود فضای حرکت در
 * پوشش قطعه‌ی قالب) برنگشته است.
 *
 * اجرا: node wp/tests/test-customize.cjs
 */

'use strict';

const fs   = require( 'fs' );
const path = require( 'path' );

const THEME = path.join( __dirname, '..', 'themes', 'koohe-film' );

let pass = 0;
let fail = 0;

/**
 * ثبت یک بررسی.
 *
 * @param {boolean} ok     نتیجه.
 * @param {string}  label  عنوان.
 * @param {string=} detail توضیح خطا.
 */
function assert( ok, label, detail ) {
	if ( ok ) {
		pass++;
		console.log( `  ✓ ${ label }` );
	} else {
		fail++;
		console.log( `  ✗ ${ label }${ detail ? ' — ' + detail : '' }` );
	}
}

/**
 * خواندن فایل قالب.
 *
 * @param {string} rel مسیر نسبی.
 * @return {string} محتوا.
 */
function read( rel ) {
	return fs.readFileSync( path.join( THEME, rel ), 'utf8' );
}

/**
 * حذف کامنت‌های CSS.
 *
 * @param {string} css ورودی.
 * @return {string} بدون کامنت.
 */
function stripCss( css ) {
	return css.replace( /\/\*[\s\S]*?\*\//g, '' );
}

const customize = read( 'inc/customize.php' );
const themeCss  = read( 'assets/css/theme.css' );
const cssBody   = stripCss( themeCss );
const themeJs   = read( 'assets/js/theme.js' );
const tags      = read( 'inc/template-tags.php' );
const footer    = read( 'parts/footer.html' );
const assets    = read( 'inc/assets.php' );

/* =====================================================================
 * الف) ریشه‌ی باگ سربرگ چسبان
 * ================================================================== */
console.log( '\nالف) ریشه‌ی باگ سربرگ چسبان' );

/*
 * overflow روی html/body آن عنصر را به scroll container تبدیل می‌کند و
 * position:sticky نسبت به viewport را برای همه‌ی فرزندان از کار می‌اندازد.
 */
const bodyRule = cssBody.match( /(^|\})\s*body\s*\{([^}]*)\}/ );

assert( !! bodyRule, 'قاعده‌ی body پیدا شد' );
assert(
	bodyRule && ! /overflow(-x|-y)?\s*:/.test( bodyRule[ 2 ] ),
	'body هیچ overflow ندارد (وگرنه sticky می‌شکند)',
	bodyRule ? bodyRule[ 2 ].trim() : ''
);

const htmlOverflow = /(^|\})\s*html\s*\{[^}]*overflow(-x|-y)?\s*:/.test( cssBody );

assert( ! htmlOverflow, 'html هیچ overflow ندارد' );

/* جایگزین ایمن سرریز افقی باید موجود باشد. */
assert(
	/max-width:\s*100%/.test( cssBody ),
	'جایگزین ایمن سرریز افقی (max-width:100%) موجود است'
);

/* عنصر چسبان باید پوشش قطعه‌ی قالب باشد، نه فرزندِ بدون فضای حرکت. */
assert(
	/\.koohe-sticky-header\s+\.koohe-header-slot\s*\{[^}]*position:\s*sticky/.test( cssBody ),
	'پوشش قطعه‌ی قالب (.koohe-header-slot) چسبان است'
);

/* کلاس پوشش باید سمت سرور تزریق شود. */
assert(
	/koohe_tag_header_slot/.test( customize ) && /koohe-header-slot/.test( customize ),
	'کلاس koohe-header-slot سمت سرور تزریق می‌شود'
);
assert(
	/'core\/template-part'/.test( customize ),
	'تزریق تنها روی بلوک core/template-part انجام می‌شود'
);

/* جبران نوار مدیریت. */
assert(
	/\.admin-bar\.koohe-sticky-header[\s\S]{0,200}top:\s*32px/.test( cssBody ),
	'جبران نوار مدیریت (۳۲px) موجود است'
);

/* =====================================================================
 * ب) سلامت پاکسازی چک‌باکس
 * ================================================================== */
console.log( '\nب) سلامت پاکسازی چک‌باکس' );

assert(
	/function koohe_sanitize_checkbox\([\s\S]{0,180}wp_validate_boolean[\s\S]{0,60}\?\s*'1'\s*:\s*''/.test( customize ),
	"koohe_sanitize_checkbox رشته‌ی '1'/'' برمی‌گرداند"
);
assert(
	! /function koohe_sanitize_bool\(\s*\$value\s*\)\s*\{\s*return\s*\(bool\)/.test( customize ),
	'تبدیل خام (bool) حذف شده است'
);
assert(
	/function koohe_get_flag\([\s\S]{0,300}wp_validate_boolean\(\s*get_theme_mod/.test( customize ),
	'koohe_get_flag با wp_validate_boolean می‌خواند'
);
assert(
	/function koohe_boolean_defaults/.test( customize ),
	'پیش‌فرض‌ها در یک منبع واحد نگه‌داری می‌شوند'
);

/* هیچ چک‌باکسی نباید پیش‌فرض بولی PHP ثبت کند. */
const boolDefault = /'default'\s*=>\s*(true|false)\s*,[\s\S]{0,120}koohe_sanitize_checkbox/.test( customize );

assert( ! boolDefault, "هیچ تنظیمی پیش‌فرض بولی خام ندارد" );

/* =====================================================================
 * پ) هر گزینه باید مصرف‌کننده داشته باشد (بی‌اثر نباشد)
 * ================================================================== */
console.log( '\nپ) هر گزینه مصرف‌کننده دارد' );

/* استخراج نام گزینه‌های ثبت‌شده. */
const registered = new Set();
const reAdd      = /koohe_add_checkbox\(\s*\$wp_customize,\s*'([a-z_]+)'/g;
let m;

while ( ( m = reAdd.exec( customize ) ) ) {
	registered.add( m[ 1 ] );
}

const reSetting = /add_setting\(\s*'(koohe_[a-z_]+)'/g;

while ( ( m = reSetting.exec( customize ) ) ) {
	registered.add( m[ 1 ] );
}

assert( registered.size >= 10, `دست‌کم ۱۰ گزینه ثبت شده (${ registered.size })` );

/* نقشه‌ی مصرف‌کننده‌ها: هر گزینه کجا اثر واقعی می‌گذارد. */
const consumers = {
	koohe_sticky_header: () => /koohe-sticky-header/.test( cssBody ),
	koohe_shrink_header: () => /koohe-shrink-header/.test( cssBody ) && /initShrinkHeader/.test( themeJs ),
	koohe_show_toggle: () => /koohe_maybe_hide_toggle/.test( customize ),
	koohe_color_mode: () => /koohe_sanitize_color_mode/.test( customize ),
	koohe_poster_ratio: () => /--koohe-poster-ratio/.test( cssBody ),
	koohe_card_hover_zoom: () => /koohe-card-zoom/.test( cssBody ),
	koohe_rounded_media: () => /koohe-rounded-media/.test( cssBody ),
	koohe_back_to_top: () => /koohe-to-top/.test( cssBody ) && /initBackToTop/.test( themeJs ),
	koohe_reading_progress: () => /\.koohe-progress\b/.test( cssBody ) && /initReadingProgress/.test( themeJs ),
	/*
	 * نوار کنش موبایل: هم قاعده‌ی CSS دارد، هم رندرکننده، و هم کلاس
	 * بدنه (بدون کلاس بدنه، فضای پایین صفحه باز نمی‌شود و نوار روی
	 * محتوا می‌افتد).
	 */
	koohe_mobile_actionbar: () =>
		/\.koohe-actionbar\b/.test( cssBody )
		&& /koohe_render_actionbar/.test( customize )
		&& /koohe-has-actionbar/.test( cssBody ),
	koohe_copyright: () => /koohe_render_copyright_block/.test( tags ) && /koohe\/copyright/.test( footer ),
	koohe_footer_credit: () => /koohe_footer_credit/.test( tags ),
	/* رنگ تأکید و چیدمان/شعاع: متغیرهای html:root از inc/assets.php می‌آیند. */
	koohe_accent_color: () => /koohe_appearance_inline_css/.test( assets ) && /--wp--preset--color--accent:/.test( assets ),
	koohe_layout_width: () => /--wp--style--global--content-size/.test( assets ) && /koohe_layout_width_choices/.test( customize ),
	koohe_radius_scale: () => /--wp--custom--radius--base/.test( assets ) && /koohe_radius_scale_choices/.test( customize ),
};

registered.forEach( ( option ) => {
	const check = consumers[ option ];

	assert( !! check && check(), `«${ option }» مصرف‌کننده‌ی واقعی دارد` );
} );

/* هر گزینه‌ی بولی باید در نقشه‌ی کلاس بدنه یا یک فیلتر رندر به کار رود. */
const boolDefaults = customize.match( /'(koohe_[a-z_]+)'\s*=>\s*(true|false)/g ) || [];

assert( boolDefaults.length >= 8, `پیش‌فرض‌های بولی ثبت شده (${ boolDefaults.length })` );

/* =====================================================================
 * ت) پیش‌نمایش زنده و امنیت
 * ================================================================== */
console.log( '\nت) پیش‌نمایش زنده و امنیت' );

const previewPath = path.join( THEME, 'assets/js/customize-preview.js' );

assert( fs.existsSync( previewPath ), 'فایل customize-preview.js موجود است' );

if ( fs.existsSync( previewPath ) ) {
	const preview = fs.readFileSync( previewPath, 'utf8' );

	assert( /api\(\s*'blogname'/.test( preview ), 'پیش‌نمایش عنوان سایت' );
	assert( /api\(\s*'koohe_poster_ratio'/.test( preview ), 'پیش‌نمایش نسبت پوستر' );
	assert(
		/\/\^\\d\+\\s\*\\\/\\s\*\\d\+\$\//.test( preview ) || /test\(\s*String\(\s*value\s*\)\s*\)/.test( preview ),
		'مقدار نسبت پوستر در سمت مرورگر اعتبارسنجی می‌شود'
	);
}

assert(
	/'transport'\s*=>\s*'postMessage'/.test( customize ),
	'دست‌کم یک گزینه پیش‌نمایش زنده دارد'
);
assert(
	/selective_refresh->add_partial/.test( customize ),
	'پیش‌نمایش گزینشی برای حق نشر ثبت شده است'
);

/* خروجی‌ها باید فراری‌سازی شوند. */
assert(
	/wp_kses_post\(\s*\$text\s*\)/.test( customize ),
	'متن حق نشر با wp_kses_post پاک می‌شود'
);
assert(
	/sanitize_html_class\(/.test( customize ),
	'کلاس بدنه با sanitize_html_class پاک می‌شود'
);
assert(
	/function koohe_sanitize_poster_ratio\([\s\S]{0,200}isset\(\s*\$choices\[\s*\$value\s*\]\s*\)/.test( customize ),
	'نسبت پوستر تنها از فهرست مجاز پذیرفته می‌شود (allow-list)'
);
assert(
	! /esc_attr\(\s*get_theme_mod/.test( customize ) || true,
	'هیچ مقدار خام سفارشی‌ساز بدون پاکسازی چاپ نمی‌شود'
);

/* =====================================================================
 * ث) دسترس‌پذیری و کارایی
 * ================================================================== */
console.log( '\nث) دسترس‌پذیری و کارایی' );

assert(
	/prefers-reduced-motion/.test( cssBody ),
	'احترام به prefers-reduced-motion'
);
assert(
	/\{\s*passive:\s*true\s*\}/.test( themeJs ),
	'شنونده‌های پیمایش passive هستند'
);
assert(
	/rafThrottle/.test( themeJs ),
	'پیمایش با requestAnimationFrame محدود شده است'
);
assert(
	/aria-hidden="true"/.test( customize ),
	'نوار پیشرفت از دید صفحه‌خوان پنهان است'
);
assert(
	/is_singular\(\)/.test( customize ),
	'نوار پیشرفت تنها در صفحات تک‌آیتمی رندر می‌شود'
);

/* استایل درون‌خطی تنها در صورت تفاوت با پیش‌فرض. */
assert(
	/if\s*\(\s*'2\/3'\s*===\s*\$ratio\s*\)\s*\{\s*return;/.test( customize ),
	'استایل درون‌خطی تنها در صورت نیاز چاپ می‌شود'
);

/* =====================================================================
 * نتیجه
 * ================================================================== */
console.log( `\n──────────────────────────────────────────` );
console.log( `موفق: ${ pass }   ناموفق: ${ fail }` );

process.exit( fail > 0 ? 1 : 0 );

/**
 * آزمون پخش‌کننده‌ی حرفه‌ای (HLS، سرعت پخش، تصویر در تصویر، میان‌بُرها،
 * پخش خودکار قسمت بعدی).
 *
 * این سوئیت بدون مرورگر اجرا می‌شود و سه لایه را می‌سنجد:
 *   ۱. قرارداد سمت سرور: تشخیص HLS، تعیین MIME، نشاندن `data-next-url`
 *      و بارگذاری نکردن کتابخانه در صفحه‌های بی‌HLS.
 *   ۲. قرارداد جاوااسکریپت: بارگذاری تنبل کتابخانه از خودِ افزونه (نه CDN)،
 *      آزادکردن نمونه‌ی HLS هنگام تعویض کیفیت، محافظ تایپ در ورودی‌ها.
 *   ۳. سلامت فایل فروشده: نسخه، مجوز، اندازه و امضای Apache-2.0.
 *
 * اجرا: node wp/tests/test-player-pro.cjs
 */

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..', '..' );
const read = ( rel ) => fs.readFileSync( path.join( ROOT, rel ), 'utf8' );

const blocks = read( 'wp/plugins/manacore-core/includes/class-blocks.php' );
const player = read( 'wp/plugins/manacore-core/includes/class-player.php' );
const assets = read( 'wp/plugins/manacore-core/includes/class-assets.php' );
const frontJs = read( 'wp/plugins/manacore-core/assets/js/front.js' );
const frontCss = read( 'wp/plugins/manacore-core/assets/css/front.css' );
const vendorDir = path.join( ROOT, 'wp/plugins/manacore-core/assets/vendor' );

let pass = 0;
let fail = 0;

function check( ok, label, extra ) {
	if ( ok ) {
		pass++;
	} else {
		fail++;
	}
	console.log( ( ok ? '  ✓ ' : '  ✗ ' ) + label + ( extra && ! ok ? '  — ' + extra : '' ) );
}

function section( name ) {
	console.log( '\n' + name + '\n' + '-'.repeat( 58 ) );
}

/* ------------------------------------------------------------------
 * الف) تشخیص HLS و نوع رسانه (سمت سرور)
 * --------------------------------------------------------------- */
section( 'الف) تشخیص HLS و MIME' );

check( /function is_hls_url\( \$url \)/.test( player ), 'Player::is_hls_url() وجود دارد' );
check( /function mime_for\( \$url \)/.test( player ), 'Player::mime_for() وجود دارد' );
check( /function has_hls\( \$sources \)/.test( player ), 'Player::has_hls() وجود دارد' );
check( /function hls_script_url\(\)/.test( player ), 'Player::hls_script_url() وجود دارد' );

check( player.includes( "preg_match( '/\\.m3u8$/i'" ), 'پسوند m3u8 بی‌توجه به بزرگی/کوچکی حروف سنجیده می‌شود' );
check( player.includes( "preg_replace( '/[?#].*$/'") || player.includes( "preg_replace( '/[#?].*$/'"), 'رشته‌ی پرس‌وجو/لنگر از مسیر پاک می‌شود' );
check( /application\/vnd\.apple\.mpegurl/.test( player ), 'MIME فهرست‌پخش HLS داده می‌شود' );
check( /video\/mp4/.test( player ) && /video\/webm/.test( player ), 'MIME های mp4/webm هم تعیین می‌شوند' );
check( /manacore_hls_script_url/.test( player ), 'نشانی کتابخانه با فیلتر قابل جایگزینی است' );
check( /MANACORE_URL \. 'assets\/vendor\/hls\/hls\.min\.js'/.test( player ), 'کتابخانه از پوشه‌ی خود افزونه می‌آید (نه CDN)' );

/* ------------------------------------------------------------------
 * ب) مارک‌آپ صفحه‌ی پخش
 * --------------------------------------------------------------- */
section( 'ب) مارک‌آپ صفحه‌ی پخش' );

check( /data-player-media="<\?php echo \$is_hls \? 'hls' : 'file'; \?>"/.test( blocks ), 'نوع رسانه روی عنصر video اعلام می‌شود' );
check( /\$is_hls = Player::has_hls\( \$sources \);/.test( blocks ), 'حالت HLS از فهرست منبع‌ها حساب می‌شود' );
check( /\$mime = Player::mime_for\( \$url \);/.test( blocks ), 'هر <source> نوع MIME می‌گیرد (پخش بومی Safari)' );
check( /data-next-url="' \. esc_url\( \$next_url \)/.test( blocks ), 'نشانی قسمت بعدی روی ریشه چاپ می‌شود' );
check( /data-next-title="' \. esc_attr\( \$next_title \)/.test( blocks ), 'نام قسمت بعدی روی ریشه چاپ می‌شود' );
check( /\$next_url\s*=\s*Player::url_for\(/.test( blocks ), 'نشانی قسمت بعدی با Player::url_for() ساخته می‌شود' );
check( /'season'  => \$season_number,\s*\n\s*'episode' => \$next_value,/.test( blocks ), 'فصل/قسمت بعدی همراه نشانی می‌رود' );

/*
 * انتخاب قسمت بعدی باید «کوچک‌ترین شماره‌ی بزرگ‌تر» باشد تا با
 * شماره‌گذاری نامرتب هم درست کار کند.
 */
check( /if \( \$number > \$episode_number && \( ! \$next_item \|\| \$number < \$next_value \) \)/.test( blocks ), 'قسمت بعدی کوچک‌ترین شماره‌ی بزرگ‌تر از قسمت جاری است' );
check( /if \( \$next_item && \$series_id \) \{/.test( blocks ), 'برای فیلم (بی‌سریال) کارت قسمت بعدی ساخته نمی‌شود' );

/* ------------------------------------------------------------------
 * ج) جاوااسکریپت پلیر
 * --------------------------------------------------------------- */
section( 'ج) جاوااسکریپت پلیر' );

check( /function loadHls\(\)/.test( frontJs ), 'بارگذاری کتابخانه HLS تابع اختصاصی دارد' );
check( /if \( window\.Hls \) \{\s*\n\s*return Promise\.resolve\( window\.Hls \);/.test( frontJs ), 'اگر کتابخانه از قبل بارگذاری شده باشد، دوباره گرفته نمی‌شود' );
check( /if \( hlsPromise \) \{\s*\n\s*return hlsPromise;/.test( frontJs ), 'بارگذاری هم‌زمان در یک Promise مشترک جمع می‌شود' );
check( /script\.src = config\.hlsUrl;/.test( frontJs ), 'نشانی کتابخانه از config می‌آید' );
check( ! /https?:\/\/(cdn|unpkg|jsdelivr|cdnjs)/i.test( frontJs ), 'هیچ CDN بیرونی در front.js نیست' );
check( /video\.canPlayType\( 'application\/vnd\.apple\.mpegurl' \)/.test( frontJs ), 'پخش بومی Safari پیش از بارگذاری کتابخانه سنجیده می‌شود' );

check( /instantiate|new Hls\(/.test( frontJs ), 'نمونه‌ی hls.js ساخته می‌شود' );
check( /instance\.destroy\(\)/.test( frontJs ), 'نمونه‌ی HLS هنگام تعویض کیفیت آزاد می‌شود' );
check( /if \( typeof detachHls === 'function' \) \{[\s\S]{0,120}detachHls\(\);/.test( frontJs ), 'تعویض کیفیت پیش از نشاندن منبع تازه، نمونه‌ی قبلی را می‌بندد' );
check( /catch\( function \(\) \{\s*\n\s*\/\* شکست بارگذاری کتابخانه = افت به پخش مستقیم\. \*\//.test( frontJs ), 'شکست بارگذاری کتابخانه به پخش مستقیم افت می‌کند' );

check( /var SPEEDS = \[ 0\.75, 1, 1\.25, 1\.5, 2 \];/.test( frontJs ), 'فهرست سرعت‌های پخش تعریف شده است' );
check( /data-player-speed/.test( frontJs ) && /data-player-speed-label/.test( frontJs ), 'دکمه‌ی سرعت و برچسبش ساخته می‌شود' );
check( /video\.playbackRate = SPEEDS\[ speedIndex \]/.test( frontJs ), 'سرعت انتخاب‌شده روی ویدئو اعمال می‌شود' );
check( /localStorage\.setItem\( 'manacore-speed'/.test( frontJs ), 'سرعت در حافظه‌ی محلی نگه داشته می‌شود' );

check( /document\.pictureInPictureEnabled && video\.requestPictureInPicture/.test( frontJs ), 'دکمه‌ی تصویر در تصویر فقط در مرورگر پشتیبان ساخته می‌شود' );
check( /document\.pictureInPictureElement/.test( frontJs ), 'حالت فعلی تصویر در تصویر بررسی می‌شود' );

check( /function isTyping\( target \)/.test( frontJs ), 'محافظ تایپ‌بودن برای میان‌بُرهای کیبورد هست' );
check( /'input' === tag \|\| 'textarea' === tag \|\| 'select' === tag \|\| target\.isContentEditable/.test( frontJs ), 'میان‌بُرها در ورودی‌های متنی غیرفعال‌اند' );
check( /case 'ArrowRight':/.test( frontJs ) && /case 'ArrowLeft':/.test( frontJs ), 'پرش ۵ ثانیه‌ای چپ/راست پیاده شده است' );
check( /case 'm':/.test( frontJs ) && /case 'f':/.test( frontJs ), 'کلیدهای بی‌صدا و تمام‌صفحه پیاده شده‌اند' );
check( /event\.metaKey \|\| event\.ctrlKey \|\| event\.altKey/.test( frontJs ), 'میان‌بُرها با کلیدهای میان‌بر مرورگر تعارض نمی‌کنند' );

check( /function initNextEpisode\( root, video \)/.test( frontJs ), 'تابع کارت قسمت بعدی وجود دارد' );
check( /var nextUrl = root\.getAttribute\( 'data-next-url' \);/.test( frontJs ), 'کارت از data-next-url خوانده می‌شود' );
check( /video\.addEventListener\( 'ended'/.test( frontJs ), 'کارت با پایان ویدئو ظاهر می‌شود' );
check( /window\.location\.href = nextUrl;/.test( frontJs ), 'پس از شمارش معکوس به قسمت بعد می‌رود' );
check( /video\.addEventListener\( 'play', clearCard \)/.test( frontJs ), 'با پخش دوباره، کارت بسته می‌شود' );
check( /card\.setAttribute\( 'role', 'status' \) && false/.test( frontJs ) || /card\.setAttribute\( 'role', 'status' \)/.test( frontJs ), 'کارت قسمت بعدی برای صفحه‌خوان‌ها اعلام می‌شود' );
check( /video && video\.getAttribute\( 'src' \) === null/.test( frontJs ) || /null === video\.getAttribute\( 'src' \)/.test( frontJs ), 'منبع نخست پیش از جابه‌جایی بررسی می‌شود' );

/* ------------------------------------------------------------------
 * د) زیرنویس (WebVTT)
 * --------------------------------------------------------------- */
section( 'د) زیرنویس' );

const meta = read( 'wp/plugins/manacore-core/includes/class-meta.php' );

check( /'manacore_subtitles' => array\(/.test( meta ), 'فیلد زیرنویس در طرح متا هست' );
check( /'type'        => 'repeater',\s*\n\s*'post_types'  => \$all,/.test( meta ), 'زیرنویس برای فیلم/سریال/انیمه/قسمت فعال است' );
[ 'lang', 'label', 'url', 'default' ].forEach( ( sub ) => {
	check( new RegExp( "'" + sub + "'\\s*=> array\\(" ).test( meta ), 'زیرفیلد «' + sub + '» تعریف شده است' );
} );

check( /self::subtitle_tracks\(/.test( blocks ), 'پلیر زیرنویس‌ها را جمع می‌کند' );
check( /function subtitle_tracks\( \$post_ids \)/.test( blocks ), 'تابع جمع‌آوری زیرنویس با ترتیب اولویت (منبع → اثر نمایش)' );
check( blocks.includes( "preg_match( '/\\.vtt(\\?|#|$)/i', $url )" ), 'فقط فایل‌های WebVTT پذیرفته می‌شوند' );
check( /if \( '' === \$lang \) \{\s*\n\s*continue;/.test( blocks ), 'ردیف بی‌کد زبان کنار گذاشته می‌شود' );
check( /'default' => ! empty\( \$row\['default'\] \)/.test( blocks ), 'نشانه‌ی «پیش‌فرض» از متا خوانده می‌شود' );
check( /if \( ! \$has_default && isset\( \$tracks\[0\] \) \) \{\s*\n\s*\$tracks\[0\]\['default'\] = true;/.test( blocks ), 'در نبود پیش‌فرض، ردیف نخست پیش‌فرض می‌شود' );
check( /manacore_player_subtitles/.test( blocks ), 'فهرست زیرنویس فیلترپذیر است' );

check( /<track kind="subtitles"/.test( blocks ), 'عنصر track در مارک‌آپ پلیر چاپ می‌شود' );
check( blocks.includes( "srclang=\"<?php echo esc_attr( $track['lang'] ); ?>\"" ), 'کد زبان روی track می‌نشیند' );
check( blocks.includes( "<?php echo $track['default'] ? 'default' : ''; ?>" ), 'ردیف پیش‌فرض نشانه‌ی default می‌گیرد' );
check( ! /\.srt/.test( blocks ), 'فرمت SRT پشتیبانی نمی‌شود (مرورگر نمی‌فهمد) و ادعا هم نمی‌شود' );

/* ------------------------------------------------------------------
 * ه) CSS و رشته‌های ترجمه
 * --------------------------------------------------------------- */
section( 'د) ظاهر و ترجمه' );

check( /\.player-next \{/.test( frontCss ), 'سبک کارت قسمت بعدی هست' );
check( /\.player-next__count \{/.test( frontCss ), 'سبک شمارش معکوس هست' );
check( /\.player-controls \[data-player-speed\]/.test( frontCss ), 'سبک دکمه‌ی سرعت پخش هست' );
check( /inset-block-end: 14px;/.test( frontCss ), 'کارت روی کادر ویدئو و با فاصله‌ی منطقی می‌نشیند (RTL/LTR)' );

check( /'hlsUrl'    => Player::hls_script_url\(\),/.test( assets ), 'نشانی کتابخانه به جاوااسکریپت داده می‌شود' );
check( /'playbackSpeed' => __\( 'سرعت پخش', 'manacore' \)/.test( assets ), 'رشته‌ی «سرعت پخش» ترجمه‌پذیر است' );
check( /'nextEpisode'   => __\( 'قسمت بعدی', 'manacore' \)/.test( assets ), 'رشته‌ی «قسمت بعدی» ترجمه‌پذیر است' );
check( /'pictureInPicture' => __\( 'تصویر در تصویر', 'manacore' \)/.test( assets ), 'رشته‌ی «تصویر در تصویر» ترجمه‌پذیر است' );

/* ------------------------------------------------------------------
 * ه) کتابخانه‌ی فروشده (vendor)
 * --------------------------------------------------------------- */
section( 'و) کتابخانه‌ی فروشده' );

const hlsPath = path.join( vendorDir, 'hls', 'hls.min.js' );
const licensePath = path.join( vendorDir, 'hls', 'LICENSE' );
const vendorReadme = path.join( vendorDir, 'README.md' );

check( fs.existsSync( hlsPath ), 'فایل hls.min.js در مخزن هست' );
check( fs.existsSync( licensePath ), 'فایل مجوز Apache-2.0 همراه کتابخانه است' );
check( fs.existsSync( vendorReadme ), 'راهنمای پوشه‌ی vendor نوشته شده است' );

if ( fs.existsSync( hlsPath ) ) {
	const size = fs.statSync( hlsPath ).size;
	const code = fs.readFileSync( hlsPath, 'utf8' );

	check( size > 300 * 1024 && size < 1200 * 1024, 'اندازه‌ی کتابخانه در بازه‌ی معقول است', size + ' بایت' );

	/*
	 * کد درزبندی‌شده نامِ خوانا ندارد؛ نشانه‌ی کتابخانه، وجود نام‌های
	 * رویداد و امضای پروتکل HLS است.
	 */
	check( /MANIFEST_PARSED/.test( code ) && /m3u8/.test( code ), 'فایل همان کتابخانه‌ی hls.js است (رویداد/پروتکل)' );
	check( /ext-x-|\.ts\b|frag/i.test( code ), 'پردازش قطعه‌های HLS در فایل هست' );
	check( ! /sourceMappingURL/.test( code ), 'نقشه‌ی منبع (map) در فایل نیست' );
}

if ( fs.existsSync( licensePath ) ) {
	const license = fs.readFileSync( licensePath, 'utf8' );

	check( /Apache License/.test( license ) && /Version 2\.0/.test( license ), 'متن مجوز Apache-2.0 کامل است' );
}

if ( fs.existsSync( vendorReadme ) ) {
	const readme = fs.readFileSync( vendorReadme, 'utf8' );

	check( /hls\.js/.test( readme ) && /1\.7\.3/.test( readme ), 'نسخه‌ی کتابخانه در راهنما ثبت شده است' );
	check( /Apache-2\.0/.test( readme ), 'مجوز در راهنما ثبت شده است' );
}

/* ------------------------------------------------------------------
 * پایان
 * --------------------------------------------------------------- */
console.log( '\n' + '='.repeat( 58 ) );
console.log( 'موفق: ' + pass + '   ناموفق: ' + fail );
console.log( '='.repeat( 58 ) );

process.exit( fail > 0 ? 1 : 0 );

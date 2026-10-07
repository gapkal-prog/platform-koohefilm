/*
 * کاشت/برگشت داده‌ی آزمون برای سوئیت‌های مرورگری.
 *
 * چرا یک پوشه‌ی مشترک؟ سوئیت‌ها هر یک نصب آزمون وردپرس را با داده‌ی بذرگرفته
 * می‌سنجند و ترتیب اجرا تضمین‌شده نیست (هر سوئیت در پایانش داده‌ی خودش را
 * برمی‌گرداند). بدون کاشتِ درون‌سوئیتی، یک برگه مثل `/magazine/` بسته به
 * ترتیب اجرا ۴۰۴ می‌دهد و آزمون می‌شکند — همان اتفاقی که در تگ‌های w18i و w18d
 * دیده شد. پس قرارداد این است: هر سوئیتی که به برگه‌ی بذرگرفته نیاز دارد،
 * خودش `seedAll()` می‌زند و در پایان `restoreAll()`.
 *
 * استفاده:
 *   const { seedAll, restoreAll } = require( './qa-seeds.cjs' );
 *   seedAll();            // همه‌ی بذرها (idempotent)
 *   restoreAll();         // فقط بذرهایی که حالت برگشت دارند
 */
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const WP_ROOT = process.env.WP_ROOT || '/home/user/.cache/wp';
const WP_CLI  = process.env.WP_CLI || '/usr/local/bin/wp';
const QA_ENV  = path.join( __dirname, '..', 'qa-env' );

/* [ پرونده، حالت برگشت دارد؟ ] — ترتیب همین است و برعکس برمی‌گردد. */
const SEEDS = [
	[ 'seed.php', false ],
	[ 'seed-article.php', true ],
	[ 'seed-magazine.php', true ],
	[ 'seed-info.php', true ],
	[ 'seed-about.php', true ],
	[ 'seed-cast.php', true ],
	[ 'seed-live.php', true ],
	[ 'seed-account.php', false ],
];

/**
 * اجرای یک بذر یا برگشت آن.
 *
 * @param {string}  file پرونده‌ی بذر در `wp/tests/qa-env`.
 * @param {boolean} back حالت برگشت؟
 * @return {string} آخرین سطر خروجی wp-cli.
 */
function runSeed( file, back ) {
	const args = [ WP_CLI, '--path=' + WP_ROOT, 'eval-file', path.join( QA_ENV, file ) ];
	if ( back ) {
		args.push( 'restore' );
	}
	return execFileSync( 'php', args, { encoding: 'utf8' } ).trim().split( '\n' ).pop();
}

function seedAll( verbose = true ) {
	const out = [];
	for ( const [ file ] of SEEDS ) {
		let line;
		try {
			line = runSeed( file, false );
		} catch ( error ) {
			line = '✗ ' + file + ': ' + String( error.message ).slice( 0, 160 );
		}
		out.push( [ file, line ] );
		if ( verbose ) {
			console.log( '  ' + file.padEnd( 20 ) + line );
		}
	}
	return out;
}

/**
 * فقط بذر پایه (`seed.php`) — کمینه‌ی داده‌ای که برگه‌های عمومی به آن نیاز دارند
 * (امروزِ برنامه‌ی پخش، فهرست‌ها، ناوبری). برخلاف `seedAll()` هیچ بذر موقتی
 * (مثل «قاب‌های پخش زنده») را روی نصب فعال نمی‌کند.
 *
 * @param {boolean} verbose چاپ خطوط؟
 * @return {Array} فهرست [پرونده، خروجی].
 */
function seedCore( verbose = true ) {
	let line;
	try {
		line = runSeed( 'seed.php', false );
	} catch ( error ) {
		line = '✗ seed.php: ' + String( error.message ).slice( 0, 160 );
	}
	if ( verbose ) {
		console.log( '  ' + 'seed.php'.padEnd( 20 ) + line );
	}
	return [ [ 'seed.php', line ] ];
}

/**
 * برآوردن این تضمین که بذرِ «پخش زنده» روی نصب فعال **نباشد**.
 *
 * چرا لازم است: `seed-live.php` دو سریال موجود (`تاج`، `چرنوبیل`) را موقتاً به
 * «امروز» منتقل می‌کند تا قاب‌های پخش زنده پر شوند. همان متا، پنل برنامه‌ی
 * صفحه‌ی نخست را هم عوض می‌کند (سه ردیف به‌جای یک ردیفِ مرجع) — همین یک بار
 * `schedule-parity` را سرخ کرد. این تابع اگر بذری فعال باشد برمی‌گرداند و اگر
 * چیزی برای برگشت نباشد، بی‌اثر است.
 *
 * @param {boolean} verbose چاپ خط؟
 * @return {string} خروجی.
 */
function ensureLiveRestored( verbose = true ) {
	let line;
	try {
		line = runSeed( 'seed-live.php', true );
	} catch ( error ) {
		line = '✗ seed-live.php: ' + String( error.message ).slice( 0, 160 );
	}
	if ( verbose ) {
		console.log( '  ' + 'seed-live.php'.padEnd( 20 ) + line );
	}
	return line;
}

function restoreAll( verbose = true ) {
	const out = [];
	for ( const [ file, back ] of [ ...SEEDS ].reverse() ) {
		if ( ! back ) {
			continue;
		}
		let line;
		try {
			line = runSeed( file, true );
		} catch ( error ) {
			line = '✗ ' + file + ': ' + String( error.message ).slice( 0, 160 );
		}
		out.push( [ file, line ] );
		if ( verbose ) {
			console.log( '  ' + file.padEnd( 20 ) + line );
		}
	}
	return out;
}

module.exports = { runSeed, seedAll, seedCore, ensureLiveRestored, restoreAll, SEEDS, QA_ENV, WP_ROOT, WP_CLI };

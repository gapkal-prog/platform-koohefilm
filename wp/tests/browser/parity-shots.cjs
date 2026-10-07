/*
 * شاهد چشمی پاریتی: هیرو صفحه‌ی جزئیات و شبکه‌ی پلن‌ها.
 *
 * برای هر ناحیه، یک برش از محصول و یک برش از مرجع سینورا می‌گیرد تا
 * تفاوت‌ها را بتوان با چشم سنجید، نه فقط با عدد. اجرا:
 *
 *   PLAYWRIGHT_BROWSERS_PATH=/home/user/.cache/pw-browsers \
 *     node wp/tests/browser/parity-shots.cjs
 *
 * خروجی در `docs/shots/` نوشته می‌شود. پیش‌نیاز: هر دو سرور بالا باشند
 * (۸۰۹۹ محصول، ۸۰۹۸ مرجع) — همان‌طور که `qa-env/provision.sh` می‌سازد.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { seedAll, restoreAll } = require( './qa-seeds.cjs' );

const WP = 'http://localhost:8099';
const REF = 'http://localhost:8098';
const OUT = path.join( __dirname, '..', '..', '..', 'docs', 'shots' );

/* نگاشت «صفحه‌ی محصول» به «صفحه‌ی مرجع». */
const TARGETS = [
	{
		/* صفحه‌ی نخست: ظرف محتوای خانه در برابر `main.home-page` مرجع. */
		name: 'home',
		wp: WP + '/',
		ref: REF + '/index.html',
		wpSel: '.koohe-home',
		refSel: 'main.home-page',
		widths: [ 1440, 390 ],
	},
	{
		name: 'hero',
		wp: WP + '/movie/the-godfather/',
		ref: REF + '/detail.html?id=dune',
		wpSel: '.koohe-detail-hero',
		refSel: '.detail-hero',
		widths: [ 1440 ],
	},
	{
		name: 'plans',
		wp: WP + '/subscribe/',
		ref: REF + '/subscription.html',
		wpSel: '.manacore-plans',
		refSel: '.pricing-grid',
		widths: [ 1440, 980 ],
	},
	{
		/* ناحیه‌ی دانلود: هم‌نام‌کلاسِ مرجع (`.download-section`). */
		name: 'download',
		wp: WP + '/series/shogun/',
		ref: REF + '/detail.html?id=shogun',
		wpSel: '.download-section',
		refSel: '.download-section',
		wait: '.download-section .download-size',
		widths: [ 1440, 980 ],
	},
	{
		/* صفحه‌ی کشف: ستون فیلتر + شبکه‌ی کارت‌ها (بازه‌ی `browse-layout`). */
		name: 'browse',
		wp: WP + '/browse/',
		ref: REF + '/browse.html',
		wpSel: '.browse-layout',
		refSel: '.browse-layout',
		wait: '.filter-sidebar .genre-checks',
		widths: [ 1440, 390 ],
	},
	{
		/* برنامه‌ی پخش: قاب پنل + ستون کنار (`.schedule-page-layout`). */
		name: 'schedule',
		wp: WP + '/schedule/',
		ref: REF + '/schedule.html',
		wpSel: '.schedule-page-layout',
		refSel: '.schedule-page-layout',
		wait: '.week-tabs [role="tab"]',
		widths: [ 1440, 390 ],
	},
	{
		/* صفحه‌ی پخش: `page-watch` در برابر `player.html` مرجع. */
		name: 'player',
		wp: WP + '/watch/?manacore_id=8&season=1&episode=1&quality=1080p',
		ref: REF + '/player.html?id=shogun&season=1&episode=1&quality=1080p',
		wpSel: '.player-page',
		refSel: '.player-page',
		wait: '.player-episodes .episode-pills',
		/*
		 * پله‌ی ۳۹۰px عمداً اضافه شده است: نردبان موبایلِ صفحه‌ی پخش
		 * (پدینگ، قاب، یادداشت نمونه، حالت سینما) فقط در همین عرض
		 * سنجیده می‌شود و شاهد چشمی‌اش اینجاست — بند V37 بخش ۱.
		 */
		widths: [ 1440, 980, 390 ],
	},
	{
		/*
		 * برگه‌ی مقاله: سرصفحه + متن فصل‌دار + ستون کنار (فهرست،
		 * آثار مرتبط، کارت ترویجی) + بخش «داستان هنوز ادامه دارد...».
		 */
		name: 'article',
		wp: WP + '/dune-world/',
		ref: REF + '/article.html',
		wpSel: '.article-layout',
		refSel: '.article-layout',
		wait: '.article-chapter',
		widths: [ 1440, 390 ],
	},
	{
		/* سینورامگ: مقاله‌ی ویژه + شبکه‌ی مقاله‌ها. */
		name: 'magazine',
		wp: WP + '/magazine/',
		ref: REF + '/magazine.html',
		wpSel: '.magazine-feature-grid',
		refSel: '.magazine-feature-grid',
		wait: '.articles-grid .article-card',
		widths: [ 1440, 390 ],
	},
	{
		/* راهنما: فهرست پرسش‌های پرتکرار (`.faq-list.info-faq`). */
		name: 'help',
		wp: WP + '/help/',
		ref: REF + '/help.html',
		wpSel: '.faq-list.info-faq',
		refSel: '.faq-list.info-faq',
		wait: '.faq-item',
		widths: [ 1440, 390 ],
	},
	{
		/*
		 * برگه‌ی «درباره ما»: شبکه‌ی سه‌کارتی ارزش‌ها و روایت پایانی
		 * (`.about-values` در برابر همان بخش مرجع).
		 */
		name: 'about',
		wp: WP + '/about/',
		ref: REF + '/about.html',
		wpSel: '.about-values',
		refSel: '.about-values',
		wait: '.about-story',
		widths: [ 1440, 390 ],
	},
	{
		/*
		 * قوانین و حریم خصوصی: ستون بندهای حقوقی (`.legal-content`).
		 * `flattenHeader` چون این ستون از بالای نما شروع می‌شود و سربرگ
		 * چسبانِ محصول روی بند اول می‌افتد و برش را گمراه می‌کند.
		 */
		name: 'privacy',
		wp: WP + '/privacy/',
		ref: REF + '/privacy.html',
		wpSel: '.legal-content',
		refSel: '.legal-content',
		wait: '.legal-content section',
		flattenHeader: true,
		widths: [ 1440, 390 ],
	},
	{
		/*
		 * بازیگران و عوامل: شبکه‌ی چهره‌ها. مرجع، همان برگه است
		 * (`cast.html`) و نمای درونی‌اش با `?id=` روی همین main
		 * (`page-container cast-page` → `person-page`) می‌نشیند.
		 */
		name: 'cast',
		wp: WP + '/cast/',
		ref: REF + '/cast.html',
		wpSel: '.cast-page',
		refSel: '.cast-page',
		wait: '.person-card',
		widths: [ 1440, 390 ],
	},
	{
		/* برگه‌ی چهره: سرصفحه + فیلموگرافی + چهره‌های دیگر. */
		name: 'person',
		wp: WP + '/person/jared-harris/',
		ref: REF + '/cast.html?id=timothee-chalamet',
		wpSel: '.person-page',
		refSel: '.person-page',
		wait: '.person-facts',
		widths: [ 1440, 390 ],
	},
	{
		/* پخش زنده: ستون پخش‌کننده + ستون کانال‌ها (`.live-layout`). */
		name: 'live',
		wp: WP + '/live/',
		ref: REF + '/live.html',
		wpSel: '.live-layout',
		refSel: '.live-layout',
		wait: '.channel-card',
		widths: [ 1440, 980, 390 ],
	},
];

/*
 * پیش‌نیاز داده: برش‌های مقاله/سینورامگ/راهنما/قوانین/چهره/پخش زنده روی داده‌ی
 * آزمونی می‌نشینند که هر سوئیتِ دیگر خودش می‌کارد و در پایان برمی‌گرداند؛ چون
 * ترتیب اجرا تضمین‌شده نیست (مثلاً `cast-parity` در پایانش چهره‌ها را حذف می‌کند)
 * این سوئیت هم مثل بقیه خودش می‌کارد و برمی‌گرداند. عارضه‌ی جانبی: پیش‌تر برشِ
 * گرفته‌نشده فقط یک هشدار بود و می‌توانست «سبز» شمرده شود.
 */
( async () => {
	fs.mkdirSync( OUT, { recursive: true } );

	console.log( '— کاشت داده‌ی آزمون —' );
	seedAll();

	const browser = await chromium.launch( { args: [ '--no-sandbox' ] } );
	let missing = 0;
	const missingList = [];

	/*
	 * `ONLY=live` فقط همان هدف را می‌گیرد. به‌کار می‌آید وقتی یک برگه
	 * بذرِ آزمون مخصوص خودش دارد (قاب‌های امروز برگه‌ی پخش زنده) و برش باید
	 * در همان وضعیت بذرگرفته گرفته شود:
	 *
	 *   php wp tests/qa-env/seed-live.php
	 *   ONLY=live node wp/tests/browser/parity-shots.cjs
	 *   php wp tests/qa-env/seed-live.php restore
	 */
	const only   = process.env.ONLY || '';
	const chosen = only ? TARGETS.filter( ( target ) => target.name === only ) : TARGETS;

	if ( ! chosen.length ) {
		console.log( '✗ هدفی با نام «' + only + '» وجود ندارد' );
		await browser.close();
		process.exit( 1 );
	}

	for ( const target of chosen ) {
		for ( const width of target.widths ) {
			for ( const side of [ 'wp', 'ref' ] ) {
				const page = await browser.newPage( {
					viewport: { width, height: 1000 },
					locale: 'fa-IR',
					deviceScaleFactor: 1,
				} );

				await page.goto( target[ side ], { waitUntil: 'load' } );

				/*
				 * بخش‌هایی که مرجع با جاوااسکریپت می‌سازد (`detail.html`،
				 * `player.html`) تا آماده‌نشدن، برش خالی می‌شود؛ پس اگر
				 * انتخابگر «انتظار» داده شده باشد، منتظرش می‌مانیم.
				 */
				if ( target.wait ) {
					await page.waitForFunction(
						( sel ) => !! document.querySelector( sel ),
						target.wait,
						{ timeout: 15000 }
					).catch( () => {} );
				}

				await page.waitForTimeout( 600 );

				const handle = await page.$( target[ side + 'Sel' ] );

				if ( ! handle ) {
					console.log( `✗ ${ target.name } ${ side } @${ width }: «${ target[ side + 'Sel' ] }» پیدا نشد` );
					missing++;
					missingList.push( `${ target.name } ${ side } @${ width } → «${ target[ side + 'Sel' ] }» در ${ target[ side ] }` );
					await page.close();
					continue;
				}

				/*
				 * برش با `page.screenshot({ clip })` فقط داخل نما کار می‌کند
				 * و بخش‌های پایین صفحه را خارج از تصویر می‌برد؛ عکس خودِ
				 * عنصر گرفته می‌شود (خودش اسکرول می‌کند).
				 */
				await handle.scrollIntoViewIfNeeded().catch( () => {} );

				/*
				 * سربرگ چسبان فقط در برش دست‌وپاگیر است (روی محتوا می‌افتد)؛
				 * این استایل فقط داخل صفحه‌ی آزمون تزریق می‌شود و به محصول
				 * کاری ندارد.
				 */
				if ( target.flattenHeader ) {
					await page.addStyleTag( { content: '.koohe-header, .site-header { position: static !important; }' } );
				}
				const box = await handle.boundingBox();
				await handle.screenshot( {
					path: path.join( OUT, `${ side }-${ target.name }-${ width }.png` ),
				} );

				console.log(
					`✓ ${ side }-${ target.name }-${ width }.png — ` +
					`${ Math.round( box.width ) }×${ Math.round( box.height ) } @x${ Math.round( box.x ) }`
				);
				await page.close();
			}
		}
	}

	await browser.close();

	console.log( '— برگشت داده‌ی آزمون —' );
	restoreAll();

	/*
	 * برشِ گرفته‌نشده = آزمونی که اجرا نشده؛ طبق قرارداد پروژه «سبز» شمرده نمی‌شود.
	 * پس با کد ۱ بیرون می‌آییم و دلیل هر برش را فهرست می‌کنیم.
	 */
	if ( missing ) {
		console.log( `\n✗ ${ missing } برش گرفته نشد (خروج ۱):` );
		missingList.forEach( ( line ) => console.log( '   · ' + line ) );
		process.exit( 1 );
	}

	console.log( `\nهمه‌ی برش‌ها گرفته شد (${ chosen.reduce( ( n, t ) => n + t.widths.length * 2, 0 ) }).` );
} )();

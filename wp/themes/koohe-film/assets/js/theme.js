/**
 * Koohe Film — اسکریپت سمت کاربر قالب.
 *
 * وابستگی: هیچ (Vanilla JS).
 * داده‌ی محلی‌سازی‌شده: window.kooheFilm
 *   { restUrl, nonce, defaultMode, hasCore, i18n{ toDark, toLight, menu, close, top, loading, noResult } }
 *
 * نکته‌ی مهم: منطق تغییر حالت رنگ به‌صورت پیش‌فرض توسط افزونه‌ی ManaCore Core
 * (initColorMode در manacore-front) انجام می‌شود و کلید قالب از همان انتخاب‌گر
 * [data-manacore-theme-toggle] استفاده می‌کند. این فایل تنها زمانی منطق جایگزین
 * را ثبت می‌کند که افزونه فعال نباشد (kooheFilm.hasCore === false) تا رویداد
 * دوباره ثبت نشود.
 *
 * @package KooheFilm
 */

( function () {
	'use strict';

	var STORAGE_KEY = 'manacore-color-mode';
	var root        = document.documentElement;
	var config      = window.kooheFilm || {};
	var i18n        = config.i18n || {};

	/* ---------------------------------------------------------------------
	 * ابزارهای کمکی
	 * ------------------------------------------------------------------ */

	/**
	 * انتخاب چندگانه به‌صورت آرایه‌ی واقعی.
	 *
	 * @param {string}      selector انتخاب‌گر CSS.
	 * @param {ParentNode=} scope    دامنه.
	 * @return {Array} آرایه‌ی عناصر.
	 */
	function qsa( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	/**
	 * یافتن نزدیک‌ترین نیای منطبق (سازگار با هدف‌های غیرعنصری رویداد).
	 *
	 * @param {EventTarget} target   هدف رویداد.
	 * @param {string}      selector انتخاب‌گر.
	 * @return {Element|null} عنصر یا null.
	 */
	function closest( target, selector ) {
		var node = target;
		while ( node && 1 === node.nodeType ) {
			if ( node.matches && node.matches( selector ) ) {
				return node;
			}
			node = node.parentElement;
		}
		return null;
	}

	/**
	 * آیا کاربر حرکت کمتر را ترجیح می‌دهد؟
	 *
	 * @return {boolean} نتیجه.
	 */
	function prefersReducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	/**
	 * محدودکننده‌ی فراخوانی بر پایه‌ی فریم نمایش.
	 *
	 * @param {Function} fn تابع.
	 * @return {Function} تابع محدودشده.
	 */
	function rafThrottle( fn ) {
		var scheduled = false;
		return function () {
			if ( scheduled ) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame( function () {
				scheduled = false;
				fn();
			} );
		};
	}

	/**
	 * ارتفاع مؤثر سربرگ چسبان (برای جبران هنگام پرش به لنگر).
	 *
	 * @return {number} ارتفاع بر حسب پیکسل.
	 */
	function stickyOffset() {
		var offset = 0;
		var header = document.querySelector( '.koohe-header' );

		if ( header && 'sticky' === window.getComputedStyle( header ).position ) {
			offset += header.offsetHeight;
		}

		var adminBar = document.getElementById( 'wpadminbar' );

		if ( adminBar && 'fixed' === window.getComputedStyle( adminBar ).position ) {
			offset += adminBar.offsetHeight;
		}

		return offset + 12;
	}

	/**
	 * پیمایش نرم (با احترام به prefers-reduced-motion).
	 *
	 * @param {number} top موقعیت هدف.
	 */
	function scrollToTop( top ) {
		window.scrollTo( {
			top: Math.max( 0, top ),
			behavior: prefersReducedMotion() ? 'auto' : 'smooth',
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۱. حالت رنگ (تیره/روشن)
	 * ------------------------------------------------------------------ */

	/**
	 * همگام‌سازی وضعیت و برچسب دکمه‌ها با حالت جاری.
	 *
	 * @param {string} mode حالت (dark|light).
	 */
	function syncToggles( mode ) {
		var isDark = 'dark' === mode;
		var label  = isDark ? i18n.toLight : i18n.toDark;

		qsa( '[data-manacore-theme-toggle]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', isDark ? 'true' : 'false' );

			if ( label ) {
				btn.setAttribute( 'aria-label', label );
				btn.setAttribute( 'title', label );
			}
		} );
	}

	/**
	 * راه‌اندازی حالت رنگ.
	 *
	 * اسکریپت درون‌خطی سربرگ پیش از رندر مقدار data-color-mode را تنظیم می‌کند؛
	 * اینجا وضعیت دکمه‌ها همگام می‌شود و در نبود افزونه منطق کلیک اضافه می‌گردد.
	 */
	function initColorMode() {
		var current = root.getAttribute( 'data-color-mode' );

		if ( 'dark' !== current && 'light' !== current ) {
			current = 'dark' === config.defaultMode ? 'dark' : 'light';
			root.setAttribute( 'data-color-mode', current );
		}

		syncToggles( current );

		/* پیروی از تنظیم سیستم تا زمانی که کاربر انتخاب دستی نکرده باشد. */
		if ( 'auto' === config.defaultMode && window.matchMedia ) {
			var mql = window.matchMedia( '(prefers-color-scheme: dark)' );

			var onSchemeChange = function ( event ) {
				var stored = null;

				try {
					stored = window.localStorage.getItem( STORAGE_KEY );
				} catch ( e ) {
					stored = null;
				}

				if ( stored ) {
					return;
				}

				var next = event.matches ? 'dark' : 'light';
				root.setAttribute( 'data-color-mode', next );
				syncToggles( next );
			};

			if ( mql.addEventListener ) {
				mql.addEventListener( 'change', onSchemeChange );
			} else if ( mql.addListener ) {
				mql.addListener( onSchemeChange );
			}
		}

		/* افزونه‌ی هسته فعال است ⟸ منطق کلیک آنجا ثبت شده؛ دوباره ثبت نکن. */
		if ( config.hasCore ) {
			document.addEventListener( 'click', function ( event ) {
				if ( ! closest( event.target, '[data-manacore-theme-toggle]' ) ) {
					return;
				}

				/* اجازه بده هسته حالت را عوض کند، سپس برچسب‌ها را به‌روز کن. */
				window.setTimeout( function () {
					syncToggles( root.getAttribute( 'data-color-mode' ) );
				}, 0 );
			} );

			return;
		}

		/* منطق جایگزین در نبود افزونه‌ی هسته. */
		document.addEventListener( 'click', function ( event ) {
			var btn = closest( event.target, '[data-manacore-theme-toggle]' );

			if ( ! btn ) {
				return;
			}

			event.preventDefault();

			var next = 'dark' === root.getAttribute( 'data-color-mode' ) ? 'light' : 'dark';
			root.setAttribute( 'data-color-mode', next );

			try {
				window.localStorage.setItem( STORAGE_KEY, next );
			} catch ( e ) {
				// نادیده.
			}

			syncToggles( next );
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۱.۵ سربرگ چسبان: کوچک‌شدن و نوار پیشرفت خوانش
	 * ------------------------------------------------------------------ */

	/**
	 * افزودن کلاس is-shrunk به پوشش سربرگ پس از پیمایش.
	 *
	 * گزینه‌ی سفارشی‌ساز با کلاس‌های koohe-shrink-header و
	 * koohe-sticky-header روی <body> منتقل می‌شود.
	 */
	function initShrinkHeader() {
		if ( ! document.body.classList.contains( 'koohe-shrink-header' ) ||
			! document.body.classList.contains( 'koohe-sticky-header' ) ) {
			return;
		}

		var slot = document.querySelector( '.koohe-header-slot' ) ||
			document.querySelector( '.koohe-header' );

		if ( ! slot ) {
			return;
		}

		var update = rafThrottle( function () {
			var y = window.pageYOffset || root.scrollTop || 0;

			slot.classList.toggle( 'is-shrunk', y > 80 );
		} );

		window.addEventListener( 'scroll', update, { passive: true } );
		update();
	}

	/**
	 * نوار پیشرفت خوانش.
	 */
	function initReadingProgress() {
		var wrap = document.querySelector( '[data-koohe-progress]' );

		if ( ! wrap ) {
			return;
		}

		var bar = wrap.querySelector( '.koohe-progress-bar' );

		if ( ! bar ) {
			return;
		}

		var update = rafThrottle( function () {
			var doc      = document.documentElement;
			var scrolled = window.pageYOffset || doc.scrollTop || 0;
			var height   = ( doc.scrollHeight || 0 ) - window.innerHeight;
			var percent  = height > 0 ? Math.min( 100, Math.max( 0, ( scrolled / height ) * 100 ) ) : 0;

			bar.style.width = percent.toFixed( 2 ) + '%';
		} );

		window.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update, { passive: true } );
		update();
	}

	/* ---------------------------------------------------------------------
	 * ۲. دکمه‌ی بازگشت به بالا
	 * ------------------------------------------------------------------ */

	/**
	 * نمایش/پنهان‌سازی و عملکرد دکمه‌ی بازگشت به بالا.
	 */
	function initBackToTop() {
		var button = document.querySelector( '[data-koohe-to-top]' );

		if ( ! button ) {
			return;
		}

		if ( i18n.top ) {
			button.setAttribute( 'aria-label', i18n.top );
			button.setAttribute( 'title', i18n.top );
		}

		var update = rafThrottle( function () {
			var y = window.pageYOffset || root.scrollTop || 0;
			button.classList.toggle( 'is-visible', y > 400 );
		} );

		window.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', update, { passive: true } );
		update();

		button.addEventListener( 'click', function () {
			scrollToTop( 0 );

			/* بازگرداندن فوکوس به ابتدای محتوا برای دسترس‌پذیری. */
			var main = document.getElementById( 'koohe-main' );

			if ( main ) {
				main.setAttribute( 'tabindex', '-1' );
				main.focus( { preventScroll: true } );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۳. پرش نرم به لنگرها با احتساب سربرگ چسبان
	 * ------------------------------------------------------------------ */

	/**
	 * مدیریت کلیک روی لینک‌های داخل‌صفحه‌ای.
	 */
	function initAnchorScroll() {
		document.addEventListener( 'click', function ( event ) {
			if ( event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || 0 !== event.button ) {
				return;
			}

			var link = closest( event.target, 'a[href^="#"]' );

			if ( ! link ) {
				return;
			}

			var hash = link.getAttribute( 'href' );

			if ( ! hash || '#' === hash || link.hasAttribute( 'data-koohe-no-scroll' ) ) {
				return;
			}

			/* کنترل‌های بلوک‌های هسته (تب فصل‌ها، پخش‌کننده و…) دست‌نخورده بمانند. */
			if ( link.hasAttribute( 'data-manacore-season' ) || 'tab' === link.getAttribute( 'role' ) ) {
				return;
			}

			var target;

			try {
				target = document.querySelector( hash );
			} catch ( e ) {
				return;
			}

			if ( ! target ) {
				return;
			}

			event.preventDefault();

			scrollToTop( target.getBoundingClientRect().top + ( window.pageYOffset || 0 ) - stickyOffset() );

			target.setAttribute( 'tabindex', '-1' );

			if ( target.focus ) {
				target.focus( { preventScroll: true } );
			}

			if ( window.history && window.history.pushState ) {
				window.history.pushState( null, '', hash );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۴. بهبود ناوبری
	 * ------------------------------------------------------------------ */

	/**
	 * بستن منوی بازشوی ناوبری هسته با کلید Escape و بهبود برچسب‌ها.
	 */
	function initNavigation() {
		var nav = document.querySelector( '.koohe-nav' );

		if ( ! nav ) {
			return;
		}

		if ( i18n.menu ) {
			qsa( '.wp-block-navigation__responsive-container-open', nav ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-label', i18n.menu );
			} );
		}

		if ( i18n.close ) {
			qsa( '.wp-block-navigation__responsive-container-close', nav ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-label', i18n.close );
			} );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key && 'Esc' !== event.key ) {
				return;
			}

			var open = nav.querySelector( '.wp-block-navigation__responsive-container.is-menu-open' );

			if ( ! open ) {
				return;
			}

			var closeBtn = open.querySelector( '.wp-block-navigation__responsive-container-close' );

			if ( closeBtn ) {
				closeBtn.click();
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۵. ریل‌های افقی (is-style-koohe-rail و شبکه‌های اسکرولی هسته)
	 * ------------------------------------------------------------------ */

	/**
	 * پیمایش افقی با کشیدن ماوس، برای ریل‌های اسکرول‌شونده.
	 */
	function initRails() {
		var rails = qsa( '.is-style-koohe-rail .wp-block-post-template, .manacore-grid-cards.is-scroll' );

		rails.forEach( function ( rail ) {
			if ( rail.getAttribute( 'data-koohe-rail' ) ) {
				return;
			}

			rail.setAttribute( 'data-koohe-rail', '1' );

			var isDown  = false;
			var startX  = 0;
			var startSL = 0;

			rail.addEventListener( 'pointerdown', function ( event ) {
				if ( 'mouse' !== event.pointerType ) {
					return;
				}

				isDown  = true;
				startX  = event.clientX;
				startSL = rail.scrollLeft;
			} );

			rail.addEventListener( 'pointermove', function ( event ) {
				if ( ! isDown ) {
					return;
				}

				var delta = event.clientX - startX;

				if ( Math.abs( delta ) > 4 ) {
					rail.scrollLeft = startSL - delta;
				}
			} );

			[ 'pointerup', 'pointerleave', 'pointercancel' ].forEach( function ( type ) {
				rail.addEventListener( type, function () {
					isDown = false;
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * ۶. تصاویر: نشانه‌گذاری تصاویر خراب
	 * ------------------------------------------------------------------ */

	/**
	 * اعلان شناور (toast) — برای نمایش پیام‌های کوتاه به کاربر.
	 * استفاده: window.kooheToast( 'پیام', { type: 'error' } )
	 *
	 * @param {string} message متن پیام.
	 * @param {Object=} opts    گزینه‌ها: { type: 'error'|'', duration: ms }
	 */
	function showToast( message, opts ) {
		opts = opts || {};
		var duration = opts.duration || 3500;
		var el = document.createElement( 'div' );
		el.className = 'koohe-toast' + ( 'error' === opts.type ? ' is-error' : '' );
		el.setAttribute( 'role', 'status' );
		el.textContent = message;
		document.body.appendChild( el );
		setTimeout( function () {
			el.style.opacity = '0';
			setTimeout( function () {
				if ( el.parentNode ) {
					el.parentNode.removeChild( el );
				}
			}, 250 );
		}, duration );
	}

	/* در دسترس قرار دادن از بیرون. */
	window.kooheToast = showToast;

	/**
	 * افزودن کلاس به تصاویر بارگذاری‌نشده تا CSS جایگزین را نمایش دهد.
	 */
	function initImageFallback() {
		document.addEventListener(
			'error',
			function ( event ) {
				var img = event.target;

				if ( ! img || 'IMG' !== img.tagName || img.getAttribute( 'data-koohe-fallback' ) ) {
					return;
				}

				if ( ! closest( img, '.koohe-poster-card, .manacore-card, .koohe-title-header' ) ) {
					return;
				}

				img.setAttribute( 'data-koohe-fallback', '1' );
				img.classList.add( 'koohe-img-fallback' );
			},
			true
		);
	}

	/* ---------------------------------------------------------------------
	 * تب‌بار صفحه‌ی جزئیات (پیمایش به بخش + تشخیص بخش فعال)
	 * ------------------------------------------------------------------ */

	/**
	 * فعال‌سازی تب‌بار چسبان در قالب‌های جزئیات.
	 *
	 * کلیک روی هر تب به‌آرامی به بخش مربوطه می‌رود و هنگام اسکرول، تبِ بخشِ
	 * دیده‌شده فعال می‌شود (مانند صفحه‌ی جزئیات سینورا). چون در معماری بلوکی،
	 * بخش‌ها پنهان نمی‌شوند و پشت‌سرهم می‌آیند، رفتار «پرش به بخش» انتخاب شده است.
	 */
	function initDetailTabs() {
		var bar = document.querySelector( '[data-koohe-tab-bar]' );
		if ( ! bar ) {
			return;
		}

		var buttons = bar.querySelectorAll( '.koohe-tabs > button[data-koohe-tab]' );
		if ( ! buttons.length ) {
			return;
		}

		var targets = {
			overview: document.querySelector( '.koohe-single-body' ),
			episodes: document.querySelector( '.manacore-episodes, .manacore-episodes-block' ),
			download: document.querySelector( '.download-section, .manacore-links-block' ),
			cast: document.querySelector( '.manacore-cast, .manacore-cast-block' ),
			comments: document.querySelector( '.comments-section' )
		};

		/**
		 * فاصله‌ی پایین هدر و تب‌بار برای اسکرول دقیق.
		 */
		function stickyOffset() {
			var header = document.querySelector( '.koohe-header' ) || document.querySelector( 'header' );
			var h = header ? header.offsetHeight : 0;
			return h + bar.offsetHeight + 16;
		}

		/**
		 * تعیین تب فعال.
		 *
		 * توجه به دسترس‌پذیری: این نوار «تب» به معنای ARIA نیست — هیچ پنلی
		 * پنهان/نمایان نمی‌شود، بلکه فهرستی از لنگرهای درون‌صفحه‌ای است که
		 * با اسکرول فعال می‌شود. پیش‌تر هر دکمه `role="tab"` می‌گرفت بدون
		 * آنکه والدش `role="tablist"` باشد؛ axe-core این را نقض بحرانی
		 * `aria-required-parent` می‌دید (چهار نمونه در هر برگه‌ی تک‌اثر).
		 * اکنون به‌جای نقش نادرستِ تب، همان معنای واقعی اعلام می‌شود:
		 * `aria-current="true"` روی مورد فعال.
		 */
		function setActive( name ) {
			for ( var i = 0; i < buttons.length; i++ ) {
				var isActive = buttons[ i ].getAttribute( 'data-koohe-tab' ) === name;
				buttons[ i ].classList.toggle( 'is-active', isActive );
				if ( isActive ) {
					buttons[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					buttons[ i ].removeAttribute( 'aria-current' );
				}
			}
		}

		/**
		 * هم‌گام‌سازی نشانی صفحه با بخش فعال (پیوندپذیری، مانند طرح مرجع).
		 * Closest را با replaceState می‌نویسیم تا پرش صفحه رخ ندهد.
		 */
		function syncHash( name ) {
			if ( ! name || ! window.history || ! window.history.replaceState ) {
				return;
			}
			var base = window.location.href.split( '#' )[ 0 ];
			window.history.replaceState( null, '', base + '#' + name );
		}

		var isClickScrolling = false;

		function scrollTo( el ) {
			var top = el.getBoundingClientRect().top + window.pageYOffset - stickyOffset();
			window.scrollTo( { top: top, behavior: 'smooth' } );
		}

		for ( var i = 0; i < buttons.length; i++ ) {
			( function ( btn ) {
				var name = btn.getAttribute( 'data-koohe-tab' );
				/*
				 * هر دکمه می‌تواند مقصد خودش را با `data-koohe-target`
				 * صریح تعیین کند. لازم است چون در مرجع، «فصل‌ها و
				 * قسمت‌ها» و «پخش و دانلود» یک بخش یکسان‌اند و
				 * نگاشت پیش‌فرضِ نام تب کافی نیست.
				 */
				var explicit = btn.getAttribute( 'data-koohe-target' );
				var el = ( explicit ? document.querySelector( explicit ) : null ) || targets[ name ];

				if ( ! el ) {
					btn.hidden = true;
					return;
				}

				if ( btn.classList.contains( 'is-active' ) ) {
					btn.setAttribute( 'aria-current', 'true' );
				}

				btn.addEventListener( 'click', function () {
					setActive( name );
					syncHash( name );
					isClickScrolling = true;
					scrollTo( el );
					setTimeout( function () {
						isClickScrolling = false;
					}, 800 );
				} );
			} )( buttons[ i ] );
		}

		/* تشخیص بخش فعال هنگام اسکرول. */
		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver( function ( entries ) {
				if ( isClickScrolling ) {
					return;
				}

				for ( var j = 0; j < entries.length; j++ ) {
					if ( entries[ j ].isIntersecting ) {
						var entryEl = entries[ j ].target;
						for ( var key in targets ) {
							if ( targets[ key ] === entryEl ) {
								setActive( key );
								break;
							}
						}
					}
				}
			}, { rootMargin: '-' + stickyOffset() + 'px 0px -55% 0px', threshold: 0 } );

			for ( var key in targets ) {
				if ( targets[ key ] ) {
					observer.observe( targets[ key ] );
				}
			}
		}

		/*
		 * ورود با لنگر (مثلاً «/movie/x/#download» که از دکمه‌ی «پخش و
		 * دانلود» یا لینک بیرونی می‌آید): همان بخش فعال و به آن اسکرول شود.
		 */
		var initial = ( window.location.hash || '' ).replace( '#', '' );
		if ( initial && targets[ initial ] ) {
			setActive( initial );
			isClickScrolling = true;
			scrollTo( targets[ initial ] );
			setTimeout( function () {
				isClickScrolling = false;
			}, 900 );
		}
	}

	/* ---------------------------------------------------------------------
	 * بارگذاری بیشتر (Progressive Enhancement روی صفحه‌بندی کلاسیک)
	 * ------------------------------------------------------------------ */

	/**
	 * دکمه‌ی «بازدید بیشتر» در آرشیوها.
	 *
	 * دو حالت پشتیبانی می‌شود:
	 *  ۱) ظرف اختصاصی .koohe-load-more با یک لینک به صفحه‌ی بعد؛
	 *  ۲) صفحه‌بندی هسته با کلاس koohe-pagination--load-more — در این حالت
	 *     فقط پیوندِ «بعدی» به‌عنوان دکمه عمل می‌کند و با افزودن کلاس
	 *     is-load-more-mode، شماره‌ها و «قبلی» پنهان می‌شوند (فقط وقتی JS فعال
	 *     است؛ بدون JS صفحه‌بندی کامل و کلاسیک دیده می‌شود).
	 *
	 * محتوای صفحه‌ی بعد واکشی و کارت‌ها به شبکه‌ی فعلی افزوده می‌شوند. اگر
	 * صفحه‌ی بعدی نداشته باشد، ظرف دکمه حذف می‌شود.
	 */
	function initLoadMore() {
		var trigger = document.querySelector( '.koohe-load-more' ) ||
			document.querySelector( '.koohe-pagination--load-more' );
		if ( ! trigger ) {
			return;
		}

		var isCorePagination = ! trigger.classList.contains( 'koohe-load-more' );
		var link = isCorePagination
			? trigger.querySelector( '.wp-block-query-pagination-next' )
			: trigger.querySelector( 'a[href]' );

		if ( ! link || ! link.getAttribute( 'href' ) ) {
			if ( isCorePagination ) {
				/* صفحه‌ی بعدی وجود ندارد؛ صفحه‌بندی کلاسیک دست‌نخورده می‌ماند. */
				return;
			}
			trigger.hidden = true;
			return;
		}

		var grid = document.querySelector(
			'.koohe-poster-grid, .koohe-query .wp-block-post-template'
		);
		if ( ! grid ) {
			return;
		}

		if ( isCorePagination ) {
			trigger.classList.add( 'is-load-more-mode' );
		}

		var label = link.textContent;
		var busy = false;

		link.addEventListener( 'click', function ( event ) {
			var url = link.getAttribute( 'href' );

			/* فقط نشانی‌های داخلی را به‌صورت AJAX می‌گیریم. */
			if ( busy || ! url || ( ! url.startsWith( '/' ) && 0 !== url.indexOf( window.location.origin ) ) ) {
				return;
			}

			event.preventDefault();
			busy = true;
			link.classList.add( 'is-busy' );
			link.setAttribute( 'aria-busy', 'true' );
			link.textContent = label;

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}
					return response.text();
				} )
				.then( function ( html ) {
					var doc = new DOMParser().parseFromString( html, 'text/html' );
					var nextGrid = doc.querySelector(
						'.koohe-poster-grid, .koohe-query .wp-block-post-template'
					);
					var nextTrigger = isCorePagination
						? doc.querySelector( '.koohe-pagination--load-more .wp-block-query-pagination-next' )
						: doc.querySelector( '.koohe-load-more a[href]' );
					var added = 0;

					if ( nextGrid ) {
						Array.prototype.forEach.call( nextGrid.children, function ( card ) {
							if ( 'SCRIPT' === card.tagName || 'STYLE' === card.tagName ) {
								return;
							}
							grid.appendChild( document.importNode( card, true ) );
							added++;
						} );
					}

					if ( nextTrigger && added ) {
						link.setAttribute( 'href', nextTrigger.getAttribute( 'href' ) );
					} else {
						/* صفحه‌ی بعدی نیست؛ دکمه و ظرفش حذف می‌شوند. */
						if ( trigger.parentNode ) {
							trigger.parentNode.removeChild( trigger );
						}
					}

					/* کارت‌های تازه ممکن است وابستگی JS داشته باشند (واچ‌لیست و…). */
					document.dispatchEvent( new CustomEvent( 'manacore:content-updated' ) );
				} )
				.catch( function () {
					/* خطا: به صفحه‌ی بعدی می‌رویم (رفتار بدون JS). */
					window.location.href = url;
				} )
				.finally( function () {
					busy = false;
					link.classList.remove( 'is-busy' );
					link.removeAttribute( 'aria-busy' );
				} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * اورلی جستجو (⌘K / Ctrl+K)
	 * ------------------------------------------------------------------ */

	/**
	 * پنل جستجوی سریع — هم‌رفتار با مرجع (`cinora/assets/js/main.js`).
	 *
	 * مسیر داده: `kooheFilm.searchUrl` که به مسیر REST خودِ افزونه
	 * (`manacore/v1/search`) اشاره می‌کند. پیش‌تر این‌جا نشانی دستی ساخته
	 * می‌شد (`config.restUrl` + `wp/v2/search`) و چون `restUrl` فضای‌نام
	 * افزونه بود، درخواست به `…/wp-json/manacore/v1/wp/v2/search` می‌رفت،
	 * ۴۰۴ می‌گرفت و خطا هم بلعیده می‌شد؛ نتیجه: هر عبارت — فارسی یا
	 * انگلیسی — پیام «این داستان را هنوز پیدا نکردیم» را نشان می‌داد.
	 *
	 * رفتار: با ورودی خالی نتایج پیشنهادی («این روزها بیشتر جستجو می‌شوند»)،
	 * از دو نویسه به بعد جستجوی زنده با تأخیر ۲۵۰ms، و تفکیک روشن میان
	 * «نتیجه‌ای نیست» و «جستجو انجام نشد» (خطای شبکه با پیام نبود نتیجه
	 * اشتباه گرفته نشود).
	 */
	function initSearchOverlay() {
		var overlay = document.querySelector( '[data-koohe-search-overlay]' );
		if ( ! overlay ) {
			return;
		}

		var input     = overlay.querySelector( '[data-koohe-search-input]' );
		var form      = overlay.querySelector( '.koohe-search-overlay__form' );
		var results   = overlay.querySelector( '[data-koohe-search-results]' );
		var empty     = overlay.querySelector( '[data-koohe-search-empty]' );
		var label     = overlay.querySelector( '[data-koohe-search-label]' );
		var lastQuery = '';
		var timer     = null;
		var controller = null;
		var suggestions = null;

		/*
		 * دکمه‌های بازکننده‌ی پوسته (مثل دکمه‌ی جستجوی سربرگ) وضعیت
		 * `aria-expanded` خود را هم به‌روز می‌کنند؛ بدون آن صفحه‌خوان
		 * نمی‌فهمد پوسته باز شده است.
		 */
		var triggers = qsa( '[data-koohe-search-open]' );

		var historyBox   = overlay.querySelector( '[data-koohe-search-history]' );
		var historyList  = overlay.querySelector( '[data-koohe-search-history-list]' );
		var genresBox    = overlay.querySelector( '[data-koohe-search-genres-box]' );
		var genresList   = overlay.querySelector( '[data-koohe-search-genres]' );
		var genres       = Array.isArray( config.genres ) ? config.genres : [];

		function setExpanded( state ) {
			triggers.forEach( function ( btn ) {
				btn.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			} );
		}

		function open() {
			overlay.hidden = false;
			document.body.classList.add( 'koohe-search-open' );
			setExpanded( true );

			if ( input ) {
				input.focus();
			}

			showSuggestions();
		}

		function close() {
			overlay.hidden = true;
			document.body.classList.remove( 'koohe-search-open' );
			setExpanded( false );
		}

		/** نشانی برگه‌ی جستجو برای ارسال بدون جاوااسکریپت/شکست جستجوی زنده. */
		if ( form && config.searchPage ) {
			form.setAttribute( 'action', config.searchPage );
		}

		/** برچسب بالای فهرست: شمار نتایج یا «پیشنهادهای روز». */
		function setLabel( text ) {
			if ( label ) {
				label.textContent = text;
				label.hidden = false;
			}
		}

		/** پنهان‌کردن هر دو حالت نتیجه/خالی. */
		function reset() {
			if ( results ) {
				results.hidden = true;
				results.innerHTML = '';
			}
			if ( empty ) {
				empty.hidden = true;
			}
		}

		/**
		 * رندر یک فهرست نتیجه؛ همان ساختار مرجع: پوستر، عنوان، نام اصلی و
		 * «سال · نوع» به‌همراه شِورون.
		 *
		 * @param {Array} items آیتم‌های پاسخ REST.
		 */
		function renderItems( items ) {
			if ( ! results ) {
				return;
			}

			results.innerHTML = '';

			items.forEach( function ( item ) {
				var a = document.createElement( 'a' );
				a.href = item.url || '#';
				a.addEventListener( 'click', function () {
					rememberQuery( input ? input.value : '' );
				} );

				var img = document.createElement( 'img' );
				img.src = item.poster || '';
				img.alt = '';
				img.loading = 'lazy';
				a.appendChild( img );

				var body  = document.createElement( 'span' );
				var title = document.createElement( 'strong' );
				title.textContent = item.title || '';
				body.appendChild( title );

				if ( item.original ) {
					var original = document.createElement( 'small' );
					original.textContent = item.original;
					body.appendChild( original );
				}

				var meta = [ item.year ? toFa( item.year ) : '', item.typeLabel || '' ].filter( Boolean ).join( ' · ' );
				if ( meta ) {
					var em = document.createElement( 'em' );
					em.textContent = meta;
					body.appendChild( em );
				}

				a.appendChild( body );

				var chevron = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
				chevron.setAttribute( 'width', '17' );
				chevron.setAttribute( 'height', '17' );
				chevron.setAttribute( 'viewBox', '0 0 24 24' );
				chevron.setAttribute( 'fill', 'none' );
				chevron.setAttribute( 'stroke', 'currentColor' );
				chevron.setAttribute( 'stroke-width', '2' );
				chevron.setAttribute( 'stroke-linecap', 'round' );
				chevron.setAttribute( 'stroke-linejoin', 'round' );
				chevron.setAttribute( 'aria-hidden', 'true' );
				chevron.innerHTML = '<path d="m15 18-6-6 6-6"/>';
				a.appendChild( chevron );

				results.appendChild( a );
			} );

			results.hidden = ! items.length;
		}

		/**
		 * پیام وضعیت داخل ظرف نتایج (در حال جستجو / خطا).
		 *
		 * @param {string} text متن پیام.
		 */
		function setStatus( text ) {
			if ( ! results ) {
				return;
			}

			results.innerHTML = '';
			var p = document.createElement( 'p' );
			p.className = 'koohe-search-overlay__status';
			p.textContent = text;
			results.appendChild( p );
			results.hidden = false;

			if ( empty ) {
				empty.hidden = true;
			}
		}

		/**
		 * واکشی یک مسیر REST افزونه.
		 *
		 * @param {string} url نشانی کامل.
		 * @return {Promise<Object>} پاسخ JSON.
		 */
		function request( url ) {
			if ( controller ) {
				controller.abort();
			}
			controller = new AbortController();

			return fetch( url, {
				signal: controller.signal,
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} );
		}

		/* ---------------------------------------------------------------
		 * تاریخچه‌ی جستجو: فقط روی دستگاه کاربر (localStorage)، حداکثر
		 * ۶ مورد، جدیدترین اول. ثبت هنگام ارسال فرم یا باز کردن یک نتیجه.
		 * ------------------------------------------------------------- */

		var HISTORY_KEY = 'koohe-search-history';
		var HISTORY_MAX = 6;

		function readHistory() {
			try {
				var list = JSON.parse( window.localStorage.getItem( HISTORY_KEY ) || '[]' );

				return Array.isArray( list ) ? list.filter( function ( term ) {
					return 'string' === typeof term && '' !== term;
				} ).slice( 0, HISTORY_MAX ) : [];
			} catch ( error ) {
				return [];
			}
		}

		function rememberQuery( query ) {
			var term = String( query || '' ).trim();

			if ( term.length < 2 ) {
				return;
			}

			var list = readHistory().filter( function ( item ) {
				return item !== term;
			} );

			list.unshift( term );

			try {
				window.localStorage.setItem( HISTORY_KEY, JSON.stringify( list.slice( 0, HISTORY_MAX ) ) );
			} catch ( error ) {
				/* حافظه‌ی مرورگر پر یا بسته است؛ تاریخچه اختیاری است. */
			}
		}

		function forgetHistory() {
			try {
				window.localStorage.removeItem( HISTORY_KEY );
			} catch ( error ) {
				/* همان‌طور؛ نبودِ دسترسی به حافظه خطای کاربری نیست. */
			}
		}

		/**
		 * دکمه‌ی پیوند یا عنصر دکمه‌وار برای یک چیپ.
		 *
		 * @param {string} text متن چیپ.
		 * @param {string} href نشانی (اختیاری؛ بدون آن دکمه ساخته می‌شود).
		 * @return {HTMLElement} عنصر.
		 */
		function makeChip( text, href ) {
			var node = document.createElement( href ? 'a' : 'button' );

			node.className = 'koohe-search-overlay__chip';
			if ( href ) {
				node.href = href;
			} else {
				node.type = 'button';
			}
			node.textContent = text;

			return node;
		}

		/** بخش‌های «ژانرها» و «تاریخچه» فقط وقتی کادر خالی است دیده می‌شوند. */
		function setIdle( visible ) {
			if ( historyBox ) {
				historyBox.hidden = ! visible || ! readHistory().length;
			}
			if ( genresBox ) {
				genresBox.hidden = ! visible || ! genres.length;
			}
		}

		function renderHistory() {
			if ( ! historyBox || ! historyList ) {
				return;
			}

			var list = readHistory();

			historyList.innerHTML = '';
			list.forEach( function ( term ) {
				var chip = makeChip( term, '' );

				chip.addEventListener( 'click', function () {
					searchFor( term );
				} );

				historyList.appendChild( chip );
			} );
		}

		/**
		 * اجرای جستجو از روی یک عبارت ذخیره‌شده. بدون افزونه، به برگه‌ی
		 * جستجوی وردپرس می‌رود.
		 *
		 * @param {string} term عبارت.
		 */
		function searchFor( term ) {
			if ( ! config.searchUrl ) {
				window.location.href = ( config.searchPage || '/' ) + '?s=' + encodeURIComponent( term );
				return;
			}

			input.value = term;
			setIdle( false );
			window.clearTimeout( timer );
			runSearch( term );
		}

		function renderGenres() {
			if ( ! genresList || ! genres.length ) {
				return;
			}

			genres.forEach( function ( genre ) {
				genresList.appendChild( makeChip( genre.name, genre.url ) );
			} );
		}

		/** برچسب‌های بخش‌ها از ترجمه‌های قالب (`kooheFilm.i18n`). */
		function labelSections() {
			var i18n = config.i18n || {};
			var set  = function ( selector, text ) {
				var node = overlay.querySelector( selector );

				if ( node && text ) {
					node.textContent = text;
				}
			};

			set( '[data-koohe-search-history-title]', i18n.history );
			set( '[data-koohe-search-history-clear]', i18n.clear );
			set( '[data-koohe-search-genres-title]', i18n.genres );
		}

		if ( historyBox ) {
			qsa( '[data-koohe-search-history-clear]', overlay ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					forgetHistory();
					renderHistory();
					setIdle( true );
				} );
			} );
		}

		labelSections();
		renderGenres();

		/** پیشنهادهای روز (وقتی کادر جستجو خالی است). */
		function showSuggestions() {
			renderHistory();
			setIdle( true );

			if ( ! config.titlesUrl ) {
				return;
			}

			if ( suggestions ) {
				setLabel( config.i18n.trending || '' );
				renderItems( suggestions );
				return;
			}

			request( config.titlesUrl + '?per_page=6&source=trending' )
				.then( function ( payload ) {
					suggestions = ( payload && payload.items ) || [];
					if ( ! input || '' !== input.value.trim() ) {
						return;
					}
					setLabel( config.i18n.trending || '' );
					renderItems( suggestions );
				} )
				.catch( function () {
					/* پیشنهادها تزئینی‌اند؛ خطایشان چیزی را نمی‌شکند. */
				} );
		}

		/**
		 * جستجوی زنده و نمایش نتیجه.
		 *
		 * @param {string} query عبارت کاربر.
		 */
		function runSearch( query ) {
			lastQuery = query;

			if ( ! config.searchUrl ) {
				/* بدون افزونه، جستجوی زنده نداریم؛ فرم به برگه‌ی جستجو می‌رود. */
				return;
			}

			setStatus( config.i18n.searching || '' );

			request(
				config.searchUrl +
					'?q=' + encodeURIComponent( query ) +
					'&per_page=6'
			)
				.then( function ( payload ) {
					/* اگر در فاصله‌ی واکشی، پرسمان عوض شده باشد، نتیجه‌ی کهنه نکوب. */
					if ( query !== lastQuery ) {
						return;
					}

					var items = ( payload && payload.items ) || [];

					setLabel(
						( config.i18n.results || '%s' ).replace( '%s', toFa( items.length ) )
					);
					renderItems( items );

					if ( empty ) {
						empty.hidden = !! items.length;
					}
				} )
				.catch( function ( error ) {
					if ( error && 'AbortError' === error.name ) {
						return;
					}

					/*
					 * خطای شبکه/سرور با «نتیجه‌ای نیست» یکی نیست؛ پیام
					 * درست نشان داده می‌شود تا کاربر گمان نکند محتوا
					 * وجود ندارد.
					 */
					setLabel( '' );
					setStatus( config.i18n.error || '' );
				} );
		}

		if ( form ) {
			form.addEventListener( 'submit', function () {
				rememberQuery( input ? input.value : '' );
			} );
		}

		if ( input ) {
			input.addEventListener( 'input', function () {
				var query = input.value.trim();

				window.clearTimeout( timer );

				if ( '' !== query ) {
					setIdle( false );
				}

				if ( query.length < 2 ) {
					reset();

					if ( '' === query ) {
						showSuggestions();
					} else {
						setLabel( config.i18n.trending || '' );
					}

					return;
				}

				timer = window.setTimeout( function () {
					runSearch( query );
				}, 250 );
			} );

			/* پاک‌کردن کادر با Escape: بازگشت به پیشنهادها (نه بستن پوسته). */
			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && input.value ) {
					event.stopPropagation();
					input.value = '';
					reset();
					showSuggestions();
				}
			} );
		}

		qsa( '[data-koohe-search-open]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', open );
		} );

		qsa( '[data-koohe-search-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', close );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			/* ⌘K یا Ctrl+K: باز/بسته کردن. */
			if ( ( event.metaKey || event.ctrlKey ) && 'k' === ( event.key || '' ).toLowerCase() ) {
				event.preventDefault();
				if ( overlay.hidden ) {
					open();
				} else {
					close();
				}
				return;
			}

			if ( 'Escape' === event.key && ! overlay.hidden ) {
				close();
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * کشوی منوی موبایل (≤۹۸۰px)
	 * ------------------------------------------------------------------ */

	/**
	 * کشوی کنار — همان رفتار مرجع.
	 *
	 * باز شدن با `[data-mobile-open]` (همبرگری سربرگ)، بستن با دکمه‌ی
	 * `[data-mobile-close]`، با کلیک روی پس‌زمینه (نه داخل کشو) و با
	 * `[data-mobile-search]` (ردیف جستجو: اول کشو بسته می‌شود، بعد پوسته‌ی
	 * جستجو باز می‌شود). قفل پیمایش پس‌زمینه عیناً مثل مرجع با
	 * `body.drawer-open` و `overflow: hidden` انجام می‌شود.
	 *
	 * دو افزوده‌ی دسترس‌پذیری که مرجع نداشت: بستن با Escape و بازگشت
	 * فوکوس به دکمه‌ی همبرگری.
	 */
	function initMobileDrawer() {
		var toggle  = document.querySelector( '[data-mobile-open]' );
		var drawer  = document.querySelector( '[data-mobile-drawer]' );
		var closeButton = document.querySelector( '[data-mobile-close]' );

		if ( ! toggle || ! drawer ) {
			return;
		}

		/**
		 * بستن کشو.
		 *
		 * @param {boolean} restoreFocus فوکوس به دکمه‌ی همبرگری برگردد؟
		 */
		function close( restoreFocus ) {
			if ( drawer.hidden ) {
				return;
			}

			var hadFocus = drawer.contains( document.activeElement );

			drawer.hidden = true;
			document.body.classList.remove( 'drawer-open' );
			document.body.style.overflow = '';
			toggle.setAttribute( 'aria-expanded', 'false' );

			if ( restoreFocus && hadFocus ) {
				toggle.focus();
			}
		}

		function open() {
			drawer.hidden = false;
			document.body.classList.add( 'drawer-open' );
			document.body.style.overflow = 'hidden';
			toggle.setAttribute( 'aria-expanded', 'true' );

			if ( closeButton ) {
				closeButton.focus();
			}
		}

		toggle.addEventListener( 'click', open );

		if ( closeButton ) {
			closeButton.addEventListener( 'click', function () {
				close( true );
			} );
		}

		/* کلیک روی پس‌زمینه — نه کلیک داخل خود کشو. */
		drawer.addEventListener( 'mousedown', function ( event ) {
			if ( event.target === drawer ) {
				close( true );
			}
		} );

		qsa( '[data-mobile-search]', drawer ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				close( false );
			} );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				close( true );
			}
		} );

		/*
		 * اگر پنجره به عرض دسکتاپ رسید، کشو نباید باز و قفل بماند؛
		 * در آن عرض دکمه‌ی همبرگری هم پنهان است و راه بستنی نمی‌ماند.
		 */
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth > 980 ) {
				close( false );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * فهرست پرسش‌های برگه‌ی «راهنما»
	 * ------------------------------------------------------------------ */

	/**
	 * بازکردن نخستین پرسشِ فهرست.
	 *
	 * مرجع هم همین کار را در `help.js` می‌کند (`i===0?'open':''`)؛ این‌جا
	 * چون پرسش‌ها بلوک‌های بومی `core/details` هستند، فقط همان یکی باز
	 * می‌شود و بقیه با خودِ مرورگر کار می‌کنند (بدون جاوااسکریپت هم همه‌ی
	 * پاسخ‌ها در دسترس‌اند، فقط بسته‌اند).
	 */
	function initFaq() {
		var list = document.querySelector( '.faq-list' );

		if ( ! list ) {
			return;
		}

		var items = qsa( '.faq-item', list );

		if ( ! items.length ) {
			return;
		}

		var alreadyOpen = items.some( function ( item ) {
			return item.open;
		} );

		if ( ! alreadyOpen ) {
			items[ 0 ].open = true;
		}
	}

	/* ---------------------------------------------------------------------
	 * راه‌اندازی
	 * ------------------------------------------------------------------ */

	/**
	 * اجرای همه‌ی ماژول‌ها.
	 */
	function boot() {
		initColorMode();
		initShrinkHeader();
		initReadingProgress();
		initBackToTop();
		initAnchorScroll();
		initNavigation();
		initRails();
		initImageFallback();
		initToast();
		initDetailTabs();
		initLoadMore();
		initSearchOverlay();
		initMobileDrawer();
		initFaq();
		initComments();
	}

	/**
	 * فعال‌سازی رویداد سراسری برای اعلان‌ها.
	 */
	function initToast() {
		document.addEventListener( 'koohe:toast', function ( event ) {
			var detail = event.detail || {};
			showToast( detail.message || '', { type: detail.type || '', duration: detail.duration } );
		} );
	}

	/**
	 * بخش دیدگاه‌ها: شمارنده‌ی نویسه، ابزار اسپویل و آشکارسازی.
	 *
	 * معادل مرجع (`cinora/assets/js/detail.js`) با سه تفاوت عمدی:
	 *   • رقم‌ها فارسی می‌شوند (سایت فارسی است، مرجع لاتین می‌گذاشت).
	 *   • متن اسپویل حذف نمی‌شود؛ در همان عنصر می‌ماند و آشکار می‌شود.
	 *   • «تمام دیدگاه اسپویل دارد» وضعیت دکمه را با `aria-expanded`
	 *     اعلام می‌کند تا برای صفحه‌خوان هم روشن باشد.
	 */
	function initComments() {
		var section = document.querySelector( '.comments-section' );
		if ( ! section ) {
			return;
		}

		var input = section.querySelector( '#comment' );
		var counter = section.querySelector( '[data-comment-length]' );

		if ( input && counter ) {
			var max = parseInt( input.getAttribute( 'maxlength' ), 10 ) || 1500;
			var update = function () {
				counter.textContent = toFa( String( input.value.length ) ) + ' / ' + toFa( String( max ) );
			};
			input.addEventListener( 'input', update );
			update();
		}

		/* ابزار اسپویل: پیچیدن متن انتخاب‌شده در [spoiler]…[/spoiler]. */
		var tool = section.querySelector( '[data-spoiler-tool]' );
		if ( tool && input ) {
			tool.addEventListener( 'click', function () {
				var start = input.selectionStart;
				var end = input.selectionEnd;

				if ( start === end ) {
					showToast( 'اول بخشی از متن دیدگاه را انتخاب کن.', { type: 'error' } );
					input.focus();
					return;
				}

				input.value = input.value.slice( 0, start ) + '[spoiler]' + input.value.slice( start, end ) + '[/spoiler]' + input.value.slice( end );
				input.dispatchEvent( new Event( 'input' ) );
				input.focus();
				input.setSelectionRange( start + 9, end + 9 );
			} );
		}

		reveal( section, '[data-inline-spoiler]' );
		reveal( section, '[data-whole-spoiler]' );

		/* مرتب‌سازی دیدگاه‌های سطح اول بر پایه‌ی زمان خودِ هسته. */
		var sort = section.querySelector( '[data-comment-sort]' );
		var list = section.querySelector( '.wp-block-comment-template' );
		if ( sort && list ) {
			var applySort = function () {
				var items = Array.prototype.slice.call( list.children );
				items.sort( function ( a, b ) {
					var ta = time( a );
					var tb = time( b );
					return 'oldest' === sort.value ? ta - tb : tb - ta;
				} );
				items.forEach( function ( item ) {
					list.appendChild( item );
				} );
			};

			sort.addEventListener( 'change', applySort );
			/*
			 * وردپرس دیدگاه‌ها را از قدیمی به جدید می‌چیند؛ مرجع
			 * «جدیدترین» را پیش‌فرض دارد. با اجرای همان مرتب‌سازی در
			 * بارگذاری، ترتیبِ دیده‌شده با گزینه‌ی انتخابی یکی می‌شود.
			 * فقط دیدگاه‌های سطح اول جابه‌جا می‌شوند تا پاسخ‌ها کنار
			 * دیدگاه والدشان بمانند.
			 */
			applySort();
		}
	}

	/** زمان دیدگاه از `time[datetime]` هسته؛ نبودش یعنی «نامعلوم». */
	function time( item ) {
		var node = item.querySelector( 'time[datetime]' );
		if ( ! node ) {
			return 0;
		}
		var value = Date.parse( node.getAttribute( 'datetime' ) );
		return isNaN( value ) ? 0 : value;
	}

	/** دکمه‌های آشکارساز اسپویل (درون‌متنی و کل دیدگاه). */
	function reveal( scope, selector ) {
		Array.prototype.forEach.call( scope.querySelectorAll( selector ), function ( button ) {
			button.addEventListener( 'click', function () {
				var text = button.parentNode.querySelector( selector === '[data-inline-spoiler]' ? '.inline-spoiler__text' : '.whole-spoiler__text' );
				if ( ! text ) {
					return;
				}
				var open = 'true' !== button.getAttribute( 'aria-expanded' );
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				text.hidden = ! open;
				button.hidden = open;
			} );
		} );
	}

	/** تبدیل رقم‌های لاتین به فارسی (هم‌ارز `manacore_fa_digits`). */
	function toFa( value ) {
		return String( value ).replace( /[0-9]/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt( parseInt( d, 10 ) );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	/* بازسازی ریل‌ها پس از بارگذاری پویا (مثلاً فیلترهای AJAX هسته). */
	document.addEventListener( 'manacore:content-updated', initRails );
} )();

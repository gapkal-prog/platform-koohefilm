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
			download: document.querySelector( '.manacore-links, .manacore-links-block' ),
			cast: document.querySelector( '.manacore-cast, .manacore-cast-block' ),
			comments: document.querySelector( '.koohe-comments' )
		};

		/**
		 * فاصله‌ی پایین هدر و تب‌بار برای اسکرول دقیق.
		 */
		function stickyOffset() {
			var header = document.querySelector( '.koohe-header' ) || document.querySelector( 'header' );
			var h = header ? header.offsetHeight : 0;
			return h + bar.offsetHeight + 16;
		}

		function setActive( name ) {
			for ( var i = 0; i < buttons.length; i++ ) {
				var isActive = buttons[ i ].getAttribute( 'data-koohe-tab' ) === name;
				buttons[ i ].classList.toggle( 'is-active', isActive );
				buttons[ i ].setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			}
		}

		var isClickScrolling = false;

		function scrollTo( el ) {
			var top = el.getBoundingClientRect().top + window.pageYOffset - stickyOffset();
			window.scrollTo( { top: top, behavior: 'smooth' } );
		}

		for ( var i = 0; i < buttons.length; i++ ) {
			( function ( btn ) {
				var name = btn.getAttribute( 'data-koohe-tab' );
				var el = targets[ name ];

				if ( ! el ) {
					btn.hidden = true;
					return;
				}

				btn.setAttribute( 'role', 'tab' );
				btn.setAttribute( 'aria-selected', btn.classList.contains( 'is-active' ) ? 'true' : 'false' );

				btn.addEventListener( 'click', function () {
					setActive( name );
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
	 * پنل جستجوی سریع.
	 *
	 * نتایج زنده از REST هسته (منسوخ با data-manacore-search) نمی‌آید؛ اینجا
	 * مستقیم از endpoint سرچ هسته استفاده می‌شود. اگر ورودی خالی باشد، نتایج
	 * پنهان و دکمه‌ی Escape بسته می‌شود. دکمه‌ی هدر با data-koohe-search-open
	 * و کلید میان‌بر ⌘K/Ctrl+K آن را باز می‌کند.
	 */
	function initSearchOverlay() {
		var overlay = document.querySelector( '[data-koohe-search-overlay]' );
		if ( ! overlay ) {
			return;
		}

		var input = overlay.querySelector( '[data-koohe-search-input]' );
		var results = overlay.querySelector( '[data-koohe-search-results]' );
		var empty = overlay.querySelector( '[data-koohe-search-empty]' );
		var lastQuery = '';
		var searchTimer = null;

		function open() {
			overlay.hidden = false;
			document.body.classList.add( 'koohe-search-open' );
			if ( input ) {
				input.focus();
			}
		}

		function close() {
			overlay.hidden = true;
			document.body.classList.remove( 'koohe-search-open' );
		}

		function renderResults( items ) {
			if ( ! results ) {
				return;
			}
			results.hidden = ! items.length;
			if ( empty ) {
				empty.hidden = !! items.length;
			}

			results.innerHTML = '';
			items.forEach( function ( item ) {
				var a = document.createElement( 'a' );
				a.href = item.url || '#';

				var img = document.createElement( 'img' );
				img.src = item.image || '';
				img.alt = '';
				img.loading = 'lazy';
				a.appendChild( img );

				var body = document.createElement( 'span' );
				var strong = document.createElement( 'strong' );
				strong.textContent = item.title || '';
				body.appendChild( strong );

				if ( item.type ) {
					var small = document.createElement( 'small' );
					small.textContent = item.type;
					body.appendChild( small );
				}

				a.appendChild( body );
				results.appendChild( a );
			} );
		}

		function runSearch( query ) {
			lastQuery = query;
			var restBase = ( window.manacore && window.manacore.restUrl ) || '/wp-json/';
			var restBase2 = restBase.replace( /\/?$/, '/' );
			fetch( restBase2 + 'wp/v2/search?search=' + encodeURIComponent( query ) + '&per_page=6' )
				.then( function ( r ) {
					return r.ok ? r.json() : [];
				} )
				.then( function ( data ) {
					/* اگر در فاصله‌ی واکشی، پرسمان عوض شده باشد، نتیجه‌ی کهنه نکوب. */
					if ( query !== lastQuery ) {
						return;
					}
					renderResults( ( data || [] ).map( function ( item ) {
						return {
							url: item.url || item._links && item._links.self && item._links.self[ 0 ] && item._links.self[ 0 ].href || '#',
							title: item.title || '',
							type: item.subtype || item.type || ''
						};
					} ) );
				} )
				.catch( function () {} );
		}

		if ( input ) {
			input.addEventListener( 'input', function () {
				var query = input.value.trim();
				window.clearTimeout( searchTimer);
				if ( query.length < 2 ) {
					if ( results ) {
						results.hidden = true;
					}
					if ( empty ) {
						empty.hidden = true;
					}
					return;
				}
				searchTimer = window.setTimeout( function () {
					runSearch( query );
				}, 250 );
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
			}		if ( 'Escape' === event.key && ! overlay.hidden ) {
			close();
		}
	} );
}

	/* ---------------------------------------------------------------------
	 * مگامنوی دسته‌بندی‌ها (بازآفرینی سینورا)
	 * ------------------------------------------------------------------ */

	/**
	 * پنل مگامنو: باز شدن با hover/focus/کلیک، بستن با Escape و کلیک بیرون.
	 *
	 * پنل با شورت‌کد [manacore_mega_menu] در قطعه‌ی سربرگ رندر می‌شود
	 * (data-mega-menu) و دکمه‌ی بازکن آن data-koohe-mega-open دارد؛ بدون JS
	 * دکمه به آرشیو ژانرها می‌رود و پنل hidden می‌ماند.
	 */
	function initMegaMenu() {
		var panel  = document.querySelector( '[data-mega-menu]' );
		var toggle = document.querySelector( '[data-koohe-mega-open]' );

		if ( ! panel || ! toggle ) {
			return;
		}

		var fallback = toggle.getAttribute( 'href' ) || '/genre/';
		var opened   = false;
		/* اگر پنل خالی رندر شد (بدون ترم)، دکمه باید لینک معمولی بماند. */
		var usable   = !! panel.querySelector( '.manacore-mega__genres a' );

		function setOpen( open ) {
			opened = open;
			panel.hidden = ! open;
			toggle.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		function toggleFromEvent( event ) {
			event.preventDefault();

			if ( ! usable ) {
				window.location.href = fallback;
				return;
			}

			setOpen( ! opened );
		}

		toggle.addEventListener( 'click', toggleFromEvent );

		toggle.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				toggleFromEvent( event );
			}
		} );

		panel.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && opened ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( opened && ! panel.contains( event.target ) && ! toggle.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		toggle.addEventListener( 'mouseenter', function () {
			if ( usable && ! opened ) {
				setOpen( true );
			}
		} );

		if ( ! usable ) {
			toggle.setAttribute( 'href', fallback );
			toggle.setAttribute( 'role', 'link' );
			toggle.removeAttribute( 'aria-haspopup' );
			toggle.removeAttribute( 'tabindex' );
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
		initMegaMenu();
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

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	/* بازسازی ریل‌ها پس از بارگذاری پویا (مثلاً فیلترهای AJAX هسته). */
	document.addEventListener( 'manacore:content-updated', initRails );
} )();

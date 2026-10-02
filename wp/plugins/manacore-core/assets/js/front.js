/**
 * تعامل‌های سمت کاربر: امتیازدهی، لیست تماشا، کپی لینک، پخش‌کننده، جستجو، اسلایدر.
 *
 * @package ManaCore\Core
 */
( function () {
	'use strict';

	var config = window.manaCore || {};
	var i18n = config.i18n || {};

	function api( path, options ) {
		options = options || {};
		return fetch( config.restUrl + path, {
			method: options.method || 'GET',
			headers: Object.assign(
				{
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				options.headers || {}
			),
			credentials: 'same-origin',
			body: options.body ? JSON.stringify( options.body ) : undefined,
		} ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					throw json;
				}
				return json;
			} );
		} );
	}

	/* ---------------- اعلان شناور ---------------- */
	var toastTimer = null;

	function toast( message, isError ) {
		var node = document.getElementById( 'manacore-toast' );
		if ( ! node ) {
			node = document.createElement( 'div' );
			node.id = 'manacore-toast';
			node.className = 'manacore-toast';
			node.setAttribute( 'role', 'status' );
			node.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( node );
		}
		node.textContent = message;
		node.classList.toggle( 'is-error', !! isError );
		node.classList.add( 'is-visible' );
		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			node.classList.remove( 'is-visible' );
		}, 2600 );
	}

	/* ---------------- کپی لینک ---------------- */
	function initCopy() {
		document.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-manacore-copy]' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();
			var url = btn.getAttribute( 'data-manacore-copy' );

			var done = function () {
				toast( i18n.copied || 'کپی شد' );
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( url ).then( done ).catch( function () {
					fallbackCopy( url );
					done();
				} );
			} else {
				fallbackCopy( url );
				done();
			}
		} );
	}

	function fallbackCopy( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			// نادیده.
		}
		document.body.removeChild( area );
	}

	/* ---------------- امتیازدهی ---------------- */
	function initRating() {
		document.querySelectorAll( '[data-manacore-rating]' ).forEach( function ( box ) {
			var postId = parseInt( box.getAttribute( 'data-manacore-rating' ), 10 );
			var stars = Array.prototype.slice.call( box.querySelectorAll( '.manacore-star' ) );
			var summary = box.querySelector( '[data-rating-summary]' );

			function paint( value ) {
				stars.forEach( function ( star, index ) {
					star.classList.toggle( 'is-on', index < value );
				} );
			}

			stars.forEach( function ( star ) {
				var value = parseInt( star.getAttribute( 'data-value' ), 10 );

				star.addEventListener( 'mouseenter', function () {
					paint( value );
				} );

				star.addEventListener( 'click', function () {
					star.disabled = true;
					api( 'rate', {
						method: 'POST',
						body: { post_id: postId, rating: value },
					} )
						.then( function ( result ) {
							paint( result.mine || value );
							stars.forEach( function ( s, index ) {
								s.setAttribute( 'aria-checked', index + 1 === result.mine ? 'true' : 'false' );
							} );
							if ( summary ) {
								summary.textContent =
									Number( result.average ).toFixed( 1 ) +
									' / 10 (' +
									result.count +
									')';
							}
							toast( i18n.added ? 'ثبت شد' : 'ثبت شد' );
						} )
						.catch( function ( error ) {
							toast( ( error && error.message ) || i18n.error || 'خطا', true );
						} )
						.finally( function () {
							star.disabled = false;
						} );
				} );
			} );

			box.addEventListener( 'mouseleave', function () {
				var checked = box.querySelector( '.manacore-star[aria-checked="true"]' );
				paint( checked ? parseInt( checked.getAttribute( 'data-value' ), 10 ) : 0 );
			} );
		} );
	}

	/* ---------------- لیست تماشا ---------------- */
	function initWatchlist() {
		document.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-manacore-watchlist]' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();

			if ( ! config.loggedIn ) {
				toast( i18n.loginRequired || 'ابتدا وارد شوید', true );
				window.setTimeout( function () {
					window.location.href = config.loginUrl;
				}, 900 );
				return;
			}

			var postId = parseInt( btn.getAttribute( 'data-manacore-watchlist' ), 10 );
			btn.disabled = true;

			api( 'watchlist', {
				method: 'POST',
				body: { post_id: postId },
			} )
				.then( function ( result ) {
					btn.setAttribute( 'aria-pressed', result.active ? 'true' : 'false' );
					btn.classList.toggle( 'is-active', result.active );
					toast( result.active ? i18n.added : i18n.removed );
				} )
				.catch( function () {
					toast( i18n.error || 'خطا', true );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	/* ---------------- پخش‌کننده ---------------- */
	function embedFor( url ) {
		var youtube = url.match(
			/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/i
		);
		if ( youtube ) {
			return {
				type: 'iframe',
				src: 'https://www.youtube-nocookie.com/embed/' + youtube[ 1 ] + '?rel=0&autoplay=1',
			};
		}
		var aparat = url.match( /aparat\.com\/v\/([A-Za-z0-9]+)/i );
		if ( aparat ) {
			return {
				type: 'iframe',
				src: 'https://www.aparat.com/video/video/embed/videohash/' + aparat[ 1 ] + '/vt/frame',
			};
		}
		var vimeo = url.match( /vimeo\.com\/(\d+)/i );
		if ( vimeo ) {
			return { type: 'iframe', src: 'https://player.vimeo.com/video/' + vimeo[ 1 ] + '?autoplay=1' };
		}
		if ( /\.(mp4|webm|ogv|m3u8)(\?|$)/i.test( url ) ) {
			return { type: 'video', src: url };
		}
		return { type: 'iframe', src: url };
	}

	function initPlayer() {
		var modal = document.getElementById( 'manacore-player-modal' );
		if ( ! modal ) {
			return;
		}
		var body = modal.querySelector( '[data-modal-body]' );
		var title = modal.querySelector( '[data-modal-title]' );
		var lastFocus = null;

		function open( url, label ) {
			var embed = embedFor( url );
			body.innerHTML = '';

			if ( embed.type === 'video' ) {
				var video = document.createElement( 'video' );
				video.controls = true;
				video.autoplay = true;
				video.playsInline = true;
				video.src = embed.src;
				body.appendChild( video );
			} else {
				var frame = document.createElement( 'iframe' );
				frame.src = embed.src;
				frame.allow =
					'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
				frame.allowFullscreen = true;
				frame.referrerPolicy = 'strict-origin-when-cross-origin';
				frame.title = label || 'player';
				body.appendChild( frame );
			}

			if ( title ) {
				title.textContent = label || '';
			}
			lastFocus = document.activeElement;
			modal.hidden = false;
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'manacore-modal-open' );
			var closeBtn = modal.querySelector( '.manacore-modal-close' );
			if ( closeBtn ) {
				closeBtn.focus();
			}
		}

		function close() {
			body.innerHTML = '';
			modal.hidden = true;
			modal.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'manacore-modal-open' );
			if ( lastFocus ) {
				lastFocus.focus();
			}
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-manacore-play]' );
			if ( trigger ) {
				event.preventDefault();
				open( trigger.getAttribute( 'data-manacore-play' ), trigger.getAttribute( 'data-title' ) );
				return;
			}
			if ( event.target.closest( '[data-manacore-close]' ) ) {
				close();
			}
		} );

		/*
		 * حالت سینما: جعبه‌ی مودال تمام‌صفحه می‌شود (CSS). روی ویدیوی
		 * تمام‌صفحه‌ی بومی خودکار خاموش می‌شود تا کنترل‌ها دوباره پیدا شوند.
		 */
		var cinemaBtn = modal.querySelector( '[data-manacore-cinema]' );

		if ( cinemaBtn ) {
			cinemaBtn.addEventListener( 'click', function () {
				var on = ! modal.classList.contains( 'is-cinema' );
				modal.classList.toggle( 'is-cinema', on );
				cinemaBtn.classList.toggle( 'is-on', on );
				cinemaBtn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}

		document.addEventListener( 'fullscreenchange', function () {
			if ( document.fullscreenElement && modal.classList.contains( 'is-cinema' ) && cinemaBtn ) {
				modal.classList.remove( 'is-cinema' );
				cinemaBtn.classList.remove( 'is-on' );
				cinemaBtn.setAttribute( 'aria-pressed', 'false' );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! modal.hidden ) {
				close();
			}
		} );
	}

	/* ---------------- تب فصل‌ها ---------------- */
	function initSeasonTabs() {
		document.querySelectorAll( '.manacore-links' ).forEach( function ( wrap ) {
			wrap.querySelectorAll( '[data-season-tab]' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					var target = tab.getAttribute( 'data-season-tab' );
					wrap.querySelectorAll( '[data-season-tab]' ).forEach( function ( other ) {
						var active = other === tab;
						other.classList.toggle( 'is-active', active );
						other.setAttribute( 'aria-selected', active ? 'true' : 'false' );
					} );
					wrap.querySelectorAll( '[data-season-panel]' ).forEach( function ( panel ) {
						panel.classList.toggle(
							'is-active',
							panel.getAttribute( 'data-season-panel' ) === target
						);
					} );
				} );
			} );
		} );
	}

	/* ---------------- جستجوی زنده ---------------- */
	function initSearch() {
		document.querySelectorAll( '[data-manacore-search]' ).forEach( function ( wrap ) {
			var input = wrap.querySelector( '.manacore-search-input' );
			var results = wrap.querySelector( '[data-search-results]' );
			var timer = null;
			var controller = null;

			if ( ! input || ! results ) {
				return;
			}

			// تنظیمات بلوک جستجو از ویرایشگر.
			if ( '0' === wrap.getAttribute( 'data-live' ) ) {
				return;
			}

			var limit = parseInt( wrap.getAttribute( 'data-limit' ), 10 );
			if ( ! limit || limit < 1 ) {
				limit = 8;
			}

			var types = wrap.getAttribute( 'data-types' ) || '';

			input.addEventListener( 'input', function () {
				var term = input.value.trim();
				window.clearTimeout( timer );

				if ( term.length < 2 ) {
					results.hidden = true;
					results.innerHTML = '';
					return;
				}

				timer = window.setTimeout( function () {
					if ( controller ) {
						controller.abort();
					}
					controller = new AbortController();

					results.hidden = false;
					results.innerHTML =
						'<p class="manacore-search-status">' + ( i18n.searching || 'در حال جستجو…' ) + '</p>';

					var endpoint =
						config.restUrl +
						'search?q=' +
						encodeURIComponent( term ) +
						'&per_page=' +
						encodeURIComponent( limit ) +
						( types ? '&type=' + encodeURIComponent( types ) : '' );

					fetch( endpoint, {
						signal: controller.signal,
						credentials: 'same-origin',
					} )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( payload ) {
							if ( ! payload.items || ! payload.items.length ) {
								results.innerHTML =
									'<p class="manacore-search-status">' +
									( i18n.noResults || 'نتیجه‌ای یافت نشد.' ) +
									'</p>';
								return;
							}
							results.innerHTML = payload.items
								.map( function ( item ) {
									return (
										'<a class="manacore-search-item" href="' +
										item.url +
										'"><img src="' +
										item.poster +
										'" alt="" loading="lazy" /><span class="manacore-search-item-body">' +
										'<strong>' +
										item.title +
										'</strong><small>' +
										[ item.typeLabel, item.year, item.rating ? '⭐ ' + item.rating : '' ]
											.filter( Boolean )
											.join( ' • ' ) +
										'</small></span></a>'
									);
								} )
								.join( '' );
						} )
						.catch( function ( error ) {
							if ( error.name !== 'AbortError' ) {
								results.innerHTML =
									'<p class="manacore-search-status">' + ( i18n.error || 'خطا' ) + '</p>';
							}
						} );
				}, 300 );
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! wrap.contains( event.target ) ) {
					results.hidden = true;
				}
			} );
		} );
	}

	/* ---------------- اسلایدر ویژه ---------------- */
	function initHero() {
		document.querySelectorAll( '[data-manacore-hero]' ).forEach( function ( hero ) {
			var slides = Array.prototype.slice.call( hero.querySelectorAll( '[data-hero-slide]' ) );
			var dots = Array.prototype.slice.call( hero.querySelectorAll( '[data-hero-dot]' ) );
			if ( slides.length < 2 ) {
				return;
			}

			var current = 0;
			var timer = null;

			// تنظیمات از ویرایشگر بلوک روی خود عنصر نوشته می‌شود.
			var autoplay = '0' !== hero.getAttribute( 'data-autoplay' );
			var interval = parseInt( hero.getAttribute( 'data-interval' ), 10 );
			if ( ! interval || interval < 1500 ) {
				interval = 7000;
			}

			var prevBtn = hero.querySelector( '[data-hero-prev]' );
			var nextBtn = hero.querySelector( '[data-hero-next]' );

			function show( index ) {
				current = ( index + slides.length ) % slides.length;
				slides.forEach( function ( slide, i ) {
					slide.classList.toggle( 'is-active', i === current );
				} );
				dots.forEach( function ( dot, i ) {
					dot.classList.toggle( 'is-active', i === current );
					dot.setAttribute( 'aria-selected', i === current ? 'true' : 'false' );
				} );
			}

			function start() {
				if ( ! autoplay ) {
					return;
				}
				if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
					return;
				}
				stop();
				timer = window.setInterval( function () {
					show( current + 1 );
				}, interval );
			}

			function stop() {
				window.clearInterval( timer );
			}

			dots.forEach( function ( dot, index ) {
				dot.addEventListener( 'click', function () {
					show( index );
					start();
				} );
			} );

			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					show( current - 1 );
					start();
				} );
			}

			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					show( current + 1 );
					start();
				} );
			}

			// پیمایش با صفحه‌کلید برای دسترس‌پذیری.
			hero.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowLeft' === event.key ) {
					show( current + 1 );
					start();
				} else if ( 'ArrowRight' === event.key ) {
					show( current - 1 );
					start();
				}
			} );

			hero.addEventListener( 'mouseenter', stop );
			hero.addEventListener( 'mouseleave', start );
			start();
		} );
	}

	/* ---------------- آمار دانلود ---------------- */
	function initDownloadTracking() {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-manacore-download]' );
			if ( ! link ) {
				return;
			}
			var postId = parseInt( link.getAttribute( 'data-manacore-download' ), 10 );
			if ( ! postId ) {
				return;
			}
			var payload = JSON.stringify( { post_id: postId } );
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon(
					config.restUrl + 'track-download',
					new Blob( [ payload ], { type: 'application/json' } )
				);
			} else {
				api( 'track-download', { method: 'POST', body: { post_id: postId } } ).catch( function () {} );
			}
		} );
	}

	/* ---------------- حالت تیره/روشن ---------------- */
	function initColorMode() {
		var STORAGE_KEY = 'manacore-color-mode';
		var root = document.documentElement;

		function apply( mode ) {
			root.setAttribute( 'data-color-mode', mode );
			try {
				window.localStorage.setItem( STORAGE_KEY, mode );
			} catch ( e ) {
				// نادیده.
			}
			document.querySelectorAll( '[data-manacore-theme-toggle]' ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-pressed', mode === 'dark' ? 'true' : 'false' );
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-manacore-theme-toggle]' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();
			var next = root.getAttribute( 'data-color-mode' ) === 'dark' ? 'light' : 'dark';
			apply( next );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initCopy();
		initRating();
		initWatchlist();
		initPlayer();
		initSeasonTabs();
		initSearch();
		initHero();
		initDownloadTracking();
		initColorMode();
	} );
} )();

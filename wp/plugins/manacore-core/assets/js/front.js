/**
 * تعامل‌های سمت کاربر: امتیازدهی، لیست تماشا، کپی لینک، پخش‌کننده، جستجو، اسلایدر.
 *
 * @package ManaCore\Core
 */
( function () {
	'use strict';

	/*
	 * کلید ذخیره‌ی پیشرفت تماشا. مرجع همین کار را با
	 * `cinora-progress` می‌کند؛ ما فضای‌نام افزونه را نگه می‌داریم.
	 */
	var PROGRESS_KEY = 'manacore-progress';

	function progressKey( postId, quality ) {
		return String( postId || 0 ) + '|' + String( quality || '' );
	}

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

	/* ---------------- صفحه‌ی پخش ---------------- */
	/*
	 * کنش‌های نوار کنترل، مثل `player.html` مرجع:
	 *   ۱) انتخاب کیفیت → منبع ویدئو + نشان کیفیت + پیوند دانلود همان کیفیت
	 *   ۲) حالت سینما → کلاس `cinema-mode` روی ریشه (قاب پهن‌تر)
	 *   ۳) تمام‌صفحه → Fullscreen API روی همان قاب
	 *   ۴) پیشرفت تماشا در حافظه‌ی محلی ذخیره و در بازدید بعد ادامه داده می‌شود
	 * در حالت iframe (آپارات/یوتیوب) کیفیت جابه‌جا نمی‌شود؛ فقط پوسته‌ی
	 * بیرونی تغییر می‌کند تا شکستن پخش رخ ندهد.
	 */
	function initPlayerPage() {
		var roots = document.querySelectorAll( '[data-manacore-player-page]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			var video = root.querySelector( '[data-player-video]' );
			var select = root.querySelector( '[data-player-quality]' );
			var badge = root.querySelector( '.player-badges > span:last-child' );
			var download = root.querySelector( '[data-player-download]' );

			/*
			 * نشاندن منبع جاری با آگاهی از نوع رسانه: `.m3u8` روی مرورگرهای
			 * بدون پخش بومی HLS از راه `hls.js` می‌رود. نمونه‌ی فعال در
			 * `detachHls` نگه داشته می‌شود تا با هر تعویض کیفیت آزاد شود.
			 */
			var detachHls = null;

			if ( video && null === video.getAttribute( 'src' ) ) {
				var initial = video.querySelector( 'source[data-src]' );
				var initialUrl = initial ? String( initial.getAttribute( 'data-src' ) || '' ) : '';

				/*
				 * منبع نخست HLS است و مرورگر پخش بومی ندارد؟ پس پیش از هر
				 * اقدامی `hls.js` وصل می‌شود. برای mp4 همان مسیر بومی
				 * `<source>` می‌ماند و هیچ اسکریپتی اضافه نمی‌شود.
				 */
				if ( /\.m3u8(\?|#|$)/i.test( initialUrl ) ) {
					detachHls = attachSource( video, initialUrl, false );
				}
			}

			/*
			 * «تماشا» = شروع واقعی پخش، نه بازشدن صفحه. یک‌بار برای هر
			 * بارگذاریِ صفحه فرستاده می‌شود و سرور هم پنجره‌ی
			 * ضدرعدّ‌سازی دارد؛ پس رفرش، عدد را باد نمی‌کند.
			 */
			if ( video ) {
				var watchCounted = false;

				video.addEventListener( 'play', function () {
					if ( watchCounted ) {
						return;
					}

					watchCounted = true;

					api( 'track-view', {
						method: 'POST',
						body: {
							post_id: parseInt( root.getAttribute( 'data-manacore-player-page' ), 10 ) || 0,
						},
					} ).catch( function () {} );
				} );
			}

			if ( video && select ) {
				select.addEventListener( 'change', function () {
					var option = select.options[ select.selectedIndex ];
					var source = option ? option.getAttribute( 'data-src' ) : '';
					if ( ! source ) {
						var match = root.querySelector( '[data-quality="' + select.value + '"]' );
						source = match ? match.getAttribute( 'data-src' ) : '';
					}
					if ( ! source ) {
						return;
					}

					/* نمونه‌ی پیشین HLS آزاد می‌شود؛ وگرنه هر تعویض کیفیت
					 * یک نمونه‌ی زنده‌ی دیگر در حافظه می‌گذاشت. */
					if ( typeof detachHls === 'function' ) {
						detachHls();
						detachHls = null;
					}

					var wasPlaying = ! video.paused;
					video.setAttribute( 'data-player-media', /\.m3u8(\?|#|$)/i.test( source ) ? 'hls' : 'file' );
					detachHls = attachSource( video, source, wasPlaying );

					/*
					 * مرجع با تغییر کیفیت، نشان کیفیت و پیوند دانلود را هم
					 * به همان کیفیت می‌برد — نه فقط منبع پخش را.
					 */
					if ( badge ) {
						badge.textContent = select.value;
					}

					var file = option ? option.getAttribute( 'data-download' ) : '';
					if ( download && file ) {
						download.setAttribute( 'href', file );
					}
				} );
			}

			/*
			 * ادامه‌ی تماشا: مرجع پیشرفت را در حافظه‌ی محلی نگه می‌دارد و
			 * بازدید بعد از همان‌جا ادامه می‌دهد. کلید برای هر اثر و کیفیت
			 * جداگانه است تا جابه‌جایی کیفیت، پیشرفت را گم نکند.
			 */
			if ( video ) {
				var progressId = progressKey( root.getAttribute( 'data-manacore-player-page' ), select ? select.value : '' );
				var lastSaved = -1;

				var readProgress = function () {
					try {
						var all = JSON.parse( window.localStorage.getItem( PROGRESS_KEY ) || '{}' );
						return all[ progressId ] || null;
					} catch ( e ) {
						return null;
					}
				};

				var saveProgress = function ( complete ) {
					if ( ! video.duration ) {
						return;
					}

					var seconds = Math.floor( video.currentTime );
					if ( ! complete && seconds - lastSaved < 3 ) {
						return;
					}

					lastSaved = seconds;

					try {
						var all = JSON.parse( window.localStorage.getItem( PROGRESS_KEY ) || '{}' );
						all[ progressId ] = {
							progress: complete ? 100 : ( video.currentTime / video.duration ) * 100,
							seconds: seconds,
							updatedAt: new Date().toISOString()
						};
						window.localStorage.setItem( PROGRESS_KEY, JSON.stringify( all ) );
					} catch ( e ) {}

					pushServerProgress(
						parseInt( root.getAttribute( 'data-manacore-player-page' ), 10 ) || 0,
						complete ? 100 : ( video.currentTime / video.duration ) * 100,
						seconds
					);
				};

				var resume = function () {
					var saved = readProgress();
					if ( saved && saved.progress > 0 && saved.progress < 95 && video.duration ) {
						try {
							video.currentTime = ( video.duration * saved.progress ) / 100;
						} catch ( e ) {}
					}
				};

				video.addEventListener( 'loadedmetadata', resume );
				video.addEventListener( 'timeupdate', function () { saveProgress( false ); } );
				video.addEventListener( 'pause', function () {
					lastSaved = -10;
					saveProgress( false );
				} );
				video.addEventListener( 'ended', function () { saveProgress( true ); } );
			}

			var cinema = root.querySelector( '[data-player-cinema]' );
			if ( cinema ) {
				cinema.addEventListener( 'click', function () {
					/* مرجع کلاس `cinema-mode` را روی ریشه‌ی صفحه می‌گذارد. */
					var on = root.classList.toggle( 'cinema-mode' );
					cinema.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
			}

			var full = root.querySelector( '[data-player-fullscreen]' );
			var frame = root.querySelector( '[data-player-frame]' );
			if ( full && frame ) {
				full.addEventListener( 'click', function () {
					if ( document.fullscreenElement ) {
						document.exitFullscreen();
						return;
					}
					if ( frame.requestFullscreen ) {
						frame.requestFullscreen();
					}
				} );
			}

			/* Escape از حالت سینما بیرون می‌آید (مثل مُدال). */
			document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key && root.classList.contains( 'cinema-mode' ) ) {
					root.classList.remove( 'cinema-mode' );
					if ( cinema ) {
						cinema.setAttribute( 'aria-pressed', 'false' );
					}
				}
			} );

			/* افزودنی‌های پلیر: سرعت پخش، تصویر در تصویر، میان‌بُرها، قسمت بعدی. */
			if ( video ) {
				initPlayerExtras( root, video );
				initPlayerKeyboard( root, video, frame );
				initNextEpisode( root, video );
			}
		} );
	}

	/* ---------------- پخش‌کننده‌ی حرفه‌ای ---------------- */

	/*
	 * کتابخانه‌ی HLS فقط در صورت نیاز بارگذاری می‌شود: صفحه‌هایی که منبع
	 * `.m3u8` دارند یک اسکریپت ۶۰۰ کیلوبایتی می‌گیرند و بقیه هیچ. فایل از
	 * خودِ افزونه سرو می‌شود (`config.hlsUrl`)، نه CDN — سایت‌های فارسی
	 * مخاطب ما به CDN دسترسی پایدار ندارند.
	 */
	var hlsPromise = null;

	function loadHls() {
		if ( window.Hls ) {
			return Promise.resolve( window.Hls );
		}

		if ( hlsPromise ) {
			return hlsPromise;
		}

		hlsPromise = new Promise( function ( resolve, reject ) {
			if ( ! config.hlsUrl ) {
				reject( new Error( 'hls-url-missing' ) );
				return;
			}

			var script = document.createElement( 'script' );
			script.src = config.hlsUrl;
			script.async = true;
			script.onload = function () {
				window.Hls ? resolve( window.Hls ) : reject( new Error( 'hls-missing' ) );
			};
			script.onerror = function () {
				reject( new Error( 'hls-load-failed' ) );
			};
			document.head.appendChild( script );
		} );

		return hlsPromise;
	}

	/*
	 * نشاندن یک منبع روی پلیر: اگر HLS باشد و مرورگر پخش بومی نداشته
	 * باشد، `hls.js` وصل می‌شود؛ در غیر این صورت همان `video.src`.
	 * مقدار برگشتی، نمونه‌ی فعال HLS است (یا null) تا با تغییر کیفیت
	 * نمونه‌ی قبلی آزاد شود — بی آن، هر تعویض کیفیت یک نمونه‌ی زنده‌ی
	 * دیگر در حافظه می‌گذاشت.
	 */
	function attachSource( video, url, autoplay, onReady ) {
		var needHls = /\.m3u8(\?|#|$)/i.test( String( url || '' ) );
		var nativeHls = video.canPlayType( 'application/vnd.apple.mpegurl' );

		if ( ! needHls || nativeHls ) {
			video.src = url;
			video.load();
			if ( autoplay ) {
				video.play().catch( function () {} );
			}
			if ( onReady ) {
				onReady( null );
			}
			return null;
		}

		var instance = null;

		loadHls()
			.then( function ( Hls ) {
				if ( ! Hls.isSupported() ) {
					video.src = url;
					video.load();
					return;
				}

				instance = new Hls( { enableWorker: true, lowLatencyMode: false } );
				instance.loadSource( url );
				instance.attachMedia( video );

				instance.on( Hls.Events.MANIFEST_PARSED, function () {
					if ( autoplay ) {
						video.play().catch( function () {} );
					}
				} );

				if ( onReady ) {
					onReady( instance );
				}
			} )
			.catch( function () {
				/* شکست بارگذاری کتابخانه = افت به پخش مستقیم. */
				video.src = url;
				video.load();
			} );

		return function () {
			if ( instance ) {
				instance.destroy();
			}
		};
	}

	/*
	 * میان‌بُرهای کیبورد پلیر. مرجع ندارد، ولی هر پلیر حرفه‌ای دارد و
	 * دسترس‌پذیری را هم بالا می‌برد (کاربر بدون ماوس هم می‌تواند پخش را
	 * کنترل کند). در ورودی‌های متنی هرگز فعال نمی‌شود.
	 */
	function isTyping( target ) {
		if ( ! target ) {
			return false;
		}

		var tag = String( target.tagName || '' ).toLowerCase();

		return 'input' === tag || 'textarea' === tag || 'select' === tag || target.isContentEditable;
	}

	function initPlayerKeyboard( root, video, frame ) {
		document.addEventListener( 'keydown', function ( event ) {
			if ( isTyping( event.target ) || event.metaKey || event.ctrlKey || event.altKey ) {
				return;
			}

			/* فقط وقتی صفحه‌ی پخش واقعاً در دید است. */
			if ( ! root.isConnected || root.offsetParent === null ) {
				return;
			}

			var handled = true;

			switch ( event.key ) {
				case ' ':
				case 'k':
				case 'K':
					video.paused ? video.play().catch( function () {} ) : video.pause();
					break;
				case 'ArrowRight':
					video.currentTime = Math.min( video.duration || 0, video.currentTime + 5 );
					break;
				case 'ArrowLeft':
					video.currentTime = Math.max( 0, video.currentTime - 5 );
					break;
				case 'ArrowUp':
					video.volume = Math.min( 1, video.volume + 0.1 );
					break;
				case 'ArrowDown':
					video.volume = Math.max( 0, video.volume - 0.1 );
					break;
				case 'm':
				case 'M':
					video.muted = ! video.muted;
					break;
				case 'f':
				case 'F':
					if ( frame && frame.requestFullscreen && ! document.fullscreenElement ) {
						frame.requestFullscreen();
					} else if ( document.fullscreenElement ) {
						document.exitFullscreen();
					}
					break;
				default:
					handled = false;
			}

			if ( handled ) {
				event.preventDefault();
			}
		} );
	}

	/*
	 * دکمه‌های «سرعت پخش» و «تصویر در تصویر» با جاوااسکریپت ساخته
	 * می‌شوند تا مارک‌آپ سمت سرور (و قرارداد آزمون‌های هم‌سانی با مرجع)
	 * دست‌نخورده بماند. اگر مرورگر PiP نداشته باشد، دکمه ساخته نمی‌شود.
	 */
	var SPEEDS = [ 0.75, 1, 1.25, 1.5, 2 ];

	function initPlayerExtras( root, video ) {
		var controls = root.querySelector( '.player-controls' );
		if ( ! controls || root.querySelector( '[data-player-speed]' ) ) {
			return;
		}

		var speedIndex = 1;

		try {
			var saved = parseFloat( window.localStorage.getItem( 'manacore-speed' ) );
			var found = SPEEDS.indexOf( saved );
			if ( found > -1 ) {
				speedIndex = found;
			}
		} catch ( e ) {}

		video.playbackRate = SPEEDS[ speedIndex ];

		var speed = document.createElement( 'button' );
		speed.type = 'button';
		speed.className = 'manacore-btn is-secondary is-small';
		speed.setAttribute( 'data-player-speed', '1' );
		speed.setAttribute(
			'aria-label',
			( i18n.playbackSpeed || 'سرعت پخش' ) + ': ' + SPEEDS[ speedIndex ] + '×'
		);
		speed.innerHTML = '<span aria-hidden="true">⏱</span> ';

		var speedLabel = document.createElement( 'span' );
		speedLabel.setAttribute( 'data-player-speed-label', '1' );
		speedLabel.textContent = SPEEDS[ speedIndex ] + '×';
		speed.appendChild( speedLabel );

		speed.addEventListener( 'click', function () {
			speedIndex = ( speedIndex + 1 ) % SPEEDS.length;
			var rate = SPEEDS[ speedIndex ];
			video.playbackRate = rate;
			speedLabel.textContent = rate + '×';
			speed.setAttribute( 'aria-label', ( i18n.playbackSpeed || 'سرعت پخش' ) + ': ' + rate + '×' );
			try {
				window.localStorage.setItem( 'manacore-speed', String( rate ) );
			} catch ( e ) {}
			toast( ( i18n.playbackSpeed || 'سرعت پخش' ) + ': ' + rate + '×' );
		} );

		controls.appendChild( speed );

		if ( document.pictureInPictureEnabled && video.requestPictureInPicture ) {
			var pip = document.createElement( 'button' );
			pip.type = 'button';
			pip.className = 'manacore-btn is-secondary is-small';
			pip.setAttribute( 'data-player-pip', '1' );
			pip.setAttribute( 'aria-label', i18n.pictureInPicture || 'تصویر در تصویر' );
			pip.innerHTML = '<span aria-hidden="true">⧉</span> ' + ( i18n.pictureInPicture || 'تصویر در تصویر' );
			pip.addEventListener( 'click', function () {
				if ( document.pictureInPictureElement ) {
					document.exitPictureInPicture();
					return;
				}
				video.requestPictureInPicture().catch( function () {} );
			} );
			controls.appendChild( pip );
		}
	}

	/*
	 * کارت «قسمت بعدی» با شمارش معکوس. داده از `data-next-url` و
	 * `data-next-title` می‌آید که فقط برای قسمت‌های میانی سریال چاپ
	 * می‌شوند؛ برای فیلم هیچ کارتی ساخته نمی‌شود.
	 */
	var NEXT_DELAY = 8;

	function initNextEpisode( root, video ) {
		var nextUrl = root.getAttribute( 'data-next-url' );
		var frame = root.querySelector( '[data-player-frame]' );

		if ( ! nextUrl || ! frame || ! video ) {
			return;
		}

		var nextTitle = root.getAttribute( 'data-next-title' ) || '';
		var timer = null;

		function clearCard() {
			var card = frame.querySelector( '.player-next' );
			if ( card ) {
				card.parentNode.removeChild( card );
			}
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		video.addEventListener( 'ended', function () {
			clearCard();

			var card = document.createElement( 'div' );
			card.className = 'player-next';
			card.setAttribute( 'role', 'status' );
			card.setAttribute( 'aria-live', 'polite' );

			var label = document.createElement( 'p' );
			label.className = 'player-next__label';
			label.textContent = i18n.nextEpisode || 'قسمت بعدی';

			var title = document.createElement( 'strong' );
			title.textContent = nextTitle;

			var countdown = document.createElement( 'span' );
			countdown.className = 'player-next__count';
			countdown.setAttribute( 'data-player-next-count', '1' );

			var play = document.createElement( 'a' );
			play.className = 'manacore-btn is-primary is-small';
			play.setAttribute( 'data-player-next', '1' );
			play.href = nextUrl;
			play.textContent = i18n.playNext || 'پخش قسمت بعدی';

			var cancel = document.createElement( 'button' );
			cancel.type = 'button';
			cancel.className = 'manacore-btn is-secondary is-small';
			cancel.textContent = i18n.cancel || 'لغو';
			cancel.addEventListener( 'click', clearCard );

			card.appendChild( label );
			card.appendChild( title );
			card.appendChild( countdown );
			card.appendChild( play );
			card.appendChild( cancel );
			frame.appendChild( card );

			var left = NEXT_DELAY;
			countdown.textContent = left;

			timer = window.setInterval( function () {
				left -= 1;
				countdown.textContent = left;

				if ( left <= 0 ) {
					window.clearInterval( timer );
					timer = null;
					window.location.href = nextUrl;
				}
			}, 1000 );
		} );

		video.addEventListener( 'play', clearCard );
	}

	/* ---------------- گزارش خرابی لینک ---------------- */

	/*
	 * کاربر روی «خراب است؟» می‌زند، یک فرم کوچک کنارش باز می‌شود، لینک
	 * مشکل‌دار را از فهرست همان بخش انتخاب می‌کند و می‌فرستد. فهرست
	 * گزینه‌ها از خودِ جدول دانلود خوانده می‌شود (نه داده‌ی تکراری در
	 * مارک‌آپ)، پس هر تغییری در جدول خودکار اینجا هم دیده می‌شود.
	 */
	/*
	 * ---------------- درخواست فیلم/سریال ----------------
	 *
	 * فرم ثبت و رأی‌گیری هر دو روی REST کار می‌کنند. حالت‌های خطا از
	 * پیام خود سرور می‌آید (پیام فارسی، همان‌جا در PHP) تا متن‌ها دو جا
	 * تکرار نشوند؛ فقط وقتی پاسخ پیامی نداشت، متن جانشین نشان داده
	 * می‌شود.
	 */
	function initRequests() {
		var form = document.querySelector( '[data-manacore-request-form]' );

		if ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var status = form.querySelector( '[data-manacore-request-status]' );
				var submit = form.querySelector( 'button[type="submit"]' );
				var body = {
					title: valueOf( form, 'title' ),
					type: valueOf( form, 'type' ),
					year: parseInt( valueOf( form, 'year' ), 10 ) || 0,
					link: valueOf( form, 'link' ),
					note: valueOf( form, 'note' ),
					hp: valueOf( form, 'hp' ),
				};

				if ( body.title.length < 2 ) {
					setStatus( status, i18n.requestTitle || 'نام فیلم یا سریال را کامل بنویسید.', true );
					return;
				}

				if ( submit ) {
					submit.disabled = true;
				}

				setStatus( status, i18n.requestSending || 'در حال ارسال…', false );

				api( 'request', { method: 'POST', body: body } )
					.then( function ( json ) {
						setStatus( status, json && json.message ? json.message : i18n.requestDone || 'درخواست شما ثبت شد.', false );
						toast( json && json.message ? json.message : i18n.requestDone || 'درخواست شما ثبت شد.', false );

						form.reset();

						/* تخته‌ی همین صفحه تازه می‌شود تا رأی تازه دیده شود. */
						if ( json && json.id ) {
							refreshRequests();
						}
					} )
					.catch( function ( error ) {
						setStatus( status, ( error && error.message ) || i18n.requestError || 'ارسال نشد؛ دوباره تلاش کنید.', true );
					} )
					.then( function () {
						if ( submit ) {
							submit.disabled = false;
						}
					} );
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-manacore-vote]' ) : null;

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			if ( button.classList.contains( 'is-voted' ) ) {
				toast( i18n.requestVoted || 'شما پیش‌تر به این درخواست رأی داده‌اید.', false );
				return;
			}

			button.disabled = true;

			api( 'request-vote', {
				method: 'POST',
				body: { request_id: parseInt( button.getAttribute( 'data-request-id' ), 10 ) || 0 },
			} )
				.then( function ( json ) {
					var count = button.closest( '.manacore-request' ).querySelector( '[data-request-count]' );

					if ( count && json && typeof json.votes !== 'undefined' ) {
						count.textContent = json.votes;
					}

					button.classList.add( 'is-voted' );
					toast( json && json.message ? json.message : i18n.requestVoted || 'رأی ثبت شد.', false );
				} )
				.catch( function ( error ) {
					toast( ( error && error.message ) || i18n.requestError || 'رأی ثبت نشد.', true );
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );

		refreshRequests();
	}

	/* خواندن یک فیلد فرم با پیشوند `manacore_settings`-مانند؛ ساده و امن. */
	function valueOf( form, name ) {
		var field = form.elements[ name ];

		if ( ! field ) {
			return '';
		}

		return ( field.value || '' ).trim();
	}

	function setStatus( node, message, isError ) {
		if ( ! node ) {
			return;
		}

		node.textContent = message || '';
		node.classList.toggle( 'is-error', !! isError );
	}

	/*
	 * تخته‌ی درخواست‌ها را از همان مسیر REST سرور تازه می‌کند تا شمار
	 * رأی‌ها پس از ثبت درخواست/رأی همیشه واقعی باشد (بدون بازخوانی صفحه).
	 */
	function refreshRequests() {
		var board = document.querySelector( '[data-manacore-requests]' );
		var mine  = document.querySelector( '[data-manacore-mine]' );

		/*
		 * پنل «درخواست‌های من» هم بعد از ثبت درخواست تازه باید فهرست
		 * کامل را بگیرد؛ سرور همان تکه‌ی HTML را می‌فرستد، پس کاربر
		 * بدون بازخوانی صفحه Markup تازه می‌بیند.
		 */
		if ( mine ) {
			api( 'my-requests', { method: 'GET' } )
				.then( function ( json ) {
					if ( json && json.html ) {
						mine.innerHTML = json.html;
					}
				} )
				.catch( function () {} );
		}

		if ( ! board ) {
			return;
		}

		fetch( config.restUrl + 'requests?status=all&per_page=60' )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( json ) {
				if ( ! json || ! json.items ) {
					return;
				}

				json.items.forEach( function ( item ) {
					var node = board.querySelector( '[data-request-id="' + item.id + '"]' );

					if ( ! node ) {
						return;
					}

					var count = node.querySelector( '[data-request-count]' );

					if ( count ) {
						count.textContent = item.votes;
					}
				} );
			} )
			.catch( function () {
				/* تازه‌سازی تزئینی است؛ خطایش نباید چیزی را بشکند. */
			} );
	}

	function initReports() {
		document.querySelectorAll( '[data-manacore-report]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var section = button.closest( '.download-section' ) || document;
				var existing = section.querySelector( '.manacore-report-panel' );

				if ( existing ) {
					existing.parentNode.removeChild( existing );
					return;
				}

				var panel = document.createElement( 'form' );
				panel.className = 'manacore-report-panel';
				panel.setAttribute( 'aria-label', i18n.reportTitle || 'گزارش خرابی لینک' );

				var title = document.createElement( 'p' );
				title.className = 'manacore-report-panel__title';
				title.textContent = i18n.reportTitle || 'گزارش خرابی لینک';
				panel.appendChild( title );

				var select = document.createElement( 'select' );
				select.className = 'manacore-report-panel__link';
				select.setAttribute( 'aria-label', i18n.reportWhich || 'کدام لینک؟' );

				var rows = section.querySelectorAll( '.download-row' );
				Array.prototype.forEach.call( rows, function ( row ) {
					var link = row.querySelector( 'a[href]' );
					var quality = row.querySelector( '.quality-name b' );

					if ( ! link || ! link.getAttribute( 'href' ) ) {
						return;
					}

					var option = document.createElement( 'option' );
					option.value = link.getAttribute( 'href' );
					option.textContent = ( quality ? quality.textContent.trim() + ' — ' : '' ) + ( link.textContent.trim() || link.getAttribute( 'href' ) );
					option.setAttribute( 'data-quality', quality ? quality.textContent.trim() : '' );
					select.appendChild( option );
				} );

				/* اگر جدولی نبود، همان لینک روی خود دکمه به کار می‌رود. */
				if ( ! select.options.length ) {
					var fallback = document.createElement( 'option' );
					fallback.value = button.getAttribute( 'data-link-url' ) || '';
					fallback.textContent = i18n.reportGeneric || 'لینک این بخش';
					select.appendChild( fallback );
				}

				var reason = document.createElement( 'input' );
				reason.type = 'text';
				reason.maxLength = 180;
				reason.className = 'manacore-report-panel__reason';
				reason.placeholder = i18n.reportReason || 'توضیح کوتاه (اختیاری)';
				reason.setAttribute( 'aria-label', reason.placeholder );

				var submit = document.createElement( 'button' );
				submit.type = 'submit';
				submit.className = 'manacore-btn is-primary is-small';
				submit.textContent = i18n.reportSend || 'ارسال گزارش';

				var cancel = document.createElement( 'button' );
				cancel.type = 'button';
				cancel.className = 'manacore-btn is-secondary is-small';
				cancel.textContent = i18n.cancel || 'لغو';
				cancel.addEventListener( 'click', function () {
					panel.parentNode.removeChild( panel );
					button.focus();
				} );

				panel.appendChild( select );
				panel.appendChild( reason );
				panel.appendChild( submit );
				panel.appendChild( cancel );

				panel.addEventListener( 'submit', function ( event ) {
					event.preventDefault();

					var option = select.options[ select.selectedIndex ];
					var url = option ? option.value : '';

					if ( ! url ) {
						toast( i18n.reportWhich || 'کدام لینک؟', true );
						return;
					}

					submit.disabled = true;

					api( 'report-link', {
						method: 'POST',
						body: {
							post_id: parseInt( button.getAttribute( 'data-post-id' ), 10 ) || 0,
							link_url: url,
							link_label: option ? option.textContent.trim() : '',
							quality: option && option.getAttribute( 'data-quality' ) ? option.getAttribute( 'data-quality' ) : button.getAttribute( 'data-quality' ) || '',
							reason: reason.value,
						},
					} )
						.then( function ( response ) {
							toast( ( response && response.message ) || i18n.reportDone || 'گزارش ثبت شد. ممنون!', false );
							panel.parentNode.removeChild( panel );
							button.setAttribute( 'disabled', 'disabled' );
							button.classList.add( 'is-reported' );
						} )
						.catch( function ( error ) {
							submit.disabled = false;
							toast( ( error && error.message ) || i18n.error || 'خطایی رخ داد. دوباره تلاش کنید.', true );
						} );
				} );

				button.parentNode.appendChild( panel );
				select.focus();
			} );
		} );
	}

	/* ---------------- تب فصل‌ها ---------------- */
	function initSeasonTabs() {
		document.querySelectorAll( '[data-manacore-downloads]' ).forEach( function ( wrap ) {
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

	/* ---------------- فصل‌ها و قسمت‌ها (`.episode-card` مرجع) ---------------- */
	/*
	 * رفتار مرجع:
	 *   • نوار فصل‌ها + گزینش فصل، پنل همان فصل را نشان می‌دهد؛
	 *   • هر کارت قسمت با کلیک، جدول کیفیت‌های خودش را باز/بسته می‌کند
	 *     (`aria-expanded` و کلاس `expanded` روی کارت).
	 * حالت بدون جاوااسکریپت هم درست است: پنل فصل نخست از سمت سرور باز
	 * است و جدول قسمت نخست دیده می‌شود.
	 */
	function initEpisodeCards() {
		document.querySelectorAll( '[data-manacore-episodes]' ).forEach( function ( wrap ) {
			var tabs = Array.prototype.slice.call( wrap.querySelectorAll( '.season-tabs > button' ) );
			var select = wrap.querySelector( '[data-season-select]' );
			var panels = Array.prototype.slice.call( wrap.querySelectorAll( '[data-season-panel]' ) );

			function show( season ) {
				panels.forEach( function ( panel ) {
					var active = panel.getAttribute( 'data-season-panel' ) === String( season );
					panel.hidden = ! active;
					panel.classList.toggle( 'is-active', active );
				} );

				tabs.forEach( function ( tab ) {
					var active = tab.getAttribute( 'data-season' ) === String( season );
					tab.classList.toggle( 'active', active );
					tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				if ( select ) {
					select.value = String( season );
				}
			}

			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					show( tab.getAttribute( 'data-season' ) );
				} );
			} );

			if ( select ) {
				select.addEventListener( 'change', function () {
					show( select.value );
				} );
			}

			wrap.querySelectorAll( '.episode-toggle' ).forEach( function ( toggle ) {
				var card = toggle.closest( '.episode-card' );
				var body = card ? card.querySelector( '.episode-download' ) : null;

				if ( ! card || ! body ) {
					return;
				}

				toggle.addEventListener( 'click', function () {
					var open = ! card.classList.contains( 'expanded' );
					card.classList.toggle( 'expanded', open );
					body.hidden = ! open;
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				} );
			} );
		} );
	}

	/* ---------------- تب روزهای هفته (برنامه‌ی هفتگی) ---------------- */
	/*
	 * الگوی مرجع: نوار تب با role="tablist" و پنل هر روز. رفتار درست
	 * شامل چهار چیز است: انتخاب تب، پنهان/نمایان کردن پنل‌ها، به‌روزرسانی
	 * aria-selected و پیمایش با کلیدهای جهت‌دار. اگر جاوااسکریپت نباشد،
	 * پنل روزِ فعال از سمت سرور باز است و بقیه بسته می‌مانند.
	 */
	function initScheduleTabs() {
		/*
		 * دو چیدمان همین بلوک: `.manacore-schedule` (صفحه‌ی نخست) و
		 * `.schedule-panel.full-schedule` (برگه‌ی «برنامه پخش»). هر دو
		 * همان نوار تب و همان قرارداد `aria-controls` را دارند.
		 */
		document.querySelectorAll( '.manacore-schedule, .schedule-panel' ).forEach( function ( panel ) {
			var tabs = Array.prototype.slice.call( panel.querySelectorAll( '.manacore-day-tab' ) );
			if ( ! tabs.length ) {
				return;
			}

			function activate( tab ) {
				tabs.forEach( function ( other ) {
					var on = other === tab;
					other.classList.toggle( 'active', on );
					other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					other.setAttribute( 'tabindex', on ? '0' : '-1' );

					var box = document.getElementById( other.getAttribute( 'aria-controls' ) );
					if ( box ) {
						box.hidden = ! on;
					}
				} );
			}

			tabs.forEach( function ( tab, index ) {
				tab.addEventListener( 'click', function () {
					activate( tab );
				} );

				tab.addEventListener( 'keydown', function ( event ) {
					var step = 0;
					if ( event.key === 'ArrowLeft' ) {
						step = 1; // در چیدمان راست‌به‌چپ، چپ یعنی تب بعدی.
					} else if ( event.key === 'ArrowRight' ) {
						step = -1;
					} else if ( event.key === 'Home' ) {
						step = -index;
					} else if ( event.key === 'End' ) {
						step = tabs.length - 1 - index;
					} else {
						return;
					}

					event.preventDefault();
					var next = tabs[ ( index + step + tabs.length ) % tabs.length ];
					activate( next );
					next.focus();
				} );
			} );

			// تب فعال باید تنها تبِ قابل فوکوس باشد (قرارداد tablist).
			tabs.forEach( function ( tab ) {
				tab.setAttribute( 'tabindex', tab.classList.contains( 'active' ) ? '0' : '-1' );
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
	/*
	 * رفتار اسلایدر هم‌شکل مرجع `cinora/index.html`:
	 *   • جابه‌جایی با فلش‌ها، نقطه‌ها و کلیدهای جهت‌دار
	 *   • شمارنده‌ی «۰۱ / ۰۳» با هر تغییر تازه می‌شود
	 *   • پخش خودکار با توقف روی هاور و فوکوس، و توقف در تب پنهان
	 *   • چرخش ملایم کادر با حرکت ماوس (اگر کلید tilt روشن باشد)
	 * همه‌ی این‌ها با احترام به prefers-reduced-motion و بدون هیچ
	 * اندازه‌گیری/شمارشی که به آمار سایت اضافه کند.
	 */
	function initHero() {
		var finePointer = window.matchMedia( '(pointer: fine)' );

		document.querySelectorAll( '[data-manacore-hero]' ).forEach( function ( hero ) {
			var slides = Array.prototype.slice.call( hero.querySelectorAll( '[data-hero-slide]' ) );
			var dots = Array.prototype.slice.call( hero.querySelectorAll( '[data-hero-dot]' ) );
			if ( slides.length < 2 ) {
				return;
			}

			var current = 0;
			var timer = null;
			var paused = false;

			// تنظیمات از ویرایشگر بلوک روی خود عنصر نوشته می‌شود.
			var autoplay = '0' !== hero.getAttribute( 'data-autoplay' );
			var tilt = 'true' === hero.getAttribute( 'data-tilt' );
			var interval = parseInt( hero.getAttribute( 'data-interval' ), 10 );
			if ( ! interval || interval < 1500 ) {
				interval = 7000;
			}

			var prevBtn = hero.querySelector( '[data-hero-prev]' );
			var nextBtn = hero.querySelector( '[data-hero-next]' );
			var counter = hero.querySelector( '.manacore-hero-counter b' );

			function reduced() {
				return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			}

			function pad( value ) {
				return value < 10 ? '0' + value : String( value );
			}

			function show( index ) {
				current = ( index + slides.length ) % slides.length;
				slides.forEach( function ( slide, i ) {
					slide.classList.toggle( 'is-active', i === current );
				} );
				dots.forEach( function ( dot, i ) {
					dot.classList.toggle( 'is-active', i === current );
					dot.setAttribute( 'aria-pressed', i === current ? 'true' : 'false' );
				} );
				if ( counter ) {
					counter.textContent = pad( current + 1 );
				}
			}

			function start() {
				if ( ! autoplay || paused || document.hidden || reduced() ) {
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

			function go( index ) {
				show( index );
				start();
			}

			dots.forEach( function ( dot, index ) {
				dot.addEventListener( 'click', function () {
					go( index );
				} );
			} );

			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					go( current - 1 );
				} );
			}

			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					go( current + 1 );
				} );
			}

			// پیمایش با صفحه‌کلید برای دسترس‌پذیری.
			hero.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowLeft' === event.key ) {
					go( current + 1 );
				} else if ( 'ArrowRight' === event.key ) {
					go( current - 1 );
				}
			} );

			hero.addEventListener( 'focusin', function () {
				paused = true;
				stop();
			} );

			hero.addEventListener( 'focusout', function () {
				paused = false;
				start();
			} );

			hero.addEventListener( 'mouseenter', function () {
				paused = true;
				stop();
			} );

			hero.addEventListener( 'mouseleave', function () {
				paused = false;
				hero.style.removeProperty( '--mc-hero-tilt' );
				start();
			} );

			if ( tilt ) {
				hero.addEventListener( 'mousemove', function ( event ) {
					if ( reduced() || ! finePointer.matches ) {
						return;
					}
					var rect = hero.getBoundingClientRect();
					if ( ! rect.width ) {
						return;
					}
					var angle = ( ( event.clientX - rect.left ) / rect.width - .5 ) * .8;
					hero.style.setProperty( '--mc-hero-tilt', angle.toFixed( 3 ) + 'deg' );
				} );
			}

			/*
			 * تب پنهان نباید اسلاید عوض کند؛ هم مصرف بی‌دلیل را می‌گیرد و
			 * هم وقتی کاربر برمی‌گردد اسلاید عوض‌شده غافلگیرش نمی‌کند.
			 */
			document.addEventListener( 'visibilitychange', function () {
				if ( document.hidden ) {
					stop();
				} else {
					start();
				}
			} );

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
	/*
	 * نوار فیلتر: اگر دکمه‌ی «اعمال فیلتر» در قالب نباشد (`showSubmit:false`)،
	 * تغییر هر گزینشگر باید خودش فرم را بفرستد — وگرنه کل نوار کناری بی‌اثر
	 * می‌ماند. این نقص با سنجش واقعی پیدا شد: در `/movie/` پنج گزینشگر وجود
	 * داشت، هیچ دکمه‌ی ارسالی نبود، و تغییر «ژانر» نه نشانی را عوض می‌کرد و نه
	 * نتیجه را (۱۲ کارت پیش و پس). مرجع هم پالایشش را بی‌درنگ اعمال می‌کند.
	 */
	function initFilterForm() {
		document.querySelectorAll( '.manacore-filter-form' ).forEach( function ( form ) {
			if ( form.querySelector( 'button[type="submit"], input[type="submit"]' ) ) {
				return; // کاربر دکمه‌ی «اعمال فیلتر» را دارد؛ رفتار دستی می‌ماند.
			}

			/*
			 * ارسال خودکار: هر کنترلِ تغییردهنده‌ای که خودش دکمه‌ی ارسال
			 * ندارد. مرجع هم همین رفتار را دارد (هر تیک/گزینش بی‌درنگ
			 * فهرست را عوض می‌کند) — گزینشگر، تیک‌باکس ژانر و کلید دوبله
			 * بی‌درنگ، ولی لغزنده‌ی امتیاز با ۳۰۰ms درنگ تا با هر پیکسلِ
			 * کشیدن، درخواست تازه فرستاده نشود.
			 */
			var submit = function () {
				if ( typeof form.requestSubmit === 'function' ) {
					form.requestSubmit();
				} else {
					form.submit();
				}
			};

			form.querySelectorAll( 'select' ).forEach( function ( select ) {
				select.addEventListener( 'change', submit );
			} );

			form.querySelectorAll( 'input[type="checkbox"], input[type="radio"]' ).forEach( function ( box ) {
				box.addEventListener( 'change', submit );
			} );

			var range = form.querySelector( '.rating-range' );
			if ( range ) {
				var timer = null;

				var paint = function () {
					var label = form.querySelector( '#rating-label' );

					if ( ! label ) {
						return;
					}

					var value = parseInt( range.value, 10 ) || 0;
					var all   = range.getAttribute( 'data-all-label' ) || '';

					label.textContent = value > 0
						? String( value ).replace( /\d/g, function ( digit ) {
							return '۰۱۲۳۴۵۶۷۸۹'[ parseInt( digit, 10 ) ];
						} ) + '+'
						: all;
				};

				range.addEventListener( 'input', function () {
					paint();

					if ( timer ) {
						window.clearTimeout( timer );
					}

					timer = window.setTimeout( function () {
						timer = null;
						submit();
					}, 300 );
				} );
			}
		} );
	}

	/*
	 * نوار مرور برگه‌ی کشف (`.browse-toolbar` مرجع).
	 *
	 * سه کار می‌کند و هر سه، همان رفتار `browse.js` مرجع است:
	 *   ۱) جستجوی زنده: با هر تغییر متن (با تأخیر کوتاه) فرم را می‌فرستد.
	 *   ۲) صافی نوع: دکمه‌ی نوع را در ورودی پنهان می‌گذارد و فرم را می‌فرستد
	 *      (سمت سرور صالِ ‌فهرست، شمارش و صفحه‌بندی را می‌دهد).
	 *   ۳) حالت نمایش: شبکه/فهرست را روی همان شبکه عوض می‌کند و وضعیت
	 *      دکمه‌ها را هم هم‌گام نگه می‌دارد.
	 *
	 * فرم بدون دکمه‌ی ارسال است، پس رفتار دستیِ `initFilterForm()` اینجا
	 * دخالت نمی‌کند (آن تابع به `.manacore-filter-form` و `select` بسنده
	 * می‌کند).
	 */
	function initBrowseToolbar() {
		var toolbar = document.querySelector( '[data-manacore-browse-toolbar]' );
		if ( ! toolbar ) {
			return;
		}

		function submit() {
			if ( typeof toolbar.requestSubmit === 'function' ) {
				toolbar.requestSubmit();
			} else {
				toolbar.submit();
			}
		}

		var search = toolbar.querySelector( '.browse-search > input' );
		var clear  = toolbar.querySelector( '#clear-search' );
		var timer  = null;

		if ( search ) {
			search.addEventListener( 'input', function () {
				if ( clear ) {
					clear.hidden = ! search.value;
				}
				window.clearTimeout( timer );
				// تأخیر کوتاه تا هر حرف یک درخواست نسازد (مرجع: بی‌درنگ).
				timer = window.setTimeout( submit, 450 );
			} );

			// Enter هم بلافاصله می‌فرستد.
			search.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					window.clearTimeout( timer );
					event.preventDefault();
					submit();
				}
			} );
		}

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				if ( ! search ) {
					return;
				}
				search.value = '';
				clear.hidden = true;
				window.clearTimeout( timer );
				submit();
			} );
		}

		var sort = toolbar.querySelector( '#sort' );
		if ( sort ) {
			sort.addEventListener( 'change', submit );
		}

		// صافی نوع: ورودی پنهانِ همراهِ دکمه‌ها.
		var typeField = toolbar.querySelector( 'input[type="hidden"][name="type"]' );
		var typeGroup = toolbar.querySelector( '[data-manacore-type-tabs]' );

		if ( typeField && typeGroup ) {
			typeGroup.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( 'button[data-type]' );
				if ( ! button ) {
					return;
				}
				typeField.value = button.getAttribute( 'data-type' ) || 'all';
				submit();
			} );
		}

		// حالت نمایش: کلاس `list-layout` روی شبکه‌ی همان صفحه.
		var modes = toolbar.querySelectorAll( '[data-mode]' );
		if ( modes.length ) {
			var grid = document.querySelector( '.manacore-titles-block .manacore-grid-cards' );

			modes.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					modes.forEach( function ( other ) {
						var active = other === button;
						other.classList.toggle( 'active', active );
						other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );

					if ( grid ) {
						grid.classList.toggle( 'list-layout', 'list' === button.getAttribute( 'data-mode' ) );
					}
				} );
			} );
		}

		// دکمه‌ی «فیلترها» در موبایل: همان `.open` مرجع روی سایدبار.
		var trigger = toolbar.querySelector( '#mobile-filter' );
		if ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var sidebar = document.getElementById( trigger.getAttribute( 'aria-controls' ) || '' );
				if ( ! sidebar ) {
					return;
				}
				var open = sidebar.classList.toggle( 'open' );
				trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}
	}

	/*
	 * «داستان‌های بیشتر» (`.load-more-zone` مرجع).
	 *
	 * پیوند صفحه‌ی بعد را می‌گیرد، کارت‌هایش را به انتهای همین شبکه
	 * می‌چسباند (رفتار مرجع: افزودن، نه جایگزینی) و پیوند تازه‌ای برای
	 * صفحه‌ی بعد می‌گذارد؛ در پایان همان پیام مرجع را نشان می‌دهد. اگر
	 * جاوااسکریپت نباشد، همان پیوند یک صفحه‌بندی معمولی است و کار می‌کند.
	 */
	function initLoadMore() {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-manacore-load-more]' );
			if ( ! link || link.getAttribute( 'aria-busy' ) === 'true' ) {
				return;
			}

			var zone = link.closest( '.load-more-zone' );
			var grid = document.querySelector( '.manacore-titles-block .manacore-grid-cards' );
			var block = grid ? grid.closest( '.manacore-titles-block' ) : null;

			if ( ! zone || ! grid || ! window.fetch ) {
				return; // بدون جاوااسکریپت/مرورگر قدیمی: پیوند خودش کار می‌کند.
			}

			event.preventDefault();
			link.setAttribute( 'aria-busy', 'true' );

			window.fetch( link.href, { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.text();
				} )
				.then( function ( html ) {
					var parsed = new window.DOMParser().parseFromString( html, 'text/html' );
					var page   = parsed.querySelector( '.manacore-titles-block .manacore-grid-cards' );
					var next   = parsed.querySelector( '[data-manacore-load-more]' );

					if ( ! page ) {
						window.location.href = link.href;
						return;
					}

					/*
					 * `children` یک مجموعه‌ی زنده است؛ اگر همان را پیمایش
					 * کنیم و هر گره را به شبکه‌ی مقصد بچسبانیم، گره از
					 * مجموعه‌ی مبدأ حذف می‌شود و ایندکس‌ها جابه‌جا
					 * می‌شوند — نتیجه، جاافتادن هر کارت دوم بود
					 * (سنجیده‌شده: صفحه‌ی دوم با دو کارت، فقط یکی را
					 * می‌افزود). پس نخست یک رونوشت ساکن می‌گیریم.
					 */
					var incoming = Array.prototype.slice.call( page.children );

					incoming.forEach( function ( card ) {
						grid.appendChild( card );
					} );

					if ( next ) {
						link.setAttribute( 'href', next.getAttribute( 'href' ) );
						link.removeAttribute( 'aria-busy' );
						// شمارش کارت‌های افزوده‌شده به خواننده‌ی صفحه.
						link.setAttribute( 'data-manacore-loaded', String( page.children.length ) );
					} else if ( block ) {
						var done = parsed.querySelector( '.load-more-zone' );
						zone.innerHTML = done ? done.innerHTML : '';
					} else {
						zone.innerHTML = '';
					}
				} )
				.catch( function () {
					window.location.href = link.href;
				} );
		} );
	}

	/*
	 * تب‌های نوع سرصفحه‌ی بخش (`.section-tabs` مرجع): شبکه‌ی همان بلوک را
	 * سمت کاربر پالایش می‌کنند — دقیقاً همان کاری که `home.js` مرجع در بخش
	 * «این روزها، روی بورس» می‌کند (کاتالوگ را بر اساس نوع می‌پالاید و
	 * دوباره می‌کشد). اینجا کارت‌ها از قبل رندر شده‌اند، پس فقط نشان
	 * می‌خورند و نمایش‌شان عوض می‌شود؛ «همه» همه را برمی‌گرداند.
	 */
	function initSectionTypeTabs() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.manacore-block-head .section-tabs > button[data-type]' );
			if ( ! button ) {
				return;
			}

			var tablist = button.closest( '.section-tabs' );
			var block   = button.closest( '.manacore-titles-block' );
			var grid    = block ? block.querySelector( '.manacore-grid-cards' ) : null;

			if ( ! grid ) {
				return;
			}

			var type = button.getAttribute( 'data-type' ) || 'all';

			tablist.querySelectorAll( 'button[data-type]' ).forEach( function ( tab ) {
				var isActive = tab === button;
				tab.classList.toggle( 'active', isActive );
				tab.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			} );

			grid.querySelectorAll( '.manacore-card' ).forEach( function ( card ) {
				var cardType = card.getAttribute( 'data-manacore-type' ) || '';
				var hide     = 'all' !== type && cardType !== type;

				card.classList.toggle( 'is-filtered-out', hide );
				if ( hide ) {
					card.setAttribute( 'aria-hidden', 'true' );
				} else {
					card.removeAttribute( 'aria-hidden' );
				}
			} );
		} );
	}

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


	/*
	 * «پخش زنده» — هم‌ارز `cinora/assets/js/live.js`.
	 *
	 * سه رفتار: (۱) ساعت زنده‌ی منطقه‌ی زمانیِ تنظیم‌شده در بلوک، (۲) جابه‌جایی
	 * کانال **بدون بازخوانی صفحه** از نقطه‌ی REST، (۳) کلید صدا و تمام‌صفحه و
	 * دکمه‌ی تلاش دوباره.
	 *
	 * قاعده‌ی مهم: هر کارت کانال یک **پیوند واقعی** (`?channel=slug`) است، پس
	 * اگر جاوااسکریپت نباشد یا درخواست شکست بخورد، کاربر با همان پیوند به
	 * نسخه‌ی سرور-محور می‌رود؛ هیچ‌وقت «کارت مرده» نمی‌بینیم.
	 */
	/*
	 * صافی درجای چهره‌ها — هم‌ارز `cast.js` مرجع.
	 *
	 * جست‌وجو هم‌زمان روی نام فارسی و لاتین می‌گردد و دکمه‌های نقش روی
	 * `data-role` هر کارت. همه‌ی کارت‌ها از سرور رندر شده‌اند، پس بدون
	 * جاوااسکریپت هم برگه کامل است و این تابع فقط آن را پالایش می‌کند.
	 */
	function initPeopleFilter() {
		var toolbar = document.querySelector( '[data-manacore-people-toolbar]' );

		if ( ! toolbar ) {
			return;
		}

		var grids = document.querySelectorAll( '[data-manacore-people-grid]' );

		if ( ! grids.length ) {
			return;
		}

		var search = toolbar.querySelector( '[data-manacore-people-search]' );
		var buttons = toolbar.querySelectorAll( '[data-manacore-people-roles] button' );
		var status = document.querySelector( '[data-manacore-people-count]' );
		var role = 'all';

		var faDigits = function ( value ) {
			return String( value ).replace( /\d/g, function ( digit ) {
				return '۰۱۲۳۴۵۶۷۸۹'.charAt( parseInt( digit, 10 ) );
			} );
		};

		var apply = function () {
			var term = search ? search.value.trim().toLowerCase() : '';
			var total = 0;

			Array.prototype.forEach.call( grids, function ( grid ) {
				var cards = grid.querySelectorAll( '.person-card' );
				var visible = 0;

				Array.prototype.forEach.call( cards, function ( card ) {
					var haystack = ( card.getAttribute( 'data-search' ) || '' ).toLowerCase();
					var cardRole = card.getAttribute( 'data-role' ) || '';
					var match = ( '' === term || haystack.indexOf( term ) !== -1 ) &&
						( 'all' === role || cardRole.split( ' ' ).indexOf( role ) !== -1 );

					card.hidden = ! match;

					if ( match ) {
						visible++;
					}
				} );

				var empty = grid.querySelector( '.empty-state' );

				if ( empty ) {
					empty.hidden = visible > 0;
				}

				total += visible;
			} );

			if ( status ) {
				var template = status.getAttribute( 'data-count-template' ) || '{count}';

				status.textContent = template.replace( '{count}', faDigits( total ) );
			}
		};

		if ( search ) {
			search.addEventListener( 'input', apply );
		}

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				role = button.getAttribute( 'data-role' ) || 'all';

				Array.prototype.forEach.call( buttons, function ( other ) {
					var active = other === button;

					other.classList.toggle( 'active', active );
					other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );

				apply();
			} );
		} );

		apply();
	}

	/*
	 * پالایش برجای کارت‌های مقاله با تب‌های دسته (برگه‌ی «سینورامگ»).
	 *
	 * مرجع (`magazine.js`) با کلیک روی هر تب، شبکه را از نو می‌سازد؛ ما
	 * همان رفتار را روی کارت‌های رندرشده‌ی سرور اجرا می‌کنیم تا بدون
	 * جاوااسکریپت هم همه‌ی مقاله‌ها دیده شوند. تب‌ها `data-category` و
	 * کارت‌ها همان نامک‌ها را در `data-category` دارند.
	 */
	function initArticleFilter() {
		var grids = document.querySelectorAll( '[data-manacore-article-filter]' );

		if ( ! grids.length ) {
			return;
		}

		Array.prototype.forEach.call( grids, function ( grid ) {
			var header = grid.closest( '.manacore-magazine' ) || grid.parentElement;
			var tabs   = header ? header.querySelectorAll( '.section-tabs button' ) : [];
			var empty  = grid.querySelector( '.empty-state' );

			if ( ! tabs.length ) {
				return;
			}

			var apply = function ( value ) {
				var visible = 0;

				Array.prototype.forEach.call( grid.querySelectorAll( '.article-card' ), function ( card ) {
					var slugs = ( card.getAttribute( 'data-category' ) || '' ).split( /\s+/ );
					var match = 'all' === value || slugs.indexOf( value ) !== -1;

					card.hidden = ! match;

					if ( match ) {
						visible++;
					}
				} );

				if ( empty ) {
					empty.hidden = visible > 0;
				}
			};

			Array.prototype.forEach.call( tabs, function ( tab ) {
				tab.addEventListener( 'click', function () {
					Array.prototype.forEach.call( tabs, function ( other ) {
						var active = other === tab;

						other.classList.toggle( 'active', active );
						other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );

					apply( tab.getAttribute( 'data-category' ) || 'all' );
				} );
			} );
		} );
	}

	function initLivePlayer() {
		var clocks = document.querySelectorAll( '[data-manacore-clock]' );

		Array.prototype.forEach.call( clocks, function ( node ) {
			var target = node.querySelector( 'b' );
			var zone   = node.getAttribute( 'data-timezone' ) || 'Asia/Tehran';

			if ( ! target ) {
				return;
			}

			var tick = function () {
				var now = new Date();
				var text;

				try {
					text = new Intl.DateTimeFormat( 'fa-IR', {
						timeZone: zone,
						hour: '2-digit',
						minute: '2-digit',
					} ).format( now );
				} catch ( error ) {
					/* منطقه‌ی زمانی نامعتبر: ساعت محلی همان مرورگر. */
					text = now.getHours() + ':' + String( now.getMinutes() ).padStart( 2, '0' );
				}

				/* ارقام لاتین → فارسی، مثل همه‌ی اعداد رابط کاربری. */
				target.textContent = String( text ).replace( /\d/g, function ( digit ) {
					return '۰۱۲۳۴۵۶۷۸۹'.charAt( parseInt( digit, 10 ) );
				} );
			};

			tick();
			window.setInterval( tick, 30000 );
		} );

		var player = document.querySelector( '[data-manacore-live]' );

		if ( ! player ) {
			return;
		}

		var video   = player.querySelector( '#live-video' );
		var title   = player.querySelector( '#live-title' );
		var sub     = player.querySelector( '#live-subtitle' );
		var muteBtn = player.querySelector( '#mute-video' );
		var fullBtn = player.querySelector( '#fullscreen-video' );
		var retry   = player.querySelector( '#retry-video' );
		var error   = player.querySelector( '#video-error' );
		var cards   = document.querySelectorAll( '[data-channel]' );

		var i18n = ( window.manaCore && window.manaCore.i18n ) || {};

		var setMute = function ( muted ) {
			if ( ! video ) {
				return;
			}

			video.muted = muted;

			if ( muteBtn ) {
				muteBtn.setAttribute( 'aria-pressed', muted ? 'true' : 'false' );
				muteBtn.setAttribute(
					'aria-label',
					muted ? ( i18n.unmute || 'روشن کردن صدا' ) : ( i18n.mute || 'بی‌صدا کردن' )
				);
			}
		};

		var markActive = function ( slug ) {
			Array.prototype.forEach.call( cards, function ( card ) {
				var active = card.getAttribute( 'data-channel' ) === slug;

				card.classList.toggle( 'active', active );

				if ( active ) {
					card.setAttribute( 'aria-current', 'true' );
				} else {
					card.removeAttribute( 'aria-current' );
				}
			} );
		};

		Array.prototype.forEach.call( cards, function ( card ) {
			card.addEventListener( 'click', function ( event ) {
				var slug     = card.getAttribute( 'data-channel' );
				var restUrl  = window.manaCore && window.manaCore.restUrl;

				if ( ! slug || ! restUrl || ! video ) {
					return;
				}

				/*
				 * فقط کلیک‌های ساده‌ی چپ بدون کلید میان‌بر: باز کردن در تب
				 * تازه یا دانلود باید همان رفتار مرورگر بماند.
				 */
				if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0 ) {
					return;
				}

				event.preventDefault();

				window.fetch( restUrl + 'live/' + encodeURIComponent( slug ), {
					credentials: 'same-origin',
					headers: { Accept: 'application/json' },
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( 'live ' + response.status );
						}
						return response.json();
					} )
					.then( function ( data ) {
						if ( title ) {
							title.textContent = data.title || data.name || '';
						}

						if ( sub ) {
							sub.textContent = data.subtitle || '';
						}

						if ( data.poster ) {
							video.setAttribute( 'poster', data.poster );
						}

						if ( data.video ) {
							video.setAttribute( 'src', data.video );
						} else {
							video.removeAttribute( 'src' );
						}

						if ( error ) {
							error.classList.add( 'hidden' );
						}

						try {
							video.load();
							video.play().catch( function () {} );
						} catch ( e ) {
							/* مرورگر ویدئو را نپذیرفت؛ قاب خالی می‌ماند. */
						}

						var logo = player.querySelector( '.live-channel-logo' );

						if ( logo && data.iconSvg ) {
							logo.innerHTML = data.iconSvg;
						}

						markActive( slug );

						/* نشانی هم هم‌زمان می‌شود تا اشتراک‌گذاری درست باشد. */
						if ( window.history && window.history.replaceState ) {
							var url = new URL( window.location.href );

							url.searchParams.set( 'channel', slug );
							window.history.replaceState( {}, '', url.toString() );
						}
					} )
					.catch( function () {
						/*
						 * شکست درخواست = کانال عوض نمی‌شود، پس کاربر را با
						 * همان پیوند به نسخه‌ی سرور-محور می‌فرستیم.
						 */
						window.location.href = card.getAttribute( 'href' );
					} );
			} );
		} );

		if ( muteBtn ) {
			muteBtn.addEventListener( 'click', function () {
				setMute( ! ( video && video.muted ) );
			} );
		}

		if ( fullBtn && video ) {
			fullBtn.addEventListener( 'click', function () {
				if ( video.requestFullscreen ) {
					video.requestFullscreen().catch( function () {} );
				}
			} );
		}

		if ( video ) {
			setMute( video.muted );

			video.addEventListener( 'error', function () {
				if ( error ) {
					error.classList.remove( 'hidden' );
				}
			} );

			if ( retry ) {
				retry.addEventListener( 'click', function () {
					if ( error ) {
						error.classList.add( 'hidden' );
					}

					try {
						video.load();
						video.play().catch( function () {} );
					} catch ( e ) {
						/* مرورگر پشتیبانی نکرد. */
					}
				} );
			}
		}
	}

	/* ---------------- برگه‌ی حساب کاربری ---------------- */

	/**
	 * جابه‌جایی تب‌های برگه‌ی حساب — هم‌رفتار با `account.js` مرجع:
	 * پنل‌ها با `hidden` باز/بسته می‌شوند و نشانی با `?tab=` به‌روز می‌شود،
	 * بدون بازخوانی صفحه. ردیف‌های تب **پیوند واقعی**‌اند، پس با
	 * جاوااسکریپت خاموش هم هر تب بازشدنی است.
	 */
	function initAccountTabs() {
		var links  = Array.prototype.slice.call( document.querySelectorAll( '[data-tab]' ) );
		var panels = Array.prototype.slice.call( document.querySelectorAll( '[data-panel]' ) );

		if ( ! links.length || ! panels.length ) {
			return;
		}

		var show = function ( tab, updateUrl ) {
			var target = null;

			panels.forEach( function ( panel ) {
				var on = panel.getAttribute( 'data-panel' ) === tab;
				panel.hidden = ! on;
				if ( on ) {
					target = panel;
				}
			} );

			links.forEach( function ( link ) {
				var on = link.getAttribute( 'data-tab' ) === tab;
				link.classList.toggle( 'active', on );
				if ( on ) {
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );

			if ( updateUrl ) {
				try {
					var url = new URL( window.location.href );
					url.searchParams.set( 'tab', tab );
					url.searchParams.delete( 'notice' );
					window.history.replaceState( null, '', url.toString() );
				} catch ( e ) {
					// آدرس‌های نامتعارف: بی‌خیال به‌روزرسانی نشانی.
				}
			}

			return target;
		};

		var activate = function ( link, event, key ) {
			var tab = link.getAttribute( key );

			if ( ! tab || ! document.querySelector( '[data-panel="' + tab + '"]' ) ) {
				return;
			}

			event.preventDefault();
			var panel = show( tab, true );

			/*
			 * جابه‌جایی تب، «رفتن به بخش تازه» است؛ پس فوکوس به نخستین
			 * سرتیتر همان پنل می‌رود تا کاربر صفحه‌خوان هم بفهمد کجاست
			 * (بدون اسکرول، تا رفتار مرجع عوض نشود).
			 */
			if ( panel ) {
				var head = panel.querySelector( 'h2, h3' );
				if ( head ) {
					head.setAttribute( 'tabindex', '-1' );
					head.focus( { preventScroll: true } );
				}
			}
		};

		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				activate( link, event, 'data-tab' );
			} );
		} );

		/*
		 * میان‌برهای تب: مرجع آن‌ها را با `data-tab-link` نشانه می‌زند
		 * (مثل «کشف سلیقه من ‹» در بنر خوش‌آمد) و همان تب را باز می‌کند.
		 * جدا از ردیف‌های `data-tab` نگه داشته می‌شوند تا شمارش ردیف‌های
		 * ستون کنار و وضعیت `aria-current` فقط به همان ردیف‌ها بماند.
		 */
		Array.prototype.slice.call( document.querySelectorAll( '[data-tab-link]' ) ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				activate( link, event, 'data-tab-link' );
			} );
		} );

		/* پنلِ تبِ نشانی، هم‌خوان با آنچه سرور رندر کرده است. */
		var params = new URLSearchParams( window.location.search );
		var tab    = params.get( 'tab' );
		if ( tab && document.querySelector( '[data-panel="' + tab + '"]' ) ) {
			show( tab, false );
		}
	}

	/**
	 * پیام‌های فرم‌های برگه‌ی حساب (`?notice=…` پس از POST/Redirect/GET).
	 */
	function initAccountNotices() {
		var params = new URLSearchParams( window.location.search );
		var notice = params.get( 'notice' );

		if ( ! notice ) {
			return;
		}

		var messages = i18n.account || {};
		var message  = messages[ notice ];

		if ( ! message ) {
			return;
		}

		toast( message, /error|mismatch|wrong|big|type/i.test( notice ) );

		try {
			var url = new URL( window.location.href );
			url.searchParams.delete( 'notice' );
			window.history.replaceState( null, '', url.toString() );
		} catch ( e ) {
			// نادیده.
		}
	}

	/**
	 * عکس پروفایل: انتخاب فایل، همان لحظه فرم را می‌فرستد (مرجع هم
	 * بی‌درنگ بعد از انتخاب فایل عکس را ذخیره می‌کند).
	 */
	function initAccountAvatar() {
		var input = document.getElementById( 'avatar-file' );

		if ( ! input || ! input.form ) {
			return;
		}

		input.addEventListener( 'change', function () {
			var file = input.files && input.files[ 0 ];

			if ( ! file ) {
				return;
			}

			var max = ( i18n.account && i18n.account.avatarMax ) || 5242880;

			if ( file.size > max ) {
				toast( ( i18n.account && i18n.account.avatarTooBig ) || i18n.error, true );
				input.value = '';
				return;
			}

			if ( ! /^image\/(png|jpe?g|webp)$/.test( file.type ) ) {
				toast( ( i18n.account && i18n.account.avatarType ) || i18n.error, true );
				input.value = '';
				return;
			}

			if ( typeof input.form.requestSubmit === 'function' ) {
				input.form.requestSubmit();
			} else {
				input.form.submit();
			}
		} );
	}

	/**
	 * ثبت پیشرفت پخش در پروفایل کاربر (سرور)، جدا از کش محلی.
	 *
	 * مرجع پیشرفت را فقط در `localStorage` نگه می‌داشت؛ در محصول، تب
	 * «تاریخچه تماشا» و تحلیل‌های برگه‌ی حساب از همین داده ساخته می‌شوند.
	 *
	 * @param {number} postId  شناسه‌ی اثر.
	 * @param {number} percent درصد تماشا.
	 * @param {number} seconds ثانیه‌ی جاری.
	 */
	function pushServerProgress( postId, percent, seconds ) {
		if ( ! config.loggedIn || ! postId ) {
			return;
		}

		api( 'progress', {
			method: 'POST',
			body: {
				post_id: postId,
				percent: percent,
				minutes: Math.floor( ( seconds || 0 ) / 60 ),
			},
		} ).catch( function () {
			// ثبت پیشرفت، مسیر بحرانی نیست؛ شکست آن به کاربر نشان داده نمی‌شود.
		} );
	}


	/**
	 * برگه‌ی مقاله — نوار پیشرفت مطالعه، فهرست فصل‌ها و دکمه‌ی کپی لینک.
	 *
	 * قرارداد اجرایی مرجع (`article.js`) عیناً پیاده شده است:
	 *   - درصد = فاصله‌ی بالای متن از بالای دید، تقسیم بر
	 *     «ارتفاع متن منهای نصف ارتفاع دید» و سقف ۱۰۰.
	 *   - فصل فعال، آخرین فصلی است که بالای آن از ۱۸۰px گذشته باشد.
	 * همه‌ی محاسبه‌ها تدریجی (Progressive Enhancement) هستند: نبودِ
	 * جاوااسکریپت هیچ‌چیز را پنهان نمی‌کند و متن کامل خوانده می‌شود.
	 */
	function initArticlePage() {
		var card = document.querySelector( '[data-manacore-reading]' );

		if ( ! card ) {
			return;
		}

		var body    = document.querySelector( '.article-body' );
		var bar     = card.querySelector( '[data-manacore-progress]' );
		var percent = card.querySelector( '[data-manacore-percent]' );
		var links   = Array.prototype.slice.call( card.querySelectorAll( '[data-chapter]' ) );
		var fa      = function ( value ) {
			return String( value ).replace( /\d/g, function ( d ) {
				return '۰۱۲۳۴۵۶۷۸۹'.charAt( +d );
			} );
		};

		if ( body && bar && percent ) {
			var update = function () {
				var range = Math.max( 1, body.scrollHeight - window.innerHeight / 2 );
				var raw   = -body.getBoundingClientRect().top / range * 100;
				var pct   = Math.max( 0, Math.min( 100, raw ) );

				bar.style.width = pct + '%';
				percent.textContent = fa( Math.round( pct ) );

				var active = 0;

				links.forEach( function ( link ) {
					var chapter = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );

					if ( chapter && chapter.getBoundingClientRect().top < 180 ) {
						active = Number( link.getAttribute( 'data-chapter' ) );
					}
				} );

				links.forEach( function ( link ) {
					var isActive = Number( link.getAttribute( 'data-chapter' ) ) === active;
					link.classList.toggle( 'active', isActive );

					if ( isActive ) {
						link.setAttribute( 'aria-current', 'true' );
					} else {
						link.removeAttribute( 'aria-current' );
					}
				} );
			};

			window.addEventListener( 'scroll', update, { passive: true } );
			window.addEventListener( 'resize', update );
			update();
		}

		/* دکمه‌ی «کپی لینک»: بازخورد در همان دکمه، نه پنجره‌ی هشدار. */
		var button = document.querySelector( '[data-manacore-copy-link]' );

		if ( ! button ) {
			return;
		}

		var original = button.getAttribute( 'aria-label' ) || '';

		var flash = function ( label ) {
			button.classList.add( 'is-copied' );
			button.setAttribute( 'aria-label', label );

			window.setTimeout( function () {
				button.classList.remove( 'is-copied' );
				button.setAttribute( 'aria-label', original );
			}, 2000 );
		};

		button.addEventListener( 'click', function () {
			var url = window.location.href;
			var done = function () {
				flash( 'لینک مقاله کپی شد' );
			};

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( url ).then( done, function () {
					flash( 'کپی نشد؛ آدرس را دستی بردارید' );
				} );
				return;
			}

			var field = document.createElement( 'textarea' );
			field.value = url;
			field.setAttribute( 'readonly', 'readonly' );
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild( field );
			field.select();

			try {
				document.execCommand( 'copy' );
				done();
			} catch ( error ) {
				flash( 'کپی نشد؛ آدرس را دستی بردارید' );
			}

			document.body.removeChild( field );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initCopy();
		initRating();
		initWatchlist();
		initPlayer();
		initSeasonTabs();
		initEpisodeCards();
		initPlayerPage();
		initScheduleTabs();
		initSearch();
		initHero();
		initDownloadTracking();
		initReports();
		initRequests();
		initSectionTypeTabs();
		initFilterForm();
		initBrowseToolbar();
		initLoadMore();
		initColorMode();
		initAccountTabs();
		initAccountNotices();
		initAccountAvatar();
		initLivePlayer();
		initPeopleFilter();
		initArticleFilter();
		initArticlePage();
	} );
} )();

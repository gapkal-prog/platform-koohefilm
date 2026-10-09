/**
 * ابزارهای مشترک انتخابگرها در پیشخوان: جعبه‌ی پیشنهاد با کیبورد (combobox)،
 * درخواست REST و کمک‌های متن.
 *
 * عوامل (admin-people.js) و آثار مجموعه/کانال (admin-works.js) روی همین ماژول
 * ساخته می‌شوند تا رفتار کیبورد، وضعیت خطا و دسترس‌پذیری یکسان بماند.
 *
 * همه‌ی متن‌های برگرفته از داده با textContent ساخته می‌شوند (بدون innerHTML).
 * وابسته به window.manaCoreAdmin که در class-assets.php محلی‌سازی می‌شود.
 *
 * API عمومی: window.ManaCorePicker
 *   t(key, fallback), format(text, value), el(tag, className, text),
 *   api(path, params), combobox(input, box, options), digits(value)
 */
(function () {
	'use strict';

	var cfg = window.manaCoreAdmin || {};

	function t( key, fallback ) {
		return ( cfg.i18n && cfg.i18n[ key ] ) || fallback;
	}

	function format( text, value ) {
		return String( text ).replace( '%s', value );
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	}

	/**
	 * عدد را با ارقام فارسی برمی‌گرداند.
	 *
	 * @param {number|string} value عدد.
	 * @return {string}
	 */
	function digits( value ) {
		return String( value ).replace( /\d/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt( Number( d ) );
		} );
	}

	/**
	 * GET به مسیر REST افزونه با پارامترهای جست‌وجو. خروجی: JSON.
	 *
	 * @param {string} path   مسیر نسبی به manacore/v1/ (مثلاً «works»).
	 * @param {Object} params پارامترهای query.
	 * @return {Promise<Object>}
	 */
	function api( path, params ) {
		var url = new URL( ( cfg.restUrl || '' ) + path, window.location.href );

		Object.keys( params || {} ).forEach( function ( key ) {
			url.searchParams.set( key, params[ key ] );
		} );

		return fetch( url.toString(), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce || '' },
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} );
	}

	/**
	 * جعبه‌ی پیشنهاد با کیبورد برای یک ورودی.
	 *
	 * opts:
	 *  - search(query)  → Promise<Array>  منبع نتایج.
	 *  - option(item)   → {label, meta, badge, className, disabled}  نمای هر نتیجه.
	 *  - onPick(item)   انتخاب یک نتیجه.
	 *  - onFree(query)  (allowFree) افزودن مقدار آزاد.
	 *  - browse         بارگذاری فهرست با فوکوس روی ورودی خالی.
	 *  - allowFree      نمایش گزینه‌ی «افزودن به‌عنوان مقدار آزاد».
	 *  - emptyText      متن وقتی نتیجه‌ای نیست.
	 *
	 * @param {HTMLInputElement} input ورودی.
	 * @param {HTMLElement}      box   جعبه‌ی نتایج.
	 * @param {Object}           opts  تنظیمات.
	 * @return {{close: Function}}
	 */
	function combobox( input, box, opts ) {
		var state = { seq: 0, timer: 0, active: -1 };

		if ( ! box.id ) {
			box.id = 'manacore-picker-' + Math.random().toString( 36 ).slice( 2, 9 );
		}
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-controls', box.id );
		input.setAttribute( 'aria-expanded', 'false' );

		function view( item ) {
			return opts.option ? opts.option( item ) : { label: String( item ) };
		}

		function open() {
			box.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function close() {
			state.active = -1;
			box.hidden = true;
			box.textContent = '';
			input.setAttribute( 'aria-expanded', 'false' );
		}

		function options() {
			return Array.prototype.slice.call(
				box.querySelectorAll( '[role="option"]:not([aria-disabled="true"])' )
			);
		}

		function moveActive( step ) {
			var list = options();
			if ( ! list.length ) {
				return;
			}
			state.active = ( state.active + step + list.length ) % list.length;
			list.forEach( function ( node, index ) {
				var on = index === state.active;
				node.classList.toggle( 'is-active', on );
				node.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			if ( list[ state.active ] ) {
				list[ state.active ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function option( label, meta, onChoose, disabled ) {
			var node = el( 'button', 'manacore-picker-option' );
			node.type = 'button';
			node.setAttribute( 'role', 'option' );
			node.setAttribute( 'aria-selected', 'false' );
			node.appendChild( el( 'span', 'manacore-picker-name', label ) );
			if ( meta ) {
				node.appendChild( el( 'span', 'manacore-picker-meta', meta ) );
			}
			if ( disabled ) {
				node.setAttribute( 'aria-disabled', 'true' );
				node.classList.add( 'is-disabled' );
			}
			node.addEventListener( 'mousedown', function ( event ) {
				// نگه‌داشتن فوکوس تا blur پیش از انتخاب، جعبه را نبندد.
				event.preventDefault();
				if ( ! disabled ) {
					onChoose();
				}
			} );
			return node;
		}

		function message( text ) {
			box.appendChild( el( 'p', 'manacore-picker-empty', text ) );
		}

		function render( items, query ) {
			box.textContent = '';

			items.forEach( function ( item ) {
				var data = view( item );
				var node = option(
					data.label,
					data.meta,
					function () {
						opts.onPick( item );
						close();
					},
					!! data.disabled
				);

				if ( data.className ) {
					node.classList.add( data.className );
				}
				if ( data.badge ) {
					node.appendChild( el( 'span', 'manacore-picker-badge', data.badge ) );
				}

				box.appendChild( node );
			} );

			var exact = items.some( function ( item ) {
				return String( view( item ).label ).toLowerCase() === query.toLowerCase();
			} );

			if ( query && opts.allowFree && ! exact ) {
				var free = option(
					format( t( 'addFree', 'افزودن «%s» به‌عنوان نام آزاد' ), query ),
					'',
					function () {
						opts.onFree( query );
						close();
					},
					false
				);
				free.classList.add( 'is-free' );
				box.appendChild( free );
			}

			if ( ! items.length && ! ( query && opts.allowFree ) ) {
				message( opts.emptyText || t( 'noResults', 'نتیجه‌ای پیدا نشد.' ) );
			}

			state.active = -1;
			open();
		}

		function load( query ) {
			var seq = ++state.seq;

			opts.search( query ).then( function ( items ) {
				if ( seq === state.seq ) {
					render( items, query );
				}
			} ).catch( function () {
				if ( seq === state.seq ) {
					box.textContent = '';
					message( t( 'searchError', 'خطا در جست‌وجو. دوباره تلاش کنید.' ) );
					open();
				}
			} );
		}

		input.addEventListener( 'input', function () {
			var query = input.value.trim();
			window.clearTimeout( state.timer );

			if ( ! query && ! opts.browse ) {
				close();
				return;
			}

			state.timer = window.setTimeout( function () {
				load( query );
			}, 200 );
		} );

		input.addEventListener( 'focus', function () {
			if ( opts.browse && ! input.value.trim() ) {
				load( '' );
			}
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var list = options();

			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				if ( box.hidden ) {
					load( input.value.trim() );
				} else {
					moveActive( 1 );
				}
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				moveActive( -1 );
			} else if ( 'Enter' === event.key ) {
				// Enter هرگز فرم پست را ارسال نکند.
				event.preventDefault();
				if ( state.active >= 0 && list[ state.active ] ) {
					list[ state.active ].dispatchEvent( new MouseEvent( 'mousedown' ) );
				} else if ( input.value.trim() && opts.allowFree ) {
					opts.onFree( input.value.trim() );
					close();
				}
			} else if ( 'Escape' === event.key ) {
				close();
			}
		} );

		input.addEventListener( 'blur', function () {
			window.setTimeout( close, 150 );
		} );

		return { close: close };
	}

	window.ManaCorePicker = {
		t: t,
		format: format,
		el: el,
		digits: digits,
		api: api,
		combobox: combobox,
	};
}());

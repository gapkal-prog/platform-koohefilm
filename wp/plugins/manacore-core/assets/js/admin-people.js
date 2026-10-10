/**
 * عوامل در پیشخوان: برچسب‌های کارگردان/نویسنده/تهیه‌کننده/آهنگساز، جست‌وجوی
 * عوامل ثبت‌شده (CPT person)، پنل جست‌وجوی تب «عوامل» و پیوند ردیف‌های بازیگران.
 *
 * همه‌ی متن‌های برگرفته از داده با textContent ساخته می‌شوند (بدون innerHTML).
 * وابسته به admin-picker.js (window.ManaCorePicker) که پیش از این فایل بار می‌شود.
 */
(function () {
	'use strict';

	var picker = window.ManaCorePicker;
	var MAX_ITEMS = 50;
	var t = picker.t;
	var format = picker.format;
	var el = picker.el;

	/**
	 * جست‌وجوی عوامل از REST. خروجی: آرایه‌ی عامل‌ها.
	 *
	 * @param {Object} params q، role، limit.
	 * @return {Promise<Array>}
	 */
	function searchPeople( params ) {
		return picker.api( 'people', params ).then( function ( data ) {
			return data && Array.isArray( data.items ) ? data.items : [];
		} );
	}

	/**
	 * متن کمکی هر گزینه: نام لاتین، نام اصلی، سال تولد، اهل کجا و نقش‌ها.
	 *
	 * @param {Object} item عامل.
	 * @return {string}
	 */
	function metaLine( item ) {
		var parts = [];

		if ( item.english ) {
			parts.push( item.english );
		}
		if ( item.original ) {
			parts.push( item.original );
		}
		if ( item.birth_year ) {
			parts.push( String( item.birth_year ) );
		}
		if ( item.birthplace ) {
			parts.push( item.birthplace );
		}
		if ( item.roles && item.roles.length ) {
			parts.push( item.roles.join( ' · ' ) );
		}

		return parts.join( ' — ' );
	}

	/**
	 * جعبه‌ی پیشنهاد عوامل: منبع REST و نمای مخصوص عامل روی combobox مشترک.
	 *
	 * @param {HTMLInputElement} input ورودی.
	 * @param {HTMLElement}      box   جعبه‌ی نتایج.
	 * @param {Object}           opts  role (رشته یا تابع)، browse، allowFree، onPick، onFree.
	 * @return {{close: Function}}
	 */
	function combobox( input, box, opts ) {
		function currentRole() {
			return typeof opts.role === 'function' ? opts.role() : ( opts.role || '' );
		}

		return picker.combobox( input, box, {
			browse: opts.browse,
			allowFree: opts.allowFree,
			onPick: opts.onPick,
			onFree: opts.onFree,
			search: function ( query ) {
				return searchPeople( {
					q: query,
					role: currentRole(),
					limit: 8,
				} );
			},
			option: function ( item ) {
				var data = { label: item.name, meta: metaLine( item ) };
				if ( item.role_match && currentRole() ) {
					data.badge = t( 'roleMatch', 'نقش مطابق' );
					data.className = 'is-role-match';
				}
				return data;
			},
		} );
	}

	/**
	 * فهرست عوامل یک نقش (برچسب‌ها).
	 *
	 * @param {HTMLElement} root ظرف data-people.
	 */
	function initPeopleField( root ) {
		var input = root.querySelector( '[data-people-input]' );
		var chips = root.querySelector( '[data-people-chips]' );
		var box   = root.querySelector( '[data-people-results]' );
		var value = root.querySelector( '[data-people-value]' );
		var role  = root.getAttribute( 'data-people' ) || '';
		var items = parse( value.value );

		function parse( raw ) {
			var data;
			try {
				data = JSON.parse( raw || '[]' );
			} catch ( error ) {
				data = String( raw || '' ).split( /[,،]+/ );
			}
			if ( ! Array.isArray( data ) ) {
				return [];
			}
			return data.map( function ( entry ) {
				var name = typeof entry === 'string' ? entry : ( entry && entry.name ) || '';
				return {
					name: String( name ).trim(),
					person_id: entry && entry.person_id ? parseInt( entry.person_id, 10 ) : 0,
				};
			} ).filter( function ( entry ) {
				return entry.name !== '';
			} );
		}

		function isDuplicate( item ) {
			return items.some( function ( existing ) {
				if ( item.person_id ) {
					return existing.person_id === item.person_id;
				}
				return ! existing.person_id && existing.name.toLowerCase() === item.name.toLowerCase();
			} );
		}

		function sync() {
			value.value = JSON.stringify( items );
			chips.textContent = '';

			items.forEach( function ( item, index ) {
				var chip = el( 'span', 'manacore-chip' + ( item.person_id ? ' is-linked' : '' ) );
				chip.appendChild( el( 'span', 'manacore-chip-name', item.name ) );

				if ( item.person_id ) {
					chip.title = t( 'linked', 'به صفحه‌ی عامل پیوند خورده' );
				}

				var remove = el( 'button', 'manacore-chip-remove' );
				remove.type = 'button';
				remove.setAttribute( 'aria-label', format( t( 'removeName', 'حذف %s' ), item.name ) );
				remove.appendChild( el( 'span', 'dashicons dashicons-no-alt' ) );
				remove.addEventListener( 'click', function () {
					items.splice( index, 1 );
					sync();
					input.focus();
				} );

				chip.appendChild( remove );
				chips.appendChild( chip );
			} );
		}

		function add( item ) {
			if ( items.length >= MAX_ITEMS || isDuplicate( item ) ) {
				return false;
			}
			items.push( {
				name: item.name,
				person_id: item.person_id ? parseInt( item.person_id, 10 ) : 0,
			} );
			sync();
			return true;
		}

		root.manaCorePeople = { add: add };

		combobox( input, box, {
			role: role,
			browse: true,
			allowFree: true,
			onPick: function ( item ) {
				add( { name: item.name, person_id: item.id } );
				input.value = '';
				input.focus();
			},
			onFree: function ( name ) {
				add( { name: name, person_id: 0 } );
				input.value = '';
				input.focus();
			},
		} );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Backspace' === event.key && '' === input.value && items.length ) {
				items.pop();
				sync();
			}
		} );

		sync();
	}

	/**
	 * پیوند یک ردیف بازیگر به عامل: نام، شناسه، تصویر و نشان وضعیت.
	 *
	 * @param {HTMLElement} row ردیف.
	 * @param {Object|null} item عامل؛ null یعنی جدا کردن.
	 */
	function linkCastRow( row, item ) {
		var idInput    = row.querySelector( 'input[name$="[person_id]"]' );
		var photoInput = row.querySelector( 'input[name$="[photo]"]' );
		var search     = row.querySelector( '[data-person-search]' );
		var badge      = row.querySelector( '[data-person-badge]' );

		if ( ! idInput || ! search ) {
			return;
		}

		idInput.value = item ? String( item.id ) : '';
		if ( item ) {
			search.value = item.name;
			if ( photoInput && ! photoInput.value && item.photo ) {
				photoInput.value = item.photo;
			}
		}
		if ( badge ) {
			badge.hidden = ! item;
		}
	}

	/**
	 * جست‌وجوی عامل در هر ردیف بازیگر (وقتی ورودی برای اولین بار فوکوس می‌گیرد).
	 *
	 * @param {HTMLInputElement} input ورودی نام.
	 */
	function bindCastPicker( input ) {
		var row = input.closest( '.manacore-repeater-row' );
		var box = row ? row.querySelector( '[data-people-results]' ) : null;

		if ( ! row || ! box ) {
			return;
		}

		input.dataset.personBound = '1';

		combobox( input, box, {
			role: 'cast',
			browse: false,
			allowFree: true,
			onPick: function ( item ) {
				linkCastRow( row, item );
			},
			onFree: function ( name ) {
				linkCastRow( row, null );
				input.value = name;
			},
		} );

		// تایپ دستی پیوند قبلی را می‌شکند؛ انتخاب از فهرست آن را دوباره می‌سازد.
		input.addEventListener( 'input', function () {
			var idInput = row.querySelector( 'input[name$="[person_id]"]' );
			if ( idInput && idInput.value ) {
				linkCastRow( row, null );
			}
		} );
	}

	/**
	 * فهرست جست‌وجوی تب «عوامل»: انتخاب عامل به نقش انتخابی اضافه می‌شود.
	 *
	 * @param {HTMLElement} root ظرف data-people-finder.
	 */
	function initFinder( root ) {
		var input  = root.querySelector( '[data-finder-input]' );
		var select = root.querySelector( '[data-finder-role]' );
		var box    = root.querySelector( '[data-finder-results]' );

		if ( ! input || ! select || ! box ) {
			return;
		}

		function addToRole( item ) {
			var role = select.value;

			if ( 'cast' === role ) {
				addCastRow( item );
				return;
			}

			var field = document.querySelector( '[data-people="' + role + '"]' );
			if ( ! field || ! field.manaCorePeople ) {
				box.textContent = '';
				box.appendChild( el( 'p', 'manacore-picker-empty', t( 'roleMissing', 'این نقش برای این نوع محتوا فعال نیست.' ) ) );
				box.hidden = false;
				return;
			}

			field.manaCorePeople.add( { name: item.name, person_id: item.id } );
			field.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
		}

		function addCastRow( item ) {
			var repeater = document.querySelector( '[data-repeater="manacore_cast"]' );
			if ( ! repeater ) {
				return;
			}

			var addBtn = repeater.querySelector( '.manacore-repeater-add' );
			if ( addBtn ) {
				addBtn.click();
			}

			var rows = repeater.querySelectorAll( '.manacore-repeater-row' );
			var row  = rows[ rows.length - 1 ];
			if ( row ) {
				linkCastRow( row, item );
				row.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
			}
		}

		combobox( input, box, {
			role: function () {
				return select.value;
			},
			browse: true,
			allowFree: false,
			onPick: addToRole,
			onFree: function () {},
		} );

		select.addEventListener( 'change', function () {
			if ( ! input.value.trim() ) {
				input.dispatchEvent( new Event( 'focus' ) );
			} else {
				input.dispatchEvent( new Event( 'input' ) );
			}
		} );
	}

	function init() {
		document.querySelectorAll( '[data-people]' ).forEach( function ( root ) {
			if ( root.querySelector( '[data-people-input]' ) && root.querySelector( '[data-people-value]' ) ) {
				initPeopleField( root );
			}
		} );

		document.querySelectorAll( '[data-people-finder]' ).forEach( initFinder );

		// ردیف‌های بازیگر، از جمله ردیف‌های تازه، هنگام نخستین فوکوس به جست‌وجو وصل می‌شوند.
		document.addEventListener( 'focusin', function ( event ) {
			var target = event.target;
			if ( target && target.matches && target.matches( '[data-person-search]' ) && ! target.dataset.personBound ) {
				bindCastPicker( target );
			}
		} );

		// پاک‌کردن پیوند از دکمه‌ی «جدا کردن».
		document.addEventListener( 'click', function ( event ) {
			var unlink = event.target.closest && event.target.closest( '[data-person-unlink]' );
			if ( ! unlink ) {
				return;
			}
			var row = unlink.closest( '.manacore-repeater-row' );
			if ( row ) {
				linkCastRow( row, null );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}());

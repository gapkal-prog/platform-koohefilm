/**
 * انتخابگرهای «اثر» در پیشخوان:
 *  - فهرست مرتب آثار مجموعه ([data-works-list]): جست‌وجو، افزودن، جابه‌جایی
 *    بالا/پایین، حذف، «افزودن آثار متصل» و «پاک کردن همه».
 *  - انتخاب یک اثر ([data-work-select]) برای فیلدهایی مانند «اثر در حال پخش».
 *
 * ترتیب نمایش همان ترتیب ردیف‌ها در DOM است و ورودی‌های مخفی هر ردیف به همان
 * ترتیب ارسال می‌شوند؛ بنابراین ترتیب ذخیره‌شده دقیقاً ترتیب فهرست است.
 *
 * همه‌ی متن‌های برگرفته از داده با textContent ساخته می‌شوند (بدون innerHTML).
 * وابسته به admin-picker.js (window.ManaCorePicker).
 */
(function () {
	'use strict';

	var picker = window.ManaCorePicker;
	var t      = picker.t;
	var format = picker.format;
	var el     = picker.el;
	var digits = picker.digits;

	var DEFAULT_MAX = 200;

	function parseJSON( raw, fallback ) {
		try {
			var data = JSON.parse( raw || '' );
			return data === null || data === undefined ? fallback : data;
		} catch ( error ) {
			return fallback;
		}
	}

	/**
	 * متن کمکی یک اثر: نوع، سال و وضعیت (وضعیت غیرمنتشرشده نشان داده می‌شود).
	 *
	 * @param {Object} item اثر.
	 * @return {string}
	 */
	function metaText( item ) {
		var parts = [];

		if ( item.type_label ) {
			parts.push( item.type_label );
		}
		if ( item.year ) {
			parts.push( digits( item.year ) );
		}
		if ( item.status && 'publish' !== item.status && item.status_label ) {
			parts.push( item.status_label );
		}

		return parts.join( ' · ' );
	}

	function titleOf( item ) {
		return item.title || t( 'untitled', 'بدون عنوان' );
	}

	/**
	 * فهرست مرتب آثار یک مجموعه.
	 *
	 * @param {HTMLElement} root ظرف data-works-list.
	 */
	function initList( root ) {
		var list      = root.querySelector( '[data-works-selected]' );
		var input     = root.querySelector( '[data-works-search]' );
		var typeSel   = root.querySelector( '[data-works-type]' );
		var box       = root.querySelector( '[data-works-results]' );
		var count     = root.querySelector( '[data-works-count]' );
		var empty     = root.querySelector( '[data-works-empty]' );
		var status    = root.querySelector( '[data-works-status]' );
		var importBtn = root.querySelector( '[data-works-import]' );
		var clearBtn  = root.querySelector( '[data-works-clear]' );
		var fallback  = root.querySelector( '[data-works-fallback]' );

		if ( ! list || ! input || ! box ) {
			return;
		}

		var name   = root.getAttribute( 'data-works-name' ) || 'manacore_collection_items[]';
		var max    = parseInt( root.getAttribute( 'data-max' ), 10 ) || DEFAULT_MAX;
		var items  = parseJSON( root.getAttribute( 'data-items' ), [] );
		var linked = parseJSON( root.getAttribute( 'data-linked' ), [] );

		// ورودی‌های بدون JS از فیلد پنهان خوانده می‌شوند؛ از این پس JS مالک فهرست است.
		if ( fallback && fallback.parentNode ) {
			fallback.parentNode.removeChild( fallback );
		}

		function ids() {
			return items.map( function ( item ) {
				return item.id;
			} );
		}

		function has( id ) {
			return items.some( function ( item ) {
				return item.id === id;
			} );
		}

		function say( text ) {
			if ( status ) {
				status.textContent = text;
			}
		}

		function focusRow( id, act ) {
			var row = list.querySelector( '[data-id="' + id + '"]' );
			var btn = row ? row.querySelector( '[data-act="' + act + '"]:not([disabled])' ) : null;

			if ( btn ) {
				btn.focus();
			} else {
				input.focus();
			}
		}

		function actionButton( act, label, aria, disabled ) {
			var btn = el( 'button', 'button-link manacore-works-action is-' + act, label );
			btn.type = 'button';
			btn.setAttribute( 'data-act', act );
			btn.setAttribute( 'aria-label', aria );
			btn.disabled = !! disabled;
			return btn;
		}

		function row( item, index ) {
			var title = titleOf( item );
			var li    = el( 'li', 'manacore-works-row' );
			li.setAttribute( 'data-id', String( item.id ) );

			li.appendChild( el( 'span', 'manacore-works-index', digits( index + 1 ) ) );

			if ( item.thumb ) {
				var img = el( 'img', 'manacore-works-thumb' );
				img.src     = item.thumb;
				img.alt     = '';
				img.loading = 'lazy';
				img.width   = 40;
				img.height  = 56;
				li.appendChild( img );
			} else {
				li.appendChild( el( 'span', 'manacore-works-thumb is-empty' ) );
			}

			var body = el( 'span', 'manacore-works-body' );
			body.appendChild( el( 'strong', 'manacore-works-title', title ) );
			if ( metaText( item ) ) {
				body.appendChild( el( 'small', 'manacore-works-meta', metaText( item ) ) );
			}
			li.appendChild( body );

			var actions = el( 'span', 'manacore-works-actions' );
			actions.appendChild( actionButton(
				'up',
				t( 'moveUpShort', 'بالا' ),
				format( t( 'moveUpWork', 'انتقال «%s» به بالا' ), title ),
				0 === index
			) );
			actions.appendChild( actionButton(
				'down',
				t( 'moveDownShort', 'پایین' ),
				format( t( 'moveDownWork', 'انتقال «%s» به پایین' ), title ),
				index === items.length - 1
			) );
			actions.appendChild( actionButton(
				'remove',
				t( 'removeShort', 'حذف' ),
				format( t( 'removeWork', 'حذف «%s» از مجموعه' ), title ),
				false
			) );
			li.appendChild( actions );

			// ورودی مخفی: ترتیب ارسال همان ترتیب ردیف‌هاست.
			var hidden = el( 'input' );
			hidden.type  = 'hidden';
			hidden.name  = name;
			hidden.value = String( item.id );
			li.appendChild( hidden );

			return li;
		}

		function availableLinked() {
			return linked.filter( function ( entry ) {
				return ! has( entry.id );
			} );
		}

		function render() {
			list.textContent = '';
			items.forEach( function ( item, index ) {
				list.appendChild( row( item, index ) );
			} );

			if ( count ) {
				count.textContent = format( t( 'countWorks', '%s اثر' ), digits( items.length ) );
			}
			if ( empty ) {
				empty.hidden = items.length > 0;
			}
			if ( clearBtn ) {
				clearBtn.disabled = items.length === 0;
			}
			if ( importBtn ) {
				var pending = availableLinked().length;
				importBtn.hidden = pending === 0;
				importBtn.textContent = format( t( 'importLinked', 'افزودن آثار متصل (%s)' ), digits( pending ) );
			}
		}

		function add( item ) {
			if ( has( item.id ) ) {
				return false;
			}
			if ( items.length >= max ) {
				say( format( t( 'maxWorks', 'حداکثر %s اثر در هر مجموعه مجاز است.' ), digits( max ) ) );
				return false;
			}
			items.push( {
				id: item.id,
				title: item.title,
				type: item.type,
				type_label: item.type_label,
				year: item.year,
				status: item.status,
				status_label: item.status_label,
				thumb: item.thumb,
			} );
			render();
			say( format( t( 'workAdded', '«%s» افزوده شد.' ), titleOf( item ) ) );
			return true;
		}

		list.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest && event.target.closest( '[data-act]' );
			if ( ! btn || btn.disabled ) {
				return;
			}

			var rowNode = btn.closest( '.manacore-works-row' );
			var id      = parseInt( rowNode ? rowNode.getAttribute( 'data-id' ) : '0', 10 );
			var index   = ids().indexOf( id );
			if ( index < 0 ) {
				return;
			}

			var act = btn.getAttribute( 'data-act' );
			var title = titleOf( items[ index ] );

			if ( 'remove' === act ) {
				items.splice( index, 1 );
				render();
				say( format( t( 'workRemoved', '«%s» حذف شد.' ), title ) );
				input.focus();
				return;
			}

			var target = 'up' === act ? index - 1 : index + 1;
			if ( target < 0 || target >= items.length ) {
				return;
			}
			var moved = items.splice( index, 1 )[ 0 ];
			items.splice( target, 0, moved );
			render();
			say( format( format( t( 'workMoved', '«%s» به موقعیت %s منتقل شد.' ), title ), digits( target + 1 ) ) );
			focusRow( id, act );
		} );

		picker.combobox( input, box, {
			browse: true,
			allowFree: false,
			emptyText: t( 'noWorks', 'اثری پیدا نشد.' ),
			search: function ( query ) {
				return picker.api( 'works', {
					q: query,
					type: typeSel ? typeSel.value : '',
					limit: 15,
					exclude: ids().join( ',' ),
				} ).then( function ( data ) {
					return data && Array.isArray( data.items ) ? data.items : [];
				} );
			},
			option: function ( item ) {
				var added = has( item.id );
				return {
					label: titleOf( item ),
					meta: metaText( item ),
					badge: added ? t( 'alreadyAdded', 'در فهرست' ) : '',
					disabled: added,
				};
			},
			onPick: function ( item ) {
				add( item );
				input.value = '';
				input.focus();
			},
			onFree: function () {},
		} );

		if ( typeSel ) {
			typeSel.addEventListener( 'change', function () {
				if ( input.value.trim() ) {
					input.dispatchEvent( new Event( 'input' ) );
				} else {
					input.dispatchEvent( new Event( 'focus' ) );
				}
			} );
		}

		if ( importBtn ) {
			importBtn.addEventListener( 'click', function () {
				var pending = availableLinked();
				var added   = 0;
				pending.some( function ( entry ) {
					if ( items.length >= max ) {
						return true;
					}
					items.push( {
						id: entry.id,
						title: entry.title,
						type: entry.type,
						type_label: entry.type_label,
						year: entry.year,
						status: entry.status,
						status_label: entry.status_label,
						thumb: entry.thumb,
					} );
					added++;
					return false;
				} );
				render();
				say( format( t( 'linkedAdded', '%s اثر متصل افزوده شد.' ), digits( added ) ) );
			} );
		}

		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( ! items.length ) {
					return;
				}
				if ( ! window.confirm( t( 'confirmClear', 'همه‌ی آثار این مجموعه از فهرست حذف شوند؟' ) ) ) {
					return;
				}
				items.length = 0;
				render();
				say( t( 'clearedWorks', 'فهرست آثار خالی شد.' ) );
				input.focus();
			} );
		}

		render();
	}

	/**
	 * انتخاب یک اثر (تک‌مقداری).
	 *
	 * @param {HTMLElement} root ظرف data-work-select.
	 */
	function initSingle( root ) {
		var input   = root.querySelector( '[data-work-search]' );
		var box     = root.querySelector( '[data-work-results]' );
		var value   = root.querySelector( '[data-work-value]' );
		var current = root.querySelector( '[data-work-current]' );
		var none    = root.querySelector( '[data-work-none]' );

		if ( ! input || ! box || ! value || ! current ) {
			return;
		}

		var selected = parseJSON( root.getAttribute( 'data-item' ), null );

		function render() {
			current.textContent = '';
			value.value = selected ? String( selected.id ) : '';

			if ( ! selected ) {
				current.hidden = true;
				if ( none ) {
					none.hidden = false;
				}
				return;
			}

			var title = titleOf( selected );
			var chip  = el( 'span', 'manacore-work-chip' );

			if ( selected.thumb ) {
				var img = el( 'img', 'manacore-works-thumb' );
				img.src     = selected.thumb;
				img.alt     = '';
				img.loading = 'lazy';
				img.width   = 32;
				img.height  = 44;
				chip.appendChild( img );
			}

			var body = el( 'span', 'manacore-works-body' );
			body.appendChild( el( 'strong', 'manacore-works-title', title ) );
			if ( metaText( selected ) ) {
				body.appendChild( el( 'small', 'manacore-works-meta', metaText( selected ) ) );
			}
			chip.appendChild( body );
			current.appendChild( chip );

			var remove = el( 'button', 'button-link manacore-works-action is-remove', t( 'clearWork', 'حذف انتخاب' ) );
			remove.type = 'button';
			remove.setAttribute( 'aria-label', format( t( 'removeItem', 'حذف «%s»' ), title ) );
			remove.addEventListener( 'click', function () {
				selected = null;
				render();
				input.focus();
			} );
			current.appendChild( remove );

			current.hidden = false;
			if ( none ) {
				none.hidden = true;
			}
		}

		picker.combobox( input, box, {
			browse: false,
			allowFree: false,
			emptyText: t( 'noWorks', 'اثری پیدا نشد.' ),
			search: function ( query ) {
				return picker.api( 'works', { q: query, limit: 12 } ).then( function ( data ) {
					return data && Array.isArray( data.items ) ? data.items : [];
				} );
			},
			option: function ( item ) {
				return { label: titleOf( item ), meta: metaText( item ) };
			},
			onPick: function ( item ) {
				selected = item;
				render();
				input.value = '';
				input.focus();
			},
			onFree: function () {},
		} );

		render();
	}

	function init() {
		document.querySelectorAll( '[data-works-list]' ).forEach( initList );
		document.querySelectorAll( '[data-work-select]' ).forEach( initSingle );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}());

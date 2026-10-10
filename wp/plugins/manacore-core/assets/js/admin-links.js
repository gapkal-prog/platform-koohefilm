/**
 * مدیریت گروه‌های لینک دانلود در متاباکس.
 *
 * @package ManaCore\Core
 */
( function () {
	'use strict';

	var i18n = ( window.manaCoreAdmin && window.manaCoreAdmin.i18n ) || {};

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	function uid( prefix ) {
		return prefix + '_' + Math.random().toString( 36 ).slice( 2, 10 );
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key === 'class' ) {
				node.className = attrs[ key ];
			} else if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else if ( key === 'html' ) {
				node.innerHTML = attrs[ key ];
			} else if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), attrs[ key ] );
			} else if ( attrs[ key ] !== null && attrs[ key ] !== undefined && attrs[ key ] !== false ) {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function selectField( options, value, onChange, placeholder ) {
		var select = el( 'select', { class: 'manacore-input' } );
		if ( placeholder ) {
			select.appendChild( el( 'option', { value: '', text: placeholder } ) );
		}
		Object.keys( options ).forEach( function ( key ) {
			var opt = el( 'option', { value: key, text: options[ key ] } );
			if ( String( value ) === String( key ) ) {
				opt.selected = true;
			}
			select.appendChild( opt );
		} );
		select.addEventListener( 'change', function () {
			onChange( select.value );
		} );
		return select;
	}

	function textField( value, placeholder, onChange, type ) {
		var input = el( 'input', {
			type: type || 'text',
			class: 'manacore-input',
			value: value === undefined || value === null ? '' : value,
			placeholder: placeholder || '',
		} );
		input.addEventListener( 'input', function () {
			onChange( input.value );
		} );
		return input;
	}

	/**
	 * نشانه‌گذاری یک کنترل با نام فیلد (برای به‌روزرسانی مستقیم هنگام سنجش).
	 *
	 * @param {HTMLElement} node کنترل.
	 * @param {string}      name نام فیلد.
	 * @return {HTMLElement}
	 */
	function tag( node, name ) {
		node.setAttribute( 'data-field', name );
		return node;
	}

	/**
	 * سازنده‌ی رابط مدیریت لینک.
	 *
	 * @param {HTMLElement} root ریشه.
	 */
	function LinksApp( root ) {
		this.root = root;
		this.input = root.querySelector( '[data-links-input]' );
		this.list = root.querySelector( '[data-groups]' );
		this.counter = root.querySelector( '[data-links-count]' );
		this.isSerial = root.getAttribute( 'data-is-serial' ) === '1';
		this.qualities = this.parse( root.getAttribute( 'data-qualities' ) ) || {};
		this.languages = this.parse( root.getAttribute( 'data-languages' ) ) || {};
		this.types = this.parse( root.getAttribute( 'data-types' ) ) || {};
		this.data = this.parse( root.getAttribute( 'data-value' ) ) || [];
		this.inspectUrl = root.getAttribute( 'data-inspect-url' ) || '';
		this.nonce = root.getAttribute( 'data-nonce' ) || '';
		this.collapsed = {};

		this.bindToolbar();
		this.render();
	}

	LinksApp.prototype.parse = function ( raw ) {
		if ( ! raw ) {
			return null;
		}
		try {
			return JSON.parse( raw );
		} catch ( e ) {
			return null;
		}
	};

	LinksApp.prototype.sync = function () {
		this.input.value = JSON.stringify( this.data );
		var total = this.data.reduce( function ( sum, group ) {
			return sum + ( group.items ? group.items.length : 0 );
		}, 0 );
		if ( this.counter ) {
			this.counter.textContent = t( 'totalLinks', 'مجموع لینک‌ها:' ) + ' ' + total;
		}
	};

	LinksApp.prototype.bindToolbar = function () {
		var self = this;
		this.root.querySelectorAll( '[data-action]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var action = btn.getAttribute( 'data-action' );
				if ( action === 'add-group' ) {
					self.addGroup();
				} else if ( action === 'add-season-group' ) {
					self.addSeasonGroup();
				} else if ( action === 'bulk-import' ) {
					self.bulkImport();
				} else if ( action === 'collapse-all' ) {
					self.toggleAll();
				}
			} );
		} );
	};

	LinksApp.prototype.addGroup = function ( preset ) {
		var group = Object.assign(
			{
				id: uid( 'grp' ),
				title: '',
				season: '',
				quality: '',
				language: '',
				encoder: '',
				size: '',
				note: '',
				premium: false,
				items: [],
			},
			preset || {}
		);
		this.data.push( group );
		this.render();
		this.sync();
	};

	LinksApp.prototype.addSeasonGroup = function () {
		var season = window.prompt( t( 'askSeason', 'شماره فصل را وارد کنید:' ), String( this.data.length + 1 ) );
		if ( season === null ) {
			return;
		}
		var count = window.prompt( t( 'askEpisodes', 'تعداد قسمت‌های این فصل:' ), '12' );
		if ( count === null ) {
			return;
		}
		var total = parseInt( count, 10 ) || 0;
		var items = [];
		for ( var i = 1; i <= total; i++ ) {
			items.push( {
				id: uid( 'lnk' ),
				label: t( 'episode', 'قسمت' ) + ' ' + i,
				episode: i,
				url: '',
				type: 'direct',
				size: '',
				quality: '',
				encoder: '',
				note: '',
			} );
		}
		this.addGroup( {
			title: t( 'season', 'فصل' ) + ' ' + season,
			season: parseInt( season, 10 ) || '',
			items: items,
		} );
	};

	LinksApp.prototype.bulkImport = function () {
		var help = t(
			'bulkHelp',
			'هر خط یک لینک. قالب: عنوان | آدرس | شماره قسمت (اختیاری) | حجم (اختیاری)'
		);
		var raw = window.prompt( help, '' );
		if ( ! raw ) {
			return;
		}
		var items = [];
		raw.split( /\r?\n/ ).forEach( function ( line ) {
			line = line.trim();
			if ( ! line ) {
				return;
			}
			var parts = line.split( '|' ).map( function ( p ) {
				return p.trim();
			} );
			var url = '';
			var label = '';
			if ( parts.length === 1 ) {
				url = parts[ 0 ];
				label = '';
			} else {
				label = parts[ 0 ];
				url = parts[ 1 ];
			}
			if ( ! url ) {
				return;
			}
			items.push( {
				id: uid( 'lnk' ),
				label: label,
				episode: parts[ 2 ] ? parseInt( parts[ 2 ], 10 ) : '',
				url: url,
				type: url.indexOf( 'magnet:' ) === 0 ? 'magnet' : 'direct',
				size: parts[ 3 ] || '',
				quality: '',
				encoder: '',
				note: '',
			} );
		} );
		if ( ! items.length ) {
			return;
		}
		this.addGroup( { title: t( 'importedGroup', 'گروه وارد شده' ), items: items } );
	};

	LinksApp.prototype.toggleAll = function () {
		var self = this;
		var anyOpen = this.data.some( function ( group ) {
			return ! self.collapsed[ group.id ];
		} );
		this.data.forEach( function ( group ) {
			self.collapsed[ group.id ] = anyOpen;
		} );
		this.render();
	};

	LinksApp.prototype.move = function ( index, delta ) {
		var target = index + delta;
		if ( target < 0 || target >= this.data.length ) {
			return;
		}
		var tmp = this.data[ index ];
		this.data[ index ] = this.data[ target ];
		this.data[ target ] = tmp;
		this.render();
		this.sync();
	};

	LinksApp.prototype.render = function () {
		var self = this;
		this.list.innerHTML = '';

		if ( ! this.data.length ) {
			this.list.appendChild(
				el( 'p', {
					class: 'manacore-empty',
					text: t( 'noGroups', 'هنوز گروهی اضافه نشده است. با دکمه‌ی «افزودن گروه لینک» شروع کنید.' ),
				} )
			);
		}

		this.data.forEach( function ( group, index ) {
			self.list.appendChild( self.renderGroup( group, index ) );
		} );

		this.sync();
	};

	LinksApp.prototype.renderGroup = function ( group, index ) {
		var self = this;
		var isCollapsed = !! this.collapsed[ group.id ];

		var header = el( 'div', { class: 'manacore-group-header' }, [
			el( 'button', {
				type: 'button',
				class: 'manacore-collapse dashicons dashicons-arrow-' + ( isCollapsed ? 'down' : 'up' ) + '-alt2',
				'aria-label': t( 'toggle', 'باز/بسته' ),
				onclick: function () {
					self.collapsed[ group.id ] = ! isCollapsed;
					self.render();
				},
			} ),
			el( 'strong', {
				class: 'manacore-group-title',
				text: group.title || t( 'untitledGroup', 'گروه بدون عنوان' ),
			} ),
			el( 'span', {
				class: 'manacore-badge',
				text: ( group.items ? group.items.length : 0 ) + ' ' + t( 'linkUnit', 'لینک' ),
			} ),
			el( 'span', { class: 'manacore-spacer' } ),
			el( 'button', {
				type: 'button',
				class: 'button-link manacore-icon-btn dashicons dashicons-arrow-up-alt',
				'aria-label': t( 'moveUp', 'بالا' ),
				onclick: function () {
					self.move( index, -1 );
				},
			} ),
			el( 'button', {
				type: 'button',
				class: 'button-link manacore-icon-btn dashicons dashicons-arrow-down-alt',
				'aria-label': t( 'moveDown', 'پایین' ),
				onclick: function () {
					self.move( index, 1 );
				},
			} ),
			el( 'button', {
				type: 'button',
				class: 'button-link manacore-icon-btn manacore-danger dashicons dashicons-trash',
				'aria-label': t( 'removeGroup', 'حذف گروه' ),
				onclick: function () {
					if ( window.confirm( t( 'confirmGroup', 'این گروه و همه‌ی لینک‌های آن حذف شود؟' ) ) ) {
						self.data.splice( index, 1 );
						self.render();
					}
				},
			} ),
		] );

		var meta = el( 'div', { class: 'manacore-group-meta' }, [
			this.labeled(
				t( 'groupTitle', 'عنوان گروه' ),
				textField( group.title, t( 'groupTitlePh', 'مثلا: فصل ۱ — کیفیت ۱۰۸۰p' ), function ( v ) {
					group.title = v;
					self.sync();
					var titleNode = header.querySelector( '.manacore-group-title' );
					if ( titleNode ) {
						titleNode.textContent = v || t( 'untitledGroup', 'گروه بدون عنوان' );
					}
				} )
			),
			this.isSerial
				? this.labeled(
						t( 'season', 'فصل' ),
						textField(
							group.season,
							'1',
							function ( v ) {
								group.season = v === '' ? '' : parseInt( v, 10 );
								self.sync();
							},
							'number'
						)
				  )
				: null,
			this.labeled(
				t( 'quality', 'کیفیت' ),
				selectField(
					this.qualities,
					group.quality,
					function ( v ) {
						group.quality = v;
						self.sync();
					},
					t( 'choose', '— انتخاب —' )
				)
			),
			this.labeled(
				t( 'language', 'زبان / دوبله' ),
				selectField(
					this.languages,
					group.language,
					function ( v ) {
						group.language = v;
						self.sync();
					},
					t( 'choose', '— انتخاب —' )
				)
			),
			this.labeled(
				t( 'encoder', 'انکودر' ),
				textField( group.encoder, 'PSA, YTS ...', function ( v ) {
					group.encoder = v;
					self.sync();
				} )
			),
			this.labeled(
				t( 'size', 'حجم' ),
				textField( group.size, '1.2GB', function ( v ) {
					group.size = v;
					self.sync();
				} )
			),
			this.labeled(
				t( 'note', 'توضیح' ),
				textField( group.note, '', function ( v ) {
					group.note = v;
					self.sync();
				} )
			),
			this.checkbox( t( 'premiumGroup', 'فقط اعضای اشتراکی' ), group.premium, function ( v ) {
				group.premium = v;
				self.sync();
			} ),
		] );

		var itemsWrap = el( 'div', { class: 'manacore-items' } );
		( group.items || [] ).forEach( function ( item, itemIndex ) {
			itemsWrap.appendChild( self.renderItem( group, item, itemIndex ) );
		} );

		var actions = el( 'div', { class: 'manacore-group-actions' }, [
			el( 'button', {
				type: 'button',
				class: 'button',
				html: '<span class="dashicons dashicons-plus-alt2"></span> ' + t( 'addLink', 'افزودن لینک' ),
				onclick: function () {
					group.items = group.items || [];
					group.items.push( {
						id: uid( 'lnk' ),
						label: '',
						episode: '',
						url: '',
						type: 'direct',
						size: '',
						quality: '',
						encoder: '',
						note: '',
					} );
					self.render();
				},
			} ),
			el( 'button', {
				type: 'button',
				class: 'button',
				html: '<span class="dashicons dashicons-admin-page"></span> ' + t( 'duplicate', 'تکثیر گروه' ),
				onclick: function () {
					var copy = JSON.parse( JSON.stringify( group ) );
					copy.id = uid( 'grp' );
					copy.title = ( copy.title || '' ) + ' ' + t( 'copySuffix', '(کپی)' );
					( copy.items || [] ).forEach( function ( it ) {
						it.id = uid( 'lnk' );
					} );
					self.data.splice( index + 1, 0, copy );
					self.render();
				},
			} ),
		] );

		var body = el( 'div', { class: 'manacore-group-body' }, [ meta, itemsWrap, actions ] );
		if ( isCollapsed ) {
			body.hidden = true;
		}

		return el( 'div', { class: 'manacore-group' }, [ header, body ] );
	};

	LinksApp.prototype.renderItem = function ( group, item, itemIndex ) {
		var self = this;
		var urlInput = tag(
			textField( item.url, 'https://...', function ( v ) {
				item.url = v;
				self.sync();
			}, 'text' ),
			'url'
		);

		var node = el( 'div', { class: 'manacore-item', 'data-item-id': item.id }, [
			el( 'span', { class: 'manacore-item-index', text: String( itemIndex + 1 ) } ),
			this.labeled(
				t( 'label', 'عنوان' ),
				tag(
					textField( item.label, t( 'labelPh', 'قسمت ۱ / لینک مستقیم' ), function ( v ) {
						item.label = v;
						self.sync();
					} ),
					'label'
				)
			),
			this.isSerial
				? this.labeled(
						t( 'episodeNo', 'قسمت' ),
						tag(
							textField(
								item.episode,
								'1',
								function ( v ) {
									item.episode = v === '' ? '' : parseInt( v, 10 );
									self.sync();
								},
								'number'
							),
							'episode'
						)
				  )
				: null,
			this.labeled( t( 'url', 'آدرس لینک' ), urlInput, 'is-grow' ),
			this.labeled(
				t( 'type', 'نوع' ),
				tag(
					selectField( this.types, item.type, function ( v ) {
						item.type = v;
						self.sync();
					} ),
					'type'
				)
			),
			this.labeled(
				t( 'qualityOverride', 'کیفیت' ),
				tag(
					selectField(
						this.qualities,
						item.quality,
						function ( v ) {
							item.quality = v;
							self.sync();
						},
						t( 'inherit', 'ارث از گروه' )
					),
					'quality'
				)
			),
			this.labeled(
				t( 'languageOverride', 'زبان / دوبله' ),
				tag(
					selectField(
						this.languages,
						item.language,
						function ( v ) {
							item.language = v;
							self.sync();
						},
						t( 'inherit', 'ارث از گروه' )
					),
					'language'
				)
			),
			this.labeled(
				t( 'encoderOverride', 'انکودر' ),
				tag(
					textField( item.encoder, t( 'inherit', 'ارث از گروه' ), function ( v ) {
						item.encoder = v;
						self.sync();
					} ),
					'encoder'
				)
			),
			this.sizeField( group, item ),
			el( 'button', {
				type: 'button',
				class: 'button-link manacore-icon-btn manacore-danger dashicons dashicons-no-alt',
				'aria-label': t( 'removeLink', 'حذف لینک' ),
				onclick: function () {
					group.items.splice( itemIndex, 1 );
					self.render();
				},
			} ),
		] );

		/* سنجش خودکار (بدون شبکه) وقتی نشانی تغییر و از فیلد خارج می‌شود. */
		urlInput.addEventListener( 'change', function () {
			self.autoInspect( group, item );
		} );

		return node;
	};

	/**
	 * فیلد حجم به‌همراه دکمه‌ی «سنجش از لینک».
	 *
	 * دکمه حجم را از میزبان می‌سنجد (درخواست شبکه‌ای، با محدودیت نرخ)، و
	 * بقیه‌ی مشخصات را فقط وقتی خالی‌اند پر می‌کند.
	 *
	 * @param {Object} group گروه لینک.
	 * @param {Object} item  ردیف لینک.
	 * @return {HTMLElement}
	 */
	LinksApp.prototype.sizeField = function ( group, item ) {
		var self = this;
		var input = tag(
			textField( item.size, '350MB', function ( v ) {
				item.size = v;
				self.sync();
			} ),
			'size'
		);
		var message = el( 'span', { class: 'manacore-inspect-msg', role: 'status', 'aria-live': 'polite' } );
		var button = el( 'button', {
			type: 'button',
			class: 'button button-small manacore-inspect',
			text: t( 'inspect', 'سنجش از لینک' ),
			title: t( 'inspectHint', 'حجم را از میزبان می‌سنجد؛ کیفیت، زبان، انکودر و نام را هم از نشانی می‌خواند.' ),
			onclick: function () {
				self.inspect( group, item, button, message );
			},
		} );

		input.setAttribute( 'aria-label', t( 'size', 'حجم' ) );

		return el( 'div', { class: 'manacore-inline-field is-grow manacore-size-field' }, [
			el( 'span', { class: 'manacore-inline-label', text: t( 'size', 'حجم' ) } ),
			el( 'span', { class: 'manacore-inspect-row' }, [ input, button ] ),
			message,
		] );
	};

	/**
	 * نشانه‌ی ردیف لینک در فهرست فعلی.
	 *
	 * @param {Object} item ردیف لینک.
	 * @return {HTMLElement|null}
	 */
	LinksApp.prototype.nodeFor = function ( item ) {
		var nodes = this.list.querySelectorAll( '[data-item-id]' );

		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].getAttribute( 'data-item-id' ) === item.id ) {
				return nodes[ i ];
			}
		}

		return null;
	};

	/**
	 * درخواست سنجش به مسیر REST.
	 *
	 * @param {Object}  item  ردیف لینک.
	 * @param {boolean} probe true = سنجش شبکه‌ای (حجم)، false = فقط تجزیه‌ی نشانی.
	 * @return {Promise<Object>}
	 */
	LinksApp.prototype.fetchInspection = function ( item, probe ) {
		var query = 'url=' + encodeURIComponent( item.url.trim() ) + '&probe=' + ( probe ? '1' : '0' );

		if ( item.label ) {
			query += '&label=' + encodeURIComponent( item.label );
		}

		return fetch( this.inspectUrl + '?' + query, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': this.nonce },
		} ).then( function ( res ) {
			return res.json().then( function ( body ) {
				if ( ! res.ok ) {
					throw new Error( body && body.message ? body.message : t( 'inspectFailed', 'سنجش انجام نشد.' ) );
				}
				return body;
			} );
		} );
	};

	/**
	 * اعمال نتیجه‌ی سنجش روی ردیف.
	 *
	 * فقط فیلدهای خالی پر می‌شوند و مقدار تکراری گروه نوشته نمی‌شود (تا
	 * ارث‌بری حفظ شود). حجم را فقط سنجش دستی بازنویسی می‌کند. مقدارها مستقیم
	 * در ورودی‌ها نوشته می‌شوند تا فوکوس و متن در حال تایپ از دست نرود.
	 *
	 * @param {Object}  group          گروه لینک.
	 * @param {Object}  item           ردیف لینک.
	 * @param {Object}  data           پاسخ سنجش.
	 * @param {boolean} overwriteSize  بازنویسی حجم فعلی؟
	 * @return {string[]} نام فیلدهای تغییرکرده.
	 */
	LinksApp.prototype.applyInspection = function ( group, item, data, overwriteSize ) {
		var changed = [];
		var node = this.nodeFor( item );

		function fill( key, value ) {
			if ( ! value || item[ key ] || value === group[ key ] ) {
				return;
			}
			item[ key ] = value;
			changed.push( key );
		}

		fill( 'label', data.name );
		fill( 'quality', data.quality );
		fill( 'language', data.language );
		fill( 'encoder', data.encoder );

		if ( data.size && ( overwriteSize || ! item.size ) ) {
			item.size = data.size;
			changed.push( 'size' );
		}

		if ( changed.length ) {
			this.sync();
		}

		changed.forEach( function ( key ) {
			var field = node ? node.querySelector( '[data-field="' + key + '"]' ) : null;
			if ( field ) {
				field.value = item[ key ];
			}
		} );

		return changed;
	};

	/**
	 * سنجش خودکار هنگام تغییر نشانی (بدون شبکه و بدون محدودیت نرخ).
	 *
	 * @param {Object} group گروه لینک.
	 * @param {Object} item  ردیف لینک.
	 */
	LinksApp.prototype.autoInspect = function ( group, item ) {
		var self = this;
		var url = item.url || '';

		if ( ! /^https?:\/\//i.test( url.trim() ) || ! this.inspectUrl || ! this.nonce ) {
			return;
		}

		this.fetchInspection( item, false )
			.then( function ( data ) {
				if ( item.url !== url ) {
					return;
				}

				var node = self.nodeFor( item );
				var msg = node ? node.querySelector( '.manacore-inspect-msg' ) : null;
				var changed = self.applyInspection( group, item, data, false );

				if ( msg && changed.length ) {
					msg.textContent = t( 'autoFilled', 'مشخصات از خود نشانی خوانده شد.' );
				}
			} )
			.catch( function () {
				/* سنجش خودکار بی‌صدا است؛ خطا مانع ویرایش نمی‌شود. */
			} );
	};

	/**
	 * سنجش دستی (حجم از میزبان) با نمایش وضعیت.
	 *
	 * @param {Object}      group   گروه لینک.
	 * @param {Object}      item    ردیف لینک.
	 * @param {HTMLElement} button  دکمه‌ی سنجش.
	 * @param {HTMLElement} message محل پیام وضعیت.
	 */
	LinksApp.prototype.inspect = function ( group, item, button, message ) {
		var self = this;

		if ( ! this.inspectUrl || ! this.nonce ) {
			message.textContent = t( 'inspectUnavailable', 'سنجش در این صفحه فعال نیست.' );
			return;
		}

		if ( ! /^https?:\/\//i.test( ( item.url || '' ).trim() ) ) {
			message.textContent = t( 'needUrl', 'ابتدا نشانی معتبر با http یا https وارد کنید.' );
			return;
		}

		button.disabled = true;
		message.textContent = t( 'inspecting', 'در حال سنجش…' );

		this.fetchInspection( item, true )
			.then( function ( data ) {
				self.applyInspection( group, item, data, true );
				message.textContent = data.reached
					? t( 'inspectDone', 'حجم از میزبان خوانده شد.' )
					: t( 'inspectNoSize', 'حجم از میزبان خوانده نشد؛ بقیه‌ی مشخصات از نشانی و نام لینک خوانده شد.' );
				button.disabled = false;
			} )
			.catch( function ( err ) {
				message.textContent = err && err.message ? err.message : t( 'inspectFailed', 'سنجش انجام نشد.' );
				button.disabled = false;
			} );
	};

	LinksApp.prototype.labeled = function ( label, field, extraClass ) {
		return el( 'label', { class: 'manacore-inline-field ' + ( extraClass || '' ) }, [
			el( 'span', { class: 'manacore-inline-label', text: label } ),
			field,
		] );
	};

	LinksApp.prototype.checkbox = function ( label, checked, onChange ) {
		var input = el( 'input', { type: 'checkbox' } );
		input.checked = !! checked;
		input.addEventListener( 'change', function () {
			onChange( input.checked );
		} );
		return el( 'label', { class: 'manacore-inline-field manacore-inline-check' }, [
			input,
			el( 'span', { text: label } ),
		] );
	};

	function initRepeaters() {
		document.querySelectorAll( '[data-repeater]' ).forEach( function ( wrap ) {
			var rowsWrap = wrap.querySelector( '.manacore-repeater-rows' );
			var template = wrap.querySelector( '.manacore-repeater-template' );
			var addBtn = wrap.querySelector( '.manacore-repeater-add' );
			var key = wrap.getAttribute( 'data-repeater' );

			function reindex() {
				rowsWrap.querySelectorAll( '.manacore-repeater-row' ).forEach( function ( row, index ) {
					row.querySelectorAll( '[name]' ).forEach( function ( field ) {
						field.name = field.name.replace(
							new RegExp( '^' + key + '\\[[^\\]]*\\]' ),
							key + '[' + index + ']'
						);
					} );
				} );
			}

			if ( addBtn && template ) {
				addBtn.addEventListener( 'click', function () {
					var html = template.innerHTML.replace( /__INDEX__/g, String( rowsWrap.children.length ) );
					var temp = document.createElement( 'div' );
					temp.innerHTML = html;
					rowsWrap.appendChild( temp.firstElementChild );
					reindex();
				} );
			}

			wrap.addEventListener( 'click', function ( event ) {
				var btn = event.target.closest( '.manacore-repeater-remove' );
				if ( ! btn || ! wrap.contains( btn ) ) {
					return;
				}
				var row = btn.closest( '.manacore-repeater-row' );
				if ( row ) {
					row.remove();
					reindex();
				}
			} );
		} );
	}

	function initTabs() {
		document.querySelectorAll( '[data-manacore-tabs]' ).forEach( function ( wrap ) {
			wrap.querySelectorAll( '.manacore-tab' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					var target = tab.getAttribute( 'data-tab' );
					wrap.querySelectorAll( '.manacore-tab' ).forEach( function ( t2 ) {
						var active = t2 === tab;
						t2.classList.toggle( 'is-active', active );
						t2.setAttribute( 'aria-selected', active ? 'true' : 'false' );
					} );
					wrap.querySelectorAll( '.manacore-tab-panel' ).forEach( function ( panel ) {
						panel.classList.toggle( 'is-active', panel.getAttribute( 'data-panel' ) === target );
					} );
				} );
			} );
		} );
	}

	function initMedia() {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		document.querySelectorAll( '.manacore-media-pick' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var wrap = btn.closest( '.manacore-media-field' );
				var input = wrap.querySelector( '.manacore-media-input' );
				var preview = wrap.parentNode.querySelector( '.manacore-media-preview' );
				var frame = window.wp.media( {
					title: t( 'selectImage', 'انتخاب تصویر' ),
					multiple: false,
					library: { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					input.value = attachment.url;
					if ( preview ) {
						preview.src = attachment.url;
						preview.hidden = false;
					}
				} );
				frame.open();
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-manacore-links]' ).forEach( function ( root ) {
			new LinksApp( root );
		} );
		initRepeaters();
		initTabs();
		initMedia();
	} );
} )();

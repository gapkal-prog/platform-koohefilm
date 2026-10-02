/**
 * ManaCore Sources – admin fetch box.
 *
 * Drives the search / preview / import flow against the manacore/v1 REST
 * routes exposed by ManaCore\Sources\Rest.
 *
 * Vanilla ES5-compatible JS – no build step, no framework dependency.
 *
 * @package ManaCore\Sources
 */

( function ( window, document ) {
	'use strict';

	var settings = window.manaCoreSources || {};
	var i18n = settings.i18n || {};

	/**
	 * Read a translated string with a safe fallback.
	 *
	 * @param {string} key      i18n key.
	 * @param {string} fallback Fallback text.
	 * @return {string} Text.
	 */
	function t( key, fallback ) {
		return i18n[ key ] || fallback || '';
	}

	/**
	 * Escape a string for safe HTML interpolation.
	 *
	 * @param {*} value Raw value.
	 * @return {string} Escaped text.
	 */
	function esc( value ) {
		if ( value === null || typeof value === 'undefined' ) {
			return '';
		}

		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/**
	 * Only allow http(s) image/source URLs into markup.
	 *
	 * @param {*} value Raw URL.
	 * @return {string} Safe URL or empty string.
	 */
	function safeUrl( value ) {
		var url = String( value || '' ).trim();

		if ( ! url || ! /^https?:\/\//i.test( url ) ) {
			return '';
		}

		return esc( url );
	}

	/**
	 * Build a REST endpoint URL with query args.
	 *
	 * @param {string} path Route suffix, e.g. '/search'.
	 * @param {Object} args Query args.
	 * @return {string} Full URL.
	 */
	function endpoint( path, args ) {
		var base = ( settings.restUrl || '' ) + path;
		var parts = [];
		var key;

		for ( key in args ) {
			if ( ! Object.prototype.hasOwnProperty.call( args, key ) ) {
				continue;
			}

			if ( args[ key ] === '' || args[ key ] === null || typeof args[ key ] === 'undefined' ) {
				continue;
			}

			parts.push( encodeURIComponent( key ) + '=' + encodeURIComponent( args[ key ] ) );
		}

		if ( ! parts.length ) {
			return base;
		}

		return base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + parts.join( '&' );
	}

	/**
	 * Minimal fetch wrapper with nonce + JSON handling.
	 *
	 * @param {string} url     Target URL.
	 * @param {Object} options Fetch options.
	 * @return {Promise} Resolves with parsed JSON, rejects with an Error.
	 */
	function request( url, options ) {
		var opts = options || {};
		var headers = {
			Accept: 'application/json',
			'X-WP-Nonce': settings.nonce || ''
		};

		if ( opts.body ) {
			headers[ 'Content-Type' ] = 'application/json';
		}

		return window
			.fetch( url, {
				method: opts.method || 'GET',
				credentials: 'same-origin',
				headers: headers,
				body: opts.body ? JSON.stringify( opts.body ) : undefined
			} )
			.then( function ( response ) {
				return response
					.json()
					.catch( function () {
						return null;
					} )
					.then( function ( data ) {
						if ( ! response.ok ) {
							var message =
								( data && data.message ) ||
								t( 'error', 'Request failed' );

							throw new Error( message );
						}

						return data;
					} );
			} );
	}

	/**
	 * Resolve the current post id, preferring the live editor value so that a
	 * freshly-saved auto-draft still works without a page reload.
	 *
	 * @return {number} Post id.
	 */
	function currentPostId() {
		var field = document.getElementById( 'post_ID' );
		var fromField = field ? parseInt( field.value, 10 ) : 0;

		if ( fromField ) {
			return fromField;
		}

		if (
			window.wp &&
			window.wp.data &&
			typeof window.wp.data.select === 'function'
		) {
			try {
				var editor = window.wp.data.select( 'core/editor' );

				if ( editor && typeof editor.getCurrentPostId === 'function' ) {
					var fromEditor = parseInt( editor.getCurrentPostId(), 10 );

					if ( fromEditor ) {
						return fromEditor;
					}
				}
			} catch ( e ) {
				// Editor store unavailable – fall through.
			}
		}

		return parseInt( settings.postId, 10 ) || 0;
	}

	/**
	 * Notify listeners that a field was refreshed programmatically.
	 *
	 * Dispatching real events keeps the metabox scripts (tabs, repeater sorting,
	 * link builder) and any third-party integration in sync with the new value.
	 *
	 * @param {HTMLElement} element Field element.
	 */
	function notifyChange( element ) {
		var names = [ 'input', 'change' ];
		var i;

		for ( i = 0; i < names.length; i++ ) {
			var event;

			// CustomEvent is unavailable in very old browsers; fall back to the
			// legacy createEvent API rather than failing silently.
			if ( typeof window.Event === 'function' ) {
				event = new window.Event( names[ i ], { bubbles: true } );
			} else {
				event = document.createEvent( 'Event' );
				event.initEvent( names[ i ], true, true );
			}

			element.dispatchEvent( event );
		}
	}

	/**
	 * Write a scalar value into a form control of any type.
	 *
	 * @param {string} key   Meta key / input name.
	 * @param {*}      value Stored value.
	 * @return {boolean} Whether a control was updated.
	 */
	function applyScalar( key, value ) {
		var nodes = document.getElementsByName( key );
		var text = value === null || typeof value === 'undefined' ? '' : String( value );
		var touched = false;
		var i;

		for ( i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var type = ( node.type || '' ).toLowerCase();

			if ( type === 'checkbox' ) {
				node.checked = text !== '' && text !== '0';
			} else if ( type === 'radio' ) {
				node.checked = node.value === text;
			} else {
				node.value = text;
			}

			notifyChange( node );
			touched = true;
		}

		return touched;
	}

	/**
	 * Rebuild a repeater's rows from imported data.
	 *
	 * Rows are cloned from the `<template>` the metabox already renders, so the
	 * markup, field names and indexes stay identical to a server-side render.
	 *
	 * @param {string} key  Meta key.
	 * @param {Array}  rows Row objects.
	 * @return {boolean} Whether the repeater was rebuilt.
	 */
	function applyRepeater( key, rows ) {
		var wrap = document.querySelector( '[data-repeater="' + key + '"]' );

		if ( ! wrap || ! rows || ! rows.length ) {
			return false;
		}

		var list = wrap.querySelector( '.manacore-repeater-rows' );
		var template = wrap.querySelector( '.manacore-repeater-template' );

		if ( ! list || ! template || ! template.innerHTML ) {
			return false;
		}

		var markup = '';
		var i;

		for ( i = 0; i < rows.length; i++ ) {
			markup += template.innerHTML.replace( /__INDEX__/g, String( i ) );
		}

		list.innerHTML = markup;

		var built = list.querySelectorAll( '.manacore-repeater-row' );

		for ( i = 0; i < built.length && i < rows.length; i++ ) {
			var row = rows[ i ] || {};
			var fields = built[ i ].querySelectorAll( '[name]' );
			var j;

			for ( j = 0; j < fields.length; j++ ) {
				// Names look like `manacore_cast[0][name]`; read the subkey.
				var match = /\[([^\]]+)\]\s*$/.exec( fields[ j ].name || '' );
				var subKey = match ? match[ 1 ] : '';

				if ( subKey && Object.prototype.hasOwnProperty.call( row, subKey ) ) {
					var value = row[ subKey ];

					fields[ j ].value =
						value === null || typeof value === 'undefined' ? '' : String( value );
				}
			}
		}

		notifyChange( list );

		return true;
	}

	/**
	 * Refresh the open editor with the values an import just stored.
	 *
	 * The edit screen was rendered before the import ran, so its inputs still
	 * hold the pre-import (usually empty) state. Writing the stored values back
	 * is what makes "Import" visibly take effect without a page reload — and it
	 * also prevents the next save from submitting the stale empty inputs.
	 *
	 * @param {Object} changed Import summary from the REST response.
	 * @return {boolean} Whether anything on the page was updated.
	 */
	function applyValues( changed ) {
		if ( ! changed || ! changed.values ) {
			return false;
		}

		var touched = false;
		var key;

		for ( key in changed.values ) {
			if ( ! Object.prototype.hasOwnProperty.call( changed.values, key ) ) {
				continue;
			}

			var value = changed.values[ key ];

			if ( Object.prototype.toString.call( value ) === '[object Array]' ) {
				touched = applyRepeater( key, value ) || touched;
			} else {
				touched = applyScalar( key, value ) || touched;
			}
		}

		touched = applyTitle( changed.post_title ) || touched;

		if ( touched ) {
			syncFormState( changed.values );
		}

		return touched;
	}

	/**
	 * Keep the metabox state fingerprint in step with the refreshed inputs.
	 *
	 * ManaCore\Core\Metaboxes renders a hidden fingerprint of the values the
	 * form was built with, and on save it ignores an empty input whose meta
	 * changed afterwards. Once the inputs have been refreshed in place they are
	 * no longer stale, so the fingerprint is cleared for those keys to let the
	 * editor genuinely blank a field again without reloading the page.
	 *
	 * @param {Object} values Stored values keyed by meta key.
	 */
	function syncFormState( values ) {
		var field = document.getElementsByName( 'manacore_meta_state' )[ 0 ];

		if ( ! field ) {
			return;
		}

		var state;

		try {
			state = JSON.parse( field.value || '{}' );
		} catch ( e ) {
			return;
		}

		if ( ! state || typeof state !== 'object' ) {
			return;
		}

		var key;

		for ( key in values ) {
			if (
				Object.prototype.hasOwnProperty.call( values, key ) &&
				Object.prototype.hasOwnProperty.call( state, key )
			) {
				// Drop the key so the save path treats the input as authoritative.
				delete state[ key ];
			}
		}

		field.value = JSON.stringify( state );
	}

	/**
	 * Push an imported title into the classic or block editor.
	 *
	 * @param {*} title Stored post title.
	 * @return {boolean} Whether the title was updated.
	 */
	function applyTitle( title ) {
		var text = String( title || '' ).trim();

		if ( ! text ) {
			return false;
		}

		// Block editor: go through the data store so the change is tracked.
		if (
			window.wp &&
			window.wp.data &&
			typeof window.wp.data.dispatch === 'function'
		) {
			try {
				var editor = window.wp.data.select( 'core/editor' );

				if ( editor && typeof editor.getEditedPostAttribute === 'function' ) {
					if ( editor.getEditedPostAttribute( 'title' ) !== text ) {
						window.wp.data
							.dispatch( 'core/editor' )
							.editPost( { title: text } );
					}

					return true;
				}
			} catch ( e ) {
				// Store unavailable – fall through to the classic editor.
			}
		}

		var field = document.getElementById( 'title' );

		if ( field && field.value !== text ) {
			field.value = text;

			if ( window.jQuery ) {
				window.jQuery( '#title-prompt-text' ).addClass( 'screen-reader-text' );
			}

			notifyChange( field );

			return true;
		}

		return false;
	}

	/**
	 * Replace the featured image preview after an import sideloaded a poster.
	 *
	 * @param {*} url Thumbnail URL.
	 * @return {boolean} Whether a preview was replaced.
	 */
	function applyThumbnail( url ) {
		var safe = safeUrl( url );

		if ( ! safe ) {
			return false;
		}

		var box = document.getElementById( 'postimagediv' );

		if ( ! box ) {
			return false;
		}

		var image = box.querySelector( 'img' );

		if ( image ) {
			image.src = String( url );

			return true;
		}

		return false;
	}

	/**
	 * Fetch box controller.
	 *
	 * @param {HTMLElement} root Box element.
	 * @constructor
	 */
	function FetchBox( root ) {
		this.root = root;
		this.queryInput = root.querySelector( '.manacore-fetch-query' );
		this.yearInput = root.querySelector( '.manacore-fetch-year' );
		this.providerSelect = root.querySelector( '.manacore-fetch-provider' );
		this.submitButton = root.querySelector( '.manacore-fetch-submit' );
		this.statusBox = root.querySelector( '.manacore-fetch-status' );
		this.resultsBox = root.querySelector( '.manacore-fetch-results' );
		this.overwriteInput = root.querySelector( '.manacore-fetch-overwrite' );
		this.sectionInputs = root.querySelectorAll( '[data-section]' );

		this.results = [];
		this.selected = null;
		this.busy = false;

		this.bind();
	}

	/**
	 * Wire up DOM events.
	 */
	FetchBox.prototype.bind = function () {
		var self = this;

		if ( this.submitButton ) {
			this.submitButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				self.search();
			} );
		}

		if ( this.queryInput ) {
			this.queryInput.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' || event.keyCode === 13 ) {
					event.preventDefault();
					self.search();
				}
			} );
		}

		if ( this.yearInput ) {
			this.yearInput.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' || event.keyCode === 13 ) {
					event.preventDefault();
					self.search();
				}
			} );
		}

		// Delegated clicks inside the results area.
		if ( this.resultsBox ) {
			this.resultsBox.addEventListener( 'click', function ( event ) {
				var item = event.target.closest( '.manacore-fetch-item' );

				if ( item ) {
					event.preventDefault();
					self.preview( parseInt( item.getAttribute( 'data-index' ), 10 ), item );
					return;
				}

				if ( event.target.closest( '.manacore-fetch-import' ) ) {
					event.preventDefault();
					self.importRecord();
					return;
				}

				if ( event.target.closest( '.manacore-fetch-cancel' ) ) {
					event.preventDefault();
					self.selected = null;
					self.renderResults();
				}
			} );
		}

		if ( this.statusBox ) {
			this.statusBox.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '.manacore-fetch-reload' ) ) {
					event.preventDefault();
					window.location.reload();
				}
			} );
		}
	};

	/**
	 * Update the status line.
	 *
	 * @param {string}  message Message text (may be empty to clear).
	 * @param {string}  state   One of '', 'loading', 'error', 'success', 'warning'.
	 * @param {boolean} reload  Append a reload link.
	 */
	FetchBox.prototype.setStatus = function ( message, state, reload ) {
		if ( ! this.statusBox ) {
			return;
		}

		this.statusBox.className = 'manacore-fetch-status';

		if ( state ) {
			this.statusBox.classList.add( 'is-' + state );
		}

		if ( ! message ) {
			this.statusBox.textContent = '';
			return;
		}

		var html = esc( message );

		if ( reload ) {
			html +=
				' <a href="#" class="manacore-fetch-reload">' +
				esc( t( 'reload', 'Reload' ) ) +
				'</a>';
		}

		this.statusBox.innerHTML = html;
	};

	/**
	 * Toggle the busy state on the search button.
	 *
	 * @param {boolean} busy Busy flag.
	 */
	FetchBox.prototype.setBusy = function ( busy ) {
		this.busy = !! busy;

		if ( ! this.submitButton ) {
			return;
		}

		this.submitButton.disabled = this.busy;
		this.submitButton.classList.toggle( 'is-busy', this.busy );
	};

	/**
	 * Run a provider search.
	 */
	FetchBox.prototype.search = function () {
		var self = this;

		if ( this.busy ) {
			return;
		}

		var query = this.queryInput ? this.queryInput.value.trim() : '';

		if ( ! query ) {
			this.setStatus( t( 'searchLabel', 'Enter a title' ), 'warning' );

			if ( this.queryInput ) {
				this.queryInput.focus();
			}

			return;
		}

		this.selected = null;
		this.results = [];

		if ( this.resultsBox ) {
			this.resultsBox.innerHTML = '';
		}

		this.setBusy( true );
		this.setStatus( t( 'searching', 'Searching…' ), 'loading' );

		var args = {
			query: query,
			kind: settings.kind || '',
			provider: this.providerSelect ? this.providerSelect.value : '',
			post_id: currentPostId()
		};

		var year = this.yearInput ? parseInt( this.yearInput.value, 10 ) : 0;

		if ( year ) {
			args.year = year;
		}

		request( endpoint( '/search', args ) )
			.then( function ( data ) {
				self.setBusy( false );
				self.results = ( data && data.results ) || [];

				if ( ! self.results.length ) {
					self.setStatus( t( 'noResults', 'No results found.' ), 'warning' );
					return;
				}

				self.setStatus( '', '' );
				self.renderResults();
			} )
			.catch( function ( error ) {
				self.setBusy( false );
				self.setStatus( error.message || t( 'error', 'Error' ), 'error' );
			} );
	};

	/**
	 * Render the result list (or the selected preview card).
	 */
	FetchBox.prototype.renderResults = function () {
		if ( ! this.resultsBox ) {
			return;
		}

		if ( this.selected ) {
			this.resultsBox.innerHTML = this.previewMarkup( this.selected );
			return;
		}

		var html = '<ul class="manacore-fetch-list">';

		for ( var i = 0; i < this.results.length; i++ ) {
			html += this.itemMarkup( this.results[ i ], i );
		}

		html += '</ul>';

		this.resultsBox.innerHTML = html;
	};

	/**
	 * Markup for a single search hit.
	 *
	 * @param {Object} hit   Search hit.
	 * @param {number} index Index in the results array.
	 * @return {string} HTML.
	 */
	FetchBox.prototype.itemMarkup = function ( hit, index ) {
		var poster = safeUrl( hit.poster );
		var thumb = poster
			? '<span class="manacore-fetch-thumb"><img src="' +
			  poster +
			  '" alt="" loading="lazy" /></span>'
			: '<span class="manacore-fetch-thumb is-empty" aria-hidden="true"></span>';

		var sub = '';

		if ( hit.provider_label ) {
			sub +=
				'<span class="manacore-fetch-provider-tag">' +
				esc( hit.provider_label ) +
				'</span>';
		}

		if ( hit.year ) {
			sub += '<span>' + esc( hit.year ) + '</span>';
		}

		if ( hit.rating ) {
			sub +=
				'<span class="manacore-fetch-rating">' + esc( hit.rating ) + '</span>';
		}

		var title = hit.title || hit.original || '—';

		var overview = hit.overview
			? '<p class="manacore-fetch-overview">' + esc( hit.overview ) + '</p>'
			: '';

		return (
			'<li>' +
			'<button type="button" class="manacore-fetch-item" data-index="' +
			index +
			'">' +
			thumb +
			'<span class="manacore-fetch-info">' +
			'<span class="manacore-fetch-title">' +
			esc( title ) +
			'</span>' +
			'<span class="manacore-fetch-sub">' +
			sub +
			'</span>' +
			overview +
			'</span>' +
			'</button>' +
			'</li>'
		);
	};

	/**
	 * Fetch a full record for preview.
	 *
	 * @param {number}      index   Result index.
	 * @param {HTMLElement} element Clicked element.
	 */
	FetchBox.prototype.preview = function ( index, element ) {
		var self = this;
		var hit = this.results[ index ];

		if ( ! hit || this.busy ) {
			return;
		}

		this.setBusy( true );

		if ( element ) {
			element.classList.add( 'is-loading' );
		}

		this.setStatus( t( 'searching', 'Loading…' ), 'loading' );

		request(
			endpoint( '/preview', {
				provider: hit.provider,
				remote_id: hit.remote_id,
				kind: hit.kind || settings.kind || '',
				post_id: currentPostId()
			} )
		)
			.then( function ( data ) {
				self.setBusy( false );
				self.setStatus( '', '' );

				self.selected = {
					provider: hit.provider,
					providerLabel: hit.provider_label || '',
					remoteId: hit.remote_id,
					kind: hit.kind || settings.kind || '',
					summary: ( data && data.summary ) || {}
				};

				self.renderResults();
			} )
			.catch( function ( error ) {
				self.setBusy( false );

				if ( element ) {
					element.classList.remove( 'is-loading' );
				}

				self.setStatus( error.message || t( 'error', 'Error' ), 'error' );
			} );
	};

	/**
	 * Markup for the preview card of a selected record.
	 *
	 * @param {Object} selected Selected record wrapper.
	 * @return {string} HTML.
	 */
	FetchBox.prototype.previewMarkup = function ( selected ) {
		var s = selected.summary || {};
		var poster = safeUrl( s.poster );

		var thumb = poster
			? '<span class="manacore-fetch-thumb"><img src="' +
			  poster +
			  '" alt="" loading="lazy" /></span>'
			: '<span class="manacore-fetch-thumb is-empty" aria-hidden="true"></span>';

		var facts = '';

		if ( s.year ) {
			facts +=
				'<li><b>' +
				esc( t( 'yearLabel', 'Year' ) ) +
				'</b>' +
				esc( s.year ) +
				'</li>';
		}

		if ( s.runtime ) {
			facts += '<li>' + esc( s.runtime ) + '</li>';
		}

		if ( s.genres ) {
			facts += '<li>' + esc( s.genres ) + '</li>';
		}

		if ( s.cast_count ) {
			facts +=
				'<li><b>' +
				esc( t( 'cast', 'Cast' ) ) +
				'</b>' +
				esc( s.cast_count ) +
				'</li>';
		}

		if ( s.seasons ) {
			facts +=
				'<li><b>' +
				esc( t( 'seasons', 'Seasons' ) ) +
				'</b>' +
				esc( s.seasons ) +
				'</li>';
		}

		if ( s.provider || selected.providerLabel ) {
			facts +=
				'<li><b>' +
				esc( t( 'sourceLabel', 'Source' ) ) +
				'</b>' +
				esc( s.provider || selected.providerLabel ) +
				'</li>';
		}

		var sourceUrl = safeUrl( s.source_url );
		var sourceLink = sourceUrl
			? '<a class="manacore-fetch-source-link" href="' +
			  sourceUrl +
			  '" target="_blank" rel="noopener noreferrer">' +
			  esc( t( 'sourceLabel', 'Source' ) ) +
			  '</a>'
			: '';

		var original =
			s.original && s.original !== s.title
				? '<p class="manacore-fetch-preview-original">' +
				  esc( s.original ) +
				  '</p>'
				: '';

		return (
			'<div class="manacore-fetch-preview">' +
			'<div class="manacore-fetch-preview-head">' +
			thumb +
			'<div class="manacore-fetch-info">' +
			'<h4 class="manacore-fetch-preview-title">' +
			esc( s.title || '—' ) +
			'</h4>' +
			original +
			'</div>' +
			'</div>' +
			( facts ? '<ul class="manacore-fetch-facts">' + facts + '</ul>' : '' ) +
			'<div class="manacore-fetch-actions">' +
			'<button type="button" class="button button-primary manacore-fetch-import">' +
			esc( t( 'import', 'Import' ) ) +
			'</button>' +
			'<button type="button" class="button manacore-fetch-cancel">' +
			esc( t( 'searchButton', 'Back' ) ) +
			'</button>' +
			sourceLink +
			'</div>' +
			'</div>'
		);
	};

	/**
	 * Collect the checked import sections.
	 *
	 * @return {Object} Section map.
	 */
	FetchBox.prototype.sections = function () {
		var map = {};
		var i;

		for ( i = 0; i < this.sectionInputs.length; i++ ) {
			map[ this.sectionInputs[ i ].getAttribute( 'data-section' ) ] =
				this.sectionInputs[ i ].checked;
		}

		return map;
	};

	/**
	 * Import the selected record into the current post.
	 */
	FetchBox.prototype.importRecord = function () {
		var self = this;

		if ( ! this.selected || this.busy ) {
			return;
		}

		var postId = currentPostId();

		if ( ! postId ) {
			this.setStatus( t( 'saveFirst', 'Save the post first.' ), 'warning' );
			return;
		}

		var button = this.root.querySelector( '.manacore-fetch-import' );

		if ( button ) {
			button.disabled = true;
			button.textContent = t( 'importing', 'Importing…' );
		}

		this.setBusy( true );
		this.setStatus( t( 'importing', 'Importing…' ), 'loading' );

		request( endpoint( '/import', {} ), {
			method: 'POST',
			body: {
				post_id: postId,
				provider: this.selected.provider,
				remote_id: this.selected.remoteId,
				kind: this.selected.kind,
				overwrite: !! ( this.overwriteInput && this.overwriteInput.checked ),
				sections: this.sections()
			}
		} )
			.then( function ( data ) {
				self.setBusy( false );

				var message = t( 'imported', 'Imported.' );

				if ( data && data.message ) {
					message += ' ' + data.message;
				}

				/*
				 * Refresh the edit screen with what was actually stored. The
				 * page was rendered before the import ran, so without this the
				 * fields keep showing their empty pre-import state and the
				 * import looks like it did nothing at all.
				 */
				var changed = data && data.changed ? data.changed : null;
				var applied = applyValues( changed );

				if ( changed ) {
					applyThumbnail( changed.thumbnail_url );
				}

				if ( applied ) {
					message += ' ' + t( 'fieldsUpdated', '' );
				} else {
					message += ' ' + t( 'reloadHint', '' );
				}

				// A reload link is only useful when the fields could not be
				// refreshed in place.
				self.setStatus( message, 'success', ! applied );

				if ( button ) {
					button.disabled = true;
					button.textContent = t( 'imported', 'Imported' );
				}
			} )
			.catch( function ( error ) {
				self.setBusy( false );
				self.setStatus( error.message || t( 'error', 'Error' ), 'error' );

				if ( button ) {
					button.disabled = false;
					button.textContent = t( 'import', 'Import' );
				}
			} );
	};

	/**
	 * Boot every fetch box on the page.
	 */
	function boot() {
		if ( ! window.fetch || ! settings.restUrl ) {
			return;
		}

		var boxes = document.querySelectorAll( '[data-manacore-sources]' );
		var i;

		for ( i = 0; i < boxes.length; i++ ) {
			if ( boxes[ i ].getAttribute( 'data-manacore-ready' ) === '1' ) {
				continue;
			}

			boxes[ i ].setAttribute( 'data-manacore-ready', '1' );
			new FetchBox( boxes[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	// Metaboxes can be re-rendered in the block editor; re-scan on demand.
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'manacore:refresh', boot );
	}

	window.manaCoreSourcesBoot = boot;
} )( window, document );

/**
 * Koohe Film — ثبت سمت مرورگر بلوک‌های قالب.
 *
 * چرا لازم است؟
 * بلوک‌های koohe/* تنها با register_block_type در PHP ثبت شده بودند.
 * وردپرس در این حالت تعریف بلوک را به ویرایشگر می‌فرستد ولی هیچ
 * پیاده‌سازی edit/save در جاوااسکریپت وجود ندارد، پس ویرایشگر پیام
 * «سایت شما از بلوک … پشتیبانی نمی‌کند» را نشان می‌دهد. اینجا برای هر
 * بلوک یک edit با پیش‌نمایش سمت سرور (ServerSideRender) ثبت می‌شود تا
 * بلوک در ویرایشگر سایت و ویرایشگر نوشته درست دیده و ویرایش شود.
 *
 * بدون JSX نوشته شده تا نیازی به مرحله‌ی بیلد نباشد.
 *
 * @package KooheFilm
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var C                 = wp.components;
	var ServerSideRender  = wp.serverSideRender;
	var registry          = window.kooheBlockRegistry || {};

	/**
	 * ساخت تابع edit برای یک بلوک سمت سرور.
	 *
	 * @param {string} name   نام بلوک.
	 * @param {Object} config تعریف بلوک از سمت سرور.
	 * @return {Function} کامپوننت edit.
	 */
	function makeEdit( name, config ) {
		return function ( props ) {
			var blockProps = useBlockProps ? useBlockProps() : {};
			var attributes = props.attributes || {};
			var controls   = [];
			var fields     = config.editorFields || {};

			/* ساخت خودکار کنترل‌ها بر پایه‌ی تعریف سمت سرور. */
			Object.keys( fields ).forEach( function ( key ) {
				var field = fields[ key ] || {};

				if ( 'text' === field.type && C.TextControl ) {
					controls.push(
						el( C.TextControl, {
							key: key,
							label: field.label || key,
							help: field.help || undefined,
							value: undefined === attributes[ key ] ? '' : attributes[ key ],
							onChange: function ( value ) {
								var next = {};

								next[ key ] = value;
								props.setAttributes( next );
							},
						} )
					);
				} else if ( 'toggle' === field.type && C.ToggleControl ) {
					controls.push(
						el( C.ToggleControl, {
							key: key,
							label: field.label || key,
							help: field.help || undefined,
							checked: !! attributes[ key ],
							onChange: function ( value ) {
								var next = {};

								next[ key ] = value;
								props.setAttributes( next );
							},
						} )
					);
				}
			} );

			var panels = controls.length
				? el(
						InspectorControls,
						null,
						el(
							C.PanelBody,
							{ title: __( 'تنظیمات بلوک', 'koohe-film' ), initialOpen: true },
							controls
						)
				  )
				: null;

			/*
			 * پیش‌نمایش سمت سرور تا خروجی ویرایشگر با سایت یکی باشد.
			 * پوشش .koohe-block-preview کلیک‌ها را می‌گیرد تا کاربر
			 * بتواند بلوک را انتخاب کند و درون پیش‌نمایش گیر نکند.
			 */
			var preview = ServerSideRender
				? el( ServerSideRender, {
						block: name,
						attributes: attributes,
						httpMethod: 'POST',
				  } )
				: el(
						'div',
						{ className: 'koohe-block-fallback' },
						config.title || name
				  );

			return el(
				Fragment,
				null,
				panels,
				el(
					'div',
					blockProps,
					el( 'div', { className: 'koohe-block-preview' }, preview )
				)
			);
		};
	}

	Object.keys( registry ).forEach( function ( name ) {
		var config = registry[ name ] || {};

		/*
		 * اگر هسته پیش‌تر نسخه‌ی سمت سرور را ثبت کرده باشد، ابتدا آن را
		 * برمی‌داریم تا ثبت دوباره خطای «قبلاً ثبت شده» ندهد.
		 */
		if ( wp.blocks.getBlockType( name ) ) {
			wp.blocks.unregisterBlockType( name );
		}

		wp.blocks.registerBlockType( name, {
			apiVersion: 3,
			title: config.title || name,
			description: config.description || '',
			category: config.category || 'manacore',
			icon: config.icon || 'video-alt2',
			keywords: config.keywords || [],
			supports: config.supports || {},
			attributes: config.attributes || {},
			example: { attributes: {} },
			edit: makeEdit( name, config ),
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );

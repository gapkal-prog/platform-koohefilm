/**
 * کنترل‌های ویرایشگر بلوک‌های افزونه‌ی اشتراک.
 *
 * چرا این فایل لازم است؟
 * بلوک‌هایی که فقط سمت سرور با `register_block_type()` ثبت می‌شوند، در
 * فهرست سمت سرور هستند (و در REST هم برمی‌گردند) اما کتابخانه‌ی بلوک‌ها
 * در مرورگر آن‌ها را نمی‌شناسد؛ نتیجه‌اش این است که در ویرایشگر سایت و
 * ویرایشگر برگه، هر دو بلوک به‌شکل «سایت شما از بلوک … پشتیبانی نمی‌کند»
 * (`core/missing`) دیده می‌شوند. این فایل همان ثبت سمت مرورگر را انجام
 * می‌دهد: پیش‌نمایش با `ServerSideRender` (همان بازتاب سمت سرور) و یک
 * پنل تنظیمات برای گزینه‌های واقعی هر بلوک.
 *
 * بدون JSX نوشته شده تا مرحله‌ی بیلد لازم نباشد (هم‌سبک با بقیه‌ی پروژه).
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var ToggleControl = wp.components.ToggleControl;
	var PanelBody = wp.components.PanelBody;
	var Placeholder = wp.components.Placeholder;

	var registry = window.manacoreSubsBlocks || {};

	/**
	 * پنل تنظیمات هر بلوک.
	 *
	 * تابع JSON‌شدنی نیست، پس به‌جای تابع در رجیستری، بر پایه‌ی نام بلوک
	 * ساخته می‌شود (همان کاری که افزونه‌ی هسته با `switch ( name )` می‌کند).
	 *
	 * @param {string} name نام بلوک.
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل یا null.
	 */
	function panelsFor( name, props ) {
		if ( 'manacore/subscription-status' === name ) {
			return el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{
						title: __( 'تنظیمات کارت وضعیت', 'manacore' ),
						initialOpen: true,
					},
					el( ToggleControl, {
						label: __( 'نمایش فشرده', 'manacore' ),
						help: __( 'کارت کوتاه‌تر با فاصله‌های کمتر؛ مناسب ستون کنار.', 'manacore' ),
						checked: !! props.attributes.compact,
						onChange: function ( value ) {
							props.setAttributes( { compact: !! value } );
						},
					} )
				)
			);
		}

		if ( 'manacore/subscription-plans' === name ) {
			return el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'طرح‌های اشتراک', 'manacore' ), initialOpen: true },
					el(
						'p',
						{ className: 'components-base-control__help' },
						__(
							'عنوان، قیمت، تخفیف و ویژگی‌های هر طرح از سطح‌های اشتراک خوانده می‌شود (تنظیمات ← اشتراک) و در صورت نگاشت محصول ووکامرس، از قیمت همان محصول.',
							'manacore'
						)
					)
				)
			);
		}

		return null;
	}

	/**
	 * پیش‌نمایش سمت سرور + پنل تنظیمات.
	 *
	 * @param {string} name نام بلوک.
	 * @return {Function} کامپوننت ویرایش.
	 */
	function makeEdit( name ) {
		return function Edit( props ) {
			var blockProps = useBlockProps();
			var controls = panelsFor( name, props );

			var preview = ServerSideRender
				? el( ServerSideRender, {
						block: name,
						attributes: props.attributes,
						httpMethod: 'POST',
				  } )
				: el(
						Placeholder,
						{ label: __( 'پیش‌نمایش سمت سرور در دسترس نیست.', 'manacore' ) }
				  );

			return el(
				wp.element.Fragment,
				null,
				controls,
				el( 'div', blockProps, el( 'div', { className: 'manacore-block-preview' }, preview ) )
			);
		};
	}

	Object.keys( registry ).forEach( function ( name ) {
		var config = registry[ name ] || {};

		/* اگر پیش‌تر (از سمت سرور یا جای دیگر) ثبت شده، دوباره ثبت نکنیم. */
		if ( wp.blocks.getBlockType( name ) ) {
			return;
		}

		registerBlockType( name, {
			apiVersion: 3,
			title: config.title || name,
			description: config.description || '',
			category: config.category || 'manacore',
			icon: config.icon || 'star-filled',
			keywords: config.keywords || [],
			supports: config.supports || { html: false },
			attributes: config.attributes || {},
			edit: makeEdit( name ),
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );

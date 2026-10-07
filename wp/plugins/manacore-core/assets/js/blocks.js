/**
 * کنترل‌های ویرایشگر بلوک‌های ManaCore.
 *
 * ساختار پنل‌ها برای بلوک‌های حلقه:
 *   ۱) محتوا و سرتیتر
 *   ۲) منبع و کوئری
 *   ۳) فیلتر تاکسونومی
 *   ۴) فیلترهای پیشرفته
 *   ۵) مرتب‌سازی
 *   ۶) چیدمان
 *   ۷) نمایش کارت
 *   ۸) نمایش شرطی
 *   ۹) رفتار بلوک
 *
 * بدون JSX نوشته شده تا نیازی به مرحله‌ی بیلد نباشد.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InnerBlocks = wp.blockEditor.InnerBlocks;

	var C = wp.components;
	var PanelBody = C.PanelBody;
	var PanelRow = C.PanelRow;
	var TextControl = C.TextControl;
	var TextareaControl = C.TextareaControl;
	var SelectControl = C.SelectControl;
	var RangeControl = C.RangeControl;
	var ToggleControl = C.ToggleControl;
	var Button = C.Button;
	var FormTokenField = C.FormTokenField;
	var Notice = C.Notice;
	var Spinner = C.Spinner;

	var ServerSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;
	var ComboboxControl = C.ComboboxControl;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var data = window.manaCoreBlocks || {};

	/* -----------------------------------------------------------------
	 * کمکی‌ها
	 * -------------------------------------------------------------- */

	/**
	 * تبدیل { key: label } به [{ value, label }].
	 *
	 * @param {Object} object     شیء ورودی.
	 * @param {string} emptyLabel برچسب گزینه‌ی خالی (اختیاری).
	 * @return {Array} گزینه‌ها.
	 */
	function toOptions( object, emptyLabel ) {
		var out = [];
		if ( emptyLabel ) {
			out.push( { label: emptyLabel, value: '' } );
		}
		Object.keys( object || {} ).forEach( function ( key ) {
			out.push( { label: object[ key ], value: key } );
		} );
		return out;
	}

	/**
	 * فهرست تاکسونومی‌ها به شکل گزینه.
	 *
	 * @param {string} emptyLabel برچسب خالی.
	 * @return {Array} گزینه‌ها.
	 */
	function taxonomyOptions( emptyLabel ) {
		var out = [];
		if ( emptyLabel ) {
			out.push( { label: emptyLabel, value: '' } );
		}
		Object.keys( data.taxonomies || {} ).forEach( function ( slug ) {
			out.push( { label: data.taxonomies[ slug ].label + ' (' + slug + ')', value: slug } );
		} );
		return out;
	}

	/**
	 * ترم‌های یک تاکسونومی.
	 *
	 * @param {string} taxonomy اسلاگ تاکسونومی.
	 * @return {Array} فهرست { value, label }.
	 */
	function termsOf( taxonomy ) {
		var tax = ( data.taxonomies || {} )[ taxonomy ];
		return tax && tax.terms ? tax.terms : [];
	}

	/**
	 * تبدیل نام‌های انتخاب‌شده در FormTokenField به اسلاگ.
	 *
	 * @param {Array} tokens نام‌ها یا اسلاگ‌ها.
	 * @param {Array} list   فهرست مرجع { value, label }.
	 * @return {Array} اسلاگ‌ها.
	 */
	function tokensToValues( tokens, list ) {
		return ( tokens || [] ).map( function ( token ) {
			var found = list.filter( function ( item ) {
				return item.label === token || item.value === token;
			} )[ 0 ];
			return found ? found.value : token;
		} );
	}

	/**
	 * تبدیل اسلاگ‌ها به برچسب برای نمایش در FormTokenField.
	 *
	 * @param {Array} values اسلاگ‌ها.
	 * @param {Array} list   فهرست مرجع.
	 * @return {Array} برچسب‌ها.
	 */
	function valuesToTokens( values, list ) {
		return ( values || [] ).map( function ( value ) {
			var found = list.filter( function ( item ) {
				return item.value === value;
			} )[ 0 ];
			return found ? found.label : value;
		} );
	}

	/**
	 * کنترل چند‌انتخابی مبتنی بر FormTokenField.
	 *
	 * @param {Object} config پیکربندی { label, help, value, options, onChange }.
	 * @return {Object} المان.
	 */
	function MultiSelect( config ) {
		var list = config.options || [];
		return el( FormTokenField, {
			label: config.label,
			help: config.help,
			value: valuesToTokens( config.value, list ),
			suggestions: list.map( function ( item ) {
				return item.label;
			} ),
			onChange: function ( tokens ) {
				config.onChange( tokensToValues( tokens, list ) );
			},
			__experimentalExpandOnFocus: true,
			__nextHasNoMarginBottom: true,
		} );
	}

	/**
	 * به‌روزرسانی یک ویژگی.
	 *
	 * @param {Object} props props بلوک.
	 * @param {string} key   نام ویژگی.
	 * @return {Function} تابع تغییر.
	 */
	function setter( props, key ) {
		return function ( value ) {
			var patch = {};
			patch[ key ] = value;
			props.setAttributes( patch );
		};
	}

	/**
	 * تغییر یک عدد صحیح.
	 *
	 * @param {Object} props props بلوک.
	 * @param {string} key   نام ویژگی.
	 * @return {Function} تابع تغییر.
	 */
	function intSetter( props, key ) {
		return function ( value ) {
			var patch = {};
			patch[ key ] = parseInt( value, 10 ) || 0;
			props.setAttributes( patch );
		};
	}

	/**
	 * آیا این ویژگی در بلوک تعریف شده است؟
	 *
	 * @param {Object} props props بلوک.
	 * @param {string} key   نام ویژگی.
	 * @return {boolean} نتیجه.
	 */
	function has( props, key ) {
		return typeof props.attributes[ key ] !== 'undefined';
	}

	/**
	 * تبدیل رشته‌ی کاما‌جدا به آرایه.
	 *
	 * @param {string} value ورودی.
	 * @return {Array} آرایه.
	 */
	function splitList( value ) {
		return String( value || '' )
			.split( /[\s,،]+/ )
			.filter( function ( item ) {
				return '' !== item;
			} );
	}

	/* -----------------------------------------------------------------
	 * پنل ۸ — نمایش شرطی
	 * -------------------------------------------------------------- */

	var VISIBILITY_DEFAULT = {
		enabled: false,
		action: 'show',
		relation: 'AND',
		rules: [],
		devices: [],
	};

	var RULE_DEFAULT = {
		type: 'context',
		operator: 'is',
		taxonomy: '',
		values: [],
		from: '',
		to: '',
		key: '',
	};

	/**
	 * خواندن پیکربندی نمایش شرطی به‌همراه مقادیر پیش‌فرض.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object} پیکربندی.
	 */
	function visibilityConfig( props ) {
		var raw = props.attributes.visibility || {};
		return {
			enabled: !! raw.enabled,
			action: 'hide' === raw.action ? 'hide' : 'show',
			relation: 'OR' === raw.relation ? 'OR' : 'AND',
			rules: Array.isArray( raw.rules ) ? raw.rules : [],
			devices: Array.isArray( raw.devices ) ? raw.devices : [],
		};
	}

	/**
	 * ذخیره‌ی بخشی از پیکربندی نمایش شرطی.
	 *
	 * @param {Object} props props بلوک.
	 * @param {Object} patch تغییرات.
	 */
	function setVisibility( props, patch ) {
		var next = Object.assign( {}, VISIBILITY_DEFAULT, visibilityConfig( props ), patch );
		props.setAttributes( { visibility: next } );
	}

	/**
	 * به‌روزرسانی یک قاعده در فهرست قواعد.
	 *
	 * @param {Object} props props بلوک.
	 * @param {number} index شماره‌ی قاعده.
	 * @param {Object} patch تغییرات قاعده.
	 */
	function setRule( props, index, patch ) {
		var config = visibilityConfig( props );
		var rules  = config.rules.map( function ( rule, i ) {
			if ( i !== index ) {
				return rule;
			}
			return Object.assign( {}, RULE_DEFAULT, rule, patch );
		} );
		setVisibility( props, { rules: rules } );
	}

	/**
	 * ساخت ورودی مقدار برای هر نوع قاعده.
	 *
	 * @param {Object} props props بلوک.
	 * @param {Object} rule  قاعده.
	 * @param {number} index شماره.
	 * @return {Array} المان‌ها.
	 */
	function ruleValueFields( props, rule, index ) {
		var out = [];
		var vis = data.visibility || {};

		var onValues = function ( values ) {
			setRule( props, index, { values: values } );
		};
		var onSingle = function ( value ) {
			setRule( props, index, { values: value ? [ value ] : [] } );
		};
		var single = ( rule.values || [] )[ 0 ] || '';

		switch ( rule.type ) {

			case 'context':
				out.push(
					MultiSelect( {
						key: 'v',
						label: __( 'زمینه‌های صفحه', 'manacore' ),
						help: __( 'در کدام نوع صفحه‌ها این شرط برقرار است.', 'manacore' ),
						value: rule.values,
						options: toOptions( vis.contexts ),
						onChange: onValues,
					} )
				);
				break;

			case 'post_type':
				out.push(
					MultiSelect( {
						key: 'v',
						label: __( 'نوع‌های محتوا', 'manacore' ),
						value: rule.values,
						options: toOptions( data.allPostTypes ),
						onChange: onValues,
					} )
				);
				break;

			case 'taxonomy':
			case 'archive_term':
				out.push(
					el( SelectControl, {
						key: 'tax',
						label: __( 'تاکسونومی', 'manacore' ),
						value: rule.taxonomy,
						options: taxonomyOptions( __( '— انتخاب کنید —', 'manacore' ) ),
						onChange: function ( value ) {
							setRule( props, index, { taxonomy: value, values: [] } );
						},
						__nextHasNoMarginBottom: true,
					} )
				);
				if ( rule.taxonomy ) {
					out.push(
						MultiSelect( {
							key: 'v',
							label: __( 'ترم‌ها', 'manacore' ),
							help: __( 'خالی بگذارید تا «هر ترمی» در نظر گرفته شود.', 'manacore' ),
							value: rule.values,
							options: termsOf( rule.taxonomy ),
							onChange: onValues,
						} )
					);
				}
				break;

			case 'post_ids':
				out.push(
					el( TextControl, {
						key: 'v',
						label: __( 'شناسه‌ی نوشته‌ها', 'manacore' ),
						help: __( 'با کاما جدا کنید. مثال: 12, 48, 90', 'manacore' ),
						value: ( rule.values || [] ).join( ', ' ),
						onChange: function ( value ) {
							onValues( splitList( value ) );
						},
						__nextHasNoMarginBottom: true,
					} )
				);
				break;

			case 'login':
				out.push(
					el( SelectControl, {
						key: 'v',
						label: __( 'وضعیت ورود', 'manacore' ),
						value: single || 'logged_in',
						options: [
							{ label: __( 'کاربر وارد شده', 'manacore' ), value: 'logged_in' },
							{ label: __( 'کاربر مهمان', 'manacore' ), value: 'logged_out' },
						],
						onChange: onSingle,
						__nextHasNoMarginBottom: true,
					} )
				);
				break;

			case 'user_role':
				out.push(
					MultiSelect( {
						key: 'v',
						label: __( 'نقش‌های کاربری', 'manacore' ),
						value: rule.values,
						options: toOptions( data.roles ),
						onChange: onValues,
					} )
				);
				break;

			case 'subscription':
				if ( ! data.hasSubs ) {
					out.push(
						el(
							Notice,
							{ key: 'warn', status: 'warning', isDismissible: false },
							__( 'افزونه‌ی اشتراک ManaCore فعال نیست.', 'manacore' )
						)
					);
				}
				out.push(
					MultiSelect( {
						key: 'v',
						label: __( 'سطوح اشتراک', 'manacore' ),
						value: rule.values,
						options: toOptions( data.subscription ),
						onChange: onValues,
					} )
				);
				break;

			case 'premium':
			case 'has_links':
				out.push(
					el( SelectControl, {
						key: 'v',
						label: 'premium' === rule.type
							? __( 'محتوای اشتراکی باشد؟', 'manacore' )
							: __( 'لینک دانلود داشته باشد؟', 'manacore' ),
						value: single || 'yes',
						options: [
							{ label: __( 'بله', 'manacore' ), value: 'yes' },
							{ label: __( 'خیر', 'manacore' ), value: 'no' },
						],
						onChange: onSingle,
						__nextHasNoMarginBottom: true,
					} )
				);
				break;

			case 'date_range':
				out.push(
					el( TextControl, {
						key: 'from',
						type: 'date',
						label: __( 'از تاریخ', 'manacore' ),
						value: rule.from,
						onChange: function ( value ) {
							setRule( props, index, { from: value } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( TextControl, {
						key: 'to',
						type: 'date',
						label: __( 'تا تاریخ', 'manacore' ),
						value: rule.to,
						onChange: function ( value ) {
							setRule( props, index, { to: value } );
						},
						__nextHasNoMarginBottom: true,
					} )
				);
				break;

			case 'query_var':
				out.push(
					el( TextControl, {
						key: 'key',
						label: __( 'نام پارامتر', 'manacore' ),
						help: __( 'مثال: ref در آدرس ?ref=home', 'manacore' ),
						value: rule.key,
						onChange: function ( value ) {
							setRule( props, index, { key: value } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					el( TextControl, {
						key: 'v',
						label: __( 'مقدارهای مجاز', 'manacore' ),
						help: __( 'با کاما جدا کنید. خالی = فقط وجود پارامتر مهم است.', 'manacore' ),
						value: ( rule.values || [] ).join( ', ' ),
						onChange: function ( value ) {
							onValues( splitList( value ) );
						},
						__nextHasNoMarginBottom: true,
					} )
				);
				break;
		}

		return out;
	}

	/**
	 * پنل نمایش شرطی (مشترک میان همه‌ی بلوک‌ها).
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function visibilityPanel( props ) {
		if ( ! has( props, 'visibility' ) ) {
			return null;
		}

		var config = visibilityConfig( props );
		var vis    = data.visibility || {};
		var active = config.enabled && ( config.rules.length || config.devices.length );

		var children = [
			el( ToggleControl, {
				key: 'enabled',
				label: __( 'فعال‌سازی نمایش شرطی', 'manacore' ),
				help: __( 'با فعال کردن این گزینه، نمایش بلوک به شرط‌های زیر وابسته می‌شود.', 'manacore' ),
				checked: config.enabled,
				onChange: function ( value ) {
					setVisibility( props, { enabled: value } );
				},
				__nextHasNoMarginBottom: true,
			} ),
		];

		if ( config.enabled ) {
			children.push(
				el( SelectControl, {
					key: 'action',
					label: __( 'در صورت برقراری شرط‌ها', 'manacore' ),
					value: config.action,
					options: [
						{ label: __( 'بلوک نمایش داده شود', 'manacore' ), value: 'show' },
						{ label: __( 'بلوک پنهان شود', 'manacore' ), value: 'hide' },
					],
					onChange: function ( value ) {
						setVisibility( props, { action: value } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( SelectControl, {
					key: 'relation',
					label: __( 'رابطه‌ی میان شرط‌ها', 'manacore' ),
					value: config.relation,
					options: [
						{ label: __( 'همه‌ی شرط‌ها (AND)', 'manacore' ), value: 'AND' },
						{ label: __( 'حداقل یک شرط (OR)', 'manacore' ), value: 'OR' },
					],
					onChange: function ( value ) {
						setVisibility( props, { relation: value } );
					},
					__nextHasNoMarginBottom: true,
				} )
			);

			config.rules.forEach( function ( raw, index ) {
				var rule = Object.assign( {}, RULE_DEFAULT, raw );

				children.push(
					el(
						'div',
						{ className: 'manacore-rule', key: 'rule-' + index },
						el(
							'div',
							{ className: 'manacore-rule-head' },
							el(
								'span',
								{ className: 'manacore-rule-index' },
								__( 'شرط', 'manacore' ) + ' ' + ( index + 1 )
							),
							el(
								Button,
								{
									isDestructive: true,
									variant: 'tertiary',
									size: 'small',
									icon: 'trash',
									label: __( 'حذف شرط', 'manacore' ),
									onClick: function () {
										setVisibility( props, {
											rules: config.rules.filter( function ( item, i ) {
												return i !== index;
											} ),
										} );
									},
								}
							)
						),
						el( SelectControl, {
							label: __( 'نوع شرط', 'manacore' ),
							value: rule.type,
							options: toOptions( vis.ruleTypes ),
							onChange: function ( value ) {
								setRule( props, index, {
									type: value,
									values: [],
									taxonomy: '',
									from: '',
									to: '',
									key: '',
								} );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: __( 'عملگر', 'manacore' ),
							value: rule.operator,
							options: [
								{ label: __( 'برابر باشد (is)', 'manacore' ), value: 'is' },
								{ label: __( 'برابر نباشد (is not)', 'manacore' ), value: 'is_not' },
							],
							onChange: function ( value ) {
								setRule( props, index, { operator: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						ruleValueFields( props, rule, index )
					)
				);
			} );

			children.push(
				el(
					'div',
					{ className: 'manacore-rule-add', key: 'add' },
					el(
						Button,
						{
							variant: 'secondary',
							icon: 'plus-alt2',
							onClick: function () {
								setVisibility( props, {
									rules: config.rules.concat( [ Object.assign( {}, RULE_DEFAULT ) ] ),
								} );
							},
						},
						__( 'افزودن شرط', 'manacore' )
					)
				),
				MultiSelect( {
					key: 'devices',
					label: __( 'پنهان‌سازی در دستگاه‌ها', 'manacore' ),
					help: __( 'در دستگاه‌های انتخاب‌شده این بلوک با CSS پنهان می‌شود.', 'manacore' ),
					value: config.devices,
					options: toOptions( vis.devices ),
					onChange: function ( values ) {
						setVisibility( props, { devices: values } );
					},
				} ),
				el(
					'p',
					{ className: 'manacore-panel-note', key: 'note' },
					__( 'در ویرایشگر همیشه پیش‌نمایش نمایش داده می‌شود؛ شرط‌ها فقط در سایت اعمال می‌شوند.', 'manacore' )
				)
			);
		}

		return el(
			PanelBody,
			{
				key: 'visibility',
				title: __( 'نمایش شرطی', 'manacore' ) + ( active ? ' •' : '' ),
				initialOpen: false,
				className: active ? 'manacore-visibility-active' : '',
			},
			children
		);
	}

	/* -----------------------------------------------------------------
	 * پنل ۱ — محتوا و سرتیتر
	 * -------------------------------------------------------------- */

	var HEADING_ICONS = {
		'': __( 'بدون آیکون', 'manacore' ),
		film: __( 'فیلم', 'manacore' ),
		star: __( 'ستاره', 'manacore' ),
		fire: __( 'داغ', 'manacore' ),
		clock: __( 'ساعت', 'manacore' ),
		eye: __( 'چشم', 'manacore' ),
		grid: __( 'شبکه', 'manacore' ),
		playlist: __( 'فهرست پخش', 'manacore' ),
		heart: __( 'قلب', 'manacore' ),
		crown: __( 'تاج', 'manacore' ),
		compass: __( 'قطب‌نما', 'manacore' ),
		tv: __( 'تلویزیون', 'manacore' ),
		calendar: __( 'تقویم', 'manacore' ),
		person: __( 'چهره', 'manacore' ),
		sparkle: __( 'درخشش', 'manacore' ),
		newspaper: __( 'روزنامه', 'manacore' ),
		info: __( 'اطلاعات', 'manacore' ),
	};

	var TARGET_OPTIONS = [
		{ label: __( 'همین پنجره', 'manacore' ), value: '' },
		{ label: __( 'پنجره‌ی جدید', 'manacore' ), value: '_blank' },
	];

	/**
	 * پنل سرتیتر و پیوند «همه».
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function headerPanel( props ) {
		if ( ! has( props, 'heading' ) ) {
			return null;
		}

		var a = props.attributes;

		return el(
			PanelBody,
			{ key: 'header', title: __( 'محتوا و سرتیتر', 'manacore' ), initialOpen: true },
			el( TextControl, {
				label: __( 'عنوان بخش', 'manacore' ),
				value: a.heading,
				onChange: setter( props, 'heading' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'سطح عنوان', 'manacore' ),
				value: a.headingLevel,
				options: toOptions( data.headingLevels ),
				onChange: setter( props, 'headingLevel' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'آیکون عنوان', 'manacore' ),
				value: a.headingIcon,
				options: toOptions( HEADING_ICONS ),
				onChange: setter( props, 'headingIcon' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( TextareaControl, {
				label: __( 'توضیح زیر عنوان', 'manacore' ),
				value: a.subheading,
				rows: 2,
				onChange: setter( props, 'subheading' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'نمایش پیوند «مشاهده‌ی همه»', 'manacore' ),
				checked: !! a.showMore,
				onChange: setter( props, 'showMore' ),
				__nextHasNoMarginBottom: true,
			} ),
			a.showMore
				? el(
						Fragment,
						null,
						el( TextControl, {
							label: __( 'برچسب پیوند', 'manacore' ),
							value: a.moreLabel,
							onChange: setter( props, 'moreLabel' ),
							__nextHasNoMarginBottom: true,
						} ),
						el( TextControl, {
							label: __( 'آدرس پیوند', 'manacore' ),
							type: 'url',
							value: a.moreUrl,
							onChange: setter( props, 'moreUrl' ),
							__nextHasNoMarginBottom: true,
						} ),
						el( SelectControl, {
							label: __( 'نحوه‌ی باز شدن', 'manacore' ),
							value: a.moreTarget,
							options: TARGET_OPTIONS,
							onChange: setter( props, 'moreTarget' ),
							__nextHasNoMarginBottom: true,
						} )
				  )
				: null
		);
	}

	/* -----------------------------------------------------------------
	 * پنل ۲ و ۳ و ۴ و ۵ — منبع، تاکسونومی، فیلترها، مرتب‌سازی
	 * -------------------------------------------------------------- */

	var TAX_RULE_DEFAULT = {
		taxonomy: '',
		terms: [],
		operator: 'IN',
	};

	/**
	 * به‌روزرسانی یک قاعده‌ی تاکسونومی.
	 *
	 * @param {Object} props props بلوک.
	 * @param {number} index شماره.
	 * @param {Object} patch تغییرات.
	 */
	function setTaxRule( props, index, patch ) {
		var rules = ( props.attributes.taxQuery || [] ).map( function ( rule, i ) {
			if ( i !== index ) {
				return rule;
			}
			return Object.assign( {}, TAX_RULE_DEFAULT, rule, patch );
		} );
		props.setAttributes( { taxQuery: rules } );
	}

	/**
	 * پنل منبع داده و تعداد.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function sourcePanel( props ) {
		if ( ! has( props, 'source' ) ) {
			return null;
		}

		var a = props.attributes;

		return el(
			PanelBody,
			{ key: 'source', title: __( 'منبع و کوئری', 'manacore' ), initialOpen: true },
			el( ToggleControl, {
				label: __( 'ارث‌بری از کوئری صفحه', 'manacore' ),
				help: __( 'در قالب‌های آرشیو، جستجو و تاکسونومی از کوئری اصلی استفاده می‌کند.', 'manacore' ),
				checked: !! a.inheritQuery,
				onChange: setter( props, 'inheritQuery' ),
				__nextHasNoMarginBottom: true,
			} ),
			a.inheritQuery
				? el(
						'p',
						{ className: 'manacore-panel-note' },
						__( 'با فعال بودن ارث‌بری، منبع و بیشتر فیلترها نادیده گرفته می‌شوند.', 'manacore' )
				  )
				: null,
			has( props, 'inheritFilters' )
				? el( ToggleControl, {
						label: __( 'ارث‌بری فیلترهای نشانی', 'manacore' ),
						help: __( 'ژانر، سال، کشور، کیفیت، زبان، مرتب‌سازی و جستجوی نوار فیلتر روی همین حلقه اعمال می‌شوند — مناسب صفحه‌ی «کشف داستان‌ها».', 'manacore' ),
						checked: !! a.inheritFilters,
						onChange: setter( props, 'inheritFilters' ),
						__nextHasNoMarginBottom: true,
				  } )
				: null,
			el( SelectControl, {
				label: __( 'منبع داده', 'manacore' ),
				value: a.source,
				options: toOptions( data.sources ),
				onChange: setter( props, 'source' ),
				__nextHasNoMarginBottom: true,
			} ),
			MultiSelect( {
				label: __( 'نوع‌های محتوا', 'manacore' ),
				help: __( 'خالی = همه‌ی نوع‌های آثار (فیلم، سریال، انیمه).', 'manacore' ),
				value: a.postTypes,
				options: toOptions( data.postTypes ),
				onChange: setter( props, 'postTypes' ),
			} ),
			el( RangeControl, {
				label: __( 'تعداد آیتم', 'manacore' ),
				value: a.count,
				min: 1,
				max: 48,
				onChange: intSetter( props, 'count' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( RangeControl, {
				label: __( 'پرش از ابتدا (offset)', 'manacore' ),
				value: a.offset,
				min: 0,
				max: 100,
				onChange: intSetter( props, 'offset' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'حذف اثر جاری از نتایج', 'manacore' ),
				checked: !! a.excludeCurrent,
				onChange: setter( props, 'excludeCurrent' ),
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	/**
	 * پنل فیلتر تاکسونومی با ساختار تکرارشونده.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function taxonomyPanel( props ) {
		if ( ! has( props, 'taxQuery' ) ) {
			return null;
		}

		var a     = props.attributes;
		var rules = Array.isArray( a.taxQuery ) ? a.taxQuery : [];

		var children = [
			el(
				'p',
				{ className: 'manacore-panel-note', key: 'note' },
				__( 'مشخص کنید این حلقه از کدام دسته، برچسب یا تاکسونومی محتوا بگیرد.', 'manacore' )
			),
		];

		if ( rules.length > 1 ) {
			children.push(
				el( SelectControl, {
					key: 'relation',
					label: __( 'رابطه‌ی میان فیلترها', 'manacore' ),
					value: a.taxRelation,
					options: [
						{ label: __( 'همه (AND)', 'manacore' ), value: 'AND' },
						{ label: __( 'هرکدام (OR)', 'manacore' ), value: 'OR' },
					],
					onChange: setter( props, 'taxRelation' ),
					__nextHasNoMarginBottom: true,
				} )
			);
		}

		rules.forEach( function ( raw, index ) {
			var rule = Object.assign( {}, TAX_RULE_DEFAULT, raw );

			children.push(
				el(
					'div',
					{ className: 'manacore-rule', key: 'tax-' + index },
					el(
						'div',
						{ className: 'manacore-rule-head' },
						el(
							'span',
							{ className: 'manacore-rule-index' },
							__( 'فیلتر', 'manacore' ) + ' ' + ( index + 1 )
						),
						el( Button, {
							isDestructive: true,
							variant: 'tertiary',
							size: 'small',
							icon: 'trash',
							label: __( 'حذف فیلتر', 'manacore' ),
							onClick: function () {
								props.setAttributes( {
									taxQuery: rules.filter( function ( item, i ) {
										return i !== index;
									} ),
								} );
							},
						} )
					),
					el( SelectControl, {
						label: __( 'تاکسونومی', 'manacore' ),
						value: rule.taxonomy,
						options: taxonomyOptions( __( '— انتخاب کنید —', 'manacore' ) ),
						onChange: function ( value ) {
							setTaxRule( props, index, { taxonomy: value, terms: [] } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					rule.taxonomy
						? MultiSelect( {
								label: __( 'ترم‌ها', 'manacore' ),
								value: rule.terms,
								options: termsOf( rule.taxonomy ),
								onChange: function ( values ) {
									setTaxRule( props, index, { terms: values } );
								},
						  } )
						: null,
					el( SelectControl, {
						label: __( 'عملگر', 'manacore' ),
						value: rule.operator,
						options: [
							{ label: __( 'یکی از ترم‌ها (IN)', 'manacore' ), value: 'IN' },
							{ label: __( 'هیچ‌یک از ترم‌ها (NOT IN)', 'manacore' ), value: 'NOT IN' },
							{ label: __( 'همه‌ی ترم‌ها (AND)', 'manacore' ), value: 'AND' },
						],
						onChange: function ( value ) {
							setTaxRule( props, index, { operator: value } );
						},
						__nextHasNoMarginBottom: true,
					} )
				)
			);
		} );

		children.push(
			el(
				'div',
				{ className: 'manacore-rule-add', key: 'add' },
				el(
					Button,
					{
						variant: 'secondary',
						icon: 'plus-alt2',
						onClick: function () {
							props.setAttributes( {
								taxQuery: rules.concat( [ Object.assign( {}, TAX_RULE_DEFAULT ) ] ),
							} );
						},
					},
					__( 'افزودن فیلتر تاکسونومی', 'manacore' )
				)
			)
		);

		return el(
			PanelBody,
			{
				key: 'taxonomy',
				title: __( 'فیلتر تاکسونومی', 'manacore' ) + ( rules.length ? ' •' : '' ),
				initialOpen: false,
			},
			children
		);
	}

	/**
	 * پنل فیلترهای پیشرفته‌ی کوئری.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function filtersPanel( props ) {
		if ( ! has( props, 'minRating' ) ) {
			return null;
		}

		var a = props.attributes;

		return el(
			PanelBody,
			{ key: 'filters', title: __( 'فیلترهای پیشرفته', 'manacore' ), initialOpen: false },
			el( TextControl, {
				label: __( 'جستجوی کلیدواژه', 'manacore' ),
				value: a.search,
				onChange: setter( props, 'search' ),
				__nextHasNoMarginBottom: true,
			} ),
			el(
				'div',
				{ className: 'manacore-grid-two' },
				el( TextControl, {
					label: __( 'از سال', 'manacore' ),
					type: 'number',
					value: a.yearFrom || '',
					onChange: intSetter( props, 'yearFrom' ),
					__nextHasNoMarginBottom: true,
				} ),
				el( TextControl, {
					label: __( 'تا سال', 'manacore' ),
					type: 'number',
					value: a.yearTo || '',
					onChange: intSetter( props, 'yearTo' ),
					__nextHasNoMarginBottom: true,
				} )
			),
			el( RangeControl, {
				label: __( 'حداقل امتیاز', 'manacore' ),
				value: a.minRating,
				min: 0,
				max: 10,
				step: 0.5,
				onChange: function ( value ) {
					props.setAttributes( { minRating: parseFloat( value ) || 0 } );
				},
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'محتوای اشتراکی', 'manacore' ),
				value: a.premiumFilter,
				options: toOptions( data.premiumOptions ),
				onChange: setter( props, 'premiumFilter' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				label: __( 'نوشته‌های ویژه (sticky)', 'manacore' ),
				value: a.stickyMode,
				options: toOptions( data.stickyOptions ),
				onChange: setter( props, 'stickyMode' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'فقط آثار دارای لینک دانلود', 'manacore' ),
				checked: !! a.onlyWithLinks,
				onChange: setter( props, 'onlyWithLinks' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'فقط آثار دارای پوستر', 'manacore' ),
				checked: !! a.onlyWithPoster,
				onChange: setter( props, 'onlyWithPoster' ),
				__nextHasNoMarginBottom: true,
			} ),
			MultiSelect( {
				label: __( 'نویسندگان', 'manacore' ),
				value: a.authors,
				options: [],
				onChange: setter( props, 'authors' ),
				help: __( 'شناسه‌ی نویسنده را وارد و Enter بزنید.', 'manacore' ),
			} ),
			el( TextControl, {
				label: __( 'فقط این شناسه‌ها', 'manacore' ),
				help: __( 'با کاما جدا کنید.', 'manacore' ),
				value: a.includeIds,
				onChange: setter( props, 'includeIds' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( TextControl, {
				label: __( 'حذف این شناسه‌ها', 'manacore' ),
				help: __( 'با کاما جدا کنید.', 'manacore' ),
				value: a.excludeIds,
				onChange: setter( props, 'excludeIds' ),
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	/**
	 * پنل مرتب‌سازی.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function orderPanel( props ) {
		if ( ! has( props, 'orderBy' ) ) {
			return null;
		}

		var a       = props.attributes;
		var isMeta  = 'meta_num' === a.orderBy || 'meta_text' === a.orderBy;
		var noOrder = '' === a.orderBy || 'rand' === a.orderBy;

		return el(
			PanelBody,
			{ key: 'order', title: __( 'مرتب‌سازی', 'manacore' ), initialOpen: false },
			el( SelectControl, {
				label: __( 'مرتب‌سازی بر اساس', 'manacore' ),
				value: a.orderBy,
				options: toOptions( data.orderOptions ),
				onChange: setter( props, 'orderBy' ),
				__nextHasNoMarginBottom: true,
			} ),
			isMeta
				? el( TextControl, {
						label: __( 'کلید فیلد دلخواه', 'manacore' ),
						help: __( 'مثال: manacore_imdb_rating', 'manacore' ),
						value: a.metaOrderKey,
						onChange: setter( props, 'metaOrderKey' ),
						__nextHasNoMarginBottom: true,
				  } )
				: null,
			noOrder
				? null
				: el( SelectControl, {
						label: __( 'جهت مرتب‌سازی', 'manacore' ),
						value: a.order,
						options: [
							{ label: __( 'نزولی (جدید به قدیم)', 'manacore' ), value: 'DESC' },
							{ label: __( 'صعودی (قدیم به جدید)', 'manacore' ), value: 'ASC' },
						],
						onChange: setter( props, 'order' ),
						__nextHasNoMarginBottom: true,
				  } )
		);
	}

	/**
	 * مجموع پنل‌های کوئری.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Array} پنل‌ها.
	 */
	function queryPanels( props ) {
		return [ sourcePanel( props ), taxonomyPanel( props ), filtersPanel( props ), orderPanel( props ) ];
	}

	/* -----------------------------------------------------------------
	 * پنل ۶ — چیدمان
	 * -------------------------------------------------------------- */

	/**
	 * پنل چیدمان و ستون‌بندی واکنش‌گرا.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function layoutPanel( props ) {
		if ( ! has( props, 'columns' ) ) {
			return null;
		}

		var a = props.attributes;

		return el(
			PanelBody,
			{ key: 'layout', title: __( 'چیدمان', 'manacore' ), initialOpen: false },
			has( props, 'layout' )
				? el( SelectControl, {
						label: __( 'نوع چیدمان', 'manacore' ),
						value: a.layout,
						options: toOptions( data.layouts ),
						onChange: setter( props, 'layout' ),
						__nextHasNoMarginBottom: true,
				  } )
				: null,
			el( RangeControl, {
				label: __( 'ستون‌ها — دسکتاپ', 'manacore' ),
				value: a.columns,
				min: 1,
				max: 8,
				onChange: intSetter( props, 'columns' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( RangeControl, {
				label: __( 'ستون‌ها — تبلت', 'manacore' ),
				value: a.columnsTablet,
				min: 1,
				max: 8,
				onChange: intSetter( props, 'columnsTablet' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( RangeControl, {
				label: __( 'ستون‌ها — موبایل', 'manacore' ),
				value: a.columnsMobile,
				min: 1,
				max: 6,
				onChange: intSetter( props, 'columnsMobile' ),
				__nextHasNoMarginBottom: true,
			} ),
			has( props, 'gap' )
				? el( RangeControl, {
						label: __( 'فاصله‌ی میان آیتم‌ها (پیکسل)', 'manacore' ),
						help: __( '۰ = استفاده از فاصله‌ی پیش‌فرض پوسته.', 'manacore' ),
						value: a.gap,
						min: 0,
						max: 64,
						onChange: intSetter( props, 'gap' ),
						__nextHasNoMarginBottom: true,
				  } )
				: null
		);
	}

	/* -----------------------------------------------------------------
	 * پنل ۷ — نمایش کارت
	 * -------------------------------------------------------------- */

	/*
	 * سبک‌های کارت از همان فهرستی خوانده می‌شود که PHP برای اعتبارسنجی
	 * به کار می‌برد (Block_Data::card_styles). فهرست دوم و سخت‌کدشده در
	 * جاوااسکریپت باعث می‌شد گزینه‌ای در ویرایشگر دیده شود که در خروجی
	 * بی‌اثر است — همان اشکالی که در نوار فیلتر هم رخ داده بود.
	 */
	var CARD_STYLES = data.cardStyles && Object.keys( data.cardStyles ).length
		? data.cardStyles
		: {
				poster: __( 'پوستر', 'manacore' ),
				wide: __( 'عریض', 'manacore' ),
				minimal: __( 'مینیمال', 'manacore' ),
				text: __( 'فقط متن', 'manacore' ),
		  };

	var CARD_TOGGLES = [
		[ 'showRating', __( 'امتیاز', 'manacore' ) ],
		[ 'showYear', __( 'سال تولید', 'manacore' ) ],
		[ 'showType', __( 'نوع محتوا', 'manacore' ) ],
		[ 'showQuality', __( 'کیفیت', 'manacore' ) ],
		[ 'showGenre', __( 'ژانر', 'manacore' ) ],
		[ 'showEpisode', __( 'شماره‌ی قسمت', 'manacore' ) ],
		[ 'showViews', __( 'تعداد بازدید', 'manacore' ) ],
		[ 'showRuntime', __( 'مدت زمان', 'manacore' ) ],
		[ 'showPremium', __( 'نشان اشتراکی', 'manacore' ) ],
		[ 'showWatchlist', __( 'دکمه‌ی لیست تماشا', 'manacore' ) ],
		[ 'showOverlay', __( 'پوشش روی پوستر', 'manacore' ) ],
		[ 'showExcerpt', __( 'خلاصه‌ی داستان', 'manacore' ) ],
	];

	/**
	 * پنل اجزای کارت.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function cardPanel( props ) {
		if ( ! has( props, 'cardStyle' ) ) {
			return null;
		}

		var a        = props.attributes;
		var children = [
			el( SelectControl, {
				key: 'style',
				label: __( 'سبک کارت', 'manacore' ),
				value: a.cardStyle,
				options: toOptions( CARD_STYLES ),
				onChange: setter( props, 'cardStyle' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				key: 'ratio',
				label: __( 'نسبت تصویر', 'manacore' ),
				/*
				 * نسبت انتخابی، نسبت پیش‌فرض سبک کارت را بازنویسی می‌کند
				 * (مثلاً «افقی ۱۶:۹» با انتخاب «پوستر ۲:۳» پوستری می‌شود).
				 * این راهنما همان چیزی است که دو کنترل را از «تداخل» به
				 * «تقدم روشن» تبدیل می‌کند. در سبک «فقط متن» تصویری وجود
				 * ندارد، پس کنترل غیرفعال می‌شود.
				 */
				help: 'text' === a.cardStyle
					? __( 'سبک «فقط متن» تصویر ندارد، پس نسبت تصویر اثری ندارد.', 'manacore' )
					: __( 'اگر مقداری انتخاب کنید، نسبت پیش‌فرض سبک کارت را بازنویسی می‌کند.', 'manacore' ),
				disabled: 'text' === a.cardStyle,
				value: a.imageRatio,
				options: toOptions( data.imageRatios ),
				onChange: setter( props, 'imageRatio' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				key: 'titleTag',
				label: __( 'تگ عنوان کارت', 'manacore' ),
				help: __( 'سطح سرتیتر معنایی کارت را تعیین می‌کند؛ ظاهر کارت را عوض نمی‌کند (برای صفحه‌خوان‌ها و ساختار سرفصل‌ها مهم است).', 'manacore' ),
				value: a.titleTag,
				options: toOptions( data.headingLevels ),
				onChange: setter( props, 'titleTag' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( RangeControl, {
				key: 'titleLines',
				label: __( 'حداکثر خطوط عنوان', 'manacore' ),
				help: __( '۰ = بدون محدودیت.', 'manacore' ),
				value: a.titleLines,
				min: 0,
				max: 4,
				onChange: intSetter( props, 'titleLines' ),
				__nextHasNoMarginBottom: true,
			} ),
		];

		/* کارت رتبه‌دار سینورا: تنها برای بلوک‌هایی که ویژگی ranked دارند. */
		if ( has( props, 'ranked' ) ) {
			children.push(
				el( ToggleControl, {
					key: 'ranked',
					label: __( 'شماره‌ی رتبه (کارت رتبه‌دار)', 'manacore' ),
					checked: !! a.ranked,
					onChange: setter( props, 'ranked' ),
					__nextHasNoMarginBottom: true,
				} )
			);
		}

		/*
		 * تب‌های نوع سرصفحه (`.section-tabs` مرجع): همان ردیف «همه / فیلم‌ها /
		 * سریال‌ها» که مرجع در بخش «این روزها، روی بورس» دارد. تنها وقتی
		 * رندر می‌شود که فهرست بیش از یک نوع محتوا داشته باشد.
		 */
		if ( has( props, 'showTypeTabs' ) ) {
			children.push(
				el( ToggleControl, {
					key: 'showTypeTabs',
					label: __( 'تب‌های نوع در سرصفحه (همه / فیلم‌ها / سریال‌ها)', 'manacore' ),
					help: __( 'شبکه را سمت کاربر بر اساس نوع محتوا پالایش می‌کند؛ فقط وقتی فهرست بیش از یک نوع دارد نمایش داده می‌شود.', 'manacore' ),
					checked: !! a.showTypeTabs,
					onChange: setter( props, 'showTypeTabs' ),
					__nextHasNoMarginBottom: true,
				} )
			);
		}

		CARD_TOGGLES.forEach( function ( pair ) {
			if ( ! has( props, pair[ 0 ] ) ) {
				return;
			}
			children.push(
				el( ToggleControl, {
					key: pair[ 0 ],
					label: pair[ 1 ],
					checked: !! a[ pair[ 0 ] ],
					onChange: setter( props, pair[ 0 ] ),
					__nextHasNoMarginBottom: true,
				} )
			);
		} );

		if ( a.showExcerpt ) {
			children.push(
				el( RangeControl, {
					key: 'excerptWords',
					label: __( 'تعداد کلمات خلاصه', 'manacore' ),
					value: a.excerptWords,
					min: 4,
					max: 60,
					onChange: intSetter( props, 'excerptWords' ),
					__nextHasNoMarginBottom: true,
				} )
			);
		}

		children.push(
			el( SelectControl, {
				key: 'target',
				label: __( 'نحوه‌ی باز شدن پیوند کارت', 'manacore' ),
				value: a.cardLinkTarget,
				options: TARGET_OPTIONS,
				onChange: setter( props, 'cardLinkTarget' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( TextControl, {
				key: 'cardClass',
				label: __( 'کلاس CSS هر کارت', 'manacore' ),
				help: __( 'روی تگ <article> هر کارت اعمال می‌شود (برای استایل‌دهی اختصاصی).', 'manacore' ),
				value: a.cardClass,
				onChange: setter( props, 'cardClass' ),
				__nextHasNoMarginBottom: true,
			} )
		);

		return el(
			PanelBody,
			{ key: 'card', title: __( 'نمایش کارت', 'manacore' ), initialOpen: false },
			children
		);
	}

	/* -----------------------------------------------------------------
	 * پنل ۹ — پیشرفته
	 * -------------------------------------------------------------- */

	/**
	 * پنل تنظیمات پیشرفته.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} پنل.
	 */
	function advancedPanel( props ) {
		if ( ! has( props, 'extraClass' ) ) {
			return null;
		}

		var a = props.attributes;

		/*
		 * اینجا دو کنترل «کلاس CSS افزوده» و «شناسه‌ی HTML (لنگر)» حذف شدند:
		 *    ۱) وردپرس خودش پنل «پیشرفته» را با «لنگر HTML» و «کلاس(های)
		 *       اضافی CSS» می‌سازد (چون بلوک `supports.anchor` دارد) و هر دو
		 *       به همان صفت‌های `anchor`/`className` می‌نویسند؛ نتیجه، دو پنل
		 *       هم‌نام و دو فیلد تکراری بود.
		 *    ۲) `className` توسط `get_block_wrapper_attributes()` خودبه‌خود
		 *       روی پوشش بلوک می‌نشیند، پس نیازی به کنترل جداگانه نیست.
		 *
		 * صفت کهنه‌ی `extraClass` برای سازگاری محتوای ذخیره‌شده در
		 * `Block_Support::wrapper()` باقی مانده، اما دیگر کنترل ویرایشگری
		 * ندارد؛ نام این پنل هم به «رفتار بلوک» تغییر کرد تا با پنل
		 * استاندارد «پیشرفته» اشتباه گرفته نشود.
		 */
		return el(
			PanelBody,
			{ key: 'advanced', title: __( 'رفتار بلوک', 'manacore' ), initialOpen: false },
			el( TextControl, {
				label: __( 'متن حالت خالی', 'manacore' ),
				value: a.emptyText,
				onChange: setter( props, 'emptyText' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( ToggleControl, {
				label: __( 'پنهان کردن کل بلوک در صورت خالی بودن', 'manacore' ),
				checked: !! a.hideIfEmpty,
				onChange: setter( props, 'hideIfEmpty' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( RangeControl, {
				label: __( 'مدت کش (ثانیه)', 'manacore' ),
				help: __( '۰ = بدون کش. برای حلقه‌های سنگین مقدار ۳۰۰ پیشنهاد می‌شود.', 'manacore' ),
				value: a.cacheTtl,
				min: 0,
				max: 86400,
				step: 60,
				onChange: intSetter( props, 'cacheTtl' ),
				__nextHasNoMarginBottom: true,
			} )
		);
	}

	/* -----------------------------------------------------------------
	 * پنل‌های ویژه‌ی هر بلوک
	 * -------------------------------------------------------------- */

	/* -----------------------------------------------------------------
	 * گزینش‌گر «اثر» (جست‌وجو در نوع‌های محتوا)
	 * -------------------------------------------------------------- */

	/** نام نوع‌های محتوایی که می‌شود انتخاب کرد و REST آن‌ها هست. */
	function pickableTypes( only ) {
		var all = data.pickableTypes || {};
		var out = [];

		( only && only.length ? only : Object.keys( all ) ).forEach( function ( slug ) {
			if ( all[ slug ] && all[ slug ].restBase ) {
				out.push( { slug: slug, label: all[ slug ].label, restBase: all[ slug ].restBase } );
			}
		} );

		return out;
	}

	/**
	 * جست‌وجوی آثار در REST وردپرس.
	 *
	 * @param {Array}  types  نوع‌های محتوا.
	 * @param {string} search عبارت جست‌وجو (خالی = تازه‌ترین‌ها).
	 * @return {Promise} آرایه‌ی { value, label, slug }.
	 */
	function searchTitles( types, search ) {
		if ( ! apiFetch ) {
			return Promise.resolve( [] );
		}

		var requests = types.map( function ( type ) {
			var path = '/wp/v2/' + type.restBase + '?per_page=20&orderby=' +
				( search ? 'relevance' : 'date' ) +
				( search ? '&search=' + encodeURIComponent( search ) : '' ) +
				'&_fields=id,title';

			return apiFetch( { path: path } )
				.then( function ( list ) {
					return ( list || [] ).map( function ( item ) {
						var title = item.title && item.title.rendered ? item.title.rendered : '#' + item.id;
						return {
							value: String( item.id ),
							label: stripTags( title ) + ' — ' + type.label,
							slug: type.slug,
						};
					} );
				} )
				.catch( function () {
					return [];
				} );
		} );

		return Promise.all( requests ).then( function ( groups ) {
			var out = [];
			groups.forEach( function ( group ) {
				group.forEach( function ( item ) {
					if ( ! out.some( function ( seen ) { return seen.value === item.value; } ) ) {
						out.push( item );
					}
				} );
			} );
			return out;
		} );
	}

	/** حذف تگ‌های HTML از عنوان REST. */
	function stripTags( html ) {
		return String( html ).replace( /<[^>]*>/g, '' ).replace( /&hellip;/g, '…' ).trim();
	}

	/**
	 * کنترل انتخاب اثر — با جست‌وجو، به‌جای وارد‌کردن دستی شناسه.
	 *
	 * ریشه‌ی مشکلی که این کنترل حل می‌کند: پیش‌تر تنها راه انتخاب یک اثر
	 * مشخص (مثلاً سریال میزبانِ قسمت‌ها)، نوشتن «شناسه‌ی عددی» در یک
	 * فیلد متنی بود. مدیر غیر‌کدنویس نه شناسه را می‌دانست و نه راهی برای
	 * یافتنش داشت، پس بلوک «قسمت‌ها» عملاً خالی می‌ماند.
	 *
	 * @param {Object} props props بلوک.
	 * @param {Object} opts  { types, label, help }.
	 * @return {Object} کنترل.
	 */
	function PostPickerControl( props, opts ) {
		opts = opts || {};

		var types    = pickableTypes( opts.types );
		var attr     = opts.attr || 'postId';
		var selected = parseInt( props.attributes[ attr ], 10 ) || 0;
		var setAttr  = props.setAttributes;

		var state = useState( [] );
		var list  = state[ 0 ];
		var setList = state[ 1 ];

		var busy = useState( false );
		var loading = busy[ 0 ];
		var setLoading = busy[ 1 ];

		var query = useState( '' );
		var search = query[ 0 ];
		var setSearch = query[ 1 ];

		var current = useState( null );
		var chosen = current[ 0 ];
		var setChosen = current[ 1 ];

		// فهرست اولیه: تازه‌ترین آثار.
		useEffect( function () {
			var alive = true;
			setLoading( true );

			searchTitles( types, '' ).then( function ( items ) {
				if ( alive ) {
					setList( items );
					setLoading( false );
				}
			} );

			return function () { alive = false; };
		}, [ types.map( function ( t ) { return t.slug; } ).join( ',' ) ] );

		// جست‌وجوی تأخیری هنگام تایپ.
		useEffect( function () {
			var term = ( search || '' ).trim();
			if ( term.length < 2 ) {
				return undefined;
			}

			var alive = true;
			var timer = setTimeout( function () {
				setLoading( true );
				searchTitles( types, term ).then( function ( items ) {
					if ( alive ) {
						setList( items );
						setLoading( false );
					}
				} );
			}, 350 );

			return function () {
				alive = false;
				clearTimeout( timer );
			};
		}, [ search ] );

		// عنوان اثرِ انتخاب‌شده (تا وقتی در فهرست نتایج نیست هم دیده شود).
		useEffect( function () {
			if ( ! selected ) {
				setChosen( null );
				return undefined;
			}

			var known = list.filter( function ( item ) {
				return parseInt( item.value, 10 ) === selected;
			} )[ 0 ];

			if ( known ) {
				setChosen( known );
				return undefined;
			}

			if ( ! apiFetch ) {
				return undefined;
			}

			var alive = true;
			types.forEach( function ( type ) {
				apiFetch( { path: '/wp/v2/' + type.restBase + '/' + selected + '?_fields=id,title' } )
					.then( function ( item ) {
						if ( alive && item && item.id ) {
							setChosen( {
								value: String( item.id ),
								label: stripTags( item.title && item.title.rendered ? item.title.rendered : '' ) + ' — ' + type.label,
								slug: type.slug,
							} );
						}
					} )
					.catch( function () {} );
			} );

			return function () { alive = false; };
		}, [ selected ] );

		var options = useMemo( function () {
			var out = [ { value: '', label: __( 'اثر جاری صفحه (پیش‌فرض)', 'manacore' ) } ];

			if ( chosen && ! list.some( function ( item ) { return item.value === chosen.value; } ) ) {
				out.push( chosen );
			}

			list.forEach( function ( item ) {
				out.push( item );
			} );

			return out;
		}, [ list, chosen ] );

		return el(
			ComboboxControl,
			{
				key: attr + '-picker',
				label: opts.label || __( 'اثر', 'manacore' ),
				help: opts.help || __( 'نام اثر را بنویسید و از فهرست انتخاب کنید. «اثر جاری صفحه» یعنی بلوک همان محتوای صفحه را نشان دهد.', 'manacore' ),
				value: String( selected || '' ),
				options: options,
				onChange: function ( next ) {
					setAttr( ( function () {
						var patch = {};
						patch[ attr ] = parseInt( next, 10 ) || 0;
						return patch;
					} )() );
				},
				onFilterValueChange: setSearch,
				__nextHasNoMarginBottom: true,
			}
		);
	}

	/**
	 * ورودی «اثرِ هدف» برای بلوک‌های تک‌اثری.
	 *
	 * اگر اجزای لازم (apiFetch/ComboboxControl) نبودند، به همان فیلد عددی
	 * قبلی برمی‌گردیم تا ویرایشگر هرگز بی‌کنترل نشود.
	 *
	 * @param {Object} props props بلوک.
	 * @param {Object} opts  { types, label, help }.
	 * @return {Object|null} کنترل.
	 */
	function postIdControl( props, opts ) {
		if ( ! has( props, 'postId' ) ) {
			return null;
		}

		if ( apiFetch && ComboboxControl ) {
			return PostPickerControl( props, opts );
		}

		return el( TextControl, {
			label: __( 'شناسه‌ی اثر', 'manacore' ),
			help: __( '۰ = اثر جاری صفحه.', 'manacore' ),
			type: 'number',
			value: props.attributes.postId || '',
			onChange: intSetter( props, 'postId' ),
			__nextHasNoMarginBottom: true,
		} );
	}

	/**
	 * ساخت پنل ویژه از فهرست کنترل‌های ساده.
	 *
	 * @param {Object} props props بلوک.
	 * @param {string} title عنوان پنل.
	 * @param {Array}  items فهرست کنترل‌ها.
	 * @return {Object} پنل.
	 */
	function optionsPanel( props, title, items ) {
		var children = [];

		items.forEach( function ( item, index ) {
			if ( ! item || ! has( props, item.attr ) ) {
				return;
			}

			var a     = props.attributes;
			var value = a[ item.attr ];
			var base  = { key: item.attr + '-' + index, label: item.label, help: item.help };

			if ( 'toggle' === item.type ) {
				children.push(
					el(
						ToggleControl,
						Object.assign( base, {
							checked: !! value,
							onChange: setter( props, item.attr ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			} else if ( 'select' === item.type ) {
				children.push(
					el(
						SelectControl,
						Object.assign( base, {
							value: value,
							options: item.options,
							onChange: setter( props, item.attr ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			} else if ( 'range' === item.type ) {
				children.push(
					el(
						RangeControl,
						Object.assign( base, {
							value: value,
							min: item.min,
							max: item.max,
							step: item.step,
							onChange: intSetter( props, item.attr ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			} else if ( 'post' === item.type ) {
				children.push(
					postIdControl( props, {
						key: base.key,
						types: item.types,
						label: item.label,
						help: item.help,
						attr: item.attr,
					} )
				);
			} else if ( 'multi' === item.type ) {
				children.push(
					MultiSelect( {
						key: base.key,
						label: item.label,
						help: item.help,
						value: value,
						options: item.options,
						onChange: setter( props, item.attr ),
					} )
				);
			} else if ( 'textarea' === item.type ) {
				children.push(
					el(
						TextareaControl,
						Object.assign( base, {
							rows: item.rows || 3,
							value: value,
							onChange: setter( props, item.attr ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			} else {
				children.push(
					el(
						TextControl,
						Object.assign( base, {
							type: item.type || 'text',
							value: 'number' === item.type ? value || '' : value,
							onChange: 'number' === item.type
								? intSetter( props, item.attr )
								: setter( props, item.attr ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			}
		} );

		if ( ! children.length ) {
			return null;
		}

		return el( PanelBody, { key: 'options', title: title, initialOpen: true }, children );
	}

	var RATING_SOURCES = {
		imdb_rating: 'IMDb',
		tmdb_rating: 'TMDB',
		mal_rating: 'MAL',
		editor_score: __( 'سردبیر', 'manacore' ),
		user_rating: __( 'کاربران', 'manacore' ),
	};

	var ORDER_DIRECTIONS = [
		{ label: __( 'صعودی', 'manacore' ), value: 'ASC' },
		{ label: __( 'نزولی', 'manacore' ), value: 'DESC' },
	];

	/**
	 * پنل‌های ویژه برای هر نام بلوک.
	 *
	 * @param {string} name  نام بلوک.
	 * @param {Object} props props بلوک.
	 * @return {Array} پنل‌ها.
	 */
	function extraPanels( name, props ) {
		var a = props.attributes;

		switch ( name ) {

			case 'manacore/hero-slider':
				return [
					optionsPanel( props, __( 'تنظیمات اسلایدر', 'manacore' ), [
						{
							attr: 'sliderStyle',
							type: 'select',
							label: __( 'سبک ظاهری اسلایدر', 'manacore' ),
							options: toOptions( data.sliderStyles ),
						},
						{
							attr: 'effect',
							type: 'select',
							label: __( 'جلوه‌ی گذر اسلاید', 'manacore' ),
							options: toOptions( data.sliderEffects ),
						},
						{
							attr: 'contentAlign',
							type: 'select',
							label: __( 'جای‌گیری محتوا', 'manacore' ),
							options: toOptions( data.sliderAligns ),
						},
						{
							attr: 'overlay',
							type: 'range',
							label: __( 'شدت پوشش تصویر (٪)', 'manacore' ),
							help: __( 'صفر یعنی استفاده از پوشش پیش‌فرض قالب.', 'manacore' ),
							min: 0,
							max: 100,
						},
						{
							attr: 'height',
							type: 'select',
							label: __( 'ارتفاع اسلایدر', 'manacore' ),
							options: [
								{ label: __( 'کوتاه', 'manacore' ), value: 'small' },
								{ label: __( 'متوسط', 'manacore' ), value: 'medium' },
								{ label: __( 'بلند', 'manacore' ), value: 'large' },
								{ label: __( 'تمام‌صفحه', 'manacore' ), value: 'full' },
							],
						},
						{ attr: 'autoplay', type: 'toggle', label: __( 'پخش خودکار', 'manacore' ) },
						a.autoplay
							? {
									attr: 'interval',
									type: 'range',
									label: __( 'فاصله‌ی تغییر (ثانیه)', 'manacore' ),
									min: 2,
									max: 30,
							  }
							: null,
						{ attr: 'showDots', type: 'toggle', label: __( 'نمایش نقطه‌ها', 'manacore' ) },
						{ attr: 'showArrows', type: 'toggle', label: __( 'نمایش فلش‌ها', 'manacore' ) },
						{ attr: 'showLogo', type: 'toggle', label: __( 'نمایش لوگوی اثر', 'manacore' ) },
						{ attr: 'showMeta', type: 'toggle', label: __( 'نمایش مشخصات', 'manacore' ) },
						{ attr: 'showGenres', type: 'toggle', label: __( 'نمایش ژانرها', 'manacore' ) },
						a.showGenres
							? {
									attr: 'genreCount',
									type: 'range',
									label: __( 'تعداد ژانر', 'manacore' ),
									min: 1,
									max: 6,
							  }
							: null,
						{ attr: 'showExcerpt', type: 'toggle', label: __( 'نمایش خلاصه', 'manacore' ) },
						a.showExcerpt
							? {
									attr: 'excerptWords',
									type: 'range',
									label: __( 'تعداد کلمات خلاصه', 'manacore' ),
									min: 6,
									max: 80,
							  }
							: null,
						{ attr: 'showTrailer', type: 'toggle', label: __( 'دکمه‌ی تریلر', 'manacore' ) },
						{ attr: 'primaryLabel', label: __( 'برچسب دکمه‌ی اصلی', 'manacore' ) },
						{ attr: 'trailerLabel', label: __( 'برچسب دکمه‌ی تریلر', 'manacore' ) },
					] ),
				];

			case 'manacore/title-meta':
				return [
					optionsPanel( props, __( 'مشخصات نمایشی', 'manacore' ), [
						{
							attr: 'fields',
							type: 'multi',
							label: __( 'فیلدهای نمایشی', 'manacore' ),
							help: __( 'خالی = فیلدهای پیش‌فرض.', 'manacore' ),
							options: toOptions( data.metaFields ),
						},
						{
							attr: 'metaLayout',
							type: 'select',
							label: __( 'چیدمان', 'manacore' ),
							options: [
								{ label: __( 'ردیفی', 'manacore' ), value: 'rows' },
								{ label: __( 'دو ستونی', 'manacore' ), value: 'columns' },
								{ label: __( 'درون‌خطی', 'manacore' ), value: 'inline' },
							],
						},
						{ attr: 'linkTerms', type: 'toggle', label: __( 'پیوند دادن ترم‌ها', 'manacore' ) },
						{ attr: 'hideEmpty', type: 'toggle', label: __( 'پنهان کردن فیلدهای خالی', 'manacore' ) },
						{
							attr: 'labelWidth',
							type: 'range',
							label: __( 'عرض ستون برچسب (پیکسل)', 'manacore' ),
							help: __( '۰ = خودکار.', 'manacore' ),
							min: 0,
							max: 260,
							step: 10,
						},
						{ attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ), help: __( 'نام اثر را بنویسید و انتخاب کنید؛ خالی یعنی همین صفحه.', 'manacore' ) },
					] ),
				];

			case 'manacore/download-links':
				return [
					optionsPanel( props, __( 'تنظیمات لینک‌ها', 'manacore' ), [
						{
							/*
							 * حالتِ باکس: فیلم = جدول کیفیت‌ها، سریال =
							 * بسته‌های کامل فصل، قسمت = کیفیت‌های همان
							 * قسمت. «خودکار» از نوع پست تشخیص می‌دهد تا
							 * قالب‌های موجود بدون تغییر کار کنند.
							 */
							attr: 'mode',
							type: 'select',
							label: __( 'حالت نمایش', 'manacore' ),
							help: __( '«خودکار» از نوع محتوا پیروی می‌کند: فیلم ← جدول کیفیت، سریال ← بسته‌های کامل فصل.', 'manacore' ),
							options: [
								{ label: __( 'خودکار (از نوع محتوا)', 'manacore' ), value: 'auto' },
								{ label: __( 'فیلم — جدول کیفیت‌ها', 'manacore' ), value: 'movie' },
								{ label: __( 'سریال — بسته‌های فصل', 'manacore' ), value: 'series' },
								{ label: __( 'قسمت — کیفیت‌های همین قسمت', 'manacore' ), value: 'episode' },
							],
						},
						{
							attr: 'boxStyle',
							type: 'select',
							label: __( 'سبک ظاهری جدول', 'manacore' ),
							help: __( '«کارتی» همان قاب مرجع است؛ «بدون قاب» فقط خط‌های جداکننده دارد.', 'manacore' ),
							options: toOptions( data.downloadStyles ),
						},
						{
							attr: 'packLabel',
							type: 'text',
							label: __( 'برچسب بسته‌ی فصل', 'manacore' ),
							help: __( 'در حالت سریال، زیر کیفیت هر ردیف می‌آید. خالی = «بسته‌ی کامل فصل».', 'manacore' ),
						},
						{
							attr: 'sizeLabel',
							type: 'text',
							label: __( 'عنوان ستون حجم', 'manacore' ),
							help: __( 'خالی = «حجم نمونه».', 'manacore' ),
						},
						{
							/*
							 * منبع ردیف‌های جدول. در سریال‌ها لینک هر قسمت روی
							 * پست همان قسمت ثبت می‌شود؛ «هر دو» بسته‌های کامل
							 * فصل و لینک قسمت‌ها را یک‌جا می‌آورد.
							 */
							attr: 'linkSource',
							type: 'select',
							label: __( 'منبع ردیف‌ها', 'manacore' ),
							help: __( '«خودکار» برای سریال‌ها هر دو منبع را می‌آورد (بسته‌های فصل و لینک قسمت‌ها).', 'manacore' ),
							options: [
								{ label: __( 'خودکار (از نوع محتوا)', 'manacore' ), value: 'auto' },
								{ label: __( 'فقط لینک‌های همین اثر', 'manacore' ), value: 'post' },
								{ label: __( 'فقط لینک‌های قسمت‌ها', 'manacore' ), value: 'episodes' },
								{ label: __( 'هر دو', 'manacore' ), value: 'both' },
							],
						},
						'linkSource-episodes' === a.linkSource || 'both' === a.linkSource || 'auto' === a.linkSource
							? {
								attr: 'episodeLabel',
								type: 'text',
								label: __( 'برچسب ردیف قسمت‌ها', 'manacore' ),
								help: __( 'زیر کیفیت هر ردیف می‌آید؛ `%s` جای شماره‌ی قسمت است. خالی = «قسمت ۲».', 'manacore' ),
							}
							: null,
						{
							attr: 'subtitle',
							type: 'text',
							label: __( 'زیرعنوان', 'manacore' ),
							help: __( 'یک سطر کوتاه زیر سرتیتر، مثل «پخش آنلاین یا دانلود؛ انتخاب با توست.»', 'manacore' ),
						},
						{ attr: 'showTabs', type: 'toggle', label: __( 'تب‌بندی فصل‌ها', 'manacore' ) },
						{ attr: 'showIcon', type: 'toggle', label: __( 'نمایش آیکون بخش', 'manacore' ) },
						{ attr: 'showCount', type: 'toggle', label: __( 'نمایش تعداد لینک', 'manacore' ) },
						{ attr: 'showNotice', type: 'toggle', label: __( 'نمایش هشدار اشتراک', 'manacore' ) },
						{
							attr: 'types',
							type: 'multi',
							label: __( 'نوع‌های مجاز', 'manacore' ),
							help: __( 'خالی = همه‌ی نوع‌ها.', 'manacore' ),
							options: toOptions( data.linkTypes ),
						},
						{
							attr: 'qualities',
							type: 'multi',
							label: __( 'کیفیت‌های مجاز', 'manacore' ),
							help: __( 'خالی = همه‌ی کیفیت‌ها.', 'manacore' ),
							options: toOptions( data.qualities ),
						},
						{
							attr: 'season',
							type: 'number',
							label: __( 'فقط این فصل', 'manacore' ),
							help: __( '۰ = همه‌ی فصل‌ها.', 'manacore' ),
						},
						{ attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ), help: __( 'نام اثر را بنویسید و انتخاب کنید؛ خالی یعنی همین صفحه.', 'manacore' ) },
					] ),
				];

			case 'manacore/rating-box':
				return [
					optionsPanel( props, __( 'تنظیمات امتیاز', 'manacore' ), [
						{ attr: 'showScores', type: 'toggle', label: __( 'نمایش امتیاز منابع', 'manacore' ) },
						a.showScores
							? {
									attr: 'scoreSources',
									type: 'multi',
									label: __( 'منابع امتیاز', 'manacore' ),
									help: __( 'خالی = IMDb، TMDB، MAL و سردبیر.', 'manacore' ),
									options: toOptions( RATING_SOURCES ),
							  }
							: null,
						{ attr: 'showBars', type: 'toggle', label: __( 'نمایش نوار درصدی', 'manacore' ) },
						{ attr: 'showUserRating', type: 'toggle', label: __( 'امتیازدهی کاربران', 'manacore' ) },
						{ attr: 'showSummary', type: 'toggle', label: __( 'نمایش خلاصه‌ی امتیاز', 'manacore' ) },
						{ attr: 'userLabel', label: __( 'برچسب امتیاز کاربران', 'manacore' ) },
						{ attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ), help: __( 'نام اثر را بنویسید و انتخاب کنید؛ خالی یعنی همین صفحه.', 'manacore' ) },
					] ),
				];

			case 'manacore/cast-list':
				return [
					optionsPanel( props, __( 'تنظیمات بازیگران', 'manacore' ), [
						{
							attr: 'layout',
							type: 'select',
							label: __( 'چیدمان', 'manacore' ),
							options: [
								{ label: __( 'ریل افقی', 'manacore' ), value: 'carousel' },
								{ label: __( 'شبکه‌ای', 'manacore' ), value: 'grid' },
							],
						},
						{ attr: 'count', type: 'range', label: __( 'تعداد', 'manacore' ), min: 1, max: 40 },
						{ attr: 'offset', type: 'range', label: __( 'پرش از ابتدا', 'manacore' ), min: 0, max: 30 },
						{ attr: 'columns', type: 'range', label: __( 'ستون‌ها — دسکتاپ', 'manacore' ), min: 2, max: 10 },
						{ attr: 'columnsTablet', type: 'range', label: __( 'ستون‌ها — تبلت', 'manacore' ), min: 2, max: 8 },
						{ attr: 'columnsMobile', type: 'range', label: __( 'ستون‌ها — موبایل', 'manacore' ), min: 1, max: 6 },
						{ attr: 'showPhoto', type: 'toggle', label: __( 'نمایش عکس', 'manacore' ) },
						{ attr: 'showCharacter', type: 'toggle', label: __( 'نمایش نام نقش', 'manacore' ) },
						{
							attr: 'imageRatio',
							type: 'select',
							label: __( 'نسبت تصویر', 'manacore' ),
							options: toOptions( data.imageRatios ),
						},
						{ attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ), help: __( 'نام اثر را بنویسید و انتخاب کنید؛ خالی یعنی همین صفحه.', 'manacore' ) },
					] ),
				];

			case 'manacore/trailer':
				return [
					optionsPanel( props, __( 'تنظیمات تریلر', 'manacore' ), [
						{
							attr: 'videoRatio',
							type: 'select',
							label: __( 'نسبت ویدیو', 'manacore' ),
							options: [
								{ label: '16:9', value: '16-9' },
								{ label: '21:9', value: '21-9' },
								{ label: '4:3', value: '4-3' },
								{ label: '1:1', value: '1-1' },
							],
						},
						{ attr: 'showPlayer', type: 'toggle', label: __( 'نمایش پخش‌کننده', 'manacore' ), help: __( 'خاموش = فقط دکمه‌ی «پخش تریلر» (مناسب سرصفحه‌ی تک‌قسمت).', 'manacore' ) },
						{ attr: 'showPoster', type: 'toggle', label: __( 'نمایش پوستر پیش از پخش', 'manacore' ) },
						{
							attr: 'metaKey',
							label: __( 'کلید فیلد آدرس ویدیو', 'manacore' ),
							help: __( 'پیش‌فرض: manacore_trailer_url', 'manacore' ),
						},
						{ attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ), help: __( 'نام اثر را بنویسید و انتخاب کنید؛ خالی یعنی همین صفحه.', 'manacore' ) },
						{
							attr: 'ctaLabel',
							label: __( 'برچسب دکمه‌ی پخش تریلر', 'manacore' ),
							help: __( 'خالی = «پخش تریلر». دکمه به صفحه‌ی پخش (/watch/) می‌رود و اگر آن صفحه نباشد، پخش‌کننده را در همین صفحه باز می‌کند.', 'manacore' ),
						},
					] ),
				];

			case 'manacore/schedule':
				return [
					optionsPanel( props, __( 'تنظیمات برنامه', 'manacore' ), [
						{ attr: 'perDay', type: 'range', label: __( 'قسمت در هر روز', 'manacore' ), min: 1, max: 12 },
						{
							attr: 'activeDay',
							type: 'select',
							label: __( 'روز فعال در آغاز', 'manacore' ),
							options: [
								{ label: __( 'امروز', 'manacore' ), value: 'today' },
								{ label: __( 'شنبه', 'manacore' ), value: 'saturday' },
								{ label: __( 'یکشنبه', 'manacore' ), value: 'sunday' },
								{ label: __( 'دوشنبه', 'manacore' ), value: 'monday' },
								{ label: __( 'سه‌شنبه', 'manacore' ), value: 'tuesday' },
								{ label: __( 'چهارشنبه', 'manacore' ), value: 'wednesday' },
								{ label: __( 'پنجشنبه', 'manacore' ), value: 'thursday' },
								{ label: __( 'جمعه', 'manacore' ), value: 'friday' },
							],
						},
						{ attr: 'showTime', type: 'toggle', label: __( 'نمایش ساعت پخش', 'manacore' ) },
						{ attr: 'showThumb', type: 'toggle', label: __( 'نمایش تصویر بندانگشتی', 'manacore' ) },
						{ attr: 'showEpisode', type: 'toggle', label: __( 'نمایش فصل/قسمت/زمان', 'manacore' ) },
						{ attr: 'footnote', label: __( 'یادداشت پایین (خالی = پیش‌فرض)', 'manacore' ) },
					] ),

					/*
					 * چیدمان «پنل کامل» (برگه‌ی «برنامه پخش» مرجع) و منبع داده.
					 * گزینه‌های وابسته فقط وقتی نشان داده می‌شوند که معنی داشته
					 * باشند تا پنل صفحه‌ی نخست کوتاه بماند.
					 */
					optionsPanel( props, __( 'چیدمان و منبع', 'manacore' ), [
						{
							attr: 'layout',
							type: 'select',
							label: __( 'چیدمان', 'manacore' ),
							options: [
								{ label: __( 'بلوک (صفحه‌ی نخست)', 'manacore' ), value: 'block' },
								{ label: __( 'پنل کامل (برگه‌ی برنامه پخش)', 'manacore' ), value: 'panel' },
							],
						},
						{
							attr: 'mode',
							type: 'select',
							label: __( 'منبع داده', 'manacore' ),
							help: __( '«قسمت‌ها» از تاریخ پخش قسمت‌ها می‌سازد؛ «آثار زمان‌بندی‌شده» هر اثری را که «روز پخش» دارد فهرست می‌کند.', 'manacore' ),
							options: [
								{ label: __( 'قسمت‌ها', 'manacore' ), value: 'episode' },
								{ label: __( 'آثار زمان‌بندی‌شده', 'manacore' ), value: 'series' },
							],
						},
						'series' === a.mode
							? {
								attr: 'postTypes',
								type: 'multi',
								label: __( 'نوع محتوا', 'manacore' ),
								options: toOptions( data.postTypes ),
							}
							: null,
						'series' === a.mode
							? { attr: 'showSeasonMeta', type: 'toggle', label: __( 'نمایش «فصل n · m قسمت»', 'manacore' ) }
							: null,
						'series' === a.mode
							? { attr: 'showOriginalTitle', type: 'toggle', label: __( 'نمایش نام اصلی', 'manacore' ) }
							: null,
						'series' === a.mode
							? { attr: 'showPlay', type: 'toggle', label: __( 'نمایش دکمه‌ی پخش', 'manacore' ) }
							: null,
					] ),

					a.layout === 'panel'
						? optionsPanel( props, __( 'سرصفحه‌ی پنل', 'manacore' ), [
							{ attr: 'panelTitle', label: __( 'عنوان پنل', 'manacore' ), help: __( 'خالی = «قرارهای این هفته».', 'manacore' ) },
							{ attr: 'panelSubtitle', label: __( 'زیرنویس پنل', 'manacore' ), help: __( 'خالی = «برنامه‌ی هفتگی آثار».', 'manacore' ) },
							{ attr: 'panelIcon', label: __( 'نشانه‌ی پنل (نویسه)', 'manacore' ) },
							{ attr: 'showTimezone', type: 'toggle', label: __( 'نمایش نشانگر زمان', 'manacore' ) },
							a.showTimezone
								? { attr: 'timezoneLabel', label: __( 'متن نشانگر زمان', 'manacore' ), help: __( 'خالی = «به وقت محلی».', 'manacore' ) }
								: null,
							{ attr: 'showFootnote', type: 'toggle', label: __( 'نمایش یادداشت پایین', 'manacore' ) },
						] )
						: null,

					'series' === a.mode
						? optionsPanel( props, __( 'حالت خالی روز', 'manacore' ), [
							{ attr: 'emptyMessage', label: __( 'پیام روز خالی', 'manacore' ) },
							{ attr: 'emptyLinkLabel', label: __( 'برچسب پیوند', 'manacore' ) },
							{
								attr: 'emptyLinkUrl',
								label: __( 'مقصد پیوند', 'manacore' ),
								help: __( 'خالی = برگه‌ی کشف با مرتب‌سازی «امتیاز».', 'manacore' ),
							},
						] )
						: null,
				];

			/*
			 * کارت اطلاعاتی ستون کنار (`.schedule-note-card` و `.sidebar-promo`).
			 * «یادداشت» کارت ساده‌ی متنی است و «ترویجی» یک پیوند ترویجی.
			 */
			case 'manacore/info-card':
				return [
					optionsPanel( props, __( 'کارت', 'manacore' ), [
						{
							attr: 'variant',
							type: 'select',
							label: __( 'گونه', 'manacore' ),
							options: [
								{ label: __( 'یادداشت (کارت اطلاعاتی)', 'manacore' ), value: 'note' },
								{ label: __( 'ترویجی (پیوند)', 'manacore' ), value: 'promo' },
							],
						},
						{ attr: 'iconText', label: __( 'نشانه (نویسه)', 'manacore' ), help: __( 'یک نویسه یا ایموجی؛ فقط در گونه‌ی یادداشت نمایش داده می‌شود.', 'manacore' ) },
						{ attr: 'title', label: __( 'عنوان', 'manacore' ) },
						{ attr: 'text', type: 'textarea', rows: 3, label: __( 'متن', 'manacore' ) },
						'note' === a.variant
							? { attr: 'metaText', label: __( 'خط پایین', 'manacore' ), help: __( 'مثلاً «تمام ساعت‌ها به وقت تهران». خالی = بدون خط پایین.', 'manacore' ) }
							: null,
						'note' === a.variant
							? { attr: 'showMetaDot', type: 'toggle', label: __( 'نقطه‌ی زنده کنار خط پایین', 'manacore' ) }
							: null,
						'a.promo' === a.variant
							? { attr: 'linkLabel', label: __( 'برچسب پیوند', 'manacore' ), help: __( 'خالی = کارت بدون پیوند.', 'manacore' ) }
							: null,
						'a.promo' === a.variant && a.linkLabel
							? {
								attr: 'linkUrl',
								label: __( 'مقصد پیوند', 'manacore' ),
								help: __( 'نشانی معمولی، یا نشانه: `account` / `account:watchlist` (حساب کاربری)، `discovery` (برگه‌ی کشف)، `subscribe` (اشتراک).', 'manacore' ),
							}
							: null,
					] ),
				];

			case 'manacore/taste-banner':
				return [
					optionsPanel( props, __( 'تنظیمات بنر', 'manacore' ), [
						{ attr: 'eyebrow', label: __( 'برچسب بالای تیتر', 'manacore' ) },
						{ attr: 'heading', label: __( 'تیتر', 'manacore' ) },
						{ attr: 'text', label: __( 'توضیح', 'manacore' ) },
						{ attr: 'linkLabel', label: __( 'متن پیوند', 'manacore' ) },
						{ attr: 'linkUrl', label: __( 'نشانی پیوند', 'manacore' ), help: __( 'خالی بگذارید تا پیوندی نمایش داده نشود.', 'manacore' ) },
						{ attr: 'badge', label: __( 'برچسب انگلیسی گوشه', 'manacore' ) },
						{ attr: 'showArt', type: 'toggle', label: __( 'نمایش آیکون‌های تزئینی', 'manacore' ) },
					] ),
				];

			case 'manacore/collection-row':
				return [
					optionsPanel( props, __( 'تنظیمات کالکشن‌ها', 'manacore' ), [
						{ attr: 'count', type: 'range', label: __( 'تعداد', 'manacore' ), min: 1, max: 24 },
						{ attr: 'label', label: __( 'برچسب کوچک کارت', 'manacore' ) },
						{ attr: 'showNumber', type: 'toggle', label: __( 'نمایش شماره‌ی ترتیب', 'manacore' ) },
						{ attr: 'showDescription', type: 'toggle', label: __( 'نمایش توضیح کوتاه', 'manacore' ) },
					] ),
				];

			case 'manacore/magazine-row':
				return [
					optionsPanel( props, __( 'تنظیمات مجله', 'manacore' ), [
						{ attr: 'count', type: 'range', label: __( 'تعداد مقاله', 'manacore' ), min: 1, max: 12 },
						{ attr: 'category', label: __( 'نامک دسته (اختیاری)', 'manacore' ), help: __( 'مثلاً reviews — خالی یعنی همه‌ی مقاله‌ها.', 'manacore' ) },
						{ attr: 'badge', label: __( 'برچسب روی تصویر', 'manacore' ) },
						{ attr: 'showCategoryBadge', type: 'toggle', label: __( 'برچسب = دسته‌ی واقعی مقاله', 'manacore' ) },
						{ attr: 'linkLabel', label: __( 'متن پیوند پایانی', 'manacore' ) },
						{ attr: 'showReadTime', type: 'toggle', label: __( 'نمایش زمان مطالعه', 'manacore' ) },
						{ attr: 'showDate', type: 'toggle', label: __( 'نمایش تاریخ', 'manacore' ) },
						{ attr: 'showExcerpt', type: 'toggle', label: __( 'نمایش خلاصه', 'manacore' ) },
						{ attr: 'excerptWords', type: 'range', label: __( 'شمار واژه‌های خلاصه', 'manacore' ), min: 6, max: 40 },
						{ attr: 'showCategoryTabs', type: 'toggle', label: __( 'تب‌های دسته در سرصفحه', 'manacore' ), help: __( 'دسته‌ها از تاکسونومی واقعیِ همان مقاله‌ها ساخته می‌شوند.', 'manacore' ) },
						{ attr: 'excludeCurrent', type: 'toggle', label: __( 'نوشته‌ی جاری تکرار نشود', 'manacore' ), help: __( 'روی برگه‌ی مقاله، خودِ نوشته در ردیف نیاید.', 'manacore' ) },
						{ attr: 'categoryTabAllLabel', label: __( 'برچسب تب «همه»', 'manacore' ) },
					] ),
				];

			case 'manacore/magazine-hero':
				return [
					optionsPanel( props, __( 'تنظیمات سرصفحه‌ی مجله', 'manacore' ), [
						{ attr: 'count', type: 'range', label: __( 'شمار مقاله‌ها (۱ ویژه + بقیه کوچک)', 'manacore' ), min: 2, max: 8 },
						{ attr: 'category', label: __( 'نامک دسته (اختیاری)', 'manacore' ), help: __( 'خالی یعنی همه‌ی مقاله‌ها.', 'manacore' ) },
						{ attr: 'readLabel', label: __( 'متن زمان مطالعه', 'manacore' ), help: __( '`{count}` با شمار دقیقه‌ها پر می‌شود.', 'manacore' ) },
						{ attr: 'linkLabel', type: 'toggle', label: __( 'نشانه‌ی فلش پایان کارت ویژه', 'manacore' ) },
						{ attr: 'sideIcon', type: 'toggle', label: __( 'ردیف زمان مطالعه در کارت‌های کوچک', 'manacore' ) },
						{ attr: 'showExcerpt', type: 'toggle', label: __( 'نمایش خلاصه‌ی کارت ویژه', 'manacore' ) },
						{ attr: 'excerptWords', type: 'range', label: __( 'شمار واژه‌های خلاصه', 'manacore' ), min: 6, max: 40 },
					] ),
				];

			case 'manacore/article-header':
				return [
					optionsPanel( props, __( 'تنظیمات سرصفحه‌ی مقاله', 'manacore' ), [
						{ attr: 'showCategoryBadge', type: 'toggle', label: __( 'نشان دسته (`.exclusive-tag`)', 'manacore' ) },
						{ attr: 'showDescription', type: 'toggle', label: __( 'نمایش توضیح (چکیده)', 'manacore' ) },
						{ attr: 'showMeta', type: 'toggle', label: __( 'سطر تاریخ و زمان مطالعه', 'manacore' ) },
						{ attr: 'metaFormat', label: __( 'قالب فراداده‌ی نویسنده', 'manacore' ), help: __( '`{date}` و `{minutes}` با تاریخ و شمار دقیقه‌ها پر می‌شوند.', 'manacore' ) },
						{ attr: 'authorFallback', label: __( 'نام نویسنده (وقتی نوشته نویسنده ندارد)', 'manacore' ) },
						{ attr: 'showCopyLink', type: 'toggle', label: __( 'دکمه‌ی کپی لینک', 'manacore' ) },
						{ attr: 'copyLabel', label: __( 'نشان دکمه‌ی کپی', 'manacore' ) },
					] ),
				];

			case 'manacore/article-toc':
				return [
					optionsPanel( props, __( 'تنظیمات فهرست مقاله', 'manacore' ), [
						{ attr: 'eyebrow', label: __( 'ریزسطر بالای کارت', 'manacore' ) },
						{ attr: 'heading', label: __( 'تیتر کارت', 'manacore' ) },
						{ attr: 'showNumbers', type: 'toggle', label: __( 'شماره‌ی فصل‌ها', 'manacore' ) },
						{ attr: 'showProgress', type: 'toggle', label: __( 'نوار پیشرفت مطالعه', 'manacore' ) },
						{ attr: 'percentLabel', label: __( 'متن درصد مطالعه', 'manacore' ), help: __( '`{percent}` با عدد درصد پر می‌شود.', 'manacore' ) },
					] ),
				];

			case 'manacore/related-titles':
				return [
					optionsPanel( props, __( 'تنظیمات آثار مرتبط', 'manacore' ), [
						{ attr: 'heading', label: __( 'تیتر بخش', 'manacore' ) },
						{ attr: 'count', type: 'range', label: __( 'شمار آثار', 'manacore' ), min: 1, max: 8 },
						{
							attr: 'postTypes',
							type: 'multi',
							label: __( 'نوع محتوا', 'manacore' ),
							options: toOptions( data.postTypes ),
						},
						{
							attr: 'postId',
							type: 'post',
							types: [ 'movie', 'series' ],
							label: __( 'اثر پین‌شده (اختیاری)', 'manacore' ),
							help: __( 'نخستین ردیف همین اثر می‌شود؛ بقیه از هم‌برچسب‌ها و سپس تازه‌ترین آثار پر می‌شود.', 'manacore' ),
						},
						{ attr: 'showOriginalTitle', type: 'toggle', label: __( 'نمایش نام اصلی', 'manacore' ) },
						{ attr: 'showMore', type: 'toggle', label: __( 'پیوند پایانی', 'manacore' ) },
						{ attr: 'moreLabel', label: __( 'متن پیوند پایانی', 'manacore' ) },
						{ attr: 'moreUrl', label: __( 'نشانی پیوند پایانی', 'manacore' ) },
					] ),
				];

			case 'manacore/filter-bar':
				return [
					optionsPanel( props, __( 'تنظیمات فیلتر', 'manacore' ), [
						{
							attr: 'taxonomies',
							type: 'multi',
							label: __( 'تاکسونومی‌های فیلتر', 'manacore' ),
							help: __( 'ترتیب انتخاب، ترتیب نمایش فیلدها است.', 'manacore' ),
							options: taxonomyOptions(),
						},
						{
							attr: 'formLayout',
							type: 'select',
							label: __( 'چیدمان فرم', 'manacore' ),
							options: [
								{ label: __( 'درون‌خطی', 'manacore' ), value: 'inline' },
								{ label: __( 'شبکه‌ای', 'manacore' ), value: 'grid' },
								{ label: __( 'ستونی', 'manacore' ), value: 'stack' },
								{ label: __( 'سایدبار پیشرفته (مرجع)', 'manacore' ), value: 'sidebar' },
							],
							help: __( '«سایدبار پیشرفته» همان `.filter-sidebar` مرجع را می‌سازد: سرصفحه، ژانرهای تیک‌زنی، بازه‌ی سال، لغزنده‌ی امتیاز، کلید دوبله، دکمه‌ی پاک‌سازی و پنل راهنما.', 'manacore' ),
						},
						'sidebar' === a.formLayout
							? { attr: 'sidebarTitle', label: __( 'عنوان سایدبار', 'manacore' ), help: __( 'خالی = «فیلتر پیشرفته».', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout
							? {
								attr: 'checkTaxonomy',
								type: 'select',
								label: __( 'تاکسونومی گروه تیک‌زنی', 'manacore' ),
								options: taxonomyOptions(),
							}
							: null,
						'sidebar' === a.formLayout
							? { attr: 'showGenreChecks', type: 'toggle', label: __( 'ژانرهای تیک‌زنی با شمار آثار', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout
							? { attr: 'showYearRange', type: 'toggle', label: __( 'بازه‌ی «سال ساخت»', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showYearRange
							? { attr: 'yearLabel', label: __( 'عنوان «سال ساخت»', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout
							? { attr: 'showRating', type: 'toggle', label: __( 'لغزنده‌ی «امتیاز IMDb»', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showRating
							? { attr: 'ratingLabel', label: __( 'عنوان «امتیاز IMDb»', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showRating
							? { attr: 'ratingMax', type: 'range', label: __( 'بیشینه‌ی لغزنده', 'manacore' ), min: 5, max: 10 }
							: null,
						'sidebar' === a.formLayout
							? { attr: 'showDubbed', type: 'toggle', label: __( 'کلید «فقط دوبله فارسی»', 'manacore' ), help: __( 'روی فراداده‌ی «دوبله فارسی دارد» هر اثر کار می‌کند.', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showDubbed
							? { attr: 'dubbedLabel', label: __( 'برچسب کلید دوبله', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showReset
							? { attr: 'resetLabel', label: __( 'برچسب دکمه‌ی پاک‌سازی', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout
							? { attr: 'showHint', type: 'toggle', label: __( 'پنل «انتخاب سخت شده؟»', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showHint
							? { attr: 'hintTitle', label: __( 'عنوان پنل راهنما', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showHint
							? { attr: 'hintText', label: __( 'متن پنل راهنما', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showHint
							? { attr: 'hintLabel', label: __( 'برچسب پیوند پنل راهنما', 'manacore' ) }
							: null,
						'sidebar' === a.formLayout && a.showHint
							? { attr: 'hintUrl', label: __( 'مقصد پیوند راهنما', 'manacore' ), help: __( 'خالی = «مرتب‌سازی بر امتیاز + امتیاز ۸ به بالا» روی همین آرشیو.', 'manacore' ) }
							: null,
						{ attr: 'showSearch', type: 'toggle', label: __( 'کادر جستجو', 'manacore' ) },
						{ attr: 'showSort', type: 'toggle', label: __( 'گزینه‌ی مرتب‌سازی', 'manacore' ) },
						{ attr: 'showSubmit', type: 'toggle', label: __( 'دکمه‌ی اعمال', 'manacore' ) },
						a.showSubmit
							? { attr: 'submitLabel', label: __( 'برچسب دکمه‌ی اعمال', 'manacore' ) }
							: null,
						{ attr: 'showReset', type: 'toggle', label: __( 'پیوند پاک‌سازی فیلترها', 'manacore' ) },
						{
							attr: 'showActiveChips',
							type: 'toggle',
							label: __( 'نمایش برچسب فیلترهای فعال', 'manacore' ),
							help: __( 'هر برچسب با یک کلیک همان فیلتر را حذف می‌کند.', 'manacore' ),
						},
						{ attr: 'hideEmptyTerms', type: 'toggle', label: __( 'پنهان کردن ترم‌های بدون محتوا', 'manacore' ) },
						{
							attr: 'termLimit',
							type: 'range',
							label: __( 'حداکثر ترم در هر فیلد', 'manacore' ),
							min: 10,
							max: 300,
							step: 10,
						},
					] ),
				];

			case 'manacore/browse-toolbar':
				return [
					optionsPanel( props, __( 'کنترل‌های نوار مرور', 'manacore' ), [
						{ attr: 'showSearch', type: 'toggle', label: __( 'کادر جستجو', 'manacore' ) },
						a.showSearch
							? { attr: 'searchPlaceholder', label: __( 'متن راهنمای جستجو', 'manacore' ) }
							: null,
						{
							attr: 'showTypes',
							type: 'toggle',
							label: __( 'دکمه‌های نوع (همه/فیلم/سریال…)', 'manacore' ),
							help: __( 'مقدار انتخاب‌شده در نشانی می‌رود (`?type=`) و همان حلقه‌ی کشف را صال می‌کند.', 'manacore' ),
						},
						{ attr: 'showSort', type: 'toggle', label: __( 'گزینشگر مرتب‌سازی', 'manacore' ) },
						{ attr: 'showViewMode', type: 'toggle', label: __( 'دکمه‌های حالت نمایش', 'manacore' ) },
						{
							attr: 'showMobileFilter',
							type: 'toggle',
							label: __( 'دکمه‌ی فیلترها برای موبایل', 'manacore' ),
							help: __( 'سایدبار فیلترها را زیر ۷۶۸ پیکسل باز و بسته می‌کند.', 'manacore' ),
						},
						a.showMobileFilter
							? {
								attr: 'filterId',
								label: __( 'شناسه‌ی سایدبار فیلتر', 'manacore' ),
								help: __( 'باید با شناسه‌ی (لنگر) گروهی که بلوک «نوار فیلتر» را در بر گرفته یکی باشد.', 'manacore' ),
							  }
							: null,
					] ),
				];

			case 'manacore/search-box':
				return [
					optionsPanel( props, __( 'تنظیمات جستجو', 'manacore' ), [
						{ attr: 'placeholder', label: __( 'متن راهنمای کادر', 'manacore' ) },
						{
							attr: 'inputSize',
							type: 'select',
							label: __( 'اندازه‌ی کادر', 'manacore' ),
							options: [
								{ label: __( 'کوچک', 'manacore' ), value: 'small' },
								{ label: __( 'متوسط', 'manacore' ), value: 'medium' },
								{ label: __( 'بزرگ', 'manacore' ), value: 'large' },
							],
						},
						{ attr: 'showIcon', type: 'toggle', label: __( 'نمایش آیکون', 'manacore' ) },
						{ attr: 'showButton', type: 'toggle', label: __( 'نمایش دکمه', 'manacore' ) },
						a.showButton
							? { attr: 'buttonLabel', label: __( 'برچسب دکمه', 'manacore' ) }
							: null,
						{ attr: 'liveResults', type: 'toggle', label: __( 'نتایج زنده', 'manacore' ) },
						a.liveResults
							? {
									attr: 'resultCount',
									type: 'range',
									label: __( 'تعداد نتایج زنده', 'manacore' ),
									min: 3,
									max: 20,
							  }
							: null,
						{
							attr: 'searchTypes',
							type: 'multi',
							label: __( 'محدود به نوع‌های محتوا', 'manacore' ),
							help: __( 'خالی = همه‌ی آثار.', 'manacore' ),
							options: toOptions( data.postTypes ),
						},
					] ),
				];

			/*
			 * بلوک‌های برگه‌های تازه (پخش زنده و سرصفحه‌ی مشترک).
			 * هر متن، هر نشانه و هر رفتار از همین‌جا قابل ویرایش است؛
			 * هیچ رشته‌ای در قالب سخت‌کد نشده است.
			 */
			case 'manacore/cta-link':
				return [
					optionsPanel( props, __( 'دکمه‌ی پیوند', 'manacore' ), [
						{ attr: 'label', type: 'text', label: __( 'برچسب دکمه', 'manacore' ) },
						{ attr: 'url', type: 'text', label: __( 'نشانی', 'manacore' ), help: __( 'نشانی کامل یا نشانک ماناکور (مثل `subscribe`، `discovery`، `account`)؛ `#filmography` هم کار می‌کند.', 'manacore' ) },
						{
							attr: 'variant',
							type: 'select',
							label: __( 'گونه', 'manacore' ),
							options: [
								{ label: __( 'اصلی', 'manacore' ), value: 'primary' },
								{ label: __( 'دوم', 'manacore' ), value: 'secondary' },
								{ label: __( 'شیشه‌ای', 'manacore' ), value: 'glass' },
								{ label: __( 'بی‌قاب (متن)', 'manacore' ), value: 'plain' },
							],
						},
						{ attr: 'small', type: 'toggle', label: __( 'اندازه‌ی کوچک', 'manacore' ) },
						{ attr: 'showChevron', type: 'toggle', label: __( 'نشانه‌ی فلش کنار برچسب', 'manacore' ) },
						{ attr: 'target', type: 'text', label: __( 'هدف پیوند (target)', 'manacore' ) },
						{ attr: 'rel', type: 'text', label: __( 'رابطه‌ی پیوند (rel)', 'manacore' ) },
					] ),
				];

			case 'manacore/person-meta':
				return [
					optionsPanel( props, __( 'شناسنامه‌ی چهره', 'manacore' ), [
						{
							attr: 'postId',
							type: 'post',
							label: __( 'چهره', 'manacore' ),
							help: __( 'خالی بگذارید تا چهره‌ی همین برگه استفاده شود.', 'manacore' ),
							types: [ 'person' ],
						},
						{
							attr: 'variant',
							type: 'select',
							label: __( 'گونه', 'manacore' ),
							help: __( '«شناسنامه» برای زیر نام و «نشان نقش» برای گوشه‌ی تصویر قاب چهره.', 'manacore' ),
							options: [
								{ label: __( 'شناسنامه (نام لاتین + دانستنی‌ها)', 'manacore' ), value: 'identity' },
								{ label: __( 'نشان نقش (گوشه‌ی تصویر)', 'manacore' ), value: 'role' },
							],
						},
						{ attr: 'showEnglish', type: 'toggle', label: __( 'نمایش نام لاتین', 'manacore' ) },
						{ attr: 'showBorn', type: 'toggle', label: __( 'نمایش زادروز', 'manacore' ) },
						{ attr: 'showCountry', type: 'toggle', label: __( 'نمایش کشور', 'manacore' ) },
						{ attr: 'showWorks', type: 'toggle', label: __( 'نمایش شمار آثار', 'manacore' ) },
						a.showWorks ? { attr: 'worksLabel', type: 'text', label: __( 'برچسب شمار آثار', 'manacore' ), help: __( '{count} با شمار واقعی آثار همین چهره پر می‌شود.', 'manacore' ) } : null,
						{ attr: 'emptyText', type: 'text', label: __( 'متن حالت خالی', 'manacore' ), help: __( 'وقتی نه نام لاتین و نه دانستنی‌ای ثبت نشده باشد.', 'manacore' ) },
					] ),
				];

			case 'manacore/breadcrumb':
				return [
					optionsPanel( props, __( 'مسیر صفحه', 'manacore' ), [
						{ attr: 'homeLabel', type: 'text', label: __( 'برچسب خانه', 'manacore' ), help: __( 'خالی = نام سایت.', 'manacore' ) },
						{ attr: 'parentLabel', type: 'text', label: __( 'برچسب پله‌ی میانی', 'manacore' ) },
						{ attr: 'parentUrl', type: 'text', label: __( 'نشانی پله‌ی میانی', 'manacore' ), help: __( 'می‌توانید از نشانک‌های ماناکور استفاده کنید؛ مثل `discovery`.', 'manacore' ) },
						{ attr: 'currentLabel', type: 'text', label: __( 'برچسب صفحه‌ی جاری', 'manacore' ), help: __( 'خالی = عنوان خودِ صفحه. `{name}` هم پشتیبانی می‌شود.', 'manacore' ) },
						{
							attr: 'separator',
							type: 'select',
							label: __( 'جدامایه', 'manacore' ),
							options: [
								{ label: __( 'نشانه‌ی فلش (مرجع)', 'manacore' ), value: 'chevron' },
								{ label: __( 'گیومه‌ی فارسی ‹', 'manacore' ), value: 'text' },
							],
						},
					] ),
				];

			case 'manacore/people-grid':
				return [
					optionsPanel( props, __( 'شبکه‌ی چهره‌ها', 'manacore' ), [
						{
							attr: 'variant',
							type: 'select',
							label: __( 'حالت', 'manacore' ),
							help: __( '«کارت بلند» همان `.people-grid` برگه‌ی بازیگران و «کارت کوچک» همان `.cast-grid` بخش «چهره‌های دیگر» است.', 'manacore' ),
							options: [
								{ label: __( 'کارت بلند (۳:۴ با نشان نقش)', 'manacore' ), value: 'cards' },
								{ label: __( 'کارت کوچک (بخش چهره‌های دیگر)', 'manacore' ), value: 'related' },
							],
						},
						{ attr: 'count', type: 'range', min: 1, max: 60, label: __( 'شمار کارت‌ها', 'manacore' ) },
						'cards' === a.variant ? { attr: 'columns', type: 'range', min: 1, max: 6, label: __( 'ستون‌ها (دسکتاپ)', 'manacore' ) } : null,
						{
							attr: 'roles',
							type: 'multi',
							label: __( 'پالایش نقش', 'manacore' ),
							help: __( 'خالی = همه‌ی نقش‌ها. نقش‌ها از تاکسونومی «نقش‌های چهره» می‌آیند و از پیشخوان قابل افزودن‌اند.', 'manacore' ),
							options: toOptions( data.personRoles ),
						},
						{
							attr: 'orderby',
							type: 'select',
							label: __( 'ترتیب', 'manacore' ),
							options: [
								{ label: __( 'دستی (ترتیب صفحه‌ی فهرست)', 'manacore' ), value: 'menu_order' },
								{ label: __( 'الفبا', 'manacore' ), value: 'title' },
								{ label: __( 'تاریخ', 'manacore' ), value: 'date' },
								{ label: __( 'تصادفی', 'manacore' ), value: 'random' },
							],
						},
						{ attr: 'excludeCurrent', type: 'toggle', label: __( 'حذف چهره‌ی جاری', 'manacore' ), help: __( 'در برگه‌ی چهره، خودش تکرار نشود.', 'manacore' ) },
						{ attr: 'showWorks', type: 'toggle', label: __( 'نمایش شمار آثار', 'manacore' ) },
						{ attr: 'worksLabel', type: 'text', label: __( 'برچسب شمار آثار', 'manacore' ), help: __( '{count} با شمار واقعی آثار همان چهره پر می‌شود.', 'manacore' ) },
						{ attr: 'emptyHeading', type: 'text', label: __( 'تیتر حالت خالی', 'manacore' ) },
						{ attr: 'emptyText', type: 'text', label: __( 'متن حالت خالی', 'manacore' ) },
						{ attr: 'countLabel', type: 'text', label: __( 'الگوی شمار نتایج', 'manacore' ), help: __( 'برای صفحه‌خوان‌ها؛ {count} با شمار نتایج پر می‌شود.', 'manacore' ) },
					] ),
				];

			case 'manacore/page-intro':
				return [
					optionsPanel( props, __( 'سرصفحه', 'manacore' ), [
						{
							attr: 'layout',
							type: 'select',
							label: __( 'چیدمان', 'manacore' ),
							help: __( '«دوستونی» همان `.page-title-row` مرجع است (پخش زنده، بازیگران)، «میانی» همان `.info-intro` (راهنما، حریم خصوصی) و «مجله» همان `.magazine-intro`.', 'manacore' ),
							options: [
								{ label: __( 'دوستونی (عنوان + ساعت/آیکون)', 'manacore' ), value: 'split' },
								{ label: __( 'میانی (آیکون بالای عنوان)', 'manacore' ), value: 'centered' },
								{ label: __( 'مجله‌ای (عنوان بزرگ)', 'manacore' ), value: 'magazine' },
							],
						},
						{ attr: 'eyebrow', type: 'text', label: __( 'ریزسطر بالای عنوان', 'manacore' ) },
						{ attr: 'title', type: 'text', label: __( 'عنوان', 'manacore' ) },
						{
							attr: 'accent',
							type: 'text',
							label: __( 'واژه‌ی تأکیدی عنوان', 'manacore' ),
							help: __( 'با رنگ تأکیدی و در ادامه‌ی عنوان نمایش داده می‌شود.', 'manacore' ),
						},
						{ attr: 'text', type: 'textarea', label: __( 'توضیح کوتاه', 'manacore' ) },
						{
							attr: 'headingTag',
							type: 'select',
							label: __( 'سطح عنوان', 'manacore' ),
							help: __( 'برای دسترس‌پذیری برگه در هر صفحه فقط یک `h1` بگذارید.', 'manacore' ),
							options: [
								{ label: __( 'h1 (سرصفحه‌ی برگه)', 'manacore' ), value: 'h1' },
								{ label: __( 'h2 (بخش صفحه)', 'manacore' ), value: 'h2' },
							],
						},
						{
							attr: 'icon',
							type: 'select',
							label: __( 'نشانه', 'manacore' ),
							options: toOptions( data.headingIcons ),
						},
						{ attr: 'iconBox', type: 'toggle', label: __( 'نشانه در جعبه‌ی تاج‌مانند', 'manacore' ) },
						{ attr: 'showBreadcrumb', type: 'toggle', label: __( 'نمایش مسیر راهنما', 'manacore' ) },
						a.showBreadcrumb ? { attr: 'breadcrumbHome', type: 'text', label: __( 'برچسب خانه', 'manacore' ), help: __( 'خالی = نام سایت.', 'manacore' ) } : null,
						a.showBreadcrumb ? { attr: 'breadcrumbLabel', type: 'text', label: __( 'برچسب برگه‌ی جاری', 'manacore' ), help: __( 'خالی = خودِ عنوان.', 'manacore' ) } : null,
						{ attr: 'showClock', type: 'toggle', label: __( 'ساعت زنده', 'manacore' ) },
						a.showClock ? { attr: 'clockLabel', type: 'text', label: __( 'برچسب ساعت', 'manacore' ) } : null,
						a.showClock ? {
							attr: 'clockTimezone',
							type: 'text',
							label: __( 'منطقه‌ی زمانی', 'manacore' ),
							help: __( 'مثل `Asia/Tehran`؛ ساعت با ارقام فارسی و بدون بازخوانی صفحه به‌روز می‌شود.', 'manacore' ),
						} : null,
					] ),
				];

			case 'manacore/live-player':
				return [
					optionsPanel( props, __( 'پخش زنده', 'manacore' ), [
						{
							attr: 'channelId',
							type: 'post',
							label: __( 'کانال پیش‌فرض', 'manacore' ),
							help: __( '۰ = کانال نخست یا کانالی که در نشانی آمده (`?channel=`).', 'manacore' ),
							postType: 'channel',
						},
						{ attr: 'titleOverride', type: 'text', label: __( 'عنوان دلخواه (اختیاری)', 'manacore' ), help: __( 'خالی = زیرعنوان کانال.', 'manacore' ) },
						{ attr: 'onAirLabel', type: 'text', label: __( 'برچسب نشان «در حال پخش»', 'manacore' ) },
						{ attr: 'nowLabel', type: 'text', label: __( 'برچسب «همین حالا»', 'manacore' ) },
						{ attr: 'autoplay', type: 'toggle', label: __( 'پخش خودکار (بی‌صدا)', 'manacore' ) },
						{ attr: 'showNotice', type: 'toggle', label: __( 'نمایش یادداشت زیر پخش‌کننده', 'manacore' ) },
						a.showNotice ? { attr: 'noticeText', type: 'textarea', label: __( 'متن یادداشت', 'manacore' ) } : null,
						{ attr: 'errorText', type: 'text', label: __( 'پیام خطای ویدئو', 'manacore' ) },
						{ attr: 'retryLabel', type: 'text', label: __( 'برچسب دکمه‌ی تلاش دوباره', 'manacore' ) },
					] ),
					optionsPanel( props, __( 'قاب‌های امروز', 'manacore' ), [
						{ attr: 'heading', type: 'text', label: __( 'عنوان بخش', 'manacore' ) },
						{ attr: 'showProgram', type: 'toggle', label: __( 'نمایش شبکه‌ی قاب‌ها', 'manacore' ) },
						a.showProgram ? {
							attr: 'programCount',
							type: 'range',
							label: __( 'شمار قاب‌ها', 'manacore' ),
							min: 1,
							max: 12,
						} : null,
						a.showProgram ? {
							attr: 'programTypes',
							type: 'multi',
							label: __( 'نوع‌های محتوا',
							'manacore' ),
							options: toOptions( data.postTypes ),
						} : null,
						a.showProgram ? {
							attr: 'programFallback',
							type: 'toggle',
							label: __( 'اگر امروز برنامه‌ای نبود، نزدیک‌ترین روز', 'manacore' ),
							help: __( 'داده از فراداده‌ی «روز پخش» و «ساعت پخش» خودِ آثار می‌آید.', 'manacore' ),
						} : null,
						a.showProgram ? { attr: 'scheduleLabel', type: 'text', label: __( 'برچسب قاب‌های زمان‌بندی‌شده', 'manacore' ) } : null,
						a.showProgram ? { attr: 'fallbackLabel', type: 'text', label: __( 'برچسب قاب‌های روز نزدیک', 'manacore' ) } : null,
						a.showProgram ? { attr: 'clockGlyph', type: 'text', label: __( 'نشانه‌ی ساعت', 'manacore' ) } : null,
					] ),
				];

			case 'manacore/live-channels':
				return [
					optionsPanel( props, __( 'فهرست کانال‌ها', 'manacore' ), [
						{ attr: 'heading', type: 'text', label: __( 'عنوان ستون', 'manacore' ) },
						{
							attr: 'headingIcon',
							type: 'select',
							label: __( 'نشانه‌ی عنوان', 'manacore' ),
							options: toOptions( data.headingIcons ),
						},
						{
							attr: 'countLabel',
							type: 'text',
							label: __( 'برچسب شمار',
							'manacore' ),
							help: __( '`{count}` با شمار واقعی کانال‌ها جایگزین می‌شود.', 'manacore' ),
						},
						{ attr: 'onlineLabel', type: 'text', label: __( 'برچسب کانال در حال پخش', 'manacore' ) },
						{ attr: 'qualitySuffix', type: 'text', label: __( 'پسوند زیر نام کانال', 'manacore' ) },
						{
							attr: 'limit',
							type: 'number',
							label: __( 'بیشترین شمار کانال', 'manacore' ),
							help: __( '۰ = همه‌ی کانال‌ها.', 'manacore' ),
						},
					] ),
					optionsPanel( props, __( 'کارت یادداشت', 'manacore' ), [
						{ attr: 'showNote', type: 'toggle', label: __( 'نمایش کارت یادداشت', 'manacore' ) },
						a.showNote ? {
							attr: 'noteIcon',
							type: 'select',
							label: __( 'نشانه‌ی یادداشت', 'manacore' ),
							help: __( 'مرجع این کارت را بی‌نشانه می‌سازد؛ فقط اگر خواستید نشانه بگذارید.', 'manacore' ),
							options: [ { label: __( '— بدون نشانه —', 'manacore' ), value: '' } ].concat(
								toOptions( data.headingIcons )
							),
						} : null,
						a.showNote ? { attr: 'noteTitle', type: 'text', label: __( 'عنوان یادداشت', 'manacore' ) } : null,
						a.showNote ? { attr: 'noteText', type: 'textarea', label: __( 'متن یادداشت', 'manacore' ) } : null,
						a.showNote ? { attr: 'noteLinkLabel', type: 'text', label: __( 'برچسب پیوند', 'manacore' ) } : null,
						a.showNote ? {
							attr: 'noteLinkUrl',
							type: 'text',
							label: __( 'نشانی پیوند', 'manacore' ),
							help: __( 'می‌توانید از نشانه‌های کوتاه مثل `discovery` هم استفاده کنید.', 'manacore' ),
						} : null,
					] ),
				];

			/*
			 * بلوک‌های برگه‌ی «حساب کاربری» — هم‌ارز `account.html` مرجع.
			 * هر متن و هر نشانه‌ی این بلوک‌ها از همین‌جا قابل ویرایش است.
			 */
			case 'manacore/account-requests':
				return [
					optionsPanel( props, __( 'پنل درخواست‌ها', 'manacore' ), [
						{ attr: 'showForm', type: 'toggle', label: __( 'نمایش فرم ثبت درخواست', 'manacore' ), help: __( 'خاموش کردنش فقط فهرست درخواست‌های کاربر را نگه می‌دارد.', 'manacore' ) },
						{ attr: 'formHeading', type: 'text', label: __( 'عنوان فرم', 'manacore' ), help: __( 'خالی بگذارید تا عنوان پیش‌فرض تنظیمات درخواست‌ها بیاید.', 'manacore' ) },
						{ attr: 'formIntro', type: 'textarea', label: __( 'توضیح فرم', 'manacore' ) },
						{ attr: 'formButton', type: 'text', label: __( 'برچسب دکمه‌ی فرم', 'manacore' ) },
						{ attr: 'perPage', type: 'range', label: __( 'حداکثر درخواست در فهرست', 'manacore' ), min: 1, max: 60 },
						{ attr: 'emptyTitle', type: 'text', label: __( 'عنوان حالت خالی', 'manacore' ) },
						{ attr: 'emptyText', type: 'textarea', label: __( 'متن حالت خالی', 'manacore' ) },
					] ),
				];

			case 'manacore/account-requests':
				return [
					optionsPanel( props, __( 'پنل درخواست‌ها', 'manacore' ), [
						{ attr: 'showForm', type: 'toggle', label: __( 'نمایش فرم ثبت درخواست', 'manacore' ), help: __( 'خاموش کردنش فقط فهرست درخواست‌های کاربر را نگه می‌دارد.', 'manacore' ) },
						{ attr: 'formHeading', type: 'text', label: __( 'عنوان فرم', 'manacore' ), help: __( 'خالی بگذارید تا عنوان پیش‌فرض تنظیمات درخواست‌ها بیاید.', 'manacore' ) },
						{ attr: 'formIntro', type: 'textarea', label: __( 'توضیح فرم', 'manacore' ) },
						{ attr: 'formButton', type: 'text', label: __( 'برچسب دکمه‌ی فرم', 'manacore' ) },
						{ attr: 'perPage', type: 'range', label: __( 'حداکثر درخواست در فهرست', 'manacore' ), min: 1, max: 60 },
						{ attr: 'emptyTitle', type: 'text', label: __( 'عنوان حالت خالی', 'manacore' ) },
						{ attr: 'emptyText', type: 'textarea', label: __( 'متن حالت خالی', 'manacore' ) },
					] ),
				];

			case 'manacore/account-greeting':
				return [
					optionsPanel( props, __( 'تنظیمات سرصفحه', 'manacore' ), [
						{
							attr: 'eyebrow',
							type: 'text',
							label: __( 'ریزسطر بالای عنوان', 'manacore' ),
						},
						{
							attr: 'guestHeading',
							type: 'text',
							label: __( 'عنوان برای مهمان', 'manacore' ),
						},
						{
							attr: 'userHeading',
							type: 'text',
							label: __( 'عنوان برای کاربر وارد‌شده', 'manacore' ),
							help: __( '«{name}» با نام نمایشی کاربر جایگزین می‌شود.', 'manacore' ),
						},
						{
							attr: 'text',
							type: 'textarea',
							label: __( 'توضیح کوتاه', 'manacore' ),
						},
						{
							attr: 'guestButtonLabel',
							type: 'text',
							label: __( 'برچسب دکمه برای مهمان', 'manacore' ),
						},
						{
							attr: 'userButtonLabel',
							type: 'text',
							label: __( 'برچسب دکمه برای کاربر وارد‌شده', 'manacore' ),
						},
						{
							attr: 'loginButtonLabel',
							type: 'text',
							label: __( 'برچسب دکمه وقتی ثبت‌نام بسته است', 'manacore' ),
						},
						{
							attr: 'buttonUrl',
							type: 'text',
							label: __( 'نشانی دکمه', 'manacore' ),
							help: __( 'خالی بگذارید تا مقصد هوشمند انتخاب شود: مهمان → ساخت حساب، کاربر → تب مقصد.', 'manacore' ),
						},
						{
							attr: 'buttonTab',
							type: 'select',
							label: __( 'تب مقصد دکمه', 'manacore' ),
							options: toOptions( data.accountTabs ),
						},
						{
							attr: 'showButton',
							type: 'toggle',
							label: __( 'نمایش دکمه', 'manacore' ),
						},
					] ),
				];

			case 'manacore/account-nav':
				return [
					optionsPanel( props, __( 'تنظیمات ستون کنار', 'manacore' ), [
						{
							attr: 'navLabel',
							type: 'text',
							label: __( 'برچسب ناوبری (برای صفحه‌خوان)', 'manacore' ),
						},
						{
							attr: 'showProfile',
							type: 'toggle',
							label: __( 'نمایش کارت پروفایل', 'manacore' ),
						},
						{
							attr: 'showLogout',
							type: 'toggle',
							label: __( 'نمایش «خروج از حساب»', 'manacore' ),
						},
						{
							attr: 'showUpgrade',
							type: 'toggle',
							label: __( 'نمایش کارت ارتقا', 'manacore' ),
						},
						{
							attr: 'upgradeHeading',
							type: 'text',
							label: __( 'عنوان کارت ارتقا', 'manacore' ),
						},
						{
							attr: 'upgradeLabel',
							type: 'text',
							label: __( 'برچسب پیوند کارت ارتقا', 'manacore' ),
						},
						{
							attr: 'upgradeUrl',
							type: 'text',
							label: __( 'نشانی کارت ارتقا', 'manacore' ),
							help: __( 'خالی بگذارید تا برگه‌ی اشتراک خودِ افزونه استفاده شود.', 'manacore' ),
						},
					] ),
				];

			case 'manacore/account-stats':
				return [
					optionsPanel( props, __( 'شمارنده‌ها', 'manacore' ), [
						{ attr: 'showWatchlist', type: 'toggle', label: __( 'کارت «لیست تماشا»', 'manacore' ) },
						{ attr: 'showWatched', type: 'toggle', label: __( 'کارت «دیده‌شده»', 'manacore' ) },
						{ attr: 'showMinutes', type: 'toggle', label: __( 'کارت «دقیقه تماشا»', 'manacore' ) },
						{ attr: 'showGenre', type: 'toggle', label: __( 'کارت «ژانر موردعلاقه»', 'manacore' ) },
						{ attr: 'genreEmptyText', type: 'text', label: __( 'متن ژانر پیش از کشف', 'manacore' ) },
						{ attr: 'watchlistLabel', type: 'text', label: __( 'برچسب کارت لیست تماشا', 'manacore' ) },
						{ attr: 'watchedLabel', type: 'text', label: __( 'برچسب کارت دیده‌شده', 'manacore' ) },
						{ attr: 'minutesLabel', type: 'text', label: __( 'برچسب کارت دقیقه', 'manacore' ) },
						{ attr: 'genreLabel', type: 'text', label: __( 'برچسب کارت ژانر', 'manacore' ) },
						{ attr: 'watchlistIcon', type: 'text', label: __( 'نشانه‌ی کارت لیست تماشا', 'manacore' ) },
						{ attr: 'watchedIcon', type: 'text', label: __( 'نشانه‌ی کارت دیده‌شده', 'manacore' ) },
						{ attr: 'minutesIcon', type: 'text', label: __( 'نشانه‌ی کارت دقیقه', 'manacore' ) },
						{ attr: 'genreIcon', type: 'text', label: __( 'نشانه‌ی کارت ژانر', 'manacore' ) },
					] ),
				];

			case 'manacore/episodes-list':
				return [
					optionsPanel( props, __( 'تنظیمات قسمت‌ها', 'manacore' ), [
						{
							attr: 'boxStyle',
							type: 'select',
							label: __( 'سبک ظاهری جعبه', 'manacore' ),
							help: __( 'همان فهرست بلوک «لینک‌های دانلود»؛ «کارتی» قاب مرجع است.', 'manacore' ),
							options: toOptions( data.downloadStyles ),
						},
						{ attr: 'showNotice', type: 'toggle', label: __( 'نمایش هشدار اشتراک', 'manacore' ) },
						{
							attr: 'showPackList',
							type: 'toggle',
							label: __( 'نمایش بسته‌های کامل فصل', 'manacore' ),
							help: __( 'لینک‌هایی که روی خودِ سریال ثبت شده‌اند (مثل «دانلود کامل فصل ۱») بالای قسمت‌های هر فصل می‌آید.', 'manacore' ),
						},
						{
							attr: 'packTitle',
							type: 'text',
							label: __( 'برچسب بسته‌ی فصل', 'manacore' ),
							help: __( 'خالی = «بسته‌ی کامل فصل».', 'manacore' ),
						},
						{
							attr: 'sizeLabel',
							type: 'text',
							label: __( 'عنوان ستون حجم', 'manacore' ),
							help: __( 'خالی = «حجم نمونه».', 'manacore' ),
						},
						{
							attr: 'playLabel',
							type: 'text',
							label: __( 'برچسب دکمه‌ی پخش', 'manacore' ),
							help: __( 'در راهنمای دسترس‌پذیری دکمه‌ی ▶ استفاده می‌شود. «%s» = شماره‌ی قسمت.', 'manacore' ),
						},
						{
							attr: 'seasonNumber',
							type: 'number',
							label: __( 'فقط این فصل', 'manacore' ),
							help: __( '۰ = همه‌ی فصل‌ها.', 'manacore' ),
						},
						{
							attr: 'seasonOrder',
							type: 'select',
							label: __( 'ترتیب فصل‌ها', 'manacore' ),
							options: ORDER_DIRECTIONS,
						},
						{
							attr: 'episodeOrder',
							type: 'select',
							label: __( 'ترتیب قسمت‌ها', 'manacore' ),
							options: ORDER_DIRECTIONS,
						},
						{
							attr: 'openSeason',
							type: 'select',
							label: __( 'فصل باز به‌صورت پیش‌فرض', 'manacore' ),
							options: [
								{ label: __( 'اولین فصل', 'manacore' ), value: 'first' },
								{ label: __( 'آخرین فصل', 'manacore' ), value: 'last' },
								{ label: __( 'همه‌ی فصل‌ها', 'manacore' ), value: 'all' },
								{ label: __( 'هیچ‌کدام', 'manacore' ), value: 'none' },
							],
						},
						{
							attr: 'limit',
							type: 'range',
							label: __( 'حداکثر قسمت در هر فصل', 'manacore' ),
							help: __( '۰ = بدون محدودیت.', 'manacore' ),
							min: 0,
							max: 100,
						},
						{ attr: 'showEpisodeName', type: 'toggle', label: __( 'نمایش نام قسمت', 'manacore' ) },
						{ attr: 'showAirDate', type: 'toggle', label: __( 'نمایش تاریخ پخش', 'manacore' ) },
						{ attr: 'showCount', type: 'toggle', label: __( 'نمایش تعداد قسمت‌ها', 'manacore' ) },
						{ attr: 'showThumb', type: 'toggle', label: __( 'نمایش بندانگشتی', 'manacore' ) },
						{ attr: 'postId', type: 'post', types: [ 'series', 'anime' ], label: __( 'سریال / انیمه', 'manacore' ), help: __( 'سریالی را انتخاب کنید تا قسمت‌های همان سریال نمایش داده شود. «اثر جاری صفحه» یعنی قسمت‌های سریالِ همین صفحه.', 'manacore' ) },
					] ),
				];
		}

		return [ postIdControl( props ) ? optionsPanel( props, __( 'تنظیمات', 'manacore' ), [ { attr: 'postId', type: 'post', label: __( 'اثر', 'manacore' ) } ] ) : null ];
	}

	/* -----------------------------------------------------------------
	 * ثبت بلوک‌ها
	 * -------------------------------------------------------------- */

	/**
	 * ساخت تابع ویرایش برای یک بلوک.
	 *
	 * @param {string} name نام بلوک.
	 * @return {Function} کامپوننت.
	 */
	function makeEdit( name ) {
		return function Edit( props ) {
			var blockProps = useBlockProps();

			var panels = [ headerPanel( props ) ]
				.concat( extraPanels( name, props ) )
				.concat( queryPanels( props ) )
				.concat( [ layoutPanel( props ), cardPanel( props ), visibilityPanel( props ), advancedPanel( props ) ] )
				.filter( Boolean );

			var preview = ServerSideRender
				? el( ServerSideRender, {
						block: name,
						attributes: props.attributes,
						httpMethod: 'POST',
				  } )
				: el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__( 'پیش‌نمایش سمت سرور در دسترس نیست.', 'manacore' )
				  );

			return el(
				Fragment,
				null,
				el( InspectorControls, null, panels ),
				el( 'div', blockProps, el( 'div', { className: 'manacore-block-preview' }, preview ) )
			);
		};
	}

	var registry = window.manaCoreBlockRegistry || {};

	Object.keys( registry ).forEach( function ( name ) {
		var config = registry[ name ] || {};

		// «تب حساب کاربری» ویرایشگر دستی دارد (InnerBlocks) و در حلقه ثبت نمی‌شود.
		if ( 'manacore/account-panel' === name ) {
			return;
		}

		// اگر هسته پیش‌تر نسخه‌ی سمت سرور را در فهرست ویرایشگر ثبت کرده باشد،
		// ابتدا آن را برمی‌داریم تا ثبت مجدد خطای «قبلاً ثبت شده» ندهد.
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
			edit: makeEdit( name ),
			save: function () {
				return null;
			},
		} );
	} );


	/*
	 * «تب حساب کاربری» تنها بلوک برگه‌ی حساب است که محتوای درونی می‌پذیرد و
	 * ویرایشگر دستی دارد: پیش‌نمایش سمت سرور (`ServerSideRender`) با
	 * `InnerBlocks` سازگار نیست. بچه‌ها در بوم ویرایش می‌شوند و سمت سرور
	 * درست همان مارک‌آپ مرجع رندر می‌شود: `section[data-panel]`.
	 */
	( function () {
		var name   = 'manacore/account-panel';
		var config = registry[ name ];

		if ( ! config || ! InnerBlocks ) {
			return;
		}

		if ( wp.blocks.getBlockType( name ) ) {
			wp.blocks.unregisterBlockType( name );
		}

		wp.blocks.registerBlockType( name, {
			apiVersion: 3,
			title: config.title,
			description: config.description,
			category: config.category || 'manacore',
			icon: config.icon || 'index-card',
			keywords: [],
			supports: { html: false, anchor: true },
			attributes: config.attributes,
			edit: function ( props ) {
				var blockProps = useBlockProps( { className: 'manacore-account-panel' } );
				var tab        = props.attributes.tab;

				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( 'تب', 'manacore' ), initialOpen: true },
							el( SelectControl, {
								key: 'tab',
								label: __( 'این ظرف کدام تب است؟', 'manacore' ),
								value: tab,
								options: toOptions( data.accountTabs ),
								onChange: setter( props, 'tab' ),
								__nextHasNoMarginBottom: true,
							} ),
							el( TextControl, {
								key: 'label',
								label: __( 'برچسب اختیاری', 'manacore' ),
								value: props.attributes.label,
								onChange: setter( props, 'label' ),
								help: __( 'فقط در فهرست بلوک‌ها دیده می‌شود؛ روی نمایش اثر ندارد.', 'manacore' ),
								__nextHasNoMarginBottom: true,
							} )
						)
					),
					el(
						'section',
						Object.assign( {}, blockProps, { 'data-panel': tab } ),
						el( InnerBlocks, { template: [], templateLock: false } )
					)
				);
			},
			save: function () {
				return el( InnerBlocks.Content );
			},
		} );
	} )();

	window.manaCoreBlockUtils = {
		toOptions: toOptions,
		taxonomyOptions: taxonomyOptions,
		termsOf: termsOf,
		MultiSelect: MultiSelect,
		setter: setter,
		intSetter: intSetter,
		has: has,
		splitList: splitList,
		headerPanel: headerPanel,
		queryPanels: queryPanels,
		layoutPanel: layoutPanel,
		cardPanel: cardPanel,
		visibilityPanel: visibilityPanel,
		advancedPanel: advancedPanel,
		makeEdit: makeEdit,
	};
} )( window.wp );

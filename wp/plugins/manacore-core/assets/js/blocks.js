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
 *   ۹) پیشرفته
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

	var ServerSideRender = wp.serverSideRender;
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
				value: a.imageRatio,
				options: toOptions( data.imageRatios ),
				onChange: setter( props, 'imageRatio' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( SelectControl, {
				key: 'titleTag',
				label: __( 'تگ عنوان کارت', 'manacore' ),
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
				label: __( 'کلاس CSS افزوده‌ی کارت', 'manacore' ),
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

		return el(
			PanelBody,
			{ key: 'advanced', title: __( 'پیشرفته', 'manacore' ), initialOpen: false },
			el( TextControl, {
				label: __( 'کلاس CSS افزوده', 'manacore' ),
				value: a.extraClass,
				onChange: setter( props, 'extraClass' ),
				__nextHasNoMarginBottom: true,
			} ),
			el( TextControl, {
				label: __( 'شناسه‌ی HTML (لنگر)', 'manacore' ),
				help: __( 'برای پیوند دادن به این بخش، مثال: latest-movies', 'manacore' ),
				value: a.anchor,
				onChange: setter( props, 'anchor' ),
				__nextHasNoMarginBottom: true,
			} ),
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

	/**
	 * ورودی «شناسه‌ی اثر» برای بلوک‌های تک‌اثری.
	 *
	 * @param {Object} props props بلوک.
	 * @return {Object|null} کنترل.
	 */
	function postIdControl( props ) {
		if ( ! has( props, 'postId' ) ) {
			return null;
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
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
					] ),
				];

			case 'manacore/download-links':
				return [
					optionsPanel( props, __( 'تنظیمات لینک‌ها', 'manacore' ), [
						{
							attr: 'boxStyle',
							type: 'select',
							label: __( 'سبک ظاهری جعبه', 'manacore' ),
							options: toOptions( data.downloadStyles ),
						},
						{ attr: 'showTabs', type: 'toggle', label: __( 'تب‌بندی فصل‌ها', 'manacore' ) },
						{ attr: 'openFirst', type: 'toggle', label: __( 'باز بودن اولین گروه', 'manacore' ) },
						{ attr: 'showIcon', type: 'toggle', label: __( 'نمایش آیکون نوع', 'manacore' ) },
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
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
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
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
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
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
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
						{ attr: 'showPoster', type: 'toggle', label: __( 'نمایش پوستر پیش از پخش', 'manacore' ) },
						{
							attr: 'metaKey',
							label: __( 'کلید فیلد آدرس ویدیو', 'manacore' ),
							help: __( 'پیش‌فرض: manacore_trailer_url', 'manacore' ),
						},
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
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
							],
						},
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

			case 'manacore/episodes-list':
				return [
					optionsPanel( props, __( 'تنظیمات قسمت‌ها', 'manacore' ), [
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
						{ attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ), help: __( '۰ = اثر جاری.', 'manacore' ) },
					] ),
				];
		}

		return [ postIdControl( props ) ? optionsPanel( props, __( 'تنظیمات', 'manacore' ), [ { attr: 'postId', type: 'number', label: __( 'شناسه‌ی اثر', 'manacore' ) } ] ) : null ];
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
